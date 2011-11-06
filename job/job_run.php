<?PHP
define('DONOTCACHEPAGE', true);
define('DONOTCACHEDB', true);
define('DONOTMINIFY', true);
define('DONOTCDN', true);
define('DONOTCACHCEOBJECT', true);
define('W3TC_IN_MINIFY',false); //W3TC will not loaded
define('BACKWPUP_LINE_SEPARATOR', strstr(PHP_OS, 'WIN') || strtr(PHP_OS, "OS/2")? "\r\n" : "\n");
//definie E_DEPRECATED if PHP lower than 5.3
if (!defined('E_DEPRECATED'))
	define('E_DEPRECATED',8192);
if (!defined('E_USER_DEPRECATED'))
	define('E_USER_DEPRECATED',16384);
	
//try to disable safe mode
@ini_set('safe_mode','0');
// Now user abrot allowed
@ini_set('ignore_user_abort','0');
//disable user abort
ignore_user_abort(true);
//make needed vars global
global $wpdb,$backwpupjobrun,$backwpup_cfg,$l10n;
//set vars... from get
if (!isset($jobstarttype) and empty($jobstarttype) and in_array($_REQUEST['starttype'],array('restarttime','restart','runnow')))
	$jobstarttype=trim($_REQUEST['starttype']);
if (!isset($jobstartid) and empty($jobstartid) and !empty($_GET['jobid']))
	$jobstartid=(int)$_GET['jobid'];

/** Setup WordPress environment */
if (!defined('ABSPATH')) {
	if (file_exists(trim(urldecode($_REQUEST['ABSPATH'])).'wp-load.php'))
        require_once(trim(urldecode($_REQUEST['ABSPATH'])).'wp-load.php');
	else
		die('Path check');
	//check nonce
	if ($jobstarttype=='runnow') {
		if (!wp_verify_nonce($_GET['_wpnonce'],'backwpup-job-running'))
			die('Security check');
	} elseif ($jobstarttype=='restarttime' or $jobstarttype=='restart') {
		if (empty($_POST['_wpnonce']))
			die('Security check');	
	} else {
		die();
	}
}
//unload translation
if (!empty($backwpup_cfg['unloadtranslations']))
	unset($l10n);
//load needed functions for the jobrun
require_once(dirname(__FILE__).'/job_functions.php');
$backwpupjobrun=backwpup_get_option('WORKING','DATA');
if ($jobstarttype=='runnow' or $jobstarttype=='cronrun') {
	if (!empty($backwpupjobrun))
		die('A job already running!');
	if (isset($jobstartid) and is_integer($jobstartid)) {
		require_once(dirname(__FILE__).'/job_start.php');
		backwpup_job_start($jobstartid,$jobstarttype);
	} else
		die('Job check');
} elseif ($jobstarttype=='restart' or $jobstarttype=='restarttime') {
	if (empty($backwpupjobrun) or !is_array($backwpupjobrun))
		die('No working data');
} else {
	die('Starttype check');
}
//disconect from browser on restarts
if ($jobstarttype=='restart' or $jobstarttype=='restarttime') {
	ob_end_clean();
	header("Connection: close");
	ob_start();
	header("Content-Length: 0");
	ob_end_flush();
	flush();
}
//schow working job page and let job work on
elseif ($jobstarttype=='runnow') { //got to jobrun page
	ob_start();
    wp_redirect(backwpup_admin_url('admin.php').'?page=backwpupworking');
    echo ' ';
    // flush any buffers and send the headers
    while ( @ob_end_flush() );
    flush();
}
//check existing Logfile
if (!file_exists($backwpupjobrun['LOGFILE'])) {
    $wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main_name='WORKING'");
	$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main_name='TEMP'");
	die('No logfile found!');
}
//set function for PHP user defineid error handling
$backwpupjobrun['PHP']['INI']['ERROR_LOG']=ini_get('error_log');
$backwpupjobrun['PHP']['INI']['LOG_ERRORS']=ini_get('log_errors');
$backwpupjobrun['PHP']['INI']['DISPLAY_ERRORS']=ini_get('display_errors');
@ini_set('error_log', $backwpupjobrun['LOGFILE']);
@ini_set('display_errors', 'Off');
@ini_set('log_errors', 'On');
set_error_handler('backwpup_job_joberrorhandler',E_ALL | E_STRICT);
//Check dobbel running and inactivity
if ($backwpupjobrun['WORKING']['PID']!=getmypid() and $backwpupjobrun['WORKING']['TIMESTAMP']>(current_time('timestamp')-500) and $jobstarttype=='restarttime') {
	trigger_error(__('Job restart terminated, bcause old job runs again!','backwpup'),E_USER_ERROR);
	die();
} elseif($jobstarttype=='restarttime') {
	trigger_error(__('Job restarted, because of inactivity!','backwpup'),E_USER_ERROR);
} elseif ($backwpupjobrun['WORKING']['PID']!=getmypid() and $backwpupjobrun['WORKING']['PID']!=0 and $backwpupjobrun['WORKING']['timestamp']>(time()-500)) {
	trigger_error(sprintf(__('Second Prozess is running, but old job runs! Start type is %s','backwpup'),$jobstarttype),E_USER_ERROR);
	die();
}
//set Pid
$backwpupjobrun['WORKING']['PID']=getmypid();
// execute function on job shutdown
register_shutdown_function('backwpup_job_shutdown');
if (function_exists('pcntl_signal')) {
	declare(ticks=1); //set ticks
	pcntl_signal(15, 'backwpup_job_shutdown'); //SIGTERM
	//pcntl_signal(9, 'backwpup_job_shutdown'); //SIGKILL
	pcntl_signal(2, 'backwpup_job_shutdown'); //SIGINT
}
//update working data
backwpup_job_update_working_data(true);
//Load needed files
foreach($backwpupjobrun['WORKING']['STEPS'] as $step) {
	$stepfile=strtolower($step).'.php';
	if ($step!='JOB_END') {
		if (is_file(dirname(__FILE__).'/'.$stepfile)) {
			include_once(dirname(__FILE__).'/'.$stepfile);
		} else {
			trigger_error(sprintf(__('Can not find job step file: %s','backwpup'),$stepfile),E_USER_ERROR);
		}
	}
}
// Working step by step
foreach($backwpupjobrun['WORKING']['STEPS'] as $step) {
	global $backwpup_cfg;
	//Set next step
	if (!isset($backwpupjobrun['WORKING'][$step]['STEP_TRY']) or empty($backwpupjobrun['WORKING'][$step]['STEP_TRY'])) {
		$backwpupjobrun['WORKING'][$step]['STEP_TRY']=0;
		$backwpupjobrun['WORKING']['STEPDONE']=0;
		$backwpupjobrun['WORKING']['STEPTODO']=0;
	}
	//update running file
	backwpup_job_update_working_data(true);
	//Run next step
	if (!in_array($step,$backwpupjobrun['WORKING']['STEPSDONE'])) {
		if (function_exists('backwpup_job_'.strtolower($step))) {
			while ($backwpupjobrun['WORKING'][$step]['STEP_TRY']<$backwpup_cfg['jobstepretry']) {
				if (in_array($step,$backwpupjobrun['WORKING']['STEPSDONE']))
					break;
				$backwpupjobrun['WORKING'][$step]['STEP_TRY']++;
				backwpup_job_update_working_data(true);
				call_user_func('backwpup_job_'.strtolower($step));
			}
			if ($backwpupjobrun['WORKING'][$step]['STEP_TRY']>=$backwpup_cfg['jobstepretry'])
				trigger_error(__('Step aborted has too many tries!','backwpup'),E_USER_ERROR);
		} else {
			trigger_error(sprintf(__('Can not find job step function %s!','backwpup'),strtolower($step)),E_USER_ERROR);
			$backwpupjobrun['WORKING']['STEPSDONE'][]=$step;
		}
	}
}

?>