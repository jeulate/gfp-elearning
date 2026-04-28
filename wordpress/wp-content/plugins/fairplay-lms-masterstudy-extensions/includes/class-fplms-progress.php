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

                        if ( 'completed' === $status || $percent >= 100.0 ) {
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

        $cache_key = 'fplms_sdash_v8_' . $user_id;
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        $stats = [
            'enrolled'          => 0,
            'avg_progress'      => 0,
            'completed'         => 0,
            'in_progress_count' => 0,
            'certificates'      => 0,
            'hours'             => 0.0,
            'expiring_count'    => 0,
            'expiring_ids'      => [],
            'upcoming_count'    => 0,
            'upcoming_ids'      => [],
            'courses_list'      => [],
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

                if ( 'completed' === $status || $progress >= 100.0 ) {
                    $stats['completed']++;
                    $completed_ids[] = (int) $cid;
                } elseif ( $progress > 1 ) {
                    $stats['in_progress_count']++;
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
                   AND meta_key = 'end_time'
                   AND meta_value != ''
                   AND meta_value > 0"
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

        // Lista de cursos inscritos para la vista de calendario del estudiante
        if ( ! empty( $enrolled_ids ) ) {
            $cids_safe = implode( ',', array_map( 'intval', array_keys( $enrolled_ids ) ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $cal_dur_rows = (array) $wpdb->get_results(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta}
                 WHERE post_id IN ($cids_safe) AND meta_key = 'end_time'
                   AND meta_value != '' AND meta_value > 0"
            );
            $cal_dur_map = [];
            foreach ( $cal_dur_rows as $dr ) {
                $cal_dur_map[ (int) $dr->post_id ] = (int) $dr->meta_value;
            }
            foreach ( array_keys( $enrolled_ids ) as $cid ) {
                $post = get_post( $cid );
                if ( ! $post ) continue;
                $start_iso   = get_the_date( 'Y-m-d', $cid );
                $prog_data   = $enrolled_ids[ $cid ];
                $progress    = (float) ( $prog_data['progress'] ?? 0 );
                $status      = strtolower( (string) ( $prog_data['status'] ?? '' ) );
                $is_complete = ( 'completed' === $status || $progress >= 100.0 );
                $stats['courses_list'][] = [
                    'id'         => $cid,
                    'title'      => get_the_title( $cid ),
                    'view_url'   => (string) get_permalink( $cid ),
                    'date_start' => $start_iso,
                    'date_end'   => isset( $cal_dur_map[ $cid ] )
                        ? wp_date( 'Y-m-d', strtotime( $start_iso ) + $cal_dur_map[ $cid ] * DAY_IN_SECONDS )
                        : null,
                    'progress'   => (int) round( $progress ),
                    'completed'  => $is_complete,
                ];
            }
        }

        set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );
        return $stats;
    }

    /**
     * Estadísticas del panel /user-account/ para rol instructor/tutor.
     * Devuelve: created_courses, expiring_courses, total_students,
     *           avg_student_progress, total_certificates, expiring_ids, courses_list.
     */
    public function get_instructor_dashboard_stats( int $user_id ): array {
        global $wpdb;

        $cache_key = 'fplms_idash_v4_' . $user_id;
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
            'courses_list'         => [],
        ];

        // 1. Cursos publicados del instructor — ordenados por fecha de creación descendente
        $course_ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = %s AND post_author = %d AND post_status = 'publish'
             ORDER BY post_date DESC",
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

            // 5. Lista detallada de cursos para el panel frontend del instructor
            // Conteo de estudiantes por curso (evitar N queries)
            $students_by_course = [];
            if ( $ms_table ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
                $sc_rows = (array) $wpdb->get_results(
                    "SELECT course_id, COUNT(DISTINCT user_id) AS cnt FROM `{$ms_table}`
                     WHERE course_id IN ($in) GROUP BY course_id"
                );
                foreach ( $sc_rows as $sc ) {
                    $students_by_course[ (int) $sc->course_id ] = (int) $sc->cnt;
                }
            }

            // Metas de estructura en una sola query masiva (solo canal, sucursal, cargo)
            $struct_meta_keys = [
                FairPlay_LMS_Config::META_COURSE_CHANNELS => FairPlay_LMS_Config::TAX_CHANNEL,
                FairPlay_LMS_Config::META_COURSE_BRANCHES => FairPlay_LMS_Config::TAX_BRANCH,
                FairPlay_LMS_Config::META_COURSE_ROLES    => FairPlay_LMS_Config::TAX_ROLE,
            ];
            $struct_keys_list = array_keys( $struct_meta_keys );
            $struct_keys_sql  = implode( ',', array_fill( 0, count( $struct_keys_list ), '%s' ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $struct_rows = (array) $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
                     WHERE post_id IN ($in) AND meta_key IN ($struct_keys_sql)",
                    ...$struct_keys_list
                )
            );
            // Indexar por curso y tipo
            $struct_by_course = []; // [ cid => [ meta_key => [ids] ] ]
            foreach ( $struct_rows as $sr ) {
                $cid_s   = (int) $sr->post_id;
                $ids_arr = array_values( array_filter( array_map( 'intval', (array) maybe_unserialize( $sr->meta_value ) ) ) );
                if ( ! empty( $ids_arr ) ) {
                    $struct_by_course[ $cid_s ][ $sr->meta_key ] = $ids_arr;
                }
            }

            // Helper: resolver IDs de términos a nombres
            $term_cache    = [];
            $resolve_terms = function( array $ids, string $taxonomy ) use ( &$term_cache ): array {
                $names = [];
                foreach ( $ids as $tid ) {
                    $key = $taxonomy . '_' . $tid;
                    if ( ! isset( $term_cache[ $key ] ) ) {
                        $term = get_term( $tid, $taxonomy );
                        $term_cache[ $key ] = ( $term && ! is_wp_error( $term ) ) ? $term->name : null;
                    }
                    if ( $term_cache[ $key ] ) {
                        $names[] = $term_cache[ $key ];
                    }
                }
                return array_unique( $names );
            };

            $account_base = rtrim( home_url( '/user-account/edit-course/' ), '/' );

            // Duración de acceso por curso (días) — para calcular fecha de fin de vigencia
            $duration_map = [];
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $dur_rows = (array) $wpdb->get_results(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta}
                 WHERE post_id IN ($in) AND meta_key = 'end_time'
                   AND meta_value != '' AND meta_value > 0"
            );
            foreach ( $dur_rows as $dr ) {
                $duration_map[ (int) $dr->post_id ] = (int) $dr->meta_value;
            }

            foreach ( $course_ids as $cid ) {
                $post = get_post( $cid );
                if ( ! $post ) continue;

                $cid_structs = $struct_by_course[ $cid ] ?? [];

                $channels = $resolve_terms(
                    $cid_structs[ FairPlay_LMS_Config::META_COURSE_CHANNELS ] ?? [],
                    FairPlay_LMS_Config::TAX_CHANNEL
                );
                $branches = $resolve_terms(
                    $cid_structs[ FairPlay_LMS_Config::META_COURSE_BRANCHES ] ?? [],
                    FairPlay_LMS_Config::TAX_BRANCH
                );
                $roles = $resolve_terms(
                    $cid_structs[ FairPlay_LMS_Config::META_COURSE_ROLES ] ?? [],
                    FairPlay_LMS_Config::TAX_ROLE
                );

                $start_iso = get_the_date( 'Y-m-d', $cid );
                $end_iso   = isset( $duration_map[ $cid ] )
                    ? wp_date( 'Y-m-d', strtotime( $start_iso ) + $duration_map[ $cid ] * DAY_IN_SECONDS )
                    : null;

                $stats['courses_list'][] = [
                    'id'          => $cid,
                    'title'       => get_the_title( $cid ),
                    'edit_url'    => $account_base . '/' . $cid . '/',
                    'view_url'    => (string) get_permalink( $cid ),
                    'created'     => get_the_date( 'd/m/Y', $cid ),
                    'modified'    => get_the_modified_date( 'd/m/Y', $cid ),
                    'students'    => $students_by_course[ $cid ] ?? 0,
                    'channels'    => $channels,
                    'branches'    => $branches,
                    'roles'       => $roles,
                    'is_expiring' => in_array( $cid, $exp_ids, true ),
                    'status'      => $post->post_status,
                    'date_start'  => $start_iso,
                    'date_end'    => $end_iso,
                ];
            }
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
