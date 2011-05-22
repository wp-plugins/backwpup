<?PHP
// set the cache limiter to 'nocache'
session_cache_limiter('nocache');
// set the cache expire to 30 minutes 
session_cache_expire(30);
// give the session a name
session_name('BackWPupSession');
// start session
session_start();
// Conection termination
ob_end_clean();
header("Connection: close");
ob_start();
header("Content-Length: 0");
ob_end_flush();
flush();
//check session id
$BackWPupSession=session_id($_GET['BackWPupSession']);
if (empty($BackWPupSession) or !$BackWPupSession) {
	die('Wrong Session!');
}
//Set a constance for not direkt loding in other files
define('BACKWPUP_JOBRUN_FILE', __FILE__);
// get needed functions for the jobrun
require_once('./jobfunctions.php');
//disable safe mode
ini_set('safe_mode','Off');
// Now user abrot allowed
ini_set('ignore_user_abort','Off');
ignore_user_abort(true);
// set max execution time for script 300=5 min mot webservers
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
//update running file
if (is_file($_SESSION['STATIC']['TEMPDIR'].'.backwpup_running')) {
	update_working_file();
} else {
	$_SESSION['WORKING']['ACTIVE_STEP']='JOB_END';
}
//Load needed files
foreach($_SESSION['WORKING']['STEPS'] as $step) {
	$stepfile=strtolower($step).'.php';
	if ($step!='JOB_END') {
		if (is_file('./'.$stepfile)) {
			require_once('./'.$stepfile);
		} elseif ($_SESSION['WP']['WP_DEBUG']) {
			trigger_error(__('Can not find job step file:','backwpup').' '.$stepfile,E_USER_WARNING);
		} 
	}
}

//Work 
while ($_SESSION['WORKING']['FINISHED']==false) {
	//check if job aborded
	if (!is_file($_SESSION['STATIC']['TEMPDIR'].'/.backwpup_running')) {
		job_end();
		break;
	}
	// Working step by step
	foreach($_SESSION['WORKING']['STEPS'] as $step) {
		//check if job aborded
		if (!is_file($_SESSION['STATIC']['TEMPDIR'].'/.backwpup_running')) {
			job_end();
			break 2;
		}
		//Set next step
		if (!$_SESSION['WORKING'][$step]['DONE'] and !isset($_SESSION['WORKING'][$step]['STEP_TRY'])) {
			$_SESSION['WORKING']['ACTIVE_STEP']=$step;
			$_SESSION['WORKING'][$step]['STEP_TRY']=0;
		} else {
			continue;
		}
		//Run next step
		if ($_SESSION['WORKING']['ACTIVE_STEP']==$step) {
			$_SESSION['WORKING'][$step]['STEP_TRY']=$_SESSION['WORKING'][$step]['STEP_TRY']+1;
			if (function_exists(strtolower($step))) {
				$func=call_user_func(strtolower($step));
				if ($func)
					$_SESSION['WORKING'][$step]['DONE']=true;
				else
					break;
			} elseif ($_SESSION['WP']['WP_DEBUG']) {
				trigger_error(__('Can not find job step function:','backwpup').' '.strtolower($step),E_USER_WARNING);
				$_SESSION['WORKING'][$step]['DONE']=true;
				break;
			} else {
				$_SESSION['WORKING'][$step]['DONE']=true;
				break;
			}
			if ($_SESSION['WORKING'][$step]['STEP_TRY']>=3) {
				$_SESSION['WORKING'][$step]['DONE']=true;
				break;
			}
		} 
	}
}
//close mysql
mysql_close($mysqlconlink);
?>