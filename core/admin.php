<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

class BackWPup_Admin {

	public function __construct() {
		if (is_multisite()) {
			add_action('network_admin_notices', create_function('','global $backwpup_admin_message;if (current_user_can(BACKWPUP_USER_CAPABILITY)) echo $backwpup_admin_message;'));
			add_action('network_admin_menu', array($this,'admin_menu'));
			add_action('wp_network_dashboard_setup', array($this,'dashboard_setup'));
			add_filter('plugin_row_meta', array($this,'plugin_links'),10,2);
		} else {
			add_action('admin_notices', create_function('','global $backwpup_admin_message;if (current_user_can(BACKWPUP_USER_CAPABILITY)) echo $backwpup_admin_message;'));
			add_action('admin_menu', array($this,'admin_menu'));
			add_action('wp_dashboard_setup', array($this,'dashboard_setup'));
			add_filter('plugin_action_links_'.BACKWPUP_PLUGIN_BASENAME, create_function('$links','array_unshift($links,"<a href=\"".backwpup_admin_url("admin.php")."?page=backwpup\" title=\"". __("Go to Settings Page","backwpup") ."\" class=\"edit\">". __("Settings","backwpup") ."</a>");return $links;'));
			add_filter('plugin_row_meta', array($this,'plugin_links'),10,2);
		}
		//make backwpup first plugin
		add_filter('pre_update_option_active_plugins', array($this,'first_plugin'),1,2);
		//Oauth bypass if a other Plugin check only for $_REQUEST['oauth_token'];
		add_action('init',array($this,'oauth_bypass'),1);
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
		global $backwpup_message,$backwpup_listtable;
		if (!current_user_can(BACKWPUP_USER_CAPABILITY))
			return;
		if (!empty($_GET['page']) and in_array($_GET['page'],explode(',',BACKWPUP_MENU_PAGES)) and is_file(dirname(__FILE__).'/../admin/page_'.$_GET['page'].'.php'))
			include_once(dirname(__FILE__).'/../admin/page_'.$_GET['page'].'.php');
	}

	public function admin_page_load() {
		global $backwpup_message,$backwpup_listtable;
		//check user permissions
		if (!current_user_can(BACKWPUP_USER_CAPABILITY))
			return;
		//check called page exists
		if (empty($_GET['page']) or !in_array($_GET['page'],explode(',',BACKWPUP_MENU_PAGES)) or !is_file(dirname(__FILE__).'/../admin/page_'.$_GET['page'].'.php'))
			return;
		if (method_exists(get_current_screen(),'add_help_tab')) {
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
		} elseif (function_exists('add_contextual_help')) { //for WP < 3.3 help
			add_contextual_help( get_current_screen(),
				'<p><a href="http://backwpup.com" target="_blank">BackWPup</a> v. '.BACKWPUP_VERSION.', <a href="http://www.gnu.org/licenses/gpl-2.0.html" target="_blank">GPL2</a> &copy '.date('Y').' <a href="http://danielhuesken.de" target="_blank">Daniel H&uuml;sken</a></p><p>'.__('BackWPup comes with ABSOLUTELY NO WARRANTY. This is free software, and you are welcome to redistribute it under certain conditions.','backwpup').'</p>'.
					'<p><strong>' . __( 'For more information:','backwpup' ) . '</strong></p><p>' .
					' ' . __( '<a href="http://backwpup.com/manual/" target="_blank">Documentation</a>','backwpup' ) . ' |' .
					' ' . __( '<a href="http://backwpup.com/faq/" target="_blank">FAQ</a>','backwpup' ) . ' |' .
					' ' . __( '<a href="http://backwpup.com/forums/" target="_blank">Support Forums</a>','backwpup' ) . ' |' .
					' ' . __( '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=Q3QSVRSFXBLSE" target="_blank">Donate</a>','backwpup' ) . ' |' .
					' ' . __( '<a href="https://flattr.com/thing/345067/BackWPup" target="_blank">Flattr</a>','backwpup' ) . ' ' .
					'</p>'
			);
		}
		//add css for Admin Section
		if (is_file(dirname(__FILE__).'/../css/'.$_GET['page'].'.css')) {
			if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG)
				wp_enqueue_style($_GET['page'],BACKWPUP_PLUGIN_BASEURL.'/css/'.$_GET['page'].'.css','',time(),'screen');
			else
				wp_enqueue_style($_GET['page'],BACKWPUP_PLUGIN_BASEURL.'/css/'.$_GET['page'].'.css','',BACKWPUP_VERSION,'screen');
		}
		//add java for Admin Section
		if (is_file(dirname(__FILE__).'/../js/'.$_GET['page'].'.js')) {
			if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG)
				wp_enqueue_script($_GET['page'],BACKWPUP_PLUGIN_BASEURL.'/js/'.$_GET['page'].'.js','',time(),true);
			else
				wp_enqueue_script($_GET['page'],BACKWPUP_PLUGIN_BASEURL.'/js/'.$_GET['page'].'.js','',BACKWPUP_VERSION,true);
		}
		//include functions
		if (is_file(dirname(__FILE__).'/../admin/func_'.$_GET['page'].'.php'))
			include_once(dirname(__FILE__).'/../admin/func_'.$_GET['page'].'.php');
		//include header
		if (is_file(dirname(__FILE__).'/../admin/header_'.$_GET['page'].'.php'))
			include_once(dirname(__FILE__).'/../admin/header_'.$_GET['page'].'.php');
	}

	public function plugin_links($links, $file) {
		if ($file == BACKWPUP_PLUGIN_BASENAME) {
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
		$widgets = get_option('dashboard_widget_options');
		if (!isset($widgets['backwpup_dashboard_logs']) or $widgets['backwpup_dashboard_logs']<1 or $widgets['backwpup_dashboard_logs']>20)
			$widgets['backwpup_dashboard_logs'] =5;
		//get log files
		$logfiles=array();
		if (is_readable(backwpup_get_option('cfg','logfolder')) and  $dir = @opendir( backwpup_get_option('cfg','logfolder') ) ) {
			while (($file = readdir( $dir ) ) !== false ) {
				if (is_file(backwpup_get_option('cfg','logfolder').$file) and 'backwpup_log_' == substr($file,0,strlen('backwpup_log_')) and  ('.html' == substr($file,-5) or '.html.gz' == substr($file,-8)))
					$logfiles[]=$file;
			}
			closedir( $dir );
			rsort($logfiles);
		}
		echo '<ul>';
		if (count($logfiles)>0) {
			$count=0;
			foreach ($logfiles as $logfile) {
				$logdata=backwpup_read_logheader(backwpup_get_option('cfg','logfolder').$logfile);
				echo '<li>';
				echo '<span>'.date_i18n(get_option('date_format').' @ '.get_option('time_format'),$logdata['logtime']).'</span> ';
				echo '<a href="'.wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpupworking&logfile='.backwpup_get_option('cfg','logfolder').$logfile, 'view-log_'.$logfile).'" title="'.__('View Log:','backwpup').' '.basename($logfile).'">'.$logdata['name'].'</i></a>';
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
		$mainsactive=$wpdb->get_col("SELECT main FROM `".$wpdb->prefix."backwpup` WHERE main LIKE 'job_%' AND name='activetype' AND value!=''");
		if (empty($mainsactive)) {
			echo '<ul><li><i>'.__('none','backwpup').'</i></li></ul>';
			return;
		}
		$backupdata=backwpup_get_option('working','data');
		//get ordering
		$mainscronnextrun=$wpdb->get_col("SELECT main FROM `".$wpdb->prefix."backwpup` WHERE main LIKE 'job_%' AND name='cronnextrun' ORDER BY value ASC");
		echo '<ul>';
		foreach ($mainscronnextrun as $main) {
			if (!in_array($main,$mainsactive))
				continue;
			$name=backwpup_get_option($main,'name');
			$jobid=backwpup_get_option($main,'jobid');
			if (!empty($backupdata) and $backupdata['JOBID']==$jobid) {
				$startime=backwpup_get_option($main,'starttime');
				$runtime=current_time('timestamp')-$startime;
				echo '<li><span style="font-weight:bold;">'.$jobid.'. '.$name.': </span>';
				printf('<span style="color:#e66f00;">'.__('working since %d sec.','backwpup').'</span>',$runtime);
				echo " <a style=\"color:green;\" href=\"" . backwpup_admin_url('admin.php').'?page=backwpupworking' . "\">" . __('View!','backwpup') . "</a>";
				echo " <a style=\"color:red;\" href=\"" . wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpup&action=abort', 'abort-job') . "\">" . __('Abort!','backwpup') . "</a>";
				echo "</li>";
			} else {
				$cronnextrun=backwpup_get_option($main,'cronnextrun');
				echo '<li><span>'.date_i18n(get_option('date_format'),$cronnextrun).' @ '.date_i18n(get_option('time_format'),$cronnextrun).'</span>';
				echo ' <a href="'.wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpupeditjob&jobid='.$jobid, 'edit-job').'" title="'.__('Edit Job','backwpup').'">'.$name.'</a><br />';
				echo "</li>";
			}
		}
		echo '</ul>';
	}

	public function first_plugin($newvalue, $oldvalue) {
		if (!is_array($newvalue))
			return $newvalue;
		for ($i=0; $i<count($newvalue);$i++) {
			if ($newvalue[$i]==BACKWPUP_PLUGIN_BASENAME)
				unset($newvalue[$i]);
		}
		array_unshift($newvalue,BACKWPUP_PLUGIN_BASENAME);
		return $newvalue;
	}

	function oauth_bypass() {
		//bypass Google Analytics by Yoast oauth
		if (isset($_GET['oauth_token']) and $_GET['page']=='backwpupeditjob') {
			$_GET['oauth_token_backwpup']=$_GET['oauth_token'];
			unset($_GET['oauth_token']);
			unset($_REQUEST['oauth_token']);
		}
	}
}
new BackWPup_Admin();
?>