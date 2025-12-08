# 🔍 Checklist de Verificación Post-Implementación

## Verificación del Código

### ✅ Archivos Creados/Modificados

```bash
✓ class-fplms-config.php - 4 constantes agregadas (líneas ~32-36)
✓ class-fplms-courses.php - Constructor + 3 métodos nuevos
✓ class-fplms-course-visibility.php - Archivo NUEVO completo (230 líneas)
✓ class-fplms-plugin.php - Instancia + 2 hooks + 1 método
✓ fairplay-lms-masterstudy-extensions.php - 1 require agregado
```

### ✅ Verificar Require

En `fairplay-lms-masterstudy-extensions.php` línea ~27:
```php
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-course-visibility.php';
```

---

## Prueba en WordPress

### 1️⃣ Activar Plugin
```
1. WordPress Admin → Plugins
2. Buscar: "FairPlay LMS"
3. Hacer clic en "Activar"
4. NO debería haber errores PHP
```

### 2️⃣ Verificar Menú
```
1. Dashboard → FairPlay LMS
2. Debería haber submenu "Cursos"
3. Hacer clic en "Cursos"
4. Debería listar cursos actuales
5. Cada curso debería tener 2 botones nuevos:
   - "Gestionar estructuras"
   - "Gestionar módulos"
```

### 3️⃣ Crear Estructuras de Prueba
```
1. Admin → FairPlay LMS → Estructuras
2. Tab "Ciudades" → Crear: "Test-Bogota"
3. Tab "Canales" → Crear: "Test-Premium"
4. Activar ambas
```

### 4️⃣ Asignar Estructura a Usuario
```
1. Admin → Usuarios → Editar tu usuario
2. Bajar a "Estructura organizacional FairPlay"
3. Seleccionar:
   - Ciudad: Test-Bogota
   - Canal: Test-Premium
4. Guardar cambios
```

### 5️⃣ Crear Curso con Restricción
```
1. Admin → FairPlay LMS → Cursos
2. Seleccionar un curso existente
3. Hacer clic en "Gestionar estructuras"
4. Marcar:
   ✓ Ciudad: Test-Bogota
   ✓ Canal: Test-Premium
5. Botón "Guardar estructuras"
6. Debería redirigir a listado de cursos
```

### 6️⃣ Verificar Metadata en Base de Datos
```
PHP en functions.php:
add_action('wp_footer', function() {
    if(is_user_logged_in()) {
        $user_id = get_current_user_id();
        echo '<!-- DEBUG: ';
        echo 'City=' . get_user_meta($user_id, 'fplms_city', true) . '; ';
        echo 'Channel=' . get_user_meta($user_id, 'fplms_channel', true);
        echo ' -->';
    }
});
```
Debe mostrar IDs de términos en comentario HTML.

---

## Pruebas Funcionales

### Test 1: Usuario Ve Cursos Autorizados
```
SETUP:
- User A: Ciudad = Test-Bogota
- Curso 1: Restringido a Test-Bogota
- Curso 2: Sin restricción

RESULTADO ESPERADO:
- User A ve Curso 1 ✅
- User A ve Curso 2 ✅

TEST:
1. Ingresar como User A
2. Ir a área de cursos (MasterStudy)
3. Debería listar ambos cursos
```

### Test 2: Usuario NO Ve Cursos No Autorizados
```
SETUP:
- User B: Ciudad = Test-Medellin  
- Curso 1: Restringido a Test-Bogota

RESULTADO ESPERADO:
- User B NO ve Curso 1 ❌

TEST:
1. Crear User B con estructura diferente
2. Ingresar como User B
3. Ir a área de cursos
4. Curso 1 NO debería aparecer
```

### Test 3: Admin Ve Todo
```
SETUP:
- User C: Admin con manage_options
- Curso 1-5: Con diferentes restricciones

RESULTADO ESPERADO:
- User C ve TODOS los cursos

TEST:
1. Ingresar como Admin
2. Ir a FairPlay LMS → Cursos
3. Debería listar todos
4. Ir a área de cursos (frontend)
5. Debería ver todos también
```

### Test 4: Sin Restricciones = Para Todos
```
SETUP:
- Any User: Cualquier estructura
- Curso X: Sin marcar ninguna estructura

RESULTADO ESPERADO:
- Todos ven Curso X

TEST:
1. Editar Curso X → Gestionar estructuras
2. Dejar todos los checkboxes SIN marcar
3. Guardar
4. Ingresar con diferentes usuarios
5. Todos deben ver el curso
```

---

## Verificación de Errores

### Errores Esperados (Normal)

VS Code mostrará estos como errores de linting - **NO SON PROBLEMAS REALES**:
```
- "Call to unknown function: 'add_action'"
- "Call to unknown function: 'get_post_meta'"
- "Call to unknown function: 'wp_nonce_field'"

Razón: VS Code no tiene stubs de WordPress. Funciones existirán en runtime.
```

### Errores Reales (Si hay)

Si ves estos, HAY UN PROBLEMA:
```
❌ Parse error en class-fplms-course-visibility.php
   → Sintaxis PHP incorrecta

❌ Call to undefined method en class-fplms-plugin.php
   → Constructor de Courses no recibe $structures

❌ Datos no se guardan en DB
   → Verificar que nonce es correcto
```

---

## Verificación en Base de Datos

### Tabla: wp_postmeta
```sql
-- Ver estructuras asignadas a un curso:
SELECT * FROM wp_postmeta 
WHERE post_id = [COURSE_ID] 
AND meta_key LIKE 'fplms_course_%';

Debería mostrar 4 filas:
- fplms_course_cities = a:3:{i:0;i:1;...}
- fplms_course_channels = a:2:{...}
- fplms_course_branches = a:0:{}
- fplms_course_roles = a:1:{...}
```

### Tabla: wp_usermeta
```sql
-- Ver estructura de un usuario:
SELECT * FROM wp_usermeta 
WHERE user_id = [USER_ID] 
AND meta_key IN('fplms_city','fplms_channel','fplms_branch','fplms_job_role');

Debería mostrar 4 filas con valores INT (IDs de términos)
```

---

## Puntos de Validación

### 1. Interfaz Aparece
- [ ] Botón "Gestionar estructuras" visible en tabla de cursos
- [ ] Al clickear, se abre vista con checkboxes
- [ ] Checkboxes funcionan (se marcan/desmarcan)
- [ ] Botón "Guardar estructuras" existe

### 2. Datos Se Guardan
- [ ] Al guardar, se redirige a listado de cursos
- [ ] NO hay error PHP
- [ ] En BD, postmeta contiene los datos
- [ ] Al volver a abrir, checkboxes estaban guardados

### 3. Filtrado Funciona
- [ ] Usuario con estructura apropiada VE el curso
- [ ] Usuario sin estructura correcta NO lo ve
- [ ] Admin lo ve de todas formas
- [ ] Curso sin restricciones se ve para todos

### 4. Seguridad
- [ ] Nonce se valida (form tiene wp_nonce_field)
- [ ] POST solo funciona con CAP_MANAGE_COURSES
- [ ] Inputs están sanitizados (absint, array_map)
- [ ] No hay SQL injection posible

---

## Comandos Útiles (Terminal WordPress)

Si instalaste WP-CLI:

```bash
# Ver todos los cursos
wp post list --post_type=stm-courses

# Ver postmeta de un curso
wp post meta list [COURSE_ID] | grep fplms_course

# Ver metadata de un usuario
wp user meta list [USER_ID] | grep fplms

# Limpiar transientes (si hay caché)
wp transient delete --all
```

---

## Logs a Revisar

### PHP Error Log
```bash
/var/log/php-fpm/error.log  (Linux)
C:\xampp\logs\php_error.log (Windows XAMPP)
/Applications/XAMPP/logs/php_error.log (Mac)
```

### WordPress Debug Log
En wp-config.php está habilitado:
```php
define('WP_DEBUG_LOG', true);
// Revisar: wp-content/debug.log
```

### Consola JavaScript (DevTools)
```javascript
// Si hay errores AJAX al guardar:
1. F12 → Network tab
2. Enviar formulario
3. Ver POST request
4. Verificar response (debería ser redirect 200)
```

---

## Resumen de Puntos Críticos

| Ítem | Estado | Verificar |
|------|--------|-----------|
| Archivo visibility creado | ✓ | Existe en includes/ |
| Require agregado | ✓ | En main plugin file |
| Constructor updated | ✓ | Recibe $structures |
| Hooks registrados | ✓ | En register_hooks() |
| UI aparece | ? | Ir a Cursos y ver botón |
| Datos guardan | ? | Revisar BD |
| Filtrado funciona | ? | Probar con usuarios |

---

## Si Algo No Funciona

### Sintaxis Error
```
1. Abrir class-fplms-course-visibility.php
2. Revisar cierre de clases } al final
3. Revisar paréntesis balanceados
4. Copiar a IDE local y verificar
```

### Interfaz No Aparece
```
1. Verificar que class-fplms-courses.php tenga $this->structures
2. Verificar que render_course_structures_view existe
3. Revisar que view='structures' en URL es reconocido
4. Ver logs PHP por errores
```

### Datos No Se Guardan
```
1. Verificar que fplms_courses_action = 'assign_structures' en POST
2. Revisar nonce en form: wp_nonce_field('fplms_courses_save')
3. Verificar save_course_structures($course_id) se ejecuta
4. Ver BD si update_post_meta se ejecutó
```

### Filtrado No Funciona
```
1. Verificar estructura asignada a usuario: get_user_meta()
2. Verificar estructura asignada a curso: get_post_meta()
3. Revisar hooks: stm_lms_get_user_courses y stm_lms_course_list_query
4. Probar directamente: $visibility->can_user_see_course()
```

---

## Checklist Final

Antes de pasar a producción:

- [ ] Plugin se activa sin errores
- [ ] Interface aparece en admin
- [ ] Puedo asignar estructuras a cursos
- [ ] Los datos se guardan en BD
- [ ] Usuarios ven solo sus cursos
- [ ] Admin ve todos los cursos
- [ ] Cursos sin restricción se ven para todos
- [ ] No hay errores en logs
- [ ] Cambios son instantáneos
- [ ] Permisos se respetan

---

**Si todos los puntos están ✅, entonces está listo para producción.**

