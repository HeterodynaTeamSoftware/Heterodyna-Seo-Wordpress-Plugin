<?php
if (!defined('ABSPATH')) { exit; }

class HET_SEO_Rollback {
    const TABLE = 'het_seo_ai_rollbacks';

    private $logger;

    public function __construct(HET_SEO_Logger $logger) {
        $this->logger = $logger;
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            queue_id BIGINT UNSIGNED NOT NULL,
            post_id BIGINT UNSIGNED NULL,
            meta_key VARCHAR(191) NOT NULL,
            old_value LONGTEXT NULL,
            new_value LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            rolled_back_at DATETIME NULL,
            rolled_back_by BIGINT UNSIGNED NULL,
            PRIMARY KEY (id),
            KEY queue_id (queue_id),
            KEY post_id (post_id),
            KEY meta_key (meta_key)
        ) $charset;";
        dbDelta($sql);
    }

    public function record_meta_change($queue_id, $post_id, $meta_key, $old_value, $new_value) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $wpdb->insert($table, [
            'queue_id' => intval($queue_id),
            'post_id' => $post_id ? intval($post_id) : null,
            'meta_key' => sanitize_text_field($meta_key),
            'old_value' => maybe_serialize($old_value),
            'new_value' => maybe_serialize($new_value),
            'created_at' => current_time('mysql'),
        ], ['%d','%d','%s','%s','%s','%s']);
        $this->logger->log('Rollback snapshot recorded', ['queue_id' => $queue_id, 'post_id' => $post_id, 'meta_key' => $meta_key]);
    }

    public function rollback_queue($queue_id) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE queue_id = %d AND rolled_back_at IS NULL ORDER BY id DESC", intval($queue_id)));
        $user_id = get_current_user_id();
        $count = 0;
        foreach ($rows as $r) {
            if (!empty($r->post_id)) {
                update_post_meta(intval($r->post_id), $r->meta_key, maybe_unserialize($r->old_value));
                $wpdb->update($table, [
                    'rolled_back_at' => current_time('mysql'),
                    'rolled_back_by' => $user_id,
                ], ['id' => intval($r->id)], ['%s','%d'], ['%d']);
                $count++;
            }
        }
        $this->logger->log('Rollback applied', ['queue_id' => $queue_id, 'count' => $count]);
        return $count;
    }

    public function list_recent($limit = 100) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $limit = max(1, min(500, intval($limit)));
        return $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT $limit");
    }
}
