<?php if (!defined('ABSPATH')) { exit; }
?>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-performance"></span> PSI</div>
  <div class="het-seo-card__sub">Kolejka PageSpeed Insights (v5). Jeśli nie podasz klucza API, pobrania będą kończyć się błędem „brak klucza”.</div>

  <?php if (isset($psi_diag) && is_array($psi_diag)): ?>
    <div class="het-seo-card" style="margin-top:12px">
      <div class="het-seo-card__title" style="font-size:13px"><span class="dashicons dashicons-info"></span> Diagnostyka</div>
      <div class="het-grid3" style="margin-top:10px">
        <div class="het-metric"><span>PSI API key</span><strong><?php echo !empty($psi_diag['has_key']) ? 'Ustawiony ✅' : 'Brak ❌'; ?></strong></div>
        <div class="het-metric"><span>Wyniki (DB)</span><strong><?php echo intval($psi_diag['results_count'] ?? 0); ?></strong></div>
        <div class="het-metric"><span>Kolejka</span><strong><?php
          $qc = $psi_diag['queue_counts'] ?? [];
          echo 'P:' . intval($qc['pending'] ?? 0) . ' R:' . intval($qc['running'] ?? 0) . ' D:' . intval($qc['done'] ?? 0) . ' E:' . intval($qc['error'] ?? 0);
        ?></strong></div>
      </div>
      <?php if (empty($psi_diag['results_count']) && empty($psi_diag['has_key'])): ?>
        <div class="het-muted" style="margin-top:8px">Wykres jest pusty, bo nie mamy jeszcze zapisanych wyników. Najpierw ustaw klucz PSI API, potem uruchom przetwarzanie kolejki.</div>
      <?php elseif (!empty($psi_diag['last_error'])): ?>
        <div class="het-muted" style="margin-top:8px">Ostatni błąd (ERROR): <code><?php echo esc_html(wp_trim_words((string)$psi_diag['last_error'], 30)); ?></code></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

<?php
// Build series for chart (group by strategy + url)
$series = [];
if (isset($psi_results) && is_array($psi_results)) {
  foreach ($psi_results as $r) {
    $url = isset($r['url']) ? (string)$r['url'] : '';
    $str = isset($r['strategy']) ? (string)$r['strategy'] : 'mobile';
    if ($url === '') { continue; }
    $key = $str . '|' . $url;
    if (!isset($series[$key])) { $series[$key] = []; }
    $series[$key][] = [
      't' => (string) ($r['fetched_at'] ?? ''),
      'perf' => isset($r['perf']) && $r['perf'] !== null ? (int)$r['perf'] : null,
      'seo' => isset($r['seo']) && $r['seo'] !== null ? (int)$r['seo'] : null,
      'pwa' => isset($r['pwa']) && $r['pwa'] !== null ? (int)$r['pwa'] : null,
      'bp' => isset($r['best_practices']) && $r['best_practices'] !== null ? (int)$r['best_practices'] : null,
      'acc' => isset($r['accessibility']) && $r['accessibility'] !== null ? (int)$r['accessibility'] : null,
    ];
  }
}
// Keep chronological order inside each series
foreach ($series as $k => $arr) { $series[$k] = array_reverse($arr); }
$series_keys = array_keys($series);
?>
<?php if (!empty($series_keys)): ?>
  <div class="het-seo-card" style="margin-top:12px">
    <div class="het-seo-card__title"><span class="dashicons dashicons-chart-line"></span> Wykres PSI</div>
    <div class="het-seo-card__sub">Podgląd trendu (Performance) dla ostatnich wyników.</div>

    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:10px">
      <label class="het-muted het-small" style="min-width:120px">Seria:</label>
      <select id="hetPsiSeries" style="min-width:420px;max-width:100%">
        <?php foreach ($series_keys as $k):
          $parts = explode('|', $k, 2);
          $label = (count($parts) === 2 ? strtoupper($parts[0]) . ' • ' . $parts[1] : $k);
        ?>
          <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($label); ?></option>
        <?php endforeach; ?>
      </select>
      <div class="het-muted het-small">Ostatnia wartość: <strong id="hetPsiLastValue">—</strong></div>
    </div>

    <div style="margin-top:10px">
      <canvas id="hetPsiChart" width="860" height="180" style="width:100%;height:180px;background:rgba(0,0,0,.08);border:1px solid var(--het-border);border-radius:14px"></canvas>
    </div>
    <script>
      window.HET_SEO_PSI_SERIES = <?php echo wp_json_encode($series); ?>;
    </script>
  </div>
<?php endif; ?>


</div>

<div class="het-seo-grid" style="margin-top:12px">
  <div class="het-seo-card">
    <div class="het-seo-card__title">Ustawienia</div>
    <form method="post">
      <?php wp_nonce_field('het_seo_psi_save'); ?>
      <div class="het-field">
        <label>PSI API key (Google)</label>
        <input type="text" name="psi_api_key" value="<?php echo esc_attr($psi_settings['api_key']); ?>" placeholder="AIza...">
        <div class="het-muted het-small">Klucz tylko do pobierania wyników. Bez niego kolejka nadal działa (statusy/error).</div>
      </div>
      <div class="het-field" style="margin-top:10px">
        <label>Domyślna strategia</label>
        <select name="psi_strategy">
          <option value="mobile" <?php selected($psi_settings['strategy'], 'mobile'); ?>>mobile</option>
          <option value="desktop" <?php selected($psi_settings['strategy'], 'desktop'); ?>>desktop</option>
        </select>
      </div>
      <button class="button button-primary" name="het_seo_psi_save" value="1" style="margin-top:12px">Zapisz</button>
    </form>
  </div>

  <div class="het-seo-card">
    <div class="het-seo-card__title">Dodaj do kolejki</div>
    <form method="post" style="margin-bottom:10px">
      <?php wp_nonce_field('het_seo_psi_enqueue'); ?>
      <div class="het-field">
        <label>URL</label>
        <input type="url" name="psi_url" placeholder="<?php echo esc_attr(home_url('/')); ?>">
      </div>
      <div class="het-field" style="margin-top:10px">
        <label>Strategia (teraz)</label>
        <select name="psi_strategy_now">
          <option value="mobile">mobile</option>
          <option value="desktop">desktop</option>
        </select>
      </div>
      <button class="button" name="het_seo_psi_enqueue" value="1" style="margin-top:12px">Dodaj</button>
    </form>

    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap">
      <?php wp_nonce_field('het_seo_psi_enqueue_sitemap'); ?>
      <button class="button" name="het_seo_psi_enqueue_sitemap" value="1">Dodaj z sitemap.xml</button>
    </form>

    <form method="post" style="margin-top:10px">
      <?php wp_nonce_field('het_seo_psi_run_now'); ?>
      <button class="button button-secondary" name="het_seo_psi_run_now" value="1">Przetwórz teraz (5)</button>
    </form>
  </div>
</div>

<div class="het-seo-card" style="margin-top:12px">
  <div class="het-seo-card__title">Kolejka</div>

  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:10px 0">
    <input type="hidden" name="page" value="<?php echo esc_attr(HET_SEO_Admin::MENU_SLUG_PSI); ?>">
    <label class="het-muted het-small">Status:</label>
    <select name="qstatus">
      <option value="" <?php selected(empty($psi_qstatus)); ?>>wszystkie</option>
      <option value="pending" <?php selected($psi_qstatus,'pending'); ?>>pending</option>
      <option value="running" <?php selected($psi_qstatus,'running'); ?>>running</option>
      <option value="done" <?php selected($psi_qstatus,'done'); ?>>done</option>
      <option value="error" <?php selected($psi_qstatus,'error'); ?>>error</option>
    </select>
    <button class="button" type="submit">Filtruj</button>
    <span class="het-muted het-small">• Kliknij URL żeby podejrzeć historię wyników.</span>
  </form>

  <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;margin:-2px 0 10px">
    <?php wp_nonce_field('het_seo_psi_q_action'); ?>
    <input type="hidden" name="het_seo_psi_q_action" value="1">
    <button class="button button-secondary" name="q_act" value="clear_done" onclick="return confirm('Wyczyścić elementy DONE z kolejki?');">Wyczyść DONE</button>
    <button class="button button-secondary" name="q_act" value="clear_error" onclick="return confirm('Wyczyścić elementy ERROR z kolejki?');">Wyczyść ERROR</button>
  </form>
  <?php if (empty($psi_queue)): ?>
    <p class="het-muted">Brak elementów w kolejce.</p>
  <?php else: ?>
    <div class="het-table-wrap">
      <table class="widefat striped">
        <thead>
          <tr>
            <th>ID</th><th>Status</th><th>Priorytet</th><th>Strategia</th><th>URL</th><th>Tries</th><th>Błąd</th><th>Updated</th><th>Akcje</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($psi_queue as $r): ?>
          <tr>
            <td><?php echo intval($r['id']); ?></td>
            <td><code><?php echo esc_html($r['status']); ?></code></td>
            <td><?php echo intval($r['priority']); ?></td>
            <td><?php echo esc_html($r['strategy']); ?></td>
            <td>
              <?php
                $hist_url = add_query_arg([
                  'page' => HET_SEO_Admin::MENU_SLUG_PSI,
                  'url' => rawurlencode((string)$r['url']),
                  'strategy' => (string)$r['strategy'],
                ], admin_url('admin.php'));
              ?>
              <a href="<?php echo esc_url($hist_url); ?>" title="Pokaż historię wyników">
                <?php echo esc_html(wp_trim_words((string)$r['url'], 10)); ?>
              </a>
            </td>
            <td><?php echo intval($r['tries']); ?></td>
            <td class="het-small"><?php echo esc_html(wp_trim_words((string)$r['last_error'], 16)); ?></td>
            <td class="het-small"><?php echo esc_html($r['updated_at']); ?></td>
            <td>
              <form method="post" style="display:flex;gap:6px;flex-wrap:wrap">
                <?php wp_nonce_field('het_seo_psi_q_action'); ?>
                <input type="hidden" name="het_seo_psi_q_action" value="1">
                <input type="hidden" name="qid" value="<?php echo intval($r['id']); ?>">
                <button class="button button-small" name="q_act" value="retry">Retry</button>
                <button class="button button-small" name="q_act" value="delete" onclick="return confirm('Usunąć ten element z kolejki?');">Usuń</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if (!empty($psi_view_url)): ?>
  <div class="het-seo-card" style="margin-top:12px">
    <div class="het-seo-card__title">Historia wyników</div>
    <div class="het-seo-card__sub">
      URL: <code><?php echo esc_html($psi_view_url); ?></code>
      <?php if (!empty($psi_view_strategy)): ?>
        • Strategia: <code><?php echo esc_html($psi_view_strategy); ?></code>
      <?php endif; ?>
    </div>
    <?php if (empty($psi_history)): ?>
      <p class="het-muted">Brak wyników dla tego URL.</p>
    <?php else: ?>
      <div class="het-table-wrap">
        <table class="widefat striped">
          <thead>
            <tr>
              <th>Data</th><th>Strategia</th><th>Perf</th><th>SEO</th><th>BP</th><th>A11y</th><th>PWA</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($psi_history as $h): ?>
              <tr>
                <td class="het-small"><?php echo esc_html((string)($h['fetched_at'] ?? '')); ?></td>
                <td><?php echo esc_html((string)($h['strategy'] ?? '')); ?></td>
                <td><strong><?php echo esc_html((string)($h['perf'] ?? '')); ?></strong></td>
                <td><?php echo esc_html((string)($h['seo'] ?? '')); ?></td>
                <td><?php echo esc_html((string)($h['best_practices'] ?? '')); ?></td>
                <td><?php echo esc_html((string)($h['accessibility'] ?? '')); ?></td>
                <td><?php echo esc_html((string)($h['pwa'] ?? '')); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="het-seo-card" style="margin-top:12px">
  <div class="het-seo-card__title">Ostatnie wyniki</div>
  <?php if (empty($psi_results)): ?>
    <p class="het-muted">Brak wyników.</p>
  <?php else: ?>
    <div class="het-table-wrap">
      <table class="widefat striped">
        <thead>
          <tr>
            <th>Data</th><th>Strategia</th><th>Perf</th><th>SEO</th><th>BP</th><th>A11y</th><th>PWA</th><th>URL</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($psi_results as $r): ?>
          <tr>
            <td class="het-small"><?php echo esc_html($r['fetched_at']); ?></td>
            <td><?php echo esc_html($r['strategy']); ?></td>
            <td><strong><?php echo esc_html($r['perf']); ?></strong></td>
            <td><?php echo esc_html($r['seo']); ?></td>
            <td><?php echo esc_html($r['best_practices']); ?></td>
            <td><?php echo esc_html($r['accessibility']); ?></td>
            <td><?php echo esc_html($r['pwa']); ?></td>
            <td>
              <?php
                $hist_url2 = add_query_arg([
                  'page' => HET_SEO_Admin::MENU_SLUG_PSI,
                  'url' => rawurlencode((string)$r['url']),
                  'strategy' => (string)$r['strategy'],
                ], admin_url('admin.php'));
              ?>
              <a href="<?php echo esc_url($hist_url2); ?>" title="Pokaż historię wyników">
                <?php echo esc_html(wp_trim_words((string)$r['url'], 10)); ?>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
