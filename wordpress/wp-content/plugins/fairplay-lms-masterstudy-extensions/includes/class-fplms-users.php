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

    public function __construct(
        FairPlay_LMS_Structures_Controller $structures,
        FairPlay_LMS_Progress_Service $progress
    ) {
        $this->structures = $structures;
        $this->progress   = $progress;
    }

    // ==========================
    //   CAMPOS EN PERFIL USUARIO
    // ==========================

    public function render_user_structures_fields( $user ): void {

        if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_USERS ) && ! current_user_can( 'edit_user', $user->ID ) ) {
            return;
        }

        $user_city    = get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_CITY, true );
        $user_channel = get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_CHANNEL, true );
        $user_branch  = get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_BRANCH, true );
        $user_role    = get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_ROLE, true );

        $cities   = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CITY );
        $channels = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CHANNEL );
        $branches = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_BRANCH );
        $roles    = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_ROLE );
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

        $roles_def = [
            FairPlay_LMS_Config::ROLE_STUDENT,
            FairPlay_LMS_Config::ROLE_TUTOR,
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

        $cities   = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CITY );
        $channels = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CHANNEL );
        $branches = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_BRANCH );
        $roles    = $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_ROLE );

        $filter_city    = isset( $_GET['fplms_filter_city'] ) ? absint( $_GET['fplms_filter_city'] ) : 0;
        $filter_channel = isset( $_GET['fplms_filter_channel'] ) ? absint( $_GET['fplms_filter_channel'] ) : 0;
        $filter_branch  = isset( $_GET['fplms_filter_branch'] ) ? absint( $_GET['fplms_filter_branch'] ) : 0;
        $filter_role    = isset( $_GET['fplms_filter_role'] ) ? absint( $_GET['fplms_filter_role'] ) : 0;

        $users  = $this->get_users_filtered_by_structure( $filter_city, $filter_channel, $filter_branch, $filter_role );
        $matrix = FairPlay_LMS_Capabilities::get_matrix();
        $can_edit = current_user_can( 'manage_options' );

        $roles_def_labels = [
            FairPlay_LMS_Config::ROLE_STUDENT => 'Alumno FairPlay (fplms_student)',
            FairPlay_LMS_Config::ROLE_TUTOR   => 'Tutor FairPlay (fplms_tutor)',
            FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR => 'Instructor MasterStudy (stm_lms_instructor)',
            'administrator'                   => 'Administrador (administrator)',
        ];

        $caps_def_labels = [
            FairPlay_LMS_Config::CAP_MANAGE_STRUCTURES => 'Gestionar estructuras',
            FairPlay_LMS_Config::CAP_MANAGE_USERS      => 'Gestionar usuarios',
            FairPlay_LMS_Config::CAP_MANAGE_COURSES    => 'Gestionar cursos',
            FairPlay_LMS_Config::CAP_VIEW_REPORTS      => 'Ver informes',
            FairPlay_LMS_Config::CAP_VIEW_PROGRESS     => 'Ver avances',
            FairPlay_LMS_Config::CAP_VIEW_CALENDAR     => 'Ver calendario',
        ];
        ?>
        <div class="wrap">
            <h1>Usuarios</h1>

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

            <h2 style="margin-top:2em;">Crear nuevo usuario</h2>

            <?php if ( isset( $_GET['user_created'] ) ) : ?>
                <div id="message" class="updated notice notice-success is-dismissible">
                    <p>Usuario creado correctamente. ID: <?php echo esc_html( absint( $_GET['user_created'] ) ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( isset( $_GET['error'] ) ) : ?>
                <div id="message" class="error notice notice-error is-dismissible">
                    <p>
                        <?php
                        $error_msg = sanitize_text_field( wp_unslash( $_GET['error'] ) );
                        echo 'incomplete_data' === $error_msg ? 'Datos incompletos. Verifica que llenes todos los campos requeridos.' : 'Error al crear el usuario. Verifica que el usuario no exista.';
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" style="margin-bottom:2em;">
                <?php wp_nonce_field( 'fplms_new_user_save', 'fplms_new_user_nonce' ); ?>
                <input type="hidden" name="fplms_new_user_action" value="create_user">

                <table class="form-table">
                    <tr>
                        <th><label for="fplms_user_login">Usuario *</label></th>
                        <td>
                            <input type="text" id="fplms_user_login" name="fplms_user_login" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="fplms_user_email">Email *</label></th>
                        <td>
                            <input type="email" id="fplms_user_email" name="fplms_user_email" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="fplms_user_pass">Contraseña *</label></th>
                        <td>
                            <input type="password" id="fplms_user_pass" name="fplms_user_pass" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="fplms_first_name">Nombre</label></th>
                        <td>
                            <input type="text" id="fplms_first_name" name="fplms_first_name" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="fplms_last_name">Apellido</label></th>
                        <td>
                            <input type="text" id="fplms_last_name" name="fplms_last_name" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label>Roles *</label></th>
                        <td>
                            <?php foreach ( $roles_def_labels as $role_key => $role_label ) : ?>
                                <label>
                                    <input type="checkbox" name="fplms_roles[]" value="<?php echo esc_attr( $role_key ); ?>">
                                    <?php echo esc_html( $role_label ); ?>
                                </label><br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="fplms_city">Ciudad</label></th>
                        <td>
                            <select name="fplms_city" id="fplms_city">
                                <option value="">— Sin asignar —</option>
                                <?php foreach ( $cities as $id => $name ) : ?>
                                    <option value="<?php echo esc_attr( $id ); ?>">
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
                                <?php foreach ( $channels as $id => $name ) : ?>
                                    <option value="<?php echo esc_attr( $id ); ?>">
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
                                <?php foreach ( $branches as $id => $name ) : ?>
                                    <option value="<?php echo esc_attr( $id ); ?>">
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
                                <?php foreach ( $roles as $id => $name ) : ?>
                                    <option value="<?php echo esc_attr( $id ); ?>">
                                        <?php echo esc_html( $name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">Crear usuario</button>
                </p>
            </form>

            <h2 style="margin-top:2em;">Usuarios por estructura</h2>

            <form method="get" style="margin-bottom:1em;">
                <input type="hidden" name="page" value="fplms-users">
                <table class="form-table">
                    <tr>
                        <th>Ciudad</th>
                        <td>
                            <select name="fplms_filter_city">
                                <option value="">Todas</option>
                                <?php foreach ( $cities as $id => $name ) : ?>
                                    <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $filter_city, $id ); ?>>
                                        <?php echo esc_html( $name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <th>Canal</th>
                        <td>
                            <select name="fplms_filter_channel">
                                <option value="">Todos</option>
                                <?php foreach ( $channels as $id => $name ) : ?>
                                    <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $filter_channel, $id ); ?>>
                                        <?php echo esc_html( $name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Sucursal</th>
                        <td>
                            <select name="fplms_filter_branch">
                                <option value="">Todas</option>
                                <?php foreach ( $branches as $id => $name ) : ?>
                                    <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $filter_branch, $id ); ?>>
                                        <?php echo esc_html( $name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <th>Cargo</th>
                        <td>
                            <select name="fplms_filter_role">
                                <option value="">Todos</option>
                                <?php foreach ( $roles as $id => $name ) : ?>
                                    <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $filter_role, $id ); ?>>
                                        <?php echo esc_html( $name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button">Filtrar</button>
                </p>
            </form>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Rol(es)</th>
                        <th>Ciudad</th>
                        <th>Canal</th>
                        <th>Sucursal</th>
                        <th>Cargo</th>
                        <th>Avance (resumen)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $users ) ) : ?>
                        <?php foreach ( $users as $user ) : ?>
                            <?php
                            $city_name    = $this->structures->get_term_name_by_id( get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_CITY, true ) );
                            $channel_name = $this->structures->get_term_name_by_id( get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_CHANNEL, true ) );
                            $branch_name  = $this->structures->get_term_name_by_id( get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_BRANCH, true ) );
                            $role_name    = $this->structures->get_term_name_by_id( get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_ROLE, true ) );
                            $progress     = $this->progress->get_user_progress_summary( $user->ID );
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>">
                                        <?php echo esc_html( $user->display_name ); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html( $user->user_email ); ?></td>
                                <td><?php echo esc_html( implode( ', ', $user->roles ) ); ?></td>
                                <td><?php echo esc_html( $city_name ); ?></td>
                                <td><?php echo esc_html( $channel_name ); ?></td>
                                <td><?php echo esc_html( $branch_name ); ?></td>
                                <td><?php echo esc_html( $role_name ); ?></td>
                                <td><?php echo esc_html( $progress ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="8">No se encontraron usuarios con estos filtros.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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
            $city_id    = isset( $_POST['fplms_city'] ) ? absint( $_POST['fplms_city'] ) : 0;
            $channel_id = isset( $_POST['fplms_channel'] ) ? absint( $_POST['fplms_channel'] ) : 0;
            $branch_id  = isset( $_POST['fplms_branch'] ) ? absint( $_POST['fplms_branch'] ) : 0;
            $role_id    = isset( $_POST['fplms_job_role'] ) ? absint( $_POST['fplms_job_role'] ) : 0;
            $user_roles = isset( $_POST['fplms_roles'] ) && is_array( $_POST['fplms_roles'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['fplms_roles'] ) ) : [];

            // Validar datos requeridos
            if ( ! $user_login || ! $user_email || ! $user_pass ) {
                wp_safe_redirect(
                    add_query_arg(
                        [ 'page' => 'fplms-users', 'error' => 'incomplete_data' ],
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
                        [ 'page' => 'fplms-users', 'error' => 'user_exists' ],
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

            // Asignar roles - Remover "subscriber" automático de wp_create_user
            $user = new WP_User( $user_id );
            // Remover rol "subscriber" que wp_create_user() asigna automáticamente
            $user->remove_role( 'subscriber' );
            // Asignar solo los roles seleccionados
            foreach ( $user_roles as $role ) {
                $user->add_role( $role );
            }

            // Asignar estructuras
            if ( $city_id ) {
                update_user_meta( $user_id, FairPlay_LMS_Config::USER_META_CITY, $city_id );
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

            wp_safe_redirect(
                add_query_arg(
                    [ 'page' => 'fplms-users', 'user_created' => $user_id ],
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }
    }
}
