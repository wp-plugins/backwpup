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
$backwpup_cfg='';
global $backwpup_cfg,$l10n;
//check get vars
if (empty($_GET['starttype']) or !in_array($_GET['starttype'],array('restarttime','restart','runnow','cronrun','runext')))
	die('Starttype check');
if ((empty($_GET['jobid']) or !is_numeric($_GET['jobid'])) and in_array($_GET['starttype'],array('runnow','cronrun','runext')))
	die('JOBID check');
$_GET['_wpnonce']=preg_replace( '/[^a-zA-Z0-9_\-]/', '',trim($_GET['_wpnonce']));
if (empty($_GET['_wpnonce']) or !is_string($_GET['_wpnonce']))
	die('Nonce pre check');
$_GET['ABSPATH']=preg_replace( '/[^a-zA-Z0-9:.\/_\-]/', '',trim(urldecode($_GET['ABSPATH'])));
$_GET['ABSPATH']=str_replace(array('../','\\','//'),'',$_GET['ABSPATH']);
if (file_exists($_GET['ABSPATH'].'wp-load.php'))
	require_once($_GET['ABSPATH'].'wp-load.php');
else
	die('ABSPATH check');
if (!(in_array($_GET['starttype'],array('restarttime','restart','cronrun','runnow')) and wp_verify_nonce($_GET['_wpnonce'],'backwpup-job-running'))
	and !($_GET['starttype']=='runext' and !empty($_GET['_wpnonce']) and !empty($backwpup_cfg['jobrunauthkey']) and $backwpup_cfg['jobrunauthkey']))
		die('Nonce check');

if (in_array($_GET['starttype'],array('restarttime','restart','cronrun','runext'))) {
	ob_end_clean();
	header("Connection: close");
	ob_start();
	header("Content-Length: 0");
	ob_end_flush();
	flush();
}
elseif ($_GET['starttype']=='runnow') {
	ob_start();
	wp_redirect(backwpup_admin_url('admin.php').'?page=backwpupworking');
	echo ' ';
	while ( @ob_end_flush() );
	flush();
}
//unload translation
if (!empty($backwpup_cfg['unloadtranslations']))
	unset($l10n);


class BackWPup_job {

	private $jobdata=array();

	public function __construct() {

	}


	private function start() {

	}


	public function __destruct() {

	}
}
//start class
new BackWPup_job();
?>