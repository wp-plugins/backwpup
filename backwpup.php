<?php
/*
Plugin Name: BackWPup
Plugin URI: http://backwpup.com
Description: Wordpress Backup and more...
Author: Daniel H&uuml;sken
Version: 2.0.5
Author URI: http://danielhuesken.de
Text Domain: backwpup
Domain Path: /lang/
*/

/*
	Copyright (C) 2011  Daniel Hüsken  (email: mail@backwpup.com)

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

// don't load directly
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

//Set plugin dirname
define('BACKWPUP_PLUGIN_BASEDIR', dirname(plugin_basename(__FILE__)));
define('BACKWPUP_PLUGIN_BASEURL',plugins_url('',__FILE__));
//Set Plugin Version
define('BACKWPUP_VERSION', '2.1.0');
//Set Min Wordpress Version
define('BACKWPUP_MIN_WORDPRESS_VERSION', '3.2');
//Set User Capability
define('BACKWPUP_USER_CAPABILITY', 'export');
//Set useable destinations
if (!defined('BACKWPUP_DESTS')) {
	if (!function_exists('curl_init'))
		define('BACKWPUP_DESTS', 'FTP,MSAZURE');
	else 
		define('BACKWPUP_DESTS', 'FTP,DROPBOX,SUGARSYNC,S3,GSTORAGE,RSC,MSAZURE');
}
//Set Dropbox Aplication Keys
define('BACKWPUP_DROPBOX_APP_KEY', 'q2jbt0unkkc54u2');
define('BACKWPUP_DROPBOX_APP_SECRET', 't5hlbxtz473hchy');
//Set SugarSync Aplication Keys
define('BACKWPUP_SUGARSYNC_ACCESSKEY', 'OTcwNjc5MTI5OTQxMzY1Njc5OA');
define('BACKWPUP_SUGARSYNC_PRIVATEACCESSKEY', 'NzNmNDMwMDBiNTkwNDY0YzhjY2JiN2E5YWVkMjFmYmI');
//BackWPup API url
define('BACKWPUP_API_URL', 'http://api.backwpup.com');
//load Text Domain
load_plugin_textdomain('backwpup', false, BACKWPUP_PLUGIN_BASEDIR.'/lang');
//Load functions file
require_once(dirname(__FILE__).'/backwpup-functions.php');
//Plugin activate
register_activation_hook(__FILE__, 'backwpup_plugin_activate');
//Plugin deactivate
register_deactivation_hook(__FILE__, 'backwpup_plugin_deactivate');
//Admin message
add_action('admin_notices', 'backwpup_admin_notice'); 
if (backwpup_env_checks()) {
	//add Menu
	if (is_multisite()) {
		if  (WP_NETWORK_ADMIN) {
			add_action('plugins_loaded' , 'backwpup_plugin_activate'); //Activation for mu
			add_action('network_admin_menu', 'backwpup_admin_menu');
		}
	} else {
		add_action('admin_menu', 'backwpup_admin_menu');
	}
	//add cron intervals
	add_filter('cron_schedules', 'backwpup_intervals');
	//Actions for Cron job
	add_action('backwpup_cron', 'backwpup_cron',1);
	//add Dashboard widget
	add_action('wp_dashboard_setup', 'backwpup_add_dashboard');
	//add Admin Bar menu
	add_action('admin_bar_menu', 'backwpup_add_adminbar',100);
	//Additional links on the plugin page
	add_filter('plugin_action_links_'.BACKWPUP_PLUGIN_BASEDIR.'/backwpup.php', 'backwpup_plugin_options_link');
	add_filter('plugin_row_meta', 'backwpup_plugin_links',10,2);
	//load ajax functions
	backwpup_load_ajax();
	//Disabele WP_Corn
	$cfg=get_option('backwpup');
	if (isset($cfg['disablewpcron']) && $cfg['disablewpcron'])
		define('DISABLE_WP_CRON',true);
	//test if cron active
	if (!(wp_next_scheduled('backwpup_cron')))
		wp_schedule_event(mktime(date("H")), 'backwpup_int', 'backwpup_cron');
}
?>
