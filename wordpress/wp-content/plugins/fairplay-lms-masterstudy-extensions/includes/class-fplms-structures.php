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

        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'city';

        $tabs = [
            'city'    => [
                'label'    => 'Ciudades',
                'taxonomy' => FairPlay_LMS_Config::TAX_CITY,
            ],
            'channel' => [
                'label'    => 'Canales / Franquicias',
                'taxonomy' => FairPlay_LMS_Config::TAX_CHANNEL,
            ],
            'branch'  => [
                'label'    => 'Sucursales',
                'taxonomy' => FairPlay_LMS_Config::TAX_BRANCH,
            ],
            'role'    => [
                'label'    => 'Cargos',
                'taxonomy' => FairPlay_LMS_Config::TAX_ROLE,
            ],
        ];

        if ( ! isset( $tabs[ $tab ] ) ) {
            $tab = 'city';
        }

        $current = $tabs[ $tab ];

        $terms = get_terms(
            [
                'taxonomy'   => $current['taxonomy'],
                'hide_empty' => false,
            ]
        );
        ?>
        <div class="wrap">
            <h1>Estructuras</h1>

            <h2 class="nav-tab-wrapper">
                <?php foreach ( $tabs as $key => $info ) : ?>
                    <?php
                    $class = ( $key === $tab ) ? 'nav-tab nav-tab-active' : 'nav-tab';
                    $url   = add_query_arg(
                        [
                            'page' => 'fplms-structures',
                            'tab'  => $key,
                        ],
                        admin_url( 'admin.php' )
                    );
                    ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>">
                        <?php echo esc_html( $info['label'] ); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <h2><?php echo esc_html( $current['label'] ); ?></h2>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Activo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) : ?>
                    <?php foreach ( $terms as $term ) : ?>
                        <?php
                        $active = get_term_meta( $term->term_id, FairPlay_LMS_Config::META_ACTIVE, true );
                        $active = ( '1' === $active );
                        ?>
                        <tr>
                            <td><?php echo esc_html( $term->name ); ?></td>
                            <td><?php echo $active ? 'Sí' : 'No'; ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field( 'fplms_structures_save', 'fplms_structures_nonce' ); ?>
                                    <input type="hidden" name="fplms_structures_action" value="toggle_active">
                                    <input type="hidden" name="fplms_taxonomy" value="<?php echo esc_attr( $current['taxonomy'] ); ?>">
                                    <input type="hidden" name="fplms_term_id" value="<?php echo esc_attr( $term->term_id ); ?>">
                                    <input type="hidden" name="fplms_tab" value="<?php echo esc_attr( $tab ); ?>">
                                    <button type="submit" class="button">
                                        <?php echo $active ? 'Desactivar' : 'Activar'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="3">No hay registros todavía.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>

            <h3 style="margin-top:2em;">Nuevo registro</h3>
            <form method="post">
                <?php wp_nonce_field( 'fplms_structures_save', 'fplms_structures_nonce' ); ?>
                <input type="hidden" name="fplms_structures_action" value="create">
                <input type="hidden" name="fplms_taxonomy" value="<?php echo esc_attr( $current['taxonomy'] ); ?>">
                <input type="hidden" name="fplms_tab" value="<?php echo esc_attr( $tab ); ?>">

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="fplms_name">Nombre</label></th>
                        <td>
                            <input name="fplms_name" id="fplms_name" type="text" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Activo</th>
                        <td>
                            <label>
                                <input name="fplms_active" type="checkbox" value="1" checked>
                                Marcar como activo
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">Guardar</button>
                </p>
            </form>
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
}
