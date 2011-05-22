<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

//Thems Option menu entry
function backwpup_admin_menu() {
	add_menu_page( __('BackWPup','backwpup'), __('BackWPup','backwpup'), BACKWPUP_USER_CAPABILITY, 'backwpup', 'backwpup_menu_page', plugins_url('css/backup-icon20.gif',__FILE__) );
	$hook = add_submenu_page( 'backwpup', __('Jobs','backwpup'), __('Jobs','backwpup'), BACKWPUP_USER_CAPABILITY, 'backwpup', 'backwpup_menu_page' );
	add_action('load-'.$hook, 'backwpup_menu_page_header');
	$hook = add_submenu_page( 'backwpup', __('Add New','backwpup'), __('Add New','backwpup'), BACKWPUP_USER_CAPABILITY, 'backwpupeditjob', 'backwpup_menu_page' );
	add_action('load-'.$hook, 'backwpup_menu_page_header');
	$hook = add_submenu_page( 'backwpup', __('Working','backwpup'), __('Working','backwpup'), BACKWPUP_USER_CAPABILITY, 'backwpupworking', 'backwpup_menu_page' );
	add_action('load-'.$hook, 'backwpup_menu_page_header');
	$hook = add_submenu_page( 'backwpup', __('Logs','backwpup'), __('Logs','backwpup'), BACKWPUP_USER_CAPABILITY, 'backwpuplogs', 'backwpup_menu_page' );
	add_action('load-'.$hook, 'backwpup_menu_page_header');
	$hook = add_submenu_page( 'backwpup', __('Backups','backwpup'), __('Backups','backwpup'), BACKWPUP_USER_CAPABILITY, 'backwpupbackups', 'backwpup_menu_page' );
	add_action('load-'.$hook, 'backwpup_menu_page_header');
	$hook = add_submenu_page( 'backwpup', __('Tools','backwpup'), __('Tools','backwpup'), BACKWPUP_USER_CAPABILITY, 'backwpuptools', 'backwpup_menu_page' );
	add_action('load-'.$hook, 'backwpup_menu_page_header');
	$hook = add_submenu_page( 'backwpup', __('Settings','backwpup'), __('Settings','backwpup'), BACKWPUP_USER_CAPABILITY, 'backwpupsettings', 'backwpup_menu_page' );
	add_action('load-'.$hook, 'backwpup_menu_page_header');
}

function backwpup_menu_page() {
	global $backwpup_message,$backwpup_listtable,$current_screen;
	//check user premessions
	if (!current_user_can(BACKWPUP_USER_CAPABILITY))
		return;
	//Set pages that exists
	$menupages=array('backwpup','backwpupeditjob','backwpupworking','backwpuplogs','backwpupbackups','backwpuptools','backwpupsettings');
	//check called page exists
	if (!empty($_REQUEST['page']) and in_array($_REQUEST['page'],$menupages)) {
		$page=$_REQUEST['page'];
		//check page file exists
		if (is_file(dirname(__FILE__).'/pages/page_'.$page.'.php')) 
			require_once(dirname(__FILE__).'/pages/page_'.$page.'.php');
	}
}

function backwpup_menu_page_header() {
	global $backwpup_message,$backwpup_listtable,$current_screen;
	//check user premessions
	if (!current_user_can(BACKWPUP_USER_CAPABILITY))
		return;
	//Set pages that exists
	$menupages=array('backwpup','backwpupeditjob','backwpupworking','backwpuplogs','backwpupbackups','backwpuptools','backwpupsettings');
	//check called page exists
	if (!empty($_REQUEST['page']) and in_array($_REQUEST['page'],$menupages)) {
		$page=$_REQUEST['page'];
		//check page file exists
		if (is_file(dirname(__FILE__).'/pages/page_'.$page.'.php')) {
			//Css for Admin Section
			if (is_file(dirname(__FILE__).'/css/'.$page.'.css')) {
				if (WP_DEBUG)
					wp_enqueue_style($page,plugins_url('css/'.$page.'.css',__FILE__),'',time(),'screen');
				else
					wp_enqueue_style($page,plugins_url('css/'.$page.'.css',__FILE__),'',BACKWPUP_VERSION,'screen');
			}
			//add java
			if (is_file(dirname(__FILE__).'/js/'.$page.'.js')) {
				if (WP_DEBUG)
					wp_enqueue_script($page,plugins_url('js/'.$page.'.js',__FILE__),'',time(),true);
				else
					wp_enqueue_script($page,plugins_url('js/'.$page.'.js',__FILE__),'',BACKWPUP_VERSION,true);
			}
			//incude functions
			if (is_file(dirname(__FILE__).'/pages/func_'.$page.'.php')) 
				require_once(dirname(__FILE__).'/pages/func_'.$page.'.php');
			//include code
			if (is_file(dirname(__FILE__).'/pages/header_'.$page.'.php')) 
				require_once(dirname(__FILE__).'/pages/header_'.$page.'.php');
		}
	}
}

function backwpup_load_ajax() {
	//Set pages that exists
	$menupages=array('backwpup','backwpupeditjob','backwpupworking','backwpuplogs','backwpupbackups','backwpuptools','backwpupsettings');
	if (!empty($_POST['backwpupajaxpage']) and in_array($_POST['backwpupajaxpage'],$menupages)) {
		$page=$_POST['backwpupajaxpage'];
		//incude functions
		if (is_file(dirname(__FILE__).'/pages/func_'.$page.'.php')) 
			require_once(dirname(__FILE__).'/pages/func_'.$page.'.php');	
	}
}

function backwpup_contextual_help($help='') {
	global $current_screen;
	//help Footer
	$help.=	'<div class="metabox-prefs">'.
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
			'</div>';
	//add help
	add_contextual_help($current_screen,$help);
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
	if (!isset($cfg['maxlogs']) or !is_int($cfg['maxlogs'])) $cfg['maxlogs']=50;
	if (!function_exists('gzopen') or !isset($cfg['gzlogs'])) $cfg['gzlogs']=false;
	if (!isset($cfg['dirlogs']) or !is_dir($cfg['dirlogs'])) {
		$rand = substr( md5( md5( SECURE_AUTH_KEY ) ), -5 );
		$cfg['dirlogs']=str_replace('\\','/',trailingslashit(WP_CONTENT_DIR)).'backwpup-'.$rand.'-logs/';
	}
	//remove old option
	unset($cfg['dirtemp']);
	update_option('backwpup',$cfg);
	//delete not longer used options
	delete_option('backwpup_backups_chache');
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
	$settings_link='<a href="admin.php?page=backwpup" title="' . __('Go to Settings Page','backwpup') . '" class="edit">' . __('Settings') . '</a>';
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
	$intervals['backwpup_int']=array('interval' => '120', 'display' => __('BackWPup', 'backwpup'));
	$schedules=array_merge($intervals,$schedules);
	return $schedules;
}

//cron work
function backwpup_cron() {	
	$jobs=(array)get_option('backwpup_jobs');
	if (is_file(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running')) {
		$runningfile=file_get_contents(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running');
		$infile=unserialize(trim($runningfile));
		if ($infile['timestamp']>time()-310) {
			$ch=curl_init();
			curl_setopt($ch,CURLOPT_URL,$_SESSION['STATIC']['JOBRUNURL']);
			curl_setopt($ch,CURLOPT_RETURNTRANSFER,false);
			curl_setopt($ch,CURLOPT_FORBID_REUSE,true);
			curl_setopt($ch,CURLOPT_FRESH_CONNECT,true);
			curl_setopt($ch,CURLOPT_POST,true);
			curl_setopt($ch,CURLOPT_POSTFIELDS,array('BackWPupSession'=>$infile['SID']));
			curl_setopt($ch,CURLOPT_TIMEOUT,0.01);
			curl_exec($ch);
			curl_close($ch);
		}
	} else {
		foreach ($jobs as $jobid => $jobvalue) {
			if (!$jobvalue['activated'])
				continue;
			if ($jobvalue['cronnextrun']<=current_time('timestamp')) {
				backwpup_jobstart($jobid);
			}
		}
	}
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
	$excludedir[]=rtrim(str_replace('\\','/',sys_get_temp_dir()),'/'); //exclude temp
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
					return false;
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
					return false;
				$cron[$cronarraykey]=array_merge($cron[$cronarraykey],array(0=>$value));
			}
		}
	}

	//calc next timestamp
	$currenttime=current_time('timestamp');
	foreach (array(date('Y'),date('Y')+1) as $year) {
		foreach ($cron['mon'] as $mon) {
			foreach ($cron['mday'] as $mday) {
				foreach ($cron['hours'] as $hours) {
					foreach ($cron['minutes'] as $minutes) {
						$timestamp=mktime($hours,$minutes,0,$mon,$mday,$year);
						if (in_array(date('w',$timestamp),$cron['wday']) and $timestamp>$currenttime) {
								return $timestamp;
						}
					}
				}
			}
		}
	}
	return false;
}

function backwpup_env_checks() {
	global $wp_version,$backwpup_admin_message;
	$message='';
	$checks=true;
	$cfg=get_option('backwpup');
	if (version_compare($wp_version, '3.1', '<')) { // check WP Version
		$message.=__('- WordPress 3.2 or heiger needed!','backwpup') . '<br />';
		$checks=false;
	}
	if (version_compare(phpversion(), '5.2.4', '<')) { // check PHP Version sys_get_temp_dir
		$message.=__('- PHP 5.2.4 or higher needed!','backwpup') . '<br />';
		$checks=false;
	}
	if (!function_exists('curl_init')) { // check curl
		$message.=__('- curl is needed!','backwpup') . '<br />';
		$checks=false;
	}
	if (!function_exists('session_start')) { // check sessions
		$message.=__('- PHP sessions needed!','backwpup') . '<br />';
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


//Checking,upgrade and default job setting
function backwpup_check_job_vars($jobsettings,$jobid) {
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

	//upgrade old schedule
	if (!isset($jobsettings['cron']) and isset($jobsettings['scheduletime']) and isset($jobsettings['scheduleintervaltype']) and isset($jobsettings['scheduleintervalteimes'])) {  //Upgrade to cron string
		if ($jobsettings['scheduleintervaltype']==60) { //Min
			$jobsettings['cron']='*/'.$jobsettings['scheduleintervalteimes'].' * * * *';
		}
		if ($jobsettings['scheduleintervaltype']==3600) { //Houer
			$jobsettings['cron']=(date('i',$jobsettings['scheduletime'])*1).' */'.$jobsettings['scheduleintervalteimes'].' * * *';
		}
		if ($jobsettings['scheduleintervaltype']==86400) {  //Days
			$jobsettings['cron']=(date('i',$jobsettings['scheduletime'])*1).' '.date('G',$jobsettings['scheduletime']).' */'.$jobsettings['scheduleintervalteimes'].' * *';
		}
	}

	if (!isset($jobsettings['cron']) or !is_string($jobsettings['cron']))
		$jobsettings['cron']='0 3 * * *';
		
	if (!isset($jobsettings['cronnextrun']) or !is_numeric($jobsettings['cronnextrun']))
		$jobsettings['cronnextrun']=backwpup_cron_next($jobsettings['cron']);;
		
	if (!isset($jobsettings['mailaddresslog']) or!is_string($jobsettings['mailaddresslog']) or false === $pos=strpos($jobsettings['mailaddresslog'],'@') or false === strpos($jobsettings['mailaddresslog'],'.',$pos))
		$jobsettings['mailaddresslog']=get_option('admin_email');

	if (!isset($jobsettings['mailerroronly']) or !is_bool($jobsettings['mailerroronly']))
		$jobsettings['mailerroronly']=true;
	
	//old tables for backup (exclude)
	if (isset($jobsettings['dbexclude'])) {
		if (is_array($jobsettings['dbexclude'])) {
			$jobsettings['dbtables']=array();
			$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');
			foreach ($tables as $table) {
				if (!in_array($table,$jobsettings['dbexclude']))
					$jobsettings['dbtables'][]=$table;
			}
		}
		unset($jobsettings['dbexclude']);
	}
	
	//Tables to backup
	if (!isset($jobsettings['dbtables']) or !is_array($jobsettings['dbtables'])) {
		$jobsettings['dbtables']=array();
		$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');
		foreach ($tables as $table) {
			if (substr($table,0,strlen($wpdb->prefix))==$wpdb->prefix)
				$jobsettings['dbtables'][]=$table;
		}
	}
	sort($jobsettings['dbtables']);
	
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
	sort($fileexclude);
	$jobsettings['fileexclude']=implode(',',$fileexclude);

	if (!isset($jobsettings['dirinclude']) or !is_string($jobsettings['dirinclude']))
		$jobsettings['dirinclude']='';
	$dirinclude=explode(',',$jobsettings['dirinclude']);
	foreach($dirinclude as $key => $value) {
		$dirinclude[$key]=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($value))));
		if ($dirinclude[$key]=='/' or empty($dirinclude[$key]) or !is_dir($dirinclude[$key]))
			unset($dirinclude[$key]);
	}
	sort($dirinclude);
	$jobsettings['dirinclude']=implode(',',$dirinclude);

	if (!isset($jobsettings['backuproot']) or !is_bool($jobsettings['backuproot']))
		$jobsettings['backuproot']=true;

	if (!isset($jobsettings['backupcontent']) or !is_bool($jobsettings['backupcontent']))
		$jobsettings['backupcontent']=true;

	if (!isset($jobsettings['backupplugins']) or !is_bool($jobsettings['backupplugins']))
		$jobsettings['backupplugins']=true;

	if (!isset($jobsettings['backupthemes']) or !is_bool($jobsettings['backupthemes']))
		$jobsettings['backupthemes']=true;

	if (!isset($jobsettings['backupuploads']) or !is_bool($jobsettings['backupuploads']))
		$jobsettings['backupuploads']=true;

	if (!isset($jobsettings['backuprootexcludedirs']) or !is_array($jobsettings['backuprootexcludedirs']))
		$jobsettings['backuprootexcludedirs']=array();
	foreach($jobsettings['backuprootexcludedirs'] as $key => $value) {
		$jobsettings['backuprootexcludedirs'][$key]=str_replace('//','/',str_replace('\\','/',trim($value)));
		if (empty($jobsettings['backuprootexcludedirs'][$key]) or $jobsettings['backuprootexcludedirs'][$key]=='/' or !is_dir($jobsettings['backuprootexcludedirs'][$key]))
			unset($jobsettings['backuprootexcludedirs'][$key]);
	}
	sort($jobsettings['backuprootexcludedirs']);

	if (!isset($jobsettings['backupcontentexcludedirs']) or !is_array($jobsettings['backupcontentexcludedirs']))
		$jobsettings['backupcontentexcludedirs']=array();
	foreach($jobsettings['backupcontentexcludedirs'] as $key => $value) {
		$jobsettings['backupcontentexcludedirs'][$key]=str_replace('//','/',str_replace('\\','/',trim($value)));
		if (empty($jobsettings['backupcontentexcludedirs'][$key]) or $jobsettings['backupcontentexcludedirs'][$key]=='/' or !is_dir($jobsettings['backupcontentexcludedirs'][$key]))
			unset($jobsettings['backupcontentexcludedirs'][$key]);
	}
	sort($jobsettings['backupcontentexcludedirs']);

	if (!isset($jobsettings['backuppluginsexcludedirs']) or !is_array($jobsettings['backuppluginsexcludedirs']))
		$jobsettings['backuppluginsexcludedirs']=array();
	foreach($jobsettings['backuppluginsexcludedirs'] as $key => $value) {
		$jobsettings['backuppluginsexcludedirs'][$key]=str_replace('//','/',str_replace('\\','/',trim($value)));
		if (empty($jobsettings['backuppluginsexcludedirs'][$key]) or $jobsettings['backuppluginsexcludedirs'][$key]=='/' or !is_dir($jobsettings['backuppluginsexcludedirs'][$key]))
			unset($jobsettings['backuppluginsexcludedirs'][$key]);
	}
	sort($jobsettings['backuppluginsexcludedirs']);

	if (!isset($jobsettings['backupthemesexcludedirs']) or !is_array($jobsettings['backupthemesexcludedirs']))
		$jobsettings['backupthemesexcludedirs']=array();
	foreach($jobsettings['backupthemesexcludedirs'] as $key => $value) {
		$jobsettings['backupthemesexcludedirs'][$key]=str_replace('//','/',str_replace('\\','/',trim($value)));
		if (empty($jobsettings['backupthemesexcludedirs'][$key]) or $jobsettings['backupthemesexcludedirs'][$key]=='/' or !is_dir($jobsettings['backupthemesexcludedirs'][$key]))
			unset($jobsettings['backupthemesexcludedirs'][$key]);
	}
	sort($jobsettings['backupthemesexcludedirs']);

	if (!isset($jobsettings['backupuploadsexcludedirs']) or !is_array($jobsettings['backupuploadsexcludedirs']))
		$jobsettings['backupuploadsexcludedirs']=array();
	foreach($jobsettings['backupuploadsexcludedirs'] as $key => $value) {
		$jobsettings['backupuploadsexcludedirs'][$key]=str_replace('//','/',str_replace('\\','/',trim($value)));
		if (empty($jobsettings['backupuploadsexcludedirs'][$key]) or $jobsettings['backupuploadsexcludedirs'][$key]=='/' or !is_dir($jobsettings['backupuploadsexcludedirs'][$key]))
			unset($jobsettings['backupuploadsexcludedirs'][$key]);
	}
	sort($jobsettings['backupuploadsexcludedirs']);

	$fileformarts=array('.zip','.tar.gz','.tar.bz2','.tar');
	if (!isset($jobsettings['fileformart']) or !in_array($jobsettings['fileformart'],$fileformarts))
		$jobsettings['fileformart']='.zip';
	
	if (!isset($jobsettings['fileprefix']) or !is_string($jobsettings['fileprefix']))
		$jobsettings['fileprefix']='backwpup_'.$jobid.'_';
	
	if (!isset($jobsettings['mailefilesize']) or !is_float($jobsettings['mailefilesize']))
		$jobsettings['mailefilesize']=0;

	if (!isset($jobsettings['backupdir']) or !is_dir($jobsettings['backupdir']))
		$jobsettings['backupdir']='';
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
	if (substr($jobsettings['ftpdir'],0,1)!='/')
		$jobsettings['ftpdir']='/'.$jobsettings['ftpdir'];

	if (!isset($jobsettings['ftpmaxbackups']) or !is_int($jobsettings['ftpmaxbackups']))
		$jobsettings['ftpmaxbackups']=0;
	
	if (!isset($jobsettings['ftppasv']) or !is_bool($jobsettings['ftppasv']))
		$jobsettings['ftppasv']=true;
	
	if (!isset($jobsettings['ftpssl']) or !is_bool($jobsettings['ftpssl']))
		$jobsettings['ftpssl']=false;

	if (!isset($jobsettings['awsAccessKey']) or !is_string($jobsettings['awsAccessKey']))
		$jobsettings['awsAccessKey']='';

	if (!isset($jobsettings['awsSecretKey']) or !is_string($jobsettings['awsSecretKey']))
		$jobsettings['awsSecretKey']='';

	if (!isset($jobsettings['awsrrs']) or !is_bool($jobsettings['awsrrs']))
		$jobsettings['awsrrs']=false;

	if (!isset($jobsettings['awsBucket']) or !is_string($jobsettings['awsBucket']))
		$jobsettings['awsBucket']='';

	if (!isset($jobsettings['awsdir']) or !is_string($jobsettings['awsdir']) or $jobsettings['awsdir']=='/')
		$jobsettings['awsdir']='';
	$jobsettings['awsdir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($jobsettings['awsdir']))));
	if (substr($jobsettings['awsdir'],0,1)=='/')
		$jobsettings['awsdir']=substr($jobsettings['awsdir'],1);

	if (!isset($jobsettings['awsmaxbackups']) or !is_int($jobsettings['awsmaxbackups']))
		$jobsettings['awsmaxbackups']=0;
		
	if (!isset($jobsettings['msazureHost']) or !is_string($jobsettings['msazureHost']))
		$jobsettings['msazureHost']='blob.core.windows.net';

	if (!isset($jobsettings['msazureAccName']) or !is_string($jobsettings['msazureAccName']))
		$jobsettings['msazureAccName']='';

	if (!isset($jobsettings['msazureKey']) or !is_string($jobsettings['msazureKey']))
		$jobsettings['msazureKey']='';

	if (!isset($jobsettings['msazureContainer']) or !is_string($jobsettings['msazureContainer']))
		$jobsettings['msazureContainer']='';

	if (!isset($jobsettings['msazuredir']) or !is_string($jobsettings['msazuredir']) or $jobsettings['msazuredir']=='/')
		$jobsettings['msazuredir']='';
	$jobsettings['msazuredir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($jobsettings['msazuredir']))));
	if (substr($jobsettings['msazuredir'],0,1)=='/')
		$jobsettings['msazuredir']=substr($jobsettings['msazuredir'],1);

	if (!isset($jobsettings['msazuremaxbackups']) or !is_int($jobsettings['msazuremaxbackups']))
		$jobsettings['msazuremaxbackups']=0;	
		
	if (!isset($jobsettings['rscUsername']) or !is_string($jobsettings['rscUsername']))
		$jobsettings['rscUsername']='';

	if (!isset($jobsettings['rscAPIKey']) or !is_string($jobsettings['rscAPIKey']))
		$jobsettings['rscAPIKey']='';

	if (!isset($jobsettings['rscContainer']) or !is_string($jobsettings['rscContainer']))
		$jobsettings['rscContainer']='';

	if (!isset($jobsettings['rscdir']) or !is_string($jobsettings['rscdir']) or $jobsettings['rscdir']=='/')
		$jobsettings['rscdir']='';
	$jobsettings['rscdir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($jobsettings['rscdir']))));
	if (substr($jobsettings['rscdir'],0,1)=='/')
		$jobsettings['rscdir']=substr($jobsettings['rscdir'],1);

	if (!isset($jobsettings['rscmaxbackups']) or !is_int($jobsettings['rscmaxbackups']))
		$jobsettings['rscmaxbackups']=0;
		
	if (isset($jobsettings['dropemail']))
		unset($jobsettings['dropemail']);
		
	if (isset($jobsettings['dropepass']))
		unset($jobsettings['dropepass']);
		
	if (!isset($jobsettings['dropetoken']) or !is_string($jobsettings['dropetoken']))
		$jobsettings['dropetoken']='';
	
	if (!isset($jobsettings['dropesecret']) or !is_string($jobsettings['dropesecret']))
		$jobsettings['dropesecret']='';

	if (!isset($jobsettings['dropedir']) or !is_string($jobsettings['dropedir']) or $jobsettings['dropedir']=='/')
		$jobsettings['dropedir']='';
	$jobsettings['dropedir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($jobsettings['dropedir']))));
	if (substr($jobsettings['dropedir'],0,1)=='/')
		$jobsettings['dropedir']=substr($jobsettings['dropedir'],1);
	
	if (!isset($jobsettings['dropemaxbackups']) or !is_int($jobsettings['dropemaxbackups']))
		$jobsettings['dropemaxbackups']=0;	
		
	if (!isset($jobsettings['sugaruser']) or !is_string($jobsettings['sugaruser']))
		$jobsettings['sugaruser']='';

	if (!isset($jobsettings['sugarpass']) or !is_string($jobsettings['sugarpass']))
		$jobsettings['sugarpass']='';		

	if (!isset($jobsettings['sugarroot']) or !is_string($jobsettings['sugarroot']))
		$jobsettings['sugarroot']='';
		
	if (!isset($jobsettings['sugardir']) or !is_string($jobsettings['sugardir']) or $jobsettings['sugardir']=='/')
		$jobsettings['sugardir']='';
	$jobsettings['sugardir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($jobsettings['sugardir']))));
	if (substr($jobsettings['sugardir'],0,1)=='/')
		$jobsettings['sugardir']=substr($jobsettings['sugardir'],1);
	
	if (!isset($jobsettings['sugarmaxbackups']) or !is_int($jobsettings['sugarmaxbackups']))
		$jobsettings['sugarmaxbackups']=0;			
			
	if (!isset($jobsettings['mailaddress']) or !is_string($jobsettings['mailaddress']) or false === $pos=strpos($jobsettings['mailaddress'],'@') or false === strpos($jobsettings['mailaddress'],'.',$pos))
		$jobsettings['mailaddress']='';

	return $jobsettings;
}	
?>