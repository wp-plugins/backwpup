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

if ( ! defined( 'ABSPATH' ) ) {
	header( $_SERVER["SERVER_PROTOCOL"] . " 404 Not Found" );
	header( "Status: 404 Not Found" );
	die();
}

if ( ! class_exists( 'BackWPup' ) ) {

	//check const for backup destinations or set it
	if ( ! defined( 'BACKWPUP_DESTS' ) ) {
		if ( ! function_exists( 'curl_init' ) )
			define('BACKWPUP_DESTS', 'FOLDER,MAIL,FTP,MSAZURE,BOXNET');
		else
			define('BACKWPUP_DESTS', 'FOLDER,MAIL,FTP,DROPBOX,SUGARSYNC,S3,GSTORAGE,RSC,MSAZURE');
	}

	//Load functions file
	include_once(dirname( __FILE__ ) . '/inc/functions.php');

	//Start Plugin
	add_action( 'plugins_loaded', array( 'BackWPup', 'get_object' ) );

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
			if ( version_compare( $wp_version, '3.1', '<' ) ) { // check WP Version
				add_action( 'admin_notices', create_function( '', 'echo "<div id=\"message\" class=\"error fade\"><strong>".__("BackWPup:", "backwpup")."</strong><br />".__("- WordPress 3.1 or higher is needed!","backwpup")."</div>";' ) );
				return;
			}
			if ( version_compare( phpversion(), '5.2.4', '<' ) ) { // check PHP Version
				add_action( 'admin_notices', create_function( '', 'echo "<div id=\"message\" class=\"error fade\"><strong>".__("BackWPup:", "backwpup")."</strong><br />".__("- PHP 5.2.4 or higher is needed!","backwpup")."</div>";' ) );
				return;
			}
			//textdomain overide
			add_filter( 'override_load_textdomain' , array($this,'overide_textdomain'), 1, 3 );
			//Load text domain
			load_plugin_textdomain( 'backwpup', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
			//register auto load
			spl_autoload_register( array( $this, 'autoloader' ) );
			//WP-Cron
			add_action( 'backwpup_cron', array( 'BackWPup_Cron', 'run' ) );
			if ( is_main_site() ) {
				//start upgrade if needed
				if ( get_option( 'backwpup_file_md5' ) != md5_file( __FILE__ ) )
					BackWPup_Init::upgrade();
				//Multisite or normal actions/filter
				if ( is_multisite() ) {
					add_action( 'network_admin_menu', array( $this, 'admin_menu' ) );
					add_action( 'wp_network_dashboard_setup', array( $this, 'dashboard_setup' ) );
					add_filter( 'plugin_row_meta', array( $this, 'plugin_links' ), 10, 2 );
				} else {
					add_action( 'admin_menu', array( $this, 'admin_menu' ) );
					add_action( 'wp_dashboard_setup', array( $this, 'dashboard_setup' ) );
					add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), create_function( '$links', 'array_unshift($links,"<a href=\"".backwpup_admin_url("admin.php")."?page=backwpup\" title=\"". __("Go to Settings Page","backwpup") ."\" class=\"edit\">". __("Settings","backwpup") ."</a>");return $links;' ) );
					add_filter( 'plugin_row_meta', array( $this, 'plugin_links' ), 10, 2 );
				}
				//activation/deactivation/uninstall hook
				register_activation_hook( __FILE__, array( 'BackWPup_Init', 'upgrade' ) );
				register_deactivation_hook( __FILE__, array( 'BackWPup_Init', 'deactivate' ) );
				register_uninstall_hook( __FILE__, array( 'BackWPup_Init', 'uninstall' ) );
				//Things that must do in plugin init
				add_action( 'init', array( $this, 'plugin_init' ) );
				//load API
				add_action( 'init', array( 'BackWPup_Api', 'get_object' ) );
				//ajax actions  now in ajax.php
				//add_action( 'wp_ajax_backwpup_show_info', array( 'BackWPup_Ajax_Fileinfo', 'get_object' ) );
				//add_action( 'wp_ajax_backwpup_working', array( 'BackWPup_Ajax_Working', 'working' ) );
				//add_action( 'wp_ajax_backwpup_cron_text', array( 'BackWPup_Ajax_Editjob', 'cron_text' ) );
				//add_action( 'wp_ajax_backwpup_aws_buckets', array( 'BackWPup_Ajax_Editjob', 'aws_buckets' ) );
				//add_action( 'wp_ajax_backwpup_gstorage_buckets', array( 'BackWPup_Ajax_Editjob', 'gstorage_buckets' ) );
				//add_action( 'wp_ajax_backwpup_rsc_container', array( 'BackWPup_Ajax_Editjob', 'rsc_container' ) );
				//add_action( 'wp_ajax_backwpup_msazure_container', array( 'BackWPup_Ajax_Editjob', 'msazure_container' ) );
				//add_action( 'wp_ajax_backwpup_sugarsync_root', array( 'BackWPup_Ajax_Editjob', 'sugarsync_root' ) );
				//bypass Google Analytics by Yoast oauth
				if ( isset($_GET['oauth_token']) && $_GET['page'] == 'backwpupeditjob' ) {
					$_GET['oauth_token_backwpup'] = $_GET['oauth_token'];
					unset($_GET['oauth_token']);
					unset($_REQUEST['oauth_token']);
				}
			}
		}

		/**
		 * @static
		 * @return \BackWPup
		 */
		public static function get_object() {
			return new self;
		}

		/**
		 * @static
		 *
		 * @param bool $getdata
		 *
		 * @return array|string
		 */
		public static function get_plugin_data($getdata=false) {

			$default_headers = array(
				'Name' => 'Plugin Name',
				'PluginURI' => 'Plugin URI',
				'Version' => 'Version',
				'Description' => 'Description',
				'Author' => 'Author',
				'AuthorURI' => 'Author URI',
				'TextDomain' => 'Text Domain',
				'DomainPath' => 'Domain Path',
			);

			$plugin_data = get_file_data( __FILE__, $default_headers, 'plugin' );
			$plugin_data['BaseName']=plugin_basename(__FILE__);
			$plugin_data['Folder']=dirname( plugin_basename( __FILE__ ) );
			$plugin_data['URL']=plugins_url( '', __FILE__ );

			if (empty($plugin_data['Version']))
				$plugin_data['Version']='0.0';

			if (!$getdata)
				return $plugin_data;
			return $plugin_data[$getdata];
		}

		/**
		 *
		 * include not existing classes automatically
		 *
		 * @param string $class_name Class to include
		 *
		 * @return bool if class loaded or not
		 */
		public static function autoloader( $class_name ) {
			//WordPress classes loader
			$wpclass='/class-'.strtolower(str_replace('_','-',$class_name)).'.php';
			if ( file_exists(ABSPATH .'wp-admin/includes'.$wpclass) ) {
				include_once(ABSPATH .'wp-admin/includes'.$wpclass);
				return true;
			}
			if ( file_exists(ABSPATH . WPINC . $wpclass) ) {
				include_once(ABSPATH . WPINC . $wpclass);
				return true;
			}
			//External libs to load
			if ( 'CF_Authentication' == $class_name ) {
				include_once(dirname( __FILE__ ) . '/sdk/rackspace/cloudfiles.php');
				return true;
			}
			if ( 'Microsoft_WindowsAzure_Storage_Blob' == $class_name ) {
				include_once(dirname( __FILE__ ) . '/sdk/Microsoft/AutoLoader.php');
				return true;
			}
			if ( 'AmazonS3' == $class_name ) {
				include_once(dirname( __FILE__ ) . '/sdk/aws/sdk.class.php');
				return true;
			}
			//BackWPup classes to load
			if ( substr( $class_name, 0, 9 ) == 'BackWPup_' ) {
				$inc = dirname( __FILE__ ) . '/inc/class-' . strtolower( str_replace( array( 'BackWPup_', '_' ), array( '', '-' ), $class_name ) ) . '.php';
				if ( file_exists( $inc ) ) {
					include_once($inc);
					return true;
				}
			}
			return false;
		}

		/**
		 *
		 * Add Menu entry's
		 *
		 * @return nothing
		 */
		public function admin_menu() {
			add_menu_page( __( 'BackWPup', 'backwpup' ), __( 'BackWPup', 'backwpup' ), 'backwpup', 'backwpup', array( 'BackWPup_Page_Backwpup', 'page' ), plugins_url( '', __FILE__ ) . '/css/BackWPup16.png' );
			$page_hook = add_submenu_page( 'backwpup', __( 'Jobs', 'backwpup' ), __( 'Jobs', 'backwpup' ), 'backwpup', 'backwpup', array( 'BackWPup_Page_Backwpup', 'page' ) );
			add_action( 'load-' . $page_hook, array( 'BackWPup_Page_Backwpup', 'load' ) );
			add_action('admin_print_scripts-' . $page_hook,  array( 'BackWPup_Page_Backwpup', 'javascript' ));
			add_action('admin_print_styles-' . $page_hook,  array( 'BackWPup_Page_Backwpup', 'css' ));
			$page_hook = add_submenu_page( 'backwpup', __( 'Add New', 'backwpup' ), __( 'Add New', 'backwpup' ), 'backwpup', 'backwpupeditjob', array( 'BackWPup_Page_Editjob', 'page' ) );
			add_action( 'load-' . $page_hook, array( 'BackWPup_Page_Editjob', 'load' ) );
			add_action('admin_print_scripts-' . $page_hook,  array( 'BackWPup_Page_Editjob', 'javascript' ));
			add_action('admin_print_styles-' . $page_hook,  array( 'BackWPup_Page_Editjob', 'css' ));
			if ( backwpup_get_workingdata( false ) ) {
				$page_hook = add_submenu_page( 'backwpup', __( 'Working Job', 'backwpup' ), __( 'Working Job', 'backwpup' ), 'backwpup', 'backwpupworking', array( 'BackWPup_Page_Working', 'page' ) );
				add_action( 'load-' . $page_hook, array( 'BackWPup_Page_Working', 'load' ) );
				add_action('admin_print_scripts-' . $page_hook,  array( 'BackWPup_Page_Working', 'javascript' ));
				add_action('admin_print_styles-' . $page_hook,  array( 'BackWPup_Page_Working', 'css' ));
			}
			elseif ( isset($_GET['page']) && $_GET['page'] == 'backwpupworking' ) {
				$page_hook = add_submenu_page( 'backwpup', __( 'Watch Log', 'backwpup' ), __( 'Watch Log', 'backwpup' ), 'backwpup', 'backwpupworking', array( 'BackWPup_Page_Working', 'page' ) );
				add_action( 'load-' . $page_hook, array( 'BackWPup_Page_Working', 'load' ) );
				add_action('admin_print_scripts-' . $page_hook,  array( 'BackWPup_Page_Working', 'javascript' ));
				add_action('admin_print_styles-' . $page_hook,  array( 'BackWPup_Page_Working', 'css' ));
			}
			$page_hook = add_submenu_page( 'backwpup', __( 'Logs', 'backwpup' ), __( 'Logs', 'backwpup' ), 'backwpup', 'backwpuplogs', array( 'BackWPup_Page_Logs', 'page' ) );
			add_action( 'load-' . $page_hook, array( 'BackWPup_Page_Logs', 'load' ) );
			add_action('admin_print_styles-' . $page_hook,  array( 'BackWPup_Page_Logs', 'css' ));
			$page_hook = add_submenu_page( 'backwpup', __( 'Backups', 'backwpup' ), __( 'Backups', 'backwpup' ), 'backwpup', 'backwpupbackups', array( 'BackWPup_Page_Backups', 'page' ) );
			add_action( 'load-' . $page_hook, array( 'BackWPup_Page_Backups', 'load' ) );
			add_action('admin_print_styles-' . $page_hook,  array( 'BackWPup_Page_Backups', 'css' ));
			$page_hook = add_submenu_page( 'backwpup', __( 'Tools', 'backwpup' ), __( 'Tools', 'backwpup' ), 'backwpup', 'backwpuptools', array( 'BackWPup_Page_Tools', 'page' ) );
			add_action( 'load-' . $page_hook, array( 'BackWPup_Page_Tools', 'load' ) );
			add_action('admin_print_styles-' . $page_hook,  array( 'BackWPup_Page_Tools', 'css' ));
			$page_hook = add_submenu_page( 'backwpup', __( 'Settings', 'backwpup' ), __( 'Settings', 'backwpup' ), 'backwpup', 'backwpupsettings', array( 'BackWPup_Page_Settings', 'page' ) );
			add_action( 'load-' . $page_hook, array( 'BackWPup_Page_Settings', 'load' ) );
			add_action('admin_print_styles-' . $page_hook,  array( 'BackWPup_Page_Settings', 'css' ));
		}

		/**
		 *
		 * Add Links in Plugins Menu to BackWPup
		 *
		 * @param $links
		 * @param $file
		 *
		 * @return array
		 */
		public function plugin_links( $links, $file ) {
			if ( $file == plugin_basename( __FILE__ ) ) {
				$links[] = __( '<a href="http://backwpup.com/manual/" target="_blank">Documentation</a>', 'backwpup' );
				$links[] = __( '<a href="http://backwpup.com/faq/" target="_blank">FAQ</a>', 'backwpup' );
				$links[] = __( '<a href="http://backwpup.com/forums/" target="_blank">Support Forums</a>', 'backwpup' );
				$links[] = __( '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=Q3QSVRSFXBLSE" target="_blank">Donate</a>', 'backwpup' );
				$links[] = __( '<a href="https://plus.google.com/112659782148359984250/" target="_blank">Google+</a>', 'backwpup' );
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
			if ( ! current_user_can( 'backwpup' ) )
				return;
			wp_add_dashboard_widget( "backwpup_dashboard_widget_logs", __( "BackWPup Logs", "backwpup" ), array( 'BackWPup_Dashboard', 'dashboard_logs' ), array( 'BackWPup_Dashboard', 'dashboard_logs_config' ) );
			wp_add_dashboard_widget( "backwpup_dashboard_widget_activejobs", __( "BackWPup Active Jobs", "backwpup" ), array( 'BackWPup_Dashboard', 'dashboard_activejobs' ) );
		}

		/**
		 *
		 * Plugin init function
		 *
		 * @return nothing
		 */
		public function plugin_init() {
			//add admin bar. Works only in init
			if ( is_main_site() && is_admin_bar_showing() && ! defined( 'DOING_CRON' ) && current_user_can( 'backwpup' ) && backwpup_get_option( 'cfg', 'showadminbar' ) ) {
				wp_enqueue_style( "backwpupadmin", plugins_url( '', __FILE__ ) . "/css/adminbar.css", "", ((defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG) ? time() : BackWPup::get_plugin_data('Version')), "screen" );
				add_action( 'admin_bar_menu', array( 'BackWPup_Adminbar', 'adminbar' ), 100 );
			}
		}

		/**
		 *
		 * Textdomain overide
		 *
		 * @param $default
		 * @param $domain
		 * @param $mofile
		 *
		 * @return bool
		 */
		public function overide_textdomain($default, $domain, $mofile) {
			if ( (defined( 'DOING_CRON' ) && DOING_CRON) ) {
				global $l10n;
				if ($domain=='backwpup') {
					foreach (array_keys($l10n) as $domainkey)
						unset($l10n[$domainkey]);
				} else {
					return true;
				}
			}
			return $default;
		}

	}
}