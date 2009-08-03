<?PHP
if ( !defined('ABSPATH') ) {
	/** Setup WordPress environment */
	require_once($_GET['ABSPATH'].'/wp-load.php');
}
check_admin_referer('dojob-now_' . (int)$_GET['jobid']);
echo '<pre>';
backwpup_dojob($_GET['jobid']);
echo '</pre>';
?>