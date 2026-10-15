<?php if (!defined('ABSPATH')) { exit; }
?>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-bell"></span> Alerty</div>
  <div class="het-seo-card__sub">Lokalne reguły + historia zdarzeń. Bez SaaS, bez subskrypcji — tylko proste, stabilne mechanizmy.</div>
</div>

<div class="het-seo-grid" style="margin-top:12px">
  <div class="het-seo-card">
    <div class="het-seo-card__title">Reguły</div>
    <form method="post">
      <?php wp_nonce_field('het_seo_alerts_save'); ?>
      <div class="het-field">
        <label>Email do powiadomień (opcjonalnie)</label>
        <input type="email" name="alerts_email_to" value="<?php echo esc_attr($alert_rules['404_spike']['email_to'] ?? ''); ?>" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
        <div class="het-muted het-small">Jeśli puste — zapisy będą tylko w historii zdarzeń.</div>
      </div>

      <div class="het-field" style="margin-top:12px">
        <label><input type="checkbox" name="rule_404_enabled" value="1" <?php checked(!empty($alert_rules['404_spike']['enabled'])); ?>> Wzrost 404</label>
        <div class="het-row" style="gap:8px">
          <div style="flex:1">
            <span class="het-muted het-small">Próg (%)</span>
            <input type="number" step="1" min="0" name="rule_404_threshold" value="<?php echo esc_attr($alert_rules['404_spike']['threshold'] ?? 50); ?>">
          </div>
          <div style="flex:1">
            <span class="het-muted het-small">Okno (h)</span>
            <input type="number" step="1" min="1" name="rule_404_window" value="<?php echo esc_attr($alert_rules['404_spike']['window_hours'] ?? 24); ?>">
          </div>
        </div>
      </div>

      <div class="het-field" style="margin-top:10px">
        <label><input type="checkbox" name="rule_uptime_enabled" value="1" <?php checked(!empty($alert_rules['uptime']['enabled'])); ?>> Uptime (robots/sitemap/security)</label>
      </div>

      <div class="het-field" style="margin-top:10px">
        <label><input type="checkbox" name="rule_psi_enabled" value="1" <?php checked(!empty($alert_rules['psi_low']['enabled'])); ?>> Niski PSI (perf)</label>
        <div class="het-row" style="gap:8px">
          <div style="flex:1">
            <span class="het-muted het-small">Próg (0–100)</span>
            <input type="number" step="1" min="0" max="100" name="rule_psi_threshold" value="<?php echo esc_attr($alert_rules['psi_low']['threshold'] ?? 50); ?>">
          </div>
        </div>
      </div>

      <button class="button button-primary" name="het_seo_alerts_save" value="1" style="margin-top:12px">Zapisz reguły</button>
    </form>

    <form method="post" style="margin-top:12px">
      <?php wp_nonce_field('het_seo_alerts_run_now'); ?>
      <button class="button" name="het_seo_alerts_run_now" value="1">Uruchom teraz</button>
      <span class="het-muted het-small" style="margin-left:8px">CRON: <code>het_seo_cron_alerts_daily</code></span>
    </form>
  </div>

  <div class="het-seo-card">
    <div class="het-seo-card__title">Jak to działa</div>
    <ul class="het-list">
      <li>404 spike: porównuje ostatnie okno vs poprzednie okno</li>
      <li>Uptime: sprawdza HTTP robots.txt / sitemap.xml / security.txt</li>
      <li>PSI low: patrzy na ostatnie wyniki PSI (perf)</li>
    </ul>
    <p class="het-muted het-small">Reguły są lokalne, bez żadnych „planów”. Jeśli kiedyś zechcesz — podłączymy kanały (webhook/telegram), ale to nie jest wymagane.</p>
  </div>
</div>

<div class="het-seo-card" style="margin-top:12px">
  <div class="het-seo-card__title">Historia zdarzeń</div>
  <?php if (empty($alert_events)): ?>
    <p class="het-muted">Brak zdarzeń.</p>
  <?php else: ?>
    <div class="het-table-wrap">
      <table class="widefat striped">
        <thead>
          <tr><th>Data</th><th>Reguła</th><th>Severity</th><th>Komunikat</th></tr>
        </thead>
        <tbody>
        <?php foreach ($alert_events as $e): ?>
          <tr>
            <td class="het-small"><?php echo esc_html($e['created_at']); ?></td>
            <td><code><?php echo esc_html($e['rule_key']); ?></code></td>
            <td><?php echo esc_html($e['severity']); ?></td>
            <td><?php echo esc_html($e['message']); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
