<?php if (!defined('ABSPATH')) { exit; }

$export_url = wp_nonce_url(add_query_arg([
    'page' => HET_SEO_Admin::MENU_SLUG_AI_AUTO,
    'het_seo_ai_auto_export' => '1',
], admin_url('admin.php')), 'het_seo_ai_auto_export');

?>

<?php
$approve_all_url = wp_nonce_url(add_query_arg([
  'page' => HET_SEO_Admin::MENU_SLUG_AI_AUTO,
  'het_seo_ai_approve_all' => '1',
], admin_url('admin.php')), 'het_seo_ai_approve_all');
?>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-superhero"></span> AI Auto</div>
  <div class="het-seo-card__sub">Automatyczne, bezpieczne poprawki SEO w tle. Tryb <strong>dry-run</strong> działa zawsze; tryb wykonania jest dostępny w buildzie open-source.</div>
</div>

<div class="het-grid2" style="margin-top:12px">

  <div class="het-seo-card">
    <div class="het-seo-card__title">Ustawienia</div>
    <form method="post">
      <?php wp_nonce_field('het_seo_save_ai_auto'); ?>
      <table class="form-table" role="presentation">
        <tr>
          <th scope="row">Włącz AI Auto</th>
          <td>
            <label><input type="checkbox" name="enabled" value="1" <?php checked(!empty($s['enabled'])); ?>> aktywne</label>
            <p class="description">Gdy wyłączone — cron się uruchamia, ale nic nie robi (bezpiecznie).</p>
          </td>
        </tr>
        <tr>
          <th scope="row">PANIC STOP</th>
          <td>
            <label><input type="checkbox" name="panic_stop" value="1" <?php checked(!empty($s['panic_stop'])); ?>> stop natychmiastowy</label>
            <p class="description">Twarde wyłączenie automatyki. Przydatne gdy chcesz zachować spokój podczas zmian na stronie.</p>
          </td>
        </tr>
        
        <tr>
          <th scope="row">Autonomia</th>
          <td>
            <label><input type="radio" name="autonomy" value="manual" <?php checked($s['autonomy'] ?? 'manual', 'manual'); ?>> manual (tylko ręcznie)</label><br>
            <label><input type="radio" name="autonomy" value="semi" <?php checked($s['autonomy'] ?? 'manual', 'semi'); ?>> semi-auto (kolejka do zatwierdzenia)</label><br>
            <label><input type="radio" name="autonomy" value="auto" <?php checked($s['autonomy'] ?? 'manual', 'auto'); ?>> auto (cron skanuje + przetwarza)</label>
            <p class="description">Semi-auto tworzy zadania wymagające akceptacji (bez zapisu). Auto skanuje i przetwarza batch w tle.</p>
          </td>
        </tr>

<tr>
          <th scope="row">Tryb</th>
          <td>
            <label><input type="radio" name="mode" value="dry" <?php checked($s['mode'], 'dry'); ?>> dry-run (bez zapisu)</label><br>
            <label><input type="radio" name="mode" value="exec" <?php checked($s['mode'], 'exec'); ?>> wykonaj (zapis zmian)</label>
          </td>
        </tr>
        <tr>
          <th scope="row">Godzina uruchomienia (codziennie)</th>
          <td>
            <input type="number" name="daily_hour" min="0" max="23" value="<?php echo esc_attr($s['daily_hour']); ?>" style="width:80px"> :00
            <p class="description">Serwerowa strefa czasu WP.</p>
          </td>
        </tr>
        <tr>
          <th scope="row">Limit skanu</th>
          <td>
            <input type="number" name="limit" min="10" max="2000" value="<?php echo esc_attr($s['limit']); ?>" style="width:110px">
            <p class="description">Ile wpisów maksymalnie przeskanować dziennie.</p>
          </td>
        </tr>
        <tr>
          <th scope="row">Typy treści</th>
          <td>
            <div style="display:flex;flex-wrap:wrap;gap:12px">
              <?php foreach ($post_types as $pt => $obj): ?>
                <label>
                  <input type="checkbox" name="post_types[]" value="<?php echo esc_attr($pt); ?>" <?php checked(in_array($pt, (array)$s['post_types'], true)); ?>>
                  <?php echo esc_html($obj->labels->singular_name); ?>
                </label>
              <?php endforeach; ?>
            </div>
          </td>
        </tr>
        <tr>
          <th scope="row">Whitelist napraw (safe)</th>
          <td>
            <label><input type="checkbox" name="allowed_fixes[]" value="missing_meta" <?php checked(in_array('missing_meta', (array)$s['allowed_fixes'], true)); ?>> brakujące meta</label><br>
            <label><input type="checkbox" name="allowed_fixes[]" value="duplicates" <?php checked(in_array('duplicates', (array)$s['allowed_fixes'], true)); ?>> duplikaty (canonical)</label><br>
            <label><input type="checkbox" name="allowed_fixes[]" value="too_long" <?php checked(in_array('too_long', (array)$s['allowed_fixes'], true)); ?>> zbyt długie meta</label><br>
            <label><input type="checkbox" name="allowed_fixes[]" value="ctr_style" <?php checked(in_array('ctr_style', (array)$s['allowed_fixes'], true)); ?>> CTR-style</label>
            <p class="description">Bezpieczeństwo: to są tylko działania na meta/canonical. Treści wpisów nie ruszamy.</p>
          </td>
        </tr>
        <tr>
          <th scope="row">CTR-style</th>
          <td>
            <label><input type="checkbox" name="include_ctr" value="1" <?php checked(!empty($s['include_ctr'])); ?>> uwzględnij CTR-style (ostatni priorytet)</label>
          </td>
        </tr>
      </table>
      <p>
        <button class="button button-primary" name="het_seo_save_ai_auto" value="1">Zapisz</button>
      </p>
    </form>
  </div>

  
  <div class="het-seo-card" style="margin-bottom:12px">
    <div class="het-seo-card__title">Zadania do zatwierdzenia (semi-auto)</div>
    <p class="het-muted">Widoczne tylko, gdy autonomia jest ustawiona na <strong>semi-auto</strong>. Zatwierdzone zadania przechodzą do kolejki <em>pending</em>.</p>
    <p><a class="button" href="<?php echo esc_url($approve_all_url); ?>">Zatwierdź wszystkie</a></p>
    <div class="het-tablewrap">
      <table class="widefat striped">
        <thead>
          <tr>
            <th>ID</th>
            <th>Fix</th>
            <th>Impact</th>
            <th>Priorytet</th>
            <th>URL</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($approvals)): ?>
            <tr><td colspan="5" class="het-muted">Brak zadań oczekujących na zatwierdzenie.</td></tr>
          <?php else: ?>
            <?php foreach ($approvals as $a): ?>
              <tr>
                <td><?php echo esc_html($a->id); ?></td>
                <td><?php echo esc_html($a->fix_type); ?></td>
                <td><?php echo esc_html($a->impact_score); ?></td>
                <td><?php echo esc_html($a->priority); ?></td>
                <td><a href="<?php echo esc_url($a->url); ?>" target="_blank" rel="noreferrer"><?php echo esc_html(wp_parse_url($a->url, PHP_URL_PATH)); ?></a></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<div class="het-seo-card">
    <div class="het-seo-card__title">Raporty</div>
    <p class="het-muted">Ostatnie uruchomienia automatyki (cron). Możesz wyeksportować CSV.</p>
    <p><a class="button" href="<?php echo esc_url($export_url); ?>">Export CSV</a></p>

    <div class="het-tablewrap">
      <table class="widefat striped">
        <thead>
          <tr>
            <th>ID</th>
            <th>Typ</th>
            <th>Tryb</th>
            <th>Enqueued</th>
            <th>Processed</th>
            <th>Błędy</th>
            <th>Start</th>
            <th>Koniec</th>
            <th>Notatka</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($runs)): ?>
            <tr><td colspan="9" class="het-muted">Brak uruchomień. (Cron wykona się po pierwszym dniu lub po wejściu w WP, jeśli CRON jest pseudo-cron.)</td></tr>
          <?php else: ?>
            <?php foreach ($runs as $r): ?>
              <tr>
                <td><?php echo esc_html($r->id); ?></td>
                <td><?php echo esc_html($r->run_type); ?></td>
                <td><?php echo esc_html($r->mode); ?></td>
                <td><?php echo esc_html($r->enqueued); ?></td>
                <td><?php echo esc_html($r->processed); ?></td>
                <td><?php echo esc_html($r->errors); ?></td>
                <td><?php echo esc_html($r->started_at); ?></td>
                <td><?php echo esc_html($r->finished_at); ?></td>
                <td><?php echo esc_html($r->note); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
