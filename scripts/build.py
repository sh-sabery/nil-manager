#!/usr/bin/env python3
"""Validate release metadata and build a deterministic WooCommerce plugin ZIP."""
import argparse
import hashlib
import json
from pathlib import Path
import re
import zipfile

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--tag', help='Require the release tag to match vX.Y.Z')
parser.add_argument('--channel', choices=('github', 'zhaket-review'), default='github', help='Marketplace output is a review candidate, never a licensed sale build')
parser.add_argument('--product-token-file', type=Path, help='File containing only the distributable Zhaket product ID; never a seller/API private key')
parser.add_argument('--zhaket-updater-file', type=Path, help='Unmodified official updater ZIP, review channel only')
parser.add_argument('--guard-test-report', type=Path, help='Local redacted report from scripts/guard-live.php for this version/product')
args = parser.parse_args()
product_token = ''
if args.product_token_file:
    if args.channel != 'zhaket-review':
        raise SystemExit('Product identifier is supported only in the marketplace review build')
    product_token = args.product_token_file.read_text().strip()
    if not re.fullmatch(r'[A-Za-z0-9_-]{8,512}', product_token):
        raise SystemExit('Invalid product identifier format')
addon = None
if args.zhaket_updater_file:
    if args.channel != 'zhaket-review':
        raise SystemExit('Updater add-on is supported only in the marketplace review build')
    addon = json.loads((ROOT / 'docs/zhaket-updater.json').read_text())
    data = args.zhaket_updater_file.read_bytes()
    if len(data) != addon['size'] or hashlib.sha256(data).hexdigest() != addon['sha256']:
        raise SystemExit('Official updater size/SHA256 does not match verified metadata')
    with zipfile.ZipFile(args.zhaket_updater_file) as z:
        if z.testzip() or addon['entrypoint'] not in z.namelist():
            raise SystemExit('Invalid official updater ZIP')
version = (ROOT / 'VERSION').read_text().strip()
live = None
if args.guard_test_report:
    if args.channel != 'zhaket-review' or not product_token:
        raise SystemExit('A Guard report requires a configured review build')
    live = json.loads(args.guard_test_report.read_text())
    if live.get('plugin_version') != version or live.get('product_sha256') != hashlib.sha256(product_token.encode()).hexdigest() or live.get('activation') is not True or live.get('validation') is not True or live.get('scope') != 'adapter-real-https-with-isolated-option-stubs' or live.get('domain') != 'nil-manager-guard.test' or live.get('wordpress_bootstrapped') is not False or not re.fullmatch(r'\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00', live.get('tested_at', '')):
        raise SystemExit('Guard report does not match this version/product and successful local smoke test')
if not re.fullmatch(r'\d+\.\d+\.\d+', version):
    raise SystemExit('VERSION must be X.Y.Z')
source = (ROOT / 'nil-manager/nil-manager.php').read_text()
header = re.search(r'^ \* Version: (.+)$', source, re.M)
constant = re.search(r"const VERSION = '([^']+)'", source)
package = json.loads((ROOT / 'package.json').read_text())
lock = json.loads((ROOT / 'package-lock.json').read_text())
versions = [header.group(1) if header else None, constant.group(1) if constant else None,
            package.get('version'), lock.get('version'), lock.get('packages', {}).get('', {}).get('version')]
if any(v != version for v in versions):
    raise SystemExit(f'Version mismatch: expected {version}, found {versions}')
if args.tag and args.tag != 'v' + version:
    raise SystemExit(f'Tag must be v{version}')
changelog = (ROOT / 'CHANGELOG.md').read_text()
section = re.search(r'^## \[' + re.escape(version) + r'\] - \d{4}-\d{2}-\d{2}\n(.*?)(?=^## \[|\Z)', changelog, re.M | re.S)
if not section or not section.group(1).strip():
    raise SystemExit('Matching nonempty CHANGELOG release section is required')
description = ROOT / 'nil-manager/changelog-fa.html'
expected_hash = hashlib.sha256(changelog.encode()).hexdigest()
if not description.exists() or not description.read_text().startswith('<!-- source-sha256: ' + expected_hash + ' -->'):
    raise SystemExit('Persian release descriptions are stale; run npm run build:descriptions')
out = ROOT / 'dist'
if args.channel == 'zhaket-review':
    out = out / 'zhaket-review'
out.mkdir(parents=True, exist_ok=True)
suffix = '-zhaket-review' if args.channel == 'zhaket-review' else ''
archive = out / f'nil-manager-v{version}{suffix}.zip'
channel_file = ROOT / 'nil-manager/includes-channel.php'
if channel_file.read_text() != "<?php\n/** Distribution identity, generated only by the release builder. */\ndefined( 'ABSPATH' ) || exit;\nreturn 'github';\n":
    raise SystemExit('Source channel must remain github; build transforms the review copy only')
product_file = ROOT / 'nil-manager/includes-license-product.php'
if not product_file.read_text().endswith("return '';\n"):
    raise SystemExit('Keep the distributable product identifier outside source; inject it at build time')
manifest_files = []
allowed = {'.php', '.js', '.css', '.html', '.txt', '.woff2', '.svg'}
files = sorted((ROOT / 'nil-manager').rglob('*'))
with zipfile.ZipFile(archive, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as bundle:
    for file in files:
        if file.name in {'admin.js','suite.js','advanced.js','market.js','payment-checkout.js','admin.css'}:
            continue  # Reviewable sources stay in git; WordPress receives production assets only.
        if not file.is_file():
            continue
        if file.suffix not in allowed or file.is_symlink():
            raise SystemExit(f'Unexpected plugin file: {file}')
        # Keep existing WordPress installations replaceable while source paths use nil-manager.
        relative = file.relative_to(ROOT / 'nil-manager')
        if relative.as_posix() == 'nil-manager.php':
            relative = Path('nilwears-bulk-manager.php')
        info = zipfile.ZipInfo((Path('nilwears-bulk-manager') / relative).as_posix(), date_time=(2020, 1, 1, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        data = file.read_bytes()
        if file == channel_file and args.channel == 'zhaket-review':
            data = data.replace(b"return 'github';", b"return 'zhaket';")
        if file == product_file and product_token:
            data = data.replace(b"return '';", ("return '" + product_token + "';").encode())
        bundle.writestr(info, data)
        manifest_files.append({'path': info.filename, 'size': len(data), 'sha256': hashlib.sha256(data).hexdigest()})
    if addon:
        data = args.zhaket_updater_file.read_bytes()
        info = zipfile.ZipInfo('nilwears-bulk-manager/addons/' + addon['archive'], date_time=(2020, 1, 1, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        bundle.writestr(info, data)
        manifest_files.append({'path': info.filename, 'size': len(data), 'sha256': hashlib.sha256(data).hexdigest()})
with zipfile.ZipFile(archive) as bundle:
    if bundle.testzip():
        raise SystemExit('ZIP integrity check failed')
    if 'nilwears-bulk-manager/nilwears-bulk-manager.php' not in bundle.namelist():
        raise SystemExit('Missing plugin entrypoint')
digest = hashlib.sha256(archive.read_bytes()).hexdigest()
(out / (archive.name + '.sha256')).write_text(f'{digest}  {archive.name}\n')
(out / 'release-notes.md').write_text(f'# نیل منجر — نسخهٔ {version}\n\n' + section.group(1).strip() + '\n')
manifest = {'product': 'نیل منجر', 'publisher': 'نیل وب', 'version': version,
            'channel': args.channel, 'basename': 'nilwears-bulk-manager/nilwears-bulk-manager.php',
            'archive': archive.name, 'sha256': digest, 'size': archive.stat().st_size,
            'guard_integrated': args.channel == 'zhaket-review', 'guard_configured': bool(product_token), 'guard_live_tested': bool(live), 'guard_test_scope': live['scope'] if live else None, 'guard_tested_at': live['tested_at'] if live else None, 'marketplace_approved': False,
            'updater_bundled': bool(addon), 'updater_version': addon['version'] if addon else None, 'updater_runtime_tested': False,
            'readiness': 'review-only' if args.channel == 'zhaket-review' else 'github-release',
            'files': manifest_files}
(out / (archive.name + '.manifest.json')).write_text(json.dumps(manifest, indent=2) + '\n')
print(f'Built {archive.name}; SHA256 {digest}')
