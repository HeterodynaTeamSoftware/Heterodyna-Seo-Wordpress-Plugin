<?php if (!defined('ABSPATH')) { exit; }
$opts = get_option(HET_SEO_OPT, []);
$cf_enabled = !empty($opts['cf_enabled']);
$gsc_enabled = !empty($opts['gsc_enabled']);

$badge = function($ok, $txt_ok='OK', $txt_bad='Do poprawy') {
  $c = $ok ? 'het-pill het-pill--ok' : 'het-pill het-pill--bad';
  $t = $ok ? $txt_ok : $txt_bad;
  return '<span class="' . esc_attr($c) . '">' . esc_html($t) . '</span>';
};

$public = isset($report['public']) && is_array($report['public']) ? $report['public'] : [];
$site = isset($report['site']) && is_array($report['site']) ? $report['site'] : [];
$cron = isset($report['cron']) && is_array($report['cron']) ? $report['cron'] : [];
?>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-heart"></span> Stan witryny</div>
  <div class="het-seo-card__sub">Szybki, czytelny raport dla właściciela strony. Best-effort, read-only. Ostatnia aktualizacja: <b><?php echo esc_html($report['generated_at'] ?? '—'); ?></b></div>

  <div class="het-mini-grid" style="margin-top:10px">
    <div class="het-mini-tile">
      <div class="het-mini-tile__label">HTTPS</div>
      <div class="het-mini-tile__value"><?php echo $badge(!empty($site['https']), 'Włączone', 'Brak'); ?></div>
      <div class="het-mini-tile__hint"><?php echo esc_html($site['home'] ?? ''); ?></div>
    </div>
    <div class="het-mini-tile">
      <div class="het-mini-tile__label">WordPress</div>
      <div class="het-mini-tile__value"><?php echo esc_html($site['wp'] ?? '—'); ?></div>
      <div class="het-mini-tile__hint">PHP: <?php echo esc_html($site['php'] ?? '—'); ?></div>
    </div>
    <div class="het-mini-tile">
      <div class="het-mini-tile__label">WP-Cron</div>
      <div class="het-mini-tile__value"><?php echo $badge(empty($cron['wp_cron_disabled']), 'Aktywny', 'Wyłączony'); ?></div>
      <div class="het-mini-tile__hint">Jeśli wyłączony — ustaw CRON na serwerze.</div>
    </div>
  </div>

  <div class="het-seo-grid" style="margin-top:12px">
    <div class="het-seo-card" style="padding:14px">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
        <div>
          <div style="font-weight:700">Publiczne zasoby</div>
          <div class="het-seo-muted">Czy to, co powinno być publiczne, faktycznie działa.</div>
        </div>
        <div><?php echo $badge(!empty($public['robots']['ok']) && !empty($public['sitemap']['ok']), 'W porządku', 'Sprawdź'); ?></div>
      </div>

      <table class="widefat striped" style="margin-top:10px">
        <thead><tr><th>Element</th><th>Status</th><th>HTTP</th><th>Link</th></tr></thead>
        <tbody>
          <?php
            $rows = [
              ['Robots.txt', $public['robots'] ?? null],
              ['Sitemap.xml', $public['sitemap'] ?? null],
              ['security.txt', $public['security'] ?? null],
              ['REST API (root)', $public['rest_root'] ?? null],
            ];
            foreach ($rows as $r) {
              [$label,$p] = $r;
              $ok = is_array($p) && !empty($p['ok']);
              $code = is_array($p) ? ($p['code'] ?? '—') : '—';
              $url = is_array($p) ? ($p['url'] ?? '') : '';
              echo '<tr>';
              echo '<td><b>' . esc_html($label) . '</b></td>';
              echo '<td>' . $badge($ok, 'OK', 'Do poprawy') . '</td>';
              echo '<td>' . esc_html((string)$code) . '</td>';
              echo '<td>' . ($url ? '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">Otwórz</a>' : '—') . '</td>';
              echo '</tr>';
            }
          ?>
        </tbody>
      </table>

      <div class="het-seo-muted" style="margin-top:10px">
        Uwaga: test jest best-effort. Jeśli hosting blokuje HEAD/GET, wynik może być "Do poprawy" mimo że działa.
      </div>
    </div>

    <div class="het-seo-card" style="padding:14px">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
        <div>
          <div style="font-weight:700">Automaty / zadania</div>
          <div class="het-seo-muted">Następne uruchomienia CRON (jeśli skonfigurowane).</div>
        </div>
        <div><?php echo $badge(!empty($cron['next_psi_daily']) || !empty($cron['next_alerts']) || !empty($cron['next_ai_auto']), 'Zaplanowane', 'Brak'); ?></div>
      </div>

      <table class="widefat striped" style="margin-top:10px">
        <thead><tr><th>Zadanie</th><th>Następne uruchomienie</th></tr></thead>
        <tbody>
          <tr><td><b>PSI (daily)</b></td><td><?php echo esc_html($cron['next_psi_daily'] ?? '—'); ?></td></tr>
          <tr><td><b>PSI queue</b></td><td><?php echo esc_html($cron['next_psi_queue'] ?? '—'); ?></td></tr>
          <tr><td><b>Alerty (daily)</b></td><td><?php echo esc_html($cron['next_alerts'] ?? '—'); ?></td></tr>
          <tr><td><b>AI Autopilot (daily)</b></td><td><?php echo esc_html($cron['next_ai_auto'] ?? '—'); ?></td></tr>
        </tbody>
      </table>

      <div style="margin-top:10px" class="het-links">
        <a class="het-link" href="<?php echo esc_url(admin_url('admin.php?page=' . HET_SEO_Admin::MENU_SLUG_TESTS)); ?>">
          <span class="dashicons dashicons-yes"></span>
          <div>
            <div class="het-link__title">Testy usług</div>
            <div class="het-link__desc">Dokładniejsze testy techniczne (dla admina)</div>
          </div>
        </a>
      </div>
    </div>
  </div>

  <hr style="margin:16px 0" />

  <div class="het-seo-card__title" style="margin-top:0"><span class="dashicons dashicons-admin-links"></span> Integracje zewnętrzne (read-only)</div>
  <div class="het-seo-card__sub">Na tym etapie zapisujemy tylko dane dostępowe i preferencje. Nie łączymy się automatycznie. Docelowo: Cloudflare / Search Console / hosting panel.</div>

  <form method="post" action="">
    <?php wp_nonce_field('het_seo_status_save'); ?>

    <div class="het-seo-grid" style="margin-top:12px">
      <div class="het-seo-card" style="padding:14px">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
          <div>
            <div style="font-weight:700">Cloudflare</div>
            <div class="het-seo-muted">Token API + identyfikatory (opcjonalnie).</div>
          </div>
          <div><?php echo $badge($cf_enabled, 'Włączone', 'Wyłączone'); ?></div>
        </div>

        <label style="display:block;margin-top:10px">
          <input type="checkbox" name="cf_enabled" value="1" <?php checked($cf_enabled); ?> />
          Włącz integrację Cloudflare (read-only)
        </label>

        <div style="margin-top:10px">
          <div class="het-seo-muted">API Token</div>
          <input class="regular-text" type="password" name="cf_api_token" value="<?php echo esc_attr($opts['cf_api_token'] ?? ''); ?>" placeholder="..." autocomplete="off" />
        </div>
        <div style="margin-top:10px">
          <div class="het-seo-muted">Account ID</div>
          <input class="regular-text" type="text" name="cf_account_id" value="<?php echo esc_attr($opts['cf_account_id'] ?? ''); ?>" placeholder="np. 123abc..." />
        </div>
        <div style="margin-top:10px">
          <div class="het-seo-muted">Zone ID</div>
          <input class="regular-text" type="text" name="cf_zone_id" value="<?php echo esc_attr($opts['cf_zone_id'] ?? ''); ?>" placeholder="np. 456def..." />
        </div>

        <div class="het-seo-muted" style="margin-top:10px">W kolejnej paczce: podgląd stanu SSL/CDN/cache (best-effort).</div>
      </div>

      <div class="het-seo-card" style="padding:14px">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
          <div>
            <div style="font-weight:700">Google Search Console</div>
            <div class="het-seo-muted">Wskaż właściwość (property) i włącz integrację.</div>
          </div>
          <div><?php echo $badge($gsc_enabled, 'Włączone', 'Wyłączone'); ?></div>
        </div>

        <label style="display:block;margin-top:10px">
          <input type="checkbox" name="gsc_enabled" value="1" <?php checked($gsc_enabled); ?> />
          Włącz integrację GSC (read-only)
        </label>

        <div style="margin-top:10px">
          <div class="het-seo-muted">Property</div>
          <input class="regular-text" type="text" name="gsc_property" value="<?php echo esc_attr($opts['gsc_property'] ?? ''); ?>" placeholder="sc-domain:twojadomena.pl" />
        </div>

        <div class="het-seo-muted" style="margin-top:10px">W kolejnej paczce: pobieranie TOP zapytań i błędów indeksacji (jeśli dostępne).</div>
      </div>
    </div>

    <p style="margin-top:12px">
      <button class="button button-primary" name="het_seo_status_save" value="1">
        Zapisz ustawienia integracji
      </button>
    </p>
  </form>
</div>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
