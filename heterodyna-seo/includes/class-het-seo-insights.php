<?php
if (!defined('ABSPATH')) { exit; }

/**
 * MEGA PACK C — Insights:
 * - Topic fingerprints (posts + term archives)
 * - Cannibalization / topic conflicts
 * - Canonical / noindex suggestions
 * - Semi-auto approval (first iteration: approvals + manual apply)
 *
 * Notes:
 * - Term suggestions are stored with post_id as negative term_id (e.g. -123).
 */
class HET_SEO_Insights {
    const TABLE_TOPICS = 'het_seo_topics';
    const TABLE_TERM_TOPICS = 'het_seo_term_topics';
    const TABLE_CONFLICTS = 'het_seo_conflicts';
    const TABLE_SUGGESTIONS = 'het_seo_suggestions';

    private $logger;
    private $license;

    public function __construct(HET_SEO_Logger $logger, HET_SEO_License $license) {
        $this->logger = $logger;
        $this->license = $license;

        // Upgrade-safe DB install (overwrites don't trigger activation hook)
        self::maybe_install();
    }

    public static function maybe_install() {
        $v = (string) get_option('het_seo_insights_db', '');
        if ($v === HET_SEO_VER) { return; }
        self::install();
        update_option('het_seo_insights_db', HET_SEO_VER, false);
    }

    private function get_exclusion_ids() {
        $ids = get_option('het_seo_insights_exclusions', []);
        if (!is_array($ids)) { $ids = []; }
        $out = [];
        foreach ($ids as $id) {
            $id = intval($id);
            if ($id > 0) { $out[$id] = true; }
        }
        return array_keys($out);
    }

    public function get_exclusions() {
        $ids = $this->get_exclusion_ids();
        $items = [];
        foreach ($ids as $pid) {
            $p = get_post($pid);
            if (!$p) { continue; }
            $items[] = [
                'post_id' => (int)$pid,
                'title' => (string)get_the_title($p),
                'url' => (string)get_permalink($p),
            ];
        }
        return $items;
    }

    public function add_exclusion($post_id) {
        $post_id = intval($post_id);
        if ($post_id < 1) { return new WP_Error('bad_post', 'Niepoprawny post_id'); }
        $p = get_post($post_id);
        if (!$p) { return new WP_Error('not_found', 'Nie znaleziono posta'); }
        $ids = $this->get_exclusion_ids();
        $ids[] = $post_id;
        $ids = array_values(array_unique(array_map('intval', $ids)));
        update_option('het_seo_insights_exclusions', $ids, false);
        return ['ok' => true];
    }

    public function remove_exclusion($post_id) {
        $post_id = intval($post_id);
        if ($post_id < 1) { return new WP_Error('bad_post', 'Niepoprawny post_id'); }
        $ids = $this->get_exclusion_ids();
        $ids = array_values(array_filter($ids, function($x) use ($post_id){ return intval($x) !== $post_id; }));
        update_option('het_seo_insights_exclusions', $ids, false);
        return ['ok' => true];
    }

    private function normalize_text($s) {
        $s = strtolower((string)$s);
        $s = wp_strip_all_tags($s);
        $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }

    private function tokens($s) {
        $s = $this->normalize_text($s);
        if ($s === '') { return []; }
        $parts = preg_split('/\s+/u', $s);
        $stop = [
            'i','oraz','albo','lub','że','to','na','w','we','z','za','do','od','po','pod','nad','przez','dla','u','o','a',
            'the','and','of','in','on','for','with','is','are','was','were','be','as','at','by','it','this','that'
        ];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') { continue; }
            if (mb_strlen($p) < 4) { continue; }
            if (in_array($p, $stop, true)) { continue; }
            $out[] = $p;
        }
        return $out;
    }

    private function intent_for_text($text) {
        $t = $this->normalize_text($text);
        if (preg_match('/\b(kup|cena|oferta|promocja|taniej|sklep|zamów|zamow|rezerwuj)\b/u', $t)) return 'transakcyjna';
        if (preg_match('/\b(opinie|recenzja|ranking|porównanie|porownanie|test)\b/u', $t)) return 'komercyjna';
        if (preg_match('/\b(jak|poradnik|instrukcja|co to|definicja|kiedy|dlaczego)\b/u', $t)) return 'informacyjna';
        return 'mieszana';
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $t_topics = $wpdb->prefix . self::TABLE_TOPICS;
        $t_term_topics = $wpdb->prefix . self::TABLE_TERM_TOPICS;
        $t_conf = $wpdb->prefix . self::TABLE_CONFLICTS;
        $t_sug = $wpdb->prefix . self::TABLE_SUGGESTIONS;

        $sql1 = "CREATE TABLE {$t_topics} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            url TEXT NOT NULL,
            post_type VARCHAR(64) NOT NULL,
            title TEXT NOT NULL,
            topic_hash CHAR(32) NOT NULL,
            kw_primary VARCHAR(191) NOT NULL DEFAULT '',
            word_count INT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id),
            KEY topic_hash (topic_hash),
            KEY post_type (post_type)
        ) {$charset};";

        $sql1b = "CREATE TABLE {$t_term_topics} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            term_id BIGINT UNSIGNED NOT NULL,
            taxonomy VARCHAR(64) NOT NULL,
            url TEXT NOT NULL,
            title TEXT NOT NULL,
            topic_hash CHAR(32) NOT NULL,
            kw_primary VARCHAR(191) NOT NULL DEFAULT '',
            word_count INT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY term_id (term_id),
            KEY topic_hash (topic_hash),
            KEY taxonomy (taxonomy)
        ) {$charset};";

        $sql2 = "CREATE TABLE {$t_conf} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            topic_hash CHAR(32) NOT NULL,
            kind VARCHAR(32) NOT NULL,
            items_json LONGTEXT NOT NULL,
            score INT NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY topic_hash (topic_hash),
            KEY kind (kind),
            KEY status (status)
        ) {$charset};";

        $sql3 = "CREATE TABLE {$t_sug} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT SIGNED NOT NULL,
            kind VARCHAR(32) NOT NULL,
            payload_json LONGTEXT NOT NULL,
            confidence INT NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL DEFAULT 'approval',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY kind (kind),
            KEY status (status)
        ) {$charset};";

        dbDelta($sql1);
        dbDelta($sql1b);
        dbDelta($sql2);
        dbDelta($sql3);
    }

    private function fingerprint_for_post(WP_Post $post) {
        $title = get_the_title($post);
        $content = wp_strip_all_tags($post->post_content ?? '');
        $content = wp_trim_words($content, 120, '');
        $tokens = $this->tokens($title . ' ' . $content);
        if (empty($tokens)) {
            $tokens = $this->tokens($title);
        }
        $freq = [];
        foreach ($tokens as $t) { $freq[$t] = ($freq[$t] ?? 0) + 1; }
        arsort($freq);
        $top = array_slice(array_keys($freq), 0, 10);
        $kw_primary = $top[0] ?? '';
        $hash = md5(implode('|', $top));
        $wc = max(0, (int) str_word_count($this->normalize_text($post->post_content ?? '')));
        return [
            'topic_hash' => $hash,
            'kw_primary' => $kw_primary,
            'word_count' => $wc,
        ];
    }

    private function fingerprint_for_term(WP_Term $term) {
        $title = $term->name;
        $content = wp_strip_all_tags($term->description ?? '');
        $content = wp_trim_words($content, 80, '');
        $tokens = $this->tokens($title . ' ' . $content);
        if (empty($tokens)) { $tokens = $this->tokens($title); }
        $freq = [];
        foreach ($tokens as $t) { $freq[$t] = ($freq[$t] ?? 0) + 1; }
        arsort($freq);
        $top = array_slice(array_keys($freq), 0, 10);
        $kw_primary = $top[0] ?? '';
        $hash = md5(implode('|', $top));
        $wc = max(0, (int) str_word_count($this->normalize_text($term->description ?? '')));
        return [
            'topic_hash' => $hash,
            'kw_primary' => $kw_primary,
            'word_count' => $wc,
        ];
    }

    /**
     * Scan posts/pages (and optionally terms) and rebuild topics + conflicts + suggestions.
     * Returns summary.
     */
    public function scan_and_build($limit = 500, $include_terms = true, $term_limit = 300, $taxonomies = []) {
        $limit = max(1, min(2000, (int)$limit));
        $term_limit = max(0, min(2000, (int)$term_limit));

        global $wpdb;
        $t_topics = $wpdb->prefix . self::TABLE_TOPICS;
        $t_term_topics = $wpdb->prefix . self::TABLE_TERM_TOPICS;
        $t_conf = $wpdb->prefix . self::TABLE_CONFLICTS;
        $t_sug = $wpdb->prefix . self::TABLE_SUGGESTIONS;

        $now = current_time('mysql');
        $excluded = array_flip($this->get_exclusion_ids());

        // --- Scan posts/pages
        $q = new WP_Query([
            'post_type' => get_post_types(['public' => true], 'names'),
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'modified',
            'order' => 'DESC',
            'fields' => 'all',
            'no_found_rows' => true,
        ]);

        $scanned_post_ids = [];
        $clusters = [];
        $seen_posts = 0;
        foreach ($q->posts as $post) {
            if (!($post instanceof WP_Post)) { continue; }
            if ($post->post_type === 'attachment') { continue; }
            if (isset($excluded[(int)$post->ID])) { continue; }

            $scanned_post_ids[] = (int)$post->ID;

            $fp = $this->fingerprint_for_post($post);
            $intent = $this->intent_for_text(get_the_title($post) . ' ' . ($post->post_content ?? ''));
            $url = get_permalink($post);

            $row = [
                'post_id' => (int)$post->ID,
                'url' => (string)$url,
                'post_type' => (string)$post->post_type,
                'title' => (string)get_the_title($post),
                'topic_hash' => (string)$fp['topic_hash'],
                'kw_primary' => (string)$fp['kw_primary'],
                'word_count' => (int)$fp['word_count'],
                'updated_at' => $now,
            ];

            $wpdb->replace($t_topics, $row, ['%d','%s','%s','%s','%s','%s','%d','%s']);

            $clusters[$row['topic_hash']][] = [
                'type' => 'post',
                'id' => $row['post_id'],
                'url' => $row['url'],
                'title' => $row['title'],
                'post_type' => $row['post_type'],
                'word_count' => $row['word_count'],
                'intent' => (string)$intent,
            ];
            $seen_posts++;
        }

        // --- Scan terms (categories/tags by default)
        $seen_terms = 0;
        $scanned_term_ids = [];
        if ($include_terms && $term_limit > 0) {
            if (!is_array($taxonomies) || empty($taxonomies)) {
                $taxonomies = ['category', 'post_tag'];
            }
            $taxonomies = array_values(array_unique(array_filter(array_map('sanitize_key', $taxonomies))));
            $taxonomies = array_values(array_filter($taxonomies, function($t){ return taxonomy_exists($t); }));

            if (!empty($taxonomies)) {
                $terms = get_terms([
                    'taxonomy' => $taxonomies,
                    'hide_empty' => false,
                    'number' => $term_limit,
                    'orderby' => 'count',
                    'order' => 'DESC',
                ]);

                if (!is_wp_error($terms) && is_array($terms)) {
                    foreach ($terms as $term) {
                        if (!($term instanceof WP_Term)) { continue; }
                        $scanned_term_ids[] = (int)$term->term_id;

                        $fp = $this->fingerprint_for_term($term);
                        $intent = $this->intent_for_text($term->name . ' ' . ($term->description ?? ''));
                        $url = get_term_link($term);
                        if (is_wp_error($url)) { $url = ''; }

                        $rowt = [
                            'term_id' => (int)$term->term_id,
                            'taxonomy' => (string)$term->taxonomy,
                            'url' => (string)$url,
                            'title' => (string)$term->name,
                            'topic_hash' => (string)$fp['topic_hash'],
                            'kw_primary' => (string)$fp['kw_primary'],
                            'word_count' => (int)$fp['word_count'],
                            'updated_at' => $now,
                        ];
                        $wpdb->replace($t_term_topics, $rowt, ['%d','%s','%s','%s','%s','%s','%d','%s']);

                        $clusters[$rowt['topic_hash']][] = [
                            'type' => 'term',
                            'id' => $rowt['term_id'],
                            'taxonomy' => $rowt['taxonomy'],
                            'url' => $rowt['url'],
                            'title' => $rowt['title'],
                            'word_count' => $rowt['word_count'],
                            'intent' => (string)$intent,
                        ];
                        $seen_terms++;
                    }
                }
            }
        }

        // --- Wipe pending suggestions for scanned targets (approval/auto) to avoid duplicates
        if (!empty($scanned_post_ids)) {
            $place = implode(',', array_fill(0, count($scanned_post_ids), '%d'));
            $wpdb->query($wpdb->prepare("DELETE FROM {$t_sug} WHERE status IN ('approval','auto') AND post_id IN ({$place})", $scanned_post_ids));
        }
        if (!empty($scanned_term_ids)) {
            $neg = array_map(function($x){ return -1 * abs((int)$x); }, $scanned_term_ids);
            $place = implode(',', array_fill(0, count($neg), '%d'));
            $wpdb->query($wpdb->prepare("DELETE FROM {$t_sug} WHERE status IN ('approval','auto') AND post_id IN ({$place})", $neg));
        }

        // --- Thin content → suggest noindex (manual approval) for scanned posts
        $sug_count = 0;
        foreach ($scanned_post_ids as $pid) {
            $wc = (int)$wpdb->get_var($wpdb->prepare("SELECT word_count FROM {$t_topics} WHERE post_id=%d", $pid));
            if ($wc > 0 && $wc < 120) {
                $already = get_post_meta($pid, 'het_seo_noindex', true);
                if ($already) { continue; }
                $conf = ($wc < 80) ? 88 : 76;
                $payload = [
                    'suggest' => 'noindex',
                    'reason' => 'Thin-content: bardzo krótka treść (word_count < 120).',
                ];
                $wpdb->insert($t_sug, [
                    'post_id' => (int)$pid,
                    'kind' => 'noindex',
                    'payload_json' => wp_json_encode($payload),
                    'confidence' => $conf,
                    'status' => 'approval',
                    'created_at' => $now,
                    'updated_at' => $now,
                ], ['%d','%s','%s','%d','%s','%s','%s']);
                $sug_count++;
            }
        }

        // --- Rebuild conflicts/suggestions (deterministic: wipe open rows and rebuild for scanned hashes)
        $hashes = array_keys($clusters);
        if (!empty($hashes)) {
            $place = implode(',', array_fill(0, count($hashes), '%s'));
            $wpdb->query($wpdb->prepare("DELETE FROM {$t_conf} WHERE status='open' AND topic_hash IN ({$place})", $hashes));
        }

        $conf_count = 0;
        foreach ($clusters as $hash => $items) {
            if (count($items) < 2) { continue; }

            // Sort by "content strength": posts usually win over terms at same wc
            usort($items, function($a,$b){
                $aw = (int)($a['word_count'] ?? 0);
                $bw = (int)($b['word_count'] ?? 0);
                if (($a['type'] ?? '') !== ($b['type'] ?? '') && $aw === $bw) {
                    // Prefer post over term
                    return (($b['type'] ?? '') === 'post') ? 1 : -1;
                }
                return $bw <=> $aw;
            });

            $has_post = false;
            $has_term = false;
            foreach ($items as $it) {
                if (($it['type'] ?? '') === 'post') $has_post = true;
                if (($it['type'] ?? '') === 'term') $has_term = true;
            }

            $kind = 'cannibal';
            if ($has_post && $has_term) {
                $kind = 'term_vs_post';
            } elseif ($has_term && !$has_post) {
                $kind = 'term_cannibal';
            }

            $score = count($items);
            $wpdb->insert($t_conf, [
                'topic_hash' => $hash,
                'kind' => $kind,
                'items_json' => wp_json_encode($items),
                'score' => $score,
                'status' => 'open',
                'created_at' => $now,
                'updated_at' => $now,
            ], ['%s','%s','%s','%d','%s','%s','%s']);
            $conf_id = (int)$wpdb->insert_id;
            if ($conf_id) { $conf_count++; }

            // Suggestions logic
            $primary = $items[0];
            $p_wc = max(1, (int)($primary['word_count'] ?? 1));
            $p_intent = (string)($primary['intent'] ?? '');

            foreach ($items as $idx => $it) {
                if ($idx === 0) { continue; }

                $i_type = (string)($it['type'] ?? 'post');
                $i_id = (int)($it['id'] ?? 0);
                if ($i_id < 1) { continue; }

                $wc = max(0, (int)($it['word_count'] ?? 0));
                $ratio = $wc > 0 ? ($p_wc / max(1,$wc)) : 999;
                $confidence = 70;
                if ($ratio >= 1.4) { $confidence = 90; }
                elseif ($ratio >= 1.15) { $confidence = 82; }
                else { $confidence = 74; }

                // intent mismatch lowers confidence
                $i_intent = (string)($it['intent'] ?? '');
                if ($p_intent && $i_intent && $p_intent !== $i_intent) {
                    $confidence = max(55, $confidence - 18);
                }

                // Case: primary is post, secondary is term -> suggest term noindex (safer than canonical)
                if (($primary['type'] ?? '') === 'post' && $i_type === 'term') {
                    $payload = [
                        'conflict_id' => $conf_id,
                        'topic_hash' => $hash,
                        'suggest' => 'noindex',
                        'target' => 'term',
                        'term_id' => $i_id,
                        'taxonomy' => (string)($it['taxonomy'] ?? ''),
                        'reason' => 'Konflikt wpis vs archiwum (term): bezpieczna sugestia to noindex dla archiwum.',
                    ];
                    $wpdb->insert($t_sug, [
                        'post_id' => -1 * abs($i_id),
                        'kind' => 'noindex',
                        'payload_json' => wp_json_encode($payload),
                        'confidence' => max(60, min(92, $confidence)),
                        'status' => 'approval',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], ['%d','%s','%s','%d','%s','%s','%s']);
                    $sug_count++;
                    continue;
                }

                // Default: canonical to primary URL (works for post targets, and also for term targets)
                $payload = [
                    'conflict_id' => $conf_id,
                    'topic_hash' => $hash,
                    'suggest' => 'canonical',
                    'canonical_to' => (string)($primary['url'] ?? ''),
                    'reason' => ($kind === 'term_vs_post')
                        ? 'Konflikt archiwum (term) vs wpis: ujednolicenie canonical.'
                        : 'Kanibalizacja tematu: kilka URL-i dla tego samego fingerprintu.',
                ];

                $target_post_id = ($i_type === 'term') ? (-1 * abs($i_id)) : $i_id;

                $wpdb->insert($t_sug, [
                    'post_id' => (int)$target_post_id,
                    'kind' => 'canonical',
                    'payload_json' => wp_json_encode($payload),
                    'confidence' => $confidence,
                    'status' => ($confidence >= 88 ? 'auto' : 'approval'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ], ['%d','%s','%s','%d','%s','%s','%s']);
                $sug_count++;
            }
        }

        return [
            'scanned_posts' => $seen_posts,
            'scanned_terms' => $seen_terms,
            'conflicts_open' => $conf_count,
            'suggestions_created' => $sug_count,
        ];
    }

    public function list_conflicts($kind = 'cannibal', $limit = 100) {
        global $wpdb;
        $t_conf = $wpdb->prefix . self::TABLE_CONFLICTS;
        $limit = max(1, min(200, (int)$limit));
        $kind = sanitize_key($kind);
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t_conf} WHERE status='open' AND kind=%s ORDER BY score DESC, id DESC LIMIT %d", $kind, $limit), ARRAY_A);

        $merge_map = $this->get_merge_map();

        foreach ($rows as &$r) {
            $r['items'] = [];
            $decoded = json_decode($r['items_json'] ?? '', true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $r['items'] = $decoded;
            }

            // winner/loser heuristic (safe, no mutations)
            $winner_id = 0;
            $winner_score = -1;
            foreach ($r['items'] as $it) {
                $id = (int)($it['id'] ?? 0);
                $type = $it['type'] ?? (($it['taxonomy'] ?? '') ? 'term' : 'post');

                $score = 0;
                if ($type === 'post' && $id > 0) {
                    $p = get_post($id);
                    if ($p && !is_wp_error($p)) {
                        $score += 200; // prefer posts over terms
                        $score += min(5000, str_word_count(wp_strip_all_tags($p->post_content)));
                        $score += (int)($p->comment_count ?? 0);
                    }
                } else {
                    // term id stored as positive in items but in our suggestion system term ids are negative
                    $term_id = $id;
                    $tax = $it['taxonomy'] ?? '';
                    $t = ($term_id && $tax) ? get_term($term_id, $tax) : null;
                    if ($t && !is_wp_error($t)) {
                        $score += 100;
                        $score += min(2000, str_word_count(wp_strip_all_tags((string)$t->description)));
                        $score += (int)($t->count ?? 0);
                    }
                }

                if ($score > $winner_score) {
                    $winner_score = $score;
                    // for terms we expose as negative id to be consistent across UI
                    $winner_id = ($type === 'term') ? (-1 * abs($id)) : $id;
                }
            }

            $r['winner_id'] = $winner_id;
            $r['loser_ids'] = [];
            foreach ($r['items'] as $it) {
                $id = (int)($it['id'] ?? 0);
                $type = $it['type'] ?? (($it['taxonomy'] ?? '') ? 'term' : 'post');
                $cid = ($type === 'term') ? (-1 * abs($id)) : $id;
                if ($cid && $cid !== $winner_id) $r['loser_ids'][] = $cid;

                // mark merge candidate flag for UI
                $th = (string)($r['topic_hash'] ?? '');
                if ($th && isset($merge_map[$th]) && isset($merge_map[$th][(string)$cid])) {
                    $it['merge_marked'] = true;
                }
            }

            // attach merge map for this conflict (lightweight)
            $th = (string)($r['topic_hash'] ?? '');
            $r['merge_candidates'] = ($th && isset($merge_map[$th]) && is_array($merge_map[$th])) ? array_values($merge_map[$th]) : [];
        }
        return $rows;
    }

    public function list_suggestions($status = null, $limit = 200) {
        global $wpdb;
        $t = $wpdb->prefix . self::TABLE_SUGGESTIONS;
        $limit = max(1, min(500, (int)$limit));
        if ($status) {
            $status = sanitize_key($status);
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} WHERE status=%s ORDER BY confidence DESC, id DESC LIMIT %d", $status, $limit), ARRAY_A);
        } else {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} ORDER BY confidence DESC, id DESC LIMIT %d", $limit), ARRAY_A);
        }
        foreach ($rows as &$r) {
            $r['payload'] = [];
            $decoded = json_decode($r['payload_json'] ?? '', true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $r['payload'] = $decoded;
            }

            $pid = (int)($r['post_id'] ?? 0);
            if ($pid < 0) {
                $term_id = abs($pid);
                $term = get_term($term_id);
                $r['url'] = '';
                $r['title'] = '(term #' . $term_id . ')';
                if ($term && !is_wp_error($term)) {
                    $r['title'] = (string)$term->name . ' (' . (string)$term->taxonomy . ')';
                    $u = get_term_link($term);
                    if (!is_wp_error($u)) { $r['url'] = (string)$u; }
                }
            } else {
                $r['url'] = ($pid > 0) ? (string)get_permalink($pid) : '';
                $r['title'] = ($pid > 0) ? (string)get_the_title($pid) : '';
            }
        }
        return $rows;
    }

    public function ignore_suggestion($id) {
        global $wpdb;
        $t = $wpdb->prefix . self::TABLE_SUGGESTIONS;
        $id = (int)$id;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d", $id), ARRAY_A);
        if (!$row) { return new WP_Error('not_found', 'Nie znaleziono sugestii'); }
        if (($row['status'] ?? '') === 'ignored') { return ['already' => true]; }
        $wpdb->update($t, [
            'status' => 'ignored',
            'updated_at' => current_time('mysql'),
        ], ['id' => $id], ['%s','%s'], ['%d']);
        return ['ignored' => true];
    }


    public function mark_merge_candidate($topic_hash, $winner_id, $loser_id) {
        if (!$topic_hash || !$winner_id || !$loser_id) {
            return new WP_Error('bad_request', 'Brak danych');
        }
        $topic_hash = sanitize_text_field($topic_hash);
        $winner_id = (int)$winner_id;
        $loser_id = (int)$loser_id;

        $opt_key = 'het_seo_merge_candidates';
        $map = get_option($opt_key, []);
        if (!is_array($map)) $map = [];
        if (!isset($map[$topic_hash]) || !is_array($map[$topic_hash])) {
            $map[$topic_hash] = [];
        }
        $map[$topic_hash][(string)$loser_id] = [
            'winner_id' => $winner_id,
            'loser_id'  => $loser_id,
            'topic_hash'=> $topic_hash,
            'marked_at' => current_time('mysql'),
        ];
        update_option($opt_key, $map, false);
        return ['topic_hash' => $topic_hash, 'winner_id' => $winner_id, 'loser_id' => $loser_id];
    }

    private function get_merge_map() {
        $map = get_option('het_seo_merge_candidates', []);
        return is_array($map) ? $map : [];
    }

    public function apply_suggestion($id) {
        global $wpdb;
        $t = $wpdb->prefix . self::TABLE_SUGGESTIONS;
        $id = (int)$id;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d", $id), ARRAY_A);
        if (!$row) { return new WP_Error('not_found', 'Nie znaleziono sugestii'); }
        if (($row['status'] ?? '') === 'applied') { return ['already' => true]; }

        $target = (int)($row['post_id'] ?? 0);
        if ($target === 0) { return new WP_Error('bad_target', 'Niepoprawny target'); }

        $kind = sanitize_key($row['kind'] ?? '');
        $payload = json_decode($row['payload_json'] ?? '', true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
            $payload = [];
        }

        $is_term = ($target < 0);
        if ($is_term) {
            $term_id = abs($target);
            $term = get_term($term_id);
            if (!$term || is_wp_error($term)) { return new WP_Error('bad_term', 'Niepoprawny term'); }

            if ($kind === 'canonical') {
                $to = isset($payload['canonical_to']) ? esc_url_raw($payload['canonical_to']) : '';
                if ($to === '') { return new WP_Error('bad_payload', 'Brak canonical_to'); }
                update_term_meta($term_id, 'het_seo_canonical', $to);
            } elseif ($kind === 'noindex') {
                update_term_meta($term_id, 'het_seo_noindex', 1);
            } else {
                return new WP_Error('bad_kind', 'Nieobsługiwany typ sugestii');
            }
        } else {
            $post_id = $target;
            if ($post_id < 1) { return new WP_Error('bad_post', 'Niepoprawny post_id'); }

            if ($kind === 'canonical') {
                $to = isset($payload['canonical_to']) ? esc_url_raw($payload['canonical_to']) : '';
                if ($to === '') { return new WP_Error('bad_payload', 'Brak canonical_to'); }
                update_post_meta($post_id, 'het_seo_canonical', $to);
            } elseif ($kind === 'noindex') {
                update_post_meta($post_id, 'het_seo_noindex', 1);
            } else {
                return new WP_Error('bad_kind', 'Nieobsługiwany typ sugestii');
            }
        }

        $wpdb->update($t, [
            'status' => 'applied',
            'updated_at' => current_time('mysql'),
        ], ['id' => $id], ['%s','%s'], ['%d']);

        return ['applied' => true];
    }

    /**
     * Mass apply: apply up to $limit suggestions with confidence >= $min_confidence.
     * Only suggestions with status=$status are considered (default: approval).
     */
    public function apply_bulk($min_confidence = 88, $limit = 50, $status = 'approval') {
        global $wpdb;
        $t = $wpdb->prefix . self::TABLE_SUGGESTIONS;

        $min_confidence = max(0, min(100, (int)$min_confidence));
        $limit = max(1, min(500, (int)$limit));
        $status = sanitize_key($status ?: 'approval');

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$t} WHERE status=%s AND confidence >= %d ORDER BY confidence DESC, id DESC LIMIT %d",
            $status,
            $min_confidence,
            $limit
        ), ARRAY_A);

        $applied = 0;
        $errors = [];
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            if (!$id) { continue; }
            $res = $this->apply_suggestion($id);
            if (is_wp_error($res)) {
                $errors[] = ['id' => $id, 'error' => $res->get_error_message()];
                continue;
            }
            $applied++;
        }

        return [
            'applied' => $applied,
            'considered' => count($rows),
            'min_confidence' => $min_confidence,
            'status' => $status,
            'errors' => $errors,
        ];
    }
}
