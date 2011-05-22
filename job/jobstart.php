<?PHP
// don't load directly
if ( !defined('ABSPATH') )
	die('-1');
	
function backwpup_jobstart($jobid='') {
	global $wpdb;
	$jobid=(int)trim($jobid);
	if (empty($jobid) or !is_integer($jobid)) {
		return false;
	}
	//check if a job running
	if (file_exists(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running')) {
		$runningfile=file_get_contents(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running');
		$infile=unserialize(trim($runningfile));
		if ($infile['timestamp']<time()-1800) {
			_e("A job already running!","backwpup");
			return false;
		} else { //delete working file job thing it not works longer.
			unlink(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running');
			sleep(3);
		}
	}
	// set the cache limiter to 'nocache'
	session_cache_limiter('nocache');
	// set the cache expire to 30 minutes 
	session_cache_expire(30);
	// give the session a name
	session_name('BackWPupSession');
	// start session
	session_start();
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
	$_SESSION['WP']['WP_DEBUG']=WP_DEBUG;
	$_SESSION['WP']['BLOGNAME']=get_bloginfo('name');
	$_SESSION['WP']['TIMEDIFF']=current_time('timestamp')-time();
	$_SESSION['WP']['WPLANG']=WPLANG;
	//WP folder
	$_SESSION['WP']['ABSPATH']=ABSPATH;
	$_SESSION['WP']['WP_CONTENT_DIR']=WP_CONTENT_DIR;
	$_SESSION['WP']['WP_PLUGIN_DIR']=WP_PLUGIN_DIR;
	$_SESSION['WP']['WP_THEMES_DIR']=trailingslashit(WP_CONTENT_DIR).'themes/';
	$_SESSION['WP']['WP_UPLOAD_DIR']=backwpup_get_upload_dir();
	$_SESSION['WP']['WPINC']=WPINC;
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
	//Set job data
	$jobs=get_option('backwpup_jobs');
	$_SESSION['JOB']=backwpup_check_job_vars($jobs[$jobid],$jobid);
	$_SESSION['JOB']['ID']=$jobid; // must on secend (overwrite)
	//STATIC data
	$_SESSION['STATIC']['JOBRUNURL']=plugins_url('jobrun.php',__FILE__);
	$_SESSION['STATIC']['TEMPDIR']=rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/'; //PHP 5.2.1 sys_get_temp_dir
	if (!is_writable($_SESSION['STATIC']['TEMPDIR'])) {
		_e("Temp dir not writeable","backwpup");
		session_destroy();
		return false;
	}
	//write working file
	$fd=fopen($_SESSION['STATIC']['TEMPDIR'].'.backwpup_running','w');
	fwrite($fd,serialize(array('SID'=>session_id(),'timestamp'=>time(),'JOBID'=>$_SESSION['JOB']['ID'])));
	fclose($fd);
	//
	$_SESSION['CFG']['dirlogs']=rtrim(str_replace('\\','/',$_SESSION['CFG']['dirlogs']),'/').'/'; 
	if (!is_dir($_SESSION['CFG']['dirlogs'])) {
		if (!mkdir($_SESSION['CFG']['dirlogs'],0755,true)) {
			sprintf(__('Can not create folder for log files: %1$s','backwpup'),$_SESSION['CFG']['dirlogs']);
			return false;
		}
		//create .htaccess for apache and index.html for other
		if (strtolower(substr($_SERVER["SERVER_SOFTWARE"],0,6))=="apache") {  //check if it a apache webserver
			if($file = fopen($_SESSION['CFG']['dirlogs'].'.htaccess', 'w')) {
				fwrite($file, "Order allow,deny\ndeny from all");
				fclose($file);
			}
		} else {
			if($file = fopen($_SESSION['CFG']['dirlogs'].'index.html', 'w')) {
				fwrite($file,"\n");
				fclose($file);
			}
			if($file = fopen($_SESSION['CFG']['dirlogs'].'index.php', 'w')) {
				fwrite($file,"\n");
				fclose($file);
			}
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
	fwrite($fd,"<meta name=\"php_version\" content=\"".phpversion()."\" />\n");
	fwrite($fd,"<meta name=\"mysql_version\" content=\"".$wpdb->get_var("SELECT VERSION() AS version")."\" />\n");
	fwrite($fd,"<meta name=\"backwpup_logtime\" content=\"".current_time('timestamp')."\" />\n");
	fwrite($fd,str_pad("<meta name=\"backwpup_errors\" content=\"0\" />",100)."\n");
	fwrite($fd,str_pad("<meta name=\"backwpup_warnings\" content=\"0\" />",100)."\n");
	fwrite($fd,"<meta name=\"backwpup_jobid\" content=\"".$_SESSION['JOB']['ID']."\" />\n");
	fwrite($fd,"<meta name=\"backwpup_jobname\" content=\"".$_SESSION['JOB']['name']."\" />\n");
	fwrite($fd,"<meta name=\"backwpup_jobtype\" content=\"".$_SESSION['JOB']['type']."\" />\n");
	fwrite($fd,str_pad("<meta name=\"backwpup_backupfilesize\" content=\"0\" />",100)."\n");
	fwrite($fd,str_pad("<meta name=\"backwpup_jobruntime\" content=\"0\" />",100)."\n");
	fwrite($fd,"<style type=\"text/css\">\n");
	fwrite($fd,".timestamp {background-color:grey;}\n");
	fwrite($fd,".warning {background-color:yellow;}\n");
	fwrite($fd,".error {background-color:red;}\n");
	fwrite($fd,"</style>\n");
	fwrite($fd,"<title>".sprintf(__('BackWPup Log for %1$s from %2$s at %3$s','backwpup'),$_SESSION['JOB']['name'],date_i18n(get_option('date_format')),date_i18n(get_option('time_format')))."</title>\n</head>\n<body style=\"font-family:monospace;font-size:12px;white-space:nowrap;\">\n");
	fclose($fd);
	//Set job start settings
	$jobs[$_SESSION['JOB']['ID']]['starttime']=time(); //set start time for job
	$_SESSION['JOB']['starttime']=$jobs[$_SESSION['JOB']['ID']]['starttime'];
	$jobs[$_SESSION['JOB']['ID']]['logfile']=$_SESSION['STATIC']['LOGFILE'];	   //Set current logfile
	$jobs[$_SESSION['JOB']['ID']]['cronnextrun']=backwpup_cron_next($jobs[$_SESSION['JOB']['ID']]['cron']);  //set next run
	$jobs[$_SESSION['JOB']['ID']]['lastbackupdownloadurl']='';
	$_SESSION['JOB']['lastbackupdownloadurl']='';
	update_option('backwpup_jobs',$jobs); //Save job Settings	
	//Set todo
	$_SESSION['STATIC']['TODO']=explode('+',$_SESSION['JOB']['type']);
	//only for jos that makes backups
	if (in_array('FILE',$_SESSION['STATIC']['TODO']) or in_array('DB',$_SESSION['STATIC']['TODO']) or in_array('WPEXP',$_SESSION['STATIC']['TODO'])) {
		//set Backup Dir if not set
		if (empty($_SESSION['JOB']['backupdir']))
			$_SESSION['JOB']['backupdir']=$_SESSION['STATIC']['TEMPDIR'];
		//clear path
		$_SESSION['JOB']['backupdir']=rtrim(str_replace('\\','/',$_SESSION['JOB']['backupdir']),'/').'/'; 
		//create backup dir if it not exists
		if (!is_dir($_SESSION['JOB']['backupdir'])) {
			if (!mkdir($_SESSION['JOB']['backupdir'],0755,true)) {
				sprintf(__('Can not create folder for backup files: %1$s','backwpup'),$_SESSION['JOB']['backupdir']);
				return false;
			}
			//create .htaccess for apache and index.html for other
			if (strtolower(substr($_SERVER["SERVER_SOFTWARE"],0,6))=="apache") {  //check if it a apache webserver
				if($file = fopen($_SESSION['JOB']['backupdir'].'.htaccess', 'w')) {
					fwrite($file, "Order allow,deny\ndeny from all");
					fclose($file);
				}
			} else {
				if($file = fopen($_SESSION['JOB']['backupdir'].'index.html', 'w')) {
					fwrite($file,"\n");
					fclose($file);
				}
				if($file = fopen($_SESSION['JOB']['backupdir'].'index.php', 'w')) {
					fwrite($file,"\n");
					fclose($file);
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
	//Set job as not finished
	$_SESSION['WORKING']['FINISHED']=false;
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
	//setiing first set as active
	$_SESSION['WORKING']['ACTIVE_STEP']=$_SESSION['WORKING']['STEPS'][0];
	//mark all as not done
	foreach($_SESSION['WORKING']['STEPS'] as $step) 
		$_SESSION['WORKING'][$step]['DONE']=false;
	//Close session
	$BackWPupSession=session_id();
	session_write_close();
	//Run job
	if (!empty($_SESSION['STATIC']['JOBRUNURL']) and !empty($BackWPupSession)) {
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
	return $_SESSION['STATIC']['LOGFILE'];
}
?>