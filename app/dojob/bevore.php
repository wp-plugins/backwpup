<?php
global $logtime;
//@ini_set('memory_limit', '256M');
ignore_user_abort(true);
ob_start();
ob_end_clean();

define( 'PCLZIP_TEMPORARY_DIR', BackWPupFunctions::get_temp_dir().'backwpup/' );
$cfg=get_option('backwpup');
$jobs=get_option('backwpup_jobs');
$logtime=time();
$jobs[$jobid]['starttime']=$logtime;
$jobs[$jobid]['stoptime']='';
$jobs[$jobid]['scheduletime']=wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid));
update_option('backwpup_jobs',$jobs); //Save Settings
if ($jobs[$jobid]['type']=='FILE' or $jobs[$jobid]['type']=='DB+FILE' or $jobs[$jobid]['type']=='DB') {
	if (!empty($jobs[$jobid]['backupdir'])) {
		$backupfile=$jobs[$jobid]['backupdir'].'/backwpup_'.$jobid.'_'.date('Y-m-d_H-i-s',$jobs[$jobid]['starttime']).'.zip';
	} else {
		$backupfile=BackWPupFunctions::get_temp_dir().'backwpup/backwpup_'.$jobid.'_'.date('Y-m-d_H-i-s',$jobs[$jobid]['starttime']).'.zip';
	}
} else {
	$backupfile='';
}
$logonlytyps=array('OPTIMIZE','CHECK');
if (in_array($jobs[$jobid]['type'],$logonlytyps)) {
	$jobs[$jobid]['maxbackups']=20;
}

//Create Log
$wpdb->insert( $wpdb->backwpup_logs, array( 'logtime' => $logtime, 'jobid' => $jobid, 'jobname' => $jobs[$jobid]['name'], 'type' => $jobs[$jobid]['type'], 'log' => '' ));


if (!ini_get('safe_mode') or strtolower(ini_get('safe_mode'))=='off' or ini_get('safe_mode')=='0') {
	set_time_limit(300); //300 is most webserver time limit.
} else {
	BackWPupFunctions::joblog($logtime,__('WARNING:','backwpup').' '.sprintf(__('PHP Safe Mode is on!!! Max exec time is %1$s sec.','backwpup'),ini_get('max_execution_time')));
}

//Look for and Crate Temp dir and secure
BackWPupFunctions::joblog($logtime,sprintf(__('Temp dir is %1$s.','backwpup'),BackWPupFunctions::get_temp_dir().'backwpup'));

if (!is_dir(BackWPupFunctions::get_temp_dir().'backwpup')) {
	if (!mkdir(BackWPupFunctions::get_temp_dir().'backwpup',0777,true)) {
		BackWPupFunctions::joblog($logtime,__('ERROR:','backwpup').' '.__('Can not create Temp dir','backwpup'));	
		require_once('after.php');
		return false;
	} 
}
if (!is_writeable(BackWPupFunctions::get_temp_dir().'backwpup')) {
		BackWPupFunctions::joblog($logtime,__('ERROR:','backwpup').' '.__('Can not write to Temp dir','backwpup'));	
		require_once('after.php');
		return false;	
}
if (!is_file(BackWPupFunctions::get_temp_dir().'backwpup/.htaccess')) {
	if($file = @fopen(BackWPupFunctions::get_temp_dir().'backwpup/.htaccess', 'w')) {
		fwrite($file, "Order allow,deny\ndeny from all");
		fclose($file);
	}
}
if (!is_file(BackWPupFunctions::get_temp_dir().'backwpup/index.html')) {
	if($file = @fopen(BackWPupFunctions::get_temp_dir().'backwpup/index.html', 'w')) {
		fwrite($file,"\n");
		fclose($file);
	} 
}


if (!empty($backupfile)) {
	//Look for and Crate Backup dir and secure
	if (!is_dir($jobs[$jobid]['backupdir'])) {
		if (!mkdir($jobs[$jobid]['backupdir'],0777,true)) {
			BackWPupFunctions::joblog($logtime,__('ERROR:','backwpup').' '.__('Can not create Backup dir','backwpup'));	
			require_once('after.php');
			return false;
		}	 
	}
	if (!is_writeable($jobs[$jobid]['backupdir'])) {
			BackWPupFunctions::joblog($logtime,__('ERROR:','backwpup').' '.__('Can not write to Backup dir','backwpup'));	
			require_once('after.php');
			return false;	
	}
	if (!is_file($jobs[$jobid]['backupdir'].'/.htaccess')) {
		if($file = fopen($jobs[$jobid]['backupdir'].'/.htaccess', 'w')) {
			fwrite($file, "Order allow,deny\ndeny from all");
			fclose($file);
		}
	}
	if (!is_file($jobs[$jobid]['backupdir'].'/index.html')) {
		if($file = fopen($jobs[$jobid]['backupdir'].'/index.html', 'w')) {
			fwrite($file,"\n");
			fclose($file);
		} 
	}
	BackWPupFunctions::joblog($logtime,__('Backup zip file save to:','backwpup').' '.$backupfile);
}


?>