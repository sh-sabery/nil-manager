# Development and releases

The installable plugin is `nilwears-bulk-manager/`. Development tools and tests must stay outside that directory. Do not commit site credentials, customer data, database dumps, `node_modules`, or generated ZIPs.

Run `npm ci`, `npm run check:js`, `npm run test:ui`, and `php tests/pricing.php`. PHP lint must pass for all PHP files. Build with `python3 scripts/build.py`; it verifies VERSION, the plugin header and constant, npm manifest and lock, and the matching CHANGELOG section. Python's standard library is sufficient.

`tests/ui-dom.cjs` tests real admin JavaScript against a DOM and mocked HTTP. It does not verify browser rendering or database persistence. `tests/pricing.php` is independent of WordPress. `tests/integration.php` creates fixture products and exercises real WooCommerce CRUD and REST endpoints in a disposable WordPress Playground installation. Mount this repository's tests directory as `/nil-tests`, install/activate WooCommerce and this plugin, then use a Playground `runPHP` step to require `/wordpress/wp-load.php` and `/nil-tests/integration.php`. The script intentionally emulates MySQL lock statements on SQLite. It must never run on a production store and does not verify MySQL checkout concurrency. Check `/nil-tests/integration-result.txt` for the result. The initial run passed 50 checks on WP 7.1.3 / WC 11.2.1 with PHP 8.3; those versions are test evidence, not installation requirements.

For each released update:

1. Update code, tests, and user documentation as appropriate.
2. Choose a new semantic version; never reuse a released tag.
3. Change `VERSION`, the plugin's `Version:` header and `NIL_Bulk_Manager::VERSION`, and `package.json`; regenerate `package-lock.json` with `npm install --package-lock-only`.
4. Add `## [X.Y.Z] - YYYY-MM-DD` and factual release notes to CHANGELOG.md.
5. Run tests and build the ZIP. Inspect the diff and ensure the package only contains plugin files.
6. Commit and push the change to `main`. The release workflow reruns checks, creates a `vX.Y.Z` tag for a new VERSION, and publishes a release with the installation ZIP and exact changelog entry. A manually pushed version tag can trigger the same workflow. Existing releases are skipped without changing their tags or assets.
7. Check the Actions result and release asset. Report the commit, tag, release link, and any unverified behavior to the user. Site installation is a separate action.

GitHub Actions may need to be enabled for the private repository and use available private-repository Actions minutes. The release job requests only `contents: write`; tests use `contents: read`. No external deployment credentials or automatic WordPress installation are configured.


## Version 1.1 validation

- PHP lint for every plugin and test PHP file, plus 1,532 pricing checks, 14 XLSX/CSV checks (including literal formulas, numeric report cells and leading-zero SKUs), and 10 private-updater HTTP mock checks. Local standalone PHP runtime: PHP.wasm 8.5.
- Real admin JavaScript tested in LinkeDOM: table/variation selection, preview/apply/undo, stock inheritance, retaining edits made after preview, quick-create attribute parsing, file column mapping, async button enablement, branding and updater status. This is DOM interaction verification, not a visual browser rendering test.
- Disposable WordPress Playground, PHP 8.3, WP 7.1.3 / WooCommerce 11.2.1: legacy order storage passed 138 combined CRUD/REST assertions; HPOS enabled passed 140 assertions, including matching generated variations through WooCommerce’s storefront variation matcher. Scenarios include array-field undo, cost pricing, variable creation/idempotency, spreadsheet roundtrips and conflicts, scheduled jobs, historical order cost/refunds, unknown historical costs, mixed currencies, actual image editing/renaming and archive/restore.
- No production-store installation or real MySQL checkout-concurrency test. SQLite shims emulate MySQL locks only. The updater's authorization, redirection and digest checks use fake credentials and mocked HTTP; private-token configuration and a real dashboard upgrade on the Nilwears server remain installation tasks.

To reproduce in a **disposable** Playground, use `tests/playground-hpos.json` or `tests/playground-legacy.json`. Mount the plugin directory at `/wordpress/wp-content/plugins/nilwears-bulk-manager` and the tests directory at `/nil-tests`, then run that Blueprint with `@wp-playground/cli@3.1.60`. The Blueprint downloads and activates WooCommerce. For offline reproducibility, mount an extracted WooCommerce 11.2.1 directory at `/wordpress/wp-content/plugins/woocommerce` and replace the first `installPlugin` step with `activatePlugin`. `tests/suite-result.txt` contains the assertion result and is excluded from git. Test images, orders, products and customer email addresses are generated fixtures only. Never mount a live store or production database.


## Version 1.2 performance verification

`npm run build:assets` creates the shipped minified assets; `npm run check:assets` rejects stale outputs or CSS/JS larger than 60,000 bytes. CI runs both `npm run test:ui` and `npm run test:ui:min`. Runtime WordPress does not require Node or any npm package. `scripts/build.py` excludes the unminified development assets from ZIPs.

The disposable Blueprints now also run `tests/frontend.php` before fixtures and `tests/performance.php` after the functional suite. PHP 8.3 / WP 7.1.3 / WooCommerce 11.2.1 with HPOS enabled and disabled each passed 156 combined assertions: 17 cold-cache SQL queries for a product with 2 variations and the same 17 for 40 variations; the initial unoptimized 40-variation probe issued 97. These are fixture-specific whole-endpoint counts, not a universal promise for every third-party plugin configuration. Frontend boot added zero SQL and no public assets; spreadsheet/media/maintenance modules stayed unloaded until used. Explicit media scanning, cache replacement and cleanup age/state/ownership checks passed. Core functional tests, 1,532 pricing checks, 14 spreadsheet checks and 10 updater HTTP checks remain covered. Production and source DOM tests pass, including tab navigation. Visual rendering of the actual Nilwears admin and live GTmetrix were not measured.

Temporary cache slots are single-active per tool/user. History cleanup is explicit and bounded. No extra periodic cleanup, media or analytics job is registered. Required historical cost and undo data still need storage; this is not a zero-database-footprint claim. See PERFORMANCE.md for the contract and source references for current stable release targets.


## Version 1.3 validation

`tests/advanced.php` now follows the performance suite in both disposable Blueprints. PHP 8.3 / WordPress 7.1.3 / WooCommerce 11.2.1 passed 209 cumulative assertions in each HPOS-enabled and legacy-order fixture. This includes new custom/global variation combinations, preserving old children, storefront attribute matching, duplicate SKUs, late-SKU collision rollback, stale-parent rejection, file-created variations, personal views/rules and anonymous denial. Native COGS checks cover inherited/additive costs, absolute and zero overrides, clearing to inheritance, exact undo, migration conflicts, explicit migration, backend-switch conflicts, native order capture and profit-report cost, without a parallel Nil order snapshot. Cold table requests with native COGS enabled measured 17 queries for 2 variations and 17 for 40, matching the disabled-state measurements.

Source and production DOM suites pass saved-column persistence, layout isolation to the product grid and preview/apply for additional variations. PHP.wasm 8.5 linted 19 plugin/test PHP files and passed the 1,532 pricing, 14 spreadsheet and 10 updater checks. Total shipped admin CSS/JS is 59,870 bytes (20,819 with deterministic gzip), below the unchanged 60,000-byte limit. The updater unit test deliberately uses a fake older version and mocked releases; it does not configure production credentials.

One initial legacy run stopped on the existing unused-image candidate assertion: the conservative reference scanner matched another fixture metadata record. The identical assertion passed on rerun after diagnostics were expanded to identify the matching record on future failures. No relaxation of production image-reference protections was made. This fixture can conservatively report a candidate as referenced; do not use that test result as proof that all real unused images can be identified.

These suites use SQLite and explicit MySQL lock shims. They do not establish real MySQL concurrency behavior, third-party theme/plugin compatibility, browser visual rendering, live GTmetrix scores, or a real WordPress private-token upgrade. New variation creation has a separate confirmation and no price-job undo; existing variation rows remain untouched. Cost migration is preview-only until the normal price job is confirmed.


## Version 1.4 validation

Run both updater modes: `php tests/updater.php` and `NIL_PUBLIC_UPDATES=1 php tests/updater.php`. The ten checks in each mode cover authenticated/anonymous metadata and download, expected package identity, redirected credential removal, allowed asset hosts, byte count, SHA-256 tampering and other-plugin isolation. CI and release workflows run both. This is mocked HTTP evidence, not a live WordPress dashboard upgrade.

The source and production DOM suites now load the actual static admin/help PHP templates (with the PHP guard stripped), then exercise the pricing workspace, help recipes without writes, live sample calculations, malformed rounding inputs, immediate branding, unsaved-preview isolation, failed-save preservation, logo replacement/clearing, startup branding in a fresh document and shop-manager disabled controls after asynchronous loading. The initial async cleanup no longer enables newly rendered intentionally disabled controls. The saved-view select setter clears options before selecting the intended one, avoiding stale selections.

PHP.wasm 8.5 linted 21 plugin/test PHP files. The 1,532 price, 14 spreadsheet, 10 private-updater and 10 public-updater checks passed. PHP 8.3 / WordPress 7.1.3 / WooCommerce 11.2.1 disposable HPOS and legacy suites each passed 218 cumulative assertions. New checks verify sanitized/persisted branding, failed HTTP-logo saves preserving stored values, three-digit hex normalization, manager restrictions, anonymous template denial and zero SQL while rendering admin/help templates. Performance fixtures retained 17 cold table queries for 2 and 40 variations in legacy/native COGS modes, zero plugin boot SQL and zero public assets.

`python3 scripts/preview.py` writes an ignored self-contained mock interface and a 390px iframe harness to `dist/`; the production plugin does not ship either. Cloud Browser rejected the local file protocol when attempting visual review, so no visual desktop/mobile browser result is claimed. Do not confuse LinkeDOM interaction tests or the preview generator with browser visual verification. Real Nilwears theme/plugin compatibility, MySQL concurrency, storefront scores and installation remain outside these disposable fixtures.
