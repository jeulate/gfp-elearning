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

    /**
     * @var FairPlay_LMS_Onboarding
     */
    private $onboarding;

    /**
     * @var FairPlay_LMS_Quiz_Settings
     */
    private $quiz_settings;

    /**
     * @var FairPlay_LMS_Quiz_Availability
     */
    private $quiz_availability;

    /**
     * @var FairPlay_LMS_Quiz_Weights
     */
    private $quiz_weights;

    /**
     * @var FairPlay_LMS_Survey
     */
    private $survey;

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
        $this->onboarding     = new FairPlay_LMS_Onboarding();
        $this->quiz_settings     = new FairPlay_LMS_Quiz_Settings();
        $this->quiz_availability  = new FairPlay_LMS_Quiz_Availability();
        $this->quiz_weights       = new FairPlay_LMS_Quiz_Weights();
        $this->survey             = new FairPlay_LMS_Survey();
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
        add_action( 'admin_init', [ $this->structures, 'handle_export_request' ] );

        // Post types internos (módulos y temas)
        add_action( 'init',       [ $this->courses, 'register_post_types' ] );

        // Formularios de cursos / módulos / temas / profesor
        add_action( 'admin_init', [ $this->courses, 'handle_form' ] );

        // Ocultar cursos inactivos (draft) a roles no-administrador (frontend + REST + AJAX)
        add_action( 'pre_get_posts', [ $this->courses, 'filter_inactive_courses' ] );

        // Usuarios: vincular estructuras
        add_action( 'show_user_profile',        [ $this->users, 'render_user_structures_fields' ] );
        add_action( 'edit_user_profile',        [ $this->users, 'render_user_structures_fields' ] );
        add_action( 'personal_options_update',  [ $this->users, 'save_user_structures_fields' ] );
        add_action( 'edit_user_profile_update', [ $this->users, 'save_user_structures_fields' ] );

        // Crear nuevo usuario desde panel FairPlay
        add_action( 'admin_init', [ $this->users, 'handle_new_user_form' ] );

        // Editar usuario desde panel FairPlay
        add_action( 'admin_init', [ $this->users, 'handle_edit_user_form' ] );

        // Acciones masivas de usuarios
        add_action( 'admin_init', [ $this->users, 'handle_bulk_user_actions' ] );

        // Matriz de privilegios
        add_action( 'admin_init', [ $this->users, 'handle_caps_matrix_form' ] );

        // Interceptar el AJAX de MasterStudy ANTES de que lo procese (prioridad 0)
        // para bloquear usuarios inactivos con mensaje personalizado en el formato nativo de Vue.
        add_action( 'wp_ajax_nopriv_stm_lms_login', [ $this->users, 'intercept_masterstudy_login' ], 0 );

        // Bloquear inicio de sesión de usuarios inactivos (prioridad 30, después de validar credenciales)
        add_filter( 'authenticate', [ $this->users, 'block_inactive_user_login' ], 30, 1 );

        // AJAX (sin sesión): verificar si el último login falló por cuenta inactiva
        add_action( 'wp_ajax_nopriv_fplms_check_blocked', [ $this->users, 'ajax_check_blocked' ] );

        // Inyectar script de reemplazo de mensaje en formularios de login del frontend
        add_action( 'wp_footer', [ $this, 'inject_inactive_login_message_script' ] );

        // Bloquear selector de unidad de tiempo en el course builder (forzar minutos)
        add_action( 'wp_footer', [ $this, 'inject_quiz_duration_unit_lock_script' ] );

        // Forzar duration_measure = minutes en cada guardado de quiz (server-side)
        add_action( 'save_post_stm-quizzes', [ $this, 'enforce_quiz_duration_minutes' ], 20, 1 );

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

        // Auto-enrolar usuario en cursos al crear o actualizar su estructura
        add_action( 'fplms_user_created',          [ $this->courses, 'auto_enroll_user_in_matching_courses' ] );
        add_action( 'fplms_user_structures_saved', [ $this->courses, 'auto_enroll_user_in_matching_courses' ] );

        // Filtrado de cursos matriculados por visibilidad de estructura (respuesta AJAX)
        add_filter( 'stm_lms_get_user_courses_filter', [ $this->visibility, 'filter_user_courses_response' ], 10, 1 );
        add_filter( 'stm_lms_course_list_query', [ $this, 'filter_course_query' ], 10, 1 );

        // Filtrado de cursos por estructura en pre_get_posts (frontend + admin instructores)
        add_action( 'pre_get_posts', [ $this, 'filter_courses_pre_get_posts' ] );

        // Auto-asignar estructura del instructor al crear un curso nuevo
        add_action( 'save_post_' . FairPlay_LMS_Config::MS_PT_COURSE, [ $this, 'auto_assign_instructor_structure_to_course' ], 1, 3 );

        // Restricción de categorías para instructores: solo pueden ver/usar las de su canal
        add_action( 'pre_get_terms',                                    [ $this, 'restrict_course_categories_for_instructor' ] );
        add_filter( 'rest_' . FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY . '_query', [ $this, 'restrict_categories_rest_query' ], 10, 2 );
        add_action( 'save_post_' . FairPlay_LMS_Config::MS_PT_COURSE,   [ $this, 'enforce_instructor_category_on_save' ], 8, 3 );

        // Visualización de cursos en frontend (estructuras, ocultar ratings/estudiantes)
        $this->course_display->register_hooks();

        // AJAX: Cargar dinámicamente términos filtrados por ciudad
        add_action( 'wp_ajax_fplms_get_terms_by_city', [ $this->structures, 'ajax_get_terms_by_city' ] );
        add_action( 'wp_ajax_nopriv_fplms_get_terms_by_city', [ $this->structures, 'ajax_get_terms_by_city' ] );
        
        // AJAX: Cargar términos filtrados por padre (sistema jerárquico completo)
        add_action( 'wp_ajax_fplms_get_terms_by_parent', [ $this->structures, 'ajax_get_terms_by_parent' ] );
        
        // Bitácora: Menú
        add_action( 'admin_menu', [ $this->audit_admin, 'register_admin_menu' ] );
        
        // Bitácora: Acciones de usuario desde bitácora
        add_action( 'admin_post_fplms_reactivate_user', [ $this->audit_admin, 'handle_user_reactivation' ] );
        add_action( 'admin_post_fplms_delete_user_permanently', [ $this->audit_admin, 'handle_user_permanent_deletion' ] );
        add_action( 'wp_ajax_nopriv_fplms_get_terms_by_parent', [ $this->structures, 'ajax_get_terms_by_parent' ] );
        
        // AJAX: Cargar estructuras en cascada para asignación a cursos
        add_action( 'wp_ajax_fplms_get_cascade_structures', [ $this->structures, 'ajax_get_cascade_structures' ] );

        // FEATURE 1: Meta Box de Estructuras en Creación de Cursos
        add_action( 'add_meta_boxes', [ $this->courses, 'register_structures_meta_box' ] );
        add_action( 'save_post_' . FairPlay_LMS_Config::MS_PT_COURSE, [ $this->courses, 'save_course_structures_on_publish' ], 10, 3 );
        
        // Forzar editor clásico para cursos (evitar Course Builder automático)
        add_filter( 'use_block_editor_for_post_type', [ $this, 'force_classic_editor_for_courses' ], 10, 2 );
        
        // FEATURE 2: Sincronización Canales ↔ Categorías
        add_action( 'created_' . FairPlay_LMS_Config::TAX_CHANNEL, [ $this->structures, 'sync_channel_to_category' ], 10, 3 );
        add_action( 'edited_' . FairPlay_LMS_Config::TAX_CHANNEL, [ $this->structures, 'sync_channel_to_category' ], 10, 3 );
        add_action( 'delete_' . FairPlay_LMS_Config::TAX_CHANNEL, [ $this->structures, 'unsync_channel_on_delete' ], 10, 4 );

        // FEATURE 2b: Sincronización Sucursales/Cargos → Subcategorías (jerarquía nativa en Course Builder)
        add_action( 'created_' . FairPlay_LMS_Config::TAX_BRANCH,       [ $this->structures, 'sync_branch_to_subcategory' ], 10, 3 );
        add_action( 'edited_' . FairPlay_LMS_Config::TAX_BRANCH,        [ $this->structures, 'sync_branch_to_subcategory' ], 10, 3 );
        add_action( 'delete_' . FairPlay_LMS_Config::TAX_BRANCH,        [ $this->structures, 'unsync_branch_on_delete' ], 10, 4 );
        add_action( 'created_' . FairPlay_LMS_Config::TAX_ROLE,         [ $this->structures, 'sync_role_to_subcategory' ], 10, 3 );
        add_action( 'edited_' . FairPlay_LMS_Config::TAX_ROLE,          [ $this->structures, 'sync_role_to_subcategory' ], 10, 3 );
        add_action( 'delete_' . FairPlay_LMS_Config::TAX_ROLE,          [ $this->structures, 'unsync_role_on_delete' ], 10, 4 );
        
        // FEATURE 3: Detectar categorías asignadas en Course Builder y aplicar cascada
        add_action( 'set_object_terms', [ $this->courses, 'sync_categories_to_structures' ], 10, 6 );
        
        // También sincronizar cuando se guarda un curso (para editor clásico y actualizaciones)
        add_action( 'save_post_' . FairPlay_LMS_Config::MS_PT_COURSE, [ $this->courses, 'sync_course_categories_on_save' ], 20, 3 );
        
        // Auditoría: Registrar acciones en cursos
        add_action( 'save_post_' . FairPlay_LMS_Config::MS_PT_COURSE, [ $this->courses, 'log_course_save' ], 30, 3 );
        add_action( 'before_delete_post', [ $this->courses, 'log_course_deletion' ], 10, 1 );
        
        // Auditoría: Registrar acciones en lecciones
        add_action( 'save_post_' . FairPlay_LMS_Config::MS_PT_LESSON, [ $this->courses, 'log_lesson_save' ], 10, 3 );
        add_action( 'before_delete_post', [ $this->courses, 'log_lesson_deletion' ], 10, 1 );
        
        // Auditoría: Registrar acciones en quizzes
        add_action( 'save_post_' . FairPlay_LMS_Config::MS_PT_QUIZ, [ $this->courses, 'log_quiz_save' ], 10, 3 );
        add_action( 'before_delete_post', [ $this->courses, 'log_quiz_deletion' ], 10, 1 );
        
        // Auditoría: Registrar eliminación/reactivación de usuarios
        add_action( 'delete_user', [ $this->users, 'handle_user_soft_delete' ], 5, 3 );
        add_action( 'admin_post_fplms_reactivate_user', [ $this->audit_admin, 'handle_user_reactivation' ] );
        add_action( 'admin_post_fplms_permanently_delete_user', [ $this->audit_admin, 'handle_user_permanent_deletion' ] );

        // Onboarding: Términos y Condiciones + email de bienvenida
        add_action( 'admin_menu',  [ $this->onboarding, 'register_admin_menu' ] );
        add_action( 'admin_init',  [ $this->onboarding, 'handle_terms_form' ] );
        add_action( 'init',        [ $this->onboarding, 'register_shortcode' ] );
        add_action( 'wp_ajax_fplms_resend_welcome', [ $this->onboarding, 'ajax_resend_welcome_email' ] );
        // Enviar email al crear usuario desde el panel FairPlay LMS
        add_action( 'fplms_user_created', [ $this->onboarding, 'send_welcome_email' ], 10, 1 );

        // Ajustes de Tests: menú + guardar configuración
        add_action( 'admin_menu', [ $this->quiz_settings, 'register_admin_menu' ] );
        add_action( 'admin_init', [ $this->quiz_settings, 'handle_save' ] );

        // Vigencia de quizzes: metabox + enforcement REST
        $this->quiz_availability->register_hooks();

        // Ponderación de preguntas: metabox + score filter REST
        $this->quiz_weights->register_hooks();

        // Encuestas de Satisfacción
        add_action( 'admin_init',                                              [ $this->survey, 'maybe_create_table' ], 1 );
        add_action( 'admin_menu',                                              [ $this->survey, 'register_admin_menu' ] );
        add_action( 'add_meta_boxes',                                          [ $this->survey, 'register_metabox' ] );
        add_action( 'save_post_' . FairPlay_LMS_Config::MS_PT_COURSE,          [ $this->survey, 'save_metabox' ], 20, 3 );
        add_action( 'wp_footer',                                               [ $this->survey, 'inject_survey_script' ] );
        add_action( 'wp_ajax_fplms_check_survey',                              [ $this->survey, 'ajax_check_survey' ] );
        add_action( 'wp_ajax_fplms_submit_survey',                             [ $this->survey, 'ajax_submit_survey' ] );
        add_action( 'wp_ajax_fplms_toggle_survey',                             [ $this->survey, 'ajax_toggle_survey' ] );
        add_action( 'wp_ajax_fplms_get_survey_settings',                       [ $this->survey, 'ajax_get_survey_settings' ] );
        add_action( 'wp_ajax_fplms_save_survey_settings',                      [ $this->survey, 'ajax_save_survey_settings' ] );

        // FEATURE: AJAX helpers para gestión de estructuras (usados desde panel admin de cursos)
        add_action( 'wp_ajax_fplms_get_frontend_structures',  [ $this->courses, 'ajax_get_frontend_structures' ] );
        add_action( 'wp_ajax_fplms_get_branch_roles',         [ $this->courses, 'ajax_get_branch_roles' ] );
        add_action( 'wp_ajax_fplms_save_frontend_structures', [ $this->courses, 'ajax_save_frontend_structures' ] );
        // Cascade multiselect en meta box de estructuras del editor clásico
        add_action( 'wp_ajax_fplms_cascade_structures',       [ $this->courses, 'ajax_cascade_structures' ] );
        // Nota: render_frontend_structure_panel NO se usa (reemplazado por jerarquía de subcategorías nativa)

        // Administradores tienen control total sobre todos los cursos MasterStudy,
        // independientemente de quién sea el autor del curso.
        add_filter( 'map_meta_cap', [ $this, 'grant_admin_full_course_control' ], 10, 4 );
    }

    /**
     * Permite a usuarios con rol administrator editar/eliminar/publicar cualquier
     * curso de MasterStudy aunque no sean el autor del post.
     *
     * @param array  $caps    Capacidades mapeadas.
     * @param string $cap     Capacidad solicitada.
     * @param int    $user_id ID del usuario.
     * @param array  $args    Argumentos adicionales (normalmente [post_id]).
     * @return array
     */
    public function grant_admin_full_course_control( array $caps, string $cap, int $user_id, $args ): array {
        $args = is_array( $args ) ? $args : [];
        // Solo actuar sobre operaciones de curso
        $course_caps = [ 'edit_post', 'delete_post', 'publish_post', 'read_post' ];
        if ( ! in_array( $cap, $course_caps, true ) ) {
            return $caps;
        }

        // Solo si hay un post_id y el usuario es administrador
        $post_id = ! empty( $args[0] ) ? (int) $args[0] : 0;
        if ( ! $post_id || ! user_can( $user_id, 'administrator' ) ) {
            return $caps;
        }

        // Solo para el tipo de post de cursos MasterStudy
        if ( get_post_type( $post_id ) !== FairPlay_LMS_Config::MS_PT_COURSE ) {
            return $caps;
        }

        // Eliminar cualquier restricción basada en autoría — el admin puede todo
        return array_diff( $caps, [ 'do_not_allow' ] );
    }

    /**
     * Inyecta un MutationObserver que, cuando Vue muestra cualquier mensaje de error
     * de login, consulta via AJAX si el fallo fue por cuenta inactiva y reemplaza el
     * texto. Usa transients (BD) en lugar de cookies para evitar el stripping de
     * headers por proxies/CDN/caching plugins.
     */
    public function inject_inactive_login_message_script(): void {
        if ( is_user_logged_in() ) {
            return;
        }
        $ajax_url = esc_js( admin_url( 'admin-ajax.php' ) );
        ?>
        <script>
        (function() {
            var MSG      = 'Usuario no habilitado, cont\u00e1ctate con el administrador del sitio.';
            var AJAX_URL = '<?php echo $ajax_url; ?>';

            // Selector exacto confirmado por inspecci\u00f3n DOM (MasterStudy 3.x):
            // <span data-error-id="wrong_password"
            //       class="masterstudy-authorization__form-field-error">Wrong password</span>
            var ERROR_SELS = [
                '.masterstudy-authorization__form-field-error',
                '[data-error-id]',
                '.stm-lms-login__error',
                '.stm-lms-form__error'
            ];

            // Busca el campo de email/usuario dentro del formulario de login
            function getLoginEmail() {
                var form = document.querySelector(
                    '.masterstudy-authorization__form, form[class*="authorization"], form[class*="login"]'
                );
                var root = form || document;
                var el = root.querySelector(
                    'input[type="email"], input[name="user_login"], input[name="log"], ' +
                    'input[name="email"], input[type="text"]'
                );
                return el ? el.value.trim() : '';
            }

            // ¿Algún selector de error tiene texto visible ahora?
            function hasVisibleError() {
                return ERROR_SELS.some(function(s) {
                    var els = document.querySelectorAll(s);
                    for (var i = 0; i < els.length; i++) {
                        if (els[i].textContent.trim()) return true;
                    }
                    return false;
                });
            }

            // Reemplaza el texto en todos los elementos de error visibles
            function replaceErrorText() {
                ERROR_SELS.forEach(function(s) {
                    document.querySelectorAll(s).forEach(function(el) {
                        if (el.textContent.trim()) el.textContent = MSG;
                    });
                });
                clearInterval(pollTimer);
                observer.disconnect();
            }

            var checking = false;

            function checkAndReplace() {
                if (checking) return;
                if (!hasVisibleError()) return;
                checking = true;
                var fd = new FormData();
                fd.append('action', 'fplms_check_blocked');
                fetch(AJAX_URL, { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data && data.success && data.data && data.data.blocked) {
                            replaceErrorText();
                        }
                    })
                    .catch(function() {})
                setTimeout(function() { checking = false; }, 2000);
            }

            // Polling: dispara checkAndReplace cada 600 ms hasta que reemplace o pase 30 s
            var pollTimer = setInterval(checkAndReplace, 600);
            setTimeout(function() { clearInterval(pollTimer); }, 30000);

            // MutationObserver simplificado: cualquier mutación DOM puede ser relevante
            var observer = new MutationObserver(function() {
                checkAndReplace();
            });

            function startObserver() {
                observer.observe(document.body, { childList: true, subtree: true, characterData: true });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', startObserver);
            } else {
                startObserver();
            }
        })();
        </script>
        <?php
    }

    /**
     * Fuerza que la unidad de tiempo del quiz sea siempre "minutes" en el servidor.
     * Se ejecuta DESPUÉS de que MasterStudy guarda los meta (prioridad 20).
     */
    public function enforce_quiz_duration_minutes( int $post_id ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        update_post_meta( $post_id, 'duration_measure', 'minutes' );
    }

    /**
     * Oculta el selector "Unidad de tiempo" en el course builder de MasterStudy
     * usando una regla CSS con :has() en el <head> — React no puede sobreescribir
     * un <style> del documento, a diferencia de los inline styles en nodos del SPA.
     */
    public function inject_quiz_duration_unit_lock_script(): void {
        if ( ! is_user_logged_in() || is_admin() ) {
            return;
        }
        ?>
        <style id="fplms-quiz-unit-lock">
        /* Ocultar campo "Unidad de tiempo" en ajustes de quiz.
           El selector :has() vive en el cascade CSS — React no puede borrarlo. */
        [role="group"]:has(input[name="duration_measure"]) {
            display: none !important;
        }
        </style>
        <script id="fplms-quiz-unit-lock-js">
        (function () {
            'use strict';
            /* Oculta el grupo [role="group"] que contiene el input oculto
               duration_measure. Se usa setProperty con !important para que
               sobreviva cualquier inline-style que React pueda inyectar.
               NO se despacha ningún evento, así no se provoca re-render. */
            function hideDurationField() {
                var inputs = document.querySelectorAll('input[name="duration_measure"]');
                for (var i = 0; i < inputs.length; i++) {
                    var group = inputs[i].closest('[role="group"]');
                    if (group) {
                        group.style.setProperty('display', 'none', 'important');
                    }
                }
            }
            hideDurationField();
            if (window.MutationObserver) {
                new MutationObserver(function (mutations) {
                    for (var i = 0; i < mutations.length; i++) {
                        if (mutations[i].addedNodes.length > 0) {
                            hideDurationField();
                            break;
                        }
                    }
                }).observe(document.body, { childList: true, subtree: true });
            }
        })();
        </script>
        <?php
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
     * Filtra cursos por estructura del usuario vía pre_get_posts.
     * - Frontend: aplica a todos los usuarios no administradores.
     * - Admin: aplica solo a instructores.
     */
    public function filter_courses_pre_get_posts( WP_Query $query ): void {

        if ( ! $query->is_main_query() ) {
            return;
        }

        if ( $query->get( 'post_type' ) !== FairPlay_LMS_Config::MS_PT_COURSE ) {
            return;
        }

        $user_id = get_current_user_id();

        if ( 0 === $user_id || current_user_can( 'manage_options' ) ) {
            return;
        }

        // En admin, solo aplicar a instructores
        if ( is_admin() ) {
            $user = wp_get_current_user();
            if ( ! in_array( FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR, (array) $user->roles, true ) ) {
                return;
            }
        }

        $visible = $this->visibility->get_visible_courses_for_user( $user_id );

        // Si MasterStudy ya puso un post__in (cursos matriculados del usuario),
        // hacemos la intersección para no sobreescribir su filtro de matrícula.
        $existing = array_filter( (array) $query->get( 'post__in' ) );
        if ( ! empty( $existing ) ) {
            $visible = array_values( array_intersect( $existing, $visible ) );
        }

        $query->set( 'post__in', ! empty( $visible ) ? $visible : [ 0 ] );
    }

    /**
     * Auto-asigna la estructura del instructor al crear un nuevo curso.
     * Solo actúa en la creación (no en actualizaciones) y solo para instructores.
     *
     * @param int     $post_id ID del post.
     * @param WP_Post $post    Objeto post.
     * @param bool    $update  True si es una actualización, false si es creación.
     */
    public function auto_assign_instructor_structure_to_course( int $post_id, WP_Post $post, bool $update ): void {

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( $update ) {
            return; // Solo en creación
        }

        if ( current_user_can( 'manage_options' ) ) {
            return; // Los administradores asignan estructuras manualmente
        }

        $current_user = wp_get_current_user();

        if ( ! in_array( FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR, (array) $current_user->roles, true ) ) {
            return;
        }

        $meta_map = [
            FairPlay_LMS_Config::META_COURSE_CITIES    => FairPlay_LMS_Config::USER_META_CITY,
            FairPlay_LMS_Config::META_COURSE_COMPANIES => FairPlay_LMS_Config::USER_META_COMPANY,
            FairPlay_LMS_Config::META_COURSE_CHANNELS  => FairPlay_LMS_Config::USER_META_CHANNEL,
            FairPlay_LMS_Config::META_COURSE_BRANCHES  => FairPlay_LMS_Config::USER_META_BRANCH,
        ];

        foreach ( $meta_map as $course_meta => $user_meta ) {
            $val = (int) get_user_meta( $current_user->ID, $user_meta, true );
            if ( $val > 0 ) {
                update_post_meta( $post_id, $course_meta, [ $val ] );
            }
        }
    }

    /**
     * Filtra la REST query de la taxonomía de categorías de cursos para instructores.
     * Aplica la misma restricción que restrict_course_categories_for_instructor pero
     * sobre el endpoint REST `/wp-json/wp/v2/stm_lms_course_taxonomy` que usa el
     * Vue Course Builder del frontend.
     *
     * @param array           $args    Argumentos preparados para WP_Term_Query.
     * @param WP_REST_Request $request La petición REST.
     * @return array
     */
    public function restrict_categories_rest_query( array $args, WP_REST_Request $request ): array {
        if ( current_user_can( 'manage_options' ) ) {
            return $args;
        }

        $user = wp_get_current_user();
        if ( ! in_array( FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR, (array) $user->roles, true ) ) {
            return $args;
        }

        $channel_id = (int) get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_CHANNEL, true );
        if ( ! $channel_id ) {
            $args['include'] = [ 0 ];
            return $args;
        }

        $root_cat_id = (int) get_term_meta( $channel_id, 'fplms_linked_category_id', true );
        if ( ! $root_cat_id ) {
            $args['include'] = [ 0 ];
            return $args;
        }

        $allowed  = [ $root_cat_id ];
        $children = get_term_children( $root_cat_id, FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY );
        if ( ! is_wp_error( $children ) ) {
            $allowed = array_merge( $allowed, $children );
        }

        $args['include'] = $allowed;
        return $args;
    }

    /**
     * Restringe la lista de categorías de cursos (stm_lms_course_taxonomy) para que
     * los instructores solo vean las categorías vinculadas a su canal y sus subcategorías.
     *
     * Se activa via el hook 'pre_get_terms'.
     *
     * @param WP_Term_Query $query Objeto de consulta de términos.
     */
    public function restrict_course_categories_for_instructor( WP_Term_Query $query ): void {
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }

        $taxonomies = (array) ( $query->query_vars['taxonomy'] ?? [] );
        if ( ! in_array( FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY, $taxonomies, true ) ) {
            return;
        }

        $user = wp_get_current_user();
        if ( ! in_array( FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR, (array) $user->roles, true ) ) {
            return;
        }

        $channel_id = (int) get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_CHANNEL, true );
        if ( ! $channel_id ) {
            // Sin canal asignado: no mostrar ninguna categoría
            $query->query_vars['include'] = [ 0 ];
            return;
        }

        $root_cat_id = (int) get_term_meta( $channel_id, 'fplms_linked_category_id', true );
        if ( ! $root_cat_id ) {
            $query->query_vars['include'] = [ 0 ];
            return;
        }

        $allowed  = [ $root_cat_id ];
        $children = get_term_children( $root_cat_id, FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY );
        if ( ! is_wp_error( $children ) ) {
            $allowed = array_merge( $allowed, $children );
        }

        $query->query_vars['include'] = $allowed;
    }

    /**
     * Al guardar un curso, si el usuario es instructor y ha asignado una categoría
     * que no pertenece a su canal, la reemplaza por la categoría raíz de su canal.
     *
     * Es la contraparte backend de restrict_course_categories_for_instructor.
     *
     * @param int     $post_id ID del post.
     * @param WP_Post $post    Objeto post.
     * @param bool    $update  True si es actualización.
     */
    public function enforce_instructor_category_on_save( int $post_id, WP_Post $post, bool $update ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }

        $current_user = wp_get_current_user();
        if ( ! in_array( FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR, (array) $current_user->roles, true ) ) {
            return;
        }

        $channel_id = (int) get_user_meta( $current_user->ID, FairPlay_LMS_Config::USER_META_CHANNEL, true );
        if ( ! $channel_id ) {
            return;
        }

        $root_cat_id = (int) get_term_meta( $channel_id, 'fplms_linked_category_id', true );
        if ( ! $root_cat_id ) {
            return;
        }

        // Categorías permitidas: la raíz del canal + todas sus subcategorías
        $allowed  = [ $root_cat_id ];
        $children = get_term_children( $root_cat_id, FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY );
        if ( ! is_wp_error( $children ) ) {
            $allowed = array_merge( $allowed, $children );
        }

        $assigned = wp_get_post_terms( $post_id, FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY, [ 'fields' => 'ids' ] );
        if ( is_wp_error( $assigned ) || empty( $assigned ) ) {
            return;
        }

        $valid = array_values(
            array_filter( $assigned, fn( $t ) => in_array( (int) $t, $allowed, true ) )
        );

        if ( count( $valid ) === count( $assigned ) ) {
            return; // Todo correcto, no hay nada que corregir
        }

        // Hay términos no permitidos: mantener solo los válidos, o forzar la raíz
        if ( empty( $valid ) ) {
            $valid = [ $root_cat_id ];
        }

        // Evitar recursar en sync_categories_to_structures mientras corregimos
        remove_action( 'set_object_terms', [ $this->courses, 'sync_categories_to_structures' ], 10 );
        wp_set_post_terms( $post_id, $valid, FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY );
        add_action( 'set_object_terms', [ $this->courses, 'sync_categories_to_structures' ], 10, 6 );
    }

    /**
     * Obtener instancia del controlador de usuarios.
     * 
     * @return FairPlay_LMS_Users_Controller
     */
    public function get_users_controller(): FairPlay_LMS_Users_Controller {
        return $this->users;
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

