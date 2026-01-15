# 🔧 Instrucciones de Instalación - Mejoras Interfaz Usuarios

**Fecha:** 15 de Enero de 2026  
**Versión:** 1.0  
**Plugin:** FairPlay LMS – MasterStudy Extensions

---

## 📋 Requisitos Previos

### Sistema
- **WordPress:** 5.0 o superior (recomendado 6.0+)
- **PHP:** 7.4 o superior (recomendado 8.0+)
- **MySQL:** 5.6 o superior

### Plugins
- **MasterStudy LMS** (versión compatible)
- **FairPlay LMS – MasterStudy Extensions** (versión 0.7.0 o superior)

### Navegador Administrador
- Chrome, Firefox, Safari o Edge (actualizado)
- JavaScript habilitado
- Cookies habilitadas

---

## 📁 Archivos Modificados

El cambio modifica **UN SOLO ARCHIVO**:

```
/wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/
  └─ includes/
      └─ class-fplms-users.php
```

---

## ✅ Instalación Paso a Paso

### Paso 1: Hacer Backup

**Importante:** Antes de cualquier cambio, realizar backup completo.

```bash
# Opción 1: Backup del archivo específico
cp class-fplms-users.php class-fplms-users.php.backup

# Opción 2: Backup de todo el plugin
cp -r fairplay-lms-masterstudy-extensions/ fairplay-lms-masterstudy-extensions.backup/

# Opción 3: Backup de la base de datos (desde hosting/cPanel)
# Usar herramienta de backup del hosting
```

### Paso 2: Reemplazar el Archivo

**Opción A: FTP/SFTP**

1. Conectar al servidor FTP/SFTP
2. Navegar a: `/wp-content/plugins/fairplay-lms-masterstudy-extensions/includes/`
3. Eliminar el archivo `class-fplms-users.php` (opcional)
4. Subir el nuevo archivo `class-fplms-users.php`
5. Verificar permisos: 644 (rw-r--r--)

**Opción B: Panel de Control (cPanel, Plesk, etc.)**

1. Acceder al File Manager
2. Navegar a: `/public_html/wp-content/plugins/fairplay-lms-masterstudy-extensions/includes/`
3. Seleccionar y eliminar `class-fplms-users.php`
4. Subir el nuevo archivo
5. Establecer permisos a 644

**Opción C: SSH/Terminal**

```bash
# Navegar al directorio
cd /ruta/al/wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/includes/

# Reemplazar archivo
cp /ruta/local/class-fplms-users.php .

# Establecer permisos
chmod 644 class-fplms-users.php

# Verificar cambios
ls -la class-fplms-users.php
```

### Paso 3: Verificar Instalación

#### Método 1: Panel WordPress

1. Acceder a **Panel Admin** → **Complementos**
2. Buscar **FairPlay LMS – MasterStudy Extensions**
3. Verificar que está **Activado**
4. Si hay error, verá aviso en la parte superior

#### Método 2: Revisión de Errores

1. Ir a **Panel Admin** → **FairPlay LMS** → **Usuarios**
2. Si carga correctamente → ✅ Instalación exitosa
3. Si hay error PHP → Revisar permisos o sintaxis

#### Método 3: Activar Debug

En `wp-config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Revisar: `/wp-content/debug.log`

---

## 🧪 Pruebas Post-Instalación

### Test 1: Acceso a la Página

```
✓ Panel Admin → FairPlay LMS → Usuarios
✓ Debe mostrar: Matriz de privilegios + Crear nuevo usuario + Listado usuarios
✓ Sin errores PHP
```

### Test 2: Formulario de Creación

```
✓ Ver área de fotografía (izquierda)
✓ Ver formulario (derecha)
✓ Secciones bien estructuradas
✓ Botones visibles (Guardar, Cancelar)
```

### Test 3: Carga de Imagen

```
✓ Hacer clic en área naranja
✓ Seleccionar imagen
✓ Ver preview
```

### Test 4: Drag & Drop

```
✓ Arrastrar imagen a área
✓ Soltar sobre el área
✓ Ver preview automáti
camente
```

### Test 5: Crear Usuario Completo

```
✓ Llenar todos los campos
✓ Subir fotografía
✓ Hacer clic "Guardar"
✓ Ver mensaje "Usuario creado correctamente"
```

### Test 6: Responsive

```
✓ Probar en desktop (1920x1080)
✓ Probar en tablet (768x1024)
✓ Probar en móvil (375x667)
✓ Verificar que se adapte correctamente
```

---

## 🛠️ Troubleshooting

### Problema: "Parse error" después de actualizar

**Causa:** Error de sintaxis en PHP

**Solución:**
```bash
# Verificar sintaxis
php -l class-fplms-users.php

# Si hay error, restaurar backup
cp class-fplms-users.php.backup class-fplms-users.php

# Contactar con soporte técnico
```

### Problema: "Fatal error: Class not found"

**Causa:** Archivo no cargado correctamente

**Solución:**
```bash
# Verificar que el archivo existe
ls -la /ruta/correcta/class-fplms-users.php

# Verificar permisos
chmod 644 class-fplms-users.php

# Desactivar y reactivar plugin
```

### Problema: Formulario se ve roto

**Causa:** CSS no se cargó correctamente

**Solución:**
1. Limpiar caché del navegador (Ctrl+Shift+Del)
2. Desactivar plugins de caché (si existen)
3. Limpiar caché de WordPress (si usa):
   - WP Super Cache
   - W3 Total Cache
   - WP Rocket

### Problema: Las imágenes no se guardan

**Causa:** Permisos de directorio insuficientes

**Solución:**
```bash
# Verificar permisos de wp-content
chmod 755 /ruta/wp-content/
chmod 755 /ruta/wp-content/uploads/

# Crear directorio si no existe
mkdir -p /ruta/wp-content/uploads/
```

### Problema: "fplms_user_photo input not found"

**Causa:** HTML no se renderiza correctamente

**Solución:**
1. Verificar sintaxis PHP en class-fplms-users.php
2. Revisar logs de error en wp-content/debug.log
3. Desactivar otros plugins temporalmente

---

## 📊 Verificación de Cambios

### Antes de la Instalación

```
ls -la class-fplms-users.php
```

Debería mostrar tamaño antiguo (aproximadamente ~688 líneas)

### Después de la Instalación

```
ls -la class-fplms-users.php
```

Debería mostrar tamaño nuevo (aproximadamente ~1010 líneas)

---

## 🔄 Rollback (Si es Necesario)

Si necesitas volver a la versión anterior:

```bash
# Restaurar desde backup
cp class-fplms-users.php.backup class-fplms-users.php

# O desde Git (si usas control de versiones)
git checkout class-fplms-users.php
```

---

## 📈 Verificar Éxito

### Indicadores de Éxito

✅ No hay errores PHP en los logs  
✅ La página de usuarios carga correctamente  
✅ El formulario se ve moderno y bien estructurado  
✅ El área de fotografía es visible y funcional  
✅ Se pueden crear usuarios sin errores  
✅ Las imágenes se guardan correctamente  
✅ Los datos se almacenan en la BD  

### Datos de Registro

```sql
-- Verificar usuario creado
SELECT * FROM wp_users WHERE user_login = 'nuevo_usuario';

-- Verificar metadata
SELECT * FROM wp_usermeta WHERE user_id = 123 AND meta_key LIKE 'fplms%';

-- Verificar attachment
SELECT * FROM wp_posts WHERE post_type = 'attachment' AND post_parent = 123;
```

---

## 📞 Soporte Técnico

Si encuentras problemas:

### 1. Revisar Documentación

- [x] MEJORAS_INTERFAZ_CREACION_USUARIOS.md
- [x] DOCUMENTACION_TECNICA_CREACION_USUARIOS.md
- [x] COMPARATIVA_ANTES_DESPUES_USUARIOS.md

### 2. Verificar Logs

```bash
# WordPress debug log
tail -f /ruta/wp-content/debug.log

# Apache/Nginx error log
tail -f /var/log/apache2/error.log
tail -f /var/log/nginx/error.log
```

### 3. Contactar a Administrador

Proporcionar:
- [ ] Versión de WordPress
- [ ] Versión de PHP
- [ ] Mensaje de error exacto
- [ ] Pasos para reproducir
- [ ] Navegador utilizado

---

## 🔒 Consideraciones de Seguridad

### Permisos de Archivo

```bash
# Correctos
chmod 644 class-fplms-users.php
chmod 755 includes/

# No hacer
chmod 777 class-fplms-users.php  # Demasiado abierto
chmod 600 class-fplms-users.php  # Demasiado restrictivo
```

### Permisos de Directorio

```bash
# Correctos
chmod 755 /wp-content/
chmod 755 /wp-content/uploads/

# No hacer
chmod 777 /wp-content/  # Demasiado abierto
chmod 600 /wp-content/  # Demasiado restrictivo
```

### Backup Regular

```bash
# Backup diario
0 2 * * * /backup.sh  # Cron job a las 2:00 AM

# Verificar integridad
md5sum class-fplms-users.php > checksum.txt
```

---

## 📅 Checklist de Instalación

- [ ] Hacer backup del sistema
- [ ] Descargar archivo actualizado
- [ ] Reemplazar archivo en servidor
- [ ] Establecer permisos (644)
- [ ] Acceder a panel admin
- [ ] Ir a FairPlay LMS → Usuarios
- [ ] Verificar que carga sin errores
- [ ] Probar crear usuario de prueba
- [ ] Probar subir imagen
- [ ] Probar drag & drop
- [ ] Verificar datos en BD
- [ ] Prueba en móvil
- [ ] Prueba en tablet
- [ ] Documentar cambios
- [ ] Notificar a equipo

---

## 🎉 Instalación Completada

Una vez completados todos los pasos:

✅ La nueva interfaz estará **activa**  
✅ Todos los usuarios podrán **ver el cambio**  
✅ Las **fotos se guardarán automáticamente**  
✅ Todo funciona en **mobile, tablet y desktop**  

---

**Instalación completada exitosamente** ✅

Para preguntas adicionales, revisar la documentación generada o contactar con soporte técnico.
