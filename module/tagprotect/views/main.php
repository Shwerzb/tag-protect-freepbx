<?php if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
$slugs = array();
foreach ($lists as $l) { $slugs[] = $l['slug'].' ('.$l['number_count'].')'; }
?>
<div class="container-fluid">
  <h1><i class="fa fa-shield"></i> <?php echo _("TAG Protect"); ?></h1>

  <?php if (!empty($msg)): ?>
    <div class="alert alert-success"><?php echo $msg; ?></div>
  <?php endif; ?>
  <?php if (empty($tp->get('api_key'))): ?>
    <div class="alert alert-warning"><?php echo _("No API key set yet. Enter your TAG Protect API key below and Submit."); ?></div>
  <?php endif; ?>

  <!-- ===================== TEST TOOL ===================== -->
  <form class="" method="post" action="config.php?display=tagprotect" style="margin-bottom:15px;">
    <div class="row"><div class="col-md-12">
      <div class="panel panel-default"><div class="panel-body">
        <strong><?php echo _("Test a number"); ?></strong>
        <div class="input-group" style="max-width:480px;margin-top:6px;">
          <input type="text" class="form-control" name="tp_test_number" placeholder="e.g. 8454141100" value="<?php echo htmlspecialchars($_POST['tp_test_number'] ?? ''); ?>">
          <span class="input-group-btn"><button class="btn btn-default" type="submit" name="tp_test_btn" value="1"><?php echo _("Check"); ?></button></span>
        </div>
        <?php if ($testResult !== null):
          $st = $testResult['status'] ?? 'error';
          $cls = $st==='blocked' ? 'danger' : ($st==='approved' ? 'success' : 'warning'); ?>
          <div class="alert alert-<?php echo $cls; ?>" style="margin-top:8px;">
            <strong><?php echo strtoupper(htmlspecialchars($st)); ?></strong>
            — <?php echo htmlspecialchars($testResult['message'] ?? ''); ?>
            <?php if (!empty($testResult['blocked_in_lists'])) echo ' ['.htmlspecialchars(implode(', ',$testResult['blocked_in_lists'])).']'; ?>
          </div>
        <?php endif; ?>
      </div></div>
    </div></div>
  </form>

  <!-- ===================== SETTINGS ===================== -->
  <form class="fpbx-submit" name="tagprotectform" id="tagprotectform" method="post" action="config.php?display=tagprotect">
    <input type="hidden" name="submit" value="1">

    <!-- General -->
    <div class="element-container"><div class="row"><div class="col-md-12">
      <div class="row"><div class="col-md-3"><label class="control-label"><?php echo _("Enabled"); ?></label></div>
        <div class="col-md-9">
          <span class="radioset">
            <input type="radio" name="enabled" id="enabled_yes" value="1" <?php echo $tp->get('enabled')==='1'?'checked':''; ?>><label for="enabled_yes"><?php echo _("Yes"); ?></label>
            <input type="radio" name="enabled" id="enabled_no"  value="0" <?php echo $tp->get('enabled')!=='1'?'checked':''; ?>><label for="enabled_no"><?php echo _("No"); ?></label>
          </span>
          <span class="help-block"><?php echo _("Master on/off for all screening."); ?></span></div></div>
    </div></div></div>

    <div class="element-container"><div class="row"><div class="col-md-12">
      <div class="row"><div class="col-md-3"><label class="control-label"><?php echo _("Screen outbound calls"); ?></label></div>
        <div class="col-md-9">
          <span class="radioset">
            <input type="radio" name="outbound" id="outbound_yes" value="1" <?php echo $tp->get('outbound')==='1'?'checked':''; ?>><label for="outbound_yes"><?php echo _("Yes"); ?></label>
            <input type="radio" name="outbound" id="outbound_no"  value="0" <?php echo $tp->get('outbound')!=='1'?'checked':''; ?>><label for="outbound_no"><?php echo _("No"); ?></label>
          </span>
          <span class="help-block"><?php echo _("Block numbers your users dial out."); ?></span></div></div>
    </div></div></div>

    <!-- API -->
    <?php
      $fields = array(
        'api_key'   => array(_("API key"), _("Your cbk_... key (case-sensitive).")),
        'requester' => array(_("Billing / location ID"), _("Unique per site - TAG bills \$20/mo per unique ID.")),
        'lists'     => array(_("Lists to enforce"), _("Select one or more lists (Ctrl/Cmd-click), or 'All lists'.")),
        'cache_ttl' => array(_("Cache lifetime (sec)"), _("86400 = 24h. Repeat numbers are answered from cache.")),
        'timeout_ms'=> array(_("API timeout (ms)"), _("Fail-open if exceeded.")),
        'allow_url' => array(_("Always-allow resync URL"), _("Raw URL of the shared always-allow list (e.g. on GitHub).")),
        'base_url'  => array(_("API base URL"), _("Leave default unless told otherwise.")),
      );
      foreach ($fields as $k=>$f): ?>
      <div class="element-container"><div class="row"><div class="col-md-12">
        <div class="row">
          <div class="col-md-3"><label class="control-label" for="<?php echo $k; ?>"><?php echo $f[0]; ?></label></div>
          <div class="col-md-9">
            <?php if ($k === 'api_key'): ?>
              <div class="input-group">
                <input type="password" class="form-control" id="api_key" name="api_key" autocomplete="off" value="<?php echo htmlspecialchars($tp->get($k)); ?>">
                <span class="input-group-btn">
                  <button class="btn btn-default" type="button" id="tp_key_toggle" onclick="tpToggleKey()" title="<?php echo _('Show/Hide'); ?>"><i class="fa fa-eye"></i></button>
                  <button class="btn btn-default" type="button" onclick="tpCopyKey(this)" title="<?php echo _('Copy'); ?>"><i class="fa fa-copy"></i></button>
                </span>
              </div>
            <?php elseif ($k === 'lists' && !empty($lists)):
              $cur = array_map('trim', explode(',', $tp->get('lists'))); $isAll = in_array('all', $cur); ?>
              <select class="form-control" name="lists[]" multiple size="<?php echo min(8, count($lists)+1); ?>">
                <option value="all" <?php echo $isAll?'selected':''; ?>><?php echo _("All lists"); ?></option>
                <?php foreach ($lists as $l): ?>
                  <option value="<?php echo htmlspecialchars($l['slug']); ?>" <?php echo (!$isAll && in_array($l['slug'],$cur))?'selected':''; ?>>
                    <?php echo htmlspecialchars($l['name'].' ('.$l['slug'].', '.$l['number_count'].')'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input type="text" class="form-control" id="<?php echo $k; ?>" name="<?php echo $k; ?>" value="<?php echo htmlspecialchars($tp->get($k)); ?>">
            <?php endif; ?>
            <span class="help-block"><?php echo $f[1]; ?></span>
          </div>
        </div>
      </div></div></div>
    <?php endforeach; ?>

    <!-- Always-allow -->
    <div class="element-container"><div class="row"><div class="col-md-12">
      <div class="row"><div class="col-md-3"><label class="control-label"><?php echo _("Always-allow list"); ?></label></div>
        <div class="col-md-9">
          <a class="btn btn-default btn-sm" href="config.php?display=tagprotect&amp;tp_resync=1" style="margin-bottom:6px;"
             onclick="return confirm('<?php echo _('Replace the list below with the shared list from the resync URL? Unsaved edits will be lost.'); ?>');">
            <i class="fa fa-refresh"></i> <?php echo _("Resync allowed numbers"); ?>
          </a>
          <textarea class="form-control" name="always_allow" rows="10" style="font-family:monospace;"><?php echo htmlspecialchars($tp->readAllow()); ?></textarea>
          <span class="help-block"><?php echo _("Numbers that are NEVER blocked (911, Hatzalah, etc). One per line; # for comments. 'Resync' pulls the shared list from the URL in settings."); ?></span>
        </div></div>
    </div></div></div>

    <!-- Inbound routes -->
    <div class="element-container"><div class="row"><div class="col-md-12">
      <div class="row"><div class="col-md-3"><label class="control-label"><?php echo _("Screen inbound routes"); ?></label></div>
        <div class="col-md-9">
          <?php if (empty($routes)): ?>
            <span class="help-block"><?php echo _("No inbound routes found."); ?></span>
          <?php else: ?>
            <table class="table table-striped" style="max-width:640px;">
              <thead><tr><th></th><th><?php echo _("DID"); ?></th><th><?php echo _("CID"); ?></th><th><?php echo _("Description"); ?></th></tr></thead>
              <tbody>
              <?php foreach ($routes as $r): ?>
                <tr>
                  <td><input type="checkbox" name="screen_routes[]" value="<?php echo htmlspecialchars($r['key']); ?>" <?php echo $r['screened']?'checked':''; ?>></td>
                  <td><?php echo htmlspecialchars($r['extension']!==''?$r['extension']:_('(any)')); ?></td>
                  <td><?php echo htmlspecialchars($r['cidnum']!==''?$r['cidnum']:_('(any)')); ?></td>
                  <td><?php echo htmlspecialchars($r['description']); ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            <span class="help-block"><?php echo _("Checked routes send incoming calls through screening, then on to their normal destination."); ?></span>
          <?php endif; ?>
        </div></div>
    </div></div></div>

    <div class="element-container"><div class="row"><div class="col-md-12">
      <button type="submit" class="btn btn-primary"><?php echo _("Submit"); ?></button>
    </div></div></div>
  </form>
</div>
<script type="text/javascript">
function tpToggleKey(){
  var i=document.getElementById('api_key'), b=document.getElementById('tp_key_toggle');
  if(i.type==='password'){ i.type='text'; b.innerHTML='<i class="fa fa-eye-slash"></i>'; }
  else { i.type='password'; b.innerHTML='<i class="fa fa-eye"></i>'; }
}
function tpCopyKey(btn){
  var i=document.getElementById('api_key'), val=i.value;
  var done=function(){ var o=btn.innerHTML; btn.innerHTML='<i class="fa fa-check"></i>'; setTimeout(function(){btn.innerHTML=o;},1200); };
  if(navigator.clipboard && navigator.clipboard.writeText){ navigator.clipboard.writeText(val).then(done, function(){tpFallbackCopy(i);done();}); }
  else { tpFallbackCopy(i); done(); }
}
function tpFallbackCopy(i){ var t=i.type; i.type='text'; i.select(); try{document.execCommand('copy');}catch(e){} i.type=t; window.getSelection().removeAllRanges(); }
</script>
