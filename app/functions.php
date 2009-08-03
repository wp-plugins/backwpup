<?PHP
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');
	
	//Thems Option menu entry
	function backwpup_menu_entry() {
		$hook = add_management_page(__('BackWPup','backwpup'), __('BackWPup','backwpup'), '10', 'BackWPup','backwpup_options_page') ;
		add_action('load-'.$hook, 'backwpup_options_load');
		add_contextual_help($hook,backwpup_show_help());
	}	
	
	// Help too display
	function backwpup_show_help() {
		$help .= '<div class="metabox-prefs">';
		$help .= '<a href="http://wordpress.org/tags/backwpup" target="_blank">'.__('Support').'</a>';
		$help .= ' | <a href="http://wordpress.org/extend/plugins/backwpup/faq/" target="_blank">' . __('FAQ') . '</a>';
		$help .= ' | <a href="http://danielhuesken.de/portfolio/backwpup" target="_blank">' . __('Plugin Homepage', 'backwpup') . '</a>';
		$help .= ' | <a href="http://wordpress.org/extend/plugins/backwpup" target="_blank">' . __('Plugin Home on WordPress.org', 'backwpup') . '</a>';
		$help .= ' | <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=daniel%40huesken-net%2ede&amp;item_name=Daniel%20Huesken%20Plugin%20Donation&amp;item_number=BackWPup&amp;no_shipping=0&amp;no_note=1&amp;tax=0&amp;currency_code=EUR&amp;lc=DE&amp;bn=PP%2dDonationsBF&amp;charset=UTF%2d8" target="_blank">' . __('Donate') . '</a>';
		$help .= "</div>\n";	
		$help .= '<div class="metabox-prefs">';
		$help .= __('Version:', 'backwpup').' '.BACKWPUP_VERSION.' | ';
		$help .= __('Author:', 'backwpup').' <a href="http://danielhuesken.de" target="_blank">Daniel H&uuml;sken</a>';
		$help .= "</div>\n";
		return $help;
	}
	
	//Options Page
	function backwpup_options_page() {
		global $wpdb,$backwpup_message;
		if (!current_user_can(10)) 
			wp_die('No rights');
		if(!empty($backwpup_message)) 
			echo '<div id="message" class="updated fade"><p><strong>'.$backwpup_message.'</strong></p></div>';
		switch($_REQUEST['action']) {
		case 'edit':
			$jobs=get_option('backwpup_jobs');
		    $jobid = (int) $_REQUEST['jobid'];
			check_admin_referer('edit-job');
			require_once(WP_PLUGIN_DIR.'/'.BACKWPUP_PLUGIN_DIR.'/app/options-jobs.php');
			break;
		case 'logs':
			require_once(WP_PLUGIN_DIR.'/'.BACKWPUP_PLUGIN_DIR.'/app/options-logs.php');
			break;
		case 'settings':
			$cfg=get_option('backwpup');
			require_once(WP_PLUGIN_DIR.'/'.BACKWPUP_PLUGIN_DIR.'/app/options-settings.php');
			break;
		case 'tools':
			require_once(WP_PLUGIN_DIR.'/'.BACKWPUP_PLUGIN_DIR.'/app/options-tools.php');
			break;
		case 'runnow':
		    $jobid = (int) $_GET['jobid'];
			check_admin_referer('runnow-job_' . $jobid);
			$jobs=get_option('backwpup_jobs');
			require_once(WP_PLUGIN_DIR.'/'.BACKWPUP_PLUGIN_DIR.'/app/options-runnow.php');
			break;
		case 'view_log':
		    $logtime= (int) $_GET['logtime'];
			check_admin_referer('view-log');
			require_once(WP_PLUGIN_DIR.'/'.BACKWPUP_PLUGIN_DIR.'/app/options-view_log.php');
			break;
		default:
			$jobs=get_option('backwpup_jobs');
			require_once(WP_PLUGIN_DIR.'/'.BACKWPUP_PLUGIN_DIR.'/app/options.php');
			break;
		}	
	}
	
	//Options Page
	function backwpup_options_load() {
		global $wpdb,$backwpup_message;
		if (!current_user_can(10)) 
			wp_die('No rights');
		//Css for Admin Section
		wp_enqueue_style('BackWpup',plugins_url('/'.BACKWPUP_PLUGIN_DIR.'/app/css/options.css'),'',BACKWPUP_VERSION,'screen');
		//wp_enqueue_script('BackWpupOptions',plugins_url('/'.BACKWPUP_PLUGIN_DIR.'/app/js/options.js'),array('jquery','utils','jquery-ui-core'),BACKWPUP_VERSION,true);
		//For save Options
		require_once(WP_PLUGIN_DIR.'/'.BACKWPUP_PLUGIN_DIR.'/app/options-save.php');
	}
	
    //delete Otions
	function backwpup_plugin_uninstall() {
		global $wpdb;
		delete_option('backwpup');
		delete_option('backwpup_jobs');
		$wpdb->query("DROP TABLE IF EXISTS ".$wpdb->backwpup_logs);
	}
	
	//On Plugin activate
	function backwpup_plugin_activate() {
		global $wpdb;

		//Create log table
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		$charset_collate = '';
		if($wpdb->supports_collation()) {
			if(!empty($wpdb->charset)) {
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			}
			if(!empty($wpdb->collate)) {
				$charset_collate .= " COLLATE $wpdb->collate";
			}
		}
		$statements = array( 
			"CREATE TABLE ".$wpdb->backwpup_logs." (
			logtime BIGINT NOT NULL,
			jobid INT NOT NULL,
			jobname VARCHAR(255) NOT NULL,
			type VARCHAR(20) NOT NULL,
			error TINYINT NOT NULL default '0',
			warning TINYINT NOT NULL default '0',
			worktime TINYINT NOT NULL default '0',
			log LONGTEXT NOT NULL default '',
			backupfile VARCHAR(255),
			PRIMARY KEY (logtime)
			)".$charset_collate,
		);
		$sql = implode(';', $statements);
		dbDelta($sql);
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
	function backwpup_plugin_deactivate() {
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
	function backwpup_plugin_options_link($links) {
		$settings_link='<a href="admin.php?page=BackWPup" title="' . __('Go to Settings Page','backwpup') . '" class="edit">' . __('Settings') . '</a>';
		array_unshift( $links, $settings_link ); 
		return $links;
	}
	
	//add links on plugins page
	function backwpup_plugin_links($links, $file) {
		if ($file == BACKWPUP_PLUGIN_DIR.'/backwpup.php') {
			$links[] = '<a href="http://wordpress.org/extend/plugins/backwpup/faq/" target="_blank">' . __('FAQ') . '</a>';
			$links[] = '<a href="http://wordpress.org/tags/backwpup/" target="_blank">' . __('Support') . '</a>';
			$links[] = '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=daniel%40huesken-net%2ede&item_name=Daniel%20Huesken%20Plugin%20Donation&item_number=BackWPup&no_shipping=0&no_note=1&tax=0&currency_code=EUR&lc=DE&bn=PP%2dDonationsBF&charset=UTF%2d8" target="_blank">' . __('Donate') . '</a>';
		}
		return $links;
	}
	
	//Add cron interval
	function backwpup_intervals($schedules) {
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
	function backwpup_dojob($args) {
		global $wpdb;
		
		if (is_array($args)) { //cron gifes no complete arry back!!!
			extract($args, EXTR_SKIP );
		} else {
			$jobid=$args;
		}
		if (empty($jobid)) return false;
		require_once(ABSPATH . 'wp-admin/includes/file.php'); //for get_tempdir();
		require_once('dojob/bevore.php');
		switch($jobs[$jobid]['type']) {
		case 'DB+FILE':
			require_once(ABSPATH . 'wp-admin/includes/class-pclzip.php');
			require_once('dojob/db.php');
			require_once('dojob/file.php');
			require_once('dojob/destination-ftp.php');
			break;
		case 'DB':
			require_once(ABSPATH . 'wp-admin/includes/class-pclzip.php');
			require_once('dojob/db.php');
			require_once('dojob/destination-ftp.php');
			break;
		case 'FILE':
			require_once(ABSPATH . 'wp-admin/includes/class-pclzip.php');
			require_once('dojob/file.php');
			require_once('dojob/destination-ftp.php');
			break;
		case 'OPTIMIZE':
			require_once('dojob/optimize.php');
			break;
		case 'CHECK':
			require_once('dojob/check.php');
			break;
		}
		require_once('dojob/destination-mail.php');
		require_once('dojob/after.php');
		
		return $logtime;
	}
	
	//increase Memory need free memory in bytes
	function backwpup_needfreememory($memneed) {
		global $logtime;
		if (!function_exists('memory_get_usage'))
			return true;
			
		//calc mem to bytes
		if (strtoupper(substr(trim(ini_get('memory_limit')),-1))=='K')
			$memory=trim(substr(ini_get('memory_limit'),0,-1))*1024;
		elseif (strtoupper(substr(trim(ini_get('memory_limit')),-1))=='M')
			$memory=trim(substr(ini_get('memory_limit'),0,-1))*1024*1024;
		elseif (strtoupper(substr(trim(ini_get('memory_limit')),-1))=='G')
			$memory=trim(substr(ini_get('memory_limit'),0,-1))*1024*1024*1024;
		else
			$memory=trim(ini_get('memory_limit'));
			
		if (memory_get_usage()+$memneed>$memory) { // increase Memory
			if (ini_get('safe_mode') or strtolower(ini_get('safe_mode'))=='on' or ini_get('safe_mode')=='1') {
				backwpup_joblog($logtime,__('WARNING:','backwpup').' '.sprintf(__('PHP Safe Mode is on!!! Can not increse Memory Limit is %1$s','backwpup'),ini_get('memory_limit')));
				return false;
			}
			$newmemory=round((memory_get_usage()+$memneed)/1024/1024)+1;
			if ($oldmem=ini_set('memory_limit', $newmemory.'M')) 
				backwpup_joblog($logtime,sprintf(__('Memory incresed from %1$s to %2$s','backwpup'),$oldmem,ini_get('memory_limit')));
			else 
				backwpup_joblog($logtime,sprintf(__('ERROR:','backwpup').' '.__('Can not increse Memory Limit is %1$s','backwpup'),ini_get('memory_limit')));
		} 
		return true;
	}
	
	
	//Make Log File for Jobs.
	function backwpup_joblog($logtime,$entry) {
		global $wpdb;
		$errors=0;$warnings=0;
		if (substr($entry,0,strlen(__('ERROR:','backwpup')))==__('ERROR:','backwpup'))
			$errors=1;
		if (substr($entry,0,strlen(__('WARNING:','backwpup')))==__('WARNING:','backwpup'))
			$warnings=1;
		mysql_query("UPDATE ".$wpdb->backwpup_logs." SET error=error+".$errors.", warning=warning+".$warnings.", log=concat(log,'".mysql_real_escape_string(date('Y-m-d H:i:s').": ".$entry."\n")."') WHERE logtime=".$logtime);
		if (!defined('DOING_CRON'))
			echo date('Y-m-d H:i:s').": ".$entry."\n";
		flush();
	}
	
	//file size
	function backwpup_formatBytes($bytes, $precision = 2) {
		$units = array('B', 'KB', 'MB', 'GB', 'TB');
		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);
		$bytes /= pow(1024, $pow);
		return round($bytes, $precision) . ' ' . $units[$pow];
	} 
	
	//echo long backup type name
	function backwpup_backup_types($type='',$echo=false) {
		switch($type) {
		case 'DB+FILE':
			$typename=__('Database &amp; File Backup','backwpup');
			break;
		case 'DB':
			$typename=__('Database Backup','backwpup');
			break;			
		case 'FILE':
			$typename=__('File Backup','backwpup');
			break;
		case 'OPTIMIZE':
			$typename=__('Optimize Database Tabels','backwpup');
			break;
		case 'CHECK':
			$typename=__('Check Database Tabels','backwpup');
			break;
		default:
			$typename=array('DB+FILE','DB','FILE','OPTIMIZE','CHECK');
			break;
		}
		if ($echo and !empty($type)) 
			echo $typename;
		else
			return $typename;
	}
	
	//Dashboard widget
	function backwpup_dashboard_output() {
		global $wpdb;
		echo '<strong>'.__('Logs:','backwpup').'</strong><br />';
		$logs=$wpdb->get_results("SELECT * FROM ".$wpdb->backwpup_logs." ORDER BY logtime DESC LIMIT 5", ARRAY_A);
		$wpdb->flush();
		if (is_array($logs)) { 
			foreach ($logs as $logvalue) {
				echo '<a href="'.wp_nonce_url('admin.php?page=BackWPup&action=view_log&logtime='.$logvalue['logtime'], 'view-log').'" title="'.__('View Log','backwpup').'">'.date(get_option('date_format'),$logvalue['logtime']).' '.date(get_option('time_format'),$logvalue['logtime']).': <i>';
				if (empty($logvalue['jobname'])) 
					backwpup_backup_types($logvalue['type'],true);
				else
					echo $logvalue['jobname'];
				echo '</i>';
				if($logvalue['error']>0 or $logvalue['warning']>0) { 
					if ($logvalue['error']>0)
						echo ' <span style="color:red;">'.$logvalue['error'].' '.__('ERROR(S)','backwpup').'</span>'; 
					if ($logvalue['warning']>0)
						echo ' <span style="color:yellow;">'.$logvalue['warning'].' '.__('WARNING(S)','backwpup').'</span>'; 
				} else { 
					echo ' <span style="color:green;">'.__('OK','backwpup').'</span>';  
				} 
				echo '</a><br />';
			}
		} else {
			echo '<i>'.__('none','backwpup').'</i><br />';
		}
		$jobs=get_option('backwpup_jobs');
		echo '<strong>'.__('Scheduled Jobs:','backwpup').'</strong><br />';
		if (is_array($jobs)) { 
			foreach ($jobs as $jobid => $jobvalue) {
				if (wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid))) {
					echo '<a href="'.wp_nonce_url('admin.php?page=BackWPup&action=edit&jobid='.$jobid, 'edit-job').'" title="'.__('Edit Job','backwpup').'">';
					if ($jobvalue['starttime']>0 and empty($jobvalue['stoptime'])) {
						$runtime=time()-$jobvalue['starttime'];
						echo __('Running since:','backwpup').' '.$runtime.' '.__('sec.','backwpup');
					} elseif ($time=wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid))) {
						echo date(get_option('date_format'),$time).' '.date(get_option('time_format'),$time);
					}
					echo ': <span>'.$jobvalue['name'].'</span></a><br />';
				}
			}
		} else {
			echo '<i>'.__('none','backwpup').'</i><br />';
		}
	}
	
	//add dashboard widget
	function backwpup_add_dashboard() {
		wp_add_dashboard_widget( 'backwpup_dashboard_widget', 'BackWPup', 'backwpup_dashboard_output' );		
	}
	
	//Sed mail send Method
	function backwpup_use_mail_method() {
		global $phpmailer;
		$cfg=get_option('backwpup'); //Load Settings
		if ($cfg['mailmethod']=="SMTP") {
			$smtpport=25;
			$smtphost=$cfg['mailhost'];
			if (false !== strpos($cfg['mailhost'],':')) //look for port
				list($smtphost,$smtpport)=split(':',$cfg['mailhost'],2);
			$phpmailer->Host=$smtphost;
			$phpmailer->Port=$smtpport;
			$phpmailer->SMTPSecure=$cfg['mailsecure'];
			$phpmailer->Username=$cfg['mailuser'];
			$phpmailer->Password=base64_decode($cfg['mailpass']);
			if (!empty($cfg['mailuser']) and !empty($cfg['mailpass']))
				$phpmailer->SMTPAuth=true;
			$phpmailer->IsSMTP();
		} elseif ($cfg['mailmethod']=="Sendmail") {
			$phpmailer->Sendmail=$cfg['mailsendmail'];
			$phpmailer->IsSendmail();
		} else {
			$phpmailer->IsMail();
		}
	}
	
	// add all action and so on only if plugin loaded.
	function backwpup_init() {
		//Disabele WP_Corn
		$cfg=get_option('backwpup');
		if ($cfg['disablewpcron'])
			define('DISABLE_WP_CRON',true);
		//add Menu
		add_action('admin_menu', 'backwpup_menu_entry');
		//Additional links on the plugin page
		if (current_user_can(10)) 
			add_filter('plugin_action_links_'.BACKWPUP_PLUGIN_DIR.'/backwpup.php', 'backwpup_plugin_options_link');
		if (current_user_can('install_plugins')) 		
			add_filter('plugin_row_meta', 'backwpup_plugin_links',10,2);
		//add cron intervals
		add_filter('cron_schedules', 'backwpup_intervals');
		//Actions for Cron job
		add_action('backwpup_cron', 'backwpup_dojob');
		//add Dashboard widget
		if (current_user_can(10)) 
			add_action('wp_dashboard_setup', 'backwpup_add_dashboard');
	} 	

?>