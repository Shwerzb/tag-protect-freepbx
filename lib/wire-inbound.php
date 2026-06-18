#!/usr/bin/env php
<?php
/**
 * TAG Protect - automatic INBOUND wiring for FreePBX.
 *
 *   php wire-inbound.php status   # show each inbound route + whether it's screened
 *   php wire-inbound.php wire      # route every inbound route through the screener
 *   php wire-inbound.php unwire    # restore every route to its original destination
 *
 * Mechanism (the same thing the GUI does): for each inbound route it creates a
 * Custom Destination whose Target is "tag-inbound-screen,s,1" and whose Return
 * destination is the route's ORIGINAL destination, then points the route at it.
 * Idempotent: a route already screened is left alone; unwire restores the original.
 *
 * After wiring/unwiring, run:  fwconsole reload
 */
$bootstrap_settings['freepbx_auth'] = false;
if (!@include '/etc/freepbx.conf') { fwrite(STDERR, "ERR: /etc/freepbx.conf not found - run this on the FreePBX server.\n"); exit(2); }

$SCREEN = 'tag-inbound-screen,s,1';
$KV     = 'kvstore_FreePBX_modules_Customappsreg';
$db     = FreePBX::Database();
$action = isset($argv[1]) ? $argv[1] : 'status';

function loadDests($db,$KV){
    $out = array();
    try { $rows = $db->query("SELECT `key`,`val` FROM `$KV` WHERE `id`='dests'")->fetchAll(PDO::FETCH_ASSOC); }
    catch (Exception $e) { return $out; }
    foreach ($rows as $r) $out[$r['key']] = json_decode($r['val'], true);
    return $out;
}
function nextId($d){ $m=0; foreach (array_keys($d) as $k) if (is_numeric($k) && (int)$k>$m) $m=(int)$k; return $m+1; }
function findDest($d,$target,$dest){ foreach ($d as $id=>$v){ if (($v['target']??'')===$target && ($v['dest']??'')===$dest) return $id; } return null; }
function addDest($db,$KV,$target,$dest,$desc){
    $d = loadDests($db,$KV); $id = nextId($d);
    $json = json_encode(array('destid'=>(string)$id,'target'=>$target,'description'=>$desc,'notes'=>'','destret'=>'1','dest'=>$dest));
    $st = $db->prepare("INSERT INTO `$KV` (`key`,`val`,`type`,`id`) VALUES (?,?,'json-arr','dests')");
    $st->execute(array((string)$id,$json));
    return $id;
}
function isScreened($cur,$d,$SCREEN){
    if (preg_match('/^customdests,dest-(\d+),1$/',$cur,$m) && isset($d[$m[1]]) && ($d[$m[1]]['target']??'')===$SCREEN) return $m[1];
    return false;
}

$routes = $db->query("SELECT extension,cidnum,destination,description FROM incoming")->fetchAll(PDO::FETCH_ASSOC);
$d = loadDests($db,$KV);

if ($action === 'status') {
    if (!$routes) { echo "  (no inbound routes defined)\n"; exit(0); }
    foreach ($routes as $r) {
        $s = isScreened($r['destination'],$d,$SCREEN);
        printf("  DID='%s' CID='%s' -> %s  [%s]\n", $r['extension']?:'(any)', $r['cidnum']?:'(any)', $r['destination'], $s!==false?'SCREENED':'not screened');
    }
    exit(0);
}

if ($action === 'wire') {
    $changed = 0;
    foreach ($routes as $r) {
        $cur = $r['destination'];
        if (isScreened($cur,$d,$SCREEN) !== false) { echo "  already screened: DID='{$r['extension']}' CID='{$r['cidnum']}'\n"; continue; }
        $id = findDest($d,$SCREEN,$cur);
        if ($id === null) { $id = addDest($db,$KV,$SCREEN,$cur,"TAG screen -> $cur"); $d = loadDests($db,$KV); }
        $st = $db->prepare("UPDATE incoming SET destination=? WHERE extension=? AND COALESCE(cidnum,'')=COALESCE(?,'')");
        $st->execute(array("customdests,dest-$id,1",$r['extension'],$r['cidnum']));
        echo "  WIRED DID='{$r['extension']}' CID='{$r['cidnum']}': was '$cur' -> customdests,dest-$id,1\n";
        $changed++;
    }
    echo "  $changed route(s) changed.\n";
    exit(0);
}

if ($action === 'unwire') {
    $changed = 0;
    foreach ($routes as $r) {
        $id = isScreened($r['destination'],$d,$SCREEN);
        if ($id === false) continue;
        $orig = $d[$id]['dest'] ?? 'app-blackhole,hangup,1';
        $st = $db->prepare("UPDATE incoming SET destination=? WHERE extension=? AND COALESCE(cidnum,'')=COALESCE(?,'')");
        $st->execute(array($orig,$r['extension'],$r['cidnum']));
        echo "  UNWIRED DID='{$r['extension']}' -> restored '$orig'\n";
        $changed++;
    }
    echo "  $changed route(s) restored.\n";
    exit(0);
}

fwrite(STDERR, "usage: php wire-inbound.php <status|wire|unwire>\n");
exit(2);
