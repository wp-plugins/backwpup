<?PHP
// don't load directly
if ( !defined('BACKWPUP_JOBRUN_FILE') )
	die('-1');
	
//For Later Translation ;)
function __($message,$domain='backwpup') {
	$msgid=md5($message);
	if (!empty($_SESSION['TRANSLATE'][$msgid]))
		$message=$_SESSION['TRANSLATE'][$msgid];
	return $message;
}

//For Later Translation ;)
function _e($message,$domain='backwpup') {
	$msgid=md5($message);
	if (!empty($_SESSION['TRANSLATE'][$msgid]))
		$message=$_SESSION['TRANSLATE'][$msgid];
	echo $message;
}

function read_logheader() {
	$headers=array("backwpup_version" => "version","backwpup_logtime" => "logtime","backwpup_errors" => "errors","backwpup_warnings" => "warnings","backwpup_jobid" => "jobid","backwpup_jobname" => "name","backwpup_jobtype" => "type","backwpup_jobruntime" => "runtime","backwpup_backupfilesize" => "backupfilesize");
	//Read file
	if (strtolower(substr($_SESSION['STATIC']['LOGFILE'],-3))==".gz") {
		$fp = gzopen( $_SESSION['STATIC']['LOGFILE'], 'r' );
		$file_data = gzread( $fp, 1536 ); // Pull only the first 1,5kiB of the file in.
		gzclose( $fp );
	} else {
		$fp = fopen( $_SESSION['STATIC']['LOGFILE'], 'r' );
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
		$joddata['logtime']=filectime($_SESSION['STATIC']['LOGFILE']);

	return $joddata;
}

function exists_option($option='backwpup_jobs') {
	$query="SELECT option_value as value FROM ".$_SESSION['WP']['OPTIONS_TABLE']." WHERE option_name='".trim($option)."' LIMIT 1";
	$res=mysql_query($query);
	if (!$res or mysql_num_rows($res)<1) {
		return false;
	}
	return true;
}

function get_option($option='backwpup_jobs') {
	$query="SELECT option_value FROM ".$_SESSION['WP']['OPTIONS_TABLE']." WHERE option_name='".trim($option)."' LIMIT 1";
	$res=mysql_query($query);
	if (!$res) {
		trigger_error(sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), mysql_error(), $query),E_USER_ERROR);
		return false;
	}
	return unserialize(mysql_result($res,0));
}

function update_option($option='backwpup_jobs',$data) {
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
			$fdmain=fopen(trailingslashit(ABSPATH).'.maintenance','w');
			fwrite($fdmain,'<?php $upgrading = ' . time() . '; ?>');
			fclose($fdmain);
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
			@unlink(trailingslashit(ABSPATH).'.maintenance');
		}
	}
}

function update_working_file() {
	$fd=fopen($_SESSION['STATIC']['TEMPDIR'].'.backwpup_running','w');
	fwrite($fd,serialize(array('SID'=>session_id(),'timestamp'=>time(),'JOBID'=>$_SESSION['JOB']['ID'],'LOGFILE'=>$_SESSION['STATIC']['LOGFILE'])));
	fclose($fd);
}

//function for PHP error handling
function joberrorhandler() {
	$args = func_get_args(); // 0:errno, 1:errstr, 2:errfile, 3:errline
	//genrate timestamp
	$timestamp="<span class=\"timestamp\" title=\"[Line: ".$args[3]."|File: ".basename($args[2])."|Mem: ".formatbytes(@memory_get_usage(true))."|Mem Max: ".formatbytes(@memory_get_peak_usage(true))."|Mem Limit: ".ini_get('memory_limit')."]\">".date('Y-m-d H:i.s').":</span> ";

	switch ($args[0]) {
	case E_NOTICE:
	case E_USER_NOTICE:
		$massage=$timestamp."<span>".$args[1]."</span>";
		break;
	case E_WARNING:
	case E_USER_WARNING:
		$logheader=read_logheader(); //read warnig count from log header
		$warnings=$logheader['warnings']+1;
		$massage=$timestamp."<span class=\"warning\">".__('[WARNING]','backwpup')." ".$args[1]."</span>";
		break;
	case E_ERROR: 
	case E_USER_ERROR:
		$logheader=read_logheader(); //read error count from log header
		$errors=$logheader['errors']+1;
		$massage=$timestamp."<span class=\"error\">".__('[ERROR]','backwpup')." ".$args[1]."</span>";
		break;
	case E_DEPRECATED:
	case E_USER_DEPRECATED:
		$massage=$timestamp."<span>".__('[DEPRECATED]','backwpup')." ".$args[1]."</span>";
		break;
	case E_STRICT:
		$massage=$timestamp."<span>".__('[STRICT NOTICE]','backwpup')." ".$args[1]."</span>";
		break;
	case E_RECOVERABLE_ERROR:
		$massage=$timestamp."<span>".__('[RECOVERABLE ERROR]','backwpup')." ".$args[1]."</span>";
		break;
	default:
		$massage=$timestamp."<span>[".$args[0]."] ".$args[1]."</span>";
		break;
	}

	//wirte log file
	$fd=fopen($_SESSION['STATIC']['LOGFILE'],'a');
	fwrite($fd,$massage."<br />\n");
	fclose($fd);

	//write new log header
	if (isset($errors) or isset($warnings)) {
		$fd=fopen($_SESSION['STATIC']['LOGFILE'],'r+');
		while (!feof($fd)) {
			$line=fgets($fd);
			if (stripos($line,"<meta name=\"backwpup_errors\"") !== false and isset($errors)) {
				fseek($fd,$filepos);
				fwrite($fd,str_pad("<meta name=\"backwpup_errors\" content=\"".$errors."\" />",100)."\n");
				break;
			}
			if (stripos($line,"<meta name=\"backwpup_warnings\"") !== false and isset($warnings)) {
				fseek($fd,$filepos);
				fwrite($fd,str_pad("<meta name=\"backwpup_warnings\" content=\"".$warnings."\" />",100)."\n");
				break;
			}
			$filepos=ftell($fd);
		}
		fclose($fd);
	}

	//write working file
	if (is_file($_SESSION['STATIC']['TEMPDIR'].'.backwpup_running')) {
		update_working_file();
	} else {
		$_SESSION['WORKING']['ACTIVE_STEP']='JOB_END';
	}	

	if ($args[0]==E_ERROR or $args[0]==E_CORE_ERROR or $args[0]==E_COMPILE_ERROR) {//Die on fatal php errors.
		$_SESSION['WORKING']['GOTO']='NEXT';
		die();
	}
	
	//true for no more php error hadling.
	return true;
}	

//job end function
function job_end() {
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

	//clean up
	if (is_file($_SESSION['STATIC']['TEMPDIR'].'.backwpup_running')) 
		unlink($_SESSION['STATIC']['TEMPDIR'].'.backwpup_running');
	else
		trigger_error(__('Job aborted by user','backwpup'),E_USER_ERROR);
	if (is_file($_SESSION['STATIC']['TEMPDIR'].$_SESSION['WP']['DB_NAME'].'.sql'))
		unlink($_SESSION['STATIC']['TEMPDIR'].$_SESSION['WP']['DB_NAME'].'.sql');
	if (is_file($_SESSION['STATIC']['TEMPDIR'].$_SESSION['WP']['DB_NAME'].'.sql'))	
		unlink($_SESSION['STATIC']['TEMPDIR'].preg_replace( '/[^a-z0-9_\-]/', '', strtolower($_SESSION['WP']['BLOGNAME'])).'.wordpress.' . date( 'Y-m-d' ) . '.xml');
	if ($_SESSION['JOB']['backupdir']!=$_SESSION['STATIC']['TEMPDIR'] and is_file($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile'])) 
		unlink($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile']);
	
	$jobs=get_option('backwpup_jobs');
	$jobs[$_SESSION['JOB']['ID']]['lastrun']=$jobs[$_SESSION['JOB']['ID']]['starttime']+$_SESSION['WP']['TIMEDIFF'];
	$_SESSION['JOB']['lastrun']=$jobs[$_SESSION['JOB']['ID']]['lastrun'];
	$jobs[$_SESSION['JOB']['ID']]['lastruntime']=time()-$_SESSION['JOB']['starttime'];
	$_SESSION['JOB']['lastruntime']=$jobs[$_SESSION['JOB']['ID']]['lastruntime'];
	$jobs[$_SESSION['JOB']['ID']]['starttime']='';
	if (!empty($_SESSION['JOB']['lastbackupdownloadurl']))
		$jobs[$_SESSION['JOB']['ID']]['lastbackupdownloadurl']=$_SESSION['JOB']['lastbackupdownloadurl'];
	else
		$jobs[$_SESSION['JOB']['ID']]['lastbackupdownloadurl']='';
	update_option('backwpup_jobs',$jobs); //Save Settings
	$_SESSION['WORKING']['FINISHED']=true;
	
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
	$fd=fopen($_SESSION['STATIC']['LOGFILE'],'a');
	fwrite($fd,"</body>\n</html>\n");
	fclose($fd);
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
		$jobs[$_SESSION['JOB']['ID']]['logfile']=$_SESSION['STATIC']['LOGFILE'];
		update_option('backwpup_jobs',$jobs); //Save Settings
	}
	
	$logdata=read_logheader();
	//Send mail with log
	$sendmail=false;
	if ($logdata['errors']>0 and $_SESSION['JOB']['mailerroronly'] and !empty($_SESSION['JOB']['mailaddresslog']))
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
			$smtpport=25;
			$smtphost=$_SESSION['CFG']['mailhost'];
			if (false !== strpos($_SESSION['CFG']['mailhost'],':')) //look for port
				list($smtphost,$smtpport)=explode(':',$_SESSION['CFG']['mailhost'],2);
			$phpmailer->Host=$smtphost;
			$phpmailer->Port=$smtpport;
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
		
		$mailbody=__("Jobname:","backwpup")." ".$logdata['name']."\n";
		$mailbody.=__("Jobtype:","backwpup")." ".$logdata['type']."\n";
		if (!empty($logdata['errors']))
			$mailbody.=__("Errors:","backwpup")." ".$logdata['errors']."\n";
		if (!empty($logdata['warnings']))
			$mailbody.=__("Warnings:","backwpup")." ".$logdata['warnings']."\n";
		
		$phpmailer->From     = $_SESSION['CFG']['mailsndemail'];
		$phpmailer->FromName = $_SESSION['CFG']['mailsndname'];
		$phpmailer->AddAddress($_SESSION['JOB']['mailaddresslog']);
		$phpmailer->Subject  =  __('BackWPup Log from','backwpup').' '.date('Y-m-d H:i',$_SESSION['JOB']['starttime']).': '.$_SESSION['JOB']['name'];
		$phpmailer->IsHTML(false);
		$phpmailer->Body  =  $mailbody;
		$phpmailer->AddAttachment($_SESSION['STATIC']['LOGFILE']);
		$phpmailer->Send();
	}
	
	//Destroy session
	$_SESSION = array();
	if (ini_get("session.use_cookies")) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000, $params["path"],
			$params["domain"], $params["secure"], $params["httponly"]
		);
	}
	session_destroy();
}

// execute on script job shutdown
function job_shutdown() {
	//Put last error to log if one
	$lasterror=error_get_last();
	if ($lasterror['type']==E_ERROR) {
		$_SESSION['WORKING']['GOTO']='NEXT';
		$fd=fopen($_SESSION['STATIC']['LOGFILE'],'a');
		fwrite($fd,"<span style=\"background-color:c3c3c3;\" title=\"[Line: ".$lasterror['line']."|File: ".basename($lasterror['file'])."\">".date('Y-m-d H:i.s').":</span> <span style=\"background-color:red;\">[ERROR]".$lasterror['message']."</span><br />\n");
		fclose($fd);
		//write new log header
		$logheader=read_logheader();
		$errors=$logheader['errors']+1;
		$fd=fopen($_SESSION['STATIC']['LOGFILE'],'r+');
		while (!feof($fd)) {
			$line=fgets($fd);
			if (stripos($line,"<meta name=\"backwpup_errors\"") !== false and isset($errors)) {
				fseek($fd,$filepos);
				fwrite($fd,str_pad("<meta name=\"backwpup_errors\" content=\"".$errors."\" />",100)."\n");
				break;
			}
			$filepos=ftell($fd);
		}
		fclose($fd);
	}
	//Close session
	$BackWPupSession=session_id();
	session_write_close();
	//Excute jobrun again
	if (!file_exists($_SESSION['STATIC']['TEMPDIR'].'.backwpup_running'))
		return;
	$fd=fopen($_SESSION['STATIC']['LOGFILE'],'a');
	fwrite($fd,"<span style=\"background-color:c3c3c3;\" title=\"[Line: ".__LINE__."|File: ".basename(__FILE__)."|Mem: ".formatbytes(@memory_get_usage(true))."|Mem Max: ".formatbytes(@memory_get_peak_usage(true))."|Mem Limit: ".ini_get('memory_limit')."]\">".date('Y-m-d H:i.s').":</span> <span>".__('Script stopped will started again now!','backwpup')."</span><br />\n");
	fclose($fd);
	$ch=curl_init();
	curl_setopt($ch,CURLOPT_URL,$_SESSION['STATIC']['JOBRUNURL']);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,false);
	curl_setopt($ch,CURLOPT_FORBID_REUSE,true);
	curl_setopt($ch,CURLOPT_FRESH_CONNECT,true);
	curl_setopt($ch,CURLOPT_POST,true);
	curl_setopt($ch,CURLOPT_POSTFIELDS,array('BackWPupSession'=>$BackWPupSession));
	curl_setopt($ch,CURLOPT_TIMEOUT,0.01);
	curl_exec($ch);
	curl_close($ch);
}
?>