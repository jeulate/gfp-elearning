<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gestiona la visibilidad y asignación del menú de navegación frontend
 * según el estado de sesión y el rol del usuario.
 *
 * Comportamiento:
 *  - Visitantes (sin sesión)     → Muestra el menú "Landing" (si está asignado) o ningún menú.
 *  - Estudiante (subscriber)     → Muestra el menú configurado para Estudiantes.
 *  - Instructor (stm_lms_instructor) → Muestra el menú configurado para Instructores.
 *  - Administrador               → No se modifica (acceso total al menú del tema).
 *
 * Requiere activación desde FairPlay LMS → Menú por Rol en el panel admin.
 */
class FairPlay_LMS_Nav_Menu {

    const OPTION_KEY          = 'fplms_nav_menu_settings';
    const LOCATION_LANDING    = 'fplms-menu-landing';
    const LOCATION_STUDENT    = 'fplms-menu-student';
    const LOCATION_INSTRUCTOR = 'fplms-menu-instructor';

    // ── Registro de hooks ────────────────────────────────────────────────────

    public function register_hooks(): void {
        // Registrar ubicaciones de menú propias (prioridad 20, después del tema)
        add_action( 'after_setup_theme', [ $this, 'register_nav_locations' ], 20 );

        // Interceptar argumentos de wp_nav_menu() para redirigir por rol
        add_filter( 'wp_nav_menu_args', [ $this, 'filter_nav_menu_args' ], 10, 1 );

        // Inyectar CSS para ocultar menús del tema que no usan wp_nav_menu()
        add_action( 'wp_head', [ $this, 'inject_guest_hide_styles' ], 99 );

        // Subpágina de configuración dentro del menú FairPlay LMS
        add_action( 'admin_menu', [ $this, 'add_admin_submenu' ], 25 );

        // Procesar el formulario de guardado
        add_action( 'admin_post_fplms_nav_menu_save', [ $this, 'handle_save_settings' ] );

        // Inyectar cursos del canal del usuario como sub-ítems dinámicos en el menú
        // (Se activa en ítems de menú que tengan la clase CSS "fplms-mis-cursos")
        add_filter( 'wp_nav_menu_objects', [ $this, 'inject_channel_courses_submenu' ], 10, 2 );
    }

    // ── Ubicaciones de menú ──────────────────────────────────────────────────

    public function register_nav_locations(): void {
        register_nav_menus( [
            self::LOCATION_LANDING    => 'Landing / Visitantes (FairPlay)',
            self::LOCATION_STUDENT    => 'Menú Estudiante (FairPlay)',
            self::LOCATION_INSTRUCTOR => 'Menú Instructor (FairPlay)',
        ] );
    }

    // ── Filtro de menú por rol ───────────────────────────────────────────────

    /**
     * Filtra los argumentos de wp_nav_menu() para servir el menú correcto.
     *
     * @param array $args Argumentos de wp_nav_menu().
     * @return array
     */
    public function filter_nav_menu_args( $args ): array {
        if ( is_admin() || ! $this->is_enabled() ) {
            return $args;
        }

        $location = $this->resolve_location_for_current_user();

        // Administrador: sin cambios
        if ( $location === null ) {
            return $args;
        }

        // Sin sesión + sin menú landing asignado → suprimir completamente
        if ( $location === false ) {
            $args['menu']           = -1;
            $args['theme_location'] = 'fplms-nonexistent';
            $args['fallback_cb']    = false;
            return $args;
        }

        // Reemplazar con la ubicación correspondiente al rol
        $args['theme_location'] = $location;
        $args['menu']           = 0;

        return $args;
    }

    /**
     * Determina la ubicación de menú a usar según el usuario actual.
     *
     * @return string|null|false
     *   string → ubicación a usar
     *   null   → administrador, sin modificar
     *   false  → visitante sin menú landing, suprimir
     */
    private function resolve_location_for_current_user() {
        if ( ! is_user_logged_in() ) {
            $locs = get_nav_menu_locations();
            return ( ! empty( $locs[ self::LOCATION_LANDING ] ) )
                ? self::LOCATION_LANDING
                : false;
        }

        $user = wp_get_current_user();

        if ( in_array( 'administrator', (array) $user->roles, true ) ) {
            return null; // Sin modificación
        }

        if ( in_array( FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR, (array) $user->roles, true ) ) {
            return self::LOCATION_INSTRUCTOR;
        }

        // subscriber y cualquier otro rol frontend → menú estudiante
        return self::LOCATION_STUDENT;
    }

    // ── CSS de respaldo para elementos nav fuera de wp_nav_menu() ────────────

    /**
     * Inyecta CSS que oculta los selectores de navegación del tema
     * cuando el visitante no tiene sesión iniciada.
     * Actúa como respaldo para temas que renderizan el nav fuera de wp_nav_menu().
     */
    public function inject_guest_hide_styles(): void {
        if ( is_admin() || ! $this->is_enabled() || is_user_logged_in() ) {
            return;
        }

        $settings  = $this->get_settings();
        $selectors = $this->get_all_selectors( $settings );

        if ( empty( $selectors ) ) {
            return;
        }

        $css = implode( ",\n", array_map( 'esc_attr', $selectors ) );

        echo '<style id="fplms-nav-guest-hide">' . "\n";
        echo $css . " { display: none !important; }\n";
        echo "</style>\n";
    }

    /**
     * Combina los selectores predeterminados con los personalizados del admin.
     *
     * @param array $settings
     * @return string[]
     */
    private function get_all_selectors( array $settings ): array {
        $defaults   = $this->default_selectors();
        $custom_raw = trim( $settings['custom_selectors'] ?? '' );
        $custom     = $custom_raw !== ''
            ? array_filter( array_map( 'trim', explode( "\n", $custom_raw ) ) )
            : [];

        return array_unique( array_merge( $defaults, $custom ) );
    }

    /**
     * Selectores CSS del menú principal para los temas más comunes.
     * Incluye MasterStudy LMS, temas populares y bloques de WordPress (FSE).
     *
     * @return string[]
     */
    private function default_selectors(): array {
        return [
            // MasterStudy LMS (todas las variantes conocidas del tema Starter)
            '.stm-lms-main-header__navigation',
            '.stm-lms-main-header .menu',
            '.stm-lms-main-header .nav-menu',
            '.stm-lms-main-header nav',
            '.stm-lms-main-header__nav',
            '.stm-lms-main-header-nav',
            '.stm-lms-main-header .stm-lms-main-header__menu',
            '.masterstudy-nav',
            '.masterstudy-menu',
            '.ms-nav',
            // Selectores genéricos de WordPress
            '.main-navigation',
            '.site-navigation',
            '.primary-navigation',
            '.header-navigation',
            '.nav-primary',
            '.navbar-nav',
            '.site-header nav',
            'header nav',
            '.header-menu',
            '.navigation-wrap',
            '#site-navigation',
            '#main-navigation',
            '#primary-menu',
            // WordPress FSE / bloques
            '.wp-block-navigation',
            // Otros comunes
            '.header__nav',
            '.top-navigation',
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    // ── Cursos dinámicos por canal ──────────────────────────────────────────

    /**
     * Procesa los ítems del menú para dos funcionalidades dinámicas:
     *
     *  fplms-perfil     → Reemplaza la URL del ítem con la URL real del perfil
     *                     del usuario actual (student-public-account o instructor-public-account).
     *
     *  fplms-mis-cursos → Añade como hijos los cursos del canal asignado al usuario.
     *                     Instructores verán solo sus propios cursos en ese canal.
     *
     * Ambas clases funcionan de forma independiente y pueden coexistir en el mismo menú.
     *
     * @param \WP_Post[] $items Ítems del menú ya procesados por WordPress.
     * @param object     $args  Argumentos del menú.
     * @return \WP_Post[]
     */
    public function inject_channel_courses_submenu( array $items, $args ): array {
        if ( is_admin() || ! $this->is_enabled() || ! is_user_logged_in() ) {
            return $items;
        }

        $user_id   = get_current_user_id();
        $courses   = null; // carga perezosa: solo si hay ítem fplms-mis-cursos
        $fake_id   = 900000;
        $order     = 0;
        $new_items = [];

        // Detectar las clases extra que el tema añade a los ítems del menú
        // para replicarlas en los sub-ítems dinámicos (ej: HFE usa hfe-menu-item).
        $theme_anchor_class = $this->detect_theme_anchor_class( $items );

        foreach ( $items as $item ) {
            $classes = (array) $item->classes;

            // ── fplms-perfil: reemplazar URL con la página de perfil real del usuario ──
            if ( in_array( 'fplms-perfil', $classes, true ) ) {
                $profile_url = $this->get_user_profile_url( $user_id );
                if ( $profile_url ) {
                    $item->url  = $profile_url;
                    $item->guid = $profile_url;

                    // Marcar como activo si la página actual coincide con el perfil.
                    // WordPress ya calculó current-menu-item antes de este filtro (contra '#'),
                    // así que debemos añadirlo manualmente comparando rutas.
                    $profile_path  = trailingslashit( wp_parse_url( $profile_url, PHP_URL_PATH ) );
                    $current_path  = trailingslashit( strtok( $_SERVER['REQUEST_URI'], '?' ) );
                    if ( $current_path === $profile_path || 0 === strpos( $current_path, $profile_path ) ) {
                        if ( ! in_array( 'current-menu-item', $item->classes, true ) ) {
                            $item->classes[] = 'current-menu-item';
                        }
                    }
                }
            }

            // ── fplms-mis-cursos: preparar inyección de sub-ítems ──
            $inject_courses = in_array( 'fplms-mis-cursos', $classes, true );
            if ( $inject_courses ) {
                if ( $courses === null ) {
                    $courses = $this->get_user_channel_courses( $user_id );
                }
                // Añadir menu-item-has-children ANTES de insertar el ítem padre
                // para que el Walker y el tema activen el dropdown.
                if ( ! empty( $courses ) && ! in_array( 'menu-item-has-children', $classes, true ) ) {
                    $item->classes[] = 'menu-item-has-children';
                }
            }

            $item->menu_order = ++$order;
            $new_items[]      = $item;

            // Insertar cursos como hijos después del ítem padre
            if ( $inject_courses && ! empty( $courses ) ) {
                $parent_db_id = (int) $item->db_id;
                foreach ( $courses as $course ) {
                    $fake_id++;
                    $new_items[] = $this->build_dynamic_menu_item(
                        $fake_id,
                        $course->post_title,
                        (string) get_permalink( $course->ID ),
                        $parent_db_id,
                        ++$order,
                        $theme_anchor_class
                    );
                }
            }
        }

        return $new_items;
    }

    /**
     * Detecta la clase CSS que el tema añade a los enlaces de menú (ej: "hfe-menu-item")
     * para que los sub-ítems dinámicos sean visualmente coherentes.
     *
     * @param \WP_Post[] $items
     * @return string  Clase extra o cadena vacía.
     */
    private function detect_theme_anchor_class( array $items ): string {
        // Clases de temas/plugins conocidos que deben propagarse a sub-ítems
        $known_classes = [ 'hfe-menu-item', 'et_pb_menu_item', 'elementor-nav-menu--item' ];
        foreach ( $items as $item ) {
            foreach ( $known_classes as $cls ) {
                if ( in_array( $cls, (array) $item->classes, true ) ) {
                    return $cls;
                }
            }
        }
        return '';
    }

    /**
     * Genera la URL del perfil público del usuario actual en MasterStudy LMS.
     * Busca la página WordPress por slug; si no existe como página real
     * (MasterStudy puede usar rutas virtuales), usa home_url() como fallback.
     *
     * @param int $user_id
     * @return string URL completa o cadena vacía.
     */
    private function get_user_profile_url( int $user_id ): string {
        static $url_cache = [];

        if ( isset( $url_cache[ $user_id ] ) ) {
            return $url_cache[ $user_id ];
        }

        $user  = get_userdata( $user_id );
        $roles = $user ? (array) $user->roles : [];

        $is_instructor = in_array( FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR, $roles, true );
        $base_slug     = $is_instructor ? 'instructor-public-account' : 'student-public-account';

        // Intentar obtener la página WP real
        $page = get_page_by_path( $base_slug );
        if ( $page ) {
            $base_url = trailingslashit( get_permalink( $page->ID ) );
        } else {
            // MasterStudy puede registrar estas rutas via rewrite rules sin página WP real
            $base_url = trailingslashit( home_url( '/' . $base_slug ) );
        }

        return $url_cache[ $user_id ] = $base_url . $user_id . '/';
    }

    /**
     * Devuelve los cursos en los que el usuario está matriculado en MasterStudy LMS.
     * Para instructores también incluye los cursos que dictan como docente.
     *
     * Usa consulta directa a la tabla de MasterStudy (stm_lms_user_courses en v3+,
     * stm_lms_users en v2) para evitar efectos secundarios de la API interna
     * (STM_LMS_User::get_user_courses puede llamar die() en contextos no-AJAX).
     *
     * Resultados almacenados en caché estática para evitar consultas repetidas
     * en la misma carga de página.
     *
     * @param int $user_id
     * @return \WP_Post[]
     */
    private function get_user_channel_courses( int $user_id ): array {
        static $cache    = [];
        static $uc_table = false; // false = no buscado aún, null = no existe

        if ( isset( $cache[ $user_id ] ) ) {
            return $cache[ $user_id ];
        }

        global $wpdb;

        $user          = get_userdata( $user_id );
        $roles         = $user ? (array) $user->roles : [];
        $is_instructor = in_array( FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR, $roles, true );
        $course_ids    = [];

        // ── 1. Detectar tabla de matriculaciones de MasterStudy ─────────────
        // v3+: stm_lms_user_courses | v2: stm_lms_users
        if ( $uc_table === false ) {
            $uc_table = null;
            foreach ( [ $wpdb->prefix . 'stm_lms_user_courses', $wpdb->prefix . 'stm_lms_users' ] as $t ) {
                if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) {
                    $uc_table = $t;
                    break;
                }
            }
        }

        // ── 2. Cursos matriculados (ambos roles) ────────────────────────────
        if ( $uc_table ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT course_id FROM `{$uc_table}` WHERE user_id = %d",
                $user_id
            ) );
            $course_ids = array_map( 'intval', (array) $ids );
        }

        // ── 3. Para instructores: también sus cursos como docente ───────────
        if ( $is_instructor ) {
            $taught_ids = get_posts( [
                'post_type'      => FairPlay_LMS_Config::MS_PT_COURSE,
                'post_status'    => 'publish',
                'posts_per_page' => 50,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => [ [
                    'key'   => FairPlay_LMS_Config::MS_META_COURSE_TEACHER,
                    'value' => $user_id,
                ] ],
            ] );
            $course_ids = array_unique( array_merge( $course_ids, array_map( 'intval', (array) $taught_ids ) ) );
        }

        if ( empty( $course_ids ) ) {
            return $cache[ $user_id ] = [];
        }

        $posts = get_posts( [
            'post_type'      => FairPlay_LMS_Config::MS_PT_COURSE,
            'post_status'    => 'publish',
            'post__in'       => $course_ids,
            'posts_per_page' => 50,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ] );

        return $cache[ $user_id ] = $posts;
    }

    /**
     * Construye un objeto de ítem de menú dinámico (sin registro en base de datos).
     *
     * @param int    $id        ID sintético (alto para evitar colisiones).
     * @param string $title     Título visible en el menú.
     * @param string $url       URL de destino.
     * @param int    $parent_id db_id del ítem padre.
     * @param int    $order     Posición en el menú.
     * @return \stdClass
     */
    private function build_dynamic_menu_item( int $id, string $title, string $url, int $parent_id, int $order, string $extra_class = '' ): \stdClass {
        $item = new \stdClass();

        // Propiedades de WP_Post requeridas por wp_nav_menu()
        $item->ID                    = $id;
        $item->post_author           = '0';
        $item->post_date             = '0000-00-00 00:00:00';
        $item->post_date_gmt         = '0000-00-00 00:00:00';
        $item->post_content          = '';
        $item->post_title            = esc_html( $title );
        $item->post_excerpt          = '';
        $item->post_status           = 'publish';
        $item->comment_status        = 'closed';
        $item->ping_status           = 'closed';
        $item->post_password         = '';
        $item->post_name             = 'fplms-course-' . $id;
        $item->to_ping               = '';
        $item->pinged                = '';
        $item->post_modified         = '0000-00-00 00:00:00';
        $item->post_modified_gmt     = '0000-00-00 00:00:00';
        $item->post_content_filtered = '';
        $item->post_parent           = 0;
        $item->guid                  = esc_url( $url );
        $item->menu_order            = $order;
        $item->post_type             = 'nav_menu_item';
        $item->post_mime_type        = '';
        $item->comment_count         = 0;
        $item->filter                = 'raw';

        // Propiedades de ítem de menú nav
        $item->db_id                  = $id;
        $item->menu_item_parent       = (string) $parent_id;
        $item->object_id              = (string) $id;
        $item->object                 = FairPlay_LMS_Config::MS_PT_COURSE;
        $item->type                   = 'post_type';
        $item->type_label             = 'Curso';
        $item->title                  = esc_html( $title );
        $item->url                    = esc_url( $url );
        $item->target                 = '';
        $item->attr_title             = '';
        $item->description            = '';
        // Incluir clase del tema (ej: hfe-menu-item) para compatibilidad visual
        $classes = [ 'menu-item', 'menu-item-type-post_type', 'menu-item-object-stm-courses', 'fplms-channel-course' ];
        if ( $extra_class !== '' ) {
            $classes[] = $extra_class;
        }
        $item->classes                = $classes;
        $item->xfn                    = '';
        $item->current                = false;
        $item->current_item_ancestor  = false;
        $item->current_item_parent    = false;

        return $item;
    }

    private function is_enabled(): bool {
        return ! empty( $this->get_settings()['enabled'] );
    }

    /**
     * @return array{enabled: bool, custom_selectors: string}
     */
    private function get_settings(): array {
        $defaults = [ 'enabled' => false, 'custom_selectors' => '' ];
        return array_merge( $defaults, (array) get_option( self::OPTION_KEY, [] ) );
    }

    // ── Admin: página de configuración ──────────────────────────────────────

    public function add_admin_submenu(): void {
        add_submenu_page(
            'fplms-dashboard',
            'Menú por Rol',
            'Menú por Rol',
            'manage_options',
            'fplms-nav-menu',
            [ $this, 'render_admin_page' ]
        );
    }

    public function render_admin_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = $this->get_settings();
        $menus    = wp_get_nav_menus();
        $nav_locs = get_nav_menu_locations();

        $location_rows = [
            [
                'label' => 'Menú para visitantes / Landing',
                'loc'   => self::LOCATION_LANDING,
                'desc'  => 'Se muestra cuando el usuario NO ha iniciado sesión. Si se deja sin asignar, no se mostrará ningún menú al visitante.',
            ],
            [
                'label' => 'Menú para Estudiantes',
                'loc'   => self::LOCATION_STUDENT,
                'desc'  => 'Usuarios con rol Estudiante (subscriber).',
            ],
            [
                'label' => 'Menú para Instructores',
                'loc'   => self::LOCATION_INSTRUCTOR,
                'desc'  => 'Usuarios con rol Instructor (stm_lms_instructor).',
            ],
        ];
        ?>
        <div class="wrap">
            <h1>Menú por Rol — FairPlay LMS</h1>

            <?php if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) : ?>
                <div class="notice notice-success is-dismissible"><p><strong>Configuración guardada correctamente.</strong></p></div>
            <?php endif; ?>

            <p style="color:#555;max-width:720px;margin-bottom:24px">
                Controla qué menú de navegación se muestra a cada tipo de usuario.
                Los visitantes sin sesión verán únicamente el menú <em>Landing</em> (o ningún menú si no está asignado),
                logrando una experiencia de página de bienvenida sin navegación.
                Después de activar, crea los menús en <strong>Apariencia → Menús</strong> y asígnalos aquí
                (o desde <strong>Apariencia → Menús → Administrar ubicaciones</strong>).
            </p>

            <div style="background:#fff8e5;border-left:4px solid #f0c040;padding:14px 18px;max-width:720px;margin-bottom:24px;border-radius:0 4px 4px 0">
                <strong>Cursos dinámicos por canal</strong><br>
                Para mostrar automáticamente los cursos del canal de cada usuario en el menú:
                <ol style="margin:8px 0 0 18px;color:#555">
                    <li>En <strong>Apariencia → Menús</strong>, activa <em>Clases CSS</em> desde <strong>Opciones de pantalla</strong> (arriba a la derecha).</li>
                    <li>Añade un ítem «Enlace personalizado» o página con el título que prefieras (ej: <em>Mis Cursos</em>).</li>
                    <li>En el campo <strong>Clases CSS</strong> de ese ítem escribe exactamente: <code>fplms-mis-cursos</code></li>
                    <li>Guarda el menú. Al iniciar sesión, los cursos del canal del usuario aparecerán como subitems automáticamente.</li>
                </ol>
                <p style="margin-top:10px;color:#555">
                    También puedes añadir un ítem de <strong>Perfil</strong> con clase CSS <code>fplms-perfil</code>:
                    la URL se reemplazará automáticamente por la página de perfil real del usuario
                    (<code>student-public-account/{id}</code> para estudiantes,
                    <code>instructor-public-account/{id}</code> para instructores).
                    Pon <code>#</code> como URL al crear el ítem.
                </p>
                <ol style="margin:0 0 0 18px;color:#555;list-style:none">
                </ol>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'fplms_nav_menu_save', 'fplms_nav_nonce' ); ?>
                <input type="hidden" name="action" value="fplms_nav_menu_save">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Activar control de menú por rol</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>>
                                Habilitar ocultamiento de menú para visitantes y asignación por rol
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            Selectores CSS adicionales
                            <br><small style="font-weight:normal;color:#777">uno por línea</small>
                        </th>
                        <td>
                            <textarea name="custom_selectors" rows="5" class="large-text code"
                                placeholder=".mi-clase-nav&#10;#mi-id-nav"><?php echo esc_textarea( $settings['custom_selectors'] ?? '' ); ?></textarea>
                            <p class="description">
                                Selectores CSS adicionales del menú de tu tema para ocultar a visitantes (uno por línea).
                                Los selectores predeterminados de WordPress y MasterStudy LMS ya están incluidos automáticamente.
                            </p>
                        </td>
                    </tr>
                </table>

                <h2 style="margin-top:32px">Asignación de menús por rol</h2>
                <p style="color:#555;max-width:720px;margin-bottom:16px">
                    Selecciona qué menú de WordPress se sirve para cada tipo de usuario.
                    Si dejas una ubicación sin asignar, se mostrará el menú predeterminado del tema para ese rol.
                </p>

                <table class="form-table" role="presentation">
                    <?php foreach ( $location_rows as $row ) :
                        $assigned = (int) ( $nav_locs[ $row['loc'] ] ?? 0 );
                    ?>
                    <tr>
                        <th scope="row"><?php echo esc_html( $row['label'] ); ?></th>
                        <td>
                            <select name="menu_loc[<?php echo esc_attr( $row['loc'] ); ?>]">
                                <option value="0">— Sin asignar —</option>
                                <?php foreach ( $menus as $menu ) : ?>
                                    <option value="<?php echo (int) $menu->term_id; ?>"
                                        <?php selected( $assigned, $menu->term_id ); ?>>
                                        <?php echo esc_html( $menu->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php echo esc_html( $row['desc'] ); ?></p>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <?php submit_button( 'Guardar cambios' ); ?>
            </form>

            <hr style="margin-top:40px">
            <h2>Selectores CSS predeterminados incluidos</h2>
            <p style="color:#666;font-size:13px">Estos selectores se ocultan automáticamente a los visitantes sin necesidad de configuración adicional:</p>
            <code style="display:block;background:#f6f7f7;padding:14px 18px;border-radius:4px;font-size:12px;line-height:2;max-width:700px">
                <?php echo esc_html( implode( "\n", $this->default_selectors() ) ); ?>
            </code>
        </div>
        <?php
    }

    /**
     * Procesa el formulario de guardado de configuración (admin-post.php).
     */
    public function handle_save_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'No autorizado.', 403 );
        }

        check_admin_referer( 'fplms_nav_menu_save', 'fplms_nav_nonce' );

        // Guardar opciones del plugin
        $settings = [
            'enabled'          => ! empty( $_POST['enabled'] ),
            'custom_selectors' => sanitize_textarea_field( wp_unslash( $_POST['custom_selectors'] ?? '' ) ),
        ];
        update_option( self::OPTION_KEY, $settings );

        // Guardar asignación de ubicaciones de menú
        $raw_locs = ( isset( $_POST['menu_loc'] ) && is_array( $_POST['menu_loc'] ) )
            ? $_POST['menu_loc']
            : [];

        $nav_locs = get_nav_menu_locations();
        foreach ( [ self::LOCATION_LANDING, self::LOCATION_STUDENT, self::LOCATION_INSTRUCTOR ] as $loc ) {
            $nav_locs[ $loc ] = isset( $raw_locs[ $loc ] ) ? (int) $raw_locs[ $loc ] : 0;
        }
        set_theme_mod( 'nav_menu_locations', $nav_locs );

        wp_safe_redirect( admin_url( 'admin.php?page=fplms-nav-menu&saved=1' ) );
        exit;
    }
}
