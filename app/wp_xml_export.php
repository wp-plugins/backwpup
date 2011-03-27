<?PHP
if (file_exists(trim($_GET['wpabs']).'wp-load.php')) {
	require_once(trim($_GET['wpabs']).'wp-load.php'); /** Setup WordPress environment */
} else {
	header("HTTP/1.0 404 Not Found");
	die();
}
if (!wp_verify_nonce($_GET['_nonce'], 'backwpup-xmlexport')) {
	require_once(trim($_GET['wpabs']).'wp-admin/includes/export.php');
	export_wp();
} else {
	header("HTTP/1.0 404 Not Found");
	die();
}
?>