<?PHP
if ( !defined('ABSPATH') ) {
	/** Setup WordPress environment */
	require_once($_GET['ABSPATH'].'/wp-load.php');
}
check_admin_referer('viewlognow');
readfile($_GET['logfile']);
?>
