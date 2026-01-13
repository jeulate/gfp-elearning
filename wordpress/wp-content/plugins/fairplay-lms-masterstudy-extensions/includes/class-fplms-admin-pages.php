<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FairPlay_LMS_Admin_Pages {

    public function render_dashboard_page(): void {
        ?>
        <style>
            .fplms-dashboard {
                background: #f8f9fa;
                padding: 30px 0;
                min-height: 100vh;
            }

            .fplms-dashboard-header {
                background: linear-gradient(135deg, #F44336 0%, #59100a 100%);
                color: white;
                padding: 40px 20px;
                text-align: center;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }

            .fplms-dashboard-header h1 {
                margin: 0 0 10px 0;
                font-size: 32px;
                font-weight: 600;
                color: white;
            }

            .fplms-dashboard-header p {
                margin: 0;
                opacity: 0.95;
                font-size: 16px;
            }

            .fplms-dashboard-content {
                max-width: 1200px;
                margin: 40px auto;
                padding: 0 20px;
            }

            .fplms-cards-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 25px;
                margin-bottom: 40px;
            }

            .fplms-card {
                background: white;
                border-radius: 10px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                overflow: hidden;
                transition: all 0.3s ease;
                text-decoration: none;
                color: inherit;
                display: flex;
                flex-direction: column;
                height: 100%;
            }

            .fplms-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
            }

            .fplms-card-header {
                background: linear-gradient(135deg,  #F44336 0%, #59100a 100%);
                color: white;
                padding: 30px 20px;
                text-align: center;
                flex-shrink: 0;
            }

            .fplms-card-icon {
                font-size: 48px;
                margin-bottom: 15px;
                display: block;
            }

            .fplms-card-title {
                font-size: 20px;
                font-weight: 600;
                margin: 0;
                color: white;
            }

            .fplms-card-body {
                padding: 25px 20px;
                flex-grow: 1;
                display: flex;
                flex-direction: column;
            }

            .fplms-card-description {
                font-size: 14px;
                color: #666;
                line-height: 1.6;
                margin-bottom: 20px;
                flex-grow: 1;
            }

            .fplms-card-footer {
                padding-top: 15px;
                border-top: 1px solid #e2e4e7;
            }

            .fplms-card-button {
                display: inline-block;
                background: #da291c;
                color: white;
                padding: 10px 20px;
                border-radius: 5px;
                text-decoration: none;
                font-size: 14px;
                font-weight: 500;
                transition: background 0.2s ease;
                text-align: center;
                border: none;
                cursor: pointer;
            }

            .fplms-card-button:hover {
                background: #000000;
                text-decoration: none;
                color: white;
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
        </style>

        <div class="fplms-dashboard">
            <div class="fplms-dashboard-header">
                <h1>Dashboard FairPlay LMS</h1>
                <p>Gestiona estructura, usuarios, cursos, avances y reportes</p>
            </div>

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
