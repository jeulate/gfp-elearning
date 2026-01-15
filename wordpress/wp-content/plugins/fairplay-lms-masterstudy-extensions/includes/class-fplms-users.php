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

    /**
     * Registra la fecha y hora del último login del usuario.
     * Se ejecuta cuando un usuario inicia sesión mediante el hook wp_login.
     *
     * @param string $user_login Nombre de usuario que inició sesión
     * @param WP_User $user Objeto del usuario
     */
    public function record_user_login( string $user_login, $user ): void {
        if ( isset( $user->ID ) ) {
            $current_time = current_time( 'mysql' );
            update_user_meta( $user->ID, 'last_login', $current_time );
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
        
        // Obtener datos de estructura para cascada
        $structure_relations = $this->get_structure_relations();
        
        ?>
        <div class="wrap">
            <h1>Usuarios</h1>

            <!-- BOTONES DE ACCIONES RÁPIDAS -->
            <div style="margin: 20px 0; display: flex; gap: 10px;">
                <a href="#crear-usuario" class="button button-primary" id="btn-crear-usuario">
                    ➕ Crear usuario
                </a>
                <a href="#matriz-privilegios" class="button" id="btn-matriz-privilegios">
                    🔐 Matriz de privilegios
                </a>
            </div>

            <!-- TABLA DE USUARIOS -->
            <h2 style="margin-top: 30px;">Usuarios registrados</h2>

            <?php if ( isset( $_GET['user_created'] ) ) : ?>
                <div id="message" class="updated notice notice-success is-dismissible">
                    <p>Usuario creado correctamente. ID: <?php echo esc_html( absint( $_GET['user_created'] ) ); ?></p>
                </div>
            <?php endif; ?>

            <form method="get" style="margin-bottom:1em;">
                <input type="hidden" name="page" value="fplms-users">
                <table class="form-table">
                    <tr>
                        <th>Ciudad</th>
                        <td>
                            <select name="fplms_filter_city" id="fplms_filter_city">
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
                            <select name="fplms_filter_channel" id="fplms_filter_channel">
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
                            <select name="fplms_filter_branch" id="fplms_filter_branch">
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
                            <select name="fplms_filter_role" id="fplms_filter_role">
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

            <style>
                .fplms-users-table-wrapper {
                    border: 1px solid #ddd;
                    border-radius: 6px;
                    overflow: hidden;
                    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
                    background-color: white;
                }
                
                .fplms-users-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 0;
                }
                
                .fplms-users-table thead {
                    background: linear-gradient(135deg, #f5f5f5 0%, #f9f9f9 100%);
                    border-bottom: 2px solid #e0e0e0;
                }
                
                .fplms-users-table thead th {
                    padding: 14px 16px;
                    text-align: left;
                    font-weight: 600;
                    color: #333;
                    font-size: 13px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                .fplms-users-table tbody tr {
                    border-bottom: 1px solid #f0f0f0;
                    transition: background-color 0.2s ease, box-shadow 0.2s ease;
                }
                
                .fplms-users-table tbody tr:hover {
                    background-color: #fafafa;
                    box-shadow: inset 0 0 0 1px #f0f0f0;
                }
                
                .fplms-users-table tbody tr:last-child {
                    border-bottom: none;
                }
                
                .fplms-users-table td {
                    padding: 14px 16px;
                    color: #555;
                    font-size: 14px;
                }
                
                .fplms-users-table td:first-child {
                    font-weight: 600;
                    color: #1976d2;
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
            </style>

            <div class="fplms-users-table-wrapper">
            <table class="fplms-users-table widefat striped">
                <thead>
                    <tr>
                        <th>Usuarios</th>
                        <th>Correo</th>
                        <th>Fecha de Registro</th>
                        <th>Último Inicio de Sesión</th>
                        <th>Acciones</th>
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
                            ?>
                            <tr>
                                <td>
                                    <?php echo esc_html( $user->first_name . ' ' . $user->last_name ); ?>
                                </td>
                                <td><?php echo esc_html( $user->user_email ); ?></td>
                                <td><?php echo esc_html( date( 'd/m/Y', strtotime( $user_registered ) ) ); ?></td>
                                <td><?php echo esc_html( $last_login ); ?></td>
                                <td class="actions-cell">
                                    <a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>" title="Ver perfil" class="action-link">👁️</a>
                                    <a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>" title="Editar usuario" class="action-link">✏️</a>
                                    <a href="#" onclick="if(confirm('¿Estás seguro de que deseas eliminar este usuario?')) { window.location.href='<?php echo esc_url( add_query_arg( [ 'action' => 'delete_user', 'user_id' => $user->ID ], admin_url( 'admin.php?page=fplms-users' ) ) ); ?>'; } return false;" title="Eliminar usuario" class="action-link">🗑️</a>
                                    <a href="<?php echo esc_url( add_query_arg( [ 'user_id' => $user->ID ], admin_url( 'admin.php?page=fplms-progress' ) ) ); ?>" title="Ver progreso" class="action-link">📊</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5">No se encontraron usuarios con estos filtros.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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

            <!-- SECCIÓN CREAR USUARIO MEJORADA (Oculta inicialmente) -->
            <div id="crear-usuario" style="margin-top: 40px; display: none;">
                <h2>Crear nuevo usuario</h2>

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

                <style>
                    .fplms-create-user-container {
                        background: white;
                        border-radius: 8px;
                        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                        overflow: hidden;
                        margin-bottom: 2em;
                    }

                    .fplms-user-form-wrapper {
                        display: grid;
                        grid-template-columns: 1fr 2fr;
                        gap: 40px;
                        padding: 40px;
                    }

                    @media (max-width: 1024px) {
                        .fplms-user-form-wrapper {
                            grid-template-columns: 1fr;
                            gap: 30px;
                        }
                    }

                    .fplms-user-image-section {
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: flex-start;
                    }

                    .fplms-user-image-upload {
                        width: 100%;
                        max-width: 280px;
                        aspect-ratio: 1;
                        border: 2px dashed #e0a05d;
                        border-radius: 8px;
                        background: #fff8f0;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        cursor: pointer;
                        transition: all 0.3s ease;
                        position: relative;
                        overflow: hidden;
                    }

                    .fplms-user-image-upload:hover {
                        background: #ffe8d1;
                        border-color: #ff9800;
                    }

                    .fplms-user-image-upload.has-image {
                        border: none;
                        background: transparent;
                    }

                    .fplms-user-image-upload img {
                        width: 100%;
                        height: 100%;
                        object-fit: cover;
                        border-radius: 8px;
                    }

                    .fplms-user-image-placeholder {
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        width: 100%;
                        height: 100%;
                        color: #ff9800;
                        font-size: 14px;
                        text-align: center;
                        padding: 20px;
                    }

                    .fplms-user-image-placeholder span {
                        font-size: 48px;
                        margin-bottom: 10px;
                    }

                    #fplms_user_photo {
                        display: none;
                    }

                    .fplms-user-form-fields {
                        display: flex;
                        flex-direction: column;
                        gap: 20px;
                    }

                    .fplms-form-row {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 20px;
                    }

                    .fplms-form-row.full {
                        grid-template-columns: 1fr;
                    }

                    .fplms-form-group {
                        display: flex;
                        flex-direction: column;
                    }

                    .fplms-form-group label {
                        font-weight: 600;
                        color: #333;
                        margin-bottom: 8px;
                        font-size: 14px;
                    }

                    .fplms-form-group label .required {
                        color: #f44336;
                        margin-left: 4px;
                    }

                    .fplms-form-group input[type="text"],
                    .fplms-form-group input[type="email"],
                    .fplms-form-group input[type="password"],
                    .fplms-form-group select {
                        padding: 12px;
                        border: 1px solid #ddd;
                        border-radius: 6px;
                        font-size: 14px;
                        font-family: inherit;
                        transition: border-color 0.3s ease, box-shadow 0.3s ease;
                    }

                    .fplms-form-group input[type="text"]:focus,
                    .fplms-form-group input[type="email"]:focus,
                    .fplms-form-group input[type="password"]:focus,
                    .fplms-form-group select:focus {
                        outline: none;
                        border-color: #ff9800;
                        box-shadow: 0 0 0 3px rgba(255, 152, 0, 0.1);
                    }

                    .fplms-form-section-title {
                        font-weight: 700;
                        color: #333;
                        margin-top: 10px;
                        margin-bottom: 15px;
                        padding-bottom: 10px;
                        border-bottom: 2px solid #f0f0f0;
                        font-size: 13px;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                        color: #666;
                    }

                    .fplms-form-checkboxes {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 12px;
                        padding: 12px;
                        background: #fafafa;
                        border-radius: 6px;
                    }

                    .fplms-form-checkboxes label {
                        display: flex;
                        align-items: center;
                        cursor: pointer;
                        font-weight: normal;
                        margin-bottom: 0;
                    }

                    .fplms-form-checkboxes input[type="checkbox"] {
                        margin-right: 10px;
                        cursor: pointer;
                        width: 18px;
                        height: 18px;
                        accent-color: #ff9800;
                    }

                    .fplms-form-checkbox-active {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        padding: 12px;
                        background: #fafafa;
                        border-radius: 6px;
                    }

                    .fplms-form-checkbox-active input[type="checkbox"] {
                        width: 20px;
                        height: 20px;
                        accent-color: #4caf50;
                        cursor: pointer;
                    }

                    .fplms-form-checkbox-active label {
                        font-weight: 500;
                        margin: 0;
                        cursor: pointer;
                    }

                    .fplms-form-actions {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 15px;
                        margin-top: 30px;
                        padding-top: 20px;
                        border-top: 1px solid #eee;
                    }

                    .fplms-form-actions button {
                        padding: 14px 30px;
                        border: none;
                        border-radius: 6px;
                        font-size: 16px;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.3s ease;
                    }

                    .fplms-form-actions .button-primary {
                        background: #1976d2;
                        color: white;
                    }

                    .fplms-form-actions .button-primary:hover {
                        background: #1565c0;
                        transform: translateY(-2px);
                        box-shadow: 0 4px 8px rgba(25, 118, 210, 0.3);
                    }

                    .fplms-form-actions .button-secondary {
                        background: #f5f5f5;
                        color: #333;
                    }

                    .fplms-form-actions .button-secondary:hover {
                        background: #e0e0e0;
                    }
                </style>

                <div class="fplms-create-user-container">
                    <form method="post" class="fplms-user-form-wrapper" enctype="multipart/form-data" id="form-crear-usuario">
                        <?php wp_nonce_field( 'fplms_new_user_save', 'fplms_new_user_nonce' ); ?>
                        <input type="hidden" name="fplms_new_user_action" value="create_user">

                        <!-- SECCIÓN IZQUIERDA: IMAGEN -->
                        <div class="fplms-user-image-section">
                            <div class="fplms-user-image-upload" id="fplms-image-upload-area">
                                <div class="fplms-user-image-placeholder">
                                    <span>📷</span>
                                    <div>Haz clic para subir fotografía</div>
                                </div>
                            </div>
                            <input type="file" id="fplms_user_photo" name="fplms_user_photo" accept="image/*">
                        </div>

                        <!-- SECCIÓN DERECHA: FORMULARIO -->
                        <div class="fplms-user-form-fields">
                            <!-- Datos Personales -->
                            <div>
                                <div class="fplms-form-section-title">Datos Personales</div>
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

                            <!-- Credenciales -->
                            <div>
                                <div class="fplms-form-section-title">Credenciales de Acceso</div>
                                <div class="fplms-form-row full">
                                    <div class="fplms-form-group">
                                        <label for="fplms_user_login">Nombre de usuario <span class="required">*</span></label>
                                        <input type="text" id="fplms_user_login" name="fplms_user_login" required>
                                    </div>
                                </div>
                                <div class="fplms-form-row full">
                                    <div class="fplms-form-group">
                                        <label for="fplms_user_email">Correo electrónico <span class="required">*</span></label>
                                        <input type="email" id="fplms_user_email" name="fplms_user_email" required>
                                    </div>
                                </div>
                                <div class="fplms-form-row full">
                                    <div class="fplms-form-group">
                                        <label for="fplms_user_pass">Contraseña <span class="required">*</span></label>
                                        <input type="password" id="fplms_user_pass" name="fplms_user_pass" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Estructura Organizacional CON CASCADA -->
                            <div>
                                <div class="fplms-form-section-title">Estructura Organizacional</div>
                                <div class="fplms-form-row">
                                    <div class="fplms-form-group">
                                        <label for="fplms_city">Ciudad</label>
                                        <select name="fplms_city" id="fplms_city">
                                            <option value="">— Sin asignar —</option>
                                            <?php foreach ( $cities as $id => $name ) : ?>
                                                <option value="<?php echo esc_attr( $id ); ?>">
                                                    <?php echo esc_html( $name ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="fplms-form-group">
                                        <label for="fplms_channel">Canal / Franquicia</label>
                                        <select name="fplms_channel" id="fplms_channel">
                                            <option value="">— Sin asignar —</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="fplms-form-row">
                                    <div class="fplms-form-group">
                                        <label for="fplms_branch">Sucursal</label>
                                        <select name="fplms_branch" id="fplms_branch">
                                            <option value="">— Sin asignar —</option>
                                        </select>
                                    </div>
                                    <div class="fplms-form-group">
                                        <label for="fplms_job_role">Cargo</label>
                                        <select name="fplms_job_role" id="fplms_job_role">
                                            <option value="">— Sin asignar —</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Tipo de usuario -->
                            <div>
                                <div class="fplms-form-section-title">Tipo de Usuario <span class="required">*</span></div>
                                <div class="fplms-form-checkboxes">
                                    <?php foreach ( $roles_def_labels as $role_key => $role_label ) : ?>
                                        <label>
                                            <input type="checkbox" name="fplms_roles[]" value="<?php echo esc_attr( $role_key ); ?>">
                                            <?php echo esc_html( $role_label ); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Estado del usuario -->
                            <div class="fplms-form-checkbox-active">
                                <input type="checkbox" id="fplms_user_active" name="fplms_user_active" value="1" checked>
                                <label for="fplms_user_active">Activo</label>
                            </div>

                            <!-- Botones de acción -->
                            <div class="fplms-form-actions">
                                <button type="submit" class="button button-primary">Guardar</button>
                                <button type="button" class="button button-secondary" onclick="document.getElementById('crear-usuario').style.display='none'; window.scrollTo(0, 0);">Cancelar</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
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

                    // CASCADA DE SELECTS: CIUDAD -> CANAL -> SUCURSAL -> CARGO
                    const citySelect = document.getElementById('fplms_city');
                    const channelSelect = document.getElementById('fplms_channel');
                    const branchSelect = document.getElementById('fplms_branch');
                    const jobRoleSelect = document.getElementById('fplms_job_role');

                    // Función para actualizar un select basado en otro
                    function updateSelectOptions(parentSelect, childSelect, taxonomy) {
                        if (!parentSelect || !childSelect) return;

                        parentSelect.addEventListener('change', function() {
                            const parentValue = this.value;
                            
                            if (!parentValue) {
                                // Si no hay selección, resetear a opciones iniciales
                                childSelect.innerHTML = '<option value="">— Sin asignar —</option>';
                                return;
                            }

                            // Mostrar indicador de carga
                            childSelect.innerHTML = '<option value="">Cargando...</option>';

                            // Hacer petición AJAX
                            const formData = new FormData();
                            formData.append('action', 'fplms_get_terms_by_city');
                            formData.append('city_id', parentValue);
                            formData.append('taxonomy', taxonomy);

                            fetch(ajaxurl, {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success && data.data) {
                                    let html = '';
                                    for (const [termId, termName] of Object.entries(data.data)) {
                                        html += '<option value="' + termId + '">' + termName + '</option>';
                                    }
                                    childSelect.innerHTML = html;
                                } else {
                                    childSelect.innerHTML = '<option value="">Error al cargar opciones</option>';
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                childSelect.innerHTML = '<option value="">Error al cargar opciones</option>';
                            });
                        });
                    }

                    // Configurar cascadas
                    if (citySelect) {
                        updateSelectOptions(citySelect, channelSelect, '<?php echo FairPlay_LMS_Config::TAX_CHANNEL; ?>');
                        updateSelectOptions(citySelect, branchSelect, '<?php echo FairPlay_LMS_Config::TAX_BRANCH; ?>');
                        updateSelectOptions(citySelect, jobRoleSelect, '<?php echo FairPlay_LMS_Config::TAX_ROLE; ?>');
                    }

                    // MANEJO DE FOTOGRAFÍA
                    const uploadArea = document.getElementById('fplms-image-upload-area');
                    const fileInput = document.getElementById('fplms_user_photo');

                    if ( uploadArea && fileInput ) {
                        uploadArea.addEventListener('click', function() {
                            fileInput.click();
                        });

                        fileInput.addEventListener('change', function(e) {
                            const file = e.target.files[0];
                            if (file && file.type.startsWith('image/')) {
                                const reader = new FileReader();
                                reader.onload = function(event) {
                                    uploadArea.innerHTML = '<img src="' + event.target.result + '" alt="Usuario">';
                                    uploadArea.classList.add('has-image');
                                };
                                reader.readAsDataURL(file);
                            }
                        });

                        uploadArea.addEventListener('dragover', function(e) {
                            e.preventDefault();
                            uploadArea.style.background = '#ffe8d1';
                        });

                        uploadArea.addEventListener('dragleave', function(e) {
                            e.preventDefault();
                            uploadArea.style.background = '#fff8f0';
                        });

                        uploadArea.addEventListener('drop', function(e) {
                            e.preventDefault();
                            const files = e.dataTransfer.files;
                            if (files.length > 0) {
                                fileInput.files = files;
                                const event = new Event('change', { bubbles: true });
                                fileInput.dispatchEvent(event);
                            }
                        });
                    }
                });
            </script>
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

            // Validar datos requeridos (nombre y apellido ahora también son requeridos)
            if ( ! $user_login || ! $user_email || ! $user_pass || ! $first_name || ! $last_name ) {
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

            // Manejar subida de fotografía
            if ( isset( $_FILES['fplms_user_photo'] ) && ! empty( $_FILES['fplms_user_photo']['tmp_name'] ) ) {
                $this->handle_user_photo_upload( $user_id, $_FILES['fplms_user_photo'] );
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
        }
    }
}
