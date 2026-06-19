#!/usr/bin/env bash
#
# TAG Protect - install the FreePBX GUI MODULE (one command).
#
#   curl -fsSL https://raw.githubusercontent.com/Shwerzb/tag-protect-freepbx/main/install-module.sh | sudo bash
#
# Downloads the module, installs it into FreePBX, then lets you choose:
#   [1] GUI setup  - just install; configure later at Connectivity -> TAG Protect
#   [2] CLI setup  - enter API key/lists/requester now (no need to open the GUI)
#
set -euo pipefail

GH_REPO="${TAGPROTECT_REPO:-Shwerzb/tag-protect-freepbx}"
BRANCH="${TAGPROTECT_BRANCH:-main}"
SRC=/usr/local/lib/tag-protect-freepbx
MODDIR=/var/www/html/admin/modules/tagprotect
TTY=/dev/tty

g(){ printf '\033[32m%s\033[0m\n' "$*"; }
y(){ printf '\033[33m%s\033[0m\n' "$*"; }
r(){ printf '\033[31m%s\033[0m\n' "$*"; }
ask(){ local p="$1" d="${2:-}" a=""; if [ -n "$d" ]; then printf '%s [%s]: ' "$p" "$d" >"$TTY"; else printf '%s: ' "$p" >"$TTY"; fi; read -r a <"$TTY" || true; echo "${a:-$d}"; }

g "==> TAG Protect MODULE installer ($GH_REPO@$BRANCH)"
[ "$(id -u)" -eq 0 ] || { r "Run as root (sudo)."; exit 1; }
command -v fwconsole >/dev/null || { r "fwconsole not found - this must run on a FreePBX server."; exit 1; }
command -v git >/dev/null || (yum -y install git || apt-get -y update && apt-get -y install git) >/dev/null 2>&1 || true
command -v git >/dev/null || { r "git is required."; exit 1; }

# 1) download / update the repo
if [ -d "$SRC/.git" ]; then ( cd "$SRC" && git fetch --depth=1 origin "$BRANCH" && git reset --hard "origin/$BRANCH" )
else rm -rf "$SRC"; git clone --depth=1 -b "$BRANCH" "https://github.com/$GH_REPO.git" "$SRC"; fi

# 2) place the module + install it
g "==> installing module files ..."
mkdir -p "$MODDIR"
cp -a "$SRC/module/tagprotect/." "$MODDIR/"
find "$MODDIR" -type f \( -name '*.php' -o -name '*.txt' -o -name '*.xml' \) -exec sed -i 's/\r$//' {} \;
g "==> registering with FreePBX ..."
fwconsole ma install tagprotect 2>&1 | tail -6 || { r "module install failed"; exit 1; }

# 2b) sign the module locally so FreePBX trusts it (no "unsigned" banner)
g "==> signing module for this server ..."
bash "$SRC/lib/sign-module.sh" || y "  signing skipped (module still works; would show as unsigned)"

# 3) setup method
echo "" >"$TTY"
g "Choose setup method:" >/dev/null
printf '  [1] GUI  - configure later in the web UI (Connectivity -> TAG Protect)\n  [2] CLI  - enter settings now\n' >"$TTY"
METHOD="$(ask 'Setup method' '1')"

if [ "$METHOD" = "2" ]; then
  API_BASE="$(ask 'API base URL' 'https://callblocker.overcloud.us/api/v1')"
  while :; do
    APIKEY="$(ask 'TAG Protect API key (cbk_...)')"
    [ -n "$APIKEY" ] || { r "  key required"; continue; }
    RESP="$(curl -s --max-time 12 -H "X-API-Key: $APIKEY" -H 'Accept: application/json' "$API_BASE/lists" || true)"
    if echo "$RESP" | grep -q '"slug"'; then
      g "  key OK. Lists:"; echo "$RESP" | grep -oE '"slug":"[^"]*"' | sed 's/"slug":"/    - /; s/"//' >"$TTY"; break
    fi
    r "  rejected (keys are case-sensitive). Try again."
  done
  LISTS="$(ask 'Lists to enforce (all, or comma-separated slugs)' 'all')"
  REQ="$(ask 'Billing/location ID (UNIQUE per site - $20/mo each)')"
  CACHE="$(ask 'Cache lifetime seconds' '86400')"
  WIRE='0'; printf 'Screen INBOUND routes too? (y/n) [y]: ' >"$TTY"; read -r w <"$TTY" || true; case "${w:-y}" in y|Y|yes|YES) WIRE='1';; esac

  php "$SRC/module/cli-setup.php" --key="$APIKEY" --base="$API_BASE" --lists="$LISTS" --requester="$REQ" --cache="$CACHE" --wire="$WIRE"
  g "==> applying config ..."; fwconsole reload >/dev/null 2>&1 || true
  g "==> CLI setup complete."
else
  g "==> Module installed. Finish setup in the web UI:"
  printf '     Connectivity -> TAG Protect  (enter your API key, pick lists, set the billing ID)\n' >"$TTY"
fi

cat >"$TTY" <<DONE

$(g 'Done.')  Module page: Connectivity -> TAG Protect
  (If you see an "Unsigned Module" banner, that is normal for a self-hosted module.
   Remove it via Settings -> Advanced Settings -> "Module Signature Checking" -> No.)
DONE
