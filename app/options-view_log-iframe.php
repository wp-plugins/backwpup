<?PHP
if ( !defined('ABSPATH') ) {
	/** Setup WordPress environment */
	require_once($_GET['ABSPATH'].'/wp-load.php');
}
check_admin_referer('viewlognow_'.basename($_GET['logfile']));
readfile($_GET['logfile']);
?>
