<?php if (!defined('ABSPATH')) { exit; }
$base = admin_url('admin.php');
$u_center = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_CENTER], $base);
$opts = isset($opts) && is_array($opts) ? $opts : get_option(HET_SEO_OPT, []);
$enabled = !empty($opts['security_txt_enabled']);
$contact = isset($opts['security_txt_contact']) ? (string)$opts['security_txt_contact'] : "mailto:security@".parse_url(home_url(), PHP_URL_HOST);
$expires = isset($opts['security_txt_expires']) ? (string)$opts['security_txt_expires'] : '';
$security_url = home_url('/.well-known/security.txt');
?>
<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-shield"></span> security.txt</div>
  <div class="het-seo-card__sub">Generator <code>/.well-known/security.txt</code> (virtual). Przydatne dla bezpieczeństwa i zgodności.</div>

  <div class="het-actions" style="margin-top:10px">
    <a class="button" href="<?php echo esc_url($u_center); ?>"><span class="dashicons dashicons-admin-home"></span> Wróć do Centrum</a>
    <a class="button button-primary" target="_blank" rel="noopener" href="<?php echo esc_url($security_url); ?>"><span class="dashicons dashicons-external"></span> Otwórz security.txt</a>
  </div>

  <form method="post" style="margin-top:14px">
    <?php wp_nonce_field('het_seo_save_security'); ?>
    <label style="display:flex;gap:10px;align-items:center;margin:10px 0">
      <input type="checkbox" name="security_txt_enabled" value="1" <?php checked($enabled); ?>/>
      <strong>Włącz generator security.txt</strong>
    </label>

    <div class="het-grid" style="grid-template-columns:1fr;gap:10px">
      <div class="het-field">
        <div class="het-field__label">Contact</div>
        <textarea class="large-text code" name="security_txt_contact" rows="4"><?php echo esc_textarea($contact); ?></textarea>
        <div class="het-field__hint">Jedna linia na wpis, np. <code>mailto:security@twojadomena.pl</code> lub URL do formularza.</div>
      </div>
      <div class="het-field">
        <div class="het-field__label">Expires (opcjonalnie)</div>
        <input class="regular-text" type="text" name="security_txt_expires" value="<?php echo esc_attr($expires); ?>" placeholder="2026-12-31T23:59:59Z"/>
        <div class="het-field__hint">Format ISO-8601 (Z). Jeśli puste — nie będzie dodane.</div>
      </div>
    </div>

    <p style="margin-top:12px">
      <button class="button button-primary" type="submit" name="het_seo_save_security" value="1"><span class="dashicons dashicons-yes"></span> Zapisz</button>
    </p>
  </form>
</div>
