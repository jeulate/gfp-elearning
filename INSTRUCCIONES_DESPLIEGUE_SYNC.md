# 🚀 INSTRUCCIONES DE DESPLIEGUE - Sincronización Canales ↔ Categorías

**Fecha:** 2026-02-16  
**Versión:** 1.0.0  
**Objetivo:** Implementar sincronización automática entre canales y categorías + sistema de auditoría

---

## 📦 ARCHIVOS NUEVOS CREADOS

Los siguientes archivos fueron creados y deben subirse al servidor:

1. ✅ `includes/class-fplms-audit-logger.php` - Clase de auditoría
2. ✅ `admin/class-fplms-audit-admin.php` - Interfaz administrativa de bitácora

## 📝 ARCHIVOS MODIFICADOS

Los siguientes archivos fueron modificados:

1. ✅ `includes/class-fplms-structures.php` - Sincronización canales → categorías
2. ✅ `includes/class-fplms-courses.php` - Detección de categorías y cascada
3. ✅ `includes/class-fplms-plugin.php` - Registro de hooks
4. ✅ `fairplay-lms-masterstudy-extensions.php` - Requires y creación de tabla

---

## 🔧 PASO A PASO - DESPLIEGUE EN DROPLET

### **PASO 1: Hacer Commit de los Cambios Locales**

Desde tu máquina local (PowerShell en `d:\Programas\gfp-elearning`):

```powershell
# 1. Ver qué archivos cambiaron
git status

# 2. Agregar todos los archivos modificados y nuevos
git add .

# 3. Hacer commit
git commit -m "feat: Sincronización canales-categorías + sistema de auditoría

- Agregar FairPlay_LMS_Audit_Logger para bitácora
- Agregar FairPlay_LMS_Audit_Admin para interfaz admin
- Sincronización automática canal → categoría
- Detección de categorías en Course Builder
- Aplicación automática de cascada estructural
- Tabla wp_fplms_audit_log con auditoría completa"

# 4. Subir al repositorio
git push origin main
```

---

### **PASO 2: Actualizar en el Servidor**

Conéctate por SSH a tu droplet:

```bash
# 1. Navegar al directorio del plugin
cd /var/www/wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions

# 2. Hacer backup ANTES de actualizar
cp -r . ../fairplay-lms-backup-$(date +%Y%m%d_%H%M%S)

# 3. Actualizar desde Git
git fetch origin
git pull origin main

# 4. Verificar que los archivos nuevos existen
ls -lh includes/class-fplms-audit-logger.php
ls -lh admin/class-fplms-audit-admin.php

# 5. Verificar permisos (deben ser www-data)
sudo chown -R www-data:www-data /var/www/wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions
sudo chmod -R 755 /var/www/wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions
```

---

### **PASO 3: Desactivar y Reactivar el Plugin**

Esto ejecutará el hook de activación que crea la tabla de auditoría:

**Opción A - Desde el navegador (RECOMENDADO):**

1. Ve a: `http://TU-DOMINIO/wp-admin/plugins.php`
2. Busca **"FairPlay LMS – MasterStudy Extensions"**
3. Haz clic en **"Desactivar"**
4. Espera 2 segundos
5. Haz clic en **"Activar"**

**Opción B - Desde SSH con WP-CLI:**

```bash
# Desactivar
wp plugin deactivate fairplay-lms-masterstudy-extensions

# Activar (esto crea la tabla de auditoría)
wp plugin activate fairplay-lms-masterstudy-extensions
```

---

### **PASO 4: Verificar que la Tabla se Creó**

En SSH, conecta a MySQL:

```bash
mysql -u root -p
```

Luego ejecuta:

```sql
USE boostacademy_bd;

-- Ver si existe la tabla
SHOW TABLES LIKE '%fplms_audit_log%';

-- Debe mostrar: wp_fplms_audit_log

-- Ver estructura de la tabla
DESCRIBE wp_fplms_audit_log;

-- Debe mostrar 12 columnas:
-- id, timestamp, user_id, user_name, action, entity_type, 
-- entity_id, entity_title, old_value, new_value, ip_address, user_agent

exit;
```

---

### **PASO 5: Crear un Canal de Prueba**

Esto verificará que la sincronización canal → categoría funciona:

1. Ve a: `http://TU-DOMINIO/wp-admin/admin.php?page=fairplay-lms&tab=structures`
2. En la pestaña **"Canales"**, haz clic en **"+ Crear Nuevo Canal"**
3. Nombre: `CANAL PRUEBA SYNC - 16 FEB`
4. Descripción: `Prueba sincronización automática`
5. Selecciona una empresa padre
6. Haz clic en **"Guardar"**

---

### **PASO 6: Verificar Sincronización**

Ejecuta en MySQL:

```sql
USE boostacademy_bd;

-- 1. Obtener el ID del canal recién creado
SELECT term_id, name, slug 
FROM wp_terms 
WHERE name LIKE '%CANAL PRUEBA SYNC%';

-- Anota el term_id, por ejemplo: 999

-- 2. Ver si tiene una categoría vinculada
SELECT meta_key, meta_value 
FROM wp_termmeta 
WHERE term_id = 999 
AND meta_key = 'fplms_linked_category_id';

-- Debe mostrar un meta_value con el ID de la categoría creada

-- 3. Verificar que la categoría existe
SELECT t.term_id, t.name, t.slug, tt.taxonomy
FROM wp_terms t
JOIN wp_term_taxonomy tt ON t.term_id = tt.term_id
WHERE t.slug LIKE '%fplms-canal-prueba%'
AND tt.taxonomy = 'stm_lms_course_taxonomy';

-- Debe mostrar la categoría con slug: fplms-canal-prueba-sync-16-feb

-- 4. Ver el log de auditoría
SELECT * FROM wp_fplms_audit_log 
ORDER BY id DESC 
LIMIT 5;

exit;
```

---

### **PASO 7: Probar Course Builder con Categorías**

1. Ve a: `http://TU-DOMINIO/wp-admin/post-new.php?post_type=stm-courses`
2. **Course Builder debería abrirse automáticamente**
3. Rellena:
   - **Título:** `CURSO PRUEBA SYNC - 16 FEB 2026`
   - En el panel derecho busca **"Category"**
   - Selecciona la categoría: `CANAL PRUEBA SYNC - 16 FEB`
4. Haz clic en **"Publish"**
5. **Anota el ID del curso** (aparece en la URL: `post=XXXXX`)

---

### **PASO 8: Verificar Aplicación de Cascada**

Ejecuta en MySQL (REEMPLAZA 53697 con el ID del curso que creaste):

```sql
USE boostacademy_bd;

-- Ver las estructuras asignadas automáticamente
SELECT 
    meta_key,
    meta_value
FROM wp_postmeta
WHERE post_id = 53697
AND meta_key LIKE 'fplms_course_%'
ORDER BY meta_key;

-- Deberías ver:
-- fplms_course_channels   => Array con el canal
-- fplms_course_companies  => Array con la empresa (cascada)
-- fplms_course_cities     => Array con la ciudad (cascada)
-- fplms_course_branches   => Array con sucursales (cascada)
-- fplms_course_roles      => Array con cargos (cascada)

exit;
```

---

### **PASO 9: Verificar Interfaz de Bitácora**

1. Ve a: `http://TU-DOMINIO/wp-admin/admin.php?page=fairplay-lms-audit`
2. **Deberías ver:**
   - 📊 **Estadísticas** en la parte superior
   - 🔍 **Filtros** por acción, tipo, fecha
   - 📋 **Tabla de registros** con todas las operaciones
   - 📥 **Botón "Exportar CSV"**

3. **Busca los registros:**
   - Acción: `channel_category_sync` (cuando creaste el canal)
   - Acción: `course_structures_synced_from_categories` (cuando creaste el curso)

4. **Haz clic en "👁️ Ver"** para ver detalles completos

---

## ✅ CRITERIOS DE ÉXITO

### ✅ TODO FUNCIONA si:

1. ✅ La tabla `wp_fplms_audit_log` existe en la BD
2. ✅ Al crear un canal, se crea automáticamente una categoría con slug `fplms-[nombre-canal]`
3. ✅ Los termmeta tienen `fplms_linked_category_id` y `fplms_linked_channel_id`
4. ✅ Al crear curso con Course Builder y seleccionar categoría, se guardan en `fplms_course_*`
5. ✅ La cascada aplica automáticamente (canal → empresa → ciudad)
6. ✅ La interfaz de bitácora muestra todos los registros

---

## 🐛 TROUBLESHOOTING

### Problema: Tabla de auditoría NO se crea

**Solución:**
```bash
# En SSH, crear manualmente
mysql -u root -p boostacademy_bd

# Copiar y pegar el CREATE TABLE del archivo class-fplms-audit-logger.php líneas 38-56
```

### Problema: Categoría NO se crea al crear canal

**Verificar:**
```bash
# Ver errores de PHP
tail -n 50 /var/www/wordpress/wp-content/debug.log

# Buscar errores relacionados con sync_channel_to_category
grep "sync_channel_to_category" /var/www/wordpress/wp-content/debug.log
```

### Problema: Estructuras NO se guardan desde Course Builder

**Verificar:**
```sql
-- Ver si el hook set_object_terms se ejecutó
SELECT * FROM wp_fplms_audit_log 
WHERE action = 'course_structures_synced_from_categories'
ORDER BY id DESC 
LIMIT 5;
```

### Problema: Interfaz de bitácora no aparece

**Verificar:**
```bash
# Verificar que el archivo existe
ls -lh /var/www/wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/admin/class-fplms-audit-admin.php

# Ver errores de WordPress
tail -n 50 /var/www/wordpress/wp-content/debug.log | grep "fplms-audit"
```

---

## 📊 MONITOREO POST-DESPLIEGUE

### Durante las primeras 24 horas, monitorea:

```bash
# Ver logs en tiempo real
tail -f /var/www/wordpress/wp-content/debug.log

# Cada hora, consultar cantidad de registros de auditoría
mysql -u root -p -e "SELECT COUNT(*) as total_logs FROM boostacademy_bd.wp_fplms_audit_log;"

# Ver últimos 10 logs
mysql -u root -p -e "SELECT id, timestamp, action, entity_type, entity_title FROM boostacademy_bd.wp_fplms_audit_log ORDER BY id DESC LIMIT 10;"
```

---

## 🎯 PRÓXIMOS PASOS

Una vez verificado que TODO funciona:

1. ✅ **Sincronizar canales existentes:**
   - Editar cada canal manualmente para que cree su categoría

2. ✅ **Informar a instructores:**
   - Pueden usar Course Builder normalmente
   - Las estructuras se asignan automáticamente al seleccionar categorías

3. ✅ **Establecer limpieza de logs:**
   - Configurar cron para ejecutar `cleanup_old_logs(90)` mensualmente

4. ✅ **Testing completo:**
   - Probar con usuario instructor
   - Verificar notificaciones por email
   - Validar permisos

---

## 📞 SOPORTE

Si encuentras algún problema durante el despliegue:

1. Captura el error exacto del log
2. Ejecuta las queries SQL de verificación
3. Comparte los resultados

---

## 🎉 ¡LISTO!

Cuando completes todos los pasos y verifiques que funciona:
- ✅ Marca la tarea de testing como completada
- ✅ Procede con Feature 2 (mostrar estructuras en frontend)
- ✅ Documenta cualquier ajuste fino necesario
