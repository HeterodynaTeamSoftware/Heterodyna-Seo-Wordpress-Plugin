# Heterodyna SEO — integrated i18n audit

Build: `03.06.038-os-i18n-integrated-ui-fix`

## What was integrated

- Added a code-level language layer in `includes/class-het-seo-i18n.php`.
- Added bundled translation dictionaries in `includes/i18n-extra.php`.
- Kept the WordPress-installable plugin root as `heterodyna-seo/` in the ZIP.
- Added/kept the admin option: `Heterodyna SEO → Ustawienia → Język wtyczki`.
- Supported languages: Polish, English, Russian.
- Extended JS translations for dynamic tables, confirmations, buttons and AJAX states.
- Kept the open-source state: no license key required, no commercial activation server, no remote license heartbeat/reporting.

## Integration checks performed

- PHP lint: all PHP files checked with `php -l`.
- Dictionary include check: `includes/i18n-extra.php` loads correctly.
- Runtime translation smoke test: selected English language and translated representative UI strings through `HET_SEO_I18N::t()`.
- ZIP structure check: top-level directory is `heterodyna-seo/`.
- Static scan for commercial telemetry/licensing endpoints.
- Static scan for remaining direct remote calls.

## Notes

Some Polish text intentionally remains in the source code because Polish is the base language and dictionary keys must stay available. In English/Russian mode the admin output buffer and JS label map translate these strings at runtime. Functional remote calls remain only for user-triggered features such as PageSpeed Insights, sitemap/URL import, X/Twitter and LinkedIn publishing; these are not hidden telemetry.
