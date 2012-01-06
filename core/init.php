<?PHP
if (!defined('ABSPATH'))
	die();

function backwpup_plugin_init() {
	global $wpdb,$backwpup_cfg;
	//load cfg
	$backwpup_cfg['dbversion']='0.0';
	$cfgs=$wpdb->get_results("SELECT name,value FROM `".$wpdb->prefix."backwpup` WHERE `main_name`='cfg'");
	if (is_array($cfgs)) {
		foreach ($cfgs as $cfg)
			$backwpup_cfg[$cfg->name]=maybe_unserialize($cfg->value);
	}
	//start upgrade if needed
	if ($backwpup_cfg['dbversion']!=BACKWPUP_VERSION) {
		require_once(dirname(__FILE__).'/upgrade.php');
		backwpup_upgrade();
	}
	//add admin bar work only in init
	if (!defined('DOING_CRON') and $backwpup_cfg['showadminbar'] and current_user_can(BACKWPUP_USER_CAPABILITY) and is_admin_bar_showing()) {
		wp_enqueue_style("backwpupadmin",BACKWPUP_PLUGIN_BASEURL."/css/adminbar.css","",BACKWPUP_VERSION,"screen");
		include_once(dirname(__FILE__).'/adminbar.php');
	}
}
add_action('init','backwpup_plugin_init');
//load Api for update checks and so on
include_once(dirname(__FILE__).'/api.php');
?>