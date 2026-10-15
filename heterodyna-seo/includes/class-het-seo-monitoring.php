<?php
if (!defined('ABSPATH')) { exit; }

class HET_SEO_Monitoring {
    private $logger;
    private $alerts;
    private $table_updates;

    public function __construct(HET_SEO_Logger $logger, HET_SEO_Alerts $alerts) {
        $this->logger = $logger;
        $this->alerts = $alerts;
        global $wpdb;
        $this->table_updates = $wpdb->prefix . 'het_seo_google_updates';

        add_action('het_seo_cron_monitor_daily', [$this, 'cron_daily']);
    }

    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'het_seo_google_updates';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_date DATE NOT NULL,
            title VARCHAR(190) NOT NULL,
            event_type VARCHAR(32) NOT NULL DEFAULT 'core',
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY event_date (event_date),
            KEY event_type (event_type)
        ) {$charset};";
        dbDelta($sql);
    }

    public static function ensure_cron() {
        if (!wp_next_scheduled('het_seo_cron_monitor_daily')) {
            wp_schedule_event(time() + 120, 'daily', 'het_seo_cron_monitor_daily');
        }
    }

    public function cron_daily() {
        // Best-effort delta: compare latest site snapshot with previous and push alert
        try {
            $delta = $this->get_site_delta_summary(true);
            if (!empty($delta['changed'])) {
                $msg = 'Wykryto zmiany SEO (site snapshot): ' . implode(' · ', $delta['changed']);
                $this->alerts->add_event('seo_delta', 'info', $msg, $delta);
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    public function list_google_updates($limit = 200) {
        global $wpdb;
        $limit = max(10, min(500, (int)$limit));
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table_updates} ORDER BY event_date DESC, id DESC LIMIT %d", $limit), ARRAY_A);
    }

    public function add_google_update($date, $title, $type, $notes) {
        global $wpdb;
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date) ? (string)$date : gmdate('Y-m-d');
        $title = (string)$title;
        if ($title === '') { $title = 'Google update'; }
        $type = $type ?: 'core';
        $wpdb->insert($this->table_updates, [
            'event_date' => $date,
            'title' => $title,
            'event_type' => $type,
            'notes' => $notes,
            'created_at' => current_time('mysql'),
        ]);
    }

    public function get_site_delta_summary($return_full = false) {
        // Requires Data Core tables; do best-effort queries
        global $wpdb;
        $table = $wpdb->prefix . 'het_seo_site_snapshot';
        $has = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if (!$has) { return ['ok'=>false,'changed'=>[]]; }

        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 2", ARRAY_A);
        if (!is_array($rows) || count($rows) < 2) { return ['ok'=>true,'changed'=>[]]; }

        $a = json_decode((string)($rows[0]['metrics_json'] ?? ''), true);
        $b = json_decode((string)($rows[1]['metrics_json'] ?? ''), true);
        $a = is_array($a) ? $a : [];
        $b = is_array($b) ? $b : [];

        // normalize keys
        $map = [
            'url_count' => ['total_urls','url_count'],
            'words_avg' => ['avg_words','words_avg'],
            'meta_title_set' => ['meta_title_set','title_set'],
            'meta_desc_set' => ['meta_desc_set','desc_set'],
            'noindex' => ['noindex_count','noindex'],
        ];
        $getv = function($arr, $keys) {
            foreach ($keys as $k) { if (isset($arr[$k])) return $arr[$k]; }
            return null;
        };

        $changed = [];
        foreach ($map as $label => $keys) {
            $v1 = $getv($a, $keys);
            $v2 = $getv($b, $keys);
            if ($v1 !== null && $v2 !== null && (string)$v1 !== (string)$v2) {
                $changed[] = "{$label}: {$v2}→{$v1}";
            }
        }

        $out = [
            'ok' => true,
            'changed' => $changed,
            'latest_id' => (int)($rows[0]['id'] ?? 0),
            'prev_id' => (int)($rows[1]['id'] ?? 0),
            'latest_at' => (string)($rows[0]['created_at'] ?? ''),
            'prev_at' => (string)($rows[1]['created_at'] ?? ''),
        ];
        if ($return_full) {
            $out['latest_metrics'] = $a;
            $out['prev_metrics'] = $b;
        }
        return $out;
    }
}
