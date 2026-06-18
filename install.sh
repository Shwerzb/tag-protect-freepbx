#!/usr/bin/env bash
#
# TAG Protect for FreePBX - one-command installer.
#
#   curl -fsSL https://raw.githubusercontent.com/Shwerzb/tag-protect-freepbx/main/install.sh | sudo bash
#
# Downloads the addon to /usr/local/lib/tag-protect-freepbx and runs the interactive setup.
#
set -euo pipefail

# Repo to install from (override with: export TAGPROTECT_REPO=user/repo)
GH_REPO="${TAGPROTECT_REPO:-Shwerzb/tag-protect-freepbx}"
BRANCH="${TAGPROTECT_BRANCH:-main}"
DEST=/usr/local/lib/tag-protect-freepbx

echo "==> TAG Protect installer (repo: $GH_REPO, branch: $BRANCH)"
[ "$(id -u)" -eq 0 ] || { echo "Please run as root (sudo)."; exit 1; }

if ! command -v git >/dev/null; then
  echo "==> installing git ..."
  (yum -y install git || dnf -y install git || apt-get -y update && apt-get -y install git) >/dev/null 2>&1 || true
fi
command -v git >/dev/null || { echo "git is required but could not be installed."; exit 1; }

if [ -d "$DEST/.git" ]; then
  echo "==> updating existing copy ..."
  git -C "$DEST" fetch --depth=1 origin "$BRANCH"
  git -C "$DEST" reset --hard "origin/$BRANCH"
else
  echo "==> downloading addon ..."
  rm -rf "$DEST"
  git clone --depth=1 -b "$BRANCH" "https://github.com/$GH_REPO.git" "$DEST"
fi

# record repo for the 'tagprotect' CLI (resync/update)
printf 'GH_REPO=%s\nBRANCH=%s\n' "$GH_REPO" "$BRANCH" > "$DEST/.repo-info"

exec bash "$DEST/lib/setup.sh" "$DEST"
