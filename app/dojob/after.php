<?PHP
//Delete old Logs/Backupfiles
if (!empty($jobs[$jobid]['maxbackups'])) {
	BackWPupFunctions::joblog($logtime,__('Delete old backup files...','backwpup'));
	$counter=0;$countdelbackups=0;$countdellogs=0;
	$result=mysql_query("SELECT * FROM ".$wpdb->backwpup_logs." ORDER BY logtime DESC");
	while ($logs = mysql_fetch_assoc($result)) {
		if (!empty($logs['backupfile']) or in_array($jobs[$jobid]['type'],$logonlytyps))
			$counter++;
		if ($counter>=$jobs[$jobid]['maxbackups']) {
			if (is_file($logs['backupfile'])) {
				unlink($logs['backupfile']);
				$countdelbackups++;
			}
			$wpdb->query("DELETE FROM ".$wpdb->backwpup_logs." WHERE logtime=".$logs['logtime']);
			$countdellogs++;
		}
	}
	if ($countdelbackups>0)
		BackWPupFunctions::joblog($logtime,$countdelbackups.' '.__('old backup files deleted!!!','backwpup'));
	if ($countdellogs>0)
		BackWPupFunctions::joblog($logtime,$countdellogs.' '.__('old logs deleted!!!','backwpup'));
}

if (is_file($backupfile)) {
	BackWPupFunctions::joblog($logtime,sprintf(__('Backup zip filesize is %1s','backwpup'),BackWPupFunctions::formatBytes(filesize($backupfile))));
}

if (is_file(BackWPupFunctions::get_temp_dir().'backwpup/'.DB_NAME.'.sql') ) { //delete sql temp file
	unlink(BackWPupFunctions::get_temp_dir().'backwpup/'.DB_NAME.'.sql');
}

if (empty($jobs[$jobid]['backupdir']) and (dirname($backupfile)!=BackWPupFunctions::get_temp_dir().'backwpup') and is_file($backupfile) ) { //delete backup file in temp dir
	unlink($backupfile);
	unset($backupfile);
}

$jobs=get_option('backwpup_jobs');
$jobs[$jobid]['stoptime']=time();
$jobs[$jobid]['lastrun']=$jobs[$jobid]['starttime'];
$jobs[$jobid]['lastruntime']=$jobs[$jobid]['stoptime']-$jobs[$jobid]['starttime'];
$jobs[$jobid]['scheduletime']=wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid));
update_option('backwpup_jobs',$jobs); //Save Settings
BackWPupFunctions::joblog($logtime,sprintf(__('Backup Excution time %1s sec.','backwpup'),$jobs[$jobid]['lastruntime']));
//Write backupfile und worktime to log
$wpdb->update( $wpdb->backwpup_logs, array( 'worktime' => $jobs[$jobid]['lastruntime'], 'backupfile' => mysql_real_escape_string($backupfile)), array( 'logtime' => $logtime ));
ob_end_flush();
?>