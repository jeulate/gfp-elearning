# 📚 Sistema de Gestión de Curriculum con Secciones y Lecciones

## 🎯 Resumen de la Implementación

Se ha implementado un **sistema completo de gestión de curriculum** que replica la funcionalidad mostrada en la imagen de MasterStudy LMS, permitiendo:

- ✅ Crear **secciones/módulos** organizativos
- ✅ **Asignar lecciones** existentes a cada sección
- ✅ **Visualizar** el curriculum organizado por secciones
- ✅ **Gestionar** (agregar/eliminar) lecciones dentro de cada sección
- ✅ **Integración completa** con el meta `curriculum` de MasterStudy

---

## 🖼️ Comparación con la Interfaz Original

### Lo que muestra la imagen de MasterStudy:
```
Curriculum
├── span.chakra-editable__preview.chakra-z[6]rgf
│
├── 📂 Módulo 1
│   ├── 📝 Nvidia New Technologies ...
│   ├── 📝 Engine Target Audience
│   ├── ❓ Quiz: Mobile / Native Apps
│   ├── 📄 Uploaded Lesson Material
│   ├── 📝 Sample Text Lesson
│   └── ❓ Quiz: Mobile / Native Apps
│
├── 📂 Módulo 2
│   ├── 📝 Realistic Graphic on UE4
│   ├── 📝 Volta GPU for optimization
│   └── 📝 Deep Learning
│
└── ➕ New section
```

### Lo que implementamos:
```
🎓 Gestión de Curriculum: [Nombre del Curso]

📂 Sección 1: Módulo 1
   📝 Lección 1 (ID: 123) | ⏱️ 30 min | ❌
   🎥 Lección 2 (ID: 124) | ⏱️ 45 min | ❌
   
   ➕ Agregar Lecciones a esta Sección
   [☐ Lección disponible 1]
   [☐ Lección disponible 2]
   [✅ Agregar Lecciones Seleccionadas]

📂 Sección 2: Módulo 2
   📝 Lección 3 (ID: 125) | ⏱️ 60 min | ❌
   
   ➕ Agregar Lecciones a esta Sección
   ...

➕ Crear Nueva Sección
   [Título de la Sección ___________]
   [✅ Crear Sección]
```

---

## 🎨 Características de la Interfaz

### 1. **Vista Organizada por Secciones**
- Cada sección se muestra como un bloque con estilo visual claro
- Borde y sombreado distintivo
- Header con título de la sección

### 2. **Gestión de Lecciones por Sección**
- Lista visual de lecciones asignadas con:
  - 📝 Icono según tipo de lección (texto, video, slide, etc.)
  - Título de la lección
  - ID y metadatos (duración)
  - Botón para eliminar (❌)

### 3. **Asignación Dinámica**
- Cada sección tiene su propio formulario de asignación
- Grid con checkboxes de lecciones disponibles
- Las lecciones ya asignadas no aparecen como disponibles

### 4. **Creación de Secciones**
- Formulario simple al final del curriculum
- Solo requiere título de sección
- Creación instantánea

### 5. **Acciones Disponibles**
- ✅ Crear nueva sección
- ✅ Agregar múltiples lecciones a una sección
- ✅ Eliminar lección de una sección
- ✅ Eliminar sección completa (con confirmación)

---

## 🔧 Estructura Técnica

### Meta del Curriculum

El sistema guarda todo en el meta `curriculum` del curso de MasterStudy:

```php
// Estructura del curriculum
$curriculum = [
    // Sección 1
    [
        'title'     => 'Módulo 1',
        'materials' => [
            [
                'post_id' => 123,
                'title'   => 'Introducción a HTML',
            ],
            [
                'post_id' => 124,
                'title'   => 'CSS Básico',
            ],
        ],
    ],
    
    // Sección 2
    [
        'title'     => 'Módulo 2',
        'materials' => [
            [
                'post_id' => 125,
                'title'   => 'JavaScript Avanzado',
            ],
        ],
    ],
    
    // Lección suelta (sin sección) - también soportado
    [
        'title' => 'Lección independiente',
        'id'    => 126,
        'type'  => 'stm-lessons',
    ],
];
```

### Acciones Implementadas

#### 1. `create_section` - Crear Sección
```php
POST: fplms_courses_action = create_section
      section_title = "Módulo 1"
      fplms_course_id = 123
```

#### 2. `add_lessons_to_section` - Agregar Lecciones
```php
POST: fplms_courses_action = add_lessons_to_section
      section_index = 0 (índice de la sección)
      lesson_ids[] = [123, 124, 125]
      fplms_course_id = 123
```

#### 3. `delete_section` - Eliminar Sección
```php
POST: fplms_courses_action = delete_section
      section_index = 0
      fplms_course_id = 123
```

#### 4. `remove_material_from_section` - Remover Lección
```php
POST: fplms_courses_action = remove_material_from_section
      section_index = 0
      material_index = 2
      fplms_course_id = 123
```

---

## 🚀 Cómo Usar el Sistema

### Paso 1: Acceder a Gestión de Módulos

1. Ve a **FairPlay LMS → Cursos**
2. Localiza el curso deseado
3. Haz clic en **"📚 Gestionar módulos"**

### Paso 2: Crear una Sección

1. Desplázate hasta el final del curriculum
2. En el formulario **"➕ Crear Nueva Sección"**:
   - Escribe el título (ej: "Módulo 1", "Introducción", etc.)
   - Clic en **"✅ Crear Sección"**
3. La sección aparecerá inmediatamente

### Paso 3: Agregar Lecciones a la Sección

1. Dentro de la sección creada, busca **"➕ Agregar Lecciones a esta Sección"**
2. Marca las checkboxes de las lecciones que deseas agregar
3. Clic en **"✅ Agregar Lecciones Seleccionadas"**
4. Las lecciones aparecerán listadas dentro de la sección

### Paso 4: Gestionar el Contenido

- **Eliminar una lección**: Clic en el botón **❌** junto a la lección
- **Eliminar una sección**: Clic en **"🗑️ Eliminar"** en el header de la sección
  - ⚠️ Esto eliminará la sección y TODAS sus lecciones asignadas

### Paso 5: Ver el Resultado en Frontend

1. Las secciones y lecciones se mostrarán automáticamente en el curso
2. Los estudiantes verán el curriculum organizado por secciones
3. Compatible con todas las funcionalidades nativas de MasterStudy

---

## 📊 Flujos de Trabajo

### Flujo 1: Crear Curriculum desde Cero

```
1. Crear curso en MasterStudy
   ↓
2. Crear lecciones (desde "Gestionar lecciones" o MasterStudy)
   ↓
3. Ir a "Gestionar módulos"
   ↓
4. Crear sección "Módulo 1"
   ↓
5. Agregar lecciones a "Módulo 1"
   ↓
6. Crear sección "Módulo 2"
   ↓
7. Agregar lecciones a "Módulo 2"
   ↓
8. ✅ Curriculum organizado y listo
```

### Flujo 2: Reorganizar Curriculum Existente

```
1. Ir a "Gestionar módulos" del curso
   ↓
2. Ver lecciones actuales (pueden estar sueltas)
   ↓
3. Crear secciones nuevas
   ↓
4. Mover lecciones a las secciones correspondientes
   ↓
5. Eliminar lecciones sueltas o reorganizar
   ↓
6. ✅ Curriculum reorganizado
```

### Flujo 3: Agregar Contenido a Sección Existente

```
1. Crear nuevas lecciones
   ↓
2. Ir a "Gestionar módulos"
   ↓
3. Localizar la sección objetivo
   ↓
4. En "Agregar Lecciones a esta Sección":
   - Marcar las nuevas lecciones
   - Agregar
   ↓
5. ✅ Contenido actualizado
```

---

## 🎯 Casos de Uso

### Caso 1: Curso de Programación Web

```
📚 Curso: Desarrollo Web Full Stack

📂 Módulo 1: Fundamentos HTML/CSS
   📝 Introducción a HTML
   📝 Etiquetas Semánticas
   🎥 Video: Práctica HTML
   📝 Introducción a CSS
   📝 Flexbox y Grid
   
📂 Módulo 2: JavaScript Básico
   📝 Variables y Tipos de Datos
   📝 Funciones
   🎥 Video: DOM Manipulation
   ❓ Quiz: JavaScript Básico
   
📂 Módulo 3: React
   📝 Componentes
   📝 Hooks
   🎥 Video: Proyecto Final
```

### Caso 2: Curso de Idiomas

```
📚 Curso: Inglés Intermedio

📂 Unidad 1: Present Tenses
   📝 Simple Present
   📝 Present Continuous
   🎥 Video: Conversación
   ❓ Quiz: Present Tenses
   
📂 Unidad 2: Past Tenses
   📝 Simple Past
   📝 Past Continuous
   🎥 Video: Storytelling
   
📂 Proyecto Final
   📄 Instrucciones del Proyecto
   🎥 Video: Presentación Ejemplo
```

### Caso 3: Curso de Empresa

```
📚 Curso: Capacitación Empleados Nuevos

📂 Día 1: Bienvenida
   📝 Historia de la Empresa
   🎥 Video: Tour Virtual
   📝 Políticas y Procedimientos
   
📂 Día 2: Herramientas de Trabajo
   📝 Manual de Usuario - Sistema CRM
   🎥 Tutorial: Plataforma Interna
   ❓ Quiz: Herramientas
   
📂 Día 3: Práctica
   📄 Casos de Estudio
   💻 Zoom: Sesión con Mentor
```

---

## 💡 Ventajas del Sistema

### 1. **Organización Clara**
- El contenido está agrupado lógicamente
- Fácil de navegar para estudiantes
- Profesional y estructurado

### 2. **Flexibilidad Total**
- Crea tantas secciones como necesites
- Mueve lecciones entre secciones
- Reorganiza según evolucione tu curso

### 3. **Integración Nativa**
- Usa el sistema de curriculum de MasterStudy
- Compatible con todas las features de MasterStudy
- No modifica la estructura original

### 4. **Gestión Eficiente**
- Asigna múltiples lecciones a la vez
- Vista clara de qué está en cada sección
- Eliminación rápida y segura

### 5. **Experiencia Mejorada**
- Interfaz visual moderna
- Iconos intuitivos
- Confirmaciones para acciones destructivas

---

## 🔄 Integración con Sistema de Lecciones

Este sistema trabaja en conjunto con el **Sistema de Gestión de Lecciones** implementado previamente:

### Flujo Completo de Trabajo

```
1️⃣ CREAR LECCIONES
   FairPlay LMS → Cursos → [Curso] → "📖 Gestionar lecciones"
   - Crear lecciones nuevas
   - O usar lecciones existentes de MasterStudy
   
2️⃣ ORGANIZAR EN SECCIONES
   FairPlay LMS → Cursos → [Curso] → "📚 Gestionar módulos"
   - Crear secciones
   - Asignar lecciones a cada sección
   - Organizar el curriculum
   
3️⃣ RESULTADO
   - Curriculum completo y organizado
   - Visible en frontend del curso
   - Estudiantes ven el contenido estructurado
```

### Relación entre Sistemas

| Sistema | Función | Vista |
|---------|---------|-------|
| **Gestionar Lecciones** | Crear/editar lecciones individuales | Vista centrada en lecciones |
| **Gestionar Módulos** | Organizar lecciones en secciones | Vista de curriculum completo |

Ambos sistemas modifican el mismo `curriculum` meta, pero desde perspectivas diferentes.

---

## 🎨 Personalización Visual

### Iconos Según Tipo de Lección

El sistema muestra iconos automáticos:

| Tipo | Icono | Código |
|------|-------|--------|
| Texto | 📝 | `type: 'text'` |
| Video | 🎥 | `type: 'video'` |
| Presentación | 📊 | `type: 'slide'` |
| Stream | 📡 | `type: 'stream'` |
| Zoom | 💻 | `type: 'zoom'` |
| Quiz | ❓ | `post_type: 'stm-quizzes'` |
| Documento | 📄 | Por defecto |

### Estilos CSS Aplicados

```css
.curriculum-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 15px;
    padding: 15px;
}

.material-item {
    background: #f9f9f9;
    border-left: 3px solid #2271b1;
    padding: 10px;
    margin-bottom: 8px;
}

.material-item:hover {
    background: #f0f0f1;
}
```

---

## 🧪 Testing Recomendado

### Test 1: Crear Sección
```
✓ Ir a "Gestionar módulos" de un curso
✓ Crear nueva sección con título "Test Módulo 1"
✓ Verificar que aparece en el curriculum
✓ Verificar que está vacía inicialmente
```

### Test 2: Agregar Lecciones a Sección
```
✓ Tener lecciones creadas previamente
✓ En una sección, marcar 3 lecciones
✓ Agregar lecciones seleccionadas
✓ Verificar que aparecen dentro de la sección
✓ Verificar que ya no aparecen como "disponibles"
```

### Test 3: Eliminar Lección de Sección
```
✓ En una sección con lecciones, clic en ❌
✓ Confirmar eliminación
✓ Verificar que la lección desaparece
✓ Verificar que vuelve a estar "disponible"
✓ Verificar que no se eliminó la lección (solo se desasignó)
```

### Test 4: Eliminar Sección Completa
```
✓ En una sección, clic en "🗑️ Eliminar"
✓ Confirmar en el diálogo
✓ Verificar que la sección desaparece
✓ Verificar que las lecciones que tenía vuelven a estar disponibles
✓ Verificar que el meta curriculum se actualizó correctamente
```

### Test 5: Múltiples Secciones
```
✓ Crear 3 secciones diferentes
✓ Agregar lecciones a cada una
✓ Verificar que cada sección mantiene sus lecciones
✓ Verificar que las lecciones no se repiten
✓ Ver el curso en frontend y verificar organización
```

### Test 6: Compatibilidad con MasterStudy
```
✓ Crear curriculum con este sistema
✓ Abrir el curso en editor de MasterStudy
✓ Verificar que las secciones aparecen correctamente
✓ Agregar contenido desde MasterStudy
✓ Volver a FairPlay LMS y verificar sincronización
```

---

## 📝 Notas Técnicas Importantes

### Índices en el Curriculum

El sistema usa **índices numéricos** para identificar secciones y materiales:

```php
$curriculum[0]               // Primera sección
$curriculum[0]['materials'][0]  // Primer material de primera sección
$curriculum[1]               // Segunda sección
```

⚠️ **Importante**: Al eliminar elementos, se usa `array_splice()` para mantener los índices consecutivos.

### Lecciones Sueltas vs. Lecciones en Sección

El sistema diferencia entre:

**Lección en sección:**
```php
[
    'title' => 'Módulo 1',
    'materials' => [
        ['post_id' => 123, 'title' => 'Lección 1']
    ]
]
```

**Lección suelta:**
```php
[
    'title' => 'Lección Independiente',
    'id'    => 123,
    'type'  => 'stm-lessons'
]
```

### Filtrado de Lecciones Disponibles

El sistema **automáticamente excluye** lecciones ya asignadas:

```php
$assigned_lesson_ids = []; // Recopila IDs asignados

foreach ( $curriculum as $item ) {
    // Busca en secciones
    if ( isset( $item['materials'] ) ) {
        foreach ( $item['materials'] as $material ) {
            $assigned_lesson_ids[] = $material['post_id'];
        }
    }
    // Busca lecciones sueltas
    if ( isset( $item['type'] ) && $item['type'] === 'stm-lessons' ) {
        $assigned_lesson_ids[] = $item['id'];
    }
}

// Filtrar disponibles
$available_lessons = array_filter( $all_lessons, function($lesson) use ($assigned_lesson_ids) {
    return ! in_array( $lesson->ID, $assigned_lesson_ids );
});
```

---

## 🔐 Seguridad

### Verificaciones Implementadas

1. **Nonce Verification**: Todos los formularios usan `wp_nonce_field()`
2. **Capability Check**: Requiere `CAP_MANAGE_COURSES`
3. **Input Sanitization**: `sanitize_text_field()`, `absint()`
4. **Output Escaping**: `esc_html()`, `esc_attr()`, `esc_url()`
5. **Confirmation Dialogs**: JavaScript `confirm()` antes de eliminar

### Ejemplo de Seguridad en Acción

```php
// Input sanitization
$section_title = sanitize_text_field( wp_unslash( $_POST['section_title'] ?? '' ) );

// Nonce check
if ( ! wp_verify_nonce( $_POST['fplms_courses_nonce'], 'fplms_courses_save' ) ) {
    return;
}

// Capability check
if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_COURSES ) ) {
    wp_die( 'No tienes permisos...' );
}

// Output escaping
echo '<h2>' . esc_html( $section_title ) . '</h2>';
```

---

## 📚 Referencias

### Archivos Modificados

```
wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/
└── includes/
    └── class-fplms-courses.php
        ├── render_course_modules_view()    [COMPLETAMENTE REESCRITO]
        ├── handle_form()                    [NUEVAS ACCIONES]
        │   ├── create_section
        │   ├── add_lessons_to_section
        │   ├── delete_section
        │   └── remove_material_from_section
        └── get_all_lessons()                [USADO PARA LISTAR]
```

### Constantes Usadas

```php
FairPlay_LMS_Config::MS_META_CURRICULUM  // 'curriculum'
FairPlay_LMS_Config::MS_PT_LESSON        // 'stm-lessons'
FairPlay_LMS_Config::MS_PT_QUIZ          // 'stm-quizzes'
FairPlay_LMS_Config::CAP_MANAGE_COURSES  // Capability requerida
```

### Métodos Helper

```php
get_all_lessons()           // Obtiene todas las lecciones MasterStudy
get_post_meta()             // Lee curriculum del curso
update_post_meta()          // Guarda curriculum actualizado
array_splice()              // Remueve elementos manteniendo índices
array_filter()              // Filtra lecciones disponibles
```

---

## 🎓 Conclusión

El sistema de gestión de curriculum con secciones proporciona:

1. **Organización Profesional** del contenido del curso
2. **Interfaz Intuitiva** similar a MasterStudy nativo
3. **Flexibilidad Total** para crear y gestionar secciones
4. **Integración Perfecta** con el sistema de lecciones existente
5. **Compatibilidad Completa** con MasterStudy LMS

Los usuarios ahora pueden:
- ✅ Crear cursos con estructura de módulos
- ✅ Organizar lecciones en secciones temáticas
- ✅ Gestionar el curriculum de forma visual
- ✅ Ofrecer una experiencia educativa organizada

Todo esto manteniendo la compatibilidad con el ecosistema de MasterStudy y las funcionalidades nativas del LMS.

---

**Fecha de Implementación:** Enero 27, 2026  
**Versión del Plugin:** 0.8.0+  
**Desarrollador:** Juan Eulate / Insoftline  
**Integración:** MasterStudy LMS + FairPlay LMS Extension
