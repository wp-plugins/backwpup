<?PHP
if (file_exists(trim($_GET['wpabs']).'wp-load.php') and file_exists(trim($_GET['logfile']))) {
	require_once(trim($_GET['wpabs']).'wp-load.php'); /** Setup WordPress environment */
	check_admin_referer('viewlognow_'.basename($_GET['logfile']));
	readfile(trim($_GET['logfile']));
} else {
	header("HTTP/1.0 404 Not Found");
}
?>
