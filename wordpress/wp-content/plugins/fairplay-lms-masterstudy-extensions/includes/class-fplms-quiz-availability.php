<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gestiona la vigencia (ventana de fechas) de los quizzes de MasterStudy.
 *
 * Concepto:
 *   - Duración del examen : cuánto tiempo tiene el estudiante una vez que empieza.
 *   - Vigencia del quiz   : rango de fechas en que el quiz puede ser iniciado.
 *
 * Meta keys:
 *   _fplms_quiz_available_from  (Y-m-d) — fecha de inicio de disponibilidad
 *   _fplms_quiz_available_until (Y-m-d) — fecha de fin de disponibilidad
 *
 * Flujo:
 *   1. El administrador fija las fechas desde el metabox en el editor del
 *      CPT stm-quizzes (WP Admin → Tests → editar quiz).
 *   2. Al iniciar un quiz, la capa REST verifica la ventana antes de
 *      permitir el acceso.
 *   3. La función estática is_available() puede invocarse desde cualquier
 *      parte del plugin.
 */
class FairPlay_LMS_Quiz_Availability {

    const META_FROM  = '_fplms_quiz_available_from';
    const META_UNTIL = '_fplms_quiz_available_until';
    const NONCE_META = 'fplms_quiz_availability';

    // ── Registro de hooks ──────────────────────────────────────────────────────

    public function register_hooks(): void {
        add_action( 'add_meta_boxes',        [ $this, 'add_meta_box' ] );
        add_action( 'save_post_stm-quizzes', [ $this, 'save_meta' ], 10, 1 );
        add_filter( 'rest_pre_dispatch',     [ $this, 'enforce_rest' ], 5, 3 );
    }

    // ── Metabox ────────────────────────────────────────────────────────────────

    public function add_meta_box(): void {
        add_meta_box(
            'fplms-quiz-availability',
            '📅 Vigencia del Quiz',
            [ $this, 'render_meta_box' ],
            'stm-quizzes',
            'side',
            'default'
        );
    }

    public function render_meta_box( WP_Post $post ): void {
        $from  = (string) get_post_meta( $post->ID, self::META_FROM,  true );
        $until = (string) get_post_meta( $post->ID, self::META_UNTIL, true );

        wp_nonce_field( self::NONCE_META, self::NONCE_META . '_nonce' );

        // Estado actual del quiz
        $status = self::get_availability_status( $post->ID );

        if ( $status['no_restriction'] ) {
            $badge = '<div class="fplms-av-badge" style="background:#f3f4f6;color:#6b7280;">Sin restricción de fechas</div>';
        } elseif ( $status['active'] ) {
            $badge = '<div class="fplms-av-badge" style="background:#dcfce7;color:#16a34a;">✅ Disponible ahora</div>';
        } elseif ( $status['pending'] ) {
            $badge = '<div class="fplms-av-badge" style="background:#fef9c3;color:#854d0e;">⏳ Aún no disponible</div>';
        } else {
            $badge = '<div class="fplms-av-badge" style="background:#fee2e2;color:#dc2626;">❌ Vigencia expirada</div>';
        }
        ?>
        <style>
        .fplms-av-badge {
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 14px;
        }
        .fplms-av-row { margin-bottom: 12px; }
        .fplms-av-row label {
            display: block;
            font-weight: 600;
            font-size: 12px;
            color: #374151;
            margin-bottom: 4px;
        }
        .fplms-av-row input[type="date"] {
            width: 100%;
            padding: 5px 8px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 13px;
            box-sizing: border-box;
        }
        .fplms-av-row input[type="date"]:focus {
            outline: 2px solid #667eea;
            border-color: #667eea;
        }
        .fplms-av-hint {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 8px;
            line-height: 1.5;
        }
        </style>

        <?php echo $badge; // phpcs:ignore -- safe hardcoded HTML ?>

        <div class="fplms-av-row">
            <label for="fplms_av_from">Disponible desde</label>
            <input type="date" id="fplms_av_from" name="fplms_av_from"
                   value="<?php echo esc_attr( $from ); ?>">
        </div>

        <div class="fplms-av-row">
            <label for="fplms_av_until">Disponible hasta</label>
            <input type="date" id="fplms_av_until" name="fplms_av_until"
                   value="<?php echo esc_attr( $until ); ?>">
        </div>

        <p class="fplms-av-hint">
            Deja ambos campos vacíos para no restringir el acceso por fechas.<br>
            Los estudiantes solo podrán <strong>iniciar</strong> el quiz dentro de este rango.
        </p>
        <?php
    }

    public function save_meta( int $post_id ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        $nonce_field = self::NONCE_META . '_nonce';

        if ( ! isset( $_POST[ $nonce_field ] ) ) {
            return;
        }
        if ( ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ),
            self::NONCE_META
        ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $from  = isset( $_POST['fplms_av_from'] )  ? sanitize_text_field( wp_unslash( $_POST['fplms_av_from'] ) )  : '';
        $until = isset( $_POST['fplms_av_until'] ) ? sanitize_text_field( wp_unslash( $_POST['fplms_av_until'] ) ) : '';

        $from  = self::validate_date( $from )  ? $from  : '';
        $until = self::validate_date( $until ) ? $until : '';

        update_post_meta( $post_id, self::META_FROM,  $from );
        update_post_meta( $post_id, self::META_UNTIL, $until );
    }

    // ── REST enforcement ───────────────────────────────────────────────────────

    /**
     * Intercepta peticiones REST de MasterStudy para iniciar quizzes y bloquea
     * el acceso si el quiz está fuera de su ventana de disponibilidad.
     *
     * @param mixed           $result  null = continuar; WP_Error = abortar.
     * @param WP_REST_Server  $server  Instancia del servidor REST.
     * @param WP_REST_Request $request Petición actual.
     * @return mixed
     */
    public function enforce_rest( $result, $server, WP_REST_Request $request ) {
        if ( null !== $result ) {
            return $result; // ya fue interceptada por otra capa
        }

        // Solo peticiones POST a endpoints de quiz
        if ( 'POST' !== $request->get_method() ) {
            return $result;
        }

        $route = $request->get_route();
        if ( ! preg_match( '#/(stm-lms|masterstudy-lms)/v\d+/quiz#i', $route ) ) {
            return $result;
        }

        // Buscar quiz_id en los parámetros de la petición
        $quiz_id = (int) (
            $request->get_param( 'quiz_id' )
            ?: $request->get_param( 'id' )
            ?: $request->get_param( 'lessonId' )
            ?: 0
        );

        if ( $quiz_id <= 0 ) {
            return $result; // no se puede determinar el quiz, dejar pasar
        }

        if ( ! self::is_available( $quiz_id ) ) {
            $status = self::get_availability_status( $quiz_id );
            if ( $status['pending'] ) {
                $fecha       = self::format_date( $status['from'] );
                $custom      = FairPlay_LMS_Quiz_Settings::get_pending_message();
                $msg         = $custom
                    ? str_replace( '{fecha}', $fecha, $custom )
                    : sprintf( 'Este test estará disponible desde el %s.', $fecha );
            } else {
                $fecha       = self::format_date( $status['until'] );
                $custom      = FairPlay_LMS_Quiz_Settings::get_expired_message();
                $msg         = $custom
                    ? str_replace( '{fecha}', $fecha, $custom )
                    : sprintf( 'La vigencia de este test expiró el %s.', $fecha );
            }
            return new WP_Error( 'quiz_not_available', $msg, [ 'status' => 403 ] );
        }

        return $result;
    }

    // ── Helpers estáticos ──────────────────────────────────────────────────────

    /**
     * Devuelve true si el quiz puede iniciarse ahora (sin restricción o dentro
     * de la ventana configurada).
     */
    public static function is_available( int $quiz_id ): bool {
        $from  = (string) get_post_meta( $quiz_id, self::META_FROM,  true );
        $until = (string) get_post_meta( $quiz_id, self::META_UNTIL, true );

        // Sin restricción
        if ( '' === $from && '' === $until ) {
            return true;
        }

        $today = current_time( 'Y-m-d' );

        if ( '' !== $from && $today < $from ) {
            return false;
        }

        if ( '' !== $until && $today > $until ) {
            return false;
        }

        return true;
    }

    /**
     * Devuelve un array con el estado detallado de disponibilidad del quiz.
     *
     * Claves: no_restriction, active, pending, expired, from, until
     */
    public static function get_availability_status( int $quiz_id ): array {
        $from  = (string) get_post_meta( $quiz_id, self::META_FROM,  true );
        $until = (string) get_post_meta( $quiz_id, self::META_UNTIL, true );

        $no_restriction = '' === $from && '' === $until;
        $today          = current_time( 'Y-m-d' );
        $pending        = ! empty( $from )  && $today < $from;
        $expired        = ! empty( $until ) && $today > $until;
        $active         = ! $no_restriction && ! $pending && ! $expired;

        return compact( 'no_restriction', 'active', 'pending', 'expired', 'from', 'until' );
    }

    // ── Privados ───────────────────────────────────────────────────────────────

    private static function validate_date( string $date ): bool {
        if ( '' === $date ) {
            return false;
        }
        $d = \DateTime::createFromFormat( 'Y-m-d', $date );
        return $d instanceof \DateTime && $d->format( 'Y-m-d' ) === $date;
    }

    private static function format_date( string $ymd ): string {
        if ( ! self::validate_date( $ymd ) ) {
            return $ymd;
        }
        $d      = \DateTime::createFromFormat( 'Y-m-d', $ymd );
        $months = [
            '',
            'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
        ];
        return $d->format( 'j' ) . ' de ' . $months[ (int) $d->format( 'n' ) ] . ' de ' . $d->format( 'Y' );
    }
}
