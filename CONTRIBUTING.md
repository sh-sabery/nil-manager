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
