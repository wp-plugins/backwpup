<?PHP
if ( !defined('ABSPATH') ) {
	/** Setup WordPress environment */
	require_once($_GET['ABSPATH'].'/wp-load.php');
}
//check_admin_referer('xmlexportwp');
require_once($_GET['ABSPATH'].'/wp-admin/includes/export.php');
export_wp();
?>