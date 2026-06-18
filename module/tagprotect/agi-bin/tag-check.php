#!/usr/bin/env php
<?php
/**
 * TAG Protect call-screening AGI for FreePBX / Asterisk (chan_pjsip).
 *
 *   AGI(tag-check.php,<in|out>,<number>[,<requester>])
 *
 * Sets channel var TAGPROTECT_BLOCKED = "1" (block) / "0" (allow).
 * Order: emergency/always-allow  ->  local cache  ->  TAG API (fail-open).
 */
error_reporting(E_ERROR | E_PARSE);

$CONFIG_FILE = '/etc/asterisk/tagprotect.conf';
$ALLOW_FILES = array(                       // checked in order; all merged
    '/etc/asterisk/tagprotect-emergency.txt',        // shipped list (resynced from git)
    '/etc/asterisk/tagprotect-emergency.local.txt',  // your local additions (never overwritten)
);

while (($l = fgets(STDIN)) !== false) { if (trim($l) === '') break; }   // consume AGI env
function agi_cmd($c){ fwrite(STDOUT, $c."\n"); fflush(STDOUT); return fgets(STDIN); }
function agi_set($n,$v){ agi_cmd('SET VARIABLE "'.$n.'" "'.$v.'"'); }
function agi_log($m){ agi_cmd('VERBOSE "TAGProtect: '.str_replace('"',"'",$m).'" 1'); }
function decide($b){ agi_set('TAGPROTECT_BLOCKED', $b ? '1':'0'); exit(0); }

$direction = isset($argv[1]) ? $argv[1] : 'out';
$number    = isset($argv[2]) ? $argv[2] : '';
$reqOv     = isset($argv[3]) ? $argv[3] : '';
$digits    = preg_replace('/\D/','',$number);
if ($digits === '') { agi_log('empty number -> allow'); decide(false); }

$cfg = @parse_ini_file($CONFIG_FILE);
if (!$cfg || empty($cfg['api_key'])) { agi_log('no config -> fail-open allow'); decide(false); }
$apiKey    = $cfg['api_key'];
$baseUrl   = !empty($cfg['base_url'])   ? rtrim($cfg['base_url'],'/') : 'https://callblocker.overcloud.us/api/v1';
$requester = $reqOv !== ''              ? $reqOv : (!empty($cfg['requester']) ? $cfg['requester'] : 'default');
$lists     = !empty($cfg['lists'])      ? $cfg['lists'] : 'all';
$timeoutMs = !empty($cfg['timeout_ms']) ? (int)$cfg['timeout_ms'] : 1500;
$cacheTtl  = isset($cfg['cache_ttl'])   ? (int)$cfg['cache_ttl'] : 86400;
$cacheDb   = !empty($cfg['cache_db'])   ? $cfg['cache_db'] : '/var/lib/asterisk/tagprotect/cache.db';

/* 1) always-allow (emergency) - never block; exact normalized match, +1 aware */
$set = array();
foreach ($ALLOW_FILES as $f) {
    $lines = @file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) continue;
    foreach ($lines as $e) {
        $e = trim($e); if ($e === '' || $e[0] === '#') continue;
        $ed = preg_replace('/\D/','',$e); if ($ed !== '') $set[$ed] = true;
    }
}
if ($set) {
    $cands = array($digits);
    if (strlen($digits)===11 && $digits[0]==='1') $cands[] = substr($digits,1);
    if (strlen($digits)===10) $cands[] = '1'.$digits;
    foreach ($cands as $cd) if (isset($set[$cd])) { agi_log("always-allow ($number)"); decide(false); }
}

/* 2) local cache */
$db = null;
try {
    if (class_exists('SQLite3')) {
        $dir = dirname($cacheDb); if (!is_dir($dir)) @mkdir($dir,0775,true);
        $db = new SQLite3($cacheDb); $db->busyTimeout(1000);
        $db->exec('CREATE TABLE IF NOT EXISTS cache (num TEXT PRIMARY KEY, blocked INTEGER, ts INTEGER)');
        $st = $db->prepare('SELECT blocked,ts FROM cache WHERE num=:n'); $st->bindValue(':n',$digits,SQLITE3_TEXT);
        $row = $st->execute()->fetchArray(SQLITE3_ASSOC);
        if ($row && (time()-(int)$row['ts']) < $cacheTtl) { agi_log("cache hit ($number) blocked=".$row['blocked']); decide(((int)$row['blocked'])===1); }
    }
} catch (Exception $e) { $db = null; }

/* 3) TAG API (fail-open) */
$listArr = array_map('trim', explode(',', $lists));
$payload = json_encode(array('number'=>$number,'lists'=>$listArr,'requester'=>$requester));
$ch = curl_init($baseUrl.'/lists/check');
curl_setopt_array($ch, array(CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload, CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_TIMEOUT_MS=>$timeoutMs, CURLOPT_CONNECTTIMEOUT_MS=>$timeoutMs,
    CURLOPT_HTTPHEADER=>array('Content-Type: application/json','Accept: application/json','X-API-Key: '.$apiKey)));
$resp = curl_exec($ch); $err = curl_error($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
if ($resp===false || $err!=='' || $code<200 || $code>=300) { agi_log("API error (code=$code) -> fail-open allow"); decide(false); }
$j = json_decode($resp,true); $status = isset($j['data']['status']) ? $j['data']['status'] : null;
if ($status==='blocked') $blocked=true; elseif ($status==='approved') $blocked=false;
else { agi_log('unexpected response -> allow'); decide(false); }

if ($db) { try {
    $st=$db->prepare('INSERT OR REPLACE INTO cache (num,blocked,ts) VALUES (:n,:b,:t)');
    $st->bindValue(':n',$digits,SQLITE3_TEXT); $st->bindValue(':b',$blocked?1:0,SQLITE3_INTEGER);
    $st->bindValue(':t',time(),SQLITE3_INTEGER); $st->execute();
} catch (Exception $e) {} }
agi_log(($blocked?'BLOCKED':'approved')." $direction $number (lists=$lists)");
decide($blocked);
