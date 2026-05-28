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

        $terms_map       = isset( $_POST['fplms_terms_company'] ) && is_array( $_POST['fplms_terms_company'] )
            ? wp_unslash( $_POST['fplms_terms_company'] )
            : [];
        $email_from_map  = isset( $_POST['fplms_email_from_company'] ) && is_array( $_POST['fplms_email_from_company'] )
            ? wp_unslash( $_POST['fplms_email_from_company'] )
            : [];
        $email_name_map  = isset( $_POST['fplms_email_name_company'] ) && is_array( $_POST['fplms_email_name_company'] )
            ? wp_unslash( $_POST['fplms_email_name_company'] )
            : [];
        $email_subject_map = isset( $_POST['fplms_email_subject_company'] ) && is_array( $_POST['fplms_email_subject_company'] )
            ? wp_unslash( $_POST['fplms_email_subject_company'] )
            : [];
        $email_bg_map    = isset( $_POST['fplms_email_bg_company'] ) && is_array( $_POST['fplms_email_bg_company'] )
            ? wp_unslash( $_POST['fplms_email_bg_company'] )
            : [];
        $email_accent_map = isset( $_POST['fplms_email_accent_company'] ) && is_array( $_POST['fplms_email_accent_company'] )
            ? wp_unslash( $_POST['fplms_email_accent_company'] )
            : [];
        $email_logo_map  = isset( $_POST['fplms_email_logo_company'] ) && is_array( $_POST['fplms_email_logo_company'] )
            ? wp_unslash( $_POST['fplms_email_logo_company'] )
            : [];

        $companies = get_terms(
            [
                'taxonomy'   => FairPlay_LMS_Config::TAX_COMPANY,
                'hide_empty' => false,
            ]
        );

        if ( ! is_wp_error( $companies ) ) {
            foreach ( $companies as $company ) {
                $company_id = (int) $company->term_id;
                if ( ! $company_id ) {
                    continue;
                }

                $terms_content = isset( $terms_map[ $company_id ] )
                    ? wp_kses_post( $terms_map[ $company_id ] )
                    : '';
                $email_from = isset( $email_from_map[ $company_id ] )
                    ? sanitize_email( $email_from_map[ $company_id ] )
                    : '';
                $email_name = isset( $email_name_map[ $company_id ] )
                    ? sanitize_text_field( $email_name_map[ $company_id ] )
                    : '';
                $email_subject = isset( $email_subject_map[ $company_id ] )
                    ? sanitize_text_field( $email_subject_map[ $company_id ] )
                    : '';
                $email_bg = isset( $email_bg_map[ $company_id ] )
                    ? esc_url_raw( $email_bg_map[ $company_id ] )
                    : '';
                $email_accent = isset( $email_accent_map[ $company_id ] )
                    ? sanitize_hex_color( $email_accent_map[ $company_id ] )
                    : '';
                $email_logo = isset( $email_logo_map[ $company_id ] )
                    ? esc_url_raw( $email_logo_map[ $company_id ] )
                    : '';

                update_option( $this->get_terms_option_key( $company_id ), $terms_content );
                update_option( $this->get_company_email_from_option_key( $company_id ), $email_from );
                update_option( $this->get_company_email_name_option_key( $company_id ), $email_name );
                update_option( $this->get_company_email_subject_option_key( $company_id ), $email_subject );
                update_option( $this->get_company_email_bg_option_key( $company_id ), $email_bg );
                update_option( $this->get_company_email_accent_option_key( $company_id ), $email_accent ?: '' );
                update_option( $this->get_company_email_logo_option_key( $company_id ), $email_logo );
            }
        }

        update_option( FairPlay_LMS_Config::OPTION_ONBOARDING_PAGE_ID,
            absint( $_POST['fplms_onboarding_page_id'] ?? 0 ) );
        update_option( FairPlay_LMS_Config::OPTION_DEFAULT_COMPANY_ID,
            absint( $_POST['fplms_default_company_id'] ?? 0 ) );

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

        $saved              = isset( $_GET['saved'] );
        $page_id            = (int) get_option( FairPlay_LMS_Config::OPTION_ONBOARDING_PAGE_ID, 0 );
        $default_company_id = (int) get_option( FairPlay_LMS_Config::OPTION_DEFAULT_COMPANY_ID, 0 );

        // Obtener todas las páginas publicadas para el selector
        $pages = get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
        $companies = get_terms(
            [
                'taxonomy'   => FairPlay_LMS_Config::TAX_COMPANY,
                'hide_empty' => false,
            ]
        );

        if ( is_wp_error( $companies ) ) {
            $companies = [];
        }

        wp_enqueue_media();

        ?>
        <div class="wrap">
            <h1 style="display:flex;align-items:center;gap:10px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/>
                </svg>
                Términos y Condiciones – Configuración
            </h1>

            <style>
                .fplms-terms-shell { background: #fff; border: 1px solid #dcdcde; border-radius: 10px; padding: 18px 22px; margin-top: 12px; }
                .fplms-terms-shell .form-table th { width: 220px; }
                .fplms-terms-title { margin: 26px 0 8px; font-size: 18px; }
                .fplms-rpt-tabs-nav { display:flex; flex-wrap:wrap; gap:3px; border-bottom:2px solid #ffa800; margin: 12px 0 0; }
                .fplms-rpt-tab-btn { padding:10px 16px; border:1.5px solid #e0e0e0; border-bottom:none; background:#f5f5f5; cursor:pointer; font-size:13px; font-weight:500; color:#555; border-radius:6px 6px 0 0; transition:all .15s; }
                .fplms-rpt-tab-btn:hover { background:#fff8ee; color:#e08800; }
                .fplms-rpt-tab-btn.active { background:#ffa800; color:#fff; border-color:#ffa800; font-weight:700; }
                .fplms-company-panel { display:none; border:1px solid #dcdcde; border-top:none; border-radius:0 0 8px 8px; padding:22px; margin-bottom:18px; background:#fff; }
                .fplms-company-panel.is-visible { display:block; }
                .fplms-media-help { margin-top: 6px; font-size: 12px; color: #646970; }
                .fplms-image-preview { margin-top:8px; }
                .fplms-image-preview img { max-width:320px; height:auto; border:1px solid #ddd; border-radius:6px; }
                .fplms-contract-note { margin-top: 0; color: #646970; }
                .fplms-email-layout-row { display:grid; grid-template-columns:minmax(560px, 1.55fr) minmax(360px, 1fr); gap:18px; align-items:start; }
                .fplms-email-fields .form-table { margin-top:18px; }
                .fplms-email-fields .regular-text { width:100%; max-width:100%; }
                .fplms-email-preview-wrap { margin-top: 18px; border: 1px solid #dcdcde; border-radius: 8px; overflow: hidden; background: #f8f9fa; }
                .fplms-email-preview-head { padding: 10px 14px; background: #fff; border-bottom: 1px solid #dcdcde; }
                .fplms-email-preview-head strong { display: block; font-size: 13px; }
                .fplms-email-preview-head span { color: #646970; font-size: 12px; }
                .fplms-email-preview-canvas { padding: 14px; }
                .fplms-email-preview-card { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #ececec; }
                .fplms-email-preview-header { padding: 26px 22px; text-align: center; background: #f6b23a; background-size: cover; background-position: center; }
                .fplms-email-preview-logo { max-width: 170px; max-height: 64px; display: none; margin: 0 auto 8px; }
                .fplms-email-preview-title { color: #fff; font-size: 20px; font-weight: 700; margin: 0; }
                .fplms-email-preview-body { padding: 22px; color: #555; font-size: 13px; line-height: 1.55; }
                .fplms-email-preview-subject { margin: 0 0 10px; font-size: 14px; color: #111; font-weight: 600; }
                .fplms-email-preview-btn { display: inline-block; margin-top: 12px; padding: 10px 18px; border-radius: 5px; color: #fff; font-weight: 700; font-size: 12px; text-decoration: none; background: #f6b23a; }
                .fplms-email-preview-footer { padding: 12px 22px; background: #f9f9f9; text-align: center; color: #999; font-size: 11px; }
                .fplms-email-preview-footer a { color: inherit; text-decoration: none; }
                @media (max-width: 1280px) {
                    .fplms-email-layout-row { grid-template-columns: 1.25fr 1fr; }
                }
                @media (max-width: 1080px) {
                    .fplms-email-layout-row { grid-template-columns: 1fr; }
                }
            </style>

            <?php if ( $saved ) : ?>
                <div class="notice notice-success is-dismissible"><p>✓ Configuración guardada correctamente.</p></div>
            <?php endif; ?>

            <form method="post" action="" class="fplms-terms-shell">
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
                        <th scope="row"><label for="fplms_default_company_id">Empresa por defecto</label></th>
                        <td>
                            <select name="fplms_default_company_id" id="fplms_default_company_id" style="min-width:300px;">
                                <option value="0">— Sin empresa por defecto —</option>
                                <?php foreach ( $companies as $company ) : ?>
                                    <option value="<?php echo esc_attr( $company->term_id ); ?>" <?php selected( $default_company_id, (int) $company->term_id ); ?>>
                                        <?php echo esc_html( $company->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Se usa cuando un usuario no tiene empresa asignada o si el sitio recién fue instalado.</p>
                        </td>
                    </tr>
                </table>

                <?php if ( empty( $companies ) ) : ?>
                    <div class="notice notice-warning" style="margin-top:20px;">
                        <p>No hay empresas en estructuras. Crea empresas primero en <strong>Estructuras</strong> para configurar Términos y Correos por empresa.</p>
                    </div>
                <?php else : ?>
                    <h2 class="fplms-terms-title">Configuración por empresa</h2>
                    <p class="fplms-contract-note">Selecciona una empresa para editar su contrato y la personalización del correo de bienvenida.</p>
                    <div class="fplms-rpt-tabs-nav">
                        <?php foreach ( $companies as $index => $company ) : ?>
                            <button type="button"
                                    class="fplms-rpt-tab-btn fplms-company-tab <?php echo 0 === $index ? 'active' : ''; ?>"
                                    data-target="fplms-company-panel-<?php echo esc_attr( $company->term_id ); ?>">
                                <?php echo esc_html( $company->name ); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <?php foreach ( $companies as $index => $company ) : ?>
                        <?php
                        $company_id = (int) $company->term_id;
                        $company_terms = $this->get_company_terms_content( $company_id, $company->slug );
                        $company_email_from = $this->get_company_email_from( $company_id, $company->slug );
                        $company_email_name = $this->get_company_email_name( $company_id, $company->name );
                        $company_email_subject = $this->get_company_email_subject( $company_id );
                        $company_email_bg = $this->get_company_email_bg( $company_id );
                        $company_email_accent = $this->get_company_email_accent( $company_id, $company->slug );
                        $company_email_logo = $this->get_company_email_logo( $company_id );
                        ?>
                        <div id="fplms-company-panel-<?php echo esc_attr( $company_id ); ?>"
                                class="fplms-company-panel <?php echo 0 === $index ? 'is-visible' : ''; ?>"
                                data-company-id="<?php echo esc_attr( $company_id ); ?>">
                            <h3 style="margin-top:0;">Contrato de Servicio – <?php echo esc_html( $company->name ); ?></h3>
                            <p class="description">Contrato que verá el usuario al activar su cuenta para esta empresa.</p>
                            <?php
                            wp_editor( $company_terms, 'fplms_terms_company_' . $company_id, [
                                'textarea_name' => 'fplms_terms_company[' . $company_id . ']',
                                'textarea_rows' => 24,
                                'editor_height' => 520,
                                'media_buttons' => false,
                                'teeny'         => false,
                            ] );
                            ?>

                            <div class="fplms-email-layout-row">
                                <div class="fplms-email-fields">
                                    <table class="form-table" role="presentation">
                                        <tr>
                                            <th scope="row"><label for="fplms_email_from_company_<?php echo esc_attr( $company_id ); ?>">Email remitente</label></th>
                                            <td>
                                                <input type="email"
                                                       id="fplms_email_from_company_<?php echo esc_attr( $company_id ); ?>"
                                                       name="fplms_email_from_company[<?php echo esc_attr( $company_id ); ?>]"
                                                       value="<?php echo esc_attr( $company_email_from ); ?>"
                                                       class="regular-text"
                                                       placeholder="noreply@empresa.com">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="fplms_email_name_company_<?php echo esc_attr( $company_id ); ?>">Nombre remitente</label></th>
                                            <td>
                                                <input type="text"
                                                       id="fplms_email_name_company_<?php echo esc_attr( $company_id ); ?>"
                                                       name="fplms_email_name_company[<?php echo esc_attr( $company_id ); ?>]"
                                                       value="<?php echo esc_attr( $company_email_name ); ?>"
                                                       class="regular-text"
                                                       placeholder="Empresa e-Learning">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="fplms_email_subject_company_<?php echo esc_attr( $company_id ); ?>">Asunto del email</label></th>
                                            <td>
                                                <input type="text"
                                                       id="fplms_email_subject_company_<?php echo esc_attr( $company_id ); ?>"
                                                       name="fplms_email_subject_company[<?php echo esc_attr( $company_id ); ?>]"
                                                       value="<?php echo esc_attr( $company_email_subject ); ?>"
                                                       class="regular-text"
                                                       placeholder="Bienvenido/a - Activa tu cuenta">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="fplms_email_accent_company_<?php echo esc_attr( $company_id ); ?>">Color principal</label></th>
                                            <td>
                                                <input type="color"
                                                       id="fplms_email_accent_company_<?php echo esc_attr( $company_id ); ?>"
                                                       name="fplms_email_accent_company[<?php echo esc_attr( $company_id ); ?>]"
                                                       value="<?php echo esc_attr( $company_email_accent ?: '#f6b23a' ); ?>">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="fplms_email_logo_company_<?php echo esc_attr( $company_id ); ?>">Logo (URL)</label></th>
                                            <td>
                                                <input type="url"
                                                       id="fplms_email_logo_company_<?php echo esc_attr( $company_id ); ?>"
                                                       name="fplms_email_logo_company[<?php echo esc_attr( $company_id ); ?>]"
                                                       value="<?php echo esc_attr( $company_email_logo ); ?>"
                                                       class="regular-text"
                                                       placeholder="https://.../logo.png">
                                                <button type="button"
                                                        class="button fplms-media-pick"
                                                        data-target="fplms_email_logo_company_<?php echo esc_attr( $company_id ); ?>"
                                                        style="margin-left:8px;">Seleccionar imagen</button>
                                                <p class="fplms-media-help">Dimensiones recomendadas logo: 320 x 90 px (formato PNG/SVG, fondo transparente).</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="fplms_email_bg_company_<?php echo esc_attr( $company_id ); ?>">Imagen de fondo del encabezado</label></th>
                                            <td>
                                                <input type="url"
                                                       id="fplms_email_bg_company_<?php echo esc_attr( $company_id ); ?>"
                                                       name="fplms_email_bg_company[<?php echo esc_attr( $company_id ); ?>]"
                                                       value="<?php echo esc_attr( $company_email_bg ); ?>"
                                                       class="regular-text"
                                                       placeholder="https://.../header.jpg">
                                                <button type="button"
                                                        class="button fplms-media-pick"
                                                        data-target="fplms_email_bg_company_<?php echo esc_attr( $company_id ); ?>"
                                                        style="margin-left:8px;">Seleccionar imagen</button>
                                                <p class="fplms-media-help">Dimensiones recomendadas encabezado: 1200 x 320 px (JPG/PNG, peso menor a 500 KB).</p>
                                                <?php if ( ! empty( $company_email_bg ) ) : ?>
                                                    <div class="fplms-image-preview"><img src="<?php echo esc_url( $company_email_bg ); ?>" alt="preview"></div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>

                                <div class="fplms-email-preview-wrap" data-preview-wrap="<?php echo esc_attr( $company_id ); ?>">
                                    <div class="fplms-email-preview-head">
                                        <strong>Vista previa rápida (sin guardar)</strong>
                                        <span>Solo refleja remitente, asunto, color principal, logo y fondo de encabezado.</span>
                                    </div>
                                    <div class="fplms-email-preview-canvas">
                                        <div class="fplms-email-preview-card">
                                            <div class="fplms-email-preview-header" data-preview-header>
                                                <img src="" alt="logo" class="fplms-email-preview-logo" data-preview-logo>
                                                <h4 class="fplms-email-preview-title" data-preview-title><?php echo esc_html( $company_email_name ?: $company->name ); ?></h4>
                                            </div>
                                            <div class="fplms-email-preview-body">
                                                <p class="fplms-email-preview-subject" data-preview-subject><?php echo esc_html( $company_email_subject ?: 'Bienvenido/a - Activa tu cuenta' ); ?></p>
                                                <p>Hola, Nombre Usuario:</p>
                                                <p>Tu cuenta fue creada exitosamente. Para activarla debes aceptar el contrato y crear contraseña.</p>
                                                <a href="#" onclick="return false;" class="fplms-email-preview-btn" data-preview-button>Activar mi cuenta</a>
                                            </div>
                                            <div class="fplms-email-preview-footer">
                                                © <?php echo esc_html( get_bloginfo( 'name' ) ); ?> - <a href="<?php echo esc_url( home_url() ); ?>"><?php echo esc_html( home_url() ); ?></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <p style="margin-top:24px;">
                    <?php submit_button( 'Guardar configuración', 'primary', 'submit', false ); ?>
                </p>
            </form>

            <script>
            (function() {
                var tabs = document.querySelectorAll('.fplms-company-tab');
                var panels = document.querySelectorAll('.fplms-company-panel');

                function byId(id) {
                    return document.getElementById(id);
                }

                function safeValue(id, fallback) {
                    var el = byId(id);
                    if (!el) {
                        return fallback || '';
                    }
                    return (el.value || '').trim() || (fallback || '');
                }

                function updateCompanyPreview(companyId) {
                    try {
                        var panel = byId('fplms-company-panel-' + companyId);
                        if (!panel) {
                            return;
                        }

                        var name = safeValue('fplms_email_name_company_' + companyId, 'Empresa e-Learning');
                        var subject = safeValue('fplms_email_subject_company_' + companyId, 'Bienvenido/a - Activa tu cuenta');
                        var accent = safeValue('fplms_email_accent_company_' + companyId, '#f6b23a');
                        var bg = safeValue('fplms_email_bg_company_' + companyId, '');
                        var logo = safeValue('fplms_email_logo_company_' + companyId, '');

                        var header = panel.querySelector('[data-preview-header]');
                        var title = panel.querySelector('[data-preview-title]');
                        var subjectEl = panel.querySelector('[data-preview-subject]');
                        var btn = panel.querySelector('[data-preview-button]');
                        var logoEl = panel.querySelector('[data-preview-logo]');

                        if (title) {
                            title.textContent = name;
                        }
                        if (subjectEl) {
                            subjectEl.textContent = subject;
                        }
                        if (btn) {
                            btn.style.background = accent;
                        }
                        if (header) {
                            if (bg) {
                                header.style.backgroundImage = "url('" + bg.replace(/'/g, "%27") + "')";
                                header.style.backgroundSize = 'cover';
                                header.style.backgroundPosition = 'center';
                                header.style.backgroundColor = '';
                            } else {
                                header.style.backgroundImage = 'none';
                                header.style.backgroundColor = accent;
                            }
                        }
                        if (logoEl) {
                            if (logo) {
                                logoEl.src = logo;
                                logoEl.style.display = 'block';
                            } else {
                                logoEl.removeAttribute('src');
                                logoEl.style.display = 'none';
                            }
                        }
                    } catch (e) {
                        // Mantener el panel funcional incluso si falla la vista previa.
                    }
                }

                function bindPreview(companyId) {
                    var ids = [
                        'fplms_email_name_company_' + companyId,
                        'fplms_email_subject_company_' + companyId,
                        'fplms_email_accent_company_' + companyId,
                        'fplms_email_bg_company_' + companyId,
                        'fplms_email_logo_company_' + companyId
                    ];

                    ids.forEach(function(id) {
                        var el = byId(id);
                        if (!el) {
                            return;
                        }
                        el.addEventListener('input', function() {
                            updateCompanyPreview(companyId);
                        });
                        el.addEventListener('change', function() {
                            updateCompanyPreview(companyId);
                        });
                    });

                    updateCompanyPreview(companyId);
                }

                tabs.forEach(function(tab) {
                    tab.addEventListener('click', function() {
                        var target = this.getAttribute('data-target');
                        tabs.forEach(function(btn) { btn.classList.remove('active'); });
                        this.classList.add('active');
                        panels.forEach(function(panel) {
                            panel.classList.toggle('is-visible', panel.id === target);
                        });
                    });
                });

                panels.forEach(function(panel) {
                    var companyId = panel.getAttribute('data-company-id');
                    if (companyId) {
                        bindPreview(companyId);
                    }
                });

                var mediaButtons = document.querySelectorAll('.fplms-media-pick');
                mediaButtons.forEach(function(button) {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        var inputId = this.getAttribute('data-target');
                        var input = document.getElementById(inputId);
                        if (!input || !window.wp || !wp.media) {
                            return;
                        }
                        var frame = wp.media({
                            title: 'Seleccionar imagen',
                            multiple: false,
                            library: { type: 'image' },
                            button: { text: 'Usar imagen' }
                        });
                        frame.on('select', function() {
                            var attachment = frame.state().get('selection').first().toJSON();
                            input.value = attachment.url || '';
                            var panel = input.closest('.fplms-company-panel');
                            if (panel) {
                                var companyId = panel.getAttribute('data-company-id');
                                if (companyId) {
                                    updateCompanyPreview(companyId);
                                }
                            }
                        });
                        frame.open();
                    });
                });
            })();
            </script>

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
        $company_id = $this->detect_user_company( $user_id );

        // Generar reset key (expira ~24h según WordPress)
        $reset_key = get_password_reset_key( $user );
        if ( is_wp_error( $reset_key ) ) {
            return;
        }

        $this->dispatch_welcome_email( $user, $reset_key, $company_id );
    }

    /**
     * Detecta la empresa del usuario y devuelve el term_id de fplms_company.
     *
     * Compatibilidad:
     * - Si USER_META_TERMS_COMPANY contiene un slug legacy (eliora/fairplay),
     *   intentamos resolverlo a term_id.
     * - Si no hay empresa en el usuario, usamos la empresa por defecto configurada.
     */
    private function detect_user_company( int $user_id ): int {
        $company_term_id = (int) get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_COMPANY, true );
        if ( $company_term_id > 0 ) {
            $term = get_term( $company_term_id, FairPlay_LMS_Config::TAX_COMPANY );
            if ( $term && ! is_wp_error( $term ) ) {
                return (int) $term->term_id;
            }
        }

        $legacy_company = get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_TERMS_COMPANY, true );
        if ( is_string( $legacy_company ) && '' !== $legacy_company ) {
            $legacy_company = sanitize_title( $legacy_company );
            $legacy_term = get_term_by( 'slug', $legacy_company, FairPlay_LMS_Config::TAX_COMPANY );
            if ( $legacy_term && ! is_wp_error( $legacy_term ) ) {
                return (int) $legacy_term->term_id;
            }
        }

        return $this->get_default_company_id();
    }

    /**
     * Construye y envía el email de bienvenida.
     *
     * @param WP_User $user
     * @param string  $reset_key
     * @param int     $company_id term_id de fplms_company
     */
    private function dispatch_welcome_email( WP_User $user, string $reset_key, int $company_id ): void {
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

        $company_term = $company_id > 0 ? get_term( $company_id, FairPlay_LMS_Config::TAX_COMPANY ) : null;
        $company_slug = ( $company_term && ! is_wp_error( $company_term ) ) ? (string) $company_term->slug : '';
        $company_name = ( $company_term && ! is_wp_error( $company_term ) ) ? (string) $company_term->name : 'FairPlay';

        $from_email = $this->get_company_email_from( $company_id, $company_slug );
        if ( empty( $from_email ) ) {
            $from_email = (string) get_option( 'admin_email' );
        }

        $from_name = $this->get_company_email_name( $company_id, $company_name . ' e-Learning' );

        $first_name = $user->first_name ?: $user->user_login;
        $subject = $this->get_company_email_subject( $company_id );
        if ( empty( $subject ) ) {
            $subject = "Bienvenido/a a {$site_name} - Activa tu cuenta";
        }

        $body = $this->build_email_html(
            $first_name,
            $site_name,
            $blog_url,
            $onboarding_url,
            [
                'from_name' => $from_name,
                'accent'    => $this->get_company_email_accent( $company_id, $company_slug ),
                'bg_image'  => $this->get_company_email_bg( $company_id ),
                'logo'      => $this->get_company_email_logo( $company_id ),
            ]
        );

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
        string $site_name,
        string $blog_url,
        string $onboarding_url,
        array $company_email_config
    ): string {
        $accent = isset( $company_email_config['accent'] ) ? (string) $company_email_config['accent'] : '#f6b23a';
        $from_name = isset( $company_email_config['from_name'] ) ? (string) $company_email_config['from_name'] : $site_name;
        $bg_image = isset( $company_email_config['bg_image'] ) ? esc_url( (string) $company_email_config['bg_image'] ) : '';
        $logo = isset( $company_email_config['logo'] ) ? esc_url( (string) $company_email_config['logo'] ) : '';
        $header_style = "padding:32px 40px;text-align:center;";
        if ( ! empty( $bg_image ) ) {
            $header_style .= "background-image:url('{$bg_image}');background-size:cover;background-position:center;";
        } else {
            $header_style .= "background:{$accent};";
        }

        $logo_html = '';
        if ( ! empty( $logo ) ) {
            $logo_html = "<div style=\"margin-bottom:10px;\"><img src=\"{$logo}\" alt=\"Logo\" style=\"max-width:180px;height:auto;border:0;\"></div>";
        }

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
                    <td style="{$header_style}">
                        {$logo_html}
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

        $company_id = $this->detect_user_company( $user_id );
        $this->dispatch_welcome_email( $user, $reset_key, $company_id );

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
        $company_id = $this->detect_user_company( $user->ID );
        $company_term = $company_id > 0 ? get_term( $company_id, FairPlay_LMS_Config::TAX_COMPANY ) : null;
        $company_slug = ( $company_term && ! is_wp_error( $company_term ) ) ? (string) $company_term->slug : '';
        $company_label = ( $company_term && ! is_wp_error( $company_term ) ) ? (string) $company_term->name : 'Empresa';
        $terms_content = $this->get_company_terms_content( $company_id, $company_slug );
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
.fplms-ob-step.active { color: #f6b23a; font-weight: 600; }
.fplms-ob-step.done { color: #38a169; font-weight: 600; }
.fplms-ob-step-num { display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: #e5e7eb; font-size: 13px; font-weight: 700; }
.fplms-ob-step.active .fplms-ob-step-num { background: #f6b23a; color: #fff; }
.fplms-ob-step.done .fplms-ob-step-num { background: #38a169; color: #fff; }
.fplms-ob-step-divider { flex: 1; height: 2px; background: #e5e7eb; min-width: 24px; }
.fplms-ob-step-divider.active { background: #f6b23a; }
.fplms-ob-body { padding: 32px 40px; }
.fplms-ob-title { font-size: 22px; font-weight: 700; color: #111827; margin: 0 0 8px; }
.fplms-ob-subtitle { font-size: 15px; color: #6b7280; margin: 0 0 24px; }
.fplms-ob-error { background: #fff5f5; border-left: 4px solid #e53e3e; color: #c53030; padding: 12px 16px; border-radius: 4px; font-size: 14px; margin-bottom: 20px; }
.fplms-ob-terms-box { max-height: 360px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px 24px; background: #fafafa; font-size: 14px; line-height: 1.7; color: #374151; margin-bottom: 24px; }
.fplms-ob-check-label { display: flex; align-items: flex-start; gap: 10px; font-size: 14px; color: #374151; cursor: pointer; margin-bottom: 20px; }
.fplms-ob-check-label input[type="checkbox"] { margin-top: 2px; width: 18px; height: 18px; accent-color: #f6b23a; flex-shrink: 0; }
.fplms-ob-actions { display: flex; gap: 12px; }
.fplms-ob-btn { padding: 12px 28px; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: opacity .2s; }
.fplms-ob-btn-primary { background: #f6b23a; color: #fff; }
.fplms-ob-btn-primary:hover { opacity: .88; }
.fplms-ob-btn-primary:disabled { background: #9ca3af; cursor: not-allowed; }
.fplms-ob-reject-form { margin-top: 16px; }
.fplms-ob-reject-hint { font-size: 13px; color: #9ca3af; }
.fplms-ob-btn-link { background: none; border: none; padding: 0; font-size: 13px; color: #e53e3e; cursor: pointer; text-decoration: underline; }
button.fplms-ob-btn-link:hover,.fplms-ob-btn-link:hover {background-color: transparent !important;}
.fplms-ob-field { margin-bottom: 20px; }
.fplms-ob-field label { display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 6px; }
.fplms-ob-input { width: 100%; padding: 10px 44px 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px; box-sizing: border-box; outline: none; }
.fplms-ob-input:focus { border-color: #f6b23a; box-shadow: 0 0 0 3px rgba(246,178,58,.12); }
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

    private function get_terms_option_key( int $company_id ): string {
        return FairPlay_LMS_Config::OPTION_TERMS_BY_COMPANY_PREFIX . $company_id;
    }

    private function get_company_email_from_option_key( int $company_id ): string {
        return FairPlay_LMS_Config::OPTION_EMAIL_FROM_BY_COMPANY_PREFIX . $company_id;
    }

    private function get_company_email_name_option_key( int $company_id ): string {
        return FairPlay_LMS_Config::OPTION_EMAIL_NAME_BY_COMPANY_PREFIX . $company_id;
    }

    private function get_company_email_subject_option_key( int $company_id ): string {
        return FairPlay_LMS_Config::OPTION_EMAIL_SUBJECT_BY_COMPANY_PREFIX . $company_id;
    }

    private function get_company_email_bg_option_key( int $company_id ): string {
        return FairPlay_LMS_Config::OPTION_EMAIL_BG_IMAGE_BY_COMPANY_PREFIX . $company_id;
    }

    private function get_company_email_accent_option_key( int $company_id ): string {
        return FairPlay_LMS_Config::OPTION_EMAIL_ACCENT_BY_COMPANY_PREFIX . $company_id;
    }

    private function get_company_email_logo_option_key( int $company_id ): string {
        return FairPlay_LMS_Config::OPTION_EMAIL_LOGO_BY_COMPANY_PREFIX . $company_id;
    }

    private function get_company_terms_content( int $company_id, string $company_slug = '' ): string {
        if ( $company_id > 0 ) {
            $dynamic = (string) get_option( $this->get_terms_option_key( $company_id ), '' );
            if ( '' !== $dynamic ) {
                return $dynamic;
            }
        }

        $legacy_key = $this->get_legacy_terms_option_key_by_slug( $company_slug );
        if ( $legacy_key ) {
            return (string) get_option( $legacy_key, '' );
        }

        return '';
    }

    private function get_company_email_from( int $company_id, string $company_slug = '' ): string {
        if ( $company_id > 0 ) {
            $dynamic = (string) get_option( $this->get_company_email_from_option_key( $company_id ), '' );
            if ( '' !== $dynamic ) {
                return $dynamic;
            }
        }

        $legacy_key = $this->get_legacy_email_option_key_by_slug( $company_slug );
        if ( $legacy_key ) {
            return (string) get_option( $legacy_key, '' );
        }

        return '';
    }

    private function get_company_email_name( int $company_id, string $fallback ): string {
        if ( $company_id > 0 ) {
            $dynamic = (string) get_option( $this->get_company_email_name_option_key( $company_id ), '' );
            if ( '' !== $dynamic ) {
                return $dynamic;
            }
        }
        return $fallback;
    }

    private function get_company_email_subject( int $company_id ): string {
        if ( $company_id > 0 ) {
            return (string) get_option( $this->get_company_email_subject_option_key( $company_id ), '' );
        }
        return '';
    }

    private function get_company_email_bg( int $company_id ): string {
        if ( $company_id > 0 ) {
            return (string) get_option( $this->get_company_email_bg_option_key( $company_id ), '' );
        }
        return '';
    }

    private function get_company_email_accent( int $company_id, string $company_slug = '' ): string {
        if ( $company_id > 0 ) {
            $dynamic = (string) get_option( $this->get_company_email_accent_option_key( $company_id ), '' );
            if ( '' !== $dynamic ) {
                return $dynamic;
            }
        }

        $normalized = strtolower( $company_slug );
        if ( false !== strpos( $normalized, 'fairplay' ) ) {
            return '#e3342f';
        }

        return '#f6b23a';
    }

    private function get_company_email_logo( int $company_id ): string {
        if ( $company_id > 0 ) {
            return (string) get_option( $this->get_company_email_logo_option_key( $company_id ), '' );
        }
        return '';
    }

    private function get_legacy_terms_option_key_by_slug( string $company_slug ): string {
        $normalized = strtolower( $company_slug );
        if ( false !== strpos( $normalized, 'eliora' ) ) {
            return FairPlay_LMS_Config::OPTION_TERMS_ELIORA;
        }
        if ( false !== strpos( $normalized, 'fairplay' ) ) {
            return FairPlay_LMS_Config::OPTION_TERMS_FAIRPLAY;
        }
        return '';
    }

    private function get_legacy_email_option_key_by_slug( string $company_slug ): string {
        $normalized = strtolower( $company_slug );
        if ( false !== strpos( $normalized, 'eliora' ) ) {
            return FairPlay_LMS_Config::OPTION_EMAIL_ELIORA;
        }
        if ( false !== strpos( $normalized, 'fairplay' ) ) {
            return FairPlay_LMS_Config::OPTION_EMAIL_FAIRPLAY;
        }
        return '';
    }

    private function get_default_company_id(): int {
        $configured_default = (int) get_option( FairPlay_LMS_Config::OPTION_DEFAULT_COMPANY_ID, 0 );
        if ( $configured_default > 0 ) {
            $term = get_term( $configured_default, FairPlay_LMS_Config::TAX_COMPANY );
            if ( $term && ! is_wp_error( $term ) ) {
                return (int) $term->term_id;
            }
        }

        $first_company = get_terms(
            [
                'taxonomy'   => FairPlay_LMS_Config::TAX_COMPANY,
                'hide_empty' => false,
                'number'     => 1,
                'orderby'    => 'term_id',
                'order'      => 'ASC',
            ]
        );

        if ( ! is_wp_error( $first_company ) && ! empty( $first_company ) ) {
            return (int) $first_company[0]->term_id;
        }

        return 0;
    }

    private function get_client_ip(): string {
        foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ] as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                return sanitize_text_field( strtok( $_SERVER[ $key ], ',' ) );
            }
        }
        return 'unknown';
    }
}
