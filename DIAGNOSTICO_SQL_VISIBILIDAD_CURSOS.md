# Diagnóstico SQL — Visibilidad de Cursos por Usuario

> Ejecutar en el servidor vía SSH usando `wp-cli` o directamente en MySQL.  
> Reemplazar `USER_ID` y `COURSE_IDS` con los valores reales.  
> Prefijo de tablas: `wp_` (ajustar si difiere).

---

## 0 · Preparación — Detectar tabla de matrículas MasterStudy

```sql
SHOW TABLES LIKE '%stm_lms%';
```

Esto lista todas las tablas de MasterStudy. Las relevantes son:
- `wp_stm_lms_user_courses` — tabla principal de matrículas (v4+)
- `wp_stm_lms_users` — tabla alternativa (versiones antiguas)

> **Usar en el resto de las consultas la que exista (normalmente `wp_stm_lms_user_courses`).**

---

## 1 · Resolver ID del usuario a diagnosticar

```sql
-- Por email
SELECT ID, user_login, user_email, display_name
FROM wp_users
WHERE user_email = 'correo@ejemplo.com';

-- Por login
SELECT ID, user_login, user_email, display_name
FROM wp_users
WHERE user_login = 'nombre_usuario';
```

---

## 2 · Ver todos los cursos donde está inscrito el usuario

```sql
-- Reemplazar 123 por el USER_ID real
SELECT
    uc.course_id,
    p.post_title        AS titulo,
    p.post_status       AS estado_publicacion,
    p.post_author       AS autor_id,
    uc.progress_percent AS progreso,
    uc.status           AS estado_matricula,
    uc.user_id
FROM wp_stm_lms_user_courses uc
LEFT JOIN wp_posts p ON p.ID = uc.course_id
WHERE uc.user_id = 123
ORDER BY uc.course_id;
```

**Qué buscar:**
- `estado_publicacion = 'publish'` → curso activo (debe ser visible).
- `estado_publicacion = 'draft' / 'private' / 'pending'` → inactivo (solo admin o autor deben verlo).
- `estado_publicacion = NULL` → el post fue eliminado (matricula huérfana).

---

## 3 · Verificar exactamente los 4 cursos esperados (53965, 53940, 53841, 53818)

```sql
-- Reemplazar 123 por el USER_ID real
SELECT
    p.ID                AS course_id,
    p.post_title        AS titulo,
    p.post_status       AS estado_publicacion,
    p.post_author       AS autor_id,
    uc.progress_percent AS progreso,
    uc.status           AS estado_matricula,
    CASE
        WHEN uc.course_id IS NULL         THEN '❌ NO INSCRITO'
        WHEN p.post_status = 'publish'    THEN '✅ ACTIVO Y VISIBLE'
        WHEN p.post_status = 'draft'      THEN '🔴 DRAFT — solo admin/autor'
        WHEN p.post_status = 'private'    THEN '🔴 PRIVATE — solo admin/autor'
        WHEN p.post_status = 'pending'    THEN '🔴 PENDING — solo admin/autor'
        ELSE CONCAT('⚠ ', p.post_status)
    END AS diagnostico
FROM wp_posts p
LEFT JOIN wp_stm_lms_user_courses uc
    ON uc.course_id = p.ID AND uc.user_id = 123
WHERE p.ID IN (53965, 53940, 53841, 53818)
ORDER BY p.ID;
```

---

## 4 · Ver estructuras asignadas al usuario

```sql
-- Reemplazar 123 por el USER_ID real
SELECT meta_key, meta_value
FROM wp_usermeta
WHERE user_id = 123
  AND meta_key IN (
      'fplms_city',
      'fplms_company',
      'fplms_channel',
      'fplms_branch',
      'fplms_role'
  )
ORDER BY meta_key;
```

**Si no devuelve filas:** el usuario no tiene estructuras asignadas → puede ver TODOS los cursos activos.

---

## 5 · Ver restricciones estructurales de los 4 cursos esperados

```sql
SELECT
    post_id             AS course_id,
    meta_key            AS restriccion,
    meta_value          AS valor_serializado
FROM wp_postmeta
WHERE post_id IN (53965, 53940, 53841, 53818)
  AND meta_key IN (
      'fplms_course_cities',
      'fplms_course_companies',
      'fplms_course_channels',
      'fplms_course_branches',
      'fplms_course_roles'
  )
ORDER BY post_id, meta_key;
```

**Interpretación:**
- Si `meta_value` es vacío, `a:0:{}` o `NULL` → el curso no tiene restricción de ese tipo → visible para todos los activos.
- Si contiene IDs → el usuario debe coincidir en ese nivel para verlo.

---

## 6 · Diagnóstico completo: usuarios, estructuras y visibilidad de un curso

```sql
-- Muestra todos los usuarios inscritos en un curso específico y si deberían verlo
-- Reemplazar 53965 por cualquier course_id a diagnosticar
SELECT
    u.ID                                        AS user_id,
    u.user_login,
    u.user_email,
    uc.progress_percent                         AS progreso,
    uc.status                                   AS estado_matricula,
    um_city.meta_value                          AS city_id,
    um_channel.meta_value                       AS channel_id,
    um_branch.meta_value                        AS branch_id,
    pm_channels.meta_value                      AS curso_channels,
    pm_branches.meta_value                      AS curso_branches,
    p.post_status                               AS estado_curso
FROM wp_stm_lms_user_courses uc
JOIN wp_users u ON u.ID = uc.user_id
JOIN wp_posts p ON p.ID = uc.course_id
LEFT JOIN wp_usermeta um_city    ON um_city.user_id    = u.ID AND um_city.meta_key    = 'fplms_city'
LEFT JOIN wp_usermeta um_channel ON um_channel.user_id = u.ID AND um_channel.meta_key = 'fplms_channel'
LEFT JOIN wp_usermeta um_branch  ON um_branch.user_id  = u.ID AND um_branch.meta_key  = 'fplms_branch'
LEFT JOIN wp_postmeta pm_channels ON pm_channels.post_id = uc.course_id AND pm_channels.meta_key = 'fplms_course_channels'
LEFT JOIN wp_postmeta pm_branches ON pm_branches.post_id = uc.course_id AND pm_branches.meta_key = 'fplms_course_branches'
WHERE uc.course_id = 53965
ORDER BY u.ID;
```

---

## 7 · Limpiar caché de transients del panel de estudiante

```sql
-- Limpia los transients del dashboard para que el próximo carga recalcule desde 0
DELETE FROM wp_options
WHERE option_name LIKE '_transient_fplms_sdash_%'
   OR option_name LIKE '_transient_timeout_fplms_sdash_%';

-- Si hay caché de OPcache/Redis, también borrar instructor dashboard
DELETE FROM wp_options
WHERE option_name LIKE '_transient_fplms_idash_%'
   OR option_name LIKE '_transient_timeout_fplms_idash_%';
```

---

## 8 · Ejecutar el diagnóstico PHP completo vía WP-CLI (en el servidor)

Conectarse por SSH y ejecutar desde el directorio raíz de WordPress:

```bash
# Paso A: Ver tabla de matrículas real
wp db query "SHOW TABLES LIKE '%stm_lms%';"

# Paso B: Resolver ID del usuario
wp user get correo@dominio.com --field=ID

# Paso C: Diagnóstico completo del usuario (reemplazar 123)
wp eval "
\$uid = 123; // <-- USER_ID aquí
global \$wpdb;

// Tabla de matrículas
\$table = null;
foreach (['wp_stm_lms_user_courses','wp_stm_lms_users'] as \$t) {
    if (\$wpdb->get_var(\$wpdb->prepare('SHOW TABLES LIKE %s',\$t)) === \$t) {
        \$table = \$t; break;
    }
}

// Matrículas con estado
\$rows = \$table
    ? \$wpdb->get_results(\$wpdb->prepare(\"SELECT course_id,progress_percent,status FROM \`{\$table}\` WHERE user_id=%d\",\$uid))
    : [];

echo '=== MATRÍCULAS (' . count(\$rows) . ') ===' . PHP_EOL;
foreach (\$rows as \$r) {
    \$status = get_post_status(\$r->course_id);
    echo sprintf('  Curso %d | post_status=%-10s | ms_status=%-12s | progreso=%s%%' . PHP_EOL,
        \$r->course_id, \$status ?: 'DELETED', \$r->status, \$r->progress_percent);
}

// Estructuras del usuario
\$keys = ['fplms_city','fplms_company','fplms_channel','fplms_branch','fplms_role'];
echo PHP_EOL . '=== ESTRUCTURAS USUARIO ===' . PHP_EOL;
foreach (\$keys as \$k) {
    \$v = get_user_meta(\$uid, \$k, true);
    echo '  ' . \$k . ' = ' . (empty(\$v) ? '(sin asignar)' : \$v) . PHP_EOL;
}

// Visibilidad con el servicio del plugin
if (class_exists('FairPlay_LMS_Course_Visibility_Service')) {
    \$svc = new FairPlay_LMS_Course_Visibility_Service();
    \$visible = [];
    \$blocked = [];
    foreach (\$rows as \$r) {
        \$cid = (int)\$r->course_id;
        if (\$svc->can_user_see_course(\$uid, \$cid)) {
            \$visible[] = \$cid;
        } else {
            \$blocked[] = \$cid;
        }
    }
    sort(\$visible);
    sort(\$blocked);
    echo PHP_EOL . '=== VISIBLE ('. count(\$visible) .') ===' . PHP_EOL;
    echo '  ' . implode(', ', \$visible) . PHP_EOL;
    echo PHP_EOL . '=== BLOQUEADO ('. count(\$blocked) .') ===' . PHP_EOL;
    foreach (\$blocked as \$cid) {
        echo sprintf('  %d | post_status=%s | title=%s' . PHP_EOL,
            \$cid, get_post_status(\$cid), get_the_title(\$cid));
    }
} else {
    echo 'AVISO: FairPlay_LMS_Course_Visibility_Service no encontrada.' . PHP_EOL;
}

// Limpiar caché
delete_transient('fplms_sdash_v9_' . \$uid);
echo PHP_EOL . '✓ Transient de caché eliminado.' . PHP_EOL;
"

# Paso D: Ver estadísticas recalculadas (sin caché)
wp eval "
\$uid = 123; // <-- USER_ID aquí
delete_transient('fplms_sdash_v9_' . \$uid);
\$p = new FairPlay_LMS_Progress_Service();
\$data = \$p->get_student_dashboard_stats(\$uid);
echo 'enrolled='.     \$data['enrolled']                  . PHP_EOL;
echo 'completed='.    \$data['completed']                 . PHP_EOL;
echo 'in_progress='.  \$data['in_progress_count']         . PHP_EOL;
echo 'avg_progress='. \$data['avg_progress'] . '%'        . PHP_EOL;
echo 'certificates='. \$data['certificates']              . PHP_EOL;
echo 'courses_list IDs: ' . implode(', ', array_column(\$data['courses_list'], 'id')) . PHP_EOL;
"
```

---

## 9 · Verificar que el perfil público /student-public-account/5/ también filtra

```bash
# Reemplazar 5 por el user_id del perfil público a revisar
wp eval "
\$uid = 5;
global \$wpdb;
\$table = null;
foreach (['wp_stm_lms_user_courses','wp_stm_lms_users'] as \$t) {
    if (\$wpdb->get_var(\$wpdb->prepare('SHOW TABLES LIKE %s',\$t)) === \$t) {
        \$table = \$t; break;
    }
}
\$rows = \$table
    ? \$wpdb->get_results(\$wpdb->prepare(\"SELECT course_id FROM \`{\$table}\` WHERE user_id=%d\",\$uid))
    : [];
\$all_ids = array_map(function(\$r){ return (int)\$r->course_id; }, \$rows);
\$active  = array_filter(\$all_ids, function(\$cid){ return 'publish' === get_post_status(\$cid); });
sort(\$active);

echo 'Total inscritos: '  . count(\$all_ids) . PHP_EOL;
echo 'Activos (publish): '. count(\$active)  . PHP_EOL;
echo 'IDs activos: '      . implode(', ', array_values(\$active)) . PHP_EOL;
"
```

---

## 10 · Reactivar un curso inactivo (si procede)

```sql
-- Reemplazar 53965 por el course_id a activar
UPDATE wp_posts
SET post_status = 'publish'
WHERE ID = 53965
  AND post_type = 'stm-courses';
```

> **Después:** limpiar caché con el query del paso 7.
