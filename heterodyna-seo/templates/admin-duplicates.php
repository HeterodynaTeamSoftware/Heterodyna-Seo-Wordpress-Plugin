<?php if (!defined('ABSPATH')) { exit; }

$post_types = get_post_types(['public'=>true], 'names');
unset($post_types['attachment']);

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 1000;
$limit = max(100, min(5000, $limit));

$mode = isset($_GET['mode']) ? sanitize_key($_GET['mode']) : 'title'; // title|desc

$q = new WP_Query([
  'post_type' => array_values($post_types),
  'posts_per_page' => $limit,
  'post_status' => 'publish',
  'orderby' => 'date',
  'order' => 'DESC',
  'no_found_rows' => true,
]);

$groups = [];
foreach ($q->posts as $p) {
  $val = '';
  if ($mode === 'desc') {
    $val = (string) get_post_meta($p->ID, 'het_seo_meta_description', true);
  } else {
    $val = (string) get_post_meta($p->ID, 'het_seo_meta_title', true);
  }
  $key = strtolower(trim(wp_strip_all_tags($val)));
  if ($key === '') { continue; }
  if (!isset($groups[$key])) { $groups[$key] = ['value'=>$val,'items'=>[]]; }
  $groups[$key]['items'][] = $p;
}

$dupes = [];
foreach ($groups as $g) {
  if (count($g['items']) >= 2) { $dupes[] = $g; }
}

// sort by group size desc
usort($dupes, function($a,$b){ return count($b['items']) - count($a['items']); });

$base = admin_url('admin.php');
$u_title = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_DUPLICATES,'mode'=>'title'], $base);
$u_desc  = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_DUPLICATES,'mode'=>'desc'], $base);

?>

<h1>Duplikaty meta</h1>
<p class="het-muted">Wykrywa duplikaty <strong><?php echo $mode === 'desc' ? 'opisów' : 'tytułów'; ?></strong> na podstawie pól <code>het_seo_meta_title</code> / <code>het_seo_meta_description</code>. To jest audyt „soft” — bez crawl.</p>

<div class="het-actions" style="margin:12px 0;">
  <a class="button <?php echo $mode==='title'?'button-primary':''; ?>" href="<?php echo esc_url($u_title); ?>">Duplikaty Title</a>
  <a class="button <?php echo $mode==='desc'?'button-primary':''; ?>" href="<?php echo esc_url($u_desc); ?>">Duplikaty Description</a>
  <form method="get" style="display:inline-block;margin-left:10px;">
    <input type="hidden" name="page" value="<?php echo esc_attr(HET_SEO_Admin::MENU_SLUG_DUPLICATES); ?>">
    <input type="hidden" name="mode" value="<?php echo esc_attr($mode); ?>">
    <label class="het-inline">Limit:
      <input type="number" name="limit" value="<?php echo esc_attr($limit); ?>" min="100" max="5000" style="width:110px;">
    </label>
    <button class="button">Odśwież</button>
  </form>
</div>

<?php if (empty($dupes)): ?>
  <div class="notice notice-success"><p>Brak duplikatów w ostatnich <?php echo intval($limit); ?> publikacjach.</p></div>
<?php else: ?>
  <div class="notice notice-warning"><p>Znaleziono grup duplikatów: <strong><?php echo intval(count($dupes)); ?></strong> (w limicie <?php echo intval($limit); ?>).</p></div>

  <?php foreach ($dupes as $g): ?>
    <div class="het-seo-card" style="margin:12px 0;">
      <div class="het-seo-card__title"><?php echo esc_html($g['value']); ?></div>
      <p class="het-muted">Wystąpienia: <strong><?php echo intval(count($g['items'])); ?></strong></p>

      <table class="widefat striped">
        <thead>
          <tr>
            <th>ID</th>
            <th>Tytuł wpisu</th>
            <th>Typ</th>
            <th>URL</th>
            <th>Akcje</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($g['items'] as $p): ?>
            <tr>
              <td><?php echo intval($p->ID); ?></td>
              <td><?php echo esc_html(get_the_title($p)); ?></td>
              <td><code><?php echo esc_html($p->post_type); ?></code></td>
              <td><a href="<?php echo esc_url(get_permalink($p)); ?>" target="_blank" rel="noopener"><?php echo esc_html(get_permalink($p)); ?></a></td>
              <td>
                <a class="button button-small" href="<?php echo esc_url(get_edit_post_link($p->ID)); ?>">Edytuj</a>
                <a class="button button-small" href="<?php echo esc_url(add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_META_AUDIT], $base)); ?>">Audyt meta</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
