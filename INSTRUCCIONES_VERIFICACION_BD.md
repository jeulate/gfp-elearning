# 🔍 VERIFICACIÓN INMEDIATA: Guardado en BD + Logging

**Fecha:** 2026-02-16  
**Objetivo:** Verificar que las estructuras se guardan correctamente en la base de datos

---

## ✅ PASO 1: Activar Debug Log

Edita el archivo `wp-config.php` y agrega/modifica estas líneas:

```php
// Habilitar modo debug
define('WP_DEBUG', true);

// Guardar errores en archivo de log
define('WP_DEBUG_LOG', true);

// No mostrar errores en pantalla (para producción)
define('WP_DEBUG_DISPLAY', false);
@ini_set('display_errors', 0);
```

El log se guardará en: `wp-content/debug.log`

---

## ✅ PASO 2: Probar Guardado

### A. Crear curso de prueba

1. Ve a: `FairPlay LMS → Cursos → ➕ Crear Nuevo Curso`
2. (Si te redirige a Course Builder, úsalo)
3. En Course Builder:
   - Título: `TEST LOGGING - [FECHA]`
   - En el campo "Category", selecciona cualquier categoría
4. Guarda el curso

### B. Revisar el log

```bash
# En el servidor, ejecuta:
tail -f wp-content/debug.log
```

O descarga el archivo y ábrelo con editor de texto.

**Busca líneas que digan:**
```
=== FPLMS: Guardando estructuras ===
Curso ID: 123
Título: TEST LOGGING - 2026-02-16
...
```

---

## ✅ PASO 3: Verificar en Base de Datos

### Opción A: phpMyAdmin

1. Abre phpMyAdmin
2. Selecciona tu base de datos
3. Ejecuta esta query:

```sql
SELECT 
    p.ID,
    p.post_title,
    p.post_status,
    pm1.meta_value as fplms_cities,
    pm2.meta_value as fplms_companies,
    pm3.meta_value as fplms_channels
FROM wp_posts p
LEFT JOIN wp_postmeta pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'fplms_course_cities'
LEFT JOIN wp_postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'fplms_course_companies'
LEFT JOIN wp_postmeta pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'fplms_course_channels'
WHERE p.post_type = 'stm-courses'
AND p.post_title LIKE '%TEST LOGGING%'
ORDER BY p.ID DESC
LIMIT 5;
```

### Opción B: Plugin Query Monitor

Si tienes el plugin "Query Monitor" instalado:
1. Actívalo
2. Crea/edita un curso
3. Ve a la barra superior → "QM" → "Database Queries"
4. Busca queries con `fplms_course_`

---

## 📊 RESULTADOS ESPERADOS

### Si TODO funciona correctamente:

**En debug.log verás:**
```
=== FPLMS: Guardando estructuras ===
Curso ID: 882
Título: TEST LOGGING - 2026-02-16
Status: publish
Usuario: 1 (admin)
Estructuras INPUT:
  - Ciudades: [1,2]
  - Empresas: [3]
  - Canales: [5]
Estructuras DESPUÉS DE CASCADA:
  - Ciudad

es: [1,2]
  - Empresas: [3,4,5]
  - Canales: [5,6,7,8]
  - Sucursales: [10,11,12]
  - Cargos: [20,21,22]
Post meta actualizado correctamente
=== Fin guardado ===
```

**En la BD verás:**
```
| ID  | post_title              | fplms_cities | fplms_channels |
|-----|-------------------------|--------------|----------------|
| 882 | TEST LOGGING 2026-02-16 | a:2:{i:0;i:1;i:1;i:2;} | a:3:{i:0;i:5;i:1;i:6;i:2;i:7;} |
```

### Si NO funciona:

#### Problema A: No aparece nada en debug.log
**Causa:** El hook `save_post` no se está ejecutando  
**Solución:** Verificar que el filtro de editor clásico no está bloqueando

#### Problema B: Aparece log pero valores vacíos
**Causa:** La meta box no está enviando datos  
**Solución:** Course Builder necesita integración adicional

#### Problema C: Aparece log pero no se guarda en BD
**Causa:** Error en `update_post_meta`  
**Solución:** Verificar permisos de BD

---

## 🎯 PRÓXIMO PASO SEGÚN RESULTADO

### ✅ Si TODO funciona:
→ **Proceder con Sincronización Canal → Categoría**

### ⚠️ Si hay problemas:
→ **Reportar qué aparece en el log y en la BD**

---

## 🔧 DEBUGGING ADICIONAL

Si necesitas más información, activa este logging también:

### En `register_structures_meta_box()`:
```php
public function register_structures_meta_box(): void {
    error_log('FPLMS: Registrando meta box de estructuras');
    
    add_meta_box(
        'fplms_course_structures_metabox',
        '🏢 Asignar Estructuras FairPlay',
        [ $this, 'render_structures_meta_box' ],
        FairPlay_LMS_Config::MS_PT_COURSE,
        'side',
        'default'
    );
    
    error_log('FPLMS: Meta box registrada correctamente');
}
```

### En `render_structures_meta_box()`:
```php
public function render_structures_meta_box( $post ): void {
    error_log('FPLMS: Renderizando meta box para curso ID: ' . $post->ID);
    
    // ... resto del código
}
```

---

## 📸 Capturas Sugeridas

Por favor, toma capturas de:

1. **Course Builder** mostrando el campo de categorías
2. **Debug log** con las líneas de "Guardando estructuras"
3. **Resultado de la query SQL** en phpMyAdmin
4. **Cualquier error** que aparezca

---

¿Puedes ejecutar estos pasos y reportarme qué ves en el debug.log y en la BD?
