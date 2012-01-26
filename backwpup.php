<?php
/*
Plugin Name: BackWPup
Plugin URI: http://backwpup.com
Description: WordPress Backup and more...
Author: Daniel H&uuml;sken
Author URI: http://danielhuesken.de
Version: 3.0-Dev
Text Domain: backwpup
Domain Path: /lang/
Network: true
License: GPLv3
*/

/*
	Copyright 2009-2012  Daniel Hüsken  (email: mail@backwpup.com)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

//check constance's
if (!defined('BACKWPUP_DESTS')) {
	if (!function_exists('curl_init'))
		define('BACKWPUP_DESTS', 'FOLDER,MAIL,FTP,MSAZURE,BOXNET');
	else
		define('BACKWPUP_DESTS', 'FOLDER,MAIL,FTP,DROPBOX,SUGARSYNC,S3,GSTORAGE,RSC,MSAZURE');
}
if (!defined('FS_CHMOD_DIR'))
	define('FS_CHMOD_DIR', 0755 );

//Load functions file
include_once(dirname(__FILE__).'/backwpup-functions.php');

/**
 *
 * Main BackWPup Class that auto load all needed classes
 *
 */
class BackWPup {

	/**
	 *
	 * Set needed filters and actions and load all needed
	 *
	 * @return \BackWPup
	 */
	public function __construct() {
		global $wp_version;
		//Check minimal versions
		if (version_compare($wp_version, '3.1', '<')) { // check WP Version
			add_action('admin_notices', create_function('','echo "<div id=\"message\" class=\"error fade\"><strong>".__("BackWPup:", "backwpup")."</strong><br />".__("- WordPress 3.1 or higher is needed!","backwpup")."</div>";'));
			return;
		}
		if (version_compare(phpversion(), '5.2.4', '<'))  {// check PHP Version
			add_action('admin_notices', create_function('','echo "<div id=\"message\" class=\"error fade\"><strong>".__("BackWPup:", "backwpup")."</strong><br />".__("- PHP 5.2.4 or higher is needed!","backwpup")."</div>";'));
			return;
		}
		//register auto load
		spl_autoload_register(array($this,'autoload'));
		//not load translations for other text domains reduces memory and load time
		if ((defined('DOING_CRON') && DOING_CRON ) || (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action']) && in_array($_POST['action'],array('backwpup_show_info','backwpup_working','backwpup_cron_text','backwpup_aws_buckets','backwpup_gstorage_buckets','backwpup_rsc_container','backwpup_msazure_container','backwpup_sugarsync_root'))))
			add_filter('override_load_textdomain', create_function('$default, $domain, $mofile','if ($domain=="backwpup") return $default; else return true;'),1,3);
		//Load text domain
		load_plugin_textdomain('backwpup', false, dirname(plugin_basename( __FILE__ )).'/lang');
		//Multisite or normal actions/filter
		if (is_multisite()) {
			add_action('network_admin_menu', array($this,'admin_menu'));
			add_action('wp_network_dashboard_setup', array($this,'dashboard_setup'));
			add_filter('plugin_row_meta', array($this,'plugin_links'),10,2);
		} else {
			add_action('admin_menu', array($this,'admin_menu'));
			add_action('wp_dashboard_setup', array($this,'dashboard_setup'));
			add_filter('plugin_action_links_'.plugin_basename(__FILE__), create_function('$links','array_unshift($links,"<a href=\"".backwpup_admin_url("admin.php")."?page=backwpup\" title=\"". __("Go to Settings Page","backwpup") ."\" class=\"edit\">". __("Settings","backwpup") ."</a>");return $links;'));
			add_filter('plugin_row_meta', array($this,'plugin_links'),10,2);
		}
		//WP-Cron
		add_filter('cron_schedules', create_function('$schedules','$schedules["backwpup"]=array("interval"=>240,"display"=> __("BackWPup", "backwpup"));return $schedules;'));
		add_action('backwpup_cron',  array('BackWPup_Cron','run'));
		if (is_main_site()) {
			//activation/deactivation/uninstall hook
			register_activation_hook(__FILE__, array('BackWPup_Init','upgrade'));
			register_deactivation_hook(__FILE__, array('BackWPup_Init','deactivate'));
			register_uninstall_hook(__FILE__, array('BackWPup_Init','uninstall'));
			//Things that must do in plugin int
			add_action('init',array($this,'plugin_init'),1);
			//ajax actions
			add_action('wp_ajax_backwpup_show_info', array('BackWPup_Ajax_Fileinfo','get_object'));
			add_action('wp_ajax_backwpup_working', array('BackWPup_Ajax_Working','working'));
			add_action('wp_ajax_backwpup_cron_text', array('BackWPup_Ajax_Editjob','cron_text'));
			add_action('wp_ajax_backwpup_aws_buckets', array('BackWPup_Ajax_Editjob','aws_buckets'));
			add_action('wp_ajax_backwpup_gstorage_buckets', array('BackWPup_Ajax_Editjob','gstorage_buckets'));
			add_action('wp_ajax_backwpup_rsc_container', array('BackWPup_Ajax_Editjob','rsc_container'));
			add_action('wp_ajax_backwpup_msazure_container', array('BackWPup_Ajax_Editjob','msazure_container'));
			add_action('wp_ajax_backwpup_sugarsync_root', array('BackWPup_Ajax_Editjob','sugarsync_root'));
		}
		//load API for update checks and so on
		new BackWPup_Api();
	}

	/**
	 *
	 * include not existing classes automatically
	 *
	 * @param string $class_name Class to include
	 */
	public function autoload($class_name) {
		//WordPress classes to load
		if ('WP_List_Table' == $class_name)
			include_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
		if ('PclZip' == $class_name)
			include_once(ABSPATH . 'wp-admin/includes/class-pclzip.php');
		//Include external Libs
		if ('CF_Authentication' == $class_name)
			include_once(dirname(__FILE__).'/libs/rackspace/cloudfiles.php');
		if ('Microsoft_WindowsAzure_Storage_Blob' == $class_name)
			include_once(dirname(__FILE__).'/libs/Microsoft/AutoLoader.php');
		if ('AmazonS3' == $class_name)
			include_once(dirname(__FILE__).'/libs/aws/sdk.class.php');
		//BackWPup class loading
		if (substr($class_name,0,9)=='BackWPup_') {
			$inc = '/class/'.str_replace('BackWPup_','',$class_name).'.php';
			if (file_exists(dirname(__FILE__).$inc))
				include_once dirname(__FILE__).$inc;
		}
	}

	/**
	 *
	 * Add Menu entry's
	 *
	 * @return nothing
	 */
	public function admin_menu() {
		add_menu_page( __('BackWPup','backwpup'), __('BackWPup','backwpup'), 'backwpup', 'backwpup', array('BackWPup_Page_Backwpup','page'), plugins_url('',__FILE__).'/css/BackWPup16.png');
		$page_hook=add_submenu_page( 'backwpup', __('Jobs','backwpup'), __('Jobs','backwpup'), 'backwpup', 'backwpup',array('BackWPup_Page_Backwpup','page') );
		add_action('load-'.$page_hook, array('BackWPup_Page_Backwpup','load'));
		$page_hook=add_submenu_page( 'backwpup', __('Add New','backwpup'), __('Add New','backwpup'), 'backwpup', 'backwpupeditjob', array('BackWPup_Page_Editjob','page') );
		add_action('load-'.$page_hook, array('BackWPup_Page_Editjob','load'));
		if (backwpup_get_workingdata(false)) {
			$page_hook=add_submenu_page( 'backwpup', __('Working Job','backwpup'), __('Working Job','backwpup'), 'backwpup', 'backwpupworking', array('BackWPup_Page_Working','page') );
			add_action('load-'.$page_hook, array('BackWPup_Page_Working','load'));
		}
		elseif (isset($_GET['page']) && $_GET['page']=='backwpupworking') {
			$page_hook=add_submenu_page( 'backwpup', __('Watch Log','backwpup'), __('Watch Log','backwpup'), 'backwpup', 'backwpupworking', array('BackWPup_Page_Working','page') );
			add_action('load-'.$page_hook, array('BackWPup_Page_Working','load'));
		}
		$page_hook=add_submenu_page( 'backwpup', __('Logs','backwpup'), __('Logs','backwpup'), 'backwpup', 'backwpuplogs', array('BackWPup_Page_Logs','page') );
		add_action('load-'.$page_hook, array('BackWPup_Page_Logs','load'));
		$page_hook=add_submenu_page( 'backwpup', __('Backups','backwpup'), __('Backups','backwpup'), 'backwpup', 'backwpupbackups', array('BackWPup_Page_Backups','page') );
		add_action('load-'.$page_hook, array('BackWPup_Page_Backups','load'));
		$page_hook=add_submenu_page( 'backwpup', __('Tools','backwpup'), __('Tools','backwpup'), 'backwpup', 'backwpuptools', array('BackWPup_Page_Tools','page') );
		add_action('load-'.$page_hook, array('BackWPup_Page_Tools','load'));
		$page_hook=add_submenu_page( 'backwpup', __('Settings','backwpup'), __('Settings','backwpup'), 'backwpup', 'backwpupsettings', array('BackWPup_Page_Settings','page') );
		add_action('load-'.$page_hook, array('BackWPup_Page_Settings','load'));
	}

	/**
	 *
	 * Add Links in Plugins Menu to BackWPup
	 *
	 * @param $links
	 * @param $file
	 * @return array
	 */
	public function plugin_links($links, $file) {
		if ($file == plugin_basename(__FILE__)) {
			$links[] = __( '<a href="http://backwpup.com/manual/" target="_blank">Documentation</a>','backwpup' );
			$links[] = __( '<a href="http://backwpup.com/faq/" target="_blank">FAQ</a>','backwpup' );
			$links[] = __( '<a href="http://backwpup.com/forums/" target="_blank">Support Forums</a>','backwpup' );
			$links[] = __( '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=Q3QSVRSFXBLSE" target="_blank">Donate</a>','backwpup' );
			$links[] = __( '<a href="https://plus.google.com/112659782148359984250/" target="_blank">Google+</a>','backwpup' );
		}
		return $links;
	}

	/**
	 *
	 * Dashboard Widgets setup
	 *
	 * @return nothing
	 */
	public function dashboard_setup() {
		if (!current_user_can('backwpup'))
			return;
		wp_add_dashboard_widget("backwpup_dashboard_widget_logs", __("BackWPup Logs","backwpup"),array('BackWPup_Dashboard','dashboard_logs'),array('BackWPup_Dashboard','dashboard_logs_config'));
		wp_add_dashboard_widget("backwpup_dashboard_widget_activejobs", __("BackWPup Active Jobs","backwpup"),array('BackWPup_Dashboard','dashboard_activejobs'));
	}

	/**
	 *
	 * Plugin init function
	 *
	 * @return nothing
	 */
	public function plugin_init() {
		//start upgrade if needed
		if (is_main_site() && backwpup_get_option('backwpup','md5')!=md5_file(__FILE__))
			BackWPup_Init::upgrade();
		//add admin bar. Works only in init
		if (is_main_site() && is_admin_bar_showing() && !defined('DOING_CRON') && current_user_can('backwpup') && backwpup_get_option('cfg','showadminbar')) {
			wp_enqueue_style("backwpupadmin",plugins_url('',__FILE__)."/css/adminbar.css","",backwpup_get_version(),"screen");
			add_action('admin_bar_menu', array('BackWPup_Adminbar','adminbar'),100);
		}
		//bypass Google Analytics by Yoast oauth
		if (isset($_GET['oauth_token']) && $_GET['page']=='backwpupeditjob') {
			$_GET['oauth_token_backwpup']=$_GET['oauth_token'];
			unset($_GET['oauth_token']);
			unset($_REQUEST['oauth_token']);
		}
	}
}
new BackWPup();