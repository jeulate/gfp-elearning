# Análisis de Jerarquía de Estructuras - GFP eLearnin

## 📊 Estado Actual

### Taxonomías Registradas
```
┌─────────────────────────────────────────┐
│ Ciudades (fplms_city)                   │
│ ├─ 8 registros activos                  │
│ └─ Nivel superior de la jerarquía        │
└─────────────────────────────────────────┘
         ↓ (Relación: META_TERM_CITIES)
┌─────────────────────────────────────────┐
│ Canales/Franquicias (fplms_channel)     │
│ ├─ 10 registros                         │
│ ├─ ✅ Actualmente pueden asignarse      │
│ │  múltiples ciudades                   │
│ └─ Usa: META_TERM_CITIES (JSON)         │
└─────────────────────────────────────────┘
         ↓ (Relación: NO IMPLEMENTADA)
┌─────────────────────────────────────────┐
│ Sucursales (fplms_branch)               │
│ ├─ 6 registros                          │
│ ├─ ❌ SIN RELACIÓN CON CANALES          │
│ └─ Necesita: META_TERM_CHANNELS         │
└─────────────────────────────────────────┘
         ↓ (Relación: NO IMPLEMENTADA)
┌─────────────────────────────────────────┐
│ Cargos (fplms_job_role)                 │
│ ├─ N registros                          │
│ ├─ ❌ SIN RELACIÓN CON SUCURSALES       │
│ └─ Necesita: META_TERM_BRANCHES         │
└─────────────────────────────────────────┘
```

## 🎯 Lo Que Necesita Implementarse

### 1. **Relación Canales → Sucursales**
- **Campo Meta:** `META_TERM_CHANNELS` (para sucursales)
- **Formato:** JSON array con IDs de canales
- **Funcionalidad:**
  - Asignar múltiples canales a una sucursal
  - Visualizar canales relacionados en la UI
  - Filtrar sucursales por canal
  - Cascada: Si se desactiva un canal, evaluar sucursales huérfanas

### 2. **Relación Sucursales → Cargos**
- **Campo Meta:** `META_TERM_BRANCHES` (para cargos)
- **Formato:** JSON array con IDs de sucursales
- **Funcionalidad:**
  - Asignar múltiples sucursales a un cargo
  - Visualizar sucursales relacionadas en la UI
  - Filtrar cargos por sucursal
  - Validar que un cargo tenga al menos una sucursal

### 3. **Reflejo en Asignación de Cursos**
- **Estado Actual:** Los cursos pueden asignarse a ciudades/canales
- **Necesario:**
  - Agregar filtros por sucursal y cargo
  - Cascada de selección: Elegir ciudad → ciudades disponibles → canales disponibles → sucursales → cargos
  - Actualizar `META_COURSE_CHANNELS`, `META_COURSE_BRANCHES`, `META_COURSE_ROLES`

## 📋 Cambios en Config

### Constantes a Agregar/Actualizar

```php
// Nuevas constantes para relaciones jerárquicas
public const META_TERM_CHANNELS = 'fplms_channels';  // Para sucursales
public const META_TERM_BRANCHES = 'fplms_branches';  // Para cargos

// Ya existen:
public const META_TERM_CITIES = 'fplms_cities';     // Para canales/sucursales/cargos
public const META_COURSE_CITIES = 'fplms_course_cities';
public const META_COURSE_CHANNELS = 'fplms_course_channels';
public const META_COURSE_BRANCHES = 'fplms_course_branches';
public const META_COURSE_ROLES = 'fplms_course_roles';
```

## 🔧 Funciones a Implementar

### En `class-fplms-structures.php`

#### Para Sucursales ↔ Canales
1. `save_term_channels(int $term_id, array $channel_ids): bool`
2. `get_term_channels(int $term_id): array`
3. `get_branches_by_channels(string $taxonomy, array $channel_ids): array`
4. `get_terms_all_channels(string $taxonomy): array`

#### Para Cargos ↔ Sucursales
1. `save_term_branches(int $term_id, array $branch_ids): bool`
2. `get_term_branches(int $term_id): array`
3. `get_roles_by_branches(string $taxonomy, array $branch_ids): array`
4. `get_terms_all_branches(string $taxonomy): array`

#### Helpers de Validación
1. `validate_hierarchy(string $taxonomy, int $term_id, array $parent_ids): bool`
   - Verifica que las relaciones sean válidas
   - Evita referencias circulares

## 🎨 Cambios en UI (class-fplms-structures.php)

### Formulario de Creación/Edición de Sucursales
```html
<!-- Agregar selector de Canales (multi-select con búsqueda) -->
<div class="fplms-edit-field fplms-channels-field">
  <label>Canales Relacionados</label>
  <div class="fplms-channel-selector">
    <input type="text" class="fplms-channel-search" placeholder="🔍 Buscar canal...">
    <div class="fplms-channels-list">
      <!-- Checkboxes de canales activos -->
    </div>
  </div>
</div>
```

### Formulario de Creación/Edición de Cargos
```html
<!-- Agregar selector de Sucursales (multi-select con búsqueda) -->
<div class="fplms-edit-field fplms-branches-field">
  <label>Sucursales Relacionadas</label>
  <div class="fplms-branch-selector">
    <input type="text" class="fplms-branch-search" placeholder="🔍 Buscar sucursal...">
    <div class="fplms-branches-list">
      <!-- Checkboxes de sucursales activas -->
    </div>
  </div>
</div>
```

### Vista en Listado
```
Antes:
┌─ Sucursal: "Aldo Pando" | Cochabamba | [Activo] [✏️] [🗑️]

Después:
┌─ Sucursal: "Aldo Pando"
  🔗 Canales: "Insoftline, MasterStudy"
  🔗 Ciudad: "Cochabamba"
  [Activo] [✏️] [🗑️]
```

## 📈 Orden de Implementación

### Fase 1: Backend (Funciones Base)
1. Actualizar `class-fplms-config.php` - Agregar constantes
2. Implementar funciones de Sucursales ↔ Canales
3. Implementar funciones de Cargos ↔ Sucursales
4. Actualizar manejador de formularios (handle_form)

### Fase 2: Frontend (UI/UX)
1. Actualizar HTML de formularios (crear/editar)
2. Adaptar vista en listado
3. Agregar scripts JS para multi-select

### Fase 3: Cursos
1. Actualizar selector de estructuras en cursos
2. Implementar cascada de filtros
3. Guardar relaciones correctamente

### Fase 4: Validación
1. Testing de relaciones jerárquicas
2. Testing de cascadas
3. Testing de eliminación (validar huérfanos)

## 🔄 Flujo Esperado (User Story)

### Escenario: Asignar curso a estructura específica

**Antes (Actual):**
1. Admin selecciona Ciudad
2. Admin selecciona Canal(es)
3. ✓ Curso visible para esa combinación

**Después (Nuevo):**
1. Admin selecciona Ciudad → Filtra canales disponibles
2. Admin selecciona Canal → Filtra sucursales disponibles
3. Admin selecciona Sucursal → Filtra cargos disponibles
4. Admin selecciona Cargo (opcional) → Determina visibilidad final
5. ✓ Curso visible solo para esa jerarquía específica

## ⚠️ Consideraciones Especiales

### Cascada de Desactivación
```php
// Si se desactiva un Canal:
// - Las Sucursales huérfanas (sin otro canal activo) también se desactivan
// - Los Cargos asociados a esas sucursales también se desactivan
// - Los Cursos pierden visibilidad progresivamente
```

### Validación de Integridad
```php
// No permitir:
// - Sucursal sin canales
// - Cargo sin sucursales
// - Ciclos/referencias circulares
```

### Migración de Datos
```php
// Si hay datos existentes:
// - Las Sucursales existentes deben heredar
//   los canales de su ciudad (si existe relación previa)
// - Los Cargos deben asignarse a sucursales existentes manualmente
```

## 📝 Next Steps
Especificar qué cambios deseas que implemente primero.
