# Estructura Jerárquica de Ciudades - Documentación Técnica

## 📌 Resumen Ejecutivo

Se ha implementado un sistema de **relaciones jerárquicas** entre estructuras organizacionales:
- **Ciudad** → **Canales/Franquicias** → **Sucursales** → **Cargos**

Este sistema permite:
1. Crear la misma estructura (ej: "Canal A") en diferentes ciudades sin conflictos
2. Mostrar **dinámicamente** solo las opciones de la ciudad seleccionada
3. Al asignar una ciudad a un curso, elegir si es **visible para TODOS** los de esa ciudad O **específicamente** seleccionados

---

## 🏗️ Arquitectura del Sistema

### Modelo de Dependencias

```
CIUDAD (Nivel 0)
 ├── CANAL A (Nivel 1) → parent_city = CIUDAD_ID
 │    ├── SUCURSAL 1 (Nivel 2) → parent_city = CIUDAD_ID
 │    │    ├── CARGO: Gerente → parent_city = CIUDAD_ID
 │    │    └── CARGO: Vendedor → parent_city = CIUDAD_ID
 │    └── SUCURSAL 2 (Nivel 2) → parent_city = CIUDAD_ID
 └── CANAL B (Nivel 1) → parent_city = CIUDAD_ID
```

### Almacenamiento en Base de Datos

Las relaciones se guardan en **term_meta** de WordPress:

```sql
-- Tabla: wp_termmeta
+----------+----------+---------------------------+--------------------+
| meta_id  | term_id  | meta_key                  | meta_value         |
+----------+----------+---------------------------+--------------------+
| 1        | 5        | fplms_parent_city         | 3                  |
| 2        | 6        | fplms_parent_city         | 3                  |
| 3        | 7        | fplms_parent_channel      | 5                  |
| 4        | 8        | fplms_active              | 1                  |
+----------+----------+---------------------------+--------------------+

Ejemplo:
- Term ID 3 = "Bogotá" (City, sin padre)
- Term ID 5 = "Canal A" (Channel, parent_city = 3)
- Term ID 7 = "Sucursal 1" (Branch, parent_city = 3)
```

---

## 🔧 Cambios en el Código

### 1. `class-fplms-config.php`

**Nuevas constantes agregadas:**

```php
// Meta de términos para relaciones jerárquicas
public const META_TERM_PARENT_CITY    = 'fplms_parent_city';      // Para canales, sucursales, cargos
public const META_TERM_PARENT_CHANNEL = 'fplms_parent_channel';   // Para sucursales, cargos
public const META_TERM_PARENT_BRANCH  = 'fplms_parent_branch';    // Para cargos
```

**Uso:**
- Almacenan el ID del término padre de cada estructura
- Permiten queryar rápidamente todas las subestructuras de una ciudad

---

### 2. `class-fplms-structures.php`

#### Métodos Nuevos

**`save_hierarchy_relation(int, string, int): bool`**

Guarda la relación jerárquica entre un término y su padre.

```php
// Ejemplo: Asignar Canal ID 5 a Bogotá (ID 3)
$structures->save_hierarchy_relation(5, 'city', 3);

// Almacena en BD:
// term_id=5, meta_key='fplms_parent_city', meta_value=3
```

**`get_terms_by_parent(string, string, int): array`**

Obtiene todos los términos de una taxonomía que tienen un padre específico.

```php
// Ejemplo: Obtener todos los canales de Bogotá (ID 3)
$channels = $structures->get_terms_by_parent(
    'fplms_channel',      // taxonomy
    'city',               // parent_type
    3                     // parent_term_id
);

// Devuelve array de objetos WP_Term
```

**`get_parent_term(int, string): int`**

Obtiene el ID del padre de un término.

```php
// Ejemplo: ¿A qué ciudad pertenece el canal 5?
$city_id = $structures->get_parent_term(5, 'city');
// Devuelve: 3 (ID de Bogotá)
```

**`get_active_terms_by_city(string, int): array`**

Obtiene los términos **activos** filtrados por ciudad. Ideal para dropdowns.

```php
// Ejemplo: Canales activos de Bogotá
$channels = $structures->get_active_terms_by_city(
    'fplms_channel',
    3  // Bogotá ID
);

// Devuelve: [5 => 'Canal A', 6 => 'Canal B']
```

**`is_term_related_to_city(int, int): bool`**

Verifica si un término está relacionado con una ciudad específica.

```php
// Ejemplo: ¿El canal 5 pertenece a Bogotá (3)?
if ($structures->is_term_related_to_city(5, 3)) {
    echo "Sí, Canal 5 está en Bogotá";
}
```

**`ajax_get_terms_by_city(): void`**

Endpoint AJAX para cargar dinámicamente términos filtrados. **Se llama desde JavaScript.**

Entrada (POST):
```php
$_POST['city_id']   // ID de ciudad
$_POST['taxonomy']  // Taxonomía a filtrar (channel, branch, role)
```

Salida (JSON):
```json
{
  "success": true,
  "data": {
    "5": "Canal A",
    "6": "Canal B",
    "10": "Canal C"
  }
}
```

#### Métodos Modificados

**`handle_form()`** - Ahora captura la ciudad padre al crear términos:

```php
if ( 'create' === $action ) {
    // ... crear término ...
    
    // Nuevo: Guardar relación de ciudad si viene en el form
    if (FairPlay_LMS_Config::TAX_CHANNEL === $taxonomy && !empty($_POST['fplms_parent_city'])) {
        $parent_city = absint($_POST['fplms_parent_city']);
        $this->save_hierarchy_relation($term['term_id'], 'city', $parent_city);
    }
}
```

**`render_page()`** - Ahora muestra selector de ciudad al crear nuevas estructuras:

```php
<?php if ( 'city' !== $tab ) : ?>
    <tr>
        <th><label for="fplms_parent_city">Ciudad relacionada</label></th>
        <td>
            <select name="fplms_parent_city" id="fplms_parent_city" required>
                <option value="">-- Seleccionar Ciudad --</option>
                <?php foreach ($cities as $city_id => $city_name) : ?>
                    <option value="<?php echo esc_attr($city_id); ?>">
                        <?php echo esc_html($city_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
<?php endif; ?>
```

---

### 3. `class-fplms-courses.php`

#### Método Rediseñado: `render_course_structures_view()`

**Cambios principales:**

1. **Detección de ciudad seleccionada:**
```php
$selected_city = !empty($current_structures['cities']) 
    ? reset($current_structures['cities']) 
    : 0;
```

2. **Filtrado dinámico por ciudad:**
```php
if ($selected_city) {
    $channels = $structures->get_active_terms_by_city('fplms_channel', $selected_city);
    $branches = $structures->get_active_terms_by_city('fplms_branch', $selected_city);
    $roles = $structures->get_active_terms_by_city('fplms_job_role', $selected_city);
}
```

3. **Checkboxes con data attributes:**
```html
<input type="checkbox" 
       class="fplms-city-checkbox"
       data-city-id="3"
       name="fplms_course_cities[]" 
       value="3">
```

4. **JavaScript para carga dinámica:**
```javascript
document.addEventListener('DOMContentLoaded', function() {
    const cityCheckboxes = document.querySelectorAll('.fplms-city-checkbox');
    
    cityCheckboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            if (!this.checked) return;
            
            const cityId = this.value;
            const taxonomies = ['fplms_channel', 'fplms_branch', 'fplms_job_role'];
            
            taxonomies.forEach(function(taxonomy) {
                fetch(ajaxUrl, {
                    method: 'POST',
                    body: new FormData(/* ... */)
                })
                .then(response => response.json())
                .then(data => {
                    // Actualizar fieldset con nuevas opciones
                });
            });
        });
    });
});
```

---

### 4. `class-fplms-plugin.php`

**Nuevos hooks AJAX registrados:**

```php
add_action('wp_ajax_fplms_get_terms_by_city', [$structures, 'ajax_get_terms_by_city']);
add_action('wp_ajax_nopriv_fplms_get_terms_by_city', [$structures, 'ajax_get_terms_by_city']);
```

Permiten que usuarios sin login también carguen las opciones dinámicamente.

---

## 🎯 Flujo de Uso

### Paso 1: Crear Estructuras

```
FairPlay LMS → Estructuras

1. Tab "Ciudades"
   ├─ Bogotá
   ├─ Medellín
   └─ Cali

2. Tab "Canales / Franquicias" (Nuevo: requiere seleccionar ciudad)
   ├─ Canal A (Ciudad: Bogotá)
   ├─ Canal B (Ciudad: Medellín)
   └─ Canal A (Ciudad: Medellín)  ← Mismo nombre, diferente ciudad ✓

3. Tab "Sucursales" (Nuevo: requiere seleccionar ciudad)
   ├─ Sucursal 1 (Ciudad: Bogotá)
   ├─ Sucursal 1 (Ciudad: Medellín)
   └─ Sucursal 2 (Ciudad: Bogotá)

4. Tab "Cargos" (Nuevo: requiere seleccionar ciudad)
   ├─ Gerente (Ciudad: Bogotá)
   ├─ Vendedor (Ciudad: Bogotá)
   └─ Operario (Ciudad: Medellín)
```

### Paso 2: Asignar a Cursos

```
FairPlay LMS → Cursos → [Curso X] → Estructuras

1. Usuario marca: ☑ Bogotá
   ↓ (JavaScript dispara AJAX)
   
2. Se cargan automáticamente:
   Canales: [Canal A (Bogotá)]
   Sucursales: [Sucursal 1, Sucursal 2]
   Cargos: [Gerente, Vendedor]

3. Opciones:
   A) Dejar TODOS marcados (TODOS en esa ciudad ven el curso)
   B) Seleccionar específicamente (Solo esos canales/sucursales/cargos)

4. Guardar
```

### Paso 3: Visibilidad de Cursos

```
En Frontend (próxima implementación):

Si Usuario tiene:
  City = Bogotá
  Channel = Canal A
  Branch = Sucursal 1
  Role = Vendedor

Verá el curso si:
  • Curso asignado a Ciudad: Bogotá (visible para TODOS)
  • O Curso asignado a Canal A AND Sucursal 1 AND Vendedor
```

---

## 🔒 Seguridad

### Validación y Sanitización

```php
// En ajax_get_terms_by_city()
$city_id = absint($_POST['city_id']);                    // ✓ Validado a int
$taxonomy = sanitize_text_field(wp_unslash($_POST['taxonomy'])); // ✓ Sanitizado

// Validar que taxonomía esté permitida
if (!in_array($taxonomy, $allowed_taxonomies, true)) {   // ✓ Whitelist
    wp_send_json_error('Invalid taxonomy');
}

// Respuestas seguras
wp_send_json_success($options);  // ✓ JSON escapado automáticamente
```

### CSRF Protection

- El formulario de crear estructura usa `wp_nonce_field()`
- El AJAX de carga dinámica no requiere nonce (solo lectura, sin modificar datos)

### Permisos

```php
// En render_page()
if (!current_user_can(FairPlay_LMS_Config::CAP_MANAGE_STRUCTURES)) {
    wp_die('No tienes permisos...');
}
```

---

## 📊 Ejemplo de Base de Datos

### Taxonomías y Términos

```sql
-- wp_terms (Ciudades)
| term_id | name       |
|---------|------------|
| 1       | Bogotá     |
| 2       | Medellín   |
| 3       | Cali       |

-- wp_terms (Canales)
| term_id | name       |
|---------|------------|
| 10      | Canal A    |
| 11      | Canal B    |
| 12      | Canal A    |

-- wp_termmeta (Relaciones)
| term_id | meta_key          | meta_value |
|---------|-------------------|------------|
| 10      | fplms_parent_city | 1          | ← Canal A pertenece a Bogotá
| 11      | fplms_parent_city | 2          | ← Canal B pertenece a Medellín
| 12      | fplms_parent_city | 2          | ← Canal A pertenece a Medellín
```

### Posts y Post Meta (Cursos)

```sql
-- wp_postmeta (Asignaciones de estructuras)
| post_id | meta_key              | meta_value |
|---------|----------------------|------------|
| 5       | fplms_course_cities  | [1]        | ← Curso asignado a Bogotá
| 5       | fplms_course_channels| []         | ← Todos los canales (vacío)
| 5       | fplms_course_branches| []         | ← Todas las sucursales (vacío)
| 5       | fplms_course_roles   | []         | ← Todos los cargos (vacío)
```

---

## 🚀 Ventajas del Sistema

| Característica | Antes | Ahora |
|---|---|---|
| Mismo nombre en diferentes ciudades | ❌ | ✅ |
| Filtrado dinámico sin recargar | ❌ | ✅ |
| Validación de jerarquía | ❌ | ✅ |
| Performance (solo carga datos necesarios) | - | ✅ |
| UX intuitiva | ❌ | ✅ |
| Escalabilidad | Media | Alta |

---

## 📝 Próximas Fases

### Fase 2: Lógica de Visibilidad
- Implementar filtrado de cursos según estructura del usuario
- Considerar jerarquía: si es ciudad, visible para todos sus canales

### Fase 3: Frontend
- Cargar dinámicamente estructuras en el frontend
- Mostrar solo cursos visibles según estructura del usuario

### Fase 4: Reportes
- Incluir análisis de visibilidad por estructura
- Estadísticas de acceso por ciudad/canal

---

## 🧪 Testing

### Verificar en Base de Datos

```sql
-- ¿Cuáles son todos los canales de Bogotá?
SELECT t.term_id, t.name FROM wp_terms t
JOIN wp_termmeta tm ON t.term_id = tm.term_id
WHERE tm.meta_key = 'fplms_parent_city' AND tm.meta_value = 1;

-- ¿A qué ciudad pertenece el canal 10?
SELECT meta_value FROM wp_termmeta 
WHERE term_id = 10 AND meta_key = 'fplms_parent_city';
```

### Testing en Interface

1. **Crear estructuras jerárquicas**
   - Verificar que se guarden las relaciones
   - Confirmar que mismo nombre en diferentes ciudades funciona

2. **AJAX dinámico**
   - Cambiar ciudad en el formulario de asignar estructuras
   - Verificar que se actualicen las opciones sin recargar

3. **Guardar y editar**
   - Asignar ciudad y canales a un curso
   - Editar el curso y verificar que se cargan los valores guardados

4. **Validación**
   - Intentar crear canal sin seleccionar ciudad → debe fallar
   - Verificar que solo aparecen canales de la ciudad seleccionada

---

## 📚 Referencias de API

### Public Methods

| Método | Parámetros | Retorna | Descripción |
|--------|-----------|---------|-------------|
| `save_hierarchy_relation()` | `int $term_id, string $relation_type, int $parent_term_id` | `bool` | Guarda relación |
| `get_terms_by_parent()` | `string $taxonomy, string $parent_type, int $parent_term_id` | `array` | Obtiene términos |
| `get_parent_term()` | `int $term_id, string $parent_type` | `int` | Obtiene padre |
| `get_active_terms_by_city()` | `string $taxonomy, int $city_term_id` | `array` | Términos activos |
| `is_term_related_to_city()` | `int $term_id, int $city_term_id` | `bool` | Verifica relación |
| `ajax_get_terms_by_city()` | (POST) `city_id`, `taxonomy` | JSON | AJAX endpoint |

---

**Última actualización:** Diciembre 2024
**Versión:** 1.0
**Status:** ✅ Implementado y Listo para Pruebas
