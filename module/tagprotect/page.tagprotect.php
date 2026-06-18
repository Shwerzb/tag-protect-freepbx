<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

$tp = FreePBX::create()->Tagprotect;
$msg = '';
$testResult = null;

// ---- handle RESYNC allowed-numbers button ----
if (isset($_GET['tp_resync'])) {
	list($ok, $rmsg) = $tp->resyncAllow();
	$msg = $rmsg;
}

// ---- handle TEST button ----
if (isset($_POST['tp_test_btn'])) {
	$num = trim($_POST['tp_test_number'] ?? '');
	if ($num !== '') $testResult = $tp->testNumber($num);
}

// ---- handle SAVE ----
if (isset($_POST['submit']) || isset($_POST['Submit'])) {
	foreach (array('enabled','outbound') as $k) $tp->setConfig($k, (isset($_POST[$k]) && $_POST[$k]==='1') ? '1' : '0');
	foreach (array('base_url','api_key','requester','timeout_ms','cache_ttl') as $k) {
		if (isset($_POST[$k])) $tp->setConfig($k, trim($_POST[$k]));
	}
	// lists: multi-select (array) or text fallback. "all" wins; empty -> "all".
	if (isset($_POST['lists'])) {
		$v = $_POST['lists'];
		if (is_array($v)) {
			$v = array_map('trim', $v);
			$val = (in_array('all', $v) || empty($v)) ? 'all' : implode(',', array_filter($v));
			if ($val === '') $val = 'all';
		} else { $val = trim($v) === '' ? 'all' : trim($v); }
		$tp->setConfig('lists', $val);
	}
	if (isset($_POST['always_allow'])) $tp->writeAllow($_POST['always_allow']);

	// inbound per-route screening
	$want = isset($_POST['screen_routes']) && is_array($_POST['screen_routes']) ? $_POST['screen_routes'] : array();
	foreach ($tp->routes() as $r) {
		$on = in_array($r['key'], $want);
		if ($on && !$r['screened'])  $tp->wireRoute($r['extension'], $r['cidnum']);
		if (!$on && $r['screened'])  $tp->unwireRoute($r['extension'], $r['cidnum']);
	}
	$tp->writeConfFile();
	$tp->installFiles();
	needreload();
	$msg = _("Settings saved. Click the red 'Apply Config' to activate.");
}

$lists  = $tp->fetchLists();           // live list of available TAG lists (may be empty if key unset)
$routes = $tp->routes();

echo load_view(__DIR__.'/views/main.php', array(
	'tp'=>$tp, 'msg'=>$msg, 'testResult'=>$testResult, 'lists'=>$lists, 'routes'=>$routes,
));
