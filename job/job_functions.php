<?PHP
// add to file list
function backwpup_job_add_file($files) {
	global $backwpupjobrun;
	if (empty($files))
		return;
	$filelist=get_option('backwpup_job_filelist');
	foreach($files as $file)
		$filelist[]=$file;
	update_option('backwpup_job_filelist',$filelist);
}

function backwpup_job_inbytes($value) {
	$multi=strtoupper(substr(trim($value),-1));
	$bytes=abs(intval(trim($value)));
	if ($multi=='G')
		$bytes=$bytes*1024*1024*1024;
	if ($multi=='M')
		$bytes=$bytes*1024*1024;
	if ($multi=='K')
		$bytes=$bytes*1024;
	return $bytes;
}

function backwpup_job_need_free_memory($memneed) {
	if (!function_exists('memory_get_usage'))
		return;
	//need memory
	$needmemory=@memory_get_usage(true)+backwpup_job_inbytes($memneed);
	// increase Memory
	if ($needmemory>backwpup_job_inbytes(ini_get('memory_limit'))) {
		$newmemory=round($needmemory/1024/1024)+1 .'M';
		if ($needmemory>=1073741824)
			$newmemory=round($needmemory/1024/1024/1024) .'G';
		if ($oldmem=@ini_set('memory_limit', $newmemory))
			trigger_error(sprintf(__('Memory increased from %1$s to %2$s','backwpup'),$oldmem,@ini_get('memory_limit')),E_USER_NOTICE);
		else
			trigger_error(sprintf(__('Can not increase memory limit is %1$s','backwpup'),@ini_get('memory_limit')),E_USER_WARNING);
	}
}

function backwpup_job_maintenance_mode($enable = false) {
	global $backwpupjobrun;
	if (!$backwpupjobrun['STATIC']['JOB']['maintenance'])
		return;
	if ( $enable ) {
		trigger_error(__('Set Blog to maintenance mode','backwpup'),E_USER_NOTICE);
		if ( get_option('wp-maintenance-mode-msqld') ) { //Support for WP Maintenance Mode Plugin
			update_option('wp-maintenance-mode-msqld','1');
		} elseif ($mamo=get_option('plugin_maintenance-mode')) { //Support for Maintenance Mode Plugin
			$mamo['mamo_activate']='on_'.current_time('timestamp');
			$mamo['mamo_backtime_days']='0';
			$mamo['mamo_backtime_hours']='0';
			$mamo['mamo_backtime_mins']='5';
			update_option('plugin_maintenance-mode',$mamo);
		} else { //WP Support
			if (is_writable(rtrim(trailingslashit(str_replace('\\','/',ABSPATH)),'/')))
				file_put_contents(rtrim(trailingslashit(str_replace('\\','/',ABSPATH)),'/').'/.maintenance','<?php $upgrading = '.current_time('timestamp').'; ?>');
			else
				trigger_error(__('Cannot set Blog to maintenance mode! Root folder is not writeable!','backwpup'),E_USER_NOTICE);
		}
	} else {
		trigger_error(__('Set Blog to normal mode','backwpup'),E_USER_NOTICE);
		if ( get_option('wp-maintenance-mode-msqld') ) { //Support for WP Maintenance Mode Plugin
			update_option('wp-maintenance-mode-msqld','0');
		} elseif ($mamo=get_option('plugin_maintenance-mode')) { //Support for Maintenance Mode Plugin
			$mamo['mamo_activate']='off';
			update_option('plugin_maintenance-mode',$mamo);
		} else { //WP Support
			@unlink(rtrim(trailingslashit(str_replace('\\','/',ABSPATH)),'/').'/.maintenance');
		}
	}
}

function backwpup_job_curl_progresscallback($download_size, $downloaded, $upload_size, $uploaded) {
	global $backwpupjobrun;
	if ($backwpupjobrun['WORKING']['STEPTODO']>10)
		$backwpupjobrun['WORKING']['STEPDONE']=$uploaded;
	backwpup_job_update_working_data();
	return(0);
}

function backwpup_job_update_working_data($mustwrite=false) {
	global $backwpupjobrun,$wpdb;
    $backupdata=get_option('backwpup_job_working');
	if (empty($backupdata))
		backwpup_job_job_end();
	if ($mustwrite or empty($backwpupjobrun['runmicrotime']) or $backwpupjobrun['runmicrotime']<(microtime()-1000)) { //only update all 1 sec.
		if(!mysql_ping($wpdb->dbh)) { //check MySQL connection
			trigger_error(__('Database connection is gone create a new one.','backwpup'),E_USER_NOTICE);
			$wpdb->db_connect();
		}
        if ($backwpupjobrun['WORKING']['STEPTODO']>0 and $backwpupjobrun['WORKING']['STEPDONE']>0)
			$backwpupjobrun['WORKING']['STEPPERSENT']=round($backwpupjobrun['WORKING']['STEPDONE']/$backwpupjobrun['WORKING']['STEPTODO']*100);
		else
			$backwpupjobrun['WORKING']['STEPPERSENT']=1;
		if (count($backwpupjobrun['WORKING']['STEPSDONE'])>0)
			$backwpupjobrun['WORKING']['STEPSPERSENT']=round(count($backwpupjobrun['WORKING']['STEPSDONE'])/count($backwpupjobrun['WORKING']['STEPS'])*100);
		else
			$backwpupjobrun['WORKING']['STEPSPERSENT']=1;
		$backwpupjobrun['WORKING']['TIMESTAMP']=current_time('timestamp');
		@set_time_limit(0);
		update_option('backwpup_job_working',$backwpupjobrun);
	}
	return true;
}

//function for PHP error handling
function backwpup_job_joberrorhandler() {
	global $backwpupjobrun;
	$args = func_get_args(); // 0:errno, 1:errstr, 2:errfile, 3:errline

	// if error has been supressed with an @
    if (error_reporting()==0)
        return;

	$adderrorwarning=false;

	switch ($args[0]) {
	case E_NOTICE:
	case E_USER_NOTICE:
		$message="<span>".$args[1]."</span>";
		break;
	case E_WARNING:
	case E_USER_WARNING:
		$backwpupjobrun['WORKING']['WARNING']++;
		$adderrorwarning=true;
		$message="<span class=\"warning\">".__('[WARNING]','backwpup')." ".$args[1]."</span>";
		break;
	case E_ERROR:
	case E_USER_ERROR:
		$backwpupjobrun['WORKING']['ERROR']++;
		$adderrorwarning=true;
		$message="<span class=\"error\">".__('[ERROR]','backwpup')." ".$args[1]."</span>";
		break;
	case E_DEPRECATED:
	case E_USER_DEPRECATED:
		$message="<span>".__('[DEPRECATED]','backwpup')." ".$args[1]."</span>";
		break;
	case E_STRICT:
		$message="<span>".__('[STRICT NOTICE]','backwpup')." ".$args[1]."</span>";
		break;
	case E_RECOVERABLE_ERROR:
		$message="<span>".__('[RECOVERABLE ERROR]','backwpup')." ".$args[1]."</span>";
		break;
	default:
		$message="<span>[".$args[0]."] ".$args[1]."</span>";
		break;
	}

	//log line
	$timestamp="<span class=\"timestamp\" title=\"[Line: ".$args[3]."|File: ".basename($args[2])."|Mem: ".backwpup_formatBytes(@memory_get_usage(true))."|Mem Max: ".backwpup_formatBytes(@memory_get_peak_usage(true))."|Mem Limit: ".ini_get('memory_limit')."|PID: ".getmypid()."]\">".date_i18n('Y/m/d H:i.s').":</span> ";
	//wirte log file
	if (is_writable($backwpupjobrun['LOGFILE'])) {
		file_put_contents($backwpupjobrun['LOGFILE'], $timestamp.$message."<br />\n", FILE_APPEND);

		//write new log header
		if ($adderrorwarning) {
			$found=0;
			$fd=fopen($backwpupjobrun['LOGFILE'],'r+');
			while (!feof($fd)) {
				$line=fgets($fd);
				if (stripos($line,"<meta name=\"backwpup_errors\"") !== false) {
					fseek($fd,$filepos);
					fwrite($fd,str_pad("<meta name=\"backwpup_errors\" content=\"".$backwpupjobrun['WORKING']['ERROR']."\" />",100)."\n");
					$found++;
				}
				if (stripos($line,"<meta name=\"backwpup_warnings\"") !== false) {
					fseek($fd,$filepos);
					fwrite($fd,str_pad("<meta name=\"backwpup_warnings\" content=\"".$backwpupjobrun['WORKING']['WARNING']."\" />",100)."\n");
					$found++;
				}
				if ($found>=2)
					break;
				$filepos=ftell($fd);
			}
			fclose($fd);
		}
	}

    //write working data
	backwpup_job_update_working_data();

	if ($args[0]==E_ERROR or $args[0]==E_CORE_ERROR or $args[0]==E_COMPILE_ERROR) {//Die on fatal php errors.
		die();
	}

	//true for no more php error hadling.
	return true;
}

//job end function
function backwpup_job_job_end() {
	global $backwpupjobrun;
	//check if job_end allredy runs
	if (empty($backwpupjobrun['WORKING']['JOBENDINPROGRESS']) or !$backwpupjobrun['WORKING']['JOBENDINPROGRESS']) {
		$backwpupjobrun['WORKING']['JOBENDINPROGRESS']=true;
		backwpup_job_update_working_data(true);
	} else
		return;

	$backwpupjobrun['WORKING']['STEPTODO']=1;
	$backwpupjobrun['WORKING']['STEPDONE']=0;
	//delete old logs
	if (!empty($backwpupjobrun['STATIC']['CFG']['maxlogs'])) {
		if ( $dir = opendir($backwpupjobrun['STATIC']['CFG']['dirlogs']) ) { //make file list
			while (($file = readdir($dir)) !== false ) {
				if ('backwpup_log_' == substr($file,0,strlen('backwpup_log_')) and (".html" == substr($file,-5) or ".html.gz" == substr($file,-8)))
					$logfilelist[]=$file;
			}
			closedir( $dir );
		}
		if (sizeof($logfilelist)>0) {
			rsort($logfilelist);
			$numdeltefiles=0;
			for ($i=$backwpupjobrun['STATIC']['CFG']['maxlogs'];$i<sizeof($logfilelist);$i++) {
				unlink($backwpupjobrun['STATIC']['CFG']['dirlogs'].$logfilelist[$i]);
				$numdeltefiles++;
			}
			if ($numdeltefiles>0)
				trigger_error(sprintf(_n('One old log deleted','%d old logs deleted',$numdeltefiles,'backwpup'),$numdeltefiles),E_USER_NOTICE);
		}
	}
	//Display job working time
	trigger_error(sprintf(__('Job done in %s sec.','backwpup'),current_time('timestamp')-$backwpupjobrun['STATIC']['JOB']['starttime']),E_USER_NOTICE);

	if (empty($backwpupjobrun['STATIC']['backupfile']) or !is_file($backwpupjobrun['STATIC']['JOB']['backupdir'].$backwpupjobrun['STATIC']['backupfile']) or !($filesize=filesize($backwpupjobrun['STATIC']['JOB']['backupdir'].$backwpupjobrun['STATIC']['backupfile']))) //Set the filezie corectly
		$filesize=0;

	//clean up temp
	if ($dir = opendir($backwpupjobrun['STATIC']['TEMPDIR'])) {
		while (($file = readdir($dir)) !== false) {
			if (is_readable($backwpupjobrun['STATIC']['TEMPDIR'].$file) and is_file($backwpupjobrun['STATIC']['TEMPDIR'].$file)) {
				if ($file!='.' and $file!='..') {
					unlink($backwpupjobrun['STATIC']['TEMPDIR'].$file);
				}
			}
		}
		closedir($dir);
	}

	$jobs=get_option('backwpup_jobs');
	$jobs[$backwpupjobrun['STATIC']['JOB']['jobid']]['lastrun']=$jobs[$backwpupjobrun['STATIC']['JOB']['jobid']]['starttime'];
	$backwpupjobrun['STATIC']['JOB']['lastrun']=$jobs[$backwpupjobrun['STATIC']['JOB']['jobid']]['lastrun'];
	$jobs[$backwpupjobrun['STATIC']['JOB']['jobid']]['lastruntime']=current_time('timestamp')-$backwpupjobrun['STATIC']['JOB']['starttime'];
	$backwpupjobrun['STATIC']['JOB']['lastruntime']=$jobs[$backwpupjobrun['STATIC']['JOB']['jobid']]['lastruntime'];
	$jobs[$backwpupjobrun['STATIC']['JOB']['jobid']]['starttime']='';
	if (!empty($backwpupjobrun['STATIC']['JOB']['lastbackupdownloadurl']))
		$jobs[$backwpupjobrun['STATIC']['JOB']['jobid']]['lastbackupdownloadurl']=$backwpupjobrun['STATIC']['JOB']['lastbackupdownloadurl'];
	else
		$jobs[$backwpupjobrun['STATIC']['JOB']['jobid']]['lastbackupdownloadurl']='';
	update_option('backwpup_jobs',$jobs); //Save Settings

	//write header info
	if (is_writable($backwpupjobrun['LOGFILE'])) {
		$fd=fopen($backwpupjobrun['LOGFILE'],'r+');
		$found=0;
		while (!feof($fd)) {
			$line=fgets($fd);
			if (stripos($line,"<meta name=\"backwpup_jobruntime\"") !== false) {
				fseek($fd,$filepos);
				fwrite($fd,str_pad("<meta name=\"backwpup_jobruntime\" content=\"".$backwpupjobrun['STATIC']['JOB']['lastruntime']."\" />",100)."\n");
				$found++;
			}
			if (stripos($line,"<meta name=\"backwpup_backupfilesize\"") !== false) {
				fseek($fd,$filepos);
				fwrite($fd,str_pad("<meta name=\"backwpup_backupfilesize\" content=\"".$filesize."\" />",100)."\n");
				$found++;
			}
			if ($found>=2)
				break;
			$filepos=ftell($fd);
		}
		fclose($fd);
	}
	//Restore error handler
	restore_error_handler();
	//logfile end
	file_put_contents($backwpupjobrun['LOGFILE'], "</body>\n</html>\n", FILE_APPEND);
	//gzip logfile
	if ($backwpupjobrun['STATIC']['CFG']['gzlogs'] and is_writable($backwpupjobrun['LOGFILE'])) {
		$fd=fopen($backwpupjobrun['LOGFILE'],'r');
		$zd=gzopen($backwpupjobrun['LOGFILE'].'.gz','w9');
		while (!feof($fd)) {
			gzwrite($zd,fread($fd,4096));
		}
		gzclose($zd);
		fclose($fd);
		unlink($backwpupjobrun['LOGFILE']);
		$backwpupjobrun['LOGFILE']=$backwpupjobrun['LOGFILE'].'.gz';

		$jobs=get_option('backwpup_jobs');
		$jobs[$backwpupjobrun['STATIC']['JOB']['jobid']]['logfile']=$backwpupjobrun['LOGFILE'];
		update_option('backwpup_jobs',$jobs); //Save Settings
	}

	//Send mail with log
	$sendmail=false;
	if ($backwpupjobrun['WORKING']['ERROR']>0 and $backwpupjobrun['STATIC']['JOB']['mailerroronly'] and !empty($backwpupjobrun['STATIC']['JOB']['mailaddresslog']))
		$sendmail=true;
	if (!$backwpupjobrun['STATIC']['JOB']['mailerroronly'] and !empty($backwpupjobrun['STATIC']['JOB']['mailaddresslog']))
		$sendmail=true;
	if ($sendmail) {
		$message='';
		if (substr($backwpupjobrun['LOGFILE'],-3)=='.gz') {
			$lines=gzfile($backwpupjobrun['LOGFILE']);
			foreach ($lines as $line) {
				$message.=$line;
			}
		} else {
			$message=file_get_contents($backwpupjobrun['LOGFILE']);
		}

		if (empty($backwpupjobrun['STATIC']['CFG']['mailsndname']))
			$headers = 'From: '.$backwpupjobrun['STATIC']['CFG']['mailsndname'].' <'.$backwpupjobrun['STATIC']['CFG']['mailsndemail'].'>' . "\r\n";
		else
			$headers = 'From: '.$backwpupjobrun['STATIC']['CFG']['mailsndemail'] . "\r\n";
		$status='Successful';
		if ($backwpupjobrun['WORKING']['WARNING']>0)
			$status='Warning';
		if ($backwpupjobrun['WORKING']['ERROR']>0)
			$status='Error';
		add_filter('wp_mail_content_type',create_function('', 'return "text/html"; '));
		wp_mail($backwpupjobrun['STATIC']['JOB']['mailaddresslog'],
				sprintf(__('[%3$s] BackWPup log %1$s: %2$s','backwpup'),date_i18n('Y/m/d @ H:i',$backwpupjobrun['STATIC']['JOB']['starttime']),$backwpupjobrun['STATIC']['JOB']['name'],$status),
				$message,
				$headers);
	}

	$backwpupjobrun['WORKING']['STEPDONE']=1;
	$backwpupjobrun['WORKING']['STEPSDONE'][]='JOB_END'; //set done
	delete_option('backwpup_job_working');
	delete_option('backwpup_job_filelist');
	die();
}

// execute on script job shutdown
function backwpup_job_shutdown($signal='') {
	global $backwpupjobrun;
	if (empty($backwpupjobrun['LOGFILE'])) //nothing on empty
		return;
	//Put last error to log if one
	$lasterror=error_get_last();
	if (($lasterror['type']==E_ERROR or $lasterror['type']==E_PARSE or $lasterror['type']==E_CORE_ERROR or $lasterror['type']==E_COMPILE_ERROR or !empty($signal))) {
		if (!empty($signal))
			file_put_contents($backwpupjobrun['LOGFILE'], "<span class=\"timestamp\" title=\"[Line: ".__LINE__."|File: ".basename(__FILE__)."|Mem: ".backwpup_formatBytes(@memory_get_usage(true))."|Mem Max: ".backwpup_formatBytes(@memory_get_peak_usage(true))."|Mem Limit: ".ini_get('memory_limit')."|PID: ".getmypid()."]\">".date_i18n('Y/m/d H:i.s').":</span> <span class=\"error\">[ERROR]".sprintf(__('Signal $d send to script!','backwpup'),$signal)."</span><br />\n", FILE_APPEND);
		file_put_contents($backwpupjobrun['LOGFILE'], "<span class=\"timestamp\" title=\"[Line: ".$lasterror['line']."|File: ".basename($lasterror['file'])."|Mem: ".backwpup_formatBytes(@memory_get_usage(true))."|Mem Max: ".backwpup_formatBytes(@memory_get_peak_usage(true))."|Mem Limit: ".ini_get('memory_limit')."|PID: ".getmypid()."]\">".date_i18n('Y/m/d H:i.s').":</span> <span class=\"error\">[ERROR]".$lasterror['message']."</span><br />\n", FILE_APPEND);
		//write new log header
		$backwpupjobrun['WORKING']['ERROR']++;
		$fd=fopen($backwpupjobrun['LOGFILE'],'r+');
		while (!feof($fd)) {
			$line=fgets($fd);
			if (stripos($line,"<meta name=\"backwpup_errors\"") !== false) {
				fseek($fd,$filepos);
				fwrite($fd,str_pad("<meta name=\"backwpup_errors\" content=\"".$backwpupjobrun['WORKING']['ERROR']."\" />",100)."\n");
				break;
			}
			$filepos=ftell($fd);
		}
		fclose($fd);
	}
	//no more restarts
	$backwpupjobrun['WORKING']['RESTART']++;
	if ((defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON) or $backwpupjobrun['WORKING']['RESTART']>=$backwpupjobrun['STATIC']['CFG']['jobscriptretry']) {  //only x restarts allowed
		if (defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON)
			file_put_contents($backwpupjobrun['LOGFILE'], "<span class=\"timestamp\" title=\"[Line: ".__LINE__."|File: ".basename(__FILE__)."\"|Mem: ".backwpup_formatBytes(@memory_get_usage(true))."|Mem Max: ".backwpup_formatBytes(@memory_get_peak_usage(true))."|Mem Limit: ".ini_get('memory_limit')."|PID: ".getmypid()."]>".date_i18n('Y/m/d H:i.s').":</span> <span class=\"error\">[ERROR]".__('Can not restart on alternate cron....','backwpup')."</span><br />\n", FILE_APPEND);
		else
			file_put_contents($backwpupjobrun['LOGFILE'], "<span class=\"timestamp\" title=\"[Line: ".__LINE__."|File: ".basename(__FILE__)."\"|Mem: ".backwpup_formatBytes(@memory_get_usage(true))."|Mem Max: ".backwpup_formatBytes(@memory_get_peak_usage(true))."|Mem Limit: ".ini_get('memory_limit')."|PID: ".getmypid()."]>".date_i18n('Y/m/d H:i.s').":</span> <span class=\"error\">[ERROR]".__('To many restarts....','backwpup')."</span><br />\n", FILE_APPEND);
		$backwpupjobrun['WORKING']['ERROR']++;
		$fd=fopen($backwpupjobrun['LOGFILE'],'r+');
		while (!feof($fd)) {
			$line=fgets($fd);
			if (stripos($line,"<meta name=\"backwpup_errors\"") !== false) {
				fseek($fd,$filepos);
				fwrite($fd,str_pad("<meta name=\"backwpup_errors\" content=\"".$backwpupjobrun['WORKING']['ERROR']."\" />",100)."\n");
				break;
			}
			$filepos=ftell($fd);
		}
		fclose($fd);
		backwpup_job_job_end();
		exit;
	} 
	$backupdata=get_option('backwpup_job_working');
	if (empty($backupdata))
		exit;
	//set PID to 0
	$backwpupjobrun['WORKING']['PID']=0;
	//Excute jobrun again
	backwpup_job_update_working_data(true);
	file_put_contents($backwpupjobrun['LOGFILE'], "<span class=\"timestamp\" title=\"[Line: ".__LINE__."|File: ".basename(__FILE__)."|Mem: ".backwpup_formatBytes(@memory_get_usage(true))."|Mem Max: ".backwpup_formatBytes(@memory_get_peak_usage(true))."|Mem Limit: ".ini_get('memory_limit')."|PID: ".getmypid()."]\">".date_i18n('Y/m/d H:i.s').":</span> <span>".$backwpupjobrun['WORKING']['RESTART'].'. '.__('Script stop! Will started again now!','backwpup')."</span><br />\n", FILE_APPEND);
	$httpauthheader='';
	if (!empty($backwpupjobrun['STATIC']['CFG']['httpauthuser']) and !empty($backwpupjobrun['STATIC']['CFG']['httpauthpassword']))
		$httpauthheader=array( 'Authorization' => 'Basic '.base64_encode($backwpupjobrun['STATIC']['CFG']['httpauthuser'].':'.base64_decode($backwpupjobrun['STATIC']['CFG']['httpauthpassword'])));
	wp_remote_post(BACKWPUP_PLUGIN_BASEURL.'/job/job_run.php', array('timeout' => 5, 'blocking' => false, 'sslverify' => false,'headers'=>$httpauthheader, 'user-agent'=>'BackWPup','body' => array( '_wpnonce' => wp_create_nonce('backwpup-job-running'), 'starttype' => 'restart','ABSPATH'=> ABSPATH)));
	exit;
}
?>