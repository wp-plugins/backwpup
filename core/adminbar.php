<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function backwpup_add_adminbar() {
	global $wp_admin_bar,$wpdb;
	if (!backwpup_get_option('cfg','showadminbar') || !current_user_can(BACKWPUP_USER_CAPABILITY) || !is_super_admin() || !is_admin_bar_showing())
		return;
	if (!is_admin()) //load text domain if not in admin area ther it is loaded in main plugin file
		load_plugin_textdomain('backwpup', false, BACKWPUP_PLUGIN_DIR.'/lang');
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
	$abspath='';
	if (WP_PLUGIN_DIR==ABSPATH.'/wp-content/plugins')
		$abspath='ABSPATH='.urlencode(str_replace('\\','/',ABSPATH)).'&';
	$jobs=$wpdb->get_col("SELECT value FROM `".$wpdb->prefix."backwpup` WHERE main LIKE 'job_%' AND name='jobid' ORDER BY value DESC");
	foreach ($jobs as $job) {
		$name=backwpup_get_option('job_'.$job,'name');
		$wp_admin_bar->add_menu(array( 'id' => 'backwpup_jobs_'.$job, 'parent' => 'backwpup_jobs', 'title' => $name, 'href' => wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpupeditjob&jobid='.$job, 'edit-job')));
		$url=backwpup_jobrun_url('runnow',$job);
		$wp_admin_bar->add_menu(array( 'id' => 'backwpup_jobs_runnow_'.$job, 'parent' => 'backwpup_jobs_'.$job, 'title' => __('Run Now','backwpup'), 'href' => $url['url']));
	}
	//get log files
	$logfiles=array();
	if ( is_readable(backwpup_get_option('cfg','logfolder')) and $dir = @opendir( backwpup_get_option('cfg','logfolder') ) ) {
		while (($file = readdir( $dir ) ) !== false ) {
			if (is_file(backwpup_get_option('cfg','logfolder').$file) and 'backwpup_log_' == substr($file,0,strlen('backwpup_log_')) and  ('.html' == substr($file,-5) or '.html.gz' == substr($file,-8)))
				$logfiles[]=$file;
		}
		closedir( $dir );
		rsort($logfiles);
	}
	$logfilenum=count($logfiles);
	if ($logfilenum>0) {
		//show only 5 logs
		$max=5;
		if ($logfilenum<5)
			$max=$logfilenum;
		for ($i=0;$i<$max;$i++) {
			$logdata=backwpup_read_logheader(backwpup_get_option('cfg','logfolder').$logfiles[$i]);
			$title = date_i18n(get_option('date_format').' @ '.get_option('time_format'),$logdata['logtime']).' ';
			$title.= $logdata['name'];
			if ($logdata['errors']>0)
				$title.= sprintf(' <span style="color:red;">('._n("%d ERROR", "%d ERRORS", $logdata['errors'],'backwpup').')</span>', $logdata['errors']);
			if ($logdata['warnings']>0)
				$title.= sprintf(' <span style="color:#e66f00;">('._n("%d WARNING", "%d WARNINGS", $logdata['warnings'],'backwpup').')</span>', $logdata['warnings']);
			if($logdata['errors']==0 and $logdata['warnings']==0)
				$title.= ' <span style="color:green;">('.__('O.K.','backwpup').')</span>';
			$wp_admin_bar->add_menu(array( 'id' => 'backwpup_logs_'.$i ,'parent' => 'backwpup_logs', 'title' => $title, 'href' => wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpupworking&logfile='.backwpup_get_option('cfg','logfolder').$logfiles[$i], 'view-log_'.$logfiles[$i])));
		}
	}
}
wp_enqueue_style("backwpupadmin",BACKWPUP_PLUGIN_BASEURL."/css/adminbar.css","",BACKWPUP_VERSION,"screen");
add_action('admin_bar_menu', 'backwpup_add_adminbar',100);
?>