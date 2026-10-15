<?php
if (!defined('ABSPATH')) { exit; }
$base = admin_url('admin.php');
$notice = isset($_GET['het_notice']) ? sanitize_key($_GET['het_notice']) : '';
if ($notice === 'collected') {
  echo '<div class="notice notice-success"><p>Zebrano snapshot dla wybranego URL.</p></div>';
}
?>

<div class="het-card">
  <div class="het-card__head">
    <h2 style="margin:0">Dane URL (DATA CORE)</h2>
    <div class="het-card__actions">
      <form method="get" action="<?php echo esc_url($base); ?>" style="display:inline">
        <input type="hidden" name="page" value="<?php echo esc_attr(HET_SEO_Admin::MENU_SLUG_DATA_URL); ?>">
        <label style="margin-right:8px">Limit:
          <input type="number" name="limit" value="<?php echo esc_attr($limit); ?>" min="25" max="500" style="width:90px">
        </label>
        <button class="button" type="submit">Odśwież</button>
      </form>
    </div>
  </div>

  <div style="margin:12px 0 8px">
    <form method="post" action="<?php echo esc_url($base); ?>?page=<?php echo esc_attr(HET_SEO_Admin::MENU_SLUG_DATA_URL); ?>" class="het-inline" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <?php wp_nonce_field('het_seo_collect_url'); ?>
      <input type="hidden" name="het_seo_collect_url" value="1">
      <label>Post ID:
        <input type="number" name="post_id" value="0" min="1" style="width:110px">
      </label>
      <button class="button button-primary" type="submit">Zbierz snapshot dla Post ID</button>
      <span style="opacity:.75">(snapshot zapisuje metryki + wykrywa różnice w historii)</span>
    </form>
  </div>

  <table class="widefat striped">
    <thead>
      <tr>
        <th style="width:70px">ID</th>
        <th>URL / Tytuł</th>
        <th style="width:90px">Słowa</th>
        <th style="width:150px">Nagłówki</th>
        <th style="width:160px">Linki (in/out)</th>
        <th style="width:220px">Meta / Index</th>
        <th style="width:120px">PSI mob.</th>
        <th style="width:120px">Ostatni snapshot</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($urls)): ?>
        <tr><td colspan="8">Brak danych.</td></tr>
      <?php else: foreach ($urls as $r):
        $r = is_array($r) ? $r : [];
        $post_id = (int)($r['post_id'] ?? 0);
        $edit = $post_id ? get_edit_post_link($post_id) : '';
        $content = is_array(($r['content'] ?? null)) ? $r['content'] : [];
        $h = is_array(($content['headings'] ?? $r['heads'] ?? null)) ? ($content['headings'] ?? $r['heads']) : [];
        $links = is_array(($r['links'] ?? null)) ? $r['links'] : [];
        $meta = is_array(($r['meta'] ?? null)) ? $r['meta'] : [];
        $words = (int)($content['words'] ?? $r['words'] ?? 0);
        $meta_bits = [];
        if (!empty($meta['title'])) { $meta_bits[] = 'title✅'; } else { $meta_bits[] = 'title❌'; }
        if (!empty($meta['description'])) { $meta_bits[] = 'desc✅'; } else { $meta_bits[] = 'desc❌'; }
        if (!empty($meta['canonical'])) { $meta_bits[] = 'canon'; }
        if (!empty($meta['noindex'])) { $meta_bits[] = 'noindex'; }
        if (!empty($meta['nofollow'])) { $meta_bits[] = 'nofollow'; }
        $meta_txt = implode(' · ', $meta_bits);
        $psi = (isset($r['psi']) && is_array($r['psi']) && isset($r['psi']['perf']) && $r['psi']['perf'] !== null) ? (string)(int)$r['psi']['perf'] : '—';
        $snap = '—';
      ?>
        <tr>
          <td><?php echo esc_html((string)$post_id); ?></td>
          <td>
            <div style="font-weight:600">
              <?php if ($edit): ?>
                <a href="<?php echo esc_url($edit); ?>" target="_blank" rel="noopener"><?php echo esc_html($r['title']); ?></a>
              <?php else: ?>
                <?php echo esc_html($r['title']); ?>
              <?php endif; ?>
            </div>
            <div style="font-size:12px;opacity:.8">
              <a href="<?php echo esc_url($r['url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($r['url']); ?></a>
              · <a href="<?php echo esc_url(add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_HISTORY,'post_id'=>$post_id], $base)); ?>">historia</a>
            </div>
          </td>
          <td><?php echo esc_html((string)$words); ?></td>
          <td><?php echo esc_html('H1:' . (int)($h['h1'] ?? 0) . ' H2:' . (int)($h['h2'] ?? 0) . ' H3:' . (int)($h['h3'] ?? 0)); ?></td>
          <td><?php echo esc_html('in:' . (int)($links['internal_out'] ?? 0) . ' / out:' . (int)($links['external_out'] ?? 0)); ?></td>
          <td><?php echo esc_html($meta_txt); ?></td>
          <td><?php echo esc_html($psi); ?></td>
          <td><?php echo esc_html($snap); ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
