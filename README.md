# SSpkS: Simple Synology Package Server

A small package source for Synology Package Center. Put `.spk` files in a
folder, run the container, and add its address in
**Package Center → Settings → Package Sources**. The same address opens a web
page that lists the packages with search, platform and DSM filters, and
download links.

Originally based on [jdel/sspks](https://github.com/jdel/sspks) by Julien
Del-Piccolo; rewritten in 2.0 (see [CHANGELOG](CHANGELOG.md)). No runtime
dependencies besides PHP 8.2+.

## Run on a Synology NAS

1. Create two folders, for example `/volume1/docker/sspks/packages` and
   `/volume1/docker/sspks/cache`.
2. In Container Manager create a project from [`compose.yaml`](compose.yaml),
   adjust the paths, port and `user`.
3. Add `http://<nas>:9999/` as a package source in Package Center.

With plain Docker:

```sh
docker run -d --name sspks --restart unless-stopped \
  --user 1026:100 \
  -p 9999:8080 \
  -v /volume1/docker/sspks/packages:/packages:ro \
  -v /volume1/docker/sspks/cache:/cache \
  -e SSPKS_SITE_NAME="My packages" \
  ghcr.io/vladlenas/sspks:latest
```

### Permissions

The server only reads `/packages` and writes small files (metadata, icons) to
`/cache`. Run the container as the DSM user that owns these folders
(`user: "UID:GID"`; get the numbers with `id <user>` over SSH) or make the
cache folder writable for UID 33. Without a writable cache everything still
works, it is just slower and says so in the container log.

### HTTPS

Put the container behind DSM's reverse proxy (Control Panel → Login Portal →
Advanced → Reverse Proxy) or any other proxy. `X-Forwarded-Proto`,
`X-Forwarded-Host` and `X-Forwarded-Prefix` are honoured; if the generated
links are still wrong, set `SSPKS_BASE_URL`.

## Configuration

| Variable | Meaning | Default |
| --- | --- | --- |
| `SSPKS_SITE_NAME` | Title of the web page | `Synology packages` |
| `SSPKS_SITE_LANG` | `ru` or `en`; empty follows the browser | |
| `SSPKS_SITE_REDIRECTINDEX` | Redirect browsers to this URL instead of showing the list | |
| `SSPKS_BASE_URL` | Public address of the server, e.g. `https://spk.example.com/` | detected |
| `SSPKS_PACKAGES_FILE_MASK` | Which files are packages | `*.spk` |
| `SSPKS_PACKAGES_MAINTAINER` | Used when a package's INFO has none | |
| `SSPKS_PACKAGES_MAINTAINER_URL` | 〃 | |
| `SSPKS_PACKAGES_DISTRIBUTOR` | 〃 | |
| `SSPKS_PACKAGES_DISTRIBUTOR_URL` | 〃 | |
| `SSPKS_PACKAGES_SUPPORT_URL` | 〃 | |
| `SSPKS_STATS` | `off` hides the statistics page and download counts | `on` |
| `SSPKS_GITHUB_REPOS` | `owner/repo` list (spaces or commas) whose release downloads are shown | |
| `SSPKS_GITHUB_TOKEN` | GitHub token, only needed above 60 API requests/hour | |
| `TZ` | Time zone for dates in the statistics, e.g. `Europe/Vilnius` | `UTC` |
| `SSPKS_PACKAGES_DIR` / `SSPKS_CACHE_DIR` | Folders (only needed outside Docker) | `/packages`, `/cache` |

Without Docker, the same variables can go into `config.local.php` in the
project root (`<?php return ['SSPKS_SITE_NAME' => '...'];`); point the web
server's document root at `public/`.

## How packages are picked

For each package name Package Center gets the newest version that matches
the NAS: its platform (or the platform's family such as `x86_64`, `armv8`, or
`noarch`), its DSM generation (DSM 7 packages only for DSM 7, older ones only
for DSM 6), the minimum DSM version, and the update channel (beta packages
only on the beta channel). Older files can stay in the folder; they are just
not offered.

Optional extras inside an `.spk`: `screen_1.png`, `screen_2.png`, … are shown
as screenshots. A `gpgkey.asc` in the packages folder is published as keyring
(DSM 6).

## Download statistics

`/?stats` shows how often packages were downloaded from this server (per day
and per package, split into downloads from the web page and from Package
Center or direct links) and, for repositories listed in
`SSPKS_GITHUB_REPOS`, the download counters of their GitHub releases. The
package list shows a total per package, and Package Center receives the same
numbers in `download_count`.

Apache writes one line per package download to `/cache/stats/downloads.log`:
time, status, path, Range and Referer. No IP addresses or user agents are
stored. Counting starts with the first download after the update; the cache
folder must be writable for it. GitHub numbers are refreshed at most once an
hour.

## Keeping packages up to date

[`scripts/update-torrserver.sh`](scripts/update-torrserver.sh) is an example
for DSM Task Scheduler: it fetches the SPKs of the latest GitHub release,
verifies them and swaps them in atomically. Replacing files is all it takes;
the server notices changed files by size and modification time.

## Development

```sh
make test       # PHPUnit in a php:8.4-cli container
make run        # serves tests/fixtures/spk on http://localhost:9999/
make fixtures   # regenerates the test packages (Python 3)
```

Pushes to `master` publish `ghcr.io/vladlenas/sspks:latest`; tags `vX.Y.Z`
publish versioned images and a GitHub release.

## License

GPL-3.0, see [LICENSE](LICENSE).
