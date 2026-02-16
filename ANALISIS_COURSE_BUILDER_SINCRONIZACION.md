# 📊 ANÁLISIS: Course Builder + Bitácora + Sincronización

**Fecha:** 2026-02-16  
**Tema:** Integración completa de estructuras con Course Builder

---

## 🔍 PROBLEMA IDENTIFICADO

### 1. Course Builder se sigue abriendo
**Causa:** MasterStudy tiene múltiples puntos de redirección al Course Builder que nuestro filtro `force_classic_editor_for_courses` no está interceptando.

**Soluciones posibles:**

#### Opción A: Desactivar redirección de MasterStudy (JavaScript)
MasterStudy probablemente usa JavaScript para redirigir. Necesitamos:
- Detectar el script que hace la redirección
- Desactivarlo solo para administradores/instructores que usen estructuras

#### Opción B: Trabajar CON Course Builder (RECOMENDADO)
En lugar de luchar contra Course Builder, integrarnos:
- Mantener el Course Builder como editor principal
- Inyectar nuestra meta box de estructuras EN el Course Builder
- Sincronizar estructuras con categorías de WordPress

---

## 📋 VERIFICACIÓN DE GUARDADO EN BD

### Logging detallado
Agregar en `save_course_structures_on_publish()`:

```php
// Logging detallado para debugging
error_log('=== FPLMS: Guardando estructuras ===');
error_log('Curso ID: ' . $post_id);
error_log('Título: ' . $post->post_title);
error_log('Status: ' . $post->post_status);
error_log('Ciudades input: ' . print_r($cities, true));
error_log('Empresas input: ' . print_r($companies, true));
error_log('Canales input: ' . print_r($channels, true));
error_log('Después de cascada:');
error_log('  - Ciudades: ' . print_r($cascaded_structures['cities'], true));
error_log('  - Empresas: ' . print_r($cascaded_structures['companies'], true));
error_log('  - Canales: ' . print_r($cascaded_structures['channels'], true));
error_log('=== Fin guardado ===');
```

### Verificación manual en BD
```sql
SELECT 
    p.ID,
    p.post_title,
    pm1.meta_value as cities,
    pm2.meta_value as companies,
    pm3.meta_value as channels
FROM wp_posts p
LEFT JOIN wp_postmeta pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'fplms_course_cities'
LEFT JOIN wp_postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'fplms_course_companies'
LEFT JOIN wp_postmeta pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'fplms_course_channels'
WHERE p.post_type = 'stm-courses'
AND p.post_status = 'publish'
ORDER BY p.ID DESC
LIMIT 10;
```

---

## 📝 BITÁCORA DE SEGUIMIENTO

### Tabla de bitácora en BD

```sql
CREATE TABLE IF NOT EXISTS wp_fplms_audit_log (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    user_name VARCHAR(255),
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT(20) UNSIGNED,
    entity_title VARCHAR(255),
    old_value TEXT,
    new_value TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    INDEX idx_timestamp (timestamp),
    INDEX idx_user_id (user_id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Eventos a registrar

| Acción | Descripción |
|--------|-------------|
| `course_created` | Curso creado con estructuras |
| `course_structures_assigned` | Estructuras asignadas a curso |
| `course_structures_updated` | Estructuras modificadas |
| `course_deleted` | Curso eliminado |
| `structure_created` | Nueva estructura creada |
| `structure_updated` | Estructura modificada |
| `structure_deleted` | Estructura eliminada |
| `user_assigned_structure` | Usuario asignado a estructura |
| `notification_sent` | Email enviado a usuarios |
| `permission_denied` | Intento de bypass de permisos |

### Vista de bitácora para admin

Crear nueva página en: `FairPlay LMS → Bitácora`

Funcionalidades:
- Tabla con filtros por:
  - Fecha (rango)
  - Usuario
  - Acción
  - Tipo de entidad
- Exportar a CSV/Excel
- Ver detalles de cada acción
- Buscar por curso/estructura específica

---

## 🎯 ESTRATEGIA RECOMENDADA: Sincronización Canales → Categorías

### Concepto

**Cada canal crea/actualiza automáticamente una categoría de WordPress asociada.**

### Ventajas

1. ✅ **Course Builder funciona nativamente**
   - Muestra categorías en el selector
   - No necesita modificación del Course Builder
   
2. ✅ **Doble tracking**
   - Canales en taxonomía custom `fplms_channel`
   - Categorías en taxonomía nativa `course-category`
   
3. ✅ **Reportes más fáciles**
   - Queries estándar de WordPress
   - Compatible con plugins de reporting

4. ✅ **SEO mejorado**
   - URLs de categorías nativas: `/curso-categoria/canal-adidas/`
   - Better indexing

### Desventajas

⚠️ **Complejidad de sincronización**
- Mantener 2 sistemas sincronizados
- Si se crea categoría manual, puede desincronizar

⚠️ **Riesgo de duplicados**
- Si existe categoría con mismo nombre

### Implementación

#### 1. Hook al crear/editar canal

```php
// En class-fplms-structures.php, método handle_form()

if ('create' === $action && FairPlay_LMS_Config::TAX_CHANNEL === $taxonomy) {
    // Después de crear el canal
    $channel_term = wp_insert_term($name, $taxonomy);
    
    if (!is_wp_error($channel_term)) {
        $channel_id = $channel_term['term_id'];
        
        // Crear categoría de curso asociada
        $category_name = $name; // Mismo nombre
        $category = wp_insert_term($category_name, 'stm_lms_course_taxonomy'); // Taxonomía de MasterStudy
        
        if (!is_wp_error($category)) {
            // Guardar relación bidireccional
            update_term_meta($channel_id, 'fplms_linked_category_id', $category['term_id']);
            update_term_meta($category['term_id'], 'fplms_linked_channel_id', $channel_id);
        }
    }
}
```

#### 2. Sincronización automática al guardar curso

```php
// En save_course_structures_on_publish()

// Después de guardar estructuras
$channel_ids = $cascaded_structures['channels'];

foreach ($channel_ids as $channel_id) {
    // Obtener categoría asociada
    $category_id = get_term_meta($channel_id, 'fplms_linked_category_id', true);
    
    if ($category_id) {
        // Asignar categoría al curso
        wp_set_post_terms($post_id, [$category_id], 'stm_lms_course_taxonomy', true);
    }
}
```

#### 3. Sincronización inversa (si se edita en Course Builder)

```php
// Hook cuando se guarda categoría desde Course Builder
add_action('set_object_terms', 'fplms_sync_category_to_channel', 10, 6);

function fplms_sync_category_to_channel($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
    if ($taxonomy !== 'stm_lms_course_taxonomy') return;
    
    foreach ($tt_ids as $term_id) {
        // Buscar canal asociado
        $channel_id = get_term_meta($term_id, 'fplms_linked_channel_id', true);
        
        if ($channel_id) {
            // Actualizar meta del curso
            $current_channels = get_post_meta($object_id, 'fplms_course_channels', true) ?: [];
            if (!in_array($channel_id, $current_channels)) {
                $current_channels[] = $channel_id;
                update_post_meta($object_id, 'fplms_course_channels', $current_channels);
            }
        }
    }
}
```

---

## 🏗️ ARQUITECTURA PROPUESTA

```
┌─────────────────────────────────────────────────────────────┐
│                    ADMIN CREA CANAL                         │
│                                                             │
│  FairPlay Estructuras → Crear "Canal Adidas"              │
│                              │                              │
│                              ▼                              │
│                    ┌──────────────────┐                    │
│                    │ fplms_channel    │                    │
│                    │ ID: 5            │                    │
│                    │ Name: Adidas     │                    │
│                    └────────┬─────────┘                    │
│                             │                              │
│                             │ Auto-crear                   │
│                             ▼                              │
│                    ┌──────────────────┐                    │
│                    │ course-category  │                    │
│                    │ ID: 10           │                    │
│                    │ Name: Adidas     │                    │
│                    └──────────────────┘                    │
│                                                             │
│           Relación bidireccional guardada en term_meta      │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│              INSTRUCTOR ASIGNA CURSO                        │
│                                                             │
│  Course Builder → Selecciona "Categoría: Adidas"           │
│                              │                              │
│                              ▼                              │
│                    Guarda en BD:                           │
│                    - course-category: 10                    │
│                              │                              │
│                              │ Hook automático              │
│                              ▼                              │
│                    Actualiza:                              │
│                    - fplms_course_channels: [5]            │
│                                                             │
│           Sistema sincronizado automáticamente             │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                  REPORTES Y CONSULTAS                       │
│                                                             │
│  Query por canal:                                          │
│  - Opción A: SELECT * FROM wp_postmeta                     │
│              WHERE meta_key='fplms_course_channels'        │
│  - Opción B: SELECT * FROM wp_term_relationships           │
│              WHERE taxonomy='course-category'              │
│                                                             │
│  Ambas opciones dan el mismo resultado ✅                  │
└─────────────────────────────────────────────────────────────┘
```

---

## 🚀 PLAN DE IMPLEMENTACIÓN

### Fase 1: Verificación y Logging (AHORA)
1. ✅ Agregar logging detallado
2. ✅ Verificar que se guarda en BD
3. ✅ Probar con Course Builder

### Fase 2: Bitácora de Auditoría (1-2 días)
1. Crear tabla `wp_fplms_audit_log`
2. Implementar clase `FairPlay_LMS_Audit_Logger`
3. Registrar todos los eventos
4. Crear página de visualización

### Fase 3: Sincronización Canales → Categorías (2-3 días)
1. Hook al crear canal → crear categoría
2. Hook al guardar curso → sincronizar categoría
3. Hook inverso (categoría → canal)
4. Interfaz de re-sincronización manual

### Fase 4: Integración Course Builder (2-3 días)
1. Detectar campo de categoría en Course Builder
2. Agregar tooltip: "Esta categoría está sincronizada con Canal X"
3. Mostrar estructuras adicionales en sidebar
4. AJAX para ver estructura completa

---

## ✅ DECISIÓN RECOMENDADA

### Opción A: Solo meta_post (actual)
**Pros:**
- Ya implementado
- Control total

**Contras:**
- Course Builder no muestra estructuras
- Requiere modificar Course Builder

### Opción B: Sincronización dual (RECOMENDADO)
**Pros:**
- Course Builder funciona nativamente
- Doble seguridad
- SEO mejorado
- Reportes más fáciles

**Contras:**
- Más complejidad
- Riesgo de desincronización

### Opción C: Solo categorías
**Pros:**
- Simple
- Nativamente compatible

**Contras:**
- Perdemos flexibilidad de estructuras custom
- No podemos tener jerarquía completa

---

## 🎯 MI RECOMENDACIÓN FINAL

**Implementar Opción B: Sincronización Dual**

**Razones:**
1. Ya tienes el sistema de estructuras funcionando
2. Course Builder seguirá usándose (usuarios acostumbrados)
3. Mejor de ambos mundos
4. Escalable para futuros reportes

**Orden de ejecución:**
1. Primero: Agregar logging y verificar BD (15 min)
2. Segundo: Implementar sincronización Canal → Categoría (1 día)
3. Tercero: Bitácora de auditoría (1 día)
4. Cuarto: Mejorar Course Builder UI (1 día)

---

¿Procedo con la implementación de la sincronización dual?
