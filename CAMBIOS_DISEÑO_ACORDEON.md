# 📋 Cambios de Diseño - Interfaz de Estructuras (Acordeón)

## 🎯 Objetivo General
Rediseñar la interfaz de gestión de estructuras (Ciudades, Canales, Sucursales, Cargos) de un formato de tabla con pestañas a un formato moderno de acordeón con mejor UX, colores y botones de acción.

---

## ✅ Cambios Implementados

### 1. **Diseño Visual - HTML**

#### ANTES:
- Navegación mediante pestañas (`nav-tab-wrapper`)
- Tabla con estructura `<table class="widefat striped">`
- Filas individuales por cada término

#### AHORA:
```html
<div class="fplms-accordion-container">
    <div class="fplms-accordion-item">
        <div class="fplms-accordion-header">
            <span class="fplms-accordion-icon">▶</span>
            <div class="fplms-accordion-title">
                📍 Ciudades <span class="fplms-accordion-count">(5)</span>
            </div>
        </div>
        <div class="fplms-accordion-body" style="display:none;">
            <!-- Contenido expandible -->
        </div>
    </div>
</div>
```

**Ventajas:**
- ✅ Mejor visualización jerárquica
- ✅ Solo una sección abierta a la vez
- ✅ Menos desorden visual
- ✅ Más intuitivo para dispositivos móviles

---

### 2. **Elementos por Estructura**

Cada término ahora se renderiza como:

```html
<div class="fplms-term-item">
    <div class="fplms-term-header">
        <div class="fplms-term-info">
            <span class="fplms-term-name">Nombre del Término</span>
            <span class="fplms-term-cities">Ciudades: Madrid, Barcelona</span>
            <span class="fplms-term-status active">✓ Activo</span>
        </div>
        <div class="fplms-term-actions">
            <button class="fplms-btn fplms-btn-toggle" title="Activar/Desactivar">⊙○</button>
            <button class="fplms-btn fplms-btn-edit" title="Editar" onclick="...">✏️</button>
            <button class="fplms-btn fplms-btn-delete" title="Eliminar" onclick="...">🗑️</button>
        </div>
    </div>
</div>
```

**Características:**
- 📍 **Emoji Icons**: Identificación visual rápida de cada sección
- 🎨 **Color-Coding**: Bordes de colores para cada tipo de estructura
  - Ciudades: 🔵 Azul (#0073aa)
  - Canales: 🟢 Verde (#00a000)
  - Sucursales: 🟠 Naranja (#ff6f00)
  - Cargos: 🟣 Púrpura (#7c3aed)
- ✓ **Status Badges**: Indica si el elemento está activo o inactivo
- 🎯 **3 Acciones**: Toggle, Edit, Delete

---

### 3. **Estilos CSS**

#### Clases principales:

| Clase | Propósito |
|-------|-----------|
| `.fplms-accordion-container` | Contenedor principal |
| `.fplms-accordion-item` | Item del acordeón (sección) |
| `.fplms-accordion-header` | Encabezado clickeable |
| `.fplms-accordion-body` | Contenido expandible |
| `.fplms-term-item` | Fila individual de término |
| `.fplms-btn` | Botón base |
| `.fplms-btn-toggle` | Verde - Activar/Desactivar |
| `.fplms-btn-edit` | Azul - Editar |
| `.fplms-btn-delete` | Rojo - Eliminar |

#### Animaciones:
```css
/* Slide Down cuando se expande */
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Fade In para modales */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Slide In para modales */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
```

#### Responsividad:
- 📱 En dispositivos ≤ 768px: Los elementos se apilan verticalmente
- 🖥️ En desktop: Layout horizontal completo

---

### 4. **JavaScript - Funcionalidades**

#### A. Acordeón Toggle
```javascript
// Abrir/cerrar secciones al hacer clic en el header
.fplms-accordion-header click → Toggle .active class
```

- Solo permite una sección abierta a la vez
- Cierra otras automáticamente al abrir una nueva
- Transiciones suaves con CSS

#### B. Multiselect Actualizado
```javascript
function updateMultiSelectDisplay(wrapper) {
    const select = wrapper.querySelector('.fplms-multiselect');
    const display = wrapper.querySelector('.fplms-multiselect-display');
    const selected = Array.from(select.options).filter(opt => opt.selected);

    display.innerHTML = selected.map(opt => 
        `<span class="fplms-multiselect-tag">${opt.textContent}</span>`
    ).join('');
}
```

- Compatible con formularios de edición
- Muestra tags visuales de ciudades seleccionadas
- Permite agregar/quitar selecciones dinámicamente

#### C. Modales de Edición y Eliminación
```javascript
function fplmsEditStructure(termId, termName, cityIds, taxonomy) {
    // Abre modal para editar término
    // Rellenan campos automáticamente
    // Validan y actualizan via POST
}

function fplmsDeleteStructure(termId, taxonomy, tab) {
    // Abre modal de confirmación
    // Muestra nombre del elemento a eliminar
    // Confirma acción antes de ejecutar DELETE
}
```

---

### 5. **Backend - Funcionalidad de Eliminación**

#### Nuevo manejador en `handle_form()`:

```php
if ( 'delete' === $action ) {
    $term_id = isset( $_POST['fplms_term_id'] ) ? absint( $_POST['fplms_term_id'] ) : 0;

    if ( $term_id ) {
        // Eliminar relaciones de ciudades
        delete_term_meta( $term_id, FairPlay_LMS_Config::META_CITY_RELATIONS );
        
        // Eliminar el término completamente
        wp_delete_term( $term_id, $taxonomy );
    }
}
```

**Características:**
- ✅ Validación de permisos (CAP_MANAGE_STRUCTURES)
- ✅ Nonce verification
- ✅ Sanitización de inputs
- ✅ Limpia metadatos relacionados
- ✅ Redirige a la pestaña correcta después

---

## 🔐 Seguridad

Todos los cambios mantienen los estándares de seguridad WordPress:

- ✅ **Nonce Verification**: `wp_verify_nonce()` en todas las acciones
- ✅ **Capability Check**: `current_user_can( CAP_MANAGE_STRUCTURES )`
- ✅ **Input Sanitization**: `sanitize_text_field()`, `absint()`
- ✅ **Output Escaping**: `esc_attr()`, `esc_html()`
- ✅ **SQL Safety**: Uso de funciones WordPress (wp_insert_term, wp_delete_term)

---

## 📱 Responsividad

### Desktop (≥ 769px)
- Diseño horizontal completo
- Botones de acción visibles siempre
- Texto de ciudades en línea

### Tablet/Mobile (≤ 768px)
- Acordeón se adapta al ancho
- Botones se apilan verticalmente
- Texto se trunca con ellipsis si es necesario
- Toque fácil para botones

---

## 🎨 Colores y Estilos

### Acordeón
- Fondo de header: Gradient #f5f5f5 → #f9f9f9
- Borde: 1px solid #ddd
- Sombra: 0 2px 4px rgba(0,0,0,0.1)
- Hover: Sombra aumentada

### Términos
- Fondo: #fff
- Borde: 1px solid #e0e0e0
- Activo: Fondo #e3f2fd + Borde izquierdo azul
- Hover: Fondo #f9f9f9, traslación 2px derecha

### Botones
- Base: 32x32px, bordes redondeados 4px
- Toggle: Verde claro background + Green text
- Edit: Azul claro background + Blue text
- Delete: Rojo claro background + Red text
- Hover: Aumenta tamaño +10% (scale 1.1)

---

## 🧪 Testing Recomendado

### Funcionalidad
- [ ] Crear nuevo término en cada sección
- [ ] Editar nombre de término
- [ ] Editar ciudades relacionadas (Canales, Sucursales, Cargos)
- [ ] Activar/desactivar términos
- [ ] Eliminar término y verificar confirmación
- [ ] Verificar redireccionamiento a pestaña correcta

### UX
- [ ] Acordeón abre/cierra suavemente
- [ ] Solo una sección abierta a la vez
- [ ] Formularios no se pierden al cambiar acordeón
- [ ] Botones responden inmediatamente

### Responsive
- [ ] Probar en móvil (< 480px)
- [ ] Probar en tablet (480px - 768px)
- [ ] Probar en desktop (> 768px)
- [ ] Verificar que no hay overflow horizontal

### Cross-browser
- [ ] Chrome/Chromium
- [ ] Firefox
- [ ] Safari
- [ ] Edge

---

## 📝 Cambios de Archivo

### Archivo: `class-fplms-structures.php`

#### Métodos Modificados:
1. **`handle_form()`** - Agregada lógica de eliminación
2. **`render_page()`** - Rediseño completo de HTML/CSS/JS

#### Secciones Reemplazadas:
- ❌ Tab navigation → ✅ Accordion container
- ❌ Table.widefat → ✅ Accordion items + Term items
- ❌ Form table → ✅ Inline form rows
- ❌ FairPlayMultiSelect class → ✅ Simplified multiselect
- ❌ Edit modal table → ✅ Modal with form groups

#### Métodos sin cambios:
- `register_taxonomies()`
- `save_multiple_cities()`
- `get_active_terms_for_select()`
- `save_hierarchy_relation()`
- Todas las relaciones de datos

---

## 🚀 Mejoras Futuras Posibles

1. **Arrastrar y Soltar**: Reordenar términos dentro de una sección
2. **Búsqueda**: Input para filtrar términos dentro del acordeón
3. **Acciones Masivas**: Seleccionar múltiples y editar activo/inactivo
4. **Export/Import**: Descargar estructura en JSON/CSV
5. **Auditoría**: Registrar quién editó/eliminó qué y cuándo
6. **Autocomplete**: En campos de ciudad relacionada

---

## ✨ Ejemplo Visual

```
┌─────────────────────────────────────────────┐
│ ▶ 📍 Ciudades (5)                           │
├─────────────────────────────────────────────┤
│                                             │
│  ┌─────────────────────────────────────┐   │
│  │ Madrid            ✓ Activo  [⊙○][✏️][🗑️] │
│  └─────────────────────────────────────┘   │
│                                             │
│  ┌─────────────────────────────────────┐   │
│  │ Barcelona         ✓ Activo  [⊙○][✏️][🗑️] │
│  └─────────────────────────────────────┘   │
│                                             │
│  ┌─────────────────────────────────────┐   │
│  │ ➕ Agregar Nueva Ciudad             │   │
│  │  Nombre: [_____________]  [GUARDAR] │   │
│  └─────────────────────────────────────┘   │
│                                             │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│ ▼ 🏪 Canales/Franquicias (3)                │
├─────────────────────────────────────────────┤
│                                             │
│  ┌─────────────────────────────────────┐   │
│  │ Premium          ✓ Activo  [⊙○][✏️][🗑️] │
│  │ Ciudades: Madrid, Barcelona         │   │
│  └─────────────────────────────────────┘   │
│                                             │
└─────────────────────────────────────────────┘
```

---

## 📚 Referencias

- [WordPress Taxonomies](https://developer.wordpress.org/plugins/taxonomy/)
- [WordPress Admin Styling](https://developer.wordpress.org/plugins/admin-menus/styling-your-pages/)
- [JavaScript Animation](https://developer.mozilla.org/en-US/docs/Web/CSS/animation)
- [Responsive Design](https://developer.mozilla.org/en-US/docs/Learn/CSS/CSS_layout/Responsive_Design)

---

**Versión**: 1.0  
**Fecha**: 2024  
**Estado**: ✅ Completado
