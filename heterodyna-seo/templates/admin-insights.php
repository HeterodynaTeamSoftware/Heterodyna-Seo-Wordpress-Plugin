<?php if (!defined('ABSPATH')) { exit; }

$u_scan = rest_url('heterodyna-seo/v1/insights/scan');
$u_conf = rest_url('heterodyna-seo/v1/insights/conflicts');
$u_sug = rest_url('heterodyna-seo/v1/insights/suggestions');

?>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-chart-bar"></span> Analiza konfliktów SEO &amp; decyzje indeksowania</div>
  <div class="het-seo-card__sub">Kanibalizacja, konflikty tematów oraz sugestie canonical/noindex. Wszystko w formie "pół-auto": najpierw propozycje, potem zatwierdzanie.</div>

  <div class="het-tabs" data-het-tabs>
    <button class="het-tab is-active" type="button" data-het-tab="conflicts"><span class="dashicons dashicons-warning"></span> Konflikty</button>
    <button class="het-tab" type="button" data-het-tab="suggestions"><span class="dashicons dashicons-yes"></span> Sugestie</button>
    <button class="het-tab" type="button" data-het-tab="exceptions"><span class="dashicons dashicons-shield"></span> Wyjątki</button>
    <button class="het-tab" type="button" data-het-tab="about"><span class="dashicons dashicons-info"></span> Jak to działa</button>
  </div>

  <div class="het-seo-card" style="margin-top:10px">
    <div class="het-actions" style="align-items:center;flex-wrap:wrap">
      <button class="button button-primary" data-het-insights-scan><span class="dashicons dashicons-update"></span> Skanuj teraz (topics → konflikty → sugestie)</button>
      <label class="het-inline">limit <input type="number" id="hetInsightsLimit" value="500" min="1" max="2000" style="width:90px"></label>
      <label class="het-inline"><input type="checkbox" id="hetInsightsIncludeTerms" checked> uwzględnij kategorie/tagi</label>
      <label class="het-inline">term limit <input type="number" id="hetInsightsTermLimit" value="300" min="0" max="2000" style="width:90px"></label>
      <span id="hetInsightsStatus" class="het-muted"></span>
    </div>
  </div>

  <div class="het-tabpanes">
    <div class="het-pane is-active" data-het-pane="conflicts">
      <div class="het-seo-card" style="margin-top:12px">
        <div class="het-seo-card__title">Kanibalizacja / konflikty tematu</div>
        <div class="het-actions" style="margin-top:10px;flex-wrap:wrap">
          <button class="button" data-het-conf-kind="cannibal">Post↔Post</button>
          <button class="button" data-het-conf-kind="term_vs_post">Term↔Post</button>
          <button class="button" data-het-conf-kind="term_cannibal">Term↔Term</button>
          <span class="het-muted" style="margin-left:8px">Filtr konfliktów</span>
        </div>
        <div class="het-muted">Wykrywa kilka URL-i o bardzo podobnym fingerprintcie tematu. To jest sygnał do: scalenia, zmiany targetu albo ustawienia canonical.</div>
        <div id="hetConflictsTable" class="het-tablewrap" style="margin-top:10px"></div>
      </div>
    </div>

    <div class="het-pane" data-het-pane="suggestions">
      <div class="het-seo-card" style="margin-top:12px">
        <div class="het-seo-card__title">Inbox sugestii (canonical/noindex)</div>
        <div class="het-muted">Klikasz "Zastosuj" — wtyczka ustawia pola w metaboxie (canonical / noindex). Nic nie dzieje się bez Twojej decyzji.</div>
        <div class="het-actions" style="margin-top:10px">
          <button class="button" data-het-insights-refresh><span class="dashicons dashicons-update"></span> Odśwież</button>
          <label class="het-inline"><input type="checkbox" id="hetSugOnlyApproval" checked> pokaż tylko "do zatwierdzenia"</label>
          <span class="het-sep" style="opacity:.4">|</span>
          <label class="het-inline">auto-apply ≥ <input type="number" id="hetSugMinConf" value="88" min="0" max="100" style="width:70px"></label>
          <label class="het-inline">limit <input type="number" id="hetSugApplyLimit" value="30" min="1" max="500" style="width:70px"></label>
          <button class="button button-primary" data-het-insights-apply-bulk><span class="dashicons dashicons-yes"></span> Zastosuj masowo</button>
        </div>
        <div id="hetSuggestionsTable" class="het-tablewrap" style="margin-top:10px"></div>
      </div>
    </div>


    <div class="het-pane" data-het-pane="exceptions">
      <div class="het-seo-card" style="margin-top:12px">
        <div class="het-seo-card__title">Wyjątki (whitelist)</div>
        <div class="het-muted">Posty/strony na tej liście są ignorowane przez skan: nie będą trafiały do konfliktów i nie dostaną sugestii noindex/canonical.</div>
        <div class="het-actions" style="margin-top:10px;flex-wrap:wrap">
          <label class="het-inline">ID posta <input type="number" id="hetExPostId" min="1" style="width:120px"></label>
          <button class="button" data-het-ex-add><span class="dashicons dashicons-plus"></span> Dodaj</button>
          <button class="button" data-het-ex-refresh><span class="dashicons dashicons-update"></span> Odśwież</button>
          <span id="hetExStatus" class="het-muted"></span>
        </div>
        <div id="hetExceptionsTable" class="het-tablewrap" style="margin-top:10px"></div>
      </div>
    </div>

    <div class="het-pane" data-het-pane="about">
      <div class="het-seo-card" style="margin-top:12px">
        <div class="het-seo-card__title">Co jest liczone?</div>
        <ol class="het-muted" style="margin:8px 0 0 18px">
          <li><strong>Fingerprint tematu</strong> — top tokeny z tytułu i treści.</li>
          <li><strong>Konflikt</strong> — gdy dwa lub więcej URL-i ma ten sam fingerprint.</li>
          <li><strong>Sugestie</strong> — dla konfliktu proponowany jest canonical na najsilniejszy URL (najczęściej najdłuższa treść).</li>
        </ol>
        <p class="het-muted" style="margin-top:10px">To jest wersja stabilna: deterministyczny skan + Inbox sugestii. Dodano: heurystykę thin-content (noindex jako sugestia), obniżanie confidence przy konflikcie intencji oraz whitelistę „Wyjątki” + możliwość ignorowania sugestii.</p>
      </div>
    </div>
  </div>

  <script>
    window.HET_SEO_INSIGHTS = {
      scan: <?php echo wp_json_encode(esc_url_raw($u_scan)); ?>,
      conflicts: <?php echo wp_json_encode(esc_url_raw($u_conf)); ?>,
      suggestions: <?php echo wp_json_encode(esc_url_raw($u_sug)); ?>,
      exclusions: <?php echo wp_json_encode(esc_url_raw(rest_url('heterodyna-seo/v1/insights/exclusions'))); ?>,
      merge_mark: <?php echo wp_json_encode(esc_url_raw(rest_url('heterodyna-seo/v1/insights/merge-mark'))); ?>,
      ignore: <?php echo wp_json_encode(esc_url_raw(rest_url('heterodyna-seo/v1/insights/ignore'))); ?>,
      apply_bulk: <?php echo wp_json_encode(esc_url_raw(rest_url('heterodyna-seo/v1/insights/apply-bulk'))); ?>
    };
  </script>

</div>

<div class="het-mini-grid het-insights-kpi" style="margin-top:12px">
  <div class="het-mini-tile">
    <div class="het-mini-tile__label">Konflikty</div>
    <div class="het-mini-tile__value"><span id="hetKpiConflicts">—</span></div>
    <div class="het-mini-tile__hint">Wszystkie wykryte konflikty (posty i termy)</div>
  </div>
  <div class="het-mini-tile">
    <div class="het-mini-tile__label">Sugestie (pending)</div>
    <div class="het-mini-tile__value"><span id="hetKpiSugPending">—</span></div>
    <div class="het-mini-tile__hint">Do zatwierdzenia / masowych akcji</div>
  </div>
</div>

