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

        // Inicializar variables de fecha y periodo
        $now = current_time('timestamp');
        $dates = [];
        $login_counts        = [];
        $completed_counts     = [];
        $not_completed_counts = [];

        // Calcular inicio del periodo según selección
        if ($period === 'day') {
            $start = strtotime('today', $now);
            $period_days = 1;
        } elseif ($period === 'week') {
            // Inicio de la semana (lunes)
            $start = strtotime('monday this week', $now);
            $period_days = 7;
        } else { // month
            // Inicio del mes
            $start = strtotime(date('Y-m-01', $now));
            $period_days = (int)date('t', $now);
        }

        // Generar array de fechas del periodo
        $date_labels = []; // Para labels del gráfico formateadas
        $activity_counts = []; // Para actividad continua
        for ($i = 0; $i < $period_days; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} day", $start));
            $dates[] = $date;
            $login_counts[$date]        = 0;
            $completed_counts[$date]     = 0;
            $activity_counts[$date]      = 0;
            $not_completed_counts[$date] = 0;
            
            // Formatear etiqueta según el periodo (sin hora: solo fecha visible en el gráfico)
            if ($period === 'day') {
                $date_labels[] = date('d/m', strtotime($date));
            } elseif ($period === 'week') {
                $date_labels[] = date('D d', strtotime($date)); // "Lun 20"
            } else {
                $date_labels[] = date('d/m', strtotime($date)); // "20/01"
            }
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

        // Validar existencia de la tabla de actividad
        $activity_table = $wpdb->prefix . 'fplms_user_activity';
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $activity_table
        ));
        $activities = [];
        if ($table_exists === $activity_table) {
            $activities = $wpdb->get_results($wpdb->prepare(
                "SELECT DATE(activity_time) as date, COUNT(DISTINCT user_id) as count
                 FROM $activity_table
                 WHERE activity_time >= %s
                 GROUP BY DATE(activity_time)",
                date('Y-m-d 00:00:00', $start)
            ), ARRAY_A);
        }

        // Cursos completados: tabla stm_lms_user_courses (MasterStudy v3+) o stm_lms_users (v2).
        // start_time es Unix timestamp de matrícula; lo usamos como proxy de fecha ya que
        // MasterStudy no almacena una columna de fecha de finalización separada.
        $completed   = [];
        $ms_uc_table = null;
        foreach ( [ $wpdb->prefix . 'stm_lms_user_courses', $wpdb->prefix . 'stm_lms_users' ] as $_t ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $_t ) ) === $_t ) {
                $ms_uc_table = $_t;
                break;
            }
        }
        if ( $ms_uc_table ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $completed = $wpdb->get_results( $wpdb->prepare(
                "SELECT DATE(FROM_UNIXTIME(start_time)) as date, COUNT(DISTINCT user_id) as count
                 FROM `{$ms_uc_table}`
                 WHERE (status IN ('completed', 'passed') OR progress_percent >= 100)
                   AND start_time >= %d
                 GROUP BY DATE(FROM_UNIXTIME(start_time))",
                $start
            ), ARRAY_A );

            // Inscritos que NO han completado ningún curso en el mismo período
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $not_completed_rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT DATE(FROM_UNIXTIME(start_time)) as date, COUNT(DISTINCT user_id) as count
                 FROM `{$ms_uc_table}`
                 WHERE NOT (status IN ('completed', 'passed') OR progress_percent >= 100)
                   AND start_time >= %d
                 GROUP BY DATE(FROM_UNIXTIME(start_time))",
                $start
            ), ARRAY_A );
        } else {
            $not_completed_rows = [];
        }

        foreach ($logins as $row) {
            if (isset($login_counts[$row['date']])) {
                $login_counts[$row['date']] = (int)$row['count'];
            }
        }
        foreach ($activities as $row) {
            if (isset($activity_counts[$row['date']])) {
                $activity_counts[$row['date']] = (int)$row['count'];
            }
        }
        foreach ($completed as $row) {
            if (isset($completed_counts[$row['date']])) {
                $completed_counts[$row['date']] = (int)$row['count'];
            }
        }
        foreach ($not_completed_rows as $row) {
            if (isset($not_completed_counts[$row['date']])) {
                $not_completed_counts[$row['date']] = (int)$row['count'];
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
            content: "";
        }
        .fplms-svg-icon {
            width: 28px;
            height: 28px;
            display: inline-block;
            vertical-align: middle;
            margin-right: 8px;
        }
        .fplms-card-svg-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 12px;
            display: block;
            stroke: white;
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
                    <h2 class="fplms-activity-title">
                        <svg class="fplms-svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                            <path d="M22 22H2"></path>
                            <path d="M21 22V14.5C21 13.6716 20.3284 13 19.5 13H16.5C15.6716 13 15 13.6716 15 14.5V22"></path>
                            <path d="M15 22V5C15 3.58579 15 2.87868 14.5607 2.43934C14.1213 2 13.4142 2 12 2C10.5858 2 9.87868 2 9.43934 2.43934C9 2.87868 9 3.58579 9 5V22"></path>
                            <path d="M9 22V9.5C9 8.67157 8.32843 8 7.5 8H4.5C3.67157 8 3 8.67157 3 9.5V22"></path>
                        </svg>
                        Actividad de la plataforma
                    </h2>
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
                    var notCompletedData = <?php echo json_encode(array_values($not_completed_counts)); ?>;
                    var ctx = document.getElementById('fplms-activity-chart').getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode($date_labels); ?>,
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
                                    label: 'Usuarios activos',
                                    data: <?php echo json_encode(array_values($activity_counts)); ?>,
                                    backgroundColor: 'rgba(255, 152, 0, 0.8)',
                                    borderColor: 'rgba(255, 152, 0, 1)',
                                    borderWidth: 2,
                                    borderRadius: 6,
                                    hoverBackgroundColor: 'rgba(255, 152, 0, 1)'
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
                                    displayColors: true,
                                    callbacks: {
                                        label: function(context) {
                                            var label = context.dataset.label || '';
                                            var value = context.parsed.y;
                                            if (context.datasetIndex === 2) {
                                                // Cursos completados: breakdown; fallback si no hay datos
                                                var notCompleted = notCompletedData[context.dataIndex] || 0;
                                                if (value === 0 && notCompleted === 0) {
                                                    return '  \u2139\ufe0f ' + label + ': Sin actividad registrada';
                                                }
                                                return [
                                                    '  \u2705 Completaron al menos 1 curso: ' + value + ' usuarios',
                                                    '  \u274C Sin completar (inscritos ese d\u00eda): ' + notCompleted + ' usuarios'
                                                ];
                                            }
                                            // Inicios de sesión y Usuarios activos: formato estándar
                                            return '  ' + label + ': ' + value;
                                        }
                                    }
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
                                mode: 'nearest',
                                intersect: true
                            }
                        }
                    });
                });
                </script>
            </div>

            <!-- Sección de usuarios activos ahora -->
            <?php
            // Obtener usuarios activos en los últimos 15 minutos
            $fifteen_min_ago = date('Y-m-d H:i:s', strtotime('-15 minutes'));
            $active_users = $wpdb->get_results($wpdb->prepare(
                "SELECT u.ID, u.display_name, u.user_email, um.meta_value as last_activity
                 FROM {$wpdb->users} u
                 INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
                 WHERE um.meta_key = 'last_activity' 
                 AND um.meta_value >= %s
                 ORDER BY um.meta_value DESC
                 LIMIT 10",
                $fifteen_min_ago
            ));
            
            if (!empty($active_users)) :
            ?>
            <div class="fplms-activity-container" style="margin-top: 20px;">
                <div class="fplms-activity-header">
                    <h2 class="fplms-activity-title">
                        <svg class="fplms-svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        Usuarios activos ahora
                    </h2>
                    <span style="font-size: 14px; color: #666;">Últimos 15 minutos</span>
                </div>
                <div style="padding: 20px; background: white;">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Última actividad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_users as $active_user) : 
                                $time_diff = human_time_diff(strtotime($active_user->last_activity), current_time('timestamp'));
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($active_user->display_name); ?></strong></td>
                                <td><?php echo esc_html($active_user->user_email); ?></td>
                                <td>
                                    <svg class="fplms-svg-icon" viewBox="0 0 24 24" fill="none" stroke="#4CAF50" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                    </svg>
                                    <span style="color: #4CAF50;">Activo</span> 
                                    (hace <?php echo esc_html($time_diff); ?>)
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Sección de bienvenida y navegación -->
            <div class="fplms-welcome-container">
                <div class="fplms-welcome-header">
                    <svg class="fplms-svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M16 4C18.175 4.01211 19.3529 4.10856 20.1213 4.87694C21 5.75562 21 7.16983 21 9.99826V15.9983C21 18.8267 21 20.2409 20.1213 21.1196C19.2426 21.9983 17.8284 21.9983 15 21.9983H9C6.17157 21.9983 4.75736 21.9983 3.87868 21.1196C3 20.2409 3 18.8267 3 15.9983V9.99826C3 7.16983 3 5.75562 3.87868 4.87694C4.64706 4.10856 5.82497 4.01211 8 4"></path>
                        <path d="M9 13.4L10.7143 15L15 11" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M8 3.5C8 2.67157 8.67157 2 9.5 2H14.5C15.3284 2 16 2.67157 16 3.5V4.5C16 5.32843 15.3284 6 14.5 6H9.5C8.67157 6 8 5.32843 8 4.5V3.5Z"></path>
                    </svg>
                    <h1 class="fplms-welcome-title">Accesos directos LMS</h1>
                </div>
                <p class="fplms-welcome-subtitle">Gestiona estructura, usuarios, cursos, avances y reportes</p>

                <div class="fplms-dashboard-content">
                <!-- Tarjetas de Acceso Rápido -->
                <div class="fplms-cards-grid">
                    <!-- Card: Estructuras -->
                    <div class="fplms-card">
                        <div class="fplms-card-header">
                            <svg class="fplms-card-svg-icon" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <path d="M3 9h18M3 15h18M3 3h18v16H3z"></path>
                                <line x1="9" y1="3" x2="9" y2="19"></line>
                                <line x1="15" y1="3" x2="15" y2="19"></line>
                            </svg>
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
                            <svg class="fplms-card-svg-icon" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
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
                            <svg class="fplms-card-svg-icon" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            </svg>
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

                    <!-- Card: Informes -->
                    <div class="fplms-card">
                        <div class="fplms-card-header">
                            <svg class="fplms-card-svg-icon" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5" stroke-linecap="round">
                                <path d="M22 22H2"></path>
                                <path d="M21 22V14.5C21 13.6716 20.3284 13 19.5 13H16.5C15.6716 13 15 13.6716 15 14.5V22"></path>
                                <path d="M15 22V5C15 3.58579 15 2.87868 14.5607 2.43934C14.1213 2 13.4142 2 12 2C10.5858 2 9.87868 2 9.43934 2.43934C9 2.87868 9 3.58579 9 5V22"></path>
                                <path d="M9 22V9.5C9 8.67157 8.32843 8 7.5 8H4.5C3.67157 8 3 8.67157 3 9.5V22"></path>
                            </svg>
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

                    <!-- Card: Bitácora -->
                    <div class="fplms-card">
                        <div class="fplms-card-header">
                            <svg class="fplms-card-svg-icon" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                <line x1="8" y1="9" x2="16" y2="9"></line>
                                <line x1="8" y1="13" x2="14" y2="13"></line>
                            </svg>
                            <h3 class="fplms-card-title">Bitácora</h3>
                        </div>
                        <div class="fplms-card-body">
                            <p class="fplms-card-description">Consulta el registro de auditoría y cambios en el sistema.</p>
                            <div class="fplms-card-footer">
                                <a href="<?php echo admin_url('admin.php?page=fairplay-lms-audit'); ?>" class="fplms-card-button">
                                    Ir a Bitácora →
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Card: Encuestas -->
                    <div class="fplms-card">
                        <div class="fplms-card-header">
                            <svg class="fplms-card-svg-icon" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <polyline points="11 5 17 5 17 11"></polyline>
                                <polyline points="18 4 7 15"></polyline>
                                <path d="M2 12c0-5.523 4.477-10 10-10s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12z"></path>
                            </svg>
                            <h3 class="fplms-card-title">Encuestas</h3>
                        </div>
                        <div class="fplms-card-body">
                            <p class="fplms-card-description">Gestiona encuestas de satisfacción y recopila retroalimentación.</p>
                            <div class="fplms-card-footer">
                                <a href="<?php echo admin_url('admin.php?page=fplms-surveys'); ?>" class="fplms-card-button">
                                    Ir a Encuestas →
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
