<?php
if (!defined('ABSPATH')) { exit; }
$base = admin_url('admin.php');
$tabs = [
  ['id'=>'center','label'=>'Centrum','slug'=>HET_SEO_Admin::MENU_SLUG_CENTER,'icon'=>'dashicons-grid-view'],
  ['id'=>'data_site','label'=>'Dane strony','slug'=>HET_SEO_Admin::MENU_SLUG_DATA_SITE,'icon'=>'dashicons-chart-bar'],
  ['id'=>'data_url','label'=>'Dane URL','slug'=>HET_SEO_Admin::MENU_SLUG_DATA_URL,'icon'=>'dashicons-media-spreadsheet'],
  ['id'=>'history','label'=>'Historia SEO','slug'=>HET_SEO_Admin::MENU_SLUG_HISTORY,'icon'=>'dashicons-backup'],
  ['id'=>'content_intel','label'=>'Analiza treści','slug'=>HET_SEO_Admin::MENU_SLUG_CONTENT_INTEL,'icon'=>'dashicons-analytics'],
  ['id'=>'intent','label'=>'Intencja','slug'=>HET_SEO_Admin::MENU_SLUG_INTENT,'icon'=>'dashicons-search'],
  ['id'=>'gaps','label'=>'Luki treści','slug'=>HET_SEO_Admin::MENU_SLUG_CONTENT_GAPS,'icon'=>'dashicons-warning'],
  ['id'=>'settings','label'=>'Ustawienia','slug'=>HET_SEO_Admin::MENU_SLUG_SETTINGS,'icon'=>'dashicons-admin-generic'],
  ['id'=>'license','label'=>'Open Source','slug'=>HET_SEO_Admin::MENU_SLUG_LICENSE,'icon'=>'dashicons-unlock'],
];
?>
<?php
$sm_opt = get_option('het_seo_socialmedia', []);
$sm_ultra = (is_array($sm_opt) && isset($sm_opt['ui_ultra']) && (int)$sm_opt['ui_ultra'] === 1);
$sm_ultra_cls = $sm_ultra ? ' het-sm-ultra' : '';
?>
<div class="het-seo-shell <?php echo esc_attr('het-density-' . (isset($ui_density) ? $ui_density : 'compact')); ?><?php echo esc_attr($sm_ultra_cls); ?>">
  <div class="het-seo-topbar">
    <div class="het-seo-topbar__left">
      <img class="het-seo-logo" src="<?php echo esc_url(HET_SEO_URL . 'admin/img/logo.png'); ?>" alt="Heterodyna SEO">
      <div class="het-seo-badge">Open Source • <?php echo esc_html(HET_SEO_VER); ?></div>
    </div>
    <div class="het-seo-topbar__right">
      <?php $is_on = !empty($state['active']); ?>
      <div class="het-seo-lic">
        Tryb: <strong>Open Source ✅</strong>
      </div>
    </div>
  </div>

  <div class="het-seo-nav">
    <?php foreach ($tabs as $t):
      $url = add_query_arg(['page' => $t['slug']], $base);
      $is = ($active === $t['id']);
    ?>
      <a class="het-seo-nav__tab <?php echo $is ? 'is-active' : ''; ?>" href="<?php echo esc_url($url); ?>">
        <span class="dashicons <?php echo esc_attr($t['icon']); ?>"></span>
        <?php echo esc_html($t['label']); ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="het-seo-content">
