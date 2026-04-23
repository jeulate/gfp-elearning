<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FairPlay_LMS_Progress_Service {

    /**
     * Devuelve detalle de progreso por usuario.
     */
    public function get_user_progress_detail( int $user_id ): array {

        $detail = [
            'completed'     => 0,
            'in_progress'   => 0,
            'not_started'   => 0,
            'failed'        => 0,
            'total_courses' => 0,
        ];

        if ( class_exists( 'STM_LMS_User' ) && method_exists( 'STM_LMS_User', 'get_user_courses' ) ) {
            try {
                $courses = \STM_LMS_User::get_user_courses( $user_id, '', '', '', '', true );

                if ( ! empty( $courses['course_ids'] ) && is_array( $courses['course_ids'] ) ) {

                    $detail['total_courses'] = count( $courses['course_ids'] );

                    foreach ( $courses['course_ids'] as $course_id ) {

                        $course_progress = null;

                        if ( ! empty( $courses['progress'] ) && is_array( $courses['progress'] ) && isset( $courses['progress'][ $course_id ] ) ) {
                            $course_progress = $courses['progress'][ $course_id ];
                        }

                        $status  = '';
                        $percent = 0.0;

                        if ( is_array( $course_progress ) ) {
                            if ( isset( $course_progress['status'] ) ) {
                                $status = strtolower( (string) $course_progress['status'] );
                            }
                            if ( isset( $course_progress['progress'] ) ) {
                                $percent = (float) $course_progress['progress'];
                            } elseif ( isset( $course_progress['percentage'] ) ) {
                                $percent = (float) $course_progress['percentage'];
                            }
                        }

                        if ( 'completed' === $status || $percent >= 99.9 ) {
                            $detail['completed']++;
                        } elseif ( in_array( $status, [ 'failed', 'failed_quiz', 'not_passed' ], true ) ) {
                            $detail['failed']++;
                        } elseif ( $percent > 0 ) {
                            $detail['in_progress']++;
                        } else {
                            $detail['not_started']++;
                        }
                    }
                }
            } catch ( \Throwable $e ) {
                // En caso de error con MasterStudy, devolvemos todo en 0.
            }
        }

        return $detail;
    }

    /**
     * Resumen legible de progreso.
     */
    public function get_user_progress_summary( int $user_id ): string {

        $detail = $this->get_user_progress_detail( $user_id );

        if ( $detail['total_courses'] <= 0 ) {
            $summary = 'Sin cursos asignados';
        } else {
            $summary = sprintf(
                '%d completados, %d en curso, %d no iniciados, %d reprobados (Total %d)',
                (int) $detail['completed'],
                (int) $detail['in_progress'],
                (int) $detail['not_started'],
                (int) $detail['failed'],
                (int) $detail['total_courses']
            );
        }

        return apply_filters( 'fplms_user_progress_summary', $summary, $user_id, $detail );
    }

    /**
     * Estadísticas globales (para gráficos en informes).
     */
    public function get_global_progress_stats(): array {

        $stats = [
            'completed'   => 0,
            'in_progress' => 0,
            'not_started' => 0,
            'failed'      => 0,
            'total_users' => 0,
        ];

        // Por defecto solo consideramos estudiantes (subscribers)
        $query = new WP_User_Query(
            [
                'role'   => 'subscriber',
                'number' => 1000,
            ]
        );

        $users = (array) $query->get_results();

        foreach ( $users as $user ) {
            $detail = $this->get_user_progress_detail( $user->ID );
            $stats['completed']   += $detail['completed'];
            $stats['in_progress'] += $detail['in_progress'];
            $stats['not_started'] += $detail['not_started'];
            $stats['failed']      += $detail['failed'];
            $stats['total_users']++;
        }

        return $stats;
    }

    /**
     * Estadísticas del panel /user-account/ para el rol estudiante.
     * Devuelve: enrolled, avg_progress, completed, certificates, hours.
     */
    public function get_student_dashboard_stats( int $user_id ): array {
        global $wpdb;

        $cache_key = 'fplms_sdash_v6_' . $user_id;
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        $stats = [
            'enrolled'       => 0,
            'avg_progress'   => 0,
            'completed'      => 0,
            'certificates'   => 0,
            'hours'          => 0.0,
            'expiring_count' => 0,
            'expiring_ids'   => [],
            'upcoming_count' => 0,
            'upcoming_ids'   => [],
        ];

        // 1. Matrículas — tabla custom MasterStudy o usermeta como fallback
        $enrolled_ids = [];
        $ms_table     = null;
        foreach ( [ $wpdb->prefix . 'stm_lms_user_courses', $wpdb->prefix . 'stm_lms_users' ] as $t ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) {
                $ms_table = $t;
                break;
            }
        }

        if ( $ms_table ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT course_id, progress_percent, status FROM `{$ms_table}` WHERE user_id = %d",
                    $user_id
                )
            );
            foreach ( $rows as $row ) {
                $cid = (int) $row->course_id;
                if ( $cid > 0 ) {
                    $enrolled_ids[ $cid ] = [
                        'progress' => (float) $row->progress_percent,
                        'status'   => (string) $row->status,
                    ];
                }
            }
        }

        // Fallback: usermeta (matrículas vía meta stm_lms_course_NNN)
        if ( empty( $enrolled_ids ) ) {
            $meta_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT meta_key, meta_value FROM {$wpdb->usermeta}
                     WHERE user_id = %d AND meta_key REGEXP '^stm_lms_course_[0-9]+$'",
                    $user_id
                )
            );
            foreach ( $meta_rows as $row ) {
                if ( preg_match( '/^stm_lms_course_(\d+)$/', $row->meta_key, $rm ) ) {
                    $cid       = (int) $rm[1];
                    $prog_data = maybe_unserialize( $row->meta_value );
                    $enrolled_ids[ $cid ] = is_array( $prog_data )
                        ? $prog_data
                        : [ 'progress' => (float) $prog_data, 'status' => '' ];
                }
            }
        }

        $stats['enrolled'] = count( $enrolled_ids );

        if ( $stats['enrolled'] > 0 ) {
            $total_progress = 0.0;
            $completed_ids  = [];

            foreach ( $enrolled_ids as $cid => $data ) {
                $progress = (float) ( $data['progress'] ?? 0 );
                $status   = strtolower( (string) ( $data['status'] ?? '' ) );
                $total_progress += $progress;

                if ( 'completed' === $status || $progress >= 99.9 ) {
                    $stats['completed']++;
                    $completed_ids[] = (int) $cid;
                }
            }

            $stats['avg_progress'] = (int) round( $total_progress / $stats['enrolled'] );

            // 2. Horas de formación por curso completado.
            // Lógica: duration_info es la base (ej: "10 hours").
            //         Si además existe video_duration (ej: "2 horas"), se suma al valor base.
            //         Si solo existe uno de los dos, se usa ese.
            // Los valores son strings — se extrae el número con regex.
            $total_hours = 0.0;
            if ( ! empty( $completed_ids ) ) {
                $ids_safe = implode( ',', array_map( 'intval', $completed_ids ) );
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $dur_rows = $wpdb->get_results(
                    "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
                     WHERE post_id IN ($ids_safe)
                       AND meta_key IN ('duration_info', 'video_duration')
                       AND meta_value != ''"
                );
                // Acumular por curso: duration_info + video_duration (ambos si existen).
                $course_hours = [];
                foreach ( $dur_rows as $dr ) {
                    $cid_dr = (int) $dr->post_id;
                    if ( preg_match( '/(\d+(?:[.,]\d+)?)/', $dr->meta_value, $m ) ) {
                        $val = (float) str_replace( ',', '.', $m[1] );
                        if ( $val > 0 ) {
                            $course_hours[ $cid_dr ][ $dr->meta_key ] = $val;
                        }
                    }
                }
                foreach ( $course_hours as $keys ) {
                    // Base: duration_info; suma video_duration encima si existe.
                    $base  = $keys['duration_info'] ?? 0.0;
                    $extra = $keys['video_duration'] ?? 0.0;
                    $total_hours += $base + $extra;
                }
            }
            $stats['hours'] = round( $total_hours, 1 );

            // Cursos próximos a iniciar (status = 'not_started' o coming_soon_status activo).
            // Cursos con fecha de vencimiento configurada (expiring).
            $all_cids_safe = implode( ',', array_map( 'intval', array_keys( $enrolled_ids ) ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $expiring_ids = array_map( 'intval', (array) $wpdb->get_col(
                "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
                 WHERE post_id IN ($all_cids_safe)
                   AND meta_key IN ('access_duration','expiration_course','status_dates_end')
                   AND meta_value != ''
                   AND meta_value IS NOT NULL"
            ) );
            $stats['expiring_count'] = count( $expiring_ids );
            $stats['expiring_ids']   = $expiring_ids;

            // Cursos próximos: coming_soon_status = '1' o 'true' o similar.
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $upcoming_ids = array_map( 'intval', (array) $wpdb->get_col(
                "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
                 WHERE post_id IN ($all_cids_safe)
                   AND meta_key = 'coming_soon_status'
                   AND meta_value != ''
                   AND meta_value != '0'"
            ) );
            $stats['upcoming_count'] = count( $upcoming_ids );
            $stats['upcoming_ids']   = $upcoming_ids;
        }

        // 3. Certificados del usuario
        $cert_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type  IN ('stm-certificates','stm-certificate')
               AND p.post_status != 'trash'
               AND pm.meta_key   = 'user_id'
               AND pm.meta_value = %d",
            $user_id
        ) );
        // Fallback: post_author
        if ( 0 === $cert_count ) {
            $cert_count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(ID) FROM {$wpdb->posts}
                 WHERE post_type IN ('stm-certificates','stm-certificate')
                   AND post_status != 'trash'
                   AND post_author = %d",
                $user_id
            ) );
        }
        $stats['certificates'] = $cert_count;

        set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );
        return $stats;
    }

    /**
     * Estadísticas del panel /user-account/ para rol instructor/tutor.
     * Devuelve: created_courses, expiring_courses, total_students,
     *           avg_student_progress, total_certificates.
     */
    public function get_instructor_dashboard_stats( int $user_id ): array {
        global $wpdb;

        $cache_key = 'fplms_idash_v2_' . $user_id;
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        $stats = [
            'created_courses'      => 0,
            'expiring_courses'     => 0,
            'total_students'       => 0,
            'avg_student_progress' => 0,
            'total_certificates'   => 0,
            'expiring_ids'         => [],
        ];

        // 1. Cursos publicados del instructor
        $course_ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = %s AND post_author = %d AND post_status = 'publish'",
            FairPlay_LMS_Config::MS_PT_COURSE,
            $user_id
        ) ) );

        $stats['created_courses'] = count( $course_ids );

        if ( ! empty( $course_ids ) ) {
            $in = implode( ',', $course_ids ); // seguro: todos son intval

            // 2. Cursos con límite de duración ("por vencer") — count e IDs
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $exp_ids = array_map( 'intval', (array) $wpdb->get_col(
                "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
                 WHERE post_id IN ($in)
                   AND meta_key   = 'course_duration'
                   AND meta_value != ''
                   AND meta_value  > 0"
            ) );
            $stats['expiring_courses'] = count( $exp_ids );
            $stats['expiring_ids']     = $exp_ids;

            // 3. Estudiantes inscritos y progreso promedio
            $ms_table = null;
            foreach ( [ $wpdb->prefix . 'stm_lms_user_courses', $wpdb->prefix . 'stm_lms_users' ] as $t ) {
                if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) {
                    $ms_table = $t;
                    break;
                }
            }
            if ( $ms_table ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
                $rows = (array) $wpdb->get_results(
                    "SELECT user_id, progress_percent FROM `{$ms_table}` WHERE course_id IN ($in)"
                );
                if ( ! empty( $rows ) ) {
                    $unique_students = array_unique( array_column( $rows, 'user_id' ) );
                    $stats['total_students'] = count( $unique_students );
                    $total_prog = array_sum( array_column( $rows, 'progress_percent' ) );
                    $stats['avg_student_progress'] = (int) round( $total_prog / count( $rows ) );
                }
            }

            // 4. Certificados emitidos para los cursos del instructor
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $stats['total_certificates'] = (int) $wpdb->get_var(
                "SELECT COUNT(DISTINCT p.ID)
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_type  IN ('stm-certificates','stm-certificate')
                   AND p.post_status = 'publish'
                   AND pm.meta_key   = 'course_id'
                   AND pm.meta_value IN ($in)"
            );
        }

        set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );
        return $stats;
    }

    /**
     * Extrae IDs de lecciones/items del array curriculum de MasterStudy.
     * Soporta formato plano [id, id, ...], sección [['id'=>…]] y sección con
     * sublecciones [['lessons' => [id, id, ...]]].
     */
    private static function extract_lesson_ids( array $curriculum ): array {
        $ids = [];
        foreach ( $curriculum as $item ) {
            if ( is_numeric( $item ) ) {
                $ids[] = (int) $item;
            } elseif ( is_array( $item ) ) {
                if ( isset( $item['id'] ) && is_numeric( $item['id'] ) ) {
                    $ids[] = (int) $item['id'];
                }
                if ( isset( $item['lessons'] ) && is_array( $item['lessons'] ) ) {
                    foreach ( $item['lessons'] as $lesson ) {
                        if ( is_numeric( $lesson ) ) {
                            $ids[] = (int) $lesson;
                        } elseif ( is_array( $lesson ) && isset( $lesson['id'] ) ) {
                            $ids[] = (int) $lesson['id'];
                        }
                    }
                }
            }
        }
        return array_unique( $ids );
    }
}
