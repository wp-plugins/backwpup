<?PHP
if (file_exists(trim($_GET['wpabs']).'wp-load.php') and is_numeric(trim($_GET['jobid']))) {
	@ini_set('zlib.output_compression', 0);
	//disable w3tc chache
	define('DONOTCACHEPAGE', true);
	define('DONOTCACHEDB', true);
	define('DONOTMINIFY', true);
	define('DONOTCDN', true);
	define('DONOTCACHCEOBJECT', true);
	//Quick Cache
	define("QUICK_CACHE_ALLOWED", false);
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
	<!--dynamic-cached-content-->
	<!--mfunc backwpup_dojob(<?PHP echo $_GET['jobid'];?>) -->
	<?PHP
	@flush();
	@ob_flush();
	backwpup_dojob($_GET['jobid']);
	?>
	<!--/mfunc-->
	<!--/dynamic-cached-content-->
	</body>
</html>
<?PHP
} else {	
	header("HTTP/1.0 404 Not Found");
	die();
}
?>