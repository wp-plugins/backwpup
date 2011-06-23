<?PHP
// don't load directly
if ( !defined('ABSPATH') )
	die('-1');

//Thems Option menu entry
function backwpup_admin_menu() {
	$hook = add_management_page(__('BackWPup','backwpup'), __('BackWPup','backwpup'), BACKWPUP_USER_CAPABILITY, 'BackWPup','backwpup_options_page') ;
	add_action('load-'.$hook, 'backwpup_options_load');
}

//Options Page
function backwpup_options_page() {
	global $table,$backwpup_message,$page_hook;
	if (!current_user_can(BACKWPUP_USER_CAPABILITY))
		return;
	if(!empty($backwpup_message))
		echo '<div id="message" class="updated fade"><p><strong>'.$backwpup_message.'</strong></p></div>';
	switch($_REQUEST['subpage']) {
	case 'edit':
		require_once(dirname(__FILE__).'/options-edit-job.php');
		break;
	case 'logs':
		echo "<div class=\"wrap\">";
		echo "<div id=\"icon-tools\" class=\"icon32\"><br /></div>";
		echo "<h2>".__('BackWPup Logs', 'backwpup')."</h2>";
		backwpup_option_submenues();
		echo "<form id=\"posts-filter\" action=\"\" method=\"post\">";
		echo "<input type=\"hidden\" name=\"page\" value=\"BackWPup\" />";
		echo "<input type=\"hidden\" name=\"subpage\" value=\"logs\" />";
		$table->display();
		echo "<div id=\"ajax-response\"></div>";
		echo "</form>";
		echo "</div>";
		break;
	case 'settings':
		require_once(dirname(__FILE__).'/options-settings.php');
		break;
	case 'tools':
		require_once(dirname(__FILE__).'/options-tools.php');
		break;
	case 'backups':
		echo "<div class=\"wrap\">";
		echo "<div id=\"icon-tools\" class=\"icon32\"><br /></div>";
		echo "<h2>".__('BackWPup Manage Backups', 'backwpup')."</h2>";
		backwpup_option_submenues();
		echo "<form id=\"posts-filter\" action=\"\" method=\"post\">";
		echo "<input type=\"hidden\" name=\"page\" value=\"BackWPup\" />";
		echo "<input type=\"hidden\" name=\"subpage\" value=\"backups\" />";
		$table->display();
		echo "<div id=\"ajax-response\"></div>";
		echo "</form>"; 
		echo "</div>";
		break;
	case 'runnow':
		$jobid = (int) $_GET['jobid'];
		check_admin_referer('runnow-job_' . $jobid);
		$jobs=get_option('backwpup_jobs');
		echo "<div class=\"wrap\">";
		echo "<div id=\"icon-tools\" class=\"icon32\"><br /></div>";
		echo "<h2>".__('BackWPup Job Running', 'backwpup')."</h2>";
		backwpup_option_submenues();
		echo "<br class=\"clear\" />";
		echo "<big>".__('Running Job','backwpup')." <strong>".$jobs[$jobid]['name']."</strong></big>";
		echo "<iframe src=\"".wp_nonce_url(plugins_url('options-runnow-iframe.php',__FILE__).'?wpabs='.trailingslashit(ABSPATH).'&jobid=' . $jobid, 'dojob-now_' . $jobid)."\" name=\"runframe\" id=\"runframe\" width=\"100%\" height=\"450\" align=\"left\" scrolling=\"auto\" style=\"border: 1px solid gray\" frameborder=\"0\"></iframe>";
		echo "</div>";
		break;
	case 'view_log':
		check_admin_referer('view-log_'.basename($_GET['logfile']));
		echo "<div class=\"wrap\">";
		echo "<div id=\"icon-tools\" class=\"icon32\"><br /></div>";
		echo "<h2>".__('BackWPup View Logs', 'backwpup')."</h2>";
		backwpup_option_submenues();
		echo "<br class=\"clear\" />";
		echo "<big>".__('View Log','backwpup')." <strong>".basename($_GET['logfile'])."</strong></big>";
		echo "<iframe src=\"".wp_nonce_url(plugins_url('options-view_log-iframe.php',__FILE__).'?wpabs='.trailingslashit(ABSPATH).'&logfile=' . $_GET['logfile'], 'viewlognow_'.basename($_GET['logfile']))."\" name=\"logframe\" id=\"logframe\" width=\"100%\" height=\"450\" align=\"left\" scrolling=\"auto\" style=\"border: 1px solid gray\" frameborder=\"0\"></iframe>";
		echo "</div>";
		break;
	default:
		echo "<div class=\"wrap\">";
		echo "<div id=\"icon-tools\" class=\"icon32\"><br /></div>";
		echo "<h2>".__('BackWPup', 'backwpup')."&nbsp;<a href=\"".wp_nonce_url('admin.php?page=BackWPup&subpage=edit', 'edit-job')."\" class=\"button add-new-h2\">".esc_html__('Add New')."</a></h2>";
		backwpup_option_submenues();
		echo "<form id=\"posts-filter\" action=\"\" method=\"post\">";
		echo "<input type=\"hidden\" name=\"page\" value=\"BackWPup\" />";
		echo "<input type=\"hidden\" name=\"subpage\" value=\"\" />";
		$table->display();
		echo "<div id=\"ajax-response\"></div>";
		echo "</form>"; 
		echo "</div>";
		break;
	}
}

//Options Page
function backwpup_options_load() {
	global $current_screen,$table,$backwpup_message;
	if (!current_user_can(BACKWPUP_USER_CAPABILITY))
		return;
	//Css for Admin Section
	wp_enqueue_style('BackWpup',plugins_url('css/options.css',__FILE__),'',BACKWPUP_VERSION,'screen');
	wp_enqueue_script('common');
	wp_enqueue_script('wp-lists');
	wp_enqueue_script('postbox');
	wp_enqueue_script('BackWpupOptions',plugins_url('js/options.js',__FILE__),'',BACKWPUP_VERSION,true);

	add_contextual_help($current_screen,
		'<div class="metabox-prefs">'.
		'<a href="http://wordpress.org/tags/backwpup" target="_blank">'.__('Support').'</a>'.
		' | <a href="http://wordpress.org/extend/plugins/backwpup/faq/" target="_blank">' . __('FAQ') . '</a>'.
		' | <a href="http://danielhuesken.de/portfolio/backwpup" target="_blank">' . __('Plugin Homepage', 'backwpup') . '</a>'.
		' | <a href="http://wordpress.org/extend/plugins/backwpup" target="_blank">' . __('Plugin Home on WordPress.org', 'backwpup') . '</a>'.
		' | <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=daniel%40huesken-net%2ede&amp;item_name=Daniel%20Huesken%20Plugin%20Donation&amp;item_number=BackWPup&amp;no_shipping=0&amp;no_note=1&amp;tax=0&amp;bn=PP%2dDonationsBF&amp;charset=UTF%2d8" target="_blank">' . __('Donate') . '</a>'.
		' | <script type="text/javascript">
					var flattr_btn = \'compact\'
					var flattr_url = \'http://danielhuesken.de/portfolio/backwpup/\'
					</script><script src="http://api.flattr.com/button/load.js" type="text/javascript"></script>'.
		'</div>'.
		'<div class="metabox-prefs">'.
		__('Version:', 'backwpup').' '.BACKWPUP_VERSION.' | '.
		__('Author:', 'backwpup').' <a href="http://danielhuesken.de" target="_blank">Daniel H&uuml;sken</a>'.
		'</div>'
	);
		
	if ($_REQUEST['action2']!='-1' and $_REQUEST['action']=='-1')
		$_REQUEST['action']=$_REQUEST['action2'];

	switch($_REQUEST['subpage']) {
	case 'logs':
		if (!empty($_REQUEST['action'])) {
			require_once(dirname(__FILE__).'/options-save.php');
			backwpup_log_operations($_REQUEST['action']);
		}
		require_once(dirname(__FILE__).'/list-tables.php');
		$table = new BackWPup_Logs_Table;
		$table->check_permissions();
		$table->prepare_items();
		break;
	case 'edit':
		if (!empty($_POST['submit']) or !empty($_REQUEST['dropboxauth'])  or !empty($_REQUEST['dropboxauthdel'])) {
			require_once(dirname(__FILE__).'/options-save.php');
			if ($_GET['dropboxauth']=='AccessToken') 
				$backwpup_message=backwpup_save_dropboxauth();
			else
				$backwpup_message=backwpup_save_job();
		}
		break;
	case 'settings':
		if (!empty($_POST['submit'])) {
			require_once(dirname(__FILE__).'/options-save.php');
			$backwpup_message=backwpup_save_settings();
		}
		break;
	case 'tools':
		break;
	case 'backups':
		if (!empty($_REQUEST['action'])) {
			require_once(dirname(__FILE__).'/options-save.php');
			backwpup_backups_operations($_REQUEST['action']);
		}
		require_once(dirname(__FILE__).'/list-tables.php');
		$table = new BackWPup_Backups_Table;
		$table->check_permissions();
		$table->prepare_items();
		break;
	case 'runnow':
		break;
	case 'view_log':
		break;
	default:
		if (!empty($_REQUEST['action'])) {
			require_once(dirname(__FILE__).'/options-save.php');
			backwpup_job_operations($_REQUEST['action']);
		}
		require_once(dirname(__FILE__).'/list-tables.php');
		$table = new BackWPup_Jobs_Table;
		$table->check_permissions();
		$table->prepare_items();
		break;
	}
}


function backwpup_option_submenues() {
	$maincurrent="";$logscurrent="";$backupscurrent="";$toolscurrent="";$settingscurrent="";
	if (empty($_REQUEST['subpage']))
		$maincurrent=" class=\"current\"";
	if ($_REQUEST['subpage']=='logs')
		$logscurrent=" class=\"current\"";
	if ($_REQUEST['subpage']=='backups')
		$backupscurrent=" class=\"current\"";
	if ($_REQUEST['subpage']=='tools')
		$toolscurrent=" class=\"current\"";
	if ($_REQUEST['subpage']=='settings')
		$settingscurrent=" class=\"current\"";
	echo "<ul class=\"subsubsub\">";
	echo "<li><a href=\"admin.php?page=BackWPup\"$maincurrent>".__('Jobs','backwpup')."</a> |</li>";
	echo "<li><a href=\"admin.php?page=BackWPup&amp;subpage=logs\"$logscurrent>".__('Logs','backwpup')."</a> |</li>";
	echo "<li><a href=\"admin.php?page=BackWPup&amp;subpage=backups\"$backupscurrent>".__('Backups','backwpup')."</a> |</li>";
	echo "<li><a href=\"admin.php?page=BackWPup&amp;subpage=tools\"$toolscurrent>".__('Tools','backwpup')."</a> |</li>";
	echo "<li><a href=\"admin.php?page=BackWPup&amp;subpage=settings\"$settingscurrent>".__('Settings','backwpup')."</a></li>";
	echo "</ul>";
}

//On Plugin activate
function backwpup_plugin_activate() {
	//remove old cron jobs
	$jobs=(array)get_option('backwpup_jobs');
	foreach ($jobs as $jobid => $jobvalue) {
		if ($time=wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid))) {
			wp_unschedule_event($time,'backwpup_cron',array('jobid'=>$jobid));
		}
	}
	wp_clear_scheduled_hook('backwpup_cron');
	//make schedule
	wp_schedule_event(0, 'backwpup_int', 'backwpup_cron');
	//Set defaults
	$cfg=get_option('backwpup'); //Load Settings
	if (empty($cfg['mailsndemail'])) $cfg['mailsndemail']=sanitize_email(get_bloginfo( 'admin_email' ));
	if (empty($cfg['mailsndname'])) $cfg['mailsndname']='BackWPup '.get_bloginfo( 'name' );
	if (empty($cfg['mailmethod'])) $cfg['mailmethod']='mail';
	if (empty($cfg['mailsendmail'])) $cfg['mailsendmail']=substr(ini_get('sendmail_path'),0,strpos(ini_get('sendmail_path'),' -'));
	if (empty($cfg['maxlogs'])) $cfg['maxlogs']=0;
	if (!function_exists('gzopen') or !isset($cfg['gzlogs'])) $cfg['gzlogs']=false;
	if (empty($cfg['dirtemp'])) {
		$rand = substr( md5( md5( SECURE_AUTH_KEY ) ), -5 );
		$cfg['dirtemp']=backwpup_get_upload_dir();
	}
	if (empty($cfg['dirlogs'])) {
		$rand = substr( md5( md5( SECURE_AUTH_KEY ) ), -5 );
		$cfg['dirlogs']=str_replace('\\','/',trailingslashit(WP_CONTENT_DIR)).'backwpup-'.$rand.'-logs/';
	}
	update_option('backwpup',$cfg);
}

//on Plugin deaktivate
function backwpup_plugin_deactivate() {
	//remove old cron jobs
	$jobs=(array)get_option('backwpup_jobs');
	foreach ($jobs as $jobid => $jobvalue) {
		if ($time=wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid))) {
			wp_unschedule_event($time,'backwpup_cron',array('jobid'=>$jobid));
		}
	}
	wp_clear_scheduled_hook('backwpup_cron');
}

//add edit setting to plugins page
function backwpup_plugin_options_link($links) {
	if (!current_user_can(BACKWPUP_USER_CAPABILITY))
		return $links;
	$settings_link='<a href="admin.php?page=BackWPup" title="' . __('Go to Settings Page','backwpup') . '" class="edit">' . __('Settings') . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}

//add links on plugins page
function backwpup_plugin_links($links, $file) {
	if (!current_user_can('install_plugins'))
		return $links;
	if ($file == BACKWPUP_PLUGIN_BASEDIR.'/backwpup.php') {
		$links[] = '<a href="http://wordpress.org/extend/plugins/backwpup/faq/" target="_blank">' . __('FAQ') . '</a>';
		$links[] = '<a href="http://wordpress.org/tags/backwpup/" target="_blank">' . __('Support') . '</a>';
		$links[] = '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=daniel%40huesken-net%2ede&amp;item_name=Daniel%20Huesken%20Plugin%20Donation&amp;item_number=BackWPup&amp;no_shipping=0&amp;no_note=1&amp;tax=0&amp;currency_code=EUR&amp;lc=DE&amp;bn=PP%2dDonationsBF&amp;charset=UTF%2d8" target="_blank">' . __('Donate') . '</a>';
	}
	return $links;
}

//Add cron interval
function backwpup_intervals($schedules) {
	$intervals['backwpup_int']=array('interval' => '300', 'display' => __('BackWPup', 'backwpup'));
	$schedules=array_merge($intervals,$schedules);
	return $schedules;
}

//cron work
function backwpup_cron() {	
	$jobs=(array)get_option('backwpup_jobs');
	foreach ($jobs as $jobid => $jobvalue) {
		if (!$jobvalue['activated'])
			continue;
		if ($jobvalue['cronnextrun']<=current_time('timestamp')) {
			define('DONOTCACHEPAGE', true);
			define('DONOTCACHEDB', true);
			define('DONOTMINIFY', true);
			define('DONOTCDN', true);
			define('DONOTCACHCEOBJECT', true);
			//Quick Cache
			define("QUICK_CACHE_ALLOWED", false);
			echo "<!--dynamic-cached-content-->";
			echo "<!--mfunc backwpup_dojob(".$jobid.") -->";
			backwpup_dojob($jobid);
			echo "<!--/mfunc-->";
			echo "<!--/dynamic-cached-content-->";
		}
	}
}

//DoJob
function backwpup_dojob($jobid) {
	if (empty($jobid))
		return false;
	require_once(dirname(__FILE__).'/backwpup_dojob.php');
	$backwpup_dojob= new backwpup_dojob($jobid);

	//run job parts
	foreach($backwpup_dojob->todo as $key => $value) {
		switch ($value) {
		case 'DB':
			$backwpup_dojob->dump_db();
			break;
		case 'WPEXP':
			$backwpup_dojob->export_wp();
			break;
		case 'FILE':
			$backwpup_dojob->file_list();
			break;
		}
	}

	if (isset($backwpup_dojob->filelist[0][79001])) { // Make backup file
		if ($backwpup_dojob->backupfileformat==".zip")
			$backwpup_dojob->zip_files();
		elseif ($backwpup_dojob->backupfileformat==".tar.gz" or $backwpup_dojob->backupfileformat==".tar.bz2" or $backwpup_dojob->backupfileformat==".tar")
			$backwpup_dojob->tar_pack_files();
	}

	if (is_file($backwpup_dojob->backupdir.$backwpup_dojob->backupfile)) {  // Put backup file to destination
		$dests=explode(',',strtoupper(BACKWPUP_DESTS));
		if (!empty($backwpup_dojob->job['mailaddress'])) {
			$backwpup_dojob->destination_mail();
		}
		if (in_array('FTP',$dests) and !empty($backwpup_dojob->job['ftphost']) and !empty($backwpup_dojob->job['ftpuser']) and !empty($backwpup_dojob->job['ftppass']))	 {
			if (function_exists('ftp_connect')) 
				$backwpup_dojob->destination_ftp();
			else 
				trigger_error(__('FTP extension needed for FTP!','backwpup'),E_USER_ERROR);
		}
		if (in_array('DROPBOX',$dests) and !empty($backwpup_dojob->job['dropetoken']) and !empty($backwpup_dojob->job['dropesecret'])) {
			if (function_exists('curl_exec') and function_exists('json_decode')) 
				$backwpup_dojob->destination_dropbox();
			else
				trigger_error(__('Curl and Json extensions needed for DropBox!','backwpup'),E_USER_ERROR);
		}
		if (in_array('SUGARSYNC',$dests) and !empty($backwpup_dojob->job['sugaruser']) and !empty($backwpup_dojob->job['sugarpass'])) {
			if (function_exists('curl_exec') )
				$backwpup_dojob->destination_sugarsync();
			else
				trigger_error(__('Curl and Json extensions needed for DropBox!','backwpup'),E_USER_ERROR);
		}
		if (in_array('S3',$dests) and !empty($backwpup_dojob->job['awsAccessKey']) and !empty($backwpup_dojob->job['awsSecretKey']) and !empty($backwpup_dojob->job['awsBucket'])) {
			if (function_exists('curl_exec')) 
				$backwpup_dojob->destination_s3();
			else 
				trigger_error(__('Curl extension needed for Amazon S3!','backwpup'),E_USER_ERROR);
		}
		if (in_array('RSC',$dests) and !empty($backwpup_dojob->job['rscUsername']) and !empty($backwpup_dojob->job['rscAPIKey']) and !empty($backwpup_dojob->job['rscContainer'])) {
			if (function_exists('curl_exec')) 
				$backwpup_dojob->destination_rsc();
			else 
				trigger_error(__('Curl extension needed for RackSpaceCloud!','backwpup'),E_USER_ERROR);
		}
		if (in_array('MSAZURE',$dests) and !empty($backwpup_dojob->job['msazureHost']) and !empty($backwpup_dojob->job['msazureAccName']) and !empty($backwpup_dojob->job['msazureKey']) and !empty($backwpup_dojob->job['msazureContainer'])) {
			if (function_exists('curl_exec')) 
				$backwpup_dojob->destination_msazure();
			else 
				trigger_error(__('Curl extension needed for Microsoft Azure!','backwpup'),E_USER_ERROR);
		}
		if (!empty($backwpup_dojob->job['backupdir'])) {
			$backwpup_dojob->destination_dir();
		}
	}

	foreach($backwpup_dojob->todo as $key => $value) {
		switch ($value) {
		case 'CHECK':
			$backwpup_dojob->check_db();
			break;
		case 'OPTIMIZE':
			$backwpup_dojob->optimize_db();
			break;
		}
	}
		
	$backwpup_dojob->job_end();
	//geneate new chache
	update_option('backwpup_backups_chache',backwpup_get_backup_files());
	return $backwpup_dojob->logdir.$backwpup_dojob->logfile;
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
	$headers=array("backwpup_version" => "version","backwpup_logtime" => "logtime","backwpup_errors" => "errors","backwpup_warnings" => "warnings","backwpup_jobid" => "jobid","backwpup_jobname" => "name","backwpup_jobtype" => "type","backwpup_jobruntime" => "runtime","backwpup_backupfilesize" => "backupfilesize");
	if (!is_readable($logfile))
		return false;
	//Read file
	if (strtolower(substr($logfile,-3))==".gz") {
		$fp = gzopen( $logfile, 'r' );
		$file_data = gzread( $fp, 1536 ); // Pull only the first 1,5kiB of the file in.
		gzclose( $fp );
	} else {
		$fp = fopen( $logfile, 'r' );
		$file_data = fread( $fp, 1536 ); // Pull only the first 1,5kiB of the file in.
		fclose( $fp );
	}

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
	if (!current_user_can(BACKWPUP_USER_CAPABILITY))
		return;
	$cfg=get_option('backwpup');
	echo '<strong>'.__('Logs:','backwpup').'</strong><br />';
	//get log files
	$logfiles=array();
	if ( $dir = @opendir( $cfg['dirlogs'] ) ) {
		while (($file = readdir( $dir ) ) !== false ) {
			if (is_file($cfg['dirlogs'].'/'.$file) and 'backwpup_log_' == substr($file,0,strlen('backwpup_log_')) and  ('.html' == substr($file,-5) or '.html.gz' == substr($file,-8)))
				$logfiles[]=$file;
		}
		closedir( $dir );
		rsort($logfiles);
	}

	if (is_array($logfiles)) {
		$count=0;
		foreach ($logfiles as $logfile) {
			$logdata=backwpup_read_logheader($cfg['dirlogs'].'/'.$logfile);
			echo '<a href="'.wp_nonce_url('admin.php?page=BackWPup&subpage=view_log&logfile='.$cfg['dirlogs'].'/'.$logfile, 'view-log_'.$logfile).'" title="'.__('View Log','backwpup').'">'.date_i18n(get_option('date_format'),$logdata['logtime']).' '.date_i18n(get_option('time_format'),$logdata['logtime']).': <i>';
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
	$jobs=(array)get_option('backwpup_jobs');
	echo '<strong>'.__('Scheduled Jobs:','backwpup').'</strong><br />';
	foreach ($jobs as $jobid => $jobvalue) {
		if ($jobvalue['activated']) {
			echo '<a href="'.wp_nonce_url('admin.php?page=BackWPup&action=edit&jobid='.$jobid, 'edit-job').'" title="'.__('Edit Job','backwpup').'">';
			if ($jobvalue['starttime']>0 and empty($jobvalue['stoptime'])) {
				$runtime=current_time('timestamp')-$jobvalue['starttime'];
				echo __('Running since:','backwpup').' '.$runtime.' '.__('sec.','backwpup');
			} elseif ($jobvalue['activated']) {
				echo date(get_option('date_format'),$jobvalue['cronnextrun']).' '.date(get_option('time_format'),$jobvalue['cronnextrun']);
			}
			echo ': <span>'.$jobvalue['name'].'</span></a><br />';
		}
	}
	if (empty($jobs)) 
		echo '<i>'.__('none','backwpup').'</i><br />';

}

//add dashboard widget
function backwpup_add_dashboard() {
	if (!current_user_can(BACKWPUP_USER_CAPABILITY))
		return;
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

function backwpup_get_upload_dir() {
	global $switched;
	$upload_path = get_option( 'upload_path' );
	$upload_path = trim($upload_path);
	$main_override = defined( 'MULTISITE' ) && is_main_site();
	if ( empty($upload_path) ) {
		$dir = WP_CONTENT_DIR . '/uploads';
	} else {
		$dir = $upload_path;
		if ( 'wp-content/uploads' == $upload_path ) {
			$dir = WP_CONTENT_DIR . '/uploads';
		} elseif ( 0 !== strpos($dir, ABSPATH) ) {
			// $dir is absolute, $upload_path is (maybe) relative to ABSPATH
			$dir = path_join( ABSPATH, $dir );
		}
	}
	if ( defined('UPLOADS') && !$main_override && ( !isset( $switched ) || $switched === false ) ) {
		$dir = ABSPATH . UPLOADS;
	}
	if (function_exists('is_multisite')) {
		if ( is_multisite() && !$main_override && ( !isset( $switched ) || $switched === false ) ) {
			if ( defined( 'BLOGUPLOADDIR' ) )
				$dir = untrailingslashit(BLOGUPLOADDIR);
		}
	}

	return str_replace('\\','/',trailingslashit($dir));
}

function backwpup_get_exclude_wp_dirs($folder) {
	$folder=trailingslashit(str_replace('\\','/',$folder));
	$excludedir=array();
	if (false !== stripos(trailingslashit(str_replace('\\','/',ABSPATH)),$folder) and trailingslashit(str_replace('\\','/',ABSPATH))!=$folder)
		$excludedir[]=trailingslashit(str_replace('\\','/',ABSPATH));
	if (false !== stripos(trailingslashit(str_replace('\\','/',WP_CONTENT_DIR)),$folder) and trailingslashit(str_replace('\\','/',WP_CONTENT_DIR))!=$folder)
		$excludedir[]=trailingslashit(str_replace('\\','/',WP_CONTENT_DIR));
	if (false !== stripos(trailingslashit(str_replace('\\','/',WP_PLUGIN_DIR)),$folder) and trailingslashit(str_replace('\\','/',WP_PLUGIN_DIR))!=$folder)
		$excludedir[]=trailingslashit(str_replace('\\','/',WP_PLUGIN_DIR));
	if (false !== stripos(str_replace('\\','/',trailingslashit(WP_CONTENT_DIR).'themes/'),$folder) and str_replace('\\','/',trailingslashit(WP_CONTENT_DIR).'themes/')!=$folder)
		$excludedir[]=str_replace('\\','/',trailingslashit(WP_CONTENT_DIR).'themes/');
	if (false !== stripos(backwpup_get_upload_dir(),$folder) and backwpup_get_upload_dir()!=$folder)
		$excludedir[]=backwpup_get_upload_dir();
	//Exclude Backup dirs
	$jobs=(array)get_option('backwpup_jobs');
	foreach($jobs as $jobsvale) {
		if (!empty($jobsvale['backupdir']) and $jobsvale['backupdir']!='/')
			$excludedir[]=trailingslashit(str_replace('\\','/',$jobsvale['backupdir']));
	}
	return $excludedir;
}


function backwpup_calc_db_size($jobvalues) {
	global $wpdb;
	$dbsize=array('size'=>0,'num'=>0,'rows'=>0);
	$status=$wpdb->get_results("SHOW TABLE STATUS FROM `".DB_NAME."`;", ARRAY_A);
	foreach($status as $tablekey => $tablevalue) {
		if (!in_array($tablevalue['Name'],$jobvalues['dbexclude'])) {
			$dbsize['size']=$dbsize['size']+$tablevalue["Data_length"]+$tablevalue["Index_length"];
			$dbsize['num']++;
			$dbsize['rows']=$dbsize['rows']+$tablevalue["Rows"];
		}
	}
	return $dbsize;
}


function _backwpup_calc_file_size_file_list_folder( $folder = '', $levels = 100, $excludes=array(),$excludedirs=array()) {
	global $backwpup_temp_files;
	if ( !empty($folder) and $levels and $dir = @opendir( $folder )) {
		while (($file = readdir( $dir ) ) !== false ) {
			if ( in_array($file, array('.', '..','.svn') ) )
				continue;
			foreach ($excludes as $exclusion) { //exclude dirs and files
				if (false !== stripos($folder.$file,$exclusion) and !empty($exclusion) and $exclusion!='/')
					continue 2;
			}
			if ( @is_dir( $folder.$file )) {
				if (!in_array(trailingslashit($folder.$file),$excludedirs))
					_backwpup_calc_file_size_file_list_folder( trailingslashit($folder.$file), $levels - 1, $excludes);
			} elseif ((@is_file( $folder.$file ) or @is_executable($folder.$file)) and @is_readable($folder.$file)) {
				$backwpup_temp_files['num']++;
				$backwpup_temp_files['size']=$backwpup_temp_files['size']+filesize($folder.$file);
			} 
		}
		@closedir( $dir );
	}
	return $files;
}

function backwpup_calc_file_size($jobvalues) {
	global $backwpup_temp_files;
	$backwpup_temp_files=array('size'=>0,'num'=>0);
	//Exclude Temp Files
	$backwpup_exclude=explode(',',trim($jobvalues['fileexclude']));
	$backwpup_exclude=array_unique($backwpup_exclude);

	//File list for blog folders
	if ($jobvalues['backuproot'])
		_backwpup_calc_file_size_file_list_folder(trailingslashit(ABSPATH),100,$backwpup_exclude,array_merge($jobvalues['backuprootexcludedirs'],backwpup_get_exclude_wp_dirs(ABSPATH)));
	if ($jobvalues['backupcontent'])
		_backwpup_calc_file_size_file_list_folder(trailingslashit(WP_CONTENT_DIR),100,$backwpup_exclude,array_merge($jobvalues['backupcontentexcludedirs'],backwpup_get_exclude_wp_dirs(WP_CONTENT_DIR)));
	if ($jobvalues['backupplugins'])
		_backwpup_calc_file_size_file_list_folder(trailingslashit(WP_PLUGIN_DIR),100,$backwpup_exclude,array_merge($jobvalues['backuppluginsexcludedirs'],backwpup_get_exclude_wp_dirs(WP_PLUGIN_DIR)));
	if ($jobvalues['backupthemes'])
		_backwpup_calc_file_size_file_list_folder(trailingslashit(trailingslashit(WP_CONTENT_DIR).'themes'),100,$backwpup_exclude,array_merge($jobvalues['backupthemesexcludedirs'],backwpup_get_exclude_wp_dirs(trailingslashit(WP_CONTENT_DIR).'themes')));
	if ($jobvalues['backupuploads'])
		_backwpup_calc_file_size_file_list_folder(trailingslashit(backwpup_get_upload_dir()),100,$backwpup_exclude,array_merge($jobvalues['backupuploadsexcludedirs'],backwpup_get_exclude_wp_dirs(backwpup_get_upload_dir())));

	//include dirs
	if (!empty($jobvalues['dirinclude'])) {
		$dirinclude=explode(',',$jobvalues['dirinclude']);
		$dirinclude=array_unique($dirinclude);
		//Crate file list for includes
		foreach($dirinclude as $dirincludevalue) {
			if (is_dir($dirincludevalue))
				_backwpup_calc_file_size_file_list_folder(trailingslashit($dirincludevalue),100,$backwpup_exclude);
		}
	}
	
	return $backwpup_temp_files;
	
}

//Calcs next run for a cron string as timestamp
function backwpup_cron_next($cronstring) {
	//Cronstring zerlegen
	list($cronstr['minutes'],$cronstr['hours'],$cronstr['mday'],$cronstr['mon'],$cronstr['wday'])=explode(' ',$cronstring,5);

	//make arrys form string
	foreach ($cronstr as $key => $value) {
		if (strstr($value,','))
			$cronarray[$key]=explode(',',$value);
		else
			$cronarray[$key]=array(0=>$value);
	}
	//make arrys complete with ranges and steps
	foreach ($cronarray as $cronarraykey => $cronarrayvalue) {
		$cron[$cronarraykey]=array();
		foreach ($cronarrayvalue as $key => $value) {
			//steps
			$step=1;
			if (strstr($value,'/'))
				list($value,$step)=explode('/',$value,2);
			//replase weekeday 7 with 0 for sundays
			if ($cronarraykey=='wday')
				$value=str_replace('7','0',$value);
			//ranges
			if (strstr($value,'-')) {
				list($first,$last)=explode('-',$value,2);
				if (!is_numeric($first) or !is_numeric($last) or $last>60 or $first>60) //check
					return 2147483647;
				if ($cronarraykey=='minutes' and $step<5)  //set step ninmum to 5 min.
					$step=5;
				$range=array();
				for ($i=$first;$i<=$last;$i=$i+$step)
					$range[]=$i;
				$cron[$cronarraykey]=array_merge($cron[$cronarraykey],$range);
			} elseif ($value=='*') {
				$range=array();
				if ($cronarraykey=='minutes') {
					if ($step<5) //set step ninmum to 5 min.
						$step=5;
					for ($i=0;$i<=59;$i=$i+$step)
						$range[]=$i;
				}
				if ($cronarraykey=='hours') {
					for ($i=0;$i<=23;$i=$i+$step)
						$range[]=$i;
				}
				if ($cronarraykey=='mday') {
					for ($i=$step;$i<=31;$i=$i+$step)
						$range[]=$i;
				}
				if ($cronarraykey=='mon') {
					for ($i=$step;$i<=12;$i=$i+$step)
						$range[]=$i;
				}
				if ($cronarraykey=='wday') {
					for ($i=0;$i<=6;$i=$i+$step)
						$range[]=$i;
				}
				$cron[$cronarraykey]=array_merge($cron[$cronarraykey],$range);
			} else {
				//Month names
				if (strtolower($value)=='jan')
					$value=1;
				if (strtolower($value)=='feb')
					$value=2;
				if (strtolower($value)=='mar')
					$value=3;
				if (strtolower($value)=='apr')
					$value=4;
				if (strtolower($value)=='may')
					$value=5;
				if (strtolower($value)=='jun')
					$value=6;
				if (strtolower($value)=='jul')
					$value=7;
				if (strtolower($value)=='aug')
					$value=8;
				if (strtolower($value)=='sep')
					$value=9;
				if (strtolower($value)=='oct')
					$value=10;
				if (strtolower($value)=='nov')
					$value=11;
				if (strtolower($value)=='dec')
					$value=12;
				//Week Day names
				if (strtolower($value)=='sun')
					$value=0;
				if (strtolower($value)=='sat')
					$value=6;
				if (strtolower($value)=='mon')
					$value=1;
				if (strtolower($value)=='tue')
					$value=2;
				if (strtolower($value)=='wed')
					$value=3;
				if (strtolower($value)=='thu')
					$value=4;
				if (strtolower($value)=='fri')
					$value=5;
				if (!is_numeric($value) or $value>60) //check
					return 2147483647;
				$cron[$cronarraykey]=array_merge($cron[$cronarraykey],array(0=>$value));
			}
		}
	}
	//generate next 10 years
	for ($i=date('Y');$i<2038;$i++)
		$cron['year'][]=$i;
	
	//calc next timestamp
	$currenttime=current_time('timestamp');
	foreach ($cron['year'] as $year) {
		foreach ($cron['mon'] as $mon) {
			foreach ($cron['mday'] as $mday) {
				foreach ($cron['hours'] as $hours) {
					foreach ($cron['minutes'] as $minutes) {
						$timestamp=mktime($hours,$minutes,0,$mon,$mday,$year);
						if ($timestamp and in_array(date('j',$timestamp),$cron['mday']) and in_array(date('w',$timestamp),$cron['wday']) and $timestamp>$currenttime) {
							return $timestamp;
						}
					}
				}
			}
		}
	}
	return 2147483647;
}

function backwpup_env_checks() {
	global $wp_version,$backwpup_admin_message;
	$message='';
	$checks=true;
	$cfg=get_option('backwpup');
	if (version_compare($wp_version, '2.8', '<')) { // check WP Version
		$message.=__('- WordPress 2.8 or heiger needed!','backwpup') . '<br />';
		$checks=false;
	}
	if (version_compare(phpversion(), '5.2.0', '<')) { // check PHP Version
		$message.=__('- PHP 5.2.0 or higher needed!','backwpup') . '<br />';
		$checks=false;
	}
	if (!is_dir($cfg['dirlogs'])) { // create logs folder if it not exists
		@mkdir($cfg['dirlogs'],0755,true);
	}
	if (!is_dir($cfg['dirlogs'])) { // check logs folder
		$message.=__('- Logs Folder not exists:','backwpup') . ' '.$cfg['dirlogs'].'<br />';
	}
	if (!is_writable($cfg['dirlogs'])) { // check logs folder
		$message.=__('- Logs Folder not writeable:','backwpup') . ' '.$cfg['dirlogs'].'<br />';
	}
	if (!is_dir($cfg['dirtemp'])) { // create Temp folder if it not exists
		@mkdir($cfg['dirtemp'],0755,true);
	}
	if (!is_dir($cfg['dirtemp'])) { // check Temp folder
		$message.=__('- Temp Folder not exists:','backwpup') . ' '.$cfg['dirtemp'].'<br />';
	}
	if (!is_writable($cfg['dirtemp'])) { // check Temp folder
		$message.=__('- Temp Folder not writeable:','backwpup') . ' '.$cfg['dirtemp'].'<br />';
	}
	$jobs=(array)get_option('backwpup_jobs'); 
	foreach ($jobs as $jobid => $jobvalue) { //check for old cheduling
		if (isset($jobvalue['scheduletime']) and empty($jobvalue['cron']))
			$message.=__('- Please Check Scheduling time for Job:','backwpup') . ' '.$jobid.'. '.$jobvalue['name'].'<br />';
	}
	if (wp_next_scheduled('backwpup_cron')!=0 and wp_next_scheduled('backwpup_cron')>(time()+360)) {  //check cron jobs work
		$message.=__("- WP-Cron don't working please check it!","backwpup") .'<br />';
	}
	//put massage if one
	if (!empty($message))
		$backwpup_admin_message = '<div id="message" class="error fade"><strong>BackWPup:</strong><br />'.$message.'</div>';
	return $checks;
}

function backwpup_admin_notice() {
	global $backwpup_admin_message;
	echo $backwpup_admin_message;
}

?>