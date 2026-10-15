<?php
if (!defined('ABSPATH')) { exit; }

/**
 * PageSpeed Insights queue + results (optional API key).
 * Safe: if no API key, user can still enqueue and see clear errors.
 */
class HET_SEO_PSI {
    private $logger;
    private $license;
    private $table_queue;
    private $table_results;

    public function __construct(HET_SEO_Logger $logger, HET_SEO_License $license) {
        global $wpdb;
        $this->logger = $logger;
        $this->license = $license;
        $this->table_queue = $wpdb->prefix . 'het_seo_psi_queue';
        $this->table_results = $wpdb->prefix . 'het_seo_psi_results';

        add_action('het_seo_cron_psi_daily', [$this, 'cron_enqueue_daily']);
        add_action('het_seo_cron_psi_process', [$this, 'cron_process']);
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $q = $wpdb->prefix . 'het_seo_psi_queue';
        $r = $wpdb->prefix . 'het_seo_psi_results';

        $sql1 = "CREATE TABLE {$q} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            url TEXT NOT NULL,
            strategy VARCHAR(16) NOT NULL DEFAULT 'mobile',
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            priority INT NOT NULL DEFAULT 50,
            tries INT NOT NULL DEFAULT 0,
            last_error TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY status_priority (status, priority, id)
        ) {$charset};";

        $sql2 = "CREATE TABLE {$r} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            url TEXT NOT NULL,
            strategy VARCHAR(16) NOT NULL DEFAULT 'mobile',
            perf TINYINT UNSIGNED NULL,
            seo TINYINT UNSIGNED NULL,
            pwa TINYINT UNSIGNED NULL,
            best_practices TINYINT UNSIGNED NULL,
            accessibility TINYINT UNSIGNED NULL,
            fetched_at DATETIME NOT NULL,
            json LONGTEXT NULL,
            PRIMARY KEY (id),
            KEY fetched_at (fetched_at)
        ) {$charset};";

        dbDelta($sql1);
        dbDelta($sql2);
    }

    public static function ensure_cron() {
        if (!wp_next_scheduled('het_seo_cron_psi_daily')) {
            wp_schedule_event(time() + 60, 'daily', 'het_seo_cron_psi_daily');
        }
        if (!wp_next_scheduled('het_seo_cron_psi_process')) {
            // light weight: every hour
            wp_schedule_event(time() + 120, 'hourly', 'het_seo_cron_psi_process');
        }
    }

    public function cron_enqueue_daily() {
        // best effort: enqueue top URLs from sitemap
        $this->enqueue_from_sitemap(25);
    }

    public function cron_process() {
        $this->process_queue(10);
    }

    public function enqueue_url($url, $strategy = 'mobile', $priority = 50) {
        global $wpdb;
        $url = esc_url_raw($url);
        if (!$url) { return false; }
        $strategy = ($strategy === 'desktop') ? 'desktop' : 'mobile';
        $priority = max(1, min(100, (int)$priority));
        $now = current_time('mysql');
        $wpdb->insert($this->table_queue, [
            'url' => $url,
            'strategy' => $strategy,
            'status' => 'pending',
            'priority' => $priority,
            'tries' => 0,
            'last_error' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int)$wpdb->insert_id;
    }

    public function enqueue_from_sitemap($limit = 50) {
        $limit = max(1, min(500, (int)$limit));
        $sitemap = home_url('/sitemap.xml');
        $res = wp_remote_get($sitemap, ['timeout' => 15, 'redirection' => 3, 'user-agent' => 'HeterodynaSEO/' . HET_SEO_VER]);
        if (is_wp_error($res)) { return 0; }
        $body = (string) wp_remote_retrieve_body($res);
        if ($body === '') { return 0; }
        preg_match_all('~<loc>\s*([^<\s]+)\s*</loc>~i', $body, $m);
        $urls = isset($m[1]) ? array_values(array_unique(array_slice($m[1], 0, $limit))) : [];
        $n = 0;
        foreach ($urls as $u) {
            if ($this->enqueue_url($u, 'mobile', 60)) { $n++; }
        }
        return $n;
    }

    public function list_queue($limit = 50) {
        global $wpdb;
        $limit = max(1, min(500, (int)$limit));
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table_queue} ORDER BY status='pending' DESC, priority ASC, id ASC LIMIT %d", $limit), ARRAY_A);
    }

    public function list_queue_filtered($status = '', $limit = 100) {
        global $wpdb;
        $limit = max(1, min(500, (int)$limit));
        $status = sanitize_text_field((string)$status);
        if ($status && in_array($status, ['pending','running','done','error'], true)) {
            return $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$this->table_queue} WHERE status=%s ORDER BY priority ASC, id ASC LIMIT %d", $status, $limit),
                ARRAY_A
            );
        }
        return $this->list_queue($limit);
    }

    public function list_results($limit = 30) {
        global $wpdb;
        $limit = max(1, min(200, (int)$limit));
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table_results} ORDER BY id DESC LIMIT %d", $limit), ARRAY_A);
    }

    public function list_results_for_url($url, $strategy = '', $limit = 50) {
        global $wpdb;
        $limit = max(1, min(300, (int)$limit));
        $url = esc_url_raw((string)$url);
        if (!$url) { return []; }
        $strategy = sanitize_text_field((string)$strategy);
        if ($strategy && in_array($strategy, ['mobile','desktop'], true)) {
            return $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$this->table_results} WHERE url=%s AND strategy=%s ORDER BY id DESC LIMIT %d", $url, $strategy, $limit),
                ARRAY_A
            );
        }
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table_results} WHERE url=%s ORDER BY id DESC LIMIT %d", $url, $limit),
            ARRAY_A
        );
    }

    public function delete_queue_item($id) {
        global $wpdb;
        $id = (int)$id;
        if ($id <= 0) { return false; }
        $wpdb->delete($this->table_queue, ['id' => $id]);
        return true;
    }

    public function retry_queue_item($id, $reset_tries = true) {
        global $wpdb;
        $id = (int)$id;
        if ($id <= 0) { return false; }
        $data = [
            'status' => 'pending',
            'last_error' => null,
            'updated_at' => current_time('mysql'),
        ];
        if ($reset_tries) { $data['tries'] = 0; }
        $wpdb->update($this->table_queue, $data, ['id' => $id]);
        return true;
    }

    public function clear_queue_by_status($status) {
        global $wpdb;
        $status = sanitize_text_field((string)$status);
        if (!in_array($status, ['done','error'], true)) { return 0; }
        $n = (int)$wpdb->query($wpdb->prepare("DELETE FROM {$this->table_queue} WHERE status=%s", $status));
        return max(0, $n);
    }

    private function get_api_key() {
        $opts = get_option(HET_SEO_OPT, []);
        return isset($opts['psi_api_key']) ? trim((string)$opts['psi_api_key']) : '';
    }

    private function call_pagespeed($url, $strategy) {
        $key = $this->get_api_key();
        if ($key === '') {
            return new WP_Error('het_seo_no_key', 'Brak klucza API PageSpeed Insights (PSI).');
        }
        $endpoint = add_query_arg([
            'url' => $url,
            'strategy' => $strategy,
            'key' => $key,
            'category' => ['performance','seo','pwa','best-practices','accessibility'],
        ], 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed');

        $res = wp_remote_get($endpoint, ['timeout' => 30, 'redirection' => 2]);
        if (is_wp_error($res)) { return $res; }
        $code = (int) wp_remote_retrieve_response_code($res);
        $body = (string) wp_remote_retrieve_body($res);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('het_seo_psi_http', 'HTTP ' . $code . ' z PSI', ['body' => $body]);
        }
        $json = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($json)) {
            return new WP_Error('het_seo_psi_json', 'Niepoprawna odpowiedź PSI (JSON).');
        }
        return $json;
    }

    private function score_to_int($maybe) {
        // lighthouse scores are 0..1 floats
        if ($maybe === null) { return null; }
        if (is_numeric($maybe)) {
            $v = floatval($maybe);
            if ($v <= 1.01) { $v = $v * 100; }
            $v = max(0, min(100, (int) round($v)));
            return $v;
        }
        return null;
    }

    public function process_queue($max = 10) {
        global $wpdb;
        $max = max(1, min(50, (int)$max));
        $done = 0;

        for ($i = 0; $i < $max; $i++) {
            $row = $wpdb->get_row("SELECT * FROM {$this->table_queue} WHERE status='pending' ORDER BY priority ASC, id ASC LIMIT 1", ARRAY_A);
            if (!$row) { break; }

            $id = (int)$row['id'];
            $url = (string)$row['url'];
            $strategy = (string)$row['strategy'];

            $wpdb->update($this->table_queue, [
                'status' => 'running',
                'updated_at' => current_time('mysql'),
            ], ['id' => $id]);

            $json = $this->call_pagespeed($url, $strategy);
            if (is_wp_error($json)) {
                $tries = (int)$row['tries'] + 1;
                $wpdb->update($this->table_queue, [
                    'status' => ($tries >= 3) ? 'error' : 'pending',
                    'tries' => $tries,
                    'last_error' => $json->get_error_message(),
                    'updated_at' => current_time('mysql'),
                ], ['id' => $id]);
                continue;
            }

            $cats = $json['lighthouseResult']['categories'] ?? [];
            $perf = $this->score_to_int($cats['performance']['score'] ?? null);
            $seo  = $this->score_to_int($cats['seo']['score'] ?? null);
            $pwa  = $this->score_to_int($cats['pwa']['score'] ?? null);
            $bp   = $this->score_to_int($cats['best-practices']['score'] ?? null);
            $acc  = $this->score_to_int($cats['accessibility']['score'] ?? null);

            $wpdb->insert($this->table_results, [
                'url' => $url,
                'strategy' => $strategy,
                'perf' => $perf,
                'seo' => $seo,
                'pwa' => $pwa,
                'best_practices' => $bp,
                'accessibility' => $acc,
                'fetched_at' => current_time('mysql'),
                'json' => wp_json_encode($json),
            ]);

            $wpdb->update($this->table_queue, [
                'status' => 'done',
                'updated_at' => current_time('mysql'),
                'last_error' => null,
            ], ['id' => $id]);

            $done++;
        }

        return $done;
    }
}
