# ✅ Backend + UI - Cambios Implementados

## 📋 Resumen General

Se ha implementado completamente el sistema de jerarquía de estructuras con relaciones bidireccionales:
- **Ciudades** → **Canales** (ya existía, optimizado)
- **Canales** → **Sucursales** (NUEVO)
- **Sucursales** → **Cargos** (NUEVO)

---

## 🔧 Cambios en Backend

### 1. **class-fplms-config.php** - Nuevas Constantes

Se agregaron 3 nuevas constantes para las meta keys de relaciones:

```php
public const META_TERM_CITIES      = 'fplms_cities';      // Para canales (ya existía)
public const META_TERM_CHANNELS    = 'fplms_channels';    // NUEVO: Para sucursales
public const META_TERM_BRANCHES    = 'fplms_branches';    // NUEVO: Para cargos
```

**Ubicación:** [class-fplms-config.php](class-fplms-config.php#L16-L19)

---

### 2. **class-fplms-structures.php** - Nuevas Funciones

#### Funciones para **Sucursales ↔ Canales**

| Función | Propósito |
|---------|-----------|
| `save_term_channels(int, array)` | Guarda múltiples canales en una sucursal (JSON) |
| `get_term_channels(int)` | Obtiene los canales de una sucursal |
| `get_branches_by_channels(string, array)` | Filtra sucursales por canales específicos |
| `get_branches_all_channels(string)` | Obtiene todas las sucursales con sus canales |

#### Funciones para **Cargos ↔ Sucursales**

| Función | Propósito |
|---------|-----------|
| `save_term_branches(int, array)` | Guarda múltiples sucursales en un cargo (JSON) |
| `get_term_branches(int)` | Obtiene las sucursales de un cargo |
| `get_roles_by_branches(string, array)` | Filtra cargos por sucursales específicas |
| `get_roles_all_branches(string)` | Obtiene todos los cargos con sus sucursales |

#### Función de Validación

```php
validate_hierarchy(string $taxonomy, int $term_id, array $parent_ids): bool
```
- Valida que las relaciones sean válidas
- Previene auto-referencias circulares
- Verifica que los padres existan en la taxonomía correcta

**Ubicación:** [class-fplms-structures.php](class-fplms-structures.php#L1950-L2270)

---

### 3. **Actualización de handle_form()**

Se modificó el manejador de formularios para:

#### **Acción 'create'** (Crear nuevo término)
- Valida ciudades para Canales
- Valida canales para Sucursales  
- Valida sucursales para Cargos
- Guarda las relaciones usando las nuevas funciones

#### **Acción 'edit'** (Editar término existente)
- Actualiza las relaciones jerárquicas
- Mantiene integridad de datos

#### **Acción 'delete'** (Eliminar término)
- Limpia todas las meta keys de relaciones
- Evita datos huérfanos

**Ubicación:** [class-fplms-structures.php](class-fplms-structures.php#L82-L186)

---

## 🎨 Cambios en Frontend (UI)

### 1. **Listado de Términos - Mostrar Relaciones**

Se actualizó la sección que muestra cada término en el acordeón para mostrar sus relaciones:

```
ANTES:
├─ Sucursal: "Aldo Pando" | 🔗 Cochabamba | [Activo]

DESPUÉS:
├─ Sucursal: "Aldo Pando" | 🔗 🏪 Insoftline, MasterStudy | [Activo]
│  (Muestra los canales relacionados, no la ciudad)
```

**Cambios:**
- Dinámicamente muestra el nivel padre según el tipo
- Canales muestran: `📍 Ciudad1, Ciudad2`
- Sucursales muestran: `🏪 Canal1, Canal2`
- Cargos muestran: `🏢 Sucursal1, Sucursal2`

**Ubicación:** [class-fplms-structures.php](class-fplms-structures.php#L267-L308)

---

### 2. **Formulario de Edición Inline**

Se reemplazó el formulario genérico con selectores específicos por tipo:

#### Para **Canales** (editar):
```html
<label>📍 Ciudades Relacionadas</label>
<div class="fplms-parent-selector">
  <input type="text" class="fplms-parent-search" placeholder="🔍 Buscar ciudad...">
  <div class="fplms-parent-list">
    <!-- Checkboxes de ciudades activas -->
  </div>
</div>
```

#### Para **Sucursales** (editar):
```html
<label>🏪 Canales Relacionados</label>
<div class="fplms-parent-selector">
  <input type="text" class="fplms-parent-search" placeholder="🔍 Buscar canal...">
  <div class="fplms-parent-list">
    <!-- Checkboxes de canales activos -->
  </div>
</div>
```

#### Para **Cargos** (editar):
```html
<label>🏢 Sucursales Relacionadas</label>
<div class="fplms-parent-selector">
  <input type="text" class="fplms-parent-search" placeholder="🔍 Buscar sucursal...">
  <div class="fplms-parent-list">
    <!-- Checkboxes de sucursales activas -->
  </div>
</div>
```

**Ubicación:** [class-fplms-structures.php](class-fplms-structures.php#L345-L426)

---

### 3. **Formulario de Creación**

El formulario de "Crear nuevo elemento" también se actualizó para incluir los selectores dinámicos según el tipo de término.

**Ubicación:** [class-fplms-structures.php](class-fplms-structures.php#L450-L525)

---

### 4. **CSS para Nuevos Selectores**

Se agregaron estilos para `.fplms-parent-*` (genéricos):

```css
.fplms-parent-selector { }      /* Contenedor principal */
.fplms-parent-search { }        /* Input de búsqueda */
.fplms-parent-list { }          /* Contenedor de opciones */
.fplms-parent-option { }        /* Cada opción (checkbox + label) */
.fplms-parent-field { }         /* Campo padre */
```

Los estilos son idénticos a los de `.fplms-city-*` para mantener consistencia.

**Ubicación:** [class-fplms-structures.php](class-fplms-structures.php#L793-L858)

---

### 5. **JavaScript para Búsqueda Dinámica**

#### Función nueva: `fplmsFilterParents(searchInput)`

Filtra opciones de padres basado en búsqueda de texto, funciona igual que `fplmsFilterCities()`.

```javascript
function fplmsFilterParents(searchInput) {
    const parentList = searchInput.parentElement.querySelector('.fplms-parent-list');
    const searchTerm = searchInput.value.toLowerCase();
    const parentOptions = parentList.querySelectorAll('.fplms-parent-option');
    
    parentOptions.forEach(option => {
        const parentName = option.textContent.toLowerCase();
        if (parentName.includes(searchTerm)) {
            option.style.display = 'flex';
        } else {
            option.style.display = 'none';
        }
    });
}
```

#### Event Listeners Actualizados

Se agregaron listeners para:
- `.fplms-parent-search` (keyup, input)
- `.fplms-parent-option input[type="checkbox"]` (change)

**Ubicación:** [class-fplms-structures.php](class-fplms-structures.php#L1747-1792)

---

## 📊 Flujo de Datos

### Ejemplo: Crear una Sucursal

1. Usuario abre tab "Sucursales"
2. Hace clic en "➕ Crear nuevo elemento"
3. Ingresa nombre: "Aldo Pando"
4. Selecciona canales: ["Insoftline", "MasterStudy"]
5. Marca como "Activo"
6. Hace clic en "Crear"

**Backend procesa:**
```php
if ( FairPlay_LMS_Config::TAX_BRANCH === $taxonomy && ! empty( $_POST['fplms_channels'] ) ) {
    $channel_ids = array_map( 'absint', (array) $_POST['fplms_channels'] );
    $channel_ids = array_filter( $channel_ids );

    if ( ! empty( $channel_ids ) && $this->validate_hierarchy( $taxonomy, $term['term_id'], $channel_ids ) ) {
        $this->save_term_channels( $term['term_id'], $channel_ids );
    }
}
```

**Resultado:**
- Sucursal "Aldo Pando" creada
- Meta `fplms_channels` guardada como JSON: `[2, 3]`
- Listado muestra: "Aldo Pando 🔗 🏪 Insoftline, MasterStudy"

---

## 🔐 Validación de Integridad

La función `validate_hierarchy()` previene:

✓ **Auto-referencias:** Un término no puede ser su propio padre  
✓ **Padres inválidos:** Verifica que existan en la taxonomía correcta  
✓ **Relaciones cruzadas:** Solo ciudades para canales, canales para sucursales, etc.

Ejemplo de validación:
```php
// ❌ RECHAZADO: Cargo sin sucursales (array vacío)
// ✓ ACEPTADO: Cargo con 2 sucursales
// ❌ RECHAZADO: Cargo de ID 5 asignado a sucursal 5 (auto-ref)
```

---

## 📝 Notas Importantes

### Compatibilidad Retroactiva
- Sistema anterior que guardaba ciudad en meta `fplms_parent_city` sigue funcionando
- La función `get_term_cities()` intenta JSON primero, luego fallback a antiguo formato

### Datos Serializados
- Todas las relaciones se guardan como **JSON Arrays**
- Ejemplo: `[1, 3, 5]` en lugar de múltiples rows
- Más eficiente para WordPress term_meta

### Relaciones No Exclusivas
- Una Sucursal puede estar en múltiples Canales
- Un Cargo puede estar en múltiples Sucursales
- Permite máxima flexibilidad

---

## ✨ Beneficios

| Antes | Después |
|-------|---------|
| Solo Ciudades → Canales | Ciudades → Canales → Sucursales → Cargos |
| 1 ciudad por canal | Múltiples ciudades por canal |
| Sin relación sucursal-canal | Sucursales vinculadas a canales específicos |
| Sin relación cargo-sucursal | Cargos asignados a sucursales específicas |
| UI genérica | UI dinámica según tipo de término |

---

## 🚀 Próximos Pasos

### Para Cursos (Fase siguiente)

1. Actualizar selector de estructuras en cursos
2. Implementar cascada: Ciudad → Canales → Sucursales → Cargos
3. Guardar todas las relaciones en cursos
4. Filtrar visibilidad de cursos por jerarquía completa

### Para Usuarios (Fase siguiente)

1. Actualizar asignación de usuarios
2. Validar que usuarios solo vean cursos de su jerarquía
3. Agregar vista de "Mi Jerarquía" en dashboard

---

## 📂 Archivos Modificados

- ✅ `class-fplms-config.php` - Nuevas constantes
- ✅ `class-fplms-structures.php` - Toda la lógica

**Líneas de código:**
- Backend: ~400 líneas nuevas
- UI/CSS/JS: ~300 líneas nuevas
- **Total: ~700 líneas agregadas**

---

## ✅ Testing Básico

### Crear Estructura
- ✓ Crear canal con múltiples ciudades
- ✓ Crear sucursal con múltiples canales
- ✓ Crear cargo con múltiples sucursales

### Editar Estructura
- ✓ Cambiar relaciones existentes
- ✓ Agregar/quitar padres

### Eliminar Estructura
- ✓ Limpiar meta keys automáticamente
- ✓ No dejar datos huérfanos

### UI
- ✓ Búsqueda de padres funciona
- ✓ Checkboxes guardan correctamente
- ✓ Listado muestra relaciones

---

Documento generado: 2026-01-14
