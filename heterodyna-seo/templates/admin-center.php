<?php if (!defined('ABSPATH')) { exit; }
$robots_url = home_url('/robots.txt');
$u404 = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_404], admin_url('admin.php'));
$urobots = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_ROBOTS], admin_url('admin.php'));
$uimex = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_IMPORT_EXPORT], admin_url('admin.php'));
$usysfiles = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_SYSFILES], admin_url('admin.php'));
$utests = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_TESTS], admin_url('admin.php'));
$uapi = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_API], admin_url('admin.php'));
$upsi = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_PSI], admin_url('admin.php'));
$ualerts = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_ALERTS], admin_url('admin.php'));
$uredir = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_REDIRECTS], admin_url('admin.php'));
$umeta = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_META_AUDIT], admin_url('admin.php'));
$ucanon = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_CANONICAL], admin_url('admin.php'));
$usitemap = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_SITEMAP], admin_url('admin.php'));
$uai = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_AI_AUTO], admin_url('admin.php'));
$uinsights = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_INSIGHTS], admin_url('admin.php'));
$uintegrations = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_INTEGRATIONS], admin_url('admin.php'));
$ustatus = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_STATUS], admin_url('admin.php'));
$uai_content = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_AI_CONTENT], admin_url('admin.php'));
$ucontent = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_CONTENT_AUDIT], admin_url('admin.php'));
$usocial = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_SOCIAL], admin_url('admin.php'));
$usocial_dash = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_SOCIAL_DASH], admin_url('admin.php'));
$usocial_accounts = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_SOCIAL_ACCOUNTS], admin_url('admin.php'));
$usocial_composer = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_SOCIAL_COMPOSER], admin_url('admin.php'));
$usocial_campaigns = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_SOCIAL_CAMPAIGNS], admin_url('admin.php'));
$usocial_queue = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_SOCIAL_QUEUE], admin_url('admin.php'));
$usocial_logs = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_SOCIAL_LOGS], admin_url('admin.php'));
?>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-admin-home"></span> Centrum</div>
  <div class="het-seo-card__sub">Rozwiń sekcje poniżej. Każdy blok jest klikalny (accordion) i trzyma linki do funkcji.</div>


<?php
$dash = isset($dashboard) && is_array($dashboard) ? $dashboard : [];
$ai_pending = isset($dash['ai_queue_pending']) ? (int)$dash['ai_queue_pending'] : 0;
$ai_approval = isset($dash['ai_queue_approval']) ? (int)$dash['ai_queue_approval'] : 0;
$psi_qp = isset($dash['psi_queue_pending']) ? (int)$dash['psi_queue_pending'] : 0;
$psi_last = isset($dash['psi_last']) && is_array($dash['psi_last']) ? $dash['psi_last'] : null;
$psi_perf = $psi_last && isset($psi_last['perf']) ? (int)$psi_last['perf'] : null;
$psi_url_last = $psi_last && isset($psi_last['url']) ? (string)$psi_last['url'] : '';
$psi_trend = isset($dash['psi_trend']) && is_array($dash['psi_trend']) ? $dash['psi_trend'] : [];
$sitekit = isset($dash['sitekit']) && is_array($dash['sitekit']) ? $dash['sitekit'] : null;
$sk_ok = is_array($sitekit) && !empty($sitekit['ok']);
$sk_gsc = $sk_ok && isset($sitekit['gsc']) && is_array($sitekit['gsc']) ? $sitekit['gsc'] : [];
$sk_ga4 = $sk_ok && isset($sitekit['ga4']) && is_array($sitekit['ga4']) ? $sitekit['ga4'] : [];
$sk_psi = $sk_ok && isset($sitekit['psi']) && is_array($sitekit['psi']) ? $sitekit['psi'] : [];
?>
<div class="het-seo-dash">
  <div class="het-seo-dash__card">
    <div class="het-seo-dash__kpi">
      <div class="het-seo-dash__label"><span class="dashicons dashicons-chart-line"></span> PSI (ostatni wynik)</div>
      <div class="het-seo-dash__value"><?php echo is_null($psi_perf) ? '—' : intval($psi_perf) . '/100'; ?></div>
      <?php if ($psi_url_last): ?>
        <div class="het-seo-dash__hint" title="<?php echo esc_attr($psi_url_last); ?>">
          <?php echo esc_html(wp_trim_words($psi_url_last, 8, '…')); ?>
        </div>
      <?php endif; ?>

      <?php if ($sk_ok): ?>
        <div class="het-seo-dash__kpis" title="Dane: Google Site Kit (best-effort)">
          <?php if (!empty($sk_gsc['clicks']) || !empty($sk_gsc['impressions'])): ?>
            <span class="het-pill">GSC: <?php echo esc_html((string)($sk_gsc['clicks'] ?? '—')); ?> klik.</span>
            <?php if (isset($sk_gsc['ctr']) && $sk_gsc['ctr'] !== null): ?>
              <span class="het-pill">CTR: <?php echo esc_html(number_format((float)$sk_gsc['ctr'], 2)); ?>%</span>
            <?php endif; ?>
          <?php endif; ?>

          <?php if (isset($sk_ga4['sessions']) && $sk_ga4['sessions'] !== null): ?>
            <span class="het-pill">GA4: <?php echo esc_html((string)$sk_ga4['sessions']); ?> sesji</span>
          <?php endif; ?>

          <?php if (isset($sk_psi['mobile']) && $sk_psi['mobile'] !== null): ?>
            <span class="het-pill">PSI m: <?php echo esc_html((string)$sk_psi['mobile']); ?></span>
          <?php endif; ?>
          <?php if (isset($sk_psi['desktop']) && $sk_psi['desktop'] !== null): ?>
            <span class="het-pill">PSI d: <?php echo esc_html((string)$sk_psi['desktop']); ?></span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="het-seo-dash__spark" aria-hidden="true">
      <svg viewBox="0 0 120 32" preserveAspectRatio="none">
        <?php
        $pts = [];
        $vals = [];
        foreach ($psi_trend as $p) { if (isset($p['v']) && $p['v'] !== null) { $vals[] = (int)$p['v']; } }
        $min = empty($vals) ? 0 : min($vals);
        $max = empty($vals) ? 100 : max($vals);
        if ($max === $min) { $max = $min + 1; }
        $n = max(1, count($psi_trend));
        $i = 0;
        foreach ($psi_trend as $p) {
          $v = isset($p['v']) ? $p['v'] : null;
          $x = ($n === 1) ? 0 : (120 * ($i / ($n - 1)));
          $y = 28;
          if ($v !== null) {
            $vv = (int)$v;
            $y = 28 - (24 * (($vv - $min) / ($max - $min)));
          }
          $pts[] = sprintf('%.2f,%.2f', $x, $y);
          $i++;
        }
        ?>
        <polyline fill="none" stroke="currentColor" stroke-width="2" points="<?php echo esc_attr(implode(' ', $pts)); ?>"></polyline>
      </svg>
    </div>
    <div class="het-seo-dash__actions">
      <a class="button button-small" href="<?php echo esc_url($upsi); ?>"><span class="dashicons dashicons-admin-generic"></span> Otwórz PSI</a>
      <a class="button button-small" href="<?php echo esc_url($utests); ?>"><span class="dashicons dashicons-search"></span> Testy usług</a>
    </div>
  </div>

  <div class="het-seo-dash__card">
    <div class="het-seo-dash__kpi">
      <div class="het-seo-dash__label"><span class="dashicons dashicons-sos"></span> AI kolejka</div>
      <div class="het-seo-dash__value"><?php echo intval($ai_pending); ?> <span class="het-seo-dash__unit">pending</span></div>
      <div class="het-seo-dash__hint"><?php echo intval($ai_approval); ?> do zatwierdzenia</div>
    </div>
    <div class="het-seo-dash__actions">
      <a class="button button-small" href="<?php echo esc_url($uai); ?>"><span class="dashicons dashicons-controls-repeat"></span> AI Auto</a>
      <a class="button button-small" href="<?php echo esc_url($umeta); ?>"><span class="dashicons dashicons-visibility"></span> Audyt meta</a>
    </div>
  </div>

  <div class="het-seo-dash__card">
    <div class="het-seo-dash__kpi">
      <div class="het-seo-dash__label"><span class="dashicons dashicons-clock"></span> PSI kolejka</div>
      <div class="het-seo-dash__value"><?php echo intval($psi_qp); ?> <span class="het-seo-dash__unit">pending</span></div>
      <div class="het-seo-dash__hint">Dodaj URL lub zaciągnij z sitemap</div>
    </div>
    <div class="het-seo-dash__actions">
      <a class="button button-small" href="<?php echo esc_url($upsi); ?>"><span class="dashicons dashicons-plus-alt"></span> Dodaj do PSI</a>
      <a class="button button-small" href="<?php echo esc_url($usitemap); ?>"><span class="dashicons dashicons-networking"></span> Sitemap</a>
    </div>
  </div>
</div>



</div>

<div class="het-acc">

  <div class="het-acc__item is-open" data-acc>
    <button class="het-acc__hdr" type="button">
      <span class="dashicons dashicons-admin-tools"></span>
      Uniwersalne
      <span class="het-acc__chev dashicons dashicons-arrow-down-alt2"></span>
    </button>
    <div class="het-acc__body">
      <div class="het-links">
        <a class="het-link" href="<?php echo esc_url($robots_url); ?>" target="_blank" rel="noopener">
          <span class="dashicons dashicons-shield"></span>
          <div>
            <div class="het-link__title">robots.txt</div>
            <div class="het-link__desc">Podgląd publicznego pliku robots.txt</div>
          </div>
        </a>

        <a class="het-link" href="<?php echo esc_url($urobots); ?>">
          <span class="dashicons dashicons-edit"></span>
          <div>
            <div class="het-link__title">Robots</div>
            <div class="het-link__desc">Edytor reguł + podgląd wynikowego robots.txt</div>
          </div>
        </a>
<a class="het-link" href="<?php echo esc_url($usysfiles); ?>">
  <span class="dashicons dashicons-media-document"></span>
  <div>
    <div class="het-link__title">Pliki systemowe</div>
    <div class="het-link__desc">Statusy robots/sitemap/security + szybkie testy</div>
  </div>
</a>

        <a class="het-link" href="<?php echo esc_url($u404); ?>">
          <span class="dashicons dashicons-warning"></span>
          <div>
            <div class="het-link__title">Monitor 404</div>
            <div class="het-link__desc">Loguje 404 (URL, referrer, hits, czas)</div>
          </div>
        </a>

        <a class="het-link" href="<?php echo esc_url($uimex); ?>">
          <span class="dashicons dashicons-migrate"></span>
          <div>
            <div class="het-link__title">Import / Export</div>
            <div class="het-link__desc">Eksport/Import ustawień (JSON)</div>
          </div>
        </a>
      </div>
    </div>
  </div>

  <div class="het-acc__item" data-acc>
    <button class="het-acc__hdr" type="button">
      <span class="dashicons dashicons-chart-line"></span>
      SEO Core
      <span class="het-acc__chev dashicons dashicons-arrow-down-alt2"></span>
    </button>
    <div class="het-acc__body">
      <div class="het-links">
        <a class="het-link" href="<?php echo esc_url($uinsights); ?>">
          <span class="dashicons dashicons-chart-bar"></span>
          <div>
            <div class="het-link__title">Analiza konfliktów SEO &amp; decyzje indeksowania</div>
            <div class="het-link__desc">Kanibalizacja + konflikty tematu + sugestie canonical/noindex</div>
          </div>
        </a>

        <a class="het-link" href="<?php echo esc_url($umeta); ?>">
          <span class="dashicons dashicons-search"></span>
          <div>
            <div class="het-link__title">Audyt meta</div>
            <div class="het-link__desc">Brakujące / duplikaty / długości (title/description)</div>
          </div>
        </a>

        <a class="het-link" href="<?php echo esc_url($ucanon); ?>">
          <span class="dashicons dashicons-admin-links"></span>
          <div>
            <div class="het-link__title">Kanoniczne + indeksowanie</div>
            <div class="het-link__desc">Canonical + noindex/nofollow (globalnie i per wpis)</div>
          </div>
        </a>

        <a class="het-link" href="<?php echo esc_url($usitemap); ?>">
          <span class="dashicons dashicons-networking"></span>
          <div>
            <div class="het-link__title">Sitemap</div>
            <div class="het-link__desc">Generator sitemap.xml + wybór post type</div>
          </div>
        </a>
      </div>
    </div>
  </div>

  <div class="het-acc__item" data-acc>
    <button class="het-acc__hdr" type="button">
      <span class="dashicons dashicons-superhero"></span>
      AI Auto
      <span class="het-acc__chev dashicons dashicons-arrow-down-alt2"></span>
    </button>
    <div class="het-acc__body">
      <div class="het-grid2">
        <div class="het-seo-card">
          <div class="het-seo-card__title">Tryb</div>
          <p class="het-muted">Domyślnie uruchamiaj <strong>Dry-run</strong> (bez zapisu). Potem przełącz na tryb wykonania. Ustawienia automatyki (autonomia, PANIC STOP, whitelist) są poniżej.</p>

          <div class="het-actions" style="align-items:center;flex-wrap:wrap">
            <button class="button button-primary" data-het-scan data-dry="1">Skanuj + Kolejka (dry-run)</button>
            <button class="button" data-het-scan data-dry="0">Skanuj + Kolejka (wykonaj)</button>
            <a class="button" href="<?php echo esc_url($uai); ?>">Pełne ustawienia AI Auto</a>
            <label class="het-inline"><input type="checkbox" id="hetIncludeCTR"> uwzględnij CTR-style (ostatni priorytet)</label>
          </div>

          <?php
            // Compact autonomy controls directly in Centrum (so user sees them even without side menu entries).
            $ai_settings = HET_SEO_AI_Auto::get_settings();
          ?>
          <form method="post" style="margin-top:10px">
            <?php wp_nonce_field('het_seo_save_ai_auto'); ?>
            <input type="hidden" name="het_seo_save_ai_auto" value="1" />
            <input type="hidden" name="enabled" value="<?php echo !empty($ai_settings['enabled']) ? '1' : '0'; ?>" />
            <input type="hidden" name="mode" value="<?php echo esc_attr($ai_settings['mode'] ?? 'dry'); ?>" />
            <input type="hidden" name="include_ctr" value="<?php echo !empty($ai_settings['include_ctr']) ? '1' : '0'; ?>" />
            <table class="form-table" role="presentation" style="margin:0">
              <tr>
                <th scope="row">Autonomia</th>
                <td>
                  <label><input type="radio" name="autonomy" value="manual" <?php checked($ai_settings['autonomy'] ?? 'manual', 'manual'); ?>> manual</label>
                  &nbsp;&nbsp;
                  <label><input type="radio" name="autonomy" value="semi" <?php checked($ai_settings['autonomy'] ?? 'manual', 'semi'); ?>> semi-auto</label>
                  &nbsp;&nbsp;
                  <label><input type="radio" name="autonomy" value="auto" <?php checked($ai_settings['autonomy'] ?? 'manual', 'auto'); ?>> auto</label>
                  <p class="description" style="margin:6px 0 0">Semi-auto tworzy zadania do zatwierdzenia. Auto przetwarza batch w tle.</p>
                </td>
              </tr>
              <tr>
                <th scope="row">PANIC STOP</th>
                <td>
                  <label><input type="checkbox" name="panic_stop" value="1" <?php checked(!empty($ai_settings['panic_stop'])); ?>> stop natychmiastowy</label>
                </td>
              </tr>
            </table>
            <p style="margin:8px 0 0"><button class="button button-secondary">Zapisz ustawienia automatyki</button></p>
          </form>
        </div>

        <div class="het-seo-card">
          <div class="het-seo-card__title">Kolejka</div>
          <p class="het-muted">Priorytety: brakujące meta → duplikaty → zbyt długie → CTR-style. <strong>Dry-run</strong> działa zawsze; zapisy są dostępne w buildzie open-source.</p>
          <div class="het-actions">
            <button class="button" data-het-refresh>Odśwież kolejkę</button>
            <button class="button button-primary" data-het-run>Przetwórz teraz (10)</button>
          </div>
          <div class="het-small" id="hetAiUsage"></div>
        </div>
      </div>

      <div class="het-seo-card" style="margin-top:12px">
        <div class="het-seo-card__title">Ostatnie zadania</div>
        <div class="het-small het-muted">Kliknij „Rollback” przy pozycji, żeby cofnąć zmiany z tego zadania.</div>
        <div id="hetQueueTable" class="het-tablewrap"></div>
      </div>

    </div>
  </div>

  <div class="het-acc__item" data-acc>
    <button class="het-acc__hdr" type="button">
      <span class="dashicons dashicons-admin-tools"></span>
      Diagnostyka
      <span class="het-acc__chev dashicons dashicons-arrow-down-alt2"></span>
    </button>
    <div class="het-acc__body">
      <div class="het-links">
        <a class="het-link" href="<?php echo esc_url($utests); ?>">
          <span class="dashicons dashicons-yes-alt"></span>
          <div>
            <div class="het-link__title">Testy usług</div>
            <div class="het-link__desc">REST / robots / sitemap / security + CRON</div>
          </div>
        </a>

        <a class="het-link" href="<?php echo esc_url($uapi); ?>">
          <span class="dashicons dashicons-rest-api"></span>
          <div>
            <div class="het-link__title">API</div>
            <div class="het-link__desc">Podgląd endpointów i wskazówki integracji</div>
          </div>
        </a>

        <a class="het-link" href="<?php echo esc_url($upsi); ?>">
          <span class="dashicons dashicons-performance"></span>
          <div>
            <div class="het-link__title">PSI</div>
            <div class="het-link__desc">Kolejka PageSpeed Insights (v5) + wyniki</div>
          </div>
        </a>

        <a class="het-link" href="<?php echo esc_url($ualerts); ?>">
          <span class="dashicons dashicons-warning"></span>
          <div>
            <div class="het-link__title">Alerty</div>
            <div class="het-link__desc">Reguły + historia zdarzeń + email opcjonalnie</div>
          </div>
        </a>

        <a class="het-link" href="<?php echo esc_url($uredir); ?>">
          <span class="dashicons dashicons-randomize"></span>
          <div>
            <div class="het-link__title">Przekierowania</div>
            <div class="het-link__desc">301/302 + statystyki (hits)</div>
          </div>
        </a>
      </div>
    </div>
  </div>


<div class="het-acc__item" data-acc>
  <button class="het-acc__hdr" type="button">
    <span class="dashicons dashicons-admin-links"></span>
    Integracje i dane
    <span class="het-acc__chev dashicons dashicons-arrow-down-alt2"></span>
  </button>
  <div class="het-acc__body">
    <div class="het-mini-grid">
      <div class="het-mini-tile">
        <div class="het-mini-tile__label">Google Site Kit</div>
        <div class="het-mini-tile__value">KPI + źródła danych</div>
        <div class="het-mini-tile__hint">Search Console / Analytics / PageSpeed / Ads</div>
      </div>
      <div class="het-mini-tile">
        <div class="het-mini-tile__label">Automaty</div>
        <div class="het-mini-tile__value">Panel integracji</div>
        <div class="het-mini-tile__hint">Zbieramy dane i wyciągamy wnioski</div>
      </div>
    </div>
    <div class="het-links" style="margin-top:10px">
      <a class="het-link" href="<?php echo esc_url($ustatus); ?>">
        <span class="dashicons dashicons-heart"></span>
        <div>
          <div class="het-link__title">Stan witryny</div>
          <div class="het-link__desc">Czytelny raport: zasoby publiczne, CRON, gotowość integracji</div>
        </div>
      </a>
      <a class="het-link" href="<?php echo esc_url($uintegrations); ?>">
        <span class="dashicons dashicons-admin-links"></span>
        <div>
          <div class="het-link__title">Integracje</div>
          <div class="het-link__desc">Status + dane (best-effort) + połączenia</div>
        </div>
      </a>
      <a class="het-link" href="<?php echo esc_url(admin_url('admin.php?page=googlesitekit-dashboard')); ?>" target="_blank" rel="noopener">
        <span class="dashicons dashicons-google"></span>
        <div>
          <div class="het-link__title">Site Kit — Dashboard</div>
          <div class="het-link__desc">Otwórz panel wtyczki Google Site Kit</div>
        </div>
      </a>
    </div>
  </div>
</div>

<div class="het-acc__item" data-acc>
  <button class="het-acc__hdr" type="button">
    <span class="dashicons dashicons-share"></span>
    Socialmedia
    <span class="het-acc__chev dashicons dashicons-arrow-down-alt2"></span>
  </button>
  <div class="het-acc__body">
    <?php
      $sm = get_option('het_seo_socialmedia', []);
      $sm_mode = isset($sm['mode']) ? (string)$sm['mode'] : 'manual';
      $sm_file = isset($sm['file_url']) ? (string)$sm['file_url'] : '';
      $sm_ai = isset($sm['ai_guidelines']) ? (string)$sm['ai_guidelines'] : '';
      $sm_ui_ultra = !empty($sm['ui_ultra']) ? 1 : 0;
      $voice = (isset($sm['voice']) && is_array($sm['voice'])) ? $sm['voice'] : [];
      $voice_tone = isset($voice['tone']) ? (string)$voice['tone'] : 'neutral';
      $voice_style = isset($voice['style']) ? (string)$voice['style'] : 'mixed';
      $voice_cta = isset($voice['cta_list']) ? (string)$voice['cta_list'] : '';
      $voice_hash = isset($voice['hashtags']) ? (string)$voice['hashtags'] : '';
      $voice_emoji = isset($voice['emoji_mode']) ? (string)$voice['emoji_mode'] : 'auto';
      $voice_banned = isset($voice['banned_words']) ? (string)$voice['banned_words'] : '';

      $sched = (isset($sm['schedule']) && is_array($sm['schedule'])) ? $sm['schedule'] : [];
      $sched_mode = isset($sched['mode']) ? (string)$sched['mode'] : 'immediate';
      $sched_days = (isset($sched['days']) && is_array($sched['days'])) ? $sched['days'] : ['mon','tue','wed','thu','fri'];
      $sched_hour_from = isset($sched['hour_from']) ? (int)$sched['hour_from'] : 9;
      $sched_hour_to = isset($sched['hour_to']) ? (int)$sched['hour_to'] : 18;
      $sched_interval = isset($sched['interval']) ? (int)$sched['interval'] : 30;
      $sched_max_per_tick = isset($sched['max_per_tick']) ? (int)$sched['max_per_tick'] : 20;


      $channels = (isset($sm['channels']) && is_array($sm['channels'])) ? $sm['channels'] : [];
      $ch_providers = (isset($channels['providers']) && is_array($channels['providers'])) ? $channels['providers'] : [];

      $utm = (isset($sm['utm']) && is_array($sm['utm'])) ? $sm['utm'] : [];
      $utm_source = isset($utm['source']) ? (string)$utm['source'] : '';
      $utm_medium = isset($utm['medium']) ? (string)$utm['medium'] : 'social';
      $utm_campaign_mode = isset($utm['campaign_mode']) ? (string)$utm['campaign_mode'] : 'name';
      $utm_campaign_fixed = isset($utm['campaign_fixed']) ? (string)$utm['campaign_fixed'] : '';
      $utm_content_mode = isset($utm['content_mode']) ? (string)$utm['content_mode'] : 'provider';
      $utm_term = isset($utm['term']) ? (string)$utm['term'] : '';
      $templates = (isset($sm['templates']) && is_array($sm['templates'])) ? $sm['templates'] : [];
      $tpl_get = function($prov, $key, $default='') use ($templates) {
        if (!isset($templates[$prov]) || !is_array($templates[$prov])) { return $default; }
        return isset($templates[$prov][$key]) ? (string)$templates[$prov][$key] : $default;
      };
      $tpl_x_prefix = $tpl_get('x','prefix','');
      $tpl_x_suffix = $tpl_get('x','suffix','');
      $tpl_x_max = (int)$tpl_get('x','max','280');
      $tpl_x_emoji = $tpl_get('x','emoji','auto');

      $tpl_li_prefix = $tpl_get('linkedin','prefix','');
      $tpl_li_suffix = $tpl_get('linkedin','suffix','');
      $tpl_li_max = (int)$tpl_get('linkedin','max','3000');
      $tpl_li_emoji = $tpl_get('linkedin','emoji','auto');

      $tpl_fb_prefix = $tpl_get('facebook','prefix','');
      $tpl_fb_suffix = $tpl_get('facebook','suffix','');
      $tpl_fb_max = (int)$tpl_get('facebook','max','5000');
      $tpl_fb_emoji = $tpl_get('facebook','emoji','auto');
      $notice = isset($_GET['het_seo_notice']) ? sanitize_text_field(wp_unslash($_GET['het_seo_notice'])) : '';
      $sm_msg = isset($_GET['err']) ? sanitize_text_field(wp_unslash($_GET['err'])) : '';
      if ($notice === 'socialmedia_saved') {
        echo '<div class="notice notice-success is-dismissible"><p><strong>Socialmedia:</strong> ustawienia zapisane ✅</p></div>';
      } elseif ($notice === 'sm_imported') {
        echo '<div class="notice notice-success is-dismissible"><p><strong>Importer:</strong> plik wczytany ✅ (zobacz podgląd poniżej)</p></div>';
      } elseif ($notice === 'sm_committed') {
        $c = isset($_GET['sm_created']) ? (int)$_GET['sm_created'] : 0;
        echo '<div class="notice notice-success is-dismissible"><p><strong>Kolejka:</strong> dodano do Social Hub ✅ (utworzono posty: ' . intval($c) . ')</p></div>';
      } elseif ($notice === 'sm_ticked') {
        echo '<div class="notice notice-success is-dismissible"><p><strong>Kolejka:</strong> przetworzono batch ✅ (dry-run = oznacza jako sent)</p></div>';
      } elseif (in_array($notice, ['sm_import_error','sm_commit_error'], true)) {
        $label = ($notice === 'sm_commit_error') ? 'Kolejka' : 'Importer';
        echo '<div class="notice notice-error"><p><strong>' . esc_html($label) . ':</strong> ' . esc_html($sm_msg ?: 'Wystąpił błąd') . '</p></div>';
      }
    ?>

    <div class="het-grid2">
      <div class="het-seo-card">
        <div class="het-seo-card__title">Ustawienia dodawania treści</div>
        <p class="het-muted" style="margin-top:6px">Wybierz, jak mają powstawać treści do publikacji (opisy/posty). Tryb <strong>AI inteligentne</strong> ma unikać schematów i pisać naturalnie — sterujesz tym wytycznymi poniżej.</p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <?php wp_nonce_field('het_seo_socialmedia_save'); ?>
          <input type="hidden" name="action" value="het_seo_socialmedia_save" />

          <table class="form-table" role="presentation" style="margin:0">
            <tr>
              <th scope="row">Tryb</th>
              <td>
                <label style="display:block;margin-bottom:6px"><input type="radio" name="het_seo_sm_mode" value="manual" <?php checked($sm_mode, 'manual'); ?>> <strong>Ręczne</strong> — tworzysz i publikujesz sam</label>
                <label style="display:block;margin-bottom:6px"><input type="radio" name="het_seo_sm_mode" value="file" <?php checked($sm_mode, 'file'); ?>> <strong>Półautomatyczne z pliku</strong> — importujesz tematy/briefy z pliku</label>
                <label style="display:block"><input type="radio" name="het_seo_sm_mode" value="ai_smart" <?php checked($sm_mode, 'ai_smart'); ?>> <strong>Automatyczne (AI inteligentne)</strong> — generuje treść naturalnie (bez schematu)</label>
              </td>
            </tr>
            <tr>
              <th scope="row">Plik (URL)</th>
              <td>
                <input class="regular-text" type="url" name="het_seo_sm_file_url" value="<?php echo esc_attr($sm_file); ?>" placeholder="np. https://twojadomena.pl/wp-content/uploads/social-briefs.json" />
                <p class="description" style="margin-top:6px">Używane w trybie „Półautomatyczne z pliku”. Może to być JSON/CSV — wczytasz je w Importerze poniżej.</p>
              </td>
            </tr>
            <tr>
              <th scope="row">Wytyczne dla AI</th>
              <td>
                <textarea name="het_seo_sm_ai_guidelines" rows="5" class="large-text" placeholder="Np. pisz zwięźle, bez fraz w stylu: 'W dzisiejszym artykule...', unikaj sztucznych wstępów, dawaj konkret, stosuj różne struktury."><?php echo esc_textarea($sm_ai); ?></textarea>
                <p class="description" style="margin-top:6px">W trybie „AI inteligentne” te wytyczne będą dołączane do promptów (kolejny krok: generator + kolejka publikacji).</p>
              </td>
            </tr> 
            <tr>
              <th scope="row">Wygląd</th>
              <td>
                <label style="display:block;margin:0 0 6px"><input type="checkbox" name="het_seo_sm_ui_ultra" value="1" <?php checked($sm_ui_ultra, 1); ?>> <strong>Ultra-compact</strong> — maksymalnie ścisły układ (tylko w Socialmedia)</label>
                <p class="description" style="margin-top:0">Jeśli chcesz, żeby Socialmedia mieściło się „na raz”, włącz to. Nie wpływa na resztę wtyczki.</p>
              </td>
            </tr>

            <tr>
              <th scope="row">Brand Voice</th>
              <td>
                <div class="het-seo-card" style="padding:12px;margin:0;border:1px solid rgba(0,0,0,.08);box-shadow:none">
                  <p class="het-muted" style="margin:0 0 10px">Ustawienia stylu tekstów w trybie <strong>AI inteligentne</strong>. Działa w Kreatorze i przy imporcie/kolejce.</p>

                  <p style="margin:0 0 10px">
                    <label style="display:block;margin-bottom:4px"><strong>Ton</strong></label>
                    <input class="regular-text" type="text" name="het_seo_sm_voice_tone" value="<?php echo esc_attr($voice_tone); ?>" placeholder="np. ekspercki, konkretny, bez lania wody" />
                  </p>

                  <p style="margin:0 0 10px">
                    <label style="display:block;margin-bottom:4px"><strong>Styl</strong></label>
                    <select name="het_seo_sm_voice_style">
                      <option value="mixed" <?php selected($voice_style,'mixed'); ?>>Mieszany (różne struktury)</option>
                      <option value="short" <?php selected($voice_style,'short'); ?>>Krótko i konkretnie</option>
                      <option value="bullets" <?php selected($voice_style,'bullets'); ?>>Punktowo / checklisty</option>
                      <option value="story" <?php selected($voice_style,'story'); ?>>Mini-story / przykład</option>
                    </select>
                  </p>

                  <p style="margin:0 0 10px">
                    <label style="display:block;margin-bottom:4px"><strong>CTA (1 na linię)</strong></label>
                    <textarea name="het_seo_sm_cta_list" rows="4" class="large-text" placeholder="Sprawdź u siebie i daj znać
Chcesz, żebym przejrzał URL?"><?php echo esc_textarea($voice_cta); ?></textarea>
                  </p>

                  <p style="margin:0 0 10px">
                    <label style="display:block;margin-bottom:4px"><strong>Hashtagi (lista lub przecinki)</strong></label>
                    <textarea name="het_seo_sm_hashtags" rows="2" class="large-text" placeholder="#SEO #Heterodyna #performance"><?php echo esc_textarea($voice_hash); ?></textarea>
                  </p>

                  <p style="margin:0 0 10px">
                    <label style="display:block;margin-bottom:4px"><strong>Emoji</strong></label>
                    <select name="het_seo_sm_emoji_mode">
                      <option value="off" <?php selected($voice_emoji,'off'); ?>>Wyłącz</option>
                      <option value="light" <?php selected($voice_emoji,'light'); ?>>Delikatnie</option>
                      <option value="auto" <?php selected($voice_emoji,'auto'); ?>>Auto</option>
                      <option value="bold" <?php selected($voice_emoji,'bold'); ?>>Mocno</option>
                    </select>
                  </p>

                  <p style="margin:0">
                    <label style="display:block;margin-bottom:4px"><strong>Słowa zakazane (1 na linię)</strong></label>
                    <textarea name="het_seo_sm_banned_words" rows="3" class="large-text" placeholder="W dzisiejszym artykule
Zanurzmy się
Warto zauważyć"><?php echo esc_textarea($voice_banned); ?></textarea>
                  </p>
                </div>
              </td>
            </tr>

            <tr>
              <th scope="row">Harmonogram</th>
              <td>
                <div class="het-seo-card" style="padding:12px;margin:0;border:1px solid rgba(0,0,0,.08);box-shadow:none">
                  <p class="het-muted" style="margin:0 0 10px">Używane przy planowaniu kampanii i imporcie do kolejki. Jeśli wybierzesz <strong>„Następny slot”</strong>, wtyczka sama wyliczy najbliższy termin w dozwolonych dniach i godzinach.</p>

                  <p style="margin:0 0 10px">
                    <label style="display:block;margin-bottom:4px"><strong>Tryb planowania</strong></label>
                    <select name="het_seo_sm_sched_mode">
                      <option value="immediate" <?php selected($sched_mode,'immediate'); ?>>Natychmiast (schedule_at = NULL)</option>
                      <option value="next_slot" <?php selected($sched_mode,'next_slot'); ?>>Następny slot (wg okna czasowego)</option>
                    </select>
                  </p>

                  <p style="margin:0 0 10px">
                    <label style="display:block;margin-bottom:6px"><strong>Dni publikacji</strong></label>
                    <?php
                      $days_map = [
                        'mon' => 'Pon', 'tue' => 'Wt', 'wed' => 'Śr', 'thu' => 'Czw',
                        'fri' => 'Pt', 'sat' => 'Sob', 'sun' => 'Nd'
                      ];
                      foreach ($days_map as $dk => $dl) {
                        $checked = (is_array($sched_days) && in_array($dk, $sched_days, true)) ? 'checked' : '';
                        echo '<label style="display:inline-block;margin:0 10px 6px 0;"><input type="checkbox" name="het_seo_sm_sched_days[]" value="' . esc_attr($dk) . '" ' . $checked . '> ' . esc_html($dl) . '</label>';
                      }
                    ?>
                  </p>

                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;max-width:420px">
                    <p style="margin:0">
                      <label style="display:block;margin-bottom:4px"><strong>Godzina od</strong></label>
                      <input type="number" min="0" max="23" name="het_seo_sm_sched_hour_from" value="<?php echo esc_attr((string)$sched_hour_from); ?>" style="width:100%" />
                    </p>
                    <p style="margin:0">
                      <label style="display:block;margin-bottom:4px"><strong>Godzina do</strong></label>
                      <input type="number" min="0" max="23" name="het_seo_sm_sched_hour_to" value="<?php echo esc_attr((string)$sched_hour_to); ?>" style="width:100%" />
                    </p>
                  </div>

                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;max-width:420px;margin-top:10px">
                    <p style="margin:0">
                      <label style="display:block;margin-bottom:4px"><strong>Interwał (min)</strong></label>
                      <input type="number" min="5" max="180" name="het_seo_sm_sched_interval" value="<?php echo esc_attr((string)$sched_interval); ?>" style="width:100%" />
                    </p>
                    <p style="margin:0">
                      <label style="display:block;margin-bottom:4px"><strong>Max / tick</strong></label>
                      <input type="number" min="1" max="200" name="het_seo_sm_sched_max_per_tick" value="<?php echo esc_attr((string)$sched_max_per_tick); ?>" style="width:100%" />
                    </p>
                  </div>

                  <p class="description" style="margin:10px 0 0">Strefa czasu: używamy ustawień WordPressa (<code>Ustawienia → Ogólne</code>). Kolejka respektuje <code>schedule_at</code> (nie wyśle przed czasem).</p>
                </div>
              </td>
            </tr>

            
            <tr>
              <th scope="row">Tryb publikacji</th>
              <td>
                <?php
                  $pub = (isset($sm['publish']) && is_array($sm['publish'])) ? $sm['publish'] : [];
                  $pub_mode = isset($pub['mode']) ? (string)$pub['mode'] : 'live';
                  if (!in_array($pub_mode, ['live','safe'], true)) { $pub_mode = 'live'; }
                  $lim = (isset($pub['limits']) && is_array($pub['limits'])) ? $pub['limits'] : [];
                  $lim_provider = isset($lim['per_provider_per_day']) ? (int)$lim['per_provider_per_day'] : 25;
                  $lim_campaign = isset($lim['per_campaign_per_day']) ? (int)$lim['per_campaign_per_day'] : 50;
                  $lim_provider = max(1, min(500, $lim_provider));
                  $lim_campaign = max(1, min(500, $lim_campaign));
                ?>
                <div class="het-seo-card" style="padding:12px;margin:0;border:1px solid rgba(0,0,0,.08);box-shadow:none">
                  <p class="het-muted" style="margin:0 0 10px">Ustaw globalny tryb wysyłki. <strong>SAFE</strong> oznacza, że kolejka w trybie <code>live</code> będzie zatrzymywać posty w statusie <code>waiting_approval</code> do ręcznego zatwierdzenia.</p>

                  <p style="margin:0 0 10px">
                    <label style="display:block;margin:0 0 6px"><input type="radio" name="het_seo_sm_publish_mode" value="live" <?php checked($pub_mode,'live'); ?>> <strong>Live</strong> — wysyłaj automatycznie (jeśli provider obsługuje live)</label>
                    <label style="display:block;margin:0"><input type="radio" name="het_seo_sm_publish_mode" value="safe" <?php checked($pub_mode,'safe'); ?>> <strong>Safe (approval)</strong> — wymagaj zatwierdzenia przed wysyłką</label>
                  </p>

                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;max-width:520px;margin-top:10px">
                    <p style="margin:0">
                      <label style="display:block;margin-bottom:4px"><strong>Limit dzienny / provider</strong></label>
                      <input type="number" min="1" max="500" name="het_seo_sm_limit_provider_day" value="<?php echo esc_attr((string)$lim_provider); ?>" style="width:100%" />
                      <span class="description">Dotyczy statusu <code>sent</code> w kolejce (dziś).</span>
                    </p>
                    <p style="margin:0">
                      <label style="display:block;margin-bottom:4px"><strong>Limit dzienny / kampania</strong></label>
                      <input type="number" min="1" max="500" name="het_seo_sm_limit_campaign_day" value="<?php echo esc_attr((string)$lim_campaign); ?>" style="width:100%" />
                      <span class="description">Chroni przed „zalaniem” jedną kampanią.</span>
                    </p>
                  </div>
                </div>
              </td>
            </tr>

<tr>
              <th scope="row">Kanały + UTM</th>
              <td>
                <div class="het-seo-card" style="padding:12px;margin:0;border:1px solid rgba(0,0,0,.08);box-shadow:none">
                  <p class="het-muted" style="margin:0 0 10px">Ustaw domyślne kanały (provider) oraz parametry UTM. Wykorzystywane w Social Hub (kampanie/kolejka) i w podglądzie linków.</p>

                  <p style="margin:0 0 10px">
                    <label style="display:block;margin-bottom:6px"><strong>Domyślne kanały</strong> (jeśli w kampanii nic nie wybierzesz)</label>
                    <div style="display:flex;flex-wrap:wrap;gap:10px">
                      <?php
                        $prov = class_exists('HET_SEO_Social_Hub') ? HET_SEO_Social_Hub::get_providers() : [];
                        foreach ($prov as $pk => $pl) {
                          $is = (is_array($ch_providers) && in_array($pk, $ch_providers, true)) ? 'checked' : '';
                          echo '<label style="display:inline-flex;align-items:center;gap:6px;margin:0"><input type="checkbox" name="het_seo_sm_channels_providers[]" value="' . esc_attr($pk) . '" ' . $is . '> ' . esc_html($pl) . '</label>';
                        }
                      ?>
                    </div>
                  </p>

                  <hr style="margin:12px 0" />

                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;max-width:720px">
                    <p style="margin:0">
                      <label style="display:block;margin-bottom:4px"><strong>utm_source</strong></label>
                      <input type="text" class="regular-text" name="het_seo_sm_utm_source" value="<?php echo esc_attr($utm_source); ?>" placeholder="np. heterodyna" />
                    </p>
                    <p style="margin:0">
                      <label style="display:block;margin-bottom:4px"><strong>utm_medium</strong></label>
                      <input type="text" class="regular-text" name="het_seo_sm_utm_medium" value="<?php echo esc_attr($utm_medium); ?>" placeholder="social" />
                    </p>
                  </div>

                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;max-width:720px;margin-top:10px">
                    <p style="margin:0">
                      <label style="display:block;margin-bottom:4px"><strong>utm_campaign</strong></label>
                      <select name="het_seo_sm_utm_campaign_mode">
                        <option value="name" <?php selected($utm_campaign_mode,'name'); ?>>Nazwa kampanii (auto)</option>
                        <option value="fixed" <?php selected($utm_campaign_mode,'fixed'); ?>>Stała wartość</option>
                      </select>
                      <input type="text" class="regular-text" name="het_seo_sm_utm_campaign_fixed" value="<?php echo esc_attr($utm_campaign_fixed); ?>" placeholder="np. launch_feb" style="margin-left:10px" />
                    </p>
                    <p style="margin:0">
                      <label style="display:block;margin-bottom:4px"><strong>utm_content</strong></label>
                      <select name="het_seo_sm_utm_content_mode">
                        <option value="off" <?php selected($utm_content_mode,'off'); ?>>Wyłącz</option>
                        <option value="provider" <?php selected($utm_content_mode,'provider'); ?>>Provider (np. facebook)</option>
                        <option value="account" <?php selected($utm_content_mode,'account'); ?>>Konto (etykieta)</option>
                      </select>
                    </p>
                  </div>

                  <p style="margin:10px 0 0;max-width:720px">
                    <label style="display:block;margin-bottom:4px"><strong>utm_term</strong> (opcjonalnie)</label>
                    <input type="text" class="regular-text" name="het_seo_sm_utm_term" value="<?php echo esc_attr($utm_term); ?>" placeholder="np. seo" />
                    <span class="description" style="display:block;margin-top:6px">UTM są dopinane do <em>source_url</em> w payload kolejki (dry-run) i będą użyte w realnych konektorach.</span>
                  </p>
                </div>
              </td>
            </tr>
            <tr>
              <th scope="row">Szablony treści (per kanał)</th>
              <td>
                <div class="het-seo-card" style="padding:12px;margin:0;border:1px solid rgba(0,0,0,.08);box-shadow:none">
                  <p class="het-muted" style="margin:0 0 10px">Te szablony są stosowane <strong>przed publikacją</strong> (testy i kolejka live): prefix/suffix, limity długości oraz tryb emoji. Dzięki temu treści są spójne i dopasowane do kanału.</p>

                  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;align-items:start">
                    <div style="border:1px solid rgba(0,0,0,.06);border-radius:12px;padding:10px">
                      <div style="font-weight:700;margin-bottom:8px">X</div>
                      <label style="display:block;margin:0 0 6px"><span class="description">Prefix</span>
                        <input type="text" class="regular-text" name="het_seo_sm_tpl_x_prefix" value="<?php echo esc_attr($tpl_x_prefix); ?>" style="width:100%" />
                      </label>
                      <label style="display:block;margin:0 0 6px"><span class="description">Suffix / CTA</span>
                        <input type="text" class="regular-text" name="het_seo_sm_tpl_x_suffix" value="<?php echo esc_attr($tpl_x_suffix); ?>" style="width:100%" />
                      </label>
                      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <label style="display:block;margin:0"><span class="description">Max</span>
                          <input type="number" min="10" max="10000" name="het_seo_sm_tpl_x_max" value="<?php echo esc_attr((string)$tpl_x_max); ?>" style="width:100%" />
                        </label>
                        <label style="display:block;margin:0"><span class="description">Emoji</span>
                          <select name="het_seo_sm_tpl_x_emoji" style="width:100%">
                            <option value="auto" <?php selected($tpl_x_emoji,'auto'); ?>>Auto</option>
                            <option value="off" <?php selected($tpl_x_emoji,'off'); ?>>Off</option>
                            <option value="light" <?php selected($tpl_x_emoji,'light'); ?>>Light</option>
                            <option value="bold" <?php selected($tpl_x_emoji,'bold'); ?>>Bold</option>
                          </select>
                        </label>
                      </div>
                    </div>

                    <div style="border:1px solid rgba(0,0,0,.06);border-radius:12px;padding:10px">
                      <div style="font-weight:700;margin-bottom:8px">LinkedIn</div>
                      <label style="display:block;margin:0 0 6px"><span class="description">Prefix</span>
                        <input type="text" class="regular-text" name="het_seo_sm_tpl_li_prefix" value="<?php echo esc_attr($tpl_li_prefix); ?>" style="width:100%" />
                      </label>
                      <label style="display:block;margin:0 0 6px"><span class="description">Suffix / CTA</span>
                        <input type="text" class="regular-text" name="het_seo_sm_tpl_li_suffix" value="<?php echo esc_attr($tpl_li_suffix); ?>" style="width:100%" />
                      </label>
                      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <label style="display:block;margin:0"><span class="description">Max</span>
                          <input type="number" min="10" max="10000" name="het_seo_sm_tpl_li_max" value="<?php echo esc_attr((string)$tpl_li_max); ?>" style="width:100%" />
                        </label>
                        <label style="display:block;margin:0"><span class="description">Emoji</span>
                          <select name="het_seo_sm_tpl_li_emoji" style="width:100%">
                            <option value="auto" <?php selected($tpl_li_emoji,'auto'); ?>>Auto</option>
                            <option value="off" <?php selected($tpl_li_emoji,'off'); ?>>Off</option>
                            <option value="light" <?php selected($tpl_li_emoji,'light'); ?>>Light</option>
                            <option value="bold" <?php selected($tpl_li_emoji,'bold'); ?>>Bold</option>
                          </select>
                        </label>
                      </div>
                    </div>

                    <div style="border:1px solid rgba(0,0,0,.06);border-radius:12px;padding:10px">
                      <div style="font-weight:700;margin-bottom:8px">Facebook</div>
                      <label style="display:block;margin:0 0 6px"><span class="description">Prefix</span>
                        <input type="text" class="regular-text" name="het_seo_sm_tpl_fb_prefix" value="<?php echo esc_attr($tpl_fb_prefix); ?>" style="width:100%" />
                      </label>
                      <label style="display:block;margin:0 0 6px"><span class="description">Suffix / CTA</span>
                        <input type="text" class="regular-text" name="het_seo_sm_tpl_fb_suffix" value="<?php echo esc_attr($tpl_fb_suffix); ?>" style="width:100%" />
                      </label>
                      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <label style="display:block;margin:0"><span class="description">Max</span>
                          <input type="number" min="10" max="10000" name="het_seo_sm_tpl_fb_max" value="<?php echo esc_attr((string)$tpl_fb_max); ?>" style="width:100%" />
                        </label>
                        <label style="display:block;margin:0"><span class="description">Emoji</span>
                          <select name="het_seo_sm_tpl_fb_emoji" style="width:100%">
                            <option value="auto" <?php selected($tpl_fb_emoji,'auto'); ?>>Auto</option>
                            <option value="off" <?php selected($tpl_fb_emoji,'off'); ?>>Off</option>
                            <option value="light" <?php selected($tpl_fb_emoji,'light'); ?>>Light</option>
                            <option value="bold" <?php selected($tpl_fb_emoji,'bold'); ?>>Bold</option>
                          </select>
                        </label>
                      </div>
                      <p class="description" style="margin:8px 0 0">Facebook max domyślnie 5000.</p>
                    </div>
                  </div>
                </div>
              </td>
            </tr>




          </table>
          <p style="margin:10px 0 0"><button class="button button-primary">Zapisz Socialmedia</button></p>
        </form>
      </div>

      <div class="het-seo-card">
        <div class="het-seo-card__title">Narzędzia i funkcje</div>
        <p class="het-muted" style="margin-top:6px">Wszystko w jednym miejscu: integracje, kreator, kampanie, kolejka, logi.</p>

        <div class="het-sm-tools">
          <div class="het-sm-tools__row">
            <div class="het-sm-tools__label">🔌 Integracje</div>
            <div class="het-actionbar">
              <a class="het-action" href="<?php echo esc_url($usocial); ?>"><span class="dashicons dashicons-admin-links"></span><span>Open Graph / Twitter</span></a>
              <a class="het-action" href="<?php echo esc_url($usocial_accounts); ?>"><span class="dashicons dashicons-admin-users"></span><span>Konta / Kanały</span></a>
            </div>
          </div>

          <div class="het-sm-tools__row">
            <div class="het-sm-tools__label">⚙️ Funkcje wykonawcze</div>
            <div class="het-actionbar">
              <a class="het-action" href="<?php echo esc_url($usocial_dash); ?>"><span class="dashicons dashicons-dashboard"></span><span>Dashboard</span></a>
              <a class="het-action" href="<?php echo esc_url($usocial_composer); ?>"><span class="dashicons dashicons-edit"></span><span>Kreator</span></a>
              <a class="het-action" href="<?php echo esc_url($usocial_campaigns); ?>"><span class="dashicons dashicons-megaphone"></span><span>Kampanie</span></a>
              <a class="het-action" href="<?php echo esc_url($usocial_queue); ?>"><span class="dashicons dashicons-clock"></span><span>Kolejka</span></a>
              <a class="het-action" href="<?php echo esc_url($usocial_logs); ?>"><span class="dashicons dashicons-text-page"></span><span>Logi</span></a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php
      // Import preview payload (transient) when available
      $sm_token = isset($_GET['sm_token']) ? sanitize_text_field(wp_unslash($_GET['sm_token'])) : '';
      $sm_preview = null;
      if ($sm_token) {
        $k = 'het_seo_sm_import_' . get_current_user_id() . '_' . $sm_token;
        $sm_preview = get_transient($k);
      }
    ?>

    <div class="het-grid2" style="margin-top:12px;">
      <div class="het-seo-card">
        <div class="het-seo-card__title">Importer (JSON/CSV) → podgląd → kolejka</div>
        <p class="het-muted" style="margin-top:6px">Tryb „Półautomatyczne z pliku” działa end-to-end: wczytaj plik → zobacz rekordy → dodaj do kolejki (dry-run). Obsługiwane pola: <code>topic</code>, <code>brief</code>, <code>url</code>, <code>tags</code> (JSON/CSV).</p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
          <?php wp_nonce_field('het_seo_sm_import'); ?>
          <input type="hidden" name="action" value="het_seo_sm_import" />
          <table class="form-table" role="presentation" style="margin:0">
            <tr>
              <th scope="row">URL pliku</th>
              <td>
                <input class="regular-text" type="url" name="het_seo_sm_import_url" value="<?php echo esc_attr($sm_file); ?>" placeholder="https://.../social-briefs.json" />
                <p class="description" style="margin-top:6px">Może być JSON (lista obiektów) albo CSV z nagłówkami.</p>
              </td>
            </tr>
            <tr>
              <th scope="row">Wgraj plik</th>
              <td>
                <input type="file" name="het_seo_sm_upload" accept=".json,.csv,text/csv,application/json" />
                <p class="description" style="margin-top:6px">Limit 2MB. Upload ma priorytet nad URL.</p>
              </td>
            </tr>
          </table>
          <p style="margin:10px 0 0"><button class="button button-primary">Importuj i pokaż podgląd</button></p>
        </form>

        <?php if (is_array($sm_preview) && !empty($sm_preview['items']) && is_array($sm_preview['items'])): ?>
          <hr style="margin:14px 0;" />
          <h3 style="margin:0 0 8px">Podgląd (pierwsze 10)</h3>
          <div style="overflow:auto;border:1px solid #e5e7eb;border-radius:10px">
            <table class="widefat striped" style="margin:0;min-width:720px">
              <thead>
                <tr>
                  <th>Temat</th>
                  <th>Brief</th>
                  <th>URL</th>
                  <th>Tagi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (array_slice($sm_preview['items'], 0, 10) as $it): ?>
                  <tr>
                    <td><?php echo esc_html((string)($it['topic'] ?? '')); ?></td>
                    <td><?php echo esc_html(wp_trim_words((string)($it['brief'] ?? ''), 18, '…')); ?></td>
                    <td><?php echo esc_html(wp_trim_words((string)($it['url'] ?? ''), 8, '…')); ?></td>
                    <td><?php echo esc_html((string)($it['tags'] ?? '')); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px">
            <?php wp_nonce_field('het_seo_sm_commit'); ?>
            <input type="hidden" name="action" value="het_seo_sm_commit" />
            <input type="hidden" name="sm_token" value="<?php echo esc_attr($sm_token); ?>" />
            <label style="display:inline-block;margin:0 10px 0 0;"><input type="checkbox" name="sm_use_schedule" value="1" checked> Zaplanuj wg ustawień harmonogramu</label>

            <button class="button button-secondary">Dodaj do kolejki (dry-run)</button>
            <span class="het-muted" style="margin-left:8px">Utworzy szkice postów + wpisy w kolejce. Publikacja zewnętrzna: później (integracje).</span>
          </form>
        <?php endif; ?>
      </div>

      <div class="het-seo-card">
        <div class="het-seo-card__title">Kolejka (dry-run) — „Przetwórz teraz”</div>
        <p class="het-muted" style="margin-top:6px">Na tym etapie <strong>dry-run</strong> oznacza: przetworzenie kolejki i zapis do logów (bez publikacji do Facebook/IG/X). Dzięki temu masz cały pipeline gotowy.</p>

        <?php
          global $wpdb;
          $tQ = $wpdb->prefix . 'het_seo_social_queue';
          $q_pending = (int)$wpdb->get_var("SELECT COUNT(1) FROM {$tQ} WHERE status='queued'");
          $q_sent = (int)$wpdb->get_var("SELECT COUNT(1) FROM {$tQ} WHERE status='sent'");
          $q_blocked = (int)$wpdb->get_var("SELECT COUNT(1) FROM {$tQ} WHERE status='blocked'");
        ?>

        <div class="het-metrics" style="margin-top:10px">
          <div class="het-metric"><div class="het-metric__k">Queued</div><div class="het-metric__v"><?php echo intval($q_pending); ?></div></div>
          <div class="het-metric"><div class="het-metric__k">Sent</div><div class="het-metric__v"><?php echo intval($q_sent); ?></div></div>
          <div class="het-metric"><div class="het-metric__k">Blocked</div><div class="het-metric__v"><?php echo intval($q_blocked); ?></div></div>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px">
          <?php wp_nonce_field('het_seo_sm_tick'); ?>
          <input type="hidden" name="action" value="het_seo_sm_tick" />
          <button class="button button-primary">Przetwórz teraz (max 20)</button>
        </form>

        <p style="margin-top:10px"><a class="button button-small" href="<?php echo esc_url($usocial_queue); ?>">Podgląd kolejki</a> <a class="button button-small" href="<?php echo esc_url($usocial_logs); ?>">Logi</a></p>
      </div>
    </div>

    <!-- (Importer/Queue block rendered above) -->
  </div>
</div>

<div class="het-acc__item" data-acc>
  <button class="het-acc__hdr" type="button">
    <span class="dashicons dashicons-controls-repeat"></span>
    Pakiety
    <span class="het-acc__chev dashicons dashicons-arrow-down-alt2"></span>
  </button>
  <div class="het-acc__body">
    <div class="het-links">
      <a class="het-link" href="<?php echo esc_url(admin_url('admin.php?page=' . HET_SEO_Admin::MENU_SLUG_DATA_SITE)); ?>">
        <span class="dashicons dashicons-chart-bar"></span>
        <div>
          <div class="het-link__title">Dane strony</div>
          <div class="het-link__desc">Rentgen domeny: posty/URL, nagłówki, linki, meta, PSI (best-effort)</div>
        </div>
      </a>

      <a class="het-link" href="<?php echo esc_url(admin_url('admin.php?page=' . HET_SEO_Admin::MENU_SLUG_DATA_URL)); ?>">
        <span class="dashicons dashicons-media-spreadsheet"></span>
        <div>
          <div class="het-link__title">Dane URL</div>
          <div class="het-link__desc">Lista URL + metryki treści, meta, linki, canonical/noindex, PSI</div>
        </div>
      </a>

      <a class="het-link" href="<?php echo esc_url(admin_url('admin.php?page=' . HET_SEO_Admin::MENU_SLUG_HISTORY)); ?>">
        <span class="dashicons dashicons-backup"></span>
        <div>
          <div class="het-link__title">Historia SEO</div>
          <div class="het-link__desc">Timeline zmian: meta, treść, nagłówki, linki (snapshoty)</div>
        </div>
      </a>

      <a class="het-link" href="<?php echo esc_url($uai_content); ?>">
        <span class="dashicons dashicons-welcome-write-blog"></span>
        <div>
          <div class="het-link__title">Generator treści (AI)</div>
          <div class="het-link__desc">Brief → artykuł → FAQ → schema (w przygotowaniu)</div>
        </div>
      </a>
      <a class="het-link" href="admin.php?page=het_seo_site_mode">
        <span class="dashicons dashicons-admin-customizer"></span>
        <div>
          <div class="het-link__title">Tryb witryny</div>
          <div class="het-link__desc">Blog / sklep / katalog / portal / wizytówka → rekomendacje konfiguracji</div>
        </div>
      </a>

      <a class="het-link" href="<?php echo esc_url($ucontent); ?>">
        <span class="dashicons dashicons-edit-page"></span>
        <div>
          <div class="het-link__title">Audyt treści</div>
          <div class="het-link__desc">Co poprawić: krótka treść, brak meta, brak obrazka</div>
        </div>
      </a>

      <a class="het-link" href="<?php echo esc_url($uai); ?>">
        <span class="dashicons dashicons-chart-pie"></span>
        <div>
          <div class="het-link__title">SEO Autopilot</div>
          <div class="het-link__desc">Pół-auto i auto zatwierdzanie zmian na podstawie AI + KPI</div>
        </div>
      </a>
    </div>
  </div>
</div>


</div>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>