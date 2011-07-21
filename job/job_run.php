<?PHP
//Set a constance for not direkt loding in other files
define('BACKWPUP_JOBRUN_FOLDER', dirname(__FILE__).'/');
// get needed functions for the jobrun
require_once(BACKWPUP_JOBRUN_FOLDER.'job_functions.php');
//check referer
if ($_SERVER['HTTP_REFERER']!=curPageURL() or $_SERVER["HTTP_USER_AGENT"]!='BackWPup') {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}
//get temp dir
$STATIC['TEMPDIR']=trim(urldecode($_COOKIE['BackWPupJobTemp']));
if (!is_writable($STATIC['TEMPDIR'])) {
	die('Temp dir not writable!!! Job aborted!');
}
//read runningfile and config
$runningfile=get_working_file();
if ($runningfile['JOBID']>0) {
	if ($staticfile=file_get_contents($STATIC['TEMPDIR'].'.static')) {
		$STATIC=unserialize(trim($staticfile));
	} else {
		delete_working_file();
		die('No config file found');
	}
	$WORKING=$runningfile['WORKING'];
	unset($runningfile);
	unset($staticfile);
} else {
	die('No running file found!!!');
}
//check are temp dirs the same
if ($STATIC['TEMPDIR']!=trim($_COOKIE['BackWPupJobTemp'])) {
	delete_working_file();
	die('Temp dir not correct!');
}
ob_end_clean();
header("Connection: close");
ob_start();
header("Content-Length: 0");
ob_end_flush();
flush();
//check existing Logfile
if (empty($STATIC) or !file_exists($STATIC['LOGFILE'])) {
	delete_working_file();
	die('No logfile found!');
}
//set timezone
date_default_timezone_set('UTC');
//set function for PHP user defineid error handling
set_error_handler('joberrorhandler',E_ALL | E_STRICT);
//Get type and check job runs
$runningfile=get_working_file();
$revtime=time()-$STATIC['CFG']['jobscriptruntimelong']-10;
if ($runningfile['PID']!=getmypid() and $runningfile['timestamp']>$revtime and $_GET['type']=='restarttime') {
	trigger_error(__('Job restart terminated, bcause old job runs again!','backwpup'),E_USER_ERROR);
	die();
} elseif($_GET['type']=='restarttime') {
	trigger_error(__('Job restarted, bcause inactivity!','backwpup'),E_USER_ERROR);
} elseif ($runningfile['PID']!=getmypid() and $runningfile['PID']!=0 and $runningfile['timestamp']>$revtime) {
	trigger_error(sprintf(__('Second Prozess is running, bcause old job runs! Start type is %s','backwpup'),$_GET['type']),E_USER_ERROR);
	die();
} 
unset($runningfile);
// execute function on job shutdown
register_shutdown_function('job_shutdown');
//disable safe mode
@ini_set('safe_mode','0');
//set execution time tom max on safe mode
if (ini_get('safe_mode')) {
	$STATIC['CFG']['jobscriptruntime']=ini_get('max_execution_time');
	$STATIC['CFG']['jobscriptruntimelong']=ini_get('max_execution_time');
} 
// Now user abrot allowed
@ini_set('ignore_user_abort','0');
//disable user abort
ignore_user_abort(true);
//update running file
update_working_file(true);
//Load needed files
foreach($WORKING['STEPS'] as $step) {
	$stepfile=strtolower($step).'.php';
	if ($step!='JOB_END') {
		if (is_file(BACKWPUP_JOBRUN_FOLDER.$stepfile)) {
			require_once(BACKWPUP_JOBRUN_FOLDER.$stepfile);
		} else {
			trigger_error(sprintf(__('Can not find job step file: %s','backwpup'),$stepfile),E_USER_ERROR);
		} 
	}
}
// Working step by step
foreach($WORKING['STEPS'] as $step) {
	//display some info massages bevor fist step
	if (count($WORKING['STEPSDONE'])==0) {
		trigger_error(sprintf(__('[INFO]: BackWPup version %1$s, Copyright &copy; %2$s %3$s'),$STATIC['BACKWPUP']['VERSION'],date('Y',time()+$STATIC['WP']['TIMEDIFF']),'<a href="http://danielhuesken.de" target="_blank">Daniel H&uuml;sken</a>'),E_USER_NOTICE);
		trigger_error(__('[INFO]: BackWPup comes with ABSOLUTELY NO WARRANTY. This is free software, and you are welcome to redistribute it under certain conditions.','backwpup'),E_USER_NOTICE);
		trigger_error(__('[INFO]: BackWPup job:','backwpup').' '.$STATIC['JOB']['jobid'].'. '.$STATIC['JOB']['name'].'; '.$STATIC['JOB']['type'],E_USER_NOTICE);
		if ($STATIC['JOB']['activated'])
			trigger_error(__('[INFO]: BackWPup cron:','backwpup').' '.$STATIC['JOB']['cron'].'; '.date('D, j M Y @ H:i',$STATIC['JOB']['cronnextrun']),E_USER_NOTICE);
		if ($STATIC['CRONSTART'])
			trigger_error(__('[INFO]: BackWPup job strated by cron','backwpup'),E_USER_NOTICE);
		else
			trigger_error(__('[INFO]: BackWPup job strated manualy','backwpup'),E_USER_NOTICE);
		trigger_error(__('[INFO]: PHP ver.:','backwpup').' '.phpversion().'; '.php_sapi_name().'; '.PHP_OS,E_USER_NOTICE);
		if (ini_get('safe_mode'))
			trigger_error(sprintf(__('[INFO]: PHP Safe mode is ON! Maximum script execution time is %1$d sec.','backwpup'),ini_get('max_execution_time')),E_USER_NOTICE);
		trigger_error(__('[INFO]: MySQL ver.:','backwpup').' '.mysql_result(mysql_query("SELECT VERSION() AS version"),0),E_USER_NOTICE);
		$curlversion=curl_version();
		trigger_error(__('[INFO]: curl ver.:','backwpup').' '.$curlversion['version'].'; '.$curlversion['ssl_version'],E_USER_NOTICE);
		trigger_error(__('[INFO]: Temp folder is:','backwpup').' '.$STATIC['TEMPDIR'],E_USER_NOTICE);
		if(!empty($STATIC['backupfile']))
			trigger_error(__('[INFO]: Backup file is:','backwpup').' '.$STATIC['JOB']['backupdir'].$STATIC['backupfile'],E_USER_NOTICE);
		//test for destinations
		if (in_array('DB',$STATIC['TODO']) or in_array('WPEXP',$STATIC['TODO']) or in_array('FILE',$STATIC['TODO'])) {
			$desttest=false;
			foreach($WORKING['STEPS'] as $deststeptest) {
				if (substr($deststeptest,0,5)=='DEST_') {
					$desttest=true;
					break;
				}
			}
			if (!$desttest)
				trigger_error(__('No destination defineid for backup!!! Please correct job settings','backwpup'),E_USER_ERROR);
		}
	}
	//Set next step
	if (!isset($WORKING[$step]['STEP_TRY']) or empty($WORKING[$step]['STEP_TRY'])) {
		$WORKING[$step]['STEP_TRY']=0;
		$WORKING['STEPDONE']=0;
		$WORKING['STEPTODO']=0;
	}
	//update running file
	update_working_file(true);
	//Run next step
	if (!in_array($step,$WORKING['STEPSDONE'])) {
		if (function_exists(strtolower($step))) {
			while ($WORKING[$step]['STEP_TRY']<$STATIC['CFG']['jobstepretry']) {
				if (in_array($step,$WORKING['STEPSDONE']))
					break;
				$WORKING[$step]['STEP_TRY']++;
				update_working_file(true);
				call_user_func(strtolower($step));
			}
			if ($WORKING[$step]['STEP_TRY']>=$STATIC['CFG']['jobstepretry'])
				trigger_error(__('Step arborted has too many trys!','backwpup'),E_USER_ERROR);
		} else {
			trigger_error(sprintf(__('Can not find job step function %s!','backwpup'),strtolower($step)),E_USER_ERROR);
			$WORKING['STEPSDONE'][]=$step;
		}
	} 
}
//close mysql
mysql_close($mysqlconlink);
?>