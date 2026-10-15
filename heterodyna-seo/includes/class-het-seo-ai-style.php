<?php
if (!defined('ABSPATH')) { exit; }

/**
 * MEGA PACK B: Style Profile + Brand Rules
 *
 * Goal:
 * - Keep meta generation consistent for the whole site
 * - Enforce brand rules (must include / never use / preferred synonyms)
 * - Validate before save (still works in dry-run and semi-auto)
 *
 * This class hooks ONLY via filter: het_seo_ai_generate_meta
 */
class HET_SEO_AI_Style {
    const OPT_PROFILE = 'het_seo_ai_style_profile';

    /** @var HET_SEO_Logger */
    private $logger;

    public function __construct(HET_SEO_Logger $logger) {
        $this->logger = $logger;

        add_filter('het_seo_ai_generate_meta', [$this, 'filter_generate_meta'], 20, 3);
    }

    public static function defaults_profile() {
        return [
            'enabled' => 1,
            'tone' => 'neutral', // neutral|info|sales
            'separator' => '—',
            'title_suffix' => '', // e.g. "Heterodyna"
            'title_avg' => 48,
            'desc_avg' => 130,
            'allow_emoji' => 0,
        ];
    }

    public static function get_profile() {
        $raw = get_option(self::OPT_PROFILE, []);
        $p = wp_parse_args(is_array($raw) ? $raw : [], self::defaults_profile());
        $p['enabled'] = !empty($p['enabled']) ? 1 : 0;
        $p['tone'] = in_array(($p['tone'] ?? 'neutral'), ['neutral','info','sales'], true) ? $p['tone'] : 'neutral';
        $p['separator'] = isset($p['separator']) ? (string)$p['separator'] : '—';
        if (!in_array($p['separator'], ['—','-','|','·','/'], true)) { $p['separator'] = '—'; }
        $p['title_suffix'] = sanitize_text_field($p['title_suffix'] ?? '');
        $p['title_avg'] = max(10, min(80, intval($p['title_avg'])));
        $p['desc_avg'] = max(30, min(160, intval($p['desc_avg'])));
        $p['allow_emoji'] = !empty($p['allow_emoji']) ? 1 : 0;
        return $p;
    }

    public static function get_rules() {
        $opts = get_option(HET_SEO_OPT, []);
        $must = isset($opts['ai_brand_must']) ? (string)$opts['ai_brand_must'] : '';
        $never = isset($opts['ai_brand_never']) ? (string)$opts['ai_brand_never'] : '';
        $syn = isset($opts['ai_brand_syn']) ? (string)$opts['ai_brand_syn'] : '';

        return [
            'must' => self::lines_to_list($must),
            'never' => self::lines_to_list($never),
            'syn' => self::lines_to_map($syn),
        ];
    }

    private static function lines_to_list($text) {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', (string)$text) as $line) {
            $line = trim(wp_strip_all_tags($line));
            if ($line !== '') { $out[] = $line; }
        }
        return array_values(array_unique($out));
    }

    private static function lines_to_map($text) {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', (string)$text) as $line) {
            $line = trim(wp_strip_all_tags($line));
            if ($line === '') { continue; }
            // Format: from => to OR from=to
            if (strpos($line, '=>') !== false) {
                [$a, $b] = array_map('trim', explode('=>', $line, 2));
            } elseif (strpos($line, '=') !== false) {
                [$a, $b] = array_map('trim', explode('=', $line, 2));
            } else {
                continue;
            }
            if ($a !== '' && $b !== '') {
                $out[$a] = $b;
            }
        }
        return $out;
    }

    public function filter_generate_meta($out, $post, $opts) {
        $profile = self::get_profile();
        if (empty($profile['enabled'])) {
            return $out;
        }

        $rules = self::get_rules();

        $title = isset($out['title']) ? (string)$out['title'] : '';
        $desc  = isset($out['description']) ? (string)$out['description'] : '';

        $title = $this->apply_synonyms($title, $rules['syn']);
        $desc  = $this->apply_synonyms($desc,  $rules['syn']);

        $title = $this->remove_never($title, $rules['never']);
        $desc  = $this->remove_never($desc,  $rules['never']);

        // Tone tweaks (lightweight, safe)
        if ($profile['tone'] === 'info') {
            $desc = $this->ensure_prefix($desc, 'Poradnik: ');
        } elseif ($profile['tone'] === 'sales') {
            // Mild CTA, no clickbait.
            $desc = $this->ensure_suffix($desc, ' Sprawdź szczegóły.');
        }

        // Must include phrases (apply to desc first, then title if needed)
        foreach ($rules['must'] as $phrase) {
            if ($phrase === '') { continue; }
            if (!$this->contains_ci($desc, $phrase)) {
                $desc = trim($desc . ' ' . $phrase);
            }
        }

        // Optional suffix in title
        if (!empty($profile['title_suffix'])) {
            $suffix = $profile['title_suffix'];
            if (!$this->contains_ci($title, $suffix)) {
                $sep = ' ' . $profile['separator'] . ' ';
                $title = trim($title) . $sep . $suffix;
            }
        }

        // Validate + normalize
        [$title2, $desc2, $note] = $this->validate_and_normalize($title, $desc, $profile, $rules);
        if ($note) {
            $this->logger->log('AI Style adjusted', ['post_id' => $post instanceof WP_Post ? $post->ID : 0, 'note' => $note]);
        }

        return [
            'title' => $title2,
            'description' => $desc2,
        ];
    }

    private function apply_synonyms($text, $map) {
        $text = (string)$text;
        if (!$map) { return $text; }
        foreach ($map as $from => $to) {
            if ($from === '' || $to === '') { continue; }
            $text = str_ireplace($from, $to, $text);
        }
        return $text;
    }

    private function remove_never($text, $list) {
        $text = (string)$text;
        if (!$list) { return $text; }
        foreach ($list as $bad) {
            if ($bad === '') { continue; }
            $text = str_ireplace($bad, '', $text);
        }
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private function ensure_prefix($text, $prefix) {
        $text = trim((string)$text);
        if ($text === '') { return $text; }
        if (stripos($text, $prefix) === 0) { return $text; }
        return $prefix . $text;
    }

    private function ensure_suffix($text, $suffix) {
        $text = trim((string)$text);
        if ($text === '') { return $text; }
        if ($this->contains_ci($text, trim($suffix))) { return $text; }
        return rtrim($text, '.') . '.' . $suffix;
    }

    private function contains_ci($haystack, $needle) {
        return (stripos((string)$haystack, (string)$needle) !== false);
    }

    private function strip_emoji_if_needed($text, $allow_emoji) {
        if ($allow_emoji) { return $text; }
        // Basic emoji range removal (safe fallback)
        $text = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $text);
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private function validate_and_normalize($title, $desc, $profile, $rules) {
        $note = '';

        $title = trim(wp_strip_all_tags((string)$title));
        $desc  = trim(wp_strip_all_tags((string)$desc));

        $title = $this->strip_emoji_if_needed($title, !empty($profile['allow_emoji']));
        $desc  = $this->strip_emoji_if_needed($desc,  !empty($profile['allow_emoji']));

        // Hard length caps (safe)
        $tmax = 60;
        $dmax = 155;

        if (mb_strlen($title) > $tmax) {
            $title = mb_substr($title, 0, $tmax);
            $note .= 'Title przycięty. ';
        }
        if (mb_strlen($desc) > $dmax) {
            $desc = mb_substr($desc, 0, $dmax);
            $note .= 'Description przycięty. ';
        }

        // Ensure must-include still present after trimming
        foreach ((array)($rules['must'] ?? []) as $phrase) {
            if ($phrase === '') { continue; }
            if (!$this->contains_ci($desc, $phrase)) {
                // Append, but keep cap
                $desc = trim($desc . ' ' . $phrase);
                if (mb_strlen($desc) > $dmax) {
                    $desc = mb_substr($desc, 0, $dmax);
                }
                $note .= 'Dodano frazę obowiązkową. ';
            }
        }

        // Final cleanup spaces
        $title = trim(preg_replace('/\s+/', ' ', $title));
        $desc  = trim(preg_replace('/\s+/', ' ', $desc));

        return [$title, $desc, trim($note)];
    }

    /**
     * Rebuild style profile based on existing meta across selected post types.
     * Lightweight: just averages lengths and picks most common separator.
     */
    public static function rebuild_profile($post_types = ['post','page'], $limit = 200) {
        $post_types = array_values(array_filter(array_map('sanitize_key', (array)$post_types)));
        if (!$post_types) { $post_types = ['post','page']; }
        $limit = max(20, min(1000, intval($limit)));

        $q = new WP_Query([
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        $titles = [];
        $descs = [];
        $sep_counts = ['—'=>0,'-'=>0,'|'=>0,'·'=>0,'/'=>0];

        foreach ($q->posts as $pid) {
            $t = (string)get_post_meta($pid, 'het_seo_meta_title', true);
            $d = (string)get_post_meta($pid, 'het_seo_meta_description', true);
            if ($t === '') { $t = wp_strip_all_tags(get_the_title($pid)); }
            if ($d === '') { $d = wp_trim_words(wp_strip_all_tags(get_post_field('post_content', $pid)), 30, ''); }
            $t = trim($t);
            $d = trim($d);
            if ($t !== '') { $titles[] = mb_strlen($t); }
            if ($d !== '') { $descs[] = mb_strlen($d); }
            foreach (array_keys($sep_counts) as $sep) {
                if (strpos($t, $sep) !== false) { $sep_counts[$sep]++; }
            }
        }

        $avg_t = $titles ? intval(array_sum($titles) / count($titles)) : 48;
        $avg_d = $descs ? intval(array_sum($descs) / count($descs)) : 130;

        arsort($sep_counts);
        $best_sep = key($sep_counts);
        if (!$best_sep) { $best_sep = '—'; }

        $p = self::get_profile();
        $p['title_avg'] = max(10, min(80, $avg_t));
        $p['desc_avg'] = max(30, min(160, $avg_d));
        $p['separator'] = $best_sep;
        update_option(self::OPT_PROFILE, $p, false);
        return $p;
    }
}
