<?php if (!defined('ABSPATH')) { exit; }
?>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-randomize"></span> Przekierowania</div>
  <div class="het-seo-card__sub">Proste 301/302 z bazy (bez .htaccess). Działa w WordPressie — idealne do ogarniania 404.</div>
</div>

<div class="het-seo-card" style="margin-top:12px">
  <div class="het-seo-card__title"><span class="dashicons dashicons-admin-settings"></span> Ustawienia</div>
  <form method="post" style="margin-top:8px">
    <?php wp_nonce_field('het_seo_redirect_settings'); ?>
    <label><input type="checkbox" name="auto_redirect_smart" value="1" <?php checked(!empty($opts['auto_redirect_smart'])); ?>> Smart auto-redirect: jeśli URL odpowiada istniejącej ścieżce wpisu/strony, przekieruj 301 automatycznie (bez wpisu w tabeli).</label>
    <p class="description">Bezpieczne: dotyczy tylko dopasowania do istniejących treści WordPressa (get_page_by_path). Jeśli coś nie pasuje — nic nie robi.</p>
    <p><button class="button button-primary" name="het_seo_redirect_settings" value="1">Zapisz</button></p>
  </form>
</div>

<div class="het-seo-grid" style="margin-top:12px">
  <div class="het-seo-card">
    <div class="het-seo-card__title">Dodaj przekierowanie</div>
    <form method="post">
      <?php wp_nonce_field('het_seo_redirect_add'); ?>
      <div class="het-field">
        <label>Z (ścieżka)</label>
        <input type="text" name="from_path" placeholder="/stary-url" value="<?php echo esc_attr($prefill_from ?? ''); ?>" required>
        <div class="het-muted het-small">Tylko ścieżka, bez domeny. Np. <code>/kategoria/stary</code></div>
      </div>
      <div class="het-field" style="margin-top:10px">
        <label>Do (URL)</label>
        <input type="url" name="to_url" placeholder="<?php echo esc_attr(home_url('/')); ?>" required>
      </div>
      <div class="het-field" style="margin-top:10px">
        <label>Kod</label>
        <select name="code">
          <option value="301">301 (stałe)</option>
          <option value="302">302 (tymczasowe)</option>
        </select>
      </div>
      <button class="button button-primary" name="het_seo_redirect_add" value="1" style="margin-top:12px">Dodaj</button>
    </form>
  </div>

  <div class="het-seo-card">
    <div class="het-seo-card__title">Wskazówka</div>
    <p class="het-muted">Tip: w Monitorze 404 masz przycisk „Dodaj przekierowanie”, który przenosi tutaj z wypełnioną ścieżką.</p>
    <ul class="het-list">
      <li>Wydajność: działa w PHP (WordPress), bez zmian serwera</li>
      <li>Bezpieczeństwo: tylko <code>manage_options</code> może zarządzać</li>
      <li>Statystyki: liczymy <code>hits</code> + <code>last_hit</code></li>
    </ul>
  </div>
</div>

<div class="het-seo-card" style="margin-top:12px">
  <div class="het-seo-card__title">Lista</div>
  <?php if (empty($redirects)): ?>
    <p class="het-muted">Brak przekierowań.</p>
  <?php else: ?>
    <div class="het-table-wrap">
      <table class="widefat striped">
        <thead>
          <tr><th>ID</th><th>Z</th><th>Do</th><th>Kod</th><th>Hits</th><th>Last hit</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($redirects as $r): ?>
          <tr>
            <td><?php echo intval($r['id']); ?></td>
            <td><code><?php echo esc_html($r['from_path']); ?></code></td>
            <td><a href="<?php echo esc_url($r['to_url']); ?>" target="_blank" rel="noopener"><?php echo esc_html(wp_trim_words($r['to_url'], 12)); ?></a></td>
            <td><?php echo intval($r['code']); ?></td>
            <td><?php echo intval($r['hits']); ?></td>
            <td class="het-small"><?php echo esc_html($r['last_hit'] ?: '—'); ?></td>
            <td>
              <form method="post" style="margin:0">
                <?php wp_nonce_field('het_seo_redirect_delete'); ?>
                <input type="hidden" name="rid" value="<?php echo intval($r['id']); ?>">
                <button class="button button-small" name="het_seo_redirect_delete" value="1" onclick="return confirm('Usunąć przekierowanie?')">Usuń</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
