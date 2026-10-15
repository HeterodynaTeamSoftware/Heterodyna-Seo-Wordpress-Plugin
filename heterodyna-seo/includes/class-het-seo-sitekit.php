<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Best-effort KPI fetch from Google Site Kit, without hard dependencies.
 * We call Site Kit REST routes internally via rest_do_request (no HTTP, no nonce).
 */
class HET_SEO_SiteKit {

    public static function is_active(): bool {
        // Fast checks (no fatal if Site Kit not installed)
        if (defined('GOOGLESITEKIT_VERSION')) { return true; }
        if (class_exists('Google\\Site_Kit\\Core\\Plugin')) { return true; }
        return false;
    }

    /**
     * Fetch KPI bundle. Never throws.
     * @return array{ok:bool, message:string, gsc:array, ga4:array, psi:array}
     */
    public static function get_kpis(int $days = 28): array {
        $out = [
            'ok' => false,
            'message' => 'Site Kit nieaktywne',
            'gsc' => [ 'clicks' => null, 'impressions' => null, 'ctr' => null, 'position' => null ],
            'ga4' => [ 'sessions' => null, 'users' => null ],
            'psi' => [ 'mobile' => null, 'desktop' => null ],
        ];

        if (!self::is_active()) {
            return $out;
        }

        if (!function_exists('rest_do_request') || !class_exists('WP_REST_Request')) {
            $out['message'] = 'REST niedostępny';
            return $out;
        }

        $end = gmdate('Y-m-d', strtotime('-1 day'));
        $start = gmdate('Y-m-d', strtotime('-' . max(1, (int)$days) . ' days'));

        // Search Console (clicks / impressions / ctr / position)
        $gsc = self::rest_post('/google-site-kit/v1/modules/search-console/data/searchanalytics', [
            'startDate' => $start,
            'endDate' => $end,
            // Request totals (no dimensions) – Site Kit returns rows; we'll aggregate.
            'dimensions' => [],
            'rowLimit' => 500,
        ]);
        if (is_array($gsc)) {
            $agg = [ 'clicks' => 0.0, 'impressions' => 0.0, 'ctr' => 0.0, 'position' => 0.0, 'rows' => 0 ];
            if (!empty($gsc['rows']) && is_array($gsc['rows'])) {
                foreach ($gsc['rows'] as $row) {
                    if (!isset($row['metrics']) || !is_array($row['metrics'])) { continue; }
                    $m = $row['metrics'];
                    $agg['clicks'] += isset($m[0]) ? (float)$m[0] : 0.0;
                    $agg['impressions'] += isset($m[1]) ? (float)$m[1] : 0.0;
                    $agg['ctr'] += isset($m[2]) ? (float)$m[2] : 0.0;
                    $agg['position'] += isset($m[3]) ? (float)$m[3] : 0.0;
                    $agg['rows']++;
                }
                $out['gsc']['clicks'] = (int) round($agg['clicks']);
                $out['gsc']['impressions'] = (int) round($agg['impressions']);
                // ctr and position: average if we have rows.
                if ($agg['rows'] > 0) {
                    $out['gsc']['ctr'] = round(($agg['ctr'] / $agg['rows']) * 100, 2); // %
                    $out['gsc']['position'] = round($agg['position'] / $agg['rows'], 2);
                }
            }
        }

        // Analytics 4 (sessions / users) – best-effort
        $ga4 = self::rest_post('/google-site-kit/v1/modules/analytics-4/data/report', [
            'startDate' => $start,
            'endDate' => $end,
            'dimensions' => [],
            'metrics' => [ 'sessions', 'totalUsers' ],
        ]);
        if (is_array($ga4)) {
            // Try multiple response shapes.
            if (isset($ga4['totals'][0]['metricValues']) && is_array($ga4['totals'][0]['metricValues'])) {
                $mv = $ga4['totals'][0]['metricValues'];
                $out['ga4']['sessions'] = isset($mv[0]['value']) ? (int)$mv[0]['value'] : null;
                $out['ga4']['users'] = isset($mv[1]['value']) ? (int)$mv[1]['value'] : null;
            } elseif (isset($ga4['rows'][0]['metricValues']) && is_array($ga4['rows'][0]['metricValues'])) {
                $mv = $ga4['rows'][0]['metricValues'];
                $out['ga4']['sessions'] = isset($mv[0]['value']) ? (int)$mv[0]['value'] : null;
                $out['ga4']['users'] = isset($mv[1]['value']) ? (int)$mv[1]['value'] : null;
            }
        }

        // PageSpeed Insights – score only (mobile + desktop)
        $url = home_url('/');
        $psi_m = self::rest_post('/google-site-kit/v1/modules/pagespeed-insights/data/pagespeed', [
            'url' => $url,
            'strategy' => 'mobile',
        ]);
        $psi_d = self::rest_post('/google-site-kit/v1/modules/pagespeed-insights/data/pagespeed', [
            'url' => $url,
            'strategy' => 'desktop',
        ]);
        $out['psi']['mobile'] = self::extract_psi_score($psi_m);
        $out['psi']['desktop'] = self::extract_psi_score($psi_d);

        $out['ok'] = true;
        $out['message'] = 'OK';
        return $out;
    }

    private static function rest_post(string $route, array $params): ?array {
        try {
            $req = new WP_REST_Request('POST', $route);
            foreach ($params as $k => $v) {
                $req->set_param($k, $v);
            }
            $res = rest_do_request($req);
            if (is_wp_error($res)) { return null; }
            $status = (int) $res->get_status();
            if ($status < 200 || $status >= 300) { return null; }
            $data = $res->get_data();
            return is_array($data) ? $data : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function extract_psi_score($psi): ?int {
        if (!is_array($psi)) { return null; }
        // Try Site Kit PSI response shapes.
        if (isset($psi['lighthouseResult']['categories']['performance']['score'])) {
            return (int) round(((float)$psi['lighthouseResult']['categories']['performance']['score']) * 100);
        }
        if (isset($psi['loadingExperience']['metrics'])) {
            // Fallback: no single score; leave null.
            return null;
        }
        return null;
    }
}
