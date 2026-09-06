-- Rollback de la capa Power BI. No elimina datos operativos.

DROP VIEW IF EXISTS wp_fplms_bi_certificates;
DROP VIEW IF EXISTS wp_fplms_bi_activity;
DROP VIEW IF EXISTS wp_fplms_bi_satisfaction;
DROP VIEW IF EXISTS wp_fplms_bi_evaluations;
DROP VIEW IF EXISTS wp_fplms_bi_assignments;
DROP VIEW IF EXISTS wp_fplms_bi_courses;
DROP VIEW IF EXISTS wp_fplms_bi_users;
DROP VIEW IF EXISTS wp_fplms_bi_structures;
