# Synology Package Repository

Community SPK repository for Synology NAS.

This project is based on [jdel/sspks](https://github.com/jdel/sspks) and contains a customized interface and Synology model list.

## Features

- Modern web interface.
- Synology model-based package listing.
- DSM 7.3+ model list.
- Compatible with Synology Package Center.
- Docker deployment.

## Docker

The repository uses two persistent directories:

- `packages/` — SPK packages.
- `cache/` — generated package metadata and thumbnails.

Example:

```bash
docker run -d --name sspks \
  -v /path/to/packages:/var/www/localhost/htdocs/packages \
  -v /path/to/cache:/var/www/localhost/htdocs/cache \
  -p 8080:8080 \
  sspks
```

## Package updates

SPK packages are built and published separately in:

https://github.com/vladlenas/TorrServer-DSM

The repository is intended to update the published SPKs automatically on the Synology host. The update process downloads a complete new set first, replaces the old packages only after a successful download, and then clears the generated cache.

## Theme

The active theme is `themes/modern`.

The original `classic` and `material` themes are kept as fallback.
