<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function backwpup_plugin_deactivate() {
	global $wpdb;
	wp_clear_scheduled_hook('backwpup_cron');
	$wpdb->query("UPDATE ".$wpdb->prefix."backwpup SET value='0.0' WHERE main='cfg' and name='dbversion' LIMIT 1");
	$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main='temp'");
	$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main='working'");
	//include_once(dirname(__FILE__).'/api.php');
	do_action('backwpup_api_delete');
}
?>