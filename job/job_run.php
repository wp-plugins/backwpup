<?PHP
//Set a constance for not direkt loding in other files
define('BACKWPUP_JOBRUN_FOLDER', dirname(__FILE__).'/');
// get needed functions for the jobrun
require_once(BACKWPUP_JOBRUN_FOLDER.'job_functions.php');
// set the cache limiter to 'nocache'
session_cache_limiter('nocache');
// set the cache expire to 30 minutes 
session_cache_expire(30);
// give the session a name
session_name('BackWPupSession');
//delete session cookie
session_set_cookie_params(0);
// start session
session_start();
// Conection termination
ob_end_clean();
header("Connection: close");
ob_start();
header("Content-Length: 0");
ob_end_flush();
flush();
//check existing session and Logfile
if (!empty($_SESSION) and !file_exists($_SESSION['STATIC']['LOGFILE'])) {
	delete_working_file();
	die();
}
//disable safe mode
@ini_set('safe_mode','0');
//set execution time tom max on safe mode
if (ini_get('safe_mode')) {
	$_SESSION['CFG']['jobscriptruntime']=ini_get('max_execution_time');
	$_SESSION['CFG']['jobscriptruntimelong']=ini_get('max_execution_time');
} 
if (empty($_SESSION['CFG']['jobscriptruntime']) or !is_int($_SESSION['CFG']['jobscriptruntime']))
	$_SESSION['CFG']['jobscriptruntime']=ini_get('max_execution_time');
if (empty($_SESSION['CFG']['jobscriptruntimelong']) or !is_int($_SESSION['CFG']['jobscriptruntimelong']))
	$_SESSION['CFG']['jobscriptruntimelong']=300;
// Now user abrot allowed
@ini_set('ignore_user_abort','0');
//disable user abort
ignore_user_abort(true);
// execute function on job shutdown
register_shutdown_function('job_shutdown');
//set function for PHP user defineid error handling
if ($_SESSION['WP']['WP_DEBUG'])
	set_error_handler('joberrorhandler',E_ALL | E_STRICT);
else
	set_error_handler('joberrorhandler',E_ALL & ~E_NOTICE);
//Ceck Session ID
$runningfile=get_working_file();
if ($runningfile['SID']!=session_id()) {
	trigger_error(__('Wrong Session ID!','backwpup'),E_USER_ERROR);
	job_end();
}
//check max script execution tme
if (ini_get('safe_mode') or strtolower(ini_get('safe_mode'))=='on' or ini_get('safe_mode')=='1')
	trigger_error(sprintf(__('PHP Safe Mode is on!!! Max exec time is %1$d sec.','backwpup'),ini_get('max_execution_time')),E_USER_NOTICE);

//update running file
update_working_file();

//Load needed files
foreach($_SESSION['WORKING']['STEPS'] as $step) {
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
foreach($_SESSION['WORKING']['STEPS'] as $step) {
	//display some info massages bevor fist step
	if (count($_SESSION['WORKING']['STEPSDONE'])==0) {
		trigger_error('[INFO]: BackWPup version '.$_SESSION['BACKWPUP']['VERSION'].', Copyright &copy; '.date('Y').' <a href="http://danielhuesken.de" target="_blank">Daniel H&uuml;sken</a>',E_USER_NOTICE);
		trigger_error(__('[INFO]: BackWPup comes with ABSOLUTELY NO WARRANTY. This is free software, and you are welcome to redistribute it under certain conditions.','backwpup'),E_USER_NOTICE);
		trigger_error(__('[INFO]: BackWPup job:','backwpup').' '.$_SESSION['JOB']['jobid'].'. '.$_SESSION['JOB']['name'].'; '.$_SESSION['JOB']['type'],E_USER_NOTICE);
		if ($_SESSION['JOB']['activated'])
			trigger_error(__('[INFO]: BackWPup cron:','backwpup').' '.$_SESSION['JOB']['cron'].'; '.date('D, j M Y H:i',$_SESSION['JOB']['cronnextrun']),E_USER_NOTICE);
		trigger_error(__('[INFO]: PHP ver.:','backwpup').' '.phpversion().'; '.php_sapi_name().'; '.PHP_OS,E_USER_NOTICE);
		if (ini_get('safe_mode'))
			trigger_error(__('[INFO]: PHP Safe mode is ON!','backwpup'),E_USER_NOTICE);		
		trigger_error(__('[INFO]: MySQL ver.:','backwpup').' '.mysql_result(mysql_query("SELECT VERSION() AS version"),0),E_USER_NOTICE);
		$curlversion=curl_version();
		trigger_error(__('[INFO]: curl ver.:','backwpup').' '.$curlversion['version'].'; '.$curlversion['ssl_version'],E_USER_NOTICE);
		trigger_error(__('[INFO]: Temp folder is:','backwpup').' '.$_SESSION['STATIC']['TEMPDIR'],E_USER_NOTICE);
		if(!empty($_SESSION['STATIC']['backupfile']))
			trigger_error(__('[INFO]: Backup file is:','backwpup').' '.$_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile'],E_USER_NOTICE);
	}
	//update running file
	update_working_file();
	//Set next step
	if (!isset($_SESSION['WORKING'][$step]['STEP_TRY']) or empty($_SESSION['WORKING'][$step]['STEP_TRY'])) {
		$_SESSION['WORKING'][$step]['STEP_TRY']=0;
		$_SESSION['WORKING']['STEPDONE']=0;
		$_SESSION['WORKING']['STEPTODO']=0;
	}
	//Run next step
	if (!in_array($step,$_SESSION['WORKING']['STEPSDONE'])) {
		if (function_exists(strtolower($step))) {
			while ($_SESSION['WORKING'][$step]['STEP_TRY']<$_SESSION['CFG']['jobstepretry']) {
				if (in_array($step,$_SESSION['WORKING']['STEPSDONE']))
					break;
				$_SESSION['WORKING'][$step]['STEP_TRY']++;
				$func=call_user_func(strtolower($step));
			}
			if ($_SESSION['WORKING'][$step]['STEP_TRY']>=$_SESSION['CFG']['jobstepretry'])
				trigger_error(__('Step arborted has too many trys!!!','backwpup'),E_USER_ERROR);
		} else {
			trigger_error(__('Can not find job step function:','backwpup').' '.strtolower($step),E_USER_ERROR);
			$_SESSION['WORKING']['STEPSDONE'][]=$step;
		}
	} 
}

//close mysql
mysql_close($mysqlconlink);
?>