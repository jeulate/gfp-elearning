<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FairPlay_LMS_Admin_Pages {

    public function render_dashboard_page(): void {
        global $wpdb;

        // Filtros de periodo
        $period = isset($_GET['fplms_period']) ? sanitize_text_field($_GET['fplms_period']) : 'day';
        $period_options = [
            'day' => 'Hoy',
            'week' => 'Esta semana',
            'month' => 'Este mes'
        ];
                $login_counts = [];
                $completed_counts = [];
                $period_days = ($period === 'day') ? 1 : (($period === 'week') ? 7 : (int)date('t', $now));
                for ($i = 0; $i < $period_days; $i++) {
                    $date = date('Y-m-d', strtotime("+{$i} day", $start));
                    $dates[] = $date;
                    $login_counts[$date] = 0;
                    $completed_counts[$date] = 0;
                }

                // Validar existencia de la tabla de logins
                $login_table = $wpdb->prefix . 'fplms_user_logins';
                $table_exists = $wpdb->get_var($wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $login_table
                ));
                $logins = [];
                if ($table_exists === $login_table) {
                    $logins = $wpdb->get_results($wpdb->prepare(
                        "SELECT DATE(login_time) as date, COUNT(*) as count
                         FROM $login_table
                         WHERE login_time >= %s
                         GROUP BY DATE(login_time)",
                        date('Y-m-d 00:00:00', $start)
                    ), ARRAY_A);
                }

                // Consulta robusta de cursos completados (solo si meta_value es fecha)
                $completed = [];
                $meta_key = 'stm_lms_completed_courses';
                $usermeta_table = $wpdb->usermeta;
                // Solo contar si meta_value es una fecha válida
                $completed = $wpdb->get_results($wpdb->prepare(
                    "SELECT DATE(meta_value) as date, COUNT(*) as count
                     FROM $usermeta_table
                     WHERE meta_key = %s AND meta_value REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}' AND meta_value >= %s
                     GROUP BY DATE(meta_value)",
                    $meta_key,
                    date('Y-m-d 00:00:00', $start)
                ), ARRAY_A);

                foreach ($logins as $row) {
                    if (isset($login_counts[$row['date']])) {
                        $login_counts[$row['date']] = (int)$row['count'];
                    }
                }
                foreach ($completed as $row) {
                    if (isset($completed_counts[$row['date']])) {
                        $completed_counts[$row['date']] = (int)$row['count'];
                    }
                }
                ?>
                <style>
                    .fplms-dashboard {
                        background: #f5f7fa;
                        min-height: 100vh;
                        padding: 0;
                    }
                    .fplms-dashboard-content {
                        max-width: 1200px;
                        margin: 0 auto;
                        padding: 30px 20px;
                    }
                    .fplms-cards-grid {
                        display: grid;
                        grid-template-columns: repeat(3, 1fr);
                        gap: 25px;
                        margin-bottom: 40px;
                    }
                    .fplms-card {
                        background: #fff;
                        border-radius: 12px;
                        overflow: hidden;
                        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                        display: flex;
                        flex-direction: column;
                        border: 1px solid #e5e7eb;
                    }
                    .fplms-card:hover {
                        transform: translateY(-8px);
                        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
                        border-color: #F44336;
                    }
                    .fplms-card-header {
                        background: linear-gradient(135deg, #F44336 0%, #D32F2F 100%);
                        color: white;
                        padding: 35px 25px;
                        text-align: center;
                        position: relative;
                        overflow: hidden;
                    }
                    .fplms-card-header::before {
                        content: '';
                        position: absolute;
                        top: -50%;
                        right: -50%;
                        width: 200%;
                        height: 200%;
                        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
                        opacity: 0;
                        transition: opacity 0.3s ease;
                    }
                    .fplms-card:hover .fplms-card-header::before {
                        opacity: 1;
                    }
                    .fplms-card-icon {
                        font-size: 56px;
                        margin-bottom: 12px;
                        display: block;
                        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
                    }
                    .fplms-card-title {
                        font-size: 20px;
                        font-weight: 700;
                        margin: 0;
                        color: white;
                        letter-spacing: 0.3px;
                    }
                    .fplms-card-body {
                        padding: 25px;
                        flex-grow: 1;
                        display: flex;
                        flex-direction: column;
                    }
                    .fplms-card-description {
                        font-size: 14px;
                        color: #6b7280;
                        line-height: 1.7;
                        margin-bottom: 20px;
                        flex-grow: 1;
                    }
                    .fplms-card-footer {
                        padding-top: 0;
                        border-top: none;
                    }
                    .fplms-card-button {
                        display: block;
                        background: linear-gradient(135deg, #F44336 0%, #D32F2F 100%);
                        color: white;
                        padding: 12px 24px;
                        border-radius: 8px;
                        text-decoration: none;
                        font-size: 14px;
                        font-weight: 600;
                        transition: all 0.3s ease;
                        text-align: center;
                        border: none;
                        cursor: pointer;
                        box-shadow: 0 2px 8px rgba(244, 67, 54, 0.3);
                        position: relative;
                        overflow: hidden;
                    }
                    .fplms-card-button::before {
                        content: '';
                        position: absolute;
                        top: 0;
                        left: -100%;
                        width: 100%;
                        height: 100%;
                        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
                        transition: left 0.5s ease;
                    }
                    .fplms-card-button:hover::before {
                        left: 100%;
                    }
                    .fplms-card-button:hover {
                        background: linear-gradient(135deg, #D32F2F 0%, #B71C1C 100%);
                        box-shadow: 0 4px 16px rgba(244, 67, 54, 0.4);
                        transform: translateY(-2px);
                        text-decoration: none;
                        color: white;
                    }
                    @media (max-width: 1024px) {
                        .fplms-cards-grid {
                            grid-template-columns: repeat(2, 1fr);
                        }
                    }
                    @media (max-width: 640px) {
                        .fplms-cards-grid {
                            grid-template-columns: 1fr;
                        }
                    }
                    .fplms-welcome-container {
                        max-width: 1200px;
                        margin: 0 auto 40px auto;
                        background: #fff;
                        border-radius: 12px;
                        padding: 30px;
                        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                    }
                    .fplms-welcome-header {
                        display: flex;
                        align-items: center;
                        gap: 15px;
                        margin-bottom: 15px;
                    }
                    .fplms-welcome-icon {
                        font-size: 32px;
                        flex-shrink: 0;
                    }
                    .fplms-welcome-title {
                        margin: 0;
                        font-size: 24px;
                        font-weight: 700;
                        color: #1a1a1a;
                    }
                    .fplms-welcome-subtitle {
                        margin: 0 0 0 47px;
                        font-size: 14px;
                        color: #6b7280;
                        font-weight: 400;
                        line-height: 1.6;
                    }
                    @media (max-width: 768px) {
                        .fplms-welcome-header {
                            flex-direction: column;
                            align-items: flex-start;
                        }
                        .fplms-welcome-subtitle {
                            margin-left: 0;
                        }
                    }
                    .fplms-stats-grid {
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                        gap: 20px;
                        margin-bottom: 40px;
                    }
                    .fplms-stat-card {
                        background: white;
                        padding: 20px;
                        border-radius: 8px;
                        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
                        border-left: 4px solid #59100a;
                    }
                    .fplms-stat-label {
                        color: #666;
                        font-size: 12px;
                        text-transform: uppercase;
                        font-weight: 600;
                        margin-bottom: 5px;
                    }
                    .fplms-stat-value {
                        font-size: 28px;
                        font-weight: 700;
                        color: #59100a;
                    }
                    @media (max-width: 768px) {
                        .fplms-dashboard-header h1 {
                            font-size: 24px;
                        }
                        .fplms-cards-grid {
                            grid-template-columns: 1fr;
                        }
                        .fplms-dashboard-content {
                            margin: 20px auto;
                        }
                    }
                .fplms-activity-container {
                    max-width: 1200px;
                    margin: 0 auto 40px auto;
                    background: #fff;
                    border-radius: 12px;
                    padding: 30px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                }
                .fplms-activity-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 25px;
                    flex-wrap: wrap;
                    gap: 15px;
                }
                .fplms-activity-title {
                    margin: 0;
                    font-size: 24px;
                    font-weight: 700;
                    color: #1a1a1a;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .fplms-activity-title:before {
                    content: "📊";
                    font-size: 28px;
                }
                .fplms-period-selector {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .fplms-period-selector label {
                    font-weight: 600;
                    color: #555;
                    font-size: 14px;
                }
                .fplms-period-selector select {
                    padding: 8px 30px 8px 15px;
                    border: 2px solid #e0e0e0;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 500;
                    color: #333;
                    background-color: #fff;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    -webkit-appearance: none;
                    -moz-appearance: none;
                    appearance: none;
                }
                .fplms-period-selector select:hover {
                    border-color: #F44336;
                    background-color: #fff;
                }
                .fplms-period-selector select:focus {
                    outline: none;
                    border-color: #F44336;
                    box-shadow: 0 0 0 3px rgba(244, 67, 54, 0.1);
                }
                .fplms-chart-wrapper {
                    position: relative;
                    padding: 20px 10px;
                    background: linear-gradient(to bottom, #f8f9fa 0%, #ffffff 100%);
                    border-radius: 8px;
                    border: 1px solid #e9ecef;
                }
                @media (max-width: 768px) {
                    .fplms-activity-header {
                        flex-direction: column;
                        align-items: flex-start;
                    }
                    .fplms-activity-container {
                        padding: 20px 15px;
                    }
                }
                </style>
                <div class="fplms-dashboard">
            <div class="fplms-activity-container">
                <div class="fplms-activity-header">
                    <h2 class="fplms-activity-title">Actividad de la plataforma</h2>
                    <div class="fplms-period-selector">
                        <form method="get">
                            <input type="hidden" name="page" value="fplms-dashboard" />
                            <label for="fplms_period">Periodo:</label>
                            <select name="fplms_period" id="fplms_period" onchange="this.form.submit()">
                                <?php foreach($period_options as $key=>$label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($period, $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>
                <div class="fplms-chart-wrapper">
                    <canvas id="fplms-activity-chart" height="80"></canvas>
                </div>
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var ctx = document.getElementById('fplms-activity-chart').getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode($dates); ?>,
                            datasets: [
                                {
                                    label: 'Inicios de sesión',
                                    data: <?php echo json_encode(array_values($login_counts)); ?>,
                                    backgroundColor: 'rgba(33, 150, 243, 0.8)',
                                    borderColor: 'rgba(33, 150, 243, 1)',
                                    borderWidth: 2,
                                    borderRadius: 6,
                                    hoverBackgroundColor: 'rgba(33, 150, 243, 1)'
                                },
                                {
                                    label: 'Cursos completados',
                                    data: <?php echo json_encode(array_values($completed_counts)); ?>,
                                    backgroundColor: 'rgba(76, 175, 80, 0.8)',
                                    borderColor: 'rgba(76, 175, 80, 1)',
                                    borderWidth: 2,
                                    borderRadius: 6,
                                    hoverBackgroundColor: 'rgba(76, 175, 80, 1)'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                                legend: {
                                    position: 'top',
                                    align: 'end',
                                    labels: {
                                        boxWidth: 15,
                                        boxHeight: 15,
                                        padding: 15,
                                        font: {
                                            size: 13,
                                            weight: '600'
                                        },
                                        usePointStyle: true,
                                        pointStyle: 'circle'
                                    }
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    padding: 12,
                                    titleFont: {
                                        size: 14,
                                        weight: 'bold'
                                    },
                                    bodyFont: {
                                        size: 13
                                    },
                                    cornerRadius: 6,
                                    displayColors: true
                                }
                            },
                            scales: {
                                x: {
                                    stacked: false,
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        font: {
                                            size: 11,
                                            weight: '500'
                                        },
                                        color: '#666'
                                    }
                                },
                                y: {
                                    stacked: false,
                                    beginAtZero: true,
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.05)',
                                        drawBorder: false
                                    },
                                    ticks: {
                                        font: {
                                            size: 11,
                                            weight: '500'
                                        },
                                        color: '#666',
                                        precision: 0
                                    }
                                }
                            },
                            interaction: {
                                mode: 'index',
                                intersect: false
                            }
                        }
                    });
                });
                </script>
            </div>

            <!-- Sección de bienvenida y navegación -->
            <div class="fplms-welcome-container">
                <div class="fplms-welcome-header">
                    <div class="fplms-welcome-icon">🎯</div>
                    <h1 class="fplms-welcome-title">Dashboard FairPlay LMS</h1>
                </div>
                <p class="fplms-welcome-subtitle">Gestiona estructura, usuarios, cursos, avances y reportes</p>

                <div class="fplms-dashboard-content">
                <!-- Tarjetas de Acceso Rápido -->
                <div class="fplms-cards-grid">
                    <!-- Card: Estructuras -->
                    <div class="fplms-card">
                        <div class="fplms-card-header">
                            <span class="fplms-card-icon">🏢</span>
                            <br>
                            <h3 class="fplms-card-title">Estructuras</h3>
                        </div>
                        <div class="fplms-card-body">
                            <p class="fplms-card-description">Gestiona la estructura jerárquica de ciudades, canales, sucursales y cargos.</p>
                            <div class="fplms-card-footer">
                                <a href="<?php echo admin_url('admin.php?page=fplms-structures'); ?>" class="fplms-card-button">
                                    Ir a Estructuras →
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Card: Usuarios -->
                    <div class="fplms-card">
                        <div class="fplms-card-header">
                            <span class="fplms-card-icon">👥</span>
                            <br>
                            <h3 class="fplms-card-title">Usuarios</h3>
                        </div>
                        <div class="fplms-card-body">
                            <p class="fplms-card-description">Crea, edita y gestiona usuarios con permisos y asignación a estructuras.</p>
                            <div class="fplms-card-footer">
                                <a href="<?php echo admin_url('admin.php?page=fplms-users'); ?>" class="fplms-card-button">
                                    Ir a Usuarios →
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Card: Cursos -->
                    <div class="fplms-card">
                        <div class="fplms-card-header">
                            <span class="fplms-card-icon">📚</span>
                            <br>
                            <h3 class="fplms-card-title">Cursos</h3>
                        </div>
                        <div class="fplms-card-body">
                            <p class="fplms-card-description">Configura visibilidad de cursos por estructura y asigna a usuarios.</p>
                            <div class="fplms-card-footer">
                                <a href="<?php echo admin_url('admin.php?page=fplms-courses'); ?>" class="fplms-card-button">
                                    Ir a Cursos →
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Card: Avances -->
                    <div class="fplms-card">
                        <div class="fplms-card-header">
                            <span class="fplms-card-icon">📊</span>
                            <br>
                            <h3 class="fplms-card-title">Avances</h3>
                        </div>
                        <div class="fplms-card-body">
                            <p class="fplms-card-description">Revisa el progreso de los usuarios en cursos por estructura jerárquica.</p>
                            <div class="fplms-card-footer">
                                <a href="<?php echo admin_url('admin.php?page=fplms-progress'); ?>" class="fplms-card-button">
                                    Ir a Avances →
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Card: Calendario -->
                    <div class="fplms-card">
                        <div class="fplms-card-header">
                            <span class="fplms-card-icon">📅</span>
                            <br>
                            <h3 class="fplms-card-title">Calendario</h3>
                        </div>
                        <div class="fplms-card-body">
                            <p class="fplms-card-description">Vista de cursos vigentes y programación en calendario mensual/semanal.</p>
                            <div class="fplms-card-footer">
                                <a href="<?php echo admin_url('admin.php?page=fplms-calendar'); ?>" class="fplms-card-button">
                                    Ir a Calendario →
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Card: Informes -->
                    <div class="fplms-card">
                        <div class="fplms-card-header">
                            <span class="fplms-card-icon">📈</span>
                            <br>
                            <h3 class="fplms-card-title">Informes</h3>
                        </div>
                        <div class="fplms-card-body">
                            <p class="fplms-card-description">Genera reportes detallados de uso, progreso y estadísticas generales.</p>
                            <div class="fplms-card-footer">
                                <a href="<?php echo admin_url('admin.php?page=fplms-reports'); ?>" class="fplms-card-button">
                                    Ir a Informes →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>
        <?php
    }

    public function render_progress_page(): void {
        ?>
        <div class="wrap">
            <h1>Avances</h1>
            <p>
                Aquí podrás construir vistas detalladas de avance por estructura (ciudad, canal, sucursal, cargo).
                Por ahora, el resumen básico de progreso por usuario se muestra en la sección "Usuarios" y las
                estadísticas globales con gráficos en "Informes".
            </p>
        </div>
        <?php
    }

    public function render_calendar_page(): void {
        ?>
        <div class="wrap">
            <h1>Calendario</h1>
            <p>Aquí mostraremos cursos vigentes y programación (vista mensual / semanal) en una siguiente iteración.</p>
        </div>
        <?php
    }
}
