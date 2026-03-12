<?php
/**
 * FairPlay LMS – Onboarding Controller
 *
 * Flujo:
 *   1. Admin crea usuario → se genera reset key → se envía email de bienvenida (expira 24h).
 *   2. Usuario hace clic en el link → página frontend con shortcode [fplms_onboarding].
 *   3. PASO 1: Se muestran los T&C de la empresa que corresponde (Eliora o FairPlay).
 *      Si no acepta, se bloquea el avance. Si rechaza expresamente, queda en 'rejected'.
 *   4. PASO 2: Tras aceptar T&C, formulario para definir nueva contraseña.
 *   5. Se activa el usuario y se redirige al portal MasterStudy.
 *   6. Cualquier intento de login con estado 'pending' muestra mensaje específico.
 *   7. Admin puede reenviar el email desde la tabla de usuarios.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FairPlay_LMS_Onboarding {

    // ── Páginas admin ────────────────────────────────────────────────────────

    /**
     * Registra el submenú "Configuración" → "Términos y Condiciones".
     * Llamado desde FairPlay_LMS_Admin_Menu::register().
     */
    public function register_admin_menu(): void {
        add_submenu_page(
            'fplms-dashboard',
            'Términos y Condiciones',
            'Términos y Condiciones',
            FairPlay_LMS_Config::CAP_MANAGE_USERS,
            'fplms-terms',
            [ $this, 'render_terms_admin_page' ]
        );
    }

    /**
     * Procesa el guardado de la página de configuración de T&C.
     * Llamado en admin_init.
     */
    public function handle_terms_form(): void {
        if ( ! isset( $_POST['fplms_terms_save_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fplms_terms_save_nonce'] ) ), 'fplms_terms_save' ) ) {
            wp_die( 'Nonce inválido' );
        }
        if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_USERS ) ) {
            wp_die( 'Sin permisos' );
        }

        // Guardar contenido HTML (permitido: kses_post)
        $content_eliora  = isset( $_POST['fplms_terms_eliora'] )
            ? wp_kses_post( wp_unslash( $_POST['fplms_terms_eliora'] ) )
            : '';
        $content_fairplay = isset( $_POST['fplms_terms_fairplay'] )
            ? wp_kses_post( wp_unslash( $_POST['fplms_terms_fairplay'] ) )
            : '';

        update_option( FairPlay_LMS_Config::OPTION_TERMS_ELIORA,   $content_eliora );
        update_option( FairPlay_LMS_Config::OPTION_TERMS_FAIRPLAY,  $content_fairplay );
        update_option( FairPlay_LMS_Config::OPTION_EMAIL_ELIORA,
            sanitize_email( wp_unslash( $_POST['fplms_email_eliora'] ?? '' ) ) );
        update_option( FairPlay_LMS_Config::OPTION_EMAIL_FAIRPLAY,
            sanitize_email( wp_unslash( $_POST['fplms_email_fairplay'] ?? '' ) ) );
        update_option( FairPlay_LMS_Config::OPTION_ONBOARDING_PAGE_ID,
            absint( $_POST['fplms_onboarding_page_id'] ?? 0 ) );

        wp_safe_redirect( add_query_arg( [ 'page' => 'fplms-terms', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Página de configuración de T&C en el panel admin.
     */
    public function render_terms_admin_page(): void {
        if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_USERS ) ) {
            wp_die( 'Sin permisos' );
        }

        $saved           = isset( $_GET['saved'] );
        $terms_eliora    = get_option( FairPlay_LMS_Config::OPTION_TERMS_ELIORA, '' );
        $terms_fairplay  = get_option( FairPlay_LMS_Config::OPTION_TERMS_FAIRPLAY, '' );
        $email_eliora    = get_option( FairPlay_LMS_Config::OPTION_EMAIL_ELIORA, '' );
        $email_fairplay  = get_option( FairPlay_LMS_Config::OPTION_EMAIL_FAIRPLAY, '' );
        $page_id         = (int) get_option( FairPlay_LMS_Config::OPTION_ONBOARDING_PAGE_ID, 0 );

        // Obtener todas las páginas publicadas para el selector
        $pages = get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );

        ?>
        <div class="wrap">
            <h1 style="display:flex;align-items:center;gap:10px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/>
                </svg>
                Términos y Condiciones – Configuración
            </h1>

            <?php if ( $saved ) : ?>
                <div class="notice notice-success is-dismissible"><p>✓ Configuración guardada correctamente.</p></div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field( 'fplms_terms_save', 'fplms_terms_save_nonce' ); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="fplms_onboarding_page_id">Página de Onboarding</label></th>
                        <td>
                            <select name="fplms_onboarding_page_id" id="fplms_onboarding_page_id" style="min-width:300px;">
                                <option value="0">— Seleccionar página —</option>
                                <?php foreach ( $pages as $p ) : ?>
                                    <option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $page_id, $p->ID ); ?>>
                                        <?php echo esc_html( $p->post_title ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                Página WordPress que contiene el shortcode <code>[fplms_onboarding]</code>.
                                El link del email apuntará a esta página.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fplms_email_eliora">Email remitente – Eliora</label></th>
                        <td>
                            <input type="email" name="fplms_email_eliora" id="fplms_email_eliora"
                                   value="<?php echo esc_attr( $email_eliora ); ?>"
                                   class="regular-text" placeholder="noreply@eliora.com">
                            <p class="description">El correo de bienvenida a usuarios de Eliora se enviará desde esta dirección.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fplms_email_fairplay">Email remitente – FairPlay</label></th>
                        <td>
                            <input type="email" name="fplms_email_fairplay" id="fplms_email_fairplay"
                                   value="<?php echo esc_attr( $email_fairplay ); ?>"
                                   class="regular-text" placeholder="noreply@fairplay.com">
                            <p class="description">El correo de bienvenida a usuarios de FairPlay se enviará desde esta dirección.</p>
                        </td>
                    </tr>
                </table>

                <h2 style="margin-top:30px;">Contrato de Servicio – Eliora</h2>
                <p class="description">Pega aquí el contenido HTML (o texto) del contrato para usuarios de la empresa Eliora.</p>
                <?php
                wp_editor( $terms_eliora, 'fplms_terms_eliora', [
                    'textarea_name' => 'fplms_terms_eliora',
                    'textarea_rows' => 20,
                    'media_buttons' => false,
                    'teeny'         => false,
                ] );
                ?>

                <h2 style="margin-top:30px;">Contrato de Servicio – FairPlay</h2>
                <p class="description">Pega aquí el contenido HTML (o texto) del contrato para usuarios de la empresa FairPlay.</p>
                <?php
                wp_editor( $terms_fairplay, 'fplms_terms_fairplay', [
                    'textarea_name' => 'fplms_terms_fairplay',
                    'textarea_rows' => 20,
                    'media_buttons' => false,
                    'teeny'         => false,
                ] );
                ?>

                <p style="margin-top:24px;">
                    <?php submit_button( 'Guardar configuración', 'primary', 'submit', false ); ?>
                </p>
            </form>

            <hr style="margin-top:40px;">
            <h3>Shortcode para Elementor</h3>
            <p>
                Crea una nueva página en WordPress (ej. <em>/bienvenida/</em>), añade un widget
                <strong>Shortcode</strong> de Elementor y pega el siguiente código:
            </p>
            <code style="display:block;background:#f0f0f1;padding:10px 14px;border-radius:4px;font-size:14px;">[fplms_onboarding]</code>
            <p style="color:#777;font-size:13px;">
                Luego selecciona esa página en el campo <em>"Página de Onboarding"</em> arriba.
                El link que se envía en el email apuntará automáticamente a esa URL.
            </p>
        </div>
        <?php
    }

    // ── Email de bienvenida ───────────────────────────────────────────────────

    /**
     * Se invoca después de crear un usuario desde el panel FairPlay LMS.
     * Marca al usuario como 'pending', genera el reset key y envía el email.
     *
     * @param int $user_id
     */
    public function send_welcome_email( int $user_id ): void {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        // Marcar como pendiente de onboarding
        update_user_meta( $user_id, FairPlay_LMS_Config::USER_META_ONBOARDING_STATUS, 'pending' );

        // Detectar empresa del usuario (para elegir T&C y remitente)
        $company_slug = $this->detect_user_company( $user_id );

        // Generar reset key (expira ~24h según WordPress)
        $reset_key = get_password_reset_key( $user );
        if ( is_wp_error( $reset_key ) ) {
            return;
        }

        $this->dispatch_welcome_email( $user, $reset_key, $company_slug );
    }

    /**
     * Detecta si el usuario pertenece a Eliora o FairPlay según su empresa
     * asignada en el meta de estructuras.
     *
     * @param int $user_id
     * @return string 'eliora' | 'fairplay'
     */
    private function detect_user_company( int $user_id ): string {
        $company_term_id = (int) get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_COMPANY, true );
        if ( $company_term_id ) {
            $term = get_term( $company_term_id, FairPlay_LMS_Config::TAX_COMPANY );
            if ( $term && ! is_wp_error( $term ) ) {
                $slug = strtolower( $term->slug );
                if ( strpos( $slug, 'eliora' ) !== false ) {
                    return 'eliora';
                }
                if ( strpos( $slug, 'fairplay' ) !== false ) {
                    return 'fairplay';
                }
                // Fallback por nombre
                $name = strtolower( $term->name );
                if ( strpos( $name, 'eliora' ) !== false ) {
                    return 'eliora';
                }
            }
        }
        return 'fairplay'; // Default
    }

    /**
     * Construye y envía el email de bienvenida.
     *
     * @param WP_User $user
     * @param string  $reset_key
     * @param string  $company_slug 'eliora' | 'fairplay'
     */
    private function dispatch_welcome_email( WP_User $user, string $reset_key, string $company_slug ): void {
        $page_id = (int) get_option( FairPlay_LMS_Config::OPTION_ONBOARDING_PAGE_ID, 0 );
        if ( ! $page_id ) {
            // Fallback: usar wp-login reset URL
            $onboarding_url = network_site_url( "wp-login.php?action=rp&key={$reset_key}&login=" . rawurlencode( $user->user_login ), 'login' );
        } else {
            $onboarding_url = add_query_arg(
                [
                    'fplms_key'   => rawurlencode( $reset_key ),
                    'fplms_login' => rawurlencode( $user->user_login ),
                ],
                get_permalink( $page_id )
            );
        }

        $site_name = get_bloginfo( 'name' );
        $blog_url  = home_url();

        // Determinar remitente según empresa
        if ( 'eliora' === $company_slug ) {
            $from_email = get_option( FairPlay_LMS_Config::OPTION_EMAIL_ELIORA, get_option( 'admin_email' ) );
            $from_name  = 'Eliora e-Learning';
        } else {
            $from_email = get_option( FairPlay_LMS_Config::OPTION_EMAIL_FAIRPLAY, get_option( 'admin_email' ) );
            $from_name  = 'FairPlay e-Learning';
        }

        $first_name = $user->first_name ?: $user->user_login;

        $subject = "Bienvenido/a a {$site_name} – Activa tu cuenta";

        $body = $this->build_email_html( $first_name, $user->user_login, $site_name, $blog_url, $onboarding_url, $company_slug, $from_name );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            "From: {$from_name} <{$from_email}>",
        ];

        wp_mail( $user->user_email, $subject, $body, $headers );
    }

    /**
     * Genera el HTML del email de bienvenida.
     */
    private function build_email_html(
        string $first_name,
        string $user_login,
        string $site_name,
        string $blog_url,
        string $onboarding_url,
        string $company_slug,
        string $from_name
    ): string {
        $accent = 'eliora' === $company_slug ? '#1a56db' : '#e3342f';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bienvenido/a a {$site_name}</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:30px 0;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
        <!-- Header -->
        <tr>
          <td style="background:{$accent};padding:32px 40px;text-align:center;">
            <h1 style="color:#ffffff;margin:0;font-size:24px;font-weight:700;">{$from_name}</h1>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding:40px;">
            <p style="font-size:16px;color:#333;margin:0 0 16px;">Hola, <strong>{$first_name}</strong>,</p>
            <p style="font-size:15px;color:#555;margin:0 0 16px;">
              Tu cuenta en <strong>{$site_name}</strong> ha sido creada exitosamente.
              Para activarla, necesitas:
            </p>
            <ol style="font-size:15px;color:#555;padding-left:20px;margin:0 0 24px;">
              <li style="margin-bottom:8px;">Leer y aceptar el <strong>Contrato de Servicio</strong>.</li>
              <li style="margin-bottom:8px;">Crear tu <strong>contraseña de acceso</strong>.</li>
            </ol>
            <p style="font-size:14px;color:#e53e3e;background:#fff5f5;border-left:4px solid #e53e3e;padding:12px 16px;border-radius:4px;margin:0 0 28px;">
              ⚠️ Este enlace expirará en <strong>24 horas</strong>.
              Si no lo usas a tiempo, contacta al administrador para recibir un nuevo correo.
            </p>
            <div style="text-align:center;margin-bottom:28px;">
              <a href="{$onboarding_url}"
                 style="display:inline-block;background:{$accent};color:#ffffff;text-decoration:none;
                        padding:14px 36px;border-radius:6px;font-size:16px;font-weight:700;">
                Activar mi cuenta
              </a>
            </div>
            <p style="font-size:13px;color:#777;margin:0 0 6px;">Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
            <p style="font-size:13px;word-break:break-all;color:{$accent};margin:0 0 24px;">{$onboarding_url}</p>
            <hr style="border:none;border-top:1px solid #eee;margin:0 0 20px;">
            <p style="font-size:13px;color:#aaa;margin:0;">
              Este correo fue enviado automáticamente por {$site_name}.
              Si no reconoces este mensaje, puedes ignorarlo.
            </p>
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="background:#f9f9f9;padding:16px 40px;text-align:center;">
            <p style="font-size:12px;color:#aaa;margin:0;">© {$site_name} – <a href="{$blog_url}" style="color:{$accent};text-decoration:none;">{$blog_url}</a></p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;
    }

    // ── AJAX: reenviar email ──────────────────────────────────────────────────

    /**
     * Manejador AJAX para reenviar el email de bienvenida desde el panel admin.
     */
    public function ajax_resend_welcome_email(): void {
        check_ajax_referer( 'fplms_resend_welcome', 'nonce' );

        if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_USERS ) ) {
            wp_send_json_error( [ 'message' => 'Sin permisos.' ] );
        }

        $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
        if ( ! $user_id ) {
            wp_send_json_error( [ 'message' => 'ID de usuario inválido.' ] );
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            wp_send_json_error( [ 'message' => 'Usuario no encontrado.' ] );
        }

        $status = get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_ONBOARDING_STATUS, true );
        // Permitir reenvío si está pending, rejected, o si nunca se envió
        if ( 'completed' === $status ) {
            wp_send_json_error( [ 'message' => 'El usuario ya completó el onboarding.' ] );
        }

        // Resetear a pending antes de reenviar
        update_user_meta( $user_id, FairPlay_LMS_Config::USER_META_ONBOARDING_STATUS, 'pending' );

        $reset_key    = get_password_reset_key( $user );
        if ( is_wp_error( $reset_key ) ) {
            wp_send_json_error( [ 'message' => 'No se pudo generar el enlace de activación.' ] );
        }

        $company_slug = $this->detect_user_company( $user_id );
        $this->dispatch_welcome_email( $user, $reset_key, $company_slug );

        wp_send_json_success( [ 'message' => "Email de bienvenida reenviado a {$user->user_email}." ] );
    }

    // ── Shortcode frontend [fplms_onboarding] ────────────────────────────────

    /**
     * Registra el shortcode.
     */
    public function register_shortcode(): void {
        add_shortcode( 'fplms_onboarding', [ $this, 'render_onboarding_page' ] );
    }

    /**
     * Renderiza la página de onboarding (T&C + nueva contraseña).
     * Se inyecta vía shortcode en una página de Elementor/WordPress.
     *
     * @return string HTML del onboarding
     */
    public function render_onboarding_page(): string {
        // Parámetros recibidos en la URL
        $raw_key   = isset( $_GET['fplms_key'] )   ? wp_unslash( $_GET['fplms_key'] )   : '';
        $raw_login = isset( $_GET['fplms_login'] ) ? wp_unslash( $_GET['fplms_login'] ) : '';

        // Limpiar la clave: WP usa base64 con '/' que puede llegar urlencodeado
        $key   = sanitize_text_field( $raw_key );
        $login = sanitize_user( $raw_login );

        // Estado del flujo: step1=terms, step2=password, done, error
        $step = isset( $_SESSION['fplms_ob_step'] ) ? $_SESSION['fplms_ob_step'] : 'step1';

        // Si se está procesando un POST, lo manejamos aquí (sin AJAX para mayor compatibilidad con Elementor)
        if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['fplms_ob_action'] ) ) {
            return $this->process_onboarding_post( $key, $login );
        }

        // Validar key y login antes de mostrar nada
        if ( empty( $key ) || empty( $login ) ) {
            return $this->render_ob_error( 'Enlace inválido. Por favor solicita un nuevo correo de activación al administrador.' );
        }

        // Verificar usuario
        $user = get_user_by( 'login', $login );
        if ( ! $user ) {
            return $this->render_ob_error( 'Usuario no encontrado.' );
        }

        // Verificar que aún está pendiente (no completado)
        $ob_status = get_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_ONBOARDING_STATUS, true );
        if ( 'completed' === $ob_status ) {
            return $this->render_ob_already_done();
        }

        // Verificar el reset key de WordPress (también valida expiración 24h)
        $check = check_password_reset_key( $key, $login );
        if ( is_wp_error( $check ) ) {
            return $this->render_ob_expired( $user );
        }

        // Determinar en qué paso estamos:
        // Si T&C ya fueron aceptados en esta sesión (guardado en user meta temporal),
        // vamos directo al paso 2
        $terms_accepted_temp = get_user_meta( $user->ID, '_fplms_terms_accepted_temp', true );
        if ( $terms_accepted_temp ) {
            return $this->render_step2_password( $key, $login, $user );
        }

        return $this->render_step1_terms( $key, $login, $user );
    }

    /**
     * Procesa el POST del formulario de onboarding (T&C o contraseña).
     */
    private function process_onboarding_post( string $key, string $login ): string {
        $action = sanitize_text_field( wp_unslash( $_POST['fplms_ob_action'] ) );

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fplms_ob_nonce'] ?? '' ) ), 'fplms_onboarding_' . $login ) ) {
            return $this->render_ob_error( 'Sesión inválida. Por favor recarga la página.' );
        }

        $user = get_user_by( 'login', $login );
        if ( ! $user ) {
            return $this->render_ob_error( 'Usuario no encontrado.' );
        }

        // Verificar key aún válida
        $check = check_password_reset_key( $key, $login );
        if ( is_wp_error( $check ) ) {
            return $this->render_ob_expired( $user );
        }

        // ── Paso 1: Aceptar / rechazar T&C ────────────────────────────────
        if ( 'accept_terms' === $action ) {
            $accepted = isset( $_POST['fplms_accept_terms'] ) && '1' === $_POST['fplms_accept_terms'];
            if ( ! $accepted ) {
                // Mostrar paso 1 con error
                return $this->render_step1_terms( $key, $login, $user, 'Debes aceptar los términos y condiciones para continuar.' );
            }

            // Marcar aceptación temporal para esta sesión (sin activar aún)
            update_user_meta( $user->ID, '_fplms_terms_accepted_temp', time() );

            return $this->render_step2_password( $key, $login, $user );
        }

        // ── Rechazar T&C expresamente ──────────────────────────────────────
        if ( 'reject_terms' === $action ) {
            update_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_ONBOARDING_STATUS, 'rejected' );
            update_user_meta( $user->ID, 'fplms_user_status', 'inactive' );
            delete_user_meta( $user->ID, '_fplms_terms_accepted_temp' );
            return $this->render_ob_rejected();
        }

        // ── Paso 2: Guardar nueva contraseña ──────────────────────────────
        if ( 'set_password' === $action ) {
            $pass1 = isset( $_POST['fplms_pass1'] ) ? wp_unslash( $_POST['fplms_pass1'] ) : '';
            $pass2 = isset( $_POST['fplms_pass2'] ) ? wp_unslash( $_POST['fplms_pass2'] ) : '';

            if ( empty( $pass1 ) || $pass1 !== $pass2 ) {
                return $this->render_step2_password( $key, $login, $user, 'Las contraseñas no coinciden o están vacías.' );
            }
            if ( strlen( $pass1 ) < 8 ) {
                return $this->render_step2_password( $key, $login, $user, 'La contraseña debe tener al menos 8 caracteres.' );
            }

            // Guardar todo: contraseña, T&C aceptados, onboarding completado
            reset_password( $user, $pass1 );

            $ip = $this->get_client_ip();
            update_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_TERMS_ACCEPTED, time() );
            update_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_TERMS_IP, $ip );
            update_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_TERMS_COMPANY, $this->detect_user_company( $user->ID ) );
            update_user_meta( $user->ID, FairPlay_LMS_Config::USER_META_ONBOARDING_STATUS, 'completed' );
            // Activar usuario si estaba inactivo/pendiente
            update_user_meta( $user->ID, 'fplms_user_status', 'active' );
            // Limpiar meta temporal
            delete_user_meta( $user->ID, '_fplms_terms_accepted_temp' );

            // Login automático
            wp_set_current_user( $user->ID );
            wp_set_auth_cookie( $user->ID, false );

            return $this->render_ob_success( $user );
        }

        return $this->render_ob_error( 'Acción desconocida.' );
    }

    // ── Renderizado de pasos ─────────────────────────────────────────────────

    private function render_step1_terms( string $key, string $login, WP_User $user, string $error = '' ): string {
        $company_slug  = $this->detect_user_company( $user->ID );
        $option_key    = 'eliora' === $company_slug
            ? FairPlay_LMS_Config::OPTION_TERMS_ELIORA
            : FairPlay_LMS_Config::OPTION_TERMS_FAIRPLAY;
        $terms_content = get_option( $option_key, '' );
        $company_label = 'eliora' === $company_slug ? 'Eliora' : 'FairPlay';
        $nonce         = wp_create_nonce( 'fplms_onboarding_' . $login );
        $first_name    = $user->first_name ?: $user->user_login;
        $error_html    = $error ? '<div class="fplms-ob-error">' . esc_html( $error ) . '</div>' : '';

        ob_start();
        ?>
        <div class="fplms-onboarding-wrap">
            <?php echo $this->ob_styles(); ?>

            <div class="fplms-ob-card">
                <!-- Paso indicador -->
                <div class="fplms-ob-steps">
                    <div class="fplms-ob-step active">
                        <span class="fplms-ob-step-num">1</span>
                        <span class="fplms-ob-step-label">Términos y Condiciones</span>
                    </div>
                    <div class="fplms-ob-step-divider"></div>
                    <div class="fplms-ob-step">
                        <span class="fplms-ob-step-num">2</span>
                        <span class="fplms-ob-step-label">Crear Contraseña</span>
                    </div>
                </div>

                <div class="fplms-ob-body">
                    <h2 class="fplms-ob-title">Contrato de Servicio – <?php echo esc_html( $company_label ); ?></h2>
                    <p class="fplms-ob-subtitle">Hola <strong><?php echo esc_html( $first_name ); ?></strong>, para activar tu cuenta debes leer y aceptar el siguiente contrato.</p>

                    <?php echo $error_html; ?>

                    <div class="fplms-ob-terms-box">
                        <?php echo wp_kses_post( $terms_content ?: '<p><em>El contenido del contrato aún no ha sido configurado. Contacta al administrador.</em></p>' ); ?>
                    </div>

                    <form method="post" class="fplms-ob-form">
                        <input type="hidden" name="fplms_key"      value="<?php echo esc_attr( $key ); ?>">
                        <input type="hidden" name="fplms_login"    value="<?php echo esc_attr( $login ); ?>">
                        <input type="hidden" name="fplms_ob_nonce" value="<?php echo esc_attr( $nonce ); ?>">
                        <input type="hidden" name="fplms_ob_action" value="accept_terms">

                        <label class="fplms-ob-check-label">
                            <input type="checkbox" name="fplms_accept_terms" value="1" id="fplms_accept_cb">
                            He leído y acepto los términos y condiciones del Contrato de Servicio.
                        </label>

                        <div class="fplms-ob-actions">
                            <button type="submit" class="fplms-ob-btn fplms-ob-btn-primary" id="fplms_ob_accept_btn" disabled>
                                Aceptar y continuar →
                            </button>
                        </div>
                    </form>

                    <!-- Rechazo explícito -->
                    <form method="post" class="fplms-ob-reject-form">
                        <input type="hidden" name="fplms_key"      value="<?php echo esc_attr( $key ); ?>">
                        <input type="hidden" name="fplms_login"    value="<?php echo esc_attr( $login ); ?>">
                        <input type="hidden" name="fplms_ob_nonce" value="<?php echo esc_attr( $nonce ); ?>">
                        <input type="hidden" name="fplms_ob_action" value="reject_terms">
                        <p class="fplms-ob-reject-hint">
                            Si no aceptas los términos no podrás acceder a la plataforma.
                            <button type="submit" class="fplms-ob-btn-link" onclick="return confirm('¿Estás seguro? No podrás acceder al portal si rechazas los términos.');">
                                No acepto los términos
                            </button>
                        </p>
                    </form>
                </div>
            </div>

            <script>
            (function() {
                var cb  = document.getElementById('fplms_accept_cb');
                var btn = document.getElementById('fplms_ob_accept_btn');
                if ( cb && btn ) {
                    cb.addEventListener('change', function() {
                        btn.disabled = ! this.checked;
                    });
                }
            })();
            </script>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_step2_password( string $key, string $login, WP_User $user, string $error = '' ): string {
        $nonce      = wp_create_nonce( 'fplms_onboarding_' . $login );
        $first_name = $user->first_name ?: $user->user_login;
        $error_html = $error ? '<div class="fplms-ob-error">' . esc_html( $error ) . '</div>' : '';

        ob_start();
        ?>
        <div class="fplms-onboarding-wrap">
            <?php echo $this->ob_styles(); ?>

            <div class="fplms-ob-card">
                <div class="fplms-ob-steps">
                    <div class="fplms-ob-step done">
                        <span class="fplms-ob-step-num">✓</span>
                        <span class="fplms-ob-step-label">Términos y Condiciones</span>
                    </div>
                    <div class="fplms-ob-step-divider active"></div>
                    <div class="fplms-ob-step active">
                        <span class="fplms-ob-step-num">2</span>
                        <span class="fplms-ob-step-label">Crear Contraseña</span>
                    </div>
                </div>

                <div class="fplms-ob-body">
                    <h2 class="fplms-ob-title">Crea tu contraseña de acceso</h2>
                    <p class="fplms-ob-subtitle">¡Perfecto, <strong><?php echo esc_html( $first_name ); ?></strong>! Ya aceptaste los términos. Ahora define tu contraseña para acceder al portal.</p>

                    <?php echo $error_html; ?>

                    <form method="post" class="fplms-ob-form" id="fplms_pass_form">
                        <input type="hidden" name="fplms_key"      value="<?php echo esc_attr( $key ); ?>">
                        <input type="hidden" name="fplms_login"    value="<?php echo esc_attr( $login ); ?>">
                        <input type="hidden" name="fplms_ob_nonce" value="<?php echo esc_attr( $nonce ); ?>">
                        <input type="hidden" name="fplms_ob_action" value="set_password">

                        <div class="fplms-ob-field">
                            <label for="fplms_pass1">Nueva contraseña <span style="color:#e53e3e">*</span></label>
                            <div class="fplms-ob-pass-wrap">
                                <input type="password" name="fplms_pass1" id="fplms_pass1" class="fplms-ob-input" required autocomplete="new-password">
                                <button type="button" class="fplms-ob-toggle-pass" data-target="fplms_pass1" aria-label="Mostrar contraseña">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                        </div>

                        <div class="fplms-ob-field">
                            <label for="fplms_pass2">Confirmar contraseña <span style="color:#e53e3e">*</span></label>
                            <div class="fplms-ob-pass-wrap">
                                <input type="password" name="fplms_pass2" id="fplms_pass2" class="fplms-ob-input" required autocomplete="new-password">
                                <button type="button" class="fplms-ob-toggle-pass" data-target="fplms_pass2" aria-label="Mostrar contraseña">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                            <div id="fplms_pass_match" style="font-size:13px;margin-top:4px;"></div>
                        </div>

                        <p class="fplms-ob-hint">Mínimo 8 caracteres.</p>

                        <div class="fplms-ob-actions">
                            <button type="submit" class="fplms-ob-btn fplms-ob-btn-primary">
                                Guardar contraseña y acceder al portal
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            (function() {
                // Toggle visibilidad contraseña
                document.querySelectorAll('.fplms-ob-toggle-pass').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var id = this.getAttribute('data-target');
                        var inp = document.getElementById(id);
                        if (inp) inp.type = inp.type === 'password' ? 'text' : 'password';
                    });
                });
                // Verificar coincidencia en tiempo real
                var p1 = document.getElementById('fplms_pass1');
                var p2 = document.getElementById('fplms_pass2');
                var fb = document.getElementById('fplms_pass_match');
                if (p1 && p2 && fb) {
                    function checkMatch() {
                        if (!p2.value) { fb.textContent = ''; return; }
                        if (p1.value === p2.value) {
                            fb.textContent = '✓ Las contraseñas coinciden';
                            fb.style.color = '#38a169';
                        } else {
                            fb.textContent = '✗ No coinciden';
                            fb.style.color = '#e53e3e';
                        }
                    }
                    p1.addEventListener('input', checkMatch);
                    p2.addEventListener('input', checkMatch);
                }
            })();
            </script>
        </div>
        <?php
        return ob_get_clean();
    }

    // ── Pantallas de estado ───────────────────────────────────────────────────

    private function render_ob_error( string $msg ): string {
        ob_start();
        ?>
        <div class="fplms-onboarding-wrap">
            <?php echo $this->ob_styles(); ?>
            <div class="fplms-ob-card fplms-ob-state-card">
                <div class="fplms-ob-state-icon" style="color:#e53e3e">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <h2>Enlace inválido</h2>
                <p><?php echo esc_html( $msg ); ?></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_ob_expired( WP_User $user ): string {
        ob_start();
        ?>
        <div class="fplms-onboarding-wrap">
            <?php echo $this->ob_styles(); ?>
            <div class="fplms-ob-card fplms-ob-state-card">
                <div class="fplms-ob-state-icon" style="color:#dd6b20">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <h2>Enlace expirado</h2>
                <p>El enlace de activación ha expirado (validez: 24 horas). Contacta al administrador para que te reenvíe el correo de bienvenida.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_ob_rejected(): string {
        ob_start();
        ?>
        <div class="fplms-onboarding-wrap">
            <?php echo $this->ob_styles(); ?>
            <div class="fplms-ob-card fplms-ob-state-card">
                <div class="fplms-ob-state-icon" style="color:#718096">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                </div>
                <h2>Términos rechazados</h2>
                <p>Has rechazado los términos y condiciones. No podrás acceder al portal sin aceptarlos.</p>
                <p>Si cambias de opinión, contacta al administrador para solicitar un nuevo enlace de activación.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_ob_already_done(): string {
        $login_url = home_url();
        ob_start();
        ?>
        <div class="fplms-onboarding-wrap">
            <?php echo $this->ob_styles(); ?>
            <div class="fplms-ob-card fplms-ob-state-card">
                <div class="fplms-ob-state-icon" style="color:#38a169">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h2>Cuenta ya activada</h2>
                <p>Tu cuenta ya fue activada anteriormente. Puedes iniciar sesión normalmente.</p>
                <a href="<?php echo esc_url( $login_url ); ?>" class="fplms-ob-btn fplms-ob-btn-primary" style="display:inline-block;margin-top:16px;">
                    Ir al portal
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_ob_success( WP_User $user ): string {
        $portal_url = home_url();
        $first_name = $user->first_name ?: $user->user_login;
        ob_start();
        ?>
        <div class="fplms-onboarding-wrap">
            <?php echo $this->ob_styles(); ?>
            <div class="fplms-ob-card fplms-ob-state-card">
                <div class="fplms-ob-state-icon" style="color:#38a169">
                    <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h2>¡Bienvenido/a, <?php echo esc_html( $first_name ); ?>!</h2>
                <p>Tu cuenta está activa y tu contraseña ha sido guardada. Serás redirigido al portal en unos segundos.</p>
                <a href="<?php echo esc_url( $portal_url ); ?>" class="fplms-ob-btn fplms-ob-btn-primary" style="display:inline-block;margin-top:16px;">
                    Ir al portal ahora
                </a>
            </div>
            <script>
                setTimeout(function(){ window.location = '<?php echo esc_js( $portal_url ); ?>'; }, 3500);
            </script>
        </div>
        <?php
        return ob_get_clean();
    }

    // ── Estilos CSS del shortcode ─────────────────────────────────────────────

    private function ob_styles(): string {
        return <<<CSS
<style>
.fplms-onboarding-wrap { max-width: 760px; margin: 40px auto; padding: 0 16px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
.fplms-ob-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.10); overflow: hidden; }
.fplms-ob-steps { display: flex; align-items: center; padding: 20px 32px; background: #f7f8fa; border-bottom: 1px solid #e5e7eb; gap: 8px; }
.fplms-ob-step { display: flex; align-items: center; gap: 8px; color: #9ca3af; font-size: 14px; }
.fplms-ob-step.active { color: #1a56db; font-weight: 600; }
.fplms-ob-step.done { color: #38a169; font-weight: 600; }
.fplms-ob-step-num { display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: #e5e7eb; font-size: 13px; font-weight: 700; }
.fplms-ob-step.active .fplms-ob-step-num { background: #1a56db; color: #fff; }
.fplms-ob-step.done .fplms-ob-step-num { background: #38a169; color: #fff; }
.fplms-ob-step-divider { flex: 1; height: 2px; background: #e5e7eb; min-width: 24px; }
.fplms-ob-step-divider.active { background: #1a56db; }
.fplms-ob-body { padding: 32px 40px; }
.fplms-ob-title { font-size: 22px; font-weight: 700; color: #111827; margin: 0 0 8px; }
.fplms-ob-subtitle { font-size: 15px; color: #6b7280; margin: 0 0 24px; }
.fplms-ob-error { background: #fff5f5; border-left: 4px solid #e53e3e; color: #c53030; padding: 12px 16px; border-radius: 4px; font-size: 14px; margin-bottom: 20px; }
.fplms-ob-terms-box { max-height: 360px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px 24px; background: #fafafa; font-size: 14px; line-height: 1.7; color: #374151; margin-bottom: 24px; }
.fplms-ob-check-label { display: flex; align-items: flex-start; gap: 10px; font-size: 14px; color: #374151; cursor: pointer; margin-bottom: 20px; }
.fplms-ob-check-label input[type="checkbox"] { margin-top: 2px; width: 18px; height: 18px; accent-color: #1a56db; flex-shrink: 0; }
.fplms-ob-actions { display: flex; gap: 12px; }
.fplms-ob-btn { padding: 12px 28px; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: opacity .2s; }
.fplms-ob-btn-primary { background: #1a56db; color: #fff; }
.fplms-ob-btn-primary:hover { opacity: .88; }
.fplms-ob-btn-primary:disabled { background: #9ca3af; cursor: not-allowed; }
.fplms-ob-reject-form { margin-top: 16px; }
.fplms-ob-reject-hint { font-size: 13px; color: #9ca3af; }
.fplms-ob-btn-link { background: none; border: none; padding: 0; font-size: 13px; color: #e53e3e; cursor: pointer; text-decoration: underline; }
.fplms-ob-field { margin-bottom: 20px; }
.fplms-ob-field label { display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 6px; }
.fplms-ob-input { width: 100%; padding: 10px 44px 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px; box-sizing: border-box; outline: none; }
.fplms-ob-input:focus { border-color: #1a56db; box-shadow: 0 0 0 3px rgba(26,86,219,.12); }
.fplms-ob-pass-wrap { position: relative; }
.fplms-ob-toggle-pass { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #9ca3af; padding: 0; }
.fplms-ob-hint { font-size: 13px; color: #9ca3af; margin: -12px 0 20px; }
.fplms-ob-state-card { padding: 48px 40px; text-align: center; }
.fplms-ob-state-icon { margin-bottom: 16px; }
.fplms-ob-state-card h2 { font-size: 22px; font-weight: 700; color: #111827; margin: 0 0 12px; }
.fplms-ob-state-card p { font-size: 15px; color: #6b7280; margin: 0 0 8px; }
@media (max-width: 600px) {
    .fplms-ob-body { padding: 24px 20px; }
    .fplms-ob-steps { padding: 16px; }
    .fplms-ob-step-label { display: none; }
}
</style>
CSS;
    }

    // ── Utilidades ────────────────────────────────────────────────────────────

    private function get_client_ip(): string {
        foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ] as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                return sanitize_text_field( strtok( $_SERVER[ $key ], ',' ) );
            }
        }
        return 'unknown';
    }
}
