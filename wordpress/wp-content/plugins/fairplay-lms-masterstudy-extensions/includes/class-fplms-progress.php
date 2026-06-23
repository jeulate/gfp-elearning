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
     */
    public function get_student_dashboard_stats( int $user_id ): array {
        global $wpdb;

        // Forzar limpieza de caché para debugging (eliminar en producción)
        // delete_transient( 'fplms_sdash_v14_' . $user_id );

        $cache_key = 'fplms_sdash_v14_' . $user_id;
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

        // 1. Matrículas desde la tabla MasterStudy (solo cursos publish)
        $enrolled_ids = [];
        $ms_table     = $wpdb->prefix . 'stm_lms_user_courses';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT uc.course_id, uc.progress_percent, uc.status
                 FROM {$ms_table} uc
                 INNER JOIN {$wpdb->posts} p
                    ON p.ID = uc.course_id
                   AND p.post_type = 'stm-courses'
                   AND p.post_status = 'publish'
                 WHERE uc.user_id = %d",
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

        /**
         * NOTA: no re-filtrar por estructuras aquí.
         *
         * Esta fuente ya representa cursos publish en los que el usuario
         * está matriculado (tabla stm_lms_user_courses). Reaplicar filtros de
         * visibilidad en esta capa puede descontar cursos válidos y desalinear
         * el total del panel respecto a las matrículas reales.
         */

        /**
         * FILTRO 3: Eliminar cursos huérfanos
         */
        $enrolled_ids = array_filter(
            $enrolled_ids,
            static function ( $course_data, $course_id ): bool {
                $cid = (int) $course_id;
                if ( $cid <= 0 ) {
                    return false;
                }
                $post = get_post( $cid );
                return $post !== null && $post->post_type === 'stm-courses';
            },
            ARRAY_FILTER_USE_BOTH
        );

        // Recalcular enrolled DESPUÉS de todos los filtros.
        $stats['enrolled'] = count( $enrolled_ids );

        if ( $stats['enrolled'] > 0 ) {
            $total_progress = 0.0;
            $completed_ids  = [];

            foreach ( $enrolled_ids as $cid => $data ) {
                $progress = (float) ( $data['progress'] ?? 0 );
                $status   = strtolower( (string) ( $data['status'] ?? '' ) );
                $total_progress += $progress;

                if ( 'completed' === $status || 'passed' === $status || $progress >= 100.0 ) {
                    $stats['completed']++;
                    $completed_ids[] = (int) $cid;
                } elseif ( $progress > 1 ) {
                    $stats['in_progress_count']++;
                }
            }

            $stats['avg_progress'] = (int) round( $total_progress / $stats['enrolled'] );

            // Horas de formación:
            // Suma duración de lecciones y quizzes de cursos completados.
            // Excluye tareas/assignments.
            $total_minutes = 0;

            if ( ! empty( $completed_ids ) ) {
                $ids_safe = implode( ',', array_map( 'intval', $completed_ids ) );

                $duration_rows = $wpdb->get_results(
                    "
                    SELECT 
                        cs.course_id,
                        cm.post_id,
                        cm.post_type,
                        duration_meta.meta_value AS duration,
                        measure_meta.meta_value AS duration_measure
                    FROM {$wpdb->prefix}stm_lms_curriculum_materials cm
                    INNER JOIN {$wpdb->prefix}stm_lms_curriculum_sections cs
                        ON cs.id = cm.section_id
                    LEFT JOIN {$wpdb->postmeta} duration_meta
                        ON duration_meta.post_id = cm.post_id
                    AND duration_meta.meta_key = 'duration'
                    LEFT JOIN {$wpdb->postmeta} measure_meta
                        ON measure_meta.post_id = cm.post_id
                    AND measure_meta.meta_key = 'duration_measure'
                    WHERE cs.course_id IN ({$ids_safe})
                    AND cm.post_type IN ('stm-lessons', 'stm-quizzes')
                    AND duration_meta.meta_value IS NOT NULL
                    AND duration_meta.meta_value != ''
                    "
                );

                foreach ( $duration_rows as $row ) {
                    $duration = trim( (string) $row->duration );
                    $measure  = strtolower( trim( (string) $row->duration_measure ) );

                    if ( '' === $duration ) {
                        continue;
                    }

                    // Formato HH:MM o H:MM. Ejemplo: 1:15, 0:30.
                    if ( false !== strpos( $duration, ':' ) ) {
                        [ $hours, $minutes ] = array_pad( explode( ':', $duration ), 2, 0 );

                        $total_minutes += ( (int) $hours * 60 ) + (int) $minutes;
                        continue;
                    }

                    // Formato numérico. En quizzes normalmente viene "3" + duration_measure = minutes.
                    if ( is_numeric( $duration ) ) {
                        $value = (int) $duration;

                        if ( in_array( $measure, [ 'hour', 'hours' ], true ) ) {
                            $total_minutes += $value * 60;
                        } else {
                            $total_minutes += $value;
                        }
                    }
                }
            }

            $hours_part   = intdiv( $total_minutes, 60 );
            $minutes_part = $total_minutes % 60;

            $stats['hours_minutes'] = $total_minutes;

            $stats['hours'] = sprintf(
                '%d:%02d horas',
                $hours_part,
                $minutes_part
            );

            // Cursos próximos y por vencer
            $filtered_cids = array_keys( $enrolled_ids );
            if ( ! empty( $filtered_cids ) ) {
                $all_cids_safe = implode( ',', array_map( 'intval', $filtered_cids ) );
                
                $expiring_ids = array_map( 'intval', (array) $wpdb->get_col(
                    "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
                     WHERE post_id IN ($all_cids_safe)
                       AND meta_key = 'end_time'
                       AND meta_value != ''
                       AND meta_value > 0"
                ) );
                $stats['expiring_count'] = count( $expiring_ids );
                $stats['expiring_ids']   = $expiring_ids;

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
        }

        // Certificados - CORREGIDO
        // Los certificados de MasterStudy se almacenan como metadatos de usuario
        // con la clave 'stm_lms_certificate_code_XXX' donde XXX es el ID del curso
         $cert_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT uc.course_id)
                FROM {$wpdb->prefix}stm_lms_user_courses uc
                INNER JOIN {$wpdb->posts} p
                    ON p.ID = uc.course_id
                AND p.post_type = 'stm-courses'
                AND p.post_status = 'publish'
                INNER JOIN {$wpdb->usermeta} um
                    ON um.user_id = uc.user_id
                AND um.meta_key = CONCAT('stm_lms_certificate_code_', uc.course_id)
                AND um.meta_value != ''
                WHERE uc.user_id = %d
                AND uc.progress_percent >= 100",
                $user_id
            )
        );

        $stats['certificates'] = $cert_count;

        // Lista de cursos para la vista
        if ( ! empty( $enrolled_ids ) ) {
            $filtered_cids = array_keys( $enrolled_ids );
            if ( ! empty( $filtered_cids ) ) {
                $cids_safe = implode( ',', array_map( 'intval', $filtered_cids ) );
                
                $cal_dur_rows = (array) $wpdb->get_results(
                    "SELECT post_id, meta_value FROM {$wpdb->postmeta}
                     WHERE post_id IN ($cids_safe) AND meta_key = 'end_time'
                       AND meta_value != '' AND meta_value > 0"
                );
                $cal_dur_map = [];
                foreach ( $cal_dur_rows as $dr ) {
                    $cal_dur_map[ (int) $dr->post_id ] = (int) $dr->meta_value;
                }

                foreach ( $filtered_cids as $cid ) {
                    $post = get_post( $cid );
                    if ( ! $post ) {
                        continue;
                    }
                    
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
        }

        set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );
        return $stats;
    }

    /**
     * Estadísticas del panel para rol instructor.
     */
    public function get_instructor_dashboard_stats( int $user_id ): array {
        global $wpdb;

        $cache_key = 'fplms_idash_v5_' . $user_id;
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

        $course_ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = %s AND post_author = %d AND post_status IN ('publish','draft')
             ORDER BY post_date DESC",
            'stm-courses',
            $user_id
        ) ) );

        $stats['created_courses'] = count( $course_ids );

        if ( ! empty( $course_ids ) ) {
            $in = implode( ',', $course_ids );

            $exp_ids = array_map( 'intval', (array) $wpdb->get_col(
                "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
                 WHERE post_id IN ($in)
                   AND meta_key   = 'course_duration'
                   AND meta_value != ''
                   AND meta_value  > 0"
            ) );
            $stats['expiring_courses'] = count( $exp_ids );
            $stats['expiring_ids']     = $exp_ids;

            $ms_table = $wpdb->prefix . 'stm_lms_user_courses';
            if ( $ms_table ) {
                $rows = (array) $wpdb->get_results(
                    "SELECT user_id, progress_percent FROM {$ms_table} WHERE course_id IN ($in)"
                );
                if ( ! empty( $rows ) ) {
                    $unique_students = array_unique( array_column( $rows, 'user_id' ) );
                    $stats['total_students'] = count( $unique_students );
                    $total_prog = array_sum( array_column( $rows, 'progress_percent' ) );
                    $stats['avg_student_progress'] = (int) round( $total_prog / count( $rows ) );
                }
            }

            $stats['total_certificates'] = (int) $wpdb->get_var(
                "SELECT COUNT(DISTINCT p.ID)
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_type  IN ('stm-certificates','stm-certificate')
                   AND p.post_status = 'publish'
                   AND pm.meta_key   = 'course_id'
                   AND pm.meta_value IN ($in)"
            );

            $students_by_course = [];
            if ( $ms_table ) {
                $sc_rows = (array) $wpdb->get_results(
                    "SELECT course_id, COUNT(DISTINCT user_id) AS cnt FROM {$ms_table}
                     WHERE course_id IN ($in) GROUP BY course_id"
                );
                foreach ( $sc_rows as $sc ) {
                    $students_by_course[ (int) $sc->course_id ] = (int) $sc->cnt;
                }
            }

            $struct_meta_keys = [
                'fplms_course_channels',
                'fplms_course_branches',
                'fplms_course_roles',
            ];
            $struct_keys_sql  = implode( ',', array_fill( 0, count( $struct_meta_keys ), '%s' ) );
            $struct_rows = (array) $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
                     WHERE post_id IN ($in) AND meta_key IN ($struct_keys_sql)",
                    ...$struct_meta_keys
                )
            );
            
            $struct_by_course = [];
            foreach ( $struct_rows as $sr ) {
                $cid_s   = (int) $sr->post_id;
                $ids_arr = array_values( array_filter( array_map( 'intval', (array) maybe_unserialize( $sr->meta_value ) ) ) );
                if ( ! empty( $ids_arr ) ) {
                    $struct_by_course[ $cid_s ][ $sr->meta_key ] = $ids_arr;
                }
            }

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

            $duration_map = [];
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
                    $cid_structs['fplms_course_channels'] ?? [],
                    'fplms_channel'
                );
                $branches = $resolve_terms(
                    $cid_structs['fplms_course_branches'] ?? [],
                    'fplms_branch'
                );
                $roles = $resolve_terms(
                    $cid_structs['fplms_course_roles'] ?? [],
                    'fplms_job_role'
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