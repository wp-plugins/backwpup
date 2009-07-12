<?php
$cfg=get_option('backwpup');
$jobs=get_option('backwpup_jobs');
$jobs[$jobid]['starttime']=time();
$jobs[$jobid]['stoptime']='';
$jobs[$jobid]['scheduletime']=wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid));
update_option('backwpup_jobs',$jobs); //Save Settings
$logtime=$jobs[$jobid]['starttime'];
$backupfilename='/backwpup_'.$jobid.'_'.date('Y-m-d_H-i-s',$jobs[$jobid]['starttime']).'.zip';
if (!empty($jobs[$jobid]['backupdir'])) {
	$backupfile=$jobs[$jobid]['backupdir'].$backupfilename;
} else {
	$backupfile=BackWPupFunctions::get_temp_dir().'backwpup'.$backupfilename;
}
$logonlytyps=array('OPTIMIZE');
if (in_array($jobs[$jobid]['type'],$logonlytyps)) {
	$jobs[$jobid]['maxbackups']=20;
}

//Create Log
$logs=get_option('backwpup_log');
$logs[$logtime]['jobid']=$jobid;
$logs[$logtime]['error']=0;
$logs[$logtime]['warning']=0;
$logs[$logtime]['log']='';
$logs[$logtime]['type']=$jobs[$jobid]['type'];
$logs[$logtime]['jobname']=$jobs[$jobid]['name'];
update_option('backwpup_log',$logs);

if (!ini_get('safe_mode') or strtolower(ini_get('safe_mode'))=='off') {
	set_time_limit(300); //300 is most webserver time limit.
} else {
	BackWPupFunctions::joblog($logtime,__('WARNING:','backwpup').' '.sprintf(__('Safe Mode is on!!! Max exec time is %1$s sec.','backwpup'),ini_get('max_execution_time')));
}

//Look for and Crate Temp dir and secure
if (!is_dir(BackWPupFunctions::get_temp_dir().'backwpup')) {
	if (!mkdir(BackWPupFunctions::get_temp_dir().'backwpup')) {
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
		if (!mkdir($jobs[$jobid]['backupdir'])) {
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