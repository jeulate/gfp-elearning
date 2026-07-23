<?php
/**
 * Plugin Name: FairPlay LMS – MasterStudy Extensions
 * Plugin URI:  https://www.linkedin.com/in/jaeulate/
 * Description: Extensiones del panel admin, estructuras, usuarios y cursos para la plataforma eLearning con MasterStudy.
 * Version:     1.9.9
 * Author:      Insoftline / Juan Eulate
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FPLMS_PLUGIN_FILE', __FILE__ );
define( 'FPLMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FPLMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Evita que el Gradebook de MasterStudy consulte sus endpoints con course_id vacío.
 *
 * El script de Fairplay se registra como dependencia del controlador original para
 * poder interceptar el evento del selector antes de que MasterStudy inicialice las
 * peticiones AJAX.
 */
function fplms_enqueue_gradebook_empty_course_guard() {
    if ( ! is_user_logged_in() ) {
        return;
    }

    $request_uri  = isset( $_SERVER['REQUEST_URI'] )
        ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
        : '';
    $request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );

    if ( ! preg_match( '#/user-account/gradebook/?$#', $request_path ) ) {
        return;
    }

    $script_handle = 'fplms-gradebook-empty-course';
    $script_path   = 'assets/js/gradebook-empty-course.js';
    $script_file   = FPLMS_PLUGIN_DIR . $script_path;

    if ( ! file_exists( $script_file ) ) {
        return;
    }

    wp_register_script(
        $script_handle,
        FPLMS_PLUGIN_URL . $script_path,
        [],
        (string) filemtime( $script_file ),
        true
    );

    global $wp_scripts;

    if (
        $wp_scripts instanceof WP_Scripts &&
        isset( $wp_scripts->registered['masterstudy-account-gradebook'] )
    ) {
        $dependencies = $wp_scripts->registered['masterstudy-account-gradebook']->deps;

        if ( ! in_array( $script_handle, $dependencies, true ) ) {
            $dependencies[] = $script_handle;
            $wp_scripts->registered['masterstudy-account-gradebook']->deps = $dependencies;
        }

        return;
    }

    // Respaldo para cambios futuros en el registro de scripts de MasterStudy.
    wp_enqueue_script( $script_handle );
}
add_action( 'wp_enqueue_scripts', 'fplms_enqueue_gradebook_empty_course_guard', 999 );

/**
 * Normaliza números con coma decimal en respuestas REST de MasterStudy.
 * Evita errores JS como: data.current.toFixed is not a function
 */
function fplms_normalize_masterstudy_rest_numbers( $response, $server, $request ) {

    // Asegurar que sea una respuesta REST válida
    if ( ! $response instanceof WP_REST_Response ) {
        return $response;
    }

    $route = $request->get_route();

    // Solo aplicar a endpoints de MasterStudy
    if ( strpos( $route, '/stm-lms/' ) === false && strpos( $route, '/masterstudy-lms/' ) === false ) {
        return $response;
    }

    $data = $response->get_data();

    if ( empty( $data ) ) {
        return $response;
    }

    $normalize_numbers = function ( &$item ) use ( &$normalize_numbers ) {
        if ( is_array( $item ) ) {
            foreach ( $item as &$value ) {
                $normalize_numbers( $value );
            }
            return;
        }

        if ( is_object( $item ) ) {
            foreach ( $item as &$value ) {
                $normalize_numbers( $value );
            }
            return;
        }

        if ( is_string( $item ) ) {
            $value = trim( $item );

            // Solo convertir strings numéricos tipo 4,5 o 80,00
            if ( preg_match( '/^-?\d+(,\d+)?$/', $value ) ) {
                $normalized = str_replace( ',', '.', $value );

                if ( is_numeric( $normalized ) ) {
                    $item = (float) $normalized;
                }
            }
        }
    };

    $normalize_numbers( $data );
    $response->set_data( $data );

    return $response;
}
add_filter( 'rest_post_dispatch', 'fplms_normalize_masterstudy_rest_numbers', 10, 3 );

// Includes
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-config.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-capabilities.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-progress.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-structures.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-users.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-courses.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-course-visibility.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-course-display.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-reports.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-audit-logger.php';
require_once FPLMS_PLUGIN_DIR . 'admin/class-fplms-audit-admin.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-onboarding.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-quiz-settings.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-quiz-availability.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-quiz-weights.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-survey.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-admin-pages.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-admin-menu.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-nav-menu.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-plugin.php';

// Activación / desactivación
register_activation_hook(
    __FILE__,
    [ 'FairPlay_LMS_Capabilities', 'activate' ]
);

register_deactivation_hook(
    __FILE__,
    [ 'FairPlay_LMS_Capabilities', 'deactivate' ]
);

// Al activar el plugin, crear las tablas necesarias
register_activation_hook(__FILE__, 'fplms_create_user_logins_table');
function fplms_create_user_logins_table() {
    if ( ! function_exists('dbDelta') ) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Tabla de logins
    $table_logins = $wpdb->prefix . 'fplms_user_logins';
    $table_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $table_logins
    ));
    
    if ($table_exists !== $table_logins) {
        $sql = "CREATE TABLE $table_logins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            login_time DATETIME NOT NULL,
            INDEX (user_id),
            INDEX (login_time)
        ) $charset_collate;";
        dbDelta($sql);
    }

    // Tabla de actividad
    $table_activity = $wpdb->prefix . 'fplms_user_activity';
    $table_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $table_activity
    ));
    
    if ($table_exists !== $table_activity) {
        $sql = "CREATE TABLE $table_activity (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            activity_time DATETIME NOT NULL,
            page_url VARCHAR(500),
            INDEX (user_id),
            INDEX (activity_time)
        ) $charset_collate;";
        dbDelta($sql);
    }
    
    // Tabla de auditoría
    require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-audit-logger.php';
    $audit_logger = new FairPlay_LMS_Audit_Logger();
    $audit_logger->create_table();

    // Tabla de encuestas de satisfacción
    require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-survey.php';
    $survey = new FairPlay_LMS_Survey();
    $survey->create_table();
}

// Bootstrap del plugin
function fairplay_lms_extensions_bootstrap() {
    global $fairplay_lms_plugin;
    $fairplay_lms_plugin = new FairPlay_LMS_Plugin();
}
add_action( 'plugins_loaded', 'fairplay_lms_extensions_bootstrap' );

add_filter('gettext', 'fplms_custom_login_translations', 20, 3);

function fplms_custom_login_translations($translated, $text, $domain) {

    $translations = [
        'Send reset link' => 'Enviar enlace de recuperación',
        'Send Reset Link' => 'Enviar enlace de recuperación',
        'Password reset link sent' => 'Enlace de recuperación enviado',
        'Forgot Password' => 'Olvidé mi contraseña',
        'Reset Password' => 'Restablecer contraseña',
        'Remember me' => 'Recordarme',
        'Login' => 'Iniciar sesión',
        'Register' => 'Registrarse',
        'True-False' => 'Verdadero/Falso',
        'True/False' => 'Verdadero/Falso',
        'Matching' => 'Coincidencia',
        'Image matching' => 'Coincidencia de imágenes',
        'Image Matching' => 'Coincidencia de imágenes',
    ];

    if (isset($translations[$text])) {
        return $translations[$text];
    }

    return $translated;
}

//add_action( 'wp_enqueue_scripts', 'fplms_enqueue_masterstudy_builder_custom_styles', 999 );
//add_action( 'wp_head', 'fplms_masterstudy_builder_inline_style_fallback', 999 );
//add_action( 'wp_footer', 'fplms_masterstudy_builder_js_width_fallback', 999 );

function fplms_is_masterstudy_builder_request() {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';

    return (
        // En MasterStudy, muchas veces la SPA arranca en /user-account/
        // y luego navega internamente a /edit-course/... sin recarga completa.
        // Si no permitimos /user-account/, los scripts nunca llegan al HTML inicial.
        strpos( $request_uri, '/user-account/edit-course/' ) !== false ||
        strpos( $request_uri, '/edit-quiz/' ) !== false ||
        strpos( $request_uri, '/curriculum/' ) !== false ||
        strpos( $request_uri, '/quiz/' ) !== false
    );
}

function fplms_enqueue_masterstudy_builder_custom_styles() {
    if ( ! fplms_is_masterstudy_builder_request() ) {
        return;
    }

    $css_rel_path = 'assets/css/masterstudy-builder-custom.css';
    $css_abs_path = FPLMS_PLUGIN_DIR . $css_rel_path;
    $css_version  = file_exists( $css_abs_path ) ? (string) filemtime( $css_abs_path ) : '1.0.1';

    $translation_js_rel_path = 'assets/js/quiz-type-translation.js';
    $translation_js_abs_path = FPLMS_PLUGIN_DIR . $translation_js_rel_path;
    if ( ! file_exists( $translation_js_abs_path ) ) {
        $translation_js_rel_path = 'assets/js/quiz-type-translations.js';
        $translation_js_abs_path = FPLMS_PLUGIN_DIR . $translation_js_rel_path;
    }
    $translation_js_version = file_exists( $translation_js_abs_path )
        ? ( (string) filemtime( $translation_js_abs_path ) . '-' . substr( md5_file( $translation_js_abs_path ), 0, 8 ) )
        : '1.0.0';

    $button_fix_js_rel_path = 'assets/js/masterstudy-builder-button-fix.js';
    $button_fix_js_abs_path = FPLMS_PLUGIN_DIR . $button_fix_js_rel_path;
    $button_fix_js_version  = file_exists( $button_fix_js_abs_path )
        ? ( (string) filemtime( $button_fix_js_abs_path ) . '-' . substr( md5_file( $button_fix_js_abs_path ), 0, 8 ) )
        : '1.0.0';

    wp_enqueue_style(
        'fplms-masterstudy-builder-custom',
        FPLMS_PLUGIN_URL . $css_rel_path,
        [],
        $css_version
    );

    if ( file_exists( $translation_js_abs_path ) ) {
        wp_enqueue_script(
            'fplms-quiz-type-translations',
            FPLMS_PLUGIN_URL . $translation_js_rel_path,
            [],
            $translation_js_version,
            true
        );
    }

    if ( file_exists( $button_fix_js_abs_path ) ) {
        wp_enqueue_script(
            'fplms-masterstudy-builder-button-fix',
            FPLMS_PLUGIN_URL . $button_fix_js_rel_path,
            [],
            $button_fix_js_version,
            true
        );
    }
}

function fplms_masterstudy_builder_inline_style_fallback() {
    if ( ! fplms_is_masterstudy_builder_request() ) {
        return;
    }

    echo '<style id="fplms-masterstudy-builder-inline-fallback">'
        . '.chakra-j6rous,button.chakra-button.chakra-j6rous,button.chakra-button.chakra-j6rous[disabled],button.chakra-button.chakra-j6rous[aria-disabled="true"]{width:52px!important;min-width:52px!important;max-width:52px!important;flex:0 0 52px!important;}'
        . 'button.chakra-button.chakra-j6rous>div{width:100%!important;justify-content:center!important;}'
        . 'button.chakra-button.chakra-j6rous p{display:inline-block!important;}'
        . 'div[role="group"] input[placeholder*="nueva respuesta"]+button.chakra-button,div[role="group"] input[placeholder*="new answer"]+button.chakra-button,div[role="group"] input[placeholder*="respuesta"]+button.chakra-button{width:52px!important;min-width:52px!important;max-width:52px!important;flex:0 0 52px!important;}'
        . 'div[role="group"] input[placeholder*="nueva respuesta"]+button.chakra-button>div,div[role="group"] input[placeholder*="new answer"]+button.chakra-button>div,div[role="group"] input[placeholder*="respuesta"]+button.chakra-button>div{width:100%!important;justify-content:center!important;}'
        . '</style>';
}

function fplms_masterstudy_builder_js_width_fallback() {
    if ( ! fplms_is_masterstudy_builder_request() ) {
        return;
    }

    ?>
    <script id="fplms-masterstudy-builder-js-fallback">
    /* Fallback inline — refuerza el fix del boton en caso de que el script externo
       no haya cargado todavia. Comparte la misma logica SPA-aware. */
    (function () {
        function isBuilderPath() {
            var p = window.location ? window.location.pathname : '';
            return /\/user-account\/edit-course\/\d+/.test(p) ||
                   /\/edit-quiz\/\d+/.test(p) ||
                   /\/edit-course\/\d+/.test(p) ||
                   /\/curriculum\//.test(p) ||
                   /\/quiz\/\d+/.test(p);
        }

        function applyButtonFix(button) {
            if (!button) { return; }
            button.style.setProperty('width',     '52px',     'important');
            button.style.setProperty('min-width', '52px',     'important');
            button.style.setProperty('max-width', '52px',     'important');
            button.style.setProperty('flex',      '0 0 52px', 'important');
            var inner = button.querySelector(':scope > div');
            if (inner) {
                inner.style.setProperty('width',           '100%',   'important');
                inner.style.setProperty('justify-content', 'center', 'important');
            }
        }

        function findTargetButtons() {
            var seen = new Set();
            [
                'button.chakra-button.chakra-j6rous',
                'button.chakra-button[class*="j6r"]',
                'div[role="group"] input[placeholder*="respuesta"] + button.chakra-button',
                'div[role="group"] input[placeholder*="answer"] + button.chakra-button'
            ].forEach(function (sel) {
                try { document.querySelectorAll(sel).forEach(function (n) { seen.add(n); }); } catch (e) {}
            });
            return seen;
        }

        function runFix() {
            if (!isBuilderPath()) { return; }
            findTargetButtons().forEach(applyButtonFix);
        }

        runFix();

        var _mt = null;
        new MutationObserver(function () {
            clearTimeout(_mt);
            _mt = setTimeout(runFix, 60);
        }).observe(document.body, { childList: true, subtree: true, attributes: true,
            attributeFilter: ['class', 'style', 'disabled', 'aria-disabled'] });

        var _lp = window.location ? window.location.pathname : '';
        setInterval(function () {
            var cp = window.location ? window.location.pathname : '';
            if (cp !== _lp) { _lp = cp; runFix(); }
        }, 200);
    })();
    </script>
    <?php
}


add_action( 'template_redirect', 'fplms_start_builder_output_buffer', 0 );

function fplms_start_builder_output_buffer() {
    if ( ! fplms_is_masterstudy_builder_request() ) {
        return;
    }

    ob_start( 'fplms_inject_builder_assets_into_html' );
}

function fplms_inject_builder_assets_into_html( $html ) {
    if ( stripos( $html, '</head>' ) === false ) {
        return $html;
    }

    $css_url = FPLMS_PLUGIN_URL . 'assets/css/masterstudy-builder-custom.css?v=' . time();
    $btn_js  = FPLMS_PLUGIN_URL . 'assets/js/masterstudy-builder-button-fix.js?v=' . time();
    $tr_js   = FPLMS_PLUGIN_URL . 'assets/js/quiz-type-translation.js?v=' . time();

    $inject = "\n"
        . '<link rel="stylesheet" id="fplms-builder-custom-css" href="' . esc_url( $css_url ) . '" media="all">' . "\n"
        . '<script id="fplms-builder-button-fix-js" src="' . esc_url( $btn_js ) . '" defer></script>' . "\n"
        . '<script id="fplms-quiz-type-translation-js" src="' . esc_url( $tr_js ) . '" defer></script>' . "\n";

    return str_ireplace( '</head>', $inject . '</head>', $html );
}
