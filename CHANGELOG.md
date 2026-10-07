# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [2.1.0] - 2026-10

### Added
- Download statistics at `/?stats`: downloads from this server per day and per
  package (from the web page vs. Package Center/direct links), and download
  counters of GitHub releases for repositories in `SSPKS_GITHUB_REPOS`
  (cached for an hour, last good data kept when GitHub is unreachable).
- Download totals in the package list and in the catalog's
  `download_count`/`recent_download_count`.
- `TZ` sets the time zone for dates; `SSPKS_STATS=off` turns statistics off.

### Fixed
- Empty list items were rendered as the text "null" in package details when
  a package had no minimum DSM version.

## [2.0.0] - 2026-10

Rewrite of the fork. Same idea, same `/packages` and `/cache` volumes, same
port 8080 and the same `SSPKS_*` variables.

### Changed
- New web interface: one page with search, platform and DSM filters, all
  builds of a package, changelog and screenshots, Russian and English,
  light and dark theme.
- SPK files are read with a built-in streaming tar reader instead of
  PharData; nothing is extracted to a shared temp folder any more.
- Cache: one record per package, rebuilt when the file's size or
  modification time changes. Replacing a package under the same name is
  picked up immediately, MD5 is computed once per file instead of on every
  request, entries of deleted packages and files from 1.x are cleaned up.
- Docker image based on `php:8.4-apache`, runs as an unprivileged user,
  only the `public/` folder and `*.spk` downloads are reachable, health check.
- `deppkgs` comes from `install_dep_packages` and `conflictpkgs` from
  `install_conflict_packages` (previously `install_dep_services` was sent).
- ARM platforms are mapped to their families (`armv7`, `armv8`), and
  `x64`/`aarch64` are accepted as aliases.
- Settings come only from environment variables (or `config.local.php`).
  `conf/sspks.yaml` and `conf/synology_models.yaml` are gone.
- `gpgkey.asc` is read from the packages folder.
- CI: tests on PHP 8.2–8.4, multi-arch image on GHCR.

### Fixed
- Catalog JSON broke on quotes and backslashes in descriptions or changelogs
  (`stripslashes` on the encoded JSON).
- Race between concurrent requests extracting different packages.
- Package file names with spaces or `+` produced broken download links.
- An empty update channel returned no packages.
- PHP 8.1+ deprecation notices.

### Removed
- Native SPK package of the server (`_syno_package`): it did not install
  on DSM 7. Use the Docker image.
- `?fulllist`, the device model list, `selftest.php` (use `?health`),
  Composer, Gitpod and Scrutinizer setup.

## [1.2.1] - 2022-03-16
Last release of the original code base.
