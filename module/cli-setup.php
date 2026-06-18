#!/usr/bin/env php
<?php
/**
 * CLI setup for the TAG Protect FreePBX module (called by install-module.sh).
 *   php cli-setup.php --key=cbk_.. --base=URL --lists=all --requester=site1 --cache=86400 --wire=1
 * Writes settings into the module's config, regenerates files, and (optionally) wires inbound routes.
 */
$bootstrap_settings['freepbx_auth'] = false;
if (!@include '/etc/freepbx.conf') { fwrite(STDERR, "ERR: not a FreePBX server\n"); exit(2); }
$o = getopt('', array('key:','base:','lists:','requester:','cache:','timeout:','wire:'));
$tp = FreePBX::create()->Tagprotect;

$map = array('key'=>'api_key','base'=>'base_url','lists'=>'lists','requester'=>'requester','cache'=>'cache_ttl','timeout'=>'timeout_ms');
foreach ($map as $opt=>$cfg) if (isset($o[$opt]) && $o[$opt] !== '') $tp->setConfig($cfg, $o[$opt]);
$tp->setConfig('enabled', '1');
$tp->setConfig('outbound', '1');

$tp->writeConfFile();
$tp->installFiles();

if (($o['wire'] ?? '0') === '1') {
	foreach ($tp->routes() as $r) if (!$r['screened']) $tp->wireRoute($r['extension'], $r['cidnum']);
	echo "inbound routes wired\n";
}
echo "config written (requester=".$tp->get('requester').", lists=".$tp->get('lists').")\n";
