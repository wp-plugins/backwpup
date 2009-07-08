<?PHP
//Delete old Logs/Backupfiles
if (!empty($jobs[$jobid]['maxbackups'])) {
	BackWPupFunctions::joblog($logtime,__('Delete old backup files...','backwpup'));
	$logs=get_option('backwpup_log');
	if (is_array($logs)) {
		unset($logkeys);
		foreach ($logs as $timestamp => $logdata) {
			if ($logdata['jobid']==$jobid)
				$logkeys[]=$timestamp;
		}
		if (is_array($logkeys)) {
			rsort($logkeys,SORT_NUMERIC);
			$counter=0;$countdelbackups=0;
			for ($i=0;$i<sizeof($logkeys);$i++) {
				if (!empty($logs[$logkeys[$i]]['backupfile']) or in_array($jobs[$jobid]['type'],$logonlytyps))
					$counter++;
				if ($counter>=$jobs[$jobid]['maxbackups']) {
					if (is_file($logs[$logkeys[$i]]['backupfile'])) {
						unlink($logs[$logkeys[$i]]['backupfile']);
						$countdelbackups++;
					}
					unset($logs[$logkeys[$i]]);
				}
			}
		}
	}
	update_option('backwpup_log',$logs);
	BackWPupFunctions::joblog($logtime,$countdelbackups.' '.__('Old backup files deleted!!!','backwpup'));
	//clean vars
	unset($logkeys);
	unset($logs);
}


if (is_file($backupfile)) {
	BackWPupFunctions::joblog($logtime,sprintf(__('Backup zip filesize is %1s','backwpup'),BackWPupFunctions::formatBytes(filesize($backupfile))));
}

if (is_file(BackWPupFunctions::get_temp_dir().'backwpup/'.DB_NAME.'.sql') ) { //delete sql temp file
	unlink(BackWPupFunctions::get_temp_dir().'backwpup/'.DB_NAME.'.sql');
}
if (empty($jobs[$jobid]['backupdir']) and ($backupfile!=BackWPupFunctions::get_temp_dir().'backwpup'.$backupfilename) and is_file($backupfile) ) { //delete backup file in temp dir
	unlink($backupfile);
	unset($backupfile);
}

$jobs=get_option('backwpup_jobs');
$jobs[$jobid]['stoptime']=time();
$jobs[$jobid]['lastrun']=$jobs[$jobid]['starttime'];
$jobs[$jobid]['lastruntime']=$jobs[$jobid]['stoptime']-$jobs[$jobid]['starttime'];
$jobs[$jobid]['scheduletime']=wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid));
update_option('backwpup_jobs',$jobs); //Save Settings

$logs=get_option('backwpup_log');
$logs[$logtime]['worktime']=$jobs[$jobid]['stoptime']-$jobs[$jobid]['starttime'];
if (is_file($backupfile)) 
	$logs[$logtime]['backupfile']=$backupfile;	
update_option('backwpup_log',$logs);
?>