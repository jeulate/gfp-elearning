<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ajustes de Tests — página admin para configurar el mensaje personalizado
 * que se muestra al finalizar un examen.
 *
 * Opción WP: 'fplms_quiz_completion_message'  (HTML/texto)
 * Opción WP: 'fplms_quiz_completion_enabled'  (1 / '' )
 */
class FairPlay_LMS_Quiz_Settings {

    const OPTION_MESSAGE         = 'fplms_quiz_completion_message';
    const OPTION_ENABLED         = 'fplms_quiz_completion_enabled';
    const OPTION_FAIL_MESSAGE    = 'fplms_quiz_fail_message';
    const OPTION_FAIL_ENABLED    = 'fplms_quiz_fail_enabled';
    const OPTION_PENDING_MESSAGE = 'fplms_quiz_pending_message';
    const OPTION_EXPIRED_MESSAGE = 'fplms_quiz_expired_message';
    const OPTION_WEIGHT_DEFAULT  = 'fplms_quiz_weight_default';
    const NONCE_ACTION           = 'fplms_quiz_settings_save';
    const NONCE_FIELD            = 'fplms_quiz_settings_nonce';

    // ── Admin ─────────────────────────────────────────────────────────────────

    public function register_admin_menu(): void {
        add_submenu_page(
            'fplms-dashboard',
            'Ajustes de Tests',
            'Ajustes de Tests',
            'manage_options',
            'fplms-quiz-settings',
            [ $this, 'render_page' ]
        );
    }

    public function handle_save(): void {
        // Solo procesar en esta página y solo en POST
        if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
            return;
        }
        if ( ! isset( $_GET['page'] ) || 'fplms-quiz-settings' !== $_GET['page'] ) {
            return;
        }
        if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $enabled = isset( $_POST['fplms_quiz_completion_enabled'] ) ? '1' : '';
        update_option( self::OPTION_ENABLED, $enabled );

        // Permitir HTML seguro igual que wp_editor (kses author)
        $message = isset( $_POST['fplms_quiz_completion_message'] )
            ? wp_kses_post( wp_unslash( $_POST['fplms_quiz_completion_message'] ) )
            : '';
        update_option( self::OPTION_MESSAGE, $message );

        $fail_enabled = isset( $_POST['fplms_quiz_fail_enabled'] ) ? '1' : '';
        update_option( self::OPTION_FAIL_ENABLED, $fail_enabled );

        $fail_message = isset( $_POST['fplms_quiz_fail_message'] )
            ? wp_kses_post( wp_unslash( $_POST['fplms_quiz_fail_message'] ) )
            : '';
        update_option( self::OPTION_FAIL_MESSAGE, $fail_message );

        // Mensajes de vigencia (texto plano, sin HTML)
        $pending_msg = isset( $_POST['fplms_quiz_pending_message'] )
            ? sanitize_text_field( wp_unslash( $_POST['fplms_quiz_pending_message'] ) )
            : '';
        update_option( self::OPTION_PENDING_MESSAGE, $pending_msg );

        $expired_msg = isset( $_POST['fplms_quiz_expired_message'] )
            ? sanitize_text_field( wp_unslash( $_POST['fplms_quiz_expired_message'] ) )
            : '';
        update_option( self::OPTION_EXPIRED_MESSAGE, $expired_msg );

        // Modo de ponderación por defecto global
        $weight_default = isset( $_POST['fplms_weight_default'] )
            ? sanitize_text_field( wp_unslash( $_POST['fplms_weight_default'] ) )
            : 'auto';
        if ( ! in_array( $weight_default, [ 'auto', 'manual' ], true ) ) {
            $weight_default = 'auto';
        }
        update_option( self::OPTION_WEIGHT_DEFAULT, $weight_default );

        // Guardar vigencias individuales por quiz
        if ( isset( $_POST['fplms_av'] ) && is_array( $_POST['fplms_av'] ) ) {
            foreach ( $_POST['fplms_av'] as $quiz_id_raw => $dates ) {
                $qid = (int) $quiz_id_raw;
                if ( $qid <= 0 || 'stm-quizzes' !== get_post_type( $qid ) ) {
                    continue;
                }
                if ( ! current_user_can( 'edit_post', $qid ) ) {
                    continue;
                }
                $av_from  = isset( $dates['from'] )  ? sanitize_text_field( wp_unslash( $dates['from'] ) )  : '';
                $av_until = isset( $dates['until'] ) ? sanitize_text_field( wp_unslash( $dates['until'] ) ) : '';
                $d_from  = \DateTime::createFromFormat( 'Y-m-d', $av_from );
                $d_until = \DateTime::createFromFormat( 'Y-m-d', $av_until );
                $av_from  = ( $d_from  instanceof \DateTime && $d_from->format( 'Y-m-d' )  === $av_from  ) ? $av_from  : '';
                $av_until = ( $d_until instanceof \DateTime && $d_until->format( 'Y-m-d' ) === $av_until ) ? $av_until : '';
                update_post_meta( $qid, FairPlay_LMS_Quiz_Availability::META_FROM,  $av_from );
                update_post_meta( $qid, FairPlay_LMS_Quiz_Availability::META_UNTIL, $av_until );
            }
        }

        // POST → Redirect → GET: evita que render_page() lea el caché del request POST.
        wp_safe_redirect(
            add_query_arg(
                [ 'page' => 'fplms-quiz-settings', 'fplms_saved' => '1' ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', 'fairplay-lms' ) );
        }

        $enabled      = (bool) get_option( self::OPTION_ENABLED, '' );
        $message      = get_option( self::OPTION_MESSAGE, '' );
        $fail_enabled = (bool) get_option( self::OPTION_FAIL_ENABLED, '' );
        $fail_message = get_option( self::OPTION_FAIL_MESSAGE, '' );

        // Aviso de guardado exitoso (POST→Redirect→GET: el parámetro lo pone handle_save)
        if ( ! empty( $_GET['fplms_saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>✅ Ajustes de test guardados correctamente.</p></div>';
        }
        ?>
        <div class="wrap">
            <h1 style="display:flex;align-items:center;gap:10px;">
                <svg viewBox="0 0 24 24" style="width:28px;height:28px;fill:#667eea;flex-shrink:0;">
                    <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 3c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm7 13H5v-.23c0-.62.28-1.2.76-1.58C7.47 15.82 9.64 15 12 15s4.53.82 6.24 2.19c.48.38.76.97.76 1.58V19z"/>
                </svg>
                Ajustes de Tests
            </h1>
            <p style="color:#6b7280;margin-top:4px;">Configura el mensaje personalizado que verán los estudiantes al finalizar un examen.</p>

            <style>
                .fplms-qs-card {
                    background: #fff;
                    border-radius: 12px;
                    box-shadow: 0 2px 12px rgba(0,0,0,.08);
                    padding: 28px 32px;
                    max-width: 860px;
                    margin-top: 24px;
                }
                .fplms-qs-section-title {
                    font-size: 15px;
                    font-weight: 600;
                    color: #1f2937;
                    margin: 0 0 6px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .fplms-qs-section-title svg { fill: #667eea; }
                .fplms-qs-desc { font-size: 13px; color: #6b7280; margin: 0 0 18px; }
                .fplms-qs-toggle-row {
                    display: flex;
                    align-items: center;
                    gap: 14px;
                    margin-bottom: 24px;
                    padding: 16px 20px;
                    background: #f8f9fc;
                    border-radius: 8px;
                    border: 1px solid #e5e7eb;
                }
                .fplms-qs-toggle-label { font-size: 14px; font-weight: 500; color: #374151; }
                /* Toggle switch */
                .fplms-toggle { position:relative; display:inline-block; width:44px; height:24px; flex-shrink:0; }
                .fplms-toggle input { opacity:0; width:0; height:0; }
                .fplms-toggle-slider {
                    position: absolute; inset: 0;
                    background: #d1d5db;
                    border-radius: 24px;
                    cursor: pointer;
                    transition: .3s;
                }
                .fplms-toggle-slider::before {
                    content: '';
                    position: absolute;
                    width: 18px; height: 18px;
                    left: 3px; top: 3px;
                    background: #fff;
                    border-radius: 50%;
                    transition: .3s;
                }
                .fplms-toggle input:checked + .fplms-toggle-slider { background: #667eea; }
                .fplms-toggle input:checked + .fplms-toggle-slider::before { transform: translateX(20px); }
                .fplms-qs-editor-wrap { margin-bottom: 24px; }
                .fplms-qs-preview {
                    border: 1px dashed #c4b5fd;
                    border-radius: 8px;
                    padding: 18px 20px;
                    background: #faf5ff;
                    margin-top: 12px;
                }
                .fplms-qs-preview-label {
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                    color: #7c3aed;
                    letter-spacing: .5px;
                    margin-bottom: 10px;
                }
                .fplms-qs-preview-content { font-size: 14px; color: #374151; }
                .fplms-qs-save-btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    background: linear-gradient(135deg, #667eea, #764ba2);
                    color: #fff;
                    border: none;
                    border-radius: 8px;
                    padding: 10px 24px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: opacity .2s;
                }
                .fplms-qs-save-btn:hover { opacity: .88; }
                .fplms-qs-save-btn svg { fill: #fff; }
            </style>

            <form method="post" action="">
                <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>

                <div class="fplms-qs-card">

                    <h2 class="fplms-qs-section-title" style="font-size:16px;margin-bottom:18px;">
                        <svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:#22c55e;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                        Mensaje al aprobar
                    </h2>

                    <!-- Toggle activar/desactivar -->
                    <div class="fplms-qs-toggle-row">
                        <label class="fplms-toggle">
                            <input type="checkbox" name="fplms_quiz_completion_enabled" id="fplms_qms_enabled" value="1" <?php checked( $enabled ); ?>>
                            <span class="fplms-toggle-slider"></span>
                        </label>
                        <span class="fplms-qs-toggle-label">Mostrar mensaje al finalizar el examen</span>
                    </div>

                    <!-- Editor de mensaje -->
                    <p class="fplms-qs-section-title">
                        <svg viewBox="0 0 24 24" style="width:16px;height:16px;"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34a.9959.9959 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                        Contenido del mensaje
                    </p>
                    <p class="fplms-qs-desc">Puedes usar HTML. Este mensaje aparecerá debajo del resultado (porcentaje) al terminar cada examen.</p>

                    <div class="fplms-qs-editor-wrap">
                        <?php
                        wp_editor( $message, 'fplms_quiz_completion_message', [
                            'textarea_name' => 'fplms_quiz_completion_message',
                            'textarea_rows' => 8,
                            'media_buttons' => false,
                            'teeny'         => false,
                            'quicktags'     => true,
                        ] );
                        ?>
                    </div>

                    <!-- Vista previa en vivo -->
                    <div class="fplms-qs-preview" id="fplms-qs-preview-wrap">
                        <div class="fplms-qs-preview-label">Vista previa</div>
                        <div class="fplms-qs-preview-content" id="fplms-qs-preview-content">
                            <?php if ( $message ) : ?>
                                <?php echo wp_kses_post( $message ); ?>
                            <?php else : ?>
                                <em style="color:#9ca3af;">El mensaje aparecerá aquí…</em>
                            <?php endif; ?>
                        </div>
                    </div>

                </div><!-- .fplms-qs-card .aprobado -->

                <!-- ── Tarjeta: mensaje al reprobar ──────────────────────── -->
                <div class="fplms-qs-card" style="margin-top:20px;">

                    <h2 class="fplms-qs-section-title" style="font-size:16px;margin-bottom:18px;">
                        <svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:#ef4444;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/></svg>
                        Mensaje al reprobar
                    </h2>

                    <div class="fplms-qs-toggle-row">
                        <label class="fplms-toggle">
                            <input type="checkbox" name="fplms_quiz_fail_enabled" id="fplms_fail_enabled" value="1" <?php checked( $fail_enabled ); ?>>
                            <span class="fplms-toggle-slider"></span>
                        </label>
                        <span class="fplms-qs-toggle-label">Mostrar mensaje cuando el estudiante reprueba</span>
                    </div>

                    <p class="fplms-qs-section-title">
                        <svg viewBox="0 0 24 24" style="width:16px;height:16px;"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34a.9959.9959 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                        Contenido del mensaje
                    </p>
                    <p class="fplms-qs-desc">Puedes usar HTML. Este mensaje aparecerá cuando el estudiante no alcance la calificación mínima.</p>

                    <div class="fplms-qs-editor-wrap">
                        <?php
                        wp_editor( $fail_message, 'fplms_quiz_fail_message', [
                            'textarea_name' => 'fplms_quiz_fail_message',
                            'textarea_rows' => 8,
                            'media_buttons' => false,
                            'teeny'         => false,
                            'quicktags'     => true,
                        ] );
                        ?>
                    </div>

                    <div class="fplms-qs-preview" id="fplms-qs-fail-preview-wrap">
                        <div class="fplms-qs-preview-label">Vista previa</div>
                        <div class="fplms-qs-preview-content" id="fplms-qs-fail-preview-content">
                            <?php if ( $fail_message ) : ?>
                                <?php echo wp_kses_post( $fail_message ); ?>
                            <?php else : ?>
                                <em style="color:#9ca3af;">El mensaje aparecerá aquí…</em>
                            <?php endif; ?>
                        </div>
                    </div>

                </div><!-- .fplms-qs-card .reprobado -->

                <?php
                // ── Tarjeta: vigencia por quiz ─────────────────────────────
                $all_quizzes = get_posts( [
                    'post_type'      => 'stm-quizzes',
                    'post_status'    => [ 'publish', 'draft' ],
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                    'no_found_rows'  => true,
                ] );
                ?>
                <div class="fplms-qs-card" id="fplms-av-card" style="margin-top:20px;">

                    <h2 class="fplms-qs-section-title" style="font-size:16px;margin-bottom:6px;">
                        <svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:#667eea;"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>
                        Vigencia por Test
                    </h2>
                    <p class="fplms-qs-desc" style="margin-bottom:18px;">Define el rango de fechas en que cada test estará disponible para ser iniciado. Deja las fechas vacías para no restringir el acceso.</p>

                    <style>
                        .fplms-av-search {
                            width: 100%; max-width: 340px;
                            padding: 8px 12px;
                            border: 1px solid #d1d5db;
                            border-radius: 8px;
                            font-size: 13px;
                            margin-bottom: 16px;
                            box-sizing: border-box;
                        }
                        .fplms-av-search:focus { outline: 2px solid #667eea; border-color: #667eea; }
                        .fplms-av-table { width: 100%; border-collapse: collapse; font-size: 13px; }
                        .fplms-av-table thead th {
                            text-align: left;
                            padding: 8px 12px;
                            background: #f3f4f6;
                            color: #6b7280;
                            font-weight: 600;
                            font-size: 11px;
                            text-transform: uppercase;
                            letter-spacing: .4px;
                            border-bottom: 1px solid #e5e7eb;
                        }
                        .fplms-av-table thead th:first-child { border-radius: 6px 0 0 0; }
                        .fplms-av-table thead th:last-child  { border-radius: 0 6px 0 0; }
                        .fplms-av-table tbody tr { border-bottom: 1px solid #f3f4f6; transition: background .15s; }
                        .fplms-av-table tbody tr:hover { background: #f8f9fc; }
                        .fplms-av-table td { padding: 10px 12px; vertical-align: middle; }
                        .fplms-av-status {
                            display: inline-block;
                            padding: 3px 9px;
                            border-radius: 20px;
                            font-size: 11px;
                            font-weight: 600;
                            white-space: nowrap;
                        }
                        .fplms-av-status.s-none    { background:#f3f4f6; color:#6b7280; }
                        .fplms-av-status.s-active  { background:#dcfce7; color:#16a34a; }
                        .fplms-av-status.s-pending { background:#fef9c3; color:#854d0e; }
                        .fplms-av-status.s-expired { background:#fee2e2; color:#dc2626; }
                        .fplms-av-table input[type="date"] {
                            padding: 5px 8px;
                            border: 1px solid #d1d5db;
                            border-radius: 6px;
                            font-size: 12px;
                            width: 134px;
                            transition: border-color .15s;
                        }
                        .fplms-av-table input[type="date"]:focus { outline: 2px solid #667eea; border-color: #667eea; }
                        .fplms-av-quiz-name { font-weight: 500; color: #1f2937; display: flex; align-items: center; gap: 6px; }
                        .fplms-av-quiz-name a {
                            color: #a78bfa;
                            text-decoration: none;
                            font-size: 11px;
                            padding: 2px 6px;
                            border: 1px solid #ede9fe;
                            border-radius: 4px;
                            transition: background .15s;
                        }
                        .fplms-av-quiz-name a:hover { background: #ede9fe; }
                        .fplms-av-clear-btn {
                            background: none;
                            border: 1px solid #e5e7eb;
                            border-radius: 6px;
                            padding: 5px 9px;
                            font-size: 12px;
                            color: #9ca3af;
                            cursor: pointer;
                            transition: all .2s;
                            line-height: 1;
                        }
                        .fplms-av-clear-btn:hover { border-color: #ef4444; color: #ef4444; background: #fff5f5; }
                        .fplms-av-no-results { text-align:center; color:#9ca3af; padding:20px; font-style:italic; display:none; }
                        .fplms-av-count { font-size: 12px; color: #9ca3af; margin-bottom: 10px; }
                    </style>

                    <?php if ( empty( $all_quizzes ) ) : ?>
                        <p style="color:#9ca3af;font-style:italic;">No hay tests publicados aún.</p>
                    <?php else : ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:4px;">
                            <input type="text" id="fplms-av-search" class="fplms-av-search"
                                   placeholder="🔍 Buscar test por nombre…" autocomplete="off" style="margin-bottom:0;">
                            <span class="fplms-av-count" id="fplms-av-count"><?php echo count( $all_quizzes ); ?> tests</span>
                        </div>

                        <div style="overflow-x:auto;margin-top:12px;">
                        <table class="fplms-av-table" id="fplms-av-table">
                            <thead>
                                <tr>
                                    <th style="width:95px;">Estado</th>
                                    <th>Test</th>
                                    <th style="width:148px;">Disponible desde</th>
                                    <th style="width:148px;">Disponible hasta</th>
                                    <th style="width:46px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ( $all_quizzes as $quiz ) :
                                $av_status = FairPlay_LMS_Quiz_Availability::get_availability_status( $quiz->ID );
                                $q_from    = (string) get_post_meta( $quiz->ID, FairPlay_LMS_Quiz_Availability::META_FROM,  true );
                                $q_until   = (string) get_post_meta( $quiz->ID, FairPlay_LMS_Quiz_Availability::META_UNTIL, true );
                                if ( $av_status['no_restriction'] ) {
                                    $sc = 's-none';    $sl = 'Sin límite';
                                } elseif ( $av_status['active'] ) {
                                    $sc = 's-active';  $sl = '✓ Disponible';
                                } elseif ( $av_status['pending'] ) {
                                    $sc = 's-pending'; $sl = '⏳ Pendiente';
                                } else {
                                    $sc = 's-expired'; $sl = '✕ Expirado';
                                }
                                $edit_url = get_edit_post_link( $quiz->ID );
                            ?>
                            <tr data-name="<?php echo esc_attr( mb_strtolower( $quiz->post_title ) ); ?>">
                                <td><span class="fplms-av-status <?php echo esc_attr( $sc ); ?>"><?php echo esc_html( $sl ); ?></span></td>
                                <td>
                                    <span class="fplms-av-quiz-name">
                                        <?php echo esc_html( $quiz->post_title ); ?>
                                        <?php if ( $edit_url ) : ?>
                                        <a href="<?php echo esc_url( $edit_url ); ?>" target="_blank">Editar</a>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <input type="date"
                                           name="fplms_av[<?php echo esc_attr( $quiz->ID ); ?>][from]"
                                           value="<?php echo esc_attr( $q_from ); ?>">
                                </td>
                                <td>
                                    <input type="date"
                                           name="fplms_av[<?php echo esc_attr( $quiz->ID ); ?>][until]"
                                           value="<?php echo esc_attr( $q_until ); ?>">
                                </td>
                                <td>
                                    <button type="button" class="fplms-av-clear-btn" title="Borrar fechas">✕</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <p class="fplms-av-no-results" id="fplms-av-no-results">No se encontraron tests con ese nombre.</p>
                    <?php endif; ?>

                </div><!-- .fplms-av-card -->

                <!-- ── Tarjeta: mensajes de vigencia ────────────────────── -->
                <?php
                $pending_msg = (string) get_option( self::OPTION_PENDING_MESSAGE, '' );
                $expired_msg = (string) get_option( self::OPTION_EXPIRED_MESSAGE, '' );
                ?>
                <div class="fplms-qs-card" style="margin-top:20px;">

                    <h2 class="fplms-qs-section-title" style="font-size:16px;margin-bottom:6px;">
                        <svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:#f59e0b;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                        Mensajes de vigencia
                    </h2>
                    <p class="fplms-qs-desc" style="margin-bottom:24px;">Texto que verá el estudiante cuando intente iniciar un test fuera de su ventana de disponibilidad. Puedes usar <code>{fecha}</code> como marcador y será reemplazado automáticamente por la fecha correspondiente.</p>

                    <!-- Mensaje: aún no disponible -->
                    <div style="margin-bottom:28px;">
                        <p class="fplms-qs-section-title">
                            <svg viewBox="0 0 24 24" style="width:15px;height:15px;fill:#f59e0b;"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm4.3 14.3L11 11V6h1.5v4.56l4.8 4.8-1 .94z"/></svg>
                            Test aún no disponible
                        </p>
                        <p class="fplms-qs-desc">Se muestra cuando el estudiante intenta iniciar un test antes de la fecha de inicio.</p>
                        <input type="text"
                               id="fplms_quiz_pending_message"
                               name="fplms_quiz_pending_message"
                               value="<?php echo esc_attr( $pending_msg ); ?>"
                               placeholder="Este test estará disponible desde el {fecha}."
                               style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;box-sizing:border-box;">
                        <p class="fplms-qs-desc" style="margin-top:6px;margin-bottom:0;">Deja vacío para usar el mensaje por defecto.</p>
                    </div>

                    <!-- Mensaje: vigencia expirada -->
                    <div>
                        <p class="fplms-qs-section-title">
                            <svg viewBox="0 0 24 24" style="width:15px;height:15px;fill:#ef4444;"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/></svg>
                            Vigencia expirada
                        </p>
                        <p class="fplms-qs-desc">Se muestra cuando la fecha límite del test ya pasó.</p>
                        <input type="text"
                               id="fplms_quiz_expired_message"
                               name="fplms_quiz_expired_message"
                               value="<?php echo esc_attr( $expired_msg ); ?>"
                               placeholder="La vigencia de este test expiró el {fecha}."
                               style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;box-sizing:border-box;">
                        <p class="fplms-qs-desc" style="margin-top:6px;margin-bottom:0;">Deja vacío para usar el mensaje por defecto.</p>
                    </div>

                </div><!-- mensajes de vigencia -->

                <!-- ── Tarjeta: ponderación de preguntas ─────────────────── -->
                <?php
                $weight_default = (string) get_option( self::OPTION_WEIGHT_DEFAULT, 'auto' );
                if ( ! in_array( $weight_default, [ 'auto', 'manual' ], true ) ) {
                    $weight_default = 'auto';
                }
                $all_quizzes_w = get_posts( [
                    'post_type'      => 'stm-quizzes',
                    'post_status'    => [ 'publish', 'draft' ],
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                    'no_found_rows'  => true,
                ] );
                ?>
                <div class="fplms-qs-card" style="margin-top:20px;">

                    <h2 class="fplms-qs-section-title" style="font-size:16px;margin-bottom:6px;">
                        <svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:#667eea;"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14l-5-5 1.41-1.41L12 14.17l7.59-7.59L21 8l-9 9z"/></svg>
                        Ponderación de Preguntas
                    </h2>
                    <p class="fplms-qs-desc" style="margin-bottom:20px;">Define cómo se distribuyen los 100 puntos entre las preguntas de cada test. La ponderación individual se configura en el editor de cada test.</p>

                    <!-- Default global ──────────────────────────────────────── -->
                    <p class="fplms-qs-section-title">
                        <svg viewBox="0 0 24 24" style="width:15px;height:15px;fill:#667eea;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                        Modo por defecto global
                    </p>
                    <p class="fplms-qs-desc">Todos los tests nuevos heredarán este comportamiento a menos que se configure individualmente.</p>

                    <div style="display:flex;gap:24px;flex-wrap:wrap;padding:14px 18px;background:#f8f9fc;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:24px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:500;font-size:13px;">
                            <input type="radio" name="fplms_weight_default" value="auto"
                                   style="accent-color:#667eea;cursor:pointer;"
                                   <?php checked( 'auto', $weight_default ); ?>>
                            <span>
                                <strong>Automática</strong><br>
                                <span style="font-size:12px;color:#6b7280;font-weight:400;">Los 100 puntos se reparten en partes iguales entre todas las preguntas.</span>
                            </span>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:500;font-size:13px;">
                            <input type="radio" name="fplms_weight_default" value="manual"
                                   style="accent-color:#667eea;cursor:pointer;"
                                   <?php checked( 'manual', $weight_default ); ?>>
                            <span>
                                <strong>Manual</strong><br>
                                <span style="font-size:12px;color:#6b7280;font-weight:400;">El administrador asigna el peso de cada pregunta en el editor del test.</span>
                            </span>
                        </label>
                    </div>

                    <!-- Resumen por test ────────────────────────────────────── -->
                    <p class="fplms-qs-section-title" style="margin-bottom:14px;">
                        <svg viewBox="0 0 24 24" style="width:15px;height:15px;fill:#667eea;"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg>
                        Estado por test
                    </p>

                    <?php if ( empty( $all_quizzes_w ) ) : ?>
                        <p style="color:#9ca3af;font-style:italic;">No hay tests publicados aún.</p>
                    <?php else : ?>
                    <style>
                        .fplms-wt-sum-table { width:100%; border-collapse:collapse; font-size:13px; }
                        .fplms-wt-sum-table thead th {
                            text-align:left; padding:8px 12px;
                            background:#f3f4f6; color:#6b7280;
                            font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.4px;
                            border-bottom:1px solid #e5e7eb;
                        }
                        .fplms-wt-sum-table thead th:first-child { border-radius:6px 0 0 0; }
                        .fplms-wt-sum-table thead th:last-child  { border-radius:0 6px 0 0; }
                        .fplms-wt-sum-table tbody tr   { border-bottom:1px solid #f3f4f6; transition:background .15s; }
                        .fplms-wt-sum-table tbody tr:hover { background:#f8f9fc; }
                        .fplms-wt-sum-table td { padding:9px 12px; vertical-align:middle; }
                        .fplms-wt-badge {
                            display:inline-block; padding:3px 9px; border-radius:20px;
                            font-size:11px; font-weight:600; white-space:nowrap;
                        }
                        .fplms-wt-badge.b-auto    { background:#e0f2fe; color:#0369a1; }
                        .fplms-wt-badge.b-manual  { background:#ede9fe; color:#5b21b6; }
                        .fplms-wt-badge.b-ok      { background:#dcfce7; color:#16a34a; }
                        .fplms-wt-badge.b-warn    { background:#fef9c3; color:#854d0e; }
                        .fplms-wt-badge.b-inherit { background:#f3f4f6; color:#6b7280; }
                        .fplms-wt-edit-link {
                            color:#a78bfa; text-decoration:none;
                            font-size:11px; padding:2px 7px;
                            border:1px solid #ede9fe; border-radius:4px;
                            transition:background .15s;
                        }
                        .fplms-wt-edit-link:hover { background:#ede9fe; }
                    </style>
                    <div style="overflow-x:auto;">
                    <table class="fplms-wt-sum-table">
                        <thead>
                            <tr>
                                <th>Test</th>
                                <th style="width:130px;">Modo efectivo</th>
                                <th style="width:100px;">Preguntas</th>
                                <th style="width:160px;">Estado ponderación</th>
                                <th style="width:80px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $all_quizzes_w as $wquiz ) :
                            $wq_id        = $wquiz->ID;
                            $wq_mode_raw  = (string) get_post_meta( $wq_id, FairPlay_LMS_Quiz_Weights::META_MODE, true );
                            $wq_effective = ( '' !== $wq_mode_raw ) ? $wq_mode_raw : $weight_default;
                            $wq_q_ids     = FairPlay_LMS_Quiz_Weights::get_question_ids( $wq_id );
                            $wq_count     = count( $wq_q_ids );

                            // Badge modo
                            if ( '' === $wq_mode_raw ) {
                                $mode_class = 'b-inherit';
                                $mode_label = 'Heredado (' . ( 'manual' === $weight_default ? 'Manual' : 'Auto' ) . ')';
                            } elseif ( 'manual' === $wq_mode_raw ) {
                                $mode_class = 'b-manual'; $mode_label = 'Manual';
                            } else {
                                $mode_class = 'b-auto'; $mode_label = 'Automática';
                            }

                            // Badge estado
                            if ( 'auto' === $wq_effective ) {
                                $st_class = 'b-auto'; $st_label = 'Distribución equitativa';
                            } else {
                                $saved_wraw = get_post_meta( $wq_id, FairPlay_LMS_Quiz_Weights::META_WEIGHTS, true );
                                $saved_wdec = is_string( $saved_wraw ) ? json_decode( $saved_wraw, true ) : null;
                                if ( is_array( $saved_wdec ) && ! empty( $saved_wdec ) ) {
                                    $wsum = round( array_sum( array_values( $saved_wdec ) ), 2 );
                                    if ( abs( $wsum - 100 ) < 0.02 ) {
                                        $st_class = 'b-ok'; $st_label = 'Configurado (= 100)';
                                    } else {
                                        $st_class = 'b-warn'; $st_label = 'Configurado (≠ 100)';
                                    }
                                } else {
                                    $st_class = 'b-warn'; $st_label = 'Sin configurar';
                                }
                            }

                            $edit_url = get_edit_post_link( $wq_id );
                        ?>
                        <tr>
                            <td style="font-weight:500;color:#1f2937;"><?php echo esc_html( $wquiz->post_title ); ?></td>
                            <td><span class="fplms-wt-badge <?php echo esc_attr( $mode_class ); ?>"><?php echo esc_html( $mode_label ); ?></span></td>
                            <td style="color:#6b7280;"><?php echo esc_html( $wq_count ); ?> pregunta<?php echo $wq_count !== 1 ? 's' : ''; ?></td>
                            <td><span class="fplms-wt-badge <?php echo esc_attr( $st_class ); ?>"><?php echo esc_html( $st_label ); ?></span></td>
                            <td>
                                <?php if ( $edit_url ) : ?>
                                <a href="<?php echo esc_url( $edit_url ); ?>" target="_blank" class="fplms-wt-edit-link">Configurar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>

                </div><!-- ponderación de preguntas -->

                <div style="margin-top:24px;max-width:860px;">
                    <button type="submit" class="fplms-qs-save-btn">
                        <svg viewBox="0 0 24 24" style="width:16px;height:16px;"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                        Guardar ajustes
                    </button>
                </div>
            </form>
        </div>

        <script>
        (function() {
            // ── Búsqueda en tabla de vigencias ────────────────────────────
            var avSearch = document.getElementById('fplms-av-search');
            if (avSearch) {
                avSearch.addEventListener('input', function() {
                    var q = this.value.toLowerCase().trim();
                    var rows = document.querySelectorAll('#fplms-av-table tbody tr');
                    var visible = 0;
                    rows.forEach(function(row) {
                        var name = row.getAttribute('data-name') || '';
                        var show = !q || name.indexOf(q) !== -1;
                        row.style.display = show ? '' : 'none';
                        if (show) visible++;
                    });
                    var countEl = document.getElementById('fplms-av-count');
                    if (countEl) countEl.textContent = visible + ' test' + (visible !== 1 ? 's' : '');
                    var noRes = document.getElementById('fplms-av-no-results');
                    if (noRes) noRes.style.display = visible === 0 ? 'block' : 'none';
                });
            }

            // ── Botones «borrar fechas» ───────────────────────────────────
            document.addEventListener('click', function(e) {
                if (!e.target.classList.contains('fplms-av-clear-btn')) return;
                var row = e.target.closest('tr');
                if (!row) return;
                row.querySelectorAll('input[type="date"]').forEach(function(inp) { inp.value = ''; });
            });

            // ── Actualizadores de vista previa de mensajes ────────────────
            function makeUpdater(editorId, previewId) {
                return function() {
                    var content = '';
                    if (window.tinyMCE && tinyMCE.get(editorId)) {
                        content = tinyMCE.get(editorId).getContent();
                    } else {
                        var ta = document.getElementById(editorId);
                        if (ta) content = ta.value;
                    }
                    var preview = document.getElementById(previewId);
                    if (!preview) return;
                    preview.innerHTML = content.trim()
                        ? content
                        : '<em style="color:#9ca3af;">El mensaje aparecerá aquí…</em>';
                };
            }
            [
                { editor: 'fplms_quiz_completion_message', preview: 'fplms-qs-preview-content' },
                { editor: 'fplms_quiz_fail_message',       preview: 'fplms-qs-fail-preview-content' }
            ].forEach(function(cfg) {
                var fn = makeUpdater(cfg.editor, cfg.preview);
                var _poll = setInterval(function() {
                    if (window.tinyMCE && tinyMCE.get(cfg.editor)) {
                        tinyMCE.get(cfg.editor).on('keyup change NodeChange', fn);
                        clearInterval(_poll);
                    }
                }, 400);
                var ta = document.getElementById(cfg.editor);
                if (ta) ta.addEventListener('input', fn);
            });
        })();
        </script>
        <?php
    }

    // ── Helpers públicos para el frontend ─────────────────────────────────────

    /**
     * Devuelve el mensaje guardado (HTML) o cadena vacía si está desactivado.
     */
    public static function get_message(): string {
        if ( ! get_option( self::OPTION_ENABLED ) ) {
            return '';
        }
        return (string) get_option( self::OPTION_MESSAGE, '' );
    }

    /**
     * Devuelve el mensaje de reprobado (HTML) o cadena vacía si está desactivado.
     */
    public static function get_fail_message(): string {
        if ( ! get_option( self::OPTION_FAIL_ENABLED ) ) {
            return '';
        }
        return (string) get_option( self::OPTION_FAIL_MESSAGE, '' );
    }

    /**
     * Devuelve el mensaje personalizado para test pendiente, o cadena vacía
     * si el admin no configuró uno (enforce_rest usará el mensaje por defecto).
     */
    public static function get_pending_message(): string {
        return (string) get_option( self::OPTION_PENDING_MESSAGE, '' );
    }

    /**
     * Devuelve el mensaje personalizado para vigencia expirada, o cadena vacía
     * si el admin no configuró uno (enforce_rest usará el mensaje por defecto).
     */
    public static function get_expired_message(): string {
        return (string) get_option( self::OPTION_EXPIRED_MESSAGE, '' );
    }

    /**
     * Devuelve el modo de ponderación por defecto global: 'auto' o 'manual'.
     */
    public static function get_weight_default_mode(): string {
        $opt = (string) get_option( self::OPTION_WEIGHT_DEFAULT, 'auto' );
        return in_array( $opt, [ 'auto', 'manual' ], true ) ? $opt : 'auto';
    }
}
