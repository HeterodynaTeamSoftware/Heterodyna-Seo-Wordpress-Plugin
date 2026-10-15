<?php
if (!defined('ABSPATH')) { exit; }

/**
 * ARCHITECTURE (v03.04)
 *
 * Goal: "WOW" tabs with data flood around information architecture:
 * - Cannibalization (URL vs URL)
 * - Topic clusters (best-effort grouping)
 * - Site structure overview
 *
 * Notes:
 * - no external APIs
 * - heavy computations are cached (transients)
 */
class HET_SEO_Architecture {
    private $logger;

    public function __construct(HET_SEO_Logger $logger) {
        $this->logger = $logger;
    }

    /**
     * Return latest content intel rows indexed by post_id.
     */
    public function get_latest_intel($limit = 250) {
        global $wpdb;
        $table = $wpdb->prefix . HET_SEO_Content_Intel::TABLE;

        // If table missing, return empty.
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if (!$exists) { return []; }

        // Latest row per post_id (best-effort). For performance we load more and pick newest.
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", max(50, (int)$limit * 3)),
            ARRAY_A
        );

        $out = [];
        if (!is_array($rows)) { return []; }
        foreach ($rows as $r) {
            $pid = (int)($r['post_id'] ?? 0);
            if ($pid <= 0) { continue; }
            if (isset($out[$pid])) { continue; }
            $out[$pid] = $this->normalize_intel_row($r);
            if (count($out) >= $limit) { break; }
        }
        return $out;
    }

    private function normalize_intel_row($r) {
        $metrics = [];
        if (!empty($r['metrics_json'])) {
            $decoded = json_decode((string)$r['metrics_json'], true);
            if (is_array($decoded)) { $metrics = $decoded; }
        }
        $r['metrics'] = $metrics;
        return $r;
    }

    private function tokenize($text) {
        $text = strtolower(wp_strip_all_tags((string)$text));
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);
        $parts = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $stop = [
            'i','a','o','u','w','z','na','do','od','pod','nad','dla','oraz','ale','czy','to','ten','ta','te',
            'jest','są','być','się','jak','co','kiedy','gdzie','który','która','które','twoje','nasze'
        ];
        $stop = array_fill_keys($stop, true);
        $tokens = [];
        foreach ($parts as $p) {
            if (mb_strlen($p) < 3) { continue; }
            if (isset($stop[$p])) { continue; }
            $tokens[] = $p;
        }
        return $tokens;
    }

    private function jaccard($a, $b) {
        $sa = array_fill_keys($a, true);
        $sb = array_fill_keys($b, true);
        $inter = 0;
        foreach ($sa as $k => $_) {
            if (isset($sb[$k])) { $inter++; }
        }
        $union = count($sa) + count($sb) - $inter;
        if ($union <= 0) { return 0.0; }
        return $inter / $union;
    }

    /**
     * Compute cannibalization candidates.
     * Returns list sorted by score desc: [post_id_a, post_id_b, score, reason]
     */
    public function get_cannibalization($limit_pairs = 80) {
        $cache_key = 'het_seo_arch_cannibal_v1';
        $cached = get_transient($cache_key);
        if (is_array($cached)) { return $cached; }

        $intel = $this->get_latest_intel(220);
        if (count($intel) < 5) {
            set_transient($cache_key, [], HOUR_IN_SECONDS);
            return [];
        }

        // Prepare token sets per post from title + H1 + top headings.
        $docs = [];
        foreach ($intel as $pid => $row) {
            $post = get_post($pid);
            if (!$post || $post->post_status !== 'publish') { continue; }
            $m = (array)($row['metrics'] ?? []);
            $h1 = (string)($m['h1'] ?? '');
            $heads = $m['heads'] ?? [];
            $head_text = '';
            if (is_array($heads)) {
                // take up to 10 headings
                $n = 0;
                foreach ($heads as $hx => $arr) {
                    if (!is_array($arr)) { continue; }
                    foreach ($arr as $t) {
                        $head_text .= ' ' . (string)$t;
                        $n++;
                        if ($n >= 10) { break 2; }
                    }
                }
            }

            $blob = $post->post_title . ' ' . $h1 . ' ' . $head_text;
            $tokens = $this->tokenize($blob);
            if (count($tokens) < 6) { continue; }
            $docs[$pid] = $tokens;
        }

        $pids = array_keys($docs);
        $pairs = [];
        $cnt = count($pids);
        for ($i = 0; $i < $cnt; $i++) {
            for ($j = $i + 1; $j < $cnt; $j++) {
                $a = $pids[$i];
                $b = $pids[$j];
                $score = $this->jaccard($docs[$a], $docs[$b]);
                if ($score < 0.28) { continue; }
                $pairs[] = [
                    'a' => $a,
                    'b' => $b,
                    'score' => $score,
                    'reason' => 'Podobne tytuły/H1/nagłówki (Jaccard)'
                ];
            }
        }

        usort($pairs, function($x, $y) {
            return $y['score'] <=> $x['score'];
        });

        $pairs = array_slice($pairs, 0, max(20, (int)$limit_pairs));
        set_transient($cache_key, $pairs, HOUR_IN_SECONDS);
        return $pairs;
    }

    /**
     * Best-effort clusters: groups by primary category + similarity centroid.
     */
    public function get_clusters($limit_clusters = 30) {
        $cache_key = 'het_seo_arch_clusters_v1';
        $cached = get_transient($cache_key);
        if (is_array($cached)) { return $cached; }

        $intel = $this->get_latest_intel(260);
        $items = [];
        foreach ($intel as $pid => $row) {
            $post = get_post($pid);
            if (!$post || $post->post_status !== 'publish') { continue; }
            $cats = get_the_category($pid);
            $cat = (!empty($cats) && isset($cats[0])) ? (string)$cats[0]->name : 'Bez kategorii';
            $items[] = [
                'post_id' => $pid,
                'cat' => $cat,
                'title' => $post->post_title,
                'tokens' => $this->tokenize($post->post_title)
            ];
        }

        // Group by category
        $by = [];
        foreach ($items as $it) {
            $by[$it['cat']][] = $it;
        }

        $clusters = [];
        foreach ($by as $cat => $arr) {
            $clusters[] = [
                'name' => $cat,
                'count' => count($arr),
                'items' => array_slice($arr, 0, 12),
            ];
        }

        usort($clusters, function($a, $b) { return $b['count'] <=> $a['count']; });
        $clusters = array_slice($clusters, 0, max(10, (int)$limit_clusters));
        set_transient($cache_key, $clusters, HOUR_IN_SECONDS);
        return $clusters;
    }

    /**
     * Structure overview: basic counts + top templates + orphan pages.
     */
    public function get_structure_overview($limit_orphans = 20) {
        $cache_key = 'het_seo_arch_structure_v1';
        $cached = get_transient($cache_key);
        if (is_array($cached)) { return $cached; }

        global $wpdb;
        $posts_total = (int)$wpdb->get_var("SELECT COUNT(1) FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('post','page')");

        // Orphans: pages with 0 internal inbound links (best-effort using datacore URL snapshots metrics)
        $orphans = [];
        $snap_table = $wpdb->prefix . HET_SEO_Data_Core::TABLE_URL_SNAPSHOT;
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $snap_table));
        if ($exists) {
            $rows = $wpdb->get_results("SELECT post_id, metrics_json FROM {$snap_table} ORDER BY id DESC LIMIT 800", ARRAY_A);
            $seen = [];
            foreach ($rows as $r) {
                $pid = (int)($r['post_id'] ?? 0);
                if ($pid <= 0 || isset($seen[$pid])) { continue; }
                $seen[$pid] = true;
                $m = json_decode((string)$r['metrics_json'], true);
                if (!is_array($m)) { continue; }
                $in = (int)($m['internal_in'] ?? 0);
                if ($in === 0) {
                    $post = get_post($pid);
                    if ($post && $post->post_status === 'publish') {
                        $orphans[] = [ 'post_id' => $pid, 'title' => $post->post_title, 'url' => get_permalink($pid) ];
                        if (count($orphans) >= $limit_orphans) { break; }
                    }
                }
            }
        }

        $out = [
            'posts_total' => $posts_total,
            'orphans' => $orphans,
        ];
        set_transient($cache_key, $out, HOUR_IN_SECONDS);
        return $out;
    }
}
