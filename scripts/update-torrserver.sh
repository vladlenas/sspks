#!/bin/sh
#
# Puts the SPK files of the latest GitHub release of TorrServer-DSM into the
# SSpkS packages folder. Meant for DSM Task Scheduler (run as root or as the
# user that owns the packages folder).
#
# Safe to run as often as you like: it only downloads when the release is
# newer than what is already there, checks every file before touching the
# folder, and swaps the files in atomically, so Package Center never sees a
# half-written package.
#
# Optional environment:
#   PACKAGES_DIR   packages folder (default: ../packages next to this script)
#   GITHUB_TOKEN   raises the GitHub API limit from 60 to 5000 requests/hour

set -eu

REPO="vladlenas/TorrServer-DSM"
ASSET_PREFIX="TorrServer-DSM-MatriX."   # file names start with this...
EXPECTED_ASSETS=3                       # ...and every release has this many SPKs

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
PACKAGES_DIR="${PACKAGES_DIR:-$SCRIPT_DIR/../packages}"
PACKAGES_DIR="$(CDPATH= cd -- "$PACKAGES_DIR" && pwd)"
API_URL="https://api.github.com/repos/${REPO}/releases/latest"

log() { printf '%s\n' "$*"; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

# ------------------------------------------------------------
# One run at a time (Task Scheduler can overlap with a manual run).
# ------------------------------------------------------------

LOCK_DIR="$PACKAGES_DIR/.update-torrserver.lock"
if ! mkdir "$LOCK_DIR" 2>/dev/null; then
    # A lock older than an hour is left over from a killed run.
    if [ -n "$(find "$LOCK_DIR" -maxdepth 0 -mmin +60 2>/dev/null)" ]; then
        rmdir "$LOCK_DIR" 2>/dev/null || true
        mkdir "$LOCK_DIR" 2>/dev/null || die "another update is running"
    else
        log "Another update is running, skipping."
        exit 0
    fi
fi

TMP_DIR="$(mktemp -d)"
cleanup() {
    rm -rf "$TMP_DIR"
    rm -f "$PACKAGES_DIR"/.*.spk.part 2>/dev/null || true
    rmdir "$LOCK_DIR" 2>/dev/null || true
}
trap cleanup EXIT
trap 'exit 1' INT TERM HUP

log "=== TorrServer updater ==="
log "Repository: $REPO"
log "Packages:   $PACKAGES_DIR"
log ""

# ------------------------------------------------------------
# Version helpers
# ------------------------------------------------------------

# Succeeds when dotted version $1 is greater than $2. Non-numeric suffixes
# ("1.2.3-beta", "145.1rc") are ignored, missing parts count as 0.
version_gt()
{
    awk -v a="$1" -v b="$2" 'BEGIN {
        na = split(a, x, "."); nb = split(b, y, ".")
        n = (na > nb) ? na : nb
        for (i = 1; i <= n; i++) {
            p = x[i] + 0; q = y[i] + 0
            if (p > q) exit 0
            if (p < q) exit 1
        }
        exit 1
    }'
}

# The SPK file name carries TorrServer's own version (e.g. 145.1), INFO
# carries the DSM package version (e.g. 1.9.145.1). We always use INFO.
package_version()
{
    tar -xOf "$1" INFO 2>/dev/null |
        sed -n 's/^version="\{0,1\}\([^"]*\)"\{0,1\}[[:space:]]*$/\1/p' |
        head -n 1
}

# ------------------------------------------------------------
# Latest release
# ------------------------------------------------------------

set -- -fsSL -H "Accept: application/vnd.github+json"
if [ -n "${GITHUB_TOKEN:-}" ]; then
    set -- "$@" -H "Authorization: Bearer $GITHUB_TOKEN"
fi
RELEASE_JSON="$(curl "$@" "$API_URL")" || die "GitHub API request failed"

LATEST_TAG="$(
    printf '%s\n' "$RELEASE_JSON" |
    sed -n 's/.*"tag_name"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' |
    head -n 1
)"
[ -n "$LATEST_TAG" ] || die "cannot determine the latest release"
LATEST_VERSION="${LATEST_TAG#v}"
log "Latest release: $LATEST_TAG"

# ------------------------------------------------------------
# What we have now
# ------------------------------------------------------------

CURRENT_VERSION=""
for file in "$PACKAGES_DIR/$ASSET_PREFIX"*.spk; do
    [ -f "$file" ] || continue
    file_version="$(package_version "$file")"
    if [ -z "$file_version" ]; then
        log "WARNING: cannot read the version of $(basename "$file")"
        continue
    fi
    log "Found package: $(basename "$file") -> $file_version"
    if [ -z "$CURRENT_VERSION" ] || version_gt "$file_version" "$CURRENT_VERSION"; then
        CURRENT_VERSION="$file_version"
    fi
done
log "Current version: ${CURRENT_VERSION:-none}"
log ""

if [ -n "$CURRENT_VERSION" ]; then
    if version_gt "$CURRENT_VERSION" "$LATEST_VERSION"; then
        log "The folder already has a newer version ($CURRENT_VERSION) than the release ($LATEST_VERSION). Nothing to do."
        exit 0
    fi
    if ! version_gt "$LATEST_VERSION" "$CURRENT_VERSION"; then
        log "Already up to date."
        exit 0
    fi
fi

log "Update required: ${CURRENT_VERSION:-none} -> $LATEST_VERSION"
log ""

# ------------------------------------------------------------
# Download every SPK of the release into a temporary folder.
# File names come from the release itself, never built by hand.
# ------------------------------------------------------------

ASSET_URLS="$(
    printf '%s\n' "$RELEASE_JSON" |
    sed -n 's/.*"browser_download_url"[[:space:]]*:[[:space:]]*"\(https:\/\/[^"]*\.spk\)".*/\1/p' |
    grep -F "/$ASSET_PREFIX" || true
)"
ASSET_COUNT="$(printf '%s\n' "$ASSET_URLS" | sed '/^$/d' | wc -l | tr -d ' ')"
[ "$ASSET_COUNT" -eq "$EXPECTED_ASSETS" ] ||
    die "expected $EXPECTED_ASSETS SPK files in $LATEST_TAG, found $ASSET_COUNT"

log "Downloading..."
for url in $ASSET_URLS; do
    asset="${url##*/}"
    case "$asset" in
        */* | .* | "") die "unexpected file name: $asset" ;;
    esac
    log "  $asset"
    curl -fsSL --retry 3 --retry-delay 2 -o "$TMP_DIR/$asset" "$url" || die "download failed: $asset"
done

# ------------------------------------------------------------
# Check every file before touching the packages folder:
# it must be a readable tar archive whose INFO has the release version.
# ------------------------------------------------------------

log "Checking..."
for file in "$TMP_DIR"/*.spk; do
    asset="$(basename "$file")"
    tar -tf "$file" >/dev/null 2>&1 || die "$asset is not a valid SPK (tar archive is damaged)"
    file_version="$(package_version "$file")"
    [ -n "$file_version" ] || die "$asset has no version in INFO"
    if [ "$file_version" != "$LATEST_VERSION" ]; then
        # Otherwise the script would re-download the same release on every run.
        die "$asset has version $file_version in INFO, but the release tag says $LATEST_VERSION"
    fi
done
log "All $EXPECTED_ASSETS packages are valid."
log ""

# ------------------------------------------------------------
# Swap in the new files, then remove the old ones.
# Each file is copied under a hidden temporary name inside the packages
# folder and renamed: a rename within one folder is atomic.
# ------------------------------------------------------------

log "Installing..."
for file in "$TMP_DIR"/*.spk; do
    asset="$(basename "$file")"
    part="$PACKAGES_DIR/.$asset.part"
    cp "$file" "$part"
    chmod 644 "$part"
    mv -f "$part" "$PACKAGES_DIR/$asset"
done

for file in "$PACKAGES_DIR/$ASSET_PREFIX"*.spk; do
    [ -f "$file" ] || continue
    [ -f "$TMP_DIR/$(basename "$file")" ] && continue
    log "  removing old $(basename "$file")"
    rm -f "$file"
done

# SSpkS 2 notices replaced files by itself (size + modification time), so the
# cache is not cleared any more.

log ""
log "=== Update completed: $LATEST_VERSION ==="
