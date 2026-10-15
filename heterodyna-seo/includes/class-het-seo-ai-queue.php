<?php
if (!defined('ABSPATH')) { exit; }

class HET_SEO_AI_Queue {
    const TABLE = 'het_seo_ai_queue';

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVAL = 'approval'; // requires user approval (semi-auto)

    const STATUS_RUNNING = 'running';
    const STATUS_DONE = 'done';
    const STATUS_ERROR = 'error';
    const STATUS_SKIPPED = 'skipped';

    const FIX_MISSING_META = 'missing_meta';
    const FIX_DUPLICATES = 'duplicates';
    const FIX_TOO_LONG = 'too_long';
    const FIX_CTR = 'ctr_style';

    private $logger;
    private $license;
    private $rollback;

    public function __construct(HET_SEO_Logger $logger, HET_SEO_License $license, HET_SEO_Rollback $rollback) {
        $this->logger = $logger;
        $this->license = $license;
        $this->rollback = $rollback;

        
// Ensure custom schedules exist BEFORE scheduling events (prevents invalid_schedule)
add_filter('cron_schedules', function($s) {
    if (!isset($s['five_minutes'])) {
        $s['five_minutes'] = ['interval' => 300, 'display' => 'Every 5 minutes'];
    }
    return $s;
});

add_action('het_seo_cron_process_queue', [$this, 'cron_process']);
if (!wp_next_scheduled('het_seo_cron_process_queue')) {
    wp_schedule_event(time() + 120, 'five_minutes', 'het_seo_cron_process_queue');
}
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            site_id BIGINT UNSIGNED NULL,
            post_id BIGINT UNSIGNED NULL,
            url TEXT NULL,
            fix_type VARCHAR(50) NOT NULL,
            priority INT NOT NULL DEFAULT 100,
            impact_score INT NOT NULL DEFAULT 0,
            requires_approval TINYINT(1) NOT NULL DEFAULT 0,
            approved_by BIGINT UNSIGNED NULL,
            approved_at DATETIME NULL,

            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts INT NOT NULL DEFAULT 0,
            last_error TEXT NULL,
            dry_run TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY status_priority (status, priority),
            KEY post_id (post_id),
            KEY fix_type (fix_type)
        ) $charset;";
        dbDelta($sql);
    }

    public function enqueue_post($post_id, $fix_type, $priority, $dry_run = false, $requires_approval = false, $impact_score = 0) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $wpdb->insert($table, [
            'site_id' => get_current_blog_id(),
            'post_id' => intval($post_id),
            'url' => get_permalink($post_id),
            'fix_type' => sanitize_key($fix_type),
            'priority' => intval($priority),
            'impact_score' => intval($impact_score),
            'requires_approval' => $requires_approval ? 1 : 0,
            'status' => $requires_approval ? self::STATUS_APPROVAL : self::STATUS_PENDING,
            'attempts' => 0,
            'last_error' => null,
            'dry_run' => $dry_run ? 1 : 0,
            'approved_by' => null,
            'approved_at' => null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ], ['%d','%d','%s','%s','%d','%s','%d','%s','%d','%s','%s']);
        $this->logger->log('Enqueued', ['post_id' => $post_id, 'fix_type' => $fix_type, 'priority' => $priority, 'impact' => $impact_score, 'approval' => $requires_approval, 'dry_run' => $dry_run]);
    }

    public function scan_and_enqueue($args = []) {
        $defaults = [
            'post_types' => ['post', 'page'],
            'limit' => 200,
            'dry_run' => true,
            'include_ctr' => false,
        ];
        $args = wp_parse_args($args, $defaults);

        // Dry-run is always allowed. Real enqueue/processing should be gated by active license.
        if (empty($args['dry_run']) && !$this->license->can_run_write_actions()) {
            return new WP_Error('license', 'Build open-source: wykonywanie napraw jest dostępne.');
        }

        $q = new WP_Query([
            'post_type' => $args['post_types'],
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(1000, intval($args['limit']))),
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        $count = 0;
        foreach ($q->posts as $post_id) {
            $prio_base = 100;
            // Priority order: missing meta -> duplicates -> too long -> ctr
            $missing = $this->needs_missing_meta(intval($post_id));
            $too_long = $this->needs_too_long(intval($post_id));
            if ($missing) {
                $this->enqueue_post($post_id, self::FIX_MISSING_META, 10, $args['dry_run']);
                $count++;
            }
            // duplicates and canonical require heuristics; enqueue light tasks
            $this->enqueue_post($post_id, self::FIX_DUPLICATES, 20, $args['dry_run']);
            $count++;
            if ($too_long) {
                $this->enqueue_post($post_id, self::FIX_TOO_LONG, 30, $args['dry_run']);
                $count++;
            }
            if (!empty($args['include_ctr'])) {
                $this->enqueue_post($post_id, self::FIX_CTR, 40, $args['dry_run']);
                $count++;
            }
        }
        return ['enqueued' => $count];
    }

    
    public function list_approvals($limit = 200) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $limit = max(1, min(500, intval($limit)));
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE status = %s ORDER BY impact_score DESC, priority ASC, id ASC LIMIT %d", self::STATUS_APPROVAL, $limit));
    }

    public function approve_items($ids = []) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $ids = array_map('intval', (array)$ids);
        $ids = array_values(array_filter($ids));
        if (!$ids) { return 0; }
        $in = implode(',', array_fill(0, count($ids), '%d'));
        $params = array_merge([self::STATUS_PENDING, get_current_user_id(), current_time('mysql')], $ids);
        $sql = $wpdb->prepare("UPDATE $table SET status=%s, approved_by=%d, approved_at=%s, updated_at=%s WHERE id IN ($in) AND status=%s",
                              array_merge([self::STATUS_PENDING, get_current_user_id(), current_time('mysql'), current_time('mysql'), self::STATUS_APPROVAL], $ids));
        $wpdb->query($sql);
        return $wpdb->rows_affected;
    }

public function list_queue($status = null, $limit = 200) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $limit = max(1, min(500, intval($limit)));
        if ($status) {
            return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE status = %s ORDER BY priority ASC, id ASC LIMIT $limit", $status));
        }
        return $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT $limit");
    }

    public function cron_process() {
        $this->process_next(10);
    }

    public function process_next($max = 5) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $max = max(1, min(50, intval($max)));

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status = %s ORDER BY impact_score DESC, priority ASC, id ASC LIMIT %d",
            self::STATUS_PENDING,
            $max
        ));

        $done = 0;
        foreach ($items as $item) {
            $id = intval($item->id);
            $post_id = intval($item->post_id);
            $dry = !empty($item->dry_run);

            // Writes require active license; dry-run is allowed.
            if (!$dry && !$this->license->can_run_write_actions()) {
                $this->mark_error($id, 'Build open-source: wykonywanie napraw jest dostępne.');
                continue;
            }

            $wpdb->update($table, ['status' => self::STATUS_RUNNING, 'updated_at' => current_time('mysql')], ['id' => $id], ['%s','%s'], ['%d']);

            try {
                $res = $this->apply_fix($post_id, $item->fix_type, $id, $dry);
                if (is_wp_error($res)) {
                    $this->mark_error($id, $res->get_error_message());
                } else {
                    $wpdb->update($table, ['status' => self::STATUS_DONE, 'updated_at' => current_time('mysql'), 'last_error' => null], ['id' => $id], ['%s','%s','%s'], ['%d']);
                    $done++;
                }
            } catch (Throwable $e) {
                $this->mark_error($id, $e->getMessage());
            }
        }

        return $done;
    }

    private function mark_error($id, $msg) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $id = intval($id);
        $msg = wp_strip_all_tags($msg);
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET status=%s, attempts=attempts+1, last_error=%s, updated_at=%s WHERE id=%d",
            self::STATUS_ERROR,
            $msg,
            current_time('mysql'),
            $id
        ));
        $this->logger->log('Queue item error', ['id' => $id, 'msg' => $msg]);
    }


    private function apply_fix($post_id, $fix_type, $queue_id, $dry_run) {
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return new WP_Error('post', 'Post nie istnieje lub nie jest opublikowany.');
        }

        // Internal meta keys (safe, non-invasive)
        $k_title = 'het_seo_meta_title';
        $k_desc  = 'het_seo_meta_description';
        $k_canon = 'het_seo_canonical';

        $title = get_post_meta($post_id, $k_title, true);
        $desc  = get_post_meta($post_id, $k_desc, true);

        if ($fix_type === self::FIX_MISSING_META) {
            if ($title && $desc) { return true; }
            $gen = $this->generate_meta($post, ['mode' => 'missing']);
            return $this->write_meta($post_id, $queue_id, $dry_run, $k_title, $k_desc, $gen['title'], $gen['description']);
        }

        if ($fix_type === self::FIX_TOO_LONG) {
            $gen = $this->generate_meta($post, ['mode' => 'shorten']);
            return $this->write_meta($post_id, $queue_id, $dry_run, $k_title, $k_desc, $gen['title'], $gen['description']);
        }

        if ($fix_type === self::FIX_CTR) {
            $gen = $this->generate_meta($post, ['mode' => 'ctr']);
            return $this->write_meta($post_id, $queue_id, $dry_run, $k_title, $k_desc, $gen['title'], $gen['description']);
        }

        if ($fix_type === self::FIX_DUPLICATES) {
            // Safe canonicalization: if canonical is empty, set to permalink.
            $canon = get_post_meta($post_id, $k_canon, true);
            if ($canon) { return true; }
            $new = get_permalink($post_id);
            if ($dry_run) { return true; }
            $this->rollback->record_meta_change($queue_id, $post_id, $k_canon, $canon, $new);
            update_post_meta($post_id, $k_canon, $new);
            return true;
        }

        return new WP_Error('fix', 'Nieznany typ naprawy.');
    }

    private function write_meta($post_id, $queue_id, $dry_run, $k_title, $k_desc, $new_title, $new_desc) {
        $old_title = get_post_meta($post_id, $k_title, true);
        $old_desc  = get_post_meta($post_id, $k_desc, true);

        if ($dry_run) {
            return true;
        }

        $new_title = sanitize_text_field($new_title);
        $new_desc  = sanitize_text_field($new_desc);

        $this->rollback->record_meta_change($queue_id, $post_id, $k_title, $old_title, $new_title);
        $this->rollback->record_meta_change($queue_id, $post_id, $k_desc,  $old_desc,  $new_desc);

        update_post_meta($post_id, $k_title, $new_title);
        update_post_meta($post_id, $k_desc,  $new_desc);

        return true;
    }

    private function generate_meta(WP_Post $post, $opts = []) {
        $mode = $opts['mode'] ?? 'missing';
        $site = get_bloginfo('name');
        $base_title = wp_strip_all_tags(get_the_title($post));

        $title = $base_title;
        $desc = '';

        $excerpt = $post->post_excerpt ? $post->post_excerpt : wp_trim_words(wp_strip_all_tags($post->post_content), 30, '');
        $desc = $excerpt;

        if ($mode === 'ctr') {
            // CTR-safe: add a mild hook, no clickbait.
            $title = $base_title . ' — praktycznie i konkretnie';
            $desc = $excerpt;
        }

        // Length constraints
        $title = mb_substr($title, 0, 60);
        $desc  = mb_substr($desc, 0, 155);

        // Allow external AI provider to override
        $out = apply_filters('het_seo_ai_generate_meta', [
            'title' => $title,
            'description' => $desc,
        ], $post, $opts);

        return [
            'title' => $out['title'] ?? $title,
            'description' => $out['description'] ?? $desc,
        ];
    }

    private function needs_missing_meta($post_id) {
        $t = get_post_meta($post_id, 'het_seo_meta_title', true);
        $d = get_post_meta($post_id, 'het_seo_meta_description', true);
        return empty($t) || empty($d);
    }

    private function needs_too_long($post_id) {
        $t = get_post_meta($post_id, 'het_seo_meta_title', true);
        $d = get_post_meta($post_id, 'het_seo_meta_description', true);
        return (mb_strlen($t) > 60) || (mb_strlen($d) > 155);
    }
}
