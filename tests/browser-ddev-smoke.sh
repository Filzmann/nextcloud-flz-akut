#!/usr/bin/env bash
# Opt-in authentifizierter Browser-Smoke gegen die laufende DDEV-Umgebung.
# Voraussetzungen: DDEV-Projekt laeuft, google-chrome auf dem Host installiert.
# Nutzung: tests/browser-ddev-smoke.sh  (AKUT_BASE_URL/AKUT_BROWSER_USER/AKUT_BROWSER_PASSWORD optional)
set -euo pipefail

base_url="${AKUT_BASE_URL:-https://nextcloud-dev.ddev.site}"
user="${AKUT_BROWSER_USER:-admin}"
password="${AKUT_BROWSER_PASSWORD:-admin}"
workdir="$(mktemp -d)"
chrome_profile="$workdir/chrome-profile"
chrome_log="$workdir/chrome.log"
screenshot="$workdir/flz-akut-browser.png"
chrome_pid=''

cleanup() {
    if [[ -n "$chrome_pid" ]] && kill -0 "$chrome_pid" >/dev/null 2>&1; then
        kill "$chrome_pid" >/dev/null 2>&1 || true
        wait "$chrome_pid" >/dev/null 2>&1 || true
    fi
    rm -rf "$workdir"
}
trap cleanup EXIT

mkdir -p "$chrome_profile"
google-chrome \
    --headless=new \
    --disable-dev-shm-usage \
    --no-sandbox \
    --ignore-certificate-errors \
    --remote-allow-origins='*' \
    --remote-debugging-port=0 \
    --user-data-dir="$chrome_profile" \
    about:blank >"$chrome_log" 2>&1 &
chrome_pid=$!

for _ in $(seq 1 100); do
    [[ -s "$chrome_profile/DevToolsActivePort" ]] && break
    if ! kill -0 "$chrome_pid" >/dev/null 2>&1; then
        echo 'Headless Chrome wurde unerwartet beendet.' >&2
        sed -n '1,120p' "$chrome_log" >&2
        exit 1
    fi
    sleep 0.1
done
if [[ ! -s "$chrome_profile/DevToolsActivePort" ]]; then
    echo 'Chrome stellte innerhalb des Zeitlimits keinen DevTools-Port bereit.' >&2
    exit 1
fi
cdp_port="$(sed -n '1p' "$chrome_profile/DevToolsActivePort")"

AKUT_CDP_PORT="$cdp_port" \
AKUT_BASE_URL="$base_url" \
AKUT_BROWSER_USER="$user" \
AKUT_BROWSER_PASSWORD="$password" \
AKUT_BROWSER_SCREENSHOT="$screenshot" \
node "$(dirname "$0")/js/browser-ddev-smoke.mjs"

if [[ ! -s "$screenshot" ]]; then
    echo 'Das Overlay wurde nicht als temporärer Sichtnachweis erfasst.' >&2
    exit 1
fi

echo 'flz_akut DDEV-Browser-Smoke: OK'
