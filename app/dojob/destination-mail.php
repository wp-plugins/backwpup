<?php
if (!empty($jobs[$jobid]['mailaddress'])) {
	BackWPupFunctions::joblog($logtime,__('Sendig mail...','backwpup'));
	if (is_file($backupfile)) {
		if (filesize($backupfile)<20971520) {
			$mailfiles[0]=$backupfile;
			if (!BackWPupFunctions::needfreememory(filesize($backupfile)*3+33554432)) {
				BackWPupFunctions::joblog($logtime,__('ERROR:','backwpup').' '.__('Out of Memory for sending Backup Archive by mail','backwpup'));
				unset($mailfiles);
			}
		} else {
			if (!empty($jobs[$jobid]['backupdir'])) {
				BackWPupFunctions::joblog($logtime,__('WARNING:','backwpup').' '.__('Backup Archive too big for sendig by mail','backwpup'));		
			} else {
				BackWPupFunctions::joblog($logtime,__('ERROR:','backwpup').' '.__('Backup Archive too big for sendig by mail','backwpup'));	
			}
			unset($mailfiles);
		}
	}
	if (wp_mail($jobs[$jobid]['mailaddress'],__('BackWPup Job:','backwpup').' '.$jobs[$jobid]['name'],$wpdb->get_var("SELECT log FROM ".$wpdb->backwpup_logs." WHERE logtime=".$logtime),'',$mailfiles)) {
		BackWPupFunctions::joblog($logtime,__('Mail send!!!','backwpup'));
	} else {
		BackWPupFunctions::joblog($logtime,__('ERROR:','backwpup').' '.__('Can not send mail!!!','backwpup'));
	}	
}
//clean vars
unset($mailfiles);
?>