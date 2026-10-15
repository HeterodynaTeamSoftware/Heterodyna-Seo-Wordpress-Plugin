<?php if (!defined('ABSPATH')) { exit; }

$opts = get_option(HET_SEO_OPT, []);
$mode = isset($opts['site_mode']) ? (string)$opts['site_mode'] : '';

$link_ai_auto   = add_query_arg(['page' => HET_SEO_Admin::MENU_SLUG_AI_AUTO], admin_url('admin.php'));
$link_site_mode = add_query_arg(['page' => HET_SEO_Admin::MENU_SLUG_SITE_MODE], admin_url('admin.php'));
$link_settings  = add_query_arg(['page' => HET_SEO_Admin::MENU_SLUG_SETTINGS], admin_url('admin.php'));

?>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-welcome-write-blog"></span> Generator treści (AI)</div>
  <div class="het-seo-card__sub">
    Ten moduł jest już podpięty w menu i Centrum (bez linków typu <code>#</code>), ale sam generator jest jeszcze w trybie „bezpieczny szkielet”.
    Dzięki temu nic nie dzieje się automatycznie w tle i nie ma ryzyka „auto-apply”.
  </div>

  <div class="het-actions" style="margin-top:10px">
    <a class="button button-primary" href="<?php echo esc_url($link_settings); ?>">Ustawienia → SEO Core / AI</a>
    <a class="button" href="<?php echo esc_url($link_site_mode); ?>">Tryb witryny (rekomendacje)</a>
    <a class="button" href="<?php echo esc_url($link_ai_auto); ?>">SEO Autopilot (AI Auto)</a>
  </div>

  <div style="margin-top:12px" class="het-muted">
    <p><strong>Status:</strong> w przygotowaniu.</p>
    <ul style="margin:6px 0 0 18px">
      <li>Brief → artykuł → FAQ → schema</li>
      <li>Oparcie o <em>Tryb witryny</em> (<?php echo $mode ? esc_html($mode) : 'nie ustawiono'; ?>) jako kontekst.</li>
      <li>Tryb działania: „rekomendacje + ręczne zatwierdzanie” (zero automatu bez Twojej decyzji).</li>
    </ul>
  </div>
</div>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
