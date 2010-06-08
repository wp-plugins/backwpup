<?PHP
if (is_dir($_GET['ABSPATH'])) {
	require_once($_GET['ABSPATH'].'/wp-load.php'); /** Setup WordPress environment */
} else {
	header("HTTP/1.0 404 Not Found");
}
if ($_GET['_nonce']==substr(md5(md5(SECURE_AUTH_KEY)),10,10)) {
	require_once($_GET['ABSPATH'].'/wp-admin/includes/export.php');
	export_wp();
} else {
	header("HTTP/1.0 404 Not Found");
}
?>