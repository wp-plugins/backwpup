<?PHP 
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');

global $logtime;

define( 'PCLZIP_TEMPORARY_DIR', get_temp_dir().'backwpup/' );
$cfg=get_option('backwpup');
$jobs=get_option('backwpup_jobs');
$logtime=time();
$jobs[$jobid]['starttime']=$logtime;
$jobs[$jobid]['stoptime']='';
$jobs[$jobid]['scheduletime']=wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid));
update_option('backwpup_jobs',$jobs); //Save Settings
if ($jobs[$jobid]['type']=='FILE' or $jobs[$jobid]['type']=='DB+FILE' or $jobs[$jobid]['type']=='DB') {
	if (!empty($jobs[$jobid]['backupdir'])) {
		$backupfile=$jobs[$jobid]['backupdir'].'/backwpup_'.$jobid.'_'.date_i18n('Y-m-d_H-i-s',$jobs[$jobid]['starttime']).'.zip';
	} else {
		$backupfile=get_temp_dir().'backwpup/backwpup_'.$jobid.'_'.date_i18n('Y-m-d_H-i-s',$jobs[$jobid]['starttime']).'.zip';
	}
} else {
	$backupfile='';
}

//Create Log
$wpdb->insert( $wpdb->backwpup_logs, array( 'logtime' => $logtime, 'jobid' => $jobid, 'jobname' => $jobs[$jobid]['name'], 'type' => $jobs[$jobid]['type'], 'log' => '' ));

if (!ini_get('safe_mode') or strtolower(ini_get('safe_mode'))=='off' or ini_get('safe_mode')=='0') {
	if (empty($cfg['maxexecutiontime']))
		$cfg['maxexecutiontime']=300;
	set_time_limit($cfg['maxexecutiontime']); //300 is most webserver time limit.
} else {
	backwpup_joblog($logtime,__('WARNING:','backwpup').' '.sprintf(__('PHP Safe Mode is on!!! Max exec time is %1$s sec.','backwpup'),ini_get('max_execution_time')));
}

if (!function_exists('memory_get_usage')) {
	if (empty($cfg['memorylimit']))
		$cfg['memorylimit']='128M';
	ini_set('memory_limit', $cfg['memorylimit']);
	backwpup_joblog($logtime,__('WARNING:','backwpup').' '.sprintf(__('Memory limit set to %1$s ,because can not use PHP: memory_get_usage() function.','backwpup'),ini_get('memory_limit')));
}

//Look for and Crate Temp dir and secure
backwpup_joblog($logtime,sprintf(__('Temp dir is %1$s.','backwpup'),get_temp_dir().'backwpup'));

if (!is_dir(get_temp_dir().'backwpup')) {
	if (!mkdir(get_temp_dir().'backwpup',0777,true)) {
		backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('Can not create Temp dir','backwpup'));	
		require_once('after.php');
		return false;
	} 
}
if (!is_writeable(get_temp_dir().'backwpup')) {
		backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('Can not write to Temp dir','backwpup'));	
		require_once('after.php');
		return false;	
}
if (strtolower(substr($_SERVER["SERVER_SOFTWARE"],0,6))=="apache") {  //check if it a apache webserver
	if (!is_file(get_temp_dir().'backwpup/.htaccess')) {
		if($file = @fopen(get_temp_dir().'backwpup/.htaccess', 'w')) {
			fwrite($file, "Order allow,deny\ndeny from all");
			fclose($file);
		}
	}
} else {
	if (!is_file(get_temp_dir().'backwpup/index.html')) {
		if($file = @fopen(get_temp_dir().'backwpup/index.html', 'w')) {
			fwrite($file,"\n");
			fclose($file);
		} 
	}
}

if (!empty($backupfile)) {
	//Look for and Crate Backup dir and secure
	if (!is_dir($jobs[$jobid]['backupdir'])) {
		if (!mkdir($jobs[$jobid]['backupdir'],0777,true)) {
			backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('Can not create Backup dir','backwpup'));	
			require_once('after.php');
			return false;
		}	 
	}
	if (!is_writeable($jobs[$jobid]['backupdir'])) {
			backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('Can not write to Backup dir','backwpup'));	
			require_once('after.php');
			return false;	
	}
	if (strtolower(substr($_SERVER["SERVER_SOFTWARE"],0,6))=="apache") {  //check if it a apache webserver
		if (!is_file($jobs[$jobid]['backupdir'].'/.htaccess')) {
			if($file = fopen($jobs[$jobid]['backupdir'].'/.htaccess', 'w')) {
				fwrite($file, "Order allow,deny\ndeny from all");
				fclose($file);
			}
		}
	} else {
		if (!is_file($jobs[$jobid]['backupdir'].'/index.html')) {
			if($file = fopen($jobs[$jobid]['backupdir'].'/index.html', 'w')) {
				fwrite($file,"\n");
				fclose($file);
			} 
		}
	}
	backwpup_joblog($logtime,__('Backup zip file save to:','backwpup').' '.$backupfile);
}
?>