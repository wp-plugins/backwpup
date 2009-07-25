<?php
$sendmail=false;
if ($wpdb->get_var("SELECT error FROM ".$wpdb->backwpup_logs." WHERE logtime=".$logtime)>0 and $jobs[$jobid]['mailerroronly'])
	$sendmail=true;
if (!$jobs[$jobid]['mailerroronly'])
	$sendmail=true;
	
if (!empty($jobs[$jobid]['mailaddress']) and $sendmail) {
	//maillig method
	add_action('phpmailer_init', array('BackWPupFunctions', 'use_mail_method'));
	global $phpmailer;
	BackWPupFunctions::joblog($logtime,__('Sendig mail...','backwpup'));
	if (is_file($backupfile) and !empty($jobs[$jobid]['mailefilesize'])) {
		$maxfilezise=abs($jobs[$jobid]['mailefilesize']*1024*1024);
		if (filesize($backupfile)<$maxfilezise) {
			$mailfiles[0]=$backupfile;
			if (!BackWPupFunctions::needfreememory(filesize($backupfile)*4)) {
				BackWPupFunctions::joblog($logtime,__('ERROR:','backwpup').' '.__('Can not increase Memory for sending Backup Archive by Mail','backwpup'));
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
	if (wp_mail($jobs[$jobid]['mailaddress'],__('BackWPup Job:','backwpup').' '.date('Y-m-d H:i',$logtime).': '.$jobs[$jobid]['name'] ,$wpdb->get_var("SELECT log FROM ".$wpdb->backwpup_logs." WHERE logtime=".$logtime),'',$mailfiles)) {
		BackWPupFunctions::joblog($logtime,__('Mail send!!!','backwpup'));
	} else {
		BackWPupFunctions::joblog($logtime,__('ERROR:','backwpup').' '.__('Can not send mail:','backwpup').' '.$phpmailer->ErrorInfo);
	}	
}
//clean vars
unset($mailfiles);
?>