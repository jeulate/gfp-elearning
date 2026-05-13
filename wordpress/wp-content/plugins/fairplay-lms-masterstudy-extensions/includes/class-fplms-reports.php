<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FairPlay_LMS_Reports_Controller {

    /** @var FairPlay_LMS_Users_Controller */
    private $users;

    /** @var FairPlay_LMS_Structures_Controller */
    private $structures;

    /** @var FairPlay_LMS_Progress_Service */
    private $progress;

    public function __construct(
        FairPlay_LMS_Users_Controller $users,
        FairPlay_LMS_Structures_Controller $structures,
        FairPlay_LMS_Progress_Service $progress
    ) {
        $this->users      = $users;
        $this->structures = $structures;
        $this->progress   = $progress;
    }

    // AJAX REGISTRATION
    public function register_ajax_hooks(): void {
        $map = [
            'fplms_report_participation'  => 'ajax_report_participation',
            'fplms_report_progress'       => 'ajax_report_progress',
            'fplms_report_performance'    => 'ajax_report_performance',
            'fplms_report_certificates'   => 'ajax_report_certificates',
            'fplms_report_time'           => 'ajax_report_time',
            'fplms_report_satisfaction'   => 'ajax_report_satisfaction',
            'fplms_report_channels'       => 'ajax_report_channels',
            'fplms_reports_user_search'   => 'ajax_reports_user_search',
            'fplms_report_tests'          => 'ajax_report_tests',
            'fplms_report_test_detail'    => 'ajax_report_test_detail',
            'fplms_report_test_reset'     => 'ajax_report_test_reset',
            'fplms_report_test_answers'   => 'ajax_report_test_answers',
            'fplms_report_matrix'         => 'ajax_report_matrix',
        ];
        foreach ( $map as $action => $method ) {
            add_action( 'wp_ajax_' . $action, [ $this, $method ] );
        }
    }

    // SECURITY CHECK
    private function verify_request(): bool {
        if ( ! check_ajax_referer( 'fplms_reports_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Nonce invalido.' ], 403 );
            return false;
        }
        if ( ! current_user_can( FairPlay_LMS_Config::CAP_VIEW_REPORTS ) ) {
            wp_send_json_error( [ 'message' => 'Sin permisos.' ], 403 );
            return false;
        }
        return true;
    }

    // FILTER HELPERS
    private function get_filters(): array {
        return [
            'date_from'  => isset( $_POST['date_from'] )  ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) )  : '',
            'date_to'    => isset( $_POST['date_to'] )    ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) )    : '',
            'channel_id' => isset( $_POST['channel_id'] ) ? (int) $_POST['channel_id'] : 0,
            'city_id'    => isset( $_POST['city_id'] )    ? (int) $_POST['city_id']    : 0,
            'company_id' => isset( $_POST['company_id'] ) ? (int) $_POST['company_id'] : 0,
            'role_id'    => isset( $_POST['role_id'] )    ? (int) $_POST['role_id']    : 0,
            'user_ids'   => isset( $_POST['user_ids'] ) && '' !== $_POST['user_ids']
                ? array_filter( array_map( 'intval', explode( ',', wp_unslash( $_POST['user_ids'] ) ) ) )
                : [],
        ];
    }

    private function get_user_ids_for_filters( array $f ): ?array {
        global $wpdb;
        if ( ! empty( $f['user_ids'] ) ) return array_values( $f['user_ids'] );
        $joins = [];
        if ( $f['city_id'] )    $joins[] = $wpdb->prepare( "INNER JOIN {$wpdb->usermeta} um_ci ON u.ID=um_ci.user_id AND um_ci.meta_key='fplms_city'    AND um_ci.meta_value=%s", (string)$f['city_id'] );
        if ( $f['company_id'] ) $joins[] = $wpdb->prepare( "INNER JOIN {$wpdb->usermeta} um_co ON u.ID=um_co.user_id AND um_co.meta_key='fplms_company' AND um_co.meta_value=%s", (string)$f['company_id'] );
        if ( $f['channel_id'] ) $joins[] = $wpdb->prepare( "INNER JOIN {$wpdb->usermeta} um_ch ON u.ID=um_ch.user_id AND um_ch.meta_key='fplms_channel' AND um_ch.meta_value=%s", (string)$f['channel_id'] );
        if ( $f['role_id'] )    $joins[] = $wpdb->prepare( "INNER JOIN {$wpdb->usermeta} um_rl ON u.ID=um_rl.user_id AND um_rl.meta_key='fplms_job_role' AND um_rl.meta_value=%s", (string)$f['role_id'] );
        if ( empty($joins) ) return null;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return array_map( 'intval', (array) $wpdb->get_col( "SELECT DISTINCT u.ID FROM {$wpdb->users} u " . implode(' ', $joins) ) );
    }

    private function uid_in( ?array $uids, string $col = 'u.ID' ): string {
        if ( null === $uids ) return '';
        if ( empty($uids) ) return ' AND 1=0';
        return ' AND ' . $col . ' IN (' . implode(',', array_map('intval',$uids)) . ')';
    }

    private function date_sql( string $col, string $from, string $to ): string {
        global $wpdb;
        $sql = '';
        if ($from) $sql .= $wpdb->prepare(" AND $col >= %s", $from.' 00:00:00');
        if ($to)   $sql .= $wpdb->prepare(" AND $col <= %s", $to  .' 23:59:59');
        return $sql;
    }

    private function ms_table(): ?string {
        global $wpdb;
        static $cache = false;
        if (false !== $cache) return $cache ?: null;
        foreach ([$wpdb->prefix.'stm_lms_user_courses', $wpdb->prefix.'stm_lms_users'] as $t) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$t)) === $t) { $cache=$t; return $t; }
        }
        $cache=''; return null;
    }

    private function render_section_head(string $title, string $url): string {
        $ico = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
        $btn = $url ? '<a href="'.esc_url($url).'" class="button fplms-rpt-export-btn" target="_blank">'.$ico.'Exportar Excel</a>' : '';
        return '<div class="fplms-rpt-section-head"><h2 class="fplms-rpt-title">'.esc_html($title).'</h2>'.$btn.'</div>';
    }
    private function sub(string $t): string   { return '<h3 class="fplms-rpt-sub">'.esc_html($t).'</h3>'; }
    private function empty_msg(string $t): string { return '<p class="fplms-rpt-empty">'.esc_html($t).'</p>'; }
    private function metric(string $h): string  { return '<p class="fplms-rpt-metric">'.$h.'</p>'; }
    /**
     * Renders a row of stat cards. Each card: ['label'=>string, 'value'=>string, 'sub'=>string (opt), 'color'=>'green|yellow|red|blue|orange' (opt)]
     */
    private function stat_cards(array $cards): string {
        $out = '<div class="fplms-stat-cards">';
        foreach ($cards as $c) {
            $color = $c['color'] ?? 'blue';
            $sub   = isset($c['sub']) && $c['sub'] !== '' ? '<span class="fplms-sc-sub">'.esc_html($c['sub']).'</span>' : '';
            $out  .= '<div class="fplms-sc fplms-sc--'.$color.'">'
                   . '<p class="fplms-sc-label">'.esc_html($c['label']).'</p>'
                   . '<p class="fplms-sc-value">'.$c['value'].'</p>'
                   . $sub
                   . '</div>';
        }
        $out .= '</div>';
        return $out;
    }
    private function badge(string $text, string $color): string {
        return '<span class="fplms-badge '.esc_attr($color).'">'.esc_html($text).'</span>';
    }
    private function translate_status(string $status): string {
        $map = [
            ''             => 'No iniciado',
            'not_started'  => 'No iniciado',
            'enrolled'     => 'Inscrito',
            'in_progress'  => 'En progreso',
            'completed'    => 'Completado',
            'passed'       => 'Aprobado',
            'failed'       => 'Reprobado',
            'not_passed'   => 'Reprobado',
            'pending'      => 'Pendiente',
        ];
        return $map[$status] ?? $status;
    }
    private function table_open(array $heads): string {
        $ths = implode('', array_map(fn($h)=>'<th>'.esc_html($h).'</th>', $heads));
        return '<div class="fplms-rpt-table-wrap"><table class="widefat striped fplms-rpt-table"><thead><tr>'.$ths.'</tr></thead><tbody>';
    }
    private function table_close(): string { return '</tbody></table></div>'; }

    /** Variante paginada: envuelve la tabla con controles de paginación cliente. */
    private function paged_table_open(array $heads): string {
        $ths = implode('', array_map(fn($h) => '<th>' . esc_html($h) . '</th>', $heads));
        return '<div class="fplms-paged-wrap">'
             . '<div class="fplms-rpt-table-wrap"><table class="widefat striped fplms-rpt-table">'
             . '<thead><tr>' . $ths . '</tr></thead><tbody>';
    }
    private function paged_table_close(): string {
        return '</tbody></table></div>'
             . '<div class="fplms-paged-controls">'
             . '<label style="font-size:12px;color:#555">Mostrar: <select class="fplms-paged-size">'
             . '<option value="10" selected>10</option>'
             . '<option value="20">20</option>'
             . '<option value="50">50</option>'
             . '</select></label>'
             . '<span class="fplms-paged-info"></span>'
             . '<button type="button" class="button button-small fplms-paged-prev">&lsaquo; Anterior</button>'
             . '<button type="button" class="button button-small fplms-paged-next">Siguiente &rsaquo;</button>'
             . '</div></div>';
    }

    /** Parse varied duration strings to minutes: "1:30", "2h 45m", "30 min", "2 horas", "1" */
    private function parse_duration_minutes(string $raw): float {
        $raw = trim($raw);
        if ($raw === '' || strtoupper($raw) === 'NULL') return 0.0;
        // H:MM or HH:MM or HH:MM:SS
        if (preg_match('/^(\d+):(\d{1,2})(?::\d+)?$/', $raw, $m))
            return (float)$m[1] * 60 + (float)$m[2];
        // Nh Nm  e.g. "2h 45m", "1h", "1 hora"
        if (preg_match('/(\d+(?:\.\d+)?)\s*h(?:ora[s]?)?\b/i', $raw, $mh)) {
            preg_match('/(\d+)\s*m(?:in)?/i', $raw, $mm);
            return (float)$mh[1] * 60 + (isset($mm[1]) ? (float)$mm[1] : 0);
        }
        // N min / N minutos
        if (preg_match('/^(\d+(?:\.\d+)?)\s*m(?:in(?:uto[s]?)?)?$/i', $raw, $m))
            return (float)$m[1];
        // Plain integer/decimal → treat as hours (lesson UI default)
        if (preg_match('/^(\d+(?:\.\d+)?)$/', $raw, $m))
            return (float)$m[1] * 60;
        return 0.0;
    }

    /** Format minutes to human-readable: "2h 30m", "45 min", "0 min" */
    private function fmt_minutes(float $minutes): string {
        if ($minutes <= 0) return '0 min';
        $h = (int)floor($minutes / 60);
        $m = (int)round(fmod($minutes, 60));
        if ($h > 0 && $m > 0) return $h . 'h ' . $m . 'm';
        if ($h > 0) return $h . ' h';
        return $m . ' min';
    }

    /**
     * Aggregates raw quiz attempt rows (one per attempt) into one summary row
     * per (user + quiz + course).
     * - Approved: score and attempt number of the FIRST passing attempt
     * - Failed:   score and total attempts of the last attempt
     */
    private function aggregate_quiz_rows(array $raw): array {
        $grouped = [];
        $pass_set = ['passed', 'completed'];
        foreach ($raw as $r) {
            $key = $r->user_id . '_' . $r->quiz_id . '_' . $r->course_id;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'display_name' => $r->display_name,
                    'user_email'   => $r->user_email,
                    'course_name'  => $r->course_name,
                    'quiz_name'    => $r->quiz_name,
                    'attempts'     => [],
                ];
            }
            $grouped[$key]['attempts'][] = ['score' => $r->score, 'status' => $r->status];
        }
        $agg = [];
        foreach ($grouped as $data) {
            $atts  = $data['attempts'];
            $total = count($atts);
            $last  = $atts[$total - 1];
            $pass_score   = null;
            $pass_attempt = null;
            foreach ($atts as $i => $a) {
                if (in_array(strtolower($a['status'] ?? ''), $pass_set, true)) {
                    $pass_score   = $a['score'];
                    $pass_attempt = $i + 1;
                    break;
                }
            }
            $agg[] = (object) [
                'display_name'   => $data['display_name'],
                'user_email'     => $data['user_email'],
                'course_name'    => $data['course_name'],
                'quiz_name'      => $data['quiz_name'],
                'total_attempts' => $total,
                'score'          => $pass_score ?? $last['score'],
                'attempts_label' => $pass_attempt ?? $total,
                'status'         => $pass_attempt !== null
                                    ? ($atts[$pass_attempt - 1]['status'] ?? 'passed')
                                    : ($last['status'] ?? 'failed'),
            ];
        }
        return $agg;
    }

    private function build_export_url(string $report, array $f): string {
        $uid_str = !empty($f['user_ids']) ? implode(',', array_map('intval', (array)$f['user_ids'])) : '';
        return add_query_arg( array_filter([
            'page'         => 'fplms-reports',
            'fplms_export' => $report.'_xls',
            'date_from'    => $f['date_from']  ?? '',
            'date_to'      => $f['date_to']    ?? '',
            'channel_id'   => $f['channel_id'] ?: '',
            'city_id'      => $f['city_id']    ?: '',
            'company_id'   => $f['company_id'] ?: '',
            'role_id'      => $f['role_id']    ?: '',
            'user_ids'     => $uid_str,
            '_wpnonce'     => wp_create_nonce('fplms_export_nonce'),
        ]), admin_url('admin.php') );
    }

    // REPORT 1 — PARTICIPACION
    public function ajax_report_participation(): void {
        if (!$this->verify_request()) return;
        global $wpdb;
        $f    = $this->get_filters();
        $uids = $this->get_user_ids_for_filters($f);
        $html = $this->render_section_head('Reporte de Participacion', $this->build_export_url('participation',$f));

        // 1A. Usuarios registrados
        // Cuando se filtra por usuario específico no aplicamos el filtro de fecha sobre la fecha de registro
        // (el usuario debe aparecer siempre que coincida con el ID buscado, independientemente de cuándo se registró)
        $date_w = empty($f['user_ids']) ? $this->date_sql('u.user_registered', $f['date_from'], $f['date_to']) : '';
        $uid_w  = $this->uid_in($uids);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $created = (array) $wpdb->get_results(
            "SELECT u.ID,u.display_name,u.user_email,u.user_registered,
                    um_ci.meta_value AS city_id, um_ch.meta_value AS channel_id,
                    um_st.meta_value AS user_status
             FROM {$wpdb->users} u
             LEFT JOIN {$wpdb->usermeta} um_ci ON u.ID=um_ci.user_id AND um_ci.meta_key='fplms_city'
             LEFT JOIN {$wpdb->usermeta} um_ch ON u.ID=um_ch.user_id AND um_ch.meta_key='fplms_channel'
             LEFT JOIN {$wpdb->usermeta} um_st ON u.ID=um_st.user_id AND um_st.meta_key='fplms_user_status'
             WHERE 1=1 $uid_w $date_w ORDER BY u.user_registered DESC LIMIT 1000"
        );
        $act=0; $inact=0;
        foreach ($created as $r) { if (empty($r->user_status) || 'active' === $r->user_status) $act++; else $inact++; }
        $html .= $this->stat_cards([
            ['label'=>'Usuarios Registrados', 'value'=>'<strong>'.count($created).'</strong>', 'color'=>'blue'],
            ['label'=>'Activos',   'value'=>'<strong>'.$act.'</strong>',   'color'=>'green'],
            ['label'=>'Inactivos', 'value'=>'<strong>'.$inact.'</strong>', 'color'=>'red'],
        ]);
        $html .= $this->sub('Usuarios Registrados');
        $html .= $this->paged_table_open(['Nombre','Email','Fecha Registro','Ciudad','Canal','Estado']);
        foreach ($created as $r) {
            $city    = $r->city_id    ? $this->structures->get_term_name_by_id((int)$r->city_id)    : '—';
            $channel = $r->channel_id ? $this->structures->get_term_name_by_id((int)$r->channel_id) : '—';
            $isact   = empty($r->user_status) || 'active' === $r->user_status;
            $html .= '<tr><td>'.esc_html($r->display_name).'</td><td>'.esc_html($r->user_email).'</td>'
                   . '<td>'.esc_html(wp_date('d/m/Y',strtotime($r->user_registered))).'</td>'
                   . '<td>'.esc_html($city).'</td><td>'.esc_html($channel).'</td>'
                   . '<td>'.($isact?$this->badge('Activo','green'):$this->badge('Inactivo','red')).'</td></tr>';
        }
        $html .= $this->paged_table_close();

        // 1B. Frecuencia de acceso
        $login_table = $wpdb->prefix.'fplms_user_logins';
        $login_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$login_table)) === $login_table);
        $html .= $this->sub('Frecuencia de Acceso / N. de Actividades del Sistema');
        if (!$login_exists) {
            $html .= $this->empty_msg('Tabla de logins no encontrada. Ejecuta el script create-activity-table.php.');
        } else {
            $ld      = $this->date_sql('l.login_time', $f['date_from'], $f['date_to']);
            $lw      = null!==$uids ? (empty($uids)?'AND 1=0':'AND l.user_id IN ('.implode(',',array_map('intval',$uids)).')') : '';
            $uw_act  = null!==$uids ? (empty($uids)?'AND 1=0':'AND u.ID IN ('.implode(',',array_map('intval',$uids)).')') : '';
            $act_tbl = $wpdb->prefix.'fplms_user_activity';
            $act_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$act_tbl)) === $act_tbl);
            // Date filter applied to activity_time so pings are scoped to the same date range as logins
            $ad      = $this->date_sql('a.activity_time', $f['date_from'], $f['date_to']);
            // Also scope the activity subquery to the filtered user(s) to avoid cross-user aggregation
            $aw      = null!==$uids ? (empty($uids)?'AND 1=0':'AND a.user_id IN ('.implode(',',array_map('intval',$uids)).')') : '';
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $logins = (array)$wpdb->get_results(
                "SELECT u.display_name,u.user_email,u.ID AS user_id,COUNT(l.id) AS login_count,MAX(l.login_time) AS last_login"
                . ($act_exists ? ",COALESCE(ua.activity_count,0) AS activity_count" : ",0 AS activity_count")
                // LEFT JOIN so filtered users with 0 logins still appear when a user filter is active
                . " FROM {$wpdb->users} u LEFT JOIN `{$login_table}` l ON u.ID=l.user_id{$ld}"
                . ($act_exists ? " LEFT JOIN (SELECT user_id,COUNT(*) AS activity_count FROM `{$act_tbl}` a WHERE 1=1{$ad}{$aw} GROUP BY user_id) ua ON u.ID=ua.user_id" : "")
                // Without a user filter require at least 1 login; with a user filter always show the user
                . " WHERE 1=1 $uw_act GROUP BY u.ID"
                . (null === $uids ? " HAVING login_count > 0" : "")
                . " ORDER BY login_count DESC LIMIT 500"
            );
            if (empty($logins)) { $html .= $this->empty_msg('Sin registros de login en el periodo seleccionado.'); }
            else {
                $html .= $this->paged_table_open(['Nombre','Email','N. de Ingresos','Actividad','Ultimo Ingreso']);
                foreach ($logins as $r) {
                    $acts = (int)$r->activity_count;
                    $act_badge = $acts >= 50 ? $this->badge($acts,'green') : ($acts >= 10 ? $this->badge($acts,'yellow') : $this->badge($acts,'red'));
                    $html .= '<tr><td>'.esc_html($r->display_name).'</td><td>'.esc_html($r->user_email).'</td>'
                           . '<td><strong>'.(int)$r->login_count.'</strong></td>'
                           . '<td>'.$act_badge.'</td>'
                           . '<td>'.esc_html($r->last_login ? \DateTime::createFromFormat('Y-m-d H:i:s', $r->last_login)->format('d/m/Y H:i') : '—').'</td></tr>';
                }
                $html .= $this->paged_table_close();
                if ($act_exists) {
                    $html .= '<p style="font-size:11px;color:#888;margin:4px 0 0">'
                           . '&#9432; <strong>Actividad</strong>: pings registrados mientras el usuario naveg&oacute; la plataforma (aprox. 1 ping cada 5 minutos de sesion activa). '
                           . '<span style="background:#46b450;color:#fff;padding:1px 6px;border-radius:3px;font-size:10px">&ge;50</span> alta &nbsp;'
                           . '<span style="background:#ffb900;color:#fff;padding:1px 6px;border-radius:3px;font-size:10px">&ge;10</span> media &nbsp;'
                           . '<span style="background:#dc3232;color:#fff;padding:1px 6px;border-radius:3px;font-size:10px">&lt;10</span> baja</p>';
                }
            }
        }

        // 1C. Tasa de abandono
        $ms = $this->ms_table();
        $html .= $this->sub('Tasa de Abandono — Inscritos sin Participacion o sin Completar');
        if (!$ms) { $html .= $this->empty_msg('Tabla de matriculas MasterStudy no encontrada.'); }
        else {
            $mw = null!==$uids?(empty($uids)?'AND 1=0':'AND uc.user_id IN ('.implode(',',array_map('intval',$uids)).')'):'';
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $total_enroll = (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$ms}` uc WHERE 1=1 $mw");
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $abandon = (array)$wpdb->get_results(
                "SELECT u.display_name,u.user_email,p.post_title AS course_name,uc.progress_percent,uc.status
                 FROM `{$ms}` uc INNER JOIN {$wpdb->users} u ON uc.user_id=u.ID
                 INNER JOIN {$wpdb->posts} p ON uc.course_id=p.ID
                 WHERE uc.progress_percent<1 AND uc.status NOT IN('completed','passed') $mw
                 ORDER BY u.display_name,p.post_title LIMIT 500"
            );
            $ac  = count($abandon);
            $pct = $total_enroll>0 ? round($ac/$total_enroll*100,1) : 0;
            $html .= $this->stat_cards([
                ['label'=>'Inscripciones sin progreso', 'value'=>'<strong>'.$ac.'</strong>',          'color'=>'red'],
                ['label'=>'Total inscripciones',        'value'=>'<strong>'.$total_enroll.'</strong>', 'color'=>'blue'],
                ['label'=>'Tasa de abandono',           'value'=>'<strong>'.$pct.'%</strong>',        'color'=>$pct>=50?'red':($pct>=25?'yellow':'green')],
            ]);
            if (!empty($abandon)) {
                $html .= $this->paged_table_open(['Usuario','Email','Curso','Progreso','Estado']);
                foreach ($abandon as $r) {
                    $html .= '<tr><td>'.esc_html($r->display_name).'</td><td>'.esc_html($r->user_email).'</td>'
                           . '<td>'.esc_html($r->course_name).'</td><td>'.(int)$r->progress_percent.'%</td>'
                           . '<td>'.$this->badge($this->translate_status($r->status??''),'yellow').'</td></tr>';
                }
                $html .= $this->paged_table_close();
            }
        }

        // 1D. Dados de baja
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $inactive = (array)$wpdb->get_results(
            "SELECT u.ID,u.display_name,u.user_email,u.user_registered,
                    um_ci.meta_value AS city_id,um_ch.meta_value AS channel_id,
                    um_db.meta_value AS deactivated_by_id,
                    um_dd.meta_value AS deactivated_date
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} um_st ON u.ID=um_st.user_id AND um_st.meta_key='fplms_user_status' AND um_st.meta_value='inactive'
             LEFT JOIN {$wpdb->usermeta} um_ci ON u.ID=um_ci.user_id AND um_ci.meta_key='fplms_city'
             LEFT JOIN {$wpdb->usermeta} um_ch ON u.ID=um_ch.user_id AND um_ch.meta_key='fplms_channel'
             LEFT JOIN {$wpdb->usermeta} um_db ON u.ID=um_db.user_id AND um_db.meta_key='fplms_deactivated_by'
             LEFT JOIN {$wpdb->usermeta} um_dd ON u.ID=um_dd.user_id AND um_dd.meta_key='fplms_deactivated_date'
             WHERE 1=1 $uid_w ORDER BY um_dd.meta_value DESC, u.display_name LIMIT 500"
        );
        // Preload display names of admins who deactivated users (avoid N+1 queries)
        $admin_ids = array_filter(array_unique(array_column((array)$inactive,'deactivated_by_id')));
        $admin_names = [];
        if (!empty($admin_ids)) {
            $id_in = implode(',', array_map('intval', $admin_ids));
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            foreach ((array)$wpdb->get_results("SELECT ID, display_name FROM {$wpdb->users} WHERE ID IN ({$id_in})") as $adm) {
                $admin_names[(int)$adm->ID] = $adm->display_name;
            }
        }
        $html .= $this->sub('Usuarios Dados de Baja / Inactivos ('.count($inactive).')');
        if (empty($inactive)) { $html .= $this->empty_msg('No hay usuarios dados de baja.'); }
        else {
            $html .= $this->paged_table_open(['ID','Nombre','Email','Fecha Registro','Ciudad','Canal','Inactivado por','Fecha Inactivacion']);
            foreach ($inactive as $r) {
                $city    = $r->city_id    ? $this->structures->get_term_name_by_id((int)$r->city_id)    : '—';
                $channel = $r->channel_id ? $this->structures->get_term_name_by_id((int)$r->channel_id) : '—';
                $by      = !empty($r->deactivated_by_id) ? esc_html($admin_names[(int)$r->deactivated_by_id] ?? 'ID '.(int)$r->deactivated_by_id) : '—';
                $date    = !empty($r->deactivated_date)  ? esc_html(wp_date('d/m/Y H:i', strtotime($r->deactivated_date))) : '—';
                $html .= '<tr><td>'.(int)$r->ID.'</td><td>'.esc_html($r->display_name).'</td><td>'.esc_html($r->user_email).'</td>'
                       . '<td>'.esc_html(wp_date('d/m/Y',strtotime($r->user_registered))).'</td>'
                       . '<td>'.esc_html($city).'</td><td>'.esc_html($channel).'</td>'
                       . '<td>'.$by.'</td><td>'.$date.'</td></tr>';
            }
            $html .= $this->paged_table_close();
        }
        wp_send_json_success(['html'=>$html]);
    }

    // REPORT 2 — PROGRESO
    public function ajax_report_progress(): void {
        if (!$this->verify_request()) return;
        global $wpdb;
        $f=$this->get_filters(); $uids=$this->get_user_ids_for_filters($f); $ms=$this->ms_table();
        $html = $this->render_section_head('Reporte de Progreso', $this->build_export_url('progress',$f));
        if (!$ms) { $html.=$this->empty_msg('Tabla de matriculas MasterStudy no encontrada.'); wp_send_json_success(['html'=>$html]); return; }

        $mw = null!==$uids?(empty($uids)?'AND uc.user_id=0':'AND uc.user_id IN ('.implode(',',array_map('intval',$uids)).')'):'';

        // 2A. Por curso
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $cstats = (array)$wpdb->get_results(
            "SELECT p.ID AS course_id, p.post_title AS course_name, p.post_status AS course_status,
                    COUNT(uc.user_id) AS enrolled,
                    ROUND(AVG(uc.progress_percent),1) AS avg_progress,
                    SUM(CASE WHEN uc.status IN('completed','passed') OR uc.progress_percent>=100 THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN uc.progress_percent>0 AND uc.progress_percent<100 AND uc.status NOT IN('completed','passed') THEN 1 ELSE 0 END) AS in_progress,
                    SUM(CASE WHEN uc.progress_percent<1 AND uc.status NOT IN('completed','passed') THEN 1 ELSE 0 END) AS not_started
             FROM {$wpdb->posts} p
             LEFT JOIN `{$ms}` uc ON p.ID=uc.course_id $mw
             WHERE p.post_type='stm-courses' AND p.post_status IN('publish','draft')
             GROUP BY p.ID ORDER BY enrolled DESC, p.post_title"
        );
        $html .= $this->sub('Porcentaje de Avance por Curso');
        if (empty($cstats)) { $html.=$this->empty_msg('Sin datos.'); }
        else {
            $html .= $this->paged_table_open(['Curso','Inscritos','Avance Prom.','Completados','Tasa Finalizacion','En Progreso','No Iniciados','Tasa Abandono','Estado']);
            foreach ($cstats as $r) {
                $en=(int)$r->enrolled; $ep=max(1,$en);
                $cp=(int)$r->completed; $ns=(int)$r->not_started;
                $fp=round($cp/$ep*100,1); $ap=round($ns/$ep*100,1);
                $fc=$fp>=70?'green':($fp>=40?'yellow':'red'); $ac=$ap<=20?'green':($ap<=50?'yellow':'red');
                $is_active = ($r->course_status === 'publish');
                $html .= '<tr><td>'.esc_html($r->course_name).'</td><td>'.$en.'</td><td>'.esc_html((string)$r->avg_progress).'%</td>'
                       . '<td>'.$cp.'</td><td>'.$this->badge($fp.'%',$fc).'</td>'
                       . '<td>'.(int)$r->in_progress.'</td><td>'.$ns.'</td><td>'.$this->badge($ap.'%',$ac).'</td>'
                       . '<td>'.($is_active ? $this->badge('Activo','green') : $this->badge('Inactivo','red')).'</td></tr>';
            }
            $html .= $this->paged_table_close();
        }

        // 2B. Mayor abandono top 10
        $html .= $this->sub('Capacitaciones con Mayor Abandono (Top 10)');
        $sorted = array_filter($cstats, fn($r)=>(int)$r->enrolled>0 && $r->course_status==='publish');
        usort($sorted, fn($a,$b)=>(int)$b->not_started/(max(1,(int)$b->enrolled)) <=> (int)$a->not_started/(max(1,(int)$a->enrolled)));
        $top10 = array_slice($sorted,0,10);
        if (!empty($top10)) {
            $html .= $this->paged_table_open(['Curso','Inscritos','Sin Participacion','Tasa Abandono']);
            foreach ($top10 as $r) {
                $ep=max(1,(int)$r->enrolled); $pct=round((int)$r->not_started/$ep*100,1);
                $html .= '<tr><td>'.esc_html($r->course_name).'</td><td>'.(int)$r->enrolled.'</td>'
                       . '<td>'.(int)$r->not_started.'</td><td>'.$this->badge($pct.'%',$pct>50?'red':'yellow').'</td></tr>';
            }
            $html .= $this->paged_table_close();
        }

        // 2C. Por usuario
        $uw = null!==$uids?(empty($uids)?'AND u.ID=0':'AND u.ID IN ('.implode(',',array_map('intval',$uids)).')'):'';
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $uprg = (array)$wpdb->get_results(
            "SELECT u.display_name,u.user_email,COUNT(uc.course_id) AS total_courses,
                    ROUND(AVG(uc.progress_percent),1) AS avg_progress,
                    SUM(CASE WHEN uc.status IN('completed','passed') OR uc.progress_percent>=100 THEN 1 ELSE 0 END) AS completed
             FROM {$wpdb->users} u INNER JOIN `{$ms}` uc ON u.ID=uc.user_id
             WHERE 1=1 $uw GROUP BY u.ID ORDER BY avg_progress DESC LIMIT 300"
        );
        $html .= $this->sub('Progreso por Usuario');
        if (empty($uprg)) { $html.=$this->empty_msg('Sin datos de progreso por usuario.'); }
        else {
            $html .= $this->paged_table_open(['Usuario','Email','Cursos Asignados','Avance Promedio','Completados','Pendientes']);
            foreach ($uprg as $r) {
                $t=max(1,(int)$r->total_courses); $cp=(int)$r->completed;
                $html .= '<tr><td>'.esc_html($r->display_name).'</td><td>'.esc_html($r->user_email).'</td>'
                       . '<td>'.$t.'</td><td>'.esc_html((string)$r->avg_progress).'%</td>'
                       . '<td>'.$cp.'</td><td>'.($t-$cp).'</td></tr>';
            }
            $html .= $this->paged_table_close();
        }
        wp_send_json_success(['html'=>$html]);
    }

    // REPORT 3 — DESEMPENO
    public function ajax_report_performance(): void {
        if (!$this->verify_request()) return;
        global $wpdb;
        $f=$this->get_filters(); $uids=$this->get_user_ids_for_filters($f); $ms=$this->ms_table();
        $html = $this->render_section_head('Reporte de Desempeno', $this->build_export_url('performance',$f));

        // 3A. Quiz results
        $quiz_table=null;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->prefix.'stm_lms_user_quizzes'))===$wpdb->prefix.'stm_lms_user_quizzes') $quiz_table=$wpdb->prefix.'stm_lms_user_quizzes';
        $html .= $this->sub('Calificaciones en Evaluaciones');
        if ($quiz_table) {
            $qw = null!==$uids?(empty($uids)?'AND uq.user_id=0':'AND uq.user_id IN ('.implode(',',array_map('intval',$uids)).')'):'';
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $qrows = (array)$wpdb->get_results(
                "SELECT uq.user_id,uq.course_id,uq.quiz_id,uq.user_quiz_id,
                        uq.progress AS score,uq.status,
                        u.display_name,u.user_email,
                        pc.post_title AS course_name,q.post_title AS quiz_name
                 FROM `{$quiz_table}` uq INNER JOIN {$wpdb->users} u ON uq.user_id=u.ID
                 LEFT JOIN {$wpdb->posts} q ON uq.quiz_id=q.ID LEFT JOIN {$wpdb->posts} pc ON uq.course_id=pc.ID
                 WHERE 1=1 $qw ORDER BY uq.user_id,uq.quiz_id,uq.course_id,uq.user_quiz_id ASC LIMIT 2000"
            );
            if (!empty($qrows)) {
                $agg = $this->aggregate_quiz_rows($qrows);
                $passed=count(array_filter($agg,fn($r)=>in_array(strtolower($r->status??''),['passed','completed'],true)));
                $failed=count($agg)-$passed; $tot=count($agg);
                $pp=$tot>0?round($passed/$tot*100,1):0;
                $html .= $this->stat_cards([
                    ['label'=>'Evaluados',  'value'=>'<strong>'.$tot.'</strong>',                'color'=>'blue'],
                    ['label'=>'Aprobados',  'value'=>'<strong>'.$passed.'</strong>', 'sub'=>$pp.'%', 'color'=>'green'],
                    ['label'=>'Reprobados', 'value'=>'<strong>'.$failed.'</strong>',               'color'=>'red'],
                ]);
                $html .= $this->paged_table_open(['Usuario','Email','Curso','Evaluacion','Intentos','Puntaje','Estado']);
                foreach ($agg as $r) {
                    $st=strtolower($r->status??'');
                    $cl=in_array($st,['passed','completed'],true)?'green':(in_array($st,['failed','not_passed'],true)?'red':'yellow');
                    $n=(int)$r->attempts_label;
                    $html .= '<tr><td>'.esc_html($r->display_name).'</td><td>'.esc_html($r->user_email).'</td>'
                           . '<td>'.esc_html($r->course_name??'—').'</td><td>'.esc_html($r->quiz_name??'—').'</td>'
                           . '<td>'.($n===1?'1 intento':$n.' intentos').'</td>'
                           . '<td>'.esc_html((string)$r->score).'</td><td>'.$this->badge($this->translate_status($r->status??''),$cl).'</td></tr>';
                }
                $html .= $this->paged_table_close();
            } else { $html.=$this->empty_msg('Sin datos de evaluaciones.'); }
        } else { $html.=$this->empty_msg('Tabla de resultados de MasterStudy no encontrada.'); }

        // 3B. Cursos vencidos
        $html .= $this->sub('Capacitaciones Vencidas (Plazo Cumplido — Pendientes)');
        if ($ms) {
            $today=wp_date('Y-m-d');
            $mw2=null!==$uids?(empty($uids)?'AND uc.user_id=0':'AND uc.user_id IN ('.implode(',',array_map('intval',$uids)).')'):'';
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $exp=(array)$wpdb->get_results($wpdb->prepare(
                "SELECT u.display_name,u.user_email,p.post_title AS course_name,uc.status,uc.progress_percent,
                        pm.meta_value AS end_days,DATE_ADD(p.post_date,INTERVAL pm.meta_value DAY) AS expiry_date
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id AND pm.meta_key='end_time' AND pm.meta_value>0
                 INNER JOIN `{$ms}` uc ON p.ID=uc.course_id INNER JOIN {$wpdb->users} u ON uc.user_id=u.ID
                 WHERE p.post_type='stm-courses' AND DATE_ADD(p.post_date,INTERVAL pm.meta_value DAY)<%s
                   AND uc.status NOT IN('completed','passed') AND uc.progress_percent<100 $mw2
                 ORDER BY expiry_date ASC LIMIT 300",$today));
            $html .= $this->stat_cards([
                ['label'=>'Usuarios con cursos vencidos', 'value'=>'<strong>'.count($exp).'</strong>', 'color'=>'red'],
            ]);
            if (!empty($exp)) {
                $html .= $this->paged_table_open(['Usuario','Email','Curso','Progreso','Dias Vigencia','Vencio el']);
                foreach ($exp as $r) {
                    $html .= '<tr><td>'.esc_html($r->display_name).'</td><td>'.esc_html($r->user_email).'</td>'
                           . '<td>'.esc_html($r->course_name).'</td><td>'.(int)$r->progress_percent.'%</td>'
                           . '<td>'.(int)$r->end_days.' dias</td>'
                           . '<td>'.$this->badge(wp_date('d/m/Y',strtotime($r->expiry_date)),'red').'</td></tr>';
                }
                $html .= $this->paged_table_close();
            } else { $html.=$this->empty_msg('No hay cursos vencidos sin completar.'); }
        } else { $html.=$this->empty_msg('Tabla de matriculas no encontrada.'); }

        // 3C. Mayor cumplimiento
        $html .= $this->sub('Capacitaciones con Mayor Cumplimiento (Top 10)');
        if ($ms) {
            $mw3=null!==$uids?(empty($uids)?'AND uc.user_id=0':'AND uc.user_id IN ('.implode(',',array_map('intval',$uids)).')'):'';
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $tc=(array)$wpdb->get_results(
                "SELECT p.post_title AS course_name, p.post_status AS course_status,
                        COUNT(uc.user_id) AS enrolled,
                        SUM(CASE WHEN uc.status IN('completed','passed') OR uc.progress_percent>=100 THEN 1 ELSE 0 END) AS completed,
                        ROUND(SUM(CASE WHEN uc.status IN('completed','passed') OR uc.progress_percent>=100 THEN 1 ELSE 0 END)/COUNT(uc.user_id)*100,1) AS rate
                 FROM {$wpdb->posts} p INNER JOIN `{$ms}` uc ON p.ID=uc.course_id
                 WHERE p.post_type='stm-courses' AND p.post_status IN('publish','draft') AND uc.user_id>0 $mw3
                 GROUP BY p.ID HAVING enrolled>=1 ORDER BY rate DESC LIMIT 10"
            );
            if (!empty($tc)) {
                $html .= $this->paged_table_open(['Curso','Estado','Inscritos','Completados','Tasa Cumplimiento']);
                foreach ($tc as $r) {
                    $is_active = ($r->course_status === 'publish');
                    $html .= '<tr><td>'.esc_html($r->course_name).'</td>'
                           . '<td>'.($is_active ? $this->badge('Activo','green') : $this->badge('Inactivo','red')).'</td>'
                           . '<td>'.(int)$r->enrolled.'</td>'
                           . '<td>'.(int)$r->completed.'</td>'
                           . '<td>'.$this->badge($r->rate.'%',$r->rate>=70?'green':($r->rate>=40?'yellow':'red')).'</td></tr>';
                }
                $html .= $this->paged_table_close();
            } else { $html.=$this->empty_msg('Sin datos.'); }
        }
        wp_send_json_success(['html'=>$html]);
    }

    // REPORT 4 — CERTIFICADOS
    public function ajax_report_certificates(): void {
        if (!$this->verify_request()) return;
        global $wpdb;
        $f=$this->get_filters(); $uids=$this->get_user_ids_for_filters($f); $ms=$this->ms_table();
        if (!$ms) { wp_send_json_success(['html'=>$this->render_section_head('Reporte de Certificacion','').$this->empty_msg('Tabla de matriculas no encontrada.')]); return; }
        $uw=null!==$uids?(empty($uids)?'AND uc.user_id=0':'AND uc.user_id IN ('.implode(',',array_map('intval',$uids)).')'):'';
        $dw='';
        if (!empty($f['date_from'])) $dw.=$wpdb->prepare(' AND FROM_UNIXTIME(uc.end_time) >= %s',$f['date_from'].' 00:00:00');
        if (!empty($f['date_to']))   $dw.=$wpdb->prepare(' AND FROM_UNIXTIME(uc.end_time) <= %s',$f['date_to']  .' 23:59:59');
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $certs=(array)$wpdb->get_results(
            "SELECT u.ID AS user_id, u.display_name, u.user_email,
                    p.ID AS course_id, p.post_title AS course_name,
                    uc.end_time,
                    pm_cert.meta_value AS cert_template_id,
                    cp.post_title AS template_name,
                    prev.meta_value AS cert_preview
             FROM `{$ms}` uc
             INNER JOIN {$wpdb->users} u ON uc.user_id=u.ID
             INNER JOIN {$wpdb->posts} p ON uc.course_id=p.ID AND p.post_status='publish'
             INNER JOIN {$wpdb->postmeta} pm_cert ON p.ID=pm_cert.post_id
                 AND pm_cert.meta_key='course_certificate'
                 AND pm_cert.meta_value REGEXP '^[0-9]+$'
             LEFT JOIN {$wpdb->posts} cp ON pm_cert.meta_value=cp.ID
             LEFT JOIN {$wpdb->postmeta} prev ON cp.ID=prev.post_id AND prev.meta_key='certificate_preview'
             WHERE (uc.status IN('completed','passed') OR uc.progress_percent>=100)
               AND uc.end_time>0
               $uw $dw
             ORDER BY u.display_name, p.post_title LIMIT 1000"
        );
        $html = $this->render_section_head('Reporte de Certificacion', $this->build_export_url('certificates',$f));

        $total     = count($certs);
        $students  = count(array_unique(array_column((array)$certs,'user_id')));
        $courses_n = count(array_unique(array_column((array)$certs,'course_id')));
        $html .= $this->stat_cards([
            ['label'=>'Certificados alcanzados', 'value'=>'<strong>'.$total.'</strong>',     'color'=>'green'],
            ['label'=>'Estudiantes',              'value'=>'<strong>'.$students.'</strong>',  'color'=>'blue'],
            ['label'=>'Cursos con certificado',  'value'=>'<strong>'.$courses_n.'</strong>', 'color'=>'orange'],
        ]);
        $html .= '<p style="font-size:12px;color:#888;margin:0 0 14px">'
               . '&#9432; El seguimiento de descarga no esta disponible en la version actual de MasterStudy LMS.</p>';

        // — Cursos con Certificado
        $html .= $this->sub('Cursos con Certificado Configurado');
        if (!empty($certs)) {
            $by_c=[];
            foreach ($certs as $r) {
                $cid=(int)$r->course_id;
                if (!isset($by_c[$cid])) $by_c[$cid]=['name'=>$r->course_name,'template'=>$r->template_name?:'—','preview'=>$r->cert_preview??'','count'=>0];
                $by_c[$cid]['count']++;
            }
            usort($by_c,fn($a,$b)=>$b['count']<=>$a['count']);
            $html .= $this->paged_table_open(['Vista Previa','Curso','Plantilla','Estudiantes con Certificado']);
            foreach ($by_c as $c) {
                $thumb = !empty($c['preview'])
                    ? '<img src="'.esc_url($c['preview']).'\" style="height:48px;border-radius:4px;border:1px solid #ddd" loading="lazy">'
                    : '<span style="color:#ccc;font-size:11px">Sin preview</span>';
                $html .= '<tr>'
                       . '<td style="width:80px">'. $thumb .'</td>'
                       . '<td>'.esc_html($c['name']).'</td>'
                       . '<td>'.esc_html($c['template']).'</td>'
                       . '<td><strong>'.$c['count'].'</strong></td>'
                       . '</tr>';
            }
            $html .= $this->paged_table_close();
        } else { $html.=$this->empty_msg('No hay cursos con certificado configurado o ningun estudiante lo ha completado.'); }

        // — Detalle por Estudiante
        $html .= $this->sub('Detalle — Estudiante / Curso / Certificado');
        if (!empty($certs)) {
            $html .= $this->paged_table_open(['Estudiante','Email','Curso','Fecha Finalizacion','Certificado']);
            foreach ($certs as $r) {
                $cert_url = home_url('/certificate/'.(int)$r->course_id.'/'.(int)$r->user_id.'/');
                $btn      = '<a href="'.esc_url($cert_url).'" target="_blank" class="button button-small" style="font-size:11px;padding:2px 8px">Ver certificado</a>';
                $end_date = (int)$r->end_time>0 ? wp_date('d/m/Y',(int)$r->end_time) : '—';
                $html .= '<tr>'
                       . '<td>'.esc_html($r->display_name).'</td>'
                       . '<td>'.esc_html($r->user_email).'</td>'
                       . '<td>'.esc_html($r->course_name).'</td>'
                       . '<td>'.esc_html($end_date).'</td>'
                       . '<td>'.$btn.'</td>'
                       . '</tr>';
            }
            $html .= $this->paged_table_close();
        } else { $html.=$this->empty_msg('No se encontraron certificados obtenidos.'); }
        wp_send_json_success(['html'=>$html]);
    }

    // REPORT 5 — TIEMPO
    public function ajax_report_time(): void {
        if (!$this->verify_request()) return;
        global $wpdb;
        $f=$this->get_filters(); $uids=$this->get_user_ids_for_filters($f); $ms=$this->ms_table();
        $html = $this->render_section_head('Reporte de Tiempo de Capacitacion', $this->build_export_url('time',$f));
        if (!$ms) { $html.=$this->empty_msg('Tabla de matriculas no encontrada.'); wp_send_json_success(['html'=>$html]); return; }
        $mw=null!==$uids?(empty($uids)?'AND uc.user_id=0':'AND uc.user_id IN ('.implode(',',array_map('intval',$uids)).')'):''; 
        $sec_tbl=$wpdb->prefix.'stm_lms_curriculum_sections';
        $mat_tbl=$wpdb->prefix.'stm_lms_curriculum_materials';
        $ul_tbl =$wpdb->prefix.'stm_lms_user_lessons';
        // 1) All enrollments (completed + in-progress)
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $enrollments=(array)$wpdb->get_results(
            "SELECT u.ID AS user_id, u.display_name, u.user_email,
                    p.ID AS course_id, p.post_title AS course_name,
                    uc.progress_percent, uc.status
             FROM `{$ms}` uc
             INNER JOIN {$wpdb->users} u ON uc.user_id=u.ID
             INNER JOIN {$wpdb->posts} p ON uc.course_id=p.ID AND p.post_status='publish'
             WHERE 1=1 $mw
             ORDER BY u.display_name, p.post_title LIMIT 1000"
        );
        if (empty($enrollments)) {
            $html.=$this->empty_msg('Sin datos de tiempo.');
            wp_send_json_success(['html'=>$html]); return;
        }
        $course_ids=array_unique(array_map(fn($r)=>(int)$r->course_id, $enrollments));
        $user_ids  =array_unique(array_map(fn($r)=>(int)$r->user_id,   $enrollments));
        $cid_in=implode(',', $course_ids);
        $uid_in=implode(',', $user_ids);
        // 2) All lesson+quiz durations per course
        // Lessons: duration parsed normally (H:MM, "2h 45m", etc.)
        // Quizzes: duration value is always plain minutes (e.g. "5" = 5 min)
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $ld_rows=(array)$wpdb->get_results(
            "SELECT cs.course_id, cm.post_id AS lesson_id, lp.post_type, pm.meta_value AS duration
             FROM `{$sec_tbl}` cs
             INNER JOIN `{$mat_tbl}` cm ON cm.section_id=cs.id
             INNER JOIN {$wpdb->posts} lp ON cm.post_id=lp.ID AND lp.post_type IN ('stm-lessons','stm-quizzes')
             LEFT JOIN {$wpdb->postmeta} pm ON cm.post_id=pm.post_id AND pm.meta_key='duration'
             WHERE cs.course_id IN ({$cid_in})"
        );
        $course_lessons=[]; // [course_id][lesson_id] = minutes
        foreach ($ld_rows as $ld) {
            $cid=(int)$ld->course_id; $lid=(int)$ld->lesson_id;
            // Quizzes: treat duration as plain minutes; lessons: use full parser
            $mins = ($ld->post_type === 'stm-quizzes')
                ? (float)($ld->duration ?? 0)
                : $this->parse_duration_minutes($ld->duration ?? '');
            $course_lessons[$cid][$lid] = $mins;
        }
        // 3) Lessons completed per user per course
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $done_rows=(array)$wpdb->get_results(
            "SELECT user_id, course_id, lesson_id
             FROM `{$ul_tbl}`
             WHERE course_id IN ({$cid_in}) AND user_id IN ({$uid_in}) AND end_time>0"
        );
        $done_map=[]; // "uid_cid" => [lesson_id => true]
        foreach ($done_rows as $dr) {
            $done_map[(int)$dr->user_id.'_'.(int)$dr->course_id][(int)$dr->lesson_id]=true;
        }
        // 4) Compute per-row hours
        $trows=[];
        foreach ($enrollments as $r) {
            $uid=(int)$r->user_id; $cid=(int)$r->course_id;
            $is_done=in_array($r->status,['completed','passed'],true)||(int)$r->progress_percent>=100;
            $all_mins=$course_lessons[$cid]??[];
            $total_min=(float)array_sum($all_mins);
            $done_min=0.0;
            foreach (($done_map[$uid.'_'.$cid]??[]) as $lid=>$_) $done_min+=$all_mins[$lid]??0.0;
            $trows[]=(object)[
                'display_name'=>$r->display_name,'user_email'=>$r->user_email,
                'course_name'=>$r->course_name,'progress'=>(int)$r->progress_percent,
                'is_done'=>$is_done,'total_min'=>$total_min,
                'lesson_min'=>$is_done?$total_min:$done_min,
            ];
        }
        // Summary by user
        $by=[];
        foreach ($trows as $r) {
            $k=$r->display_name.'|'.$r->user_email;
            if (!isset($by[$k])) $by[$k]=['name'=>$r->display_name,'email'=>$r->user_email,'lesson_min'=>0.0,'courses'=>0];
            $by[$k]['lesson_min']+=$r->lesson_min;
            $by[$k]['courses']++;
        }
        uasort($by,fn($a,$b)=>$b['lesson_min']<=>$a['lesson_min']);
        $th=(float)array_sum(array_column($by,'lesson_min'));
        $html.=$this->stat_cards([
            ['label'=>'Total horas de lección', 'value'=>'<strong>'.$this->fmt_minutes($th).'</strong>', 'color'=>'orange'],
            ['label'=>'Usuarios con actividad', 'value'=>'<strong>'.count($by).'</strong>',              'color'=>'blue'],
        ]);
        $html.=$this->sub('Horas por Usuario');
        $html.=$this->paged_table_open(['Usuario','Email','Cursos','Horas de Lección']);
        foreach ($by as $row) {
            $html.='<tr><td>'.esc_html($row['name']).'</td><td>'.esc_html($row['email']).'</td>'
                  .'<td>'.(int)$row['courses'].'</td><td><strong>'.$this->fmt_minutes($row['lesson_min']).'</strong></td></tr>';
        }
        $html.=$this->paged_table_close();
        $html.=$this->sub('Detalle por Usuario y Curso');
        $html.=$this->paged_table_open(['Usuario','Email','Curso','Progreso','Duración Total','Horas de Lección']);
        foreach ($trows as $r) {
            $badge=$r->is_done?$this->badge('Completado','green'):$this->badge($r->progress.'%',$r->progress>=50?'yellow':'red');
            $html.='<tr>'
                  .'<td>'.esc_html($r->display_name).'</td>'
                  .'<td>'.esc_html($r->user_email).'</td>'
                  .'<td>'.esc_html($r->course_name).'</td>'
                  .'<td>'.$badge.'</td>'
                  .'<td>'.$this->fmt_minutes($r->total_min).'</td>'
                  .'<td><strong>'.$this->fmt_minutes($r->lesson_min).'</strong></td>'
                  .'</tr>';
        }
        $html.=$this->paged_table_close();
        wp_send_json_success(['html'=>$html]);
    }

    // REPORT 6 — SATISFACCION
    public function ajax_report_satisfaction(): void {
        if (!$this->verify_request()) return;
        global $wpdb;
        $f=$this->get_filters(); $uids=$this->get_user_ids_for_filters($f);
        $html=$this->render_section_head('Reporte de Satisfaccion',$this->build_export_url('satisfaction',$f));
        $st=$wpdb->prefix.'fplms_survey_responses';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$st))!==$st) {
            $html.=$this->empty_msg('El modulo de encuestas no tiene respuestas registradas aun.');
            wp_send_json_success(['html'=>$html]); return;
        }
        $uw=null!==$uids?(empty($uids)?'AND sr.user_id=0':'AND sr.user_id IN ('.implode(',',array_map('intval',$uids)).')'):'';
        $dw=$this->date_sql('sr.submitted_at',$f['date_from'],$f['date_to']);

        // ── Aggregate per course (one row per question per user in the table) ──
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $course_stats=(array)$wpdb->get_results(
            "SELECT sr.course_id,p.post_title AS course_name,
                    COUNT(DISTINCT sr.user_id) AS respondents,
                    ROUND(AVG(sr.score),2)     AS avg_score,
                    MIN(sr.submitted_at)       AS first_sub,
                    MAX(sr.submitted_at)       AS last_sub
             FROM `{$st}` sr
             LEFT JOIN {$wpdb->posts} p ON sr.course_id=p.ID
             WHERE 1=1 $uw $dw
             GROUP BY sr.course_id
             ORDER BY respondents DESC"
        );

        $total_resp   =(int)array_sum(array_column($course_stats,'respondents'));
        $total_courses=count($course_stats);
        $overall_avg  =$total_resp>0
            ? round(array_sum(array_map(fn($c)=>(float)$c->avg_score*(int)$c->respondents,$course_stats))/$total_resp,2)
            : 0;
        $overall_pct  =round($overall_avg/5*100,1);
        $ga_col=$overall_pct>=80?'green':($overall_pct>=50?'yellow':'red');

        $html.=$this->stat_cards([
            ['label'=>'Total Respuestas', 'value'=>'<strong>'.$total_resp.'</strong>','color'=>'blue'],
            ['label'=>'Cursos evaluados', 'value'=>'<strong>'.$total_courses.'</strong>','color'=>'green'],
            ['label'=>'Promedio general', 'value'=>'<strong>'.$overall_pct.'%</strong>','color'=>$ga_col],
        ]);

        if (empty($course_stats)) {
            $html.=$this->empty_msg('Sin respuestas para los filtros seleccionados.');
            wp_send_json_success(['html'=>$html,'charts'=>[]]); return;
        }

        // ── Per-course blocks ─────────────────────────────────────────────────
        $html.=$this->sub('Resultados por Encuesta');
        $charts=[]; // chart data passed as separate JSON — scripts in innerHTML never execute

        foreach ($course_stats as $cstat) {
            $cid  =(int)$cstat->course_id;
            $cname=$cstat->course_name?:'(Sin curso)';
            $avg  =(float)$cstat->avg_score;
            $pct  =round($avg/5*100,1);
            $ac   =$pct>=80?'green':($pct>=50?'yellow':'red');

            // Score distribution 1–5
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $dist_rows=(array)$wpdb->get_results($wpdb->prepare(
                "SELECT score,COUNT(*) AS cnt FROM `{$st}` sr WHERE sr.course_id=%d $uw $dw GROUP BY score",$cid
            ));
            $dist=[1=>0,2=>0,3=>0,4=>0,5=>0];
            foreach ($dist_rows as $dr) $dist[(int)$dr->score]=(int)$dr->cnt;

            // Per-question averages
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $q_avgs=(array)$wpdb->get_results($wpdb->prepare(
                "SELECT question_idx,question,ROUND(AVG(score),2) AS avg_score,COUNT(DISTINCT user_id) AS cnt
                 FROM `{$st}` sr WHERE sr.course_id=%d $uw $dw
                 GROUP BY question_idx,question ORDER BY question_idx",$cid
            ));

            $chart_id='fplms-sv-ch-'.$cid;

            // Store chart data for JS (passed outside HTML, not as <script> tag)
            $charts[]=['id'=>$chart_id,'labels'=>['Muy insatisfecho (1)','Insatisfecho (2)','Neutral (3)','Satisfecho (4)','Muy satisfecho (5)'],'data'=>array_values($dist),'colors'=>['#ef4444','#f97316','#eab308','#84cc16','#22c55e']];

            $html.='<div class="fplms-sv-block">';

            // Header
            $html.='<div class="fplms-sv-block-head">'
                  .'<span class="fplms-sv-block-title">'.esc_html($cname).'</span>'
                  .'<span class="fplms-sv-block-meta">'
                  .'<span><strong>'.(int)$cstat->respondents.'</strong> Respuestas</span>'
                  .'<span>Promedio: '.$this->badge($pct.'%',$ac).'</span>'
                  .'<span>Primera: '.esc_html(wp_date('d/m/Y',strtotime($cstat->first_sub))).'</span>'
                  .'<span>Ultima: '.esc_html(wp_date('d/m/Y',strtotime($cstat->last_sub))).'</span>'
                  .'</span></div>';

            // Two-column layout
            $html.='<div class="fplms-sv-layout">';

            // Chart column — canvas only, Chart.js rendered from JS after innerHTML is set
            $html.='<div class="fplms-sv-chart-col">'
                  .'<canvas id="'.esc_attr($chart_id).'" width="220" height="220"></canvas>'
                  .'</div>';

            // Questions column
            $html.='<div class="fplms-sv-questions-col">';
            if (!empty($q_avgs)) {
                $html.='<table class="fplms-sv-qtable widefat striped">'
                      .'<thead><tr><th>#</th><th>Pregunta</th><th>Promedio</th><th>Resp.</th></tr></thead><tbody>';
                foreach ($q_avgs as $qa) {
                    $qa_avg=(float)$qa->avg_score;
                    $qa_pct=round($qa_avg/5*100,1);
                    $qa_col=$qa_pct>=80?'green':($qa_pct>=50?'yellow':'red');
                    $html.='<tr>'
                          .'<td class="fplms-sv-qnum">'.((int)$qa->question_idx+1).'</td>'
                          .'<td>'.esc_html($qa->question).'</td>'
                          .'<td>'.$this->badge($qa_pct.'%',$qa_col).'</td>'
                          .'<td class="fplms-sv-qcnt">'.(int)$qa->cnt.'</td>'
                          .'</tr>';
                }
                $html.='</tbody></table>';
            } else {
                $html.=$this->empty_msg('Sin preguntas registradas.');
            }
            $html.='</div>'; // questions col
            $html.='</div>'; // layout
            $html.='</div>'; // block
        }

        // ── Summary table ─────────────────────────────────────────────────────
        $html.=$this->sub('Resumen por Curso');
        $html.=$this->paged_table_open(['Curso','Respuestas','Promedio','Primera Respuesta','Ultima Respuesta']);
        foreach ($course_stats as $cstat) {
            $avg=(float)$cstat->avg_score;
            $spct=round($avg/5*100,1);
            $ac =$spct>=80?'green':($spct>=50?'yellow':'red');
            $html.='<tr>'
                  .'<td>'.esc_html($cstat->course_name?:'(Sin curso)').'</td>'
                  .'<td>'.(int)$cstat->respondents.'</td>'
                  .'<td>'.$this->badge($spct.'%',$ac).'</td>'
                  .'<td>'.esc_html(wp_date('d/m/Y',strtotime($cstat->first_sub))).'</td>'
                  .'<td>'.esc_html(wp_date('d/m/Y',strtotime($cstat->last_sub))).'</td>'
                  .'</tr>';
        }
        $html.=$this->paged_table_close();

        // Charts data is separate from HTML — consumed by initSvCharts() in JS
        wp_send_json_success(['html'=>$html,'charts'=>$charts]);
    }

    // REPORT 7 — CANALES
    public function ajax_report_channels(): void {
        if (!$this->verify_request()) return;
        global $wpdb;
        $f=$this->get_filters(); $ms=$this->ms_table();
        $cha=['taxonomy'=>FairPlay_LMS_Config::TAX_CHANNEL,'hide_empty'=>false];
        if ($f['channel_id']) $cha['include']=[$f['channel_id']];
        $channels=get_terms($cha);
        $html=$this->render_section_head('Reporte de Canales / Area',$this->build_export_url('channels',$f));
        if (is_wp_error($channels)||empty($channels)) { $html.=$this->empty_msg('No hay canales configurados.'); wp_send_json_success(['html'=>$html]); return; }
        $comp=[];
        foreach ($channels as $ch) {
            $cid=(int)$ch->term_id;
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $cuids=array_map('intval',(array)$wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key='fplms_channel' AND meta_value=%s",(string)$cid
            )));
            $uc=count($cuids);
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $courses=(array)$wpdb->get_results($wpdb->prepare(
                "SELECT p.ID,p.post_title FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id AND pm.meta_key='fplms_course_channels'
                 WHERE p.post_type=%s AND p.post_status='publish' AND pm.meta_value LIKE %s ORDER BY p.post_title",
                FairPlay_LMS_Config::MS_PT_COURSE,'%"'.$cid.'"%'
            ));
            $html.='<h3 class="fplms-rpt-sub fplms-rpt-channel-head">'.esc_html($ch->name)
                  .'<span class="fplms-rpt-channel-count"> ('.$uc.' usuarios | '.count($courses).' cursos)</span></h3>';
            if (empty($courses)) { $html.=$this->empty_msg('Sin cursos asignados a este canal.'); $comp[$ch->name]=['courses'=>0,'enrolled'=>0,'completed'=>0,'rate'=>0]; continue; }
            $html.=$this->paged_table_open(['Curso','Usuarios del Canal','Inscritos en Curso','Completados','Tasa Cumplimiento']);
            $cen=0; $ccp=0;
            foreach ($courses as $cr) {
                $rid=(int)$cr->ID;
                if (!$ms||empty($cuids)) { $html.='<tr><td>'.esc_html($cr->post_title).'</td><td>'.$uc.'</td><td>—</td><td>—</td><td>—</td></tr>'; continue; }
                $ids=implode(',',array_map('intval',$cuids));
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $en=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$ms}` WHERE course_id=%d AND user_id IN ($ids)",$rid));
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $cp=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$ms}` WHERE course_id=%d AND user_id IN ($ids) AND (status IN('completed','passed') OR progress_percent>=100)",$rid));
                $cen+=$en; $ccp+=$cp;
                $rt=$en>0?round($cp/$en*100,1):0; $rc=$rt>=70?'green':($rt>=40?'yellow':'red');
                $html.='<tr><td>'.esc_html($cr->post_title).'</td><td>'.$uc.'</td><td>'.$en.'</td><td>'.$cp.'</td><td>'.$this->badge($rt.'%',$rc).'</td></tr>';
            }
            $html.=$this->paged_table_close();
            $cr2=$cen>0?round($ccp/$cen*100,1):0; $cc=$cr2>=70?'green':($cr2>=40?'yellow':'red');
            $html.='<p class="fplms-rpt-metric" style="text-align:right;margin-top:-8px;">Cumplimiento global del canal: '.$this->badge($cr2.'%',$cc).'</p>';
            $comp[$ch->name]=['courses'=>count($courses),'enrolled'=>$cen,'completed'=>$ccp,'rate'=>$cr2];
        }
        if (count($comp)>1) {
            $html.='<hr style="margin:28px 0;border-color:#eee;">'.$this->sub('Comparacion Global entre Canales');
            $html.=$this->paged_table_open(['Canal','Cursos','Total Inscripciones','Completadas','Tasa Cumplimiento']);
            foreach ($comp as $nm=>$cd) {
                $rt=$cd['rate']??0; $rc=$rt>=70?'green':($rt>=40?'yellow':'red');
                $html.='<tr><td><strong>'.esc_html($nm).'</strong></td><td>'.(int)$cd['courses'].'</td>'
                      .'<td>'.(int)$cd['enrolled'].'</td><td>'.(int)$cd['completed'].'</td>'
                      .'<td>'.$this->badge($rt.'%',$rc).'</td></tr>';
            }
            $html.=$this->paged_table_close();
        }
        wp_send_json_success(['html'=>$html]);
    }

    // USER SEARCH AUTOCOMPLETE
    public function ajax_reports_user_search(): void {
        if (!check_ajax_referer('fplms_reports_nonce','nonce',false)) { wp_send_json_error([],403); return; }
        if (!current_user_can(FairPlay_LMS_Config::CAP_VIEW_REPORTS)) { wp_send_json_error([],403); return; }
        $q=sanitize_text_field(wp_unslash($_POST['q']??''));
        if (strlen($q)<2) { wp_send_json_success(['users'=>[]]); return; }
        $qr=new WP_User_Query(['search'=>'*'.$q.'*','search_columns'=>['display_name','user_email','user_login'],'number'=>15,'fields'=>['ID','display_name','user_email']]);
        $users=[];
        foreach ((array)$qr->get_results() as $u) $users[]=['id'=>(int)$u->ID,'label'=>$u->display_name.' ('.$u->user_email.')'];
        wp_send_json_success(['users'=>$users]);
    }

    // TEST REPORT — LIST OF ALL QUIZZES WITH AGGREGATE STATS
    public function ajax_report_tests(): void {
        if (!$this->verify_request()) return;
        global $wpdb;
        $f    = $this->get_filters();
        $uids = $this->get_user_ids_for_filters($f);
        $html = $this->render_section_head('Reporte de Tests / Evaluaciones', $this->build_export_url('tests', $f));
        $quiz_table = null;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix.'stm_lms_user_quizzes')) === $wpdb->prefix.'stm_lms_user_quizzes')
            $quiz_table = $wpdb->prefix.'stm_lms_user_quizzes';
        if (!$quiz_table) {
            wp_send_json_success(['html' => $html.$this->empty_msg('Tabla de evaluaciones (stm_lms_user_quizzes) no encontrada.')]);
            return;
        }
        $uw     = $this->uid_in($uids, 'uq.user_id');
        // When filters are active, only show quizzes with actual attempts from filtered users
        $having = ($uids !== null) ? 'HAVING COUNT(uq.user_id) > 0' : '';
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = (array)$wpdb->get_results(
            "SELECT q.ID AS quiz_id, q.post_title AS quiz_name,
                    MAX(pc.post_title) AS course_name,
                    COUNT(uq.user_id) AS total_attempts,
                    SUM(CASE WHEN uq.status IN('passed','completed') THEN 1 ELSE 0 END) AS approved,
                    ROUND(AVG(CASE WHEN uq.progress > 0 THEN uq.progress ELSE NULL END), 2) AS avg_score
             FROM {$wpdb->posts} q
             LEFT JOIN `{$quiz_table}` uq ON q.ID = uq.quiz_id $uw
             LEFT JOIN {$wpdb->posts} pc ON uq.course_id = pc.ID
             WHERE q.post_type = 'stm-quizzes' AND q.post_status = 'publish'
             GROUP BY q.ID, q.post_title
             $having
             ORDER BY q.post_title"
        );
        if (empty($rows)) {
            wp_send_json_success(['html' => $html.$this->empty_msg('No se encontraron evaluaciones publicadas.')]);
            return;
        }
        $total_q   = count($rows);
        $total_att = (int)array_sum(array_column($rows, 'total_attempts'));
        $total_app = (int)array_sum(array_column($rows, 'approved'));
        $html .= $this->stat_cards([
            ['label'=>"Test's Evaluados",      'value'=>'<strong>'.$total_q.'</strong>',   'color'=>'blue'],
            ['label'=>'Ejecuciones totales','value'=>'<strong>'.$total_att.'</strong>', 'color'=>'orange'],
            ['label'=>'Aprobados',          'value'=>'<strong>'.$total_app.'</strong>', 'color'=>'green'],
        ]);
        $det_ico = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:3px"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>';
        $html .= $this->paged_table_open(['#', 'Test', 'Curso', 'Completado', 'Aprobado', 'Puntuación Media', 'Opciones']);
        foreach ($rows as $i => $r) {
            $avg  = $r->avg_score !== null ? number_format((float)$r->avg_score, 2).'%' : '—';
            $tot  = (int)$r->total_attempts;
            $app  = (int)$r->approved;
            $app_str = $app.'/'.$tot;
            $acol = $tot > 0 ? ($app/$tot >= .7 ? 'green' : ($app/$tot >= .4 ? 'yellow' : 'red')) : 'yellow';
            $avgscore = (float)($r->avg_score ?? 0);
            $btn  = '<button class="button button-small fplms-test-detail-btn" data-quiz-id="'.esc_attr($r->quiz_id).'" title="Ver Informes">'.$det_ico.'Informes</button>';
            $html .= '<tr>'
                   . '<td style="color:#aaa;font-size:11px">#'.($i+1).'</td>'
                   . '<td><strong>'.esc_html($r->quiz_name).'</strong></td>'
                   . '<td>'.esc_html($r->course_name ?? '—').'</td>'
                   . '<td>'.esc_html((string)$tot).'</td>'
                   . '<td>'.$this->badge($app_str, $acol).'</td>'
                   . '<td>'.$this->badge($avg, $avgscore >= 70 ? 'green' : ($avgscore >= 50 ? 'yellow' : 'red')).'</td>'
                   . '<td>'.$btn.'</td></tr>';
        }
        $html .= $this->paged_table_close();
        wp_send_json_success(['html' => $html]);
    }

    // TEST REPORT — DETAIL (per-user results for a specific quiz)
    public function ajax_report_test_detail(): void {
        if (!$this->verify_request()) return;
        global $wpdb;
        $quiz_id = isset($_POST['quiz_id']) ? (int)$_POST['quiz_id'] : 0;
        if (!$quiz_id) { wp_send_json_error(['message' => 'Quiz ID inválido.']); return; }

        $quiz_table = null;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix.'stm_lms_user_quizzes')) === $wpdb->prefix.'stm_lms_user_quizzes')
            $quiz_table = $wpdb->prefix.'stm_lms_user_quizzes';
        if (!$quiz_table) { wp_send_json_error(['message' => 'Tabla de evaluaciones no encontrada.']); return; }

        $quiz_title = get_the_title($quiz_id) ?: 'Test #'.$quiz_id;

        // Detect course_id for this quiz (from existing attempts)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $course_id   = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT course_id FROM `{$quiz_table}` WHERE quiz_id = %d AND course_id > 0 LIMIT 1",
            $quiz_id
        ));
        $course_name = $course_id > 0 ? get_the_title($course_id) : null;

        $f    = $this->get_filters();
        $uids = $this->get_user_ids_for_filters($f);
        $ms   = $this->ms_table();
        $rows = [];

        // Strategy A: enrolled users in the course + their attempt (includes "Sin iniciar")
        if ($course_id > 0 && $ms) {
            $uw_uc = $this->uid_in($uids, 'uc.user_id');
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
            $rows = (array)$wpdb->get_results($wpdb->prepare(
                "SELECT u.ID AS user_id, u.display_name, u.user_email,
                        uq.user_quiz_id, uq.progress AS score, uq.status, uq.created_at
                 FROM `{$ms}` uc
                 INNER JOIN {$wpdb->users} u ON uc.user_id = u.ID
                 LEFT JOIN `{$quiz_table}` uq ON uq.user_id = uc.user_id AND uq.quiz_id = %d
                 WHERE uc.course_id = %d {$uw_uc}
                 ORDER BY u.display_name, uq.user_quiz_id ASC",
                $quiz_id, $course_id
            ));
        }

        // Strategy B fallback: only users who have a quiz record
        if (empty($rows)) {
            $uw_uq = $this->uid_in($uids, 'uq.user_id');
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
            $rows = (array)$wpdb->get_results($wpdb->prepare(
                "SELECT u.ID AS user_id, u.display_name, u.user_email,
                        uq.user_quiz_id, uq.progress AS score, uq.status, uq.created_at
                 FROM `{$quiz_table}` uq
                 INNER JOIN {$wpdb->users} u ON uq.user_id = u.ID
                 WHERE uq.quiz_id = %d {$uw_uq}
                 ORDER BY u.display_name",
                $quiz_id
            ));
        }

        // Fetch timing data from user_quizzes_times — latest attempt per user for this quiz
        $time_map   = [];
        $times_table = null;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix.'stm_lms_user_quizzes_times')) === $wpdb->prefix.'stm_lms_user_quizzes_times')
            $times_table = $wpdb->prefix.'stm_lms_user_quizzes_times';
        if ($times_table) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $time_rows = (array)$wpdb->get_results($wpdb->prepare(
                "SELECT user_id, start_time, end_time
                 FROM `{$times_table}`
                 WHERE quiz_id = %d
                 ORDER BY user_quiz_time_id DESC",
                $quiz_id
            ));
            foreach ($time_rows as $tr) {
                // Keep only the latest row per user (DESC order, first occurrence wins)
                if (!isset($time_map[(int)$tr->user_id]))
                    $time_map[(int)$tr->user_id] = $tr;
            }
        }

        // Map: user_id => max(user_quiz_id) — timing only applies to the latest attempt
        $latest_attempt = [];
        foreach ($rows as $r) {
            $uid  = (int) $r->user_id;
            $uqid = (int) ( $r->user_quiz_id ?? 0 );
            if ( $uqid > ( $latest_attempt[ $uid ] ?? 0 ) ) {
                $latest_attempt[ $uid ] = $uqid;
            }
        }

        // Compute stats
        $total = count($rows); $approved = 0; $failed = 0; $pending = 0;
        foreach ($rows as $r) {
            $st = strtolower($r->status ?? '');
            if (in_array($st, ['passed','completed'], true))      $approved++;
            elseif (in_array($st, ['failed','not_passed'], true)) $failed++;
            else                                                   $pending++;
        }
        $pct = $total > 0 ? round($approved / $total * 100) : 0;

        $back_ico = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><polyline points="15 18 9 12 15 6"/></svg>';
        $exp_ico  = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
        $exp_url  = $this->build_export_url('tests_detail_'.$quiz_id, $f);

        // Navigation bar
        $html  = '<div style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">';
        $html .= '<button id="fplms-test-back-btn" class="button">'.$back_ico.'Volver al listado</button>';
        $html .= '<a href="'.esc_url($exp_url).'" class="button button-primary fplms-rpt-export-btn" target="_blank">'.$exp_ico.'Exportar en Excel</a>';
        $html .= '</div>';

        // Quiz info card
        $html .= '<div style="background:#fff8ee;border-left:4px solid #ffa800;padding:12px 16px;margin-bottom:18px;border-radius:0 6px 6px 0;">';
        $html .= '<strong style="font-size:15px">'.esc_html($quiz_title).'</strong>';
        if ($course_name) $html .= '<span style="color:#666;margin-left:10px;font-size:13px">'.esc_html($course_name).'</span>';
        $html .= '<div style="margin-top:6px;font-size:13px;color:#555">';
        $html .= '<strong>'.$total.'</strong> usuarios asignados &mdash; ';
        $html .= '<span style="color:#22c55e;font-weight:600">'.$approved.' aprobados ('.$pct.'%)</span> &mdash; ';
        $html .= '<span style="color:#ef4444;font-weight:600">'.$failed.' reprobados</span>';
        if ($pending > 0) $html .= ' &mdash; <span style="color:#888">'.$pending.' sin iniciar</span>';
        $html .= '</div></div>';

        if (empty($rows)) {
            $html .= $this->empty_msg('Sin resultados para esta evaluación.');
            wp_send_json_success(['html' => $html]);
            return;
        }

        // Active filter context strip
        $fp = [];
        if (!empty($f['user_ids'])) {
            $names = array_map(fn($id) => get_userdata($id)?->display_name ?? '#'.$id, array_values($f['user_ids']));
            $fp[] = 'Usuario: '.esc_html(implode(', ', $names));
        }
        if ($f['channel_id']) { $term=get_term($f['channel_id']); if ($term&&!is_wp_error($term)) $fp[]='Canal: '.esc_html($term->name); }
        if ($f['city_id'])    { $term=get_term($f['city_id']);    if ($term&&!is_wp_error($term)) $fp[]='Ciudad: '.esc_html($term->name); }
        if ($f['company_id']) { $term=get_term($f['company_id']); if ($term&&!is_wp_error($term)) $fp[]='Empresa: '.esc_html($term->name); }
        if ($f['role_id'])    { $term=get_term($f['role_id']);    if ($term&&!is_wp_error($term)) $fp[]='Cargo: '.esc_html($term->name); }
        if ($f['date_from'])  $fp[] = 'Desde: '.esc_html($f['date_from']);
        if ($f['date_to'])    $fp[] = 'Hasta: '.esc_html($f['date_to']);
        if (!empty($fp)) {
            $html .= '<div class="fplms-filter-ctx">'
                   . '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>'
                   . '<span>Resultados filtrados por: '.implode(' &nbsp;&middot;&nbsp; ', $fp).'</span></div>';
        }

        // Build pie chart + table in two-column layout
        $pie = $this->render_pie_chart([
            ['label' => 'Aprobados',   'value' => $approved, 'color' => '#22c55e'],
            ['label' => 'Reprobados',  'value' => $failed,   'color' => '#ef4444'],
            ['label' => 'Sin iniciar', 'value' => $pending,  'color' => '#94a3b8'],
        ]);

        $can_reset = current_user_can('manage_options');
        $reset_ico = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:3px"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.39"/></svg>';
        $view_ico  = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:3px"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';

        // Compute sequential attempt_number per user (ordered by user_quiz_id ASC = same order as user_answers.attempt_number)
        $user_attempt_seq = [];
        foreach ($rows as &$r) {
            $uid = (int)$r->user_id;
            if (!isset($user_attempt_seq[$uid])) $user_attempt_seq[$uid] = 0;
            if (!empty($r->user_quiz_id)) $user_attempt_seq[$uid]++;
            $r->attempt_number = !empty($r->user_quiz_id) ? $user_attempt_seq[$uid] : null;
        }
        unset($r);

        $table  = $this->table_open(['Usuario', 'Fecha de Realización', 'Duración', 'Resultado', 'Puntuación', 'Opciones']);
        foreach ($rows as $r) {
            $st       = strtolower($r->status ?? '');
            $cl       = in_array($st, ['passed','completed'], true) ? 'green' : (in_array($st, ['failed','not_passed'], true) ? 'red' : 'yellow');
            $label    = in_array($st, ['passed','completed'], true) ? 'APROBADO' : (in_array($st, ['failed','not_passed'], true) ? 'REPROBADO' : 'SIN INICIAR');
            $uid_key   = (int) $r->user_id;
            $is_latest = ( (int)( $r->user_quiz_id ?? 0 ) === ( $latest_attempt[ $uid_key ] ?? -1 ) );
            $qt        = $is_latest ? ( $time_map[ $uid_key ] ?? null ) : null;
            $start_ts  = $qt ? (int)$qt->start_time : 0;
            $end_ts    = $qt ? (int)$qt->end_time   : 0;
            // Fecha: prefer end_time from times table (latest attempt only), fallback to created_at
            $fecha     = $end_ts > 0
                ? wp_date('d/m/Y, H:i:s', $end_ts)
                : (!empty($r->created_at) ? wp_date('d/m/Y, H:i:s', strtotime($r->created_at)) : '—');
            // Duración: solo para el intento más reciente, y solo si tenemos ambos timestamps
            $dur       = ($is_latest && $end_ts > 0 && $start_ts > 0 && $end_ts > $start_ts)
                ? $this->format_duration($end_ts - $start_ts)
                : '—';
            $score    = $r->score !== null ? number_format((float)$r->score, 2).'%' : '—';
            $opts = '';
            if (!empty($r->attempt_number)) {
                $opts .= '<button class="button button-small fplms-test-answers-btn" data-quiz-id="'.esc_attr($quiz_id).'" data-user-id="'.esc_attr($r->user_id).'" data-attempt="'.esc_attr($r->attempt_number).'" data-user-name="'.esc_attr($r->display_name).'" style="color:#0070c0;margin-right:4px">'.$view_ico.'Ver Resp.</button>';
            }
            $opts .= ($can_reset && !empty($r->user_quiz_id))
                ? '<button class="button button-small fplms-test-reset-btn" data-quiz-id="'.esc_attr($quiz_id).'" data-user-id="'.esc_attr($r->user_id).'" data-user-quiz-id="'.esc_attr($r->user_quiz_id).'" data-attempt="'.esc_attr($r->attempt_number ?? 1).'" data-user-name="'.esc_attr($r->display_name).'" style="color:#c00">'.$reset_ico.'Resetear</button>'
                : '';
            $table .= '<tr>'
                    . '<td><strong>'.esc_html($r->display_name).'</strong><br><small style="color:#888">'.esc_html($r->user_email).'</small></td>'
                    . '<td style="white-space:nowrap">'.esc_html($fecha).'</td>'
                    . '<td style="white-space:nowrap">'.esc_html($dur).'</td>'
                    . '<td>'.$this->badge($label, $cl).'</td>'
                    . '<td>'.esc_html($score).'</td>'
                    . '<td>'.$opts.'</td></tr>';
        }
        $table .= $this->table_close();

        // Two-column layout: table (left) + pie chart (right)
        $html .= '<div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap">';
        $html .= '<div style="flex:1;min-width:300px;overflow-x:auto">'.$table.'</div>';
        $html .= '<div style="flex-shrink:0">'.$pie.'</div>';
        $html .= '</div>';

        wp_send_json_success(['html' => $html, 'quiz_id' => $quiz_id]);
    }

    // TEST REPORT — RESET a user's quiz attempt
    public function ajax_report_test_reset(): void {
        if (!check_ajax_referer('fplms_reports_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce inválido.'], 403); return;
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sin permisos para resetear evaluaciones.'], 403); return;
        }
        global $wpdb;
        $quiz_id      = isset($_POST['quiz_id'])       ? (int)$_POST['quiz_id']       : 0;
        $user_id      = isset($_POST['user_id'])       ? (int)$_POST['user_id']       : 0;
        $user_quiz_id = isset($_POST['user_quiz_id'])  ? (int)$_POST['user_quiz_id']  : 0;
        $attempt      = isset($_POST['attempt'])       ? (int)$_POST['attempt']       : 0;
        if (!$quiz_id || !$user_id || !$user_quiz_id) { wp_send_json_error(['message' => 'Parámetros inválidos.']); return; }

        $quiz_table = $wpdb->prefix.'stm_lms_user_quizzes';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $quiz_table)) !== $quiz_table) {
            wp_send_json_error(['message' => 'Tabla de evaluaciones no encontrada.']); return;
        }

        // Delete only this specific attempt row (identified by its PK user_quiz_id)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $deleted = $wpdb->delete($quiz_table, ['user_quiz_id' => $user_quiz_id, 'user_id' => $user_id, 'quiz_id' => $quiz_id], ['%d', '%d', '%d']);
        if (false === $deleted) {
            wp_send_json_error(['message' => 'Error al eliminar el intento: '.$wpdb->last_error]); return;
        }

        // Delete the answers for this specific attempt_number
        if ($attempt > 0) {
            $ans_tbl = $wpdb->prefix.'stm_lms_user_answers';
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $ans_tbl)) === $ans_tbl) {
                $wpdb->delete($ans_tbl, ['user_id' => $user_id, 'quiz_id' => $quiz_id, 'attempt_number' => $attempt], ['%d', '%d', '%d']);
            }
        }

        // Log to audit trail
        $student    = get_userdata($user_id);
        $quiz_title = get_the_title($quiz_id) ?: 'Quiz #'.$quiz_id;
        $logger = new FairPlay_LMS_Audit_Logger();
        $logger->log_action(
            'quiz_attempt_deleted',
            'quiz',
            $quiz_id,
            $quiz_title,
            'Intento #'.$attempt.' de '.($student ? $student->display_name.' ('.$student->user_email.')' : 'user_id='.$user_id),
            'Eliminado por administrador'
        );

        wp_send_json_success(['message' => 'Intento eliminado correctamente. El estudiante puede volver a realizar la evaluación.', 'deleted' => (int)$deleted]);
    }

    // TEST REPORT — VIEW per-attempt questions & answers
    public function ajax_report_test_answers(): void {
        if (!$this->verify_request()) return;
        global $wpdb;
        $quiz_id = isset($_POST['quiz_id'])        ? (int)$_POST['quiz_id']        : 0;
        $user_id = isset($_POST['user_id'])        ? (int)$_POST['user_id']        : 0;
        $attempt = isset($_POST['attempt_number']) ? (int)$_POST['attempt_number'] : 1;
        if (!$quiz_id || !$user_id) { wp_send_json_error(['message' => 'Parámetros inválidos.']); return; }

        $ans_tbl = $wpdb->prefix.'stm_lms_user_answers';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $ans_tbl)) !== $ans_tbl) {
            wp_send_json_error(['message' => 'Tabla de respuestas no encontrada.']); return;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $answers = (array)$wpdb->get_results($wpdb->prepare(
            "SELECT question_id, user_answer, correct_answer
             FROM `{$ans_tbl}`
             WHERE user_id = %d AND quiz_id = %d AND attempt_number = %d
             ORDER BY user_answer_id ASC",
            $user_id, $quiz_id, $attempt
        ));

        if (empty($answers)) {
            wp_send_json_success(['html' => '<p style="color:#888;padding:12px 0">No hay respuestas registradas para este intento.</p>']);
            return;
        }

        $html  = '<table class="widefat striped fplms-rpt-table">';
        $html .= '<thead><tr><th>#</th><th>Pregunta</th><th>Respuesta del usuario</th><th>Resultado</th></tr></thead><tbody>';
        foreach ($answers as $i => $a) {
            $q_title = get_the_title((int)$a->question_id) ?: 'Pregunta #'.(int)$a->question_id;
            $correct  = (bool)$a->correct_answer;
            $html .= '<tr>'
                   . '<td style="width:32px;color:#aaa;font-size:11px">'.($i+1).'</td>'
                   . '<td>'.esc_html($q_title).'</td>'
                   . '<td>'.esc_html($a->user_answer ?: '—').'</td>'
                   . '<td>'.($correct ? $this->badge('Correcta','green') : $this->badge('Incorrecta','red')).'</td>'
                   . '</tr>';
        }
        $html .= '</tbody></table>';

        $total   = count($answers);
        $correct = count(array_filter($answers, fn($a) => (bool)$a->correct_answer));
        $pct     = $total > 0 ? round($correct / $total * 100) : 0;
        $summary = '<p style="margin:0 0 12px;font-size:13px;color:#555">'
                 . '<strong>'.$correct.'</strong> de <strong>'.$total.'</strong> preguntas correctas &mdash; '
                 . $this->badge($pct.'%', $pct >= 70 ? 'green' : ($pct >= 50 ? 'yellow' : 'red'))
                 . '</p>';

        wp_send_json_success(['html' => $summary.$html]);
    }

    // REPORT — MATRIZ DE FORMACION
    public function ajax_report_matrix(): void {
        if (!$this->verify_request()) return;
        global $wpdb;
        $f    = $this->get_filters();
        $uids = $this->get_user_ids_for_filters($f);
        $ms   = $this->ms_table();

        $search   = isset($_POST['matrix_search'])   ? sanitize_text_field(wp_unslash($_POST['matrix_search']))   : '';
        $page     = isset($_POST['matrix_page'])     ? max(1, (int)$_POST['matrix_page'])                         : 1;
        $per_page = isset($_POST['matrix_per_page']) ? (int)$_POST['matrix_per_page']                              : 10;
        if (!in_array($per_page, [10, 20, 50, 100], true)) $per_page = 10;

        $html = $this->render_section_head('Matriz de Formacion', $this->build_export_url('matrix', $f));

        if (!$ms) {
            $html .= $this->empty_msg('Tabla de matriculas MasterStudy no encontrada.');
            wp_send_json_success(['html' => $html]); return;
        }

        // 1. All published courses (max 200)
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $courses = (array)$wpdb->get_results(
            "SELECT ID, post_title FROM {$wpdb->posts}
             WHERE post_type='stm-courses' AND post_status='publish'
             ORDER BY post_title LIMIT 200"
        );
        if (empty($courses)) {
            $html .= $this->empty_msg('No se encontraron cursos publicados.');
            wp_send_json_success(['html' => $html]); return;
        }

        // 2. User WHERE from global filters
        $uid_where = null !== $uids
            ? (empty($uids) ? 'AND 1=0' : 'AND u.ID IN (' . implode(',', array_map('intval', $uids)) . ')')
            : '';

        // 3. Matrix search filter (name / email / login)
        $search_where = '';
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $search_where = $wpdb->prepare(
                'AND (u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s)',
                $like, $like, $like
            );
        }

        // 4. Total count
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $total  = (int)$wpdb->get_var("SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u WHERE 1=1 $uid_where $search_where");
        $pages  = max(1, (int)ceil($total / $per_page));
        if ($page > $pages) $page = $pages;
        $offset = ($page - 1) * $per_page;

        // 5. Paginated users
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $users = (array)$wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.display_name, u.user_email
             FROM {$wpdb->users} u
             WHERE 1=1 $uid_where $search_where
             ORDER BY u.display_name LIMIT %d OFFSET %d",
            $per_page, $offset
        ));

        // 6. Enrollment map for current page users
        $enr_map = [];
        if (!empty($users)) {
            $uid_str = implode(',', array_map(fn($u) => (int)$u->ID, $users));
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $enrs = (array)$wpdb->get_results(
                "SELECT user_id, course_id, progress_percent, status FROM `{$ms}` WHERE user_id IN ($uid_str)"
            );
            foreach ($enrs as $e) {
                $enr_map[(int)$e->user_id][(int)$e->course_id] = [
                    'progress' => (float)$e->progress_percent,
                    'status'   => strtolower($e->status ?? ''),
                ];
            }
        }

        // 7. Icons
        $check_ico = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:block;margin:0 auto"><polyline points="20 6 9 17 4 12"/></svg>';
        $dot_ico   = '<svg viewBox="0 0 24 24" width="10" height="10" style="display:block;margin:0 auto"><circle cx="12" cy="12" r="6" fill="#fff"/></svg>';

        // 8. Legend
        $html .= '<div class="fplms-mx-legend">'
               . '<span class="fplms-mx-leg-item"><span class="fplms-mx-leg-dot fplms-mx-done">'.$check_ico.'</span>Completado</span>'
               . '<span class="fplms-mx-leg-item"><span class="fplms-mx-leg-dot fplms-mx-progress"></span>En Progreso</span>'
               . '<span class="fplms-mx-leg-item"><span class="fplms-mx-leg-dot fplms-mx-assigned">'.$dot_ico.'</span>Asignado (Sin iniciar)</span>'
               . '<span class="fplms-mx-leg-item"><span class="fplms-mx-leg-dot fplms-mx-empty-dot"></span>No inscrito</span>'
               . '</div>';

        // 9. Search + per-page controls
        $pp_opts = implode('', array_map(fn($v) => '<option value="'.$v.'"'.($per_page===$v?' selected':'').'>'.$v.'</option>', [10,20,50,100]));
        $html .= '<div class="fplms-mx-controls">'
               . '<input type="text" id="fplms-mx-search" class="fplms-mx-search-input" placeholder="Buscar por nombre, email o usuario..." value="'.esc_attr($search).'" autocomplete="off">'
               . '<label class="fplms-mx-per-page-label">Mostrar: <select id="fplms-mx-per-page" class="fplms-mx-per-page">'.$pp_opts.'</select></label>'
               . '</div>';

        // 10. Matrix table
        $html .= '<div class="fplms-mx-table-wrap"><table class="fplms-mx-table widefat"><thead><tr>';
        $html .= '<th class="fplms-mx-th-user">Usuario</th>';
        $html .= '<th class="fplms-mx-th-email">Email</th>';
        foreach ($courses as $c) {
            $short = mb_strlen($c->post_title) > 26 ? mb_substr($c->post_title, 0, 24).'…' : $c->post_title;
            $html .= '<th class="fplms-mx-th-course" title="'.esc_attr($c->post_title).'">'
                   . '<div class="fplms-mx-th-inner">'.esc_html($short).'</div></th>';
        }
        $html .= '</tr></thead><tbody>';

        if (empty($users)) {
            $cols = count($courses) + 2;
            $html .= '<tr><td colspan="'.$cols.'" style="text-align:center;color:#aaa;padding:28px 0">No se encontraron estudiantes para los filtros aplicados.</td></tr>';
        } else {
            foreach ($users as $u) {
                $uid  = (int)$u->ID;
                $html .= '<tr>';
                $html .= '<td class="fplms-mx-td-user"><strong>'.esc_html($u->display_name).'</strong></td>';
                $html .= '<td class="fplms-mx-td-email"><small>'.esc_html($u->user_email).'</small></td>';
                foreach ($courses as $c) {
                    $cid = (int)$c->ID;
                    $enr = $enr_map[$uid][$cid] ?? null;
                    if ($enr === null) {
                        $html .= '<td class="fplms-mx-cell fplms-mx-empty" title="No inscrito"></td>';
                    } elseif (in_array($enr['status'], ['completed','passed'], true) || $enr['progress'] >= 100) {
                        $html .= '<td class="fplms-mx-cell fplms-mx-done" title="Completado">'.$check_ico.'</td>';
                    } elseif ($enr['progress'] > 0) {
                        $pct  = round($enr['progress']);
                        $html .= '<td class="fplms-mx-cell fplms-mx-progress" title="En progreso ('.$pct.'%)"><small>'.$pct.'%</small></td>';
                    } else {
                        $html .= '<td class="fplms-mx-cell fplms-mx-assigned" title="Asignado — Sin iniciar">'.$dot_ico.'</td>';
                    }
                }
                $html .= '</tr>';
            }
        }
        $html .= '</tbody></table></div>';

        // 11. Pagination
        $html .= '<div class="fplms-mx-pagination">'
               . '<span class="fplms-mx-pag-info">'.number_format($total).' estudiantes — pág. '.$page.' / '.$pages.'</span>'
               . '<button type="button" class="button button-small fplms-mx-prev"'.($page<=1?' disabled':'').'>&#8249; Anterior</button>'
               . '<button type="button" class="button button-small fplms-mx-next"'.($page>=$pages?' disabled':'').'>Siguiente &#8250;</button>'
               . '</div>';

        wp_send_json_success(['html' => $html]);
    }

    private function render_pie_chart(array $slices, int $size = 184): string {
        $total = (int)array_sum(array_column($slices, 'value'));
        if ($total <= 0) {
            return '<div style="padding:18px 20px;background:#fff;border:1px solid #eee;border-radius:8px;min-width:195px;text-align:center;color:#bbb;font-size:12px">Sin datos disponibles</div>';
        }
        $cx = $size / 2; $cy = $size / 2;
        $r  = ($size / 2) - 10;
        $ir = $r * 0.50; // inner radius (donut)

        $non_empty = array_values(array_filter($slices, fn($s) => (int)$s['value'] > 0));
        $paths = '';

        if (count($non_empty) === 1) {
            // Full circle for single slice
            $paths .= '<circle cx="'.round($cx,1).'" cy="'.round($cy,1).'" r="'.round($r,1).'" fill="'.esc_attr($non_empty[0]['color']).'"/>';
        } else {
            $angle = -M_PI / 2; // start at 12 o'clock
            foreach ($slices as $sl) {
                $val = (int)$sl['value'];
                if ($val <= 0) continue;
                $sweep = 2 * M_PI * $val / $total;
                $x1 = $cx + $r * cos($angle);
                $y1 = $cy + $r * sin($angle);
                $angle += $sweep;
                $x2 = $cx + $r * cos($angle);
                $y2 = $cy + $r * sin($angle);
                $large = $sweep > M_PI ? 1 : 0;
                $paths .= '<path d="M'.round($cx,1).','.round($cy,1)
                        . ' L'.round($x1,2).','.round($y1,2)
                        . ' A'.round($r,1).','.round($r,1).' 0 '.$large.',1 '.round($x2,2).','.round($y2,2)
                        . ' Z" fill="'.esc_attr($sl['color']).'"/>';
            }
        }

        // Donut hole + center label
        $paths .= '<circle cx="'.round($cx,1).'" cy="'.round($cy,1).'" r="'.round($ir,1).'" fill="#fff"/>';
        $paths .= '<text x="'.round($cx,1).'" y="'.round($cy - 5, 1).'" text-anchor="middle" dominant-baseline="middle" font-size="22" font-weight="700" fill="#333">'.$total.'</text>';
        $paths .= '<text x="'.round($cx,1).'" y="'.round($cy + 15, 1).'" text-anchor="middle" font-size="9" fill="#999" letter-spacing="0.5">TOTAL</text>';

        $svg = '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 '.$size.' '.$size.'" style="display:block;margin:0 auto">'.$paths.'</svg>';

        $legend = '';
        foreach ($slices as $sl) {
            $val = (int)$sl['value'];
            $pct = round($val / $total * 100);
            $legend .= '<div style="display:flex;align-items:center;gap:7px;margin-bottom:7px;font-size:12px">'
                     . '<span style="width:12px;height:12px;border-radius:3px;background:'.esc_attr($sl['color']).';flex-shrink:0;display:inline-block"></span>'
                     . '<span style="color:#555;flex:1">'.esc_html($sl['label']).'</span>'
                     . '<strong style="color:#333;min-width:22px;text-align:right">'.$val.'</strong>'
                     . '<span style="color:#aaa;min-width:40px">('.$pct.'%)</span>'
                     . '</div>';
        }

        return '<div style="padding:18px 20px;background:#fff;border:1px solid #eee;border-radius:8px;min-width:195px;box-shadow:0 1px 4px rgba(0,0,0,.06)">'
             . '<div style="font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px;text-align:center">Distribucion de Resultados</div>'
             . $svg
             . '<div style="margin-top:14px">'.$legend.'</div>'
             . '</div>';
    }

    // HELPER — Format seconds as "Xm Ys"
    private function format_duration(int $seconds): string {
        if ($seconds <= 0) return '—';
        $m = intdiv($seconds, 60); $s = $seconds % 60;
        return $m > 0 ? $m.'m '.$s.'s' : $s.'s';
    }

    // CSV EXPORT
    public function handle_export(): void {
        if (!is_admin()) return;
        if (!isset($_GET['page'])||'fplms-reports'!==$_GET['page']) return;
        if (!isset($_GET['fplms_export'])) return;
        if (!current_user_can(FairPlay_LMS_Config::CAP_VIEW_REPORTS)) return;
        if (!isset($_GET['_wpnonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])),'fplms_export_nonce')) return;
        $fmt=sanitize_text_field(wp_unslash($_GET['fplms_export']));
        $raw_uids = isset($_GET['user_ids']) ? sanitize_text_field(wp_unslash($_GET['user_ids'])) : '';
        $parsed_uids = '' !== $raw_uids
            ? array_values(array_filter(array_map('intval', explode(',', $raw_uids))))
            : [];
        $f=[
            'date_from'  => isset($_GET['date_from'])  ? sanitize_text_field(wp_unslash($_GET['date_from']))  : '',
            'date_to'    => isset($_GET['date_to'])    ? sanitize_text_field(wp_unslash($_GET['date_to']))    : '',
            'channel_id' => isset($_GET['channel_id']) ? (int)$_GET['channel_id'] : 0,
            'city_id'    => isset($_GET['city_id'])    ? (int)$_GET['city_id']    : 0,
            'company_id' => isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0,
            'role_id'    => isset($_GET['role_id'])    ? (int)$_GET['role_id']    : 0,
            'user_ids'   => $parsed_uids,
        ];
        $this->dispatch_xls_export($fmt,$f);
    }

    private function dispatch_xls_export(string $format, array $f): void {
        global $wpdb;
        $uids = $this->get_user_ids_for_filters($f);
        $ms   = $this->ms_table();
        $mw   = null !== $uids ? (empty($uids) ? 'AND uc.user_id=0' : 'AND uc.user_id IN (' . implode(',', array_map('intval', $uids)) . ')') : '';
        $uw   = $this->uid_in($uids);
        $u_in = null !== $uids ? (empty($uids) ? 'AND 1=0' : 'AND u.ID IN (' . implode(',', array_map('intval', $uids)) . ')') : '';

        switch (true) {

            // -- PARTICIPACION ----------------------------------------------
            case str_starts_with($format, 'participation'):
                $dw  = $this->date_sql('u.user_registered', $f['date_from'], $f['date_to']);
                $ldw = $this->date_sql('l.login_time', $f['date_from'], $f['date_to']);
                $lw  = null !== $uids ? (empty($uids) ? 'AND l.user_id=0' : 'AND l.user_id IN (' . implode(',', array_map('intval', $uids)) . ')') : '';

                // 1A – Usuarios registrados (date filter on registration)
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $reg = (array) $wpdb->get_results(
                    "SELECT u.display_name,u.user_email,u.user_registered,
                            um_ci.meta_value AS city_id,um_ch.meta_value AS channel_id,
                            um_st.meta_value AS user_status
                     FROM {$wpdb->users} u
                     LEFT JOIN {$wpdb->usermeta} um_ci ON u.ID=um_ci.user_id AND um_ci.meta_key='fplms_city'
                     LEFT JOIN {$wpdb->usermeta} um_ch ON u.ID=um_ch.user_id AND um_ch.meta_key='fplms_channel'
                     LEFT JOIN {$wpdb->usermeta} um_st ON u.ID=um_st.user_id AND um_st.meta_key='fplms_user_status'
                     WHERE 1=1 $uw $dw ORDER BY u.user_registered DESC LIMIT 5000"
                );

                // 1B – Login frequency (date filter on login_time AND activity_time)
                $login_table  = $wpdb->prefix . 'fplms_user_logins';
                $login_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $login_table)) === $login_table);
                $act_tbl_xls  = $wpdb->prefix . 'fplms_user_activity';
                $act_exists_xls = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $act_tbl_xls)) === $act_tbl_xls);
                $adw = $this->date_sql('a.activity_time', $f['date_from'], $f['date_to']);
                $logins = [];
                if ($login_exists) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    $logins = (array) $wpdb->get_results(
                        "SELECT u.display_name,u.user_email,COUNT(l.id) AS login_count,MAX(l.login_time) AS last_login"
                        . ($act_exists_xls ? ",COALESCE(ua.activity_count,0) AS activity_count" : ",0 AS activity_count")
                        . " FROM {$wpdb->users} u INNER JOIN `{$login_table}` l ON u.ID=l.user_id"
                        . ($act_exists_xls ? " LEFT JOIN (SELECT user_id,COUNT(*) AS activity_count FROM `{$act_tbl_xls}` a WHERE 1=1{$adw} GROUP BY user_id) ua ON u.ID=ua.user_id" : "")
                        . " WHERE 1=1 $lw $ldw GROUP BY u.ID ORDER BY login_count DESC LIMIT 5000"
                    );
                }

                // 1C – Abandonment (no date filter – all enrolled with 0 progress)
                $abandon = [];
                if ($ms) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    $abandon = (array) $wpdb->get_results(
                        "SELECT u.display_name,u.user_email,p.post_title AS course_name,
                                uc.progress_percent,uc.status
                         FROM `{$ms}` uc
                         INNER JOIN {$wpdb->users} u ON uc.user_id=u.ID
                         INNER JOIN {$wpdb->posts} p ON uc.course_id=p.ID
                         WHERE uc.progress_percent<1 AND uc.status NOT IN('completed','passed') $mw
                         ORDER BY u.display_name,p.post_title LIMIT 5000"
                    );
                }

                // 1D – Inactive / given-up users (no date filter)
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $inactive = (array) $wpdb->get_results(
                    "SELECT u.ID AS user_id,u.display_name,u.user_email,u.user_registered,
                            um_ci.meta_value AS city_id,um_ch.meta_value AS channel_id,
                            um_db.meta_value AS deactivated_by_id,
                            um_dd.meta_value AS deactivated_date
                     FROM {$wpdb->users} u
                     INNER JOIN {$wpdb->usermeta} um_st ON u.ID=um_st.user_id
                         AND um_st.meta_key='fplms_user_status' AND um_st.meta_value='inactive'
                     LEFT JOIN {$wpdb->usermeta} um_ci ON u.ID=um_ci.user_id AND um_ci.meta_key='fplms_city'
                     LEFT JOIN {$wpdb->usermeta} um_ch ON u.ID=um_ch.user_id AND um_ch.meta_key='fplms_channel'
                     LEFT JOIN {$wpdb->usermeta} um_db ON u.ID=um_db.user_id AND um_db.meta_key='fplms_deactivated_by'
                     LEFT JOIN {$wpdb->usermeta} um_dd ON u.ID=um_dd.user_id AND um_dd.meta_key='fplms_deactivated_date'
                     WHERE 1=1 $uw ORDER BY u.display_name LIMIT 5000"
                );
                // Resolve admin display names for deactivated_by_id
                $admin_names_xls = [];
                foreach ($inactive as $ir) {
                    $aid = (int)($ir->deactivated_by_id ?? 0);
                    if ($aid && !isset($admin_names_xls[$aid])) {
                        $au = get_userdata($aid);
                        $admin_names_xls[$aid] = $au ? $au->display_name : 'ID '.$aid;
                    }
                }

                $this->output_xls_multi('participacion', 'Reporte de Participacion', [
                    [
                        'title'   => 'Usuarios Registrados (' . count($reg) . ')',
                        'headers' => ['Nombre', 'Email', 'Fecha Registro', 'Ciudad', 'Canal', 'Estado'],
                        'data'    => array_map(fn($r) => [
                            $r->display_name,
                            $r->user_email,
                            wp_date('d/m/Y', strtotime($r->user_registered)),
                            $r->city_id    ? $this->structures->get_term_name_by_id((int)$r->city_id)    : '',
                            $r->channel_id ? $this->structures->get_term_name_by_id((int)$r->channel_id) : '',
                            empty($r->user_status) || 'active' === $r->user_status ? 'Activo' : 'Inactivo',
                        ], $reg),
                    ],
                    [
                        'title'   => 'Frecuencia de Acceso / N. de Actividades del Sistema',
                        'headers' => ['Nombre', 'Email', 'N. de Ingresos', 'Actividad', 'Ultimo Ingreso'],
                        'data'    => array_map(fn($r) => [
                            $r->display_name,
                            $r->user_email,
                            (int)$r->login_count,
                            (int)$r->activity_count,
                            $r->last_login ? wp_date('d/m/Y H:i', strtotime($r->last_login)) : '—',
                        ], $logins),
                    ],
                    [
                        'title'   => 'Tasa de Abandono — Inscritos sin Participacion o sin Completar (' . count($abandon) . ')',
                        'headers' => ['Usuario', 'Email', 'Curso', 'Progreso %', 'Estado'],
                        'data'    => array_map(fn($r) => [
                            $r->display_name,
                            $r->user_email,
                            $r->course_name,
                            $r->progress_percent . '%',
                            !$r->status || 'not_started' === $r->status ? 'No iniciado' : $r->status,
                        ], $abandon),
                    ],
                    [
                        'title'   => 'Usuarios Dados de Baja / Inactivos (' . count($inactive) . ')',
                        'headers' => ['Nombre', 'Email', 'Fecha Registro', 'Ciudad', 'Canal', 'Inactivado por', 'Fecha Inactivacion'],
                        'data'    => array_map(fn($r) => [
                            $r->display_name,
                            $r->user_email,
                            wp_date('d/m/Y', strtotime($r->user_registered)),
                            $r->city_id    ? $this->structures->get_term_name_by_id((int)$r->city_id)    : '',
                            $r->channel_id ? $this->structures->get_term_name_by_id((int)$r->channel_id) : '',
                            !empty($r->deactivated_by_id) ? ($admin_names_xls[(int)$r->deactivated_by_id] ?? 'ID '.(int)$r->deactivated_by_id) : '',
                            !empty($r->deactivated_date)  ? wp_date('d/m/Y H:i', strtotime($r->deactivated_date)) : '',
                        ], $inactive),
                    ],
                ], $f);
                break;

            // -- PROGRESO --------------------------------------------------
            case str_starts_with($format, 'progress'):
                if (!$ms) { echo 'Sin tabla.'; exit; }

                // 2A – Per-course aggregates
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $cstats = (array) $wpdb->get_results(
                    "SELECT p.post_title AS course_name,
                            COUNT(uc.user_id) AS enrolled,
                            ROUND(AVG(uc.progress_percent),1) AS avg_progress,
                            SUM(CASE WHEN uc.status IN('completed','passed') OR uc.progress_percent>=100 THEN 1 ELSE 0 END) AS completed,
                            SUM(CASE WHEN uc.progress_percent>0 AND uc.progress_percent<100 AND uc.status NOT IN('completed','passed') THEN 1 ELSE 0 END) AS in_progress,
                            SUM(CASE WHEN uc.progress_percent<1 AND uc.status NOT IN('completed','passed') THEN 1 ELSE 0 END) AS not_started
                     FROM {$wpdb->posts} p LEFT JOIN `{$ms}` uc ON p.ID=uc.course_id $mw
                     WHERE p.post_type='stm-courses' AND p.post_status='publish'
                     GROUP BY p.ID ORDER BY enrolled DESC, p.post_title"
                );

                // 2B – Top 10 abandono (computed from $cstats already fetched)
                $top10_sorted = array_values(array_filter($cstats, fn($r) => (int)$r->enrolled > 0));
                usort($top10_sorted, fn($a, $b) => (int)$b->not_started / max(1, (int)$b->enrolled) <=> (int)$a->not_started / max(1, (int)$a->enrolled));
                $top10_xls = array_slice($top10_sorted, 0, 10);

                // 2C – Per-user aggregate (matches HTML: total courses, avg progress, completed, pending)
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $uprg = (array) $wpdb->get_results(
                    "SELECT u.display_name,u.user_email,
                            COUNT(uc.course_id) AS total_courses,
                            ROUND(AVG(uc.progress_percent),1) AS avg_progress,
                            SUM(CASE WHEN uc.status IN('completed','passed') OR uc.progress_percent>=100 THEN 1 ELSE 0 END) AS completed
                     FROM {$wpdb->users} u INNER JOIN `{$ms}` uc ON u.ID=uc.user_id
                     WHERE 1=1 $u_in GROUP BY u.ID ORDER BY avg_progress DESC LIMIT 5000"
                );

                $this->output_xls_multi('progreso', 'Reporte de Progreso', [
                    [
                        'title'   => 'Porcentaje de Avance por Curso',
                        'headers' => ['Curso', 'Inscritos', 'Avance Prom. %', 'Completados', 'Tasa Finalizacion %', 'En Progreso', 'No Iniciados', 'Tasa Abandono %'],
                        'data'    => array_map(fn($r) => [
                            $r->course_name,
                            (int)$r->enrolled,
                            $r->avg_progress . '%',
                            (int)$r->completed,
                            (int)$r->enrolled > 0 ? round((int)$r->completed  / (int)$r->enrolled * 100, 1) . '%' : '0%',
                            (int)$r->in_progress,
                            (int)$r->not_started,
                            (int)$r->enrolled > 0 ? round((int)$r->not_started / (int)$r->enrolled * 100, 1) . '%' : '0%',
                        ], $cstats),
                    ],
                    [
                        'title'   => 'Capacitaciones con Mayor Abandono (Top 10)',
                        'headers' => ['Curso', 'Inscritos', 'Sin Participacion', 'Tasa Abandono'],
                        'data'    => array_map(fn($r) => [
                            $r->course_name,
                            (int)$r->enrolled,
                            (int)$r->not_started,
                            (int)$r->enrolled > 0 ? round((int)$r->not_started / (int)$r->enrolled * 100, 1) . '%' : '0%',
                        ], $top10_xls),
                    ],
                    [
                        'title'   => 'Progreso por Usuario',
                        'headers' => ['Usuario', 'Email', 'Cursos Asignados', 'Avance Promedio', 'Completados', 'Pendientes'],
                        'data'    => array_map(fn($r) => [
                            $r->display_name,
                            $r->user_email,
                            (int)$r->total_courses,
                            $r->avg_progress . '%',
                            (int)$r->completed,
                            (int)$r->total_courses - (int)$r->completed,
                        ], $uprg),
                    ],
                ], $f);
                break;

            // -- DESEMPENO ------------------------------------------------
            case str_starts_with($format, 'performance'):
                $quiz_table = null;
                if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'stm_lms_user_quizzes')) === $wpdb->prefix . 'stm_lms_user_quizzes') {
                    $quiz_table = $wpdb->prefix . 'stm_lms_user_quizzes';
                }
                $qw = null !== $uids ? (empty($uids) ? 'AND uq.user_id=0' : 'AND uq.user_id IN (' . implode(',', array_map('intval', $uids)) . ')') : '';
                $quizzes = [];
                if ($quiz_table) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    $quizzes = (array) $wpdb->get_results(
                        "SELECT uq.user_id,uq.course_id,uq.quiz_id,uq.user_quiz_id,
                                uq.progress AS score,uq.status,
                                u.display_name,u.user_email,
                                pc.post_title AS course_name,q.post_title AS quiz_name
                         FROM `{$quiz_table}` uq
                         INNER JOIN {$wpdb->users} u ON uq.user_id=u.ID
                         LEFT JOIN {$wpdb->posts} q  ON uq.quiz_id=q.ID
                         LEFT JOIN {$wpdb->posts} pc ON uq.course_id=pc.ID
                         WHERE 1=1 $qw ORDER BY uq.user_id,uq.quiz_id,uq.course_id,uq.user_quiz_id ASC LIMIT 5000"
                    );
                    $quizzes = $this->aggregate_quiz_rows($quizzes);
                }
                $expired = [];
                if ($ms) {
                    $today = wp_date('Y-m-d');
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    $expired = (array) $wpdb->get_results($wpdb->prepare(
                        "SELECT u.display_name,u.user_email,p.post_title AS course_name,
                                uc.progress_percent,pm.meta_value AS end_days,
                                DATE_ADD(p.post_date,INTERVAL pm.meta_value DAY) AS expiry_date
                         FROM {$wpdb->posts} p
                         INNER JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id AND pm.meta_key='end_time' AND pm.meta_value>0
                         INNER JOIN `{$ms}` uc ON p.ID=uc.course_id
                         INNER JOIN {$wpdb->users} u ON uc.user_id=u.ID
                         WHERE p.post_type='stm-courses'
                           AND DATE_ADD(p.post_date,INTERVAL pm.meta_value DAY)<%s
                           AND uc.status NOT IN('completed','passed') AND uc.progress_percent<100 $mw
                         ORDER BY expiry_date ASC LIMIT 5000",
                        $today
                    ));
                }
                $this->output_xls_multi('desempeno', 'Reporte de Desempeno', [
                    [
                        'title'   => 'Resultados de Evaluaciones (' . count($quizzes) . ' estudiantes)',
                        'headers' => ['Usuario', 'Email', 'Curso', 'Evaluacion', 'Intentos', 'Puntaje', 'Estado'],
                        'data'    => array_map(fn($r) => [
                            $r->display_name,
                            $r->user_email,
                            $r->course_name ?? '—',
                            $r->quiz_name   ?? '—',
                            (int)$r->attempts_label === 1 ? '1 intento' : (int)$r->attempts_label . ' intentos',
                            $r->score,
                            $this->translate_status($r->status ?? ''),
                        ], $quizzes),
                    ],
                    [
                        'title'   => 'Capacitaciones Vencidas sin Completar (' . count($expired) . ')',
                        'headers' => ['Usuario', 'Email', 'Curso', 'Progreso %', 'Dias Vigencia', 'Fecha Vencimiento'],
                        'data'    => array_map(fn($r) => [
                            $r->display_name, $r->user_email, $r->course_name,
                            $r->progress_percent . '%',
                            (int)$r->end_days . ' dias',
                            wp_date('d/m/Y', strtotime($r->expiry_date)),
                        ], $expired),
                    ],
                ], $f);
                break;

            // -- CERTIFICADOS ----------------------------------------------
            case str_starts_with($format, 'certificates'):
                if (!$ms) { echo 'Sin datos.'; exit; }
                $uw_xls = null !== $uids ? (empty($uids) ? 'AND uc.user_id=0' : 'AND uc.user_id IN (' . implode(',', array_map('intval', $uids)) . ')') : '';
                $dw_xls = '';
                if (!empty($f['date_from'])) $dw_xls .= $wpdb->prepare(' AND FROM_UNIXTIME(uc.end_time) >= %s', $f['date_from'] . ' 00:00:00');
                if (!empty($f['date_to']))   $dw_xls .= $wpdb->prepare(' AND FROM_UNIXTIME(uc.end_time) <= %s', $f['date_to']   . ' 23:59:59');
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $cert_rows = (array) $wpdb->get_results(
                    "SELECT u.ID AS user_id, u.display_name, u.user_email,
                            p.ID AS course_id, p.post_title AS course_name,
                            uc.end_time,
                            cp.post_title AS template_name
                     FROM `{$ms}` uc
                     INNER JOIN {$wpdb->users} u ON uc.user_id=u.ID
                     INNER JOIN {$wpdb->posts} p ON uc.course_id=p.ID AND p.post_status='publish'
                     INNER JOIN {$wpdb->postmeta} pm_cert ON p.ID=pm_cert.post_id
                         AND pm_cert.meta_key='course_certificate'
                         AND pm_cert.meta_value REGEXP '^[0-9]+$'
                     LEFT JOIN {$wpdb->posts} cp ON pm_cert.meta_value=cp.ID
                     WHERE (uc.status IN('completed','passed') OR uc.progress_percent>=100)
                       AND uc.end_time>0
                       $uw_xls $dw_xls
                     ORDER BY u.display_name, p.post_title LIMIT 5000"
                );
                // By-course summary for XLS
                $by_course_xls2 = [];
                foreach ($cert_rows as $r) {
                    $k = (int)$r->course_id;
                    if (!isset($by_course_xls2[$k])) $by_course_xls2[$k] = ['name'=>$r->course_name,'template'=>$r->template_name??'—','count'=>0];
                    $by_course_xls2[$k]['count']++;
                }
                usort($by_course_xls2, fn($a,$b) => $b['count'] <=> $a['count']);
                $this->output_xls_multi('certificados', 'Reporte de Certificacion', [
                    [
                        'title'   => 'Por Curso (' . count($by_course_xls2) . ')',
                        'headers' => ['Curso', 'Plantilla Certificado', 'Estudiantes con Certificado'],
                        'data'    => array_map(fn($c) => [
                            $c['name'], $c['template'], $c['count'],
                        ], $by_course_xls2),
                    ],
                    [
                        'title'   => 'Detalle Estudiantes (' . count($cert_rows) . ')',
                        'headers' => ['Estudiante', 'Email', 'Curso', 'Fecha Finalizacion', 'URL Certificado'],
                        'data'    => array_map(fn($r) => [
                            $r->display_name,
                            $r->user_email,
                            $r->course_name,
                            $r->end_time > 0 ? wp_date('d/m/Y', (int)$r->end_time) : '—',
                            home_url('/certificate/' . (int)$r->course_id . '/' . (int)$r->user_id . '/'),
                        ], $cert_rows),
                    ],
                ], $f);
                break;

            // -- TIEMPO ---------------------------------------------------
            case str_starts_with($format, 'time'):
                if (!$ms) { echo 'Sin datos.'; exit; }
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $trows = (array) $wpdb->get_results(
                    "SELECT u.display_name,u.user_email,p.post_title AS course,
                            COALESCE(CAST(pm_vd.meta_value AS DECIMAL(10,2)),0) AS vh,
                            COALESCE(CAST(pm_di.meta_value AS DECIMAL(10,2)),0) AS dh
                     FROM {$wpdb->users} u
                     INNER JOIN `{$ms}` uc ON u.ID=uc.user_id
                     INNER JOIN {$wpdb->posts} p ON uc.course_id=p.ID AND p.post_status='publish'
                     LEFT JOIN {$wpdb->postmeta} pm_vd ON p.ID=pm_vd.post_id AND pm_vd.meta_key='video_duration'
                     LEFT JOIN {$wpdb->postmeta} pm_di ON p.ID=pm_di.post_id AND pm_di.meta_key='duration_info'
                     WHERE (uc.status IN('completed','passed') OR uc.progress_percent>=100) $mw
                     ORDER BY u.display_name LIMIT 5000"
                );
                $by = [];
                foreach ($trows as $r) {
                    $k = $r->display_name . '|' . $r->user_email;
                    if (!isset($by[$k])) $by[$k] = ['name' => $r->display_name, 'email' => $r->user_email, 'hours' => 0.0, 'courses' => 0];
                    $by[$k]['hours']   += max((float)$r->vh, (float)$r->dh);
                    $by[$k]['courses'] ++;
                }
                uasort($by, fn($a, $b) => $b['hours'] <=> $a['hours']);
                $this->output_xls_multi('tiempo_capacitacion', 'Reporte de Tiempo de Capacitacion', [
                    [
                        'title'   => 'Horas por Usuario (Cursos Aprobados)',
                        'headers' => ['Usuario', 'Email', 'Cursos Aprobados', 'Horas Totales'],
                        'data'    => array_map(fn($row) => [
                            $row['name'], $row['email'], (int)$row['courses'], round($row['hours'], 1) . ' h',
                        ], array_values($by)),
                    ],
                    [
                        'title'   => 'Detalle por Usuario y Curso',
                        'headers' => ['Usuario', 'Email', 'Curso', 'Horas'],
                        'data'    => array_map(fn($r) => [
                            $r->display_name, $r->user_email, $r->course,
                            round(max((float)$r->vh, (float)$r->dh), 1) . ' h',
                        ], $trows),
                    ],
                ], $f);
                break;

            // -- SATISFACCION ---------------------------------------------
            case str_starts_with($format, 'satisfaction'):
                $st = $wpdb->prefix . 'fplms_survey_responses';
                if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $st)) !== $st) {
                    echo 'Sin datos de satisfaccion.'; exit;
                }
                $sw     = null !== $uids ? (empty($uids) ? 'AND sr.user_id=0' : 'AND sr.user_id IN (' . implode(',', array_map('intval', $uids)) . ')') : '';
                $dw_sat = $this->date_sql('sr.submitted_at', $f['date_from'], $f['date_to']);
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $srows = (array) $wpdb->get_results(
                    "SELECT u.display_name,u.user_email,p.post_title AS course_name,
                            sr.question_idx,sr.question,sr.score,sr.submitted_at
                     FROM `{$st}` sr
                     INNER JOIN {$wpdb->users} u ON sr.user_id=u.ID
                     LEFT JOIN {$wpdb->posts} p ON sr.course_id=p.ID
                     WHERE 1=1 $sw $dw_sat
                     ORDER BY sr.course_id,sr.user_id,sr.question_idx LIMIT 5000"
                );
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $n_submissions = (int) $wpdb->get_var(
                    "SELECT COUNT(DISTINCT CONCAT(sr.user_id,'-',sr.course_id)) FROM `{$st}` sr WHERE 1=1 $sw $dw_sat"
                );
                // Per-course summary
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $sum_rows = (array) $wpdb->get_results(
                    "SELECT p.post_title AS course_name,COUNT(DISTINCT sr.user_id) AS respondents,
                            ROUND(AVG(sr.score),2) AS avg_score,
                            MIN(sr.submitted_at) AS first_sub,MAX(sr.submitted_at) AS last_sub
                     FROM `{$st}` sr LEFT JOIN {$wpdb->posts} p ON sr.course_id=p.ID
                     WHERE 1=1 $sw $dw_sat GROUP BY sr.course_id ORDER BY respondents DESC LIMIT 5000"
                );
                $this->output_xls_multi('satisfaccion', 'Reporte de Satisfaccion', [
                    [
                        'title'   => 'Resumen por Curso (' . count($sum_rows) . ' cursos)',
                        'headers' => ['Curso', 'Respondentes', 'Promedio (1-5)', 'Primera Respuesta', 'Ultima Respuesta'],
                        'data'    => array_map(fn($r) => [
                            $r->course_name ?? '(Sin curso)',
                            (int) $r->respondents,
                            number_format((float)$r->avg_score, 2),
                            wp_date('d/m/Y', strtotime($r->first_sub)),
                            wp_date('d/m/Y', strtotime($r->last_sub)),
                        ], $sum_rows),
                    ],
                    [
                        'title'   => 'Detalle de Respuestas (' . $n_submissions . ' envios)',
                        'headers' => ['Usuario', 'Email', 'Curso', 'Pregunta', 'Puntuacion (1-5)', 'Fecha'],
                        'data'    => array_map(fn($r) => [
                            $r->display_name, $r->user_email, $r->course_name ?? '—',
                            $r->question, (int) $r->score,
                            wp_date('d/m/Y', strtotime($r->submitted_at)),
                        ], $srows),
                    ],
                ], $f);
                break;

            // -- CANALES --------------------------------------------------
            case str_starts_with($format, 'channels'):
                if (!$ms) { echo 'Sin datos.'; exit; }
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $ch_rows = (array) $wpdb->get_results(
                    "SELECT t.name AS canal,
                            COUNT(DISTINCT uc.user_id) AS inscritos,
                            SUM(CASE WHEN uc.status IN('completed','passed') OR uc.progress_percent>=100 THEN 1 ELSE 0 END) AS completados,
                            ROUND(SUM(CASE WHEN uc.status IN('completed','passed') OR uc.progress_percent>=100 THEN 1 ELSE 0 END)/COUNT(DISTINCT uc.user_id)*100,1) AS tasa,
                            p.post_title AS curso
                     FROM {$wpdb->term_taxonomy} tt
                     INNER JOIN {$wpdb->terms} t ON tt.term_id=t.term_id
                     INNER JOIN {$wpdb->postmeta} pm ON pm.meta_key='fplms_course_channels'
                         AND pm.meta_value LIKE CONCAT('%\"',tt.term_id,'\"%')
                     INNER JOIN {$wpdb->posts} p ON pm.post_id=p.ID
                         AND p.post_type='stm-courses' AND p.post_status='publish'
                     INNER JOIN `{$ms}` uc ON uc.course_id=p.ID
                     WHERE tt.taxonomy='fplms_channel' $mw
                     GROUP BY tt.term_id,p.ID ORDER BY t.name,p.post_title LIMIT 5000"
                );
                $this->output_xls_multi('canales', 'Reporte de Canales', [
                    [
                        'title'   => 'Cumplimiento por Canal y Curso',
                        'headers' => ['Canal', 'Curso', 'Inscritos', 'Completados', 'Tasa Cumplimiento %'],
                        'data'    => array_map(fn($r) => [
                            $r->canal, $r->curso, (int)$r->inscritos, (int)$r->completados, $r->tasa,
                        ], $ch_rows),
                    ],
                ], $f);
                break;

            // -- TESTS DETAIL ----------------------------------------------
            case str_starts_with($format, 'tests_detail_'):
                $quiz_id_e = (int)substr($format, strlen('tests_detail_'));
                $qt_e = null;
                if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix.'stm_lms_user_quizzes')) === $wpdb->prefix.'stm_lms_user_quizzes')
                    $qt_e = $wpdb->prefix.'stm_lms_user_quizzes';
                if (!$qt_e || !$quiz_id_e) { echo 'Sin datos.'; exit; }
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $quiz_title_e = get_the_title($quiz_id_e) ?: 'Test #'.$quiz_id_e;
                $uw_q_e = $this->uid_in($uids, 'uq.user_id');
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
                $det_rows = (array)$wpdb->get_results($wpdb->prepare(
                    "SELECT u.ID AS user_id, u.display_name, u.user_email,
                            uq.user_quiz_id, uq.progress AS score, uq.status, uq.created_at,
                            MAX(pc.post_title) AS course_name
                     FROM `{$qt_e}` uq
                     INNER JOIN {$wpdb->users} u ON uq.user_id = u.ID
                     LEFT JOIN {$wpdb->posts} pc ON uq.course_id = pc.ID
                     WHERE uq.quiz_id = %d $uw_q_e
                     GROUP BY uq.user_quiz_id, u.ID, u.display_name, u.user_email, uq.progress, uq.status, uq.created_at
                     ORDER BY u.display_name",
                    $quiz_id_e
                ));
                // Fetch timing data for XLS export
                $xls_time_map = [];
                $qt_times_e = $wpdb->prefix.'stm_lms_user_quizzes_times';
                if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $qt_times_e)) === $qt_times_e) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $xls_time_rows = (array)$wpdb->get_results($wpdb->prepare(
                        "SELECT user_id, start_time, end_time FROM `{$qt_times_e}` WHERE quiz_id = %d ORDER BY user_quiz_time_id DESC",
                        $quiz_id_e
                    ));
                    foreach ($xls_time_rows as $xtr) {
                        if (!isset($xls_time_map[(int)$xtr->user_id])) $xls_time_map[(int)$xtr->user_id] = $xtr;
                    }
                }
                // Map: user_id => max(user_quiz_id) para timing solo en el intento más reciente
                $xls_latest = [];
                foreach ($det_rows as $r) {
                    $uid  = (int) $r->user_id;
                    $uqid = (int) ( $r->user_quiz_id ?? 0 );
                    if ( $uqid > ( $xls_latest[ $uid ] ?? 0 ) ) $xls_latest[ $uid ] = $uqid;
                }
                $det_data = [];
                foreach ($det_rows as $r) {
                    $st_e      = strtolower($r->status ?? '');
                    $lbl_e     = in_array($st_e, ['passed','completed'], true) ? 'Aprobado' : (in_array($st_e, ['failed','not_passed'], true) ? 'Reprobado' : 'Sin iniciar');
                    $xls_uid   = (int) $r->user_id;
                    $xls_is_latest = ( (int)( $r->user_quiz_id ?? 0 ) === ( $xls_latest[ $xls_uid ] ?? -1 ) );
                    $xqt       = $xls_is_latest ? ( $xls_time_map[ $xls_uid ] ?? null ) : null;
                    $xstart    = $xqt ? (int)$xqt->start_time : 0;
                    $xend      = $xqt ? (int)$xqt->end_time   : 0;
                    $fecha_e   = $xend > 0
                        ? wp_date('d/m/Y H:i:s', $xend)
                        : (!empty($r->created_at) ? wp_date('d/m/Y H:i:s', strtotime($r->created_at)) : '—');
                    $dur_e     = ($xls_is_latest && $xend > 0 && $xstart > 0 && $xend > $xstart) ? $this->format_duration($xend - $xstart) : '—';
                    $det_data[] = [$r->display_name, $r->user_email, $r->course_name ?? '—', $fecha_e, $lbl_e, number_format((float)$r->score, 2), $dur_e];
                }
                $this->output_xls_multi('test_detalle_'.$quiz_id_e, 'Detalle: '.$quiz_title_e, [[
                    'title'   => 'Resultados ('.count($det_rows).' usuarios)',
                    'headers' => ['Usuario', 'Email', 'Curso', 'Fecha de Realizacion', 'Resultado', 'Puntuacion %', 'Duracion'],
                    'data'    => $det_data,
                ]], $f);
                break;

            // -- TESTS ------------------------------------------------------
            case str_starts_with($format, 'tests'):
                $qt_l = null;
                if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix.'stm_lms_user_quizzes')) === $wpdb->prefix.'stm_lms_user_quizzes')
                    $qt_l = $wpdb->prefix.'stm_lms_user_quizzes';
                if (!$qt_l) { echo 'Sin datos.'; exit; }
                $uw_q_l = $this->uid_in($uids, 'uq.user_id');
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $tl_rows = (array)$wpdb->get_results(
                    "SELECT q.ID AS quiz_id, q.post_title AS quiz_name,
                            MAX(pc.post_title) AS course_name,
                            COUNT(uq.user_id) AS total_attempts,
                            SUM(CASE WHEN uq.status IN('passed','completed') THEN 1 ELSE 0 END) AS approved,
                            ROUND(AVG(CASE WHEN uq.progress > 0 THEN uq.progress ELSE NULL END), 2) AS avg_score
                     FROM {$wpdb->posts} q
                     LEFT JOIN `{$qt_l}` uq ON q.ID = uq.quiz_id $uw_q_l
                     LEFT JOIN {$wpdb->posts} pc ON uq.course_id = pc.ID
                     WHERE q.post_type = 'stm-quizzes' AND q.post_status = 'publish'
                     GROUP BY q.ID, q.post_title
                     ORDER BY q.post_title"
                );
                $tl_data = [];
                foreach ($tl_rows as $i => $r) {
                    $tl_data[] = [$i+1, $r->quiz_name, $r->course_name ?? '—', (int)$r->total_attempts, (int)$r->approved, number_format((float)($r->avg_score ?? 0), 2)];
                }
                $this->output_xls_multi('tests', 'Reporte de Tests / Evaluaciones', [[
                    'title'   => 'Listado de Evaluaciones ('.count($tl_rows).')',
                    'headers' => ['#', 'Test', 'Curso', 'Completado', 'Aprobado', 'Puntuacion Media %'],
                    'data'    => $tl_data,
                ]], $f);
                break;

            // -- MATRIZ DE FORMACION ---------------------------------------
            case 'matrix_xls':
                // All published courses
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $mx_courses = (array)$wpdb->get_results(
                    "SELECT ID, post_title FROM {$wpdb->posts}
                     WHERE post_type='stm-courses' AND post_status='publish'
                     ORDER BY post_title LIMIT 200"
                );
                // All matching users (apply global user filter, no pagination)
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $mx_users = (array)$wpdb->get_results(
                    "SELECT u.ID, u.display_name, u.user_email
                     FROM {$wpdb->users} u
                     WHERE 1=1 $uw
                     ORDER BY u.display_name LIMIT 5000"
                );
                // Enrollment map
                $mx_enr = [];
                if (!empty($mx_users) && $ms) {
                    $mx_uid_str = implode(',', array_map(fn($u) => (int)$u->ID, $mx_users));
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    $mx_enrs = (array)$wpdb->get_results(
                        "SELECT user_id, course_id, progress_percent, status FROM `{$ms}` WHERE user_id IN ($mx_uid_str)"
                    );
                    foreach ($mx_enrs as $e) {
                        $mx_enr[(int)$e->user_id][(int)$e->course_id] = [
                            'progress' => (float)$e->progress_percent,
                            'status'   => strtolower($e->status ?? ''),
                        ];
                    }
                }
                // Build headers + data
                $mx_headers = ['Usuario', 'Email'];
                foreach ($mx_courses as $c) $mx_headers[] = $c->post_title;
                $mx_data = [];
                foreach ($mx_users as $u) {
                    $row = [$u->display_name, $u->user_email];
                    foreach ($mx_courses as $c) {
                        $enr = $mx_enr[(int)$u->ID][(int)$c->ID] ?? null;
                        if ($enr === null) {
                            $row[] = '';
                        } elseif (in_array($enr['status'], ['completed','passed'], true) || $enr['progress'] >= 100) {
                            $row[] = '✓ Completado';
                        } elseif ($enr['progress'] > 0) {
                            $row[] = 'En Progreso (' . round($enr['progress']) . '%)';
                        } else {
                            $row[] = 'Asignado';
                        }
                    }
                    $mx_data[] = $row;
                }
                $this->output_xls_multi('matriz_formacion', 'Matriz de Formacion', [[
                    'title'   => 'Matriz de Formacion — '.count($mx_courses).' cursos / '.count($mx_users).' estudiantes',
                    'headers' => $mx_headers,
                    'data'    => $mx_data,
                ]], $f);
                break;
            default:
                $users = $this->users->get_users_filtered_by_structure(0, 0, 0, 0);
                $this->output_xls_multi('usuarios_avance', 'Usuarios — Avance General', [
                    [
                        'title'   => 'Listado de Usuarios',
                        'headers' => ['ID', 'Nombre', 'Email', 'Roles', 'Ciudad', 'Canal', 'Sucursal', 'Cargo'],
                        'data'    => array_map(fn($u) => [
                            $u->ID, $u->display_name, $u->user_email, implode(', ', $u->roles),
                            $this->structures->get_term_name_by_id(get_user_meta($u->ID, FairPlay_LMS_Config::USER_META_CITY,    true)),
                            $this->structures->get_term_name_by_id(get_user_meta($u->ID, FairPlay_LMS_Config::USER_META_CHANNEL, true)),
                            $this->structures->get_term_name_by_id(get_user_meta($u->ID, FairPlay_LMS_Config::USER_META_BRANCH,  true)),
                            $this->structures->get_term_name_by_id(get_user_meta($u->ID, FairPlay_LMS_Config::USER_META_ROLE,    true)),
                        ], $users),
                    ],
                ], $f);
        }
    }

    /**
     * Output a multi-section SpreadsheetML XLS.
     * Each section = ['title'=>string, 'headers'=>string[], 'data'=>array[]]
     * Section title row: #FFA800 bg (orange).
     * Column header row: #CC8600 bg (dark orange).
     * Data rows alternate white / #FFF8EE.
     */
    private function output_xls_multi(string $slug, string $report_title, array $sections, array $filters = []): void {
        $xe = fn($v) => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        // Build metadata line (filters + user names)
        $fp = [];
        if (!empty($filters['date_from'])) $fp[] = 'Desde: ' . $filters['date_from'];
        if (!empty($filters['date_to']))   $fp[] = 'Hasta: ' . $filters['date_to'];
        if (!empty($filters['user_ids'])) {
            $uid_list = is_array($filters['user_ids'])
                ? $filters['user_ids']
                : array_values(array_filter(array_map('intval', explode(',', (string)$filters['user_ids']))));
            $names = [];
            foreach ($uid_list as $uid) {
                $ud = get_userdata((int)$uid);
                if ($ud) $names[] = $ud->display_name;
            }
            if (!empty($names)) $fp[] = 'Usuario: ' . implode(', ', $names);
        }
        $meta = 'Generado: ' . wp_date('d/m/Y H:i') . (empty($fp) ? '' : ' | ' . implode(' | ', $fp));

        // Helper: sanitize Excel sheet name (max 31 chars, no invalid chars, deduplicated)
        $used_names = [];
        $make_sheet_name = function(string $title, int $idx) use (&$used_names): string {
            $clean = preg_replace('/[\/\\\\\?\*\[\]:]/', '', $title);
            $clean = trim((string)$clean);
            if ($clean === '') $clean = 'Hoja' . $idx;
            $clean = mb_substr($clean, 0, 31);
            $base  = $clean;
            $i     = 2;
            while (isset($used_names[$clean])) {
                $suffix = ' ' . $i++;
                $clean  = mb_substr($base, 0, 31 - mb_strlen($suffix)) . $suffix;
            }
            $used_names[$clean] = true;
            return $clean;
        };

        $styles = '<Styles>'
            . '<Style ss:ID="s_title"><Font ss:Bold="1" ss:Size="13" ss:Color="#333333"/>'
            .   '<Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>'
            .   '<Alignment ss:Horizontal="Left" ss:Vertical="Center"/></Style>'
            . '<Style ss:ID="s_meta"><Font ss:Italic="1" ss:Size="9" ss:Color="#888888"/>'
            .   '<Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/></Style>'
            . '<Style ss:ID="s_sec"><Font ss:Bold="1" ss:Size="11" ss:Color="#FFFFFF"/>'
            .   '<Interior ss:Color="#FFA800" ss:Pattern="Solid"/>'
            .   '<Alignment ss:Horizontal="Left" ss:Vertical="Center"/></Style>'
            . '<Style ss:ID="s_hdr"><Font ss:Bold="1" ss:Size="10" ss:Color="#FFFFFF"/>'
            .   '<Interior ss:Color="#CC8600" ss:Pattern="Solid"/>'
            .   '<Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>'
            .   '<Borders>'
            .     '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#AA6E00"/>'
            .     '<Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AA6E00"/>'
            .     '<Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#AA6E00"/>'
            .   '</Borders></Style>'
            . '<Style ss:ID="s_even"><Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>'
            .   '<Alignment ss:Vertical="Center"/>'
            .   '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0F0F0"/></Borders></Style>'
            . '<Style ss:ID="s_odd"><Interior ss:Color="#FFF8EE" ss:Pattern="Solid"/>'
            .   '<Alignment ss:Vertical="Center"/>'
            .   '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0E8D8"/></Borders></Style>'
            . '</Styles>';

        // One worksheet per section
        $worksheets = '';
        foreach ($sections as $idx => $s) {
            $ncols      = count($s['headers']);
            $merge      = max(0, $ncols - 1);
            $sheet_name = $make_sheet_name($s['title'], $idx + 1);

            $sheet_body  = '';
            $sheet_body .= '<Row ss:Height="22"><Cell ss:StyleID="s_title" ss:MergeAcross="' . $merge . '">'
                         . '<Data ss:Type="String">' . $xe($report_title) . '</Data></Cell></Row>';
            $sheet_body .= '<Row ss:Height="14"><Cell ss:StyleID="s_meta" ss:MergeAcross="' . $merge . '">'
                         . '<Data ss:Type="String">' . $xe($meta) . '</Data></Cell></Row>';
            $sheet_body .= '<Row ss:Height="8"/>';
            $sheet_body .= '<Row ss:Height="20"><Cell ss:StyleID="s_sec" ss:MergeAcross="' . $merge . '">'
                         . '<Data ss:Type="String">' . $xe($s['title']) . '</Data></Cell></Row>';

            $hdr = implode('', array_map(
                fn($h) => '<Cell ss:StyleID="s_hdr"><Data ss:Type="String">' . $xe($h) . '</Data></Cell>',
                $s['headers']
            ));
            $sheet_body .= '<Row ss:Height="20">' . $hdr . '</Row>';

            if (empty($s['data'])) {
                $sheet_body .= '<Row ss:Height="18"><Cell ss:StyleID="s_even" ss:MergeAcross="' . $merge . '">'
                             . '<Data ss:Type="String">(Sin datos para los filtros seleccionados)</Data></Cell></Row>';
            } else {
                $i = 0;
                foreach ($s['data'] as $row) {
                    $sty  = ($i % 2 === 0) ? 's_even' : 's_odd';
                    $sheet_body .= '<Row ss:Height="18">';
                    foreach ((array)$row as $cell) {
                        $v    = (string)$cell;
                        $type = (is_numeric($v) && !preg_match('/^0\d/', $v) && $v !== '') ? 'Number' : 'String';
                        $sheet_body .= '<Cell ss:StyleID="' . $sty . '"><Data ss:Type="' . $type . '">' . $xe($v) . '</Data></Cell>';
                    }
                    $sheet_body .= '</Row>';
                    $i++;
                }
            }

            $worksheets .= '<Worksheet ss:Name="' . $xe($sheet_name) . '"><Table>' . $sheet_body . '</Table></Worksheet>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<?mso-application progid="Excel.Sheet"?>' . "\n"
             . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
             . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
             . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
             . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
             . $styles
             . $worksheets
             . '</Workbook>';

        // Flush all WordPress output buffers so no HTML contaminates the file
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="fairplay_' . $slug . '_' . gmdate('Ymd_His') . '.xls"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $xml;
        exit;
    }

    private function output_xls(string $slug, string $title, array $headers, array $data, array $filters = []): void {
        $ncols  = count($headers);
        $merge  = max(0, $ncols - 1);
        $xe     = fn($v) => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $fp = [];
        if (!empty($filters['date_from'])) $fp[] = 'Desde: ' . $filters['date_from'];
        if (!empty($filters['date_to']))   $fp[] = 'Hasta: ' . $filters['date_to'];

        // Resolve filtered user names for the metadata row
        if (!empty($filters['user_ids'])) {
            $uid_list = is_array($filters['user_ids'])
                ? $filters['user_ids']
                : array_values(array_filter(array_map('intval', explode(',', (string)$filters['user_ids']))));
            $names = [];
            foreach ($uid_list as $uid) {
                $u = get_userdata((int)$uid);
                if ($u) $names[] = $u->display_name;
            }
            if (!empty($names)) $fp[] = 'Usuario: ' . implode(', ', $names);
        }

        $meta = 'Generado: ' . wp_date('d/m/Y H:i') . (empty($fp) ? '' : ' | ' . implode(' | ', $fp));

        $hdr_xml = '';
        foreach ($headers as $h) {
            $hdr_xml .= '<Cell ss:StyleID="s_hdr"><Data ss:Type="String">' . $xe($h) . '</Data></Cell>';
        }

        $rows_xml = ''; $i = 0;
        foreach ($data as $row) {
            $sty = ($i % 2 === 0) ? 's_even' : 's_odd';
            $rows_xml .= '<Row ss:Height="18">';
            foreach ((array)$row as $cell) {
                $v    = (string)$cell;
                $type = (is_numeric($v) && !preg_match('/^0\d/', $v) && $v !== '') ? 'Number' : 'String';
                $rows_xml .= '<Cell ss:StyleID="' . $sty . '"><Data ss:Type="' . $type . '">' . $xe($v) . '</Data></Cell>';
            }
            $rows_xml .= '</Row>'; $i++;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<?mso-application progid="Excel.Sheet"?>' . "\n"
             . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
             . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
             . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
             . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
             . '<Styles>'
             . '<Style ss:ID="s_title"><Font ss:Bold="1" ss:Size="13" ss:Color="#333333"/>'
             .   '<Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>'
             .   '<Alignment ss:Horizontal="Left" ss:Vertical="Center"/></Style>'
             . '<Style ss:ID="s_meta"><Font ss:Italic="1" ss:Size="9" ss:Color="#888888"/>'
             .   '<Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/></Style>'
             . '<Style ss:ID="s_hdr">'
             .   '<Font ss:Bold="1" ss:Size="10" ss:Color="#FFFFFF"/>'
             .   '<Interior ss:Color="#FFA800" ss:Pattern="Solid"/>'
             .   '<Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>'
             .   '<Borders>'
             .     '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#CC8600"/>'
             .     '<Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E09000"/>'
             .     '<Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E09000"/>'
             .   '</Borders></Style>'
             . '<Style ss:ID="s_even">'
             .   '<Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>'
             .   '<Alignment ss:Vertical="Center"/>'
             .   '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0F0F0"/></Borders></Style>'
             . '<Style ss:ID="s_odd">'
             .   '<Interior ss:Color="#FFF8EE" ss:Pattern="Solid"/>'
             .   '<Alignment ss:Vertical="Center"/>'
             .   '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#F0E8D8"/></Borders></Style>'
             . '</Styles>'
             . '<Worksheet ss:Name="Reporte"><Table>'
             // Title
             . '<Row ss:Height="28"><Cell ss:StyleID="s_title" ss:MergeAcross="' . $merge . '">'
             .   '<Data ss:Type="String">' . $xe($title) . '</Data></Cell></Row>'
             // Meta
             . '<Row ss:Height="16"><Cell ss:StyleID="s_meta" ss:MergeAcross="' . $merge . '">'
             .   '<Data ss:Type="String">' . $xe($meta) . '</Data></Cell></Row>'
             // Spacer
             . '<Row ss:Height="6"/>'
             // Headers
             . '<Row ss:Height="24">' . $hdr_xml . '</Row>'
             // Data
             . $rows_xml
             . '</Table>'
             . '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">'
             .   '<FreezePanes/><FrozenNoSplit/>'
             .   '<SplitHorizontal>4</SplitHorizontal><TopRowBottomPane>4</TopRowBottomPane>'
             .   '<ActivePane>2</ActivePane>'
             . '</WorksheetOptions>'
             . '</Worksheet></Workbook>';

        // Flush every output buffer WordPress (or plugins) may have opened so
        // no HTML leaks into the file before our headers are sent.
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="fairplay_' . $slug . '_' . gmdate('Ymd_His') . '.xls"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $xml;
        exit;
    }

    // RENDER MAIN PAGE
    public function render_reports_page(): void {
        if (!current_user_can(FairPlay_LMS_Config::CAP_VIEW_REPORTS)) wp_die('Sin permisos.');
        $cities    = $this->structures->get_active_terms_for_select(FairPlay_LMS_Config::TAX_CITY);
        $companies = $this->structures->get_active_terms_for_select(FairPlay_LMS_Config::TAX_COMPANY);
        $channels  = $this->structures->get_active_terms_for_select(FairPlay_LMS_Config::TAX_CHANNEL);
        $roles     = $this->structures->get_active_terms_for_select(FairPlay_LMS_Config::TAX_ROLE);
        $nonce     = wp_create_nonce('fplms_reports_nonce');
        $tabs = [
            'participation'=>'Participacion','progress'=>'Progreso','performance'=>'Desempeno',
            'certificates'=>'Certificados','time'=>'Tiempo','satisfaction'=>'Satisfaccion','channels'=>'Canales',
            'tests'=>'Tests','matrix'=>'Matriz de Formacion',
        ];
        $icons = [
            'participation' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><rect x="18" y="3" width="4" height="18"/><rect x="10" y="8" width="4" height="13"/><rect x="2" y="13" width="4" height="8"/></svg>',
            'progress'      => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
            'performance'   => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2z"/></svg>',
            'certificates'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>',
            'time'          => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
            'satisfaction'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            'channels'      => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>',
            'tests'         => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
            'matrix'        => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>',
        ];
        ?>
        <div class="wrap fplms-reports-wrap">
            <h1 class="wp-heading-inline"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1d2327" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-4px;margin-right:6px"><rect x="9" y="2" width="6" height="4" rx="1"/><path d="M5 4H3a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h18a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2"/><path d="M9 12h6M9 16h6"/></svg>Reportes FairPlay LMS</h1>
            <hr class="wp-header-end">
            <div class="fplms-rpt-filters">
                <div class="fplms-rpt-filters-row">
                    <div class="fplms-rpt-fg"><label>Desde</label><input type="date" id="fplms-rpt-date-from"></div>
                    <div class="fplms-rpt-fg"><label>Hasta</label><input type="date" id="fplms-rpt-date-to"></div>
                    <div class="fplms-rpt-fg"><label>Regional (Ciudad)</label>
                        <select id="fplms-rpt-city"><option value="">— Todas —</option>
                        <?php foreach ($cities as $id=>$nm): ?><option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($nm); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="fplms-rpt-fg"><label>Empresa</label>
                        <select id="fplms-rpt-company"><option value="">— Todas —</option>
                        <?php foreach ($companies as $id=>$nm): ?><option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($nm); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="fplms-rpt-fg"><label>Canal / Area</label>
                        <select id="fplms-rpt-channel"><option value="">— Todos —</option>
                        <?php foreach ($channels as $id=>$nm): ?><option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($nm); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="fplms-rpt-fg"><label>Cargo</label>
                        <select id="fplms-rpt-role"><option value="">— Todos —</option>
                        <?php foreach ($roles as $id=>$nm): ?><option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($nm); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="fplms-rpt-fg fplms-rpt-fg-user">
                        <label>Usuario</label>
                        <input type="text" id="fplms-rpt-user-search" placeholder="Buscar por nombre o email...">
                        <input type="hidden" id="fplms-rpt-user-ids" value="">
                        <div id="fplms-rpt-suggestions"></div>
                    </div>
                </div>
                <div class="fplms-rpt-filters-actions">
                    <button id="fplms-rpt-apply" class="button button-primary"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>Aplicar Filtros</button>
                    <button id="fplms-rpt-reset" class="button"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Limpiar</button>
                </div>
            </div>
            <div id="fplms-active-filters-bar"></div>
            <div class="fplms-rpt-tabs-nav">
                <?php foreach ($tabs as $slug=>$label): ?>
                <button class="fplms-rpt-tab-btn" data-tab="<?php echo esc_attr($slug); ?>"><?php echo ($icons[$slug]??'') . esc_html($label); ?></button>
                <?php endforeach; ?>
            </div>
            <div id="fplms-rpt-content" class="fplms-rpt-content">
                <div class="fplms-rpt-placeholder"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#ddd" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="display:block;margin:0 auto 12px"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 9h6M9 12h6M9 15h4"/></svg><p>Selecciona una pestana de reporte para comenzar.</p></div>
            </div>
        </div>
        <!-- Confirm modal (reset intento) -->
        <div id="fplms-rpt-confirm-modal" class="fplms-rpt-cmodal-overlay">
            <div class="fplms-rpt-cmodal">
                <div id="fplms-rpt-cmodal-header" class="fplms-rpt-cmodal-header fplms-rpt-cmodal-header--red">
                    <svg id="fplms-rpt-cmodal-icon" viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                    <h3 id="fplms-rpt-cmodal-title"></h3>
                </div>
                <div class="fplms-rpt-cmodal-body">
                    <p id="fplms-rpt-cmodal-text"></p>
                    <div id="fplms-rpt-cmodal-name" class="fplms-rpt-cmodal-name"></div>
                    <p id="fplms-rpt-cmodal-sub" style="color:#4b5563;font-size:13px;margin:8px 0 0"></p>
                </div>
                <div class="fplms-rpt-cmodal-footer">
                    <button type="button" class="fplms-rpt-cmodal-cancel" id="fplms-rpt-cmodal-cancel">Cancelar</button>
                    <button type="button" class="fplms-rpt-cmodal-confirm" id="fplms-rpt-cmodal-confirm">Confirmar</button>
                </div>
            </div>
        </div>

        <div id="fplms-rpt-modal" style="display:none;position:fixed;inset:0;z-index:100000;align-items:center;justify-content:center;background:rgba(0,0,0,.48)">
            <div style="background:#fff;border-radius:14px;padding:38px 40px 32px;max-width:420px;width:92%;text-align:center;box-shadow:0 16px 48px rgba(0,0,0,.22)">
                <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#FFA800" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="display:block;margin:0 auto 16px"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/><circle cx="18" cy="18" r="5" fill="#fff" stroke="#22c55e" stroke-width="2"/><path d="m16 18 1.5 1.5L20 16" stroke="#22c55e" stroke-width="2"/></svg>
                <h3 style="margin:0 0 10px;font-size:17px;color:#222;font-weight:700">Filtros Aplicados</h3>
                <p style="color:#666;font-size:14px;line-height:1.6;margin:0 0 24px">Selecciona cada pestana para obtener la informacion de ese reporte de forma independiente segun los filtros seleccionados.</p>
                <button id="fplms-rpt-modal-close" class="button button-primary" style="min-width:120px">Entendido</button>
            </div>
        </div>
        <style>
        .fplms-reports-wrap{max-width:1400px}
        .fplms-rpt-filters{background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:18px 20px 14px;margin:16px 0}
        .fplms-rpt-filters-row{display:flex;flex-wrap:wrap;gap:12px 16px;align-items:flex-end}
        .fplms-rpt-fg{display:flex;flex-direction:column;gap:4px;min-width:148px;position:relative}
        .fplms-rpt-fg-user{min-width:220px}
        .fplms-rpt-fg label{font-size:11px;font-weight:600;color:#666;text-transform:uppercase;letter-spacing:.4px}
        .fplms-rpt-fg select,.fplms-rpt-fg input[type=date],.fplms-rpt-fg input[type=text]{padding:6px 10px;border:1.5px solid #ddd;border-radius:6px;font-size:13px;background:#fafafa}
        .fplms-rpt-fg select:focus,.fplms-rpt-fg input:focus{border-color:#ffa800;outline:none;background:#fff}
        .fplms-rpt-filters-actions{margin-top:14px;display:flex;gap:8px}
        #fplms-active-filters-bar{display:none;align-items:center;flex-wrap:wrap;gap:6px;padding:8px 14px;background:#fff8ee;border:1px solid #ffe0a0;border-radius:0 0 6px 6px;margin-bottom:4px;font-size:12px}
        .fplms-fbar-lbl{font-weight:700;color:#c96800;font-size:11px;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;margin-left:6px}
        .fplms-fbar-badge{padding:2px 10px;background:#fff4e0;border:1px solid #ffa80055;border-radius:12px;color:#7a4800;font-size:11px;font-weight:600}
        .fplms-fbar-note{margin-left:auto;font-size:11px;color:#aaa;font-style:italic;white-space:nowrap}
        .fplms-filter-ctx{display:flex;align-items:center;gap:8px;padding:7px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;font-size:12px;color:#78350f;margin-bottom:14px}
        #fplms-rpt-suggestions{position:absolute;top:calc(100% + 2px);left:0;right:0;background:#fff;border:1.5px solid #ddd;border-radius:0 0 6px 6px;z-index:200;max-height:200px;overflow-y:auto;display:none;box-shadow:0 4px 12px rgba(0,0,0,.1)}
        .fplms-sug-item{padding:7px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid #f0f0f0}
        .fplms-sug-item:hover{background:#fff8ee}
        .fplms-rpt-tabs-nav{display:flex;flex-wrap:wrap;gap:3px;border-bottom:2px solid #ffa800;margin-bottom:0}
        .fplms-rpt-tab-btn{padding:10px 16px;border:1.5px solid #e0e0e0;border-bottom:none;background:#f5f5f5;cursor:pointer;font-size:13px;font-weight:500;color:#555;border-radius:6px 6px 0 0;transition:all .15s}
        .fplms-rpt-tab-btn:hover{background:#fff8ee;color:#e08800}
        .fplms-rpt-tab-btn.active{background:#ffa800;color:#fff;border-color:#ffa800;font-weight:700}
        .fplms-rpt-content{background:#fff;border:1.5px solid #e0e0e0;border-top:none;border-radius:0 0 8px 8px;padding:24px;min-height:320px}
        .fplms-rpt-placeholder{text-align:center;color:#bbb;padding:60px 0;font-size:15px}
        /* — Matriz de Formacion — */
        .fplms-mx-legend{display:flex;flex-wrap:wrap;gap:10px 20px;margin:8px 0 14px;padding:10px 14px;background:#f9f9f9;border-radius:6px;border:1px solid #e8e8e8}
        .fplms-mx-leg-item{display:flex;align-items:center;gap:6px;font-size:12px;color:#555}
        .fplms-mx-leg-dot{width:22px;height:22px;border-radius:4px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
        .fplms-mx-leg-dot.fplms-mx-done{background:#22c55e}
        .fplms-mx-leg-dot.fplms-mx-progress{background:#f59e0b}
        .fplms-mx-leg-dot.fplms-mx-assigned{background:#3b82f6}
        .fplms-mx-leg-dot.fplms-mx-empty-dot{background:#f0f0f0;border:1px solid #ddd}
        .fplms-mx-controls{display:flex;flex-wrap:wrap;align-items:center;gap:10px 16px;margin-bottom:12px}
        .fplms-mx-search-input{flex:1;min-width:220px;max-width:380px;padding:6px 10px;border:1px solid #ddd;border-radius:5px;font-size:13px}
        .fplms-mx-per-page-label{font-size:12px;color:#555;display:flex;align-items:center;gap:6px}
        .fplms-mx-per-page{padding:4px 6px;border:1px solid #ddd;border-radius:4px;font-size:12px}
        .fplms-mx-table-wrap{overflow-x:auto;border-radius:6px;border:1px solid #e0e0e0;margin-bottom:4px}
        .fplms-mx-table{border-collapse:collapse;width:100%;min-width:400px}
        .fplms-mx-table thead tr{background:#f3f4f6}
        .fplms-mx-th-user,.fplms-mx-th-email{font-size:12px;padding:8px 10px;white-space:nowrap;border-bottom:2px solid #d1d5db;text-align:left}
        .fplms-mx-th-user{min-width:160px;position:sticky;left:0;background:#f3f4f6;z-index:3;box-shadow:2px 0 4px rgba(0,0,0,.06)}
        .fplms-mx-th-email{min-width:180px;position:sticky;left:160px;background:#f3f4f6;z-index:3;box-shadow:2px 0 4px rgba(0,0,0,.04)}
        .fplms-mx-th-course{width:38px;min-width:38px;max-width:38px;padding:4px 2px;border-bottom:2px solid #d1d5db;border-left:1px solid #e5e7eb;vertical-align:bottom}
        .fplms-mx-th-inner{writing-mode:vertical-lr;transform:rotate(180deg);white-space:nowrap;font-size:11px;color:#4b5563;padding-bottom:4px;max-height:110px;overflow:hidden}
        .fplms-mx-table tbody tr:nth-child(even){background:#fafafa}
        .fplms-mx-table tbody tr:hover td{background:#f0f7ff!important}
        .fplms-mx-td-user{position:sticky;left:0;background:inherit;z-index:1;padding:6px 10px;white-space:nowrap;font-size:13px;border-right:1px solid #e5e7eb;box-shadow:2px 0 4px rgba(0,0,0,.04)}
        .fplms-mx-td-email{position:sticky;left:160px;background:inherit;z-index:1;padding:6px 10px;white-space:nowrap;font-size:12px;color:#6b7280;border-right:1px solid #e5e7eb;box-shadow:2px 0 4px rgba(0,0,0,.02)}
        .fplms-mx-cell{width:38px;min-width:38px;max-width:38px;text-align:center;padding:4px 2px;border-left:1px solid #e5e7eb;border-bottom:1px solid #f3f4f6}
        .fplms-mx-done{background:#22c55e}
        .fplms-mx-progress{background:#f59e0b}
        .fplms-mx-progress small{color:#fff;font-size:9px;font-weight:700;display:block;line-height:1.6}
        .fplms-mx-assigned{background:#3b82f6}
        .fplms-mx-empty{background:#fff}
        .fplms-mx-pagination{display:flex;align-items:center;gap:10px;margin-top:10px;flex-wrap:wrap}
        .fplms-mx-pag-info{font-size:12px;color:#6b7280;flex:1;min-width:120px}
        /* — Confirm modal — */
        .fplms-rpt-cmodal-overlay{display:none;position:fixed;inset:0;z-index:100002;background:rgba(0,0,0,.52);align-items:center;justify-content:center}
        .fplms-rpt-cmodal-overlay.active{display:flex}
        .fplms-rpt-cmodal{background:#fff;border-radius:12px;width:92%;max-width:420px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.25)}
        .fplms-rpt-cmodal-header{display:flex;align-items:center;gap:12px;padding:18px 22px;color:#fff}
        .fplms-rpt-cmodal-header svg{width:26px;height:26px;flex-shrink:0;fill:#fff}
        .fplms-rpt-cmodal-header h3{margin:0;font-size:15px;font-weight:700;color: white;}
        .fplms-rpt-cmodal-header--red{background:linear-gradient(135deg,#dc2626,#b91c1c)}
        .fplms-rpt-cmodal-header--amber{background:linear-gradient(135deg,#d97706,#b45309)}
        .fplms-rpt-cmodal-body{padding:20px 22px 8px;font-size:13px;color:#374151}
        .fplms-rpt-cmodal-name{margin:10px 0 0;padding:10px 14px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;font-size:13px;font-weight:600;color:#991b1b;word-break:break-word}
        .fplms-rpt-cmodal-name--amber{background:#fffbeb;border-color:#fde68a;color:#92400e}
        .fplms-rpt-cmodal-footer{display:flex;justify-content:flex-end;gap:10px;padding:16px 22px}
        .fplms-rpt-cmodal-cancel{background:#f3f4f6;border:1px solid #d1d5db;border-radius:6px;padding:7px 18px;font-size:13px;cursor:pointer;color:#374151}
        .fplms-rpt-cmodal-cancel:hover{background:#e5e7eb}
        .fplms-rpt-cmodal-confirm{background:#dc2626;border:none;border-radius:6px;padding:7px 18px;font-size:13px;font-weight:600;cursor:pointer;color:#fff}
        .fplms-rpt-cmodal-confirm:hover{background:#b91c1c}
        .fplms-rpt-cmodal-confirm--amber{background:#d97706}
        .fplms-rpt-cmodal-confirm--amber:hover{background:#b45309}
        .fplms-rpt-loading{text-align:center;padding:50px;color:#888;font-size:14px}
        .fplms-rpt-section-head{display:flex;align-items:center;justify-content:space-between;border-bottom:2px solid #ffa800;padding-bottom:10px;margin-bottom:20px}
        .fplms-rpt-title{margin:0;font-size:18px;color:#333}
        .fplms-rpt-export-btn{font-size:12px!important}
        .fplms-rpt-sub{font-size:14px;color:#444;margin:22px 0 8px;border-left:3px solid #ffa800;padding-left:10px}
        .fplms-rpt-channel-head{background:#fff8ee;border-radius:0 6px 6px 0;padding:8px 12px}
        .fplms-rpt-channel-count{font-size:12px;font-weight:400;color:#888}
        .fplms-rpt-metric{font-size:13px;color:#555;margin:4px 0 10px}
        .fplms-stat-cards{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 20px}
        .fplms-sc{background:#fff;border:1.5px solid #e8e8e8;border-radius:10px;padding:12px 18px;min-width:130px;flex:1;position:relative;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05)}
        .fplms-sc::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;border-radius:10px 0 0 10px}
        .fplms-sc--blue::before{background:#2196f3}.fplms-sc--green::before{background:#46b450}.fplms-sc--red::before{background:#dc3232}.fplms-sc--yellow::before{background:#ffb900}.fplms-sc--orange::before{background:#ffa800}
        .fplms-sc-label{font-size:10px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.5px;margin:0 0 6px}
        .fplms-sc-value{font-size:22px;color:#1a1a1a;margin:0;line-height:1.1}
        .fplms-sc-value strong{font-weight:700}
        .fplms-sc-sub{display:inline-block;margin-top:4px;font-size:11px;font-weight:600;padding:1px 7px;border-radius:20px;background:#f0f0f0;color:#555}
        .fplms-sv-block{background:#fff;border:1.5px solid #e8e8e8;border-radius:10px;padding:16px 18px;margin-bottom:18px;box-shadow:0 1px 4px rgba(0,0,0,.04)}
        .fplms-sv-block-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;border-bottom:1.5px solid #f0f0f0;padding-bottom:12px;margin-bottom:14px}
        .fplms-sv-block-title{font-size:14px;font-weight:700;color:#222}
        .fplms-sv-block-meta{display:flex;flex-wrap:wrap;gap:10px 18px;font-size:12px;color:#666}
        .fplms-sv-block-meta strong{color:#333}
        .fplms-sv-layout{display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap}
        .fplms-sv-chart-col{flex-shrink:0;display:flex;flex-direction:column;align-items:center;gap:6px}
        .fplms-sv-questions-col{flex:1;min-width:260px}
        .fplms-sv-qtable{font-size:12px;border-collapse:collapse;width:100%}
        .fplms-sv-qtable th{background:#f5f5f5;font-weight:600;font-size:11px;text-transform:uppercase;padding:6px 10px;white-space:nowrap}
        .fplms-sv-qtable td{padding:6px 10px;vertical-align:middle;border-bottom:1px solid #f0f0f0}
        .fplms-sv-qtable tr:last-child td{border-bottom:none}
        .fplms-sv-qnum{color:#aaa;font-size:11px;text-align:center}
        .fplms-sv-qcnt{text-align:center;color:#555}
        .fplms-rpt-empty{color:#999;font-size:13px;padding:10px;background:#f9f9f9;border-radius:4px}
        .fplms-rpt-table-wrap{overflow-x:auto;margin-bottom:14px}
        .fplms-rpt-table{font-size:12px;min-width:500px}
        .fplms-rpt-table th{background:#f5f5f5;font-weight:600;white-space:nowrap;font-size:11px;text-transform:uppercase}
        .fplms-rpt-table td{vertical-align:middle}
        .fplms-badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700}
        .fplms-badge.green{background:#d4edda;color:#155724}
        .fplms-badge.yellow{background:#fff3cd;color:#856404}
        .fplms-badge.red{background:#f8d7da;color:#721c24}
        .fplms-spin-dot{display:inline-block;width:16px;height:16px;border:2.5px solid #ffa80033;border-top-color:#ffa800;border-radius:50%;animation:fplms-spin .7s linear infinite;vertical-align:-4px;margin-right:6px}
        @keyframes fplms-spin{to{transform:rotate(360deg)}}
        .fplms-paged-wrap{margin-bottom:16px}
        .fplms-paged-controls{display:flex;align-items:center;gap:8px;margin-top:6px;flex-wrap:wrap;padding:6px 4px;background:#fafafa;border:1px solid #e8e8e8;border-radius:0 0 6px 6px}
        .fplms-paged-controls label{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#555;white-space:nowrap}
        .fplms-paged-controls select{appearance:none;-webkit-appearance:none;height:30px;font-size:13px;font-weight:700;color:#333;padding:0 28px 0 10px;border:1.5px solid #ffa800;border-radius:5px;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%23ffa800' d='M0 0l5 6 5-6z'/%3E%3C/svg%3E") no-repeat right 8px center;background-size:10px 6px;cursor:pointer;outline:none;min-width:68px}
        .fplms-paged-controls select:focus{border-color:#e08800;box-shadow:0 0 0 2px rgba(255,168,0,.2)}
        .fplms-paged-info{flex:1;font-size:12px;color:#777;text-align:center;min-width:120px}
        .fplms-paged-prev,.fplms-paged-next{font-size:12px!important;padding:4px 12px!important;line-height:22px!important;height:auto!important;border-color:#ffa800!important;color:#ffa800!important;font-weight:600!important}
        .fplms-paged-prev:hover,.fplms-paged-next:hover{background:#fff8ee!important;border-color:#e08800!important;color:#e08800!important}
        .fplms-paged-prev:disabled,.fplms-paged-next:disabled{opacity:.35;cursor:not-allowed;border-color:#ddd!important;color:#aaa!important}
        </style>
        <script>
        (function(){
            var NONCE=<?php echo wp_json_encode($nonce); ?>,AJAXURL=<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            var activeTab=null,searchTimer=null;
            function initSvCharts(charts){
                if(!charts||!charts.length)return;
                function render(){
                    charts.forEach(function(c){
                        var el=document.getElementById(c.id);
                        if(!el||el._fplmsChart)return;
                        el._fplmsChart=new Chart(el,{type:'pie',
                            data:{labels:c.labels,datasets:[{data:c.data,backgroundColor:c.colors,borderWidth:2,borderColor:'#fff',hoverOffset:4}]},
                            options:{responsive:false,plugins:{legend:{position:'bottom',labels:{font:{size:11},boxWidth:14,padding:8}}}}});
                    });
                }
                if(typeof Chart!=='undefined'){render();return;}
                if(window._fplmsSvJsLoading){var t=setInterval(function(){if(typeof Chart!=='undefined'){clearInterval(t);render();}},100);return;}
                window._fplmsSvJsLoading=true;
                var s=document.createElement('script');
                s.src='https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
                s.onload=render;document.head.appendChild(s);
            }
            function initPagedTables(container) {
                container.querySelectorAll('.fplms-paged-wrap').forEach(function(wrap) {
                    var rows = Array.from(wrap.querySelectorAll('tbody tr'));
                    if (!rows.length) return;
                    var perPage = 10, page = 1;
                    var sizeEl = wrap.querySelector('.fplms-paged-size');
                    var infoEl = wrap.querySelector('.fplms-paged-info');
                    var prevEl = wrap.querySelector('.fplms-paged-prev');
                    var nextEl = wrap.querySelector('.fplms-paged-next');
                    function render() {
                        var total = rows.length, pages = Math.ceil(total / perPage) || 1;
                        if (page > pages) page = pages;
                        var start = (page - 1) * perPage, end = Math.min(start + perPage, total);
                        rows.forEach(function(r, i) { r.style.display = (i >= start && i < end) ? '' : 'none'; });
                        infoEl.textContent = total + ' registros — pág. ' + page + ' / ' + pages;
                        prevEl.disabled = page <= 1;
                        nextEl.disabled = page >= pages;
                    }
                    sizeEl.addEventListener('change', function() { perPage = parseInt(this.value, 10); page = 1; render(); });
                    prevEl.addEventListener('click', function() { if (page > 1) { page--; render(); } });
                    nextEl.addEventListener('click', function() { if (page < Math.ceil(rows.length / perPage)) { page++; render(); } });
                    render();
                });
            }
            function updateFilterBar(){
                var bar=document.getElementById('fplms-active-filters-bar');if(!bar)return;
                var badges=[];
                var df=document.getElementById('fplms-rpt-date-from'),dt=document.getElementById('fplms-rpt-date-to');
                if(df&&df.value)badges.push('Desde: '+df.value);
                if(dt&&dt.value)badges.push('Hasta: '+dt.value);
                [{label:'Ciudad',id:'fplms-rpt-city'},{label:'Empresa',id:'fplms-rpt-company'},{label:'Canal',id:'fplms-rpt-channel'},{label:'Cargo',id:'fplms-rpt-role'}].forEach(function(s){
                    var el=document.getElementById(s.id);
                    if(el&&el.value&&el.selectedIndex>0)badges.push(s.label+': '+el.options[el.selectedIndex].text);
                });
                var uids=document.getElementById('fplms-rpt-user-ids'),uname=document.getElementById('fplms-rpt-user-search');
                if(uname&&uname.value){
                    if(uids&&uids.value) badges.push('Usuario: '+uname.value);
                    else badges.push('\u26a0\ufe0f Usuario: "'+uname.value+'" \u2014 selecciona del desplegable');
                }
                if(!badges.length){bar.style.display='none';bar.innerHTML='';return;}
                bar.style.display='flex';
                var ico='<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#c96800" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>';
                bar.innerHTML=ico+'<span class="fplms-fbar-lbl">Filtros activos:</span>'+badges.map(function(b){return '<span class="fplms-fbar-badge">'+b+'</span>';}).join('')+'<span class="fplms-fbar-note">Se aplican a todos los tabs</span>';
            }
            var tabActions={participation:'fplms_report_participation',progress:'fplms_report_progress',performance:'fplms_report_performance',certificates:'fplms_report_certificates',time:'fplms_report_time',satisfaction:'fplms_report_satisfaction',channels:'fplms_report_channels',tests:'fplms_report_tests'};
            var matrixState={search:'',page:1,perPage:10};
            var matrixSearchTimer=null;
            function loadMatrix(){
                var content=document.getElementById('fplms-rpt-content');
                content.innerHTML='<div class="fplms-rpt-loading"><span class="fplms-spin-dot"></span>Cargando matriz…</div>';
                var p=Object.assign({action:'fplms_report_matrix',nonce:NONCE,matrix_search:matrixState.search,matrix_page:matrixState.page,matrix_per_page:matrixState.perPage},getFilters());
                var body=Object.keys(p).map(function(k){return encodeURIComponent(k)+'='+encodeURIComponent(p[k]);}).join('&');
                fetch(AJAXURL,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body})
                    .then(function(r){return r.json();})
                    .then(function(res){content.innerHTML=res.success?res.data.html:'<p class="notice notice-error">'+(res.data?res.data.message:'Error')+'</p>';})
                    .catch(function(){content.innerHTML='<p class="notice notice-error">Error de conexión.</p>';});
            }
            function getFilters(){return{date_from:document.getElementById('fplms-rpt-date-from').value||'',date_to:document.getElementById('fplms-rpt-date-to').value||'',channel_id:document.getElementById('fplms-rpt-channel').value||'',city_id:document.getElementById('fplms-rpt-city').value||'',company_id:document.getElementById('fplms-rpt-company').value||'',role_id:document.getElementById('fplms-rpt-role').value||'',user_ids:document.getElementById('fplms-rpt-user-ids').value||''};}
            function loadReport(tab){
                if(!tab)return; activeTab=tab;
                if(tab==='matrix'){ matrixState={search:'',page:1,perPage:10}; loadMatrix(); return; }
                var action=tabActions[tab]; if(!action)return;
                var content=document.getElementById('fplms-rpt-content');
                content.innerHTML='<div class="fplms-rpt-loading"><span class="fplms-spin-dot"></span>Cargando reporte…</div>';
                var p=Object.assign({action:action,nonce:NONCE},getFilters());
                var body=Object.keys(p).map(k=>encodeURIComponent(k)+'='+encodeURIComponent(p[k])).join('&');
                fetch(AJAXURL,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body})
                    .then(r=>r.json()).then(res=>{content.innerHTML=res.success?res.data.html:'<p class="notice notice-error">'+(res.data?res.data.message:'Error')+'</p>'; initPagedTables(content); initSvCharts(res.data&&res.data.charts?res.data.charts:[]);})
                    .catch(()=>{content.innerHTML='<p class="notice notice-error">Error de conexion.</p>';});
            }
            document.querySelector('.fplms-rpt-tabs-nav').addEventListener('click',function(e){
                var btn=e.target.closest('.fplms-rpt-tab-btn'); if(!btn)return;
                document.querySelectorAll('.fplms-rpt-tab-btn').forEach(b=>b.classList.remove('active'));
                btn.classList.add('active'); updateFilterBar(); loadReport(btn.dataset.tab);
            });
            document.getElementById('fplms-rpt-apply').addEventListener('click',function(){
                var m=document.getElementById('fplms-rpt-modal');
                m.style.display='flex';
                updateFilterBar();
                if(activeTab) loadReport(activeTab);
            });
            document.getElementById('fplms-rpt-modal-close').addEventListener('click',function(){
                document.getElementById('fplms-rpt-modal').style.display='none';
            });
            document.getElementById('fplms-rpt-modal').addEventListener('click',function(e){
                if(e.target===this) this.style.display='none';
            });
            document.getElementById('fplms-rpt-reset').addEventListener('click',function(){
                ['fplms-rpt-date-from','fplms-rpt-date-to','fplms-rpt-city','fplms-rpt-company','fplms-rpt-channel','fplms-rpt-role'].forEach(id=>{var el=document.getElementById(id);if(el)el.value='';});
                document.getElementById('fplms-rpt-user-ids').value='';document.getElementById('fplms-rpt-user-search').value='';
                updateFilterBar();
                if(activeTab)loadReport(activeTab);
            });
            var us=document.getElementById('fplms-rpt-user-search'),sug=document.getElementById('fplms-rpt-suggestions');
            us.addEventListener('input',function(){
                var q=this.value.trim(); document.getElementById('fplms-rpt-user-ids').value='';
                updateFilterBar();
                // If the field was fully cleared, reload immediately without user filter
                if(q.length===0){sug.style.display='none';sug.innerHTML='';if(activeTab)loadReport(activeTab);return;}
                clearTimeout(searchTimer); if(q.length<2){sug.style.display='none';sug.innerHTML='';return;}
                searchTimer=setTimeout(function(){
                    var body='action=fplms_reports_user_search&nonce='+encodeURIComponent(NONCE)+'&q='+encodeURIComponent(q);
                    fetch(AJAXURL,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body})
                        .then(r=>r.json()).then(res=>{
                            sug.innerHTML='';
                            if(res.success&&res.data.users.length){res.data.users.forEach(u=>{var d=document.createElement('div');d.className='fplms-sug-item';d.textContent=u.label;d.dataset.id=u.id;sug.appendChild(d);});sug.style.display='block';}
                            else sug.style.display='none';
                        });
                },350);
            });
            sug.addEventListener('click',function(e){var it=e.target.closest('.fplms-sug-item');if(!it)return;us.value=it.textContent.trim();document.getElementById('fplms-rpt-user-ids').value=it.dataset.id;sug.style.display='none';updateFilterBar();if(activeTab)loadReport(activeTab);});
            document.addEventListener('click',function(e){if(!e.target.closest('.fplms-rpt-fg-user'))sug.style.display='none';});

            // — Test report: detail navigation, back, reset —
            var currentQuizId = null;
            function loadTestDetail(quizId) {
                currentQuizId = quizId;
                var content = document.getElementById('fplms-rpt-content');
                content.innerHTML = '<div class="fplms-rpt-loading"><span class="fplms-spin-dot"></span>Cargando detalle…</div>';
                var p = Object.assign({action:'fplms_report_test_detail',nonce:NONCE,quiz_id:quizId}, getFilters());
                var body = Object.keys(p).map(function(k){return encodeURIComponent(k)+'='+encodeURIComponent(p[k]);}).join('&');
                fetch(AJAXURL,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body})
                    .then(function(r){return r.json();})
                    .then(function(res){content.innerHTML = res.success ? res.data.html : '<p class="notice notice-error">'+(res.data?res.data.message:'Error')+'</p>';});
            }
            // — Confirm modal for reset —
            var _fplmsRptConfirmCb = null;
            function fplmsRptShowConfirm(opts) {
                document.getElementById('fplms-rpt-cmodal-header').className = 'fplms-rpt-cmodal-header fplms-rpt-cmodal-header--' + (opts.variant || 'red');
                document.getElementById('fplms-rpt-cmodal-icon').innerHTML = '<path d="' + (opts.iconPath || 'M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z') + '"/>';
                document.getElementById('fplms-rpt-cmodal-title').textContent = opts.title || '';
                document.getElementById('fplms-rpt-cmodal-text').innerHTML   = opts.bodyText || '';
                var nameEl = document.getElementById('fplms-rpt-cmodal-name');
                nameEl.textContent = opts.name || '';
                nameEl.style.display = opts.name ? '' : 'none';
                nameEl.className = 'fplms-rpt-cmodal-name' + (opts.variant === 'amber' ? ' fplms-rpt-cmodal-name--amber' : '');
                var subEl = document.getElementById('fplms-rpt-cmodal-sub');
                subEl.innerHTML = opts.subText || '';
                subEl.style.display = opts.subText ? '' : 'none';
                var btn = document.getElementById('fplms-rpt-cmodal-confirm');
                btn.textContent = opts.btnText || 'Confirmar';
                btn.className = 'fplms-rpt-cmodal-confirm' + (opts.variant === 'amber' ? ' fplms-rpt-cmodal-confirm--amber' : '');
                _fplmsRptConfirmCb = opts.onConfirm || null;
                document.getElementById('fplms-rpt-confirm-modal').classList.add('active');
            }
            document.getElementById('fplms-rpt-cmodal-cancel').addEventListener('click', function(){
                document.getElementById('fplms-rpt-confirm-modal').classList.remove('active');
                _fplmsRptConfirmCb = null;
            });
            document.getElementById('fplms-rpt-cmodal-confirm').addEventListener('click', function(){
                document.getElementById('fplms-rpt-confirm-modal').classList.remove('active');
                var cb = _fplmsRptConfirmCb; _fplmsRptConfirmCb = null;
                if (cb) cb();
            });
            document.getElementById('fplms-rpt-confirm-modal').addEventListener('click', function(e){
                if (e.target === this) { this.classList.remove('active'); _fplmsRptConfirmCb = null; }
            });

            function resetQuizAttempt(quizId, userId, userQuizId, attempt, userName) {
                fplmsRptShowConfirm({
                    variant : 'red',
                    iconPath: 'M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z',
                    title   : 'Eliminar intento #' + attempt,
                    bodyText: '\u00bfDeseas eliminar el <strong>intento #' + attempt + '</strong> de este estudiante?',
                    name    : userName,
                    subText : 'El estudiante recuperar\u00e1 ese intento disponible. <strong>Esta acci\u00f3n no se puede deshacer.</strong>',
                    btnText : 'S\u00ed, eliminar intento',
                    onConfirm: function() {
                        var body = 'action=fplms_report_test_reset&nonce='+encodeURIComponent(NONCE)
                                 + '&quiz_id='+encodeURIComponent(quizId)
                                 + '&user_id='+encodeURIComponent(userId)
                                 + '&user_quiz_id='+encodeURIComponent(userQuizId)
                                 + '&attempt='+encodeURIComponent(attempt);
                        fetch(AJAXURL,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body})
                            .then(function(r){return r.json();})
                            .then(function(res){
                                if (res.success) {
                                    if (currentQuizId) loadTestDetail(currentQuizId);
                                } else {
                                    alert('Error: '+(res.data ? res.data.message : 'Error desconocido'));
                                }
                            });
                    }
                });
            }
            document.getElementById('fplms-rpt-content').addEventListener('click', function(e) {
                var detBtn  = e.target.closest('.fplms-test-detail-btn');
                var backBtn = e.target.closest('#fplms-test-back-btn');
                var rstBtn  = e.target.closest('.fplms-test-reset-btn');
                var ansBtn  = e.target.closest('.fplms-test-answers-btn');
                var mxPrev  = e.target.closest('.fplms-mx-prev');
                var mxNext  = e.target.closest('.fplms-mx-next');
                if (detBtn)  { loadTestDetail(detBtn.dataset.quizId); }
                else if (backBtn) { currentQuizId = null; loadReport('tests'); }
                else if (rstBtn)  { resetQuizAttempt(rstBtn.dataset.quizId, rstBtn.dataset.userId, rstBtn.dataset.userQuizId, rstBtn.dataset.attempt, rstBtn.dataset.userName); }
                else if (ansBtn)  { showTestAnswers(ansBtn.dataset.quizId, ansBtn.dataset.userId, ansBtn.dataset.attempt, ansBtn.dataset.userName); }
                else if (mxPrev && !mxPrev.disabled) { matrixState.page--; loadMatrix(); }
                else if (mxNext && !mxNext.disabled) { matrixState.page++; loadMatrix(); }
            });
            document.getElementById('fplms-rpt-content').addEventListener('input', function(e) {
                if (e.target.id === 'fplms-mx-search') {
                    clearTimeout(matrixSearchTimer);
                    var val = e.target.value;
                    matrixSearchTimer = setTimeout(function(){ matrixState.search=val; matrixState.page=1; loadMatrix(); }, 420);
                }
            });
            document.getElementById('fplms-rpt-content').addEventListener('change', function(e) {
                if (e.target.id === 'fplms-mx-per-page') {
                    matrixState.perPage = parseInt(e.target.value, 10);
                    matrixState.page = 1;
                    loadMatrix();
                }
            });

            // — Test answers modal —
            (function(){
                var modal = document.createElement('div');
                modal.id = 'fplms-answers-modal';
                modal.style.cssText = 'display:none;position:fixed;inset:0;z-index:100001;background:rgba(0,0,0,.52);overflow-y:auto;padding:40px 20px';
                modal.innerHTML = '<div style="background:#fff;border-radius:10px;max-width:820px;margin:0 auto;padding:28px 32px;position:relative">'
                    + '<button id="fplms-answers-modal-close" style="position:absolute;top:10px;right:14px;background:none;border:none;font-size:26px;line-height:1;cursor:pointer;color:#999">&times;</button>'
                    + '<h3 id="fplms-answers-modal-title" style="margin:0 0 4px;font-size:15px;color:#1d2327"></h3>'
                    + '<div id="fplms-answers-modal-body" style="margin-top:14px"></div>'
                    + '</div>';
                document.body.appendChild(modal);
                document.getElementById('fplms-answers-modal-close').addEventListener('click', function(){ modal.style.display='none'; });
                modal.addEventListener('click', function(e){ if(e.target===modal) modal.style.display='none'; });
            })();

            function showTestAnswers(quizId, userId, attempt, userName) {
                var modal = document.getElementById('fplms-answers-modal');
                var title = document.getElementById('fplms-answers-modal-title');
                var body  = document.getElementById('fplms-answers-modal-body');
                title.textContent = 'Respuestas de ' + userName + ' \u2014 Intento #' + attempt;
                body.innerHTML = '<div class="fplms-rpt-loading"><span class="fplms-spin-dot"></span>Cargando respuestas\u2026</div>';
                modal.style.display = 'block';
                var postBody = 'action=fplms_report_test_answers&nonce='+encodeURIComponent(NONCE)+'&quiz_id='+encodeURIComponent(quizId)+'&user_id='+encodeURIComponent(userId)+'&attempt_number='+encodeURIComponent(attempt);
                fetch(AJAXURL,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:postBody})
                    .then(function(r){return r.json();})
                    .then(function(res){ body.innerHTML = res.success ? res.data.html : '<p class="notice notice-error">'+(res.data?res.data.message:'Error')+'</p>'; })
                    .catch(function(){ body.innerHTML = '<p class="notice notice-error">Error de conexi\u00f3n.</p>'; });
            }
        })();
        </script>
        <?php
    }
}
