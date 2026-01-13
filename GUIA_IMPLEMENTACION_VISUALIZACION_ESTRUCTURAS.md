# 📋 Guía de Visualización y Asignación de Estructuras en Cursos

## 🎯 Objetivo Alcanzado

Se ha implementado un sistema completo para **visualizar las estructuras asignadas a cada curso** en el panel administrativo FairPlay LMS, permitiendo que los cursos muestren claramente qué ciudades, canales, sucursales y cargos tienen acceso.

---

## ✨ Cambios Realizados

### 1. Visualización de Estructuras en Listado de Cursos

**Archivo modificado**: `class-fplms-courses.php`

#### Columna Nueva en Tabla de Cursos

Se agregó una nueva columna **"Estructuras asignadas"** que muestra:

```
📍 Ciudades: Bogotá, Medellín
🏪 Canales: Canal A, Canal B
🏢 Sucursales: Sucursal Centro
👔 Cargos: Gerente, Vendedor
```

Si un curso no tiene restricciones de estructura:
```
Sin restricción (visible para todos)
```

#### Métodos Agregados

**1. `format_course_structures_display( array $structures ): string`**

```php
/**
 * Formatea las estructuras de un curso para mostrar en la tabla.
 * Recibe un array con estructura: ['cities' => [ids], 'channels' => [ids], ...]
 * Retorna HTML formateado para mostrar.
 */
private function format_course_structures_display( array $structures ): string {
    // Procesa cada nivel de estructura
    // Retorna HTML con emojis y nombres legibles
}
```

**2. `get_term_names_by_ids( array $term_ids ): array`**

```php
/**
 * Obtiene los nombres de términos por sus IDs.
 * Busca cada término en WordPress y retorna su nombre.
 */
private function get_term_names_by_ids( array $term_ids ): array {
    // Itera sobre los IDs de términos
    // Retorna array de nombres
}
```

---

### 2. Mejora del Formulario de Asignación de Estructuras

**Archivo modificado**: `class-fplms-courses.php` - Método `render_course_structures_view()`

#### JavaScript Mejorado

Se reemplazó el JavaScript anterior con una versión robusta que:

✅ **Incluye validación de Nonce**
```javascript
const nonce = '<?php echo wp_create_nonce( 'fplms_get_terms' ); ?>';
formData.append('nonce', nonce);
```

✅ **Manejo de errores mejorado**
```javascript
.then(response => {
    if (!response.ok) throw new Error('Network response was not ok');
    return response.json();
})
.catch(error => {
    console.error('Error al cargar estructuras:', error);
    fieldset.innerHTML = '<p><em style="color: red;">Error al cargar opciones...</em></p>';
});
```

✅ **Escapado de HTML**
```javascript
function escapeHtml(unsafe) {
    return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;")...
}
```

✅ **Carga automática al iniciar**
```javascript
const selectedCities = Array.from(document.querySelectorAll('.fplms-city-checkbox'))
    .filter(cb => cb.checked);
if (selectedCities.length > 0) {
    const event = new Event('change');
    selectedCities[0].dispatchEvent(event);
}
```

✅ **Nombres correctos de inputs**
```javascript
if (taxonomy === '<?php echo FairPlay_LMS_Config::TAX_CHANNEL; ?>') {
    inputName += 'channels[]';
} else if (taxonomy === '<?php echo FairPlay_LMS_Config::TAX_BRANCH; ?>') {
    inputName += 'branches[]';
} // ... etc
```

---

## 📐 Flujo de Funcionamiento

### Paso 1: Ver Cursos

```
Admin accede a: FairPlay LMS → Cursos
        ↓
Se cargan todos los cursos MasterStudy
        ↓
Para cada curso:
    - Título ✓
    - ID ✓
    - Profesor asignado ✓
    - ✨ ESTRUCTURAS ASIGNADAS (NUEVA COLUMNA)
    - Botones de acción ✓
```

### Paso 2: Asignar Estructuras a un Curso

```
Admin hace clic en "Gestionar estructuras" para un curso
        ↓
Se abre formulario con:
    ✓ Checkboxes de ciudades
    ✓ Fieldsets para canales, sucursales, cargos (dinámicos)
        ↓
Admin selecciona una ciudad
        ↓
JavaScript dispara AJAX a: admin-ajax.php?action=fplms_get_terms_by_city
        ↓
Se cargan dinámicamente:
    ✓ Canales de esa ciudad
    ✓ Sucursales de esa ciudad
    ✓ Cargos de esa ciudad
        ↓
Admin selecciona qué canales/sucursales/cargos pueden ver el curso
        ↓
Guarda cambios con POST
        ↓
Se almacena en post_meta:
    fplms_course_cities   → array(...)
    fplms_course_channels → array(...)
    fplms_course_branches → array(...)
    fplms_course_roles    → array(...)
```

### Paso 3: Vista Actualizada

```
Al regresar al listado de cursos
        ↓
La nueva columna muestra:
    📍 Ciudades: (los nombres de las ciudades seleccionadas)
    🏪 Canales: (los nombres de los canales seleccionados)
    🏢 Sucursales: (los nombres de las sucursales seleccionadas)
    👔 Cargos: (los nombres de los cargos seleccionados)
```

---

## 🔒 Seguridad Implementada

| Aspecto | Implementación |
|--------|-----------------|
| **Nonce** | `wp_create_nonce('fplms_get_terms')` en AJAX |
| **Sanitización** | `absint()` para IDs, `array_map()` para arrays |
| **Escapado** | `esc_html()` en nombres de términos, `escapeHtml()` en JS |
| **Validación** | Validación de response.ok antes de procesar JSON |
| **Permisos** | Verificación de `CAP_MANAGE_COURSES` en formulario |

---

## 🛠️ Cómo Usar

### Para Administradores

#### 1. Crear/Configurar Estructuras Base

```
FairPlay LMS → Estructuras
    ├─ Ciudades: Bogotá, Medellín, Cali
    ├─ Canales: Canal A, Canal B, Franquicia X
    ├─ Sucursales: Centro, Sur, Norte
    └─ Cargos: Gerente, Vendedor, Asistente
```

#### 2. Crear un Nuevo Curso (MasterStudy)

```
MasterStudy LMS → Agregar curso nuevo
```

#### 3. Asignar Estructuras al Curso

```
FairPlay LMS → Cursos → [Seleccionar curso] → Gestionar estructuras

Formulario:
├─ ☐ Bogotá
├─ ☐ Medellín
└─ ☐ Cali

Cuando seleccionas "Bogotá", se cargan automáticamente:
├─ Canales de Bogotá: ☐ Canal A, ☐ Canal B
├─ Sucursales de Bogotá: ☐ Centro, ☐ Sur
└─ Cargos de Bogotá: ☐ Gerente, ☐ Vendedor
```

#### 4. Ver Estructuras en Listado

```
FairPlay LMS → Cursos

Tabla muestra:
┌─────────────┬────┬──────────────┬─────────────────────┐
│ Curso       │ ID │ Profesor     │ Estructuras Assign. │
├─────────────┼────┼──────────────┼─────────────────────┤
│ Python 101  │ 42 │ Juan Pérez   │ 📍 Bogotá, Medellín │
│             │    │              │ 🏪 Canal A          │
│             │    │              │ 🏢 Centro           │
│             │    │              │ 👔 Gerente          │
└─────────────┴────┴──────────────┴─────────────────────┘
```

---

## 📊 Base de Datos

### Almacenamiento

```sql
-- Tabla: wp_postmeta
+----------+----------+------------------------------+--------------------+
| meta_id  | post_id  | meta_key                     | meta_value         |
+----------+----------+------------------------------+--------------------+
| 1001     | 42       | fplms_course_cities          | a:2:{i:0;i:3;...}  | ← Array: [3, 4]
| 1002     | 42       | fplms_course_channels        | a:1:{i:0;i:5;...}  | ← Array: [5]
| 1003     | 42       | fplms_course_branches        | a:1:{i:0;i:7;...}  | ← Array: [7]
| 1004     | 42       | fplms_course_roles           | a:2:{i:0;i:9;...}  | ← Array: [9, 10]
+----------+----------+------------------------------+--------------------+
```

### Recuperación de Datos

```php
$course_structures = get_post_meta( $course_id, 'fplms_course_cities', true );
// Retorna: array( 3, 4 )

$term = get_term( 3 );
// Retorna: WP_Term { name: "Bogotá", ... }
```

---

## 🐛 Troubleshooting

### Problema: "No hay opciones disponibles para esta ciudad"

**Causa**: La ciudad seleccionada no tiene canales/sucursales/cargos asignados

**Solución**: 
1. Ve a FairPlay LMS → Estructuras
2. Verifica que los canales, sucursales y cargos estén asignados a esa ciudad
3. Recarga la página

### Problema: AJAX no funciona

**Causa**: El servidor no tiene activado el manejador AJAX `fplms_get_terms_by_city`

**Solución**: Verifica que en `class-fplms-plugin.php` esté registrado el hook:
```php
add_action( 'wp_ajax_fplms_get_terms_by_city', [ $this->structures, 'ajax_get_terms_by_city' ] );
```

### Problema: Los nombres de términos aparecen vacíos

**Causa**: Los IDs guardados no corresponden a términos existentes

**Solución**:
1. Ve a Base de Datos → Tabla wp_terms
2. Verifica que existan los IDs guardados en post_meta
3. Usa `wp_term_exists()` para validar

---

## 📝 Cambios Futuros Recomendados

### Fase 2: Mejoras Cosméticas
- [ ] Agregar búsqueda en selects de estructuras
- [ ] Mostrar emojis solo en pantallas grandes (responsive)
- [ ] Agregar ícono de cadena para ver relaciones jerárquicas

### Fase 3: Funcionalidades Avanzadas
- [ ] Presets de estructura (ej: "Todos los canales de Bogotá")
- [ ] Bulk edit de estructuras para múltiples cursos
- [ ] Filtrar cursos por estructura en tabla
- [ ] Exportar/importar configuración de estructuras

### Fase 4: Integración
- [ ] Sincronizar con categorías MasterStudy
- [ ] Mostrar estructura en frontend del estudiante
- [ ] Notificaciones cuando se asigna estructura nueva

---

## ✅ Checklist de Verificación

- [x] Visualización de estructuras en tabla
- [x] Emojis para cada nivel de estructura
- [x] Mensaje "Sin restricción" cuando no hay filtros
- [x] JavaScript mejorado con nonce
- [x] Manejo de errores en AJAX
- [x] Escapado de HTML en JS
- [x] Carga automática de estructuras relacionadas
- [x] Validación de response HTTP
- [x] Nombres correctos en inputs dinámicos

---

## 📞 Soporte

Si encuentras problemas o tienes sugerencias para mejoras:

1. **Verificar la consola del navegador** (F12) para errores de JavaScript
2. **Revisar logs de WordPress** en wp-content/debug.log
3. **Probar en incógnito** para descartar conflictos de caché
4. **Validar permisos de usuario** - Debe ser Administrador o tener CAP_MANAGE_COURSES
