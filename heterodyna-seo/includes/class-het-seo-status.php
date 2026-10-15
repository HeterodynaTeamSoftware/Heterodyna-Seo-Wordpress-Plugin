<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Client-friendly site status checks (best-effort, read-only).
 */
class HET_SEO_Status {

    /**
     * Get a status report. Uses a short transient to avoid hammering remote requests.
     */
    public static function get_report(): array {
        $cache_key = 'het_seo_status_report_v1';
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $home = home_url('/');
        $report = [
            'generated_at' => current_time('mysql'),
            'site' => [
                'home' => $home,
                'https' => (stripos($home, 'https://') === 0),
                'wp' => get_bloginfo('version'),
                'php' => PHP_VERSION,
            ],
            'public' => [
                'robots' => self::probe_url(home_url('/robots.txt')),
                'sitemap' => self::probe_url(home_url('/sitemap.xml')),
                'security' => self::probe_url(home_url('/.well-known/security.txt')),
                'rest_root' => self::probe_url(rest_url('/')),
            ],
        ];

        // Cron hints (best-effort)
        $report['cron'] = [
            'wp_cron_disabled' => (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON),
            'next_ai_auto' => self::next_event('het_seo_cron_ai_auto_daily'),
            'next_psi_daily' => self::next_event('het_seo_cron_psi_daily'),
            'next_psi_queue' => self::next_event('het_seo_cron_psi_process'),
            'next_alerts' => self::next_event('het_seo_cron_alerts_daily'),
        ];

        set_transient($cache_key, $report, 60);
        return $report;
    }

    private static function next_event(string $hook): ?string {
        $ts = wp_next_scheduled($hook);
        if (!$ts) return null;
        return date_i18n('Y-m-d H:i:s', $ts);
    }

    /**
     * Probe a URL with a light request.
     */
    private static function probe_url(string $url): array {
        $res = [
            'url' => $url,
            'ok' => false,
            'code' => null,
            'note' => '',
        ];

        $args = [
            'timeout' => 6,
            'redirection' => 2,
            'user-agent' => 'HeterodynaSEO/' . (defined('HET_SEO_VER') ? HET_SEO_VER : 'dev') . ' (+status probe)',
        ];

        $r = wp_remote_head($url, $args);
        if (is_wp_error($r)) {
            // Some servers block HEAD; try GET as fallback.
            $r2 = wp_remote_get($url, $args);
            if (is_wp_error($r2)) {
                $res['note'] = $r2->get_error_message();
                return $res;
            }
            $code = wp_remote_retrieve_response_code($r2);
            $res['code'] = $code;
            $res['ok'] = ($code >= 200 && $code < 400);
            return $res;
        }

        $code = wp_remote_retrieve_response_code($r);
        $res['code'] = $code;
        $res['ok'] = ($code >= 200 && $code < 400);
        return $res;
    }
}
