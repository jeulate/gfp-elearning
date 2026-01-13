# 🔧 Referencia Técnica Rápida - Acordeón

## 📍 Ubicación del Código

**Archivo Principal:**
```
wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/includes/
└── class-fplms-structures.php
```

**Métodos Principales:**
- `handle_form()` (línea ~50) - Procesa POST
- `render_page()` (línea ~160) - Renderiza UI

---

## 🎯 Estructura DOM

```html
<div class="fplms-accordion-container">
  <div class="fplms-accordion-item">
    <div class="fplms-accordion-header">
      <span class="fplms-accordion-icon">▶</span>
      <span class="fplms-accordion-title">
        📍 Ciudades <span class="fplms-accordion-count">(5)</span>
      </span>
    </div>
    <div class="fplms-accordion-body">
      <div class="fplms-terms-list">
        <div class="fplms-term-item">
          <div class="fplms-term-header">
            <div class="fplms-term-info">
              <span class="fplms-term-name">Nombre</span>
              <span class="fplms-term-cities">Ciudades</span>
              <span class="fplms-term-status">✓ Activo</span>
            </div>
            <div class="fplms-term-actions">
              <button class="fplms-btn fplms-btn-toggle">⊙○</button>
              <button class="fplms-btn fplms-btn-edit">✏️</button>
              <button class="fplms-btn fplms-btn-delete">🗑️</button>
            </div>
          </div>
        </div>
      </div>
      <div class="fplms-new-item-form">
        <form>...</form>
      </div>
    </div>
  </div>
</div>
```

---

## 🎨 Clases CSS (Rápida Referencia)

| Clase | Propósito |
|-------|-----------|
| `.fplms-accordion-container` | Wrapper principal |
| `.fplms-accordion-item` | Item expandible |
| `.fplms-accordion-header` | Clickeable, expande/colapsa |
| `.fplms-accordion-body` | Contenido (display:none por defecto) |
| `.fplms-accordion-icon` | Flecha que rota |
| `.fplms-accordion-title` | Texto del header |
| `.fplms-accordion-count` | Badge con número |
| `.fplms-term-item` | Row individual |
| `.fplms-term-header` | Layout del término |
| `.fplms-term-info` | Info (nombre, ciudades, estado) |
| `.fplms-term-name` | Nombre del término |
| `.fplms-term-cities` | Ciudades vinculadas |
| `.fplms-term-status` | Active/Inactive badge |
| `.fplms-term-actions` | Botones (toggle, edit, delete) |
| `.fplms-btn` | Botón base |
| `.fplms-btn-toggle` | Estilo toggle (verde) |
| `.fplms-btn-edit` | Estilo edit (azul) |
| `.fplms-btn-delete` | Estilo delete (rojo) |
| `.fplms-new-item-form` | Formulario crear |
| `.fplms-form-row` | Row de formulario |
| `.fplms-multiselect-wrapper` | Wrapper select |
| `.fplms-multiselect` | Select hidden |
| `.fplms-multiselect-display` | Display de tags |
| `.fplms-multiselect-tag` | Tag individual |

---

## 🔄 Flujo de POST

### Acción: CREATE
```
POST /wp-admin/admin.php
├── fplms_structures_nonce: wp_nonce
├── fplms_structures_action: "create"
├── fplms_taxonomy: "fplms_city" | "fplms_channel" | ...
├── fplms_name: "Nombre del elemento"
├── fplms_cities[]: [id1, id2, ...] (opcional)
├── fplms_active: "1" (checkbox)
└── fplms_tab: "city" | "channel" | "branch" | "role"
```

### Acción: TOGGLE_ACTIVE
```
POST /wp-admin/admin.php
├── fplms_structures_nonce: wp_nonce
├── fplms_structures_action: "toggle_active"
├── fplms_taxonomy: "fplms_city" | ...
├── fplms_term_id: 123
└── fplms_tab: "city"
```

### Acción: EDIT
```
POST /wp-admin/admin.php
├── fplms_structures_nonce: wp_nonce
├── fplms_structures_action: "edit"
├── fplms_taxonomy: "fplms_city" | ...
├── fplms_term_id: 123
├── fplms_name: "Nuevo nombre"
├── fplms_cities[]: [id1, id2, ...]
└── fplms_tab: "city"
```

### Acción: DELETE (NUEVO)
```
POST /wp-admin/admin.php
├── fplms_structures_nonce: wp_nonce
├── fplms_structures_action: "delete"
├── fplms_taxonomy: "fplms_city" | ...
├── fplms_term_id: 123
└── fplms_tab: "city"
```

---

## 🎮 Funciones JavaScript

### Acordeón
```javascript
// Toggle accordion
.fplms-accordion-header click → .fplms-accordion-item.active toggle
// Solo una abierta a la vez
```

### Multiselect
```javascript
function updateMultiSelectDisplay(wrapper)
// Actualiza display de tags cuando cambia select

function initializeMultiSelects()
// Inicializa todos los multiselects en DOM
```

### Modales
```javascript
function fplmsEditStructure(termId, termName, cityIds, taxonomy)
// Abre modal de edición

function fplmsCloseEditModal()
// Cierra modal de edición

function fplmsDeleteStructure(termId, taxonomy, tab)
// Abre modal de confirmación de delete

function fplmsConfirmDelete()
// Ejecuta la eliminación

function fplmsCloseDeleteModal()
// Cierra modal de delete
```

---

## 🎨 Colores (Hex Codes)

```css
/* Ciudades */
$color-city: #0073aa;

/* Canales */
$color-channel: #00a000;

/* Sucursales */
$color-branch: #ff6f00;

/* Cargos */
$color-role: #7c3aed;

/* Grises */
$gray-light: #f9f9f9;
$gray-medium: #f0f0f0;
$gray-border: #ddd;

/* Estados */
$color-active: #d4edda;
$color-inactive: #f8d7da;

/* Botones */
$btn-green: #e8f5e9;
$btn-blue: #e3f2fd;
$btn-red: #ffebee;
```

---

## 📐 Animaciones

```css
/* Slide Down (Body) */
@keyframes slideDown {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Fade In (Modales) */
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* Slide In (Modales) */
@keyframes slideIn {
  from { opacity: 0; transform: translateY(-50px); }
  to { opacity: 1; transform: translateY(0); }
}
```

---

## 🔐 Validaciones

### Backend (PHP)
```php
// Verificar nonce
wp_verify_nonce($_POST['fplms_structures_nonce'], 'fplms_structures_save')

// Verificar capacidad
current_user_can(FairPlay_LMS_Config::CAP_MANAGE_STRUCTURES)

// Sanitizar inputs
sanitize_text_field(wp_unslash($_POST['field']))
absint($_POST['term_id'])

// Escapar outputs
esc_attr($value)
esc_html($value)

// Validar taxonomía
in_array($taxonomy, $allowed_taxonomies, true)
```

### Frontend (JS)
```javascript
// Required attributes en inputs
<input required>

// Type="text" y "number"
<input type="text">

// Validación de POST
if (!deleteData.termId) return;
```

---

## 📊 Base de Datos

### Taxonomías
```php
FairPlay_LMS_Config::TAX_CITY      // fplms_city
FairPlay_LMS_Config::TAX_CHANNEL   // fplms_channel
FairPlay_LMS_Config::TAX_BRANCH    // fplms_branch
FairPlay_LMS_Config::TAX_ROLE      // fplms_job_role
```

### Meta Keys
```php
FairPlay_LMS_Config::META_ACTIVE               // Activo/Inactivo
FairPlay_LMS_Config::META_CITY_RELATIONS      // Relaciones ciudad
```

### Funciones WordPress Usadas
```php
wp_insert_term($name, $taxonomy)
wp_update_term($term_id, $taxonomy, $args)
wp_delete_term($term_id, $taxonomy)
get_terms($args)
get_term_meta($term_id, $meta_key, $single)
update_term_meta($term_id, $meta_key, $meta_value)
delete_term_meta($term_id, $meta_key)
```

---

## 🐛 Debugging

### Ver request POST
```php
error_log(print_r($_POST, true)); // wp-content/debug.log
```

### Ver términos
```php
$terms = get_terms(['taxonomy' => 'fplms_city', 'hide_empty' => false]);
var_dump($terms);
```

### Ver meta de término
```php
$meta = get_term_meta($term_id, FairPlay_LMS_Config::META_ACTIVE, true);
var_dump($meta); // '1' o '0' o empty
```

### Ver respuesta AJAX
```javascript
console.log('POST:', {
    action: 'delete',
    term_id: termId,
    taxonomy: taxonomy
});
```

---

## 🚀 Performance Tips

1. **CSS**: Usa `transform` y `opacity` (GPU-acelerado)
2. **JS**: Event delegation en lugar de attach listeners individuales
3. **HTML**: Minimizar profundidad de nesting
4. **Imágenes**: Usar emojis (no requieren assets)
5. **Modal**: Crear una sola vez, reutilizar

---

## 📱 Responsive Breakpoints

```css
/* Desktop */
@media (min-width: 769px) {
  .fplms-term-header { flex-direction: row; }
  .fplms-term-actions { justify-content: flex-end; }
}

/* Tablet/Mobile */
@media (max-width: 768px) {
  .fplms-term-header { flex-direction: column; }
  .fplms-term-actions { width: 100%; }
}
```

---

## 🔍 Selectores Útiles

```javascript
// Acordeón
document.querySelectorAll('.fplms-accordion-item')
document.querySelectorAll('.fplms-accordion-header')

// Términos
document.querySelectorAll('.fplms-term-item')
element.querySelector('.fplms-term-name')

// Modales
document.getElementById('fplms-edit-modal')
document.getElementById('fplms-delete-modal')

// Formularios
form.querySelector('[name="fplms_name"]')
form.querySelector('[name="fplms_cities[]"]')
```

---

## ✅ Checklist para Cambios Futuros

Si necesitas agregar/cambiar algo:

- [ ] ¿Añadiste nonce validation?
- [ ] ¿Añadiste sanitización de inputs?
- [ ] ¿Escapaste outputs?
- [ ] ¿Verificaste permisos?
- [ ] ¿Es responsive?
- [ ] ¿Testeaste en móvil?
- [ ] ¿Agregaste feedback visual?
- [ ] ¿Documentaste los cambios?
- [ ] ¿Validaste en cross-browser?

---

## 📚 Recursos WordPress

- [Taxonomies API](https://developer.wordpress.org/plugins/taxonomy/)
- [Hooks & Filters](https://developer.wordpress.org/plugins/hooks/)
- [Admin Pages](https://developer.wordpress.org/plugins/admin-menus/)
- [Security](https://developer.wordpress.org/plugins/security/)
- [Data Sanitization](https://developer.wordpress.org/plugins/security/sanitizing-input/)
- [Data Escaping](https://developer.wordpress.org/plugins/security/escaping-output/)

---

**Última actualización**: 2024  
**Versión**: 1.0  
**Mantenedor**: [Tu nombre/equipo]
