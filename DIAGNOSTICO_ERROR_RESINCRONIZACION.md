# 🔧 Diagnóstico y Solución: Error en Resincronización de Cursos

**Fecha:** 16 de febrero de 2026  
**Usuario reportó:** "cuando intento resincronizar cursos sale error"

---

## 🎯 Objetivo

Diagnosticar y corregir errores que aparecen al hacer clic en **"↳ Resincronizar Cursos"** en el menú de FairPlay LMS.

---

## 🔍 Paso 1: Identificar el Error Exacto

### Habilitar Modo Debug en WordPress

1. Editar archivo: `wp-config.php`
2. Buscar estas líneas:
   ```php
   define( 'WP_DEBUG', false );
   ```

3. Reemplazar con:
   ```php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   define( 'WP_DEBUG_DISPLAY', false );
   @ini_set( 'display_errors', 0 );
   ```

4. Guardar archivo

### Reproducir el Error

1. Ir a **WordPress Admin** → **FairPlay LMS** → **↳ Resincronizar Cursos**
2. Clic en **"Resincronizar Todos los Cursos"**
3. Si aparece error, tomar captura de pantalla

### Revisar Log de Errores

**Ubicación del log:** `wp-content/debug.log`

```bash
# Ver últimas líneas del log
tail -n 50 wp-content/debug.log
```

**Buscar errores como:**
- `PHP Fatal error`
- `PHP Warning`
- `Call to undefined method`
- `Uncaught Error`

---

## 🐛 Errores Comunes y Soluciones

### Error 1: "Call to undefined method FairPlay_LMS_Courses_Controller::apply_structure_cascade()"

**Causa:** Método no está definido como público o no existe

**Solución:**
```php
// Verificar en class-fplms-courses.php que existe:
public function apply_structure_cascade( int $course_id, array $structures ): array
```

Si el método está como `private`, cambiar a `public`:
```php
// ANTES:
private function apply_structure_cascade( ... )

// DESPUÉS:
public function apply_structure_cascade( ... )
```

**Ubicación:** `class-fplms-courses.php` línea ~3139

---

### Error 2: "Cannot pass parameter by reference"

**Causa:** PHP 8.x no permite pasar parámetros por referencia en llamadas dinámicas

**Solución:** Verificar que el constructor no tiene problemas:
```php
// Constructor correcto en class-fplms-courses.php
public function __construct( FairPlay_LMS_Structures_Controller $structures = null ) {
    $this->structures = $structures;
}
```

---

### Error 3: "Maximum execution time exceeded"

**Causa:** Muchos cursos para resincronizar

**Solución Temporal:**
1. Editar `resync-all-courses.php`
2. Agregar al inicio:
   ```php
   set_time_limit( 300 ); // 5 minutos
   ini_set( 'memory_limit', '512M' );
   ```

**Solución Permanente:** Implementar resincronización por lotes (batches)

---

### Error 4: "Call to a member function on null"

**Causa:** `$structures_controller` no se inicializó correctamente

**Solución:** Verificar en `resync-all-courses.php` línea 37:
```php
$structures_controller = new FairPlay_LMS_Structures_Controller();
$courses_controller = new FairPlay_LMS_Courses_Controller( $structures_controller );
```

Agregar validación:
```php
if ( ! $structures_controller || ! $courses_controller ) {
    wp_die( 'Error: No se pudieron inicializar los controladores.' );
}
```

---

### Error 5: "Invalid nonce"

**Causa:** Sesión expirada o formulario antiguo

**Solución:** Refrescar la página (F5) y volver a intentar

---

### Error 6: "Database error"

**Causa:** Problema al guardar en `post_meta` o `wp_fplms_audit_log`

**Solución:** Verificar permisos de base de datos:
```sql
SHOW GRANTS FOR CURRENT_USER;
```

Debería incluir: `INSERT, UPDATE, DELETE, SELECT`

---

## 🔧 Código de Diagnóstico Automático

Crear archivo temporal: **`diagnose-resync.php`** en la raíz del plugin

```php
<?php
/**
 * Script de diagnóstico para resincronización
 * Ejecutar desde: wp-admin/admin.php?page=diagnose-resync
 */

if ( ! defined( 'ABSPATH' ) ) {
    require_once '../../../wp-load.php';
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'No tienes permisos.' );
}

echo '<h1>🔍 Diagnóstico de Resincronización</h1>';

// Test 1: Verificar clases existen
echo '<h2>Test 1: Clases</h2>';
if ( class_exists( 'FairPlay_LMS_Structures_Controller' ) ) {
    echo '✅ FairPlay_LMS_Structures_Controller existe<br>';
} else {
    echo '❌ FairPlay_LMS_Structures_Controller NO ENCONTRADA<br>';
}

if ( class_exists( 'FairPlay_LMS_Courses_Controller' ) ) {
    echo '✅ FairPlay_LMS_Courses_Controller existe<br>';
} else {
    echo '❌ FairPlay_LMS_Courses_Controller NO ENCONTRADA<br>';
}

// Test 2: Instanciar controladores
echo '<h2>Test 2: Instanciación</h2>';
try {
    $structures = new FairPlay_LMS_Structures_Controller();
    echo '✅ Structures controller instanciado<br>';
    
    $courses = new FairPlay_LMS_Courses_Controller( $structures );
    echo '✅ Courses controller instanciado<br>';
} catch ( Exception $e ) {
    echo '❌ Error: ' . $e->getMessage() . '<br>';
}

// Test 3: Verificar método apply_structure_cascade existe
echo '<h2>Test 3: Métodos</h2>';
if ( method_exists( $courses, 'apply_structure_cascade' ) ) {
    echo '✅ Método apply_structure_cascade existe<br>';
    
    $reflection = new ReflectionMethod( $courses, 'apply_structure_cascade' );
    if ( $reflection->isPublic() ) {
        echo '✅ Método es público<br>';
    } else {
        echo '❌ Método NO es público (cambiar a public)<br>';
    }
} else {
    echo '❌ Método apply_structure_cascade NO ENCONTRADO<br>';
}

// Test 4: Contar cursos
echo '<h2>Test 4: Cursos</h2>';
$courses_count = wp_count_posts( FairPlay_LMS_Config::MS_PT_COURSE );
echo 'Total de cursos: ' . $courses_count->publish . ' publicados<br>';

// Test 5: Verificar base de datos
echo '<h2>Test 5: Base de Datos</h2>';
global $wpdb;
$audit_table = $wpdb->prefix . 'fplms_audit_log';
$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$audit_table'" );
if ( $table_exists ) {
    echo '✅ Tabla wp_fplms_audit_log existe<br>';
} else {
    echo '❌ Tabla wp_fplms_audit_log NO EXISTE<br>';
}

// Test 6: Verificar límites PHP
echo '<h2>Test 6: Configuración PHP</h2>';
echo 'max_execution_time: ' . ini_get( 'max_execution_time' ) . ' segundos<br>';
echo 'memory_limit: ' . ini_get( 'memory_limit' ) . '<br>';
echo 'post_max_size: ' . ini_get( 'post_max_size' ) . '<br>';

echo '<hr>';
echo '<p><strong>Si todos los tests muestran ✅, el problema puede estar en:</strong></p>';
echo '<ul>';
echo '<li>Datos corruptos en base de datos</li>';
echo '<li>Conflicto con otro plugin</li>';
echo '<li>Error específico en un curso en particular</li>';
echo '</ul>';
echo '<p><a href="admin.php?page=resync-all-courses">Volver a Resincronizar Cursos</a></p>';
```

**Uso:**
1. Subir archivo a: `wp-content/plugins/fairplay-lms-masterstudy-extensions/`
2. Ir a: `wp-admin/admin.php?page=diagnose-resync` (NO funcionará directamente, necesita registrarse como página admin)

**Alternativa más simple:** Copiar código en `resync-all-courses.php` temporalmente antes de la resincronización

---

## 🩺 Diagnóstico Paso a Paso Manual

### Opción A: Verificar Constructor

1. Abrir: `class-fplms-courses.php`
2. Buscar línea ~13:
   ```php
   public function __construct( FairPlay_LMS_Structures_Controller $structures = null ) {
       $this->structures = $structures;
   }
   ```
3. Verificar que sea **exactamente** así (incluyendo `= null`)

### Opción B: Verificar Método apply_structure_cascade

1. Abrir: `class-fplms-courses.php`
2. Buscar línea ~3139:
   ```php
   public function apply_structure_cascade( int $course_id, array $structures ): array {
   ```
3. Verificar que dice **`public`** (no `private`)

### Opción C: Verificar Llamada en resync-all-courses.php

1. Abrir: `resync-all-courses.php`
2. Buscar línea ~37:
   ```php
   $structures_controller = new FairPlay_LMS_Structures_Controller();
   $courses_controller = new FairPlay_LMS_Courses_Controller( $structures_controller );
   ```
3. Debe ser exactamente así

### Opción D: Probar con UN curso manualmente

Agregar código temporal en `resync-all-courses.php` después de línea 38:

```php
// TEST: Probar con un solo curso primero
$test_course_id = 123; // Cambiar por ID de curso real
$test_course = get_post( $test_course_id );

if ( $test_course ) {
    echo '<div class="notice notice-info">';
    echo '<p>🧪 Probando con curso: ' . get_the_title( $test_course_id ) . '</p>';
    
    $category_ids = wp_get_object_terms( $test_course_id, 'stm_lms_course_taxonomy', [ 'fields' => 'ids' ] );
    echo '<p>Categorías encontradas: ' . count( $category_ids ) . '</p>';
    
    if ( ! empty( $category_ids ) ) {
        foreach ( $category_ids as $cat_id ) {
            $channel_id = $structures_controller->get_linked_channel( $cat_id );
            echo '<p>Categoría ' . $cat_id . ' → Canal: ' . ( $channel_id ? $channel_id : 'SIN CANAL' ) . '</p>';
        }
    }
    
    echo '</div>';
}
// Comentar resto del código para probar solo esto
exit;
```

---

## ✅ Solución Rápida: Refrescar Todo

Si ningún diagnóstico funciona, intentar:

### 1. Desactivar y Reactivar Plugin

1. **WP Admin** → **Plugins**
2. Desactivar **FairPlay LMS Extensions**
3. Esperar 3 segundos
4. Activar nuevamente

### 2. Limpiar Caché

```bash
# Si usas caché de objetos (Redis/Memcached)
wp cache flush

# Si usas plugin de caché
wp plugin deactivate w3-total-cache --network
wp plugin activate w3-total-cache --network
```

### 3. Regenerar Archivos

```bash
# Desde terminal en la raíz de WordPress
wp rewrite flush
wp cache flush
```

---

## 📊 Tabla de Resolución Rápida

| Síntoma | Causa Probable | Solución |
|---------|---------------|----------|
| "Fatal error: Call to undefined method" | Método privado o no existe | Cambiar a `public` línea 3139 |
| "Maximum execution time" | Demasiados cursos | Agregar `set_time_limit(300)` |
| "Memory exhausted" | PHP memory insuficiente | `ini_set('memory_limit', '512M')` |
| "Invalid nonce" | Sesión expirada | Refrescar página F5 |
| Carga infinita sin error | JavaScript bloqueado | Verificar consola del navegador |
| "Database error" | Permisos DB | Verificar grants de MySQL |

---

## 🚨 Si el Problema Persiste

### Crear Caso de Soporte con Esta Información:

1. **Mensaje de error exacto** (captura de pantalla o texto copiado)
2. **Archivo:** `wp-content/debug.log` (últimas 100 líneas)
3. **Versión PHP:** (ejecutar `php -v` en terminal)
4. **Versión WordPress:** (ver en wp-admin **Dashboard** → **Actualizaciones**)
5. **Plugins activos:** Listar todos los plugins instalados
6. **Resultado del diagnóstico:** Copiar output de diagnose-resync.php

---

## 📞 Próximos Pasos

1. ✅ **Habilitar WP_DEBUG** y reproducir error
2. ✅ **Revisar debug.log** y copiar errores
3. ✅ **Ejecutar diagnóstico manual** (verificar constructor, método, llamada)
4. ✅ **Aplicar solución** según tabla de resolución rápida
5. ✅ **Probar resincronización** nuevamente
6. ✅ **Reportar resultado** si persiste el problema

---

**Última actualización:** 16 de febrero de 2026  
**Mantenedor:** Equipo FairPlay LMS
