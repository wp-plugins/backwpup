<?PHP
// don't load directly
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}
	
function backwpup_jobstart($jobid='') {
	global $wpdb;
	$jobid=(int)trim($jobid);
	if (empty($jobid) or !is_integer($jobid)) {
		return false;
	}
	//check if a job running
	if ($infile=backwpup_get_working_file()) {
		if ($infile['timestamp']<time()-1800) {
			_e("A job already running!","backwpup");
			return false;
		} else { //delete working file job thing it not works longer.
			unlink(backwpup_get_temp().'.running');
			sleep(3);
		}
	}
	// set the cache limiter to 'nocache'
	session_cache_limiter('nocache');
	// set the cache expire to 30 minutes 
	session_cache_expire(30);
	// give the session a name
	session_name('BackWPupSession');
	//delete session cookie
	session_set_cookie_params(0);
	// start session
	session_start();
	//get session id
	$backwpupsid=session_id();
	//clean session
	$_SESSION = array();
	//Set needed WP vars to Session
	$_SESSION['WP']['DB_NAME']=DB_NAME;
	$_SESSION['WP']['DB_USER']=DB_USER;
	$_SESSION['WP']['DB_PASSWORD']=DB_PASSWORD;
	$_SESSION['WP']['DB_HOST']=DB_HOST;
	$_SESSION['WP']['DB_CHARSET']=DB_CHARSET;
	$_SESSION['WP']['DB_COLLATE']=DB_COLLATE;
	$_SESSION['WP']['OPTIONS_TABLE']=$wpdb->options;
	$_SESSION['WP']['TABLE_PREFIX']=$wpdb->prefix;
	$_SESSION['WP']['WP_DEBUG']=WP_DEBUG;
	$_SESSION['WP']['BLOGNAME']=get_bloginfo('name');
	if (defined('WP_SITEURL'))
		$_SESSION['WP']['SITEURL']=trailingslashit(WP_SITEURL);
	else
		$_SESSION['WP']['SITEURL']=trailingslashit(get_option('siteurl'));
	$_SESSION['WP']['TIMEDIFF']=current_time('timestamp')-time();
	$_SESSION['WP']['WPLANG']=WPLANG;
	//timezone
	$_SESSION['WP']['GMTOFFSET']=get_option('gmt_offset');
	$_SESSION['WP']['TIMEZONE']=get_option('timezone_string');
	if (empty($_SESSION['WP']['TIMEZONE'])) { // Create a UTC+- zone if no timezone string exists
		if ( 0 == $_SESSION['WP']['GMTOFFSET'] )
			$_SESSION['WP']['TIMEZONE'] = 'UTC+0';
		elseif ($_SESSION['WP']['GMTOFFSET'] < 0)
			$_SESSION['WP']['TIMEZONE'] = 'UTC' . $_SESSION['WP']['GMTOFFSET'];
		else
			$_SESSION['WP']['TIMEZONE'] = 'UTC+' . $_SESSION['WP']['GMTOFFSET'];
	}
	//WP folder
	$_SESSION['WP']['ABSPATH']=rtrim(str_replace('\\','/',ABSPATH),'/').'/';
	$_SESSION['WP']['WP_CONTENT_DIR']=rtrim(str_replace('\\','/',WP_CONTENT_DIR),'/').'/';
	$_SESSION['WP']['WP_PLUGIN_DIR']=rtrim(str_replace('\\','/',WP_PLUGIN_DIR),'/').'/';
	$_SESSION['WP']['WP_THEMES_DIR']=rtrim(str_replace('\\','/',trailingslashit(WP_CONTENT_DIR).'themes/'),'/').'/';
	$_SESSION['WP']['WP_UPLOAD_DIR']=rtrim(str_replace('\\','/',backwpup_get_upload_dir()),'/').'/';
	$_SESSION['WP']['WPINC']=WPINC;
	$_SESSION['WP']['MULTISITE']=is_multisite();
	$_SESSION['WP']['ADMINURL']=admin_url('admin.php');
	//Load Translation
	if (!empty($_SESSION['WP']['WPLANG']) and is_file(dirname(__FILE__).'/../lang/backwpup-'.$_SESSION['WP']['WPLANG'].'.po')) {
		$file = fopen (dirname(__FILE__).'/../lang/backwpup-'.$_SESSION['WP']['WPLANG'].'.po', "r");
		while (!feof($file)){
			$line = trim(fgets($file));
			if (substr($line,0,7)=='msgid "') {
				$msgid=md5(substr($line,7,-1));
				$msgstr=substr(trim(fgets($file)),8,-1);
				$_SESSION['TRANSLATE'][$msgid]=$msgstr;
			}
		}
		fclose($file);
	}
	//Set plugin data
	$_SESSION['BACKWPUP']['PLUGIN_BASEDIR']=BACKWPUP_PLUGIN_BASEDIR;
	$_SESSION['BACKWPUP']['VERSION']=BACKWPUP_VERSION;
	$_SESSION['BACKWPUP']['BACKWPUP_DESTS']=BACKWPUP_DESTS;
	$_SESSION['BACKWPUP']['DROPBOX_APP_KEY']=BACKWPUP_DROPBOX_APP_KEY;
	$_SESSION['BACKWPUP']['DROPBOX_APP_SECRET']=BACKWPUP_DROPBOX_APP_SECRET;
	$_SESSION['BACKWPUP']['SUGARSYNC_ACCESSKEY']=BACKWPUP_SUGARSYNC_ACCESSKEY;
	$_SESSION['BACKWPUP']['SUGARSYNC_PRIVATEACCESSKEY']=BACKWPUP_SUGARSYNC_PRIVATEACCESSKEY;
	//Set config data
	$_SESSION['CFG']=get_option('backwpup');
	//Check working times
	if (empty($_SESSION['CFG']['jobstepretry']) or !is_int($_SESSION['CFG']['jobstepretry']) or $_SESSION['CFG']['jobstepretry']>100)
		$_SESSION['CFG']['jobstepretry']=3;
	if (empty($_SESSION['CFG']['jobscriptretry']) or !is_int($_SESSION['CFG']['jobscriptretry']) or $_SESSION['CFG']['jobscriptretry']>100)
		$_SESSION['CFG']['jobscriptretry']=5;
	if (empty($_SESSION['CFG']['jobscriptruntime']) or !is_int($_SESSION['CFG']['jobscriptruntime']) or $_SESSION['CFG']['jobscriptruntime']>100)
		$_SESSION['CFG']['jobscriptruntime']=30;
	if (empty($_SESSION['CFG']['jobscriptruntimelong']) or !is_int($_SESSION['CFG']['jobscriptruntimelong']) or $_SESSION['CFG']['jobscriptruntimelong']>1000)
		$_SESSION['CFG']['jobscriptruntimelong']=300;
	//Set job data
	$_SESSION['JOB']=backwpup_get_job_vars($jobid);
	//STATIC data
	$_SESSION['STATIC']['JOBRUNURL']=BACKWPUP_PLUGIN_BASEURL.'/job/job_run.php';
	//get and create temp dir
	$_SESSION['STATIC']['TEMPDIR']=backwpup_get_temp();
	if (!is_dir($_SESSION['STATIC']['TEMPDIR'])) {
		if (!mkdir(rtrim($_SESSION['STATIC']['TEMPDIR'],'/'),0777,true)) {
			sprintf(__('Can not create temp folder: %1$s','backwpup'),$_SESSION['STATIC']['TEMPDIR']);
			return false;
		}		
	}
	if (!is_writable($_SESSION['STATIC']['TEMPDIR'])) {
		_e("Temp dir not writeable","backwpup");
		session_destroy();
		return false;
	} else {  //clean up old temp files
		if ($dir = opendir($_SESSION['STATIC']['TEMPDIR'])) {
			while (($file = readdir($dir)) !== false) {
				if (is_readable($_SESSION['STATIC']['TEMPDIR'].$file) and is_file($_SESSION['STATIC']['TEMPDIR'].$file)) {
					if ($file!='.' and $file!='..') {
						unlink($_SESSION['STATIC']['TEMPDIR'].$file);
					}
				}
			}
			closedir($dir);
		}
		//create .htaccess for apache and index.html for other
		if (strtolower(substr($_SERVER["SERVER_SOFTWARE"],0,6))=="apache") {  //check if it a apache webserver
			if (!is_file($_SESSION['STATIC']['TEMPDIR'].'.htaccess')) 
				file_put_contents($_SESSION['STATIC']['TEMPDIR'].'.htaccess',"Order allow,deny\ndeny from all");
		} else {
			if (!is_file($_SESSION['STATIC']['TEMPDIR'].'index.html')) 
				file_put_contents($_SESSION['STATIC']['TEMPDIR'].'index.html',"\n");
			if (!is_file($_SESSION['STATIC']['TEMPDIR'].'index.php'))
				file_put_contents($_SESSION['STATIC']['TEMPDIR'].'index.php',"\n");
		}	
	}
	$_SESSION['CFG']['dirlogs']=rtrim(str_replace('\\','/',$_SESSION['CFG']['dirlogs']),'/').'/'; 
	if (!is_dir($_SESSION['CFG']['dirlogs'])) {
		if (!mkdir(rtrim($_SESSION['CFG']['dirlogs'],'/'),0777,true)) {
			sprintf(__('Can not create folder for log files: %1$s','backwpup'),$_SESSION['CFG']['dirlogs']);
			return false;
		}
		//create .htaccess for apache and index.html for other
		if (strtolower(substr($_SERVER["SERVER_SOFTWARE"],0,6))=="apache") {  //check if it a apache webserver
			if (!is_file($_SESSION['CFG']['dirlogs'].'.htaccess')) 
				file_put_contents($_SESSION['CFG']['dirlogs'].'.htaccess',"Order allow,deny\ndeny from all");
		} else {
			if (!is_file($_SESSION['CFG']['dirlogs'].'index.html')) 
				file_put_contents($_SESSION['CFG']['dirlogs'].'index.html',"\n");
			if (!is_file($_SESSION['CFG']['dirlogs'].'index.php'))
				file_put_contents($_SESSION['CFG']['dirlogs'].'index.php',"\n");
		}			
	}
	if (!is_writable($_SESSION['CFG']['dirlogs'])) {
		_e("Log folder not writeable!","backwpup");
		session_destroy();
		return false;
	}
	//check exists gzip functions
	if(!function_exists('gzopen'))
		$_SESSION['CFG']['gzlogs']=false;
	//set Logfile
	$_SESSION['STATIC']['LOGFILE']=$_SESSION['CFG']['dirlogs'].'backwpup_log_'.date_i18n('Y-m-d_H-i-s').'.html';
	//create log file
	$fd=fopen($_SESSION['STATIC']['LOGFILE'],'w');
	//Create log file header
	fwrite($fd,"<html>\n<head>\n");
	fwrite($fd,"<meta name=\"backwpup_version\" content=\"".BACKWPUP_VERSION."\" />\n");
	fwrite($fd,"<meta name=\"backwpup_logtime\" content=\"".current_time('timestamp')."\" />\n");
	fwrite($fd,str_pad("<meta name=\"backwpup_errors\" content=\"0\" />",100)."\n");
	fwrite($fd,str_pad("<meta name=\"backwpup_warnings\" content=\"0\" />",100)."\n");
	fwrite($fd,"<meta name=\"backwpup_jobid\" content=\"".$_SESSION['JOB']['jobid']."\" />\n");
	fwrite($fd,"<meta name=\"backwpup_jobname\" content=\"".$_SESSION['JOB']['name']."\" />\n");
	fwrite($fd,"<meta name=\"backwpup_jobtype\" content=\"".$_SESSION['JOB']['type']."\" />\n");
	fwrite($fd,str_pad("<meta name=\"backwpup_backupfilesize\" content=\"0\" />",100)."\n");
	fwrite($fd,str_pad("<meta name=\"backwpup_jobruntime\" content=\"0\" />",100)."\n");
	fwrite($fd,"<style type=\"text/css\">\n");
	fwrite($fd,".timestamp {background-color:grey;}\n");
	fwrite($fd,".warning {background-color:yellow;}\n");
	fwrite($fd,".error {background-color:red;}\n");
	fwrite($fd,"#body {font-family:monospace;font-size:12px;white-space:nowrap;}\n");
	fwrite($fd,"</style>\n");
	fwrite($fd,"<title>".sprintf(__('BackWPup Log for %1$s from %2$s at %3$s','backwpup'),$_SESSION['JOB']['name'],date_i18n(get_option('date_format')),date_i18n(get_option('time_format')))."</title>\n</head>\n<body id=\"body\">\n");
	fclose($fd);
	//write working file
	file_put_contents($_SESSION['STATIC']['TEMPDIR'].'.running',serialize(array('SID'=>$backwpupsid,'timestamp'=>time(),'JOBID'=>$_SESSION['JOB']['jobid'],'LOG'=>'','LOGFILE'=>$_SESSION['STATIC']['LOGFILE'],'WARNING'=>0,'ERROR'=>0,'STEPSPERSENT'=>0,'STEPPERSENT'=>0)));
	//Set job start settings
	$jobs=get_option('backwpup_jobs');
	$jobs[$_SESSION['JOB']['jobid']]['starttime']=time(); //set start time for job
	$_SESSION['JOB']['starttime']=$jobs[$_SESSION['JOB']['jobid']]['starttime'];
	$jobs[$_SESSION['JOB']['jobid']]['logfile']=$_SESSION['STATIC']['LOGFILE'];	   //Set current logfile
	$jobs[$_SESSION['JOB']['jobid']]['cronnextrun']=backwpup_cron_next($jobs[$_SESSION['JOB']['jobid']]['cron']);  //set next run
	$_SESSION['JOB']['cronnextrun']=$jobs[$_SESSION['JOB']['jobid']]['cronnextrun'];
	$jobs[$_SESSION['JOB']['jobid']]['lastbackupdownloadurl']='';
	$_SESSION['JOB']['lastbackupdownloadurl']='';
	update_option('backwpup_jobs',$jobs); //Save job Settings	
	//Set todo
	$_SESSION['STATIC']['TODO']=explode('+',$_SESSION['JOB']['type']);
	//only for jos that makes backups
	if (in_array('FILE',$_SESSION['STATIC']['TODO']) or in_array('DB',$_SESSION['STATIC']['TODO']) or in_array('WPEXP',$_SESSION['STATIC']['TODO'])) {
		//make emty file list
		$_SESSION['WORKING']['FILELIST']=array();
		$_SESSION['WORKING']['ALLFILESIZE']=0;
		//set Backup Dir if not set
		if (empty($_SESSION['JOB']['backupdir'])) {
			$_SESSION['JOB']['backupdir']=$_SESSION['STATIC']['TEMPDIR'];
		} else {
			//clear path
			$_SESSION['JOB']['backupdir']=rtrim(str_replace('\\','/',$_SESSION['JOB']['backupdir']),'/').'/'; 
			//create backup dir if it not exists
			if (!is_dir($_SESSION['JOB']['backupdir'])) {
				if (!mkdir(rtim($_SESSION['JOB']['backupdir'],'/'),0777,true)) {
					sprintf(__('Can not create folder for backup files: %1$s','backwpup'),$_SESSION['JOB']['backupdir']);
					return false;
				}
				//create .htaccess for apache and index.html for other
				if (strtolower(substr($_SERVER["SERVER_SOFTWARE"],0,6))=="apache") {  //check if it a apache webserver
					if (!is_file($_SESSION['JOB']['backupdir'].'.htaccess')) 
						file_put_contents($_SESSION['JOB']['backupdir'].'.htaccess',"Order allow,deny\ndeny from all");
				} else {
					if (!is_file($_SESSION['JOB']['backupdir'].'index.html')) 
						file_put_contents($_SESSION['JOB']['backupdir'].'index.html',"\n");
					if (!is_file($_SESSION['JOB']['backupdir'].'index.php'))
						file_put_contents($_SESSION['JOB']['backupdir'].'index.php',"\n");
				}			
			}
		}
		//check backup dir				
		if (!is_writable($_SESSION['JOB']['backupdir'])) {
			_e("Backup folder not writeable!","backwpup");
			session_destroy();		
			return false;
		}
		//set Backup file Name
		$_SESSION['STATIC']['backupfile']=$_SESSION['JOB']['fileprefix'].date_i18n('Y-m-d_H-i-s').$_SESSION['JOB']['fileformart'];
	}
	//set ERROR and WARNINGS counter
	$_SESSION['WORKING']['WARNING']=0;
	$_SESSION['WORKING']['ERROR']=0;
	$_SESSION['WORKING']['RESTART']=0;
	$_SESSION['WORKING']['STEPSDONE']=array();
	$_SESSION['WORKING']['STEPTODO']=0;
	$_SESSION['WORKING']['STEPDONE']=0;
	//build working steps
	$_SESSION['WORKING']['STEPS']=array();
	//setup job steps
	if (in_array('DB',$_SESSION['STATIC']['TODO']))
		$_SESSION['WORKING']['STEPS'][]='DB_DUMP';
	if (in_array('WPEXP',$_SESSION['STATIC']['TODO']))
		$_SESSION['WORKING']['STEPS'][]='WP_EXPORT';
	if (in_array('FILE',$_SESSION['STATIC']['TODO']))
		$_SESSION['WORKING']['STEPS'][]='FILE_LIST';
	if (in_array('DB',$_SESSION['STATIC']['TODO']) or in_array('WPEXP',$_SESSION['STATIC']['TODO']) or in_array('FILE',$_SESSION['STATIC']['TODO'])) {
		$_SESSION['WORKING']['STEPS'][]='BACKUP_CREATE';		
		//ADD Destinations
		$_SESSION['WORKING']['STEPS'][]='DEST_FOLDER';
		$_SESSION['WORKING']['STEPS'][]='DEST_MAIL';
		$dests=explode(',',strtoupper(BACKWPUP_DESTS));
		foreach($dests as $dest)
			$_SESSION['WORKING']['STEPS'][]='DEST_'.strtoupper($dest);
	}
	if (in_array('CHECK',$_SESSION['STATIC']['TODO']))
		$_SESSION['WORKING']['STEPS'][]='DB_CHECK';
	if (in_array('OPTIMIZE',$_SESSION['STATIC']['TODO']))
		$_SESSION['WORKING']['STEPS'][]='DB_OPTIMIZE';	
	$_SESSION['WORKING']['STEPS'][]='JOB_END';
	//mark all as not done
	foreach($_SESSION['WORKING']['STEPS'] as $step) 
		$_SESSION['WORKING'][$step]['DONE']=false;
	//Close session
	session_write_close();
	//Run job
	$ch=curl_init();
	curl_setopt($ch,CURLOPT_URL,$_SESSION['STATIC']['JOBRUNURL']);
	//curl_setopt($ch,CURLOPT_COOKIESESSION, true);
	//curl_setopt($ch,CURLOPT_COOKIE,'BackWPupSession='.$backwpupsid.'; path='.ini_get('session.cookie_path'));
	curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch,CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,false);
	curl_setopt($ch,CURLOPT_FORBID_REUSE,true);
	curl_setopt($ch,CURLOPT_FRESH_CONNECT,true);
	curl_setopt($ch,CURLOPT_TIMEOUT,0.01);
	curl_exec($ch);
	curl_close($ch);
	return $_SESSION['STATIC']['LOGFILE'];
}
?>