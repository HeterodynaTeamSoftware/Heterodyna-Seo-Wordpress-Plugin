# Heterodyna SEO — i18n audit

Build: `03.06.036-os-i18n-audit`

## Scope

This build extends the open-source i18n layer for:

- Polish (`pl`)
- English (`en`)
- Russian (`ru`)

The translation layer is local/static and does not use any external translation service.

## What changed

- Expanded English and Russian dictionaries with additional admin-panel labels, notices, buttons, settings, descriptions and JS confirmation messages.
- Added extra reusable phrase mappings to cover longer strings and mixed UI fragments.
- Kept Polish as the default language.
- Kept the open-source license/telemetry-disabled state from the previous build.

## Checks performed

- PHP syntax check for all plugin PHP files.
- Static scan for potentially untranslated Polish UI strings.
- Static scan for commercial activation / telemetry leftovers.

## Notes

- Compatibility stubs related to the previous commercial license class remain, but no remote activation endpoint, remote heartbeat registration, or license validation request is used.
- Functional third-party integrations such as PSI, X/Twitter, LinkedIn, sitemap import and URL imports remain unchanged because they are user-triggered features, not hidden telemetry.
