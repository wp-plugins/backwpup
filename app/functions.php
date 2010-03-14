<?PHP
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');
	
	//Thems Option menu entry
	function backwpup_menu_entry() {
		$hook = add_management_page(__('BackWPup','backwpup'), __('BackWPup','backwpup'), '10', 'BackWPup','backwpup_options_page') ;
		add_action('load-'.$hook, 'backwpup_options_load');
		add_contextual_help($hook,backwpup_show_help());
		register_column_headers('backwpup_options',array('cb'=>'<input type="checkbox" />','id'=>__('ID','backwpup'),'name'=>__('Name','backwpup'),'type'=>__('Type','backwpup'),'next'=>__('Next Run','backwpup'),'last'=>__('Last Run','backwpup')));
		register_column_headers('backwpup_options_logs',array('cb'=>'<input type="checkbox" />','id'=>__('Job','backwpup'),'log'=>__('Backup/Log Date/Time','backwpup')));
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
			$cfg=get_option('backwpup');
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
		//wp_enqueue_script('BackWpupOptions',plugins_url('/'.BACKWPUP_PLUGIN_DIR.'/app/js/otions.js'),'',BACKWPUP_VERSION,true);
		//For save Options
		require_once(WP_PLUGIN_DIR.'/'.BACKWPUP_PLUGIN_DIR.'/app/options-save.php');
	}
	
    //delete Otions
	function backwpup_plugin_uninstall() {
		delete_option('backwpup');
		delete_option('backwpup_jobs');
	}
	
	//On Plugin activate
	function backwpup_plugin_activate() {
		//delete old log table
		global $wpdb;
		$wpdb->backwpup_logs = $wpdb->prefix.'backwpup_logs';
		$wpdb->query("DROP TABLE IF EXISTS ".$wpdb->backwpup_logs);
		//add cron jobs
		$jobs=get_option('backwpup_jobs');
		if (is_array($jobs)) { 
			foreach ($jobs as $jobid => $jobvalue) {
				if ($jobvalue['activated']) {
					wp_schedule_event($jobvalue['scheduletime'], 'backwpup_int_'.$jobid, 'backwpup_cron',array('jobid'=>$jobid));
				}
			}
		}
		//Set defaults
		$cfg=get_option('backwpup'); //Load Settings
		if (empty($cfg['mailsndemail'])) $cfg['mailsndemail']=sanitize_email(get_bloginfo( 'admin_email' ));
	    if (empty($cfg['mailsndname'])) $cfg['mailsndname']='BackWPup '.get_bloginfo( 'name' );
	    if (empty($cfg['mailmethod'])) $cfg['mailmethod']='mail';
		if (empty($cfg['mailsendmail'])) $cfg['mailsendmail']=substr(ini_get('sendmail_path'),0,strpos(ini_get('sendmail_path'),' -'));
		if (empty($cfg['memorylimit'])) $cfg['memorylimit']='128M';
		if (empty($cfg['maxlogs'])) $cfg['maxlogs']=0;
		if (empty($cfg['dirlogs'])) $cfg['dirlogs']=str_replace('\\','/',stripslashes(get_temp_dir().'backwpup/logs'));
		if (empty($cfg['dirtemp'])) $cfg['dirtemp']=str_replace('\\','/',stripslashes(get_temp_dir().'backwpup'));
		update_option('backwpup',$cfg);
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
			$links[] = '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=daniel%40huesken-net%2ede&amp;item_name=Daniel%20Huesken%20Plugin%20Donation&amp;item_number=BackWPup&amp;no_shipping=0&amp;no_note=1&amp;tax=0&amp;currency_code=EUR&amp;lc=DE&amp;bn=PP%2dDonationsBF&amp;charset=UTF%2d8" target="_blank">' . __('Donate') . '</a>';
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
		if (is_array($args)) { //cron gifes no complete array back!!!
			extract($args, EXTR_SKIP );
		} else {
			$jobid=$args;
		}
		if (empty($jobid)) return false;
		require_once(ABSPATH . 'wp-admin/includes/file.php'); //for get_tempdir();
		require_once('backwpup_dojob.php');
		$dojob= new backwpup_dojob($jobid);
		unset($dojob);
		return BACKWPUP_LOGFILE;
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
			$typename=__('Optimize Database Tables','backwpup');
			break;
		case 'CHECK':
			$typename=__('Check Database Tables','backwpup');
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
	
	//add spases
	function backwpup_fillspases($number) {
		$spaces='';
		for ($i=0;$i<$number;$i++) {
			$spaces.=' ';
		}
		return $spaces;
	}
	
	//read log file header
	function backwpup_read_logheader($logfile) {
		$headers=array("backwupu_errors" => "errors","backwupu_warnings" => "warnings","backwupu_jobid" => "jobid","backwupu_jobname" => "name","backwupu_jobtype" => "type","backwupu_jobruntime" => "runtime");
		//Read file
		$fp = @fopen( $logfile, 'r' );
		$file_data = @fread( $fp, 4096 ); // Pull only the first 4kiB of the file in.
		@fclose( $fp );

		//get data form file
		foreach ($headers as $keyword => $field) {
			preg_match('/(<meta name="'.$keyword.'" content="(.*)" \/>)/i',$file_data,$content);
			if (!empty($content))
				$joddata[$field]=$content[2];
			else
				$joddata[$field]='';
		}
		return $joddata;
	}
	
	
	//Dashboard widget
	function backwpup_dashboard_output() {
		global $wpdb;
		echo '<strong>'.__('Logs:','backwpup').'</strong><br />';
		$logs=$wpdb->get_results("SELECT * FROM ".$wpdb->backwpup_logs." ORDER BY logtime DESC LIMIT 5", ARRAY_A);
		$wpdb->flush();
		if (is_array($logs)) { 
			foreach ($logs as $logvalue) {
				echo '<a href="'.wp_nonce_url('admin.php?page=BackWPup&action=view_log&logtime='.$logvalue['logtime'], 'view-log').'" title="'.__('View Log','backwpup').'">'.date_i18n(get_option('date_format'),$logvalue['logtime']).' '.date_i18n(get_option('time_format'),$logvalue['logtime']).': <i>';
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
						echo date_i18n(get_option('date_format'),$time).' '.date_i18n(get_option('time_format'),$time);
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