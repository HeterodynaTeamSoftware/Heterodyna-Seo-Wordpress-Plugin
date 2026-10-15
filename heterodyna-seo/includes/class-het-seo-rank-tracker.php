<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Rank Tracker (v03.05+)
 *
 * Stores keyword positions over time, imported from:
 * - GSC exports (avg position)
 * - external rank tools CSV/JSON
 *
 * We intentionally do not scrape SERPs directly from WP.
 */
class HET_SEO_Rank_Tracker {
    const TABLE_KW = 'het_seo_rank_keywords';
    const TABLE_POS = 'het_seo_rank_positions';
    const TABLE_DOMAIN = 'het_seo_domain_rank';

    private $logger;

    public function __construct(HET_SEO_Logger $logger) {
        $this->logger = $logger;
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $tkw = $wpdb->prefix . self::TABLE_KW;
        $tpos = $wpdb->prefix . self::TABLE_POS;
        $tdom = $wpdb->prefix . self::TABLE_DOMAIN;

        $sql1 = "CREATE TABLE $tkw (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            keyword VARCHAR(255) NOT NULL,
            engine VARCHAR(32) NOT NULL DEFAULT 'google',
            locale VARCHAR(16) NOT NULL DEFAULT 'pl-PL',
            device VARCHAR(16) NOT NULL DEFAULT 'mobile',
            target_url TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY keyword (keyword(191)),
            KEY engine (engine),
            KEY is_active (is_active)
        ) $charset;";

        $sql2 = "CREATE TABLE $tpos (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            kw_id BIGINT(20) UNSIGNED NOT NULL,
            checked_at DATETIME NOT NULL,
            position FLOAT NULL,
            url TEXT NULL,
            title TEXT NULL,
            source VARCHAR(64) NOT NULL DEFAULT 'manual',
            raw_json LONGTEXT NULL,
            PRIMARY KEY (id),
            KEY kw_id (kw_id),
            KEY checked_at (checked_at)
        ) $charset;";

        $sql3 = "CREATE TABLE $tdom (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            engine VARCHAR(32) NOT NULL DEFAULT 'google',
            checked_at DATETIME NOT NULL,
            domain VARCHAR(255) NOT NULL,
            visibility_score FLOAT NULL,
            keywords_top3 INT NULL,
            keywords_top10 INT NULL,
            keywords_top50 INT NULL,
            raw_json LONGTEXT NULL,
            PRIMARY KEY (id),
            KEY engine (engine),
            KEY checked_at (checked_at),
            KEY domain (domain(191))
        ) $charset;";

        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
    }

    public function add_keyword($keyword, $engine='google', $locale='pl-PL', $device='mobile', $target_url='') {
        global $wpdb;
        $keyword = trim(wp_strip_all_tags((string)$keyword));
        if ($keyword === '') { return 0; }
        $engine = sanitize_key($engine);
        $locale = preg_replace('~[^a-zA-Z0-9\-\_]~', '', (string)$locale);
        $device = sanitize_key($device);
        $tkw = $wpdb->prefix . self::TABLE_KW;

        $wpdb->insert($tkw, [
            'keyword'=>$keyword,
            'engine'=>$engine ?: 'google',
            'locale'=>$locale ?: 'pl-PL',
            'device'=>$device ?: 'mobile',
            'target_url'=> $target_url ? esc_url_raw($target_url) : null,
            'is_active'=>1,
            'created_at'=>current_time('mysql'),
        ], ['%s','%s','%s','%s','%s','%d','%s']);

        return (int)$wpdb->insert_id;
    }

    public function get_keywords($limit=200) {
        global $wpdb;
        $tkw = $wpdb->prefix . self::TABLE_KW;
        $limit = max(1, min(1000, (int)$limit));
        $rows = $wpdb->get_results("SELECT * FROM $tkw WHERE is_active=1 ORDER BY id DESC LIMIT ".intval($limit), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    public function get_last_position_for_kw($kw_id) {
        global $wpdb;
        $tpos = $wpdb->prefix . self::TABLE_POS;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tpos WHERE kw_id=%d ORDER BY checked_at DESC, id DESC LIMIT 1", (int)$kw_id), ARRAY_A);
        return $row ? $row : null;
    }

    public function import_positions($rows, $source='manual') {
        global $wpdb;
        $tpos = $wpdb->prefix . self::TABLE_POS;
        $tkw = $wpdb->prefix . self::TABLE_KW;

        $inserted = 0;
        foreach ((array)$rows as $r) {
            $kw = isset($r['keyword']) ? trim((string)$r['keyword']) : '';
            if ($kw === '') { continue; }
            $engine = sanitize_key($r['engine'] ?? 'google');
            $locale = preg_replace('~[^a-zA-Z0-9\-\_]~', '', (string)($r['locale'] ?? 'pl-PL'));
            $device = sanitize_key($r['device'] ?? 'mobile');

            // Find existing kw
            $kw_id = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $tkw WHERE keyword=%s AND engine=%s AND locale=%s AND device=%s LIMIT 1",
                $kw, $engine ?: 'google', $locale ?: 'pl-PL', $device ?: 'mobile'
            ));
            if (!$kw_id) {
                $kw_id = $this->add_keyword($kw, $engine, $locale, $device, $r['target_url'] ?? '');
                if (!$kw_id) { continue; }
            }

            $checked_at = $r['checked_at'] ?? current_time('mysql');
            $pos = isset($r['position']) ? floatval($r['position']) : null;
            $url = isset($r['url']) ? esc_url_raw($r['url']) : null;
            $title = isset($r['title']) ? wp_strip_all_tags((string)$r['title']) : null;
            $raw_json = isset($r['raw_json']) ? wp_json_encode($r['raw_json']) : null;

            $wpdb->insert($tpos, [
                'kw_id'=>$kw_id,
                'checked_at'=>$checked_at,
                'position'=>$pos,
                'url'=>$url,
                'title'=>$title,
                'source'=>sanitize_key($source ?: 'manual'),
                'raw_json'=>$raw_json,
            ], ['%d','%s','%f','%s','%s','%s','%s']);
            $inserted++;
        }
        return $inserted;
    }

    public static function parse_import_text($text) {
        $text = trim((string)$text);
        if ($text === '') { return []; }

        // Try JSON
        if (preg_match('~^\s*\[~', $text)) {
            $arr = json_decode($text, true);
            if (is_array($arr)) { return $arr; }
        }

        $rows = [];
        $lines = preg_split('~\r?\n~', $text);
        foreach ($lines as $ln) {
            $ln = trim($ln);
            if ($ln === '' || strpos($ln, '#') === 0) { continue; }
            // CSV/TSV/; separated: keyword;position;url;checked_at;engine;locale;device
            $parts = preg_split('~[;\t,]~', $ln);
            $parts = array_map('trim', $parts);
            if (count($parts) < 2) { continue; }
            $rows[] = [
                'keyword' => $parts[0],
                'position' => $parts[1],
                'url' => $parts[2] ?? '',
                'checked_at' => $parts[3] ?? current_time('mysql'),
                'engine' => $parts[4] ?? 'google',
                'locale' => $parts[5] ?? 'pl-PL',
                'device' => $parts[6] ?? 'mobile',
            ];
        }
        return $rows;
    }
}
