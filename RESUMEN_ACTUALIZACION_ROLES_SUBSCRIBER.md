# Actualización del Sistema de Roles - Resumen de Cambios

## 📋 Cambios Realizados

### 1. Sistema de Roles Simplificado

**Antes (4 roles):**
- `fplms_student` - Alumno FairPlay
- `fplms_tutor` - Tutor FairPlay
- `stm_lms_instructor` - Instructor MasterStudy
- `administrator` - Administrador

**Después (3 roles):**
- `subscriber` - **Estudiante** (rol nativo WordPress/MasterStudy)
- `stm_lms_instructor` - **Docente** (rol MasterStudy LMS)
- `administrator` - **Administrador** (rol nativo WordPress)

### 2. Nombres Visuales vs Roles Internos

| Nombre Visual | Rol Interno | Descripción |
|--------------|-------------|-------------|
| **Estudiante** | `subscriber` | Rol nativo de WordPress usado por MasterStudy para estudiantes |
| **Docente** | `stm_lms_instructor` | Rol de MasterStudy LMS para instructores/profesores |
| **Administrador** | `administrator` | Rol nativo de WordPress con acceso completo |

### 3. Archivos Modificados

#### `class-fplms-users.php`
- **Línea ~341**: Actualizada definición de `$roles_def_labels` con 3 roles simplificados
- Mapeo directo a roles nativos de WordPress/MasterStudy

#### `class-fplms-capabilities.php`
- **Método `activate()`**: 
  - Eliminada creación de roles personalizados `fplms_student` y `fplms_tutor`
  - Agregadas capacidades del plugin al rol nativo `subscriber`
  - Actualizado rol `stm_lms_instructor` con capacidad de ver reportes

- **Método `get_default_capability_matrix()`**:
  - Reemplazados roles antiguos por nuevos roles nativos
  - Matriz simplificada con 3 roles

### 4. Matriz de Privilegios Actualizada

```php
[
    'subscriber' => [
        'fplms_manage_structures' => false,
        'fplms_manage_users'      => false,
        'fplms_manage_courses'    => false,
        'fplms_view_reports'      => false,
        'fplms_view_progress'     => true,  // ✅
        'fplms_view_calendar'     => true,  // ✅
    ],
    'stm_lms_instructor' => [
        'fplms_manage_structures' => false,
        'fplms_manage_users'      => false,
        'fplms_manage_courses'    => true,  // ✅
        'fplms_view_reports'      => true,  // ✅
        'fplms_view_progress'     => true,  // ✅
        'fplms_view_calendar'     => true,  // ✅
    ],
    'administrator' => [
        'fplms_manage_structures' => true,  // ✅
        'fplms_manage_users'      => true,  // ✅
        'fplms_manage_courses'    => true,  // ✅
        'fplms_view_reports'      => true,  // ✅
        'fplms_view_progress'     => true,  // ✅
        'fplms_view_calendar'     => true,  // ✅
    ],
]
```

### 5. Script de Migración

**Archivo:** `migrate-update-roles-subscriber.php`

**Funciones:**
1. ✅ Configura capacidades del rol `subscriber` (Estudiante)
2. ✅ Actualiza capacidades del rol `stm_lms_instructor` (Docente)
3. ✅ Asegura capacidades del `administrator`
4. ✅ Actualiza matriz de privilegios en base de datos
5. ✅ Migra usuarios existentes:
   - `fplms_student` → `subscriber`
   - `fplms_tutor` → `stm_lms_instructor`

## 🎯 Ventajas del Nuevo Sistema

### 1. Integración Nativa con MasterStudy
- **Estudiantes**: Usa el rol `subscriber` que MasterStudy ya reconoce
- **Instructores**: Usa directamente `stm_lms_instructor` sin duplicación
- Mejor compatibilidad con actualizaciones de MasterStudy

### 2. Simplicidad
- ❌ Eliminados roles personalizados redundantes
- ✅ Solo 3 roles claros y necesarios
- ✅ Nombres en español en la interfaz

### 3. Mantenibilidad
- Menos conflictos de roles
- Actualización más sencilla del plugin
- Aprovecha roles nativos de WordPress

## 📝 Instrucciones de Migración

### Paso 1: Ejecutar Script de Migración
1. Acceder a: `https://tu-sitio.com/wp-content/plugins/fairplay-lms-masterstudy-extensions/migrate-update-roles-subscriber.php`
2. Verificar que muestre: "✅ Migración Completada"
3. Verificar estadísticas de usuarios migrados

### Paso 2: Verificar Usuarios
1. Ir a **Usuarios** en el panel de WordPress
2. Confirmar que los roles se muestran correctamente:
   - Suscriptor (antes Alumno FairPlay)
   - Instructor (antes Tutor FairPlay o Instructor MasterStudy)
   - Administrador

### Paso 3: Probar Creación de Usuario
1. Ir a **FairPlay LMS → Usuarios**
2. Clic en **Crear Usuario**
3. Verificar que el select de "Tipo de Usuario" muestre:
   - Estudiante
   - Docente
   - Administrador
4. Crear un usuario de prueba de cada tipo

### Paso 4: Verificar Matriz de Privilegios
1. En **FairPlay LMS → Usuarios**
2. Clic en **Matriz de Privilegios**
3. Confirmar que se muestran los 3 roles con privilegios correctos

### Paso 5: Eliminar Script de Migración
**¡MUY IMPORTANTE!**
```bash
# Eliminar el archivo por seguridad
rm wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/migrate-update-roles-subscriber.php
```

## ⚠️ Notas Importantes

1. **Roles Antiguos**: Los roles `fplms_student` y `fplms_tutor` se mantienen en la base de datos por seguridad, pero ya no se usan. Pueden eliminarse manualmente después de verificar que todo funciona.

2. **MasterStudy Activo**: Si MasterStudy LMS está inactivo, el rol `stm_lms_instructor` no existirá. Los usuarios se asignarán temporalmente como `subscriber` hasta que se active el plugin.

3. **Permisos**: Los usuarios migrados mantendrán todos sus datos de estructura (ciudad, empresa, canal, sucursal, cargo).

4. **Compatibilidad**: Este cambio es compatible con MasterStudy LMS 3.x y superior.

## 🔄 Rollback (Revertir Cambios)

Si necesitas revertir los cambios:

1. Restaurar backup de archivos modificados
2. Ejecutar en la consola de WordPress:
```php
// Revertir usuarios
$users = get_users(['role' => 'subscriber', 'number' => -1]);
foreach ($users as $user) {
    if (get_user_meta($user->ID, 'fplms_city', true)) {
        $user_obj = new WP_User($user->ID);
        $user_obj->remove_role('subscriber');
        $user_obj->add_role('fplms_student');
    }
}
```

## ✅ Checklist de Verificación

- [ ] Script de migración ejecutado exitosamente
- [ ] Usuarios migrados verificados en lista de usuarios
- [ ] Formulario de creación de usuarios muestra 3 roles
- [ ] Matriz de privilegios actualizada
- [ ] Prueba de creación de nuevo usuario Estudiante
- [ ] Prueba de creación de nuevo usuario Docente
- [ ] Prueba de creación de nuevo usuario Administrador
- [ ] Login de usuario migrado funciona correctamente
- [ ] Permisos de usuario verificados
- [ ] Script de migración eliminado

---

**Fecha de actualización:** Enero 2026  
**Versión del plugin:** 1.2.0  
**Compatibilidad:** WordPress 6.x, MasterStudy LMS 3.x+
