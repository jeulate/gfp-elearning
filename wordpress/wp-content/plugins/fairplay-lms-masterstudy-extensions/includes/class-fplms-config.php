<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FairPlay_LMS_Config {

    // Taxonomías de estructuras
    public const TAX_CITY    = 'fplms_city';
    public const TAX_COMPANY = 'fplms_company';
    public const TAX_CHANNEL = 'fplms_channel';
    public const TAX_BRANCH  = 'fplms_branch';
    public const TAX_ROLE    = 'fplms_job_role';

    // Meta de términos (activo/inactivo)
    public const META_ACTIVE = 'fplms_active';

    // Meta de términos para relaciones jerárquicas (Ciudad -> Empresa -> Canal -> Sucursal -> Cargo)
    public const META_TERM_PARENT_CITY    = 'fplms_parent_city';     // Para canales, sucursales, cargos (DEPRECATED)
    public const META_TERM_PARENT_CHANNEL = 'fplms_parent_channel';  // Para sucursales, cargos (DEPRECATED)
    public const META_TERM_PARENT_BRANCH  = 'fplms_parent_branch';   // Para cargos (DEPRECATED)
    public const META_TERM_CITIES         = 'fplms_cities';          // Array JSON de ciudades (para empresas)
    public const META_TERM_COMPANIES      = 'fplms_companies';       // Array JSON de empresas (para canales)
    public const META_TERM_CHANNELS       = 'fplms_channels';        // Array JSON de canales (para sucursales)
    public const META_TERM_BRANCHES       = 'fplms_branches';        // Array JSON de sucursales (para cargos)
    
    // Aliases para relaciones jerárquicas (más explícitas)
    public const META_COMPANY_CITIES    = 'fplms_cities';      // Array de IDs de ciudades asociadas a una empresa
    public const META_CHANNEL_COMPANIES = 'fplms_companies';   // Array de IDs de empresas asociadas a un canal
    public const META_BRANCH_CHANNELS   = 'fplms_channels';    // Array de IDs de canales asociados a una sucursal
    public const META_ROLE_BRANCHES     = 'fplms_branches';    // Array de IDs de sucursales asociadas a un cargo

    // Meta en usuarios (link a estructuras)
    public const USER_META_CITY    = 'fplms_city';
    public const USER_META_COMPANY = 'fplms_company';
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

    // Meta de cursos para visibilidad por estructura
    public const META_COURSE_CITIES    = 'fplms_course_cities';
    public const META_COURSE_COMPANIES = 'fplms_course_companies';
    public const META_COURSE_CHANNELS  = 'fplms_course_channels';
    public const META_COURSE_BRANCHES  = 'fplms_course_branches';
    public const META_COURSE_ROLES     = 'fplms_course_roles';

    // Meta para lecciones asignadas a cursos
    public const META_COURSE_LESSONS = 'fplms_course_lessons'; // Array de IDs de lecciones

    // Opción para matriz de privilegios
    public const OPTION_CAP_MATRIX = 'fplms_capability_matrix';

    // Config MasterStudy – centralizamos aquí para facilitar cambios futuros
    public const MS_ROLE_INSTRUCTOR     = 'stm_lms_instructor';
    public const MS_PT_COURSE           = 'stm-courses';
    public const MS_PT_LESSON           = 'stm-lessons';
    public const MS_PT_QUIZ             = 'stm-quizzes';
    public const MS_TAX_COURSE_CATEGORY = 'stm_lms_course_taxonomy';
    public const MS_META_COURSE_TEACHER = 'stm_lms_course_teacher';
    public const MS_META_CURRICULUM     = 'curriculum'; // Meta key para el curriculum de MasterStudy

    // Roles propios (DEPRECATED - mantener por compatibilidad temporal)
    // Los estudiantes ahora usan 'subscriber' (rol nativo WordPress/MasterStudy)
    // Los docentes usan directamente 'stm_lms_instructor' (rol MasterStudy)
    public const ROLE_STUDENT = 'fplms_student'; // @deprecated Use 'subscriber' instead
    public const ROLE_TUTOR   = 'fplms_tutor';   // @deprecated Use 'stm_lms_instructor' instead

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
