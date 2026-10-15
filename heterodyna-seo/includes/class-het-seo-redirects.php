<?php
if (!defined('ABSPATH')) { exit; }

/** Simple redirect manager (301/302) */
class HET_SEO_Redirects {
    private $logger;
    private $table;

    public function __construct(HET_SEO_Logger $logger) {
        global $wpdb;
        $this->logger = $logger;
        $this->table = $wpdb->prefix . 'het_seo_redirects';
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $t = $wpdb->prefix . 'het_seo_redirects';
        $sql = "CREATE TABLE {$t} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            from_path VARCHAR(255) NOT NULL,
            to_url TEXT NOT NULL,
            code SMALLINT NOT NULL DEFAULT 301,
            hits INT NOT NULL DEFAULT 0,
            last_hit DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY from_path (from_path)
        ) {$charset};";
        dbDelta($sql);
    }

    public function add_redirect($from_path, $to_url, $code = 301) {
        global $wpdb;
        $from_path = trim((string)$from_path);
        if ($from_path === '') { return false; }
        if ($from_path[0] !== '/') { $from_path = '/' . $from_path; }
        $to_url = esc_url_raw($to_url);
        if (!$to_url) { return false; }
        $code = (int)$code;
        if (!in_array($code, [301,302], true)) { $code = 301; }
        $now = current_time('mysql');
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->table} (from_path, to_url, code, hits, last_hit, created_at)
             VALUES (%s, %s, %d, 0, NULL, %s)
             ON DUPLICATE KEY UPDATE to_url=VALUES(to_url), code=VALUES(code)",
            $from_path, $to_url, $code, $now
        ));
        return true;
    }

    public function delete_redirect($id) {
        global $wpdb;
        $id = (int)$id;
        if ($id <= 0) { return false; }
        $wpdb->delete($this->table, ['id' => $id]);
        return true;
    }

    public function list_redirects($limit = 200) {
        global $wpdb;
        $limit = max(1, min(1000, (int)$limit));
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} ORDER BY id DESC LIMIT %d", $limit), ARRAY_A);
    }

    public function maybe_redirect() {
        if (is_admin()) { return; }
        global $wpdb;
        $uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
        if ($uri === '') { return; }
        $path = wp_parse_url($uri, PHP_URL_PATH);
        if (!$path) { return; }

        // Smart auto-redirect (safe): if path matches existing post/page slug.
        $opts = get_option(HET_SEO_OPT, []);
        if (!empty($opts['auto_redirect_smart'])) {
            $maybe = $this->smart_match_permalink($path);
            if ($maybe) {
                wp_redirect($maybe, 301);
                exit;
            }
        }
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE from_path = %s LIMIT 1", $path), ARRAY_A);
        if (!$row) { return; }
        $to = (string)$row['to_url'];
        $code = (int)$row['code'];
        // update stats
        $wpdb->query($wpdb->prepare("UPDATE {$this->table} SET hits=hits+1, last_hit=%s WHERE id=%d", current_time('mysql'), (int)$row['id']));
        wp_redirect($to, $code);
        exit;
    }

    private function smart_match_permalink($path) {
        $path = (string)$path;
        if ($path === '' || $path === '/') { return ''; }
        // normalize
        $candidate = trim($path);
        $candidate = strtok($candidate, '?');
        $candidate = strtok($candidate, '#');
        $candidate = trim($candidate);
        $candidate = trim($candidate, '/');
        if ($candidate === '') { return ''; }

        $public_types = get_post_types(['public'=>true], 'names');
        unset($public_types['attachment']);

        // Try exact path
        $p = get_page_by_path($candidate, OBJECT, $public_types);
        if ($p && !empty($p->ID)) {
            return get_permalink($p->ID);
        }
        // Try sanitized last segment
        $parts = explode('/', $candidate);
        if (count($parts) > 1) {
            $last = end($parts);
            $last = sanitize_title($last);
            $parts[count($parts)-1] = $last;
            $cand2 = implode('/', $parts);
            $p2 = get_page_by_path($cand2, OBJECT, $public_types);
            if ($p2 && !empty($p2->ID)) {
                return get_permalink($p2->ID);
            }
        }
        return '';
    }
}
