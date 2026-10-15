<?php if (!defined('ABSPATH')) { exit; }
$base = admin_url('admin.php');
$u_center = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_CENTER], $base);
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 200;
$limit = max(25, min(500, $limit));
?>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-search"></span> Audyt meta</div>
  <div class="het-seo-card__sub">
    Lista ostatnich wpisów/stron i ich meta (title/description). Wykrywa braki, duplikaty oraz podejrzane długości.
  </div>
  <div class="het-actions" style="margin-top:10px">
    <a class="button" href="<?php echo esc_url($u_center); ?>">← Wróć do Centrum</a>
    <a class="button" href="<?php echo esc_url(add_query_arg(['limit'=>200])); ?>">Limit 200</a>
    <a class="button" href="<?php echo esc_url(add_query_arg(['limit'=>500])); ?>">Limit 500</a>
  </div>

  <form method="get" style="margin-top:12px">
    <input type="hidden" name="page" value="<?php echo esc_attr(HET_SEO_Admin::MENU_SLUG_META_AUDIT); ?>">
    <input type="hidden" name="limit" value="<?php echo intval($limit); ?>">
    <div class="het-actions" style="gap:10px;flex-wrap:wrap">
      <label class="het-inline"><input type="checkbox" name="issues" value="1" <?php checked(!empty($_GET['issues'])); ?>> tylko problemy</label>
      <label class="het-inline"><input type="checkbox" name="missing" value="1" <?php checked(!empty($_GET['missing'])); ?>> braki</label>
      <label class="het-inline"><input type="checkbox" name="dupes" value="1" <?php checked(!empty($_GET['dupes'])); ?>> duplikaty</label>
      <label class="het-inline"><input type="checkbox" name="toolong" value="1" <?php checked(!empty($_GET['toolong'])); ?>> za długie</label>
      <button class="button" type="submit">Filtruj</button>
      <a class="button" href="<?php echo esc_url(remove_query_arg(['issues','missing','dupes','toolong'])); ?>">Wyczyść</a>
    </div>
  </form>
</div>

<div class="het-seo-card">
  <div class="het-seo-card__title">Wyniki</div>

  <form method="post" style="margin-top:10px">
    <?php wp_nonce_field('het_seo_meta_bulk'); ?>
    <div class="het-actions" style="gap:10px;flex-wrap:wrap">
      <label class="het-inline"><input type="checkbox" id="hetMetaSelectAll"> zaznacz wszystko</label>
      <select name="bulk_action">
        <option value="">— Akcja masowa —</option>
        <option value="enqueue_missing">AI Auto: brakujące meta</option>
        <option value="enqueue_toolong">AI Auto: skróć (za długie)</option>
        <option value="enqueue_ctr">AI Auto: CTR-style</option>
      </select>
      <label class="het-inline"><input type="checkbox" name="dry_run" value="1" checked> dry-run</label>
      <button class="button button-primary" name="het_seo_meta_bulk" value="1">Wykonaj</button>
      <span class="het-muted het-small">Tip: jeśli odznaczysz dry-run, zmiany zostaną zapisane lokalnie.</span>
    </div>

    <div class="het-tablewrap" style="margin-top:10px">
      <table class="widefat striped">
      <thead>
        <tr>
          <th style="width:34px"></th>
          <th>ID</th>
          <th>Typ</th>
          <th>Tytuł (edytuj)</th>
          <th>Meta title</th>
          <th>Meta desc</th>
          <th>Canonical</th>
          <th>robots</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="8">Brak danych.</td></tr>
      <?php else: foreach ($rows as $r):
        $t_bad = ($r['len_t'] === 0 || $r['len_t'] > 70 || $r['dup_t']);
        $d_bad = ($r['len_d'] === 0 || $r['len_d'] > 165 || $r['dup_d']);
        $t_hint = [];
        if ($r['len_t'] === 0) { $t_hint[] = 'brak'; }
        if ($r['len_t'] > 70) { $t_hint[] = 'za długi'; }
        if ($r['dup_t']) { $t_hint[] = 'duplikat'; }
        $d_hint = [];
        if ($r['len_d'] === 0) { $d_hint[] = 'brak'; }
        if ($r['len_d'] > 165) { $d_hint[] = 'za długa'; }
        if ($r['dup_d']) { $d_hint[] = 'duplikat'; }
        $robots = [];
        if (!empty($r['noindex'])) { $robots[] = 'noindex'; }
        if (!empty($r['nofollow'])) { $robots[] = 'nofollow'; }
      ?>
        <tr>
          <td>
            <input class="hetMetaRow" type="checkbox" name="post_ids[]" value="<?php echo intval($r['ID']); ?>">
          </td>
          <td><?php echo intval($r['ID']); ?></td>
          <td><?php echo esc_html($r['type']); ?></td>
          <td>
            <div><a href="<?php echo esc_url($r['edit']); ?>"><?php echo esc_html($r['title']); ?></a></div>
            <div class="het-small"><a href="<?php echo esc_url($r['permalink']); ?>" target="_blank" rel="noopener">podgląd</a></div>
          </td>
          <td>
            <div class="<?php echo $t_bad ? 'het-badge het-badge--warn' : 'het-badge het-badge--ok'; ?>">
              <?php echo intval($r['len_t']); ?> znaków
              <?php if (!empty($t_hint)) { echo ' • ' . esc_html(implode(', ', $t_hint)); } ?>
            </div>
            <div class="het-small het-muted"><?php echo esc_html(mb_strimwidth($r['meta_title'], 0, 80, '…')); ?></div>
          </td>
          <td>
            <div class="<?php echo $d_bad ? 'het-badge het-badge--warn' : 'het-badge het-badge--ok'; ?>">
              <?php echo intval($r['len_d']); ?> znaków
              <?php if (!empty($d_hint)) { echo ' • ' . esc_html(implode(', ', $d_hint)); } ?>
            </div>
            <div class="het-small het-muted"><?php echo esc_html(mb_strimwidth($r['meta_desc'], 0, 110, '…')); ?></div>
          </td>
          <td>
            <?php if (!empty($r['canonical'])): ?>
              <div class="het-small"><a href="<?php echo esc_url($r['canonical']); ?>" target="_blank" rel="noopener"><?php echo esc_html(mb_strimwidth($r['canonical'], 0, 60, '…')); ?></a></div>
            <?php else: ?>
              <span class="het-muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (empty($robots)): ?>
              <span class="het-muted">index,follow</span>
            <?php else: ?>
              <span class="het-badge het-badge--warn"><?php echo esc_html(implode(',', $robots)); ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
      </table>
    </div>
  </form>
</div>

<script>
  (function(){
    const all = document.getElementById('hetMetaSelectAll');
    if(!all) return;
    all.addEventListener('change', function(){
      document.querySelectorAll('.hetMetaRow').forEach(cb=>{ cb.checked = all.checked; });
    });
  })();
</script>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
