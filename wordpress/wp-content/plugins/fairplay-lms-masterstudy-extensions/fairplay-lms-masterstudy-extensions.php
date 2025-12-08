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

// Bootstrap del plugin
function fairplay_lms_extensions_bootstrap() {
    global $fairplay_lms_plugin;
    $fairplay_lms_plugin = new FairPlay_LMS_Plugin();
}
add_action( 'plugins_loaded', 'fairplay_lms_extensions_bootstrap' );
