<?php if (!defined('ABSPATH')) { exit; }
$robots = $opts['robots_rules'] ?? '';
$debug = !empty($opts['debug']);
$pt_title_def = $opts['pattern_title_default'] ?? '';
$pt_desc_def  = $opts['pattern_desc_default'] ?? '';

// Social/Schema
$social_enabled = !empty($opts['social_enabled']);
$social_twitter_card = $opts['social_twitter_card'] ?? 'summary_large_image';
$social_default_image = $opts['social_default_image'] ?? '';

$schema_enabled = !empty($opts['schema_enabled']);
$schema_org_name = $opts['schema_org_name'] ?? '';
$schema_org_logo = $opts['schema_org_logo'] ?? '';
$schema_site_search = !empty($opts['schema_site_search']);

// MEGA PACK B: AI Style Profile + Brand Rules
$ai_profile = (class_exists('HET_SEO_AI_Style') && is_callable(['HET_SEO_AI_Style','get_profile'])) ? HET_SEO_AI_Style::get_profile() : ['enabled'=>1,'tone'=>'neutral','separator'=>'—','title_suffix'=>'','allow_emoji'=>0,'title_avg'=>48,'desc_avg'=>130];
$ai_style_enabled = !empty($ai_profile['enabled']);
$ai_tone = $ai_profile['tone'] ?? 'neutral';
$ai_sep = $ai_profile['separator'] ?? '—';
$ai_suffix = $ai_profile['title_suffix'] ?? '';
$ai_allow_emoji = !empty($ai_profile['allow_emoji']);

$ai_brand_must = $opts['ai_brand_must'] ?? '';
$ai_brand_never = $opts['ai_brand_never'] ?? '';
$ai_brand_syn = $opts['ai_brand_syn'] ?? '';

$public_types = get_post_types(['public'=>true], 'objects');
unset($public_types['attachment']);
?>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-admin-generic"></span> Ustawienia</div>
  <div class="het-seo-card__sub">Ustawienia ogólne (debug, robots, wzorce meta). Pozostałe funkcje są w Centrum → Uniwersalne.</div>
</div>

<form method="post" class="het-seo-card">
  <?php wp_nonce_field('het_seo_save_settings'); ?>

  <table class="form-table" role="presentation">
    <tr>
      <th scope="row">Język wtyczki</th>
      <td><?php HET_SEO_I18N::select_field(); ?></td>
    </tr>

    <tr>
      <th scope="row">Wygląd panelu</th>
      <td>
        <label style="display:inline-block;margin-right:14px"><input type="radio" name="het_ui_density" value="compact" <?php checked(($ui_density ?? 'compact'), 'compact'); ?>> <strong>Compact</strong> — mniej pionu, wszystko się mieści</label>
        <label style="display:inline-block"><input type="radio" name="het_ui_density" value="comfortable" <?php checked(($ui_density ?? 'compact'), 'comfortable'); ?>> <strong>Comfortable</strong> — więcej odstępów</label>
        <p class="description" style="margin-top:6px">Dotyczy całego panelu wtyczki (Centrum, Social Hub i pozostałe ekrany).</p>
      </td>
    </tr>
    <tr>
      <th scope="row">Debug log</th>
      <td>
        <label><input type="checkbox" name="debug" value="1" <?php checked($debug); ?>> Włącz logowanie do error_log</label>
      </td>
    </tr>
    <tr>
      <th scope="row">Robots rules (dopisek)</th>
      <td>
        <textarea name="robots_rules" rows="8" class="large-text code" placeholder="User-agent: *\nDisallow: /wp-admin/"><?php echo esc_textarea($robots); ?></textarea>
        <p class="description">Dopisuje reguły do wirtualnego robots.txt WordPressa (nie nadpisuje domyślnych).</p>
      </td>
    </tr>

    <tr>
      <th scope="row">Wzorce SEO (domyślne)</th>
      <td>
        <p class="description">Używane tylko jeśli wpis nie ma ustawionych własnych pól <code>het_seo_meta_title</code> / <code>het_seo_meta_description</code>.</p>
        <p><strong>Title</strong></p>
        <input type="text" class="regular-text" name="pattern_title_default" value="<?php echo esc_attr($pt_title_def); ?>" placeholder="{title} — {site}">
        <p class="description">Zmienne: <code>{title}</code>, <code>{site}</code>, <code>{sep}</code>, <code>{date}</code>.</p>
        <p style="margin-top:10px"><strong>Description</strong></p>
        <input type="text" class="large-text" name="pattern_desc_default" value="<?php echo esc_attr($pt_desc_def); ?>" placeholder="{excerpt}">
        <p class="description">Zmienne: <code>{excerpt}</code>, <code>{title}</code>, <code>{site}</code>, <code>{date}</code>.</p>
      </td>
    </tr>

    <tr>
      <th scope="row">Wzorce SEO (per typ treści)</th>
      <td>
        <div style="display:grid;grid-template-columns:1fr;gap:12px;max-width:920px">
          <?php foreach ($public_types as $pt => $obj):
            $k1 = 'pattern_title_' . $pt;
            $k2 = 'pattern_desc_' . $pt;
            $v1 = $opts[$k1] ?? '';
            $v2 = $opts[$k2] ?? '';
          ?>
            <div style="border:1px solid var(--het-border);border-radius:14px;padding:12px;background:var(--het-card)">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                <span class="dashicons dashicons-media-text"></span>
                <strong><?php echo esc_html($obj->labels->singular_name); ?></strong>
                <span style="opacity:.7"><?php echo esc_html('(' . $pt . ')'); ?></span>
              </div>
              <div style="display:grid;grid-template-columns:1fr;gap:8px">
                <input type="text" class="large-text" name="<?php echo esc_attr($k1); ?>" value="<?php echo esc_attr($v1); ?>" placeholder="Title: (puste = domyślny)">
                <input type="text" class="large-text" name="<?php echo esc_attr($k2); ?>" value="<?php echo esc_attr($v2); ?>" placeholder="Description: (puste = domyślny)">
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </td>
    </tr>

    <tr>
      <th scope="row">Social (Open Graph / Twitter)</th>
      <td>
        <label style="display:inline-flex;align-items:center;gap:8px">
          <input type="checkbox" name="social_enabled" value="1" <?php checked($social_enabled); ?>> Włącz meta OG/Twitter
        </label>
        <div style="margin-top:10px;display:grid;grid-template-columns:1fr;gap:10px;max-width:920px">
          <div>
            <label><strong>Twitter card</strong></label>
            <select name="social_twitter_card">
              <option value="summary" <?php selected($social_twitter_card, 'summary'); ?>>summary</option>
              <option value="summary_large_image" <?php selected($social_twitter_card, 'summary_large_image'); ?>>summary_large_image</option>
            </select>
            <p class="description">Jeśli brak obrazka, automatycznie spadnie do <code>summary</code>.</p>
          </div>
          <div>
            <label><strong>Domyślny obraz OG</strong></label>
            <input type="url" class="large-text" name="social_default_image" value="<?php echo esc_attr($social_default_image); ?>" placeholder="https://.../og.jpg">
            <p class="description">Jeśli wpis ma obraz wyróżniający — użyjemy go. W innym wypadku weźmiemy ten URL.</p>
          </div>
        </div>
      </td>
    </tr>

    <tr>
      <th scope="row">Schema (JSON-LD)</th>
      <td>
        <label style="display:inline-flex;align-items:center;gap:8px">
          <input type="checkbox" name="schema_enabled" value="1" <?php checked($schema_enabled); ?>> Włącz JSON-LD (Organization, Website, Article/WebPage, Breadcrumbs)
        </label>
        <div style="margin-top:10px;display:grid;grid-template-columns:1fr;gap:10px;max-width:920px">
          <div>
            <label><strong>Nazwa organizacji</strong></label>
            <input type="text" class="regular-text" name="schema_org_name" value="<?php echo esc_attr($schema_org_name); ?>" placeholder="(puste = nazwa witryny)">
          </div>
          <div>
            <label><strong>Logo organizacji (URL)</strong></label>
            <input type="url" class="large-text" name="schema_org_logo" value="<?php echo esc_attr($schema_org_logo); ?>" placeholder="https://.../logo.png">
          </div>
          <div>
            <label style="display:inline-flex;align-items:center;gap:8px">
              <input type="checkbox" name="schema_site_search" value="1" <?php checked($schema_site_search); ?>> Dodaj SearchAction (pole wyszukiwania w wynikach)
            </label>
          </div>
        </div>
      </td>
    </tr>

    <tr>
      <th scope="row">AI Style Profile (MEGA PACK B)</th>
      <td>
        <label style="display:inline-flex;align-items:center;gap:8px">
          <input type="checkbox" name="ai_style_enabled" value="1" <?php checked($ai_style_enabled); ?>> Włącz profil stylu dla AI (spójne meta)
        </label>
        <div style="margin-top:10px;display:grid;grid-template-columns:1fr;gap:10px;max-width:920px">
          <div>
            <label><strong>Ton</strong></label>
            <select name="ai_tone">
              <option value="neutral" <?php selected($ai_tone, 'neutral'); ?>>neutral</option>
              <option value="info" <?php selected($ai_tone, 'info'); ?>>info (poradnik)</option>
              <option value="sales" <?php selected($ai_tone, 'sales'); ?>>sales (delikatne CTA)</option>
            </select>
          </div>
          <div>
            <label><strong>Separator w tytule</strong></label>
            <select name="ai_separator">
              <option value="—" <?php selected($ai_sep, '—'); ?>>—</option>
              <option value="-" <?php selected($ai_sep, '-'); ?>>-</option>
              <option value="|" <?php selected($ai_sep, '|'); ?>>|</option>
              <option value="·" <?php selected($ai_sep, '·'); ?>>·</option>
              <option value="/" <?php selected($ai_sep, '/'); ?>>/</option>
            </select>
          </div>
          <div>
            <label><strong>Sufiks tytułu (opcjonalny)</strong></label>
            <input type="text" class="regular-text" name="ai_title_suffix" value="<?php echo esc_attr($ai_suffix); ?>" placeholder="np. Heterodyna">
            <p class="description">Jeśli ustawisz, AI dopisze sufiks do Title, np. <code>Tytuł — Heterodyna</code>.</p>
          </div>
          <div>
            <label style="display:inline-flex;align-items:center;gap:8px">
              <input type="checkbox" name="ai_allow_emoji" value="1" <?php checked($ai_allow_emoji); ?>> Pozwól na emoji w Title/Description
            </label>
            <p class="description">Domyślnie emoji są usuwane (bezpiecznie).</p>
          </div>

          <div class="het-auto-profile-card" style="border:1px solid var(--het-border);border-radius:14px;padding:12px;background:var(--het-card)">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
              <div>
                <strong>Profil automatyczny (statystyki)</strong>
                <div style="opacity:.8">Średnia długość: Title ~ <?php echo intval($ai_profile['title_avg'] ?? 0); ?>, Description ~ <?php echo intval($ai_profile['desc_avg'] ?? 0); ?>. Separator: <code><?php echo esc_html($ai_sep); ?></code></div>
              </div>
              <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=het_seo_rebuild_style_profile'), 'het_seo_rebuild_style_profile')); ?>">Przelicz profil teraz</a>
            </div>
            <p class="description" style="margin-top:8px">Przelicza profil na podstawie istniejących meta (Title/Description) w Twoich treściach.</p>
          </div>
        </div>
      </td>
    </tr>

    <tr>
      <th scope="row">Brand Rules (dla AI)</th>
      <td>
        <div style="display:grid;grid-template-columns:1fr;gap:10px;max-width:920px">
          <div>
            <label><strong>Must include (każda linia to fraza)</strong></label>
            <textarea name="ai_brand_must" rows="4" class="large-text code" placeholder="np. Heterodyna\nSaaS SEO"><?php echo esc_textarea($ai_brand_must); ?></textarea>
            <p class="description">Jeśli frazy nie ma w Description, AI dopisze ją na końcu (z limitem długości).</p>
          </div>
          <div>
            <label><strong>Never use (zakazane słowa)</strong></label>
            <textarea name="ai_brand_never" rows="4" class="large-text code" placeholder="np. tanio\nkliknij tu"><?php echo esc_textarea($ai_brand_never); ?></textarea>
            <p class="description">Zakazane frazy są usuwane z Title/Description.</p>
          </div>
          <div>
            <label><strong>Preferred synonyms (mapowanie)</strong></label>
            <textarea name="ai_brand_syn" rows="4" class="large-text code" placeholder="np. ogłoszenia => oferty\nportal=serwis"><?php echo esc_textarea($ai_brand_syn); ?></textarea>
            <p class="description">Format: <code>stare => nowe</code> lub <code>stare=nowe</code>. Zamiana jest niewrażliwa na wielkość liter.</p>
          </div>
        </div>
      </td>
    </tr>
  </table>

  <p>
    <button type="submit" class="button button-primary" name="het_seo_save_settings" value="1">Zapisz</button>
  </p>
</form>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
