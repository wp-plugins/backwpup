<?php
/*
Plugin Name: BackWPup
Plugin URI: http://backwpup.com
Description: WordPress Backup and more...
Author: Daniel H&uuml;sken
Version: 3.0-Dev
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
define('BACKWPUP_VERSION', '3.0-Dev');
define('BACKWPUP_MIN_WORDPRESS_VERSION', '3.2');
define('BACKWPUP_USER_CAPABILITY', 'export');
define('BACKWPUP_MENU_PAGES', 'backwpup,backwpupeditjob,backwpupworking,backwpuplogs,backwpupbackups,backwpuptools,backwpupsettings');
if (!defined('BACKWPUP_DESTS')) {
	if (!function_exists('curl_init'))
		define('BACKWPUP_DESTS', 'FTP,MSAZURE,BOXNET');
	else
		define('BACKWPUP_DESTS', 'FTP,DROPBOX,SUGARSYNC,S3,GSTORAGE,RSC,MSAZURE,BOXNET');
}
if (!defined('FS_CHMOD_DIR'))
	define('FS_CHMOD_DIR', 0755 );

if (is_main_site()) {
	//Load textdomain
	load_plugin_textdomain('backwpup', false, BACKWPUP_PLUGIN_BASENAME.'/lang');
	//deactivation hook
	register_deactivation_hook(__FILE__, 'backwpup_plugin_deactivate');
	if (current_filter('deactivate_'.BACKWPUP_PLUGIN_BASENAME))
		include_once(dirname(__FILE__).'/core/deactivate.php');
	//Load some file
	include_once(dirname(__FILE__).'/core/functions.php');
	include_once(dirname(__FILE__).'/core/init.php');
	//WP-Cron
	add_filter('cron_schedules', create_function('$schedules','$schedules["backwpup"]=array("interval"=>300,"display"=> __("BackWPup", "backwpup"));return $schedules;'));
	if (defined('DOING_CRON')and !defined('DOING_BACKWPUP_JOB') and DOING_CRON )
		include_once(dirname(__FILE__).'/core/wp-cron.php');
	//load menus and pages
	if (!defined('DOING_CRON') and !defined('DOING_BACKWPUP_JOB') and !defined("DOING_AJAX") and !defined('XMLRPC_REQUEST') and !defined('APP_REQUEST') and is_admin())
		include_once(dirname(__FILE__).'/core/admin.php');

}
//load doing job class
if (defined('DOING_BACKWPUP_JOB') and DOING_BACKWPUP_JOB)
	include_once(dirname(__FILE__).'/core/job.php');
//include ajax functions
if (defined('DOING_AJAX') and DOING_AJAX and !empty($_POST['backwpupajaxpage'])) {
	$backwpup_manu_page=trim($_POST['backwpupajaxpage']);
	if (!empty($backwpup_manu_page) and in_array($backwpup_manu_page,explode(',',BACKWPUP_MENU_PAGES)) and is_file(dirname(__FILE__).'/admin/ajax_'.$backwpup_manu_page.'.php'))
		include_once(dirname(__FILE__).'/admin/ajax_'.$backwpup_manu_page.'.php');
}
?>