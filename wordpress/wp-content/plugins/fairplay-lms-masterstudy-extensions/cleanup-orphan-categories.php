<?php
/**
 * Script para limpiar categorías huérfanas y revinacular canales con categorías
 * Se ejecuta desde: wp-admin/admin.php?page=cleanup-orphan-categories
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Acceso directo no permitido' );
}

// Verificar permisos
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'No tienes permisos para acceder a esta página.' );
}

// Procesar limpieza si se solicita
$action_performed = false;
$results = [];

if ( isset( $_POST['cleanup_orphans'] ) && check_admin_referer( 'fplms_cleanup_orphans' ) ) {
	$action_performed = true;
	
	// Obtener todas las categorías de cursos
	$categories = get_terms([
		'taxonomy'   => FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY,
		'hide_empty' => false,
	]);
	
	$results['total_categories'] = count( $categories );
	$results['orphans_deleted'] = 0;
	$results['relinked'] = 0;
	$results['already_linked'] = 0;
	$results['broken_links_fixed'] = 0;
	
	foreach ( $categories as $category ) {
		$channel_id = get_term_meta( $category->term_id, 'fplms_linked_channel_id', true );
		
		// Si tiene canal vinculado, validar que el canal existe
		if ( ! empty( $channel_id ) ) {
			$channel = get_term( $channel_id, FairPlay_LMS_Config::TAX_CHANNEL );
			
			// Si el canal no existe, tratar como huérfano
			if ( ! $channel || is_wp_error( $channel ) ) {
				delete_term_meta( $category->term_id, 'fplms_linked_channel_id' );
				$channel_id = null; // Resetear para que se procese como huérfano
				$results['broken_links_fixed']++;
			} else {
				$results['already_linked']++;
				continue;
			}
		}
		
		// Si no tiene canal vinculado o el canal no existe
		if ( empty( $channel_id ) ) {
			// Intentar buscar canal con slug similar
			$slug = str_replace( 'fplms-', '', $category->slug );
			$channel = get_term_by( 'slug', $slug, FairPlay_LMS_Config::TAX_CHANNEL );
			
			if ( $channel && ! is_wp_error( $channel ) ) {
				// Revincular
				update_term_meta( $category->term_id, 'fplms_linked_channel_id', $channel->term_id );
				update_term_meta( $channel->term_id, 'fplms_linked_category_id', $category->term_id );
				$results['relinked']++;
				$results['relinked_details'][] = "Categoría '{$category->name}' revinculada con canal '{$channel->name}'";
			} else {
				// Eliminar categoría huérfana
				wp_delete_term( $category->term_id, FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY );
				$results['orphans_deleted']++;
				$results['deleted_details'][] = "Categoría '{$category->name}' eliminada (sin canal asociado)";
			}
		}
	}
}

?>
<div class="wrap">
	<h1>🧹 Limpieza y Vinculación de Categorías</h1>
	
	<div class="notice notice-info">
		<p><strong>¿Qué hace esta herramienta?</strong></p>
		<ol style="list-style: decimal; margin-left: 20px; line-height: 1.6;">
			<li><strong>Busca categorías sin vinculación:</strong> Identifica categorías de MasterStudy que no están vinculadas a ningún canal de FairPlay.</li>
			<li><strong>Vincula automáticamente:</strong> Si encuentra un canal con el mismo nombre (o slug similar), crea la vinculación automáticamente.</li>
			<li><strong>Detecta vínculos rotos:</strong> Encuentra categorías vinculadas a canales que ya no existen y corrige el problema.</li>
			<li><strong>Limpia huérfanos:</strong> Elimina categorías que no tienen canal asociado y no pueden ser vinculadas.</li>
		</ol>
		<p style="margin-top: 10px; padding: 10px; background: #fffbf0; border-left: 3px solid #f0b849;">
			<strong>💡 Importante:</strong> Si después de ejecutar esta limpieza siguen apareciendo categorías sin canal, 
			necesitas crear los canales faltantes en <strong>FairPlay LMS → Estructuras → Canales</strong> con el mismo nombre que las categorías.
		</p>
	</div>
	
	<?php if ( $action_performed ) : ?>
		<div class="notice notice-success">
			<h2>✅ Limpieza completada</h2>
			<ul style="list-style: disc; margin-left: 20px; font-size: 14px;">
				<li><strong>Total de categorías:</strong> <?php echo esc_html( $results['total_categories'] ); ?></li>
				<li><strong>Categorías ya vinculadas:</strong> <?php echo esc_html( $results['already_linked'] ); ?></li>
				<?php if ( $results['broken_links_fixed'] > 0 ) : ?>
					<li style="color: #d63638;"><strong>Vínculos rotos corregidos:</strong> <?php echo esc_html( $results['broken_links_fixed'] ); ?></li>
				<?php endif; ?>
				<li><strong>Categorías revinculadas:</strong> <?php echo esc_html( $results['relinked'] ); ?></li>
				<li><strong>Categorías huérfanas eliminadas:</strong> <?php echo esc_html( $results['orphans_deleted'] ); ?></li>
			</ul>
			
			<?php if ( ! empty( $results['relinked_details'] ) ) : ?>
				<h3>Revinculaciones:</h3>
				<ul style="list-style: disc; margin-left: 20px; font-size: 13px; color: #0a7;">
					<?php foreach ( $results['relinked_details'] as $detail ) : ?>
						<li><?php echo esc_html( $detail ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			
			<?php if ( ! empty( $results['deleted_details'] ) ) : ?>
				<h3>Eliminaciones:</h3>
				<ul style="list-style: disc; margin-left: 20px; font-size: 13px; color: #d63638;">
					<?php foreach ( $results['deleted_details'] as $detail ) : ?>
						<li><?php echo esc_html( $detail ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	
	<div class="card" style="max-width: 800px; margin-top: 20px;">
		<h2>Estado actual</h2>
		<?php
		// Mostrar estado actual
		$all_categories = get_terms([
			'taxonomy'   => FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY,
			'hide_empty' => false,
		]);
		
		$all_channels = get_terms([
			'taxonomy'   => FairPlay_LMS_Config::TAX_CHANNEL,
			'hide_empty' => false,
		]);
		
		$linked_count = 0;
		$orphan_count = 0;
		$broken_links = 0;
		
		foreach ( $all_categories as $cat ) {
			$channel_id = get_term_meta( $cat->term_id, 'fplms_linked_channel_id', true );
			if ( $channel_id ) {
				// Validar que el canal realmente existe
				$channel = get_term( $channel_id, FairPlay_LMS_Config::TAX_CHANNEL );
				if ( $channel && ! is_wp_error( $channel ) ) {
					$linked_count++;
				} else {
					// Canal vinculado no existe (vínculo roto)
					$broken_links++;
					$orphan_count++; // Contar como huérfano para habilitar botón
				}
			} else {
				$orphan_count++;
			}
		}
		?>
		<table class="widefat" style="margin-top: 10px;">
			<tr>
				<td><strong>Canales totales:</strong></td>
				<td><?php echo esc_html( count( $all_channels ) ); ?></td>
			</tr>
			<tr>
				<td><strong>Categorías totales:</strong></td>
				<td><?php echo esc_html( count( $all_categories ) ); ?></td>
			</tr>
			<tr>
				<td><strong>Categorías vinculadas:</strong></td>
				<td style="color: #0a7;"><?php echo esc_html( $linked_count ); ?></td>
			</tr>
			<?php if ( $broken_links > 0 ) : ?>
			<tr style="background: #fff3cd;">
				<td><strong>Vínculos rotos:</strong></td>
				<td style="color: #d63638; font-weight: bold;">
					<?php echo esc_html( $broken_links ); ?>
					<span style="font-size: 12px; color: #856404; display: block; margin-top: 3px;">
						(Categorías vinculadas a canales que ya no existen)
					</span>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<td><strong>Categorías huérfanas:</strong></td>
				<td style="color: <?php echo $orphan_count > 0 ? '#d63638' : '#0a7'; ?>">
					<?php echo esc_html( $orphan_count ); ?>
				</td>
			</tr>
		</table>
		
		<?php if ( $orphan_count > 0 ) : ?>
			<form method="post" style="margin-top: 20px;">
				<?php wp_nonce_field( 'fplms_cleanup_orphans' ); ?>
				<button type="submit" name="cleanup_orphans" class="button button-primary button-large">
					🧹 Ejecutar Limpieza
				</button>
			</form>
		<?php else : ?>
			<p style="color: #0a7; margin-top: 15px;">✅ No hay categorías huérfanas que limpiar</p>
		<?php endif; ?>
	</div>
	
	<div class="card" style="max-width: 800px; margin-top: 20px;">
		<h2>Verificar sincronización de cursos</h2>
		<p>Para verificar que los cursos tienen sus estructuras correctamente sincronizadas:</p>
		<ol style="list-style: decimal; margin-left: 20px;">
			<li>Ve a <strong>FairPlay LMS → Cursos</strong></li>
			<li>Edita un curso que tenga categoría asignada</li>
			<li>Guarda el curso (sin hacer cambios)</li>
			<li>Vuelve al listado y verifica que aparezcan las estructuras (📍 Ciudad, 🏢 Empresa, 🏪 Canal)</li>
		</ol>
	</div>
</div>
