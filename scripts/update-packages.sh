#!/bin/sh
#
# Puts the SPK files of the latest GitHub release of each package below into
# the SSpkS packages folder. Meant for DSM Task Scheduler; safe to run as
# often as you like.
#
# For every package it
#   - looks at the latest release of the channel (see PACKAGES),
#   - does nothing if that release is already installed,
#   - downloads all .spk files of the release into a temporary folder,
#   - checks them (tar archive, INFO version, SHA256SUMS when the release has
#     one, beta flag matching the channel), never installs an older version,
#   - swaps them into the packages folder atomically and removes the files of
#     the previous release.
#
# Channels
#   stable  newest normal release (GitHub's "latest"; pre-releases are ignored)
#   beta    newest release including pre-releases. The SPKs must have
#           beta="yes" in INFO: SSpkS then offers them only to NAS that have
#           "beta versions" switched on in Package Center. Without the flag
#           they would go to everyone, so such files are refused.
#
# Optional environment:
#   PACKAGES_DIR   packages folder (default: ../packages next to this script)
#   GITHUB_TOKEN   raises the GitHub API limit from 60 to 5000 requests/hour
#
# Needs python3 for reading GitHub's answers (DSM 7 ships it).

set -eu

# repository                 file name prefix          channel
PACKAGES="
vladlenas/TorrServer-DSM     TorrServer-DSM-MatriX.    stable
vladlenas/Silo-DSM           Silo-DSM-                 beta
"

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
PACKAGES_DIR="${PACKAGES_DIR:-$SCRIPT_DIR/../packages}"
PACKAGES_DIR="$(CDPATH= cd -- "$PACKAGES_DIR" && pwd)"
STATE_DIR="$PACKAGES_DIR/.update-state"

log() { printf '%s\n' "$*"; }
warn() { printf 'WARNING: %s\n' "$*" >&2; }

PYTHON=""
for candidate in python3 /bin/python3 /usr/bin/python3 /usr/local/bin/python3 /var/packages/Python3*/target/usr/bin/python3; do
    if command -v "$candidate" >/dev/null 2>&1; then
        PYTHON="$(command -v "$candidate")"
        break
    fi
done
if [ -z "$PYTHON" ]; then
    printf 'ERROR: python3 not found (install Python 3 from Package Center)\n' >&2
    exit 1
fi

# ------------------------------------------------------------
# One run at a time (Task Scheduler can overlap with a manual run).
# ------------------------------------------------------------

LOCK_DIR="$PACKAGES_DIR/.update-packages.lock"
if ! mkdir "$LOCK_DIR" 2>/dev/null; then
    if [ -n "$(find "$LOCK_DIR" -maxdepth 0 -mmin +60 2>/dev/null)" ]; then
        rmdir "$LOCK_DIR" 2>/dev/null || true
        mkdir "$LOCK_DIR" 2>/dev/null || { log "Another update is running, skipping."; exit 0; }
    else
        log "Another update is running, skipping."
        exit 0
    fi
fi

TMP_ROOT="$(mktemp -d)"
cleanup() {
    rm -rf "$TMP_ROOT"
    rm -f "$PACKAGES_DIR"/.*.spk.part 2>/dev/null || true
    rmdir "$LOCK_DIR" 2>/dev/null || true
}
trap cleanup EXIT
trap 'exit 1' INT TERM HUP
mkdir -p "$STATE_DIR"

# ------------------------------------------------------------
# Helpers
# ------------------------------------------------------------

# Succeeds when version $1 is greater than $2 (numbers compared part by part,
# "-" and "_" count as separators, non-numeric parts as 0).
version_gt()
{
    awk -v a="$1" -v b="$2" 'BEGIN {
        gsub(/[-_]/, ".", a); gsub(/[-_]/, ".", b)
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

# Value of KEY in the INFO file of SPK $1 (quotes removed).
info_value()
{
    tar -xOf "$1" INFO 2>/dev/null |
        sed -n "s/^$2=\"\{0,1\}\([^\"]*\)\"\{0,1\}[[:space:]]*\$/\1/p" |
        head -n 1
}

is_yes()
{
    case "$(printf '%s' "$1" | tr 'A-Z' 'a-z')" in
        yes|true|1|on) return 0 ;;
        *) return 1 ;;
    esac
}

sha256_of()
{
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$1" | cut -d ' ' -f 1
    else
        "$PYTHON" -c 'import hashlib,sys; print(hashlib.sha256(open(sys.argv[1],"rb").read()).hexdigest())' "$1"
    fi
}

# Prints the chosen release of the GitHub API answer on stdin:
#   line 1: tag
#   then:   <name> TAB <download url>   for every *.spk and SHA256SUMS asset
# Exits 3 if there is no release for the channel.
pick_release()
{
    "$PYTHON" -c '
import json, sys
channel, prefix = sys.argv[1], sys.argv[2]
data = json.load(sys.stdin)
releases = data if isinstance(data, list) else [data]
for r in releases:
    if r.get("draft"):
        continue
    if channel == "stable" and r.get("prerelease"):
        continue
    print(r["tag_name"])
    for a in r.get("assets", []):
        name = a.get("name", "")
        if (name.startswith(prefix) and name.endswith(".spk")) or name == "SHA256SUMS":
            if "/" in name or name.startswith(".") or "\t" in name:
                continue
            print(name + "\t" + a["browser_download_url"])
    sys.exit(0)
sys.exit(3)
' "$1" "$2"
}

# ------------------------------------------------------------
# One package
# ------------------------------------------------------------

update_package()
{
    repo="$1"; prefix="$2"; channel="$3"
    work="$TMP_ROOT/$(printf '%s' "$repo" | tr '/' '_')"
    state_file="$STATE_DIR/$(printf '%s' "$repo" | tr '/' '_').tag"
    mkdir -p "$work/new" || return 1

    log "=== $repo ($channel) ==="

    if [ "$channel" = "stable" ]; then
        api="https://api.github.com/repos/${repo}/releases/latest"
    elif [ "$channel" = "beta" ]; then
        api="https://api.github.com/repos/${repo}/releases?per_page=20"
    else
        warn "unknown channel '$channel' for $repo"
        return 1
    fi

    set -- -fsSL -H "Accept: application/vnd.github+json"
    if [ -n "${GITHUB_TOKEN:-}" ]; then
        set -- "$@" -H "Authorization: Bearer $GITHUB_TOKEN"
    fi
    if ! curl "$@" "$api" > "$work/release.json"; then
        warn "GitHub API request failed for $repo"
        return 1
    fi

    set +e
    pick_release "$channel" "$prefix" < "$work/release.json" > "$work/release.txt"
    rc=$?
    set -e
    if [ "$rc" -eq 3 ]; then
        log "No $channel release yet."
        return 0
    elif [ "$rc" -ne 0 ]; then
        warn "cannot read the release list of $repo"
        return 1
    fi

    tag="$(head -n 1 "$work/release.txt")"
    tail -n +2 "$work/release.txt" > "$work/assets.txt"
    spk_count="$(grep -c '\.spk	' "$work/assets.txt" || true)"
    log "Latest release: $tag ($spk_count SPK)"

    # Already installed: nothing to download. Files removed by hand are
    # brought back.
    have_files=0
    for file in "$PACKAGES_DIR/$prefix"*.spk; do
        [ -f "$file" ] && have_files=1 && break
    done
    if [ "$(cat "$state_file" 2>/dev/null)" = "$tag" ] && [ "$have_files" -eq 1 ]; then
        log "Already up to date."
        return 0
    fi

    if [ "$spk_count" -eq 0 ]; then
        warn "release $tag of $repo has no ${prefix}*.spk files"
        return 1
    fi

    # Version currently in the folder (highest INFO version).
    current=""
    for file in "$PACKAGES_DIR/$prefix"*.spk; do
        [ -f "$file" ] || continue
        v="$(info_value "$file" version)"
        [ -n "$v" ] || continue
        if [ -z "$current" ] || version_gt "$v" "$current"; then
            current="$v"
        fi
    done
    log "In the folder: ${current:-nothing}"

    log "Downloading..."
    while IFS="	" read -r name url; do
        [ -n "$name" ] || continue
        log "  $name"
        if ! curl -fsSL --retry 3 --retry-delay 2 -o "$work/new/$name" "$url"; then
            warn "download failed: $name"
            return 1
        fi
    done < "$work/assets.txt"

    log "Checking..."
    if [ -f "$work/new/SHA256SUMS" ]; then
        for file in "$work/new/$prefix"*.spk; do
            name="$(basename "$file")"
            expected="$(awk -v n="$name" '{ f = $2; sub(/^\*/, "", f); if (f == n) { print $1; exit } }' "$work/new/SHA256SUMS")"
            if [ -z "$expected" ]; then
                warn "$name is not listed in SHA256SUMS"
                return 1
            fi
            if [ "$(sha256_of "$file")" != "$expected" ]; then
                warn "$name: checksum does not match SHA256SUMS"
                return 1
            fi
        done
        log "  SHA256SUMS: ok"
        rm -f "$work/new/SHA256SUMS"
    fi

    new_version=""
    package_name=""
    for file in "$work/new/$prefix"*.spk; do
        name="$(basename "$file")"
        if ! tar -tf "$file" >/dev/null 2>&1; then
            warn "$name is not a valid SPK (damaged tar archive)"
            return 1
        fi
        v="$(info_value "$file" version)"
        p="$(info_value "$file" package)"
        if [ -z "$v" ] || [ -z "$p" ]; then
            warn "$name has no package name or version in INFO"
            return 1
        fi
        if [ -z "$new_version" ]; then
            new_version="$v"; package_name="$p"
        elif [ "$v" != "$new_version" ] || [ "$p" != "$package_name" ]; then
            warn "files of $tag disagree: $p $v vs $package_name $new_version"
            return 1
        fi
        beta="$(info_value "$file" beta)"
        if [ "$channel" = "beta" ] && ! is_yes "$beta"; then
            warn "$name has no beta=\"yes\" in INFO; it would be offered to every NAS, not only to beta testers. Not installed."
            return 1
        fi
        if [ "$channel" = "stable" ] && is_yes "$beta"; then
            warn "$name is marked beta=\"yes\": only NAS with beta versions enabled will see it."
        fi
    done
    log "  $package_name $new_version: ok"

    if [ -n "$current" ] && ! version_gt "$new_version" "$current"; then
        if [ "$new_version" = "$current" ]; then
            log "Same version as in the folder, nothing to install."
        else
            log "The folder has a newer version ($current) than $tag ($new_version). Nothing to install."
        fi
        printf '%s\n' "$tag" > "$state_file" || true
        return 0
    fi

    log "Installing: ${current:-nothing} -> $new_version"
    for file in "$work/new/"*.spk; do
        name="$(basename "$file")"
        part="$PACKAGES_DIR/.$name.part"
        if ! { cp "$file" "$part" && chmod 644 "$part" && mv -f "$part" "$PACKAGES_DIR/$name"; }; then
            rm -f "$part"
            warn "cannot write $name to the packages folder"
            return 1
        fi
    done
    for file in "$PACKAGES_DIR/$prefix"*.spk; do
        [ -f "$file" ] || continue
        [ -f "$work/new/$(basename "$file")" ] && continue
        log "  removing old $(basename "$file")"
        rm -f "$file"
    done
    printf '%s\n' "$tag" > "$state_file"
    log "Updated to $new_version."
}

# ------------------------------------------------------------

log "Packages folder: $PACKAGES_DIR"
log ""

printf '%s\n' "$PACKAGES" | while read -r repo prefix channel; do
    [ -n "$repo" ] || continue
    case "$repo" in \#*) continue ;; esac
    if ! update_package "$repo" "$prefix" "$channel"; then
        echo failed >> "$TMP_ROOT/failed"
        log "Skipped $repo because of the error above."
    fi
    log ""
done

if [ -f "$TMP_ROOT/failed" ]; then
    log "Finished with errors."
    exit 1
fi
log "Done."
