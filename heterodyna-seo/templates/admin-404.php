<?php if (!defined('ABSPATH')) { exit; }
?>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-warning"></span> Monitor 404</div>
  <div class="het-seo-card__sub">Loguje 404 publiczne. Tabela rośnie — w kolejnych paczkach dojdą filtry, eksport i auto-reguły.</div>
</div>

<div class="het-seo-card">
  <div class="het-tablewrap">
    <table class="widefat striped">
      <thead>
        <tr>
          <th>URL</th>
          <th>Hits</th>
          <th>Ostatnio</th>
          <th>Referrer</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="5"><em>Brak danych.</em></td></tr>
        <?php else: foreach ($rows as $r): ?>
          <?php
            $path = '';
            $p = wp_parse_url((string)$r->url);
            if (!empty($p['path'])) { $path = (string)$p['path']; }
            if ($path === '') { $path = '/'; }
            $u_redir = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_REDIRECTS, 'from'=>$path], admin_url('admin.php'));
          ?>
          <tr>
            <td><code><?php echo esc_html($r->url); ?></code></td>
            <td><?php echo intval($r->hits); ?></td>
            <td><?php echo esc_html($r->last_seen); ?></td>
            <td class="het-muted"><?php echo esc_html(mb_substr((string)$r->referrer,0,120)); ?></td>
            <td style="white-space:nowrap">
              <a class="button button-small" href="<?php echo esc_url($u_redir); ?>">Dodaj przekierowanie</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
