#!/usr/bin/env bash
# Self-sign the installed tagprotect module so FreePBX trusts it on THIS box
# (clears the "unsigned module" banner without disabling signature checking).
# Works with GnuPG 2.0 (Sangoma 7 / FreePBX 16) and 2.1+ (FreePBX 17). Idempotent. Run as root.
set -uo pipefail

KD=/home/asterisk/.gnupg
MOD=/var/www/html/admin/modules/tagprotect
HELPER="$(cd "$(dirname "$0")" && pwd)/sign-manifest.php"
UID2="TAG Protect Local Signing <tagprotect@localhost>"

command -v gpg >/dev/null || { echo "  gpg not found; skipping signing"; exit 0; }
[ -d "$MOD" ] || { echo "  module not installed; skipping"; exit 0; }

G(){ sudo -u asterisk env HOME=/home/asterisk gpg --homedir "$KD" --no-tty "$@"; }

# GnuPG capability: >= 2.1 supports --quick-generate-key and --pinentry-mode loopback
VER=$(gpg --version 2>/dev/null | head -1 | grep -oE '[0-9]+\.[0-9]+' | head -1)
MAJ=${VER%%.*}; MIN=${VER#*.}
modern=0; [ "${MAJ:-0}" -ge 2 ] && [ "${MIN:-0}" -ge 1 ] && modern=1

# 1. signing key (generate once, no passphrase so signing is non-interactive)
if ! G --list-keys "$UID2" >/dev/null 2>&1; then
  # Headless servers (esp. with GnuPG 2.0) block on /dev/random during key gen.
  # Provision an entropy source if the pool is low.
  if [ "$(cat /proc/sys/kernel/random/entropy_avail 2>/dev/null || echo 9999)" -lt 200 ] && ! pgrep -x haveged >/dev/null 2>&1; then
    echo "  low entropy - installing haveged for key generation ..."
    { yum -y install haveged || apt-get -y install haveged; } >/dev/null 2>&1 && systemctl enable --now haveged >/dev/null 2>&1 || true
    sleep 2
  fi
  if [ "$modern" = 1 ]; then
    G --batch --pinentry-mode loopback --passphrase "" --quick-generate-key "$UID2" rsa2048 sign 0 >/dev/null 2>&1
  else
    printf '%s\n' '%transient-key' 'Key-Type: RSA' 'Key-Length: 2048' \
      'Name-Real: TAG Protect Local Signing' 'Name-Email: tagprotect@localhost' \
      'Expire-Date: 0' '%commit' | G --batch --gen-key >/dev/null 2>&1
  fi
fi
FPR=$(G --list-keys --with-colons --with-fingerprint "$UID2" 2>/dev/null | awk -F: '/^fpr:/{print $10; exit}')
[ -n "$FPR" ] || { echo "  key generation failed (gpg ${VER:-?}); module left unsigned"; exit 0; }

# 2. trust it ultimately
echo "$FPR:6:" | G --import-ownertrust >/dev/null 2>&1

# 3. manifest + clearsign -> module.sig (temp file readable by the asterisk user)
TMP=$(mktemp); chmod 0644 "$TMP"
sudo -u asterisk php "$HELPER" "${FPR: -16}" > "$TMP" 2>/dev/null
[ -s "$TMP" ] || { echo "  manifest generation failed; skipping"; rm -f "$TMP"; exit 0; }
rm -f "$MOD/module.sig"
if [ "$modern" = 1 ]; then
  G --batch --yes --pinentry-mode loopback --passphrase "" --digest-algo SHA256 --clearsign --output "$MOD/module.sig" "$TMP" >/dev/null 2>&1
else
  G --batch --yes --digest-algo SHA256 --clearsign --output "$MOD/module.sig" "$TMP" >/dev/null 2>&1
fi
rm -f "$TMP"
[ -s "$MOD/module.sig" ] || { echo "  clearsign failed (gpg ${VER:-?}); module left unsigned"; exit 0; }
chown asterisk:asterisk "$MOD/module.sig"

# 4. re-verify, cache the result, clear the stale notification
php -r 'include "/etc/freepbx.conf";
  $r = module_functions::create()->updateSignature("tagprotect", false);
  if (($r["status"] ?? 0) & 128) { FreePBX::Notifications()->delete("freepbx","FW_UNSIGNED"); echo "  signed and trusted (status ".$r["status"].")\n"; }
  else { echo "  WARNING: signature not trusted (status ".($r["status"] ?? "?").")\n"; }' 2>/dev/null
