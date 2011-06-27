<?PHP
// don't load directly
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function __($message,$domain='backwpup') {
	$msgid=md5($message);
	if (!empty($_SESSION['TRANSLATE'][$msgid]))
		$message=$_SESSION['TRANSLATE'][$msgid];
	return $message;
}

function _e($message,$domain='backwpup') {
	$msgid=md5($message);
	if (!empty($_SESSION['TRANSLATE'][$msgid]))
		$message=$_SESSION['TRANSLATE'][$msgid];
	echo $message;
}

function _n($singular,$plural,$count,$domain='backwpup') {
	if ($count<=1)
		$msgid=md5($singular);
	else
		$msgid=md5($plural);
	if (!empty($_SESSION['TRANSLATE'][$msgid]))
		$message=$_SESSION['TRANSLATE'][$msgid];
	return $message;
}

function exists_option($option='backwpup_jobs') {
	mysql_update();
	$query="SELECT option_value as value FROM ".$_SESSION['WP']['OPTIONS_TABLE']." WHERE option_name='".trim($option)."' LIMIT 1";
	$res=mysql_query($query);
	if (!$res or mysql_num_rows($res)<1) {
		return false;
	}
	return true;
}

function get_option($option='backwpup_jobs') {
	mysql_update();
	$query="SELECT option_value FROM ".$_SESSION['WP']['OPTIONS_TABLE']." WHERE option_name='".trim($option)."' LIMIT 1";
	$res=mysql_query($query);
	if (!$res) {
		trigger_error(sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), mysql_error(), $query),E_USER_ERROR);
		return false;
	}
	return unserialize(mysql_result($res,0));
}

function update_option($option='backwpup_jobs',$data) {
	mysql_update();
	$serdata=mysql_real_escape_string(serialize($data));
	$query="UPDATE ".$_SESSION['WP']['OPTIONS_TABLE']." SET option_value= '".$serdata."' WHERE option_name='".trim($option)."' LIMIT 1";
	$res=mysql_query($query);
	if (!$res) {
		trigger_error(sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), mysql_error(), $query),E_USER_ERROR);
		return false;
	}
	return true;
}

//file size
function formatbytes($bytes, $precision = 2) {
	$units = array('B', 'KB', 'MB', 'GB', 'TB');
	$bytes = max($bytes, 0);
	$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
	$pow = min($pow, count($units) - 1);
	$bytes /= pow(1024, $pow);
	return round($bytes, $precision) . ' ' . $units[$pow];
}

function need_free_memory($memneed) {
	if (ini_get('safe_mode') or strtolower(ini_get('safe_mode'))=='on' or ini_get('safe_mode')=='1') {
		trigger_error(sprintf(__('PHP Safe Mode is on!!! Can not increase Memory Limit is %1$s','backwpup'),ini_get('memory_limit')),E_USER_WARNING);
		return false;
	}

	//calc mem to bytes
	if (strtoupper(substr(trim(ini_get('memory_limit')),-1))=='K')
		$memory=trim(substr(ini_get('memory_limit'),0,-1))*1024;
	elseif (strtoupper(substr(trim(ini_get('memory_limit')),-1))=='M')
		$memory=trim(substr(ini_get('memory_limit'),0,-1))*1024*1024;
	elseif (strtoupper(substr(trim(ini_get('memory_limit')),-1))=='G')
		$memory=trim(substr(ini_get('memory_limit'),0,-1))*1024*1024*1024;
	else
		$memory=trim(ini_get('memory_limit'));

	//use real memory at php version 5.2.0
	if (version_compare(phpversion(), '5.2.0', '<'))
		$memnow=memory_get_usage();
	else
		$memnow=memory_get_usage(true);

	//need memory
	$needmemory=$memnow+$memneed;

	// increase Memory
	if ($needmemory>$memory) {
		$newmemory=round($needmemory/1024/1024)+1;
		if ($oldmem=ini_set('memory_limit', $newmemory.'M'))
			trigger_error(sprintf(__('Memory increased from %1$s to %2$s','backwpup'),$oldmem,ini_get('memory_limit')),E_USER_NOTICE);
		else
			trigger_error(sprintf(__('Can not increase Memory Limit is %1$s','backwpup'),ini_get('memory_limit')),E_USER_WARNING);
	}
	return true;
}

function maintenance_mode($enable = false) {
	if (!$_SESSION['JOB']['maintenance'])
		return;
	if ( $enable ) {
		trigger_error(__('Set Blog to Maintenance Mode','backwpup'),E_USER_NOTICE);
		if ( exists_option('wp-maintenance-mode-msqld') ) { //Support for WP Maintenance Mode Plugin
			update_option('wp-maintenance-mode-msqld','1');
		} elseif ( exists_option('plugin_maintenance-mode') ) { //Support for Maintenance Mode Plugin
			$mamo=get_option('plugin_maintenance-mode');
			$mamo['mamo_activate']='on_'.current_time('timestamp');
			$mamo['mamo_backtime_days']='0';
			$mamo['mamo_backtime_hours']='0';
			$mamo['mamo_backtime_mins']='5';
			update_option('plugin_maintenance-mode',$mamo);
		} else { //WP Support
			if (is_writable(rtrim($_SESSION['WP']['ABSPATH'],'/')))
				file_put_contents(rtrim($_SESSION['WP']['ABSPATH'],'/').'/.maintenance','<?php $upgrading = '.time().'; ?>');
			else
				trigger_error(__('Cannot set Website/Blog to Maintenance Mode! Root folder is not writeable!','backwpup'),E_USER_NOTICE);
		}
	} else {
		trigger_error(__('Set Blog to normal Mode','backwpup'),E_USER_NOTICE);
		if ( exists_option('wp-maintenance-mode-msqld') ) { //Support for WP Maintenance Mode Plugin
			update_option('wp-maintenance-mode-msqld','0');
		} elseif ( exists_option('plugin_maintenance-mode') ) { //Support for Maintenance Mode Plugin
			$mamo=get_option('plugin_maintenance-mode');
			$mamo['mamo_activate']='off';
			update_option('plugin_maintenance-mode',$mamo);
		} else { //WP Support
			@unlink(rtrim($_SESSION['WP']['ABSPATH'],'/').'/.maintenance');
		}
	}
}

function curl_progresscallback($download_size, $downloaded, $upload_size, $uploaded) {
	$_SESSION['WORKING']['STEPDONE']=$uploaded;
	update_working_file();
	return(0);
}

function get_working_dir() {
	$folder='backwpup_'.substr(md5(str_replace('\\','/',realpath(rtrim(basename(__FILE__),'/\\').'/'))),8,16).'/';
	$tempdir=getenv('TMP');
	if (!$tempdir or !is_writable($tempdir) or !is_dir($tempdir))
		$tempdir=getenv('TEMP');
	if (!$tempdir or !is_writable($tempdir) or !is_dir($tempdir))
		$tempdir=getenv('TMPDIR');
	if (!$tempdir or !is_writable($tempdir) or !is_dir($tempdir))
		$tempdir=ini_get('upload_tmp_dir');
	if (!$tempdir or empty($tempdir) or !is_writable($tempdir) or !is_dir($tempdir))
		$tempdir=sys_get_temp_dir();
	$tempdir=str_replace('\\','/',realpath(rtrim($tempdir,'/'))).'/';
	if (is_dir($tempdir.$folder) and is_writable($tempdir.$folder)) {
		return $tempdir.$folder;
	} else {
		return false;
	}
}

function get_working_file() {
	$tempdir=get_working_dir();
	if (is_file($tempdir.'.running')) {
		if ($runningfile=file_get_contents($tempdir.'.running'))
			return unserialize(trim($runningfile));
		else
			return false;
	} else {
		return false;
	}
}

function delete_working_file() {
	$tempdir=get_working_dir();
	if (is_file($tempdir.'.running')) {
		unlink($tempdir.'.running');
		return true;
	} else {
		return false;
	}
}

function update_working_file() {
	if (!file_exists($_SESSION['STATIC']['TEMPDIR'].'.running'))
		job_end();
	if (empty($_SESSION['WORKING']['MICROTIME']) or $_SESSION['WORKING']['MICROTIME']>(microtime()-500)) { //only update all 500 ms
		if ($_SESSION['WORKING']['STEPTODO']>0 and $_SESSION['WORKING']['STEPDONE']>0)
			$steppersent=round($_SESSION['WORKING']['STEPDONE']/$_SESSION['WORKING']['STEPTODO']*100);
		else
			$steppersent=1;
		if (count($_SESSION['WORKING']['STEPSDONE'])>0)
			$stepspersent=round(count($_SESSION['WORKING']['STEPSDONE'])/count($_SESSION['WORKING']['STEPS'])*100);
		else
			$stepspersent=1;
		$pid=0;
		@set_time_limit($_SESSION['CFG']['jobscriptruntime']);
		mysql_update();
		if (function_exists('posix_getpid'))
			$pid=posix_getpid();
		$runningfile=file_get_contents($_SESSION['STATIC']['TEMPDIR'].'/.running');
		$infile=unserialize(trim($runningfile));		
		file_put_contents($_SESSION['STATIC']['TEMPDIR'].'/.running',serialize(array('SID'=>session_id(),'timestamp'=>time(),'JOBID'=>$_SESSION['JOB']['jobid'],'LOGFILE'=>$_SESSION['STATIC']['LOGFILE'],'PID'=>$pid,'WARNING'=>$_SESSION['WORKING']['WARNING'],'ERROR'=>$_SESSION['WORKING']['ERROR'],'STEPSPERSENT'=>$stepspersent,'STEPPERSENT'=>$steppersent,'ABSPATH'=>$_SESSION['WP']['ABSPATH'])));
		$_SESSION['WORKING']['MICROTIME']=microtime();
	}
	return true;
}

function mysql_update() {
	global $mysqlconlink;
	if (!$mysqlconlink or !@mysql_ping($mysqlconlink)) {
		// make a mysql connection
		$mysqlconlink=mysql_connect($_SESSION['WP']['DB_HOST'], $_SESSION['WP']['DB_USER'], $_SESSION['WP']['DB_PASSWORD'], true);
		if (!$mysqlconlink) 
			trigger_error(__('No MySQL connection:','backwpup').' ' . mysql_error(),E_USER_ERROR);
		//set connecten charset
		if (!empty($_SESSION['WP']['DB_CHARSET'])) {
			if ( function_exists( 'mysql_set_charset' )) {
				mysql_set_charset( $_SESSION['WP']['DB_CHARSET'], $mysqlconlink );
			} else {
				$query = "SET NAMES '".$_SESSION['WP']['DB_CHARSET']."'";
				if (!empty($collate))
					$query .= " COLLATE '".$_SESSION['WP']['DB_COLLATE']."'";
				mysql_query($query,$mysqlconlink);
			}
		}
		//connect to database
		$mysqldblink = mysql_select_db($_SESSION['WP']['DB_NAME'], $mysqlconlink);
		if (!$mysqldblink)
			trigger_error(__('No MySQL connection to database:','backwpup').' ' . mysql_error(),E_USER_ERROR);
	}
}

//function for PHP error handling
function joberrorhandler() {
	$args = func_get_args(); // 0:errno, 1:errstr, 2:errfile, 3:errline
	$adderrorwarning=false;

	switch ($args[0]) {
	case E_NOTICE:
	case E_USER_NOTICE:
		$message="<span>".$args[1]."</span>";
		break;
	case E_WARNING:
	case E_USER_WARNING:
		$_SESSION['WORKING']['WARNING']++;
		$adderrorwarning=true;
		$message="<span class=\"warning\">".__('[WARNING]','backwpup')." ".$args[1]."</span>";
		break;
	case E_ERROR: 
	case E_USER_ERROR:
		$_SESSION['WORKING']['ERROR']++;
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

	//genrate timestamp
	$timestamp="<span class=\"timestamp\" title=\"[Line: ".$args[3]."|File: ".basename($args[2])."|Mem: ".formatbytes(@memory_get_usage(true))."|Mem Max: ".formatbytes(@memory_get_peak_usage(true))."|Mem Limit: ".ini_get('memory_limit')."]\">".date('Y-m-d H:i.s').":</span> ";
	//wirte log file
	file_put_contents($_SESSION['STATIC']['LOGFILE'], $timestamp.$message."<br />\n", FILE_APPEND);

	//write new log header
	if ($adderrorwarning) {
		$found=0;
		$fd=fopen($_SESSION['STATIC']['LOGFILE'],'r+');
		while (!feof($fd)) {
			$line=fgets($fd);
			if (stripos($line,"<meta name=\"backwpup_errors\"") !== false) {
				fseek($fd,$filepos);
				fwrite($fd,str_pad("<meta name=\"backwpup_errors\" content=\"".$_SESSION['WORKING']['ERROR']."\" />",100)."\n");
				$found++;
			}
			if (stripos($line,"<meta name=\"backwpup_warnings\"") !== false) {
				fseek($fd,$filepos);
				fwrite($fd,str_pad("<meta name=\"backwpup_warnings\" content=\"".$_SESSION['WORKING']['WARNING']."\" />",100)."\n");
				$found++;
			}
			if ($found>=2)
				break;
			$filepos=ftell($fd);
		}
		fclose($fd);
	}

	//write working file
	if (is_file($_SESSION['STATIC']['TEMPDIR'].'/.running'))
		update_working_file();

	if ($args[0]==E_ERROR or $args[0]==E_CORE_ERROR or $args[0]==E_COMPILE_ERROR) {//Die on fatal php errors.
		die();
	}
	
	//true for no more php error hadling.
	return true;
}	

//job end function
function job_end() {
	global $mysqlconlink;
	$_SESSION['WORKING']['STEPTODO']=1;
	$_SESSION['WORKING']['STEPDONE']=0;
	//delete old logs
	if (!empty($_SESSION['CFG']['maxlogs'])) {
		if ( $dir = opendir($_SESSION['CFG']['dirlogs']) ) { //make file list
			while (($file = readdir($dir)) !== false ) {
				if ('backwpup_log_' == substr($file,0,strlen('backwpup_log_')) and (".html" == substr($file,-5) or ".html.gz" == substr($file,-8)))
					$logfilelist[]=$file;
			}
			closedir( $dir );
		}
		if (sizeof($logfilelist)>0) {
			rsort($logfilelist);
			$numdeltefiles=0;
			for ($i=$_SESSION['CFG']['maxlogs'];$i<sizeof($logfilelist);$i++) {
				unlink($_SESSION['CFG']['dirlogs'].$logfilelist[$i]);
				$numdeltefiles++;
			}
			if ($numdeltefiles>0)
				trigger_error($numdeltefiles.__(' old Log files deleted!!!','backwpup'),E_USER_NOTICE);
		}
	}
	//Display job working time	
	trigger_error(sprintf(__('Job done in %1s sec.','backwpup'),time()-$_SESSION['JOB']['starttime']),E_USER_NOTICE);	
	
	if (!is_file($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile']) or !($filesize=filesize($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile']))) //Set the filezie corectly
		$filesize=0;

	//clean up temp
	if ($dir = opendir($_SESSION['STATIC']['TEMPDIR'])) {
		while (($file = readdir($dir)) !== false) {
			if (is_readable($_SESSION['STATIC']['TEMPDIR'].$file) and is_file($_SESSION['STATIC']['TEMPDIR'].$file)) {
				if ($file!='.' and $file!='..' and $file!='.running') {
					unlink($_SESSION['STATIC']['TEMPDIR'].$file);
				}
			}
		}
		closedir($dir);
	}
	
	$jobs=get_option('backwpup_jobs');
	$jobs[$_SESSION['JOB']['jobid']]['lastrun']=$jobs[$_SESSION['JOB']['jobid']]['starttime']+$_SESSION['WP']['TIMEDIFF'];
	$_SESSION['JOB']['lastrun']=$jobs[$_SESSION['JOB']['jobid']]['lastrun'];
	$jobs[$_SESSION['JOB']['jobid']]['lastruntime']=time()-$_SESSION['JOB']['starttime'];
	$_SESSION['JOB']['lastruntime']=$jobs[$_SESSION['JOB']['jobid']]['lastruntime'];
	$jobs[$_SESSION['JOB']['jobid']]['starttime']='';
	if (!empty($_SESSION['JOB']['lastbackupdownloadurl']))
		$jobs[$_SESSION['JOB']['jobid']]['lastbackupdownloadurl']=$_SESSION['JOB']['lastbackupdownloadurl'];
	else
		$jobs[$_SESSION['JOB']['jobid']]['lastbackupdownloadurl']='';
	update_option('backwpup_jobs',$jobs); //Save Settings
	
	//write heder info
	$fd=fopen($_SESSION['STATIC']['LOGFILE'],'r+');
	$found=0;
	while (!feof($fd)) {
		$line=fgets($fd);
		if (stripos($line,"<meta name=\"backwpup_jobruntime\"") !== false) {
			fseek($fd,$filepos);
			fwrite($fd,str_pad("<meta name=\"backwpup_jobruntime\" content=\"".$_SESSION['JOB']['lastruntime']."\" />",100)."\n");
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
	//Restore error handler
	restore_error_handler();
	//logfile end
	file_put_contents($_SESSION['STATIC']['LOGFILE'], "</body>\n</html>\n", FILE_APPEND);
	//gzip logfile
	if ($_SESSION['CFG']['gzlogs']) {
		$fd=fopen($_SESSION['STATIC']['LOGFILE'],'r');
		$zd=gzopen($_SESSION['STATIC']['LOGFILE'].'.gz','w9');
		while (!feof($fd)) {
			gzwrite($zd,fread($fd,4096));
		}
		gzclose($zd);
		fclose($fd);
		unlink($_SESSION['STATIC']['LOGFILE']);
		$_SESSION['STATIC']['LOGFILE']=$_SESSION['STATIC']['LOGFILE'].'.gz';
		
		$jobs=get_option('backwpup_jobs');
		$jobs[$_SESSION['JOB']['jobid']]['logfile']=$_SESSION['STATIC']['LOGFILE'];
		update_option('backwpup_jobs',$jobs); //Save Settings
	}
	
	//Send mail with log
	$sendmail=false;
	if ($_SESSION['WORKING']['ERROR']>0 and $_SESSION['JOB']['mailerroronly'] and !empty($_SESSION['JOB']['mailaddresslog']))
		$sendmail=true;
	if (!$_SESSION['JOB']['mailerroronly'] and !empty($_SESSION['JOB']['mailaddresslog']))
		$sendmail=true;
	if ($sendmail) {
		//Create PHP Mailer
		require_once($_SESSION['WP']['ABSPATH'].$_SESSION['WP']['WPINC'].'/class-phpmailer.php');
		$phpmailer = new PHPMailer();
		//Setting den methode
		if ($_SESSION['CFG']['mailmethod']=="SMTP") {
			require_once($_SESSION['WP']['ABSPATH'].$_SESSION['WP']['WPINC'].'/class-smtp.php');
			$phpmailer->Host=$_SESSION['CFG']['mailhost'];
			$phpmailer->Port=$_SESSION['CFG']['mailhostport'];
			$phpmailer->SMTPSecure=$_SESSION['CFG']['mailsecure'];
			$phpmailer->Username=$_SESSION['CFG']['mailuser'];
			$phpmailer->Password=base64_decode($_SESSION['CFG']['mailpass']);
			if (!empty($_SESSION['CFG']['mailuser']) and !empty($_SESSION['CFG']['mailpass']))
				$phpmailer->SMTPAuth=true;
			$phpmailer->IsSMTP();
		} elseif ($_SESSION['CFG']['mailmethod']=="Sendmail") {
			$phpmailer->Sendmail=$_SESSION['CFG']['mailsendmail'];
			$phpmailer->IsSendmail();
		} else {
			$phpmailer->IsMail();
		}
		
		$mailbody=__("Jobname:","backwpup")." ".$_SESSION['JOB']['name']."\n";
		$mailbody.=__("Jobtype:","backwpup")." ".$_SESSION['JOB']['type']."\n";
		if (!empty($_SESSION['WORKING']['ERROR']))
			$mailbody.=__("Errors:","backwpup")." ".$_SESSION['WORKING']['ERROR']."\n";
		if (!empty($_SESSION['WORKING']['WARNINGS']))
			$mailbody.=__("Warnings:","backwpup")." ".$_SESSION['WORKING']['WARNINGS']."\n";
		
		$phpmailer->From     = $_SESSION['CFG']['mailsndemail'];
		$phpmailer->FromName = $_SESSION['CFG']['mailsndname'];
		$phpmailer->AddAddress($_SESSION['JOB']['mailaddresslog']);
		$phpmailer->Subject  =  __('BackWPup Log from','backwpup').' '.date('Y-m-d H:i',$_SESSION['JOB']['starttime']).': '.$_SESSION['JOB']['name'];
		$phpmailer->IsHTML(false);
		$phpmailer->Body  =  $mailbody;
		$phpmailer->AddAttachment($_SESSION['STATIC']['LOGFILE']);
		$phpmailer->Send();
	}

	$_SESSION['WORKING']['STEPDONE']=1;
	$_SESSION['WORKING']['STEPSDONE'][]='JOB_END'; //set done
	if (is_file($_SESSION['STATIC']['TEMPDIR'].'/.running')) {
		update_working_file();
		unlink($_SESSION['STATIC']['TEMPDIR'].'/.running');
	}
	//Destroy session
	$_SESSION = array();
	session_destroy();
	mysql_close($mysqlconlink);
	die();
}

// execute on script job shutdown
function job_shutdown() {
	if (empty($_SESSION['STATIC']['LOGFILE'])) //nothing on empy session
		return;
	$_SESSION['WORKING']['RESTART']++;
	if ($_SESSION['WORKING']['RESTART']>=$_SESSION['CFG']['jobscriptretry'] and file_exists($_SESSION['STATIC']['TEMPDIR'].'/.running')) {  //only x restarts allowed
		file_put_contents($_SESSION['STATIC']['LOGFILE'], "<span class=\"timestamp\" title=\"[Line: ".__LINE__."|File: ".basename(__FILE__)."\">".date('Y-m-d H:i.s').":</span> <span class=\"error\">[ERROR]".__('To many restarts....','backwpup')."</span><br />\n", FILE_APPEND);
		$_SESSION['WORKING']['ERROR']++;
		$fd=fopen($_SESSION['STATIC']['LOGFILE'],'r+');
		while (!feof($fd)) {
			$line=fgets($fd);
			if (stripos($line,"<meta name=\"backwpup_errors\"") !== false) {
				fseek($fd,$filepos);
				fwrite($fd,str_pad("<meta name=\"backwpup_errors\" content=\"".$_SESSION['WORKING']['ERROR']."\" />",100)."\n");
				break;
			}
			$filepos=ftell($fd);
		}
		fclose($fd);
		job_end();
	}
	//Put last error to log if one
	$lasterror=error_get_last();
	if ($lasterror['type']==E_ERROR or $lasterror['type']==E_PARSE or $lasterror['type']==E_CORE_ERROR or $lasterror['type']==E_COMPILE_ERROR) {
		file_put_contents($_SESSION['STATIC']['LOGFILE'], "<span class=\"timestamp\" title=\"[Line: ".$lasterror['line']."|File: ".basename($lasterror['file'])."\">".date('Y-m-d H:i.s').":</span> <span class=\"error\">[ERROR]".$lasterror['message']."</span><br />\n", FILE_APPEND);
		//write new log header
		$_SESSION['WORKING']['ERROR']++;
		$fd=fopen($_SESSION['STATIC']['LOGFILE'],'r+');
		while (!feof($fd)) {
			$line=fgets($fd);
			if (stripos($line,"<meta name=\"backwpup_errors\"") !== false) {
				fseek($fd,$filepos);
				fwrite($fd,str_pad("<meta name=\"backwpup_errors\" content=\"".$_SESSION['WORKING']['ERROR']."\" />",100)."\n");
				break;
			}
			$filepos=ftell($fd);
		}
		fclose($fd);
	}
	//Close session
	session_write_close();
	//Excute jobrun again
	if (!file_exists($_SESSION['STATIC']['TEMPDIR'].'/.running'))
		return;
	file_put_contents($_SESSION['STATIC']['LOGFILE'], "<span class=\"timestamp\" title=\"[Line: ".__LINE__."|File: ".basename(__FILE__)."|Mem: ".formatbytes(@memory_get_usage(true))."|Mem Max: ".formatbytes(@memory_get_peak_usage(true))."|Mem Limit: ".ini_get('memory_limit')."]\">".date('Y-m-d H:i.s').":</span> <span>".$_SESSION['WORKING']['RESTART'].'. '.__('Script stop! Will started again now!','backwpup')."</span><br />\n", FILE_APPEND);
	$ch=curl_init();
	curl_setopt($ch,CURLOPT_URL,$_SESSION['STATIC']['JOBRUNURL']);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,false);
	curl_setopt($ch,CURLOPT_FORBID_REUSE,true);
	curl_setopt($ch,CURLOPT_FRESH_CONNECT,true);
	curl_setopt($ch,CURLOPT_TIMEOUT,0.01);
	curl_exec($ch);
	curl_close($ch);
}
?>