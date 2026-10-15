<?php
if (!defined('ABSPATH')) { exit; }
// Expects: $opts, $preview_url
$enabled = !empty($opts['social_enabled']) ? 1 : 0;
$tw_site = isset($opts['social_twitter_site']) ? (string)$opts['social_twitter_site'] : '';
$def_img = isset($opts['social_default_image']) ? (string)$opts['social_default_image'] : '';
$fb_app  = isset($opts['social_fb_app_id']) ? (string)$opts['social_fb_app_id'] : '';
?>
<div class="het-seo-shell">
  <div class="het-page">
    <div class="het-page__head">
      <h1 class="het-page__title">Social Media</h1>
      <p class="het-page__sub">Ustaw podstawowe meta dla udostępnień (Open Graph + Twitter Cards). Moduł jest <strong>opcjonalny</strong> — nic nie zmienia, dopóki go nie włączysz.</p>
    </div>

    <form method="post" class="het-card" style="margin-top:14px;">
      <?php wp_nonce_field('het_seo_social_save'); ?>
      <input type="hidden" name="het_seo_social_save" value="1" />

      <div class="het-card__head">
        <div>
          <div class="het-card__title"><span class="dashicons dashicons-share"></span> Ustawienia</div>
          <div class="het-muted het-small">Włączasz tylko generowanie meta tagów w <code>&lt;head&gt;</code>. Meta z metaboxa (title/description) ma pierwszeństwo nad domyślnymi.</div>
        </div>
        <div class="het-card__actions">
          <a class="button button-secondary" href="admin.php?page=<?php echo esc_attr(HET_SEO_Admin::MENU_SLUG_CONTENT_AUDIT); ?>">Audyt treści</a>
          <a class="button button-secondary" href="admin.php?page=<?php echo esc_attr(HET_SEO_Admin::MENU_SLUG_META_AUDIT); ?>">Audyt meta</a>
        </div>
      </div>

      <div class="het-metrics" style="margin-top:10px;">
        <label class="het-metric" style="align-items:flex-start;gap:10px;">
          <input type="checkbox" name="social_enabled" value="1" <?php checked($enabled, 1); ?> />
          <div>
            <div style="font-weight:800;color:#111;">Włącz meta Social (OG + Twitter)</div>
            <div class="het-muted het-small">Dodaje og:title, og:description, og:image, og:url + twitter:card. Bez żadnego „autopilota”.</div>
          </div>
        </label>
      </div>

      <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:10px;">
        <div>
          <label class="het-muted het-small" for="social_default_image">Domyślny obrazek (URL)</label>
          <input id="social_default_image" name="social_default_image" type="url" class="regular-text" value="<?php echo esc_attr($def_img); ?>" placeholder="https://.../share.jpg" style="width:100%;" />
          <div class="het-muted het-small">Używany, gdy wpis/strona nie ma obrazka wyróżniającego.</div>
        </div>
        <div>
          <label class="het-muted het-small" for="social_twitter_site">Twitter/X @site</label>
          <input id="social_twitter_site" name="social_twitter_site" type="text" class="regular-text" value="<?php echo esc_attr($tw_site); ?>" placeholder="@twojprofil" style="width:100%;" />
          <div class="het-muted het-small">Opcjonalnie. Jeśli puste — pomijamy tag.</div>
        </div>
        <div>
          <label class="het-muted het-small" for="social_fb_app_id">Facebook App ID</label>
          <input id="social_fb_app_id" name="social_fb_app_id" type="text" class="regular-text" value="<?php echo esc_attr($fb_app); ?>" placeholder="123456789" style="width:100%;" />
          <div class="het-muted het-small">Opcjonalnie. Przydaje się w niektórych ekosystemach FB.</div>
        </div>
        <div class="het-card" style="margin:0;">
          <div class="het-card__title">Podgląd</div>
          <div class="het-muted het-small">Najprościej sprawdzisz przez wklejenie linku w Messenger/FB/LinkedIn. Tu tylko szybka kontrola.</div>
          <div style="margin-top:8px;">
            <code><?php echo esc_html($preview_url); ?></code>
          </div>
        </div>
      </div>

      <div class="het-card__actions" style="margin-top:12px;">
        <button type="submit" class="button button-primary">Zapisz</button>
      </div>
    </form>

    <div class="het-card" style="margin-top:14px;">
      <div class="het-card__title"><span class="dashicons dashicons-yes"></span> Co to daje klientowi</div>
      <ul class="het-list">
        <li>Linki udostępniane w Social Media wyglądają „profesjonalnie” (tytuł, opis, obrazek).</li>
        <li>Mniej sytuacji: „wkleiłem link i wygląda źle”.</li>
        <li>Podstawa pod kampanie reklamowe (FB/IG/LinkedIn).</li>
      </ul>
    </div>

  </div>
</div>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
