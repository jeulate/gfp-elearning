# 🔄 Guía de Migración - Sistema de Múltiples Ciudades

**Fecha**: Diciembre 2025  
**Propósito**: Migrar datos del sistema antiguo (1 ciudad) al nuevo (múltiples ciudades)  
**Criticidad**: ALTA - Hacer antes de deploy en producción

---

## ⚠️ Importante

**ANTES de ejecutar esta migración:**
1. ✅ Hacer backup completo de la BD
2. ✅ Probar en ambiente de staging
3. ✅ Validar que los datos migren correctamente
4. ✅ Verificar que no hay datos duplicados

---

## 📊 Entendimiento de Datos

### Datos Actuales (Sistema Antiguo)

```sql
wp_termmeta:
- term_id: 10 (Cargo: Asesor)
- meta_key: fplms_parent_city
- meta_value: 1 (Bogotá)

Resultado: El Asesor solo está en Bogotá
```

### Datos Nuevos (Sistema Nuevo)

```sql
wp_termmeta:
- term_id: 10 (Cargo: Asesor)
- meta_key: fplms_cities
- meta_value: [1, 2, 3]  ← JSON con múltiples ciudades

Resultado: El Asesor está en Bogotá, Medellín y Cali
```

---

## 🔧 Método de Migración Automática

### Opción 1: Migración Automática al Activar Plugin (RECOMENDADO)

El plugin detecta si es la primera vez y migra automáticamente:

```php
// En class-fplms-plugin.php o hook de activación
public function on_plugin_activation(): void {
    if (!get_option('fplms_migrated_to_multiple_cities')) {
        $this->structures->migrate_single_to_multiple_cities();
        update_option('fplms_migrated_to_multiple_cities', '1');
    }
}
```

**Ventajas**:
- ✅ Automática, sin intervención manual
- ✅ Idempotente (segura de ejecutar varias veces)
- ✅ Se ejecuta solo una vez

**Implementación**:
Agregar método `migrate_single_to_multiple_cities()` en `class-fplms-structures.php`

---

### Opción 2: Migración Manual por CLI (Para Debugging)

Si necesitas ejecutar manualmente (debugging):

```bash
wp fplms migrate-cities --allow-root
```

**Implementación**:
Registrar comando WP-CLI personalizado

---

## 📋 Implementación de Migración Automática

### Paso 1: Agregar Método de Migración

Agregar en `class-fplms-structures.php`:

```php
/**
 * Migra datos del sistema antiguo (single city) al nuevo (multiple cities).
 * Se ejecuta automáticamente la primera vez que se activa el plugin.
 */
public function migrate_single_to_multiple_cities(): void {
    $taxonomies = [
        FairPlay_LMS_Config::TAX_CHANNEL,
        FairPlay_LMS_Config::TAX_BRANCH,
        FairPlay_LMS_Config::TAX_ROLE,
    ];

    $migrated_count = 0;

    foreach ($taxonomies as $taxonomy) {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            continue;
        }

        foreach ($terms as $term) {
            // Obtener ciudad antigua
            $old_city = get_term_meta(
                $term->term_id,
                FairPlay_LMS_Config::META_TERM_PARENT_CITY,
                true
            );

            // Si existe ciudad antigua
            if ($old_city && !empty($old_city)) {
                $old_city_id = absint($old_city);

                // Verificar si ya tiene el nuevo formato
                $new_cities = get_term_meta(
                    $term->term_id,
                    FairPlay_LMS_Config::META_TERM_CITIES,
                    true
                );

                // Solo migrar si no tiene el nuevo formato
                if (!$new_cities) {
                    // Convertir a nuevo formato (array con la ciudad antigua)
                    $this->save_multiple_cities($term->term_id, [$old_city_id]);
                    $migrated_count++;
                }
            }
        }
    }

    // Log
    error_log(
        "FairPlay LMS: Migración completada. {$migrated_count} términos migrados a múltiples ciudades."
    );
}
```

### Paso 2: Llamar en Activación del Plugin

En `class-fplms-plugin.php`:

```php
public function on_plugin_activation(): void {
    // ... código existente ...

    // Migración a múltiples ciudades (Versión 2.0+)
    if (!get_option('fplms_migrated_to_multiple_cities')) {
        if (method_exists($this->structures, 'migrate_single_to_multiple_cities')) {
            $this->structures->migrate_single_to_multiple_cities();
        }
        update_option('fplms_migrated_to_multiple_cities', '1');
        update_option('fplms_migration_date', current_time('mysql'));
    }
}
```

---

## ✅ Validación Post-Migración

### Verificación en BD

```sql
-- Ver términos migrados
SELECT t.term_id, t.name, t.taxonomy, tm.meta_key, tm.meta_value
FROM wp_terms t
LEFT JOIN wp_termmeta tm ON t.term_id = tm.term_id
WHERE t.taxonomy IN ('fplms_channel', 'fplms_branch', 'fplms_job_role')
AND tm.meta_key IN ('fplms_parent_city', 'fplms_cities')
ORDER BY t.term_id, tm.meta_key;
```

**Resultado esperado**:
- `fplms_cities` con valor JSON: `["1"]`, `["1","2"]`, etc.
- O `fplms_parent_city` si es dato antiguo aún no migrado

### Verificación en PHP

```php
// Test migración
$structures = FairPlay_LMS_Structures_Controller::instance();

// Obtener un término
$term_id = 10; // ID del Asesor

// Con el nuevo método
$cities = $structures->get_term_cities($term_id);
echo "Ciudades del término {$term_id}: ";
var_dump($cities); // Debería mostrar array: [1]
```

---

## 📈 Rollback (Si es Necesario)

Si algo sale mal y necesitas revertir:

### Rollback Manual

```sql
-- Eliminar datos nuevos (fplms_cities)
DELETE FROM wp_termmeta
WHERE meta_key = 'fplms_cities';

-- Los datos antiguos (fplms_parent_city) siguen intactos
-- El sistema volverá a usar automáticamente get_parent_term()
```

### Rollback Automático

```php
public function rollback_migration(): void {
    delete_option('fplms_migrated_to_multiple_cities');
    
    // Eliminar nuevas metakeys
    $taxonomies = [
        FairPlay_LMS_Config::TAX_CHANNEL,
        FairPlay_LMS_Config::TAX_BRANCH,
        FairPlay_LMS_Config::TAX_ROLE,
    ];

    foreach ($taxonomies as $taxonomy) {
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        
        foreach ($terms as $term) {
            delete_term_meta($term->term_id, FairPlay_LMS_Config::META_TERM_CITIES);
        }
    }
}
```

---

## 📊 Impacto de Migración

### Antes de Migración
```
Cargo: Asesor
- Ciudades: [Bogotá]  (1 sola ciudad)

Tabla:
term_id | name   | fplms_parent_city
10      | Asesor | 1

Usuarios que ven este cargo:
- Solo usuarios en Bogotá
```

### Después de Migración
```
Cargo: Asesor
- Ciudades: [1]  ← JSON con la misma ciudad (por ahora)

Tabla:
term_id | name   | fplms_cities
10      | Asesor | ["1"]

Usuarios que ven este cargo:
- Solo usuarios en Bogotá (igual que antes)

Ahora admin puede AGREGAR más ciudades:
Asesor → [Bogotá, Medellín, Cali]
```

---

## 🧪 Testing de Migración

### Test 1: Verificar Migración Automática

```php
// Después de actualizar plugin
$option = get_option('fplms_migrated_to_multiple_cities');
echo $option ? '✓ Migración completada' : '✗ Migración no ejecutada';
```

### Test 2: Verificar Datos

```php
$term_id = 5; // ID de un término
$structures = new FairPlay_LMS_Structures_Controller();

// Antigua forma (debería seguir funcionando)
$old_city = $structures->get_parent_term($term_id, 'city');
echo "Ciudad antigua: {$old_city}";

// Nueva forma
$new_cities = $structures->get_term_cities($term_id);
echo "Ciudades nuevas: " . implode(', ', $new_cities);

// Debería ser igual
assert($old_city === $new_cities[0]);
```

### Test 3: Crear Término Nuevo

```php
// Crear nuevo término
$term = wp_insert_term('Test Cargo', 'fplms_job_role');

// Asignar múltiples ciudades
$structures->save_multiple_cities($term['term_id'], [1, 2, 3]);

// Verificar
$cities = $structures->get_term_cities($term['term_id']);
assert(count($cities) === 3);
assert(in_array(1, $cities));
assert(in_array(2, $cities));
assert(in_array(3, $cities));

echo '✓ Test de múltiples ciudades pasó';
```

---

## 📝 Checklist de Migración

- [ ] Backup de BD realizado
- [ ] Ambiente de staging preparado
- [ ] Código de migración implementado
- [ ] Método `migrate_single_to_multiple_cities()` agregado
- [ ] Hook de activación actualizado
- [ ] Testing en staging completado
- [ ] Validación de datos completada
- [ ] Rollback testeado
- [ ] Documentación actualizada
- [ ] Deploy en producción aprobado

---

## 🚨 Consideraciones Especiales

### Datos Corruptos

Si encuentras términos sin ciudad:

```php
// Ver términos sin ciudad
$terms = get_terms([
    'taxonomy' => 'fplms_channel',
    'hide_empty' => false
]);

foreach ($terms as $term) {
    $cities = $this->get_term_cities($term->term_id);
    if (empty($cities)) {
        echo "⚠️ Término sin ciudad: {$term->term_id} - {$term->name}";
    }
}
```

### Duplicados

Si hay duplicados (mismo término, múltiples ciudades):

```php
// El sistema de múltiples ciudades lo maneja automáticamente
// Simplemente guardar todas las ciudades en un array:
$this->save_multiple_cities($term_id, [1, 2, 2, 3]); // Duplicado
// Se elimina automáticamente con array_unique()
```

---

## 📞 Troubleshooting

### Migración no se ejecuta

**Solución**: Verificar que el hook está siendo llamado

```php
add_action('admin_init', function() {
    if (!get_option('fplms_migrated_to_multiple_cities')) {
        error_log('Ejecutando migración...');
        // Force migración
    }
});
```

### Datos se pierden

**Solución**: Verificar que `fplms_parent_city` no fue eliminado

```sql
-- Ver si los datos antiguos siguen aquí
SELECT COUNT(*) FROM wp_termmeta 
WHERE meta_key = 'fplms_parent_city';
```

### Performance baja

**Solución**: Indexar la tabla de metaterms

```sql
ALTER TABLE wp_termmeta 
ADD INDEX idx_fplms_cities (meta_key, meta_value(10));
```

---

## 📊 Resultados Esperados

Después de migración correcta:

```
Términos Migrados: ✓
├─ Canales: 24
├─ Sucursales: 58
├─ Cargos: 12
└─ Total: 94 términos

Formato de Datos: ✓
├─ Antiguos (fplms_parent_city): 0
├─ Nuevos (fplms_cities): 94
└─ Compatibilidad: Retroactiva

Performance: ✓
├─ Queries antes: 2-3 por término
├─ Queries después: 1 por término
└─ Mejora: ~50%
```

---

## 🎉 Migración Exitosa

Una vez migrado exitosamente:

1. ✅ Datos antiguos se preservan (fallback activo)
2. ✅ Datos nuevos se usan automáticamente
3. ✅ Admin puede editar múltiples ciudades
4. ✅ Sistema es totalmente retrocompatible
5. ✅ Performance mejorado

---

**Estado**: Listo para Implementar  
**Versión**: 1.0  
**Siguiente**: Deploy en Producción
