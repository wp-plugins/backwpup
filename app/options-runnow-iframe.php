<?PHP
if (file_exists(trim($_GET['wpabs']).'wp-load.php') and is_numeric(trim($_GET['jobid']))) {
	require_once(trim($_GET['wpabs']).'wp-load.php'); /** Setup WordPress environment */
	check_admin_referer('dojob-now_' . (int)$_GET['jobid']);
	backwpup_send_no_cache_header();
	// flush any buffers and send the headers
	@flush();
	@ob_flush();
?>
<html>
    <head>
	<?PHP backwpup_meta_no_cache(); ?>
	<title><?PHP _e('Do Job','backwpup');  ?></title>
    </head>
	<body style="font-family:monospace;font-size:12px;white-space:nowrap;">
	<?PHP
	backwpup_dojob($_GET['jobid']);
	?>
	</body>
</html>
<?PHP
} else {	
	header("HTTP/1.0 404 Not Found");
}
?>