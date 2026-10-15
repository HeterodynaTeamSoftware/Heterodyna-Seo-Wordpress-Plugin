<?php if (!defined('ABSPATH')) { exit; }
$base = admin_url('admin.php');
$u_center = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_CENTER], $base);
$opts = isset($opts) && is_array($opts) ? $opts : get_option(HET_SEO_OPT, []);
$enabled = !empty($opts['sitemap_enabled']);
$sel = isset($opts['sitemap_post_types']) && is_array($opts['sitemap_post_types']) ? $opts['sitemap_post_types'] : ['post','page'];
$sitemap_url = home_url('/sitemap.xml');
?>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-networking"></span> Sitemap</div>
  <div class="het-seo-card__sub">Prosty generator <code>sitemap.xml</code> (virtual). Jeśli masz inną wtyczkę od sitemap, możesz tu to wyłączyć.</div>
  <div class="het-actions" style="margin-top:10px">
    <a class="button" href="<?php echo esc_url($u_center); ?>">← Wróć do Centrum</a>
    <a class="button" href="<?php echo esc_url($sitemap_url); ?>" target="_blank" rel="noopener">Otwórz sitemap.xml</a>
  </div>
</div>

<div class="het-seo-card">
  <div class="het-seo-card__title">Ustawienia</div>
  <form method="post">
    <?php wp_nonce_field('het_seo_save_sitemap'); ?>
    <table class="form-table">
      <tr>
        <th>Włącz generator</th>
        <td>
          <label><input type="checkbox" name="sitemap_enabled" value="1" <?php checked($enabled); ?>> generuj <code>/sitemap.xml</code></label>
          <p class="description">Obsługujemy tylko dokładny adres <code>/sitemap.xml</code>.</p>
        </td>
      </tr>
      <tr>
        <th>Post types</th>
        <td>
          <?php foreach ($public_types as $pt):
            $checked = in_array($pt->name, $sel, true);
          ?>
            <label style="display:block;margin:4px 0">
              <input type="checkbox" name="sitemap_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked($checked); ?>>
              <?php echo esc_html($pt->labels->name . ' (' . $pt->name . ')'); ?>
            </label>
          <?php endforeach; ?>
          <p class="description">Jeśli odznaczysz wszystko, domyślnie użyjemy post + page.</p>
        </td>
      </tr>
    </table>
    <p><button class="button button-primary" name="het_seo_save_sitemap" value="1">Zapisz</button></p>
  </form>
</div>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
