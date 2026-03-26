<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gestiona la ponderación (peso) de preguntas en los quizzes de MasterStudy.
 *
 * Reglas:
 *  - Regla 1 (manual): el administrador asigna un peso numérico a cada pregunta;
 *                      la suma debe ser 100.
 *  - Regla 2 (auto):   si no hay pesos manuales definidos, los 100 puntos se
 *                      reparten de forma equitativa entre todas las preguntas.
 *
 * Meta keys (sobre stm-quizzes):
 *   _fplms_question_weights — JSON: { "question_id": weight, ... }
 *   _fplms_quiz_weight_mode — '' (hereda global) | 'auto' | 'manual'
 *
 * Opción global:
 *   fplms_quiz_weight_default — 'auto' | 'manual'  (defecto: 'auto')
 *
 * Notas sobre integración con MasterStudy:
 *   - Las preguntas de un quiz se leen desde el post_meta 'questions' del CPT
 *     stm-quizzes. Admite tanto arrays de IDs enteros como arrays de arrays
 *     con clave 'id'.
 *   - El filtro REST rest_post_dispatch intenta modificar el score de la
 *     respuesta cuando el modo es 'manual'. Requiere que MasterStudy devuelva
 *     los resultados por pregunta bajo la clave 'questions' o 'results'.
 *     Si la estructura difiere, el score no se modifica (fallback seguro).
 *   - Para una integración más profunda, usa el helper estático
 *     FairPlay_LMS_Quiz_Weights::calculate_score() desde cualquier hook
 *     de MasterStudy que proporcione los IDs de preguntas correctas.
 */
class FairPlay_LMS_Quiz_Weights {

    const META_WEIGHTS   = '_fplms_question_weights';
    const META_MODE      = '_fplms_quiz_weight_mode';
    const OPTION_DEFAULT = 'fplms_quiz_weight_default';
    const NONCE_META     = 'fplms_quiz_weights';

    // ── Registro de hooks ──────────────────────────────────────────────────────

    public function register_hooks(): void {
        add_action( 'add_meta_boxes',        [ $this, 'add_meta_box' ] );
        add_action( 'save_post_stm-quizzes', [ $this, 'save_meta' ], 10, 1 );
        add_filter( 'rest_post_dispatch',    [ $this, 'filter_quiz_result' ], 20, 3 );
        add_action( 'rest_api_init',         [ $this, 'register_rest_routes' ] );
        add_action( 'wp_loaded',             [ $this, 'enqueue_frontend_script' ] );
    }

    // ── REST API ───────────────────────────────────────────────────────────────

    public function register_rest_routes(): void {
        register_rest_route( 'fplms/v1', '/quiz/(?P<id>\d+)/weights', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'rest_get_weights' ],
                'permission_callback' => [ $this, 'rest_check_permission' ],
                'args'                => [ 'id' => [ 'required' => true, 'type' => 'integer', 'minimum' => 1 ] ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'rest_save_weights' ],
                'permission_callback' => [ $this, 'rest_check_permission' ],
                'args'                => [ 'id' => [ 'required' => true, 'type' => 'integer', 'minimum' => 1 ] ],
            ],
        ] );
    }

    public function rest_check_permission( WP_REST_Request $request ): bool {
        $quiz_id = (int) $request->get_param( 'id' );
        return $quiz_id > 0 && current_user_can( 'edit_post', $quiz_id );
    }

    public function rest_get_weights( WP_REST_Request $request ): WP_REST_Response {
        $quiz_id       = (int) $request->get_param( 'id' );
        $mode          = (string) get_post_meta( $quiz_id, self::META_MODE, true );
        $saved_json    = (string) get_post_meta( $quiz_id, self::META_WEIGHTS, true );
        $saved_weights = ( '' !== $saved_json ) ? json_decode( $saved_json, true ) : [];
        if ( ! is_array( $saved_weights ) ) {
            $saved_weights = [];
        }

        $question_ids = self::get_question_ids( $quiz_id );
        $questions    = [];
        foreach ( $question_ids as $q_id ) {
            $q_post      = get_post( $q_id );
            $questions[] = [
                'id'    => $q_id,
                'title' => $q_post ? $q_post->post_title : sprintf( 'Pregunta #%d', $q_id ),
            ];
        }

        // Debug: lista de meta keys para diagnóstico (se puede eliminar después)
        $all_meta_keys = array_keys( get_post_meta( $quiz_id ) );

        return new WP_REST_Response( [
            'mode'            => $mode,
            'effective_mode'  => self::get_mode( $quiz_id ),
            'weights'         => $saved_weights,
            'auto_weights'    => empty( $question_ids ) ? [] : self::compute_auto_weights( $question_ids ),
            'questions'       => $questions,
            'global_default'  => self::get_default_mode(),
            'debug_meta_keys' => $all_meta_keys,
        ] );
    }

    public function rest_save_weights( WP_REST_Request $request ): WP_REST_Response {
        $quiz_id = (int) $request->get_param( 'id' );

        $mode = (string) ( $request->get_param( 'mode' ) ?? '' );
        if ( ! in_array( $mode, [ '', 'auto', 'manual' ], true ) ) {
            $mode = '';
        }
        update_post_meta( $quiz_id, self::META_MODE, $mode );

        $raw_weights   = $request->get_param( 'weights' );
        $clean_weights = [];
        if ( is_array( $raw_weights ) ) {
            foreach ( $raw_weights as $q_id_raw => $val_raw ) {
                $q_id = (int) $q_id_raw;
                if ( $q_id > 0 ) {
                    $clean_weights[ $q_id ] = round( (float) $val_raw, 2 );
                }
            }
        }
        update_post_meta( $quiz_id, self::META_WEIGHTS, wp_json_encode( $clean_weights ) );

        return new WP_REST_Response( [ 'success' => true ] );
    }

    // ── Script frontend ────────────────────────────────────────────────────────

    public function enqueue_frontend_script(): void {
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        if ( ! preg_match( '#/edit-quiz/(\d+)#', $uri, $m ) ) {
            return;
        }
        $quiz_id = (int) $m[1];
        if ( $quiz_id <= 0 ) {
            return;
        }

        $config     = wp_json_encode( [
            'quizId'   => $quiz_id,
            'restBase' => rest_url( 'fplms/v1/quiz/' . $quiz_id . '/weights' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
        ] );
        $script_url = esc_url( FPLMS_PLUGIN_URL . 'assets/js/quiz-weights-editor.js?v=1.0.4' );

        $injection = "<script>window.fplmsWeights = {$config};</script>\n"
                   . "<script src=\"{$script_url}\" defer></script>\n";

        // ob_start: intercepta el HTML completo sin importar qué template usa MasterStudy.
        // Funciona aunque wp_head() / wp_footer() no sean invocados.
        ob_start( function ( $buffer ) use ( $injection ) {
            if ( false !== strpos( $buffer, '</head>' ) ) {
                return str_replace( '</head>', $injection . '</head>', $buffer );
            }
            if ( false !== strpos( $buffer, '</body>' ) ) {
                return str_replace( '</body>', $injection . '</body>', $buffer );
            }
            return $buffer . $injection;
        } );
    }

    // ── Metabox ────────────────────────────────────────────────────────────────

    public function add_meta_box(): void {
        add_meta_box(
            'fplms-quiz-weights',
            'Ponderación de Preguntas',
            [ $this, 'render_meta_box' ],
            'stm-quizzes',
            'normal',
            'default'
        );
    }

    public function render_meta_box( WP_Post $post ): void {
        $mode    = (string) get_post_meta( $post->ID, self::META_MODE, true ); // '' | 'auto' | 'manual'
        $saved_w = get_post_meta( $post->ID, self::META_WEIGHTS, true );
        $weights = [];
        if ( is_array( $saved_w ) ) {
            $weights = $saved_w;
        } elseif ( is_string( $saved_w ) && '' !== $saved_w ) {
            $decoded = json_decode( $saved_w, true );
            if ( is_array( $decoded ) ) {
                $weights = $decoded;
            }
        }

        $global_default  = self::get_default_mode();
        $question_ids    = self::get_question_ids( $post->ID );
        $resolved_mode   = ( '' !== $mode ) ? $mode : $global_default;
        $auto_weights    = empty( $question_ids ) ? [] : self::compute_auto_weights( $question_ids );

        wp_nonce_field( self::NONCE_META, self::NONCE_META . '_nonce' );
        ?>
        <style>
            .fplms-wt-wrap           { font-size: 13px; }
            .fplms-wt-section-label  { font-weight: 600; color: #374151; margin: 0 0 8px; }
            .fplms-wt-mode-row {
                display: flex; align-items: center; gap: 20px; flex-wrap: wrap;
                padding: 12px 16px;
                background: #f8f9fc;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                margin-bottom: 20px;
            }
            .fplms-wt-mode-row label  { display: flex; align-items: center; gap: 6px; cursor: pointer; font-weight: 500; color: #374151; }
            .fplms-wt-mode-row input  { cursor: pointer; accent-color: #667eea; }
            .fplms-wt-tag-global      { display:inline-block; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600; background:#e0f2fe; color:#0369a1; margin-left:4px; }
            .fplms-wt-table           { width: 100%; border-collapse: collapse; font-size: 13px; }
            .fplms-wt-table thead th  {
                text-align: left; padding: 8px 12px;
                background: #f3f4f6; color: #6b7280;
                font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .4px;
                border-bottom: 1px solid #e5e7eb;
            }
            .fplms-wt-table thead th:first-child { border-radius: 6px 0 0 0; }
            .fplms-wt-table thead th:last-child  { border-radius: 0 6px 0 0; }
            .fplms-wt-table tbody tr  { border-bottom: 1px solid #f3f4f6; transition: background .15s; }
            .fplms-wt-table tbody tr:hover { background: #f8f9fc; }
            .fplms-wt-table td        { padding: 9px 12px; vertical-align: middle; }
            .fplms-wt-input {
                width: 86px; padding: 6px 8px; border: 1px solid #d1d5db;
                border-radius: 6px; font-size: 13px; text-align: right;
                transition: border-color .15s;
            }
            .fplms-wt-input:focus     { outline: 2px solid #667eea; border-color: #667eea; }
            .fplms-wt-input.wt-error  { border-color: #ef4444 !important; background: #fef2f2; }
            .fplms-wt-bar {
                display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
                padding: 13px 16px; margin-top: 14px;
                background: #f8f9fc; border: 1px solid #e5e7eb; border-radius: 8px;
            }
            .fplms-wt-total-num  { font-size: 20px; font-weight: 700; }
            .fplms-wt-total-num.wt-ok  { color: #16a34a; }
            .fplms-wt-total-num.wt-err { color: #dc2626; }
            .fplms-wt-total-msg { font-size: 12px; }
            .fplms-wt-dist-btn {
                margin-left: auto; padding: 7px 14px;
                background: linear-gradient(135deg,#667eea,#764ba2); color: #fff;
                border: none; border-radius: 6px; font-size: 12px; font-weight: 600;
                cursor: pointer; transition: opacity .2s;
            }
            .fplms-wt-dist-btn:hover { opacity: .88; }
            .fplms-wt-hint  { font-size: 12px; color: #9ca3af; margin: 8px 0 0; }
            .fplms-wt-auto-preview {
                padding: 14px 16px; border-radius: 8px;
                background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; font-size: 13px;
            }
            .fplms-wt-auto-preview p { margin: 0 0 6px; }
            .fplms-wt-auto-preview p:last-child { margin-bottom: 0; }
            .fplms-wt-empty { color: #9ca3af; font-style: italic; padding: 14px 0; }
        </style>

        <div class="fplms-wt-wrap">

            <!-- Modo de ponderación ─────────────────────────────────────────── -->
            <p class="fplms-wt-section-label">Modo de ponderación</p>
            <div class="fplms-wt-mode-row">
                <label>
                    <input type="radio" name="fplms_wt_mode" value="" <?php checked( '', $mode ); ?>>
                    Heredar configuración global
                    <span class="fplms-wt-tag-global">Global: <?php echo esc_html( 'manual' === $global_default ? 'Manual' : 'Automática' ); ?></span>
                </label>
                <label>
                    <input type="radio" name="fplms_wt_mode" value="auto" <?php checked( 'auto', $mode ); ?>>
                    Forzar automática
                </label>
                <label>
                    <input type="radio" name="fplms_wt_mode" value="manual" <?php checked( 'manual', $mode ); ?>>
                    Ponderación manual
                </label>
            </div>

            <?php if ( empty( $question_ids ) ) : ?>
                <p class="fplms-wt-empty">Este quiz no tiene preguntas todavía. Cuando agregues preguntas aparecerá aquí la tabla de ponderación.</p>
            <?php else : ?>

                <!-- Sección: modo automático ──────────────────────────────── -->
                <div id="fplms-wt-auto-sec" style="display:<?php echo 'manual' !== $resolved_mode ? 'block' : 'none'; ?>;">
                    <?php
                    $vals    = array_values( $auto_weights );
                    $min_val = min( $vals );
                    $max_val = max( $vals );
                    ?>
                    <div class="fplms-wt-auto-preview">
                        <p><strong>Distribución automática &mdash; <?php echo esc_html( count( $question_ids ) ); ?> pregunta<?php echo count( $question_ids ) !== 1 ? 's' : ''; ?></strong></p>
                        <?php if ( abs( $min_val - $max_val ) < 0.01 ) : ?>
                            <p>Cada pregunta vale <strong><?php echo esc_html( number_format( $min_val, 2 ) ); ?> puntos</strong> (total&nbsp;=&nbsp;100).</p>
                        <?php else : ?>
                            <p>La mayoría de preguntas valen <strong><?php echo esc_html( number_format( $min_val, 2 ) ); ?> puntos</strong>; la última vale <strong><?php echo esc_html( number_format( $max_val, 2 ) ); ?> puntos</strong> para completar exactamente 100.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sección: modo manual ──────────────────────────────────── -->
                <div id="fplms-wt-manual-sec" style="display:<?php echo 'manual' === $resolved_mode ? 'block' : 'none'; ?>;">
                    <div style="overflow-x:auto;">
                    <table class="fplms-wt-table">
                        <thead>
                            <tr>
                                <th style="width:36px;">#</th>
                                <th>Pregunta</th>
                                <th style="width:120px; text-align:right;">Peso (pts)</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $i = 0; foreach ( $question_ids as $q_id ) : $i++; ?>
                        <?php
                            $q_post       = get_post( $q_id );
                            $q_title      = $q_post ? $q_post->post_title : sprintf( 'Pregunta #%d', $q_id );
                            $saved_weight = isset( $weights[ $q_id ] ) ? (float) $weights[ $q_id ] : 0.0;
                        ?>
                        <tr>
                            <td style="color:#9ca3af;"><?php echo esc_html( $i ); ?></td>
                            <td style="font-weight:500;color:#1f2937;"><?php echo esc_html( $q_title ); ?></td>
                            <td style="text-align:right;">
                                <input type="number"
                                       class="fplms-wt-input fplms-wt-weight-inp"
                                       name="fplms_wt_weights[<?php echo esc_attr( $q_id ); ?>]"
                                       value="<?php echo esc_attr( $saved_weight > 0 ? number_format( $saved_weight, 2, '.', '' ) : '' ); ?>"
                                       min="0" max="100" step="0.01"
                                       placeholder="0.00">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>

                    <!-- Barra de total ──────────────────────────────────────── -->
                    <div class="fplms-wt-bar">
                        <span style="color:#6b7280;font-size:13px;">Total acumulado:</span>
                        <span class="fplms-wt-total-num wt-err" id="fplms-wt-total">0.00</span>
                        <span style="color:#6b7280;font-size:13px;">/ 100</span>
                        <span class="fplms-wt-total-msg" id="fplms-wt-msg" style="color:#dc2626;"></span>
                        <button type="button" class="fplms-wt-dist-btn" id="fplms-wt-dist">Distribuir equitativamente</button>
                    </div>
                    <p class="fplms-wt-hint">La suma de todos los pesos debe ser exactamente <strong>100</strong>. Usa el botón para distribuir automáticamente entre <?php echo esc_html( count( $question_ids ) ); ?> pregunta<?php echo count( $question_ids ) !== 1 ? 's' : ''; ?>.</p>
                </div>

            <?php endif; ?>
        </div><!-- .fplms-wt-wrap -->

        <script>
        (function() {
            var globalDefault = <?php echo json_encode( $global_default ); ?>;
            var radios        = document.querySelectorAll('input[name="fplms_wt_mode"]');
            var autoSec       = document.getElementById('fplms-wt-auto-sec');
            var manualSec     = document.getElementById('fplms-wt-manual-sec');

            function resolvedMode() {
                var sel = document.querySelector('input[name="fplms_wt_mode"]:checked');
                var val = sel ? sel.value : '';
                return val === '' ? globalDefault : val;
            }

            function updateSections() {
                var m = resolvedMode();
                if (autoSec)   autoSec.style.display   = m !== 'manual' ? 'block' : 'none';
                if (manualSec) manualSec.style.display = m === 'manual' ? 'block' : 'none';
                if (m === 'manual') updateTotal();
            }

            function updateTotal() {
                var inputs = document.querySelectorAll('.fplms-wt-weight-inp');
                var total  = 0;
                inputs.forEach(function(inp) { total += parseFloat(inp.value) || 0; });
                total = Math.round(total * 100) / 100;

                var el  = document.getElementById('fplms-wt-total');
                var msg = document.getElementById('fplms-wt-msg');
                if (!el) return;

                el.textContent = total.toFixed(2);
                var ok = Math.abs(total - 100) < 0.011;
                el.className   = 'fplms-wt-total-num ' + (ok ? 'wt-ok' : 'wt-err');

                if (msg) {
                    if (ok) {
                        msg.textContent = '✓ Correcto';
                        msg.style.color = '#16a34a';
                    } else if (total > 100) {
                        msg.textContent = '⚠ Supera 100 en ' + (total - 100).toFixed(2) + ' puntos';
                        msg.style.color = '#dc2626';
                    } else {
                        msg.textContent = '⚠ Faltan ' + (100 - total).toFixed(2) + ' puntos';
                        msg.style.color = '#d97706';
                    }
                }
            }

            radios.forEach(function(r) { r.addEventListener('change', updateSections); });
            document.querySelectorAll('.fplms-wt-weight-inp').forEach(function(inp) {
                inp.addEventListener('input', updateTotal);
            });

            // Distribución equitativa
            var distBtn = document.getElementById('fplms-wt-dist');
            if (distBtn) {
                distBtn.addEventListener('click', function() {
                    var inputs = document.querySelectorAll('.fplms-wt-weight-inp');
                    var n = inputs.length;
                    if (!n) return;
                    var base      = Math.floor((100 / n) * 100) / 100;
                    var remainder = Math.round((100 - base * n) * 100) / 100;
                    inputs.forEach(function(inp, idx) {
                        inp.value = (idx === n - 1)
                            ? (Math.round((base + remainder) * 100) / 100).toFixed(2)
                            : base.toFixed(2);
                    });
                    updateTotal();
                });
            }

            updateTotal();
        })();
        </script>
        <?php
    }

    // ── Guardar meta ───────────────────────────────────────────────────────────

    public function save_meta( int $post_id ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! isset( $_POST[ self::NONCE_META . '_nonce' ] ) ) {
            return;
        }
        if ( ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST[ self::NONCE_META . '_nonce' ] ) ),
            self::NONCE_META
        ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Modo
        $mode = isset( $_POST['fplms_wt_mode'] )
            ? sanitize_text_field( wp_unslash( $_POST['fplms_wt_mode'] ) )
            : '';
        if ( ! in_array( $mode, [ '', 'auto', 'manual' ], true ) ) {
            $mode = '';
        }
        update_post_meta( $post_id, self::META_MODE, $mode );

        // Pesos individuales
        $weights = [];
        if ( isset( $_POST['fplms_wt_weights'] ) && is_array( $_POST['fplms_wt_weights'] ) ) {
            foreach ( $_POST['fplms_wt_weights'] as $q_id_raw => $val_raw ) {
                $q_id = (int) $q_id_raw;
                if ( $q_id <= 0 ) {
                    continue;
                }
                $val = round( (float) sanitize_text_field( wp_unslash( (string) $val_raw ) ), 2 );
                if ( $val >= 0 ) {
                    $weights[ $q_id ] = $val;
                }
            }
        }
        update_post_meta( $post_id, self::META_WEIGHTS, wp_json_encode( $weights ) );
    }

    // ── Filtro REST: aplicar ponderación en la respuesta del quiz ─────────────

    /**
     * Intenta modificar el score de la respuesta REST de MasterStudy cuando el
     * quiz tiene ponderación manual activa.
     *
     * Requiere que la respuesta incluya los resultados por pregunta bajo la clave
     * 'questions' o 'results', con campos 'id' / 'question_id' para el ID y
     * 'correct' / 'status' / 'is_correct' para indicar si fue correcta.
     * Si la estructura no se reconoce, la respuesta NO se modifica (fallback seguro).
     */
    public function filter_quiz_result( $response, $server, $request ) {
        if ( ! ( $response instanceof WP_REST_Response ) ) {
            return $response;
        }
        if ( 'POST' !== $request->get_method() ) {
            return $response;
        }

        $route = $request->get_route();
        if ( ! preg_match( '#/(stm-lms|masterstudy-lms)/v\d+/quiz#', $route ) ) {
            return $response;
        }

        $data = $response->get_data();
        if ( ! is_array( $data ) ) {
            return $response;
        }

        // Obtener quiz_id desde los parámetros de la petición
        $quiz_id = (int) ( $request->get_param( 'quiz_id' )
                        ?? $request->get_param( 'id' )
                        ?? 0 );
        if ( $quiz_id <= 0 ) {
            return $response;
        }

        // Solo actuar si el modo efectivo es 'manual'
        if ( 'manual' !== self::get_mode( $quiz_id ) ) {
            return $response;
        }

        $weights = self::get_effective_weights( $quiz_id );
        if ( empty( $weights ) ) {
            return $response;
        }

        // Buscar resultados por pregunta en la respuesta de MasterStudy
        $questions = $data['questions'] ?? $data['results'] ?? null;
        if ( ! is_array( $questions ) ) {
            return $response;
        }

        $total_weight  = 0.0;
        $earned_weight = 0.0;

        foreach ( $questions as $q_data ) {
            if ( ! is_array( $q_data ) ) {
                continue;
            }
            $q_id = (int) ( $q_data['id'] ?? $q_data['question_id'] ?? $q_data['ID'] ?? 0 );
            if ( ! $q_id || ! isset( $weights[ $q_id ] ) ) {
                continue;
            }
            $w = (float) $weights[ $q_id ];
            $total_weight += $w;

            // MasterStudy puede usar distintas claves para indicar respuesta correcta
            $is_correct = ! empty( $q_data['correct'] )
                        || 'correct' === ( $q_data['status']  ?? '' )
                        || 'correct' === ( $q_data['verdict'] ?? '' )
                        || ! empty( $q_data['is_correct'] );
            if ( $is_correct ) {
                $earned_weight += $w;
            }
        }

        if ( $total_weight <= 0 ) {
            return $response;
        }

        $weighted_score = round( ( $earned_weight / $total_weight ) * 100, 2 );

        // Reemplazar los campos numéricos de puntuación conocidos
        foreach ( [ 'progress_percent', 'percent', 'point', 'score', 'result' ] as $field ) {
            if ( isset( $data[ $field ] ) && is_numeric( $data[ $field ] ) ) {
                $data[ $field ] = $weighted_score;
            }
        }

        $response->set_data( $data );
        return $response;
    }

    // ── Helpers estáticos ──────────────────────────────────────────────────────

    /**
     * Devuelve el modo efectivo para un quiz: 'auto' o 'manual'.
     * Si el quiz tiene '' (hereda), usa el default global.
     */
    public static function get_mode( int $quiz_id ): string {
        $mode = (string) get_post_meta( $quiz_id, self::META_MODE, true );
        if ( '' === $mode ) {
            $mode = self::get_default_mode();
        }
        return $mode;
    }

    /**
     * Devuelve el modo global por defecto: 'auto' o 'manual'.
     */
    public static function get_default_mode(): string {
        $opt = (string) get_option( self::OPTION_DEFAULT, 'auto' );
        return in_array( $opt, [ 'auto', 'manual' ], true ) ? $opt : 'auto';
    }

    /**
     * Obtiene los IDs de preguntas de un quiz desde el post_meta de MasterStudy.
     * Estrategia multi-capa para ser compatible con cualquier versión del plugin.
     *
     * @return int[]
     */
    public static function get_question_ids( int $quiz_id ): array {
        // ── Capa 1: meta keys conocidos ────────────────────────────────────────
        $known_keys = [ 'questions', '_questions', 'stm_questions', 'quiz_questions', 'stm_lms_quiz_questions', '_stm_questions' ];
        foreach ( $known_keys as $key ) {
            $val = get_post_meta( $quiz_id, $key, true );
            if ( ! empty( $val ) ) {
                $ids = self::parse_question_items( $val );
                if ( ! empty( $ids ) ) {
                    return $ids;
                }
            }
        }

        // ── Capa 2: escanear TODOS los meta del quiz ───────────────────────────
        // Identifica arrays que contienen IDs de posts de tipo question.
        $all_meta = get_post_meta( $quiz_id );
        foreach ( $all_meta as $meta_key => $meta_values ) {
            if ( strpos( $meta_key, '_fplms' ) !== false ) {
                continue; // saltar nuestros propios meta
            }
            foreach ( $meta_values as $raw_value ) {
                $ids = self::parse_question_items( $raw_value );
                if ( empty( $ids ) ) {
                    continue;
                }
                // Verificar que el primer ID es un post real de tipo pregunta
                $first_post = get_post( $ids[0] );
                if ( $first_post && strpos( $first_post->post_type, 'question' ) !== false ) {
                    return $ids;
                }
                // También aceptar si el post_parent coincide con el quiz
                if ( $first_post && (int) $first_post->post_parent === $quiz_id ) {
                    return $ids;
                }
            }
        }

        // ── Capa 3: posts hijos (cualquier tipo/estado) ─────────────────────────
        $children = get_posts( [
            'post_type'      => 'any',
            'post_parent'    => $quiz_id,
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ] );
        if ( ! empty( $children ) ) {
            return array_map( 'intval', $children );
        }

        return [];
    }

    /**
     * Parsea un valor de meta (array, JSON, serializado) y extrae IDs de preguntas.
     *
     * @param  mixed $raw
     * @return int[]
     */
    private static function parse_question_items( $raw ): array {
        if ( is_string( $raw ) ) {
            $decoded = json_decode( $raw, true );
            if ( is_array( $decoded ) ) {
                $raw = $decoded;
            } else {
                $unserialized = maybe_unserialize( $raw );
                $raw          = is_array( $unserialized ) ? $unserialized : [];
            }
        }

        if ( ! is_array( $raw ) || empty( $raw ) ) {
            return [];
        }

        $ids = [];
        foreach ( $raw as $item ) {
            if ( is_numeric( $item ) && $item > 0 ) {
                $ids[] = (int) $item;
            } elseif ( is_array( $item ) ) {
                $q_id = (int) ( $item['id'] ?? $item['post_id'] ?? $item['question_id'] ?? $item['ID'] ?? 0 );
                if ( $q_id > 0 ) {
                    $ids[] = $q_id;
                }
            }
        }

        return array_values( array_unique( array_filter( $ids ) ) );
    }

    /**
     * Devuelve los pesos efectivos (quiz_weight_mode + fallback a auto si no hay
     * pesos guardados) como un mapa { question_id => weight }.
     *
     * @return float[]  Clave: question_id, valor: peso (0 – 100)
     */
    public static function get_effective_weights( int $quiz_id ): array {
        $question_ids = self::get_question_ids( $quiz_id );
        if ( empty( $question_ids ) ) {
            return [];
        }

        if ( 'manual' === self::get_mode( $quiz_id ) ) {
            $saved = get_post_meta( $quiz_id, self::META_WEIGHTS, true );
            if ( is_string( $saved ) && '' !== $saved ) {
                $decoded = json_decode( $saved, true );
                if ( is_array( $decoded ) && ! empty( $decoded ) ) {
                    $out = [];
                    foreach ( $question_ids as $q_id ) {
                        $out[ $q_id ] = isset( $decoded[ $q_id ] ) ? (float) $decoded[ $q_id ] : 0.0;
                    }
                    return $out;
                }
            }
        }

        return self::compute_auto_weights( $question_ids );
    }

    /**
     * Calcula el puntaje ponderado (0 – 100) para un quiz dados los IDs de
     * preguntas contestadas correctamente.
     *
     * Uso desde cualquier hook de MasterStudy:
     *   $score = FairPlay_LMS_Quiz_Weights::calculate_score($quiz_id, $correct_question_ids);
     *
     * @param int   $quiz_id      ID del quiz.
     * @param int[] $correct_ids  IDs de preguntas correctas.
     * @return float
     */
    public static function calculate_score( int $quiz_id, array $correct_ids ): float {
        $weights = self::get_effective_weights( $quiz_id );
        if ( empty( $weights ) ) {
            return 0.0;
        }
        $total_weight  = array_sum( $weights );
        $earned_weight = 0.0;
        foreach ( $correct_ids as $q_id ) {
            $q_id = (int) $q_id;
            if ( isset( $weights[ $q_id ] ) ) {
                $earned_weight += (float) $weights[ $q_id ];
            }
        }
        return $total_weight > 0 ? round( ( $earned_weight / $total_weight ) * 100, 2 ) : 0.0;
    }

    /**
     * Distribuye 100 puntos de forma equitativa entre las preguntas.
     * Si 100 no es divisible exactamente, el remanente se añade a la última pregunta.
     *
     * @param int[] $question_ids
     * @return float[]  Clave: question_id, valor: peso
     */
    public static function compute_auto_weights( array $question_ids ): array {
        $n = count( $question_ids );
        if ( 0 === $n ) {
            return [];
        }
        $base      = floor( ( 100 / $n ) * 100 ) / 100;
        $remainder = round( 100 - ( $base * $n ), 2 );
        $weights   = [];
        foreach ( $question_ids as $idx => $q_id ) {
            $weights[ $q_id ] = ( $idx === $n - 1 )
                ? round( $base + $remainder, 2 )
                : $base;
        }
        return $weights;
    }
}
