<?php
if (!defined('ABSPATH')) { exit; }

// Variables expected:
// - $tab (string)
// - $data (array)

if ($tab === 'cannibal'):
    $pairs = is_array($data['cannibal'] ?? null) ? $data['cannibal'] : [];
?>

<div class="het-card" style="padding:14px; margin-bottom:14px;">
  <h2 style="margin:0 0 6px;">Kanibalizacja</h2>
  <p style="margin:0; color:#50575e;">Podejrzane pary URL-i, które wyglądają jakby walczyły o tę samą intencję. Liczone z tytułów, H1 i nagłówków (best-effort).</p>
</div>

<div class="het-card" style="padding:0; overflow:auto;">
  <table class="widefat striped" style="margin:0;">
    <thead>
      <tr>
        <th style="width:84px;">Score</th>
        <th>URL A</th>
        <th>URL B</th>
        <th style="width:260px;">Powód</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($pairs)): ?>
      <tr><td colspan="4" style="padding:14px; color:#50575e;">Brak danych. Najpierw uruchom <strong>Analiza treści</strong> i/lub <strong>Dane URL</strong>, żeby wtyczka miała z czego liczyć podobieństwa.</td></tr>
    <?php else: foreach ($pairs as $p):
        $a = (int)($p['a'] ?? 0);
        $b = (int)($p['b'] ?? 0);
        $score = (float)($p['score'] ?? 0);
        $pa = $a ? get_post($a) : null;
        $pb = $b ? get_post($b) : null;
        $ua = $a ? get_permalink($a) : '';
        $ub = $b ? get_permalink($b) : '';
    ?>
      <tr>
        <td style="font-weight:700;"><?php echo esc_html(number_format($score, 2)); ?></td>
        <td>
          <?php if ($pa): ?>
            <div style="font-weight:600;"><?php echo esc_html($pa->post_title); ?></div>
            <div style="font-size:12px; color:#50575e;"><a href="<?php echo esc_url($ua); ?>" target="_blank" rel="noopener"><?php echo esc_html($ua); ?></a></div>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td>
          <?php if ($pb): ?>
            <div style="font-weight:600;"><?php echo esc_html($pb->post_title); ?></div>
            <div style="font-size:12px; color:#50575e;"><a href="<?php echo esc_url($ub); ?>" target="_blank" rel="noopener"><?php echo esc_html($ub); ?></a></div>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td style="color:#50575e;"><?php echo esc_html((string)($p['reason'] ?? '')); ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php
elseif ($tab === 'clusters'):
    $clusters = is_array($data['clusters'] ?? null) ? $data['clusters'] : [];
?>

<div class="het-card" style="padding:14px; margin-bottom:14px;">
  <h2 style="margin:0 0 6px;">Klastry tematyczne (best-effort)</h2>
  <p style="margin:0; color:#50575e;">Szybkie grupowanie treści, żebyś widział gdzie masz "silosy" i gdzie brakuje spójności. Na start grupujemy po kategorii i pokazujemy próbkę URL-i.</p>
</div>

<?php if (empty($clusters)): ?>
  <div class="het-card" style="padding:14px; color:#50575e;">Brak danych do klastrów.</div>
<?php else: ?>
  <div class="het-center__grid" style="grid-template-columns:repeat(3,minmax(0,1fr));">
    <?php foreach ($clusters as $c): ?>
      <div class="het-card" style="padding:14px;">
        <div style="display:flex; justify-content:space-between; gap:10px; align-items:baseline;">
          <h3 style="margin:0; font-size:16px;"><?php echo esc_html((string)$c['name']); ?></h3>
          <span style="font-weight:700;"><?php echo (int)($c['count'] ?? 0); ?></span>
        </div>
        <div style="margin-top:10px;">
          <?php foreach ((array)($c['items'] ?? []) as $it):
            $pid = (int)($it['post_id'] ?? 0);
            if (!$pid) { continue; }
            $url = get_permalink($pid);
          ?>
            <div style="margin:0 0 8px;">
              <div style="font-weight:600; font-size:13px; line-height:1.2;"><?php echo esc_html((string)($it['title'] ?? '')); ?></div>
              <div style="font-size:12px; color:#50575e;"><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php echo esc_html($url); ?></a></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php
else:
    $s = is_array($data['structure'] ?? null) ? $data['structure'] : [];
    $orphans = is_array($s['orphans'] ?? null) ? $s['orphans'] : [];
?>

<div class="het-card" style="padding:14px; margin-bottom:14px;">
  <h2 style="margin:0 0 6px;">Struktura serwisu</h2>
  <p style="margin:0; color:#50575e;">Podsumowanie architektury: liczba URL-i, potencjalne osierocone strony (0 linków wewnętrznych IN — best-effort z danych snapshotów).</p>
</div>

<div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:14px;">
  <div class="het-metric" style="padding:10px 12px; border:1px solid #dcdcde; border-radius:12px; background:#fff;">
    <div style="font-size:12px; color:#50575e;">Opublikowane posty+strony</div>
    <div style="font-size:22px; font-weight:700;"><?php echo (int)($s['posts_total'] ?? 0); ?></div>
  </div>
  <div class="het-metric" style="padding:10px 12px; border:1px solid #dcdcde; border-radius:12px; background:#fff;">
    <div style="font-size:12px; color:#50575e;">Podejrzane "orphan" URL</div>
    <div style="font-size:22px; font-weight:700;"><?php echo (int)count($orphans); ?></div>
  </div>
</div>

<div class="het-card" style="padding:0; overflow:auto;">
  <table class="widefat striped" style="margin:0;">
    <thead><tr><th>Orphan URL</th><th style="width:320px;">Link</th></tr></thead>
    <tbody>
    <?php if (empty($orphans)): ?>
      <tr><td colspan="2" style="padding:14px; color:#50575e;">Brak orphanów (albo brak danych snapshotów). Uruchom <strong>Dane URL → Zbierz dane</strong>, żeby policzyć linkowanie.</td></tr>
    <?php else: foreach ($orphans as $o): ?>
      <tr>
        <td style="font-weight:600;"><?php echo esc_html((string)($o['title'] ?? '')); ?></td>
        <td style="font-size:12px;"><a href="<?php echo esc_url((string)($o['url'] ?? '')); ?>" target="_blank" rel="noopener"><?php echo esc_html((string)($o['url'] ?? '')); ?></a></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php endif; ?>
