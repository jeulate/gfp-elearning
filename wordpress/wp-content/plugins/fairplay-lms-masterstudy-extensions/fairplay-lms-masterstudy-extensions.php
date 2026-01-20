<?php
/**
 * Plugin Name: FairPlay LMS – MasterStudy Extensions
 * Plugin URI:  https://www.linkedin.com/in/jaeulate/
 * Description: Extensiones del panel admin, estructuras, usuarios y cursos para la plataforma eLearning con MasterStudy.
 * Version:     0.7.0
 * Author:      Insoftline / Juan Eulate
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FPLMS_PLUGIN_FILE', __FILE__ );
define( 'FPLMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FPLMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Includes
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-config.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-capabilities.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-progress.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-structures.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-users.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-courses.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-course-visibility.php';
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-reports.php';
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

// Al activar el plugin, crear la tabla para registrar los inicios de sesión de usuario
register_activation_hook(__FILE__, 'fplms_create_user_logins_table');
function fplms_create_user_logins_table() {
    if ( ! function_exists('dbDelta') ) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'fplms_user_logins';
    $charset_collate = $wpdb->get_charset_collate();

    // Verifica si la tabla ya existe
    $table_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $table_name
    ));
    if ($table_exists === $table_name) {
        return; // Ya existe, no hacer nada
    }

    $sql = "CREATE TABLE $table_name (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        login_time DATETIME NOT NULL,
        INDEX (user_id),
        INDEX (login_time)
    ) $charset_collate;";

    dbDelta($sql);
}

// Bootstrap del plugin
function fairplay_lms_extensions_bootstrap() {
    global $fairplay_lms_plugin;
    $fairplay_lms_plugin = new FairPlay_LMS_Plugin();
}
add_action( 'plugins_loaded', 'fairplay_lms_extensions_bootstrap' );
