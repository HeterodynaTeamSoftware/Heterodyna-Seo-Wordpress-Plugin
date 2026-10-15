<?php if (!defined('ABSPATH')) { exit; }
$base = admin_url('admin.php');
$u_center = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_CENTER], $base);
$opts = isset($opts) && is_array($opts) ? $opts : get_option(HET_SEO_OPT, []);
$mode = isset($opts['default_canonical_mode']) ? (string)$opts['default_canonical_mode'] : 'off';
?>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-admin-links"></span> Kanoniczne + indeksowanie</div>
  <div class="het-seo-card__sub">Ustawienia globalne. Per wpis: w meta używamy pól <code>het_seo_canonical</code>, <code>het_seo_noindex</code>, <code>het_seo_nofollow</code>.</div>
  <div class="het-actions" style="margin-top:10px">
    <a class="button" href="<?php echo esc_url($u_center); ?>">← Wróć do Centrum</a>
  </div>
</div>

<div class="het-seo-card">
  <div class="het-seo-card__title">Ustawienia</div>
  <form method="post">
    <?php wp_nonce_field('het_seo_save_canon'); ?>
    <table class="form-table">
      <tr>
        <th>Domyślny canonical</th>
        <td>
          <select name="default_canonical_mode">
            <option value="off" <?php selected($mode, 'off'); ?>>Wyłączony (tylko per wpis)</option>
            <option value="self" <?php selected($mode, 'self'); ?>>Self canonical (jeśli brak per wpis)</option>
          </select>
          <p class="description">Uwaga: nie nadpisujemy ustawień innych wtyczek — to jest minimalny tryb.</p>
        </td>
      </tr>
      <tr>
        <th>Noindex dla archiwów</th>
        <td>
          <label style="display:block;margin-bottom:6px"><input type="checkbox" name="noindex_archives" value="1" <?php checked(!empty($opts['noindex_archives'])); ?>> master: kategorie / tagi / autor / data</label>
          <div class="het-muted" style="margin:6px 0 0 2px">
            <label style="margin-right:14px"><input type="checkbox" name="noindex_categories" value="1" <?php checked(!empty($opts['noindex_categories'])); ?>> kategorie</label>
            <label style="margin-right:14px"><input type="checkbox" name="noindex_tags" value="1" <?php checked(!empty($opts['noindex_tags'])); ?>> tagi</label>
            <label style="margin-right:14px"><input type="checkbox" name="noindex_author" value="1" <?php checked(!empty($opts['noindex_author'])); ?>> autor</label>
            <label><input type="checkbox" name="noindex_date" value="1" <?php checked(!empty($opts['noindex_date'])); ?>> data</label>
          </div>
          <p class="description">Master ustawia wszystko na ON, ale możesz sterować osobno.</p>
        </td>
      </tr>
      <tr>
        <th>Noindex dla paginacji</th>
        <td>
          <label><input type="checkbox" name="noindex_paged" value="1" <?php checked(!empty($opts['noindex_paged'])); ?>> strony 2,3,4… (is_paged)</label>
        </td>
      </tr>
      <tr>
        <th>Noindex dla URL z parametrami</th>
        <td>
          <label><input type="checkbox" name="noindex_params" value="1" <?php checked(!empty($opts['noindex_params'])); ?>> jeśli w URL są parametry (np. ?utm=…)</label>
        </td>
      </tr>
      <tr>
        <th>Noindex dla wyszukiwarki</th>
        <td>
          <label><input type="checkbox" name="noindex_search" value="1" <?php checked(!empty($opts['noindex_search'])); ?>> strony wyników wyszukiwania</label>
        </td>
      </tr>
      <tr>
        <th>Wyjątki globalnego noindex</th>
        <td>
          <textarea name="noindex_whitelist_paths" rows="4" style="width:100%" placeholder="/blog/kategoria/specjalna/\n/sklep/*"><?php echo esc_textarea((string)($opts['noindex_whitelist_paths'] ?? '')); ?></textarea>
          <p class="description">Jedna ścieżka na linię. Gwiazdka na końcu działa jako prefix (np. <code>/sklep/*</code>). Jeśli pasuje — globalny noindex dla archiwów/paginacji/parametrów nie będzie dodany.</p>
        </td>
      </tr>
    </table>
    <p><button class="button button-primary" name="het_seo_save_canon" value="1">Zapisz</button></p>
  </form>
</div>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-database"></span> Masowe noindex / nofollow</div>
  <div class="het-seo-card__sub">Szybkie ustawienie meta robots dla całego typu treści (np. noindex dla stron ofert, tagów itp.). Możesz też wyczyścić (odznaczyć) aby usunąć meta z wpisów.</div>
  <form method="post" style="margin-top:10px">
    <?php wp_nonce_field('het_seo_bulk_apply'); ?>
    <table class="form-table">
      <tr>
        <th>Typ treści</th>
        <td>
          <select name="bulk_post_type">
            <?php foreach (($public_types ?? []) as $pt => $obj): ?>
              <option value="<?php echo esc_attr($pt); ?>"><?php echo esc_html($obj->labels->singular_name); ?> (<?php echo esc_html($pt); ?>)</option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
      <tr>
        <th>Ustaw</th>
        <td>
          <label style="margin-right:14px"><input type="checkbox" name="bulk_noindex" value="1"> noindex</label>
          <label><input type="checkbox" name="bulk_nofollow" value="1"> nofollow</label>
          <p class="description">Jeśli nie zaznaczysz — meta zostanie usunięte z wpisów tego typu.</p>
        </td>
      </tr>
      <tr>
        <th>Limit</th>
        <td>
          <input type="number" name="bulk_limit" value="5000" min="1" max="200000" style="width:120px"> <span class="description">(ochrona — ile wpisów zmienić maks.)</span>
        </td>
      </tr>
    </table>
    <p><button class="button button-primary" name="het_seo_bulk_apply" value="1">Zastosuj masowo</button></p>
  </form>
</div>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
