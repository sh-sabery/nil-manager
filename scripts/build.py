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
args = parser.parse_args()
version = (ROOT / 'VERSION').read_text().strip()
if not re.fullmatch(r'\d+\.\d+\.\d+', version):
    raise SystemExit('VERSION must be X.Y.Z')
source = (ROOT / 'nilwears-bulk-manager/nilwears-bulk-manager.php').read_text()
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
description = ROOT / 'nilwears-bulk-manager/changelog-fa.html'
expected_hash = hashlib.sha256(changelog.encode()).hexdigest()
if not description.exists() or not description.read_text().startswith('<!-- source-sha256: ' + expected_hash + ' -->'):
    raise SystemExit('Persian release descriptions are stale; run npm run build:descriptions')
out = ROOT / 'dist'
if args.channel == 'zhaket-review':
    out = out / 'zhaket-review'
out.mkdir(parents=True, exist_ok=True)
suffix = '-zhaket-review' if args.channel == 'zhaket-review' else ''
archive = out / f'nil-manager-v{version}{suffix}.zip'
channel_file = ROOT / 'nilwears-bulk-manager/includes-channel.php'
if channel_file.read_text() != "<?php\n/** Distribution identity, generated only by the release builder. */\ndefined( 'ABSPATH' ) || exit;\nreturn 'github';\n":
    raise SystemExit('Source channel must remain github; build transforms the review copy only')
manifest_files = []
allowed = {'.php', '.js', '.css', '.html', '.txt', '.woff2', '.svg'}
files = sorted((ROOT / 'nilwears-bulk-manager').rglob('*'))
with zipfile.ZipFile(archive, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as bundle:
    for file in files:
        if file.name in {'admin.js','suite.js','advanced.js','market.js','payment-checkout.js','admin.css'}:
            continue  # Reviewable sources stay in git; WordPress receives production assets only.
        if not file.is_file():
            continue
        if file.suffix not in allowed or file.is_symlink():
            raise SystemExit(f'Unexpected plugin file: {file}')
        info = zipfile.ZipInfo(file.relative_to(ROOT).as_posix(), date_time=(2020, 1, 1, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        data = file.read_bytes()
        if file == channel_file and args.channel == 'zhaket-review':
            data = data.replace(b"return 'github';", b"return 'zhaket';")
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
            'guard_integrated': False, 'marketplace_approved': False,
            'readiness': 'review-only' if args.channel == 'zhaket-review' else 'github-release',
            'files': manifest_files}
(out / (archive.name + '.manifest.json')).write_text(json.dumps(manifest, indent=2) + '\n')
print(f'Built {archive.name}; SHA256 {digest}')
