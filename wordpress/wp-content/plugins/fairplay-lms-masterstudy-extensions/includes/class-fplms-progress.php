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
}
