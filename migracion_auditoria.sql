-- ========================================================
-- Script de Migración de Base de Datos
-- Sistema de Auditoría Mejorado - FairPlay LMS
-- ========================================================
--
-- Propósito: Agregar columnas 'status' y 'meta_data' a la tabla
--            de auditoría y cambiar engine a InnoDB
--
-- Versión: 1.0
-- Fecha: 15 de Enero de 2025
--
-- IMPORTANTE: 
-- 1. Hacer BACKUP completo de la base de datos antes de ejecutar
-- 2. Este script es idempotente (se puede ejecutar múltiples veces)
-- 3. Verificar que la tabla wp_fplms_audit_log exista antes de ejecutar
--
-- ========================================================

-- ---------------------------
-- PASO 1: Backup de seguridad
-- ---------------------------
-- Ejecutar ANTES de aplicar cambios:
-- 
-- CREATE TABLE wp_fplms_audit_log_backup_20250115 
-- AS SELECT * FROM wp_fplms_audit_log;
--
-- Para restaurar en caso de error:
-- DROP TABLE wp_fplms_audit_log;
-- RENAME TABLE wp_fplms_audit_log_backup_20250115 TO wp_fplms_audit_log;

-- ---------------------------
-- PASO 2: Verificar tabla actual
-- ---------------------------

SELECT 
    'Tabla actual antes de migración:' AS info,
    COUNT(*) AS total_registros,
    MAX(id) AS max_id
FROM wp_fplms_audit_log;

-- Mostrar estructura actual
DESCRIBE wp_fplms_audit_log;

-- ---------------------------
-- PASO 3: Agregar columna 'status'
-- ---------------------------

-- Verificar si columna ya existe
SELECT 
    COLUMN_NAME, 
    COLUMN_TYPE, 
    IS_NULLABLE, 
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'wp_fplms_audit_log'
  AND COLUMN_NAME = 'status';

-- Si no existe, agregar columna 'status'
ALTER TABLE wp_fplms_audit_log
ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'completed' AFTER user_agent;

-- Llenar valores existentes con 'completed'
UPDATE wp_fplms_audit_log
SET status = 'completed'
WHERE status IS NULL OR status = '';

-- ---------------------------
-- PASO 4: Agregar columna 'meta_data'
-- ---------------------------

-- Verificar si columna ya existe
SELECT 
    COLUMN_NAME, 
    COLUMN_TYPE, 
    IS_NULLABLE, 
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'wp_fplms_audit_log'
  AND COLUMN_NAME = 'meta_data';

-- Si no existe, agregar columna 'meta_data'
ALTER TABLE wp_fplms_audit_log
ADD COLUMN IF NOT EXISTS meta_data TEXT NULL AFTER status;

-- ---------------------------
-- PASO 5: Agregar índice para 'status'
-- ---------------------------

-- Verificar si índice ya existe
SELECT 
    INDEX_NAME, 
    COLUMN_NAME, 
    SEQ_IN_INDEX
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'wp_fplms_audit_log'
  AND INDEX_NAME = 'idx_status';

-- Crear índice si no existe
-- Nota: En MySQL 5.7 usar DROP INDEX IF EXISTS no está disponible
-- Por eso usamos procedimiento condicional

DELIMITER $$

CREATE PROCEDURE add_status_index_if_not_exists()
BEGIN
    DECLARE index_count INT;
    
    SELECT COUNT(*) INTO index_count
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'wp_fplms_audit_log'
      AND INDEX_NAME = 'idx_status';
    
    IF index_count = 0 THEN
        ALTER TABLE wp_fplms_audit_log
        ADD INDEX idx_status (status);
    END IF;
END$$

DELIMITER ;

-- Ejecutar procedimiento
CALL add_status_index_if_not_exists();

-- Eliminar procedimiento temporal
DROP PROCEDURE IF EXISTS add_status_index_if_not_exists;

-- ---------------------------
-- PASO 6: Cambiar engine a InnoDB
-- ---------------------------

-- Verificar engine actual
SELECT 
    TABLE_NAME, 
    ENGINE, 
    ROW_FORMAT, 
    TABLE_ROWS,
    DATA_LENGTH,
    INDEX_LENGTH
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'wp_fplms_audit_log';

-- Cambiar a InnoDB (si no lo es ya)
ALTER TABLE wp_fplms_audit_log ENGINE=InnoDB;

-- ---------------------------
-- PASO 7: Optimizar tabla
-- ---------------------------

-- Optimizar después de cambios
OPTIMIZE TABLE wp_fplms_audit_log;

-- Analizar tabla para actualizar estadísticas
ANALYZE TABLE wp_fplms_audit_log;

-- ---------------------------
-- PASO 8: Verificar migración completa
-- ---------------------------

-- Verificar estructura final
SELECT 
    'Estructura final de la tabla:' AS info;
    
DESCRIBE wp_fplms_audit_log;

-- Verificar índices
SELECT 
    INDEX_NAME, 
    COLUMN_NAME, 
    NON_UNIQUE, 
    SEQ_IN_INDEX
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'wp_fplms_audit_log'
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

-- Verificar engine
SELECT 
    TABLE_NAME, 
    ENGINE,
    TABLE_COLLATION,
    CREATE_TIME,
    UPDATE_TIME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'wp_fplms_audit_log';

-- Verificar datos preservados
SELECT 
    COUNT(*) AS total_registros,
    MIN(timestamp) AS registro_mas_antiguo,
    MAX(timestamp) AS registro_mas_reciente,
    COUNT(DISTINCT user_id) AS usuarios_unicos,
    COUNT(DISTINCT action) AS acciones_unicas
FROM wp_fplms_audit_log;

-- Mostrar distribución de status (debe ser todo 'completed' si son registros antiguos)
SELECT 
    status,
    COUNT(*) AS cantidad,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM wp_fplms_audit_log), 2) AS porcentaje
FROM wp_fplms_audit_log
GROUP BY status;

-- ---------------------------
-- PASO 9: Migración de user_meta (opcional)
-- ---------------------------

-- Este paso solo es necesario si hay usuarios que fueron "eliminados"
-- antes de implementar el soft-delete y quieres marcarlos como inactivos

-- Consultar usuarios que fueron eliminados según la auditoría pero que 
-- todavía existen en wp_users (esto no debería pasar, pero por si acaso)

SELECT 
    a.entity_id AS user_id,
    a.entity_title AS user_name,
    a.timestamp AS deleted_at,
    u.user_login,
    u.user_email
FROM wp_fplms_audit_log a
LEFT JOIN wp_users u ON a.entity_id = u.ID
WHERE a.entity_type = 'user'
  AND a.action LIKE '%delete%'
  AND u.ID IS NOT NULL;  -- Usuario aún existe

-- Si quieres marcar esos usuarios como inactivos:
-- (DESCOMENTAR SOLO SI ES NECESARIO)

/*
INSERT INTO wp_usermeta (user_id, meta_key, meta_value)
SELECT DISTINCT
    a.entity_id,
    'fplms_user_status',
    'inactive'
FROM wp_fplms_audit_log a
INNER JOIN wp_users u ON a.entity_id = u.ID
WHERE a.entity_type = 'user'
  AND a.action LIKE '%delete%'
  AND NOT EXISTS (
      SELECT 1 FROM wp_usermeta um
      WHERE um.user_id = a.entity_id
        AND um.meta_key = 'fplms_user_status'
  );
*/

-- ---------------------------
-- PASO 10: Verificación final de integridad
-- ---------------------------

-- Verificar que no hay valores NULL en columnas críticas
SELECT 
    'Registros con valores NULL en columnas críticas:' AS info;

SELECT 
    COUNT(*) AS registros_con_nulls,
    SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) AS user_id_null,
    SUM(CASE WHEN action IS NULL THEN 1 ELSE 0 END) AS action_null,
    SUM(CASE WHEN entity_type IS NULL THEN 1 ELSE 0 END) AS entity_type_null,
    SUM(CASE WHEN status IS NULL THEN 1 ELSE 0 END) AS status_null
FROM wp_fplms_audit_log;

-- Si todo sale bien, debe mostrar 0 en todas las columnas

-- ---------------------------
-- PASO 11: Cleanup (opcional)
-- ---------------------------

-- Si la migración fue exitosa y has verificado todo,
-- puedes eliminar la tabla de backup después de unos días:

-- DROP TABLE IF EXISTS wp_fplms_audit_log_backup_20250115;

-- ---------------------------
-- RESUMEN DE CAMBIOS APLICADOS
-- ---------------------------

-- ✅ Columna 'status' agregada (VARCHAR(20), DEFAULT 'completed')
-- ✅ Columna 'meta_data' agregada (TEXT, NULL)
-- ✅ Índice 'idx_status' agregado
-- ✅ Engine cambiado a InnoDB
-- ✅ Tabla optimizada y analizada
-- ✅ Verificaciones de integridad ejecutadas

-- ---------------------------
-- NOTAS FINALES
-- ---------------------------

-- 1. Tiempo estimado de ejecución:
--    - Tablas pequeñas (<10k registros): < 1 segundo
--    - Tablas medianas (10k-100k registros): 1-5 segundos
--    - Tablas grandes (>100k registros): 5-30 segundos
--
-- 2. El cambio de engine puede tardar más en tablas grandes
--    pero es seguro (hace copia interna)
--
-- 3. Si hay errores de sintaxis con 'IF NOT EXISTS',
--    ejecutar manualmente las verificaciones con INFORMATION_SCHEMA
--
-- 4. Para verificar que todo funciona:
--    - Crear un curso nuevo
--    - Ir a bitácora → debe aparecer "Curso Creado"
--    - Intentar eliminar un usuario de prueba
--    - Verificar que aparece como "Usuario Desactivado"
--
-- 5. Rollback: Si algo sale mal, restaurar desde backup:
--    DROP TABLE wp_fplms_audit_log;
--    RENAME TABLE wp_fplms_audit_log_backup_20250115 TO wp_fplms_audit_log;

-- ========================================================
-- FIN DEL SCRIPT DE MIGRACIÓN
-- ========================================================
