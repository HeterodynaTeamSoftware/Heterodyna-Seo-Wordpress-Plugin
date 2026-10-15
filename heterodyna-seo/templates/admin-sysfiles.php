<?php if (!defined('ABSPATH')) { exit; }
$urobots = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_ROBOTS], admin_url('admin.php'));
$usecurity = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_SECURITY], admin_url('admin.php'));
$usitemap = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_SITEMAP], admin_url('admin.php'));
?>
<div class="het-seo-card het-seo-card--sysfiles">
  <div class="het-seo-card__head">
    <div class="het-seo-card__title"><span class="dashicons dashicons-media-document"></span> Pliki systemowe</div>
    <div class="het-seo-card__sub">Szybki podgląd statusów publicznych endpointów. Białe tła (tabele) = czarny tekst, karty = jasny tekst.</div>
  </div>

  <div class="het-mini-grid" style="margin-top:12px">
    <div class="het-mini-tile">
      <div class="het-mini-tile__k">Robots</div>
      <div class="het-mini-tile__v"><?php echo isset($checks['robots.txt']['code']) ? (int)$checks['robots.txt']['code'] : 0; ?></div>
      <div class="het-mini-tile__s"><?php echo !empty($checks['robots.txt']['ok']) ? 'OK' : 'Sprawdź'; ?></div>
      <div class="het-mini-tile__a">
        <a class="button button-small" href="<?php echo esc_url($urobots); ?>">Edytor</a>
        <a class="button button-small" target="_blank" rel="noopener" href="<?php echo esc_url(home_url('/robots.txt')); ?>">Podgląd</a>
      </div>
    </div>

    <div class="het-mini-tile">
      <div class="het-mini-tile__k">Sitemap</div>
      <div class="het-mini-tile__v"><?php echo isset($checks['sitemap.xml']['code']) ? (int)$checks['sitemap.xml']['code'] : 0; ?></div>
      <div class="het-mini-tile__s"><?php echo !empty($checks['sitemap.xml']['ok']) ? 'OK' : 'Sprawdź'; ?></div>
      <div class="het-mini-tile__a">
        <a class="button button-small" href="<?php echo esc_url($usitemap); ?>">Ustawienia</a>
        <a class="button button-small" target="_blank" rel="noopener" href="<?php echo esc_url(home_url('/sitemap.xml')); ?>">Podgląd</a>
      </div>
    </div>

    <div class="het-mini-tile">
      <div class="het-mini-tile__k">WP Sitemap</div>
      <div class="het-mini-tile__v"><?php echo isset($checks['wp-sitemap.xml']['code']) ? (int)$checks['wp-sitemap.xml']['code'] : 0; ?></div>
      <div class="het-mini-tile__s"><?php echo !empty($checks['wp-sitemap.xml']['ok']) ? 'OK' : 'Sprawdź'; ?></div>
      <div class="het-mini-tile__a">
        <a class="button button-small" target="_blank" rel="noopener" href="<?php echo esc_url(home_url('/wp-sitemap.xml')); ?>">Podgląd</a>
      </div>
    </div>

    <div class="het-mini-tile">
      <div class="het-mini-tile__k">security.txt</div>
      <div class="het-mini-tile__v"><?php echo isset($checks['security.txt']['code']) ? (int)$checks['security.txt']['code'] : 0; ?></div>
      <div class="het-mini-tile__s"><?php echo !empty($checks['security.txt']['ok']) ? 'OK' : 'Sprawdź'; ?></div>
      <div class="het-mini-tile__a">
        <a class="button button-small" href="<?php echo esc_url($usecurity); ?>">Generator</a>
        <a class="button button-small" target="_blank" rel="noopener" href="<?php echo esc_url(home_url('/.well-known/security.txt')); ?>">Podgląd</a>
      </div>
    </div>
  </div>

  <div style="height:14px"></div>

  <table class="widefat striped">
    <thead>
      <tr>
        <th>Plik</th>
        <th>URL</th>
        <th>Status</th>
        <th>Uwagi</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($urls as $k => $u): 
        $c = isset($checks[$k]) ? $checks[$k] : ['ok'=>false,'code'=>0,'detail'=>''];
      ?>
      <tr>
        <td><strong><?php echo esc_html($k); ?></strong></td>
        <td><a href="<?php echo esc_url($u); ?>" target="_blank" rel="noopener"><?php echo esc_html($u); ?></a></td>
        <td>
          <span class="<?php echo !empty($c['ok']) ? 'het-pill het-pill--ok' : 'het-pill het-pill--bad'; ?>">
            <?php echo !empty($c['ok']) ? 'OK' : 'Błąd'; ?> (<?php echo (int)$c['code']; ?>)
          </span>
        </td>
        <td><?php echo !empty($c['detail']) ? esc_html($c['detail']) : '—'; ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

</div>
