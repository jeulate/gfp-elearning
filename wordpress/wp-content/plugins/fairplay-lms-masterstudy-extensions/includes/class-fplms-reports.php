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
        $btn = $url ? '<a href="'.esc_url($url).'" class="button fplms-rpt-export-btn" target="_blank">&#11015; Exportar Excel</a>' : '';
        return '<div class="fplms-rpt-section-head"><h2 class="fplms-rpt-title">'.esc_html($title).'</h2>'.$btn.'</div>';
    }
    private function sub(string $t): string   { return '<h3 class="fplms-rpt-sub">'.esc_html($t).'</h3>'; }
    private function empty_msg(string $t): string { return '<p class="fplms-rpt-empty">'.esc_html($t).'</p>'; }
    private function metric(string $h): string  { return '<p class="fplms-rpt-metric">'.$h.'</p>'; }
    private function badge(string $text, string $color): string {
        return '<span class="fplms-badge '.esc_attr($color).'">'.esc_html($text).'</span>';
    }
    private function table_open(array $heads): string {
        $ths = implode('', array_map(fn($h)=>'<th>'.esc_html($h).'</th>', $heads));
        return '<div class="fplms-rpt-table-wrap"><table class="widefat striped fplms-rpt-table"><thead><tr>'.$ths.'</tr></thead><tbody>';
    }
    private function table_close(): string { return '</tbody></table></div>'; }

    private function build_export_url(string $report, array $f): string {
        return add_query_arg( array_filter([
            'page'         => 'fplms-reports',
            'fplms_export' => $report.'_xls',
            'date_from'    => $f['date_from']  ?? '',
            'date_to'      => $f['date_to']    ?? '',
            'channel_id'   => $f['channel_id'] ?: '',
            'city_id'      => $f['city_id']    ?: '',
            'company_id'   => $f['company_id'] ?: '',
            'role_id'      => $f['role_id']    ?: '',
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
                    um_st.meta_value AS fplms_active
             FROM {$wpdb->users} u
             LEFT JOIN {$wpdb->usermeta} um_ci ON u.ID=um_ci.user_id AND um_ci.meta_key='fplms_city'
             LEFT JOIN {$wpdb->usermeta} um_ch ON u.ID=um_ch.user_id AND um_ch.meta_key='fplms_channel'
             LEFT JOIN {$wpdb->usermeta} um_st ON u.ID=um_st.user_id AND um_st.meta_key='fplms_active'
             WHERE 1=1 $uid_w $date_w ORDER BY u.user_registered DESC LIMIT 1000"
        );
        $act=0; $inact=0;
        foreach ($created as $r) { if (''===(string)$r->fplms_active||'1'===(string)$r->fplms_active) $act++; else $inact++; }
        $html .= $this->sub('Usuarios Registrados ('.count($created).') — Activos:'.$act.' / Inactivos:'.$inact);
        $html .= $this->table_open(['Nombre','Email','Fecha Registro','Ciudad','Canal','Estado']);
        foreach ($created as $r) {
            $city    = $r->city_id    ? $this->structures->get_term_name_by_id((int)$r->city_id)    : '—';
            $channel = $r->channel_id ? $this->structures->get_term_name_by_id((int)$r->channel_id) : '—';
            $isact   = ''===(string)$r->fplms_active||'1'===(string)$r->fplms_active;
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
                           . '<td>'.$this->badge($r->status?:'not_started','yellow').'</td></tr>';
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
             INNER JOIN {$wpdb->usermeta} um_st ON u.ID=um_st.user_id AND um_st.meta_key='fplms_active' AND um_st.meta_value='0'
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
                           . '<td>'.esc_html((string)$r->score).'</td><td>'.$this->badge($r->status??'—',$cl).'</td></tr>';
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

    // CSV EXPORT
    public function handle_export(): void {
        if (!is_admin()) return;
        if (!isset($_GET['page'])||'fplms-reports'!==$_GET['page']) return;
        if (!isset($_GET['fplms_export'])) return;
        if (!current_user_can(FairPlay_LMS_Config::CAP_VIEW_REPORTS)) return;
        if (!isset($_GET['_wpnonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])),'fplms_export_nonce')) return;
        $fmt=sanitize_text_field(wp_unslash($_GET['fplms_export']));
        $f=['date_from'=>isset($_GET['date_from'])?sanitize_text_field(wp_unslash($_GET['date_from'])):'','date_to'=>isset($_GET['date_to'])?sanitize_text_field(wp_unslash($_GET['date_to'])):'','channel_id'=>isset($_GET['channel_id'])?(int)$_GET['channel_id']:0,'city_id'=>isset($_GET['city_id'])?(int)$_GET['city_id']:0,'company_id'=>isset($_GET['company_id'])?(int)$_GET['company_id']:0,'role_id'=>isset($_GET['role_id'])?(int)$_GET['role_id']:0,'user_ids'=>[]];
        $this->dispatch_xls_export($fmt,$f);
    }

    private function dispatch_xls_export(string $format, array $f): void {
        global $wpdb;
        $uids=$this->get_user_ids_for_filters($f); $ms=$this->ms_table();
        $mw=null!==$uids?(empty($uids)?'AND uc.user_id=0':'AND uc.user_id IN ('.implode(',',array_map('intval',$uids)).')'):'';
        $uw=$this->uid_in($uids);
        switch (true) {
            case str_starts_with($format,'participation'):
                $dw=$this->date_sql('u.user_registered',$f['date_from'],$f['date_to']);
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $rows=(array)$wpdb->get_results("SELECT u.display_name,u.user_email,u.user_registered,um_ci.meta_value AS city_id,um_ch.meta_value AS channel_id,um_st.meta_value AS fplms_active FROM {$wpdb->users} u LEFT JOIN {$wpdb->usermeta} um_ci ON u.ID=um_ci.user_id AND um_ci.meta_key='fplms_city' LEFT JOIN {$wpdb->usermeta} um_ch ON u.ID=um_ch.user_id AND um_ch.meta_key='fplms_channel' LEFT JOIN {$wpdb->usermeta} um_st ON u.ID=um_st.user_id AND um_st.meta_key='fplms_active' WHERE 1=1 $uw $dw ORDER BY u.user_registered DESC LIMIT 5000");
                $this->output_xls('participacion','Reporte de Participacion',['Nombre','Email','Fecha Registro','Ciudad','Canal','Estado'],array_map(fn($r)=>[$r->display_name,$r->user_email,wp_date('d/m/Y',strtotime($r->user_registered)),$r->city_id?$this->structures->get_term_name_by_id((int)$r->city_id):'',$r->channel_id?$this->structures->get_term_name_by_id((int)$r->channel_id):'',''===(string)$r->fplms_active||'1'===(string)$r->fplms_active?'Activo':'Inactivo'],$rows),$f);
                break;
            case str_starts_with($format,'progress'):
                if (!$ms) { echo 'Sin tabla.'; exit; }
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $rows=(array)$wpdb->get_results("SELECT u.display_name,u.user_email,p.post_title AS course,uc.progress_percent,uc.status FROM {$wpdb->users} u INNER JOIN `{$ms}` uc ON u.ID=uc.user_id INNER JOIN {$wpdb->posts} p ON uc.course_id=p.ID WHERE 1=1 $mw ORDER BY u.display_name LIMIT 5000");
                $this->output_xls('progreso','Reporte de Progreso',['Usuario','Email','Curso','Progreso %','Estado'],array_map(fn($r)=>[$r->display_name,$r->user_email,$r->course,$r->progress_percent,$r->status],$rows),$f);
                break;
            case str_starts_with($format,'certificates'):
                $u_in=null!==$uids?(empty($uids)?'AND 1=0':'AND pm_uid.meta_value IN ('.implode(',',array_map('intval',$uids)).')'):'';
                $dw=$this->date_sql('p.post_date',$f['date_from'],$f['date_to']);
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $rows=(array)$wpdb->get_results("SELECT u.display_name,u.user_email,pc.post_title AS course,p.post_date FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm_uid ON p.ID=pm_uid.post_id AND pm_uid.meta_key='user_id' INNER JOIN {$wpdb->users} u ON pm_uid.meta_value=u.ID LEFT JOIN {$wpdb->postmeta} pm_cid ON p.ID=pm_cid.post_id AND pm_cid.meta_key='course_id' LEFT JOIN {$wpdb->posts} pc ON pm_cid.meta_value=pc.ID WHERE p.post_type IN('stm-certificates','stm-certificate') AND p.post_status!='trash' $u_in $dw ORDER BY p.post_date DESC LIMIT 5000");
                $this->output_xls('certificados','Reporte de Certificacion',['Usuario','Email','Curso','Fecha Emision'],array_map(fn($r)=>[$r->display_name,$r->user_email,$r->course?:'—',wp_date('d/m/Y',strtotime($r->post_date))],$rows),$f);
                break;
            case str_starts_with($format,'time'):
                if (!$ms) { echo 'Sin datos.'; exit; }
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $rows=(array)$wpdb->get_results("SELECT u.display_name,u.user_email,p.post_title AS course,COALESCE(CAST(pm_vd.meta_value AS DECIMAL(10,2)),0) AS vh,COALESCE(CAST(pm_di.meta_value AS DECIMAL(10,2)),0) AS dh FROM {$wpdb->users} u INNER JOIN `{$ms}` uc ON u.ID=uc.user_id INNER JOIN {$wpdb->posts} p ON uc.course_id=p.ID AND p.post_status='publish' LEFT JOIN {$wpdb->postmeta} pm_vd ON p.ID=pm_vd.post_id AND pm_vd.meta_key='video_duration' LEFT JOIN {$wpdb->postmeta} pm_di ON p.ID=pm_di.post_id AND pm_di.meta_key='duration_info' WHERE (uc.status IN('completed','passed') OR uc.progress_percent>=100) $mw ORDER BY u.display_name LIMIT 5000");
                $this->output_xls('tiempo_capacitacion','Reporte de Tiempo de Capacitacion',['Usuario','Email','Curso','Horas'],array_map(fn($r)=>[$r->display_name,$r->user_email,$r->course,round(max((float)$r->vh,(float)$r->dh),1).' h'],$rows),$f);
                break;
            default:
                $users=$this->users->get_users_filtered_by_structure(0,0,0,0);
                $this->output_xls('usuarios_avance','Usuarios — Avance General',['ID','Nombre','Email','Roles','Ciudad','Canal','Sucursal','Cargo','Avance'],array_map(fn($u)=>[$u->ID,$u->display_name,$u->user_email,implode(', ',$u->roles),$this->structures->get_term_name_by_id(get_user_meta($u->ID,FairPlay_LMS_Config::USER_META_CITY,true)),$this->structures->get_term_name_by_id(get_user_meta($u->ID,FairPlay_LMS_Config::USER_META_CHANNEL,true)),$this->structures->get_term_name_by_id(get_user_meta($u->ID,FairPlay_LMS_Config::USER_META_BRANCH,true)),$this->structures->get_term_name_by_id(get_user_meta($u->ID,FairPlay_LMS_Config::USER_META_ROLE,true)),$this->progress->get_user_progress_summary($u->ID)],$users),$f);
        }
    }

    private function output_xls(string $slug, string $title, array $headers, array $data, array $filters = []): void {
        $ncols  = count($headers);
        $merge  = max(0, $ncols - 1);
        $xe     = fn($v) => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $fp = [];
        if (!empty($filters['date_from'])) $fp[] = 'Desde: ' . $filters['date_from'];
        if (!empty($filters['date_to']))   $fp[] = 'Hasta: ' . $filters['date_to'];
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

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="fairplay_' . $slug . '_' . gmdate('Ymd_His') . '.xls"');
        header('Cache-Control: max-age=0');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $xml; exit;
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
        ];
        $icons=['participation'=>'📊','progress'=>'📈','performance'=>'🏆','certificates'=>'🎓','time'=>'⏱','satisfaction'=>'⭐','channels'=>'🏢'];
        ?>
        <div class="wrap fplms-reports-wrap">
            <h1 class="wp-heading-inline">📋 Reportes FairPlay LMS</h1>
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
                    <button id="fplms-rpt-apply" class="button button-primary">🔍 Aplicar Filtros</button>
                    <button id="fplms-rpt-reset" class="button">✖ Limpiar</button>
                </div>
            </div>
            <div class="fplms-rpt-tabs-nav">
                <?php foreach ($tabs as $slug=>$label): ?>
                <button class="fplms-rpt-tab-btn" data-tab="<?php echo esc_attr($slug); ?>"><?php echo esc_html(($icons[$slug]??'').' '.$label); ?></button>
                <?php endforeach; ?>
            </div>
            <div id="fplms-rpt-content" class="fplms-rpt-content">
                <div class="fplms-rpt-placeholder"><p>👆 Selecciona un reporte para comenzar.</p></div>
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
        </style>
        <script>
        (function(){
            var NONCE=<?php echo wp_json_encode($nonce); ?>,AJAXURL=<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            var activeTab=null,searchTimer=null;
            var tabActions={participation:'fplms_report_participation',progress:'fplms_report_progress',performance:'fplms_report_performance',certificates:'fplms_report_certificates',time:'fplms_report_time',satisfaction:'fplms_report_satisfaction',channels:'fplms_report_channels'};
            function getFilters(){return{date_from:document.getElementById('fplms-rpt-date-from').value||'',date_to:document.getElementById('fplms-rpt-date-to').value||'',channel_id:document.getElementById('fplms-rpt-channel').value||'',city_id:document.getElementById('fplms-rpt-city').value||'',company_id:document.getElementById('fplms-rpt-company').value||'',role_id:document.getElementById('fplms-rpt-role').value||'',user_ids:document.getElementById('fplms-rpt-user-ids').value||''};}
            function loadReport(tab){
                if(!tab)return; activeTab=tab;
                var action=tabActions[tab]; if(!action)return;
                var content=document.getElementById('fplms-rpt-content');
                content.innerHTML='<div class="fplms-rpt-loading">⏳ Cargando reporte…</div>';
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
            document.getElementById('fplms-rpt-apply').addEventListener('click',function(){if(activeTab)loadReport(activeTab);});
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
        })();
        </script>
        <?php
    }
}
