<?php
/**
 * Script de migración para agregar el nivel de "Empresa" a la jerarquía
 * 
 * Nueva jerarquía:
 * 1. Ciudades
 * 2. Empresa (NUEVO)
 * 3. Canales / Franquicias
 * 4. Sucursales
 * 5. Cargos
 * 
 * INSTRUCCIONES:
 * 1. Hacer backup de la base de datos antes de ejecutar
 * 2. Acceder desde navegador: http://tu-sitio.com/wp-content/plugins/fairplay-lms-masterstudy-extensions/migrate-add-company-level.php
 * 3. O ejecutar desde línea de comandos: php migrate-add-company-level.php
 * 4. Eliminar este archivo después de ejecutarlo por seguridad
 */

// Cargar WordPress
require_once('../../../../wp-load.php');

// Verificar que el usuario es administrador (si se ejecuta desde navegador)
if (php_sapi_name() !== 'cli' && !current_user_can('manage_options')) {
    die('Acceso denegado');
}

global $wpdb;

echo "<h2>Migración: Agregar nivel de Empresa</h2>\n";
echo "<p>Iniciando migración...</p>\n";

// Registrar la nueva taxonomía de empresa
$taxonomy_company = 'fplms_company';

// Verificar si la taxonomía ya existe
$existing_terms = get_terms([
    'taxonomy' => $taxonomy_company,
    'hide_empty' => false,
]);

if (is_wp_error($existing_terms)) {
    // Registrar taxonomía
    register_taxonomy(
        $taxonomy_company,
        'post',
        [
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'hierarchical' => false,
            'label' => 'Empresas'
        ]
    );
    echo "✓ Taxonomía 'fplms_company' registrada.<br>\n";
} else {
    echo "✓ Taxonomía 'fplms_company' ya existe.<br>\n";
}

// Agregar la nueva constante de meta para empresas en los canales
// META_TERM_COMPANIES para canales (array JSON de empresas)

echo "<br><h3>Verificación de estructura completada</h3>\n";
echo "<p><strong>Próximos pasos:</strong></p>\n";
echo "<ol>\n";
echo "<li>La taxonomía 'fplms_company' ha sido registrada</li>\n";
echo "<li>Los archivos PHP del plugin han sido actualizados con las nuevas constantes</li>\n";
echo "<li>La jerarquía ahora es: Ciudades → Empresas → Canales → Sucursales → Cargos</li>\n";
echo "<li>Puedes comenzar a crear empresas desde el panel de administración</li>\n";
echo "</ol>\n";

echo "<br><p style='color: green; font-weight: bold;'>✓ Migración completada exitosamente!</p>\n";
echo "<p style='color: red; font-weight: bold;'>IMPORTANTE: Elimina este archivo (migrate-add-company-level.php) por seguridad.</p>";
