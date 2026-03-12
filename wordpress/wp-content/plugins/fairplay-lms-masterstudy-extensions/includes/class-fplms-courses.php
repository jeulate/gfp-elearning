<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FairPlay_LMS_Courses_Controller {

    /**
     * @var FairPlay_LMS_Structures_Controller
     */
    private $structures;

    /**
     * @var FairPlay_LMS_Audit_Logger
     */
    private $logger;

    public function __construct( FairPlay_LMS_Structures_Controller $structures = null ) {
        $this->structures = $structures;
        $this->logger = new FairPlay_LMS_Audit_Logger();
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
     * Oculta cursos con post_status = 'draft' a todos los roles excepto el administrador.
     * Se aplica tanto en frontend como en backend (REST, shortcodes, etc.).
     */
    public function filter_inactive_courses( \WP_Query $query ): void {
        // Los administradores ven todo.
        if ( current_user_can( 'administrator' ) ) {
            return;
        }

        // Si la query incluye el post type de cursos, forzar sólo publicados.
        $post_type = $query->get( 'post_type' );
        $types = (array) $post_type;
        if ( in_array( FairPlay_LMS_Config::MS_PT_COURSE, $types, true )
            || 'any' === $post_type
            || ( is_singular() && get_query_var( 'post_type' ) === FairPlay_LMS_Config::MS_PT_COURSE )
        ) {
            $query->set( 'post_status', [ 'publish' ] );
        }
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

            // Capturar docente anterior ANTES de actualizar (para la bitácora)
            $old_teacher_id   = (int) get_post_meta( $course_id, FairPlay_LMS_Config::MS_META_COURSE_TEACHER, true );
            $old_teacher_user = $old_teacher_id ? get_user_by( 'id', $old_teacher_id ) : null;
            $old_teacher_name = $old_teacher_user ? $old_teacher_user->display_name : 'Sin docente';

            if ( $instructor_id > 0 ) {

                // Actualizar meta de MasterStudy Y post_author (ambos campos son usados por el LMS)
                update_post_meta( $course_id, FairPlay_LMS_Config::MS_META_COURSE_TEACHER, $instructor_id );
                wp_update_post( [
                    'ID'          => $course_id,
                    'post_author' => $instructor_id,
                ] );

                $user = get_user_by( 'id', $instructor_id );
                if ( $user && ! in_array( FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR, (array) $user->roles, true ) ) {
                    $user->add_role( FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR );
                }

                $new_teacher_name = $user ? $user->display_name : 'Sin docente';
            } else {
                delete_post_meta( $course_id, FairPlay_LMS_Config::MS_META_COURSE_TEACHER );
                // Sin docente: dejar post_author como admin (ID 1) o sin cambios
                $new_teacher_name = 'Sin docente';
            }

            // Registrar en la bitácora de auditoría
            $this->logger->log_action(
                'instructor_changed',
                'course',
                $course_id,
                get_the_title( $course_id ),
                $old_teacher_name,
                $new_teacher_name
            );

            wp_safe_redirect(
                add_query_arg(
                    [ 'page' => 'fplms-courses', 'notice' => 'instructor_assigned' ],
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

        // Eliminar cursos (acción masiva o individual) ──────────────────────
        if ( 'bulk_delete' === $action ) {
            $bulk_ids = isset( $_POST['fplms_bulk_course_ids'] )
                ? array_map( 'absint', (array) wp_unslash( $_POST['fplms_bulk_course_ids'] ) )
                : [];
            $deleted         = 0;
            $had_structures  = 0;
            foreach ( $bulk_ids as $bid ) {
                if ( ! $bid || FairPlay_LMS_Config::MS_PT_COURSE !== get_post_type( $bid ) ) {
                    continue;
                }
                // Detectar si el curso tenía estructuras asignadas
                $structs = $this->get_course_structures( $bid );
                $has_any = false;
                foreach ( $structs as $list ) {
                    if ( ! empty( array_filter( (array) $list ) ) ) {
                        $has_any = true;
                        break;
                    }
                }
                if ( $has_any ) {
                    ++$had_structures;
                }
                wp_delete_post( $bid, true );
                ++$deleted;
            }
            wp_safe_redirect(
                add_query_arg(
                    array_filter( [
                        'page'            => 'fplms-courses',
                        'deleted'         => $deleted,
                        'had_structures'  => $had_structures > 0 ? $had_structures : null,
                    ] ),
                    admin_url( 'admin.php' )
                )
            );
            exit;
        }

        // Activar / desactivar cursos (individual y masivo) ─────────────────
        if ( in_array( $action, [ 'toggle_course_status', 'bulk_activate', 'bulk_deactivate' ], true ) ) {

            // Los IDs siempre vienen en fplms_bulk_course_ids[] (tanto toggle individual como masivo)
            $target_ids = isset( $_POST['fplms_bulk_course_ids'] )
                ? array_map( 'absint', (array) wp_unslash( $_POST['fplms_bulk_course_ids'] ) )
                : [];

            if ( 'toggle_course_status' === $action ) {
                $new_status = sanitize_key( wp_unslash( $_POST['fplms_new_status'] ?? '' ) );
                $new_status = in_array( $new_status, [ 'publish', 'draft' ], true ) ? $new_status : 'draft';
                $notice_key = 'publish' === $new_status ? 'course_activated' : 'course_deactivated';
            } else {
                $new_status = 'bulk_activate' === $action ? 'publish' : 'draft';
                $notice_key = 'bulk_activate' === $action ? 'courses_activated' : 'courses_deactivated';
            }

            $processed = 0;
            foreach ( $target_ids as $tid ) {
                if ( ! $tid || FairPlay_LMS_Config::MS_PT_COURSE !== get_post_type( $tid ) ) {
                    continue;
                }
                $old_status = get_post_status( $tid );
                if ( $old_status === $new_status ) {
                    continue; // sin cambio real, evitar entrada duplicada en bitácora
                }
                wp_update_post( [ 'ID' => $tid, 'post_status' => $new_status ] );
                $this->logger->log_action(
                    'publish' === $new_status ? 'course_activated' : 'course_deactivated',
                    'course',
                    $tid,
                    get_the_title( $tid ),
                    'publish' === $old_status ? 'Activo' : 'Inactivo',
                    'publish' === $new_status ? 'Activo' : 'Inactivo'
                );
                ++$processed;
            }

            wp_safe_redirect(
                add_query_arg(
                    [ 'page' => 'fplms-courses', 'notice' => $notice_key, 'count' => $processed ],
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

        // 1. Usuarios con el rol de instructor o administrador
        $user_query = new WP_User_Query(
            [
                'role__in' => [
                    FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR,
                    'administrator',
                ],
                'number'  => -1,
                'orderby' => 'display_name',
                'order'   => 'ASC',
            ]
        );

        $users    = (array) $user_query->get_results();
        $user_ids = array_map( function( $u ) { return (int) $u->ID; }, $users );

        // 2. Incluir también los usuarios ya asignados como docentes en cursos existentes
        //    (ya sea via stm_lms_course_teacher meta o como post_author del curso).
        //    Esto garantiza que la lista coincida con lo que muestra MasterStudy en su
        //    propia vista de edición de curso.
        $courses = get_posts(
            [
                'post_type'      => FairPlay_LMS_Config::MS_PT_COURSE,
                'posts_per_page' => -1,
                'post_status'    => [ 'publish', 'draft' ],
                'fields'         => 'all',
            ]
        );

        $extra_ids = [];
        foreach ( $courses as $course ) {
            if ( ! empty( $course->post_author ) ) {
                $extra_ids[] = (int) $course->post_author;
            }
            $meta_teacher = (int) get_post_meta( $course->ID, FairPlay_LMS_Config::MS_META_COURSE_TEACHER, true );
            if ( $meta_teacher ) {
                $extra_ids[] = $meta_teacher;
            }
        }

        $extra_ids = array_unique( array_diff( array_filter( $extra_ids ), $user_ids ) );

        if ( ! empty( $extra_ids ) ) {
            $extra_query = new WP_User_Query(
                [
                    'include' => $extra_ids,
                    'fields'  => 'all',
                ]
            );
            $extra_users = (array) $extra_query->get_results();
            $users       = array_merge( $users, $extra_users );

            usort(
                $users,
                function( $a, $b ) {
                    return strcmp( $a->display_name, $b->display_name );
                }
            );
        }

        return $users;
    }

    /**
     * Listado de cursos MasterStudy — tabla moderna con búsqueda, paginación, acciones masivas y exportación.
     */
    private function render_course_list_view(): void {

        if ( ! post_type_exists( FairPlay_LMS_Config::MS_PT_COURSE ) ) {
            echo '<p><strong>Advertencia:</strong> No se encontró el tipo de contenido <code>' . esc_html( FairPlay_LMS_Config::MS_PT_COURSE ) . '</code>. Verifica que MasterStudy LMS esté activo.</p>';
            return;
        }

        // ── Cargar cursos ────────────────────────────────────────────────────
        $courses    = get_posts( [
            'post_type'      => FairPlay_LMS_Config::MS_PT_COURSE,
            'posts_per_page' => 500,
            'post_status'    => [ 'publish', 'draft' ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );
        $instructors = $this->get_available_instructors();
        $total       = count( $courses );
        $deleted_n        = isset( $_GET['deleted'] )        ? max( 0, (int) $_GET['deleted'] )        : 0;
        $had_structures_n = isset( $_GET['had_structures'] )  ? max( 0, (int) $_GET['had_structures'] ) : 0;
        $notice           = isset( $_GET['notice'] )          ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';
        $notice_count     = isset( $_GET['count'] )           ? max( 1, (int) $_GET['count'] )            : 1;
        $notice      = isset( $_GET['notice'] )   ? sanitize_text_field( wp_unslash( $_GET['notice'] ) ) : '';

        // ── Recopilar opciones de filtro: canales y sucursales ───────────────
        $filter_channels = [];
        $filter_branches = [];
        foreach ( $courses as $_fc ) {
            $_st = $this->get_course_structures( $_fc->ID );
            foreach ( $_st['channels'] as $_tid ) {
                $_tid = (int) $_tid;
                if ( $_tid && ! isset( $filter_channels[ $_tid ] ) ) {
                    $_t = get_term( $_tid );
                    if ( $_t && ! is_wp_error( $_t ) ) {
                        $filter_channels[ $_tid ] = $_t->name;
                    }
                }
            }
            foreach ( $_st['branches'] as $_tid ) {
                $_tid = (int) $_tid;
                if ( $_tid && ! isset( $filter_branches[ $_tid ] ) ) {
                    $_t = get_term( $_tid );
                    if ( $_t && ! is_wp_error( $_t ) ) {
                        $filter_branches[ $_tid ] = $_t->name;
                    }
                }
            }
        }
        asort( $filter_channels );
        asort( $filter_branches );
        ?>

        <style>
            /* ── Page header ── */
            .fplms-cl-page-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
            .fplms-cl-title-row   { display:flex; align-items:center; gap:12px; margin-bottom:4px; }
            .fplms-cl-page-icon   { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#667eea,#764ba2); box-shadow:0 3px 10px rgba(102,126,234,.4); }
            .fplms-cl-page-icon svg { width:22px; height:22px; fill:#fff; }
            .fplms-cl-page-header h2 { font-size:22px; font-weight:700; color:#1f2937; margin:0; }
            .fplms-cl-page-subtitle  { font-size:13px; color:#6b7280; margin:0; padding-left:54px; }

            /* ── Table card ── */
            .fplms-table-card-c { background:#fff; border-radius:14px; border:1px solid #e5e7eb; box-shadow:0 1px 8px rgba(0,0,0,.07); overflow:hidden; }

            /* ── Top bar ── */
            .fplms-topbar-c { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid #f3f4f6; flex-wrap:wrap; gap:12px; }
            .fplms-topbar-left-c  { display:flex; align-items:center; gap:8px; font-size:14px; color:#6b7280; }
            .fplms-topbar-right-c { display:flex; align-items:center; gap:10px; flex:1; justify-content:flex-end; flex-wrap:wrap; }
            .fplms-entries-sel-c  { padding:5px 10px; border:1px solid #e5e7eb; border-radius:6px; font-size:14px; color:#374151; background:#fff; cursor:pointer; outline:none; }

            /* ── Filter selects (canal, sucursal) ── */
            .fplms-cl-filter-sel { padding:8px 10px; border:1px solid #e5e7eb; border-radius:8px; font-size:13.5px; color:#374151; background:#fff; cursor:pointer; outline:none; transition:border-color .2s, box-shadow .2s; white-space:nowrap; }
            .fplms-cl-filter-sel:focus { border-color:#667eea; box-shadow:0 0 0 3px rgba(102,126,234,.1); }

            /* ── Search ── */
            .fplms-cl-search-wrap  { position:relative; flex:1; min-width:180px; max-width:340px; }
            .fplms-cl-search-input { padding:8px 32px 8px 14px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; color:#374151; width:100%; outline:none; transition:border-color .2s, box-shadow .2s; box-sizing:border-box; }
            .fplms-cl-search-input:focus { border-color:#667eea; box-shadow:0 0 0 3px rgba(102,126,234,.1); }
            .fplms-cl-search-input::placeholder { color:#9ca3af; }
            .fplms-cl-search-clear { position:absolute; right:9px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#9ca3af; font-size:15px; line-height:1; padding:2px; display:none; }
            .fplms-cl-search-clear:hover { color:#374151; }
            .fplms-cl-search-clear.visible { display:block; }

            /* ── Active filters badge ── */
            .fplms-cl-active-filters { display:flex; align-items:center; gap:7px; padding:5px 10px; background:#eff6ff; border-radius:7px; border:1px solid #bfdbfe; }
            .fplms-cl-active-badge { font-size:12px; color:#1d4ed8; font-weight:500; }
            .fplms-cl-filter-clear-all { font-size:12px; color:#6b7280; background:none; border:none; cursor:pointer; padding:0; white-space:nowrap; }
            .fplms-cl-filter-clear-all:hover { color:#dc2626; }

            /* ── Create course button ── */
            .fplms-cl-create-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; background:#667eea; color:#fff !important; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; transition:background .2s; text-decoration:none !important; white-space:nowrap; }
            .fplms-cl-create-btn:hover { background:#5a6fd6; color:#fff !important; }
            .fplms-cl-create-btn svg { width:16px; height:16px; fill:#fff; }

            /* ── Download button ── */
            .fplms-cl-dl-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; font-size:14px; font-weight:500; color:#374151; cursor:pointer; transition:all .2s; text-decoration:none; }
            .fplms-cl-dl-btn:hover { background:#f9fafb; border-color:#d1d5db; color:#374151; }
            .fplms-cl-dl-btn svg { width:16px; height:16px; fill:#6b7280; }
            .fplms-cl-dl-wrap { position:relative; display:inline-flex; }
            .fplms-cl-dl-dropdown { position:absolute; top:calc(100% + 6px); right:0; background:#fff; border:1px solid #e5e7eb; border-radius:8px; box-shadow:0 4px 16px rgba(0,0,0,.12); min-width:170px; z-index:200; display:none; overflow:hidden; }
            .fplms-cl-dl-dropdown.open { display:block; }
            .fplms-cl-dl-item { display:flex; align-items:center; gap:8px; padding:10px 14px; font-size:14px; color:#374151; cursor:pointer; border:none; background:none; width:100%; text-align:left; }
            .fplms-cl-dl-item:hover { background:#f9fafb; }
            .fplms-cl-dl-item svg { width:15px; height:15px; flex-shrink:0; }

            /* ── Bulk bar ── */
            .fplms-cl-bulk-bar { display:none; align-items:center; gap:12px; padding:11px 18px; border-bottom:1px solid #f3f4f6; background:#fafbff; flex-wrap:wrap; }
            .fplms-cl-bulk-bar.visible { display:flex; }
            .fplms-cl-bulk-label  { font-size:14px; color:#6b7280; font-weight:500; white-space:nowrap; }
            .fplms-cl-bulk-badge  { display:inline-flex; align-items:center; justify-content:center; min-width:22px; height:22px; padding:0 6px; background:#667eea; color:#fff; font-size:12px; font-weight:700; border-radius:11px; }
            .fplms-cl-bulk-select { padding:7px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; color:#374151; background:#fff; cursor:pointer; outline:none; }
            .fplms-cl-bulk-apply  { display:inline-flex; align-items:center; gap:6px; padding:7px 18px; background:#667eea; color:#fff; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; transition:background .2s; }
            .fplms-cl-bulk-apply:hover { background:#5a6fd6; }

            /* ── Table ── */
            .fplms-ct-wrapper { overflow-x:auto; -webkit-overflow-scrolling:touch; }
            .fplms-ct { width:100%; border-collapse:collapse; margin:0; font-size:14px; }
            .fplms-ct thead th { padding:11px 16px; text-align:left; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; color:#fff; background:#55585b; border-bottom:1px solid #e5e7eb; white-space:nowrap; }
            .fplms-ct tbody tr { border-bottom:1px solid #f3f4f6; transition:background .12s; }
            .fplms-ct tbody tr:last-child { border-bottom:none; }
            .fplms-ct tbody tr:hover { background:#f9fafb; }
            .fplms-ct td { padding:12px 16px; color:#374151; vertical-align:middle; }
            .fplms-ct-title { font-weight:600; color:#111827; font-size:14px; line-height:1.4; }
            .fplms-ct-meta  { font-size:12px; color:#9ca3af; margin-top:2px; }
            .fplms-ct input[type="checkbox"] { width:16px; height:16px; cursor:pointer; accent-color:#667eea; }

            /* ── Status badges ── */
            .fplms-cs-badge { display:inline-flex; align-items:center; padding:2px 9px; border-radius:20px; font-size:11px; font-weight:600; white-space:nowrap; margin-top:3px; }
            .fplms-cs-publish { background:#ECFDF3; color:#027A48; }
            .fplms-cs-draft   { background:#FFF8C5; color:#8a6c00; }

            /* ── Structure tags ── */
            .fplms-ct-struct-tags { display:flex; flex-wrap:wrap; gap:4px; }
            .fplms-ct-struct-tag  { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; background:#f3f4f6; border-radius:4px; font-size:12px; color:#374151; white-space:nowrap; }
            .fplms-ct-struct-tag svg { width:11px; height:11px; fill:#6b7280; flex-shrink:0; }
            .fplms-ct-struct-free { background:#e7f5fe; color:#1a56db; }
            .fplms-ct-struct-free svg { fill:#1a56db; }

            /* ── Action icon buttons ── */
            .fplms-ct-actions { display:flex; align-items:center; gap:2px; justify-content:center; }
            .fplms-ct-btn { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:6px; border:none; background:transparent; cursor:pointer; transition:all .15s; text-decoration:none; padding:0; position:relative; }
            .fplms-ct-btn svg { width:17px; height:17px; fill:#9ca3af; transition:fill .15s; }
            .fplms-ct-btn:hover { background:#f3f4f6; }
            .fplms-ct-btn.activate:hover  { background:#dcfce7; } .fplms-ct-btn.activate:hover svg  { fill:#16a34a; }
            .fplms-ct-btn.deactivate:hover { background:#fef9c3; } .fplms-ct-btn.deactivate:hover svg { fill:#ca8a04; }
            .fplms-ct-btn.structures:hover svg { fill:#0891b2; }
            .fplms-ct-btn.edit:hover svg       { fill:#2563eb; }
            .fplms-ct-btn.delete:hover svg     { fill:#dc2626; }
            .fplms-ct-btn::after { content:attr(data-tip); position:absolute; bottom:calc(100% + 6px); left:50%; transform:translateX(-50%); background:#1f2937; color:#fff; font-size:11px; white-space:nowrap; padding:4px 8px; border-radius:5px; pointer-events:none; opacity:0; transition:opacity .15s; z-index:99; }
            .fplms-ct-btn:hover::after { opacity:1; }
            /* Inactive (draft) row — slight dim */
            .fplms-cl-row.row-inactive { opacity:.6; }
            .fplms-cl-row.row-inactive .fplms-ct-title a { color:#6b7280; }

            /* ── Assign instructor form ── */
            .fplms-ct-inst-form { display:flex; gap:5px; align-items:center; }
            .fplms-ct-inst-form select { flex:1; min-width:0; max-width:200px; font-size:14px; padding:5px 8px; border:1px solid #e5e7eb; border-radius:6px; color:#374151; background:#fff; cursor:pointer; outline:none; transition:border-color .2s; }
            .fplms-ct-inst-form select:focus { border-color:#667eea; box-shadow:0 0 0 3px rgba(102,126,234,.1); }
            .fplms-ct-save-btn { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; background:#667eea; border:none; border-radius:6px; cursor:pointer; flex-shrink:0; transition:background .2s; }
            .fplms-ct-save-btn:hover { background:#5a6fd6; }
            .fplms-ct-save-btn svg { width:16px; height:16px; fill:#fff; }

            /* ── Pagination ── */
            .fplms-cl-pag-wrap { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-top:1px solid #f3f4f6; flex-wrap:wrap; gap:8px; }
            .fplms-cl-pag-info { font-size:13px; color:#6b7280; }
            .fplms-cl-pag-controls { display:flex; align-items:center; gap:4px; }
            .fplms-cl-pag-btn { min-width:36px; height:34px; padding:0 10px; border:1px solid #e5e7eb; background:#fff; color:#374151; font-size:13px; border-radius:6px; cursor:pointer; transition:all .15s; display:inline-flex; align-items:center; justify-content:center; }
            .fplms-cl-pag-btn:hover:not(:disabled) { background:#f3f4f6; border-color:#d1d5db; }
            .fplms-cl-pag-btn.active { background:#667eea; color:#fff; border-color:#667eea; font-weight:600; }
            .fplms-cl-pag-btn:disabled { color:#d1d5db; cursor:not-allowed; }

            /* ── Toast notifications ── */
            .fplms-cl-toast { display:flex; align-items:center; gap:10px; padding:13px 40px 13px 16px; border-radius:10px; margin-bottom:16px; font-size:14px; position:relative; animation:fplmsSlideIn .3s ease; }
            .fplms-cl-toast-green { background:#ECFDF3; border:1px solid #6ee7b7; color:#065f46; }
            .fplms-cl-toast-blue  { background:#eff6ff; border:1px solid #93c5fd; color:#1e40af; }
            .fplms-cl-toast-red   { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
            .fplms-cl-toast svg  { width:18px; height:18px; fill:currentColor; flex-shrink:0; }
            .fplms-cl-toast-close { position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:currentColor; opacity:.5; font-size:16px; padding:2px 6px; line-height:1; }
            .fplms-cl-toast-close:hover { opacity:1; }
            @keyframes fplmsSlideIn { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }

            /* ── Delete modal ── */
            .fplms-cl-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:100000; align-items:center; justify-content:center; }
            .fplms-cl-modal-overlay.active { display:flex; }
            .fplms-cl-modal { background:#fff; border-radius:14px; box-shadow:0 8px 40px rgba(0,0,0,.2); max-width:460px; width:100%; overflow:hidden; }
            .fplms-cl-modal-header { padding:18px 22px; border-bottom:1px solid #fee2e2; background:#fef2f2; display:flex; align-items:center; gap:10px; }
            .fplms-cl-modal-header svg { width:22px; height:22px; fill:#dc2626; flex-shrink:0; }
            .fplms-cl-modal-header h3 { margin:0; font-size:16px; font-weight:700; color:#7f1d1d; }
            .fplms-cl-modal-body { padding:22px; color:#374151; font-size:14px; }
            .fplms-cl-modal-course { padding:10px 14px; background:#f9fafb; border-radius:8px; border-left:3px solid #dc2626; font-weight:600; color:#111827; margin:12px 0; }
            .fplms-cl-modal-footer { display:flex; gap:10px; justify-content:flex-end; padding:14px 22px; border-top:1px solid #f3f4f6; background:#f9fafb; }
            .fplms-cl-modal-cancel  { padding:8px 20px; background:#fff; color:#374151; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; font-weight:500; cursor:pointer; transition:all .2s; }
            .fplms-cl-modal-cancel:hover { background:#f3f4f6; }
            .fplms-cl-modal-confirm { padding:8px 20px; background:#dc2626; color:#fff; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; transition:background .2s; }
            .fplms-cl-modal-confirm:hover { background:#b91c1c; }

            /* ── Instructor modal (blue variant) ── */
            .fplms-cl-inst-modal-header { padding:18px 22px; border-bottom:1px solid #dbeafe; background:#eff6ff; display:flex; align-items:center; gap:10px; }
            .fplms-cl-inst-modal-header svg { width:22px; height:22px; fill:#2563eb; flex-shrink:0; }
            .fplms-cl-inst-modal-header h3 { margin:0; font-size:16px; font-weight:700; color:#1e3a8a; }
            .fplms-cl-inst-modal-course { padding:10px 14px; background:#f9fafb; border-radius:8px; border-left:3px solid #2563eb; font-weight:600; color:#111827; margin:12px 0; }
            .fplms-cl-inst-modal-confirm { padding:8px 20px; background:#2563eb; color:#fff; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; transition:background .2s; }
            .fplms-cl-inst-modal-confirm:hover { background:#1d4ed8; }

            @media (max-width:900px) {
                .fplms-ct { min-width:860px; }
                .fplms-topbar-c { flex-direction:column; align-items:flex-start; }
                .fplms-topbar-right-c { width:100%; flex-wrap:wrap; }
                .fplms-cl-search-wrap { flex:1; min-width:100%; max-width:100%; }
                .fplms-cl-search-input { width:100%; box-sizing:border-box; }
            }
        </style>

        <div class="fplms-course-list-wrapper">

            <?php if ( $deleted_n > 0 ) : ?>
            <div class="fplms-cl-toast fplms-cl-toast-green fplms-cl-toast-auto">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 13.17l7.59-7.59L19 8l-9 9z"/></svg>
                <span><strong><?php echo esc_html( $deleted_n ); ?> curso(s) eliminado(s) permanentemente.</strong><?php if ( $had_structures_n > 0 ) : ?> <em style="font-size:12px;opacity:.85;">(<?php echo esc_html( $had_structures_n ); ?> tenía<?php echo $had_structures_n > 1 ? 'n' : ''; ?> estructuras asignadas que también fueron desvinculadas.)</em><?php endif; ?></span>
                <button type="button" class="fplms-cl-toast-close" onclick="this.parentElement.remove()">&#x2715;</button>
            </div>
            <?php endif; ?>
            <?php if ( 'instructor_assigned' === $notice ) : ?>
            <div class="fplms-cl-toast fplms-cl-toast-blue fplms-cl-toast-auto">
                <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                <span><strong>Docente asignado correctamente.</strong> El cambio fue registrado en la bitácora de auditoría.</span>
                <button type="button" class="fplms-cl-toast-close" onclick="this.parentElement.remove()">&#x2715;</button>
            </div>
            <?php endif; ?>
            <?php if ( in_array( $notice, [ 'course_activated', 'courses_activated' ], true ) ) : ?>
            <div class="fplms-cl-toast fplms-cl-toast-green fplms-cl-toast-auto">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 13.17l7.59-7.59L19 8l-9 9z"/></svg>
                <span><strong><?php echo esc_html( $notice_count ); ?> curso(s) activado(s) correctamente.</strong> Ahora son visibles para todos los roles.</span>
                <button type="button" class="fplms-cl-toast-close" onclick="this.parentElement.remove()">&#x2715;</button>
            </div>
            <?php endif; ?>
            <?php if ( in_array( $notice, [ 'course_deactivated', 'courses_deactivated' ], true ) ) : ?>
            <div class="fplms-cl-toast fplms-cl-toast-red fplms-cl-toast-auto">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8 0-1.85.63-3.55 1.69-4.9L16.9 18.31C15.55 19.37 13.85 20 12 20zm6.31-3.1L7.1 5.69C8.45 4.63 10.15 4 12 4c4.42 0 8 3.58 8 8 0 1.85-.63 3.55-1.69 4.9z"/></svg>
                <span><strong><?php echo esc_html( $notice_count ); ?> curso(s) desactivado(s).</strong> No son visibles para ningún rol fuera del administrador.</span>
                <button type="button" class="fplms-cl-toast-close" onclick="this.parentElement.remove()">&#x2715;</button>
            </div>
            <?php endif; ?>

            <!-- Page header -->
            <div class="fplms-cl-page-header">
                <div>
                    <div class="fplms-cl-title-row">
                        <div class="fplms-cl-page-icon">
                            <svg viewBox="0 0 24 24"><path d="M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S2.45 4.9 1 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C3.1 20.45 5.05 20 6.5 20c1.95 0 4.05.4 5.5 1.5 1.35-.85 3.8-1.5 5.5-1.5 1.65 0 3.35.3 4.75 1.05.1.05.15.05.25.05.25 0 .5-.25.5-.5V6c-.6-.45-1.25-.75-2-.85V5zm0 13.5c-1.1-.35-2.3-.5-3.5-.5-1.7 0-4.15.65-5.5 1.5V8c1.35-.85 3.8-1.5 5.5-1.5 1.2 0 2.4.15 3.5.5v11.5z"/></svg>
                        </div>
                        <h2>Gestión de Cursos</h2>
                    </div>
                    <p class="fplms-cl-page-subtitle"><?php echo esc_html( $total ); ?> curso<?php echo 1 !== $total ? 's' : ''; ?> en total</p>
                </div>

            </div>

            <!-- Table card -->
            <div class="fplms-table-card-c">

                <!-- TOP BAR -->
                <div class="fplms-topbar-c">
                    <div class="fplms-topbar-left-c">
                        Mostrar
                        <select id="fplms-cl-per-page" class="fplms-entries-sel-c">
                            <option value="10">10</option>
                            <option value="20" selected>20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        entradas
                    </div>
                    <div class="fplms-topbar-right-c">
                        <select id="fplms-cl-filter-channel" class="fplms-cl-filter-sel">
                            <option value="">Todos los canales</option>
                            <?php foreach ( $filter_channels as $fid => $fname ) : ?>
                                <option value="<?php echo esc_attr( $fid ); ?>"><?php echo esc_html( $fname ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="fplms-cl-filter-branch" class="fplms-cl-filter-sel">
                            <option value="">Todas las sucursales</option>
                            <?php foreach ( $filter_branches as $fid => $fname ) : ?>
                                <option value="<?php echo esc_attr( $fid ); ?>"><?php echo esc_html( $fname ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="fplms-cl-filter-status" class="fplms-cl-filter-sel">
                            <option value="">Todos los estados</option>
                            <option value="publish">&#x2713; Activos</option>
                            <option value="draft">⦸ Inactivos</option>
                        </select>
                        <div class="fplms-cl-search-wrap">
                            <input type="text" id="fplms-cl-search" class="fplms-cl-search-input" placeholder="Buscar por título, ID, docente...">
                            <button type="button" class="fplms-cl-search-clear" id="fplms-cl-search-clear" title="Limpiar búsqueda">&#x2715;</button>
                        </div>
                        <div id="fplms-cl-active-filters" class="fplms-cl-active-filters" style="display:none;">
                            <span class="fplms-cl-active-badge" id="fplms-cl-active-badge"></span>
                            <button type="button" onclick="fplmsClClearAllFilters()" class="fplms-cl-filter-clear-all">&#x2715; Limpiar</button>
                        </div>
                        <div class="fplms-cl-dl-wrap" id="fplms-cl-dl-wrap">
                            <button type="button" class="fplms-cl-dl-btn" onclick="fplmsClToggleDl(event)">
                                <svg viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                                Descargar
                                <svg viewBox="0 0 24 24" style="width:12px;height:12px;margin-left:2px;fill:#6b7280;"><path d="M7 10l5 5 5-5z"/></svg>
                            </button>
                            <div class="fplms-cl-dl-dropdown" id="fplms-cl-dl-dropdown">
                                <button type="button" class="fplms-cl-dl-item" onclick="fplmsClExportXLS()">
                                    <svg viewBox="0 0 24 24" style="fill:#217346;"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm-1 7V3.5L18.5 9H13zM7 17l2-3-2-3h1.7l1.3 2 1.3-2H13l-2 3 2 3h-1.7L10 18l-1.3 2H7z"/></svg>
                                    Excel (.xls)
                                </button>
                                <button type="button" class="fplms-cl-dl-item" onclick="fplmsClExportPDF()">
                                    <svg viewBox="0 0 24 24" style="fill:#e53e3e;"><path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5V11H19v2h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zM13 11h1V8.5h-1V11z"/></svg>
                                    PDF
                                </button>
                            </div>
                        </div>
                        <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . FairPlay_LMS_Config::MS_PT_COURSE ) ); ?>" class="fplms-cl-create-btn">
                            <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                            Crear Nuevo Curso
                        </a>
                    </div>
                </div>

                <!-- BULK ACTIONS BAR (hidden until checkboxes selected) -->
                <div class="fplms-cl-bulk-bar" id="fplms-cl-bulk-bar">
                    <span class="fplms-cl-bulk-label">Acciones masivas</span>
                    <span class="fplms-cl-bulk-badge" id="fplms-cl-bulk-count">0</span>
                    seleccionados
                    <select class="fplms-cl-bulk-select" id="fplms-cl-bulk-select">
                        <option value="">— Seleccionar acción —</option>
                        <option value="bulk_activate">Activar cursos</option>
                        <option value="bulk_deactivate">Desactivar cursos</option>
                        <option value="bulk_delete">Eliminar</option>
                    </select>
                    <button type="button" class="fplms-cl-bulk-apply" onclick="fplmsClApplyBulk()">Aplicar</button>
                </div>

                <div class="fplms-ct-wrapper">
                    <table class="fplms-ct" id="fplms-cl-table">
                            <thead>
                                <tr>
                                    <th style="width:36px;padding-left:18px;"><input type="checkbox" id="fplms-cl-select-all"></th>
                                    <th>Curso</th>
                                    <th>Docente</th>
                                    <th>Fecha</th>
                                    <th>Estructuras</th>
                                    <th style="width:215px;">Asignar Docente</th>
                                    <th style="text-align:center;width:145px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ( empty( $courses ) ) : ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;padding:52px;color:#9ca3af;">
                                        <svg viewBox="0 0 24 24" style="width:44px;height:44px;fill:#e5e7eb;display:block;margin:0 auto 14px;"><path d="M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S2.45 4.9 1 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C3.1 20.45 5.05 20 6.5 20c1.95 0 4.05.4 5.5 1.5 1.35-.85 3.8-1.5 5.5-1.5 1.65 0 3.35.3 4.75 1.05.1.05.15.05.25.05.25 0 .5-.25.5-.5V6c-.6-.45-1.25-.75-2-.85V5z"/></svg>
                                        No hay cursos disponibles todavía.
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ( $courses as $course ) :
                                    $modules_url    = add_query_arg( [ 'page' => 'fplms-courses', 'view' => 'modules',    'course_id' => $course->ID ], admin_url( 'admin.php' ) );
                                    $lessons_url    = add_query_arg( [ 'page' => 'fplms-courses', 'view' => 'lessons',    'course_id' => $course->ID ], admin_url( 'admin.php' ) );
                                    $structures_url = add_query_arg( [ 'page' => 'fplms-courses', 'view' => 'structures', 'course_id' => $course->ID ], admin_url( 'admin.php' ) );
                                    // Construir URL de edición directamente para evitar que MasterStudy filtre el enlace
                                    // según autoría — los admins deben poder editar cualquier curso.
                                    $edit_url       = admin_url( 'post.php?post=' . $course->ID . '&action=edit' );

                                    $teacher_id = (int) get_post_meta( $course->ID, FairPlay_LMS_Config::MS_META_COURSE_TEACHER, true );
                                    if ( ! $teacher_id ) {
                                        $teacher_id = (int) get_post_meta( $course->ID, 'instructor_id', true );
                                    }
                                    $teacher_name = $teacher_id ? get_the_author_meta( 'display_name', $teacher_id ) : '';

                                    $course_structures = $this->get_course_structures( $course->ID );
                                    $fecha             = date_i18n( 'd/m/Y', strtotime( $course->post_date ) );
                                    $is_active         = 'publish' === $course->post_status;
                                    $status_label      = $is_active ? 'Publicado' : 'Borrador';
                                    $status_class      = $is_active ? 'fplms-cs-publish' : 'fplms-cs-draft';
                                    $search_str        = strtolower( get_the_title( $course ) . ' ' . $course->ID . ' ' . $teacher_name );
                                ?>
                                <tr class="fplms-cl-row<?php echo $is_active ? '' : ' row-inactive'; ?>"
                                    data-course-id="<?php echo esc_attr( $course->ID ); ?>"
                                    data-search="<?php echo esc_attr( $search_str ); ?>"
                                    data-status="<?php echo $is_active ? 'publish' : 'draft'; ?>"
                                    data-channels="<?php echo esc_attr( implode( '|', array_filter( array_map( 'strval', $course_structures['channels'] ) ) ) ); ?>"
                                    data-branches="<?php echo esc_attr( implode( '|', array_filter( array_map( 'strval', $course_structures['branches'] ) ) ) ); ?>">
                                    <td style="padding-left:18px;">
                                        <input type="checkbox" class="fplms-cl-cb" value="<?php echo esc_attr( $course->ID ); ?>">
                                    </td>
                                    <td>
                                        <div class="fplms-ct-title">
                                            <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $course->ID . '&action=edit' ) ); ?>" style="color:inherit;text-decoration:none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'"><?php echo esc_html( get_the_title( $course ) ); ?></a>
                                        </div>
                                        <div class="fplms-ct-meta">ID: <?php echo esc_html( $course->ID ); ?></div>
                                        <span class="fplms-cs-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
                                    </td>
                                    <td>
                                        <div style="font-weight:500;color:#1f2937;"><?php echo esc_html( $teacher_name ?: '— Sin asignar —' ); ?></div>
                                    </td>
                                    <td><?php echo esc_html( $fecha ); ?></td>
                                    <td>
                                        <div class="fplms-ct-struct-tags">
                                            <?php echo $this->format_course_structures_compact( $course_structures ); // phpcs:ignore -- HTML with SVG ?>
                                        </div>
                                    </td>
                                    <td>
                                        <form method="post" class="fplms-ct-inst-form" action="<?php echo esc_url( admin_url( 'admin.php?page=fplms-courses' ) ); ?>">
                                            <?php wp_nonce_field( 'fplms_courses_save', 'fplms_courses_nonce' ); ?>
                                            <input type="hidden" name="fplms_courses_action" value="assign_instructor">
                                            <input type="hidden" name="fplms_course_id" value="<?php echo esc_attr( $course->ID ); ?>">
                                            <select name="fplms_instructor_id">
                                                <option value="0">— Sin docente —</option>
                                                <?php foreach ( $instructors as $inst ) : ?>
                                                    <option value="<?php echo esc_attr( $inst->ID ); ?>" <?php selected( $teacher_id, $inst->ID ); ?>>
                                                        <?php echo esc_html( $inst->display_name ); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="fplms-ct-save-btn" title="Guardar docente"
                                                    onclick="fplmsClShowInstructorModal(this)">
                                                <svg viewBox="0 0 24 24"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="fplms-ct-actions">
                                            <a href="<?php echo esc_url( $structures_url ); ?>" class="fplms-ct-btn structures" data-tip="Estructuras">
                                                <svg viewBox="0 0 24 24"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                                            </a>
                                            <?php if ( $is_active ) : ?>
                                            <button type="button" class="fplms-ct-btn deactivate" data-tip="Desactivar"
                                                    onclick="fplmsClToggleStatus(<?php echo (int)$course->ID; ?>, 'draft', '<?php echo esc_js( get_the_title( $course ) ); ?>')">
                                                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8 0-1.85.63-3.55 1.69-4.9L16.9 18.31C15.55 19.37 13.85 20 12 20zm6.31-3.1L7.1 5.69C8.45 4.63 10.15 4 12 4c4.42 0 8 3.58 8 8 0 1.85-.63 3.55-1.69 4.9z"/></svg>
                                            </button>
                                            <?php else : ?>
                                            <button type="button" class="fplms-ct-btn activate" data-tip="Activar"
                                                    onclick="fplmsClToggleStatus(<?php echo (int)$course->ID; ?>, 'publish', '<?php echo esc_js( get_the_title( $course ) ); ?>')">
                                                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                                            </button>
                                            <?php endif; ?>
                                            <a href="<?php echo esc_url( $edit_url ); ?>" class="fplms-ct-btn edit" data-tip="Editar">
                                                <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                            </a>
                                            <button type="button" class="fplms-ct-btn delete" data-tip="Eliminar"
                                                    onclick="fplmsClShowDeleteModal(<?php echo (int) $course->ID; ?>, '<?php echo esc_js( get_the_title( $course ) ); ?>')">
                                                <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                <!-- PAGINATION -->
                <div id="fplms-cl-pagination"></div>

            </div><!-- .fplms-table-card-c -->
        </div><!-- .fplms-course-list-wrapper -->

        <!-- HIDDEN BULK FORM (standalone outside card — fixes nested-forms bug) -->
        <form method="post" id="fplms-cl-bulk-form" style="display:none;" action="<?php echo esc_url( admin_url( 'admin.php?page=fplms-courses' ) ); ?>">
            <?php wp_nonce_field( 'fplms_courses_save', 'fplms_courses_nonce' ); ?>
            <input type="hidden" name="fplms_courses_action" id="fplms-cl-bulk-action-input" value="">
            <div id="fplms-cl-bulk-ids"></div>
        </form>

        <!-- DELETE CONFIRM MODAL -->
        <div id="fplms-cl-delete-modal" class="fplms-cl-modal-overlay">
            <div class="fplms-cl-modal">
                <div class="fplms-cl-modal-header">
                    <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                    <h3>Eliminar curso</h3>
                </div>
                <div class="fplms-cl-modal-body">
                    <p><strong>Advertencia:</strong> Esta acción es <strong style="color:#dc2626;">permanente</strong> y no se puede deshacer.</p>
                    <div class="fplms-cl-modal-course" id="fplms-cl-delete-name"></div>
                    <p style="color:#4b5563;">Se eliminará el curso y todos sus datos asociados.</p>
                </div>
                <div class="fplms-cl-modal-footer">
                    <button type="button" class="fplms-cl-modal-cancel" onclick="fplmsClCloseDeleteModal()">Cancelar</button>
                    <button type="button" class="fplms-cl-modal-confirm" onclick="fplmsClConfirmDelete()">Eliminar permanentemente</button>
                </div>
            </div>
        </div>

        <!-- INSTRUCTOR ASSIGN CONFIRM MODAL -->
        <div id="fplms-cl-instructor-modal" class="fplms-cl-modal-overlay">
            <div class="fplms-cl-modal">
                <div class="fplms-cl-inst-modal-header">
                    <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    <h3>Confirmar asignación de docente</h3>
                </div>
                <div class="fplms-cl-modal-body">
                    <p>¿Deseas asignar el siguiente docente al curso?</p>
                    <div class="fplms-cl-inst-modal-course" id="fplms-cl-inst-course-name"></div>
                    <p style="color:#4b5563;margin-top:8px;">
                        <strong>Nuevo docente:</strong> <span id="fplms-cl-inst-teacher-name" style="color:#1d4ed8;font-weight:600;"></span>
                    </p>
                    <p style="color:#6b7280;font-size:13px;">Este cambio quedará registrado en la bitácora de auditoría.</p>
                </div>
                <div class="fplms-cl-modal-footer">
                    <button type="button" class="fplms-cl-modal-cancel" onclick="fplmsClCloseInstructorModal()">Cancelar</button>
                    <button type="button" class="fplms-cl-inst-modal-confirm" onclick="fplmsClConfirmInstructor()">Confirmar asignación</button>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var allRows      = Array.from(document.querySelectorAll('.fplms-cl-row'));
            var filteredRows = allRows.slice();
            var currentPage  = 1;
            var perPage      = 20;

            // ── Filtros: texto + canal + sucursal + estado ─────────────────────
            var searchInput = document.getElementById('fplms-cl-search');
            var clearBtn    = document.getElementById('fplms-cl-search-clear');
            var chanSel     = document.getElementById('fplms-cl-filter-channel');
            var branchSel   = document.getElementById('fplms-cl-filter-branch');
            var statusSel   = document.getElementById('fplms-cl-filter-status');

            function applyFilters() {
                var q      = searchInput.value.trim().toLowerCase();
                var chan    = chanSel    ? chanSel.value    : '';
                var branch  = branchSel  ? branchSel.value  : '';
                var status  = statusSel  ? statusSel.value  : '';

                filteredRows = allRows.filter(function(r) {
                    var matchText   = !q      || r.dataset.search.indexOf(q) !== -1;
                    var matchChan   = !chan   || ('|' + (r.dataset.channels||'') + '|').indexOf('|' + chan + '|')   !== -1;
                    var matchBranch = !branch || ('|' + (r.dataset.branches||'') + '|').indexOf('|' + branch + '|') !== -1;
                    var matchStatus = !status || r.dataset.status === status;
                    return matchText && matchChan && matchBranch && matchStatus;
                });

                clearBtn.classList.toggle('visible', !!searchInput.value);
                currentPage = 1;
                renderPagination();
                updateBulkBar();
                updateActiveBar();
            }

            function updateActiveBar() {
                var hasFilt = searchInput.value || (chanSel && chanSel.value) || (branchSel && branchSel.value) || (statusSel && statusSel.value);
                var bar   = document.getElementById('fplms-cl-active-filters');
                var badge = document.getElementById('fplms-cl-active-badge');
                if (!bar || !badge) return;
                if (hasFilt) {
                    var parts = [];
                    if (searchInput.value) parts.push('"' + searchInput.value + '"');
                    if (chanSel    && chanSel.value)    parts.push('Canal: '     + chanSel.options[chanSel.selectedIndex].text);
                    if (branchSel  && branchSel.value)  parts.push('Sucursal: '  + branchSel.options[branchSel.selectedIndex].text);
                    if (statusSel  && statusSel.value)  parts.push('Estado: '    + statusSel.options[statusSel.selectedIndex].text);
                    badge.textContent = parts.join(' · ') + ' — ' + filteredRows.length + ' resultado(s)';
                    bar.style.display = '';
                } else {
                    bar.style.display = 'none';
                }
            }

            searchInput.addEventListener('input', applyFilters);
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                applyFilters();
            });
            if (chanSel)    chanSel.addEventListener('change',    applyFilters);
            if (branchSel)  branchSel.addEventListener('change',  applyFilters);
            if (statusSel)  statusSel.addEventListener('change',  applyFilters);

            window.fplmsClApplyFilters = applyFilters;
            window.fplmsClClearAllFilters = function() {
                searchInput.value = '';
                if (chanSel)    chanSel.value    = '';
                if (branchSel)  branchSel.value  = '';
                if (statusSel)  statusSel.value  = '';
                applyFilters();
            };

            // ── Per-page selector ────────────────────────────────────────────
            document.getElementById('fplms-cl-per-page').addEventListener('change', function() {
                perPage     = parseInt(this.value) || 20;
                currentPage = 1;
                renderPagination();
            });

            // ── Select all checkbox ──────────────────────────────────────────
            var selectAll = document.getElementById('fplms-cl-select-all');
            selectAll.addEventListener('change', function() {
                var visible = filteredRows.slice((currentPage - 1) * perPage, currentPage * perPage);
                visible.forEach(function(r) {
                    var cb = r.querySelector('.fplms-cl-cb');
                    if (cb) cb.checked = selectAll.checked;
                });
                updateBulkBar();
            });

            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('fplms-cl-cb')) updateBulkBar();
            });

            function updateBulkBar() {
                var count = document.querySelectorAll('.fplms-cl-cb:checked').length;
                document.getElementById('fplms-cl-bulk-count').textContent = count;
                document.getElementById('fplms-cl-bulk-bar').classList.toggle('visible', count > 0);
            }

            // ── Pagination ───────────────────────────────────────────────────
            function renderPagination() {
                var container  = document.getElementById('fplms-cl-pagination');
                var total      = filteredRows.length;
                var totalPages = Math.ceil(total / perPage) || 1;
                if (currentPage > totalPages) currentPage = totalPages;

                allRows.forEach(function(r) { r.style.display = 'none'; });
                var start = (currentPage - 1) * perPage;
                var end   = start + perPage;
                filteredRows.slice(start, end).forEach(function(r) { r.style.display = ''; });

                if (total === 0) {
                    container.innerHTML = '<div class="fplms-cl-pag-wrap"><span class="fplms-cl-pag-info">No se encontraron cursos.</span></div>';
                    return;
                }

                var from = start + 1;
                var to   = Math.min(end, total);
                var info = '<span class="fplms-cl-pag-info">Mostrando ' + from + ' a ' + to + ' de ' + total + ' entradas</span>';

                var btns = '';
                btns += '<button class="fplms-cl-pag-btn" ' + (currentPage === 1 ? 'disabled' : '') + ' onclick="fplmsClPage(' + (currentPage - 1) + ')">← Anterior</button>';

                var maxBtns = 5;
                var sp = Math.max(1, currentPage - 2);
                var ep = Math.min(totalPages, sp + maxBtns - 1);
                if (ep - sp < maxBtns - 1) sp = Math.max(1, ep - maxBtns + 1);

                if (sp > 1) {
                    btns += '<button class="fplms-cl-pag-btn" onclick="fplmsClPage(1)">1</button>';
                    if (sp > 2) btns += '<span style="padding:0 4px;color:#9ca3af;">…</span>';
                }
                for (var i = sp; i <= ep; i++) {
                    btns += '<button class="fplms-cl-pag-btn' + (i === currentPage ? ' active' : '') + '" onclick="fplmsClPage(' + i + ')">' + i + '</button>';
                }
                if (ep < totalPages) {
                    if (ep < totalPages - 1) btns += '<span style="padding:0 4px;color:#9ca3af;">…</span>';
                    btns += '<button class="fplms-cl-pag-btn" onclick="fplmsClPage(' + totalPages + ')">' + totalPages + '</button>';
                }
                btns += '<button class="fplms-cl-pag-btn" ' + (currentPage >= totalPages ? 'disabled' : '') + ' onclick="fplmsClPage(' + (currentPage + 1) + ')">Siguiente →</button>';

                container.innerHTML = '<div class="fplms-cl-pag-wrap">' + info + '<div class="fplms-cl-pag-controls">' + btns + '</div></div>';
            }

            window.fplmsClPage = function(page) {
                var totalPages = Math.ceil(filteredRows.length / perPage) || 1;
                if (page < 1 || page > totalPages) return;
                currentPage = page;
                renderPagination();
                var wrap = document.querySelector('.fplms-ct-wrapper');
                if (wrap) wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
            };

            // ── Init ─────────────────────────────────────────────────────────
            renderPagination();

            // expose filtered rows for export
            window._fplmsClRows = function() {
                return filteredRows;
            };
        });

        // ── Download dropdown ────────────────────────────────────────────────
        window.fplmsClToggleDl = function(e) {
            e.stopPropagation();
            document.getElementById('fplms-cl-dl-dropdown').classList.toggle('open');
        };
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#fplms-cl-dl-wrap')) {
                var dd = document.getElementById('fplms-cl-dl-dropdown');
                if (dd) dd.classList.remove('open');
            }
        });

        // ── Export helpers ───────────────────────────────────────────────────
        function fplmsClGetExportRows() {
            var checked = document.querySelectorAll('.fplms-cl-cb:checked');
            if (checked.length > 0) {
                return Array.from(checked).map(function(cb) { return cb.closest('tr.fplms-cl-row'); }).filter(Boolean);
            }
            return Array.from(document.querySelectorAll('.fplms-cl-row')).filter(function(r) { return r.style.display !== 'none'; });
        }

        function fplmsClExtractRow(row) {
            var cells = row.querySelectorAll('td');
            if (cells.length < 6) return null;
            return {
                title:   (cells[1].querySelector('.fplms-ct-title')  || { textContent: '' }).textContent.trim(),
                id:      (cells[1].querySelector('.fplms-ct-meta')   || { textContent: '' }).textContent.trim(),
                status:  (cells[1].querySelector('.fplms-cs-badge')  || { textContent: '' }).textContent.trim(),
                docente: cells[2].textContent.trim(),
                fecha:   cells[3].textContent.trim(),
                structs: cells[4].textContent.trim().replace(/\s+/g, ' ')
            };
        }

        window.fplmsClExportXLS = function() {
            var rows = fplmsClGetExportRows();
            var hdrs = ['Curso', 'ID', 'Estado', 'Docente', 'Fecha', 'Estructuras'];
            var t = '<table border="1" style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:12px;">';
            t += '<thead><tr>' + hdrs.map(function(h) {
                return '<th style="background:#667eea;color:#fff;padding:8px 12px;font-weight:bold;">' + h + '</th>';
            }).join('') + '</tr></thead><tbody>';
            rows.forEach(function(row) {
                var d = fplmsClExtractRow(row);
                if (!d) return;
                var vals = [d.title, d.id, d.status, d.docente, d.fecha, d.structs];
                t += '<tr>' + vals.map(function(v) {
                    return '<td style="padding:6px 10px;">' + v.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</td>';
                }).join('') + '</tr>';
            });
            t += '</tbody></table>';
            var blob = new Blob(['<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' + t + '</body></html>'], { type: 'application/vnd.ms-excel;charset=utf-8;' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'cursos.xls';
            a.click();
            URL.revokeObjectURL(a.href);
            document.getElementById('fplms-cl-dl-dropdown').classList.remove('open');
        };

        window.fplmsClExportPDF = function() {
            var rows    = fplmsClGetExportRows();
            var hdrs    = ['Curso', 'ID', 'Estado', 'Docente', 'Fecha', 'Estructuras'];
            var checked = document.querySelectorAll('.fplms-cl-cb:checked').length;
            var label   = checked > 0 ? checked + ' seleccionados' : rows.length + ' cursos';
            var t = '<table><thead><tr>' + hdrs.map(function(h) { return '<th>' + h + '</th>'; }).join('') + '</tr></thead><tbody>';
            rows.forEach(function(row) {
                var d = fplmsClExtractRow(row);
                if (!d) return;
                var vals = [d.title, d.id, d.status, d.docente, d.fecha, d.structs];
                t += '<tr>' + vals.map(function(v) {
                    return '<td>' + v.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</td>';
                }).join('') + '</tr>';
            });
            t += '</tbody></table>';
            var win = window.open('', '_blank', 'width=960,height=700');
            win.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Cursos</title><style>'
                + 'body{font-family:Arial,sans-serif;font-size:12px;margin:24px;color:#1f2937;}'
                + 'h2{font-size:16px;margin-bottom:14px;}'
                + 'p.meta{font-size:11px;color:#6b7280;margin-bottom:16px;}'
                + 'table{width:100%;border-collapse:collapse;}'
                + 'th{background:#667eea;color:#fff;padding:8px 10px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.5px;}'
                + 'td{padding:7px 10px;border-bottom:1px solid #e5e7eb;}'
                + 'tr:nth-child(even) td{background:#f9fafb;}'
                + '.btn{margin-top:18px;padding:9px 22px;background:#667eea;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;}'
                + '@media print{.btn{display:none;}}'
                + '</style></head><body>'
                + '<h2>Cursos</h2><p class="meta">' + label + ' &mdash; ' + new Date().toLocaleDateString('es-ES') + '</p>'
                + t
                + '<br><button class="btn" onclick="window.print()">Imprimir / Guardar como PDF</button>'
                + '</body></html>');
            win.document.close();
            document.getElementById('fplms-cl-dl-dropdown').classList.remove('open');
        };

        // ── Bulk submit helper ───────────────────────────────────────────────
        function fplmsClSubmitBulk(action, ids) {
            var container = document.getElementById('fplms-cl-bulk-ids');
            container.innerHTML = '';
            ids.forEach(function(id) {
                var inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = 'fplms_bulk_course_ids[]';
                inp.value = id;
                container.appendChild(inp);
            });
            document.getElementById('fplms-cl-bulk-action-input').value = action;
            document.getElementById('fplms-cl-bulk-form').submit();
        }

        // ── Toggle individual course status ─────────────────────────────
        window.fplmsClToggleStatus = function(courseId, newStatus, courseTitle) {
            var label = 'publish' === newStatus ? 'activar' : 'desactivar';
            var msg   = 'publish' === newStatus
                ? '¿Activar el curso "' + courseTitle + '"? Será visible para todos los roles.'
                : '¿Desactivar el curso "' + courseTitle + '"? Dejará de ser visible para todos los roles excepto el administrador.';
            if (!confirm(msg)) return;
            var container = document.getElementById('fplms-cl-bulk-ids');
            container.innerHTML = '';
            var inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'fplms_bulk_course_ids[]'; inp.value = courseId;
            container.appendChild(inp);
            var nsInp = document.createElement('input');
            nsInp.type = 'hidden'; nsInp.name = 'fplms_new_status'; nsInp.value = newStatus;
            container.appendChild(nsInp);
            document.getElementById('fplms-cl-bulk-action-input').value = 'toggle_course_status';
            document.getElementById('fplms-cl-bulk-form').submit();
        };

        // ── Bulk action ──────────────────────────────────────────────────────
        window.fplmsClApplyBulk = function() {
            var action  = document.getElementById('fplms-cl-bulk-select').value;
            if (!action) { alert('Selecciona una acción.'); return; }
            var checked = document.querySelectorAll('.fplms-cl-cb:checked');
            if (!checked.length) { alert('Selecciona al menos un curso.'); return; }
            var ids = Array.from(checked).map(function(cb) { return cb.value; });
            if (action === 'bulk_delete') {
                if (!confirm('¿Eliminar permanentemente ' + ids.length + ' curso(s)? Esta acción no se puede deshacer.')) return;
                fplmsClSubmitBulk('bulk_delete', ids);
            } else if (action === 'bulk_activate') {
                if (!confirm('¿Activar ' + ids.length + ' curso(s)? Serán visibles para todos los roles.')) return;
                fplmsClSubmitBulk('bulk_activate', ids);
            } else if (action === 'bulk_deactivate') {
                if (!confirm('¿Desactivar ' + ids.length + ' curso(s)? No serán visibles para ningún rol fuera del administrador.')) return;
                fplmsClSubmitBulk('bulk_deactivate', ids);
            }
        };

        // ── Individual delete modal ──────────────────────────────────────────
        var _fplmsClDelId = null;

        window.fplmsClShowDeleteModal = function(courseId, courseTitle) {
            _fplmsClDelId = courseId;
            document.getElementById('fplms-cl-delete-name').textContent = courseTitle;
            document.getElementById('fplms-cl-delete-modal').classList.add('active');
        };
        window.fplmsClCloseDeleteModal = function() {
            document.getElementById('fplms-cl-delete-modal').classList.remove('active');
            _fplmsClDelId = null;
        };
        window.fplmsClConfirmDelete = function() {
            if (!_fplmsClDelId) return;
            document.getElementById('fplms-cl-delete-modal').classList.remove('active');
            fplmsClSubmitBulk('bulk_delete', [String(_fplmsClDelId)]);
        };
        document.addEventListener('click', function(e) {
            if (e.target.id === 'fplms-cl-delete-modal') fplmsClCloseDeleteModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') { fplmsClCloseDeleteModal(); fplmsClCloseInstructorModal(); }
        });

        // ── Instructor assign modal ──────────────────────────────────────────
        var _fplmsClInstForm = null;

        window.fplmsClShowInstructorModal = function(btn) {
            var form = btn.closest('form');
            if (!form) return;
            _fplmsClInstForm = form;
            var select = form.querySelector('select[name="fplms_instructor_id"]');
            var selectedOpt = select ? select.options[select.selectedIndex] : null;
            var teacherName = selectedOpt ? selectedOpt.text.trim() : '— Sin docente —';
            // Get course title from the row
            var row = form.closest('tr');
            var titleEl = row ? row.querySelector('.fplms-ct-title a') : null;
            var courseName = titleEl ? titleEl.textContent.trim() : '';
            document.getElementById('fplms-cl-inst-course-name').textContent = courseName;
            document.getElementById('fplms-cl-inst-teacher-name').textContent = teacherName;
            document.getElementById('fplms-cl-instructor-modal').classList.add('active');
        };
        window.fplmsClCloseInstructorModal = function() {
            document.getElementById('fplms-cl-instructor-modal').classList.remove('active');
            _fplmsClInstForm = null;
        };
        window.fplmsClConfirmInstructor = function() {
            if (!_fplmsClInstForm) return;
            document.getElementById('fplms-cl-instructor-modal').classList.remove('active');
            _fplmsClInstForm.submit();
        };
        document.addEventListener('click', function(e) {
            if (e.target.id === 'fplms-cl-instructor-modal') fplmsClCloseInstructorModal();
        });

        // ── Toast auto-dismiss ───────────────────────────────────────────────
        document.querySelectorAll('.fplms-cl-toast-auto').forEach(function(t) {
            setTimeout(function() {
                t.style.transition = 'opacity .5s ease';
                t.style.opacity = '0';
                setTimeout(function() { if (t.parentNode) t.parentNode.removeChild(t); }, 520);
            }, 5000);
        });
        </script>
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

        // Obtener términos activos disponibles por nivel
        $cities = $this->structures ? $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CITY ) : [];
        
        // Si el curso ya tiene estructuras, cargar también las actuales para mostrarlas
        $companies = [];
        $channels = [];
        $branches = [];
        $roles = [];
        
        // Cargar empresas actuales si hay ciudades seleccionadas
        if ( ! empty( $current_structures['cities'] ) && $this->structures ) {
            $companies_terms = $this->structures->get_terms_by_cities( FairPlay_LMS_Config::TAX_COMPANY, $current_structures['cities'] );
            foreach ( $companies_terms as $term ) {
                $companies[ $term->term_id ] = $term->name;
            }
        }
        
        // Cargar canales actuales si hay empresas seleccionadas
        if ( ! empty( $current_structures['companies'] ) && $this->structures ) {
            $channels_terms = $this->structures->get_channels_by_companies( FairPlay_LMS_Config::TAX_CHANNEL, $current_structures['companies'] );
            foreach ( $channels_terms as $term ) {
                $channels[ $term->term_id ] = $term->name;
            }
        }
        
        // Cargar sucursales actuales si hay canales seleccionados
        if ( ! empty( $current_structures['channels'] ) && $this->structures ) {
            $branches_terms = $this->structures->get_branches_by_channels( FairPlay_LMS_Config::TAX_BRANCH, $current_structures['channels'] );
            foreach ( $branches_terms as $term ) {
                $branches[ $term->term_id ] = $term->name;
            }
        }
        
        // Cargar cargos actuales si hay sucursales seleccionadas
        if ( ! empty( $current_structures['branches'] ) && $this->structures ) {
            $roles_terms = $this->structures->get_roles_by_branches( FairPlay_LMS_Config::TAX_ROLE, $current_structures['branches'] );
            foreach ( $roles_terms as $term ) {
                $roles[ $term->term_id ] = $term->name;
            }
        }
        
        ?>
        <style>
            .fplms-cascade-info {
                background: #e7f3ff;
                border-left: 4px solid #2271b1;
                padding: 15px;
                margin: 20px 0;
                border-radius: 4px;
            }
            .fplms-cascade-info strong {
                color: #135e96;
            }
            .fplms-structure-container {
                min-height: 60px;
                padding: 10px;
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .fplms-structure-container label {
                display: block;
                padding: 6px 10px;
                margin: 3px 0;
                background: #fff;
                border-radius: 3px;
                cursor: pointer;
                transition: background 0.2s;
            }
            .fplms-structure-container label:hover {
                background: #e7f3ff;
            }
            .fplms-structure-container input[type="checkbox"] {
                margin-right: 8px;
                vertical-align: middle;
            }
            .fplms-loading {
                color: #999;
                font-style: italic;
                padding: 10px;
            }
            .fplms-empty-state {
                color: #666;
                font-style: italic;
                padding: 10px;
            }
        </style>
        
        <div class="fplms-cascade-info">
            <h3 style="margin-top:0;display:flex;align-items:center;gap:8px;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="#135e96"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg> Asignación Inteligente en Cascada</h3>
            <p><strong>Cómo funciona:</strong></p>
            <ul style="margin-left:20px;list-style:none;padding:0;">
                <li style="margin:5px 0;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="15" height="15" style="vertical-align:middle;fill:#e53935"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg> <strong>Selecciona Ciudad(es)</strong> → Se cargan automáticamente todas las empresas, canales, sucursales y cargos de esas ciudades</li>
                <li style="margin:5px 0;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="15" height="15" style="vertical-align:middle;fill:#1565c0"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg> <strong>Selecciona Empresa(s)</strong> → Se cargan automáticamente todos los canales, sucursales y cargos de esas empresas</li>
                <li style="margin:5px 0;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="15" height="15" style="vertical-align:middle;fill:#0277bd"><path d="M20 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg> <strong>Selecciona Canal(es)</strong> → Se cargan automáticamente todas las sucursales y cargos de esos canales</li>
                <li style="margin:5px 0;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="15" height="15" style="vertical-align:middle;fill:#558b2f"><path d="M15 11V5l-3-3-3 3v2H3v14h18V11h-6zm-8 8H5v-2h2v2zm0-4H5v-2h2v2zm0-4H5V9h2v2zm6 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V9h2v2zm0-4h-2V5h2v2zm6 12h-2v-2h2v2zm0-4h-2v-2h2v2z"/></svg> <strong>Selecciona Sucursal(es)</strong> → Se cargan automáticamente todos los cargos de esas sucursales</li>
            </ul>
            <p><strong><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="15" height="15" style="vertical-align:middle;fill:#2e7d32"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14l-4-4 1.41-1.41L10 13.17l6.59-6.59L18 8l-8 8z"/></svg> Puedes seleccionar una o más opciones en cada nivel.</strong> Las opciones cargadas se pre-seleccionan automáticamente, pero puedes desmarcar las que no necesites.</p>
        </div>

        <form method="post" id="fplms-structures-form">
            <?php wp_nonce_field( 'fplms_courses_save', 'fplms_courses_nonce' ); ?>
            <input type="hidden" name="fplms_courses_action" value="assign_structures">
            <input type="hidden" name="fplms_course_id" value="<?php echo esc_attr( $course_id ); ?>">

            <table class="form-table">
                <tr>
                    <th scope="row"><label><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" style="vertical-align:middle;fill:#e53935"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg> Ciudades</label></th>
                    <td>
                        <div class="fplms-structure-container" id="fplms-cities-container">
                            <?php if ( ! empty( $cities ) ) : ?>
                                <?php foreach ( $cities as $term_id => $term_name ) : ?>
                                    <label>
                                        <input type="checkbox" 
                                               name="fplms_course_cities[]" 
                                               class="fplms-cascade-checkbox"
                                               data-level="cities"
                                               value="<?php echo esc_attr( $term_id ); ?>" 
                                               <?php checked( in_array( $term_id, $current_structures['cities'], true ) ); ?>>
                                        <?php echo esc_html( $term_name ); ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p class="fplms-empty-state">No hay ciudades disponibles.</p>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" style="vertical-align:middle;fill:#1565c0"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg> Empresas</label></th>
                    <td>
                        <div class="fplms-structure-container" id="fplms-companies-container">
                            <?php if ( ! empty( $companies ) ) : ?>
                                <?php foreach ( $companies as $term_id => $term_name ) : ?>
                                    <label>
                                        <input type="checkbox" 
                                               name="fplms_course_companies[]" 
                                               class="fplms-cascade-checkbox"
                                               data-level="companies"
                                               value="<?php echo esc_attr( $term_id ); ?>" 
                                               <?php checked( in_array( $term_id, $current_structures['companies'], true ) ); ?>>
                                        <?php echo esc_html( $term_name ); ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p class="fplms-empty-state">Selecciona una ciudad primero para cargar empresas.</p>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" style="vertical-align:middle;fill:#0277bd"><path d="M20 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg> Canales / Franquicias</label></th>
                    <td>
                        <div class="fplms-structure-container" id="fplms-channels-container">
                            <?php if ( ! empty( $channels ) ) : ?>
                                <?php foreach ( $channels as $term_id => $term_name ) : ?>
                                    <label>
                                        <input type="checkbox" 
                                               name="fplms_course_channels[]" 
                                               class="fplms-cascade-checkbox"
                                               data-level="channels"
                                               value="<?php echo esc_attr( $term_id ); ?>" 
                                               <?php checked( in_array( $term_id, $current_structures['channels'], true ) ); ?>>
                                        <?php echo esc_html( $term_name ); ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p class="fplms-empty-state">Selecciona una empresa primero para cargar canales.</p>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" style="vertical-align:middle;fill:#558b2f"><path d="M15 11V5l-3-3-3 3v2H3v14h18V11h-6zm-8 8H5v-2h2v2zm0-4H5v-2h2v2zm0-4H5V9h2v2zm6 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V9h2v2zm0-4h-2V5h2v2zm6 12h-2v-2h2v2zm0-4h-2v-2h2v2z"/></svg> Sucursales</label></th>
                    <td>
                        <div class="fplms-structure-container" id="fplms-branches-container">
                            <?php if ( ! empty( $branches ) ) : ?>
                                <?php foreach ( $branches as $term_id => $term_name ) : ?>
                                    <label>
                                        <input type="checkbox" 
                                               name="fplms_course_branches[]" 
                                               class="fplms-cascade-checkbox"
                                               data-level="branches"
                                               value="<?php echo esc_attr( $term_id ); ?>" 
                                               <?php checked( in_array( $term_id, $current_structures['branches'], true ) ); ?>>
                                        <?php echo esc_html( $term_name ); ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p class="fplms-empty-state">Selecciona un canal primero para cargar sucursales.</p>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" style="vertical-align:middle;fill:#6a1b9a"><path d="M20 6h-2.18c.07-.44.18-.88.18-1.35C18 2.53 16.5 1 14.65 1c-1.07 0-2.02.5-2.65 1.28L12 2.93l-.5-.65C10.87 1.5 9.96 1 8.85 1 7 1 5.5 2.53 5.5 4.65c0 .47.11.9.18 1.35H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-5.36-3c.98 0 1.86.83 1.86 1.65-.01.45-.1.9-.3 1.35h-3.12c-.2-.45-.3-.9-.3-1.35 0-.82.88-1.65 1.86-1.65zM8.85 3c.98 0 1.86.83 1.86 1.65 0 .45-.1.9-.3 1.35H7.29c-.2-.45-.3-.9-.3-1.35C6.99 3.83 7.87 3 8.85 3zM20 20H4V8h4v1c0 .55.45 1 1 1s1-.45 1-1V8h4v1c0 .55.45 1 1 1s1-.45 1-1V8h4v12z"/></svg> Cargos</label></th>
                    <td>
                        <div class="fplms-structure-container" id="fplms-roles-container">
                            <?php if ( ! empty( $roles ) ) : ?>
                                <?php foreach ( $roles as $term_id => $term_name ) : ?>
                                    <label>
                                        <input type="checkbox" 
                                               name="fplms_course_roles[]" 
                                               class="fplms-cascade-checkbox"
                                               data-level="roles"
                                               value="<?php echo esc_attr( $term_id ); ?>" 
                                               <?php checked( in_array( $term_id, $current_structures['roles'], true ) ); ?>>
                                        <?php echo esc_html( $term_name ); ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p class="fplms-empty-state">Selecciona una sucursal primero para cargar cargos.</p>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" style="vertical-align:middle;fill:currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg> Guardar estructuras y notificar usuarios</button>
            </p>
        </form>
        
        <script>
        jQuery(document).ready(function($) {
            /**
             * Sistema de Cascada Dinámica para Asignación de Estructuras
             * Este script maneja la carga automática de estructuras relacionadas
             * cuando el usuario selecciona elementos de nivel superior
             */
            
            const ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
            const cascadeNonce = '<?php echo esc_js( wp_create_nonce( 'fplms_cascade' ) ); ?>';
            
            // Contenedores de cada nivel
            const containers = {
                cities: $('#fplms-cities-container'),
                companies: $('#fplms-companies-container'),
                channels: $('#fplms-channels-container'),
                branches: $('#fplms-branches-container'),
                roles: $('#fplms-roles-container')
            };
            
            // Mapeo de niveles y sus descendientes
            const levelHierarchy = {
                cities: ['companies', 'channels', 'branches', 'roles'],
                companies: ['channels', 'branches', 'roles'],
                channels: ['branches', 'roles'],
                branches: ['roles']
            };
            
            /**
             * Maneja el cambio en checkboxes de un nivel específico
             */
            function handleLevelChange(level) {
                const $checkboxes = containers[level].find('input[type="checkbox"]');
                const selectedIds = [];
                
                $checkboxes.each(function() {
                    if ($(this).is(':checked')) {
                        selectedIds.push($(this).val());
                    }
                });
                
                if (selectedIds.length === 0) {
                    // Si no hay selección, limpiar niveles descendientes
                    clearDescendantLevels(level);
                    return;
                }
                
                // Cargar estructuras en cascada
                loadCascadeStructures(level, selectedIds);
            }
            
            /**
             * Carga estructuras en cascada mediante AJAX
             */
            function loadCascadeStructures(level, selectedIds) {
                // Mostrar indicador de carga en descendientes
                const descendants = levelHierarchy[level] || [];
                descendants.forEach(function(descendantLevel) {
                    containers[descendantLevel].html('<p class="fplms-loading"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" style="vertical-align:middle;fill:#999"><path d="M6 2v6l2 2-2 2v6h12v-6l-2-2 2-2V2H6zm10 14.5V18H8v-1.5l2-2V12h4v2.5l2 2zm-5-6.5-2-2V4h6v4l-2 2h-2z"/></svg> Cargando...</p>');
                });
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'fplms_get_cascade_structures',
                        nonce: cascadeNonce,
                        level: level,
                        selected_ids: JSON.stringify(selectedIds)
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            // Solo actualizar niveles DESCENDIENTES, no el nivel actual
                            // Esto permite selección múltiple sin perder las opciones visibles
                            
                            if (level === 'cities') {
                                // Desde ciudades: actualizar empresas, canales, sucursales, cargos
                                updateCheckboxes('companies', response.data.companies);
                                updateCheckboxes('channels', response.data.channels);
                                updateCheckboxes('branches', response.data.branches);
                                updateCheckboxes('roles', response.data.roles);
                            } else if (level === 'companies') {
                                // Desde empresas: actualizar solo canales, sucursales, cargos
                                // NO actualizar empresas para permitir selección múltiple
                                updateCheckboxes('channels', response.data.channels);
                                updateCheckboxes('branches', response.data.branches);
                                updateCheckboxes('roles', response.data.roles);
                            } else if (level === 'channels') {
                                // Desde canales: actualizar solo sucursales y cargos
                                updateCheckboxes('branches', response.data.branches);
                                updateCheckboxes('roles', response.data.roles);
                            } else if (level === 'branches') {
                                // Desde sucursales: actualizar solo cargos
                                updateCheckboxes('roles', response.data.roles);
                            }
                        } else {
                            console.error('Error en respuesta AJAX:', response);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error AJAX:', error);
                        descendants.forEach(function(descendantLevel) {
                            containers[descendantLevel].html('<p style="color:red;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" style="vertical-align:middle;fill:red"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg> Error al cargar estructuras.</p>');
                        });
                    }
                });
            }
            
            /**
             * Actualiza los checkboxes de un nivel específico
             */
            function updateCheckboxes(level, items) {
                const container = containers[level];
                
                if (!container || !items) {
                    return;
                }
                
                // Limpiar contenedor
                container.html('');
                
                // Si no hay items, mostrar mensaje
                if (Object.keys(items).length === 0) {
                    let message = '';
                    switch(level) {
                        case 'companies':
                            message = 'No hay empresas disponibles para las ciudades seleccionadas.';
                            break;
                        case 'channels':
                            message = 'No hay canales disponibles para las empresas seleccionadas.';
                            break;
                        case 'branches':
                            message = 'No hay sucursales disponibles para los canales seleccionados.';
                            break;
                        case 'roles':
                            message = 'No hay cargos disponibles para las sucursales seleccionadas.';
                            break;
                    }
                    container.html('<p class="fplms-empty-state">' + message + '</p>');
                    return;
                }
                
                // Crear checkboxes (pre-seleccionados por defecto)
                Object.entries(items).forEach(function([id, name]) {
                    const $label = $('<label></label>');
                    const $checkbox = $('<input type="checkbox" class="fplms-cascade-checkbox">')
                        .attr('name', 'fplms_course_' + level + '[]')
                        .attr('data-level', level)
                        .attr('value', id)
                        .prop('checked', true); // Pre-seleccionar
                    
                    $label.append($checkbox);
                    $label.append(' ' + name);
                    container.append($label);
                });
                
                // Agregar event listeners a los nuevos checkboxes
                container.find('.fplms-cascade-checkbox').on('change', function() {
                    const currentLevel = $(this).data('level');
                    handleLevelChange(currentLevel);
                });
            }
            
            /**
             * Limpia niveles descendientes cuando se deselecciona todo
             */
            function clearDescendantLevels(fromLevel) {
                const descendants = levelHierarchy[fromLevel] || [];
                
                descendants.forEach(function(descendantLevel) {
                    let message = '';
                    switch(descendantLevel) {
                        case 'companies':
                            message = 'Selecciona una ciudad primero para cargar empresas.';
                            break;
                        case 'channels':
                            message = 'Selecciona una empresa primero para cargar canales.';
                            break;
                        case 'branches':
                            message = 'Selecciona un canal primero para cargar sucursales.';
                            break;
                        case 'roles':
                            message = 'Selecciona una sucursal primero para cargar cargos.';
                            break;
                    }
                    containers[descendantLevel].html('<p class="fplms-empty-state">' + message + '</p>');
                });
            }
            
            // Event listeners para TODOS los niveles (usando delegación de eventos)
            // Esto permite que funcione con checkboxes cargados dinámicamente y los ya existentes
            containers.cities.on('change', '.fplms-cascade-checkbox', function() {
                handleLevelChange('cities');
            });
            
            containers.companies.on('change', '.fplms-cascade-checkbox', function() {
                handleLevelChange('companies');
            });
            
            containers.channels.on('change', '.fplms-cascade-checkbox', function() {
                handleLevelChange('channels');
            });
            
            containers.branches.on('change', '.fplms-cascade-checkbox', function() {
                handleLevelChange('branches');
            });
            
            // NOTA: No agregamos listener para 'roles' porque es el último nivel (no tiene descendientes)
        });
        </script>
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
        // SVG icons (inline, 11×11).
        $svg_city    = '<svg viewBox="0 0 24 24" style="width:11px;height:11px;fill:#6b7280;flex-shrink:0;"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>';
        $svg_company = '<svg viewBox="0 0 24 24" style="width:11px;height:11px;fill:#6b7280;flex-shrink:0;"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg>';
        $svg_channel = '<svg viewBox="0 0 24 24" style="width:11px;height:11px;fill:#6b7280;flex-shrink:0;"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>';
        $svg_branch  = '<svg viewBox="0 0 24 24" style="width:11px;height:11px;fill:#6b7280;flex-shrink:0;"><path d="M13 7h-2v2h2V7zm0 4h-2v2h2v-2zm4 0h-2v2h2v-2zM3 3v18h18V3H3zm16 16H5V5h14v14zM9 7H7v2h2V7zm0 4H7v2h2v-2z"/></svg>';
        $svg_role    = '<svg viewBox="0 0 24 24" style="width:11px;height:11px;fill:#6b7280;flex-shrink:0;"><path d="M20 6h-2.18c.07-.44.18-.88.18-1 0-2.21-1.79-4-4-4s-4 1.79-4 4c0 .12.11.56.18 1H8c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h12c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6-3c1.1 0 2 .9 2 2 0 .12-.11.56-.18 1h-3.64C12.11 5.56 12 5.12 12 5c0-1.1.9-2 2-2zm6 17H8V8h2v1c0 .55.45 1 1 1h6c.55 0 1-.45 1-1V8h2v12z"/></svg>';

        $tags = [];

        // Ciudades
        if ( ! empty( $structures['cities'] ) ) {
            foreach ( $this->get_term_names_by_ids( $structures['cities'] ) as $name ) {
                $tags[] = '<span class="fplms-ct-struct-tag">' . $svg_city . esc_html( $name ) . '</span>';
            }
        }

        // Empresas
        if ( ! empty( $structures['companies'] ) ) {
            foreach ( $this->get_term_names_by_ids( $structures['companies'] ) as $name ) {
                $tags[] = '<span class="fplms-ct-struct-tag">' . $svg_company . esc_html( $name ) . '</span>';
            }
        }

        // Canales
        if ( ! empty( $structures['channels'] ) ) {
            foreach ( $this->get_term_names_by_ids( $structures['channels'] ) as $name ) {
                $tags[] = '<span class="fplms-ct-struct-tag">' . $svg_channel . esc_html( $name ) . '</span>';
            }
        }

        // Sucursales
        if ( ! empty( $structures['branches'] ) ) {
            foreach ( $this->get_term_names_by_ids( $structures['branches'] ) as $name ) {
                $tags[] = '<span class="fplms-ct-struct-tag">' . $svg_branch . esc_html( $name ) . '</span>';
            }
        }

        // Cargos
        if ( ! empty( $structures['roles'] ) ) {
            foreach ( $this->get_term_names_by_ids( $structures['roles'] ) as $name ) {
                $tags[] = '<span class="fplms-ct-struct-tag">' . $svg_role . esc_html( $name ) . '</span>';
            }
        }

        if ( empty( $tags ) ) {
            $svg_globe = '<svg viewBox="0 0 24 24" style="width:11px;height:11px;fill:#1a56db;flex-shrink:0;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>';
            return '<span class="fplms-ct-struct-tag fplms-ct-struct-free">' . $svg_globe . 'Sin restricción</span>';
        }

        // Limitar a 3 tags + "+X más" si hay muchos.
        if ( count( $tags ) > 3 ) {
            $remaining = count( $tags ) - 3;
            $tags      = array_slice( $tags, 0, 3 );
            $tags[]    = '<span class="fplms-ct-struct-tag" style="background:#f0f0f1;color:#6b7280;">+' . $remaining . ' más</span>';
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
    /**
     * AJAX: devuelve los términos hijo disponibles dado un nivel superior seleccionado.
     * Action: fplms_cascade_structures
     */
    public function ajax_cascade_structures(): void {
        check_ajax_referer( 'fplms_cascade_nonce', 'nonce' );

        $level  = isset( $_POST['level'] ) ? sanitize_text_field( wp_unslash( $_POST['level'] ) ) : '';
        $ids    = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : [];
        $result = [];

        switch ( $level ) {
            case 'city':
                // Cities selected → return companies in those cities
                $terms = $this->structures->get_terms_by_cities( FairPlay_LMS_Config::TAX_COMPANY, $ids );
                foreach ( $terms as $t ) {
                    $result[ $t->term_id ] = $t->name;
                }
                break;
            case 'company':
                // Companies selected → return channels in those companies
                $terms = $this->structures->get_channels_by_companies( FairPlay_LMS_Config::TAX_CHANNEL, $ids );
                foreach ( $terms as $t ) {
                    $result[ $t->term_id ] = $t->name;
                }
                break;
            case 'channel':
                // Channels selected → return branches in those channels
                $terms = $this->structures->get_branches_by_channels( FairPlay_LMS_Config::TAX_BRANCH, $ids );
                foreach ( $terms as $t ) {
                    $result[ $t->term_id ] = $t->name;
                }
                break;
            case 'branch':
                // Branches selected → return roles in those branches
                $terms = $this->structures->get_roles_by_branches( FairPlay_LMS_Config::TAX_ROLE, $ids );
                foreach ( $terms as $t ) {
                    $result[ $t->term_id ] = $t->name;
                }
                break;
        }

        wp_send_json_success( $result );
    }

    public function render_structures_meta_box( $post ): void {
        wp_nonce_field( 'fplms_save_course_structures', 'fplms_structures_nonce' );

        $current_structures = [];
        if ( $post->ID ) {
            $current_structures = $this->get_course_structures( $post->ID );
        }
        
        // Estructuras disponibles según rol
        $available_structures = $this->get_available_structures_for_user();
        $current_user = wp_get_current_user();
        $is_admin     = current_user_can( 'manage_options' );

        // Valores actuales del curso (arrays de IDs)
        $sel_cities    = array_map( 'intval', (array) ( $current_structures['cities']    ?? [] ) );
        $sel_companies = array_map( 'intval', (array) ( $current_structures['companies'] ?? [] ) );
        $sel_channels  = array_map( 'intval', (array) ( $current_structures['channels']  ?? [] ) );
        $sel_branches  = array_map( 'intval', (array) ( $current_structures['branches']  ?? [] ) );
        $sel_roles     = array_map( 'intval', (array) ( $current_structures['roles']     ?? [] ) );

        // Pre-cargar opciones para niveles 2-5 basados en la selección actual
        // (City seleccionadas → empresas asociadas, etc.)
        $companies_init = $sel_cities
            ? $this->structures->get_terms_by_cities( FairPlay_LMS_Config::TAX_COMPANY, $sel_cities )
            : [];
        $cids_for_ch     = $sel_companies ?: array_column( $companies_init, 'term_id' );
        $channels_init   = $cids_for_ch
            ? $this->structures->get_channels_by_companies( FairPlay_LMS_Config::TAX_CHANNEL, $cids_for_ch )
            : [];
        $chids_for_br    = $sel_channels ?: array_column( $channels_init, 'term_id' );
        $branches_init   = $chids_for_br
            ? $this->structures->get_branches_by_channels( FairPlay_LMS_Config::TAX_BRANCH, $chids_for_br )
            : [];
        $brids_for_role  = $sel_branches ?: array_column( $branches_init, 'term_id' );
        $roles_init      = $brids_for_role
            ? $this->structures->get_roles_by_branches( FairPlay_LMS_Config::TAX_ROLE, $brids_for_role )
            : [];

        // Helper → convierte lista de WP_Term en {id:name} para JSON
        $to_map = static function( array $terms ): array {
            $map = [];
            foreach ( $terms as $t ) {
                $map[ (int) $t->term_id ] = $t->name;
            }
            return $map;
        };

        $ajax_url   = admin_url( 'admin-ajax.php' );
        $nonce      = wp_create_nonce( 'fplms_cascade_nonce' );
        ?>
        <div id="fplms-mb" style="font-size:12.5px;">
        <style>
        #fplms-mb { --c1:#0073aa; --cr:#d63638; }
        .fplms-mb-role { font-size:11px; padding:6px 8px; border-radius:4px; margin-bottom:10px; font-weight:600; }
        .fplms-mb-role.admin   { background:#dbeafe; color:#1e40af; border-left:3px solid #3b82f6; }
        .fplms-mb-role.inst    { background:#fef3c7; color:#92400e; border-left:3px solid #f59e0b; }
        .fplms-mb-level        { margin-bottom:10px; }
        .fplms-mb-label        { font-weight:600; color:#1d2327; margin-bottom:4px; display:flex; align-items:center; gap:4px; font-size:11.5px; }
        .fplms-mb-label svg    { width:13px;height:13px;fill:var(--c1);flex-shrink:0; }
        .fplms-mb-label .cnt   { margin-left:auto; background:var(--c1); color:#fff; border-radius:10px; padding:1px 7px; font-size:10px; font-weight:700; }
        /* multiselect trigger */
        .fplms-mb-trigger      { display:flex; align-items:center; justify-content:space-between; padding:5px 8px; border:1px solid #c3c4c7; border-radius:4px; cursor:pointer; background:#fff; min-height:30px; gap:6px; transition:border-color .15s; }
        .fplms-mb-trigger:hover{ border-color:var(--c1); }
        .fplms-mb-trigger.open { border-color:var(--c1); box-shadow:0 0 0 2px rgba(0,115,170,.15); }
        .fplms-mb-trigger.disabled{ background:#f6f7f7; color:#a0a5aa; cursor:default; pointer-events:none; }
        .fplms-mb-pills        { display:flex; flex-wrap:wrap; gap:3px; flex:1; min-width:0; }
        .fplms-mb-pill         { background:#e8f0fe; color:#1d4ed8; border-radius:10px; padding:1px 8px; font-size:10.5px; display:flex; align-items:center; gap:3px; white-space:nowrap; }
        .fplms-mb-pill .rm     { cursor:pointer; font-size:12px; line-height:1; color:#6b7280; }
        .fplms-mb-pill .rm:hover{ color:var(--cr); }
        .fplms-mb-placeholder  { color:#a0a5aa; font-size:11.5px; }
        .fplms-mb-arrow        { flex-shrink:0; color:#a0a5aa; transition:transform .2s; }
        .fplms-mb-trigger.open .fplms-mb-arrow { transform:rotate(180deg); }
        /* popover */
        .fplms-mb-popover      { display:none; background:#fff; border:1px solid #ddd; border-radius:5px; box-shadow:0 4px 16px rgba(0,0,0,.12); max-height:200px; overflow:hidden; flex-direction:column; }
        .fplms-mb-popover.open { display:flex; }
        .fplms-mb-search       { padding:5px 8px; border-bottom:1px solid #f0f0f0; }
        .fplms-mb-search input { width:100%; padding:4px 6px; border:1px solid #ddd; border-radius:3px; font-size:11.5px; outline:none; box-sizing:border-box; }
        .fplms-mb-search input:focus { border-color:var(--c1); }
        .fplms-mb-options      { overflow-y:auto; padding:4px 0; flex:1; }
        .fplms-mb-opt          { display:flex; align-items:center; gap:6px; padding:4px 10px; cursor:pointer; transition:background .1s; font-size:12px; }
        .fplms-mb-opt:hover    { background:#f0f6fc; }
        .fplms-mb-opt input    { flex-shrink:0; cursor:pointer; }
        .fplms-mb-opt.selected { background:#f0f6fc; }
        .fplms-mb-empty        { padding:8px 10px; color:#a0a5aa; font-size:11.5px; }
        .fplms-mb-loading      { padding:8px 10px; color:#a0a5aa; font-size:11px; display:flex; align-items:center; gap:5px; }
        .fplms-mb-spinner      { width:12px;height:12px;border:2px solid #ddd;border-top-color:var(--c1);border-radius:50%;animation:fplms-spin .6s linear infinite; }
        @keyframes fplms-spin   { to { transform:rotate(360deg); } }
        /* cascade arrow */
        .fplms-mb-cascade-arrow{ text-align:center; color:#d1d5db; font-size:10px; margin:2px 0; }
        /* summary bar */
        .fplms-mb-summary      { background:#f0f6fc; border-radius:4px; padding:6px 8px; font-size:11px; color:#1d4ed8; margin-top:8px; line-height:1.6; }
        .fplms-mb-summary strong{ display:block; margin-bottom:2px; color:#1d2327; font-size:11.5px; }
        </style>

        <?php if ( $is_admin ) : ?>
        <div class="fplms-mb-role admin">
            <svg viewBox="0 0 24 24" style="width:12px;height:12px;fill:#1e40af;vertical-align:middle;margin-right:4px;"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
            Administrador — control total
        </div>
        <?php else : ?>
        <div class="fplms-mb-role inst">
            <?php esc_html_e( 'Instructor — solo tus estructuras', 'fplms' ); ?>
        </div>
        <?php endif; ?>

        <?php
        // Build level definitions for PHP → JS injection
        $levels = [
            [
                'key'         => 'cities',
                'label'       => 'Ciudades',
                'icon'        => '<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>',
                'options'     => $available_structures['cities'],
                'selected'    => $sel_cities,
                'placeholder' => 'Seleccionar ciudades…',
                'input_name'  => 'fplms_course_cities[]',
            ],
            [
                'key'         => 'companies',
                'label'       => 'Empresas',
                'icon'        => '<path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/>',
                'options'     => $to_map( $companies_init ),
                'selected'    => $sel_companies,
                'placeholder' => 'Seleccionar empresas…',
                'input_name'  => 'fplms_course_companies[]',
            ],
            [
                'key'         => 'channels',
                'label'       => 'Canales',
                'icon'        => '<path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>',
                'options'     => $to_map( $channels_init ),
                'selected'    => $sel_channels,
                'placeholder' => 'Seleccionar canales…',
                'input_name'  => 'fplms_course_channels[]',
            ],
            [
                'key'         => 'branches',
                'label'       => 'Sucursales',
                'icon'        => '<path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>',
                'options'     => $to_map( $branches_init ),
                'selected'    => $sel_branches,
                'placeholder' => 'Seleccionar sucursales…',
                'input_name'  => 'fplms_course_branches[]',
            ],
            [
                'key'         => 'roles',
                'label'       => 'Cargos',
                'icon'        => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
                'options'     => $to_map( $roles_init ),
                'selected'    => $sel_roles,
                'placeholder' => 'Seleccionar cargos…',
                'input_name'  => 'fplms_course_roles[]',
            ],
        ];

        foreach ( $levels as $i => $lvl ) :
            $is_last = ( $i === count( $levels ) - 1 );
            $level_key  = esc_attr( $lvl['key'] );
            $opts_json  = wp_json_encode( $lvl['options'] );
            $sel_json   = wp_json_encode( $lvl['selected'] );
        ?>
        <div class="fplms-mb-level" id="fplms-mb-level-<?php echo $level_key; ?>">
            <div class="fplms-mb-label">
                <svg viewBox="0 0 24 24"><?php echo $lvl['icon']; // phpcs:ignore ?></svg>
                <?php echo esc_html( $lvl['label'] ); ?>
                <span class="cnt" id="fplms-cnt-<?php echo $level_key; ?>">0</span>
            </div>
            <!-- hidden inputs populated by JS -->
            <div id="fplms-inputs-<?php echo $level_key; ?>"></div>
            <!-- multiselect trigger -->
            <div class="fplms-mb-trigger" id="fplms-trigger-<?php echo $level_key; ?>"
                 data-level="<?php echo $level_key; ?>"
                 onclick="fplmsMbToggle('<?php echo $level_key; ?>')">
                <div class="fplms-mb-pills" id="fplms-pills-<?php echo $level_key; ?>">
                    <span class="fplms-mb-placeholder" id="fplms-ph-<?php echo $level_key; ?>"><?php echo esc_html( $lvl['placeholder'] ); ?></span>
                </div>
                <span class="fplms-mb-arrow">▾</span>
            </div>
            <!-- popover -->
            <div class="fplms-mb-popover" id="fplms-pop-<?php echo $level_key; ?>">
                <div class="fplms-mb-search">
                    <input type="text" placeholder="Buscar..." oninput="fplmsMbSearch('<?php echo $level_key; ?>',this.value)">
                </div>
                <div class="fplms-mb-options" id="fplms-opts-<?php echo $level_key; ?>"></div>
            </div>
            <!-- init data -->
            <script>
            (function(){
                var lv='<?php echo $level_key; ?>';
                window._fplmsMbData = window._fplmsMbData||{};
                window._fplmsMbSel  = window._fplmsMbSel||{};
                window._fplmsMbData[lv] = <?php echo $opts_json; // phpcs:ignore ?>;
                window._fplmsMbSel[lv]  = <?php echo $sel_json;  // phpcs:ignore ?>;
            })();
            </script>
        </div>
        <?php if ( ! $is_last ) : ?>
        <div class="fplms-mb-cascade-arrow">↓</div>
        <?php endif; ?>
        <?php endforeach; ?>

        <div class="fplms-mb-summary" id="fplms-mb-summary" style="display:none;">
            <strong>Asignación en cascada</strong>
            <span id="fplms-mb-summary-text"></span>
        </div>

        <script>
        (function(){
            var ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;
            var nonce   = <?php echo wp_json_encode( $nonce ); ?>;
            var LEVELS  = ['cities','companies','channels','branches','roles'];
            var PARENTS = { companies:'cities', channels:'companies', branches:'channels', roles:'branches' };
            var CHILDREN= { cities:'companies', companies:'channels', channels:'branches', branches:'roles' };
            var LEVEL_MAP= { cities:'city', companies:'company', channels:'channel', branches:'branch', roles:'branch' };
            /* LEVEL_MAP: level key → ajax 'level' param for the child load */
            var LOAD_CHILD_LEVEL = { cities:'city', companies:'company', channels:'channel', branches:'branch' };

            // ─── Render options list ────────────────────────────────────────
            function renderOpts(lv, q) {
                var data = window._fplmsMbData[lv]||{};
                var sel  = window._fplmsMbSel[lv]||[];
                var el   = document.getElementById('fplms-opts-'+lv);
                if (!el) return;
                var html = '';
                var count = 0;
                for (var id in data) {
                    var name = data[id];
                    if (q && name.toLowerCase().indexOf(q.toLowerCase()) === -1) continue;
                    var checked = sel.indexOf(parseInt(id)) !== -1 ? ' checked' : '';
                    var cls     = checked ? ' selected' : '';
                    html += '<label class="fplms-mb-opt'+cls+'" data-id="'+id+'">'
                          + '<input type="checkbox" value="'+id+'"'+checked+'>'
                          + '<span>'+escH(name)+'</span></label>';
                    count++;
                }
                if (!count) html = '<div class="fplms-mb-empty">Sin resultados</div>';
                el.innerHTML = html;

                // Bind checkboxes
                var cbks = el.querySelectorAll('input[type=checkbox]');
                cbks.forEach(function(cb){
                    cb.addEventListener('change', function(){
                        toggleSel(lv, parseInt(this.value), this.checked);
                    });
                });
            }

            // ─── Toggle selection ───────────────────────────────────────────
            function toggleSel(lv, id, on) {
                var sel  = window._fplmsMbSel[lv]||[];
                if (on) {
                    if (sel.indexOf(id) === -1) sel.push(id);
                } else {
                    sel = sel.filter(function(x){ return x !== id; });
                }
                window._fplmsMbSel[lv] = sel;
                renderPills(lv);
                updateHiddenInputs(lv);
                updateCounter(lv);
                renderOpts(lv, ''); // refresh checked state in popover

                // Cascade: reload next level
                var childLv = CHILDREN[lv];
                if (childLv) {
                    loadChildLevel(lv, childLv);
                }
                updateSummary();
            }

            // ─── Pills ──────────────────────────────────────────────────────
            function renderPills(lv) {
                var sel  = window._fplmsMbSel[lv]||[];
                var data = window._fplmsMbData[lv]||{};
                var ph   = document.getElementById('fplms-ph-'+lv);
                var pillsEl = document.getElementById('fplms-pills-'+lv);
                // Remove old pills
                Array.from(pillsEl.querySelectorAll('.fplms-mb-pill')).forEach(function(p){ p.remove(); });
                if (sel.length === 0) {
                    if (ph) ph.style.display = '';
                } else {
                    if (ph) ph.style.display = 'none';
                    sel.forEach(function(id){
                        var name = data[id] || '#'+id;
                        var pill = document.createElement('span');
                        pill.className = 'fplms-mb-pill';
                        pill.innerHTML = escH(name)
                            +'<span class="rm" data-id="'+id+'" data-lv="'+lv+'" title="Quitar">×</span>';
                        pill.querySelector('.rm').addEventListener('click', function(e){
                            e.stopPropagation();
                            toggleSel(lv, parseInt(this.dataset.id), false);
                        });
                        pillsEl.insertBefore(pill, ph);
                    });
                }
            }

            // ─── Hidden inputs ──────────────────────────────────────────────
            function updateHiddenInputs(lv) {
                var sel     = window._fplmsMbSel[lv]||[];
                var wrapper = document.getElementById('fplms-inputs-'+lv);
                if (!wrapper) return;
                var names   = { cities:'fplms_course_cities[]', companies:'fplms_course_companies[]',
                                channels:'fplms_course_channels[]', branches:'fplms_course_branches[]',
                                roles:'fplms_course_roles[]' };
                var html = '';
                sel.forEach(function(id){
                    html += '<input type="hidden" name="'+names[lv]+'" value="'+parseInt(id)+'">';
                });
                wrapper.innerHTML = html;
            }

            // ─── Counter badge ──────────────────────────────────────────────
            function updateCounter(lv) {
                var cnt = document.getElementById('fplms-cnt-'+lv);
                if (cnt) cnt.textContent = (window._fplmsMbSel[lv]||[]).length;
            }

            // ─── AJAX load child level ──────────────────────────────────────
            function loadChildLevel(parentLv, childLv) {
                var parentSel = window._fplmsMbSel[parentLv]||[];
                var allParent = Object.keys(window._fplmsMbData[parentLv]||{}).map(Number);
                // If nothing selected in parent → use all available parent items
                var idsToSend = parentSel.length ? parentSel : allParent;

                if (!idsToSend.length) {
                    // Parent has no options at all → clear child and all descendants
                    clearLevel(childLv, true);
                    return;
                }

                setLoading(childLv, true);

                var fd = new FormData();
                fd.append('action', 'fplms_cascade_structures');
                fd.append('nonce',  nonce);
                fd.append('level',  LOAD_CHILD_LEVEL[parentLv]);
                idsToSend.forEach(function(id){ fd.append('ids[]', id); });

                fetch(ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(resp){
                        setLoading(childLv, false);
                        if (resp && resp.success) {
                            var newData = resp.data||{};
                            window._fplmsMbData[childLv] = newData;
                            // Keep only selected IDs that still exist in new data
                            var prevSel = window._fplmsMbSel[childLv]||[];
                            var newKeys = Object.keys(newData).map(Number);
                            window._fplmsMbSel[childLv] = prevSel.filter(function(id){
                                return newKeys.indexOf(id) !== -1;
                            });
                            renderOpts(childLv, '');
                            renderPills(childLv);
                            updateHiddenInputs(childLv);
                            updateCounter(childLv);
                            enableLevel(childLv, Object.keys(newData).length > 0);
                            // Cascade further
                            var grandChild = CHILDREN[childLv];
                            if (grandChild) loadChildLevel(childLv, grandChild);
                        }
                    })
                    .catch(function(){ setLoading(childLv, false); });
            }

            function clearLevel(lv, cascade) {
                window._fplmsMbData[lv] = {};
                window._fplmsMbSel[lv]  = [];
                renderOpts(lv, '');
                renderPills(lv);
                updateHiddenInputs(lv);
                updateCounter(lv);
                enableLevel(lv, false);
                if (cascade) {
                    var ch = CHILDREN[lv];
                    if (ch) clearLevel(ch, true);
                }
            }

            function setLoading(lv, on) {
                var el = document.getElementById('fplms-opts-'+lv);
                if (on && el) el.innerHTML = '<div class="fplms-mb-loading"><div class="fplms-mb-spinner"></div> Cargando…</div>';
            }

            function enableLevel(lv, enabled) {
                var trig = document.getElementById('fplms-trigger-'+lv);
                if (!trig) return;
                if (enabled) {
                    trig.classList.remove('disabled');
                } else {
                    trig.classList.add('disabled');
                    closePop(lv);
                }
            }

            // ─── Open / close popover ───────────────────────────────────────
            window.fplmsMbToggle = function(lv) {
                var trig = document.getElementById('fplms-trigger-'+lv);
                if (trig && trig.classList.contains('disabled')) return;
                var pop = document.getElementById('fplms-pop-'+lv);
                if (!pop) return;
                var isOpen = pop.classList.contains('open');
                // Close all others
                LEVELS.forEach(function(l){ closePop(l); });
                if (!isOpen) {
                    pop.classList.add('open');
                    trig.classList.add('open');
                    renderOpts(lv, '');
                    var si = pop.querySelector('input[type=text]');
                    if (si) { si.value=''; si.focus(); }
                }
            };

            function closePop(lv) {
                var pop  = document.getElementById('fplms-pop-'+lv);
                var trig = document.getElementById('fplms-trigger-'+lv);
                if (pop)  pop.classList.remove('open');
                if (trig) trig.classList.remove('open');
            }

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.fplms-mb-level')) {
                    LEVELS.forEach(function(l){ closePop(l); });
                }
            });

            // ─── Search filter ──────────────────────────────────────────────
            window.fplmsMbSearch = function(lv, q) { renderOpts(lv, q); };

            // ─── Summary ────────────────────────────────────────────────────
            function updateSummary() {
                var parts = [];
                var labels = { cities:'Ciudades', companies:'Empresas', channels:'Canales',
                               branches:'Sucursales', roles:'Cargos' };
                LEVELS.forEach(function(lv){
                    var n = (window._fplmsMbSel[lv]||[]).length;
                    if (n) parts.push(n + ' ' + labels[lv].toLowerCase());
                });
                var el = document.getElementById('fplms-mb-summary');
                var tx = document.getElementById('fplms-mb-summary-text');
                if (parts.length) {
                    el.style.display = '';
                    tx.textContent   = parts.join(' · ');
                } else {
                    el.style.display = 'none';
                }
            }

            // ─── Escape HTML ────────────────────────────────────────────────
            function escH(s) {
                return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }

            // ─── Init ────────────────────────────────────────────────────────
            document.addEventListener('DOMContentLoaded', function() {
                LEVELS.forEach(function(lv){
                    renderPills(lv);
                    updateHiddenInputs(lv);
                    updateCounter(lv);
                    var hasOpts = Object.keys(window._fplmsMbData[lv]||{}).length > 0;
                    var isFirst = lv === 'cities';
                    enableLevel(lv, isFirst || hasOpts);
                });
                updateSummary();
            });
        })();
        </script>
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
        
        // Obtener estructuras anteriores para comparar (ANTES de guardar las nuevas)
        $old_structures = [
            'cities'    => (array) get_post_meta( $post_id, FairPlay_LMS_Config::META_COURSE_CITIES, true ),
            'companies' => (array) get_post_meta( $post_id, FairPlay_LMS_Config::META_COURSE_COMPANIES, true ),
            'channels'  => (array) get_post_meta( $post_id, FairPlay_LMS_Config::META_COURSE_CHANNELS, true ),
            'branches'  => (array) get_post_meta( $post_id, FairPlay_LMS_Config::META_COURSE_BRANCHES, true ),
            'roles'     => (array) get_post_meta( $post_id, FairPlay_LMS_Config::META_COURSE_ROLES, true ),
        ];
        
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
        
        // REGISTRAR EN AUDITORÍA
        $structures_changed = $this->structures_have_changed( $old_structures, $cascaded_structures );
        
        if ( $structures_changed || empty( array_filter( $old_structures ) ) ) {
            // Las estructuras cambiaron o es la primera vez que se asignan
            $action = empty( array_filter( $old_structures ) ) ? 'structures_assigned' : 'structures_updated';
            
            // Preparar datos para old_value y new_value
            $old_value_data = [
                'cities'    => $old_structures['cities'],
                'companies' => $old_structures['companies'],
                'channels'  => $old_structures['channels'],
                'branches'  => $old_structures['branches'],
                'roles'     => $old_structures['roles'],
            ];
            
            $new_value_data = [
                'cities'    => $cascaded_structures['cities'],
                'companies' => $cascaded_structures['companies'],
                'channels'  => $cascaded_structures['channels'],
                'branches'  => $cascaded_structures['branches'],
                'roles'     => $cascaded_structures['roles'],
            ];
            
            // Registrar en la bitácora de auditoría
            $this->logger->log_action(
                $action,
                'course',
                $post_id,
                $post->post_title,
                wp_json_encode( $old_value_data, JSON_UNESCAPED_UNICODE ),
                wp_json_encode( $new_value_data, JSON_UNESCAPED_UNICODE )
            );
            
            error_log( '✅ Estructuras registradas en auditoría: ' . $action );
        }
        
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

		// === Clasificación jerárquica (igual que sync_categories_to_structures) ===
		$channels_found    = [];
		$explicit_branches = [];
		$explicit_roles    = [];

		foreach ( $category_ids as $category_id ) {
			$cat_id = (int) $category_id;

			$linked_channel = $structures_controller->get_linked_channel( $cat_id );
			if ( $linked_channel ) {
				$channels_found[] = $linked_channel;
				continue;
			}

			$linked_branch_id = (int) get_term_meta( $cat_id, 'fplms_linked_branch_id', true );
			if ( $linked_branch_id ) {
				$explicit_branches[] = $linked_branch_id;
				$branch_cat          = get_term( $cat_id, FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY );
				if ( $branch_cat && ! is_wp_error( $branch_cat ) && $branch_cat->parent ) {
					$parent_channel = $structures_controller->get_linked_channel( $branch_cat->parent );
					if ( $parent_channel ) {
						$channels_found[] = $parent_channel;
					}
				}
				continue;
			}

			$linked_role_id = (int) get_term_meta( $cat_id, 'fplms_linked_role_id', true );
			if ( $linked_role_id ) {
				$explicit_roles[] = $linked_role_id;
				$role_cat         = get_term( $cat_id, FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY );
				if ( $role_cat && ! is_wp_error( $role_cat ) && $role_cat->parent ) {
					$parent_branch_id = (int) get_term_meta( $role_cat->parent, 'fplms_linked_branch_id', true );
					if ( $parent_branch_id ) {
						$explicit_branches[] = $parent_branch_id;
					}
					$branch_cat = get_term( $role_cat->parent, FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY );
					if ( $branch_cat && ! is_wp_error( $branch_cat ) && $branch_cat->parent ) {
						$grandparent_channel = $structures_controller->get_linked_channel( $branch_cat->parent );
						if ( $grandparent_channel ) {
							$channels_found[] = $grandparent_channel;
						}
					}
				}
				continue;
			}
		}

		$channels_to_assign = array_values( array_unique( array_filter( $channels_found ) ) );
		$explicit_branches  = array_values( array_unique( array_filter( $explicit_branches ) ) );
		$explicit_roles     = array_values( array_unique( array_filter( $explicit_roles ) ) );

		if ( empty( $channels_to_assign ) ) {
			return;
		}

		// Leer restricciones guardadas por sync_categories_to_structures (puede haberse ejecutado antes)
		$manual_branches = array_filter( array_map( 'absint', (array) get_post_meta( $post_id, 'fplms_manual_branches', true ) ) );
		$manual_roles    = array_filter( array_map( 'absint', (array) get_post_meta( $post_id, 'fplms_manual_roles',    true ) ) );
		// Si la llamada proviene del editor clásico (admin), usar las restricciones derivadas directamente
		if ( ! empty( $explicit_branches ) || ! empty( $explicit_roles ) ) {
			$manual_branches = $explicit_branches;
			$manual_roles    = $explicit_roles;
		}

		// Aplicar cascada con restricciones
		$cascaded = $this->apply_cascade_with_restrictions( $channels_to_assign, array_values( $manual_branches ), array_values( $manual_roles ) );

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

		// === Clasificación jerárquica de categorías seleccionadas ===
		// Canal  → categoría raíz (meta: fplms_linked_channel_id)
		// Sucursal → subcategoría  (meta: fplms_linked_branch_id)
		// Cargo  → sub-subcategoría (meta: fplms_linked_role_id)
		$channels_found    = [];   // IDs de canales derivados de la selección
		$explicit_branches = [];   // Sucursales elegidas explícitamente
		$explicit_roles    = [];   // Cargos elegidos explícitamente

		foreach ( $terms as $term_id ) {
			$term_id_int = (int) $term_id;

			// ¿Es una categoría de canal?
			$linked_channel = $structures_controller->get_linked_channel( $term_id_int );
			if ( $linked_channel ) {
				$channels_found[] = $linked_channel;
				continue;
			}

			// ¿Es una subcategoría de sucursal?
			$linked_branch_id = (int) get_term_meta( $term_id_int, 'fplms_linked_branch_id', true );
			if ( $linked_branch_id ) {
				$explicit_branches[] = $linked_branch_id;
				// Derivar canal desde la categoría padre
				$branch_cat = get_term( $term_id_int, FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY );
				if ( $branch_cat && ! is_wp_error( $branch_cat ) && $branch_cat->parent ) {
					$parent_channel = $structures_controller->get_linked_channel( $branch_cat->parent );
					if ( $parent_channel ) {
						$channels_found[] = $parent_channel;
					}
				}
				continue;
			}

			// ¿Es una sub-subcategoría de cargo?
			$linked_role_id = (int) get_term_meta( $term_id_int, 'fplms_linked_role_id', true );
			if ( $linked_role_id ) {
				$explicit_roles[] = $linked_role_id;
				// Derivar sucursal y canal desde abuelo/padre
				$role_cat = get_term( $term_id_int, FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY );
				if ( $role_cat && ! is_wp_error( $role_cat ) && $role_cat->parent ) {
					$parent_branch_id = (int) get_term_meta( $role_cat->parent, 'fplms_linked_branch_id', true );
					if ( $parent_branch_id ) {
						$explicit_branches[] = $parent_branch_id;
					}
					$branch_cat = get_term( $role_cat->parent, FairPlay_LMS_Config::MS_TAX_COURSE_CATEGORY );
					if ( $branch_cat && ! is_wp_error( $branch_cat ) && $branch_cat->parent ) {
						$grandparent_channel = $structures_controller->get_linked_channel( $branch_cat->parent );
						if ( $grandparent_channel ) {
							$channels_found[] = $grandparent_channel;
						}
					}
				}
				continue;
			}
		}

		$channels_to_assign = array_values( array_unique( array_filter( $channels_found ) ) );
		$explicit_branches  = array_values( array_unique( array_filter( $explicit_branches ) ) );
		$explicit_roles     = array_values( array_unique( array_filter( $explicit_roles ) ) );

		if ( empty( $channels_to_assign ) ) {
			return;
		}

		// Persistir restricciones derivadas de la selección de categorías
		update_post_meta( $object_id, 'fplms_manual_branches', $explicit_branches );
		update_post_meta( $object_id, 'fplms_manual_roles',    $explicit_roles );

		// Aplicar cascada con las restricciones detectadas
		$cascaded = $this->apply_cascade_with_restrictions( $channels_to_assign, $explicit_branches, $explicit_roles );

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

	/**
	 * Registrar creación o edición de curso en bitácora
	 *
	 * @param int     $post_id ID del post
	 * @param WP_Post $post    Objeto del post
	 * @param bool    $update  Si es actualización
	 * @return void
	 */
	public function log_course_save( int $post_id, $post, bool $update ): void {
		// Ignorar revisiones y auto-guardados
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Solo registrar si el post está publicado
		if ( $post->post_status !== 'publish' ) {
			return;
		}

		$course_title = get_the_title( $post_id );

		if ( $update ) {
			// Es una edición
			$this->logger->log_course_updated(
				$post_id,
				$course_title,
				[], // old_data - podría obtener versión anterior si se necesita
				[
					'status' => $post->post_status,
					'modified' => current_time( 'mysql' ),
				]
			);
		} else {
			// Es una creación
			$this->logger->log_course_created(
				$post_id,
				$course_title,
				[
					'status' => $post->post_status,
					'author' => $post->post_author,
					'created' => $post->post_date,
				]
			);
		}
	}

	/**
	 * Registrar eliminación de curso en bitácora
	 *
	 * @param int $post_id ID del post
	 * @return void
	 */
	public function log_course_deletion( int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post || $post->post_type !== FairPlay_LMS_Config::MS_PT_COURSE ) {
			return;
		}

		$this->logger->log_course_deleted( $post_id, get_the_title( $post_id ) );
	}

	/**
	 * Registrar creación o edición de lección en bitácora
	 *
	 * @param int     $post_id ID del post
	 * @param WP_Post $post    Objeto del post
	 * @param bool    $update  Si es actualización
	 * @return void
	 */
	public function log_lesson_save( int $post_id, $post, bool $update ): void {
		// Ignorar revisiones y auto-guardados
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Solo registrar si el post está publicado
		if ( $post->post_status !== 'publish' ) {
			return;
		}

		$lesson_title = get_the_title( $post_id );
		$course_id = get_post_meta( $post_id, 'course_id', true ); // MasterStudy almacena el curso así

		if ( $update ) {
			$this->logger->log_lesson_updated(
				$post_id,
				$lesson_title,
				[],
				[
					'status' => $post->post_status,
					'modified' => current_time( 'mysql' ),
				]
			);
		} else {
			$this->logger->log_lesson_added(
				$post_id,
				$lesson_title,
				$course_id ? absint( $course_id ) : 0
			);
		}
	}

	/**
	 * Registrar eliminación de lección en bitácora
	 *
	 * @param int $post_id ID del post
	 * @return void
	 */
	public function log_lesson_deletion( int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post || $post->post_type !== FairPlay_LMS_Config::MS_PT_LESSON ) {
			return;
		}

		$this->logger->log_lesson_deleted( $post_id, get_the_title( $post_id ) );
	}

	/**
	 * Registrar creación o edición de quiz en bitácora
	 *
	 * @param int     $post_id ID del post
	 * @param WP_Post $post    Objeto del post
	 * @param bool    $update  Si es actualización
	 * @return void
	 */
	public function log_quiz_save( int $post_id, $post, bool $update ): void {
		// Ignorar revisiones y auto-guardados
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Solo registrar si el post está publicado
		if ( $post->post_status !== 'publish' ) {
			return;
		}

		$quiz_title = get_the_title( $post_id );
		$course_id = get_post_meta( $post_id, 'course_id', true );

		if ( $update ) {
			$this->logger->log_quiz_updated(
				$post_id,
				$quiz_title,
				[],
				[
					'status' => $post->post_status,
					'modified' => current_time( 'mysql' ),
				]
			);
		} else {
			$this->logger->log_quiz_added(
				$post_id,
				$quiz_title,
				$course_id ? absint( $course_id ) : 0
			);
		}
	}

	/**
	 * Registrar eliminación de quiz en bitácora
	 *
	 * @param int $post_id ID del post
	 * @return void
	 */
	public function log_quiz_deletion( int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post || $post->post_type !== FairPlay_LMS_Config::MS_PT_QUIZ ) {
			return;
		}

		$this->logger->log_quiz_deleted( $post_id, get_the_title( $post_id ) );
	}

	// ═══════════════════════════════════════════════════════════════════════════
	// FEATURE: Restricción de Estructuras en Editor Frontend de Cursos
	// ═══════════════════════════════════════════════════════════════════════════

	/**
	 * Aplica la cascada jerárquica desde canales respetando restricciones manuales
	 * de sucursales y cargos.
	 *
	 * Canal → sube a Empresa → Ciudad (siempre completo).
	 * Canal → Sucursales (todas, o solo las de $manual_branches si está definido).
	 * Sucursal → Cargos (todos, o solo los de $manual_roles si está definido).
	 *
	 * @param array $channels        IDs de canales seleccionados.
	 * @param array $manual_branches IDs de sucursales explícitamente seleccionadas (vacío = todas).
	 * @param array $manual_roles    IDs de cargos explícitamente seleccionados (vacío = todos).
	 * @return array Estructura completa con cities, companies, channels, branches, roles.
	 */
	private function apply_cascade_with_restrictions( array $channels, array $manual_branches = [], array $manual_roles = [] ): array {
		$structures_ctrl = new FairPlay_LMS_Structures_Controller();

		$result = [
			'cities'    => [],
			'companies' => [],
			'channels'  => $channels,
			'branches'  => [],
			'roles'     => [],
		];

		if ( empty( $channels ) ) {
			return $result;
		}

		// ── Subir jerarquía: Channel → Company → City (siempre completo) ────────
		foreach ( $channels as $channel_id ) {
			$company_ids = $structures_ctrl->get_term_companies( $channel_id );
			foreach ( (array) $company_ids as $company_id ) {
				if ( ! in_array( $company_id, $result['companies'], true ) ) {
					$result['companies'][] = (int) $company_id;
				}
				$city_ids = $structures_ctrl->get_term_cities( $company_id );
				foreach ( (array) $city_ids as $city_id ) {
					if ( ! in_array( $city_id, $result['cities'], true ) ) {
						$result['cities'][] = (int) $city_id;
					}
				}
			}
		}

		// ── Bajar jerarquía: Channel → Branch (con restricción opcional) ─────────
		$all_branch_ids = [];
		foreach ( $channels as $channel_id ) {
			$all_branch_ids = array_unique( array_merge( $all_branch_ids, $this->get_branches_by_channel( (int) $channel_id ) ) );
		}

		// Si hay selección manual, validar que pertenecen a alguno de los canales.
		$effective_branches = ! empty( $manual_branches )
			? array_values( array_intersect( array_map( 'intval', $manual_branches ), $all_branch_ids ) )
			: $all_branch_ids;

		$result['branches'] = $effective_branches;

		// ── Bajar jerarquía: Branch → Role (con restricción opcional) ────────────
		$all_role_ids = [];
		foreach ( $effective_branches as $branch_id ) {
			$all_role_ids = array_unique( array_merge( $all_role_ids, $this->get_roles_by_branch( (int) $branch_id ) ) );
		}

		// Si hay selección manual de cargos, validar que pertenecen a las sucursales efectivas.
		$result['roles'] = ! empty( $manual_roles )
			? array_values( array_intersect( array_map( 'intval', $manual_roles ), $all_role_ids ) )
			: $all_role_ids;

		return $result;
	}

	/**
	 * AJAX: Devuelve las sucursales y cargos disponibles para un curso (basados en
	 * su canal) junto con el estado actual de restricciones manuales guardadas.
	 */
	public function ajax_get_frontend_structures(): void {
		check_ajax_referer( 'fplms_frontend_structures', 'nonce' );

		$course_id = absint( $_POST['course_id'] ?? 0 );
		if ( ! $course_id || ! current_user_can( 'edit_post', $course_id ) ) {
			wp_send_json_error( 'Permiso denegado o curso no válido.' );
		}

		$channels = array_filter( array_map( 'absint', (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_CHANNELS, true ) ) );

		if ( empty( $channels ) ) {
			wp_send_json_success( [
				'channel_names'   => [],
				'branches'        => [],
				'manual_branches' => [],
				'roles'           => [],
				'manual_roles'    => [],
			] );
			return;
		}

		// Nombres de los canales.
		$channel_names = [];
		foreach ( $channels as $cid ) {
			$t = get_term( $cid, FairPlay_LMS_Config::TAX_CHANNEL );
			if ( $t && ! is_wp_error( $t ) ) {
				$channel_names[] = $t->name;
			}
		}

		// Todas las sucursales de esos canales.
		$all_branch_ids = [];
		foreach ( $channels as $cid ) {
			$all_branch_ids = array_unique( array_merge( $all_branch_ids, $this->get_branches_by_channel( $cid ) ) );
		}
		$branches = [];
		foreach ( $all_branch_ids as $bid ) {
			$t = get_term( $bid, FairPlay_LMS_Config::TAX_BRANCH );
			if ( $t && ! is_wp_error( $t ) ) {
				$branches[ $bid ] = $t->name;
			}
		}

		// Selecciones manuales actuales.
		$manual_branches = array_values( array_filter( array_map( 'absint', (array) get_post_meta( $course_id, 'fplms_manual_branches', true ) ) ) );
		$manual_roles    = array_values( array_filter( array_map( 'absint', (array) get_post_meta( $course_id, 'fplms_manual_roles',    true ) ) ) );

		// Cargos disponibles: de las sucursales manuales si hay, si no de todas.
		$branch_ids_for_roles = ! empty( $manual_branches ) ? $manual_branches : $all_branch_ids;
		$all_role_ids         = [];
		foreach ( $branch_ids_for_roles as $bid ) {
			$all_role_ids = array_unique( array_merge( $all_role_ids, $this->get_roles_by_branch( $bid ) ) );
		}
		$roles = [];
		foreach ( $all_role_ids as $rid ) {
			$t = get_term( $rid, FairPlay_LMS_Config::TAX_ROLE );
			if ( $t && ! is_wp_error( $t ) ) {
				$roles[ $rid ] = $t->name;
			}
		}

		wp_send_json_success( [
			'channel_names'   => $channel_names,
			'branches'        => $branches,
			'manual_branches' => $manual_branches,
			'roles'           => $roles,
			'manual_roles'    => $manual_roles,
		] );
	}

	/**
	 * AJAX: Devuelve los cargos de un conjunto de sucursales (carga dinámica
	 * cuando el usuario cambia la selección de sucursales en el panel frontend).
	 */
	public function ajax_get_branch_roles(): void {
		check_ajax_referer( 'fplms_frontend_structures', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'No autenticado.' );
		}

		$branch_ids = isset( $_POST['branch_ids'] )
			? array_filter( array_map( 'absint', (array) wp_unslash( $_POST['branch_ids'] ) ) )
			: [];

		$all_role_ids = [];
		foreach ( $branch_ids as $bid ) {
			$all_role_ids = array_unique( array_merge( $all_role_ids, $this->get_roles_by_branch( $bid ) ) );
		}

		$roles = [];
		foreach ( $all_role_ids as $rid ) {
			$t = get_term( $rid, FairPlay_LMS_Config::TAX_ROLE );
			if ( $t && ! is_wp_error( $t ) ) {
				$roles[ $rid ] = $t->name;
			}
		}

		wp_send_json_success( $roles );
	}

	/**
	 * AJAX: Guarda las restricciones manuales de sucursal/cargo para un curso
	 * y re-aplica la cascada con esas restricciones.
	 */
	public function ajax_save_frontend_structures(): void {
		check_ajax_referer( 'fplms_frontend_structures', 'nonce' );

		$course_id = absint( $_POST['course_id'] ?? 0 );
		if ( ! $course_id || ! current_user_can( 'edit_post', $course_id ) ) {
			wp_send_json_error( 'Permiso denegado o curso no válido.' );
		}

		$branch_ids = isset( $_POST['branch_ids'] )
			? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['branch_ids'] ) ) ) )
			: [];
		$role_ids   = isset( $_POST['role_ids'] )
			? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['role_ids'] ) ) ) )
			: [];

		// Guardar selecciones manuales.
		update_post_meta( $course_id, 'fplms_manual_branches', $branch_ids );
		update_post_meta( $course_id, 'fplms_manual_roles',    $role_ids );

		// Obtener canales actuales del curso.
		$channels = array_values( array_filter( array_map( 'absint', (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_CHANNELS, true ) ) ) );

		if ( empty( $channels ) ) {
			wp_send_json_error( 'El curso no tiene canal asignado. Primero selecciona una Categoría/Canal en el editor del curso.' );
		}

		// Re-aplicar la cascada con las restricciones guardadas.
		$cascaded = $this->apply_cascade_with_restrictions( $channels, $branch_ids, $role_ids );

		update_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_CITIES,    $cascaded['cities'] );
		update_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_COMPANIES, $cascaded['companies'] );
		update_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_CHANNELS,  $cascaded['channels'] );
		update_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_BRANCHES,  $cascaded['branches'] );
		update_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_ROLES,     $cascaded['roles'] );

		if ( class_exists( 'FairPlay_LMS_Audit_Logger' ) ) {
			( new FairPlay_LMS_Audit_Logger() )->log_action(
				'course_manual_restrictions_saved',
				'course',
				$course_id,
				get_the_title( $course_id ),
				null,
				[
					'channels'  => $channels,
					'branches'  => $branch_ids,
					'roles'     => $role_ids,
					'cascaded'  => $cascaded,
				]
			);
		}

		wp_send_json_success( [
			'message' => sprintf(
				'%d sucursal(es) y %d cargo(s) asignados.',
				count( $cascaded['branches'] ),
				count( $cascaded['roles'] )
			),
		] );
	}

	/**
	 * Inyecta el panel de "Restricción de Estructuras" en el editor frontend de
	 * cursos de MasterStudy. Llamado desde el hook wp_footer.
	 * Renames "Categoría" → "Canal" via MutationObserver JS.
	 */
	public function render_frontend_structure_panel(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
		if ( strpos( $request_uri, 'edit-course' ) === false ) {
			return;
		}

		$course_id    = absint( $_GET['course_id'] ?? 0 );
		$nonce        = wp_create_nonce( 'fplms_frontend_structures' );
		$ajax_url_js  = esc_js( admin_url( 'admin-ajax.php' ) );
		$nonce_js     = esc_js( $nonce );
		$course_id_js = (int) $course_id;
		?>
		<style>
			#fplms-fe-panel { display:none; background:#fff; border-radius:14px; border:1px solid #e5e7eb; box-shadow:0 2px 12px rgba(0,0,0,.08); margin:28px auto; max-width:768px; width:100%; overflow:hidden; }
			#fplms-fe-panel * { box-sizing:border-box; }
			#fplms-fe-panel p { margin:0; }
			.fplms-fe-hdr  { background:linear-gradient(135deg,#667eea,#764ba2); padding:16px 22px; display:flex; align-items:center; gap:12px; }
			.fplms-fe-hdr svg { width:22px;height:22px;fill:#fff;flex-shrink:0; }
			.fplms-fe-hdr-text h3 { margin:0;font-size:15px;font-weight:700;color:#fff; }
			.fplms-fe-hdr-text p  { margin:3px 0 0;font-size:12px;color:rgba(255,255,255,.8); }
			.fplms-fe-body { padding:20px 22px; }
			.fplms-fe-channel-row { display:flex;align-items:center;gap:10px;padding:10px 14px;background:#f0f6fe;border-radius:8px;border-left:3px solid #667eea;margin-bottom:18px;font-size:14px;color:#1e3a5f; }
			.fplms-fe-channel-row svg { width:16px;height:16px;fill:#667eea;flex-shrink:0; }
			.fplms-fe-sec-title { font-size:13px;font-weight:700;color:#374151;margin-bottom:5px;display:flex;align-items:center;gap:6px; }
			.fplms-fe-sec-title svg { width:14px;height:14px;fill:#6b7280; }
			.fplms-fe-hint { font-size:12px;color:#9ca3af;margin-bottom:10px; }
			.fplms-fe-chips { display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px; }
			.fplms-fe-chip { display:inline-flex;align-items:center;gap:6px;padding:5px 12px;background:#f3f4f6;border:1.5px solid #e5e7eb;border-radius:20px;font-size:13px;color:#374151;cursor:pointer;transition:all .15s;user-select:none; }
			.fplms-fe-chip input { display:none; }
			.fplms-fe-chip:hover { border-color:#667eea;color:#667eea; }
			.fplms-fe-chip.sel  { background:#667eea;border-color:#667eea;color:#fff; }
			.fplms-fe-empty { font-size:13px;color:#9ca3af;padding:6px 0; }
			.fplms-fe-hr { border:none;border-top:1px solid #f3f4f6;margin:18px 0; }
			.fplms-fe-footer { display:flex;align-items:center;gap:12px;padding:14px 22px;border-top:1px solid #f3f4f6;background:#f9fafb;flex-wrap:wrap; }
			.fplms-fe-save-btn { display:inline-flex;align-items:center;gap:8px;padding:9px 22px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:opacity .2s; }
			.fplms-fe-save-btn:hover { opacity:.88; }
			.fplms-fe-save-btn svg { width:16px;height:16px;fill:#fff; }
			.fplms-fe-save-btn:disabled { opacity:.5;cursor:not-allowed; }
			.fplms-fe-status { font-size:13px;color:#6b7280;flex:1; }
			.fplms-fe-status.ok  { color:#027A48; }
			.fplms-fe-status.err { color:#dc2626; }
			@keyframes fplmsSpin { to { transform:rotate(360deg); } }
			.fplms-fe-spin { display:inline-block;width:14px;height:14px;border:2px solid rgba(102,126,234,.3);border-top-color:#667eea;border-radius:50%;animation:fplmsSpin .7s linear infinite;vertical-align:middle; }
			.fplms-fe-notice { background:#fffbeb;border:1px solid #f59e0b;border-radius:8px;padding:12px 16px;font-size:13px;color:#92400e;display:flex;align-items:flex-start;gap:10px; }
			.fplms-fe-notice svg { width:18px;height:18px;fill:#f59e0b;flex-shrink:0;margin-top:1px; }
		</style>

		<div id="fplms-fe-panel">
			<div class="fplms-fe-hdr">
				<svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
				<div class="fplms-fe-hdr-text">
					<h3>Restricción de Estructuras</h3>
					<p>Define a qué sucursales y cargos aplica este curso</p>
				</div>
			</div>
			<div class="fplms-fe-body">
				<?php if ( ! $course_id ) : ?>
				<div class="fplms-fe-notice">
					<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
					<span>Guarda el curso primero para configurar restricciones de sucursal y cargo.</span>
				</div>
				<?php else : ?>
				<div id="fplms-fe-inner">
					<div style="text-align:center;padding:20px;"><span class="fplms-fe-spin"></span> Cargando estructuras…</div>
				</div>
				<?php endif; ?>
			</div>
			<?php if ( $course_id ) : ?>
			<div class="fplms-fe-footer">
				<button type="button" id="fplms-fe-save" class="fplms-fe-save-btn" disabled>
					<svg viewBox="0 0 24 24"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
					Guardar Estructuras
				</button>
				<span class="fplms-fe-status" id="fplms-fe-status"></span>
			</div>
			<?php endif; ?>
		</div>

		<script>
		(function() {
			var NONCE     = '<?php echo $nonce_js; ?>';
			var AJAX_URL  = '<?php echo $ajax_url_js; ?>';
			var COURSE_ID = <?php echo $course_id_js; ?>;
			var _branches = {};  // { id: name }
			var _roles    = {};  // { id: name }
			var _selB     = [];  // checked branch IDs
			var _selR     = [];  // checked role IDs

			// ── Inject panel after MasterStudy form ──────────────────────────
			function inject() {
				var panel = document.getElementById('fplms-fe-panel');
				if ( !panel || panel._injected ) return false;
				var targets = [
					'.masterstudy-course-builder', '.stm-lms-course-builder',
					'[class*="course-builder"]', '.masterstudy-user-account__content',
					'.stm-lms-user-account__content', '.masterstudy-page__content',
					'main .entry-content', '.stm-content__inner', 'main'
				];
				for ( var i = 0; i < targets.length; i++ ) {
					var el = document.querySelector(targets[i]);
					if ( el ) {
						el.insertAdjacentElement('afterend', panel);
						panel.style.display = 'block';
						panel._injected = true;
						return true;
					}
				}
				document.body.appendChild(panel);
				panel.style.display = 'block';
				panel._injected = true;
				return true;
			}
			function tryInject() {
				if ( inject() ) return;
				var n = 0, t = setInterval(function() { if ( inject() || ++n > 30 ) clearInterval(t); }, 400);
			}

			// ── Rename "Categoría" → "Canal" via MutationObserver ───────────
			function renameLabels() {
				document.querySelectorAll('label, span, p, div').forEach(function(el) {
					if ( el.children.length === 0 ) {
						var txt = el.textContent.trim();
						if ( txt === 'Categoría' || txt === 'Category' ) el.textContent = 'Canal';
					}
				});
			}
			new MutationObserver(renameLabels).observe(document.body, { childList:true, subtree:true });

			// ── Event delegation: chip toggle ────────────────────────────────
			document.addEventListener('click', function(e) {
				var chip = e.target && e.target.closest && e.target.closest('.fplms-fe-chip');
				if ( !chip || !chip.closest('#fplms-fe-inner') ) return;
				var cb = chip.querySelector('input[type="checkbox"]');
				if ( !cb ) return;
				cb.checked = !cb.checked;
				chip.classList.toggle('sel', cb.checked);
				syncSel();
				if ( chip.dataset.type === 'branch' ) reloadRoles();
				markDirty();
			});

			// ── Save button ──────────────────────────────────────────────────
			document.addEventListener('click', function(e) {
				if ( e.target && e.target.id === 'fplms-fe-save' ) doSave();
			});

			// ── Sync state from DOM ──────────────────────────────────────────
			function syncSel() {
				_selB = [];
				document.querySelectorAll('#fplms-fe-branches .fplms-fe-chip.sel').forEach(function(c) { _selB.push(Number(c.dataset.id)); });
				_selR = [];
				document.querySelectorAll('#fplms-fe-roles-area .fplms-fe-chip.sel').forEach(function(c) { _selR.push(Number(c.dataset.id)); });
			}

			// ── Render full panel from AJAX data ─────────────────────────────
			function renderPanel(d) {
				_branches = d.branches    || {};
				_roles    = d.roles       || {};
				_selB     = (d.manual_branches || []).map(Number);
				_selR     = (d.manual_roles    || []).map(Number);

				var chName = (d.channel_names || []).join(', ') || '—';
				var html   = '';

				// Canal info row
				html += '<div class="fplms-fe-channel-row">'
					+ '<svg viewBox="0 0 24 24"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>'
					+ '<div><strong>Canal:</strong> ' + esc(chName) + '</div></div>';

				// Branches
				html += '<div class="fplms-fe-sec-title">'
					+ '<svg viewBox="0 0 24 24"><path d="M13 7h-2v2h2V7zm0 4h-2v2h2v-2zm4 0h-2v2h2v-2zM3 3v18h18V3H3zm16 16H5V5h14v14zM9 7H7v2h2V7zm0 4H7v2h2v-2z"/></svg>'
					+ 'Sucursal <span style="font-weight:400;color:#9ca3af;font-size:11px;">(opcional)</span></div>'
					+ '<p class="fplms-fe-hint">Sin selección = aplica a todas las sucursales del canal</p>';

				var bIds = Object.keys(_branches);
				if ( bIds.length === 0 ) {
					html += '<p class="fplms-fe-empty">No hay sucursales configuradas para este canal.</p>';
				} else {
					html += '<div class="fplms-fe-chips" id="fplms-fe-branches">';
					bIds.forEach(function(bid) {
						var on = _selB.indexOf(Number(bid)) !== -1;
						html += '<div class="fplms-fe-chip' + (on ? ' sel' : '') + '" data-id="' + bid + '" data-type="branch">'
							+ '<input type="checkbox" value="' + bid + '"' + (on ? ' checked' : '') + '>' + esc(_branches[bid]) + '</div>';
					});
					html += '</div>';
				}

				html += '<hr class="fplms-fe-hr">';

				// Roles wrapper
				html += '<div class="fplms-fe-sec-title">'
					+ '<svg viewBox="0 0 24 24"><path d="M20 6h-2.18c.07-.44.18-.88.18-1 0-2.21-1.79-4-4-4s-4 1.79-4 4c0 .12.11.56.18 1H8c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h12c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6-3c1.1 0 2 .9 2 2 0 .12-.11.56-.18 1h-3.64C12.11 5.56 12 5.12 12 5c0-1.1.9-2 2-2zm6 17H8V8h2v1c0 .55.45 1 1 1h6c.55 0 1-.45 1-1V8h2v12z"/></svg>'
					+ 'Cargo <span style="font-weight:400;color:#9ca3af;font-size:11px;">(opcional)</span></div>'
					+ '<p class="fplms-fe-hint">Sin selección = aplica a todos los cargos de las sucursales elegidas</p>';
				html += '<div id="fplms-fe-roles-area">' + buildRolesHtml(_roles, _selR) + '</div>';

				document.getElementById('fplms-fe-inner').innerHTML = html;
				var btn = document.getElementById('fplms-fe-save');
				if ( btn ) btn.disabled = false;
				setStatus('');
			}

			function buildRolesHtml(rolesObj, selR) {
				var rIds = Object.keys(rolesObj);
				if ( rIds.length === 0 ) {
					return '<p class="fplms-fe-empty">Selecciona sucursales para ver los cargos disponibles.</p>';
				}
				var h = '<div class="fplms-fe-chips">';
				rIds.forEach(function(rid) {
					var on = selR.indexOf(Number(rid)) !== -1;
					h += '<div class="fplms-fe-chip' + (on ? ' sel' : '') + '" data-id="' + rid + '" data-type="role">'
						+ '<input type="checkbox" value="' + rid + '"' + (on ? ' checked' : '') + '>' + esc(rolesObj[rid]) + '</div>';
				});
				return h + '</div>';
			}

			// ── Reload roles when branches selection changes ──────────────────
			function reloadRoles() {
				var area = document.getElementById('fplms-fe-roles-area');
				if ( !area ) return;
				// If no branches selected, load roles of ALL channel branches.
				var branchIdsToLoad = _selB.length > 0 ? _selB : Object.keys(_branches).map(Number);
				if ( branchIdsToLoad.length === 0 ) {
					area.innerHTML = '<p class="fplms-fe-empty">Sin sucursales disponibles.</p>';
					return;
				}
				area.innerHTML = '<span class="fplms-fe-spin"></span>';
				var fd = new FormData();
				fd.append('action', 'fplms_get_branch_roles');
				fd.append('nonce',   NONCE);
				branchIdsToLoad.forEach(function(bid) { fd.append('branch_ids[]', bid); });
				fetch(AJAX_URL, { method:'POST', body:fd })
					.then(function(r){ return r.json(); })
					.then(function(d){
						if ( d && d.success ) {
							_roles = d.data || {};
							// Invalidate selections that no longer exist.
							var valid = Object.keys(_roles).map(Number);
							_selR = _selR.filter(function(r){ return valid.indexOf(r) !== -1; });
							area.innerHTML = buildRolesHtml(_roles, _selR);
						}
					})
					.catch(function(){});
			}

			// ── Save ─────────────────────────────────────────────────────────
			function doSave() {
				var btn = document.getElementById('fplms-fe-save');
				if ( !btn || btn.disabled ) return;
				syncSel();
				btn.disabled = true;
				setStatus('<span class="fplms-fe-spin"></span> Guardando…');
				var fd = new FormData();
				fd.append('action',    'fplms_save_frontend_structures');
				fd.append('nonce',      NONCE);
				fd.append('course_id',  COURSE_ID);
				_selB.forEach(function(b){ fd.append('branch_ids[]', b); });
				_selR.forEach(function(r){ fd.append('role_ids[]',   r); });
				fetch(AJAX_URL, { method:'POST', body:fd })
					.then(function(r){ return r.json(); })
					.then(function(d){
						btn.disabled = false;
						if ( d && d.success ) {
							setStatus('✓ ' + (d.data.message || 'Guardado.'), 'ok');
						} else {
							setStatus('✗ ' + (d.data || 'Error al guardar.'), 'err');
						}
					})
					.catch(function(){ btn.disabled = false; setStatus('✗ Error de red.', 'err'); });
			}

			// ── Load initial data ─────────────────────────────────────────────
			function loadData() {
				if ( !COURSE_ID ) return;
				var fd = new FormData();
				fd.append('action',    'fplms_get_frontend_structures');
				fd.append('nonce',      NONCE);
				fd.append('course_id',  COURSE_ID);
				fetch(AJAX_URL, { method:'POST', body:fd })
					.then(function(r){ return r.json(); })
					.then(function(d){
						if ( d && d.success ) {
							renderPanel(d.data);
						} else {
							var inner = document.getElementById('fplms-fe-inner');
							if (inner) inner.innerHTML = '<p style="color:#dc2626;font-size:13px;">No se pudieron cargar las estructuras. Verifica que el curso tenga un Canal (Categoría) asignado.</p>';
						}
					})
					.catch(function(){
						var inner = document.getElementById('fplms-fe-inner');
						if (inner) inner.innerHTML = '<p style="color:#dc2626;font-size:13px;">Error de red.</p>';
					});
			}

			function markDirty() { setStatus('Cambios sin guardar.'); }
			function setStatus(msg, cls) {
				var el = document.getElementById('fplms-fe-status');
				if (!el) return;
				el.innerHTML = msg;
				el.className = 'fplms-fe-status' + (cls ? ' ' + cls : '');
			}
			function esc(s) {
				return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
			}

			// ── Bootstrap ────────────────────────────────────────────────────
			if ( document.readyState === 'loading' ) {
				document.addEventListener('DOMContentLoaded', function(){ tryInject(); renameLabels(); loadData(); });
			} else {
				tryInject(); renameLabels(); loadData();
			}
		})();
		</script>
		<?php
	}

}
