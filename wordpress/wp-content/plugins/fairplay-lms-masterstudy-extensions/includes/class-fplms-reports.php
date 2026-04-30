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
        $date_w = $this->date_sql('u.user_registered', $f['date_from'], $f['date_to']);
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
        $html .= $this->sub('Usuarios Registrados ('.count($created).') — Activos:'.$act.' / Inactivos:'.$inact);
        $html .= $this->table_open(['Nombre','Email','Fecha Registro','Ciudad','Canal','Estado']);
        foreach ($created as $r) {
            $city    = $r->city_id    ? $this->structures->get_term_name_by_id((int)$r->city_id)    : '—';
            $channel = $r->channel_id ? $this->structures->get_term_name_by_id((int)$r->channel_id) : '—';
            $isact   = empty($r->user_status) || 'active' === $r->user_status;
            $html .= '<tr><td>'.esc_html($r->display_name).'</td><td>'.esc_html($r->user_email).'</td>'
                   . '<td>'.esc_html(wp_date('d/m/Y',strtotime($r->user_registered))).'</td>'
                   . '<td>'.esc_html($city).'</td><td>'.esc_html($channel).'</td>'
                   . '<td>'.($isact?$this->badge('Activo','green'):$this->badge('Inactivo','red')).'</td></tr>';
        }
        $html .= $this->table_close();

        // 1B. Frecuencia de acceso
        $login_table = $wpdb->prefix.'fplms_user_logins';
        $login_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$login_table)) === $login_table);
        $html .= $this->sub('Frecuencia de Acceso / N. de Ingresos al Sistema');
        if (!$login_exists) {
            $html .= $this->empty_msg('Tabla de logins no encontrada. Ejecuta el script create-activity-table.php.');
        } else {
            $ld  = $this->date_sql('l.login_time', $f['date_from'], $f['date_to']);
            $lw  = null!==$uids ? (empty($uids)?'AND 1=0':'AND l.user_id IN ('.implode(',',array_map('intval',$uids)).')') : '';
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $logins = (array)$wpdb->get_results(
                "SELECT u.display_name,u.user_email,COUNT(l.id) AS login_count,MAX(l.login_time) AS last_login
                 FROM {$wpdb->users} u INNER JOIN `{$login_table}` l ON u.ID=l.user_id
                 WHERE 1=1 $lw $ld GROUP BY u.ID ORDER BY login_count DESC LIMIT 500"
            );
            if (empty($logins)) { $html .= $this->empty_msg('Sin registros de login en el periodo seleccionado.'); }
            else {
                $html .= $this->table_open(['Nombre','Email','N. de Ingresos','Ultimo Ingreso']);
                foreach ($logins as $r) {
                    $html .= '<tr><td>'.esc_html($r->display_name).'</td><td>'.esc_html($r->user_email).'</td>'
                           . '<td><strong>'.(int)$r->login_count.'</strong></td>'
                           . '<td>'.esc_html($r->last_login?wp_date('d/m/Y H:i',strtotime($r->last_login)):'—').'</td></tr>';
                }
                $html .= $this->table_close();
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
            $html .= $this->metric('<strong>'.$ac.'</strong> inscripciones sin progreso'.($total_enroll?' de <strong>'.$total_enroll.'</strong> totales — tasa: <strong>'.$pct.'%</strong>':''));
            if (!empty($abandon)) {
                $html .= $this->table_open(['Usuario','Email','Curso','Progreso','Estado']);
                foreach ($abandon as $r) {
                    $html .= '<tr><td>'.esc_html($r->display_name).'</td><td>'.esc_html($r->user_email).'</td>'
                           . '<td>'.esc_html($r->course_name).'</td><td>'.(int)$r->progress_percent.'%</td>'
                           . '<td>'.$this->badge($this->translate_status($r->status??''),'yellow').'</td></tr>';
                }
                $html .= $this->table_close();
            }
        }

        // 1D. Dados de baja
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $inactive = (array)$wpdb->get_results(
            "SELECT u.ID,u.display_name,u.user_email,u.user_registered,
                    um_ci.meta_value AS city_id,um_ch.meta_value AS channel_id
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} um_st ON u.ID=um_st.user_id AND um_st.meta_key='fplms_user_status' AND um_st.meta_value='inactive'
             LEFT JOIN {$wpdb->usermeta} um_ci ON u.ID=um_ci.user_id AND um_ci.meta_key='fplms_city'
             LEFT JOIN {$wpdb->usermeta} um_ch ON u.ID=um_ch.user_id AND um_ch.meta_key='fplms_channel'
             WHERE 1=1 $uid_w ORDER BY u.display_name LIMIT 500"
        );
        $html .= $this->sub('Usuarios Dados de Baja / Inactivos ('.count($inactive).')');
        if (empty($inactive)) { $html .= $this->empty_msg('No hay usuarios dados de baja.'); }
        else {
            $html .= $this->table_open(['ID','Nombre','Email','Fecha Registro','Ciudad','Canal']);
            foreach ($inactive as $r) {
                $city    = $r->city_id    ? $this->structures->get_term_name_by_id((int)$r->city_id)    : '—';
                $channel = $r->channel_id ? $this->structures->get_term_name_by_id((int)$r->channel_id) : '—';
                $html .= '<tr><td>'.(int)$r->ID.'</td><td>'.esc_html($r->display_name).'</td><td>'.esc_html($r->user_email).'</td>'
                       . '<td>'.esc_html(wp_date('d/m/Y',strtotime($r->user_registered))).'</td>'
                       . '<td>'.esc_html($city).'</td><td>'.esc_html($channel).'</td></tr>';
            }
            $html .= $this->table_close();
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
            "SELECT p.ID AS course_id,p.post_title AS course_name,
                    COUNT(uc.user_id) AS enrolled,
                    ROUND(AVG(uc.progress_percent),1) AS avg_progress,
                    SUM(CASE WHEN uc.status IN('completed','passed') OR uc.progress_percent>=100 THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN uc.progress_percent>0 AND uc.progress_percent<100 AND uc.status NOT IN('completed','passed') THEN 1 ELSE 0 END) AS in_progress,
                    SUM(CASE WHEN uc.progress_percent<1 AND uc.status NOT IN('completed','passed') THEN 1 ELSE 0 END) AS not_started
             FROM {$wpdb->posts} p
             LEFT JOIN `{$ms}` uc ON p.ID=uc.course_id $mw
             WHERE p.post_type='stm-courses' AND p.post_status='publish'
             GROUP BY p.ID ORDER BY enrolled DESC, p.post_title"
        );
        $html .= $this->sub('Porcentaje de Avance por Curso');
        if (empty($cstats)) { $html.=$this->empty_msg('Sin datos.'); }
        else {
            $html .= $this->table_open(['Curso','Inscritos','Avance Prom.','Completados','Tasa Finalizacion','En Progreso','No Iniciados','Tasa Abandono']);
            foreach ($cstats as $r) {
                $en=(int)$r->enrolled; $ep=max(1,$en);
                $cp=(int)$r->completed; $ns=(int)$r->not_started;
                $fp=round($cp/$ep*100,1); $ap=round($ns/$ep*100,1);
                $fc=$fp>=70?'green':($fp>=40?'yellow':'red'); $ac=$ap<=20?'green':($ap<=50?'yellow':'red');
                $html .= '<tr><td>'.esc_html($r->course_name).'</td><td>'.$en.'</td><td>'.esc_html((string)$r->avg_progress).'%</td>'
                       . '<td>'.$cp.'</td><td>'.$this->badge($fp.'%',$fc).'</td>'
                       . '<td>'.(int)$r->in_progress.'</td><td>'.$ns.'</td><td>'.$this->badge($ap.'%',$ac).'</td></tr>';
            }
            $html .= $this->table_close();
        }

        // 2B. Mayor abandono top 10
        $html .= $this->sub('Capacitaciones con Mayor Abandono (Top 10)');
        $sorted = array_filter($cstats, fn($r)=>(int)$r->enrolled>0);
        usort($sorted, fn($a,$b)=>(int)$b->not_started/(max(1,(int)$b->enrolled)) <=> (int)$a->not_started/(max(1,(int)$a->enrolled)));
        $top10 = array_slice($sorted,0,10);
        if (!empty($top10)) {
            $html .= $this->table_open(['Curso','Inscritos','Sin Participacion','Tasa Abandono']);
            foreach ($top10 as $r) {
                $ep=max(1,(int)$r->enrolled); $pct=round((int)$r->not_started/$ep*100,1);
                $html .= '<tr><td>'.esc_html($r->course_name).'</td><td>'.(int)$r->enrolled.'</td>'
                       . '<td>'.(int)$r->not_started.'</td><td>'.$this->badge($pct.'%',$pct>50?'red':'yellow').'</td></tr>';
            }
            $html .= $this->table_close();
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
            $html .= $this->table_open(['Usuario','Email','Cursos Asignados','Avance Promedio','Completados','Pendientes']);
            foreach ($uprg as $r) {
                $t=max(1,(int)$r->total_courses); $cp=(int)$r->completed;
                $html .= '<tr><td>'.esc_html($r->display_name).'</td><td>'.esc_html($r->user_email).'</td>'
                       . '<td>'.$t.'</td><td>'.esc_html((string)$r->avg_progress).'%</td>'
                       . '<td>'.$cp.'</td><td>'.($t-$cp).'</td></tr>';
            }
            $html .= $this->table_close();
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
                "SELECT u.display_name,u.user_email,pc.post_title AS course_name,q.post_title AS quiz_name,uq.progress AS score,uq.status
                 FROM `{$quiz_table}` uq INNER JOIN {$wpdb->users} u ON uq.user_id=u.ID
                 LEFT JOIN {$wpdb->posts} q ON uq.quiz_id=q.ID LEFT JOIN {$wpdb->posts} pc ON uq.course_id=pc.ID
                 WHERE 1=1 $qw ORDER BY u.display_name,q.post_title LIMIT 500"
            );
            if (!empty($qrows)) {
                $passed=count(array_filter($qrows,fn($r)=>in_array(strtolower($r->status??''),['passed','completed'],true)));
                $failed=count(array_filter($qrows,fn($r)=>in_array(strtolower($r->status??''),['failed','not_passed'],true)));
                $tot=count($qrows); $pp=$tot>0?round($passed/$tot*100,1):0;
                $html .= $this->metric('Total:<strong>'.$tot.'</strong> | Aprobados:<strong>'.$passed.' ('.$pp.'%)</strong> | Reprobados:<strong>'.$failed.'</strong>');
                $html .= $this->table_open(['Usuario','Email','Curso','Evaluacion','Puntaje','Estado']);
                foreach ($qrows as $r) {
                    $st=strtolower($r->status??'');
                    $cl=in_array($st,['passed','completed'],true)?'green':(in_array($st,['failed','not_passed'],true)?'red':'yellow');
                    $html .= '<tr><td>'.esc_html($r->display_name).'</td><td>'.esc_html($r->user_email).'</td>'
                           . '<td>'.esc_html($r->course_name??'—').'</td><td>'.esc_html($r->quiz_name??'—').'</td>'
                           . '<td>'.esc_html((string)$r->score).'</td><td>'.$this->badge($this->translate_status($r->status??''),$cl).'</td></tr>';
                }
                $html .= $this->table_close();
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
            $html .= $this->metric('<strong>'.count($exp).'</strong> usuarios con cursos vencidos sin completar');
            if (!empty($exp)) {
                $html .= $this->table_open(['Usuario','Email','Curso','Progreso','Dias Vigencia','Vencio el']);
                foreach ($exp as $r) {
                    $html .= '<tr><td>'.esc_html($r->display_name).'</td><td>'.esc_html($r->user_email).'</td>'
                           . '<td>'.esc_html($r->course_name).'</td><td>'.(int)$r->progress_percent.'%</td>'
                           . '<td>'.(int)$r->end_days.' dias</td>'
                           . '<td>'.$this->badge(wp_date('d/m/Y',strtotime($r->expiry_date)),'red').'</td></tr>';
                }
                $html .= $this->table_close();
            } else { $html.=$this->empty_msg('No hay cursos vencidos sin completar.'); }
        } else { $html.=$this->empty_msg('Tabla de matriculas no encontrada.'); }

        // 3C. Mayor cumplimiento
        $html .= $this->sub('Capacitaciones con Mayor Cumplimiento (Top 10)');
        if ($ms) {
            $mw3=null!==$uids?(empty($uids)?'AND uc.user_id=0':'AND uc.user_id IN ('.implode(',',array_map('intval',$uids)).')'):'';
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $tc=(array)$wpdb->get_results(
                "SELECT p.post_title AS course_name,COUNT(uc.user_id) AS enrolled,
                        SUM(CASE WHEN uc.status IN('completed','passed') OR uc.progress_percent>=100 THEN 1 ELSE 0 END) AS completed,
                        ROUND(SUM(CASE WHEN uc.status IN('completed','passed') OR uc.progress_percent>=100 THEN 1 ELSE 0 END)/COUNT(uc.user_id)*100,1) AS rate
                 FROM {$wpdb->posts} p INNER JOIN `{$ms}` uc ON p.ID=uc.course_id
                 WHERE p.post_type='stm-courses' AND p.post_status='publish' AND uc.user_id>0 $mw3
                 GROUP BY p.ID HAVING enrolled>=1 ORDER BY rate DESC LIMIT 10"
            );
            if (!empty($tc)) {
                $html .= $this->table_open(['Curso','Inscritos','Completados','Tasa Cumplimiento']);
                foreach ($tc as $r) {
                    $html .= '<tr><td>'.esc_html($r->course_name).'</td><td>'.(int)$r->enrolled.'</td>'
                           . '<td>'.(int)$r->completed.'</td><td>'.$this->badge($r->rate.'%',$r->rate>=70?'green':($r->rate>=40?'yellow':'red')).'</td></tr>';
                }
                $html .= $this->table_close();
            } else { $html.=$this->empty_msg('Sin datos.'); }
        }
        wp_send_json_success(['html'=>$html]);
    }

    // REPORT 4 — CERTIFICADOS
    public function ajax_report_certificates(): void {
        if (!$this->verify_request()) return;
        global $wpdb;
        $f=$this->get_filters(); $uids=$this->get_user_ids_for_filters($f);
        $u_in=null!==$uids?(empty($uids)?'AND 1=0':'AND pm_uid.meta_value IN ('.implode(',',array_map('intval',$uids)).')'):'';
        $dw=$this->date_sql('p.post_date',$f['date_from'],$f['date_to']);
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $certs=(array)$wpdb->get_results(
            "SELECT u.display_name,u.user_email,pc.post_title AS course_name,p.post_date AS issued_date,pm_dl.meta_value AS downloaded
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_uid ON p.ID=pm_uid.post_id AND pm_uid.meta_key='user_id'
             INNER JOIN {$wpdb->users} u ON pm_uid.meta_value=u.ID
             LEFT JOIN {$wpdb->postmeta} pm_cid ON p.ID=pm_cid.post_id AND pm_cid.meta_key='course_id'
             LEFT JOIN {$wpdb->posts} pc ON pm_cid.meta_value=pc.ID
             LEFT JOIN {$wpdb->postmeta} pm_dl ON p.ID=pm_dl.post_id AND pm_dl.meta_key='certificate_downloaded'
             WHERE p.post_type IN('stm-certificates','stm-certificate') AND p.post_status!='trash'
             $u_in $dw ORDER BY p.post_date DESC LIMIT 500"
        );
        $html = $this->render_section_head('Reporte de Certificacion', $this->build_export_url('certificates',$f));
        $dl=count(array_filter($certs,fn($r)=>!empty($r->downloaded)));
        $html .= $this->metric('Total emitidos:<strong>'.count($certs).'</strong> | Descargados:<strong>'.$dl.'</strong>');
        $by=[]; foreach ($certs as $r) { $cn=$r->course_name?:'(Sin curso)'; $by[$cn]=($by[$cn]??0)+1; }
        arsort($by);
        if (!empty($by)) {
            $html .= $this->sub('Certificados por Curso');
            $html .= $this->table_open(['Curso','Certificados Emitidos']);
            foreach ($by as $cn=>$cnt) $html.='<tr><td>'.esc_html($cn).'</td><td><strong>'.(int)$cnt.'</strong></td></tr>';
            $html .= $this->table_close();
        }
        $html .= $this->sub('Detalle de Certificados Emitidos');
        if (!empty($certs)) {
            $html .= $this->table_open(['Usuario','Email','Curso','Fecha Emision','Descargado']);
            foreach ($certs as $r) {
                $dl2=!empty($r->downloaded)?$this->badge('Si','green'):$this->badge('No','yellow');
                $html .= '<tr><td>'.esc_html($r->display_name).'</td><td>'.esc_html($r->user_email).'</td>'
                       . '<td>'.esc_html($r->course_name?:'—').'</td>'
                       . '<td>'.esc_html(wp_date('d/m/Y',strtotime($r->issued_date))).'</td>'
                       . '<td>'.$dl2.'</td></tr>';
            }
            $html .= $this->table_close();
        } else { $html.=$this->empty_msg('No se encontraron certificados.'); }
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
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $trows=(array)$wpdb->get_results(
            "SELECT u.display_name,u.user_email,p.post_title AS course_name,
                    COALESCE(CAST(pm_vd.meta_value AS DECIMAL(10,2)),0) AS video_hours,
                    COALESCE(CAST(pm_di.meta_value AS DECIMAL(10,2)),0) AS duration_hours
             FROM {$wpdb->users} u INNER JOIN `{$ms}` uc ON u.ID=uc.user_id
             INNER JOIN {$wpdb->posts} p ON uc.course_id=p.ID AND p.post_status='publish'
             LEFT JOIN {$wpdb->postmeta} pm_vd ON p.ID=pm_vd.post_id AND pm_vd.meta_key='video_duration'
             LEFT JOIN {$wpdb->postmeta} pm_di ON p.ID=pm_di.post_id AND pm_di.meta_key='duration_info'
             WHERE (uc.status IN('completed','passed') OR uc.progress_percent>=100) $mw
             ORDER BY u.display_name,p.post_title LIMIT 500"
        );
        $by=[];
        foreach ($trows as $r) {
            $k=(string)$r->display_name.'|'.(string)$r->user_email;
            if (!isset($by[$k])) $by[$k]=['name'=>$r->display_name,'email'=>$r->user_email,'hours'=>0.0,'courses'=>0];
            $by[$k]['hours']  += max((float)$r->video_hours,(float)$r->duration_hours);
            $by[$k]['courses']++;
        }
        uasort($by,fn($a,$b)=>$b['hours']<=>$a['hours']);
        $th=array_sum(array_column($by,'hours'));
        $html.=$this->metric('Total horas capacitacion (cursos aprobados):<strong>'.round($th,1).' h</strong>');
        $html.=$this->sub('Horas por Usuario (Cursos Aprobados)');
        if (empty($by)) { $html.=$this->empty_msg('Sin datos de horas.'); }
        else {
            $html.=$this->table_open(['Usuario','Email','Cursos Aprobados','Horas Totales']);
            foreach ($by as $row) {
                $html.='<tr><td>'.esc_html($row['name']).'</td><td>'.esc_html($row['email']).'</td>'
                      .'<td>'.(int)$row['courses'].'</td><td><strong>'.round($row['hours'],1).' h</strong></td></tr>';
            }
            $html.=$this->table_close();
        }
        $html.=$this->sub('Detalle por Usuario y Curso');
        if (!empty($trows)) {
            $html.=$this->table_open(['Usuario','Email','Curso','Horas']);
            foreach ($trows as $r) {
                $h=round(max((float)$r->video_hours,(float)$r->duration_hours),1);
                $html.='<tr><td>'.esc_html($r->display_name).'</td><td>'.esc_html($r->user_email).'</td>'
                      .'<td>'.esc_html($r->course_name).'</td><td>'.$h.' h</td></tr>';
            }
            $html.=$this->table_close();
        }
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
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $srows=(array)$wpdb->get_results(
            "SELECT u.display_name,u.user_email,p.post_title AS course_name,sr.answers,sr.submitted_at
             FROM `{$st}` sr INNER JOIN {$wpdb->users} u ON sr.user_id=u.ID
             LEFT JOIN {$wpdb->posts} p ON sr.course_id=p.ID
             WHERE 1=1 $uw $dw ORDER BY sr.submitted_at DESC LIMIT 300"
        );
        $html.=$this->metric('Total respuestas:<strong>'.count($srows).'</strong>');
        $cs=[];
        foreach ($srows as $r) {
            $cn=$r->course_name?:'(Sin curso)'; if (!isset($cs[$cn])) $cs[$cn]=['total'=>0.0,'count'=>0];
            $ans=maybe_unserialize($r->answers);
            if (is_array($ans)) foreach ($ans as $v) if (is_numeric($v)) { $cs[$cn]['total']+=(float)$v; $cs[$cn]['count']++; }
        }
        if (!empty($cs)) {
            $html.=$this->sub('Puntuacion Promedio por Curso');
            $html.=$this->table_open(['Curso','Respuestas','Puntuacion Promedio']);
            foreach ($cs as $cn=>$d) {
                $avg=$d['count']>0?round($d['total']/$d['count'],2):0;
                $html.='<tr><td>'.esc_html($cn).'</td><td>'.(int)$d['count'].'</td>'
                      .'<td>'.$this->badge($avg.' / 5',$avg>=4?'green':($avg>=2.5?'yellow':'red')).'</td></tr>';
            }
            $html.=$this->table_close();
        }
        $html.=$this->sub('Detalle de Respuestas');
        if (!empty($srows)) {
            $html.=$this->table_open(['Usuario','Curso','Fecha','Respuestas']);
            foreach ($srows as $r) {
                $ans=maybe_unserialize($r->answers);
                $at=is_array($ans)?implode(' | ',array_map('esc_html',$ans)):esc_html((string)$r->answers);
                $html.='<tr><td>'.esc_html($r->display_name).'</td><td>'.esc_html($r->course_name?:'—').'</td>'
                      .'<td>'.esc_html(wp_date('d/m/Y',strtotime($r->submitted_at))).'</td><td>'.$at.'</td></tr>';
            }
            $html.=$this->table_close();
        } else { $html.=$this->empty_msg('Sin respuestas para los filtros seleccionados.'); }
        wp_send_json_success(['html'=>$html]);
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
            $html.=$this->table_open(['Curso','Usuarios del Canal','Inscritos en Curso','Completados','Tasa Cumplimiento']);
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
            $html.=$this->table_close();
            $cr2=$cen>0?round($ccp/$cen*100,1):0; $cc=$cr2>=70?'green':($cr2>=40?'yellow':'red');
            $html.='<p class="fplms-rpt-metric" style="text-align:right;margin-top:-8px;">Cumplimiento global del canal: '.$this->badge($cr2.'%',$cc).'</p>';
            $comp[$ch->name]=['courses'=>count($courses),'enrolled'=>$cen,'completed'=>$ccp,'rate'=>$cr2];
        }
        if (count($comp)>1) {
            $html.='<hr style="margin:28px 0;border-color:#eee;">'.$this->sub('Comparacion Global entre Canales');
            $html.=$this->table_open(['Canal','Cursos','Total Inscripciones','Completadas','Tasa Cumplimiento']);
            foreach ($comp as $nm=>$cd) {
                $rt=$cd['rate']??0; $rc=$rt>=70?'green':($rt>=40?'yellow':'red');
                $html.='<tr><td><strong>'.esc_html($nm).'</strong></td><td>'.(int)$cd['courses'].'</td>'
                      .'<td>'.(int)$cd['enrolled'].'</td><td>'.(int)$cd['completed'].'</td>'
                      .'<td>'.$this->badge($rt.'%',$rc).'</td></tr>';
            }
            $html.=$this->table_close();
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
        $uw = $this->uid_in($uids, 'uq.user_id');
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
             ORDER BY q.post_title"
        );
        if (empty($rows)) {
            wp_send_json_success(['html' => $html.$this->empty_msg('No se encontraron evaluaciones publicadas.')]);
            return;
        }
        $total_q   = count($rows);
        $total_att = (int)array_sum(array_column($rows, 'total_attempts'));
        $total_app = (int)array_sum(array_column($rows, 'approved'));
        $html .= $this->metric('<strong>'.$total_q.'</strong> evaluaciones | <strong>'.$total_att.'</strong> ejecuciones totales | <strong>'.$total_app.'</strong> aprobados');
        $det_ico = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:3px"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>';
        $html .= $this->table_open(['#', 'Test', 'Curso', 'Completado', 'Aprobado', 'Puntuación Media', 'Opciones']);
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
        $html .= $this->table_close();
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

        // Detect whether time columns exist
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $qt_cols   = array_column((array)$wpdb->get_results("SHOW COLUMNS FROM `{$quiz_table}`"), 'Field');
        $has_times = in_array('start_time', $qt_cols, true) && in_array('end_time', $qt_cols, true);
        $time_cols = $has_times ? ', uq.start_time, uq.end_time' : ', NULL AS start_time, NULL AS end_time';

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
                        uq.progress AS score, uq.status {$time_cols}
                 FROM `{$ms}` uc
                 INNER JOIN {$wpdb->users} u ON uc.user_id = u.ID
                 LEFT JOIN `{$quiz_table}` uq ON uq.user_id = uc.user_id AND uq.quiz_id = %d
                 WHERE uc.course_id = %d {$uw_uc}
                 ORDER BY u.display_name",
                $quiz_id, $course_id
            ));
        }

        // Strategy B fallback: only users who have a quiz record
        if (empty($rows)) {
            $uw_uq = $this->uid_in($uids, 'uq.user_id');
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
            $rows = (array)$wpdb->get_results($wpdb->prepare(
                "SELECT u.ID AS user_id, u.display_name, u.user_email,
                        uq.progress AS score, uq.status {$time_cols}
                 FROM `{$quiz_table}` uq
                 INNER JOIN {$wpdb->users} u ON uq.user_id = u.ID
                 WHERE uq.quiz_id = %d {$uw_uq}
                 ORDER BY u.display_name",
                $quiz_id
            ));
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

        // Build pie chart + table in two-column layout
        $pie = $this->render_pie_chart([
            ['label' => 'Aprobados',   'value' => $approved, 'color' => '#22c55e'],
            ['label' => 'Reprobados',  'value' => $failed,   'color' => '#ef4444'],
            ['label' => 'Sin iniciar', 'value' => $pending,  'color' => '#94a3b8'],
        ]);

        $can_reset = current_user_can('manage_options');
        $reset_ico = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:3px"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.39"/></svg>';

        $table  = $this->table_open(['Usuario', 'Fecha', 'Resultado', 'Puntuación', 'Hora', 'Opciones']);
        foreach ($rows as $r) {
            $st       = strtolower($r->status ?? '');
            $cl       = in_array($st, ['passed','completed'], true) ? 'green' : (in_array($st, ['failed','not_passed'], true) ? 'red' : 'yellow');
            $label    = in_array($st, ['passed','completed'], true) ? 'APROBADO' : (in_array($st, ['failed','not_passed'], true) ? 'REPROBADO' : 'SIN INICIAR');
            $end_ts   = (int)($r->end_time ?? 0);
            $start_ts = (int)($r->start_time ?? 0);
            $fecha    = $end_ts > 0 ? wp_date('d/m/Y, H:i:s', $end_ts) : ($start_ts > 0 ? wp_date('d/m/Y, H:i:s', $start_ts) : '—');
            $dur      = ($end_ts > 0 && $start_ts > 0 && $end_ts > $start_ts) ? $this->format_duration($end_ts - $start_ts) : '—';
            $score    = $r->score !== null ? number_format((float)$r->score, 2).'%' : '—';
            $opts     = $can_reset
                ? '<button class="button button-small fplms-test-reset-btn" data-quiz-id="'.esc_attr($quiz_id).'" data-user-id="'.esc_attr($r->user_id).'" data-user-name="'.esc_attr($r->display_name).'" style="color:#c00">'.$reset_ico.'Resetear</button>'
                : '—';
            $table .= '<tr>'
                    . '<td><strong>'.esc_html($r->display_name).'</strong><br><small style="color:#888">'.esc_html($r->user_email).'</small></td>'
                    . '<td style="white-space:nowrap">'.esc_html($fecha).'</td>'
                    . '<td>'.$this->badge($label, $cl).'</td>'
                    . '<td>'.esc_html($score).'</td>'
                    . '<td style="white-space:nowrap">'.esc_html($dur).'</td>'
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
        $quiz_id = isset($_POST['quiz_id']) ? (int)$_POST['quiz_id'] : 0;
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        if (!$quiz_id || !$user_id) { wp_send_json_error(['message' => 'Parámetros inválidos.']); return; }
        $quiz_table = null;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix.'stm_lms_user_quizzes')) === $wpdb->prefix.'stm_lms_user_quizzes')
            $quiz_table = $wpdb->prefix.'stm_lms_user_quizzes';
        if (!$quiz_table) { wp_send_json_error(['message' => 'Tabla de evaluaciones no encontrada.']); return; }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $deleted = $wpdb->delete($quiz_table, ['user_id' => $user_id, 'quiz_id' => $quiz_id], ['%d', '%d']);
        if (false === $deleted) {
            wp_send_json_error(['message' => 'Error al resetear: '.$wpdb->last_error]); return;
        }
        wp_send_json_success(['message' => 'Evaluación reseteada correctamente.', 'deleted' => (int)$deleted]);
    }

    // HELPER — SVG donut pie chart. $slices = [['label'=>'', 'value'=>0, 'color'=>'#hex'], ...]
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

                // 1B – Login frequency (date filter on login_time)
                $login_table  = $wpdb->prefix . 'fplms_user_logins';
                $login_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $login_table)) === $login_table);
                $logins = [];
                if ($login_exists) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    $logins = (array) $wpdb->get_results(
                        "SELECT u.display_name,u.user_email,COUNT(l.id) AS login_count,MAX(l.login_time) AS last_login
                         FROM {$wpdb->users} u INNER JOIN `{$login_table}` l ON u.ID=l.user_id
                         WHERE 1=1 $lw $ldw GROUP BY u.ID ORDER BY login_count DESC LIMIT 5000"
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
                    "SELECT u.display_name,u.user_email,u.user_registered,
                            um_ci.meta_value AS city_id,um_ch.meta_value AS channel_id
                     FROM {$wpdb->users} u
                     INNER JOIN {$wpdb->usermeta} um_st ON u.ID=um_st.user_id
                         AND um_st.meta_key='fplms_user_status' AND um_st.meta_value='inactive'
                     LEFT JOIN {$wpdb->usermeta} um_ci ON u.ID=um_ci.user_id AND um_ci.meta_key='fplms_city'
                     LEFT JOIN {$wpdb->usermeta} um_ch ON u.ID=um_ch.user_id AND um_ch.meta_key='fplms_channel'
                     WHERE 1=1 $uw ORDER BY u.display_name LIMIT 5000"
                );

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
                        'title'   => 'Frecuencia de Acceso / N. de Ingresos al Sistema',
                        'headers' => ['Nombre', 'Email', 'N. de Ingresos', 'Ultimo Ingreso'],
                        'data'    => array_map(fn($r) => [
                            $r->display_name,
                            $r->user_email,
                            (int)$r->login_count,
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
                        'headers' => ['Nombre', 'Email', 'Fecha Registro', 'Ciudad', 'Canal'],
                        'data'    => array_map(fn($r) => [
                            $r->display_name,
                            $r->user_email,
                            wp_date('d/m/Y', strtotime($r->user_registered)),
                            $r->city_id    ? $this->structures->get_term_name_by_id((int)$r->city_id)    : '',
                            $r->channel_id ? $this->structures->get_term_name_by_id((int)$r->channel_id) : '',
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
                        "SELECT u.display_name,u.user_email,
                                pc.post_title AS course_name,q.post_title AS quiz_name,
                                uq.progress AS score,uq.status
                         FROM `{$quiz_table}` uq
                         INNER JOIN {$wpdb->users} u ON uq.user_id=u.ID
                         LEFT JOIN {$wpdb->posts} q  ON uq.quiz_id=q.ID
                         LEFT JOIN {$wpdb->posts} pc ON uq.course_id=pc.ID
                         WHERE 1=1 $qw ORDER BY u.display_name,q.post_title LIMIT 5000"
                    );
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
                        'title'   => 'Resultados de Evaluaciones (' . count($quizzes) . ')',
                        'headers' => ['Usuario', 'Email', 'Curso', 'Evaluacion', 'Puntaje', 'Estado'],
                        'data'    => array_map(fn($r) => [
                            $r->display_name, $r->user_email,
                            $r->course_name ?? '—', $r->quiz_name ?? '—',
                            $r->score, $r->status ?? '—',
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
                $cert_in = null !== $uids ? (empty($uids) ? 'AND 1=0' : 'AND pm_uid.meta_value IN (' . implode(',', array_map('intval', $uids)) . ')') : '';
                $dw      = $this->date_sql('p.post_date', $f['date_from'], $f['date_to']);
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $certs = (array) $wpdb->get_results(
                    "SELECT u.display_name,u.user_email,pc.post_title AS course,
                            p.post_date,pm_dl.meta_value AS downloaded
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm_uid ON p.ID=pm_uid.post_id AND pm_uid.meta_key='user_id'
                     INNER JOIN {$wpdb->users} u ON pm_uid.meta_value=u.ID
                     LEFT JOIN {$wpdb->postmeta} pm_cid ON p.ID=pm_cid.post_id AND pm_cid.meta_key='course_id'
                     LEFT JOIN {$wpdb->posts} pc ON pm_cid.meta_value=pc.ID
                     LEFT JOIN {$wpdb->postmeta} pm_dl ON p.ID=pm_dl.post_id AND pm_dl.meta_key='certificate_downloaded'
                     WHERE p.post_type IN('stm-certificates','stm-certificate') AND p.post_status!='trash'
                     $cert_in $dw ORDER BY p.post_date DESC LIMIT 5000"
                );
                $this->output_xls_multi('certificados', 'Reporte de Certificacion', [
                    [
                        'title'   => 'Certificados Emitidos (' . count($certs) . ')',
                        'headers' => ['Usuario', 'Email', 'Curso', 'Fecha Emision', 'Descargado'],
                        'data'    => array_map(fn($r) => [
                            $r->display_name, $r->user_email,
                            $r->course ?: '—',
                            wp_date('d/m/Y', strtotime($r->post_date)),
                            !empty($r->downloaded) ? 'Si' : 'No',
                        ], $certs),
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
                    "SELECT u.display_name,u.user_email,p.post_title AS course_name,sr.answers,sr.submitted_at
                     FROM `{$st}` sr
                     INNER JOIN {$wpdb->users} u ON sr.user_id=u.ID
                     LEFT JOIN {$wpdb->posts} p ON sr.course_id=p.ID
                     WHERE 1=1 $sw $dw_sat ORDER BY sr.submitted_at DESC LIMIT 5000"
                );
                $this->output_xls_multi('satisfaccion', 'Reporte de Satisfaccion', [
                    [
                        'title'   => 'Respuestas de Encuestas (' . count($srows) . ')',
                        'headers' => ['Usuario', 'Email', 'Curso', 'Respuestas', 'Fecha'],
                        'data'    => array_map(fn($r) => [
                            $r->display_name, $r->user_email, $r->course_name ?? '—',
                            is_array(maybe_unserialize($r->answers))
                                ? implode(' | ', maybe_unserialize($r->answers))
                                : $r->answers,
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
                $qt_cols_e  = array_column((array)$wpdb->get_results("SHOW COLUMNS FROM `{$qt_e}`"), 'Field');
                $time_sel_e = in_array('start_time', $qt_cols_e, true) && in_array('end_time', $qt_cols_e, true)
                    ? ', uq.start_time, uq.end_time' : ', 0 AS start_time, 0 AS end_time';
                $quiz_title_e = get_the_title($quiz_id_e) ?: 'Test #'.$quiz_id_e;
                $uw_q_e = $this->uid_in($uids, 'uq.user_id');
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
                $det_rows = (array)$wpdb->get_results($wpdb->prepare(
                    "SELECT u.display_name, u.user_email,
                            uq.progress AS score, uq.status {$time_sel_e},
                            MAX(pc.post_title) AS course_name
                     FROM `{$qt_e}` uq
                     INNER JOIN {$wpdb->users} u ON uq.user_id = u.ID
                     LEFT JOIN {$wpdb->posts} pc ON uq.course_id = pc.ID
                     WHERE uq.quiz_id = %d $uw_q_e
                     GROUP BY uq.user_id, u.display_name, u.user_email, uq.progress, uq.status
                     ORDER BY u.display_name",
                    $quiz_id_e
                ));
                $det_data = [];
                foreach ($det_rows as $r) {
                    $st_e    = strtolower($r->status ?? '');
                    $lbl_e   = in_array($st_e, ['passed','completed'], true) ? 'Aprobado' : (in_array($st_e, ['failed','not_passed'], true) ? 'Reprobado' : 'Sin iniciar');
                    $end_e   = (int)($r->end_time ?? 0);
                    $start_e = (int)($r->start_time ?? 0);
                    $fecha_e = $end_e > 0 ? wp_date('d/m/Y H:i:s', $end_e) : ($start_e > 0 ? wp_date('d/m/Y H:i:s', $start_e) : '—');
                    $dur_e   = ($end_e > 0 && $start_e > 0 && $end_e > $start_e) ? $this->format_duration($end_e - $start_e) : '—';
                    $det_data[] = [$r->display_name, $r->user_email, $r->course_name ?? '—', $fecha_e, $lbl_e, number_format((float)$r->score, 2), $dur_e];
                }
                $this->output_xls_multi('test_detalle_'.$quiz_id_e, 'Detalle: '.$quiz_title_e, [[
                    'title'   => 'Resultados ('.count($det_rows).' usuarios)',
                    'headers' => ['Usuario', 'Email', 'Curso', 'Fecha', 'Resultado', 'Puntuacion %', 'Tiempo'],
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

            // -- DEFAULT --------------------------------------------------
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

        // Max columns (for title/meta merge span)
        $max_cols = 1;
        foreach ($sections as $s) $max_cols = max($max_cols, count($s['headers']));
        $merge_all = max(0, $max_cols - 1);

        $body  = '';
        $body .= '<Row ss:Height="28"><Cell ss:StyleID="s_title" ss:MergeAcross="' . $merge_all . '">'
               . '<Data ss:Type="String">' . $xe($report_title) . '</Data></Cell></Row>';
        $body .= '<Row ss:Height="16"><Cell ss:StyleID="s_meta" ss:MergeAcross="' . $merge_all . '">'
               . '<Data ss:Type="String">' . $xe($meta) . '</Data></Cell></Row>';

        foreach ($sections as $s) {
            $ncols = count($s['headers']);
            $merge = max(0, $ncols - 1);

            // Spacer
            $body .= '<Row ss:Height="10"/>';

            // Section heading (orange #FFA800)
            $body .= '<Row ss:Height="22"><Cell ss:StyleID="s_sec" ss:MergeAcross="' . $merge . '">'
                   . '<Data ss:Type="String">' . $xe($s['title']) . '</Data></Cell></Row>';

            // Column headers (dark orange #CC8600)
            $hdr = implode('', array_map(
                fn($h) => '<Cell ss:StyleID="s_hdr"><Data ss:Type="String">' . $xe($h) . '</Data></Cell>',
                $s['headers']
            ));
            $body .= '<Row ss:Height="20">' . $hdr . '</Row>';

            // Data rows
            if (empty($s['data'])) {
                $body .= '<Row ss:Height="18"><Cell ss:StyleID="s_even" ss:MergeAcross="' . $merge . '">'
                       . '<Data ss:Type="String">(Sin datos para los filtros seleccionados)</Data></Cell></Row>';
            } else {
                $i = 0;
                foreach ($s['data'] as $row) {
                    $sty  = ($i % 2 === 0) ? 's_even' : 's_odd';
                    $body .= '<Row ss:Height="18">';
                    foreach ((array)$row as $cell) {
                        $v    = (string)$cell;
                        $type = (is_numeric($v) && !preg_match('/^0\d/', $v) && $v !== '') ? 'Number' : 'String';
                        $body .= '<Cell ss:StyleID="' . $sty . '"><Data ss:Type="' . $type . '">' . $xe($v) . '</Data></Cell>';
                    }
                    $body .= '</Row>';
                    $i++;
                }
            }
        }

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

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<?mso-application progid="Excel.Sheet"?>' . "\n"
             . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
             . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
             . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
             . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
             . $styles
             . '<Worksheet ss:Name="Reporte"><Table>'
             . $body
             . '</Table></Worksheet></Workbook>';

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
            'tests'=>'Tests',
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
            <div class="fplms-rpt-tabs-nav">
                <?php foreach ($tabs as $slug=>$label): ?>
                <button class="fplms-rpt-tab-btn" data-tab="<?php echo esc_attr($slug); ?>"><?php echo ($icons[$slug]??'') . esc_html($label); ?></button>
                <?php endforeach; ?>
            </div>
            <div id="fplms-rpt-content" class="fplms-rpt-content">
                <div class="fplms-rpt-placeholder"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#ddd" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="display:block;margin:0 auto 12px"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 9h6M9 12h6M9 15h4"/></svg><p>Selecciona una pestana de reporte para comenzar.</p></div>
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
        #fplms-rpt-suggestions{position:absolute;top:calc(100% + 2px);left:0;right:0;background:#fff;border:1.5px solid #ddd;border-radius:0 0 6px 6px;z-index:200;max-height:200px;overflow-y:auto;display:none;box-shadow:0 4px 12px rgba(0,0,0,.1)}
        .fplms-sug-item{padding:7px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid #f0f0f0}
        .fplms-sug-item:hover{background:#fff8ee}
        .fplms-rpt-tabs-nav{display:flex;flex-wrap:wrap;gap:3px;border-bottom:2px solid #ffa800;margin-bottom:0}
        .fplms-rpt-tab-btn{padding:10px 16px;border:1.5px solid #e0e0e0;border-bottom:none;background:#f5f5f5;cursor:pointer;font-size:13px;font-weight:500;color:#555;border-radius:6px 6px 0 0;transition:all .15s}
        .fplms-rpt-tab-btn:hover{background:#fff8ee;color:#e08800}
        .fplms-rpt-tab-btn.active{background:#ffa800;color:#fff;border-color:#ffa800;font-weight:700}
        .fplms-rpt-content{background:#fff;border:1.5px solid #e0e0e0;border-top:none;border-radius:0 0 8px 8px;padding:24px;min-height:320px}
        .fplms-rpt-placeholder{text-align:center;color:#bbb;padding:60px 0;font-size:15px}
        .fplms-rpt-loading{text-align:center;padding:50px;color:#888;font-size:14px}
        .fplms-rpt-section-head{display:flex;align-items:center;justify-content:space-between;border-bottom:2px solid #ffa800;padding-bottom:10px;margin-bottom:20px}
        .fplms-rpt-title{margin:0;font-size:18px;color:#333}
        .fplms-rpt-export-btn{font-size:12px!important}
        .fplms-rpt-sub{font-size:14px;color:#444;margin:22px 0 8px;border-left:3px solid #ffa800;padding-left:10px}
        .fplms-rpt-channel-head{background:#fff8ee;border-radius:0 6px 6px 0;padding:8px 12px}
        .fplms-rpt-channel-count{font-size:12px;font-weight:400;color:#888}
        .fplms-rpt-metric{font-size:13px;color:#555;margin:4px 0 10px}
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
        </style>
        <script>
        (function(){
            var NONCE=<?php echo wp_json_encode($nonce); ?>,AJAXURL=<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            var activeTab=null,searchTimer=null;
            var tabActions={participation:'fplms_report_participation',progress:'fplms_report_progress',performance:'fplms_report_performance',certificates:'fplms_report_certificates',time:'fplms_report_time',satisfaction:'fplms_report_satisfaction',channels:'fplms_report_channels',tests:'fplms_report_tests'};
            function getFilters(){return{date_from:document.getElementById('fplms-rpt-date-from').value||'',date_to:document.getElementById('fplms-rpt-date-to').value||'',channel_id:document.getElementById('fplms-rpt-channel').value||'',city_id:document.getElementById('fplms-rpt-city').value||'',company_id:document.getElementById('fplms-rpt-company').value||'',role_id:document.getElementById('fplms-rpt-role').value||'',user_ids:document.getElementById('fplms-rpt-user-ids').value||''};}
            function loadReport(tab){
                if(!tab)return; activeTab=tab;
                var action=tabActions[tab]; if(!action)return;
                var content=document.getElementById('fplms-rpt-content');
                content.innerHTML='<div class="fplms-rpt-loading"><span class="fplms-spin-dot"></span>Cargando reporte…</div>';
                var p=Object.assign({action:action,nonce:NONCE},getFilters());
                var body=Object.keys(p).map(k=>encodeURIComponent(k)+'='+encodeURIComponent(p[k])).join('&');
                fetch(AJAXURL,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body})
                    .then(r=>r.json()).then(res=>{content.innerHTML=res.success?res.data.html:'<p class="notice notice-error">'+(res.data?res.data.message:'Error')+'</p>';})
                    .catch(()=>{content.innerHTML='<p class="notice notice-error">Error de conexion.</p>';});
            }
            document.querySelector('.fplms-rpt-tabs-nav').addEventListener('click',function(e){
                var btn=e.target.closest('.fplms-rpt-tab-btn'); if(!btn)return;
                document.querySelectorAll('.fplms-rpt-tab-btn').forEach(b=>b.classList.remove('active'));
                btn.classList.add('active'); loadReport(btn.dataset.tab);
            });
            document.getElementById('fplms-rpt-apply').addEventListener('click',function(){
                var m=document.getElementById('fplms-rpt-modal');
                m.style.display='flex';
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
                if(activeTab)loadReport(activeTab);
            });
            var us=document.getElementById('fplms-rpt-user-search'),sug=document.getElementById('fplms-rpt-suggestions');
            us.addEventListener('input',function(){
                var q=this.value.trim(); document.getElementById('fplms-rpt-user-ids').value='';
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
            sug.addEventListener('click',function(e){var it=e.target.closest('.fplms-sug-item');if(!it)return;us.value=it.textContent.trim();document.getElementById('fplms-rpt-user-ids').value=it.dataset.id;sug.style.display='none';});
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
            function resetQuizAttempt(quizId, userId, userName) {
                if (!confirm('\u00bfResetear la evaluaci\u00f3n de ' + userName + '?\nEsta acci\u00f3n eliminar\u00e1 el intento y no se puede deshacer.')) return;
                var body = 'action=fplms_report_test_reset&nonce='+encodeURIComponent(NONCE)+'&quiz_id='+quizId+'&user_id='+userId;
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
            document.getElementById('fplms-rpt-content').addEventListener('click', function(e) {
                var detBtn  = e.target.closest('.fplms-test-detail-btn');
                var backBtn = e.target.closest('#fplms-test-back-btn');
                var rstBtn  = e.target.closest('.fplms-test-reset-btn');
                if (detBtn)  { loadTestDetail(detBtn.dataset.quizId); }
                else if (backBtn) { currentQuizId = null; loadReport('tests'); }
                else if (rstBtn)  { resetQuizAttempt(rstBtn.dataset.quizId, rstBtn.dataset.userId, rstBtn.dataset.userName); }
            });
        })();
        </script>
        <?php
    }
}
