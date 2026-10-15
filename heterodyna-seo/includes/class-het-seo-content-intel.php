<?php
if (!defined('ABSPATH')) { exit; }

/**
 * CONTENT INTELLIGENCE (v03.02)
 *
 * Best-effort offline content analytics:
 * - word/sentence/paragraph counts
 * - presence of FAQ/lists/tables/images/video
 * - basic intent classifier (info/transactional/navigational)
 * - actionable "gaps" checklist
 */
class HET_SEO_Content_Intel {
    const TABLE_INTEL = 'het_seo_content_intel';
    // Back-compat alias (used by Architecture module)
    const TABLE = 'het_seo_content_intel';

    private $logger;

    public function __construct(HET_SEO_Logger $logger) {
        $this->logger = $logger;
        add_action('het_seo_cron_content_intel_batch', [$this, 'cron_batch']);
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $t = $wpdb->prefix . self::TABLE_INTEL;

        $sql = "CREATE TABLE {$t} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            url TEXT NOT NULL,
            content_hash CHAR(40) NOT NULL DEFAULT '',
            intent VARCHAR(16) NOT NULL DEFAULT 'unknown',
            metrics_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY post_id_hash (post_id, content_hash),
            KEY post_id (post_id),
            KEY created_at (created_at)
        ) {$charset};";

        dbDelta($sql);
    }

    public static function ensure_cron() {
        if (!wp_next_scheduled('het_seo_cron_content_intel_batch')) {
            // small hourly batches, safe for shared hosting
            wp_schedule_event(time() + 420, 'hourly', 'het_seo_cron_content_intel_batch');
        }
    }

    public function cron_batch() {
        try {
            $this->analyze_batch(25);
        } catch (Throwable $e) {
            $this->logger->log('content_intel_batch_error', $e->getMessage());
        }
    }

    public function analyze_batch($limit = 25) {
        $limit = max(10, min(200, (int)$limit));
        $offset = (int) get_option('het_seo_content_intel_offset', 0);

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
            update_option('het_seo_content_intel_offset', 0);
            return 0;
        }

        $saved = 0;
        foreach ($ids as $pid) {
            if ($this->analyze_post((int)$pid)) {
                $saved++;
            }
        }

        update_option('het_seo_content_intel_offset', $offset + count($ids));
        return $saved;
    }

    public function analyze_post($post_id) {
        global $wpdb;
        $t = $wpdb->prefix . self::TABLE_INTEL;

        $post = get_post($post_id);
        if (!$post) { return false; }

        $url = (string) get_permalink($post_id);
        if (!$url) { return false; }

        $raw = (string) $post->post_content;
        $rendered = $this->best_effort_render($raw);
        $txt = trim((string) wp_strip_all_tags($rendered));

        $meta_title = (string) get_post_meta($post_id, 'het_seo_meta_title', true);
        $meta_desc  = (string) get_post_meta($post_id, 'het_seo_meta_description', true);

        $hash = sha1($txt . '|' . $meta_title . '|' . $meta_desc);

        // skip if we already have this hash
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t} WHERE post_id=%d AND content_hash=%s LIMIT 1",
            $post_id,
            $hash
        ));
        if ($exists) { return false; }

        $metrics = $this->build_metrics($post_id, $post, $rendered, $txt);
        $intent = $metrics['intent'] ?? 'unknown';

        $now = current_time('mysql');
        $wpdb->insert($t, [
            'post_id'      => $post_id,
            'url'          => $url,
            'content_hash' => $hash,
            'intent'       => (string) $intent,
            'metrics_json' => wp_json_encode($metrics),
            'created_at'   => $now,
        ]);

        return true;
    }

    public function get_latest_for_post($post_id) {
        global $wpdb;
        $t = $wpdb->prefix . self::TABLE_INTEL;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$t} WHERE post_id=%d ORDER BY id DESC LIMIT 1",
            (int)$post_id
        ), ARRAY_A);
        if (!$row) { return null; }
        $m = json_decode((string)($row['metrics_json'] ?? ''), true);
        if (!is_array($m)) { $m = []; }
        $m['_row'] = $row;
        return $m;
    }

    /**
     * Detailed analysis view for a single post/page.
     * Returns an associative array ready for templates.
     */
    public function get_detail($post_id) {
        $post_id = (int)$post_id;
        if ($post_id <= 0) { return null; }

        $latest = $this->get_latest_for_post($post_id);

        // best-effort: generate analysis on the fly if missing (common after overwrite-upgrade)
        if (!$latest) {
            try { $this->analyze_post($post_id); } catch (Throwable $e) { /* ignore */ }
            $latest = $this->get_latest_for_post($post_id);
        }

        if (!is_array($latest)) { $latest = []; }

        return [
            'post_id' => $post_id,
            'type' => (string) get_post_type($post_id),
            'title' => (string) get_the_title($post_id),
            'url' => (string) get_permalink($post_id),
            'intent' => (string)($latest['intent'] ?? 'unknown'),
            'counts' => is_array(($latest['counts'] ?? null)) ? ($latest['counts'] ?? []) : [],
            'features' => is_array(($latest['features'] ?? null)) ? ($latest['features'] ?? []) : [],
            'scores' => is_array(($latest['scores'] ?? null)) ? ($latest['scores'] ?? []) : [],
            'gaps' => is_array(($latest['gaps'] ?? null)) ? ($latest['gaps'] ?? []) : [],
            'row' => is_array(($latest['_row'] ?? null)) ? ($latest['_row'] ?? []) : [],
        ];
    }

    public function get_overview($limit = 200) {
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
            $latest = $this->get_latest_for_post($pid);
            $counts = is_array(($latest['counts'] ?? null)) ? ($latest['counts'] ?? []) : [];
            $features = is_array(($latest['features'] ?? null)) ? ($latest['features'] ?? []) : [];
            $out[] = [
                'post_id' => $pid,
                'type' => get_post_type($pid),
                'title' => (string) get_the_title($pid),
                'url' => (string) get_permalink($pid),
                'modified' => (string) get_post_modified_time('Y-m-d H:i:s', false, $pid, true),
                'intent' => (string)($latest['intent'] ?? 'unknown'),
                'words' => (int)($counts['words'] ?? 0),
                'reading_min' => (int)($counts['reading_min'] ?? 0),
                'score' => (int)($latest['scores']['content'] ?? 0),
                'gaps_top' => $latest['gaps']['top'] ?? [],
                // Backward compatible payload used by templates (metrics.*)
                'metrics' => [
                    'words' => (int)($counts['words'] ?? 0),
                    'sentences' => (int)($counts['sentences'] ?? 0),
                    'paragraphs' => (int)($counts['paragraphs'] ?? 0),
                    'images' => (int)($features['images'] ?? 0),
                    'lists' => (int)($features['lists'] ?? 0),
                    'tables' => (int)($features['tables'] ?? 0),
                    'has_faq' => !empty($features['faq']),
                ],
            ];
        }
        return $out;
    }

    public function manual_collect_sitewide($limit = 200) {
        // lightweight: analyze newest N posts/pages immediately
        $limit = max(10, min(500, (int)$limit));
        $q = new WP_Query([
            'post_type'      => ['post', 'page'],
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);
        $saved = 0;
        foreach ((array)$q->posts as $pid) {
            if ($this->analyze_post((int)$pid)) { $saved++; }
        }
        return $saved;
    }

    private function best_effort_render($raw) {
        // Keep it safe: do shortcodes only (no full the_content filter side-effects).
        $raw = (string) $raw;
        if (has_shortcode($raw, '')) {
            // noop; has_shortcode requires tag, ignore
        }
        $rendered = do_shortcode($raw);
        return $rendered;
    }

    private function build_metrics($post_id, $post, $html, $txt) {
        $title = (string) get_the_title($post_id);

        $counts = $this->count_bundle($txt, $html);
        $intent = $this->infer_intent($title, $txt);
        $features = $this->detect_features($html);
        $gaps = $this->build_gaps($intent, $features, $counts, $title, $txt);

        $score = $this->score_content($intent, $features, $counts, $gaps);

        return [
            'post_id' => (int)$post_id,
            'url' => (string) get_permalink($post_id),
            'type' => (string) $post->post_type,
            'title' => $title,
            'intent' => $intent,
            'counts' => $counts,
            'features' => $features,
            'gaps' => $gaps,
            'scores' => [
                'content' => $score,
            ],
            'created_at' => current_time('mysql'),
        ];
    }

    private function count_bundle($txt, $html) {
        $words = $this->count_words($txt);
        $chars = function_exists('mb_strlen') ? mb_strlen($txt, 'UTF-8') : strlen($txt);
        $sentences = $this->count_sentences($txt);
        $paragraphs = $this->count_paragraphs($html, $txt);

        // reading time: 200 wpm
        $reading_min = $words > 0 ? (int) max(1, ceil($words / 200)) : 0;

        return [
            'words' => $words,
            'chars' => (int)$chars,
            'sentences' => $sentences,
            'paragraphs' => $paragraphs,
            'reading_min' => $reading_min,
        ];
    }

    private function detect_features($html) {
        $html = (string) $html;
        $has_faq = (bool) preg_match('/FAQ|Najczęściej|Pytania\s+i\s+odpowiedzi/i', $html);
        $lists = 0;
        if (preg_match_all('/<(ul|ol)\b/i', $html, $m)) { $lists = count($m[0]); }
        $tables = 0;
        if (preg_match_all('/<table\b/i', $html, $m2)) { $tables = count($m2[0]); }
        $images = 0;
        if (preg_match_all('/<img\b/i', $html, $m3)) { $images = count($m3[0]); }
        $videos = 0;
        if (preg_match_all('/<iframe\b/i', $html, $m4)) { $videos = count($m4[0]); }
        $headings = ['h1'=>0,'h2'=>0,'h3'=>0,'h4'=>0,'h5'=>0,'h6'=>0];
        foreach ($headings as $tag => $_) {
            if (preg_match_all('/<' . $tag . '\b[^>]*>/i', $html, $mm)) {
                $headings[$tag] = count($mm[0]);
            }
        }

        return [
            'faq' => $has_faq ? 1 : 0,
            'lists' => $lists,
            'tables' => $tables,
            'images' => $images,
            'videos' => $videos,
            'headings' => $headings,
        ];
    }

    private function infer_intent($title, $txt) {
        $s = strtolower($title . ' ' . $txt);

        $score_info = 0;
        $score_trans = 0;
        $score_nav = 0;

        // informational
        foreach (['jak ', 'co to', 'poradnik', 'instrukcja', 'krok po kroku', 'dlaczego', 'ile ', 'czy ', 'przykład', 'wzór'] as $kw) {
            if (strpos($s, $kw) !== false) { $score_info += 2; }
        }

        // transactional
        foreach (['kup', 'cena', 'promoc', 'oferta', 'zamów', 'sklep', 'abonament', 'pakiet', 'wycena', 'koszt', 'rabat'] as $kw) {
            if (strpos($s, $kw) !== false) { $score_trans += 2; }
        }

        // navigational / brand
        foreach (['kontakt', 'o nas', 'logowanie', 'panel', 'regulamin', 'polityka prywatności'] as $kw) {
            if (strpos($s, $kw) !== false) { $score_nav += 2; }
        }

        // choose
        $max = max($score_info, $score_trans, $score_nav);
        if ($max <= 0) { return 'unknown'; }
        if ($max === $score_trans) { return 'transactional'; }
        if ($max === $score_nav) { return 'navigational'; }
        return 'informational';
    }

    private function build_gaps($intent, $features, $counts, $title, $txt) {
        $gaps = [];

        // universal
        if (($features['headings']['h2'] ?? 0) < 2) {
            $gaps[] = ['key'=>'need_h2', 'label'=>'Dodaj więcej sekcji H2 (min. 2–4)'];
        }
        if (($counts['words'] ?? 0) < 450) {
            $gaps[] = ['key'=>'thin_content', 'label'=>'Treść jest krótka (rozważ min. 450–800 słów)'];
        }
        if (($features['lists'] ?? 0) < 1) {
            $gaps[] = ['key'=>'need_list', 'label'=>'Dodaj listę (UL/OL) – poprawia czytelność i snippet'];
        }
        if (($features['images'] ?? 0) < 1) {
            $gaps[] = ['key'=>'need_image', 'label'=>'Dodaj min. 1 obraz (wspiera UX i czas na stronie)'];
        }

        // intent-specific
        if ($intent === 'informational') {
            if (!$features['faq']) {
                $gaps[] = ['key'=>'need_faq', 'label'=>'Dodaj sekcję FAQ (PAA / rich results)'];
            }
            if (stripos($txt, 'podsum') === false && stripos($txt, 'wniosk') === false) {
                $gaps[] = ['key'=>'need_summary', 'label'=>'Dodaj krótkie podsumowanie/wnioski na końcu'];
            }
        }

        if ($intent === 'transactional') {
            $has_cta = (bool) preg_match('/zamów|kup|dodaj do koszyka|skontaktuj|wyślij zapytanie|zarezerwuj/i', $txt);
            if (!$has_cta) {
                $gaps[] = ['key'=>'need_cta', 'label'=>'Dodaj mocne CTA (np. zamów / zapytaj / zarezerwuj)'];
            }
            if (($features['tables'] ?? 0) < 1) {
                $gaps[] = ['key'=>'need_pricing_table', 'label'=>'Dodaj tabelę (np. porównanie / cennik)'];
            }
            if (!$features['faq']) {
                $gaps[] = ['key'=>'need_faq', 'label'=>'Dodaj FAQ – redukuje obiekcje i zwiększa konwersję'];
            }
        }

        // top list
        $top = array_slice(array_map(function($g){ return $g['label']; }, $gaps), 0, 3);

        return [
            'items' => $gaps,
            'top' => $top,
            'count' => count($gaps),
        ];
    }

    private function score_content($intent, $features, $counts, $gaps) {
        $score = 100;

        // penalties
        $score -= (int) min(40, (($gaps['count'] ?? 0) * 8));

        // light bonuses
        if (($features['tables'] ?? 0) >= 1) { $score += 3; }
        if (($features['faq'] ?? 0) >= 1) { $score += 5; }
        if (($features['images'] ?? 0) >= 2) { $score += 2; }
        if (($counts['words'] ?? 0) >= 1200) { $score += 3; }

        $score = max(0, min(100, $score));
        return $score;
    }

    private function count_words($text) {
        $text = trim((string)$text);
        if ($text === '') { return 0; }
        $parts = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($parts) ? count($parts) : 0;
    }

    private function count_sentences($text) {
        $text = trim((string)$text);
        if ($text === '') { return 0; }
        $parts = preg_split('/[\.!\?]+\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($parts) ? count($parts) : 0;
    }

    private function count_paragraphs($html, $txt) {
        $html = (string)$html;
        if (preg_match_all('/<p\b/i', $html, $m)) {
            return count($m[0]);
        }
        // fallback: blank line split
        $parts = preg_split('/\n\s*\n/u', (string)$txt, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($parts) ? count($parts) : 0;
    }
}
