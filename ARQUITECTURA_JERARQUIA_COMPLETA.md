# 🏛️ Arquitectura Final - Jerarquía de Estructuras

## 📊 Estructura de Datos

### Taxonomías (WordPress Terms)

```
WordPress
├─ fplms_city (Ciudades) [8 registros]
│  └─ Meta: fplms_active = '1'/'0'
│
├─ fplms_channel (Canales/Franquicias) [10 registros]
│  ├─ Meta: fplms_active = '1'/'0'
│  └─ Meta: fplms_cities = '[1, 3]' ← Relación a ciudades
│
├─ fplms_branch (Sucursales) [6 registros]
│  ├─ Meta: fplms_active = '1'/'0'
│  └─ Meta: fplms_channels = '[2, 3]' ← Relación a canales
│
└─ fplms_job_role (Cargos) [N registros]
   ├─ Meta: fplms_active = '1'/'0'
   └─ Meta: fplms_branches = '[5, 6, 7]' ← Relación a sucursales
```

---

## 🔗 Relaciones Jerárquicas

```
┌─────────────────┐
│ CIUDAD          │
│ Santa Cruz      │ (ID: 1)
│ Cochabamba      │ (ID: 3)
└────────┬────────┘
         │
         │ fplms_cities = [1, 3]
         ▼
┌─────────────────────┐
│ CANAL               │
│ Insoftline          │ (ID: 2)
│ MasterStudy         │ (ID: 3)
└────────┬────────────┘
         │
         │ fplms_channels = [2, 3]
         ▼
┌──────────────────┐
│ SUCURSAL         │
│ Aldo Pando       │ (ID: 5)
│ Bold Aranjuez    │ (ID: 6)
└────────┬─────────┘
         │
         │ fplms_branches = [5, 6]
         ▼
┌──────────────────┐
│ CARGO            │
│ Gerente          │ (ID: 8)
│ Supervisor       │ (ID: 9)
└──────────────────┘
```

---

## 📁 Estructura de Carpetas

```
wordpress/wp-content/plugins/
└─ fairplay-lms-masterstudy-extensions/
   ├─ fairplay-lms-masterstudy-extensions.php
   └─ includes/
      ├─ class-fplms-config.php ✏️ MODIFICADO
      │  └─ META_TERM_CHANNELS
      │  └─ META_TERM_BRANCHES
      │
      ├─ class-fplms-structures.php ✏️ MODIFICADO
      │  ├─ 9 nuevas funciones
      │  ├─ UI actualizada
      │  ├─ CSS nuevo
      │  └─ JavaScript nuevo
      │
      ├─ class-fplms-courses.php
      ├─ class-fplms-users.php
      └─ ... otros archivos
```

---

## 🔄 Flujo de Datos

### 1️⃣ Crear Sucursal

```
Usuario
  ↓
form.html → fplms_structures_action = 'create'
  ↓
handle_form() 
  ├─ Validar nonce
  ├─ Leer fplms_name → "Aldo Pando"
  ├─ Leer fplms_channels[] → [2, 3]
  ├─ Validar jerarquía
  ├─ wp_insert_term() → term_id = 5
  ├─ update_term_meta(5, 'fplms_active', '1')
  └─ save_term_channels(5, [2, 3])
       └─ update_term_meta(5, 'fplms_channels', '[2, 3]')
  ↓
Redirect → render_page()
  ├─ get_terms('fplms_branch')
  ├─ get_term_channels(5) → [2, 3]
  ├─ get_term_name_by_id(2) → "Insoftline"
  ├─ get_term_name_by_id(3) → "MasterStudy"
  └─ Mostrar: "Aldo Pando 🔗 🏪 Insoftline, MasterStudy"
```

### 2️⃣ Editar Sucursal

```
Usuario → Click ✏️
  ↓
UI → Mostrar formulario inline
  ├─ Título: "Aldo Pando"
  ├─ Búsqueda: [🔍 Buscar canal...]
  ├─ get_active_terms_for_select('fplms_channel')
  ├─ get_term_channels(5) → marcar [2, 3] como checked
  └─ Botones: [Cancelar] [Guardar Cambios]
  ↓
Usuario → Busca en campo
  ↓
JavaScript → fplmsFilterParents()
  ├─ searchTerm = "master"
  ├─ Mostrar solo opciones que coincidan
  └─ "MasterStudy" visible, "Insoftline" oculto
  ↓
Usuario → Agregar/quitar checks
  ↓
Usuario → Click "Guardar Cambios"
  ↓
JavaScript → fplmsSubmitEdit()
  ├─ e.preventDefault()
  ├─ Leer checkboxes: [2, 3] + nuevos
  ├─ Crear formulario POST
  ├─ fplmsShowSuccess('✓ Guardado')
  └─ form.submit()
  ↓
Backend → handle_form() con action = 'edit'
  ├─ wp_update_term(5, 'fplms_branch', ['name' => ...])
  └─ save_term_channels(5, $channel_ids)
```

### 3️⃣ Eliminar Sucursal

```
Usuario → Click 🗑️
  ↓
Mostrar Modal:
  ├─ ¿Confirmar eliminación?
  ├─ "Aldo Pando"
  └─ [Cancelar] [Eliminar Definitivamente]
  ↓
Usuario → Click confirmar
  ↓
JavaScript → Crear formulario POST
  ├─ action = 'delete'
  ├─ term_id = 5
  ├─ taxonomy = 'fplms_branch'
  └─ form.submit()
  ↓
Backend → handle_form()
  ├─ delete_term_meta(5, 'fplms_channels')
  ├─ delete_term_meta(5, 'fplms_branches')
  ├─ delete_term_meta(5, 'fplms_cities')
  ├─ delete_term_meta(5, 'fplms_active')
  └─ wp_delete_term(5, 'fplms_branch')
```

---

## 🎯 Funciones por Caso de Uso

### Caso: "Mostrar sucursales de un canal"

```php
$channel_id = 2;  // Insoftline
$branches = $structures->get_branches_by_channels('fplms_branch', [$channel_id]);
// Retorna: [term_id=5 (Aldo Pando), term_id=6 (Bold Aranjuez)]
```

**Internamente:**
1. `get_terms('fplms_branch')` → Todas las sucursales
2. Para cada una: `get_term_channels(5)` → [2, 3]
3. Si 2 está en [2, 3] → Incluir
4. Retornar array filtrado

### Caso: "Validar que sucursal pertenece a canal"

```php
$branch_id = 5;
$channel_id = 2;

$channels = $structures->get_term_channels($branch_id);  // [2, 3]
$belongs = in_array($channel_id, $channels);  // true/false
```

### Caso: "Obtener todo la jerarquía de una sucursal"

```php
$branch_id = 5;

// Canales
$channels = $structures->get_term_channels(5);  // [2, 3]

// Ciudades (de los canales)
$cities = [];
foreach ($channels as $channel_id) {
    $channel_cities = $structures->get_term_cities($channel_id);  // [1, 3]
    $cities = array_merge($cities, $channel_cities);
}
$cities = array_unique($cities);  // [1, 3]

// Cargos (de la sucursal)
$roles = $structures->get_roles_by_branches('fplms_job_role', [$branch_id]);
```

---

## 🧠 Lógica de Búsqueda

### Búsqueda en tiempo real

```
Input: [🔍 Buscar canal...]
       ↓ user types "master"
       
JavaScript → fplmsFilterParents(searchInput)
├─ searchTerm = "master"
├─ querySelector('.fplms-parent-list')
├─ querySelectorAll('.fplms-parent-option')
└─ Para cada opción:
   ├─ option.textContent.toLowerCase() = "masterstudy"
   ├─ "masterstudy".includes("master") = true
   └─ option.style.display = 'flex'

Resultado: Solo "MasterStudy" visible
           "Insoftline" oculto
```

---

## 📊 Diagrama ER Simplificado

```
CITY (term)
├─ id (term_id)
├─ name
└─ meta: fplms_active

CHANNEL (term)
├─ id (term_id)
├─ name
├─ meta: fplms_active
└─ meta: fplms_cities [JSON array]

BRANCH (term)
├─ id (term_id)
├─ name
├─ meta: fplms_active
└─ meta: fplms_channels [JSON array]

ROLE (term)
├─ id (term_id)
├─ name
├─ meta: fplms_active
└─ meta: fplms_branches [JSON array]

Relaciones (en term_meta):
├─ CHANNEL.fplms_cities → CITY.id
├─ BRANCH.fplms_channels → CHANNEL.id
└─ ROLE.fplms_branches → BRANCH.id
```

---

## ⚡ Performance

### Complejidad

| Operación | Complejidad | Queries |
|-----------|-------------|---------|
| get_term_channels() | O(1) | 1 |
| get_branches_by_channels() | O(n) | 1 + n |
| validate_hierarchy() | O(m) | m |

- n = número de sucursales
- m = número de canales a validar

### Optimizaciones Implementadas

✅ JSON serializado (1 meta key, no N)  
✅ Array unique para evitar duplicados  
✅ Array filter para sanitización  
✅ No queries recursivas  

---

## 🔒 Seguridad

### Validaciones

```php
// 1. Integridad de jerarquía
validate_hierarchy('fplms_branch', 5, [2, 3])
  ├─ ¿5 existe? ✓
  ├─ ¿[2,3] existen en fplms_channel? ✓
  ├─ ¿5 no está en [2,3]? ✓ (no auto-ref)
  └─ return true

// 2. Sanitización
$ids = array_map('absint', $ids);      // Enteros
$ids = array_filter($ids);             // Remove nulls
$ids = array_unique($ids);             // No duplicados

// 3. Autorización
current_user_can(CAP_MANAGE_STRUCTURES) // Solo admins

// 4. CSRF
wp_verify_nonce(...) // Verificación de token
```

---

## 📚 Stack Tecnológico

**Backend:**
- PHP 7.4+
- WordPress 5.0+
- Term Meta API

**Frontend:**
- HTML5
- CSS3
- Vanilla JavaScript (sin dependencies)

**Storage:**
- MySQL: wp_terms, wp_termmeta
- Format: JSON en text field

---

## 🚀 Próximas Integraciones

```
1. Cursos
   ├─ get_branches_by_channels() → Filtrar cursos
   ├─ get_roles_by_branches() → Filtrar por cargo
   └─ Cascada: Ciudad → Canales → Sucursales → Cargos

2. Usuarios
   ├─ Validar jerarquía del usuario
   ├─ Solo ver cursos de su jerarquía
   └─ Reports por estructura

3. API
   ├─ GET /structures/hierarchy → Árbol completo
   ├─ GET /branches/{id}/channels → Canales de sucursal
   └─ GET /roles/{id}/branches → Sucursales de cargo
```

---

## 📖 Documentación Referenciada

- [QUICK_REFERENCE_JERARQUIA.md](QUICK_REFERENCE_JERARQUIA.md) - API rápida
- [IMPLEMENTACION_JERARQUIA_BACKEND_UI.md](IMPLEMENTACION_JERARQUIA_BACKEND_UI.md) - Detalles técnicos
- [STATUS_IMPLEMENTACION_COMPLETA.md](STATUS_IMPLEMENTACION_COMPLETA.md) - Estado final

---

## ✅ Checklist de Verificación

- [x] Diseño de BD
- [x] Funciones backend
- [x] Validaciones
- [x] Formularios
- [x] Búsqueda
- [x] CSS responsivo
- [x] Documentación
- [ ] Testing en staging
- [ ] Testing en producción
- [ ] Integración Cursos
- [ ] Integración Usuarios

---

Arquitectura v1.0 completada: 2026-01-14
