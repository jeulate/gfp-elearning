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

    /**
     * @var FairPlay_LMS_Course_Display
     */
    private $course_display;

    /**
     * @var FairPlay_LMS_Audit_Logger
     */
    private $audit_logger;

    /**
     * @var FairPlay_LMS_Audit_Admin
     */
    private $audit_admin;

    public function __construct() {

        $this->structures     = new FairPlay_LMS_Structures_Controller();
        $this->progress       = new FairPlay_LMS_Progress_Service();
        $this->users          = new FairPlay_LMS_Users_Controller( $this->structures, $this->progress );
        $this->courses        = new FairPlay_LMS_Courses_Controller( $this->structures );
        $this->visibility     = new FairPlay_LMS_Course_Visibility_Service();
        $this->reports        = new FairPlay_LMS_Reports_Controller( $this->users, $this->structures, $this->progress );
        $this->pages          = new FairPlay_LMS_Admin_Pages();
        $this->course_display = new FairPlay_LMS_Course_Display();
        $this->audit_logger   = new FairPlay_LMS_Audit_Logger();
        $this->audit_admin    = new FairPlay_LMS_Audit_Admin();
        $this->menu           = new FairPlay_LMS_Admin_Menu(
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

        // Registrar último login de usuario
        add_action( 'wp_login', [ $this->users, 'record_user_login' ], 10, 2 );

        // Registrar actividad del usuario en cada carga de página
        add_action( 'init', [ $this->users, 'record_user_activity' ] );

        // Heartbeat para detectar usuarios activos
        add_filter( 'heartbeat_received', [ $this->users, 'heartbeat_received' ], 10, 2 );

        // Enqueue scripts para heartbeat
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_heartbeat_script' ] );

        // Exportaciones / informes
        add_action( 'admin_init', [ $this->reports, 'handle_export' ] );

        // Filtrado de cursos por visibilidad de estructura
        add_filter( 'stm_lms_get_user_courses', [ $this->visibility, 'filter_courses_array' ], 10, 1 );
        add_filter( 'stm_lms_course_list_query', [ $this, 'filter_course_query' ], 10, 1 );

        // Visualización de cursos en frontend (estructuras, ocultar ratings/estudiantes)
        $this->course_display->register_hooks();

        // AJAX: Cargar dinámicamente términos filtrados por ciudad
        add_action( 'wp_ajax_fplms_get_terms_by_city', [ $this->structures, 'ajax_get_terms_by_city' ] );
        add_action( 'wp_ajax_nopriv_fplms_get_terms_by_city', [ $this->structures, 'ajax_get_terms_by_city' ] );
        
        // AJAX: Cargar términos filtrados por padre (sistema jerárquico completo)
        add_action( 'wp_ajax_fplms_get_terms_by_parent', [ $this->structures, 'ajax_get_terms_by_parent' ] );
        add_action( 'wp_ajax_nopriv_fplms_get_terms_by_parent', [ $this->structures, 'ajax_get_terms_by_parent' ] );

        // FEATURE 1: Meta Box de Estructuras en Creación de Cursos
        add_action( 'add_meta_boxes', [ $this->courses, 'register_structures_meta_box' ] );
        add_action( 'save_post_' . FairPlay_LMS_Config::MS_PT_COURSE, [ $this->courses, 'save_course_structures_on_publish' ], 10, 3 );
        
        // Forzar editor clásico para cursos (evitar Course Builder automático)
        add_filter( 'use_block_editor_for_post_type', [ $this, 'force_classic_editor_for_courses' ], 10, 2 );
        
        // FEATURE 2: Sincronización Canales ↔ Categorías
        add_action( 'created_' . FairPlay_LMS_Config::TAX_CHANNEL, [ $this->structures, 'sync_channel_to_category' ], 10, 3 );
        add_action( 'edited_' . FairPlay_LMS_Config::TAX_CHANNEL, [ $this->structures, 'sync_channel_to_category' ], 10, 3 );
        add_action( 'delete_' . FairPlay_LMS_Config::TAX_CHANNEL, [ $this->structures, 'unsync_channel_on_delete' ], 10, 4 );
        
        // FEATURE 3: Detectar categorías asignadas en Course Builder y aplicar cascada
        add_action( 'set_object_terms', [ $this->courses, 'sync_categories_to_structures' ], 10, 6 );
        
        // También sincronizar cuando se guarda un curso (para editor clásico y actualizaciones)
        add_action( 'save_post_' . FairPlay_LMS_Config::MS_PT_COURSE, [ $this->courses, 'sync_course_categories_on_save' ], 20, 3 );
        
        // Auditoría: Menú admin
        add_action( 'admin_menu', [ $this->audit_admin, 'register_admin_menu' ], 20 );
    }

    /**
     * Encola el script del heartbeat para rastrear actividad de usuarios.
     */
    public function enqueue_heartbeat_script(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        // Asegurar que el heartbeat está habilitado
        wp_enqueue_script( 'heartbeat' );

        // Script inline para enviar señal de actividad
        $inline_script = "
        jQuery(document).ready(function($) {
            // Enviar señal de actividad cada minuto mediante heartbeat
            $(document).on('heartbeat-send', function(e, data) {
                data.fplms_user_active = true;
            });

            // Manejar respuesta del servidor
            $(document).on('heartbeat-tick', function(e, data) {
                if (data.fplms_activity_recorded) {
                    console.log('Actividad registrada');
                }
            });
        });
        ";
        wp_add_inline_script( 'heartbeat', $inline_script );
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

    /**
     * Fuerza el editor clásico para cursos de MasterStudy.
     * Esto evita que el Course Builder se abra automáticamente
     * y permite usar la meta box de estructuras.
     * 
     * @param bool   $use_block_editor Si se debe usar el editor de bloques
     * @param string $post_type        Tipo de post
     * @return bool
     */
    public function force_classic_editor_for_courses( $use_block_editor, $post_type ): bool {
        
        // Forzar editor clásico para cursos de MasterStudy
        if ( FairPlay_LMS_Config::MS_PT_COURSE === $post_type ) {
            return false;
        }
        
        return $use_block_editor;
    }
}

