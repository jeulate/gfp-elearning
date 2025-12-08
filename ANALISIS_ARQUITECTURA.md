# Análisis de Arquitectura - FairPlay LMS MasterStudy Extensions

## 📋 Resumen Ejecutivo

El plugin FairPlay LMS es una extensión para MasterStudy que implementa un sistema de estructura organizacional basado en **4 niveles jerárquicos**:
1. **Ciudad** (fplms_city)
2. **Canal/Franquicia** (fplms_channel)
3. **Sucursal** (fplms_branch)
4. **Cargo** (fplms_job_role)

---

## 🏗️ Arquitectura Actual - Análisis de Clases

### 1. **class-fplms-config.php** - Configuración Centralizada
**Propósito**: Almacenar todas las constantes del sistema (taxonomías, post types, capabilities, roles, metadata)

**Elementos Clave**:
- **Taxonomías** (estructuras): TAX_CITY, TAX_CHANNEL, TAX_BRANCH, TAX_ROLE
- **Post Types Internos**: CPT_MODULE (módulos), CPT_TOPIC (temas)
- **Metadata de Usuarios**: USER_META_CITY, USER_META_CHANNEL, USER_META_BRANCH, USER_META_ROLE
- **Capabilities del Plugin**: 
  - `CAP_MANAGE_STRUCTURES` - gestionar estructuras
  - `CAP_MANAGE_USERS` - gestionar usuarios
  - `CAP_MANAGE_COURSES` - gestionar cursos
  - `CAP_VIEW_REPORTS`, `CAP_VIEW_PROGRESS`, `CAP_VIEW_CALENDAR`
- **Roles Propios**: ROLE_STUDENT, ROLE_TUTOR

---

### 2. **class-fplms-capabilities.php** - Gestión de Permisos
**Propósito**: Crear roles y asignar capabilities según la matriz de privilegios

**Métodos Principales**:
- `activate()` - Crea roles (Alumno, Tutor), asigna capabilities a administrador e instructor
- `deactivate()` - Mantiene roles/capabilities intactos al desactivar
- `get_default_capability_matrix()` - Define matriz de permisos por rol
- `sync_capabilities_to_roles()` - Sincroniza matriz de BD con roles reales

**Roles Creados**:
| Rol | Permisos |
|-----|----------|
| fplms_student | Ver progreso, calendario |
| fplms_tutor | Gestionar cursos, ver progreso |
| stm_lms_instructor | Gestionar cursos (hereda del plugin MasterStudy) |
| administrator | Todas las capabilities |

---

### 3. **class-fplms-structures.php** - Gestión de Estructuras Organizacionales
**Propósito**: Crear y gestionar los 4 niveles de estructura

**Métodos Principales**:
- `register_taxonomies()` - Registra 4 taxonomías (ciudad, canal, sucursal, cargo)
- `handle_form()` - Procesa formularios para crear/activar/desactivar términos
- `get_active_terms_for_select()` - Obtiene términos activos para dropdowns

**Flujo**:
```
Admin crea/edita estructura 
  → Valida nonce y permisos
  → Inserta término en taxonomía
  → Guarda metadata "activo" para filtrar
```

---

### 4. **class-fplms-users.php** - Gestión de Usuarios
**Propósito**: Vincular usuarios con estructuras organizacionales y gestionar su progreso

**Métodos Principales**:
- `render_user_structures_fields()` - Muestra 4 dropdowns en perfil de usuario (Ciudad, Canal, Sucursal, Cargo)
- `save_user_structures_fields()` - Guarda metadata del usuario
- `handle_caps_matrix_form()` - Procesa matriz de privilegios personalizada

**Datos Guardados**:
```php
get_user_meta(user_id, 'fplms_city')     // ID de término
get_user_meta(user_id, 'fplms_channel')  // ID de término
get_user_meta(user_id, 'fplms_branch')   // ID de término
get_user_meta(user_id, 'fplms_job_role') // ID de término
```

---

### 5. **class-fplms-courses.php** - Gestión de Cursos
**Propósito**: Crear/modificar cursos, módulos, temas y asignar instructores

**Métodos Principales**:
- `register_post_types()` - Registra CPT internos: módulos y temas
- `handle_form()` - Procesa:
  - `assign_instructor` - Asigna profesor a curso
  - `create_module` - Crea módulo dentro de curso
  - `create_topic` - Crea tema dentro de módulo
  - `save_module_topics` - Guarda temas de un módulo

**Estructura de Datos**:
```
Curso (stm-courses de MasterStudy)
  ├── Módulo 1 (fplms_module)
  │   ├── Tema 1 (fplms_topic)
  │   └── Tema 2
  └── Módulo 2
      └── Tema 3
```

**Metadatos Clave**:
- `fplms_course_id` - Vincula módulo con curso
- `fplms_module_id` - Vincula tema con módulo

---

### 6. **class-fplms-progress.php** - Servicio de Progreso
**Propósito**: Seguimiento de avance de usuario en cursos/lecciones

**Métodos Principales** (lectura de archivo requerida):
- Calcula avance % en cursos
- Registra lecciones completadas
- Genera datos para reportes

---

### 7. **class-fplms-reports.php** - Generación de Reportes
**Propósito**: Exportar datos y generar informes de uso/progreso

**Métodos Principales**:
- `handle_export()` - Procesa descarga de CSV/Excel
- Reportes por estructura (ciudad, canal, sucursal, cargo)

---

### 8. **class-fplms-admin-pages.php** - Páginas de Admin
**Propósito**: Renderizar interfaz de usuario en panel admin

**Páginas**:
- Dashboard - Resumen general (pendiente de widgets)
- Avances - Detalles por estructura (en desarrollo)
- Calendario - Programación de cursos (en desarrollo)

---

### 9. **class-fplms-admin-menu.php** - Construcción del Menú
**Propósito**: Agregar opciones de menú en admin y vincular con páginas

---

### 10. **class-fplms-plugin.php** - Bootstrap del Sistema
**Propósito**: Orquestador central que instancia todas las clases y registra hooks

**Construcción**:
```php
FairPlay_LMS_Plugin
  ├── FairPlay_LMS_Structures_Controller
  ├── FairPlay_LMS_Progress_Service
  ├── FairPlay_LMS_Users_Controller (dep: structures, progress)
  ├── FairPlay_LMS_Courses_Controller
  ├── FairPlay_LMS_Reports_Controller (dep: users, structures, progress)
  ├── FairPlay_LMS_Admin_Pages
  └── FairPlay_LMS_Admin_Menu (dep: pages, structures, users, courses, reports)
```

---

## 🎯 TU REQUISITO: Sistema de Filtrado de Cursos por Estructura

### Situación Actual
- ✅ Los usuarios tienen estructura asignada (ciudad, canal, sucursal, cargo)
- ❌ Los cursos NO están vinculados a estructuras
- ❌ NO existe filtrado de cursos por estructura del usuario

### Solución Propuesta

#### **Paso 1: Ampliar Metadata de Cursos**
Agregar a `class-fplms-config.php`:
```php
public const META_COURSE_CITIES    = 'fplms_course_cities';
public const META_COURSE_CHANNELS  = 'fplms_course_channels';
public const META_COURSE_BRANCHES  = 'fplms_course_branches';
public const META_COURSE_ROLES     = 'fplms_course_roles';
```

#### **Paso 2: Extender Interfaz de Edición de Cursos**
En `class-fplms-courses.php`:
- Agregar checkboxes multi-select en formulario de creación/edición de cursos
- Permitir seleccionar qué estructuras pueden ver el curso
- Guardar selections como post meta

#### **Paso 3: Crear Servicio de Filtrado**
Nueva clase `class-fplms-course-visibility.php`:
```php
class FairPlay_LMS_Course_Visibility_Service {
    /**
     * Devuelve cursos visibles para usuario
     */
    public function get_visible_courses_for_user($user_id) {
        // 1. Obtener estructura del usuario
        // 2. Consultar cursos donde estructura coincide
        // 3. Retornar array de IDs de cursos
    }
    
    /**
     * Verifica si usuario puede ver curso
     */
    public function can_user_see_course($user_id, $course_id) {
        // Devuelve true/false
    }
}
```

#### **Paso 4: Integrar con MasterStudy Frontend**
En `class-fplms-plugin.php`, usar hooks de MasterStudy:
```php
add_filter('stm_lms_get_courses', [$visibility_service, 'filter_courses_for_user']);
add_filter('stm_lms_course_visibility', [$visibility_service, 'check_visibility']);
```

#### **Paso 5: Dashboard/Lista de Cursos**
Mostrar solo cursos del usuario con:
- Filtro por estructura asignada al usuario
- Búsqueda y sorting

---

## 📊 Diagrama de Flujo Propuesto

```
┌─────────────────────────────────────────────────────────┐
│ Usuario Alumno Accede al Portal                         │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
        ┌────────────────────────────┐
        │ Leer metadata del usuario  │
        │ (ciudad, canal, sucursal,  │
        │  cargo)                    │
        └────────────┬───────────────┘
                     │
                     ▼
        ┌────────────────────────────┐
        │ Query: Encontrar cursos    │
        │ donde estructura del       │
        │ usuario coincida           │
        └────────────┬───────────────┘
                     │
                     ▼
        ┌────────────────────────────┐
        │ Renderizar listado de      │
        │ SOLO cursos autorizados    │
        └────────────────────────────┘
```

---

## 🔧 Implementación Recomendada

### Prioridad 1: Interfaz en Admin
1. Extender formulario en "Gestionar Cursos" con checkboxes de estructuras
2. Guardar selecciones como post meta

### Prioridad 2: Lógica de Filtrado
1. Crear servicio `FairPlay_LMS_Course_Visibility_Service`
2. Métodos para verificar visibilidad

### Prioridad 3: Frontend
1. Filtrar cursos en listados/búsqueda
2. Ocultar cursos no autorizados
3. Mostrar mensaje si no hay cursos disponibles

### Prioridad 4: Seguridad
1. Validar permisos en endpoints
2. No mostrar datos de cursos en API sin validación

---

## 📝 Notas Técnicas

- **Taxonomías**: Usadas para valores finitos y reutilizables (Ciudad, Canal, etc.)
- **Post Meta**: Usada para datos específicos de usuario/curso
- **Capabilities Matrix**: Permite permisos granulares sin tocar código
- **Inyección de Dependencias**: Las clases usan constructores para inyectar dependencias (buena práctica)

