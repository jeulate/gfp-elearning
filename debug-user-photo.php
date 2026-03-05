<?php
/**
 * Script de diagnóstico para detectar meta keys de foto de usuario
 * 
 * INSTRUCCIONES:
 * 1. Sube este archivo a: wp-content/plugins/fairplay-lms-masterstudy-extensions/
 * 2. Accede desde navegador: https://boostacademy.com.bo/wp-content/plugins/fairplay-lms-masterstudy-extensions/debug-user-photo.php?user_id=X
 * 3. Reemplaza X con el ID del usuario juaneulate
 */

// Cargar WordPress
require_once('../../../wp-load.php');

// Verificar que se pasó un user_id
if (!isset($_GET['user_id'])) {
    die('❌ Error: Debes pasar ?user_id=X en la URL');
}

$user_id = absint($_GET['user_id']);

// Verificar que el usuario existe
$user = get_userdata($user_id);
if (!$user) {
    die('❌ Error: Usuario ID ' . $user_id . ' no encontrado');
}

echo '<h1>🔍 Diagnóstico de Foto de Usuario</h1>';
echo '<h2>Usuario: ' . esc_html($user->display_name) . ' (ID: ' . $user_id . ')</h2>';
echo '<hr>';

// Obtener TODOS los user meta
$all_meta = get_user_meta($user_id);

echo '<h3>📋 Todos los User Meta:</h3>';
echo '<table border="1" cellpadding="10" style="border-collapse: collapse; width: 100%;">';
echo '<tr><th style="text-align:left;">Meta Key</th><th style="text-align:left;">Meta Value</th></tr>';

$photo_related_keys = [];

foreach ($all_meta as $key => $values) {
    $value = maybe_unserialize($values[0]);
    
    // Detectar keys relacionadas con foto/avatar/image
    if (stripos($key, 'photo') !== false || 
        stripos($key, 'avatar') !== false || 
        stripos($key, 'image') !== false ||
        stripos($key, 'stm_lms') !== false) {
        $photo_related_keys[$key] = $value;
    }
    
    // Mostrar todos los meta
    $display_value = is_array($value) ? json_encode($value) : $value;
    echo '<tr>';
    echo '<td><code>' . esc_html($key) . '</code></td>';
    echo '<td>' . esc_html(substr($display_value, 0, 200)) . (strlen($display_value) > 200 ? '...' : '') . '</td>';
    echo '</tr>';
}

echo '</table>';

echo '<hr>';
echo '<h3>🎯 Meta Keys Relacionados con Foto:</h3>';

if (empty($photo_related_keys)) {
    echo '<p style="color: red;">❌ No se encontraron meta keys relacionados con foto/avatar</p>';
} else {
    echo '<table border="1" cellpadding="10" style="border-collapse: collapse; width: 100%;">';
    echo '<tr><th style="text-align:left;">Meta Key</th><th style="text-align:left;">Meta Value</th><th>Acción</th></tr>';
    
    foreach ($photo_related_keys as $key => $value) {
        $display_value = is_array($value) ? json_encode($value) : $value;
        echo '<tr>';
        echo '<td><strong><code>' . esc_html($key) . '</code></strong></td>';
        echo '<td>' . esc_html($display_value) . '</td>';
        
        // Si es un número, puede ser un attachment ID
        if (is_numeric($value)) {
            $attachment_url = wp_get_attachment_url($value);
            if ($attachment_url) {
                echo '<td>✅ <a href="' . esc_url($attachment_url) . '" target="_blank">Ver Imagen</a></td>';
            } else {
                echo '<td>❌ No es attachment válido</td>';
            }
        } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
            echo '<td>🔗 <a href="' . esc_url($value) . '" target="_blank">Ver URL</a></td>';
        } else {
            echo '<td>-</td>';
        }
        
        echo '</tr>';
    }
    
    echo '</table>';
}

echo '<hr>';
echo '<h3>🔬 Verificación de Funciones PHP:</h3>';
echo '<ul>';

// Verificar fplms_user_photo_url
$fplms_url = get_user_meta($user_id, 'fplms_user_photo_url', true);
echo '<li><code>fplms_user_photo_url</code>: ' . ($fplms_url ? '✅ ' . esc_html($fplms_url) : '❌ No existe') . '</li>';

// Verificar fplms_user_photo_id
$fplms_id = get_user_meta($user_id, 'fplms_user_photo_id', true);
if ($fplms_id) {
    $fplms_id_url = wp_get_attachment_url($fplms_id);
    echo '<li><code>fplms_user_photo_id</code>: ✅ ' . $fplms_id . ' → <a href="' . esc_url($fplms_id_url) . '" target="_blank">' . esc_html($fplms_id_url) . '</a></li>';
} else {
    echo '<li><code>fplms_user_photo_id</code>: ❌ No existe</li>';
}

// Verificar stm_lms_user_photo
$stm_photo = get_user_meta($user_id, 'stm_lms_user_photo', true);
if ($stm_photo) {
    $stm_photo_url = wp_get_attachment_url($stm_photo);
    echo '<li><code>stm_lms_user_photo</code>: ✅ ' . $stm_photo . ' → <a href="' . esc_url($stm_photo_url) . '" target="_blank">' . esc_html($stm_photo_url) . '</a></li>';
} else {
    echo '<li><code>stm_lms_user_photo</code>: ❌ No existe</li>';
}

echo '</ul>';

echo '<hr>';
echo '<h3>💡 Recomendaciones:</h3>';
echo '<ol>';

if ($stm_photo && !$fplms_id) {
    echo '<li><strong>ACCIÓN:</strong> Ejecuta esto en la base de datos o via WP-CLI para sincronizar:<br>';
    echo '<code style="background:#f0f0f0;padding:10px;display:block;margin:10px 0;">';
    echo "update_user_meta($user_id, 'fplms_user_photo_id', $stm_photo);<br>";
    echo "update_user_meta($user_id, 'fplms_user_photo_url', '" . esc_html($stm_photo_url) . "');";
    echo '</code></li>';
}

if (!$stm_photo && !$fplms_id) {
    echo '<li><strong>PROBLEMA:</strong> El usuario no tiene ninguna foto guardada en meta keys estándar.</li>';
    echo '<li>Revisa los meta keys de la tabla superior para identificar dónde MasterStudy guarda la foto.</li>';
}

if ($fplms_id) {
    echo '<li>✅ El usuario ya tiene sincronizada la foto con FairPlay LMS. Debería aparecer en el formulario de edición.</li>';
}

echo '</ol>';

?>
