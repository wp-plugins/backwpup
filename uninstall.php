<?PHP
if (!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN'))
	die();
global $wpdb;
require_once(dirname(__FILE__).'/backwpup-api.php');
$backwpupapi->delete();
$wpdb->query("DROP TABLE `".$wpdb->prefix."backwpup`");
?>
