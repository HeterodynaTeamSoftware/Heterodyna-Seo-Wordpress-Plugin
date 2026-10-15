<?php if (!defined('ABSPATH')) { exit; }
$is_on = !empty($state['active']);
?>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-unlock"></span> Open Source</div>
  <div class="het-seo-card__sub">
    Ta wersja wtyczki jest przygotowana do publikacji jako open source. Nie wymaga klucza, nie aktywuje się na zewnętrznym serwerze i nie wysyła zdalnego raportowania statusu.
  </div>
</div>

<?php if (!empty($notice)) { echo $notice; } ?>

<div class="het-seo-card" style="margin-top:12px">
  <div class="het-grid2">
    <div>
      <div class="het-metric"><span>Status</span><strong><?php echo $is_on ? 'Aktywna ✅' : 'Nieaktywna ❌'; ?></strong></div>
      <div class="het-muted" style="margin-top:6px"><?php echo esc_html($state['message'] ?? ''); ?></div>
    </div>
    <div>
      <div class="het-metric"><span>Tryb</span><strong>Open Source</strong></div>
      <div class="het-muted" style="margin-top:6px">Wszystkie funkcje są dostępne lokalnie bez zdalnej walidacji komercyjnej.</div>
    </div>
  </div>
</div>

<div class="het-seo-card" style="margin-top:12px">
  <div class="het-seo-card__title" style="font-size:13px"><span class="dashicons dashicons-privacy"></span> Prywatność</div>
  <ul style="margin:8px 0 0 18px;list-style:disc">
    <li>Brak wbudowanego adresu serwera komercyjnej aktywacji.</li>
    <li>Brak aktywacji zdalnej.</li>
    <li>Brak zdalnego raportowania statusu.</li>
    <li>Stare lokalne stare dane komercyjne/tokeny można usunąć poniżej.</li>
  </ul>

  <form method="post" style="margin-top:12px">
    <?php wp_nonce_field('het_seo_open_source'); ?>
    <button type="submit" class="button" name="het_seo_clear_commercial_data" value="1" onclick="return confirm('Usunąć stare lokalne dane komercyjne i tokeny?');">Usuń stare stare dane komercyjne/tokeny</button>
  </form>
</div>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
