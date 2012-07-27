<?PHP
if (!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN')) {
	die();
}

include(ABSPATH . WPINC . '/version.php'); // include an unmodified $wp_version
wp_remote_post( 'https://api.backwpup.com', array( 'sslverify' => false, 'body'=>array('URL'=>home_url(),'ACTION'=>'delete'), 'user-agent'=>'BackWPup/0.0.0; WordPress/'.$wp_version.'; ' . home_url()));
delete_option('backwpup');
delete_option('backwpup_jobs');