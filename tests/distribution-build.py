#!/usr/bin/env python3
"""Build both channels and verify exact packaged identities, hashes and isolation."""
import hashlib
import json
from pathlib import Path
import subprocess
import zipfile

ROOT = Path(__file__).resolve().parents[1]
version = (ROOT / 'VERSION').read_text().strip()
source = ROOT / 'nilwears-bulk-manager/includes-channel.php'
before = source.read_bytes()
contents = {}
for channel in ('github', 'zhaket-review'):
    subprocess.run(['python3', 'scripts/build.py', '--channel', channel], cwd=ROOT, check=True)
    folder = ROOT / 'dist' / ('zhaket-review' if channel == 'zhaket-review' else '')
    suffix = '-zhaket-review' if channel == 'zhaket-review' else ''
    archive = folder / f'nilwears-bulk-manager-v{version}{suffix}.zip'
    manifest = json.loads(archive.with_name(archive.name + '.manifest.json').read_text())
    assert manifest['sha256'] == hashlib.sha256(archive.read_bytes()).hexdigest()
    assert manifest['size'] == archive.stat().st_size
    assert manifest['channel'] == channel and not manifest['guard_integrated'] and not manifest['marketplace_approved']
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
assert contents['github'].keys() == contents['zhaket-review'].keys()
changed = [name for name in contents['github'] if contents['github'][name] != contents['zhaket-review'][name]]
assert changed == ['nilwears-bulk-manager/includes-channel.php'], changed
assert b"return 'github';" in contents['github'][changed[0]]
assert b"return 'zhaket';" in contents['zhaket-review'][changed[0]]
print('PASS: both reproducible plugin-only channels, manifest hashes, identical basename, unchanged source, review isolation')
