<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FairPlay_LMS_Plugin {

    /**
     * @var FairPlay_LMS_Structures_Controller
     */
    private $structures;

    /**
     * @var FairPlay_LMS_Progress_Service
     */
    private $progress;

    /**
     * @var FairPlay_LMS_Users_Controller
     */
    private $users;

    /**
     * @var FairPlay_LMS_Courses_Controller
     */
    private $courses;

    /**
     * @var FairPlay_LMS_Course_Visibility_Service
     */
    private $visibility;

    /**
     * @var FairPlay_LMS_Reports_Controller
     */
    private $reports;

    /**
     * @var FairPlay_LMS_Admin_Pages
     */
    private $pages;

    /**
     * @var FairPlay_LMS_Admin_Menu
     */
    private $menu;

    public function __construct() {

        $this->structures = new FairPlay_LMS_Structures_Controller();
        $this->progress   = new FairPlay_LMS_Progress_Service();
        $this->users      = new FairPlay_LMS_Users_Controller( $this->structures, $this->progress );
        $this->courses    = new FairPlay_LMS_Courses_Controller( $this->structures );
        $this->visibility = new FairPlay_LMS_Course_Visibility_Service();
        $this->reports    = new FairPlay_LMS_Reports_Controller( $this->users, $this->structures, $this->progress );
        $this->pages      = new FairPlay_LMS_Admin_Pages();
        $this->menu       = new FairPlay_LMS_Admin_Menu(
            $this->pages,
            $this->structures,
            $this->users,
            $this->courses,
            $this->reports
        );

        $this->register_hooks();
    }

    /**
     * Registra todos los hooks del plugin.
     */
    private function register_hooks(): void {

        // Menú admin
        add_action( 'admin_menu', [ $this->menu, 'register' ] );

        // Estructuras
        add_action( 'init',       [ $this->structures, 'register_taxonomies' ] );
        add_action( 'admin_init', [ $this->structures, 'handle_form' ] );

        // Post types internos (módulos y temas)
        add_action( 'init',       [ $this->courses, 'register_post_types' ] );

        // Formularios de cursos / módulos / temas / profesor
        add_action( 'admin_init', [ $this->courses, 'handle_form' ] );

        // Usuarios: vincular estructuras
        add_action( 'show_user_profile',        [ $this->users, 'render_user_structures_fields' ] );
        add_action( 'edit_user_profile',        [ $this->users, 'render_user_structures_fields' ] );
        add_action( 'personal_options_update',  [ $this->users, 'save_user_structures_fields' ] );
        add_action( 'edit_user_profile_update', [ $this->users, 'save_user_structures_fields' ] );

        // Crear nuevo usuario desde panel FairPlay
        add_action( 'admin_init', [ $this->users, 'handle_new_user_form' ] );

        // Matriz de privilegios
        add_action( 'admin_init', [ $this->users, 'handle_caps_matrix_form' ] );

        // Exportaciones / informes
        add_action( 'admin_init', [ $this->reports, 'handle_export' ] );

        // Filtrado de cursos por visibilidad de estructura
        add_filter( 'stm_lms_get_user_courses', [ $this->visibility, 'filter_courses_array' ], 10, 1 );
        add_filter( 'stm_lms_course_list_query', [ $this, 'filter_course_query' ], 10, 1 );

        // AJAX: Cargar dinámicamente términos filtrados por ciudad
        add_action( 'wp_ajax_fplms_get_terms_by_city', [ $this->structures, 'ajax_get_terms_by_city' ] );
        add_action( 'wp_ajax_nopriv_fplms_get_terms_by_city', [ $this->structures, 'ajax_get_terms_by_city' ] );
    }

    /**
     * Filtra la query de cursos para mostrar solo los visibles según estructura.
     * Hook de compatibilidad con MasterStudy.
     */
    public function filter_course_query( $query_args ): array {

        if ( ! is_array( $query_args ) ) {
            return $query_args;
        }

        $user_id = get_current_user_id();

        if ( 0 === $user_id || current_user_can( 'manage_options' ) ) {
            return $query_args;
        }

        // Obtener cursos visibles para el usuario
        $visible_course_ids = $this->visibility->get_visible_courses_for_user( $user_id );

        if ( ! empty( $visible_course_ids ) ) {
            $query_args['post__in'] = $visible_course_ids;
        } else {
            // Si no hay cursos visibles, retornar query que no devuelva resultados
            $query_args['post__in'] = [ 0 ];
        }

        return $query_args;
    }
}

