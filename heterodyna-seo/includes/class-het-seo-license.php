<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Open-source compatibility module.
 *
 * The previous commercial build used remote commercial activation and remote status reporting.
 * This open-source build does not call any commercial activation or reporting endpoint. The class is kept only as a stable API for the rest of the plugin.
 */
class HET_SEO_License {
    private $logger;

    // Kept for backward compatibility with older stored options and hooks.
    const OPT_TOKEN = 'license_token';
    const OPT_TOKEN_HASH = 'license_token_hash';
    const OPT_ACTIVATED_AT = 'license_activated_at';
    const OPT_EXPIRES_AT = 'license_expires_at';
    const OPT_PLAN_CODE = 'license_plan_code';
    const OPT_DURATION_MONTHS = 'license_duration_months';
    const OPT_TIER = 'license_tier';
    const OPT_LAST_SYNC_AT = 'license_last_sync_at';
    const OPT_LAST_ERROR = 'license_last_error';
    const CRON_HOOK = 'het_seo_os_disabled_compat_cron';

    public function __construct(HET_SEO_Logger $logger) {
        $this->logger = $logger;
    }

    public function get_server_base(): string {
        return '';
    }

    public function get_masked_key(string $key): string {
        $key = trim($key);
        if ($key === '') return '';
        if (strlen($key) <= 12) return $key;
        return substr($key, 0, 6) . '…' . substr($key, -4);
    }

    public function get_masked_token(string $token): string {
        return '';
    }

    public static function cron_schedules($schedules) {
        return $schedules;
    }

    public function init_cron(): void {
        // Open-source build: no remote commercial activation or reporting.
    }

    public function get_state() {
        $state = [
            'key' => '',
            'active' => true,
            'message' => 'Open-source build — wszystkie funkcje są dostępne bez klucza.',
            'server_base' => '',
            'token' => '',
            'token_masked' => '',
            'activated_at' => '',
            'expires_at' => '',
            'plan_code' => 'OPEN-SOURCE',
            'tier' => 'open-source',
            'duration_months' => 0,
            'last_sync_at' => 0,
            'activations_used' => 0,
            'activations_limit' => 0,
        ];

        $state = apply_filters('het_seo_license_state', $state);
        $state['active'] = true;
        $state['message'] = isset($state['message']) ? wp_strip_all_tags((string)$state['message']) : 'Open-source build.';
        return $state;
    }

    /** @return array|WP_Error */
    public function activate_remote(string $unused_key = '') {
        return [
            'ok' => true,
            'message' => 'Open-source build: aktywacja zdalna nie jest wymagana.',
        ];
    }

    /** @return array|WP_Error */
    public function sync_me() {
        return [
            'ok' => true,
            'message' => 'Open-source build: zdalna synchronizacja komercyjna jest wyłączona.',
        ];
    }

    public function clear_activation(): void {
        $opts = get_option(HET_SEO_OPT, []);
        unset(
            $opts[self::OPT_TOKEN],
            $opts[self::OPT_TOKEN_HASH],
            $opts[self::OPT_ACTIVATED_AT],
            $opts[self::OPT_EXPIRES_AT],
            $opts[self::OPT_PLAN_CODE],
            $opts[self::OPT_DURATION_MONTHS],
            $opts[self::OPT_TIER],
            $opts[self::OPT_LAST_SYNC_AT],
            $opts[self::OPT_LAST_ERROR]
        );
        update_option(HET_SEO_OPT, $opts, false);
    }


    public function is_active() {
        return true;
    }

    public function can_run_write_actions() {
        return true;
    }

    public function get_today_usage() {
        return ['calls' => 0, 'batches' => 0];
    }
}
