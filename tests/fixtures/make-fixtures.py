#!/usr/bin/env python3
"""Regenerates the test .spk files in tests/fixtures/spk/.

Run from the repository root:  python3 tests/fixtures/make-fixtures.py
"""
import base64
import io
import os
import tarfile

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, 'spk')
ICON = open(os.path.join(HERE, 'icon.png'), 'rb').read()


def info(**fields):
    lines = []
    for key, value in fields.items():
        lines.append(f'{key}="{value}"' if not key.startswith('raw_') else f'{key[4:]}={value}')
    return ('\n'.join(lines) + '\n').encode()


def add(tar, name, data, mtime=1_700_000_000):
    entry = tarfile.TarInfo(name)
    entry.size = len(data)
    entry.mtime = mtime
    entry.mode = 0o644
    tar.addfile(entry, io.BytesIO(data))


def add_dir(tar, name):
    entry = tarfile.TarInfo(name)
    entry.type = tarfile.DIRTYPE
    entry.mode = 0o755
    entry.mtime = 1_700_000_000
    tar.addfile(entry)


def write(name, entries, mode='w', fmt=tarfile.USTAR_FORMAT):
    path = os.path.join(OUT, name)
    with tarfile.open(path, mode, format=fmt) as tar:
        for entry in entries:
            entry(tar)
    os.utime(path, (1_700_000_000, 1_700_000_000))


os.makedirs(OUT, exist_ok=True)
for old in os.listdir(OUT):
    os.remove(os.path.join(OUT, old))

# Full DSM 7 package: icons, screenshots, a wizard and a payload to skip.
write('alpha_x64-1.0.spk', [
    lambda t: add(t, 'INFO', info(
        package='alpha', version='1.0.0-1', arch='x86_64 avoton', os_min_ver='7.0-40000',
        displayname='Alpha', displayname_rus='Альфа',
        description='Alpha "quoted" package', description_rus='Пакет альфа',
        maintainer='Alpha Team', maintainer_url='https://alpha.example',
        changelog='<a href=\\"https://alpha.example/notes\\">Notes</a> & more',
        install_dep_packages='beta-tool>2.0', start_dep_services='ssh',
        silent_install='yes', raw_beta='no',
    )),
    lambda t: add(t, 'package.tgz', os.urandom(5000)),
    lambda t: add(t, 'PACKAGE_ICON.PNG', ICON),
    lambda t: add(t, 'PACKAGE_ICON_256.PNG', ICON),
    lambda t: add(t, 'screen_2.png', b'second'),
    lambda t: add(t, 'screen_1.png', b'first'),
    lambda t: add_dir(t, 'WIZARD_UIFILES'),
    lambda t: add(t, 'WIZARD_UIFILES/install_uifile', b'[]'),
])

# Newer version of the same package for x86_64.
write('alpha_x64-1.1.spk', [
    lambda t: add(t, 'INFO', info(package='alpha', version='1.1.0-1', arch='x86_64', os_min_ver='7.0-40000')),
    lambda t: add(t, 'package.tgz', os.urandom(1000)),
])

# Same version built for ARMv8.
write('alpha_armv8-1.1.spk', [
    lambda t: add(t, 'INFO', info(package='alpha', version='1.1.0-1', arch='aarch64', os_min_ver='7.0-40000')),
])

# Beta, noarch, gzipped, entries prefixed with ./, icon embedded in INFO.
write('beta-tool.spk', [
    lambda t: add(t, './INFO', info(
        package='beta-tool', version='2.0.0', arch='noarch', os_min_ver='7.0-40000',
        package_icon=base64.b64encode(ICON).decode(), raw_beta='yes',
    )),
], mode='w:gz')

# DSM 6 package (firmware= instead of os_min_ver=).
write('legacy_dsm6.spk', [
    lambda t: add(t, 'INFO', info(package='legacy', version='0.9', arch='x86_64', firmware='6.1-15047')),
])

# GNU long names before the INFO file.
write('longname.spk', [
    lambda t: add(t, 'docs/' + 'x' * 120 + '.txt', b'long'),
    lambda t: add(t, 'INFO', info(package='longname', version='1.0', arch='noarch', os_min_ver='7.0-40000')),
], fmt=tarfile.GNU_FORMAT)

# Broken files.
with open(os.path.join(OUT, 'not-a-tar.spk'), 'wb') as f:
    f.write(b'this is not an archive' * 50)
write('no-info.spk', [lambda t: add(t, 'package.tgz', b'payload')])

full = open(os.path.join(OUT, 'alpha_x64-1.0.spk'), 'rb').read()
with open(os.path.join(OUT, 'truncated.spk'), 'wb') as f:
    f.write(full[:2048])  # INFO header + start of package.tgz

print('\n'.join(sorted(os.listdir(OUT))))
