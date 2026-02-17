# ✅ Checklist de Implementación - Sistema de Auditoría Mejorado

## 📋 Antes de Empezar

- [ ] **Hacer backup completo** de:
  - [ ] Base de datos (exportar SQL completo)
  - [ ] Carpeta wp-content/plugins/fairplay-lms-masterstudy-extensions/
  - [ ] Tabla específica: `wp_fplms_audit_log`

- [ ] **Verificar requisitos**:
  - [ ] WordPress 5.8 o superior
  - [ ] PHP 7.4 o superior
  - [ ] MasterStudy LMS 3.x instalado y activo
  - [ ] Permisos de escritura en DB y archivos

- [ ] **Acceso necesario**:
  - [ ] Acceso FTP/SFTP al servidor
  - [ ] Acceso PHPMyAdmin o CLI de MySQL
  - [ ] Usuario WordPress con rol de Administrador

---

## 🗄️ Paso 1: Migración de Base de Datos

### 1.1 Backup de Seguridad

```sql
-- Ejecutar en PHPMyAdmin o CLI
CREATE TABLE wp_fplms_audit_log_backup_20250115 
AS SELECT * FROM wp_fplms_audit_log;

-- Verificar backup
SELECT COUNT(*) FROM wp_fplms_audit_log_backup_20250115;
```

- [ ] Backup de tabla creado ✓
- [ ] Total de registros verificado ✓

### 1.2 Ejecutar Script de Migración

- [ ] Abrir archivo `migracion_auditoria.sql`
- [ ] Copiar contenido completo
- [ ] Pegar en PHPMyAdmin → SQL
- [ ] Ejecutar script completo
- [ ] Verificar que no hay errores

### 1.3 Verificar Cambios

```sql
-- Verificar nuevas columnas
DESCRIBE wp_fplms_audit_log;

-- Debe mostrar:
-- - status VARCHAR(20) DEFAULT 'completed'
-- - meta_data TEXT NULL

-- Verificar engine
SHOW TABLE STATUS LIKE 'wp_fplms_audit_log';
-- Debe mostrar: Engine = InnoDB
```

- [ ] Columna `status` agregada ✓
- [ ] Columna `meta_data` agregada ✓
- [ ] Engine = InnoDB ✓
- [ ] Índice `idx_status` creado ✓

---

## 📂 Paso 2: Actualizar Archivos del Plugin

### 2.1 Subir Archivos Modificados

Archivos que necesitas copiar a producción:

```
fairplay-lms-masterstudy-extensions/
├── includes/
│   ├── class-fplms-audit-logger.php     ← REEMPLAZAR
│   ├── class-fplms-courses.php          ← REEMPLAZAR
│   ├── class-fplms-users.php            ← REEMPLAZAR
│   └── class-fplms-plugin.php           ← REEMPLAZAR
└── admin/
    └── class-fplms-audit-admin.php      ← REEMPLAZAR
```

**Pasos**:

- [ ] Conectar por FTP/SFTP a servidor
- [ ] Navegar a: `wp-content/plugins/fairplay-lms-masterstudy-extensions/`
- [ ] **BACKUP local** de los archivos originales antes de reemplazar
- [ ] Subir `class-fplms-audit-logger.php` a `/includes/`
- [ ] Subir `class-fplms-courses.php` a `/includes/`
- [ ] Subir `class-fplms-users.php` a `/includes/`
- [ ] Subir `class-fplms-plugin.php` a `/includes/`
- [ ] Subir `class-fplms-audit-admin.php` a `/admin/`
- [ ] Verificar permisos: 644 para archivos, 755 para carpetas

### 2.2 Verificar Archivos Subidos

- [ ] Todos los archivos tienen tamaño correcto (no 0 bytes)
- [ ] Archivos no tienen permisos incorrectos (evitar 777)
- [ ] No hay archivos `.bak` o duplicados accidentales

---

## 🔄 Paso 3: Reactivar Plugin

### 3.1 Desactivar y Reactivar

- [ ] Ir a: WordPress Admin → Plugins
- [ ] Buscar "FairPlay LMS MasterStudy Extensions"
- [ ] Clic en "Desactivar"
- [ ] Esperar confirmación
- [ ] Clic en "Activar"
- [ ] Verificar que NO hay errores en pantalla

### 3.2 Verificar Logs de Errores

```bash
# En servidor, revisar:
tail -f /path/to/wp-content/debug.log

# Buscar errores relacionados con:
# - Fatal error
# - Warning
# - FairPlay_LMS
```

- [ ] No hay fatal errors ✓
- [ ] No hay warnings críticos ✓

---

## 🧪 Paso 4: Testing Funcional

### Test 1: Verificar Interfaz de Auditoría

- [ ] Ir a: **FairPlay LMS → Bitácora de Auditoría**
- [ ] Verificar que la página carga sin errores
- [ ] Verificar que aparece columna "Acciones" (nueva)
- [ ] Verificar filtros actualizados con nuevas opciones:
  - [ ] Filtro "Acción" tiene grupos: Cursos, Lecciones, Quizzes, Usuarios
  - [ ] Filtro "Tipo de Entidad" incluye: Curso, Lección, Quiz, Usuario

### Test 2: Auditoría de Curso

**Crear Curso**:
- [ ] Ir a: Cursos → Añadir Nuevo
- [ ] Título: "TEST - Auditoría Sistema"
- [ ] Publicar
- [ ] Ir a Bitácora → Filtrar por "Curso Creado"
- [ ] **Debe aparecer**: "📘 Curso Creado" con el curso test

**Actualizar Curso**:
- [ ] Editar curso test
- [ ] Cambiar título a: "TEST - Auditoría Actualizado"
- [ ] Actualizar
- [ ] Ir a Bitácora → Filtrar por "Curso Actualizado"
- [ ] **Debe aparecer**: "✏️ Curso Actualizado" con valores antes/después

**Eliminar Curso**:
- [ ] Eliminar curso test
- [ ] Ir a Bitácora → Filtrar por "Curso Eliminado"
- [ ] **Debe aparecer**: "🗑️ Curso Eliminado"

### Test 3: Auditoría de Lección

- [ ] Crear curso "Curso para Lecciones TEST"
- [ ] Publicar
- [ ] Crear lección "Lección TEST 1"
- [ ] Asignar al curso
- [ ] Publicar
- [ ] Ir a Bitácora → Filtrar por "Lección Agregada"
- [ ] **Debe aparecer**: "📝 Lección Agregada"
- [ ] Actualizar lección (cambiar título)
- [ ] Verificar "Lección Actualizada" en bitácora
- [ ] Eliminar lección
- [ ] Verificar "Lección Eliminada" en bitácora

### Test 4: Auditoría de Quiz

- [ ] Crear quiz "Quiz TEST 1"
- [ ] Publicar
- [ ] Ir a Bitácora → Filtrar por "Quiz Agregado"
- [ ] **Debe aparecer**: "❓ Quiz Agregado"
- [ ] Actualizar quiz
- [ ] Verificar "Quiz Actualizado"
- [ ] Eliminar quiz
- [ ] Verificar "Quiz Eliminado"

### Test 5: Soft-Delete de Usuario

**Preparación**:
- [ ] Crear usuario de prueba:
  - Username: `test_auditoria_2025`
  - Email: `test_audit@ejemplo.com`
  - Rol: Suscriptor
- [ ] Guardar y anotar el ID del usuario

**Desactivación**:
- [ ] Ir a: Usuarios → Todos los Usuarios
- [ ] Buscar usuario de prueba
- [ ] Clic en "Eliminar"
- [ ] Confirmar eliminación

**Verificación**:
- [ ] Ir a: FairPlay LMS → Bitácora
- [ ] Filtrar por: Acción = "Usuario Desactivado"
- [ ] **Debe aparecer**: "❌ Usuario Desactivado" con el nombre del usuario
- [ ] Verificar que en columna "Acciones" aparecen 2 botones:
  - [ ] ✅ Reactivar (azul)
  - [ ] 🗑️ Eliminar Definitivo (rojo)

**Verificar en Base de Datos**:
```sql
SELECT * FROM wp_users WHERE user_login = 'test_auditoria_2025';
-- Debe devolver el usuario (NO fue eliminado)

SELECT meta_key, meta_value 
FROM wp_usermeta 
WHERE user_id = [ID del usuario]
  AND meta_key LIKE 'fplms_%';
-- Debe mostrar:
-- fplms_user_status = 'inactive'
-- fplms_deactivated_date = [timestamp]
-- fplms_deactivated_by = [ID del admin]
```

- [ ] Usuario existe en `wp_users` ✓
- [ ] User meta `fplms_user_status` = 'inactive' ✓
- [ ] Fecha y admin de desactivación registrados ✓

### Test 6: Reactivación de Usuario

**Ejecutar Reactivación**:
- [ ] En bitácora, buscar usuario desactivado
- [ ] Clic en botón **"✅ Reactivar"**
- [ ] Debe redirigir a bitácora con mensaje de éxito

**Verificación**:
- [ ] Filtrar por: Acción = "Usuario Reactivado"
- [ ] **Debe aparecer**: Nuevo registro "✅ Usuario Reactivado"
- [ ] En columna "Acciones" del registro anterior debe mostrar:
  - "✅ Ya reactivado" (sin botones)

**Verificar en Base de Datos**:
```sql
SELECT meta_key, meta_value 
FROM wp_usermeta 
WHERE user_id = [ID]
  AND meta_key LIKE 'fplms_%';
-- Debe mostrar:
-- fplms_user_status = 'active'
-- fplms_reactivated_date = [timestamp]
-- fplms_reactivated_by = [ID del admin]
```

- [ ] Usuario status = 'active' ✓
- [ ] Fecha y admin de reactivación registrados ✓

**Verificar Login**:
- [ ] Iniciar sesión con el usuario reactivado
- [ ] **Debe permitir** acceso normal

### Test 7: Eliminación Permanente

**⚠️ USAR USUARIO DE PRUEBA DIFERENTE**

**Preparación**:
- [ ] Crear nuevo usuario: `test_delete_definitivo`
- [ ] "Eliminar" usuario (quedará inactivo)
- [ ] Verificar que aparece en bitácora como desactivado

**Primera Confirmación**:
- [ ] En bitácora, clic en **"🗑️ Eliminar Definitivo"**
- [ ] **Debe mostrar**: Pantalla de confirmación con:
  - [ ] Título: "⚠️ Confirmar Eliminación Permanente"
  - [ ] Fondo amarillo con borde naranja
  - [ ] Lista de advertencias (bullet points)
  - [ ] Texto en negritas: "¿Estás COMPLETAMENTE SEGURO?"
  - [ ] 2 botones: "SÍ, ELIMINAR PERMANENTEMENTE" (rojo) y "NO, VOLVER" (azul)

**Cancelación**:
- [ ] Clic en **"NO, VOLVER A LA BITÁCORA"**
- [ ] Debe regresar a bitácora sin cambios
- [ ] Usuario debe seguir existiendo

**Confirmación Final**:
- [ ] Volver a hacer clic en "🗑️ Eliminar Definitivo"
- [ ] Leer advertencias completas
- [ ] Clic en **"SÍ, ELIMINAR PERMANENTEMENTE"**
- [ ] Debe redirigir con mensaje de confirmación

**Verificación**:
- [ ] En bitácora, filtrar por "Usuario Eliminado Permanentemente"
- [ ] **Debe aparecer**: "🔥 Usuario Eliminado Permanentemente"

**Verificar en Base de Datos**:
```sql
SELECT * FROM wp_users WHERE user_login = 'test_delete_definitivo';
-- Debe devolver 0 filas (usuario eliminado)
```

- [ ] Usuario NO existe en `wp_users` ✓
- [ ] Registro en auditoría sí existe ✓

### Test 8: Filtros y Exportación

**Filtros**:
- [ ] Filtrar por: Acción = "Curso Creado"
  - Solo deben aparecer cursos creados
- [ ] Filtrar por: Tipo = "Usuario"
  - Solo deben aparecer acciones de usuarios
- [ ] Filtrar por: Fecha desde hoy hasta hoy
  - Solo registros de hoy
- [ ] Clic en "Limpiar Filtros"
  - Todos los filtros se resetean

**Exportación**:
- [ ] Aplicar filtro: Acción = "Usuario Desactivado"
- [ ] Clic en **"📥 Exportar CSV"**
- [ ] Debe descargar archivo: `fplms-audit-log-[fecha]-[hora].csv`
- [ ] Abrir CSV con Excel
- [ ] Verificar que:
  - [ ] Tiene todas las columnas
  - [ ] Solo contiene usuarios desactivados
  - [ ] Acentos se ven correctamente (UTF-8)

### Test 9: Permisos de Seguridad

**Crear Usuario No-Admin**:
- [ ] Crear usuario con rol "Editor" o "Autor"
- [ ] Guardar

**Cerrar Sesión como Admin**:
- [ ] Cerrar sesión actual
- [ ] Iniciar sesión con usuario no-admin

**Intentar Acceso**:
- [ ] Ir a: `/wp-admin/admin.php?page=fairplay-lms-audit`
- [ ] **Debe mostrar**: "No tienes permisos" o redirigir

**Intentar Reactivación Directa**:
- [ ] Copiar URL de reactivación (del test anterior)
- [ ] Pegar en navegador (como usuario no-admin)
- [ ] **Debe mostrar**: "❌ No tienes permisos para realizar esta acción."

**Volver como Admin**:
- [ ] Cerrar sesión
- [ ] Iniciar como administrador
- [ ] Repetir acceso a bitácora
- [ ] **Debe funcionar** correctamente

- [ ] Solo admins ven la bitácora ✓
- [ ] Solo admins pueden reactivar/eliminar ✓

---

## 🎯 Paso 5: Verificaciones Post-Implementación

### 5.1 Salud del Sistema

- [ ] No hay errores en `wp-content/debug.log`
- [ ] No hay warnings en pantalla de WordPress
- [ ] Plugins activos funcionan normalmente
- [ ] Tema no tiene conflictos

### 5.2 Performance

```sql
-- Verificar tamaño de tabla
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS "Size (MB)"
FROM information_schema.TABLES
WHERE table_schema = DATABASE()
  AND table_name = 'wp_fplms_audit_log';
```

- [ ] Tamaño de tabla es razonable (<100 MB ideal)
- [ ] Consultas a bitácora cargan en <2 segundos
- [ ] No hay lag en el admin de WordPress

### 5.3 Funcionalidad de MasterStudy LMS

- [ ] Crear curso funciona normalmente
- [ ] Lecciones se agregan correctamente
- [ ] Quizzes funcionan
- [ ] Usuarios se registran/editan sin problemas
- [ ] Frontend del LMS carga correctamente

---

## 📊 Paso 6: Monitoreo Inicial (Primera Semana)

### Día 1
- [ ] Revisar debug.log cada 2 horas
- [ ] Verificar que se registran acciones nuevas
- [ ] Confirmar que no hay duplicados

### Día 2-3
- [ ] Revisar debug.log 1 vez al día
- [ ] Verificar tamaño de tabla de auditoría
- [ ] Confirmar que filtros funcionan con datos reales

### Día 4-7
- [ ] Monitoreo normal
- [ ] Verificar reportes de usuarios (si hay quejas)
- [ ] Confirmar que soft-delete funciona en producción real

---

## 📚 Paso 7: Documentación y Capacitación

### 7.1 Guardar Documentación

- [ ] Archivar estos documentos en lugar seguro:
  - [x] SISTEMA_AUDITORIA_COMPLETO.md
  - [x] RESUMEN_AUDITORIA_MEJORADA.md
  - [x] migracion_auditoria.sql
  - [x] CHECKLIST_IMPLEMENTACION.md (este archivo)

### 7.2 Capacitar Administradores

- [ ] Mostrar cómo acceder a bitácora
- [ ] Explicar cómo usar filtros
- [ ] Demostrar proceso de reactivación
- [ ] Advertir sobre eliminación permanente
- [ ] Compartir documentación

### 7.3 Comunicar a Usuarios

- [ ] Anunciar nueva funcionalidad de auditoría
- [ ] Explicar proceso de desactivación/reactivación
- [ ] Informar política de retención de datos

---

## 🆘 Paso 8: Plan de Rollback (Si algo sale mal)

### Si hay errores después de migración DB:

```sql
-- ROLLBACK DE BASE DE DATOS
DROP TABLE wp_fplms_audit_log;
RENAME TABLE wp_fplms_audit_log_backup_20250115 TO wp_fplms_audit_log;

-- Verificar
SELECT COUNT(*) FROM wp_fplms_audit_log;
```

### Si hay errores después de subir archivos:

1. **Restaurar archivos originales**:
   - Copiar backups locales de vuelta al servidor
   - Verificar permisos correctos

2. **Desactivar plugin temporalmente**:
   - Desactivar "FairPlay LMS MasterStudy Extensions"
   - Revisar error exacto en logs
   - Contactar soporte técnico si es necesario

3. **Rollback completo**:
   ```bash
   # En servidor:
   cd wp-content/plugins/
   rm -rf fairplay-lms-masterstudy-extensions/
   # Subir versión anterior completa
   ```

---

## ✅ Checklist Final de Implementación Exitosa

### Base de Datos
- [ ] ✅ Tabla migrada correctamente
- [ ] ✅ Columnas `status` y `meta_data` presentes
- [ ] ✅ Índices creados
- [ ] ✅ Engine = InnoDB

### Archivos
- [ ] ✅ Todos los archivos subidos
- [ ] ✅ Permisos correctos (644/755)
- [ ] ✅ No hay errores de sintaxis

### Funcionalidad
- [ ] ✅ Auditoría de cursos funciona
- [ ] ✅ Auditoría de lecciones funciona
- [ ] ✅ Auditoría de quizzes funciona
- [ ] ✅ Soft-delete de usuarios funciona
- [ ] ✅ Reactivación funciona
- [ ] ✅ Eliminación permanente funciona
- [ ] ✅ Filtros funcionan correctamente
- [ ] ✅ Exportación CSV funciona

### Seguridad
- [ ] ✅ Solo admins acceden a bitácora
- [ ] ✅ Nonces verificados
- [ ] ✅ Permisos validados
- [ ] ✅ Confirmación de dos pasos funciona

### Monitoreo
- [ ] ✅ No hay errores en debug.log
- [ ] ✅ Performance es aceptable
- [ ] ✅ No hay conflictos con otros plugins

### Documentación
- [ ] ✅ Documentación archivada
- [ ] ✅ Administradores capacitados
- [ ] ✅ Plan de rollback documentado

---

## 🎉 ¡Implementación Completa!

Si todos los checkboxes están marcados, la implementación del Sistema de Auditoría Mejorado está **COMPLETA Y FUNCIONANDO**.

### Próximos Pasos (Opcional)

1. **Optimización**:
   - Configurar cron job para archivar logs antiguos (>6 meses)
   - Implementar índices adicionales si hay tablas muy grandes

2. **Mejoras Futuras**:
   - Dashboard widget con últimas acciones
   - Notificaciones por email para acciones críticas
   - Gráficas de actividad

3. **Mantenimiento**:
   - Revisar tamaño de tabla mensualmente
   - Purgar registros >1 año si es necesario
   - Mantener documentación actualizada

---

**Fecha de Implementación**: _______________  
**Implementado por**: _______________  
**Versión**: 1.0  
**Estado**: [ ] En Progreso  |  [ ] Completado  |  [ ] Rollback Necesario

---

**Notas adicionales**:

_Espacio para anotar observaciones durante la implementación:_

```
[Agregar aquí cualquier problema encontrado, solución aplicada, o modificación necesaria]
```

---

**Firma del Responsable**:

___________________________  
Nombre y Fecha

