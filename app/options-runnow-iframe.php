<?PHP
if (is_dir($_GET['ABSPATH'])) {
	require_once($_GET['ABSPATH'].'/wp-load.php'); /** Setup WordPress environment */
	check_admin_referer('dojob-now_' . (int)$_GET['jobid']);
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
	<meta http-equiv="expires" content="0">
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