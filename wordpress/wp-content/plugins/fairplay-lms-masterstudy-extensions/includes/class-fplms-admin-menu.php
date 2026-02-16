<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FairPlay_LMS_Admin_Menu {

    /**
     * @var FairPlay_LMS_Admin_Pages
     */
    private $pages;

    /**
     * @var FairPlay_LMS_Structures_Controller
     */
    private $structures;

    /**
     * @var FairPlay_LMS_Users_Controller
     */
    private $users;

    /**
     * @var FairPlay_LMS_Courses_Controller
     */
    private $courses;

    /**
     * @var FairPlay_LMS_Reports_Controller
     */
    private $reports;

    public function __construct(
        FairPlay_LMS_Admin_Pages $pages,
        FairPlay_LMS_Structures_Controller $structures,
        FairPlay_LMS_Users_Controller $users,
        FairPlay_LMS_Courses_Controller $courses,
        FairPlay_LMS_Reports_Controller $reports
    ) {
        $this->pages      = $pages;
        $this->structures = $structures;
        $this->users      = $users;
        $this->courses    = $courses;
        $this->reports    = $reports;
    }

    /**
     * Registro de menú y submenús.
     */
    public function register(): void {

        add_menu_page(
            'FairPlay LMS',
            'FairPlay LMS',
            FairPlay_LMS_Config::CAP_MANAGE_COURSES,
            'fplms-dashboard',
            [ $this->pages, 'render_dashboard_page' ],
            'dashicons-welcome-learn-more',
            3
        );

        add_submenu_page(
            'fplms-dashboard',
            'Inicio',
            'Inicio',
            FairPlay_LMS_Config::CAP_MANAGE_COURSES,
            'fplms-dashboard',
            [ $this->pages, 'render_dashboard_page' ]
        );

        add_submenu_page(
            'fplms-dashboard',
            'Estructuras',
            'Estructuras',
            FairPlay_LMS_Config::CAP_MANAGE_STRUCTURES,
            'fplms-structures',
            [ $this->structures, 'render_page' ]
        );

        // Herramientas de sincronización
        add_submenu_page(
            'fplms-dashboard',
            'Limpieza de Categorías',
            '↳ Limpieza Categorías',
            'manage_options',
            'fplms-cleanup-orphan-categories',
            [ $this, 'render_cleanup_page' ]
        );

        add_submenu_page(
            'fplms-dashboard',
            'Resincronizar Cursos',
            '↳ Resincronizar Cursos',
            'manage_options',
            'fplms-resync-all-courses',
            [ $this, 'render_resync_page' ]
        );

        add_submenu_page(
            'fplms-dashboard',
            'Usuarios',
            'Usuarios',
            FairPlay_LMS_Config::CAP_MANAGE_USERS,
            'fplms-users',
            [ $this->users, 'render_users_page' ]
        );

        add_submenu_page(
            'fplms-dashboard',
            'Cursos',
            'Cursos',
            FairPlay_LMS_Config::CAP_MANAGE_COURSES,
            'fplms-courses',
            [ $this->courses, 'render_courses_page' ]
        );

        add_submenu_page(
            'fplms-dashboard',
            'Avances',
            'Avances',
            FairPlay_LMS_Config::CAP_VIEW_PROGRESS,
            'fplms-progress',
            [ $this->pages, 'render_progress_page' ]
        );

        add_submenu_page(
            'fplms-dashboard',
            'Calendario',
            'Calendario',
            FairPlay_LMS_Config::CAP_VIEW_CALENDAR,
            'fplms-calendar',
            [ $this->pages, 'render_calendar_page' ]
        );

        add_submenu_page(
            'fplms-dashboard',
            'Informes',
            'Informes',
            FairPlay_LMS_Config::CAP_VIEW_REPORTS,
            'fplms-reports',
            [ $this->reports, 'render_reports_page' ]
        );
    }

    /**
     * Renderizar página de limpieza de categorías huérfanas
     */
    public function render_cleanup_page(): void {
        require_once FPLMS_PLUGIN_DIR . '/cleanup-orphan-categories.php';
    }

    /**
     * Renderizar página de resincronización de cursos
     */
    public function render_resync_page(): void {
        require_once FPLMS_PLUGIN_DIR . '/resync-all-courses.php';
    }
}
