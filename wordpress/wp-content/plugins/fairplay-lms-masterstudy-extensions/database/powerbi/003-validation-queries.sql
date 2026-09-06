-- Ejecutar después de 001-create-reporting-views.sql.

SELECT 'structures' AS view_name, COUNT(*) AS row_count FROM wp_fplms_bi_structures
UNION ALL SELECT 'users', COUNT(*) FROM wp_fplms_bi_users
UNION ALL SELECT 'courses', COUNT(*) FROM wp_fplms_bi_courses
UNION ALL SELECT 'assignments', COUNT(*) FROM wp_fplms_bi_assignments
UNION ALL SELECT 'evaluations', COUNT(*) FROM wp_fplms_bi_evaluations
UNION ALL SELECT 'satisfaction', COUNT(*) FROM wp_fplms_bi_satisfaction
UNION ALL SELECT 'activity', COUNT(*) FROM wp_fplms_bi_activity
UNION ALL SELECT 'certificates', COUNT(*) FROM wp_fplms_bi_certificates;

SELECT
    COUNT(*) AS courses_assigned,
    SUM(is_completed) AS courses_completed,
    ROUND(100.0 * SUM(is_completed) / NULLIF(COUNT(*), 0), 2) AS completion_rate
FROM wp_fplms_bi_assignments;

SELECT
    COUNT(DISTINCT submission_key) AS survey_submissions,
    COUNT(DISTINCT course_id) AS evaluated_courses,
    COUNT(DISTINCT user_id) AS respondents,
    ROUND(AVG(score), 2) AS average_score,
    ROUND(AVG(satisfaction_percent), 2) AS average_satisfaction_percent
FROM wp_fplms_bi_satisfaction;

SELECT
    ROUND(SUM(training_minutes) / 60.0, 2) AS total_training_hours,
    COUNT(DISTINCT user_id) AS users_with_activity,
    SUM(starts_new_session) AS sessions
FROM wp_fplms_bi_activity;

SELECT
    COUNT(*) AS issued_certificates,
    COUNT(DISTINCT user_id) AS certified_students,
    COUNT(DISTINCT CONCAT(user_id, '-', course_id)) AS certified_user_courses
FROM wp_fplms_bi_certificates;

SELECT
    evaluation_type,
    COUNT(*) AS evaluations,
    SUM(is_passed = 1) AS passed,
    SUM(is_passed = 0) AS failed,
    ROUND(AVG(score), 2) AS average_score
FROM wp_fplms_bi_evaluations
GROUP BY evaluation_type
ORDER BY evaluation_type;

SELECT
    COUNT(*) AS completed_with_final_grade,
    ROUND(AVG(final_grade), 2) AS course_weighted_average
FROM wp_fplms_bi_assignments
WHERE is_completed = 1
  AND final_grade IS NOT NULL;

SELECT
    ROUND(AVG(user_average), 2) AS student_weighted_overall_average
FROM (
    SELECT user_id, AVG(final_grade) AS user_average
    FROM wp_fplms_bi_assignments
    WHERE is_completed = 1
      AND final_grade IS NOT NULL
    GROUP BY user_id
) averages_by_user;
