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
//Load textdomain
load_plugin_textdomain('backwpup', false, BACKWPUP_PLUGIN_BASENAME.'/lang');
//Load some file
include_once(dirname(__FILE__).'/backwpup-functions.php');
include_once(dirname(__FILE__).'/backwpup-api.php');
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
		add_action('backwpup_cron',  array($this,'cron_run'),1);
		add_action('admin_bar_menu', array($this,'add_adminbar'),100);
		add_action('init', create_function('',''));
		if (is_multisite()) {
			add_action('network_admin_notices', create_function('','global $backwpup_admin_message;if (current_user_can(BACKWPUP_USER_CAPABILITY)) echo $backwpup_admin_message;'));
			add_action('network_admin_menu', array($this,'admin_menu'));
			add_action('wp_network_dashboard_setup', array($this,'dashboard_setup'));
			add_filter('plugin_row_meta', array($this,'plugin_links'),10,2);
		} else {
			add_action('admin_notices', create_function('','global $backwpup_admin_message;if (current_user_can(BACKWPUP_USER_CAPABILITY)) echo $backwpup_admin_message;'));
			add_action('admin_menu', array($this,'admin_menu'));
			add_action('wp_dashboard_setup', array($this,'dashboard_setup'));
			add_filter('plugin_action_links_'.BACKWPUP_PLUGIN_BASENAME.'/backwpup.php', create_function('$links','array_unshift($links,"<a href=\"".backwpup_admin_url("admin.php")."?page=backwpup\" title=\"". __("Go to Settings Page","backwpup") ."\" class=\"edit\">". __("Settings","backwpup") ."</a>");return $links;'));
			add_filter('plugin_row_meta', array($this,'plugin_links'),10,2);
		}
		if (is_main_site())
			add_action('init', array($this,'plugin_init'));
	}

	function plugin_init() {
		global $wpdb,$backwpup_cfg;
		// return if not main
		if (!is_main_site())
			return;
		//Create log table
		$dbversion=backwpup_get_option('dbversion','dbversion');
		//Create DB table if not exists
		if (empty($dbversion)) {
			$query ='CREATE TABLE IF NOT EXISTS `'.$wpdb->prefix.'backwpup` (
				`main_name` varchar(64) NOT NULL,
				`name` varchar(64) NOT NULL,
				`value` longtext NOT NULL,
				KEY `main_name` (`main_name`),
				KEY `name` (`name`)
				) ';
			if(!empty($wpdb->charset))
				$query .= 'DEFAULT CHARACTER SET '.$wpdb->charset;
			if(!empty($wpdb->collate))
				$query .= ' COLLATE '.$wpdb->collate;
			$wpdb->query($query);
			//Put old jobs to DB
			$jobs=get_option('backwpup_jobs');
			if (is_array($jobs)) {
				foreach ($jobs as $jobid => $jobvalue) {
					if (empty($jobvalue['jobid']))
						$jobvalue['jobid']=$jobid;
					$jobvalue['type']=explode('+',$jobvalue['type']); //save as array
					unset($jobvalue['scheduleintervaltype'],$jobvalue['scheduleintervalteimes'],$jobvalue['scheduleinterval'],$jobvalue['dropemail'],$jobvalue['dropepass'],$jobvalue['dropesignmethod'],$jobvalue['dbtables']);
					foreach ($jobvalue as $jobvaluename => $jobvaluevalue) {
						backwpup_update_option('job_'.$jobvalue['jobid'],$jobvaluename,$jobvaluevalue);
					}
				}
			}
			delete_option('backwpup_jobs');
			//Put old cfg to DB
			$cfg=get_option('backwpup');
			// delete old not nedded vars
			$cfg['tempfolder']=$cfg['dirtemp']; //if old value switsch it to new
			$cfg['logfolder']=$cfg['dirlogs'];
			unset($cfg['mailmethod'],$cfg['mailsendmail'],$cfg['mailhost'],$cfg['mailhostport'],$cfg['mailsecure'],$cfg['mailuser'],$cfg['mailpass'],$cfg['dirtemp'],$cfg['dirlogs'],$cfg['logfilelist'],$cfg['jobscriptruntime'],$cfg['jobscriptruntimelong'],$cfg['last_activate'],$cfg['disablewpcron']);
			if (is_array($cfg)) {
				foreach ($cfg as $cfgname => $cfgvalue) {
					backwpup_update_option('cfg',$cfgname,$cfgvalue);
				}
			}
			delete_option('backwpup');
		}

		// on version updates
		if ($dbversion!=BACKWPUP_VERSION) {
			backwpup_update_option('dbversion','dbversion',BACKWPUP_VERSION);
			//cleanup database
			$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main_name='job_'");
			$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main_name='temp'");
			$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main_name='working'");
			//remove old cron jobs
			wp_clear_scheduled_hook('backwpup_cron');
			//make new schedule
			$activejobs=$wpdb->get_var("SELECT value FROM `".$wpdb->prefix."backwpup` WHERE main_name LIKE 'job_%' AND name='activated' AND value='1' LIMIT 1",0,0);
			if (!empty($activejobs))
				wp_schedule_event(time(), 'backwpup', 'backwpup_cron');
			//check cfg
			//Set settings defaults
			$mailsndemail=backwpup_get_option('cfg','mailsndemail');
			if (empty($mailsndemail)) backwpup_update_option('cfg','mailsndemail',sanitize_email(get_bloginfo( 'admin_email' )));
			$mailsndname=backwpup_get_option('cfg','mailsndname');
			if (empty($mailsndname)) backwpup_update_option('cfg','mailsndname','BackWPup '.get_bloginfo('name'));
			if (!backwpup_get_option('cfg','showadminbar')) backwpup_update_option('cfg','showadminbar',true);
			$jobstepretry=backwpup_get_option('cfg','jobstepretry');
			if (!is_numeric($jobstepretry) or 100<$jobstepretry or empty($jobstepretry))  backwpup_update_option('cfg','jobstepretry',3);
			$jobscriptretry=backwpup_get_option('cfg','jobscriptretry');
			if (!is_numeric($jobscriptretry) or 100<$jobscriptretry or empty($jobscriptretry)) backwpup_update_option('cfg','jobscriptretry',5);
			$maxlogs=backwpup_get_option('cfg','maxlogs');
			if (empty($maxlogs) or !is_numeric($maxlogs)) backwpup_update_option('cfg','maxlogs',50);
			if (!function_exists('gzopen') or !backwpup_get_option('cfg','gzlogs')) backwpup_add_option('cfg','gzlogs',false);
			if (!class_exists('ZipArchive') or !backwpup_get_option('cfg','phpzip')) backwpup_add_option('cfg','phpzip',false);
			if (!backwpup_get_option('cfg','unloadtranslations')) backwpup_add_option('cfg','unloadtranslations',false);
			if (!backwpup_get_option('cfg','apicronservice')) backwpup_add_option('cfg','apicronservice',false);
			$logfolder=backwpup_get_option('cfg','logfolder');
			if (!isset($logfolder) or empty($logfolder) or !is_dir($logfolder)) {
				$rand = substr( md5( md5( SECURE_AUTH_KEY ) ), -5 );
				backwpup_update_option('cfg','logfolder',str_replace('\\','/',trailingslashit(WP_CONTENT_DIR)).'backwpup-'.$rand.'-logs/');
			}
			if (!backwpup_get_option('cfg','httpauthuser')) backwpup_update_option('cfg','httpauthuser','');
			if (!backwpup_get_option('cfg','httpauthpassword')) backwpup_update_option('cfg','httpauthpassword','');
			if (!backwpup_get_option('cfg','jobrunauthkey'))
				backwpup_update_option('cfg','jobrunauthkey', wp_create_nonce('BackWPupJobRun'));
			if (!backwpup_get_option('cfg','tempfolder')) {
				if (defined('WP_TEMP_DIR'))
					$tempfolder=trim(WP_TEMP_DIR);
				if (empty($tempfolder) or !backwpup_check_open_basedir($tempfolder) or !@is_writable($tempfolder) or !@is_dir($tempfolder))
					$tempfolder=sys_get_temp_dir();									//normal temp dir
				if (empty($tempfolder) or !backwpup_check_open_basedir($tempfolder) or !@is_writable($tempfolder) or !@is_dir($tempfolder))
					$tempfolder=ini_get('upload_tmp_dir');							//if sys_get_temp_dir not work
				if (empty($tempfolder) or !backwpup_check_open_basedir($tempfolder) or !@is_writable($tempfolder) or !@is_dir($tempfolder))
					$tempfolder=WP_CONTENT_DIR.'/';
				if (empty($tempfolder) or !backwpup_check_open_basedir($tempfolder) or !@is_writable($tempfolder) or !@is_dir($tempfolder))
					$tempfolder=get_temp_dir();
				backwpup_update_option('cfg','tempfolder',trailingslashit(str_replace('\\','/',realpath($tempfolder))));
			}
		}

		//load cfg
		$backwpupapi=new BackWPup_api();
		$backwpup_cfg=$backwpupapi->get_apps();
		$cfgs=$wpdb->get_results("SELECT name,value FROM `".$wpdb->prefix."backwpup` WHERE `main_name`='cfg'");
		foreach ($cfgs as $cfg) {
			$backwpup_cfg[$cfg->name]=maybe_unserialize($cfg->value);
		}

		//Css for Admin bar
		if ($backwpup_cfg['showadminbar'])
			wp_enqueue_style("backwpupadmin",BACKWPUP_PLUGIN_BASEURL."/css/adminbar.css","",BACKWPUP_VERSION,"screen");
	}

	public function plugin_deactivate() {
		global $wpdb;
		wp_clear_scheduled_hook('backwpup_cron');
		backwpup_update_option('dbversion','dbversion','0.0');
		$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main_name='temp'");
		$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main_name='working'");
		$backwpupapi=new BackWPup_api();
		$backwpupapi->delete();
	}

	public function admin_menu() {
		$page_hook=add_menu_page( __('BackWPup','backwpup'), __('BackWPup','backwpup'), BACKWPUP_USER_CAPABILITY, 'backwpup', array($this,'admin_page'), BACKWPUP_PLUGIN_BASEURL.'/css/BackWPup16.png');
		add_action('load-'.$page_hook, array($this,'admin_page_load'));
		$page_hook=add_submenu_page( 'backwpup', __('Jobs','backwpup'), __('Jobs','backwpup'), BACKWPUP_USER_CAPABILITY, 'backwpup', array($this,'admin_page') );
		add_action('load-'.$page_hook, array($this,'admin_page_load'));
		$page_hook=add_submenu_page( 'backwpup', __('Add New','backwpup'), __('Add New','backwpup'), BACKWPUP_USER_CAPABILITY, 'backwpupeditjob', array($this,'admin_page') );
		add_action('load-'.$page_hook, array($this,'admin_page_load'));
		$backupdata=backwpup_get_option('working','data');
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

	public function plugin_links($links, $file) {
		if ($file == BACKWPUP_PLUGIN_BASENAME.'/backwpup.php') {
			$links[] = __( '<a href="http://backwpup.com/manual/" target="_blank">Documentation</a>','backwpup' );
			$links[] = __( '<a href="http://backwpup.com/faq/" target="_blank">FAQ</a>','backwpup' );
			$links[] = __( '<a href="http://backwpup.com/forums/" target="_blank">Support Forums</a>','backwpup' );
			$links[] = __( '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=Q3QSVRSFXBLSE" target="_blank">Donate</a>','backwpup' );
			$links[] = __( '<a href="https://flattr.com/thing/345067/BackWPup" target="_blank">Flattr</a>','backwpup' );
		}
		return $links;
	}

	public function dashboard_setup() {
		if (!current_user_can(BACKWPUP_USER_CAPABILITY))
			return;
		wp_add_dashboard_widget("backwpup_dashboard_widget_logs", __("BackWPup Logs","backwpup"),array($this,"dashboard_logs"),array($this,"dashboard_logs_config"));
		wp_add_dashboard_widget("backwpup_dashboard_widget_activejobs", __("BackWPup Active Jobs","backwpup"),array($this,"dashboard_activejobs"));
	}

	public function dashboard_logs() {
		global $backwpup_cfg;
		$widgets = get_option('dashboard_widget_options');
		if (!isset($widgets['backwpup_dashboard_logs']) or $widgets['backwpup_dashboard_logs']<1 or $widgets['backwpup_dashboard_logs']>20)
			$widgets['backwpup_dashboard_logs'] =5;
		//get log files
		$logfiles=array();
		if ( $dir = @opendir( $backwpup_cfg['logfolder'] ) ) {
			while (($file = readdir( $dir ) ) !== false ) {
				if (is_file($backwpup_cfg['logfolder'].'/'.$file) and 'backwpup_log_' == substr($file,0,strlen('backwpup_log_')) and  ('.html' == substr($file,-5) or '.html.gz' == substr($file,-8)))
					$logfiles[]=$file;
			}
			closedir( $dir );
			rsort($logfiles);
		}
		echo '<ul>';
		if (count($logfiles)>0) {
			$count=0;
			foreach ($logfiles as $logfile) {
				$logdata=backwpup_read_logheader($backwpup_cfg['logfolder'].'/'.$logfile);
				echo '<li>';
				echo '<span>'.date_i18n(get_option('date_format').' @ '.get_option('time_format'),$logdata['logtime']).'</span> ';
				echo '<a href="'.wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpupworking&logfile='.$backwpup_cfg['logfolder'].'/'.$logfile, 'view-log_'.$logfile).'" title="'.__('View Log:','backwpup').' '.basename($logfile).'">'.$logdata['name'].'</i></a>';
				if ($logdata['errors']>0)
					printf(' <span style="color:red;font-weight:bold;">'._n("%d ERROR", "%d ERRORS", $logdata['errors'],'backwpup').'</span>', $logdata['errors']);
				if ($logdata['warnings']>0)
					printf(' <span style="color:#e66f00;font-weight:bold;">'._n("%d WARNING", "%d WARNINGS", $logdata['warnings'],'backwpup').'</span>', $logdata['warnings']);
				if($logdata['errors']==0 and $logdata['warnings']==0)
					echo ' <span style="color:green;font-weight:bold;">'.__('O.K.','backwpup').'</span>';
				echo '</li>';
				$count++;
				if ($count>=$widgets['backwpup_dashboard_logs'])
					break;
			}
			echo '</ul>';
		} else {
			echo '<i>'.__('none','backwpup').'</i>';
		}
	}

	public function dashboard_logs_config() {
		if ( !$widget_options = get_option( 'dashboard_widget_options' ) )
			$widget_options = array();

		if ( !isset($widget_options['backwpup_dashboard_logs']) )
			$widget_options['backwpup_dashboard_logs'] = 5;

		if ( 'POST' == $_SERVER['REQUEST_METHOD'] && isset($_POST['backwpup_dashboard_logs']) ) {
			$number = absint( $_POST['backwpup_dashboard_logs'] );
			$widget_options['backwpup_dashboard_logs'] = $number;
			update_option( 'dashboard_widget_options', $widget_options );
		}

		echo '<p><label for="backwpup-logs">'.__('How many of the lastes logs would you like to display?','backwpup').'</label>';
		echo '<select id="backwpup-logs" name="backwpup_dashboard_logs">';
		for ($i=0;$i<=20;$i++)
			echo '<option value="'.$i.'" '.selected($i,$widget_options['backwpup_dashboard_logs']).'>'.$i.'</option>';
		echo '</select>';

	}

	public function dashboard_activejobs() {
		global $wpdb;
		$main_namesactive=$wpdb->get_col("SELECT main_name FROM `".$wpdb->prefix."backwpup` WHERE main_name LIKE 'job_%' AND name='activated' AND value='1'");
		if (empty($main_namesactive)) {
			echo '<ul><li><i>'.__('none','backwpup').'</i></li></ul>';
			return;
		}
		$backupdata=backwpup_get_option('working','data');
		//get ordering
		$main_namescronnextrun=$wpdb->get_col("SELECT main_name FROM `".$wpdb->prefix."backwpup` WHERE main_name LIKE 'job_%' AND name='cronnextrun' ORDER BY value ASC");
		echo '<ul>';
		foreach ($main_namescronnextrun as $main_name) {
			if (!in_array($main_name,$main_namesactive))
				continue;
			$name=backwpup_get_option($main_name,'name');
			$jobid=backwpup_get_option($main_name,'jobid');
			if (!empty($backupdata) and $backupdata['STATIC']['JOB']['jobid']==$jobid) {
				$startime=backwpup_get_option($main_name,'starttime');
				$runtime=current_time('timestamp')-$startime;
				echo '<li><span style="font-weight:bold;">'.$jobid.'. '.$name.': </span>';
				printf('<span style="color:#e66f00;">'.__('working since %d sec.','backwpup').'</span>',$runtime);
				echo " <a style=\"color:green;\" href=\"" . backwpup_admin_url('admin.php').'?page=backwpupworking' . "\">" . __('View!','backwpup') . "</a>";
				echo " <a style=\"color:red;\" href=\"" . wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpup&action=abort', 'abort-job') . "\">" . __('Abort!','backwpup') . "</a>";
				echo "</li>";
			} else {
				$cronnextrun=backwpup_get_option($main_name,'cronnextrun');
				echo '<li><span>'.date_i18n(get_option('date_format'),$cronnextrun).' @ '.date_i18n(get_option('time_format'),$cronnextrun).'</span>';
				echo ' <a href="'.wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpupeditjob&jobid='.$jobid, 'edit-job').'" title="'.__('Edit Job','backwpup').'">'.$name.'</a><br />';
				echo "</li>";
			}
		}
		echo '</ul>';
	}

	public function add_adminbar() {
		global $wp_admin_bar,$backwpup_cfg,$wpdb;
		if (!$backwpup_cfg['showadminbar'] || !current_user_can(BACKWPUP_USER_CAPABILITY) || !is_super_admin() || !is_admin_bar_showing())
			return;
		$backupdata=backwpup_get_option('working','data');
		$menutitle='<span class="ab-icon"></span><span class="ab-label"></span>';
		if (!empty($backupdata))
			$menutitle= '<span class="ab-icon"></span><span class="ab-label">!</span>';
		$wp_admin_bar->add_menu(array( 'id' => 'backwpup', 'title' => $menutitle, 'href' => backwpup_admin_url('admin.php').'?page=backwpup','meta' => array('title' => __( 'BackWPup', 'backwpup' ))));
		if (!empty($backupdata)) {
			$wp_admin_bar->add_menu(array( 'id' => 'backwpup_working' ,'parent' => 'backwpup_jobs', 'title' => __('See Working!','backwpup'), 'href' => backwpup_admin_url('admin.php').'?page=backwpupworking'));
			$wp_admin_bar->add_menu(array( 'id' => 'backwpup_working_abort' ,'parent' => 'backwpup_working', 'title' => __('Abort!','backwpup'), 'href' => wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpup&action=abort', 'abort-job')));
		}
		$wp_admin_bar->add_menu(array( 'id' => 'backwpup_jobs', 'parent' => 'backwpup', 'title' => __('Jobs','backwpup'), 'href' => backwpup_admin_url('admin.php').'?page=backwpup'));
		$wp_admin_bar->add_menu(array( 'id' => 'backwpup_jobs_new', 'parent' => 'backwpup_jobs', 'title' => __('Add New','backwpup'), 'href' => backwpup_admin_url('admin.php').'?page=backwpupeditjob'));
		$wp_admin_bar->add_menu(array( 'id' => 'backwpup_logs' ,'parent' => 'backwpup', 'title' => __('Logs','backwpup'), 'href' => backwpup_admin_url('admin.php').'?page=backwpuplogs'));
		$wp_admin_bar->add_menu(array( 'id' => 'backwpup_backups' ,'parent' => 'backwpup', 'title' => __('Backups','backwpup'), 'href' => backwpup_admin_url('admin.php').'?page=backwpupbackups'));
		//add jobs
		$jobs=$wpdb->get_col("SELECT value FROM `".$wpdb->prefix."backwpup` WHERE main_name LIKE 'job_%' AND name='jobid' ORDER BY value DESC");
		foreach ($jobs as $job) {
			$name=backwpup_get_option('job_'.$job,'name');
			$wp_admin_bar->add_menu(array( 'id' => 'backwpup_jobs_'.$job, 'parent' => 'backwpup_jobs', 'title' => $name, 'href' => wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpupeditjob&jobid='.$job, 'edit-job')));
			$wp_admin_bar->add_menu(array( 'id' => 'backwpup_jobs_runnow_'.$job, 'parent' => 'backwpup_jobs_'.$job, 'title' => __('Run Now','backwpup'), 'href' => wp_nonce_url(BACKWPUP_PLUGIN_BASEURL.'/backwpup-job.php?ABSPATH='.urlencode(ABSPATH).'&starttype=runnow&jobid='.(int)$job, 'backwpup-job-running')));
		}
		//get log files
		$logfiles=array();
		if ( $dir = @opendir( $backwpup_cfg['logfolder'] ) ) {
			while (($file = readdir( $dir ) ) !== false ) {
				if (is_file($backwpup_cfg['logfolder'].'/'.$file) and 'backwpup_log_' == substr($file,0,strlen('backwpup_log_')) and  ('.html' == substr($file,-5) or '.html.gz' == substr($file,-8)))
					$logfiles[]=$file;
			}
			closedir( $dir );
			rsort($logfiles);
		}
		if (count($logfiles)>0) {
			for ($i=0;$i<5;$i++) {
				$logdata=backwpup_read_logheader($backwpup_cfg['logfolder'].'/'.$logfiles[$i]);
				$title = date_i18n(get_option('date_format').' @ '.get_option('time_format'),$logdata['logtime']).' ';
				$title.= $logdata['name'];
				if ($logdata['errors']>0)
					$title.= sprintf(' <span style="color:red;">('._n("%d ERROR", "%d ERRORS", $logdata['errors'],'backwpup').')</span>', $logdata['errors']);
				if ($logdata['warnings']>0)
					$title.= sprintf(' <span style="color:#e66f00;">('._n("%d WARNING", "%d WARNINGS", $logdata['warnings'],'backwpup').')</span>', $logdata['warnings']);
				if($logdata['errors']==0 and $logdata['warnings']==0)
					$title.= ' <span style="color:green;">('.__('O.K.','backwpup').')</span>';
				$wp_admin_bar->add_menu(array( 'id' => 'backwpup_logs_'.$i ,'parent' => 'backwpup_logs', 'title' => $title, 'href' => wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpupworking&logfile='.$backwpup_cfg['logfolder'].'/'.$logfiles[$i], 'view-log_'.$logfiles[$i])));
			}
		}
	}


	public function cron_run() {
		global $backwpup_cfg,$wpdb;
		$httpauthheader='';
		if (!empty($backwpup_cfg['httpauthuser']) and !empty($backwpup_cfg['httpauthpassword']))
			$httpauthheader=array( 'Authorization' => 'Basic '.base64_encode($backwpup_cfg['httpauthuser'].':'.base64_decode($backwpup_cfg['httpauthpassword'])));
		$backupdata=backwpup_get_option('working','data');
		if (!empty($backupdata)) {
			$revtime=current_time('timestamp')-600; //10 min no progress.
			if (!empty($backupdata['working']['TIMESTAMP']) and $backupdata['working']['TIMESTAMP']<$revtime)
				wp_remote_get(BACKWPUP_PLUGIN_BASEURL.'/backwpup-job.php?ABSPATH='.urlencode(str_replace('\\','/',ABSPATH)).'&_wpnonce='.$backwpup_cfg['jobrunauthkey'].'&starttype=restarttime', array('timeout' => 5, 'blocking' => false, 'sslverify' => false, 'headers'=>$httpauthheader, 'user-agent'=>'BackWPup'));
		} else {
			$main_names=$wpdb->get_col("SELECT main_name FROM `".$wpdb->prefix."backwpup` WHERE main_name LIKE 'job_%' AND name='activated' AND vlaue='1'");
			if (!empty($main_names)) {
				foreach ($main_names as $main_name) {
					$cronnextrun=backwpup_get_option($main_name,'cronnextrun');
					if ($cronnextrun<=current_time('timestamp')) {
						$jobstartid=backwpup_get_option($main_name,'jobid');
						backwpup_update_option('job_' . $jobstartid, 'cronnextrun', backwpup_cron_next(backwpup_get_option('job_' . $jobstartid, 'cron'))); //update next run time
						wp_remote_get(BACKWPUP_PLUGIN_BASEURL.'/backwpup-job.php?ABSPATH='.urlencode(str_replace('\\','/',ABSPATH)).'&_wpnonce='.$backwpup_cfg['jobrunauthkey'].'&starttype=cronrun&jobid='.(int)$jobstartid, array('timeout' => 5, 'blocking' => false, 'sslverify' => false, 'headers'=>$httpauthheader, 'user-agent'=>'BackWPup'));
						exit;
					}
				}
			}
		}
	}


}

new BackWPup();
?>