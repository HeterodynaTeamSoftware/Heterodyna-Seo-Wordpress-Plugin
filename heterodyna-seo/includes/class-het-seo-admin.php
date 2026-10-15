<?php
if (!defined('ABSPATH')) { exit; }

class HET_SEO_Admin {
    const MENU_SLUG_CENTER = 'het_seo_center';
    const MENU_SLUG_SETTINGS = 'het_seo_settings';
    const MENU_SLUG_LICENSE = 'het_seo_license'; // kept as slug for backward-compatible links; page is now Open Source

    // Socialmedia (grouped hub inside main Heterodyna SEO menu)
    const MENU_SLUG_SOCIALMEDIA = 'het_seo_socialmedia';
    // DATA CORE (v03.01)
    const MENU_SLUG_DATA_SITE = 'het_seo_data_site';
    const MENU_SLUG_DATA_URL  = 'het_seo_data_url';
    const MENU_SLUG_HISTORY   = 'het_seo_history';

    // CONTENT INTELLIGENCE (v03.02)
    const MENU_SLUG_CONTENT_INTEL = 'het_seo_content_intel';
    const MENU_SLUG_INTENT        = 'het_seo_intent';
    const MENU_SLUG_CONTENT_GAPS  = 'het_seo_content_gaps';

    // SERP INTELLIGENCE (v03.03)
    const MENU_SLUG_SERP_INTEL   = 'het_seo_serp_intel';

    // ARCHITECTURE (v03.04) — WOW tabs
    const MENU_SLUG_ARCHITECTURE = 'het_seo_architecture';
    const MENU_SLUG_MONITORING = 'het_seo_monitoring';
    const MENU_SLUG_404 = 'het_seo_404';
    const MENU_SLUG_IMPORT_EXPORT = 'het_seo_import_export';
    const MENU_SLUG_ROBOTS = 'het_seo_robots';
    const MENU_SLUG_TESTS = 'het_seo_tests';
    const MENU_SLUG_API = 'het_seo_api';
    const MENU_SLUG_PSI = 'het_seo_psi';
    const MENU_SLUG_ALERTS = 'het_seo_alerts';
    const MENU_SLUG_REDIRECTS = 'het_seo_redirects';
    const MENU_SLUG_DUPLICATES = 'het_seo_duplicates';
    const MENU_SLUG_INSIGHTS = 'het_seo_insights';
    const MENU_SLUG_DOCS = 'het_seo_docs';

    // New pages (MEGA PACK I)
    const MENU_SLUG_INTEGRATIONS = 'het_seo_integrations';
    const MENU_SLUG_SECURITY = 'het_seo_security_txt';
    const MENU_SLUG_SYSFILES = 'het_seo_sysfiles';
    const MENU_SLUG_SITE_MODE = 'het_seo_site_mode';

    // SEO Core (restore missing functions)
    const MENU_SLUG_META_AUDIT = 'het_seo_meta_audit';
    const MENU_SLUG_CANONICAL = 'het_seo_canonical';
    const MENU_SLUG_SITEMAP = 'het_seo_sitemap';

    // AI Auto settings & reports
    const MENU_SLUG_AI_AUTO = 'het_seo_ai_auto';
    const MENU_SLUG_AI_CONTENT = 'het_seo_ai_content';

    // Client: Content & Social
    const MENU_SLUG_CONTENT_AUDIT = 'het_seo_content_audit';
    const MENU_SLUG_SOCIAL = 'het_seo_social';
    // Social Media Hub subpages (MEGA PACK I)
    const MENU_SLUG_SOCIAL_DASH      = 'het_seo_social_dash';
    const MENU_SLUG_SOCIAL_ACCOUNTS  = 'het_seo_social_accounts';
    const MENU_SLUG_SOCIAL_COMPOSER  = 'het_seo_social_composer';
    const MENU_SLUG_SOCIAL_CAMPAIGNS = 'het_seo_social_campaigns';
    const MENU_SLUG_SOCIAL_QUEUE     = 'het_seo_social_queue';
    const MENU_SLUG_SOCIAL_LOGS      = 'het_seo_social_logs';
    const MENU_SLUG_STATUS = 'het_seo_status';

    private $logger;
    private $license;
    private $queue;
    private $rollback;
    private $psi;
    private $alerts;
    private $redir;
    private $ai_auto;
    private $datacore;
    private $content_intel;
    private $serp;
    private $arch;
    private $monitor;
    private $integrations;
    private $rank;

    /**
     * Czy pełny tryb jest aktywny?
     */
    private function is_pro(): bool {
        if (function_exists('het_seo_is_pro_active')) {
            return (bool) het_seo_is_pro_active();
        }
        $state = $this->license ? $this->license->get_state() : ['active' => false];
        return !empty($state['active']);
    }

    /**
     * Open-source build: no PRO lock is enforced.
     */
    private function render_pro_lock(string $nazwa_funkcji = 'Ta funkcja'): void {
        echo '<div class="wrap"><h1>' . esc_html(het_seo_t($nazwa_funkcji)) . '</h1><p>' . esc_html(het_seo_t('Ta funkcja jest dostępna w wersji open-source bez klucza.')) . '</p></div>';
    }

    public function __construct(
        HET_SEO_Logger $logger,
        HET_SEO_License $license,
        $queue,
        HET_SEO_Rollback $rollback,
        HET_SEO_PSI $psi,
        HET_SEO_Alerts $alerts,
        HET_SEO_Redirects $redir,
        $ai_auto,
        HET_SEO_Data_Core $datacore,
        $content_intel,
        $serp,
        $arch,
        HET_SEO_Monitoring $monitor,
        $integrations,
        $rank
    ) {
        $this->logger = $logger;
        $this->license = $license;
        $this->queue = $queue;
        $this->rollback = $rollback;
        $this->psi = $psi;
        $this->alerts = $alerts;
        $this->redir = $redir;
        $this->ai_auto = $ai_auto;
        $this->datacore = $datacore;
        $this->content_intel = $content_intel;
        $this->serp = $serp;
        $this->arch = $arch;
        $this->monitor = $monitor;
        $this->integrations = $integrations;
        $this->rank = $rank;

        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', ['HET_SEO_I18N', 'start_admin_buffer']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);

        // CSV exports
        add_action('admin_post_het_seo_export_csv', [$this, 'handle_export_csv']);
        // SERP Intelligence (v03.03)
        add_action('admin_post_het_seo_serp_add_kw', [$this, 'handle_serp_add_kw']);
        add_action('admin_post_het_seo_serp_import', [$this, 'handle_serp_import']);

        // Socialmedia (Centrum accordion settings)
        add_action('admin_post_het_seo_socialmedia_save', [$this, 'handle_socialmedia_save']);

        // Socialmedia: file importer / queue actions (Centrum accordion)
        add_action('admin_post_het_seo_sm_import', [$this, 'handle_socialmedia_import']);
        add_action('admin_post_het_seo_sm_commit', [$this, 'handle_socialmedia_commit']);
        add_action('admin_post_het_seo_sm_tick', [$this, 'handle_socialmedia_tick_now']);
        add_action('admin_post_het_seo_export_social_logs', [$this, 'handle_export_social_logs']);
        add_action('admin_post_het_seo_social_metrics_refresh', [$this, 'handle_social_metrics_refresh']);


        // MEGA PACK B: rebuild style profile
        // Integrations hub + Rank tracker
add_action('admin_post_het_seo_integrations_save', [$this, 'handle_integrations_save']);
add_action('admin_post_het_seo_rank_add_kw', [$this, 'handle_rank_add_kw']);
add_action('admin_post_het_seo_rank_import', [$this, 'handle_rank_import']);

add_action('admin_post_het_seo_rebuild_style_profile', [$this, 'handle_rebuild_style_profile']);

        // 404 monitor
        add_action('template_redirect', [$this, 'capture_404'], 0);

        // redirects
        add_action('template_redirect', [$this->redir, 'maybe_redirect'], 0);

        // sitemap.xml (virtual)
        add_action('template_redirect', [$this, 'maybe_output_sitemap'], 1);

        // security.txt (virtual)
        add_action('template_redirect', [$this, 'maybe_output_security_txt'], 1);

        // robots.txt (virtual)
        add_filter('robots_txt', [$this, 'filter_robots_txt'], 10, 2);

        // Add meta output via wp_head (minimal, safe)
        add_action('wp_head', [$this, 'output_global_robots'], 0);
        add_action('wp_head', [$this, 'output_meta_tags'], 1);
    }

    /**
     * Safe redirect helper.
     * Some third-party plugins may echo output early in wp-admin, causing "headers already sent".
     * When headers are already sent, fall back to a JS redirect.
     */
    private function safe_redirect($url) {
        $url = esc_url_raw($url);
        if (!headers_sent()) {
            wp_safe_redirect($url);
            exit;
        }
        // Fallback: JS redirect.
        $json = wp_json_encode($url);
        echo '<script>window.location.replace(' . $json . ');</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_attr($url) . '"></noscript>';
        exit;
    }

    public static function install() {
        self::install_404_table();
    }

    public function menu() {
        $cap = 'manage_options';
        $icon = 'dashicons-chart-area';
        add_menu_page('Heterodyna SEO', 'Heterodyna SEO', $cap, self::MENU_SLUG_CENTER, [$this, 'page_center'], $icon, 56);

        // LEFT ADMIN MENU POLICY:
        // In WP sidebar show ONLY: Centrum, Ustawienia, Open Source.
        // All other plugin pages are hidden (parent = null) and accessible from Centrum UI / top tabs.
        add_submenu_page(self::MENU_SLUG_CENTER, 'Centrum', 'Centrum', $cap, self::MENU_SLUG_CENTER, [$this, 'page_center']);
        add_submenu_page(self::MENU_SLUG_CENTER, 'Ustawienia', 'Ustawienia', $cap, self::MENU_SLUG_SETTINGS, [$this, 'page_settings']);
        add_submenu_page(self::MENU_SLUG_CENTER, 'Open Source', 'Open Source', $cap, self::MENU_SLUG_LICENSE, [$this, 'page_license']);

        // Hidden “main” pages (still rendered with the Heterodyna SEO shell / CSS)
        add_submenu_page(null, 'Dane strony', 'Dane strony', $cap, self::MENU_SLUG_DATA_SITE, [$this, 'page_data_site']);
        add_submenu_page(null, 'Dane URL', 'Dane URL', $cap, self::MENU_SLUG_DATA_URL, [$this, 'page_data_url']);
        add_submenu_page(null, 'Historia SEO', 'Historia SEO', $cap, self::MENU_SLUG_HISTORY, [$this, 'page_history']);
        add_submenu_page(null, 'Analiza treści', 'Analiza treści', $cap, self::MENU_SLUG_CONTENT_INTEL, [$this, 'page_content_intel']);
        add_submenu_page(null, 'Intencja', 'Intencja', $cap, self::MENU_SLUG_INTENT, [$this, 'page_intent']);
        add_submenu_page(null, 'Luki treści', 'Luki treści', $cap, self::MENU_SLUG_CONTENT_GAPS, [$this, 'page_content_gaps']);
        add_submenu_page(null, 'SERP Intelligence', 'SERP Intelligence', $cap, self::MENU_SLUG_SERP_INTEL, [$this, 'page_serp_intel']);
        add_submenu_page(null, 'Architektura SEO', 'Architektura SEO', $cap, self::MENU_SLUG_ARCHITECTURE, [$this, 'page_architecture']);
        add_submenu_page(null, 'Monitoring SEO', 'Monitoring SEO', $cap, self::MENU_SLUG_MONITORING, [$this, 'page_monitoring']);

        // NOTE: Socialmedia is rendered as an accordion block inside Centrum (no separate admin submenu).

        // Hidden pages (linked from Centrum accordion / internal buttons)
        add_submenu_page(null, 'AI Auto', 'AI Auto', $cap, self::MENU_SLUG_AI_AUTO, [$this, 'page_ai_auto']);
        add_submenu_page(null, 'Generator treści AI', 'Generator treści AI', $cap, self::MENU_SLUG_AI_CONTENT, [$this, 'page_ai_content']);

        // Client: Content & Social
        add_submenu_page(null, 'Audyt treści', 'Audyt treści', $cap, self::MENU_SLUG_CONTENT_AUDIT, [$this, 'page_content_audit']);
        // Social (basic OG/Twitter settings) — hidden (accessible from Socialmedia hub)
        add_submenu_page(null, 'Social Media', 'Social Media', $cap, self::MENU_SLUG_SOCIAL, [$this, 'page_social']);

        // Social Hub (dashboard/accounts/composer/...) — hidden (accessible from Socialmedia hub)
        add_submenu_page(null, 'Social Dashboard', 'Social Dashboard', $cap, self::MENU_SLUG_SOCIAL_DASH, [$this, 'page_social_dashboard']);
        add_submenu_page(null, 'Social Konta', 'Social Konta', $cap, self::MENU_SLUG_SOCIAL_ACCOUNTS, [$this, 'page_social_accounts']);
        add_submenu_page(null, 'Social Kreator', 'Social Kreator', $cap, self::MENU_SLUG_SOCIAL_COMPOSER, [$this, 'page_social_composer']);
        add_submenu_page(null, 'Social Kampanie', 'Social Kampanie', $cap, self::MENU_SLUG_SOCIAL_CAMPAIGNS, [$this, 'page_social_campaigns']);
        add_submenu_page(null, 'Social Kolejka', 'Social Kolejka', $cap, self::MENU_SLUG_SOCIAL_QUEUE, [$this, 'page_social_queue']);
        add_submenu_page(null, 'Social Logi', 'Social Logi', $cap, self::MENU_SLUG_SOCIAL_LOGS, [$this, 'page_social_logs']);

        // Client-friendly status dashboard (read-only).
        add_submenu_page(null, 'Stan witryny', 'Stan witryny', $cap, self::MENU_SLUG_STATUS, [$this, 'page_status']);

        // Tools / diagnostics
        add_submenu_page(null, 'Testy usług', 'Testy usług', $cap, self::MENU_SLUG_TESTS, [$this, 'page_tests']);
        add_submenu_page(null, 'API', 'API', $cap, self::MENU_SLUG_API, [$this, 'page_api']);
        add_submenu_page(null, 'PSI', 'PSI', $cap, self::MENU_SLUG_PSI, [$this, 'page_psi']);
        add_submenu_page(null, 'Alerty', 'Alerty', $cap, self::MENU_SLUG_ALERTS, [$this, 'page_alerts']);
        add_submenu_page(null, 'Przekierowania', 'Przekierowania', $cap, self::MENU_SLUG_REDIRECTS, [$this, 'page_redirects']);

        // SEO Core
        add_submenu_page(null, 'Audyt meta', 'Audyt meta', $cap, self::MENU_SLUG_META_AUDIT, [$this, 'page_meta_audit']);
        add_submenu_page(null, 'Kanoniczne + indeksowanie', 'Kanoniczne + indeksowanie', $cap, self::MENU_SLUG_CANONICAL, [$this, 'page_canonical']);
        add_submenu_page(null, 'Sitemap', 'Sitemap', $cap, self::MENU_SLUG_SITEMAP, [$this, 'page_sitemap']);
        add_submenu_page(null, "Tryb witryny", "Tryb witryny", $cap, self::MENU_SLUG_SITE_MODE, [$this, "page_site_mode"]);

        add_submenu_page(null, 'Security.txt', 'Security.txt', $cap, self::MENU_SLUG_SECURITY, [$this, 'page_security']);
        add_submenu_page(null, 'Pliki systemowe', 'Pliki systemowe', $cap, self::MENU_SLUG_SYSFILES, [$this, 'page_sysfiles']);
        add_submenu_page(null, 'Integracje', 'Integracje', $cap, self::MENU_SLUG_INTEGRATIONS, [$this, 'page_integrations']);

        // Universal tools
        add_submenu_page(null, 'Monitor 404', 'Monitor 404', $cap, self::MENU_SLUG_404, [$this, 'page_404']);
        add_submenu_page(null, 'Robots', 'Robots', $cap, self::MENU_SLUG_ROBOTS, [$this, 'page_robots']);
        add_submenu_page(null, 'Import / Export', 'Import / Export', $cap, self::MENU_SLUG_IMPORT_EXPORT, [$this, 'page_import_export']);
        add_submenu_page(null, 'Duplikaty', 'Duplikaty', $cap, self::MENU_SLUG_DUPLICATES, [$this, 'page_duplicates']);
        // SEO Insights: conflicts + canonical/noindex decisions
        add_submenu_page(null, 'Analiza konfliktów SEO & decyzje indeksowania', 'Analiza konfliktów SEO & decyzje indeksowania', $cap, self::MENU_SLUG_INSIGHTS, [$this, 'page_insights']);
        add_submenu_page(null, 'Dokumentacja', 'Dokumentacja', $cap, self::MENU_SLUG_DOCS, [$this, 'page_docs']);
    }

    /**
     * Save Socialmedia preferences from Centrum accordion.
     */
    public function handle_socialmedia_save() {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        if (!$this->is_pro()) {
            wp_die('Ta funkcja jest dostępna w buildzie open-source. Odśwież stronę i spróbuj ponownie.');
        }
        check_admin_referer('het_seo_socialmedia_save');

        $mode = isset($_POST['het_seo_sm_mode']) ? sanitize_text_field(wp_unslash($_POST['het_seo_sm_mode'])) : 'manual';
        if (!in_array($mode, ['manual', 'file', 'ai_smart'], true)) {
            $mode = 'manual';
        }
        $file_url = isset($_POST['het_seo_sm_file_url']) ? esc_url_raw(wp_unslash($_POST['het_seo_sm_file_url'])) : '';
        $ai_guidelines = isset($_POST['het_seo_sm_ai_guidelines']) ? sanitize_textarea_field(wp_unslash($_POST['het_seo_sm_ai_guidelines'])) : '';
        $ui_ultra = isset($_POST['het_seo_sm_ui_ultra']) ? 1 : 0;
// Brand voice (used by generator)
$voice_tone = isset($_POST['het_seo_sm_voice_tone']) ? sanitize_text_field(wp_unslash($_POST['het_seo_sm_voice_tone'])) : 'neutral';
$voice_style = isset($_POST['het_seo_sm_voice_style']) ? sanitize_text_field(wp_unslash($_POST['het_seo_sm_voice_style'])) : 'mixed';
$cta_list = isset($_POST['het_seo_sm_cta_list']) ? sanitize_textarea_field(wp_unslash($_POST['het_seo_sm_cta_list'])) : '';
$hashtags = isset($_POST['het_seo_sm_hashtags']) ? sanitize_textarea_field(wp_unslash($_POST['het_seo_sm_hashtags'])) : '';
$emoji_mode = isset($_POST['het_seo_sm_emoji_mode']) ? sanitize_text_field(wp_unslash($_POST['het_seo_sm_emoji_mode'])) : 'auto';
if (!in_array($emoji_mode, ['off','auto','light','bold'], true)) { $emoji_mode = 'auto'; }
$banned_words = isset($_POST['het_seo_sm_banned_words']) ? sanitize_textarea_field(wp_unslash($_POST['het_seo_sm_banned_words'])) : '';


// Harmonogram (używany przy automatycznym planowaniu kampanii / importach)
$sched_mode = isset($_POST['het_seo_sm_sched_mode']) ? sanitize_text_field(wp_unslash($_POST['het_seo_sm_sched_mode'])) : 'immediate';
if (!in_array($sched_mode, ['immediate','next_slot'], true)) { $sched_mode = 'immediate'; }

$allowed_days = ['mon','tue','wed','thu','fri','sat','sun'];
$sched_days = isset($_POST['het_seo_sm_sched_days']) ? array_map('sanitize_key', (array)$_POST['het_seo_sm_sched_days']) : ['mon','tue','wed','thu','fri'];
$sched_days = array_values(array_intersect($allowed_days, $sched_days));
if (empty($sched_days)) { $sched_days = ['mon','tue','wed','thu','fri']; }

$sched_hour_from = isset($_POST['het_seo_sm_sched_hour_from']) ? (int)$_POST['het_seo_sm_sched_hour_from'] : 9;
$sched_hour_to = isset($_POST['het_seo_sm_sched_hour_to']) ? (int)$_POST['het_seo_sm_sched_hour_to'] : 18;
$sched_hour_from = max(0, min(23, $sched_hour_from));
$sched_hour_to = max(0, min(23, $sched_hour_to));
if ($sched_hour_to < $sched_hour_from) { $tmp = $sched_hour_from; $sched_hour_from = $sched_hour_to; $sched_hour_to = $tmp; }

$sched_interval = isset($_POST['het_seo_sm_sched_interval']) ? (int)$_POST['het_seo_sm_sched_interval'] : 30;
$sched_interval = max(5, min(180, $sched_interval));

$sched_max_per_tick = isset($_POST['het_seo_sm_sched_max_per_tick']) ? (int)$_POST['het_seo_sm_sched_max_per_tick'] : 20;
$sched_max_per_tick = max(1, min(200, $sched_max_per_tick));


// Kanały domyślne + UTM (używane w kampaniach / kolejce)
$providers_allowed = [];
if (class_exists('HET_SEO_Social_Hub') && method_exists('HET_SEO_Social_Hub','get_providers')) {
    $providers_allowed = array_keys(HET_SEO_Social_Hub::get_providers());
}
$channels_providers = isset($_POST['het_seo_sm_channels_providers']) ? array_map('sanitize_key', (array)$_POST['het_seo_sm_channels_providers']) : [];
if (!empty($providers_allowed)) {
    $channels_providers = array_values(array_intersect($providers_allowed, $channels_providers));
}
// UTM
$utm_source = isset($_POST['het_seo_sm_utm_source']) ? sanitize_text_field(wp_unslash($_POST['het_seo_sm_utm_source'])) : '';
$utm_medium = isset($_POST['het_seo_sm_utm_medium']) ? sanitize_text_field(wp_unslash($_POST['het_seo_sm_utm_medium'])) : 'social';
$utm_campaign_mode = isset($_POST['het_seo_sm_utm_campaign_mode']) ? sanitize_key(wp_unslash($_POST['het_seo_sm_utm_campaign_mode'])) : 'name';
if (!in_array($utm_campaign_mode, ['name','fixed'], true)) { $utm_campaign_mode = 'name'; }
$utm_campaign_fixed = isset($_POST['het_seo_sm_utm_campaign_fixed']) ? sanitize_text_field(wp_unslash($_POST['het_seo_sm_utm_campaign_fixed'])) : '';
$utm_content_mode = isset($_POST['het_seo_sm_utm_content_mode']) ? sanitize_key(wp_unslash($_POST['het_seo_sm_utm_content_mode'])) : 'provider';
if (!in_array($utm_content_mode, ['off','provider','account'], true)) { $utm_content_mode = 'provider'; }
$utm_term = isset($_POST['het_seo_sm_utm_term']) ? sanitize_text_field(wp_unslash($_POST['het_seo_sm_utm_term'])) : '';




// Szablony treści per kanał (stosowane przed publikacją/testami)
$tpl_x_prefix = isset($_POST['het_seo_sm_tpl_x_prefix']) ? sanitize_text_field(wp_unslash($_POST['het_seo_sm_tpl_x_prefix'])) : '';
$tpl_x_suffix = isset($_POST['het_seo_sm_tpl_x_suffix']) ? sanitize_text_field(wp_unslash($_POST['het_seo_sm_tpl_x_suffix'])) : '';
$tpl_x_max = isset($_POST['het_seo_sm_tpl_x_max']) ? (int)$_POST['het_seo_sm_tpl_x_max'] : 280;
$tpl_x_max = max(10, min(10000, $tpl_x_max));
$tpl_x_emoji = isset($_POST['het_seo_sm_tpl_x_emoji']) ? sanitize_key(wp_unslash($_POST['het_seo_sm_tpl_x_emoji'])) : 'auto';
if (!in_array($tpl_x_emoji, ['off','auto','light','bold'], true)) { $tpl_x_emoji = 'auto'; }

$tpl_li_prefix = isset($_POST['het_seo_sm_tpl_li_prefix']) ? sanitize_text_field(wp_unslash($_POST['het_seo_sm_tpl_li_prefix'])) : '';
$tpl_li_suffix = isset($_POST['het_seo_sm_tpl_li_suffix']) ? sanitize_text_field(wp_unslash($_POST['het_seo_sm_tpl_li_suffix'])) : '';
$tpl_li_max = isset($_POST['het_seo_sm_tpl_li_max']) ? (int)$_POST['het_seo_sm_tpl_li_max'] : 3000;
$tpl_li_max = max(10, min(10000, $tpl_li_max));
$tpl_li_emoji = isset($_POST['het_seo_sm_tpl_li_emoji']) ? sanitize_key(wp_unslash($_POST['het_seo_sm_tpl_li_emoji'])) : 'auto';
if (!in_array($tpl_li_emoji, ['off','auto','light','bold'], true)) { $tpl_li_emoji = 'auto'; }

$tpl_fb_prefix = isset($_POST['het_seo_sm_tpl_fb_prefix']) ? sanitize_text_field(wp_unslash($_POST['het_seo_sm_tpl_fb_prefix'])) : '';
$tpl_fb_suffix = isset($_POST['het_seo_sm_tpl_fb_suffix']) ? sanitize_text_field(wp_unslash($_POST['het_seo_sm_tpl_fb_suffix'])) : '';
$tpl_fb_max = isset($_POST['het_seo_sm_tpl_fb_max']) ? (int)$_POST['het_seo_sm_tpl_fb_max'] : 5000;
$tpl_fb_max = max(10, min(10000, $tpl_fb_max));
$tpl_fb_emoji = isset($_POST['het_seo_sm_tpl_fb_emoji']) ? sanitize_key(wp_unslash($_POST['het_seo_sm_tpl_fb_emoji'])) : 'auto';
if (!in_array($tpl_fb_emoji, ['off','auto','light','bold'], true)) { $tpl_fb_emoji = 'auto'; }

$templates = [
    'x' => ['prefix'=>$tpl_x_prefix,'suffix'=>$tpl_x_suffix,'max'=>$tpl_x_max,'emoji'=>$tpl_x_emoji],
    'linkedin' => ['prefix'=>$tpl_li_prefix,'suffix'=>$tpl_li_suffix,'max'=>$tpl_li_max,'emoji'=>$tpl_li_emoji],
    'facebook' => ['prefix'=>$tpl_fb_prefix,'suffix'=>$tpl_fb_suffix,'max'=>$tpl_fb_max,'emoji'=>$tpl_fb_emoji],
];

// Publikacja: Live vs Safe (approval) + limity dzienne
$publish_mode = isset($_POST['het_seo_sm_publish_mode']) ? sanitize_key(wp_unslash($_POST['het_seo_sm_publish_mode'])) : 'live';
if (!in_array($publish_mode, ['live','safe'], true)) { $publish_mode = 'live'; }
$limit_provider_day = isset($_POST['het_seo_sm_limit_provider_day']) ? (int)$_POST['het_seo_sm_limit_provider_day'] : 25;
$limit_campaign_day = isset($_POST['het_seo_sm_limit_campaign_day']) ? (int)$_POST['het_seo_sm_limit_campaign_day'] : 50;
$limit_provider_day = max(1, min(500, $limit_provider_day));
$limit_campaign_day = max(1, min(500, $limit_campaign_day));


        update_option('het_seo_socialmedia', [
'mode' => $mode,
            'file_url' => $file_url,
            'ai_guidelines' => $ai_guidelines,
            'ui_ultra' => $ui_ultra,
            'voice' => [
                'tone' => $voice_tone,
                'style' => $voice_style,
                'cta_list' => $cta_list,
                'hashtags' => $hashtags,
                'emoji_mode' => $emoji_mode,
                'banned_words' => $banned_words,
            ],
            'schedule' => [
                'mode' => $sched_mode,
                'days' => $sched_days,
                'hour_from' => $sched_hour_from,
                'hour_to' => $sched_hour_to,
                'interval' => $sched_interval,
                'max_per_tick' => $sched_max_per_tick,
            ],
            'channels' => [
                'providers' => $channels_providers,
            ],
            'utm' => [
                'source' => $utm_source,
                'medium' => $utm_medium,
                'campaign_mode' => $utm_campaign_mode,
                'campaign_fixed' => $utm_campaign_fixed,
                'content_mode' => $utm_content_mode,
                'term' => $utm_term,
            ],
            'templates' => $templates,

            'publish' => [
                'mode' => $publish_mode,
                'limits' => [
                    'per_provider_per_day' => $limit_provider_day,
                    'per_campaign_per_day' => $limit_campaign_day,
                ],
            ],
            'updated_at' => time(),
        ], false);

        $back = add_query_arg([
            'page' => self::MENU_SLUG_CENTER,
            'het_seo_notice' => 'socialmedia_saved',
        ], admin_url('admin.php'));
        $this->safe_redirect($back);
        exit;
    }

    /**
     * Import Socialmedia briefs from URL / upload (JSON/CSV) and store in transient for preview.
     */
    public function handle_socialmedia_import() {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        if (!$this->is_pro()) {
            wp_die('Ta funkcja jest dostępna w buildzie open-source. Odśwież stronę i spróbuj ponownie.');
        }
        check_admin_referer('het_seo_sm_import');

        $raw = '';
        $name = '';

        // Upload has priority.
        if (!empty($_FILES['het_seo_sm_upload']) && isset($_FILES['het_seo_sm_upload']['tmp_name']) && is_uploaded_file($_FILES['het_seo_sm_upload']['tmp_name'])) {
            $file = $_FILES['het_seo_sm_upload'];
            if ((int)($file['size'] ?? 0) > 2 * 1024 * 1024) {
                $this->redirect_center_notice('sm_import_error', ['err' => 'Plik jest za duży (max 2MB).']);
            }
            $name = sanitize_file_name((string)($file['name'] ?? 'upload'));
            $raw = (string)file_get_contents($file['tmp_name']);
        } else {
            $url = isset($_POST['het_seo_sm_import_url']) ? esc_url_raw(wp_unslash($_POST['het_seo_sm_import_url'])) : '';
            if (!$url) {
                $this->redirect_center_notice('sm_import_error', ['err' => 'Podaj URL lub wgraj plik.']);
            }
            $name = $url;
            $res = wp_remote_get($url, ['timeout' => 12, 'redirection' => 3, 'user-agent' => 'HeterodynaSEO/' . HET_SEO_VER]);
            if (is_wp_error($res)) {
                $this->redirect_center_notice('sm_import_error', ['err' => $res->get_error_message()]);
            }
            $code = (int)wp_remote_retrieve_response_code($res);
            if ($code < 200 || $code >= 300) {
                $this->redirect_center_notice('sm_import_error', ['err' => 'HTTP ' . $code]);
            }
            $raw = (string)wp_remote_retrieve_body($res);
            if (strlen($raw) > 2 * 1024 * 1024) {
                $this->redirect_center_notice('sm_import_error', ['err' => 'Pobrany plik jest za duży (max 2MB).']);
            }
        }

        $parsed = $this->parse_socialmedia_briefs($raw, $name);
        if (is_wp_error($parsed)) {
            $this->redirect_center_notice('sm_import_error', ['err' => $parsed->get_error_message()]);
        }
        $items = $parsed;
        if (empty($items)) {
            $this->redirect_center_notice('sm_import_error', ['err' => 'Brak rekordów do importu.']);
        }

        $token = strtolower(wp_generate_password(10, false, false));
        $key = 'het_seo_sm_import_' . get_current_user_id() . '_' . $token;
        set_transient($key, [
            'name' => (string)$name,
            'items' => $items,
            'created_at' => time(),
        ], 20 * MINUTE_IN_SECONDS);

        $this->redirect_center_notice('sm_imported', ['sm_token' => $token]);
    }

    /**
     * Commit previously imported briefs (from transient) into Social Hub tables (posts + queue).
     */
    public function handle_socialmedia_commit() {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        if (!$this->is_pro()) {
            wp_die('Ta funkcja jest dostępna w buildzie open-source. Odśwież stronę i spróbuj ponownie.');
        }
        check_admin_referer('het_seo_sm_commit');

        $token = isset($_POST['sm_token']) ? sanitize_text_field(wp_unslash($_POST['sm_token'])) : '';
        if (!$token) {
            $this->redirect_center_notice('sm_commit_error', ['err' => 'Brak tokenu importu.']);
        }
        $key = 'het_seo_sm_import_' . get_current_user_id() . '_' . $token;
        $payload = get_transient($key);
        if (!$payload || !is_array($payload) || empty($payload['items']) || !is_array($payload['items'])) {
            $this->redirect_center_notice('sm_commit_error', ['err' => 'Import wygasł lub brak danych. Zrób import jeszcze raz.']);
        }

        if (!class_exists('HET_SEO_Social_Hub')) {
            $this->redirect_center_notice('sm_commit_error', ['err' => 'Brak modułu Social Hub.']);
        }

        $sm = get_option('het_seo_socialmedia', []);
        $mode = isset($sm['mode']) ? (string)$sm['mode'] : 'manual';
        $guidelines = isset($sm['ai_guidelines']) ? (string)$sm['ai_guidelines'] : '';
        $voice = (isset($sm['voice']) && is_array($sm['voice'])) ? $sm['voice'] : [];

        $schedule = (isset($sm['schedule']) && is_array($sm['schedule'])) ? $sm['schedule'] : [];
        $use_schedule = !empty($_POST['sm_use_schedule']);
        $base_schedule_at = '';
        $sched_interval = isset($schedule['interval']) ? (int)$schedule['interval'] : 30;
        $sched_interval = max(5, min(180, $sched_interval));
        if ($use_schedule && class_exists('HET_SEO_Social_Hub') && method_exists('HET_SEO_Social_Hub','compute_next_slot')) {
            $base_schedule_at = HET_SEO_Social_Hub::compute_next_slot($schedule);
        }

        $aid = HET_SEO_Social_Hub::ensure_default_account();

        $items = array_slice($payload['items'], 0, 200);
        $created = 0;
        foreach ($items as $it) {
            $topic = isset($it['topic']) ? (string)$it['topic'] : '';
            $brief = isset($it['brief']) ? (string)$it['brief'] : '';
            $url = isset($it['url']) ? (string)$it['url'] : '';

            $title = $topic !== '' ? $topic : (isset($it['title']) ? (string)$it['title'] : 'Social post');

            $gen = null;
            if ($mode === 'ai_smart') {
                $gen = HET_SEO_Social_Hub::ai_generate_smart($title, $guidelines, $voice);
            }

            $content = '';
            if (is_array($gen)) {
                $title = $gen['title'] ?? $title;
                $content = (string)($gen['content'] ?? '');
            } else {
                // File mode: build from brief (safe, not AI).
                $content = "📌 {$title}\n\n";
                if ($brief !== '') {
                    $content .= "Brief:\n" . $brief . "\n\n";
                } else {
                    $content .= "Szkic:\n— punkt 1\n— punkt 2\n— CTA\n\n";
                }
                if ($url !== '') {
                    $content .= "Źródło: {$url}\n\n";
                }
                $content .= "#Heterodyna";
            }

            $media = (isset($it['media']) && is_array($it['media'])) ? $it['media'] : [];
            $post_id = HET_SEO_Social_Hub::create_post($title, $content, $url, $media);
            // One campaign per post (dry-run) so queue can process them individually.
            $schedule_at = '';
            if ($use_schedule && $base_schedule_at) {
                try {
                    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
                    $dt = new DateTimeImmutable($base_schedule_at, $tz);
                    $dt = $dt->modify('+' . (int)($created * $sched_interval) . ' minutes');
                    $schedule_at = $dt->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    $schedule_at = $base_schedule_at;
                }
            }
            HET_SEO_Social_Hub::create_campaign('SM Import: ' . mb_substr($title, 0, 64), $post_id, [$aid], 'dry', $schedule_at);
            $created++;
        }

        // Keep transient for preview, but add marker.
        $payload['committed_at'] = time();
        set_transient($key, $payload, 20 * MINUTE_IN_SECONDS);

        $this->redirect_center_notice('sm_committed', ['sm_token' => $token, 'sm_created' => (int)$created]);
    }

    /**
     * Manually process a small queue batch now (dry-run will mark as sent).
     */
    public function handle_socialmedia_tick_now() {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        if (!$this->is_pro()) {
            wp_die('Ta funkcja jest dostępna w buildzie open-source. Odśwież stronę i spróbuj ponownie.');
        }
        check_admin_referer('het_seo_sm_tick');
        if (class_exists('HET_SEO_Social_Hub')) {
            HET_SEO_Social_Hub::queue_tick();
        }
        $this->redirect_center_notice('sm_ticked', []);
    }

    private function redirect_center_notice($notice, $extra = []) {
        $args = array_merge([
            'page' => self::MENU_SLUG_CENTER,
            'het_seo_notice' => $notice,
        ], (array)$extra);
        $back = add_query_arg($args, admin_url('admin.php'));
        $this->safe_redirect($back);
        exit;
    }

    /**
     * Parse JSON/CSV social briefs. Returns array of normalized items or WP_Error.
     */
    private function parse_socialmedia_briefs($raw, $name = '') {
        $raw = (string)$raw;
        $name = (string)$name;
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw); // strip UTF-8 BOM
        $trim = trim($raw);
        if ($trim === '') {
            return new WP_Error('empty', 'Plik jest pusty.');
        }

        $is_json = false;
        if (preg_match('/\.json(\?.*)?$/i', $name)) { $is_json = true; }
        if (!$is_json && (isset($trim[0]) && ($trim[0] === '{' || $trim[0] === '['))) { $is_json = true; }

        if ($is_json) {
            $data = json_decode($trim, true);
            if (!is_array($data)) {
                return new WP_Error('json', 'Nieprawidłowy JSON. Oczekuję tablicy obiektów.');
            }
            // Accept {items:[...]} too
            if (isset($data['items']) && is_array($data['items'])) { $data = $data['items']; }
            $out = [];
            foreach ($data as $row) {
                if (!is_array($row)) { continue; }
                $out[] = $this->normalize_sm_row($row);
            }
            $out = array_values(array_filter($out, function($r){ return !empty($r['topic']) || !empty($r['brief']) || !empty($r['text']); }));
            return $out;
        }

        // CSV fallback
        $rows = preg_split("/\r\n|\r|\n/", $trim);
        if (!is_array($rows) || count($rows) < 1) {
            return new WP_Error('csv', 'Nieprawidłowy CSV.');
        }
        // detect delimiter
        $first = (string)$rows[0];
        $delims = [',',';','\t','|'];
        $best = ','; $bestc = -1;
        foreach ($delims as $d) {
            $c = substr_count($first, $d);
            if ($c > $bestc) { $bestc = $c; $best = $d; }
        }
        $header = str_getcsv(array_shift($rows), $best);
        $header = array_map(function($h){ return sanitize_key($h); }, (array)$header);
        $out = [];
        foreach ($rows as $line) {
            if (trim($line) === '') { continue; }
            $cols = str_getcsv($line, $best);
            $row = [];
            foreach ($header as $i => $k) {
                if ($k === '') { continue; }
                $row[$k] = isset($cols[$i]) ? $cols[$i] : '';
            }
            $out[] = $this->normalize_sm_row($row);
        }
        $out = array_values(array_filter($out, function($r){ return !empty($r['topic']) || !empty($r['brief']) || !empty($r['text']); }));
        return $out;
    }

    private function normalize_sm_row($row) {
        $topic = '';
        if (isset($row['topic'])) { $topic = (string)$row['topic']; }
        elseif (isset($row['title'])) { $topic = (string)$row['title']; }
        elseif (isset($row['temat'])) { $topic = (string)$row['temat']; }

        $brief = '';
        if (isset($row['brief'])) { $brief = (string)$row['brief']; }
        elseif (isset($row['opis'])) { $brief = (string)$row['opis']; }
        elseif (isset($row['description'])) { $brief = (string)$row['description']; }

        $text = '';
        if (isset($row['text'])) { $text = (string)$row['text']; }
        elseif (isset($row['content'])) { $text = (string)$row['content']; }
        elseif (isset($row['body'])) { $text = (string)$row['body']; }

        // If someone provides full text but leaves brief empty, treat text as brief for compatibility with existing importer/commit flow.
        if ($brief === '' && $text !== '') { $brief = $text; }

        $url = '';
        if (isset($row['url'])) { $url = (string)$row['url']; }
        elseif (isset($row['source_url'])) { $url = (string)$row['source_url']; }
        elseif (isset($row['link'])) { $url = (string)$row['link']; }
        elseif (isset($row['href'])) { $url = (string)$row['href']; }
        $url = $url ? esc_url_raw($url) : '';

        $tags = '';
        if (isset($row['tags'])) { $tags = (string)$row['tags']; }
        if (isset($row['tagi'])) { $tags = (string)$row['tagi']; }

        // Media: accept common keys (media, media_url, image, image_url). Comma/newline separated.
        $media_raw = '';
        if (isset($row['media'])) { $media_raw = (string)$row['media']; }
        elseif (isset($row['media_url'])) { $media_raw = (string)$row['media_url']; }
        elseif (isset($row['image'])) { $media_raw = (string)$row['image']; }
        elseif (isset($row['image_url'])) { $media_raw = (string)$row['image_url']; }
        $media_raw = trim((string)$media_raw);
        $media = [];
        if ($media_raw !== '') {
            $parts = preg_split('/\s*,\s*|\r\n|\r|\n/', $media_raw);
            foreach ((array)$parts as $p) {
                $p = trim((string)$p);
                if ($p === '') continue;
                $media[] = esc_url_raw($p);
            }
        }

        return [
            'topic' => sanitize_text_field(wp_unslash($topic)),
            'brief' => sanitize_textarea_field(wp_unslash($brief)),
            'text'  => sanitize_textarea_field(wp_unslash($text)),
            'url' => $url,
            'tags' => sanitize_text_field(wp_unslash($tags)),
            'media' => $media,
        ];
    }

    private function http_check($url) {
        $args = [
            'timeout' => 10,
            'redirection' => 3,
            'user-agent' => 'HeterodynaSEO/' . HET_SEO_VER,
        ];
        $res = wp_remote_get($url, $args);
        if (is_wp_error($res)) {
            return ['ok'=>false,'code'=>0,'detail'=>$res->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        return ['ok'=>($code>=200 && $code<400),'code'=>$code,'detail'=>''];
    }

    public function assets($hook) {
        // Load assets on plugin pages AND on post editor screens (metabox styling).
        $is_plugin = (strpos($hook, 'het_seo_') !== false) || (strpos($hook, 'heterodyna-seo') !== false);
        $is_editor = in_array($hook, ['post.php', 'post-new.php'], true);
        if (!$is_plugin && !$is_editor) { return; }
        wp_enqueue_style('het-seo-admin', HET_SEO_URL . 'admin/css/admin.css', [], HET_SEO_VER);
        wp_enqueue_script('het-seo-admin', HET_SEO_URL . 'admin/js/admin.js', ['jquery'], HET_SEO_VER, true);

        $ui = get_option('het_seo_ui', []);
        $density = (isset($ui['density']) && in_array($ui['density'], ['compact','comfortable'], true)) ? $ui['density'] : 'compact';
        wp_localize_script('het-seo-admin', 'HET_SEO', [
            'rest' => esc_url_raw(rest_url('heterodyna-seo/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'ui' => [
                'density' => $density,
            ],
            'i18n' => HET_SEO_I18N::js_labels(),
        ]);
    }

    private function header_shell($active = 'center') {
        $state = $this->license->get_state();
        $ui = get_option('het_seo_ui', []);
        $ui_density = (isset($ui['density']) && in_array($ui['density'], ['compact','comfortable'], true)) ? $ui['density'] : 'compact';
        include HET_SEO_PATH . 'templates/admin-header.php';
    }
    private function footer_shell() {
        include HET_SEO_PATH . 'templates/admin-footer.php';
    }


    public function page_center() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        $this->header_shell('center');

        // Dashboard quick stats (best-effort, no hard deps)
        global $wpdb;
        $dashboard = [
            'ai_queue_pending' => 0,
            'ai_queue_approval' => 0,
            'psi_queue_pending' => 0,
            'psi_last' => null,
            'psi_trend' => [],
            'sitekit' => [ 'ok' => false, 'message' => 'n/a', 'gsc' => [], 'ga4' => [], 'psi' => [] ],
        ];

        // AI queue counts
        $ai_table = $wpdb->prefix . HET_SEO_AI_Queue::TABLE;
        $dashboard['ai_queue_pending'] = (int) $wpdb->get_var("SELECT COUNT(1) FROM {$ai_table} WHERE status='pending'");
        $dashboard['ai_queue_approval'] = (int) $wpdb->get_var("SELECT COUNT(1) FROM {$ai_table} WHERE status='approval'");

        // PSI stats
        $psi_q = $wpdb->prefix . 'het_seo_psi_queue';
        $psi_r = $wpdb->prefix . 'het_seo_psi_results';
        $dashboard['psi_queue_pending'] = (int) $wpdb->get_var("SELECT COUNT(1) FROM {$psi_q} WHERE status='pending'");
        $dashboard['psi_last'] = $wpdb->get_row("SELECT * FROM {$psi_r} ORDER BY id DESC LIMIT 1", ARRAY_A);

        // Last 14 perf points (any URL) for mini-trend
        $rows = $wpdb->get_results("SELECT fetched_at, perf FROM {$psi_r} WHERE perf IS NOT NULL ORDER BY id DESC LIMIT 14", ARRAY_A);
        if (is_array($rows) && !empty($rows)) {
            $rows = array_reverse($rows);
            foreach ($rows as $r) {
                $dashboard['psi_trend'][] = [
                    't' => (string) $r['fetched_at'],
                    'v' => is_null($r['perf']) ? null : (int) $r['perf'],
                ];
            }
        }

        // Site Kit KPIs (best-effort; no hard dependencies)
        if (class_exists('HET_SEO_SiteKit')) {
            $dashboard['sitekit'] = HET_SEO_SiteKit::get_kpis(28);
        }

        include HET_SEO_PATH . 'templates/admin-center.php';
    }

    public function page_data_site() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        // Manual snapshot
        if (isset($_POST['het_seo_collect_site']) && check_admin_referer('het_seo_collect_site')) {
            $this->datacore->collect_site_snapshot(true);
            $back = wp_get_referer();
            if (!$back) {
                $back = add_query_arg(['page' => self::MENU_SLUG_DATA_SITE], admin_url('admin.php'));
            }
            $this->safe_redirect(add_query_arg(['het_notice' => 'collected'], $back));
            exit;
        }

        $this->header_shell('data_site');
        $site = $this->datacore->get_site_metrics();
        include HET_SEO_PATH . 'templates/admin-data-site.php';
    }

    public function page_data_url() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }

        // Manual snapshot for selected post
        if (isset($_POST['het_seo_collect_url']) && check_admin_referer('het_seo_collect_url')) {
            $pid = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
            if ($pid > 0) {
                $this->datacore->collect_url_snapshot($pid, true);
            }
            $back = wp_get_referer();
            if (!$back) {
                $back = add_query_arg(['page' => self::MENU_SLUG_DATA_URL], admin_url('admin.php'));
            }
            $this->safe_redirect(add_query_arg(['het_notice' => 'collected'], $back));
            exit;
        }

        $this->header_shell('data_url');
        $limit = isset($_GET['limit']) ? max(25, min(500, (int)$_GET['limit'])) : 200;
        $urls = $this->datacore->get_urls_overview($limit);
        include HET_SEO_PATH . 'templates/admin-data-url.php';
    }

    public function page_history() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        $this->header_shell('history');
        $post_id = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;
        $hist = $this->datacore->get_history($post_id, 300);
        include HET_SEO_PATH . 'templates/admin-history.php';
    }

    public function page_content_intel() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        if (!$this->is_pro()) { $this->render_pro_lock('Analiza treści'); return; }

        // Manual analyze
        if (isset($_POST['het_seo_analyze_now']) && check_admin_referer('het_seo_analyze_now')) {
            $pid = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
            if ($pid > 0) {
                $this->content_intel->analyze_post($pid, true);
            } else {
                // small batch
                $this->content_intel->analyze_batch(25);
            }
            $back = wp_get_referer();
            if (!$back) {
                $back = add_query_arg(['page' => self::MENU_SLUG_CONTENT_INTEL], admin_url('admin.php'));
            }
            $this->safe_redirect(add_query_arg(['het_notice' => 'analyzed'], $back));
            exit;
        }

        $this->header_shell('content_intel');
        $limit = isset($_GET['limit']) ? max(25, min(500, (int)$_GET['limit'])) : 200;
        $rows = $this->content_intel->get_overview($limit);
        include HET_SEO_PATH . 'templates/admin-content-intel.php';
    }

    public function page_intent() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        if (!$this->is_pro()) { $this->render_pro_lock('Intencja'); return; }
        $this->header_shell('intent');
        $limit = isset($_GET['limit']) ? max(25, min(500, (int)$_GET['limit'])) : 200;
        $rows = $this->content_intel->get_overview($limit);
        include HET_SEO_PATH . 'templates/admin-intent.php';
    }

    public function page_content_gaps() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        if (!$this->is_pro()) { $this->render_pro_lock('Luki treści'); return; }
        $this->header_shell('gaps');
        $post_id = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;
        $detail = $post_id > 0 ? $this->content_intel->get_detail($post_id) : null;
        $limit = isset($_GET['limit']) ? max(25, min(300, (int)$_GET['limit'])) : 100;
        $rows = $this->content_intel->get_overview($limit);
        include HET_SEO_PATH . 'templates/admin-content-gaps.php';
    }

    

    public function page_serp_intel() {
        if (!current_user_can('manage_options')) { return; }
        if (!$this->is_pro()) { $this->render_pro_lock('SERP Intelligence'); return; }
        $this->header_shell('serp_intel');
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'serp';
        $tabs = [
            'serp' => 'SERP',
            'competition' => 'Konkurencja',
            'gap' => 'GAP',
        ];

        echo '<div class="het-page">';
        echo '<div class="het-card" style="margin-bottom:14px">';
        echo '<div class="het-card__head">';
        echo '<div>';
        echo '<h2 style="margin:0">SERP Intelligence</h2>';
        echo '<div class="het-muted" style="max-width:980px">Analiza SERP/konkurencji/GAP. Best-effort: bez zewnętrznych API, z cache i realnymi metrykami z Twoich treści.</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Tabs (WOW: real tabs, not links list)
        echo '<nav class="het-tabs" style="margin:12px 0 16px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">';
        foreach ($tabs as $k=>$label) {
            $url = admin_url('admin.php?page=' . self::MENU_SLUG_SERP_INTEL . '&tab=' . $k);
            $active = ($tab===$k) ? 'style="background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 2px rgba(0,0,0,.04);"' : 'style="background:#f6f7f7;border:1px solid #dcdcde;"';
            echo '<a class="button" ' . $active . ' href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';

        $keywords = $this->serp->list_keywords(200);
        $current_keyword_id = isset($_GET['kw']) ? (int)$_GET['kw'] : (isset($keywords[0]['id']) ? (int)$keywords[0]['id'] : 0);
        $current_keyword = $current_keyword_id ? $this->serp->get_keyword($current_keyword_id) : null;

        // Keyword control row
        echo '<div class="het-card" style="padding:14px; margin-bottom:14px;">';
        echo '<div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">';

        // Add keyword
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; margin:0;">';
        wp_nonce_field('het_seo_serp_add_kw');
        echo '<input type="hidden" name="action" value="het_seo_serp_add_kw" />';
        echo '<div><label><strong>Dodaj keyword</strong></label><br/><input type="text" name="keyword" class="regular-text" placeholder="np. detektor metali" required /></div>';
        echo '<div><label><strong>Locale</strong></label><br/><input type="text" name="locale" value="pl-PL" style="width:90px;" /></div>';
        echo '<div><label><strong>Device</strong></label><br/><select name="device"><option value="mobile">mobile</option><option value="desktop">desktop</option></select></div>';
        echo '<div><button class="button button-primary">Dodaj</button></div>';
        echo '</form>';

        // Select keyword
        echo '<form method="get" action="" style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; margin:0;">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::MENU_SLUG_SERP_INTEL) . '" />';
        echo '<input type="hidden" name="tab" value="' . esc_attr($tab) . '" />';
        echo '<div><label><strong>Wybrany keyword</strong></label><br/><select name="kw" style="min-width:260px;">';
        foreach ($keywords as $kw) {
            $sel = ((int)$kw['id']===$current_keyword_id) ? 'selected' : '';
            echo '<option value="' . (int)$kw['id'] . '" ' . $sel . '>' . esc_html($kw['keyword']) . ' (' . esc_html($kw['device']) . ')</option>';
        }
        echo '</select></div>';
        echo '<div><button class="button">Pokaż</button></div>';
        echo '</form>';

        echo '</div>';
        echo '</div>';

        if ($tab === 'serp') {
            $this->render_serp_tab($current_keyword);
        } elseif ($tab === 'competition') {
            $this->render_competition_tab();
        } else {
            $this->render_gap_tab($current_keyword);
        }

        echo '</div>'; // .het-page
        $this->footer_shell();
    }

    private function render_serp_tab($current_keyword) {
        $kw_id = $current_keyword ? (int)$current_keyword['id'] : 0;

        echo '<div class="het-card" style="padding:14px; margin-bottom:14px;">';
        echo '<h2 style="margin-top:0;">SERP (wklej wyniki)</h2>';
        echo '<p style="margin-top:6px; color:#50575e;">Dla stabilności nie scrapujemy Google z serwera. Wklej eksport SERP z dowolnego narzędzia (JSON lub CSV). System policzy metryki i GAP.</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('het_seo_serp_import');
        echo '<input type="hidden" name="action" value="het_seo_serp_import" />';
        echo '<input type="hidden" name="keyword_id" value="' . (int)$kw_id . '" />';
        echo '<textarea name="payload" rows="10" style="width:100%; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;" placeholder="JSON: [{position:1,url:https://...}]\nCSV: 1;https://...;Tytuł"></textarea>';
        echo '<p style="margin:10px 0 0; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">';
        echo '<button class="button button-primary" ' . ($kw_id? '' : 'disabled') . '>Importuj SERP</button>';
        echo '</p>';
        echo '</form>';
        echo '</div>';

        if (!$kw_id) {
            echo '<div class="notice notice-warning"><p>Dodaj keyword, aby zobaczyć wyniki SERP.</p></div>';
            return;
        }

        $rows = $this->serp->list_results($kw_id, 20);

        echo '<div class="het-card" style="padding:14px;">';
        echo '<h2 style="margin-top:0;">TOP 10/20 (zaimportowane)</h2>';
        if (!$rows) {
            echo '<p>Brak wyników. Wklej dane w sekcji powyżej.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr>
                <th>#</th><th>URL</th><th>Domena</th><th>Words</th><th>H2</th><th>H3</th><th>Schema</th>
            </tr></thead><tbody>';
            foreach ($rows as $r) {
                $m = json_decode($r['metrics_json'] ?? '', true);
                $s = json_decode($r['structure_json'] ?? '', true);
                $schema = '';
                if (is_array($s) && !empty($s['schema']) && is_array($s['schema'])) {
                    $schema = implode(', ', array_slice(array_map('strval', $s['schema']), 0, 4));
                }
                echo '<tr>';
                echo '<td>' . (int)$r['position'] . '</td>';
                echo '<td><a href="' . esc_url($r['url']) . '" target="_blank" rel="noopener">' . esc_html($r['title'] ?: $r['url']) . '</a></td>';
                echo '<td>' . esc_html($r['domain']) . '</td>';
                echo '<td>' . esc_html((string)($m['words'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string)($m['h2'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string)($m['h3'] ?? '')) . '</td>';
                echo '<td>' . esc_html($schema) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }

    private function render_competition_tab() {
        $rows = $this->serp->competition_rollup(50);

        echo '<div class="het-card" style="padding:14px;">';
        echo '<h2 style="margin-top:0;">Konkurencja (rollup domen)</h2>';
        echo '<p style="margin-top:6px; color:#50575e;">Zliczane na podstawie wszystkich zaimportowanych SERP. Im więcej keywordów dodasz, tym lepszy obraz.</p>';

        if (!$rows) {
            echo '<p>Brak danych. Zaimportuj chociaż jeden SERP w zakładce SERP.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr>
                <th>Domena</th><th>Wystąpień</th><th>Śr. pozycja</th>
            </tr></thead><tbody>';
            foreach ($rows as $r) {
                echo '<tr>';
                echo '<td>' . esc_html($r['domain']) . '</td>';
                echo '<td>' . (int)$r['cnt'] . '</td>';
                echo '<td>' . esc_html(number_format_i18n((float)$r['avg_pos'], 1)) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '</div>';
    }

    private function render_gap_tab($current_keyword) {
        $kw_id = $current_keyword ? (int)$current_keyword['id'] : 0;
        $gap = $kw_id ? $this->serp->get_gap($kw_id) : null;

        echo '<div class="het-card" style="padding:14px;">';
        echo '<h2 style="margin-top:0;">GAP (best-effort)</h2>';
        if (!$kw_id) {
            echo '<p>Dodaj keyword, aby zobaczyć GAP.</p>';
            echo '</div>';
            return;
        }
        if (!$gap) {
            echo '<p>Brak danych GAP. Zaimportuj SERP w zakładce SERP — GAP liczy się automatycznie.</p>';
            echo '</div>';
            return;
        }

        $gap_json = json_decode($gap['gap_json'] ?? '', true);
        $top3 = is_array($gap_json) ? ($gap_json['top3_avg'] ?? []) : [];

        echo '<div style="display:flex; gap:12px; flex-wrap:wrap; margin:10px 0 14px;">';
        echo '<div class="het-metric" style="padding:10px 12px; border:1px solid #dcdcde; border-radius:12px; background:#fff;"><div style="font-size:12px; color:#50575e;">GAP score</div><div style="font-size:22px; font-weight:700;">' . (int)$gap['gap_score'] . '</div></div>';
        echo '<div class="het-metric" style="padding:10px 12px; border:1px solid #dcdcde; border-radius:12px; background:#fff;"><div style="font-size:12px; color:#50575e;">TOP3 avg words</div><div style="font-size:22px; font-weight:700;">' . esc_html((string)($top3['words'] ?? '—')) . '</div></div>';
        echo '<div class="het-metric" style="padding:10px 12px; border:1px solid #dcdcde; border-radius:12px; background:#fff;"><div style="font-size:12px; color:#50575e;">TOP3 avg H2</div><div style="font-size:22px; font-weight:700;">' . esc_html((string)($top3['h2'] ?? '—')) . '</div></div>';
        echo '<div class="het-metric" style="padding:10px 12px; border:1px solid #dcdcde; border-radius:12px; background:#fff;"><div style="font-size:12px; color:#50575e;">TOP3 avg H3</div><div style="font-size:22px; font-weight:700;">' . esc_html((string)($top3['h3'] ?? '—')) . '</div></div>';
        echo '</div>';

        echo '<p style="color:#50575e; margin:0;">' . esc_html((string)($gap_json['note'] ?? '')) . '</p>';

        echo '</div>';
    }
    /**
     * ARCHITECTURE (v03.04): Cannibalization / Clusters / Structure (tabs).
     */
    public function page_architecture() {
        if (!current_user_can('manage_options')) { return; }
        if (!$this->is_pro()) { $this->render_pro_lock('Architektura SEO'); return; }
        $this->header_shell('architecture');
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'cannibal';
        $tabs = [
            'cannibal' => 'Kanibalizacja',
            'clusters' => 'Klastry',
            'structure' => 'Struktura',
        ];

        echo '<div class="het-page">';
        echo '<div class="het-card" style="margin-bottom:14px">';
        echo '<div class="het-card__head">';
        echo '<div>';
        echo '<h2 style="margin:0">Architektura SEO</h2>';
        echo '<div class="het-muted" style="max-width:980px">Kanibalizacja, klastry tematyczne i struktura serwisu. Best-effort: bez zewnętrznych API, z cache i metrykami z Twoich treści.</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '<nav class="het-tabs" style="margin:12px 0 16px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">';
        foreach ($tabs as $k=>$label) {
            $url = admin_url('admin.php?page=' . self::MENU_SLUG_ARCHITECTURE . '&tab=' . $k);
            $active = ($tab===$k) ? 'style="background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 2px rgba(0,0,0,.04);"' : 'style="background:#f6f7f7;border:1px solid #dcdcde;"';
            echo '<a class="button" ' . $active . ' href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';

        $data = [
            'cannibal' => [],
            'clusters' => [],
            'structure' => [],
        ];
        if ($tab === 'cannibal') {
            $data['cannibal'] = $this->arch->get_cannibalization(80);
        } elseif ($tab === 'clusters') {
            $data['clusters'] = $this->arch->get_clusters(30);
        } else {
            $data['structure'] = $this->arch->get_structure_overview(25);
        }

        include HET_SEO_PATH . 'templates/admin-architecture.php';
        echo '</div>'; // .het-page
        $this->footer_shell();
    }

    public function page_monitoring() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        if (!$this->is_pro()) { $this->render_pro_lock('Monitoring SEO'); return; }
    $this->ensure_safe_schema();

    $tab = isset($_GET['tab']) ? sanitize_key((string)$_GET['tab']) : 'timeline';

    // handle add google update
    if (!empty($_POST['het_seo_add_google_update'])) {
        check_admin_referer('het_seo_add_google_update');
        $date = sanitize_text_field((string)($_POST['date'] ?? ''));
        $title = sanitize_text_field((string)($_POST['title'] ?? ''));
        $type = sanitize_text_field((string)($_POST['type'] ?? 'core'));
        $notes = sanitize_textarea_field((string)($_POST['notes'] ?? ''));
        $this->monitor->add_google_update($date, $title, $type, $notes);
        $this->safe_redirect(add_query_arg(['page'=>self::MENU_SLUG_MONITORING,'tab'=>'updates','added'=>1], admin_url('admin.php')));
        exit;
    }

    $data = [
        'tab' => $tab,
        'history' => $this->datacore->get_history(300),
        'alerts' => $this->alerts->list_events(200),
        'updates' => $this->monitor->list_google_updates(200),
        'delta' => $this->monitor->get_site_delta_summary(),
    ];

    $base = admin_url('admin.php');
    include HET_SEO_PATH . 'templates/admin-monitoring.php';
}

    public function page_settings() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        if (isset($_GET['het_notice']) && $_GET['het_notice'] === 'profile_rebuilt') {
            echo '<div class="notice notice-success"><p>Profil stylu AI został przeliczony.</p></div>';
        }
        if (isset($_POST['het_seo_save_settings']) && check_admin_referer('het_seo_save_settings')) {
            $opts = get_option(HET_SEO_OPT, []);
            // UI prefs (stored separately)
            $ui = get_option('het_seo_ui', []);
            $density = isset($_POST['het_ui_density']) ? sanitize_key($_POST['het_ui_density']) : '';
            $ui['density'] = in_array($density, ['compact','comfortable'], true) ? $density : (isset($ui['density']) ? $ui['density'] : 'compact');
            update_option('het_seo_ui', $ui, false);

            HET_SEO_I18N::set_language_from_post($opts);
            $opts['debug'] = !empty($_POST['debug']) ? 1 : 0;
            $opts['robots_rules'] = isset($_POST['robots_rules']) ? wp_kses_post($_POST['robots_rules']) : '';

            // Social (OG/Twitter)
            $opts['social_enabled'] = !empty($_POST['social_enabled']) ? 1 : 0;
            $opts['social_twitter_card'] = (isset($_POST['social_twitter_card']) && in_array($_POST['social_twitter_card'], ['summary', 'summary_large_image'], true)) ? sanitize_text_field($_POST['social_twitter_card']) : 'summary_large_image';
            $opts['social_default_image'] = isset($_POST['social_default_image']) ? esc_url_raw($_POST['social_default_image']) : '';

            // Schema (JSON-LD)
            $opts['schema_enabled'] = !empty($_POST['schema_enabled']) ? 1 : 0;
            $opts['schema_org_name'] = isset($_POST['schema_org_name']) ? sanitize_text_field($_POST['schema_org_name']) : '';
            $opts['schema_org_logo'] = isset($_POST['schema_org_logo']) ? esc_url_raw($_POST['schema_org_logo']) : '';
            $opts['schema_site_search'] = !empty($_POST['schema_site_search']) ? 1 : 0;

            // SEO patterns (defaults + per post type)
            $opts['pattern_title_default'] = isset($_POST['pattern_title_default']) ? sanitize_text_field($_POST['pattern_title_default']) : '';
            $opts['pattern_desc_default']  = isset($_POST['pattern_desc_default']) ? sanitize_text_field($_POST['pattern_desc_default']) : '';

            // MEGA PACK B: Brand Rules (stored in HET_SEO_OPT)
            $opts['ai_brand_must'] = isset($_POST['ai_brand_must']) ? sanitize_textarea_field($_POST['ai_brand_must']) : '';
            $opts['ai_brand_never'] = isset($_POST['ai_brand_never']) ? sanitize_textarea_field($_POST['ai_brand_never']) : '';
            $opts['ai_brand_syn'] = isset($_POST['ai_brand_syn']) ? sanitize_textarea_field($_POST['ai_brand_syn']) : '';

            // MEGA PACK B: Style Profile (stored in separate option)
            if (class_exists('HET_SEO_AI_Style')) {
                $p = HET_SEO_AI_Style::get_profile();
                $p['enabled'] = !empty($_POST['ai_style_enabled']) ? 1 : 0;
                $tone = isset($_POST['ai_tone']) ? sanitize_key($_POST['ai_tone']) : 'neutral';
                $p['tone'] = in_array($tone, ['neutral','info','sales'], true) ? $tone : 'neutral';
                $sep = isset($_POST['ai_separator']) ? (string)$_POST['ai_separator'] : '—';
                $p['separator'] = in_array($sep, ['—','-','|','·','/'], true) ? $sep : '—';
                $p['title_suffix'] = isset($_POST['ai_title_suffix']) ? sanitize_text_field($_POST['ai_title_suffix']) : '';
                $p['allow_emoji'] = !empty($_POST['ai_allow_emoji']) ? 1 : 0;
                update_option(HET_SEO_AI_Style::OPT_PROFILE, $p, false);
            }

            $public_types = get_post_types(['public'=>true], 'names');
            foreach ($public_types as $pt) {
                if ($pt === 'attachment') { continue; }
                $k1 = 'pattern_title_' . $pt;
                $k2 = 'pattern_desc_' . $pt;
                if (isset($_POST[$k1])) { $opts[$k1] = sanitize_text_field($_POST[$k1]); }
                if (isset($_POST[$k2])) { $opts[$k2] = sanitize_text_field($_POST[$k2]); }
            }
            update_option(HET_SEO_OPT, $opts, false);
            echo '<div class="notice notice-success"><p>Zapisano.</p></div>';
        }
        $this->header_shell('settings');
        $opts = get_option(HET_SEO_OPT, []);
        $ui = get_option('het_seo_ui', []);
        $ui_density = (isset($ui['density']) && in_array($ui['density'], ['compact','comfortable'], true)) ? $ui['density'] : 'compact';
        include HET_SEO_PATH . 'templates/admin-settings.php';
    }

    public function page_license() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        $notice = '';
        if (isset($_POST['het_seo_clear_commercial_data']) && check_admin_referer('het_seo_open_source')) {
            $this->license->clear_activation();
            $notice = '<div class="notice notice-success"><p>Usunięto lokalnie zapisane stare dane komercyjne/tokeny.</p></div>';
        }
        $this->header_shell('license');
        $state = $this->license->get_state();
        include HET_SEO_PATH . 'templates/admin-license.php';
    }

    public function page_ai_auto() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        if (!$this->is_pro()) { $this->render_pro_lock('AI Auto'); return; }

        // Export CSV
        if (isset($_GET['het_seo_ai_auto_export']) && $_GET['het_seo_ai_auto_export'] === '1') {
            HET_SEO_AI_Auto::export_csv();
        }
        // Approvals (semi-auto)
        if (isset($_GET['het_seo_ai_approve']) && $_GET['het_seo_ai_approve'] === '1') {
            check_admin_referer('het_seo_ai_approve');
            $ids = isset($_GET['ids']) ? explode(',', sanitize_text_field($_GET['ids'])) : [];
            $affected = $this->queue->approve_items($ids);
            echo '<div class="notice notice-success"><p>Zatwierdzono: ' . intval($affected) . ' zadań.</p></div>';
        }
        if (isset($_GET['het_seo_ai_approve_all']) && $_GET['het_seo_ai_approve_all'] === '1') {
            check_admin_referer('het_seo_ai_approve_all');
            $rows = $this->queue->list_approvals(500);
            $ids = array_map(function($r){ return $r->id; }, $rows);
            $affected = $this->queue->approve_items($ids);
            echo '<div class="notice notice-success"><p>Zatwierdzono (wszystkie): ' . intval($affected) . ' zadań.</p></div>';
        }

// Save settings
        if (isset($_POST['het_seo_save_ai_auto']) && check_admin_referer('het_seo_save_ai_auto')) {
            $s = HET_SEO_AI_Auto::get_settings();
            $s['enabled'] = !empty($_POST['enabled']) ? 1 : 0;
            $s['panic_stop'] = !empty($_POST['panic_stop']) ? 1 : 0;
            $s['mode'] = (isset($_POST['mode']) && $_POST['mode'] === 'exec') ? 'exec' : 'dry';
            $s['autonomy'] = (isset($_POST['autonomy']) && in_array($_POST['autonomy'], ['manual','semi','auto'], true)) ? $_POST['autonomy'] : 'manual';
            $s['include_ctr'] = !empty($_POST['include_ctr']) ? 1 : 0;
            $s['limit'] = isset($_POST['limit']) ? intval($_POST['limit']) : $s['limit'];
            $s['daily_hour'] = isset($_POST['daily_hour']) ? intval($_POST['daily_hour']) : $s['daily_hour'];
            $s['post_types'] = isset($_POST['post_types']) ? array_map('sanitize_key', (array)$_POST['post_types']) : $s['post_types'];
            $s['allowed_fixes'] = isset($_POST['allowed_fixes']) ? array_map('sanitize_key', (array)$_POST['allowed_fixes']) : $s['allowed_fixes'];
            update_option(HET_SEO_AI_Auto::OPT_KEY, $s, false);
            HET_SEO_AI_Auto::ensure_cron();
            echo '<div class="notice notice-success"><p>Zapisano ustawienia AI Auto.</p></div>';
        }

        $this->header_shell('center');
        $s = HET_SEO_AI_Auto::get_settings();
        $runs = HET_SEO_AI_Auto::list_runs(50);
        $approvals = $this->queue->list_approvals(100);
        $post_types = get_post_types(['public'=>true], 'objects');
        include HET_SEO_PATH . 'templates/admin-ai-auto.php';
    }

    public function page_ai_content() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        if (!$this->is_pro()) { $this->render_pro_lock('Generator treści AI'); return; }
        $this->header_shell('center');
        include HET_SEO_PATH . 'templates/admin-ai-content.php';
    }


    public function page_404() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        $this->header_shell('center');
        $rows = $this->list_404(200);
        include HET_SEO_PATH . 'templates/admin-404.php';
    }

    public function page_robots() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }

        if (isset($_POST['het_seo_save_robots']) && check_admin_referer('het_seo_save_robots')) {
            $opts = get_option(HET_SEO_OPT, []);
            $opts['robots_rules'] = isset($_POST['robots_rules']) ? wp_kses_post($_POST['robots_rules']) : '';
            update_option(HET_SEO_OPT, $opts, false);
            echo '<div class="notice notice-success"><p>Zapisano reguły robots.</p></div>';
        }

        $this->header_shell('center');
        $opts = get_option(HET_SEO_OPT, []);
        $robots_rules = isset($opts['robots_rules']) ? (string)$opts['robots_rules'] : '';

        // Preview (merge WP defaults + custom rules)
        $base = "";
        ob_start();
        do_action('do_robotstxt', true);
        $base = (string) ob_get_clean();
        if (!$base) {
            // fallback: ask WP for its current output
            $base = apply_filters('robots_txt', "User-agent: *\nDisallow:", true);
        }
        $preview = trim($base) . "\n" . trim(wp_strip_all_tags($robots_rules));
        $preview = trim($preview) . "\n";

        include HET_SEO_PATH . 'templates/admin-robots.php';
    }

    public function page_import_export() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }

        // Export settings

        if (isset($_POST['het_seo_export']) && check_admin_referer('het_seo_export')) {
            $opts = get_option(HET_SEO_OPT, []);
            unset($opts['license_key'], $opts['license_token'], $opts['license_token_hash'], $opts['license_activated_at'], $opts['license_expires_at'], $opts['license_plan_code'], $opts['license_duration_months'], $opts['license_tier'], $opts['license_last_sync_at'], $opts['license_last_error']);
            $json = wp_json_encode(['version' => HET_SEO_VER, 'options' => $opts], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename=heterodyna-seo-export-' . HET_SEO_VER . '.json');
            echo $json;
            exit;
        }

        // Export redirects
        if (isset($_POST['het_seo_export_redirects']) && check_admin_referer('het_seo_export_redirects')) {
            $redirects = $this->redir->list_redirects(100000);
            $json = wp_json_encode(['version' => HET_SEO_VER, 'redirects' => $redirects], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename=heterodyna-seo-redirects-' . HET_SEO_VER . '.json');
            echo $json;
            exit;
        }

        // Export 404 log
        if (isset($_POST['het_seo_export_404']) && check_admin_referer('het_seo_export_404')) {
            global $wpdb;
            $table = $wpdb->prefix . 'het_seo_404_log';
            $rows = $wpdb->get_results("SELECT url, referrer, user_agent, hits, first_seen, last_seen FROM {$table} ORDER BY last_seen DESC LIMIT 100000", ARRAY_A);
            $json = wp_json_encode(['version' => HET_SEO_VER, 'log404' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename=heterodyna-seo-404-' . HET_SEO_VER . '.json');
            echo $json;
            exit;
        }

        if (isset($_POST['het_seo_import']) && check_admin_referer('het_seo_import')) {
            $raw = isset($_POST['import_json']) ? wp_unslash($_POST['import_json']) : '';
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                echo '<div class="notice notice-error"><p>Niepoprawny JSON.</p></div>';
            } else {
                $did_any = false;
                if (!empty($decoded['options']) && is_array($decoded['options'])) {
                    $opts = get_option(HET_SEO_OPT, []);
                    $merged = array_merge($opts, $decoded['options']);
                    update_option(HET_SEO_OPT, $merged, false);
                    $did_any = true;
                }
                if (!empty($decoded['redirects']) && is_array($decoded['redirects'])) {
                    foreach ($decoded['redirects'] as $r) {
                        if (is_array($r)) {
                            $from = isset($r['from_path']) ? sanitize_text_field($r['from_path']) : '';
                            $to   = isset($r['to_url']) ? esc_url_raw($r['to_url']) : '';
                            $code = isset($r['code']) ? (int)$r['code'] : 301;
                            if ($from && $to) {
                                $this->redir->add_redirect($from, $to, $code);
                                $did_any = true;
                            }
                        }
                    }
                }
                if (!empty($decoded['log404']) && is_array($decoded['log404'])) {
                    global $wpdb;
                    $table = $wpdb->prefix . 'het_seo_404_log';
                    foreach ($decoded['log404'] as $row) {
                        if (!is_array($row) || empty($row['url'])) { continue; }
                        $url = (string)$row['url'];
                        $hits = isset($row['hits']) ? (int)$row['hits'] : 1;
                        $ref = isset($row['referrer']) ? (string)$row['referrer'] : '';
                        $ua  = isset($row['user_agent']) ? (string)$row['user_agent'] : '';
                        $first = isset($row['first_seen']) ? (string)$row['first_seen'] : current_time('mysql');
                        $last  = isset($row['last_seen']) ? (string)$row['last_seen'] : current_time('mysql');
                        $existing = $wpdb->get_row($wpdb->prepare("SELECT id,hits FROM {$table} WHERE url=%s", $url));
                        if ($existing) {
                            $wpdb->query($wpdb->prepare("UPDATE {$table} SET hits=hits+%d, last_seen=%s, referrer=%s, user_agent=%s WHERE id=%d", max(1,$hits), $last, $ref, $ua, (int)$existing->id));
                        } else {
                            $wpdb->insert($table, [
                                'url' => $url,
                                'referrer' => $ref,
                                'user_agent' => $ua,
                                'hits' => max(1,$hits),
                                'first_seen' => $first,
                                'last_seen' => $last,
                            ], ['%s','%s','%s','%d','%s','%s']);
                        }
                        $did_any = true;
                    }
                }

                if ($did_any) {
                    echo '<div class="notice notice-success"><p>Zaimportowano dane.</p></div>';
                } else {
                    echo '<div class="notice notice-warning"><p>JSON poprawny, ale brak rozpoznanych danych do importu.</p></div>';
                }
            }
        }

        $this->header_shell('center');
        include HET_SEO_PATH . 'templates/admin-import-export.php';
    }

    public function page_tests() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        $this->header_shell('center');
        $base = home_url('/');
        $checks = [
            ['name'=>'REST API (root)', 'url'=>rest_url()],
            ['name'=>'robots.txt (public)', 'url'=>home_url('/robots.txt')],
            ['name'=>'sitemap.xml (public)', 'url'=>home_url('/sitemap.xml')],
            ['name'=>'security.txt (public)', 'url'=>home_url('/.well-known/security.txt')],
        ];
        foreach ($checks as &$c) {
            $r = $this->http_check($c['url']);
            $c['ok'] = $r['ok'];
            $c['code'] = $r['code'];
            $c['detail'] = $r['detail'];
        }
        unset($c);

        // cron info (best-effort)
        $next_psi = wp_next_scheduled('het_seo_cron_psi_daily');
        $next_queue = wp_next_scheduled('het_seo_cron_process_queue');
        $next_alerts = wp_next_scheduled('het_seo_cron_alerts_daily');

        include HET_SEO_PATH . 'templates/admin-tests.php';
    }

    public function page_api() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        if (!$this->is_pro()) { $this->render_pro_lock('API / Automaty'); return; }
        $this->header_shell('center');
        $rest_base = rest_url('heterodyna-seo/v1');
        include HET_SEO_PATH . 'templates/admin-api.php';
    }

    public function page_psi() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        if (!$this->is_pro()) { $this->render_pro_lock('PSI'); return; }
        $this->header_shell('center');
        // actions
        if (isset($_POST['het_seo_psi_save']) && check_admin_referer('het_seo_psi_save')) {
            $opts = get_option(HET_SEO_OPT, []);
            $opts['psi_api_key'] = isset($_POST['psi_api_key']) ? sanitize_text_field($_POST['psi_api_key']) : '';
            $opts['psi_strategy'] = isset($_POST['psi_strategy']) ? sanitize_text_field($_POST['psi_strategy']) : 'mobile';
            update_option(HET_SEO_OPT, $opts, false);
            echo '<div class="notice notice-success"><p>Zapisano ustawienia PSI.</p></div>';
        }
        if (isset($_POST['het_seo_psi_enqueue']) && check_admin_referer('het_seo_psi_enqueue')) {
            $url = isset($_POST['psi_url']) ? esc_url_raw(trim((string)$_POST['psi_url'])) : '';
            $strategy = isset($_POST['psi_strategy_now']) ? sanitize_text_field($_POST['psi_strategy_now']) : 'mobile';
            if (!empty($url)) {
                $this->psi->enqueue_url($url, $strategy, 50);
                echo '<div class="notice notice-success"><p>Dodano do kolejki PSI.</p></div>';
            }
        }
        if (isset($_POST['het_seo_psi_enqueue_sitemap']) && check_admin_referer('het_seo_psi_enqueue_sitemap')) {
            $count = $this->psi->enqueue_from_sitemap();
            echo '<div class="notice notice-success"><p>Dodano z sitemap: ' . intval($count) . ' URL.</p></div>';
        }
        if (isset($_POST['het_seo_psi_run_now']) && check_admin_referer('het_seo_psi_run_now')) {
            $done = $this->psi->process_queue(5);
            echo '<div class="notice notice-info"><p>Przetworzono teraz: ' . intval($done) . '.</p></div>';
        }

        // Queue item actions (retry/delete/clear)
        if (isset($_POST['het_seo_psi_q_action']) && check_admin_referer('het_seo_psi_q_action')) {
            $act = isset($_POST['q_act']) ? sanitize_text_field((string)$_POST['q_act']) : '';
            $id  = isset($_POST['qid']) ? intval($_POST['qid']) : 0;

            if ($act === 'retry' && $id > 0) {
                $this->psi->retry_queue_item($id, true);
                echo '<div class="notice notice-success"><p>Ustawiono ponownie jako pending.</p></div>';
            } elseif ($act === 'delete' && $id > 0) {
                $this->psi->delete_queue_item($id);
                echo '<div class="notice notice-success"><p>Usunięto element z kolejki.</p></div>';
            } elseif ($act === 'clear_done') {
                $n = $this->psi->clear_queue_by_status('done');
                echo '<div class="notice notice-success"><p>Wyczyszczono DONE: ' . intval($n) . '.</p></div>';
            } elseif ($act === 'clear_error') {
                $n = $this->psi->clear_queue_by_status('error');
                echo '<div class="notice notice-success"><p>Wyczyszczono ERROR: ' . intval($n) . '.</p></div>';
            }
        }
        $opts = get_option(HET_SEO_OPT, []);
        $psi_settings = [
            'api_key' => isset($opts['psi_api_key']) ? (string)$opts['psi_api_key'] : '',
            'strategy' => isset($opts['psi_strategy']) ? (string)$opts['psi_strategy'] : 'mobile',
        ];

        // Filters / history view
        $psi_qstatus = isset($_GET['qstatus']) ? sanitize_text_field((string)$_GET['qstatus']) : '';
        $psi_view_url = isset($_GET['url']) ? esc_url_raw((string)wp_unslash($_GET['url'])) : '';
        $psi_view_strategy = isset($_GET['strategy']) ? sanitize_text_field((string)$_GET['strategy']) : '';

        $psi_queue = $this->psi->list_queue_filtered($psi_qstatus, 100);
        $psi_results = $this->psi->list_results(50);

        // Diagnostics (helps explain empty chart)
        $psi_diag = [
            'has_key' => !empty($psi_settings['api_key']),
            'queue_counts' => ['pending'=>0,'running'=>0,'done'=>0,'error'=>0],
            'last_error' => '',
            'results_count' => is_array($psi_results) ? count($psi_results) : 0,
        ];
        if (is_array($psi_queue)) {
            foreach ($psi_queue as $row) {
                $st = isset($row['status']) ? (string)$row['status'] : '';
                if (isset($psi_diag['queue_counts'][$st])) { $psi_diag['queue_counts'][$st]++; }
                if (empty($psi_diag['last_error']) && $st === 'error' && !empty($row['last_error'])) {
                    $psi_diag['last_error'] = (string)$row['last_error'];
                }
            }
        }
        $psi_history = [];
        if (!empty($psi_view_url)) {
            $psi_history = $this->psi->list_results_for_url($psi_view_url, $psi_view_strategy, 60);
        }
        include HET_SEO_PATH . 'templates/admin-psi.php';
    }

    public function page_alerts() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        if (!$this->is_pro()) { $this->render_pro_lock('Alerty'); return; }
        $this->header_shell('center');
        if (isset($_POST['het_seo_alerts_save']) && check_admin_referer('het_seo_alerts_save')) {
            $rules = [
                '404_spike' => [
                    'enabled' => !empty($_POST['rule_404_enabled']) ? 1 : 0,
                    'threshold' => isset($_POST['rule_404_threshold']) ? floatval($_POST['rule_404_threshold']) : 50,
                    'window_hours' => isset($_POST['rule_404_window']) ? intval($_POST['rule_404_window']) : 24,
                ],
                'uptime' => [
                    'enabled' => !empty($_POST['rule_uptime_enabled']) ? 1 : 0,
                    'threshold' => 0,
                ],
                'psi_low' => [
                    'enabled' => !empty($_POST['rule_psi_enabled']) ? 1 : 0,
                    'threshold' => isset($_POST['rule_psi_threshold']) ? floatval($_POST['rule_psi_threshold']) : 50,
                ],
            ];
            $email_to = isset($_POST['alerts_email_to']) ? sanitize_email($_POST['alerts_email_to']) : '';
            $this->alerts->save_rules($rules, $email_to);
            echo '<div class="notice notice-success"><p>Zapisano reguły alertów.</p></div>';
        }
        if (isset($_POST['het_seo_alerts_run_now']) && check_admin_referer('het_seo_alerts_run_now')) {
            $n = $this->alerts->run_checks();
            echo '<div class="notice notice-info"><p>Wykonano sprawdzenie. Nowe zdarzenia: ' . intval($n) . '.</p></div>';
        }
        $alert_rules = $this->alerts->get_rules();
        $alert_events = $this->alerts->list_events(50);
        include HET_SEO_PATH . 'templates/admin-alerts.php';
    }

    public function page_redirects() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        // Settings (smart auto-redirect)
        if (isset($_POST['het_seo_redirect_settings']) && check_admin_referer('het_seo_redirect_settings')) {
            $opts = get_option(HET_SEO_OPT, []);
            $opts['auto_redirect_smart'] = !empty($_POST['auto_redirect_smart']) ? 1 : 0;
            update_option(HET_SEO_OPT, $opts, false);
            echo '<div class="notice notice-success"><p>Zapisano ustawienia przekierowań.</p></div>';
        }

        $this->header_shell('center');
        if (isset($_POST['het_seo_redirect_add']) && check_admin_referer('het_seo_redirect_add')) {
            $from = isset($_POST['from_path']) ? sanitize_text_field($_POST['from_path']) : '';
            $to = isset($_POST['to_url']) ? esc_url_raw($_POST['to_url']) : '';
            $code = isset($_POST['code']) ? intval($_POST['code']) : 301;
            if ($from && $to) {
                $this->redir->add_redirect($from, $to, $code);
                echo '<div class="notice notice-success"><p>Dodano przekierowanie.</p></div>';
            }
        }
        if (isset($_POST['het_seo_redirect_delete']) && check_admin_referer('het_seo_redirect_delete')) {
            $id = isset($_POST['rid']) ? intval($_POST['rid']) : 0;
            if ($id) { $this->redir->delete_redirect($id); }
        }
        $opts = get_option(HET_SEO_OPT, []);
        $redirects = $this->redir->list_redirects(200);
        $prefill_from = '';
        if (isset($_GET['from'])) {
            $prefill_from = sanitize_text_field(wp_unslash((string)$_GET['from']));
        }
        include HET_SEO_PATH . 'templates/admin-redirects.php';
    }

    /**
     * SEO Core: Meta audit (missing/duplicates/length)
     */
    public function page_meta_audit() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        $this->header_shell('center');

        // Bulk actions (enqueue AI tasks)
        if (isset($_POST['het_seo_meta_bulk']) && check_admin_referer('het_seo_meta_bulk')) {
            $action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
            $ids = isset($_POST['post_ids']) && is_array($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : [];
            $dry = !empty($_POST['dry_run']) ? 1 : 0;

            $map = [
                'enqueue_missing' => HET_SEO_AI_Queue::FIX_MISSING_META,
                'enqueue_toolong' => HET_SEO_AI_Queue::FIX_TOO_LONG,
                'enqueue_ctr'     => HET_SEO_AI_Queue::FIX_CTR,
            ];

            if (!empty($ids) && isset($map[$action])) {
                if (!$dry && !$this->license->can_run_write_actions()) {
                    echo '<div class="notice notice-error"><p>Tryb wykonania jest dostępny w buildzie open-source. Odśwież stronę i spróbuj ponownie.</p></div>';
                } else {
                    $fix = $map[$action];
                    $n = 0;
                    foreach ($ids as $pid) {
                        if ($pid > 0) {
                            $this->queue->enqueue_post($pid, $fix, 10, $dry ? true : false);
                            $n++;
                        }
                    }
                    echo '<div class="notice notice-success"><p>Dodano do kolejki: ' . intval($n) . '.</p></div>';
                }
            }
        }

        $post_types = get_post_types(['public'=>true], 'names');
        unset($post_types['attachment']);

        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 200;
        $limit = max(25, min(500, $limit));

        $only_issues = !empty($_GET['issues']) ? 1 : 0;
        $only_missing = !empty($_GET['missing']) ? 1 : 0;
        $only_dupes = !empty($_GET['dupes']) ? 1 : 0;
        $only_toolong = !empty($_GET['toolong']) ? 1 : 0;

        $q = new WP_Query([
            'post_type' => array_values($post_types),
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        ]);

        $rows = [];
        $seen_title = [];
        $seen_desc = [];
        foreach ($q->posts as $p) {
            $t = (string) get_post_meta($p->ID, 'het_seo_meta_title', true);
            $d = (string) get_post_meta($p->ID, 'het_seo_meta_description', true);
            $c = (string) get_post_meta($p->ID, 'het_seo_canonical', true);
            $noindex = (int) get_post_meta($p->ID, 'het_seo_noindex', true);
            $nofollow = (int) get_post_meta($p->ID, 'het_seo_nofollow', true);

            $t_key = strtolower(trim($t));
            $d_key = strtolower(trim($d));
            $dup_t = ($t_key !== '' && isset($seen_title[$t_key]));
            $dup_d = ($d_key !== '' && isset($seen_desc[$d_key]));
            if ($t_key !== '') { $seen_title[$t_key] = true; }
            if ($d_key !== '') { $seen_desc[$d_key] = true; }

            $rows[] = [
                'ID' => $p->ID,
                'type' => $p->post_type,
                'title' => get_the_title($p),
                'edit' => get_edit_post_link($p->ID, ''),
                'permalink' => get_permalink($p),
                'meta_title' => $t,
                'meta_desc' => $d,
                'len_t' => strlen(wp_strip_all_tags($t)),
                'len_d' => strlen(wp_strip_all_tags($d)),
                'dup_t' => $dup_t,
                'dup_d' => $dup_d,
                'canonical' => $c,
                'noindex' => $noindex,
                'nofollow' => $nofollow,
            ];
        }

        // Apply filters (client-side would be faster, but keep server-side for simplicity)
        if ($only_issues || $only_missing || $only_dupes || $only_toolong) {
            $rows = array_values(array_filter($rows, function($r) use ($only_issues, $only_missing, $only_dupes, $only_toolong) {
                $is_missing = ($r['len_t'] === 0 || $r['len_d'] === 0);
                $is_dupe = (!empty($r['dup_t']) || !empty($r['dup_d']));
                $is_toolong = ($r['len_t'] > 70 || $r['len_d'] > 165);
                if ($only_issues && !($is_missing || $is_dupe || $is_toolong)) { return false; }
                if ($only_missing && !$is_missing) { return false; }
                if ($only_dupes && !$is_dupe) { return false; }
                if ($only_toolong && !$is_toolong) { return false; }
                return true;
            }));
        }

        include HET_SEO_PATH . 'templates/admin-meta-audit.php';
    }

    /**
     * SEO Core: Canonical + indexing settings
     */
    public function page_canonical() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }

        if (isset($_POST['het_seo_save_canon']) && check_admin_referer('het_seo_save_canon')) {
            $opts = get_option(HET_SEO_OPT, []);
            $opts['default_canonical_mode'] = isset($_POST['default_canonical_mode']) ? sanitize_text_field($_POST['default_canonical_mode']) : 'off';
            $opts['noindex_archives'] = !empty($_POST['noindex_archives']) ? 1 : 0;
            $master_arch = !empty($_POST['noindex_archives']);
            $opts['noindex_categories'] = ($master_arch || !empty($_POST['noindex_categories'])) ? 1 : 0;
            $opts['noindex_tags'] = ($master_arch || !empty($_POST['noindex_tags'])) ? 1 : 0;
            $opts['noindex_author'] = ($master_arch || !empty($_POST['noindex_author'])) ? 1 : 0;
            $opts['noindex_date'] = ($master_arch || !empty($_POST['noindex_date'])) ? 1 : 0;

            $opts['noindex_paged'] = !empty($_POST['noindex_paged']) ? 1 : 0;
            $opts['noindex_params'] = !empty($_POST['noindex_params']) ? 1 : 0;
            $opts['noindex_search'] = !empty($_POST['noindex_search']) ? 1 : 0;
            $opts['noindex_whitelist_paths'] = isset($_POST['noindex_whitelist_paths']) ? sanitize_textarea_field($_POST['noindex_whitelist_paths']) : '';
            update_option(HET_SEO_OPT, $opts, false);
            echo '<div class="notice notice-success"><p>Zapisano.</p></div>';
        }

        // Bulk apply per post type (noindex/nofollow)
        if (isset($_POST['het_seo_bulk_apply']) && check_admin_referer('het_seo_bulk_apply')) {
            $pt = isset($_POST['bulk_post_type']) ? sanitize_text_field($_POST['bulk_post_type']) : '';
            $noindex = !empty($_POST['bulk_noindex']) ? 1 : 0;
            $nofollow = !empty($_POST['bulk_nofollow']) ? 1 : 0;
            $limit = isset($_POST['bulk_limit']) ? (int)$_POST['bulk_limit'] : 0;
            $done = $this->bulk_apply_robots_to_post_type($pt, $noindex, $nofollow, $limit);
            echo '<div class="notice notice-success"><p>Masowo ustawiono meta robots dla typu <code>' . esc_html($pt) . '</code>. Zmieniono: <strong>' . intval($done) . '</strong> wpisów.</p></div>';
        }

        $this->header_shell('center');
        $opts = get_option(HET_SEO_OPT, []);
        $public_types = get_post_types(['public'=>true], 'objects');
        unset($public_types['attachment']);
        include HET_SEO_PATH . 'templates/admin-canonical.php';
    }

    private function bulk_apply_robots_to_post_type($post_type, $noindex, $nofollow, $limit = 0) {
        $post_type = (string)$post_type;
        if ($post_type === '' || !post_type_exists($post_type)) { return 0; }
        $limit = (int)$limit;
        if ($limit <= 0) { $limit = 5000; }
        $q = new WP_Query([
            'post_type' => $post_type,
            'post_status' => ['publish','private','future','draft','pending'],
            'fields' => 'ids',
            'posts_per_page' => min(1000, $limit),
            'no_found_rows' => true,
        ]);
        $count = 0;
        foreach ($q->posts as $id) {
            if ($count >= $limit) { break; }
            if ($noindex) { update_post_meta($id, 'het_seo_noindex', 1); } else { delete_post_meta($id, 'het_seo_noindex'); }
            if ($nofollow) { update_post_meta($id, 'het_seo_nofollow', 1); } else { delete_post_meta($id, 'het_seo_nofollow'); }
            $count++;
        }
        return $count;
    }

    /**
     * SEO Core: Sitemap settings
     */
    public function page_sitemap() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        if (isset($_POST['het_seo_save_sitemap']) && check_admin_referer('het_seo_save_sitemap')) {
            $opts = get_option(HET_SEO_OPT, []);
            $opts['sitemap_enabled'] = !empty($_POST['sitemap_enabled']) ? 1 : 0;
            $types = isset($_POST['sitemap_post_types']) && is_array($_POST['sitemap_post_types']) ? array_map('sanitize_text_field', $_POST['sitemap_post_types']) : [];
            $opts['sitemap_post_types'] = array_values(array_unique($types));
            update_option(HET_SEO_OPT, $opts, false);
            echo '<div class="notice notice-success"><p>Zapisano.</p></div>';
        }
        $this->header_shell('center');
        $opts = get_option(HET_SEO_OPT, []);
        $public_types = get_post_types(['public'=>true], 'objects');
        unset($public_types['attachment']);
        include HET_SEO_PATH . 'templates/admin-sitemap.php';
    }

    /**
     * Serve virtual sitemap.xml (simple)
     */
    public function maybe_output_sitemap() {
        if (is_admin()) { return; }
        $req = $_SERVER['REQUEST_URI'] ?? '';
        if (stripos($req, 'sitemap.xml') === false) { return; }
        // only handle exact /sitemap.xml (with optional query)
        $path = strtok($req, '?');
        $path = rtrim($path, '/');
        if ($path !== '/sitemap.xml') { return; }

        $opts = get_option(HET_SEO_OPT, []);
        if (empty($opts['sitemap_enabled'])) { return; }

        $types = isset($opts['sitemap_post_types']) && is_array($opts['sitemap_post_types']) ? $opts['sitemap_post_types'] : ['post','page'];
        $types = array_values(array_filter($types, function($t){ return post_type_exists($t); }));
        if (empty($types)) { $types = ['post','page']; }

        header('Content-Type: application/xml; charset=utf-8');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $q = new WP_Query([
            'post_type' => $types,
            'posts_per_page' => 1000,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        ]);
        foreach ($q->posts as $p) {
            $loc = get_permalink($p);
            $lastmod = mysql2date('c', $p->post_modified_gmt ? $p->post_modified_gmt : $p->post_modified);
            echo "  <url>\n";
            echo "    <loc>" . esc_url($loc) . "</loc>\n";
            echo "    <lastmod>" . esc_html($lastmod) . "</lastmod>\n";
            echo "  </url>\n";
        }
        echo "</urlset>";
        exit;
    }

    /**
     * Robots.txt virtual overrides
     */
    public function filter_robots_txt($output, $public) {
        $opts = get_option(HET_SEO_OPT, []);
        $rules = isset($opts['robots_rules']) ? trim((string)$opts['robots_rules']) : '';
        if ($rules === '') {
            return $output;
        }
        // Append, don't nuke defaults
        return $output . "\n\n# Heterodyna SEO\n" . $rules . "\n";
    }

    /**
     * Global robots rules (archives/search)
     */
    public function output_global_robots() {
        if (is_admin()) { return; }
        $opts = get_option(HET_SEO_OPT, []);

        // Global noindex whitelist (paths)
        $path = isset($_SERVER['REQUEST_URI']) ? (string) wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
        $path = $path ?: '/';
        $wl_raw = isset($opts['noindex_whitelist_paths']) ? (string)$opts['noindex_whitelist_paths'] : '';
        $wl = array_filter(array_map('trim', preg_split('/\R/u', $wl_raw)));
        foreach ($wl as $rule) {
            $is_prefix = false;
            if (substr($rule, -1) === '*') {
                $is_prefix = true;
                $rule = rtrim($rule, "*");
            }
            if ($rule === '') { continue; }
            if ($is_prefix) {
                if (strpos($path, $rule) === 0) { return; }
            } else {
                if ($path === $rule) { return; }
            }
        }

        $robots = [];
        if (!empty($opts['noindex_search']) && is_search()) {
            $robots[] = 'noindex';
        }
        $master_arch = !empty($opts['noindex_archives']);
        if (($master_arch || !empty($opts['noindex_categories'])) && is_category()) { $robots[] = 'noindex'; }
        if (($master_arch || !empty($opts['noindex_tags'])) && is_tag()) { $robots[] = 'noindex'; }
        if (($master_arch || !empty($opts['noindex_author'])) && is_author()) { $robots[] = 'noindex'; }
        if (($master_arch || !empty($opts['noindex_date'])) && is_date()) { $robots[] = 'noindex'; }
        if (!empty($opts['noindex_paged']) && is_paged()) {
            $robots[] = 'noindex';
        }
        if (!empty($opts['noindex_params']) && !empty($_GET)) {
            // keep it simple: any query params trigger noindex (use whitelist if needed)
            $robots[] = 'noindex';
        }
        if (!empty($robots)) {
            echo "\n<meta name=\"robots\" content=\"" . esc_attr(implode(',', array_unique($robots))) . "\">";
        }
    }

    /**
     * Minimal meta output (your theme can override).
     */
    public function output_meta_tags() {
        if (is_admin()) { return; }
        $opts = get_option(HET_SEO_OPT, []);

        // Term archives: allow canonical/noindex per term via term meta (used by MEGA PACK C suggestions)
        if (!is_singular() && (is_category() || is_tag() || is_tax())) {
            $term = get_queried_object();
            if ($term && $term instanceof WP_Term) {
                $c = (string) get_term_meta($term->term_id, 'het_seo_canonical', true);
                $noindex = (int) get_term_meta($term->term_id, 'het_seo_noindex', true);
                if ($c) { echo "\n<link rel=\"canonical\" href=\"" . esc_url($c) . "\">"; }
                if ($noindex) { echo "\n<meta name=\"robots\" content=\"noindex\">"; }
            }
            return;
        }

        if (!is_singular()) { return; }
        global $post;
        if (!$post) { return; }
        $t = get_post_meta($post->ID, 'het_seo_meta_title', true);
        $d = get_post_meta($post->ID, 'het_seo_meta_description', true);
        $c = get_post_meta($post->ID, 'het_seo_canonical', true);
        $noindex = (int) get_post_meta($post->ID, 'het_seo_noindex', true);
        $nofollow = (int) get_post_meta($post->ID, 'het_seo_nofollow', true);

        // Apply patterns if empty (safe defaults)
        $pt = get_post_type($post);
        $site = get_bloginfo('name');
        $sep  = '—';
        $date = get_the_date('', $post);
        $excerpt = trim(wp_strip_all_tags(get_the_excerpt($post)));
        if ($excerpt === '') {
            $excerpt = trim(wp_strip_all_tags(wp_trim_words($post->post_content ?? '', 30, '')));
        }
        $p_title = isset($opts['pattern_title_' . $pt]) ? (string)$opts['pattern_title_' . $pt] : '';
        $p_desc  = isset($opts['pattern_desc_' . $pt]) ? (string)$opts['pattern_desc_' . $pt] : '';
        if ($p_title === '') { $p_title = isset($opts['pattern_title_default']) ? (string)$opts['pattern_title_default'] : ''; }
        if ($p_desc === '')  { $p_desc  = isset($opts['pattern_desc_default']) ? (string)$opts['pattern_desc_default'] : ''; }
        if ($p_title === '') { $p_title = '{title} {sep} {site}'; }
        if ($p_desc === '')  { $p_desc  = '{excerpt}'; }
        $replace = [
            '{title}' => get_the_title($post),
            '{site}' => $site,
            '{sep}' => $sep,
            '{date}' => $date,
            '{excerpt}' => $excerpt,
        ];
        if (!$t) {
            $t = strtr($p_title, $replace);
            $t = trim(preg_replace('/\s+/', ' ', $t));
        }
        if (!$d) {
            $d = strtr($p_desc, $replace);
            $d = trim(preg_replace('/\s+/', ' ', $d));
        }

        if ($t) { echo "\n<meta name=\"title\" content=\"" . esc_attr($t) . "\">"; }
        if ($d) { echo "\n<meta name=\"description\" content=\"" . esc_attr(mb_substr($d, 0, 320)) . "\">"; }
        if ($c) { echo "\n<link rel=\"canonical\" href=\"" . esc_url($c) . "\">"; }

        // robots (basic)
        $robots = [];
        if ($noindex) { $robots[] = 'noindex'; }
        if ($nofollow) { $robots[] = 'nofollow'; }
        if (!empty($robots)) {
            echo "\n<meta name=\"robots\" content=\"" . esc_attr(implode(',', $robots)) . "\">";
        }

        // Social base values (also used in schema)
        $og_title = $t ? $t : get_the_title($post);
        $og_desc  = $d ? $d : '';

        // Social meta (OG/Twitter) — minimal & safe
        if (!empty($opts['social_enabled'])) {
            $og_url   = $c ? $c : get_permalink($post);

            $img = '';
            if (has_post_thumbnail($post)) {
                $thumb = get_the_post_thumbnail_url($post, 'full');
                if ($thumb) { $img = $thumb; }
            }
            if (!$img && !empty($opts['social_default_image'])) {
                $img = $opts['social_default_image'];
            }

            echo "\n<meta property=\"og:type\" content=\"article\">";
            echo "\n<meta property=\"og:title\" content=\"" . esc_attr($og_title) . "\">";
            if ($og_desc) { echo "\n<meta property=\"og:description\" content=\"" . esc_attr(mb_substr($og_desc, 0, 320)) . "\">"; }
            echo "\n<meta property=\"og:url\" content=\"" . esc_url($og_url) . "\">";
            echo "\n<meta property=\"og:site_name\" content=\"" . esc_attr(get_bloginfo('name')) . "\">";
            if ($img) { echo "\n<meta property=\"og:image\" content=\"" . esc_url($img) . "\">"; }

            $tw_card = !empty($opts['social_twitter_card']) ? $opts['social_twitter_card'] : 'summary_large_image';
            if ($tw_card === 'summary_large_image' && !$img) { $tw_card = 'summary'; }
            echo "\n<meta name=\"twitter:card\" content=\"" . esc_attr($tw_card) . "\">";
            echo "\n<meta name=\"twitter:title\" content=\"" . esc_attr($og_title) . "\">";
            if ($og_desc) { echo "\n<meta name=\"twitter:description\" content=\"" . esc_attr(mb_substr($og_desc, 0, 320)) . "\">"; }
            if ($img) { echo "\n<meta name=\"twitter:image\" content=\"" . esc_url($img) . "\">"; }
        }

        // Schema JSON-LD (Organization, Website, Article, Breadcrumbs)
        if (!empty($opts['schema_enabled'])) {
            $graph = [];
            $home = home_url('/');
            $site_name = get_bloginfo('name');
            $org_name = !empty($opts['schema_org_name']) ? $opts['schema_org_name'] : $site_name;
            $org_id = trailingslashit($home) . '#organization';

            $org = [
                '@type' => 'Organization',
                '@id' => $org_id,
                'name' => $org_name,
                'url' => $home,
            ];
            if (!empty($opts['schema_org_logo'])) {
                $org['logo'] = [
                    '@type' => 'ImageObject',
                    'url' => esc_url_raw($opts['schema_org_logo']),
                ];
            }
            $graph[] = $org;

            $website = [
                '@type' => 'WebSite',
                '@id' => trailingslashit($home) . '#website',
                'url' => $home,
                'name' => $site_name,
                'publisher' => ['@id' => $org_id],
            ];
            if (!empty($opts['schema_site_search'])) {
                $website['potentialAction'] = [[
                    '@type' => 'SearchAction',
                    'target' => add_query_arg('s', '{search_term_string}', $home),
                    'query-input' => 'required name=search_term_string',
                ]];
            }
            $graph[] = $website;

            // Article schema for singular
            $img_url = '';
            if (has_post_thumbnail($post)) {
                $thumb = get_the_post_thumbnail_url($post, 'full');
                if ($thumb) { $img_url = $thumb; }
            }
            if (!$img_url && !empty($opts['social_default_image'])) {
                $img_url = $opts['social_default_image'];
            }

            $article = [
                '@type' => (get_post_type($post) === 'page') ? 'WebPage' : 'Article',
                '@id' => ($c ? $c : get_permalink($post)) . '#primary',
                'mainEntityOfPage' => ($c ? $c : get_permalink($post)),
                'headline' => wp_strip_all_tags($og_title),
                'description' => wp_strip_all_tags($og_desc),
                'datePublished' => get_the_date('c', $post),
                'dateModified' => get_the_modified_date('c', $post),
                'author' => [
                    '@type' => 'Person',
                    'name' => get_the_author_meta('display_name', $post->post_author),
                ],
                'publisher' => ['@id' => $org_id],
            ];
            if ($img_url) {
                $article['image'] = [ $img_url ];
            }
            $graph[] = $article;

            // Breadcrumbs (basic)
            $crumbs = [];
            $pos = 1;
            $crumbs[] = ['@type'=>'ListItem','position'=>$pos++,'name'=>$site_name,'item'=>$home];
            $pt_obj = get_post_type_object(get_post_type($post));
            if ($pt_obj && !empty($pt_obj->has_archive) && get_post_type($post) !== 'page') {
                $archive = get_post_type_archive_link(get_post_type($post));
                if ($archive) {
                    $crumbs[] = ['@type'=>'ListItem','position'=>$pos++,'name'=>$pt_obj->labels->name,'item'=>$archive];
                }
            }
            // If post in category, add first category
            $cats = get_the_category($post->ID);
            if (!empty($cats) && is_array($cats)) {
                $cat = $cats[0];
                $crumbs[] = ['@type'=>'ListItem','position'=>$pos++,'name'=>$cat->name,'item'=>get_category_link($cat)];
            }
            $crumbs[] = ['@type'=>'ListItem','position'=>$pos++,'name'=>get_the_title($post),'item'=>($c ? $c : get_permalink($post))];
            $graph[] = [
                '@type' => 'BreadcrumbList',
                '@id' => ($c ? $c : get_permalink($post)) . '#breadcrumbs',
                'itemListElement' => $crumbs,
            ];

            $data = [
                '@context' => 'https://schema.org',
                '@graph' => $graph,
            ];
            echo "\n<script type=\"application/ld+json\">" . wp_json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . "</script>";
        }
    }

    /**
     * 404 monitor
     */
    private static function install_404_table() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . 'het_seo_404_log';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            url TEXT NOT NULL,
            referrer TEXT NULL,
            user_agent TEXT NULL,
            hits INT NOT NULL DEFAULT 1,
            first_seen DATETIME NOT NULL,
            last_seen DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY last_seen (last_seen)
        ) $charset;";
        dbDelta($sql);
    }

    public function capture_404() {
        if (!is_404()) { return; }
        if (is_admin()) { return; }

        global $wpdb;
        $table = $wpdb->prefix . 'het_seo_404_log';
        $url = (is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $now = current_time('mysql');

        // naive de-dup by url
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE url = %s", $url));
        if ($row) {
            $wpdb->query($wpdb->prepare("UPDATE $table SET hits=hits+1, last_seen=%s, referrer=%s, user_agent=%s WHERE id=%d", $now, $ref, $ua, intval($row->id)));
        } else {
            $wpdb->insert($table, [
                'url' => $url,
                'referrer' => $ref,
                'user_agent' => $ua,
                'hits' => 1,
                'first_seen' => $now,
                'last_seen' => $now,
            ], ['%s','%s','%s','%d','%s','%s']);
        }
    }

    private function list_404($limit = 200) {
        global $wpdb;
        $table = $wpdb->prefix . 'het_seo_404_log';
        $limit = max(1, min(500, intval($limit)));
        return $wpdb->get_results("SELECT * FROM $table ORDER BY last_seen DESC LIMIT $limit");
    }

    public function page_duplicates() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        $this->header_shell('center');
        include HET_SEO_PATH . 'templates/admin-duplicates.php';
        $this->footer_shell();
    }

    public function page_insights() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        if (!$this->is_pro()) { $this->render_pro_lock('Analiza konfliktów SEO'); return; }
        $this->header_shell('center');
        include HET_SEO_PATH . 'templates/admin-insights.php';
        $this->footer_shell();
    }


    public function page_site_mode() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        $this->header_shell('settings');

        $opts = get_option(HET_SEO_OPT, []);
        $allowed = ['blog', 'shop', 'catalog', 'portal', 'card'];

        if (!empty($_POST['het_seo_site_mode_save']) && check_admin_referer('het_seo_site_mode_save')) {
            $mode = isset($_POST['site_mode']) ? sanitize_text_field(wp_unslash($_POST['site_mode'])) : 'blog';
            if (!in_array($mode, $allowed, true)) { $mode = 'blog'; }
            $opts['site_mode'] = $mode;
            update_option(HET_SEO_OPT, $opts);
            echo '<div class="notice notice-success"><p>Zapisano tryb witryny.</p></div>';
        }

        $current_mode = isset($opts['site_mode']) ? (string)$opts['site_mode'] : 'blog';
        if (!in_array($current_mode, $allowed, true)) { $current_mode = 'blog'; }

        $modes = [
            'blog' => [
                'label' => 'Blog / content',
                'desc'  => 'Artykuły, poradniki, evergreen. Priorytet: indeksowanie treści i rich snippets.',
                'recs'  => [
                    'Meta title/description: unikalne dla wpisów i kategorii',
                    'Index: archiwa tagów według jakości (często noindex)',
                    'Sitemap: wpisy + kategorie, ogranicz śmieciowe taksonomie',
                    'Robots: blokuj parametry śledzące (utm, fbclid)',
                ],
            ],
            'shop' => [
                'label' => 'Sklep (WooCommerce)',
                'desc'  => 'Produkty i kategorie. Priorytet: kanoniczne, paginacja, parametry filtrów.',
                'recs'  => [
                    'Canonical: filtry/parametry → kanoniczne na kategorie (lub noindex)',
                    'Index: koszyk/checkout/moje konto = noindex',
                    'Sitemap: produkty + kategorie; wyłącz tagi produktów jeśli generują duplikaty',
                    'Robots: blokuj parametry filtrów (np. ?filter=, ?orderby=) jeśli masz dużo kombinacji',
                ],
            ],
            'catalog' => [
                'label' => 'Katalog / ogłoszenia',
                'desc'  => 'Dużo podstron ofert. Priorytet: powtarzalność szablonów + kontrola indeksowania filtrów.',
                'recs'  => [
                    'Canonical: strony filtrów i sortowania → kanoniczne lub noindex',
                    'Index: archiwa autora i wyszukiwania często noindex',
                    'Sitemap: oferty + kategorie; pilnuj limitów (podział sitemap)',
                    'Robots: blokuj parametry (sort, view, map)',
                ],
            ],
            'portal' => [
                'label' => 'Portal / forum',
                'desc'  => 'Dużo stron użytkowników i paginacji. Priorytet: noindex profili, kontrola crawl budget.',
                'recs'  => [
                    'Index: profile użytkowników / ustawienia / logowanie = noindex',
                    'Canonical: paginacja wątków — spójne kanoniczne',
                    'Sitemap: tylko wartościowe sekcje (wątki, kategorie)',
                    'Robots: blokuj parametry stron profili i wyszukiwania wewnętrznego',
                ],
            ],
            'card' => [
                'label' => 'Wizytówka / landing',
                'desc'  => 'Mało podstron. Priorytet: perfekcyjna strona główna + kontakt + schema.',
                'recs'  => [
                    'Index: praktycznie wszystko index (poza panelami)',
                    'Sitemap: minimalna (home + kilka podstron)',
                    'Robots: proste reguły, bez agresywnych blokad',
                    'Meta: dopracuj tytuł, opis, OG i schema (Organization/LocalBusiness)',
                ],
            ],
        ];

        include HET_SEO_PATH . 'templates/admin-site-mode.php';
        $this->footer_shell();
    }




    public function page_security() {
    if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
    if (isset($_POST['het_seo_save_security']) && check_admin_referer('het_seo_save_security')) {
        $opts = get_option(HET_SEO_OPT, []);
        $opts['security_txt_enabled'] = !empty($_POST['security_txt_enabled']) ? 1 : 0;
        $contact = isset($_POST['security_txt_contact']) ? sanitize_textarea_field(wp_unslash($_POST['security_txt_contact'])) : '';
        $expires = isset($_POST['security_txt_expires']) ? sanitize_text_field(wp_unslash($_POST['security_txt_expires'])) : '';
        $opts['security_txt_contact'] = $contact;
        $opts['security_txt_expires'] = $expires;
        update_option(HET_SEO_OPT, $opts, false);
        echo '<div class="notice notice-success"><p>Zapisano.</p></div>';
    }
    $this->header_shell('center');
    $opts = get_option(HET_SEO_OPT, []);
    include HET_SEO_PATH . 'templates/admin-security.php';
    $this->footer_shell();
}


public function page_sysfiles() {
    if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
    $this->header_shell('center');
    $opts = get_option(HET_SEO_OPT, []);
    $urls = [
        'robots.txt' => home_url('/robots.txt'),
        'sitemap.xml' => home_url('/sitemap.xml'),
        'wp-sitemap.xml' => home_url('/wp-sitemap.xml'),
        'security.txt' => home_url('/.well-known/security.txt'),
    ];
    $checks = [];
    foreach ($urls as $k => $u) {
        $checks[$k] = $this->http_check($u);
    }
    include HET_SEO_PATH . 'templates/admin-sysfiles.php';
    $this->footer_shell();
}


public function page_integrations() {
    if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
    if (!$this->is_pro()) { $this->render_pro_lock('Integracje'); return; }
    $this->header_shell('center');

    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'providers';

    // Site Kit best-effort
    $sitekit_active = function_exists('is_plugin_active') ? is_plugin_active('google-site-kit/google-site-kit.php') : (file_exists(WP_PLUGIN_DIR . '/google-site-kit/google-site-kit.php') && is_plugin_active('google-site-kit/google-site-kit.php'));
    $sitekit_opts = [];
    $known = [
        'googlesitekit_active_modules',
        'googlesitekit_authentication',
        'googlesitekit_connected_proxy_url',
        'googlesitekit_user_settings',
        'googlesitekit_site_info',
    ];
    foreach ($known as $k) {
        $v = get_option($k, null);
        if (!is_null($v)) { $sitekit_opts[$k] = $v; }
    }

    $providers = $this->integrations->get_providers_overview();
    $provider_detail = null;
    if ($tab === 'provider' && !empty($_GET['provider'])) {
        $provider_key = sanitize_key($_GET['provider']);
        $provider_detail = $this->integrations->get_provider_settings($provider_key);
        $provider_detail['key'] = $provider_key;
    }

    $keywords = [];
    $keywords_last = [];
    if ($tab === 'rankings') {
        $keywords = $this->rank->get_keywords(250);
        foreach ($keywords as $kw) {
            $last = $this->rank->get_last_position_for_kw((int)$kw['id']);
            $keywords_last[(int)$kw['id']] = $last;
        }
    }

    $data = [
        'tab' => $tab,
        'providers' => $providers,
        'provider_detail' => $provider_detail,
        'site_kit_active' => $sitekit_active,
        'site_kit_options' => $sitekit_opts,
        'keywords' => $keywords,
        'keywords_last' => $keywords_last,
    ];

    include HET_SEO_PATH . 'templates/admin-integrations.php';
    $this->footer_shell();
}



    public function page_docs() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        $this->header_shell('center');
        include HET_SEO_PATH . 'templates/admin-docs.php';
        $this->footer_shell();
    }

    
    public function handle_serp_add_kw() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień.'); }
        if (!$this->is_pro()) { wp_die('Ta funkcja jest dostępna w buildzie open-source. Odśwież stronę i spróbuj ponownie.'); }
        check_admin_referer('het_seo_serp_add_kw');

        $keyword = isset($_POST['keyword']) ? sanitize_text_field(wp_unslash($_POST['keyword'])) : '';
        $locale  = isset($_POST['locale']) ? sanitize_text_field(wp_unslash($_POST['locale'])) : 'pl-PL';
        $device  = isset($_POST['device']) ? sanitize_key(wp_unslash($_POST['device'])) : 'mobile';

        $id = $this->serp->add_keyword($keyword, $locale, $device);
        $url = admin_url('admin.php?page=' . self::MENU_SLUG_SERP_INTEL . '&tab=serp&kw=' . (int)$id);
        $this->safe_redirect($url);
        exit;
    }

    public function handle_serp_import() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień.'); }
        if (!$this->is_pro()) { wp_die('Ta funkcja jest dostępna w buildzie open-source. Odśwież stronę i spróbuj ponownie.'); }
        check_admin_referer('het_seo_serp_import');

        $keyword_id = isset($_POST['keyword_id']) ? (int)$_POST['keyword_id'] : 0;
        $payload = isset($_POST['payload']) ? (string)wp_unslash($_POST['payload']) : '';

        $res = $this->serp->import_serp_payload($keyword_id, $payload);
        $tab = 'serp';
        $url = admin_url('admin.php?page=' . self::MENU_SLUG_SERP_INTEL . '&tab=' . $tab . '&kw=' . (int)$keyword_id . '&msg=' . rawurlencode($res['msg']));
        $this->safe_redirect($url);
        exit;
    }


public function handle_export_csv() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        $type = isset($_GET['type']) ? sanitize_key($_GET['type']) : '';
        check_admin_referer('het_seo_export_csv_' . $type);

        @set_time_limit(60);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=heterodyna-seo-' . $type . '-' . date('Ymd-His') . '.csv');

        $out = fopen('php://output', 'w');
        if (!$out) { wp_die('Nie można wygenerować CSV'); }

        // UTF-8 BOM for Excel
        fwrite($out, "ï»¿");

        global $wpdb;

        if ($type === '404') {
            fputcsv($out, ['url','referrer','hits','first_seen','last_seen']);
            $table = $wpdb->prefix . 'het_seo_404_log';
            $rows = $wpdb->get_results("SELECT url, referrer, hits, first_seen, last_seen FROM $table ORDER BY last_seen DESC LIMIT 5000", ARRAY_A);
            foreach ($rows as $r) { fputcsv($out, $r); }

        } elseif ($type === 'redirects') {
            fputcsv($out, ['from_path','to_url','code','hits','last_hit','is_enabled']);
            $table = $wpdb->prefix . 'het_seo_redirects';
            $rows = $wpdb->get_results("SELECT from_path, to_url, code, hits, last_hit, is_enabled FROM $table ORDER BY last_hit DESC LIMIT 5000", ARRAY_A);
            foreach ($rows as $r) { fputcsv($out, $r); }

        } elseif ($type === 'meta') {
            fputcsv($out, ['id','url','post_type','title','description','canonical','noindex','nofollow']);
            $post_types = get_post_types(['public'=>true], 'names');
            unset($post_types['attachment']);
            $q = new WP_Query([
                'post_type' => array_values($post_types),
                'posts_per_page' => 5000,
                'post_status' => 'publish',
                'orderby' => 'date',
                'order' => 'DESC',
                'no_found_rows' => true,
            ]);
            foreach ($q->posts as $p) {
                $row = [
                    $p->ID,
                    get_permalink($p->ID),
                    $p->post_type,
                    (string) get_post_meta($p->ID,'het_seo_meta_title',true),
                    (string) get_post_meta($p->ID,'het_seo_meta_description',true),
                    (string) get_post_meta($p->ID,'het_seo_canonical',true),
                    (int) get_post_meta($p->ID,'het_seo_noindex',true),
                    (int) get_post_meta($p->ID,'het_seo_nofollow',true),
                ];
                fputcsv($out, $row);
            }

        } elseif ($type === 'score') {
            fputcsv($out, ['metric','value']);
            $total_posts = 0;
            foreach (get_post_types(['public'=>true],'names') as $pt) {
                if ($pt === 'attachment') { continue; }
                $c = wp_count_posts($pt);
                $total_posts += (int) ($c->publish ?? 0);
            }
            fputcsv($out, ['published_public_posts', $total_posts]);
            $table404 = $wpdb->prefix . 'het_seo_404_log';
            $count404 = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table404");
            fputcsv($out, ['404_rows', $count404]);
            $tableR = $wpdb->prefix . 'het_seo_redirects';
            $countR = (int) $wpdb->get_var("SELECT COUNT(*) FROM $tableR");
            fputcsv($out, ['redirect_rows', $countR]);

        } else {
            fputcsv($out, ['error','unknown_type']);
        }

        fclose($out);
        exit;
    }

    /**
     * MEGA PACK B: Rebuild style profile from existing content.
     */
    public function handle_rebuild_style_profile() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        check_admin_referer('het_seo_rebuild_style_profile');
        if (!class_exists('HET_SEO_AI_Style')) {
            $this->safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG_SETTINGS));
            exit;
        }
        $types = get_post_types(['public'=>true], 'names');
        unset($types['attachment']);
        $types = array_values($types);

        HET_SEO_AI_Style::rebuild_profile($types, 200);

        $this->safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG_SETTINGS . '&het_notice=profile_rebuilt'));
        exit;
    }


    public function maybe_output_security_txt() {
        if (is_admin()) { return; }
        $req = $_SERVER['REQUEST_URI'] ?? '';
        if (stripos($req, 'security.txt') === false) { return; }
        $path = strtok($req, '?');
        $path = rtrim($path, '/');
        if ($path !== '/.well-known/security.txt') { return; }
    
        $opts = get_option(HET_SEO_OPT, []);
        if (empty($opts['security_txt_enabled'])) { return; }
    
        $host = parse_url(home_url(), PHP_URL_HOST);
        $contact = isset($opts['security_txt_contact']) ? (string)$opts['security_txt_contact'] : ('mailto:security@' . $host);
        $expires = isset($opts['security_txt_expires']) ? trim((string)$opts['security_txt_expires']) : '';
    
        header('Content-Type: text/plain; charset=utf-8');
        echo "Contact: " . trim($contact) . "\n";
        if ($expires !== '') {
            echo "Expires: " . $expires . "\n";
        }
        echo "Preferred-Languages: pl, en\n";
        echo "Canonical: " . home_url('/.well-known/security.txt') . "\n";
        exit;
    }



    public function page_content_audit() {
        $this->header_shell('center');
        // Simple content audit for posts/pages/products (if exists)
        $post_types = ['post','page'];
        if (post_type_exists('product')) { $post_types[] = 'product'; }
        $threshold = 600; // chars
        $query = new WP_Query([
            'post_type' => $post_types,
            'post_status' => ['publish','draft','pending','future','private'],
            'posts_per_page' => 50,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => false,
        ]);

        $items = [];
        foreach ($query->posts as $p) {
            $meta_title = (string) get_post_meta($p->ID, 'het_seo_meta_title', true);
            $meta_desc  = (string) get_post_meta($p->ID, 'het_seo_meta_description', true);
            $len = strlen(wp_strip_all_tags((string)$p->post_content));
            $has_thumb = has_post_thumbnail($p->ID) ? 1 : 0;

            $flags = [];
            if ($len < $threshold) { $flags[] = 'krótka treść'; }
            if ($meta_title === '') { $flags[] = 'brak meta title'; }
            if ($meta_desc === '') { $flags[] = 'brak meta description'; }
            if (!$has_thumb) { $flags[] = 'brak obrazka'; }

            if (!empty($flags)) {
                $items[] = [
                    'ID' => $p->ID,
                    'type' => $p->post_type,
                    'title' => get_the_title($p),
                    'flags' => $flags,
                    'edit' => get_edit_post_link($p->ID, ''),
                    'view' => get_permalink($p),
                    'modified' => $p->post_modified,
                    'len' => $len,
                ];
            }
        }

        include HET_SEO_PATH . 'templates/admin-content-audit.php';
    }

    /**
     * Socialmedia hub (single entry in main plugin menu).
     * Splits Social features into: Integracje + Funkcje wykonawcze.
     */
    public function page_socialmedia() {
        if (!current_user_can('manage_options')) { return; }
        if (!$this->is_pro()) { $this->render_pro_lock('Social Hub'); return; }
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'integracje';
        if (!in_array($tab, ['integracje','funkcje'], true)) { $tab = 'integracje'; }

        // Ensure Social Hub is installed when visiting Socialmedia.
        $this->require_social_hub();

        $this->header_shell('center');
        include HET_SEO_PATH . 'templates/admin-socialmedia.php';
    }

    public function page_social() {
        if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
        if (!$this->is_pro()) { $this->render_pro_lock('Social Media'); return; }
        // Save
        $opts = get_option(HET_SEO_OPT, []);
        if (isset($_POST['het_seo_social_save']) && check_admin_referer('het_seo_social_save')) {
            $opts['social_enabled'] = !empty($_POST['social_enabled']) ? 1 : 0;
            $opts['social_twitter_site'] = isset($_POST['social_twitter_site']) ? sanitize_text_field($_POST['social_twitter_site']) : '';
            $opts['social_default_image'] = isset($_POST['social_default_image']) ? esc_url_raw($_POST['social_default_image']) : '';
            $opts['social_fb_app_id'] = isset($_POST['social_fb_app_id']) ? sanitize_text_field($_POST['social_fb_app_id']) : '';
            update_option(HET_SEO_OPT, $opts, false);
            echo '<div class="notice notice-success"><p>Zapisano ustawienia Social Media.</p></div>';
        }

        $this->header_shell('center');
        $preview_url = home_url('/');
        include HET_SEO_PATH . 'templates/admin-social.php';
    }

    public function page_status() {
        // Save read-only integration tokens (optional; does not connect automatically).
        $opts = get_option(HET_SEO_OPT, []);
        if (isset($_POST['het_seo_status_save']) && check_admin_referer('het_seo_status_save')) {
            $opts['cf_enabled'] = !empty($_POST['cf_enabled']) ? 1 : 0;
            $opts['cf_api_token'] = isset($_POST['cf_api_token']) ? sanitize_text_field($_POST['cf_api_token']) : '';
            $opts['cf_account_id'] = isset($_POST['cf_account_id']) ? sanitize_text_field($_POST['cf_account_id']) : '';
            $opts['cf_zone_id'] = isset($_POST['cf_zone_id']) ? sanitize_text_field($_POST['cf_zone_id']) : '';

            $opts['gsc_enabled'] = !empty($_POST['gsc_enabled']) ? 1 : 0;
            $opts['gsc_property'] = isset($_POST['gsc_property']) ? sanitize_text_field($_POST['gsc_property']) : '';

            update_option(HET_SEO_OPT, $opts, false);
            echo '<div class="notice notice-success"><p>Zapisano ustawienia integracji (bez łączenia).</p></div>';
        }

        $this->header_shell('center');
        $report = HET_SEO_Status::get_report();
        include HET_SEO_PATH . 'templates/admin-status.php';
    }


public function handle_integrations_save() {
    if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
    if (!$this->is_pro()) { wp_die('Ta funkcja jest dostępna w buildzie open-source. Odśwież stronę i spróbuj ponownie.'); }
    check_admin_referer('het_seo_integrations_save');

    $provider = isset($_POST['provider']) ? sanitize_key($_POST['provider']) : '';
    $settings = isset($_POST['settings']) && is_array($_POST['settings']) ? $_POST['settings'] : [];

    // Normalize common fields
    $norm = [];
    foreach ($settings as $k=>$v) {
        $k = sanitize_key($k);
        if (is_array($v)) {
            $norm[$k] = array_map('sanitize_text_field', $v);
        } else {
            $norm[$k] = sanitize_text_field((string)$v);
        }
    }
    if ($provider) {
        $norm['connected'] = !empty($_POST['connected']) ? 1 : 0;
        $this->integrations->set_provider_settings($provider, $norm);
    }

    $back = admin_url('admin.php?page=' . self::MENU_SLUG_INTEGRATIONS . '&tab=providers');
    $this->safe_redirect($back);
    exit;
}

public function handle_rank_add_kw() {
    if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
    if (!$this->is_pro()) { wp_die('Ta funkcja jest dostępna w buildzie open-source. Odśwież stronę i spróbuj ponownie.'); }
    check_admin_referer('het_seo_rank_add_kw');

    $kw = isset($_POST['keyword']) ? (string)$_POST['keyword'] : '';
    $engine = isset($_POST['engine']) ? sanitize_key($_POST['engine']) : 'google';
    $locale = isset($_POST['locale']) ? (string)$_POST['locale'] : 'pl-PL';
    $device = isset($_POST['device']) ? sanitize_key($_POST['device']) : 'mobile';
    $target_url = isset($_POST['target_url']) ? (string)$_POST['target_url'] : '';

    $this->rank->add_keyword($kw, $engine, $locale, $device, $target_url);

    $back = admin_url('admin.php?page=' . self::MENU_SLUG_INTEGRATIONS . '&tab=rankings');
    $this->safe_redirect($back);
    exit;
}

public function handle_rank_import() {
    if (!current_user_can('manage_options')) { wp_die('Brak uprawnień'); }
    check_admin_referer('het_seo_rank_import');

    $text = isset($_POST['import_text']) ? (string)$_POST['import_text'] : '';
    $source = isset($_POST['source']) ? sanitize_key($_POST['source']) : 'manual';
    $rows = HET_SEO_Rank_Tracker::parse_import_text($text);
    $this->rank->import_positions($rows, $source);

    $back = admin_url('admin.php?page=' . self::MENU_SLUG_INTEGRATIONS . '&tab=rankings');
    $this->safe_redirect($back);
    exit;
}

// ===== SOCIAL HUB PAGES =====
private function require_social_hub() {
    if (!class_exists('HET_SEO_Social_Hub')) {
        require_once HET_SEO_PATH . 'includes/class-het-seo-social-hub.php';
    }
    if (class_exists('HET_SEO_Social_Hub')) {
        HET_SEO_Social_Hub::install();
        HET_SEO_Social_Hub::ensure_cron();
    }
}

public function page_social_dashboard() {
    $this->require_social_hub();
    $tab = 'dashboard';
    $this->header_shell('center');
    include HET_SEO_PATH . 'templates/admin-social-hub.php';
}

public function page_social_accounts() {
    $this->require_social_hub();
    $tab = 'accounts';
    $this->header_shell('center');
    include HET_SEO_PATH . 'templates/admin-social-hub.php';
}

public function page_social_composer() {
    $this->require_social_hub();
    $tab = 'composer';
    $this->header_shell('center');
    include HET_SEO_PATH . 'templates/admin-social-hub.php';
}

public function page_social_campaigns() {
    $this->require_social_hub();
    $tab = 'campaigns';
    $this->header_shell('center');
    include HET_SEO_PATH . 'templates/admin-social-hub.php';
}

public function page_social_queue() {
    $this->require_social_hub();
    $tab = 'queue';
    $this->header_shell('center');
    include HET_SEO_PATH . 'templates/admin-social-hub.php';
}

public function page_social_logs() {
    $this->require_social_hub();
    $tab = 'logs';
    $this->header_shell('center');
    include HET_SEO_PATH . 'templates/admin-social-hub.php';
}


public function handle_export_social_logs() {
    if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
    check_admin_referer('het_seo_export_social_logs');

    if (!class_exists('HET_SEO_Social_Hub')) { wp_die('Social Hub missing'); }

    $rows = HET_SEO_Social_Hub::list_logs(5000);
    $rows = is_array($rows) ? $rows : [];

    $fname = 'heterodyna-seo-social-logs-' . gmdate('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $fname);

    $out = fopen('php://output', 'w');
    fputcsv($out, ['id','level','message','created_at']);
    foreach ($rows as $r) {
        fputcsv($out, [
            (int)($r['id'] ?? 0),
            (string)($r['level'] ?? ''),
            (string)($r['message'] ?? ''),
            (string)($r['created_at'] ?? ''),
        ]);
    }
    fclose($out);
    exit;
}

}
