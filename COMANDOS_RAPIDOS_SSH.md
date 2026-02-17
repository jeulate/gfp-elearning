# ⚡ Comandos Rápidos SSH - Implementación Auditoría

## 🔐 1. CONECTAR AL SERVIDOR

```bash
ssh usuario@tu-servidor.com
cd /var/www/html  # o /home/usuario/public_html
```

---

## 💾 2. BACKUP COMPLETO (EJECUTAR PRIMERO)

### Ver credenciales de BD
```bash
grep -E "DB_NAME|DB_USER|DB_PASSWORD|DB_HOST" wp-config.php
```

### Crear directorio backups
```bash
mkdir -p ~/backups
```

### Backup base de datos
```bash
# REEMPLAZAR: usuario_bd, nombre_bd con tus valores
mysqldump -u usuario_bd -p nombre_bd > ~/backups/backup_completo_$(date +%Y%m%d_%H%M%S).sql
```

### Backup tabla específica
```bash
mysqldump -u usuario_bd -p nombre_bd wp_fplms_audit_log > ~/backups/backup_audit_$(date +%Y%m%d_%H%M%S).sql
```

### Backup archivos plugin
```bash
tar -czf ~/backups/fairplay-lms-plugin-$(date +%Y%m%d_%H%M%S).tar.gz \
  wp-content/plugins/fairplay-lms-masterstudy-extensions/
```

### Verificar backups creados
```bash
ls -lh ~/backups/
```

---

## 📤 3. SUBIR ARCHIVOS DESDE TU PC

### Desde PowerShell en tu PC:

```powershell
# Subir script SQL
scp d:/Programas/gfp-elearning/migracion_auditoria.sql usuario@tu-servidor.com:~/backups/

# Comprimir archivos modificados primero
cd d:\Programas\gfp-elearning\wordpress\wp-content\plugins\fairplay-lms-masterstudy-extensions
Compress-Archive -Path includes/class-fplms-audit-logger.php,includes/class-fplms-courses.php,includes/class-fplms-users.php,includes/class-fplms-plugin.php,admin/class-fplms-audit-admin.php -DestinationPath d:\archivos-mod.zip -Force

# Subir archivos comprimidos
scp d:/archivos-mod.zip usuario@tu-servidor.com:~/backups/
```

---

## 🗄️ 4. EJECUTAR MIGRACIÓN SQL

### Ejecutar script
```bash
mysql -u usuario_bd -p nombre_bd < ~/backups/migracion_auditoria.sql
```

### Verificar columnas agregadas
```bash
mysql -u usuario_bd -p nombre_bd -e "DESCRIBE wp_fplms_audit_log;"
```

Buscar en la salida:
- `status` VARCHAR(20) ← debe aparecer
- `meta_data` TEXT ← debe aparecer

### Verificar engine InnoDB
```bash
mysql -u usuario_bd -p nombre_bd -e "SHOW TABLE STATUS LIKE 'wp_fplms_audit_log' \G" | grep Engine
```

Debe mostrar: `Engine: InnoDB`

---

## 📁 5. ACTUALIZAR ARCHIVOS DEL PLUGIN

### Descomprimir archivos
```bash
cd ~/backups
unzip archivos-mod.zip -d temp/
```

### Ir al directorio del plugin
```bash
cd /var/www/html/wp-content/plugins/fairplay-lms-masterstudy-extensions
# O tu ruta específica
```

### Copiar archivos
```bash
cp ~/backups/temp/includes/class-fplms-audit-logger.php includes/
cp ~/backups/temp/includes/class-fplms-courses.php includes/
cp ~/backups/temp/includes/class-fplms-users.php includes/
cp ~/backups/temp/includes/class-fplms-plugin.php includes/
cp ~/backups/temp/admin/class-fplms-audit-admin.php admin/
```

### Ajustar permisos
```bash
# Permisos de archivos
chmod 644 includes/class-fplms-audit-logger.php
chmod 644 includes/class-fplms-courses.php
chmod 644 includes/class-fplms-users.php
chmod 644 includes/class-fplms-plugin.php
chmod 644 admin/class-fplms-audit-admin.php

# Propietario (ajustar según tu servidor)
chown www-data:www-data includes/class-fplms-audit-logger.php
chown www-data:www-data includes/class-fplms-courses.php
chown www-data:www-data includes/class-fplms-users.php
chown www-data:www-data includes/class-fplms-plugin.php
chown www-data:www-data admin/class-fplms-audit-admin.php

# Si usas otro usuario (ejemplo: apache, nginx, tu_usuario)
# Reemplazar www-data por el usuario correcto
```

---

## 🔄 6. LIMPIAR CACHE Y REACTIVAR

### Reiniciar servicios
```bash
# Si usas PHP-FPM
sudo systemctl restart php7.4-fpm  # O php8.0-fpm

# Si usas Apache
sudo systemctl restart apache2

# Si usas Nginx
sudo systemctl restart nginx
```

### Limpiar cache WordPress (si tienes WP-CLI)
```bash
cd /var/www/html
wp cache flush
wp plugin deactivate fairplay-lms-masterstudy-extensions
wp plugin activate fairplay-lms-masterstudy-extensions
```

---

## ✅ 7. VERIFICACIÓN RÁPIDA

### Verificar últimos registros
```bash
mysql -u usuario_bd -p nombre_bd -e "
SELECT id, action, entity_type, entity_title, status, timestamp 
FROM wp_fplms_audit_log 
ORDER BY id DESC 
LIMIT 5;
"
```

### Verificar archivos actualizados
```bash
ls -lh includes/class-fplms-audit-logger.php
ls -lh includes/class-fplms-courses.php
ls -lh admin/class-fplms-audit-admin.php
```

### Ver errores (si hay)
```bash
tail -50 /var/www/html/wp-content/debug.log
```

---

## 🧪 8. TEST RÁPIDO

### Crear curso de prueba (WP-CLI)
```bash
wp post create --post_type=stm-courses --post_title='TEST Auditoría' --post_status=publish
```

### Verificar que se registró
```bash
mysql -u usuario_bd -p nombre_bd -e "
SELECT id, action, entity_title 
FROM wp_fplms_audit_log 
WHERE action = 'course_created' 
ORDER BY id DESC 
LIMIT 1;
"
```

Debe mostrar el curso TEST Auditoría

---

## 🆘 ROLLBACK RÁPIDO (SI ALGO SALE MAL)

### Restaurar base de datos
```bash
mysql -u usuario_bd -p nombre_bd < ~/backups/backup_completo_*.sql
```

### Restaurar archivos
```bash
cd /var/www/html/wp-content/plugins
rm -rf fairplay-lms-masterstudy-extensions/
tar -xzf ~/backups/fairplay-lms-plugin-*.tar.gz
```

---

## 📊 COMANDOS TODO-EN-UNO

### Backup completo en 1 línea
```bash
mysqldump -u usuario_bd -p nombre_bd > ~/backups/backup_$(date +%Y%m%d_%H%M%S).sql && tar -czf ~/backups/plugin_$(date +%Y%m%d_%H%M%S).tar.gz wp-content/plugins/fairplay-lms-masterstudy-extensions/ && echo "✅ Backups OK"
```

### Verificación completa en 1 línea
```bash
mysql -u usuario_bd -p nombre_bd -e "DESCRIBE wp_fplms_audit_log;" && ls -lh includes/class-fplms-audit-logger.php && echo "✅ Todo OK"
```

---

## 📝 NOTAS IMPORTANTES

1. **SIEMPRE hacer backup ANTES de cualquier cambio**
2. **Anotar credenciales de BD** (DB_NAME, DB_USER, DB_PASSWORD)
3. **Probar en ambiente de prueba primero** (si es posible)
4. **Verificar permisos de archivos** (644 para archivos, 755 para carpetas)
5. **Verificar propietario** (www-data, apache, nginx según tu servidor)

---

## 🎯 CHECKLIST RÁPIDO

- [ ] Backups creados (BD + archivos)
- [ ] Script SQL subido al servidor
- [ ] Script SQL ejecutado sin errores
- [ ] Columnas `status` y `meta_data` agregadas
- [ ] Engine cambiado a InnoDB
- [ ] Archivos del plugin actualizados
- [ ] Permisos correctos (644/755)
- [ ] Propietario correcto (www-data u otro)
- [ ] Cache limpiado
- [ ] Plugin reactivado
- [ ] Sin errores en debug.log
- [ ] Test de curso funciona
- [ ] Bitácora carga correctamente

---

**Tiempo total estimado**: 10-15 minutos  
**Última actualización**: 16 de Febrero de 2026
