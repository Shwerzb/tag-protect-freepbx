<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

// Dialplan generator - called by retrieve_conf during "Apply Config" / fwconsole reload.
function tagprotect_get_config($engine) {
	global $ext;
	switch ($engine) {
		case 'asterisk':
			$tp = FreePBX::create()->Tagprotect;
			$tp->writeConfFile();   // keep /etc/asterisk/tagprotect.conf in sync with GUI settings
			$tp->installFiles();    // ensure the AGI + recording are in place
			$tp->genDialplan($ext); // emit the predial hook, inbound-screen, and blocked contexts
			break;
	}
}
