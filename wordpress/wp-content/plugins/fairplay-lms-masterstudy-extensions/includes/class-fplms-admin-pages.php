<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FairPlay_LMS_Admin_Pages {

    /**
     * @var FairPlay_LMS_Courses_Controller
     */
    private $courses;

    /**
     * @var FairPlay_LMS_Users_Controller
     */
    private $users;

    public function __construct(
        FairPlay_LMS_Courses_Controller $courses,
        FairPlay_LMS_Users_Controller $users
    ) {
        $this->courses = $courses;
        $this->users   = $users;
    }

    /**
     * INICIO / DASHBOARD
     */
    public function render_dashboard_page(): void {

        $current_user = wp_get_current_user();
        $name         = ( $current_user && $current_user->exists() )
            ? $current_user->display_name
            : 'Administrador';

        // Cursos para el widget (últimos N cursos)
        $course_cards = $this->courses->get_courses_summary( 6 );

        // Stats de estructuras vs “asignadas a cursos”
        $structure_stats = $this->users->get_structure_assignment_stats();
        ?>
        <div class="wrap fplms-dashboard">
            <h1 style="margin-bottom:1rem;">¡Saludos, <?php echo esc_html( $name ); ?>!</h1>

            <div class="fplms-dashboard-grid">

                <!-- Bloque: gráfico de estructuras -->
                <div class="fplms-dashboard-block">
                    <h2>Resumen de estructuras y asignación a cursos</h2>
                    <canvas id="fplmsStructuresChart" width="400" height="220"></canvas>
                    <p class="description">
                        Se muestra cuántas estructuras han sido creadas en la plataforma y
                        cuántas de ellas tienen al menos un usuario con cursos asignados.
                    </p>
                </div>

                <!-- Bloque: widget de cursos -->
                <div class="fplms-dashboard-block">
                    <h2>Cursos recientes</h2>

                    <?php if ( empty( $course_cards ) ) : ?>
                        <p>Aún no hay cursos creados en MasterStudy.</p>
                    <?php else : ?>
                        <div class="fplms-courses-widget">
                            <?php foreach ( $course_cards as $course ) : ?>
                                <div class="fplms-course-card">
                                    <div class="fplms-course-thumb">
                                        <?php if ( ! empty( $course['thumb_url'] ) ) : ?>
                                            <img src="<?php echo esc_url( $course['thumb_url'] ); ?>" alt="<?php echo esc_attr( $course['title'] ); ?>" />
                                        <?php else : ?>
                                            <span class="dashicons dashicons-welcome-learn-more"></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fplms-course-body">
                                        <h3><?php echo esc_html( $course['title'] ); ?></h3>
                                        <?php if ( ! empty( $course['teacher'] ) ) : ?>
                                            <p class="fplms-course-teacher">
                                                Docente: <?php echo esc_html( $course['teacher'] ); ?>
                                            </p>
                                        <?php endif; ?>

                                        <div class="fplms-course-actions">
                                            <a class="button button-primary"
                                               href="<?php echo esc_url( $course['view_url'] ); ?>"
                                               target="_blank" rel="noopener">
                                                Ver curso
                                            </a>
                                            <a class="button button-secondary"
                                               href="<?php echo esc_url( $course['edit_url'] ); ?>">
                                                Editar
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <style>
            .fplms-dashboard-grid{
                display:flex;
                flex-wrap:wrap;
                gap:20px;
                align-items:flex-start;
            }
            .fplms-dashboard-block{
                flex:1 1 420px;
                background:#fff;
                border:1px solid #ccd0d4;
                border-radius:6px;
                padding:16px 20px;
                box-shadow:0 1px 1px rgba(0,0,0,0.04);
            }
            .fplms-courses-widget{
                display:flex;
                flex-direction:column;
                gap:12px;
                max-height:420px;
                overflow:auto;
            }
            .fplms-course-card{
                display:flex;
                gap:12px;
                border:1px solid #e5e5e5;
                border-radius:6px;
                padding:10px;
                background:#f9fafb;
            }
            .fplms-course-thumb{
                width:80px;
                min-width:80px;
                height:60px;
                display:flex;
                align-items:center;
                justify-content:center;
                background:#e5f2ff;
                border-radius:4px;
                overflow:hidden;
            }
            .fplms-course-thumb img{
                width:100%;
                height:100%;
                object-fit:cover;
            }
            .fplms-course-thumb .dashicons{
                font-size:28px;
            }
            .fplms-course-body h3{
                margin:0 0 4px;
                font-size:14px;
            }
            .fplms-course-teacher{
                margin:0 0 8px;
                font-size:12px;
                color:#555d66;
            }
            .fplms-course-actions .button{
                margin-right:4px;
            }
            @media (max-width: 960px){
                .fplms-dashboard-grid{
                    flex-direction:column;
                }
            }
        </style>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            (function() {
                var ctx = document.getElementById('fplmsStructuresChart');
                if (!ctx) { return; }

                var labels    = [];
                var dataTotal = [];
                var dataUsed  = [];

                <?php
                foreach ( $structure_stats as $row ) {
                    printf(
                        "labels.push(%s); dataTotal.push(%d); dataUsed.push(%d);\n",
                        wp_json_encode( $row['label'] ),
                        (int) $row['total_terms'],
                        (int) $row['terms_with_courses']
                    );
                }
                ?>

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Estructuras creadas',
                                data: dataTotal,
                                backgroundColor: 'rgba(54, 162, 235, 0.6)'
                            },
                            {
                                label: 'Con cursos asignados',
                                data: dataUsed,
                                backgroundColor: 'rgba(75, 192, 192, 0.6)'
                            }
                        ]
                    },
                    options: {
                        plugins: {
                            legend: { position: 'bottom' }
                        },
                        responsive: true,
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
        <?php
    }

    public function render_progress_page(): void {
        ?>
        <div class="wrap">
            <h1>Avances</h1>
            <p>
                Aquí podrás construir vistas detalladas de avance por estructura.
                El resumen básico se ve en "Usuarios" y las estadísticas globales en "Informes".
            </p>
        </div>
        <?php
    }

    public function render_calendar_page(): void {
        ?>
        <div class="wrap">
            <h1>Calendario</h1>
            <p>Vista de calendario para cursos y actividades (pendiente de implementar).</p>
        </div>
        <?php
    }
}
