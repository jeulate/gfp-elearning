<?php
/**
 * FairPlay LMS – Encuesta de Satisfacción de Curso (Escala Likert)
 *
 * Funcionalidades:
 *  – Meta box por curso: activar encuesta + configurar preguntas + etiqueta de escala.
 *  – Modal frontend (wpfooter): se muestra al alumno al completar el curso.
 *  – AJAX endpoints: check (¿hay encuesta pendiente?) y submit (guardar respuestas).
 *  – Tabla de resultados en el panel admin con estadísticas y exportación CSV.
 *  – Creación de tabla BD versionada (idempotente via dbDelta).
 *
 * @package FairPlay_LMS
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FairPlay_LMS_Survey {

    // ── Constantes ───────────────────────────────────────────────────────────────

    const DB_VERSION      = '1.1';
    const OPTION_DB_VER   = 'fplms_survey_db_version';
    const TABLE           = 'fplms_survey_responses';
    const META_ENABLED    = '_fplms_survey_enabled';
    const META_QUESTIONS  = '_fplms_survey_questions';
    const META_SCALE      = '_fplms_survey_scale_label';
    const META_MESSAGE    = '_fplms_survey_message';
    const NONCE_SUBMIT    = 'fplms_survey_submit';
    const NONCE_SETTINGS  = 'fplms_survey_settings';
    const NONCE_CHECK     = 'fplms_check_survey_nonce';
    const NONCE_METABOX   = 'fplms_survey_metabox_save';
    const USER_META_DONE  = '_fplms_survey_done_'; // + course_id

    /** Opciones fijas de la escala Likert 1–5. */
    private static array $SCALE = [
        1 => 'Muy insatisfecho/a',
        2 => 'Insatisfecho/a',
        3 => 'Neutral',
        4 => 'Satisfecho/a',
        5 => 'Muy satisfecho/a',
    ];

    // ── Base de Datos ────────────────────────────────────────────────────────────

    /**
     * Crea (o actualiza) la tabla de respuestas si la versión de la BD cambió.
     * Seguro de llamar en cada admin_init gracias a dbDelta.
     */
    public function maybe_create_table(): void {
        if ( get_option( self::OPTION_DB_VER ) !== self::DB_VERSION ) {
            if ( $this->create_table() ) {
                update_option( self::OPTION_DB_VER, self::DB_VERSION );
            }
        } else {
            $this->ensure_comment_column();
        }
    }

    public function create_table(): bool {
        global $wpdb;

        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            course_id    BIGINT(20) UNSIGNED NOT NULL,
            user_id      BIGINT(20) UNSIGNED NOT NULL,
            question_idx TINYINT UNSIGNED    NOT NULL DEFAULT 0,
            question     TEXT                NOT NULL,
            score        TINYINT UNSIGNED    NOT NULL,
            comment      VARCHAR(500)        NOT NULL DEFAULT '',
            submitted_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY course_id_idx    (course_id),
            KEY user_id_idx      (user_id),
            KEY submitted_at_idx (submitted_at)
        ) {$charset} ENGINE=InnoDB;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        if ( $wpdb->last_error !== '' ) {
            return false;
        }

        return $this->ensure_comment_column();
    }

    private function ensure_comment_column(): bool {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;
        $column_exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'comment' ) );
        if ( $column_exists ) {
            return true;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $added = $wpdb->query( "ALTER TABLE {$table} ADD COLUMN comment VARCHAR(500) NOT NULL DEFAULT '' AFTER score" );

        return false !== $added && '' === $wpdb->last_error;
    }

    // ── Meta Box (editor de curso) ───────────────────────────────────────────────

    public function register_metabox(): void {
        add_meta_box(
            'fplms_survey_box',
            'Encuesta de Satisfacción',
            [ $this, 'render_metabox' ],
            FairPlay_LMS_Config::MS_PT_COURSE,
            'normal',
            'default'
        );
    }

    public function render_metabox( WP_Post $post ): void {
        wp_nonce_field( self::NONCE_METABOX, 'fplms_survey_nonce' );

        $enabled     = (bool) get_post_meta( $post->ID, self::META_ENABLED, true );
        $raw         = get_post_meta( $post->ID, self::META_QUESTIONS, true );
        $questions   = is_array( $raw ) ? $raw : ( json_decode( $raw ?: '[]', true ) ?: [] );
        $scale_label = get_post_meta( $post->ID, self::META_SCALE, true ) ?: 'Nivel de satisfacción';

        if ( empty( $questions ) ) {
            $questions = [ '' ];
        }
        ?>
        <style>
        .fplms-sv-box { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding-top: 4px; }
        .fplms-sv-toggle-row { display:flex; align-items:center; gap:12px; padding:10px 0 14px; border-bottom:1px solid #e5e7eb; margin-bottom:18px; }
        .fplms-sv-toggle-label { font-size:14px; font-weight:600; color:#374151; }
        .fplms-sv-toggle { position:relative; display:inline-block; width:44px; height:24px; flex-shrink:0; }
        .fplms-sv-toggle input { opacity:0; width:0; height:0; }
        .fplms-sv-toggle-slider { position:absolute; inset:0; background:#d1d5db; border-radius:24px; cursor:pointer; transition:.3s; }
        .fplms-sv-toggle-slider::before { content:''; position:absolute; width:18px; height:18px; left:3px; top:3px; background:#fff; border-radius:50%; transition:.3s; }
        .fplms-sv-toggle input:checked + .fplms-sv-toggle-slider { background:#667eea; }
        .fplms-sv-toggle input:checked + .fplms-sv-toggle-slider::before { transform:translateX(20px); }
        .fplms-sv-section { margin-bottom:18px; }
        .fplms-sv-section-title { font-size:12px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px; }
        .fplms-sv-question-row { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
        .fplms-sv-question-row input[type=text] { flex:1; padding:7px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; color:#111827; }
        .fplms-sv-question-row input[type=text]:focus { border-color:#667eea; outline:none; box-shadow:0 0 0 2px rgba(102,126,234,.2); }
        .fplms-sv-remove-btn { width:28px; height:28px; border:none; border-radius:6px; background:#fee2e2; color:#ef4444; cursor:pointer; font-size:18px; line-height:1; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; padding:0; }
        .fplms-sv-remove-btn:hover { background:#fecaca; }
        .fplms-sv-add-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border:1px dashed #667eea; border-radius:6px; background:transparent; color:#667eea; font-size:13px; cursor:pointer; margin-top:2px; }
        .fplms-sv-add-btn:hover { background:#f5f3ff; }
        .fplms-sv-scale-row { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
        .fplms-sv-scale-row input[type=text] { padding:7px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; width:240px; }
        .fplms-sv-scale-chips { display:flex; gap:6px; flex-wrap:wrap; margin-top:8px; }
        .fplms-sv-scale-chip { padding:3px 10px; border-radius:20px; background:#f3f4f6; border:1px solid #e5e7eb; font-size:12px; color:#374151; }
        </style>
        <div class="fplms-sv-box">

            <!-- Toggle activar/desactivar -->
            <div class="fplms-sv-toggle-row">
                <label class="fplms-sv-toggle">
                    <input type="checkbox" name="fplms_survey_enabled" value="1" <?php checked( $enabled ); ?>>
                    <span class="fplms-sv-toggle-slider"></span>
                </label>
                <span class="fplms-sv-toggle-label">Activar encuesta de satisfacción al completar este curso</span>
            </div>

            <!-- Preguntas -->
            <div class="fplms-sv-section">
                <div class="fplms-sv-section-title">Preguntas</div>
                <div id="fplms-sv-questions">
                    <?php foreach ( $questions as $q ) : ?>
                    <div class="fplms-sv-question-row">
                        <input type="text" name="fplms_survey_questions[]" value="<?php echo esc_attr( $q ); ?>" placeholder="Escribe la pregunta…">
                        <button type="button" class="fplms-sv-remove-btn" title="Eliminar pregunta">&times;</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="fplms-sv-add-btn" id="fplms-sv-add-q">
                    <svg viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;"><path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"/></svg>
                    Agregar pregunta
                </button>
            </div>

            <!-- Escala -->
            <div class="fplms-sv-section">
                <div class="fplms-sv-section-title">Respuestas (escala Likert fija 1–5)</div>
                <div class="fplms-sv-scale-row">
                    <label style="font-size:13px;color:#374151;">Nombre de la escala:
                        <input type="text" name="fplms_survey_scale_label" value="<?php echo esc_attr( $scale_label ); ?>" placeholder="Ej: Nivel de satisfacción">
                    </label>
                </div>
                <div class="fplms-sv-scale-chips">
                    <?php foreach ( self::$SCALE as $val => $lbl ) : ?>
                    <span class="fplms-sv-scale-chip"><?php echo esc_html( $val . ' – ' . $lbl ); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

        </div><!-- .fplms-sv-box -->
        <script>
        (function(){
            var container = document.getElementById('fplms-sv-questions');

            document.getElementById('fplms-sv-add-q').addEventListener('click', function() {
                var row = document.createElement('div');
                row.className = 'fplms-sv-question-row';
                row.innerHTML = '<input type="text" name="fplms_survey_questions[]" placeholder="Escribe la pregunta\u2026" style="flex:1;padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">'
                              + '<button type="button" class="fplms-sv-remove-btn" title="Eliminar">\u00d7</button>';
                container.appendChild(row);
                row.querySelector('input').focus();
            });

            container.addEventListener('click', function(e) {
                if ( e.target.classList.contains('fplms-sv-remove-btn') ) {
                    var rows = container.querySelectorAll('.fplms-sv-question-row');
                    if ( rows.length > 1 ) {
                        e.target.closest('.fplms-sv-question-row').remove();
                    }
                }
            });
        })();
        </script>
        <?php
    }

    public function save_metabox( int $post_id, WP_Post $post, bool $update ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! isset( $_POST['fplms_survey_nonce'] ) ) return;
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fplms_survey_nonce'] ) ), self::NONCE_METABOX ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( $post->post_type !== FairPlay_LMS_Config::MS_PT_COURSE ) return;

        $enabled = isset( $_POST['fplms_survey_enabled'] ) ? '1' : '';
        update_post_meta( $post_id, self::META_ENABLED, $enabled );

        $questions = [];
        if ( ! empty( $_POST['fplms_survey_questions'] ) && is_array( $_POST['fplms_survey_questions'] ) ) {
            foreach ( wp_unslash( $_POST['fplms_survey_questions'] ) as $q ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
                $q = sanitize_text_field( $q );
                if ( $q !== '' ) {
                    $questions[] = $q;
                }
            }
        }
        update_post_meta( $post_id, self::META_QUESTIONS, $questions );

        $scale_label = isset( $_POST['fplms_survey_scale_label'] )
            ? sanitize_text_field( wp_unslash( $_POST['fplms_survey_scale_label'] ) )
            : 'Nivel de satisfacción';
        update_post_meta( $post_id, self::META_SCALE, $scale_label );
    }

    // ── AJAX: verificar si hay encuesta pendiente ────────────────────────────────

    public function ajax_check_survey(): void {
        check_ajax_referer( self::NONCE_CHECK, 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'reason' => 'not_logged_in' ] );
        }

        $course_id = (int) ( $_POST['course_id'] ?? 0 );
        if ( $course_id <= 0 ) {
            wp_send_json_error( [ 'reason' => 'no_course' ] );
        }

        $user_id = get_current_user_id();

        if ( get_user_meta( $user_id, self::USER_META_DONE . $course_id, true ) ) {
            wp_send_json_error( [ 'reason' => 'already_done' ] );
        }
        if ( ! get_post_meta( $course_id, self::META_ENABLED, true ) ) {
            wp_send_json_error( [ 'reason' => 'not_enabled' ] );
        }
        if ( ! $this->is_course_complete( $user_id, $course_id ) ) {
            wp_send_json_error( [ 'reason' => 'not_complete' ] );
        }

        $questions = $this->get_questions( $course_id );
        if ( empty( $questions ) ) {
            wp_send_json_error( [ 'reason' => 'no_questions' ] );
        }

        wp_send_json_success( [
            'course_id'    => $course_id,
            'course_title' => get_the_title( $course_id ),
            'questions'    => $questions,
            'scale_label'  => get_post_meta( $course_id, self::META_SCALE, true ) ?: 'Nivel de satisfacción',
            'scale'        => self::$SCALE,
        ] );
    }

    // ── AJAX: guardar respuestas ─────────────────────────────────────────────────

    public function ajax_submit_survey(): void {
        check_ajax_referer( self::NONCE_SUBMIT, 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'not_logged_in' );
        }

        $course_id = (int) ( $_POST['course_id'] ?? 0 );
        if ( $course_id <= 0 ) {
            wp_send_json_error( 'invalid_course' );
        }

        $user_id = get_current_user_id();

        // Idempotente: si ya envió, devolver éxito sin duplicar
        if ( get_user_meta( $user_id, self::USER_META_DONE . $course_id, true ) ) {
            if ( $this->has_saved_submission( $user_id, $course_id ) ) {
                wp_send_json_success( [ 'msg' => 'already_done' ] );
                return;
            }

            delete_user_meta( $user_id, self::USER_META_DONE . $course_id );
        }

        if ( ! isset( $_POST['responses'] ) || ! is_array( $_POST['responses'] ) || empty( $_POST['responses'] ) ) {
            wp_send_json_error( 'no_responses' );
        }

        $comment = isset( $_POST['comment'] )
            ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) )
            : '';
        if ( strlen( $comment ) > 500 ) {
            $comment = substr( $comment, 0, 500 );
        }

        $questions = $this->get_questions( $course_id );

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $now   = current_time( 'mysql' );
        if ( ! $this->ensure_comment_column() ) {
            wp_send_json_error( 'comment_column_missing' );
        }

        $inserted = 0;

        foreach ( $_POST['responses'] as $idx => $score ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            $idx   = (int) $idx;
            $score = (int) $score;

            if ( $score < 1 || $score > 5 ) continue;

            $ok = $wpdb->insert(
                $table,
                [
                    'course_id'    => $course_id,
                    'user_id'      => $user_id,
                    'question_idx' => $idx,
                    'question'     => $questions[ $idx ] ?? '',
                    'score'        => $score,
                    'comment'      => $comment,
                    'submitted_at' => $now,
                ],
                [ '%d', '%d', '%d', '%s', '%d', '%s', '%s' ]
            );

            if ( false !== $ok ) {
                $inserted++;
            }
        }

        if ( $inserted < 1 ) {
            wp_send_json_error( 'save_failed' );
        }

        update_user_meta( $user_id, self::USER_META_DONE . $course_id, '1' );
        wp_send_json_success( [ 'msg' => 'saved' ] );
    }

    // ── Toggle survey enabled/disabled from admin courses list ───────────────────

    public function ajax_toggle_survey(): void {
        check_ajax_referer( 'fplms_toggle_survey', 'nonce' );

        if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_COURSES ) ) {
            wp_send_json_error( 'no_caps', 403 );
        }

        $course_id = (int) ( $_POST['course_id'] ?? 0 );
        if ( $course_id <= 0 ) {
            wp_send_json_error( 'invalid_course' );
        }

        $current = (bool) get_post_meta( $course_id, self::META_ENABLED, true );
        $new_val = ! $current;
        update_post_meta( $course_id, self::META_ENABLED, $new_val ? '1' : '' );
        wp_send_json_success( [ 'enabled' => $new_val ] );
    }

    // ── Get survey settings for admin panel ──────────────────────────────────

    public function ajax_get_survey_settings(): void {
        check_ajax_referer( self::NONCE_SETTINGS, 'nonce' );

        if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_COURSES ) ) {
            wp_send_json_error( 'no_caps', 403 );
        }

        $course_id = (int) ( $_POST['course_id'] ?? 0 );
        if ( $course_id <= 0 ) {
            wp_send_json_error( 'invalid_course' );
        }

        $raw       = get_post_meta( $course_id, self::META_QUESTIONS, true );
        $questions = is_array( $raw ) ? $raw : ( json_decode( $raw ?: '[]', true ) ?: [] );
        if ( empty( $questions ) ) {
            $questions = [ '' ];
        }

        wp_send_json_success( [
            'course_id'   => $course_id,
            'course_title'=> get_the_title( $course_id ),
            'enabled'     => (bool) get_post_meta( $course_id, self::META_ENABLED, true ),
            'message'     => get_post_meta( $course_id, self::META_MESSAGE, true ) ?: '',
            'questions'   => array_values( $questions ),
            'scale_label' => get_post_meta( $course_id, self::META_SCALE, true ) ?: 'Nivel de satisfacción',
            'scale'       => self::$SCALE,
        ] );
    }

    // ── Save survey settings from admin panel ───────────────────────────────

    public function ajax_save_survey_settings(): void {
        check_ajax_referer( self::NONCE_SETTINGS, 'nonce' );

        if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_COURSES ) ) {
            wp_send_json_error( 'no_caps', 403 );
        }

        $course_id = (int) ( $_POST['course_id'] ?? 0 );
        if ( $course_id <= 0 ) {
            wp_send_json_error( 'invalid_course' );
        }

        $message = wp_kses_post( wp_unslash( $_POST['message'] ?? '' ) );
        update_post_meta( $course_id, self::META_MESSAGE, $message );

        $questions = [];
        if ( ! empty( $_POST['questions'] ) && is_array( $_POST['questions'] ) ) {
            foreach ( wp_unslash( $_POST['questions'] ) as $q ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
                $q = sanitize_text_field( $q );
                if ( $q !== '' ) {
                    $questions[] = $q;
                }
            }
        }
        update_post_meta( $course_id, self::META_QUESTIONS, $questions );

        $scale_label = sanitize_text_field( wp_unslash( $_POST['scale_label'] ?? 'Nivel de satisfacción' ) );
        update_post_meta( $course_id, self::META_SCALE, $scale_label );

        $enabled = isset( $_POST['enabled'] ) && '1' === $_POST['enabled'];
        update_post_meta( $course_id, self::META_ENABLED, $enabled ? '1' : '' );

        wp_send_json_success( [ 'enabled' => $enabled ] );
    }

    // ── Inyección de script en el footer del frontend ────────────────────────────

    public function inject_survey_script(): void {
        if ( ! is_user_logged_in() || is_admin() ) return;

        $user_id   = get_current_user_id();
        $course_id = $this->detect_course_id();
        if ( ! $course_id ) return;

        if ( ! get_post_meta( $course_id, self::META_ENABLED, true ) ) return;

        $already_done = (bool) get_user_meta( $user_id, self::USER_META_DONE . $course_id, true );
        if ( $already_done && ! $this->has_saved_submission( $user_id, $course_id ) ) {
            delete_user_meta( $user_id, self::USER_META_DONE . $course_id );
            $already_done = false;
        }

        $questions = $this->get_questions( $course_id );
        // If no questions and already done, nothing to show
        if ( empty( $questions ) && ! $already_done ) return;
        if ( empty( $questions ) ) return;

        $scale_label  = get_post_meta( $course_id, self::META_SCALE, true ) ?: 'Nivel de satisfacción';
        $course_title = get_the_title( $course_id );

        $message = (string) ( get_post_meta( $course_id, self::META_MESSAGE, true ) ?: '' );

        $data_js = wp_json_encode( [
            'courseId'    => $course_id,
            'courseTitle' => $course_title,
            'questions'   => array_values( $questions ),
            'scaleLabel'  => $scale_label,
            'scale'       => self::$SCALE,
            'message'     => $message,
            'alreadyDone' => $already_done,
            'nonceSubmit' => wp_create_nonce( self::NONCE_SUBMIT ),
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
        ] );
        ?>
        <style>
            /* ── Encuesta de satisfacción – adaptable a modo claro/oscuro ── */
            @keyframes fplmsSvFadeIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }

            .fplms-sv-inline {
                margin: 16px 0 16px; width: 100%;
                animation: fplmsSvFadeIn .35s ease;
            }

            .fplms-sv-inline__divider {
                display: flex; align-items: center; gap: 10px; margin-bottom: 14px;
            }
            .fplms-sv-inline__divider::before,
            .fplms-sv-inline__divider::after {
                content: ''; flex: 1; height: 1px; 
                background: #e5e7eb;
            }
            .fplms-sv-inline__divider-label {
                font-size: 11px; font-weight: 600; 
                color: #6b7280;
                text-transform: uppercase; letter-spacing: .6px; white-space: nowrap;
            }

            .fplms-sv-inline__message {
                font-family: var(--e-global-typography-text-font-family, "Roboto"), sans-serif;
                font-weight: var(--e-global-typography-text-font-weight, 400);
                font-size: 13px; 
                color: #4b5563;
                line-height: 1.6; text-align: center; margin-bottom: 14px;
            }

            .fplms-sv-question {
                background: #f8fafc;
                border: 1px solid #e5e7eb;
                border-radius: 10px; padding: 12px 14px; margin-bottom: 10px;
            }
            .fplms-sv-question__text {
                font-size: 13px; font-weight: 500; 
                color: #1f2937;
                margin-bottom: 10px; line-height: 1.5;
            }

            .fplms-sv-scale { display: flex; gap: 5px; flex-wrap: wrap; }
            .fplms-sv-opt { flex: 1; min-width: 42px; }
            .fplms-sv-opt input[type=radio] { display: none; }
            .fplms-sv-opt label {
                cursor: pointer; display: flex; flex-direction: column; align-items: center;
                gap: 3px; padding: 7px 4px; border-radius: 8px;
                border: 1.5px solid #d1d5db;
                width: 100%; text-align: center; transition: border-color .15s, background .15s;
                background: #ffffff;
                box-sizing: border-box;
            }
            .fplms-sv-opt label .sv-num { 
                font-size: 14px; font-weight: 700; 
                color: #374151; 
            }
            .fplms-sv-opt label .sv-lbl { 
                font-size: 9px; 
                color: #6b7280; 
                line-height: 1.2; 
            }
            .fplms-sv-opt input:checked + label { 
                border-color: #f5a623; 
                background: #fffbeb; 
            }
            .fplms-sv-opt input:checked + label .sv-num { 
                color: #f5a623; 
            }
            .fplms-sv-opt label:hover { 
                border-color: #f5a623; 
                background: #fffbeb; 
            }

            .fplms-sv-inline__actions {
                display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 14px;
            }
            .fplms-sv-comment {
                margin-top: 12px;
                background: #f8fafc;
                border: 1px solid #e5e7eb;
                border-radius: 10px; padding: 12px 14px;
            }
            .fplms-sv-comment__label {
                display: flex; align-items: center; justify-content: space-between; gap: 8px;
                margin-bottom: 8px; font-size: 12px; font-weight: 600; 
                color: #374151;
            }
            .fplms-sv-comment__hint { 
                font-size: 10px; 
                color: #6b7280; 
            }
            .fplms-sv-comment textarea {
                width: 100%; min-height: 92px; resize: vertical; box-sizing: border-box;
                border-radius: 8px; 
                border: 1.5px solid #d1d5db;
                background: #ffffff;
                color: #1f2937;
                padding: 10px 12px; font-size: 13px; line-height: 1.45;
            }
            .fplms-sv-comment textarea::placeholder { 
                color: #9ca3af; 
            }
            .fplms-sv-comment textarea:focus {
                outline: none; border-color: #f5a623; 
                box-shadow: 0 0 0 2px rgba(245,166,35,.16);
            }
            .fplms-sv-comment__footer {
                margin-top: 7px; display: flex; justify-content: space-between; gap: 8px;
                font-size: 11px; 
                color: #6b7280;
            }
            .fplms-sv-comment__count.is-limit { 
                color: #dc2626; 
            }

            .fplms-sv-submit {
                flex: 1; display: inline-flex; align-items: center; justify-content: center;
                gap: 7px; background: #f5a623;
                color: #ffffff; border: none; border-radius: 8px; padding: 10px 20px;
                font-size: 13px; font-weight: 600; cursor: pointer; transition: opacity .2s;
            }
            .fplms-sv-submit:hover { 
                background: #e8941a; 
            }
            .fplms-sv-submit:disabled { opacity: .5; cursor: not-allowed; }

            .fplms-sv-error { 
                color: #dc2626; 
                font-size: 12px; margin-top: 8px; text-align: center; 
            }

            .fplms-sv-success-inline {
                text-align: center; padding: 16px 0;
                animation: fplmsSvFadeIn .3s ease;
            }
            .fplms-sv-success-inline__icon {
                width: 48px; height: 48px; border-radius: 50%;
                background: rgba(16,185,129,.15); 
                border: 2px solid rgba(16,185,129,.4);
                margin: 0 auto 10px; display: flex; align-items: center; justify-content: center;
            }
            .fplms-sv-success-inline__title { 
                font-size: 15px; font-weight: 700; 
                color: #1f2937; 
                margin-bottom: 4px; 
            }
            .fplms-sv-success-inline__msg { 
                font-size: 12px; 
                color: #6b7280; 
            }

            .fplms-sv-done-badge {
                display: inline-flex; align-items: center; gap: 7px;
                background: rgba(16,185,129,.12); 
                border: 1px solid rgba(16,185,129,.3);
                border-radius: 20px; padding: 7px 14px; margin: 16px auto;
                font-family: var(--e-global-typography-text-font-family, "Roboto"), sans-serif;
                font-size: 12px; font-weight: 500; 
                color: #065f46;
                animation: fplmsSvFadeIn .35s ease;
            }
            .fplms-sv-done-badge svg { flex-shrink: 0; }
            .fplms-sv-done-wrap { text-align: center; width: 100%; }

            /* ============================================================
            🌙 MODO OSCURO - Detectado por clase en el body
            ============================================================ */
            /* Estilos oscuros para la encuesta cuando el course player está en modo oscuro */
            .masterstudy-course-player-content_dark-mode .fplms-sv-inline__divider::before,
            .masterstudy-course-player-content_dark-mode .fplms-sv-inline__divider::after {
                background: rgba(255,255,255,.12);
            }
            .masterstudy-course-player-content_dark-mode .fplms-sv-inline__divider-label {
                color: rgba(255,255,255,.5);
            }
            .masterstudy-course-player-content_dark-mode .fplms-sv-inline__message {
                color: rgba(255,255,255,.7);
            }
            .masterstudy-course-player-content_dark-mode .fplms-sv-question {
                background: rgba(255,255,255,.07);
                border-color: rgba(255,255,255,.12);
            }
            .masterstudy-course-player-content_dark-mode .fplms-sv-question__text {
                color: rgba(255,255,255,.9);
            }
            .masterstudy-course-player-content_dark-mode .fplms-sv-opt label {
                border-color: rgba(255,255,255,.18);
                background: rgba(255,255,255,.05);
            }
            .masterstudy-course-player-content_dark-mode .fplms-sv-opt label .sv-num {
                color: rgba(255,255,255,.8);
            }
            .masterstudy-course-player-content_dark-mode .fplms-sv-opt label .sv-lbl {
                color: rgba(255,255,255,.45);
            }
            .masterstudy-course-player-content_dark-mode .fplms-sv-opt input:checked + label {
                background: rgba(245,166,35,.2);
            }
            .masterstudy-course-player-content_dark-mode .fplms-sv-opt label:hover {
                background: rgba(245,166,35,.1);
            }
            .masterstudy-course-player-content_dark-mode .fplms-sv-comment {
                background: rgba(255,255,255,.07);
                border-color: rgba(255,255,255,.12);
            }
            .masterstudy-course-player-content_dark-mode .fplms-sv-comment__label {
                color: rgba(255,255,255,.82);
            }
            .masterstudy-course-player-content_dark-mode .fplms-sv-comment__hint {
                color: rgba(255,255,255,.5);
            }
            .masterstudy-course-player-content_dark-mode .fplms-sv-comment textarea {
                background: rgba(17,24,39,.32);
                color: #ffffff;
                border-color: rgba(255,255,255,.16);
            }
            .masterstudy-course-player-content_dark-mode .fplms-sv-comment textarea::placeholder {
                color: rgba(255,255,255,.42);
            }
            .masterstudy-course-player-content_dark-mode .fplms-sv-comment__footer {
                color: rgba(255,255,255,.5);
            }
            .masterstudy-course-player-content_dark-mode .fplms-sv-comment__count.is-limit {
                color: #fca5a5;
            }
            .masterstudy-course-player-content_dark-mode .fplms-sv-error {
                color: #ffb3b3;
            }
            .masterstudy-course-player-content_dark-mode .fplms-sv-success-inline__title {
                color: #ffffff;
            }
            .masterstudy-course-player-content_dark-mode .fplms-sv-success-inline__msg {
                color: rgba(255,255,255,.6);
            }
            .masterstudy-course-player-content_dark-mode .fplms-sv-done-badge {
                color: #6ee7b7;
                border-color: rgba(16,185,129,.4);
                background: rgba(16,185,129,.15);
            }
            </style>
        <script>
        (function(){
            var D = <?php echo $data_js; // phpcs:ignore WordPress.Security.EscapeOutput -- json_encoded ?>;
     
           function detectTheme() {
            var root = document.documentElement;
            var isDark = false;
            
            // 1. Verificar clase en el body
            if (document.body.classList.contains('dark-mode') || 
                document.body.classList.contains('dark') ||
                document.body.classList.contains('ms-theme-dark')) {
                isDark = true;
            }
            
            // 2. Verificar data attribute
            if (document.body.dataset.theme === 'dark') {
                isDark = true;
            }
            
            // 3. Verificar el overlay del modal de completado (MÁS IMPORTANTE)
            var completeModal = document.getElementById('masterstudy-single-course-complete');
            if (completeModal) {
                var modalBg = window.getComputedStyle(completeModal).background;
                // Si el modal tiene fondo oscuro (rgba con alpha), es modo oscuro
                if (modalBg.indexOf('rgba') !== -1) {
                    var match = modalBg.match(/\d+/g);
                    if (match && parseInt(match[0]) < 50) {
                        isDark = true;
                    }
                }
                // Verificar si el modal está activo y tiene fondo oscuro
                if (completeModal.classList.contains('masterstudy-single-course-complete_active')) {
                    var overlay = completeModal.querySelector('.masterstudy-single-course-complete__wrapper') || completeModal;
                    var overlayBg = window.getComputedStyle(overlay).backgroundColor;
                    if (overlayBg && overlayBg !== 'rgba(0, 0, 0, 0)') {
                        var rgb = overlayBg.match(/\d+/g);
                        if (rgb && (parseInt(rgb[0]) + parseInt(rgb[1]) + parseInt(rgb[2])) < 300) {
                            isDark = true;
                        }
                    }
                }
            }
            
            // 4. Verificar el fondo del .masterstudy-single-course-complete__success
            var success = document.querySelector('.masterstudy-single-course-complete__success');
            if (success && !isDark) {
                var successBg = window.getComputedStyle(success).backgroundColor;
                if (successBg && successBg !== 'rgba(0, 0, 0, 0)') {
                    var rgb = successBg.match(/\d+/g);
                    if (rgb && (parseInt(rgb[0]) + parseInt(rgb[1]) + parseInt(rgb[2])) < 300) {
                        isDark = true;
                    }
                }
            }
            
            // 5. Verificar color de fondo del body (solo si no se detectó antes)
            if (!isDark) {
                var bgColor = window.getComputedStyle(document.body).backgroundColor;
                var rgb = bgColor.match(/\d+/g);
                if (rgb) {
                    var brightness = (parseInt(rgb[0]) * 299 + parseInt(rgb[1]) * 587 + parseInt(rgb[2]) * 114) / 1000;
                    if (brightness < 128) {
                        isDark = true;
                    }
                }
            }
            
            // 6. Verificar variable CSS de MasterStudy (si existe)
            if (!isDark) {
                var themeVar = getComputedStyle(document.documentElement).getPropertyValue('--ms-theme-mode');
                if (themeVar && themeVar.trim() === 'dark') {
                    isDark = true;
                }
            }
            
            // 7. Último recurso: verificar preferencia del sistema
            if (!isDark && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                isDark = true;
            }
            return isDark;
        }

            // Aplicar variables CSS según el tema
            function applyThemeVariables(isDark) {
                var root = document.documentElement;
                if (isDark) {
                    root.style.setProperty('--fplms-sv-text-primary', 'rgba(255,255,255,.9)');
                    root.style.setProperty('--fplms-sv-text-secondary', 'rgba(255,255,255,.5)');
                    root.style.setProperty('--fplms-sv-border-color', 'rgba(255,255,255,.12)');
                    root.style.setProperty('--fplms-sv-card-bg', 'rgba(255,255,255,.07)');
                    root.style.setProperty('--fplms-sv-radio-bg', 'rgba(255,255,255,.05)');
                    root.style.setProperty('--fplms-sv-input-bg', 'rgba(17,24,39,.32)');
                    root.style.setProperty('--fplms-sv-error-color', '#ffb3b3');
                    root.style.setProperty('--fplms-sv-success-color', '#6ee7b7');
                } else {
                    root.style.setProperty('--fplms-sv-text-primary', 'rgba(0,0,0,.85)');
                    root.style.setProperty('--fplms-sv-text-secondary', 'rgba(0,0,0,.45)');
                    root.style.setProperty('--fplms-sv-border-color', 'rgba(0,0,0,.12)');
                    root.style.setProperty('--fplms-sv-card-bg', 'rgba(0,0,0,.04)');
                    root.style.setProperty('--fplms-sv-radio-bg', 'rgba(0,0,0,.03)');
                    root.style.setProperty('--fplms-sv-input-bg', '#f9fafb');
                    root.style.setProperty('--fplms-sv-error-color', '#dc2626');
                    root.style.setProperty('--fplms-sv-success-color', '#065f46');
                }
            }

            // Detectar y aplicar tema
            var isDark = detectTheme();
            applyThemeVariables(isDark);

            // Observar cambios de tema (si el usuario cambia el tema dinámicamente)
            var themeObserver = new MutationObserver(function() {
                var newIsDark = detectTheme();
                if (newIsDark !== isDark) {
                    isDark = newIsDark;
                    applyThemeVariables(isDark);
                }
            });
            themeObserver.observe(document.body, {
                attributes: true,
                attributeFilter: ['class', 'data-theme']
            }); 
            
            function esc(s) {
                return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }

            /* ──── Construir HTML de preguntas ──── */
            function buildQuestionsHtml() {
                var html = '';
                D.questions.forEach(function(q, i) {
                    var qName  = 'fplms_q_' + i;
                    var scaleH = '';
                    [1,2,3,4,5].forEach(function(val) {
                        scaleH += '<div class="fplms-sv-opt">'
                            + '<input type="radio" name="' + qName + '" id="' + qName + '_' + val + '" value="' + val + '">'
                            + '<label for="' + qName + '_' + val + '">'
                            + '<span class="sv-num">' + val + '</span>'
                            + '<span class="sv-lbl">' + esc(D.scale[val]) + '</span>'
                            + '</label></div>';
                    });
                    html += '<div class="fplms-sv-question">'
                        + '<div class="fplms-sv-question__text">' + (i + 1) + '. ' + esc(q) + '</div>'
                        + '<div class="fplms-sv-scale">' + scaleH + '</div>'
                        + '</div>';
                });
                return html;
            }

            /* ──── Inyectar encuesta dentro del modal de MasterStudy ──── */
            function injectInline() {
                if (document.getElementById('fplms-sv-inline')) return;

                // Contenedor de éxito y div de botones dentro del modal de MasterStudy
                var success = document.querySelector('.masterstudy-single-course-complete__success');
                var buttons = document.querySelector('.masterstudy-single-course-complete__buttons');
                if (!success || !buttons) return;

                // Si el alumno ya envió la encuesta, mostrar badge y salir
                if (D.alreadyDone) {
                    buttons.insertAdjacentHTML('beforebegin',
                        '<div class="fplms-sv-inline" id="fplms-sv-inline">'
                        + '<div class="fplms-sv-done-wrap">'
                        + '<span class="fplms-sv-done-badge">'
                        + '<svg viewBox="0 0 24 24" fill="#6ee7b7" width="15" height="15"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>'
                        + 'Tu encuesta de satisfacci\u00f3n fue enviada'
                        + '</span>'
                        + '</div>'
                        + '</div>'
                    );
                    var stat = document.querySelector('.masterstudy-single-course-complete__curiculum-statistic');
                    if (stat) stat.style.display = 'none';
                    return;
                }

                buttons.insertAdjacentHTML('beforebegin',
                    '<div class="fplms-sv-inline" id="fplms-sv-inline">'
                    + '<div class="fplms-sv-inline__divider"><span class="fplms-sv-inline__divider-label">Encuesta de satisfacci\u00f3n</span></div>'
                    + (D.message ? '<div class="fplms-sv-inline__message">' + esc(D.message) + '</div>' : '')
                    + '<div id="fplms-sv-body">'
                    + buildQuestionsHtml()
                    + '<div class="fplms-sv-comment">'
                    + '<label class="fplms-sv-comment__label" for="fplms-sv-comment">'
                    + '<span>Comentario o sugerencia</span>'
                    + '<span class="fplms-sv-comment__hint">Opcional</span>'
                    + '</label>'
                    + '<textarea id="fplms-sv-comment" maxlength="500" placeholder="Escribe una sugerencia breve sobre el curso o la experiencia de aprendizaje (m\u00e1x. 500 caracteres)..."></textarea>'
                    + '<div class="fplms-sv-comment__footer">'
                    + '<span>Tu comentario ayudar\u00e1 a mejorar futuras capacitaciones.</span>'
                    + '<span class="fplms-sv-comment__count" id="fplms-sv-comment-count">0 / 500</span>'
                    + '</div>'
                    + '</div>'
                    + '<div id="fplms-sv-error" class="fplms-sv-error" style="display:none;"></div>'
                    + '</div>'
                    + '<div class="fplms-sv-inline__actions">'
                    + '<button type="button" class="fplms-sv-submit" id="fplms-sv-submit">'
                    + '<svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14" style="flex-shrink:0;"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/></svg>'
                    + 'Enviar encuesta'
                    + '</button>'
                    + '</div>'
                    + '</div>'
                );

                document.getElementById('fplms-sv-submit').addEventListener('click', submitSurvey);
                initCommentCounter();

                // Ocultar estadísticas del currículo para dar espacio a la encuesta
                var stat = document.querySelector('.masterstudy-single-course-complete__curiculum-statistic');
                if (stat) stat.style.display = 'none';
            }

            function initCommentCounter() {
                var textarea = document.getElementById('fplms-sv-comment');
                var counter  = document.getElementById('fplms-sv-comment-count');
                if (!textarea || !counter) return;
                var sync = function() {
                    if (textarea.value.length > 500) {
                        textarea.value = textarea.value.slice(0, 500);
                    }
                    counter.textContent = textarea.value.length + ' / 500';
                    counter.classList.toggle('is-limit', textarea.value.length >= 500);
                };
                textarea.addEventListener('input', sync);
                sync();
            }

            function removeSurvey() {
                var el = document.getElementById('fplms-sv-inline');
                if (el) el.parentNode.removeChild(el);
                // Restaurar estadísticas del currículo
                var stat = document.querySelector('.masterstudy-single-course-complete__curiculum-statistic');
                if (stat) stat.style.display = '';
            }

            function showSuccessInline() {
                var body    = document.getElementById('fplms-sv-body');
                var actions = document.querySelector('.fplms-sv-inline__actions');
                if (!body) return;
                body.innerHTML = '<div class="fplms-sv-success-inline">'
                    + '<div class="fplms-sv-success-inline__icon">'
                    + '<svg viewBox="0 0 24 24" fill="#10b981" width="24" height="24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>'
                    + '</div>'
                    + '<div class="fplms-sv-success-inline__title">\u00a1Gracias por tu opini\u00f3n!</div>'
                    + '<div class="fplms-sv-success-inline__msg">Tu valoraci\u00f3n ha sido registrada correctamente.</div>'
                    + '</div>';
                if (actions) actions.style.display = 'none';
                setTimeout(removeSurvey, 2800);
            }

            /* ──── Envío ──── */
            function submitSurvey() {
                var btn     = document.getElementById('fplms-sv-submit');
                var errorEl = document.getElementById('fplms-sv-error');
                var commentEl = document.getElementById('fplms-sv-comment');
                var responses = {};
                var allAnswered = true;
                var comment = commentEl ? commentEl.value.trim() : '';

                D.questions.forEach(function(q, i) {
                    var checked = document.querySelector('input[name="fplms_q_' + i + '"]:checked');
                    if (checked) {
                        responses[i] = checked.value;
                    } else {
                        allAnswered = false;
                    }
                });

                if (!allAnswered) {
                    if (errorEl) { errorEl.textContent = 'Por favor responde todas las preguntas antes de enviar.'; errorEl.style.display = 'block'; }
                    return;
                }
                if (comment.length > 500) {
                    if (errorEl) { errorEl.textContent = 'El comentario no puede exceder 500 caracteres.'; errorEl.style.display = 'block'; }
                    return;
                }
                if (errorEl) errorEl.style.display = 'none';

                btn.disabled = true;
                btn.querySelector('svg').style.display = 'none';
                btn.lastChild.textContent = 'Enviando\u2026';

                var params = new URLSearchParams();
                params.append('action',    'fplms_submit_survey');
                params.append('nonce',     D.nonceSubmit);
                params.append('course_id', D.courseId);
                params.append('comment',   comment);
                Object.keys(responses).forEach(function(k) {
                    params.append('responses[' + k + ']', responses[k]);
                });

                fetch(D.ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString()
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        showSuccessInline();
                    } else {
                        btn.disabled = false;
                        btn.querySelector('svg').style.display = '';
                        btn.lastChild.textContent = 'Enviar encuesta';
                        if (errorEl) { errorEl.textContent = 'Error al enviar. Intenta de nuevo.'; errorEl.style.display = 'block'; }
                    }
                })
                .catch(function() {
                    btn.disabled = false;
                    btn.querySelector('svg').style.display = '';
                    btn.lastChild.textContent = 'Enviar encuesta';
                });
            }

            /* ──── Detección: MasterStudy activa el overlay al completar el curso ──── */
            var COMPLETE_ID  = 'masterstudy-single-course-complete';
            var COMPLETE_CLS = 'masterstudy-single-course-complete_active';

            function isCompletionOverlayActive() {
                var el = document.getElementById(COMPLETE_ID);
                if (!el || !el.classList.contains(COMPLETE_CLS)) return false;
                var cid = el.getAttribute('data-course-id');
                return !cid || String(cid) === String(D.courseId);
            }

            function init() {
                // Si el overlay ya está activo (página recargada con curso completado)
                if (isCompletionOverlayActive()) {
                    injectInline();
                    return;
                }

                // Observar cuándo MasterStudy activa el overlay (final de sesión activa)
                var checkTimer = null;
                var observer   = new MutationObserver(function() {
                    if (isCompletionOverlayActive()) {
                        clearTimeout(checkTimer);
                        checkTimer = setTimeout(function() {
                            observer.disconnect();
                            injectInline();
                        }, 900);
                    }
                });
                observer.observe(document.body, {
                    childList: true, subtree: true,
                    attributes: true, attributeFilter: ['class']
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
        </script>
        <?php
    }

    // ── Menú admin ───────────────────────────────────────────────────────────────

    public function register_admin_menu(): void {
        add_submenu_page(
            'fplms-dashboard',
            'Encuestas de Satisfacción',
            'Encuestas',
            FairPlay_LMS_Config::CAP_VIEW_REPORTS,
            'fplms-surveys',
            [ $this, 'render_report_page' ]
        );

        // Asegurar que wp-pointer esté disponible en nuestras páginas admin
        // (algunos scripts de WP admin lo requieren y fallan si no está cargado)
        add_action( 'admin_enqueue_scripts', static function ( string $hook ) {
            if ( str_contains( $hook, 'fplms-surveys' ) ) {
                wp_enqueue_style( 'wp-pointer' );
                wp_enqueue_script( 'wp-pointer' );
            }
        } );
    }

    // ── Página de reporte ────────────────────────────────────────────────────────

    public function render_report_page(): void {
        if ( ! current_user_can( FairPlay_LMS_Config::CAP_VIEW_REPORTS ) ) {
            wp_die( esc_html__( 'Acceso denegado.', 'fairplay-lms' ) );
        }

        // Exportación CSV directa
        if ( ! empty( $_GET['fplms_sv_export'] ) && $_GET['fplms_sv_export'] === 'csv' ) {
            $this->handle_csv_export();
            return;
        }

        global $wpdb;
        $table         = $wpdb->prefix . self::TABLE;
        $filter_course = isset( $_GET['survey_course'] ) ? (int) $_GET['survey_course'] : 0;

        // IDs con datos en BD
        $ids_with_data = array_map( 'intval', (array) $wpdb->get_col( "SELECT DISTINCT course_id FROM {$table}" ) );

        // Todos los cursos publicados (para el selector del filtro)
        $all_ids = array_map( 'intval', (array) get_posts( [
            'post_type'      => FairPlay_LMS_Config::MS_PT_COURSE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ] ) );

        // Consulta de respuestas
        if ( $filter_course > 0 ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT r.*, u.display_name, u.user_email FROM {$table} r
                     LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
                     WHERE r.course_id = %d ORDER BY r.submitted_at DESC LIMIT 2000",
                    $filter_course
                ),
                ARRAY_A
            );
        } elseif ( ! empty( $ids_with_data ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $ids_with_data ), '%d' ) );
            $rows         = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT r.*, u.display_name, u.user_email FROM {$table} r
                     LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
                     WHERE r.course_id IN ({$placeholders}) ORDER BY r.submitted_at DESC LIMIT 2000",
                    $ids_with_data
                ),
                ARRAY_A
            );
        } else {
            $rows = [];
        }

        // Agrupar respuestas: [course_id][user+'|'+date] = submission
        $by_course = [];
        foreach ( $rows as $row ) {
            $cid = (int) $row['course_id'];
            $uid = (int) $row['user_id'];
            $key = $uid . '|' . $row['submitted_at'];
            if ( ! isset( $by_course[ $cid ] ) ) $by_course[ $cid ] = [];
            if ( ! isset( $by_course[ $cid ][ $key ] ) ) {
                $by_course[ $cid ][ $key ] = [
                    'user_name'     => $row['display_name'],
                    'user_email'    => $row['user_email'],
                    'submitted_at'  => $row['submitted_at'],
                    'comment'       => $row['comment'] ?? '',
                    'scores'        => [],
                    'questions'     => [],
                ];
            }
            $qi = (int) $row['question_idx'];
            $by_course[ $cid ][ $key ]['scores'][ $qi ]    = (int) $row['score'];
            $by_course[ $cid ][ $key ]['questions'][ $qi ] = $row['question'];
        }

        // Estadísticas: promedio + distribución por pregunta
        $stats = [];
        foreach ( $by_course as $cid => $submissions ) {
            $q_scores = [];
            foreach ( $submissions as $sub ) {
                foreach ( $sub['scores'] as $qi => $score ) {
                    $q_scores[ $qi ][] = $score;
                }
            }
            foreach ( $q_scores as $qi => $scores ) {
                $total = count( $scores );
                $avg   = round( array_sum( $scores ) / max( $total, 1 ), 1 );
                $dist  = [ 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0 ];
                foreach ( $scores as $s ) { $dist[ $s ] = ( $dist[ $s ] ?? 0 ) + 1; }
                $stats[ $cid ][ $qi ] = [ 'avg' => $avg, 'total' => $total, 'dist' => $dist ];
            }
        }

        $export_url = add_query_arg( [
            'page'            => 'fplms-surveys',
            'fplms_sv_export' => 'csv',
            'survey_course'   => $filter_course ?: '',
        ], admin_url( 'admin.php' ) );
        ?>
        <div class="wrap">
            <h1 style="display:flex;align-items:center;gap:10px;">
                <svg viewBox="0 0 24 24" style="width:26px;height:26px;fill:#667eea;flex-shrink:0;">
                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                </svg>
                Encuestas de Satisfacción
            </h1>

            <style>
            .fplms-sv-page-card    { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.08); padding:24px 28px; margin-top:18px; }
            .fplms-sv-filters      { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:22px; }
            .fplms-sv-filters select{ padding:7px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; }
            .fplms-sv-filters .sv-f-btn { padding:7px 16px; background:#667eea; color:#fff; border:none; border-radius:6px; font-size:13px; cursor:pointer; }
            .fplms-sv-filters .sv-export { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border:1px solid #d1d5db; border-radius:6px; background:#fff; color:#374151; font-size:13px; text-decoration:none; }
            .fplms-sv-filters .sv-export:hover { background:#f9fafb; }
            .fplms-sv-course-block { margin-bottom:32px; }
            .fplms-sv-course-hd    { font-size:15px; font-weight:700; color:#111827; margin-bottom:12px; display:flex; align-items:center; gap:8px; }
            .fplms-sv-stats-grid   { display:grid; grid-template-columns:repeat(auto-fill, minmax(190px,1fr)); gap:10px; margin-bottom:14px; }
            .fplms-sv-stat-card    { background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:12px 14px; }
            .fplms-sv-stat-q       { font-size:11px; color:#6b7280; margin-bottom:4px; }
            .fplms-sv-stat-avg     { font-size:22px; font-weight:700; color:#667eea; }
            .fplms-sv-stat-total   { font-size:11px; color:#9ca3af; margin-top:3px; }
            .fplms-sv-dist-bars    { display:flex; gap:3px; align-items:flex-end; height:26px; margin-top:6px; }
            .fplms-sv-dist-bar     { flex:1; background:#c7d2fe; border-radius:2px 2px 0 0; min-height:2px; }
            .fplms-sv-table-shell  { border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; background:#fff; }
            .fplms-sv-table-toolbar {
                display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
                padding:12px 14px; background:#f9fafb; border-bottom:1px solid #e5e7eb;
            }
            .fplms-sv-page-size    { display:flex; align-items:center; gap:8px; color:#4b5563; font-size:12px; }
            .fplms-sv-page-size select {
                min-width:74px; padding:6px 10px; border:1px solid #d1d5db; border-radius:8px;
                background:#fff; color:#111827; font-size:12px;
            }
            .fplms-sv-page-summary { font-size:12px; color:#6b7280; }
            .fplms-sv-table        { width:100%; border-collapse:collapse; font-size:13px; margin-top:10px; }
            .fplms-sv-table th     { padding:8px 12px; text-align:left; border-bottom:2px solid #e5e7eb; color:#374151; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.4px; }
            .fplms-sv-table td     { padding:8px 12px; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
            .fplms-sv-table tr:hover td { background:#f9fafb; }
            .fplms-sv-comment-cell { min-width:260px; max-width:420px; color:#374151; }
            .fplms-sv-comment-empty { color:#d1d5db; }
            .fplms-sv-comment-box {
                max-height:72px; overflow:auto; white-space:pre-wrap; word-break:break-word;
                padding:8px 10px; border-radius:8px; background:#f9fafb; border:1px solid #e5e7eb;
                font-size:12px; line-height:1.45;
            }
            .sv-chip               { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:50%; font-size:13px; font-weight:700; }
            .sv-chip-1 { background:#fee2e2; color:#b91c1c; }
            .sv-chip-2 { background:#fed7aa; color:#9a3412; }
            .sv-chip-3 { background:#fef9c3; color:#854d0e; }
            .sv-chip-4 { background:#dcfce7; color:#166534; }
            .sv-chip-5 { background:#d1fae5; color:#065f46; }
            .fplms-sv-empty        { text-align:center; padding:44px 0; color:#9ca3af; font-size:14px; }
            .fplms-sv-pagination   {
                display:flex; align-items:center; justify-content:flex-end; gap:8px; flex-wrap:wrap;
                padding:12px 14px; background:#fff; border-top:1px solid #e5e7eb;
            }
            .fplms-sv-page-btn {
                min-width:34px; height:34px; padding:0 10px; border:1px solid #d1d5db; border-radius:8px;
                background:#fff; color:#374151; font-size:12px; font-weight:600; cursor:pointer;
                transition:background .15s,border-color .15s,color .15s;
            }
            .fplms-sv-page-btn:hover { background:#eef2ff; border-color:#c7d2fe; color:#4338ca; }
            .fplms-sv-page-btn.is-active { background:#667eea; border-color:#667eea; color:#fff; }
            .fplms-sv-page-btn.is-disabled,
            .fplms-sv-page-btn:disabled { background:#f3f4f6; border-color:#e5e7eb; color:#9ca3af; cursor:not-allowed; }
            @media (max-width: 782px) {
                .fplms-sv-table-toolbar { align-items:flex-start; }
                .fplms-sv-page-summary,
                .fplms-sv-pagination { justify-content:flex-start; }
            }
            </style>

            <div class="fplms-sv-page-card">
                <div class="fplms-sv-filters">
                    <form method="get" style="display:contents;">
                        <input type="hidden" name="page" value="fplms-surveys">
                        <select name="survey_course">
                            <option value="">Todos los cursos</option>
                            <?php foreach ( $all_ids as $cid ) :
                                $ctitle = get_the_title( $cid );
                                if ( ! $ctitle ) continue;
                            ?>
                            <option value="<?php echo esc_attr( $cid ); ?>" <?php selected( $filter_course, $cid ); ?>>
                                <?php echo esc_html( $ctitle ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="sv-f-btn">Filtrar</button>
                        <a href="<?php echo esc_url( $export_url ); ?>" class="sv-export">
                            <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                            Exportar CSV
                        </a>
                    </form>
                </div>

                <?php if ( empty( $by_course ) ) : ?>
                    <div class="fplms-sv-empty">
                        <svg viewBox="0 0 24 24" style="width:44px;height:44px;fill:#d1d5db;display:block;margin:0 auto 12px;"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                        No hay respuestas registradas todavía.
                    </div>
                <?php else : ?>
                    <?php foreach ( $by_course as $cid => $submissions ) :
                        $ctitle = get_the_title( $cid ) ?: 'Curso #' . $cid;
                        $submission_count = count( $submissions );
                        $q_list = [];
                        foreach ( $submissions as $sub ) {
                            foreach ( $sub['questions'] as $qi => $qtxt ) { $q_list[ $qi ] = $qtxt; }
                        }
                        ksort( $q_list );
                    ?>
                    <div class="fplms-sv-course-block">

                        <div class="fplms-sv-course-hd">
                            <svg viewBox="0 0 24 24" fill="#667eea" style="width:16px;height:16px;flex-shrink:0;"><path d="M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S2.45 4.9 1 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C3.1 20.45 5.05 20 6.5 20c1.95 0 3.75.4 5.5 1.5 1.65-1.1 3.75-1.5 5.5-1.5 1.95 0 3.95.4 5.25 1.35.1.05.15.05.25.05.25 0 .5-.25.5-.5V6c-.6-.45-1.25-.75-2-.95zm0 13.5c-1.1-.35-2.3-.5-3.5-.5-1.75 0-3.75.65-5 1.5V8c1.25-.85 3.25-1.5 5-1.5 1.2 0 2.4.15 3.5.5v11.5z"/></svg>
                            <?php echo esc_html( $ctitle ); ?>
                            <span style="font-size:12px;font-weight:400;color:#6b7280;">(<?php echo count( $submissions ); ?> respuesta<?php echo count( $submissions ) !== 1 ? 's' : ''; ?>)</span>
                        </div>

                        <!-- Tarjetas de estadísticas por pregunta -->
                        <?php if ( ! empty( $stats[ $cid ] ) ) : ?>
                        <div class="fplms-sv-stats-grid">
                            <?php foreach ( $stats[ $cid ] as $qi => $stat ) :
                                $q_text = $q_list[ $qi ] ?? '—';
                                $max    = max( 1, max( $stat['dist'] ) );
                            ?>
                            <div class="fplms-sv-stat-card">
                                <div class="fplms-sv-stat-q" title="<?php echo esc_attr( $q_text ); ?>"><?php echo esc_html( mb_strimwidth( $q_text, 0, 60, '…' ) ); ?></div>
                                <div class="fplms-sv-stat-avg"><?php echo esc_html( $stat['avg'] ); ?> <span style="font-size:14px;color:#9ca3af;font-weight:400;">/ 5</span></div>
                                <div class="fplms-sv-dist-bars">
                                    <?php for ( $v = 1; $v <= 5; $v++ ) :
                                        $pct = (int) round( ( $stat['dist'][ $v ] / $max ) * 100 );
                                    ?>
                                    <div class="fplms-sv-dist-bar"
                                         style="height:<?php echo esc_attr( max( 2, $pct ) ); ?>%;"
                                         title="<?php echo esc_attr( self::$SCALE[ $v ] . ': ' . $stat['dist'][ $v ] ); ?>"></div>
                                    <?php endfor; ?>
                                </div>
                                <div class="fplms-sv-stat-total"><?php echo esc_html( $stat['total'] ); ?> respuesta(s)</div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Tabla de respuestas individuales -->
                        <div class="fplms-sv-table-shell" data-total-rows="<?php echo esc_attr( $submission_count ); ?>">
                            <div class="fplms-sv-table-toolbar">
                                <label class="fplms-sv-page-size">
                                    <span>Mostrar</span>
                                    <select class="fplms-sv-page-size-select">
                                        <?php foreach ( [ 5, 10, 20, 50 ] as $page_size ) : ?>
                                        <option value="<?php echo esc_attr( $page_size ); ?>" <?php selected( 5, $page_size ); ?>>
                                            <?php echo esc_html( $page_size ); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span>registros</span>
                                </label>
                                <div class="fplms-sv-page-summary">Mostrando <?php echo esc_html( min( 5, $submission_count ) ); ?> de <?php echo esc_html( $submission_count ); ?> registros</div>
                            </div>

                            <table class="fplms-sv-table">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Participante</th>
                                        <th>Comentario</th>
                                        <?php foreach ( $q_list as $qi => $qtxt ) : ?>
                                        <th title="<?php echo esc_attr( $qtxt ); ?>">P<?php echo esc_html( $qi + 1 ); ?></th>
                                        <?php endforeach; ?>
                                        <th>Promedio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $submissions as $row_index => $sub ) :
                                        $avg_sub = count( $sub['scores'] ) > 0
                                            ? round( array_sum( $sub['scores'] ) / count( $sub['scores'] ), 1 )
                                            : '—';
                                    ?>
                                    <tr class="fplms-sv-table-row" data-row-index="<?php echo esc_attr( $row_index ); ?>">
                                        <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $sub['submitted_at'] ) ) ); ?></td>
                                        <td>
                                            <div><?php echo esc_html( $sub['user_name'] ); ?></div>
                                            <div style="font-size:11px;color:#9ca3af;"><?php echo esc_html( $sub['user_email'] ); ?></div>
                                        </td>
                                        <td class="fplms-sv-comment-cell">
                                            <?php if ( ! empty( $sub['comment'] ) ) : ?>
                                            <div class="fplms-sv-comment-box"><?php echo esc_html( $sub['comment'] ); ?></div>
                                            <?php else : ?>
                                            <span class="fplms-sv-comment-empty">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php foreach ( $q_list as $qi => $qtxt ) :
                                            $sc = $sub['scores'][ $qi ] ?? null;
                                        ?>
                                        <td>
                                            <?php if ( $sc !== null ) : ?>
                                            <span class="sv-chip sv-chip-<?php echo esc_attr( $sc ); ?>"
                                                  title="<?php echo esc_attr( self::$SCALE[ $sc ] ?? '' ); ?>">
                                                <?php echo esc_html( $sc ); ?>
                                            </span>
                                            <?php else : ?>
                                            <span style="color:#d1d5db;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endforeach; ?>
                                        <td style="font-weight:600;color:#667eea;"><?php echo esc_html( $avg_sub ); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <div class="fplms-sv-pagination" aria-label="Paginación de respuestas"></div>
                        </div>

                    </div><!-- .fplms-sv-course-block -->
                    <?php endforeach; ?>
                <?php endif; ?>

            </div><!-- .fplms-sv-page-card -->
        </div><!-- .wrap -->
        <script>
        (function() {
            function createButton(label, isDisabled, onClick, extraClass) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'fplms-sv-page-btn' + (extraClass ? ' ' + extraClass : '');
                button.textContent = label;
                button.disabled = !!isDisabled;
                if (isDisabled) {
                    button.classList.add('is-disabled');
                } else {
                    button.addEventListener('click', onClick);
                }
                return button;
            }

            function renderPagination(shell, state) {
                var rows = shell.querySelectorAll('.fplms-sv-table-row');
                var pager = shell.querySelector('.fplms-sv-pagination');
                var summary = shell.querySelector('.fplms-sv-page-summary');
                var total = rows.length;
                var start = total ? ((state.page - 1) * state.pageSize) + 1 : 0;
                var end = Math.min(state.page * state.pageSize, total);
                var totalPages = Math.max(1, Math.ceil(total / state.pageSize));

                rows.forEach(function(row, index) {
                    row.style.display = index >= start - 1 && index < end ? '' : 'none';
                });

                if (summary) {
                    summary.textContent = total
                        ? 'Mostrando ' + start + '-' + end + ' de ' + total + ' registros'
                        : 'Sin registros';
                }

                pager.innerHTML = '';
                if (total <= state.pageSize) {
                    return;
                }

                pager.appendChild(createButton('Anterior', state.page === 1, function() {
                    state.page -= 1;
                    renderPagination(shell, state);
                }));

                for (var page = 1; page <= totalPages; page++) {
                    (function(pageNumber) {
                        pager.appendChild(createButton(String(pageNumber), state.page === pageNumber, function() {
                            state.page = pageNumber;
                            renderPagination(shell, state);
                        }, state.page === pageNumber ? 'is-active' : ''));
                    })(page);
                }

                pager.appendChild(createButton('Siguiente', state.page === totalPages, function() {
                    state.page += 1;
                    renderPagination(shell, state);
                }));
            }

            document.querySelectorAll('.fplms-sv-table-shell').forEach(function(shell) {
                var select = shell.querySelector('.fplms-sv-page-size-select');
                var state = {
                    page: 1,
                    pageSize: parseInt(select.value || '5', 10)
                };

                select.addEventListener('change', function() {
                    state.pageSize = parseInt(select.value || '5', 10);
                    state.page = 1;
                    renderPagination(shell, state);
                });

                renderPagination(shell, state);
            });
        })();
        </script>
        <?php
    }

    // ── Exportación CSV ───────────────────────────────────────────────────────────

    private function handle_csv_export(): void {
        if ( ! current_user_can( FairPlay_LMS_Config::CAP_VIEW_REPORTS ) ) {
            wp_die( 'Acceso denegado.' );
        }

        global $wpdb;
        $table         = $wpdb->prefix . self::TABLE;
        $filter_course = isset( $_GET['survey_course'] ) ? (int) $_GET['survey_course'] : 0;

        if ( $filter_course > 0 ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT r.*, u.display_name, u.user_email FROM {$table} r
                     LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
                     WHERE r.course_id = %d ORDER BY r.submitted_at",
                    $filter_course
                ),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results(
                "SELECT r.*, u.display_name, u.user_email FROM {$table} r
                 LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
                 ORDER BY r.course_id, r.submitted_at",
                ARRAY_A
            );
        }

        // Número máximo de preguntas (para construir columnas dinámicas)
        $max_q = 0;
        foreach ( $rows as $row ) { $max_q = max( $max_q, (int) $row['question_idx'] + 1 ); }

        $filename = 'encuestas_satisfaccion_' . gmdate( 'Ymd_His' ) . '.csv';
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );

        $out     = fopen( 'php://output', 'w' );
        $headers = [ 'Curso', 'Nombre', 'Email', 'Fecha', 'Comentario' ];
        for ( $i = 0; $i < $max_q; $i++ ) {
            $headers[] = 'P' . ( $i + 1 ) . ' – Pregunta';
            $headers[] = 'P' . ( $i + 1 ) . ' – Puntuación';
            $headers[] = 'P' . ( $i + 1 ) . ' – Etiqueta';
        }
        $headers[] = 'Promedio';
        fputcsv( $out, $headers );

        // Agrupar por envío
        $grouped = [];
        foreach ( $rows as $row ) {
            $key = $row['course_id'] . '|' . $row['user_id'] . '|' . $row['submitted_at'];
            if ( ! isset( $grouped[ $key ] ) ) {
                $grouped[ $key ] = [
                    'course_id'    => $row['course_id'],
                    'display_name' => $row['display_name'],
                    'user_email'   => $row['user_email'],
                    'submitted_at' => $row['submitted_at'],
                    'comment'      => $row['comment'] ?? '',
                    'q'            => [],
                ];
            }
            $grouped[ $key ]['q'][ (int) $row['question_idx'] ] = [
                'question' => $row['question'],
                'score'    => (int) $row['score'],
            ];
        }

        foreach ( $grouped as $sub ) {
            $course_title = get_the_title( (int) $sub['course_id'] ) ?: 'Curso #' . $sub['course_id'];
            $csv_row      = [ $course_title, $sub['display_name'], $sub['user_email'], $sub['submitted_at'], $sub['comment'] ?? '' ];
            $scores       = [];
            for ( $i = 0; $i < $max_q; $i++ ) {
                $q               = $sub['q'][ $i ] ?? null;
                $csv_row[]       = $q ? $q['question']             : '';
                $csv_row[]       = $q ? $q['score']                : '';
                $csv_row[]       = $q ? ( self::$SCALE[ $q['score'] ] ?? '' ) : '';
                if ( $q ) { $scores[] = $q['score']; }
            }
            $csv_row[] = count( $scores ) > 0 ? round( array_sum( $scores ) / count( $scores ), 1 ) : '';
            fputcsv( $out, $csv_row );
        }

        fclose( $out );
        exit;
    }

    // ── Helpers privados ─────────────────────────────────────────────────────────

    /** Intenta identificar el course_id de la página actual. */
    private function detect_course_id(): int {
        // Método 1: queried object
        $obj = get_queried_object();
        if ( $obj instanceof WP_Post && $obj->post_type === FairPlay_LMS_Config::MS_PT_COURSE ) {
            return $obj->ID;
        }

        // Método 2: parsear la URL (MasterStudy usa /pagina-de-cursos/{slug}/...)
        $path     = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        $segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );
        foreach ( $segments as $seg ) {
            if ( preg_match( '/^[\w-]+$/', $seg ) ) {
                $post = get_page_by_path( $seg, OBJECT, FairPlay_LMS_Config::MS_PT_COURSE );
                if ( $post ) return $post->ID;
            }
        }

        return 0;
    }

    /** Comprueba si el usuario ha completado el curso consultando el meta de MasterStudy. */
    private function is_course_complete( int $user_id, int $course_id ): bool {
        // user_meta directa (MasterStudy 5.x+): seguro y sin efectos secundarios de salida.
        $progress = get_user_meta( $user_id, 'stm_lms_course_' . $course_id . '_progress', true );
        if ( is_array( $progress ) ) {
            $status  = strtolower( (string) ( $progress['status'] ?? '' ) );
            $percent = (float) ( $progress['progress'] ?? $progress['percentage'] ?? 0 );
            if ( 'completed' === $status || $percent >= 99.9 ) return true;
        }

        // Alternativa: meta plana que MasterStudy escribe en algunas versiones.
        $flat = get_user_meta( $user_id, 'stm_lms_course_' . $course_id . '_completed', true );
        if ( $flat ) return true;

        return false;
    }

    private function get_questions( int $course_id ): array {
        $raw = get_post_meta( $course_id, self::META_QUESTIONS, true );
        $qs  = is_array( $raw ) ? $raw : ( json_decode( $raw ?: '[]', true ) ?: [] );
        return array_values( array_filter( $qs ) );
    }

    private function has_saved_submission( int $user_id, int $course_id ): bool {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE course_id = %d AND user_id = %d",
                $course_id,
                $user_id
            )
        );

        return $count > 0;
    }
}
