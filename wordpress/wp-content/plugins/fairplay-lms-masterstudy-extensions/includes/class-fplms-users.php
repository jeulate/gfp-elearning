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
                    <button type="submit" class="button button-primary">Filtrar</button>
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
     */
    public function get_users_filtered_by_structure(
        int $city_id,
        int $channel_id,
        int $branch_id,
        int $role_id
    ): array {

        $meta_query = [ 'relation' => 'AND' ];

        if ( $city_id ) {
            $meta_query[] = [
                'key'   => FairPlay_LMS_Config::USER_META_CITY,
                'value' => $city_id,
            ];
        }
        if ( $channel_id ) {
            $meta_query[] = [
                'key'   => FairPlay_LMS_Config::USER_META_CHANNEL,
                'value' => $channel_id,
            ];
        }
        if ( $branch_id ) {
            $meta_query[] = [
                'key'   => FairPlay_LMS_Config::USER_META_BRANCH,
                'value' => $branch_id,
            ];
        }
        if ( $role_id ) {
            $meta_query[] = [
                'key'   => FairPlay_LMS_Config::USER_META_ROLE,
                'value' => $role_id,
            ];
        }

        $args = [
            'number'  => 500,
            'orderby' => 'display_name',
            'order'   => 'ASC',
        ];

        if ( count( $meta_query ) > 1 ) {
            $args['meta_query'] = $meta_query;
        }

        $query = new WP_User_Query( $args );
        return (array) $query->get_results();
    }

        /**
     * Estadísticas de estructuras creadas vs estructuras con usuarios que tienen cursos.
     *
     * Devuelve un array con 4 filas: ciudad, canal, sucursal y cargo.
     * Cada fila trae:
     *  - label
     *  - total_terms
     *  - terms_with_courses
     */
    public function get_structure_assignment_stats(): array {

        $config = [
            'city' => [
                'label'     => 'Ciudades',
                'taxonomy'  => FairPlay_LMS_Config::TAX_CITY,
                'user_meta' => FairPlay_LMS_Config::USER_META_CITY,
            ],
            'channel' => [
                'label'     => 'Canales / Franquicias',
                'taxonomy'  => FairPlay_LMS_Config::TAX_CHANNEL,
                'user_meta' => FairPlay_LMS_Config::USER_META_CHANNEL,
            ],
            'branch' => [
                'label'     => 'Sucursales',
                'taxonomy'  => FairPlay_LMS_Config::TAX_BRANCH,
                'user_meta' => FairPlay_LMS_Config::USER_META_BRANCH,
            ],
            'role' => [
                'label'     => 'Cargos',
                'taxonomy'  => FairPlay_LMS_Config::TAX_ROLE,
                'user_meta' => FairPlay_LMS_Config::USER_META_ROLE,
            ],
        ];

        $stats = [];

        foreach ( $config as $key => $item ) {

            $terms = $this->structures->get_active_terms_for_select( $item['taxonomy'] );
            $total = count( $terms );
            $with_courses = 0;

            if ( $total > 0 ) {
                foreach ( array_keys( $terms ) as $term_id ) {

                    // usuarios que pertenecen a este término de estructura
                    $user_query = new WP_User_Query(
                        [
                            'number'    => 500,
                            'fields'    => 'ID',
                            'meta_key'  => $item['user_meta'],
                            'meta_value'=> $term_id,
                        ]
                    );

                    $user_ids = (array) $user_query->get_results();
                    if ( empty( $user_ids ) ) {
                        continue;
                    }

                    // ¿algún usuario tiene cursos?
                    $has_courses = false;
                    foreach ( $user_ids as $uid ) {
                        $detail = $this->progress->get_user_progress_detail( (int) $uid );
                        if ( ! empty( $detail['total_courses'] ) && $detail['total_courses'] > 0 ) {
                            $has_courses = true;
                            break;
                        }
                    }

                    if ( $has_courses ) {
                        $with_courses++;
                    }
                }
            }

            $stats[ $key ] = [
                'label'             => $item['label'],
                'total_terms'       => $total,
                'terms_with_courses'=> $with_courses,
            ];
        }

        return $stats;
    }

}
