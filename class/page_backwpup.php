<?php
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

class BackWPup_Page_Backwpup {

	public function load() {
		global $backwpup_message,$backwpup_listtable,$wpdb;
		//Create Table
		$backwpup_listtable = new BackWPup_Table_Jobs;

		switch($backwpup_listtable->current_action()) {
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
					if ($option->name=="logfile" || $option->name=="starttime" or
						$option->name=="lastbackupdownloadurl" || $option->name=="lastruntime" or
						$option->name=="lastrun" || $option->name=="cronnextrun")
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
							if ($option->name=="logfile" || $option->name=="starttime" or
								$option->name=="lastbackupdownloadurl" || $option->name=="lastruntime" or
								$option->name=="lastrun" || $option->name=="cronnextrun")
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
				$backupdata=backwpup_get_workingdata();
				if (!$backupdata)
					break;
				backwpup_delete_option('working','data'); //delete working data
				if (file_exists(backwpup_get_option('cfg','tempfolder').'.backwpup_working_'.substr(md5(ABSPATH),16)))
					unlink(backwpup_get_option('cfg','tempfolder').'.backwpup_working_'.substr(md5(ABSPATH),16));
				if (!empty($backupdata['LOGFILE'])) {
					$timestamp = "<span title=\"[Type: " .E_USER_ERROR . "|Line: " . __LINE__ . "|File: " . basename(__FILE__) . "|PID: " . $backupdata['PID'] . "|Query's: " . $backupdata['COUNT']['SQLQUERRYS'] . "]\">[" . date_i18n('d-M-Y H:i:s') . "]</span> ";
					file_put_contents($backupdata['LOGFILE'], $timestamp . "<span class=\"error\">" . __('ERROR:', 'backwpup')." ".__('Aborted by user!!!','backwpup')."</span><br />\n", FILE_APPEND);
					//write new log header
					$backupdata['ERROR']++;
					$fd=fopen($backupdata['LOGFILE'],'r+');
					$filepos=ftell($fd);
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
				if (!empty($backupdata['PID']) && function_exists('posix_kill')) {
					if (posix_kill($backupdata['PID'],9))
						$backwpup_message.=__('Process killed with PID:','backwpup').' '.$backupdata['PID'];
					else
						$backwpup_message.=__('Can\'t kill process with PID:','backwpup').' '.$backupdata['PID'];
				}
				//update job settings
				$sarttime=backwpup_get_option($backupdata['JOBMAIN'],'starttime');
				if (!empty($backupdata['JOBMAIN']) && $sarttime) {
					backwpup_update_option($backupdata['JOBMAIN'],'starttime','');
					backwpup_update_option($backupdata['JOBMAIN'],'lastrun',$sarttime);
					backwpup_update_option($backupdata['JOBMAIN'],'lastruntime',(current_time('timestamp')-$sarttime));
				}
				//clean up temp
				if (!empty($backupdata['BACKUPFILE']) && file_exists(backwpup_get_option('CFG','tempfolder').$backupdata['BACKUPFILE']))
					unlink(backwpup_get_option('CFG','tempfolder').$backupdata['BACKUPFILE']);
				if (!empty($backupdata['DBDUMPFILE']) && file_exists(backwpup_get_option('CFG','tempfolder').$backupdata['DBDUMPFILE']))
					unlink(backwpup_get_option('CFG','tempfolder').$backupdata['DBDUMPFILE']);
				if (!empty($backupdata['WPEXPORTFILE']) && file_exists(backwpup_get_option('CFG','tempfolder').$backupdata['WPEXPORTFILE']))
					unlink(backwpup_get_option('CFG','tempfolder').$backupdata['WPEXPORTFILE']);
				break;
		}

		//add Help
		BackWPup_Help::help();
		BackWPup_Help::add_tab(array(
				'id'      => 'overview',
				'title'   => __('Overview'),
				'content'	=>
				'<p>' . __('Here can see some information about the jobs. How many can be switched with the view button. Also you can manage the jobs and abort working. With the links you have direct access to the last log or download.','backwpup') . '</p>'
			) );

		//add css for Admin Section
		wp_enqueue_style('backwpup_backwpup',plugins_url('',dirname(__FILE__)).'/css/backwpup.css','',((defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? time() : backwpup_get_version()),'screen');
		//add java for Admin Section
		wp_enqueue_script('backwpup_backwpup',plugins_url('',dirname(__FILE__)).'/js/backwpup.js','',((defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? time() : backwpup_get_version()),true);

		$backwpup_listtable->prepare_items();
	}

	public function env_check() {
		global $wpdb,$wp_version;
		$massage='';
		// check MySQL Version
		if (version_compare($wpdb->get_var( "SELECT VERSION() AS version" ), '5.0', '<'))
			$massage.=__("- MySQL 5.0 or higher is needed!",'backwpup').'<br />';
		// check logs folder
		if (!backwpup_check_open_basedir(backwpup_get_option('cfg','logfolder'))) //check open basedir
			$massage.=sprintf(__("- Log folder '%s' is not in open_basedir path!",'backwpup'),backwpup_get_option('cfg','logfolder')).'<br />';
		if (backwpup_get_option('cfg','logfolder') && !is_dir(backwpup_get_option('cfg','logfolder')))  // create logs folder if it not exists
			@mkdir(untrailingslashit(backwpup_get_option('cfg','logfolder')),FS_CHMOD_DIR,true);
		if (!is_dir(backwpup_get_option('cfg','logfolder')))  // check logs folder
			$massage.=sprintf(__("- Log folder '%s' not exists!",'backwpup'),backwpup_get_option('cfg','logfolder')).'<br />';
		if (!is_writable(backwpup_get_option('cfg','logfolder'))) { // check logs folder
			$massage.=sprintf(__("- Log folder '%s' is not writable!",'backwpup'),backwpup_get_option('cfg','logfolder')).'<br />';
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
			$massage.=sprintf(__("- Temp folder '%s' is not in open_basedir path!",'backwpup'),backwpup_get_option('cfg','tempfolder')).'<br />';
		if (backwpup_get_option('cfg','tempfolder') && !is_dir(backwpup_get_option('cfg','tempfolder')))  // create temp folder if it not exists
			@mkdir(untrailingslashit(backwpup_get_option('cfg','tempfolder')),FS_CHMOD_DIR,true);
		if (!is_dir(backwpup_get_option('cfg','tempfolder')))
			$massage.=sprintf(__("- Temp folder '%s' not exists!",'backwpup'),backwpup_get_option('cfg','tempfolder')).'<br />';
		if (!is_writable(backwpup_get_option('cfg','tempfolder'))) {
			$massage.=sprintf(__("- Temp folder '%s' is not writable!",'backwpup'),backwpup_get_option('cfg','tempfolder')).'<br />';
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
		if (strtolower(substr(WP_CONTENT_URL,0,7))!='http://' && strtolower(substr(WP_CONTENT_URL,0,8))!='https://') {
			$massage.=sprintf(__("- WP_CONTENT_URL '%s' must set as a full URL!",'backwpup'),WP_CONTENT_URL).'<br />';
		}
		if (strtolower(substr(WP_PLUGIN_URL,0,7))!='http://' && strtolower(substr(WP_PLUGIN_URL,0,8))!='https://') {
			$massage.=sprintf(__("- WP_PLUGIN_URL '%s' must set as a full URL!",'backwpup'),WP_PLUGIN_URL).'<br />';
		}
		//set checks ok or not
		if (!empty($massage))
			backwpup_update_option('backwpup','check',false);
		else
			backwpup_update_option('backwpup','check',true);
		//not relevant checks for job start
		if (false !== $nextrun=wp_next_scheduled('backwpup_cron') && backwpup_get_option('cfg','apicronservicekey')=='') {
			if (empty($nextrun) || $nextrun<(time()-(3600*24))) {  //check cron jobs work
				$massage.=__("- WP-Cron isn't working, please check it!","backwpup") .'<br />';
			}
		}
		if (file_exists(ABSPATH.'backwpup_db_restore.php') || file_exists(ABSPATH.'backwpup_db_restore.zip') || file_exists(ABSPATH.'.backwpup_restore')) {  //for restore file
			$massage.=__("- BackWPup DB restore script found in Blog root please delete it, for security!","backwpup") .'<br />';
		}
		//look for sql dumps in blog root
		if ( $dir = opendir(ABSPATH)) {
			$sqlfiles=array();
			while (($file = readdir( $dir ) ) !== false ) {
				if (strtolower(substr($file,-4))==".sql" || strtolower(substr($file,-7))==".sql.gz" || strtolower(substr($file,-7))==".sql.bz2")
					$sqlfiles[]=$file;
			}
			closedir( $dir );
		}
		if (!empty($sqlfiles))   //for restore file
			$massage.=sprintf(__("- SQL dumps '%s' found in Blog root please delete it, for security!","backwpup"), implode(', ',$sqlfiles) ).'<br />';
		return $massage;
	}

	public function page() {
		global $backwpup_message,$backwpup_listtable;
		echo "<div class=\"wrap\">";
		screen_icon();
		echo "<h2>".esc_html( __('BackWPup Jobs', 'backwpup'))."&nbsp;<a href=\"".wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpupeditjob', 'edit-job')."\" class=\"button add-new-h2\">".esc_html__('Add New','backwpup')."</a></h2>";
		$backwpup_error_message=self::env_check();
		if (!empty($backwpup_error_message))
			echo '<div id="message" class="error fade"><strong>'.__('BackWPup:', 'backwpup').'</strong><br />'.$backwpup_error_message.'</div>';
		if (isset($backwpup_message) && !empty($backwpup_message))
			echo "<div id=\"message\" class=\"updated\"><p>".$backwpup_message."</p></div>";
		echo "<form id=\"posts-filter\" action=\"\" method=\"get\">";
		echo "<input type=\"hidden\" name=\"page\" value=\"backwpup\" />";
		wp_nonce_field('backwpup_ajax_nonce', 'backwpupajaxnonce', false );
		$backwpup_listtable->display();
		echo "<div id=\"ajax-response\"></div>";
		echo "</form>";
		echo "</div>";
	}
}