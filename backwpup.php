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
if (!defined('ABSPATH'))
	die();
//define some things
define('BACKWPUP_PLUGIN_BASENAME',dirname(plugin_basename(__FILE__)));
define('BACKWPUP_PLUGIN_FILE',basename(__FILE__));
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

//not load translations for other text domains reduces memory and load time
if ((defined('DOING_BACKWPUP_JOB') and DOING_BACKWPUP_JOB) or (defined('DOING_AJAX') and DOING_AJAX and in_array($_POST['backwpupajaxpage'],explode(',',BACKWPUP_MENU_PAGES))))
	add_filter('override_load_textdomain', create_function('$default, $domain, $mofile','if ($domain=="backwpup") return $default; else return true;'),1,3);
//Load text domain if needed
if ((is_main_site() and is_admin() and !defined('DOING_CRON')) or (defined('DOING_BACKWPUP_JOB') and DOING_BACKWPUP_JOB) or (defined('DOING_AJAX') and DOING_AJAX and in_array($_POST['backwpupajaxpage'],explode(',',BACKWPUP_MENU_PAGES))))
	load_plugin_textdomain('backwpup', false, BACKWPUP_PLUGIN_BASENAME.'/lang');
//load thins only on main sites (MU)
if (is_main_site()) {
	//deactivation hook
	register_deactivation_hook(__FILE__, 'backwpup_plugin_deactivate');
	if (current_filter('deactivate_'.BACKWPUP_PLUGIN_BASENAME))
		include_once(dirname(__FILE__).'/core/deactivate.php');
	//Load some file
	include_once(dirname(__FILE__).'/core/functions.php');
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
if (defined('DOING_AJAX') and DOING_AJAX and in_array($_POST['backwpupajaxpage'],explode(',',BACKWPUP_MENU_PAGES)) and is_file(dirname(__FILE__).'/admin/ajax_'.$_POST['backwpupajaxpage'].'.php'))
	include_once(dirname(__FILE__).'/admin/ajax_'.$_POST['backwpupajaxpage'].'.php');
?>