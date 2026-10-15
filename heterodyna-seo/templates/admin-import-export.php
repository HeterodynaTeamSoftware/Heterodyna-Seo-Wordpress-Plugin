<?php if (!defined('ABSPATH')) { exit; }
?>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-migrate"></span> Import / Export</div>
  <div class="het-seo-card__sub">Eksportuje ustawienia oraz dane narzędzi (przekierowania, 404). Stare dane komercyjne/tokeny są pomijane. Import przyjmie każdy z tych JSON-ów.</div>
</div>

<div class="het-grid2">
  <div class="het-seo-card">
    <h3>Export</h3>
    <p class="het-muted">Możesz pobrać osobno: ustawienia, przekierowania, log 404.</p>

    <form method="post" style="margin:0 0 12px 0">
      <?php wp_nonce_field('het_seo_export'); ?>
      <button class="button button-primary" name="het_seo_export" value="1" type="submit">Ustawienia (JSON)</button>
    </form>

    <form method="post" style="margin:0 0 12px 0">
      <?php wp_nonce_field('het_seo_export_redirects'); ?>
      <button class="button" name="het_seo_export_redirects" value="1" type="submit">Przekierowania 301/302 (JSON)</button>
    </form>

    <form method="post" style="margin:0">
      <?php wp_nonce_field('het_seo_export_404'); ?>
      <button class="button" name="het_seo_export_404" value="1" type="submit">Monitor 404 (JSON)</button>
    </form>

    <hr style="margin:14px 0;">

    <h3>Eksport (CSV)</h3>
    <p class="het-muted">Szybkie eksporty operacyjne: meta / 404 / przekierowania / prosty raport.</p>
    <?php
      $csv_base = admin_url('admin-post.php');
      $csv_meta = wp_nonce_url(add_query_arg(['action'=>'het_seo_export_csv','type'=>'meta'], $csv_base), 'het_seo_export_csv_meta');
      $csv_404  = wp_nonce_url(add_query_arg(['action'=>'het_seo_export_csv','type'=>'404'], $csv_base), 'het_seo_export_csv_404');
      $csv_red  = wp_nonce_url(add_query_arg(['action'=>'het_seo_export_csv','type'=>'redirects'], $csv_base), 'het_seo_export_csv_redirects');
      $csv_sc   = wp_nonce_url(add_query_arg(['action'=>'het_seo_export_csv','type'=>'score'], $csv_base), 'het_seo_export_csv_score');
    ?>
    <p class="het-actions">
      <a class="button" href="<?php echo esc_url($csv_meta); ?>">Meta (CSV)</a>
      <a class="button" href="<?php echo esc_url($csv_404); ?>">404 (CSV)</a>
      <a class="button" href="<?php echo esc_url($csv_red); ?>">Przekierowania (CSV)</a>
      <a class="button" href="<?php echo esc_url($csv_sc); ?>">Raport (CSV)</a>
    </p>

  </div>

  <form method="post" class="het-seo-card">
    <?php wp_nonce_field('het_seo_import'); ?>
    <h3>Import</h3>
    <p class="het-muted">Wklej JSON (z exportu) i zaimportuj.</p>
    <textarea name="import_json" rows="10" class="large-text code" placeholder='{ "version": "03.00.006", "options": { ... } }'></textarea>
    <p><button class="button" name="het_seo_import" value="1" type="submit">Importuj</button></p>
  </form>
</div>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
