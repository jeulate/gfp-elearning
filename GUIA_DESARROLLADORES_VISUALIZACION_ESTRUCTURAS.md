# 🛠️ GUÍA PARA DESARROLLADORES: Mantenimiento y Extensión

## 📌 Cambios Realizados - Quick Reference

### Archivos Modificados

```
✏️ class-fplms-courses.php

CAMBIOS:
├─ render_course_list_view() [LÍNEA 241]
│  ├─ Agregar columna "Estructuras asignadas"
│  ├─ Obtener estructuras: get_course_structures()
│  └─ Formatear: format_course_structures_display()
│
├─ render_course_structures_view() [LÍNEA 611]
│  └─ JavaScript mejorado con validación y error handling
│
├─ format_course_structures_display() [LÍNEA 903] [NUEVO]
│  └─ Convierte array de IDs en string HTML legible
│
└─ get_term_names_by_ids() [LÍNEA 951] [NUEVO]
   └─ Busca nombres de términos por sus IDs
```

---

## 🔍 Análisis de Código

### Método 1: `render_course_list_view()`

**Línea de cambio clave**:
```php
// Línea 303-304
$course_structures = $this->get_course_structures( $course->ID );
$structures_display = $this->format_course_structures_display( $course_structures );
```

**Antes**:
```php
<td>
    <form method="post" style="display:flex; gap:4px; align-items:center;">
        <!-- formulario de profesor -->
    </form>
</td>
```

**Después**:
```php
<td style="font-size: 0.9em; line-height: 1.6;">
    <?php echo wp_kses_post( $structures_display ); ?>
</td>
<td>
    <form method="post" style="display:flex; gap:4px; align-items:center;">
        <!-- formulario de profesor -->
    </form>
</td>
```

### Método 2: `format_course_structures_display()` [NUEVO]

**Ubicación**: Línea 903-941

**Pseudocódigo**:
```
función format_course_structures_display(structures)
    display = []
    
    para cada nivel en ['cities', 'channels', 'branches', 'roles']:
        si structures[nivel] no está vacío:
            nombres = get_term_names_by_ids(structures[nivel])
            si nombres no está vacío:
                agregar emoji + etiqueta + nombres a display
    
    si display está vacío:
        retornar "Sin restricción (visible para todos)"
    sino:
        retornar display unido con <br>
```

**Emojis utilizados**:
| Nivel | Emoji | Código |
|-------|-------|--------|
| Cities | 📍 | `<strong>📍 Ciudades:</strong>` |
| Channels | 🏪 | `<strong>🏪 Canales:</strong>` |
| Branches | 🏢 | `<strong>🏢 Sucursales:</strong>` |
| Roles | 👔 | `<strong>👔 Cargos:</strong>` |

### Método 3: `get_term_names_by_ids()` [NUEVO]

**Ubicación**: Línea 951-962

**Algoritmo**:
```
función get_term_names_by_ids(term_ids)
    nombres = []
    
    para cada term_id en term_ids:
        término = get_term(term_id)
        
        si término existe Y no es error:
            agregar término.name a nombres
    
    retornar nombres
```

**Validaciones críticas**:
```php
$term = get_term( (int) $term_id );  // Convierte a int
if ( $term && ! is_wp_error( $term ) ) {  // Verifica existencia
    $names[] = $term->name;  // Extrae nombre
}
```

---

## 🧪 Testing

### Test Manual Paso a Paso

#### 1. Crear Datos de Prueba

```php
// En wp-cli o plugin de testing
wp term create fplms_city "Bogotá" --slug=bogota
wp term create fplms_city "Medellín" --slug=medellin

wp term create fplms_channel "Canal A" --slug=canal-a
wp term meta add 5 fplms_cities "3"  // Relacionar a Bogotá

wp term create fplms_branch "Centro" --slug=centro
wp term meta add 8 fplms_cities "3"

wp term create fplms_job_role "Vendedor" --slug=vendedor
wp term meta add 10 fplms_cities "3"
```

#### 2. Asignar Estructuras a un Curso

```php
// En panel admin o programáticamente
update_post_meta( 42, 'fplms_course_cities', [3] );
update_post_meta( 42, 'fplms_course_channels', [5] );
update_post_meta( 42, 'fplms_course_branches', [8] );
update_post_meta( 42, 'fplms_course_roles', [10] );
```

#### 3. Verificar Visualización

1. Ir a FairPlay LMS → Cursos
2. Buscar curso ID 42
3. Verificar que columna "Estructuras asignadas" muestre:
   ```
   📍 Ciudades: Bogotá
   🏪 Canales: Canal A
   🏢 Sucursales: Centro
   👔 Cargos: Vendedor
   ```

#### 4. Prueba de AJAX

1. Abrir navegador (F12)
2. Ir a "Gestionar estructuras" para curso 42
3. Seleccionar "Bogotá"
4. Verificar en Network:
   - POST a /wp-admin/admin-ajax.php
   - Action: fplms_get_terms_by_city
   - Response: JSON con términos relacionados
5. Verificar que checkboxes de canales se carguen dinámicamente

### Test Automatizado

```php
// phpunit test file
class Test_Course_Structures extends WP_UnitTestCase {
    
    public function test_format_course_structures_display() {
        $structures = [
            'cities' => [3],
            'channels' => [5],
            'branches' => [],
            'roles' => []
        ];
        
        $controller = new FairPlay_LMS_Courses_Controller();
        $output = $controller->format_course_structures_display( $structures );
        
        $this->assertStringContainsString( '📍', $output );
        $this->assertStringContainsString( '🏪', $output );
        $this->assertStringNotContainsString( '🏢', $output );
        $this->assertStringNotContainsString( '👔', $output );
    }
    
    public function test_get_term_names_by_ids() {
        // Crear términos de prueba
        $city_id = wp_create_term( 'Test City', 'fplms_city' )['term_id'];
        
        $controller = new FairPlay_LMS_Courses_Controller();
        $names = $controller->get_term_names_by_ids( [$city_id] );
        
        $this->assertContains( 'Test City', $names );
    }
}
```

---

## 🐛 Debugging

### Issue: Columna no se muestra

**Checklist**:
- [ ] El archivo class-fplms-courses.php fue guardado
- [ ] La clase tiene los 2 métodos nuevos
- [ ] No hay syntax errors (activar WP_DEBUG en wp-config.php)

**Verificar**:
```php
// En wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

// Luego revisar wp-content/debug.log
```

### Issue: Estructuras retornan vacías

**Causa probable**: `get_course_structures()` retorna arrays vacíos

**Debug**:
```php
// Agregar en render_course_list_view()
echo '<!-- DEBUG: ' . var_export( $course_structures, true ) . ' -->';
```

**Solución**:
- Verificar que se ejecutó `save_course_structures()`
- Verificar que post_meta tiene datos: 
  ```php
  get_post_meta( 42, 'fplms_course_cities', true )
  ```

### Issue: AJAX retorna error

**Pasos de diagnóstico**:
1. Abrir Network en DevTools (F12)
2. Seleccionar una ciudad
3. Buscar request a admin-ajax.php
4. Revisar Response:
   - Si error 403: Nonce inválido
   - Si error 500: Error del servidor (ver debug.log)
   - Si error 404: Hook no registrado

**Soluciones comunes**:
- Nonce expirado: Recargar página
- Hook no registrado: Verificar en class-fplms-plugin.php
- Permission denied: Usuario sin CAP_MANAGE_COURSES

---

## 📈 Mejoras Futuras

### Priority: HIGH

```
1. Caché de relaciones jerárquicas
   ├─ Problema: 50 queries por cada 50 cursos
   ├─ Solución: wp_cache_set() de términos por ciudad
   └─ Impacto: -40ms por listado
   
2. Bulk edit de estructuras
   ├─ Feature: Seleccionar múltiples cursos
   ├─ Asignar la misma estructura a todos
   └─ Impacto: Reduce tiempo de configuración 90%
```

### Priority: MEDIUM

```
3. Filtro en tabla por estructura
   ├─ Agregar dropdown/checkbox de filtro
   ├─ WHERE meta_key = 'fplms_course_cities' AND meta_value LIKE '%3%'
   └─ Impacto: Facilita búsqueda de cursos por ciudad

4. Exportar/Importar configuración
   ├─ CSV con estructura de cada curso
   ├─ Importar desde CSV
   └─ Impacto: Facilita migración/backup
```

### Priority: LOW

```
5. Interfaz visual de relaciones
   ├─ Diagrama de árbol (Ciudad > Canal > Sucursal > Cargo)
   ├─ Click para expandir/contraer
   └─ Impacto: Mejor comprensión de jerarquía

6. Sincronización con categorías MasterStudy
   ├─ Permitir usar tanto estructuras FairPlay como categorías
   ├─ Mostrar ambas en tabla
   └─ Impacto: Mayor flexibilidad
```

---

## 🔄 Workflow de Deployment

### Desarrollo Local

```bash
# 1. Crear rama
git checkout -b feature/course-structures-visualization

# 2. Realizar cambios
# - Editar class-fplms-courses.php
# - Probar localmente

# 3. Commit
git add wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/includes/class-fplms-courses.php
git commit -m "feat: Agregar visualización de estructuras en tabla de cursos"

# 4. Push
git push origin feature/course-structures-visualization

# 5. Pull Request
# - Describir cambios
# - Solicitar review
```

### Producción

```bash
# 1. Backup
mysqldump wordpress > backup.sql
cp -r wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions backup/

# 2. Deploy
git merge feature/course-structures-visualization main
git push production main

# 3. Verificar
# - Acceder a FairPlay LMS → Cursos
# - Verificar columna de estructuras
# - Probar AJAX
# - Revisar logs
```

---

## 📝 Código Comments Standards

### Para métodos nuevos

```php
/**
 * Descripción breve del método.
 *
 * Descripción detallada si es necesario.
 *
 * @param array $structures Array con estructura: ['cities' => [ids], ...].
 * @param bool  $verbose    Si mostrar detalle completo (default false).
 * @return string HTML formateado o string vacío.
 *
 * @since 1.0.0
 *
 * @example
 *     $display = $this->format_course_structures_display( $structures );
 *     echo $display;
 */
```

### Para cambios en métodos existentes

```php
// ✨ NUEVO: Agregar visualización de estructuras
// antes:
// $courses as $course -> tabla simple
// después:
// obtiene estructuras y las renderiza en nueva columna
```

---

## 🎓 Recursos Útiles

### WordPress Core

- `get_post_meta()` - [Docs](https://developer.wordpress.org/reference/functions/get_post_meta/)
- `get_term()` - [Docs](https://developer.wordpress.org/reference/functions/get_term/)
- `wp_kses_post()` - [Docs](https://developer.wordpress.org/reference/functions/wp_kses_post/)
- `esc_html()` - [Docs](https://developer.wordpress.org/reference/functions/esc_html/)

### FairPlay LMS

- [Estructura Jerárquica](../ESTRUCTURA_JERARQUICA_CIUDADES.md)
- [Config Constants](class-fplms-config.php)
- [Structures Controller](class-fplms-structures.php)

### Testing

- WP CLI - `wp shell`
- Debug Mode - `define('WP_DEBUG', true);`
- XDebug - Step-by-step debugging

---

## ✅ Checklist para Mantenimiento

### Semanal
- [ ] Revisar debug.log por errores
- [ ] Probar con nuevos datos
- [ ] Validar que AJAX responda correctamente

### Mensual
- [ ] Revisar performance (con 100+ cursos)
- [ ] Actualizar documentación si hay cambios
- [ ] Crear issues para mejoras sugeridas

### Trimestral
- [ ] Audit de seguridad
- [ ] Revisar bugs reportados
- [ ] Planificar mejoras priority HIGH

---

## 📞 Contacto para Issues

Si encuentras problemas:

1. **Verificar** WP_DEBUG está activo
2. **Revisar** logs en wp-content/debug.log
3. **Reproducir** en ambiente local
4. **Documentar** pasos exactos
5. **Reportar** con logs + screenshots

---

**Documento creado**: 13 de Enero de 2026  
**Versión**: 1.0  
**Para desarrolladores**: FairPlay LMS team
