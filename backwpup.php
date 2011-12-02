<?php
/*
Plugin Name: BackWPup
Plugin URI: http://backwpup.com
Description: WordPress Backup and more...
Author: Daniel H&uuml;sken
Version: 2.5-Dev
Author URI: http://danielhuesken.de
License: GPL2
*/

/*  Copyright 2011  Daniel Hüsken  (email: mail@backwpup.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

//define some things
define('BACKWPUP_PLUGIN_BASENAME',dirname(plugin_basename(__FILE__)));
define('BACKWPUP_PLUGIN_BASEURL',plugins_url('',__FILE__));
define('BACKWPUP_VERSION', '2.5-Dev');
define('BACKWPUP_MIN_WORDPRESS_VERSION', '3.2');
define('BACKWPUP_USER_CAPABILITY', 'export');
define('BACKWPUP_MENU_PAGES', 'backwpup,backwpupeditjob,backwpupworking,backwpuplogs,backwpupbackups,backwpuptools,backwpupsettings');
if (!defined('BACKWPUP_DESTS')) {
	if (!function_exists('curl_init'))
		define('BACKWPUP_DESTS', 'FTP,MSAZURE,BOXNET');
	else
		define('BACKWPUP_DESTS', 'FTP,DROPBOX,SUGARSYNC,S3,GSTORAGE,RSC,MSAZURE,BOXNET');
}
if (!defined('AWS_CERTIFICATE_AUTHORITY'))
    define('AWS_CERTIFICATE_AUTHORITY', dirname(__FILE__).'/libs/cacert.pem');
//Load textdomain
load_plugin_textdomain('backwpup', false, BACKWPUP_PLUGIN_BASENAME.'/lang');
//Load some file
include_once(dirname(__FILE__).'/backwpup-functions.php');
include_once(dirname(__FILE__).'/backwpup-api.php');
//include_once(dirname(__FILE__).'/backwpup-job.php');
//include page functions
if (!empty($_GET['page']))
	$backwpup_manu_page=trim($_GET['page']);
if (defined('DOING_AJAX') and DOING_AJAX and !empty($_POST['backwpupajaxpage']))
	$backwpup_manu_page=trim($_POST['backwpupajaxpage']);
if (!empty($backwpup_manu_page) and in_array($backwpup_manu_page,explode(',',BACKWPUP_MENU_PAGES)) and is_file(dirname(__FILE__).'/pages/func_'.$backwpup_manu_page.'.php'))
	include_once(dirname(__FILE__).'/pages/func_'.$backwpup_manu_page.'.php');


class BackWPup {

	public function __construct() {
		register_deactivation_hook(__FILE__, array($this,'plugin_deactivate'));
		add_filter('cron_schedules', create_function('$schedules','$schedules["backwpup"]=array("interval"=>60,"display"=> __("BackWPup", "backwpup"));return $schedules;'));
		add_action('backwpup_cron', 'backwpup_cron',1);
		add_action('admin_bar_menu', 'backwpup_add_adminbar',100);
		if (is_multisite()) {
			add_action('network_admin_notices', create_function('','global $backwpup_admin_message;if (current_user_can(BACKWPUP_USER_CAPABILITY)) echo $backwpup_admin_message;'));
			add_action('network_admin_menu', array($this,'admin_menu'));
			add_action('wp_network_dashboard_setup', create_function('','wp_add_dashboard_widget("backwpup_dashboard_widget_logs", __("BackWPup Logs","backwpup"),"backwpup_dashboard_logs","backwpup_dashboard_logs_config");
																		 wp_add_dashboard_widget("backwpup_dashboard_widget_activejobs", __("BackWPup Active Jobs","backwpup"),"backwpup_dashboard_activejobs");'));
			add_filter('plugin_row_meta', 'backwpup_plugin_links',10,2);
		} else {
			add_action('admin_notices', create_function('','global $backwpup_admin_message;if (current_user_can(BACKWPUP_USER_CAPABILITY)) echo $backwpup_admin_message;'));
			add_action('admin_menu', array($this,'admin_menu'));
			add_action('wp_dashboard_setup', create_function('','if (!current_user_can(BACKWPUP_USER_CAPABILITY)) return;
																 wp_add_dashboard_widget("backwpup_dashboard_widget_logs", __("BackWPup Logs","backwpup"),"backwpup_dashboard_logs","backwpup_dashboard_logs_config");
																 wp_add_dashboard_widget("backwpup_dashboard_widget_activejobs", __("BackWPup Active Jobs","backwpup"),"backwpup_dashboard_activejobs");'));
			add_filter('plugin_action_links_'.BACKWPUP_PLUGIN_BASENAME.'/backwpup.php', 'backwpup_plugin_options_link');
			add_filter('plugin_row_meta', 'backwpup_plugin_links',10,2);
		}
		if (is_main_site())
			add_action('init', array($this,'plugin_init'));
	}

	public function plugin_init(){



	}

	public function plugin_deactivate() {
		global $wpdb;
		wp_clear_scheduled_hook('backwpup_cron');
		backwpup_update_option('DBVERSION','DBVERSION','0.0');
		$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main_name='TEMP'");
		$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main_name='WORKING'");
		$backwpupapi=new backwpup_api();
		$backwpupapi->delete();
	}

	public function admin_menu() {
		$page_hook=add_menu_page( __('BackWPup','backwpup'), __('BackWPup','backwpup'), BACKWPUP_USER_CAPABILITY, 'backwpup', array($this,'admin_page'), BACKWPUP_PLUGIN_BASEURL.'/css/BackWPup16.png');
		add_action('load-'.$page_hook, array($this,'admin_page_load'));
		$page_hook=add_submenu_page( 'backwpup', __('Jobs','backwpup'), __('Jobs','backwpup'), BACKWPUP_USER_CAPABILITY, 'backwpup', array($this,'admin_page') );
		add_action('load-'.$page_hook, array($this,'admin_page_load'));
		$page_hook=add_submenu_page( 'backwpup', __('Add New','backwpup'), __('Add New','backwpup'), BACKWPUP_USER_CAPABILITY, 'backwpupeditjob', array($this,'admin_page') );
		add_action('load-'.$page_hook, array($this,'admin_page_load'));
		$backupdata=backwpup_get_option('WORKING','DATA');
		if (!empty($backupdata)) {
			$page_hook=add_submenu_page( 'backwpup', __('Working Job','backwpup'), __('Working Job','backwpup'), BACKWPUP_USER_CAPABILITY, 'backwpupworking', array($this,'admin_page') );
			add_action('load-'.$page_hook, array($this,'admin_page_load'));
		}
		elseif (isset($_GET['page']) and $_GET['page']=='backwpupworking') {
			$page_hook=add_submenu_page( 'backwpup', __('Watch Log','backwpup'), __('Watch Log','backwpup'), BACKWPUP_USER_CAPABILITY, 'backwpupworking', array($this,'admin_page') );
			add_action('load-'.$page_hook, array($this,'admin_page_load'));
		}
		$page_hook=add_submenu_page( 'backwpup', __('Logs','backwpup'), __('Logs','backwpup'), BACKWPUP_USER_CAPABILITY, 'backwpuplogs', array($this,'admin_page') );
		add_action('load-'.$page_hook, array($this,'admin_page_load'));
		$page_hook=add_submenu_page( 'backwpup', __('Backups','backwpup'), __('Backups','backwpup'), BACKWPUP_USER_CAPABILITY, 'backwpupbackups', array($this,'admin_page') );
		add_action('load-'.$page_hook, array($this,'admin_page_load'));
		$page_hook=add_submenu_page( 'backwpup', __('Tools','backwpup'), __('Tools','backwpup'), BACKWPUP_USER_CAPABILITY, 'backwpuptools', array($this,'admin_page') );
		add_action('load-'.$page_hook, array($this,'admin_page_load'));
		$page_hook=add_submenu_page( 'backwpup', __('Settings','backwpup'), __('Settings','backwpup'), BACKWPUP_USER_CAPABILITY, 'backwpupsettings', array($this,'admin_page') );
		add_action('load-'.$page_hook, array($this,'admin_page_load'));
	}

	public function admin_page() {
		global $backwpup_message,$backwpup_cfg,$backwpup_listtable;
		if (!current_user_can(BACKWPUP_USER_CAPABILITY))
			return;
		if (!empty($_GET['page']) and in_array($_GET['page'],explode(',',BACKWPUP_MENU_PAGES)) and is_file(dirname(__FILE__).'/pages/page_'.$_GET['page'].'.php'))
			include_once(dirname(__FILE__).'/pages/page_'.$_GET['page'].'.php');
	}

	public function admin_page_load() {
		global $backwpup_message,$backwpup_cfg,$backwpup_listtable;
		//check user premessions
		if (!current_user_can(BACKWPUP_USER_CAPABILITY))
			return;
		//check called page exists
		if (empty($_GET['page']) or !in_array($_GET['page'],explode(',',BACKWPUP_MENU_PAGES)) or !is_file(dirname(__FILE__).'/pages/page_'.$_GET['page'].'.php'))
			return;
		get_current_screen()->add_help_tab( array(
			'id'      => 'plugininfo',
			'title'   => __('Plugin Info','backwpup'),
			'content' =>
			'<p><a href="http://backwpup.com" target="_blank">BackWPup</a> v. '.BACKWPUP_VERSION.', <a href="http://www.gnu.org/licenses/gpl-2.0.html" target="_blank">GPL2</a> &copy '.date('Y').' <a href="http://danielhuesken.de" target="_blank">Daniel H&uuml;sken</a></p><p>'.__('BackWPup comes with ABSOLUTELY NO WARRANTY. This is free software, and you are welcome to redistribute it under certain conditions.','backwpup').'</p>'
		) );
		get_current_screen()->set_help_sidebar(
			'<p><strong>' . __( 'For more information:','backwpup' ) . '</strong></p>' .
			'<p>' . __( '<a href="http://backwpup.com/manual/" target="_blank">Documentation</a>','backwpup' ) . '</p>' .
			'<p>' . __( '<a href="http://backwpup.com/faq/" target="_blank">FAQ</a>','backwpup' ) . '</p>' .
			'<p>' . __( '<a href="http://backwpup.com/forums/" target="_blank">Support Forums</a>','backwpup' ) . '</p>' .
			'<p>' . __( '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=Q3QSVRSFXBLSE" target="_blank">Donate</a>','backwpup' ) . '</p>' .
			'<p>' . __( '<a href="https://flattr.com/thing/345067/BackWPup" target="_blank">Flattr</a>','backwpup' ) . '</p>'
		);
		//add css for Admin Section
		if (is_file(dirname(__FILE__).'/css/'.$_GET['page'].'.css')) {
			if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG)
				wp_enqueue_style($_GET['page'],BACKWPUP_PLUGIN_BASEURL.'/css/'.$_GET['page'].'.css','',time(),'screen');
			else
				wp_enqueue_style($_GET['page'],BACKWPUP_PLUGIN_BASEURL.'/css/'.$_GET['page'].'.css','',BACKWPUP_VERSION,'screen');
		}
		//add java for Admin Section
		if (is_file(dirname(__FILE__).'/js/'.$_GET['page'].'.js')) {
			if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG)
				wp_enqueue_script($_GET['page'],BACKWPUP_PLUGIN_BASEURL.'/js/'.$_GET['page'].'.js','',time(),true);
			else
				wp_enqueue_script($_GET['page'],BACKWPUP_PLUGIN_BASEURL.'/js/'.$_GET['page'].'.js','',BACKWPUP_VERSION,true);
		}
		//include header
		if (is_file(dirname(__FILE__).'/pages/header_'.$_GET['page'].'.php'))
			include_once(dirname(__FILE__).'/pages/header_'.$_GET['page'].'.php');
	}

}

new BackWPup();
?>
