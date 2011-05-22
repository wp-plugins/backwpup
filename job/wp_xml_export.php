<?PHP
if (file_exists(trim($_GET['wpabs']).'wp-load.php')) {
	require_once(trim($_GET['wpabs']).'wp-load.php'); /** Setup WordPress environment */
} else {
	header("HTTP/1.0 404 Not Found");
	die();
}
$nonce=get_option('backwpup_nonce');
delete_option('backwpup_nonce');
if (!empty($nonce['nonce']) and $_GET['_nonce']==$nonce['nonce'] and $nonce['timestamp']<time()+60) {
	require_once(trim($_GET['wpabs']).'wp-admin/includes/export.php');
	export_wp();
} else {
	header("HTTP/1.0 404 Not Found");
	die();
}
?>