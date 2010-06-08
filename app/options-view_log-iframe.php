<?PHP
if (is_dir($_GET['ABSPATH'])) {
	require_once($_GET['ABSPATH'].'/wp-load.php'); /** Setup WordPress environment */
	check_admin_referer('viewlognow_'.basename($_GET['logfile']));
	readfile($_GET['logfile']);
} else {
	header("HTTP/1.0 404 Not Found");
}
?>
