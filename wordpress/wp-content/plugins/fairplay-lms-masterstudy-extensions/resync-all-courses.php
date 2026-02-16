<?php
/**
 * Script para resincronizar estructuras de todos los cursos
 * Se ejecuta desde: wp-admin/admin.php?page=resync-all-courses
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Acceso directo no permitido' );
}

// Verificar permisos
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'No tienes permisos para acceder a esta página.' );
}

// Procesar resincronización si se solicita
$action_performed = false;
$results = [];

if ( isset( $_POST['resync_courses'] ) && check_admin_referer( 'fplms_resync_courses' ) ) {
	$action_performed = true;
	
	// Obtener todos los cursos
	$courses = get_posts([
		'post_type'      => FairPlay_LMS_Config::MS_PT_COURSE,
		'posts_per_page' => -1,
		'post_status'    => 'any',
	]);
	
	$results['total_courses'] = count( $courses );
	$results['synced'] = 0;
	$results['without_categories'] = 0;
	$results['without_channels'] = 0;
	$results['synced_details'] = [];
	
	$structures_controller = new FairPlay_LMS_Structures_Controller();
	$courses_controller = new FairPlay_LMS_Courses_Controller( $structures_controller );
	
	foreach ( $courses as $course ) {
		// Obtener categorías del curso
		$category_ids = wp_get_object_terms(
			$course->ID,
			FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY,
			[ 'fields' => 'ids' ]
		);
		
		if ( is_wp_error( $category_ids ) || empty( $category_ids ) ) {
			$results['without_categories']++;
			continue;
		}
		
		// Filtrar categorías que ya no existen
		$valid_category_ids = [];
		foreach ( $category_ids as $cat_id ) {
			$term = get_term( $cat_id, FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY );
			if ( $term && ! is_wp_error( $term ) ) {
				$valid_category_ids[] = $cat_id;
			}
		}
		
		// Si había categorías pero todas fueron eliminadas, limpiar la relación
		if ( empty( $valid_category_ids ) && ! empty( $category_ids ) ) {
			wp_set_object_terms( $course->ID, [], FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY );
			$results['without_categories']++;
			continue;
		}
		
		if ( empty( $valid_category_ids ) ) {
			$results['without_categories']++;
			continue;
		}
		
		$channels_found = [];
		$invalid_categories = [];
		
		// Buscar canales vinculados
		foreach ( $valid_category_ids as $category_id ) {
			$channel_id = $structures_controller->get_linked_channel( $category_id );
			
			if ( $channel_id ) {
				// Validar que el canal existe
				$channel = get_term( $channel_id, FairPlay_LMS_Config::TAX_CHANNEL );
				if ( $channel && ! is_wp_error( $channel ) ) {
					$channels_found[] = $channel_id;
				} else {
					// Canal vinculado no existe, limpiar vinculación
					delete_term_meta( $category_id, 'fplms_linked_channel_id' );
					$invalid_categories[] = $category_id;
				}
			} else {
				$invalid_categories[] = $category_id;
			}
		}
		
		if ( empty( $channels_found ) ) {
			$results['without_channels']++;
			
			// Obtener nombres de categorías sin canal válido
			$category_names = [];
			foreach ( $invalid_categories as $cat_id ) {
				$cat = get_term( $cat_id );
				if ( $cat && ! is_wp_error( $cat ) ) {
					$category_names[] = $cat->name;
				}
			}
			
			if ( ! empty( $category_names ) ) {
				$results['without_channels_details'][] = sprintf(
					'Curso "%s" (ID: %d) tiene categorías [%s] sin canal vinculado',
					get_the_title( $course->ID ),
					$course->ID,
					implode( ', ', $category_names )
				);
			}
			continue;
		}
		
		// Aplicar cascada
		$cascaded = $courses_controller->apply_structure_cascade(
			[],
			[],
			$channels_found,
			[],
			[]
		);
		
		// Guardar en post_meta
		update_post_meta( $course->ID, 'fplms_course_cities', $cascaded['cities'] );
		update_post_meta( $course->ID, 'fplms_course_companies', $cascaded['companies'] );
		update_post_meta( $course->ID, 'fplms_course_channels', $cascaded['channels'] );
		update_post_meta( $course->ID, 'fplms_course_branches', $cascaded['branches'] );
		update_post_meta( $course->ID, 'fplms_course_roles', $cascaded['roles'] );
		
		$results['synced']++;
		
		// Obtener nombres para el detalle
		$city_names = [];
		foreach ( $cascaded['cities'] as $city_id ) {
			$city = get_term( $city_id );
			if ( $city && ! is_wp_error( $city ) ) {
				$city_names[] = $city->name;
			}
		}
		
		$company_names = [];
		foreach ( $cascaded['companies'] as $company_id ) {
			$company = get_term( $company_id );
			if ( $company && ! is_wp_error( $company ) ) {
				$company_names[] = $company->name;
			}
		}
		
		$channel_names = [];
		foreach ( $cascaded['channels'] as $channel_id ) {
			$channel = get_term( $channel_id );
			if ( $channel && ! is_wp_error( $channel ) ) {
				$channel_names[] = $channel->name;
			}
		}
		
		$results['synced_details'][] = sprintf(
			'Curso "%s" (ID: %d) → 📍 %s | 🏢 %s | 🏪 %s',
			get_the_title( $course->ID ),
			$course->ID,
			! empty( $city_names ) ? implode( ', ', $city_names ) : 'Sin ciudades',
			! empty( $company_names ) ? implode( ', ', $company_names ) : 'Sin empresas',
			! empty( $channel_names ) ? implode( ', ', $channel_names ) : 'Sin canales'
		);
	}
}

?>
<div class="wrap">
	<h1>🔄 Resincronizar Estructuras de Cursos</h1>
	
	<div class="notice notice-info">
		<p><strong>¿Qué hace este script?</strong></p>
		<ul style="list-style: disc; margin-left: 20px;">
			<li>Recorre todos los cursos de la plataforma</li>
			<li>Por cada curso con categorías asignadas, busca el canal vinculado</li>
			<li>Aplica la cascada jerárquica (Ciudad → Empresa → Canal)</li>
			<li>Guarda las estructuras en post_meta para que aparezcan en el listado de cursos</li>
		</ul>
	</div>
	
	<?php if ( $action_performed ) : ?>
		<div class="notice notice-success">
			<h2>✅ Resincronización completada</h2>
			<table class="widefat" style="max-width: 600px; margin-top: 10px;">
				<tr>
					<td><strong>Total de cursos:</strong></td>
					<td><?php echo esc_html( $results['total_courses'] ); ?></td>
				</tr>
				<tr style="background: #d4edda;">
					<td><strong>Cursos sincronizados:</strong></td>
					<td style="color: #155724; font-weight: bold;"><?php echo esc_html( $results['synced'] ); ?></td>
				</tr>
				<tr>
					<td><strong>Sin categorías asignadas:</strong></td>
					<td><?php echo esc_html( $results['without_categories'] ); ?></td>
				</tr>
				<tr style="background: <?php echo $results['without_channels'] > 0 ? '#fff3cd' : '#fff'; ?>;">
					<td><strong>Con categorías sin canal:</strong></td>
					<td style="color: <?php echo $results['without_channels'] > 0 ? '#856404' : '#333'; ?>;">
						<?php echo esc_html( $results['without_channels'] ); ?>
					</td>
				</tr>
			</table>
			
			<?php if ( ! empty( $results['synced_details'] ) && $results['synced'] <= 20 ) : ?>
				<h3 style="margin-top: 20px;">Cursos sincronizados:</h3>
				<div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #0a7; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto;">
					<?php foreach ( $results['synced_details'] as $detail ) : ?>
						<div style="margin-bottom: 5px;"><?php echo esc_html( $detail ); ?></div>
					<?php endforeach; ?>
				</div>
			<?php elseif ( ! empty( $results['synced_details'] ) ) : ?>
				<p style="margin-top: 15px; color: #0a7;">
					✅ <?php echo esc_html( $results['synced'] ); ?> cursos sincronizados correctamente
				</p>
			<?php endif; ?>
			
			<?php if ( ! empty( $results['without_channels_details'] ) ) : ?>
				<div style="background: #fff3cd; padding: 20px; border-left: 4px solid #ffc107; margin-top: 20px;">
					<h3 style="margin-top: 0; color: #856404;">⚠️ Problema detectado: Categorías sin vinculación a Canales</h3>
					<p style="font-size: 14px; line-height: 1.6; color: #856404;">
						Los siguientes cursos tienen <strong>categorías asignadas</strong> en Course Builder, pero esas categorías 
						<strong>NO están vinculadas</strong> a ningún canal de FairPlay LMS. Esto impide que se sincronicen las estructuras.
					</p>
					
					<details style="margin: 15px 0;">
						<summary style="cursor: pointer; font-weight: bold; color: #856404; padding: 10px; background: #fffbf0; border-radius: 4px;">
							Ver cursos afectados (<?php echo count( $results['without_channels_details'] ); ?>)
						</summary>
						<div style="background: #fffbf0; padding: 15px; margin-top: 10px; font-family: monospace; font-size: 12px; max-height: 300px; overflow-y: auto; border-radius: 4px;">
							<?php foreach ( $results['without_channels_details'] as $detail ) : ?>
								<div style="margin-bottom: 5px; color: #856404;">• <?php echo esc_html( $detail ); ?></div>
							<?php endforeach; ?>
						</div>
					</details>
					
					<div style="background: #fff; padding: 15px; border-radius: 4px; margin-top: 15px;">
						<h4 style="margin-top: 0; color: #d63638;">🔧 Solución paso a paso:</h4>
						<ol style="line-height: 1.8; margin-left: 20px; color: #333;">
							<li>
								<strong>Ejecuta la Limpieza:</strong><br>
								Ve a <a href="<?php echo admin_url( 'admin.php?page=fplms-cleanup-orphan-categories' ); ?>" class="button button-secondary" style="margin-top: 5px;">↳ Limpieza Categorías</a><br>
								<span style="color: #666; font-size: 13px;">→ Esto intentará vincular automáticamente las categorías con canales que tengan nombres similares.</span>
							</li>
							<li style="margin-top: 10px;">
								<strong>Si la limpieza no las vincula automáticamente:</strong><br>
								<span style="color: #666;">Deberás crear manualmente los canales faltantes o eliminar las categorías huérfanas:</span>
								<ul style="margin-top: 5px; list-style: circle; margin-left: 20px; color: #666; font-size: 13px;">
									<li>Ve a <strong>FairPlay LMS → Estructuras → Canales</strong></li>
									<li>Crea los canales que faltan con el <strong>mismo nombre</strong> que las categorías</li>
									<li>Luego vuelve a ejecutar <strong>↳ Limpieza Categorías</strong></li>
								</ul>
							</li>
							<li style="margin-top: 10px;">
								<strong>Finalmente:</strong><br>
								<span style="color: #666;">Ejecuta nuevamente <strong>↳ Resincronizar Cursos</strong> para aplicar las estructuras.</span>
							</li>
						</ol>
					</div>
					
					<div style="background: #e7f3ff; padding: 12px; border-radius: 4px; margin-top: 15px; border-left: 3px solid #2271b1;">
						<strong style="color: #135e96;">💡 ¿Por qué ocurre esto?</strong><br>
						<span style="color: #666; font-size: 13px;">
							Las categorías de MasterStudy y los Canales de FairPlay son taxonomías separadas. 
							Para que funcione la sincronización, cada categoría debe estar <strong>vinculada</strong> a un canal específico.
							Esta vinculación se crea automáticamente cuando creas/editas un canal, pero si las categorías 
							existían antes, necesitas ejecutar la limpieza para establecer la vinculación.
						</span>
					</div>
				</div>
			<?php endif; ?>
			
			<a href="<?php echo admin_url( 'admin.php?page=fplms-courses' ); ?>" class="button button-primary" style="margin-top: 20px;">
				Ver Cursos →
			</a>
		</div>
	<?php else : ?>
		<div class="card" style="max-width: 800px; margin-top: 20px;">
			<h2>Estado actual</h2>
			<?php
			// Verificar cuántos cursos tienen estructuras
			$all_courses = get_posts([
				'post_type'      => FairPlay_LMS_Config::MS_PT_COURSE,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			]);
			
			$with_structures = 0;
			$without_structures = 0;
			
			foreach ( $all_courses as $course ) {
				$cities = get_post_meta( $course->ID, 'fplms_course_cities', true );
				$companies = get_post_meta( $course->ID, 'fplms_course_companies', true );
				$channels = get_post_meta( $course->ID, 'fplms_course_channels', true );
				
				if ( ! empty( $cities ) || ! empty( $companies ) || ! empty( $channels ) ) {
					$with_structures++;
				} else {
					$without_structures++;
				}
			}
			?>
			<table class="widefat" style="margin-top: 10px;">
				<tr>
					<td><strong>Cursos totales:</strong></td>
					<td><?php echo esc_html( count( $all_courses ) ); ?></td>
				</tr>
				<tr style="background: #d4edda;">
					<td><strong>Con estructuras asignadas:</strong></td>
					<td style="color: #155724;"><?php echo esc_html( $with_structures ); ?></td>
				</tr>
				<tr style="background: <?php echo $without_structures > 0 ? '#fff3cd' : '#fff'; ?>;">
					<td><strong>Sin estructuras asignadas:</strong></td>
					<td style="color: <?php echo $without_structures > 0 ? '#856404' : '#333'; ?>;">
						<?php echo esc_html( $without_structures ); ?>
					</td>
				</tr>
			</table>
			
			<form method="post" style="margin-top: 20px;">
				<?php wp_nonce_field( 'fplms_resync_courses' ); ?>
				<button type="submit" name="resync_courses" class="button button-primary button-large">
					🔄 Resincronizar Todos los Cursos
				</button>
				<p class="description" style="margin-top: 10px;">
					Este proceso puede tardar unos segundos dependiendo de la cantidad de cursos.
				</p>
			</form>
		</div>
	<?php endif; ?>
	
	<div class="card" style="max-width: 800px; margin-top: 20px;">
		<h2>Verificación manual</h2>
		<p>Después de ejecutar la resincronización:</p>
		<ol style="list-style: decimal; margin-left: 20px;">
			<li>Ve a <strong>FairPlay LMS → Cursos</strong></li>
			<li>Verifica que los cursos muestren sus estructuras (📍 Ciudad, 🏢 Empresa, 🏪 Canal)</li>
			<li>Si un curso sigue sin mostrar estructuras, verifica que:
				<ul style="list-style: circle; margin-left: 20px; margin-top: 5px;">
					<li>Tenga categorías asignadas en Course Builder</li>
					<li>Las categorías estén vinculadas a canales (ver 🧹 Limpieza)</li>
					<li>Los canales tengan empresas y ciudades asignadas</li>
				</ul>
			</li>
		</ol>
	</div>
</div>
