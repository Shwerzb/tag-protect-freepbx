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
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;max-width:640px;">
          <input type="text" class="form-control" name="tp_test_number" placeholder="<?php echo _('Number, e.g. 8454141100'); ?>"
                 value="<?php echo htmlspecialchars($_POST['tp_test_number'] ?? ''); ?>" style="flex:2;min-width:160px;">
          <input type="text" class="form-control" name="tp_test_ext" placeholder="<?php echo _('Ext (optional)'); ?>"
                 value="<?php echo htmlspecialchars($_POST['tp_test_ext'] ?? ''); ?>" style="flex:1;min-width:90px;max-width:130px;"
                 title="<?php echo _('Enter an extension to test with that extension\'s COS list restrictions'); ?>">
          <button class="btn btn-default" type="submit" name="tp_test_btn" value="1"><?php echo _("Check"); ?></button>
        </div>
        <span class="help-block" style="margin-top:3px;"><?php echo _("Leave Ext blank to test against global lists. Enter an extension to test exactly as that phone would be screened."); ?></span>
        <?php if ($testResult !== null):
          $st  = $testResult['status'] ?? 'error';
          $cls = $st==='blocked' ? 'danger' : ($st==='approved' ? 'success' : 'warning');
          $ctx = '';
          if (!empty($testResult['_ext'])) {
              $grp = $testResult['_group'] ?? '(no COS group)';
              $lst = $testResult['_lists'] ?? null;
              $ctx = ' &nbsp;<small class="text-muted">ext '
                   . htmlspecialchars($testResult['_ext']) . ' &rarr; <em>'
                   . htmlspecialchars($grp) . '</em>'
                   . ($lst ? ' ['.htmlspecialchars($lst).']' : ' [global lists]')
                   . '</small>';
          } ?>
          <div class="alert alert-<?php echo $cls; ?>" style="margin-top:8px;">
            <strong><?php echo strtoupper(htmlspecialchars($st)); ?></strong>
            — <?php echo htmlspecialchars($testResult['message'] ?? ''); ?>
            <?php if (!empty($testResult['blocked_in_lists'])) echo ' ['.htmlspecialchars(implode(', ',$testResult['blocked_in_lists'])).']'; ?>
            <?php echo $ctx; ?>
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
        'lists'     => array(_("Lists to enforce"), _("Check one or more lists, or check 'All lists'.")),
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
              <div style="display:flex;flex-wrap:wrap;gap:6px 18px;padding:4px 0;">
                <label style="display:flex;align-items:center;gap:5px;font-weight:bold;margin:0;cursor:pointer;">
                  <input type="checkbox" id="tp_lists_all" name="lists[]" value="all" <?php echo $isAll?'checked':''; ?>>
                  <?php echo _("All lists"); ?>
                </label>
                <span style="border-left:1px solid #ccc;margin:0 4px;"></span>
                <?php foreach ($lists as $l): ?>
                <label style="display:flex;align-items:center;gap:5px;font-weight:normal;margin:0;cursor:pointer;">
                  <input type="checkbox" class="tp-list-item" name="lists[]"
                         value="<?php echo htmlspecialchars($l['slug']); ?>"
                         <?php echo (!$isAll && in_array($l['slug'],$cur))?'checked':''; ?>>
                  <?php echo htmlspecialchars($l['name']); ?>
                  <small class="text-muted">(<?php echo number_format((int)$l['number_count']); ?>)</small>
                </label>
                <?php endforeach; ?>
              </div>
              <script>
              (function(){
                var allBox = document.getElementById('tp_lists_all');
                function syncGlobal() {
                  document.querySelectorAll('.tp-list-item').forEach(function(cb){
                    cb.disabled = allBox.checked;
                    if (allBox.checked) cb.checked = false;
                  });
                }
                syncGlobal();
                allBox.addEventListener('change', syncGlobal);
              })();
              </script>
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

    <!-- COS per-group list overrides -->
    <?php $cosGroups = $tp->cosGroups(); $cosConfig = $tp->getCosGroupConfig(); ?>
    <?php if (!empty($cosGroups)): ?>
    <div class="element-container"><div class="row"><div class="col-md-12">
      <div class="row">
        <div class="col-md-3"><label class="control-label"><?php echo _("Lists by Class of Service"); ?></label></div>
        <div class="col-md-9">
          <p class="help-block" style="margin-bottom:10px;">
            <?php echo _("Choose which TAG lists to enforce per COS group. Check one or more lists, or check <strong>All lists</strong>. Leave everything unchecked to fall back to the global setting above."); ?>
          </p>
          <table class="table table-bordered" style="max-width:800px;">
            <thead><tr>
              <th style="width:160px;"><?php echo _("COS Group"); ?></th>
              <th><?php echo _("TAG Lists (check one or more)"); ?></th>
              <th style="width:70px;"><?php echo _("Members"); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($cosGroups as $cg):
              $gname  = $cg['name'];
              $curVal = isset($cosConfig[$gname]) ? $cosConfig[$gname] : '';
              $curArr = $curVal !== '' ? array_map('trim', explode(',', $curVal)) : array();
              $isAll  = in_array('all', $curArr);
              $slug   = 'cos_'.preg_replace('/[^a-z0-9]/i','_', $gname);
            ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($gname); ?></strong></td>
                <td>
                  <?php if (!empty($lists)): ?>
                  <div style="display:flex;flex-wrap:wrap;gap:6px 18px;padding:4px 0;">
                    <!-- "All lists" checkbox -->
                    <label style="display:flex;align-items:center;gap:5px;font-weight:bold;margin:0;cursor:pointer;"
                           title="<?php echo _('Block against every available list'); ?>">
                      <input type="checkbox"
                             class="tp-cos-all"
                             data-group="<?php echo htmlspecialchars($slug); ?>"
                             name="cos_group_lists[<?php echo htmlspecialchars($gname); ?>][]"
                             value="all"
                             <?php echo $isAll ? 'checked' : ''; ?>>
                      <?php echo _("All lists"); ?>
                    </label>
                    <span style="border-left:1px solid #ccc;margin:0 4px;"></span>
                    <!-- per-list checkboxes -->
                    <?php foreach ($lists as $l): ?>
                    <label style="display:flex;align-items:center;gap:5px;font-weight:normal;margin:0;cursor:pointer;">
                      <input type="checkbox"
                             class="tp-cos-item tp-cos-grp-<?php echo htmlspecialchars($slug); ?>"
                             name="cos_group_lists[<?php echo htmlspecialchars($gname); ?>][]"
                             value="<?php echo htmlspecialchars($l['slug']); ?>"
                             <?php echo (!$isAll && in_array($l['slug'], $curArr)) ? 'checked' : ''; ?>>
                      <?php echo htmlspecialchars($l['name']); ?>
                      <small class="text-muted">(<?php echo number_format((int)$l['number_count']); ?>)</small>
                    </label>
                    <?php endforeach; ?>
                  </div>
                  <div style="margin-top:4px;font-size:11px;color:#999;">
                    <?php echo _("Uncheck everything to use the global setting."); ?>
                  </div>
                  <?php else: ?>
                  <input type="text" class="form-control" style="max-width:280px;"
                         name="cos_group_lists[<?php echo htmlspecialchars($gname); ?>]"
                         value="<?php echo htmlspecialchars($curVal); ?>"
                         placeholder="<?php echo _('slug1,slug2  or  all  (blank = global)'); ?>">
                  <?php endif; ?>
                </td>
                <td style="text-align:center;vertical-align:middle;">
                  <small><?php echo (int)$cg['memberCount']; ?></small>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <span class="help-block"><?php echo _("Only affects outbound calls. New COS groups appear here automatically. After saving, click Apply Config."); ?></span>
        </div>
      </div>
    </div></div></div>
    <script>
    /* "All lists" checkbox toggles individual list checkboxes */
    document.querySelectorAll('.tp-cos-all').forEach(function(allBox) {
        var grp = allBox.getAttribute('data-group');
        function syncItems() {
            document.querySelectorAll('.tp-cos-grp-' + grp).forEach(function(cb) {
                cb.disabled = allBox.checked;
                if (allBox.checked) cb.checked = false;
            });
        }
        syncItems();
        allBox.addEventListener('change', syncItems);
    });
    </script>
    <?php endif; ?>

    
    <!-- Blocked call recording -->
    <div class="element-container"><div class="row"><div class="col-md-12">
      <div class="row">
        <div class="col-md-3"><label class="control-label" for="blocked_sound"><?php echo _("Blocked call recording"); ?></label></div>
        <div class="col-md-9">
          <?php $curSound = $tp->get('blocked_sound') ?: 'custom/tag-blocked'; ?>
          <select class="form-control" name="blocked_sound" id="blocked_sound" style="max-width:420px;">
            <option value="custom/tag-blocked" <?php echo $curSound==='custom/tag-blocked'?'selected':''; ?>><?php echo _("Default (tag-blocked.wav)"); ?></option>
            <?php foreach ($recordings as $rec):
              $fn = trim($rec['fn']); ?>
            <option value="<?php echo htmlspecialchars($fn); ?>" <?php echo $curSound===$fn?'selected':''; ?>>
              <?php echo htmlspecialchars($rec['displayname']); ?>
            </option>
            <?php endforeach; ?>
          </select>
          <span class="help-block"><?php echo _("Message played to callers when their call is blocked."); ?></span>
        </div>
      </div>
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
