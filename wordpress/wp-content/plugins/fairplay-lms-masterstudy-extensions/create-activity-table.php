<?php
/**
 * Script para crear la tabla de actividad de usuarios
 * Ejecutar este archivo una vez para crear la tabla wp_fplms_user_activity
 * 
 * INSTRUCCIONES:
 * 1. Acceder a este archivo desde el navegador: http://tu-sitio.com/wp-content/plugins/fairplay-lms-masterstudy-extensions/create-activity-table.php
 * 2. O ejecutar desde la línea de comandos: php create-activity-table.php
 * 3. Eliminar este archivo después de ejecutarlo por seguridad
 */

// Cargar WordPress
require_once('../../../../wp-load.php');

// Verificar que el usuario es administrador (si se ejecuta desde navegador)
if (php_sapi_name() !== 'cli' && !current_user_can('manage_options')) {
    die('Acceso denegado');
}

global $wpdb;

if (!function_exists('dbDelta')) {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
}

$charset_collate = $wpdb->get_charset_collate();

// Crear tabla de actividad
$table_activity = $wpdb->prefix . 'fplms_user_activity';
$table_exists = $wpdb->get_var($wpdb->prepare(
    "SHOW TABLES LIKE %s",
    $table_activity
));

if ($table_exists === $table_activity) {
    echo "✓ La tabla {$table_activity} ya existe.\n<br>";
} else {
    $sql = "CREATE TABLE $table_activity (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        activity_time DATETIME NOT NULL,
        page_url VARCHAR(500),
        INDEX (user_id),
        INDEX (activity_time)
    ) $charset_collate;";
    
    dbDelta($sql);
    
    // Verificar creación
    $table_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $table_activity
    ));
    
    if ($table_exists === $table_activity) {
        echo "✓ Tabla {$table_activity} creada exitosamente.\n<br>";
    } else {
        echo "✗ Error al crear la tabla {$table_activity}.\n<br>";
    }
}

// Verificar también la tabla de logins
$table_logins = $wpdb->prefix . 'fplms_user_logins';
$table_exists = $wpdb->get_var($wpdb->prepare(
    "SHOW TABLES LIKE %s",
    $table_logins
));

if ($table_exists === $table_logins) {
    echo "✓ La tabla {$table_logins} existe.\n<br>";
} else {
    $sql = "CREATE TABLE $table_logins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        login_time DATETIME NOT NULL,
        INDEX (user_id),
        INDEX (login_time)
    ) $charset_collate;";
    
    dbDelta($sql);
    echo "✓ Tabla {$table_logins} creada.\n<br>";
}

echo "\n<br><strong>✓ Configuración completada!</strong>\n<br>";
echo "<p style='color: red; font-weight: bold;'>IMPORTANTE: Elimina este archivo (create-activity-table.php) por seguridad.</p>";
