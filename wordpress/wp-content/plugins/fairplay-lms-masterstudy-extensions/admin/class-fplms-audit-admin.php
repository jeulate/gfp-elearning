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
			'📋 Bitácora',
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

		// Procesar exportación si se solicita
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'export_csv' && check_admin_referer( 'fplms_export_audit' ) ) {
			$this->export_csv();
			return;
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
		$export_url = wp_nonce_url(
			add_query_arg( 'action', 'export_csv', admin_url( 'admin.php?page=fairplay-lms-audit' ) ),
			'fplms_export_audit'
		);
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
				</div>

				<div style="display: flex; gap: 10px;">
					<button type="submit" class="button button-primary">🔍 Filtrar</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fairplay-lms-audit' ) ); ?>" class="button">🔄 Limpiar Filtros</a>
					<a href="<?php echo esc_url( $export_url ); ?>" class="button" style="margin-left: auto;">📥 Exportar CSV</a>
				</div>
			</form>
		</div>
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
		<div class="fplms-audit-table">
			<div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4;">
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
					<h3 style="margin: 0;">Registros de Auditoría (<?php echo esc_html( number_format( $total_logs ) ); ?> total)</h3>
					
					<form method="get" action="" style="margin: 0;">
						<input type="hidden" name="page" value="fairplay-lms-audit">
						<?php
						// Mantener todos los filtros actuales
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
				</div>
				
				<?php if ( empty( $logs ) ) : ?>
					<p style="text-align: center; padding: 40px 0; color: #666;">
						📭 No se encontraron registros con los filtros aplicados.
					</p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
						<thead>
							<tr>
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
								<tr>
									<td><strong><?php echo esc_html( $log['id'] ); ?></strong></td>
									<td>
										<?php
										$date = new DateTime( $log['timestamp'] );
										echo esc_html( $date->format( 'd/m/Y H:i' ) );
										?>
									</td>
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
										<button type="button" class="button button-small fplms-view-details" data-log-id="<?php echo esc_attr( $log['id'] ); ?>">
											👁️ Ver
										</button>
									</td>
									<td>
										<?php $this->render_action_buttons( $log ); ?>
									</td>
								</tr>
								<tr id="fplms-details-<?php echo esc_attr( $log['id'] ); ?>" style="display: none;">
									<td colspan="9" style="background: #f9f9f9; padding: 15px;">
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

		<script>
		jQuery(document).ready(function($) {
			$('.fplms-view-details').on('click', function() {
				var logId = $(this).data('log-id');
				var detailsRow = $('#fplms-details-' + logId);
				
				if (detailsRow.is(':visible')) {
					detailsRow.hide();
					$(this).text('👁️ Ver');
				} else {
					$('.fplms-view-details').text('👁️ Ver');
					$('[id^="fplms-details-"]').hide();
					detailsRow.show();
					$(this).text('❌ Cerrar');
				}
			});

			$('.fplms-delete-permanently').on('click', function(e) {
				if (!confirm('⚠️ ADVERTENCIA: Esta acción eliminará PERMANENTEMENTE al usuario y NO SE PUEDE DESHACER.\n\n¿Estás COMPLETAMENTE SEGURO?')) {
					e.preventDefault();
					return false;
				}
			});
		});
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
			echo '<span style="color: #00a32a; font-size: 11px;">✅ Ya reactivado</span>';
			return;
		}

		// URLs con nonces
		$reactivate_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=fplms_reactivate_user&user_id=' . $user_id ),
			'fplms_reactivate_user'
		);

		$delete_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=fplms_permanently_delete_user&user_id=' . $user_id ),
			'fplms_delete_user_permanently'
		);

		?>
		<div style="display: flex; gap: 5px; flex-direction: column;">
			<a href="<?php echo esc_url( $reactivate_url ); ?>" class="button button-small button-primary" style="text-align: center;">
				✅ Reactivar
			</a>
			<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small button-link-delete fplms-delete-permanently" style="color: #d63638; text-align: center;">
				🗑️ Eliminar Definitivo
			</a>
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

		// Obtener instancia del controlador de usuarios
		global $fplms_plugin;
		if ( ! isset( $fplms_plugin ) || ! method_exists( $fplms_plugin, 'get_users_controller' ) ) {
			wp_die( '❌ Error: No se pudo cargar el controlador de usuarios.' );
		}

		$users_controller = $fplms_plugin->get_users_controller();
		$success = $users_controller->reactivate_user( $user_id );

		if ( $success ) {
			wp_safe_redirect(
				add_query_arg(
					[
						'page' => 'fairplay-lms-audit',
						'message' => 'user_reactivated',
					],
					admin_url( 'admin.php' )
				)
			);
		} else {
			wp_die( '❌ Error al reactivar usuario. El usuario podría no existir o no estar inactivo.' );
		}

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

		// Confirmación de seguridad
		if ( ! isset( $_GET['confirm'] ) || $_GET['confirm'] !== 'yes' ) {
			// Mostrar pantalla de confirmación
			?>
			<div class="wrap">
				<h1>⚠️ Confirmación de Eliminación Permanente</h1>
				<div class="notice notice-error" style="padding: 20px; font-size: 16px; margin: 20px 0;">
					<p><strong>Esta acción NO SE PUEDE DESHACER.</strong></p>
					<p>Estás a punto de eliminar permanentemente el usuario con ID <strong><?php echo esc_html( $user_id ); ?></strong>.</p>
					<p>Se eliminarán:</p>
					<ul style="margin-left: 20px;">
						<li>Todos los datos del usuario</li>
						<li>Todas las estructuras asignadas</li>
						<li>Todo el progreso en cursos</li>
						<li>TODOS los registros asociados</li>
					</ul>
					<p style="margin-top: 20px;">
						<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'confirm' => 'yes' ], $_SERVER['REQUEST_URI'] ), 'fplms_delete_user_permanently' ) ); ?>" class="button button-primary button-large" style="background: #d63638; border-color: #d63638;">
							🗑️ SÍ, ELIMINAR PERMANENTEMENTE
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=fairplay-lms-audit' ) ); ?>" class="button button-large">
							← Cancelar y Volver
						</a>
					</p>
				</div>
			</div>
			<?php
			exit;
		}

		// Obtener instancia del controlador de usuarios
		global $fplms_plugin;
		if ( ! isset( $fplms_plugin ) || ! method_exists( $fplms_plugin, 'get_users_controller' ) ) {
			wp_die( '❌ Error: No se pudo cargar el controlador de usuarios.' );
		}

		$users_controller = $fplms_plugin->get_users_controller();
		$success = $users_controller->permanently_delete_user( $user_id );

		if ( $success ) {
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
