<?php
if (!defined('ABSPATH')) { exit; }
$tab = (string)($data['tab'] ?? 'timeline');
$base = admin_url('admin.php');
$tabs = [
  'timeline' => 'Timeline',
  'alerts'   => 'Alerty',
  'updates'  => 'Aktualizacje Google',
];
$delta = is_array(($data['delta'] ?? null)) ? $data['delta'] : ['changed'=>[]];
$changed = is_array(($delta['changed'] ?? null)) ? $delta['changed'] : [];
?>
<div class="het-card">
  <div class="het-card__head">
    <h2 style="margin:0">Monitoring SEO</h2>
    <div style="opacity:.75;font-size:12px">TABY: Timeline · Alerty · Aktualizacje Google</div>
  </div>

  <div class="het-metrics" style="margin-top:10px">
    <div class="het-metric">
      <div class="het-metric__label">Detektor zmian</div>
      <div class="het-metric__value"><?php echo esc_html($changed ? count($changed) : 0); ?></div>
    </div>
    <div class="het-metric">
      <div class="het-metric__label">Ostatni snapshot</div>
      <div class="het-metric__value"><?php echo esc_html((string)($delta['latest_at'] ?? '—')); ?></div>
    </div>
    <div class="het-metric">
      <div class="het-metric__label">Poprzedni snapshot</div>
      <div class="het-metric__value"><?php echo esc_html((string)($delta['prev_at'] ?? '—')); ?></div>
    </div>
  </div>

  <?php if ($changed): ?>
    <div style="margin-top:10px;padding:10px;border:1px solid #e5e7eb;border-radius:12px;background:#fff">
      <div style="font-weight:600;margin-bottom:6px">Zmiany wykryte (best-effort)</div>
      <div style="display:flex;flex-wrap:wrap;gap:8px">
        <?php foreach ($changed as $c): ?>
          <span style="padding:4px 8px;border:1px solid #e5e7eb;border-radius:999px;font-size:12px"><?php echo esc_html($c); ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <h2 class="nav-tab-wrapper" style="margin-top:14px">
    <?php foreach ($tabs as $k=>$label): ?>
      <?php $url = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_MONITORING,'tab'=>$k], $base); ?>
      <a class="nav-tab <?php echo $tab===$k?'nav-tab-active':''; ?>" href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a>
    <?php endforeach; ?>
  </h2>

  <div style="padding-top:10px">
    <?php if ($tab === 'alerts'): ?>
      <?php $events = is_array(($data['alerts'] ?? null)) ? $data['alerts'] : []; ?>
      <table class="widefat striped">
        <thead><tr>
          <th style="width:90px">Severity</th>
          <th>Message</th>
          <th style="width:180px">At</th>
        </tr></thead>
        <tbody>
          <?php if (!$events): ?>
            <tr><td colspan="3">Brak alertów.</td></tr>
          <?php else: foreach ($events as $e): $e=is_array($e)?$e:[]; ?>
            <tr>
              <td><?php echo esc_html((string)($e['severity'] ?? '—')); ?></td>
              <td>
                <div style="font-weight:600"><?php echo esc_html((string)($e['rule_key'] ?? 'event')); ?></div>
                <div><?php echo esc_html((string)($e['message'] ?? '')); ?></div>
              </td>
              <td><?php echo esc_html((string)($e['created_at'] ?? '')); ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>

    <?php elseif ($tab === 'updates'): ?>
      <?php $updates = is_array(($data['updates'] ?? null)) ? $data['updates'] : []; ?>

      <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-start">
        <form method="post" action="<?php echo esc_url($base.'?page='.HET_SEO_Admin::MENU_SLUG_MONITORING.'&tab=updates'); ?>" style="min-width:320px;flex:1">
          <?php wp_nonce_field('het_seo_add_google_update'); ?>
          <input type="hidden" name="het_seo_add_google_update" value="1">
          <div style="font-weight:600;margin-bottom:8px">Dodaj aktualizację Google</div>

          <div style="display:grid;grid-template-columns:140px 1fr;gap:8px;align-items:center">
            <div>Data</div>
            <input type="date" name="date" value="<?php echo esc_attr(gmdate('Y-m-d')); ?>">

            <div>Tytuł</div>
            <input type="text" name="title" placeholder="np. Core Update">

            <div>Typ</div>
            <select name="type">
              <option value="core">core</option>
              <option value="spam">spam</option>
              <option value="reviews">reviews</option>
              <option value="helpful">helpful</option>
              <option value="other">other</option>
            </select>

            <div>Notatki</div>
            <textarea name="notes" rows="3" placeholder="krótko: co zauważyłeś / link / komentarz"></textarea>
          </div>

          <div style="margin-top:10px">
            <button class="button button-primary" type="submit">Dodaj</button>
          </div>
        </form>

        <div style="flex:2;min-width:420px">
          <div style="font-weight:600;margin-bottom:8px">Historia aktualizacji</div>
          <table class="widefat striped">
            <thead><tr>
              <th style="width:120px">Data</th>
              <th style="width:90px">Typ</th>
              <th>Tytuł</th>
            </tr></thead>
            <tbody>
              <?php if (!$updates): ?>
                <tr><td colspan="3">Brak wpisów.</td></tr>
              <?php else: foreach ($updates as $u): $u=is_array($u)?$u:[]; ?>
                <tr>
                  <td><?php echo esc_html((string)($u['event_date'] ?? '')); ?></td>
                  <td><?php echo esc_html((string)($u['event_type'] ?? '')); ?></td>
                  <td>
                    <div style="font-weight:600"><?php echo esc_html((string)($u['title'] ?? '')); ?></div>
                    <?php if (!empty($u['notes'])): ?>
                      <div style="opacity:.8"><?php echo esc_html((string)$u['notes']); ?></div>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php else: ?>
      <?php $history = is_array(($data['history'] ?? null)) ? $data['history'] : []; ?>
      <table class="widefat striped">
        <thead><tr>
          <th style="width:160px">At</th>
          <th style="width:90px">Type</th>
          <th>Message</th>
        </tr></thead>
        <tbody>
          <?php if (!$history): ?>
            <tr><td colspan="3">Brak historii.</td></tr>
          <?php else: foreach ($history as $h): $h=is_array($h)?$h:[]; ?>
            <tr>
              <td><?php echo esc_html((string)($h['created_at'] ?? '')); ?></td>
              <td><?php echo esc_html((string)($h['event_type'] ?? '')); ?></td>
              <td><?php echo esc_html((string)($h['message'] ?? '')); ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
