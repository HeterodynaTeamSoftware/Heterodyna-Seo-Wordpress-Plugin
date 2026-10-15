<?php if (!defined('ABSPATH')) { exit; } ?>

<h1>Dokumentacja</h1>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-superhero"></span> AI Auto — jak używać bezpiecznie</div>
  <ul class="ul-disc">
    <li><strong>Dry-run</strong> — domyślny tryb. AI skanuje i buduje kolejkę, ale nic nie zapisuje.</li>
    <li><strong>Wykonaj</strong> — zapis zmian. Dostępne w buildzie open-source. Zawsze działa w trybie „safe”.</li>
    <li><strong>Whitelist</strong> — wybierasz, co AI wolno robić (np. brakujące meta / zbyt długie / duplikaty / CTR-style).</li>
    <li><strong>PANIC STOP</strong> — natychmiast zatrzymuje automaty. Użyj, jeśli cokolwiek wygląda podejrzanie.</li>
    <li><strong>Rollback</strong> — cofanie zmian per zadanie / batch (tam gdzie dostępne).</li>
  </ul>
</div>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-edit"></span> SEO w edytorze</div>
  <p>W edycji wpisu/strony masz metabox <strong>Heterodyna SEO</strong>:</p>
  <ul class="ul-disc">
    <li>Title / Description (z licznikiem i podglądem snippet)</li>
    <li>Canonical</li>
    <li>Noindex / Nofollow</li>
  </ul>
  <p class="het-muted">Jeśli pole jest puste — wtyczka użyje wzorca per typ treści.</p>
</div>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-chart-line"></span> Eksporty i raporty</div>
  <p>W zakładce <strong>Import/Export</strong> możesz eksportować ustawienia (JSON) oraz dane operacyjne (CSV): meta / 404 / przekierowania / prosty raport.</p>
</div>

<div class="het-seo-card">
  <div class="het-seo-card__title"><span class="dashicons dashicons-shield"></span> Zasady „safe mode”</div>
  <ul class="ul-disc">
    <li>AI nie edytuje treści wpisu (body) bez wyraźnego trybu i zgody.</li>
    <li>AI nie nadpisuje ręcznie ustawionych meta.</li>
    <li>Zmiany są logowane, a przy operacjach AI masz możliwość cofnięcia.</li>
  </ul>
</div>
