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
            FairPlay_LMS_Config::TAX_COMPANY,
            'post',
            array_merge( $common_args, [ 'label' => 'Empresas' ] )
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
            FairPlay_LMS_Config::TAX_COMPANY,
            FairPlay_LMS_Config::TAX_CHANNEL,
            FairPlay_LMS_Config::TAX_BRANCH,
            FairPlay_LMS_Config::TAX_ROLE,
        ];

        if ( ! in_array( $taxonomy, $allowed_taxonomies, true ) ) {
            return;
        }

        if ( 'create' === $action ) {

            $name   = sanitize_text_field( wp_unslash( $_POST['fplms_name'] ?? '' ) );
            $description = isset( $_POST['fplms_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fplms_description'] ) ) : '';
            $active = ! empty( $_POST['fplms_active'] ) ? '1' : '0';

            // Validar longitud de descripción (máximo 300 caracteres)
            if ( strlen( $description ) > 300 ) {
                $description = substr( $description, 0, 300 );
            }

            if ( $name ) {
                $term = wp_insert_term( $name, $taxonomy );
                if ( ! is_wp_error( $term ) ) {
                    update_term_meta( $term['term_id'], FairPlay_LMS_Config::META_ACTIVE, $active );

                    // Guardar descripción si no está vacía
                    if ( ! empty( $description ) ) {
                        update_term_meta( $term['term_id'], FairPlay_LMS_Config::META_TERM_DESCRIPTION, $description );
                    }

                    // Guardar múltiples ciudades para Empresas
                    if ( FairPlay_LMS_Config::TAX_COMPANY === $taxonomy && ! empty( $_POST['fplms_cities'] ) ) {
                        $city_ids = array_map( 'absint', (array) $_POST['fplms_cities'] );
                        $city_ids = array_filter( $city_ids );

                        if ( ! empty( $city_ids ) && $this->validate_hierarchy( $taxonomy, $term['term_id'], $city_ids ) ) {
                            $this->save_multiple_cities( $term['term_id'], $city_ids );
                        }
                    }

                    // Guardar múltiples empresas para Canales
                    if ( FairPlay_LMS_Config::TAX_CHANNEL === $taxonomy && ! empty( $_POST['fplms_companies'] ) ) {
                        $company_ids = array_map( 'absint', (array) $_POST['fplms_companies'] );
                        $company_ids = array_filter( $company_ids );

                        if ( ! empty( $company_ids ) && $this->validate_hierarchy( $taxonomy, $term['term_id'], $company_ids ) ) {
                            $this->save_term_companies( $term['term_id'], $company_ids );
                        }
                    }

                    // Guardar múltiples canales para Sucursales
                    if ( FairPlay_LMS_Config::TAX_BRANCH === $taxonomy && ! empty( $_POST['fplms_channels'] ) ) {
                        $channel_ids = array_map( 'absint', (array) $_POST['fplms_channels'] );
                        $channel_ids = array_filter( $channel_ids );

                        if ( ! empty( $channel_ids ) && $this->validate_hierarchy( $taxonomy, $term['term_id'], $channel_ids ) ) {
                            $this->save_term_channels( $term['term_id'], $channel_ids );
                        }
                    }

                    // Guardar múltiples sucursales para Cargos
                    if ( FairPlay_LMS_Config::TAX_ROLE === $taxonomy && ! empty( $_POST['fplms_branches'] ) ) {
                        $branch_ids = array_map( 'absint', (array) $_POST['fplms_branches'] );
                        $branch_ids = array_filter( $branch_ids );

                        if ( ! empty( $branch_ids ) && $this->validate_hierarchy( $taxonomy, $term['term_id'], $branch_ids ) ) {
                            $this->save_term_branches( $term['term_id'], $branch_ids );
                        }
                    }

                    // Registrar creación en auditoría
                    if ( class_exists( 'FairPlay_LMS_Audit_Logger' ) ) {
                        $audit = new FairPlay_LMS_Audit_Logger();
                        
                        // Construir metadata para auditoría
                        $audit_meta = [
                            'active' => $active,
                            'taxonomy' => $taxonomy,
                        ];

                        if ( ! empty( $description ) ) {
                            $audit_meta['description'] = $description;
                        }

                        // Agregar relaciones jerárquicas según el tipo
                        if ( FairPlay_LMS_Config::TAX_COMPANY === $taxonomy && ! empty( $city_ids ) ) {
                            $audit_meta['city_ids'] = $city_ids;
                            $audit_meta['cities_count'] = count( $city_ids );
                        }

                        if ( FairPlay_LMS_Config::TAX_CHANNEL === $taxonomy && ! empty( $company_ids ) ) {
                            $audit_meta['company_ids'] = $company_ids;
                            $audit_meta['companies_count'] = count( $company_ids );
                        }

                        if ( FairPlay_LMS_Config::TAX_BRANCH === $taxonomy && ! empty( $channel_ids ) ) {
                            $audit_meta['channel_ids'] = $channel_ids;
                            $audit_meta['channels_count'] = count( $channel_ids );
                        }

                        if ( FairPlay_LMS_Config::TAX_ROLE === $taxonomy && ! empty( $branch_ids ) ) {
                            $audit_meta['branch_ids'] = $branch_ids;
                            $audit_meta['branches_count'] = count( $branch_ids );
                        }

                        // Obtener tipo de estructura legible
                        $structure_type = $this->get_structure_type_name( $taxonomy );

                        $audit->log_structure_created(
                            $structure_type,
                            $term['term_id'],
                            $name,
                            $audit_meta
                        );
                    }
                    
                    // Redirigir con mensaje de éxito
                    $tab = sanitize_text_field( wp_unslash( $_POST['fplms_tab'] ?? '' ) );
                    $structure_type = $this->get_structure_type_name( $taxonomy );
                    $success_msg = urlencode( "✓ Nuevo elemento creado exitosamente: \"{$name}\" ({$structure_type})" );
                    $redirect_url = add_query_arg( 
                        array(
                            'page' => 'fplms-structures',
                            'fplms_success' => $success_msg,
                            'tab' => $tab
                        ),
                        admin_url( 'admin.php' )
                    );
                    wp_redirect( $redirect_url );
                    exit;
                }
            }
        }

        if ( 'toggle_active' === $action ) {

            $term_id = isset( $_POST['fplms_term_id'] ) ? absint( $_POST['fplms_term_id'] ) : 0;
            if ( $term_id ) {
                // Obtener información del término antes de cambiar
                $term = get_term( $term_id, $taxonomy );
                $term_name = $term && ! is_wp_error( $term ) ? $term->name : "Término #{$term_id}";
                
                $current = get_term_meta( $term_id, FairPlay_LMS_Config::META_ACTIVE, true );
                $new     = ( '1' === $current ) ? '0' : '1';
                update_term_meta( $term_id, FairPlay_LMS_Config::META_ACTIVE, $new );

                // Registrar cambio de estado en auditoría
                if ( class_exists( 'FairPlay_LMS_Audit_Logger' ) ) {
                    $audit = new FairPlay_LMS_Audit_Logger();
                    $structure_type = $this->get_structure_type_name( $taxonomy );

                    $audit->log_structure_status_changed(
                        $structure_type,
                        $term_id,
                        $term_name,
                        $current ?: '0',  // Estado anterior
                        $new              // Estado nuevo
                    );
                }
                
                // Redirigir con mensaje de éxito
                $tab = sanitize_text_field( wp_unslash( $_POST['fplms_tab'] ?? '' ) );
                $status_text = ( '1' === $new ) ? 'activado' : 'desactivado';
                $success_msg = urlencode( "✓ Estado actualizado: \"{$term_name}\" ha sido {$status_text}" );
                $redirect_url = add_query_arg( 
                    array(
                        'page' => 'fplms-structures',
                        'fplms_success' => $success_msg,
                        'tab' => $tab
                    ),
                    admin_url( 'admin.php' )
                );
                
                // Headers para evitar caché y forzar actualización
                header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
                header( 'Cache-Control: post-check=0, pre-check=0', false );
                header( 'Pragma: no-cache' );
                
                wp_redirect( $redirect_url );
                exit;
            }
        }

        if ( 'edit' === $action ) {

            $term_id = isset( $_POST['fplms_term_id'] ) ? absint( $_POST['fplms_term_id'] ) : 0;
            $name    = sanitize_text_field( wp_unslash( $_POST['fplms_name'] ?? '' ) );
            $description = isset( $_POST['fplms_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fplms_description'] ) ) : '';

            // Validar longitud de descripción (máximo 300 caracteres)
            if ( strlen( $description ) > 300 ) {
                $description = substr( $description, 0, 300 );
            }

            if ( $term_id && $name ) {
                // Capturar datos antiguos para auditoría
                $old_term = get_term( $term_id, $taxonomy );
                $old_name = $old_term && ! is_wp_error( $old_term ) ? $old_term->name : '';
                $old_description = get_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION, true );
                $old_cities = get_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_CITIES, true );
                $old_companies = get_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_COMPANIES, true );
                $old_channels = get_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_CHANNELS, true );
                $old_branches = get_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_BRANCHES, true );

                // Actualizar nombre del término
                wp_update_term( $term_id, $taxonomy, [ 'name' => $name ] );

                // Actualizar descripción
                if ( ! empty( $description ) ) {
                    update_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION, $description );
                } else {
                    delete_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION );
                }

                // Actualizar múltiples ciudades para Empresas
                if ( FairPlay_LMS_Config::TAX_COMPANY === $taxonomy && ! empty( $_POST['fplms_cities'] ) ) {
                    $city_ids = array_map( 'absint', (array) $_POST['fplms_cities'] );
                    $city_ids = array_filter( $city_ids );

                    if ( ! empty( $city_ids ) && $this->validate_hierarchy( $taxonomy, $term_id, $city_ids ) ) {
                        $this->save_multiple_cities( $term_id, $city_ids );
                    }
                }

                // Actualizar múltiples empresas para Canales
                if ( FairPlay_LMS_Config::TAX_CHANNEL === $taxonomy && ! empty( $_POST['fplms_companies'] ) ) {
                    $company_ids = array_map( 'absint', (array) $_POST['fplms_companies'] );
                    $company_ids = array_filter( $company_ids );

                    if ( ! empty( $company_ids ) && $this->validate_hierarchy( $taxonomy, $term_id, $company_ids ) ) {
                        $this->save_term_companies( $term_id, $company_ids );
                    }
                }

                // Actualizar múltiples canales para Sucursales
                if ( FairPlay_LMS_Config::TAX_BRANCH === $taxonomy && ! empty( $_POST['fplms_channels'] ) ) {
                    $channel_ids = array_map( 'absint', (array) $_POST['fplms_channels'] );
                    $channel_ids = array_filter( $channel_ids );

                    if ( ! empty( $channel_ids ) && $this->validate_hierarchy( $taxonomy, $term_id, $channel_ids ) ) {
                        $this->save_term_channels( $term_id, $channel_ids );
                    }
                }

                // Actualizar múltiples sucursales para Cargos
                if ( FairPlay_LMS_Config::TAX_ROLE === $taxonomy && ! empty( $_POST['fplms_branches'] ) ) {
                    $branch_ids = array_map( 'absint', (array) $_POST['fplms_branches'] );
                    $branch_ids = array_filter( $branch_ids );

                    if ( ! empty( $branch_ids ) && $this->validate_hierarchy( $taxonomy, $term_id, $branch_ids ) ) {
                        $this->save_term_branches( $term_id, $branch_ids );
                    }
                }

                // Registrar edición en auditoría
                if ( class_exists( 'FairPlay_LMS_Audit_Logger' ) ) {
                    $audit = new FairPlay_LMS_Audit_Logger();

                    // Construir datos antiguos
                    $old_data = [
                        'name' => $old_name,
                        'taxonomy' => $taxonomy,
                    ];

                    if ( ! empty( $old_description ) ) {
                        $old_data['description'] = $old_description;
                    }

                    if ( FairPlay_LMS_Config::TAX_COMPANY === $taxonomy && ! empty( $old_cities ) ) {
                        $old_cities_array = json_decode( $old_cities, true );
                        if ( is_array( $old_cities_array ) ) {
                            $old_data['city_ids'] = $old_cities_array;
                            $old_data['cities_count'] = count( $old_cities_array );
                        }
                    }

                    if ( FairPlay_LMS_Config::TAX_CHANNEL === $taxonomy && ! empty( $old_companies ) ) {
                        $old_companies_array = json_decode( $old_companies, true );
                        if ( is_array( $old_companies_array ) ) {
                            $old_data['company_ids'] = $old_companies_array;
                            $old_data['companies_count'] = count( $old_companies_array );
                        }
                    }

                    if ( FairPlay_LMS_Config::TAX_BRANCH === $taxonomy && ! empty( $old_channels ) ) {
                        $old_channels_array = json_decode( $old_channels, true );
                        if ( is_array( $old_channels_array ) ) {
                            $old_data['channel_ids'] = $old_channels_array;
                            $old_data['channels_count'] = count( $old_channels_array );
                        }
                    }

                    if ( FairPlay_LMS_Config::TAX_ROLE === $taxonomy && ! empty( $old_branches ) ) {
                        $old_branches_array = json_decode( $old_branches, true );
                        if ( is_array( $old_branches_array ) ) {
                            $old_data['branch_ids'] = $old_branches_array;
                            $old_data['branches_count'] = count( $old_branches_array );
                        }
                    }

                    // Construir datos nuevos
                    $new_data = [
                        'name' => $name,
                        'taxonomy' => $taxonomy,
                    ];

                    if ( ! empty( $description ) ) {
                        $new_data['description'] = $description;
                    }

                    if ( FairPlay_LMS_Config::TAX_COMPANY === $taxonomy && ! empty( $city_ids ) ) {
                        $new_data['city_ids'] = $city_ids;
                        $new_data['cities_count'] = count( $city_ids );
                    }

                    if ( FairPlay_LMS_Config::TAX_CHANNEL === $taxonomy && ! empty( $company_ids ) ) {
                        $new_data['company_ids'] = $company_ids;
                        $new_data['companies_count'] = count( $company_ids );
                    }

                    if ( FairPlay_LMS_Config::TAX_BRANCH === $taxonomy && ! empty( $channel_ids ) ) {
                        $new_data['channel_ids'] = $channel_ids;
                        $new_data['channels_count'] = count( $channel_ids );
                    }

                    if ( FairPlay_LMS_Config::TAX_ROLE === $taxonomy && ! empty( $branch_ids ) ) {
                        $new_data['branch_ids'] = $branch_ids;
                        $new_data['branches_count'] = count( $branch_ids );
                    }

                    // Obtener tipo de estructura legible
                    $structure_type = $this->get_structure_type_name( $taxonomy );

                    $audit->log_structure_updated(
                        $structure_type,
                        $term_id,
                        $name,
                        $old_data,
                        $new_data
                    );
                }
                
                // Redirigir con mensaje de éxito
                $tab = sanitize_text_field( wp_unslash( $_POST['fplms_tab'] ?? '' ) );
                $success_msg = urlencode( "✓ Elemento actualizado exitosamente: \"{$name}\"" );
                $redirect_url = add_query_arg( 
                    array(
                        'page' => 'fplms-structures',
                        'fplms_success' => $success_msg,
                        'tab' => $tab
                    ),
                    admin_url( 'admin.php' )
                );
                wp_redirect( $redirect_url );
                exit;
            }
        }

        if ( 'delete' === $action ) {

            $term_id = isset( $_POST['fplms_term_id'] ) ? absint( $_POST['fplms_term_id'] ) : 0;

            if ( $term_id ) {
                // Capturar datos para auditoría ANTES de eliminar
                $term_to_delete = get_term( $term_id, $taxonomy );
                $term_name = $term_to_delete && ! is_wp_error( $term_to_delete ) ? $term_to_delete->name : "Término #{$term_id}";
                $term_description = get_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION, true );
                $term_cities = get_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_CITIES, true );
                $term_companies = get_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_COMPANIES, true );
                $term_channels = get_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_CHANNELS, true );
                $term_branches = get_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_BRANCHES, true );
                $linked_category = get_term_meta( $term_id, 'fplms_linked_category_id', true );

                // Eliminar todas las relaciones jerárquicas
                delete_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_CITIES );
                delete_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_COMPANIES );
                delete_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_CHANNELS );
                delete_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_BRANCHES );
                delete_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION );
                
                // Eliminar metadatos deprecated si existen
                delete_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_PARENT_CITY );
                delete_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_PARENT_CHANNEL );
                delete_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_PARENT_BRANCH );
                
                // Eliminar vinculación con categoría si existe (para canales)
                delete_term_meta( $term_id, 'fplms_linked_category_id' );
                
                // Registrar eliminación en auditoría ANTES de eliminar el término
                if ( class_exists( 'FairPlay_LMS_Audit_Logger' ) ) {
                    $audit = new FairPlay_LMS_Audit_Logger();

                    // Construir metadata con los datos que tenía
                    $delete_meta = [
                        'taxonomy' => $taxonomy,
                    ];

                    if ( ! empty( $term_description ) ) {
                        $delete_meta['description'] = $term_description;
                    }

                    if ( ! empty( $term_cities ) ) {
                        $cities_array = json_decode( $term_cities, true );
                        if ( is_array( $cities_array ) ) {
                            $delete_meta['city_ids'] = $cities_array;
                            $delete_meta['cities_count'] = count( $cities_array );
                        }
                    }

                    if ( ! empty( $term_companies ) ) {
                        $companies_array = json_decode( $term_companies, true );
                        if ( is_array( $companies_array ) ) {
                            $delete_meta['company_ids'] = $companies_array;
                            $delete_meta['companies_count'] = count( $companies_array );
                        }
                    }

                    if ( ! empty( $term_channels ) ) {
                        $channels_array = json_decode( $term_channels, true );
                        if ( is_array( $channels_array ) ) {
                            $delete_meta['channel_ids'] = $channels_array;
                            $delete_meta['channels_count'] = count( $channels_array );
                        }
                    }

                    if ( ! empty( $term_branches ) ) {
                        $branches_array = json_decode( $term_branches, true );
                        if ( is_array( $branches_array ) ) {
                            $delete_meta['branch_ids'] = $branches_array;
                            $delete_meta['branches_count'] = count( $branches_array );
                        }
                    }

                    if ( ! empty( $linked_category ) ) {
                        $delete_meta['linked_category_id'] = $linked_category;
                    }

                    // Obtener tipo de estructura legible
                    $structure_type = $this->get_structure_type_name( $taxonomy );

                    $audit->log_structure_deleted(
                        $structure_type,
                        $term_id,
                        $term_name,
                        $delete_meta
                    );
                }

                // Eliminar el término completamente
                wp_delete_term( $term_id, $taxonomy );
                
                // Redirigir con mensaje de éxito
                $tab = sanitize_text_field( wp_unslash( $_POST['fplms_tab'] ?? '' ) );
                $success_msg = urlencode( "✓ Elemento eliminado exitosamente: \"{$term_name}\"" );
                $redirect_url = add_query_arg(
                    array(
                        'page' => 'fplms-structures',
                        'fplms_success' => $success_msg,
                        'tab' => $tab
                    ),
                    admin_url( 'admin.php' )
                );
                wp_redirect( $redirect_url );
                exit;
            }
        }

        // Acciones masivas
        if ( 'bulk_delete' === $action ) {
            $term_ids = isset( $_POST['fplms_term_ids'] ) ? array_map( 'absint', (array) $_POST['fplms_term_ids'] ) : [];
            $term_ids = array_filter( $term_ids );
            
            $deleted_count = 0;
            $deleted_names = [];
            
            if ( ! empty( $term_ids ) ) {
                foreach ( $term_ids as $term_id ) {
                    $term = get_term( $term_id, $taxonomy );
                    
                    if ( $term && ! is_wp_error( $term ) ) {
                        $term_name = $term->name;
                        $deleted_names[] = $term_name;
                        
                        // Capturar metadatos antes de eliminar
                        $term_description = get_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION, true );
                        
                        // Eliminar todas las relaciones
                        delete_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_CITIES );
                        delete_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_COMPANIES );
                        delete_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_CHANNELS );
                        delete_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_BRANCHES );
                        delete_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION );
                        delete_term_meta( $term_id, 'fplms_linked_category_id' );
                        
                        // Registrar en bitácora
                        if ( class_exists( 'FairPlay_LMS_Audit_Logger' ) ) {
                            $audit = new FairPlay_LMS_Audit_Logger();
                            $structure_type = $this->get_structure_type_name( $taxonomy );
                            
                            $audit->log_structure_deleted(
                                $structure_type,
                                $term_id,
                                $term_name,
                                [ 'taxonomy' => $taxonomy, 'bulk_action' => true ]
                            );
                        }
                        
                        // Eliminar término
                        wp_delete_term( $term_id, $taxonomy );
                        $deleted_count++;
                    }
                }
            }
            
            // Redirigir con mensaje
            $tab = sanitize_text_field( wp_unslash( $_POST['fplms_tab'] ?? '' ) );
            $success_msg = urlencode( "✓ {$deleted_count} elemento" . ( $deleted_count > 1 ? 's eliminados' : ' eliminado' ) . " exitosamente" );
            $redirect_url = add_query_arg(
                array(
                    'page' => 'fplms-structures',
                    'fplms_success' => $success_msg,
                    'tab' => $tab
                ),
                admin_url( 'admin.php' )
            );
            wp_redirect( $redirect_url );
            exit;
        }

        if ( 'bulk_deactivate' === $action ) {
            $term_ids = isset( $_POST['fplms_term_ids'] ) ? array_map( 'absint', (array) $_POST['fplms_term_ids'] ) : [];
            $term_ids = array_filter( $term_ids );
            
            $updated_count = 0;
            
            if ( ! empty( $term_ids ) ) {
                foreach ( $term_ids as $term_id ) {
                    $term = get_term( $term_id, $taxonomy );
                    
                    if ( $term && ! is_wp_error( $term ) ) {
                        $current = get_term_meta( $term_id, FairPlay_LMS_Config::META_ACTIVE, true );
                        
                        // Solo desactivar si está activo
                        if ( '1' === $current ) {
                            update_term_meta( $term_id, FairPlay_LMS_Config::META_ACTIVE, '0' );
                            
                            // Registrar en bitácora
                            if ( class_exists( 'FairPlay_LMS_Audit_Logger' ) ) {
                                $audit = new FairPlay_LMS_Audit_Logger();
                                $structure_type = $this->get_structure_type_name( $taxonomy );
                                
                                $audit->log_structure_status_changed(
                                    $structure_type,
                                    $term_id,
                                    $term->name,
                                    '1',
                                    '0'
                                );
                            }
                            
                            $updated_count++;
                        }
                    }
                }
            }
            
            // Redirigir con mensaje
            $tab = sanitize_text_field( wp_unslash( $_POST['fplms_tab'] ?? '' ) );
            $success_msg = urlencode( "✓ {$updated_count} elemento" . ( $updated_count > 1 ? 's desactivados' : ' desactivado' ) . " exitosamente" );
            $redirect_url = add_query_arg(
                array(
                    'page' => 'fplms-structures',
                    'fplms_success' => $success_msg,
                    'tab' => $tab
                ),
                admin_url( 'admin.php' )
            );
            
            // Headers para evitar caché
            header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
            header( 'Cache-Control: post-check=0, pre-check=0', false );
            header( 'Pragma: no-cache' );
            
            wp_redirect( $redirect_url );
            exit;
        }

        if ( 'bulk_activate' === $action ) {
            $term_ids = isset( $_POST['fplms_term_ids'] ) ? array_map( 'absint', (array) $_POST['fplms_term_ids'] ) : [];
            $term_ids = array_filter( $term_ids );
            
            $updated_count = 0;
            
            if ( ! empty( $term_ids ) ) {
                foreach ( $term_ids as $term_id ) {
                    $term = get_term( $term_id, $taxonomy );
                    
                    if ( $term && ! is_wp_error( $term ) ) {
                        $current = get_term_meta( $term_id, FairPlay_LMS_Config::META_ACTIVE, true );
                        
                        // Solo activar si está inactivo
                        if ( '0' === $current || '' === $current ) {
                            update_term_meta( $term_id, FairPlay_LMS_Config::META_ACTIVE, '1' );
                            
                            // Registrar en bitácora
                            if ( class_exists( 'FairPlay_LMS_Audit_Logger' ) ) {
                                $audit = new FairPlay_LMS_Audit_Logger();
                                $structure_type = $this->get_structure_type_name( $taxonomy );
                                
                                $audit->log_structure_status_changed(
                                    $structure_type,
                                    $term_id,
                                    $term->name,
                                    $current ?: '0',
                                    '1'
                                );
                            }
                            
                            $updated_count++;
                        }
                    }
                }
            }
            
            // Redirigir con mensaje
            $tab = sanitize_text_field( wp_unslash( $_POST['fplms_tab'] ?? '' ) );
            $success_msg = urlencode( "✓ {$updated_count} elemento" . ( $updated_count > 1 ? 's activados' : ' activado' ) . " exitosamente" );
            $redirect_url = add_query_arg(
                array(
                    'page' => 'fplms-structures',
                    'fplms_success' => $success_msg,
                    'tab' => $tab
                ),
                admin_url( 'admin.php' )
            );
            
            // Headers para evitar caché
            header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
            header( 'Cache-Control: post-check=0, pre-check=0', false );
            header( 'Pragma: no-cache' );
            
            wp_redirect( $redirect_url );
            exit;
        }

        // Este redirect se mantiene por si alguna acción no tiene su propio redirect
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
     * Devuelve un icono SVG inline por nombre para uso en el panel de administración.
     * Usa inicialización estática para inicializar el mapa de paths una sola vez por request.
     */
    private function fplms_svg( string $name, int $size = 16 ): string {
        static $icons = null;
        if ( null === $icons ) {
            $icons = [
                'pin'         => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>',
                'building'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>',
                'store'       => '<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z"/>',
                'branch'      => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z"/>',
                'briefcase'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z"/>',
                'settings'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>',
                'search'      => '<path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>',
                'trash'       => '<path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>',
                'pencil'      => '<path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>',
                'note'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>',
                'plus'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>',
                'save'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859M12 3v8.25m0 0-3-3m3 3 3-3"/>',
                'check'       => '<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>',
                'check-circle'=> '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>',
                'x-circle'    => '<path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>',
                'warning'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>',
                'chevron'     => '<path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>',
                'inbox'       => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z"/>',
                'toggle-on'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>',
                'toggle-off'  => '<path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5m2.25-9.001A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>',
                'table'       => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-1.5A1.125 1.125 0 0 1 18 18.375m1.5-1.125H6m0 0c0 .621-.504 1.125-1.125 1.125M6 17.25V5.625m0 0c-.621 0-1.125.504-1.125 1.125m1.125-1.125H18m0 0c.621 0 1.125.504 1.125 1.125M18 5.625v12.75"/>',
                'file'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>',
            ];
        }
        $path = $icons[ $name ] ?? '';
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0">%2$s</svg>',
            $size,
            $path
        );
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
                'label'    => 'Ciudades',
                'icon'     => $this->fplms_svg( 'pin', 18 ),
                'taxonomy' => FairPlay_LMS_Config::TAX_CITY,
                'color'    => '#0073aa',
            ],
            'company' => [
                'label'    => 'Empresas',
                'icon'     => $this->fplms_svg( 'building', 18 ),
                'taxonomy' => FairPlay_LMS_Config::TAX_COMPANY,
                'color'    => '#9333ea',
            ],
            'channel' => [
                'label'    => 'Canales / Franquicias',
                'icon'     => $this->fplms_svg( 'store', 18 ),
                'taxonomy' => FairPlay_LMS_Config::TAX_CHANNEL,
                'color'    => '#00a000',
            ],
            'branch'  => [
                'label'    => 'Sucursales',
                'icon'     => $this->fplms_svg( 'branch', 18 ),
                'taxonomy' => FairPlay_LMS_Config::TAX_BRANCH,
                'color'    => '#ff6f00',
            ],
            'role'    => [
                'label'    => 'Cargos',
                'icon'     => $this->fplms_svg( 'briefcase', 18 ),
                'taxonomy' => FairPlay_LMS_Config::TAX_ROLE,
                'color'    => '#7c3aed',
            ],
        ];

        ?>
        <style>
            .fplms-structures-wrapper {
                background: #f5f7fa;
                min-height: 100vh;
                padding: 20px;
                margin-left: -20px;
            }
            .fplms-structures-container {
                max-width: 1200px;
                margin: 0 auto;
                background: #fff;
                border-radius: 12px;
                padding: 30px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }
            .fplms-structures-header {
                display: flex;
                align-items: center;
                gap: 15px;
                margin-bottom: 15px;
                padding-bottom: 20px;
                border-bottom: 2px solid #f0f0f0;
            }
            .fplms-structures-icon {
                display: flex;
                align-items: center;
                flex-shrink: 0;
            }
            .fplms-structures-icon svg { width: 32px; height: 32px; }
            .fplms-tab-icon { display: inline-flex; align-items: center; }
            .fplms-structures-title {
                margin: 0;
                font-size: 24px;
                font-weight: 700;
                color: #1a1a1a;
            }
            .fplms-structures-subtitle {
                margin: 0 0 25px 47px;
                font-size: 14px;
                color: #6b7280;
                font-weight: 400;
                line-height: 1.6;
            }
            
            /* ESTILOS DE TABLAS */
            .fplms-table-controls {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 15px;
                gap: 15px;
                flex-wrap: wrap;
            }
            
            .fplms-table-search {
                flex: 1;
                min-width: 250px;
            }
            
            .fplms-search-input {
                width: 100%;
                padding: 10px 15px;
                border: 1px solid #ddd;
                border-radius: 6px;
                font-size: 14px;
                transition: all 0.3s ease;
            }
            
            .fplms-search-input:focus {
                outline: none;
                border-color: #0073aa;
                box-shadow: 0 0 0 3px rgba(0,115,170,0.1);
            }
            
            .fplms-table-export {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }
            
            .fplms-export-btn {
                padding: 8px 14px !important;
                font-size: 13px !important;
                white-space: nowrap;
                border-radius: 5px !important;
                transition: all 0.3s ease !important;
            }
            
            .fplms-export-btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 2px 5px rgba(0,0,0,0.15);
            }
            
            .fplms-export-selected {
                background: #00a000 !important;
                color: white !important;
                border-color: #00a000 !important;
            }
            
            .fplms-export-selected:hover {
                background: #008000 !important;
            }
            
            /* ACCIONES MASIVAS */
            .fplms-bulk-actions-container {
                display: none;
                align-items: center;
                gap: 10px;
                padding: 10px;
                background: #fff3cd;
                border: 1px solid #ffc107;
                border-radius: 5px;
                margin-bottom: 10px;
            }
            
            .fplms-bulk-actions-container.visible {
                display: flex;
            }
            
            .fplms-bulk-select {
                padding: 6px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 13px;
                color: #333;
                background: white;
            }
            
            .fplms-bulk-apply-btn {
                background: #0073aa !important;
                color: white !important;
                border: none !important;
                padding: 6px 15px !important;
                border-radius: 4px !important;
                cursor: pointer !important;
                font-size: 13px !important;
                transition: all 0.2s ease !important;
            }
            
            .fplms-bulk-apply-btn:hover {
                background: #005a87 !important;
            }
            
            .fplms-bulk-apply-btn:disabled {
                background: #ccc !important;
                cursor: not-allowed !important;
            }
            
            /* TABLA DE DATOS */
            .fplms-data-table {
                width: 100%;
                border-collapse: collapse;
                background: white;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                border-radius: 6px;
                overflow: hidden;
                margin-bottom: 15px;
            }
            
            .fplms-data-table thead {
                background: linear-gradient(135deg, #0073aa 0%, #005a87 100%);
                color: white;
            }
            
            .fplms-data-table th {
                padding: 12px 15px;
                text-align: left;
                font-weight: 600;
                font-size: 13px;
                border-bottom: 2px solid #005a87;
            }
            
            .fplms-data-table td {
                padding: 12px 15px;
                border-bottom: 1px solid #f0f0f0;
                font-size: 13px;
                vertical-align: middle;
            }
            
            .fplms-data-table tbody tr {
                transition: all 0.2s ease;
            }
            
            .fplms-data-table tbody tr:hover {
                background: #f9f9f9;
            }
            
            .fplms-data-table tbody tr:last-child td {
                border-bottom: none;
            }
            
            .fplms-table-actions {
                white-space: nowrap;
            }
            
            .fplms-edit-row td {
                background: #f5f5f5 !important;
                padding: 0 !important;
            }
            
            /* BADGES */
            .fplms-status-badge {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 4px 10px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 600;
                white-space: nowrap;
            }
            
            .fplms-status-badge.active {
                background: #d4edda;
                color: #155724;
            }
            
            .fplms-status-badge.inactive {
                background: #f8d7da;
                color: #721c24;
            }
            
            .fplms-relation-badge {
                display: inline-block;
                padding: 3px 8px;
                background: #e3f2fd;
                color: #0277bd;
                border-radius: 3px;
                font-size: 12px;
                margin-right: 4px;
            }
            
            /* PAGINACIÓN */
            .fplms-pagination {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 5px;
                margin-top: 15px;
                flex-wrap: wrap;
            }
            
            .fplms-pagination-btn {
                padding: 6px 12px;
                background: #f5f5f5;
                border: 1px solid #ddd;
                border-radius: 4px;
                cursor: pointer;
                transition: all 0.3s ease;
                font-size: 13px;
            }
            
            .fplms-pagination-btn:hover:not(:disabled) {
                background: #0073aa;
                color: white;
                border-color: #0073aa;
            }
            
            .fplms-pagination-btn.active {
                background: #0073aa;
                color: white;
                border-color: #0073aa;
                font-weight: 600;
            }
            
            .fplms-pagination-btn:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
            
            .fplms-pagination-info {
                font-size: 13px;
                color: #666;
                padding: 6px 10px;
            }
            
            /* RESPONSIVE */
            @media (max-width: 768px) {
                .fplms-table-controls {
                    flex-direction: column;
                    align-items: stretch;
                }
                
                .fplms-table-search {
                    min-width: 100%;
                }
                
                .fplms-table-export {
                    flex-direction: column;
                }
                
                .fplms-export-btn {
                    width: 100%;
                }
                
                .fplms-data-table {
                    font-size: 12px;
                }
                
                .fplms-data-table th,
                .fplms-data-table td {
                    padding: 8px 10px;
                }
                
                .fplms-pagination {
                    font-size: 12px;
                }
            }
        </style>
        <div class="fplms-structures-wrapper">
            <div class="fplms-structures-container">
                <div class="fplms-structures-header">
                    <div class="fplms-structures-icon"><?php echo $this->fplms_svg( 'settings', 32 ); ?></div>
                    <h1 class="fplms-structures-title">Gestión de Estructuras</h1>
                </div>
                <p class="fplms-structures-subtitle">
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
                            <span class="fplms-accordion-icon"><?php echo $this->fplms_svg( 'chevron', 14 ); ?></span>
                            <span class="fplms-accordion-title">
                                <span class="fplms-tab-icon"><?php echo $tab_info['icon']; ?></span>
                                <?php echo esc_html( $tab_info['label'] ); ?>
                                <span class="fplms-accordion-count">( <?php echo count( is_wp_error( $terms ) ? [] : $terms ); ?> )</span>
                            </span>
                        </div>
                        
                        <div class="fplms-accordion-body" style="display: none; border-left: 5px solid <?php echo esc_attr( $tab_info['color'] ); ?>;">
                            
                            <?php if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) : ?>
                                <!-- Controles superiores: búsqueda y exportación -->
                                <div class="fplms-table-controls">
                                    <div class="fplms-table-search">
                                        <input type="text" 
                                               id="fplms-search-<?php echo esc_attr( $tab_key ); ?>" 
                                               class="fplms-search-input" 
                                               placeholder="Buscar por nombre..." 
                                               onkeyup="fplmsFilterTable('<?php echo esc_attr( $tab_key ); ?>')">  
                                    </div>
                                    
                                    <!-- Acciones masivas -->
                                    <div class="fplms-bulk-actions-container" id="fplms-bulk-actions-<?php echo esc_attr( $tab_key ); ?>">
                                        <strong><?php echo esc_html( $tab_info['label'] ); ?>:</strong>
                                        <span id="fplms-bulk-count-<?php echo esc_attr( $tab_key ); ?>">0 seleccionados</span>
                                        <select id="fplms-bulk-action-<?php echo esc_attr( $tab_key ); ?>" class="fplms-bulk-select">
                                            <option value="">-- Acciones masivas --</option>
                                            <option value="deactivate">Desactivar seleccionados</option>
                                            <option value="activate">Activar seleccionados</option>
                                            <option value="delete">Eliminar seleccionados</option>
                                        </select>
                                        <button type="button" 
                                                class="button fplms-bulk-apply-btn" 
                                                onclick="fplmsApplyBulkAction('<?php echo esc_attr( $tab_key ); ?>', '<?php echo esc_attr( $tab_info['taxonomy'] ); ?>')">
                                            Aplicar
                                        </button>
                                    </div>

                                    <div class="fplms-table-export">
                                        <form method="post" style="display: inline-block;" target="_blank">
                                            <?php wp_nonce_field( 'fplms_export_structures', 'fplms_export_nonce' ); ?>
                                            <input type="hidden" name="fplms_export_action" value="export_structures">
                                            <input type="hidden" name="fplms_export_type" value="<?php echo esc_attr( $tab_key ); ?>">
                                            <input type="hidden" name="fplms_export_format" id="fplms-export-format-<?php echo esc_attr( $tab_key ); ?>" value="xls">
                                            <input type="hidden" name="fplms_export_mode" id="fplms-export-mode-<?php echo esc_attr( $tab_key ); ?>" value="all">
                                            <input type="hidden" name="fplms_export_ids" id="fplms-export-ids-<?php echo esc_attr( $tab_key ); ?>" value="">
                                            
                                            <button type="button" 
                                                    class="button fplms-export-btn" 
                                                    onclick="fplmsExportStructures('<?php echo esc_attr( $tab_key ); ?>', 'xls', 'all')">
                                                <?php echo $this->fplms_svg( 'table' ); ?> Exportar XLS (Todo)
                                            </button>
                                            
                                            <button type="button" 
                                                    class="button fplms-export-btn" 
                                                    onclick="fplmsExportStructures('<?php echo esc_attr( $tab_key ); ?>', 'pdf', 'all')">
                                                <?php echo $this->fplms_svg( 'file' ); ?> Exportar PDF (Todo)
                                            </button>
                                            
                                            <button type="button" 
                                                    class="button fplms-export-btn fplms-export-selected" 
                                                    id="fplms-export-selected-<?php echo esc_attr( $tab_key ); ?>" 
                                                    onclick="fplmsExportStructures('<?php echo esc_attr( $tab_key ); ?>', 'xls', 'selected')" 
                                                    style="display: none;">
                                                <?php echo $this->fplms_svg( 'check' ); ?> Exportar Seleccionados
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <!-- Tabla de datos -->
                                <table class="fplms-data-table" id="fplms-table-<?php echo esc_attr( $tab_key ); ?>">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;">
                                                <input type="checkbox" 
                                                       id="fplms-check-all-<?php echo esc_attr( $tab_key ); ?>" 
                                                       onchange="fplmsToggleAll('<?php echo esc_attr( $tab_key ); ?>', this)">
                                            </th>
                                            <th>Nombre</th>
                                            <th>Descripción</th>
                                            <?php if ( 'city' !== $tab_key ) : ?>
                                                <?php
                                                $relation_labels = [
                                                    'company' => 'Ciudades',
                                                    'channel' => 'Empresas',
                                                    'branch'  => 'Canales',
                                                    'role'    => 'Sucursales',
                                                ];
                                                ?>
                                                <th><?php echo esc_html( $relation_labels[ $tab_key ] ); ?></th>
                                            <?php endif; ?>
                                            <th style="width: 100px;">Estado</th>
                                            <th style="width: 120px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $terms as $term ) : ?>
                                            <?php
                                            $active      = get_term_meta( $term->term_id, FairPlay_LMS_Config::META_ACTIVE, true );
                                            $active      = ( '1' === $active );
                                            $description = get_term_meta( $term->term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION, true );
                                            
                                            // Obtener relaciones según el tipo de término
                                            $parent_ids   = [];
                                            $parent_names = [];
                                            $parent_label = '';
                                            
                                            if ( 'company' === $tab_key ) {
                                                $parent_ids   = $this->get_term_cities( $term->term_id );
                                                $parent_label = 'ciudad';
                                            } elseif ( 'channel' === $tab_key ) {
                                                $parent_ids   = $this->get_term_companies( $term->term_id );
                                                $parent_label = 'empresa';
                                            } elseif ( 'branch' === $tab_key ) {
                                                $parent_ids   = $this->get_term_channels( $term->term_id );
                                                $parent_label = 'canal';
                                            } elseif ( 'role' === $tab_key ) {
                                                $parent_ids   = $this->get_term_branches( $term->term_id );
                                                $parent_label = 'sucursal';
                                            }
                                            
                                            foreach ( $parent_ids as $parent_id ) {
                                                $parent_name = $this->get_term_name_by_id( $parent_id );
                                                if ( $parent_name ) {
                                                    $parent_names[] = $parent_name;
                                                }
                                            }
                                            ?>
                                            <tr class="fplms-table-row" data-term-id="<?php echo esc_attr( $term->term_id ); ?>" data-term-name="<?php echo esc_attr( strtolower( $term->name ) ); ?>">
                                                <td>
                                                    <input type="checkbox" 
                                                           class="fplms-row-checkbox" 
                                                           data-tab="<?php echo esc_attr( $tab_key ); ?>" 
                                                           data-term-id="<?php echo esc_attr( $term->term_id ); ?>" 
                                                           onchange="fplmsUpdateExportButton('<?php echo esc_attr( $tab_key ); ?>')">
                                                </td>
                                                <td><strong><?php echo esc_html( $term->name ); ?></strong></td>
                                                <td><?php echo esc_html( $description ?: '-' ); ?></td>
                                                <?php if ( 'city' !== $tab_key ) : ?>
                                                    <td>
                                                        <?php if ( ! empty( $parent_names ) ) : ?>
                                                            <span class="fplms-relation-badge"><?php echo esc_html( implode( ', ', $parent_names ) ); ?></span>
                                                        <?php else : ?>
                                                            <span style="color: #999;">Sin relación</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                                <td>
                                                    <span class="fplms-status-badge <?php echo $active ? 'active' : 'inactive'; ?>">
                                                        <?php echo $this->fplms_svg( $active ? 'check' : 'x-circle' ); ?>
                                                        <?php echo $active ? 'Activo' : 'Inactivo'; ?>
                                                    </span>
                                                </td>
                                                <td class="fplms-table-actions">
                                                    <button type="button" class="fplms-btn fplms-btn-toggle" 
                                                        onclick="fplmsToggleStatus(<?php echo esc_attr( $term->term_id ); ?>, '<?php echo esc_js( $term->name ); ?>', '<?php echo esc_attr( $tab_info['taxonomy'] ); ?>', '<?php echo esc_attr( $tab_key ); ?>', <?php echo $active ? '1' : '0'; ?>)"
                                                        title="<?php echo $active ? 'Desactivar' : 'Activar'; ?>">
                                                        <?php echo $this->fplms_svg( $active ? 'toggle-on' : 'toggle-off' ); ?>
                                                    </button>
                                                    
                                                    <button type="button" class="fplms-btn fplms-btn-edit" 
                                                        onclick="fplmsToggleTableEditRow(<?php echo esc_attr( $term->term_id ); ?>, '<?php echo esc_attr( $tab_key ); ?>')"
                                                        title="Editar">
                                                        <?php echo $this->fplms_svg( 'pencil' ); ?>
                                                    </button>
                                                    
                                                    <button type="button" class="fplms-btn fplms-btn-delete" 
                                                        onclick="fplmsDeleteStructure(<?php echo esc_attr( $term->term_id ); ?>, '<?php echo esc_attr( $tab_info['taxonomy'] ); ?>', '<?php echo esc_attr( $tab_key ); ?>')"
                                                        title="Eliminar">
                                                        <?php echo $this->fplms_svg( 'trash' ); ?>
                                                    </button>
                                                </td>
                                            </tr>
                                            
                                            <!-- Fila de edición expandible -->
                                            <tr class="fplms-edit-row" id="fplms-edit-row-<?php echo esc_attr( $term->term_id ); ?>" style="display: none;">
                                                <td colspan="<?php echo 'city' === $tab_key ? '5' : '6'; ?>">
                                                    <!-- FORMA DE EDICIÓN INLINE -->
                                                    <?php if ( 'city' !== $tab_key ) : ?>
                                                    <div class="fplms-term-edit-form" style="padding: 16px; background: #f5f5f5;">
                                                        <form method="post" class="fplms-inline-edit-form" onsubmit="return fplmsSubmitEdit(event, this);">
                                                            <?php wp_nonce_field( 'fplms_structures_save', 'fplms_structures_nonce' ); ?>
                                                            <input type="hidden" name="fplms_structures_action" value="edit">
                                                            <input type="hidden" name="fplms_taxonomy" value="<?php echo esc_attr( $tab_info['taxonomy'] ); ?>">
                                                            <input type="hidden" name="fplms_term_id" value="<?php echo esc_attr( $term->term_id ); ?>">
                                                            <input type="hidden" name="fplms_tab" value="<?php echo esc_attr( $tab_key ); ?>">
                                                            
                                                            <div class="fplms-edit-fields-row">
                                                                <div class="fplms-edit-field">
                                                                    <label>Nombre</label>
                                                                    <input type="text" name="fplms_name" class="regular-text" value="<?php echo esc_attr( $term->name ); ?>" required>
                                                                </div>
                                                                
                                                                <!-- Campo Descripción en Edición -->
                                                                <div class="fplms-edit-field">
                                                                    <label for="fplms_edit_description_<?php echo esc_attr( $term->term_id ); ?>">
                                                                        <?php echo $this->fplms_svg( 'note' ); ?> Descripción
                                                                    </label>
                                                                    <?php 
                                                                    $current_description = get_term_meta( $term->term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION, true );
                                                                    ?>
                                                                    <textarea 
                                                                        id="fplms_edit_description_<?php echo esc_attr( $term->term_id ); ?>"
                                                                        name="fplms_description" 
                                                                        class="fplms-description-textarea" 
                                                                        maxlength="300" 
                                                                        rows="3"
                                                                        placeholder="Descripción breve (máximo 300 caracteres)..."><?php echo esc_textarea( $current_description ); ?></textarea>
                                                                    <small class="fplms-char-count" style="color: #666; font-size: 12px;">
                                                                        <span class="fplms-current-chars"><?php echo esc_html( strlen( $current_description ) ); ?></span>/300 caracteres
                                                                    </small>
                                                                </div>
                                                                
                                                                <?php if ( 'company' === $tab_key ) : ?>
                                                                <div class="fplms-edit-field fplms-parent-field">
                                                                    <label><?php echo $this->fplms_svg( 'pin' ); ?> Ciudades Relacionadas</label>
                                                                    <div class="fplms-parent-selector">
                                                                        <input type="text" class="fplms-parent-search" placeholder="Buscar ciudad...">
                                                                        <div class="fplms-parent-list">
                                                                            <?php
                                                                            $all_parents = $this->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CITY );
                                                                            $selected_parents = $this->get_term_cities( $term->term_id );
                                                                            foreach ( $all_parents as $parent_id => $parent_name ) :
                                                                            ?>
                                                                                <label class="fplms-parent-option">
                                                                                    <input type="checkbox" name="fplms_cities[]" value="<?php echo esc_attr( $parent_id ); ?>" <?php checked( in_array( $parent_id, $selected_parents, true ) ); ?>>
                                                                                    <span><?php echo esc_html( $parent_name ); ?></span>
                                                                                </label>
                                                                            <?php endforeach; ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <?php elseif ( 'channel' === $tab_key ) : ?>
                                                                <div class="fplms-edit-field fplms-parent-field">
                                                                    <label><?php echo $this->fplms_svg( 'building' ); ?> Empresas Relacionadas</label>
                                                                    <div class="fplms-parent-selector">
                                                                        <input type="text" class="fplms-parent-search" placeholder="Buscar empresa...">
                                                                        <div class="fplms-parent-list">
                                                                            <?php
                                                                            $all_parents = $this->get_active_terms_for_select( FairPlay_LMS_Config::TAX_COMPANY );
                                                                            $selected_parents = $this->get_term_companies( $term->term_id );
                                                                            foreach ( $all_parents as $parent_id => $parent_name ) :
                                                                                // Obtener ciudades de esta empresa
                                                                                $company_cities = $this->get_term_cities( $parent_id );
                                                                                $cities_json = ! empty( $company_cities ) ? implode( ',', $company_cities ) : '';
                                                                            ?>
                                                                                <label class="fplms-parent-option" data-parent-cities="<?php echo esc_attr( $cities_json ); ?>">
                                                                                    <input type="checkbox" name="fplms_companies[]" value="<?php echo esc_attr( $parent_id ); ?>" <?php checked( in_array( $parent_id, $selected_parents, true ) ); ?>>
                                                                                    <span><?php echo esc_html( $parent_name ); ?></span>
                                                                                </label>
                                                                            <?php endforeach; ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <?php elseif ( 'branch' === $tab_key ) : ?>
                                                                <div class="fplms-edit-field fplms-parent-field">
                                                                    <label><?php echo $this->fplms_svg( 'store' ); ?> Canales Relacionados</label>
                                                                    <div class="fplms-parent-selector">
                                                                        <input type="text" class="fplms-parent-search" placeholder="Buscar canal...">
                                                                        <div class="fplms-parent-list">
                                                                            <?php
                                                                            $all_parents = $this->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CHANNEL );
                                                                            $selected_parents = $this->get_term_channels( $term->term_id );
                                                                            foreach ( $all_parents as $parent_id => $parent_name ) :
                                                                                // Obtener empresas de este canal
                                                                                $channel_companies = $this->get_term_companies( $parent_id );
                                                                                $companies_json = ! empty( $channel_companies ) ? implode( ',', $channel_companies ) : '';
                                                                            ?>
                                                                                <label class="fplms-parent-option" data-parent-companies="<?php echo esc_attr( $companies_json ); ?>">
                                                                                    <input type="checkbox" name="fplms_channels[]" value="<?php echo esc_attr( $parent_id ); ?>" <?php checked( in_array( $parent_id, $selected_parents, true ) ); ?>>
                                                                                    <span><?php echo esc_html( $parent_name ); ?></span>
                                                                                </label>
                                                                            <?php endforeach; ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <?php elseif ( 'role' === $tab_key ) : ?>
                                                                <div class="fplms-edit-field fplms-parent-field">
                                                                    <label><?php echo $this->fplms_svg( 'branch' ); ?> Sucursales Relacionadas</label>
                                                                    <div class="fplms-parent-selector">
                                                                        <input type="text" class="fplms-parent-search" placeholder="Buscar sucursal...">
                                                                        <div class="fplms-parent-list">
                                                                            <?php
                                                                            $all_parents = $this->get_active_terms_for_select( FairPlay_LMS_Config::TAX_BRANCH );
                                                                            $selected_parents = $this->get_term_branches( $term->term_id );
                                                                            foreach ( $all_parents as $parent_id => $parent_name ) :
                                                                            ?>
                                                                                <label class="fplms-parent-option">
                                                                                    <input type="checkbox" name="fplms_branches[]" value="<?php echo esc_attr( $parent_id ); ?>" <?php checked( in_array( $parent_id, $selected_parents, true ) ); ?>>
                                                                                    <span><?php echo esc_html( $parent_name ); ?></span>
                                                                                </label>
                                                                            <?php endforeach; ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            
                                                            <div class="fplms-edit-actions-row">
                                                                <button type="button" class="button" onclick="location.reload()">Cancelar</button>
                                                                <button type="submit" class="button button-primary">Guardar Cambios</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                    <?php else : ?>
                                                    <!-- FORMA DE EDICIÓN INLINE PARA CIUDADES -->
                                                    <div class="fplms-term-edit-form" style="padding: 16px; background: #f5f5f5;">
                                                        <form method="post" class="fplms-inline-edit-form" onsubmit="return fplmsSubmitEdit(event, this);">
                                                            <?php wp_nonce_field( 'fplms_structures_save', 'fplms_structures_nonce' ); ?>
                                                            <input type="hidden" name="fplms_structures_action" value="edit">
                                                            <input type="hidden" name="fplms_taxonomy" value="<?php echo esc_attr( $tab_info['taxonomy'] ); ?>">
                                                            <input type="hidden" name="fplms_term_id" value="<?php echo esc_attr( $term->term_id ); ?>">
                                                            <input type="hidden" name="fplms_tab" value="<?php echo esc_attr( $tab_key ); ?>">
                                                            
                                                            <div class="fplms-edit-fields-row">
                                                                <div class="fplms-edit-field">
                                                                    <label>Nombre</label>
                                                                    <input type="text" name="fplms_name" class="regular-text" value="<?php echo esc_attr( $term->name ); ?>" required>
                                                                </div>
                                                                
                                                                <!-- Campo Descripción en Edición de Ciudad -->
                                                                <div class="fplms-edit-field">
                                                                    <label for="fplms_edit_description_<?php echo esc_attr( $term->term_id ); ?>">
                                                                        <?php echo $this->fplms_svg( 'note' ); ?> Descripción
                                                                    </label>
                                                                    <?php 
                                                                    $current_description = get_term_meta( $term->term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION, true );
                                                                    ?>
                                                                    <textarea 
                                                                        id="fplms_edit_description_<?php echo esc_attr( $term->term_id ); ?>"
                                                                        name="fplms_description" 
                                                                        class="fplms-description-textarea" 
                                                                        maxlength="300" 
                                                                        rows="3"
                                                                        placeholder="Descripción breve (máximo 300 caracteres)..."><?php echo esc_textarea( $current_description ); ?></textarea>
                                                                    <small class="fplms-char-count" style="color: #666; font-size: 12px;">
                                                                        <span class="fplms-current-chars"><?php echo esc_html( strlen( $current_description ) ); ?></span>/300 caracteres
                                                                    </small>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="fplms-edit-actions-row">
                                                                <button type="button" class="button" onclick="location.reload()">Cancelar</button>
                                                                <button type="submit" class="button button-primary">Guardar Cambios</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <!-- Paginación -->
                                <div class="fplms-pagination" id="fplms-pagination-<?php echo esc_attr( $tab_key ); ?>">
                                    <!-- Se genera dinámicamente con JavaScript -->
                                </div>
                                
                            <?php else : ?>
                                <div class="fplms-empty-state">
                                    <p><?php echo $this->fplms_svg( 'inbox', 18 ); ?> No hay <?php echo esc_html( strtolower( $tab_info['label'] ) ); ?> creadas todavía.</p>
                                </div>
                            <?php endif; ?>
                            
                            
                            <div class="fplms-new-item-form">
                                <h4><?php echo $this->fplms_svg( 'plus', 16 ); ?> Crear nuevo elemento</h4>
                                <form method="post" class="fplms-inline-form">
                                    <?php wp_nonce_field( 'fplms_structures_save', 'fplms_structures_nonce' ); ?>
                                    <input type="hidden" name="fplms_structures_action" value="create">
                                    <input type="hidden" name="fplms_taxonomy" value="<?php echo esc_attr( $tab_info['taxonomy'] ); ?>">
                                    <input type="hidden" name="fplms_tab" value="<?php echo esc_attr( $tab_key ); ?>">
                                    
                                    <div class="fplms-form-row">
                                        <?php
                                        $placeholders = [
                                            'city'    => 'Nombre de la ciudad...',
                                            'company' => 'Nombre de la empresa...',
                                            'channel' => 'Nombre del canal...',
                                            'branch'  => 'Nombre de la sucursal...',
                                            'role'    => 'Nombre del cargo...',
                                        ];
                                        $placeholder = $placeholders[ $tab_key ] ?? 'Nombre del elemento...';
                                        ?>
                                        <input name="fplms_name" type="text" class="regular-text" placeholder="<?php echo esc_attr( $placeholder ); ?>" required>
                                        
                                        <!-- Campo Descripción -->
                                        <div class="fplms-description-field" style="margin-top: 10px;">
                                            <label for="fplms_description_<?php echo esc_attr( $tab_key ); ?>">
                                                <?php echo $this->fplms_svg( 'note' ); ?> Descripción (opcional)
                                            </label>
                                            <textarea 
                                                id="fplms_description_<?php echo esc_attr( $tab_key ); ?>"
                                                name="fplms_description" 
                                                class="large-text fplms-description-textarea" 
                                                maxlength="300" 
                                                rows="3"
                                                placeholder="Descripción breve de la estructura (máximo 300 caracteres)..."></textarea>
                                            <small class="fplms-char-count" style="color: #666; font-size: 12px;">
                                                <span class="fplms-current-chars">0</span>/300 caracteres
                                            </small>
                                        </div>
                                        
                                        <?php if ( 'city' !== $tab_key ) : ?>
                                            <?php if ( 'company' === $tab_key ) : ?>
                                            <div class="fplms-edit-field fplms-parent-field">
                                                <label><?php echo $this->fplms_svg( 'pin' ); ?> Ciudades Asociadas</label>
                                                <div class="fplms-parent-selector">
                                                    <input type="text" 
                                                           class="fplms-parent-search" 
                                                           placeholder="Buscar ciudad...">
                                                    
                                                    <div class="fplms-parent-list">
                                                        <?php 
                                                        $parents = $this->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CITY );
                                                        foreach ( $parents as $parent_id => $parent_name ) : 
                                                        ?>
                                                        <label class="fplms-parent-option">
                                                            <input type="checkbox" 
                                                                   name="fplms_cities[]" 
                                                                   value="<?php echo esc_attr( $parent_id ); ?>">
                                                            <span><?php echo esc_html( $parent_name ); ?></span>
                                                        </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php elseif ( 'channel' === $tab_key ) : ?>
                                            <div class="fplms-edit-field fplms-parent-field">
                                                <label><?php echo $this->fplms_svg( 'building' ); ?> Empresas Asociadas</label>
                                                <div class="fplms-parent-selector">
                                                    <input type="text" 
                                                           class="fplms-parent-search" 
                                                           placeholder="Buscar empresa...">
                                                    
                                                    <div class="fplms-parent-list">
                                                        <?php 
                                                        $parents = $this->get_active_terms_for_select( FairPlay_LMS_Config::TAX_COMPANY );
                                                        foreach ( $parents as $parent_id => $parent_name ) : 
                                                            // Obtener ciudades asociadas a esta empresa
                                                            $company_cities = $this->get_term_cities( $parent_id );
                                                            $cities_json = ! empty( $company_cities ) ? implode( ',', $company_cities ) : '';
                                                        ?>
                                                        <label class="fplms-parent-option" data-parent-cities="<?php echo esc_attr( $cities_json ); ?>">
                                                            <input type="checkbox" 
                                                                   name="fplms_companies[]" 
                                                                   value="<?php echo esc_attr( $parent_id ); ?>">
                                                            <span><?php echo esc_html( $parent_name ); ?></span>
                                                        </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php elseif ( 'branch' === $tab_key ) : ?>
                                            <div class="fplms-edit-field fplms-parent-field">
                                                <label><?php echo $this->fplms_svg( 'store' ); ?> Canales Asociados</label>
                                                <div class="fplms-parent-selector">
                                                    <input type="text" 
                                                           class="fplms-parent-search" 
                                                           placeholder="Buscar canal...">
                                                    
                                                    <div class="fplms-parent-list">
                                                        <?php 
                                                        $parents = $this->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CHANNEL );
                                                        foreach ( $parents as $parent_id => $parent_name ) : 
                                                            // Obtener empresas asociadas a este canal
                                                            $channel_companies = $this->get_term_companies( $parent_id );
                                                            $companies_json = ! empty( $channel_companies ) ? implode( ',', $channel_companies ) : '';
                                                        ?>
                                                        <label class="fplms-parent-option" data-parent-companies="<?php echo esc_attr( $companies_json ); ?>">
                                                            <input type="checkbox" 
                                                                   name="fplms_channels[]" 
                                                                   value="<?php echo esc_attr( $parent_id ); ?>">
                                                            <span><?php echo esc_html( $parent_name ); ?></span>
                                                        </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php elseif ( 'role' === $tab_key ) : ?>
                                            <div class="fplms-edit-field fplms-parent-field">
                                                <label><?php echo $this->fplms_svg( 'branch' ); ?> Sucursales Asociadas</label>
                                                <div class="fplms-parent-selector">
                                                    <input type="text" 
                                                           class="fplms-parent-search" 
                                                           placeholder="Buscar sucursal...">
                                                    
                                                    <div class="fplms-parent-list">
                                                        <?php 
                                                        $parents = $this->get_active_terms_for_select( FairPlay_LMS_Config::TAX_BRANCH );
                                                        foreach ( $parents as $parent_id => $parent_name ) : 
                                                        ?>
                                                        <label class="fplms-parent-option">
                                                            <input type="checkbox" 
                                                                   name="fplms_branches[]" 
                                                                   value="<?php echo esc_attr( $parent_id ); ?>">
                                                            <span><?php echo esc_html( $parent_name ); ?></span>
                                                        </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
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
                        <h3><?php echo $this->fplms_svg( 'trash' ); ?> Confirmar Eliminación</h3>
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

            <!-- Modal de Confirmación de Guardar Cambios -->
            <div id="fplms-save-modal" class="fplms-modal" style="display:none;">
                <div class="fplms-modal-content" style="max-width: 450px;">
                    <div class="fplms-modal-header">
                        <h3><?php echo $this->fplms_svg( 'save' ); ?> Confirmar Cambios</h3>
                        <button class="fplms-modal-close" onclick="fplmsCloseSaveModal()">✕</button>
                    </div>
                    <div class="fplms-modal-body">
                        <p>¿Estás seguro de que deseas guardar los cambios realizados?</p>
                        <div style="background: #f0f7ff; padding: 12px; border-radius: 4px; border-left: 3px solid #0073aa; margin: 12px 0;">
                            <p style="margin: 0; color: #0073aa; font-weight: 600;" id="fplms_save_name"></p>
                            <p style="margin: 4px 0 0 0; color: #666; font-size: 13px;" id="fplms_save_details"></p>
                        </div>
                        <p style="color: #666; font-size: 12px; margin-bottom: 0;">Los cambios se aplicarán inmediatamente al sistema.</p>
                    </div>
                    <div class="fplms-modal-footer">
                        <button type="button" class="button" onclick="fplmsCloseSaveModal()">Cancelar</button>
                        <button type="button" class="button button-primary" style="background-color: #0073aa; border-color: #0073aa;" onclick="fplmsConfirmSaveChanges()">✓ Guardar Cambios</button>
                    </div>
                </div>
            </div>

            <!-- Modal de Confirmación de Cambio de Estado -->
            <div id="fplms-toggle-modal" class="fplms-modal" style="display:none;">
                <div class="fplms-modal-content" style="max-width: 450px;">
                    <div class="fplms-modal-header">
                        <h3 id="fplms-toggle-modal-title"><?php echo $this->fplms_svg( 'toggle-on' ); ?> Cambiar Estado</h3>
                        <button class="fplms-modal-close" onclick="fplmsCloseToggleModal()">✕</button>
                    </div>
                    <div class="fplms-modal-body">
                        <p id="fplms-toggle-modal-question">¿Estás seguro de que deseas cambiar el estado de este elemento?</p>
                        <div style="background: #fff3cd; padding: 12px; border-radius: 4px; border-left: 3px solid #ffc107; margin: 12px 0;">
                            <p style="margin: 0; color: #856404; font-weight: 600;" id="fplms_toggle_name"></p>
                            <p style="margin: 4px 0 0 0; color: #856404; font-size: 13px;" id="fplms_toggle_status"></p>
                        </div>
                        <p style="color: #666; font-size: 12px; margin-bottom: 0;">Este cambio se registrará en la bitácora del sistema.</p>
                    </div>
                    <div class="fplms-modal-footer">
                        <button type="button" class="button" onclick="fplmsCloseToggleModal()">Cancelar</button>
                        <button type="button" class="button button-primary" id="fplms-toggle-confirm-btn" onclick="fplmsConfirmToggleStatus()">✓ Cambiar Estado</button>
                    </div>
                </div>
            </div>

            <!-- Modal de Confirmación de Acciones Masivas -->
            <div id="fplms-bulk-modal" class="fplms-modal" style="display:none;">
                <div class="fplms-modal-content" style="max-width: 500px;">
                    <div class="fplms-modal-header">
                        <h3 id="fplms-bulk-modal-title"><?php echo $this->fplms_svg( 'warning' ); ?> Confirmar Acción Masiva</h3>
                        <button class="fplms-modal-close" onclick="fplmsCloseBulkModal()">✕</button>
                    </div>
                    <div class="fplms-modal-body">
                        <p id="fplms-bulk-modal-question">¿Estás seguro de que deseas realizar esta acción?</p>
                        <div style="background: #fff3cd; padding: 12px; border-radius: 4px; border-left: 3px solid #ffc107; margin: 12px 0;">
                            <p style="margin: 0; color: #856404; font-weight: 600;" id="fplms_bulk_action_text"></p>
                            <p style="margin: 4px 0 0 0; color: #856404; font-size: 13px;" id="fplms_bulk_elements"></p>
                        </div>
                        <div id="fplms-bulk-delete-warning" style="display: none; background: #ffebee; padding: 12px; border-radius: 4px; border-left: 3px solid #d32f2f; margin: 12px 0;">
                            <p style="margin: 0; color: #c62828; font-weight: 600;"><?php echo $this->fplms_svg( 'warning' ); ?> ADVERTENCIA: Esta acción es IRREVERSIBLE</p>
                            <p style="margin: 4px 0 0 0; color: #c62828; font-size: 13px;">Los elementos y sus relaciones se eliminarán permanentemente.</p>
                        </div>
                        <p style="color: #666; font-size: 12px; margin-bottom: 0;">Esta acción se registrará en la bitácora del sistema.</p>
                    </div>
                    <div class="fplms-modal-footer">
                        <button type="button" class="button" onclick="fplmsCloseBulkModal()">Cancelar</button>
                        <button type="button" class="button button-primary" id="fplms-bulk-confirm-btn" onclick="fplmsConfirmBulkAction()">✓ Confirmar</button>
                    </div>
                </div>
            </div>
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

            .fplms-edit-fields-row {
                display: flex;
                gap: 16px;
                flex-wrap: wrap;
            }

            .fplms-edit-actions-row {
                display: flex;
                gap: 10px;
                justify-content: flex-end;
                margin-top: 16px;
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

            /* SELECTOR GENÉRICO DE PADRES (Canales, Sucursales, Cargos) */
            .fplms-parent-selector {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .fplms-parent-search {
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 13px;
                width: 100%;
            }

            .fplms-parent-search:focus {
                outline: none;
                border-color: #0073aa;
                box-shadow: 0 0 0 2px rgba(0,115,170,0.1);
            }

            .fplms-parent-list {
                display: block;
                max-height: 240px;
                overflow-y: auto;
                padding: 4px;
                background: white;
                border: 1px solid #ddd;
                border-radius: 4px;
            }

            .fplms-parent-option {
                display: flex;
                align-items: center;
                gap: 6px;
                padding: 6px 10px;
                margin-bottom: 2px;
                background: white;
                border: 1px solid #ddd;
                border-radius: 3px;
                cursor: pointer;
                font-size: 12px;
                transition: background 0.2s ease, border-color 0.2s ease;
                white-space: normal;
                width: 100%;
                box-sizing: border-box;
            }

            .fplms-select-all-row {
                padding: 4px 4px 6px;
                border-bottom: 1px solid #e0e0e0;
                margin-bottom: 4px;
            }

            .fplms-select-all-label {
                display: flex;
                align-items: center;
                gap: 6px;
                cursor: pointer;
                font-size: 12px;
                font-weight: 600;
                color: #0073aa;
                padding: 4px 6px;
                border-radius: 3px;
                user-select: none;
            }

            .fplms-select-all-label:hover {
                background: #f0f7fc;
            }

            .fplms-parent-option:hover {
                background: #f0f0f0;
                border-color: #0073aa;
            }

            .fplms-parent-option input[type="checkbox"] {
                margin: 0;
                cursor: pointer;
            }

            .fplms-parent-option input[type="checkbox"]:checked {
                accent-color: #0073aa;
            }

            .fplms-parent-option input[type="checkbox"]:checked + span {
                color: #0073aa;
                font-weight: 600;
            }

            .fplms-parent-field {
                flex: 2;
                min-width: 300px;
            }

            /* ACCIONES DE EDICIÓN */
            .fplms-edit-actions-row .button {
                padding: 8px 16px;
                font-size: 13px;
            }

            /* RESPONSIVE PARA EDICIÓN */
            @media (max-width: 768px) {
                .fplms-edit-fields-row {
                    flex-direction: column;
                }

                .fplms-edit-field,
                .fplms-cities-field {
                    min-width: auto;
                    flex: 1 !important;
                }

                .fplms-edit-actions-row {
                    flex-direction: column;
                    gap: 8px;
                }

                .fplms-edit-actions-row .button {
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
                display: inline-flex;
                align-items: center;
                transition: transform 0.3s ease;
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

            /* === Estilos para Campo de Descripción === */
            .fplms-description-field {
                margin-bottom: 15px;
                width: 100%;
            }

            .fplms-description-field label {
                display: block;
                font-weight: 600;
                margin-bottom: 5px;
                color: #333;
            }

            .fplms-description-textarea {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-family: inherit;
                font-size: 14px;
                resize: vertical;
                transition: border-color 0.2s;
            }

            .fplms-description-textarea:focus {
                outline: none;
                border-color: #0073aa;
                box-shadow: 0 0 0 1px #0073aa;
            }

            .fplms-char-count {
                display: block;
                margin-top: 5px;
                text-align: right;
            }

            .fplms-edit-field .fplms-description-textarea {
                width: 100%;
                box-sizing: border-box;
            }
            </style>

            <script>
            var FPLMS_SVG_TRASH     = <?php echo wp_json_encode( $this->fplms_svg( 'trash', 16 ) ); ?>;
            var FPLMS_SVG_XCIRCLE   = <?php echo wp_json_encode( $this->fplms_svg( 'x-circle', 16 ) ); ?>;
            var FPLMS_SVG_CHKCIRCLE = <?php echo wp_json_encode( $this->fplms_svg( 'check-circle', 16 ) ); ?>;
            var FPLMS_SVG_WARNING   = <?php echo wp_json_encode( $this->fplms_svg( 'warning', 16 ) ); ?>;
            var FPLMS_SVG_CHECK     = <?php echo wp_json_encode( $this->fplms_svg( 'check', 16 ) ); ?>;
            var FPLMS_SVG_CHECK_LG  = <?php echo wp_json_encode( $this->fplms_svg( 'check', 32 ) ); ?>;
            var FPLMS_SVG_TOGGLE_ON  = <?php echo wp_json_encode( $this->fplms_svg( 'toggle-on', 16 ) ); ?>;
            var FPLMS_SVG_TOGGLE_OFF = <?php echo wp_json_encode( $this->fplms_svg( 'toggle-off', 16 ) ); ?>;
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
                
                // Verificar si hay mensaje de éxito en URL para mostrar notificación
                const urlParams = new URLSearchParams(window.location.search);
                const successMsg = urlParams.get('fplms_success');
                const activeTab = urlParams.get('tab');
                
                if (successMsg) {
                    fplmsShowSuccessNotification(decodeURIComponent(successMsg));
                    
                    // Limpiar URL sin recargar la página
                    const newUrl = window.location.pathname + '?page=' + urlParams.get('page');
                    window.history.replaceState({}, document.title, newUrl);
                }
                
                // Abrir acordeón de la tab activa si se especificó
                if (activeTab) {
                    const targetAccordion = document.querySelector('.fplms-accordion-header[data-tab="' + activeTab + '"]');
                    if (targetAccordion) {
                        setTimeout(() => {
                            targetAccordion.click();
                        }, 100);
                    }
                }

                // Cerrar modales al hacer clic fuera de ellos
                const deleteModal = document.getElementById('fplms-delete-modal');
                const saveModal = document.getElementById('fplms-save-modal');
                const toggleModal = document.getElementById('fplms-toggle-modal');
                const bulkModal = document.getElementById('fplms-bulk-modal');
                
                [deleteModal, saveModal, toggleModal, bulkModal].forEach(modal => {
                    if (modal) {
                        modal.addEventListener('click', function(event) {
                            if (event.target === modal) {
                                modal.style.display = 'none';
                            }
                        });
                    }
                });
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
                // Buscar en la estructura de tabla
                const row = event.target.closest('.fplms-table-row');
                let termName = 'este elemento';
                
                if (row) {
                    // En tabla: buscar en la segunda celda (columna Nombre)
                    const nameCell = row.querySelector('td:nth-child(2) strong');
                    if (nameCell) {
                        termName = nameCell.textContent;
                    }
                } else {
                    // Fallback para sistema antiguo de listas si existe
                    const termItem = event.target.closest('.fplms-term-item');
                    if (termItem) {
                        const termNameElement = termItem.querySelector('.fplms-term-name');
                        if (termNameElement) {
                            termName = termNameElement.textContent;
                        }
                    }
                }
                
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

            // Variables globales para el modal de guardado
            let saveData = {};

            function fplmsCloseSaveModal() {
                document.getElementById('fplms-save-modal').style.display = 'none';
            }

            // Variables globales para el modal de toggle status
            let toggleData = {};

            function fplmsToggleStatus(termId, termName, taxonomy, tab, currentStatus) {
                toggleData = { termId, termName, taxonomy, tab, currentStatus };
                
                // Configurar contenido del modal según el estado actual
                const isActive = currentStatus === 1 || currentStatus === '1';
                const newStatus = isActive ? 'Inactivo' : 'Activo';
                const action = isActive ? 'desactivar' : 'activar';

                document.getElementById('fplms-toggle-modal-title').innerHTML = (isActive ? FPLMS_SVG_TOGGLE_ON : FPLMS_SVG_TOGGLE_OFF) + ' ' + (isActive ? 'Desactivar' : 'Activar') + ' Elemento';
                document.getElementById('fplms-toggle-modal-question').textContent = `¿Estás seguro de que deseas ${action} este elemento?`;
                document.getElementById('fplms_toggle_name').textContent = `"${termName}"`;
                document.getElementById('fplms_toggle_status').textContent = `Estado actual: ${isActive ? 'Activo' : 'Inactivo'} → Cambiar a: ${newStatus}`;
                
                // Cambiar color del botón según la acción
                const confirmBtn = document.getElementById('fplms-toggle-confirm-btn');
                if (isActive) {
                    // Desactivar = naranja/amarillo
                    confirmBtn.style.backgroundColor = '#ff9800';
                    confirmBtn.style.borderColor = '#ff9800';
                } else {
                    // Activar = verde
                    confirmBtn.style.backgroundColor = '#4caf50';
                    confirmBtn.style.borderColor = '#4caf50';
                }
                
                document.getElementById('fplms-toggle-modal').style.display = 'flex';
            }

            function fplmsCloseToggleModal() {
                document.getElementById('fplms-toggle-modal').style.display = 'none';
            }

            function fplmsConfirmToggleStatus() {
                if (!toggleData.termId) return;

                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <?php wp_nonce_field( 'fplms_structures_save', 'fplms_structures_nonce' ); ?>
                    <input type="hidden" name="fplms_structures_action" value="toggle_active">
                    <input type="hidden" name="fplms_taxonomy" value="${toggleData.taxonomy}">
                    <input type="hidden" name="fplms_term_id" value="${toggleData.termId}">
                    <input type="hidden" name="fplms_tab" value="${toggleData.tab}">
                `;
                document.body.appendChild(form);
                form.submit();
            }

            // Variables globales para acciones masivas
            let bulkActionData = {};

            function fplmsApplyBulkAction(tabKey, taxonomy) {
                const select = document.getElementById('fplms-bulk-action-' + tabKey);
                const action = select.value;
                
                if (!action) {
                    alert('Por favor, selecciona una acción del menú.');
                    return;
                }
                
                // Obtener checkboxes marcados
                const table = document.getElementById('fplms-table-' + tabKey);
                const checkboxes = Array.from(table.querySelectorAll('.fplms-row-checkbox:checked'));
                
                if (checkboxes.length === 0) {
                    alert('Por favor, selecciona al menos un elemento.');
                    return;
                }
                
                // Obtener IDs y nombres
                const termIds = checkboxes.map(cb => cb.getAttribute('data-term-id'));
                const termNames = checkboxes.map(cb => {
                    const row = cb.closest('tr');
                    const nameCell = row.querySelector('td:nth-child(2) strong');
                    return nameCell ? nameCell.textContent : '';
                });
                
                // Guardar datos para el modal
                bulkActionData = {
                    action: action,
                    termIds: termIds,
                    termNames: termNames,
                    taxonomy: taxonomy,
                    tab: tabKey
                };
                
                // Configurar modal según la acción
                const modalTitle = document.getElementById('fplms-bulk-modal-title');
                const modalQuestion = document.getElementById('fplms-bulk-modal-question');
                const actionText = document.getElementById('fplms_bulk_action_text');
                const elementsText = document.getElementById('fplms_bulk_elements');
                const confirmBtn = document.getElementById('fplms-bulk-confirm-btn');
                const deleteWarning = document.getElementById('fplms-bulk-delete-warning');
                
                let actionLabel = '';
                let questionText = '';
                let btnColor = '';
                
                if (action === 'delete') {
                    modalTitle.innerHTML = FPLMS_SVG_TRASH + ' Eliminar Elementos';
                    actionLabel = 'Eliminar ' + termIds.length + ' elemento' + (termIds.length > 1 ? 's' : '');
                    questionText = '¿Estás seguro de que deseas ELIMINAR estos elementos?';
                    btnColor = '#d32f2f';
                    deleteWarning.style.display = 'block';
                } else if (action === 'deactivate') {
                    modalTitle.innerHTML = FPLMS_SVG_XCIRCLE + ' Desactivar Elementos';
                    actionLabel = 'Desactivar ' + termIds.length + ' elemento' + (termIds.length > 1 ? 's' : '');
                    questionText = '¿Estás seguro de que deseas DESACTIVAR estos elementos?';
                    btnColor = '#ff9800';
                    deleteWarning.style.display = 'none';
                } else if (action === 'activate') {
                    modalTitle.innerHTML = FPLMS_SVG_CHKCIRCLE + ' Activar Elementos';
                    actionLabel = 'Activar ' + termIds.length + ' elemento' + (termIds.length > 1 ? 's' : '');
                    questionText = '¿Estás seguro de que deseas ACTIVAR estos elementos?';
                    btnColor = '#4caf50';
                    deleteWarning.style.display = 'none';
                }
                
                modalQuestion.textContent = questionText;
                actionText.textContent = actionLabel;
                
                // Mostrar lista de elementos (máximo 10, luego "...")
                const displayNames = termNames.slice(0, 10);
                let namesHtml = displayNames.join(', ');
                if (termNames.length > 10) {
                    namesHtml += ', ... y ' + (termNames.length - 10) + ' más';
                }
                elementsText.textContent = namesHtml;
                
                // Cambiar color del botón
                confirmBtn.style.backgroundColor = btnColor;
                confirmBtn.style.borderColor = btnColor;
                
                // Mostrar modal
                document.getElementById('fplms-bulk-modal').style.display = 'flex';
            }

            function fplmsCloseBulkModal() {
                document.getElementById('fplms-bulk-modal').style.display = 'none';
            }

            function fplmsConfirmBulkAction() {
                if (!bulkActionData.termIds || bulkActionData.termIds.length === 0) return;
                
                // Crear formulario para enviar
                const form = document.createElement('form');
                form.method = 'POST';
                
                let actionValue = '';
                if (bulkActionData.action === 'delete') {
                    actionValue = 'bulk_delete';
                } else if (bulkActionData.action === 'deactivate') {
                    actionValue = 'bulk_deactivate';
                } else if (bulkActionData.action === 'activate') {
                    actionValue = 'bulk_activate';
                }
                
                let hiddenFields = `
                    <?php wp_nonce_field( 'fplms_structures_save', 'fplms_structures_nonce' ); ?>
                    <input type="hidden" name="fplms_structures_action" value="${actionValue}">
                    <input type="hidden" name="fplms_taxonomy" value="${bulkActionData.taxonomy}">
                    <input type="hidden" name="fplms_tab" value="${bulkActionData.tab}">
                `;
                
                // Agregar IDs
                bulkActionData.termIds.forEach((id, index) => {
                    hiddenFields += `<input type="hidden" name="fplms_term_ids[${index}]" value="${id}">`;
                });
                
                form.innerHTML = hiddenFields;
                document.body.appendChild(form);
                form.submit();
            }

            function fplmsConfirmSaveChanges() {
                if (!saveData.form) return;

                const form = saveData.form;
                const termItem = form.closest('.fplms-term-item');
                const editRow = form.closest('.fplms-edit-row'); // Para sistema de tablas
                
                // Obtener datos del formulario
                const termName = form.querySelector('input[name="fplms_name"]').value;
                const termDescription = form.querySelector('textarea[name="fplms_description"]') ? 
                                       form.querySelector('textarea[name="fplms_description"]').value : '';
                
                // Obtener relaciones seleccionadas (ciudades, empresas, canales, sucursales)
                let selectedParents = [];
                const parentCheckboxes = form.querySelectorAll('.fplms-parent-option input[type="checkbox"]:checked, .fplms-city-option input[type="checkbox"]:checked');
                selectedParents = Array.from(parentCheckboxes).map(cb => cb.value);
                
                // Crear formulario para envío
                const submitForm = document.createElement('form');
                submitForm.method = 'POST';
                submitForm.style.display = 'none';
                
                let nonceField = form.querySelector('input[name="fplms_structures_nonce"]');
                let nonce = nonceField ? nonceField.value : '';
                
                // Obtener valores del formulario original
                const termId = form.querySelector('input[name="fplms_term_id"]').value;
                const taxonomy = form.querySelector('input[name="fplms_taxonomy"]').value;
                const tab = form.querySelector('input[name="fplms_tab"]').value;
                
                // Construir HTML del formulario
                let hiddenInputs = `
                    <input type="hidden" name="fplms_structures_action" value="edit">
                    <input type="hidden" name="fplms_structures_nonce" value="${nonce}">
                    <input type="hidden" name="fplms_term_id" value="${termId}">
                    <input type="hidden" name="fplms_name" value="${termName}">
                    <input type="hidden" name="fplms_description" value="${termDescription}">
                    <input type="hidden" name="fplms_taxonomy" value="${taxonomy}">
                    <input type="hidden" name="fplms_tab" value="${tab}">
                `;
                
                // Agregar relaciones según la taxonomía
                if (taxonomy === 'fplms_company' && selectedParents.length > 0) {
                    selectedParents.forEach((parentId, index) => {
                        hiddenInputs += `<input type="hidden" name="fplms_cities[${index}]" value="${parentId}">`;
                    });
                } else if (taxonomy === 'fplms_channel' && selectedParents.length > 0) {
                    selectedParents.forEach((parentId, index) => {
                        hiddenInputs += `<input type="hidden" name="fplms_companies[${index}]" value="${parentId}">`;
                    });
                } else if (taxonomy === 'fplms_branch' && selectedParents.length > 0) {
                    selectedParents.forEach((parentId, index) => {
                        hiddenInputs += `<input type="hidden" name="fplms_channels[${index}]" value="${parentId}">`;
                    });
                } else if (taxonomy === 'fplms_job_role' && selectedParents.length > 0) {
                    selectedParents.forEach((parentId, index) => {
                        hiddenInputs += `<input type="hidden" name="fplms_branches[${index}]" value="${parentId}">`;
                    });
                }
                
                submitForm.innerHTML = hiddenInputs;
                document.body.appendChild(submitForm);
                
                // Mostrar mensaje de éxito
                const parentText = selectedParents.length > 0 ? ` con ${selectedParents.length} relación(es)` : '';
                fplmsShowSuccess(`✓ Cambios guardados: "${termName}"${parentText}`);

                // Cerrar modal
                fplmsCloseSaveModal();
                
                // Cerrar el formulario de edición inline (sistema antiguo de listas)
                if (termItem) {
                    const editForm = termItem.querySelector('.fplms-term-edit-form');
                    if (editForm) {
                        editForm.style.display = 'none';
                    }
                    
                    const editButton = termItem.querySelector('.fplms-term-header .fplms-btn-edit');
                    if (editButton) {
                        editButton.textContent = 'Editar Estructura';
                        editButton.classList.remove('fplms-cancel-edit');
                    }
                }
                
                // Cerrar fila de edición en tabla (sistema nuevo)
                if (editRow) {
                    editRow.style.display = 'none';
                }

                // Enviar formulario después de un breve delay
                setTimeout(() => submitForm.submit(), 300);
            }

            // Cerrar modales al hacer clic fuera
            window.addEventListener('click', function(event) {
                const editModal = document.getElementById('fplms-edit-modal');
                const deleteModal = document.getElementById('fplms-delete-modal');
                const saveModal = document.getElementById('fplms-save-modal');
                
                if (event.target === editModal) editModal.style.display = 'none';
                if (event.target === deleteModal) deleteModal.style.display = 'none';
                if (event.target === saveModal) saveModal.style.display = 'none';
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
             * Filtra opciones de padres (canales, sucursales, cargos) basado en búsqueda
             */
            function fplmsFilterParents(searchInput) {
                const parentList = searchInput.parentElement.querySelector('.fplms-parent-list');
                const searchTerm = searchInput.value.toLowerCase();
                const parentOptions = parentList.querySelectorAll('.fplms-parent-option');

                parentOptions.forEach(option => {
                    const parentName = option.textContent.toLowerCase();
                    if (parentName.includes(searchTerm)) {
                        option.style.display = 'flex';
                    } else {
                        option.style.display = 'none';
                    }
                });
            }

            /**
             * Activa o desactiva todos los checkboxes del selector padre
             */
            function fplmsToggleAllParents(selectAllCb) {
                var parentSelector = selectAllCb.closest('.fplms-parent-selector');
                if (!parentSelector) return;
                var parentList = parentSelector.querySelector('.fplms-parent-list');
                if (!parentList) return;
                var checkboxes = parentList.querySelectorAll('.fplms-parent-option input[type="checkbox"]');
                checkboxes.forEach(function(cb) {
                    cb.checked = selectAllCb.checked;
                });
            }

            /**
             * Inserta el control "Seleccionar todos" en cada selector de padres
             * y mantiene su estado sincronizado con los checks individuales
             */
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('.fplms-parent-selector').forEach(function(selector) {
                    var parentList = selector.querySelector('.fplms-parent-list');
                    if (!parentList) return;
                    var totalCbs = parentList.querySelectorAll('.fplms-parent-option input[type="checkbox"]');
                    if (totalCbs.length === 0) return;

                    var selectAllRow = document.createElement('div');
                    selectAllRow.className = 'fplms-select-all-row';
                    selectAllRow.innerHTML =
                        '<label class="fplms-select-all-label">'
                        + '<input type="checkbox" class="fplms-select-all-cb" onchange="fplmsToggleAllParents(this)">'
                        + '<span>Seleccionar todos</span>'
                        + '</label>';
                    selector.insertBefore(selectAllRow, parentList);

                    // Estado inicial
                    var selectAllCb = selectAllRow.querySelector('.fplms-select-all-cb');
                    var allChecked = Array.from(totalCbs).every(function(cb) { return cb.checked; });
                    var anyChecked = Array.from(totalCbs).some(function(cb) { return cb.checked; });
                    selectAllCb.checked = allChecked;
                    selectAllCb.indeterminate = !allChecked && anyChecked;
                });

                // Sincronizar "Seleccionar todos" cuando cambia un item individual
                document.addEventListener('change', function(e) {
                    if (!e.target.matches('.fplms-parent-option input[type="checkbox"]')) return;
                    var parentSelector = e.target.closest('.fplms-parent-selector');
                    if (!parentSelector) return;
                    var allCbs = parentSelector.querySelectorAll('.fplms-parent-list .fplms-parent-option input[type="checkbox"]');
                    var selectAllCb = parentSelector.querySelector('.fplms-select-all-cb');
                    if (!selectAllCb) return;
                    var allChecked = Array.from(allCbs).every(function(cb) { return cb.checked; });
                    var anyChecked = Array.from(allCbs).some(function(cb) { return cb.checked; });
                    selectAllCb.checked = allChecked;
                    selectAllCb.indeterminate = !allChecked && anyChecked;
                });
            });

            /**
             * Envía el formulario de edición inline - MUESTRA MODAL DE CONFIRMACIÓN
             */
            function fplmsSubmitEdit(event, form) {
                if (event) event.preventDefault();

                // Validación básica
                const termName = form.querySelector('input[name="fplms_name"]').value;
                if (!termName.trim()) {
                    alert('Por favor, ingresa un nombre para la estructura');
                    return false;
                }

                // Obtener relaciones seleccionadas (ciudades, empresas, canales, sucursales)
                let selectedParents = [];
                const parentCheckboxes = form.querySelectorAll('.fplms-parent-option input[type="checkbox"]:checked, .fplms-city-option input[type="checkbox"]:checked');
                selectedParents = Array.from(parentCheckboxes).map(cb => cb.value);
                
                // Obtener descripción si existe
                const descriptionField = form.querySelector('textarea[name="fplms_description"]');
                const hasDescription = descriptionField && descriptionField.value.trim().length > 0;
                
                // Preparar texto de detalles
                let detailsText = '';
                if (selectedParents.length > 0) {
                    detailsText += `${selectedParents.length} relación(es) seleccionada(s)`;
                }
                if (hasDescription) {
                    detailsText += (detailsText ? ' • ' : '') + `Descripción incluida`;
                }
                if (!detailsText) {
                    detailsText = 'Sin relaciones adicionales';
                }
                
                // Guardar datos en variable global
                saveData = { form: form };
                
                // Actualizar contenido del modal
                document.getElementById('fplms_save_name').textContent = `Elemento: "${termName}"`;
                document.getElementById('fplms_save_details').textContent = detailsText;
                
                // Mostrar modal
                document.getElementById('fplms-save-modal').style.display = 'flex';
                
                return false;
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
                            ${FPLMS_SVG_CHECK}
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
                            ${FPLMS_SVG_WARNING}
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
             * Muestra una notificación emergente de éxito (modal-style)
             */
            function fplmsShowSuccessNotification(message) {
                // Crear modal de notificación
                const modalHTML = `
                    <div id="fplms-success-modal-notification" class="fplms-modal" style="display: flex; z-index: 100000;">
                        <div class="fplms-modal-content" style="max-width: 500px; text-align: center;">
                            <div class="fplms-modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                                <h3 style="margin: 0; display: flex; align-items: center; justify-content: center; gap: 10px;">
                                    ${FPLMS_SVG_CHECK_LG}
                                    <span>¡Operación Exitosa!</span>
                                </h3>
                            </div>
                            <div class="fplms-modal-body" style="padding: 30px 20px;">
                                <p style="font-size: 16px; color: #333; margin: 0;">${message}</p>
                            </div>
                            <div class="fplms-modal-footer" style="padding: 15px 20px; background: #f8f9fa; border-top: 1px solid #dee2e6;">
                                <button type="button" class="button button-primary" onclick="fplmsCloseSuccessNotification()" style="padding: 10px 30px; font-size: 14px;">
                                    Aceptar
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                // Agregar modal al body si no existe
                if (!document.getElementById('fplms-success-modal-notification')) {
                    document.body.insertAdjacentHTML('beforeend', modalHTML);
                }
                
                // Auto-cerrar después de 5 segundos
                setTimeout(() => {
                    fplmsCloseSuccessNotification();
                }, 5000);
            }
            
            /**
             * Cierra el modal de notificación de éxito
             */
            function fplmsCloseSuccessNotification() {
                const modal = document.getElementById('fplms-success-modal-notification');
                if (modal) {
                    modal.style.display = 'none';
                    modal.remove();
                }
            }

            /**
             * Inicializa los controles de búsqueda de ciudades
             */
            document.addEventListener('DOMContentLoaded', function() {
                // Manejo de búsqueda de ciudades
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

                // Manejo de búsqueda de padres (genérico para canales, sucursales, cargos)
                const parentSearches = document.querySelectorAll('.fplms-parent-search');
                
                parentSearches.forEach(searchInput => {
                    searchInput.addEventListener('keyup', function(e) {
                        fplmsFilterParents(this);
                    });

                    // Permitir búsqueda inmediata
                    searchInput.addEventListener('input', function(e) {
                        fplmsFilterParents(this);
                    });
                });

                // Manejador para cambios en checkboxes de padres
                const parentCheckboxes = document.querySelectorAll('.fplms-parent-option input[type="checkbox"]');
                
                parentCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        // Lógica adicional si es necesaria
                    });
                });

                // === Contador de caracteres para descripciones ===
                document.querySelectorAll('.fplms-description-textarea').forEach(function(textarea) {
                    const container = textarea.closest('.fplms-description-field, .fplms-edit-field');
                    const counterSpan = container ? container.querySelector('.fplms-current-chars') : null;
                    
                    if (counterSpan) {
                        // Inicializar contador al cargar la página
                        const currentLength = textarea.value.length;
                        counterSpan.textContent = currentLength;
                        updateCounterColor(counterSpan, currentLength);

                        // Actualizar contador al escribir
                        textarea.addEventListener('input', function() {
                            const currentLength = this.value.length;
                            counterSpan.textContent = currentLength;
                            updateCounterColor(counterSpan, currentLength);
                        });
                    }
                });

                // Función para cambiar color del contador según la longitud
                function updateCounterColor(counterSpan, length) {
                    if (length >= 280) {
                        counterSpan.style.color = '#d63638'; // Rojo
                        counterSpan.style.fontWeight = 'bold';
                    } else if (length >= 250) {
                        counterSpan.style.color = '#f0b849'; // Amarillo
                        counterSpan.style.fontWeight = '600';
                    } else {
                        counterSpan.style.color = '#666'; // Gris normal
                        counterSpan.style.fontWeight = 'normal';
                    }
                }
            });
            
            // FUNCIONES DE TABLA
            
            /**
             * Inicializar todas las tablas con paginación
             */
            document.addEventListener('DOMContentLoaded', function() {
                const tables = document.querySelectorAll('.fplms-data-table');
                tables.forEach(table => {
                    const tabKey = table.id.replace('fplms-table-', '');
                    fplmsPaginateTable(tabKey, 1);
                });
            });
            
            /**
             * Filtrar tabla por búsqueda
             */
            function fplmsFilterTable(tabKey) {
                const input = document.getElementById('fplms-search-' + tabKey);
                const filter = input.value.toLowerCase();
                const table = document.getElementById('fplms-table-' + tabKey);
                const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
                
                let visibleCount = 0;
                for (let i = 0; i < rows.length; i++) {
                    const row = rows[i];
                    
                    // Ignorar filas de edición
                    if (row.classList.contains('fplms-edit-row')) {
                        continue;
                    }
                    
                    const termName = row.getAttribute('data-term-name') || '';
                    
                    if (termName.indexOf(filter) > -1) {
                        row.removeAttribute('data-filtered');
                        visibleCount++;
                    } else {
                        row.setAttribute('data-filtered', 'true');
                        row.style.display = 'none';
                        // También ocultar fila de edición si existe
                        const nextRow = row.nextElementSibling;
                        if (nextRow && nextRow.classList.contains('fplms-edit-row')) {
                            nextRow.style.display = 'none';
                        }
                    }
                }
                
                // Repaginar después de filtrar
                fplmsPaginateTable(tabKey, 1);
            }
            
            /**
             * Paginar tabla
             */
            function fplmsPaginateTable(tabKey, page) {
                const table = document.getElementById('fplms-table-' + tabKey);
                if (!table) return;
                
                const tbody = table.getElementsByTagName('tbody')[0];
                const allRows = Array.from(tbody.getElementsByTagName('tr'));
                const dataRows = [];
                
                // Obtener solo filas de datos (no filas de edición)
                // y que no estén ocultas por el filtro de búsqueda
                allRows.forEach(row => {
                    if (!row.classList.contains('fplms-edit-row')) {
                        // Verificar si está oculta por filtro de búsqueda
                        const isFilteredOut = row.hasAttribute('data-filtered') && row.getAttribute('data-filtered') === 'true';
                        if (!isFilteredOut) {
                            dataRows.push(row);
                        }
                    }
                });
                
                const rowsPerPage = 10;
                const totalPages = Math.ceil(dataRows.length / rowsPerPage);
                const startIndex = (page - 1) * rowsPerPage;
                const endIndex = startIndex + rowsPerPage;
                
                // Ocultar/mostrar filas según la página
                dataRows.forEach((row, index) => {
                    const shouldShow = (index >= startIndex && index < endIndex);
                    
                    if (shouldShow) {
                        row.style.display = '';
                        row.removeAttribute('data-page-hidden');
                    } else {
                        row.style.display = 'none';
                        row.setAttribute('data-page-hidden', 'true');
                        // Ocultar también la fila de edición si existe
                        const nextRow = row.nextElementSibling;
                        if (nextRow && nextRow.classList.contains('fplms-edit-row')) {
                            nextRow.style.display = 'none';
                        }
                    }
                });
                
                // Generar controles de paginación
                const pagination = document.getElementById('fplms-pagination-' + tabKey);
                if (!pagination) return;
                
                let html = '';
                
                if (totalPages > 1) {
                    // Botón Anterior
                    html += '<button class="fplms-pagination-btn" onclick="fplmsPaginateTable(\'' + tabKey + '\', ' + (page - 1) + ')" ' + (page === 1 ? 'disabled' : '') + '>« Anterior</button>';
                    
                    // Números de página
                    for (let i = 1; i <= totalPages; i++) {
                        if (i === 1 || i === totalPages || (i >= page - 2 && i <= page + 2)) {
                            html += '<button class="fplms-pagination-btn ' + (i === page ? 'active' : '') + '" onclick="fplmsPaginateTable(\'' + tabKey + '\', ' + i + ')">' + i + '</button>';
                        } else if (i === page - 3 || i === page + 3) {
                            html += '<span class="fplms-pagination-info">...</span>';
                        }
                    }
                    
                    // Botón Siguiente
                    html += '<button class="fplms-pagination-btn" onclick="fplmsPaginateTable(\'' + tabKey + '\', ' + (page + 1) + ')" ' + (page === totalPages ? 'disabled' : '') + '>Siguiente »</button>';
                    
                    // Info
                    html += '<span class="fplms-pagination-info">Página ' + page + ' de ' + totalPages + ' (' + dataRows.length + ' elementos)</span>';
                }
                
                pagination.innerHTML = html;
            }
            
            /**
             * Toggle de selección de todos los checkboxes
             */
            function fplmsToggleAll(tabKey, checkbox) {
                const table = document.getElementById('fplms-table-' + tabKey);
                const checkboxes = table.querySelectorAll('.fplms-row-checkbox');
                
                checkboxes.forEach(cb => {
                    cb.checked = checkbox.checked;
                });
                
                fplmsUpdateExportButton(tabKey);
            }
            
            /**
             * Actualizar botón de exportación según selección y acciones masivas
             */
            function fplmsUpdateExportButton(tabKey) {
                const table = document.getElementById('fplms-table-' + tabKey);
                const checkboxes = table.querySelectorAll('.fplms-row-checkbox:checked');
                const exportBtn = document.getElementById('fplms-export-selected-' + tabKey);
                const checkAll = document.getElementById('fplms-check-all-' + tabKey);
                const bulkContainer = document.getElementById('fplms-bulk-actions-' + tabKey);
                const bulkCount = document.getElementById('fplms-bulk-count-' + tabKey);
                
                if (checkboxes.length > 0) {
                    exportBtn.style.display = 'inline-block';
                    exportBtn.textContent = '✓ Exportar Seleccionados (' + checkboxes.length + ')';
                    
                    // Mostrar acciones masivas
                    if (bulkContainer) {
                        bulkContainer.classList.add('visible');
                    }
                    if (bulkCount) {
                        bulkCount.textContent = checkboxes.length + ' seleccionado' + (checkboxes.length > 1 ? 's' : '');
                    }
                } else {
                    exportBtn.style.display = 'none';
                    
                    // Ocultar acciones masivas
                    if (bulkContainer) {
                        bulkContainer.classList.remove('visible');
                    }
                }
                
                // Actualizar checkbox "Todos"
                const totalCheckboxes = table.querySelectorAll('.fplms-row-checkbox').length;
                if (checkboxes.length === totalCheckboxes && totalCheckboxes > 0) {
                    checkAll.checked = true;
                    checkAll.indeterminate = false;
                } else if (checkboxes.length > 0) {
                    checkAll.checked = false;
                    checkAll.indeterminate = true;
                } else {
                    checkAll.checked = false;
                    checkAll.indeterminate = false;
                }
            }
            
            /**
             * Exportar estructuras (todo o seleccionados)
             */
            function fplmsExportStructures(tabKey, format, mode) {
                const form = document.querySelector('#fplms-table-' + tabKey).closest('.fplms-accordion-body').querySelector('.fplms-table-export form');
                
                // Actualizar campos ocultos
                document.getElementById('fplms-export-format-' + tabKey).value = format;
                document.getElementById('fplms-export-mode-' + tabKey).value = mode;
                
                if (mode === 'selected') {
                    // Obtener IDs seleccionados de TODAS las páginas (no solo la visible)
                    const table = document.getElementById('fplms-table-' + tabKey);
                    const allCheckboxes = table.querySelectorAll('.fplms-row-checkbox:checked');
                    const ids = Array.from(allCheckboxes).map(cb => cb.getAttribute('data-term-id')).filter(id => id).join(',');
                    
                    if (!ids) {
                        alert('Por favor, selecciona al menos un elemento para exportar.');
                        return;
                    }
                    
                    document.getElementById('fplms-export-ids-' + tabKey).value = ids;
                } else {
                    document.getElementById('fplms-export-ids-' + tabKey).value = '';
                }
                
                // Enviar formulario
                form.submit();
            }
            
            /**
             * Toggle de fila de edición en tabla
             */
            function fplmsToggleTableEditRow(termId, tabKey) {
                const editRow = document.getElementById('fplms-edit-row-' + termId);
                
                if (!editRow) {
                    console.error('No se encontró la fila de edición para el término ' + termId);
                    return;
                }
                
                // Toggle display
                if (editRow.style.display === 'none' || !editRow.style.display) {
                    // Cerrar todas las demás filas de edición en esta tabla
                    const table = document.getElementById('fplms-table-' + tabKey);
                    const allEditRows = table.querySelectorAll('.fplms-edit-row');
                    allEditRows.forEach(row => {
                        if (row.id !== 'fplms-edit-row-' + termId) {
                            row.style.display = 'none';
                        }
                    });
                    
                    // Mostrar esta fila (usar valor explícito para que el toggle detecte correctamente)
                    editRow.style.display = 'table-row';
                } else {
                    editRow.style.display = 'none';
                }
            }
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
     * Obtener el nombre legible del tipo de estructura según la taxonomía.
     * 
     * @param string $taxonomy Taxonomía completa (ej: fplms_city, fplms_company)
     * @return string Nombre legible (city, company, channel, branch, role)
     */
    public function get_structure_type_name( string $taxonomy ): string {
        $type_map = [
            FairPlay_LMS_Config::TAX_CITY    => 'city',
            FairPlay_LMS_Config::TAX_COMPANY => 'company',
            FairPlay_LMS_Config::TAX_CHANNEL => 'channel',
            FairPlay_LMS_Config::TAX_BRANCH  => 'branch',
            FairPlay_LMS_Config::TAX_ROLE    => 'role',
        ];

        return $type_map[ $taxonomy ] ?? 'unknown';
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

        // Usar el nuevo sistema que soporta múltiples ciudades
        $terms = $this->get_terms_by_cities( $taxonomy, [ $city_term_id ] );

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
            FairPlay_LMS_Config::TAX_COMPANY,
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

    /**
     * Guarda múltiples empresas asignadas a un canal.
     * Serializa como JSON para almacenar en term meta.
     *
     * @param int   $term_id ID del término (canal)
     * @param array $company_ids Array de IDs de empresas
     * @return bool true si se guardó correctamente
     */
    public function save_term_companies( int $term_id, array $company_ids ): bool {

        if ( ! $term_id || empty( $company_ids ) ) {
            return false;
        }

        // Sanitizar y validar IDs
        $company_ids = array_map( 'absint', $company_ids );
        $company_ids = array_filter( $company_ids );

        if ( empty( $company_ids ) ) {
            return false;
        }

        // Eliminar duplicados
        $company_ids = array_unique( $company_ids );

        // Guardar como JSON serializado
        $serialized = wp_json_encode( $company_ids );
        update_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_COMPANIES, $serialized );

        return true;
    }

    /**
     * Obtiene todas las empresas asignadas a un canal.
     *
     * @param int $term_id ID del término (canal)
     * @return array Array de IDs de empresas
     */
    public function get_term_companies( int $term_id ): array {

        if ( ! $term_id ) {
            return [];
        }

        // Obtener empresas en formato JSON
        $serialized = get_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_COMPANIES, true );

        if ( $serialized ) {
            $company_ids = json_decode( $serialized, true );
            return is_array( $company_ids ) ? $company_ids : [];
        }

        return [];
    }

    /**
     * Obtiene términos (canales) que están asignados a una o varias empresas.
     *
     * @param string $taxonomy Taxonomía a consultar (debe ser fplms_channel)
     * @param array  $company_ids Array de IDs de empresas
     * @return array Array de canales que pertenecen a esas empresas
     */
    public function get_channels_by_companies( string $taxonomy, array $company_ids ): array {

        if ( $taxonomy !== FairPlay_LMS_Config::TAX_CHANNEL || empty( $company_ids ) ) {
            return [];
        }

        $company_ids = array_map( 'absint', array_filter( $company_ids ) );

        if ( empty( $company_ids ) ) {
            return [];
        }

        $all_terms = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
            ]
        );

        if ( is_wp_error( $all_terms ) || empty( $all_terms ) ) {
            return [];
        }

        $result = [];

        foreach ( $all_terms as $term ) {
            $term_companies = $this->get_term_companies( $term->term_id );

            // Si el término está en cualquiera de las empresas solicitadas
            if ( array_intersect( $term_companies, $company_ids ) ) {
                $result[] = $term;
            }
        }

        return $result;
    }

    /**
     * Obtiene todos los canales de una taxonomía con todas sus empresas asignadas.
     * Útil para mostrar en tabla cuáles empresas tiene cada canal.
     *
     * @param string $taxonomy Taxonomía a consultar (debe ser fplms_channel)
     * @return array Array con estructura: [term_id => ['name' => 'xxx', 'companies' => [1,2,3], 'active' => '1']]
     */
    public function get_channels_all_companies( string $taxonomy ): array {

        if ( $taxonomy !== FairPlay_LMS_Config::TAX_CHANNEL ) {
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
            $companies = $this->get_term_companies( $term->term_id );
            $result[ $term->term_id ] = [
                'name'      => $term->name,
                'companies' => $companies,
                'active'    => get_term_meta( $term->term_id, FairPlay_LMS_Config::META_ACTIVE, true ),
            ];
        }

        return $result;
    }

    /**
     * Guarda múltiples canales asignados a una sucursal.
     * Serializa como JSON para almacenar en term meta.
     *
     * @param int   $term_id ID del término (sucursal)
     * @param array $channel_ids Array de IDs de canales
     * @return bool true si se guardó correctamente
     */
    public function save_term_channels( int $term_id, array $channel_ids ): bool {

        if ( ! $term_id || empty( $channel_ids ) ) {
            return false;
        }

        // Sanitizar y validar IDs
        $channel_ids = array_map( 'absint', $channel_ids );
        $channel_ids = array_filter( $channel_ids );

        if ( empty( $channel_ids ) ) {
            return false;
        }

        // Eliminar duplicados
        $channel_ids = array_unique( $channel_ids );

        // Guardar como JSON serializado
        $serialized = wp_json_encode( $channel_ids );
        update_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_CHANNELS, $serialized );

        return true;
    }

    /**
     * Obtiene todos los canales asignados a una sucursal.
     *
     * @param int $term_id ID del término (sucursal)
     * @return array Array de IDs de canales
     */
    public function get_term_channels( int $term_id ): array {

        if ( ! $term_id ) {
            return [];
        }

        // Obtener canales en formato JSON
        $serialized = get_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_CHANNELS, true );

        if ( $serialized ) {
            $channel_ids = json_decode( $serialized, true );
            return is_array( $channel_ids ) ? $channel_ids : [];
        }

        return [];
    }

    /**
     * Obtiene términos (sucursales) que están asignados a uno o varios canales.
     *
     * @param string $taxonomy Taxonomía a consultar (debe ser fplms_branch)
     * @param array  $channel_ids Array de IDs de canales
     * @return array Array de sucursales que pertenecen a esos canales
     */
    public function get_branches_by_channels( string $taxonomy, array $channel_ids ): array {

        if ( $taxonomy !== FairPlay_LMS_Config::TAX_BRANCH || empty( $channel_ids ) ) {
            return [];
        }

        $channel_ids = array_map( 'absint', array_filter( $channel_ids ) );

        if ( empty( $channel_ids ) ) {
            return [];
        }

        $all_terms = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
            ]
        );

        if ( is_wp_error( $all_terms ) || empty( $all_terms ) ) {
            return [];
        }

        $result = [];

        foreach ( $all_terms as $term ) {
            $term_channels = $this->get_term_channels( $term->term_id );

            // Si el término está en cualquiera de los canales solicitados
            if ( array_intersect( $term_channels, $channel_ids ) ) {
                $result[] = $term;
            }
        }

        return $result;
    }

    /**
     * Obtiene todas las sucursales de una taxonomía con todos sus canales asignados.
     * Útil para mostrar en tabla cuáles canales tiene cada sucursal.
     *
     * @param string $taxonomy Taxonomía a consultar (debe ser fplms_branch)
     * @return array Array con estructura: [term_id => ['name' => 'xxx', 'channels' => [1,2,3], 'active' => '1']]
     */
    public function get_branches_all_channels( string $taxonomy ): array {

        if ( $taxonomy !== FairPlay_LMS_Config::TAX_BRANCH ) {
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
            $channels = $this->get_term_channels( $term->term_id );
            $result[ $term->term_id ] = [
                'name'     => $term->name,
                'channels' => $channels,
                'active'   => get_term_meta( $term->term_id, FairPlay_LMS_Config::META_ACTIVE, true ),
            ];
        }

        return $result;
    }

    /**
     * Guarda múltiples sucursales asignadas a un cargo.
     * Serializa como JSON para almacenar en term meta.
     *
     * @param int   $term_id ID del término (cargo)
     * @param array $branch_ids Array de IDs de sucursales
     * @return bool true si se guardó correctamente
     */
    public function save_term_branches( int $term_id, array $branch_ids ): bool {

        if ( ! $term_id || empty( $branch_ids ) ) {
            return false;
        }

        // Sanitizar y validar IDs
        $branch_ids = array_map( 'absint', $branch_ids );
        $branch_ids = array_filter( $branch_ids );

        if ( empty( $branch_ids ) ) {
            return false;
        }

        // Eliminar duplicados
        $branch_ids = array_unique( $branch_ids );

        // Guardar como JSON serializado
        $serialized = wp_json_encode( $branch_ids );
        update_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_BRANCHES, $serialized );

        return true;
    }

    /**
     * Obtiene todas las sucursales asignadas a un cargo.
     *
     * @param int $term_id ID del término (cargo)
     * @return array Array de IDs de sucursales
     */
    public function get_term_branches( int $term_id ): array {

        if ( ! $term_id ) {
            return [];
        }

        // Obtener sucursales en formato JSON
        $serialized = get_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_BRANCHES, true );

        if ( $serialized ) {
            $branch_ids = json_decode( $serialized, true );
            return is_array( $branch_ids ) ? $branch_ids : [];
        }

        return [];
    }

    /**
     * Obtiene términos (cargos) que están asignados a una o varias sucursales.
     *
     * @param string $taxonomy Taxonomía a consultar (debe ser fplms_job_role)
     * @param array  $branch_ids Array de IDs de sucursales
     * @return array Array de cargos que pertenecen a esas sucursales
     */
    public function get_roles_by_branches( string $taxonomy, array $branch_ids ): array {

        if ( $taxonomy !== FairPlay_LMS_Config::TAX_ROLE || empty( $branch_ids ) ) {
            return [];
        }

        $branch_ids = array_map( 'absint', array_filter( $branch_ids ) );

        if ( empty( $branch_ids ) ) {
            return [];
        }

        $all_terms = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
            ]
        );

        if ( is_wp_error( $all_terms ) || empty( $all_terms ) ) {
            return [];
        }

        $result = [];

        foreach ( $all_terms as $term ) {
            $term_branches = $this->get_term_branches( $term->term_id );

            // Si el término está en cualquiera de las sucursales solicitadas
            if ( array_intersect( $term_branches, $branch_ids ) ) {
                $result[] = $term;
            }
        }

        return $result;
    }

    /**
     * Obtiene todos los cargos de una taxonomía con todas sus sucursales asignadas.
     * Útil para mostrar en tabla cuáles sucursales tiene cada cargo.
     *
     * @param string $taxonomy Taxonomía a consultar (debe ser fplms_job_role)
     * @return array Array con estructura: [term_id => ['name' => 'xxx', 'branches' => [1,2,3], 'active' => '1']]
     */
    public function get_roles_all_branches( string $taxonomy ): array {

        if ( $taxonomy !== FairPlay_LMS_Config::TAX_ROLE ) {
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
            $branches = $this->get_term_branches( $term->term_id );
            $result[ $term->term_id ] = [
                'name'     => $term->name,
                'branches' => $branches,
                'active'   => get_term_meta( $term->term_id, FairPlay_LMS_Config::META_ACTIVE, true ),
            ];
        }

        return $result;
    }

    /**
     * Valida la integridad de la jerarquía.
     * Verifica que una relación sea válida según el tipo de taxonomía.
     *
     * @param string $taxonomy Taxonomía del término que se va a relacionar
     * @param int    $term_id ID del término
     * @param array  $parent_ids IDs de los términos padre (ciudades, canales o sucursales)
     * @return bool true si la relación es válida
     */
    public function validate_hierarchy( string $taxonomy, int $term_id, array $parent_ids ): bool {

        if ( ! $term_id || empty( $parent_ids ) ) {
            return false;
        }

        // Validar que no exista auto-referencia
        $parent_ids = array_map( 'absint', array_filter( $parent_ids ) );

        if ( in_array( $term_id, $parent_ids, true ) ) {
            return false; // El término no puede ser su propio padre
        }

        // Verificar que los IDs padres existan en la taxonomía correcta
        switch ( $taxonomy ) {
            case FairPlay_LMS_Config::TAX_COMPANY:
                // Las empresas se relacionan con ciudades
                $parent_taxonomy = FairPlay_LMS_Config::TAX_CITY;
                break;

            case FairPlay_LMS_Config::TAX_CHANNEL:
                // Los canales se relacionan con empresas
                $parent_taxonomy = FairPlay_LMS_Config::TAX_COMPANY;
                break;

            case FairPlay_LMS_Config::TAX_BRANCH:
                // Las sucursales se relacionan con canales
                $parent_taxonomy = FairPlay_LMS_Config::TAX_CHANNEL;
                break;

            case FairPlay_LMS_Config::TAX_ROLE:
                // Los cargos se relacionan con sucursales
                $parent_taxonomy = FairPlay_LMS_Config::TAX_BRANCH;
                break;

            default:
                return false;
        }

        // Verificar que todos los IDs padres existan
        foreach ( $parent_ids as $parent_id ) {
            $parent_term = get_term( $parent_id, $parent_taxonomy );
            if ( ! $parent_term || is_wp_error( $parent_term ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Handler AJAX genérico para obtener términos según su padre.
     * Soporta la nueva jerarquía: Ciudad -> Empresa -> Canal -> Sucursal -> Cargo
     */
    public function ajax_get_terms_by_parent(): void {

        if ( ! isset( $_POST['taxonomy'] ) ) {
            wp_send_json_error( 'Missing taxonomy parameter' );
        }

        $taxonomy = sanitize_text_field( wp_unslash( $_POST['taxonomy'] ) );

        // Determinar qué tipo de padre espera esta taxonomía
        $parent_value = 0;
        $terms_data = [];

        switch ( $taxonomy ) {
            case FairPlay_LMS_Config::TAX_COMPANY:
                // Empresas filtradas por ciudad
                if ( isset( $_POST['city_id'] ) ) {
                    $parent_value = absint( $_POST['city_id'] );
                    $terms_data = $this->get_terms_by_cities( $taxonomy, [ $parent_value ] );
                    // Fallback: si ninguna empresa tiene asignada la ciudad
                    // (datos aún no configurados), devolver todas las empresas
                    if ( empty( $terms_data ) ) {
                        $fallback = get_terms( [
                            'taxonomy'   => $taxonomy,
                            'hide_empty' => false,
                            'orderby'    => 'name',
                            'order'      => 'ASC',
                        ] );
                        $terms_data = is_wp_error( $fallback ) ? [] : $fallback;
                    }
                }
                break;

            case FairPlay_LMS_Config::TAX_CHANNEL:
                // Canales filtrados por empresa
                if ( isset( $_POST['company_id'] ) ) {
                    $parent_value = absint( $_POST['company_id'] );
                    $terms_data = $this->get_channels_by_companies( $taxonomy, [ $parent_value ] );
                }
                break;

            case FairPlay_LMS_Config::TAX_BRANCH:
                // Sucursales filtradas por canal
                if ( isset( $_POST['channel_id'] ) ) {
                    $parent_value = absint( $_POST['channel_id'] );
                    $terms_data = $this->get_branches_by_channels( $taxonomy, [ $parent_value ] );
                }
                break;

            case FairPlay_LMS_Config::TAX_ROLE:
                // Cargos filtrados por sucursal
                if ( isset( $_POST['branch_id'] ) ) {
                    $parent_value = absint( $_POST['branch_id'] );
                    $terms_data = $this->get_roles_by_branches( $taxonomy, [ $parent_value ] );
                }
                break;

            default:
                wp_send_json_error( 'Invalid taxonomy' );
                return;
        }

        if ( empty( $terms_data ) ) {
            wp_send_json_success( [] );
            return;
        }

        // Convertir términos a formato compatible con select
        $options = [];
        foreach ( $terms_data as $term ) {
            if ( isset( $term->term_id ) && isset( $term->name ) ) {
                $options[ $term->term_id ] = $term->name;
            }
        }

        wp_send_json_success( $options );
    }

    /**
     * AJAX: Obtiene estructuras en cascada basadas en las selecciones realizadas
     * Este método se usa en la interfaz de asignación de estructuras a cursos
     * Retorna todas las estructuras descendientes de las entidades seleccionadas
     * 
     * @return void Envía JSON response con las estructuras organizadas por nivel
     */
    public function ajax_get_cascade_structures(): void {
        
        // Verificar nonce
        check_ajax_referer( 'fplms_cascade', 'nonce' );
        
        // Obtener el nivel desde el que se inicia la cascada
        $level = isset( $_POST['level'] ) ? sanitize_text_field( wp_unslash( $_POST['level'] ) ) : '';
        
        // Obtener los IDs seleccionados
        $selected_ids = isset( $_POST['selected_ids'] ) ? json_decode( wp_unslash( $_POST['selected_ids'] ), true ) : [];
        
        if ( empty( $selected_ids ) || ! is_array( $selected_ids ) ) {
            wp_send_json_success( [
                'companies' => [],
                'channels'  => [],
                'branches'  => [],
                'roles'     => [],
            ] );
            return;
        }
        
        // Sanitizar IDs
        $selected_ids = array_map( 'absint', $selected_ids );
        
        $result = [
            'companies' => [],
            'channels'  => [],
            'branches'  => [],
            'roles'     => [],
        ];
        
        switch ( $level ) {
            case 'cities':
                // Desde ciudades: cargar empresas, canales, sucursales y cargos
                $companies_data = $this->get_terms_by_cities( FairPlay_LMS_Config::TAX_COMPANY, $selected_ids );
                
                if ( ! empty( $companies_data ) ) {
                    foreach ( $companies_data as $term ) {
                        $result['companies'][ $term->term_id ] = $term->name;
                    }
                    
                    // Obtener IDs de empresas para siguiente nivel
                    $company_ids = wp_list_pluck( $companies_data, 'term_id' );
                    
                    // Cargar canales de esas empresas
                    $channels_data = $this->get_channels_by_companies( FairPlay_LMS_Config::TAX_CHANNEL, $company_ids );
                    
                    if ( ! empty( $channels_data ) ) {
                        foreach ( $channels_data as $term ) {
                            $result['channels'][ $term->term_id ] = $term->name;
                        }
                        
                        // Obtener IDs de canales para siguiente nivel
                        $channel_ids = wp_list_pluck( $channels_data, 'term_id' );
                        
                        // Cargar sucursales de esos canales
                        $branches_data = $this->get_branches_by_channels( FairPlay_LMS_Config::TAX_BRANCH, $channel_ids );
                        
                        if ( ! empty( $branches_data ) ) {
                            foreach ( $branches_data as $term ) {
                                $result['branches'][ $term->term_id ] = $term->name;
                            }
                            
                            // Obtener IDs de sucursales para último nivel
                            $branch_ids = wp_list_pluck( $branches_data, 'term_id' );
                            
                            // Cargar cargos de esas sucursales
                            $roles_data = $this->get_roles_by_branches( FairPlay_LMS_Config::TAX_ROLE, $branch_ids );
                            
                            if ( ! empty( $roles_data ) ) {
                                foreach ( $roles_data as $term ) {
                                    $result['roles'][ $term->term_id ] = $term->name;
                                }
                            }
                        }
                    }
                }
                break;
                
            case 'companies':
                // Desde empresas: cargar canales, sucursales y cargos
                $channels_data = $this->get_channels_by_companies( FairPlay_LMS_Config::TAX_CHANNEL, $selected_ids );
                
                if ( ! empty( $channels_data ) ) {
                    foreach ( $channels_data as $term ) {
                        $result['channels'][ $term->term_id ] = $term->name;
                    }
                    
                    $channel_ids = wp_list_pluck( $channels_data, 'term_id' );
                    
                    $branches_data = $this->get_branches_by_channels( FairPlay_LMS_Config::TAX_BRANCH, $channel_ids );
                    
                    if ( ! empty( $branches_data ) ) {
                        foreach ( $branches_data as $term ) {
                            $result['branches'][ $term->term_id ] = $term->name;
                        }
                        
                        $branch_ids = wp_list_pluck( $branches_data, 'term_id' );
                        
                        $roles_data = $this->get_roles_by_branches( FairPlay_LMS_Config::TAX_ROLE, $branch_ids );
                        
                        if ( ! empty( $roles_data ) ) {
                            foreach ( $roles_data as $term ) {
                                $result['roles'][ $term->term_id ] = $term->name;
                            }
                        }
                    }
                }
                break;
                
            case 'channels':
                // Desde canales: cargar sucursales y cargos
                $branches_data = $this->get_branches_by_channels( FairPlay_LMS_Config::TAX_BRANCH, $selected_ids );
                
                if ( ! empty( $branches_data ) ) {
                    foreach ( $branches_data as $term ) {
                        $result['branches'][ $term->term_id ] = $term->name;
                    }
                    
                    $branch_ids = wp_list_pluck( $branches_data, 'term_id' );
                    
                    $roles_data = $this->get_roles_by_branches( FairPlay_LMS_Config::TAX_ROLE, $branch_ids );
                    
                    if ( ! empty( $roles_data ) ) {
                        foreach ( $roles_data as $term ) {
                            $result['roles'][ $term->term_id ] = $term->name;
                        }
                    }
                }
                break;
                
            case 'branches':
                // Desde sucursales: cargar solo cargos
                $roles_data = $this->get_roles_by_branches( FairPlay_LMS_Config::TAX_ROLE, $selected_ids );
                
                if ( ! empty( $roles_data ) ) {
                    foreach ( $roles_data as $term ) {
                        $result['roles'][ $term->term_id ] = $term->name;
                    }
                }
                break;
        }
        
        wp_send_json_success( $result );
    }

	/**
	 * Sincronizar canal con categoría de MasterStudy
	 * Crea o actualiza una categoría cuando se crea/actualiza un canal
	 *
	 * @param int   $term_id ID del canal
	 * @param int   $tt_id   Term taxonomy ID
	 * @param array $args    Argumentos pasados a wp_insert_term()
	 * @return void
	 */
	public function sync_channel_to_category( int $term_id, int $tt_id, array $args ): void {
		// Obtener el canal (el hook ya es específico para fplms_channel)
		$channel = get_term( $term_id, FairPlay_LMS_Config::TAX_CHANNEL );
		if ( is_wp_error( $channel ) || ! $channel ) {
			return;
		}

		// Verificar si ya existe una categoría vinculada
		$linked_category_id = get_term_meta( $term_id, 'fplms_linked_category_id', true );

		$category_args = [
			'taxonomy' => FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY,
			'slug'     => 'fplms-' . $channel->slug,
		];

		if ( $linked_category_id ) {
			// Actualizar categoría existente
			$category = get_term( $linked_category_id, FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY );
			
			if ( ! is_wp_error( $category ) && $category ) {
				wp_update_term(
					$linked_category_id,
					FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY,
					[
						'name'        => $channel->name,
						'description' => '🔗 Sincronizado con Canal: ' . $channel->name,
					]
				);
			} else {
				// Si la categoría fue eliminada, crear una nueva
				$linked_category_id = 0;
			}
		}

		if ( ! $linked_category_id ) {
			// Crear nueva categoría
			$result = wp_insert_term(
				$channel->name,
				FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY,
				array_merge(
					$category_args,
					[
						'description' => '🔗 Sincronizado con Canal: ' . $channel->name,
					]
				)
			);

			if ( ! is_wp_error( $result ) ) {
				$category_id = $result['term_id'];

				// Guardar relación bidireccional
				update_term_meta( $term_id, 'fplms_linked_category_id', $category_id );
				update_term_meta( $category_id, 'fplms_linked_channel_id', $term_id );

				// Registrar en auditoría si está disponible
				if ( class_exists( 'FairPlay_LMS_Audit_Logger' ) ) {
					$audit = new FairPlay_LMS_Audit_Logger();
					$audit->log_action(
						'channel_category_sync',
						'channel',
						$term_id,
						$channel->name,
						null,
						"Categoría creada: {$category_id}"
					);
				}
			}
		}
	}

	/**
	 * Obtener ID de categoría vinculada a un canal
	 *
	 * @param int $channel_id ID del canal
	 * @return int|false ID de categoría o false si no existe
	 */
	public function get_linked_category( int $channel_id ) {
		$category_id = get_term_meta( $channel_id, 'fplms_linked_category_id', true );
		
		if ( ! $category_id ) {
			return false;
		}

		// Verificar que la categoría aún existe
		$category = get_term( $category_id, FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY );
		
		return ( ! is_wp_error( $category ) && $category ) ? (int) $category_id : false;
	}

	/**
	 * Obtener ID de canal vinculado a una categoría
	 *
	 * @param int $category_id ID de categoría
	 * @return int|false ID de canal o false si no existe
	 */
	public function get_linked_channel( int $category_id ) {
		$channel_id = get_term_meta( $category_id, 'fplms_linked_channel_id', true );
		
		if ( ! $channel_id ) {
			return false;
		}

		// Verificar que el canal aún existe
		$channel = get_term( $channel_id, FairPlay_LMS_Config::TAX_CHANNEL );
		
		return ( ! is_wp_error( $channel ) && $channel ) ? (int) $channel_id : false;
	}

	/**
	 * Eliminar sincronización cuando se elimina un canal
	 *
	 * @param int     $term_id      ID del término
	 * @param int     $tt_id        Term taxonomy ID
	 * @param WP_Term $deleted_term Término eliminado
	 * @param array   $object_ids   IDs de objetos asociados
	 * @return void
	 */
	public function unsync_channel_on_delete( int $term_id, int $tt_id, $deleted_term, array $object_ids ): void {
		// El hook de delete ya es específico para fplms_channel, no necesitamos verificar

		$linked_category_id = get_term_meta( $term_id, 'fplms_linked_category_id', true );

		if ( $linked_category_id ) {
			// Eliminar la categoría asociada
			$category_term = get_term( $linked_category_id, FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY );
			$category_name = $category_term && ! is_wp_error( $category_term ) ? $category_term->name : "Categoría #{$linked_category_id}";

			// Eliminar el término de la taxonomía de categorías
			wp_delete_term( $linked_category_id, FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY );

			// Registrar en auditoría
			if ( class_exists( 'FairPlay_LMS_Audit_Logger' ) ) {
				$audit = new FairPlay_LMS_Audit_Logger();
				$audit->log_action(
					'channel_category_deleted',
					'channel',
					$term_id,
					$deleted_term->name ?? "Canal #{$term_id}",
					"Categoría eliminada: {$category_name} (ID: {$linked_category_id})",
					null
				);
			}
		}
	}

	/**
	 * Manejar solicitudes de exportación de estructuras
	 *
	 * @return void
	 */
	public function handle_export_request(): void {
		if ( ! isset( $_POST['fplms_export_action'] ) || $_POST['fplms_export_action'] !== 'export_structures' ) {
			return;
		}

		if ( ! isset( $_POST['fplms_export_nonce'] ) || ! wp_verify_nonce( $_POST['fplms_export_nonce'], 'fplms_export_structures' ) ) {
			wp_die( 'Nonce inválido' );
		}

		if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_STRUCTURES ) ) {
			wp_die( 'No tienes permisos para exportar estructuras' );
		}

		$type   = sanitize_text_field( wp_unslash( $_POST['fplms_export_type'] ?? '' ) );
		$format = sanitize_text_field( wp_unslash( $_POST['fplms_export_format'] ?? 'xls' ) );
		$mode   = sanitize_text_field( wp_unslash( $_POST['fplms_export_mode'] ?? 'all' ) );

		$term_ids = [];
		if ( $mode === 'selected' && ! empty( $_POST['fplms_export_ids'] ) ) {
			// Los IDs vienen como cadena separada por comas: "123,456,789"
			$ids_string = sanitize_text_field( wp_unslash( $_POST['fplms_export_ids'] ) );
			// Dividir por comas y convertir cada ID a entero
			$term_ids = array_map( 'absint', explode( ',', $ids_string ) );
			// Eliminar valores 0 o vacíos
			$term_ids = array_filter( $term_ids );
		}

		if ( $format === 'xls' ) {
			$this->export_structures_excel( $type, $term_ids );
		} else {
			$this->export_structures_pdf( $type, $term_ids );
		}

		exit;
	}

	/**
	 * Exportar estructuras a formato Excel (CSV UTF-8)
	 *
	 * @param string $type Tipo de estructura
	 * @param array  $term_ids IDs de términos a exportar
	 * @return void
	 */
	private function export_structures_excel( string $type, array $term_ids = [] ): void {
		$taxonomy_map = [
			'city'    => FairPlay_LMS_Config::TAX_CITY,
			'company' => FairPlay_LMS_Config::TAX_COMPANY,
			'channel' => FairPlay_LMS_Config::TAX_CHANNEL,
			'branch'  => FairPlay_LMS_Config::TAX_BRANCH,
			'role'    => FairPlay_LMS_Config::TAX_ROLE,
		];

		$label_map = [
			'city'    => 'Ciudades',
			'company' => 'Empresas',
			'channel' => 'Canales',
			'branch'  => 'Sucursales',
			'role'    => 'Cargos',
		];

		if ( ! isset( $taxonomy_map[ $type ] ) ) {
			wp_die( 'Tipo inválido' );
		}

		$taxonomy = $taxonomy_map[ $type ];
		$label    = $label_map[ $type ];

		// Obtener términos
		$args = [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		];

		if ( ! empty( $term_ids ) ) {
			$args['include'] = $term_ids;
		}

		$terms = get_terms( $args );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			wp_die( 'No hay datos para exportar' );
		}

		// Preparar headers
		$filename = "fplms-{$type}-" . date( 'Y-m-d-His' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		// UTF-8 BOM para Excel
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Headers de columnas
		$headers = [ 'ID', 'Nombre', 'Descripción', 'Estado' ];

		if ( $type !== 'city' ) {
			$relation_labels = [
				'company' => 'Ciudades',
				'channel' => 'Empresas',
				'branch'  => 'Canales',
				'role'    => 'Sucursales',
			];
			$headers[] = $relation_labels[ $type ];
		}

		fputcsv( $output, $headers );

		// Datos
		foreach ( $terms as $term ) {
			$active      = get_term_meta( $term->term_id, FairPlay_LMS_Config::META_ACTIVE, true );
			$description = get_term_meta( $term->term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION, true );

			$row = [
				$term->term_id,
				$term->name,
				$description ?: '',
				$active === '1' ? 'Activo' : 'Inactivo',
			];

			// Agregar relaciones
			if ( $type !== 'city' ) {
				$relations = [];
				if ( $type === 'company' ) {
					$parent_ids = $this->get_term_cities( $term->term_id );
				} elseif ( $type === 'channel' ) {
					$parent_ids = $this->get_term_companies( $term->term_id );
				} elseif ( $type === 'branch' ) {
					$parent_ids = $this->get_term_channels( $term->term_id );
				} else {
					$parent_ids = $this->get_term_branches( $term->term_id );
				}

				foreach ( $parent_ids as $parent_id ) {
					$parent_name = $this->get_term_name_by_id( $parent_id );
					if ( $parent_name ) {
						$relations[] = $parent_name;
					}
				}

				$row[] = implode( ', ', $relations );
			}

			fputcsv( $output, $row );
		}

		fclose( $output );
	}

	/**
	 * Exportar estructuras a formato PDF (HTML imprimible)
	 *
	 * @param string $type Tipo de estructura
	 * @param array  $term_ids IDs de términos a exportar
	 * @return void
	 */
	private function export_structures_pdf( string $type, array $term_ids = [] ): void {
		$taxonomy_map = [
			'city'    => FairPlay_LMS_Config::TAX_CITY,
			'company' => FairPlay_LMS_Config::TAX_COMPANY,
			'channel' => FairPlay_LMS_Config::TAX_CHANNEL,
			'branch'  => FairPlay_LMS_Config::TAX_BRANCH,
			'role'    => FairPlay_LMS_Config::TAX_ROLE,
		];

		$label_map = [
			'city'    => 'Ciudades',
			'company' => 'Empresas',
			'channel' => 'Canales',
			'branch'  => 'Sucursales',
			'role'    => 'Cargos',
		];

		if ( ! isset( $taxonomy_map[ $type ] ) ) {
			wp_die( 'Tipo inválido' );
		}

		$taxonomy = $taxonomy_map[ $type ];
		$label    = $label_map[ $type ];

		// Obtener términos
		$args = [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		];

		if ( ! empty( $term_ids ) ) {
			$args['include'] = $term_ids;
		}

		$terms = get_terms( $args );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			wp_die( 'No hay datos para exportar' );
		}

		// Generar HTML para impresión
		?>
		<!DOCTYPE html>
		<html lang="es">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php echo esc_html( $label ); ?> - FairPlay LMS</title>
			<style>
				@page { size: A4 landscape; margin: 1cm; }
				body { font-family: Arial, sans-serif; font-size: 10pt; margin: 0; padding: 20px; }
				h1 { text-align: center; color: #0073aa; margin-bottom: 10px; }
				.subtitle { text-align: center; color: #666; margin-bottom: 20px; font-size: 11pt; }
				table { width: 100%; border-collapse: collapse; margin-top: 15px; }
				th { background: #0073aa; color: white; padding: 10px 8px; text-align: left; font-weight: 600; font-size: 10pt; border: 1px solid #005a87; }
				td { border: 1px solid #ddd; padding: 8px; font-size: 9pt; vertical-align: top; }
				tr:nth-child(even) { background: #f9f9f9; }
				.status-active { color:#155724; font-weight: bold; background: #d4edda; padding: 3px 8px; border-radius: 3px; display: inline-block; }
				.status-inactive { color: #721c24; background: #f8d7da; padding: 3px 8px; border-radius: 3px; display: inline-block; }
				.btn-print { position: fixed; top: 20px; right: 20px; padding: 12px 24px; background: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 1000; }
				.btn-print:hover { background: #005a87; }
				@media print {
					.btn-print { display: none; }
					body { padding: 0; }
				}
			</style>
		</head>
		<body>
			<button class="btn-print" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
			
			<h1>📊 <?php echo esc_html( $label ); ?></h1>
			<p class="subtitle">Generado el <?php echo date( 'd/m/Y H:i:s' ); ?></p>

			<table>
				<thead>
					<tr>
						<th style="width: 50px;">ID</th>
						<th style="width: 180px;">Nombre</th>
						<th>Descripción</th>
						<th style="width: 80px;">Estado</th>
						<?php if ( $type !== 'city' ) : ?>
							<?php
							$relation_labels = [
								'company' => 'Ciudades',
								'channel' => 'Empresas',
								'branch'  => 'Canales',
								'role'    => 'Sucursales',
							];
							?>
							<th style="width: 200px;"><?php echo esc_html( $relation_labels[ $type ] ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $terms as $term ) : ?>
						<?php
						$active      = get_term_meta( $term->term_id, FairPlay_LMS_Config::META_ACTIVE, true );
						$description = get_term_meta( $term->term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION, true );
						$status_class = $active === '1' ? 'status-active' : 'status-inactive';
						$status_text  = $active === '1' ? 'Activo' : 'Inactivo';
						?>
						<tr>
							<td><?php echo esc_html( $term->term_id ); ?></td>
							<td><strong><?php echo esc_html( $term->name ); ?></strong></td>
							<td><?php echo esc_html( $description ?: '-' ); ?></td>
							<td><span class="<?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_text ); ?></span></td>
							<?php if ( $type !== 'city' ) : ?>
								<td>
									<?php
									$relations = [];
									if ( $type === 'company' ) {
										$parent_ids = $this->get_term_cities( $term->term_id );
									} elseif ( $type === 'channel' ) {
										$parent_ids = $this->get_term_companies( $term->term_id );
									} elseif ( $type === 'branch' ) {
										$parent_ids = $this->get_term_channels( $term->term_id );
									} else {
										$parent_ids = $this->get_term_branches( $term->term_id );
									}

									foreach ( $parent_ids as $parent_id ) {
										$parent_name = $this->get_term_name_by_id( $parent_id );
										if ( $parent_name ) {
											$relations[] = $parent_name;
										}
									}

									echo esc_html( implode( ', ', $relations ) ?: '-' );
									?>
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<script>
				window.onafterprint = function() {
					window.close();
				};
			</script>
		</body>
		</html>
		<?php
		exit;
	}
}
