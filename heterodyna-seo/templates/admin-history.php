<?php
if (!defined('ABSPATH')) { exit; }
$base = admin_url('admin.php');
$post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
?>

<div class="het-card">
  <div class="het-card__head">
    <h2 style="margin:0">Historia SEO (DATA CORE)</h2>
    <div class="het-card__actions">
      <form method="get" action="<?php echo esc_url($base); ?>" style="display:inline">
        <input type="hidden" name="page" value="<?php echo esc_attr(HET_SEO_Admin::MENU_SLUG_HISTORY); ?>">
        <label style="margin-right:6px">Post ID</label>
        <input type="number" name="post_id" value="<?php echo esc_attr($post_id); ?>" style="width:120px">
        <button class="button" type="submit">Pokaż</button>
        <a class="button" href="<?php echo esc_url(add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_HISTORY], $base)); ?>">Wyczyść</a>
      </form>
    </div>
  </div>

  <p style="margin-top:10px; color:#555">
    To jest <strong>timeline zmian</strong> wyliczany ze snapshotów (best-effort). Im częściej zbierasz dane (cron lub ręcznie), tym lepiej.
  </p>

  <table class="widefat striped" style="margin-top:10px">
    <thead>
      <tr>
        <th>Data</th>
        <th>Zakres</th>
        <th>Event</th>
        <th>URL/Post</th>
        <th>Zmiana</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($hist)): ?>
        <tr><td colspan="5">Brak historii (na razie). Zrób snapshot w „Dane strony” albo „Dane URL”.</td></tr>
      <?php else: foreach ($hist as $h):
        $scope = (string)$h['scope'];
        $u = !empty($h['url']) ? (string)$h['url'] : '';
        $pid = (int)$h['post_id'];
        $target = $u ? $u : ($pid ? ('#' . $pid) : '—');
        $change = '';
        if ($h['old_value'] !== null || $h['new_value'] !== null) {
          $old = is_string($h['old_value']) ? $h['old_value'] : '';
          $new = is_string($h['new_value']) ? $h['new_value'] : '';
          // keep short
          $old = wp_strip_all_tags((string)$old);
          $new = wp_strip_all_tags((string)$new);
          if (strlen($old) > 120) $old = substr($old, 0, 120) . '…';
          if (strlen($new) > 120) $new = substr($new, 0, 120) . '…';
          $change = '<div><code>old</code> ' . esc_html($old) . '</div><div><code>new</code> ' . esc_html($new) . '</div>';
        }
      ?>
        <tr>
          <td><?php echo esc_html($h['created_at']); ?></td>
          <td><?php echo esc_html($scope); ?></td>
          <td><?php echo esc_html($h['event_key']); ?></td>
          <td><?php echo esc_html($target); ?></td>
          <td><?php echo $change; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
