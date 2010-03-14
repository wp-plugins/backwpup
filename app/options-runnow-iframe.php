<?PHP
if ( !defined('ABSPATH') ) {
	/** Setup WordPress environment */
	require_once($_GET['ABSPATH'].'/wp-load.php');
}
?>
<html>
    <head>
    </head>
	<body style="font-family:monospace;font-size:12px;white-space:nowrap;">
	<?PHP
	check_admin_referer('dojob-now_' . (int)$_GET['jobid']);
	ignore_user_abort(true);
	// flush any buffers and send the headers
	ob_start();
	while (@ob_end_flush());
	flush();	
	backwpup_dojob($_GET['jobid']);
	?>
	</body>
</html>