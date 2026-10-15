<?php if (!defined('ABSPATH')) { exit; }
?>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-yes-alt"></span> Testy usług</div>
  <div class="het-seo-card__sub">Szybka diagnostyka: statusy HTTP oraz harmonogramy CRON.</div>
</div>

<div class="het-seo-card" style="margin-top:12px">
  <div class="het-seo-card__title">HTTP</div>
  <div class="het-tablewrap">
    <table class="widefat striped">
      <thead><tr><th>Usługa</th><th>Status</th><th>Szczegóły</th></tr></thead>
      <tbody>
        <?php foreach (($checks ?? []) as $c): ?>
          <tr>
            <td><?php echo esc_html($c['name']); ?></td>
            <td><?php echo !empty($c['ok']) ? 'OK' : 'BŁĄD'; ?></td>
            <td>
              <code><?php echo esc_html($c['url']); ?></code>
              <?php if (!empty($c['code'])): ?> — HTTP <?php echo (int) $c['code']; ?><?php endif; ?>
              <?php if (!empty($c['detail'])): ?> — <?php echo esc_html($c['detail']); ?><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="het-seo-card" style="margin-top:12px">
  <div class="het-seo-card__title">CRON</div>
  <div class="het-small">
    <p><strong>PSI (daily):</strong> <?php echo $next_psi ? esc_html(wp_date('Y-m-d H:i:s', $next_psi)) : 'brak'; ?></p>
    <p><strong>Queue (hourly):</strong> <?php echo $next_queue ? esc_html(wp_date('Y-m-d H:i:s', $next_queue)) : 'brak'; ?></p>
    <p><strong>Alerts (daily):</strong> <?php echo $next_alerts ? esc_html(wp_date('Y-m-d H:i:s', $next_alerts)) : 'brak'; ?></p>
  </div>
  <div class="het-muted het-small">Uwaga: PSI/Alerts są tu jako „parytet UI” do starszych paczek. Wypełnimy logikę, gdy podepniesz konkretne kolejki.</div>
</div>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
