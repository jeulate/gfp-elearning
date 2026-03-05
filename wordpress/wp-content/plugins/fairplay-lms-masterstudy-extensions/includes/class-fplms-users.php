<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FairPlay_LMS_Users_Controller {

    /**
     * @var FairPlay_LMS_Structures_Controller
     */
    private $structures;

    /**
     * @var FairPlay_LMS_Progress_Service
     */
    private $progress;

    /**
     * @var FairPlay_LMS_Audit_Logger
     */
    private $logger;

    public function __construct(
        FairPlay_LMS_Structures_Controller $structures,
        FairPlay_LMS_Progress_Service $progress
    ) {
        $this->structures = $structures;
        $this->progress   = $progress;
        $this->logger     = new FairPlay_LMS_Audit_Logger();
    }

    // ==========================
    //   CAMPOS EN PERFIL USUARIO
    // ==========================

    public function render_user_structures_fields( $user ): void {

        if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_USERS ) && ! current_user_can( 'edit_user', $user->ID ) ) {
            return;
        }

        $user_city    = get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_CITY, true );
        $user_company = get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_COMPANY, true );
        $user_channel = get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_CHANNEL, true );
        $user_branch  = get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_BRANCH, true );
        $user_role    = get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_ROLE, true );

        $cities    = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CITY );
        $companies = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_COMPANY );
        $channels  = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CHANNEL );
        $branches  = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_BRANCH );
        $roles     = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_ROLE );
        ?>
        <h2>Estructura organizacional FairPlay</h2>
        <table class="form-table">
            <tr>
                <th><label for="fplms_city">Ciudad</label></th>
                <td>
                    <select name="fplms_city" id="fplms_city">
                        <option value="">— Sin asignar —</option>
                        <?php foreach ( $cities as $term_id => $name ) : ?>
                            <option value="<?php echo esc_attr( $term_id ); ?>" <?php selected( (int) $user_city, $term_id ); ?>>
                                <?php echo esc_html( $name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="fplms_company">Empresa</label></th>
                <td>
                    <select name="fplms_company" id="fplms_company">
                        <option value="">— Sin asignar —</option>
                        <?php foreach ( $companies as $term_id => $name ) : ?>
                            <option value="<?php echo esc_attr( $term_id ); ?>" <?php selected( (int) $user_company, $term_id ); ?>>
                                <?php echo esc_html( $name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="fplms_channel">Canal / Franquicia</label></th>
                <td>
                    <select name="fplms_channel" id="fplms_channel">
                        <option value="">— Sin asignar —</option>
                        <?php foreach ( $channels as $term_id => $name ) : ?>
                            <option value="<?php echo esc_attr( $term_id ); ?>" <?php selected( (int) $user_channel, $term_id ); ?>>
                                <?php echo esc_html( $name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="fplms_branch">Sucursal</label></th>
                <td>
                    <select name="fplms_branch" id="fplms_branch">
                        <option value="">— Sin asignar —</option>
                        <?php foreach ( $branches as $term_id => $name ) : ?>
                            <option value="<?php echo esc_attr( $term_id ); ?>" <?php selected( (int) $user_branch, $term_id ); ?>>
                                <?php echo esc_html( $name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="fplms_job_role">Cargo</label></th>
                <td>
                    <select name="fplms_job_role" id="fplms_job_role">
                        <option value="">— Sin asignar —</option>
                        <?php foreach ( $roles as $term_id => $name ) : ?>
                            <option value="<?php echo esc_attr( $term_id ); ?>" <?php selected( (int) $user_role, $term_id ); ?>>
                                <?php echo esc_html( $name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_user_structures_fields( int $user_id ): void {

        if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_USERS ) && ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }

        $fields = [
            'fplms_city'     => FairPlay_LMS_Config::USER_META_CITY,
            'fplms_company'  => FairPlay_LMS_Config::USER_META_COMPANY,
            'fplms_channel'  => FairPlay_LMS_Config::USER_META_CHANNEL,
            'fplms_branch'   => FairPlay_LMS_Config::USER_META_BRANCH,
            'fplms_job_role' => FairPlay_LMS_Config::USER_META_ROLE,
        ];

        foreach ( $fields as $post_key => $meta_key ) {
            if ( isset( $_POST[ $post_key ] ) ) {
                $value = absint( $_POST[ $post_key ] );
                if ( $value > 0 ) {
                    update_user_meta( $user_id, $meta_key, $value );
                } else {
                    delete_user_meta( $user_id, $meta_key );
                }
            }
        }
    }

    /**
     * Registra la fecha y hora del último login del usuario.
     * Se ejecuta cuando un usuario inicia sesión mediante el hook wp_login.
     *
     * @param string $user_login Nombre de usuario que inició sesión
     * @param WP_User $user Objeto del usuario
     */
    public function record_user_login( string $user_login, $user ): void {
        if ( ! function_exists('update_user_meta') || ! function_exists('current_time') ) {
            return; // Solo ejecutar en entorno WordPress
        }
        if ( isset( $user->ID ) ) {
            $current_time = current_time( 'mysql' );
            update_user_meta( $user->ID, 'last_login', $current_time );
            update_user_meta( $user->ID, 'last_activity', $current_time );

            // Registrar el login en la tabla personalizada solo si existe
            global $wpdb;
            $table_name = $wpdb->prefix . 'fplms_user_logins';
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            ));
            if ($table_exists === $table_name) {
                $wpdb->insert(
                    $table_name,
                    [
                        'user_id'    => $user->ID,
                        'login_time' => $current_time
                    ],
                    [
                        '%d',
                        '%s'
                    ]
                );
            }
        }
    }

    /**
     * Registra la actividad del usuario en la plataforma.
     * Se ejecuta en cada carga de página para rastrear usuarios activos.
     * Esto permite detectar usuarios que mantienen sesión abierta sin cerrarla.
     */
    public function record_user_activity(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return;
        }

        $current_time = current_time( 'mysql' );
        $last_activity = get_user_meta( $user_id, 'last_activity', true );

        // Solo actualizar si han pasado más de 5 minutos desde la última actividad
        // Esto evita escrituras excesivas en la BD
        if ( $last_activity ) {
            $time_diff = strtotime( $current_time ) - strtotime( $last_activity );
            if ( $time_diff < 300 ) { // 5 minutos = 300 segundos
                return;
            }
        }

        // Actualizar timestamp de última actividad
        update_user_meta( $user_id, 'last_activity', $current_time );

        // Registrar en la tabla de actividad si existe
        global $wpdb;
        $table_name = $wpdb->prefix . 'fplms_user_activity';
        $table_exists = $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));

        if ( $table_exists === $table_name ) {
            $wpdb->insert(
                $table_name,
                [
                    'user_id'       => $user_id,
                    'activity_time' => $current_time,
                    'page_url'      => esc_url_raw( $_SERVER['REQUEST_URI'] ?? '' )
                ],
                [ '%d', '%s', '%s' ]
            );
        }
    }

    /**
     * Maneja el heartbeat de WordPress para actualizar actividad de usuarios.
     * Se ejecuta cada minuto mientras el usuario tiene la página abierta.
     *
     * @param array $response Datos de respuesta del heartbeat
     * @param array $data Datos enviados desde el cliente
     * @return array Respuesta modificada
     */
    public function heartbeat_received( $response, $data ): array {
        if ( ! is_user_logged_in() || ! isset( $data['fplms_user_active'] ) ) {
            return $response;
        }

        $user_id = get_current_user_id();
        $current_time = current_time( 'mysql' );

        // Actualizar actividad sin restricción de tiempo
        update_user_meta( $user_id, 'last_activity', $current_time );

        $response['fplms_activity_recorded'] = true;

        return $response;
    }

    // ==========================
    //   MATRIZ DE PRIVILEGIOS
    // ==========================

    public function handle_caps_matrix_form(): void {

        if ( ! isset( $_POST['fplms_caps_action'] ) ) {
            return;
        }

        // Solo administradores (o quien tenga manage_options)
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if (
            ! isset( $_POST['fplms_caps_nonce'] ) ||
            ! wp_verify_nonce( $_POST['fplms_caps_nonce'], 'fplms_caps_save' )
        ) {
            return;
        }

        // Roles válidos del sistema simplificado
        $roles_def = [
            'subscriber',
            FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR,
            'administrator',
        ];

        $caps_def = FairPlay_LMS_Config::get_plugin_caps();

        $incoming = isset( $_POST['fplms_caps'] ) && is_array( $_POST['fplms_caps'] ) ? $_POST['fplms_caps'] : [];

        $matrix = [];

        foreach ( $roles_def as $role_key ) {
            $matrix[ $role_key ] = [];
            foreach ( $caps_def as $cap_key ) {
                $matrix[ $role_key ][ $cap_key ] = isset( $incoming[ $role_key ][ $cap_key ] );
            }
        }

        FairPlay_LMS_Capabilities::save_matrix( $matrix );

        // Registrar en bitácora de auditoría
        if ( class_exists( 'FairPlay_LMS_Audit_Logger' ) ) {
            $audit = new FairPlay_LMS_Audit_Logger();
            $audit->log_action(
                'caps_matrix_updated',
                'privilege_matrix',
                0,
                'Matriz de privilegios',
                null,
                maybe_serialize( $matrix )
            );
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'         => 'fplms-users',
                    'updated_caps' => 1,
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    // ==========================
    //   LISTADO Y FILTROS
    // ==========================

    public function render_users_page(): void {

        if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_USERS ) ) {
            wp_die( 'No tienes permisos para acceder a esta sección.' );
        }
        
        // Procesar acciones masivas
        $this->handle_bulk_user_actions();
        
        // Procesar formularios
        $this->handle_new_user_form();
        $this->handle_edit_user_form();

        // Si se solicita edición de un usuario específico, mostrar formulario de edición
        if ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] && isset( $_GET['user_id'] ) ) {
            $this->render_edit_user_form( absint( $_GET['user_id'] ) );
            return;
        }

        if ( isset( $_GET['action'] ) && 'create' === $_GET['action'] ) {
            $this->render_create_user_form();
            return;
        }

        $cities    = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CITY );
        $companies = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_COMPANY );
        $channels  = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CHANNEL );
        $branches  = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_BRANCH );
        $roles     = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_ROLE );

        $filter_city    = isset( $_GET['fplms_filter_city'] ) ? absint( $_GET['fplms_filter_city'] ) : 0;
        $filter_company = isset( $_GET['fplms_filter_company'] ) ? absint( $_GET['fplms_filter_company'] ) : 0;
        $filter_channel = isset( $_GET['fplms_filter_channel'] ) ? absint( $_GET['fplms_filter_channel'] ) : 0;
        $filter_branch  = isset( $_GET['fplms_filter_branch'] ) ? absint( $_GET['fplms_filter_branch'] ) : 0;
        $filter_role    = isset( $_GET['fplms_filter_role'] ) ? absint( $_GET['fplms_filter_role'] ) : 0;
        $filter_status  = isset( $_GET['fplms_filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['fplms_filter_status'] ) ) : '';

        $users  = $this->get_users_filtered_by_structure( $filter_city, $filter_company, $filter_channel, $filter_branch, $filter_role );
        if ( in_array( $filter_status, [ 'active', 'inactive' ], true ) ) {
            $users = array_values( array_filter( $users, function( $user ) use ( $filter_status ) {
                $meta_status = get_user_meta( $user->ID, 'fplms_user_status', true );
                $is_active   = empty( $meta_status ) || $meta_status === 'active';
                return $filter_status === 'active' ? $is_active : ! $is_active;
            } ) );
        }
        $matrix = FairPlay_LMS_Capabilities::get_matrix();
        $can_edit = current_user_can( 'manage_options' );

        // Roles simplificados: 3 opciones con nombres en español
        // Mapeo: Estudiante->subscriber (MasterStudy), Docente->stm_lms_instructor, Administrador->administrator
        $roles_def_labels = [
            'subscriber'                      => 'Estudiante',
            FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR => 'Docente',
            'administrator'                   => 'Administrador',
        ];

        $caps_def_labels = [
            FairPlay_LMS_Config::CAP_MANAGE_STRUCTURES => 'Gestionar estructuras',
            FairPlay_LMS_Config::CAP_MANAGE_USERS      => 'Gestionar usuarios',
            FairPlay_LMS_Config::CAP_MANAGE_COURSES    => 'Gestionar cursos',
            FairPlay_LMS_Config::CAP_VIEW_REPORTS      => 'Ver informes',
            FairPlay_LMS_Config::CAP_VIEW_PROGRESS     => 'Ver avances',
            FairPlay_LMS_Config::CAP_VIEW_CALENDAR     => 'Ver calendario',
        ];
        
        // Obtener datos de estructura para cascada
        $structure_relations = $this->get_structure_relations();
        
        ?>
        <style>
            .fplms-users-wrapper {
                background: #f5f7fa;
                min-height: 100vh;
                padding: 20px;
                margin-left: -20px;
            }
            .fplms-users-container {
                max-width: 1200px;
                margin: 0 auto;
                background: #fff;
                border-radius: 12px;
                padding: 30px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }
            .fplms-users-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 25px;
                padding-bottom: 20px;
                border-bottom: 2px solid #f0f0f0;
                flex-wrap: wrap;
                gap: 15px;
            }
            .fplms-users-title-section {
                display: flex;
                align-items: center;
                gap: 15px;
            }
            .fplms-users-icon {
                font-size: 32px;
                flex-shrink: 0;
            }
            .fplms-users-title {
                margin: 0;
                font-size: 24px;
                font-weight: 700;
                color: #1a1a1a;
            }
            .fplms-users-actions {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            .fplms-action-button {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                background: linear-gradient(135deg, #F44336 0%, #D32F2F 100%);
                color: white;
                padding: 10px 20px;
                border-radius: 8px;
                text-decoration: none;
                font-size: 14px;
                font-weight: 600;
                transition: all 0.3s ease;
                border: none;
                cursor: pointer;
                box-shadow: 0 2px 8px rgba(244, 67, 54, 0.3);
            }
            .fplms-action-button:hover {
                background: linear-gradient(135deg, #D32F2F 0%, #B71C1C 100%);
                box-shadow: 0 4px 16px rgba(244, 67, 54, 0.4);
                transform: translateY(-2px);
                color: white;
                text-decoration: none;
            }
            .fplms-action-button.secondary {
                background: linear-gradient(135deg, #607D8B 0%, #455A64 100%);
                box-shadow: 0 2px 8px rgba(96, 125, 139, 0.3);
            }
            .fplms-action-button.secondary:hover {
                background: linear-gradient(135deg, #455A64 0%, #37474F 100%);
                box-shadow: 0 4px 16px rgba(96, 125, 139, 0.4);
            }
            .fplms-filters-section {
                background: #f8f9fa;
                border-radius: 10px;
                padding: 25px;
                margin-bottom: 25px;
                border: 1px solid #e9ecef;
            }
            .fplms-filters-title {
                margin: 0 0 20px 0;
                font-size: 16px;
                font-weight: 600;
                color: #333;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .fplms-filters-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 20px;
            }
            .fplms-filter-field {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .fplms-filter-field label {
                font-weight: 600;
                color: #555;
                font-size: 13px;
            }
            .fplms-filter-field select {
                padding: 10px 35px 10px 15px;
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
                background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23333%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E');
                background-repeat: no-repeat;
                background-position: right 10px top 50%;
                background-size: 10px auto;
            }
            .fplms-filter-field select:hover {
                border-color: #F44336;
            }
            .fplms-filter-field select:focus {
                outline: none;
                border-color: #F44336;
                box-shadow: 0 0 0 3px rgba(244, 67, 54, 0.1);
            }
            .fplms-filter-button {
                background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
                color: white;
                padding: 10px 24px;
                border-radius: 8px;
                border: none;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                box-shadow: 0 2px 8px rgba(33, 150, 243, 0.3);
            }
            .fplms-filter-button:hover {
                background: linear-gradient(135deg, #1976D2 0%, #1565C0 100%);
                box-shadow: 0 4px 16px rgba(33, 150, 243, 0.4);
                transform: translateY(-2px);
            }
            .fplms-section-title {
                margin: 30px 0 20px 0;
                font-size: 18px;
                font-weight: 700;
                color: #1a1a1a;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .fplms-section-title:before {
                content: '📋';
                font-size: 22px;
            }
            
            /* === CONTROLES DE TABLA === */
            .fplms-table-controls {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin: 20px 0;
                gap: 20px;
                flex-wrap: wrap;
            }
            .fplms-search-box {
                flex: 1;
                min-width: 300px;
            }
            .fplms-search-input {
                width: 100%;
                padding: 12px 20px;
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                font-size: 14px;
                transition: all 0.3s ease;
            }
            .fplms-search-input:focus {
                outline: none;
                border-color: #F44336;
                box-shadow: 0 0 0 3px rgba(244, 67, 54, 0.1);
            }
            .fplms-per-page-selector {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .fplms-per-page-selector label {
                font-weight: 600;
                color: #555;
                font-size: 14px;
            }
            .fplms-per-page-selector select {
                padding: 8px 30px 8px 12px;
                border: 2px solid #e0e0e0;
                border-radius: 6px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.3s ease;
                background: white url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23333%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E') no-repeat right 8px center;
                background-size: 10px;
                -webkit-appearance: none;
                -moz-appearance: none;
                appearance: none;
            }
            .fplms-per-page-selector select:hover {
                border-color: #F44336;
            }
            .fplms-per-page-selector select:focus {
                outline: none;
                border-color: #F44336;
                box-shadow: 0 0 0 3px rgba(244, 67, 54, 0.1);
            }
            
            /* === ACCIONES MASIVAS === */
            .fplms-bulk-actions-container {
                display: flex;
                align-items: center;
                gap: 15px;
                padding: 15px 20px;
                background: #f8f9fa;
                border-radius: 8px;
                border: 1px solid #e9ecef;
                margin-bottom: 20px;
            }
            .fplms-bulk-actions-container strong {
                color: #333;
                font-size: 14px;
            }
            #fplms-bulk-count {
                background: #F44336;
                color: white;
                padding: 4px 12px;
                border-radius: 12px;
                font-size: 13px;
                font-weight: 600;
            }
            .fplms-bulk-select {
                padding: 8px 30px 8px 12px;
                border: 2px solid #ddd;
                border-radius: 6px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.3s ease;
                background: white url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23333%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E') no-repeat right 8px center;
                background-size: 10px;
                -webkit-appearance: none;
                -moz-appearance: none;
                appearance: none;
                min-width: 200px;
            }
            .fplms-bulk-apply-btn {
                background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
                color: white;
                padding: 8px 20px;
                border-radius: 6px;
                border: none;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                box-shadow: 0 2px 6px rgba(33, 150, 243, 0.3);
            }
            .fplms-bulk-apply-btn:hover {
                background: linear-gradient(135deg, #1976D2 0%, #1565C0 100%);
                box-shadow: 0 4px 12px rgba(33, 150, 243, 0.4);
                transform: translateY(-1px);
            }
            
            /* === TABLA MEJORADA === */
            .fplms-users-table .checkbox-input {
                width: 18px;
                height: 18px;
                cursor: pointer;
                accent-color: #F44336;
            }
            .fplms-users-table thead th:first-child {
                width: 50px;
                text-align: center;
            }
            .fplms-users-table tbody td:first-child {
                text-align: center;
            }
            
            /* === PAGINACIÓN === */
            .fplms-pagination {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 10px;
                margin-top: 25px;
                flex-wrap: wrap;
            }
            .fplms-pagination-btn {
                padding: 8px 15px;
                border: 1px solid #ddd;
                background: white;
                color: #555;
                border-radius: 6px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.2s ease;
            }
            .fplms-pagination-btn:hover:not(:disabled) {
                background: #f5f5f5;
                border-color: #F44336;
                color: #F44336;
            }
            .fplms-pagination-btn.active {
                background: #F44336;
                color: white;
                border-color: #F44336;
            }
            .fplms-pagination-btn:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
            .fplms-pagination-info {
                color: #666;
                font-size: 14px;
                padding: 8px 15px;
            }
            
            /* === MODALES === */
            .fplms-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.6);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 100000;
                animation: fadeIn 0.2s ease;
            }
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            .fplms-modal-content {
                background: white;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                max-width: 500px;
                width: 90%;
                animation: slideIn 0.3s ease;
            }
            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateY(-50px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            .fplms-modal-header {
                padding: 20px;
                border-bottom: 1px solid #eee;
            }
            .fplms-modal-header h3 {
                margin: 0;
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 18px;
            }
            .fplms-modal-body {
                padding: 30px 20px;
            }
            .fplms-modal-body p {
                margin: 0 0 15px 0;
                font-size: 15px;
                color: #333;
            }
            .fplms-modal-footer {
                padding: 15px 20px;
                background: #f8f9fa;
                border-top: 1px solid #dee2e6;
                display: flex;
                justify-content: flex-end;
                gap: 10px;
            }
            .fplms-modal-footer button {
                padding: 10px 24px;
                border-radius: 6px;
                border: none;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            .fplms-modal-footer .button {
                background: #e9ecef;
                color: #495057;
            }
            .fplms-modal-footer .button:hover {
                background: #dee2e6;
            }
            .fplms-modal-footer .button-primary {
                background: #F44336;
                color: white;
            }
            .fplms-modal-footer .button-primary:hover {
                background: #D32F2F;
            }
            
            /* === FILTROS COLAPSABLES === */
            .fplms-filters-toggle {
                background: #2196F3;
                color: white;
                padding: 10px 20px;
                border-radius: 8px;
                border: none;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 15px;
            }
            .fplms-filters-toggle:hover {
                background: #1976D2;
            }
            .fplms-filters-section.collapsible {
                display: none;
            }
            .fplms-filters-section.collapsible.active {
                display: block;
            }
            
            /* Estilos para badges de estado */
            .fplms-status-badge {
                display: inline-block;
                padding: 6px 12px;
                border-radius: 12px;
                font-size: 13px;
                font-weight: 600;
                text-align: center;
                white-space: nowrap;
            }
            .fplms-status-badge.fplms-status-active {
                background: #ECFDF3;
                color: #027A48;
            }
            .fplms-status-badge.fplms-status-inactive {
                background: #fffaeb;
                color: #b54708;
            }
            
            /* Estilos para acciones con iconos SVG */
            .fplms-action-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 32px;
                height: 32px;
                margin: 0 4px;
                border-radius: 6px;
                transition: all 0.2s ease;
                cursor: pointer;
                position: relative;
            }
            .fplms-action-icon svg {
                width: 18px;
                height: 18px;
                transition: transform 0.2s ease;
            }
            .fplms-action-icon:hover svg {
                transform: scale(1.15);
            }
            .fplms-action-icon.edit {
                background: #E3F2FD;
            }
            .fplms-action-icon.edit:hover {
                background: #2196F3;
            }
            .fplms-action-icon.edit svg {
                fill: #2196F3;
            }
            .fplms-action-icon.edit:hover svg {
                fill: #fff;
            }
            .fplms-action-icon.activate {
                background: #E8F5E9;
            }
            .fplms-action-icon.activate:hover {
                background: #4CAF50;
            }
            .fplms-action-icon.activate svg {
                fill: #4CAF50;
            }
            .fplms-action-icon.activate:hover svg {
                fill: #fff;
            }
            .fplms-action-icon.deactivate {
                background: #FFF3E0;
            }
            .fplms-action-icon.deactivate:hover {
                background: #FF9800;
            }
            .fplms-action-icon.deactivate svg {
                fill: #FF9800;
            }
            .fplms-action-icon.deactivate:hover svg {
                fill: #fff;
            }
            .fplms-action-icon.delete {
                background: #FFEBEE;
            }
            .fplms-action-icon.delete:hover {
                background: #F44336;
            }
            .fplms-action-icon.delete svg {
                fill: #F44336;
            }
            .fplms-action-icon.delete:hover svg {
                fill: #fff;
            }
            .fplms-action-icon.progress {
                background: #F3E5F5;
            }
            .fplms-action-icon.progress:hover {
                background: #9C27B0;
            }
            .fplms-action-icon.progress svg {
                fill: #9C27B0;
            }
            .fplms-action-icon.progress:hover svg {
                fill: #fff;
            }
            
            /* Tooltip para los iconos */
            .fplms-action-icon[data-tooltip]::after {
                content: attr(data-tooltip);
                position: absolute;
                bottom: 100%;
                left: 50%;
                transform: translateX(-50%);
                padding: 6px 12px;
                background: #333;
                color: white;
                border-radius: 4px;
                font-size: 12px;
                white-space: nowrap;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.2s ease;
                margin-bottom: 5px;
                z-index: 1000;
            }
            .fplms-action-icon[data-tooltip]:hover::after {
                opacity: 1;
            }
            
            /* Modal de acción */
            .fplms-action-modal-overlay {
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
            .fplms-action-modal-overlay.active {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .fplms-action-modal {
                background: white;
                border-radius: 12px;
                padding: 0;
                max-width: 480px;
                width: 90%;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
                animation: slideUp 0.3s ease;
            }
            .fplms-action-modal-header {
                padding: 24px 24px 16px;
                border-bottom: 1px solid #E0E0E0;
            }
            .fplms-action-modal-header h3 {
                margin: 0;
                font-size: 20px;
                font-weight: 600;
                color: #212121;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .fplms-action-modal-header svg {
                width: 24px;
                height: 24px;
            }
            .fplms-action-modal-body {
                padding: 24px;
            }
            .fplms-action-modal-body p {
                margin: 0 0 16px 0;
                color: #616161;
                line-height: 1.6;
            }
            .fplms-action-modal-user-info {
                background: #F5F5F5;
                border-radius: 8px;
                padding: 16px;
                margin: 16px 0;
            }
            .fplms-action-modal-user-info strong {
                display: block;
                color: #212121;
                margin-bottom: 4px;
            }
            .fplms-action-modal-user-info span {
                color: #757575;
                font-size: 14px;
            }
            .fplms-action-modal-footer {
                padding: 16px 24px;
                border-top: 1px solid #E0E0E0;
                display: flex;
                gap: 12px;
                justify-content: flex-end;
            }
            .fplms-action-modal-footer button {
                padding: 10px 24px;
                border: none;
                border-radius: 6px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            .fplms-modal-btn-cancel {
                background: #F5F5F5;
                color: #616161;
            }
            .fplms-modal-btn-cancel:hover {
                background: #E0E0E0;
            }
            .fplms-modal-btn-confirm {
                background: #2196F3;
                color: white;
            }
            .fplms-modal-btn-confirm:hover {
                background: #1976D2;
            }
            .fplms-modal-btn-confirm.danger {
                background: #F44336;
            }
            .fplms-modal-btn-confirm.danger:hover {
                background: #D32F2F;
            }
            .fplms-modal-btn-confirm.warning {
                background: #FF9800;
            }
            .fplms-modal-btn-confirm.warning:hover {
                background: #F57C00;
            }
            .fplms-modal-btn-confirm.success {
                background: #4CAF50;
            }
            .fplms-modal-btn-confirm.success:hover {
                background: #388E3C;
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
            
            @media (max-width: 768px) {
                .fplms-users-header {
                    flex-direction: column;
                    align-items: flex-start;
                }
                .fplms-filters-grid {
                    grid-template-columns: 1fr;
                }
                .fplms-table-controls {
                    flex-direction: column;
                    align-items: stretch;
                }
                .fplms-search-box {
                    min-width: 100%;
                }
                .fplms-bulk-actions-container {
                    flex-direction: column;
                    align-items: stretch;
                }
                .fplms-bulk-select {
                    width: 100%;
                    min-width: auto;
                }
            }
        </style>
        <div class="fplms-users-wrapper">
            <div class="fplms-users-container">
                <div class="fplms-users-header">
                    <div class="fplms-users-title-section">
                        <div class="fplms-users-icon">👥</div>
                        <h1 class="fplms-users-title">Usuarios</h1>
                    </div>

                </div>

                <!-- MENSAJES DE ÉXITO/ERROR -->
                <?php if ( isset( $_GET['bulk_success'] ) && $_GET['bulk_success'] > 0 ) : ?>
                    <div class="notice notice-success is-dismissible" style="margin: 20px 0; padding: 12px 20px; background: #E8F5E9; border-left: 4px solid #4CAF50;">
                        <p style="margin: 0; color: #2E7D32; font-weight: 500;">
                            ✓ Acción completada exitosamente. 
                            <?php 
                            $action_text = isset( $_GET['bulk_action'] ) ? $_GET['bulk_action'] : '';
                            $count = intval( $_GET['bulk_success'] );
                            switch( $action_text ) {
                                case 'activate':
                                    echo sprintf( '%d usuario(s) activado(s)', $count );
                                    break;
                                case 'deactivate':
                                    echo sprintf( '%d usuario(s) desactivado(s)', $count );
                                    break;
                                case 'delete':
                                    echo sprintf( '%d usuario(s) eliminado(s)', $count );
                                    break;
                                default:
                                    echo sprintf( '%d usuario(s) procesado(s)', $count );
                            }
                            ?>
                        </p>
                    </div>
                <?php endif; ?>
                
                <?php if ( isset( $_GET['bulk_error'] ) && $_GET['bulk_error'] > 0 ) : ?>
                    <div class="notice notice-error is-dismissible" style="margin: 20px 0; padding: 12px 20px; background: #FFEBEE; border-left: 4px solid #F44336;">
                        <p style="margin: 0; color: #C62828; font-weight: 500;">
                            ⚠ Se encontraron errores al procesar <?php echo intval( $_GET['bulk_error'] ); ?> usuario(s).
                        </p>
                    </div>
                <?php endif; ?>

                <!-- FILTROS -->
                <div class="fplms-filters-section">
                    <h3 class="fplms-filters-title">🔍 Filtrar usuarios</h3>
                    <form method="get">
                        <input type="hidden" name="page" value="fplms-users">
                        <div class="fplms-filters-grid">
                            <div class="fplms-filter-field">
                                <label for="fplms_filter_city">Ciudad</label>
                                <select name="fplms_filter_city" id="fplms_filter_city">
                                    <option value="">Todas</option>
                                    <?php foreach ( $cities as $id => $name ) : ?>
                                        <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $filter_city, $id ); ?>>
                                            <?php echo esc_html( $name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fplms-filter-field">
                                <label for="fplms_filter_company">Empresa</label>
                                <select name="fplms_filter_company" id="fplms_filter_company">
                                    <option value="">Todas</option>
                                    <?php foreach ( $companies as $id => $name ) : ?>
                                        <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $filter_company, $id ); ?>>
                                            <?php echo esc_html( $name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fplms-filter-field">
                                <label for="fplms_filter_channel">Canal</label>
                                <select name="fplms_filter_channel" id="fplms_filter_channel">
                                    <option value="">Todos</option>
                                    <?php foreach ( $channels as $id => $name ) : ?>
                                        <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $filter_channel, $id ); ?>>
                                            <?php echo esc_html( $name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fplms-filter-field">
                                <label for="fplms_filter_branch">Sucursal</label>
                                <select name="fplms_filter_branch" id="fplms_filter_branch">
                                    <option value="">Todas</option>
                                    <?php foreach ( $branches as $id => $name ) : ?>
                                        <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $filter_branch, $id ); ?>>
                                            <?php echo esc_html( $name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fplms-filter-field">
                                <label for="fplms_filter_role">Cargo</label>
                                <select name="fplms_filter_role" id="fplms_filter_role">
                                    <option value="">Todos</option>
                                    <?php foreach ( $roles as $id => $name ) : ?>
                                        <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $filter_role, $id ); ?>>
                                            <?php echo esc_html( $name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="fplms-filter-field">
                                <label for="fplms_filter_status">Estado</label>
                                <select name="fplms_filter_status" id="fplms_filter_status">
                                    <option value="">Todos</option>
                                    <option value="active" <?php selected( $filter_status, 'active' ); ?>>✓ Activos</option>
                                    <option value="inactive" <?php selected( $filter_status, 'inactive' ); ?>>⊘ Inactivos</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:flex;gap:10px;align-items:center;margin-top:4px;">
                            <button type="submit" class="fplms-filter-button">🔍 Filtrar usuarios</button>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=fplms-users' ) ); ?>" class="fplms-filter-button" style="background:#f8f9fa;color:#6b7280;border:1px solid #dee2e6;text-decoration:none;">✕ Limpiar filtros</a>
                        </div>
                    </form>
                </div>

                <?php if ( isset( $_GET['user_created'] ) ) : ?>
                <!-- Modal: usuario creado exitosamente -->
                <div id="modal-user-created" class="fplms-modal" style="display:none;">
                    <div class="fplms-modal-content" style="max-width:420px;text-align:center;">
                        <div style="padding:40px 32px 32px;">
                            <div style="width:68px;height:68px;border-radius:50%;background:linear-gradient(135deg,#d1fae5,#a7f3d0);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:34px;height:34px;"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                            <h3 style="margin:0 0 8px;font-size:20px;font-weight:700;color:#111827;">¡Usuario creado!</h3>
                            <p style="margin:0 0 6px;font-size:14px;color:#6b7280;">El nuevo usuario fue registrado correctamente.</p>
                            <p style="margin:0 0 28px;font-size:13px;color:#9ca3af;">ID asignado: <strong style="color:#374151;">#<?php echo esc_html( absint( $_GET['user_created'] ) ); ?></strong></p>
                            <div style="display:flex;gap:10px;justify-content:center;">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=fplms-users&action=create' ) ); ?>"
                                   style="padding:10px 22px;background:#f3f4f6;color:#374151;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;border:1px solid #e5e7eb;">
                                    + Crear otro
                                </a>
                                <button onclick="document.getElementById('modal-user-created').style.display='none';history.replaceState(null,'',location.pathname+'?page=fplms-users');"
                                        style="padding:10px 22px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-radius:8px;font-size:14px;font-weight:600;border:none;cursor:pointer;">
                                    Ver usuarios →
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var modal = document.getElementById('modal-user-created');
                    if (modal) {
                        modal.style.display = 'flex';
                        // Cerrar al hacer clic en el fondo
                        modal.addEventListener('click', function(e) {
                            if (e.target === modal) {
                                modal.style.display = 'none';
                                history.replaceState(null, '', location.pathname + '?page=fplms-users');
                            }
                        });
                    }
                });
                </script>
                <?php endif; ?>

                <h2 class="fplms-section-title">Usuarios registrados</h2>

                <!-- BARRA DE ACCIONES MASIVAS (oculta por defecto) -->
                <div class="fplms-bulk-actions-container" id="fplms-bulk-actions" style="display: none;">
                    <span class="fplms-bulk-label">Seleccionados: <span class="fplms-bulk-badge" id="fplms-bulk-count">0</span></span>
                    <select class="fplms-bulk-select" id="fplms-bulk-action">
                        <option value="">Seleccionar acción...</option>
                        <option value="activate">Activar usuarios</option>
                        <option value="deactivate">Desactivar usuarios</option>
                        <option value="delete">Eliminar usuarios</option>
                    </select>
                    <button type="button" class="fplms-bulk-apply-btn" id="fplms-bulk-apply-btn">Aplicar</button>
                </div>

            <style>
                /* ── Card wrapper ── */
                .fplms-table-card {
                    background: #fff;
                    border-radius: 12px;
                    border: 1px solid #e5e7eb;
                    box-shadow: 0 1px 6px rgba(0,0,0,.06);
                    overflow: hidden;
                    margin-top: 16px;
                }

                /* ── Top bar ── */
                .fplms-table-topbar {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 14px 18px;
                    border-bottom: 1px solid #f3f4f6;
                    gap: 12px;
                    flex-wrap: wrap;
                }
                .fplms-table-topbar-left {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    font-size: 14px;
                    color: #6b7280;
                }
                .fplms-table-topbar-right {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    flex: 1;
                    justify-content: flex-end;
                }
                /* ── Search wrapper ── */
                .fplms-search-wrapper {
                    position: relative;
                    flex: 1;
                    min-width: 180px;
                    max-width: 380px;
                }
                .fplms-search-clear {
                    position: absolute;
                    right: 9px;
                    top: 50%;
                    transform: translateY(-50%);
                    background: none;
                    border: none;
                    cursor: pointer;
                    color: #9ca3af;
                    font-size: 15px;
                    line-height: 1;
                    padding: 2px;
                    display: none;
                }
                .fplms-search-clear:hover { color: #374151; }
                .fplms-entries-select {
                    padding: 5px 10px;
                    border: 1px solid #e5e7eb;
                    border-radius: 6px;
                    font-size: 14px;
                    color: #374151;
                    background: #fff;
                    cursor: pointer;
                }
                .fplms-search-input {
                    padding: 8px 32px 8px 14px;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    font-size: 14px;
                    color: #374151;
                    width: 100%;
                    outline: none;
                    transition: border-color .2s, box-shadow .2s;
                    box-sizing: border-box;
                }
                .fplms-search-input:focus {
                    border-color: #667eea;
                    box-shadow: 0 0 0 3px rgba(102,126,234,.1);
                }
                .fplms-download-btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 8px 16px;
                    background: #fff;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 500;
                    color: #374151;
                    cursor: pointer;
                    transition: all .2s;
                    text-decoration: none;
                }
                .fplms-download-btn:hover { background: #f9fafb; border-color: #d1d5db; }
                .fplms-download-btn svg { width: 16px; height: 16px; fill: #6b7280; }

                /* ── Table wrapper ── */
                .fplms-users-table-wrapper {
                    overflow-x: auto;
                    -webkit-overflow-scrolling: touch;
                }

                /* ── Table ── */
                .fplms-users-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 0;
                    font-size: 14px;
                }
                .fplms-users-table thead th {
                    padding: 11px 16px;
                    text-align: left;
                    font-size: 12px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: .5px;
                    color: #6b7280;
                    background: #f9fafb;
                    border-bottom: 1px solid #e5e7eb;
                    white-space: nowrap;
                }
                .fplms-users-table thead th.sortable { cursor: pointer; user-select: none; }
                .fplms-users-table thead th.sortable::after { content: ' \21D5'; color: #d1d5db; font-size: 11px; }
                .fplms-users-table tbody tr { border-bottom: 1px solid #f3f4f6; transition: background .12s; }
                .fplms-users-table tbody tr:last-child { border-bottom: none; }
                .fplms-users-table tbody tr:hover { background: #f9fafb; }
                .fplms-users-table td {
                    padding: 13px 16px;
                    color: #374151;
                    vertical-align: middle;
                }

                /* ── User cell ── */
                .fplms-user-cell-name { font-weight: 600; color: #111827; font-size: 14px; line-height: 1.4; }
                .fplms-user-cell-email { font-size: 12px; color: #9ca3af; margin-top: 2px; }

                /* ── Status badges ── */
                .fplms-status-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                    padding: 3px 10px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: 600;
                    white-space: nowrap;
                }
                .fplms-status-active  { background: #ECFDF3; color: #027A48; }
                .fplms-status-inactive { background: #FEF3F2; color: #B42318; }

                /* ── Role badge ── */
                .fplms-role-badge {
                    display: inline-block;
                    padding: 3px 10px;
                    /*background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);*/
                    color: #000000;
                    border-radius: 20px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: .4px;
                    white-space: nowrap;
                }

                /* ── Direct action icons ── */
                .fplms-direct-actions { display: flex; align-items: center; gap: 2px; justify-content: center; }
                .fplms-action-icon-btn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 32px; height: 32px;
                    border-radius: 6px;
                    border: none;
                    background: transparent;
                    cursor: pointer;
                    transition: all .15s;
                    text-decoration: none;
                    padding: 0;
                }
                .fplms-action-icon-btn svg { width: 17px; height: 17px; fill: #9ca3af; transition: fill .15s; }
                .fplms-action-icon-btn:hover { background: #f3f4f6; }
                .fplms-action-icon-btn.edit:hover svg      { fill: #2563eb; }
                .fplms-action-icon-btn.delete:hover svg    { fill: #dc2626; }
                .fplms-action-icon-btn.activate:hover svg  { fill: #059669; }
                .fplms-action-icon-btn.deactivate:hover svg { fill: #d97706; }

                /* ── Checkbox ── */
                .checkbox-input { width: 16px; height: 16px; cursor: pointer; accent-color: #667eea; }

                /* ── Bulk actions bar ── */
                .fplms-bulk-actions-container {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    padding: 11px 18px;
                    background: #fff;
                    border: 1px solid #e5e7eb;
                    border-radius: 12px;
                    box-shadow: 0 1px 6px rgba(0,0,0,.06);
                    margin-bottom: 12px;
                    font-size: 14px;
                    flex-wrap: wrap;
                }
                .fplms-bulk-label {
                    font-size: 14px;
                    color: #6b7280;
                    font-weight: 500;
                    white-space: nowrap;
                }
                .fplms-bulk-badge {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 22px;
                    height: 22px;
                    padding: 0 6px;
                    background: #667eea;
                    color: #fff;
                    font-size: 12px;
                    font-weight: 700;
                    border-radius: 11px;
                    margin-left: 4px;
                }
                .fplms-bulk-select {
                    padding: 7px 12px;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    font-size: 14px;
                    color: #374151;
                    background: #fff;
                    cursor: pointer;
                    outline: none;
                    transition: border-color .2s;
                }
                .fplms-bulk-select:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,.1); }
                .fplms-bulk-apply-btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 7px 18px;
                    background: #667eea;
                    color: #fff;
                    border: none;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background .2s;
                }
                .fplms-bulk-apply-btn:hover { background: #5a6fd6; }

                /* ── Pagination ── */
                .fplms-pagination-wrapper {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 14px 18px;
                    border-top: 1px solid #f3f4f6;
                    flex-wrap: wrap;
                    gap: 8px;
                }
                .fplms-showing-info { font-size: 13px; color: #6b7280; }
                .fplms-pagination-controls { display: flex; align-items: center; gap: 4px; }
                .fplms-pagination-btn {
                    min-width: 36px; height: 34px;
                    padding: 0 10px;
                    border: 1px solid #e5e7eb;
                    background: #fff;
                    color: #374151;
                    font-size: 13px;
                    border-radius: 6px;
                    cursor: pointer;
                    transition: all .15s;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                }

                /* ── Matrix card ── */
                .fplms-matrix-card {
                    background: #fff;
                    border-radius: 12px;
                    border: 1px solid #e5e7eb;
                    box-shadow: 0 1px 6px rgba(0,0,0,.06);
                    overflow: hidden;
                    margin-top: 16px;
                }
                .fplms-matrix-header {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    padding: 16px 20px;
                    border-bottom: 1px solid #f3f4f6;
                }
                .fplms-matrix-title {
                    font-size: 16px;
                    font-weight: 700;
                    color: #111827;
                    margin: 0;
                }
                .fplms-matrix-table-wrap { overflow-x: auto; }
                .fplms-matrix-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 14px;
                }
                .fplms-matrix-table thead th {
                    padding: 11px 18px;
                    text-align: left;
                    font-size: 12px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: .5px;
                    color: #6b7280;
                    background: #f9fafb;
                    border-bottom: 1px solid #e5e7eb;
                    white-space: nowrap;
                }
                .fplms-matrix-table thead th:first-child { min-width: 140px; }
                .fplms-matrix-table tbody tr { border-bottom: 1px solid #f3f4f6; transition: background .12s; }
                .fplms-matrix-table tbody tr:last-child { border-bottom: none; }
                .fplms-matrix-table tbody tr:hover { background: #f9fafb; }
                .fplms-matrix-table td {
                    padding: 13px 18px;
                    color: #374151;
                    vertical-align: middle;
                }
                .fplms-matrix-table td:first-child { font-weight: 600; color: #111827; }
                .fplms-matrix-table td:not(:first-child) { text-align: center; }
                .fplms-matrix-table input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; accent-color: #667eea; }
                .fplms-matrix-footer {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    padding: 14px 20px;
                    border-top: 1px solid #f3f4f6;
                }
                .fplms-matrix-note {
                    font-size: 12px;
                    color: #9ca3af;
                    margin-left: auto;
                }
                .fplms-matrix-save-btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 8px 20px;
                    background: #667eea;
                    color: #fff;
                    border: none;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background .2s;
                }
                .fplms-matrix-save-btn:hover { background: #5a6fd6; }
                .fplms-matrix-cancel-btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 8px 16px;
                    background: #fff;
                    color: #374151;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all .2s;
                    text-decoration: none;
                }
                .fplms-matrix-cancel-btn:hover { background: #f9fafb; border-color: #d1d5db; color: #374151; }
                /* ── Confirm modal ── */
                .fplms-confirm-overlay {
                    display: none;
                    position: fixed;
                    inset: 0;
                    background: rgba(0,0,0,.45);
                    z-index: 99990;
                    align-items: center;
                    justify-content: center;
                }
                .fplms-confirm-overlay.open { display: flex; }
                .fplms-confirm-modal {
                    background: #fff;
                    border-radius: 14px;
                    box-shadow: 0 8px 40px rgba(0,0,0,.18);
                    padding: 32px 28px 24px;
                    max-width: 420px;
                    width: 100%;
                    text-align: center;
                }
                .fplms-confirm-modal .fcm-icon {
                    font-size: 36px;
                    margin-bottom: 12px;
                }
                .fplms-confirm-modal h3 { font-size: 18px; font-weight: 700; color: #111827; margin: 0 0 8px; }
                .fplms-confirm-modal p  { font-size: 14px; color: #6b7280; margin: 0 0 24px; }
                .fplms-confirm-modal .fcm-actions { display: flex; gap: 10px; justify-content: center; }
                .fplms-confirm-confirm-btn {
                    padding: 9px 24px;
                    background: #667eea;
                    color: #fff;
                    border: none;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background .2s;
                }
                .fplms-confirm-confirm-btn:hover { background: #5a6fd6; }
                .fplms-confirm-cancel-btn {
                    padding: 9px 20px;
                    background: #fff;
                    color: #374151;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all .2s;
                }
                .fplms-confirm-cancel-btn:hover { background: #f9fafb; }
                .fplms-pagination-btn:hover:not(:disabled) { background: #f3f4f6; border-color: #d1d5db; }
                .fplms-pagination-btn.active { background: #667eea; color: #fff; border-color: #667eea; font-weight: 600; }
                .fplms-pagination-btn:disabled { color: #d1d5db; cursor: not-allowed; }

                /* ── Download dropdown ── */
                .fplms-download-wrapper { position: relative; display: inline-flex; }
                .fplms-download-dropdown {
                    position: absolute;
                    top: calc(100% + 6px);
                    right: 0;
                    background: #fff;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    box-shadow: 0 4px 16px rgba(0,0,0,.12);
                    min-width: 170px;
                    z-index: 200;
                    display: none;
                    overflow: hidden;
                }
                .fplms-download-dropdown.open { display: block; }
                .fplms-download-dropdown-item {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 10px 14px;
                    font-size: 14px;
                    color: #374151;
                    cursor: pointer;
                    border: none;
                    background: none;
                    width: 100%;
                    text-align: left;
                }
                .fplms-download-dropdown-item:hover { background: #f9fafb; }
                .fplms-download-dropdown-item svg { width: 15px; height: 15px; flex-shrink: 0; fill: currentColor; }

                /* ── Responsive ── */
                @media (max-width: 900px) {
                    .fplms-users-table { min-width: 860px; }
                    .fplms-table-topbar { flex-direction: column; align-items: flex-start; }
                    .fplms-search-input { width: 100%; }
                    .fplms-table-topbar-right { width: 100%; }
                }
            </style>
                <div class="fplms-table-card">
                    <!-- TOP BAR -->
                    <div class="fplms-table-topbar">
                        <div class="fplms-table-topbar-left">
                            Mostrar
                            <select id="fplms-per-page" class="fplms-entries-select">
                                <option value="10">10</option>
                                <option value="20" selected>20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            entradas
                        </div>
                        <div class="fplms-table-topbar-right">
                            <div class="fplms-search-wrapper">
                                <input type="text"
                                       id="fplms-search-users"
                                       class="fplms-search-input"
                                       placeholder="Buscar por nombre, email, ID usuario...">
                                <button type="button" class="fplms-search-clear" id="fplms-search-clear" title="Limpiar búsqueda">&#x2715;</button>
                            </div>
                            <div class="fplms-download-wrapper" id="fplms-download-wrapper">
                                <button type="button" class="fplms-download-btn" id="fplms-download-toggle" onclick="fplmsToggleDownloadMenu(event)">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                                    Descargar
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width:12px;height:12px;margin-left:2px;"><path d="M7 10l5 5 5-5z"/></svg>
                                </button>
                                <div class="fplms-download-dropdown" id="fplms-download-dropdown">
                                    <button type="button" class="fplms-download-dropdown-item" onclick="fplmsExportXLS()">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="fill:#217346;"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 7V3.5L18.5 9H13zM7 17l2-3-2-3h1.7l1.3 2 1.3-2H13l-2 3 2 3h-1.7L10 18l-1.3 2H7z"/></svg>
                                        Excel (.xls)
                                    </button>
                                    <button type="button" class="fplms-download-dropdown-item" onclick="fplmsExportPDF()">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="fill:#e53e3e;"><path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5V11H19v2h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zM13 11h1V8.5h-1V11z"/></svg>
                                        PDF
                                    </button>
                                </div>
                            </div>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=fplms-users&action=create' ) ); ?>" class="fplms-download-btn" id="btn-crear-usuario">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                                Crear usuario
                            </a>
                            <a href="#matriz-privilegios" class="fplms-download-btn" id="btn-matriz-privilegios">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                                Matriz de privilegios
                            </a>
                        </div>
                    </div>
            <div class="fplms-users-table-wrapper">
            <table class="fplms-users-table">
                <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="fplms-select-all-users" class="checkbox-input"></th>
                        <th class="sortable">Usuario</th>
                        <th class="sortable">IDUsuario</th>
                        <th class="sortable">Fecha de Registro</th>
                        <th class="sortable">Último Inicio de Sesión</th>
                        <th class="sortable">Estado</th>
                        <th class="sortable">Rol</th>
                        <th style="text-align:center;width:110px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $users ) ) : ?>
                        <?php foreach ( $users as $user ) : ?>
                            <?php
                            $user_registered = $user->user_registered;
                            $last_login = get_user_meta( $user->ID, 'last_login', true );
                            if ( ! $last_login ) {
                                $last_login = 'Nunca';
                            } else {
                                $last_login = date( 'd/m/Y H:i', strtotime( $last_login ) );
                            }
                            $id_usuario = get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_ID_USUARIO, true );
                            $full_name = $user->first_name . ' ' . $user->last_name;
                            $search_string = strtolower( $full_name . ' ' . $user->user_email . ' ' . $id_usuario );
                            
                            // Obtener estado del usuario
                            $user_status = get_user_meta( $user->ID, 'fplms_user_status', true );
                            $is_active = empty( $user_status ) || $user_status === 'active';
                            
                            // Obtener rol del usuario
                            $user_roles = $user->roles;
                            $role_name = '';
                            if ( ! empty( $user_roles ) ) {
                                $role = $user_roles[0];
                                $wp_roles = wp_roles();
                                $role_name = isset( $wp_roles->role_names[ $role ] ) ? translate_user_role( $wp_roles->role_names[ $role ] ) : ucfirst( $role );
                                $role_display_map = [
                                    'subscriber'                            => 'Estudiante',
                                    FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR => 'Tutor',
                                    'administrator'                         => 'Administrador',
                                ];
                                $role_name = isset( $role_display_map[ $role ] ) ? $role_display_map[ $role ] : $role_name;
                            }
                            ?>
                            <tr class="fplms-user-row" 
                                data-user-id="<?php echo esc_attr( $user->ID ); ?>"
                                data-search="<?php echo esc_attr( $search_string ); ?>">
                                <td>
                                    <input type="checkbox" 
                                           class="fplms-user-checkbox checkbox-input" 
                                           value="<?php echo esc_attr( $user->ID ); ?>">
                                </td>
                                <td>
                                    <div class="fplms-user-cell-name"><?php echo esc_html( $full_name ); ?></div>
                                    <div class="fplms-user-cell-email"><?php echo esc_html( $user->user_email ); ?></div>
                                </td>
                                <td>
                                    <code style="background:#f0f0f0;padding:2px 6px;border-radius:3px;font-size:12px;">
                                        <?php echo esc_html( $id_usuario ?: '—' ); ?>
                                    </code>
                                </td>
                                <td><?php echo esc_html( date( 'd/m/Y', strtotime( $user_registered ) ) ); ?></td>
                                <td><?php echo esc_html( $last_login ); ?></td>
                                <td style="text-align: center;">
                                    <?php if ( $is_active ) : ?>
                                        <span class="fplms-status-badge fplms-status-active">Activo</span>
                                    <?php else : ?>
                                        <span class="fplms-status-badge fplms-status-inactive">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="fplms-role-badge"><?php echo esc_html( $role_name ?: '—' ); ?></span>
                                </td>
                                <td class="actions-cell">
                                    <div class="fplms-direct-actions">
                                        <a href="<?php echo esc_url( add_query_arg( [ 'action' => 'edit', 'user_id' => $user->ID ], admin_url( 'admin.php?page=fplms-users' ) ) ); ?>"
                                           class="fplms-action-icon-btn edit" title="Editar usuario">
                                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                        </a>
                                        <?php if ( $is_active ) : ?>
                                            <button type="button" class="fplms-action-icon-btn deactivate" title="Desactivar usuario"
                                                    onclick="fplmsShowActionModal('deactivate', <?php echo $user->ID; ?>, '<?php echo esc_js( $full_name ); ?>', '<?php echo esc_js( $user->user_email ); ?>')">
                                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8 0-1.85.63-3.55 1.69-4.9L16.9 18.31C15.55 19.37 13.85 20 12 20zm6.31-3.1L7.1 5.69C8.45 4.63 10.15 4 12 4c4.42 0 8 3.58 8 8 0 1.85-.63 3.55-1.69 4.9z"/></svg>
                                            </button>
                                        <?php else : ?>
                                            <button type="button" class="fplms-action-icon-btn activate" title="Activar usuario"
                                                    onclick="fplmsShowActionModal('activate', <?php echo $user->ID; ?>, '<?php echo esc_js( $full_name ); ?>', '<?php echo esc_js( $user->user_email ); ?>')">
                                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="fplms-action-icon-btn delete" title="Eliminar usuario"
                                                onclick="fplmsShowActionModal('delete', <?php echo $user->ID; ?>, '<?php echo esc_js( $full_name ); ?>', '<?php echo esc_js( $user->user_email ); ?>')">
                                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="8" style="text-align:center;padding:28px;color:#9ca3af;">No se encontraron usuarios con los filtros seleccionados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>

            <!-- PAGINACIÓN -->
            <div id="fplms-pagination"></div>
                </div><!-- /.fplms-table-card -->

            <!-- MODALES DE ACCIÓN -->
            <!-- Modal: Activar Usuario -->
            <div id="fplms-activate-modal" class="fplms-action-modal-overlay">
                <div class="fplms-action-modal">
                    <div class="fplms-action-modal-header">
                        <h3>
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="fill: #4CAF50;">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                            Activar Usuario
                        </h3>
                    </div>
                    <div class="fplms-action-modal-body">
                        <p>¿Estás seguro de que deseas <strong>activar</strong> este usuario?</p>
                        <div class="fplms-action-modal-user-info">
                            <strong id="activate-user-name"></strong>
                            <span id="activate-user-email"></span>
                        </div>
                        <p style="color: #4CAF50;">El usuario podrá iniciar sesión y acceder al sistema nuevamente.</p>
                    </div>
                    <div class="fplms-action-modal-footer">
                        <button type="button" class="fplms-modal-btn-cancel" onclick="fplmsCloseActionModal()">Cancelar</button>
                        <button type="button" class="fplms-modal-btn-confirm success" onclick="fplmsConfirmAction()">Activar Usuario</button>
                    </div>
                </div>
            </div>

            <!-- Modal: Desactivar Usuario -->
            <div id="fplms-deactivate-modal" class="fplms-action-modal-overlay">
                <div class="fplms-action-modal">
                    <div class="fplms-action-modal-header">
                        <h3>
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="fill: #FF9800;">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8 0-1.85.63-3.55 1.69-4.9L16.9 18.31C15.55 19.37 13.85 20 12 20zm6.31-3.1L7.1 5.69C8.45 4.63 10.15 4 12 4c4.42 0 8 3.58 8 8 0 1.85-.63 3.55-1.69 4.9z"/>
                            </svg>
                            Desactivar Usuario
                        </h3>
                    </div>
                    <div class="fplms-action-modal-body">
                        <p>¿Estás seguro de que deseas <strong>desactivar</strong> este usuario?</p>
                        <div class="fplms-action-modal-user-info">
                            <strong id="deactivate-user-name"></strong>
                            <span id="deactivate-user-email"></span>
                        </div>
                        <p style="color: #FF9800;">⚠️ El usuario no podrá iniciar sesión hasta que sea reactivado.</p>
                    </div>
                    <div class="fplms-action-modal-footer">
                        <button type="button" class="fplms-modal-btn-cancel" onclick="fplmsCloseActionModal()">Cancelar</button>
                        <button type="button" class="fplms-modal-btn-confirm warning" onclick="fplmsConfirmAction()">Desactivar Usuario</button>
                    </div>
                </div>
            </div>

            <!-- Modal: Eliminar Usuario -->
            <div id="fplms-delete-modal" class="fplms-action-modal-overlay">
                <div class="fplms-action-modal">
                    <div class="fplms-action-modal-header">
                        <h3>
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="fill: #F44336;">
                                <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                            </svg>
                            Eliminar Usuario
                        </h3>
                    </div>
                    <div class="fplms-action-modal-body">
                        <p><strong>⚠️ ADVERTENCIA:</strong> Esta acción es <strong style="color: #F44336;">permanente</strong> y no se puede deshacer.</p>
                        <div class="fplms-action-modal-user-info">
                            <strong id="delete-user-name"></strong>
                            <span id="delete-user-email"></span>
                        </div>
                        <p style="color: #F44336;">Se eliminarán todos los datos del usuario, incluyendo su progreso en cursos y registros asociados.</p>
                        <p><strong>¿Realmente deseas eliminar este usuario?</strong></p>
                    </div>
                    <div class="fplms-action-modal-footer">
                        <button type="button" class="fplms-modal-btn-cancel" onclick="fplmsCloseActionModal()">Cancelar</button>
                        <button type="button" class="fplms-modal-btn-confirm danger" onclick="fplmsConfirmAction()">Eliminar Permanentemente</button>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN MATRIZ DE PRIVILEGIOS (Oculta inicialmente) -->
            <div id="matriz-privilegios" style="margin-top: 24px; display: none;">

                <?php if ( isset( $_GET['updated_caps'] ) ) : ?>
                    <div class="fplms-notice-success" style="display:flex;align-items:center;gap:10px;padding:12px 18px;background:#ECFDF3;border:1px solid #6ee7b7;border-radius:10px;color:#065f46;font-size:14px;margin-bottom:14px;">
                        <svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:#027A48;flex-shrink:0;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                        <span>Matriz de privilegios actualizada correctamente.</span>
                    </div>
                <?php endif; ?>

                <?php if ( $can_edit ) : ?>
                <form method="post" id="fplms-matrix-form">
                    <?php wp_nonce_field( 'fplms_caps_save', 'fplms_caps_nonce' ); ?>
                    <input type="hidden" name="fplms_caps_action" value="save">
                <?php endif; ?>

                <div class="fplms-matrix-card">
                    <div class="fplms-matrix-header">
                        <svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:#667eea;flex-shrink:0;"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                        <h2 class="fplms-matrix-title">Matriz de privilegios</h2>
                    </div>

                    <div class="fplms-matrix-table-wrap">
                        <table class="fplms-matrix-table">
                            <thead>
                                <tr>
                                    <th>Rol</th>
                                    <?php foreach ( $caps_def_labels as $cap_key => $label ) : ?>
                                        <th><?php echo esc_html( $label ); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $roles_def_labels as $role_key => $role_label ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( $role_label ); ?></td>
                                        <?php foreach ( $caps_def_labels as $cap_key => $label ) : ?>
                                            <?php $enabled = isset( $matrix[ $role_key ][ $cap_key ] ) ? (bool) $matrix[ $role_key ][ $cap_key ] : false; ?>
                                            <td>
                                                <?php if ( $can_edit ) : ?>
                                                    <input type="checkbox"
                                                           name="fplms_caps[<?php echo esc_attr( $role_key ); ?>][<?php echo esc_attr( $cap_key ); ?>]"
                                                           value="1"
                                                           <?php checked( $enabled ); ?> />
                                                <?php else : ?>
                                                    <?php echo $enabled ? '<span style="color:#027A48;font-weight:700;">&#x2714;</span>' : '<span style="color:#d1d5db;">&#x2716;</span>'; ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ( $can_edit ) : ?>
                    <div class="fplms-matrix-footer">
                        <button type="button" class="fplms-matrix-save-btn" id="fplms-matrix-save-btn">
                            <svg viewBox="0 0 24 24" style="width:15px;height:15px;fill:currentColor;"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                            Guardar configuración
                        </button>
                        <button type="button" class="fplms-matrix-cancel-btn" id="fplms-matrix-cancel-btn">
                            Cancelar
                        </button>
                        <span class="fplms-matrix-note">Solo administradores pueden modificar esta matriz.</span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ( $can_edit ) : ?>
                </form>
                <?php endif; ?>
            </div>

            <!-- Modal confirmación guardar matriz -->
            <div class="fplms-confirm-overlay" id="fplms-matrix-confirm-overlay">
                <div class="fplms-confirm-modal">
                    <div class="fcm-icon">&#x1F512;</div>
                    <h3>Guardar configuración</h3>
                    <p>Se guardarán los cambios en la matriz de privilegios.<br>Esta acción quedará registrada en la bitácora.</p>
                    <div class="fcm-actions">
                        <button type="button" class="fplms-confirm-confirm-btn" id="fplms-matrix-confirm-btn">Guardar</button>
                        <button type="button" class="fplms-confirm-cancel-btn" id="fplms-matrix-confirm-cancel">Cancelar</button>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN CREAR USUARIO MEJORADA (Oculta inicialmente) -->
            <div id="crear-usuario" style="margin-top: 40px; display: none;">
                <h2>Crear nuevo usuario</h2>

                <?php if ( isset( $_GET['error'] ) ) : ?>
                    <div id="message" class="error notice notice-error is-dismissible">
                        <p>
                            <?php
                            $error_msg = sanitize_text_field( wp_unslash( $_GET['error'] ) );
                            
                            $error_messages = [
                                'incomplete_data'     => 'Datos incompletos. Verifica que llenes todos los campos requeridos.',
                                'invalid_id_usuario'  => 'IDUsuario inválido. Debe ser alfanumérico y tener máximo 20 caracteres.',
                                'id_usuario_exists'   => 'El IDUsuario ya existe. Por favor, utiliza uno diferente.',
                                'user_exists'         => 'Error al crear el usuario. Verifica que el nombre de usuario o correo no existan.',
                            ];
                            
                            echo isset( $error_messages[ $error_msg ] ) ? esc_html( $error_messages[ $error_msg ] ) : 'Error al crear el usuario.';
                            ?>
                        </p>
                    </div>
                <?php endif; ?>


                <style>.fplms-create-user-container { --clr-primary: #667eea; --clr-primary-dark: #764ba2; }</style>

                <div class="fplms-create-user-container fplms-edit-user-container">
                    <form method="post" id="form-crear-usuario" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'fplms_new_user_save', 'fplms_new_user_nonce' ); ?>
                        <input type="hidden" name="fplms_new_user_action" value="create_user">
                        <input type="file" id="fplms_user_photo" name="fplms_user_photo" accept="image/*">

                        <!-- Hero nuevo usuario -->
                        <div class="fplms-profile-hero">
                            <div class="fplms-hero-avatar-wrap" id="fplms-image-upload-area">
                                <div class="fplms-hero-avatar-placeholder" id="fplms-avatar-placeholder">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                                </div>
                                <div class="fplms-hero-avatar-overlay">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 16.5l4-4h-3v-9h-2v9H8l4 4zm9 4.5H3v-4.5H1V21a2 2 0 002 2h18a2 2 0 002-2v-4.5h-2V21z"/></svg>
                                    Subir foto
                                </div>
                            </div>
                            <div class="fplms-hero-info">
                                <h2 class="fplms-hero-name">Nuevo Usuario</h2>
                                <div class="fplms-hero-chips">
                                    <span class="fplms-hero-chip">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg>
                                        Completar datos del formulario
                                    </span>
                                    <span class="fplms-hero-chip" id="fplms-new-status-chip" style="background:rgba(72,199,142,.35);">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z"/></svg>
                                        Activo
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Grid de tarjetas -->
                        <div class="fplms-cards-grid">

                            <!-- Datos personales -->
                            <div class="fplms-card">
                                <div class="fplms-card-header">
                                    <div class="fplms-card-header-icon">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                                    </div>
                                    <div><h3>Datos Personales</h3><p>Nombre y apellido del usuario</p></div>
                                </div>
                                <div class="fplms-card-body">
                                    <div class="fplms-form-row">
                                        <div class="fplms-form-group">
                                            <label for="fplms_first_name">Nombre <span class="required">*</span></label>
                                            <input type="text" id="fplms_first_name" name="fplms_first_name" required>
                                        </div>
                                        <div class="fplms-form-group">
                                            <label for="fplms_last_name">Apellido <span class="required">*</span></label>
                                            <input type="text" id="fplms_last_name" name="fplms_last_name" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Credenciales -->
                            <div class="fplms-card">
                                <div class="fplms-card-header">
                                    <div class="fplms-card-header-icon">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                                    </div>
                                    <div><h3>Credenciales de Acceso</h3><p>Usuario, email y contraseña</p></div>
                                </div>
                                <div class="fplms-card-body">
                                    <div class="fplms-form-group">
                                        <label for="fplms_id_usuario">IDUsuario <span class="required">*</span></label>
                                        <input type="text" id="fplms_id_usuario" name="fplms_id_usuario" maxlength="20" pattern="[a-zA-Z0-9]+" title="Solo letras y números, máximo 20 caracteres" required>
                                        <small>Alfanumérico, máximo 20 caracteres.</small>
                                    </div>
                                    <div class="fplms-form-group">
                                        <label for="fplms_user_login">Nombre de usuario <span class="required">*</span></label>
                                        <input type="text" id="fplms_user_login" name="fplms_user_login" required>
                                    </div>
                                    <div class="fplms-form-group">
                                        <label for="fplms_user_email">Correo electrónico <span class="required">*</span></label>
                                        <input type="email" id="fplms_user_email" name="fplms_user_email" required>
                                    </div>
                                    <div class="fplms-form-group">
                                        <label for="fplms_user_pass">Contraseña <span class="required">*</span></label>
                                        <input type="password" id="fplms_user_pass" name="fplms_user_pass" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Estructura Organizacional -->
                            <div class="fplms-card">
                                <div class="fplms-card-header">
                                    <div class="fplms-card-header-icon">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                                    </div>
                                    <div><h3>Estructura Organizacional</h3><p>Ciudad, empresa, canal y sucursal</p></div>
                                </div>
                                <div class="fplms-card-body">
                                    <div class="fplms-form-row">
                                        <div class="fplms-form-group">
                                            <label for="fplms_city">Ciudad</label>
                                            <select name="fplms_city" id="fplms_city">
                                                <option value="">— Sin asignar —</option>
                                                <?php foreach ( $cities as $id => $name ) : ?>
                                                    <option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $name ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="fplms-form-group">
                                            <label for="fplms_company">Empresa</label>
                                            <select name="fplms_company" id="fplms_company"><option value="">— Sin asignar —</option></select>
                                        </div>
                                    </div>
                                    <div class="fplms-form-row">
                                        <div class="fplms-form-group">
                                            <label for="fplms_channel">Canal / Franquicia</label>
                                            <select name="fplms_channel" id="fplms_channel"><option value="">— Sin asignar —</option></select>
                                        </div>
                                        <div class="fplms-form-group">
                                            <label for="fplms_branch">Sucursal</label>
                                            <select name="fplms_branch" id="fplms_branch"><option value="">— Sin asignar —</option></select>
                                        </div>
                                    </div>
                                    <div class="fplms-form-group">
                                        <label for="fplms_job_role">Cargo</label>
                                        <select name="fplms_job_role" id="fplms_job_role"><option value="">— Sin asignar —</option></select>
                                    </div>
                                </div>
                            </div>

                            <!-- Rol y Estado -->
                            <div class="fplms-card">
                                <div class="fplms-card-header">
                                    <div class="fplms-card-header-icon">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
                                    </div>
                                    <div><h3>Tipo de Usuario y Estado</h3><p>Rol asignado y estado de acceso</p></div>
                                </div>
                                <div class="fplms-card-body">
                                    <div class="fplms-form-group">
                                        <label for="fplms_user_role">Tipo de usuario <span class="required">*</span></label>
                                        <select name="fplms_user_role" id="fplms_user_role" required>
                                            <option value="">— Seleccionar tipo de usuario —</option>
                                            <?php foreach ( $roles_def_labels as $role_key => $role_label ) : ?>
                                                <option value="<?php echo esc_attr( $role_key ); ?>"><?php echo esc_html( $role_label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="fplms-status-row">
                                        <div class="fplms-status-label" id="fplms-new-status-label">
                                            <svg viewBox="0 0 24 24" width="18" height="18" style="fill:#48c78e;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z"/></svg>
                                            Usuario Activo
                                        </div>
                                        <label style="position:relative;display:inline-block;width:52px;height:28px;cursor:pointer;">
                                            <input type="checkbox" id="fplms_user_active" name="fplms_user_active" value="1" checked style="opacity:0;width:0;height:0;">
                                            <span id="fplms-new-toggle-track" style="position:absolute;inset:0;border-radius:28px;background:#48c78e;transition:background .3s;"></span>
                                            <span id="fplms-new-toggle-thumb" style="position:absolute;top:3px;left:27px;width:22px;height:22px;border-radius:50%;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.25);transition:left .3s;"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                        </div><!-- .fplms-cards-grid -->

                        <!-- Botones de acción -->
                        <div class="fplms-form-actions">
                            <button type="button" class="button-secondary" onclick="document.getElementById('crear-usuario').style.display='none'; window.scrollTo(0,0);" style="padding:11px 28px;border-radius:9px;font-size:14px;font-weight:600;cursor:pointer;background:#f8f9fa;color:#495057;border:2px solid #e9ecef;text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="#495057"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                                Cancelar
                            </button>
                            <button type="submit" class="button-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="#fff"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg>
                                Crear Usuario
                            </button>
                        </div>

                    </form>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // BOTONES PARA MOSTRAR/OCULTAR SECCIONES
                    const btnMatrizPrivilegios = document.getElementById('btn-matriz-privilegios');
                    const secMatrizPrivilegios = document.getElementById('matriz-privilegios');

                    if ( btnMatrizPrivilegios ) {
                        btnMatrizPrivilegios.addEventListener('click', function(e) {
                            e.preventDefault();
                            secMatrizPrivilegios.style.display = secMatrizPrivilegios.style.display === 'none' ? 'block' : 'none';
                            if ( secMatrizPrivilegios.style.display !== 'none' ) {
                                secMatrizPrivilegios.scrollIntoView({ behavior: 'smooth' });
                            }
                        });
                    }

                    // Matriz de privilegios: confirmar antes de guardar
                    const matrixSaveBtn   = document.getElementById('fplms-matrix-save-btn');
                    const matrixCancelBtn = document.getElementById('fplms-matrix-cancel-btn');
                    const matrixOverlay   = document.getElementById('fplms-matrix-confirm-overlay');
                    const matrixConfirmOk = document.getElementById('fplms-matrix-confirm-btn');
                    const matrixConfirmNo = document.getElementById('fplms-matrix-confirm-cancel');

                    if (matrixSaveBtn) {
                        matrixSaveBtn.addEventListener('click', function() {
                            matrixOverlay.classList.add('open');
                        });
                    }
                    if (matrixConfirmOk) {
                        matrixConfirmOk.addEventListener('click', function() {
                            matrixOverlay.classList.remove('open');
                            document.getElementById('fplms-matrix-form').submit();
                        });
                    }
                    if (matrixConfirmNo) {
                        matrixConfirmNo.addEventListener('click', function() {
                            matrixOverlay.classList.remove('open');
                        });
                    }
                    if (matrixCancelBtn) {
                        matrixCancelBtn.addEventListener('click', function() {
                            secMatrizPrivilegios.style.display = 'none';
                        });
                    }
                    if (matrixOverlay) {
                        matrixOverlay.addEventListener('click', function(e) {
                            if (e.target === matrixOverlay) matrixOverlay.classList.remove('open');
                        });
                    }

                    // CASCADA DE SELECTS: CIUDAD -> EMPRESA -> CANAL -> SUCURSAL -> CARGO
                    const citySelect = document.getElementById('fplms_city');
                    const companySelect = document.getElementById('fplms_company');
                    const channelSelect = document.getElementById('fplms_channel');
                    const branchSelect = document.getElementById('fplms_branch');
                    const jobRoleSelect = document.getElementById('fplms_job_role');

                    // Función para actualizar un select basado en otro
                    function updateSelectOptions(parentSelect, childSelect, taxonomy, parentKey = 'city_id') {
                        if (!parentSelect || !childSelect) return;

                        parentSelect.addEventListener('change', function() {
                            const parentValue = this.value;
                            
                            if (!parentValue) {
                                // Si no hay selección, resetear a opciones iniciales
                                childSelect.innerHTML = '<option value="">— Sin asignar —</option>';
                                // Resetear selects descendientes
                                resetDescendantSelects(childSelect);
                                return;
                            }

                            // Mostrar indicador de carga
                            childSelect.innerHTML = '<option value="">Cargando...</option>';

                            // Hacer petición AJAX
                            const formData = new FormData();
                            formData.append('action', 'fplms_get_terms_by_parent');
                            formData.append(parentKey, parentValue);
                            formData.append('taxonomy', taxonomy);

                            fetch(ajaxurl, {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success && data.data) {
                                    let html = '<option value="">— Sin asignar —</option>';
                                    for (const [termId, termName] of Object.entries(data.data)) {
                                        html += '<option value="' + termId + '">' + termName + '</option>';
                                    }
                                    childSelect.innerHTML = html;
                                } else {
                                    childSelect.innerHTML = '<option value="">— Sin asignar —</option>';
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                childSelect.innerHTML = '<option value="">— Sin asignar —</option>';
                            });
                        });
                    }

                    // Función para resetear selects descendientes
                    function resetDescendantSelects(fromSelect) {
                        const selectElements = [companySelect, channelSelect, branchSelect, jobRoleSelect];
                        let shouldReset = false;
                        
                        for (const select of selectElements) {
                            if (shouldReset && select) {
                                select.innerHTML = '<option value="">— Sin asignar —</option>';
                            }
                            if (select === fromSelect) {
                                shouldReset = true;
                            }
                        }
                    }

                    // Configurar cascadas
                    if (citySelect && companySelect) {
                        updateSelectOptions(citySelect, companySelect, '<?php echo FairPlay_LMS_Config::TAX_COMPANY; ?>', 'city_id');
                    }
                    if (companySelect && channelSelect) {
                        updateSelectOptions(companySelect, channelSelect, '<?php echo FairPlay_LMS_Config::TAX_CHANNEL; ?>', 'company_id');
                    }
                    if (channelSelect && branchSelect) {
                        updateSelectOptions(channelSelect, branchSelect, '<?php echo FairPlay_LMS_Config::TAX_BRANCH; ?>', 'channel_id');
                    }
                    if (branchSelect && jobRoleSelect) {
                        updateSelectOptions(branchSelect, jobRoleSelect, '<?php echo FairPlay_LMS_Config::TAX_ROLE; ?>', 'branch_id');
                    }

                    // MANEJO DE FOTOGRAFÍA - Crear Usuario
                    const uploadArea = document.getElementById('fplms-image-upload-area');
                    const fileInput = document.getElementById('fplms_user_photo');

                    if ( uploadArea && fileInput ) {
                        uploadArea.addEventListener('click', function() {
                            fileInput.click();
                        });

                        fileInput.addEventListener('change', function(e) {
                            const file = e.target.files[0];
                            if (file) {
                                if (!file.type.startsWith('image/')) {
                                    alert('Por favor selecciona un archivo de imagen válido.');
                                    this.value = '';
                                    return;
                                }
                                if (file.size > 5 * 1024 * 1024) {
                                    alert('La imagen es demasiado grande. El tamaño máximo es 5MB.');
                                    this.value = '';
                                    return;
                                }
                                const reader = new FileReader();
                                reader.onload = function(event) {
                                    let existing = uploadArea.querySelector('.fplms-hero-avatar');
                                    let placeholder = uploadArea.querySelector('.fplms-hero-avatar-placeholder');
                                    if (existing) {
                                        existing.src = event.target.result;
                                    } else {
                                        if (placeholder) placeholder.remove();
                                        const img = document.createElement('img');
                                        img.src = event.target.result;
                                        img.className = 'fplms-hero-avatar';
                                        img.alt = 'Vista previa';
                                        uploadArea.insertBefore(img, uploadArea.querySelector('.fplms-hero-avatar-overlay'));
                                    }
                                };
                                reader.readAsDataURL(file);
                            }
                        });
                    }

                    // Toggle estado - Crear Usuario
                    const newStatusCb = document.getElementById('fplms_user_active');
                    if (newStatusCb) {
                        const newTrack = document.getElementById('fplms-new-toggle-track');
                        const newThumb = document.getElementById('fplms-new-toggle-thumb');
                        const newLabel = document.getElementById('fplms-new-status-label');
                        const newChip  = document.getElementById('fplms-new-status-chip');
                        newStatusCb.addEventListener('change', function() {
                            if (this.checked) {
                                if (newTrack) newTrack.style.background = '#48c78e';
                                if (newThumb) newThumb.style.left = '27px';
                                if (newLabel) newLabel.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" style="fill:#48c78e;flex-shrink:0;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z"/></svg> Usuario Activo';
                                if (newChip)  { newChip.style.background = 'rgba(72,199,142,.35)'; newChip.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" style="fill:#fff;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z"/></svg> Activo'; }
                            } else {
                                if (newTrack) newTrack.style.background = '#ccc';
                                if (newThumb) newThumb.style.left = '3px';
                                if (newLabel) newLabel.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" style="fill:#fc814a;flex-shrink:0;"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/></svg> Usuario Inactivo';
                                if (newChip)  { newChip.style.background = 'rgba(252,129,74,.35)'; newChip.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" style="fill:#fff;"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/></svg> Inactivo'; }
                            }
                        });
                    }

                    // ====================================================
                    // NUEVA FUNCIONALIDAD: BÚSQUEDA, PAGINACIÓN Y ACCIONES MASIVAS
                    // ====================================================

                    let allRows = [];
                    let filteredRows = [];
                    let currentPage = 1;
                    let perPage = 20;
                    let selectedUsers = new Set();

                    // Inicializar funcionalidades
                    function initUserTable() {
                        allRows = Array.from(document.querySelectorAll('.fplms-user-row'));
                        filteredRows = [...allRows];
                        setupSearch();
                        setupPerPage();
                        setupCheckboxes();
                        setupBulkActions();
                        renderPagination();
                    }

                    // 1. BÚSQUEDA
                    function setupSearch() {
                        const searchInput = document.getElementById('fplms-search-users');
                        if (!searchInput) return;

                        const clearBtn = document.getElementById('fplms-search-clear');

                        searchInput.addEventListener('input', function(e) {
                            const query = e.target.value.toLowerCase().trim();
                            if (clearBtn) clearBtn.style.display = this.value ? 'block' : 'none';

                            if (query === '') {
                                filteredRows = [...allRows];
                            } else {
                                filteredRows = allRows.filter(row => {
                                    const searchData = row.getAttribute('data-search') || '';
                                    return searchData.includes(query);
                                });
                            }

                            currentPage = 1;
                            renderPagination();
                        });

                        if (clearBtn) {
                            clearBtn.addEventListener('click', function() {
                                searchInput.value = '';
                                this.style.display = 'none';
                                filteredRows = [...allRows];
                                currentPage = 1;
                                renderPagination();
                                searchInput.focus();
                            });
                        }
                    }

                    // 2. SELECTOR PER_PAGE
                    function setupPerPage() {
                        const perPageSelect = document.getElementById('fplms-per-page');
                        if (!perPageSelect) return;

                        perPageSelect.addEventListener('change', function(e) {
                            perPage = parseInt(e.target.value);
                            currentPage = 1;
                            renderPagination();
                        });
                    }

                    // 3. CHECKBOXES Y SELECCIÓN
                    function setupCheckboxes() {
                        const selectAllCheckbox = document.getElementById('fplms-select-all-users');
                        
                        if (selectAllCheckbox) {
                            selectAllCheckbox.addEventListener('change', function(e) {
                                const checkboxes = document.querySelectorAll('.fplms-user-checkbox');
                                checkboxes.forEach(cb => {
                                    cb.checked = e.target.checked;
                                    if (e.target.checked) {
                                        selectedUsers.add(cb.value);
                                    } else {
                                        selectedUsers.delete(cb.value);
                                    }
                                });
                                updateBulkActionsBar();
                            });
                        }

                        document.addEventListener('change', function(e) {
                            if (e.target.classList.contains('fplms-user-checkbox')) {
                                if (e.target.checked) {
                                    selectedUsers.add(e.target.value);
                                } else {
                                    selectedUsers.delete(e.target.value);
                                }
                                updateBulkActionsBar();
                            }
                        });
                    }

                    // 4. ACTUALIZAR BARRA DE ACCIONES MASIVAS
                    function updateBulkActionsBar() {
                        const bulkActionsContainer = document.getElementById('fplms-bulk-actions');
                        const bulkCountSpan = document.getElementById('fplms-bulk-count');
                        const selectAllCheckbox = document.getElementById('fplms-select-all-users');
                        
                        if (!bulkActionsContainer || !bulkCountSpan) return;

                        const count = selectedUsers.size;
                        bulkCountSpan.textContent = count;

                        if (count > 0) {
                            bulkActionsContainer.style.display = 'flex';
                        } else {
                            bulkActionsContainer.style.display = 'none';
                        }

                        // Actualizar estado del checkbox "seleccionar todos"
                        if (selectAllCheckbox) {
                            const totalCheckboxes = document.querySelectorAll('.fplms-user-checkbox').length;
                            const checkedCheckboxes = document.querySelectorAll('.fplms-user-checkbox:checked').length;
                            selectAllCheckbox.checked = (checkedCheckboxes === totalCheckboxes && totalCheckboxes > 0);
                            selectAllCheckbox.indeterminate = (checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes);
                        }
                    }

                    // 5. ACCIONES MASIVAS
                    function setupBulkActions() {
                        const applyBtn = document.getElementById('fplms-bulk-apply-btn');
                        const actionSelect = document.getElementById('fplms-bulk-action');

                        if (!applyBtn || !actionSelect) return;

                        applyBtn.addEventListener('click', function() {
                            const action = actionSelect.value;
                            
                            if (!action) {
                                alert('Por favor, selecciona una acción.');
                                return;
                            }

                            if (selectedUsers.size === 0) {
                                alert('No hay usuarios seleccionados.');
                                return;
                            }

                            const userIds = Array.from(selectedUsers);
                            
                            // Mostrar modal de confirmación
                            showBulkModal(action, userIds);
                        });
                    }

                    // 6. MODAL DE CONFIRMACIÓN
                    function showBulkModal(action, userIds) {
                        const actionLabels = {
                            'activate': '✅ Activar',
                            'deactivate': '⛔ Desactivar',
                            'delete': '🗑️ Eliminar'
                        };

                        const actionMessages = {
                            'activate': '¿Estás seguro de que deseas <strong>activar</strong> los usuarios seleccionados?',
                            'deactivate': '¿Estás seguro de que deseas <strong>desactivar</strong> los usuarios seleccionados?',
                            'delete': '⚠️ <strong>ATENCIÓN:</strong> ¿Estás seguro de que deseas <strong>eliminar permanentemente</strong> los usuarios seleccionados?<br><br>Esta acción no se puede deshacer.'
                        };

                        const modal = document.createElement('div');
                        modal.className = 'fplms-modal';
                        modal.innerHTML = `
                            <div class="fplms-modal-content">
                                <div class="fplms-modal-header">
                                    <h3>${actionLabels[action]} Usuarios</h3>
                                </div>
                                <div class="fplms-modal-body">
                                    <p>${actionMessages[action]}</p>
                                    <p><strong>Usuarios seleccionados:</strong> ${userIds.length}</p>
                                </div>
                                <div class="fplms-modal-footer">
                                    <button type="button" class="button" onclick="this.closest('.fplms-modal').remove()">Cancelar</button>
                                    <button type="button" class="button button-primary" id="fplms-confirm-bulk-action">Confirmar</button>
                                </div>
                            </div>
                        `;

                        document.body.appendChild(modal);

                        // Configurar botón de confirmación
                        const confirmBtn = modal.querySelector('#fplms-confirm-bulk-action');
                        confirmBtn.addEventListener('click', function() {
                            executeBulkAction(action, userIds);
                            modal.remove();
                        });

                        // Cerrar modal al hacer clic fuera
                        modal.addEventListener('click', function(e) {
                            if (e.target === modal) {
                                modal.remove();
                            }
                        });
                    }

                    // 7. EJECUTAR ACCIÓN MASIVA
                    function executeBulkAction(action, userIds) {
                        // Crear formulario y enviarlo
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = window.location.href;

                        // Agregar nonce
                        const nonceInput = document.createElement('input');
                        nonceInput.type = 'hidden';
                        nonceInput.name = 'fplms_bulk_users_nonce';
                        nonceInput.value = '<?php echo wp_create_nonce( 'fplms_bulk_users' ); ?>';
                        form.appendChild(nonceInput);

                        // Agregar acción
                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'fplms_bulk_action';
                        actionInput.value = action;
                        form.appendChild(actionInput);

                        // Agregar IDs de usuarios
                        userIds.forEach(userId => {
                            const userInput = document.createElement('input');
                            userInput.type = 'hidden';
                            userInput.name = 'fplms_bulk_users[]';
                            userInput.value = userId;
                            form.appendChild(userInput);
                        });

                        document.body.appendChild(form);
                        form.submit();
                    }

                    // 8. PAGINACIÓN
                    function renderPagination() {
                        const paginationContainer = document.getElementById('fplms-pagination');
                        if (!paginationContainer) return;

                        const totalItems = filteredRows.length;
                        const totalPages = Math.ceil(totalItems / perPage);

                        // Ocultar todas las filas primero
                        allRows.forEach(row => row.style.display = 'none');

                        // Mostrar solo las filas de la página actual
                        const start = (currentPage - 1) * perPage;
                        const end = start + perPage;
                        const pageRows = filteredRows.slice(start, end);
                        pageRows.forEach(row => row.style.display = '');

                        if (totalItems === 0) {
                            paginationContainer.innerHTML = '<div class="fplms-pagination-wrapper"><span class="fplms-showing-info">No se encontraron usuarios.</span></div>';
                            return;
                        }

                        // Info de resultados
                        const showingFrom = start + 1;
                        const showingTo   = Math.min(end, totalItems);
                        const infoHtml = `<span class="fplms-showing-info">Mostrando ${showingFrom} a ${showingTo} de ${totalItems} entradas</span>`;

                        // Controles de paginación
                        let btns = '';
                        btns += `<button class="fplms-pagination-btn" ${currentPage === 1 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})">← Anterior</button>`;

                        const maxButtons = 5;
                        let startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
                        let endPage   = Math.min(totalPages, startPage + maxButtons - 1);
                        if (endPage - startPage < maxButtons - 1) startPage = Math.max(1, endPage - maxButtons + 1);

                        if (startPage > 1) {
                            btns += `<button class="fplms-pagination-btn" onclick="changePage(1)">1</button>`;
                            if (startPage > 2) btns += `<span style="padding:0 4px;color:#9ca3af;">…</span>`;
                        }
                        for (let i = startPage; i <= endPage; i++) {
                            btns += `<button class="fplms-pagination-btn ${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`;
                        }
                        if (endPage < totalPages) {
                            if (endPage < totalPages - 1) btns += `<span style="padding:0 4px;color:#9ca3af;">…</span>`;
                            btns += `<button class="fplms-pagination-btn" onclick="changePage(${totalPages})">${totalPages}</button>`;
                        }
                        btns += `<button class="fplms-pagination-btn" ${currentPage === totalPages ? 'disabled' : ''} onclick="changePage(${currentPage + 1})">Siguiente →</button>`;

                        paginationContainer.innerHTML = `<div class="fplms-pagination-wrapper">${infoHtml}<div class="fplms-pagination-controls">${btns}</div></div>`;
                    }

                    // Función global para cambiar página
                    window.changePage = function(page) {
                        const totalPages = Math.ceil(filteredRows.length / perPage);
                        if (page < 1 || page > totalPages) return;
                        currentPage = page;
                        renderPagination();
                        
                        // Scroll suave a la tabla
                        document.querySelector('.fplms-users-table-wrapper').scrollIntoView({ 
                            behavior: 'smooth',
                            block: 'start'
                        });
                    };

                    // INICIALIZAR TABLA AL CARGAR
                    if (document.querySelector('.fplms-users-table')) {
                        initUserTable();
                    }
                });

                // ── Toggle menú descarga ──
                window.fplmsToggleDownloadMenu = function(e) {
                    e.stopPropagation();
                    document.getElementById('fplms-download-dropdown').classList.toggle('open');
                };

                // ── Helper: filas a exportar (seleccionadas o todas las filtradas) ──
                function getExportRows() {
                    const checked = document.querySelectorAll('.fplms-user-checkbox:checked');
                    if (checked.length > 0) {
                        return Array.from(checked).map(function(cb) { return cb.closest('tr.fplms-user-row'); }).filter(Boolean);
                    }
                    return filteredRows;
                }

                function extractRowData(row) {
                    const cells = row.querySelectorAll('td');
                    if (cells.length < 7) return null;
                    return {
                        name:   (cells[1].querySelector('.fplms-user-cell-name')  || {textContent:''}).textContent.trim(),
                        email:  (cells[1].querySelector('.fplms-user-cell-email') || {textContent:''}).textContent.trim(),
                        id:     cells[2].textContent.trim(),
                        fecha:  cells[3].textContent.trim(),
                        login:  cells[4].textContent.trim(),
                        status: cells[5].textContent.trim(),
                        rol:    cells[6].textContent.trim()
                    };
                }

                // ── Exportar Excel (.xls) ──
                window.fplmsExportXLS = function() {
                    const rows = getExportRows();
                    const headers = ['Usuario', 'Email', 'IDUsuario', 'Fecha de Registro', 'Último Login', 'Estado', 'Rol'];
                    let t = '<table border="1" style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:12px;">';
                    t += '<thead><tr>' + headers.map(function(h) {
                        return '<th style="background:#217346;color:#fff;padding:8px 12px;font-weight:bold;white-space:nowrap;">' + h + '</th>';
                    }).join('') + '</tr></thead><tbody>';
                    rows.forEach(function(row) {
                        const d = extractRowData(row);
                        if (!d) return;
                        const vals = [d.name, d.email, d.id, d.fecha, d.login, d.status, d.rol];
                        t += '<tr>' + vals.map(function(v) {
                            return '<td style="padding:6px 10px;">' + v.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</td>';
                        }).join('') + '</tr>';
                    });
                    t += '</tbody></table>';
                    const html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' + t + '</body></html>';
                    const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
                    const url  = URL.createObjectURL(blob);
                    const a    = document.createElement('a');
                    a.href = url; a.download = 'usuarios.xls'; a.click();
                    URL.revokeObjectURL(url);
                    document.getElementById('fplms-download-dropdown').classList.remove('open');
                };

                // ── Exportar PDF (ventana de impresión) ──
                window.fplmsExportPDF = function() {
                    const rows = getExportRows();
                    const headers = ['Usuario', 'Email', 'IDUsuario', 'Fecha de Registro', 'Último Login', 'Estado', 'Rol'];
                    const selectedCount = document.querySelectorAll('.fplms-user-checkbox:checked').length;
                    const label = selectedCount > 0 ? selectedCount + ' seleccionados' : rows.length + ' usuarios';
                    let t = '<table><thead><tr>' + headers.map(function(h) { return '<th>' + h + '</th>'; }).join('') + '</tr></thead><tbody>';
                    rows.forEach(function(row) {
                        const d = extractRowData(row);
                        if (!d) return;
                        const vals = [d.name, d.email, d.id, d.fecha, d.login, d.status, d.rol];
                        t += '<tr>' + vals.map(function(v) {
                            return '<td>' + v.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</td>';
                        }).join('') + '</tr>';
                    });
                    t += '</tbody></table>';
                    const win = window.open('', '_blank', 'width=960,height=700');
                    win.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Usuarios (' + label + ')</title><style>'
                        + 'body{font-family:Arial,sans-serif;font-size:12px;margin:24px;color:#1f2937;}'
                        + 'h2{font-size:16px;margin-bottom:14px;color:#111827;}'
                        + 'p.meta{font-size:11px;color:#6b7280;margin-bottom:16px;}'
                        + 'table{width:100%;border-collapse:collapse;}'
                        + 'th{background:#667eea;color:#fff;padding:8px 10px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.5px;}'
                        + 'td{padding:7px 10px;border-bottom:1px solid #e5e7eb;}'
                        + 'tr:nth-child(even) td{background:#f9fafb;}'
                        + '.btn{margin-top:18px;padding:9px 22px;background:#667eea;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;}'
                        + '@media print{.btn{display:none;} body{margin:10px;}}'
                        + '</style></head><body>'
                        + '<h2>Usuarios</h2><p class="meta">' + label + ' &mdash; ' + new Date().toLocaleDateString('es-ES') + '</p>'
                        + t
                        + '<br><button class="btn" onclick="window.print()">&#128438; Imprimir / Guardar como PDF</button>'
                        + '</body></html>');
                    win.document.close();
                    document.getElementById('fplms-download-dropdown').classList.remove('open');
                };
                
                // FUNCIÓN GLOBAL PARA TOGGLE DE DROPDOWN DE ACCIONES
                window.fplmsToggleActionsMenu = function(userId, event) {
                    event.stopPropagation();
                    
                    // Cerrar todos los dropdowns abiertos
                    document.querySelectorAll('.fplms-actions-dropdown-menu').forEach(function(menu) {
                        if (menu.id !== 'fplms-actions-menu-' + userId) {
                            menu.style.display = 'none';
                        }
                    });
                    
                    // Toggle del dropdown actual
                    const menu = document.getElementById('fplms-actions-menu-' + userId);
                    if (menu) {
                        menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
                    }
                };
                
                // Cerrar dropdowns al hacer clic fuera
                document.addEventListener('click', function(event) {
                    if (!event.target.closest('.fplms-actions-dropdown')) {
                        document.querySelectorAll('.fplms-actions-dropdown-menu').forEach(function(menu) {
                            menu.style.display = 'none';
                        });
                    }
                    if (!event.target.closest('#fplms-download-wrapper')) {
                        const dd = document.getElementById('fplms-download-dropdown');
                        if (dd) dd.classList.remove('open');
                    }
                });
                
                // FUNCIONES GLOBALES PARA MODALES DE ACCIÓN
                let currentActionData = {
                    action: '',
                    userId: null,
                    userName: '',
                    userEmail: ''
                };
                
                // Mostrar modal de acción
                function fplmsShowActionModal(action, userId, userName, userEmail) {
                    // Guardar datos de la acción
                    currentActionData = {
                        action: action,
                        userId: userId,
                        userName: userName,
                        userEmail: userEmail
                    };
                    
                    // Determinar qué modal mostrar
                    let modalId = '';
                    switch(action) {
                        case 'activate':
                            modalId = 'fplms-activate-modal';
                            document.getElementById('activate-user-name').textContent = userName;
                            document.getElementById('activate-user-email').textContent = userEmail;
                            break;
                        case 'deactivate':
                            modalId = 'fplms-deactivate-modal';
                            document.getElementById('deactivate-user-name').textContent = userName;
                            document.getElementById('deactivate-user-email').textContent = userEmail;
                            break;
                        case 'delete':
                            modalId = 'fplms-delete-modal';
                            document.getElementById('delete-user-name').textContent = userName;
                            document.getElementById('delete-user-email').textContent = userEmail;
                            break;
                    }
                    
                    // Mostrar modal
                    const modal = document.getElementById(modalId);
                    if (modal) {
                        modal.classList.add('active');
                        document.body.style.overflow = 'hidden';
                    }
                }
                
                // Cerrar modal
                function fplmsCloseActionModal() {
                    const modals = document.querySelectorAll('.fplms-action-modal-overlay');
                    modals.forEach(modal => {
                        modal.classList.remove('active');
                    });
                    document.body.style.overflow = '';
                    
                    // Limpiar datos
                    currentActionData = {
                        action: '',
                        userId: null,
                        userName: '',
                        userEmail: ''
                    };
                }
                
                // Confirmar acción
                function fplmsConfirmAction() {
                    if (!currentActionData.userId || !currentActionData.action) {
                        return;
                    }
                    
                    // Crear formulario y enviarlo
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = window.location.href;
                    
                    // Agregar campos según la acción
                    const fields = {
                        'fplms_bulk_action': currentActionData.action,
                        'fplms_bulk_users[]': currentActionData.userId,
                        'fplms_bulk_users_nonce': '<?php echo wp_create_nonce( 'fplms_bulk_users_action' ); ?>'
                    };
                    
                    for (const [name, value] of Object.entries(fields)) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = name;
                        input.value = value;
                        form.appendChild(input);
                    }
                    
                    document.body.appendChild(form);
                    form.submit();
                }
                
                // Cerrar modal al hacer clic fuera
                document.addEventListener('click', function(e) {
                    if (e.target.classList.contains('fplms-action-modal-overlay')) {
                        fplmsCloseActionModal();
                    }
                });
                
                // Cerrar modal con tecla ESC
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        fplmsCloseActionModal();
                    }
                });
            </script>
            </div>
        </div>
        <?php
    }

    /**
     * Usuarios filtrados por estructura.
     * Aplica filtros por estructura (ciudad, canal, sucursal, cargo).
     * Si ningún filtro está activo, retorna todos los usuarios.
     */
    public function get_users_filtered_by_structure(
        int $city_id,
        int $company_id,
        int $channel_id,
        int $branch_id,
        int $role_id
    ): array {

        $args = [
            'number'  => -1,
            'orderby' => 'display_name',
            'order'   => 'ASC',
        ];

        // Construir meta_query solo si hay filtros activos
        $meta_query_clauses = [];

        if ( $city_id ) {
            $meta_query_clauses[] = [
                'key'     => FairPlay_LMS_Config::USER_META_CITY,
                'value'   => (string) $city_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ];
        }

        if ( $company_id ) {
            $meta_query_clauses[] = [
                'key'     => FairPlay_LMS_Config::USER_META_COMPANY,
                'value'   => (string) $company_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ];
        }

        if ( $channel_id ) {
            $meta_query_clauses[] = [
                'key'     => FairPlay_LMS_Config::USER_META_CHANNEL,
                'value'   => (string) $channel_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ];
        }

        if ( $branch_id ) {
            $meta_query_clauses[] = [
                'key'     => FairPlay_LMS_Config::USER_META_BRANCH,
                'value'   => (string) $branch_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ];
        }

        if ( $role_id ) {
            $meta_query_clauses[] = [
                'key'     => FairPlay_LMS_Config::USER_META_ROLE,
                'value'   => (string) $role_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ];
        }

        // Solo aplicar meta_query si hay filtros activos
        if ( ! empty( $meta_query_clauses ) ) {
            $args['meta_query'] = [
                'relation' => 'AND',
                ...$meta_query_clauses,
            ];
        }

        $query = new WP_User_Query( $args );
        return (array) $query->get_results();
    }

    /**
     * Renderizar formulario de edición de usuario
     */
    private function render_edit_user_form( int $user_id ): void {
        $user = get_userdata( $user_id );
        
        if ( ! $user ) {
            echo '<div class="wrap"><h1>Usuario no encontrado</h1><p><a href="' . esc_url( admin_url( 'admin.php?page=fplms-users' ) ) . '" class="button">← Volver a usuarios</a></p></div>';
            return;
        }

        // Obtener datos del usuario
        $first_name = get_user_meta( $user_id, 'first_name', true );
        $last_name  = get_user_meta( $user_id, 'last_name', true );
        $id_usuario = get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_ID_USUARIO, true );
        $city_id    = get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_CITY, true );
        $company_id = get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_COMPANY, true );
        $channel_id = get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_CHANNEL, true );
        $branch_id  = get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_BRANCH, true );
        $role_id    = get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_ROLE, true );
        
        // Obtener rol actual del usuario
        $user_roles = $user->roles;
        $user_role = ! empty( $user_roles ) ? $user_roles[0] : '';
        
        // Obtener estado del usuario
        $user_status = get_user_meta( $user_id, 'fplms_user_status', true );
        $is_user_active = empty( $user_status ) || $user_status === 'active';
        
        // Obtener estructuras
        $cities    = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CITY );
        $companies = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_COMPANY );
        $channels  = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CHANNEL );
        $branches  = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_BRANCH );
        $job_roles = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_ROLE );
        
        // Roles disponibles
        $roles_def_labels = [
            'subscriber'                                  => 'Estudiante',
            FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR       => 'Docente',
            'administrator'                               => 'Administrador',
        ];
        
        // Obtener foto actual - Sistema multi-fuente inteligente
        $user_photo_url = '';
        
        // Fuente 1: URL directa guardada por FairPlay LMS
        $user_photo_url = get_user_meta( $user_id, 'fplms_user_photo_url', true );
        
        // Fuente 2: Attachment ID guardado por FairPlay LMS
        if ( empty( $user_photo_url ) ) {
            $photo_id = get_user_meta( $user_id, 'fplms_user_photo_id', true );
            if ( $photo_id ) {
                $user_photo_url = wp_get_attachment_url( $photo_id );
            }
        }
        
        // Fuente 3: Avatar de MasterStudy LMS (URL directa)
        if ( empty( $user_photo_url ) ) {
            $stm_avatar_url = get_user_meta( $user_id, 'stm_lms_user_avatar', true );
            if ( $stm_avatar_url && filter_var( $stm_avatar_url, FILTER_VALIDATE_URL ) ) {
                $user_photo_url = $stm_avatar_url;
                // Sincronizar para futuras lecturas
                update_user_meta( $user_id, 'fplms_user_photo_url', $stm_avatar_url );
            }
        }
        
        // Fuente 4: Attachment ID de MasterStudy LMS (fallback)
        if ( empty( $user_photo_url ) ) {
            $stm_photo_id = get_user_meta( $user_id, 'stm_lms_user_photo', true );
            if ( $stm_photo_id && is_numeric( $stm_photo_id ) ) {
                $attachment_url = wp_get_attachment_url( $stm_photo_id );
                if ( $attachment_url ) {
                    $user_photo_url = $attachment_url;
                    // Sincronizar para futuras lecturas
                    update_user_meta( $user_id, 'fplms_user_photo_id', $stm_photo_id );
                    update_user_meta( $user_id, 'fplms_user_photo_url', $attachment_url );
                }
            }
        }
        
        ?>
        <div class="wrap">
            <h1>✏️ Editar Usuario: <?php echo esc_html( $first_name . ' ' . $last_name ); ?></h1>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=fplms-users' ) ); ?>" class="button">← Volver a la lista de usuarios</a></p>
            
            <?php if ( isset( $_GET['updated'] ) && 'success' === $_GET['updated'] ) : ?>
                <div id="message" class="updated notice notice-success is-dismissible">
                    <p>✓ Usuario actualizado correctamente.</p>
                </div>
            <?php endif; ?>
            
            <?php if ( isset( $_GET['error'] ) ) : ?>
                <div id="message" class="error notice notice-error is-dismissible">
                    <p>
                        <?php
                        $error_msg = sanitize_text_field( wp_unslash( $_GET['error'] ) );
                        $error_messages = [
                            'incomplete_data'     => 'Datos incompletos. Verifica que llenes todos los campos requeridos.',
                            'invalid_id_usuario'  => 'IDUsuario inválido. Debe ser alfanumérico y tener máximo 20 caracteres.',
                            'id_usuario_exists'   => 'El IDUsuario ya existe. Por favor, utiliza uno diferente.',
                        ];
                        echo isset( $error_messages[ $error_msg ] ) ? esc_html( $error_messages[ $error_msg ] ) : 'Error al actualizar el usuario.';
                        ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <style>
                /* ── Variables del sistema de diseño ── */
                .fplms-edit-user-container  { --clr-primary: #667eea; --clr-primary-dark: #764ba2; }

                /* ── Layout principal ── */
                .fplms-profile-hero {
                    background: linear-gradient(135deg, var(--clr-primary) 0%, var(--clr-primary-dark) 100%);
                    border-radius: 16px;
                    padding: 36px;
                    margin-bottom: 28px;
                    display: flex;
                    align-items: center;
                    gap: 28px;
                    position: relative;
                    overflow: hidden;
                    box-shadow: 0 8px 30px rgba(102,126,234,.35);
                }
                .fplms-profile-hero::after {
                    content: '';
                    position: absolute;
                    right: -60px; top: -60px;
                    width: 260px; height: 260px;
                    border-radius: 50%;
                    background: rgba(255,255,255,.12);
                }
                .fplms-hero-avatar-wrap { position: relative; z-index: 1; cursor: pointer; flex-shrink: 0; }
                .fplms-hero-avatar {
                    width: 110px; height: 110px;
                    border-radius: 50%;
                    border: 4px solid #fff;
                    object-fit: cover;
                    box-shadow: 0 6px 20px rgba(0,0,0,.25);
                    display: block;
                }
                .fplms-hero-avatar-placeholder {
                    width: 110px; height: 110px;
                    border-radius: 50%;
                    border: 4px solid #fff;
                    background: rgba(255,255,255,.25);
                    display: flex;
                    align-items: center; justify-content: center;
                    box-shadow: 0 6px 20px rgba(0,0,0,.25);
                }
                .fplms-hero-avatar-placeholder svg { width: 56px; height: 56px; fill: rgba(255,255,255,.9); }
                .fplms-hero-avatar-overlay {
                    position: absolute; inset: 0;
                    border-radius: 50%;
                    background: rgba(0,0,0,.45);
                    display: flex; flex-direction: column;
                    align-items: center; justify-content: center;
                    gap: 4px; color: #fff; font-size: 11px;
                    opacity: 0; transition: opacity .2s;
                    text-align: center; padding: 8px;
                }
                .fplms-hero-avatar-overlay svg { width: 22px; height: 22px; fill: #fff; }
                .fplms-hero-avatar-wrap:hover .fplms-hero-avatar-overlay { opacity: 1; }
                .fplms-hero-info { flex: 1; color: #fff; position: relative; z-index: 1; }
                .fplms-hero-name { font-size: 26px; font-weight: 700; margin: 0 0 6px; color: #fff; }
                .fplms-hero-chips { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
                .fplms-hero-chip {
                    display: inline-flex; align-items: center; gap: 6px;
                    background: rgba(255,255,255,.2);
                    backdrop-filter: blur(8px);
                    border-radius: 20px;
                    padding: 5px 14px; font-size: 13px; color: #fff;
                }
                .fplms-hero-chip svg { width: 14px; height: 14px; fill: #fff; }

                /* ── Cards del formulario ── */
                .fplms-cards-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
                    gap: 22px;
                }
                .fplms-card {
                    background: #fff;
                    border-radius: 14px;
                    box-shadow: 0 2px 12px rgba(0,0,0,.07);
                    overflow: hidden;
                    transition: transform .2s, box-shadow .2s;
                }
                .fplms-card:hover { transform: translateY(-2px); box-shadow: 0 6px 22px rgba(0,0,0,.10); }
                .fplms-card-header {
                    padding: 18px 22px;
                    background: #fafbff;
                    border-bottom: 1px solid #edf0fb;
                    display: flex; align-items: center; gap: 12px;
                }
                .fplms-card-header-icon {
                    width: 38px; height: 38px;
                    border-radius: 10px;
                    background: linear-gradient(135deg, var(--clr-primary) 0%, var(--clr-primary-dark) 100%);
                    display: flex; align-items: center; justify-content: center;
                    box-shadow: 0 3px 10px rgba(102,126,234,.4);
                }
                .fplms-card-header-icon svg { width: 20px; height: 20px; fill: #fff; }
                .fplms-card-header h3 { margin: 0; font-size: 15px; font-weight: 600; color: #2d3748; }
                .fplms-card-header p  { margin: 2px 0 0; font-size: 12px; color: #6c757d; }
                .fplms-card-body { padding: 22px; }

                /* ── Grupos de campo ── */
                .fplms-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
                .fplms-form-row.full { grid-template-columns: 1fr; }
                .fplms-form-group { display: flex; flex-direction: column; margin-bottom: 18px; }
                .fplms-form-group:last-child { margin-bottom: 0; }

                .fplms-form-group label {
                    display: flex; align-items: center; gap: 6px;
                    font-size: 12px; font-weight: 600; letter-spacing: .4px;
                    text-transform: uppercase; color: #6c757d; margin-bottom: 8px;
                }
                .fplms-form-group label svg { width: 13px; height: 13px; fill: #a0aec0; }
                .fplms-form-group label .required { color: #e53e3e; }

                .fplms-form-group input[type="text"],
                .fplms-form-group input[type="email"],
                .fplms-form-group input[type="password"],
                .fplms-form-group select {
                    width: 100%; padding: 11px 15px;
                    border: 2px solid #e9ecef;
                    border-radius: 9px; font-size: 14px; font-family: inherit;
                    background: #fff; transition: border-color .2s, box-shadow .2s;
                }
                .fplms-form-group input:focus,
                .fplms-form-group select:focus {
                    outline: none; border-color: var(--clr-primary);
                    box-shadow: 0 0 0 4px rgba(102,126,234,.12);
                }
                .fplms-form-group input:disabled {
                    background: #f8f9fa; color: #6c757d; cursor: not-allowed;
                }
                .fplms-form-group small { margin-top: 5px; font-size: 11px; color: #a0aec0; }
                .fplms-form-group .required { color: #e53e3e; }

                /* ── Barra de acciones ── */
                .fplms-form-actions {
                    margin-top: 28px;
                    display: flex; gap: 12px; justify-content: flex-end;
                    padding: 20px 24px;
                    background: #fff;
                    border-radius: 14px;
                    box-shadow: 0 2px 12px rgba(0,0,0,.07);
                }
                .fplms-form-actions button {
                    padding: 11px 28px;
                    border: none; border-radius: 9px;
                    font-size: 14px; font-weight: 600; cursor: pointer;
                    display: inline-flex; align-items: center; gap: 8px;
                    transition: all .2s;
                }
                .fplms-form-actions .button-primary {
                    background: linear-gradient(135deg, var(--clr-primary) 0%, var(--clr-primary-dark) 100%);
                    color: #fff;
                    box-shadow: 0 4px 15px rgba(102,126,234,.4);
                }
                .fplms-form-actions .button-primary:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 6px 20px rgba(102,126,234,.5);
                }
                .fplms-form-actions .button-secondary {
                    background: #f8f9fa; color: #495057; border: 2px solid #e9ecef;
                }
                .fplms-form-actions .button-secondary:hover { background: #e9ecef; }

                /* ── Status toggle ── */
                .fplms-status-row {
                    display: flex; align-items: center; justify-content: space-between;
                    padding: 14px 16px;
                    background: #f8f9fa; border-radius: 9px;
                }
                .fplms-status-label { font-size: 14px; font-weight: 500; color: #333; display: flex; align-items: center; gap: 8px; }
                .fplms-status-label svg { width: 18px; height: 18px; fill: #6c757d; }

                input[type="file"] { display: none; }

                @media (max-width: 900px) {
                    .fplms-profile-hero { flex-direction: column; text-align: center; }
                    .fplms-hero-chips { justify-content: center; }
                    .fplms-cards-grid { grid-template-columns: 1fr; }
                    .fplms-form-row { grid-template-columns: 1fr; }
                }

                /* Estilos para el modal de confirmación */
                .fplms-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.5);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 100000;
                    animation: fadeIn 0.2s ease;
                }

                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }

                .fplms-modal-content {
                    background: #fff;
                    border-radius: 8px;
                    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                    max-width: 600px;
                    width: 90%;
                    max-height: 90vh;
                    overflow-y: auto;
                    animation: slideIn 0.3s ease;
                }

                @keyframes slideIn {
                    from {
                        opacity: 0;
                        transform: translateY(-50px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                .fplms-modal-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 20px;
                    border-bottom: 1px solid #eee;
                }

                .fplms-modal-header h3 {
                    margin: 0;
                    font-size: 18px;
                }

                .fplms-modal-body {
                    padding: 20px;
                }

                .fplms-modal-footer {
                    padding: 16px 20px;
                    border-top: 1px solid #dee2e6;
                    background: #f8f9fa;
                }
            </style>
            
            <div class="fplms-edit-user-container">
                <form method="post" id="fplms-edit-user-form" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'fplms_edit_user_save', 'fplms_edit_user_nonce' ); ?>
                    <input type="hidden" name="fplms_edit_user_action" value="update_user">
                    <input type="hidden" name="fplms_user_id" value="<?php echo esc_attr( $user_id ); ?>">
                    <input type="file" id="fplms_user_photo" name="fplms_user_photo" accept="image/*">

                    <!-- ── Hero / encabezado de perfil ── -->
                    <div class="fplms-profile-hero">
                        <div class="fplms-hero-avatar-wrap" id="fplms-image-upload-area">
                            <?php if ( $user_photo_url ) : ?>
                                <img src="<?php echo esc_url( $user_photo_url ); ?>" alt="Foto de usuario" class="fplms-hero-avatar" id="fplms-preview-image">
                            <?php else : ?>
                                <div class="fplms-hero-avatar-placeholder" id="fplms-avatar-placeholder">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                                </div>
                            <?php endif; ?>
                            <div class="fplms-hero-avatar-overlay">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 16.5l4-4h-3v-9h-2v9H8l4 4zm9 4.5H3v-4.5H1V21a2 2 0 002 2h18a2 2 0 002-2v-4.5h-2V21z"/></svg>
                                Cambiar foto
                            </div>
                        </div>
                        <div class="fplms-hero-info">
                            <h2 class="fplms-hero-name"><?php echo esc_html( $first_name . ' ' . $last_name ); ?></h2>
                            <div class="fplms-hero-chips">
                                <span class="fplms-hero-chip">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                                    <?php echo esc_html( $user->user_email ); ?>
                                </span>
                                <span class="fplms-hero-chip">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                                    <?php echo esc_html( $user->user_login ); ?>
                                </span>
                                <?php if ( $is_user_active ) : ?>
                                    <span class="fplms-hero-chip" style="background:rgba(72,199,142,.35);">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z"/></svg>
                                        Activo
                                    </span>
                                <?php else : ?>
                                    <span class="fplms-hero-chip" style="background:rgba(252,129,74,.35);">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/></svg>
                                        Inactivo
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ── Grid de tarjetas ── -->
                    <div class="fplms-cards-grid">

                        <!-- Tarjeta: Datos personales -->
                        <div class="fplms-card">
                            <div class="fplms-card-header">
                                <div class="fplms-card-header-icon">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                                </div>
                                <div>
                                    <h3>Datos Personales</h3>
                                    <p>Nombre y apellido del usuario</p>
                                </div>
                            </div>
                            <div class="fplms-card-body">
                                <div class="fplms-form-row">
                                    <div class="fplms-form-group">
                                        <label for="fplms_first_name">
                                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12z"/></svg>
                                            Nombre <span class="required">*</span>
                                        </label>
                                        <input type="text" id="fplms_first_name" name="fplms_first_name" value="<?php echo esc_attr( $first_name ); ?>" required>
                                    </div>
                                    <div class="fplms-form-group">
                                        <label for="fplms_last_name">
                                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12z"/></svg>
                                            Apellido <span class="required">*</span>
                                        </label>
                                        <input type="text" id="fplms_last_name" name="fplms_last_name" value="<?php echo esc_attr( $last_name ); ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tarjeta: Credenciales -->
                        <div class="fplms-card">
                            <div class="fplms-card-header">
                                <div class="fplms-card-header-icon">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                                </div>
                                <div>
                                    <h3>Credenciales de Acceso</h3>
                                    <p>Usuario, email y contraseña</p>
                                </div>
                            </div>
                            <div class="fplms-card-body">
                                <div class="fplms-form-group">
                                    <label for="fplms_id_usuario">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17.63 5.84C17.27 5.33 16.67 5 16 5L5 5.01C3.9 5.01 3 5.9 3 7v10c0 1.1.9 1.99 2 1.99L16 19c.67 0 1.27-.33 1.63-.84L22 12l-4.37-6.16z"/></svg>
                                        IDUsuario <span class="required">*</span>
                                    </label>
                                    <input type="text" id="fplms_id_usuario" name="fplms_id_usuario"
                                           value="<?php echo esc_attr( $id_usuario ); ?>"
                                           maxlength="20" pattern="[a-zA-Z0-9]+"
                                           title="Solo letras y números, máximo 20 caracteres" required>
                                    <small>Alfanumérico, máximo 20 caracteres.</small>
                                </div>
                                <div class="fplms-form-group">
                                    <label for="fplms_user_login">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg>
                                        Nombre de usuario
                                    </label>
                                    <input type="text" id="fplms_user_login" value="<?php echo esc_attr( $user->user_login ); ?>" disabled>
                                    <small>El nombre de usuario no se puede cambiar.</small>
                                </div>
                                <div class="fplms-form-group">
                                    <label for="fplms_user_email">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                                        Correo electrónico <span class="required">*</span>
                                    </label>
                                    <input type="email" id="fplms_user_email" name="fplms_user_email" value="<?php echo esc_attr( $user->user_email ); ?>" required>
                                </div>
                                <div class="fplms-form-group">
                                    <label for="fplms_user_pass">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2z"/></svg>
                                        Nueva contraseña
                                    </label>
                                    <input type="password" id="fplms_user_pass" name="fplms_user_pass" placeholder="Dejar vacío para no cambiar">
                                    <small>Dejar en blanco para mantener la contraseña actual.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Tarjeta: Estructura Organizacional -->
                        <div class="fplms-card">
                            <div class="fplms-card-header">
                                <div class="fplms-card-header-icon">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                                </div>
                                <div>
                                    <h3>Estructura Organizacional</h3>
                                    <p>Ciudad, empresa, canal y sucursal</p>
                                </div>
                            </div>
                            <div class="fplms-card-body">
                                <div class="fplms-form-row">
                                    <div class="fplms-form-group">
                                        <label for="fplms_city">
                                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                                            Ciudad
                                        </label>
                                        <select name="fplms_city" id="fplms_city">
                                            <option value="">— Sin asignar —</option>
                                            <?php foreach ( $cities as $id => $name ) : ?>
                                                <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $city_id, $id ); ?>>
                                                    <?php echo esc_html( $name ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="fplms-form-group">
                                        <label for="fplms_company">
                                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg>
                                            Empresa
                                        </label>
                                        <select name="fplms_company" id="fplms_company">
                                            <option value="">— Sin asignar —</option>
                                            <?php foreach ( $companies as $id => $name ) : ?>
                                                <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $company_id, $id ); ?>>
                                                    <?php echo esc_html( $name ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="fplms-form-row">
                                    <div class="fplms-form-group">
                                        <label for="fplms_channel">
                                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                                            Canal / Franquicia
                                        </label>
                                        <select name="fplms_channel" id="fplms_channel">
                                            <option value="">— Sin asignar —</option>
                                            <?php foreach ( $channels as $id => $name ) : ?>
                                                <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $channel_id, $id ); ?>>
                                                    <?php echo esc_html( $name ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="fplms-form-group">
                                        <label for="fplms_branch">
                                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M13 7h-2v2h2V7zm0 4h-2v6h2v-6zm4-9.99L7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99zM17 19H7V5h10v14z"/></svg>
                                            Sucursal
                                        </label>
                                        <select name="fplms_branch" id="fplms_branch">
                                            <option value="">— Sin asignar —</option>
                                            <?php foreach ( $branches as $id => $name ) : ?>
                                                <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $branch_id, $id ); ?>>
                                                    <?php echo esc_html( $name ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="fplms-form-group">
                                    <label for="fplms_job_role">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.33C18 2.1 15.9 0 13.33 0c-1.44 0-2.68.63-3.57 1.62L8 3.5 6.24 1.62C5.35.63 4.1 0 2.67 0 1.1 0-1.1 2.1-1.1 4.67c0 .45.11.89.18 1.33H-1c-1.1 0-2 .9-2 2v11c0 1.1.9 2 2 2h21c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2z"/></svg>
                                        Cargo
                                    </label>
                                    <select name="fplms_job_role" id="fplms_job_role">
                                        <option value="">— Sin asignar —</option>
                                        <?php foreach ( $job_roles as $id => $name ) : ?>
                                            <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $role_id, $id ); ?>>
                                                <?php echo esc_html( $name ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Tarjeta: Rol y Estado -->
                        <div class="fplms-card">
                            <div class="fplms-card-header">
                                <div class="fplms-card-header-icon">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg>
                                </div>
                                <div>
                                    <h3>Tipo de Usuario y Estado</h3>
                                    <p>Rol asignado y estado de acceso</p>
                                </div>
                            </div>
                            <div class="fplms-card-body">
                                <div class="fplms-form-group">
                                    <label for="fplms_user_role">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                                        Tipo de usuario <span class="required">*</span>
                                    </label>
                                    <select name="fplms_user_role" id="fplms_user_role" required>
                                        <option value="">— Seleccionar tipo de usuario —</option>
                                        <?php foreach ( $roles_def_labels as $role_key => $role_label ) : ?>
                                            <option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( $user_role, $role_key ); ?>>
                                                <?php echo esc_html( $role_label ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="fplms-status-row" id="fplms-status-row">
                                    <div class="fplms-status-label" id="fplms-status-label">
                                        <?php if ( $is_user_active ) : ?>
                                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="fill:#48c78e;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z"/></svg>
                                            Usuario Activo
                                        <?php else : ?>
                                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="fill:#fc814a;"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/></svg>
                                            Usuario Inactivo
                                        <?php endif; ?>
                                    </div>
                                    <label style="position:relative;display:inline-block;width:52px;height:28px;cursor:pointer;">
                                        <input type="checkbox"
                                               name="fplms_user_status"
                                               id="fplms_user_status"
                                               value="active"
                                               <?php checked( $is_user_active ); ?>
                                               style="opacity:0;width:0;height:0;">
                                        <span id="fplms-toggle-track" style="
                                            position:absolute;inset:0;border-radius:28px;
                                            background:<?php echo $is_user_active ? '#48c78e' : '#ccc'; ?>;
                                            transition:background .3s;">
                                        </span>
                                        <span id="fplms-toggle-thumb" style="
                                            position:absolute;top:3px;
                                            left:<?php echo $is_user_active ? '27px' : '3px'; ?>;
                                            width:22px;height:22px;
                                            border-radius:50%;background:#fff;
                                            box-shadow:0 2px 6px rgba(0,0,0,.25);
                                            transition:left .3s;">
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                    </div><!-- .fplms-cards-grid -->

                    <!-- ── Botones de acción ── -->
                    <div class="fplms-form-actions">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=fplms-users' ) ); ?>" class="button-secondary" style="padding:11px 28px;border-radius:9px;font-size:14px;font-weight:600;cursor:pointer;background:#f8f9fa;color:#495057;border:2px solid #e9ecef;text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="#495057"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                            Cancelar
                        </a>
                        <button type="submit" class="button-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="#fff"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                            Guardar Cambios
                        </button>
                    </div>

                </form>
            </div>
            
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // ── Subida de foto de perfil en el hero ──
                    const uploadArea = document.getElementById('fplms-image-upload-area');
                    const fileInput  = document.getElementById('fplms_user_photo');

                    if (uploadArea && fileInput) {
                        uploadArea.addEventListener('click', function() {
                            fileInput.click();
                        });

                        fileInput.addEventListener('change', function(e) {
                            if (this.files && this.files[0]) {
                                const file = this.files[0];
                                if (!file.type.startsWith('image/')) {
                                    alert('Por favor selecciona un archivo de imagen válido.');
                                    this.value = '';
                                    return;
                                }
                                if (file.size > 5 * 1024 * 1024) {
                                    alert('La imagen es demasiado grande. El tamaño máximo es 5MB.');
                                    this.value = '';
                                    return;
                                }
                                const reader = new FileReader();
                                reader.onload = function(e) {
                                    // Mostrar imagen circular en el hero
                                    let existing = uploadArea.querySelector('.fplms-hero-avatar');
                                    let placeholder = uploadArea.querySelector('.fplms-hero-avatar-placeholder');
                                    if (existing) {
                                        existing.src = e.target.result;
                                    } else {
                                        if (placeholder) placeholder.remove();
                                        const img = document.createElement('img');
                                        img.src = e.target.result;
                                        img.className = 'fplms-hero-avatar';
                                        img.id = 'fplms-preview-image';
                                        img.alt = 'Vista previa';
                                        uploadArea.insertBefore(img, uploadArea.querySelector('.fplms-hero-avatar-overlay'));
                                    }
                                };
                                reader.readAsDataURL(file);
                            }
                        });
                    }

                    // ── Toggle de estado con animación ──
                    const statusCheckbox = document.getElementById('fplms_user_status');
                    if (statusCheckbox) {
                        const track = document.getElementById('fplms-toggle-track');
                        const thumb = document.getElementById('fplms-toggle-thumb');
                        const label = document.getElementById('fplms-status-label');
                        statusCheckbox.addEventListener('change', function() {
                            if (this.checked) {
                                track.style.background = '#48c78e';
                                thumb.style.left = '27px';
                                label.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" style="fill:#48c78e;flex-shrink:0;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z"/></svg> Usuario Activo';
                            } else {
                                track.style.background = '#ccc';
                                thumb.style.left = '3px';
                                label.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" style="fill:#fc814a;flex-shrink:0;"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/></svg> Usuario Inactivo';
                            }
                        });
                    }
                });

                // ── Modal de confirmación para guardar cambios ──
                const editForm = document.getElementById('fplms-edit-user-form');
                if (editForm) {
                    editForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const modalHTML = `
                            <div id="fplms-edit-confirm-modal" class="fplms-modal" style="display: flex; z-index: 100000;">
                                <div class="fplms-modal-content" style="max-width: 500px;">
                                    <div class="fplms-modal-header" style="background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); color: white;">
                                        <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                                            <span style="font-size: 24px;">⚠️</span>
                                            <span>Confirmar Actualización</span>
                                        </h3>
                                    </div>
                                    <div class="fplms-modal-body" style="padding: 30px 20px;">
                                        <p style="font-size: 16px; color: #333; margin: 0 0 15px 0;">
                                            ¿Estás seguro de que deseas actualizar la información de este usuario?
                                        </p>
                                        <p style="font-size: 14px; color: #666; margin: 0;">
                                            Los cambios se guardarán permanentemente en el sistema.
                                        </p>
                                    </div>
                                    <div class="fplms-modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; padding: 15px 20px; background: #f8f9fa; border-top: 1px solid #dee2e6;">
                                        <button type="button" class="button" onclick="fplmsCloseEditConfirmModal()" style="padding: 10px 20px;">
                                            Cancelar
                                        </button>
                                        <button type="button" class="button button-primary" onclick="fplmsConfirmEditUser()" style="padding: 10px 30px; background: #2196F3; border-color: #2196F3;">
                                            💾 Guardar Cambios
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        document.body.insertAdjacentHTML('beforeend', modalHTML);
                    });
                }

                // Función para cerrar modal de confirmación
                window.fplmsCloseEditConfirmModal = function() {
                    const modal = document.getElementById('fplms-edit-confirm-modal');
                    if (modal) {
                        modal.remove();
                    }
                };

                // Función para confirmar y enviar formulario
                window.fplmsConfirmEditUser = function() {
                    const modal = document.getElementById('fplms-edit-confirm-modal');
                    if (modal) {
                        modal.remove();
                    }
                    
                    const editForm = document.getElementById('fplms-edit-user-form');
                    if (editForm) {
                        // Remover el event listener para evitar loop
                        const newForm = editForm.cloneNode(true);
                        editForm.parentNode.replaceChild(newForm, editForm);
                        newForm.submit();
                    }
                };
                
                // Manejar cambio de estado (checkbox activo/inactivo)
                const statusCheckbox = document.getElementById('fplms_user_status');
                if (statusCheckbox) {
                    const statusLabel = statusCheckbox.parentElement.querySelector('span');
                    
                    statusCheckbox.addEventListener('change', function() {
                        if (this.checked) {
                            statusLabel.textContent = '✓ Usuario Activo';
                        } else {
                            statusLabel.textContent = '⊘ Usuario Inactivo';
                        }
                    });
                }
            </script>
        </div>
        <?php
    }

    /**
     * Manejo del formulario para crear nuevo usuario.
     */
    public function handle_new_user_form(): void {

        if ( ! isset( $_POST['fplms_new_user_action'] ) ) {
            return;
        }

        if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_USERS ) ) {
            return;
        }

        if (
            ! isset( $_POST['fplms_new_user_nonce'] ) ||
            ! wp_verify_nonce( $_POST['fplms_new_user_nonce'], 'fplms_new_user_save' )
        ) {
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_POST['fplms_new_user_action'] ) );

        if ( 'create_user' === $action ) {

            $user_login = sanitize_text_field( wp_unslash( $_POST['fplms_user_login'] ?? '' ) );
            $user_email = sanitize_email( wp_unslash( $_POST['fplms_user_email'] ?? '' ) );
            $user_pass  = sanitize_text_field( wp_unslash( $_POST['fplms_user_pass'] ?? '' ) );
            $first_name = sanitize_text_field( wp_unslash( $_POST['fplms_first_name'] ?? '' ) );
            $last_name  = sanitize_text_field( wp_unslash( $_POST['fplms_last_name'] ?? '' ) );
            $id_usuario = sanitize_text_field( wp_unslash( $_POST['fplms_id_usuario'] ?? '' ) );
            $city_id    = isset( $_POST['fplms_city'] ) ? absint( $_POST['fplms_city'] ) : 0;
            $company_id = isset( $_POST['fplms_company'] ) ? absint( $_POST['fplms_company'] ) : 0;
            $channel_id = isset( $_POST['fplms_channel'] ) ? absint( $_POST['fplms_channel'] ) : 0;
            $branch_id  = isset( $_POST['fplms_branch'] ) ? absint( $_POST['fplms_branch'] ) : 0;
            $role_id    = isset( $_POST['fplms_job_role'] ) ? absint( $_POST['fplms_job_role'] ) : 0;
            $user_role   = isset( $_POST['fplms_user_role'] ) ? sanitize_text_field( wp_unslash( $_POST['fplms_user_role'] ) ) : '';
            $user_status = ( isset( $_POST['fplms_user_active'] ) && '1' === $_POST['fplms_user_active'] ) ? 'active' : 'inactive';

            // Validar datos requeridos (nombre, apellido, IDUsuario y rol son requeridos)
            if ( ! $user_login || ! $user_email || ! $user_pass || ! $first_name || ! $last_name || ! $id_usuario || ! $user_role ) {
                wp_safe_redirect(
                    add_query_arg(
                        [ 'page' => 'fplms-users', 'action' => 'create', 'error' => 'incomplete_data' ],
                        admin_url( 'admin.php' )
                    )
                );
                exit;
            }

            // Validar formato de IDUsuario (alfanumérico, máximo 20 caracteres)
            if ( ! preg_match( '/^[a-zA-Z0-9]{1,20}$/', $id_usuario ) ) {
                wp_safe_redirect(
                    add_query_arg(
                        [ 'page' => 'fplms-users', 'action' => 'create', 'error' => 'invalid_id_usuario' ],
                        admin_url( 'admin.php' )
                    )
                );
                exit;
            }

            // Verificar que IDUsuario sea único
            $existing_users = get_users( [
                'meta_key'   => FairPlay_LMS_Config::USER_META_ID_USUARIO,
                'meta_value' => $id_usuario,
                'number'     => 1,
            ] );

            if ( ! empty( $existing_users ) ) {
                wp_safe_redirect(
                    add_query_arg(
                        [ 'page' => 'fplms-users', 'action' => 'create', 'error' => 'id_usuario_exists' ],
                        admin_url( 'admin.php' )
                    )
                );
                exit;
            }

            // Crear usuario
            $user_id = wp_create_user( $user_login, $user_pass, $user_email );

            if ( is_wp_error( $user_id ) ) {
                wp_safe_redirect(
                    add_query_arg(
                        [ 'page' => 'fplms-users', 'action' => 'create', 'error' => 'user_exists' ],
                        admin_url( 'admin.php' )
                    )
                );
                exit;
            }

            // Actualizar datos del usuario
            if ( $first_name ) {
                update_user_meta( $user_id, 'first_name', $first_name );
            }
            if ( $last_name ) {
                update_user_meta( $user_id, 'last_name', $last_name );
            }
            if ( $id_usuario ) {
                update_user_meta( $user_id, FairPlay_LMS_Config::USER_META_ID_USUARIO, $id_usuario );
            }

            // Manejar subida de fotografía
            if ( isset( $_FILES['fplms_user_photo'] ) && ! empty( $_FILES['fplms_user_photo']['tmp_name'] ) ) {
                $this->handle_user_photo_upload( $user_id, $_FILES['fplms_user_photo'] );
            }

            // Asignar rol - Remover "subscriber" automático de wp_create_user
            $user = new WP_User( $user_id );
            // Remover rol "subscriber" que wp_create_user() asigna automáticamente
            $user->remove_role( 'subscriber' );
            // Asignar el rol seleccionado
            if ( $user_role ) {
                $user->add_role( $user_role );
            }

            // Asignar estructuras
            if ( $city_id ) {
                update_user_meta( $user_id, FairPlay_LMS_Config::USER_META_CITY, $city_id );
            }
            if ( $company_id ) {
                update_user_meta( $user_id, FairPlay_LMS_Config::USER_META_COMPANY, $company_id );
            }
            if ( $channel_id ) {
                update_user_meta( $user_id, FairPlay_LMS_Config::USER_META_CHANNEL, $channel_id );
            }
            if ( $branch_id ) {
                update_user_meta( $user_id, FairPlay_LMS_Config::USER_META_BRANCH, $branch_id );
            }
            if ( $role_id ) {
                update_user_meta( $user_id, FairPlay_LMS_Config::USER_META_ROLE, $role_id );
            }

            // Guardar estado activo/inactivo
            update_user_meta( $user_id, 'fplms_user_status', $user_status );

            // Registrar en bitácora de auditoría
            $new_user_data = get_userdata( $user_id );
            $this->logger->log_user_created(
                $user_id,
                trim( $first_name . ' ' . $last_name ),
                [
                    'login'      => $user_login,
                    'email'      => $user_email,
                    'id_usuario' => $id_usuario,
                    'role'       => $user_role,
                    'status'     => $user_status,
                    'city_id'    => $city_id,
                    'company_id' => $company_id,
                    'channel_id' => $channel_id,
                    'branch_id'  => $branch_id,
                    'role_id'    => $role_id,
                ]
            );

            wp_safe_redirect(
                add_query_arg(
                    [ 'page' => 'fplms-users', 'user_created' => $user_id ],
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }
    }

    /**
     * Manejo del formulario para editar usuario existente
     */
    public function handle_edit_user_form(): void {

        if ( ! isset( $_POST['fplms_edit_user_action'] ) ) {
            return;
        }

        if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_USERS ) ) {
            return;
        }

        if (
            ! isset( $_POST['fplms_edit_user_nonce'] ) ||
            ! wp_verify_nonce( $_POST['fplms_edit_user_nonce'], 'fplms_edit_user_save' )
        ) {
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_POST['fplms_edit_user_action'] ) );

        if ( 'update_user' === $action ) {
            $user_id    = isset( $_POST['fplms_user_id'] ) ? absint( $_POST['fplms_user_id'] ) : 0;
            
            if ( ! $user_id ) {
                wp_safe_redirect(
                    add_query_arg(
                        [ 'page' => 'fplms-users', 'error' => 'invalid_user' ],
                        admin_url( 'admin.php' )
                    )
                );
                exit;
            }

            // Capturar datos antiguos para la bitácora
            $user = get_userdata( $user_id );
            $old_data = [
                'email'       => $user->user_email,
                'first_name'  => get_user_meta( $user_id, 'first_name', true ),
                'last_name'   => get_user_meta( $user_id, 'last_name', true ),
                'id_usuario'  => get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_ID_USUARIO, true ),
                'role'        => ! empty( $user->roles ) ? $user->roles[0] : '',
                'city_id'     => get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_CITY, true ),
                'company_id'  => get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_COMPANY, true ),
                'channel_id'  => get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_CHANNEL, true ),
                'branch_id'   => get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_BRANCH, true ),
                'role_id'     => get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_ROLE, true ),
            ];

            $user_email  = sanitize_email( wp_unslash( $_POST['fplms_user_email'] ?? '' ) );
            $user_pass   = isset( $_POST['fplms_user_pass'] ) ? sanitize_text_field( wp_unslash( $_POST['fplms_user_pass'] ) ) : '';
            $first_name  = sanitize_text_field( wp_unslash( $_POST['fplms_first_name'] ?? '' ) );
            $last_name   = sanitize_text_field( wp_unslash( $_POST['fplms_last_name'] ?? '' ) );
            $id_usuario  = sanitize_text_field( wp_unslash( $_POST['fplms_id_usuario'] ?? '' ) );
            $city_id     = isset( $_POST['fplms_city'] ) ? absint( $_POST['fplms_city'] ) : 0;
            $company_id  = isset( $_POST['fplms_company'] ) ? absint( $_POST['fplms_company'] ) : 0;
            $channel_id  = isset( $_POST['fplms_channel'] ) ? absint( $_POST['fplms_channel'] ) : 0;
            $branch_id   = isset( $_POST['fplms_branch'] ) ? absint( $_POST['fplms_branch'] ) : 0;
            $role_id     = isset( $_POST['fplms_job_role'] ) ? absint( $_POST['fplms_job_role'] ) : 0;
            $user_role   = isset( $_POST['fplms_user_role'] ) ? sanitize_text_field( wp_unslash( $_POST['fplms_user_role'] ) ) : '';

            // Validar datos requeridos
            if ( ! $user_email || ! $first_name || ! $last_name || ! $id_usuario || ! $user_role ) {
                wp_safe_redirect(
                    add_query_arg(
                        [ 'page' => 'fplms-users', 'action' => 'edit', 'user_id' => $user_id, 'error' => 'incomplete_data' ],
                        admin_url( 'admin.php' )
                    )
                );
                exit;
            }

            // Validar formato de IDUsuario
            if ( ! preg_match( '/^[a-zA-Z0-9]{1,20}$/', $id_usuario ) ) {
                wp_safe_redirect(
                    add_query_arg(
                        [ 'page' => 'fplms-users', 'action' => 'edit', 'user_id' => $user_id, 'error' => 'invalid_id_usuario' ],
                        admin_url( 'admin.php' )
                    )
                );
                exit;
            }

            // Verificar que IDUsuario sea único (excepto para el usuario actual)
            $current_id_usuario = get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_ID_USUARIO, true );
            
            if ( $id_usuario !== $current_id_usuario ) {
                $existing_users = get_users( [
                    'meta_key'   => FairPlay_LMS_Config::USER_META_ID_USUARIO,
                    'meta_value' => $id_usuario,
                    'number'     => 1,
                    'exclude'    => [ $user_id ],
                ] );

                if ( ! empty( $existing_users ) ) {
                    wp_safe_redirect(
                        add_query_arg(
                            [ 'page' => 'fplms-users', 'action' => 'edit', 'user_id' => $user_id, 'error' => 'id_usuario_exists' ],
                            admin_url( 'admin.php' )
                        )
                    );
                    exit;
                }
            }

            // Actualizar datos básicos del usuario
            $user_data = [
                'ID'         => $user_id,
                'user_email' => $user_email,
            ];

            // Si se proporcionó una nueva contraseña, agregarla
            if ( ! empty( $user_pass ) ) {
                $user_data['user_pass'] = $user_pass;
            }

            wp_update_user( $user_data );

            // Actualizar metadata
            if ( $first_name ) {
                update_user_meta( $user_id, 'first_name', $first_name );
            }
            if ( $last_name ) {
                update_user_meta( $user_id, 'last_name', $last_name );
            }
            if ( $id_usuario ) {
                update_user_meta( $user_id, FairPlay_LMS_Config::USER_META_ID_USUARIO, $id_usuario );
            }

            // Manejar subida de fotografía
            if ( isset( $_FILES['fplms_user_photo'] ) && ! empty( $_FILES['fplms_user_photo']['tmp_name'] ) ) {
                $this->handle_user_photo_upload( $user_id, $_FILES['fplms_user_photo'] );
            }

            // Actualizar estado del usuario
            $new_status = isset( $_POST['fplms_user_status'] ) && $_POST['fplms_user_status'] === 'active' ? 'active' : 'inactive';
            $old_status = get_user_meta( $user_id, 'fplms_user_status', true );
            $old_status = empty( $old_status ) ? 'active' : $old_status;
            
            if ( $new_status !== $old_status ) {
                update_user_meta( $user_id, 'fplms_user_status', $new_status );
                
                if ( $new_status === 'inactive' ) {
                    update_user_meta( $user_id, 'fplms_deactivated_date', current_time( 'mysql' ) );
                    update_user_meta( $user_id, 'fplms_deactivated_by', get_current_user_id() );
                    $this->logger->log_user_deactivated( $user_id, $user->display_name, $user->user_email );
                } else {
                    update_user_meta( $user_id, 'fplms_reactivated_date', current_time( 'mysql' ) );
                    update_user_meta( $user_id, 'fplms_reactivated_by', get_current_user_id() );
                    $this->logger->log_user_reactivated( $user_id, $user->display_name, $user->user_email );
                }
            }

            // Actualizar rol si cambió
            $user = new WP_User( $user_id );
            $current_roles = $user->roles;
            
            if ( ! in_array( $user_role, $current_roles, true ) ) {
                // Remover todos los roles actuales
                foreach ( $current_roles as $role ) {
                    $user->remove_role( $role );
                }
                // Asignar el nuevo rol
                $user->add_role( $user_role );
            }

            // Asignar estructuras
            if ( $city_id ) {
                update_user_meta( $user_id, FairPlay_LMS_Config::USER_META_CITY, $city_id );
            } else {
                delete_user_meta( $user_id, FairPlay_LMS_Config::USER_META_CITY );
            }
            
            if ( $company_id ) {
                update_user_meta( $user_id, FairPlay_LMS_Config::USER_META_COMPANY, $company_id );
            } else {
                delete_user_meta( $user_id, FairPlay_LMS_Config::USER_META_COMPANY );
            }
            
            if ( $channel_id ) {
                update_user_meta( $user_id, FairPlay_LMS_Config::USER_META_CHANNEL, $channel_id );
            } else {
                delete_user_meta( $user_id, FairPlay_LMS_Config::USER_META_CHANNEL );
            }
            
            if ( $branch_id ) {
                update_user_meta( $user_id, FairPlay_LMS_Config::USER_META_BRANCH, $branch_id );
            } else {
                delete_user_meta( $user_id, FairPlay_LMS_Config::USER_META_BRANCH );
            }
            
            if ( $role_id ) {
                update_user_meta( $user_id, FairPlay_LMS_Config::USER_META_ROLE, $role_id );
            } else {
                delete_user_meta( $user_id, FairPlay_LMS_Config::USER_META_ROLE );
            }

            // Preparar datos nuevos para la bitácora
            $new_data = [
                'email'       => $user_email,
                'first_name'  => $first_name,
                'last_name'   => $last_name,
                'id_usuario'  => $id_usuario,
                'role'        => $user_role,
                'city_id'     => $city_id,
                'company_id'  => $company_id,
                'channel_id'  => $channel_id,
                'branch_id'   => $branch_id,
                'role_id'     => $role_id,
            ];

            // Registrar en bitácora
            $user_name = $first_name . ' ' . $last_name;
            $this->logger->log_user_updated( $user_id, $user_name, $old_data, $new_data );

            // Redirigir con mensaje de éxito
            wp_safe_redirect(
                add_query_arg(
                    [ 'page' => 'fplms-users', 'action' => 'edit', 'user_id' => $user_id, 'updated' => 'success' ],
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }
    }

    /**
     * Obtiene las relaciones de estructura para la cascada de selects
     */
    private function get_structure_relations(): array {
        // Retornar un array vacío para evitar errores de procesamiento
        // Los cascading selects funcionarán desde el PHP cuando se cargue el formulario
        return [
            'city_channels'      => [],
            'channel_branches'   => [],
            'branch_roles'       => [],
        ];
    }

    /**
     * Manejo de subida de fotografía de usuario.
     */
    private function handle_user_photo_upload( int $user_id, array $file ): void {

        // Validar archivo
        if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            return;
        }

        // Validar tipo de archivo
        $allowed_types = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];
        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        $mime_type = finfo_file( $finfo, $file['tmp_name'] );
        finfo_close( $finfo );

        if ( ! in_array( $mime_type, $allowed_types, true ) ) {
            return;
        }

        // Validar tamaño (máximo 5MB)
        if ( $file['size'] > 5 * 1024 * 1024 ) {
            return;
        }

        // Usar WordPress Media Library para guardar la imagen
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // Crear un archivo temporal para que WordPress lo maneje
        $overrides = [ 'test_form' => false ];
        $uploaded_file = wp_handle_upload( $file, $overrides );

        if ( isset( $uploaded_file['error'] ) ) {
            return;
        }

        // Crear un attachment post
        $file_path = $uploaded_file['file'];
        $file_url = $uploaded_file['url'];
        $file_type = $uploaded_file['type'];

        $attachment = [
            'post_mime_type' => $file_type,
            'post_title'     => 'Foto del usuario ' . get_user_by( 'id', $user_id )->display_name,
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachment_id = wp_insert_attachment( $attachment, $file_path );

        if ( ! is_wp_error( $attachment_id ) ) {
            // Generar metadatos de la imagen
            $attach_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
            wp_update_attachment_metadata( $attachment_id, $attach_data );

            // Guardar el ID del attachment en la metadata del usuario
            update_user_meta( $user_id, 'fplms_user_photo_id', $attachment_id );

            // También guardar la URL para acceso rápido
            update_user_meta( $user_id, 'fplms_user_photo_url', $file_url );
            
            // IMPORTANTE: Sincronizar con MasterStudy LMS para que la foto aparezca en todo el sistema
            update_user_meta( $user_id, 'stm_lms_user_avatar', $file_url );
        }
    }

    /**
     * Maneja la "eliminación" de usuarios (soft delete)
     * En lugar de eliminar definitivamente, marca el usuario como inactivo
     *
     * @param int      $user_id  ID del usuario a eliminar
     * @param int|null $reassign ID del usuario al que reasignar contenido
     * @param WP_User  $user     Objeto del usuario
     * @return void
     */
    public function handle_user_soft_delete( int $user_id, $reassign, $user ): void {
        // Verificar que no sea un super admin
        if ( is_super_admin( $user_id ) ) {
            return; // No permitir eliminar super admins
        }

        // Obtener datos del usuario antes de modificar
        $user_data = get_userdata( $user_id );
        if ( ! $user_data ) {
            return;
        }

        // Marcar usuario como inactivo en lugar de eliminar
        update_user_meta( $user_id, 'fplms_user_status', 'inactive' );
        update_user_meta( $user_id, 'fplms_deactivated_date', current_time( 'mysql' ) );
        update_user_meta( $user_id, 'fplms_deactivated_by', get_current_user_id() );

        // Registrar en auditoría
        $this->logger->log_user_deactivated(
            $user_id,
            $user_data->display_name,
            $user_data->user_email
        );

        // Prevenir la eliminación real del usuario
        // NOTA: Esto no funciona con el hook delete_user, necesitamos usar un filtro diferente
        // Por ahora registramos la acción, la prevención se hará en la interfaz admin
    }

    /**
     * Verifica si un usuario está inactivo (soft deleted)
     *
     * @param int $user_id ID del usuario
     * @return bool True si está inactivo
     */
    public function is_user_inactive( int $user_id ): bool {
        $status = get_user_meta( $user_id, 'fplms_user_status', true );
        return $status === 'inactive';
    }

    /**
     * Reactivar un usuario previamente desactivado
     *
     * @param int $user_id ID del usuario
     * @return bool True si se reactivó exitosamente
     */
    public function reactivate_user( int $user_id ): bool {
        $user_data = get_userdata( $user_id );
        if ( ! $user_data ) {
            return false;
        }

        // Verificar que el usuario esté inactivo
        if ( ! $this->is_user_inactive( $user_id ) ) {
            return false;
        }

        // Reactivar usuario
        update_user_meta( $user_id, 'fplms_user_status', 'active' );
        update_user_meta( $user_id, 'fplms_reactivated_date', current_time( 'mysql' ) );
        update_user_meta( $user_id, 'fplms_reactivated_by', get_current_user_id() );

        // Registrar en auditoría
        $this->logger->log_user_reactivated(
            $user_id,
            $user_data->display_name,
            $user_data->user_email
        );

        return true;
    }

    /**
     * Eliminar permanentemente un usuario
     *
     * @param int $user_id ID del usuario
     * @return bool True si se eliminó exitosamente
     */
    public function permanently_delete_user( int $user_id ): bool {
        $user_data = get_userdata( $user_id );
        if ( ! $user_data ) {
            return false;
        }

        // Registrar en auditoría ANTES de eliminar
        $this->logger->log_user_permanently_deleted(
            $user_id,
            $user_data->display_name,
            $user_data->user_email
        );

        // Eliminar usuario definitivamente
        require_once ABSPATH . 'wp-admin/includes/user.php';
        $deleted = wp_delete_user( $user_id );

        return $deleted !== false;
    }

    /**
     * Manejar acciones masivas de usuarios (activar, inactivar, eliminar)
     */
    public function handle_bulk_user_actions(): void {
        // Verificar si se envió una acción masiva
        if ( ! isset( $_POST['fplms_bulk_action'] ) || ! isset( $_POST['fplms_bulk_users_nonce'] ) ) {
            return;
        }

        // Verificar nonce
        if ( ! wp_verify_nonce( $_POST['fplms_bulk_users_nonce'], 'fplms_bulk_users_action' ) ) {
            return;
        }

        // Verificar permisos
        if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_USERS ) ) {
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_POST['fplms_bulk_action'] ) );
        $user_ids = isset( $_POST['fplms_bulk_users'] ) ? array_map( 'absint', $_POST['fplms_bulk_users'] ) : [];

        if ( empty( $user_ids ) || ! in_array( $action, [ 'activate', 'deactivate', 'delete' ], true ) ) {
            wp_safe_redirect(
                add_query_arg( [ 'page' => 'fplms-users', 'bulk_error' => 'invalid_request' ], admin_url( 'admin.php' ) )
            );
            exit;
        }

        $success_count = 0;
        $error_count = 0;

        foreach ( $user_ids as $user_id ) {
            $user = get_userdata( $user_id );
            if ( ! $user ) {
                $error_count++;
                continue;
            }

            switch ( $action ) {
                case 'deactivate':
                    update_user_meta( $user_id, 'fplms_user_status', 'inactive' );
                    update_user_meta( $user_id, 'fplms_deactivated_date', current_time( 'mysql' ) );
                    update_user_meta( $user_id, 'fplms_deactivated_by', get_current_user_id() );
                    $this->logger->log_user_deactivated( $user_id, $user->display_name, $user->user_email );
                    $success_count++;
                    break;

                case 'activate':
                    update_user_meta( $user_id, 'fplms_user_status', 'active' );
                    update_user_meta( $user_id, 'fplms_reactivated_date', current_time( 'mysql' ) );
                    update_user_meta( $user_id, 'fplms_reactivated_by', get_current_user_id() );
                    $this->logger->log_user_reactivated( $user_id, $user->display_name, $user->user_email );
                    $success_count++;
                    break;

                case 'delete':
                    // Verificar que no se elimine el usuario actual
                    if ( $user_id === get_current_user_id() ) {
                        $error_count++;
                        continue 2;
                    }
                    
                    // Registrar en bitácora antes de eliminar
                    $this->logger->log_user_permanently_deleted( $user_id, $user->display_name, $user->user_email );
                    
                    // Eliminar permanentemente
                    require_once( ABSPATH . 'wp-admin/includes/user.php' );
                    $result = wp_delete_user( $user_id );
                    
                    if ( $result ) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                    break;
            }
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'fplms-users',
                    'bulk_success' => $success_count,
                    'bulk_error' => $error_count,
                    'bulk_action' => $action,
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    /**
     * Renderiza la página dedicada para crear un nuevo usuario (misma apariencia que editar).
     */
    private function render_create_user_form(): void {
        if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_USERS ) ) {
            echo '<div class="wrap"><h1>Sin permiso</h1></div>';
            return;
        }

        $cities = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CITY );
        $roles_def_labels = [
            'subscriber'                            => 'Estudiante',
            FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR => 'Docente',
            'administrator'                         => 'Administrador',
        ];

        ?>
        <div class="wrap">
            <h1>➕ Crear Nuevo Usuario</h1>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=fplms-users' ) ); ?>" class="button">← Volver a la lista de usuarios</a></p>

            <?php if ( isset( $_GET['error'] ) ) : ?>
                <div id="message" class="error notice notice-error is-dismissible">
                    <p>
                        <?php
                        $error_msg = sanitize_text_field( wp_unslash( $_GET['error'] ) );
                        $error_messages = [
                            'incomplete_data'    => 'Datos incompletos. Verifica que llenes todos los campos requeridos.',
                            'invalid_id_usuario' => 'IDUsuario inválido. Debe ser alfanumérico y tener máximo 20 caracteres.',
                            'id_usuario_exists'  => 'El IDUsuario ya existe. Por favor, utiliza uno diferente.',
                            'user_exists'        => 'Error al crear el usuario. Verifica que el nombre de usuario o correo no existan.',
                        ];
                        echo isset( $error_messages[ $error_msg ] ) ? esc_html( $error_messages[ $error_msg ] ) : 'Error al crear el usuario.';
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <style>
                /* ── Variables del sistema de diseño ── */
                .fplms-edit-user-container  { --clr-primary: #667eea; --clr-primary-dark: #764ba2; }

                /* ── Layout principal ── */
                .fplms-profile-hero {
                    background: linear-gradient(135deg, var(--clr-primary) 0%, var(--clr-primary-dark) 100%);
                    border-radius: 16px;
                    padding: 36px;
                    margin-bottom: 28px;
                    display: flex;
                    align-items: center;
                    gap: 28px;
                    position: relative;
                    overflow: hidden;
                    box-shadow: 0 8px 30px rgba(102,126,234,.35);
                }
                .fplms-profile-hero::after {
                    content: '';
                    position: absolute;
                    right: -60px; top: -60px;
                    width: 260px; height: 260px;
                    border-radius: 50%;
                    background: rgba(255,255,255,.12);
                }
                .fplms-hero-avatar-wrap { position: relative; z-index: 1; cursor: pointer; flex-shrink: 0; }
                .fplms-hero-avatar {
                    width: 110px; height: 110px;
                    border-radius: 50%;
                    border: 4px solid #fff;
                    object-fit: cover;
                    box-shadow: 0 6px 20px rgba(0,0,0,.25);
                    display: block;
                }
                .fplms-hero-avatar-placeholder {
                    width: 110px; height: 110px;
                    border-radius: 50%;
                    border: 4px solid #fff;
                    background: rgba(255,255,255,.25);
                    display: flex;
                    align-items: center; justify-content: center;
                    box-shadow: 0 6px 20px rgba(0,0,0,.25);
                }
                .fplms-hero-avatar-placeholder svg { width: 56px; height: 56px; fill: rgba(255,255,255,.9); }
                .fplms-hero-avatar-overlay {
                    position: absolute; inset: 0;
                    border-radius: 50%;
                    background: rgba(0,0,0,.45);
                    display: flex; flex-direction: column;
                    align-items: center; justify-content: center;
                    gap: 4px; color: #fff; font-size: 11px;
                    opacity: 0; transition: opacity .2s;
                    text-align: center; padding: 8px;
                }
                .fplms-hero-avatar-overlay svg { width: 22px; height: 22px; fill: #fff; }
                .fplms-hero-avatar-wrap:hover .fplms-hero-avatar-overlay { opacity: 1; }
                .fplms-hero-info { flex: 1; color: #fff; position: relative; z-index: 1; }
                .fplms-hero-name { font-size: 26px; font-weight: 700; margin: 0 0 6px; color: #fff; }
                .fplms-hero-chips { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
                .fplms-hero-chip {
                    display: inline-flex; align-items: center; gap: 6px;
                    background: rgba(255,255,255,.2);
                    backdrop-filter: blur(8px);
                    border-radius: 20px;
                    padding: 5px 14px; font-size: 13px; color: #fff;
                }
                .fplms-hero-chip svg { width: 14px; height: 14px; fill: #fff; }

                /* ── Cards del formulario ── */
                .fplms-cards-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
                    gap: 22px;
                }
                .fplms-card {
                    background: #fff;
                    border-radius: 14px;
                    box-shadow: 0 2px 12px rgba(0,0,0,.07);
                    overflow: hidden;
                    transition: transform .2s, box-shadow .2s;
                }
                .fplms-card:hover { transform: translateY(-2px); box-shadow: 0 6px 22px rgba(0,0,0,.10); }
                .fplms-card-header {
                    padding: 18px 22px;
                    background: #fafbff;
                    border-bottom: 1px solid #edf0fb;
                    display: flex; align-items: center; gap: 12px;
                }
                .fplms-card-header-icon {
                    width: 38px; height: 38px;
                    border-radius: 10px;
                    background: linear-gradient(135deg, var(--clr-primary) 0%, var(--clr-primary-dark) 100%);
                    display: flex; align-items: center; justify-content: center;
                    box-shadow: 0 3px 10px rgba(102,126,234,.4);
                }
                .fplms-card-header-icon svg { width: 20px; height: 20px; fill: #fff; }
                .fplms-card-header h3 { margin: 0; font-size: 15px; font-weight: 600; color: #2d3748; }
                .fplms-card-header p  { margin: 2px 0 0; font-size: 12px; color: #6c757d; }
                .fplms-card-body { padding: 22px; }

                /* ── Grupos de campo ── */
                .fplms-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
                .fplms-form-row.full { grid-template-columns: 1fr; }
                .fplms-form-group { display: flex; flex-direction: column; margin-bottom: 18px; }
                .fplms-form-group:last-child { margin-bottom: 0; }

                .fplms-form-group label {
                    display: flex; align-items: center; gap: 6px;
                    font-size: 12px; font-weight: 600; letter-spacing: .4px;
                    text-transform: uppercase; color: #6c757d; margin-bottom: 8px;
                }
                .fplms-form-group label svg { width: 13px; height: 13px; fill: #a0aec0; }
                .fplms-form-group label .required { color: #e53e3e; }

                .fplms-form-group input[type="text"],
                .fplms-form-group input[type="email"],
                .fplms-form-group input[type="password"],
                .fplms-form-group select {
                    width: 100%; padding: 11px 15px;
                    border: 2px solid #e9ecef;
                    border-radius: 9px; font-size: 14px; font-family: inherit;
                    background: #fff; transition: border-color .2s, box-shadow .2s;
                }
                .fplms-form-group input:focus,
                .fplms-form-group select:focus {
                    outline: none; border-color: var(--clr-primary);
                    box-shadow: 0 0 0 4px rgba(102,126,234,.12);
                }
                .fplms-form-group input:disabled {
                    background: #f8f9fa; color: #6c757d; cursor: not-allowed;
                }
                .fplms-form-group small { margin-top: 5px; font-size: 11px; color: #a0aec0; }
                .fplms-form-group .required { color: #e53e3e; }

                /* ── Barra de acciones ── */
                .fplms-form-actions {
                    margin-top: 28px;
                    display: flex; gap: 12px; justify-content: flex-end;
                    padding: 20px 24px;
                    background: #fff;
                    border-radius: 14px;
                    box-shadow: 0 2px 12px rgba(0,0,0,.07);
                }
                .fplms-form-actions button {
                    padding: 11px 28px;
                    border: none; border-radius: 9px;
                    font-size: 14px; font-weight: 600; cursor: pointer;
                    display: inline-flex; align-items: center; gap: 8px;
                    transition: all .2s;
                }
                .fplms-form-actions .button-primary {
                    background: linear-gradient(135deg, var(--clr-primary) 0%, var(--clr-primary-dark) 100%);
                    color: #fff;
                    box-shadow: 0 4px 15px rgba(102,126,234,.4);
                }
                .fplms-form-actions .button-primary:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 6px 20px rgba(102,126,234,.5);
                }
                .fplms-form-actions .button-secondary {
                    background: #f8f9fa; color: #495057; border: 2px solid #e9ecef;
                }
                .fplms-form-actions .button-secondary:hover { background: #e9ecef; }

                /* ── Status toggle ── */
                .fplms-status-row {
                    display: flex; align-items: center; justify-content: space-between;
                    padding: 14px 16px;
                    background: #f8f9fa; border-radius: 9px;
                }
                .fplms-status-label { font-size: 14px; font-weight: 500; color: #333; display: flex; align-items: center; gap: 8px; }
                .fplms-status-label svg { width: 18px; height: 18px; fill: #6c757d; }

                input[type="file"] { display: none; }

                @media (max-width: 900px) {
                    .fplms-profile-hero { flex-direction: column; text-align: center; }
                    .fplms-hero-chips { justify-content: center; }
                    .fplms-cards-grid { grid-template-columns: 1fr; }
                    .fplms-form-row { grid-template-columns: 1fr; }
                }

                /* ── Password toggle ── */
                .fplms-password-wrapper {
                    position: relative;
                    display: flex;
                    align-items: center;
                }
                .fplms-password-wrapper input {
                    flex: 1;
                    padding-right: 46px !important;
                }
                .fplms-password-toggle {
                    position: absolute;
                    right: 12px;
                    top: 50%;
                    transform: translateY(-50%);
                    background: none !important;
                    border: none !important;
                    box-shadow: none !important;
                    padding: 4px;
                    margin: 0;
                    cursor: pointer;
                    color: #a0aec0;
                    line-height: 0;
                    transition: color .2s;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: auto !important;
                    height: auto !important;
                    min-height: unset !important;
                }
                .fplms-password-toggle:hover { color: #667eea; }
                .fplms-password-toggle svg { width: 20px; height: 20px; display: block; }
            </style>

            <div class="fplms-edit-user-container">
                <form method="post" id="form-crear-usuario" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'fplms_new_user_save', 'fplms_new_user_nonce' ); ?>
                    <input type="hidden" name="fplms_new_user_action" value="create_user">
                    <input type="file" id="fplms_user_photo" name="fplms_user_photo" accept="image/*">

                    <!-- ── Hero / encabezado de perfil ── -->
                    <div class="fplms-profile-hero">
                        <div class="fplms-hero-avatar-wrap" id="fplms-image-upload-area">
                            <div class="fplms-hero-avatar-placeholder" id="fplms-avatar-placeholder">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                            </div>
                            <div class="fplms-hero-avatar-overlay">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 16.5l4-4h-3v-9h-2v9H8l4 4zm9 4.5H3v-4.5H1V21a2 2 0 002 2h18a2 2 0 002-2v-4.5h-2V21z"/></svg>
                                Subir foto
                            </div>
                        </div>
                        <div class="fplms-hero-info">
                            <h2 class="fplms-hero-name">Nuevo Usuario</h2>
                            <div class="fplms-hero-chips">
                                <span class="fplms-hero-chip">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg>
                                    Completar datos del formulario
                                </span>
                                <span class="fplms-hero-chip" id="fplms-new-status-chip" style="background:rgba(72,199,142,.35);">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z"/></svg>
                                    Activo
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- ── Grid de tarjetas ── -->
                    <div class="fplms-cards-grid">

                        <!-- Datos personales -->
                        <div class="fplms-card">
                            <div class="fplms-card-header">
                                <div class="fplms-card-header-icon">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                                </div>
                                <div><h3>Datos Personales</h3><p>Nombre y apellido del usuario</p></div>
                            </div>
                            <div class="fplms-card-body">
                                <div class="fplms-form-row">
                                    <div class="fplms-form-group">
                                        <label for="fplms_first_name">Nombre <span class="required">*</span></label>
                                        <input type="text" id="fplms_first_name" name="fplms_first_name" required>
                                    </div>
                                    <div class="fplms-form-group">
                                        <label for="fplms_last_name">Apellido <span class="required">*</span></label>
                                        <input type="text" id="fplms_last_name" name="fplms_last_name" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Credenciales de acceso -->
                        <div class="fplms-card">
                            <div class="fplms-card-header">
                                <div class="fplms-card-header-icon">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                                </div>
                                <div><h3>Credenciales de Acceso</h3><p>Usuario, email y contraseña</p></div>
                            </div>
                            <div class="fplms-card-body">
                                <div class="fplms-form-group">
                                    <label for="fplms_id_usuario">IDUsuario <span class="required">*</span></label>
                                    <input type="text" id="fplms_id_usuario" name="fplms_id_usuario" maxlength="20" pattern="[a-zA-Z0-9]+" title="Solo letras y números, máximo 20 caracteres" required>
                                    <small>Alfanumérico, máximo 20 caracteres.</small>
                                </div>
                                <div class="fplms-form-group">
                                    <label for="fplms_user_login">Nombre de usuario <span class="required">*</span></label>
                                    <input type="text" id="fplms_user_login" name="fplms_user_login" required>
                                </div>
                                <div class="fplms-form-group">
                                    <label for="fplms_user_email">Correo electrónico <span class="required">*</span></label>
                                    <input type="email" id="fplms_user_email" name="fplms_user_email" required>
                                </div>
                                <div class="fplms-form-group">
                                    <label for="fplms_user_pass">Contraseña <span class="required">*</span></label>
                                    <div class="fplms-password-wrapper">
                                        <input type="password" id="fplms_user_pass" name="fplms_user_pass" required>
                                        <button type="button" class="fplms-password-toggle" aria-label="Mostrar/ocultar contraseña" data-target="fplms_user_pass">
                                            <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                            <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Estructura Organizacional -->
                        <div class="fplms-card">
                            <div class="fplms-card-header">
                                <div class="fplms-card-header-icon">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                                </div>
                                <div><h3>Estructura Organizacional</h3><p>Ciudad, empresa, canal y sucursal</p></div>
                            </div>
                            <div class="fplms-card-body">
                                <div class="fplms-form-row">
                                    <div class="fplms-form-group">
                                        <label for="fplms_city">Ciudad</label>
                                        <select name="fplms_city" id="fplms_city">
                                            <option value="">— Sin asignar —</option>
                                            <?php foreach ( $cities as $id => $name ) : ?>
                                                <option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $name ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="fplms-form-group">
                                        <label for="fplms_company">Empresa</label>
                                        <select name="fplms_company" id="fplms_company"><option value="">— Sin asignar —</option></select>
                                    </div>
                                </div>
                                <div class="fplms-form-row">
                                    <div class="fplms-form-group">
                                        <label for="fplms_channel">Canal / Franquicia</label>
                                        <select name="fplms_channel" id="fplms_channel"><option value="">— Sin asignar —</option></select>
                                    </div>
                                    <div class="fplms-form-group">
                                        <label for="fplms_branch">Sucursal</label>
                                        <select name="fplms_branch" id="fplms_branch"><option value="">— Sin asignar —</option></select>
                                    </div>
                                </div>
                                <div class="fplms-form-group">
                                    <label for="fplms_job_role">Cargo</label>
                                    <select name="fplms_job_role" id="fplms_job_role"><option value="">— Sin asignar —</option></select>
                                </div>
                            </div>
                        </div>

                        <!-- Tipo de Usuario y Estado -->
                        <div class="fplms-card">
                            <div class="fplms-card-header">
                                <div class="fplms-card-header-icon">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
                                </div>
                                <div><h3>Tipo de Usuario y Estado</h3><p>Rol asignado y estado de acceso</p></div>
                            </div>
                            <div class="fplms-card-body">
                                <div class="fplms-form-group">
                                    <label for="fplms_user_role">Tipo de usuario <span class="required">*</span></label>
                                    <select name="fplms_user_role" id="fplms_user_role" required>
                                        <option value="">— Seleccionar tipo de usuario —</option>
                                        <?php foreach ( $roles_def_labels as $role_key => $role_label ) : ?>
                                            <option value="<?php echo esc_attr( $role_key ); ?>"><?php echo esc_html( $role_label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="fplms-status-row">
                                    <div class="fplms-status-label" id="fplms-new-status-label">
                                        <svg viewBox="0 0 24 24" width="18" height="18" style="fill:#48c78e;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z"/></svg>
                                        Usuario Activo
                                    </div>
                                    <label style="position:relative;display:inline-block;width:52px;height:28px;cursor:pointer;">
                                        <input type="checkbox" id="fplms_user_active" name="fplms_user_active" value="1" checked style="opacity:0;width:0;height:0;">
                                        <span id="fplms-new-toggle-track" style="position:absolute;inset:0;border-radius:28px;background:#48c78e;transition:background .3s;"></span>
                                        <span id="fplms-new-toggle-thumb" style="position:absolute;top:3px;left:27px;width:22px;height:22px;border-radius:50%;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.25);transition:left .3s;"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                    </div><!-- .fplms-cards-grid -->

                    <!-- ── Botones de acción ── -->
                    <div class="fplms-form-actions">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=fplms-users' ) ); ?>" class="button-secondary" style="padding:11px 28px;border-radius:9px;font-size:14px;font-weight:600;cursor:pointer;background:#f8f9fa;color:#495057;border:2px solid #e9ecef;text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="#495057"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                            Cancelar
                        </a>
                        <button type="submit" class="button-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="#fff"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z"/></svg>
                            Crear Usuario
                        </button>
                    </div>

                </form>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // ── Subida de foto de perfil en el hero ──
                    const uploadArea = document.getElementById('fplms-image-upload-area');
                    const fileInput  = document.getElementById('fplms_user_photo');

                    if (uploadArea && fileInput) {
                        uploadArea.addEventListener('click', function() {
                            fileInput.click();
                        });

                        fileInput.addEventListener('change', function(e) {
                            const file = e.target.files[0];
                            if (file) {
                                if (!file.type.startsWith('image/')) {
                                    alert('Por favor selecciona un archivo de imagen válido.');
                                    this.value = '';
                                    return;
                                }
                                if (file.size > 5 * 1024 * 1024) {
                                    alert('La imagen es demasiado grande. El tamaño máximo es 5MB.');
                                    this.value = '';
                                    return;
                                }
                                const reader = new FileReader();
                                reader.onload = function(event) {
                                    let existing = uploadArea.querySelector('.fplms-hero-avatar');
                                    let placeholder = uploadArea.querySelector('.fplms-hero-avatar-placeholder');
                                    if (existing) {
                                        existing.src = event.target.result;
                                    } else {
                                        if (placeholder) placeholder.remove();
                                        const img = document.createElement('img');
                                        img.src = event.target.result;
                                        img.className = 'fplms-hero-avatar';
                                        img.alt = 'Vista previa';
                                        uploadArea.insertBefore(img, uploadArea.querySelector('.fplms-hero-avatar-overlay'));
                                    }
                                };
                                reader.readAsDataURL(file);
                            }
                        });
                    }

                    // ── Toggle de estado con animación ──
                    const newStatusCb = document.getElementById('fplms_user_active');
                    if (newStatusCb) {
                        const newTrack = document.getElementById('fplms-new-toggle-track');
                        const newThumb = document.getElementById('fplms-new-toggle-thumb');
                        const newLabel = document.getElementById('fplms-new-status-label');
                        const newChip  = document.getElementById('fplms-new-status-chip');
                        newStatusCb.addEventListener('change', function() {
                            if (this.checked) {
                                if (newTrack) newTrack.style.background = '#48c78e';
                                if (newThumb) newThumb.style.left = '27px';
                                if (newLabel) newLabel.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" style="fill:#48c78e;flex-shrink:0;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z"/></svg> Usuario Activo';
                                if (newChip)  { newChip.style.background = 'rgba(72,199,142,.35)'; newChip.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" style="fill:#fff;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z"/></svg> Activo'; }
                            } else {
                                if (newTrack) newTrack.style.background = '#ccc';
                                if (newThumb) newThumb.style.left = '3px';
                                if (newLabel) newLabel.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" style="fill:#fc814a;flex-shrink:0;"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/></svg> Usuario Inactivo';
                                if (newChip)  { newChip.style.background = 'rgba(252,129,74,.35)'; newChip.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" style="fill:#fff;"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/></svg> Inactivo'; }
                            }
                        });
                    }

                    // ── Cascada de selects: Ciudad → Empresa → Canal → Sucursal → Cargo ──
                    const citySelect    = document.getElementById('fplms_city');
                    const companySelect = document.getElementById('fplms_company');
                    const channelSelect = document.getElementById('fplms_channel');
                    const branchSelect  = document.getElementById('fplms_branch');
                    const jobRoleSelect = document.getElementById('fplms_job_role');

                    function updateSelectOptions(parentSelect, childSelect, taxonomy, parentKey) {
                        if (!parentSelect || !childSelect) return;
                        parentSelect.addEventListener('change', function() {
                            const parentValue = this.value;
                            if (!parentValue) {
                                childSelect.innerHTML = '<option value="">— Sin asignar —</option>';
                                resetDescendantSelects(childSelect);
                                return;
                            }
                            childSelect.innerHTML = '<option value="">Cargando...</option>';
                            const formData = new FormData();
                            formData.append('action', 'fplms_get_terms_by_parent');
                            formData.append(parentKey, parentValue);
                            formData.append('taxonomy', taxonomy);
                            fetch(ajaxurl, { method: 'POST', body: formData })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success && data.data) {
                                        let html = '<option value="">— Sin asignar —</option>';
                                        for (const [termId, termName] of Object.entries(data.data)) {
                                            html += '<option value="' + termId + '">' + termName + '</option>';
                                        }
                                        childSelect.innerHTML = html;
                                    } else {
                                        childSelect.innerHTML = '<option value="">— Sin asignar —</option>';
                                    }
                                })
                                .catch(() => {
                                    childSelect.innerHTML = '<option value="">— Sin asignar —</option>';
                                });
                        });
                    }

                    function resetDescendantSelects(fromSelect) {
                        const selectElements = [companySelect, channelSelect, branchSelect, jobRoleSelect];
                        let shouldReset = false;
                        for (const select of selectElements) {
                            if (shouldReset && select) {
                                select.innerHTML = '<option value="">— Sin asignar —</option>';
                            }
                            if (select === fromSelect) shouldReset = true;
                        }
                    }

                    updateSelectOptions(citySelect,    companySelect, '<?php echo FairPlay_LMS_Config::TAX_COMPANY; ?>', 'city_id');
                    updateSelectOptions(companySelect, channelSelect, '<?php echo FairPlay_LMS_Config::TAX_CHANNEL; ?>', 'company_id');
                    updateSelectOptions(channelSelect, branchSelect,  '<?php echo FairPlay_LMS_Config::TAX_BRANCH; ?>',  'channel_id');
                    updateSelectOptions(branchSelect,  jobRoleSelect, '<?php echo FairPlay_LMS_Config::TAX_ROLE; ?>',    'branch_id');

                    // Toggle mostrar/ocultar contraseña
                    document.querySelectorAll('.fplms-password-toggle').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            const input = document.getElementById(this.dataset.target);
                            if (!input) return;
                            const isHidden = input.type === 'password';
                            input.type = isHidden ? 'text' : 'password';
                            this.querySelector('.icon-eye').style.display     = isHidden ? 'none'  : '';
                            this.querySelector('.icon-eye-off').style.display = isHidden ? ''      : 'none';
                        });
                    });
                });
            </script>
        </div>
        <?php
    }
}
