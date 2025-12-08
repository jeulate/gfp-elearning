<?php
if (!defined('ABSPATH')) {
    exit;
}

class FairPlay_LMS_Plugin
{

    /**
     * @var FairPlay_LMS_Structures_Controller
     */
    private $structures;

    /**
     * @var FairPlay_LMS_Progress_Service
     */
    private $progress;

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

    /**
     * @var FairPlay_LMS_Admin_Pages
     */
    private $pages;

    /**
     * @var FairPlay_LMS_Admin_Menu
     */
    private $menu;

    public function __construct()
    {

        $this->structures = new FairPlay_LMS_Structures_Controller();
        $this->progress = new FairPlay_LMS_Progress_Service();
        $this->users = new FairPlay_LMS_Users_Controller($this->structures, $this->progress);
        $this->courses = new FairPlay_LMS_Courses_Controller();
        $this->reports = new FairPlay_LMS_Reports_Controller($this->users, $this->structures, $this->progress);

        // 👇 ahora el dashboard recibe cursos y usuarios
        $this->pages = new FairPlay_LMS_Admin_Pages($this->courses, $this->users);

        $this->menu = new FairPlay_LMS_Admin_Menu(
            $this->pages,
            $this->structures,
            $this->users,
            $this->courses,
            $this->reports
        );

    }

    /**
     * Registra todos los hooks del plugin.
     */
    private function register_hooks(): void
    {

        // Menú admin
        add_action('admin_menu', [$this->menu, 'register']);

        // Estructuras
        add_action('init', [$this->structures, 'register_taxonomies']);
        add_action('admin_init', [$this->structures, 'handle_form']);

        // Post types internos (módulos y temas)
        add_action('init', [$this->courses, 'register_post_types']);

        // Formularios de cursos / módulos / temas / profesor
        add_action('admin_init', [$this->courses, 'handle_form']);

        // Usuarios: vincular estructuras
        add_action('show_user_profile', [$this->users, 'render_user_structures_fields']);
        add_action('edit_user_profile', [$this->users, 'render_user_structures_fields']);
        add_action('personal_options_update', [$this->users, 'save_user_structures_fields']);
        add_action('edit_user_profile_update', [$this->users, 'save_user_structures_fields']);

        // Matriz de privilegios
        add_action('admin_init', [$this->users, 'handle_caps_matrix_form']);

        // Exportaciones / informes
        add_action('admin_init', [$this->reports, 'handle_export']);
    }
}
