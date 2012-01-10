<?PHP
if (!defined('ABSPATH'))
	die();

function backwpup_plugin_deactivate() {
	global $wpdb,$backwpupapi;
	wp_clear_scheduled_hook('backwpup_cron');
	$wpdb->query("UPDATE ".$wpdb->prefix."backwpup SET value='0.0' WHERE main='cfg' and name='dbversion' LIMIT 1");
	$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main='temp'");
	$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main='working'");
	//include_once(dirname(__FILE__).'/api.php');
	$backwpupapi->delete();
}
?>