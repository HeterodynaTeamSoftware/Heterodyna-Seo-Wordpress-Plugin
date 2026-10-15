<?php
if (!defined('ABSPATH')) { exit; }

// Expects: $current_mode, $modes
?>
<div class="het-seo-shell">
  <div class="het-page">
    <div class="het-page__head">
      <h1 class="het-page__title">Tryb witryny</h1>
      <p class="het-page__sub">Wybierz profil, a poniżej dostaniesz rekomendacje konfiguracji (bez automatycznego wdrażania).</p>
    </div>

    <form method="post" class="het-card" style="margin-top:14px;">
      <?php wp_nonce_field('het_seo_site_mode_save'); ?>
      <input type="hidden" name="het_seo_site_mode_save" value="1" />

      <div class="het-modes" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
        <?php foreach ($modes as $key => $meta): ?>
          <label class="het-card" style="margin:0;cursor:pointer;">
            <div style="display:flex;gap:10px;align-items:flex-start;">
              <input type="radio" name="site_mode" value="<?php echo esc_attr($key); ?>" <?php checked($current_mode, $key); ?> style="margin-top:3px;" />
              <div>
                <div style="font-weight:800;color:#111;"><?php echo esc_html($meta['label']); ?></div>
                <div style="font-size:12px;color:#666;margin-top:4px;"><?php echo esc_html($meta['desc']); ?></div>
              </div>
            </div>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="het-card__actions" style="margin-top:12px;">
        <button type="submit" class="button button-primary">Zapisz tryb</button>
        <a class="button button-secondary" href="admin.php?page=<?php echo esc_attr(HET_SEO_Admin::MENU_SLUG_CANONICAL); ?>">Przejdź do kanonicznych / indeksowania</a>
        <a class="button button-secondary" href="admin.php?page=<?php echo esc_attr(HET_SEO_Admin::MENU_SLUG_ROBOTS); ?>">Przejdź do robots.txt</a>
      </div>
    </form>

    <div class="het-card" style="margin-top:14px;">
      <div class="het-card__head">
        <div>
          <div class="het-card__title">Rekomendacje dla: <strong><?php echo esc_html($modes[$current_mode]['label'] ?? $current_mode); ?></strong></div>
          <div class="het-card__hint">To są bezpieczne podpowiedzi. Nic nie zostanie zmienione automatycznie.</div>
        </div>
      </div>

      <table class="widefat striped" style="margin-top:10px;">
        <thead>
          <tr>
            <th>Obszar</th>
            <th>Rekomendacja</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $recs = isset($modes[$current_mode]['recs']) && is_array($modes[$current_mode]['recs']) ? $modes[$current_mode]['recs'] : [];
          foreach ($recs as $rec):
            $parts = explode(':', $rec, 2);
            $area = trim($parts[0]);
            $msg = trim($parts[1] ?? $parts[0]);
          ?>
          <tr>
            <td style="width:220px;"><strong><?php echo esc_html($area); ?></strong></td>
            <td><?php echo esc_html($msg); ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recs)): ?>
          <tr><td colspan="2">Brak rekomendacji.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <div style="margin-top:10px;font-size:12px;color:#666;">
        Tip: w kolejnym kroku możemy dodać <strong>presety do zastosowania jednym kliknięciem</strong>, ale dopiero po Twojej akceptacji (żeby nic nie psuło SEO).
      </div>
    </div>

  </div>
</div>
