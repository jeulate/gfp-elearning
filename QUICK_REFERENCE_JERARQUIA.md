# 🚀 Quick Reference - Jerarquía de Estructuras

## Funciones Disponibles

### Sucursales ↔ Canales

```php
// Guardar relación
$structures->save_term_channels(int $branch_id, array $channel_ids): bool

// Obtener canales de sucursal
$channels = $structures->get_term_channels(5);  // [2, 3]

// Filtrar sucursales por canal
$branches = $structures->get_branches_by_channels('fplms_branch', [2]);

// Tabla completa
$data = $structures->get_branches_all_channels('fplms_branch');
// [5 => ['name' => 'Aldo Pando', 'channels' => [2,3], 'active' => '1']]
```

### Cargos ↔ Sucursales

```php
// Guardar relación
$structures->save_term_branches(int $role_id, array $branch_ids): bool

// Obtener sucursales de cargo
$branches = $structures->get_term_branches(8);  // [5, 6]

// Filtrar cargos por sucursal
$roles = $structures->get_roles_by_branches('fplms_job_role', [5]);

// Tabla completa
$data = $structures->get_roles_all_branches('fplms_job_role');
// [8 => ['name' => 'Gerente', 'branches' => [5,6], 'active' => '1']]
```

### Validación

```php
$is_valid = $structures->validate_hierarchy(
    'fplms_branch',  // Taxonomía
    5,               // Term ID a validar
    [2, 3]           // IDs padres (canales)
);  // true/false
```

---

## Meta Keys

```php
// Constantes en FairPlay_LMS_Config
META_TERM_CHANNELS = 'fplms_channels'   // JSON array de canales [2, 3]
META_TERM_BRANCHES = 'fplms_branches'   // JSON array de sucursales [5, 6]
META_TERM_CITIES   = 'fplms_cities'     // JSON array de ciudades [1, 3]
```

---

## Base de Datos

```json
// wp_termmeta
term_id: 5
meta_key: "fplms_channels"
meta_value: "[2, 3]"

term_id: 8
meta_key: "fplms_branches"
meta_value: "[5, 6, 7]"
```

---

## UI Selectores CSS

```html
<!-- Búsqueda -->
<input class="fplms-parent-search" placeholder="🔍 Buscar...">

<!-- Opciones -->
<div class="fplms-parent-list">
  <label class="fplms-parent-option">
    <input type="checkbox" name="fplms_channels[]" value="2">
    <span>Insoftline</span>
  </label>
</div>
```

---

## JavaScript

```javascript
// Filtrar opciones
fplmsFilterParents(searchInput);

// Mostrar mensajes
fplmsShowSuccess('✓ Guardado');
fplmsShowError('⚠ Error');

// Toggle edición
fplmsToggleEdit(button);

// Enviar formulario
fplmsSubmitEdit(form, event);
```

---

## Taxonomías

```php
TAX_CITY    = 'fplms_city'
TAX_CHANNEL = 'fplms_channel'
TAX_BRANCH  = 'fplms_branch'
TAX_ROLE    = 'fplms_job_role'
```

---

## Ejemplo Completo

### Crear Sucursal

```php
// Backend
$term = wp_insert_term('Aldo Pando', 'fplms_branch');
$branch_id = $term['term_id'];

// Asignar canales
$structures->save_term_channels($branch_id, [2, 3]);

// Activar
update_term_meta($branch_id, 'fplms_active', '1');
```

### Validar en Jerarquía

```php
// Verificar si sucursal pertenece a canal
$channels = $structures->get_term_channels($branch_id);
if (in_array($channel_id, $channels)) {
    // OK: La sucursal es de este canal
}
```

### Listar Cursos por Jerarquía

```php
// Próximamente en cursos...
// Usuario en cargo X
// Cursos de sucursales del cargo X
// Canales de esas sucursales
// Ciudades de esos canales
```

---

## Errores Comunes

❌ **No validar jerarquía**
```php
// MAL
$structures->save_term_channels(999, [2, 3]);  // Term 999 no existe

// BIEN
if ($structures->validate_hierarchy('fplms_branch', 999, [2, 3])) {
    $structures->save_term_channels(999, [2, 3]);
}
```

❌ **Olvidar arrays**
```php
// MAL
$structures->save_term_channels(5, 2);  // Debe ser array

// BIEN
$structures->save_term_channels(5, [2]);
```

❌ **Pasar IDs inválidos**
```php
// MAL
$ids = [0, 2, 3, 'invalid'];

// BIEN
$ids = array_map('absint', array_filter($ids));  // [2, 3]
```

---

## Performance

✅ **Optimizado:**
- 1 meta key por relación (no N)
- JSON en lugar de tablas
- Array unique evita duplicados
- No queries recursivas

⚠️ **Considerar:**
- Si +1000 términos, usar índices
- Si +10 relaciones por término, revisar estructura

---

## Próximos Pasos

1. **Cursos:** Usar `get_branches_by_channels()` para filtrados
2. **Usuarios:** Validar permisos por jerarquía
3. **Reports:** Analizar asignaciones por estructura
4. **API:** Endpoints JSON para cascadas dinámicas

---

## Soporte

Ver documentación completa en:
- [IMPLEMENTACION_JERARQUIA_BACKEND_UI.md](IMPLEMENTACION_JERARQUIA_BACKEND_UI.md)
- [STATUS_IMPLEMENTACION_COMPLETA.md](STATUS_IMPLEMENTACION_COMPLETA.md)
