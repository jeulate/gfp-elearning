<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FairPlay_LMS_Reports_Controller {

    /**
     * @var FairPlay_LMS_Users_Controller
     */
    private $users;

    /**
     * @var FairPlay_LMS_Structures_Controller
     */
    private $structures;

    /**
     * @var FairPlay_LMS_Progress_Service
     */
    private $progress;

    public function __construct(
        FairPlay_LMS_Users_Controller $users,
        FairPlay_LMS_Structures_Controller $structures,
        FairPlay_LMS_Progress_Service $progress
    ) {
        $this->users      = $users;
        $this->structures = $structures;
        $this->progress   = $progress;
    }

    /**
     * Exportaciones CSV / Excel.
     */
    public function handle_export(): void {

        if ( ! is_admin() ) {
            return;
        }

        if ( ! isset( $_GET['page'] ) || 'fplms-reports' !== $_GET['page'] ) {
            return;
        }

        if ( ! isset( $_GET['fplms_export'] ) ) {
            return;
        }

        if ( ! current_user_can( FairPlay_LMS_Config::CAP_VIEW_REPORTS ) ) {
            return;
        }

        $format = sanitize_text_field( wp_unslash( $_GET['fplms_export'] ) );

        // Todos los usuarios (puedes limitar por rol si quieres).
        $users = $this->users->get_users_filtered_by_structure( 0, 0, 0, 0 );

        $filename_base = 'fairplay_usuarios_avance_' . gmdate( 'Ymd_His' );

        $headers_row = [
            'ID',
            'Nombre',
            'Email',
            'Roles',
            'Ciudad',
            'Canal',
            'Sucursal',
            'Cargo',
            'Avance (resumen)',
        ];

        if ( 'csv' === $format ) {

            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="' . $filename_base . '.csv"' );

            $output = fopen( 'php://output', 'w' );
            fputcsv( $output, $headers_row );

            foreach ( $users as $user ) {
                $city_name    = $this->structures->get_term_name_by_id( get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_CITY, true ) );
                $channel_name = $this->structures->get_term_name_by_id( get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_CHANNEL, true ) );
                $branch_name  = $this->structures->get_term_name_by_id( get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_BRANCH, true ) );
                $role_name    = $this->structures->get_term_name_by_id( get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_ROLE, true ) );
                $progress     = $this->progress->get_user_progress_summary( $user->ID );

                fputcsv(
                    $output,
                    [
                        $user->ID,
                        $user->display_name,
                        $user->user_email,
                        implode( ', ', $user->roles ),
                        $city_name,
                        $channel_name,
                        $branch_name,
                        $role_name,
                        $progress,
                    ]
                );
            }

            fclose( $output );
            exit;
        }

        if ( 'excel' === $format ) {

            header( 'Content-Type: application/vnd.ms-excel; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="' . $filename_base . '.xls"' );

            $output = fopen( 'php://output', 'w' );
            fputcsv( $output, $headers_row );

            foreach ( $users as $user ) {
                $city_name    = $this->structures->get_term_name_by_id( get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_CITY, true ) );
                $channel_name = $this->structures->get_term_name_by_id( get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_CHANNEL, true ) );
                $branch_name  = $this->structures->get_term_name_by_id( get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_BRANCH, true ) );
                $role_name    = $this->structures->get_term_name_by_id( get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_ROLE, true ) );
                $progress     = $this->progress->get_user_progress_summary( $user->ID );

                fputcsv(
                    $output,
                    [
                        $user->ID,
                        $user->display_name,
                        $user->user_email,
                        implode( ', ', $user->roles ),
                        $city_name,
                        $channel_name,
                        $branch_name,
                        $role_name,
                        $progress,
                    ]
                );
            }

            fclose( $output );
            exit;
        }
    }

    /**
     * Página de informes.
     */
    public function render_reports_page(): void {

        if ( ! current_user_can( FairPlay_LMS_Config::CAP_VIEW_REPORTS ) ) {
            wp_die( 'No tienes permisos para acceder a esta sección.' );
        }

        $stats = $this->progress->get_global_progress_stats();

        $completed   = (int) $stats['completed'];
        $in_progress = (int) $stats['in_progress'];
        $not_started = (int) $stats['not_started'];
        $failed      = (int) $stats['failed'];
        ?>
        <div class="wrap">
            <h1>Informes</h1>

            <h2>Exportar datos</h2>
            <p>
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'fplms-reports', 'fplms_export' => 'csv' ], admin_url( 'admin.php' ) ) ); ?>" class="button button-primary">
                    Exportar CSV
                </a>
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'fplms-reports', 'fplms_export' => 'excel' ], admin_url( 'admin.php' ) ) ); ?>" class="button">
                    Exportar Excel
                </a>
            </p>

            <h2>Resumen global de estados de cursos (alumnos)</h2>
            <table class="widefat striped" style="max-width:600px;">
                <thead>
                    <tr>
                        <th>Estado</th>
                        <th>Cantidad de cursos</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Completados</td>
                        <td><?php echo esc_html( $completed ); ?></td>
                    </tr>
                    <tr>
                        <td>En curso</td>
                        <td><?php echo esc_html( $in_progress ); ?></td>
                    </tr>
                    <tr>
                        <td>No iniciados</td>
                        <td><?php echo esc_html( $not_started ); ?></td>
                    </tr>
                    <tr>
                        <td>Reprobados</td>
                        <td><?php echo esc_html( $failed ); ?></td>
                    </tr>
                </tbody>
            </table>

            <h2 style="margin-top:2em;">Gráfico de estados</h2>
            <canvas id="fplmsProgressChart" width="600" height="300"></canvas>

            <p class="description">
                Este gráfico resume el total de cursos de todos los alumnos, separados por estado:
                completados, en curso, no iniciados y reprobados.
            </p>

            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                (function() {
                    var ctx = document.getElementById('fplmsProgressChart').getContext('2d');
                    var chart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: ['Completados', 'En curso', 'No iniciados', 'Reprobados'],
                            datasets: [{
                                label: 'Cantidad de cursos',
                                data: [
                                    <?php echo $completed; ?>,
                                    <?php echo $in_progress; ?>,
                                    <?php echo $not_started; ?>,
                                    <?php echo $failed; ?>
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { precision: 0 }
                                }
                            }
                        }
                    });
                })();
            </script>
        </div>
        <?php
    }
}
