# 📘 DOCUMENTACIÓN TÉCNICA - Sincronización Canales ↔ Categorías

**Fecha:** 2026-02-16  
**Versión:** 1.0.0  
**Desarrollador:** GitHub Copilot (Claude Sonnet 4.5)

---

## 🎯 RESUMEN EJECUTIVO

Se implementó un sistema completo de sincronización bidireccional entre **Canales FairPlay** y **Categorías MasterStudy**, permitiendo que Course Builder funcione nativamente con las estructuras jerárquicas personalizadas. Incluye sistema de auditoría empresarial.

---

## 🏗️ ARQUITECTURA IMPLEMENTADA

### **Componente 1: Sistema de Auditoría**

#### Clase: `FairPlay_LMS_Audit_Logger`
**Ubicación:** `includes/class-fplms-audit-logger.php`

**Responsabilidades:**
- Crear y gestionar tabla `wp_fplms_audit_log`
- Registrar todas las operaciones con metadatos completos
- Proveer métodos de consulta, filtrado y exportación
- Generar estadísticas de uso

**Tabla de Base de Datos:**
```sql
CREATE TABLE wp_fplms_audit_log (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT(20) UNSIGNED NOT NULL,
    entity_title VARCHAR(255) DEFAULT NULL,
    old_value TEXT DEFAULT NULL,
    new_value TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY timestamp_idx (timestamp),
    KEY user_id_idx (user_id),
    KEY action_idx (action),
    KEY entity_type_idx (entity_type),
    KEY entity_id_idx (entity_id),
    KEY composite_idx (entity_type, entity_id, action)
);
```

**Métodos Principales:**
- `create_table()` - Crear tabla con dbDelta
- `log_action()` - Registrar acción con contexto completo
- `get_logs($args)` - Consultar logs con filtros
- `count_logs($args)` - Contar registros filtrados
- `export_to_csv($args)` - Exportar a CSV
- `cleanup_old_logs($days)` - Limpiar logs antiguos
- `get_statistics($args)` - Obtener estadísticas agregadas

**Acciones Registradas:**
- `course_created` - Curso creado manualmente
- `structures_assigned` - Estructuras asignadas en meta box
- `structures_updated` - Estructuras actualizadas
- `course_structures_synced_from_categories` - Sync desde Course Builder
- `channel_category_sync` - Canal sincronizado con categoría
- `channel_unsynced` - Canal desvinculado
- `permission_denied` - Permiso denegado a instructor
- `notification_sent` - Notificación enviada por email

---

### **Componente 2: Interfaz Administrativa**

#### Clase: `FairPlay_LMS_Audit_Admin`
**Ubicación:** `admin/class-fplms-audit-admin.php`

**Responsabilidades:**
- Renderizar página de bitácora en WordPress Admin
- Proveer filtros interactivos (acción, entidad, fecha, usuario)
- Mostrar estadísticas en tiempo real
- Permitir exportación a CSV
- Paginación de resultados

**Menú Admin:**
- **Ruta:** `FairPlay LMS → 📋 Bitácora`
- **Capability:** `manage_options`
- **Slug:** `fairplay-lms-audit`

**Características de la Interfaz:**
- 📊 Tarjetas de estadísticas (total logs, acción más frecuente, usuario más activo)
- 🔍 Filtros por acción, tipo de entidad, rango de fechas
- 📋 Tabla paginada con 50 registros por página
- 👁️ Vista expandible de detalles (valores anterior/nuevo, user agent)
- 📥 Exportación a CSV con BOM UTF-8
- 🎨 Diseño responsive con grid CSS

---

### **Componente 3: Sincronización Canal → Categoría**

#### Métodos en `FairPlay_LMS_Structures_Controller`

##### `sync_channel_to_category($term_id, $tt_id, $taxonomy)`
**Hook:** `created_fplms_channel`, `edited_fplms_channel`

**Flujo:**
1. Verificar que es taxonomía `fplms_channel`
2. Comprobar si ya existe categoría vinculada (`fplms_linked_category_id`)
3. Si existe: Actualizar nombre y descripción de categoría
4. Si NO existe: Crear nueva categoría con slug `fplms-[canal-slug]`
5. Guardar relación bidireccional en termmeta:
   - `fplms_linked_category_id` en canal
   - `fplms_linked_channel_id` en categoría
6. Registrar en auditoría

**Taxonomía de Categorías:**
- Taxonomía: `stm_lms_course_taxonomy` (nativa de MasterStudy)
- Slug: `fplms-{canal-slug}`
- Descripción: `🔗 Sincronizado con Canal: {nombre}`

##### `get_linked_category($channel_id)`
Obtiene ID de categoría vinculada a un canal.

##### `get_linked_channel($category_id)`
Obtiene ID de canal vinculado a una categoría.

##### `unsync_channel_on_delete($term_id, $tt_id, $taxonomy, $deleted_term)`
**Hook:** `delete_fplms_channel`

Remueve vinculación bidireccional cuando se elimina un canal (la categoría permanece).

---

### **Componente 4: Detección y Cascada en Course Builder**

#### Método en `FairPlay_LMS_Courses_Controller`

##### `sync_categories_to_structures($object_id, $terms, $tt_ids, $taxonomy, $append, $old_terms)`
**Hook:** `set_object_terms` (WordPress core)

**Flujo:**
1. Verificar que es taxonomía `stm_lms_course_taxonomy`
2. Verificar que el post es tipo `stm-courses`
3. Prevenir loops recursivos con constante `FPLMS_SYNCING_CATEGORIES`
4. Para cada categoría asignada:
   - Buscar canal vinculado usando `get_linked_channel()`
   - Si encuentra canal, agregarlo al array de canales
5. Aplicar cascada estructural usando `apply_structure_cascade()`
6. Guardar en post_meta:
   - `fplms_course_cities`
   - `fplms_course_companies`
   - `fplms_course_channels`
   - `fplms_course_branches`
   - `fplms_course_roles`
7. Registrar en auditoría
8. Enviar notificaciones por email

##### `apply_structure_cascade($cities, $companies, $channels, $branches, $roles)`
**Método Privado**

**Algoritmo de Cascada:**
```
SI hay canales:
    PARA CADA canal:
        Obtener empresas del canal
        PARA CADA empresa:
            Agregar empresa al resultado
            Obtener ciudades de la empresa
            PARA CADA ciudad:
                Agregar ciudad al resultado
        
        Obtener sucursales del canal
        PARA CADA sucursal:
            Agregar sucursal al resultado
            Obtener cargos de la sucursal
            PARA CADA cargo:
                Agregar cargo al resultado

SI hay empresas (sin canales):
    PARA CADA empresa:
        Obtener ciudades de la empresa
        PARA CADA ciudad:
            Agregar ciudad al resultado

RETORNAR array con todas las estructuras
```

**Jerarquía:**
```
Ciudad (fplms_city)
    └── Empresa (fplms_company)
            └── Canal (fplms_channel)
                    └── Sucursal (fplms_branch)
                            └── Cargo (fplms_role)
```

---

## 🔌 HOOKS REGISTRADOS

### En `FairPlay_LMS_Plugin::register_hooks()`

```php
// Sincronización canal → categoría
add_action('created_fplms_channel', [$this->structures, 'sync_channel_to_category'], 10, 3);
add_action('edited_fplms_channel', [$this->structures, 'sync_channel_to_category'], 10, 3);
add_action('delete_fplms_channel', [$this->structures, 'unsync_channel_on_delete'], 10, 4);

// Detección de categorías en Course Builder
add_action('set_object_terms', [$this->courses, 'sync_categories_to_structures'], 10, 6);

// Menú de auditoría
add_action('admin_menu', [$this->audit_admin, 'register_admin_menu'], 20);
```

### Hook de Activación

```php
// En fairplay-lms-masterstudy-extensions.php
register_activation_hook(__FILE__, 'fplms_create_user_logins_table');

// Dentro de la función:
$audit_logger = new FairPlay_LMS_Audit_Logger();
$audit_logger->create_table();
```

---

## 🔐 SEGURIDAD

### Prevención de Loops Recursivos
```php
if (defined('FPLMS_SYNCING_CATEGORIES') && FPLMS_SYNCING_CATEGORIES) {
    return;
}
define('FPLMS_SYNCING_CATEGORIES', true);
```

### Sanitización de Inputs
- `sanitize_text_field()` en todos los `$_GET`/`$_POST`
- `wp_unslash()` para valores de formularios
- `intval()` para IDs numéricos
- `esc_html()`, `esc_attr()`, `esc_url()` en outputs

### Verificación de Permisos
- Bitácora: `current_user_can('manage_options')`
- Exportación CSV: `check_admin_referer('fplms_export_audit')`
- Acciones de estructura: Validación en métodos individuales

### SQL Injection Prevention
- Uso de `$wpdb->prepare()` en todas las queries
- Placeholders `%s`, `%d` para valores dinámicos
- No construcción manual de SQL strings

---

## 📊 FLUJO DE DATOS

### Caso de Uso 1: Crear Canal

```
USUARIO → Crear Canal "Ventas CABA"
    ↓
FairPlay_LMS_Structures_Controller::handle_form()
    ↓
wp_insert_term(..., 'fplms_channel')
    ↓
Hook: created_fplms_channel
    ↓
FairPlay_LMS_Structures_Controller::sync_channel_to_category()
    ↓
wp_insert_term("Ventas CABA", 'stm_lms_course_taxonomy', [
    'slug' => 'fplms-ventas-caba',
    'description' => '🔗 Sincronizado con Canal: Ventas CABA'
])
    ↓
update_term_meta(canal_id, 'fplms_linked_category_id', categoria_id)
update_term_meta(categoria_id, 'fplms_linked_channel_id', canal_id)
    ↓
FairPlay_LMS_Audit_Logger::log_action(
    'channel_category_sync',
    'channel',
    canal_id,
    'Ventas CABA',
    null,
    "Categoría creada: {categoria_id}"
)
    ↓
RESULTADO: Canal + Categoría vinculados bidireccionalmente
```

### Caso de Uso 2: Crear Curso con Course Builder

```
USUARIO → Course Builder → Selecciona Categoría "Ventas CABA"
    ↓
Course Builder → wp_set_object_terms(curso_id, [categoria_id], 'stm_lms_course_taxonomy')
    ↓
Hook: set_object_terms
    ↓
FairPlay_LMS_Courses_Controller::sync_categories_to_structures()
    ↓
get_linked_channel(categoria_id) → canal_id
    ↓
apply_structure_cascade([], [], [canal_id], [], [])
    ↓
Obtener empresas del canal → [empresa_id]
Obtener ciudades de la empresa → [ciudad_id]
Obtener sucursales del canal → [sucursal_id]
Obtener cargos de la sucursal → [cargo_id]
    ↓
update_post_meta(curso_id, 'fplms_course_cities', [ciudad_id])
update_post_meta(curso_id, 'fplms_course_companies', [empresa_id])
update_post_meta(curso_id, 'fplms_course_channels', [canal_id])
update_post_meta(curso_id, 'fplms_course_branches', [sucursal_id])
update_post_meta(curso_id, 'fplms_course_roles', [cargo_id])
    ↓
FairPlay_LMS_Audit_Logger::log_action(
    'course_structures_synced_from_categories',
    'course',
    curso_id,
    'Título del Curso',
    [old_categories],
    [new_categories, channels, cascaded_structures]
)
    ↓
send_course_update_notifications(curso_id, [], cascaded_structures)
    ↓
RESULTADO: Estructuras completas asignadas + Notificaciones enviadas
```

---

## 🧪 TESTING

### Test 1: Sincronización Canal → Categoría

**Precondición:** Plugin activado

**Pasos:**
1. Crear canal "TEST SYNC"
2. Verificar en BD: `SELECT * FROM wp_termmeta WHERE meta_key = 'fplms_linked_category_id'`
3. Verificar categoría: `SELECT * FROM wp_terms WHERE slug LIKE '%fplms-test-sync%'`

**Resultado Esperado:**
- Categoría existe con slug `fplms-test-sync`
- Termmeta tiene vinculación bidireccional
- Log en auditoría con acción `channel_category_sync`

### Test 2: Cascada desde Course Builder

**Precondición:** Canal vinculado a categoría

**Pasos:**
1. Crear curso con Course Builder
2. Asignar categoría sincronizada
3. Verificar `SELECT * FROM wp_postmeta WHERE post_id = X AND meta_key LIKE 'fplms_course_%'`

**Resultado Esperado:**
- 5 filas de post_meta (cities, companies, channels, branches, roles)
- Valores contienen arrays serializados con term IDs
- Log en auditoría con acción `course_structures_synced_from_categories`

### Test 3: Interfaz de Auditoría

**Precondición:** Logs generados

**Pasos:**
1. Ir a `FairPlay LMS → Bitácora`
2. Aplicar filtros por acción
3. Ver detalles de un log
4. Exportar CSV

**Resultado Esperado:**
- Estadísticas correctas en tarjetas
- Tabla muestra logs filtrados
- Detalles expandibles funcionan
- CSV descarga correctamente con UTF-8 BOM

---

## 🚀 RENDIMIENTO

### Consideraciones de Optimización

**Índices de BD:**
- 8 índices en `wp_fplms_audit_log` para consultas rápidas
- Índice compuesto `(entity_type, entity_id, action)` para filtros múltiples

**Prevención de N+1 Queries:**
- Cascada usa queries por lote con `get_term_companies()`, `get_term_cities()`, etc.
- No queries dentro de loops de términos

**Caché:**
- WordPress object cache automático para términos
- `get_term()` usa cache nativo de WP

**Limpieza de Logs:**
- Método `cleanup_old_logs(90)` para eliminar registros antiguos
- Recomendado: Cron mensual

---

## 📈 MÉTRICAS

### Datos Registrados en Auditoría

Por cada acción:
- ✅ Timestamp preciso (DATETIME)
- ✅ Usuario (ID + nombre)
- ✅ Acción (tipo predefinido)
- ✅ Entidad (tipo + ID + título)
- ✅ Valor anterior y nuevo (serializados si necesario)
- ✅ IP del cliente
- ✅ User Agent completo

### Estadísticas Disponibles

- Total de registros
- Desglose por acción
- Top 10 usuarios más activos
- Desglose por tipo de entidad
- Rango temporal personalizado

---

## 🔄 FLUJO DE ACTUALIZACIÓN

### Actualizaciones Futuras

**Para agregar nueva acción de auditoría:**
1. Llamar `$audit->log_action('nueva_accion', 'tipo', $id, $titulo, $old, $new)`
2. Agregar traducción en `format_action()` de `FairPlay_LMS_Audit_Admin`
3. Agregar opción en filtro de interfaz

**Para modificar cascada:**
1. Editar `apply_structure_cascade()` en `FairPlay_LMS_Courses_Controller`
2. Actualizar tests
3. Documentar cambio

**Para agregar filtro en bitácora:**
1. Agregar campo en `render_filters()`
2. Agregar condición WHERE en `get_logs()`
3. Mantener en paginación

---

## 📝 NOTAS TÉCNICAS

### Compatibilidad

- ✅ WordPress 5.8+
- ✅ PHP 7.4+
- ✅ MasterStudy LMS 3.x
- ✅ MySQL 5.7+ / MariaDB 10.2+

### Dependencias

- WordPress Core: `wp_insert_term()`, `wp_update_term()`, `wp_set_object_terms()`
- MasterStudy: Taxonomía `stm_lms_course_taxonomy`, Post Type `stm-courses`
- FairPlay: Taxonomías personalizadas (`fplms_city`, `fplms_company`, etc.)

### Limitaciones Conocidas

- Sincronización solo para canales (no otras estructuras por ahora)
- Auditoría crece indefinidamente sin limpieza automática
- No sincronización inversa (editar categoría no afecta canal)
- Course Builder debe usar categorías (no tags u otras taxonomías)

---

## 🎓 RECURSOS ADICIONALES

- [ANALISIS_COURSE_BUILDER_SINCRONIZACION.md](ANALISIS_COURSE_BUILDER_SINCRONIZACION.md) - Análisis técnico inicial
- [INSTRUCCIONES_DESPLIEGUE_SYNC.md](INSTRUCCIONES_DESPLIEGUE_SYNC.md) - Guía de despliegue paso a paso
- [INSTRUCCIONES_VERIFICACION_BD.md](INSTRUCCIONES_VERIFICACION_BD.md) - Verificación de guardado en BD

---

**Fin de Documentación Técnica**  
Versión 1.0.0 - 2026-02-16
