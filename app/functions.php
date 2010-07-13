<?PHP
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');
	
	//Thems Option menu entry
	function backwpup_menu_entry() {
		$hook = add_management_page(__('BackWPup','backwpup'), __('BackWPup','backwpup'), '10', 'BackWPup','backwpup_options_page') ;
		add_action('load-'.$hook, 'backwpup_options_load');
		add_contextual_help($hook,backwpup_show_help());
		switch($_REQUEST['action']) {
		case 'logs':
			register_column_headers($hook,array('cb'=>'<input type="checkbox" />','id'=>__('Job','backwpup'),'type'=>__('Type','backwpup'),'log'=>__('Backup/Log Date/Time','backwpup'),'status'=>__('Status','backwpup'),'size'=>__('Size','backwpup'),'runtime'=>__('Runtime','backwpup')));
			break;
		case 'edit':
			break;
		case 'settings':
			break;
		case 'tools':
			break;
		case 'runnow':
			add_action('load-'.$hook, 'backwpup_send_no_cache_header');
			add_action('admin_head-'.$hook, 'backwpup_meta_no_cache');
			break;
		case 'view_log':
			break;
		default:
			register_column_headers($hook,array('cb'=>'<input type="checkbox" />','id'=>__('ID','backwpup'),'jobname'=>__('Job Name','backwpup'),'type'=>__('Type','backwpup'),'next'=>__('Next Run','backwpup'),'last'=>__('Last Run','backwpup')));
			break;
		}
	}	
	
	// Help too display
	function backwpup_show_help() {
		$help .= '<div class="metabox-prefs">';
		$help .= '<a href="http://wordpress.org/tags/backwpup" target="_blank">'.__('Support').'</a>';
		$help .= ' | <a href="http://wordpress.org/extend/plugins/backwpup/faq/" target="_blank">' . __('FAQ') . '</a>';
		$help .= ' | <a href="http://danielhuesken.de/portfolio/backwpup" target="_blank">' . __('Plugin Homepage', 'backwpup') . '</a>';
		$help .= ' | <a href="http://wordpress.org/extend/plugins/backwpup" target="_blank">' . __('Plugin Home on WordPress.org', 'backwpup') . '</a>';
		$help .= ' | <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=daniel%40huesken-net%2ede&amp;item_name=Daniel%20Huesken%20Plugin%20Donation&amp;item_number=BackWPup&amp;no_shipping=0&amp;no_note=1&amp;tax=0&amp;currency_code=EUR&amp;lc=DE&amp;bn=PP%2dDonationsBF&amp;charset=UTF%2d8" target="_blank">' . __('Donate') . '</a>';
		$help .= " | <script type=\"text/javascript\">
					var flattr_btn = 'compact';
					var flattr_url = 'http://danielhuesken.de/portfolio/backwpup/';
					</script><script src=\"http://api.flattr.com/button/load.js\" type=\"text/javascript\"></script>";
		$help .= "</div>\n";	
		$help .= '<div class="metabox-prefs">';
		$help .= __('Version:', 'backwpup').' '.BACKWPUP_VERSION.' | ';
		$help .= __('Author:', 'backwpup').' <a href="http://danielhuesken.de" target="_blank">Daniel H&uuml;sken</a>';
		$help .= "</div>\n";
		return $help;
	}
	
	//Options Page
	function backwpup_options_page() {
		global $wpdb,$backwpup_message,$page_hook;
		if (!current_user_can(10)) 
			wp_die('No rights');
		if(!empty($backwpup_message)) 
			echo '<div id="message" class="updated fade"><p><strong>'.$backwpup_message.'</strong></p></div>';
		switch($_REQUEST['action']) {
		case 'edit':
			$jobs=get_option('backwpup_jobs');
		    $jobid = (int) $_REQUEST['jobid'];
			check_admin_referer('edit-job');
			require_once(plugin_dir_path(__FILE__).'options-jobs.php');
			break;
		case 'logs':
			$cfg=get_option('backwpup');
			require_once(plugin_dir_path(__FILE__).'options-logs.php');
			break;
		case 'settings':
			$cfg=get_option('backwpup');
			require_once(plugin_dir_path(__FILE__).'options-settings.php');
			break;
		case 'tools':
			require_once(plugin_dir_path(__FILE__).'options-tools.php');
			break;
		case 'runnow':
		    $jobid = (int) $_GET['jobid'];
			check_admin_referer('runnow-job_' . $jobid);
			$jobs=get_option('backwpup_jobs');
			require_once(plugin_dir_path(__FILE__).'options-runnow.php');
			break;
		case 'view_log':
			check_admin_referer('view-log_'.basename($_GET['logfile']));
			require_once(plugin_dir_path(__FILE__).'options-view_log.php');
			break;
		default:
			$jobs=get_option('backwpup_jobs');
			require_once(plugin_dir_path(__FILE__).'options.php');
			break;
		}	
	}
	
	//Options Page
	function backwpup_options_load() {
		global $wpdb,$backwpup_message;
		if (!current_user_can(10)) 
			wp_die('No rights');
		//Css for Admin Section
		wp_enqueue_style('BackWpup',plugins_url('css/options.css',__FILE__),'',BACKWPUP_VERSION,'screen');
		wp_enqueue_script('BackWpupOptions',plugins_url('js/options.js',__FILE__),'',BACKWPUP_VERSION,true);
		//For save Options
		require_once(plugin_dir_path(__FILE__).'options-save.php');
	}
	
    //delete Otions
	function backwpup_plugin_uninstall() {
		delete_option('backwpup');
		delete_option('backwpup_jobs');
	}

	//Checking,upgrade and default job setting
	function backwpup_check_job_vars($jobsettings) {
		global $wpdb;
		//check job type
		if (!isset($jobsettings['type']) or !is_string($jobsettings['type']))
			$jobsettings['type']='DB+FILE';
		$todo=explode('+',strtoupper($jobsettings['type']));
		foreach($todo as $key => $value) {
			if (!in_array($value,backwpup_backup_types()))
				unset($todo[$key]);
		}
		$jobsettings['type']=implode('+',$todo);
		if (empty($jobsettings['type']))
			$jobsettings['type']='DB+FILE';
		
		if (empty($jobsettings['name']) or !is_string($jobsettings['name']))
			$jobsettings['name']= __('New');
		
		if (!isset($jobsettings['activated']) or !is_bool($jobsettings['activated']))	
			$jobsettings['activated']=false;
		
		if (!isset($jobsettings['scheduletime']) or !is_numeric($jobsettings['scheduletime']))
			$jobsettings['scheduletime']=current_time('timestamp');
		
		if (!isset($jobsettings['scheduleintervaltype']) or !is_int($jobsettings['scheduleintervaltype']))			
			$jobsettings['scheduleintervaltype']=3600;
		if ($jobsettings['scheduleintervaltype']!=60 and $jobsettings['scheduleintervaltype']!=3600 and $jobsettings['scheduleintervaltype']!=86400)
			$jobsettings['scheduleintervaltype']=3600;
			
		if (!isset($jobsettings['scheduleintervalteimes']) or !is_int($jobsettings['scheduleintervalteimes']) or ($jobsettings['scheduleintervalteimes']<1 and $jobsettings['scheduleintervalteimes']>100))
			$jobsettings['scheduleintervalteimes']=1;
		
		$jobsettings['scheduleinterval']=$jobsettings['scheduleintervaltype']*$jobsettings['scheduleintervalteimes'];
		
		if (!is_string($jobsettings['mailaddresslog']) or false === $pos=strpos($jobsettings['mailaddresslog'],'@') or false === strpos($jobsettings['mailaddresslog'],'.',$pos))
			$jobsettings['mailaddresslog']=get_option('admin_email');
		
		if (!isset($jobsettings['mailerroronly']) or !is_bool($jobsettings['mailerroronly']))
			$jobsettings['mailerroronly']=true;
			
		if (!isset($jobsettings['dbexclude']) or !is_array($jobsettings['dbexclude'])) {
			$jobsettings['dbexclude']=array();
			$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');
			foreach ($tables as $table) {
				if (substr($table,0,strlen($wpdb->prefix))!=$wpdb->prefix)
					$jobsettings['dbexclude'][]=$table;
			}		
		}
		$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');
		foreach($jobsettings['dbexclude'] as $key => $value) {
			if (empty($jobsettings['dbexclude'][$key]) or !in_array($value,$tables))
				unset($jobsettings['dbexclude'][$key]);
		}
			
		if (!isset($jobsettings['dbshortinsert']) or !is_bool($jobsettings['dbshortinsert']))
			$jobsettings['dbshortinsert']=false;
		
		if (!isset($jobsettings['maintenance']) or !is_bool($jobsettings['maintenance']))
			$jobsettings['maintenance']=false;
		
		if (!isset($jobsettings['fileexclude']) or !is_string($jobsettings['fileexclude']))
			$jobsettings['fileexclude']='';
		$fileexclude=explode(',',$jobsettings['fileexclude']);
		foreach($fileexclude as $key => $value) {
			$fileexclude[$key]=str_replace('//','/',str_replace('\\','/',trim($value)));
			if (empty($fileexclude[$key]))
				unset($fileexclude[$key]);
		}
		$jobsettings['fileexclude']=implode(',',$fileexclude);	
		
		if (!isset($jobsettings['dirinclude']) or !is_string($jobsettings['dirinclude']))
			$jobsettings['dirinclude']='';
		$dirinclude=explode(',',$jobsettings['dirinclude']);
		foreach($dirinclude as $key => $value) {
			$dirinclude[$key]=str_replace('//','/',str_replace('\\','/',trim($value)));
			if (empty($dirinclude[$key]) or !is_dir($dirinclude[$key]))
				unset($dirinclude[$key]);
		}
		$jobsettings['dirinclude']=implode(',',$dirinclude);		

		if (!isset($jobsettings['backuproot']) or !is_bool($jobsettings['backuproot']))
			$jobsettings['backuproot']=true;
		
		if (!isset($jobsettings['backupcontent']) or !is_bool($jobsettings['backupcontent']))
			$jobsettings['backupcontent']=true;
		
		if (!isset($jobsettings['backupplugins']) or !is_bool($jobsettings['backupplugins']))
			$jobsettings['backupplugins']=true;
		
		$fileformarts=array('.zip','.tar.gz','tar.bz2','.tar');
		if (!isset($jobsettings['fileformart']) or !in_array($jobsettings['fileformart'],$fileformarts))
			$jobsettings['fileformart']='.zip';
		
		if (!isset($jobsettings['mailefilesize']) or !is_float($jobsettings['mailefilesize']))
			$jobsettings['mailefilesize']=0;
		
		if (!isset($jobsettings['backupdir']) or (!is_dir($jobsettings['backupdir']) and !empty($jobsettings['backupdir']))) {
			$rand = substr( md5( md5( SECURE_AUTH_KEY ) ), -5 );
			$jobsettings['backupdir']=str_replace('\\','/',trailingslashit(WP_CONTENT_DIR)).'backwpup-'.$rand.'/';		
		}
		$jobsettings['backupdir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($jobsettings['backupdir']))));
		if ($jobsettings['backupdir']=='/')
			$jobsettings['backupdir']='';
		
		if (!isset($jobsettings['maxbackups']) or !is_int($jobsettings['maxbackups']))
			$jobsettings['maxbackups']=0;
			
		if (!isset($jobsettings['ftphost']) or !is_string($jobsettings['ftphost']))
			$jobsettings['ftphost']='';
		
		if (!isset($jobsettings['ftpuser']) or !is_string($jobsettings['ftpuser']))	
			$jobsettings['ftpuser']='';
		
		if (!isset($jobsettings['ftppass']) or !is_string($jobsettings['ftppass']))
			$jobsettings['ftppass']='';

		if (!isset($jobsettings['ftpdir']) or !is_string($jobsettings['ftpdir']) or $jobsettings['ftpdir']=='/')		
			$jobsettings['ftpdir']='';
		$jobsettings['ftpdir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($jobsettings['ftpdir']))));
		if (substr($jobsettings['ftpdir'],0,1)=='/')
			$jobsettings['ftpdir']=substr($jobsettings['ftpdir'],1);
		
		if (!isset($jobsettings['ftpmaxbackups']) or !is_int($jobsettings['ftpmaxbackups']))
			$jobsettings['ftpmaxbackups']=0;
		
		if (!isset($jobsettings['awsAccessKey']) or !is_string($jobsettings['awsAccessKey']))
			$jobsettings['awsAccessKey']='';
			
		if (!isset($jobsettings['awsSecretKey']) or !is_string($jobsettings['awsSecretKey']))
			$jobsettings['awsSecretKey']='';
		
		if (!isset($jobsettings['awsSSL']) or !is_bool($jobsettings['awsSSL']))
			$jobsettings['awsSSL']=true;
		
		if (!isset($jobsettings['awsBucket']) or !is_string($jobsettings['awsBucket']))
			$jobsettings['awsBucket']='';
		
		if (!isset($jobsettings['awsdir']) or !is_string($jobsettings['awsdir']) or $jobsettings['awsdir']=='/')		
			$jobsettings['awsdir']='';
		$jobsettings['awsdir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($jobsettings['awsdir']))));
		if (substr($jobsettings['awsdir'],0,1)=='/')
			$jobsettings['awsdir']=substr($jobsettings['awsdir'],1);
		
		if (!isset($jobsettings['awsmaxbackups']) or !is_int($jobsettings['awsmaxbackups']))
			$jobsettings['awsmaxbackups']=0;		
		
		if (!is_string($jobsettings['mailaddress']) or false === $pos=strpos($jobsettings['mailaddress'],'@') or false === strpos($jobsettings['mailaddress'],'.',$pos))
			$jobsettings['mailaddress']='';

		return $jobsettings;
	}

	
	//On Plugin activate
	function backwpup_plugin_activate() {
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
		if (empty($cfg['maxlogs'])) $cfg['maxlogs']=0;
		if (empty($cfg['dirtemp'])) {
			$rand = substr( md5( md5( SECURE_AUTH_KEY ) ), -5 );
			$cfg['dirtemp']=str_replace('\\','/',trailingslashit(WP_CONTENT_DIR)).'uploads/';
		}
		if (empty($cfg['dirlogs'])) {
			$rand = substr( md5( md5( SECURE_AUTH_KEY ) ), -5 );
			$cfg['dirlogs']=str_replace('\\','/',trailingslashit(WP_CONTENT_DIR)).'/backwpup-'.$rand.'-logs/';
		}
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
		if ($file == BACKWPUP_PLUGIN_BASEDIR.'/backwpup.php') {
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
		global $backwpup_logfile;
		if (is_array($args)) //cron gifes no complete array back!!!
			extract($args, EXTR_SKIP );
		else
			$jobid=$args;
		if (empty($jobid)) 
			return false;
		require_once('backwpup_dojob.php');
		$dojob= new backwpup_dojob($jobid);
		unset($dojob);
		return $backwpup_logfile;
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
		$typename='';
		if (!empty($type)) {
			$todo=explode('+',$type);
			foreach($todo as $key => $value) {
				switch($value) {
				case 'WPEXP':
					$typename.=__('WP XML Export','backwpup')."<br />";
					break;
				case 'DB':
					$typename.=__('Database Backup','backwpup')."<br />";
					break;			
				case 'FILE':
					$typename.=__('File Backup','backwpup')."<br />";
					break;
				case 'OPTIMIZE':
					$typename.=__('Optimize Database Tables','backwpup')."<br />";
					break;
				case 'CHECK':
					$typename.=__('Check Database Tables','backwpup')."<br />";
					break;
				}
			}
		} else {
			$typename=array('WPEXP','DB','FILE','OPTIMIZE','CHECK');
		}
		
		if ($echo) 
			echo $typename;
		else
			return $typename;
	}
	
	//read log file header
	function backwpup_read_logheader($logfile) {
		$headers=array("backwpup_version" => "version","backwpup_logtime" => "logtime","backwpup_errors" => "errors","backwpup_warnings" => "warnings","backwpup_jobid" => "jobid","backwpup_jobname" => "name","backwpup_jobtype" => "type","backwpup_jobruntime" => "runtime","backwpup_backupfile" => "backupfile");
		if (!is_readable($logfile))
			return false;
		//Read file
		$fp = @fopen( $logfile, 'r' );
		$file_data = @fread( $fp, 1536 ); // Pull only the first 1,5kiB of the file in.
		@fclose( $fp );

		//get data form file
		foreach ($headers as $keyword => $field) {
			preg_match('/(<meta name="'.$keyword.'" content="(.*)" \/>)/i',$file_data,$content);
			if (!empty($content))
				$joddata[$field]=$content[2];
			else
				$joddata[$field]='';
		}
		
		if (empty($joddata['logtime']))
			$joddata['logtime']=filectime($logfile);
			
		return $joddata;
	}
	
	
	//Dashboard widget
	function backwpup_dashboard_output() {
		global $wpdb;
		$cfg=get_option('backwpup');
		echo '<strong>'.__('Logs:','backwpup').'</strong><br />';
		//get log files
		$logfiles=array();
		if ( $dir = @opendir( $cfg['dirlogs'] ) ) {
			while (($file = readdir( $dir ) ) !== false ) {
				if (is_file($cfg['dirlogs'].'/'.$file) and 'backwpup_log_' == substr($file,0,strlen('backwpup_log_')) and  '.html' == substr($file,-5))
					$logfiles[]=$file;
			}
			closedir( $dir );
			rsort($logfiles);
		}
		
		if (is_array($logfiles)) {
			$count=0;
			foreach ($logfiles as $logfile) {
				$logdata=backwpup_read_logheader($cfg['dirlogs'].'/'.$logfile);
				echo '<a href="'.wp_nonce_url('admin.php?page=BackWPup&action=view_log&logfile='.$cfg['dirlogs'].'/'.$logfile, 'view-log_'.$logfile).'" title="'.__('View Log','backwpup').'">'.date_i18n(get_option('date_format'),$logdata['logtime']).' '.date_i18n(get_option('time_format'),$logdata['logtime']).': <i>';
				if (empty($logdata['name'])) 
					echo $logdata['type'];
				else
					echo $logdata['name'];
				echo '</i>';
				if($logdata['errors']>0 or $logdata['warnings']>0) { 
					if ($logdata['errors']>0)
						echo ' <span style="color:red;">'.$logdata['errors'].' '.__('ERROR(S)','backwpup').'</span>'; 
					if ($logdata['warnings']>0)
						echo ' <span style="color:yellow;">'.$logdata['warnings'].' '.__('WARNING(S)','backwpup').'</span>'; 
				} else { 
					echo ' <span style="color:green;">'.__('OK','backwpup').'</span>';  
				} 
				echo '</a><br />';
				$count++;
				if ($count>=5)
					break;
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
						$runtime=current_time('timestamp')-$jobvalue['starttime'];
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
	
	//turn cache off
	function backwpup_meta_no_cache() {
		echo "<meta http-equiv=\"expires\" content=\"0\" />\n";
		echo "<meta http-equiv=\"pragma\" content=\"no-cache\" />\n";
		echo "<meta http-equiv=\"cache-control\" content=\"no-cache\" />\n";
	}
	
	
	function backwpup_send_no_cache_header() {
		header("Expires: 0");
		header("Cache-Control: no-cache, must-revalidate");
		header("Pragma: no-cache");
		header("Cache-Control: post-check=0, pre-check=0");
	}

    //ajax/normal get buckests select box
	function backwpup_get_aws_buckets($args='') {
		if (is_array($args)) {
			extract($args);
			$ajax=false;
		} else {
			$awsAccessKey=$_POST['awsAccessKey'];
			$awsSecretKey=$_POST['awsSecretKey'];
			$selected=$_POST['selected'];
			$ajax=true;
		}
		require_once(plugin_dir_path(__FILE__).'libs/s3.php');
		if (empty($awsAccessKey)) {
			echo '<span id="awsBucket" style="color:red;">'.__('Missing Access Key ID!','backwpup').'</span>';
			die();
		}
		if (empty($awsSecretKey)) {
			echo '<span id="awsBucket" style="color:red;">'.__('Missing Secret Access Key!','backwpup').'</span>';
			die();
		}			
		$s3 = new S3($awsAccessKey, $awsSecretKey, false);
		$buckets=@$s3->listBuckets();
		if (!is_array($buckets)) {
			echo '<span id="awsBucket" style="color:red;">'.__('No Buckets found! Or wrong Keys!','backwpup').'</span>';
			die();
		}	
		echo '<select name="awsBucket" id="awsBucket">';
		foreach ($buckets as $bucket) {
			echo "<option ".selected(strtolower($selected),strtolower($bucket),false).">".$bucket."</option>";
		}
		echo '</select>';
		if ($ajax)
			die();
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
			add_filter('plugin_action_links_'.BACKWPUP_PLUGIN_BASEDIR.'/backwpup.php', 'backwpup_plugin_options_link');
		if (current_user_can('install_plugins')) 		
			add_filter('plugin_row_meta', 'backwpup_plugin_links',10,2);
		//add cron intervals
		add_filter('cron_schedules', 'backwpup_intervals');
		//Actions for Cron job
		add_action('backwpup_cron', 'backwpup_dojob');
		//add Dashboard widget
		if (current_user_can(10)) 
			add_action('wp_dashboard_setup', 'backwpup_add_dashboard');
		// add ajax function
		add_action('wp_ajax_backwpup_get_aws_buckets', 'backwpup_get_aws_buckets');
	} 	

?>