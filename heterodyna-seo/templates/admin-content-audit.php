<?php
if (!defined('ABSPATH')) { exit; }
// Expects: $items, $query, $threshold
?>
<div class="het-seo-shell">
  <div class="het-page">
    <div class="het-page__head">
      <h1 class="het-page__title">Audyt treści</h1>
      <p class="het-page__sub">Szybki przegląd treści wymagających poprawy (bez automatycznych zmian). Domyślny próg „krótkiej treści”: <strong><?php echo (int)$threshold; ?></strong> znaków.</p>
    </div>

    <div class="het-card" style="margin-top:14px;">
      <div class="het-card__head">
        <div>
          <div class="het-card__title"><span class="dashicons dashicons-edit-page"></span> Co wymaga uwagi</div>
          <div class="het-muted het-small">Lista jest ograniczona do 50 ostatnio modyfikowanych elementów (post/page/product). To celowo — ma być szybkie i „dla klienta”.</div>
        </div>
        <div class="het-card__actions">
          <a class="button button-secondary" href="admin.php?page=<?php echo esc_attr(HET_SEO_Admin::MENU_SLUG_META_AUDIT); ?>">Audyt meta</a>
          <a class="button button-secondary" href="admin.php?page=<?php echo esc_attr(HET_SEO_Admin::MENU_SLUG_SOCIAL); ?>">Social Media</a>
        </div>
      </div>

      <?php if (empty($items)): ?>
        <div class="het-muted">Wygląda dobrze ✅ Nie wykryto oczywistych braków w ostatnich treściach.</div>
      <?php else: ?>
        <table class="widefat striped" style="margin-top:10px;">
          <thead>
            <tr>
              <th style="width:70px">ID</th>
              <th style="width:110px">Typ</th>
              <th>Tytuł</th>
              <th style="width:170px">Problemy</th>
              <th style="width:140px">Akcje</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($items as $row): ?>
            <tr>
              <td><?php echo (int)$row['ID']; ?></td>
              <td><code><?php echo esc_html($row['type']); ?></code></td>
              <td>
                <strong><?php echo esc_html($row['title']); ?></strong>
                <div class="het-muted het-small">Zmodyfikowano: <?php echo esc_html($row['modified']); ?> • Długość: <?php echo (int)$row['len']; ?> znaków</div>
              </td>
              <td>
                <ul class="het-list" style="margin:0;">
                  <?php foreach ($row['flags'] as $f): ?>
                    <li><?php echo esc_html($f); ?></li>
                  <?php endforeach; ?>
                </ul>
              </td>
              <td>
                <a class="button button-small" href="<?php echo esc_url($row['edit']); ?>">Edytuj</a>
                <a class="button button-small" target="_blank" rel="noopener" href="<?php echo esc_url($row['view']); ?>">Podgląd</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="het-card" style="margin-top:14px;">
      <div class="het-card__title"><span class="dashicons dashicons-lightbulb"></span> Szybkie wskazówki</div>
      <ul class="het-list">
        <li>Uzupełnij <strong>meta title</strong> i <strong>meta description</strong> dla najważniejszych stron (najlepiej: strona główna, oferta, kontakt, top kategorie).</li>
        <li>Dodaj co najmniej 1 obrazek do każdej kluczowej podstrony (featured image) — przyda się też do Social Media.</li>
        <li>Jeśli treść jest krótka, dopisz: FAQ, korzyści, przykłady, „dla kogo”, „jak to działa”.</li>
      </ul>
    </div>

  </div>
</div>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
