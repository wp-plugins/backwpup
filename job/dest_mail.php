<?PHP
function backwpup_job_dest_mail() {
	global $backwpupjobrun,$backwpup_cfg;
	$backwpupjobrun['WORKING']['STEPTODO']=$backwpupjobrun['WORKING']['backupfilesize'];
	$backwpupjobrun['WORKING']['STEPDONE']=0;
	trigger_error(sprintf(__('%d. Try to sending backup with mail...','backwpup'),$backwpupjobrun['WORKING']['DEST_MAIL']['STEP_TRY']),E_USER_NOTICE);
	
	//check file Size
	if (!empty($backwpupjobrun['STATIC']['JOB']['mailefilesize'])) {
		if ($backwpupjobrun['WORKING']['backupfilesize']>abs($backwpupjobrun['STATIC']['JOB']['mailefilesize']*1024*1024)) {
			trigger_error(__('Backup archive too big for sending by mail!','backwpup'),E_USER_ERROR);
			$backwpupjobrun['WORKING']['STEPDONE']=1;
			$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_MAIL'; //set done
			return;
		}
	}

	trigger_error(__('Sending mail...','backwpup'),E_USER_NOTICE);
	if (empty($backwpup_cfg['mailsndname']))
		$headers = 'From: '.$backwpup_cfg['mailsndname'].' <'.$backwpup_cfg['mailsndemail'].'>' . "\r\n";
	else
		$headers = 'From: '.$backwpup_cfg['mailsndemail'] . "\r\n";
	
	backwpup_job_need_free_memory($backwpupjobrun['WORKING']['backupfilesize']*5);
	$mail=wp_mail($backwpupjobrun['STATIC']['JOB']['mailaddress'],
			sprintf(__('BackWPup archive from %1$s: %2$s','backwpup'),date_i18n('d-M-Y H:i',$backwpupjobrun['STATIC']['JOB']['starttime']),$backwpupjobrun['STATIC']['JOB']['name']),
			sprintf(__('Backup archive: %s','backwpup'),$backwpupjobrun['STATIC']['backupfile']),
			$headers,array($backwpupjobrun['STATIC']['JOB']['backupdir'].$backwpupjobrun['STATIC']['backupfile']));

	if (!$mail) {
		trigger_error(__('Error on sending mail!','backwpup'),E_USER_ERROR);
	} else {
		$backwpupjobrun['WORKING']['STEPTODO']=$backwpupjobrun['WORKING']['backupfilesize'];
		trigger_error(__('Mail sent.','backwpup'),E_USER_NOTICE);
	}
	$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_MAIL'; //set done
}
?>