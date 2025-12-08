<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FairPlay_LMS_Admin_Pages {

    public function render_dashboard_page(): void {
        ?>
        <div class="wrap">
            <h1>Dashboard FairPlay LMS</h1>
            <p>Aquí luego meteremos widgets/resúmenes (cursos activos, alumnos, avances, etc.).</p>
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
