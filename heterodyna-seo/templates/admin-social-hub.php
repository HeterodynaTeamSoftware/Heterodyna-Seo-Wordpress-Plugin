<?php
if (!defined('ABSPATH')) { exit; }
if (!current_user_can('manage_options')) { return; }

$providers = class_exists('HET_SEO_Social_Hub') ? HET_SEO_Social_Hub::get_providers() : [];
$tab = isset($tab) ? $tab : 'dashboard';

if ($tab === 'composer' || $tab === 'campaigns') { wp_enqueue_media(); }

function het_seo_social_nav($active) {
    $items = [
        'dashboard' => ['Dashboard', HET_SEO_Admin::MENU_SLUG_SOCIAL_DASH],
        'accounts'  => ['Konta', HET_SEO_Admin::MENU_SLUG_SOCIAL_ACCOUNTS],
        'composer'  => ['Kreator', HET_SEO_Admin::MENU_SLUG_SOCIAL_COMPOSER],
        'campaigns' => ['Kampanie', HET_SEO_Admin::MENU_SLUG_SOCIAL_CAMPAIGNS],
        'queue'     => ['Kolejka', HET_SEO_Admin::MENU_SLUG_SOCIAL_QUEUE],
        'logs'      => ['Logi', HET_SEO_Admin::MENU_SLUG_SOCIAL_LOGS],
    ];
    // Use Heterodyna SEO internal tab styling (not WP nav-tabs) for consistent look.
    echo '<div class="het-tabs" style="margin-top:12px;">';
    foreach ($items as $k => $it) {
        $cls = ($k === $active) ? ' is-active' : '';
        $url = admin_url('admin.php?page=' . $it[1]);
        echo '<a class="het-tab' . esc_attr($cls) . '" href="' . esc_url($url) . '">' . esc_html($it[0]) . '</a>';
    }
    echo '</div>';
}


$notice = '';
// Actions
if (!empty($_POST) && check_admin_referer('het_seo_social_hub')) {
    $action = sanitize_key($_POST['het_action'] ?? '');
    if ($action === 'add_account') {
        $provider = sanitize_key($_POST['provider'] ?? '');
        $label = sanitize_text_field($_POST['label'] ?? '');
        $ref = sanitize_text_field($_POST['ref'] ?? '');
        if ($provider && $label) {
            HET_SEO_Social_Hub::add_account($provider, $label, $ref, []);
            $notice = 'Konto dodane ✅';
        } else {
            $notice = 'Uzupełnij provider i nazwę konta.';
        }
    } elseif ($action === 'ai_generate') {
        // handled below via $_GET persistence
        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $sm = get_option('het_seo_socialmedia', []);
        $mode = is_array($sm) ? (string)($sm['mode'] ?? 'manual') : 'manual';
        $guidelines = is_array($sm) ? (string)($sm['ai_guidelines'] ?? '') : '';
        // Prefer "smart" generation to avoid schematic outputs.
        if (method_exists('HET_SEO_Social_Hub', 'ai_generate_smart') && ($mode === 'ai_smart' || trim($guidelines) !== '')) {
            $gen = HET_SEO_Social_Hub::ai_generate_smart($topic, $guidelines);
        } else {
            $gen = HET_SEO_Social_Hub::ai_generate_smart($topic, $guidelines);
        }
        $GLOBALS['het_seo_social_ai_gen'] = $gen;
        $notice = 'Wygenerowano treść (smart) ✅';
    } elseif ($action === 'create_post') {
        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');
        $src = esc_url_raw($_POST['source_url'] ?? '');
        if ($title && $content) {
            $media = [];
            $media_ids_raw = isset($_POST['media_ids']) ? sanitize_text_field(wp_unslash($_POST['media_ids'])) : '';
            $media_url_raw = isset($_POST['media_urls']) ? sanitize_textarea_field(wp_unslash($_POST['media_urls'])) : '';
            if ($media_ids_raw !== '') {
                foreach (preg_split('/\s*,\s*/', $media_ids_raw) as $idv) {
                    $aid = (int)$idv;
                    if ($aid > 0) { $media[] = ['attachment_id'=>$aid]; }
                }
            }
            if ($media_url_raw !== '') {
                foreach (preg_split('/\r\n|\r|\n/', $media_url_raw) as $u) {
                    $u = trim((string)$u);
                    if ($u !== '') { $media[] = ['url'=>$u]; }
                }
            }
            $pid = HET_SEO_Social_Hub::create_post($title, $content, $src, $media);
            $notice = 'Post zapisany jako szkic ✅ (ID: ' . intval($pid) . ')';
        } else {
            $notice = 'Uzupełnij tytuł i treść.';
        }
    } elseif ($action === 'create_campaign') {
        $name = sanitize_text_field($_POST['name'] ?? '');
        $post_id = intval($_POST['post_id'] ?? 0);
        $mode = sanitize_key($_POST['mode'] ?? 'dry');
        $targets = isset($_POST['targets']) ? array_map('intval', (array)$_POST['targets']) : [];
        $providers_sel = isset($_POST['providers']) ? array_map('sanitize_key', (array)$_POST['providers']) : [];
        // Persist both: explicit accounts and selected providers (providers will be mapped at enqueue time).
        $targets_payload = [
            'accounts' => array_values(array_filter(array_map('intval', (array)$targets))),
            'providers' => array_values(array_filter(array_map('sanitize_key', (array)$providers_sel))),
        ];
        if ($name && $post_id) {
            $cid = HET_SEO_Social_Hub::create_campaign($name, $post_id, $targets_payload, $mode, '');
            $notice = 'Kampania utworzona i wrzucona do kolejki ✅ (ID: ' . intval($cid) . ')';
        } else {
            $notice = 'Uzupełnij nazwę i wybierz post.';
        }
    } elseif ($action === 'x_save_token') {
        $account_id = intval($_POST['account_id'] ?? 0);
        $access = trim((string)($_POST['x_access_token'] ?? ''));
        $refresh = trim((string)($_POST['x_refresh_token'] ?? ''));
        $expires = trim((string)($_POST['x_expires_at'] ?? ''));
        if ($account_id > 0) {
            $acc = HET_SEO_Social_Hub::get_account($account_id);
            if ($acc) {
                $settings = [];
                if (!empty($acc['settings_json'])) {
                    $tmp = json_decode((string)$acc['settings_json'], true);
                    if (is_array($tmp)) { $settings = $tmp; }
                }
                HET_SEO_Social_Hub::set_account_setting($settings, 'x_access_token', $access);
                HET_SEO_Social_Hub::set_account_setting($settings, 'x_refresh_token', $refresh);
                $settings['x_expires_at'] = $expires;
                $settings['x_connected_at'] = current_time('mysql');
                HET_SEO_Social_Hub::update_account_settings($account_id, $settings);
                $notice = 'Zapisano tokeny X ✅';
            } else {
                $notice = 'Nie znaleziono konta.';
            }
        } else {
            $notice = 'Wybierz konto X.';
        }
    } elseif ($action === 'x_send_test') {
        $account_id = intval($_POST['account_id'] ?? 0);
        $text = trim((string)($_POST['test_text'] ?? ''));
        $fmt = HET_SEO_Social_Hub::format_text_for_provider('x', $text, HET_SEO_Social_Hub::get_socialmedia_templates());
        $text = (string)($fmt['text'] ?? $text);
        $acc = $account_id ? HET_SEO_Social_Hub::get_account($account_id) : null;
        if (!$acc) {
            $notice = 'Wybierz konto X.';
        } else {
            $token = HET_SEO_Social_Hub::get_account_setting($acc, 'x_access_token', '');
            $res = HET_SEO_Social_Hub::x_publish_tweet($token, $text);
            if (!empty($res['ok'])) {
                $notice = 'Wysłano testowy post na X ✅';
                HET_SEO_Social_Hub::log('info', 'X test tweet sent', ['account_id'=>$account_id]);
            } else {
                $err = (string)($res['err'] ?? 'Błąd');
                $notice = 'Błąd X: ' . $err;
                HET_SEO_Social_Hub::log('error', 'X test tweet failed', ['account_id'=>$account_id,'err'=>$err,'res'=>$res]);
            }
        }
    } elseif ($action === 'li_save_token') {
        $account_id = intval($_POST['account_id'] ?? 0);
        $access = trim((string)($_POST['li_access_token'] ?? ''));
        $refresh = trim((string)($_POST['li_refresh_token'] ?? ''));
        $author = trim((string)($_POST['li_author_urn'] ?? ''));
        if ($account_id > 0) {
            $acc = HET_SEO_Social_Hub::get_account($account_id);
            if ($acc) {
                $settings = [];
                if (!empty($acc['settings_json'])) {
                    $tmp = json_decode((string)$acc['settings_json'], true);
                    if (is_array($tmp)) { $settings = $tmp; }
                }
                HET_SEO_Social_Hub::set_account_setting($settings, 'li_access_token', $access);
                HET_SEO_Social_Hub::set_account_setting($settings, 'li_refresh_token', $refresh);
                $settings['li_author_urn'] = $author;
                $settings['li_connected_at'] = current_time('mysql');
                HET_SEO_Social_Hub::update_account_settings($account_id, $settings);
                $notice = 'Zapisano dane LinkedIn ✅';
            } else {
                $notice = 'Nie znaleziono konta.';
            }
        } else {
            $notice = 'Wybierz konto LinkedIn.';
        }
    } elseif ($action === 'li_send_test') {
        $account_id = intval($_POST['account_id'] ?? 0);
        $text = trim((string)($_POST['test_text'] ?? ''));
        $acc =        $fmt = HET_SEO_Social_Hub::format_text_for_provider('linkedin', $text, HET_SEO_Social_Hub::get_socialmedia_templates());
        $text = (string)($fmt['text'] ?? $text);
        $acc = $account_id ? HET_SEO_Social_Hub::get_account($account_id) : null;
        if (!$acc) {
            $notice = 'Wybierz konto LinkedIn.';
        } else {
            $token = HET_SEO_Social_Hub::get_account_setting($acc, 'li_access_token', '');
            $author = HET_SEO_Social_Hub::get_account_setting($acc, 'li_author_urn', '');
            $res = HET_SEO_Social_Hub::li_publish_post($token, $author, $text, 'PUBLIC');
            if (!empty($res['ok'])) {
                $notice = 'Wysłano testowy post na LinkedIn ✅';
                HET_SEO_Social_Hub::log('info', 'LinkedIn test post sent', ['account_id'=>$account_id]);
            } else {
                $notice = 'Błąd LinkedIn: ' . esc_html((string)($res['err'] ?? 'unknown'));
                HET_SEO_Social_Hub::log('error', 'LinkedIn test post failed', ['account_id'=>$account_id,'err'=>$res]);
            }
        }
    } elseif ($action === 'approve_send') {
        $queue_id = intval($_POST['queue_id'] ?? 0);
        if ($queue_id > 0) {
            $res = HET_SEO_Social_Hub::approve_and_send_queue_item($queue_id);
            if (!empty($res['ok'])) {
                $notice = 'Zatwierdzono i wysłano ✅';
                HET_SEO_Social_Hub::log('info', 'Queue item approved and sent', ['queue_id'=>$queue_id,'user_id'=>get_current_user_id()]);
            } else {
                $notice = 'Błąd: ' . (string)($res['err'] ?? 'nieznany');
            }
        } else {
            $notice = 'Brak ID kolejki.';
        }
    } elseif ($action === 'reject_item') {
        $queue_id = intval($_POST['queue_id'] ?? 0);
        $reason = sanitize_text_field($_POST['reason'] ?? 'Rejected');
        if ($queue_id > 0) {
            HET_SEO_Social_Hub::reject_queue_item($queue_id, $reason);
            $notice = 'Odrzucono ✅';
            HET_SEO_Social_Hub::log('info', 'Queue item rejected', ['queue_id'=>$queue_id,'user_id'=>get_current_user_id(),'reason'=>$reason]);
        } else {
            $notice = 'Brak ID kolejki.';
        }

    } elseif ($action === 'retry_now') {
        $queue_id = intval($_POST['queue_id'] ?? 0);
        if ($queue_id > 0) {
            HET_SEO_Social_Hub::retry_queue_item($queue_id);
            $notice = 'Ustawiono retry (teraz) ✅';
            HET_SEO_Social_Hub::log('info', 'Queue item forced retry', ['queue_id'=>$queue_id,'user_id'=>get_current_user_id()]);
        } else {
            $notice = 'Brak ID kolejki.';
        }
    } elseif ($action === 'cancel_item') {
        $queue_id = intval($_POST['queue_id'] ?? 0);
        $reason = sanitize_text_field($_POST['reason'] ?? 'Cancelled');
        if ($queue_id > 0) {
            HET_SEO_Social_Hub::cancel_queue_item($queue_id, $reason);
            $notice = 'Anulowano ✅';
            HET_SEO_Social_Hub::log('info', 'Queue item cancelled', ['queue_id'=>$queue_id,'user_id'=>get_current_user_id(),'reason'=>$reason]);
        } else {
            $notice = 'Brak ID kolejki.';
        }


}
}

$accounts = HET_SEO_Social_Hub::list_accounts();
$queue = HET_SEO_Social_Hub::list_queue(200);
$logs = HET_SEO_Social_Hub::list_logs(200);

// Posts list (simple)
global $wpdb;
$tPosts = $wpdb->prefix . 'het_seo_social_posts';
$posts = $wpdb->get_results("SELECT * FROM $tPosts ORDER BY id DESC LIMIT 200", ARRAY_A);
$posts = is_array($posts) ? $posts : [];

?>
<div class="het-seo-page">
    <h1 style="margin-top:0;">Social Media</h1>
    <?php het_seo_social_nav($tab); ?>
    <script>
(function(){
  try{
    var active = <?php echo json_encode($tab); ?>;
    var map = {
      dashboard: <?php echo json_encode(admin_url('admin.php?page=' . HET_SEO_Admin::MENU_SLUG_SOCIAL_DASH)); ?>,
      accounts: <?php echo json_encode(admin_url('admin.php?page=' . HET_SEO_Admin::MENU_SLUG_SOCIAL_ACCOUNTS)); ?>,
      composer: <?php echo json_encode(admin_url('admin.php?page=' . HET_SEO_Admin::MENU_SLUG_SOCIAL_COMPOSER)); ?>,
      campaigns: <?php echo json_encode(admin_url('admin.php?page=' . HET_SEO_Admin::MENU_SLUG_SOCIAL_CAMPAIGNS)); ?>,
      queue: <?php echo json_encode(admin_url('admin.php?page=' . HET_SEO_Admin::MENU_SLUG_SOCIAL_QUEUE)); ?>,
      logs: <?php echo json_encode(admin_url('admin.php?page=' . HET_SEO_Admin::MENU_SLUG_SOCIAL_LOGS)); ?>,
    };
    localStorage.setItem('het_seo_social_last', active);
    if(active === 'dashboard'){
      var last = localStorage.getItem('het_seo_social_last_real');
      if(last && last !== 'dashboard' && map[last]){
        window.location.replace(map[last]);
        return;
      }
    } else {
      localStorage.setItem('het_seo_social_last_real', active);
    }
  }catch(e){}
})();
</script>
    <?php if ($notice): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>

    <?php if ($tab === 'dashboard'): ?>
        <div class="het-card" style="padding:16px;margin-top:12px;">
            <h2 style="margin-top:0;">Dashboard</h2>
            <p>Tu masz szybki podgląd: konta, posty, kampanie i kolejkę wysyłek.</p>
            <ul>
                <li><strong>Konta:</strong> <?php echo intval(count($accounts)); ?></li>
                <li><strong>Posty (ostatnie 200):</strong> <?php echo intval(count($posts)); ?></li>
                <li><strong>Kolejka (ostatnie 200):</strong> <?php echo intval(count($queue)); ?></li>

</ul>

<?php
  $mrow = (class_exists('HET_SEO_Social_Hub') && method_exists('HET_SEO_Social_Hub','metrics_latest')) ? HET_SEO_Social_Hub::metrics_latest('site', 0) : null;
  $refresh_url = wp_nonce_url(admin_url('admin-post.php?action=het_seo_social_metrics_refresh'), 'het_seo_social_metrics_refresh');
?>

<hr style="margin:16px 0;" />
<h3 style="margin:0 0 6px 0;">Analityka: Site Kit (GA4/GSC)</h3>
<p class="het-muted" style="margin-top:0;">Snapshot KPI z Google Site Kit (jeśli aktywne). Odświeżenie zapisuje dzisiejszy rekord w bazie.</p>
<div class="het-metrics" style="display:flex;gap:12px;flex-wrap:wrap;margin:10px 0;">
    <div class="het-metric"><div class="het-metric__label">GSC kliknięcia</div><div class="het-metric__value"><?php echo esc_html($mrow ? (string)($mrow['gsc_clicks'] ?? '—') : '—'); ?></div></div>
    <div class="het-metric"><div class="het-metric__label">GSC wyświetlenia</div><div class="het-metric__value"><?php echo esc_html($mrow ? (string)($mrow['gsc_impressions'] ?? '—') : '—'); ?></div></div>
    <div class="het-metric"><div class="het-metric__label">GSC CTR</div><div class="het-metric__value"><?php echo esc_html($mrow && $mrow['gsc_ctr'] !== null ? ((float)$mrow['gsc_ctr']) . '%' : '—'); ?></div></div>
    <div class="het-metric"><div class="het-metric__label">GA4 sesje</div><div class="het-metric__value"><?php echo esc_html($mrow ? (string)($mrow['ga_sessions'] ?? '—') : '—'); ?></div></div>
    <div class="het-metric"><div class="het-metric__label">GA4 użytkownicy</div><div class="het-metric__value"><?php echo esc_html($mrow ? (string)($mrow['ga_users'] ?? '—') : '—'); ?></div></div>
</div>
<p style="margin:0;">
    <a class="button button-secondary" href="<?php echo esc_url($refresh_url); ?>">Odśwież KPI teraz</a>
    <?php if ($mrow && !empty($mrow['date_ymd'])): ?>
        <span class="het-muted" style="margin-left:8px;">Ostatni zapis: <strong><?php echo esc_html($mrow['date_ymd']); ?></strong></span>
    <?php endif; ?>
</p>

<p><em>Tryb domyślny to Dry‑run (bez publikacji). Live publikowanie dodamy przez OAuth / webhook per serwis.</em></p>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'accounts'): ?>
        <div class="het-card" style="padding:16px;margin-top:12px;">
            <h2 style="margin-top:0;">Konta</h2>
            <form method="post">
                <?php wp_nonce_field('het_seo_social_hub'); ?>
                <input type="hidden" name="het_action" value="add_account" />
                <table class="form-table">
                    <tr>
                        <th scope="row">Serwis</th>
                        <td>
                            <select name="provider">
                                <?php foreach ($providers as $k=>$v): ?>
                                    <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Nazwa konta</th>
                        <td><input type="text" name="label" class="regular-text" placeholder="np. Heterodyna Team" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Id / URL (opcjonalnie)</th>
                        <td><input type="text" name="ref" class="regular-text" placeholder="np. @konto albo URL" /></td>
                    </tr>
                </table>
                <?php submit_button('Dodaj konto'); ?>
            </form>
        </div>

        <div class="het-card" style="padding:16px;margin-top:12px;">
            <h2 style="margin-top:0;">Lista kont</h2>
            <table class="widefat striped">
                <thead><tr><th>ID</th><th>Provider</th><th>Nazwa</th><th>Ref</th><th>Enabled</th></tr></thead>
                <tbody>
                <?php foreach ($accounts as $a): ?>
                    <tr>
                        <td><?php echo intval($a['id']); ?></td>
                        <td><?php echo esc_html($a['provider']); ?></td>
                        <td><?php echo esc_html($a['account_label']); ?></td>
                        <td><?php echo esc_html($a['account_ref']); ?></td>
                        <td><?php echo intval($a['is_enabled']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($accounts)): ?>
                    <tr><td colspan="5"><em>Brak kont.</em></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
          // X (Twitter) connection panel (token-based for now).
          $x_accounts = array_values(array_filter($accounts, function($a){ return isset($a['provider']) && $a['provider'] === 'x'; }));
          $x_sel = intval($_POST['account_id'] ?? 0);
          if ($x_sel <= 0 && !empty($x_accounts)) { $x_sel = intval($x_accounts[0]['id']); }
          $x_acc = $x_sel ? HET_SEO_Social_Hub::get_account($x_sel) : null;
          $x_access = $x_acc ? HET_SEO_Social_Hub::get_account_setting($x_acc, 'x_access_token', '') : '';
          $x_refresh = $x_acc ? HET_SEO_Social_Hub::get_account_setting($x_acc, 'x_refresh_token', '') : '';
          $x_expires = $x_acc ? (string)(json_decode((string)$x_acc['settings_json'], true)['x_expires_at'] ?? '') : '';
        ?>
        <div class="het-card" style="padding:16px;margin-top:12px;">
          <h2 style="margin-top:0;">X (Twitter) — połączenie i test</h2>
          <p class="het-muted" style="margin-top:0;">Publikacja LIVE dla X działa na podstawie <strong>access token</strong> (token użytkownika OAuth2 z uprawnieniem <code>tweet.write</code>). Jeśli nie masz tokena — użyj przycisku „Połącz konto X (OAuth)”, a wtyczka zapisze go automatycznie. Dokumentacja: <a target="_blank" rel="noopener" href="https://developer.x.com/">developer.x.com</a>.</p>

          

<?php
  $oauth = HET_SEO_Social_Hub::get_oauth_settings();
  $x_conf = is_array($oauth['x'] ?? null) ? $oauth['x'] : [];
  $x_client_id = (string)($x_conf['client_id'] ?? '');
?>
<div class="het-card" style="padding:12px;margin:12px 0;border:1px dashed rgba(0,0,0,.15);">
  <h3 style="margin:0 0 8px;">OAuth (PKCE) – konfiguracja</h3>
  <form method="post" style="margin:0;">
    <?php wp_nonce_field('het_seo_social_hub'); ?>
    <input type="hidden" name="het_action" value="oauth_save_x" />
    <table class="form-table" style="margin:0;">
      <tr>
        <th scope="row">Client ID</th>
        <td><input type="text" name="x_client_id" class="regular-text" value="<?php echo esc_attr($x_client_id); ?>" placeholder="X OAuth2 Client ID" /></td>
      </tr>
    </table>
    <?php submit_button('Zapisz OAuth X', 'secondary', 'submit', false); ?>
    <span class="het-muted" style="margin-left:10px;">Redirect URI: <code><?php echo esc_html(admin_url('admin.php?page=het-seo-social&tab=accounts')); ?></code></span>
  </form>
</div>
<?php if (empty($x_accounts)): ?>
            <div class="notice notice-warning"><p>Nie masz jeszcze konta typu <strong>X (Twitter)</strong>. Dodaj je wyżej (Serwis: X).</p></div>
          <?php else: ?>
            <form method="post" style="margin-bottom:14px;">
              <?php wp_nonce_field('het_seo_social_hub'); ?>
              <input type="hidden" name="het_action" value="x_save_token" />
              <table class="form-table" style="margin:0;">
                <tr>
                  <th scope="row">Konto X</th>
                  <td>
                    <select name="account_id">
                      <?php foreach ($x_accounts as $xa): ?>
                        <option value="<?php echo intval($xa['id']); ?>" <?php selected($x_sel, intval($xa['id'])); ?>><?php echo esc_html($xa['account_label']); ?> (ID: <?php echo intval($xa['id']); ?>)</option>
                      <?php endforeach; ?>
                    </select>
                    <span class="het-muted" style="margin-left:8px;">Tokeny zapisujemy w settings_json (szyfrowane).</span>
                  </td>
                </tr>
                <tr>
                  <th scope="row">Access token</th>
                  <td><textarea name="x_access_token" class="large-text" rows="3" placeholder="wklej access token (tweet.write)"><?php echo esc_textarea($x_access); ?></textarea></td>
                </tr>
                <tr>
                  <th scope="row">Refresh token (opc.)</th>
                  <td><textarea name="x_refresh_token" class="large-text" rows="2" placeholder="opcjonalnie"><?php echo esc_textarea($x_refresh); ?></textarea></td>
                </tr>
                <tr>
                  <th scope="row">Wygasa (opc.)</th>
                  <td><input type="text" name="x_expires_at" class="regular-text" placeholder="YYYY-MM-DD HH:MM:SS" value="<?php echo esc_attr($x_expires); ?>" /></td>
                </tr>
              </table>
              <?php submit_button('Zapisz tokeny X'); ?>
              <p style="margin:10px 0 0;"><a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['het_oauth'=>'x','account_id'=>intval($x_sel)], admin_url('admin.php?page=het-seo-social&tab=accounts')), 'het_seo_oauth_start')); ?>">Połącz konto X (OAuth)</a> <span class="het-muted" style="margin-left:8px;">Po połączeniu tokeny zapiszą się automatycznie.</span></p>
            </form>

            <form method="post">
              <?php wp_nonce_field('het_seo_social_hub'); ?>
              <input type="hidden" name="het_action" value="x_send_test" />
              <input type="hidden" name="account_id" value="<?php echo intval($x_sel); ?>" />
              <table class="form-table" style="margin:0;">
                <tr>
                  <th scope="row">Treść testowa</th>
                  <td><textarea name="test_text" class="large-text" rows="3" placeholder="Test: Heterodyna SEO — X publish działa ✅"></textarea></td>
                </tr>
              </table>
              <?php submit_button('Wyślij testowy post na X', 'secondary'); ?>
            </form>
          <?php endif; ?>
        </div>

        <?php
          // LinkedIn connection panel (token-based, with author URN).
          $li_accounts = array_values(array_filter($accounts, function($a){ return isset($a['provider']) && $a['provider'] === 'linkedin'; }));
          $li_sel = intval($_POST['li_account_id'] ?? 0);
          if ($li_sel <= 0 && !empty($li_accounts)) { $li_sel = intval($li_accounts[0]['id']); }
          $li_acc = $li_sel ? HET_SEO_Social_Hub::get_account($li_sel) : null;
          $li_access = $li_acc ? HET_SEO_Social_Hub::get_account_setting($li_acc, 'li_access_token', '') : '';
          $li_refresh = $li_acc ? HET_SEO_Social_Hub::get_account_setting($li_acc, 'li_refresh_token', '') : '';
          $li_author = $li_acc ? HET_SEO_Social_Hub::get_account_setting($li_acc, 'li_author_urn', '') : '';
        ?>
        <div class="het-card" style="padding:16px;margin-top:12px;">
          <h2 style="margin-top:0;">LinkedIn — połączenie i test</h2>
          <p class="het-muted" style="margin-top:0;">Publikacja LIVE dla LinkedIn działa na podstawie <strong>access token</strong> + <strong>author URN</strong> (osoba lub organizacja). Jeśli nie masz tokena/URN — użyj „Połącz konto LinkedIn (OAuth)”, a wtyczka spróbuje wykryć URN automatycznie. Dokumentacja: <a target="_blank" rel="noopener" href="https://learn.microsoft.com/linkedin/">learn.microsoft.com/linkedin</a>.</p>


<?php
  $oauth = HET_SEO_Social_Hub::get_oauth_settings();
  $li_conf = is_array($oauth['linkedin'] ?? null) ? $oauth['linkedin'] : [];
  $li_client_id = (string)($li_conf['client_id'] ?? '');
  $li_client_secret = (string)($li_conf['client_secret'] ?? '');
?>
<div class="het-card" style="padding:12px;margin:12px 0;border:1px dashed rgba(0,0,0,.15);">
  <h3 style="margin:0 0 8px;">OAuth (PKCE) – konfiguracja (LinkedIn)</h3>
  <form method="post" style="margin:0;">
    <?php wp_nonce_field('het_seo_social_hub'); ?>
    <input type="hidden" name="het_action" value="oauth_save_li" />
    <table class="form-table" style="margin:0;">
      <tr>
        <th scope="row">Client ID</th>
        <td><input type="text" name="li_client_id" class="regular-text" value="<?php echo esc_attr($li_client_id); ?>" placeholder="LinkedIn Client ID" /></td>
      </tr>
      <tr>
        <th scope="row">Client Secret</th>
        <td><input type="password" name="li_client_secret" class="regular-text" value="<?php echo esc_attr($li_client_secret); ?>" placeholder="LinkedIn Client Secret" /></td>
      </tr>
    </table>
    <?php submit_button('Zapisz OAuth LinkedIn', 'secondary', 'submit', false); ?>
    <span class="het-muted" style="margin-left:10px;">Redirect URI: <code><?php echo esc_html(admin_url('admin.php?page=het-seo-social&tab=accounts')); ?></code></span>
  </form>
</div>

          <?php if (empty($li_accounts)): ?>
            <div class="notice notice-warning"><p>Nie masz jeszcze konta typu <strong>LinkedIn</strong>. Dodaj je wyżej (Serwis: LinkedIn).</p></div>
          <?php else: ?>
            <form method="post" style="margin-bottom:14px;">
              <?php wp_nonce_field('het_seo_social_hub'); ?>
              <input type="hidden" name="het_action" value="li_save_token" />
              <table class="form-table" style="margin:0;">
                <tr>
                  <th scope="row">Konto LinkedIn</th>
                  <td>
                    <select name="account_id">
                      <?php foreach ($li_accounts as $la): ?>
                        <option value="<?php echo intval($la['id']); ?>" <?php selected($li_sel, intval($la['id'])); ?>><?php echo esc_html($la['account_label']); ?> (ID: <?php echo intval($la['id']); ?>)</option>
                      <?php endforeach; ?>
                    </select>
                    <span class="het-muted" style="margin-left:8px;">Tokeny zapisujemy w settings_json (szyfrowane).</span>
                  </td>
                </tr>
                <tr>
                  <th scope="row">Author URN</th>
                  <td><input type="text" name="li_author_urn" class="large-text" placeholder="urn:li:person:... albo urn:li:organization:..." value="<?php echo esc_attr($li_author); ?>" /></td>
                </tr>
                <tr>
                  <th scope="row">Access token</th>
                  <td><textarea name="li_access_token" class="large-text" rows="3" placeholder="wklej access token (w_member_social / w_organization_social)"><?php echo esc_textarea($li_access); ?></textarea></td>
                </tr>
                <tr>
                  <th scope="row">Refresh token (opc.)</th>
                  <td><textarea name="li_refresh_token" class="large-text" rows="2" placeholder="opcjonalnie"><?php echo esc_textarea($li_refresh); ?></textarea></td>
                </tr>
              </table>
              <?php submit_button('Zapisz dane LinkedIn'); ?>
              <p style="margin:10px 0 0;"><a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['het_oauth'=>'linkedin','account_id'=>intval($li_sel)], admin_url('admin.php?page=het-seo-social&tab=accounts')), 'het_seo_oauth_start')); ?>">Połącz konto LinkedIn (OAuth)</a> <span class="het-muted" style="margin-left:8px;">Po połączeniu tokeny zapiszą się automatycznie i author URN spróbujemy wykryć automatycznie.</span></p>
            </form>

            <form method="post">
              <?php wp_nonce_field('het_seo_social_hub'); ?>
              <input type="hidden" name="het_action" value="li_send_test" />
              <input type="hidden" name="account_id" value="<?php echo intval($li_sel); ?>" />
              <table class="form-table" style="margin:0;">
                <tr>
                  <th scope="row">Treść testowa</th>
                  <td><textarea name="test_text" class="large-text" rows="3" placeholder="Test: Heterodyna SEO — LinkedIn publish działa ✅"></textarea></td>
                </tr>
              </table>
              <?php submit_button('Wyślij testowy post na LinkedIn', 'secondary'); ?>
            </form>
          <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'composer'): ?>
        <div class="het-card" style="padding:16px;margin-top:12px;">
            <h2 style="margin-top:0;">Kreator + AI (warianty A/B/C) + kolejka</h2>

            <form method="post" style="margin-bottom:16px;">
                <?php wp_nonce_field('het_seo_social_hub'); ?>
                <input type="hidden" name="het_action" value="ai_generate" />
                <p><strong>Temat / URL / fraza:</strong></p>
                <input type="text" name="topic" class="large-text" placeholder="np. 3 błędy w meta title, które obniżają CTR" />
                <?php submit_button('Generuj 3 warianty (A/B/C)', 'secondary'); ?>
            </form>

            <hr style="margin:14px 0;" />

            <h3 style="margin:0 0 8px;">Nowy post (z media)</h3>
            <p class="het-muted" style="margin-top:0">Możesz dodać <strong>załączniki</strong> (biblioteka WP) lub <strong>URL-e</strong>. W LIVE: LinkedIn próbuje upload binarny (image) → asset, X próbuje chunked upload (wymaga tokena user z <code>media.write</code>), w razie problemów — fallback do URL.</p>

            <form method="post" class="het-seo-social-post-form">
                <?php wp_nonce_field('het_seo_social_hub'); ?>
                <input type="hidden" name="het_action" value="create_post" />

                <table class="form-table">
                    <tr>
                        <th scope="row">Tytuł</th>
                        <td><input type="text" name="title" class="large-text" placeholder="np. 5 tipów SEO na dziś" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Treść</th>
                        <td><textarea name="content" class="large-text" rows="6" placeholder="Treść posta…"></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row">source_url (opcjonalnie)</th>
                        <td><input type="url" name="source_url" class="large-text" placeholder="https://..." /></td>
                    </tr>

                    <tr>
                        <th scope="row">Media (biblioteka WP)</th>
                        <td>
                            <input type="hidden" name="media_ids" id="het_sm_media_ids" value="" />
                            <button type="button" class="button" id="het_sm_pick_media">Wybierz z biblioteki</button>
                            <button type="button" class="button" id="het_sm_clear_media" style="margin-left:6px;">Wyczyść</button>
                            <div id="het_sm_media_preview" style="margin-top:10px;display:flex;gap:10px;flex-wrap:wrap;"></div>
                            <p class="description">Na teraz: w publikacji LIVE używany jest <strong>pierwszy</strong> element (reszta zostaje w DB pod kolejne iteracje).</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Media (URL-e)</th>
                        <td>
                            <textarea name="media_urls" class="large-text" rows="3" placeholder="jeden URL na linię"></textarea>
                            <p class="description">Jeśli nie masz pliku w bibliotece — wklej link (np. do obrazu/wideo). LinkedIn użyje ARTICLE, X dopisze URL do treści (fallback).</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Zapisz post (draft)', 'primary'); ?>
            </form>

            <script>
            (function(){
              if(!window.wp || !wp.media) return;
              var frame;
              var btn = document.getElementById('het_sm_pick_media');
              var clr = document.getElementById('het_sm_clear_media');
              var hid = document.getElementById('het_sm_media_ids');
              var prev = document.getElementById('het_sm_media_preview');

              function render(ids, items){
                try{
                  prev.innerHTML = '';
                  (items||[]).forEach(function(it){
                    var wrap = document.createElement('div');
                    wrap.style.width = '84px';
                    wrap.style.border = '1px solid rgba(0,0,0,.12)';
                    wrap.style.borderRadius = '10px';
                    wrap.style.overflow = 'hidden';
                    wrap.title = it.filename || '';
                    var img = document.createElement('img');
                    img.src = (it.sizes && it.sizes.thumbnail && it.sizes.thumbnail.url) ? it.sizes.thumbnail.url : (it.icon || it.url || '');
                    img.style.width = '84px';
                    img.style.height = '84px';
                    img.style.objectFit = 'cover';
                    wrap.appendChild(img);
                    prev.appendChild(wrap);
                  });
                }catch(e){}
              }

              function setIds(ids, items){
                hid.value = (ids||[]).join(',');
                render(ids, items);
              }

              btn && btn.addEventListener('click', function(e){
                e.preventDefault();
                if(frame){ frame.open(); return; }
                frame = wp.media({
                  title: 'Wybierz media',
                  button: { text: 'Dodaj' },
                  multiple: true
                });
                frame.on('select', function(){
                  var sel = frame.state().get('selection');
                  var ids = [];
                  var items = [];
                  sel.each(function(att){
                    var a = att.toJSON();
                    ids.push(a.id);
                    items.push(a);
                  });
                  setIds(ids, items);
                });
                frame.open();
              });

              clr && clr.addEventListener('click', function(e){
                e.preventDefault();
                setIds([], []);
              });
            })();
            </script>

            <?php $vars = $GLOBALS['het_seo_social_ai_gen_variants'] ?? []; ?>
            <?php if (is_array($vars) && !empty($vars)): ?>
                <div class="het-seo-card" style="padding:12px;margin:0;border:1px solid rgba(0,0,0,.08);box-shadow:none">
                    <p class="het-muted" style="margin:0 0 10px">Masz 3 warianty — wybierz i wrzuć do kolejki. Jeśli <strong>schedule_at</strong> zostawisz puste, wtyczka wyliczy najbliższy slot wg ustawień harmonogramu.</p>

                    <?php
                      $labels = ['A','B','C'];
                      foreach ($vars as $i => $gen) {
                        $lab = $labels[$i] ?? ('V' . $i);
                        $title = (string)($gen['title'] ?? '');
                        $content = (string)($gen['content'] ?? '');
                    ?>
                      <div class="het-card" style="padding:12px;margin:0 0 10px;border:1px solid rgba(0,0,0,.06)">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
                          <strong>Wariant <?php echo esc_html($lab); ?></strong>
                          <span class="het-muted">możesz edytować przed zapisem</span>
                        </div> 
                      </div>
                    <?php } ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="het-card" style="padding:16px;margin-top:12px;">
            <h2 style="margin-top:0;">Ostatnie posty</h2>
            <table class="widefat striped">
                <thead><tr><th>ID</th><th>Tytuł</th><th>Status</th><th>Utworzono</th></tr></thead>
                <tbody>
                <?php foreach ($posts as $p): ?>
                    <tr>
                        <td><?php echo intval($p['id']); ?></td>
                        <td><?php echo esc_html($p['title']); ?></td>
                        <td><?php echo esc_html($p['status']); ?></td>
                        <td><?php echo esc_html($p['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($posts)): ?>
                    <tr><td colspan="4"><em>Brak postów.</em></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php if ($tab === 'campaigns'): ?>
        <div class="het-card" style="padding:16px;margin-top:12px;">
            <h2 style="margin-top:0;">Kampanie (wysyłka seryjna)</h2>
            <form method="post">
                <?php wp_nonce_field('het_seo_social_hub'); ?>
                <input type="hidden" name="het_action" value="create_campaign" />
                <table class="form-table">
                    <tr>
                        <th scope="row">Nazwa kampanii</th>
                        <td><input type="text" name="name" class="large-text" placeholder="np. Start kampanii — luty" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Post</th>
                        <td>
                            <select name="post_id">
                                <?php foreach ($posts as $p): ?>
                                    <option value="<?php echo intval($p['id']); ?>"><?php echo esc_html('#'.$p['id'].' — '.$p['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Tryb</th>
                        <td>
                            <label><input type="radio" name="mode" value="dry" checked /> Dry‑run</label>
                            &nbsp;&nbsp;
                            <label><input type="radio" name="mode" value="live" /> Live (wymaga konektorów)</label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">schedule_at (opcjonalnie)</th>
                        <td>
                            <input type="datetime-local" name="schedule_at" value="" />
                            <p class="description" style="margin-top:6px">Jeśli puste — wtyczka policzy najbliższy slot wg ustawień harmonogramu (Centrum → Socialmedia).</p>
                        </td>
                    </tr>


                    <tr>
                        <th scope="row">Kanały (provider)</th>
                        <td>
                            <?php
                              $sm = get_option('het_seo_socialmedia', []);
                              $channels = (is_array($sm) && isset($sm['channels']) && is_array($sm['channels'])) ? $sm['channels'] : [];
                              $def = (isset($channels['providers']) && is_array($channels['providers'])) ? $channels['providers'] : [];
                            ?>
                            <p class="description" style="margin-top:0">Jeśli nie wybierzesz konkretnych kont poniżej, a zaznaczysz kanały — kampania trafi na aktywne konta z tych providerów.</p>
                            <div style="display:flex;flex-wrap:wrap;gap:10px">
                              <?php foreach ($providers as $pk=>$pl): 
                                $ck = (is_array($def) && in_array($pk, $def, true)) ? 'checked' : '';
                              ?>
                                <label style="display:inline-block"><input type="checkbox" name="providers[]" value="<?php echo esc_attr($pk); ?>" <?php echo $ck; ?> /> <?php echo esc_html($pl); ?></label>
                              <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Cele (konta)</th>
                        <td>
                            <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="targets[]" value="" disabled /> (Jeśli nic nie zaznaczysz — wyśle na wszystkie aktywne)</label>
                            <?php foreach ($accounts as $a): ?>
                                <label style="display:block;">
                                    <input type="checkbox" name="targets[]" value="<?php echo intval($a['id']); ?>" />
                                    <?php echo esc_html($a['provider'].' — '.$a['account_label']); ?>
                                </label>
                            <?php endforeach; ?>
                            <?php if (empty($accounts)): ?>
                                <em>Dodaj najpierw konto w TAB „Konta”.</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Utwórz kampanię i wrzuć do kolejki'); ?>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'queue'): ?>
        <div class="het-card" style="padding:16px;margin-top:12px;">
            <h2 style="margin-top:0;">Kolejka</h2>
            <table class="widefat striped">
                <thead><tr><th>ID</th><th>Kampania</th><th>Konto</th><th>Status</th><th>Próby</th><th>Następny run</th><th>External</th><th>Ostatni błąd</th><th>Akcje</th></tr></thead>
                <tbody>
                <?php foreach ($queue as $q): ?>
                    <tr>
                        <td><?php echo intval($q['id']); ?></td>
                        <td><?php echo intval($q['campaign_id']); ?></td>
                        <td><?php echo intval($q['account_id']); ?></td>
                        <td><?php echo esc_html($q['status']); ?></td>
                        <td><?php echo intval($q['attempt']); ?>/<?php echo intval($q['max_attempts'] ?? 0); ?></td>
                        <td><?php echo esc_html($q['next_run_at'] ?? ''); ?></td>
                        <td><?php echo esc_html($q['external_ref'] ?? ''); ?></td>
                        <td><?php echo esc_html(mb_substr((string)($q['last_error'] ?? ''), 0, 120)); ?></td>
                        <td>
                          <?php if ($q['status'] === 'waiting_approval'): ?>
                            <form method="post" style="display:inline-block;margin:0 6px 0 0;">
                              <?php wp_nonce_field('het_seo_social_hub'); ?>
                              <input type="hidden" name="het_action" value="approve_send" />
                              <input type="hidden" name="queue_id" value="<?php echo intval($q['id']); ?>" />
                              <button class="button button-primary">Zatwierdź i wyślij</button>
                            </form>
                            <form method="post" style="display:inline-block;margin:0;">
                              <?php wp_nonce_field('het_seo_social_hub'); ?>
                              <input type="hidden" name="het_action" value="reject_item" />
                              <input type="hidden" name="queue_id" value="<?php echo intval($q['id']); ?>" />
                              <input type="hidden" name="reason" value="Rejected in SAFE mode" />
                              <button class="button">Odrzuć</button>
                            </form>
                          <?php else: ?>
                            <form method="post" style="display:inline-block;margin:0 6px 0 0;">
                              <?php wp_nonce_field('het_seo_social_hub'); ?>
                              <input type="hidden" name="het_action" value="retry_now" />
                              <input type="hidden" name="queue_id" value="<?php echo intval($q['id']); ?>" />
                              <button class="button button-secondary">Retry teraz</button>
                            </form>
                            <form method="post" style="display:inline-block;margin:0;">
                              <?php wp_nonce_field('het_seo_social_hub'); ?>
                              <input type="hidden" name="het_action" value="cancel_item" />
                              <input type="hidden" name="queue_id" value="<?php echo intval($q['id']); ?>" />
                              <input type="hidden" name="reason" value="Cancelled in UI" />
                              <button class="button">Anuluj</button>
                            </form>
                          <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($queue)): ?>
                    <tr><td colspan="9"><em>Pusto. Utwórz kampanię.</em></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'logs'): ?>
        <div class="het-card" style="padding:16px;margin-top:12px;">
            <h2 style="margin-top:0;">Logi</h2>
<?php $export_url = wp_nonce_url(admin_url('admin-post.php?action=het_seo_export_social_logs'), 'het_seo_export_social_logs'); ?>
            <p style="margin:10px 0 14px">
              <a class="button button-secondary" href="<?php echo esc_url($export_url); ?>">
                Eksport CSV
              </a>
            </p>

            <table class="widefat striped">
                <thead><tr><th>ID</th><th>Level</th><th>Message</th><th>Time</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td><?php echo intval($l['id']); ?></td>
                        <td><?php echo esc_html($l['level']); ?></td>
                        <td><?php echo esc_html($l['message']); ?></td>
                        <td><?php echo esc_html($l['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="4"><em>Brak logów.</em></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
