<?PHP
if (!defined('ABSPATH'))
	die();


//Create Table
$backwpup_listtable = new BackWPup_Jobs_Table;

//get cuurent action
$doaction = $backwpup_listtable->current_action();

if (!empty($doaction)) {
	switch($doaction) {
	case 'delete': //Delete Job
		$jobs=get_option('backwpup_jobs');
		if (is_array($_GET['jobs'])) {
			check_admin_referer('bulk-jobs');
			foreach ($_GET['jobs'] as $jobid) {
				unset($jobs[$jobid]);
			}
		}
		//activate/deactivate seduling if not needed
		$activejobs=false;
		foreach ($jobs as $jobid => $jobvalue) {
			if (!empty($jobvalue['activated']))
				$activejobs=true;
		}
		if (!$activejobs and false !== wp_next_scheduled('backwpup_cron')) {
			wp_clear_scheduled_hook('backwpup_cron');
		}
		update_option('backwpup_jobs',$jobs);
		break;
	case 'copy': //Copy Job
		$jobid = (int) $_GET['jobid'];
		check_admin_referer('copy-job_'.$jobid);
		$jobs=get_option('backwpup_jobs');
		//generate new ID
		$heighestid=0;
		foreach ($jobs as $jobkey => $jobvalue) {
			if ($jobkey>$heighestid) $heighestid=$jobkey;
		}
		$newjobid=$heighestid+1;
		$jobs[$newjobid]=$jobs[$jobid];
		$jobs[$newjobid]['name']=__('Copy of','backwpup').' '.$jobs[$newjobid]['name'];
		$jobs[$newjobid]['activated']=false;
		$jobs[$newjobid]['fileprefix']=str_replace($jobid,$newjobid,$jobs[$jobid]['fileprefix']);
		unset($jobs[$newjobid]['logfile']);
		unset($jobs[$newjobid]['starttime']);
		unset($jobs[$newjobid]['lastbackupdownloadurl']);
		unset($jobs[$newjobid]['lastruntime']);
		unset($jobs[$newjobid]['lastrun']);
		update_option('backwpup_jobs',$jobs);
		break;
	case 'export': //Copy Job
		if (is_array($_GET['jobs'])) {
			check_admin_referer('bulk-jobs');
			foreach ($_GET['jobs'] as $jobid) {
				$jobsexport[$jobid]=backwpup_get_job_vars($jobid);
				$jobsexport[$jobid]['activated']=false;
				unset($jobsexport[$jobid]['logfile']);
				unset($jobsexport[$jobid]['starttime']);
				unset($jobsexport[$jobid]['lastbackupdownloadurl']);
				unset($jobsexport[$jobid]['lastruntime']);
				unset($jobsexport[$jobid]['lastrun']);
			}
		}
		$export=serialize($jobsexport);
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
		$backupdata=get_transient('backwpup_job_working');
		if (empty($backupdata))
			break;
        delete_transient('backwpup_job_working');
        delete_transient('backwpup_job_filelist');
		//clean up temp
		if (file_exists($backupdata['STATIC']['TEMPDIR'].$backupdata['STATIC']['backupfile']))
			unlink($backupdata['STATIC']['TEMPDIR'].$backupdata['STATIC']['backupfile']);
		if (file_exists($backupdata['STATIC']['TEMPDIR'].$backupdata['STATIC']['JOB']['dbdumpfile']))	
			unlink($backupdata['STATIC']['TEMPDIR'].$backupdata['STATIC']['JOB']['dbdumpfile']);
		if (file_exists($backupdata['STATIC']['TEMPDIR'].$backupdata['STATIC']['JOB']['wpexportfile']))	
			unlink($backupdata['STATIC']['TEMPDIR'].$backupdata['STATIC']['JOB']['wpexportfile']);
		if (!empty($backupdata['LOGFILE'])) {
			file_put_contents($backupdata['LOGFILE'], "<span class=\"timestamp\">".date_i18n('Y/m/d H:i.s').":</span> <span class=\"error\">[ERROR]".__('Aborted by user!!!','backwpup')."</span><br />\n", FILE_APPEND);
			//write new log header
			$backupdata['WORKING']['ERROR']++;
			$fd=fopen($backupdata['LOGFILE'],'r+');
			while (!feof($fd)) {
				$line=fgets($fd);
				if (stripos($line,"<meta name=\"backwpup_errors\"") !== false) {
					fseek($fd,$filepos);
					fwrite($fd,str_pad("<meta name=\"backwpup_errors\" content=\"".$backupdata['WORKING']['ERROR']."\" />",100)."\n");
					break;
				}
				$filepos=ftell($fd);
			}
			fclose($fd);
		}
		$backwpup_message=__('Job will be terminated.','backwpup').'<br />';
		if (!empty($backupdata['WORKING']['PID']) and function_exists('posix_kill')) {
			if (posix_kill($backupdata['WORKING']['PID'],9))
				$backwpup_message.=__('Process killed with PID:','backwpup').' '.$backupdata['WORKING']['PID'];
			else
				$backwpup_message.=__('Can\'t kill process with PID:','backwpup').' '.$runningfile['WORKING']['PID'];
		}
		//update job settings
		if (!empty($backupdata['STATIC']['JOB']['jobid'])) {
			$jobs=get_option('backwpup_jobs');
			if (isset($newlogfile) and !empty($newlogfile))
				$jobs[$runningfile['STATIC']['JOB']['jobid']]['logfile']=$newlogfile;
			$jobs[$backupdata['STATIC']['JOB']['jobid']]['lastrun']=$jobs[$backupdata['STATIC']['JOB']['jobid']]['starttime'];
			$jobs[$backupdata['STATIC']['JOB']['jobid']]['lastruntime']=$backupdata['timestamp']-$jobs[$backupdata['STATIC']['JOB']['jobid']]['starttime'];
			update_option('backwpup_jobs',$jobs); //Save Settings
		}
		break;
	}
}

//add Help
backwpup_contextual_help(__('Here is the job overview with some information. You can see some further information of the jobs, how many can be switched with the view button. Also you can manage the jobs or abbort working jobs. Some links are added to have direct access to the last log or download.','backwpup'));

$backwpup_listtable->prepare_items();

//ENV Checks
global $wp_version,$backwpup_admin_message;
$backwpup_admin_message='';
$cfg=get_option('backwpup');
if (version_compare($wp_version, BACKWPUP_MIN_WORDPRESS_VERSION, '<')) { // check WP Version
	$backwpup_admin_message.=str_replace('%d',BACKWPUP_MIN_WORDPRESS_VERSION,__('- WordPress %d or higher is needed!','backwpup')) . '<br />';
	$checks=false;
}
if (version_compare(phpversion(), '5.2.4', '<')) { // check PHP Version
	$backwpup_admin_message.=__('- PHP 5.2.4 or higher is needed!','backwpup') . '<br />';
	$checks=false;
}
if (!backwpup_check_open_basedir($cfg['dirlogs'])) { // check logs folder
	$backwpup_admin_message.=sprintf(__("- Log folder '%s' is not in open_basedir path!",'backwpup'),$cfg['dirlogs']).'<br />';
	if (!empty($cfg['dirlogs']) and !is_dir($cfg['dirlogs'])) { // create logs folder if it not exists
		@mkdir(untrailingslashit($cfg['dirlogs']),FS_CHMOD_DIR,true);
	}
	if (!is_dir($cfg['dirlogs'])) { // check logs folder
		$backwpup_admin_message.=printf(__("- Log folder '%s' not exists!",'backwpup'),$cfg['dirlogs']);
	}
	if (!is_writable($cfg['dirlogs'])) { // check logs folder
		$backwpup_admin_message.=sprintf(__("- Log folder '%s' is not writeable!",'backwpup'),$cfg['dirlogs']).'<br />';
	} else {
		//create .htaccess for apache and index.html for other
		if (strtolower(substr($_SERVER["SERVER_SOFTWARE"],0,6))=="apache") {  //check if it a apache webserver
			if (!is_file($cfg['dirlogs'].'.htaccess'))
				file_put_contents($cfg['dirlogs'].'.htaccess',"Order allow,deny\ndeny from all");
		} else {
			if (!is_file($cfg['dirlogs'].'index.html'))
				file_put_contents($cfg['dirlogs'].'index.html',"\n");
			if (!is_file($cfg['dirlogs'].'index.php'))
				file_put_contents($cfg['dirlogs'].'index.php',"\n");
		}
	}
}
$tempdir=backwpup_get_temp();
if (!backwpup_check_open_basedir($tempdir)) { // check temp folder
	$backwpup_admin_message.=sprintf(__("- Temp folder '%s' is not in open_basedir path!",'backwpup'),$tempdir).'<br />';
	if (!is_dir($tempdir)) { // create logs folder if it not exists
		@mkdir(untrailingslashit($tempdir),FS_CHMOD_DIR,true);
	}
	if (!is_dir($cfg['dirlogs'])) { // check logs folder
		$backwpup_admin_message.=printf(__("- Temp folder '%s' not exists!",'backwpup'),$tempdir);
	}
	if (!is_writable($tempdir)) { // check logs folder
		$backwpup_admin_message.=sprintf(__("- Temp folder '%s' is not writeable!",'backwpup'),$tempdir).'<br />';
	} else {
		//create .htaccess for apache and index.html for other
		if (strtolower(substr($_SERVER["SERVER_SOFTWARE"],0,6))=="apache") {  //check if it a apache webserver
			if (!is_file($tempdir.'.htaccess'))
				file_put_contents($tempdir.'.htaccess',"Order allow,deny\ndeny from all");
		} else {
			if (!is_file($tempdir.'index.html'))
				file_put_contents($tempdir.'index.html',"\n");
			if (!is_file($tempdir.'index.php'))
				file_put_contents($tempdir.'index.php',"\n");
		}
	}
}
if (strtolower(substr(WP_CONTENT_URL,0,7))!='http://' and strtolower(substr(WP_CONTENT_URL,0,8))!='https://') { // check logs folder
	$backwpup_admin_message.=sprintf(__("- WP_CONTENT_URL '%s' must set as a full URL!",'backwpup'),WP_CONTENT_URL).'<br />';
}
if (strtolower(substr(WP_PLUGIN_URL,0,7))!='http://' and strtolower(substr(WP_PLUGIN_URL,0,8))!='https://') { // check logs folder
	$backwpup_admin_message.=sprintf(__("- WP_PLUGIN_URL '%s' must set as a full URL!",'backwpup'),WP_PLUGIN_URL).'<br />';
}
//set cheks ok or not
if (!empty($backwpup_admin_message)) 
	define('BACKWPUP_ENV_CHECK_OK',false);
else
	define('BACKWPUP_ENV_CHECK_OK',true);
//not relevant cheks for job start
if (false !== $nextrun=wp_next_scheduled('backwpup_cron')) {
	if (empty($nextrun) or $nextrun<(time()-(3600*24))) {  //check cron jobs work
		$backwpup_admin_message.=__("- WP-Cron isn't working, please check it!","backwpup") .'<br />';
	}
}
//put massage if one
if (!empty($backwpup_admin_message))
	$backwpup_admin_message = '<div id="message" class="error fade"><strong>BackWPup:</strong><br />'.$backwpup_admin_message.'</div>';
?>