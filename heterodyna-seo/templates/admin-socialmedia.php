<?php
if (!defined('ABSPATH')) { exit; }
if (!current_user_can('manage_options')) { return; }

$tab = isset($tab) ? $tab : 'integracje';

$tabs = [
    'integracje' => 'Integracje',
    'funkcje'    => 'Funkcje wykonawcze',
];

$tab_url = function($t) {
    return admin_url('admin.php?page=' . HET_SEO_Admin::MENU_SLUG_SOCIALMEDIA . '&tab=' . $t);
};

$page_url = function($slug) {
    return admin_url('admin.php?page=' . $slug);
};

$btn = function($label, $slug, $extra_class = 'button') use ($page_url) {
    $url = $page_url($slug);
    echo '<a class="' . esc_attr($extra_class) . '" href="' . esc_url($url) . '" style="margin-right:8px;margin-bottom:8px;display:inline-block;">' . esc_html($label) . '</a>';
};
?>

<div class="wrap">
    <h1>Socialmedia (Heterodyna SEO)</h1>

    <h2 class="nav-tab-wrapper">
        <?php foreach ($tabs as $k=>$label): ?>
            <?php $cls = ($k === $tab) ? ' nav-tab-active' : ''; ?>
            <a class="nav-tab<?php echo esc_attr($cls); ?>" href="<?php echo esc_url($tab_url($k)); ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </h2>

    <?php if ($tab === 'integracje'): ?>
        <div class="het-card" style="padding:16px;margin-top:12px;">
            <h2 style="margin-top:0;">Integracje</h2>
            <p>Tu trzymamy wszystko, co jest <strong>podłączeniem / konfiguracją</strong> (konta, tokeny, ustawienia OG/Twitter).</p>

            <h3 style="margin-bottom:8px;">Podstawowe ustawienia (OpenGraph / Twitter)</h3>
            <div>
                <?php $btn('Ustawienia Social (OG/Twitter)', HET_SEO_Admin::MENU_SLUG_SOCIAL); ?>
            </div>

            <hr style="margin:14px 0;" />

            <h3 style="margin-bottom:8px;">Konta</h3>
            <div>
                <?php $btn('Konta (provider + etykiety)', HET_SEO_Admin::MENU_SLUG_SOCIAL_ACCOUNTS); ?>
            </div>

            <p style="margin-top:10px;"><em>Uwaga:</em> publikowanie Live per serwis dojdzie przez OAuth/Webhook per provider. Na dziś mamy bezpieczny tryb Dry‑run.</p>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'funkcje'): ?>
        <div class="het-card" style="padding:16px;margin-top:12px;">
            <h2 style="margin-top:0;">Funkcje wykonawcze</h2>
            <p>Tu masz narzędzia do <strong>tworzenia i wykonywania</strong>: kreator, kampanie, kolejka, logi.</p>

            <h3 style="margin-bottom:8px;">Szybki start</h3>
            <div>
                <?php $btn('Dashboard', HET_SEO_Admin::MENU_SLUG_SOCIAL_DASH, 'button button-primary'); ?>
                <?php $btn('Kreator + AI', HET_SEO_Admin::MENU_SLUG_SOCIAL_COMPOSER); ?>
            </div>

            <h3 style="margin:14px 0 8px;">Kampanie i wysyłka</h3>
            <div>
                <?php $btn('Kampanie', HET_SEO_Admin::MENU_SLUG_SOCIAL_CAMPAIGNS); ?>
                <?php $btn('Kolejka', HET_SEO_Admin::MENU_SLUG_SOCIAL_QUEUE); ?>
                <?php $btn('Logi', HET_SEO_Admin::MENU_SLUG_SOCIAL_LOGS); ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
