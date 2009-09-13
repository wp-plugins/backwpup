<?PHP 
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');

//Delete old Backupfiles
if (!empty($jobs[$jobid]['maxbackups']) and !empty($jobs[$jobid]['backupdir']) and is_dir($jobs[$jobid]['backupdir'])) {
	unset($backupfilelist);	
	if ( $dir = @opendir($jobs[$jobid]['backupdir']) ) { //make file list	
		while (($file = readdir($dir)) !== false ) {
			if ('backwpup_'.$jobid.'_' == substr($file,0,strlen('backwpup_'.$jobid.'_')) and ".zip" == substr($file,-4))
				$backupfilelist[]=$file;
		}
		@closedir( $dir );
	}
	if (sizeof($backupfilelist)>0) {
		rsort($backupfilelist);
		$numdeltefiles=0;
		for ($i=$jobs[$jobid]['maxbackups'];$i<sizeof($backupfilelist);$i++) {
			unlink(trailingslashit($jobs[$jobid]['backupdir']).$backupfilelist[$i]);
			$numdeltefiles++;
		}
		if ($numdeltefiles>0)
			backwpup_joblog($logtime,$numdeltefiles.' '.__('old backup files deleted!!!','backwpup'));
	}
}
//Delete old Logs
if (!empty($cfg['maxlogs'])) {
	$countdellogs=0;
	$result=mysql_query("SELECT * FROM ".$wpdb->backwpup_logs." ORDER BY logtime DESC LIMIT ".$cfg['maxlogs'].",18446744073709551615");
	while ($logs = mysql_fetch_assoc($result)) {
		$wpdb->query("DELETE FROM ".$wpdb->backwpup_logs." WHERE logtime=".$logs['logtime']);
		$countdellogs++;
	}	
	if ($countdellogs>0)
		backwpup_joblog($logtime,$countdellogs.' '.__('old logs deleted!!!','backwpup'));
}

if (is_file($backupfile)) {
	backwpup_joblog($logtime,sprintf(__('Backup ZIP File size is %1s','backwpup'),backwpup_formatBytes(filesize($backupfile))));
}

if (is_file(get_temp_dir().'backwpup/'.DB_NAME.'.sql') ) { //delete sql temp file
	unlink(get_temp_dir().'backwpup/'.DB_NAME.'.sql');
}

if (empty($jobs[$jobid]['backupdir']) and (dirname($backupfile)!=get_temp_dir().'backwpup') and is_file($backupfile) ) { //delete backup file in temp dir
	unlink($backupfile);
	unset($backupfile);
}

$jobs=get_option('backwpup_jobs');
$jobs[$jobid]['stoptime']=time();
$jobs[$jobid]['lastrun']=$jobs[$jobid]['starttime'];
$jobs[$jobid]['lastruntime']=$jobs[$jobid]['stoptime']-$jobs[$jobid]['starttime'];
$jobs[$jobid]['scheduletime']=wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid));
update_option('backwpup_jobs',$jobs); //Save Settings
backwpup_joblog($logtime,sprintf(__('Job done in %1s sec.','backwpup'),$jobs[$jobid]['lastruntime']));
//Write backupfile und worktime to log
$wpdb->update( $wpdb->backwpup_logs, array( 'worktime' => $jobs[$jobid]['lastruntime'], 'backupfile' => mysql_real_escape_string($backupfile)), array( 'logtime' => $logtime ));
?>