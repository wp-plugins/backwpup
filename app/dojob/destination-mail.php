<?php
if (!empty($jobs[$jobid]['mailaddress'])) {
	BackWPupFunctions::joblog($logfile,__('Sendig mail...','backwpup'));
	if (is_file($backupfile)) {
		if (filesize($backupfile)<5242880) {
			$mailfiles=$backupfile;
		} else {
			BackWPupFunctions::joblog($logfile,__('WARNING: Backup Archive too big for sendig by mail','backwpup'));
			$mailfiles='';
		}
	}
	$logmassage=file($logfile);
	foreach ($logmassage as $massageline) {
		$mailmessage.=$massageline;
	}
	if (wp_mail($jobs[$jobid]['mailaddress'],__('BackWPup Job:','backwpup').' '.$jobs[$jobid]['name'],$mailmessage,'',$mailfiles)) {
		BackWPupFunctions::joblog($logfile,__('Mail send!!!','backwpup'));
	} else {
		BackWPupFunctions::joblog($logfile,__('ERROR: can not send mail!!!','backwpup'));
		$joberror=true;
	}	
}
//clean vars
unset($mailfiles);
unset($message);
?>