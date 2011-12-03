<?PHP
if (!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN'))
	die();
require_once(dirname(__FILE__).'/backwpup-api.php');
$backwpupapi=new BackWPup_api();
$backwpupapi->delete();
global $wpdb;
$wpdb->query("DROP TABLE `".$wpdb->prefix."backwpup`");
?>
