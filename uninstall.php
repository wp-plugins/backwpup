<?PHP
if (!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}
delete_option('backwpup');
delete_option('backwpup_jobs');
delete_option('backwpup_last_activate');
?>
