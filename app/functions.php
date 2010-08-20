<?PHP
// don't load directly
if ( !defined('ABSPATH') )
	die('-1');

	//Thems Option menu entry
	function backwpup_menu_entry() {
		$hook = add_management_page(__('BackWPup','backwpup'), __('BackWPup','backwpup'), '10', 'BackWPup','backwpup_options_page') ;
		add_action('load-'.$hook, 'backwpup_options_load');
	}

	//Options Page
	function backwpup_options_page() {
		global $table,$backwpup_message,$page_hook;
		if (!current_user_can(10))
			wp_die('No rights');
		if(!empty($backwpup_message))
			echo '<div id="message" class="updated fade"><p><strong>'.$backwpup_message.'</strong></p></div>';
		switch($_REQUEST['subpage']) {
		case 'edit':
			require_once(plugin_dir_path(__FILE__).'options-edit-job.php');
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
			require_once(plugin_dir_path(__FILE__).'options-settings.php');
			break;
		case 'tools':
			require_once(plugin_dir_path(__FILE__).'options-tools.php');
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
			echo "<h2>".__('BackWPup', 'backwpup')."&nbsp;<a href=\"".wp_nonce_url('admin.php?page=BackWPup&subpage=edit&jobid=0', 'edit-job')."\" class=\"button add-new-h2\">".esc_html__('Add New')."</a></h2>";
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
		
		if (!current_user_can(10))
			wp_die('No rights');
		//Css for Admin Section
		wp_enqueue_style('BackWpup',plugins_url('css/options.css',__FILE__),'',BACKWPUP_VERSION,'screen');
		wp_enqueue_script('BackWpupOptions',plugins_url('js/options.js',__FILE__),'',BACKWPUP_VERSION,true);
		add_contextual_help($current_screen,
			'<div class="metabox-prefs">'.
			'<a href="http://wordpress.org/tags/backwpup" target="_blank">'.__('Support').'</a>'.
			' | <a href="http://wordpress.org/extend/plugins/backwpup/faq/" target="_blank">' . __('FAQ') . '</a>'.
			' | <a href="http://danielhuesken.de/portfolio/backwpup" target="_blank">' . __('Plugin Homepage', 'backwpup') . '</a>'.
			' | <a href="http://wordpress.org/extend/plugins/backwpup" target="_blank">' . __('Plugin Home on WordPress.org', 'backwpup') . '</a>'.
			' | <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&amp;business=daniel%40huesken-net%2ede&amp;item_name=Daniel%20Huesken%20Plugin%20Donation&amp;item_number=BackWPup&amp;no_shipping=0&amp;no_note=1&amp;tax=0&amp;currency_code=EUR&amp;lc=DE&amp;bn=PP%2dDonationsBF&amp;charset=UTF%2d8" target="_blank">' . __('Donate') . '</a>'.
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
		
		if ($_REQUEST['action2']!='-1' and !empty($_REQUEST['doaction2']))
			$_REQUEST['action']=$_REQUEST['action2'];

		switch($_REQUEST['subpage']) {
		case 'logs':
			if (!empty($_REQUEST['action'])) {
				require_once('options-save.php');
				backwpup_log_operations($_REQUEST['action']);
			}
			$table = new BackWPup_Logs_Table;
			$table->check_permissions();
			$table->prepare_items();
			break;
		case 'edit':
			if (!empty($_POST['submit'])) {
				require_once('options-save.php');
				$backwpup_message=backwpup_save_job();
			}
			break;
		case 'settings':
			if (!empty($_POST['submit'])) {
				require_once('options-save.php');
				$backwpup_message=backwpup_save_settings();
			}
			break;
		case 'tools':
			break;
		case 'backups':
			if (!empty($_REQUEST['action'])) {
				require_once('options-save.php');
				backwpup_backups_operations($_REQUEST['action']);
			}
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
				require_once('options-save.php');
				backwpup_job_operations($_REQUEST['action']);
			}
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

		//upgrade ode schedule
		if (isset($jobsettings['scheduletime']) and isset($jobsettings['scheduleintervaltype']) and isset($jobsettings['scheduleintervalteimes'])) {  //Upgrade to cron string
			if ($jobsettings['scheduleintervaltype']==60) { //Min
				$jobsettings['cron']='*/'.$jobsettings['scheduleintervalteimes'].' * * * *';
			}
			if ($jobsettings['scheduleintervaltype']==3600) { //Houer
				$jobsettings['cron']=date('i',$jobsettings['scheduletime']).' */'.$jobsettings['scheduleintervalteimes'].' * * *';
			}
			if ($jobsettings['scheduleintervaltype']==86400) {  //Days
				$jobsettings['cron']=date('i',$jobsettings['scheduletime']).' '.date_i18n('G',$jobsettings['scheduletime']).' */'.$jobsettings['scheduleintervalteimes'].' * *';
			}
			$jobsettings['cronnextrun']=backwpup_cron_next($jobsettings['cron']);
			unset($jobsettings['scheduletime']);
			unset($jobsettings['scheduleintervaltype']);
			unset($jobsettings['scheduleintervalteimes']);
			unset($jobsettings['scheduleinterval']);
		}

		if (!isset($jobsettings['cron']) or !is_string($jobsettings['cron']))
			$jobsettings['cron']='0 3 * * *';

		if (!isset($jobsettings['cronnextrun']) or !is_numeric($jobsettings['cronnextrun']))
			$jobsettings['cronnextrun']=backwpup_cron_next($jobsettings['cron']);

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
		sort($jobsettings['dbexclude']);

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
			$dirinclude[$key]=str_replace('//','/',str_replace('\\','/',trim($value)));
			if (empty($dirinclude[$key]) or !is_dir($dirinclude[$key]))
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
		if (substr($jobsettings['ftpdir'],0,1)!='/')
			$jobsettings['ftpdir']='/'.$jobsettings['ftpdir'];

		if (!isset($jobsettings['ftpmaxbackups']) or !is_int($jobsettings['ftpmaxbackups']))
			$jobsettings['ftpmaxbackups']=0;

		if (!isset($jobsettings['awsAccessKey']) or !is_string($jobsettings['awsAccessKey']))
			$jobsettings['awsAccessKey']='';

		if (!isset($jobsettings['awsSecretKey']) or !is_string($jobsettings['awsSecretKey']))
			$jobsettings['awsSecretKey']='';

		if (!isset($jobsettings['awsSSL']) or !is_bool($jobsettings['awsSSL']))
			$jobsettings['awsSSL']=true;

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

		if (!is_string($jobsettings['mailaddress']) or false === $pos=strpos($jobsettings['mailaddress'],'@') or false === strpos($jobsettings['mailaddress'],'.',$pos))
			$jobsettings['mailaddress']='';

		return $jobsettings;
	}


	//On Plugin activate
	function backwpup_plugin_activate() {
		//remove old cron jobs
		$jobs=get_option('backwpup_jobs');
		if (is_array($jobs)) {
			foreach ($jobs as $jobid => $jobvalue) {
				if ($time=wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid))) {
					wp_unschedule_event($time,'backwpup_cron',array('jobid'=>$jobid));
				}
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
		$jobs=get_option('backwpup_jobs');
		if (is_array($jobs)) {
			foreach ($jobs as $jobid => $jobvalue) {
				if ($time=wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid))) {
					wp_unschedule_event($time,'backwpup_cron',array('jobid'=>$jobid));
				}
			}
		}
		wp_clear_scheduled_hook('backwpup_cron');
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
		$intervals['backwpup_int']=array('interval' => '300', 'display' => __('BackWPup', 'backwpup'));
		$schedules=array_merge($intervals,$schedules);
		return $schedules;
	}


	//cron work
	function backwpup_cron() {
		$jobs=get_option('backwpup_jobs');
		foreach ($jobs as $jobid => $jobvalue) {
			if (!$jobvalue['activated'])
				continue;
			if ($jobvalue['cronnextrun']<=current_time('timestamp')) {
				backwpup_dojob($jobid);
			}
		}
	}

	//DoJob
	function backwpup_dojob($jobid) {
		global $backwpup_logfile;
		if (empty($jobid))
			return false;
		require_once('backwpup_dojob.php');
		$backwpup_dojob= new backwpup_dojob($jobid);
		unset($backwpup_dojob);
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
		$headers=array("backwpup_version" => "version","backwpup_logtime" => "logtime","backwpup_errors" => "errors","backwpup_warnings" => "warnings","backwpup_jobid" => "jobid","backwpup_jobname" => "name","backwpup_jobtype" => "type","backwpup_jobruntime" => "runtime","backwpup_backupfilesize" => "backupfilesize");
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
				if ($jobvalue['activated']) {
					echo '<a href="'.wp_nonce_url('admin.php?page=BackWPup&action=edit&jobid='.$jobid, 'edit-job').'" title="'.__('Edit Job','backwpup').'">';
					if ($jobvalue['starttime']>0 and empty($jobvalue['stoptime'])) {
						$runtime=current_time('timestamp')-$jobvalue['starttime'];
						echo __('Running since:','backwpup').' '.$runtime.' '.__('sec.','backwpup');
					} elseif (!empty($jobvalue['cronnextrun'])) {
						echo date(get_option('date_format'),$jobvalue['cronnextrun']).' '.date(get_option('time_format'),$jobvalue['cronnextrun']);
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
		$jobs=get_option('backwpup_jobs');
		if (is_array($jobs)) {
			foreach($jobs as $jobsvale) {
				if (!empty($jobsvale['backupdir']) and $jobsvale['backupdir']!='/')
					$excludedir[]=trailingslashit(str_replace('\\','/',$jobsvale['backupdir']));
			}
		}
		return $excludedir;
	}

	//ajax/normal get backup files and infos
	function backwpup_get_backup_files() {
		$jobs=get_option('backwpup_jobs'); //Load jobs
		$filecounter=0;
		$files=array();
		$donefolders=array();
		if (extension_loaded('curl') or @dl(PHP_SHLIB_SUFFIX == 'so' ? 'curl.so' : 'php_curl.dll')) {
			if (!class_exists('S3'))
				require_once(plugin_dir_path(__FILE__).'libs/S3.php');
			if (!class_exists('CF_Authentication'))
				require_once(plugin_dir_path(__FILE__).'libs/rackspace/cloudfiles.php');
		}

		if (!is_array($jobs)) //return is Jobs empty
			return false;

		foreach ($jobs as $jobid => $jobvalue) { //go job by job
			$jobvalue=backwpup_check_job_vars($jobvalue); //Check job values
			$todo=explode('+',$jobvalue['type']); //only for backup jobs
			if (!in_array('FILE',$todo) and !in_array('DB',$todo) and !in_array('WPEXP',$todo))
				continue;

			//Get files/filinfo in backup folder
			if (!empty($jobvalue['backupdir']) and !in_array($jobvalue['backupdir'],$donefolders)) {
				if ( $dir = @opendir( $jobvalue['backupdir'] ) ) {
					while (($file = readdir( $dir ) ) !== false ) {
						if (substr($file,0,1)=='.' or !(strtolower(substr($file,-4))=='.zip' or strtolower(substr($file,-4))=='.tar'  or strtolower(substr($file,-7))=='.tar.gz'  or strtolower(substr($file,-8))=='.tar.bz2'))
							continue;
						if (is_file($jobvalue['backupdir'].$file)) {
							$files[$filecounter]['type']='FOLDER';
							$files[$filecounter]['jobid']=$jobid;
							$files[$filecounter]['file']=$jobvalue['backupdir'].$file;
							$files[$filecounter]['filename']=$file;
							$files[$filecounter]['downloadurl']=wp_nonce_url('admin.php?page=BackWPup&subpage=backups&action=download&file='.$jobvalue['backupdir'].$file, 'download-backup_'.$file);
							$files[$filecounter]['filesize']=filesize($jobvalue['backupdir'].$file);
							$files[$filecounter]['time']=filemtime($jobvalue['backupdir'].$file);
							$filecounter++;
						}
					}
					closedir( $dir );
					$donefolders[]=$jobvalue['backupdir'];
				}
			}
			//Get files/filinfo from S3
			if (class_exists('S3') and !in_array($jobvalue['awsAccessKey'].'|'.$jobvalue['awsBucket'].'|'.$jobvalue['awsdir'],$donefolders)) {
				if (!empty($jobvalue['awsAccessKey']) and !empty($jobvalue['awsSecretKey']) and !empty($jobvalue['awsBucket'])) {
					$s3 = new S3($jobvalue['awsAccessKey'], $jobvalue['awsSecretKey'], $jobvalue['awsSSL']);
					if (($contents = $s3->getBucket($jobvalue['awsBucket'],$jobvalue['awsdir'])) !== false) {
						foreach ($contents as $object) {
							if (strtolower(substr($object['name'],-4))=='.zip' or strtolower(substr($object['name'],-4))=='.tar'  or strtolower(substr($object['name'],-7))=='.tar.gz'  or strtolower(substr($object['name'],-8))=='.tar.bz2') {
								$files[$filecounter]['type']='S3';
								$files[$filecounter]['jobid']=$jobid;
								$files[$filecounter]['file']=$object['name'];
								$files[$filecounter]['filename']=basename($object['name']);
								$files[$filecounter]['downloadurl']=wp_nonce_url('admin.php?page=BackWPup&subpage=backups&action=downloads3&file='.$object['name'].'&jobid='.$jobid, 'downloads3-backup_'.$object['name']);
								$files[$filecounter]['filesize']=$object['size'];
								$files[$filecounter]['time']=$object['time'];
								$filecounter++;
							}
						}
					}
					$donefolders[]=$jobvalue['awsAccessKey'].'|'.$jobvalue['awsBucket'].'|'.$jobvalue['awsdir'];
				}
			}
			//Get files/filinfo from RSC
			if (class_exists('CF_Authentication') and !in_array($jobvalue['rscUsername'].'|'.$jobvalue['rscContainer'].'|'.$jobvalue['rscdir'],$donefolders)) {
				if (!empty($jobvalue['rscUsername']) and !empty($jobvalue['rscAPIKey']) and !empty($jobvalue['rscContainer'])) {
					$auth = new CF_Authentication($jobvalue['rscUsername'], $jobvalue['rscAPIKey']);
					$auth->ssl_use_cabundle();
					if ($auth->authenticate()) {
						$conn = new CF_Connection($auth);
						$conn->ssl_use_cabundle();
						$backwpupcontainer = $conn->get_container($jobvalue['rscContainer']);
						$contents = $backwpupcontainer->get_objects(0,NULL,NULL,$jobvalue['rscdir']);
						foreach ($contents as $object) {
							if (strtolower(substr($object->name,-4))=='.zip' or strtolower(substr($object->name,-4))=='.tar'  or strtolower(substr($object->name,-7))=='.tar.gz'  or strtolower(substr($object->name,-8))=='.tar.bz2') {
								$files[$filecounter]['type']='RSC';
								$files[$filecounter]['jobid']=$jobid;
								$files[$filecounter]['file']=$object->name;
								$files[$filecounter]['filename']=basename($object->name);
								$files[$filecounter]['downloadurl']=wp_nonce_url('admin.php?page=BackWPup&subpage=backups&action=downloadrsc&file='.$object->name.'&jobid='.$jobid, 'downloadrsc-backup_'.$object->name);
								$files[$filecounter]['filesize']=$object->content_length;
								$files[$filecounter]['time']=$object->last_modified;
								$filecounter++;
							}
						}
						$donefolders[]=$jobvalue['rscUsername'].'|'.$jobvalue['rscContainer'].'|'.$jobvalue['rscdir'];
					}
				}
			}
			//Get files/filinfo from FTP
			if (!empty($jobvalue['ftphost']) and !empty($jobvalue['ftpuser']) and !empty($jobvalue['ftppass']) and !in_array($jobvalue['ftphost'].'|'.$jobvalue['ftpuser'].'|'.$jobvalue['ftpdir'],$donefolders)) {
				$ftpport=21;
				$ftphost=$jobvalue['ftphost'];
				if (false !== strpos($jobvalue['ftphost'],':')) //look for port
					list($ftphost,$ftpport)=explode(':',$jobvalue,2);

				$SSL=false;
				if (function_exists('ftp_ssl_connect')) { //make SSL FTP connection
					$ftp_conn_id = ftp_ssl_connect($ftphost,$ftpport,10);
					if ($ftp_conn_id)
						$SSL=true;
				}
				if (!$ftp_conn_id) { //make normal FTP conection if SSL not work
					$ftp_conn_id = ftp_connect($ftphost,$ftpport,10);
				}
				if ($ftp_conn_id) {
					//FTP Login
					$loginok=false;
					if (@ftp_login($ftp_conn_id, $jobvalue['ftpuser'], base64_decode($jobvalue['ftppass']))) {
						$loginok=true;
					} else { //if PHP ftp login don't work use raw login
						if (substr(trim(ftp_raw($ftp_conn_id,'USER '.$jobvalue['ftpuser'])),0,3)<400) {
							if (substr(trim(ftp_raw($ftp_conn_id,'PASS '.base64_decode($jobvalue['ftppass']))),0,3)<400) {
								$loginok=true;
							}
						}
					}
				}
				if ($loginok) {
					ftp_pasv($ftp_conn_id, true);
					if ($ftpfilelist=ftp_nlist($ftp_conn_id, $jobvalue['ftpdir'])) {
						foreach($ftpfilelist as $ftpfiles) {
							if (substr(basename($ftpfiles),0,1)=='.' or !(strtolower(substr($ftpfiles,-4))=='.zip' or strtolower(substr($ftpfiles,-4))=='.tar'  or strtolower(substr($ftpfiles,-7))=='.tar.gz'  or strtolower(substr($ftpfiles,-8))=='.tar.bz2'))
								continue;
							$files[$filecounter]['type']='FTP';
							$files[$filecounter]['jobid']=$jobid;
							$files[$filecounter]['file']=$ftpfiles;
							$files[$filecounter]['filename']=basename($ftpfiles);
							$files[$filecounter]['downloadurl']="ftp://".$jobvalue['ftpuser'].":".base64_decode($jobvalue['ftppass'])."@".$jobvalue['ftphost'].$ftpfiles;
							$files[$filecounter]['filesize']=ftp_size($ftp_conn_id,$ftpfiles);
							if ('backwpup_log_' == substr(basename($ftpfiles),0,strlen('backwpup_log_'))) {
								$filnameparts=explode('_',substr(basename($ftpfiles),0,strpos(basename($ftpfiles),'.')));
								$files[$filecounter]['time']=strtotime($filnameparts[2].' '.str_replace('-',':',$filnameparts[3]));
							}
							$filecounter++;
						}
					}
				}
				$donefolders[]=$jobvalue['ftphost'].'|'.$jobvalue['ftpuser'].'|'.$jobvalue['ftpdir'];
			}
		}
		//Sort list
		$tmp = Array();
		foreach($files as &$ma)
			$tmp[] = &$ma["time"];
		array_multisort($tmp, SORT_DESC, $files);
		return $files;
	}

    //ajax/normal get buckests select box
	function backwpup_get_aws_buckets($args='') {
		if (is_array($args)) {
			extract($args);
			$ajax=false;
		} else {
			$awsAccessKey=$_POST['awsAccessKey'];
			$awsSecretKey=$_POST['awsSecretKey'];
			$awsselected=$_POST['awsselected'];
			$ajax=true;
		}
		if (!class_exists('S3'))
			require_once(plugin_dir_path(__FILE__).'libs/S3.php');
		if (empty($awsAccessKey)) {
			echo '<span id="awsBucket" style="color:red;">'.__('Missing Access Key ID!','backwpup').'</span>';
			if ($ajax)
				die();
			else
				return;
		}
		if (empty($awsSecretKey)) {
			echo '<span id="awsBucket" style="color:red;">'.__('Missing Secret Access Key!','backwpup').'</span>';
			if ($ajax)
				die();
			else
				return;
		}
		$s3 = new S3($awsAccessKey, $awsSecretKey, false);
		$buckets=@$s3->listBuckets();
		if (!is_array($buckets)) {
			echo '<span id="awsBucket" style="color:red;">'.__('No Buckets found! Or wrong Keys!','backwpup').'</span>';
			if ($ajax)
				die();
			else
				return;
		}
		echo '<select name="awsBucket" id="awsBucket">';
		foreach ($buckets as $bucket) {
			echo "<option ".selected(strtolower($awsselected),strtolower($bucket),false).">".$bucket."</option>";
		}
		echo '</select>';
		if ($ajax)
			die();
		else
			return;
	}

	//ajax/normal get Container for RSC select box
	function backwpup_get_rsc_container($args='') {
		if (is_array($args)) {
			extract($args);
			$ajax=false;
		} else {
			$rscUsername=$_POST['rscUsername'];
			$rscAPIKey=$_POST['rscAPIKey'];
			$rscselected=$_POST['rscselected'];
			$ajax=true;
		}
		if (!class_exists('CF_Authentication'))
			require_once(plugin_dir_path(__FILE__).'libs/rackspace/cloudfiles.php');

		if (empty($rscUsername)) {
			echo '<span id="rscContainer" style="color:red;">'.__('Missing Username!','backwpup').'</span>';
			if ($ajax)
				die();
			else
				return;
		}
		if (empty($rscAPIKey)) {
			echo '<span id="rscContainer" style="color:red;">'.__('Missing API Key!','backwpup').'</span>';
			if ($ajax)
				die();
			else
				return;
		}
		$auth = new CF_Authentication($rscUsername, $rscAPIKey);

		try {
			$auth->authenticate();
			$conn = new CF_Connection($auth);
			$containers=$conn->get_containers();
		} catch (Exception $e) {
			echo '<span id="rscContainer" style="color:red;">'.__($e->getMessage(),'backwpup').'</span>';
			if ($ajax)
				die();
			else
				return;
		}

		if (!is_array($containers)) {
			echo '<span id="rscContainer" style="color:red;">'.__('No Containerss found!','backwpup').'</span>';
			if ($ajax)
				die();
			else
				return;
		}
		echo '<select name="rscContainer" id="rscContainer">';
		foreach ($containers as $container) {
			echo "<option ".selected(strtolower($rscselected),strtolower($container->name),false).">".$container->name."</option>";
		}
		echo '</select>';
			if ($ajax)
				die();
			else
				return;
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
		if (version_compare($wp_version, '2.8', '<')) { // check WP Version
			$message.=__('- WordPress 2.8 or heiger needed!','backwpup') . '<br />';
			$checks=false;
		}
		if (version_compare(phpversion(), '5.2.0', '<')) { // check PHP Version
			$message.=__('- PHP 5.2.0 or higher needed!','backwpup') . '<br />';
			$checks=false;
		}
		if (!is_dir($cfg['dirlogs'])) { // check logs folder
			$message.=__('- Logs Folder not exists:','backwpup') . ' '.$cfg['dirlogs'].'<br />';
		}
		if (!is_writable($cfg['dirlogs'])) { // check logs folder
			$message.=__('- Logs Folder not writeable:','backwpup') . ' '.$cfg['dirlogs'].'<br />';
		}
		if (!is_dir($cfg['dirtemp'])) { // check Temp folder
			$message.=__('- Temp Folder not exists:','backwpup') . ' '.$cfg['dirtemp'].'<br />';
		}
		if (!is_writable($cfg['dirtemp'])) { // check Temp folder
			$message.=__('- Temp Folder not writeable:','backwpup') . ' '.$cfg['dirtemp'].'<br />';
		}
		if (!is_writable($cfg['dirtemp'])) { // check Temp folder
			$message.=__('- Temp Folder not writeable:','backwpup') . ' '.$cfg['dirtemp'].'<br />';
		}
		$jobs=get_option('backwpup_jobs'); 
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
	
	// add all action and so on only if plugin loaded.
	function backwpup_init() {
		if (!backwpup_env_checks())
			return;
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
		add_action('backwpup_cron', 'backwpup_cron');
		//test if cron active
		if (!(wp_next_scheduled('backwpup_cron')))
			wp_schedule_event(0, 'backwpup_int', 'backwpup_cron');
		//add Dashboard widget
		if (current_user_can(10))
			add_action('wp_dashboard_setup', 'backwpup_add_dashboard');
		// add ajax function
		add_action('wp_ajax_backwpup_get_aws_buckets', 'backwpup_get_aws_buckets');
		add_action('wp_ajax_backwpup_get_rsc_container', 'backwpup_get_rsc_container');
		//load tables Classes
		require_once('list-tables.php');
	}

?>