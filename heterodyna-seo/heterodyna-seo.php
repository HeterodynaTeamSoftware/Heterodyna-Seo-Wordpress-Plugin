<?php
/**
 * Plugin Name: Heterodyna SEO
 * Description: Heterodyna SEO — open-source SEO toolkit with integrated PL/EN/RU admin language switch, SEO Core, AI Auto and universal tools.
 * Version: 03.06.039-os-i18n-ru-safe
 * Author: Heterodyna Team Software
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: heterodyna-seo
 */

if (!defined('ABSPATH')) { exit; }

define('HET_SEO_VER','03.06.039-os-i18n-ru-safe');
define('HET_SEO_PATH', plugin_dir_path(__FILE__));
define('HET_SEO_URL', plugin_dir_url(__FILE__));
define('HET_SEO_OPT', 'het_seo_options');

/**
 * Open-source build: remote licensing and remote reporting are disabled.
 */
require_once HET_SEO_PATH . 'includes/class-het-seo-i18n.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-logger.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-license.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-rollback.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-ai-queue.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-ai-auto.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-ai-style.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-psi.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-sitekit.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-integrations-hub.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-rank-tracker.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-alerts.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-monitoring.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-redirects.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-insights.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-rest.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-data-core.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-content-intel.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-serp-intel.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-architecture.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-admin.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-social.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-social-hub.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-status.php';
require_once HET_SEO_PATH . 'includes/class-het-seo-metabox.php';

/**
 * Open-source build: all plugin features are available without a license key.
 */
function het_seo_is_pro_active(): bool {
    return (bool) apply_filters('het_seo_is_pro_active', true, '', get_option(HET_SEO_OPT, []));
}

/** Stały link sprzedażowy (podmiana bez grzebania w kodzie). */
function het_seo_purchase_url(): string {
    return (string) apply_filters('het_seo_purchase_url', '');
}

function het_seo_maybe_upgrade() {
    global $wpdb;
    $dbver = (string)get_option('het_seo_db_ver', '');

    // Overwrite-upgrade ZIPs do not trigger activation hooks. Ensure required tables exist.
    $need = false;
    $required_tables = [
        $wpdb->prefix . 'het_seo_url_snapshot',
        $wpdb->prefix . 'het_seo_site_snapshot',
        $wpdb->prefix . 'het_seo_history',
        $wpdb->prefix . 'het_seo_content_intel',
        $wpdb->prefix . 'het_seo_serp_keywords',
        $wpdb->prefix . 'het_seo_serp_results',
        $wpdb->prefix . 'het_seo_serp_gap',
        $wpdb->prefix . 'het_seo_integrations',
        $wpdb->prefix . 'het_seo_rank_keywords',
        $wpdb->prefix . 'het_seo_rank_positions',
        $wpdb->prefix . 'het_seo_domain_rank',
        $wpdb->prefix . 'het_seo_social_accounts',
        $wpdb->prefix . 'het_seo_social_posts',
        $wpdb->prefix . 'het_seo_social_campaigns',
        $wpdb->prefix . 'het_seo_social_queue',
        $wpdb->prefix . 'het_seo_social_logs',

    ];

    foreach ($required_tables as $t) {
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t));
        if (!$exists) { $need = true; break; }
    }

    if ($need || version_compare($dbver, HET_SEO_VER, '<')) {
        // Open source: install core and advanced modules.
        if (class_exists('HET_SEO_Data_Core')) { HET_SEO_Data_Core::install(); HET_SEO_Data_Core::ensure_cron(); }

        // Advanced modules.
        if (het_seo_is_pro_active()) {
            if (class_exists('HET_SEO_Content_Intel')) { HET_SEO_Content_Intel::install(); HET_SEO_Content_Intel::ensure_cron(); }
            if (class_exists('HET_SEO_SERP_Intel')) { HET_SEO_SERP_Intel::install(); }
            if (class_exists('HET_SEO_Integrations_Hub')) { HET_SEO_Integrations_Hub::install(); }
            if (class_exists('HET_SEO_Rank_Tracker')) { HET_SEO_Rank_Tracker::install(); }
            if (class_exists('HET_SEO_Social_Hub')) { HET_SEO_Social_Hub::install(); HET_SEO_Social_Hub::ensure_cron(); }
        }

        // keep version marker
        update_option('het_seo_db_ver', HET_SEO_VER);
    }
}
add_action('plugins_loaded', 'het_seo_maybe_upgrade', 1);


function het_seo_boot() {
    $logger   = new HET_SEO_Logger();
    $license  = new HET_SEO_License($logger);
    $rollback = new HET_SEO_Rollback($logger);

    // Open-source build: no remote commercial activation or reporting.
    $license->init_cron();

    $queue = null;
    $ai_auto = null;

    // Open source: AI modules are available without a license key.
    if (het_seo_is_pro_active()) {
        $queue    = new HET_SEO_AI_Queue($logger, $license, $rollback);
        $ai_auto  = new HET_SEO_AI_Auto($logger, $license, $queue);

        // Style Profile + Brand Rules (AI)
        new HET_SEO_AI_Style($logger);
    }

    $psi      = new HET_SEO_PSI($logger, $license);
    $alerts   = new HET_SEO_Alerts($logger, $license);
    $monitor  = new HET_SEO_Monitoring($logger, $alerts);
    $redir    = new HET_SEO_Redirects($logger);

    // Advanced insights.
    $insights = het_seo_is_pro_active() ? new HET_SEO_Insights($logger, $license) : null;

    // DATA CORE (v03.01): snapshots + history (best-effort)
    $datacore = new HET_SEO_Data_Core($logger);

    // CONTENT INTELLIGENCE (v03.02): intent + content gaps + richer per-URL analysis
    $content_intel = het_seo_is_pro_active() ? new HET_SEO_Content_Intel($logger) : null;

    // SERP INTELLIGENCE (v03.03)
    $serp = het_seo_is_pro_active() ? new HET_SEO_SERP_Intel($logger) : null;

    // ARCHITECTURE (v03.04)
    $arch = het_seo_is_pro_active() ? new HET_SEO_Architecture($logger) : null;

    // INTEGRATIONS + RANK TRACKER (v03.05.100)
    $integrations = het_seo_is_pro_active() ? new HET_SEO_Integrations_Hub($logger) : null;
    $rank = het_seo_is_pro_active() ? new HET_SEO_Rank_Tracker($logger) : null;

    // REST endpoints.
    if (het_seo_is_pro_active()) {
        new HET_SEO_REST($logger, $license, $queue, $rollback, $insights);
    }

    new HET_SEO_Admin($logger, $license, $queue, $rollback, $psi, $alerts, $redir, $ai_auto, $datacore, $content_intel, $serp, $arch, $monitor, $integrations, $rank);
    if (het_seo_is_pro_active()) {
        new HET_SEO_Social();
    }
    new HET_SEO_Metabox($logger);
}
add_action('plugins_loaded', 'het_seo_boot');

// Overwrite-upgrade safe: ensure DB schema + cron even without re-activation.
add_action('plugins_loaded', function() {
    if (!is_admin() && !wp_doing_cron()) { return; }
    $installed = (string)get_option('het_seo_db_ver', '');
    if ($installed === HET_SEO_VER) { return; }

    // Best-effort schema/cron ensures (idempotent)
    try { HET_SEO_Rollback::install(); } catch (Throwable $e) {}
    try { HET_SEO_Admin::install(); } catch (Throwable $e) {}

    try { HET_SEO_PSI::install(); } catch (Throwable $e) {}
    try { HET_SEO_Alerts::install();
    HET_SEO_Monitoring::install(); } catch (Throwable $e) {}
    try { HET_SEO_Monitoring::install(); } catch (Throwable $e) {}
    try { HET_SEO_Redirects::install(); } catch (Throwable $e) {}
    // Advanced modules.
    if (het_seo_is_pro_active()) {
        try { HET_SEO_AI_Queue::install(); } catch (Throwable $e) {}
        try { HET_SEO_AI_Auto::install(); } catch (Throwable $e) {}
        try { HET_SEO_Insights::install(); } catch (Throwable $e) {}
        try { HET_SEO_Content_Intel::install(); } catch (Throwable $e) {}
        try { HET_SEO_SERP_Intel::install(); } catch (Throwable $e) {}
    }

    try { HET_SEO_Data_Core::install(); } catch (Throwable $e) {}

    try { HET_SEO_PSI::ensure_cron(); } catch (Throwable $e) {}
    try { HET_SEO_Alerts::ensure_cron();
    HET_SEO_Monitoring::ensure_cron(); } catch (Throwable $e) {}
    try { HET_SEO_Monitoring::ensure_cron(); } catch (Throwable $e) {}
    if (het_seo_is_pro_active()) {
        try { HET_SEO_AI_Auto::ensure_cron(); } catch (Throwable $e) {}
        try { HET_SEO_Content_Intel::ensure_cron(); } catch (Throwable $e) {}
    }
    try { HET_SEO_Data_Core::ensure_cron(); } catch (Throwable $e) {}

    update_option('het_seo_db_ver', HET_SEO_VER, true);
}, 20);

register_activation_hook(__FILE__, function() {
    HET_SEO_Rollback::install();
    HET_SEO_Admin::install();

    HET_SEO_PSI::install();
    HET_SEO_Alerts::install();
    HET_SEO_Monitoring::install();
    HET_SEO_Redirects::install();

    HET_SEO_Data_Core::install();

    HET_SEO_PSI::ensure_cron();
    HET_SEO_Alerts::ensure_cron();
    HET_SEO_Monitoring::ensure_cron();

    HET_SEO_Data_Core::ensure_cron();
    // Open-source build: no commercial activation or remote reporting.
});

register_deactivation_hook(__FILE__, function() {
    // leave data; cron removed
    wp_clear_scheduled_hook('het_seo_cron_process_queue');
    wp_clear_scheduled_hook('het_seo_cron_psi_daily');
    wp_clear_scheduled_hook('het_seo_cron_psi_process');
    wp_clear_scheduled_hook('het_seo_cron_alerts_daily');
    wp_clear_scheduled_hook('het_seo_cron_ai_auto_daily');
    wp_clear_scheduled_hook('het_seo_cron_ai_auto_weekly_report');

    wp_clear_scheduled_hook('het_seo_cron_datacore_daily');
    wp_clear_scheduled_hook('het_seo_cron_datacore_batch');

    wp_clear_scheduled_hook('het_seo_cron_content_intel_daily');
    wp_clear_scheduled_hook('het_seo_cron_content_intel_batch');

    // Backward compatibility: clear old commercial scheduled hook if it exists.
    wp_clear_scheduled_hook(HET_SEO_License::CRON_HOOK);
});