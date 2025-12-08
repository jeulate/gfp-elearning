# Guía de Implementación - Sistema de Visibilidad de Cursos por Estructura

## 📋 Resumen de la Implementación

Se ha implementado un sistema completo de **filtrado de cursos basado en estructuras organizacionales** que permite:

1. **Asignar estructuras a cursos** (ciudad, canal, sucursal, cargo)
2. **Mostrar solo cursos autorizados** a cada usuario según su estructura
3. **Interface intuitiva** en el panel de administración

---

## 🔧 Cambios Realizados

### 1. **class-fplms-config.php** ✅
Agregadas 4 constantes nuevas para almacenar metadata de cursos:
```php
public const META_COURSE_CITIES   = 'fplms_course_cities';   // Array de IDs de ciudades
public const META_COURSE_CHANNELS = 'fplms_course_channels'; // Array de IDs de canales
public const META_COURSE_BRANCHES = 'fplms_course_branches'; // Array de IDs de sucursales
public const META_COURSE_ROLES    = 'fplms_course_roles';    // Array de IDs de cargos
```

### 2. **class-fplms-courses.php** ✅
Cambios principales:

#### Constructor extendido:
```php
public function __construct( FairPlay_LMS_Structures_Controller $structures = null ) {
    $this->structures = $structures;
}
```

#### Métodos nuevos:
- `save_course_structures($course_id)` - Guarda estructuras asignadas a un curso
- `get_course_structures($course_id)` - Obtiene estructuras de un curso
- `render_course_structures_view($course_id)` - UI para asignar estructuras
- Acción `assign_structures` en `handle_form()`

#### Interface:
- Nuevo botón "Gestionar estructuras" en la tabla de cursos
- Nueva vista con checkboxes para seleccionar ciudades, canales, sucursales y cargos

### 3. **class-fplms-course-visibility.php** ✅ (NUEVO)
Servicio completo de visibilidad con métodos:

```php
// Obtiene cursos visibles para un usuario
get_visible_courses_for_user($user_id): array

// Verifica si usuario puede ver un curso
can_user_see_course($user_id, $course_id): bool

// Filtra array de cursos según usuario
filter_courses_array($course_ids, $user_id): array
```

**Lógica de filtrado:**
- Usuario sin estructura asignada → ve TODOS los cursos
- Curso sin restricciones → visible para TODOS
- Curso con restricciones → visible solo si estructura del usuario coincide

### 4. **class-fplms-plugin.php** ✅
Cambios:

#### Constructor:
```php
private $visibility; // Nueva instancia del servicio

public function __construct() {
    // ...
    $this->courses    = new FairPlay_LMS_Courses_Controller( $this->structures );
    $this->visibility = new FairPlay_LMS_Course_Visibility_Service();
    // ...
}
```

#### Hooks de filtrado:
```php
add_filter( 'stm_lms_get_user_courses', [$this->visibility, 'filter_courses_array'] );
add_filter( 'stm_lms_course_list_query', [$this, 'filter_course_query'] );
```

Método adicional:
```php
public function filter_course_query($query_args): array {
    // Limita query a cursos visibles del usuario
}
```

### 5. **fairplay-lms-masterstudy-extensions.php** ✅
Agregado el require:
```php
require_once FPLMS_PLUGIN_DIR . 'includes/class-fplms-course-visibility.php';
```

---

## 🚀 Cómo Usar

### Paso 1: Crear Estructuras (Admin)
1. Ir a **FairPlay LMS → Estructuras**
2. Crear ciudades, canales, sucursales y cargos
3. Activarlos para que estén disponibles

### Paso 2: Asignar Estructuras a Usuarios
1. Ir a **Usuarios** en el panel de WordPress
2. Editar usuario
3. Asignar ciudad, canal, sucursal y cargo

### Paso 3: Asignar Estructuras a Cursos
1. Ir a **FairPlay LMS → Cursos**
2. Hacer clic en **"Gestionar estructuras"** del curso
3. Marcar las estructuras que pueden ver el curso
4. **Si no marcas ninguna, el curso es visible para TODOS**

### Paso 4: El Usuario ve Solo sus Cursos
- Cuando un usuario accede al portal, verá automáticamente solo cursos autorizados
- Si su estructura coincide con la asignada al curso, lo verá

---

## 📊 Ejemplos de Funcionamiento

### Ejemplo 1: Curso Restringido a una Ciudad
```
Curso: "Inducción Nivel 1"
Estructuras asignadas:
  ✓ Ciudad: Bogotá

Usuario: Juan Pérez
Estructura:
  - Ciudad: Bogotá
  - Canal: Premium
  - Sucursal: Centro

Resultado: ✅ Juan VE el curso (su ciudad es Bogotá)
```

### Ejemplo 2: Curso para Múltiples Estructuras
```
Curso: "Ventas Avanzada"
Estructuras asignadas:
  ✓ Cargo: Vendedor
  ✓ Cargo: Gerente
  ✓ Ciudad: Bogotá

Usuario 1: María Rodríguez (Vendedor, Bogotá)
Resultado: ✅ VE el curso (cargo coincide)

Usuario 2: Carlos López (Vendedor, Medellín)
Resultado: ❌ NO ve el curso (ciudad no coincide)
```

### Ejemplo 3: Curso sin Restricciones
```
Curso: "Bienvenida al Sistema"
Estructuras asignadas: (ninguna)

Cualquier usuario:
Resultado: ✅ TODOS ven el curso
```

---

## 🔐 Notas Importantes

1. **Administradores**: Siempre ven todos los cursos (no se filtra para `manage_options`)

2. **Sin estructura asignada**: Usuarios sin estructura en la BD ven todos los cursos

3. **Lógica OR**: Si un curso tiene restricciones de Ciudad Y de Cargo, el usuario necesita que UNA DE ELLAS coincida (no ambas)

4. **Actualizaciones**: Los cambios son inmediatos, no requieren caché limpieza

---

## 🧪 Testing Recomendado

```
✓ Crear 2-3 estructuras en cada nivel (ciudad, canal, sucursal, cargo)
✓ Asignar estructuras a 2-3 usuarios distintos
✓ Crear 3-4 cursos con diferentes configuraciones:
  - 1 sin restricciones (visible para todos)
  - 1 restringido a ciudad
  - 1 restringido a cargo
  - 1 restringido a múltiples estructuras
✓ Ingresar como cada usuario y verificar cursos visibles
✓ Probar cambios: editar estructura de usuario/curso y verificar cambios
```

---

## 📁 Archivos Modificados

| Archivo | Cambio |
|---------|--------|
| `class-fplms-config.php` | +4 constantes META_COURSE_* |
| `class-fplms-courses.php` | +3 métodos, nueva view, constructor extendido |
| `fairplay-lms-masterstudy-extensions.php` | +1 require |
| `class-fplms-plugin.php` | +1 propiedad, +2 hooks, +1 método público |
| `class-fplms-course-visibility.php` | 📄 NUEVO archivo completo |

---

## 💡 Próximos Pasos Opcionales

1. **Dashboard de Estadísticas**: Mostrar cuántos usuarios ven cada curso
2. **Bulk Edit**: Cambiar estructuras de múltiples cursos a la vez
3. **Reporte**: Exportar matriz de visibilidad (usuario-curso-estructura)
4. **Caché**: Cachear queries de visibilidad para optimizar performance
5. **API**: Endpoint REST para consultar cursos visibles

