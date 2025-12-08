<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FairPlay_LMS_Courses_Controller {

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
                    FairPlay_LMS_Config::ROLE_TUTOR,
                    'administrator',
                ],
                'number'   => 300,
                'orderby'  => 'display_name',
                'order'    => 'ASC',
            ]
        );

        return (array) $user_query->get_results();
    }

    /**
     * Listado de cursos MasterStudy con asignación de profesor.
     */
    private function render_course_list_view(): void {

        if ( ! post_type_exists( FairPlay_LMS_Config::MS_PT_COURSE ) ) {
            echo '<p><strong>Advertencia:</strong> No se encontró el tipo de contenido <code>' . esc_html( FairPlay_LMS_Config::MS_PT_COURSE ) . '</code>. Verifica que MasterStudy LMS esté activo.</p>';
            return;
        }

        $courses = get_posts(
            [
                'post_type'      => FairPlay_LMS_Config::MS_PT_COURSE,
                'posts_per_page' => 50,
                'post_status'    => 'publish',
            ]
        );

        if ( empty( $courses ) ) {
            echo '<p>No hay cursos creados todavía.</p>';
            return;
        }

        $instructors = $this->get_available_instructors();
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Curso</th>
                    <th>ID</th>
                    <th>Profesor actual</th>
                    <th>Asignar / cambiar profesor</th>
                    <th>Acciones</th>
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

                $teacher_id   = (int) get_post_meta( $course->ID, FairPlay_LMS_Config::MS_META_COURSE_TEACHER, true );
                $teacher_name = $teacher_id ? get_the_author_meta( 'display_name', $teacher_id ) : '';
                if ( ! $teacher_name ) {
                    $teacher_name = '— Sin asignar —';
                }
                ?>
                <tr>
                    <td><?php echo esc_html( get_the_title( $course ) ); ?></td>
                    <td><?php echo esc_html( $course->ID ); ?></td>
                    <td><?php echo esc_html( $teacher_name ); ?></td>
                    <td>
                        <form method="post" style="display:flex; gap:4px; align-items:center;">
                            <?php wp_nonce_field( 'fplms_courses_save', 'fplms_courses_nonce' ); ?>
                            <input type="hidden" name="fplms_courses_action" value="assign_instructor">
                            <input type="hidden" name="fplms_course_id" value="<?php echo esc_attr( $course->ID ); ?>">

                            <select name="fplms_instructor_id">
                                <option value="0">— Sin profesor —</option>
                                <?php foreach ( $instructors as $user ) : ?>
                                    <option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $teacher_id, $user->ID ); ?>>
                                        <?php echo esc_html( $user->display_name . ' (' . implode( ', ', $user->roles ) . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button type="submit" class="button button-primary">
                                Guardar
                            </button>
                        </form>
                    </td>
                    <td>
                        <a href="<?php echo esc_url( $modules_url ); ?>" class="button">Gestionar módulos</a>
                        <a href="<?php echo esc_url( get_edit_post_link( $course->ID ) ); ?>" class="button-secondary">Editar curso (MasterStudy)</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Vista de módulos por curso.
     */
    private function render_course_modules_view( int $course_id ): void {

        $course = get_post( $course_id );
        if ( ! $course || FairPlay_LMS_Config::MS_PT_COURSE !== $course->post_type ) {
            echo '<p>Curso no válido.</p>';
            return;
        }

        $back_url = add_query_arg( [ 'page' => 'fplms-courses' ], admin_url( 'admin.php' ) );

        echo '<p><a href="' . esc_url( $back_url ) . '">&larr; Volver al listado de cursos</a></p>';

        echo '<h2>Curso: ' . esc_html( get_the_title( $course ) ) . ' (ID ' . esc_html( $course->ID ) . ')</h2>';

        $modules = get_posts(
            [
                'post_type'   => FairPlay_LMS_Config::CPT_MODULE,
                'meta_key'    => FairPlay_LMS_Config::META_MODULE_COURSE,
                'meta_value'  => $course_id,
                'numberposts' => -1,
                'post_status' => 'publish',
                'orderby'     => 'menu_order',
                'order'       => 'ASC',
            ]
        );
        ?>
        <h3>Módulos del curso</h3>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Título del módulo</th>
                    <th>ID</th>
                    <th>Resumen</th>
                    <th>Temas</th>
                </tr>
            </thead>
            <tbody>
            <?php if ( ! empty( $modules ) ) : ?>
                <?php foreach ( $modules as $module ) : ?>
                    <?php
                    $topics_url = add_query_arg(
                        [
                            'page'      => 'fplms-courses',
                            'view'      => 'topics',
                            'course_id' => $course_id,
                            'module_id' => $module->ID,
                        ],
                        admin_url( 'admin.php' )
                    );

                    $topics_count = $this->count_topics_for_module( $module->ID );
                    ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url( $topics_url ); ?>">
                                <?php echo esc_html( get_the_title( $module ) ); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html( $module->ID ); ?></td>
                        <td><?php echo wp_kses_post( wp_trim_words( $module->post_content, 25 ) ); ?></td>
                        <td><?php echo esc_html( $topics_count ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="4">No hay módulos creados todavía para este curso.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>

        <h3 style="margin-top:2em;">Crear nuevo módulo</h3>
        <form method="post">
            <?php wp_nonce_field( 'fplms_courses_save', 'fplms_courses_nonce' ); ?>
            <input type="hidden" name="fplms_courses_action" value="create_module">
            <input type="hidden" name="fplms_course_id" value="<?php echo esc_attr( $course_id ); ?>">

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="fplms_module_title">Título del módulo</label></th>
                    <td>
                        <input name="fplms_module_title" id="fplms_module_title" type="text" class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="fplms_module_summary">Resumen</label></th>
                    <td>
                        <textarea name="fplms_module_summary" id="fplms_module_summary" class="large-text" rows="4"></textarea>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">Guardar módulo</button>
            </p>
        </form>
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
}
