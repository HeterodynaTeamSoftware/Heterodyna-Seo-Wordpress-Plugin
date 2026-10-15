<?php
if (!defined('ABSPATH')) { exit; }

class HET_SEO_AI_Auto {
    const OPT_KEY = 'het_seo_ai_auto';
    const TABLE_RUNS = 'het_seo_ai_runs';

    private $logger;
    private $license;
    private $queue;

    public function __construct(HET_SEO_Logger $logger, HET_SEO_License $license, HET_SEO_AI_Queue $queue) {
        $this->logger = $logger;
        $this->license = $license;
        $this->queue = $queue;

        add_filter('cron_schedules', [$this, 'cron_schedules']);
        add_action('het_seo_cron_ai_auto_daily', [$this, 'cron_daily']);
        add_action('het_seo_cron_ai_auto_weekly_report', [$this, 'cron_weekly_report']);
    }

    public static function defaults() {
        return [
            'enabled' => 0,
            'panic_stop' => 0,
            'autonomy' => 'manual', // manual|semi|auto

            'mode' => 'dry', // dry|exec
            'include_ctr' => 0,
            'limit' => 200,
            'post_types' => ['post','page'],
            'allowed_fixes' => [
                HET_SEO_AI_Queue::FIX_MISSING_META,
                HET_SEO_AI_Queue::FIX_TOO_LONG,
                HET_SEO_AI_Queue::FIX_DUPLICATES,
            ],
            'daily_hour' => 3,
        ];
    }

    public static function get_settings() {
        $raw = get_option(self::OPT_KEY, []);
        $s = wp_parse_args(is_array($raw) ? $raw : [], self::defaults());
        // sanitize
        $s['enabled'] = !empty($s['enabled']) ? 1 : 0;
        $s['panic_stop'] = !empty($s['panic_stop']) ? 1 : 0;
        $s['mode'] = ($s['mode'] === 'exec') ? 'exec' : 'dry';
        $s['autonomy'] = in_array(($s['autonomy'] ?? 'manual'), ['manual','semi','auto'], true) ? ($s['autonomy'] ?? 'manual') : 'manual';
        $s['include_ctr'] = !empty($s['include_ctr']) ? 1 : 0;
        $s['limit'] = max(10, min(2000, intval($s['limit'])));
        $s['daily_hour'] = max(0, min(23, intval($s['daily_hour'])));
        $pts = [];
        foreach ((array)($s['post_types'] ?? []) as $pt) {
            $pt = sanitize_key($pt);
            if ($pt) { $pts[] = $pt; }
        }
        $s['post_types'] = $pts ? array_values(array_unique($pts)) : ['post','page'];
        $allowed = [];
        $valid = [
            HET_SEO_AI_Queue::FIX_MISSING_META,
            HET_SEO_AI_Queue::FIX_DUPLICATES,
            HET_SEO_AI_Queue::FIX_TOO_LONG,
            HET_SEO_AI_Queue::FIX_CTR,
        ];
        foreach ((array)($s['allowed_fixes'] ?? []) as $fx) {
            $fx = sanitize_key($fx);
            if (in_array($fx, $valid, true)) { $allowed[] = $fx; }
        }
        $s['allowed_fixes'] = $allowed ? array_values(array_unique($allowed)) : [HET_SEO_AI_Queue::FIX_MISSING_META];
        return $s;
    }

    public function cron_schedules($s) {
        if (!isset($s['het_weekly'])) {
            $s['het_weekly'] = ['interval' => 7 * DAY_IN_SECONDS, 'display' => 'Weekly'];
        }
        return $s;
    }

    public static function ensure_cron() {
        $s = self::get_settings();
        // schedule daily even if disabled, but it will no-op when disabled; keeps behavior stable across upgrades
        $hook = 'het_seo_cron_ai_auto_daily';
        $next = wp_next_scheduled($hook);
        if (!$next) {
            // schedule near desired hour
            $ts = strtotime('today ' . sprintf('%02d:00:00', intval($s['daily_hour'])));
            if ($ts <= time()) { $ts = strtotime('tomorrow ' . sprintf('%02d:00:00', intval($s['daily_hour']))); }
            wp_schedule_event($ts, 'daily', $hook);
        }

        $whook = 'het_seo_cron_ai_auto_weekly_report';
        if (!wp_next_scheduled($whook)) {
            wp_schedule_event(time() + 3600, 'het_weekly', $whook);
        }
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . self::TABLE_RUNS;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            run_type VARCHAR(20) NOT NULL DEFAULT 'daily',
            mode VARCHAR(10) NOT NULL DEFAULT 'dry',
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            panic_stop TINYINT(1) NOT NULL DEFAULT 0,
            post_types TEXT NULL,
            allowed_fixes TEXT NULL,
            include_ctr TINYINT(1) NOT NULL DEFAULT 0,
            scan_limit INT NOT NULL DEFAULT 200,
            enqueued INT NOT NULL DEFAULT 0,
            processed INT NOT NULL DEFAULT 0,
            errors INT NOT NULL DEFAULT 0,
            note TEXT NULL,
            started_at DATETIME NOT NULL,
            finished_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY run_type (run_type),
            KEY started_at (started_at)
        ) $charset;";
        dbDelta($sql);
    }

    public function cron_daily() {
        $s = self::get_settings();
        $run_id = $this->start_run('daily', $s);

        if (empty($s['enabled']) || !empty($s['panic_stop'])) {

            $this->finish_run($run_id, 0, 0, 0, 'Wyłączone lub PANIC STOP.');
            return;
        }

        if (($s['autonomy'] ?? 'manual') === 'manual') {
            $this->finish_run($run_id, 0, 0, 0, 'Autonomia: manual — cron nie wykonuje skanu.');
            return;
        }

        $dry = ($s['mode'] !== 'exec');

        // If executing writes, require active license.
        if (!$dry && !$this->license->can_run_write_actions()) {
            $this->finish_run($run_id, 0, 0, 1, 'Build open-source: tryb wykonania jest dostępny.');
            return;
        }

        // Scan & enqueue
        $res = $this->scan_with_whitelist($s, $dry);
        if (is_wp_error($res)) {
            $this->finish_run($run_id, 0, 0, 1, $res->get_error_message());
            return;
        }
        $enq = intval($res['enqueued'] ?? 0);

        // W trybie semi — tylko kolejka do zatwierdzenia; w trybie auto — procesujemy batch.
        $processed = 0;
        if (($s['autonomy'] ?? 'manual') === 'auto') {
            $processed = intval($this->queue->process_next(20));
        }
        $this->finish_run($run_id, $enq, $processed, 0, null);
    }

    public function cron_weekly_report() {
        // Placeholder: keep for future email summaries.
        $s = self::get_settings();
        $run_id = $this->start_run('weekly', $s);
        $this->finish_run($run_id, 0, 0, 0, 'Raport tygodniowy: generacja w panelu (CSV).');
    }

    private function compute_impact_score($post_id, $fix_type) {
        $impact = 0;
        switch ($fix_type) {
            case HET_SEO_AI_Queue::FIX_MISSING_META: $impact = 80; break;
            case HET_SEO_AI_Queue::FIX_DUPLICATES:   $impact = 60; break;
            case HET_SEO_AI_Queue::FIX_TOO_LONG:     $impact = 40; break;
            case HET_SEO_AI_Queue::FIX_CTR:          $impact = 20; break;
            default: $impact = 10; break;
        }
        // Light boost for pages (often "money pages")
        $pt = get_post_type($post_id);
        if ($pt === 'page') { $impact += 10; }
        return max(0, min(100, intval($impact)));
    }

    private function impact_to_priority($impact, $fix_priority_hint) {
        // Lower priority number = earlier. Map impact (0..100) to ~ (900..800), then add hint.
        $base = 900 - (intval($impact) * 2); // 900..700
        return max(1, intval($base) + intval($fix_priority_hint));
    }

    private function scan_with_whitelist($s, $dry) {
        // We reuse scan_and_enqueue, but we only enqueue allowed fix types.
        // To keep it safe and fast, we implement our own light scan loop here.
        $allowed = (array)$s['allowed_fixes'];
        $requires_approval = (($s['autonomy'] ?? 'manual') === 'semi');
        $include_ctr = !empty($s['include_ctr']);
        if ($include_ctr && !in_array(HET_SEO_AI_Queue::FIX_CTR, $allowed, true)) {
            // include_ctr requires ctr in whitelist
            $include_ctr = false;
        }

        $q = new WP_Query([
            'post_type' => $s['post_types'],
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(2000, intval($s['limit']))),
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        $count = 0;
        foreach ($q->posts as $post_id) {
            $post_id = intval($post_id);

            if (in_array(HET_SEO_AI_Queue::FIX_MISSING_META, $allowed, true)) {
                // enqueue only if missing
                $t = get_post_meta($post_id, 'het_seo_meta_title', true);
                $d = get_post_meta($post_id, 'het_seo_meta_description', true);
                if (empty($t) || empty($d)) {
                    $impact = $this->compute_impact_score($post_id, HET_SEO_AI_Queue::FIX_MISSING_META);
                    $prio = $this->impact_to_priority($impact, 10);
                    $this->queue->enqueue_post($post_id, HET_SEO_AI_Queue::FIX_MISSING_META, $prio, $dry, $requires_approval, $impact);
                    $count++;
                }
            }

            if (in_array(HET_SEO_AI_Queue::FIX_DUPLICATES, $allowed, true)) {
                $impact = $this->compute_impact_score($post_id, HET_SEO_AI_Queue::FIX_DUPLICATES);
                    $prio = $this->impact_to_priority($impact, 20);
                    $this->queue->enqueue_post($post_id, HET_SEO_AI_Queue::FIX_DUPLICATES, $prio, $dry, $requires_approval, $impact);
                $count++;
            }

            if (in_array(HET_SEO_AI_Queue::FIX_TOO_LONG, $allowed, true)) {
                $t = get_post_meta($post_id, 'het_seo_meta_title', true);
                $d = get_post_meta($post_id, 'het_seo_meta_description', true);
                if ((mb_strlen($t) > 60) || (mb_strlen($d) > 155)) {
                    $impact = $this->compute_impact_score($post_id, HET_SEO_AI_Queue::FIX_TOO_LONG);
                    $prio = $this->impact_to_priority($impact, 30);
                    $this->queue->enqueue_post($post_id, HET_SEO_AI_Queue::FIX_TOO_LONG, $prio, $dry, $requires_approval, $impact);
                    $count++;
                }
            }

            if ($include_ctr) {
                $impact = $this->compute_impact_score($post_id, HET_SEO_AI_Queue::FIX_CTR);
                    $prio = $this->impact_to_priority($impact, 40);
                    $this->queue->enqueue_post($post_id, HET_SEO_AI_Queue::FIX_CTR, $prio, $dry, $requires_approval, $impact);
                $count++;
            }
        }

        return ['enqueued' => $count];
    }

    private function start_run($type, $s) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_RUNS;
        $wpdb->insert($table, [
            'run_type' => sanitize_key($type),
            'mode' => $s['mode'],
            'enabled' => intval($s['enabled']),
            'panic_stop' => intval($s['panic_stop']),
            'post_types' => wp_json_encode(array_values($s['post_types'])),
            'allowed_fixes' => wp_json_encode(array_values($s['allowed_fixes'])),
            'include_ctr' => intval($s['include_ctr']),
            'scan_limit' => intval($s['limit']),
            'enqueued' => 0,
            'processed' => 0,
            'errors' => 0,
            'note' => null,
            'started_at' => current_time('mysql'),
            'finished_at' => null,
        ], ['%s','%s','%d','%d','%s','%s','%d','%d','%d','%d','%d','%s','%s','%s']);
        return intval($wpdb->insert_id);
    }

    private function finish_run($id, $enqueued, $processed, $errors, $note) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_RUNS;
        $wpdb->update($table, [
            'enqueued' => intval($enqueued),
            'processed' => intval($processed),
            'errors' => intval($errors),
            'note' => $note ? wp_strip_all_tags($note) : null,
            'finished_at' => current_time('mysql'),
        ], ['id' => intval($id)], ['%d','%d','%d','%s','%s'], ['%d']);
    }

    public static function list_runs($limit = 50) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_RUNS;
        $limit = max(1, min(200, intval($limit)));
        return $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT $limit");
    }

    public static function export_csv() {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
        check_admin_referer('het_seo_ai_auto_export');

        $rows = self::list_runs(200);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=het-seo-ai-auto-runs.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['id','run_type','mode','enabled','panic_stop','post_types','allowed_fixes','include_ctr','scan_limit','enqueued','processed','errors','note','started_at','finished_at']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r->id,
                $r->run_type,
                $r->mode,
                $r->enabled,
                $r->panic_stop,
                $r->post_types,
                $r->allowed_fixes,
                $r->include_ctr,
                $r->scan_limit,
                $r->enqueued,
                $r->processed,
                $r->errors,
                $r->note,
                $r->started_at,
                $r->finished_at,
            ]);
        }
        fclose($out);
        exit;
    }
}
