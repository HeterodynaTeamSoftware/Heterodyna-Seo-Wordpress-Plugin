<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Integrations Hub (v03.05+)
 *
 * Stores connector settings for webmaster tools and search engines.
 * Best-effort: we do NOT force OAuth flows here; we support:
 * - Site Kit bridge (read-only)
 * - API credentials storage (advanced)
 * - Manual import (safe)
 */
class HET_SEO_Integrations_Hub {
    const TABLE = 'het_seo_integrations';

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
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            provider VARCHAR(64) NOT NULL,
            settings LONGTEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY provider (provider)
        ) $charset;";
        dbDelta($sql);
    }

    public function get_provider_settings($provider) {
        global $wpdb;
        $provider = sanitize_key($provider);
        $table = $wpdb->prefix . self::TABLE;
        $row = $wpdb->get_row($wpdb->prepare("SELECT settings, updated_at FROM $table WHERE provider=%s LIMIT 1", $provider), ARRAY_A);
        if (!$row) { return ['settings'=>[], 'updated_at'=>null]; }
        $settings = json_decode((string)$row['settings'], true);
        if (!is_array($settings)) { $settings = []; }
        return ['settings'=>$settings, 'updated_at'=>$row['updated_at']];
    }

    public function set_provider_settings($provider, array $settings) {
        global $wpdb;
        $provider = sanitize_key($provider);
        $table = $wpdb->prefix . self::TABLE;
        $data = [
            'provider' => $provider,
            'settings' => wp_json_encode($settings),
            'updated_at' => current_time('mysql'),
        ];
        $formats = ['%s','%s','%s'];
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE provider=%s", $provider));
        if ($exists) {
            $wpdb->update($table, ['settings'=>$data['settings'], 'updated_at'=>$data['updated_at']], ['provider'=>$provider], ['%s','%s'], ['%s']);
        } else {
            $wpdb->insert($table, $data, $formats);
        }
        return true;
    }

    public function get_providers_overview() {
        $providers = [
            ['key'=>'gsc', 'label'=>'Google Search Console', 'hint'=>'Najważniejsze: zapytania, CTR, pozycja, indeksowanie.'],
            ['key'=>'bing', 'label'=>'Bing Webmaster Tools', 'hint'=>'Bing/Chat: zapytania, crawl, indeks.'],
            ['key'=>'yandex', 'label'=>'Yandex Webmaster', 'hint'=>'Rynek wschodni: indeks, zapytania.'],
            ['key'=>'seznam', 'label'=>'Seznam Webmaster', 'hint'=>'Czechy: widoczność i indeks.'],
            ['key'=>'baidu', 'label'=>'Baidu Webmaster', 'hint'=>'Chiny: indeks i zapytania.'],
            ['key'=>'naver', 'label'=>'Naver Search Advisor', 'hint'=>'Korea: indeks i ruch.'],
            ['key'=>'sitekit', 'label'=>'Google Site Kit', 'hint'=>'Mostek do GSC/GA4 (jeśli włączone).'],
        ];

        $out = [];
        foreach ($providers as $p) {
            $row = $this->get_provider_settings($p['key']);
            $settings = $row['settings'];
            $connected = !empty($settings['connected']);
            $out[] = [
                'key' => $p['key'],
                'label' => $p['label'],
                'hint' => $p['hint'],
                'connected' => $connected,
                'updated_at' => $row['updated_at'],
            ];
        }
        return $out;
    }
}
