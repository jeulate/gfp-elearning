# 🚀 Guía Práctica de Implementación via SSH - Sistema de Auditoría

## 📋 Resumen de Pasos

1. ✅ Conectar por SSH al servidor
2. ✅ Hacer backup completo de la base de datos
3. ✅ Hacer backup de los archivos del plugin
4. ✅ Ejecutar script de migración SQL
5. ✅ Subir archivos modificados del plugin
6. ✅ Verificar que todo funciona
7. ✅ Testing básico

**Tiempo estimado**: 15-20 minutos

---

## 🔐 PASO 1: Conectar por SSH

```bash
# Conectar al servidor
ssh usuario@tu-servidor.com

# O si usas puerto específico:
ssh -p 22 usuario@tu-servidor.com

# Cambiar al directorio de WordPress
cd /ruta/a/tu/sitio/wordpress
# Ejemplo común:
cd /var/www/html
# o
cd /home/usuario/public_html
```

**Verificar ubicación**:
```bash
# Verificar que estás en el directorio correcto
ls -la | grep wp-config.php
# Debe mostrar el archivo wp-config.php
```

---

## 💾 PASO 2: Backup de Base de Datos

### 2.1 Explorar wp-config.php para obtener credenciales

```bash
# Ver credenciales de la base de datos
grep -E "DB_NAME|DB_USER|DB_PASSWORD|DB_HOST" wp-config.php

# Ejemplo de salida:
# define('DB_NAME', 'nombre_bd');
# define('DB_USER', 'usuario_bd');
# define('DB_PASSWORD', 'password_bd');
# define('DB_HOST', 'localhost');
```

**Anotar estos valores**:
- `DB_NAME`: ___________________
- `DB_USER`: ___________________
- `DB_PASSWORD`: ___________________
- `DB_HOST`: ___________________

### 2.2 Backup Completo de la Base de Datos

```bash
# Crear directorio para backups (si no existe)
mkdir -p ~/backups
cd ~/backups

# Exportar base de datos completa
# REEMPLAZAR: nombre_bd, usuario_bd, password_bd con tus valores
mysqldump -u usuario_bd -p nombre_bd > backup_completo_$(date +%Y%m%d_%H%M%S).sql

# Te pedirá la contraseña, ingresarla cuando aparezca:
# Enter password: [AQUÍ INGRESAR PASSWORD]

# Si quieres evitar ingresar password cada vez (menos seguro):
mysqldump -u usuario_bd -pPASSWORD_AQUI nombre_bd > backup_completo_$(date +%Y%m%d_%H%M%S).sql
```

**Verificar que se creó el backup**:
```bash
ls -lh ~/backups/backup_completo_*.sql

# Debe mostrar algo como:
# -rw-r--r-- 1 user user 45M Feb 16 10:30 backup_completo_20260216_103045.sql
```

### 2.3 Backup Específico de Tabla de Auditoría

```bash
# Backup solo de la tabla wp_fplms_audit_log
mysqldump -u usuario_bd -p nombre_bd wp_fplms_audit_log > backup_audit_table_$(date +%Y%m%d_%H%M%S).sql

# Verificar
ls -lh ~/backups/backup_audit_*.sql
```

### 2.4 Descargar Backups a tu PC (Opcional pero Recomendado)

**Desde tu PC local** (abrir nueva terminal):

```bash
# Descargar backup completo
scp usuario@tu-servidor.com:~/backups/backup_completo_*.sql ./

# O si usas puerto específico:
scp -P 22 usuario@tu-servidor.com:~/backups/backup_completo_*.sql ./
```

✅ **Checkpoint**: Tienes 2 archivos de backup creados

---

## 📂 PASO 3: Backup de Archivos del Plugin

```bash
# Volver al directorio de WordPress
cd /ruta/a/tu/sitio/wordpress

# Crear backup de la carpeta completa del plugin
tar -czf ~/backups/fairplay-lms-plugin-backup-$(date +%Y%m%d_%H%M%S).tar.gz \
  wp-content/plugins/fairplay-lms-masterstudy-extensions/

# Verificar que se creó
ls -lh ~/backups/fairplay-lms-plugin-backup-*.tar.gz
```

✅ **Checkpoint**: Backup de archivos creado

---

## 🗄️ PASO 4: Ejecutar Script de Migración SQL

### 4.1 Subir el Archivo SQL al Servidor

**Desde tu PC local** (abrir nueva terminal):

```bash
# Subir migracion_auditoria.sql al servidor
scp d:/Programas/gfp-elearning/migracion_auditoria.sql usuario@tu-servidor.com:~/backups/

# O si usas puerto específico:
scp -P 22 d:/Programas/gfp-elearning/migracion_auditoria.sql usuario@tu-servidor.com:~/backups/
```

### 4.2 Ejecutar Script SQL desde SSH

**Volver a la terminal SSH del servidor**:

```bash
# Opción 1: Ejecutar script completo directamente
mysql -u usuario_bd -p nombre_bd < ~/backups/migracion_auditoria.sql

# Te pedirá password:
# Enter password: [INGRESAR PASSWORD]

# Opción 2: Entrar a MySQL de forma interactiva
mysql -u usuario_bd -p
# Enter password: [INGRESAR PASSWORD]

# Una vez dentro de MySQL:
USE nombre_bd;
source ~/backups/migracion_auditoria.sql;
```

### 4.3 Verificar Cambios en la Base de Datos

```bash
# Verificar que las columnas se agregaron
mysql -u usuario_bd -p nombre_bd -e "DESCRIBE wp_fplms_audit_log;"

# Debe mostrar:
# +-------------+---------------------+------+-----+-------------------+
# | Field       | Type                | Null | Key | Default           |
# +-------------+---------------------+------+-----+-------------------+
# | id          | bigint(20) unsigned | NO   | PRI | NULL              |
# | timestamp   | datetime            | YES  | MUL | CURRENT_TIMESTAMP |
# | user_id     | bigint(20) unsigned | NO   | MUL | NULL              |
# | action      | varchar(255)        | NO   | MUL | NULL              |
# | entity_type | varchar(100)        | NO   | MUL | NULL              |
# | entity_id   | bigint(20)          | NO   |     | NULL              |
# | entity_title| varchar(255)        | YES  |     | NULL              |
# | old_value   | text                | YES  |     | NULL              |
# | new_value   | text                | YES  |     | NULL              |
# | ip_address  | varchar(45)         | YES  |     | NULL              |
# | user_agent  | text                | YES  |     | NULL              |
# | status      | varchar(20)         | YES  | MUL | completed         | ← NUEVA
# | meta_data   | text                | YES  |     | NULL              | ← NUEVA
# +-------------+---------------------+------+-----+-------------------+
```

```bash
# Verificar que el engine cambió a InnoDB
mysql -u usuario_bd -p nombre_bd -e "SHOW TABLE STATUS LIKE 'wp_fplms_audit_log';"

# En la columna "Engine" debe mostrar: InnoDB
```

✅ **Checkpoint**: Base de datos migrada correctamente

---

## 📤 PASO 5: Subir Archivos Modificados del Plugin

### 5.1 Preparar Archivos en tu PC

**En tu PC, comprime los archivos modificados**:

```powershell
# Desde PowerShell en Windows
cd d:\Programas\gfp-elearning\wordpress\wp-content\plugins\fairplay-lms-masterstudy-extensions

# Crear un ZIP con solo los archivos modificados
Compress-Archive -Path `
  includes/class-fplms-audit-logger.php, `
  includes/class-fplms-courses.php, `
  includes/class-fplms-users.php, `
  includes/class-fplms-plugin.php, `
  admin/class-fplms-audit-admin.php `
  -DestinationPath d:\Programas\gfp-elearning\archivos-modificados.zip -Force
```

### 5.2 Subir Archivos al Servidor

```bash
# Subir ZIP al servidor
scp d:/Programas/gfp-elearning/archivos-modificados.zip usuario@tu-servidor.com:~/backups/

# O si usas puerto específico:
scp -P 22 d:/Programas/gfp-elearning/archivos-modificados.zip usuario@tu-servidor.com:~/backups/
```

### 5.3 Descomprimir y Copiar Archivos

**Volver a la terminal SSH del servidor**:

```bash
# Ir al directorio temporal
cd ~/backups

# Descomprimir archivos
unzip archivos-modificados.zip -d archivos-modificados/

# Navegar al directorio del plugin
cd /ruta/a/tu/sitio/wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions

# Copiar archivos uno por uno (para mayor control)

# 1. class-fplms-audit-logger.php
cp ~/backups/archivos-modificados/includes/class-fplms-audit-logger.php includes/

# 2. class-fplms-courses.php
cp ~/backups/archivos-modificados/includes/class-fplms-courses.php includes/

# 3. class-fplms-users.php
cp ~/backups/archivos-modificados/includes/class-fplms-users.php includes/

# 4. class-fplms-plugin.php
cp ~/backups/archivos-modificados/includes/class-fplms-plugin.php includes/

# 5. class-fplms-audit-admin.php
cp ~/backups/archivos-modificados/admin/class-fplms-audit-admin.php admin/
```

### 5.4 Verificar Permisos de Archivos

```bash
# Asegurar permisos correctos
chmod 644 includes/class-fplms-audit-logger.php
chmod 644 includes/class-fplms-courses.php
chmod 644 includes/class-fplms-users.php
chmod 644 includes/class-fplms-plugin.php
chmod 644 admin/class-fplms-audit-admin.php

# Verificar propietario (ajustar según tu configuración)
# Usualmente es www-data o el usuario de Apache/Nginx
chown www-data:www-data includes/class-fplms-audit-logger.php
chown www-data:www-data includes/class-fplms-courses.php
chown www-data:www-data includes/class-fplms-users.php
chown www-data:www-data includes/class-fplms-plugin.php
chown www-data:www-data admin/class-fplms-audit-admin.php

# Si tu servidor usa otro usuario (por ejemplo: apache, nginx, tu-usuario)
# Reemplazar www-data por el usuario correcto
```

✅ **Checkpoint**: Archivos subidos con permisos correctos

---

## 🔄 PASO 6: Limpiar Cache y Reactivar Plugin

### 6.1 Limpiar Cache de PHP (si usas OPcache)

```bash
# Si tienes acceso a PHP-FPM
systemctl restart php7.4-fpm

# O si usas PHP8.0
systemctl restart php8.0-fpm

# Si usas Apache con mod_php
systemctl restart apache2

# Si usas Nginx
systemctl restart nginx
```

### 6.2 Limpiar Cache de WordPress

```bash
# Si usas WP-CLI (recomendado)
cd /ruta/a/tu/sitio/wordpress
wp cache flush

# Si no tienes WP-CLI, eliminar cache manualmente
rm -rf wp-content/cache/*
```

### 6.3 Reactivar Plugin desde CLI (Opcional)

```bash
# Desactivar plugin
wp plugin deactivate fairplay-lms-masterstudy-extensions

# Reactivar plugin
wp plugin activate fairplay-lms-masterstudy-extensions

# Verificar que está activo
wp plugin list | grep fairplay
```

**O desde el Admin de WordPress**:
1. Ir a: Plugins → Plugins Instalados
2. Desactivar "FairPlay LMS MasterStudy Extensions"
3. Reactivar el plugin

✅ **Checkpoint**: Cache limpiado, plugin reactivado

---

## 🧪 PASO 7: Testing Básico

### 7.1 Verificar que la Bitácora Carga

**Desde navegador**:
- Ir a: `https://tu-sitio.com/wp-admin/admin.php?page=fairplay-lms-audit`
- Verificar que la página carga sin errores
- Verificar que aparece columna "Acciones"

### 7.2 Test Rápido: Crear Curso

```bash
# Opción 1: Desde navegador
# - Ir a: Cursos → Añadir Nuevo
# - Título: "TEST - Implementación Auditoría"
# - Publicar

# Opción 2: Desde WP-CLI
cd /ruta/a/tu/sitio/wordpress
wp post create \
  --post_type=stm-courses \
  --post_title='TEST - Implementación Auditoría' \
  --post_status=publish \
  --post_content='Este es un curso de prueba para verificar auditoría'
```

### 7.3 Verificar Registro en Auditoría

```bash
# Verificar desde MySQL
mysql -u usuario_bd -p nombre_bd -e "
SELECT id, action, entity_type, entity_title, timestamp 
FROM wp_fplms_audit_log 
WHERE action = 'course_created' 
ORDER BY id DESC 
LIMIT 5;
"

# Debe mostrar:
# +----+----------------+-------------+--------------------------------+---------------------+
# | id | action         | entity_type | entity_title                   | timestamp           |
# +----+----------------+-------------+--------------------------------+---------------------+
# | XX | course_created | course      | TEST - Implementación Auditoría| 2026-02-16 10:45:23 |
# +----+----------------+-------------+--------------------------------+---------------------+
```

### 7.4 Test de Soft-Delete de Usuario

```bash
# Crear usuario de prueba desde WP-CLI
wp user create test_audit test_audit@ejemplo.com \
  --role=subscriber \
  --user_pass=TestPass123

# Anotar el ID del usuario creado (ejemplo: 123)

# "Eliminar" usuario (quedará inactivo)
wp user delete 123 --yes

# IMPORTANTE: Con la nueva implementación, el usuario NO se eliminará
# Se marcará como inactivo

# Verificar en la base de datos
mysql -u usuario_bd -p nombre_bd -e "
SELECT * FROM wp_users WHERE ID = 123;
"
# Debe devolver el usuario (NO fue eliminado)

# Verificar user_meta
mysql -u usuario_bd -p nombre_bd -e "
SELECT meta_key, meta_value 
FROM wp_usermeta 
WHERE user_id = 123 
  AND meta_key LIKE 'fplms_%';
"
# Debe mostrar:
# fplms_user_status = 'inactive'
# fplms_deactivated_date = [timestamp]
```

✅ **Checkpoint**: Testing básico completado

---

## ✅ PASO 8: Verificación Final

### 8.1 Checklist de Verificación

Ejecutar estos comandos para verificar todo:

```bash
# 1. Verificar estructura de tabla
mysql -u usuario_bd -p nombre_bd -e "DESCRIBE wp_fplms_audit_log;"

# 2. Verificar engine
mysql -u usuario_bd -p nombre_bd -e "SHOW TABLE STATUS LIKE 'wp_fplms_audit_log';"

# 3. Verificar índices
mysql -u usuario_bd -p nombre_bd -e "
SHOW INDEX FROM wp_fplms_audit_log;
"

# 4. Verificar últimos registros
mysql -u usuario_bd -p nombre_bd -e "
SELECT id, action, entity_type, entity_title, status, timestamp 
FROM wp_fplms_audit_log 
ORDER BY id DESC 
LIMIT 10;
"

# 5. Verificar que archivos existen
ls -lh wp-content/plugins/fairplay-lms-masterstudy-extensions/includes/class-fplms-audit-logger.php
ls -lh wp-content/plugins/fairplay-lms-masterstudy-extensions/includes/class-fplms-courses.php
ls -lh wp-content/plugins/fairplay-lms-masterstudy-extensions/includes/class-fplms-users.php
ls -lh wp-content/plugins/fairplay-lms-masterstudy-extensions/includes/class-fplms-plugin.php
ls -lh wp-content/plugins/fairplay-lms-masterstudy-extensions/admin/class-fplms-audit-admin.php

# 6. Verificar logs de errores de WordPress
tail -50 wp-content/debug.log | grep -i "fairplay\|fatal\|error"
```

### 8.2 Estado Final

✅ Base de datos migrada  
✅ Archivos actualizados  
✅ Plugin reactivado  
✅ Testing básico pasado  
✅ Sin errores en logs  

---

## 🆘 SOLUCIÓN DE PROBLEMAS

### Problema: "Access denied for user"

```bash
# Verificar credenciales
grep -E "DB_USER|DB_PASSWORD" wp-config.php

# Intentar conectarse manualmente
mysql -u usuario_bd -p
# Ingresar password cuando lo solicite
```

### Problema: "Table doesn't exist"

```bash
# Verificar que tabla existe
mysql -u usuario_bd -p nombre_bd -e "SHOW TABLES LIKE 'wp_fplms_audit_log';"

# Si no existe, crear tabla ejecutando:
mysql -u usuario_bd -p nombre_bd < ~/backups/migracion_auditoria.sql
```

### Problema: "Permission denied" al copiar archivos

```bash
# Cambiar a usuario con permisos
sudo su

# O agregar sudo a cada comando:
sudo cp ~/backups/archivos-modificados/includes/class-fplms-audit-logger.php includes/
```

### Problema: "Plugin Error" después de reactivar

```bash
# 1. Verificar sintaxis PHP
php -l includes/class-fplms-audit-logger.php
php -l includes/class-fplms-courses.php
php -l includes/class-fplms-users.php
php -l includes/class-fplms-plugin.php
php -l admin/class-fplms-audit-admin.php

# 2. Ver errores en log
tail -100 wp-content/debug.log

# 3. Si hay error fatal, restaurar backup
cd /ruta/a/tu/sitio/wordpress/wp-content/plugins
rm -rf fairplay-lms-masterstudy-extensions/
tar -xzf ~/backups/fairplay-lms-plugin-backup-*.tar.gz
```

### Problema: "Cannot write to database"

```bash
# Verificar permisos del usuario de MySQL
mysql -u root -p

SHOW GRANTS FOR 'usuario_bd'@'localhost';

# Debe tener permisos ALTER, CREATE, INSERT, UPDATE
```

---

## 🔄 ROLLBACK COMPLETO (Si algo sale muy mal)

### Restaurar Base de Datos

```bash
# 1. Conectar a MySQL
mysql -u usuario_bd -p

# 2. Dentro de MySQL
USE nombre_bd;

# 3. Eliminar tabla actual
DROP TABLE wp_fplms_audit_log;

# 4. Salir de MySQL
exit

# 5. Restaurar desde backup
mysql -u usuario_bd -p nombre_bd < ~/backups/backup_completo_20260216_*.sql

# Verificar
mysql -u usuario_bd -p nombre_bd -e "SELECT COUNT(*) FROM wp_fplms_audit_log;"
```

### Restaurar Archivos del Plugin

```bash
# Ir al directorio de plugins
cd /ruta/a/tu/sitio/wordpress/wp-content/plugins

# Eliminar carpeta actual
rm -rf fairplay-lms-masterstudy-extensions/

# Extraer backup
tar -xzf ~/backups/fairplay-lms-plugin-backup-*.tar.gz

# Verificar
ls -la fairplay-lms-masterstudy-extensions/
```

---

## 📊 RESUMEN DE COMANDOS RÁPIDOS

### Backup Completo en 1 Comando

```bash
# Crear todos los backups
mysqldump -u usuario_bd -p nombre_bd > ~/backups/backup_completo_$(date +%Y%m%d_%H%M%S).sql && \
mysqldump -u usuario_bd -p nombre_bd wp_fplms_audit_log > ~/backups/backup_audit_$(date +%Y%m%d_%H%M%S).sql && \
tar -czf ~/backups/fairplay-lms-plugin-$(date +%Y%m%d_%H%M%S).tar.gz \
  wp-content/plugins/fairplay-lms-masterstudy-extensions/ && \
echo "✅ Backups completados"
```

### Migración Completa en 1 Comando

```bash
# Ejecutar migración y verificar
mysql -u usuario_bd -p nombre_bd < ~/backups/migracion_auditoria.sql && \
mysql -u usuario_bd -p nombre_bd -e "DESCRIBE wp_fplms_audit_log;" && \
echo "✅ Migración completada"
```

---

## 📱 CONTACTO Y SOPORTE

Si encuentras problemas:

1. **Revisar logs**:
   ```bash
   tail -100 wp-content/debug.log
   tail -100 /var/log/apache2/error.log  # O nginx error.log
   ```

2. **Verificar estado de servicios**:
   ```bash
   systemctl status mysql
   systemctl status apache2  # O nginx
   systemctl status php7.4-fpm  # O php8.0-fpm
   ```

3. **Documentación completa**:
   - Ver: `SISTEMA_AUDITORIA_COMPLETO.md`
   - Ver: `CHECKLIST_IMPLEMENTACION.md`

---

**Última actualización**: 16 de Febrero de 2026  
**Versión**: 1.0  
**Estado**: ✅ Listo para Implementación

