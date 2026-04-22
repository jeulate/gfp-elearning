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
