<?php
/* Build the module.sig manifest for the tagprotect module. Each hash is computed
   against exactly the destination FreePBX's verifyModule() checks, guaranteeing a match.
   Usage: php sign-manifest.php <signing-keyid>   (prints manifest to stdout) */
$bootstrap_settings['freepbx_auth'] = false;
include '/etc/freepbx.conf';

$mod   = 'tagprotect';
$keyid = isset($argv[1]) ? $argv[1] : '';
$dir   = \FreePBX::Config()->get('AMPWEBROOT')."/admin/modules/$mod";

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
$hashes = array();
foreach ($it as $f) {
    if (!$f->isFile()) continue;
    $rel = str_replace('\\', '/', ltrim(substr($f->getPathname(), strlen($dir)), '/\\'));
    if ($rel === 'module.sig') continue;
    try { $dest = \FreePBX::Installer()->getDestination($mod, $rel, true); }
    catch (\Exception $e) { continue; }
    if ($dest === false || $dest === null || !is_file($dest)) continue;
    $hashes[$rel] = hash_file('sha256', $dest);
}
ksort($hashes);

echo ";################################################\n";
echo ";#        TAG Protect Module Signature          #\n";
echo ";################################################\n";
echo "[config]\nversion=1\nhash=sha256\nsignedwith=$keyid\n";
echo "signedby='TAG Protect Local Signing'\nrepo=local\n[hashes]\n";
foreach ($hashes as $file => $h) echo "$file = $h\n";
echo ";# End\n";
