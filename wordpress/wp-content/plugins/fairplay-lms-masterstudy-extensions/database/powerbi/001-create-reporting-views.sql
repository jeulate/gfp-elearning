-- FairPlay LMS - Capa de lectura para Power BI
-- Compatible con las instalaciones BoostAcademy y MatchUp que usan prefijo wp_.
-- Ejecutar con una cuenta que tenga CREATE VIEW sobre la base de WordPress.

CREATE OR REPLACE VIEW wp_fplms_bi_structures AS
SELECT
    tt.term_taxonomy_id AS structure_key,
    t.term_id AS structure_id,
    tt.taxonomy AS structure_type,
    t.name AS structure_name,
    t.slug AS structure_slug,
    tt.parent AS parent_structure_id,
    parent_t.name AS parent_structure_name,
    CASE
        WHEN COALESCE(active_meta.meta_value, '1') IN ('1', 'yes', 'true', 'active') THEN 1
        ELSE 0
    END AS is_active
FROM wp_term_taxonomy tt
INNER JOIN wp_terms t
    ON t.term_id = tt.term_id
LEFT JOIN wp_terms parent_t
    ON parent_t.term_id = tt.parent
LEFT JOIN wp_termmeta active_meta
    ON active_meta.term_id = t.term_id
   AND active_meta.meta_key = 'fplms_active'
WHERE tt.taxonomy IN (
    'fplms_city',
    'fplms_company',
    'fplms_channel',
    'fplms_branch',
    'fplms_job_role'
);

CREATE OR REPLACE VIEW wp_fplms_bi_users AS
SELECT
    u.ID AS user_id,
    u.display_name,
    u.user_email,
    u.user_registered,
    COALESCE(NULLIF(meta.user_status, ''), 'active') AS user_status,
    CASE
        WHEN COALESCE(NULLIF(meta.user_status, ''), 'active') = 'active' THEN 1
        ELSE 0
    END AS is_active,
    meta.city_id,
    city.name AS city_name,
    meta.company_id,
    company.name AS company_name,
    meta.channel_id,
    channel.name AS channel_name,
    meta.branch_id,
    branch.name AS branch_name,
    meta.job_role_id,
    job_role.name AS job_role_name
FROM wp_users u
LEFT JOIN (
    SELECT
        user_id,
        MAX(CASE WHEN meta_key = 'fplms_user_status' THEN meta_value END) AS user_status,
        MAX(CASE WHEN meta_key = 'fplms_city' THEN CAST(NULLIF(meta_value, '') AS UNSIGNED) END) AS city_id,
        MAX(CASE WHEN meta_key = 'fplms_company' THEN CAST(NULLIF(meta_value, '') AS UNSIGNED) END) AS company_id,
        MAX(CASE WHEN meta_key = 'fplms_channel' THEN CAST(NULLIF(meta_value, '') AS UNSIGNED) END) AS channel_id,
        MAX(CASE WHEN meta_key = 'fplms_branch' THEN CAST(NULLIF(meta_value, '') AS UNSIGNED) END) AS branch_id,
        MAX(CASE WHEN meta_key = 'fplms_job_role' THEN CAST(NULLIF(meta_value, '') AS UNSIGNED) END) AS job_role_id
    FROM wp_usermeta
    WHERE meta_key IN (
        'fplms_user_status',
        'fplms_city',
        'fplms_company',
        'fplms_channel',
        'fplms_branch',
        'fplms_job_role'
    )
    GROUP BY user_id
) meta
    ON meta.user_id = u.ID
LEFT JOIN wp_terms city ON city.term_id = meta.city_id
LEFT JOIN wp_terms company ON company.term_id = meta.company_id
LEFT JOIN wp_terms channel ON channel.term_id = meta.channel_id
LEFT JOIN wp_terms branch ON branch.term_id = meta.branch_id
LEFT JOIN wp_terms job_role ON job_role.term_id = meta.job_role_id;

CREATE OR REPLACE VIEW wp_fplms_bi_courses AS
SELECT
    ids.course_id,
    COALESCE(NULLIF(p.post_title, ''), CONCAT('Curso #', ids.course_id)) AS course_name,
    COALESCE(NULLIF(p.post_status, ''), 'historical') AS course_status,
    p.post_date AS created_at,
    p.post_modified AS updated_at,
    CASE WHEN p.ID IS NULL THEN 1 ELSE 0 END AS is_historical
FROM (
    SELECT ID AS course_id
    FROM wp_posts
    WHERE post_type = 'stm-courses'

    UNION

    SELECT course_id
    FROM wp_stm_lms_user_courses
    WHERE course_id > 0

    UNION

    SELECT course_id
    FROM wp_fplms_survey_responses
    WHERE course_id > 0

    UNION

    SELECT course_id
    FROM wp_stm_lms_user_quizzes
    WHERE course_id > 0

    UNION

    SELECT course_id
    FROM wp_stm_lms_user_assignments
    WHERE course_id > 0
) ids
LEFT JOIN wp_posts p
    ON p.ID = ids.course_id;

CREATE OR REPLACE VIEW wp_fplms_bi_assignments AS
SELECT
    uc.user_course_id AS assignment_id,
    uc.user_id,
    uc.course_id,
    LEAST(GREATEST(COALESCE(uc.progress_percent, 0), 0), 100) AS progress_percent,
    uc.final_grade,
    NULLIF(TRIM(LOWER(uc.status)), '') AS source_status,
    CASE
        WHEN LOWER(COALESCE(uc.status, '')) IN ('completed', 'passed')
          OR uc.progress_percent >= 100
        THEN 'completed'
        WHEN LOWER(COALESCE(uc.status, '')) = 'enrolled' THEN 'enrolled'
        WHEN uc.progress_percent > 0 THEN 'in_progress'
        ELSE 'not_started'
    END AS normalized_status,
    CASE
        WHEN LOWER(COALESCE(uc.status, '')) IN ('completed', 'passed')
          OR uc.progress_percent >= 100
        THEN 1
        ELSE 0
    END AS is_completed,
    FROM_UNIXTIME(NULLIF(uc.start_time, 0)) AS assigned_at,
    FROM_UNIXTIME(NULLIF(uc.end_time, 0)) AS completed_at,
    DATE(FROM_UNIXTIME(NULLIF(uc.start_time, 0))) AS assigned_date,
    DATE(FROM_UNIXTIME(NULLIF(uc.end_time, 0))) AS completed_date
FROM wp_stm_lms_user_courses uc
WHERE uc.user_id > 0
  AND uc.course_id > 0;

CREATE OR REPLACE VIEW wp_fplms_bi_evaluations AS
SELECT
    CONCAT('quiz-', uq.user_quiz_id) AS evaluation_key,
    'quiz' AS evaluation_type,
    uq.user_quiz_id AS source_id,
    uq.user_id,
    uq.course_id,
    uq.quiz_id AS item_id,
    LEAST(GREATEST(COALESCE(uq.progress, 0), 0), 100) AS score,
    NULLIF(TRIM(LOWER(uq.status)), '') AS evaluation_status,
    CASE
        WHEN LOWER(COALESCE(uq.status, '')) IN ('passed', 'completed') THEN 1
        WHEN LOWER(COALESCE(uq.status, '')) = 'failed' THEN 0
        ELSE NULL
    END AS is_passed,
    CASE
        WHEN uq.created_at IS NULL
          OR CAST(uq.created_at AS CHAR) = '0000-00-00 00:00:00'
        THEN NULL
        ELSE uq.created_at
    END AS evaluated_at
FROM wp_stm_lms_user_quizzes uq
WHERE uq.user_id > 0
  AND uq.course_id > 0

UNION ALL

SELECT
    CONCAT('assignment-', ua.id) AS evaluation_key,
    'assignment' AS evaluation_type,
    ua.id AS source_id,
    ua.user_id,
    ua.course_id,
    ua.assignment_id AS item_id,
    ua.grade AS score,
    NULLIF(TRIM(LOWER(ua.status)), '') AS evaluation_status,
    CASE
        WHEN LOWER(COALESCE(ua.status, '')) IN ('passed', 'completed') THEN 1
        WHEN LOWER(COALESCE(ua.status, '')) IN ('failed', 'not_passed') THEN 0
        ELSE NULL
    END AS is_passed,
    FROM_UNIXTIME(NULLIF(ua.updated_at, 0)) AS evaluated_at
FROM wp_stm_lms_user_assignments ua
WHERE ua.user_id > 0
  AND ua.course_id > 0;

CREATE OR REPLACE VIEW wp_fplms_bi_satisfaction AS
SELECT
    sr.id AS response_id,
    CONCAT(sr.user_id, '-', sr.course_id) AS submission_key,
    sr.user_id,
    sr.course_id,
    sr.question_idx,
    sr.question,
    sr.score,
    ROUND(sr.score / 5.0 * 100, 2) AS satisfaction_percent,
    sr.comment,
    sr.submitted_at,
    DATE(sr.submitted_at) AS submitted_date
FROM wp_fplms_survey_responses sr
WHERE sr.user_id > 0
  AND sr.course_id > 0
  AND sr.score BETWEEN 1 AND 5;

CREATE OR REPLACE VIEW wp_fplms_bi_activity AS
SELECT
    ordered.id AS activity_id,
    ordered.user_id,
    ordered.activity_time,
    DATE(ordered.activity_time) AS activity_date,
    ordered.page_url,
    ordered.previous_activity_time,
    CASE
        WHEN ordered.previous_activity_time IS NULL THEN 0
        WHEN TIMESTAMPDIFF(MINUTE, ordered.previous_activity_time, ordered.activity_time) BETWEEN 0 AND 15
        THEN TIMESTAMPDIFF(SECOND, ordered.previous_activity_time, ordered.activity_time) / 60.0
        ELSE 0
    END AS training_minutes,
    CASE
        WHEN ordered.previous_activity_time IS NULL THEN 1
        WHEN TIMESTAMPDIFF(MINUTE, ordered.previous_activity_time, ordered.activity_time) > 15 THEN 1
        ELSE 0
    END AS starts_new_session
FROM (
    SELECT
        a.id,
        a.user_id,
        a.activity_time,
        a.page_url,
        LAG(a.activity_time) OVER (
            PARTITION BY a.user_id
            ORDER BY a.activity_time, a.id
        ) AS previous_activity_time
    FROM wp_fplms_user_activity a
    WHERE a.user_id > 0
) ordered;

CREATE OR REPLACE VIEW wp_fplms_bi_certificates AS
SELECT
    um.umeta_id AS certificate_id,
    um.user_id,
    CAST(SUBSTRING(um.meta_key, LENGTH('stm_lms_certificate_code_') + 1) AS UNSIGNED) AS course_id,
    um.meta_value AS certificate_code
FROM wp_usermeta um
WHERE um.meta_key LIKE 'stm_lms_certificate_code_%'
  AND NULLIF(TRIM(um.meta_value), '') IS NOT NULL
  AND SUBSTRING(um.meta_key, LENGTH('stm_lms_certificate_code_') + 1) REGEXP '^[0-9]+$';
