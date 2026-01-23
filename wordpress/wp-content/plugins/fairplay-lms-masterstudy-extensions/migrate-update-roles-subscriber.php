<?php
/**
 * Script de migración para actualizar sistema de roles
 * De: fplms_student/fplms_tutor a subscriber/stm_lms_instructor
 * 
 * EJECUTAR UNA SOLA VEZ y luego ELIMINAR este archivo.
 * 
 * Instrucciones:
 * 1. Acceder a: https://tu-sitio.com/wp-content/plugins/fairplay-lms-masterstudy-extensions/migrate-update-roles-subscriber.php
 * 2. Verificar que la migración se complete exitosamente
 * 3. ELIMINAR este archivo por seguridad
 */

// Cargar WordPress
require_once __DIR__ . '/../../../../wp-load.php';

// Verificar permisos de administrador
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'No tienes permisos para ejecutar esta migración.' );
}

echo '<h1>Migración de Roles: Actualización a Sistema Simplificado</h1>';
echo '<p>Iniciando migración...</p>';

// 1. Agregar capacidades del plugin al rol subscriber
echo '<h2>1. Configurando rol Subscriber (Estudiante)</h2>';
$subscriber = get_role( 'subscriber' );
if ( $subscriber ) {
    $subscriber->add_cap( 'fplms_view_progress' );
    $subscriber->add_cap( 'fplms_view_calendar' );
    echo '<p>✅ Capacidades agregadas al rol subscriber</p>';
} else {
    echo '<p>❌ No se pudo encontrar el rol subscriber</p>';
}

// 2. Actualizar capacidades del rol stm_lms_instructor (Docente)
echo '<h2>2. Configurando rol Instructor (Docente)</h2>';
$instructor = get_role( 'stm_lms_instructor' );
if ( $instructor ) {
    $instructor->add_cap( 'fplms_manage_courses' );
    $instructor->add_cap( 'fplms_view_reports' );
    $instructor->add_cap( 'fplms_view_progress' );
    $instructor->add_cap( 'fplms_view_calendar' );
    echo '<p>✅ Capacidades actualizadas para instructor</p>';
} else {
    echo '<p>⚠️ El rol stm_lms_instructor no existe (se creará cuando MasterStudy esté activo)</p>';
}

// 3. Asegurar capacidades del administrador
echo '<h2>3. Configurando rol Administrator</h2>';
$admin = get_role( 'administrator' );
if ( $admin ) {
    $admin->add_cap( 'fplms_manage_structures' );
    $admin->add_cap( 'fplms_manage_users' );
    $admin->add_cap( 'fplms_manage_courses' );
    $admin->add_cap( 'fplms_view_reports' );
    $admin->add_cap( 'fplms_view_progress' );
    $admin->add_cap( 'fplms_view_calendar' );
    echo '<p>✅ Capacidades actualizadas para administrador</p>';
}

// 4. Actualizar matriz de privilegios
echo '<h2>4. Actualizando Matriz de Privilegios</h2>';
$new_matrix = [
    'subscriber' => [
        'fplms_manage_structures' => false,
        'fplms_manage_users'      => false,
        'fplms_manage_courses'    => false,
        'fplms_view_reports'      => false,
        'fplms_view_progress'     => true,
        'fplms_view_calendar'     => true,
    ],
    'stm_lms_instructor' => [
        'fplms_manage_structures' => false,
        'fplms_manage_users'      => false,
        'fplms_manage_courses'    => true,
        'fplms_view_reports'      => true,
        'fplms_view_progress'     => true,
        'fplms_view_calendar'     => true,
    ],
    'administrator' => [
        'fplms_manage_structures' => true,
        'fplms_manage_users'      => true,
        'fplms_manage_courses'    => true,
        'fplms_view_reports'      => true,
        'fplms_view_progress'     => true,
        'fplms_view_calendar'     => true,
    ],
];

update_option( 'fplms_cap_matrix', $new_matrix );
echo '<p>✅ Matriz de privilegios actualizada</p>';

// 5. Migrar usuarios existentes con roles antiguos a subscriber
echo '<h2>5. Migrando Usuarios con Roles Antiguos</h2>';

// Buscar usuarios con rol fplms_student
$students = get_users( [ 'role' => 'fplms_student', 'number' => -1 ] );
$students_count = 0;
foreach ( $students as $user ) {
    $user_obj = new WP_User( $user->ID );
    $user_obj->remove_role( 'fplms_student' );
    $user_obj->add_role( 'subscriber' );
    $students_count++;
}
echo '<p>✅ Migrados ' . $students_count . ' usuarios de fplms_student a subscriber (Estudiante)</p>';

// Buscar usuarios con rol fplms_tutor
$tutors = get_users( [ 'role' => 'fplms_tutor', 'number' => -1 ] );
$tutors_count = 0;
foreach ( $tutors as $user ) {
    $user_obj = new WP_User( $user->ID );
    $user_obj->remove_role( 'fplms_tutor' );
    // Si existe el rol instructor, asignarlo; si no, dejar como subscriber
    if ( get_role( 'stm_lms_instructor' ) ) {
        $user_obj->add_role( 'stm_lms_instructor' );
    } else {
        $user_obj->add_role( 'subscriber' );
        echo '<p>⚠️ Usuario ID ' . $user->ID . ': asignado como subscriber (instructor no disponible)</p>';
    }
    $tutors_count++;
}
echo '<p>✅ Migrados ' . $tutors_count . ' usuarios de fplms_tutor a stm_lms_instructor (Docente)</p>';

// 6. Limpiar roles antiguos (opcional, comentado por seguridad)
echo '<h2>6. Limpieza de Roles Antiguos</h2>';
echo '<p>⚠️ Los roles fplms_student y fplms_tutor se mantienen por seguridad.</p>';
echo '<p>Si deseas eliminarlos después de verificar que todo funciona:</p>';
echo '<pre>remove_role("fplms_student");</pre>';
echo '<pre>remove_role("fplms_tutor");</pre>';

// Estadísticas finales
echo '<h2>✅ Migración Completada</h2>';
echo '<ul>';
echo '<li><strong>Estudiantes migrados:</strong> ' . $students_count . '</li>';
echo '<li><strong>Docentes migrados:</strong> ' . $tutors_count . '</li>';
echo '<li><strong>Total usuarios procesados:</strong> ' . ($students_count + $tutors_count) . '</li>';
echo '</ul>';

echo '<h3>🎯 Sistema de Roles Actualizado:</h3>';
echo '<ul>';
echo '<li><strong>Estudiante</strong> → subscriber (rol nativo de WordPress/MasterStudy)</li>';
echo '<li><strong>Docente</strong> → stm_lms_instructor (rol de MasterStudy LMS)</li>';
echo '<li><strong>Administrador</strong> → administrator (rol nativo de WordPress)</li>';
echo '</ul>';

echo '<hr>';
echo '<p style="color: red; font-weight: bold;">⚠️ IMPORTANTE: Ahora debes ELIMINAR este archivo (migrate-update-roles-subscriber.php) por seguridad.</p>';
echo '<p><a href="' . admin_url('users.php') . '">Ir a lista de usuarios</a></p>';
