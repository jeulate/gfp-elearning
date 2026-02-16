<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FairPlay_LMS_Courses_Controller {

    /**
     * @var FairPlay_LMS_Structures_Controller
     */
    private $structures;

    public function __construct( FairPlay_LMS_Structures_Controller $structures = null ) {
        $this->structures = $structures;
    }

    /**
     * Registra post types internos de módulos y temas.
     */
    public function register_post_types(): void {

        register_post_type(
            FairPlay_LMS_Config::CPT_MODULE,
            [
                'label'           => 'Módulos',
                'public'          => false,
                'show_ui'         => false,
                'show_in_menu'    => false,
                'supports'        => [ 'title', 'editor' ],
                'capability_type' => 'post',
            ]
        );

        register_post_type(
            FairPlay_LMS_Config::CPT_TOPIC,
            [
                'label'           => 'Temas',
                'public'          => false,
                'show_ui'         => false,
                'show_in_menu'    => false,
                'supports'        => [ 'title', 'editor' ],
                'capability_type' => 'post',
            ]
        );
    }

    /**
     * Manejo de formularios de cursos / módulos / temas / profesor.
     */
    public function handle_form(): void {

        if ( ! isset( $_POST['fplms_courses_action'] ) ) {
            return;
        }

        if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_COURSES ) ) {
            return;
        }

        if (
            ! isset( $_POST['fplms_courses_nonce'] ) ||
            ! wp_verify_nonce( $_POST['fplms_courses_nonce'], 'fplms_courses_save' )
        ) {
            return;
        }

        $action    = sanitize_text_field( wp_unslash( $_POST['fplms_courses_action'] ) );
        $course_id = isset( $_POST['fplms_course_id'] ) ? absint( $_POST['fplms_course_id'] ) : 0;
        $module_id = isset( $_POST['fplms_module_id'] ) ? absint( $_POST['fplms_module_id'] ) : 0;

        // Asignar profesor
        if ( 'assign_instructor' === $action && $course_id ) {

            $instructor_id = isset( $_POST['fplms_instructor_id'] ) ? absint( $_POST['fplms_instructor_id'] ) : 0;

            if ( $instructor_id > 0 ) {

                update_post_meta( $course_id, FairPlay_LMS_Config::MS_META_COURSE_TEACHER, $instructor_id );

                $user = get_user_by( 'id', $instructor_id );
                if ( $user && ! in_array( FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR, (array) $user->roles, true ) ) {
                    $user->add_role( FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR );
                }
            } else {
                delete_post_meta( $course_id, FairPlay_LMS_Config::MS_META_COURSE_TEACHER );
            }

            wp_safe_redirect(
                add_query_arg(
                    [ 'page' => 'fplms-courses' ],
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }

        // Asignar estructuras a curso
        if ( 'assign_structures' === $action && $course_id ) {
            $this->save_course_structures( $course_id );

            wp_safe_redirect(
                add_query_arg(
                    [ 'page' => 'fplms-courses' ],
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }

        // Crear módulo
        if ( 'create_module' === $action && $course_id ) {

            $module_title   = sanitize_text_field( wp_unslash( $_POST['fplms_module_title'] ?? '' ) );
            $module_summary = wp_kses_post( wp_unslash( $_POST['fplms_module_summary'] ?? '' ) );

            if ( $module_title ) {
                $new_module_id = wp_insert_post(
                    [
                        'post_type'    => FairPlay_LMS_Config::CPT_MODULE,
                        'post_title'   => $module_title,
                        'post_content' => $module_summary,
                        'post_status'  => 'publish',
                    ]
                );

                if ( $new_module_id && ! is_wp_error( $new_module_id ) ) {
                    update_post_meta( $new_module_id, FairPlay_LMS_Config::META_MODULE_COURSE, $course_id );
                }
            }

            wp_safe_redirect(
                add_query_arg(
                    [
                        'page'      => 'fplms-courses',
                        'view'      => 'modules',
                        'course_id' => $course_id,
                    ],
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }

        // Crear tema
        if ( 'create_topic' === $action && $course_id && $module_id ) {

            $topic_title   = sanitize_text_field( wp_unslash( $_POST['fplms_topic_title'] ?? '' ) );
            $topic_summary = wp_kses_post( wp_unslash( $_POST['fplms_topic_summary'] ?? '' ) );
            $resource_id   = isset( $_POST['fplms_topic_resource'] ) ? absint( $_POST['fplms_topic_resource'] ) : 0;
            $resource_type = sanitize_text_field( wp_unslash( $_POST['fplms_topic_resource_type'] ?? '' ) );

            if ( $topic_title ) {
                $new_topic_id = wp_insert_post(
                    [
                        'post_type'    => FairPlay_LMS_Config::CPT_TOPIC,
                        'post_title'   => $topic_title,
                        'post_content' => $topic_summary,
                        'post_status'  => 'publish',
                    ]
                );

                if ( $new_topic_id && ! is_wp_error( $new_topic_id ) ) {
                    update_post_meta( $new_topic_id, FairPlay_LMS_Config::META_TOPIC_MODULE, $module_id );
                    if ( $resource_id ) {
                        update_post_meta( $new_topic_id, FairPlay_LMS_Config::META_TOPIC_RESOURCE_ID, $resource_id );
                    }
                    if ( $resource_type ) {
                        update_post_meta( $new_topic_id, FairPlay_LMS_Config::META_TOPIC_RESOURCE_TYPE, $resource_type );
                    }
                }
            }

            wp_safe_redirect(
                add_query_arg(
                    [
                        'page'      => 'fplms-courses',
                        'view'      => 'topics',
                        'course_id' => $course_id,
                        'module_id' => $module_id,
                    ],
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }

        // Crear lección MasterStudy
        if ( 'create_lesson' === $action && $course_id ) {
            $this->handle_create_lesson( $course_id );
        }

        // Asignar lecciones a curso
        if ( 'assign_lessons' === $action && $course_id ) {
            $this->handle_assign_lessons( $course_id );
        }

        // Desasignar lección de curso
        if ( 'unassign_lesson' === $action && $course_id ) {
            $lesson_id = isset( $_POST['fplms_lesson_id'] ) ? absint( $_POST['fplms_lesson_id'] ) : 0;
            if ( $lesson_id ) {
                $this->unassign_lesson_from_course( $course_id, $lesson_id );
            }
            wp_safe_redirect(
                add_query_arg(
                    [
                        'page'      => 'fplms-courses',
                        'view'      => 'lessons',
                        'course_id' => $course_id,
                        'unassigned' => 1,
                    ],
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }

        // Eliminar lección
        if ( 'delete_lesson' === $action ) {
            $lesson_id = isset( $_POST['fplms_lesson_id'] ) ? absint( $_POST['fplms_lesson_id'] ) : 0;
            if ( $lesson_id ) {
                wp_delete_post( $lesson_id, true );
            }
            wp_safe_redirect(
                add_query_arg(
                    [
                        'page'      => 'fplms-courses',
                        'view'      => 'lessons',
                        'course_id' => $course_id,
                    ],
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }

        // Crear sección en el curriculum
        if ( 'create_section' === $action && $course_id ) {
            $section_title = sanitize_text_field( wp_unslash( $_POST['section_title'] ?? '' ) );
            
            if ( $section_title ) {
                $curriculum = get_post_meta( $course_id, FairPlay_LMS_Config::MS_META_CURRICULUM, true );
                if ( ! is_array( $curriculum ) ) {
                    $curriculum = [];
                }

                // Agregar nueva sección al curriculum
                $curriculum[] = [
                    'title'     => $section_title,
                    'materials' => [],
                ];

                update_post_meta( $course_id, FairPlay_LMS_Config::MS_META_CURRICULUM, $curriculum );
            }

            wp_safe_redirect(
                add_query_arg(
                    [
                        'page'      => 'fplms-courses',
                        'view'      => 'modules',
                        'course_id' => $course_id,
                        'created'   => 1,
                    ],
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }

        // Agregar lecciones a una sección
        if ( 'add_lessons_to_section' === $action && $course_id ) {
            $section_index = isset( $_POST['section_index'] ) ? absint( $_POST['section_index'] ) : 0;
            $lesson_ids = isset( $_POST['lesson_ids'] ) && is_array( $_POST['lesson_ids'] ) 
                ? array_map( 'absint', $_POST['lesson_ids'] ) 
                : [];

            if ( ! empty( $lesson_ids ) ) {
                $curriculum = get_post_meta( $course_id, FairPlay_LMS_Config::MS_META_CURRICULUM, true );
                
                if ( is_array( $curriculum ) && isset( $curriculum[ $section_index ] ) ) {
                    if ( ! isset( $curriculum[ $section_index ]['materials'] ) ) {
                        $curriculum[ $section_index ]['materials'] = [];
                    }

                    // Agregar cada lección a los materiales de la sección
                    foreach ( $lesson_ids as $lesson_id ) {
                        $lesson = get_post( $lesson_id );
                        if ( $lesson && FairPlay_LMS_Config::MS_PT_LESSON === $lesson->post_type ) {
                            $curriculum[ $section_index ]['materials'][] = [
                                'post_id' => $lesson_id,
                                'title'   => $lesson->post_title,
                            ];
                        }
                    }

                    update_post_meta( $course_id, FairPlay_LMS_Config::MS_META_CURRICULUM, $curriculum );
                }
            }

            wp_safe_redirect(
                add_query_arg(
                    [
                        'page'      => 'fplms-courses',
                        'view'      => 'modules',
                        'course_id' => $course_id,
                        'added'     => count( $lesson_ids ),
                    ],
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }

        // Eliminar sección del curriculum
        if ( 'delete_section' === $action && $course_id ) {
            $section_index = isset( $_POST['section_index'] ) ? absint( $_POST['section_index'] ) : 0;

            $curriculum = get_post_meta( $course_id, FairPlay_LMS_Config::MS_META_CURRICULUM, true );
            
            if ( is_array( $curriculum ) && isset( $curriculum[ $section_index ] ) ) {
                array_splice( $curriculum, $section_index, 1 );
                update_post_meta( $course_id, FairPlay_LMS_Config::MS_META_CURRICULUM, $curriculum );
            }

            wp_safe_redirect(
                add_query_arg(
                    [
                        'page'      => 'fplms-courses',
                        'view'      => 'modules',
                        'course_id' => $course_id,
                        'deleted'   => 1,
                    ],
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }

        // Remover material de una sección
        if ( 'remove_material_from_section' === $action && $course_id ) {
            $section_index = isset( $_POST['section_index'] ) ? absint( $_POST['section_index'] ) : 0;
            $material_index = isset( $_POST['material_index'] ) ? absint( $_POST['material_index'] ) : 0;

            $curriculum = get_post_meta( $course_id, FairPlay_LMS_Config::MS_META_CURRICULUM, true );
            
            if ( is_array( $curriculum ) && isset( $curriculum[ $section_index ]['materials'][ $material_index ] ) ) {
                array_splice( $curriculum[ $section_index ]['materials'], $material_index, 1 );
                update_post_meta( $course_id, FairPlay_LMS_Config::MS_META_CURRICULUM, $curriculum );
            }

            wp_safe_redirect(
                add_query_arg(
                    [
                        'page'      => 'fplms-courses',
                        'view'      => 'modules',
                        'course_id' => $course_id,
                        'removed'   => 1,
                    ],
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }
    }

    /**
     * Entrada principal para la página "Cursos".
     */
    public function render_courses_page(): void {

        if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_COURSES ) ) {
            wp_die( 'No tienes permisos para acceder a esta sección.' );
        }

        $view      = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : 'courses';
        $course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
        $module_id = isset( $_GET['module_id'] ) ? absint( $_GET['module_id'] ) : 0;

        echo '<div class="wrap">';
        echo '<h1>Cursos</h1>';

        if ( 'modules' === $view && $course_id ) {
            $this->render_course_modules_view( $course_id );
        } elseif ( 'topics' === $view && $course_id && $module_id ) {
            $this->render_module_topics_view( $course_id, $module_id );
        } elseif ( 'lessons' === $view && $course_id ) {
            $this->render_course_lessons_view( $course_id );
        } elseif ( 'structures' === $view && $course_id ) {
            $this->render_course_structures_view( $course_id );
        } else {
            $this->render_course_list_view();
        }

        echo '</div>';
    }

    /**
     * Usuarios que pueden ser profesores.
     */
    private function get_available_instructors(): array {

        $user_query = new WP_User_Query(
            [
                'role__in' => [
                    FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR,
                    'administrator',
                ],
                'number'   => 300,
                'orderby'  => 'display_name',
                'order'    => 'ASC',
            ]
        );

        $results = (array) $user_query->get_results();
        
        // DEBUG: Descomentar estas líneas si necesitas diagnosticar problemas con instructores
        // error_log( 'FairPlay LMS: Instructores encontrados: ' . count( $results ) );
        // foreach ( $results as $user ) {
        //     error_log( '  - ' . $user->display_name . ' (ID: ' . $user->ID . ', Login: ' . $user->user_login . ')' );
        // }
        
        return $results;
    }

    /**
     * Listado de cursos MasterStudy con asignación de profesor.
     */
    private function render_course_list_view(): void {

        if ( ! post_type_exists( FairPlay_LMS_Config::MS_PT_COURSE ) ) {
            echo '<p><strong>Advertencia:</strong> No se encontró el tipo de contenido <code>' . esc_html( FairPlay_LMS_Config::MS_PT_COURSE ) . '</code>. Verifica que MasterStudy LMS esté activo.</p>';
            return;
        }

        // Paginación
        $paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $per_page = 10;
        $offset = ( $paged - 1 ) * $per_page;

        $total_courses = wp_count_posts( FairPlay_LMS_Config::MS_PT_COURSE );
        $total_published = isset( $total_courses->publish ) ? (int) $total_courses->publish : 0;
        $total_pages = ceil( $total_published / $per_page );

        $courses = get_posts(
            [
                'post_type'      => FairPlay_LMS_Config::MS_PT_COURSE,
                'posts_per_page' => $per_page,
                'offset'         => $offset,
                'post_status'    => 'publish',
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]
        );

        $instructors = $this->get_available_instructors();
        
        // Estilos CSS para diseño minimalista
        ?>
        <style>
            .fplms-courses-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding: 15px 0;
                border-bottom: 2px solid #2271b1;
            }
            .fplms-create-course-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                background: #2271b1;
                color: white;
                padding: 10px 20px;
                border-radius: 4px;
                text-decoration: none;
                font-weight: 600;
                transition: background 0.2s;
            }
            .fplms-create-course-btn:hover {
                background: #135e96;
                color: white;
            }
            .fplms-actions-compact {
                display: flex;
                gap: 6px;
                flex-wrap: wrap;
            }
            .fplms-action-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 36px;
                height: 36px;
                padding: 0;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                background: #fff;
                color: #2c3338;
                cursor: pointer;
                transition: all 0.2s;
                position: relative;
                font-size: 16px;
            }
            .fplms-action-btn:hover {
                background: #2271b1;
                color: white;
                border-color: #2271b1;
                transform: translateY(-2px);
            }
            .fplms-action-btn .tooltip {
                visibility: hidden;
                position: absolute;
                bottom: 100%;
                left: 50%;
                transform: translateX(-50%);
                background: #1d2327;
                color: white;
                padding: 6px 12px;
                border-radius: 4px;
                font-size: 12px;
                white-space: nowrap;
                margin-bottom: 8px;
                opacity: 0;
                transition: opacity 0.2s;
                z-index: 1000;
            }
            .fplms-action-btn .tooltip::after {
                content: '';
                position: absolute;
                top: 100%;
                left: 50%;
                transform: translateX(-50%);
                border: 4px solid transparent;
                border-top-color: #1d2327;
            }
            .fplms-action-btn:hover .tooltip {
                visibility: visible;
                opacity: 1;
            }
            .fplms-edit-btn {
                background: #f0f0f1;
            }
            .fplms-edit-btn:hover {
                background: #646970;
                color: white;
                border-color: #646970;
            }
            .fplms-instructor-form {
                display: flex;
                gap: 6px;
                align-items: center;
            }
            .fplms-instructor-form select {
                max-width: 200px;
                font-size: 13px;
            }
            .fplms-instructor-form .button {
                padding: 4px 12px;
                height: 32px;
                font-size: 13px;
            }
            .fplms-course-title {
                font-weight: 600;
                color: #2271b1;
            }
            .fplms-course-id {
                color: #646970;
                font-size: 0.9em;
            }
            .fplms-structures-tags {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                font-size: 0.85em;
            }
            .fplms-structure-tag {
                display: inline-block;
                padding: 3px 8px;
                background: #f0f0f1;
                border-radius: 3px;
                color: #2c3338;
            }
            .fplms-pagination {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 10px;
                margin-top: 20px;
                padding: 15px 0;
            }
            .fplms-pagination a,
            .fplms-pagination span {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 36px;
                height: 36px;
                padding: 0 12px;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                background: #fff;
                color: #2c3338;
                text-decoration: none;
                transition: all 0.2s;
            }
            .fplms-pagination a:hover {
                background: #2271b1;
                color: white;
                border-color: #2271b1;
            }
            .fplms-pagination .current {
                background: #2271b1;
                color: white;
                border-color: #2271b1;
                font-weight: 600;
            }
            .fplms-info-box {
                background: #e7f5fe;
                border-left: 4px solid #2271b1;
                padding: 12px 16px;
                margin-bottom: 20px;
                border-radius: 4px;
            }
            .fplms-info-box p {
                margin: 0;
                color: #1d2327;
            }
            .fplms-table-minimal {
                background: white;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                overflow: hidden;
            }
            .fplms-table-minimal thead {
                background: #f6f7f7;
            }
            .fplms-table-minimal th {
                font-weight: 600;
                font-size: 13px;
                color: #1d2327;
                padding: 12px;
            }
            .fplms-table-minimal td {
                padding: 12px;
                border-top: 1px solid #f0f0f1;
                vertical-align: middle;
            }
            .fplms-empty-state {
                text-align: center;
                padding: 60px 20px;
            }
            .fplms-empty-state-icon {
                font-size: 64px;
                margin-bottom: 20px;
                opacity: 0.3;
            }
            .fplms-empty-state h3 {
                color: #646970;
                margin-bottom: 10px;
            }
            .fplms-empty-state p {
                color: #787c82;
                margin-bottom: 20px;
            }
        </style>

        <div class="fplms-courses-header">
            <div>
                <h2 style="margin: 0;">📚 Cursos MasterStudy</h2>
                <p style="margin: 5px 0 0 0; color: #646970;">
                    <?php echo esc_html( $total_published ); ?> curso<?php echo $total_published !== 1 ? 's' : ''; ?> encontrado<?php echo $total_published !== 1 ? 's' : ''; ?>
                </p>
            </div>
            <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . FairPlay_LMS_Config::MS_PT_COURSE ) ); ?>" class="fplms-create-course-btn">
                <span style="font-size: 18px;">➕</span>
                <span>Crear Nuevo Curso</span>
            </a>
        </div>

        <?php if ( empty( $courses ) ) : ?>
            <div class="fplms-empty-state">
                <div class="fplms-empty-state-icon">📚</div>
                <h3>No hay cursos creados todavía</h3>
                <p>Crea tu primer curso para comenzar a organizar tu contenido educativo.</p>
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . FairPlay_LMS_Config::MS_PT_COURSE ) ); ?>" class="button button-primary button-hero">
                    ➕ Crear Primer Curso
                </a>
            </div>
        <?php else : ?>
            <div class="fplms-info-box">
                <p><strong>💡 Consejo:</strong> Usa los iconos de acción para gestionar cada curso. Puedes administrar módulos, lecciones, estructuras y editar el contenido.</p>
            </div>

            <table class="widefat striped fplms-table-minimal">
                <thead>
                    <tr>
                        <th style="width: 30%;">Curso</th>
                        <th style="width: 15%;">Profesor</th>
                        <th style="width: 25%;">Estructuras</th>
                        <th style="width: 15%;">Asignar Profesor</th>
                        <th style="width: 15%;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $courses as $course ) : ?>
                    <?php
                    $modules_url = add_query_arg(
                        [
                            'page'      => 'fplms-courses',
                            'view'      => 'modules',
                            'course_id' => $course->ID,
                        ],
                        admin_url( 'admin.php' )
                    );

                    $lessons_url = add_query_arg(
                        [
                            'page'      => 'fplms-courses',
                            'view'      => 'lessons',
                            'course_id' => $course->ID,
                        ],
                        admin_url( 'admin.php' )
                    );

                    $structures_url = add_query_arg(
                        [
                            'page'      => 'fplms-courses',
                            'view'      => 'structures',
                            'course_id' => $course->ID,
                        ],
                        admin_url( 'admin.php' )
                    );

                    // Obtener instructor del curso con fallbacks
                    $teacher_id = (int) get_post_meta( $course->ID, FairPlay_LMS_Config::MS_META_COURSE_TEACHER, true );
                    
                    // Fallback: Intentar otros meta keys que MasterStudy podría usar
                    if ( ! $teacher_id ) {
                        // Algunas versiones de MasterStudy usan 'instructor_id'
                        $teacher_id = (int) get_post_meta( $course->ID, 'instructor_id', true );
                    }
                    
                    if ( ! $teacher_id ) {
                        // Intentar con el autor del post
                        $teacher_id = (int) $course->post_author;
                    }
                    
                    $teacher_name = $teacher_id ? get_the_author_meta( 'display_name', $teacher_id ) : '';
                    if ( ! $teacher_name ) {
                        $teacher_name = '— Sin asignar —';
                    }

                    // Obtener estructuras del curso
                    $course_structures = $this->get_course_structures( $course->ID );
                    ?>
                    <tr>
                        <td>
                            <div class="fplms-course-title"><?php echo esc_html( get_the_title( $course ) ); ?></div>
                            <div class="fplms-course-id">ID: <?php echo esc_html( $course->ID ); ?></div>
                        </td>
                        <td><?php echo esc_html( $teacher_name ); ?></td>
                        <td>
                            <div class="fplms-structures-tags">
                                <?php echo wp_kses_post( $this->format_course_structures_compact( $course_structures ) ); ?>
                            </div>
                        </td>
                        <td>
                            <form method="post" class="fplms-instructor-form">
                                <?php wp_nonce_field( 'fplms_courses_save', 'fplms_courses_nonce' ); ?>
                                <input type="hidden" name="fplms_courses_action" value="assign_instructor">
                                <input type="hidden" name="fplms_course_id" value="<?php echo esc_attr( $course->ID ); ?>">

                                <select name="fplms_instructor_id">
                                    <option value="0">— Sin profesor —</option>
                                    <?php foreach ( $instructors as $user ) : ?>
                                        <option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $teacher_id, $user->ID ); ?>>
                                            <?php echo esc_html( $user->display_name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <button type="submit" class="button button-primary button-small">💾</button>
                            </form>
                        </td>
                        <td>
                            <div class="fplms-actions-compact">
                                <a href="<?php echo esc_url( $modules_url ); ?>" class="fplms-action-btn" title="Módulos">
                                    📚
                                    <span class="tooltip">Módulos</span>
                                </a>
                                <a href="<?php echo esc_url( $lessons_url ); ?>" class="fplms-action-btn" title="Lecciones">
                                    📖
                                    <span class="tooltip">Lecciones</span>
                                </a>
                                <a href="<?php echo esc_url( $structures_url ); ?>" class="fplms-action-btn" title="Estructuras">
                                    🏢
                                    <span class="tooltip">Estructuras</span>
                                </a>
                                <a href="<?php echo esc_url( get_edit_post_link( $course->ID ) ); ?>" class="fplms-action-btn fplms-edit-btn" title="Editar">
                                    ✏️
                                    <span class="tooltip">Editar Curso</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ( $total_pages > 1 ) : ?>
                <div class="fplms-pagination">
                    <?php if ( $paged > 1 ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1 ) ); ?>">
                            ← Anterior
                        </a>
                    <?php endif; ?>

                    <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
                        <?php if ( $i === $paged ) : ?>
                            <span class="current"><?php echo esc_html( $i ); ?></span>
                        <?php else : ?>
                            <a href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>">
                                <?php echo esc_html( $i ); ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ( $paged < $total_pages ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1 ) ); ?>">
                            Siguiente →
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php
    }

    /**
     * Vista de gestión de módulos/secciones y lecciones del curriculum MasterStudy.
     */
    private function render_course_modules_view( int $course_id ): void {

        $course = get_post( $course_id );
        if ( ! $course || FairPlay_LMS_Config::MS_PT_COURSE !== $course->post_type ) {
            echo '<p>Curso no válido.</p>';
            return;
        }

        $back_url = add_query_arg( [ 'page' => 'fplms-courses' ], admin_url( 'admin.php' ) );

        // Obtener curriculum actual
        $curriculum = get_post_meta( $course_id, FairPlay_LMS_Config::MS_META_CURRICULUM, true );
        if ( ! is_array( $curriculum ) ) {
            $curriculum = [];
        }

        // Obtener todas las lecciones disponibles no asignadas
        $all_lessons = $this->get_all_lessons();
        $assigned_lesson_ids = [];
        
        // Recopilar IDs de lecciones ya asignadas
        foreach ( $curriculum as $item ) {
            if ( isset( $item['type'] ) && FairPlay_LMS_Config::MS_PT_LESSON === $item['type'] && isset( $item['id'] ) ) {
                $assigned_lesson_ids[] = (int) $item['id'];
            } elseif ( isset( $item['materials'] ) && is_array( $item['materials'] ) ) {
                foreach ( $item['materials'] as $material ) {
                    if ( isset( $material['post_id'] ) ) {
                        $assigned_lesson_ids[] = (int) $material['post_id'];
                    }
                }
            }
        }

        $available_lessons = array_filter( $all_lessons, function( $lesson ) use ( $assigned_lesson_ids ) {
            return ! in_array( $lesson->ID, $assigned_lesson_ids, true );
        });

        ?>
        <div class="wrap">
            <p><a href="<?php echo esc_url( $back_url ); ?>">&larr; Volver al listado de cursos</a></p>
            
            <h2>📚 Gestión de Curriculum: <?php echo esc_html( get_the_title( $course ) ); ?></h2>
            <p class="description">
                Organiza el contenido del curso en secciones y asigna lecciones a cada sección.
            </p>

            <style>
                .curriculum-builder { margin: 20px 0; }
                .curriculum-section { 
                    background: #fff;
                    border: 1px solid #ccd0d4;
                    border-radius: 4px;
                    margin-bottom: 15px;
                    padding: 15px;
                }
                .section-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding-bottom: 10px;
                    border-bottom: 2px solid #f0f0f1;
                    margin-bottom: 15px;
                }
                .section-title {
                    font-size: 16px;
                    font-weight: 600;
                    color: #1d2327;
                    margin: 0;
                }
                .section-actions {
                    display: flex;
                    gap: 8px;
                }
                .materials-list {
                    list-style: none;
                    margin: 0;
                    padding: 0;
                }
                .material-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 10px;
                    background: #f9f9f9;
                    border-left: 3px solid #2271b1;
                    margin-bottom: 8px;
                    border-radius: 2px;
                }
                .material-item:hover {
                    background: #f0f0f1;
                }
                .material-info {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    flex: 1;
                }
                .material-icon {
                    font-size: 18px;
                }
                .material-title {
                    font-weight: 500;
                    color: #2271b1;
                }
                .material-meta {
                    font-size: 12px;
                    color: #646970;
                }
                .add-section-box,
                .add-lesson-box {
                    background: #f9f9f9;
                    border: 2px dashed #c3c4c7;
                    border-radius: 4px;
                    padding: 20px;
                    margin: 20px 0;
                }
                .lesson-select-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                    gap: 10px;
                    margin-top: 15px;
                    max-height: 300px;
                    overflow-y: auto;
                    padding: 10px;
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                }
                .lesson-checkbox-label {
                    display: flex;
                    align-items: center;
                    padding: 8px;
                    background: #f9f9f9;
                    border-radius: 3px;
                    cursor: pointer;
                }
                .lesson-checkbox-label:hover {
                    background: #e9e9e9;
                }
                .empty-curriculum {
                    text-align: center;
                    padding: 40px;
                    color: #646970;
                }
            </style>

            <div class="curriculum-builder">
                <?php if ( empty( $curriculum ) ) : ?>
                    <div class="empty-curriculum">
                        <p>📝 El curriculum está vacío. Crea tu primera sección para comenzar.</p>
                    </div>
                <?php else : ?>
                    <?php 
                    $section_index = 0;
                    foreach ( $curriculum as $index => $item ) : 
                        if ( isset( $item['title'] ) ) :
                            $section_index++;
                            $is_section = ! isset( $item['type'] ) || '' === $item['type'];
                            $materials = isset( $item['materials'] ) && is_array( $item['materials'] ) ? $item['materials'] : [];
                    ?>
                            <?php if ( $is_section ) : ?>
                                <div class="curriculum-section">
                                    <div class="section-header">
                                        <h3 class="section-title">📂 <?php echo esc_html( $item['title'] ); ?></h3>
                                        <div class="section-actions">
                                            <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar esta sección y todo su contenido?');">
                                                <?php wp_nonce_field( 'fplms_courses_save', 'fplms_courses_nonce' ); ?>
                                                <input type="hidden" name="fplms_courses_action" value="delete_section">
                                                <input type="hidden" name="fplms_course_id" value="<?php echo esc_attr( $course_id ); ?>">
                                                <input type="hidden" name="section_index" value="<?php echo esc_attr( $index ); ?>">
                                                <button type="submit" class="button button-small">🗑️ Eliminar</button>
                                            </form>
                                        </div>
                                    </div>

                                    <?php if ( ! empty( $materials ) ) : ?>
                                        <ul class="materials-list">
                                            <?php foreach ( $materials as $mat_index => $material ) : ?>
                                                <?php 
                                                $mat_id = isset( $material['post_id'] ) ? (int) $material['post_id'] : 0;
                                                $mat_post = get_post( $mat_id );
                                                if ( $mat_post ) :
                                                    $mat_type = get_post_meta( $mat_id, 'type', true );
                                                    $mat_duration = get_post_meta( $mat_id, 'duration', true );
                                                    
                                                    $icon = '📝';
                                                    if ( 'video' === $mat_type ) $icon = '🎥';
                                                    elseif ( 'slide' === $mat_type ) $icon = '📊';
                                                    elseif ( 'stream' === $mat_type ) $icon = '📡';
                                                    elseif ( 'zoom' === $mat_type ) $icon = '💻';
                                                    elseif ( FairPlay_LMS_Config::MS_PT_QUIZ === $mat_post->post_type ) $icon = '❓';
                                                ?>
                                                    <li class="material-item">
                                                        <div class="material-info">
                                                            <span class="material-icon"><?php echo $icon; ?></span>
                                                            <div>
                                                                <div class="material-title"><?php echo esc_html( $mat_post->post_title ); ?></div>
                                                                <div class="material-meta">
                                                                    ID: <?php echo esc_html( $mat_id ); ?>
                                                                    <?php if ( $mat_duration ) : ?>
                                                                        | ⏱️ <?php echo esc_html( $mat_duration ); ?> min
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar esta lección de la sección?');">
                                                            <?php wp_nonce_field( 'fplms_courses_save', 'fplms_courses_nonce' ); ?>
                                                            <input type="hidden" name="fplms_courses_action" value="remove_material_from_section">
                                                            <input type="hidden" name="fplms_course_id" value="<?php echo esc_attr( $course_id ); ?>">
                                                            <input type="hidden" name="section_index" value="<?php echo esc_attr( $index ); ?>">
                                                            <input type="hidden" name="material_index" value="<?php echo esc_attr( $mat_index ); ?>">
                                                            <button type="submit" class="button button-small button-link-delete">❌</button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else : ?>
                                        <p style="color: #646970; font-style: italic;">Esta sección está vacía. Agrega lecciones abajo.</p>
                                    <?php endif; ?>

                                    <!-- Formulario para agregar lecciones a esta sección -->
                                    <div class="add-lesson-box" style="margin-top: 15px;">
                                        <form method="post">
                                            <?php wp_nonce_field( 'fplms_courses_save', 'fplms_courses_nonce' ); ?>
                                            <input type="hidden" name="fplms_courses_action" value="add_lessons_to_section">
                                            <input type="hidden" name="fplms_course_id" value="<?php echo esc_attr( $course_id ); ?>">
                                            <input type="hidden" name="section_index" value="<?php echo esc_attr( $index ); ?>">
                                            
                                            <h4>➕ Agregar Lecciones a esta Sección</h4>
                                            
                                            <?php if ( ! empty( $available_lessons ) ) : ?>
                                                <div class="lesson-select-grid">
                                                    <?php foreach ( $available_lessons as $lesson ) : ?>
                                                        <label class="lesson-checkbox-label">
                                                            <input type="checkbox" name="lesson_ids[]" value="<?php echo esc_attr( $lesson->ID ); ?>" style="margin-right: 8px;">
                                                            <span>📝 <?php echo esc_html( $lesson->post_title ); ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                                <p class="submit" style="margin-top: 15px;">
                                                    <button type="submit" class="button button-secondary">✅ Agregar Lecciones Seleccionadas</button>
                                                </p>
                                            <?php else : ?>
                                                <p style="color: #646970;">No hay lecciones disponibles. Todas las lecciones están asignadas o no hay lecciones creadas.</p>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </div>
                            <?php else : ?>
                                <!-- Lección suelta (sin sección) -->
                                <?php
                                $lesson_id = isset( $item['id'] ) ? (int) $item['id'] : 0;
                                $lesson = get_post( $lesson_id );
                                if ( $lesson ) :
                                ?>
                                    <div class="material-item" style="margin-bottom: 10px;">
                                        <div class="material-info">
                                            <span class="material-icon">📝</span>
                                            <div>
                                                <div class="material-title"><?php echo esc_html( $lesson->post_title ); ?></div>
                                                <div class="material-meta">ID: <?php echo esc_html( $lesson_id ); ?> | Sin sección</div>
                                            </div>
                                        </div>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar esta lección del curso?');">
                                            <?php wp_nonce_field( 'fplms_courses_save', 'fplms_courses_nonce' ); ?>
                                            <input type="hidden" name="fplms_courses_action" value="unassign_lesson">
                                            <input type="hidden" name="fplms_course_id" value="<?php echo esc_attr( $course_id ); ?>">
                                            <input type="hidden" name="lesson_id" value="<?php echo esc_attr( $lesson_id ); ?>">
                                            <button type="submit" class="button button-small button-link-delete">❌</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php 
                        endif;
                    endforeach; 
                    ?>
                <?php endif; ?>

                <!-- Crear nueva sección -->
                <div class="add-section-box">
                    <form method="post">
                        <?php wp_nonce_field( 'fplms_courses_save', 'fplms_courses_nonce' ); ?>
                        <input type="hidden" name="fplms_courses_action" value="create_section">
                        <input type="hidden" name="fplms_course_id" value="<?php echo esc_attr( $course_id ); ?>">
                        
                        <h3>➕ Crear Nueva Sección</h3>
                        <p class="description">Las secciones te permiten organizar las lecciones del curso en módulos o unidades temáticas.</p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="section_title">Título de la Sección *</label>
                                </th>
                                <td>
                                    <input 
                                        type="text" 
                                        name="section_title" 
                                        id="section_title" 
                                        class="regular-text" 
                                        placeholder="Ej: Módulo 1, Introducción, etc."
                                        required
                                    >
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" class="button button-primary">✅ Crear Sección</button>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    private function count_topics_for_module( int $module_id ): int {

        $topics = get_posts(
            [
                'post_type'   => FairPlay_LMS_Config::CPT_TOPIC,
                'meta_key'    => FairPlay_LMS_Config::META_TOPIC_MODULE,
                'meta_value'  => $module_id,
                'numberposts' => -1,
                'post_status' => 'publish',
                'fields'      => 'ids',
            ]
        );
        return is_array( $topics ) ? count( $topics ) : 0;
    }

    /**
     * Vista de temas/subtemas por módulo.
     */
    private function render_module_topics_view( int $course_id, int $module_id ): void {

        $course = get_post( $course_id );
        $module = get_post( $module_id );

        if (
            ! $course || FairPlay_LMS_Config::MS_PT_COURSE !== $course->post_type ||
            ! $module || FairPlay_LMS_Config::CPT_MODULE !== $module->post_type
        ) {
            echo '<p>Curso o módulo no válido.</p>';
            return;
        }

        $back_url = add_query_arg(
            [
                'page'      => 'fplms-courses',
                'view'      => 'modules',
                'course_id' => $course_id,
            ],
            admin_url( 'admin.php' )
        );

        echo '<p><a href="' . esc_url( $back_url ) . '">&larr; Volver a módulos del curso</a></p>';

        echo '<h2>Curso: ' . esc_html( get_the_title( $course ) ) . ' (ID ' . esc_html( $course->ID ) . ')</h2>';
        echo '<h3>Módulo: ' . esc_html( get_the_title( $module ) ) . ' (ID ' . esc_html( $module->ID ) . ')</h3>';

        $topics = get_posts(
            [
                'post_type'   => FairPlay_LMS_Config::CPT_TOPIC,
                'meta_key'    => FairPlay_LMS_Config::META_TOPIC_MODULE,
                'meta_value'  => $module_id,
                'numberposts' => -1,
                'post_status' => 'publish',
                'orderby'     => 'menu_order',
                'order'       => 'ASC',
            ]
        );

        $ms_resources = $this->get_masterstudy_resources();
        ?>
        <h4>Temas del módulo</h4>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Tema / Subtema</th>
                    <th>ID</th>
                    <th>Resumen</th>
                    <th>Recurso MasterStudy</th>
                </tr>
            </thead>
            <tbody>
            <?php if ( ! empty( $topics ) ) : ?>
                <?php foreach ( $topics as $topic ) : ?>
                    <?php
                    $res_id    = (int) get_post_meta( $topic->ID, FairPlay_LMS_Config::META_TOPIC_RESOURCE_ID, true );
                    $res_label = '';
                    if ( $res_id ) {
                        $res_post = get_post( $res_id );
                        if ( $res_post ) {
                            $res_label = sprintf(
                                '%s (ID %d, tipo %s)',
                                $res_post->post_title,
                                $res_post->ID,
                                $res_post->post_type
                            );
                        }
                    }
                    ?>
                    <tr>
                        <td><?php echo esc_html( get_the_title( $topic ) ); ?></td>
                        <td><?php echo esc_html( $topic->ID ); ?></td>
                        <td><?php echo wp_kses_post( wp_trim_words( $topic->post_content, 25 ) ); ?></td>
                        <td><?php echo esc_html( $res_label ?: '—' ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="4">No hay temas creados todavía para este módulo.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>

        <h4 style="margin-top:2em;">Crear nuevo tema / subtema</h4>
        <form method="post">
            <?php wp_nonce_field( 'fplms_courses_save', 'fplms_courses_nonce' ); ?>
            <input type="hidden" name="fplms_courses_action" value="create_topic">
            <input type="hidden" name="fplms_course_id" value="<?php echo esc_attr( $course_id ); ?>">
            <input type="hidden" name="fplms_module_id" value="<?php echo esc_attr( $module_id ); ?>">

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="fplms_topic_title">Título del tema</label></th>
                    <td>
                        <input name="fplms_topic_title" id="fplms_topic_title" type="text" class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="fplms_topic_summary">Resumen / descripción</label></th>
                    <td>
                        <textarea name="fplms_topic_summary" id="fplms_topic_summary" class="large-text" rows="3"></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="fplms_topic_resource_type">Tipo de recurso</label></th>
                    <td>
                        <select name="fplms_topic_resource_type" id="fplms_topic_resource_type">
                            <option value="">— Sin recurso —</option>
                            <option value="lesson">Lección</option>
                            <option value="quiz">Quiz</option>
                            <option value="other">Otro</option>
                        </select>
                        <p class="description">
                            Tipo lógico para tu organización. El recurso real se selecciona debajo.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="fplms_topic_resource">Recurso MasterStudy</label></th>
                    <td>
                        <select name="fplms_topic_resource" id="fplms_topic_resource">
                            <option value="">— Sin asignar —</option>
                            <?php foreach ( $ms_resources as $res ) : ?>
                                <option value="<?php echo esc_attr( $res['ID'] ); ?>">
                                    <?php echo esc_html( $res['label'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            Se listan lecciones y quizzes existentes de MasterStudy (no filtrados por curso).
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">Guardar tema</button>
            </p>
        </form>
        <?php
    }

    /**
     * Vista para gestionar estructuras de un curso.
     */
    private function render_course_structures_view( int $course_id ): void {

        $course = get_post( $course_id );
        if ( ! $course || FairPlay_LMS_Config::MS_PT_COURSE !== $course->post_type ) {
            echo '<p>Curso no válido.</p>';
            return;
        }

        $back_url = add_query_arg( [ 'page' => 'fplms-courses' ], admin_url( 'admin.php' ) );

        echo '<p><a href="' . esc_url( $back_url ) . '">&larr; Volver al listado de cursos</a></p>';
        echo '<h2>Estructuras para: ' . esc_html( get_the_title( $course ) ) . ' (ID ' . esc_html( $course->ID ) . ')</h2>';

        // Obtener estructuras actuales del curso
        $current_structures = $this->get_course_structures( $course_id );

        // Obtener términos activos
        $cities    = $this->structures ? $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CITY ) : [];
        $companies = $this->structures ? $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_COMPANY ) : [];
        $channels  = $this->structures ? $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CHANNEL ) : [];
        $branches  = $this->structures ? $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_BRANCH ) : [];
        $roles     = $this->structures ? $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_ROLE ) : [];
        ?>
        <div class="notice notice-info" style="padding: 15px; margin: 20px 0;">
            <h3 style="margin-top: 0;">ℹ️ Lógica de asignación en cascada</h3>
            <p><strong>Al asignar un curso a una estructura, automáticamente se asigna a todas las estructuras descendientes:</strong></p>
            <ul style="margin-left: 20px;">
                <li>📍 <strong>Ciudad</strong> → Se asigna a todas las empresas, canales, sucursales y cargos de esa ciudad</li>
                <li>🏢 <strong>Empresa</strong> → Se asigna a todos los canales, sucursales y cargos de esa empresa</li>
                <li>🏪 <strong>Canal</strong> → Se asigna a todas las sucursales y cargos de ese canal</li>
                <li>🏢 <strong>Sucursal</strong> → Se asigna a todos los cargos de esa sucursal</li>
                <li>👔 <strong>Cargo</strong> → Se asigna específicamente a ese cargo</li>
            </ul>
            <p><em>Los usuarios asignados a estas estructuras recibirán una notificación por correo electrónico.</em></p>
        </div>

        <form method="post">
            <?php wp_nonce_field( 'fplms_courses_save', 'fplms_courses_nonce' ); ?>
            <input type="hidden" name="fplms_courses_action" value="assign_structures">
            <input type="hidden" name="fplms_course_id" value="<?php echo esc_attr( $course_id ); ?>">

            <table class="form-table">
                <tr>
                    <th scope="row"><label>📍 Ciudades</label></th>
                    <td>
                        <?php if ( ! empty( $cities ) ) : ?>
                            <fieldset>
                                <?php foreach ( $cities as $term_id => $term_name ) : ?>
                                    <label style="display: block; margin: 5px 0;">
                                        <input type="checkbox" 
                                               name="fplms_course_cities[]" 
                                               value="<?php echo esc_attr( $term_id ); ?>" 
                                               <?php checked( in_array( $term_id, $current_structures['cities'], true ) ); ?>>
                                        <?php echo esc_html( $term_name ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php else : ?>
                            <p><em>No hay ciudades disponibles.</em></p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label>🏢 Empresas</label></th>
                    <td>
                        <?php if ( ! empty( $companies ) ) : ?>
                            <fieldset>
                                <?php foreach ( $companies as $term_id => $term_name ) : ?>
                                    <label style="display: block; margin: 5px 0;">
                                        <input type="checkbox" 
                                               name="fplms_course_companies[]" 
                                               value="<?php echo esc_attr( $term_id ); ?>" 
                                               <?php checked( in_array( $term_id, $current_structures['companies'], true ) ); ?>>
                                        <?php echo esc_html( $term_name ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php else : ?>
                            <p><em>No hay empresas disponibles.</em></p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label>🏪 Canales / Franquicias</label></th>
                    <td>
                        <?php if ( ! empty( $channels ) ) : ?>
                            <fieldset>
                                <?php foreach ( $channels as $term_id => $term_name ) : ?>
                                    <label style="display: block; margin: 5px 0;">
                                        <input type="checkbox" 
                                               name="fplms_course_channels[]" 
                                               value="<?php echo esc_attr( $term_id ); ?>" 
                                               <?php checked( in_array( $term_id, $current_structures['channels'], true ) ); ?>>
                                        <?php echo esc_html( $term_name ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php else : ?>
                            <p><em>No hay canales disponibles.</em></p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label>🏢 Sucursales</label></th>
                    <td>
                        <?php if ( ! empty( $branches ) ) : ?>
                            <fieldset>
                                <?php foreach ( $branches as $term_id => $term_name ) : ?>
                                    <label style="display: block; margin: 5px 0;">
                                        <input type="checkbox" 
                                               name="fplms_course_branches[]" 
                                               value="<?php echo esc_attr( $term_id ); ?>" 
                                               <?php checked( in_array( $term_id, $current_structures['branches'], true ) ); ?>>
                                        <?php echo esc_html( $term_name ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php else : ?>
                            <p><em>No hay sucursales disponibles.</em></p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label>👔 Cargos</label></th>
                    <td>
                        <?php if ( ! empty( $roles ) ) : ?>
                            <fieldset>
                                <?php foreach ( $roles as $term_id => $term_name ) : ?>
                                    <label style="display: block; margin: 5px 0;">
                                        <input type="checkbox" 
                                               name="fplms_course_roles[]" 
                                               value="<?php echo esc_attr( $term_id ); ?>" 
                                               <?php checked( in_array( $term_id, $current_structures['roles'], true ) ); ?>>
                                        <?php echo esc_html( $term_name ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php else : ?>
                            <p><em>No hay cargos disponibles.</em></p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">💾 Guardar estructuras y notificar usuarios</button>
            </p>
        </form>
        <?php
    }

    /**
     * Recurso MasterStudy (lecciones y quizzes).
     */
    private function get_masterstudy_resources(): array {

        $resources = [];

        $post_types = [
            FairPlay_LMS_Config::MS_PT_LESSON,
            FairPlay_LMS_Config::MS_PT_QUIZ,
        ];

        $posts = get_posts(
            [
                'post_type'   => $post_types,
                'numberposts' => 200,
                'post_status' => 'publish',
            ]
        );

        if ( ! empty( $posts ) ) {
            foreach ( $posts as $post ) {
                $resources[] = [
                    'ID'    => $post->ID,
                    'label' => sprintf(
                        '[%s] %s (ID %d)',
                        $post->post_type,
                        $post->post_title,
                        $post->ID
                    ),
                ];
            }
        }

        return $resources;
    }

    /**
     * Guarda las estructuras asignadas a un curso con cascada jerárquica.
     */
    private function save_course_structures( int $course_id ): void {
        // Obtener estructuras enviadas desde el formulario
        $cities    = isset( $_POST['fplms_course_cities'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['fplms_course_cities'] ) ) : [];
        $companies = isset( $_POST['fplms_course_companies'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['fplms_course_companies'] ) ) : [];
        $channels  = isset( $_POST['fplms_course_channels'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['fplms_course_channels'] ) ) : [];
        $branches  = isset( $_POST['fplms_course_branches'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['fplms_course_branches'] ) ) : [];
        $roles     = isset( $_POST['fplms_course_roles'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['fplms_course_roles'] ) ) : [];

        // Aplicar lógica de cascada: si se selecciona un nivel superior, automáticamente se incluyen todos los inferiores
        $cascaded_structures = $this->apply_cascade_logic( $cities, $companies, $channels, $branches, $roles );

        // Guardar estructuras
        update_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_CITIES, $cascaded_structures['cities'] );
        update_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_COMPANIES, $cascaded_structures['companies'] );
        update_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_CHANNELS, $cascaded_structures['channels'] );
        update_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_BRANCHES, $cascaded_structures['branches'] );
        update_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_ROLES, $cascaded_structures['roles'] );

        // Enviar notificaciones por correo a los usuarios afectados
        $this->send_course_assignment_notifications( $course_id, $cascaded_structures );
    }

    /**
     * Obtiene las estructuras asignadas a un curso.
     */
    public function get_course_structures( int $course_id ): array {
        return [
            'cities'    => (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_CITIES, true ),
            'companies' => (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_COMPANIES, true ),
            'channels'  => (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_CHANNELS, true ),
            'branches'  => (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_BRANCHES, true ),
            'roles'     => (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_ROLES, true ),
        ];
    }

    /**
     * Formatea las estructuras de un curso para mostrarlas de manera compacta con tags.
     * 
     * @param array $structures Array con estructura: ['cities' => [ids], 'companies' => [ids], 'channels' => [ids], 'branches' => [ids], 'roles' => [ids]].
     * @return string HTML formateado para mostrar.
     */
    private function format_course_structures_compact( array $structures ): string {
        $tags = [];

        // Ciudades
        if ( ! empty( $structures['cities'] ) ) {
            $city_names = $this->get_term_names_by_ids( $structures['cities'] );
            foreach ( $city_names as $name ) {
                $tags[] = '<span class="fplms-structure-tag">📍 ' . esc_html( $name ) . '</span>';
            }
        }

        // Empresas
        if ( ! empty( $structures['companies'] ) ) {
            $company_names = $this->get_term_names_by_ids( $structures['companies'] );
            foreach ( $company_names as $name ) {
                $tags[] = '<span class="fplms-structure-tag">🏢 ' . esc_html( $name ) . '</span>';
            }
        }

        // Canales
        if ( ! empty( $structures['channels'] ) ) {
            $channel_names = $this->get_term_names_by_ids( $structures['channels'] );
            foreach ( $channel_names as $name ) {
                $tags[] = '<span class="fplms-structure-tag">🏪 ' . esc_html( $name ) . '</span>';
            }
        }

        // Sucursales
        if ( ! empty( $structures['branches'] ) ) {
            $branch_names = $this->get_term_names_by_ids( $structures['branches'] );
            foreach ( $branch_names as $name ) {
                $tags[] = '<span class="fplms-structure-tag">🏢 ' . esc_html( $name ) . '</span>';
            }
        }

        // Cargos
        if ( ! empty( $structures['roles'] ) ) {
            $role_names = $this->get_term_names_by_ids( $structures['roles'] );
            foreach ( $role_names as $name ) {
                $tags[] = '<span class="fplms-structure-tag">👔 ' . esc_html( $name ) . '</span>';
            }
        }

        if ( empty( $tags ) ) {
            return '<span class="fplms-structure-tag" style="background: #e7f5fe; color: #2271b1;">🌐 Sin restricción</span>';
        }

        // Limitar a 3 tags + "y X más" si hay muchos
        if ( count( $tags ) > 3 ) {
            $remaining = count( $tags ) - 3;
            $tags = array_slice( $tags, 0, 3 );
            $tags[] = '<span class="fplms-structure-tag" style="background: #f0f0f1;">+' . $remaining . ' más</span>';
        }

        return implode( '', $tags );
    }

    /**
     * Formatea las estructuras de un curso para mostrar en la tabla.
     * 
     * @param array $structures Array con estructura: ['cities' => [ids], 'companies' => [ids], 'channels' => [ids], 'branches' => [ids], 'roles' => [ids]].
     * @return string HTML formateado para mostrar.
     */
    private function format_course_structures_display( array $structures ): string {
        $display = [];

        // Ciudades
        if ( ! empty( $structures['cities'] ) ) {
            $city_names = $this->get_term_names_by_ids( $structures['cities'] );
            if ( ! empty( $city_names ) ) {
                $display[] = '<strong>📍 Ciudades:</strong> ' . esc_html( implode( ', ', $city_names ) );
            }
        }

        // Empresas
        if ( ! empty( $structures['companies'] ) ) {
            $company_names = $this->get_term_names_by_ids( $structures['companies'] );
            if ( ! empty( $company_names ) ) {
                $display[] = '<strong>🏢 Empresas:</strong> ' . esc_html( implode( ', ', $company_names ) );
            }
        }

        // Canales
        if ( ! empty( $structures['channels'] ) ) {
            $channel_names = $this->get_term_names_by_ids( $structures['channels'] );
            if ( ! empty( $channel_names ) ) {
                $display[] = '<strong>🏪 Canales:</strong> ' . esc_html( implode( ', ', $channel_names ) );
            }
        }

        // Sucursales
        if ( ! empty( $structures['branches'] ) ) {
            $branch_names = $this->get_term_names_by_ids( $structures['branches'] );
            if ( ! empty( $branch_names ) ) {
                $display[] = '<strong>🏢 Sucursales:</strong> ' . esc_html( implode( ', ', $branch_names ) );
            }
        }

        // Cargos
        if ( ! empty( $structures['roles'] ) ) {
            $role_names = $this->get_term_names_by_ids( $structures['roles'] );
            if ( ! empty( $role_names ) ) {
                $display[] = '<strong>👔 Cargos:</strong> ' . esc_html( implode( ', ', $role_names ) );
            }
        }

        if ( empty( $display ) ) {
            return '<em style="color: #666;">Sin restricción (visible para todos)</em>';
        }

        return implode( '<br>', $display );
    }

    /**
     * Obtiene los nombres de términos por sus IDs.
     * 
     * @param array $term_ids Array de IDs de términos.
     * @return array Array de nombres de términos.
     */
    private function get_term_names_by_ids( array $term_ids ): array {
        $names = [];
        foreach ( $term_ids as $term_id ) {
            $term = get_term( (int) $term_id );
            if ( $term && ! is_wp_error( $term ) ) {
                $names[] = $term->name;
            }
        }
        return $names;
    }

    /**
     * Aplica la lógica de cascada jerárquica.
     * Si se selecciona un nivel superior, se incluyen automáticamente todos sus descendientes.
     * 
     * @param array $cities IDs de ciudades seleccionadas
     * @param array $companies IDs de empresas seleccionadas
     * @param array $channels IDs de canales seleccionados
     * @param array $branches IDs de sucursales seleccionadas
     * @param array $roles IDs de cargos seleccionados
     * @return array Estructuras con cascada aplicada
     */
    private function apply_cascade_logic( array $cities, array $companies, array $channels, array $branches, array $roles ): array {
        $result = [
            'cities'    => $cities,
            'companies' => $companies,
            'channels'  => $channels,
            'branches'  => $branches,
            'roles'     => $roles,
        ];

        // Si hay ciudades seleccionadas, incluir todas las empresas de esas ciudades
        if ( ! empty( $cities ) ) {
            foreach ( $cities as $city_id ) {
                $city_companies = $this->get_companies_by_city( $city_id );
                $result['companies'] = array_unique( array_merge( $result['companies'], $city_companies ) );
            }
        }

        // Si hay empresas seleccionadas, incluir todos los canales de esas empresas
        if ( ! empty( $result['companies'] ) ) {
            foreach ( $result['companies'] as $company_id ) {
                $company_channels = $this->get_channels_by_company( $company_id );
                $result['channels'] = array_unique( array_merge( $result['channels'], $company_channels ) );
            }
        }

        // Si hay canales seleccionados, incluir todas las sucursales de esos canales
        if ( ! empty( $result['channels'] ) ) {
            foreach ( $result['channels'] as $channel_id ) {
                $channel_branches = $this->get_branches_by_channel( $channel_id );
                $result['branches'] = array_unique( array_merge( $result['branches'], $channel_branches ) );
            }
        }

        // Si hay sucursales seleccionadas, incluir todos los cargos de esas sucursales
        if ( ! empty( $result['branches'] ) ) {
            foreach ( $result['branches'] as $branch_id ) {
                $branch_roles = $this->get_roles_by_branch( $branch_id );
                $result['roles'] = array_unique( array_merge( $result['roles'], $branch_roles ) );
            }
        }

        return $result;
    }

    /**
     * Obtiene todas las empresas asociadas a una ciudad.
     * 
     * @param int $city_id ID de la ciudad
     * @return array Array de IDs de empresas
     */
    private function get_companies_by_city( int $city_id ): array {
        $companies = get_terms( [
            'taxonomy'   => FairPlay_LMS_Config::TAX_COMPANY,
            'hide_empty' => false,
        ] );

        $result = [];
        foreach ( $companies as $company ) {
            $company_cities = get_term_meta( $company->term_id, FairPlay_LMS_Config::META_COMPANY_CITIES, true );
            if ( is_array( $company_cities ) && in_array( $city_id, $company_cities, true ) ) {
                $result[] = $company->term_id;
            }
        }

        return $result;
    }

    /**
     * Obtiene todos los canales asociados a una empresa.
     * 
     * @param int $company_id ID de la empresa
     * @return array Array de IDs de canales
     */
    private function get_channels_by_company( int $company_id ): array {
        $channels = get_terms( [
            'taxonomy'   => FairPlay_LMS_Config::TAX_CHANNEL,
            'hide_empty' => false,
        ] );

        $result = [];
        foreach ( $channels as $channel ) {
            $channel_companies = get_term_meta( $channel->term_id, FairPlay_LMS_Config::META_CHANNEL_COMPANIES, true );
            if ( is_array( $channel_companies ) && in_array( $company_id, $channel_companies, true ) ) {
                $result[] = $channel->term_id;
            }
        }

        return $result;
    }

    /**
     * Obtiene todas las sucursales asociadas a un canal.
     * 
     * @param int $channel_id ID del canal
     * @return array Array de IDs de sucursales
     */
    private function get_branches_by_channel( int $channel_id ): array {
        $branches = get_terms( [
            'taxonomy'   => FairPlay_LMS_Config::TAX_BRANCH,
            'hide_empty' => false,
        ] );

        $result = [];
        foreach ( $branches as $branch ) {
            $branch_channels = get_term_meta( $branch->term_id, FairPlay_LMS_Config::META_BRANCH_CHANNELS, true );
            if ( is_array( $branch_channels ) && in_array( $channel_id, $branch_channels, true ) ) {
                $result[] = $branch->term_id;
            }
        }

        return $result;
    }

    /**
     * Obtiene todos los cargos asociados a una sucursal.
     * 
     * @param int $branch_id ID de la sucursal
     * @return array Array de IDs de cargos
     */
    private function get_roles_by_branch( int $branch_id ): array {
        $roles = get_terms( [
            'taxonomy'   => FairPlay_LMS_Config::TAX_ROLE,
            'hide_empty' => false,
        ] );

        $result = [];
        foreach ( $roles as $role ) {
            $role_branches = get_term_meta( $role->term_id, FairPlay_LMS_Config::META_ROLE_BRANCHES, true );
            if ( is_array( $role_branches ) && in_array( $branch_id, $role_branches, true ) ) {
                $result[] = $role->term_id;
            }
        }

        return $result;
    }

    /**
     * Envía notificaciones por correo a los usuarios afectados cuando se asigna un curso.
     * 
     * @param int   $course_id ID del curso
     * @param array $structures Estructuras asignadas al curso
     */
    private function send_course_assignment_notifications( int $course_id, array $structures ): void {
        // Obtener información del curso
        $course = get_post( $course_id );
        if ( ! $course ) {
            return;
        }

        $course_title = get_the_title( $course );
        $course_url   = get_permalink( $course_id );

        // Obtener todos los usuarios que coinciden con las estructuras asignadas
        $affected_users = $this->get_users_by_structures( $structures );

        // Enviar correo a cada usuario
        foreach ( $affected_users as $user_id ) {
            $user = get_user_by( 'id', $user_id );
            if ( ! $user ) {
                continue;
            }

            $subject = sprintf( 'Nuevo curso asignado: %s', $course_title );
            
            $message = sprintf(
                "Hola %s,\n\n" .
                "Se te ha asignado un nuevo curso:\n\n" .
                "📚 Curso: %s\n" .
                "🔗 Acceder al curso: %s\n\n" .
                "¡Esperamos que disfrutes este contenido educativo!\n\n" .
                "Saludos,\n" .
                "Equipo de FairPlay LMS",
                $user->display_name,
                $course_title,
                $course_url
            );

            wp_mail( $user->user_email, $subject, $message );
        }
    }

    /**
     * Obtiene los IDs de usuarios que pertenecen a las estructuras especificadas.
     * 
     * @param array $structures Estructuras asignadas
     * @return array Array de IDs de usuarios
     */
    private function get_users_by_structures( array $structures ): array {
        $user_ids = [];

        // Construir meta_query para buscar usuarios
        $meta_query = [ 'relation' => 'OR' ];

        if ( ! empty( $structures['cities'] ) ) {
            foreach ( $structures['cities'] as $city_id ) {
                $meta_query[] = [
                    'key'     => FairPlay_LMS_Config::USER_META_CITY,
                    'value'   => $city_id,
                    'compare' => '=',
                ];
            }
        }

        if ( ! empty( $structures['companies'] ) ) {
            foreach ( $structures['companies'] as $company_id ) {
                $meta_query[] = [
                    'key'     => FairPlay_LMS_Config::USER_META_COMPANY,
                    'value'   => $company_id,
                    'compare' => '=',
                ];
            }
        }

        if ( ! empty( $structures['channels'] ) ) {
            foreach ( $structures['channels'] as $channel_id ) {
                $meta_query[] = [
                    'key'     => FairPlay_LMS_Config::USER_META_CHANNEL,
                    'value'   => $channel_id,
                    'compare' => '=',
                ];
            }
        }

        if ( ! empty( $structures['branches'] ) ) {
            foreach ( $structures['branches'] as $branch_id ) {
                $meta_query[] = [
                    'key'     => FairPlay_LMS_Config::USER_META_BRANCH,
                    'value'   => $branch_id,
                    'compare' => '=',
                ];
            }
        }

        if ( ! empty( $structures['roles'] ) ) {
            foreach ( $structures['roles'] as $role_id ) {
                $meta_query[] = [
                    'key'     => FairPlay_LMS_Config::USER_META_ROLE,
                    'value'   => $role_id,
                    'compare' => '=',
                ];
            }
        }

        // Si no hay estructuras, no enviar notificaciones
        if ( count( $meta_query ) === 1 ) {
            return [];
        }

        // Buscar usuarios
        $user_query = new WP_User_Query( [
            'meta_query' => $meta_query,
            'fields'     => 'ID',
        ] );

        return $user_query->get_results();
    }

    /**
     * Vista para gestionar lecciones MasterStudy de un curso.
     */
    private function render_course_lessons_view( int $course_id ): void {
        $course = get_post( $course_id );
        if ( ! $course || FairPlay_LMS_Config::MS_PT_COURSE !== $course->post_type ) {
            echo '<p>Curso no válido.</p>';
            return;
        }

        $back_url = add_query_arg( [ 'page' => 'fplms-courses' ], admin_url( 'admin.php' ) );

        echo '<p><a href="' . esc_url( $back_url ) . '">&larr; Volver al listado de cursos</a></p>';
        echo '<h2>Gestión de Lecciones: ' . esc_html( get_the_title( $course ) ) . ' (ID ' . esc_html( $course->ID ) . ')</h2>';

        // Obtener lecciones asignadas al curso
        $assigned_lessons = $this->get_course_lessons( $course_id );
        
        // Obtener todas las lecciones disponibles
        $all_lessons = $this->get_all_lessons();
        ?>

        <div class="notice notice-info" style="padding: 15px; margin: 20px 0;">
            <h3 style="margin-top: 0;">ℹ️ Información sobre Lecciones</h3>
            <p><strong>Las lecciones son el contenido principal de MasterStudy LMS.</strong></p>
            <p>Desde aquí puedes:</p>
            <ul style="margin-left: 20px;">
                <li>✅ Crear nuevas lecciones para este curso</li>
                <li>✅ Asignar lecciones existentes al curso</li>
                <li>✅ Ver y gestionar todas las lecciones del curso</li>
                <li>✅ Eliminar lecciones del curso o completamente</li>
            </ul>
            <p><em>Las lecciones se integran automáticamente con el curriculum de MasterStudy.</em></p>
        </div>

        <!-- Lecciones Asignadas -->
        <h3>📚 Lecciones asignadas a este curso (<?php echo count( $assigned_lessons ); ?>)</h3>
        <?php if ( ! empty( $assigned_lessons ) ) : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">Orden</th>
                        <th>Título de la Lección</th>
                        <th>ID</th>
                        <th>Tipo</th>
                        <th>Duración</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $assigned_lessons as $index => $lesson ) : ?>
                        <?php
                        $lesson_type = get_post_meta( $lesson->ID, 'type', true ) ?: 'text';
                        $duration = get_post_meta( $lesson->ID, 'duration', true ) ?: '—';
                        $edit_url = get_edit_post_link( $lesson->ID );
                        ?>
                        <tr>
                            <td><?php echo esc_html( $index + 1 ); ?></td>
                            <td>
                                <strong><?php echo esc_html( get_the_title( $lesson ) ); ?></strong>
                                <?php if ( $lesson->post_content ) : ?>
                                    <br><span style="color: #666; font-size: 0.9em;">
                                        <?php echo esc_html( wp_trim_words( $lesson->post_content, 15 ) ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $lesson->ID ); ?></td>
                            <td>
                                <?php
                                $type_labels = [
                                    'text'     => '📝 Texto',
                                    'video'    => '🎥 Video',
                                    'slide'    => '📊 Presentación',
                                    'stream'   => '📡 Stream',
                                    'zoom'     => '💻 Zoom',
                                ];
                                echo esc_html( $type_labels[ $lesson_type ] ?? '📄 ' . ucfirst( $lesson_type ) );
                                ?>
                            </td>
                            <td><?php echo esc_html( $duration ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small" target="_blank">
                                    ✏️ Editar
                                </a>
                                <form method="post" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar esta lección del curso?');">
                                    <?php wp_nonce_field( 'fplms_courses_save', 'fplms_courses_nonce' ); ?>
                                    <input type="hidden" name="fplms_courses_action" value="unassign_lesson">
                                    <input type="hidden" name="fplms_course_id" value="<?php echo esc_attr( $course_id ); ?>">
                                    <input type="hidden" name="fplms_lesson_id" value="<?php echo esc_attr( $lesson->ID ); ?>">
                                    <button type="submit" class="button button-small">
                                        ❌ Desasignar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p style="padding: 20px; background: #f0f0f1; border-left: 4px solid #ddd;">
                📭 <em>No hay lecciones asignadas todavía a este curso.</em>
            </p>
        <?php endif; ?>

        <!-- Crear Nueva Lección -->
        <h3 style="margin-top: 3em;">➕ Crear Nueva Lección</h3>
        <form method="post" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
            <?php wp_nonce_field( 'fplms_courses_save', 'fplms_courses_nonce' ); ?>
            <input type="hidden" name="fplms_courses_action" value="create_lesson">
            <input type="hidden" name="fplms_course_id" value="<?php echo esc_attr( $course_id ); ?>">

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="fplms_lesson_title">Título de la Lección *</label>
                    </th>
                    <td>
                        <input name="fplms_lesson_title" 
                               id="fplms_lesson_title" 
                               type="text" 
                               class="regular-text" 
                               required 
                               placeholder="Ej: Introducción a HTML">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fplms_lesson_content">Contenido</label>
                    </th>
                    <td>
                        <?php
                        wp_editor( '', 'fplms_lesson_content', [
                            'textarea_name' => 'fplms_lesson_content',
                            'textarea_rows' => 10,
                            'media_buttons' => true,
                            'teeny'         => false,
                        ]);
                        ?>
                        <p class="description">Contenido principal de la lección. Puedes usar el editor visual.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fplms_lesson_type">Tipo de Lección</label>
                    </th>
                    <td>
                        <select name="fplms_lesson_type" id="fplms_lesson_type">
                            <option value="text">📝 Texto</option>
                            <option value="video">🎥 Video</option>
                            <option value="slide">📊 Presentación</option>
                            <option value="stream">📡 Stream</option>
                            <option value="zoom">💻 Zoom</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fplms_lesson_duration">Duración</label>
                    </th>
                    <td>
                        <input name="fplms_lesson_duration" 
                               id="fplms_lesson_duration" 
                               type="text" 
                               class="small-text" 
                               placeholder="30">
                        <span class="description">minutos (opcional)</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fplms_lesson_preview">¿Lección de Vista Previa?</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="fplms_lesson_preview" id="fplms_lesson_preview" value="1">
                            Permitir que los usuarios vean esta lección sin estar inscritos
                        </label>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary button-large">
                    ➕ Crear Lección y Asignar al Curso
                </button>
            </p>
        </form>

        <!-- Asignar Lecciones Existentes -->
        <?php
        $unassigned_lessons = array_filter( $all_lessons, function( $lesson ) use ( $assigned_lessons ) {
            $assigned_ids = array_map( function( $l ) { return $l->ID; }, $assigned_lessons );
            return ! in_array( $lesson->ID, $assigned_ids, true );
        });
        ?>

        <?php if ( ! empty( $unassigned_lessons ) ) : ?>
            <h3 style="margin-top: 3em;">🔗 Asignar Lecciones Existentes</h3>
            <p>Selecciona las lecciones que quieres asignar a este curso:</p>
            <form method="post" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                <?php wp_nonce_field( 'fplms_courses_save', 'fplms_courses_nonce' ); ?>
                <input type="hidden" name="fplms_courses_action" value="assign_lessons">
                <input type="hidden" name="fplms_course_id" value="<?php echo esc_attr( $course_id ); ?>">

                <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; background: #fafafa;">
                    <?php foreach ( $unassigned_lessons as $lesson ) : ?>
                        <?php
                        $lesson_type = get_post_meta( $lesson->ID, 'type', true ) ?: 'text';
                        $type_labels = [
                            'text'  => '📝',
                            'video' => '🎥',
                            'slide' => '📊',
                            'stream' => '📡',
                            'zoom' => '💻',
                        ];
                        ?>
                        <label style="display: block; padding: 10px; margin: 5px 0; background: #fff; border: 1px solid #ddd; border-radius: 3px; cursor: pointer;">
                            <input type="checkbox" name="fplms_lesson_ids[]" value="<?php echo esc_attr( $lesson->ID ); ?>">
                            <strong><?php echo esc_html( $type_labels[ $lesson_type ] ?? '📄' ); ?> <?php echo esc_html( get_the_title( $lesson ) ); ?></strong>
                            <span style="color: #666; font-size: 0.9em;">(ID: <?php echo esc_html( $lesson->ID ); ?>)</span>
                            <?php if ( $lesson->post_content ) : ?>
                                <br><span style="color: #999; font-size: 0.85em;">
                                    <?php echo esc_html( wp_trim_words( strip_tags( $lesson->post_content ), 20 ) ); ?>
                                </span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        🔗 Asignar Lecciones Seleccionadas
                    </button>
                </p>
            </form>
        <?php else : ?>
            <p style="margin-top: 2em; padding: 15px; background: #f0f0f1; border-left: 4px solid #ddd;">
                ℹ️ <em>Todas las lecciones disponibles ya están asignadas a este curso.</em>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Maneja la creación de una nueva lección MasterStudy.
     */
    private function handle_create_lesson( int $course_id ): void {
        $title    = sanitize_text_field( wp_unslash( $_POST['fplms_lesson_title'] ?? '' ) );
        $content  = wp_kses_post( wp_unslash( $_POST['fplms_lesson_content'] ?? '' ) );
        $type     = sanitize_text_field( wp_unslash( $_POST['fplms_lesson_type'] ?? 'text' ) );
        $duration = sanitize_text_field( wp_unslash( $_POST['fplms_lesson_duration'] ?? '' ) );
        $preview  = ! empty( $_POST['fplms_lesson_preview'] ) ? '1' : '';

        if ( ! $title ) {
            wp_safe_redirect(
                add_query_arg(
                    [
                        'page'      => 'fplms-courses',
                        'view'      => 'lessons',
                        'course_id' => $course_id,
                        'error'     => 'title_required',
                    ],
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }

        // Crear la lección como post de MasterStudy
        $lesson_id = wp_insert_post( [
            'post_type'    => FairPlay_LMS_Config::MS_PT_LESSON,
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
        ] );

        if ( $lesson_id && ! is_wp_error( $lesson_id ) ) {
            // Guardar metadatos de la lección
            if ( $type ) {
                update_post_meta( $lesson_id, 'type', $type );
            }
            if ( $duration ) {
                update_post_meta( $lesson_id, 'duration', $duration );
            }
            if ( $preview ) {
                update_post_meta( $lesson_id, 'preview', $preview );
            }

            // Asignar la lección al curso
            $this->assign_lesson_to_course( $course_id, $lesson_id );
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'      => 'fplms-courses',
                    'view'      => 'lessons',
                    'course_id' => $course_id,
                    'created'   => $lesson_id,
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    /**
     * Maneja la asignación de lecciones existentes a un curso.
     */
    private function handle_assign_lessons( int $course_id ): void {
        $lesson_ids = isset( $_POST['fplms_lesson_ids'] ) ? array_map( 'absint', (array) $_POST['fplms_lesson_ids'] ) : [];

        if ( empty( $lesson_ids ) ) {
            wp_safe_redirect(
                add_query_arg(
                    [
                        'page'      => 'fplms-courses',
                        'view'      => 'lessons',
                        'course_id' => $course_id,
                        'error'     => 'no_lessons_selected',
                    ],
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }

        foreach ( $lesson_ids as $lesson_id ) {
            $this->assign_lesson_to_course( $course_id, $lesson_id );
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'      => 'fplms-courses',
                    'view'      => 'lessons',
                    'course_id' => $course_id,
                    'assigned'  => count( $lesson_ids ),
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    /**
     * Asigna una lección a un curso actualizando el curriculum de MasterStudy.
     */
    private function assign_lesson_to_course( int $course_id, int $lesson_id ): void {
        // Obtener curriculum actual del curso
        $curriculum = get_post_meta( $course_id, FairPlay_LMS_Config::MS_META_CURRICULUM, true );
        
        if ( ! is_array( $curriculum ) ) {
            $curriculum = [];
        }

        // Crear entrada para la lección en el curriculum
        $lesson_entry = [
            'title' => get_the_title( $lesson_id ),
            'id'    => $lesson_id,
            'type'  => FairPlay_LMS_Config::MS_PT_LESSON,
        ];

        // Agregar al curriculum si no existe
        $lesson_exists = false;
        foreach ( $curriculum as $item ) {
            if ( isset( $item['id'] ) && (int) $item['id'] === $lesson_id ) {
                $lesson_exists = true;
                break;
            }
        }

        if ( ! $lesson_exists ) {
            $curriculum[] = $lesson_entry;
            update_post_meta( $course_id, FairPlay_LMS_Config::MS_META_CURRICULUM, $curriculum );
        }

        // También guardar en nuestro meta personalizado para tracking
        $course_lessons = (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_LESSONS, true );
        if ( ! in_array( $lesson_id, $course_lessons, true ) ) {
            $course_lessons[] = $lesson_id;
            update_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_LESSONS, $course_lessons );
        }
    }

    /**
     * Obtiene las lecciones asignadas a un curso.
     */
    private function get_course_lessons( int $course_id ): array {
        // Obtener desde el curriculum de MasterStudy
        $curriculum = get_post_meta( $course_id, FairPlay_LMS_Config::MS_META_CURRICULUM, true );
        
        if ( ! is_array( $curriculum ) || empty( $curriculum ) ) {
            return [];
        }

        $lessons = [];
        foreach ( $curriculum as $item ) {
            if ( isset( $item['id'] ) && isset( $item['type'] ) && FairPlay_LMS_Config::MS_PT_LESSON === $item['type'] ) {
                $lesson = get_post( (int) $item['id'] );
                if ( $lesson && FairPlay_LMS_Config::MS_PT_LESSON === $lesson->post_type ) {
                    $lessons[] = $lesson;
                }
            }
        }

        return $lessons;
    }

    /**
     * Obtiene todas las lecciones MasterStudy disponibles.
     */
    private function get_all_lessons(): array {
        $lessons = get_posts( [
            'post_type'      => FairPlay_LMS_Config::MS_PT_LESSON,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        return is_array( $lessons ) ? $lessons : [];
    }

    /**
     * Desasigna una lección de un curso.
     */
    private function unassign_lesson_from_course( int $course_id, int $lesson_id ): void {
        // Remover del curriculum de MasterStudy
        $curriculum = get_post_meta( $course_id, FairPlay_LMS_Config::MS_META_CURRICULUM, true );
        
        if ( is_array( $curriculum ) ) {
            $curriculum = array_filter( $curriculum, function( $item ) use ( $lesson_id ) {
                return ! ( isset( $item['id'] ) && (int) $item['id'] === $lesson_id );
            });
            
            // Re-indexar el array
            $curriculum = array_values( $curriculum );
            update_post_meta( $course_id, FairPlay_LMS_Config::MS_META_CURRICULUM, $curriculum );
        }

        // Remover de nuestro meta personalizado
        $course_lessons = (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_LESSONS, true );
        $course_lessons = array_filter( $course_lessons, function( $id ) use ( $lesson_id ) {
            return (int) $id !== $lesson_id;
        });
        $course_lessons = array_values( $course_lessons );
        update_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_LESSONS, $course_lessons );
    }

    // ==========================================
    // FEATURE 1: META BOX DE ESTRUCTURAS
    // ==========================================

    /**
     * Registra la meta box de estructuras para cursos MasterStudy.
     */
    public function register_structures_meta_box(): void {
        add_meta_box(
            'fplms_course_structures_metabox',
            '🏢 Asignar Estructuras FairPlay',
            [ $this, 'render_structures_meta_box' ],
            FairPlay_LMS_Config::MS_PT_COURSE,
            'side',
            'default'
        );
    }

    /**
     * Renderiza el contenido de la meta box de estructuras.
     * Adapta el contenido según el rol del usuario.
     * 
     * @param WP_Post $post El post actual (curso)
     */
    public function render_structures_meta_box( $post ): void {
        // Nonce para seguridad
        wp_nonce_field( 'fplms_save_course_structures', 'fplms_structures_nonce' );
        
        // Obtener estructuras actuales si el curso ya existe
        $current_structures = [];
        if ( $post->ID ) {
            $current_structures = $this->get_course_structures( $post->ID );
        }
        
        // Obtener estructuras disponibles según rol del usuario
        $available_structures = $this->get_available_structures_for_user();
        
        // Verificar si el usuario es instructor
        $current_user = wp_get_current_user();
        $is_instructor = in_array( FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR, $current_user->roles ?? [], true );
        $is_admin = current_user_can( 'manage_options' );
        
        ?>
        <div class="fplms-metabox-structures">
            <style>
                .fplms-metabox-structures {
                    font-size: 13px;
                }
                .fplms-structure-section {
                    margin-bottom: 15px;
                    padding-bottom: 15px;
                    border-bottom: 1px solid #ddd;
                }
                .fplms-structure-section:last-child {
                    border-bottom: none;
                }
                .fplms-structure-title {
                    font-weight: 600;
                    margin-bottom: 8px;
                    color: #1d2327;
                }
                .fplms-structure-checkbox {
                    display: block;
                    margin: 5px 0;
                    padding: 3px 0;
                }
                .fplms-structure-checkbox input {
                    margin-right: 5px;
                }
                .fplms-cascade-info {
                    background: #f0f6fc;
                    border-left: 3px solid #0073aa;
                    padding: 10px;
                    margin-bottom: 15px;
                    font-size: 12px;
                    line-height: 1.5;
                }
                .fplms-cascade-info strong {
                    display: block;
                    margin-bottom: 5px;
                }
                .fplms-notification-info {
                    background: #fff3cd;
                    border-left: 3px solid #ffc107;
                    padding: 10px;
                    margin-top: 15px;
                    font-size: 12px;
                }
                .fplms-instructor-info {
                    background: #fff3cd;
                    border-left: 3px solid #ffc107;
                    padding: 10px;
                    margin-bottom: 15px;
                    font-size: 12px;
                    line-height: 1.5;
                }
                .fplms-admin-info {
                    background: #d1ecf1;
                    border-left: 3px solid #0c5460;
                    padding: 10px;
                    margin-bottom: 15px;
                    font-size: 12px;
                }
            </style>
            
            <?php if ( $is_instructor && ! $is_admin ) : ?>
                <div class="fplms-instructor-info">
                    <strong>👨‍🏫 Modo Instructor</strong><br>
                    Solo puedes asignar a tus estructuras.
                </div>
            <?php else : ?>
                <div class="fplms-admin-info">
                    <strong>👑 Administrador</strong><br>
                    Puedes asignar a cualquier estructura.
                </div>
            <?php endif; ?>
            
            <div class="fplms-cascade-info">
                <strong>ℹ️ Asignación en cascada</strong>
                Al seleccionar una estructura, se asignan automáticamente todas las estructuras descendientes.
            </div>
            
            <!-- Ciudades -->
            <?php if ( ! empty( $available_structures['cities'] ) ) : ?>
            <div class="fplms-structure-section">
                <div class="fplms-structure-title">📍 Ciudades</div>
                <?php foreach ( $available_structures['cities'] as $term_id => $term_name ) : ?>
                    <label class="fplms-structure-checkbox">
                        <input type="checkbox" 
                               name="fplms_course_cities[]" 
                               value="<?php echo esc_attr( $term_id ); ?>"
                               <?php checked( in_array( $term_id, $current_structures['cities'] ?? [], true ) ); ?>>
                        <?php echo esc_html( $term_name ); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Empresas -->
            <?php if ( ! empty( $available_structures['companies'] ) ) : ?>
            <div class="fplms-structure-section">
                <div class="fplms-structure-title">🏢 Empresas</div>
                <?php foreach ( $available_structures['companies'] as $term_id => $term_name ) : ?>
                    <label class="fplms-structure-checkbox">
                        <input type="checkbox" 
                               name="fplms_course_companies[]" 
                               value="<?php echo esc_attr( $term_id ); ?>"
                               <?php checked( in_array( $term_id, $current_structures['companies'] ?? [], true ) ); ?>>
                        <?php echo esc_html( $term_name ); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Canales -->
            <?php if ( ! empty( $available_structures['channels'] ) ) : ?>
            <div class="fplms-structure-section">
                <div class="fplms-structure-title">🏪 Canales</div>
                <?php foreach ( $available_structures['channels'] as $term_id => $term_name ) : ?>
                    <label class="fplms-structure-checkbox">
                        <input type="checkbox" 
                               name="fplms_course_channels[]" 
                               value="<?php echo esc_attr( $term_id ); ?>"
                               <?php checked( in_array( $term_id, $current_structures['channels'] ?? [], true ) ); ?>>
                        <?php echo esc_html( $term_name ); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Sucursales -->
            <?php if ( ! empty( $available_structures['branches'] ) ) : ?>
            <div class="fplms-structure-section">
                <div class="fplms-structure-title">🏢 Sucursales</div>
                <?php foreach ( $available_structures['branches'] as $term_id => $term_name ) : ?>
                    <label class="fplms-structure-checkbox">
                        <input type="checkbox" 
                               name="fplms_course_branches[]" 
                               value="<?php echo esc_attr( $term_id ); ?>"
                               <?php checked( in_array( $term_id, $current_structures['branches'] ?? [], true ) ); ?>>
                        <?php echo esc_html( $term_name ); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Cargos -->
            <?php if ( ! empty( $available_structures['roles'] ) ) : ?>
            <div class="fplms-structure-section">
                <div class="fplms-structure-title">👔 Cargos</div>
                <?php foreach ( $available_structures['roles'] as $term_id => $term_name ) : ?>
                    <label class="fplms-structure-checkbox">
                        <input type="checkbox" 
                               name="fplms_course_roles[]" 
                               value="<?php echo esc_attr( $term_id ); ?>"
                               <?php checked( in_array( $term_id, $current_structures['roles'] ?? [], true ) ); ?>>
                        <?php echo esc_html( $term_name ); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if ( empty( $available_structures['cities'] ) && empty( $available_structures['channels'] ) ) : ?>
                <p style="color: #d63638; font-size: 12px;">
                    ⚠️ No tienes estructuras asignadas. Contacta al administrador para poder asignar cursos a estructuras.
                </p>
            <?php endif; ?>
            
            <div class="fplms-notification-info">
                📧 Los usuarios de las estructuras seleccionadas recibirán un correo cuando se publique el curso.
            </div>
        </div>
        <?php
    }

    /**
     * Guarda las estructuras cuando se guarda/publica un curso de MasterStudy.
     * Incluye validación de permisos para instructores.
     * 
     * @param int     $post_id ID del post
     * @param WP_Post $post    Objeto del post
     * @param bool    $update  Si es actualización o nuevo
     */
    public function save_course_structures_on_publish( int $post_id, WP_Post $post, bool $update ): void {
        
        // 1. Verificar nonce
        if ( ! isset( $_POST['fplms_structures_nonce'] ) || 
             ! wp_verify_nonce( $_POST['fplms_structures_nonce'], 'fplms_save_course_structures' ) ) {
            return;
        }
        
        // 2. Verificar que no sea autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        
        // 3. Verificar permisos
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        
        // 4. Verificar que es el post type correcto
        if ( FairPlay_LMS_Config::MS_PT_COURSE !== $post->post_type ) {
            return;
        }
        
        // Obtener estructuras del POST
        $cities    = isset( $_POST['fplms_course_cities'] ) ? array_map( 'absint', (array) $_POST['fplms_course_cities'] ) : [];
        $companies = isset( $_POST['fplms_course_companies'] ) ? array_map( 'absint', (array) $_POST['fplms_course_companies'] ) : [];
        $channels  = isset( $_POST['fplms_course_channels'] ) ? array_map( 'absint', (array) $_POST['fplms_course_channels'] ) : [];
        $branches  = isset( $_POST['fplms_course_branches'] ) ? array_map( 'absint', (array) $_POST['fplms_course_branches'] ) : [];
        $roles     = isset( $_POST['fplms_course_roles'] ) ? array_map( 'absint', (array) $_POST['fplms_course_roles'] ) : [];
        
        // VALIDACIÓN: Verificar que el instructor solo asigna a sus estructuras
        if ( ! $this->validate_instructor_structures( $channels, $cities, $companies, $branches, $roles ) ) {
            // El instructor intentó asignar a estructuras no autorizadas
            add_action( 'admin_notices', function() {
                echo '<div class="error notice"><p>⚠️ Error: No puedes asignar el curso a estructuras donde no estás asignado.</p></div>';
            });
            return;
        }
        
        // Aplicar cascada jerárquica
        $cascaded_structures = $this->apply_cascade_logic( $cities, $companies, $channels, $branches, $roles );
        
        // LOGGING: Verificar guardado
        error_log( '=== FPLMS: Guardando estructuras ===' );
        error_log( 'Curso ID: ' . $post_id );
        error_log( 'Título: ' . $post->post_title );
        error_log( 'Status: ' . $post->post_status );
        error_log( 'Usuario: ' . get_current_user_id() . ' (' . wp_get_current_user()->user_login . ')' );
        error_log( 'Estructuras INPUT:' );
        error_log( '  - Ciudades: ' . wp_json_encode( $cities ) );
        error_log( '  - Empresas: ' . wp_json_encode( $companies ) );
        error_log( '  - Canales: ' . wp_json_encode( $channels ) );
        error_log( 'Estructuras DESPUÉS DE CASCADA:' );
        error_log( '  - Ciudades: ' . wp_json_encode( $cascaded_structures['cities'] ) );
        error_log( '  - Empresas: ' . wp_json_encode( $cascaded_structures['companies'] ) );
        error_log( '  - Canales: ' . wp_json_encode( $cascaded_structures['channels'] ) );
        error_log( '  - Sucursales: ' . wp_json_encode( $cascaded_structures['branches'] ) );
        error_log( '  - Cargos: ' . wp_json_encode( $cascaded_structures['roles'] ) );
        
        // Guardar en post_meta
        update_post_meta( $post_id, FairPlay_LMS_Config::META_COURSE_CITIES, $cascaded_structures['cities'] );
        update_post_meta( $post_id, FairPlay_LMS_Config::META_COURSE_COMPANIES, $cascaded_structures['companies'] );
        update_post_meta( $post_id, FairPlay_LMS_Config::META_COURSE_CHANNELS, $cascaded_structures['channels'] );
        update_post_meta( $post_id, FairPlay_LMS_Config::META_COURSE_BRANCHES, $cascaded_structures['branches'] );
        update_post_meta( $post_id, FairPlay_LMS_Config::META_COURSE_ROLES, $cascaded_structures['roles'] );
        
        error_log( 'Post meta actualizado correctamente' );
        error_log( '=== Fin guardado ===' );
        
        // Enviar notificaciones SOLO si el curso se está publicando
        if ( 'publish' === $post->post_status ) {
            if ( ! $update ) {
                // Nuevo curso publicado - enviar notificaciones a todos
                $this->send_course_assignment_notifications( $post_id, $cascaded_structures );
            } else {
                // Curso actualizado - verificar si las estructuras cambiaron
                // Para evitar duplicar correos, solo notificar a nuevos usuarios
                $old_structures = [
                    'cities'    => (array) get_post_meta( $post_id, FairPlay_LMS_Config::META_COURSE_CITIES, true ),
                    'companies' => (array) get_post_meta( $post_id, FairPlay_LMS_Config::META_COURSE_COMPANIES, true ),
                    'channels'  => (array) get_post_meta( $post_id, FairPlay_LMS_Config::META_COURSE_CHANNELS, true ),
                    'branches'  => (array) get_post_meta( $post_id, FairPlay_LMS_Config::META_COURSE_BRANCHES, true ),
                    'roles'     => (array) get_post_meta( $post_id, FairPlay_LMS_Config::META_COURSE_ROLES, true ),
                ];
                
                $structures_changed = $this->structures_have_changed( $old_structures, $cascaded_structures );
                
                if ( $structures_changed ) {
                    // Las estructuras cambiaron - enviar notificaciones solo a nuevos usuarios
                    $this->send_course_update_notifications( $post_id, $cascaded_structures, $old_structures );
                }
            }
        }
    }

    /**
     * Obtiene las estructuras asignadas al usuario actual.
     * 
     * @param int $user_id ID del usuario (0 = actual)
     * @return array Array con estructura: ['city' => ID, 'company' => ID, 'channel' => ID, ...]
     */
    private function get_user_structures( int $user_id = 0 ): array {
        if ( 0 === $user_id ) {
            $user_id = get_current_user_id();
        }
        
        return [
            'city'    => (int) get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_CITY, true ),
            'company' => (int) get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_COMPANY, true ),
            'channel' => (int) get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_CHANNEL, true ),
            'branch'  => (int) get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_BRANCH, true ),
            'role'    => (int) get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_ROLE, true ),
        ];
    }

    /**
     * Obtiene las estructuras disponibles para asignar según el rol del usuario.
     * 
     * - Admin: Todas las estructuras
     * - Instructor: Solo sus propias estructuras
     * 
     * @return array Array con estructura: ['cities' => [...], 'channels' => [...], ...]
     */
    private function get_available_structures_for_user(): array {
        // Si es administrador, devuelve todas las estructuras
        if ( current_user_can( 'manage_options' ) || current_user_can( FairPlay_LMS_Config::CAP_MANAGE_STRUCTURES ) ) {
            return [
                'cities'    => $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CITY ),
                'companies' => $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_COMPANY ),
                'channels'  => $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CHANNEL ),
                'branches'  => $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_BRANCH ),
                'roles'     => $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_ROLE ),
            ];
        }
        
        // Si es instructor, solo sus estructuras
        $user_id = get_current_user_id();
        $user_structures = $this->get_user_structures( $user_id );
        
        $available = [
            'cities'    => [],
            'companies' => [],
            'channels'  => [],
            'branches'  => [],
            'roles'     => [],
        ];
        
        // Ciudad del instructor
        if ( $user_structures['city'] > 0 ) {
            $city_term = get_term( $user_structures['city'] );
            if ( $city_term && ! is_wp_error( $city_term ) ) {
                $available['cities'][ $city_term->term_id ] = $city_term->name;
            }
        }
        
        // Empresa del instructor
        if ( $user_structures['company'] > 0 ) {
            $company_term = get_term( $user_structures['company'] );
            if ( $company_term && ! is_wp_error( $company_term ) ) {
                $available['companies'][ $company_term->term_id ] = $company_term->name;
            }
        }
        
        // Canal del instructor (MUY IMPORTANTE)
        if ( $user_structures['channel'] > 0 ) {
            $channel_term = get_term( $user_structures['channel'] );
            if ( $channel_term && ! is_wp_error( $channel_term ) ) {
                $available['channels'][ $channel_term->term_id ] = $channel_term->name;
            }
        }
        
        // Sucursal del instructor
        if ( $user_structures['branch'] > 0 ) {
            $branch_term = get_term( $user_structures['branch'] );
            if ( $branch_term && ! is_wp_error( $branch_term ) ) {
                $available['branches'][ $branch_term->term_id ] = $branch_term->name;
            }
        }
        
        // Cargo del instructor
        if ( $user_structures['role'] > 0 ) {
            $role_term = get_term( $user_structures['role'] );
            if ( $role_term && ! is_wp_error( $role_term ) ) {
                $available['roles'][ $role_term->term_id ] = $role_term->name;
            }
        }
        
        return $available;
    }

    /**
     * Valida que el instructor solo asigne a estructuras donde está asignado.
     * Los administradores siempre pasan la validación.
     * 
     * @param array $channels  Canales a asignar
     * @param array $cities    Ciudades a asignar
     * @param array $companies Empresas a asignar
     * @param array $branches  Sucursales a asignar
     * @param array $roles     Cargos a asignar
     * @return bool True si es válido, False si no
     */
    private function validate_instructor_structures( array $channels, array $cities = [], array $companies = [], array $branches = [], array $roles = [] ): bool {
        // Admin siempre puede asignar a cualquier estructura
        if ( current_user_can( 'manage_options' ) || current_user_can( FairPlay_LMS_Config::CAP_MANAGE_STRUCTURES ) ) {
            return true;
        }
        
        $user_id = get_current_user_id();
        $user_structures = $this->get_user_structures( $user_id );
        
        // Validar ciudades
        foreach ( $cities as $city_id ) {
            if ( $city_id > 0 && $city_id !== $user_structures['city'] ) {
                return false; // Intenta asignar a una ciudad diferente
            }
        }
        
        // Validar empresas
        foreach ( $companies as $company_id ) {
            if ( $company_id > 0 && $company_id !== $user_structures['company'] ) {
                return false;
            }
        }
        
        // Validar canales (CRÍTICO)
        foreach ( $channels as $channel_id ) {
            if ( $channel_id > 0 && $channel_id !== $user_structures['channel'] ) {
                return false; // Intenta asignar a un canal donde NO está
            }
        }
        
        // Validar sucursales
        foreach ( $branches as $branch_id ) {
            if ( $branch_id > 0 && $branch_id !== $user_structures['branch'] ) {
                return false;
            }
        }
        
        // Validar cargos
        foreach ( $roles as $role_id ) {
            if ( $role_id > 0 && $role_id !== $user_structures['role'] ) {
                return false;
            }
        }
        
        return true; // Todas las validaciones pasaron
    }

    /**
     * Verifica si las estructuras han cambiado.
     * 
     * @param array $old_structures Estructuras anteriores
     * @param array $new_structures Estructuras nuevas
     * @return bool True si cambiaron
     */
    private function structures_have_changed( array $old_structures, array $new_structures ): bool {
        $keys = [ 'cities', 'companies', 'channels', 'branches', 'roles' ];
        
        foreach ( $keys as $key ) {
            $old = $old_structures[ $key ] ?? [];
            $new = $new_structures[ $key ] ?? [];
            
            sort( $old );
            sort( $new );
            
            if ( $old !== $new ) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Envía notificaciones solo a los usuarios nuevos que se agregaron.
     * 
     * @param int   $course_id ID del curso
     * @param array $new_structures Nuevas estructuras
     * @param array $old_structures Estructuras antiguas
     */
    private function send_course_update_notifications( int $course_id, array $new_structures, array $old_structures ): void {
        // Obtener usuarios nuevos (que no estaban antes)
        $old_users = $this->get_users_by_structures( $old_structures );
        $new_users = $this->get_users_by_structures( $new_structures );
        
        // Calcular diferencia (solo nuevos usuarios)
        $users_to_notify = array_diff( $new_users, $old_users );
        
        if ( empty( $users_to_notify ) ) {
            return;
        }
        
        // Obtener información del curso
        $course = get_post( $course_id );
        if ( ! $course ) {
            return;
        }
        
        $course_title = get_the_title( $course );
        $course_url   = get_permalink( $course_id );
        
        // Enviar correo solo a usuarios nuevos
        foreach ( $users_to_notify as $user_id ) {
            $user = get_user_by( 'id', $user_id );
            if ( ! $user ) {
                continue;
            }
            
            $subject = sprintf( 'Nuevo curso asignado: %s', $course_title );
            $message = sprintf(
                "Hola %s,\n\n" .
                "Se te ha asignado un nuevo curso:\n\n" .
                "📚 Curso: %s\n" .
                "🔗 Acceder al curso: %s\n\n" .
                "¡Esperamos que disfrutes este contenido educativo!\n\n" .
                "Saludos,\n" .
                "Equipo de FairPlay LMS",
                $user->display_name,
                $course_title,
                $course_url
            );
            
            wp_mail( $user->user_email, $subject, $message );
        }
    }

	/**
	 * Sincronizar categorías cuando se guarda el curso (editor clásico o actualizaciones)
	 *
	 * @param int     $post_id Post ID
	 * @param WP_Post $post    Post object
	 * @param bool    $update  Whether this is an existing post being updated
	 * @return void
	 */
	public function sync_course_categories_on_save( int $post_id, $post, bool $update ): void {
		// Evitar autosaves y revisiones
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Evitar loops recursivos
		if ( defined( 'FPLMS_SYNCING_COURSE_SAVE' ) && FPLMS_SYNCING_COURSE_SAVE ) {
			return;
		}

		define( 'FPLMS_SYNCING_COURSE_SAVE', true );

		// Obtener categorías asignadas al curso
		$category_ids = wp_get_object_terms(
			$post_id,
			FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY,
			[ 'fields' => 'ids' ]
		);

		if ( is_wp_error( $category_ids ) || empty( $category_ids ) ) {
			return;
		}

		$structures_controller = new FairPlay_LMS_Structures_Controller();
		$channels_to_assign    = [];

		// Detectar qué categorías están vinculadas a canales
		foreach ( $category_ids as $category_id ) {
			// Validar que la categoría existe
			$category = get_term( $category_id, FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY );
			if ( ! $category || is_wp_error( $category ) ) {
				continue;
			}

			$channel_id = $structures_controller->get_linked_channel( $category_id );
			if ( $channel_id ) {
				// Validar que el canal existe
				$channel = get_term( $channel_id, FairPlay_LMS_Config::TAX_CHANNEL );
				if ( $channel && ! is_wp_error( $channel ) ) {
					$channels_to_assign[] = $channel_id;
				} else {
					// Canal vinculado no existe, limpiar vinculación
					delete_term_meta( $category_id, 'fplms_linked_channel_id' );
				}
			}
		}

		if ( empty( $channels_to_assign ) ) {
			return;
		}

		// Aplicar cascada de estructuras
		$cascaded = $this->apply_structure_cascade(
			[],              // cities
			[],              // companies
			$channels_to_assign,
			[],              // branches
			[]               // roles
		);

		// Guardar en post_meta
		update_post_meta( $post_id, 'fplms_course_cities', $cascaded['cities'] );
		update_post_meta( $post_id, 'fplms_course_companies', $cascaded['companies'] );
		update_post_meta( $post_id, 'fplms_course_channels', $cascaded['channels'] );
		update_post_meta( $post_id, 'fplms_course_branches', $cascaded['branches'] );
		update_post_meta( $post_id, 'fplms_course_roles', $cascaded['roles'] );

		// Registrar en auditoría
		if ( class_exists( 'FairPlay_LMS_Audit_Logger' ) ) {
			$audit = new FairPlay_LMS_Audit_Logger();
			$audit->log_action(
				'course_structures_synced_on_save',
				'course',
				$post_id,
				get_the_title( $post_id ),
				'Categorías: ' . implode( ', ', $category_ids ),
				[
					'categories' => $category_ids,
					'channels'   => $channels_to_assign,
					'cascaded'   => $cascaded,
				]
			);
		}
	}

	/**
	 * Sincronizar categorías de Course Builder con estructuras FairPlay
	 * Se ejecuta cuando se asignan categorías a un curso en Course Builder
	 *
	 * @param int    $object_id  ID del curso
	 * @param array  $terms      Array de term IDs asignados
	 * @param array  $tt_ids     Array de term taxonomy IDs
	 * @param string $taxonomy   Taxonomía
	 * @param bool   $append     Si se agregaron o reemplazaron
	 * @param array  $old_terms  Términos anteriores
	 * @return void
	 */
	public function sync_categories_to_structures(
		int $object_id,
		array $terms,
		array $tt_ids,
		string $taxonomy,
		bool $append,
		array $old_terms
	): void {
		// Solo procesar si es la taxonomía de categorías de MasterStudy
		if ( $taxonomy !== FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY ) {
			return;
		}

		// Verificar que sea un curso
		$post = get_post( $object_id );
		if ( ! $post || $post->post_type !== FairPlay_LMS_Config::MS_PT_COURSE ) {
			return;
		}

		// Evitar loops recursivos
		if ( defined( 'FPLMS_SYNCING_CATEGORIES' ) && FPLMS_SYNCING_CATEGORIES ) {
			return;
		}

		define( 'FPLMS_SYNCING_CATEGORIES', true );

		$structures_controller = new FairPlay_LMS_Structures_Controller();
		$channels_to_assign    = [];

		// Detectar qué categorías están vinculadas a canales
		foreach ( $terms as $term_id ) {
			// Validar que el término existe
			$term = get_term( $term_id, FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}

			$channel_id = $structures_controller->get_linked_channel( $term_id );
			if ( $channel_id ) {
				// Validar que el canal existe
				$channel = get_term( $channel_id, FairPlay_LMS_Config::TAX_CHANNEL );
				if ( $channel && ! is_wp_error( $channel ) ) {
					$channels_to_assign[] = $channel_id;
				} else {
					// Canal vinculado no existe, limpiar vinculación
					delete_term_meta( $term_id, 'fplms_linked_channel_id' );
				}
			}
		}

		if ( empty( $channels_to_assign ) ) {
			return;
		}

		// Aplicar cascada de estructuras
		$cascaded = $this->apply_structure_cascade(
			[],              // cities
			[],              // companies
			$channels_to_assign,
			[],              // branches
			[]               // roles
		);

		// Guardar en post_meta
		update_post_meta( $object_id, 'fplms_course_cities', $cascaded['cities'] );
		update_post_meta( $object_id, 'fplms_course_companies', $cascaded['companies'] );
		update_post_meta( $object_id, 'fplms_course_channels', $cascaded['channels'] );
		update_post_meta( $object_id, 'fplms_course_branches', $cascaded['branches'] );
		update_post_meta( $object_id, 'fplms_course_roles', $cascaded['roles'] );

		// Registrar en auditoría
		if ( class_exists( 'FairPlay_LMS_Audit_Logger' ) ) {
			$audit = new FairPlay_LMS_Audit_Logger();
			$audit->log_action(
				'course_structures_synced_from_categories',
				'course',
				$object_id,
				get_the_title( $object_id ),
				$old_terms,
				[
					'categories' => $terms,
					'channels'   => $channels_to_assign,
					'cascaded'   => $cascaded,
				]
			);
		}

		// Enviar notificaciones
		$this->send_course_update_notifications( $object_id, [], $cascaded );
	}

	/**
	 * Aplicar cascada de estructuras jerárquicas
	 *
	 * @param array $cities    IDs de ciudades
	 * @param array $companies IDs de empresas
	 * @param array $channels  IDs de canales
	 * @param array $branches  IDs de sucursales
	 * @param array $roles     IDs de cargos
	 * @return array Array con todas las estructuras después de aplicar cascada
	 */
	public function apply_structure_cascade(
		array $cities,
		array $companies,
		array $channels,
		array $branches,
		array $roles
	): array {
		$structures_controller = new FairPlay_LMS_Structures_Controller();

		$result = [
			'cities'    => $cities,
			'companies' => $companies,
			'channels'  => $channels,
			'branches'  => $branches,
			'roles'     => $roles,
		];

		// Si hay canales, obtener sus empresas y ciudades padres
		if ( ! empty( $channels ) ) {
			foreach ( $channels as $channel_id ) {
				// Obtener empresas del canal (retorna array de IDs)
				$channel_companies = $structures_controller->get_term_companies( $channel_id );
				if ( ! empty( $channel_companies ) ) {
					foreach ( $channel_companies as $company_id ) {
						if ( ! in_array( $company_id, $result['companies'], true ) ) {
							$result['companies'][] = $company_id;
						}

						// Obtener ciudades de la empresa (retorna array de IDs)
						$company_cities = $structures_controller->get_term_cities( $company_id );
						if ( ! empty( $company_cities ) ) {
							foreach ( $company_cities as $city_id ) {
								if ( ! in_array( $city_id, $result['cities'], true ) ) {
									$result['cities'][] = $city_id;
								}
							}
						}
					}
				}

				// Obtener sucursales del canal (retorna array de IDs)
				$channel_branches = $structures_controller->get_term_branches( $channel_id );
				if ( ! empty( $channel_branches ) ) {
					foreach ( $channel_branches as $branch_id ) {
						if ( ! in_array( $branch_id, $result['branches'], true ) ) {
							$result['branches'][] = $branch_id;
						}

						// Obtener cargos de la sucursal (retorna array de IDs)
						$branch_roles = $structures_controller->get_term_roles( $branch_id );
						if ( ! empty( $branch_roles ) ) {
							foreach ( $branch_roles as $role_id ) {
								if ( ! in_array( $role_id, $result['roles'], true ) ) {
									$result['roles'][] = $role_id;
								}
							}
						}
					}
				}
			}
		}

		// Si hay empresas (sin canales), obtener sus ciudades
		if ( ! empty( $companies ) && empty( $channels ) ) {
			foreach ( $companies as $company_id ) {
				$company_cities = $structures_controller->get_term_cities( $company_id );
				if ( ! empty( $company_cities ) ) {
					foreach ( $company_cities as $city ) {
						if ( ! in_array( $city->term_id, $result['cities'], true ) ) {
							$result['cities'][] = $city->term_id;
						}
					}
				}
			}
		}

		return $result;
	}
}

