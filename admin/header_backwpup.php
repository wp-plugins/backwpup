<?PHP
if (!defined('ABSPATH'))
	die();
global $wpdb;

//Create Table
$backwpup_listtable = new BackWPup_Jobs_Table;

//get cuurent action
$doaction = $backwpup_listtable->current_action();

if (!empty($doaction)) {
	switch($doaction) {
	case 'delete': //Delete Job
		if (is_array($_GET['jobs'])) {
			check_admin_referer('bulk-jobs');
			foreach ($_GET['jobs'] as $jobid)
				$wpdb->query($wpdb->prepare("DELETE FROM ".$wpdb->prefix."backwpup WHERE main=%s",'job_'.(int)$jobid));
		}
		break;
	case 'copy': //Copy Job
		$oldmain ='job_'. (int) $_GET['jobid'];
		check_admin_referer('copy-job_'. $_GET['jobid']);
		//create new
		$newjobid=$wpdb->get_var("SELECT value FROM `".$wpdb->prefix."backwpup` WHERE main LIKE 'job_%' AND name='jobid' ORDER BY value DESC LIMIT 1",0,0);
		$newjobid++;
		$newmain='job_'.$newjobid;
		$old_options=$wpdb->get_results("SELECT name,value FROM `".$wpdb->prefix."backwpup` WHERE main='".$oldmain."' ORDER BY name ASC");
		foreach ($old_options as $option) {
			$option->value=maybe_unserialize($option->value);
			if ($option->name=="jobid")
				$option->value=$newjobid;
			if ($option->name=="name")
				$option->value=__('Copy of','backwpup').' '.$option->value;
			if ($option->name=="activetype")
				$option->value='';
			if ($option->name=="fileprefix")
				$option->value=str_replace($_GET['jobid'],$newjobid,$option->value);
			if ($option->name=="logfile" or $option->name=="starttime" or
				$option->name=="lastbackupdownloadurl" or $option->name=="lastruntime" or
				$option->name=="lastrun" or $option->name=="cronnextrun")
				continue;
			backwpup_update_option($newmain,$option->name,$option->value);
		}
		break;
	case 'export': //Copy Job
		if (is_array($_GET['jobs'])) {
			check_admin_referer('bulk-jobs');
			foreach ($_GET['jobs'] as $jobid) {
				$options=$wpdb->get_results("SELECT name,value FROM `".$wpdb->prefix."backwpup` WHERE main='job_".$jobid."' ORDER BY name ASC");
				foreach ($options as $option) {
					if ($option->name=="activetype")
						$option->value='';
					if ($option->name=="fileprefix")
						$option->value=str_replace($_GET['jobid'],$newjobid,$option->value);
					if ($option->name=="logfile" or $option->name=="starttime" or
						$option->name=="lastbackupdownloadurl" or $option->name=="lastruntime" or
						$option->name=="lastrun" or $option->name=="cronnextrun")
						continue;
					$jobsexport[$jobid][$option->name]=maybe_unserialize($option->value);
				}
			}
		}
		$export=maybe_serialize($jobsexport);
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Content-Type: text/plain");
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");
		header("Content-Disposition: attachment; filename=".sanitize_key(get_bloginfo('name'))."_BackWPupExport.txt;");
		header("Content-Transfer-Encoding: 8bit");
		header("Content-Length: ".strlen($export));
		echo $export;
		die();
		break;
	case 'abort': //Abort Job
		check_admin_referer('abort-job');
		$backupdata=backwpup_get_option('working','data');
		if (!$backupdata)
			break;
		backwpup_delete_option('working','data'); //delete working data
		if (!empty($backupdata['LOGFILE'])) {
			file_put_contents($backupdata['LOGFILE'], "<span class=\"timestamp\">".date_i18n('Y/m/d H:i.s').":</span> <span class=\"error\">[ERROR]".__('Aborted by user!!!','backwpup')."</span><br />\n", FILE_APPEND);
			//write new log header
			$backupdata['ERROR']++;
			$fd=fopen($backupdata['LOGFILE'],'r+');
			while (!feof($fd)) {
				$line=fgets($fd);
				if (stripos($line,"<meta name=\"backwpup_errors\"") !== false) {
					fseek($fd,$filepos);
					fwrite($fd,str_pad("<meta name=\"backwpup_errors\" content=\"".$backupdata['ERROR']."\" />",100)."\n");
					break;
				}
				$filepos=ftell($fd);
			}
			fclose($fd);
		}
		$backwpup_message=__('Job will be terminated.','backwpup').'<br />';
		if (!empty($backupdata['PID']) and function_exists('posix_kill')) {
			if (posix_kill($backupdata['PID'],9))
				$backwpup_message.=__('Process killed with PID:','backwpup').' '.$backupdata['PID'];
			else
				$backwpup_message.=__('Can\'t kill process with PID:','backwpup').' '.$runningfile['PID'];
		}
		//update job settings
		$sarttime=backwpup_get_option($backupdata['JOBMAIN'],'starttime');
		if (!empty($backupdata['JOBMAIN']) and $sarttime) {
			backwpup_update_option($backupdata['JOBMAIN'],'starttime','');
			backwpup_update_option($backupdata['JOBMAIN'],'lastrun',$sarttime);
			backwpup_update_option($backupdata['JOBMAIN'],'lastruntime',(current_time('timestamp')-$sarttime));
		}
		//clean up temp
		if (!empty($backupdata['BACKUPFILE']) and file_exists(backwpup_get_option('CFG','tempfolder').$backupdata['BACKUPFILE']))
			unlink(backwpup_get_option('CFG','tempfolder').$backupdata['BACKUPFILE']);
		if (!empty($backupdata['DBDUMPFILE']) and file_exists(backwpup_get_option('CFG','tempfolder').$backupdata['DBDUMPFILE']))
			unlink(backwpup_get_option('CFG','tempfolder').$backupdata['DBDUMPFILE']);
		if (!empty($backupdata['WPEXPORTFILE']) and file_exists(backwpup_get_option('CFG','tempfolder').$backupdata['WPEXPORTFILE']))
			unlink(backwpup_get_option('CFG','tempfolder').$backupdata['WPEXPORTFILE']);
		break;
	}
}

//add Help
if (method_exists(get_current_screen(),'add_help_tab')) {
	get_current_screen()->add_help_tab( array(
		'id'      => 'overview',
		'title'   => __('Overview'),
		'content'	=>
		'<p>' . __('Here can see some information about the jobs. How many can be switched with the view button. Also you can manage the jobs and abort working. With the links you have direct access to the last log or download.','backwpup') . '</p>'
	) );
}

$backwpup_listtable->prepare_items();

//ENV Checks
global $wp_version,$backwpup_admin_message;
$backwpup_admin_message='';
if (version_compare($wp_version, BACKWPUP_MIN_WORDPRESS_VERSION, '<')) { // check WP Version
	$backwpup_admin_message.=str_replace('%d',BACKWPUP_MIN_WORDPRESS_VERSION,__('- WordPress %d or higher is needed!','backwpup')) . '<br />';
	$checks=false;
}
if (version_compare(phpversion(), '5.2.4', '<')) { // check PHP Version
	$backwpup_admin_message.=__('- PHP 5.2.4 or higher is needed!','backwpup') . '<br />';
	$checks=false;
}
// check logs folder
if (!backwpup_check_open_basedir(backwpup_get_option('cfg','logfolder'))) //check open basedir
	$backwpup_admin_message.=sprintf(__("- Log folder '%s' is not in open_basedir path!",'backwpup'),backwpup_get_option('cfg','logfolder')).'<br />';
if (backwpup_get_option('cfg','logfolder') and !is_dir(backwpup_get_option('cfg','logfolder')))  // create logs folder if it not exists
	@mkdir(untrailingslashit(backwpup_get_option('cfg','logfolder')),FS_CHMOD_DIR,true);
if (!is_dir(backwpup_get_option('cfg','logfolder')))  // check logs folder
	$backwpup_admin_message.=sprintf(__("- Log folder '%s' not exists!",'backwpup'),backwpup_get_option('cfg','logfolder')).'<br />';
if (!is_writable(backwpup_get_option('cfg','logfolder'))) { // check logs folder
	$backwpup_admin_message.=sprintf(__("- Log folder '%s' is not writable!",'backwpup'),backwpup_get_option('cfg','logfolder')).'<br />';
} else {
	//create .htaccess for apache and index.html for other
	if (strtolower(substr($_SERVER["SERVER_SOFTWARE"],0,6))=="apache") {  //check if it a apache webserver
		if (!is_file(backwpup_get_option('cfg','logfolder').'.htaccess'))
			file_put_contents(backwpup_get_option('cfg','logfolder').'.htaccess',"Order allow,deny\ndeny from all");
	} else {
		if (!is_file(backwpup_get_option('cfg','logfolder').'index.html'))
			file_put_contents(backwpup_get_option('cfg','logfolder').'index.html',"\n");
		if (!is_file(backwpup_get_option('cfg','logfolder').'index.php'))
			file_put_contents(backwpup_get_option('cfg','logfolder').'index.php',"\n");
	}
}

// check temp folder
if (!backwpup_check_open_basedir(backwpup_get_option('cfg','tempfolder'))) //check open basedir
	$backwpup_admin_message.=sprintf(__("- Temp folder '%s' is not in open_basedir path!",'backwpup'),backwpup_get_option('cfg','tempfolder')).'<br />';
if (backwpup_get_option('cfg','tempfolder') and !is_dir(backwpup_get_option('cfg','tempfolder')))  // create temp folder if it not exists
	@mkdir(untrailingslashit(backwpup_get_option('cfg','tempfolder')),FS_CHMOD_DIR,true);
if (!is_dir(backwpup_get_option('cfg','tempfolder')))
	$backwpup_admin_message.=sprintf(__("- Temp folder '%s' not exists!",'backwpup'),backwpup_get_option('cfg','tempfolder')).'<br />';
if (!is_writable(backwpup_get_option('cfg','tempfolder'))) {
	$backwpup_admin_message.=sprintf(__("- Temp folder '%s' is not writable!",'backwpup'),backwpup_get_option('cfg','tempfolder')).'<br />';
} else {
	//create .htaccess for apache and index.html for other
	if (strtolower(substr($_SERVER["SERVER_SOFTWARE"],0,6))=="apache") {  //check if it a apache webserver
		if (!is_file(backwpup_get_option('cfg','tempfolder').'.htaccess'))
			file_put_contents(backwpup_get_option('cfg','tempfolder').'.htaccess',"Order allow,deny\ndeny from all");
	} else {
		if (!is_file(backwpup_get_option('cfg','tempfolder').'index.html'))
			file_put_contents(backwpup_get_option('cfg','tempfolder').'index.html',"\n");
		if (!is_file(backwpup_get_option('cfg','tempfolder').'index.php'))
			file_put_contents(backwpup_get_option('cfg','tempfolder').'index.php',"\n");
	}
}

if (strtolower(substr(WP_CONTENT_URL,0,7))!='http://' and strtolower(substr(WP_CONTENT_URL,0,8))!='https://') {
	$backwpup_admin_message.=sprintf(__("- WP_CONTENT_URL '%s' must set as a full URL!",'backwpup'),WP_CONTENT_URL).'<br />';
}
if (strtolower(substr(WP_PLUGIN_URL,0,7))!='http://' and strtolower(substr(WP_PLUGIN_URL,0,8))!='https://') {
	$backwpup_admin_message.=sprintf(__("- WP_PLUGIN_URL '%s' must set as a full URL!",'backwpup'),WP_PLUGIN_URL).'<br />';
}
//set checks ok or not
if (!empty($backwpup_admin_message)) 
	define('BACKWPUP_ENV_CHECK_OK',false);
else
	define('BACKWPUP_ENV_CHECK_OK',true);
//not relevant checks for job start
if (false !== $nextrun=wp_next_scheduled('backwpup_cron')) {
	if (empty($nextrun) or $nextrun<(time()-(3600*24))) {  //check cron jobs work
		$backwpup_admin_message.=__("- WP-Cron isn't working, please check it!","backwpup") .'<br />';
	}
}
if (file_exists(ABSPATH.'backwpup_db_restore.php') or file_exists(ABSPATH.'backwpup_db_restore.zip') or file_exists(ABSPATH.'.backwpup_restore')) {  //for restore file
	$backwpup_admin_message.=__("- BackWPup DB restore script found in Blog root please delete it, for security!","backwpup") .'<br />';
}
//look for sql dumps in blog root
if ( $dir = opendir(ABSPATH)) {
	$sqlfiles=array();
	while (($file = readdir( $dir ) ) !== false ) {
		if (strtolower(substr($file,-4))==".sql" or strtolower(substr($file,-7))==".sql.gz" or strtolower(substr($file,-7))==".sql.bz2")
			$sqlfiles[]=$file;
	}
	closedir( $dir );
}
if (!empty($sqlfiles)) {  //for restore file
	$backwpup_admin_message.=sprintf(__("- SQL dumps '%s' found in Blog root please delete it, for security!","backwpup"), implode(', ',$sqlfiles) ).'<br />';
}
//put massage if one
if (!empty($backwpup_admin_message))
	$backwpup_admin_message = '<div id="message" class="error fade"><strong>BackWPup:</strong><br />'.$backwpup_admin_message.'</div>';
?>