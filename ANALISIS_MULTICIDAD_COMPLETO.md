# 📊 ANÁLISIS - Sistema de Múltiples Ciudades para Canales y Cargos

**Fecha**: Diciembre 2025  
**Objetivo**: Permitir que canales/franquicias y cargos estén asignados a múltiples ciudades  
**Estado**: Análisis Completado - Listo para Implementar

---

## 🔴 Problema Actual

### Arquitectura Existente

El sistema actual usa una relación **1:1** (un cargo/canal a una ciudad):

```
Metakey en wp_termmeta:
- fplms_parent_city (almacena UN SOLO ID de ciudad)

Ejemplo:
┌─────────────────┬──────────────────────────────────┐
│ term_id (cargo) │ meta_key          │ meta_value    │
├─────────────────┼──────────────────┼───────────────┤
│ 5 (Asesor)      │ fplms_parent_city │ 1 (Bogotá)   │
└─────────────────┴──────────────────┴───────────────┘

Limitación: El cargo "Asesor" solo puede estar en Bogotá
```

### Consecuencias

❌ Un cargo no puede estar en múltiples ciudades  
❌ Un canal no puede estar en múltiples ciudades  
❌ Información duplicada si necesitas el cargo en otra ciudad  
❌ Complejidad al asignar cursos a múltiples ciudades

---

## 🟢 Solución Propuesta

### Nueva Arquitectura: 1:N (un cargo/canal a múltiples ciudades)

Cambiar de una sola ciudad a **lista de ciudades**:

```
Opción 1 - JSON serializado en meta (RECOMENDADO):
┌─────────────────┬──────────────────────────────────┐
│ term_id (cargo) │ meta_key          │ meta_value    │
├─────────────────┼──────────────────┼───────────────┤
│ 5 (Asesor)      │ fplms_cities      │ [1,2,3]      │  ← JSON
└─────────────────┴──────────────────┴───────────────┘

Almacena: serialize(['1', '2', '3']) o json_encode([1, 2, 3])

Beneficio: Una sola entrada meta, fácil de leer
```

```
Opción 2 - Múltiples filas meta (alternativa):
┌─────────────────┬──────────────────────────────────┐
│ term_id (cargo) │ meta_key          │ meta_value    │
├─────────────────┼──────────────────┼───────────────┤
│ 5 (Asesor)      │ fplms_city        │ 1 (Bogotá)   │
│ 5 (Asesor)      │ fplms_city        │ 2 (Medellín) │
│ 5 (Asesor)      │ fplms_city        │ 3 (Cali)     │
└─────────────────┴──────────────────┴───────────────┘

Beneficio: Más flexible pero requiere más queries
```

### ✅ Recomendación: Opción 1 (JSON)

**Ventajas**:
- Una sola entrada en meta table
- Fácil de serializar/deserializar
- Compatible con código existente
- Mejor rendimiento

---

## 📋 Plan de Implementación

### Fase 1: Nuevos Métodos Helper

#### 1. `save_multiple_cities()` - Reemplaza `save_hierarchy_relation()` para ciudades

```php
public function save_multiple_cities(int $term_id, array $city_ids): bool {
    if (!$term_id || empty($city_ids)) {
        return false;
    }
    
    // Sanitizar y validar IDs
    $city_ids = array_map('absint', $city_ids);
    $city_ids = array_filter($city_ids);
    
    if (empty($city_ids)) {
        return false;
    }
    
    // Guardar como JSON
    $serialized = wp_json_encode($city_ids);
    update_term_meta($term_id, FairPlay_LMS_Config::META_TERM_CITIES, $serialized);
    return true;
}
```

#### 2. `get_term_cities()` - Obtiene ciudades de un término

```php
public function get_term_cities(int $term_id): array {
    if (!$term_id) {
        return [];
    }
    
    $serialized = get_term_meta($term_id, FairPlay_LMS_Config::META_TERM_CITIES, true);
    
    if (!$serialized) {
        // Fallback a sistema antiguo (para compatibilidad)
        $old_city = $this->get_parent_term($term_id, 'city');
        return $old_city ? [$old_city] : [];
    }
    
    $city_ids = json_decode($serialized, true);
    return is_array($city_ids) ? $city_ids : [];
}
```

#### 3. `get_terms_by_cities()` - Obtiene términos en una o varias ciudades

```php
public function get_terms_by_cities(string $taxonomy, array $city_ids): array {
    if (empty($city_ids)) {
        return [];
    }
    
    $city_ids = array_map('absint', array_filter($city_ids));
    if (empty($city_ids)) {
        return [];
    }
    
    $result = [];
    $all_terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
    
    if (is_wp_error($all_terms) || empty($all_terms)) {
        return [];
    }
    
    foreach ($all_terms as $term) {
        $term_cities = $this->get_term_cities($term->term_id);
        
        // Si el término está en cualquiera de las ciudades solicitadas
        if (array_intersect($term_cities, $city_ids)) {
            $result[] = $term;
        }
    }
    
    return $result;
}
```

#### 4. `get_terms_all_cities()` - Obtiene términos y todas sus ciudades asignadas

```php
public function get_terms_all_cities(string $taxonomy): array {
    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false
    ]);
    
    if (is_wp_error($terms) || empty($terms)) {
        return [];
    }
    
    $result = [];
    foreach ($terms as $term) {
        $cities = $this->get_term_cities($term->term_id);
        $result[$term->term_id] = [
            'name' => $term->name,
            'cities' => $cities,
            'active' => get_term_meta($term->term_id, FairPlay_LMS_Config::META_ACTIVE, true)
        ];
    }
    
    return $result;
}
```

---

### Fase 2: Cambios en Formulario (Frontend)

#### Cambio 1: De Single Select a Multi-Select

**Antes**:
```html
<select name="fplms_parent_city" required>
    <option value="">-- Seleccionar Ciudad --</option>
    <option value="1">Bogotá</option>
    <option value="2">Medellín</option>
</select>
```

**Después**:
```html
<select name="fplms_cities[]" multiple required>
    <option value="">-- Seleccionar Ciudades --</option>
    <option value="1">Bogotá</option>
    <option value="2">Medellín</option>
    <option value="3">Cali</option>
</select>
```

**Cambios**:
- `name="fplms_parent_city"` → `name="fplms_cities[]"`
- Agregar atributo `multiple`
- `required` sigue aplicando

#### Cambio 2: Pre-rellenar Select Múltiple en Modal

**JavaScript actualizado**:
```javascript
function fplmsEditStructure(termId, termName, cityIds, taxonomy) {
    document.getElementById('fplms_edit_term_id').value = termId;
    document.getElementById('fplms_edit_name').value = termName;
    document.getElementById('fplms_edit_taxonomy').value = taxonomy;
    
    const citySelect = document.getElementById('fplms_edit_cities');
    if (citySelect && taxonomy !== 'fplms_city') {
        // Limpiar selección anterior
        Array.from(citySelect.options).forEach(opt => opt.selected = false);
        
        // Seleccionar ciudades del término
        if (Array.isArray(cityIds) && cityIds.length > 0) {
            cityIds.forEach(cityId => {
                const option = citySelect.querySelector(`option[value="${cityId}"]`);
                if (option) option.selected = true;
            });
        }
    }
    
    document.getElementById('fplms-edit-modal').style.display = 'block';
}
```

---

### Fase 3: Cambios en Handle Form

#### Actualizar acción 'create'

```php
if ('create' === $action) {
    $name = sanitize_text_field(wp_unslash($_POST['fplms_name'] ?? ''));
    $active = !empty($_POST['fplms_active']) ? '1' : '0';
    
    if ($name) {
        $term = wp_insert_term($name, $taxonomy);
        if (!is_wp_error($term)) {
            update_term_meta($term['term_id'], FairPlay_LMS_Config::META_ACTIVE, $active);
            
            // Guardar múltiples ciudades si viene en el formulario
            if (FairPlay_LMS_Config::TAX_CITY !== $taxonomy && !empty($_POST['fplms_cities'])) {
                $city_ids = array_map('absint', (array) $_POST['fplms_cities']);
                $city_ids = array_filter($city_ids);
                
                if (!empty($city_ids)) {
                    $this->save_multiple_cities($term['term_id'], $city_ids);
                }
            }
        }
    }
}
```

#### Actualizar acción 'edit'

```php
if ('edit' === $action) {
    $term_id = isset($_POST['fplms_term_id']) ? absint($_POST['fplms_term_id']) : 0;
    $name = sanitize_text_field(wp_unslash($_POST['fplms_name'] ?? ''));
    
    if ($term_id && $name) {
        wp_update_term($term_id, $taxonomy, ['name' => $name]);
        
        // Actualizar ciudades si no es la pestaña de ciudades
        if (FairPlay_LMS_Config::TAX_CITY !== $taxonomy && !empty($_POST['fplms_cities'])) {
            $city_ids = array_map('absint', (array) $_POST['fplms_cities']);
            $city_ids = array_filter($city_ids);
            
            if (!empty($city_ids)) {
                $this->save_multiple_cities($term_id, $city_ids);
            }
        }
    }
}
```

---

### Fase 4: Cambios en Tabla (Frontend)

#### Mostrar Múltiples Ciudades

**Antes**:
```html
<td><?php echo $city_name ? esc_html($city_name) : '<em>Sin asignar</em>'; ?></td>
```

**Después**:
```php
<?php
$city_ids = $this->get_term_cities($term->term_id);
$city_names = [];

foreach ($city_ids as $city_id) {
    $city_name = $this->get_term_name_by_id($city_id);
    if ($city_name) {
        $city_names[] = $city_name;
    }
}
?>

<td>
    <?php 
    if (!empty($city_names)) {
        echo esc_html(implode(', ', $city_names));
    } else {
        echo '<em>Sin asignar</em>';
    }
    ?>
</td>
```

#### Actualizar Modal para Pre-rellenar Múltiples

```php
<button type="button" class="button" 
    onclick="fplmsEditStructure(
        <?php echo esc_attr($term->term_id); ?>, 
        '<?php echo esc_attr($term->name); ?>', 
        <?php echo esc_attr(wp_json_encode($city_ids)); ?>, 
        '<?php echo esc_attr($current['taxonomy']); ?>'
    )">
    Editar
</button>
```

---

### Fase 5: Impacto en Sistema de Visibilidad de Cursos

#### Cómo Afecta a Cursos

**Nuevo flujo**:

```
1. Admin asigna curso a "Canal Premium"
2. Sistema busca EN QUÉ CIUDADES está "Canal Premium"
   - Encuentra: Bogotá, Medellín, Cali
3. Sistema obtiene TODAS las sucursales de esas ciudades
4. Curso visible para usuarios de esas sucursales

Beneficio: Automático y escalable
```

#### Cambios en `can_user_see_course()`

```php
public function can_user_see_course(int $user_id, int $course_id): bool {
    // ... código existente ...
    
    // NUEVO: Si el canal está en múltiples ciudades
    $course_channels = $this->get_course_channels($course_id);
    
    if (!empty($course_channels)) {
        $channel_id = $course_channels[0]; // Asumir primer canal
        
        // Obtener TODAS las ciudades donde está este canal
        $channel_cities = $this->structures->get_term_cities($channel_id);
        
        if (!empty($channel_cities)) {
            // El usuario debe estar en cualquiera de estas ciudades
            foreach ($channel_cities as $city_id) {
                if ($this->is_user_in_city($user_id, $city_id)) {
                    return true; // Usuario está en una de las ciudades
                }
            }
        }
    }
    
    return false;
}
```

---

## 📊 Matriz de Cambios

| Componente | Cambio | Impacto | Prioridad |
|-----------|--------|--------|-----------|
| `save_hierarchy_relation()` | Crear `save_multiple_cities()` | ALTO | P0 |
| `get_parent_term()` | Crear `get_term_cities()` | ALTO | P0 |
| Formulario Create | Multi-select en lugar de select | MEDIO | P1 |
| Formulario Edit | Multi-select en lugar de select | MEDIO | P1 |
| Modal JavaScript | Soporte para array de ciudades | BAJO | P1 |
| Tabla Render | Mostrar múltiples ciudades | BAJO | P2 |
| `can_user_see_course()` | Usar `get_term_cities()` | ALTO | P0 |
| BD | Migración de datos antigua | ALTO | P0 |

---

## 🗂️ Archivos a Modificar

1. **class-fplms-structures.php**
   - Agregar 4 métodos nuevos
   - Modificar `save_hierarchy_relation()` (deprecar pero no eliminar)
   - Modificar `handle_form()` para múltiples ciudades
   - Modificar `render_page()` para mostrar múltiples ciudades
   - Actualizar JavaScript

2. **class-fplms-course-visibility.php** (si existe)
   - Actualizar `can_user_see_course()` para usar `get_term_cities()`

3. **class-fplms-config.php** (si existe)
   - Agregar `META_TERM_CITIES` (nueva metakey)

---

## 🔄 Estrategia de Migración de Datos

### Paso 1: Crear Script de Migración

```php
public function migrate_single_to_multiple_cities(): void {
    // Obtener todos los términos con ciudad antigua
    $taxonomies = [
        FairPlay_LMS_Config::TAX_CHANNEL,
        FairPlay_LMS_Config::TAX_BRANCH,
        FairPlay_LMS_Config::TAX_ROLE,
    ];
    
    foreach ($taxonomies as $taxonomy) {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false
        ]);
        
        foreach ($terms as $term) {
            $old_city = get_term_meta($term->term_id, FairPlay_LMS_Config::META_TERM_PARENT_CITY, true);
            
            if ($old_city) {
                // Convertir a nuevo formato (array)
                $this->save_multiple_cities($term->term_id, [$old_city]);
            }
        }
    }
}
```

### Paso 2: Ejecutar Migración Automática

En el `activate_plugin()` hook:
```php
public function on_plugin_activation(): void {
    if (!get_option('fplms_migrated_to_multiple_cities')) {
        $this->migrate_single_to_multiple_cities();
        update_option('fplms_migrated_to_multiple_cities', '1');
    }
}
```

### Paso 3: Compatibilidad Retroactiva

- Sistema sigue leyendo `fplms_parent_city` (antiguo)
- Si existe, lo convierte a nuevo formato
- No eliminar dato antiguo (por si acaso)

---

## ✅ Beneficios de la Implementación

| Beneficio | Descripción |
|-----------|------------|
| **Flexibilidad** | Un cargo/canal puede estar en múltiples ciudades |
| **Escalabilidad** | Fácil agregar más ciudades sin cambiar código |
| **Usabilidad** | Interface multi-select más intuitiva |
| **Performance** | JSON es más rápido que múltiples queries |
| **Mantenibilidad** | Menos duplicación de datos |
| **Compatibilidad** | Retrocompatible con datos antiguos |

---

## ⚠️ Consideraciones Especiales

### Impacto en Visibilidad de Cursos

**Escenario**: Un usuario en Bogotá, un "Asesor" en Bogotá y Medellín

```
Antes:
- Asesor solo podía estar en 1 ciudad
- Usuario de Bogotá veía cursos asignados a "Asesor (Bogotá)" solamente

Después:
- Asesor está en [Bogotá, Medellín]
- Usuario de Bogotá ve cursos asignados a "Asesor" (sin restricción)
- Usuario de Medellín TAMBIÉN ve esos cursos

IMPORTANTE: Validar que esto es el comportamiento deseado
```

### Validaciones Adicionales Necesarias

- ✅ Validar que ciudades seleccionadas existan
- ✅ Validar que usuario tiene permisos para esas ciudades
- ✅ Validar que no hay ciudades duplicadas en el array
- ✅ Limpiar datos vacíos al guardar

---

## 🧪 Testing Requerido

| Escenario | Test Case |
|-----------|-----------|
| Crear cargo | Asignar a 1, 2, 3 ciudades |
| Editar cargo | Cambiar de 1 a múltiples ciudades |
| Eliminar ciudad | ¿Cargo sigue activo? |
| Tabla | ¿Muestra todas las ciudades? |
| Modal | ¿Pre-rellena todas las ciudades? |
| Curso | ¿Visible en todas las ciudades? |
| Migración | ¿Datos antiguos se migran bien? |

---

## 📝 Summary Ejecutivo

**Lo que necesitas saber**:

1. **Cambio Principal**: De 1 ciudad a múltiples ciudades por cargo/canal
2. **Método de Almacenamiento**: JSON en wp_termmeta
3. **Métodos a Crear**: 4 nuevos (save_multiple_cities, get_term_cities, etc)
4. **Cambios UI**: Multi-select en lugar de select simple
5. **Impacto**: Cursos ahora visible en todas las ciudades del término
6. **Migración**: Automática, retrocompatible

---

**Estado**: ✅ Análisis Completado  
**Siguiente**: Implementación en Fase 1-5  
**Tiempo Estimado**: 4-6 horas de desarrollo + testing
