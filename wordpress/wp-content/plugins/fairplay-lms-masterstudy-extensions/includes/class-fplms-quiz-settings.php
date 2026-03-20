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

    const OPTION_MESSAGE      = 'fplms_quiz_completion_message';
    const OPTION_ENABLED      = 'fplms_quiz_completion_enabled';
    const OPTION_FAIL_MESSAGE = 'fplms_quiz_fail_message';
    const OPTION_FAIL_ENABLED = 'fplms_quiz_fail_enabled';
    const NONCE_ACTION        = 'fplms_quiz_settings_save';
    const NONCE_FIELD         = 'fplms_quiz_settings_nonce';

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

        add_action( 'admin_notices', static function () {
            echo '<div class="notice notice-success is-dismissible"><p>✅ Ajustes de test guardados correctamente.</p></div>';
        } );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', 'fairplay-lms' ) );
        }

        $enabled      = (bool) get_option( self::OPTION_ENABLED, '' );
        $message      = get_option( self::OPTION_MESSAGE, '' );
        $fail_enabled = (bool) get_option( self::OPTION_FAIL_ENABLED, '' );
        $fail_message = get_option( self::OPTION_FAIL_MESSAGE, '' );
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
}
