<?php if (!defined('ABSPATH')) { exit; }
$base = admin_url('admin.php');
$tab = isset($data['tab']) ? $data['tab'] : 'providers';
$providers = isset($data['providers']) && is_array($data['providers']) ? $data['providers'] : [];
$provider_detail = isset($data['provider_detail']) && is_array($data['provider_detail']) ? $data['provider_detail'] : null;
$keywords = isset($data['keywords']) && is_array($data['keywords']) ? $data['keywords'] : [];
$keywords_last = isset($data['keywords_last']) && is_array($data['keywords_last']) ? $data['keywords_last'] : [];

$u_self = add_query_arg(['page'=>HET_SEO_Admin::MENU_SLUG_INTEGRATIONS], $base);
$u_tab = function($t) use ($u_self) { return add_query_arg(['tab'=>$t], $u_self); };
?>
<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-admin-links"></span> Integracje & Rankingi</div>
  <div class="het-seo-card__sub">
    Podpinamy wyszukiwarki / Webmaster Tools i zbieramy <strong>pozycje słów kluczowych</strong> + metryki domeny.
    Bez ryzykownego scrapingu SERP z WordPressa — wspieramy <strong>Site Kit</strong>, <strong>API (advanced)</strong> oraz <strong>import CSV/JSON</strong>.
  </div>

  <div class="het-tabs" style="margin-top:12px">
    <a class="het-tab <?php echo $tab==='providers'?'is-active':''; ?>" href="<?php echo esc_url($u_tab('providers')); ?>">Konektory</a>
    <a class="het-tab <?php echo $tab==='sitekit'?'is-active':''; ?>" href="<?php echo esc_url($u_tab('sitekit')); ?>">Google Site Kit</a>
    <a class="het-tab <?php echo $tab==='rankings'?'is-active':''; ?>" href="<?php echo esc_url($u_tab('rankings')); ?>">Rankingi</a>
  </div>

  <?php if ($tab === 'providers'): ?>
    <div class="het-mini-grid" style="margin-top:12px">
      <?php foreach ($providers as $p): ?>
        <?php
          $u_p = add_query_arg(['tab'=>'provider','provider'=>$p['key']], $u_self);
          $badge = $p['connected'] ? 'Połączone ✅' : 'Niepodpięte ❌';
        ?>
        <div class="het-mini-tile">
          <div class="het-mini-tile__label"><?php echo esc_html($p['label']); ?></div>
          <div class="het-mini-tile__value"><?php echo esc_html($badge); ?></div>
          <div class="het-mini-tile__hint"><?php echo esc_html($p['hint']); ?></div>
          <div class="het-actions" style="margin-top:10px">
            <a class="button button-primary" href="<?php echo esc_url($u_p); ?>"><span class="dashicons dashicons-admin-generic"></span> Ustawienia</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="het-seo-card" style="margin-top:14px">
      <div class="het-seo-card__title" style="font-size:14px"><span class="dashicons dashicons-lightbulb"></span> Pro tip (WOW)</div>
      <div class="het-seo-card__sub">
        Największy efekt „wow” robisz tak: podłącz GSC/Bing, dodaj listę fraz do śledzenia, a potem w „Monitoring SEO” masz timeline i alerty.
        Ta zakładka jest centrum integracji + rankingu.
      </div>
    </div>

  <?php elseif ($tab === 'provider' && $provider_detail): ?>
    <?php
      $key = $provider_detail['key'];
      $settings = isset($provider_detail['settings']) && is_array($provider_detail['settings']) ? $provider_detail['settings'] : [];
      $connected = !empty($settings['connected']);
      $label = $key;
      foreach ($providers as $p) { if ($p['key'] === $key) { $label = $p['label']; break; } }
    ?>
    <div class="het-seo-card" style="margin-top:12px">
      <div class="het-seo-card__title"><span class="dashicons dashicons-admin-generic"></span> Ustawienia: <?php echo esc_html($label); ?></div>
      <div class="het-seo-card__sub">Tryb „advanced” = możesz wkleić dane dostępowe. Jeśli nie, użyj importu w zakładce Rankingi.</div>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px">
        <?php wp_nonce_field('het_seo_integrations_save'); ?>
        <input type="hidden" name="action" value="het_seo_integrations_save" />
        <input type="hidden" name="provider" value="<?php echo esc_attr($key); ?>" />

        <label style="display:block;margin:8px 0">
          <input type="checkbox" name="connected" value="1" <?php checked($connected); ?> />
          <strong>Oznacz jako połączone</strong>
        </label>

        <div class="het-mini-grid" style="margin-top:10px">
          <div class="het-mini-tile">
            <div class="het-mini-tile__label">Property / domena</div>
            <div class="het-mini-tile__hint">np. https://twojadomena.pl/</div>
            <input class="regular-text" type="text" name="settings[property]" value="<?php echo esc_attr($settings['property'] ?? home_url('/')); ?>" />
          </div>
          <div class="het-mini-tile">
            <div class="het-mini-tile__label">Tryb</div>
            <div class="het-mini-tile__hint">sitekit / api / manual</div>
            <select name="settings[mode]">
              <?php
                $mode = $settings['mode'] ?? 'manual';
                $modes = ['manual'=>'Manual (import)', 'sitekit'=>'Site Kit (bridge)', 'api'=>'API (advanced)'];
                foreach ($modes as $k2=>$v2) {
                  echo '<option value="'.esc_attr($k2).'" '.selected($mode,$k2,false).'>'.esc_html($v2).'</option>';
                }
              ?>
            </select>
          </div>
        </div>

        <div class="het-seo-card" style="margin-top:12px">
          <div class="het-seo-card__title" style="font-size:14px"><span class="dashicons dashicons-shield"></span> Dane dostępowe (opcjonalnie)</div>
          <div class="het-seo-card__sub">Nie wymagane do działania — na start wystarczy import. Jeśli wpiszesz, to w kolejnych paczkach podepniemy automatyczne pobieranie.</div>

          <div class="het-mini-grid" style="margin-top:10px">
            <div class="het-mini-tile">
              <div class="het-mini-tile__label">Client ID</div>
              <div class="het-mini-tile__hint">Skąd wziąć: konsola dostawcy (np. Google Cloud / Microsoft). Wklej dokładnie bez spacji.</div>
              <div class="het-mini-tile__hint"><a target="_blank" rel="noopener" href="https://console.cloud.google.com/apis/credentials">Google Cloud → Credentials</a> • <a target="_blank" rel="noopener" href="https://portal.azure.com/">Microsoft/Azure Portal</a></div>
              <input class="regular-text" type="text" name="settings[client_id]" value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>" />
            </div>
            <div class="het-mini-tile">
              <div class="het-mini-tile__label">Client Secret</div>
              <div class="het-mini-tile__hint">Sekret aplikacji z konsoli dostawcy. Traktuj jak hasło — nie wysyłaj mailem, nie wklejaj w publiczne miejsca.</div>
              <input class="regular-text" type="password" name="settings[client_secret]" value="<?php echo esc_attr($settings['client_secret'] ?? ''); ?>" />
            </div>
            <div class="het-mini-tile">
              <div class="het-mini-tile__label">Refresh Token / API Key</div>
              <div class="het-mini-tile__hint">W zależności od dostawcy: token odświeżania (OAuth) albo klucz API. Jeśli nie wiesz — zostaw puste i użyj importu w „Rankingi”.</div>
              <div class="het-mini-tile__hint"><a target="_blank" rel="noopener" href="https://search.google.com/search-console">Google Search Console</a> • <a target="_blank" rel="noopener" href="https://www.bing.com/webmasters/">Bing Webmaster Tools</a></div>
              <input class="regular-text" type="password" name="settings[token]" value="<?php echo esc_attr($settings['token'] ?? ''); ?>" />
            </div>
          </div>
        </div>

        <div class="het-actions" style="margin-top:12px">
          <a class="button" href="<?php echo esc_url($u_tab('providers')); ?>"><span class="dashicons dashicons-arrow-left-alt"></span> Wróć</a>
          <button class="button button-primary"><span class="dashicons dashicons-saved"></span> Zapisz</button>
        </div>
      </form>
    </div>

  <?php elseif ($tab === 'sitekit'): ?>
    <?php
      $sitekit_admin = admin_url('admin.php?page=googlesitekit-dashboard');
      $active = !empty($data['site_kit_active']);
      $opts = isset($data['site_kit_options']) && is_array($data['site_kit_options']) ? $data['site_kit_options'] : [];
    ?>
    <div class="het-mini-grid" style="margin-top:12px">
      <div class="het-mini-tile">
        <div class="het-mini-tile__label">Google Site Kit</div>
        <div class="het-mini-tile__value"><?php echo $active ? 'Aktywny ✅' : 'Nieaktywny ❌'; ?></div>
        <div class="het-mini-tile__hint">Jeśli aktywny, to możesz potraktować go jako bridge do GSC/GA.</div>
      </div>
      <div class="het-mini-tile">
        <div class="het-mini-tile__label">Otwórz panel</div>
        <div class="het-mini-tile__value">—</div>
        <div class="het-actions" style="margin-top:10px">
          <a class="button button-primary" target="_blank" rel="noopener" href="<?php echo esc_url($sitekit_admin); ?>"><span class="dashicons dashicons-google"></span> Otwórz Site Kit</a>
        </div>
      </div>
    </div>

    <div class="het-seo-card" style="margin-top:14px">
      <div class="het-seo-card__title" style="font-size:14px"><span class="dashicons dashicons-database"></span> Dane (best-effort)</div>
      <div class="het-seo-card__sub">Podgląd kilku opcji Site Kit (read-only) — bez ryzyka, że coś popsujemy.</div>
      <div class="het-tablewrap" style="margin-top:10px">
        <table class="widefat striped">
          <thead><tr><th>Klucz</th><th>Wartość</th></tr></thead>
          <tbody>
            <?php if (empty($opts)): ?>
              <tr><td colspan="2">Brak danych.</td></tr>
            <?php else: foreach ($opts as $k=>$v): ?>
              <tr>
                <td><code><?php echo esc_html($k); ?></code></td>
                <td><pre style="white-space:pre-wrap;margin:0"><?php echo esc_html(print_r($v,true)); ?></pre></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($tab === 'rankings'): ?>
    <div class="het-mini-grid" style="margin-top:12px">
      <div class="het-mini-tile">
        <div class="het-mini-tile__label">Aktywne frazy</div>
        <div class="het-mini-tile__value"><?php echo intval(count($keywords)); ?></div>
        <div class="het-mini-tile__hint">Dodaj frazy albo zaimportuj dane z GSC / Bing / zewn. rank trackerów.</div>
      </div>
      <div class="het-mini-tile">
        <div class="het-mini-tile__label">Import</div>
        <div class="het-mini-tile__value">CSV/JSON</div>
        <div class="het-mini-tile__hint">Bez scrapingu. Importujesz pozycje i masz historię.</div>
      </div>
    </div>

    <div class="het-seo-card" style="margin-top:14px">
      <div class="het-seo-card__title"><span class="dashicons dashicons-plus"></span> Dodaj frazę do śledzenia</div>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px">
        <?php wp_nonce_field('het_seo_rank_add_kw'); ?>
        <input type="hidden" name="action" value="het_seo_rank_add_kw" />
        <div class="het-mini-grid">
          <div class="het-mini-tile">
            <div class="het-mini-tile__label">Fraza</div>
            <input class="regular-text" type="text" name="keyword" placeholder="np. heterodyna seo" required />
          </div>
          <div class="het-mini-tile">
            <div class="het-mini-tile__label">Wyszukiwarka</div>
            <select name="engine">
              <option value="google">Google</option>
              <option value="bing">Bing</option>
              <option value="yandex">Yandex</option>
              <option value="seznam">Seznam</option>
              <option value="baidu">Baidu</option>
              <option value="naver">Naver</option>
            </select>
          </div>
          <div class="het-mini-tile">
            <div class="het-mini-tile__label">Locale</div>
            <input class="regular-text" type="text" name="locale" value="pl-PL" />
          </div>
          <div class="het-mini-tile">
            <div class="het-mini-tile__label">Urządzenie</div>
            <select name="device">
              <option value="mobile">Mobile</option>
              <option value="desktop">Desktop</option>
            </select>
          </div>
          <div class="het-mini-tile">
            <div class="het-mini-tile__label">Target URL (opcjonalnie)</div>
            <input class="regular-text" type="text" name="target_url" placeholder="https://..." />
          </div>
        </div>
        <div class="het-actions" style="margin-top:10px">
          <button class="button button-primary"><span class="dashicons dashicons-yes"></span> Dodaj</button>
        </div>
      </form>
    </div>

    <div class="het-seo-card" style="margin-top:14px">
      <div class="het-seo-card__title"><span class="dashicons dashicons-upload"></span> Import pozycji (bezpieczny)</div>
      <div class="het-seo-card__sub">
        Wklej CSV/TSV/JSON. Format linii: <code>keyword;position;url;checked_at;engine;locale;device</code>.
        JSON: lista obiektów z tymi polami. <br/>
        Przykład: <code>heterodyna seo;4;https://twojadomena.pl/;2026-02-08 18:00:00;google;pl-PL;mobile</code>
      </div>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px">
        <?php wp_nonce_field('het_seo_rank_import'); ?>
        <input type="hidden" name="action" value="het_seo_rank_import" />
        <div style="display:flex;gap:10px;align-items:center;margin-bottom:8px">
          <label>Źródło:
            <select name="source">
              <option value="manual">manual</option>
              <option value="gsc">gsc</option>
              <option value="bing">bing</option>
              <option value="tool">tool</option>
            </select>
          </label>
        </div>
        <textarea name="import_text" rows="7" style="width:100%" placeholder="wklej dane..."></textarea>
        <div class="het-actions" style="margin-top:10px">
          <button class="button button-primary"><span class="dashicons dashicons-database-import"></span> Importuj</button>
        </div>
      </form>
    </div>

    <div class="het-seo-card" style="margin-top:14px">
      <div class="het-seo-card__title"><span class="dashicons dashicons-chart-line"></span> Twoje frazy (ostatni pomiar)</div>
      <div class="het-tablewrap" style="margin-top:10px">
        <table class="widefat striped">
          <thead>
            <tr>
              <th>Fraza</th>
              <th>Engine</th>
              <th>Locale</th>
              <th>Device</th>
              <th>Last pos.</th>
              <th>URL</th>
              <th>Checked</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($keywords)): ?>
              <tr><td colspan="7">Brak fraz. Dodaj lub zaimportuj.</td></tr>
            <?php else: foreach ($keywords as $kw): ?>
              <?php $last = $keywords_last[(int)$kw['id']] ?? null; ?>
              <tr>
                <td><strong><?php echo esc_html($kw['keyword']); ?></strong></td>
                <td><?php echo esc_html($kw['engine']); ?></td>
                <td><?php echo esc_html($kw['locale']); ?></td>
                <td><?php echo esc_html($kw['device']); ?></td>
                <td><?php echo $last ? esc_html($last['position']) : '—'; ?></td>
                <td><?php echo $last && !empty($last['url']) ? '<a target="_blank" rel="noopener" href="'.esc_url($last['url']).'">'.esc_html(wp_trim_words($last['url'], 8, '…')).'</a>' : '—'; ?></td>
                <td><?php echo $last ? esc_html($last['checked_at']) : '—'; ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php else: ?>
    <div style="margin-top:12px">Wybierz zakładkę.</div>
  <?php endif; ?>
</div>
