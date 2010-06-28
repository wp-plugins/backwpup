<?PHP
if (is_dir($_GET['ABSPATH']) and is_numeric($_GET['jobid'])) {
	require_once($_GET['ABSPATH'].'/wp-load.php'); /** Setup WordPress environment */
	check_admin_referer('dojob-now_' . (int)$_GET['jobid']);
	backwpup_send_no_cache_header();
	ignore_user_abort(true);
	// flush any buffers and send the headers
	@apache_setenv('no-gzip', 1);
	@ini_set('zlib.output_compression', 0);
	@ini_set('implicit_flush', 1);
	@flush();
	@ob_flush();
	
?>
<html>
    <head>
	<?PHP  backwpup_meta_no_cache(); ?>
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