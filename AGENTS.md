# نگهداری افزونهٔ نیل منجر

- The user explicitly made this repository public on 2026-10-22. Preserve its current visibility; never change visibility or add collaborators unless the user explicitly asks.
- Prefer the GitHub integration for GitHub tasks. The user authorizes Cloud Browser fallback when the integration cannot perform an operation.
- Treat this repository as the canonical source for future user-requested updates to the افزونهٔ ووکامرسی نیل منجر.
- For each requested implementation update, increase the semantic version, update CHANGELOG.md with the actual changes and validation, run the relevant checks, and commit and push the result with a new version tag, unless the user explicitly asks for review-only work. Follow CONTRIBUTING.md.
- Preserve previous commits and tags; never force-push or rewrite a released version.
- The plugin directory is the only content included in installable ZIPs. Keep tests, tooling, credentials, and generated outputs outside it.
- Do not deploy to the live Nilwears store as part of a repository update unless the user asks for installation/deployment.
- Never run tests/integration.php on a real store: it creates fixture products and contains SQLite-only lock emulation.
- Report failed or unrun checks accurately. Tests using SQLite do not prove MySQL concurrency safety.
