<?PHP
//Set a constance for not direkt loding in other files
define('BACKWPUP_JOBRUN_FOLDER', dirname(__FILE__).'/');
// get needed functions for the jobrun
require_once(BACKWPUP_JOBRUN_FOLDER.'/../libs/backwpup_get_temp.php');
require_once(BACKWPUP_JOBRUN_FOLDER.'job_functions.php');
//read runningfile and config
$runningfile=get_working_file();
if ($runningfile['JOBID']>0) {
	$WORKING=$runningfile['WORKING'];
	if ($staticfile=file_get_contents(backwpup_get_temp().'.static'))
		$STATIC=unserialize(trim($staticfile));
	else
		die();
	unset($runningfile);
	unset($staticfile);
} else {
	die();
}
ob_end_clean();
header("Connection: close");
ob_start();
header("Content-Length: 0");
ob_end_flush();
flush();
//set timezone
date_default_timezone_set($STATIC['WP']['TIMEZONE']);
//check existing Logfile
if (!empty($STATIC) and !file_exists($STATIC['LOGFILE'])) {
	delete_working_file();
	die();
}
//disable safe mode
@ini_set('safe_mode','0');
//set execution time tom max on safe mode
if (ini_get('safe_mode')) {
	$STATIC['CFG']['jobscriptruntime']=ini_get('max_execution_time');
	$STATIC['CFG']['jobscriptruntimelong']=ini_get('max_execution_time');
} 
if (empty($STATIC['CFG']['jobscriptruntime']) or !is_int($STATIC['CFG']['jobscriptruntime']))
	$STATIC['CFG']['jobscriptruntime']=ini_get('max_execution_time');
if (empty($STATIC['CFG']['jobscriptruntimelong']) or !is_int($STATIC['CFG']['jobscriptruntimelong']))
	$STATIC['CFG']['jobscriptruntimelong']=300;
// Now user abrot allowed
@ini_set('ignore_user_abort','0');
//disable user abort
ignore_user_abort(true);
// execute function on job shutdown
register_shutdown_function('job_shutdown');
//set function for PHP user defineid error handling
if ($STATIC['WP']['WP_DEBUG'])
	set_error_handler('joberrorhandler',E_ALL | E_STRICT);
else
	set_error_handler('joberrorhandler',E_ALL & ~E_NOTICE);
//check max script execution tme
if (ini_get('safe_mode') or strtolower(ini_get('safe_mode'))=='on' or ini_get('safe_mode')=='1')
	trigger_error(sprintf(__('PHP Safe Mode is on!!! Max exec time is %1$d sec.','backwpup'),ini_get('max_execution_time')),E_USER_NOTICE);

//update running file
update_working_file();

//Load needed files
foreach($WORKING['STEPS'] as $step) {
	$stepfile=strtolower($step).'.php';
	if ($step!='JOB_END') {
		if (is_file(BACKWPUP_JOBRUN_FOLDER.$stepfile)) {
			require_once(BACKWPUP_JOBRUN_FOLDER.$stepfile);
		} else {
			trigger_error(__('Can not find job step file:','backwpup').' '.$stepfile,E_USER_ERROR);
		} 
	}
}

// Working step by step
foreach($WORKING['STEPS'] as $step) {
	//display some info massages bevor fist step
	if (count($WORKING['STEPSDONE'])==0) {
		trigger_error('[INFO]: BackWPup version '.$STATIC['BACKWPUP']['VERSION'].', Copyright &copy; '.date('Y').' <a href="http://danielhuesken.de" target="_blank">Daniel H&uuml;sken</a>',E_USER_NOTICE);
		trigger_error(__('[INFO]: BackWPup comes with ABSOLUTELY NO WARRANTY. This is free software, and you are welcome to redistribute it under certain conditions.','backwpup'),E_USER_NOTICE);
		trigger_error(__('[INFO]: BackWPup job:','backwpup').' '.$STATIC['JOB']['jobid'].'. '.$STATIC['JOB']['name'].'; '.$STATIC['JOB']['type'],E_USER_NOTICE);
		if ($STATIC['JOB']['activated'])
			trigger_error(__('[INFO]: BackWPup cron:','backwpup').' '.$STATIC['JOB']['cron'].'; '.date('D, j M Y H:i',$STATIC['JOB']['cronnextrun']),E_USER_NOTICE);
		trigger_error(__('[INFO]: PHP ver.:','backwpup').' '.phpversion().'; '.php_sapi_name().'; '.PHP_OS,E_USER_NOTICE);
		if (ini_get('safe_mode'))
			trigger_error(__('[INFO]: PHP Safe mode is ON!','backwpup'),E_USER_NOTICE);		
		trigger_error(__('[INFO]: MySQL ver.:','backwpup').' '.mysql_result(mysql_query("SELECT VERSION() AS version"),0),E_USER_NOTICE);
		$curlversion=curl_version();
		trigger_error(__('[INFO]: curl ver.:','backwpup').' '.$curlversion['version'].'; '.$curlversion['ssl_version'],E_USER_NOTICE);
		trigger_error(__('[INFO]: Temp folder is:','backwpup').' '.$STATIC['TEMPDIR'],E_USER_NOTICE);
		if(!empty($STATIC['backupfile']))
			trigger_error(__('[INFO]: Backup file is:','backwpup').' '.$STATIC['JOB']['backupdir'].$STATIC['backupfile'],E_USER_NOTICE);
	}
	//update running file
	update_working_file();
	//Set next step
	if (!isset($WORKING[$step]['STEP_TRY']) or empty($WORKING[$step]['STEP_TRY'])) {
		$WORKING[$step]['STEP_TRY']=0;
		$WORKING['STEPDONE']=0;
		$WORKING['STEPTODO']=0;
	}
	//Run next step
	if (!in_array($step,$WORKING['STEPSDONE'])) {
		if (function_exists(strtolower($step))) {
			while ($WORKING[$step]['STEP_TRY']<$STATIC['CFG']['jobstepretry']) {
				if (in_array($step,$WORKING['STEPSDONE']))
					break;
				$WORKING[$step]['STEP_TRY']++;
				$func=call_user_func(strtolower($step));
			}
			if ($WORKING[$step]['STEP_TRY']>=$STATIC['CFG']['jobstepretry'])
				trigger_error(__('Step arborted has too many trys!!!','backwpup'),E_USER_ERROR);
		} else {
			trigger_error(__('Can not find job step function:','backwpup').' '.strtolower($step),E_USER_ERROR);
			$WORKING['STEPSDONE'][]=$step;
		}
	} 
}

//close mysql
mysql_close($mysqlconlink);
?>