<?PHP 
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');

$sendmail=false;
$errorcount=$wpdb->get_var("SELECT error FROM ".$wpdb->backwpup_logs." WHERE logtime=".$logtime);
if ($errorcount>0 and $jobs[$jobid]['mailerroronly'])
	$sendmail=true;
if (!$jobs[$jobid]['mailerroronly'])
	$sendmail=true;
	
if (!empty($jobs[$jobid]['mailaddress']) and $sendmail) {
	//maillig method
	add_action('phpmailer_init', 'backwpup_use_mail_method');
	global $phpmailer;
	backwpup_joblog($logtime,__('Sending mail...','backwpup'));
	if (is_file($backupfile) and !empty($jobs[$jobid]['mailefilesize'])) {
		$maxfilezise=abs($jobs[$jobid]['mailefilesize']*1024*1024);
		if (filesize($backupfile)<$maxfilezise) {
			$mailfiles[0]=$backupfile;
			if (!backwpup_needfreememory(filesize($backupfile)*4)) {
				backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('Can not increase Memory for sending Backup Archive by Mail','backwpup'));
				unset($mailfiles);
			}
		} else {
			if (!empty($jobs[$jobid]['backupdir'])) {
				backwpup_joblog($logtime,__('WARNING:','backwpup').' '.__('Backup Archive too big for sending by mail','backwpup'));		
			} else {
				backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('Backup Archive too big for sending by mail','backwpup'));	
			}
			unset($mailfiles);
		}
	}
	if (wp_mail($jobs[$jobid]['mailaddress'],__('BackWPup Job:','backwpup').' '.date_i18n('Y-m-d H:i',$logtime).': '.$jobs[$jobid]['name'] ,$wpdb->get_var("SELECT log FROM ".$wpdb->backwpup_logs." WHERE logtime=".$logtime),'',$mailfiles)) {
		backwpup_joblog($logtime,__('Mail send!!!','backwpup'));
	} else {
		backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('Can not send mail:','backwpup').' '.$phpmailer->ErrorInfo);
	}	
}
//clean vars
unset($mailfiles);
?>