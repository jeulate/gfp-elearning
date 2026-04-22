<?php
/**
 * FairPlay LMS Audit Log Admin Interface
 *
 * Interfaz administrativa para visualizar y gestionar la bitácora de auditoría
 *
 * @package    FairPlay_LMS
 * @subpackage FairPlay_LMS/admin
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase para la interfaz administrativa de la bitácora
 *
 * @since 1.0.0
 */
class FairPlay_LMS_Audit_Admin {

	/**
	 * Instancia del logger
	 *
	 * @var FairPlay_LMS_Audit_Logger
	 */
	private FairPlay_LMS_Audit_Logger $logger;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->logger = new FairPlay_LMS_Audit_Logger();
	}

	/**
	 * Registrar el menú de administración
	 *
	 * @return void
	 */
	public function register_admin_menu(): void {
		add_submenu_page(
			'fplms-dashboard',
			'Bitácora de Auditoría',
			'Bitácora',
			'manage_options',
			'fairplay-lms-audit',
			[ $this, 'render_audit_page' ]
		);
	}

	/**
	 * Renderizar página de auditoría
	 *
	 * @return void
	 */
	public function render_audit_page(): void {
		// Verificar permisos
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'No tienes permisos para acceder a esta página.' );
		}

		// Procesar exportaciones
		if ( isset( $_GET['action'] ) && check_admin_referer( 'fplms_export_audit' ) ) {
			if ( 'export_csv' === sanitize_key( $_GET['action'] ) ) {
				$this->export_csv();
				return;
			}
		}

		// Obtener filtros
		$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $per_page     = isset( $_GET['per_page'] ) ? intval( $_GET['per_page'] ) : 50;
        
        // Validar per_page (solo permitir valores específicos)
        if ( ! in_array( $per_page, [ 10, 20, 50, 100 ], true ) ) {
            $per_page = 50;
        }
        
        // Calcular offset para la paginación
        $offset = ( $current_page - 1 ) * $per_page;
        
		$filters = [
			'action'      => isset( $_GET['filter_action'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_action'] ) ) : '',
			'entity_type' => isset( $_GET['filter_entity'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_entity'] ) ) : '',
			'user_id'     => isset( $_GET['filter_user'] ) ? intval( $_GET['filter_user'] ) : 0,
			'date_from'   => isset( $_GET['filter_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_date_from'] ) ) : '',
			'date_to'     => isset( $_GET['filter_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_date_to'] ) ) : '',
			'limit'       => $per_page,
			'offset'      => $offset,
		];

		$logs        = $this->logger->get_logs( $filters );
		$total_logs  = $this->logger->count_logs( $filters );
		$total_pages = ceil( $total_logs / $per_page );

		// Obtener estadísticas
		$stats = $this->logger->get_statistics( [
			'date_from' => $filters['date_from'],
			'date_to'   => $filters['date_to'],
		] );

		?>
		<div class="wrap">
			<h1>📋 Bitácora de Auditoría FairPlay LMS</h1>
			<p class="description">
				Registro completo de todas las operaciones de asignación de estructuras en el sistema.
			</p>

			<?php
			// Mostrar mensajes de éxito/error
			if ( isset( $_GET['message'] ) ) {
				$message_type = sanitize_text_field( wp_unslash( $_GET['message'] ) );
				
				switch ( $message_type ) {
					case 'user_reactivated':
						?>
						<div class="notice notice-success is-dismissible" style="margin: 20px 0; padding: 12px 20px; background: #E8F5E9; border-left: 4px solid #4CAF50;">
							<p style="margin: 0; color: #2E7D32; font-weight: 500; display: flex; align-items: center; gap: 8px;">
								<svg viewBox="0 0 24 24" style="width: 18px; height: 18px; fill: #4CAF50; flex-shrink: 0;">
									<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
								</svg>
								<span>Usuario reactivado exitosamente. El usuario ya puede iniciar sesión en el sistema.</span>
							</p>
						</div>
						<?php
						break;
					
					case 'user_permanently_deleted':
						?>
						<div class="notice notice-success is-dismissible" style="margin: 20px 0; padding: 12px 20px; background: #E8F5E9; border-left: 4px solid #4CAF50;">
							<p style="margin: 0; color: #2E7D32; font-weight: 500; display: flex; align-items: center; gap: 8px;">
								<svg viewBox="0 0 24 24" style="width: 18px; height: 18px; fill: #4CAF50; flex-shrink: 0;">
									<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
								</svg>
								<span>Usuario eliminado permanentemente de manera exitosa.</span>
							</p>
						</div>
						<?php
						break;
				}
			}
			?>

			<?php $this->render_statistics( $stats ); ?>
			<?php $this->render_filters( $filters ); ?>
            <?php $this->render_logs_table( $logs, $current_page, $total_pages, $total_logs, $per_page ); ?>
		</div>
		<?php
	}

	/**
	 * Renderizar estadísticas
	 *
	 * @param array $stats Estadísticas
	 * @return void
	 */
	private function render_statistics( array $stats ): void {
		?>
		<div class="fplms-audit-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
			<div class="fplms-stat-card" style="background: #fff; padding: 15px; border-left: 4px solid #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<div style="font-size: 12px; color: #666; text-transform: uppercase;">Total Registros</div>
				<div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html( number_format( $stats['total_logs'] ) ); ?></div>
			</div>

			<?php if ( ! empty( $stats['actions_breakdown'] ) ) : ?>
				<?php $top_action = array_key_first( $stats['actions_breakdown'] ); ?>
				<div class="fplms-stat-card" style="background: #fff; padding: 15px; border-left: 4px solid #00a32a; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
					<div style="font-size: 12px; color: #666; text-transform: uppercase;">Acción Más Frecuente</div>
					<div style="font-size: 18px; font-weight: bold; color: #00a32a;"><?php echo esc_html( $top_action ); ?></div>
					<div style="font-size: 14px; color: #666;"><?php echo esc_html( number_format( $stats['actions_breakdown'][ $top_action ] ) ); ?> registros</div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $stats['top_users'] ) ) : ?>
				<?php $top_user = array_key_first( $stats['top_users'] ); ?>
				<div class="fplms-stat-card" style="background: #fff; padding: 15px; border-left: 4px solid #d63638; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
					<div style="font-size: 12px; color: #666; text-transform: uppercase;">Usuario Más Activo</div>
					<div style="font-size: 18px; font-weight: bold; color: #d63638;"><?php echo esc_html( $top_user ); ?></div>
					<div style="font-size: 14px; color: #666;"><?php echo esc_html( number_format( $stats['top_users'][ $top_user ] ) ); ?> acciones</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renderizar filtros
	 *
	 * @param array $filters Filtros actuales
	 * @return void
	 */
	private function render_filters( array $filters ): void {
		// URLs de exportación con los filtros activos
		$base = [ 'page' => 'fairplay-lms-audit' ];
		if ( ! empty( $filters['action'] ) )      { $base['filter_action']    = $filters['action']; }
		if ( ! empty( $filters['entity_type'] ) ) { $base['filter_entity']    = $filters['entity_type']; }
		if ( ! empty( $filters['date_from'] ) )   { $base['filter_date_from'] = $filters['date_from']; }
		if ( ! empty( $filters['date_to'] ) )     { $base['filter_date_to']   = $filters['date_to']; }
		$export_csv_url = wp_nonce_url( add_query_arg( $base + [ 'action' => 'export_csv' ], admin_url( 'admin.php' ) ), 'fplms_export_audit' );
		?>
		<div class="fplms-audit-filters" style="background: #fff; padding: 15px; margin: 20px 0; border: 1px solid #ccd0d4;">
			<form method="get" action="">
				<input type="hidden" name="page" value="fairplay-lms-audit">
				
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 15px;">
					<div>
						<label for="filter_action" style="display: block; font-weight: 600; margin-bottom: 5px;">Acción</label>
						<select name="filter_action" id="filter_action" style="width: 100%;">
							<option value="">Todas las acciones</option>
							<optgroup label="📘 Cursos">
								<option value="course_created" <?php selected( $filters['action'], 'course_created' ); ?>>Curso Creado</option>
								<option value="course_updated" <?php selected( $filters['action'], 'course_updated' ); ?>>Curso Actualizado</option>
								<option value="course_deleted" <?php selected( $filters['action'], 'course_deleted' ); ?>>Curso Eliminado</option>
							</optgroup>
							<optgroup label="📝 Lecciones">
								<option value="lesson_added" <?php selected( $filters['action'], 'lesson_added' ); ?>>Lección Agregada</option>
								<option value="lesson_updated" <?php selected( $filters['action'], 'lesson_updated' ); ?>>Lección Actualizada</option>
								<option value="lesson_deleted" <?php selected( $filters['action'], 'lesson_deleted' ); ?>>Lección Eliminada</option>
							</optgroup>
							<optgroup label="❓ Quizzes">
								<option value="quiz_added" <?php selected( $filters['action'], 'quiz_added' ); ?>>Quiz Agregado</option>
								<option value="quiz_updated" <?php selected( $filters['action'], 'quiz_updated' ); ?>>Quiz Actualizado</option>
								<option value="quiz_deleted" <?php selected( $filters['action'], 'quiz_deleted' ); ?>>Quiz Eliminado</option>
							</optgroup>
							<optgroup label="👥 Usuarios">
								<option value="user_deactivated" <?php selected( $filters['action'], 'user_deactivated' ); ?>>Usuario Desactivado</option>
								<option value="user_reactivated" <?php selected( $filters['action'], 'user_reactivated' ); ?>>Usuario Reactivado</option>
								<option value="user_permanently_deleted" <?php selected( $filters['action'], 'user_permanently_deleted' ); ?>>Usuario Eliminado</option>
							</optgroup>
							<optgroup label="🏢 Estructuras">
								<option value="structures_assigned" <?php selected( $filters['action'], 'structures_assigned' ); ?>>Estructuras Asignadas</option>
								<option value="structures_updated" <?php selected( $filters['action'], 'structures_updated' ); ?>>Estructuras Actualizadas</option>
								<option value="course_structures_synced_from_categories" <?php selected( $filters['action'], 'course_structures_synced_from_categories' ); ?>>Sincronización desde Categorías</option>
								<option value="channel_category_sync" <?php selected( $filters['action'], 'channel_category_sync' ); ?>>Canal→Categoría Sync</option>
							</optgroup>
						</select>
					</div>

					<div>
						<label for="filter_entity" style="display: block; font-weight: 600; margin-bottom: 5px;">Tipo de Entidad</label>
						<select name="filter_entity" id="filter_entity" style="width: 100%;">
							<option value="">Todas las entidades</option>
							<option value="course" <?php selected( $filters['entity_type'], 'course' ); ?>>📘 Curso</option>
							<option value="lesson" <?php selected( $filters['entity_type'], 'lesson' ); ?>>📝 Lección</option>
							<option value="quiz" <?php selected( $filters['entity_type'], 'quiz' ); ?>>❓ Quiz</option>
							<option value="user" <?php selected( $filters['entity_type'], 'user' ); ?>>👤 Usuario</option>
							<option value="channel" <?php selected( $filters['entity_type'], 'channel' ); ?>>📺 Canal</option>
							<option value="category" <?php selected( $filters['entity_type'], 'category' ); ?>>🏷️ Categoría</option>
						</select>
					</div>

					<div>
						<label for="filter_date_from" style="display: block; font-weight: 600; margin-bottom: 5px;">Desde</label>
						<input type="date" name="filter_date_from" id="filter_date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" style="width: 100%;">
					</div>

					<div>
						<label for="filter_date_to" style="display: block; font-weight: 600; margin-bottom: 5px;">Hasta</label>
						<input type="date" name="filter_date_to" id="filter_date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" style="width: 100%;">
					</div>

					<div>
						<label for="filter_period" style="display: block; font-weight: 600; margin-bottom: 5px;">Período Rápido</label>
						<select id="filter_period" style="width: 100%;">
							<option value="">— Seleccionar —</option>
							<?php
							$cy = (int) date( 'Y' );
							for ( $y = $cy; $y >= $cy - 3; $y-- ) {
								$y_esc = esc_attr( $y );
								echo "<optgroup label=\"{$y_esc}\">";
								echo "<option value=\"{$y_esc}-full\">{$y_esc} — Año completo</option>";
								echo "<option value=\"{$y_esc}-S1\">{$y_esc} — 1er Semestre (Ene–Jun)</option>";
								echo "<option value=\"{$y_esc}-S2\">{$y_esc} — 2do Semestre (Jul–Dic)</option>";
								echo "<option value=\"{$y_esc}-Q1\">{$y_esc} — T1 Trimestre (Ene–Mar)</option>";
								echo "<option value=\"{$y_esc}-Q2\">{$y_esc} — T2 Trimestre (Abr–Jun)</option>";
								echo "<option value=\"{$y_esc}-Q3\">{$y_esc} — T3 Trimestre (Jul–Sep)</option>";
								echo "<option value=\"{$y_esc}-Q4\">{$y_esc} — T4 Trimestre (Oct–Dic)</option>";
								echo '</optgroup>';
							}
							?>
						</select>
					</div>
				</div>

				<div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
					<button type="submit" class="button button-primary">🔍 Filtrar</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fairplay-lms-audit' ) ); ?>" class="button">🔄 Limpiar</a>
					<a href="<?php echo esc_url( $export_csv_url ); ?>"
					   class="button"
					   title="Exportar CSV (todos los registros filtrados, sin límite de paginación)"
					   style="display:inline-flex;align-items:center;gap:5px;margin-left:auto;">
						<svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor;flex-shrink:0;"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
						CSV completo
					</a>
				</div>
			</form>
		</div>
		<script>
		(function () {
			var periodSel = document.getElementById( 'filter_period' );
			var dateFrom  = document.getElementById( 'filter_date_from' );
			var dateTo    = document.getElementById( 'filter_date_to' );
			if ( ! periodSel || ! dateFrom || ! dateTo ) { return; }

			var periodMap = {
				'full': function (y) { return [ y + '-01-01', y + '-12-31' ]; },
				'S1':   function (y) { return [ y + '-01-01', y + '-06-30' ]; },
				'S2':   function (y) { return [ y + '-07-01', y + '-12-31' ]; },
				'Q1':   function (y) { return [ y + '-01-01', y + '-03-31' ]; },
				'Q2':   function (y) { return [ y + '-04-01', y + '-06-30' ]; },
				'Q3':   function (y) { return [ y + '-07-01', y + '-09-30' ]; },
				'Q4':   function (y) { return [ y + '-10-01', y + '-12-31' ]; },
			};

			// Detectar período al cargar si las fechas coinciden
			(function detectPeriod() {
				var from = dateFrom.value, to = dateTo.value;
				if ( ! from || ! to ) { return; }
				var year = from.substring( 0, 4 );
				for ( var code in periodMap ) {
					var d = periodMap[ code ]( year );
					if ( d[0] === from && d[1] === to ) {
						periodSel.value = year + '-' + code;
						return;
					}
				}
			} )();

			// Período → fechas
			periodSel.addEventListener( 'change', function () {
				var val = this.value;
				if ( ! val ) { return; }
				var year = val.substring( 0, 4 );
				var code = val.substring( 5 );
				if ( periodMap[ code ] ) {
					var d = periodMap[ code ]( year );
					dateFrom.value = d[0];
					dateTo.value   = d[1];
				}
			} );

			// Fecha manual → limpiar período
			dateFrom.addEventListener( 'change', function () { periodSel.value = ''; } );
			dateTo.addEventListener(   'change', function () { periodSel.value = ''; } );
		} )();
		</script>
		<?php
	}

	/**
	 * Renderizar tabla de logs
	 *
	 * @param array $logs        Array de logs
	 * @param int   $current_page Página actual
	 * @param int   $total_pages  Total de páginas
	 * @param int   $total_logs   Total de registros
	 * @param int   $per_page     Registros por página
	 * @return void
	 */
	private function render_logs_table( array $logs, int $current_page, int $total_pages, int $total_logs, int $per_page ): void {
		?>
		<style>
		.fplms-audit-dl-wrap{position:relative;display:inline-flex;}
		.fplms-audit-dl-btn{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;background:#2271b1;color:#fff!important;border:none;border-radius:4px;cursor:pointer;font-size:13px;font-weight:600;line-height:2;}
		.fplms-audit-dl-btn:hover{background:#135e96;}
		.fplms-audit-dl-drop{display:none;position:absolute;top:calc(100% + 4px);right:0;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.14);min-width:165px;z-index:9999;overflow:hidden;}
		.fplms-audit-dl-drop.open{display:block;}
		.fplms-audit-dl-item{display:flex;align-items:center;gap:8px;padding:10px 14px;font-size:13px;color:#374151;cursor:pointer;border:none;background:none;width:100%;text-align:left;}
		.fplms-audit-dl-item:hover{background:#f3f4f6;}
		.fplms-audit-cb{cursor:pointer;width:15px;height:15px;margin:0;}
		</style>
		<div class="fplms-audit-table">
			<div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4;">
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; gap:10px; flex-wrap:wrap;">
					<div style="display:flex;align-items:center;gap:12px;">
						<h3 style="margin: 0;">Registros de Auditoría (<?php echo esc_html( number_format( $total_logs ) ); ?> total)</h3>
						<span id="fplms-audit-sel-label" style="font-size:12px;color:#667eea;font-weight:600;background:#eef2ff;padding:2px 8px;border-radius:12px;display:none;"></span>
					</div>
					<div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
						<form method="get" action="" style="margin: 0;">
							<input type="hidden" name="page" value="fairplay-lms-audit">
							<?php
							foreach ( $_GET as $key => $value ) {
								if ( $key !== 'page' && $key !== 'per_page' && $key !== 'paged' ) {
									echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
								}
							}
							?>
							<label for="per_page" style="margin-right: 5px; font-weight: 600;">Mostrar:</label>
							<select name="per_page" id="per_page" onchange="this.form.submit()" style="padding: 6px 30px 6px 10px; min-width: 70px; border: 1px solid #8c8f94; border-radius: 4px; background-color: #fff; font-size: 13px; cursor: pointer;">
								<option value="10" <?php selected( $per_page, 10 ); ?>>10</option>
								<option value="20" <?php selected( $per_page, 20 ); ?>>20</option>
								<option value="50" <?php selected( $per_page, 50 ); ?>>50</option>
								<option value="100" <?php selected( $per_page, 100 ); ?>>100</option>
							</select>
							<span style="margin-left: 5px;">registros por página</span>
						</form>
						<div class="fplms-audit-dl-wrap">
							<button type="button" class="fplms-audit-dl-btn" onclick="fplmsAuditToggleDl(event)">
								<svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:#fff;flex-shrink:0;"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
								Descargar
								<svg viewBox="0 0 24 24" style="width:12px;height:12px;fill:#fff;margin-left:2px;"><path d="M7 10l5 5 5-5z"/></svg>
							</button>
							<div id="fplms-audit-dl-drop" class="fplms-audit-dl-drop">
								<button type="button" class="fplms-audit-dl-item" onclick="fplmsAuditExportXLS()">
									<svg viewBox="0 0 24 24" style="width:15px;height:15px;fill:#217346;"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 7V3.5L18.5 9H13zM7 17l2-3-2-3h1.7l1.3 2 1.3-2H13l-2 3 2 3h-1.7L10 18l-1.3 2H7z"/></svg>
									Excel (.xls)
								</button>
								<button type="button" class="fplms-audit-dl-item" onclick="fplmsAuditExportPDF()">
									<svg viewBox="0 0 24 24" style="width:15px;height:15px;fill:#e53e3e;"><path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5V11H19v2h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zM13 11h1V8.5h-1V11z"/></svg>
									PDF / Imprimir
								</button>
							</div>
						</div>
					</div>
				</div>
				
				<?php if ( empty( $logs ) ) : ?>
					<p style="text-align: center; padding: 40px 0; color: #666; display: flex; align-items: center; justify-content: center; gap: 10px; flex-direction: column;">
						<svg viewBox="0 0 24 24" style="width: 48px; height: 48px; fill: #9E9E9E;">
							<path d="M19 3H4.99c-1.11 0-1.98.89-1.98 2L3 19c0 1.1.88 2 1.99 2H19c1.1 0 2-.9 2-2V5c0-1.11-.9-2-2-2zm0 12h-4c0 1.66-1.35 3-3 3s-3-1.34-3-3H4.99V5H19v10z"/>
						</svg>
						<span>No se encontraron registros con los filtros aplicados.</span>
					</p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
						<thead>
							<tr>
								<th style="width:34px;text-align:center;"><input type="checkbox" id="fplms-audit-select-all" class="fplms-audit-cb" title="Seleccionar todo"></th>
								<th style="width: 60px;">ID</th>
								<th style="width: 150px;">Fecha/Hora</th>
								<th style="width: 120px;">Usuario</th>
								<th style="width: 180px;">Acción</th>
								<th style="width: 100px;">Tipo</th>
								<th>Entidad</th>
								<th style="width: 100px;">IP</th>
								<th style="width: 80px;">Detalles</th>
								<th style="width: 150px;">Acciones</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $logs as $log ) : ?>
								<?php $dt_r = new DateTime( $log['timestamp'] ); ?>
								<tr class="fplms-audit-row"
									data-log-id="<?php echo esc_attr( $log['id'] ); ?>"
									data-date="<?php echo esc_attr( $dt_r->format( 'd/m/Y' ) ); ?>"
									data-time="<?php echo esc_attr( $dt_r->format( 'H:i:s' ) ); ?>"
									data-user="<?php echo esc_attr( $log['user_name'] ); ?>"
									data-action="<?php echo esc_attr( $this->format_action_plain( $log['action'] ) ); ?>"
									data-type="<?php echo esc_attr( $log['entity_type'] ); ?>"
									data-entity="<?php echo esc_attr( $log['entity_title'] ?: '#' . $log['entity_id'] ); ?>"
									data-entity-id="<?php echo esc_attr( $log['entity_id'] ); ?>"
									data-ip="<?php echo esc_attr( $log['ip_address'] ?? '' ); ?>"
									data-old="<?php echo esc_attr( $log['old_value'] ?? '' ); ?>"
									data-new="<?php echo esc_attr( $log['new_value'] ?? '' ); ?>">
									<td style="text-align:center;"><input type="checkbox" class="fplms-audit-cb" style="margin:0;"></td>
									<td><strong><?php echo esc_html( $log['id'] ); ?></strong></td>
									<td><?php echo esc_html( $dt_r->format( 'd/m/Y H:i' ) ); ?></td>
									<td><?php echo esc_html( $log['user_name'] ); ?></td>
									<td><?php echo esc_html( $this->format_action( $log['action'] ) ); ?></td>
									<td>
										<span class="fplms-badge" style="background: #f0f0f1; padding: 3px 8px; border-radius: 3px; font-size: 12px;">
											<?php echo esc_html( $log['entity_type'] ); ?>
										</span>
									</td>
									<td>
										<strong><?php echo esc_html( $log['entity_title'] ?: "#{$log['entity_id']}" ); ?></strong>
										<span style="color: #666; font-size: 12px;">(ID: <?php echo esc_html( $log['entity_id'] ); ?>)</span>
									</td>
									<td><code style="font-size: 11px;"><?php echo esc_html( $log['ip_address'] ?? 'N/A' ); ?></code></td>
									<td>
										<button type="button" class="button button-small fplms-view-details" data-log-id="<?php echo esc_attr( $log['id'] ); ?>" style="display: inline-flex; align-items: center; gap: 5px;">
											<svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;">
												<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
											</svg>
											Ver
										</button>
									</td>
									<td>
										<?php $this->render_action_buttons( $log ); ?>
									</td>
								</tr>
								<tr id="fplms-details-<?php echo esc_attr( $log['id'] ); ?>" style="display: none;">
									<td colspan="10" style="background: #f9f9f9; padding: 15px;">
										<?php
										// Intentar mostrar cambios en formato mejorado si ambos valores son JSON
										if ( $log['old_value'] && $log['new_value'] ) {
											// Verificar si son JSON válidos
											$old_json = json_decode( $log['old_value'], true );
											$new_json = json_decode( $log['new_value'], true );
											
											if ( is_array( $old_json ) && is_array( $new_json ) ) {
												// Usar vista mejorada para JSON
												echo $this->render_changes_only( $log['old_value'], $log['new_value'], $log['action'] );
											} else {
												// Vista tradicional para datos no-JSON
												?>
												<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
													<div>
														<strong>Valor Anterior:</strong>
														<pre style="background: #fff; padding: 10px; border: 1px solid #ddd; max-height: 200px; overflow: auto; font-size: 11px;"><?php echo esc_html( $log['old_value'] ); ?></pre>
													</div>
													<div>
														<strong>Valor Nuevo:</strong>
														<pre style="background: #fff; padding: 10px; border: 1px solid #ddd; max-height: 200px; overflow: auto; font-size: 11px;"><?php echo esc_html( $log['new_value'] ); ?></pre>
													</div>
												</div>
												<?php
											}
										} else {
											// Si solo hay un valor
											?>
											<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
												<div>
													<strong>Valor Anterior:</strong>
													<pre style="background: #fff; padding: 10px; border: 1px solid #ddd; max-height: 200px; overflow: auto; font-size: 11px;"><?php echo esc_html( $log['old_value'] ?: 'N/A' ); ?></pre>
												</div>
												<div>
													<strong>Valor Nuevo:</strong>
													<pre style="background: #fff; padding: 10px; border: 1px solid #ddd; max-height: 200px; overflow: auto; font-size: 11px;"><?php echo esc_html( $log['new_value'] ?: 'N/A' ); ?></pre>
												</div>
											</div>
											<?php
										}
										?>
										<div style="margin-top: 10px; font-size: 12px; color: #666;">
											<strong>User Agent:</strong> <?php echo esc_html( $log['user_agent'] ?? 'N/A' ); ?>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php $this->render_pagination( $current_page, $total_pages, $per_page ); ?>
				<?php endif; ?>
			</div>
		</div>

		<!-- MODALES DE ACCIÓN DESDE BITÁCORA -->
		<style>
			/* Estilos para modales de la bitácora */
			.fplms-audit-modal-overlay {
				display: none;
				position: fixed;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				background: rgba(0, 0, 0, 0.5);
				z-index: 100000;
				animation: fadeIn 0.2s ease;
			}
			.fplms-audit-modal-overlay.active {
				display: flex;
				align-items: center;
				justify-content: center;
			}
			.fplms-audit-modal {
				background: white;
				border-radius: 12px;
				padding: 0;
				max-width: 480px;
				width: 90%;
				box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
				animation: slideUp 0.3s ease;
			}
			.fplms-audit-modal-header {
				padding: 24px 24px 16px;
				border-bottom: 1px solid #E0E0E0;
			}
			.fplms-audit-modal-header h3 {
				margin: 0;
				font-size: 20px;
				font-weight: 600;
				color: #212121;
				display: flex;
				align-items: center;
				gap: 12px;
			}
			.fplms-audit-modal-body {
				padding: 24px;
			}
			.fplms-audit-modal-body p {
				margin: 0 0 16px 0;
				color: #616161;
				line-height: 1.6;
			}
			.fplms-audit-modal-user-info {
				background: #F5F5F5;
				border-radius: 8px;
				padding: 16px;
				margin: 16px 0;
			}
			.fplms-audit-modal-user-info strong {
				display: block;
				color: #212121;
				margin-bottom: 4px;
			}
			.fplms-audit-modal-user-info span {
				color: #757575;
				font-size: 14px;
			}
			.fplms-audit-modal-footer {
				padding: 16px 24px;
				border-top: 1px solid #E0E0E0;
				display: flex;
				gap: 12px;
				justify-content: flex-end;
			}
			.fplms-audit-modal-footer button {
				padding: 10px 24px;
				border: none;
				border-radius: 6px;
				font-size: 14px;
				font-weight: 500;
				cursor: pointer;
				transition: all 0.2s ease;
			}
			.fplms-audit-modal-btn-cancel {
				background: #F5F5F5;
				color: #616161;
			}
			.fplms-audit-modal-btn-cancel:hover {
				background: #E0E0E0;
			}
			.fplms-audit-modal-btn-confirm {
				background: #2196F3;
				color: white;
			}
			.fplms-audit-modal-btn-confirm:hover {
				background: #1976D2;
			}
			.fplms-audit-modal-btn-confirm.success {
				background: #4CAF50;
			}
			.fplms-audit-modal-btn-confirm.success:hover {
				background: #388E3C;
			}
			.fplms-audit-modal-btn-confirm.danger {
				background: #F44336;
			}
			.fplms-audit-modal-btn-confirm.danger:hover {
				background: #D32F2F;
			}
			@keyframes fadeIn {
				from { opacity: 0; }
				to { opacity: 1; }
			}
			@keyframes slideUp {
				from { 
					opacity: 0;
					transform: translateY(20px);
				}
				to { 
					opacity: 1;
					transform: translateY(0);
				}
			}
		</style>

		<!-- Modal: Reactivar Usuario -->
		<div id="fplms-audit-reactivate-modal" class="fplms-audit-modal-overlay">
			<div class="fplms-audit-modal">
				<div class="fplms-audit-modal-header">
					<h3 style="display: flex; align-items: center; gap: 10px;">
						<svg viewBox="0 0 24 24" style="width: 24px; height: 24px; fill: #4CAF50; flex-shrink: 0;">
							<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
						</svg>
						<span>Reactivar Usuario</span>
					</h3>
				</div>
				<div class="fplms-audit-modal-body">
					<p>¿Estás seguro de que deseas <strong>reactivar</strong> este usuario?</p>
					<div class="fplms-audit-modal-user-info">
						<strong id="audit-reactivate-user-name"></strong>
						<span id="audit-reactivate-user-email"></span>
					</div>
					<p style="color: #4CAF50;">El usuario podrá iniciar sesión y acceder al sistema nuevamente.</p>
				</div>
				<div class="fplms-audit-modal-footer">
					<button type="button" class="fplms-audit-modal-btn-cancel" onclick="fplmsAuditCloseModal()">Cancelar</button>
					<button type="button" class="fplms-audit-modal-btn-confirm success" onclick="fplmsAuditConfirmAction()">Reactivar Usuario</button>
				</div>
			</div>
		</div>

		<!-- Modal: Eliminar Usuario -->
		<div id="fplms-audit-delete-modal" class="fplms-audit-modal-overlay">
			<div class="fplms-audit-modal">
				<div class="fplms-audit-modal-header">
					<h3 style="display: flex; align-items: center; gap: 10px;">
						<svg viewBox="0 0 24 24" style="width: 24px; height: 24px; fill: #F44336; flex-shrink: 0;">
							<path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
						</svg>
						<span>Eliminar Usuario Permanentemente</span>
					</h3>
				</div>
				<div class="fplms-audit-modal-body">
					<p style="display: flex; align-items: flex-start; gap: 8px;">
						<svg viewBox="0 0 24 24" style="width: 20px; height: 20px; fill: #FF9800; flex-shrink: 0; margin-top: 2px;">
							<path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>
						</svg>
						<span><strong>ADVERTENCIA:</strong> Esta acción es <strong style="color: #F44336;">permanente</strong> y no se puede deshacer.</span>
					</p>
					<div class="fplms-audit-modal-user-info">
						<strong id="audit-delete-user-name"></strong>
						<span id="audit-delete-user-email"></span>
					</div>
					<p style="color: #F44336;">Se eliminarán todos los datos del usuario, incluyendo su progreso en cursos y registros asociados.</p>
					<p><strong>¿Realmente deseas eliminar este usuario?</strong></p>
				</div>
				<div class="fplms-audit-modal-footer">
					<button type="button" class="fplms-audit-modal-btn-cancel" onclick="fplmsAuditCloseModal()">Cancelar</button>
					<button type="button" class="fplms-audit-modal-btn-confirm danger" onclick="fplmsAuditConfirmAction()">Eliminar Permanentemente</button>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Ver detalles de log
			$('.fplms-view-details').on('click', function() {
				var logId = $(this).data('log-id');
				var detailsRow = $('#fplms-details-' + logId);
				
				if (detailsRow.is(':visible')) {
					detailsRow.hide();
					$(this).html('<svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg> Ver');
				} else {
					$('.fplms-view-details').html('<svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg> Ver');
					$('[id^="fplms-details-"]').hide();
					detailsRow.show();
					$(this).html('<svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: currentColor;"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg> Cerrar');
				}
			});
		});

		// Variables globales para modales
		let auditCurrentActionData = {
			action: '',
			userId: null,
			userName: '',
			userEmail: ''
		};

		// Mostrar modal de acción
		function fplmsAuditShowModal(action, userId, userName, userEmail) {
			// Guardar datos de la acción
			auditCurrentActionData = {
				action: action,
				userId: userId,
				userName: userName,
				userEmail: userEmail
			};
			
			// Determinar qué modal mostrar
			let modalId = '';
			if (action === 'reactivate') {
				modalId = 'fplms-audit-reactivate-modal';
				document.getElementById('audit-reactivate-user-name').textContent = userName;
				document.getElementById('audit-reactivate-user-email').textContent = userEmail;
			} else if (action === 'delete') {
				modalId = 'fplms-audit-delete-modal';
				document.getElementById('audit-delete-user-name').textContent = userName;
				document.getElementById('audit-delete-user-email').textContent = userEmail;
			}
			
			// Mostrar modal
			const modal = document.getElementById(modalId);
			if (modal) {
				modal.classList.add('active');
				document.body.style.overflow = 'hidden';
			}
		}

		// Cerrar modal
		function fplmsAuditCloseModal() {
			const modals = document.querySelectorAll('.fplms-audit-modal-overlay');
			modals.forEach(modal => {
				modal.classList.remove('active');
			});
			document.body.style.overflow = '';
			
			// Limpiar datos
			auditCurrentActionData = {
				action: '',
				userId: null,
				userName: '',
				userEmail: ''
			};
		}

		// Confirmar acción
		function fplmsAuditConfirmAction() {
			if (!auditCurrentActionData.userId || !auditCurrentActionData.action) {
				return;
			}
			
			// Determinar la URL según la acción
			let actionParam = '';
			let nonceValue = '';
			
			if (auditCurrentActionData.action === 'reactivate') {
				actionParam = 'fplms_reactivate_user';
				nonceValue = '<?php echo wp_create_nonce( 'fplms_reactivate_user' ); ?>';
			} else if (auditCurrentActionData.action === 'delete') {
				actionParam = 'fplms_delete_user_permanently';
				nonceValue = '<?php echo wp_create_nonce( 'fplms_delete_user_permanently' ); ?>';
			}
			
			// Crear URL con nonce
			const actionUrl = '<?php echo admin_url( 'admin-post.php' ); ?>?action=' + actionParam + 
			                  '&user_id=' + auditCurrentActionData.userId + 
			                  '&_wpnonce=' + nonceValue;
			
			// Redirigir
			window.location.href = actionUrl;
		}

		// Cerrar modal al hacer clic fuera
		document.addEventListener('click', function(e) {
			if (e.target.classList.contains('fplms-audit-modal-overlay')) {
				fplmsAuditCloseModal();
			}
		});

		// Cerrar modal con tecla ESC
		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape') {
				fplmsAuditCloseModal();
			}
		});

		/* ── Dropdown Descargar ── */
		window.fplmsAuditToggleDl = function(e) {
			e.stopPropagation();
			var dd = document.getElementById('fplms-audit-dl-drop');
			if (dd) { dd.classList.toggle('open'); }
		};
		document.addEventListener('click', function(e) {
			var dd = document.getElementById('fplms-audit-dl-drop');
			if (dd && !e.target.closest('.fplms-audit-dl-wrap')) { dd.classList.remove('open'); }
		});

		/* ── Select-All checkbox ── */
		var selectAll = document.getElementById('fplms-audit-select-all');
		if (selectAll) {
			selectAll.addEventListener('change', function() {
				document.querySelectorAll('.fplms-audit-cb:not(#fplms-audit-select-all)').forEach(function(cb) {
					cb.checked = selectAll.checked;
				});
				fplmsAuditUpdateSelLabel();
			});
		}
		document.addEventListener('change', function(e) {
			if (e.target && e.target.classList.contains('fplms-audit-cb') && e.target.id !== 'fplms-audit-select-all') {
				fplmsAuditUpdateSelLabel();
			}
		});
		function fplmsAuditUpdateSelLabel() {
			var n   = document.querySelectorAll('.fplms-audit-cb:not(#fplms-audit-select-all):checked').length;
			var lbl = document.getElementById('fplms-audit-sel-label');
			if (!lbl) { return; }
			if (n > 0) { lbl.textContent = n + ' seleccionado' + (n > 1 ? 's' : ''); lbl.style.display = 'inline'; }
			else       { lbl.style.display = 'none'; }
		}

		/* ── Helpers de exportación ── */
		function fplmsHtmlEsc(s) {
			return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
		}
		function fplmsComputeChanges(oldStr, newStr) {
			if (!oldStr && !newStr) { return ''; }
			var old = {}, nw = {};
			try { old = JSON.parse(oldStr); } catch(e) {}
			try { nw  = JSON.parse(newStr); } catch(e) {}
			if (typeof old !== 'object' || old === null || typeof nw !== 'object' || nw === null) {
				var p = [];
				if (oldStr) { p.push('Anterior: ' + oldStr.substring(0,120)); }
				if (newStr) { p.push('Nuevo: '    + newStr.substring(0,120)); }
				return p.join(' | ');
			}
			var labels = {
				email:'Email', first_name:'Nombre', last_name:'Apellido', id_usuario:'ID Usuario',
				role:'Rol', city_id:'Ciudad', company_id:'Empresa', channel_id:'Canal',
				branch_id:'Sucursal', role_id:'Cargo', name:'Nombre', slug:'Slug',
				description:'Descripción', parent:'Padre', active:'Activo', status:'Estado',
				title:'Título', content:'Contenido', price:'Precio', duration:'Duración',
				level:'Nivel', passing_grade:'Nota Mínima', questions_count:'Nº Preguntas'
			};
			var keys = Object.keys(Object.assign({}, old, nw));
			var lines = [];
			keys.forEach(function(k) {
				var ov = (old[k] === undefined || old[k] === null) ? '' : (typeof old[k] === 'object' ? JSON.stringify(old[k]) : String(old[k]));
				var nv = (nw[k]  === undefined || nw[k]  === null) ? '' : (typeof nw[k]  === 'object' ? JSON.stringify(nw[k])  : String(nw[k]));
				if (ov !== nv) {
					var label = labels[k] || k.replace(/_/g,' ');
					if (ov.length > 70) { ov = ov.substring(0,67) + '...'; }
					if (nv.length > 70) { nv = nv.substring(0,67) + '...'; }
					lines.push(label + ': "' + (ov||'(vacío)') + '" → "' + (nv||'(vacío)') + '"');
				}
			});
			return lines.join('\n');
		}
		function fplmsAuditGetRows() {
			var checked = document.querySelectorAll('.fplms-audit-cb:not(#fplms-audit-select-all):checked');
			if (checked.length > 0) {
				return Array.from(checked).map(function(cb) { return cb.closest('tr.fplms-audit-row'); }).filter(Boolean);
			}
			return Array.from(document.querySelectorAll('tr.fplms-audit-row'));
		}

		/* ── Exportar Excel (.xls) ── */
		window.fplmsAuditExportXLS = function() {
			var rows = fplmsAuditGetRows();
			var selCount = document.querySelectorAll('.fplms-audit-cb:not(#fplms-audit-select-all):checked').length;
			var headers = ['ID','Fecha','Hora','Administrador','Acción','Tipo','Entidad','ID Entidad','Detalle de Cambios','IP'];
			var t = '<table border="1" style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:11px;">';
			t += '<thead><tr>' + headers.map(function(h) {
				return '<th style="background:#2271b1;color:#fff;padding:7px 10px;font-weight:bold;white-space:nowrap;">' + h + '</th>';
			}).join('') + '</tr></thead><tbody>';
			rows.forEach(function(row) {
				var d = row.dataset;
				var changes = fplmsComputeChanges(d.old || '', d['new'] || '');
				var vals = [d.logId, d.date, d.time, d.user, d.action, d.type, d.entity, d.entityId, changes, d.ip];
				t += '<tr>' + vals.map(function(v, i) {
					var wrap = i === 8 ? 'white-space:pre-wrap;max-width:300px;' : '';
					return '<td style="padding:5px 8px;vertical-align:top;' + wrap + '">' + fplmsHtmlEsc(v || '') + '</td>';
				}).join('') + '</tr>';
			});
			t += '</tbody></table>';
			var html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' + t + '</body></html>';
			var blob = new Blob(["\uFEFF" + html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
			var a = document.createElement('a');
			a.href = URL.createObjectURL(blob);
			a.download = 'bitacora-auditoria' + (selCount > 0 ? '-seleccion' : '') + '-' + new Date().toISOString().slice(0,10) + '.xls';
			document.body.appendChild(a);
			a.click();
			document.body.removeChild(a);
			URL.revokeObjectURL(a.href);
			var dd = document.getElementById('fplms-audit-dl-drop');
			if (dd) { dd.classList.remove('open'); }
		};

		/* ── Exportar PDF (ventana de impresión) ── */
		window.fplmsAuditExportPDF = function() {
			var rows = fplmsAuditGetRows();
			var selCount = document.querySelectorAll('.fplms-audit-cb:not(#fplms-audit-select-all):checked').length;
			var label = selCount > 0 ? selCount + ' seleccionado(s)' : rows.length + ' registro(s)';
			var headers = ['ID','Fecha','Hora','Administrador','Acción','Tipo','Entidad','Detalle de Cambios','IP'];
			var t = '<table><thead><tr>' + headers.map(function(h) { return '<th>' + h + '</th>'; }).join('') + '</tr></thead><tbody>';
			rows.forEach(function(row) {
				var d = row.dataset;
				var changes = fplmsComputeChanges(d.old || '', d['new'] || '').replace(/\n/g,'<br>');
				var vals = [d.logId, d.date, d.time, d.user, d.action, d.type, d.entity, changes, d.ip];
				t += '<tr>' + vals.map(function(v, i) {
					return '<td>' + (i === 7 ? (v||'') : fplmsHtmlEsc(v||'')) + '</td>';
				}).join('') + '</tr>';
			});
			t += '</tbody></table>';
			var win = window.open('', '_blank', 'width=1100,height=750');
			win.document.write('<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
				+ '<title>Bitácora de Auditoría (' + label + ')</title>'
				+ '<style>'
				+ 'body{font-family:Arial,sans-serif;font-size:11px;margin:20px;color:#1f2937;}'
				+ 'h2{font-size:16px;margin-bottom:6px;color:#1e3a5f;}'
				+ 'p.meta{font-size:11px;color:#6b7280;margin-bottom:14px;}'
				+ 'table{width:100%;border-collapse:collapse;}'
				+ 'th{background:#2271b1;color:#fff;padding:7px 8px;text-align:left;font-size:10px;white-space:nowrap;}'
				+ 'td{padding:5px 8px;border-bottom:1px solid #e5e7eb;vertical-align:top;font-size:10px;}'
				+ 'tr:nth-child(even) td{background:#f9fafb;}'
				+ '.btn{margin-top:16px;padding:8px 20px;background:#2271b1;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;}'
				+ '@media print{.btn{display:none;} body{margin:6px;}}'
				+ '</style></head><body>'
				+ '<h2>Bitácora de Auditoría — FairPlay LMS</h2>'
				+ '<p class="meta">' + label + ' &mdash; Generado: ' + new Date().toLocaleDateString('es-ES') + '</p>'
				+ t
				+ '<br><button class="btn" onclick="window.print()">Imprimir / Guardar como PDF</button>'
				+ '</body></html>');
			win.document.close();
			var dd = document.getElementById('fplms-audit-dl-drop');
			if (dd) { dd.classList.remove('open'); }
		};
		</script>
		<?php
	}

	/**
	 * Renderizar botones de acción para usuarios desactivados
	 *
	 * @param array $log Registro de auditoría
	 * @return void
	 */
	private function render_action_buttons( array $log ): void {
		// Solo mostrar botones para usuarios desactivados
		if ( $log['entity_type'] !== 'user' || $log['action'] !== 'user_deactivated' ) {
			echo '<span style="color: #999;">—</span>';
			return;
		}

		$user_id = $log['entity_id'];
		
		// Verificar si el usuario aún existe y está inactivo
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			echo '<span style="color: #999; font-size: 11px;">Usuario ya eliminado</span>';
			return;
		}

		$user_status = get_user_meta( $user_id, 'fplms_user_status', true );
		if ( $user_status !== 'inactive' ) {
			echo '<span style="color: #00a32a; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;"><svg viewBox="0 0 24 24" style="width: 14px; height: 14px; fill: #4CAF50;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg> Ya reactivado</span>';
			return;
		}

		$user_name = $user->display_name;
		$user_email = $user->user_email;

		?>
		<div style="display: flex; gap: 5px; flex-direction: column;">
			<button type="button" 
			        class="button button-small button-primary" 
			        onclick="fplmsAuditShowModal('reactivate', <?php echo esc_attr( $user_id ); ?>, '<?php echo esc_js( $user_name ); ?>', '<?php echo esc_js( $user_email ); ?>')"
			        style="text-align: center; cursor: pointer;">
				✅ Reactivar
			</button>
			<button type="button" 
			        class="button button-small button-link-delete" 
			        onclick="fplmsAuditShowModal('delete', <?php echo esc_attr( $user_id ); ?>, '<?php echo esc_js( $user_name ); ?>', '<?php echo esc_js( $user_email ); ?>')"
			        style="color: #d63638; text-align: center; cursor: pointer;">
				🗑️ Eliminar Definitivo
			</button>
		</div>
		<?php
	}

	/**
	 * Renderizar paginación
	 *
	 * @param int $current_page Página actual
	 * @param int $total_pages  Total de páginas
	 * @param int $per_page     Registros por página
	 * @return void
	 */
	private function render_pagination( int $current_page, int $total_pages, int $per_page = 50 ): void {
		if ( $total_pages <= 1 ) {
			return;
		}

		$base_url = admin_url( 'admin.php?page=fairplay-lms-audit' );
		
		// Mantener filtros y per_page en la paginación
		$query_params = [];
		if ( ! empty( $_GET['filter_action'] ) ) {
			$query_params['filter_action'] = sanitize_text_field( wp_unslash( $_GET['filter_action'] ) );
		}
		if ( ! empty( $_GET['filter_entity'] ) ) {
			$query_params['filter_entity'] = sanitize_text_field( wp_unslash( $_GET['filter_entity'] ) );
		}
		if ( ! empty( $_GET['filter_date_from'] ) ) {
			$query_params['filter_date_from'] = sanitize_text_field( wp_unslash( $_GET['filter_date_from'] ) );
		}
		if ( ! empty( $_GET['filter_date_to'] ) ) {
			$query_params['filter_date_to'] = sanitize_text_field( wp_unslash( $_GET['filter_date_to'] ) );
		}
		// Mantener el per_page actual
		if ( $per_page !== 50 ) {
			$query_params['per_page'] = $per_page;
		}

		?>
		<div class="tablenav" style="margin-top: 15px;">
			<div class="tablenav-pages">
				<span class="displaying-num"><?php echo esc_html( sprintf( 'Página %d de %d', $current_page, $total_pages ) ); ?></span>
				<span class="pagination-links">
					<?php if ( $current_page > 1 ) : ?>
						<a href="<?php echo esc_url( add_query_arg( array_merge( $query_params, [ 'paged' => 1 ] ), $base_url ) ); ?>" class="button">« Primera</a>
						<a href="<?php echo esc_url( add_query_arg( array_merge( $query_params, [ 'paged' => $current_page - 1 ] ), $base_url ) ); ?>" class="button">‹ Anterior</a>
					<?php endif; ?>

					<?php if ( $current_page < $total_pages ) : ?>
						<a href="<?php echo esc_url( add_query_arg( array_merge( $query_params, [ 'paged' => $current_page + 1 ] ), $base_url ) ); ?>" class="button">Siguiente ›</a>
						<a href="<?php echo esc_url( add_query_arg( array_merge( $query_params, [ 'paged' => $total_pages ] ), $base_url ) ); ?>" class="button">Última »</a>
					<?php endif; ?>
				</span>
			</div>
		</div>
		<?php
	}

	/**
	 * Formatear nombre de acción
	 *
	 * @param string $action Nombre de la acción
	 * @return string Nombre formateado
	 */
	private function format_action( string $action ): string {
		$actions = [
			// Cursos
			'course_created'                           => '📘 Curso Creado',
			'course_updated'                           => '✏️ Curso Actualizado',
			'course_deleted'                           => '🗑️ Curso Eliminado',
			
			// Lecciones
			'lesson_added'                             => '📝 Lección Agregada',
			'lesson_updated'                           => '✏️ Lección Actualizada',
			'lesson_deleted'                           => '🗑️ Lección Eliminada',
			
			// Quizzes
			'quiz_added'                               => '❓ Quiz Agregado',
			'quiz_updated'                             => '✏️ Quiz Actualizado',
			'quiz_deleted'                             => '🗑️ Quiz Eliminado',
			
			// Usuarios
			'user_deactivated'                         => '❌ Usuario Desactivado',
			'user_reactivated'                         => '✅ Usuario Reactivado',
			'user_permanently_deleted'                 => '🔥 Usuario Eliminado Permanentemente',
			'user_updated'                             => '✏️ Usuario Actualizado',
			
			// Estructuras
			'structure_created'                        => '➕ Estructura Creada',
			'structure_updated'                        => '✏️ Estructura Actualizada',
			'structure_deleted'                        => '🗑️ Estructura Eliminada',
			'structure_status_changed'                 => '🔄 Estado Actualizado',
			'structures_assigned'                      => '🏢 Estructuras Asignadas',
			'structures_updated'                       => '✏️ Estructuras Actualizadas',
			'course_structures_synced_from_categories' => '🔄 Sync desde Categorías',
			'channel_category_sync'                    => '🔗 Canal→Categoría',
			'channel_unsynced'                         => '🔓 Canal Desvinculado',
			
			// Sistema
			'permission_denied'                        => '🚫 Permiso Denegado',
			'notification_sent'                        => '📧 Notificación Enviada',
		];

		return $actions[ $action ] ?? ucwords( str_replace( '_', ' ', $action ) );
	}

	/**
	 * Comparar valores antiguos y nuevos para mostrar solo los cambios
	 *
	 * @param string $old_value Valor anterior en JSON
	 * @param string $new_value Valor nuevo en JSON
	 * @param string $action Tipo de acción (opcional, para contexto)
	 * @return string HTML con los cambios
	 */
	private function render_changes_only( string $old_value, string $new_value, string $action = '' ): string {
		$old_data = json_decode( $old_value, true );
		$new_data = json_decode( $new_value, true );

		// Si no son arrays válidos, mostrar como antes
		if ( ! is_array( $old_data ) || ! is_array( $new_data ) ) {
			return '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
				<div>
					<strong>Valor Anterior:</strong>
					<pre style="background: #fff; padding: 10px; border: 1px solid #ddd; max-height: 200px; overflow: auto; font-size: 11px;">' . esc_html( $old_value ?: 'N/A' ) . '</pre>
				</div>
				<div>
					<strong>Valor Nuevo:</strong>
					<pre style="background: #fff; padding: 10px; border: 1px solid #ddd; max-height: 200px; overflow: auto; font-size: 11px;">' . esc_html( $new_value ?: 'N/A' ) . '</pre>
				</div>
			</div>';
		}

		// Etiquetas amigables para los campos (mapeo expandido)
		$field_labels = [
			// Usuarios
			'email'              => 'Email',
			'first_name'         => 'Nombre',
			'last_name'          => 'Apellido',
			'id_usuario'         => 'ID Usuario',
			'role'               => 'Rol',
			'city_id'            => 'Ciudad (ID)',
			'company_id'         => 'Empresa (ID)',
			'channel_id'         => 'Canal (ID)',
			'branch_id'          => 'Sucursal (ID)',
			'role_id'            => 'Cargo (ID)',
			// Estructuras
			'name'               => 'Nombre',
			'slug'               => 'Slug',
			'description'        => 'Descripción',
			'parent'             => 'Padre (ID)',
			'active'             => 'Estado Activo',
			'status'             => 'Estado',
			'status_text'        => 'Estado',
			'taxonomy'           => 'Taxonomía',
			'term_id'            => 'ID Término',
			// Cursos
			'title'              => 'Título',
			'content'            => 'Contenido',
			'excerpt'            => 'Extracto',
			'status_course'      => 'Estado del Curso',
			'price'              => 'Precio',
			'duration'           => 'Duración',
			'level'              => 'Nivel',
			'video_duration'     => 'Duración de Video',
			'views'              => 'Vistas',
			'thumbnail'          => 'Imagen Destacada',
			// Lecciones y Quizzes
			'lesson_type'        => 'Tipo de Lección',
			'preview'            => 'Vista Previa',
			'duration_minutes'   => 'Duración (minutos)',
			'quiz_style'         => 'Estilo del Quiz',
			'passing_grade'      => 'Nota Mínima',
			'questions_count'    => 'Cantidad de Preguntas',
			're_take_cut'        => 'Recorte de Reintentos',
			// Categorías y Meta
			'category_id'        => 'Categoría (ID)',
			'categories'         => 'Categorías',
			'meta_data'          => 'Metadatos',
			'custom_fields'      => 'Campos Personalizados',
			// Relaciones
			'related_channels'   => 'Canales Relacionados',
			'related_branches'   => 'Sucursales Relacionadas',
			'assigned_to'        => 'Asignado a',
			'instructor_id'      => 'Instructor (ID)',
			'co_instructor'      => 'Co-Instructor',
		];

		// Encontrar los campos que cambiaron
		$changes = [];
		$all_keys = array_unique( array_merge( array_keys( $old_data ), array_keys( $new_data ) ) );
		
		foreach ( $all_keys as $key ) {
			$old_val = $old_data[ $key ] ?? '';
			$new_val = $new_data[ $key ] ?? '';
			
			// Manejar arrays y objetos
			if ( is_array( $old_val ) || is_object( $old_val ) ) {
				$old_val = wp_json_encode( $old_val, JSON_UNESCAPED_UNICODE );
			}
			if ( is_array( $new_val ) || is_object( $new_val ) ) {
				$new_val = wp_json_encode( $new_val, JSON_UNESCAPED_UNICODE );
			}
			
			// Convertir booleanos a texto
			if ( is_bool( $old_val ) ) {
				$old_val = $old_val ? 'Sí' : 'No';
			}
			if ( is_bool( $new_val ) ) {
				$new_val = $new_val ? 'Sí' : 'No';
			}
			
			// Normalizar valores vacíos
			$old_val = ( $old_val === '' || $old_val === 0 || $old_val === '0' || $old_val === null ) ? '' : $old_val;
			$new_val = ( $new_val === '' || $new_val === 0 || $new_val === '0' || $new_val === null ) ? '' : $new_val;
			
			// Si son diferentes, es un cambio
			if ( (string) $old_val !== (string) $new_val ) {
				$label = $field_labels[ $key ] ?? ucwords( str_replace( '_', ' ', $key ) );
				
				// Truncar valores muy largos
				$old_display = $old_val ?: '<em>Sin asignar</em>';
				$new_display = $new_val ?: '<em>Sin asignar</em>';
				
				if ( strlen( $old_display ) > 200 && strpos( $old_display, '<em>' ) === false ) {
					$old_display = substr( $old_display, 0, 200 ) . '...';
				}
				if ( strlen( $new_display ) > 200 && strpos( $new_display, '<em>' ) === false ) {
					$new_display = substr( $new_display, 0, 200 ) . '...';
				}
				
				$changes[] = [
					'label' => $label,
					'old'   => $old_display,
					'new'   => $new_display,
				];
			}
		}

		// Si no hay cambios
		if ( empty( $changes ) ) {
			return '<div style="padding: 15px; text-align: center; color: #666; font-style: italic;">
				No se detectaron cambios en los datos.
			</div>';
		}

		// Renderizar los cambios en formato tabla
		$html = '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
		$html .= '<thead><tr style="background: #f0f0f1; border-bottom: 2px solid #ddd;">';
		$html .= '<th style="padding: 10px; text-align: left; width: 30%;">Campo</th>';
		$html .= '<th style="padding: 10px; text-align: left; width: 35%; background: #ffebee;">Valor Anterior</th>';
		$html .= '<th style="padding: 10px; text-align: left; width: 35%; background: #e8f5e9;">Valor Nuevo</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $changes as $change ) {
			$html .= '<tr style="border-bottom: 1px solid #eee;">';
			$html .= '<td style="padding: 10px; font-weight: 600; color: #2271b1;">' . esc_html( $change['label'] ) . '</td>';
			$html .= '<td style="padding: 10px; background: #fff8f8; color: #d63638;">' . ( strpos( $change['old'], '<em>' ) !== false ? $change['old'] : esc_html( $change['old'] ) ) . '</td>';
			$html .= '<td style="padding: 10px; background: #f6fff8; color: #00a32a;">' . ( strpos( $change['new'], '<em>' ) !== false ? $change['new'] : esc_html( $change['new'] ) ) . '</td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table>';
		$html .= '<div style="margin-top: 15px; padding: 10px; background: #e7f5ff; border-left: 4px solid #2271b1; font-size: 12px;">';
		$html .= '<strong>ℹ️ Total de cambios:</strong> ' . count( $changes ) . ' campo(s) modificado(s)';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Exportar logs a CSV
	 *
	 * @return void
	 */
	private function export_csv(): void {
		$filters = [
			'action'      => isset( $_GET['filter_action'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_action'] ) ) : '',
			'entity_type' => isset( $_GET['filter_entity'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_entity'] ) ) : '',
			'date_from'   => isset( $_GET['filter_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_date_from'] ) ) : '',
			'date_to'     => isset( $_GET['filter_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_date_to'] ) ) : '',
		];

		$csv      = $this->logger->export_to_csv( $filters );
		$filename = 'fplms-audit-log-' . date( 'Y-m-d-His' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo "\xEF\xBB\xBF"; // UTF-8 BOM
		echo $csv;
		exit;
	}

	/**
	 * Extrae los filtros actuales de los parámetros GET para usarlos en la exportación.
	 */
	private function get_export_filters(): array {
		return [
			'action'      => isset( $_GET['filter_action'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_action'] ) ) : '',
			'entity_type' => isset( $_GET['filter_entity'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_entity'] ) ) : '',
			'user_id'     => isset( $_GET['filter_user'] ) ? intval( $_GET['filter_user'] ) : 0,
			'date_from'   => isset( $_GET['filter_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_date_from'] ) ) : '',
			'date_to'     => isset( $_GET['filter_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_date_to'] ) ) : '',
		];
	}


	/**
	 * Nombre de acción sin emojis (para Excel).
	 */
	private function format_action_plain( string $action ): string {
		$map = [
			'course_created'                           => 'Curso Creado',
			'course_updated'                           => 'Curso Actualizado',
			'course_deleted'                           => 'Curso Eliminado',
			'lesson_added'                             => 'Leccion Agregada',
			'lesson_updated'                           => 'Leccion Actualizada',
			'lesson_deleted'                           => 'Leccion Eliminada',
			'quiz_added'                               => 'Quiz Agregado',
			'quiz_updated'                             => 'Quiz Actualizado',
			'quiz_deleted'                             => 'Quiz Eliminado',
			'user_deactivated'                         => 'Usuario Desactivado',
			'user_reactivated'                         => 'Usuario Reactivado',
			'user_permanently_deleted'                 => 'Usuario Eliminado Permanentemente',
			'user_updated'                             => 'Usuario Actualizado',
			'structure_created'                        => 'Estructura Creada',
			'structure_updated'                        => 'Estructura Actualizada',
			'structure_deleted'                        => 'Estructura Eliminada',
			'structures_assigned'                      => 'Estructuras Asignadas',
			'structures_updated'                       => 'Estructuras Actualizadas',
			'course_structures_synced_from_categories' => 'Sync desde Categorias',
			'channel_category_sync'                    => 'Canal-Categoria Sync',
			'channel_unsynced'                         => 'Canal Desvinculado',
		];
		return $map[ $action ] ?? ucwords( str_replace( '_', ' ', $action ) );
	}

	/**
	 * Maneja la reactivación de un usuario desde la bitácora
	 *
	 * @return void
	 */
	public function handle_user_reactivation(): void {
		// Verificar permisos
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '❌ No tienes permisos para realizar esta acción.' );
		}

		// Verificar nonce
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'fplms_reactivate_user' ) ) {
			wp_die( '❌ Nonce de seguridad inválido.' );
		}

		// Obtener ID del usuario
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		if ( ! $user_id ) {
			wp_die( '❌ ID de usuario inválido.' );
		}

		// Verificar que el usuario exista
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_die( '❌ Error: El usuario no existe.' );
		}

		// Reactivar usuario usando el update_user_meta directamente
		update_user_meta( $user_id, 'fplms_user_status', 'active' );
		update_user_meta( $user_id, 'fplms_reactivated_date', current_time( 'mysql' ) );
		update_user_meta( $user_id, 'fplms_reactivated_by', get_current_user_id() );
		
		// Registrar en bitácora
		$this->logger->log_user_reactivated( $user_id, $user->display_name, $user->user_email );

		wp_safe_redirect(
			add_query_arg(
				[
					'page' => 'fairplay-lms-audit',
					'message' => 'user_reactivated',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Maneja la eliminación permanente de un usuario desde la bitácora
	 *
	 * @return void
	 */
	public function handle_user_permanent_deletion(): void {
		// Verificar permisos
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '❌ No tienes permisos para realizar esta acción.' );
		}

		// Verificar nonce
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'fplms_delete_user_permanently' ) ) {
			wp_die( '❌ Nonce de seguridad inválido.' );
		}

		// Obtener ID del usuario
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		if ( ! $user_id ) {
			wp_die( '❌ ID de usuario inválido.' );
		}
		
		// Verificar que no se elimine el usuario actual
		if ( $user_id === get_current_user_id() ) {
			wp_die( '❌ Error: No puedes eliminarte a ti mismo.' );
		}

		// Obtener datos del usuario antes de eliminar
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_die( '❌ Error: El usuario no existe.' );
		}
		
		// Registrar en bitácora ANTES de eliminar
		$this->logger->log_user_permanently_deleted( $user_id, $user->display_name, $user->user_email );

		// Eliminar permanentemente
		require_once( ABSPATH . 'wp-admin/includes/user.php' );
		$result = wp_delete_user( $user_id );

		if ( $result ) {
			wp_safe_redirect(
				add_query_arg(
					[
						'page' => 'fairplay-lms-audit',
						'message' => 'user_permanently_deleted',
					],
					admin_url( 'admin.php' )
				)
			);
		} else {
			wp_die( '❌ Error al eliminar usuario permanentemente.' );
		}

		exit;
	}
}
