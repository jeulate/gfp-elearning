<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FairPlay_LMS_Structures_Controller {

    /**
     * Registra las taxonomías internas para estructuras.
     */
    public function register_taxonomies(): void {

        $common_args = [
            'public'       => false,
            'show_ui'      => false,
            'show_in_menu' => false,
            'hierarchical' => false,
        ];

        register_taxonomy(
            FairPlay_LMS_Config::TAX_CITY,
            'post',
            array_merge( $common_args, [ 'label' => 'Ciudades' ] )
        );

        register_taxonomy(
            FairPlay_LMS_Config::TAX_CHANNEL,
            'post',
            array_merge( $common_args, [ 'label' => 'Canales / Franquicias' ] )
        );

        register_taxonomy(
            FairPlay_LMS_Config::TAX_BRANCH,
            'post',
            array_merge( $common_args, [ 'label' => 'Sucursales' ] )
        );

        register_taxonomy(
            FairPlay_LMS_Config::TAX_ROLE,
            'post',
            array_merge( $common_args, [ 'label' => 'Cargos' ] )
        );
    }

    /**
     * Manejo del formulario de estructuras (crear / activar / desactivar).
     */
    public function handle_form(): void {

        if ( ! isset( $_POST['fplms_structures_action'] ) ) {
            return;
        }

        if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_STRUCTURES ) ) {
            return;
        }

        if (
            ! isset( $_POST['fplms_structures_nonce'] ) ||
            ! wp_verify_nonce( $_POST['fplms_structures_nonce'], 'fplms_structures_save' )
        ) {
            return;
        }

        $action   = sanitize_text_field( wp_unslash( $_POST['fplms_structures_action'] ) );
        $taxonomy = sanitize_text_field( wp_unslash( $_POST['fplms_taxonomy'] ?? '' ) );

        $allowed_taxonomies = [
            FairPlay_LMS_Config::TAX_CITY,
            FairPlay_LMS_Config::TAX_CHANNEL,
            FairPlay_LMS_Config::TAX_BRANCH,
            FairPlay_LMS_Config::TAX_ROLE,
        ];

        if ( ! in_array( $taxonomy, $allowed_taxonomies, true ) ) {
            return;
        }

        if ( 'create' === $action ) {

            $name   = sanitize_text_field( wp_unslash( $_POST['fplms_name'] ?? '' ) );
            $active = ! empty( $_POST['fplms_active'] ) ? '1' : '0';

            if ( $name ) {
                $term = wp_insert_term( $name, $taxonomy );
                if ( ! is_wp_error( $term ) ) {
                    update_term_meta( $term['term_id'], FairPlay_LMS_Config::META_ACTIVE, $active );

                    // Guardar múltiples ciudades si viene en el formulario (nuevo sistema)
                    if ( FairPlay_LMS_Config::TAX_CITY !== $taxonomy && ! empty( $_POST['fplms_cities'] ) ) {
                        $city_ids = array_map( 'absint', (array) $_POST['fplms_cities'] );
                        $city_ids = array_filter( $city_ids );

                        if ( ! empty( $city_ids ) ) {
                            $this->save_multiple_cities( $term['term_id'], $city_ids );
                        }
                    }
                }
            }
        }

        if ( 'toggle_active' === $action ) {

            $term_id = isset( $_POST['fplms_term_id'] ) ? absint( $_POST['fplms_term_id'] ) : 0;
            if ( $term_id ) {
                $current = get_term_meta( $term_id, FairPlay_LMS_Config::META_ACTIVE, true );
                $new     = ( '1' === $current ) ? '0' : '1';
                update_term_meta( $term_id, FairPlay_LMS_Config::META_ACTIVE, $new );
            }
        }

        if ( 'edit' === $action ) {

            $term_id = isset( $_POST['fplms_term_id'] ) ? absint( $_POST['fplms_term_id'] ) : 0;
            $name    = sanitize_text_field( wp_unslash( $_POST['fplms_name'] ?? '' ) );

            if ( $term_id && $name ) {
                // Actualizar nombre del término
                wp_update_term( $term_id, $taxonomy, [ 'name' => $name ] );

                // Actualizar múltiples ciudades si viene en el formulario (nuevo sistema)
                if ( FairPlay_LMS_Config::TAX_CITY !== $taxonomy && ! empty( $_POST['fplms_cities'] ) ) {
                    $city_ids = array_map( 'absint', (array) $_POST['fplms_cities'] );
                    $city_ids = array_filter( $city_ids );

                    if ( ! empty( $city_ids ) ) {
                        $this->save_multiple_cities( $term_id, $city_ids );
                    }
                }
            }
        }

        if ( 'delete' === $action ) {

            $term_id = isset( $_POST['fplms_term_id'] ) ? absint( $_POST['fplms_term_id'] ) : 0;

            if ( $term_id ) {
                // Eliminar relaciones de ciudades si existen
                delete_term_meta( $term_id, FairPlay_LMS_Config::META_CITY_RELATIONS );
                
                // Eliminar el término completamente
                wp_delete_term( $term_id, $taxonomy );
            }
        }

        $tab = isset( $_POST['fplms_tab'] ) ? sanitize_text_field( wp_unslash( $_POST['fplms_tab'] ) ) : 'city';
        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'fplms-structures',
                    'tab'  => $tab,
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    /**
     * Página de estructuras (admin).
     */
    public function render_page(): void {

        if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_STRUCTURES ) ) {
            wp_die( 'No tienes permisos para acceder a esta sección.' );
        }

        $tabs = [
            'city'    => [
                'label'    => '📍 Ciudades',
                'icon'     => '📍',
                'taxonomy' => FairPlay_LMS_Config::TAX_CITY,
                'color'    => '#0073aa',
            ],
            'channel' => [
                'label'    => '🏪 Canales / Franquicias',
                'icon'     => '🏪',
                'taxonomy' => FairPlay_LMS_Config::TAX_CHANNEL,
                'color'    => '#00a000',
            ],
            'branch'  => [
                'label'    => '🏢 Sucursales',
                'icon'     => '🏢',
                'taxonomy' => FairPlay_LMS_Config::TAX_BRANCH,
                'color'    => '#ff6f00',
            ],
            'role'    => [
                'label'    => '👔 Cargos',
                'icon'     => '👔',
                'taxonomy' => FairPlay_LMS_Config::TAX_ROLE,
                'color'    => '#7c3aed',
            ],
        ];

        ?>
        <div class="wrap">
            <h1>⚙️ Gestión de Estructuras</h1>
            <p style="font-size: 16px; color: #666; margin-bottom: 30px;">
                Organiza tu empresa en ciudades, canales, sucursales y cargos. Expande cada sección para ver, editar o eliminar elementos.
            </p>

            <div class="fplms-accordion-container">
                <?php foreach ( $tabs as $tab_key => $tab_info ) : ?>
                    <?php
                    $terms = get_terms(
                        [
                            'taxonomy'   => $tab_info['taxonomy'],
                            'hide_empty' => false,
                        ]
                    );
                    ?>
                    <div class="fplms-accordion-item">
                        <div class="fplms-accordion-header" data-tab="<?php echo esc_attr( $tab_key ); ?>" style="border-left: 5px solid <?php echo esc_attr( $tab_info['color'] ); ?>;">
                            <span class="fplms-accordion-icon">▶</span>
                            <span class="fplms-accordion-title">
                                <?php echo esc_html( $tab_info['label'] ); ?>
                                <span class="fplms-accordion-count">( <?php echo count( is_wp_error( $terms ) ? [] : $terms ); ?> )</span>
                            </span>
                        </div>
                        
                        <div class="fplms-accordion-body" style="display: none; border-left: 5px solid <?php echo esc_attr( $tab_info['color'] ); ?>;">
                            <div class="fplms-terms-list">
                                <?php if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) : ?>
                                    <?php foreach ( $terms as $term ) : ?>
                                        <?php
                                        $active = get_term_meta( $term->term_id, FairPlay_LMS_Config::META_ACTIVE, true );
                                        $active = ( '1' === $active );
                                        $city_ids = [];
                                        $city_names = [];
                                        
                                        if ( 'city' !== $tab_key ) {
                                            $city_ids = $this->get_term_cities( $term->term_id );
                                            foreach ( $city_ids as $city_id ) {
                                                $city_name = $this->get_term_name_by_id( $city_id );
                                                if ( $city_name ) {
                                                    $city_names[] = $city_name;
                                                }
                                            }
                                        }
                                        ?>
                                        <div class="fplms-term-item" data-term-id="<?php echo esc_attr( $term->term_id ); ?>" data-active="<?php echo $active ? '1' : '0'; ?>">
                                            <div class="fplms-term-header">
                                                <div class="fplms-term-info">
                                                    <span class="fplms-term-name"><?php echo esc_html( $term->name ); ?></span>
                                                    <?php if ( 'city' !== $tab_key && ! empty( $city_names ) ) : ?>
                                                        <span class="fplms-term-cities">🔗 <?php echo esc_html( implode( ', ', $city_names ) ); ?></span>
                                                    <?php endif; ?>
                                                    <span class="fplms-term-status <?php echo $active ? 'active' : 'inactive'; ?>">
                                                        <?php echo $active ? '✓ Activo' : '✗ Inactivo'; ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="fplms-term-actions">
                                                    <form method="post" style="display:inline;">
                                                        <?php wp_nonce_field( 'fplms_structures_save', 'fplms_structures_nonce' ); ?>
                                                        <input type="hidden" name="fplms_structures_action" value="toggle_active">
                                                        <input type="hidden" name="fplms_taxonomy" value="<?php echo esc_attr( $tab_info['taxonomy'] ); ?>">
                                                        <input type="hidden" name="fplms_term_id" value="<?php echo esc_attr( $term->term_id ); ?>">
                                                        <input type="hidden" name="fplms_tab" value="<?php echo esc_attr( $tab_key ); ?>">
                                                        <button type="submit" class="fplms-btn fplms-btn-toggle" title="<?php echo $active ? 'Desactivar' : 'Activar'; ?>">
                                                            <?php echo $active ? '⊙' : '○'; ?>
                                                        </button>
                                                    </form>
                                                    
                                                    <button type="button" class="fplms-btn fplms-btn-edit" 
                                                        onclick="fplmsToggleEdit(this)"
                                                        title="Editar">
                                                        ✏️
                                                    </button>
                                                    
                                                    <button type="button" class="fplms-btn fplms-btn-delete" 
                                                        onclick="fplmsDeleteStructure(<?php echo esc_attr( $term->term_id ); ?>, '<?php echo esc_attr( $tab_info['taxonomy'] ); ?>', '<?php echo esc_attr( $tab_key ); ?>')"
                                                        title="Eliminar">
                                                        🗑️
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <!-- FORMA DE EDICIÓN INLINE -->
                                            <?php if ( 'city' !== $tab_key ) : ?>
                                            <div class="fplms-term-edit-form" style="display:none; padding: 16px; background: #f5f5f5; border-top: 1px solid #ddd;">
                                                <form method="post" class="fplms-inline-edit-form" onsubmit="return fplmsSubmitEdit(event, this);">
                                                    <?php wp_nonce_field( 'fplms_structures_save', 'fplms_structures_nonce' ); ?>
                                                    <input type="hidden" name="fplms_structures_action" value="edit">
                                                    <input type="hidden" name="fplms_taxonomy" value="<?php echo esc_attr( $tab_info['taxonomy'] ); ?>">
                                                    <input type="hidden" name="fplms_term_id" value="<?php echo esc_attr( $term->term_id ); ?>">
                                                    <input type="hidden" name="fplms_tab" value="<?php echo esc_attr( $tab_key ); ?>">
                                                    
                                                    <div class="fplms-edit-row">
                                                        <div class="fplms-edit-field">
                                                            <label>Nombre</label>
                                                            <input type="text" name="fplms_name" class="regular-text" value="<?php echo esc_attr( $term->name ); ?>" required>
                                                        </div>
                                                        
                                                        <div class="fplms-edit-field fplms-cities-field">
                                                            <label>Ciudades Relacionadas</label>
                                                            <div class="fplms-city-selector">
                                                                <input type="text" class="fplms-city-search" placeholder="🔍 Buscar ciudad..." data-field-id="city_<?php echo esc_attr( $term->term_id ); ?>">
                                                                <div class="fplms-cities-list" id="city_<?php echo esc_attr( $term->term_id ); ?>">
                                                                    <?php
                                                                    $all_cities = $this->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CITY );
                                                                    foreach ( $all_cities as $city_id => $city_name ) :
                                                                        $is_selected = in_array( $city_id, $city_ids, true );
                                                                    ?>
                                                                        <label class="fplms-city-option">
                                                                            <input type="checkbox" name="fplms_cities[]" value="<?php echo esc_attr( $city_id ); ?>" <?php checked( $is_selected ); ?> data-city-name="<?php echo esc_attr( $city_name ); ?>">
                                                                            <span><?php echo esc_html( $city_name ); ?></span>
                                                                        </label>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="fplms-edit-actions">
                                                        <button type="button" class="button" onclick="fplmsToggleEdit(this)">Cancelar</button>
                                                        <button type="submit" class="button button-primary">Guardar Cambios</button>
                                                    </div>
                                                </form>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <div class="fplms-empty-state">
                                        <p>📭 No hay <?php echo esc_html( strtolower( $tab_info['label'] ) ); ?> creadas todavía.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="fplms-new-item-form">
                                <h4>➕ Crear nuevo elemento</h4>
                                <form method="post" class="fplms-inline-form">
                                    <?php wp_nonce_field( 'fplms_structures_save', 'fplms_structures_nonce' ); ?>
                                    <input type="hidden" name="fplms_structures_action" value="create">
                                    <input type="hidden" name="fplms_taxonomy" value="<?php echo esc_attr( $tab_info['taxonomy'] ); ?>">
                                    <input type="hidden" name="fplms_tab" value="<?php echo esc_attr( $tab_key ); ?>">
                                    
                                    <div class="fplms-form-row">
                                        <input name="fplms_name" type="text" class="regular-text" placeholder="Nombre del elemento..." required>
                                        
                                        <?php if ( 'city' !== $tab_key ) : ?>
                                            <div class="fplms-edit-field fplms-cities-field">
                                                <label>Ciudades Asociadas</label>
                                                <div class="fplms-city-selector">
                                                    <input type="text" 
                                                           class="fplms-city-search" 
                                                           placeholder="Buscar ciudades...">
                                                    
                                                    <div class="fplms-cities-list">
                                                        <?php 
                                                        $cities = $this->get_active_terms_for_select('fplms_city');
                                                        foreach ($cities as $city_id => $city_name) : 
                                                        ?>
                                                        <label class="fplms-city-option">
                                                            <input type="checkbox" 
                                                                   name="fplms_cities[]" 
                                                                   value="<?php echo $city_id; ?>"
                                                                   data-city-name="<?php echo esc_attr($city_name); ?>">
                                                            <span><?php echo esc_html($city_name); ?></span>
                                                        </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <label class="fplms-checkbox">
                                            <input name="fplms_active" type="checkbox" value="1" checked>
                                            Activo
                                        </label>
                                        
                                        <button type="submit" class="button button-primary" style="background-color: <?php echo esc_attr( $tab_info['color'] ); ?>; border-color: <?php echo esc_attr( $tab_info['color'] ); ?>;">
                                            Crear
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Formularios de creación integrados en cada acordeón -->
            <!-- Contenedores para Notificaciones -->
            <div id="fplms-success-message"></div>
            <div id="fplms-error-message"></div>

            <!-- Modal de Confirmación de Eliminación -->
            <div id="fplms-delete-modal" class="fplms-modal" style="display:none;">
                <div class="fplms-modal-content" style="max-width: 400px;">
                    <div class="fplms-modal-header">
                        <h3>🗑️ Confirmar Eliminación</h3>
                        <button class="fplms-modal-close" onclick="fplmsCloseDeleteModal()">✕</button>
                    </div>
                    <div class="fplms-modal-body">
                        <p>¿Estás seguro de que deseas eliminar este elemento?</p>
                        <p style="color: #c00; font-weight: bold;" id="fplms_delete_name"></p>
                        <p style="color: #666; font-size: 12px;">Esta acción no se puede deshacer.</p>
                    </div>
                    <div class="fplms-modal-footer">
                        <button type="button" class="button" onclick="fplmsCloseDeleteModal()">Cancelar</button>
                        <button type="button" class="button button-primary" style="background-color: #c00; border-color: #c00;" onclick="fplmsConfirmDelete()">Eliminar Definitivamente</button>
                    </div>
                </div>
            </div>

            <style>
            /* NOTIFICACIÓN DE ÉXITO - Mejorada */
            .fplms-success-notice {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 99999;
                min-width: 350px;
                max-width: 500px;
                animation: slideInRight 0.4s ease;
            }

            .fplms-success-notice .fplms-notice-content {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                padding: 16px 20px;
                background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
                border: 2px solid #28a745;
                border-radius: 8px;
                box-shadow: 0 8px 24px rgba(40, 167, 69, 0.25);
                color: #155724;
                font-weight: 600;
                font-size: 14px;
            }

            .fplms-notice-icon {
                font-size: 22px;
                display: flex;
                align-items: center;
                flex-shrink: 0;
                color: #28a745;
                font-weight: bold;
            }

            .fplms-notice-text {
                flex: 1;
                line-height: 1.4;
                padding-top: 2px;
            }

            .fplms-notice-close {
                background: none;
                border: none;
                cursor: pointer;
                font-size: 20px;
                color: #155724;
                padding: 0;
                transition: transform 0.2s ease;
                flex-shrink: 0;
            }

            .fplms-notice-close:hover {
                transform: scale(1.2);
            }

            /* NOTIFICACIÓN DE ERROR */
            .fplms-error-notice {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 99999;
                min-width: 350px;
                max-width: 500px;
                animation: slideInRight 0.4s ease;
            }

            .fplms-error-notice .fplms-notice-content {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                padding: 16px 20px;
                background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
                border: 2px solid #dc3545;
                border-radius: 8px;
                box-shadow: 0 8px 24px rgba(220, 53, 69, 0.25);
                color: #721c24;
                font-weight: 600;
                font-size: 14px;
            }

            .fplms-error-notice .fplms-notice-icon {
                color: #dc3545;
            }

            /* Animación mejorada */
            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(400px) scale(0.9);
                }
                to {
                    opacity: 1;
                    transform: translateX(0) scale(1);
                }
            }

            /* Animación de salida */
            @keyframes slideOutRight {
                from {
                    opacity: 1;
                    transform: translateX(0) scale(1);
                }
                to {
                    opacity: 0;
                    transform: translateX(400px) scale(0.9);
                }
            }

            .fplms-notice-closing {
                animation: slideOutRight 0.3s ease forwards;
            }

            /* FORMULARIO INLINE DE EDICIÓN */
            .fplms-term-edit-form {
                padding: 16px;
                background: #f5f5f5;
                border-top: 1px solid #ddd;
                animation: slideDown 0.3s ease;
            }

            .fplms-inline-edit-form {
                display: flex;
                flex-direction: column;
                gap: 16px;
            }

            .fplms-edit-row {
                display: flex;
                gap: 16px;
                flex-wrap: wrap;
            }

            .fplms-edit-field {
                flex: 1;
                min-width: 250px;
            }

            .fplms-edit-field label {
                display: block;
                font-weight: 600;
                margin-bottom: 6px;
                color: #333;
                font-size: 13px;
            }

            .fplms-edit-field input[type="text"] {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 13px;
            }

            .fplms-edit-field input[type="text"]:focus {
                outline: none;
                border-color: #0073aa;
                box-shadow: 0 0 0 2px rgba(0,115,170,0.1);
            }

            /* SELECTOR DE CIUDADES */
            .fplms-city-selector {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .fplms-city-search {
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 13px;
                width: 100%;
            }

            .fplms-city-search:focus {
                outline: none;
                border-color: #0073aa;
                box-shadow: 0 0 0 2px rgba(0,115,170,0.1);
            }

            .fplms-cities-list {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                max-height: 200px;
                overflow-y: auto;
                padding: 8px;
                background: white;
                border: 1px solid #ddd;
                border-radius: 4px;
            }

            .fplms-city-option {
                display: flex;
                align-items: center;
                gap: 6px;
                padding: 6px 10px;
                background: white;
                border: 1px solid #ddd;
                border-radius: 3px;
                cursor: pointer;
                font-size: 12px;
                transition: all 0.2s ease;
                white-space: nowrap;
            }

            .fplms-city-option:hover {
                background: #f0f0f0;
                border-color: #0073aa;
            }

            .fplms-city-option input[type="checkbox"] {
                margin: 0;
                cursor: pointer;
            }

            .fplms-city-option input[type="checkbox"]:checked {
                accent-color: #0073aa;
            }

            .fplms-city-option input[type="checkbox"]:checked + span {
                color: #0073aa;
                font-weight: 600;
            }

            .fplms-cities-field {
                flex: 2;
                min-width: 300px;
            }

            /* ACCIONES DE EDICIÓN */
            .fplms-edit-actions {
                display: flex;
                gap: 10px;
                justify-content: flex-end;
            }

            .fplms-edit-actions .button {
                padding: 8px 16px;
                font-size: 13px;
            }

            /* RESPONSIVE PARA EDICIÓN */
            @media (max-width: 768px) {
                .fplms-edit-row {
                    flex-direction: column;
                }

                .fplms-edit-field,
                .fplms-cities-field {
                    min-width: auto;
                    flex: 1 !important;
                }

                .fplms-edit-actions {
                    flex-direction: column;
                    gap: 8px;
                }

                .fplms-edit-actions .button {
                    width: 100%;
                }

                .fplms-notice-content {
                    right: 10px;
                    left: 10px;
                }
            }

            /* CONTENEDOR ACORDEÓN */
            .fplms-accordion-container {
                max-width: 1200px;
                margin: 20px 0;
            }

            /* ITEM DEL ACORDEÓN */
            .fplms-accordion-item {
                margin-bottom: 15px;
                border: 1px solid #ddd;
                border-radius: 6px;
                overflow: hidden;
                background: #fff;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                transition: all 0.3s ease;
            }

            .fplms-accordion-item:hover {
                box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            }

            /* ENCABEZADO DEL ACORDEÓN */
            .fplms-accordion-header {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 16px 20px;
                cursor: pointer;
                background: linear-gradient(135deg, #f5f5f5 0%, #f9f9f9 100%);
                user-select: none;
                transition: all 0.3s ease;
                font-weight: 600;
                font-size: 15px;
            }

            .fplms-accordion-header:hover {
                background: linear-gradient(135deg, #e8e8e8 0%, #efefef 100%);
            }

            .fplms-accordion-icon {
                display: inline-block;
                transition: transform 0.3s ease;
                font-size: 12px;
                color: #666;
            }

            .fplms-accordion-item.active .fplms-accordion-icon {
                transform: rotate(90deg);
            }

            .fplms-accordion-title {
                display: flex;
                align-items: center;
                gap: 8px;
                flex: 1;
                color: #333;
            }

            .fplms-accordion-count {
                background: #f0f0f0;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 12px;
                color: #666;
                font-weight: normal;
            }

            /* CUERPO DEL ACORDEÓN */
            .fplms-accordion-body {
                padding: 20px;
                border-top: 1px solid #eee;
                background: #fafafa;
                animation: slideDown 0.3s ease;
            }

            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            /* LISTA DE TÉRMINOS */
            .fplms-terms-list {
                margin-bottom: 20px;
            }

            /* ITEM DE TÉRMINO */
            .fplms-term-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 12px 16px;
                margin-bottom: 8px;
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                transition: all 0.2s ease;
            }

            .fplms-term-item:hover {
                background: #f9f9f9;
                border-color: #bbb;
                transform: translateX(2px);
            }

            .fplms-term-item.active {
                background: #e3f2fd;
                border-left: 4px solid #0073aa;
                padding-left: 12px;
            }

            /* ENCABEZADO DEL TÉRMINO */
            .fplms-term-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                width: 100%;
                gap: 20px;
            }

            .fplms-term-info {
                display: flex;
                align-items: center;
                gap: 12px;
                flex: 1;
            }

            .fplms-term-name {
                font-weight: 600;
                color: #333;
                min-width: 150px;
            }

            .fplms-term-cities {
                font-size: 12px;
                color: #666;
                background: #f0f0f0;
                padding: 2px 8px;
                border-radius: 3px;
                flex: 1;
            }

            .fplms-term-status {
                font-size: 11px;
                padding: 4px 10px;
                border-radius: 12px;
                font-weight: 600;
                white-space: nowrap;
            }

            .fplms-term-status.active {
                background: #d4edda;
                color: #155724;
            }

            .fplms-term-status.inactive {
                background: #f8d7da;
                color: #721c24;
            }

            /* ACCIONES DEL TÉRMINO */
            .fplms-term-actions {
                display: flex;
                gap: 6px;
                flex-shrink: 0;
            }

            .fplms-btn {
                padding: 6px 12px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                transition: all 0.2s ease;
                background: #f0f0f0;
                color: #333;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 32px;
                height: 32px;
            }

            .fplms-btn:hover {
                background: #e0e0e0;
                transform: scale(1.1);
            }

            .fplms-btn-toggle {
                background: #e8f5e9;
                color: #2e7d32;
            }

            .fplms-btn-toggle:hover {
                background: #c8e6c9;
            }

            .fplms-btn-edit {
                background: #e3f2fd;
                color: #1565c0;
            }

            .fplms-btn-edit:hover {
                background: #bbdefb;
            }

            .fplms-btn-edit.fplms-cancel-edit {
                background: #ffe0b2;
                color: #e65100;
            }

            .fplms-btn-edit.fplms-cancel-edit:hover {
                background: #ffd54f;
            }

            .fplms-btn-delete {
                background: #ffebee;
                color: #c62828;
            }

            .fplms-btn-delete:hover {
                background: #ffcdd2;
            }

            /* ESTADO VACÍO */
            .fplms-empty-state {
                text-align: center;
                padding: 40px 20px;
                color: #999;
                font-style: italic;
            }

            /* FORMULARIO DE NUEVO ELEMENTO */
            .fplms-new-item-form {
                background: #f5f5f5;
                padding: 16px;
                border-radius: 4px;
                border: 2px dashed #ddd;
                margin-top: 20px;
            }

            .fplms-new-item-form h4 {
                margin-top: 0;
                margin-bottom: 12px;
                color: #333;
                font-size: 14px;
            }

            .fplms-form-row {
                display: flex;
                gap: 12px;
                align-items: flex-end;
                flex-wrap: wrap;
            }

            .fplms-form-row input[type="text"] {
                flex: 1;
                min-width: 200px;
            }

            .fplms-form-row .fplms-multiselect-wrapper {
                flex: 1;
                min-width: 200px;
            }

            /* Selector de ciudades en formulario de creación */
            .fplms-form-row .fplms-cities-field {
                flex: 1;
                min-width: 250px;
                margin: 0;
            }

            .fplms-form-row .fplms-city-selector {
                max-height: 180px;
            }

            .fplms-form-row .fplms-cities-list {
                max-height: 150px;
            }

            .fplms-checkbox {
                display: flex;
                align-items: center;
                gap: 6px;
                white-space: nowrap;
                margin: 0;
            }

            .fplms-checkbox input[type="checkbox"] {
                margin: 0;
                cursor: pointer;
            }

            .fplms-form-group {
                margin-bottom: 16px;
            }

            .fplms-form-group label {
                display: block;
                margin-bottom: 6px;
                font-weight: 600;
                color: #333;
                font-size: 13px;
            }

            /* MODAL */
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
                z-index: 10000;
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
                background: linear-gradient(135deg, #f9f9f9 0%, #f5f5f5 100%);
            }

            .fplms-modal-header h3 {
                margin: 0;
                color: #333;
                font-size: 18px;
            }

            .fplms-modal-close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #666;
                transition: color 0.2s ease;
            }

            .fplms-modal-close:hover {
                color: #333;
            }

            .fplms-modal-body {
                padding: 20px;
            }

            .fplms-modal-footer {
                padding: 16px 20px;
                border-top: 1px solid #eee;
                display: flex;
                gap: 10px;
                justify-content: flex-end;
                background: #f9f9f9;
            }

            .fplms-modal-footer .button {
                min-width: 100px;
            }

            /* MULTISELECT */
            .fplms-multiselect-wrapper {
                position: relative;
                width: 100%;
            }

            .fplms-multiselect {
                display: none;
            }

            .fplms-multiselect-display {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: #fff;
                min-height: 36px;
                cursor: pointer;
                font-size: 13px;
                transition: all 0.2s ease;
            }

            .fplms-multiselect-display:hover {
                border-color: #0073aa;
            }

            .fplms-multiselect-tag {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 4px 8px;
                background: #0073aa;
                color: #fff;
                border-radius: 3px;
                font-size: 12px;
                white-space: nowrap;
            }

            @media (max-width: 768px) {
                .fplms-term-header {
                    flex-direction: column;
                    align-items: flex-start;
                }

                .fplms-term-actions {
                    width: 100%;
                    justify-content: flex-start;
                }

                .fplms-form-row {
                    flex-direction: column;
                }

                .fplms-form-row input[type="text"],
                .fplms-form-row .fplms-multiselect-wrapper {
                    width: 100%;
                    min-width: auto;
                }
            }
            </style>

            <script>
            // Manejo del Acordeón
            document.addEventListener('DOMContentLoaded', function() {
                const headers = document.querySelectorAll('.fplms-accordion-header');
                
                headers.forEach(header => {
                    header.addEventListener('click', function() {
                        const item = this.parentElement;
                        const body = item.querySelector('.fplms-accordion-body');
                        const isActive = item.classList.contains('active');

                        // Cerrar otros accordeones
                        document.querySelectorAll('.fplms-accordion-item.active').forEach(activeItem => {
                            activeItem.classList.remove('active');
                            activeItem.querySelector('.fplms-accordion-body').style.display = 'none';
                        });

                        // Abrir/cerrar este acordeón
                        if (!isActive) {
                            item.classList.add('active');
                            body.style.display = 'block';
                        }
                    });
                });

                // Inicializar multiselects
                initializeMultiSelects();
            });

            function initializeMultiSelects() {
                const wrappers = document.querySelectorAll('.fplms-multiselect-wrapper');
                wrappers.forEach(wrapper => {
                    const select = wrapper.querySelector('.fplms-multiselect');
                    const display = wrapper.querySelector('.fplms-multiselect-display');
                    
                    if (select && display) {
                        select.addEventListener('change', () => updateMultiSelectDisplay(wrapper));
                        updateMultiSelectDisplay(wrapper);
                    }
                });
            }

            function updateMultiSelectDisplay(wrapper) {
                const select = wrapper.querySelector('.fplms-multiselect');
                const display = wrapper.querySelector('.fplms-multiselect-display');
                const selected = Array.from(select.options).filter(opt => opt.selected);

                display.innerHTML = selected.map(opt => 
                    `<span class="fplms-multiselect-tag">${opt.textContent}</span>`
                ).join('');
            }

            // Funciones de Modal
            let deleteData = { termId: null, taxonomy: null, tab: null };

            function fplmsEditStructure(termId, termName, cityIds, taxonomy) {
                document.getElementById('fplms_edit_term_id').value = termId;
                document.getElementById('fplms_edit_name').value = termName;
                document.getElementById('fplms_edit_taxonomy').value = taxonomy;
                
                const cityGroup = document.getElementById('fplms_edit_city_group');
                const citySelect = document.getElementById('fplms_edit_cities');
                
                if (taxonomy !== 'fplms_city') {
                    cityGroup.style.display = 'block';
                    Array.from(citySelect.options).forEach(opt => opt.selected = false);
                    
                    if (cityIds && Array.isArray(cityIds) && cityIds.length > 0) {
                        cityIds.forEach(cityId => {
                            const option = citySelect.querySelector(`option[value="${cityId}"]`);
                            if (option) option.selected = true;
                        });
                    }
                    
                    const wrapper = citySelect.closest('.fplms-multiselect-wrapper');
                    setTimeout(() => updateMultiSelectDisplay(wrapper), 100);
                } else {
                    cityGroup.style.display = 'none';
                }
                
                document.getElementById('fplms-edit-modal').style.display = 'flex';
            }

            function fplmsCloseEditModal() {
                document.getElementById('fplms-edit-modal').style.display = 'none';
            }

            function fplmsDeleteStructure(termId, taxonomy, tab) {
                const termName = event.target.closest('.fplms-term-item').querySelector('.fplms-term-name').textContent;
                deleteData = { termId, taxonomy, tab };
                document.getElementById('fplms_delete_name').textContent = `"${termName}"`;
                document.getElementById('fplms-delete-modal').style.display = 'flex';
            }

            function fplmsCloseDeleteModal() {
                document.getElementById('fplms-delete-modal').style.display = 'none';
            }

            function fplmsConfirmDelete() {
                if (!deleteData.termId) return;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <?php wp_nonce_field( 'fplms_structures_save', 'fplms_structures_nonce' ); ?>
                    <input type="hidden" name="fplms_structures_action" value="delete">
                    <input type="hidden" name="fplms_taxonomy" value="${deleteData.taxonomy}">
                    <input type="hidden" name="fplms_term_id" value="${deleteData.termId}">
                    <input type="hidden" name="fplms_tab" value="${deleteData.tab}">
                `;
                document.body.appendChild(form);
                form.submit();
            }

            // Cerrar modales al hacer clic fuera
            window.addEventListener('click', function(event) {
                const editModal = document.getElementById('fplms-edit-modal');
                const deleteModal = document.getElementById('fplms-delete-modal');
                
                if (event.target === editModal) editModal.style.display = 'none';
                if (event.target === deleteModal) deleteModal.style.display = 'none';
            });

            /* ==================== FUNCIONES DE EDICIÓN INLINE ==================== */

            /**
             * Alterna la visibilidad del formulario de edición inline
             */
            function fplmsToggleEdit(button) {
                const termItem = button.closest('.fplms-term-item');
                const editForm = termItem.querySelector('.fplms-term-edit-form');
                const header = termItem.querySelector('.fplms-term-header');
                
                if (editForm.style.display === 'none' || !editForm.style.display) {
                    editForm.style.display = 'block';
                    button.textContent = 'Cancelar';
                    button.classList.add('fplms-cancel-edit');
                } else {
                    editForm.style.display = 'none';
                    button.textContent = 'Editar Estructura';
                    button.classList.remove('fplms-cancel-edit');
                }
            }

            /**
             * Filtra la lista de ciudades según el término de búsqueda
             */
            function fplmsFilterCities(searchInput) {
                const cityList = searchInput.parentElement.querySelector('.fplms-cities-list');
                const searchTerm = searchInput.value.toLowerCase();
                const cityOptions = cityList.querySelectorAll('.fplms-city-option');

                cityOptions.forEach(option => {
                    const cityName = option.textContent.toLowerCase();
                    if (cityName.includes(searchTerm)) {
                        option.style.display = 'flex';
                    } else {
                        option.style.display = 'none';
                    }
                });
            }

            /**
             * Envía el formulario de edición inline
             */
            function fplmsSubmitEdit(form, event) {
                if (event) event.preventDefault();

                const termItem = form.closest('.fplms-term-item');
                const termId = form.querySelector('input[name="fplms_edit_term_id"]').value;
                const termName = form.querySelector('input[name="fplms_edit_name"]').value;
                const taxonomy = form.querySelector('input[name="fplms_edit_taxonomy"]').value;
                
                // Obtener ciudades seleccionadas
                const selectedCities = Array.from(form.querySelectorAll('.fplms-city-option input[type="checkbox"]:checked'))
                    .map(checkbox => checkbox.value);

                // Validación básica
                if (!termName.trim()) {
                    alert('Por favor, ingresa un nombre para la estructura');
                    return;
                }

                // Crear formulario para envío
                const submitForm = document.createElement('form');
                submitForm.method = 'POST';
                submitForm.style.display = 'none';
                
                let nonceField = form.querySelector('input[name="fplms_structures_nonce"]');
                let nonce = nonceField ? nonceField.value : '';

                submitForm.innerHTML = `
                    <input type="hidden" name="fplms_structures_action" value="save">
                    <input type="hidden" name="fplms_structures_nonce" value="${nonce}">
                    <input type="hidden" name="fplms_edit_term_id" value="${termId}">
                    <input type="hidden" name="fplms_edit_name" value="${termName}">
                    <input type="hidden" name="fplms_edit_taxonomy" value="${taxonomy}">
                    <input type="hidden" name="fplms_tab" value="${taxonomy}">
                    ${selectedCities.map((cityId, index) => 
                        `<input type="hidden" name="fplms_edit_cities[${index}]" value="${cityId}">`
                    ).join('')}
                `;

                document.body.appendChild(submitForm);
                
                // Mostrar mensaje de éxito
                const cityText = selectedCities.length > 0 
                    ? ` con ${selectedCities.length} ciudad(es) relacionada(s)` 
                    : '';
                fplmsShowSuccess(`✓ Cambio guardado: "${termName}"${cityText}`);

                // Cerrar el formulario
                const editForm = termItem.querySelector('.fplms-term-edit-form');
                editForm.style.display = 'none';
                
                const editButton = termItem.querySelector('.fplms-term-header .fplms-btn-edit');
                if (editButton) {
                    editButton.textContent = 'Editar Estructura';
                    editButton.classList.remove('fplms-cancel-edit');
                }

                // Enviar formulario
                setTimeout(() => submitForm.submit(), 300);
            }

            /**
             * Muestra un mensaje de éxito con duración extendida
             */
            function fplmsShowSuccess(message) {
                const container = document.getElementById('fplms-success-message');
                
                if (!container) return;

                const noticeHTML = `
                    <div class="fplms-success-notice">
                        <div class="fplms-notice-content">
                            <span class="fplms-notice-icon">✓</span>
                            <span class="fplms-notice-text">${message}</span>
                            <button type="button" class="fplms-notice-close" onclick="fplmsCloseNotice(this.closest('.fplms-success-notice'))">×</button>
                        </div>
                    </div>
                `;

                container.insertAdjacentHTML('beforeend', noticeHTML);

                // Auto-cerrar después de 8 segundos (aumentado de 4)
                const notice = container.querySelector('.fplms-success-notice:last-child');
                const autoCloseTimer = setTimeout(() => {
                    if (notice && notice.parentElement) {
                        fplmsCloseNoticeWithAnimation(notice);
                    }
                }, 8000);

                // Cancelar auto-cierre si el usuario cierra manualmente
                const closeBtn = notice.querySelector('.fplms-notice-close');
                closeBtn.addEventListener('click', () => {
                    clearTimeout(autoCloseTimer);
                });
            }

            /**
             * Muestra un mensaje de error
             */
            function fplmsShowError(message) {
                const container = document.getElementById('fplms-error-message');
                
                if (!container) return;

                const noticeHTML = `
                    <div class="fplms-error-notice">
                        <div class="fplms-notice-content">
                            <span class="fplms-notice-icon">⚠</span>
                            <span class="fplms-notice-text">${message}</span>
                            <button type="button" class="fplms-notice-close" onclick="fplmsCloseNotice(this.closest('.fplms-error-notice'))">×</button>
                        </div>
                    </div>
                `;

                container.insertAdjacentHTML('beforeend', noticeHTML);

                // Auto-cerrar después de 10 segundos (más tiempo que éxito)
                const notice = container.querySelector('.fplms-error-notice:last-child');
                const autoCloseTimer = setTimeout(() => {
                    if (notice && notice.parentElement) {
                        fplmsCloseNoticeWithAnimation(notice);
                    }
                }, 10000);

                // Cancelar auto-cierre si el usuario cierra manualmente
                const closeBtn = notice.querySelector('.fplms-notice-close');
                closeBtn.addEventListener('click', () => {
                    clearTimeout(autoCloseTimer);
                });
            }

            /**
             * Cierra una notificación con animación
             */
            function fplmsCloseNoticeWithAnimation(noticeElement) {
                if (noticeElement) {
                    noticeElement.classList.add('fplms-notice-closing');
                    setTimeout(() => {
                        if (noticeElement.parentElement) {
                            noticeElement.remove();
                        }
                    }, 300);
                }
            }

            /**
             * Cierra una notificación (llamada por botón close)
             */
            function fplmsCloseNotice(noticeElement) {
                if (noticeElement) {
                    fplmsCloseNoticeWithAnimation(noticeElement);
                }
            }

            /**
             * Versión anterior para compatibilidad
             */
            function fplmsCloseSuccess(noticeElement) {
                fplmsCloseNotice(noticeElement);
            }

            /**
             * Inicializa los controles de búsqueda de ciudades
             */
            document.addEventListener('DOMContentLoaded', function() {
                const citySearches = document.querySelectorAll('.fplms-city-search');
                
                citySearches.forEach(searchInput => {
                    searchInput.addEventListener('keyup', function(e) {
                        fplmsFilterCities(this);
                    });

                    // Permitir búsqueda inmediata
                    searchInput.addEventListener('input', function(e) {
                        fplmsFilterCities(this);
                    });
                });

                // Manejador para cambios en checkboxes de ciudades
                const cityCheckboxes = document.querySelectorAll('.fplms-city-option input[type="checkbox"]');
                
                cityCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        // Aquí puedes agregar lógica adicional si es necesaria
                        // Por ejemplo, actualizar contador de ciudades seleccionadas
                    });
                });
            });
            </script>
        </div>
        <?php
    }

    /**
     * Devuelve términos activos para un select.
     */
    public function get_active_terms_for_select( string $taxonomy ): array {

        $terms = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
            ]
        );

        $result = [];

        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $active = get_term_meta( $term->term_id, FairPlay_LMS_Config::META_ACTIVE, true );
                if ( '1' === $active || '' === $active ) {
                    $result[ $term->term_id ] = $term->name;
                }
            }
        }

        return $result;
    }

    /**
     * Nombre de término por ID (o string vacío).
     */
    public function get_term_name_by_id( $term_id ): string {
        $term_id = absint( $term_id );
        if ( ! $term_id ) {
            return '';
        }
        $term = get_term( $term_id );
        if ( $term && ! is_wp_error( $term ) ) {
            return $term->name;
        }
        return '';
    }

    /**
     * Guarda la relación jerárquica entre estructuras.
     * Ejemplo: Asignar un Canal a una Ciudad
     */
    public function save_hierarchy_relation( int $term_id, string $relation_type, int $parent_term_id ): bool {

        if ( ! $term_id || ! $parent_term_id ) {
            return false;
        }

        $meta_key = '';

        // Validar que el tipo de relación sea válido
        if ( 'city' === $relation_type ) {
            $meta_key = FairPlay_LMS_Config::META_TERM_PARENT_CITY;
        } elseif ( 'channel' === $relation_type ) {
            $meta_key = FairPlay_LMS_Config::META_TERM_PARENT_CHANNEL;
        } elseif ( 'branch' === $relation_type ) {
            $meta_key = FairPlay_LMS_Config::META_TERM_PARENT_BRANCH;
        }

        if ( ! $meta_key ) {
            return false;
        }

        update_term_meta( $term_id, $meta_key, $parent_term_id );
        return true;
    }

    /**
     * Obtiene términos filtrados por su padre en la jerarquía.
     * Ejemplo: Obtener todos los Canales de una Ciudad
     */
    public function get_terms_by_parent( string $taxonomy, string $parent_type, int $parent_term_id ): array {

        if ( ! $parent_term_id ) {
            return [];
        }

        // Determinar la meta key según el tipo de padre
        $meta_key = '';
        if ( 'city' === $parent_type ) {
            $meta_key = FairPlay_LMS_Config::META_TERM_PARENT_CITY;
        } elseif ( 'channel' === $parent_type ) {
            $meta_key = FairPlay_LMS_Config::META_TERM_PARENT_CHANNEL;
        } elseif ( 'branch' === $parent_type ) {
            $meta_key = FairPlay_LMS_Config::META_TERM_PARENT_BRANCH;
        }

        if ( ! $meta_key ) {
            return [];
        }

        $terms = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'meta_key'   => $meta_key,
                'meta_value' => $parent_term_id,
            ]
        );

        if ( is_wp_error( $terms ) ) {
            return [];
        }

        return $terms ? $terms : [];
    }

    /**
     * Obtiene el padre (ciudad) de un término.
     * Devuelve el ID del padre o 0 si no tiene.
     */
    public function get_parent_term( int $term_id, string $parent_type ): int {

        if ( ! $term_id ) {
            return 0;
        }

        $meta_key = '';

        if ( 'city' === $parent_type ) {
            $meta_key = FairPlay_LMS_Config::META_TERM_PARENT_CITY;
        } elseif ( 'channel' === $parent_type ) {
            $meta_key = FairPlay_LMS_Config::META_TERM_PARENT_CHANNEL;
        } elseif ( 'branch' === $parent_type ) {
            $meta_key = FairPlay_LMS_Config::META_TERM_PARENT_BRANCH;
        }

        if ( ! $meta_key ) {
            return 0;
        }

        $parent_id = get_term_meta( $term_id, $meta_key, true );
        return $parent_id ? absint( $parent_id ) : 0;
    }

    /**
     * Obtiene los términos activos para un select filtrados por ciudad.
     * Útil para mostrar dinámicamente las opciones en el frontend.
     */
    public function get_active_terms_by_city( string $taxonomy, int $city_term_id ): array {

        $result = [];

        if ( ! $city_term_id ) {
            return $result;
        }

        // Determinar el tipo de relación según la taxonomía
        $relation_type = '';
        if ( FairPlay_LMS_Config::TAX_CHANNEL === $taxonomy ) {
            $relation_type = 'city';
        } elseif ( FairPlay_LMS_Config::TAX_BRANCH === $taxonomy ) {
            $relation_type = 'city'; // Las sucursales pueden depender también de la ciudad
        } elseif ( FairPlay_LMS_Config::TAX_ROLE === $taxonomy ) {
            $relation_type = 'city'; // Los cargos también por ciudad
        }

        if ( ! $relation_type ) {
            return $result;
        }

        $terms = $this->get_terms_by_parent( $taxonomy, $relation_type, $city_term_id );

        if ( ! empty( $terms ) ) {
            foreach ( $terms as $term ) {
                $active = get_term_meta( $term->term_id, FairPlay_LMS_Config::META_ACTIVE, true );
                if ( '1' === $active || '' === $active ) {
                    $result[ $term->term_id ] = $term->name;
                }
            }
        }

        return $result;
    }

    /**
     * Verifica si un término tiene relación con una ciudad.
     * Útil para validar que el usuario pueda ver un curso.
     */
    public function is_term_related_to_city( int $term_id, int $city_term_id ): bool {

        if ( ! $term_id || ! $city_term_id ) {
            return false;
        }

        // Obtener la ciudad padre del término
        $parent_city = $this->get_parent_term( $term_id, 'city' );

        return $parent_city === $city_term_id;
    }

    /**
     * AJAX: Carga dinámicamente las opciones de una taxonomía filtradas por ciudad.
     * Llamada desde JavaScript cuando el usuario selecciona una ciudad.
     */
    public function ajax_get_terms_by_city(): void {

        if ( ! isset( $_POST['city_id'] ) || ! isset( $_POST['taxonomy'] ) ) {
            wp_send_json_error( 'Missing parameters' );
        }

        $city_id  = absint( $_POST['city_id'] );
        $taxonomy = sanitize_text_field( wp_unslash( $_POST['taxonomy'] ) );

        $allowed_taxonomies = [
            FairPlay_LMS_Config::TAX_CHANNEL,
            FairPlay_LMS_Config::TAX_BRANCH,
            FairPlay_LMS_Config::TAX_ROLE,
        ];

        if ( ! in_array( $taxonomy, $allowed_taxonomies, true ) ) {
            wp_send_json_error( 'Invalid taxonomy' );
        }

        $terms = $this->get_active_terms_by_city( $taxonomy, $city_id );

        $options = [ '' => '-- Seleccionar --' ];
        foreach ( $terms as $term_id => $term_name ) {
            $options[ $term_id ] = $term_name;
        }

        wp_send_json_success( $options );
    }

    /**
     * Obtiene todos los términos de una taxonomía que pueden estar en múltiples ciudades.
     * Útil para identificar canales/sucursales/cargos duplicados en diferentes ciudades.
     * 
     * @param string $taxonomy Taxonomía a consultar.
     * @return array Array con estructura: [term_id => ['name' => 'xxx', 'cities' => [1,2,3]]]
     */
    public function get_terms_with_cities( string $taxonomy ): array {

        $allowed_taxonomies = [
            FairPlay_LMS_Config::TAX_CHANNEL,
            FairPlay_LMS_Config::TAX_BRANCH,
            FairPlay_LMS_Config::TAX_ROLE,
        ];

        if ( ! in_array( $taxonomy, $allowed_taxonomies, true ) ) {
            return [];
        }

        $terms = get_terms( [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ] );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return [];
        }

        $result = [];

        foreach ( $terms as $term ) {
            $city_id = $this->get_parent_term( $term->term_id, 'city' );
            $result[ $term->term_id ] = [
                'name'   => $term->name,
                'city'   => $city_id,
                'active' => get_term_meta( $term->term_id, FairPlay_LMS_Config::META_ACTIVE, true ),
            ];
        }

        return $result;
    }

    /**
     * Guarda múltiples ciudades para un término (cargo/canal/sucursal).
     * Reemplaza a save_hierarchy_relation() para ciudades en sistema multi-ciudad.
     *
     * @param int   $term_id ID del término
     * @param array $city_ids Array de IDs de ciudades
     * @return bool true si se guardó correctamente
     */
    public function save_multiple_cities( int $term_id, array $city_ids ): bool {

        if ( ! $term_id || empty( $city_ids ) ) {
            return false;
        }

        // Sanitizar y validar IDs
        $city_ids = array_map( 'absint', $city_ids );
        $city_ids = array_filter( $city_ids );

        if ( empty( $city_ids ) ) {
            return false;
        }

        // Eliminar duplicados
        $city_ids = array_unique( $city_ids );

        // Guardar como JSON serializado
        $serialized = wp_json_encode( $city_ids );
        update_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_CITIES, $serialized );

        return true;
    }

    /**
     * Obtiene todas las ciudades asignadas a un término.
     * Soporta compatibilidad retroactiva con sistema antiguo (single city).
     *
     * @param int $term_id ID del término
     * @return array Array de IDs de ciudades
     */
    public function get_term_cities( int $term_id ): array {

        if ( ! $term_id ) {
            return [];
        }

        // Intentar obtener ciudades en nuevo formato (JSON)
        $serialized = get_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_CITIES, true );

        if ( $serialized ) {
            $city_ids = json_decode( $serialized, true );
            return is_array( $city_ids ) ? $city_ids : [];
        }

        // Fallback a sistema antiguo (compatibilidad retroactiva)
        $old_city = $this->get_parent_term( $term_id, 'city' );
        return $old_city ? [ $old_city ] : [];
    }

    /**
     * Obtiene términos que están asignados a una o varias ciudades.
     * Filtra por si el término está en alguna de las ciudades solicitadas.
     *
     * @param string $taxonomy Taxonomía a consultar
     * @param array  $city_ids Array de IDs de ciudades
     * @return array Array de términos que están en esas ciudades
     */
    public function get_terms_by_cities( string $taxonomy, array $city_ids ): array {

        if ( empty( $city_ids ) ) {
            return [];
        }

        $city_ids = array_map( 'absint', array_filter( $city_ids ) );
        if ( empty( $city_ids ) ) {
            return [];
        }

        $result    = [];
        $all_terms = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
            ]
        );

        if ( is_wp_error( $all_terms ) || empty( $all_terms ) ) {
            return [];
        }

        foreach ( $all_terms as $term ) {
            $term_cities = $this->get_term_cities( $term->term_id );

            // Si el término está en cualquiera de las ciudades solicitadas
            if ( array_intersect( $term_cities, $city_ids ) ) {
                $result[] = $term;
            }
        }

        return $result;
    }

    /**
     * Obtiene todos los términos de una taxonomía con todas sus ciudades asignadas.
     * Útil para mostrar en tabla cuáles ciudades tiene cada término.
     *
     * @param string $taxonomy Taxonomía a consultar
     * @return array Array con estructura: [term_id => ['name' => 'xxx', 'cities' => [1,2,3], 'active' => '1']]
     */
    public function get_terms_all_cities( string $taxonomy ): array {

        $allowed_taxonomies = [
            FairPlay_LMS_Config::TAX_CHANNEL,
            FairPlay_LMS_Config::TAX_BRANCH,
            FairPlay_LMS_Config::TAX_ROLE,
        ];

        if ( ! in_array( $taxonomy, $allowed_taxonomies, true ) ) {
            return [];
        }

        $terms = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
            ]
        );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return [];
        }

        $result = [];

        foreach ( $terms as $term ) {
            $cities = $this->get_term_cities( $term->term_id );
            $result[ $term->term_id ] = [
                'name'   => $term->name,
                'cities' => $cities,
                'active' => get_term_meta( $term->term_id, FairPlay_LMS_Config::META_ACTIVE, true ),
            ];
        }

        return $result;
    }
}
