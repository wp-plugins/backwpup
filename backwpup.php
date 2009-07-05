<?php
/*
Plugin Name: BackWPup
Plugin URI: http://danielhuesken.de/portfolio/backwpup/
Description: Backup and more of your WordPress Blog Database and Files.
Author: Daniel H&uuml;sken
Version: 0.1.0
Author URI: http://danielhuesken.de
Text Domain: backwpup
Domain Path: /lang/
*/

/*  
	Copyright 2007  Daniel Hüsken  (email : daniel@huesken-net.de)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
*/

/*
Change log:
  Version 0.5.0: 	Inital test Release

*/


//Set plugin dirname
define('BACKWPUP_PLUGIN_DIR', dirname(plugin_basename(__FILE__)));
//Set Plugin Version
define('BACKWPUP_VERSION', '0.5.0');

//Version check
if (version_compare($wp_version, '2.8', '<')) { // Let only Activate on WordPress Version 2.8 or heiger
	add_action('admin_notices', create_function('', 'echo \'<div id="message" class="error fade"><p><strong>' . __('Sorry, BackWPup works only under WordPress 2.8 or higher','backwpup') . '</strong></p></div>\';'));
} else {
	//Load functions file
	require_once(WP_PLUGIN_DIR.'/'.BACKWPUP_PLUGIN_DIR.'/app/functions.php');
	//Plugin activate
	register_activation_hook(__FILE__, array('BackWPupFunctions', 'plugin_activate')); //Don't work!!!!
	//Plugin deactivate
	register_deactivation_hook(__FILE__, array('BackWPupFunctions', 'plugin_deactivate'));
	//Plugin uninstall
	register_uninstall_hook(__FILE__, array('BackWPupFunctions', 'plugin_uninstall'));
	//Plugin init	
	add_action('plugins_loaded', array('BackWPupFunctions', 'init'));
}
?>
