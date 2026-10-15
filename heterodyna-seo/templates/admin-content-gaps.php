<?php
if (!defined('ABSPATH')) { exit; }
$base = admin_url('admin.php');
?>
<div class="het-page">
  <div class="het-card" style="margin-bottom:14px">
    <div class="het-card__head">
      <div>
        <h2 style="margin:0">Luki treści (CONTENT INTELLIGENCE)</h2>
        <div class="het-muted">Kliknij URL, aby zobaczyć checklistę braków. Wnioski są heurystyczne, ale dają szybkie "todo" dla copywritera.</div>
      </div>
      <div class="het-card__actions">
        <form method="get" action="<?php echo esc_url($base); ?>" style="display:flex;gap:8px;align-items:center">
          <input type="hidden" name="page" value="<?php echo esc_attr(HET_SEO_Admin::MENU_SLUG_CONTENT_GAPS); ?>">
          <label class="het-muted">post_id</label>
          <input type="number" name="post_id" value="<?php echo isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0; ?>" min="0" style="width:120px">
          <button class="button">Pokaż</button>
        </form>
      </div>
    </div>
  </div>

  <?php if (!empty($detail) && !empty($detail['gaps'])): ?>
    <div class="het-card" style="margin-bottom:14px">
      <div class="het-card__head">
        <div>
          <h3 style="margin:0">Checklist: <?php echo esc_html($detail['title'] ?? ''); ?></h3>
          <div class="het-muted"><?php echo esc_html($detail['url'] ?? ''); ?></div>
        </div>
        <div class="het-card__actions">
          <span class="het-pill">Intencja: <strong><?php echo esc_html(strtoupper($detail['intent'] ?? 'n/a')); ?></strong></span>
          <span class="het-pill">Słowa: <strong><?php echo esc_html((string)($detail['metrics']['words'] ?? '0')); ?></strong></span>
        </div>
      </div>
      <div class="het-card__body">
        <ul style="margin:0;padding-left:18px">
          <?php foreach ((array)$detail['gaps'] as $g): ?>
            <li><?php echo esc_html($g); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  <?php endif; ?>

  <div class="het-card">
    <div class="het-card__head">
      <div>
        <h3 style="margin:0">URL z największymi lukami (top)</h3>
        <div class="het-muted">Przejrzyj listę, kliknij i dostajesz checklistę.</div>
      </div>
    </div>
    <div class="het-card__body">
      <table class="widefat striped">
        <thead>
          <tr>
            <th>URL</th>
            <th>Intencja</th>
            <th>Słowa</th>
            <th>Braki</th>
            <th>Ostatnia analiza</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ((array)$rows as $r):
            $gap_count = (int)($r['gaps_count'] ?? 0);
            if ($gap_count <= 0) { continue; }
            $u = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_CONTENT_GAPS,'post_id'=>(int)($r['post_id']??0)], $base);
          ?>
            <tr>
              <td>
                <a href="<?php echo esc_url($u); ?>"><strong><?php echo esc_html($r['title'] ?? ''); ?></strong></a>
                <div class="het-muted" style="max-width:720px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo esc_html($r['url'] ?? ''); ?></div>
              </td>
              <td><span class="het-pill"><?php echo esc_html(strtoupper($r['intent'] ?? 'n/a')); ?></span></td>
              <td><?php echo esc_html((string)($r['words'] ?? 0)); ?></td>
              <td><strong><?php echo esc_html((string)$gap_count); ?></strong></td>
              <td><?php echo esc_html($r['created_at'] ?? ''); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php $this->footer_shell(); ?>
