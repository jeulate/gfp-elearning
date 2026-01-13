# 🔧 Documentación Técnica: Visualización de Estructuras en Cursos

## Índice Técnico

1. [Estructura del Sistema](#estructura-del-sistema)
2. [Métodos Implementados](#métodos-implementados)
3. [Base de Datos](#base-de-datos)
4. [JavaScript](#javascript)
5. [Seguridad](#seguridad)
6. [Troubleshooting Técnico](#troubleshooting-técnico)

---

## Estructura del Sistema

### Arquitectura General

```
WordPress (Post Meta)
├── fplms_course_cities    → array de term_ids
├── fplms_course_channels  → array de term_ids
├── fplms_course_branches  → array de term_ids
└── fplms_course_roles     → array de term_ids
    
WordPress (Terms)
├── wp_terms (cities, channels, branches, roles)
└── wp_termmeta (relaciones jerárquicas)
    ├── fplms_parent_city
    ├── fplms_parent_channel
    └── fplms_parent_branch

FairPlay Plugin
├── class-fplms-courses.php
│   ├── render_course_list_view()
│   ├── get_course_structures()
│   ├── format_course_structures_display() [NEW]
│   ├── get_term_names_by_ids() [NEW]
│   └── render_course_structures_view()
│
├── class-fplms-structures.php
│   ├── get_active_terms_for_select()
│   ├── get_active_terms_by_city()
│   └── get_term_name_by_id()
│
└── class-fplms-course-visibility.php
    ├── get_visible_courses_for_user()
    ├── can_user_see_course()
    └── structures_match()
```

### Flujo de Datos

```
Usuario Admin
    ↓
render_course_list_view()
    ├─ get_posts() → Obtiene todos los cursos
    ├─ Para cada curso:
    │  ├─ get_course_structures() → Obtiene IDs almacenados
    │  ├─ format_course_structures_display() → Convierte IDs a nombres
    │  └─ Renderiza fila con estructura visible
    └─ Muestra tabla actualizada

Usuario Admin hace clic "Gestionar estructuras"
    ↓
render_course_structures_view()
    ├─ Obtiene estructuras actuales del curso
    ├─ Obtiene ciudades activas
    ├─ Renderiza formulario con checkboxes
    └─ JavaScript espera eventos
    
Evento: Admin selecciona ciudad
    ↓
JavaScript dispara AJAX
    ├─ Prepara FormData con nonce
    ├─ POST a admin-ajax.php?action=fplms_get_terms_by_city
    ├─ Recibe JSON con términos relacionados
    └─ Actualiza fieldsets dinámicamente

Admin guarda formulario
    ↓
save_course_structures()
    ├─ Extrae arrays de POST
    ├─ Sanitiza con absint()
    ├─ update_post_meta() 4 veces
    └─ Redirige al listado
```

---

## Métodos Implementados

### 1. `render_course_list_view(): void`

**Ubicación**: `class-fplms-courses.php`, línea ~240

**Cambios**:
- Agregar nueva columna en tabla
- Obtener y formatear estructuras antes de renderizar
- Aumentar colspan si es necesario

**Código**:
```php
private function render_course_list_view(): void {
    // ... preparar datos ...
    
    foreach ( $courses as $course ) {
        $course_structures = $this->get_course_structures( $course->ID );
        $structures_display = $this->format_course_structures_display( $course_structures );
        
        // ... renderizar fila con nueva columna ...
    }
}
```

**Complejidad**: O(n) donde n = número de cursos
**Impacto de Performance**: Bajo (usa get_post_meta caché)

---

### 2. `format_course_structures_display( array $structures ): string` [NEW]

**Ubicación**: `class-fplms-courses.php`, línea ~909

**Parámetro**:
```php
$structures = [
    'cities'   => [3, 4],              // IDs de ciudades
    'channels' => [5, 6, 7],           // IDs de canales
    'branches' => [8],                 // IDs de sucursales
    'roles'    => [10, 11]             // IDs de cargos
]
```

**Retorna**: String HTML con formato
```html
<strong>📍 Ciudades:</strong> Bogotá, Medellín<br>
<strong>🏪 Canales:</strong> Canal A, Canal B<br>
<strong>🏢 Sucursales:</strong> Centro<br>
<strong>👔 Cargos:</strong> Gerente, Vendedor
```

**Lógica**:
1. Para cada nivel (cities, channels, branches, roles)
2. Si el array no está vacío:
   - Obtener nombres con `get_term_names_by_ids()`
   - Escapar con `esc_html()`
   - Agregar emoji y etiqueta strong
   - Concatenar con `<br>`
3. Si todos están vacíos, retornar mensaje por defecto

**Complejidad**: O(m) donde m = total de IDs de estructuras
**Impacto de Performance**: Bajo (máx 10-20 términos por curso)

---

### 3. `get_term_names_by_ids( array $term_ids ): array` [NEW]

**Ubicación**: `class-fplms-courses.php`, línea ~961

**Parámetro**:
```php
$term_ids = [3, 4, 5]  // IDs de términos
```

**Retorna**: 
```php
['Bogotá', 'Medellín', 'Cali']  // Nombres de términos
```

**Implementación**:
```php
private function get_term_names_by_ids( array $term_ids ): array {
    $names = [];
    foreach ( $term_ids as $term_id ) {
        $term = get_term( (int) $term_id );
        if ( $term && ! is_wp_error( $term ) ) {
            $names[] = $term->name;
        }
    }
    return $names;
}
```

**Validaciones**:
- Conversión a int: `(int) $term_id`
- Verificación WP_Error: `! is_wp_error( $term )`
- Verificación null: `$term &&`

**Complejidad**: O(m) donde m = número de IDs
**Query DB**: 1 query por cada `get_term()` (potencialmente en caché)

---

### 4. `render_course_structures_view( int $course_id ): void` [MEJORADO]

**Ubicación**: `class-fplms-courses.php`, línea ~616

**Cambios principales**:

#### 4a. Inicialización de Nonce
```php
const nonce = '<?php echo wp_create_nonce( 'fplms_get_terms' ); ?>';
formData.append('nonce', nonce);
```

#### 4b. Validación HTTP
```php
.then(response => {
    if (!response.ok) throw new Error('Network response was not ok');
    return response.json();
})
```

#### 4c. Función de Escapado
```php
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
```

#### 4d. Nombres Correctos de Inputs
```php
let inputName = 'fplms_course_';
if (taxonomy === '<?php echo FairPlay_LMS_Config::TAX_CHANNEL; ?>') {
    inputName += 'channels[]';
} else if (taxonomy === '<?php echo FairPlay_LMS_Config::TAX_BRANCH; ?>') {
    inputName += 'branches[]';
} else if (taxonomy === '<?php echo FairPlay_LMS_Config::TAX_ROLE; ?>') {
    inputName += 'roles[]';
}
```

#### 4e. Carga Automática
```php
const selectedCities = Array.from(document.querySelectorAll('.fplms-city-checkbox'))
    .filter(cb => cb.checked);
if (selectedCities.length > 0) {
    const event = new Event('change');
    selectedCities[0].dispatchEvent(event);
}
```

---

## Base de Datos

### Almacenamiento: Post Meta

```sql
SELECT * FROM wp_postmeta 
WHERE post_id = 42 
AND meta_key LIKE 'fplms_course_%';

+----------+---------+-------------------------+-------------------------------------------+
| meta_id  | post_id | meta_key                | meta_value                                |
+----------+---------+-------------------------+-------------------------------------------+
| 1001     | 42      | fplms_course_cities     | a:2:{i:0;i:3;i:1;i:4;}                    |
| 1002     | 42      | fplms_course_channels   | a:1:{i:0;i:5;}                            |
| 1003     | 42      | fplms_course_branches   | a:1:{i:0;i:8;}                            |
| 1004     | 42      | fplms_course_roles      | a:2:{i:0;i:10;i:1;i:11;}                  |
+----------+---------+-------------------------+-------------------------------------------+
```

### Lectura: get_post_meta()

```php
$cities = (array) get_post_meta( 42, 'fplms_course_cities', true );
// Retorna: array( 3, 4 )
```

**Nota**: WordPress deserializa automáticamente el formato PHP serializado

### Escritura: update_post_meta()

```php
$cities = [3, 4];
update_post_meta( 42, 'fplms_course_cities', $cities );

// WordPress serializa automáticamente:
// a:2:{i:0;i:3;i:1;i:4;}
```

### Referencias a Términos

```sql
-- Obtener nombres de términos relacionados
SELECT t.term_id, t.name 
FROM wp_terms t
WHERE t.term_id IN (3, 4)
LIMIT 20;

+----------+----------+
| term_id  | name     |
+----------+----------+
| 3        | Bogotá   |
| 4        | Medellín |
+----------+----------+
```

### Integridad Referencial

⚠️ **Importante**: Si eliminas un término, los IDs quedan huérfanos en post_meta

**Recomendación**: Crear validación en `delete_term` hook:
```php
add_action( 'delete_term', function( $term_id, $tt_id, $taxonomy ) {
    // Limpiar referencias en post_meta
    // cuando se elimina un término
}, 10, 3 );
```

---

## JavaScript

### Estructura General

```javascript
document.addEventListener('DOMContentLoaded', function() {
    // 1. Configuración inicial
    const ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
    const nonce = '<?php echo wp_create_nonce( 'fplms_get_terms' ); ?>';
    const cityCheckboxes = document.querySelectorAll('.fplms-city-checkbox');
    
    // 2. Función auxiliar
    function escapeHtml(unsafe) { ... }
    
    // 3. Event listeners
    cityCheckboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() { ... });
    });
    
    // 4. Carga automática
    const selectedCities = Array.from(cityCheckboxes).filter(cb => cb.checked);
    if (selectedCities.length > 0) { ... }
});
```

### AJAX Call

```javascript
fetch(ajaxUrl, {
    method: 'POST',
    body: formData,
    headers: {
        'X-Requested-With': 'XMLHttpRequest'
    }
})
.then(response => {
    if (!response.ok) throw new Error('Network response was not ok');
    return response.json();
})
.then(data => {
    if (data.success && data.data) {
        // Actualizar DOM
    }
})
.catch(error => {
    console.error('Error al cargar estructuras:', error);
    fieldset.innerHTML = '<p><em style="color: red;">Error al cargar...</em></p>';
});
```

### Estado de Respuesta AJAX Esperado

```json
{
  "success": true,
  "data": {
    "5": "Canal A",
    "6": "Canal B",
    "7": "Franquicia X"
  }
}
```

O si hay error:
```json
{
  "success": false,
  "data": {
    "message": "Verificación de seguridad fallida"
  }
}
```

---

## Seguridad

### 1. Nonce (CSRF Protection)

**Generación (PHP)**:
```php
$nonce = wp_create_nonce( 'fplms_get_terms' );
echo '<script>const nonce = "' . esc_js( $nonce ) . '";</script>';
```

**Envío (JavaScript)**:
```php
formData.append('nonce', nonce);
```

**Validación (PHP en handler AJAX)**:
```php
check_ajax_referer( 'fplms_get_terms', 'nonce' );
// Lanza die si es inválido
```

### 2. Sanitización

**POST (PHP)**:
```php
$cities = isset( $_POST['fplms_course_cities'] ) 
    ? array_map( 'absint', (array) wp_unslash( $_POST['fplms_course_cities'] ) ) 
    : [];
```

**Desglose**:
- `wp_unslash()`: Remove slashes añadidos por magic_quotes
- `absint()`: Convierte a integer (elimina caracteres no numéricos)
- `array_map()`: Aplica función a cada elemento

### 3. Escapado

**HTML (PHP)**:
```php
echo esc_html( $term_name );  // Escapa entidades HTML
echo esc_attr( $value );       // Escapa atributos HTML
echo wp_kses_post( $content ); // Filtra HTML permitido
```

**JavaScript**:
```php
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")    // & → &amp;
        .replace(/</g, "&lt;")     // < → &lt;
        .replace(/>/g, "&gt;")     // > → &gt;
        .replace(/"/g, "&quot;")   // " → &quot;
        .replace(/'/g, "&#039;");  // ' → &#039;
}
```

### 4. Validación HTTP

```php
if (!response.ok) throw new Error('Network response was not ok');
```

Verifica que el status HTTP sea 2xx (200-299)

### 5. Permisos

**En formulario**:
```php
if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_COURSES ) ) {
    wp_die( 'No tienes permisos...' );
}
```

**En AJAX (recomendado agregar)**:
```php
if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_COURSES ) ) {
    wp_send_json_error( 'Acceso denegado' );
}
```

---

## Troubleshooting Técnico

### Problema: AJAX retorna 404

**Causa**: El hook AJAX no está registrado

**Solución**: Verificar en `class-fplms-plugin.php`:
```php
add_action( 'wp_ajax_fplms_get_terms_by_city', [ $this->structures, 'ajax_get_terms_by_city' ] );
add_action( 'wp_ajax_nopriv_fplms_get_terms_by_city', [ $this->structures, 'ajax_get_terms_by_city' ] );
// ↑ nopriv = usuarios no autenticados (remover si es necesario)
```

### Problema: AJAX retorna error de nonce

**Causa**: El nonce está expirado o mal generado

**Solución**:
1. Verificar que `wp_create_nonce()` se ejecute en cada página
2. No guardar nonce en variable global (se regenera cada request)
3. Aumentar tiempo de vida (por defecto 12 horas):
```php
$nonce = wp_create_nonce( 'fplms_get_terms', 86400 * 2 ); // 2 días
```

### Problema: Los términos no se cargan dinámicamente

**Causa**: El checkbox está en un elemento dinámico no existente al cargar

**Solución**: Usar delegación de eventos:
```php
document.addEventListener('change', function(e) {
    if ( e.target.classList.contains('fplms-city-checkbox') ) {
        // Manejar el evento
    }
}, true); // true = captura de eventos
```

### Problema: Los nombres de términos aparecen vacíos

**Causa**: Los IDs no existen en wp_terms

**Solución**: Validar al guardar:
```php
foreach ( $cities as $city_id ) {
    if ( ! term_exists( $city_id ) ) {
        unset( $cities[ array_search( $city_id, $cities ) ] );
    }
}
update_post_meta( $course_id, 'fplms_course_cities', array_values( $cities ) );
```

### Problema: Performance lenta con 200+ cursos

**Causa**: 200 queries a `get_term()` sin caché

**Solución**: Cachear términos:
```php
$terms = get_terms( [ 'fields' => 'id=>name' ] );
wp_cache_set( 'fplms_all_terms', $terms, 'fplms', 3600 ); // 1 hora

// Luego usar:
$terms = wp_cache_get( 'fplms_all_terms', 'fplms' );
```

---

## Testing

### Test Manual

```
1. Crear 3 ciudades: Bogotá, Medellín, Cali
2. Crear canales relacionados a cada ciudad
3. Crear sucursales relacionadas
4. Crear cargos relacionados
5. Ir a un curso existente
6. Clic en "Gestionar estructuras"
7. Seleccionar "Bogotá"
8. Verificar que se carguen canales de Bogotá
9. Seleccionar canales, sucursales, cargos
10. Guardar
11. Ir a listado de cursos
12. Verificar que nueva columna muestre estructuras correctas
```

### Test de Seguridad

```
1. Abrir consola (F12)
2. Ejecutar: fetch('/wp-admin/admin-ajax.php?action=fplms_get_terms_by_city')
3. Debe retornar error 403 (sin nonce)
4. Crear usuario sin CAP_MANAGE_COURSES
5. Intentar acceder formulario
6. Debe mostrar "No tienes permisos"
```

---

## Métricas y Monitoreo

### Queries por Acción

| Acción | Queries | Cache |
|--------|---------|-------|
| Load course list (50 cursos) | 1 + 50 | get_post_meta |
| Format structures (1 curso) | 0-20 | get_term |
| AJAX get terms by city | 1 | get_terms |
| Save structures (1 curso) | 4 | update_post_meta |

### Tiempo de Ejecución Esperado

- Load course list: ~200ms
- AJAX response: ~50ms
- Save: ~100ms

### Alertas a Monitorear

```php
// Agregar a debug.log si hay muchas queries sin caché
if ( did_action( 'wp_footer' ) > 100 ) {
    error_log( 'FPLMS: Too many queries in course structures' );
}
```

---

## Cambios Futuros Sugeridos

### 1. Optimización de Búsqueda

```php
// Agregar búsqueda en selects
'search' => $search_term,
'search_columns' => [ 'name', 'slug' ],
```

### 2. Batching de Queries

```php
// Obtener todos los términos de una vez
$all_terms = get_terms( [
    'taxonomy' => [ FairPlay_LMS_Config::TAX_CITY, ... ],
    'fields' => 'id=>name',
    'hide_empty' => false,
] );
```

### 3. Caché de Relaciones

```php
// Guardar en opción para relaciones frecuentes
update_option( 'fplms_city_' . $city_id . '_channels', $channel_ids );
```

---

**Última actualización**: 13 de Enero de 2026  
**Versión**: 1.0  
**Mantenedor**: GitHub Copilot
