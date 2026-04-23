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

        // Panel /user-account/: estadísticas personalizadas por rol
        add_action( 'wp_ajax_fplms_dashboard_stats',  [ $this, 'ajax_dashboard_stats' ] );
        add_action( 'wp_footer',                       [ $this, 'inject_student_dashboard_script' ] );
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

    /**
     * Endpoint AJAX: devuelve estadísticas del panel /user-account/ según el tipo solicitado.
     * type=student  → FairPlay_LMS_Progress_Service::get_student_dashboard_stats()
     * type=instructor → FairPlay_LMS_Progress_Service::get_instructor_dashboard_stats()
     */
    public function ajax_dashboard_stats(): void {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'not_logged_in', 403 );
        }

        check_ajax_referer( 'fplms_dashboard_stats', 'nonce' );

        $type    = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : 'student';
        $user_id = get_current_user_id();

        // Modo debug: devuelve los meta de los cursos completados (solo admin)
        if ( 'debug_hours' === $type && current_user_can( 'manage_options' ) ) {
            global $wpdb;
            $stats   = $this->progress->get_student_dashboard_stats( $user_id );
            // Forzar recálculo limpio sin transient
            delete_transient( 'fplms_sdash_v4_' . $user_id );
            $stats2  = $this->progress->get_student_dashboard_stats( $user_id );
            // Obtener IDs de cursos completados directamente
            $ms_table = null;
            foreach ( [ $wpdb->prefix . 'stm_lms_user_courses', $wpdb->prefix . 'stm_lms_users' ] as $t ) {
                if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) {
                    $ms_table = $t;
                    break;
                }
            }
            $completed_ids = [];
            if ( $ms_table ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $rows = $wpdb->get_results( $wpdb->prepare(
                    "SELECT course_id, progress_percent, status FROM `{$ms_table}` WHERE user_id = %d",
                    $user_id
                ) );
                foreach ( $rows as $r ) {
                    if ( (float) $r->progress_percent >= 99.9 || strtolower( $r->status ) === 'completed' ) {
                        $completed_ids[] = (int) $r->course_id;
                    }
                }
            }
            $meta_dump = [];
            // Solo dump completo del primer curso para no saturar la respuesta
            $first_cid = ! empty( $completed_ids ) ? $completed_ids[0] : 0;
            if ( $first_cid ) {
                $all_meta = $wpdb->get_results( $wpdb->prepare(
                    "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_key",
                    $first_cid
                ) );
                $meta_dump[ $first_cid ] = [
                    'title' => get_the_title( $first_cid ),
                    'all_metas' => $all_meta,
                ];
            }
            // Además: buscar en tablas custom de MasterStudy
            $custom_tables = $wpdb->get_results(
                "SHOW TABLES LIKE '%stm_lms%'"
            );
            $stm_tables = array_map( function($r) { return array_values( (array) $r )[0]; }, $custom_tables );
            wp_send_json_success( [
                'cached_stats'   => $stats,
                'fresh_stats'    => $stats2,
                'completed_ids'  => $completed_ids,
                'meta_dump'      => $meta_dump,
                'ms_table'       => $ms_table,
                'stm_tables'     => $stm_tables,
            ] );
        }

        if ( 'instructor' === $type ) {
            $user_roles = (array) wp_get_current_user()->roles;
            $is_instructor = in_array( 'stm_lms_instructor', $user_roles, true )
                          || in_array( 'administrator', $user_roles, true )
                          || current_user_can( 'manage_options' );
            if ( ! $is_instructor ) {
                wp_send_json_error( 'forbidden', 403 );
            }
            wp_send_json_success( $this->progress->get_instructor_dashboard_stats( $user_id ) );
        } else {
            wp_send_json_success( $this->progress->get_student_dashboard_stats( $user_id ) );
        }
    }

    /**
     * Inyecta en wp_footer el script que reemplaza los bloques de estadísticas del panel
     * /user-account/ de MasterStudy con métricas relevantes para cada rol:
     * - Elemento .masterstudy-enrolled-courses-sorting  → vista estudiante (5 métricas)
     * - Elemento .masterstudy-analytics-short-report-page-stats__wrapper → vista instructor
     */
    public function inject_student_dashboard_script(): void {
        // Solo ejecutar en páginas del frontend con el usuario logueado
        if ( is_admin() || ! is_user_logged_in() ) {
            return;
        }

        // Limitar a la URL del panel de cuenta
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        if ( false === strpos( $request_uri, '/user-account' ) ) {
            return;
        }

        $ajax_url      = admin_url( 'admin-ajax.php' );
        $nonce         = wp_create_nonce( 'fplms_dashboard_stats' );
        $struct_nonce  = wp_create_nonce( 'fplms_frontend_structures' );
        ?>
        <script id="fplms-dashboard-stats-script">
        (function () {
            'use strict';

            var AJAX_URL      = <?php echo wp_json_encode( $ajax_url ); ?>;
            var NONCE         = <?php echo wp_json_encode( $nonce ); ?>;
            var STRUCT_NONCE  = <?php echo wp_json_encode( $struct_nonce ); ?>;
            var renderedStudent       = false;
            var renderedInstructor    = false;
            var searchInjected        = false;
            var searchInjectedInstr   = false;
            var studentCustomFilter        = null;  // { ids: [], tabEl: Element } | null
            var instrCustomFilter          = null;
            var studentCardObsSetup        = false;
            var instrCardObsSetup          = false;
            var programmaticStudentClick   = false; // bandera para click programático en tab "all"
            var programmaticInstrClick     = false;
            var instrCoursesData           = null;  // datos del instructor (courses_list, etc.)

            /* ── Helpers de filtrado por ID de curso ────────────────────────── */

            function extractCourseIdFromCard( card ) {
                if ( card.dataset && card.dataset.id )       return parseInt( card.dataset.id );
                if ( card.dataset && card.dataset.courseId ) return parseInt( card.dataset.courseId );
                var attr = card.querySelector( '[data-id]' );
                if ( attr ) return parseInt( attr.dataset.id );
                // Tarjeta "coming soon": el ID está en id="countdown_XXXX"
                var countdown = card.querySelector( '[id^="countdown_"]' );
                if ( countdown ) {
                    var cm = countdown.id.match( /countdown_(\d+)/ );
                    if ( cm ) return parseInt( cm[1] );
                }
                // Botón / enlace con ID como último segmento de ruta: /slug/12345  o  /slug/12345/
                var links = card.querySelectorAll( 'a[href]' );
                for ( var i = 0; i < links.length; i++ ) {
                    var href = links[i].href;
                    // último segmento numérico (mínimo 3 dígitos para no confundir páginas)
                    var pm = href.match( /\/(\d{3,})\/?(?:[?#]|$)/ );
                    if ( pm ) return parseInt( pm[1] );
                    var qm = href.match( /[?&](?:course_id|course-id|id)=(\d+)/ );
                    if ( qm ) return parseInt( qm[1] );
                }
                return 0;
            }

            // Extrae el % de progreso de una tarjeta desde la barra o texto de progreso.
            function extractProgressFromCard( card ) {
                var bar = card.querySelector( '.masterstudy-course-card__progress-bar_filled' );
                if ( bar ) {
                    var w = parseFloat( bar.style.width || '-1' );
                    if ( ! isNaN( w ) && w >= 0 ) return w;
                }
                var txt = card.querySelector( '.masterstudy-course-card__progress-title' );
                if ( txt ) {
                    var m = txt.textContent.match( /(\d+(?:\.\d+)?)\s*%/ );
                    if ( m ) return parseFloat( m[1] );
                }
                // Botón de acción: "Completado" → 100
                var btn = card.querySelector( '.masterstudy-button__title' );
                if ( btn ) {
                    var t = btn.textContent.trim().toLowerCase();
                    if ( t === 'completado' || t === 'completed' ) return 100;
                }
                return -1; // sin datos (coming soon, no iniciado)
            }

            function applyStudentCustomFilter() {
                if ( ! studentCustomFilter ) return;
                var type  = studentCustomFilter.type || 'ids';
                var ids   = studentCustomFilter.ids || [];
                var scope = document.querySelector( '.masterstudy-enrolled-courses' ) || document;
                var cards = scope.querySelectorAll(
                    '.masterstudy-course-card, .masterstudy-enrolled-courses-list__item, .masterstudy-enrolled-courses__item'
                );
                cards.forEach( function ( card ) {
                    var visible;
                    if ( type === 'in_progress' ) {
                        var p = extractProgressFromCard( card );
                        visible = p > 1 && p < 100;
                    } else if ( type === 'completed' ) {
                        var p = extractProgressFromCard( card );
                        visible = p >= 100;
                    } else {
                        var cid = extractCourseIdFromCard( card );
                        visible = ( cid && ids.indexOf( cid ) !== -1 );
                    }
                    card.style.display = visible ? '' : 'none';
                } );
                // Re-marcar tab como activo (Vue lo resetó al clickar "all")
                var tabEl  = studentCustomFilter.tabEl;
                var blocks = tabEl && tabEl.closest( '.masterstudy-enrolled-courses-tabs__blocks' );
                if ( blocks ) {
                    blocks.querySelectorAll( '.masterstudy-enrolled-courses-tabs__block' ).forEach( function ( b ) {
                        b.classList.remove( 'masterstudy-enrolled-courses-tabs__block_active' );
                    } );
                    tabEl.classList.add( 'masterstudy-enrolled-courses-tabs__block_active' );
                }
            }

            function applyInstrCustomFilter() {
                if ( ! instrCustomFilter ) return;
                var ids   = instrCustomFilter.ids;
                var scope = document.querySelector( '.masterstudy-analytics-short-report-page' ) || document;
                var cards = scope.querySelectorAll(
                    '.masterstudy-course-card, .masterstudy-courses-list__item, .masterstudy-my-courses__item, .masterstudy-instructor-courses__item'
                );
                cards.forEach( function ( card ) {
                    var cid = extractCourseIdFromCard( card );
                    card.style.display = ( cid && ids.indexOf( cid ) !== -1 ) ? '' : 'none';
                } );
                var tabEl = instrCustomFilter.tabEl;
                var tabs  = tabEl && tabEl.closest( '.masterstudy-tabs' );
                if ( tabs ) {
                    tabs.querySelectorAll( '.masterstudy-tabs__item' ).forEach( function ( li ) {
                        li.classList.remove( 'masterstudy-tabs__item_active' );
                    } );
                    tabEl.classList.add( 'masterstudy-tabs__item_active' );
                }
            }

            /* ── Barra buscadora (sin filtros propios) ─────────────────────── */

            function injectSearchBar( statsEl, cfg ) {
                if ( document.getElementById( cfg.wrapperId ) ) return;

                var wrapper = document.createElement( 'div' );
                wrapper.id  = cfg.wrapperId;
                wrapper.style.cssText = 'margin:16px 0 18px;position:relative;';
                wrapper.innerHTML =
                    '<input id="' + cfg.inputId + '" type="search" placeholder="' + cfg.placeholder + '" ' +
                    'style="width:100%;padding:10px 16px 10px 44px;border:1.5px solid #e0e0e0;border-radius:8px;' +
                    'font-size:14px;background:#fff;outline:none;box-sizing:border-box;transition:border-color .2s;" />' +
                    '<svg style="position:absolute;left:14px;top:50%;transform:translateY(-50%);' +
                    'color:#aaa;pointer-events:none;" width="18" height="18" fill="none" ' +
                    'viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">' +
                    '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';

                var noResults = document.createElement( 'p' );
                noResults.id  = cfg.noResultsId;
                noResults.style.cssText = 'display:none;text-align:center;color:#888;padding:24px 0;font-size:14px;margin:0;';
                noResults.textContent   = 'No se encontraron cursos.';

                var anchor = cfg.insertAnchor || statsEl;
                var parent = anchor.parentNode;
                parent.insertBefore( wrapper,   anchor.nextSibling );
                parent.insertBefore( noResults, wrapper.nextSibling );

                var input = wrapper.querySelector( 'input' );
                function doSearch() {
                    var q = input.value.trim().toLowerCase();
                    var scope = cfg.scopeSelector ? ( statsEl.closest( cfg.scopeSelector ) || document ) : document;
                    var cards = scope.querySelectorAll( cfg.cardSelectors );
                    var found = 0;
                    cards.forEach( function ( card ) {
                        var titleEl = card.querySelector( '.masterstudy-course-card__title, .masterstudy-course-card__name, h3, h4' );
                        var title = titleEl ? titleEl.textContent.toLowerCase() : card.textContent.toLowerCase();
                        var visible = ! q || title.indexOf( q ) !== -1;
                        card.style.display = visible ? '' : 'none';
                        if ( visible ) found++;
                    } );
                    var nr = document.getElementById( cfg.noResultsId );
                    if ( nr ) nr.style.display = ( q && found === 0 && cards.length > 0 ) ? 'block' : 'none';
                }
                input.addEventListener( 'input', doSearch );
                input.addEventListener( 'focus', function () { this.style.borderColor = '#ffa800d9'; } );
                input.addEventListener( 'blur',  function () { this.style.borderColor = '#e0e0e0'; } );

                // Limpiar filtro de texto cuando Vue re-renderiza (cambio de tab nativo)
                var listContainer = cfg.scopeSelector ? ( statsEl.closest( cfg.scopeSelector ) || parent ) : parent;
                new MutationObserver( function () {
                    if ( input.value.trim() ) doSearch();
                } ).observe( listContainer, { childList: true, subtree: true } );
            }

            /* ── Inyectar tabs nativos ────────────────────────────────────── */

            /**
             * Estudiante: agrega tabs "Próximo" y "Por Vencer" al bloque nativo
             * .masterstudy-enrolled-courses-tabs__blocks replicando la estructura existente.
             */
            function injectStudentTabs( data ) {
                var filtersInjected = false;

                var BTN_BASE   = 'padding:6px 16px;border-radius:20px;border:1.5px solid #ddd;' +
                                 'background:#f5f5f5;color:#555;font-size:13px;cursor:pointer;' +
                                 'white-space:nowrap;transition:all .18s;font-weight:500;line-height:1.5;';
                var BTN_ACTIVE = 'padding:6px 16px;border-radius:20px;border:1.5px solid #ffa800;' +
                                 'background:#ffa800;color:#fff;font-size:13px;cursor:pointer;' +
                                 'white-space:nowrap;transition:all .18s;font-weight:600;line-height:1.5;';

                function tryInject() {
                    if ( filtersInjected ) return;
                    var blocks = document.querySelector( '.masterstudy-enrolled-courses-tabs__blocks' );
                    if ( ! blocks ) return;
                    if ( document.getElementById( 'fplms-filter-buttons' ) ) { filtersInjected = true; return; }
                    filtersInjected = true;

                    // Ocultar todos los tabs nativos excepto "Todos"
                    // Vue sólo verá el tab "all" → siempre carga todos los cursos → estado consistente
                    blocks.querySelectorAll( '[data-status]' ).forEach( function ( t ) {
                        if ( t.dataset.status && t.dataset.status !== 'all' ) {
                            t.style.display = 'none';
                        }
                    } );

                    // ── Botones de filtro (reemplazan a los tabs) ─────────────────────────
                    // Cada botón filtra client-side las cards ya cargadas por Vue.
                    // No hay AJAX, no hay stopPropagation, no hay conflictos con Vue.
                    var wrap = document.createElement( 'div' );
                    wrap.id = 'fplms-filter-buttons';
                    wrap.style.cssText = 'display:flex;flex-wrap:wrap;gap:8px;margin:14px 0 6px;';

                    var defs = [
                        { label: 'Todos',       type: 'all',         ids: [],                      count: data.enrolled       || 0 },
                        { label: 'Completado',  type: 'completed',   ids: [],                      count: data.completed      || 0 },
                        { label: 'En Progreso', type: 'in_progress', ids: [],                      count: data.in_progress_count || 0 },
                        { label: 'Próximo',     type: 'ids',         ids: data.upcoming_ids || [],  count: data.upcoming_count || 0 },
                        { label: 'Por Vencer',  type: 'ids',         ids: data.expiring_ids || [],  count: data.expiring_count || 0 },
                    ];

                    var activeBtn = null;
                    function setActive( btn ) {
                        if ( activeBtn ) activeBtn.style.cssText = BTN_BASE;
                        activeBtn = btn;
                        btn.style.cssText = BTN_ACTIVE;
                    }

                    defs.forEach( function ( def ) {
                        var btn  = document.createElement( 'button' );
                        btn.type = 'button';
                        btn.textContent = def.label + ( def.count !== null ? ' (' + def.count + ')' : '' );
                        btn.style.cssText = BTN_BASE;

                        btn.addEventListener( 'click', function () {
                            setActive( btn );
                            var scope = document.querySelector( '.masterstudy-enrolled-courses' ) || document;
                            var cards = scope.querySelectorAll(
                                '.masterstudy-course-card, .masterstudy-enrolled-courses-list__item, .masterstudy-enrolled-courses__item'
                            );
                            if ( def.type === 'all' ) {
                                studentCustomFilter = null;
                                cards.forEach( function ( c ) { c.style.display = ''; } );
                            } else {
                                studentCustomFilter = { type: def.type, ids: def.ids, tabEl: null };
                                applyStudentCustomFilter();
                            }
                        } );

                        if ( def.type === 'all' ) setActive( btn );
                        wrap.appendChild( btn );
                    } );

                    // Insertar después del mensaje "sin resultados" del buscador
                    var noResultsEl  = document.getElementById( 'fplms-no-results' );
                    var searchWrapEl = document.getElementById( 'fplms-course-search-wrapper' );
                    var anchor       = noResultsEl || searchWrapEl;
                    if ( anchor && anchor.parentNode ) {
                        anchor.parentNode.insertBefore( wrap, anchor.nextSibling );
                    } else if ( blocks.parentNode ) {
                        blocks.parentNode.insertBefore( wrap, blocks );
                    }

                    // MutationObserver: re-aplicar filtro cuando Vue re-renderiza la lista
                    // (p.ej. carga de página siguiente o recarga al volver al tab "Todos")
                    if ( ! studentCardObsSetup ) {
                        studentCardObsSetup = true;
                        var courseList = document.querySelector( '.masterstudy-enrolled-courses' );
                        if ( courseList ) {
                            var filterTimer = null;
                            new MutationObserver( function () {
                                if ( studentCustomFilter ) {
                                    clearTimeout( filterTimer );
                                    filterTimer = setTimeout( applyStudentCustomFilter, 80 );
                                }
                            } ).observe( courseList, { childList: true, subtree: true } );
                        }
                    }
                }

                tryInject();
                if ( ! filtersInjected ) {
                    var obs = new MutationObserver( function () {
                        tryInject();
                        if ( filtersInjected ) obs.disconnect();
                    } );
                    obs.observe( document.body, { childList: true, subtree: true } );
                    setTimeout( function () { obs.disconnect(); }, 15000 );
                }
            }

            /**
             * Construye la tabla de gestión de cursos dentro del contenedor dado.
             * Reutilizable desde showMisCursosPage().
             */
            function buildInstructorCoursePanel( container, courses ) {
                // ── Estilos (una sola vez) ────────────────────────────────────────────
                if ( ! document.getElementById( 'fplms-icp-styles' ) ) {
                    var style = document.createElement( 'style' );
                    style.id  = 'fplms-icp-styles';
                    style.textContent =
                        '#fplms-mis-cursos-page{font-family:inherit;padding:0;}' +
                        '#fplms-mis-cursos-page h2{font-size:20px;font-weight:700;color:#222;margin:0 0 20px;}' +
                        '#fplms-icp-toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:16px;}' +
                        '#fplms-icp-search{flex:1 1 220px;min-width:160px;padding:9px 14px 9px 40px;' +
                        'border:1.5px solid #e0e0e0;border-radius:8px;font-size:13px;outline:none;' +
                        'box-sizing:border-box;background:#fff;transition:border-color .2s;}' +
                        '#fplms-icp-search:focus{border-color:#ffa800;}' +
                        '.fplms-icp-filter{padding:7px 18px;border-radius:20px;border:1.5px solid #ddd;' +
                        'background:#f5f5f5;color:#555;font-size:13px;cursor:pointer;font-weight:500;' +
                        'white-space:nowrap;transition:all .15s;}' +
                        '.fplms-icp-filter.active{border-color:#ffa800;background:#ffa800;color:#fff;font-weight:600;}' +
                        '.fplms-icp-export-btn{padding:7px 14px;border-radius:7px;border:1.5px solid #ddd;' +
                        'background:#fff;color:#555;font-size:12px;cursor:pointer;font-weight:500;' +
                        'display:inline-flex;align-items:center;gap:5px;white-space:nowrap;transition:all .15s;}' +
                        '.fplms-icp-export-btn:hover{border-color:#ffa800;color:#ffa800;}' +
                        '.fplms-icp-export-btn:disabled{opacity:.45;cursor:not-allowed;}' +
                        '#fplms-icp-sel-bar{display:none;align-items:center;gap:10px;padding:8px 14px;' +
                        'background:#fff8ec;border:1.5px solid #ffa800;border-radius:8px;margin-bottom:10px;font-size:13px;}' +
                        '#fplms-icp-sel-bar.visible{display:flex;}' +
                        '#fplms-icp-sel-count{font-weight:600;color:#e07b00;}' +
                        '#fplms-icp-table{width:100%;border-collapse:collapse;font-size:13px;}' +
                        '#fplms-icp-table th{background:#f8f8f8;color:#444;font-weight:600;padding:11px 14px;' +
                        'text-align:left;border-bottom:2px solid #e8e8e8;white-space:nowrap;}' +
                        '#fplms-icp-table th.fplms-icp-th-chk{width:36px;text-align:center;}' +
                        '#fplms-icp-table td{padding:11px 14px;border-bottom:1px solid #f0f0f0;vertical-align:middle;color:#333;}' +
                        '#fplms-icp-table td.fplms-icp-td-chk{text-align:center;width:36px;}' +
                        '#fplms-icp-table tr:hover td{background:#fffaf0;}' +
                        '#fplms-icp-table tr.fplms-icp-selected td{background:#fff8ec;}' +
                        '.fplms-icp-chk{width:16px;height:16px;cursor:pointer;accent-color:#ffa800;}' +
                        '.fplms-icp-title{font-weight:600;color:#222;}' +
                        '.fplms-icp-title a{color:#222;text-decoration:none;}' +
                        '.fplms-icp-title a:hover{color:#ffa800;}' +
                        '.fplms-icp-id{font-size:11px;color:#888;display:block;margin-top:2px;}' +
                        '.fplms-icp-tag{display:inline-block;background:#f0f4ff;color:#4466cc;' +
                        'border-radius:4px;padding:2px 7px;font-size:11px;margin:2px 2px 2px 0;}' +
                        '.fplms-icp-tag-exp{background:#fff4e0;color:#e07b00;}' +
                        '.fplms-icp-actions{display:flex;gap:6px;align-items:center;}' +
                        '.fplms-icp-btn{display:inline-flex;align-items:center;justify-content:center;' +
                        'width:32px;height:32px;border-radius:7px;border:1.5px solid #ddd;' +
                        'background:#fff;color:#666;text-decoration:none;transition:all .15s;cursor:pointer;' +
                        'font-size:11px;font-weight:600;}' +
                        '.fplms-icp-btn:hover{border-color:#ffa800;color:#ffa800;background:#fffaf0;}' +
                        '.fplms-icp-empty{text-align:center;color:#aaa;padding:40px;font-size:14px;}' +
                        '#fplms-icp-pagination{display:flex;align-items:center;gap:5px;margin-top:14px;flex-wrap:wrap;}' +
                        '.fplms-icp-pg-btn{min-width:32px;height:32px;padding:0 8px;border-radius:6px;border:1.5px solid #ddd;' +
                        'background:#fff;color:#555;font-size:13px;cursor:pointer;transition:all .15s;}' +
                        '.fplms-icp-pg-btn.active{border-color:#ffa800;background:#ffa800;color:#fff;font-weight:700;}' +
                        '.fplms-icp-pg-btn:hover:not(.active):not(:disabled){border-color:#ffa800;color:#ffa800;}' +
                        '.fplms-icp-pg-btn:disabled{opacity:.35;cursor:not-allowed;}' +
                        '#fplms-icp-bottom-bar{display:flex;align-items:center;justify-content:space-between;' +
                        'margin-top:12px;flex-wrap:wrap;gap:8px;}' +
                        '#fplms-icp-bottom-bar .dt-length{display:flex;align-items:center;gap:6px;font-size:13px;color:#555;}' +
                        '#fplms-icp-bottom-bar .dt-length .dt-input{padding:4px 8px;border:1px solid #d0d0d0;' +
                        'border-radius:4px;font-size:13px;color:#333;background:#fff;cursor:pointer;}' +
                        /* Modal estructuras */
                        '#fplms-struct-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999;' +
                        'align-items:center;justify-content:center;}' +
                        '#fplms-struct-overlay.open{display:flex;}' +
                        '#fplms-struct-modal{background:#fff;border-radius:12px;padding:28px 28px 22px;width:96%;max-width:520px;' +
                        'max-height:90vh;overflow-y:auto;box-shadow:0 8px 40px rgba(0,0,0,.18);}' +
                        '#fplms-struct-modal h3{margin:0 0 6px;font-size:17px;font-weight:700;color:#222;}' +
                        '#fplms-struct-modal .fplms-sm-sub{font-size:12px;color:#999;margin:0 0 18px;}' +
                        '.fplms-sm-section{margin-bottom:18px;}' +
                        '.fplms-sm-section label.fplms-sm-title{display:block;font-size:12px;font-weight:700;' +
                        'color:#666;text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px;}' +
                        '.fplms-sm-tags{display:flex;flex-wrap:wrap;gap:6px;}' +
                        '.fplms-sm-tag{padding:5px 12px;border-radius:20px;border:1.5px solid #ddd;' +
                        'background:#f5f5f5;color:#555;font-size:12px;cursor:pointer;transition:all .15s;font-weight:500;}' +
                        '.fplms-sm-tag.selected{border-color:#ffa800;background:#ffa800;color:#fff;font-weight:600;}' +
                        '.fplms-sm-tag:disabled,.fplms-sm-tag[disabled]{opacity:.4;cursor:not-allowed;}' +
                        '.fplms-sm-loading{text-align:center;color:#aaa;padding:16px;font-size:13px;}' +
                        '.fplms-sm-footer{display:flex;justify-content:flex-end;gap:10px;margin-top:18px;' +
                        'padding-top:14px;border-top:1px solid #f0f0f0;}' +
                        '.fplms-sm-cancel{padding:9px 18px;border-radius:8px;border:1.5px solid #ddd;' +
                        'background:#fff;color:#555;font-size:13px;cursor:pointer;font-weight:500;}' +
                        '.fplms-sm-save{padding:9px 22px;border-radius:8px;border:none;' +
                        'background:#ffa800;color:#fff;font-size:13px;cursor:pointer;font-weight:700;transition:opacity .15s;}' +
                        '.fplms-sm-save:disabled{opacity:.5;cursor:not-allowed;}' +
                        '.fplms-sm-msg{margin-top:10px;font-size:13px;text-align:center;font-weight:500;}' +
                        '.fplms-sm-msg.ok{color:#27ae60;}.fplms-sm-msg.err{color:#e74c3c;}' +
                        '@media(max-width:640px){#fplms-icp-table thead{display:none;}' +
                        '#fplms-icp-table tr{display:block;margin-bottom:14px;border:1px solid #eee;' +
                        'border-radius:10px;padding:12px;}' +
                        '#fplms-icp-table td{display:block;padding:4px 8px;border:none;}' +
                        '#fplms-icp-table td:before{content:attr(data-label);font-weight:600;color:#666;' +
                        'display:block;font-size:11px;margin-bottom:2px;}}';
                    document.head.appendChild( style );
                }

                var expCount = courses.filter( function ( c ) { return c.is_expiring; } ).length;

                // ── Estructura HTML del panel ─────────────────────────────────────────
                container.innerHTML =
                    '<h2>Mis Cursos</h2>' +
                    // barra de selección (visible solo cuando hay ítems marcados)
                    '<div id="fplms-icp-sel-bar">' +
                    '<span id="fplms-icp-sel-count">0 seleccionados</span>' +
                    '<button class="fplms-icp-export-btn" id="fplms-icp-exp-xls">' +
                    '<svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">' +
                    '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>' +
                    '<polyline points="14 2 14 8 20 8"/>' +
                    '<line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>' +
                    'Exportar XLS</button>' +
                    '<button class="fplms-icp-export-btn" id="fplms-icp-exp-pdf">' +
                    '<svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">' +
                    '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>' +
                    '<polyline points="14 2 14 8 20 8"/></svg>' +
                    'Exportar PDF</button>' +
                    '<button class="fplms-icp-export-btn" id="fplms-icp-desel" style="margin-left:auto;font-size:12px;color:#000000;">' +
                    '✕ Deseleccionar todo</button>' +
                    '</div>' +
                    // toolbar
                    '<div id="fplms-icp-toolbar">' +
                    '<div style="position:relative;flex:1 1 220px;min-width:160px;">' +
                    '<input id="fplms-icp-search" type="search" placeholder="Buscar curso..." autocomplete="off">' +
                    '<svg style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#aaa;pointer-events:none;" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>' +
                    '</div>' +
                    '<button class="fplms-icp-filter active" data-filter="all">Todos (' + courses.length + ')</button>' +
                    '<button class="fplms-icp-filter" data-filter="expiring">Por Vencer (' + expCount + ')</button>' +
                    '</div>' +
                    // tabla
                    '<div style="overflow-x:auto;">' +
                    '<table id="fplms-icp-table">' +
                    '<thead><tr>' +
                    '<th class="fplms-icp-th-chk"><input type="checkbox" class="fplms-icp-chk" id="fplms-icp-chk-all"></th>' +
                    '<th>Curso</th><th>Creación</th><th>Modificación</th>' +
                    '<th>Estructuras</th><th>Estudiantes</th><th>Acciones</th>' +
                    '</tr></thead>' +
                    '<tbody id="fplms-icp-tbody"></tbody>' +
                    '</table>' +
                    '</div>' +
                    '<div id="fplms-icp-bottom-bar">' +
                    '<div class="dt-length">' +
                    '<select id="fplms-icp-perpage" class="dt-input">' +
                    '<option value="5">5</option>' +
                    '<option value="10" selected>10</option>' +
                    '<option value="20">20</option>' +
                    '<option value="50">50</option>' +
                    '<option value="100">100</option>' +
                    '</select>' +
                    '<label for="fplms-icp-perpage"> paginas</label>' +
                    '</div>' +
                    '<div id="fplms-icp-pagination"></div>' +
                    '</div>' +
                    // modal estructuras
                    '<div id="fplms-struct-overlay">' +
                    '<div id="fplms-struct-modal">' +
                    '<h3 id="fplms-sm-title">Asignar Estructuras</h3>' +
                    '<p class="fplms-sm-sub" id="fplms-sm-sub"></p>' +
                    '<div id="fplms-sm-body"><div class="fplms-sm-loading">Cargando...</div></div>' +
                    '<p class="fplms-sm-msg" id="fplms-sm-msg"></p>' +
                    '<div class="fplms-sm-footer">' +
                    '<button class="fplms-sm-cancel" id="fplms-sm-cancel">Cancelar</button>' +
                    '<button class="fplms-sm-save"  id="fplms-sm-save">Guardar</button>' +
                    '</div>' +
                    '</div></div>';

                var tbody      = container.querySelector( '#fplms-icp-tbody' );
                var search     = container.querySelector( '#fplms-icp-search' );
                var filters    = container.querySelectorAll( '.fplms-icp-filter' );
                var chkAll     = container.querySelector( '#fplms-icp-chk-all' );
                var selBar     = container.querySelector( '#fplms-icp-sel-bar' );
                var selCount   = container.querySelector( '#fplms-icp-sel-count' );
                var btnExpXls  = container.querySelector( '#fplms-icp-exp-xls' );
                var btnExpPdf  = container.querySelector( '#fplms-icp-exp-pdf' );
                var btnDesel   = container.querySelector( '#fplms-icp-desel' );
                var perpageSel = container.querySelector( '#fplms-icp-perpage' );
                var activeFilter = 'all';
                var searchQuery  = '';
                var selectedIds  = [];   // IDs de cursos marcados

                // ── helpers de selección ──────────────────────────────────────────────
                function updateSelBar() {
                    selectedIds = [];
                    tbody.querySelectorAll( '.fplms-icp-chk:checked' ).forEach( function ( ch ) {
                        selectedIds.push( parseInt( ch.dataset.id ) );
                    } );
                    if ( selectedIds.length > 0 ) {
                        selBar.classList.add( 'visible' );
                        selCount.textContent = selectedIds.length + ' seleccionado' + ( selectedIds.length !== 1 ? 's' : '' );
                    } else {
                        selBar.classList.remove( 'visible' );
                    }
                    chkAll.indeterminate = false;
                    var total = tbody.querySelectorAll( '.fplms-icp-chk' ).length;
                    if ( selectedIds.length === 0 )      chkAll.checked = false;
                    else if ( selectedIds.length === total ) chkAll.checked = true;
                    else { chkAll.checked = false; chkAll.indeterminate = true; }
                }

                chkAll.addEventListener( 'change', function () {
                    tbody.querySelectorAll( '.fplms-icp-chk' ).forEach( function ( ch ) {
                        ch.checked = chkAll.checked;
                        ch.closest( 'tr' ).classList.toggle( 'fplms-icp-selected', chkAll.checked );
                    } );
                    updateSelBar();
                } );

                btnDesel.addEventListener( 'click', function () {
                    tbody.querySelectorAll( '.fplms-icp-chk' ).forEach( function ( ch ) {
                        ch.checked = false;
                        ch.closest( 'tr' ).classList.remove( 'fplms-icp-selected' );
                    } );
                    chkAll.checked = false;
                    updateSelBar();
                } );

                // ── Exportar XLS (tabla visible en selección) ─────────────────────────
                btnExpXls.addEventListener( 'click', function () {
                    var rows = getExportRows();
                    if ( ! rows.length ) return;
                    var csv = '\uFEFF' + // BOM UTF-8 para Excel
                        'Curso\tID\tCreación\tModificación\tCanales\tSucursales\tCargos\tEstudiantes\n' +
                        rows.map( function ( c ) {
                            return [
                                c.title, c.id, c.created, c.modified,
                                ( c.channels || [] ).join( ' | ' ),
                                ( c.branches || [] ).join( ' | ' ),
                                ( c.roles    || [] ).join( ' | ' ),
                                c.students || 0,
                            ].join( '\t' );
                        } ).join( '\n' );
                    downloadFile( csv, 'mis-cursos.xls', 'application/vnd.ms-excel' );
                } );

                // ── Exportar PDF (impresión vía ventana) ──────────────────────────────
                btnExpPdf.addEventListener( 'click', function () {
                    var rows = getExportRows();
                    if ( ! rows.length ) return;
                    var html =
                        '<!DOCTYPE html><html><head><meta charset="utf-8">' +
                        '<title>Mis Cursos</title>' +
                        '<style>body{font-family:sans-serif;font-size:12px;}' +
                        'h1{font-size:16px;margin-bottom:12px;}' +
                        'table{width:100%;border-collapse:collapse;}' +
                        'th,td{border:1px solid #ccc;padding:6px 8px;text-align:left;}' +
                        'th{background:#f5f5f5;font-weight:700;}</style></head><body>' +
                        '<h1>Mis Cursos</h1>' +
                        '<table><thead><tr>' +
                        '<th>Curso</th><th>ID</th><th>Creación</th><th>Modificación</th>' +
                        '<th>Estructuras</th><th>Estudiantes</th>' +
                        '</tr></thead><tbody>' +
                        rows.map( function ( c ) {
                            var allStructs = ( c.channels || [] ).concat( c.branches || [], c.roles || [] );
                            return '<tr><td>' + escH( c.title ) + '</td><td>' + c.id + '</td>' +
                                '<td>' + c.created + '</td><td>' + c.modified + '</td>' +
                                '<td>' + escH( allStructs.join( ', ' ) ) + '</td>' +
                                '<td>' + ( c.students || 0 ) + '</td></tr>';
                        } ).join( '' ) +
                        '</tbody></table></body></html>';
                    var w = window.open( '', '_blank', 'width=900,height=600' );
                    if ( w ) { w.document.write( html ); w.document.close(); w.print(); }
                } );

                function getExportRows() {
                    // Si hay selección → solo esos; si no, todos los visibles en la tabla
                    if ( selectedIds.length > 0 ) {
                        return courses.filter( function ( c ) { return selectedIds.indexOf( c.id ) !== -1; } );
                    }
                    var q = searchQuery.toLowerCase();
                    return courses.filter( function ( c ) {
                        if ( activeFilter === 'expiring' && ! c.is_expiring ) return false;
                        if ( q && c.title.toLowerCase().indexOf( q ) === -1 ) return false;
                        return true;
                    } );
                }

                function downloadFile( content, name, mime ) {
                    var blob = new Blob( [ content ], { type: mime + ';charset=utf-8;' } );
                    var url  = URL.createObjectURL( blob );
                    var a    = document.createElement( 'a' );
                    a.href = url; a.download = name; a.click();
                    setTimeout( function () { URL.revokeObjectURL( url ); }, 1000 );
                }

                function escH( s ) { return String( s ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ); }

                // ── Modal de estructuras ──────────────────────────────────────────────
                var overlay     = container.querySelector( '#fplms-struct-overlay' );
                var smTitle     = container.querySelector( '#fplms-sm-title' );
                var smSub       = container.querySelector( '#fplms-sm-sub' );
                var smBody      = container.querySelector( '#fplms-sm-body' );
                var smMsg       = container.querySelector( '#fplms-sm-msg' );
                var smSave      = container.querySelector( '#fplms-sm-save' );
                var smCancel    = container.querySelector( '#fplms-sm-cancel' );
                var modalCourseId = 0;

                smCancel.addEventListener( 'click', function () { overlay.classList.remove( 'open' ); } );
                overlay.addEventListener( 'click',  function ( e ) {
                    if ( e.target === overlay ) overlay.classList.remove( 'open' );
                } );

                function openStructModal( courseId, courseTitle ) {
                    modalCourseId = courseId;
                    smTitle.textContent = 'Asignar Estructuras';
                    smSub.textContent   = courseTitle;
                    smMsg.textContent   = '';
                    smMsg.className     = 'fplms-sm-msg';
                    smBody.innerHTML    = '<div class="fplms-sm-loading">Cargando estructuras...</div>';
                    smSave.disabled     = true;
                    overlay.classList.add( 'open' );

                    // Cargar estructuras del curso vía AJAX
                    var fd = new FormData();
                    fd.append( 'action',    'fplms_get_frontend_structures' );
                    fd.append( 'nonce',     STRUCT_NONCE );
                    fd.append( 'course_id', courseId );
                    fetch( AJAX_URL, { method: 'POST', body: fd } )
                        .then( function ( r ) { return r.json(); } )
                        .then( function ( res ) {
                            if ( ! res || ! res.success ) {
                                smBody.innerHTML = '<p style="color:#e74c3c;text-align:center;">' +
                                    escH( ( res && res.data ) ? res.data : 'Error al cargar.' ) + '</p>';
                                return;
                            }
                            renderModalBody( res.data );
                            smSave.disabled = false;
                        } )
                        .catch( function () {
                            smBody.innerHTML = '<p style="color:#e74c3c;text-align:center;">Error de red.</p>';
                        } );
                }

                function renderModalBody( d ) {
                    // Canales: solo lectura (informativos)
                    var channelHtml = '';
                    if ( d.channel_names && d.channel_names.length ) {
                        channelHtml =
                            '<div class="fplms-sm-section">' +
                            '<label class="fplms-sm-title">Canal</label>' +
                            '<div class="fplms-sm-tags">' +
                            d.channel_names.map( function ( n ) {
                                return '<span class="fplms-sm-tag selected" style="cursor:default;">' + escH( n ) + '</span>';
                            } ).join( '' ) +
                            '</div></div>';
                    } else {
                        channelHtml =
                            '<div class="fplms-sm-section">' +
                            '<label class="fplms-sm-title">Canal</label>' +
                            '<p style="font-size:12px;color:#e74c3c;">Sin canal asignado. Asigna una Categoría/Canal en el editor del curso.</p>' +
                            '</div>';
                    }

                    // Sucursales: toggleable
                    var branchHtml =
                        '<div class="fplms-sm-section" id="fplms-sm-branches">' +
                        '<label class="fplms-sm-title">Sucursal <span style="font-weight:400;text-transform:none;font-size:11px;color:#999;">(dejar vacío = todas)</span></label>' +
                        '<div class="fplms-sm-tags">';
                    var branches = d.branches || {};
                    var manualBranches = d.manual_branches || [];
                    Object.keys( branches ).forEach( function ( bid ) {
                        var sel = manualBranches.indexOf( parseInt( bid ) ) !== -1 ? ' selected' : '';
                        branchHtml += '<button type="button" class="fplms-sm-tag' + sel + '" data-bid="' + bid + '">' +
                            escH( branches[ bid ] ) + '</button>';
                    } );
                    if ( ! Object.keys( branches ).length ) {
                        branchHtml += '<span style="color:#aaa;font-size:12px;">Sin sucursales disponibles.</span>';
                    }
                    branchHtml += '</div></div>';

                    // Cargos: se cargan dinámicamente según sucursales seleccionadas
                    var roleHtml =
                        '<div class="fplms-sm-section" id="fplms-sm-roles">' +
                        '<label class="fplms-sm-title">Cargo <span style="font-weight:400;text-transform:none;font-size:11px;color:#999;">(dejar vacío = todos)</span></label>' +
                        '<div class="fplms-sm-tags" id="fplms-sm-roles-tags"><div class="fplms-sm-loading">Selecciona sucursales para ver cargos...</div></div>' +
                        '</div>';

                    smBody.innerHTML = channelHtml + branchHtml + roleHtml;

                    // Guardar data de roles actuales para re-pintar después de cargar
                    smBody._modalData = { roles: d.roles || {}, manualRoles: d.manual_roles || [] };

                    // Pintar roles iniciales basados en selección inicial de sucursales
                    refreshRoleOptions( manualBranches, d.roles || {}, d.manual_roles || [] );

                    // Eventos en botones de sucursal
                    smBody.querySelectorAll( '#fplms-sm-branches .fplms-sm-tag' ).forEach( function ( btn ) {
                        btn.addEventListener( 'click', function () {
                            btn.classList.toggle( 'selected' );
                            var selBranches = getSelectedBranches();
                            // Recargar cargos dinámicamente
                            loadRolesForBranches( selBranches );
                        } );
                    } );
                }

                function getSelectedBranches() {
                    var ids = [];
                    smBody.querySelectorAll( '#fplms-sm-branches .fplms-sm-tag.selected' ).forEach( function ( b ) {
                        ids.push( parseInt( b.dataset.bid ) );
                    } );
                    return ids;
                }

                function getSelectedRoles() {
                    var ids = [];
                    smBody.querySelectorAll( '#fplms-sm-roles .fplms-sm-tag.selected' ).forEach( function ( b ) {
                        ids.push( parseInt( b.dataset.rid ) );
                    } );
                    return ids;
                }

                function refreshRoleOptions( selectedBranchIds, rolesMap, selectedRoleIds ) {
                    var tagsEl = smBody.querySelector( '#fplms-sm-roles-tags' );
                    if ( ! tagsEl ) return;
                    tagsEl.innerHTML = '';
                    if ( ! Object.keys( rolesMap ).length ) {
                        tagsEl.innerHTML = '<span style="color:#aaa;font-size:12px;">' +
                            ( selectedBranchIds.length ? 'Sin cargos disponibles.' : 'Selecciona sucursales para ver cargos...' ) +
                            '</span>';
                        return;
                    }
                    Object.keys( rolesMap ).forEach( function ( rid ) {
                        var sel = selectedRoleIds.indexOf( parseInt( rid ) ) !== -1 ? ' selected' : '';
                        var btn = document.createElement( 'button' );
                        btn.type      = 'button';
                        btn.className = 'fplms-sm-tag' + sel;
                        btn.dataset.rid = rid;
                        btn.textContent = rolesMap[ rid ];
                        btn.addEventListener( 'click', function () { btn.classList.toggle( 'selected' ); } );
                        tagsEl.appendChild( btn );
                    } );
                }

                function loadRolesForBranches( branchIds ) {
                    var tagsEl = smBody.querySelector( '#fplms-sm-roles-tags' );
                    if ( ! tagsEl ) return;
                    if ( ! branchIds.length ) {
                        refreshRoleOptions( [], {}, [] );
                        return;
                    }
                    tagsEl.innerHTML = '<div class="fplms-sm-loading">Cargando cargos...</div>';
                    var fd = new FormData();
                    fd.append( 'action', 'fplms_get_branch_roles' );
                    fd.append( 'nonce',  STRUCT_NONCE );
                    branchIds.forEach( function ( id ) { fd.append( 'branch_ids[]', id ); } );
                    fetch( AJAX_URL, { method: 'POST', body: fd } )
                        .then( function ( r ) { return r.json(); } )
                        .then( function ( res ) {
                            refreshRoleOptions( branchIds, ( res && res.success && res.data ) ? res.data : {}, [] );
                        } )
                        .catch( function () {
                            tagsEl.innerHTML = '<span style="color:#e74c3c;font-size:12px;">Error al cargar cargos.</span>';
                        } );
                }

                smSave.addEventListener( 'click', function () {
                    smSave.disabled = true;
                    smMsg.textContent = 'Guardando...';
                    smMsg.className   = 'fplms-sm-msg';
                    var branchIds = getSelectedBranches();
                    var roleIds   = getSelectedRoles();
                    var fd = new FormData();
                    fd.append( 'action',    'fplms_save_frontend_structures' );
                    fd.append( 'nonce',     STRUCT_NONCE );
                    fd.append( 'course_id', modalCourseId );
                    branchIds.forEach( function ( id ) { fd.append( 'branch_ids[]', id ); } );
                    roleIds.forEach(   function ( id ) { fd.append( 'role_ids[]',   id ); } );
                    fetch( AJAX_URL, { method: 'POST', body: fd } )
                        .then( function ( r ) { return r.json(); } )
                        .then( function ( res ) {
                            smSave.disabled = false;
                            if ( res && res.success ) {
                                smMsg.textContent = '✓ ' + ( res.data && res.data.message ? res.data.message : 'Guardado correctamente.' );
                                smMsg.className   = 'fplms-sm-msg ok';
                                // Actualizar los tags de la fila en la tabla
                                updateCourseStructTags( modalCourseId, branchIds, roleIds );
                                setTimeout( function () { overlay.classList.remove( 'open' ); }, 1800 );
                            } else {
                                smMsg.textContent = '✗ ' + ( res && res.data ? res.data : 'Error al guardar.' );
                                smMsg.className   = 'fplms-sm-msg err';
                            }
                        } )
                        .catch( function () {
                            smSave.disabled   = false;
                            smMsg.textContent = '✗ Error de red.';
                            smMsg.className   = 'fplms-sm-msg err';
                        } );
                } );

                // Actualizar tags de estructura en la fila de la tabla tras guardar
                function updateCourseStructTags( courseId, branchIds, roleIds ) {
                    var row = tbody.querySelector( 'tr[data-id="' + courseId + '"]' );
                    if ( ! row ) return;
                    var td = row.querySelector( 'td[data-label="Estructuras"]' );
                    if ( ! td ) return;
                    // Reconstruir nombres desde los IDs en caché local de courses[]
                    var c = courses.filter( function ( x ) { return x.id === courseId; } )[0];
                    if ( c ) {
                        // Marcar el curso para re-consulta en el próximo load
                        c._struct_updated = true;
                    }
                    // Mensaje simple hasta próxima carga
                    td.innerHTML = '<span class="fplms-icp-tag" style="background:#e8f5e9;color:#27ae60;">Actualizado ✓</span>';
                }

                // ── Render de filas ───────────────────────────────────────────────────
                function renderRow( c ) {
                    var channels = c.channels || [];
                    var branches = c.branches || [];
                    var roles    = c.roles    || [];
                    var all      = channels.concat( branches ).concat( roles );
                    var structs_html = '';
                    var max = 3;
                    var shown = 0;
                    for ( var si = 0; si < channels.length && shown < max; si++, shown++ ) {
                        structs_html += '<span class="fplms-icp-tag" style="background:#e8f0fe;color:#3557c2;">' + escH( channels[si] ) + '</span>';
                    }
                    for ( var si = 0; si < branches.length && shown < max; si++, shown++ ) {
                        structs_html += '<span class="fplms-icp-tag" style="background:#e8f5e9;color:#2a7a45;">' + escH( branches[si] ) + '</span>';
                    }
                    for ( var si = 0; si < roles.length && shown < max; si++, shown++ ) {
                        structs_html += '<span class="fplms-icp-tag" style="background:#fff3e0;color:#b45309;">' + escH( roles[si] ) + '</span>';
                    }
                    var remaining = all.length - shown;
                    if ( remaining > 0 ) {
                        structs_html += '<span class="fplms-icp-tag">+' + remaining + ' más</span>';
                    }
                    if ( ! structs_html ) {
                        structs_html = '<span style="color:#bbb;font-size:12px;">—</span>';
                    }

                    // Botón Editar (lápiz) → edit_url
                    var edit_btn = c.edit_url
                        ? '<a href="' + c.edit_url + '" class="fplms-icp-btn" title="Editar curso">' +
                          '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"' +
                          ' fill="none" stroke="#555" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"' +
                          ' style="display:block;flex-shrink:0;">' +
                          '<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>' +
                          '<path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>'
                        : '';

                    // Botón Estructuras (árbol)
                    var struct_btn =
                        '<button type="button" class="fplms-icp-btn fplms-icp-struct-btn" ' +
                        'title="Asignar estructuras" data-id="' + c.id + '" data-title="' + escH( c.title ) + '">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"' +
                        ' fill="none" stroke="#555" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"' +
                        ' style="display:block;flex-shrink:0;">' +
                        '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></button>';

                    var tr = document.createElement( 'tr' );
                    tr.dataset.id  = c.id;
                    tr.dataset.exp = c.is_expiring ? '1' : '0';
                    tr.innerHTML =
                        '<td class="fplms-icp-td-chk"><input type="checkbox" class="fplms-icp-chk" data-id="' + c.id + '"></td>' +
                        '<td data-label="Curso"><span class="fplms-icp-title">' +
                        '<a href="' + ( c.view_url || '#' ) + '" target="_blank">' + escH( c.title ) + '</a></span>' +
                        '<span class="fplms-icp-id">ID: ' + c.id + '</span></td>' +
                        '<td data-label="Creación">'      + c.created  + '</td>' +
                        '<td data-label="Modificación">'  + c.modified + '</td>' +
                        '<td data-label="Estructuras">'   + structs_html + '</td>' +
                        '<td data-label="Estudiantes" style="text-align:center;">' + ( c.students || 0 ) + '</td>' +
                        '<td data-label="Acciones"><div class="fplms-icp-actions">' + edit_btn + struct_btn + '</div></td>';
                    return tr;
                }

                var perPage     = 10;
                var currentPage = 1;

                function applyFilters() {
                    chkAll.checked = false;
                    selBar.classList.remove( 'visible' );

                    var q = searchQuery.toLowerCase();
                    var filtered = courses.filter( function ( c ) {
                        if ( activeFilter === 'expiring' && ! c.is_expiring ) return false;
                        if ( q && c.title.toLowerCase().indexOf( q ) === -1 ) return false;
                        return true;
                    } );

                    var totalPages = Math.max( 1, Math.ceil( filtered.length / perPage ) );
                    if ( currentPage > totalPages ) currentPage = 1;
                    var start   = ( currentPage - 1 ) * perPage;
                    var pageItems = filtered.slice( start, start + perPage );

                    tbody.innerHTML = '';
                    if ( pageItems.length === 0 ) {
                        var tr = document.createElement( 'tr' );
                        tr.innerHTML = '<td colspan="7" class="fplms-icp-empty">No se encontraron cursos.</td>';
                        tbody.appendChild( tr );
                    } else {
                        pageItems.forEach( function ( c ) { tbody.appendChild( renderRow( c ) ); } );
                    }

                    tbody.querySelectorAll( '.fplms-icp-chk' ).forEach( function ( ch ) {
                        ch.addEventListener( 'change', function () {
                            ch.closest( 'tr' ).classList.toggle( 'fplms-icp-selected', ch.checked );
                            updateSelBar();
                        } );
                    } );
                    tbody.querySelectorAll( '.fplms-icp-struct-btn' ).forEach( function ( btn ) {
                        btn.addEventListener( 'click', function () {
                            openStructModal( parseInt( btn.dataset.id ), btn.dataset.title );
                        } );
                    } );

                    renderPagination( filtered.length, totalPages );
                }

                function renderPagination( total, totalPages ) {
                    var pg = document.getElementById( 'fplms-icp-pagination' );
                    if ( ! pg ) return;
                    pg.innerHTML = '';
                    if ( totalPages <= 1 && total === 0 ) return;

                    var info = document.createElement( 'span' );
                    info.style.cssText = 'font-size:12px;color:#888;';
                    var start = ( currentPage - 1 ) * perPage + 1;
                    var end   = Math.min( currentPage * perPage, total );
                    info.textContent = start + '–' + end + ' de ' + total;
                    pg.appendChild( info );

                    var btnPrev = document.createElement( 'button' );
                    btnPrev.textContent = '←';
                    btnPrev.className = 'fplms-icp-pg-btn';
                    btnPrev.disabled = currentPage === 1;
                    btnPrev.addEventListener( 'click', function () { currentPage--; applyFilters(); } );
                    pg.appendChild( btnPrev );

                    for ( var p = 1; p <= totalPages; p++ ) {
                        (function( page ) {
                            var btn = document.createElement( 'button' );
                            btn.textContent = page;
                            btn.className = 'fplms-icp-pg-btn' + ( page === currentPage ? ' active' : '' );
                            btn.addEventListener( 'click', function () { currentPage = page; applyFilters(); } );
                            pg.appendChild( btn );
                        })( p );
                    }

                    var btnNext = document.createElement( 'button' );
                    btnNext.textContent = '→';
                    btnNext.className = 'fplms-icp-pg-btn';
                    btnNext.disabled = currentPage === totalPages;
                    btnNext.addEventListener( 'click', function () { currentPage++; applyFilters(); } );
                    pg.appendChild( btnNext );
                }

                filters.forEach( function ( btn ) {
                    btn.addEventListener( 'click', function () {
                        filters.forEach( function ( b ) { b.classList.remove( 'active' ); } );
                        btn.classList.add( 'active' );
                        activeFilter = btn.dataset.filter;
                        currentPage = 1;
                        applyFilters();
                    } );
                } );

                search.addEventListener( 'input', function () {
                    searchQuery = search.value.trim();
                    currentPage = 1;
                    applyFilters();
                } );

                if ( perpageSel ) {
                    perpageSel.addEventListener( 'change', function () {
                        perPage = parseInt( perpageSel.value );
                        currentPage = 1;
                        applyFilters();
                    } );
                }

                applyFilters();
            }

            /**
             * Muestra la página "Mis Cursos" (oculta el contenido Vue del instructor).
             */
            function showMisCursosPage() {
                // Construir la página si no existe aún
                if ( ! document.getElementById( 'fplms-mis-cursos-page' ) && instrCoursesData ) {
                    var vuePage = document.querySelector( '.masterstudy-analytics-short-report-page' );
                    if ( vuePage && vuePage.parentNode ) {
                        var container = document.createElement( 'div' );
                        container.id  = 'fplms-mis-cursos-page';
                        container.style.cssText = 'display:none;';
                        vuePage.parentNode.insertBefore( container, vuePage.nextSibling );
                        buildInstructorCoursePanel( container, instrCoursesData.courses_list || [] );
                    }
                }
                var vuePage   = document.querySelector( '.masterstudy-analytics-short-report-page' );
                var statsWrap = document.querySelector( '.masterstudy-analytics-short-report-page-stats__wrapper' );
                var miCursosP = document.getElementById( 'fplms-mis-cursos-page' );
                if ( vuePage )   vuePage.style.display   = 'none';
                if ( statsWrap ) statsWrap.style.display = 'none';
                if ( miCursosP ) miCursosP.style.display = '';

                // Estado activo en sidebar
                document.querySelectorAll( '.masterstudy-account-menu__list-item' ).forEach( function ( el ) {
                    el.classList.remove( 'masterstudy-account-menu__list-item_active' );
                } );
                var nav = document.getElementById( 'fplms-mis-cursos-nav' );
                if ( nav ) nav.classList.add( 'masterstudy-account-menu__list-item_active' );
            }

            /**
             * Oculta la página "Mis Cursos" y restaura el contenido Vue del instructor.
             */
            function hideMisCursosPage() {
                var miCursosP = document.getElementById( 'fplms-mis-cursos-page' );
                var vuePage   = document.querySelector( '.masterstudy-analytics-short-report-page' );
                var statsWrap = document.querySelector( '.masterstudy-analytics-short-report-page-stats__wrapper' );
                if ( miCursosP ) miCursosP.style.display = 'none';
                if ( vuePage )   vuePage.style.display   = '';
                if ( statsWrap ) statsWrap.style.display = '';
                var nav = document.getElementById( 'fplms-mis-cursos-nav' );
                if ( nav ) nav.classList.remove( 'masterstudy-account-menu__list-item_active' );
            }

            /**
             * Inyecta el ítem "Mis Cursos" en el menú lateral del instructor
             * justo después de "Curso Nuevo".
             */
            function injectMisCursosSidebarLink() {
                function tryBuild() {
                    if ( document.getElementById( 'fplms-mis-cursos-nav' ) ) return true;
                    // Buscar el enlace "Curso Nuevo" (edit-course)
                    var items  = document.querySelectorAll( '.masterstudy-account-menu__list-item' );
                    var anchor = null;
                    items.forEach( function ( a ) {
                        if ( a.href && a.href.indexOf( 'edit-course' ) !== -1 ) anchor = a;
                    } );
                    if ( ! anchor ) return false;

                    var link = document.createElement( 'a' );
                    link.id        = 'fplms-mis-cursos-nav';
                    link.className = 'masterstudy-account-menu__list-item';
                    link.href      = '#';
                    link.setAttribute( 'data-menu-place', 'main' );
                    link.setAttribute( 'data-menu-mode',  'on'   );
                    link.innerHTML =
                        '<i class="stmlms-menu-assignments"></i>' +
                        '<span class="masterstudy-account-menu__list-item-label">Mis Cursos</span>';

                    anchor.parentNode.insertBefore( link, anchor.nextSibling );

                    // Clic en "Mis Cursos" → mostrar página personalizada
                    link.addEventListener( 'click', function ( e ) {
                        e.preventDefault();
                        e.stopPropagation();
                        showMisCursosPage();
                    } );

                    // Clic en cualquier otro ítem del menú → restaurar vista Vue
                    var menu = document.querySelector( '.masterstudy-account-menu' );
                    if ( menu ) {
                        menu.addEventListener( 'click', function ( e ) {
                            var item = e.target.closest( '.masterstudy-account-menu__list-item' );
                            if ( item && item.id !== 'fplms-mis-cursos-nav' ) {
                                hideMisCursosPage();
                            }
                        } );
                    }

                    return true;
                }

                if ( ! tryBuild() ) {
                    var obs = new MutationObserver( function () {
                        if ( tryBuild() ) obs.disconnect();
                    } );
                    obs.observe( document.body, { childList: true, subtree: true } );
                    setTimeout( function () { obs.disconnect(); }, 15000 );
                }
            }

            /* ── Helpers de bloque ───────────────────────────────────────────── */

            function mkStudentBlock( iconMod, title, value ) {
                return '<div class="masterstudy-enrolled-courses-sorting__block-wrapper">' +
                    '<div class="masterstudy-enrolled-courses-sorting__block">' +
                    '<div class="masterstudy-enrolled-courses-sorting__block-icon ' +
                    'masterstudy-enrolled-courses-sorting__block-icon_' + iconMod + '"></div>' +
                    '<div class="masterstudy-enrolled-courses-sorting__block-content">' +
                    '<span class="masterstudy-enrolled-courses-sorting__block-title">' + title + '</span>' +
                    '<span class="masterstudy-enrolled-courses-sorting__block-value">' + value + '</span>' +
                    '</div></div></div>';
            }

            function mkInstructorBlock( mod, title, value ) {
                return '<div class="masterstudy-analytics-short-report-page-stats__block">' +
                    '<div class="masterstudy-stats-block masterstudy-stats-block_' + mod + '">' +
                    '<span class="masterstudy-stats-block__icon"></span>' +
                    '<div class="masterstudy-stats-block__content">' +
                    '<div class="masterstudy-stats-block__title">' + title + '</div>' +
                    '<div class="masterstudy-stats-block__value">' + value + '</div>' +
                    '</div></div></div>';
            }

            /* ── Render student ──────────────────────────────────────────────── */

            function renderStudent( el, data ) {
                var avg  = (data.avg_progress || 0) + '%';
                var hrs  = (data.hours || 0) + ' h';
                var html = mkStudentBlock( 'courses',      'Cursos Inscritos',   data.enrolled      || 0 )
                         + mkStudentBlock( 'groups',       'Avance Promedio',    avg                    )
                         + mkStudentBlock( 'courses',      'Cursos Completados', data.completed     || 0 )
                         + mkStudentBlock( 'certificates', 'Certificados',       data.certificates  || 0 )
                         + mkStudentBlock( 'groups',       'Horas de Formación', hrs                    );
                el.innerHTML = html;
                renderedStudent = true;
                if ( ! searchInjected ) {
                    searchInjected = true;
                    injectSearchBar( el, {
                        wrapperId:     'fplms-course-search-wrapper',
                        inputId:       'fplms-course-search',
                        noResultsId:   'fplms-no-results',
                        placeholder:   'Buscar cursos inscritos...',
                        cardSelectors: '.masterstudy-course-card, .masterstudy-enrolled-courses-list__item, .masterstudy-enrolled-courses__item',
                        scopeSelector: '.masterstudy-enrolled-courses'
                    } );
                }
                injectStudentTabs( data );
            }

            /* ── Render instructor ───────────────────────────────────────────── */

            function renderInstructor( el, data ) {
                instrCoursesData = data;
                var avgP = (data.avg_student_progress || 0) + '%';
                var html = mkInstructorBlock( 'courses',              'Cursos Creados',       data.created_courses    || 0 )
                         + mkInstructorBlock( 'orders',               'Cursos por Vencer',    data.expiring_courses   || 0 )
                         + mkInstructorBlock( 'students',             'Estudiantes Inscritos', data.total_students    || 0 )
                         + mkInstructorBlock( 'enrollments',          'Avance Promedio',       avgP                       )
                         + mkInstructorBlock( 'certificates_created', 'Certificados Emitidos', data.total_certificates || 0 );
                el.innerHTML = html;
                renderedInstructor = true;
                injectMisCursosSidebarLink();
            }

            /* ── Fetch and render ────────────────────────────────────────────── */

            function fetchStats( type, callback ) {
                var fd = new FormData();
                fd.append( 'action', 'fplms_dashboard_stats' );
                fd.append( 'nonce',  NONCE );
                fd.append( 'type',   type );
                fetch( AJAX_URL, { method: 'POST', body: fd } )
                    .then( function ( r ) { return r.json(); } )
                    .then( function ( res ) { if ( res && res.success ) callback( res.data ); } )
                    .catch( function () {} );
            }

            function tryRender() {
                var studentEl    = document.querySelector( '.masterstudy-enrolled-courses-sorting' );
                var instructorEl = document.querySelector( '.masterstudy-analytics-short-report-page-stats__wrapper' );

                if ( studentEl && ! renderedStudent ) {
                    renderedStudent = true; // bloquear doble fetch
                    fetchStats( 'student', function ( data ) {
                        if ( data ) {
                            renderStudent( studentEl, data );
                        } else {
                            renderedStudent = false; // permitir reintento si AJAX devuelve vacío
                        }
                    } );
                }

                if ( instructorEl && ! renderedInstructor ) {
                    renderedInstructor = true; // bloquear doble fetch
                    fetchStats( 'instructor', function ( data ) {
                        if ( data ) {
                            renderInstructor( instructorEl, data );
                        } else {
                            renderedInstructor = false; // permitir reintento si AJAX devuelve vacío
                        }
                    } );
                }
            }

            /* ── Init ────────────────────────────────────────────────────────── */

            function init() {
                tryRender();
                var checkTimer;
                var observer = new MutationObserver( function () {
                    clearTimeout( checkTimer );
                    checkTimer = setTimeout( function () {
                        if ( renderedStudent && renderedInstructor ) {
                            observer.disconnect();
                            return;
                        }
                        tryRender();
                    }, 200 );
                } );
                observer.observe( document.body, { childList: true, subtree: true } );

                // Safety: disconnect after 30 s
                setTimeout( function () { observer.disconnect(); }, 30000 );
            }

            if ( document.readyState === 'loading' ) {
                document.addEventListener( 'DOMContentLoaded', init );
            } else {
                init();
            }
        })();
        </script>
        <?php
    }
}

