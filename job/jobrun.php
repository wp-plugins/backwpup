<?PHP
// set the cache limiter to 'nocache'
session_cache_limiter('nocache');
// set the cache expire to 30 minutes 
session_cache_expire(30);
// give the session a name
session_name('BackWPupSession');
//check and set session id must bevor session_start
//read runningfile with SID
if (file_exists(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running')) {
	$runningfile=unserialize(trim(file_get_contents(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running')));
	session_id($runningfile['SID']);//Set session id
} else {
	die();
}
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
	@unlink(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running');
	die();
}
//Set a constance for not direkt loding in other files
define('BACKWPUP_JOBRUN_FOLDER', dirname(__FILE__).'/');
// get needed functions for the jobrun
require_once(BACKWPUP_JOBRUN_FOLDER.'jobfunctions.php');
//disable safe mode
ini_set('safe_mode','Off');
// Now user abrot allowed
ini_set('ignore_user_abort','Off');
ignore_user_abort(true);
// set max execution time for script 300=5 min most webservers
set_time_limit(300);
// execute function on job shutdown
register_shutdown_function('job_shutdown');
//set function for PHP user defineid error handling
if ($_SESSION['WP']['WP_DEBUG'])
	set_error_handler('joberrorhandler',E_ALL | E_STRICT);
else
	set_error_handler('joberrorhandler',E_ALL & ~E_NOTICE);
//check max script execution tme
if (ini_get('safe_mode') or strtolower(ini_get('safe_mode'))=='on' or ini_get('safe_mode')=='1')
	trigger_error(sprintf(__('PHP Safe Mode is on!!! Max exec time is %1$d sec.','backwpup'),ini_get('max_execution_time')),E_USER_NOTICE);
// make a mysql connection
$mysqlconlink=mysql_connect($_SESSION['WP']['DB_HOST'], $_SESSION['WP']['DB_USER'], $_SESSION['WP']['DB_PASSWORD']);
if (!$mysqlconlink) {
    trigger_error(__('No MySQL connection:','backwpup').' ' . mysql_error(),E_USER_ERROR);
	job_end();
	die();
} 
//set connecten charset
if (!empty($_SESSION['WP']['DB_CHARSET'])) {
	if ( function_exists( 'mysql_set_charset' )) {
		mysql_set_charset( $_SESSION['WP']['DB_CHARSET'], $mysqlconlink );
	} else {
		$query = "SET NAMES '".$_SESSION['WP']['DB_CHARSET']."'";
		if (!empty($collate))
			$query .= " COLLATE '".$_SESSION['WP']['DB_COLLATE']."'";
		mysql_query($query,$mysqlconlink);
	}
}
//connect to database
$mysqldblink = mysql_select_db($_SESSION['WP']['DB_NAME'], $mysqlconlink);
if (!$mysqldblink) {
    trigger_error(__('No MySQL connection to database:','backwpup').' ' . mysql_error(),E_USER_ERROR);
	job_end();
	mysql_close($mysqlconlink);
	die();
}
//set som def. vars
$_SESSION['WORKING']['STEPTODO']=0;
$_SESSION['WORKING']['STEPDONE']=0;
$_SESSION['WORKING']['STEPSDONE']=array();
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
	//update running file
	update_working_file();
	//Set next step
	if (!isset($_SESSION['WORKING'][$step]['STEP_TRY']) or empty($_SESSION['WORKING'][$step]['STEP_TRY'])) {
		$_SESSION['WORKING'][$step]['STEP_TRY']=0;
	}
	//ste back step working
	$_SESSION['WORKING']['STEPDONE']=0;
	$_SESSION['WORKING']['STEPTODO']=0;
	//Run next step
	if (!in_array($step,$_SESSION['WORKING']['STEPSDONE'])) {
		if (function_exists(strtolower($step))) {
			while ($_SESSION['WORKING'][$step]['STEP_TRY']<3) {
				if (in_array($step,$_SESSION['WORKING']['STEPSDONE']))
					break;
				$_SESSION['WORKING'][$step]['STEP_TRY']++;
				$func=call_user_func(strtolower($step));
			}
			if ($_SESSION['WORKING'][$step]['STEP_TRY']>=3)
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