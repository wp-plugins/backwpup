<?php
/*
Plugin Name: BackWPup
Plugin URI: http://danielhuesken.de/portfolio/backwpup/
Description: Backup and more of your WordPress Blog Database and Files.
Author: Daniel H&uuml;sken
Version: 1.5.2
Author URI: http://danielhuesken.de
Text Domain: backwpup
Domain Path: /lang/
*/

/*
	Copyright 2010  Daniel Hüsken  (email : daniel@huesken-net.de)

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

// don't load directly
if ( !defined('ABSPATH') )
	die('-1');

//Set plugin dirname
define('BACKWPUP_PLUGIN_BASEDIR', dirname(plugin_basename(__FILE__)));
//Set Plugin Version
define('BACKWPUP_VERSION', '1.5.2');
//Set User Capability
define('BACKWPUP_USER_CAPABILITY', 'export');
//Set useable destinations
if (!defined('BACKWPUP_DESTS'))
	define('BACKWPUP_DESTS', 'S3,RSC,FTP,DROPBOX');
//load Text Domain
load_plugin_textdomain('backwpup', false, BACKWPUP_PLUGIN_BASEDIR.'/lang');
//Load functions file
require_once(dirname(__FILE__).'/app/functions.php');
//Plugin activate
register_activation_hook(__FILE__, 'backwpup_plugin_activate');
//Plugin deactivate
register_deactivation_hook(__FILE__, 'backwpup_plugin_deactivate');
//Admin message
add_action('admin_notices', 'backwpup_admin_notice'); 
if (backwpup_env_checks()) {
	//include php5 functions
	require_once(dirname(__FILE__).'/app/functions5.php');
	//add Menu
	add_action('admin_menu', 'backwpup_admin_menu');
	//add cron intervals
	add_filter('cron_schedules', 'backwpup_intervals');
	//Actions for Cron job
	add_action('backwpup_cron', 'backwpup_cron');
	//add Dashboard widget
	add_action('wp_dashboard_setup', 'backwpup_add_dashboard');
	//Additional links on the plugin page
	add_filter('plugin_action_links_'.BACKWPUP_PLUGIN_BASEDIR.'/backwpup.php', 'backwpup_plugin_options_link');
	add_filter('plugin_row_meta', 'backwpup_plugin_links',10,2);
	// add ajax function
	add_action('wp_ajax_backwpup_get_aws_buckets', 'backwpup_get_aws_buckets');
	add_action('wp_ajax_backwpup_get_rsc_container', 'backwpup_get_rsc_container');
	//Disabele WP_Corn
	$cfg=get_option('backwpup');
	if ($cfg['disablewpcron'])
		define('DISABLE_WP_CRON',true);
	//test if cron active
	if (!(wp_next_scheduled('backwpup_cron')))
		wp_schedule_event(0, 'backwpup_int', 'backwpup_cron');
}
?>
