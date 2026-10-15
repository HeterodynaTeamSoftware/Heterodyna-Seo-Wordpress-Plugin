<?php if (!defined('ABSPATH')) { exit; }

$u_public = home_url('/robots.txt');
?>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-edit"></span> Robots</div>
  <div class="het-seo-card__sub">
    Edytor reguł, które są dopisywane do standardowego <code>robots.txt</code> generowanego przez WordPress.
  </div>
  <div class="het-actions" style="margin-top:10px">
    <a class="button" href="<?php echo esc_url($u_public); ?>" target="_blank" rel="noopener">Podgląd publiczny robots.txt</a>
  </div>
</div>

<div class="het-seo-grid" style="margin-top:12px">
  <div class="het-seo-card">
    <div class="het-seo-card__title">Reguły (dopisek)</div>
    <form method="post">
      <?php wp_nonce_field('het_seo_save_robots'); ?>
      <textarea name="robots_rules" rows="10" style="width:100%;font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace"><?php echo esc_textarea($robots_rules); ?></textarea>
      <div class="het-muted het-small" style="margin-top:8px">
        Wpisz tylko dodatkowe linie, np. <code>Disallow: /wp-admin/</code>. Zostaną dopisane na końcu.
      </div>
      <button class="button button-primary" name="het_seo_save_robots" value="1" style="margin-top:10px">Zapisz</button>
    </form>
  </div>

  <div class="het-seo-card">
    <div class="het-seo-card__title">Podgląd wynikowy</div>
    <pre style="white-space:pre-wrap;background:rgba(0,0,0,0.06);padding:12px;border-radius:12px;max-height:320px;overflow:auto"><?php echo esc_html($preview); ?></pre>
    <div class="het-muted het-small">To jest to, co zobaczy robot w <code>/robots.txt</code> (WordPress + Twoje reguły).</div>
  </div>
</div>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
