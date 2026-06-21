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
	$testExt = trim($_POST['tp_test_ext'] ?? '');
	if ($num !== '') {
		// If an extension was supplied, look up its COS lists override
		$testLists = null;
		if ($testExt !== '') {
			$cosMap  = @json_decode(@file_get_contents('/etc/asterisk/tagprotect-cosmap.json'), true);
			$cosConf = @json_decode(@file_get_contents('/etc/asterisk/tagprotect-cos.json'), true);
			if (is_array($cosMap) && isset($cosMap[$testExt]) &&
			    is_array($cosConf) && isset($cosConf[$cosMap[$testExt]]) &&
			    $cosConf[$cosMap[$testExt]] !== '') {
				$testLists = $cosConf[$cosMap[$testExt]];
			}
		}
		$testResult = $tp->testNumber($num, $testLists);
		if ($testExt !== '') $testResult['_ext'] = $testExt;
		if (isset($cosMap[$testExt])) $testResult['_group'] = $cosMap[$testExt];
		if ($testLists) $testResult['_lists'] = $testLists;
	}
}

// ---- handle SAVE ----
if (isset($_POST['submit']) || isset($_POST['Submit'])) {
	foreach (array('enabled','outbound') as $k) $tp->setConfig($k, (isset($_POST[$k]) && $_POST[$k]==='1') ? '1' : '0');
	foreach (array('base_url','api_key','requester','timeout_ms','cache_ttl','blocked_sound') as $k) {
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

	// per-COS-group list overrides
	if (isset($_POST['cos_group_lists']) && is_array($_POST['cos_group_lists'])) {
		$cosConfig = array();
		foreach ($_POST['cos_group_lists'] as $gname => $val) {
			$gname = trim($gname);
			if ($gname === '') continue;
			if (is_array($val)) {
				$val = array_map('trim', $val);
				// empty first element ("-- Use global --") means no override
				$val = array_filter($val, function($x){ return $x !== ''; });
				if (empty($val)) { $cosConfig[$gname] = ''; continue; }
				$str = (in_array('all', $val)) ? 'all' : implode(',', $val);
			} else {
				$str = trim($val);
			}
			$cosConfig[$gname] = $str;
		}
		$tp->setCosGroupConfig($cosConfig);
	}

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

// Fetch system recordings for blocked-sound selector
$recordings = array();
try {
    $db2 = FreePBX::create()->Database;
    $rs = $db2->prepare('SELECT id, displayname, CAST(filename AS CHAR) AS fn FROM recordings ORDER BY displayname');
    $rs->execute();
    $recordings = $rs->fetchAll(\PDO::FETCH_ASSOC);
} catch (Exception $e) {}

echo load_view(__DIR__.'/views/main.php', array(
	'tp'=>$tp, 'msg'=>$msg, 'testResult'=>$testResult, 'lists'=>$lists, 'routes'=>$routes, 'recordings'=>$recordings,
));
