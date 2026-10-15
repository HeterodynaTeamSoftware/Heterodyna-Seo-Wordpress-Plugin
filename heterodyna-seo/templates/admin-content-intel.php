<?php
if (!defined('ABSPATH')) { exit; }
$base = admin_url('admin.php');
$notice = isset($_GET['het_notice']) ? sanitize_text_field($_GET['het_notice']) : '';
?>
<div class="het-page">
  <?php if ($notice === 'analyzed'): ?>
    <div class="notice notice-success"><p>Analiza treści wykonana ✅</p></div>
  <?php endif; ?>

  <div class="het-card" style="margin-bottom:14px">
    <div class="het-card__head">
      <div>
        <h2 style="margin:0">Analiza treści (CONTENT INTELLIGENCE)</h2>
        <div class="het-muted">Zalew danych: słowa, zdania, paragrafy, elementy treści, "czy jest FAQ", "czy są tabele", itp.</div>
      </div>
      <div class="het-card__actions">
        <form method="post" style="display:flex; gap:8px; align-items:center">
          <?php wp_nonce_field('het_seo_analyze_now'); ?>
          <select name="post_id" style="min-width:260px">
            <option value="0">Szybki batch (25 URL)</option>
            <?php
              $q = new WP_Query([
                'post_type' => ['post','page'],
                'post_status' => 'publish',
                'posts_per_page' => 200,
                'orderby' => 'modified',
                'order' => 'DESC',
                'no_found_rows' => true,
              ]);
              foreach ((array)$q->posts as $p) {
                echo '<option value="' . (int)$p->ID . '">' . esc_html(get_the_title($p->ID)) . ' (#' . (int)$p->ID . ')</option>';
              }
            ?>
          </select>
          <button class="button button-primary" type="submit" name="het_seo_analyze_now" value="1">Zbierz dane teraz</button>
        </form>
      </div>
    </div>
  </div>

  <div class="het-card">
    <div class="het-card__head">
      <div><h3 style="margin:0">Przegląd URL (<?php echo (int)$limit; ?>)</h3></div>
      <div class="het-card__actions">
        <a class="button" href="<?php echo esc_url(add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_CONTENT_GAPS], $base)); ?>">Przejdź do Luk treści</a>
      </div>
    </div>

    <div style="overflow:auto">
      <table class="widefat striped" style="min-width:1100px">
        <thead>
          <tr>
            <th>URL</th>
            <th>Intencja</th>
            <th>Słowa</th>
            <th>Zdania</th>
            <th>Paragrafy</th>
            <th>Obrazy</th>
            <th>Listy</th>
            <th>Tabele</th>
            <th>FAQ</th>
            <th>Ostatnia analiza</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="10">Brak danych. Kliknij „Zbierz dane teraz”.</td></tr>
        <?php else: foreach ($rows as $r):
          $pid = (int)($r['post_id'] ?? 0);
          $url = (string)($r['url'] ?? '');
          $m = is_array($r['metrics']) ? $r['metrics'] : [];
          $intent = (string)($r['intent'] ?? '');
          $detail_url = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_CONTENT_GAPS,'post_id'=>$pid], $base);
        ?>
          <tr>
            <td>
              <a href="<?php echo esc_url($detail_url); ?>"><strong><?php echo esc_html(get_the_title($pid)); ?></strong></a>
              <div class="het-muted"><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php echo esc_html($url); ?></a></div>
            </td>
            <td><?php echo esc_html($intent ?: 'n/a'); ?></td>
            <td><?php echo (int)($m['words'] ?? 0); ?></td>
            <td><?php echo (int)($m['sentences'] ?? 0); ?></td>
            <td><?php echo (int)($m['paragraphs'] ?? 0); ?></td>
            <td><?php echo (int)($m['images'] ?? 0); ?></td>
            <td><?php echo (int)($m['lists'] ?? 0); ?></td>
            <td><?php echo (int)($m['tables'] ?? 0); ?></td>
            <td><?php echo !empty($m['has_faq']) ? '✅' : '—'; ?></td>
            <td><?php echo esc_html((string)($r['created_at'] ?? '')); ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php $this->footer_shell(); ?>
