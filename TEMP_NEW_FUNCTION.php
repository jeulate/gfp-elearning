    public function render_users_page(): void {

        if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_USERS ) ) {


            wp_die( 'No tienes permisos para acceder a esta sección.' );
        }

        // Si se solicita edición de un usuario específico, mostrar formulario de edición
        if ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] && isset( $_GET['user_id'] ) ) {
            $this->render_edit_user_form( absint( $_GET['user_id'] ) );
            return;
        }

        // Obtener estructuras para filtros
        $cities    = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CITY );
        $companies = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_COMPANY );
        $channels  = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CHANNEL );
        $branches  = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_BRANCH );
        $roles     = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_ROLE );

        // Parámetros de filtros
        $filter_city    = isset( $_GET['fplms_filter_city'] ) ? absint( $_GET['fplms_filter_city'] ) : 0;
        $filter_company = isset( $_GET['fplms_filter_company'] ) ? absint( $_GET['fplms_filter_company'] ) : 0;
        $filter_channel = isset( $_GET['fplms_filter_channel'] ) ? absint( $_GET['fplms_filter_channel'] ) : 0;
        $filter_branch  = isset( $_GET['fplms_filter_branch'] ) ? absint( $_GET['fplms_filter_branch'] ) : 0;
        $filter_role    = isset( $_GET['fplms_filter_role'] ) ? absint( $_GET['fplms_filter_role'] ) : 0;

        // Construir query de usuarios
        $args = [
            'number' => -1, // Obtener todos, paginación en frontend
            'orderby' => 'registered',
            'order' => 'DESC',
        ];

        // Aplicar filtros de estructura si existen
        $meta_query_clauses = [];

        if ( $filter_city ) {
            $meta_query_clauses[] = [
                'key'     => FairPlay_LMS_Config::USER_META_CITY,
                'value'   => (string) $filter_city,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ];
        }

        if ( $filter_company ) {
            $meta_query_clauses[] = [
                'key'     => FairPlay_LMS_Config::USER_META_COMPANY,
                'value'   => (string) $filter_company,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ];
        }

        if ( $filter_channel ) {
            $meta_query_clauses[] = [
                'key'     => FairPlay_LMS_Config::USER_META_CHANNEL,
                'value'   => (string) $filter_channel,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ];
        }

        if ( $filter_branch ) {
            $meta_query_clauses[] = [
                'key'     => FairPlay_LMS_Config::USER_META_BRANCH,
                'value'   => (string) $filter_branch,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ];
        }

        if ( $filter_role ) {
            $meta_query_clauses[] = [
                'key'     => FairPlay_LMS_Config::USER_META_ROLE,
                'value'   => (string) $filter_role,
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

        $user_query = new WP_User_Query( $args );
        $all_users = $user_query->get_results();

        // Roles y capabilities
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

        $matrix = FairPlay_LMS_Capabilities::get_matrix();
        $can_edit = current_user_can( 'manage_options' );

        ?>
        <style>
            /* === ESTILOS GENERALES === */
            .fplms-users-wrapper {
                background: #f5f7fa;
                min-height: 100vh;
                padding: 20px;
                margin-left: -20px;
            }
            
            .fplms-users-container {
                max-width: 1400px;
                margin: 0 auto;
            }

            /* === HEADER === */
            .fplms-users-header {
                background: white;
                border-radius: 12px;
                padding: 30px;
                margin-bottom: 20px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 20px;
            }

            .fplms-users-title-section {
                display: flex;
                align-items: center;
                gap: 15px;
            }

            .fplms-users-icon {
                font-size: 36px;
            }

            .fplms-users-title {
                margin: 0;
                font-size: 28px;
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
                padding: 12px 24px;
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

            /* === CONTENEDOR PRINCIPAL === */
            .fplms-main-container {
                background: white;
                border-radius: 12px;
                padding: 30px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
                margin-bottom: 20px;
            }

            /* === CONTROLES DE TABLA === */
            .fplms-table-controls {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
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

            /* === TABLA === */
            .fplms-users-table-wrapper {
                overflow-x: auto;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
            }

            .fplms-users-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 14px;
            }

            .fplms-users-table thead {
                background: linear-gradient(135deg, #f5f5f5 0%, #fafafa 100%);
                border-bottom: 2px solid #e0e0e0;
            }

            .fplms-users-table thead th {
                padding: 15px;
                text-align: left;
                font-weight: 600;
                color: #333;
                font-size: 13px;
                text-transform: uppercase;
                border-bottom: 2px solid #e0e0e0;
            }

            .fplms-users-table thead th:first-child {
                width: 50px;
                text-align: center;
            }

            .fplms-users-table tbody tr {
                border-bottom: 1px solid #f0f0f0;
                transition: all 0.2s ease;
            }

            .fplms-users-table tbody tr:hover {
                background-color: #f8f9fa;
            }

            .fplms-users-table tbody td {
                padding: 15px;
                color: #555;
            }

            .fplms-users-table .user-checkbox {
                text-align: center;
            }

            .fplms-users-table .checkbox-input {
                width: 18px;
                height: 18px;
                cursor: pointer;
            }

            .fplms-users-table .user-info {
                font-weight: 600;
                color: #1976d2;
            }

            .fplms-users-table .id-usuario-badge {
                background: #f0f0f0;
                padding: 4px 10px;
                border-radius: 4px;
                font-size: 12px;
                font-family: 'Courier New', monospace;
                color: #555;
                display: inline-block;
            }

            .fplms-users-table .actions-cell {
                text-align: center;
                white-space: nowrap;
            }

            .fplms-users-table .action-link {
                display: inline-block;
                padding: 6px 10px;
                margin: 0 3px;
                text-decoration: none;
                cursor: pointer;
                transition: all 0.2s ease;
                border-radius: 4px;
                font-size: 16px;
            }

            .fplms-users-table .action-link:hover {
                background-color: #e3f2fd;
                transform: scale(1.1);
            }

            /* === PAGINACIÓN === */
            .fplms-pagination {
                display: flex;
                justify-content: center;
                alignitems: center;
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

            /* === FILTROS === */
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

            .fplms-filters-section {
                background: #f8f9fa;
                border-radius: 10px;
                padding: 25px;
                margin-bottom: 20px;
                border: 1px solid #e9ecef;
                display: none;
            }

            .fplms-filters-section.active {
                display: block;
            }

            .fplms-filters-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-top: 15px;
            }

            .fplms-filter-field label {
                display: block;
                font-weight: 600;
                color: #555;
                font-size: 13px;
                margin-bottom: 8px;
            }

            .fplms-filter-field select {
                width: 100%;
                padding: 10px 35px 10px 15px;
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                font-size: 14px;
                cursor: pointer;
                transition: all 0.3s ease;
                background: white url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23333%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E') no-repeat right 10px center;
                background-size: 10px;
                -webkit-appearance: none;
                -moz-appearance: none;
                appearance: none;
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
                margin-top: 15px;
            }

            .fplms-filter-button:hover {
                background: linear-gradient(135deg, #1976D2 0%, #1565C0 100%);
                box-shadow: 0 4px 16px rgba(33, 150, 243, 0.4);
                transform: translateY(-2px);
            }

            /* === SECCIONES OCULTAS === */
            .fplms-hidden-section {
                margin-top: 30px;
                display: none;
            }

            .fplms-hidden-section.active {
                display: block;
            }

            /* === RESPONSIVE === */
            @media (max-width: 768px) {
                .fplms-users-header {
                    flex-direction: column;
                    align-items: flex-start;
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

                .fplms-filters-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>

        <div class="fplms-users-wrapper">
            <div class="fplms-users-container">
                
                <!-- HEADER -->
                <div class="fplms-users-header">
                    <div class="fplms-users-title-section">
                        <div class="fplms-users-icon">👥</div>
                        <h1 class="fplms-users-title">Gestión de Usuarios</h1>
                    </div>
                    <div class="fplms-users-actions">
                        <button class="fplms-action-button" id="btn-crear-usuario">
                            ➕ Crear Usuario
                        </button>
                        <button class="fplms-action-button secondary" id="btn-matriz-privilegios">
                            🔐 Matriz de Privilegios
                        </button>
                    </div>
                </div>

                <!-- NOTIFICACIONES -->
                <?php if ( isset( $_GET['user_created'] ) ) : ?>
                    <div class="notice notice-success is-dismissible" style="margin: 0 0 20px 0;">
                        <p>✓ Usuario creado correctamente. ID: <?php echo esc_html( absint( $_GET['user_created'] ) ); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ( isset( $_GET['bulk_success'] ) ) : ?>
                    <div class="notice notice-success is-dismissible" style="margin: 0 0 20px 0;">
                        <p>✓ Acción completada: <?php echo esc_html( absint( $_GET['bulk_success'] ) ); ?> usuario(s) procesados correctamente.</p>
                    </div>
                <?php endif; ?>

                <?php if ( isset( $_GET['bulk_error'] ) && absint( $_GET['bulk_error'] ) > 0 ) : ?>
                    <div class="notice notice-error is-dismissible" style="margin: 0 0 20px 0;">
                        <p>⚠️ Error: <?php echo esc_html( absint( $_GET['bulk_error'] ) ); ?> usuario(s) no pudieron ser procesados.</p>
                    </div>
                <?php endif; ?>

                <!-- CONTENEDOR PRINCIPAL -->
                <div class="fplms-main-container">
                    
                    <!-- BOTÓN DE FILTROS -->
                    <button class="fplms-filters-toggle" onclick="fplmsToggleFilters()">
                        🔍 Filtros por Estructura
                    </button>

                    <!-- FILTROS (COLAPSABLES) -->
                    <div class="fplms-filters-section" id="fplms-filters" <?php echo ( $filter_city || $filter_company || $filter_channel || $filter_branch || $filter_role ) ? 'class="active"' : ''; ?>>
                        <form method="get" id="fplms-filters-form">
                            <input type="hidden" name="page" value="fplms-users">
                            <div class="fplms-filters-grid">
                                <div class="fplms-filter-field">
                                    <label for="fplms_filter_city">🏙️ Ciudad</label>
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
                                    <label for="fplms_filter_company">🏢 Empresa</label>
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
                                    <label for="fplms_filter_channel">📺 Canal</label>
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
                                    <label for="fplms_filter_branch">🏪 Sucursal</label>
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
                                    <label for="fplms_filter_role">💼 Cargo</label>
                                    <select name="fplms_filter_role" id="fplms_filter_role">
                                        <option value="">Todos</option>
                                        <?php foreach ( $roles as $id => $name ) : ?>
                                            <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $filter_role, $id ); ?>>
                                                <?php echo esc_html( $name ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="fplms-filter-button">🔍 Aplicar Filtros</button>
                            <?php if ( $filter_city || $filter_company || $filter_channel || $filter_branch || $filter_role ) : ?>
                                <a href="?page=fplms-users" class="fplms-filter-button" style="display: inline-block; margin-left: 10px; background: linear-gradient(135deg, #607D8B 0%, #455A64 100%); text-decoration: none; color: white;">
                                    ✖ Limpiar Filtros
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- CONTROLES DE TABLA -->
                    <div class="fplms-table-controls">
                        <div class="fplms-search-box">
                            <input 
                                type="text" 
                                id="fplms-users-search" 
                                class="fplms-search-input" 
                                placeholder="🔍 Buscar por nombre, usuario o IDUsuario..." 
                                onkeyup="fplmsSearchUsers(this.value)"
                            >
                        </div>
                        <div class="fplms-per-page-selector">
                            <label for="fplms-per-page">Mostrar:</label>
                            <select id="fplms-per-page" onchange="fplmsPaginate(1, this.value)">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>

                    <!-- ACCIONES MASIVAS -->
                    <div class="fplms-bulk-actions-container" id="fplms-bulk-actions" style="display: none;">
                        <strong>Usuarios:</strong>
                        <span id="fplms-bulk-count">0 seleccionados</span>
                        <select id="fplms-bulk-action" class="fplms-bulk-select">
                            <option value="">-- Seleccionar acción --</option>
                            <option value="activate">✅ Activar seleccionados</option>
                            <option value="deactivate">❌ Desactivar seleccionados</option>
                            <option value="delete">🗑️ Eliminar seleccionados</option>
                        </select>
                        <button type="button" class="fplms-bulk-apply-btn" onclick="fplmsApplyBulkAction()">
                            Aplicar
                        </button>
                    </div>

                    <!-- TABLA DE USUARIOS -->
                    <div class="fplms-users-table-wrapper">
                        <table class="fplms-users-table" id="fplms-users-table">
                            <thead>
                                <tr>
                                    <th>
                                        <input 
                                            type="checkbox" 
                                            id="fplms-select-all" 
                                            class="checkbox-input" 
                                            onchange="fplmsToggleAllCheckboxes(this.checked)"
                                        >
                                    </th>
                                    <th>Usuario</th>
                                    <th>IDUsuario</th>
                                    <th>Correo</th>
                                    <th>Fecha Registro</th>
                                    <th>Último Login</th>
                                    <th>Estado</th>
                                    <th class="actions-cell">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $all_users ) ) : ?>
                                    <?php foreach ( $all_users as $user ) : ?>
                                        <?php
                                        $user_registered = $user->user_registered;
                                        $last_login = get_user_meta( $user->ID, 'last_login', true );
                                        $last_login_text = $last_login ? date( 'd/m/Y H:i', strtotime( $last_login ) ) : 'Nunca';
                                        $id_usuario = get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_ID_USUARIO, true );
                                        $user_status = get_user_meta( $user->ID, 'user_status', true );
                                        $is_inactive = ( $user_status === 'inactive' );
                                        $full_name = trim( $user->first_name . ' ' . $user->last_name );
                                        if ( empty( $full_name ) ) {
                                            $full_name = $user->user_login;
                                        }
                                        ?>
                                        <tr 
                                            data-user-id="<?php echo esc_attr( $user->ID ); ?>"
                                            data-user-name="<?php echo esc_attr( strtolower( $full_name ) ); ?>"
                                            data-user-login="<?php echo esc_attr( strtolower( $user->user_login ) ); ?>"
                                            data-id-usuario="<?php echo esc_attr( strtolower( $id_usuario ) ); ?>"
                                            data-user-email="<?php echo esc_attr( strtolower( $user->user_email ) ); ?>"
                                            <?php echo $is_inactive ? 'style="opacity: 0.6; background: #fff3cd;"' : ''; ?>
                                        >
                                            <td class="user-checkbox">
                                                <input 
                                                    type="checkbox" 
                                                    class="checkbox-input fplms-user-checkbox" 
                                                    value="<?php echo esc_attr( $user->ID ); ?>"
                                                    onchange="fplmsUpdateBulkCount()"
                                                >
                                            </td>
                                            <td class="user-info">
                                                <?php echo esc_html( $full_name ); ?>
                                                <?php if ( $is_inactive ) : ?>
                                                    <span style="color: #dc3545; font-size: 12px; font-weight: normal;">(Inactivo)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="id-usuario-badge">
                                                    <?php echo esc_html( $id_usuario ?: '—' ); ?>
                                                </span>
                                            </td>
                                            <td><?php echo esc_html( $user->user_email ); ?></td>
                                            <td><?php echo esc_html( date( 'd/m/Y', strtotime( $user_registered ) ) ); ?></td>
                                            <td><?php echo esc_html( $last_login_text ); ?></td>
                                            <td>
                                                <?php if ( $is_inactive ) : ?>
                                                    <span style="color: #dc3545; font-weight: 600;">❌ Inactivo</span>
                                                <?php else : ?>
                                                    <span style="color: #28a745; font-weight: 600;">✅ Activo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="actions-cell">
                                                <a href="<?php echo esc_url( add_query_arg( [ 'action' => 'edit', 'user_id' => $user->ID ], admin_url( 'admin.php?page=fplms-users' ) ) ); ?>" title="Editar usuario" class="action-link">✏️</a>
                                                <a href="<?php echo esc_url( add_query_arg( [ 'user_id' => $user->ID ], admin_url( 'admin.php?page=fplms-progress' ) ) ); ?>" title="Ver progreso" class="action-link">📊</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                                            📭 No se encontraron usuarios con los filtros aplicados.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINACIÓN -->
                    <div class="fplms-pagination" id="fplms-pagination">
                        <!-- Se genera con JavaScript -->
                    </div>

                </div>

                <!-- SECCIÓN MATRIZ DE PRIVILEGIOS (Oculta inicialmente) -->
                <div id="matriz-privilegios" style="margin-top: 40px; display: none;">
                    <h2>Matriz de privilegios</h2>

                    <?php if ( isset( $_GET['updated_caps'] ) ) : ?>
                        <div id="message" class="updated notice notice-success is-dismissible">
                            <p>Matriz de privilegios actualizada correctamente.</p>
                        </div>
                    <?php endif; ?>

                    <?php if ( $can_edit ) : ?>
                    <form method="post">
                        <?php wp_nonce_field( 'fplms_caps_save', 'fplms_caps_nonce' ); ?>
                        <input type="hidden" name="fplms_caps_action" value="save">
                    <?php endif; ?>

                    <table class="widefat striped">
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
                                        <?php
                                        $enabled = isset( $matrix[ $role_key ][ $cap_key ] ) ? (bool) $matrix[ $role_key ][ $cap_key ] : false;
                                        ?>
                                        <td style="text-align:center;">
                                            <?php if ( $can_edit ) : ?>
                                                <input
                                                    type="checkbox"
                                                    name="fplms_caps[<?php echo esc_attr( $role_key ); ?>][<?php echo esc_attr( $cap_key ); ?>]"
                                                    value="1"
                                                    <?php checked( $enabled ); ?>
                                                />
                                            <?php else : ?>
                                                <?php echo $enabled ? '✔' : '✖'; ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ( $can_edit ) : ?>
                        <p class="description" style="margin-top:0.5em;">
                            Solo los administradores pueden modificar esta matriz. Los cambios se aplican directamente a los roles de WordPress.
                        </p>
                        <p class="submit">
                            <button type="submit" class="button button-primary">Guardar matriz de privilegios</button>
</p>
                    </form>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Inicializar paginación
            fplmsPaginate(1, 10);
        });

        /**
         * Toggle de filtros
         */
        function fplmsToggleFilters() {
            const filters = document.getElementById('fplms-filters');
            if (filters) {
                filters.classList.toggle('active');
            }
        }

        /**
         * Búsqueda de usuarios
         */
        function fplmsSearchUsers(query) {
            const table = document.getElementById('fplms-users-table');
            const rows = table.querySelectorAll('tbody tr');
            
            query = query.toLowerCase().trim();
            
            rows.forEach(row => {
                const userName = row.getAttribute('data-user-name') || '';
                const userLogin = row.getAttribute('data-user-login') || '';
                const idUsuario = row.getAttribute('data-id-usuario') || '';
                const userEmail = row.getAttribute('data-user-email') || '';
                
                const matches = !query || 
                               userName.includes(query) || 
                               userLogin.includes(query) || 
                               idUsuario.includes(query) ||
                               userEmail.includes(query);
                
                if (matches) {
                    row.style.display = '';
                    row.removeAttribute('data-filtered');
                } else {
                    row.style.display = 'none';
                    row.setAttribute('data-filtered', 'true');
                }
            });
            
            // Reiniciar paginación
            const perPage = parseInt(document.getElementById('fplms-per-page').value) || 10;
            fplmsPaginate(1, perPage);
        }

        /**
         * Paginación
         */
        function fplmsPaginate(page, perPage) {
            const table = document.getElementById('fplms-users-table');
            const rows = Array.from(table.querySelectorAll('tbody tr'));
            
            // Filtrar solo filas visibles (no filtradas por búsqueda)
            const visibleRows = rows.filter(row => {
                return !row.hasAttribute('data-filtered') || row.getAttribute('data-filtered') !== 'true';
            });
            
            const totalRows = visibleRows.length;
            const totalPages = Math.ceil(totalRows / perPage);
            const startIndex = (page - 1) * perPage;
            const endIndex = startIndex + perPage;
            
            // Ocultar/mostrar filas según la página
            rows.forEach(row => {
                row.style.display = 'none';
            });
            
            visibleRows.forEach((row, index) => {
                if (index >= startIndex && index < endIndex) {
                    row.style.display = '';
                }
            });
            
            // Generar controles de paginación
            const pagination = document.getElementById('fplms-pagination');
            if (!pagination) return;
            
            let html = '';
            
            if (totalPages > 1) {
                // Botón Anterior
                html += '<button class="fplms-pagination-btn" onclick="fplmsPaginate(' + (page - 1) + ', ' + perPage + ')" ' + (page === 1 ? 'disabled' : '') + '>« Anterior</button>';
                
                // Números de página
                for (let i = 1; i <= totalPages; i++) {
                    if (i === 1 || i === totalPages || (i >= page - 2 && i <= page + 2)) {
                        html += '<button class="fplms-pagination-btn ' + (i === page ? 'active' : '') + '" onclick="fplmsPaginate(' + i + ', ' + perPage + ')">' + i + '</button>';
                    } else if (i === page - 3 || i === page + 3) {
                        html += '<span class="fplms-pagination-info">...</span>';
                    }
                }
                
                // Botón Siguiente
                html += '<button class="fplms-pagination-btn" onclick="fplmsPaginate(' + (page + 1) + ', ' + perPage + ')" ' + (page === totalPages ? 'disabled' : '') + '>Siguiente »</button>';
                
                // Info
                html += '<span class="fplms-pagination-info">Página ' + page + ' de ' + totalPages + ' (' + totalRows + ' usuarios)</span>';
            } else if (totalRows > 0) {
                html += '<span class="fplms-pagination-info">' + totalRows + ' usuario(s) encontrado(s)</span>';
            }
            
            pagination.innerHTML = html;
        }

        /**
         * Toggle de todos los checkboxes
         */
        function fplmsToggleAllCheckboxes(checked) {
            const checkboxes = document.querySelectorAll('.fplms-user-checkbox');
            checkboxes.forEach(cb => {
                // Solo marcar los visibles
                const row = cb.closest('tr');
                if (row.style.display !== 'none') {
                    cb.checked = checked;
                }
            });
            fplmsUpdateBulkCount();
        }

        /**
         * Actualizar contador de seleccionados
         */
        function fplmsUpdateBulkCount() {
            const checkboxes = document.querySelectorAll('.fplms-user-checkbox:checked');
            const count = checkboxes.length;
            const countSpan = document.getElementById('fplms-bulk-count');
            const bulkActions = document.getElementById('fplms-bulk-actions');
            
            if (countSpan) {
                countSpan.textContent = count + ' seleccionado' + (count !== 1 ? 's' : '');
            }
            
            if (bulkActions) {
                bulkActions.style.display = count > 0 ? 'flex' : 'none';
            }
            
            // Actualizar checkbox "seleccionar todos"
            const selectAll = document.getElementById('fplms-select-all');
            const visibleCheckboxes = Array.from(document.querySelectorAll('.fplms-user-checkbox')).filter(cb => {
                return cb.closest('tr').style.display !== 'none';
            });
            const allVisibleChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.every(cb => cb.checked);
            if (selectAll) {
                selectAll.checked = allVisibleChecked;
            }
        }

        /**
         * Aplicar acción masiva
         */
        function fplmsApplyBulkAction() {
            const action = document.getElementById('fplms-bulk-action').value;
            const checkboxes = document.querySelectorAll('.fplms-user-checkbox:checked');
            const userIds = Array.from(checkboxes).map(cb => cb.value);
            
            if (!action) {
                alert('Por favor selecciona una acción.');
                return;
            }
            
            if (userIds.length === 0) {
                alert('Por favor selecciona al menos un usuario.');
                return;
            }
            
            // Mensajes de confirmación según la acción
            let modalTitle = '';
            let modalMessage = '';
            let modalColor = '';
            let modalIcon = '';
            
            switch (action) {
                case 'activate':
                    modalTitle = 'Activar Usuarios';
                    modalMessage = '¿Estás seguro de que deseas activar ' + userIds.length + ' usuario(s)?';
                    modalColor = '#28a745';
                    modalIcon = '✅';
                    break;
                case 'deactivate':
                    modalTitle = 'Desactivar Usuarios';
                    modalMessage = '¿Estás seguro de que deseas desactivar ' + userIds.length + ' usuario(s)?';
                    modalColor = '#ffc107';
                    modalIcon = '❌';
                    break;
                case 'delete':
                    modalTitle = 'Eliminar Usuarios';
                    modalMessage = '⚠️ ¿Estás seguro de que deseas eliminar permanentemente ' + userIds.length + ' usuario(s)? Esta acción NO se puede deshacer.';
                    modalColor = '#dc3545';
                    modalIcon = '🗑️';
                    break;
            }
            
            // Mostrar modal de confirmación
            fplmsShowBulkConfirmModal(modalTitle, modalMessage, modalColor, modalIcon, action, userIds);
        }

        /**
         * Mostrar modal de confirmación
         */
        function fplmsShowBulkConfirmModal(title, message, color, icon, action, userIds) {
            const modalHTML = `
                <div id="fplms-bulk-confirm-modal" class="fplms-modal" style="display: flex;">
                    <div class="fplms-modal-content">
                        <div class="fplms-modal-header" style="background: ${color}; color: white;">
                            <h3>${icon} ${title}</h3>
                        </div>
                        <div class="fplms-modal-body">
                            <p>${message}</p>
                        </div>
                        <div class="fplms-modal-footer">
                            <button type="button" class="button" onclick="fplmsCloseBulkModal()">
                                Cancelar
                            </button>
                            <button type="button" class="button button-primary" onclick="fplmsConfirmBulkAction('${action}', [${userIds.join(',')}])" style="background: ${color}; border-color: ${color};">
                                Confirmar
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHTML);
        }

        /**
         * Cerrar modal
         */
        function fplmsCloseBulkModal() {
            const modal = document.getElementById('fplms-bulk-confirm-modal');
            if (modal) {
                modal.remove();
            }
        }

        /**
         * Confirmar y ejecutar acción masiva
         */
        function fplmsConfirmBulkAction(action, userIds) {
            // Cerrar modal
            fplmsCloseBulkModal();
            
            // Crear formulario y enviar
            const form = document.createElement('form');
            form.method = 'post';
            form.action = '';
            
            // Nonce
            const nonceInput = document.createElement('input');
            nonceInput.type = 'hidden';
            nonceInput.name = 'fplms_bulk_user_nonce';
            nonceInput.value = '<?php echo wp_create_nonce( 'fplms_bulk_users' ); ?>';
            form.appendChild(nonceInput);
            
            // Action
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'fplms_bulk_user_action';
            actionInput.value = action;
            form.appendChild(actionInput);
            
            // User IDs
            userIds.forEach(userId => {
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'fplms_user_ids[]';
                idInput.value = userId;
                form.appendChild(idInput);
            });
            
            document.body.appendChild(form);
            form.submit();
        }

        // BOTONES PARA MOSTRAR/OCULTAR SECCIONES
        const btnCrearUsuario = document.getElementById('btn-crear-usuario');
        const btnMatrizPrivilegios = document.getElementById('btn-matriz-privilegios');
        const secCrearUsuario = document.getElementById('crear-usuario');
        const secMatrizPrivilegios = document.getElementById('matriz-privilegios');

        if ( btnCrearUsuario ) {
            btnCrearUsuario.addEventListener('click', function(e) {
                e.preventDefault();
                secCrearUsuario.style.display = secCrearUsuario.style.display === 'none' ? 'block' : 'none';
                secMatrizPrivilegios.style.display = 'none';
                if ( secCrearUsuario.style.display !== 'none' ) {
                    secCrearUsuario.scrollIntoView({ behavior: 'smooth' });
                }
            });
        }

        if ( btnMatrizPrivilegios ) {
            btnMatrizPrivilegios.addEventListener('click', function(e) {
                e.preventDefault();
                secMatrizPrivilegios.style.display = secMatrizPrivilegios.style.display === 'none' ? 'block' : 'none';
                secCrearUsuario.style.display = 'none';
                if ( secMatrizPrivilegios.style.display !== 'none' ) {
                    secMatrizPrivilegios.scrollIntoView({ behavior: 'smooth' });
                }
            });
        }
        </script>
