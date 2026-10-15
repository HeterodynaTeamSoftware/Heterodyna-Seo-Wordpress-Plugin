<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Local alerts engine (no SaaS).
 * - Stores rules + events in DB
 * - Runs daily via cron (can be run manually from UI)
 * - Optional email notification (admin-defined)
 */
class HET_SEO_Alerts {
    private $logger;
    private $license;
    private $table_rules;
    private $table_events;

    public function __construct(HET_SEO_Logger $logger, HET_SEO_License $license) {
        global $wpdb;
        $this->logger = $logger;
        $this->license = $license;
        $this->table_rules = $wpdb->prefix . 'het_seo_alert_rules';
        $this->table_events = $wpdb->prefix . 'het_seo_alert_events';

        add_action('het_seo_cron_alerts_daily', [$this, 'run_checks']);
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $rules = $wpdb->prefix . 'het_seo_alert_rules';
        $events = $wpdb->prefix . 'het_seo_alert_events';

        $sql1 = "CREATE TABLE {$rules} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            rule_key VARCHAR(64) NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            threshold FLOAT NOT NULL DEFAULT 0,
            window_hours INT NOT NULL DEFAULT 24,
            email_to VARCHAR(190) NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY rule_key (rule_key)
        ) {$charset};";

        $sql2 = "CREATE TABLE {$events} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            rule_key VARCHAR(64) NOT NULL,
            severity VARCHAR(16) NOT NULL DEFAULT 'info',
            message TEXT NOT NULL,
            payload_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY created_at (created_at),
            KEY rule_key (rule_key)
        ) {$charset};";

        dbDelta($sql1);
        dbDelta($sql2);

        // Defaults (idempotent)
        $now = current_time('mysql');
        $defaults = [
            ['404_spike', 1, 50, 24],
            ['uptime', 1, 0, 24],
            ['psi_low', 0, 50, 24],
        ];
        foreach ($defaults as $d) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$rules} (rule_key, enabled, threshold, window_hours, email_to, updated_at)
                 VALUES (%s, %d, %f, %d, NULL, %s)
                 ON DUPLICATE KEY UPDATE updated_at=updated_at",
                $d[0], $d[1], $d[2], $d[3], $now
            ));
        }
    }

    public static function ensure_cron() {
        if (!wp_next_scheduled('het_seo_cron_alerts_daily')) {
            wp_schedule_event(time() + 180, 'daily', 'het_seo_cron_alerts_daily');
        }
    }

    public function get_rules() {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$this->table_rules} ORDER BY rule_key ASC", ARRAY_A);
        $out = [];
        foreach ($rows as $r) {
            $out[$r['rule_key']] = $r;
        }
        return $out;
    }

    public function save_rules(array $rules, $email_to = '') {
        global $wpdb;
        $email_to = $email_to ? sanitize_email($email_to) : '';
        $now = current_time('mysql');
        foreach ($rules as $key => $cfg) {
            $enabled = !empty($cfg['enabled']) ? 1 : 0;
            $threshold = isset($cfg['threshold']) ? floatval($cfg['threshold']) : 0;
            $window = isset($cfg['window_hours']) ? intval($cfg['window_hours']) : 24;
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$this->table_rules} (rule_key, enabled, threshold, window_hours, email_to, updated_at)
                 VALUES (%s, %d, %f, %d, %s, %s)
                 ON DUPLICATE KEY UPDATE enabled=VALUES(enabled), threshold=VALUES(threshold), window_hours=VALUES(window_hours), email_to=VALUES(email_to), updated_at=VALUES(updated_at)",
                $key, $enabled, $threshold, $window, ($email_to ?: null), $now
            ));
        }
    }

    public function list_events($limit = 50) {
        global $wpdb;
        $limit = max(10, min(500, (int)$limit));
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table_events} ORDER BY id DESC LIMIT %d", $limit), ARRAY_A);
    }

    public function add_event($rule_key, $severity, $message, array $payload = []) {
        return $this->push_event($rule_key, $severity, $message, $payload);
    }

    private function push_event($rule_key, $severity, $message, array $payload = []) {
        global $wpdb;
        $wpdb->insert($this->table_events, [
            'rule_key' => $rule_key,
            'severity' => $severity,
            'message' => $message,
            'payload_json' => $payload ? wp_json_encode($payload) : null,
            'created_at' => current_time('mysql'),
        ]);
        return (int)$wpdb->insert_id;
    }

    private function maybe_email($rule, $subject, $body) {
        $to = '';
        if (!empty($rule['email_to'])) {
            $to = sanitize_email($rule['email_to']);
        }
        if (!$to) {
            return false;
        }
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        return wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Run checks. Returns number of new events created.
     */
    public function run_checks() {
        $rules = $this->get_rules();
        $new = 0;

        // 1) 404 spike (compare last window vs previous window)
        if (!empty($rules['404_spike']) && intval($rules['404_spike']['enabled']) === 1) {
            $window = max(1, intval($rules['404_spike']['window_hours']));
            $threshold = floatval($rules['404_spike']['threshold']);
            $c = $this->check_404_spike($window, $threshold);
            if ($c) {
                $new++;
                $rule = $rules['404_spike'];
                $this->maybe_email($rule, 'Heterodyna SEO: wzrost 404', $c['message']);
            }
        }

        // 2) Uptime of robots/sitemap/security
        if (!empty($rules['uptime']) && intval($rules['uptime']['enabled']) === 1) {
            $c = $this->check_uptime();
            foreach ($c as $evt) {
                $new++;
                $this->maybe_email($rules['uptime'], 'Heterodyna SEO: uptime', $evt['message']);
            }
        }

        // 3) PSI low score (based on last results)
        if (!empty($rules['psi_low']) && intval($rules['psi_low']['enabled']) === 1) {
            $threshold = floatval($rules['psi_low']['threshold']);
            $c = $this->check_psi_low($threshold);
            foreach ($c as $evt) {
                $new++;
                $this->maybe_email($rules['psi_low'], 'Heterodyna SEO: niski wynik PSI', $evt['message']);
            }
        }

        return $new;
    }

    private function check_404_spike($window_hours, $threshold_pct) {
        global $wpdb;
        $t404 = $wpdb->prefix . 'het_seo_404';
        $now = current_time('timestamp');
        $w = $window_hours * HOUR_IN_SECONDS;
        $a1 = gmdate('Y-m-d H:i:s', $now - $w + get_option('gmt_offset') * HOUR_IN_SECONDS);
        $a2 = gmdate('Y-m-d H:i:s', $now + get_option('gmt_offset') * HOUR_IN_SECONDS);
        $b1 = gmdate('Y-m-d H:i:s', $now - 2*$w + get_option('gmt_offset') * HOUR_IN_SECONDS);
        $b2 = gmdate('Y-m-d H:i:s', $now - $w + get_option('gmt_offset') * HOUR_IN_SECONDS);

        // If table missing, skip
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t404));
        if (!$exists) { return false; }

        $cur = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t404} WHERE created_at BETWEEN %s AND %s", $a1, $a2)));
        $prev = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t404} WHERE created_at BETWEEN %s AND %s", $b1, $b2)));
        if ($prev <= 0) { return false; }
        $pct = (($cur - $prev) / $prev) * 100.0;
        if ($pct >= $threshold_pct) {
            $msg = sprintf('Wzrost 404: %d → %d (%.1f%%) w oknie %dh.', $prev, $cur, $pct, $window_hours);
            $this->push_event('404_spike', 'warning', $msg, ['prev'=>$prev,'cur'=>$cur,'pct'=>$pct,'window_hours'=>$window_hours]);
            return ['message' => $msg];
        }
        return false;
    }

    private function http_check($url) {
        $res = wp_remote_get($url, ['timeout' => 10, 'redirection' => 2, 'user-agent' => 'HeterodynaSEO/' . HET_SEO_VER]);
        if (is_wp_error($res)) {
            return ['ok'=>false,'code'=>0,'detail'=>$res->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        return ['ok'=>($code>=200 && $code<400),'code'=>$code,'detail'=>''];
    }

    private function check_uptime() {
        $targets = [
            ['key'=>'robots', 'url'=>home_url('/robots.txt')],
            ['key'=>'sitemap', 'url'=>home_url('/sitemap.xml')],
            ['key'=>'security', 'url'=>home_url('/.well-known/security.txt')],
        ];
        $out = [];
        foreach ($targets as $t) {
            $r = $this->http_check($t['url']);
            if (!$r['ok']) {
                $msg = sprintf('Uptime: %s nie działa (HTTP %d).', $t['url'], $r['code']);
                $this->push_event('uptime', 'error', $msg, ['url'=>$t['url'],'code'=>$r['code'],'detail'=>$r['detail']]);
                $out[] = ['message'=>$msg];
            }
        }
        return $out;
    }

    private function check_psi_low($threshold) {
        global $wpdb;
        $psi = $wpdb->prefix . 'het_seo_psi_results';
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $psi));
        if (!$exists) { return []; }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$psi} ORDER BY id DESC LIMIT %d", 20
        ), ARRAY_A);
        $out = [];
        foreach ($rows as $r) {
            $perf = isset($r['perf']) ? intval($r['perf']) : 0;
            if ($perf > 0 && $perf < $threshold) {
                $msg = sprintf('Niski wynik PSI (perf=%d) dla %s (%s).', $perf, $r['url'], $r['strategy']);
                $this->push_event('psi_low', 'warning', $msg, ['url'=>$r['url'],'strategy'=>$r['strategy'],'perf'=>$perf]);
                $out[] = ['message'=>$msg];
            }
        }
        return $out;
    }
}
