<?PHP

class BackWPupFunctions {


	function list_files( $folder = '', $levels = 100 ) { //Same as WP function but needet for cron
		if( empty($folder) )
			return false;
		if( ! $levels )
			return false;
		$files = array();
		if ( $dir = @opendir( $folder ) ) {
			while (($file = readdir( $dir ) ) !== false ) {
				if ( in_array($file, array('.', '..','.svn') ) )
					continue;
				if ( is_dir( $folder . '/' . $file ) ) {
					$files2 = list_files( $folder . '/' . $file, $levels - 1);
					if( $files2 )
						$files = array_merge($files, $files2 );
					else
						$files[] = $folder . '/' . $file . '/';
				} else {
					$files[] = $folder . '/' . $file;
				}
			}
		}
		@closedir( $dir );
		return $files;
	}

 	function get_temp_dir() { //Same as WP function but needet for cron
		if ( defined('WP_TEMP_DIR') )
			return trailingslashit(WP_TEMP_DIR);
		$temp = WP_CONTENT_DIR . '/';
		if ( is_dir($temp) && is_writable($temp) )
			return $temp;
		if  ( function_exists('sys_get_temp_dir') )
			return trailingslashit(sys_get_temp_dir());
		return '/tmp/';
	}
	
	//Thems Option menu entry
	function menu_entry() {
		$hook = add_management_page(__('BackWPup','backwpup'), __('BackWPup','backwpup'), 'install_plugins', 'BackWPup',array('BackWPupFunctions', 'options')) ;
		add_action('load-'.$hook, array('BackWPupFunctions', 'options_load'));
		add_contextual_help($hook,BackWPupFunctions::show_help());
	}	
	
	// Help too display
	function show_help() {
		$help .= '<div class="metabox-prefs">';
		$help .= '<a href="http://wordpress.org/tags/backwpup" target="_blank">'.__('Support').'</a>';
		$help .= ' | <a href="http://wordpress.org/extend/plugins/backwpup/faq/" target="_blank">' . __('FAQ') . '</a>';
		$help .= ' | <a href="http://danielhuesken.de/portfolio/backwpup" target="_blank">' . __('Plugin Homepage', 'backwpup') . '</a>';
		$help .= ' | <a href="http://wordpress.org/extend/plugins/backwpup" target="_blank">' . __('Plugin Home on WordPress.org', 'backwpup') . '</a>';
		$help .= ' | <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=daniel%40huesken-net%2ede&item_name=Daniel%20Huesken%20Plugin%20Donation&item_number=BackWPup&no_shipping=0&no_note=1&tax=0&currency_code=EUR&lc=DE&bn=PP%2dDonationsBF&charset=UTF%2d8" target="_blank">' . __('Donate') . '</a>';
		$help .= "</div>\n";	
		$help .= '<div class="metabox-prefs">';
		$help .= __('Version:', 'backwpup').' '.BACKWPUP_VERSION.' | ';
		$help .= __('Author:', 'backwpup').' <a href="http://danielhuesken.de" target="_blank">Daniel H&uuml;sken</a>';
		$help .= "</div>\n";
		return $help;
	}
	
	//Options Page
	function options() {
		global $wpdb;
		switch($_REQUEST['action']) {
		case 'edit':
			$jobs=get_option('backwpup_jobs');
		    $jobid = (int) $_REQUEST['jobid'];
			check_admin_referer('edit-job');
			require_once(WP_PLUGIN_DIR.'/'.BACKWPUP_PLUGIN_DIR.'/app/options-edit.php');
			break;
		case 'logs':
			$logs=get_option('backwpup_log');
			require_once(WP_PLUGIN_DIR.'/'.BACKWPUP_PLUGIN_DIR.'/app/options-logs.php');
			break;
		case 'settings':
			$cfg=get_option('backwpup');
			require_once(WP_PLUGIN_DIR.'/'.BACKWPUP_PLUGIN_DIR.'/app/options-settings.php');
			break;
		case 'runnow':
		    $jobid = (int) $_GET['jobid'];
			check_admin_referer('runnow-job_' . $jobid);
			$jobs=get_option('backwpup_jobs');
			require_once(WP_PLUGIN_DIR.'/'.BACKWPUP_PLUGIN_DIR.'/app/options-runnow.php');
			break;
		case 'view_log':
		    $log= (int) $_GET['log'];
			check_admin_referer('view-log');
			$logs=get_option('backwpup_log');
			$logfile=$logs[$log]['logfile'];
			require_once(WP_PLUGIN_DIR.'/'.BACKWPUP_PLUGIN_DIR.'/app/options-view_log.php');
			break;
		default:
			$jobs=get_option('backwpup_jobs');
			require_once(WP_PLUGIN_DIR.'/'.BACKWPUP_PLUGIN_DIR.'/app/options.php');
			break;
		}	
	}
	
	//Options Page
	function options_load() {
		global $wpdb;
		//Css for Admin Section
		wp_enqueue_style('BackWpup',plugins_url('/'.BACKWPUP_PLUGIN_DIR.'/app/css/options.css'),'',BACKWPUP_VERSION,'screen');
		//wp_enqueue_script('BackWpupOptions',plugins_url('/'.BACKWPUP_PLUGIN_DIR.'/app/js/options.js'),array('jquery','utils','jquery-ui-core'),BACKWPUP_VERSION,true);
		//For save Options
		require_once(WP_PLUGIN_DIR.'/'.BACKWPUP_PLUGIN_DIR.'/app/options-save.php');
		if ($_REQUEST['action2']!='-1' and !empty($_REQUEST['doaction2'])) 
			$_REQUEST['action']=$_REQUEST['action2'];
		switch($_REQUEST['action']) {
		case 'delete':
			if (is_Array($_POST['jobs'])) {
				check_admin_referer('actions-jobs');
				foreach ($_POST['jobs'] as $jobid) {
					BackWPupOptions::delete_job($jobid);
				}
			} else {
				$jobid = (int) $_GET['jobid'];
				check_admin_referer('delete-job_' . $jobid);
				BackWPupOptions::delete_job($jobid);
			}
			$_REQUEST['action']='';
			break;
		case 'delete-logs':
			if (is_Array($_POST['logs'])) {
				check_admin_referer('actions-logs');
				foreach ($_POST['logs'] as $timestamp) {
					BackWPupOptions::delete_log($timestamp);
				}
			} else {
				$timestamp = (int) $_GET['log'];
				check_admin_referer('delete-log_' . $timestamp);
				BackWPupOptions::delete_log($timestamp);
			}
			$_REQUEST['action']='logs';
			break;
		case 'saveeditjob':
			check_admin_referer('edit-job');
			BackWPupOptions::edit_job((int) $_POST['jobid']);
			break;
		case 'savecfg':
			check_admin_referer('backwpup-cfg');
			BackWPupOptions::config();
			$_REQUEST['action']='settings';
			break;
		case 'copy':
			$jobid = (int) $_GET['jobid'];
			check_admin_referer('copy-job_'.$jobid);
			BackWPupOptions::copy_job($jobid);
			$_REQUEST['action']='';
			break;
		case 'download':
			$log = (int) $_GET['log'];
			check_admin_referer('download-backup_'.$log);
			BackWPupOptions::download_backup($log);
			$_REQUEST['action']='logs';
			break;
		}	
	}
	
    //delete Otions
	function plugin_uninstall() {
		delete_option('backwpup');
		delete_option('backwpup_jobs');
		delete_option('backwpup_log');
	}
	
	//On Plugin activate
	function plugin_activate() {
		//add cron jobs
		$jobs=get_option('backwpup_jobs');
		if (is_array($jobs)) { 
			foreach ($jobs as $jobid => $jobvalue) {
				if ($jobvalue['activated']) {
					wp_schedule_event($jobvalue['scheduletime'], 'backwpup_int_'.$jobid, 'backwpup_cron',array('jobid'=>$jobid));
				}
			}
		}
	}
	
	//on Plugin deaktivate
	function plugin_deactivate() {
		//remove cron jobs
		$jobs=get_option('backwpup_jobs');
		if (is_array($jobs)) { 
			foreach ($jobs as $jobid => $jobvalue) {
				if ($time=wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid))) {
					wp_unschedule_event($time,'backwpup_cron',array('jobid'=>$jobid));
				}
			}
		}
	}
	
	//add edit setting to plugins page
	function plugin_options_link($links) {
		$settings_link='<a href="admin.php?page=BackWPup" title="' . __('Go to Settings Page','backwpup') . '" class="edit">' . __('Settings') . '</a>';
		array_unshift( $links, $settings_link ); 
		return $links;
	}
	
	//add links on plugins page
	function plugin_links($links, $file) {
		if ($file == BACKWPUP_PLUGIN_DIR.'/backwpup.php') {
			$links[] = '<a href="http://wordpress.org/extend/plugins/backwpup/faq/" target="_blank">' . __('FAQ') . '</a>';
			$links[] = '<a href="http://wordpress.org/tags/backwpup/" target="_blank">' . __('Support') . '</a>';
			$links[] = '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=daniel%40huesken-net%2ede&item_name=Daniel%20Huesken%20Plugin%20Donation&item_number=BackWPup&no_shipping=0&no_note=1&tax=0&currency_code=EUR&lc=DE&bn=PP%2dDonationsBF&charset=UTF%2d8" target="_blank">' . __('Donate') . '</a>';
		}
		return $links;
	}
	
	//Add cron interval
	function intervals($schedules) {
		$jobs=get_option('backwpup_jobs'); //Load Settings
		if (is_array($jobs)) {
			foreach ($jobs as $jobkey => $jobvalue) {
				if (!empty($jobvalue['scheduleinterval']))
					$intervals['backwpup_int_'.$jobkey]=array('interval' => $jobvalue['scheduleinterval'], 'display' => __('BackWPup Job '.$jobkey, 'backwpup'));
			}
			if (is_array($intervals)) 
				$schedules=array_merge($intervals,$schedules);
		} 
		return $schedules;
	}
	
	//DoJob
	function dojob($args) {
		global $wpdb;
		if (is_array($args)) { //cron gifes no complete arry back!!!
			extract($args, EXTR_SKIP );
		} else {
			$jobid=$args;
		}
		if (empty($jobid)) return false;
		require_once('dojob/bevore.php');
		switch($jobs[$jobid]['type']) {
		case 'DB+FILE':
			require_once('dojob/db.php');
			require_once('dojob/file.php');
			require_once('dojob/destination-dir.php');
			//require_once('dojob/destination-ftp.php');
			break;
		case 'DB':
			require_once('dojob/db.php');
			require_once('dojob/destination-dir.php');
			//require_once('dojob/destination-ftp.php');
			break;
		case 'FILE':
			require_once('dojob/file.php');
			require_once('dojob/destination-dir.php');
			//require_once('dojob/destination-ftp.php');
			break;
		case 'OPTIMIZE':
			require_once('dojob/optimize.php');
			break;
		}
		require_once('dojob/destination-mail.php');
		require_once('dojob/after.php');
		
		if ($returnlogfile)
			return $logfile;
		else 
			return;
	}
	
	//Make Log File for Jobs.
	function joblog($logfile,$entry) {
		if($file = fopen($logfile, 'a')) {
			fwrite($file, date('Y-m-d H:i.s').": ".$entry."\n");
			fclose($file);
		} 
	}
	
	//file size
	function formatBytes($bytes, $precision = 2) {
		$units = array('B', 'KB', 'MB', 'GB', 'TB');
		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);
		$bytes /= pow(1024, $pow);
		return round($bytes, $precision) . ' ' . $units[$pow];
	} 
	
	// add all action and so on only if plugin loaded.
	function init() {
		//load Text Domain
		load_plugin_textdomain('backwpup', false, BACKWPUP_PLUGIN_DIR.'/lang');	
		//add Menu
		add_action('admin_menu', array('BackWPupFunctions', 'menu_entry'));
		//Additional links on the plugin page
		add_filter('plugin_action_links_'.BACKWPUP_PLUGIN_DIR.'/backwpup.php', array('BackWPupFunctions', 'plugin_options_link'));
		add_filter('plugin_row_meta', array('BackWPupFunctions', 'plugin_links'),10,2);
		//add cron intervals
		add_filter('cron_schedules', array('BackWPupFunctions', 'intervals'));
		//Actions for Cron job
		add_action('backwpup_cron', array('BackWPupFunctions', 'dojob'));	
	} 	
}

?>