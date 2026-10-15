<?php
if (!defined('ABSPATH')) { exit; }
$base = admin_url('admin.php');
$notice = isset($_GET['het_notice']) ? sanitize_key($_GET['het_notice']) : '';
if ($notice === 'collected') {
  echo '<div class="notice notice-success"><p>Zebrano snapshot danych strony.</p></div>';
}
?>

<?php
// Backward/upgrade safe defaults
$site = (isset($site) && is_array($site)) ? $site : [];
$url_count = (int)($site['url_count'] ?? $site['total_urls'] ?? 0);
$words_avg = (int)($site['words_avg'] ?? $site['avg_words'] ?? 0);
$meta = is_array(($site['meta'] ?? null)) ? $site['meta'] : [];
$headings = is_array(($site['headings'] ?? $site['heads'] ?? null)) ? ($site['headings'] ?? $site['heads']) : [];
$links = is_array(($site['links'] ?? null)) ? $site['links'] : [];
$internal_out = (int)($links['internal_out'] ?? $links['internal_total'] ?? 0);
$external_out = (int)($links['external_out'] ?? $links['external_total'] ?? 0);
?>

<div class="het-card">
  <div class="het-card__head">
    <h2 style="margin:0">Dane strony (DATA CORE)</h2>
    <div class="het-card__actions">
      <form method="post" action="<?php echo esc_url($base); ?>?page=<?php echo esc_attr(HET_SEO_Admin::MENU_SLUG_DATA_SITE); ?>" style="display:inline">
        <?php wp_nonce_field('het_seo_collect_site'); ?>
        <input type="hidden" name="het_seo_collect_site" value="1">
        <button class="button button-primary" type="submit">Zbierz dane teraz</button>
      </form>
    </div>
  </div>

  <div class="het-metrics" style="margin-top:10px">
    <div class="het-metric"><div class="het-metric__label">URL (posty + strony)</div><div class="het-metric__value"><?php echo esc_html($url_count); ?></div></div>
    <div class="het-metric"><div class="het-metric__label">Śr. słów / URL</div><div class="het-metric__value"><?php echo esc_html($words_avg); ?></div></div>
    <div class="het-metric"><div class="het-metric__label">Meta title ustawione</div><div class="het-metric__value"><?php echo esc_html((int)($meta['title_set'] ?? $site['meta_title_set'] ?? 0)); ?></div></div>
    <div class="het-metric"><div class="het-metric__label">Meta description ustawione</div><div class="het-metric__value"><?php echo esc_html((int)($meta['desc_set'] ?? $site['meta_desc_set'] ?? 0)); ?></div></div>
    <div class="het-metric"><div class="het-metric__label">Noindex</div><div class="het-metric__value"><?php echo esc_html((int)($meta['noindex'] ?? $site['noindex_count'] ?? 0)); ?></div></div>
    <div class="het-metric"><div class="het-metric__label">Śr. PSI (mobile)</div><div class="het-metric__value"><?php echo esc_html('—'); ?></div></div>
  </div>

  <div style="margin-top:14px">
    <h3 style="margin:0 0 8px">Rozkład nagłówków (sumarycznie)</h3>
    <table class="widefat striped">
      <thead><tr>
        <th>H1</th><th>H2</th><th>H3</th><th>H4</th><th>H5</th><th>H6</th>
      </tr></thead>
      <tbody><tr>
        <td><?php echo esc_html((int)($headings['h1'] ?? 0)); ?></td>
        <td><?php echo esc_html((int)($headings['h2'] ?? 0)); ?></td>
        <td><?php echo esc_html((int)($headings['h3'] ?? 0)); ?></td>
        <td><?php echo esc_html((int)($headings['h4'] ?? 0)); ?></td>
        <td><?php echo esc_html((int)($headings['h5'] ?? 0)); ?></td>
        <td><?php echo esc_html((int)($headings['h6'] ?? 0)); ?></td>
      </tr></tbody>
    </table>
  </div>

  <div style="margin-top:14px">
    <h3 style="margin:0 0 8px">Linki wychodzące (best-effort)</h3>
    <table class="widefat striped">
      <thead><tr>
        <th>Wewnętrzne (out)</th><th>Zewnętrzne (out)</th><th>Śr. wewn./URL</th><th>Śr. zewn./URL</th>
      </tr></thead>
      <tbody><tr>
        <td><?php echo esc_html($internal_out); ?></td>
        <td><?php echo esc_html($external_out); ?></td>
        <td><?php echo esc_html($url_count ? round($internal_out / $url_count, 2) : 0); ?></td>
        <td><?php echo esc_html($url_count ? round($external_out / $url_count, 2) : 0); ?></td>
      </tr></tbody>
    </table>
  </div>

  <p style="margin-top:12px;color:#666">
    Dane liczone są w trybie <strong>best-effort</strong> bez zewnętrznych API.
    Jeśli masz włączone PSI, panel pokaże uśrednione wyniki. Snapshoty są zapisywane i budują <strong>Historię SEO</strong>.
  </p>
</div>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
