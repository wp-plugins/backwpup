<?php
if (!empty($jobs[$jobid]['mailaddress'])) {
	BackWPupFunctions::joblog($logtime,__('Sendig mail...','backwpup'));
	if (is_file($backupfile)) {
		if (filesize($backupfile)<5242880) {
			$mailfiles=$backupfile;
		} else {
			if (!empty($jobs[$jobid]['backupdir'])) {
				BackWPupFunctions::joblog($logtime,__('WARNING:','backwpup').' '.__('Backup Archive too big for sendig by mail','backwpup'));		
			} else {
				BackWPupFunctions::joblog($logtime,__('ERROR:','backwpup').' '.__('Backup Archive too big for sendig by mail','backwpup'));	
			}
			$mailfiles='';
		}
	}
	$logs=get_option('backwpup_log');
	if (wp_mail($jobs[$jobid]['mailaddress'],__('BackWPup Job:','backwpup').' '.$jobs[$jobid]['name'],$logs[$logtime]['log'],'',$mailfiles)) {
		BackWPupFunctions::joblog($logtime,__('Mail send!!!','backwpup'));
	} else {
		BackWPupFunctions::joblog($logtime,__('ERROR:','backwpup').' '.__('Can not send mail!!!','backwpup'));
	}	
}
//clean vars
unset($mailfiles);
unset($message);
unset($logs);
?>