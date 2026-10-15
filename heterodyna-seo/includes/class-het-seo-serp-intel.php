<?php
if (!defined('ABSPATH')) { exit; }

/**
 * SERP Intelligence (v03.03)
 * - Keywords list (manual)
 * - Paste/import SERP results (JSON / CSV-lite)
 * - Competition rollup (domains)
 * - GAP (simple deltas vs TOP3 averages)
 *
 * NOTE: We do NOT scrape Google directly from the server (unstable + risky).
 * This module is built around:
 *  - manual keyword entry
 *  - paste SERP export (from any external tool)
 *  - optional future connectors (GSC, proxy API)
 */
class HET_SEO_SERP_Intel {

    private $logger;
    private $wpdb;
    private $t_keywords;
    private $t_results;
    private $t_gap;

    public function __construct(HET_SEO_Logger $logger) {
        global $wpdb;
        $this->logger = $logger;
        $this->wpdb = $wpdb;
        $this->t_keywords = $wpdb->prefix . 'het_seo_serp_keywords';
        $this->t_results  = $wpdb->prefix . 'het_seo_serp_results';
        $this->t_gap      = $wpdb->prefix . 'het_seo_serp_gap';
    }

    public static function install() {
        // Tables are created lazily on first use too, but install helps.
        $self = new self(new HET_SEO_Logger());
        $self->ensure_tables();
    }

    public function ensure_tables() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $this->wpdb->get_charset_collate();

        $sql1 = "CREATE TABLE {$this->t_keywords} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            keyword VARCHAR(255) NOT NULL,
            locale VARCHAR(20) NOT NULL DEFAULT 'pl-PL',
            device VARCHAR(20) NOT NULL DEFAULT 'mobile',
            source VARCHAR(40) NOT NULL DEFAULT 'manual',
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY keyword (keyword)
        ) $charset;";

        $sql2 = "CREATE TABLE {$this->t_results} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            keyword_id BIGINT UNSIGNED NOT NULL,
            position INT NOT NULL,
            url TEXT NOT NULL,
            domain VARCHAR(255) NOT NULL,
            title TEXT NULL,
            snippet TEXT NULL,
            metrics_json LONGTEXT NULL,
            structure_json LONGTEXT NULL,
            collected_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY keyword_id (keyword_id),
            KEY domain (domain),
            KEY position (position)
        ) $charset;";

        $sql3 = "CREATE TABLE {$this->t_gap} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            keyword_id BIGINT UNSIGNED NOT NULL,
            url TEXT NULL,
            gap_score INT NOT NULL DEFAULT 0,
            gap_json LONGTEXT NULL,
            computed_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY keyword_id (keyword_id),
            KEY gap_score (gap_score)
        ) $charset;";

        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
    }

    /* -----------------------------
     * Data helpers
     * ----------------------------- */

    public function add_keyword($keyword, $locale='pl-PL', $device='mobile', $notes='') {
        $this->ensure_tables();
        $keyword = trim(wp_strip_all_tags((string)$keyword));
        if ($keyword === '') { return 0; }
        $this->wpdb->insert($this->t_keywords, [
            'keyword' => $keyword,
            'locale' => $locale ?: 'pl-PL',
            'device' => $device ?: 'mobile',
            'source' => 'manual',
            'notes' => $notes ? wp_kses_post($notes) : null,
            'created_at' => current_time('mysql'),
        ], ['%s','%s','%s','%s','%s','%s']);
        return (int)$this->wpdb->insert_id;
    }

    public function list_keywords($limit=200) {
        $this->ensure_tables();
        $limit = max(1, min(500, (int)$limit));
        return $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$this->t_keywords} ORDER BY id DESC LIMIT %d", $limit), ARRAY_A);
    }

    public function get_keyword($keyword_id) {
        $this->ensure_tables();
        return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->t_keywords} WHERE id=%d", (int)$keyword_id), ARRAY_A);
    }

    public function list_results($keyword_id, $limit=20) {
        $this->ensure_tables();
        $limit = max(1, min(100, (int)$limit));
        return $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$this->t_results} WHERE keyword_id=%d ORDER BY position ASC LIMIT %d", (int)$keyword_id, $limit), ARRAY_A);
    }

    public function clear_results($keyword_id) {
        $this->ensure_tables();
        $this->wpdb->delete($this->t_results, ['keyword_id' => (int)$keyword_id], ['%d']);
        $this->wpdb->delete($this->t_gap, ['keyword_id' => (int)$keyword_id], ['%d']);
    }

    private function domain_from_url($url) {
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!$host) { return ''; }
        $host = strtolower($host);
        $host = preg_replace('/^www\./', '', $host);
        return $host;
    }

    /**
     * Accepts:
     * - JSON array of objects: [{position,url,title,snippet,words,h2,h3,schema:[...]}]
     * - CSV-like lines: position;url;title
     */
    public function import_serp_payload($keyword_id, $payload_raw) {
        $this->ensure_tables();
        $keyword_id = (int)$keyword_id;
        $payload_raw = trim((string)$payload_raw);
        if ($keyword_id <= 0 || $payload_raw === '') { return ['ok'=>false,'msg'=>'Brak danych.']; }

        $this->clear_results($keyword_id);

        $rows = [];
        $is_json = (substr($payload_raw,0,1)==='[' || substr($payload_raw,0,1)==='{');
        if ($is_json) {
            $decoded = json_decode($payload_raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['ok'=>false,'msg'=>'Niepoprawny JSON: ' . json_last_error_msg()];
            }
            if (isset($decoded['results']) && is_array($decoded['results'])) {
                $decoded = $decoded['results'];
            }
            if (!is_array($decoded)) {
                return ['ok'=>false,'msg'=>'JSON musi być tablicą wyników.'];
            }
            foreach ($decoded as $item) {
                if (!is_array($item)) { continue; }
                $rows[] = $item;
            }
        } else {
            $lines = preg_split("/\r\n|\n|\r/", $payload_raw);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line==='') { continue; }
                // allow ; or , or \t
                $parts = preg_split("/[;\t,]+/", $line);
                if (count($parts) < 2) { continue; }
                $rows[] = [
                    'position' => (int)trim($parts[0]),
                    'url' => trim($parts[1]),
                    'title' => isset($parts[2]) ? trim($parts[2]) : '',
                ];
            }
        }

        $inserted = 0;
        foreach ($rows as $item) {
            $pos = isset($item['position']) ? (int)$item['position'] : 0;
            $url = isset($item['url']) ? trim((string)$item['url']) : '';
            if ($pos<=0 || $url==='') { continue; }
            $domain = $this->domain_from_url($url);
            $title = isset($item['title']) ? wp_strip_all_tags((string)$item['title']) : '';
            $snippet = isset($item['snippet']) ? wp_strip_all_tags((string)$item['snippet']) : '';

            // Lightweight metrics/structure fields (optional)
            $metrics = [
                'words' => isset($item['words']) ? (int)$item['words'] : null,
                'h1' => isset($item['h1']) ? (int)$item['h1'] : null,
                'h2' => isset($item['h2']) ? (int)$item['h2'] : null,
                'h3' => isset($item['h3']) ? (int)$item['h3'] : null,
                'links' => isset($item['links']) ? (int)$item['links'] : null,
            ];
            $structure = [
                'type' => isset($item['type']) ? (string)$item['type'] : null,
                'schema' => isset($item['schema']) ? $item['schema'] : null,
                'has_faq' => isset($item['has_faq']) ? (bool)$item['has_faq'] : null,
                'has_lists' => isset($item['has_lists']) ? (bool)$item['has_lists'] : null,
                'has_tables' => isset($item['has_tables']) ? (bool)$item['has_tables'] : null,
            ];

            $this->wpdb->insert($this->t_results, [
                'keyword_id' => $keyword_id,
                'position' => $pos,
                'url' => $url,
                'domain' => $domain ?: '',
                'title' => $title ?: null,
                'snippet' => $snippet ?: null,
                'metrics_json' => wp_json_encode($metrics),
                'structure_json' => wp_json_encode($structure),
                'collected_at' => current_time('mysql'),
            ], ['%d','%d','%s','%s','%s','%s','%s','%s','%s']);
            $inserted++;
            if ($inserted >= 50) { break; }
        }

        // compute gap (best-effort)
        $this->compute_gap($keyword_id);

        return ['ok'=>true,'msg'=>"Zaimportowano: {$inserted} wyników."];
    }

    public function competition_rollup($limit=30) {
        $this->ensure_tables();
        $limit = max(1, min(200, (int)$limit));
        $sql = "SELECT domain, COUNT(*) as cnt, AVG(position) as avg_pos
                FROM {$this->t_results}
                WHERE domain <> ''
                GROUP BY domain
                ORDER BY cnt DESC, avg_pos ASC
                LIMIT %d";
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $limit), ARRAY_A);
    }

    public function compute_gap($keyword_id) {
        $this->ensure_tables();
        $keyword_id = (int)$keyword_id;
        if ($keyword_id<=0) { return; }

        $results = $this->list_results($keyword_id, 50);
        if (!$results) { return; }

        // compute TOP3 averages for words/h2/h3 (if present)
        $top3 = array_slice($results, 0, 3);
        $avg = ['words'=>0,'h2'=>0,'h3'=>0,'count'=>0];
        foreach ($top3 as $r) {
            $m = json_decode($r['metrics_json'] ?? '', true);
            if (!is_array($m)) { continue; }
            if (!empty($m['words']) || !empty($m['h2']) || !empty($m['h3'])) {
                $avg['words'] += (int)($m['words'] ?? 0);
                $avg['h2'] += (int)($m['h2'] ?? 0);
                $avg['h3'] += (int)($m['h3'] ?? 0);
                $avg['count']++;
            }
        }
        if ($avg['count']>0) {
            $avg['words'] = (int)round($avg['words']/$avg['count']);
            $avg['h2'] = (int)round($avg['h2']/$avg['count']);
            $avg['h3'] = (int)round($avg['h3']/$avg['count']);
        } else {
            $avg['words']=0; $avg['h2']=0; $avg['h3']=0;
        }

        // best effort: compare to HOME page snapshot if exists in Data Core table.
        $site_url = home_url('/');
        $gap = [
            'top3_avg' => $avg,
            'note' => 'GAP wyliczany na podstawie zaimportowanych danych SERP (best-effort).',
        ];

        // score (rough): normalize deltas if have avg words.
        $score = 0;
        if ($avg['words']>0) { $score += 40; }
        if ($avg['h2']>0) { $score += 30; }
        if ($avg['h3']>0) { $score += 30; }
        $score = min(100, $score);

        $this->wpdb->insert($this->t_gap, [
            'keyword_id' => $keyword_id,
            'url' => $site_url,
            'gap_score' => $score,
            'gap_json' => wp_json_encode($gap),
            'computed_at' => current_time('mysql'),
        ], ['%d','%s','%d','%s','%s']);
    }

    public function get_gap($keyword_id) {
        $this->ensure_tables();
        return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->t_gap} WHERE keyword_id=%d ORDER BY computed_at DESC LIMIT 1", (int)$keyword_id), ARRAY_A);
    }

}
