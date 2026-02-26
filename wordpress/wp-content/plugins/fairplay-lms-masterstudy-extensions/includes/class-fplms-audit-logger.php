<?php
/**
 * FairPlay LMS Audit Logger
 *
 * Sistema de auditoría para registrar todas las operaciones de asignación de estructuras
 *
 * @package    FairPlay_LMS
 * @subpackage FairPlay_LMS/includes
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase para gestión de bitácora de auditoría
 *
 * @since 1.0.0
 */
class FairPlay_LMS_Audit_Logger {

	/**
	 * Nombre de la tabla de auditoría
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'fplms_audit_log';
	}

	/**
	 * Crear tabla de auditoría
	 *
	 * @return bool True si se creó correctamente
	 */
	public function create_table(): bool {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			user_name VARCHAR(255) NOT NULL,
			action VARCHAR(50) NOT NULL,
			entity_type VARCHAR(50) NOT NULL,
			entity_id BIGINT(20) UNSIGNED NOT NULL,
			entity_title VARCHAR(255) DEFAULT NULL,
			old_value TEXT DEFAULT NULL,
			new_value TEXT DEFAULT NULL,
			ip_address VARCHAR(45) DEFAULT NULL,
			user_agent VARCHAR(255) DEFAULT NULL,
			status VARCHAR(20) DEFAULT 'active',
			meta_data TEXT DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY timestamp_idx (timestamp),
			KEY user_id_idx (user_id),
			KEY action_idx (action),
			KEY entity_type_idx (entity_type),
			KEY entity_id_idx (entity_id),
			KEY status_idx (status),
			KEY composite_idx (entity_type, entity_id, action)
		) $charset_collate ENGINE=InnoDB;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		return $wpdb->last_error === '';
	}

	/**
	 * Registrar acción en la bitácora
	 *
	 * @param string $action       Tipo de acción (course_created, structures_assigned, etc.)
	 * @param string $entity_type  Tipo de entidad (course, channel, category)
	 * @param int    $entity_id    ID de la entidad
	 * @param string $entity_title Título de la entidad
	 * @param mixed  $old_value    Valor anterior (opcional)
	 * @param mixed  $new_value    Valor nuevo (opcional)
	 * @return int|false ID del registro insertado o false en caso de error
	 */
	public function log_action(
		string $action,
		string $entity_type,
		int $entity_id,
		string $entity_title = '',
		$old_value = null,
		$new_value = null
	) {
		global $wpdb;

		$current_user = wp_get_current_user();
		$user_id      = $current_user->ID ?: 0;
		$user_name    = $current_user->display_name ?: 'System';

		// Obtener IP del cliente
		$ip_address = $this->get_client_ip();

		// Obtener User Agent
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) 
			? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 )
			: '';

		// Serializar valores si son arrays u objetos
		if ( ! is_null( $old_value ) && ! is_scalar( $old_value ) ) {
			$old_value = maybe_serialize( $old_value );
		}
		if ( ! is_null( $new_value ) && ! is_scalar( $new_value ) ) {
			$new_value = maybe_serialize( $new_value );
		}

		$data = [
			'timestamp'    => current_time( 'mysql' ),
			'user_id'      => $user_id,
			'user_name'    => $user_name,
			'action'       => $action,
			'entity_type'  => $entity_type,
			'entity_id'    => $entity_id,
			'entity_title' => $entity_title,
			'old_value'    => $old_value,
			'new_value'    => $new_value,
			'ip_address'   => $ip_address,
			'user_agent'   => $user_agent,
		];

		$result = $wpdb->insert( $this->table_name, $data );

		if ( $result === false ) {
			error_log( 'FPLMS Audit Log Error: ' . $wpdb->last_error );
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Obtener logs con filtros
	 *
	 * @param array $args Argumentos de filtrado
	 * @return array Array de registros de auditoría
	 */
	public function get_logs( array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'action'      => '',
			'entity_type' => '',
			'entity_id'   => 0,
			'user_id'     => 0,
			'date_from'   => '',
			'date_to'     => '',
			'limit'       => 100,
			'offset'      => 0,
			'orderby'     => 'timestamp',
			'order'       => 'DESC',
		];

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = [ '1=1' ];

		if ( ! empty( $args['action'] ) ) {
			$where_clauses[] = $wpdb->prepare( 'action = %s', $args['action'] );
		}

		if ( ! empty( $args['entity_type'] ) ) {
			$where_clauses[] = $wpdb->prepare( 'entity_type = %s', $args['entity_type'] );
		}

		if ( ! empty( $args['entity_id'] ) ) {
			$where_clauses[] = $wpdb->prepare( 'entity_id = %d', $args['entity_id'] );
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where_clauses[] = $wpdb->prepare( 'user_id = %d', $args['user_id'] );
		}

		if ( ! empty( $args['date_from'] ) ) {
			// Si date_from solo tiene fecha (no hora), agregar 00:00:00
			$date_from = strlen( $args['date_from'] ) === 10 ? $args['date_from'] . ' 00:00:00' : $args['date_from'];
			$where_clauses[] = $wpdb->prepare( 'timestamp >= %s', $date_from );
		}

		if ( ! empty( $args['date_to'] ) ) {
			// Si date_to solo tiene fecha (no hora), agregar 23:59:59 para incluir todo el día
			$date_to = strlen( $args['date_to'] ) === 10 ? $args['date_to'] . ' 23:59:59' : $args['date_to'];
			$where_clauses[] = $wpdb->prepare( 'timestamp <= %s', $date_to );
		}

		$where_sql = implode( ' AND ', $where_clauses );

		$orderby = in_array( $args['orderby'], [ 'timestamp', 'user_name', 'action', 'entity_type' ], true )
			? $args['orderby']
			: 'timestamp';

		$order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->table_name} 
			WHERE {$where_sql} 
			ORDER BY {$orderby} {$order} 
			LIMIT %d OFFSET %d",
			$args['limit'],
			$args['offset']
		);

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Contar logs con filtros
	 *
	 * @param array $args Argumentos de filtrado
	 * @return int Número de registros
	 */
	public function count_logs( array $args = [] ): int {
		global $wpdb;

		$where_clauses = [ '1=1' ];

		if ( ! empty( $args['action'] ) ) {
			$where_clauses[] = $wpdb->prepare( 'action = %s', $args['action'] );
		}

		if ( ! empty( $args['entity_type'] ) ) {
			$where_clauses[] = $wpdb->prepare( 'entity_type = %s', $args['entity_type'] );
		}

		if ( ! empty( $args['entity_id'] ) ) {
			$where_clauses[] = $wpdb->prepare( 'entity_id = %d', $args['entity_id'] );
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where_clauses[] = $wpdb->prepare( 'user_id = %d', $args['user_id'] );
		}

		if ( ! empty( $args['date_from'] ) ) {
			// Si date_from solo tiene fecha (no hora), agregar 00:00:00
			$date_from = strlen( $args['date_from'] ) === 10 ? $args['date_from'] . ' 00:00:00' : $args['date_from'];
			$where_clauses[] = $wpdb->prepare( 'timestamp >= %s', $date_from );
		}

		if ( ! empty( $args['date_to'] ) ) {
			// Si date_to solo tiene fecha (no hora), agregar 23:59:59 para incluir todo el día
			$date_to = strlen( $args['date_to'] ) === 10 ? $args['date_to'] . ' 23:59:59' : $args['date_to'];
			$where_clauses[] = $wpdb->prepare( 'timestamp <= %s', $date_to );
		}

		$where_sql = implode( ' AND ', $where_clauses );

		$sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_sql}";

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Exportar logs a CSV
	 *
	 * @param array $args Argumentos de filtrado
	 * @return string Contenido CSV
	 */
	public function export_to_csv( array $args = [] ): string {
		$logs = $this->get_logs( array_merge( $args, [ 'limit' => 10000 ] ) );

		$csv = "ID,Fecha/Hora,Usuario,Acción,Tipo Entidad,ID Entidad,Título,Valor Anterior,Valor Nuevo,IP\n";

		foreach ( $logs as $log ) {
			$csv .= sprintf(
				"%d,%s,%s,%s,%s,%d,%s,%s,%s,%s\n",
				$log['id'],
				$log['timestamp'],
				$log['user_name'],
				$log['action'],
				$log['entity_type'],
				$log['entity_id'],
				$this->escape_csv( $log['entity_title'] ?? '' ),
				$this->escape_csv( $log['old_value'] ?? '' ),
				$this->escape_csv( $log['new_value'] ?? '' ),
				$log['ip_address'] ?? ''
			);
		}

		return $csv;
	}

	/**
	 * Limpiar logs antiguos
	 *
	 * @param int $days Días de antigüedad
	 * @return int Número de registros eliminados
	 */
	public function cleanup_old_logs( int $days = 90 ): int {
		global $wpdb;

		$delete_before = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE timestamp < %s",
				$delete_before
			)
		);

		return (int) $deleted;
	}

	/**
	 * Obtener IP del cliente
	 *
	 * @return string IP del cliente
	 */
	private function get_client_ip(): string {
		$ip_keys = [
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		];

		foreach ( $ip_keys as $key ) {
			if ( isset( $_SERVER[ $key ] ) && filter_var( $_SERVER[ $key ], FILTER_VALIDATE_IP ) ) {
				return sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Escapar valor para CSV
	 *
	 * @param string $value Valor a escapar
	 * @return string Valor escapado
	 */
	private function escape_csv( string $value ): string {
		if ( strpos( $value, '"' ) !== false || strpos( $value, ',' ) !== false || strpos( $value, "\n" ) !== false ) {
			return '"' . str_replace( '"', '""', $value ) . '"';
		}
		return $value;
	}

	/**
	 * Obtener estadísticas de auditoría
	 *
	 * @param array $args Argumentos de filtrado
	 * @return array Estadísticas
	 */
	public function get_statistics( array $args = [] ): array {
		global $wpdb;

		$where_clauses = [ '1=1' ];

		if ( ! empty( $args['date_from'] ) ) {
			// Si date_from solo tiene fecha (no hora), agregar 00:00:00
			$date_from = strlen( $args['date_from'] ) === 10 ? $args['date_from'] . ' 00:00:00' : $args['date_from'];
			$where_clauses[] = $wpdb->prepare( 'timestamp >= %s', $date_from );
		}

		if ( ! empty( $args['date_to'] ) ) {
			// Si date_to solo tiene fecha (no hora), agregar 23:59:59 para incluir todo el día
			$date_to = strlen( $args['date_to'] ) === 10 ? $args['date_to'] . ' 23:59:59' : $args['date_to'];
			$where_clauses[] = $wpdb->prepare( 'timestamp <= %s', $date_to );
		}

		$where_sql = implode( ' AND ', $where_clauses );

		$stats = [
			'total_logs'        => 0,
			'actions_breakdown' => [],
			'top_users'         => [],
			'entity_breakdown'  => [],
		];

		// Total de logs
		$stats['total_logs'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_sql}"
		);

		// Desglose por acción
		$actions = $wpdb->get_results(
			"SELECT action, COUNT(*) as count 
			FROM {$this->table_name} 
			WHERE {$where_sql} 
			GROUP BY action 
			ORDER BY count DESC",
			ARRAY_A
		);
		foreach ( $actions as $action ) {
			$stats['actions_breakdown'][ $action['action'] ] = (int) $action['count'];
		}

		// Usuarios más activos
		$users = $wpdb->get_results(
			"SELECT user_name, COUNT(*) as count 
			FROM {$this->table_name} 
			WHERE {$where_sql} 
			GROUP BY user_name 
			ORDER BY count DESC 
			LIMIT 10",
			ARRAY_A
		);
		foreach ( $users as $user ) {
			$stats['top_users'][ $user['user_name'] ] = (int) $user['count'];
		}

		// Desglose por tipo de entidad
		$entities = $wpdb->get_results(
			"SELECT entity_type, COUNT(*) as count 
			FROM {$this->table_name} 
			WHERE {$where_sql} 
			GROUP BY entity_type 
			ORDER BY count DESC",
			ARRAY_A
		);
		foreach ( $entities as $entity ) {
			$stats['entity_breakdown'][ $entity['entity_type'] ] = (int) $entity['count'];
		}

		return $stats;
	}

	/**
	 * Registrar creación de curso
	 *
	 * @param int    $course_id ID del curso
	 * @param string $course_title Título del curso
	 * @param array  $meta_data Datos adicionales
	 * @return int|false
	 */
	public function log_course_created( int $course_id, string $course_title, array $meta_data = [] ) {
		return $this->log_action(
			'course_created',
			'course',
			$course_id,
			$course_title,
			null,
			wp_json_encode( $meta_data )
		);
	}

	/**
	 * Registrar edición de curso
	 *
	 * @param int    $course_id ID del curso
	 * @param string $course_title Título del curso
	 * @param array  $old_data Datos anteriores
	 * @param array  $new_data Datos nuevos
	 * @return int|false
	 */
	public function log_course_updated( int $course_id, string $course_title, array $old_data = [], array $new_data = [] ) {
		return $this->log_action(
			'course_updated',
			'course',
			$course_id,
			$course_title,
			wp_json_encode( $old_data ),
			wp_json_encode( $new_data )
		);
	}

	/**
	 * Registrar eliminación de curso
	 *
	 * @param int    $course_id ID del curso
	 * @param string $course_title Título del curso
	 * @return int|false
	 */
	public function log_course_deleted( int $course_id, string $course_title ) {
		return $this->log_action(
			'course_deleted',
			'course',
			$course_id,
			$course_title
		);
	}

	/**
	 * Registrar adición de lección
	 *
	 * @param int    $lesson_id ID de la lección
	 * @param string $lesson_title Título de la lección
	 * @param int    $course_id ID del curso asociado
	 * @return int|false
	 */
	public function log_lesson_added( int $lesson_id, string $lesson_title, int $course_id ) {
		return $this->log_action(
			'lesson_added',
			'lesson',
			$lesson_id,
			$lesson_title,
			null,
			wp_json_encode( [ 'course_id' => $course_id ] )
		);
	}

	/**
	 * Registrar edición de lección
	 *
	 * @param int    $lesson_id ID de la lección
	 * @param string $lesson_title Título de la lección
	 * @param array  $old_data Datos anteriores
	 * @param array  $new_data Datos nuevos
	 * @return int|false
	 */
	public function log_lesson_updated( int $lesson_id, string $lesson_title, array $old_data = [], array $new_data = [] ) {
		return $this->log_action(
			'lesson_updated',
			'lesson',
			$lesson_id,
			$lesson_title,
			wp_json_encode( $old_data ),
			wp_json_encode( $new_data )
		);
	}

	/**
	 * Registrar eliminación de lección
	 *
	 * @param int    $lesson_id ID de la lección
	 * @param string $lesson_title Título de la lección
	 * @return int|false
	 */
	public function log_lesson_deleted( int $lesson_id, string $lesson_title ) {
		return $this->log_action(
			'lesson_deleted',
			'lesson',
			$lesson_id,
			$lesson_title
		);
	}

	/**
	 * Registrar adición de examen/quiz
	 *
	 * @param int    $quiz_id ID del quiz
	 * @param string $quiz_title Título del quiz
	 * @param int    $course_id ID del curso asociado
	 * @return int|false
	 */
	public function log_quiz_added( int $quiz_id, string $quiz_title, int $course_id ) {
		return $this->log_action(
			'quiz_added',
			'quiz',
			$quiz_id,
			$quiz_title,
			null,
			wp_json_encode( [ 'course_id' => $course_id ] )
		);
	}

	/**
	 * Registrar edición de examen/quiz
	 *
	 * @param int    $quiz_id ID del quiz
	 * @param string $quiz_title Título del quiz
	 * @param array  $old_data Datos anteriores
	 * @param array  $new_data Datos nuevos
	 * @return int|false
	 */
	public function log_quiz_updated( int $quiz_id, string $quiz_title, array $old_data = [], array $new_data = [] ) {
		return $this->log_action(
			'quiz_updated',
			'quiz',
			$quiz_id,
			$quiz_title,
			wp_json_encode( $old_data ),
			wp_json_encode( $new_data )
		);
	}

	/**
	 * Registrar eliminación de examen/quiz
	 *
	 * @param int    $quiz_id ID del quiz
	 * @param string $quiz_title Título del quiz
	 * @return int|false
	 */
	public function log_quiz_deleted( int $quiz_id, string $quiz_title ) {
		return $this->log_action(
			'quiz_deleted',
			'quiz',
			$quiz_id,
			$quiz_title
		);
	}

	/**
	 * Registrar desactivación de usuario (soft delete)
	 *
	 * @param int    $user_id ID del usuario
	 * @param string $user_name Nombre del usuario
	 * @param string $user_email Email del usuario
	 * @return int|false
	 */
	public function log_user_deactivated( int $user_id, string $user_name, string $user_email ) {
		return $this->log_action(
			'user_deactivated',
			'user',
			$user_id,
			$user_name,
			null,
			wp_json_encode( [ 'email' => $user_email, 'status' => 'inactive' ] )
		);
	}

	/**
	 * Registrar reactivación de usuario
	 *
	 * @param int    $user_id ID del usuario
	 * @param string $user_name Nombre del usuario
	 * @param string $user_email Email del usuario
	 * @return int|false
	 */
	public function log_user_reactivated( int $user_id, string $user_name, string $user_email ) {
		return $this->log_action(
			'user_reactivated',
			'user',
			$user_id,
			$user_name,
			wp_json_encode( [ 'status' => 'inactive' ] ),
			wp_json_encode( [ 'email' => $user_email, 'status' => 'active' ] )
		);
	}

	/**
	 * Registrar eliminación permanente de usuario
	 *
	 * @param int    $user_id ID del usuario
	 * @param string $user_name Nombre del usuario
	 * @param string $user_email Email del usuario
	 * @return int|false
	 */
	public function log_user_permanently_deleted( int $user_id, string $user_name, string $user_email ) {
		return $this->log_action(
			'user_permanently_deleted',
			'user',
			$user_id,
			$user_name,
			wp_json_encode( [ 'email' => $user_email ] ),
			null
		);
	}

	/**
	 * Registrar creación de estructura jerárquica
	 *
	 * @param string $structure_type Tipo de estructura (city, company, channel, branch, role)
	 * @param int    $term_id ID del término
	 * @param string $term_name Nombre del término
	 * @param array  $meta_data Datos adicionales (descripción, relaciones, etc.)
	 * @return int|false
	 */
	public function log_structure_created( string $structure_type, int $term_id, string $term_name, array $meta_data = [] ) {
		return $this->log_action(
			'structure_created',
			$structure_type,
			$term_id,
			$term_name,
			null,
			wp_json_encode( $meta_data )
		);
	}

	/**
	 * Registrar edición de estructura jerárquica
	 *
	 * @param string $structure_type Tipo de estructura (city, company, channel, branch, role)
	 * @param int    $term_id ID del término
	 * @param string $term_name Nombre del término
	 * @param array  $old_data Datos anteriores
	 * @param array  $new_data Datos nuevos
	 * @return int|false
	 */
	public function log_structure_updated( string $structure_type, int $term_id, string $term_name, array $old_data = [], array $new_data = [] ) {
		return $this->log_action(
			'structure_updated',
			$structure_type,
			$term_id,
			$term_name,
			wp_json_encode( $old_data ),
			wp_json_encode( $new_data )
		);
	}

	/**
	 * Registrar eliminación de estructura jerárquica
	 *
	 * @param string $structure_type Tipo de estructura (city, company, channel, branch, role)
	 * @param int    $term_id ID del término
	 * @param string $term_name Nombre del término
	 * @param array  $meta_data Datos adicionales (relaciones que tenía, etc.)
	 * @return int|false
	 */
	public function log_structure_deleted( string $structure_type, int $term_id, string $term_name, array $meta_data = [] ) {
		return $this->log_action(
			'structure_deleted',
			$structure_type,
			$term_id,
			$term_name,
			wp_json_encode( $meta_data ),
			null
		);
	}

	/**
	 * Registrar cambio de estado de estructura jerárquica (activar/desactivar)
	 *
	 * @param string $structure_type Tipo de estructura (city, company, channel, branch, role)
	 * @param int    $term_id ID del término
	 * @param string $term_name Nombre del término
	 * @param string $old_status Estado anterior ('1' = activo, '0' = inactivo)
	 * @param string $new_status Estado nuevo ('1' = activo, '0' = inactivo)
	 * @return int|false
	 */
	public function log_structure_status_changed( string $structure_type, int $term_id, string $term_name, string $old_status, string $new_status ) {
		$old_data = [
			'active' => $old_status,
			'status_text' => $old_status === '1' ? 'Activo' : 'Inactivo',
		];

		$new_data = [
			'active' => $new_status,
			'status_text' => $new_status === '1' ? 'Activo' : 'Inactivo',
		];

		return $this->log_action(
			'structure_status_changed',
			$structure_type,
			$term_id,
			$term_name,
			wp_json_encode( $old_data ),
			wp_json_encode( $new_data )
		);
	}
}
