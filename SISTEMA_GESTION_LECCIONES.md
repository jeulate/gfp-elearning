# 📖 Sistema de Gestión de Lecciones MasterStudy

## Resumen de Implementación

Se ha implementado un sistema completo para gestionar lecciones de MasterStudy desde el plugin FairPlay LMS, permitiendo crear, asignar y administrar lecciones directamente vinculadas a los cursos.

---

## ✨ Características Implementadas

### 1. **Vista de Gestión de Lecciones** ✓

Se agregó una nueva vista completa para administrar lecciones por curso con las siguientes capacidades:

#### Acceso
- Desde **FairPlay LMS → Cursos**
- Botón **"📖 Gestionar lecciones"** en cada curso

#### Funcionalidades Principales
- ✅ Ver lecciones asignadas al curso
- ✅ Crear nuevas lecciones
- ✅ Asignar lecciones existentes
- ✅ Desasignar lecciones
- ✅ Editar lecciones
- ✅ Integración completa con curriculum de MasterStudy

### 2. **Creación de Lecciones** ✓

Sistema completo para crear lecciones con:

#### Campos del Formulario
```
📝 Título de la Lección * (requerido)
📄 Contenido (Editor WordPress visual)
🎨 Tipo de Lección (Texto, Video, Presentación, Stream, Zoom)
⏱️ Duración (en minutos)
👁️ Vista Previa (permitir ver sin inscripción)
```

#### Tipos de Lección Soportados
- 📝 **Texto** - Lección basada en texto
- 🎥 **Video** - Lección con video embebido
- 📊 **Presentación** - Slides o presentación
- 📡 **Stream** - Transmisión en vivo
- 💻 **Zoom** - Integración con Zoom

#### Proceso de Creación
1. Se crea como post type `stm-lessons` (MasterStudy)
2. Se guardan metadatos (tipo, duración, vista previa)
3. Se asigna automáticamente al curso
4. Se actualiza el curriculum de MasterStudy
5. Redirección automática a la vista de lecciones

### 3. **Asignación de Lecciones Existentes** ✓

#### Características
- Lista todas las lecciones disponibles no asignadas
- Selección múltiple con checkboxes
- Vista previa del contenido de cada lección
- Iconos visuales según tipo de lección
- Scroll para listas largas

#### Proceso de Asignación
1. Seleccionar lecciones deseadas
2. Hacer clic en "🔗 Asignar Lecciones Seleccionadas"
3. Se actualizan:
   - Curriculum de MasterStudy (`curriculum` meta)
   - Tracking interno (`fplms_course_lessons` meta)
4. Las lecciones aparecen en la lista de asignadas

### 4. **Gestión de Lecciones Asignadas** ✓

#### Tabla de Lecciones
Muestra información completa:

| Orden | Título | ID | Tipo | Duración | Acciones |
|-------|--------|----|----- |----------|----------|
| 1 | Introducción a HTML | 123 | 📝 Texto | 30 min | ✏️ Editar / ❌ Desasignar |

#### Acciones Disponibles
- **✏️ Editar** - Abre el editor de WordPress (nueva pestaña)
- **❌ Desasignar** - Remueve del curso sin eliminar la lección
- Confirmación antes de desasignar

### 5. **Integración con MasterStudy** ✓

#### Curriculum de MasterStudy
El sistema actualiza automáticamente el `curriculum` meta del curso:

```php
[
    [
        'title' => 'Introducción a HTML',
        'id'    => 123,
        'type'  => 'stm-lessons',
    ],
    // ... más lecciones
]
```

#### Compatibilidad
- ✅ Compatible con el editor de MasterStudy
- ✅ Las lecciones aparecen en el curso frontend
- ✅ Mantiene el orden de asignación
- ✅ No interfiere con otras funcionalidades

---

## 📁 Archivos Modificados

### 1. `class-fplms-courses.php` - Principales Cambios

#### Nuevas Acciones de Formulario
```php
// Crear lección
if ( 'create_lesson' === $action && $course_id ) {
    $this->handle_create_lesson( $course_id );
}

// Asignar lecciones existentes
if ( 'assign_lessons' === $action && $course_id ) {
    $this->handle_assign_lessons( $course_id );
}

// Desasignar lección
if ( 'unassign_lesson' === $action && $course_id ) {
    $this->unassign_lesson_from_course( $course_id, $lesson_id );
}
```

#### Nueva Vista en render_courses_page()
```php
elseif ( 'lessons' === $view && $course_id ) {
    $this->render_course_lessons_view( $course_id );
}
```

#### Nuevos Métodos Agregados

**render_course_lessons_view()** - Vista principal de lecciones
- Muestra lecciones asignadas
- Formulario de creación
- Lista de lecciones disponibles

**handle_create_lesson()** - Crea nueva lección
- Valida datos del formulario
- Crea post type `stm-lessons`
- Guarda metadatos
- Asigna al curso

**handle_assign_lessons()** - Asigna lecciones existentes
- Recibe IDs de lecciones
- Asigna cada una al curso
- Actualiza curriculum

**assign_lesson_to_course()** - Asignación individual
- Actualiza `curriculum` meta
- Actualiza `fplms_course_lessons` meta
- Evita duplicados

**unassign_lesson_from_course()** - Desasignación
- Remueve del curriculum
- Remueve del tracking interno
- Re-indexa arrays

**get_course_lessons()** - Obtiene lecciones del curso
- Lee desde `curriculum` meta
- Filtra por type `stm-lessons`
- Retorna objetos post completos

**get_all_lessons()** - Lista todas las lecciones
- Query de todos los `stm-lessons`
- Ordenadas por título
- Solo publicadas

### 2. `class-fplms-config.php` - Constantes Nuevas

```php
// Meta para lecciones asignadas a cursos
public const META_COURSE_LESSONS = 'fplms_course_lessons';

// Meta key para el curriculum de MasterStudy
public const MS_META_CURRICULUM = 'curriculum';
```

### 3. Actualización de Botones en Lista de Cursos

```php
<a href="<?php echo esc_url( $lessons_url ); ?>" class="button">
    📖 Gestionar lecciones
</a>
```

---

## 🎨 Interfaz de Usuario

### Panel Principal de Lecciones

```
┌─────────────────────────────────────────────────────────┐
│  Gestión de Lecciones: Web Coding and Apache Basics    │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ℹ️ Información sobre Lecciones                        │
│  ┌───────────────────────────────────────────────────┐ │
│  │ Las lecciones son el contenido principal de      │ │
│  │ MasterStudy LMS. Desde aquí puedes:              │ │
│  │ ✅ Crear nuevas lecciones para este curso        │ │
│  │ ✅ Asignar lecciones existentes al curso         │ │
│  │ ✅ Ver y gestionar todas las lecciones del curso │ │
│  └───────────────────────────────────────────────────┘ │
│                                                         │
│  📚 Lecciones asignadas a este curso (3)               │
│  ┌───────────────────────────────────────────────────┐ │
│  │ Orden │ Título           │ Tipo  │ Duración      │ │
│  ├───────┼──────────────────┼───────┼───────────────┤ │
│  │  1    │ Intro a HTML     │ 📝    │ 30 min   ✏️❌ │ │
│  │  2    │ CSS Básico       │ 📝    │ 45 min   ✏️❌ │ │
│  │  3    │ Video Tutorial   │ 🎥    │ 60 min   ✏️❌ │ │
│  └───────────────────────────────────────────────────┘ │
│                                                         │
│  ➕ Crear Nueva Lección                                │
│  ┌───────────────────────────────────────────────────┐ │
│  │ Título: [____________________________]            │ │
│  │ Contenido: [Editor WordPress]                     │ │
│  │ Tipo: [📝 Texto ▼]                                │ │
│  │ Duración: [30] minutos                            │ │
│  │ ☐ Lección de Vista Previa                        │ │
│  │ [➕ Crear Lección y Asignar al Curso]            │ │
│  └───────────────────────────────────────────────────┘ │
│                                                         │
│  🔗 Asignar Lecciones Existentes                       │
│  ┌───────────────────────────────────────────────────┐ │
│  │ ☐ 📝 JavaScript Básico (ID: 456)                  │ │
│  │ ☐ 🎥 Tutorial de Git (ID: 789)                    │ │
│  │ ☐ 📊 Presentación PHP (ID: 101)                   │ │
│  │ [🔗 Asignar Lecciones Seleccionadas]             │ │
│  └───────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

### Vista en Lista de Cursos

```
┌────────────────────────────────────────────────────────┐
│ Curso: Web Coding and Apache Basics                   │
│                                                        │
│ Acciones:                                              │
│ [📚 Gestionar módulos] [📖 Gestionar lecciones]       │
│ [🏢 Gestionar estructuras] [✏️ Editar curso]          │
└────────────────────────────────────────────────────────┘
```

---

## 💾 Estructura de Base de Datos

### Post Meta del Curso

```sql
-- Curriculum de MasterStudy (array serializado)
meta_key: curriculum
meta_value: a:3:{
    i:0;a:3:{
        s:5:"title";s:13:"Intro a HTML";
        s:2:"id";i:123;
        s:4:"type";s:11:"stm-lessons";
    }
    ...
}

-- Tracking interno de FairPlay (array serializado)
meta_key: fplms_course_lessons
meta_value: a:3:{i:0;i:123;i:1;i:124;i:2;i:125;}
```

### Post Meta de Lección (stm-lessons)

```sql
meta_key: type
meta_value: video

meta_key: duration
meta_value: 30

meta_key: preview
meta_value: 1
```

---

## 🔄 Flujos de Trabajo

### Flujo 1: Crear Nueva Lección

```
Usuario accede a "Gestionar lecciones"
    ↓
Llena formulario de nueva lección
    ↓
Hace clic en "Crear Lección y Asignar al Curso"
    ↓
Sistema crea post type stm-lessons
    ↓
Guarda metadatos (tipo, duración, preview)
    ↓
Asigna lección al curriculum del curso
    ↓
Guarda en tracking interno
    ↓
Redirige a vista de lecciones (con mensaje de éxito)
    ↓
Lección aparece en lista de asignadas
```

### Flujo 2: Asignar Lecciones Existentes

```
Usuario ve lista de lecciones no asignadas
    ↓
Selecciona checkboxes de lecciones deseadas
    ↓
Hace clic en "Asignar Lecciones Seleccionadas"
    ↓
Sistema itera cada lección seleccionada
    ↓
Para cada una:
  - Agrega al curriculum del curso
  - Agrega al tracking interno
  - Evita duplicados
    ↓
Redirige con mensaje de confirmación
    ↓
Lecciones aparecen en lista de asignadas
```

### Flujo 3: Desasignar Lección

```
Usuario ve lección en lista de asignadas
    ↓
Hace clic en botón "❌ Desasignar"
    ↓
Confirma acción en diálogo
    ↓
Sistema remueve lección del curriculum
    ↓
Remueve del tracking interno
    ↓
Re-indexa arrays para mantener consistencia
    ↓
Redirige a vista de lecciones
    ↓
Lección desaparece de lista de asignadas
    ↓
Lección aparece en "lecciones disponibles"
```

---

## 🧪 Testing Recomendado

### Test 1: Crear Lección Nueva
```
✓ Completar formulario con todos los campos
✓ Enviar formulario
✓ Verificar que se crea el post type stm-lessons
✓ Verificar que tiene metadatos correctos
✓ Verificar que aparece en lista de lecciones asignadas
✓ Verificar que está en el curriculum del curso
```

### Test 2: Asignar Lecciones Existentes
```
✓ Ver lecciones no asignadas
✓ Seleccionar múltiples lecciones
✓ Asignar al curso
✓ Verificar que todas aparecen en lista de asignadas
✓ Verificar que están en el curriculum
✓ Verificar que desaparecen de "disponibles"
```

### Test 3: Desasignar Lección
```
✓ Seleccionar lección asignada
✓ Hacer clic en "Desasignar"
✓ Confirmar acción
✓ Verificar que desaparece de lista de asignadas
✓ Verificar que ya no está en curriculum
✓ Verificar que aparece en "disponibles"
✓ Verificar que la lección no se eliminó (solo desasignó)
```

### Test 4: Editar Lección
```
✓ Hacer clic en "Editar" de una lección
✓ Verificar que abre editor de WordPress
✓ Modificar contenido
✓ Guardar cambios
✓ Volver a vista de lecciones
✓ Verificar que cambios se reflejan
```

### Test 5: Frontend del Curso
```
✓ Ver curso en frontend
✓ Verificar que las lecciones asignadas aparecen
✓ Verificar el orden correcto
✓ Hacer clic en una lección
✓ Verificar que el contenido se muestra correctamente
```

---

## 🚀 Uso del Sistema

### Para Administradores

#### Crear y Asignar Lecciones a un Curso

1. **Acceder a la Gestión de Lecciones**
   - Ir a **FairPlay LMS → Cursos**
   - Localizar el curso deseado
   - Hacer clic en **"📖 Gestionar lecciones"**

2. **Opción A: Crear Nueva Lección**
   - Completar el formulario:
     - Título de la lección (requerido)
     - Contenido usando el editor visual
     - Tipo de lección (Texto, Video, etc.)
     - Duración en minutos
     - Marcar si es vista previa (opcional)
   - Hacer clic en **"➕ Crear Lección y Asignar al Curso"**
   - La lección se crea y asigna automáticamente

3. **Opción B: Asignar Lecciones Existentes**
   - Revisar la lista de "Lecciones Disponibles"
   - Marcar checkboxes de las lecciones deseadas
   - Hacer clic en **"🔗 Asignar Lecciones Seleccionadas"**

4. **Gestionar Lecciones Asignadas**
   - Ver lista completa de lecciones del curso
   - Usar **"✏️ Editar"** para modificar contenido
   - Usar **"❌ Desasignar"** para remover del curso

### Para Estudiantes

Las lecciones asignadas aparecen automáticamente en el curriculum del curso en el frontend:

```
Web Coding and Apache Basics
├── 📝 Introducción a HTML (30 min)
├── 📝 CSS Básico (45 min)
├── 🎥 Video Tutorial JavaScript (60 min)
└── 📊 Presentación PHP (40 min)
```

---

## 🔍 Casos de Uso

### Caso 1: Curso Nuevo con Lecciones Personalizadas

**Escenario:** Crear un curso de programación desde cero

**Proceso:**
1. Crear el curso en MasterStudy
2. Ir a "Gestionar lecciones"
3. Crear lecciones una por una:
   - Lección 1: "Intro a HTML" (tipo: texto)
   - Lección 2: "Tutorial CSS" (tipo: video)
   - Lección 3: "JavaScript Básico" (tipo: texto)
   - Lección 4: "Proyecto Final" (tipo: presentación)
4. Cada lección se asigna automáticamente
5. Las lecciones aparecen en el orden de creación

### Caso 2: Reutilizar Lecciones en Múltiples Cursos

**Escenario:** Tengo lecciones genéricas que uso en varios cursos

**Proceso:**
1. Las lecciones ya existen en el sistema
2. Para cada curso:
   - Ir a "Gestionar lecciones"
   - En "Asignar Lecciones Existentes"
   - Seleccionar las lecciones deseadas
   - Asignar al curso
3. Las mismas lecciones pueden estar en múltiples cursos

### Caso 3: Reorganizar Contenido del Curso

**Escenario:** Quiero cambiar el orden o remover lecciones

**Proceso:**
1. Ir a "Gestionar lecciones" del curso
2. Para remover: Usar botón "Desasignar"
3. Para agregar nuevas: Usar formulario de creación o asignación
4. El orden se mantiene según la asignación

### Caso 4: Actualizar Contenido de Lección

**Escenario:** Necesito actualizar el contenido de una lección

**Proceso:**
1. Ir a "Gestionar lecciones"
2. Localizar la lección
3. Hacer clic en "✏️ Editar"
4. Se abre el editor de WordPress
5. Modificar contenido, metadatos, etc.
6. Guardar cambios
7. Los cambios se reflejan automáticamente en todos los cursos que usan esa lección

---

## 💡 Ventajas del Sistema

### 1. **Centralización**
- Todas las lecciones en un solo lugar
- Fácil de encontrar y gestionar
- No necesitas ir a múltiples secciones

### 2. **Integración Nativa**
- Usa el post type nativo de MasterStudy (`stm-lessons`)
- Compatible con todas las funcionalidades de MasterStudy
- No hay conflictos con el sistema original

### 3. **Flexibilidad**
- Crea lecciones nuevas o reutiliza existentes
- Asigna múltiples lecciones de una vez
- Fácil reorganización del contenido

### 4. **Eficiencia**
- Interfaz clara y organizada
- Proceso rápido de creación y asignación
- Vista previa del contenido antes de asignar

### 5. **Control Total**
- Editor visual completo para contenido
- Metadatos personalizables (tipo, duración, preview)
- Gestión de curriculum automática

---

## 📊 Metadatos de Lección

### Metadatos Estándar de MasterStudy

```php
// Tipo de lección
update_post_meta( $lesson_id, 'type', 'video' );
// Valores: text, video, slide, stream, zoom

// Duración en minutos
update_post_meta( $lesson_id, 'duration', '30' );

// Lección de vista previa (disponible sin inscripción)
update_post_meta( $lesson_id, 'preview', '1' );

// Video URL (si es tipo video)
update_post_meta( $lesson_id, 'video_url', 'https://youtube.com/...' );

// Otros metadatos según el tipo
```

### Metadatos Adicionales del Plugin

```php
// Relación con el curso (opcional, se maneja via curriculum)
update_post_meta( $course_id, 'fplms_course_lessons', $lesson_ids_array );

// Curriculum de MasterStudy
update_post_meta( $course_id, 'curriculum', $curriculum_array );
```

---

## 🛠️ Código de Ejemplo

### Crear Lección Programáticamente

```php
// Crear la lección
$lesson_id = wp_insert_post( [
    'post_type'    => 'stm-lessons',
    'post_title'   => 'Introducción a PHP',
    'post_content' => 'En esta lección aprenderás los fundamentos de PHP...',
    'post_status'  => 'publish',
] );

// Agregar metadatos
update_post_meta( $lesson_id, 'type', 'text' );
update_post_meta( $lesson_id, 'duration', '45' );

// Asignar al curso
$curriculum = get_post_meta( $course_id, 'curriculum', true ) ?: [];
$curriculum[] = [
    'title' => 'Introducción a PHP',
    'id'    => $lesson_id,
    'type'  => 'stm-lessons',
];
update_post_meta( $course_id, 'curriculum', $curriculum );
```

### Obtener Lecciones de un Curso

```php
$curriculum = get_post_meta( $course_id, 'curriculum', true );

foreach ( $curriculum as $item ) {
    if ( $item['type'] === 'stm-lessons' ) {
        $lesson = get_post( $item['id'] );
        echo $lesson->post_title;
    }
}
```

---

## ⚙️ Configuración Técnica

### Post Type de Lección
```
post_type: stm-lessons
taxonomies: None (por MasterStudy)
supports: title, editor, custom-fields
hierarchical: false
public: true
```

### Estructura del Curriculum
```php
[
    [
        'title' => 'Nombre de la Lección',
        'id'    => 123,              // Post ID
        'type'  => 'stm-lessons',    // Post Type
    ],
    // Puede incluir también quizzes y otros tipos
]
```

---

## 📚 Referencias

### Constantes Usadas
```php
FairPlay_LMS_Config::MS_PT_LESSON         // 'stm-lessons'
FairPlay_LMS_Config::MS_META_CURRICULUM   // 'curriculum'
FairPlay_LMS_Config::META_COURSE_LESSONS  // 'fplms_course_lessons'
```

### Acciones del Formulario
```php
'create_lesson'   // Crear nueva lección
'assign_lessons'  // Asignar lecciones existentes
'unassign_lesson' // Desasignar lección de curso
'delete_lesson'   // Eliminar lección (no implementado en UI)
```

### Vistas
```php
'lessons' // Vista principal de gestión de lecciones
```

---

## 🎓 Conclusión

El sistema de gestión de lecciones proporciona:

1. **Interfaz Unificada** para gestionar todo el contenido de un curso
2. **Integración Completa** con MasterStudy LMS
3. **Flexibilidad Total** para crear, asignar y organizar lecciones
4. **Experiencia Mejorada** para administradores
5. **Compatibilidad** con el sistema existente de módulos y temas

Todo esto hace que la gestión de contenido educativo sea más eficiente, organizada y fácil de usar.

---

**Fecha de Implementación:** Enero 27, 2026  
**Versión del Plugin:** 0.7.0+  
**Desarrollador:** Juan Eulate / Insoftline
