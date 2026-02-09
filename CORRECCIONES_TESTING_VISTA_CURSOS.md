# 🐛 CORRECCIONES DE TESTING: Vista de Cursos

**Fecha:** 2025-02-05  
**Tipo:** Bug fixes durante testing  
**Estado:** 🔄 EN PROGRESO

---

## 📋 Problemas Reportados

### Problema 1: No se muestran los instructores en la columna "Profesor"

**Síntoma:**
- La columna "Profesor" muestra "— Sin asignar —" incluso cuando el curso tiene un instructor asignado
- Ejemplo: "Fair Play SS26" tiene asignado a Juan Antonio Eulate (ID: 2)

**Análisis del código:**

El método `get_available_instructors()` está implementado correctamente:
```php
private function get_available_instructors(): array {
    $user_query = new WP_User_Query([
        'role__in' => [
            FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR, // 'stm_lms_instructor'
            'administrator',
        ],
        'number'   => 300,
        'orderby'  => 'display_name',
        'order'    => 'ASC',
    ]);
    
    return (array) $user_query->get_results();
}
```

**Posibles causas:**

1. **Los usuarios no tienen el rol correcto en la base de datos**
   - El rol `stm_lms_instructor` podría no estar asignado correctamente
   - Verificar en wp_usermeta si el meta_key es `wp_capabilities` o `wp_user_level`

2. **El meta `stm_lms_teacher` no está guardado en el curso**
   - La constante `MS_META_COURSE_TEACHER` = 'stm_lms_teacher'
   - Verificar si MasterStudy guarda ese meta correctamente

3. **Campo de meta diferente en MasterStudy**
   - Algunas versiones de MasterStudy usan `co-instructors` o `instructor_id`

**Verificación necesaria:**
```sql
-- Verificar roles de usuarios
SELECT user_id, meta_value 
FROM wp_usermeta 
WHERE meta_key = 'wp_capabilities' 
AND user_id = 2;

-- Verificar meta del curso
SELECT post_id, meta_key, meta_value 
FROM wp_postmeta 
WHERE post_id = 882 
AND meta_key LIKE '%teacher%' OR meta_key LIKE '%instructor%';
```

---

### Problema 2: Al crear curso se abre Course Builder en lugar del editor de post

**Síntoma:**
- Al hacer clic en "Crear Nuevo Curso", se abre el Course Builder de MasterStudy
- Debería abrir el editor de post estándar de WordPress para usar la meta box de estructuras

**Causa raíz:**
MasterStudy LMS tiene una configuración que redirige automáticamente al Course Builder cuando se crea un curso nuevo.

**Posibles ubicaciones del código:**
- `masterstudy-lms-learning-management-system/admin/admin.php`
- `masterstudy-lms-learning-management-system/includes/course-builder.php`

**Solución propuesta:**

#### Opción 1: Desactivar redirección de MasterStudy (Temporal)
Buscar en el código de MasterStudy algo como:
```php
add_action('admin_init', function() {
    if (isset($_GET['post_type']) && $_GET['post_type'] === 'stm-courses') {
        wp_redirect(admin_url('admin.php?page=course-builder'));
        exit;
    }
});
```

Y comentarlo o deshabilitarlo.

#### Opción 2: Forzar el editor clásico para cursos (Recomendado)
Agregar filtro en nuestro plugin para forzar el editor clásico:

```php
// En class-fplms-plugin.php register_hooks()
add_filter('use_block_editor_for_post_type', [$this, 'disable_course_builder_redirect'], 10, 2);

// Nuevo método en class-fplms-plugin.php
public function disable_course_builder_redirect($use_block_editor, $post_type) {
    if ($post_type === FairPlay_LMS_Config::MS_PT_COURSE) {
        // Forzar editor clásico para que nuestra meta box funcione
        return false;
    }
    return $use_block_editor;
}
```

#### Opción 3: Agregar parámetro de bypass (Más flexible)
Modificar el enlace para incluir un parámetro que indique que queremos usar el editor estándar:

```php
// En lugar de:
admin_url('post-new.php?post_type=stm-courses')

// Usar:
admin_url('post-new.php?post_type=stm-courses&use_classic_editor=1')
```

Y agregar un hook que capture ese parámetro para evitar la redirección.

---

## 🔧 Correcciones Propuestas

### Corrección 1: Debugging de Instructores

Agregar logging temporal para diagnosticar:

```php
private function get_available_instructors(): array {
    $user_query = new WP_User_Query([
        'role__in' => [
            FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR,
            'administrator',
        ],
        'number'   => 300,
        'orderby'  => 'display_name',
        'order'    => 'ASC',
    ]);
    
    $results = (array) $user_query->get_results();
    
    // DEBUG: Descomentar para ver qué usuarios se están obteniendo
    // error_log('FairPlay LMS: Instructores encontrados: ' . count($results));
    // foreach ($results as $user) {
    //     error_log('  - ' . $user->display_name . ' (ID: ' . $user->ID . ')');
    // }
    
    return $results;
}
```

### Corrección 2: Verificar meta correcta del instructor

Modificar el código que obtiene el instructor actual:

```php
// ANTES:
$teacher_id = (int) get_post_meta($course->ID, FairPlay_LMS_Config::MS_META_COURSE_TEACHER, true);

// DESPUÉS (con fallback):
$teacher_id = (int) get_post_meta($course->ID, FairPlay_LMS_Config::MS_META_COURSE_TEACHER, true);

// Fallback: Intentar otros posibles meta keys
if (!$teacher_id) {
    $teacher_id = (int) get_post_meta($course->ID, 'instructor_id', true);
}
if (!$teacher_id) {
    $teacher_id = (int) get_post_meta($course->ID, 'co-instructors', true);
}
```

### Corrección 3: Forzar editor clásico

Agregar en `class-fplms-plugin.php`:

```php
// En register_hooks()
add_filter('use_block_editor_for_post_type', [$this, 'force_classic_editor_for_courses'], 10, 2);

// Nuevo método
public function force_classic_editor_for_courses($use_block_editor, $post_type) {
    // Forzar editor clásico para cursos de MasterStudy
    // para que nuestra meta box de estructuras funcione correctamente
    if ($post_type === FairPlay_LMS_Config::MS_PT_COURSE) {
        return false;
    }
    return $use_block_editor;
}
```

---

## 🧪 Plan de Testing

### Test 1: Verificar roles en base de datos
```bash
# Conectar a MySQL
mysql -u root -p

# Seleccionar base de datos
USE wordpress_database;

# Ver rol del usuario ID 2
SELECT user_login, display_name, meta_value as roles
FROM wp_users u
JOIN wp_usermeta um ON u.ID = um.user_id
WHERE u.ID = 2 AND um.meta_key = 'wp_capabilities';
```

**Resultado esperado:**
```
+-------------+-----------------------+--------------------------------+
| user_login  | display_name          | roles                          |
+-------------+-----------------------+--------------------------------+
| jeulate     | Juan Antonio Eulate   | a:1:{s:17:"stm_lms_instructor"|
+-------------+-----------------------+--------------------------------+
```

### Test 2: Verificar meta del curso
```bash
SELECT meta_key, meta_value
FROM wp_postmeta
WHERE post_id = 882
ORDER BY meta_key;
```

Buscar específicamente:
- `stm_lms_teacher`
- `instructor_id`
- `co-instructors`

### Test 3: Probar solución de editor clásico
1. Implementar el filtro `force_classic_editor_for_courses`
2. Hacer clic en "Crear Nuevo Curso"
3. Verificar que se abre el editor de post estándar de WordPress
4. Verificar que aparece la meta box "🏢 Asignar Estructuras FairPlay"

---

## 📝 Notas Adicionales

### Compatibilidad con Course Builder (Feature 3)

Cuando implementemos Feature 3, necesitaremos:

1. **Hook en Course Builder:**
   - Detectar cuándo se abre el Course Builder
   - Inyectar nuestra meta box de estructuras en el sidebar

2. **AJAX para guardar estructuras desde Course Builder:**
   - Crear endpoint AJAX específico
   - Validar permisos de instructor

3. **Sincronización:**
   - Asegurar que las estructuras se guarden tanto desde el editor clásico como desde Course Builder

---

## ✅ Checklist de Implementación

- [ ] Agregar logging para debugging de instructores
- [ ] Verificar roles de usuarios en base de datos
- [ ] Verificar meta keys de instructores en cursos
- [ ] Implementar filtro para forzar editor clásico
- [ ] Probar creación de curso con editor clásico
- [ ] Verificar que meta box de estructuras aparece
- [ ] Probar asignación de instructor desde dropdown
- [ ] Documentar comportamiento para Feature 3

---

**Estado actual:** Pendiente de implementación de correcciones
