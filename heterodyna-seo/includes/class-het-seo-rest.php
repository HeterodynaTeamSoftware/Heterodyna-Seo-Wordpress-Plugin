<?php
if (!defined('ABSPATH')) { exit; }

class HET_SEO_REST {
    private $logger;
    private $license;
    private $queue;
    private $rollback;
    private $insights;

    public function __construct(HET_SEO_Logger $logger, HET_SEO_License $license, HET_SEO_AI_Queue $queue, HET_SEO_Rollback $rollback, HET_SEO_Insights $insights) {
        $this->logger = $logger;
        $this->license = $license;
        $this->queue = $queue;
        $this->rollback = $rollback;
        $this->insights = $insights;

        add_action('rest_api_init', [$this, 'register']);
    }

    public function register() {
        register_rest_route('heterodyna-seo/v1', '/ai/scan', [
            'methods' => 'POST',
            'permission_callback' => [$this, 'can_manage_pro'],
            'callback' => [$this, 'ai_scan'],
        ]);

        register_rest_route('heterodyna-seo/v1', '/ai/queue', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_manage_pro'],
            'callback' => [$this, 'ai_queue'],
        ]);

        register_rest_route('heterodyna-seo/v1', '/ai/run', [
            'methods' => 'POST',
            'permission_callback' => [$this, 'can_manage_pro'],
            'callback' => [$this, 'ai_run'],
        ]);

        register_rest_route('heterodyna-seo/v1', '/ai/rollback/(?P<queue_id>\d+)', [
            'methods' => 'POST',
            'permission_callback' => [$this, 'can_manage_pro'],
            'callback' => [$this, 'ai_rollback'],
            'args' => ['queue_id' => ['validate_callback' => 'is_numeric']]
        ]);

        register_rest_route('heterodyna-seo/v1', '/settings/export', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_manage'],
            'callback' => [$this, 'settings_export'],
        ]);

        register_rest_route('heterodyna-seo/v1', '/settings/import', [
            'methods' => 'POST',
            'permission_callback' => [$this, 'can_manage'],
            'callback' => [$this, 'settings_import'],
        ]);

        // MEGA PACK C: insights (topics/conflicts/suggestions)
        register_rest_route('heterodyna-seo/v1', '/insights/scan', [
            'methods' => 'POST',
            'permission_callback' => [$this, 'can_manage_pro'],
            'callback' => [$this, 'insights_scan'],
        ]);

        register_rest_route('heterodyna-seo/v1', '/insights/conflicts', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_manage_pro'],
            'callback' => [$this, 'insights_conflicts'],
        ]);

        register_rest_route('heterodyna-seo/v1', '/insights/suggestions', [
            'methods' => 'GET',
            'permission_callback' => [$this, 'can_manage_pro'],
            'callback' => [$this, 'insights_suggestions'],
        ]);

        register_rest_route('heterodyna-seo/v1', '/insights/apply/(?P<id>\d+)', [
            'methods' => 'POST',
            'permission_callback' => [$this, 'can_manage_pro'],
            'callback' => [$this, 'insights_apply'],
            'args' => ['id' => ['validate_callback' => 'is_numeric']]
        ]);

        register_rest_route('heterodyna-seo/v1', '/insights/apply-bulk', [
            'methods' => 'POST',
            'permission_callback' => [$this, 'can_manage_pro'],
            'callback' => [$this, 'insights_apply_bulk'],
        ]);


        register_rest_route('heterodyna-seo/v1', '/insights/ignore/(?P<id>\d+)', [
            'methods' => 'POST',
            'permission_callback' => [$this, 'can_manage_pro'],
            'callback' => [$this, 'insights_ignore'],
            'args' => ['id' => ['validate_callback' => 'is_numeric']]
        ]);

        
        register_rest_route('heterodyna-seo/v1', '/insights/merge-mark', [
            'methods' => 'POST',
            'permission_callback' => [$this, 'can_manage_pro'],
            'callback' => [$this, 'insights_merge_mark'],
        ]);

        register_rest_route('heterodyna-seo/v1', '/insights/exclusions', [
            'methods' => ['GET','POST'],
            'permission_callback' => [$this, 'can_manage_pro'],
            'callback' => [$this, 'insights_exclusions'],
        ]);

    }

    public function can_manage(WP_REST_Request $req) {
        return current_user_can('manage_options');
    }

    public function can_manage_pro(WP_REST_Request $req) {
        if (!current_user_can('manage_options')) { return false; }
        return true;
    }

    public function ai_scan(WP_REST_Request $req) {
        $dry_run = (bool)$req->get_param('dry_run');
        $limit = intval($req->get_param('limit') ?: 200);
        $include_ctr = (bool)$req->get_param('include_ctr');
        $res = $this->queue->scan_and_enqueue([
            'limit' => $limit,
            'dry_run' => $dry_run,
            'include_ctr' => $include_ctr,
        ]);
        if (is_wp_error($res)) {
            return new WP_REST_Response(['ok' => false, 'error' => $res->get_error_message()], 400);
        }
        return new WP_REST_Response(['ok' => true, 'data' => $res], 200);
    }

    public function ai_queue(WP_REST_Request $req) {
        $status = $req->get_param('status');
        $items = $this->queue->list_queue($status ? sanitize_key($status) : null, 200);
        return new WP_REST_Response([
            'ok' => true,
            'license' => $this->license->get_state(),
            'items' => $items,
        ], 200);
    }

    public function ai_run(WP_REST_Request $req) {
        $max = intval($req->get_param('max') ?: 10);
        $done = $this->queue->process_next($max);
        return new WP_REST_Response(['ok' => true, 'processed' => $done], 200);
    }

    public function ai_rollback(WP_REST_Request $req) {
        $queue_id = intval($req['queue_id']);
        $count = $this->rollback->rollback_queue($queue_id);
        return new WP_REST_Response(['ok' => true, 'rolled_back' => $count], 200);
    }

    public function settings_export() {
        $opts = get_option(HET_SEO_OPT, []);
        $safe = $opts;
        // never export secrets if you later add them
        unset($safe['license_key'], $safe['license_token'], $safe['license_token_hash'], $safe['license_activated_at'], $safe['license_expires_at'], $safe['license_plan_code'], $safe['license_duration_months'], $safe['license_tier'], $safe['license_last_sync_at'], $safe['license_last_error']);
        return new WP_REST_Response(['ok' => true, 'options' => $safe, 'version' => HET_SEO_VER], 200);
    }

    public function settings_import(WP_REST_Request $req) {
        $payload = $req->get_param('options');
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
            }
        }
        if (!is_array($payload)) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Niepoprawny payload options.'], 400);
        }
        $opts = get_option(HET_SEO_OPT, []);
        $merged = array_merge($opts, $payload);
        update_option(HET_SEO_OPT, $merged, false);
        return new WP_REST_Response(['ok' => true], 200);
    }

    public function insights_scan(WP_REST_Request $req) {
        $limit = intval($req->get_param('limit') ?: 500);
        $include_terms = (bool) $req->get_param('include_terms');
        // default true if param is missing
        if ($req->get_param('include_terms') === null) { $include_terms = true; }
        $term_limit = intval($req->get_param('term_limit') ?: 300);
        $tax = $req->get_param('taxonomies');
        if (is_string($tax)) {
            $tax = array_filter(array_map('trim', explode(',', $tax)));
        }
        if (!is_array($tax)) { $tax = []; }
        $res = $this->insights->scan_and_build($limit, $include_terms, $term_limit, $tax);
        return new WP_REST_Response(['ok' => true, 'data' => $res], 200);
    }

    public function insights_conflicts(WP_REST_Request $req) {
        $kind = $req->get_param('kind') ?: 'cannibal';
        $limit = intval($req->get_param('limit') ?: 50);
        $rows = $this->insights->list_conflicts($kind, $limit);
        return new WP_REST_Response(['ok' => true, 'items' => $rows], 200);
    }

    public function insights_suggestions(WP_REST_Request $req) {
        $status = $req->get_param('status');
        $limit = intval($req->get_param('limit') ?: 200);
        $rows = $this->insights->list_suggestions($status ? sanitize_key($status) : null, $limit);
        return new WP_REST_Response(['ok' => true, 'items' => $rows], 200);
    }

    public function insights_apply(WP_REST_Request $req) {
        $id = intval($req['id']);
        $res = $this->insights->apply_suggestion($id);
        if (is_wp_error($res)) {
            return new WP_REST_Response(['ok' => false, 'error' => $res->get_error_message()], 400);
        }
        return new WP_REST_Response(['ok' => true, 'data' => $res], 200);
    }

    public function insights_apply_bulk(WP_REST_Request $req) {
        $min = intval($req->get_param('min_confidence') ?: 88);
        $limit = intval($req->get_param('limit') ?: 50);
        $status = sanitize_key($req->get_param('status') ?: 'approval');
        $res = $this->insights->apply_bulk($min, $limit, $status);
        if (is_wp_error($res)) {
            return new WP_REST_Response(['ok' => false, 'error' => $res->get_error_message()], 400);
        }
        return new WP_REST_Response(['ok' => true, 'data' => $res], 200);
    }


    public function insights_ignore(WP_REST_Request $req) {
        $id = intval($req['id']);
        $res = $this->insights->ignore_suggestion($id);
        if (is_wp_error($res)) {
            return new WP_REST_Response(['ok' => false, 'error' => $res->get_error_message()], 400);
        }
        return new WP_REST_Response(['ok' => true, 'data' => $res], 200);
    }


    public function insights_merge_mark(WP_REST_Request $req) {
        $winner = intval($req->get_param('winner_id'));
        $loser = intval($req->get_param('loser_id'));
        $topic = sanitize_text_field($req->get_param('topic_hash') ?: '');
        if (!$winner || !$loser || !$topic) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Brak danych (winner_id/loser_id/topic_hash)'], 400);
        }
        $res = $this->insights->mark_merge_candidate($topic, $winner, $loser);
        if (is_wp_error($res)) {
            return new WP_REST_Response(['ok' => false, 'error' => $res->get_error_message()], 400);
        }
        return new WP_REST_Response(['ok' => true, 'data' => $res], 200);
    }

    public function insights_exclusions(WP_REST_Request $req) {
        if ($req->get_method() === 'POST') {
            $action = sanitize_key($req->get_param('action') ?: '');
            $post_id = intval($req->get_param('post_id') ?: 0);
            if ($action === 'add') {
                $res = $this->insights->add_exclusion($post_id);
            } elseif ($action === 'remove') {
                $res = $this->insights->remove_exclusion($post_id);
            } else {
                return new WP_REST_Response(['ok' => false, 'error' => 'Nieznana akcja'], 400);
            }
            if (is_wp_error($res)) {
                return new WP_REST_Response(['ok' => false, 'error' => $res->get_error_message()], 400);
            }
            return new WP_REST_Response(['ok' => true, 'data' => $res], 200);
        }
        $res = $this->insights->get_exclusions();
        return new WP_REST_Response(['ok' => true, 'items' => $res], 200);
    }
}
