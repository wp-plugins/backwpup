<?PHP
define('DOING_BACKWPUP_JOB', true);
define('DONOTCACHEPAGE', true);
define('DONOTCACHEDB', true);
define('DONOTMINIFY', true);
define('DONOTCDN', true);
define('DONOTCACHCEOBJECT', true);
define('W3TC_IN_MINIFY', false); //W3TC will not loaded
define('BACKWPUP_LINE_SEPARATOR', (false !== strpos(PHP_OS, "WIN") or false !== strpos(PHP_OS, "OS/2")) ? "\r\n" : "\n");
//define E_DEPRECATED if PHP lower than 5.3
if ( !defined('E_DEPRECATED') )
	define('E_DEPRECATED', 8192);
if ( !defined('E_USER_DEPRECATED') )
	define('E_USER_DEPRECATED', 16384);
if ( !defined('DOING_CRON') )
	define("DOING_CRON",true);
//try to disable safe mode
@ini_set('safe_mode', '0');
// Now user abort
@ini_set('ignore_user_abort', '0');
ignore_user_abort(true);
$backwpup_cfg = '';
$backwpup_job_object = '';
global $l10n, $backwpup_cfg, $backwpup_job_object;
//phrase commandline args
if ( defined('STDIN') ) {
	$_GET['starttype'] = 'runcmd';
	foreach ( $_SERVER['argv'] as $arg ) {
		if ( strtolower(substr($arg, 0, 7)) == '-jobid=' )
			$_GET['jobid'] = (int)substr($arg, 7);
		if ( strtolower(substr($arg, 0, 9)) == '-abspath=' )
			$_GET['ABSPATH'] = substr($arg, 9);
	}
	if ( (empty($_GET['jobid']) or !is_numeric($_GET['jobid'])) )
		die('JOBID check');
	if ( is_file('../../../wp-load.php') ) {
		require_once('../../../wp-load.php');
	} else {
		$_GET['ABSPATH'] = preg_replace('/[^a-zA-Z0-9.\/_\-]/', '', trim(urldecode($_GET['ABSPATH'])));
		$_GET['ABSPATH'] = str_replace(array( '../', '\\', '//' ), '', $_GET['ABSPATH']);
		if ( file_exists($_GET['ABSPATH'] . 'wp-load.php') )
			require_once($_GET['ABSPATH'] . 'wp-load.php');
		else
			die('ABSPATH check');
	}
	@set_time_limit(0);
} else { //normal start from webservice
	//check get vars
	if ( empty($_GET['starttype']) or !in_array($_GET['starttype'], array( 'restarttime', 'restart', 'runnow', 'cronrun', 'runext','apirun' )) )
		die('Starttype check');
	if ( (empty($_GET['jobid']) or !is_numeric($_GET['jobid'])) and in_array($_GET['starttype'], array( 'runnow', 'cronrun', 'runext','apirun' )) )
		die('JOBID check');
	$_GET['_nonce'] = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($_GET['_nonce']));
	if ( empty($_GET['_nonce']) or !is_string($_GET['_nonce']) )
		die('Nonce pre check');
	if ( is_file('../../../wp-load.php') ) {
		require_once('../../../wp-load.php');
	} else {
		$_GET['ABSPATH'] = preg_replace('/[^a-zA-Z0-9.\/_\-]/', '', trim(urldecode($_GET['ABSPATH'])));
		$_GET['ABSPATH'] = str_replace(array( '../', '\\', '//' ), '', $_GET['ABSPATH']);
		if ( realpath($_GET['ABSPATH'])  and file_exists(realpath($_GET['ABSPATH'] . 'wp-load.php')) )
			require_once(realpath($_GET['ABSPATH'] . 'wp-load.php'));
		else
			die('ABSPATH check');
	}
	if ( in_array($_GET['starttype'], array( 'restarttime', 'restart', 'cronrun', 'runnow' )) and wp_verify_nonce('BackWPupJobRun'.$jobid.$_GET['jobid'],$_GET['_nonce']))
		die('Nonce check');
	elseif ( $_GET['starttype']=='apirun' and $_GET['_nonce']!=$backwpup_cfg['apicronservicekey'])
		die('Nonce check');
	elseif ( $_GET['starttype']=='runext' and $_GET['_nonce']!=$backwpup_cfg['jobrunauthkey'])
		die('Nonce check');
	@set_time_limit($backwpup_cfg['jobrunmaxexectime']);
}
if (in_array($_GET['starttype'], array( 'runnow', 'cronrun', 'runext', 'apirun', 'runcmd' )))  {
	if ( $_GET['jobid'] != backwpup_get_option('job_' . $_GET['jobid'], 'jobid'))
		die('Wrong JOBID check');
}
//check folders
if (!is_dir($backwpup_cfg['logfolder']) or !is_writable($backwpup_cfg['logfolder']))
	die('Log folder not exists or is not writable');
if (!is_dir($backwpup_cfg['tempfolder']) or !is_writable($backwpup_cfg['tempfolder']))
	die('Temp folder not exists or is not writable');
//check running job
$backwpupjobdata = backwpup_get_option('working', 'data');
if ( in_array($_GET['starttype'], array( 'runnow', 'cronrun', 'runext', 'runcmd','apirun' )) and !empty($backwpupjobdata) )
	die('A job already running');
if ( in_array($_GET['starttype'], array( 'restart', 'restarttime' )) and (empty($backwpupjobdata) or !is_array($backwpupjobdata)) )
	die('No job running');
unset($backwpupjobdata);
//disconnect or redirect
if ( in_array($_GET['starttype'], array( 'restarttime', 'restart', 'cronrun', 'runext','apirun' )) ) {
	ob_end_clean();
	header("Connection: close");
	ob_start();
	header("Content-Length: 0");
	ob_end_flush();
	flush();
}
elseif ( $_GET['starttype'] == 'runnow' ) {
	ob_start();
	wp_redirect(backwpup_admin_url('admin.php') . '?page=backwpupworking&runlogjobid='.$_GET['jobid']);
	echo ' ';
	while ( @ob_end_flush() );
	flush();
}
//unload translation
if ( $backwpup_cfg['unloadtranslations'] )
	unset($l10n);
//start class
$backwpup_job_object = new BackWPup_job($_GET['starttype'],(int)$_GET['jobid']);
?>