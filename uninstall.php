<?PHP
if (!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN')) {
	die();
}
global $wpdb;
$wpdb->query("DROP TABLE `".$wpdb->prefix."backwpup`");
require_once(dirname(__FILE__).'/libs/backwpup_api.php');
$backwpupapi=new backwpup_api();
$backwpupapi->delete();
?>
