#!/bin/sh

set -eu

# Synology host configuration.
# Override these variables when deploying the script.
REPO="${REPO:-vladlenas/TorrServer-DSM}"
PACKAGES_DIR="${PACKAGES_DIR:-./packages}"
CACHE_DIR="${CACHE_DIR:-./cache}"
TMP_ROOT="${TMP_ROOT:-/tmp/sspks-update}"

BASE_URL="https://api.github.com/repos/${REPO}/releases/latest"
PREFIX="TorrServer-DSM-"
ARCHES="amd64 arm64 arm7"

log() {
    printf '%s %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

need_cmd() {
    command -v "$1" >/dev/null 2>&1 || {
        log "ERROR: required command not found: $1"
        exit 1
    }
}

need_cmd curl
need_cmd grep
need_cmd sed
need_cmd mktemp
need_cmd find
need_cmd mv
need_cmd rm

mkdir -p "$PACKAGES_DIR" "$CACHE_DIR"

release_json="$(mktemp)"
tmp_dir="$(mktemp -d "${TMP_ROOT}.XXXXXX")"
trap 'rm -f "$release_json"; rm -rf "$tmp_dir"' EXIT INT TERM

log "Checking latest GitHub release: ${REPO}"
curl -fsSL \
    -H 'Accept: application/vnd.github+json' \
    -H 'User-Agent: Synology-SPK-Updater' \
    "$BASE_URL" > "$release_json"

tag="$(sed -n 's/.*"tag_name"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$release_json" | head -n 1)"
[ -n "$tag" ] || { log "ERROR: release tag not found"; exit 1; }

log "Latest release: ${tag}"

# The repository is updated only when all expected SPKs are available.
for arch in $ARCHES; do
    name="${PREFIX}${tag#v}-${arch}.spk"
    # Current naming uses the TorrServer version in the filename, not the Git tag.
    # Find the matching asset by architecture instead.
    asset_url="$(sed -n '/"name"[[:space:]]*:[[:space:]]*"[^"]*-'"$arch"'\.spk"/,/"browser_download_url"/p' "$release_json" \
        | sed -n 's/.*"browser_download_url"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -n 1)"

    [ -n "$asset_url" ] || { log "ERROR: asset for ${arch} not found in ${tag}"; exit 1; }

    outfile="$tmp_dir/$(basename "$asset_url")"
    log "Downloading ${arch}: $(basename "$asset_url")"
    curl -fsSL -L \
        -H 'Accept: application/octet-stream' \
        -H 'User-Agent: Synology-SPK-Updater' \
        "$asset_url" -o "$outfile"

    [ -s "$outfile" ] || { log "ERROR: downloaded file is empty: $outfile"; exit 1; }
    downloaded_${arch}=1
    eval "file_${arch}=\"$outfile\""
done

# Replace only TorrServer packages. Other packages in the repository remain untouched.
find "$PACKAGES_DIR" -maxdepth 1 -type f -name 'TorrServer-DSM-*.spk' -delete
mv "$tmp_dir"/*.spk "$PACKAGES_DIR/"

# Package metadata and thumbnails are generated from SPKs and must be rebuilt.
find "$CACHE_DIR" -maxdepth 1 -type f \( \
    -name 'TorrServer-DSM-*.nfo' -o \
    -name 'TorrServer-DSM-*thumb_*.png' \
\) -delete

log "Repository updated to ${tag}"
