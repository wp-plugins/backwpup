<?PHP
if (!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN'))
	die();
global $wpdb;
require_once(dirname(__FILE__).'/core/api.php');
do_action('backwpup_api_delete');
$wpdb->query("DROP TABLE `".$wpdb->prefix."backwpup`");
if (file_exists(backwpup_get_option('cfg','tempfolder').'.backwpup_working_'.substr(md5(ABSPATH),16)))
	unlink(backwpup_get_option('cfg','tempfolder').'.backwpup_working_'.substr(md5(ABSPATH),16));
?>
