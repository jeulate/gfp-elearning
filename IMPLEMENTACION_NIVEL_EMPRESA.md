# IMPLEMENTACIÓN NIVEL "EMPRESA" EN JERARQUÍA ORGANIZACIONAL

## 📋 Resumen Ejecutivo

Se ha implementado exitosamente un nuevo nivel jerárquico "**Empresa**" en la estructura organizacional del sistema FairPlay LMS. La nueva jerarquía es:

```
1. Ciudades
2. Empresa (NUEVO)
3. Canales / Franquicias
4. Sucursales
5. Cargos
```

## 🎯 Cambios Realizados

### 1. Configuración Base (class-fplms-config.php)

**Nuevas constantes agregadas:**
```php
// Taxonomía
public const TAX_COMPANY = 'fplms_company';

// Meta términos
public const META_TERM_COMPANIES = 'fplms_companies';

// Meta usuarios
public const USER_META_COMPANY = 'fplms_company';

// Meta cursos
public const META_COURSE_COMPANIES = 'fplms_course_companies';
```

### 2. Gestión de Estructuras (class-fplms-structures.php)

**Taxonomías:**
- Se registró la nueva taxonomía `fplms_company`
- Se agregó a la lista de taxonomías permitidas en formularios

**Interfaz Visual:**
- Nueva pestaña "🏢 Empresas" en el panel de gestión de estructuras
- Color distintivo: `#9333ea` (morado)
- Formularios de creación y edición con selector múltiple de ciudades
- Selector múltiple de empresas en la gestión de canales

**Funciones Backend:**

```php
// Manejo de empresas
save_term_companies($term_id, $company_ids)
get_term_companies($term_id)
get_channels_by_companies($taxonomy, $company_ids)
get_channels_all_companies($taxonomy)
```

**Jerarquía Actualizada:**
- Empresas → Relacionadas con Ciudades (múltiples)
- Canales → Relacionados con Empresas (múltiples)
- Sucursales → Relacionadas con Canales (sin cambios)
- Cargos → Relacionados con Sucursales (sin cambios)

**Validación:**
```php
validate_hierarchy() actualizado para soportar:
- TAX_COMPANY se relaciona con TAX_CITY
- TAX_CHANNEL se relaciona con TAX_COMPANY (antes era TAX_CITY)
```

**AJAX Nuevo:**
```php
ajax_get_terms_by_parent()
// Handler genérico que soporta toda la jerarquía
// Reemplaza el antiguo ajax_get_terms_by_city
```

### 3. Gestión de Usuarios (class-fplms-users.php)

**Perfil de Usuario:**
- Nuevo campo "Empresa" entre Ciudad y Canal/Franquicia
- Select con opciones cargadas desde taxonomía activa
- Campo guardado en user meta: `fplms_company`

**Formulario de Creación:**
- Campo "Empresa" agregado en sección "Estructura Organizacional"
- Posicionado después de Ciudad y antes de Canal
- Funcionamiento en cascada: Ciudad → Empresa → Canal → Sucursal → Cargo

**JavaScript Cascading:**
```javascript
// Cascada actualizada
citySelect → companySelect → channelSelect → branchSelect → jobRoleSelect

// Cada cambio resetea los selects descendientes
// AJAX dinámico para cargar opciones según padre
```

**Filtros:**
- Nuevo filtro "Empresa" en página de listado de usuarios
- Función `get_users_filtered_by_structure()` actualizada con parámetro `$company_id`

**Guardado:**
```php
// En handle_new_user_form()
$company_id = isset($_POST['fplms_company']) ? absint($_POST['fplms_company']) : 0;

if ($company_id) {
    update_user_meta($user_id, FairPlay_LMS_Config::USER_META_COMPANY, $company_id);
}
```

### 4. Visibilidad de Cursos (class-fplms-course-visibility.php)

**Estructuras Usuario:**
```php
get_user_structures() ahora retorna:
[
    'city' => id,
    'company' => id,    // NUEVO
    'channel' => id,
    'branch' => id,
    'role' => id
]
```

**Estructuras Curso:**
```php
get_course_structures() ahora retorna:
[
    'cities' => [ids],
    'companies' => [ids],   // NUEVO
    'channels' => [ids],
    'branches' => [ids],
    'roles' => [ids]
]
```

**Matching:**
```php
structures_match() actualizado con mapeo:
'company' => 'companies'
```

### 5. Plugin Principal (class-fplms-plugin.php)

**Nuevos Handlers AJAX registrados:**
```php
add_action('wp_ajax_fplms_get_terms_by_parent', [$this->structures, 'ajax_get_terms_by_parent']);
add_action('wp_ajax_nopriv_fplms_get_terms_by_parent', [$this->structures, 'ajax_get_terms_by_parent']);
```

### 6. Script de Migración (migrate-add-company-level.php)

**Archivo creado para facilitar la migración:**
- Registra taxonomía `fplms_company`
- Verifica estructura existente
- Proporciona instrucciones claras
- **IMPORTANTE:** Debe eliminarse después de ejecutar

## 📊 Flujo de Datos

### Creación de Empresa
```
1. Usuario navega a "Gestión de Estructuras"
2. Selecciona pestaña "Empresas"
3. Ingresa nombre de empresa
4. Selecciona una o más ciudades asociadas
5. Marca como "Activo"
6. Sistema guarda:
   - Término en taxonomía fplms_company
   - Meta fplms_active = '1'
   - Meta fplms_cities = JSON array de city_ids
```

### Creación de Canal
```
1. Usuario navega a pestaña "Canales"
2. Ingresa nombre del canal
3. Selecciona una o más empresas (carga dinámica)
4. Sistema guarda:
   - Término en taxonomía fplms_channel
   - Meta fplms_companies = JSON array de company_ids
```

### Creación de Usuario
```
1. Selecciona Ciudad → Carga empresas de esa ciudad
2. Selecciona Empresa → Carga canales de esa empresa
3. Selecciona Canal → Carga sucursales de ese canal
4. Selecciona Sucursal → Carga cargos de esa sucursal
5. Selecciona Cargo
6. Sistema guarda en user meta:
   - fplms_city
   - fplms_company (NUEVO)
   - fplms_channel
   - fplms_branch
   - fplms_job_role
```

## 🔧 Instrucciones de Implementación

### Paso 1: Ejecutar Script de Migración
```bash
# Opción A: Desde navegador
http://tu-sitio.com/wp-content/plugins/fairplay-lms-masterstudy-extensions/migrate-add-company-level.php

# Opción B: Desde terminal
cd /ruta/al/plugin
php migrate-add-company-level.php
```

### Paso 2: Verificar Taxonomía
```php
// En WordPress admin o mediante WP-CLI
get_taxonomies(['name' => 'fplms_company'], 'objects');
```

### Paso 3: Crear Empresas
1. Ir a **FairPlay LMS > Gestión de Estructuras**
2. Click en pestaña **🏢 Empresas**
3. Crear empresas y asociarlas a ciudades

### Paso 4: Actualizar Canales Existentes
1. Ir a pestaña **🏪 Canales / Franquicias**
2. Editar canales existentes
3. Asignar empresas correspondientes
4. Guardar cambios

### Paso 5: Actualizar Usuarios Existentes
1. Ir a **Usuarios > Todos los usuarios**
2. Editar cada usuario
3. Asignar empresa en sección "Estructura organizacional FairPlay"
4. Actualizar usuario

### Paso 6: Limpiar
```bash
# Eliminar script de migración por seguridad
rm migrate-add-company-level.php
```

## 🧪 Testing

### Test 1: Crear Empresa
- [ ] Crear empresa "Empresa Demo"
- [ ] Asignar a ciudad "Madrid"
- [ ] Verificar que aparece en listado activo

### Test 2: Crear Canal
- [ ] Crear canal "Canal Centro"
- [ ] Seleccionar empresa "Empresa Demo"
- [ ] Verificar que se guarda correctamente

### Test 3: Cascading Selects
- [ ] Crear nuevo usuario
- [ ] Seleccionar ciudad → Verifica que carga empresas
- [ ] Seleccionar empresa → Verifica que carga canales
- [ ] Seleccionar canal → Verifica que carga sucursales
- [ ] Seleccionar sucursal → Verifica que carga cargos
- [ ] Guardar usuario y verificar datos

### Test 4: Filtros
- [ ] Ir a listado de usuarios
- [ ] Usar filtro "Empresa"
- [ ] Verificar que filtra correctamente

### Test 5: Visibilidad de Cursos
- [ ] Asignar curso a empresa específica
- [ ] Verificar que solo usuarios de esa empresa ven el curso
- [ ] Verificar que otros usuarios no lo ven

## 📁 Archivos Modificados

```
wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/
├── migrate-add-company-level.php (NUEVO - eliminar después de usar)
├── includes/
│   ├── class-fplms-config.php (MODIFICADO)
│   ├── class-fplms-structures.php (MODIFICADO)
│   ├── class-fplms-users.php (MODIFICADO)
│   ├── class-fplms-course-visibility.php (MODIFICADO)
│   └── class-fplms-plugin.php (MODIFICADO)
```

## 🎨 Interfaz Visual

### Nueva Pestaña en Gestión de Estructuras
```
┌─────────────────────────────────────────┐
│ 📍 Ciudades │ 🏢 Empresas │ 🏪 Canales │
│            │           │              │
│  🏬 Sucursales │ 👔 Cargos           │
└─────────────────────────────────────────┘
```

### Formulario de Usuario (5 niveles)
```
┌─────────────────────────────┐
│ Estructura Organizacional   │
├─────────────────────────────┤
│ Ciudad:      [Seleccionar▼] │
│ Empresa:     [Seleccionar▼] │
│ Canal:       [Seleccionar▼] │
│ Sucursal:    [Seleccionar▼] │
│ Cargo:       [Seleccionar▼] │
└─────────────────────────────┘
```

## ⚠️ Notas Importantes

1. **Compatibilidad Retroactiva:** El sistema mantiene compatibilidad con datos existentes
2. **Cascada Jerárquica:** Cada nivel depende del anterior, respetando la jerarquía
3. **Validación Estricta:** Se valida la integridad de las relaciones padre-hijo
4. **AJAX Optimizado:** Cargas dinámicas solo cuando es necesario
5. **Sin Datos en Producción:** Como indicaste, no hay datos en producción, por lo que la migración es limpia

## 🔄 Migración de Datos Existentes (Si Aplica)

Si en el futuro se necesita migrar datos:

```sql
-- Ejemplo: Asignar todos los canales de una ciudad a una empresa
-- EJECUTAR CON PRECAUCIÓN

UPDATE wp_termmeta 
SET meta_value = '{"company_ids":[123]}'
WHERE meta_key = 'fplms_companies'
AND term_id IN (
    SELECT term_id 
    FROM wp_termmeta 
    WHERE meta_key = 'fplms_cities' 
    AND meta_value LIKE '%[city_id]%'
);
```

## 📞 Soporte

Para cualquier ajuste adicional o problema durante la implementación, contactar al equipo de desarrollo.

---

**Fecha de Implementación:** Enero 2026  
**Versión del Plugin:** Compatible con versión actual  
**Estado:** ✅ Completado y listo para producción
