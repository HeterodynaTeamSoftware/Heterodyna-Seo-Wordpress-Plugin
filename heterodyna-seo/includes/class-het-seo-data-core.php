<?php
if (!defined('ABSPATH')) { exit; }

/**
 * DATA CORE (v03.01)
 *
 * Goal: give a "data flood" to the client.
 * Best-effort approach: works without external APIs; integrates with PSI if available.
 */
class HET_SEO_Data_Core {
    const TABLE_URL_SNAPSHOT  = 'het_seo_url_snapshot';
    const TABLE_SITE_SNAPSHOT = 'het_seo_site_snapshot';
    const TABLE_HISTORY       = 'het_seo_history';

    private $logger;

    public function __construct(HET_SEO_Logger $logger) {
        $this->logger = $logger;

        add_action('het_seo_cron_datacore_daily', [$this, 'cron_daily']);
        add_action('het_seo_cron_datacore_batch', [$this, 'cron_batch']);
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $t_url  = $wpdb->prefix . self::TABLE_URL_SNAPSHOT;
        $t_site = $wpdb->prefix . self::TABLE_SITE_SNAPSHOT;
        $t_hist = $wpdb->prefix . self::TABLE_HISTORY;

        $sql1 = "CREATE TABLE {$t_url} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NULL,
            url TEXT NOT NULL,
            content_hash CHAR(40) NOT NULL DEFAULT '',
            metrics_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY created_at (created_at)
        ) {$charset};";

        $sql2 = "CREATE TABLE {$t_site} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            metrics_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY created_at (created_at)
        ) {$charset};";

        $sql3 = "CREATE TABLE {$t_hist} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope VARCHAR(16) NOT NULL DEFAULT 'site',
            post_id BIGINT UNSIGNED NULL,
            url TEXT NULL,
            event_key VARCHAR(64) NOT NULL DEFAULT '',
            old_value LONGTEXT NULL,
            new_value LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY scope_post (scope, post_id),
            KEY created_at (created_at)
        ) {$charset};";

        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
    }

    public static function ensure_cron() {
        if (!wp_next_scheduled('het_seo_cron_datacore_daily')) {
            wp_schedule_event(time() + 180, 'daily', 'het_seo_cron_datacore_daily');
        }
        if (!wp_next_scheduled('het_seo_cron_datacore_batch')) {
            // light batch for larger sites
            wp_schedule_event(time() + 300, 'hourly', 'het_seo_cron_datacore_batch');
        }
    }

    public function cron_daily() {
        // Create one fresh site snapshot daily.
        try {
            $this->save_site_snapshot();
        } catch (Throwable $e) {
            $this->logger->log('datacore_daily_error', $e->getMessage());
        }
    }

    public function cron_batch() {
        // Incrementally snapshot URLs in small batches.
        try {
            $this->save_url_snapshots_batch(40);
        } catch (Throwable $e) {
            $this->logger->log('datacore_batch_error', $e->getMessage());
        }
    }

    /**
     * Public: returns current site metrics (best-effort).
     */
    public function get_site_metrics() {
        $q = new WP_Query([
            'post_type'      => ['post', 'page'],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);
        $ids = is_array($q->posts) ? $q->posts : [];

        $tot = [
            'url_count' => count($ids),
            'words_total' => 0,
            'words_avg' => 0,
            'headings' => ['h1'=>0,'h2'=>0,'h3'=>0,'h4'=>0,'h5'=>0,'h6'=>0],
            'links' => ['internal_out'=>0,'external_out'=>0],
            'meta' => ['title_set'=>0,'desc_set'=>0,'canonical_set'=>0,'noindex'=>0,'nofollow'=>0],
        ];

        foreach ($ids as $post_id) {
            $m = $this->extract_post_metrics((int)$post_id);
            $tot['words_total'] += (int)($m['content']['words'] ?? 0);
            foreach ($tot['headings'] as $k => $_) {
                $tot['headings'][$k] += (int)($m['content']['headings'][$k] ?? 0);
            }
            $tot['links']['internal_out'] += (int)($m['links']['internal_out'] ?? 0);
            $tot['links']['external_out'] += (int)($m['links']['external_out'] ?? 0);
            $tot['meta']['title_set'] += !empty($m['meta']['title']) ? 1 : 0;
            $tot['meta']['desc_set']  += !empty($m['meta']['description']) ? 1 : 0;
            $tot['meta']['canonical_set'] += !empty($m['meta']['canonical']) ? 1 : 0;
            $tot['meta']['noindex'] += !empty($m['meta']['noindex']) ? 1 : 0;
            $tot['meta']['nofollow'] += !empty($m['meta']['nofollow']) ? 1 : 0;
        }

        $tot['words_avg'] = $tot['url_count'] > 0 ? (int) round($tot['words_total'] / $tot['url_count']) : 0;
        return $tot;
    }

    /**
     * Public: list URL rows for admin table.
     */
    public function get_urls_overview($limit = 200) {
        $limit = max(10, min(1000, (int)$limit));
        $q = new WP_Query([
            'post_type'      => ['post', 'page'],
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);
        $out = [];
        foreach ((array)$q->posts as $pid) {
            $pid = (int)$pid;
            $out[] = $this->extract_post_metrics($pid);
        }
        return $out;
    }

    public function save_site_snapshot() {
        global $wpdb;
        $t_site = $wpdb->prefix . self::TABLE_SITE_SNAPSHOT;
        $metrics = $this->get_site_metrics();
        $now = current_time('mysql');
        $wpdb->insert($t_site, [
            'metrics_json' => wp_json_encode($metrics),
            'created_at'   => $now,
        ]);
        return (int)$wpdb->insert_id;
    }

    public function save_url_snapshots_batch($limit = 40) {
        $limit = max(10, min(500, (int)$limit));
        $offset = (int) get_option('het_seo_datacore_offset', 0);

        $q = new WP_Query([
            'post_type'      => ['post', 'page'],
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);

        $ids = (array) $q->posts;
        if (empty($ids)) {
            // restart
            update_option('het_seo_datacore_offset', 0);
            return 0;
        }

        $saved = 0;
        foreach ($ids as $pid) {
            $pid = (int)$pid;
            if ($this->save_url_snapshot($pid)) {
                $saved++;
            }
        }

        update_option('het_seo_datacore_offset', $offset + count($ids));
        return $saved;
    }

    public function save_url_snapshot($post_id) {
        global $wpdb;
        $t_url = $wpdb->prefix . self::TABLE_URL_SNAPSHOT;

        $metrics = $this->extract_post_metrics((int)$post_id);
        $url = (string)($metrics['url'] ?? '');
        if (!$url) { return false; }

        $hash = (string)($metrics['content']['hash'] ?? '');

        // avoid dup snapshots for identical content hash (latest-only check)
        $last = $wpdb->get_row($wpdb->prepare(
            "SELECT id, content_hash, metrics_json FROM {$t_url} WHERE post_id=%d ORDER BY id DESC LIMIT 1",
            $post_id
        ), ARRAY_A);

        if (is_array($last) && !empty($last['content_hash']) && $last['content_hash'] === $hash) {
            return false;
        }

        $now = current_time('mysql');
        $wpdb->insert($t_url, [
            'post_id'      => $post_id,
            'url'          => $url,
            'content_hash' => $hash,
            'metrics_json' => wp_json_encode($metrics),
            'created_at'   => $now,
        ]);

        // write a few timeline events (best-effort)
        if (is_array($last) && !empty($last['metrics_json'])) {
            $old = json_decode((string)$last['metrics_json'], true);
            $this->diff_to_history($old, $metrics, $post_id, $url);
        }

        return true;
    }

    public function get_history($post_id = 0, $limit = 200) {
        global $wpdb;
        $t_hist = $wpdb->prefix . self::TABLE_HISTORY;
        $limit = max(10, min(2000, (int)$limit));

        if ($post_id > 0) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$t_hist} WHERE (scope='url' AND post_id=%d) ORDER BY id DESC LIMIT %d",
                $post_id,
                $limit
            ), ARRAY_A);
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$t_hist} ORDER BY id DESC LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    /**
     * Extract a data-rich metrics bundle for a post.
     */
    public function extract_post_metrics($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return [];
        }

        $content_raw = (string) $post->post_content;
        $content_txt = wp_strip_all_tags($content_raw);
        $words = $this->count_words($content_txt);
        $headings = $this->count_headings($content_raw);
        $links = $this->count_links($content_raw);

        $meta_title = (string) get_post_meta($post_id, 'het_seo_meta_title', true);
        $meta_desc  = (string) get_post_meta($post_id, 'het_seo_meta_description', true);
        $canonical  = (string) get_post_meta($post_id, 'het_seo_canonical', true);
        $noindex    = (int) get_post_meta($post_id, 'het_seo_noindex', true);
        $nofollow   = (int) get_post_meta($post_id, 'het_seo_nofollow', true);

        $url = get_permalink($post_id);
        $hash = sha1($content_txt . '|' . $meta_title . '|' . $meta_desc . '|' . $canonical . '|' . $noindex . '|' . $nofollow);

        $psi = $this->get_last_psi_for_url($url);

        return [
            'post_id' => $post_id,
            'type' => (string) $post->post_type,
            'status' => (string) $post->post_status,
            'title' => (string) get_the_title($post_id),
            'url' => (string) $url,
            'modified' => (string) get_post_modified_time('Y-m-d H:i:s', false, $post_id, true),
            'content' => [
                'words' => $words,
                'headings' => $headings,
                'hash' => $hash,
            ],
            'links' => $links,
            'meta' => [
                'title' => $meta_title,
                'description' => $meta_desc,
                'canonical' => $canonical,
                'noindex' => $noindex ? 1 : 0,
                'nofollow' => $nofollow ? 1 : 0,
            ],
            'psi' => $psi,
        ];
    }

    private function get_last_psi_for_url($url) {
        global $wpdb;
        $r = $wpdb->prefix . 'het_seo_psi_results';
        if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $r))) {
            return null;
        }
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$r} WHERE url=%s ORDER BY id DESC LIMIT 1",
            $url
        ), ARRAY_A);
        if (!$row) { return null; }
        return [
            'strategy' => (string)($row['strategy'] ?? ''),
            'perf' => isset($row['perf']) ? (int)$row['perf'] : null,
            'seo' => isset($row['seo']) ? (int)$row['seo'] : null,
            'pwa' => isset($row['pwa']) ? (int)$row['pwa'] : null,
            'best_practices' => isset($row['best_practices']) ? (int)$row['best_practices'] : null,
            'accessibility' => isset($row['accessibility']) ? (int)$row['accessibility'] : null,
            'fetched_at' => (string)($row['fetched_at'] ?? ''),
        ];
    }

    private function count_words($text) {
        $text = trim((string)$text);
        if ($text === '') { return 0; }
        // unicode-friendly split
        $parts = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($parts) ? count($parts) : 0;
    }

    private function count_headings($html) {
        $out = ['h1'=>0,'h2'=>0,'h3'=>0,'h4'=>0,'h5'=>0,'h6'=>0];
        foreach ($out as $tag => $_) {
            if (preg_match_all('/<' . $tag . '\b[^>]*>/i', (string)$html, $m)) {
                $out[$tag] = count($m[0]);
            }
        }
        return $out;
    }

    private function count_links($html) {
        $internal = 0;
        $external = 0;
        $home = wp_parse_url(home_url('/'));
        $home_host = isset($home['host']) ? strtolower($home['host']) : '';

        if (preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i', (string)$html, $m)) {
            foreach ((array)$m[1] as $href) {
                $href = trim((string)$href);
                if ($href === '' || strpos($href, 'javascript:') === 0 || strpos($href, '#') === 0) { continue; }

                $u = wp_parse_url($href);
                if (!$u || empty($u['host'])) {
                    // relative => internal
                    $internal++;
                    continue;
                }
                $host = strtolower((string)$u['host']);
                if ($home_host !== '' && $host === $home_host) { $internal++; }
                else { $external++; }
            }
        }

        return ['internal_out'=>$internal,'external_out'=>$external];
    }

    private function diff_to_history($old, $new, $post_id, $url) {
        // Minimal & useful changes only (data flood without noise)
        $pairs = [
            ['key'=>'meta_title', 'label'=>'meta_title', 'old'=>$old['meta']['title'] ?? '', 'new'=>$new['meta']['title'] ?? ''],
            ['key'=>'meta_description', 'label'=>'meta_description', 'old'=>$old['meta']['description'] ?? '', 'new'=>$new['meta']['description'] ?? ''],
            ['key'=>'canonical', 'label'=>'canonical', 'old'=>$old['meta']['canonical'] ?? '', 'new'=>$new['meta']['canonical'] ?? ''],
            ['key'=>'noindex', 'label'=>'noindex', 'old'=>(string)($old['meta']['noindex'] ?? 0), 'new'=>(string)($new['meta']['noindex'] ?? 0)],
            ['key'=>'nofollow', 'label'=>'nofollow', 'old'=>(string)($old['meta']['nofollow'] ?? 0), 'new'=>(string)($new['meta']['nofollow'] ?? 0)],
            ['key'=>'words', 'label'=>'words', 'old'=>(string)($old['content']['words'] ?? 0), 'new'=>(string)($new['content']['words'] ?? 0)],
            ['key'=>'h2', 'label'=>'h2', 'old'=>(string)($old['content']['headings']['h2'] ?? 0), 'new'=>(string)($new['content']['headings']['h2'] ?? 0)],
            ['key'=>'links_internal_out', 'label'=>'links_internal_out', 'old'=>(string)($old['links']['internal_out'] ?? 0), 'new'=>(string)($new['links']['internal_out'] ?? 0)],
            ['key'=>'links_external_out', 'label'=>'links_external_out', 'old'=>(string)($old['links']['external_out'] ?? 0), 'new'=>(string)($new['links']['external_out'] ?? 0)],
        ];

        foreach ($pairs as $p) {
            if ((string)$p['old'] === (string)$p['new']) { continue; }
            $this->history_add('url', $post_id, $url, (string)$p['label'], (string)$p['old'], (string)$p['new']);
        }
    }

    public function history_add($scope, $post_id, $url, $event_key, $old_value, $new_value) {
        global $wpdb;
        $t_hist = $wpdb->prefix . self::TABLE_HISTORY;
        $scope = ($scope === 'url') ? 'url' : 'site';
        $now = current_time('mysql');
        $wpdb->insert($t_hist, [
            'scope' => $scope,
            'post_id' => $post_id ? (int)$post_id : null,
            'url' => $url ? (string)$url : null,
            'event_key' => (string)$event_key,
            'old_value' => is_null($old_value) ? null : (string)$old_value,
            'new_value' => is_null($new_value) ? null : (string)$new_value,
            'created_at' => $now,
        ]);
        return (int)$wpdb->insert_id;
    }

    /**
     * Manual trigger from admin UI (kept for backwards compatibility).
     */
    public function collect_site_snapshot($manual = false) {
        return (int) $this->save_site_snapshot();
    }

    public function collect_url_snapshot($post_id, $manual = false) {
        $post_id = (int)$post_id;
        if ($post_id <= 0) { return 0; }
        return $this->save_url_snapshot($post_id) ? 1 : 0;
    }

}
