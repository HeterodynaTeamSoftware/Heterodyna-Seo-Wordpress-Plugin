<?php if (!defined('ABSPATH')) { exit; }
?>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-rest-api"></span> API</div>
  <div class="het-seo-card__sub">Podgląd endpointów REST (internal) i wskazówki integracji.</div>
</div>

<div class="het-seo-card" style="margin-top:12px">
  <div class="het-seo-card__title">REST base</div>
  <p><code><?php echo esc_html($rest_base ?? rest_url('heterodyna-seo/v1')); ?></code></p>
  <div class="het-muted het-small">
    Autoryzacja: standardowy WP REST nonce w panelu. W zewnętrznych integracjach użyj tokenów aplikacji albo osobnego serwisu — tu nie wchodzimy w „subskrypcje”.
  </div>
</div>

<div class="het-seo-card" style="margin-top:12px">
  <div class="het-seo-card__title">Endpointy</div>
  <ul class="het-list">
    <li><code>/ai/scan</code> — skan i budowa kolejki (dry-run lub execute)</li>
    <li><code>/ai/queue</code> — lista zadań + statusy</li>
    <li><code>/ai/run</code> — przetwórz porcję</li>
    <li><code>/rollback</code> — cofnięcie zmian dla taska</li>
  </ul>
  <div class="het-muted het-small">To jest minimalny zestaw do dalszego rozwoju. Jeśli chcesz, dorzucimy „/health” i pełny raport jak w starych paczkach.</div>
</div>

<?php include HET_SEO_PATH . 'templates/admin-footer.php'; ?>
