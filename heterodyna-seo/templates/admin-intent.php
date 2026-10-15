<?php
if (!defined('ABSPATH')) { exit; }
$base = admin_url('admin.php');
?>
<div class="het-page">
  <div class="het-card" style="margin-bottom:14px">
    <div class="het-card__head">
      <div>
        <h2 style="margin:0">Intencja (CONTENT INTELLIGENCE)</h2>
        <div class="het-muted">Klasyfikacja best-effort: <strong>informacyjna</strong> / <strong>transakcyjna</strong> / <strong>nawigacyjna</strong>.</div>
      </div>
      <div class="het-card__actions">
        <a class="button" href="<?php echo esc_url(add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_CONTENT_INTEL], $base)); ?>">Przejdź do analizy treści</a>
      </div>
    </div>
  </div>

  <div class="het-card">
    <div class="het-card__head">
      <div class="het-muted">Ostatnie <?php echo (int)count($rows); ?> URL</div>
    </div>
    <div style="overflow:auto">
      <table class="widefat striped">
        <thead>
          <tr>
            <th>URL</th>
            <th>Tytuł</th>
            <th>Intencja</th>
            <th>Pewność</th>
            <th>Słowa</th>
            <th>FAQ</th>
            <th>Listy</th>
            <th>Tabele</th>
            <th>Obrazy</th>
            <th>Ostatnia analiza</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="10">Brak danych. Przejdź do „Analiza treści” i kliknij „Analizuj teraz”.</td></tr>
        <?php else: foreach ($rows as $r):
          $pid = (int)($r['post_id'] ?? 0);
          $url = (string)($r['url'] ?? '');
          $title = (string)($r['title'] ?? '');
          $intent = (string)($r['intent'] ?? 'unknown');
          $conf = isset($r['intent_conf']) ? (int)$r['intent_conf'] : 0;
          $w = isset($r['words']) ? (int)$r['words'] : 0;
          $has_faq = !empty($r['has_faq']);
          $lists = isset($r['lists']) ? (int)$r['lists'] : 0;
          $tables = isset($r['tables']) ? (int)$r['tables'] : 0;
          $images = isset($r['images']) ? (int)$r['images'] : 0;
          $detail_url = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_CONTENT_GAPS,'post_id'=>$pid], $base);
        ?>
          <tr>
            <td><a href="<?php echo esc_url($detail_url); ?>"><?php echo esc_html($url); ?></a></td>
            <td><?php echo esc_html($title); ?></td>
            <td><strong><?php echo esc_html($intent); ?></strong></td>
            <td><?php echo esc_html($conf . '%'); ?></td>
            <td><?php echo esc_html($w); ?></td>
            <td><?php echo $has_faq ? '✅' : '—'; ?></td>
            <td><?php echo esc_html($lists); ?></td>
            <td><?php echo esc_html($tables); ?></td>
            <td><?php echo esc_html($images); ?></td>
            <td><?php echo esc_html((string)($r['created_at'] ?? '')); ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php $this->footer_shell(); ?>
