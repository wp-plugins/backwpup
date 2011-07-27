<?PHP
if (!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN')) {
	die();
}

global $wp_version;
$blugurl=get_option('siteurl');
if (defined('WP_SITEURL'))
	$blugurl=WP_SITEURL;
wp_remote_post( 'http://api.backwpup.com', array('timeout' => 10, 'blocking' => false, 'sslverify' => false, 'body'=>array('URL'=>$blugurl,'EMAIL'=>get_option('admin_email'),'WP_VER'=>$wp_version,'ACTIVE'=>'D'), 'user-agent'=>'BackWPup'));
delete_option('backwpup');
delete_option('backwpup_jobs');
delete_option('backwpup_last_activate');

?>
