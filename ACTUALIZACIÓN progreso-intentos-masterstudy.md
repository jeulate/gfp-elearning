# Ajuste de progreso por intentos agotados en MasterStudy LMS Pro

## Objetivo

Este ajuste permite que un estudiante pueda continuar o completar el progreso del curso cuando agota todos sus intentos en una tarea o quiz, aunque el resultado final sea reprobado.

La regla aplicada es:

- No se modifica la nota obtenida.
- No se cambia un resultado reprobado a aprobado.
- Solo se registra el material como completado para efectos de progreso.
- El avance del curso se recalcula con base en los materiales completados.
- El curso solo se marca como `completed` cuando todos los materiales del currículo están registrados como completados.

## Caso probado

Curso de prueba:

```text
course_id = 54028
user_id   = 2
```

Estructura del curso:

```text
54029 -> Lección
54031 -> Tarea
54032 -> Lección
54033 -> Quiz
```

Total de materiales: `4`

## Tablas involucradas

```text
wp_stm_lms_user_courses
wp_stm_lms_user_lessons
wp_stm_lms_user_assignments
wp_stm_lms_user_quizzes
wp_stm_lms_curriculum_sections
wp_stm_lms_curriculum_materials
wp_postmeta
wp_posts
```

## Hooks incorporados

En `class-fplms-plugin.php`, dentro de `register_hooks()`, se agregaron hooks para detectar intentos agotados en quiz y tareas.

```php
add_action( 'masterstudy_lms_user_quiz_added', [ $this, 'fplms_complete_quiz_progress_when_attempts_exhausted' ], 20, 1 );

add_action( 'stm_lms_assignment_passed', [ $this, 'fplms_complete_assignment_progress_when_attempts_exhausted' ], 20, 3 );
add_action( 'stm_lms_assignment_failed', [ $this, 'fplms_complete_assignment_progress_when_attempts_exhausted' ], 20, 3 );
add_action( 'stm_lms_assignment_not_passed', [ $this, 'fplms_complete_assignment_progress_when_attempts_exhausted' ], 20, 3 );
add_action( 'stm_lms_assignment_graded', [ $this, 'fplms_complete_assignment_progress_when_attempts_exhausted' ], 20, 3 );
```

## Funciones incorporadas

Las funciones nuevas se insertaron después de:

```php
flush_pending_quiz_times()
```

Funciones principales:

```text
fplms_complete_assignment_progress_when_attempts_exhausted()
fplms_complete_quiz_progress_when_attempts_exhausted()
fplms_mark_material_completed()
fplms_recalculate_course_progress()
```

## Comportamiento esperado

### Tarea

Si la tarea tiene 2 intentos permitidos:

```text
Intento 1 reprobado -> no avanza el progreso.
Intento 2 reprobado -> se registra la tarea como completada para progreso.
```

La nota y estado real permanecen en:

```text
status = not_passed
```

y la nota real se conserva en:

```text
grade = nota obtenida
```

### Quiz

Si el quiz tiene 2 intentos permitidos:

```text
Intento 1 reprobado -> no avanza el progreso.
Intento 2 reprobado -> se registra el quiz como completado para progreso.
```

La nota y estado real permanecen en:

```text
status = failed
progress = nota obtenida
```

## Consultas SQL utilizadas para validar

### 1. Revisar estructura real de tablas

```sql
DESCRIBE wp_stm_lms_user_assignments;
DESCRIBE wp_stm_lms_user_quizzes;
DESCRIBE wp_stm_lms_curriculum_materials;
DESCRIBE wp_stm_lms_curriculum_sections;
```

### 2. Revisar progreso del usuario en el curso

```sql
SET @user_id = 2;
SET @course_id = 54028;

SELECT 
    uc.user_id,
    uc.course_id,
    c.post_title AS course_name,
    uc.progress_percent AS course_progress,
    uc.status AS course_status
FROM wp_stm_lms_user_courses uc
LEFT JOIN wp_posts c ON c.ID = uc.course_id
WHERE uc.user_id = @user_id
  AND uc.course_id = @course_id;
```

### 3. Revisar materiales registrados como completados/progresados

```sql
SET @user_id = 2;
SET @course_id = 54028;

SELECT 
    ul.*,
    p.post_title AS item_name,
    p.post_type
FROM wp_stm_lms_user_lessons ul
LEFT JOIN wp_posts p ON p.ID = ul.lesson_id
WHERE ul.user_id = @user_id
  AND ul.course_id = @course_id
ORDER BY ul.user_lesson_id;
```

### 4. Revisar intentos de tarea

```sql
SET @user_id = 2;
SET @course_id = 54028;

SELECT 
    ua.*,
    p.post_title AS assignment_name
FROM wp_stm_lms_user_assignments ua
LEFT JOIN wp_posts p ON p.ID = ua.assignment_id
WHERE ua.user_id = @user_id
  AND ua.course_id = @course_id
ORDER BY ua.id DESC;
```

### 5. Revisar intentos de quiz

```sql
SET @user_id = 2;
SET @course_id = 54028;

SELECT 
    uq.*,
    p.post_title AS quiz_name
FROM wp_stm_lms_user_quizzes uq
LEFT JOIN wp_posts p ON p.ID = uq.quiz_id
WHERE uq.user_id = @user_id
  AND uq.course_id = @course_id
ORDER BY uq.user_quiz_id DESC;
```

### 6. Revisar estructura del curso desde tablas curriculares

```sql
SET @course_id = 54028;

SELECT *
FROM wp_stm_lms_curriculum_sections
WHERE course_id = @course_id;

SELECT *
FROM wp_stm_lms_curriculum_materials
WHERE section_id IN (
    SELECT id
    FROM wp_stm_lms_curriculum_sections
    WHERE course_id = @course_id
)
ORDER BY section_id, `order`;
```

### 7. Calcular progreso real según materiales completados

```sql
SET @user_id = 2;
SET @course_id = 54028;

SELECT 
    completed.completed_items,
    total.total_items,
    ROUND((completed.completed_items / total.total_items) * 100) AS calculated_progress
FROM (
    SELECT COUNT(*) AS completed_items
    FROM wp_stm_lms_user_lessons
    WHERE user_id = @user_id
      AND course_id = @course_id
) completed
CROSS JOIN (
    SELECT COUNT(*) AS total_items
    FROM wp_stm_lms_curriculum_materials cm
    INNER JOIN wp_stm_lms_curriculum_sections cs
        ON cs.id = cm.section_id
    WHERE cs.course_id = @course_id
) total;
```

Resultado esperado para 3 de 4 materiales:

```text
completed_items: 3
total_items: 4
calculated_progress: 75
```

Resultado esperado para 4 de 4 materiales:

```text
completed_items: 4
total_items: 4
calculated_progress: 100
```

### 8. Revisar configuración de intentos del quiz

```sql
SELECT meta_key, meta_value
FROM wp_postmeta
WHERE post_id = 54033;
```

Resultado relevante encontrado:

```text
attempts = 2
quiz_attempts = limited
passing_grade = 68
```

## Prueba exitosa realizada

Después de 2 intentos fallidos del quiz `54033`, se obtuvo:

```sql
SELECT *
FROM wp_stm_lms_user_quizzes
WHERE user_id = 2
AND course_id = 54028
ORDER BY user_quiz_id;
```

Resultado:

```text
user_quiz_id | user_id | course_id | quiz_id | progress | status
63           | 2       | 54028     | 54033   | 0        | failed
64           | 2       | 54028     | 54033   | 0        | failed
```

Luego se validó que el quiz fue registrado como material completado para progreso:

```sql
SELECT *
FROM wp_stm_lms_user_lessons
WHERE user_id = 2
AND course_id = 54028
ORDER BY user_lesson_id;
```

Resultado:

```text
lesson_id = 54029
lesson_id = 54032
lesson_id = 54031
lesson_id = 54033
```

Finalmente, el curso quedó en 100%:

```sql
SELECT progress_percent, status
FROM wp_stm_lms_user_courses
WHERE user_id = 2
AND course_id = 54028;
```

Resultado:

```text
progress_percent = 100
status = completed
```

## Importante

No se debe actualizar `wp_stm_lms_user_quizzes.progress` a `100`, porque eso alteraría la nota real del estudiante.

No se debe cambiar `wp_stm_lms_user_quizzes.status` de `failed` a `passed` o `completed`, porque eso alteraría el resultado académico real.

El ajuste solo actúa sobre:

```text
wp_stm_lms_user_lessons
wp_stm_lms_user_courses.progress_percent
wp_stm_lms_user_courses.status, únicamente cuando el progreso llega a 100%
```

## Logs temporales

Durante la prueba se agregó un log temporal:

```php
error_log('[FPLMS] Entró a fplms_complete_quiz_progress_when_attempts_exhausted');
```

Una vez validado el flujo, se recomienda eliminarlo para evitar ruido en `debug.log`.
