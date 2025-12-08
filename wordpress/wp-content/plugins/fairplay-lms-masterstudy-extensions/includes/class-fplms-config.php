<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FairPlay_LMS_Config {

    // Taxonomías de estructuras
    public const TAX_CITY    = 'fplms_city';
    public const TAX_CHANNEL = 'fplms_channel';
    public const TAX_BRANCH  = 'fplms_branch';
    public const TAX_ROLE    = 'fplms_job_role';

    // Meta de términos (activo/inactivo)
    public const META_ACTIVE = 'fplms_active';

    // Meta en usuarios (link a estructuras)
    public const USER_META_CITY    = 'fplms_city';
    public const USER_META_CHANNEL = 'fplms_channel';
    public const USER_META_BRANCH  = 'fplms_branch';
    public const USER_META_ROLE    = 'fplms_job_role';

    // Post types internos
    public const CPT_MODULE = 'fplms_module';
    public const CPT_TOPIC  = 'fplms_topic';

    // Meta para relaciones internas
    public const META_MODULE_COURSE       = 'fplms_course_id';
    public const META_TOPIC_MODULE        = 'fplms_module_id';
    public const META_TOPIC_RESOURCE_ID   = 'fplms_resource_id';
    public const META_TOPIC_RESOURCE_TYPE = 'fplms_resource_type';

    // Opción para matriz de privilegios
    public const OPTION_CAP_MATRIX = 'fplms_capability_matrix';

    // Config MasterStudy – centralizamos aquí para facilitar cambios futuros
    public const MS_ROLE_INSTRUCTOR  = 'stm_lms_instructor';
    public const MS_PT_COURSE        = 'stm-courses';
    public const MS_PT_LESSON        = 'stm-lessons';
    public const MS_PT_QUIZ          = 'stm-quizzes';
    public const MS_META_COURSE_TEACHER = 'stm_lms_course_teacher';

    // Roles propios
    public const ROLE_STUDENT = 'fplms_student';
    public const ROLE_TUTOR   = 'fplms_tutor';

    // Capabilities del plugin
    public const CAP_MANAGE_STRUCTURES = 'fplms_manage_structures';
    public const CAP_MANAGE_USERS      = 'fplms_manage_users';
    public const CAP_MANAGE_COURSES    = 'fplms_manage_courses';
    public const CAP_VIEW_REPORTS      = 'fplms_view_reports';
    public const CAP_VIEW_PROGRESS     = 'fplms_view_progress';
    public const CAP_VIEW_CALENDAR     = 'fplms_view_calendar';

    /**
     * Devuelve la lista de capabilities del plugin.
     */
    public static function get_plugin_caps(): array {
        return [
            self::CAP_MANAGE_STRUCTURES,
            self::CAP_MANAGE_USERS,
            self::CAP_MANAGE_COURSES,
            self::CAP_VIEW_REPORTS,
            self::CAP_VIEW_PROGRESS,
            self::CAP_VIEW_CALENDAR,
        ];
    }
}
