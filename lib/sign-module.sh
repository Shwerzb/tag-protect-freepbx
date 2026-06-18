#!/usr/bin/env bash
# Self-sign the installed tagprotect module so FreePBX trusts it on THIS box
# (clears the "unsigned module" banner without disabling signature checking).
# Idempotent: safe to re-run after updates. Run as root.
set -euo pipefail

KD=/home/asterisk/.gnupg
MOD=/var/www/html/admin/modules/tagprotect
HELPER="$(cd "$(dirname "$0")" && pwd)/sign-manifest.php"
UID2="TAG Protect Local Signing <tagprotect@localhost>"

command -v gpg >/dev/null || { echo "  gpg not found; skipping signing (module will show as unsigned)"; exit 0; }
[ -d "$MOD" ] || { echo "  module not installed; skipping signing"; exit 0; }

G(){ sudo -u asterisk env HOME=/home/asterisk gpg --homedir "$KD" "$@"; }

# 1. signing key (generate once, no passphrase so it is non-interactive)
if ! G --list-keys "$UID2" >/dev/null 2>&1; then
  G --batch --pinentry-mode loopback --passphrase "" --quick-generate-key "$UID2" rsa2048 sign 0 >/dev/null 2>&1
fi
FPR=$(G --list-keys --with-colons "$UID2" | awk -F: '/^fpr:/{print $10; exit}')
[ -n "$FPR" ] || { echo "  could not create signing key; skipping"; exit 0; }

# 2. trust it ultimately (so GPG reports the signature as trusted)
echo "$FPR:6:" | G --import-ownertrust >/dev/null 2>&1

# 3. build manifest + clearsign -> module.sig
TMP=$(mktemp)
sudo -u asterisk php "$HELPER" "${FPR: -16}" > "$TMP"
G --batch --yes --pinentry-mode loopback --passphrase "" --digest-algo SHA256 \
  --clearsign --output "$MOD/module.sig" "$TMP" >/dev/null 2>&1
rm -f "$TMP"
chown asterisk:asterisk "$MOD/module.sig"

# 4. re-verify, cache the result, and clear the stale notification
php -r 'include "/etc/freepbx.conf";
  $r = module_functions::create()->updateSignature("tagprotect", false);
  if (($r["status"] ?? 0) & 128) { FreePBX::Notifications()->delete("freepbx","FW_UNSIGNED"); echo "  signed and trusted (status ".$r["status"].")\n"; }
  else { echo "  WARNING: signature not trusted (status ".($r["status"] ?? "?").")\n"; }'
