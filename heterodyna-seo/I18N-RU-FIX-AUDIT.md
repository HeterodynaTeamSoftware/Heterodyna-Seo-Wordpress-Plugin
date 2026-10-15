# Heterodyna SEO — i18n / Russian mode fix audit

Version: 03.06.039-os-i18n-ru-safe

## Fixed

- Russian mode white/broken page risk fixed.
- Admin HTML translation no longer rewrites HTML tags, URLs, CSS classes, JavaScript or WordPress admin links.
- Translation now runs only on visible text nodes.
- Unsafe short-word replacements such as Polish `po` are filtered from HTML-buffer translation to avoid corrupting visible labels like `Post` or internal markup.
- Added an English-to-Russian bridge so labels already rendered in English can still be translated when Russian is selected.
- Added extra PL/EN/RU UI translations for Center dashboard, SEO Core, PSI, settings, technical SEO, audits, redirects, integrations and social media labels.
- Kept `Open Source` as a product/license label instead of translating it word-by-word.

## Validation

- PHP syntax check passed for all plugin PHP files using `php -l`.
- Translation smoke test passed for EN and RU sample admin HTML.
- Script/style chunks are skipped during translation.
