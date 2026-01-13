# 📊 Análisis y Solución: Visualizar Estructura en Cursos

## 🎯 Objetivo

Hacer visible la estructura (Ciudades, Canales, Sucursales, Cargos) en cada curso creado, y cuando se asigne un nuevo curso a una estructura, que se muestre sin inconvenientes.

---

## 🔍 Análisis de la Situación Actual

### 1. Estructura de Datos Existente

El plugin utiliza un sistema jerárquico de estructuras:

```
CIUDAD (Nivel 0)
 ├── CANAL (Nivel 1) → parent_city = CIUDAD_ID
 │    ├── SUCURSAL (Nivel 2) → parent_city = CIUDAD_ID
 │    │    └── CARGO (Nivel 3) → parent_city = CIUDAD_ID
```

**Almacenamiento:**
- **Taxonomías internas**: `fplms_city`, `fplms_channel`, `fplms_branch`, `fplms_job_role`
- **Meta de términos**: Relaciones jerárquicas en `term_meta`
- **Meta de usuarios**: IDs de estructuras asignadas
- **Meta de posts (cursos)**: Arrays de IDs de estructuras

### 2. Guardado de Estructuras en Cursos

Los cursos almacenan la asignación de estructuras usando `post_meta`:

```php
// Meta guardadas en cada curso:
fplms_course_cities   → array( ID_CIUDAD1, ID_CIUDAD2, ... )
fplms_course_channels → array( ID_CANAL1, ID_CANAL2, ... )
fplms_course_branches → array( ID_SUCURSAL1, ID_SUCURSAL2, ... )
fplms_course_roles    → array( ID_CARGO1, ID_CARGO2, ... )
```

**Método actual:**
```php
// save_course_structures() en class-fplms-courses.php
update_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_CITIES, $cities );
update_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_CHANNELS, $channels );
update_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_BRANCHES, $branches );
update_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_ROLES, $roles );
```

### 3. Visibilidad en Frontend

El servicio `FairPlay_LMS_Course_Visibility_Service` controla qué cursos ve cada usuario:

```php
can_user_see_course( $user_id, $course_id )
  ↓
get_user_structures( $user_id ) → [ 'city' => X, 'channel' => Y, ... ]
get_course_structures( $course_id ) → [ 'cities' => [...], 'channels' => [...], ... ]
  ↓
structures_match( $user_structures, $course_structures )
```

### 4. Problema Identificado

**❌ No hay visualización de las estructuras asignadas en:**

1. **Listado de cursos**: En `render_course_list_view()`, se muestran ID y profesor, pero NO las estructuras asignadas
2. **Edición de cursos**: La interfaz de asignación de estructuras existe, pero:
   - Solo usa checkboxes estáticos
   - No aplica filtrado dinámico cuando se crea un curso nuevo
   - Los selects de canales, sucursales y cargos no se cargan dinámicamente según la ciudad seleccionada

3. **Compatibilidad con MasterStudy**: 
   - El plugin usa taxonomías internas (`fplms_*`)
   - MasterStudy usa `stm_lms_course_category` para categorías nativas
   - No hay integración entre ambos sistemas

---

## ✅ Solución Propuesta

### Paso 1: Visualizar Estructuras en Listado de Cursos

**Cambio en `render_course_list_view()`:**

Agregar columna que muestre las estructuras asignadas a cada curso:

```php
<tr>
    <td><?php echo esc_html( get_the_title( $course ) ); ?></td>
    <td><?php echo esc_html( $course->ID ); ?></td>
    <td><?php echo esc_html( $teacher_name ); ?></td>
    
    <!-- NUEVA COLUMNA: Estructuras asignadas -->
    <td>
        <?php 
        $structures = $this->get_course_structures( $course->ID );
        echo $this->format_course_structures_display( $structures );
        ?>
    </td>
    
    <td><!-- Botones de acción --></td>
</tr>
```

**Método nuevo `format_course_structures_display()`:**

```php
private function format_course_structures_display( array $structures ): string {
    $display = [];
    
    // Ciudades
    if ( ! empty( $structures['cities'] ) ) {
        $city_names = $this->get_term_names_by_ids( $structures['cities'] );
        $display[] = '📍 ' . implode( ', ', $city_names );
    }
    
    // Canales
    if ( ! empty( $structures['channels'] ) ) {
        $channel_names = $this->get_term_names_by_ids( $structures['channels'] );
        $display[] = '🏪 ' . implode( ', ', $channel_names );
    }
    
    // Sucursales
    if ( ! empty( $structures['branches'] ) ) {
        $branch_names = $this->get_term_names_by_ids( $structures['branches'] );
        $display[] = '🏢 ' . implode( ', ', $branch_names );
    }
    
    // Cargos
    if ( ! empty( $structures['roles'] ) ) {
        $role_names = $this->get_term_names_by_ids( $structures['roles'] );
        $display[] = '👔 ' . implode( ', ', $role_names );
    }
    
    if ( empty( $display ) ) {
        return '<em>Sin restricción (visible para todos)</em>';
    }
    
    return implode( '<br>', $display );
}

private function get_term_names_by_ids( array $term_ids ): array {
    $names = [];
    foreach ( $term_ids as $term_id ) {
        $term = get_term( $term_id );
        if ( $term && ! is_wp_error( $term ) ) {
            $names[] = $term->name;
        }
    }
    return $names;
}
```

### Paso 2: Mejorar Filtrado Dinámico en Asignación de Estructuras

**Problema actual:**
- Los selects de canales, sucursales y cargos solo se cargan cuando se selecciona una ciudad
- El JavaScript que manejaba esto parece incompleto

**Solución:**

Mejorar el método `render_course_structures_view()` para que:

1. Al cargar, detecte si ya hay ciudades seleccionadas y cargue sus estructuras relacionadas
2. Al cambiar ciudades, actualice dinámicamente los selects disponibles
3. Use AJAX correctamente con validación de nonce

```php
// Mejorar JavaScript existente:
document.addEventListener('DOMContentLoaded', function() {
    const ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
    const nonce = '<?php echo wp_create_nonce( 'fplms_get_terms' ); ?>';
    const cityCheckboxes = document.querySelectorAll('.fplms-city-checkbox');

    function updateStructuresForCity(cityId) {
        if (!cityId) return;

        const taxonomies = ['<?php echo FairPlay_LMS_Config::TAX_CHANNEL; ?>', 
                          '<?php echo FairPlay_LMS_Config::TAX_BRANCH; ?>', 
                          '<?php echo FairPlay_LMS_Config::TAX_ROLE; ?>'];
        const fieldsetIds = ['fplms_channels_fieldset', 'fplms_branches_fieldset', 'fplms_roles_fieldset'];

        taxonomies.forEach(function(taxonomy, index) {
            const formData = new FormData();
            formData.append('action', 'fplms_get_terms_by_city');
            formData.append('city_id', cityId);
            formData.append('taxonomy', taxonomy);
            formData.append('nonce', nonce);

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const fieldset = document.getElementById(fieldsetIds[index]);
                    let html = '';

                    for (const [termId, termName] of Object.entries(data.data)) {
                        if (termId === '') continue;
                        const inputName = 'fplms_course_' + (taxonomy === '<?php echo FairPlay_LMS_Config::TAX_CHANNEL; ?>' ? 'channels' : 
                                                            taxonomy === '<?php echo FairPlay_LMS_Config::TAX_BRANCH; ?>' ? 'branches' : 'roles') + '[]';
                        html += '<label><input type="checkbox" name="' + inputName + '" value="' + termId + '"> ' + termName + '</label><br>';
                    }

                    if (html === '') {
                        html = '<p><em>No hay opciones disponibles para esta ciudad.</em></p>';
                    }

                    fieldset.innerHTML = html;
                } else {
                    console.error('Error:', data.data.message);
                }
            })
            .catch(error => console.error('Error:', error));
        });
    }

    // Event listeners para cambios
    cityCheckboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                updateStructuresForCity(this.value);
            }
        });
    });

    // Al cargar, si hay ciudades seleccionadas, cargar sus estructuras
    const selectedCities = Array.from(cityCheckboxes).filter(cb => cb.checked);
    if (selectedCities.length > 0) {
        updateStructuresForCity(selectedCities[0].value);
    }
});
```

### Paso 3: Evitar Conflictos con MasterStudy

**Análisis:**
- MasterStudy usa `stm_lms_course_category` para sus categorías nativas
- Nuestro sistema usa `fplms_*` taxonomías internas
- No hay conflicto directo, pero sí falta integración

**Solución:**

1. **Mantener separadas las taxonomías**:
   - `fplms_*` → Uso interno del plugin FairPlay (visibilidad por estructura)
   - `stm_lms_course_category` → Categorías nativas de MasterStudy (archivos, organización)

2. **Permitir asignación a ambas**:
   - Un curso puede tener categorías MasterStudy Y estructuras FairPlay
   - Las estructuras FairPlay controlan la visibilidad
   - Las categorías MasterStudy son metadatos adicionales

3. **En el frontend**:
   - Los cursos se filtran por visibilidad según estructura del usuario
   - Se muestran también sus categorías MasterStudy si aplica

### Paso 4: Crear Método Auxiliar para Obtener Nombres de Estructura

**En `class-fplms-courses.php` o heredar de `class-fplms-structures.php`:**

```php
private function get_structure_names( string $taxonomy, array $term_ids ): array {
    $names = [];
    foreach ( $term_ids as $term_id ) {
        $term = get_term( $term_id, $taxonomy );
        if ( $term && ! is_wp_error( $term ) ) {
            $names[] = $term->name;
        }
    }
    return $names;
}
```

---

## 📋 Implementación Recomendada

### Fase 1: Visualización (Inmediata)
- ✅ Agregar columna de estructuras en listado de cursos
- ✅ Crear método `format_course_structures_display()`
- ✅ Implementar `get_term_names_by_ids()`

### Fase 2: Mejora de Interfaz (Corto plazo)
- ✅ Mejorar JavaScript del formulario de estructuras
- ✅ Agregar nonce para AJAX
- ✅ Validar respuestas de servidor

### Fase 3: Compatibilidad (Mediano plazo)
- ✅ Documentar relación entre FairPlay y MasterStudy
- ✅ Crear guía de uso integrado
- ✅ Opcional: Agregar interfaz para ambas taxonomías simultáneamente

---

## 🛡️ Consideraciones de Seguridad

1. **Validación de Nonce**: Usar `wp_verify_nonce()` en AJAX
2. **Sanitización**: Usar `absint()`, `sanitize_text_field()` en inputs
3. **Escapado**: Usar `esc_html()`, `esc_attr()` en outputs
4. **Permisos**: Verificar `current_user_can( FairPlay_LMS_Config::CAP_MANAGE_COURSES )`

---

## ⚠️ Posibles Inconvenientes y Mitigación

| Inconveniente | Causa | Solución |
|---|---|---|
| Cursos sin estructura visible | Meta no guardada | Validar en `save_course_structures()` |
| AJAX no carga estructuras | Nonce inválido | Crear endpoint AJAX con validación |
| Conflicto con categorías MasterStudy | Sobrescritura de meta | Mantener ambas taxonomías separadas |
| Rendimiento con muchas estructuras | Múltiples queries | Cachear resultados o usar batch queries |

---

## 📝 Resumen de Archivos a Modificar

1. **class-fplms-courses.php**
   - Modificar: `render_course_list_view()` - Agregar columna
   - Agregar: `format_course_structures_display()`
   - Agregar: `get_term_names_by_ids()`
   - Mejorar: `render_course_structures_view()` - JS mejorado

2. **class-fplms-plugin.php** (si existe)
   - Agregar: AJAX handler `fplms_get_terms_by_city` con nonce

3. Documentación
   - Agregar: `GUIA_ASIGNACION_ESTRUCTURAS_CURSOS.md`

---

## 🎬 Próximos Pasos

1. **Validar**: Que el sistema actual de guardado funcione correctamente
2. **Implementar**: Cambios de visualización (Fase 1)
3. **Probar**: Con múltiples ciudades y estructuras
4. **Documentar**: Proceso para usuarios finales
5. **Mejorar**: Interfaz según feedback (Fase 2 y 3)
