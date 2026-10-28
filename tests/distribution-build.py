#!/usr/bin/env python3
"""Build both channels and verify exact packaged identities, hashes and isolation."""
import hashlib
import json
from pathlib import Path
import subprocess
import zipfile

ROOT = Path(__file__).resolve().parents[1]
version = (ROOT / 'VERSION').read_text().strip()
source = ROOT / 'nil-manager/includes-channel.php'
before = source.read_bytes()
contents = {}
for channel in ('github', 'zhaket-review'):
    subprocess.run(['python3', 'scripts/build.py', '--channel', channel], cwd=ROOT, check=True)
    folder = ROOT / 'dist' / ('zhaket-review' if channel == 'zhaket-review' else '')
    suffix = '-zhaket-review' if channel == 'zhaket-review' else ''
    archive = folder / f'nil-manager-v{version}{suffix}.zip'
    manifest = json.loads(archive.with_name(archive.name + '.manifest.json').read_text())
    assert manifest['sha256'] == hashlib.sha256(archive.read_bytes()).hexdigest()
    assert manifest['size'] == archive.stat().st_size
    assert manifest['channel'] == channel and manifest['guard_integrated'] == (channel == 'zhaket-review') and not manifest['guard_configured'] and not manifest['guard_live_tested'] and not manifest['marketplace_approved']
    with zipfile.ZipFile(archive) as bundle:
        contents[channel] = {name: bundle.read(name) for name in bundle.namelist()}
    assert set(contents[channel]) == {entry['path'] for entry in manifest['files']}
    for entry in manifest['files']:
        data = contents[channel][entry['path']]
        assert len(data) == entry['size'] and hashlib.sha256(data).hexdigest() == entry['sha256']
        assert entry['path'].startswith('nilwears-bulk-manager/')
        assert not any(part in ('tests', 'scripts', 'node_modules', '.github') for part in Path(entry['path']).parts)
    first_digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    subprocess.run(['python3', 'scripts/build.py', '--channel', channel], cwd=ROOT, check=True)
    assert hashlib.sha256(archive.read_bytes()).hexdigest() == first_digest, 'non-deterministic archive'
    assert source.read_bytes() == before, 'review build changed source identity'
    assert manifest['basename'] == 'nilwears-bulk-manager/nilwears-bulk-manager.php'
    assert 'nil-manager/nil-manager.php' not in contents[channel], 'no duplicate installed plugin'
    assert contents[channel][manifest['basename']] == (ROOT / 'nil-manager/nil-manager.php').read_bytes(), 'entrypoint content preserved through path mapping'
assert contents['github'].keys() == contents['zhaket-review'].keys()
changed = [name for name in contents['github'] if contents['github'][name] != contents['zhaket-review'][name]]
assert changed == ['nilwears-bulk-manager/includes-channel.php'], changed
assert b"return 'github';" in contents['github'][changed[0]]
assert b"return 'zhaket';" in contents['zhaket-review'][changed[0]]
print('PASS: both reproducible plugin-only channels, manifest hashes, identical basename, unchanged source, review isolation')

# A public product ID may be injected only into a separate review artifact, never GitHub/source.
import tempfile
with tempfile.TemporaryDirectory() as folder:
    token = Path(folder) / 'public-product-id.txt'
    token.write_text('fixture-public-product')
    subprocess.run(['python3', 'scripts/build.py', '--channel', 'zhaket-review', '--product-token-file', str(token)], cwd=ROOT, check=True)
    archive = ROOT / 'dist/zhaket-review' / f'nil-manager-v{version}-zhaket-review.zip'
    with zipfile.ZipFile(archive) as bundle:
        assert b"return 'fixture-public-product';" in bundle.read('nilwears-bulk-manager/includes-license-product.php')
    manifest = json.loads(archive.with_name(archive.name + '.manifest.json').read_text())
    assert manifest['guard_configured'] and not manifest['guard_live_tested'] and 'fixture-public-product' not in json.dumps(manifest)
    assert (ROOT / 'nil-manager/includes-license-product.php').read_text().endswith("return '';\n")
    rejected = subprocess.run(['python3', 'scripts/build.py', '--product-token-file', str(token)], cwd=ROOT, capture_output=True)
    assert rejected.returncode != 0
# Leave the CI artifact unconfigured; fixture IDs must not be uploaded as a real candidate.
subprocess.run(['python3', 'scripts/build.py', '--channel', 'zhaket-review'], cwd=ROOT, check=True)
print('PASS: marketplace-only public product ID injection, no source/manifest leak, no live-test claim')

# Test optional add-on/report packaging in a disposable source copy, never overwrite vendor metadata.
import shutil
from datetime import datetime, timezone
with tempfile.TemporaryDirectory() as folder:
    isolated = Path(folder)
    for name in ('nil-manager','scripts','docs'):
        shutil.copytree(ROOT / name, isolated / name)
    for name in ('VERSION','CHANGELOG.md','package.json','package-lock.json'):
        shutil.copyfile(ROOT / name, isolated / name)
    metadata = json.loads((isolated / 'docs/zhaket-updater.json').read_text())
    addon = isolated / 'fixture-updater.zip'
    with zipfile.ZipFile(addon, 'w') as z:
        z.writestr(metadata['entrypoint'], '<?php /* Plugin Name: Fixture only */')
    metadata['size'] = addon.stat().st_size
    metadata['sha256'] = hashlib.sha256(addon.read_bytes()).hexdigest()
    (isolated / 'docs/zhaket-updater.json').write_text(json.dumps(metadata))
    token = isolated / 'public-product-id.txt'
    token.write_text('fixture-public-product')
    report = isolated / 'guard-report.json'
    evidence = {'plugin_version':version, 'product_sha256':hashlib.sha256(token.read_bytes()).hexdigest(), 'activation':True, 'validation':True, 'scope':'adapter-real-https-with-isolated-option-stubs', 'domain':'nil-manager-guard.test', 'wordpress_bootstrapped':False, 'tested_at':datetime.now(timezone.utc).replace(microsecond=0).isoformat()}
    report.write_text(json.dumps(evidence))
    command = ['python3','scripts/build.py','--channel','zhaket-review','--product-token-file',str(token),'--zhaket-updater-file',str(addon),'--guard-test-report',str(report)]
    subprocess.run(command,cwd=isolated,check=True)
    archive = isolated / 'dist/zhaket-review' / f'nil-manager-v{version}-zhaket-review.zip'
    manifest = json.loads(archive.with_name(archive.name+'.manifest.json').read_text())
    assert manifest['guard_live_tested'] and manifest['updater_bundled'] and not manifest['updater_runtime_tested']
    assert manifest['guard_test_scope'] == evidence['scope']
    with zipfile.ZipFile(archive) as z:
        assert z.read('nilwears-bulk-manager/addons/'+metadata['archive']) == addon.read_bytes()
        assert not any('guard-report' in n or 'public-product' in n for n in z.namelist())
    for forbidden in ('fixture-public-product','fixture-license'):
        assert forbidden not in json.dumps(manifest)
    evidence['product_sha256']='wrong-product'
    report.write_text(json.dumps(evidence))
    assert subprocess.run(command,cwd=isolated,capture_output=True).returncode != 0
    addon.write_bytes(addon.read_bytes()+b'tampered')
    assert subprocess.run(command[:-2],cwd=isolated,capture_output=True).returncode != 0
    assert subprocess.run(['python3','scripts/build.py','--zhaket-updater-file',str(addon)],cwd=isolated,capture_output=True).returncode != 0
print('PASS: optional unmodified add-on only in review ZIP; matched redacted live report; corrupt files/wrong product/GitHub injection rejected')
