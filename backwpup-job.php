<?PHP
define('DONOTCACHEPAGE', true);
define('DONOTCACHEDB', true);
define('DONOTMINIFY', true);
define('DONOTCDN', true);
define('DONOTCACHCEOBJECT', true);
define('W3TC_IN_MINIFY',false); //W3TC will not loaded
define('BACKWPUP_LINE_SEPARATOR', (strstr(PHP_OS, "WIN") or strtr(PHP_OS, "OS/2")) ? "\r\n" : "\n");
//definie E_DEPRECATED if PHP lower than 5.3
if (!defined('E_DEPRECATED'))
	define('E_DEPRECATED',8192);
if (!defined('E_USER_DEPRECATED'))
	define('E_USER_DEPRECATED',16384);
//try to disable safe mode
@ini_set('safe_mode','0');
// Now user abort
@ini_set('ignore_user_abort','0');
ignore_user_abort(true);
$backwpup_cfg='';
$backwpup_job_object='';
global $l10n,$backwpup_cfg,$backwpup_job_object;
//prase comandline args
if (defined('STDIN')) {
	$_GET['starttype']='runcmd';
	foreach($_SERVER['argv'] as $arg) {
		if (strtolower(substr($arg,0,7))=='-jobid=')
			$_GET['jobid']=(int)substr($arg,7);
		if (strtolower(substr($arg,0,9))=='-abspath=')
			$_GET['ABSPATH']=substr($arg,9);
	}
	if ((empty($_GET['jobid']) or !is_numeric($_GET['jobid'])))
		die('JOBID check');
	if (is_file('../../../wp-load.php')) {
		require_once('../../../wp-load.php');
	} else {
		$_GET['ABSPATH']=preg_replace( '/[^a-zA-Z0-9:.\/_\-]/', '',trim(urldecode($_GET['ABSPATH'])));
		$_GET['ABSPATH']=str_replace(array('../','\\','//'),'',$_GET['ABSPATH']);
		if (file_exists($_GET['ABSPATH'].'wp-load.php'))
			require_once($_GET['ABSPATH'].'wp-load.php');
		else
			die('ABSPATH check');
	}
} else { //normal start from webserver
	//check get vars
	if (empty($_GET['starttype']) or !in_array($_GET['starttype'],array('restarttime','restart','runnow','cronrun','runext')))
		die('Starttype check');
	if ((empty($_GET['jobid']) or !is_numeric($_GET['jobid'])) and in_array($_GET['starttype'],array('runnow','cronrun','runext')))
		die('JOBID check');
	$_GET['_wpnonce']=preg_replace( '/[^a-zA-Z0-9_\-]/', '',trim($_GET['_wpnonce']));
	if (empty($_GET['_wpnonce']) or !is_string($_GET['_wpnonce']))
		die('Nonce pre check');
	if (is_file('../../../wp-load.php')) {
		require_once('../../../wp-load.php');
	} else {
		$_GET['ABSPATH']=preg_replace( '/[^a-zA-Z0-9:.\/_\-]/', '',trim(urldecode($_GET['ABSPATH'])));
		$_GET['ABSPATH']=str_replace(array('../','\\','//'),'',$_GET['ABSPATH']);
		if (file_exists($_GET['ABSPATH'].'wp-load.php'))
			require_once($_GET['ABSPATH'].'wp-load.php');
		else
			die('ABSPATH check');
	}
	if (!(in_array($_GET['starttype'],array('restarttime','restart','cronrun','runnow')) and wp_verify_nonce($_GET['_wpnonce'],'backwpup-job-running'))
		and !($_GET['starttype']=='runext' and !empty($_GET['_wpnonce']) and !empty($backwpup_cfg['jobrunauthkey']) and $backwpup_cfg['jobrunauthkey']))
		die('Nonce check');
}
if ($_GET['jobid']!=backwpup_get_option('job_'.$_GET['jobid'],'jobid'))
	die('Wrong JOBID check');
//check running job
$backwpupjobdata=backwpup_get_option('working','data');
if (in_array($_GET['starttype'],array('runnow','cronrun','runext','runcmd')) and !empty($backwpupjobdata))
		die('A job already running');
if (in_array($_GET['starttype'],array('restart','restarttime')) and (empty($backwpupjobdata) or !is_array($backwpupjobdata)))
		die('No job running');
unset($backwpupjobdata);
//disconnect or redirect
if (in_array($_GET['starttype'],array('restarttime','restart','cronrun','runext'))) {
	ob_end_clean();
	header("Connection: close");
	ob_start();
	header("Content-Length: 0");
	ob_end_flush();
	flush();
}
elseif ($_GET['starttype']=='runnow') {
	ob_start();
	wp_redirect(backwpup_admin_url('admin.php').'?page=backwpupworking');
	echo ' ';
	while ( @ob_end_flush() );
	flush();
}
//unload translation
if ($backwpup_cfg['unloadtranslations'])
	unset($l10n);

class BackWPup_job {

	private $jobdata=false;

	public function __construct() {
		global $wpdb;
		//get job data
		if (in_array($_GET['starttype'],array('runnow','cronrun','runext','runcmd')))
			$this->start((int)$_GET['jobid']);
		else
			$this->jobdata=backwpup_get_option('working','data');
		//set function for PHP user defined error handling
		$this->jobdata['PHP']['INI']['ERROR_LOG']=ini_get('error_log');
		$this->jobdata['PHP']['INI']['LOG_ERRORS']=ini_get('log_errors');
		$this->jobdata['PHP']['INI']['DISPLAY_ERRORS']=ini_get('display_errors');
		@ini_set('error_log', $this->jobdata['LOGFILE']);
		@ini_set('display_errors', 'Off');
		@ini_set('log_errors', 'On');
		set_error_handler(array($this,'errorhandler'),E_ALL | E_STRICT);
		//Check dobbel running and inactivity
		if ($this->jobdata['WORKING']['PID']!=getmypid() and $this->jobdata['WORKING']['TIMESTAMP']>(current_time('timestamp')-500) and $_GET['starttype']=='restarttime') {
			trigger_error(__('Job restart terminated, because other job runs!','backwpup'),E_USER_ERROR);
			die();
		} elseif($_GET['starttype']=='restarttime') {
			trigger_error(__('Job restarted, because of inactivity!','backwpup'),E_USER_ERROR);
		} elseif ($this->jobdata['WORKING']['PID']!=getmypid() and $this->jobdata['WORKING']['PID']!=0 and $this->jobdata['WORKING']['timestamp']>(time()-500)) {
			trigger_error(sprintf(__('Second prozess is running, but old job runs! Start type is %s','backwpup'),$_GET['starttype']),E_USER_ERROR);
			die();
		}
		//set Pid
		$this->jobdata['WORKING']['PID']=getmypid();
		// execute function on job shutdown
		register_shutdown_function(array($this,'__destruct'));
		if (function_exists('pcntl_signal')) {
			declare(ticks=1); //set ticks
			pcntl_signal(15, array($this,'__destruct')); //SIGTERM
			//pcntl_signal(9, array($this,'__destruct')); //SIGKILL
			pcntl_signal(2, array($this,'__destruct')); //SIGINT
		}
		$this->_update_working_data(true);
		// Working step by step
		foreach($this->jobdata['WORKING']['STEPS'] as $step) {
			//Set next step
			if (!isset($this->jobdata['WORKING'][$step]['STEP_TRY']) or empty($this->jobdata['WORKING'][$step]['STEP_TRY'])) {
				$this->jobdata['WORKING'][$step]['STEP_TRY']=0;
				$this->jobdata['WORKING']['STEPDONE']=0;
				$this->jobdata['WORKING']['STEPTODO']=0;
			}
			//update running file
			$this->_update_working_data(true);
			//Run next step
			if (!in_array($step,$this->jobdata['WORKING']['STEPSDONE'])) {
				if (method_exists($this,strtolower($step))) {
					while ($this->jobdata['WORKING'][$step]['STEP_TRY']<$this->jobdata['STATIC']['CFG']['jobstepretry']) {
						if (in_array($step,$this->jobdata['WORKING']['STEPSDONE']))
							break;
						$this->jobdata['WORKING'][$step]['STEP_TRY']++;
						$this->_update_working_data(true);
						call_user_func(array($this,strtolower($step)));
					}
					if ($this->jobdata['WORKING'][$step]['STEP_TRY']>=$this->jobdata['STATIC']['CFG']['jobstepretry'])
						trigger_error(__('Step aborted has too many tries!','backwpup'),E_USER_ERROR);
				} else {
					trigger_error(sprintf(__('Can not find job step method %s!','backwpup'),strtolower($step)),E_USER_ERROR);
					$this->jobdata['WORKING']['STEPSDONE'][]=$step;
				}
			}
		}
	}

	public function __destruct() {
		$args = func_get_args();
		//nothing on empty
		if (empty($this->jobdata['LOGFILE']))
			return;
		//Put last error to log if one
		$lasterror=error_get_last();
		if ($lasterror['type']==E_ERROR or $lasterror['type']==E_PARSE or $lasterror['type']==E_CORE_ERROR or $lasterror['type']==E_CORE_WARNING or $lasterror['type']==E_COMPILE_ERROR or $lasterror['type']==E_COMPILE_WARNING)
			$this->errorhandler($lasterror['type'],$lasterror['message'],$lasterror['file'],$lasterror['line']);
		//Put sigterm to log
		if (!empty($args[0]))
			$this->errorhandler(E_USER_ERROR,sprintf(__('Signal $d send to script!','backwpup')),__FILE__,__LINE__);
		//no more restarts
		$this->jobdata['WORKING']['RESTART']++;
		if ((defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON) or $this->jobdata['WORKING']['RESTART']>=$this->jobdata['STATIC']['CFG']['jobscriptretry']) {  //only x restarts allowed
			if (defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON)
				$this->errorhandler(E_USER_ERROR,__('Can not restart on alternate cron....','backwpup'),__FILE__,__LINE__);
			else
				$this->errorhandler(E_USER_ERROR,__('To many restarts....','backwpup'),__FILE__,__LINE__);
			$this->end();
			exit;
		}
		$backupdata=backwpup_get_option('working','data');
		if (empty($backupdata))
			exit;
		//set PID to 0
		$this->jobdata['WORKING']['PID']=0;
		//Restart job
		$this->_update_working_data(true);
		$this->errorhandler(E_USER_NOTICE,sprintf(__('%d. Script stop! Will started again now!','backwpup'),$this->jobdata['WORKING']['RESTART']),__FILE__,__LINE__);
		$httpauthheader='';
		if (!empty($this->jobdata['STATIC']['CFG']['httpauthuser']) and !empty($this->jobdata['STATIC']['CFG']['httpauthpassword']))
			$httpauthheader=array( 'Authorization' => 'Basic '.base64_encode($this->jobdata['STATIC']['CFG']['httpauthuser'].':'.base64_decode($this->jobdata['STATIC']['CFG']['httpauthpassword'])));
		@wp_remote_get(BACKWPUP_PLUGIN_BASEURL.'/backwpup-job.php?ABSPATH='.urlencode(str_replace('\\','/',ABSPATH)).'&_wpnonce='.wp_create_nonce('backwpup-job-running').'&starttype=restart', array('timeout' => 5, 'blocking' => false, 'sslverify' => false,'headers'=>$httpauthheader, 'user-agent'=>'BackWPup'));
		exit;
	}

	private function start($jobid) {
		global $wp_version,$backwpup_cfg;
		//make start on cli mode
		if (defined('STDIN'))
			_e('Run!','backwpup');
		//clean var
		$this->jobdata = array();
		//get cfg
		$this->jobdata['STATIC']['CFG']=$backwpup_cfg;
		//check exists gzip functions
		if(!function_exists('gzopen'))
			$this->jobdata['STATIC']['CFG']['gzlogs']=false;
		if(!class_exists('ZipArchive'))
			$this->jobdata['STATIC']['CFG']['phpzip']=false;
		//set Logfile
		$this->jobdata['LOGFILE']=$this->jobdata['STATIC']['CFG']['logfolder'].'backwpup_log_'.date_i18n('Y-m-d_H-i-s').'.html';
		//Set job data
		$this->jobdata['STATIC']['JOB']=backwpup_get_job_vars($jobid);
		//Set job start settings
		$this->jobdata['STATIC']['JOB']['starttime']=current_time('timestamp'); //set start time for job
		backwpup_update_option('job_'.$this->jobdata['STATIC']['JOB']['jobid'],'starttime',$this->jobdata['STATIC']['JOB']['starttime']);
		backwpup_update_option('job_'.$this->jobdata['STATIC']['JOB']['jobid'],'logfile',$this->jobdata['LOGFILE']); //Set current logfile
		$this->jobdata['STATIC']['JOB']['cronnextrun']=backwpup_cron_next($this->jobdata['STATIC']['JOB']['cron']);  //set next run
		backwpup_update_option('job_'.$this->jobdata['STATIC']['JOB']['jobid'],'cronnextrun',$this->jobdata['STATIC']['JOB']['cronnextrun']);
		backwpup_update_option('job_'.$this->jobdata['STATIC']['JOB']['jobid'],'lastbackupdownloadurl','');
		//only for jobs that makes backups
		if (in_array('FILE',$this->jobdata['STATIC']['JOB']['type']) or in_array('DB',$this->jobdata['STATIC']['JOB']['type']) or in_array('WPEXP',$this->jobdata['STATIC']['JOB']['type'])) {
			//make empty file list
			$this->jobdata['WORKING']['ALLFILESIZE']=0;
			$this->jobdata['WORKING']['BACKUPFILESIZE']=0;
			if ($this->jobdata['STATIC']['JOB']['backuptype']=='archive') {
				//set Backup folder to temp folder if not set
				if (empty($this->jobdata['STATIC']['JOB']['backupdir']) or $this->jobdata['STATIC']['JOB']['backupdir']=='/')
					$this->jobdata['STATIC']['JOB']['backupdir']=$this->jobdata['STATIC']['CFG']['tempfolder'];
				//Create backup archive full file name
				$this->jobdata['STATIC']['backupfile']=$this->jobdata['STATIC']['JOB']['fileprefix'].date_i18n('Y-m-d_H-i-s').$this->jobdata['STATIC']['JOB']['fileformart'];
			}
		}
		$this->jobdata['WORKING']['PID']=0;
		$this->jobdata['WORKING']['WARNING']=0;
		$this->jobdata['WORKING']['ERROR']=0;
		$this->jobdata['WORKING']['RESTART']=0;
		$this->jobdata['WORKING']['STEPSDONE']=array();
		$this->jobdata['WORKING']['STEPTODO']=0;
		$this->jobdata['WORKING']['STEPDONE']=0;
		$this->jobdata['WORKING']['STEPSPERSENT']=0;
		$this->jobdata['WORKING']['STEPPERSENT']=0;
		$this->jobdata['WORKING']['TIMESTAMP']=current_time('timestamp');
		$this->jobdata['WORKING']['ENDINPROGRESS']=false;
		$this->jobdata['WORKING']['EXTRAFILESTOBACKUP']=array();
		//build working steps
		$this->jobdata['WORKING']['STEPS']=array();
		//setup job steps
		if (in_array('DB',$this->jobdata['STATIC']['JOB']['type']))
			$this->jobdata['WORKING']['STEPS'][]='DB_DUMP';
		if (in_array('WPEXP',$this->jobdata['STATIC']['JOB']['type']))
			$this->jobdata['WORKING']['STEPS'][]='WP_EXPORT';
		if (in_array('FILE',$this->jobdata['STATIC']['JOB']['type']))
			$this->jobdata['WORKING']['STEPS'][]='FILE_LIST';
		if (in_array('DB',$this->jobdata['STATIC']['JOB']['type']) or in_array('WPEXP',$this->jobdata['STATIC']['JOB']['type']) or in_array('FILE',$this->jobdata['STATIC']['JOB']['type'])) {
			if ($this->jobdata['STATIC']['JOB']['backuptype']=='archive') {
				$this->jobdata['WORKING']['STEPS'][]='CREATE_ARCHIVE';
				$backuptypeextension='';
			} elseif ($this->jobdata['STATIC']['JOB']['backuptype']=='sync') {
				$backuptypeextension='_SYNC';
			}
			//ADD Destinations
			if (!empty($this->jobdata['STATIC']['JOB']['backupdir']) and $this->jobdata['STATIC']['JOB']['backupdir']!='/' and $this->jobdata['STATIC']['JOB']['backupdir']!=$this->jobdata['STATIC']['CFG']['tempfolder'])
				$this->jobdata['WORKING']['STEPS'][]='DEST_FOLDER'.$backuptypeextension;
			if (!empty($this->jobdata['STATIC']['JOB']['mailaddress']) and $this->jobdata['STATIC']['JOB']['backuptype']=='archive')
				$this->jobdata['WORKING']['STEPS'][]='DEST_MAIL';
			if (!empty($this->jobdata['STATIC']['JOB']['ftphost']) and !empty($this->jobdata['STATIC']['JOB']['ftpuser']) and !empty($this->jobdata['STATIC']['JOB']['ftppass']) and in_array('FTP',explode(',',strtoupper(BACKWPUP_DESTS))))
				$this->jobdata['WORKING']['STEPS'][]='DEST_FTP'.$backuptypeextension;
			if (!empty($this->jobdata['STATIC']['JOB']['dropetoken']) and !empty($this->jobdata['STATIC']['JOB']['dropesecret']) and in_array('DROPBOX',explode(',',strtoupper(BACKWPUP_DESTS))))
				$this->jobdata['WORKING']['STEPS'][]='DEST_DROPBOX'.$backuptypeextension;
			if (!empty($this->jobdata['STATIC']['JOB']['boxnetauth']) and in_array('BOXNET',explode(',',strtoupper(BACKWPUP_DESTS))))
				$this->jobdata['WORKING']['STEPS'][]='DEST_BOXNET'.$backuptypeextension;
			if (!empty($this->jobdata['STATIC']['JOB']['sugaruser']) and !empty($this->jobdata['STATIC']['JOB']['sugarpass']) and !empty($this->jobdata['STATIC']['JOB']['sugarroot']) and in_array('SUGARSYNC',explode(',',strtoupper(BACKWPUP_DESTS))))
				$this->jobdata['WORKING']['STEPS'][]='DEST_SUGARSYNC'.$backuptypeextension;
			if (!empty($this->jobdata['STATIC']['JOB']['awsAccessKey']) and !empty($this->jobdata['STATIC']['JOB']['awsSecretKey']) and !empty($this->jobdata['STATIC']['JOB']['awsBucket']) and in_array('S3',explode(',',strtoupper(BACKWPUP_DESTS))))
				$this->jobdata['WORKING']['STEPS'][]='DEST_S3'.$backuptypeextension;
			if (!empty($this->jobdata['STATIC']['JOB']['GStorageAccessKey']) and !empty($this->jobdata['STATIC']['JOB']['GStorageSecret']) and !empty($this->jobdata['STATIC']['JOB']['GStorageBucket']) and in_array('GSTORAGE',explode(',',strtoupper(BACKWPUP_DESTS))))
				$this->jobdata['WORKING']['STEPS'][]='DEST_GSTORAGE'.$backuptypeextension;
			if (!empty($this->jobdata['STATIC']['JOB']['rscUsername']) and !empty($this->jobdata['STATIC']['JOB']['rscAPIKey']) and !empty($this->jobdata['STATIC']['JOB']['rscContainer']) and in_array('RSC',explode(',',strtoupper(BACKWPUP_DESTS))))
				$this->jobdata['WORKING']['STEPS'][]='DEST_RSC'.$backuptypeextension;
			if (!empty($this->jobdata['STATIC']['JOB']['msazureHost']) and !empty($this->jobdata['STATIC']['JOB']['msazureAccName']) and !empty($this->jobdata['STATIC']['JOB']['msazureKey']) and !empty($this->jobdata['STATIC']['JOB']['msazureContainer']) and in_array('MSAZURE',explode(',',strtoupper(BACKWPUP_DESTS))))
				$this->jobdata['WORKING']['STEPS'][]='DEST_MSAZURE'.$backuptypeextension;
		}
		if (in_array('CHECK',$this->jobdata['STATIC']['JOB']['type']))
			$this->jobdata['WORKING']['STEPS'][]='DB_CHECK';
		if (in_array('OPTIMIZE',$this->jobdata['STATIC']['JOB']['type']))
			$this->jobdata['WORKING']['STEPS'][]='DB_OPTIMIZE';
		$this->jobdata['WORKING']['STEPS'][]='END';
		//mark all as not done
		foreach($this->jobdata['WORKING']['STEPS'] as $step)
			$this->jobdata['WORKING'][$step]['DONE']=false;
		//write working date
		backwpup_update_option('working','data',$this->jobdata);
		//create log file
		$fd=fopen($this->jobdata['LOGFILE'],'w');
		fwrite($fd,"<html>".BACKWPUP_LINE_SEPARATOR."<head>".BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,"<meta name=\"backwpup_version\" content=\"".BACKWPUP_VERSION."\" />".BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,"<meta name=\"backwpup_logtime\" content=\"".current_time('timestamp')."\" />".BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,str_pad("<meta name=\"backwpup_errors\" content=\"0\" />",100).BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,str_pad("<meta name=\"backwpup_warnings\" content=\"0\" />",100).BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,"<meta name=\"backwpup_jobid\" content=\"".$this->jobdata['STATIC']['JOB']['jobid']."\" />".BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,"<meta name=\"backwpup_jobname\" content=\"".$this->jobdata['STATIC']['JOB']['name']."\" />".BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,"<meta name=\"backwpup_jobtype\" content=\"".implode('+',$this->jobdata['STATIC']['JOB']['type'])."\" />".BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,str_pad("<meta name=\"backwpup_backupfilesize\" content=\"0\" />",100).BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,str_pad("<meta name=\"backwpup_jobruntime\" content=\"0\" />",100).BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,"<style type=\"text/css\">".BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,".timestamp {background-color:grey;}".BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,".warning {background-color:yellow;}".BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,".error {background-color:red;}".BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,"#body {font-family:monospace;font-size:12px;white-space:nowrap;}".BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,"</style>".BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,"<title>".sprintf(__('BackWPup log for %1$s from %2$s at %3$s','backwpup'),$this->jobdata['STATIC']['JOB']['name'],date_i18n(get_option('date_format')),date_i18n(get_option('time_format')))."</title>".BACKWPUP_LINE_SEPARATOR."</head>".BACKWPUP_LINE_SEPARATOR."<body id=\"body\">".BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,sprintf(__('[INFO]: BackWPup version %1$s, WordPress version %4$s Copyright &copy; %2$s %3$s'),BACKWPUP_VERSION,date_i18n('Y'),'<a href="http://danielhuesken.de" target="_blank">Daniel H&uuml;sken</a>',$wp_version)."<br />".BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,__('[INFO]: BackWPup comes with ABSOLUTELY NO WARRANTY. This is free software, and you are welcome to redistribute it under certain conditions.','backwpup')."<br />".BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,__('[INFO]: BackWPup job:','backwpup').' '.$this->jobdata['STATIC']['JOB']['jobid'].'. '.$this->jobdata['STATIC']['JOB']['name'].'; '.implode('+',$this->jobdata['STATIC']['JOB']['type'])."<br />".BACKWPUP_LINE_SEPARATOR);
		if ($this->jobdata['STATIC']['JOB']['activated'])
			fwrite($fd,__('[INFO]: BackWPup cron:','backwpup').' '.$this->jobdata['STATIC']['JOB']['cron'].'; '.date_i18n('D, j M Y @ H:i',$this->jobdata['STATIC']['JOB']['cronnextrun'])."<br />".BACKWPUP_LINE_SEPARATOR);
		if ($_GET['starttype']=='cronrun')
			fwrite($fd,__('[INFO]: BackWPup job started from wp-cron','backwpup')."<br />".BACKWPUP_LINE_SEPARATOR);
		elseif ($_GET['starttype']=='runnow')
			fwrite($fd,__('[INFO]: BackWPup job started manually','backwpup')."<br />".BACKWPUP_LINE_SEPARATOR);
		elseif ($_GET['starttype']=='runext')
			fwrite($fd,__('[INFO]: BackWPup job started external from url','backwpup')."<br />".BACKWPUP_LINE_SEPARATOR);
		elseif ($_GET['starttype']=='runcmd')
			fwrite($fd,__('[INFO]: BackWPup job started form commandline','backwpup')."<br />".BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,__('[INFO]: PHP ver.:','backwpup').' '.phpversion().'; '.php_sapi_name().'; '.PHP_OS."<br />".BACKWPUP_LINE_SEPARATOR);
		if ((bool)ini_get('safe_mode'))
			fwrite($fd,sprintf(__('[INFO]: PHP Safe mode is ON! Maximum script execution time is %1$d sec.','backwpup'),ini_get('max_execution_time'))."<br />".BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,sprintf(__('[INFO]: MySQL ver.: %s','backwpup'),mysql_result(mysql_query("SELECT VERSION() AS version"),0))."<br />".BACKWPUP_LINE_SEPARATOR);
		if (function_exists('curl_init')) {
			$curlversion=curl_version();
			fwrite($fd,sprintf(__('[INFO]: curl ver.: %1$s; %2$s','backwpup'),$curlversion['version'],$curlversion['ssl_version'])."<br />".BACKWPUP_LINE_SEPARATOR);
		}
		fwrite($fd,sprintf(__('[INFO]: Temp folder is: %s','backwpup'),$this->jobdata['STATIC']['CFG']['tempfolder'])."<br />".BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,sprintf(__('[INFO]: Logfile folder is: %s','backwpup'),$this->jobdata['STATIC']['CFG']['logfolder'])."<br />".BACKWPUP_LINE_SEPARATOR);
		fwrite($fd,sprintf(__('[INFO]: Backup type is: %s','backwpup'),$this->jobdata['STATIC']['JOB']['backuptype'])."<br />".BACKWPUP_LINE_SEPARATOR);
		if(!empty($this->jobdata['STATIC']['backupfile']) and $this->jobdata['STATIC']['JOB']['backuptype']=='archive')
			fwrite($fd,sprintf(__('[INFO]: Backup file is: %s','backwpup'),$this->jobdata['STATIC']['JOB']['backupdir'].$this->jobdata['STATIC']['backupfile'])."<br />".BACKWPUP_LINE_SEPARATOR);
		fclose($fd);
		//test for destinations
		if (in_array('DB',$this->jobdata['STATIC']['JOB']['type']) or in_array('WPEXP',$this->jobdata['STATIC']['JOB']['type']) or in_array('FILE',$this->jobdata['STATIC']['JOB']['type'])) {
			$desttest=false;
			foreach($this->jobdata['WORKING']['STEPS'] as $deststeptest) {
				if (substr($deststeptest,0,5)=='DEST_') {
					$desttest=true;
					break;
				}
			}
			if (!$desttest)
				$this->errorhandler(E_USER_ERROR,__('No destination defined for backup!!! Please correct job settings','backwpup'),__FILE__,__LINE__);
		}
	}

	private function _checkfolder($folder) {
		$folder=untrailingslashit($folder);
		//check that is not home of WP
		if (is_file($folder.'/wp-load.php'))
			return false;
		//create backup dir if it not exists
		if (!is_dir($folder)) {
			if (!mkdir($folder,0777,true)) {
				trigger_error(sprintf(__('Can not create folder: %1$s','backwpup'),$folder),E_USER_ERROR);
				return false;
			}
			//create .htaccess for apache and index.html/php for other
			if (strtolower(substr($_SERVER["SERVER_SOFTWARE"],0,6))=="apache") {  //check for apache webserver
				if (!is_file($folder.'/.htaccess'))
					file_put_contents($folder.'/.htaccess',"Order allow,deny".BACKWPUP_LINE_SEPARATOR."deny from all");
			} else {
				if (!is_file($folder.'/index.html'))
					file_put_contents($folder.'/index.html',BACKWPUP_LINE_SEPARATOR);
				if (!is_file($folder.'/index.php'))
					file_put_contents($folder.'/index.php',BACKWPUP_LINE_SEPARATOR);
			}
		}
		//check backup dir
		if (!is_writable($folder)) {
			trigger_error(sprintf(__('Not writable folder: %1$s','backwpup'),$folder),E_USER_ERROR);
			return false;
		}
		return true;
	}

	public function errorhandler() {
		$args = func_get_args(); // 0:errno, 1:errstr, 2:errfile, 3:errline
		// if error has been supressed with an @
		if (error_reporting()==0)
			return;

		$adderrorwarning=false;

		switch ($args[0]) {
			case E_NOTICE:
			case E_USER_NOTICE:
				$messagetype="<span>";
				break;
			case E_WARNING:
			case E_CORE_WARNING:
			case E_COMPILE_WARNING:
			case E_USER_WARNING:
				$this->jobdata['WORKING']['WARNING']++;
				$adderrorwarning=true;
				$messagetype="<span class=\"warning\">".__('WARNING:','backwpup');
				break;
			case E_ERROR:
			case E_PARSE:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
				$this->jobdata['WORKING']['ERROR']++;
				$adderrorwarning=true;
				$messagetype="<span class=\"error\">".__('ERROR:','backwpup');
				break;
			case E_DEPRECATED:
			case E_USER_DEPRECATED:
				$messagetype="<span>".__('DEPRECATED:','backwpup');
				break;
			case E_STRICT:
				$messagetype="<span>".__('STRICT NOTICE:','backwpup');
				break;
			case E_RECOVERABLE_ERROR:
				$messagetype="<span>".__('RECOVERABLE ERROR:','backwpup');
				break;
			default:
				$messagetype="<span>".$args[0].":";
				break;
		}

		//log line
		$timestamp="<span title=\"[Type: ".$args[0]."|Line: ".$args[3]."|File: ".basename($args[2])."|Mem: ".backwpup_formatBytes(@memory_get_usage(true))."|Mem Max: ".backwpup_formatBytes(@memory_get_peak_usage(true))."|Mem Limit: ".ini_get('memory_limit')."|PID: ".getmypid()."]\">[".date_i18n('d-M-Y H:i:s')."]</span> ";
		//write log file
		file_put_contents($this->jobdata['LOGFILE'], $timestamp.$messagetype." ".$args[1]."</span><br />".BACKWPUP_LINE_SEPARATOR, FILE_APPEND);

		//write new log header
		if ($adderrorwarning) {
			$found=0;
			$fd=fopen($this->jobdata['LOGFILE'],'r+');
			$filepos=ftell($fd);
			while (!feof($fd)) {
				$line=fgets($fd);
				if (stripos($line,"<meta name=\"backwpup_errors\"") !== false) {
					fseek($fd,$filepos);
					fwrite($fd,str_pad("<meta name=\"backwpup_errors\" content=\"".$this->jobdata['WORKING']['ERROR']."\" />",100).BACKWPUP_LINE_SEPARATOR);
					$found++;
				}
				if (stripos($line,"<meta name=\"backwpup_warnings\"") !== false) {
					fseek($fd,$filepos);
					fwrite($fd,str_pad("<meta name=\"backwpup_warnings\" content=\"".$this->jobdata['WORKING']['WARNING']."\" />",100).BACKWPUP_LINE_SEPARATOR);
					$found++;
				}
				if ($found>=2)
					break;
				$filepos=ftell($fd);
			}
			fclose($fd);
		}

		//write working data
		$this->_update_working_data($adderrorwarning);

		//Die on fatal php errors.
		if ($args[0]==E_ERROR or $args[0]==E_CORE_ERROR or $args[0]==E_COMPILE_ERROR)
			die();

		//true for no more php error handling.
		return true;
	}

	private function _update_working_data($mustwrite=false) {
		global $wpdb;
		$backupdata=backwpup_get_option('working','data');
		if (empty($backupdata)) {
			$this->end();
			return false;
		}
		$timetoupdate=current_time('timestamp')-1; //only update all 1 sec.
		if ($mustwrite or $this->jobdata['WORKING']['TIMESTAMP']<=$timetoupdate) {
			if(!mysql_ping($wpdb->dbh)) { //check MySQL connection
				trigger_error(__('Database connection is gone create a new one.','backwpup'),E_USER_NOTICE);
				$wpdb->db_connect();
			}
			if ($this->jobdata['WORKING']['STEPTODO']>0 and $this->jobdata['WORKING']['STEPDONE']>0)
				$this->jobdata['WORKING']['STEPPERSENT']=round($this->jobdata['WORKING']['STEPDONE']/$this->jobdata['WORKING']['STEPTODO']*100);
			else
				$this->jobdata['WORKING']['STEPPERSENT']=1;
			if (count($this->jobdata['WORKING']['STEPSDONE'])>0)
				$this->jobdata['WORKING']['STEPSPERSENT']=round(count($this->jobdata['WORKING']['STEPSDONE'])/count($this->jobdata['WORKING']['STEPS'])*100);
			else
				$this->jobdata['WORKING']['STEPSPERSENT']=1;
			$this->jobdata['WORKING']['TIMESTAMP']=current_time('timestamp');
			@set_time_limit(0);
			backwpup_update_option('working','data',$this->jobdata);
			if (defined('STDIN')) //make dots on cli mode
				echo ".";
		}
		return true;
	}

	private function end() {
		global $wpdb;
		//check if end() in progress
		if (!$this->jobdata['WORKING']['ENDINPROGRESS'])
			$this->jobdata['WORKING']['ENDINPROGRESS']=true;
		else
			return;

		$this->jobdata['WORKING']['STEPTODO']=1;
		$this->jobdata['WORKING']['STEPDONE']=0;
		//Back from maintenance
		$this->_maintenance_mode(false);
		//delete old logs
		if (!empty($this->jobdata['STATIC']['CFG']['maxlogs'])) {
			if ( $dir = opendir($this->jobdata['STATIC']['CFG']['logfolder']) ) { //make file list
				while (($file = readdir($dir)) !== false ) {
					if ('backwpup_log_' == substr($file,0,strlen('backwpup_log_')) and (".html" == substr($file,-5) or ".html.gz" == substr($file,-8)))
						$logfilelist[]=$file;
				}
				closedir( $dir );
			}
			if (sizeof($logfilelist)>0) {
				rsort($logfilelist);
				$numdeltefiles=0;
				for ($i=$this->jobdata['STATIC']['CFG']['maxlogs'];$i<sizeof($logfilelist);$i++) {
					unlink($this->jobdata['STATIC']['CFG']['logfolder'].$logfilelist[$i]);
					$numdeltefiles++;
				}
				if ($numdeltefiles>0)
					trigger_error(sprintf(_n('One old log deleted','%d old logs deleted',$numdeltefiles,'backwpup'),$numdeltefiles),E_USER_NOTICE);
			}
		}
		//Display job working time
		if (!empty($this->jobdata['STATIC']['JOB']['starttime']))
			trigger_error(sprintf(__('Job done in %s sec.','backwpup'),current_time('timestamp')-$this->jobdata['STATIC']['JOB']['starttime']),E_USER_NOTICE);

		if (empty($this->jobdata['STATIC']['backupfile']) or !is_file($this->jobdata['STATIC']['JOB']['backupdir'].$this->jobdata['STATIC']['backupfile']) or !($filesize=filesize($this->jobdata['STATIC']['JOB']['backupdir'].$this->jobdata['STATIC']['backupfile']))) //Set the filesize correctly
			$filesize=0;

		//clean up temp
		if (!empty($this->jobdata['STATIC']['backupfile']) and file_exists($this->jobdata['STATIC']['CFG']['tempfolder'].$this->jobdata['STATIC']['backupfile']))
			unlink($this->jobdata['STATIC']['CFG']['tempfolder'].$this->jobdata['STATIC']['backupfile']);
		if (!empty($this->jobdata['STATIC']['JOB']['dbdumpfile']) and file_exists($this->jobdata['STATIC']['CFG']['tempfolder'].$this->jobdata['STATIC']['JOB']['dbdumpfile']))
			unlink($this->jobdata['STATIC']['CFG']['tempfolder'].$this->jobdata['STATIC']['JOB']['dbdumpfile']);
		if (!empty($this->jobdata['STATIC']['JOB']['wpexportfile']) and file_exists($this->jobdata['STATIC']['CFG']['tempfolder'].$this->jobdata['STATIC']['JOB']['wpexportfile']))
			unlink($this->jobdata['STATIC']['CFG']['tempfolder'].$this->jobdata['STATIC']['JOB']['wpexportfile']);

		//Update job options
		$starttime=backwpup_get_option('job_'.$this->jobdata['STATIC']['JOB']['jobid'],'starttime');
		if (!empty($starttime)) {
			backwpup_update_option('job_'.$this->jobdata['STATIC']['JOB']['jobid'],'lastrun',$starttime);
			backwpup_update_option('job_'.$this->jobdata['STATIC']['JOB']['jobid'],'lastruntime',(current_time('timestamp')-$starttime));
			backwpup_update_option('job_'.$this->jobdata['STATIC']['JOB']['jobid'],'starttime','');
		}
		$this->jobdata['STATIC']['JOB']['lastrun']=$starttime;
		//write header info
		if (is_writable($this->jobdata['LOGFILE'])) {
			$fd=fopen($this->jobdata['LOGFILE'],'r+');
			$filepos=ftell($fd);
			$found=0;
			while (!feof($fd)) {
				$line=fgets($fd);
				if (stripos($line,"<meta name=\"backwpup_jobruntime\"") !== false) {
					fseek($fd,$filepos);
					fwrite($fd,str_pad("<meta name=\"backwpup_jobruntime\" content=\"".backwpup_get_option('job_'.$this->jobdata['STATIC']['JOB']['jobid'],'lastruntime')."\" />",100).BACKWPUP_LINE_SEPARATOR);
					$found++;
				}
				if (stripos($line,"<meta name=\"backwpup_backupfilesize\"") !== false) {
					fseek($fd,$filepos);
					fwrite($fd,str_pad("<meta name=\"backwpup_backupfilesize\" content=\"".$filesize."\" />",100).BACKWPUP_LINE_SEPARATOR);
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
		@ini_set('log_errors', $this->jobdata['PHP']['INI']['LOG_ERRORS']);
		@ini_set('error_log', $this->jobdata['PHP']['INI']['ERROR_LOG']);
		@ini_set('display_errors', $this->jobdata['PHP']['INI']['DISPLAY_ERRORS']);
		//logfile end
		file_put_contents($this->jobdata['LOGFILE'], "</body>".BACKWPUP_LINE_SEPARATOR."</html>", FILE_APPEND);
		//gzip logfile
		if ($this->jobdata['STATIC']['CFG']['gzlogs'] and is_writable($this->jobdata['LOGFILE'])) {
			$fd=fopen($this->jobdata['LOGFILE'],'r');
			$zd=gzopen($this->jobdata['LOGFILE'].'.gz','w9');
			while (!feof($fd)) {
				gzwrite($zd,fread($fd,4096));
			}
			gzclose($zd);
			fclose($fd);
			unlink($this->jobdata['LOGFILE']);
			$this->jobdata['LOGFILE']=$this->jobdata['LOGFILE'].'.gz';
			backwpup_update_option('job_'.$this->jobdata['STATIC']['JOB']['jobid'],'logfile',$this->jobdata['LOGFILE']);
		}

		//Send mail with log
		$sendmail=false;
		if ($this->jobdata['WORKING']['ERROR']>0 and $this->jobdata['STATIC']['JOB']['mailerroronly'] and !empty($this->jobdata['STATIC']['JOB']['mailaddresslog']))
			$sendmail=true;
		if (!$this->jobdata['STATIC']['JOB']['mailerroronly'] and !empty($this->jobdata['STATIC']['JOB']['mailaddresslog']))
			$sendmail=true;
		if ($sendmail) {
			$message='';
			//read log
			if (substr($this->jobdata['LOGFILE'],-3)=='.gz') {
				$lines=gzfile($this->jobdata['LOGFILE']);
				foreach ($lines as $line) {
					$message.=$line;
				}
			} else {
				$message=file_get_contents($this->jobdata['LOGFILE']);
			}

			if (empty($this->jobdata['STATIC']['CFG']['mailsndname']))
				$headers = 'From: '.$this->jobdata['STATIC']['CFG']['mailsndname'].' <'.$this->jobdata['STATIC']['CFG']['mailsndemail'].'>' . "\r\n";
			else
				$headers = 'From: '.$this->jobdata['STATIC']['CFG']['mailsndemail'] . "\r\n";
			//special subject
			$status='Successful';
			if ($this->jobdata['WORKING']['WARNING']>0)
				$status='Warning';
			if ($this->jobdata['WORKING']['ERROR']>0)
				$status='Error';
			add_filter('wp_mail_content_type',create_function('', 'return "text/html"; '));
			wp_mail($this->jobdata['STATIC']['JOB']['mailaddresslog'],
				sprintf(__('[%3$s] BackWPup log %1$s: %2$s','backwpup'),date_i18n('d-M-Y H:i',$this->jobdata['STATIC']['JOB']['lastrun']),$this->jobdata['STATIC']['JOB']['name'],$status),
				$message,$headers);
		}
		$this->jobdata['WORKING']['STEPDONE']=1;
		$this->jobdata['WORKING']['STEPSDONE'][]='END'; //set done
		$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main_name='working'");
		$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main_name='temp'");
		if (defined('STDIN'))
			_e('Done!','backwpup');
		exit;
	}

	private function _maintenance_mode($enable=false) {
		if (!$this->jobdata['STATIC']['JOB']['maintenance'])
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
				if (is_writable(ABSPATH.'.maintenance'))
					file_put_contents(ABSPATH.'.maintenance','<?php $upgrading = '.current_time('timestamp').'; ?>');
				else
					trigger_error(__('Cannot set Blog to maintenance mode! Root folder is not writable!','backwpup'),E_USER_NOTICE);
			}
		} else {
			trigger_error(__('Set Blog to normal mode','backwpup'),E_USER_NOTICE);
			if ( get_option('wp-maintenance-mode-msqld') ) { //Support for WP Maintenance Mode Plugin
				update_option('wp-maintenance-mode-msqld','0');
			} elseif ($mamo=get_option('plugin_maintenance-mode')) { //Support for Maintenance Mode Plugin
				$mamo['mamo_activate']='off';
				update_option('plugin_maintenance-mode',$mamo);
			} else { //WP Support
				@unlink(ABSPATH.'.maintenance');
			}
		}
	}

	private function _job_inbytes($value) {
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

	private function _need_free_memory($memneed) {
		if (!function_exists('memory_get_usage'))
			return;
		//need memory
		$needmemory=@memory_get_usage(true)+$this->_job_inbytes($memneed);
		// increase Memory
		if ($needmemory>$this->_job_inbytes(ini_get('memory_limit'))) {
			$newmemory=round($needmemory/1024/1024)+1 .'M';
			if ($needmemory>=1073741824)
				$newmemory=round($needmemory/1024/1024/1024) .'G';
			if ($oldmem=@ini_set('memory_limit', $newmemory))
				trigger_error(sprintf(__('Memory increased from %1$s to %2$s','backwpup'),$oldmem,@ini_get('memory_limit')),E_USER_NOTICE);
			else
				trigger_error(sprintf(__('Can not increase memory limit is %1$s','backwpup'),@ini_get('memory_limit')),E_USER_WARNING);
		}
	}

	public function update_stepdone($done) {
		if ($this->jobdata['WORKING']['STEPTODO']>10 and $this->jobdata['STATIC']['JOB']['backuptype']!='sync')
			$this->jobdata['WORKING']['STEPDONE']=$done;
		backwpup_job_update_working_data();
	}

	private function db_dump() {
		global $wpdb,$wp_version;
		trigger_error(sprintf(__('%d. Try for database dump...','backwpup'),$this->jobdata['WORKING']['DB_DUMP']['STEP_TRY']),E_USER_NOTICE);
		if (!isset($this->jobdata['WORKING']['DB_DUMP']['DONETABLE']) or !is_array($this->jobdata['WORKING']['DB_DUMP']['DONETABLE']))
			$this->jobdata['WORKING']['DB_DUMP']['DONETABLE']=array();

		//to backup
		$tablestobackup=array();
		$tables = $wpdb->get_col("SHOW TABLES FROM `".DB_NAME."`"); //get table status
		if (mysql_error())
			trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), $wpdb->last_query),E_USER_ERROR);
		foreach ($tables as $table) {
			if (!in_array($table,$this->jobdata['STATIC']['JOB']['dbexclude']))
				$tablestobackup[]=$table;
		}
		$this->jobdata['WORKING']['STEPTODO']=count($tablestobackup);

		$datevars=array('%d','%D','%l','%N','%S','%w','%z','%W','%F','%m','%M','%n','%t','%L','%o','%Y','%a','%A','%B','%g','%G','%h','%H','%i','%s','%u','%e','%I','%O','%P','%T','%Z','%c','%U');
		$datevalues=array(date_i18n('d'),date_i18n('D'),date_i18n('l'),date_i18n('N'),date_i18n('S'),date_i18n('w'),date_i18n('z'),date_i18n('W'),date_i18n('F'),date_i18n('m'),date_i18n('M'),date_i18n('n'),date_i18n('t'),date_i18n('L'),date_i18n('o'),date_i18n('Y'),date_i18n('a'),date_i18n('A'),date_i18n('B'),date_i18n('g'),date_i18n('G'),date_i18n('h'),date_i18n('H'),date_i18n('i'),date_i18n('s'),date_i18n('u'),date_i18n('e'),date_i18n('I'),date_i18n('O'),date_i18n('P'),date_i18n('T'),date_i18n('Z'),date_i18n('c'),date_i18n('U'));
		$this->jobdata['STATIC']['JOB']['dbdumpfile']=str_replace($datevars,$datevalues,$this->jobdata['STATIC']['JOB']['dbdumpfile']);

		//check compression
		if ($this->jobdata['STATIC']['JOB']['dbdumpfilecompression']=='gz' and !function_exists('gzopen'))
			$this->jobdata['STATIC']['JOB']['dbdumpfilecompression']='';
		if ($this->jobdata['STATIC']['JOB']['dbdumpfilecompression']=='bz2' and !function_exists('bzopen'))
			$this->jobdata['STATIC']['JOB']['dbdumpfilecompression']='';
		//add file ending
		$this->jobdata['STATIC']['JOB']['dbdumpfile'].='.sql';
		if ($this->jobdata['STATIC']['JOB']['dbdumpfilecompression']=='gz' or $this->jobdata['STATIC']['JOB']['dbdumpfilecompression']=='bz2')
			$this->jobdata['STATIC']['JOB']['dbdumpfile'].='.'.$this->jobdata['STATIC']['JOB']['dbdumpfilecompression'];

		//Set maintenance
		$this->_maintenance_mode(true);

		if (count($tablestobackup)==0) { //Check tables to dump
			trigger_error(__('No tables to dump','backwpup'),E_USER_WARNING);
			$this->_maintenance_mode(false);
			$this->jobdata['WORKING']['STEPSDONE'][]='DB_DUMP'; //set done
			return;
		}

		$tablesstatus=$wpdb->get_results("SHOW TABLE STATUS FROM `".DB_NAME."`"); //get table status
		if (mysql_error())
			trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), $wpdb->last_query),E_USER_ERROR);
		foreach ($tablesstatus as $tablestatus) {
			$status[$tablestatus->Name]=$tablestatus;
		}

		if ($this->jobdata['STATIC']['JOB']['dbdumpfilecompression']=='gz')
			$file = gzopen($this->jobdata['STATIC']['CFG']['tempfolder'].$this->jobdata['STATIC']['JOB']['dbdumpfile'], 'wb9');
		elseif ($this->jobdata['STATIC']['JOB']['dbdumpfilecompression']=='bz2')
			$file = bzopen($this->jobdata['STATIC']['CFG']['tempfolder'].$this->jobdata['STATIC']['JOB']['dbdumpfile'], 'w');
		else
			$file = fopen($this->jobdata['STATIC']['CFG']['tempfolder'].$this->jobdata['STATIC']['JOB']['dbdumpfile'], 'wb');

		if (!$file) {
			trigger_error(sprintf(__('Can not create database dump file! "%s"','backwpup'),$this->jobdata['STATIC']['JOB']['dbdumpfile']),E_USER_ERROR);
			$this->_maintenance_mode(false);
			return;
		}

		$dbdumpheader= "-- ---------------------------------------------------------".BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader.= "-- Dumped with BackWPup ver.: ".BACKWPUP_VERSION.BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader.= "-- Plugin for WordPress ".$wp_version." by Daniel Huesken".BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader.= "-- http://backwpup.com".BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader.= "-- Blog Name: ".get_bloginfo('name').BACKWPUP_LINE_SEPARATOR;
		if (defined('WP_SITEURL'))
			$dbdumpheader.= "-- Blog URL: ".trailingslashit(WP_SITEURL).BACKWPUP_LINE_SEPARATOR;
		else
			$dbdumpheader.= "-- Blog URL: ".trailingslashit(get_option('siteurl')).BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader.= "-- Blog ABSPATH: ".trailingslashit(str_replace('\\','/',ABSPATH)).BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader.= "-- Table Prefix: ".$wpdb->prefix.BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader.= "-- Database Name: ".DB_NAME.BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader.= "-- Dumped on: ".date_i18n('Y-m-d H:i.s').BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader.= "-- ---------------------------------------------------------".BACKWPUP_LINE_SEPARATOR.BACKWPUP_LINE_SEPARATOR;
		//for better import with mysql client
		$dbdumpheader.= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;".BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader.= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;".BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader.= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;".BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader.= "/*!40101 SET NAMES '".mysql_client_encoding()."' */;".BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader.= "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;".BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader.= "/*!40103 SET TIME_ZONE='".$wpdb->get_var("SELECT @@time_zone")."' */;".BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader.= "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;".BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader.= "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;".BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader.= "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;".BACKWPUP_LINE_SEPARATOR;
		$dbdumpheader.= "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;".BACKWPUP_LINE_SEPARATOR.BACKWPUP_LINE_SEPARATOR;
		if ($this->jobdata['STATIC']['JOB']['dbdumpfilecompression']=='gz')
			gzwrite($file, $dbdumpheader);
		elseif ($this->jobdata['STATIC']['JOB']['dbdumpfilecompression']=='bz2')
			bzwrite($file, $dbdumpheader);
		else
			fwrite($file, $dbdumpheader);

		//make table dumps
		foreach($tablestobackup as $table) {
			if (in_array($table, $this->jobdata['WORKING']['DB_DUMP']['DONETABLE']))
				continue;
			trigger_error(sprintf(__('Dump database table "%s"','backwpup'),$table),E_USER_NOTICE);
			$this->_need_free_memory(($status[$table]->Data_length+$status[$table]->Index_length)*2); //get more memory if needed
			$this->_update_working_data();

			$tablecreate=BACKWPUP_LINE_SEPARATOR."--".BACKWPUP_LINE_SEPARATOR."-- Table structure for table $table".BACKWPUP_LINE_SEPARATOR."--".BACKWPUP_LINE_SEPARATOR.BACKWPUP_LINE_SEPARATOR;
			$tablecreate.="DROP TABLE IF EXISTS `".$table."`;".BACKWPUP_LINE_SEPARATOR;
			$tablecreate.="/*!40101 SET @saved_cs_client     = @@character_set_client */;".BACKWPUP_LINE_SEPARATOR;
			$tablecreate.="/*!40101 SET character_set_client = '".mysql_client_encoding()."' */;".BACKWPUP_LINE_SEPARATOR;
			//Dump the table structure
			$tablestruc=$wpdb->get_row("SHOW CREATE TABLE `".$table."`",'ARRAY_A');
			if (mysql_error()) {
				trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), "SHOW CREATE TABLE `".$table."`"),E_USER_ERROR);
				return false;
			}
			$tablecreate.=$tablestruc['Create Table'].";".BACKWPUP_LINE_SEPARATOR;
			$tablecreate.="/*!40101 SET character_set_client = @saved_cs_client */;".BACKWPUP_LINE_SEPARATOR;

			if ($this->jobdata['STATIC']['JOB']['dbdumpfilecompression']=='gz')
				gzwrite($file, $tablecreate);
			elseif ($this->jobdata['STATIC']['JOB']['dbdumpfilecompression']=='bz2')
				bzwrite($file, $tablecreate);
			else
				fwrite($file, $tablecreate);

			//get data from table
			$datas=$wpdb->get_results("SELECT * FROM `".$table."`",'ARRAY_N');
			if (mysql_error()) {
				trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), $wpdb->last_query),E_USER_ERROR);
				return false;
			}
			//get key information
			$keys=$wpdb->get_col_info('name',-1);

			//build key string
			$keystring='';
			if (!$this->jobdata['STATIC']['JOB']['dbshortinsert'])
				$keystring=" (`".implode("`, `",$keys)."`)";
			//colem infos
			for ($i=0;$i<count($keys);$i++) {
				$colinfo[$i]['numeric']=$wpdb->get_col_info('numeric',$i);
				$colinfo[$i]['type']=$wpdb->get_col_info('type',$i);
				$colinfo[$i]['blob']=$wpdb->get_col_info('blob',$i);
			}

			$tabledata=BACKWPUP_LINE_SEPARATOR."--".BACKWPUP_LINE_SEPARATOR."-- Dumping data for table $table".BACKWPUP_LINE_SEPARATOR."--".BACKWPUP_LINE_SEPARATOR.BACKWPUP_LINE_SEPARATOR;

			if ($status[$table]->Engine=='MyISAM')
				$tabledata.="/*!40000 ALTER TABLE `".$table."` DISABLE KEYS */;".BACKWPUP_LINE_SEPARATOR;

			if ($this->jobdata['STATIC']['JOB']['dbdumpfilecompression']=='gz')
				gzwrite($file, $tabledata);
			elseif ($this->jobdata['STATIC']['JOB']['dbdumpfilecompression']=='bz2')
				bzwrite($file, $tabledata);
			else
				fwrite($file, $tabledata);
			$tabledata='';

			$querystring='';
			foreach ($datas as $data) {
				$values = array();
				foreach($data as $key => $value) {
					if(is_null($value) or !isset($value)) // Make Value NULL to string NULL
						$value = "NULL";
					elseif($colinfo[$key]['numeric']==1 and $colinfo[$key]['type']!='timestamp' and $colinfo[$key]['blob']!=1)//is value numeric no esc
						$value = empty($value) ? 0 : $value;
					else
						$value = "'".mysql_real_escape_string($value)."'";
					$values[] = $value;
				}
				if (empty($querystring))
					$querystring="INSERT INTO `".$table."`".$keystring." VALUES".BACKWPUP_LINE_SEPARATOR;
				if (strlen($querystring)<=50000) { //write dump on more than 50000 chars.
					$querystring.="(".implode(", ",$values)."),".BACKWPUP_LINE_SEPARATOR;
				} else {
					$querystring.="(".implode(", ",$values).");".BACKWPUP_LINE_SEPARATOR;
					if ($this->jobdata['STATIC']['JOB']['dbdumpfilecompression']=='gz')
						gzwrite($file, $querystring);
					elseif ($this->jobdata['STATIC']['JOB']['dbdumpfilecompression']=='bz2')
						bzwrite($file, $querystring);
					else
						fwrite($file, $querystring);
					$querystring='';
				}
			}
			if (!empty($querystring)) //dump rest
				$tabledata=substr($querystring,0,-2).";".BACKWPUP_LINE_SEPARATOR;

			if ($status[$table]->Engine=='MyISAM')
				$tabledata.="/*!40000 ALTER TABLE `".$table."` ENABLE KEYS */;".BACKWPUP_LINE_SEPARATOR;

			if ($this->jobdata['STATIC']['JOB']['dbdumpfilecompression']=='gz')
				gzwrite($file, $tabledata);
			elseif ($this->jobdata['STATIC']['JOB']['dbdumpfilecompression']=='bz2')
				bzwrite($file, $tabledata);
			else
				fwrite($file, $tabledata);

			$wpdb->flush();

			$this->jobdata['WORKING']['DB_DUMP']['DONETABLE'][]=$table;
			$this->jobdata['WORKING']['STEPDONE']=count($this->jobdata['WORKING']['DB_DUMP']['DONETABLE']);
		}

		//for better import with mysql client
		$dbdumpfooter= BACKWPUP_LINE_SEPARATOR."--".BACKWPUP_LINE_SEPARATOR."-- Delete not needed values on backwpup table".BACKWPUP_LINE_SEPARATOR."--".BACKWPUP_LINE_SEPARATOR.BACKWPUP_LINE_SEPARATOR;
		$dbdumpfooter.= "DELETE FROM `".$wpdb->prefix."backwpup` WHERE `main_name`='TEMP';".BACKWPUP_LINE_SEPARATOR;
		$dbdumpfooter.= "DELETE FROM `".$wpdb->prefix."backwpup` WHERE `main_name`='WORKING';".BACKWPUP_LINE_SEPARATOR.BACKWPUP_LINE_SEPARATOR.BACKWPUP_LINE_SEPARATOR;
		$dbdumpfooter.= "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;".BACKWPUP_LINE_SEPARATOR;
		$dbdumpfooter.= "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;".BACKWPUP_LINE_SEPARATOR;
		$dbdumpfooter.= "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;".BACKWPUP_LINE_SEPARATOR;
		$dbdumpfooter.= "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;".BACKWPUP_LINE_SEPARATOR;
		$dbdumpfooter.= "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;".BACKWPUP_LINE_SEPARATOR;
		$dbdumpfooter.= "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;".BACKWPUP_LINE_SEPARATOR;
		$dbdumpfooter.= "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;".BACKWPUP_LINE_SEPARATOR;
		$dbdumpfooter.= "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;".BACKWPUP_LINE_SEPARATOR;

		if ($this->jobdata['STATIC']['JOB']['dbdumpfilecompression']=='gz') {
			gzwrite($file, $dbdumpfooter);
			gzclose($file);
		} elseif ($this->jobdata['STATIC']['JOB']['dbdumpfilecompression']=='bz2') {
			bzwrite($file, $dbdumpfooter);
			bzclose($file);
		} else {
			fwrite($file, $dbdumpfooter);
			fclose($file);
		}

		trigger_error(__('Database dump done!','backwpup'),E_USER_NOTICE);

		//add database file to backup files
		if (is_readable($this->jobdata['STATIC']['CFG']['tempfolder'].$this->jobdata['STATIC']['JOB']['dbdumpfile'])) {
			$this->jobdata['WORKING']['EXTRAFILESTOBACKUP'][]=$this->jobdata['STATIC']['CFG']['tempfolder'].$this->jobdata['STATIC']['JOB']['dbdumpfile'];
			trigger_error(sprintf(__('Added database dump "%1$s" with %2$s to backup file list','backwpup'),$this->jobdata['STATIC']['JOB']['dbdumpfile'],backwpup_formatBytes(filesize($this->jobdata['STATIC']['CFG']['tempfolder'].$this->jobdata['STATIC']['JOB']['dbdumpfile']))),E_USER_NOTICE);
		}
		//Back from maintenance
		$this->_maintenance_mode(false);
		$this->jobdata['WORKING']['STEPSDONE'][]='DB_DUMP'; //set done
	}

	private function db_check() {
		global $wpdb;
		trigger_error(sprintf(__('%d. Try for database check...','backwpup'),$this->jobdata['WORKING']['DB_CHECK']['STEP_TRY']),E_USER_NOTICE);
		if (!isset($this->jobdata['WORKING']['DB_CHECK']['DONETABLE']) or !is_array($this->jobdata['WORKING']['DB_CHECK']['DONETABLE']))
			$this->jobdata['WORKING']['DB_CHECK']['DONETABLE']=array();

		//to backup
		$tablestobackup=array();
		$tables = $wpdb->get_col("SHOW TABLES FROM `".DB_NAME."`"); //get table status
		if (mysql_error())
			trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), $wpdb->last_query),E_USER_ERROR);
		foreach ($tables as $table) {
			if (!in_array($table,$this->jobdata['STATIC']['JOB']['dbexclude']))
				$tablestobackup[]=$table;
		}
		//Set num of todos
		$this->jobdata['WORKING']['STEPTODO']=sizeof($tablestobackup);

		//check tables
		if (count($tablestobackup)>0) {
			$this->_maintenance_mode(true);
			foreach ($tablestobackup as $table) {
				if (in_array($table, $this->jobdata['WORKING']['DB_CHECK']['DONETABLE']))
					continue;
				$check = $wpdb->get_row("CHECK TABLE `".$table."` MEDIUM");
				if (mysql_error()) {
					trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), $wpdb->last_query),E_USER_ERROR);
					continue;
				}
				if ($check->Msg_text=='OK')
					trigger_error(sprintf(__('Result of table check for %1$s is: %2$s','backwpup'), $table, $check->Msg_text),E_USER_NOTICE);
				elseif (strtolower($check->Msg_type)=='warning')
					trigger_error(sprintf(__('Result of table check for %1$s is: %2$s','backwpup'), $table, $check->Msg_text),E_USER_WARNING);
				else
					trigger_error(sprintf(__('Result of table check for %1$s is: %2$s','backwpup'), $table, $check->Msg_text),E_USER_ERROR);

				//Try to Repair tabele
				if ($check->Msg_text!='OK') {
					$repair = $wpdb->get_row('REPAIR TABLE `'.$table.'`');
					if (mysql_error()) {
						trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), $wpdb->last_query),E_USER_ERROR);
						continue;
					}
					if ($repair->Msg_type=='OK')
						trigger_error(sprintf(__('Result of table repair for %1$s is: %2$s','backwpup'), $table, $repair->Msg_text),E_USER_NOTICE);
					elseif (strtolower($repair->Msg_type)=='warning')
						trigger_error(sprintf(__('Result of table repair for %1$s is: %2$s','backwpup'), $table, $repair->Msg_text),E_USER_WARNING);
					else
						trigger_error(sprintf(__('Result of table repair for %1$s is: %2$s','backwpup'), $table, $repair->Msg_text),E_USER_ERROR);
				}
				$this->jobdata['WORKING']['DB_CHECK']['DONETABLE'][]=$table;
				$this->jobdata['WORKING']['STEPDONE']=sizeof($this->jobdata['WORKING']['DB_CHECK']['DONETABLE']);
			}
			$this->_maintenance_mode(false);
			trigger_error(__('Database check done!','backwpup'),E_USER_NOTICE);
		} else {
			trigger_error(__('No tables to check','backwpup'),E_USER_WARNING);
		}
		$this->jobdata['WORKING']['STEPSDONE'][]='DB_CHECK'; //set done
	}

	private function db_optimize() {
		global $wpdb;
		trigger_error(sprintf(__('%d. Try for database optimize...','backwpup'),$this->jobdata['WORKING']['DB_OPTIMIZE']['STEP_TRY']),E_USER_NOTICE);
		if (!isset($this->jobdata['WORKING']['DB_OPTIMIZE']['DONETABLE']) or !is_array($this->jobdata['WORKING']['DB_OPTIMIZE']['DONETABLE']))
			$this->jobdata['WORKING']['DB_OPTIMIZE']['DONETABLE']=array();

		//to backup
		$tablestobackup=array();
		$tables = $wpdb->get_col("SHOW TABLES FROM `".DB_NAME."`"); //get table status
		if (mysql_error())
			trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), $wpdb->last_query),E_USER_ERROR);
		foreach ($tables as $table) {
			if (!in_array($table,$this->jobdata['STATIC']['JOB']['dbexclude']))
				$tablestobackup[]=$table;
		}
		//Set num of todos
		$this->jobdata['WORKING']['STEPTODO']=count($tablestobackup);

		if (count($tablestobackup)>0) {
			$this->_maintenance_mode(true);
			foreach ($tablestobackup as $table) {
				if (in_array($table, $this->jobdata['WORKING']['DB_OPTIMIZE']['DONETABLE']))
					continue;
				$optimize = $wpdb->get_row("OPTIMIZE TABLE `".$table."`");
				if (mysql_error())
					trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), $wpdb->last_query),E_USER_ERROR);
				elseif (strtolower($optimize->Msg_type)=='error')
					trigger_error(sprintf(__('Result of table optimize for %1$s is: %2$s','backwpup'), $table, $optimize->Msg_text),E_USER_ERROR);
				elseif (strtolower($optimize->Msg_type)=='warning')
					trigger_error(sprintf(__('Result of table optimize for %1$s is: %2$s','backwpup'), $table, $optimize->Msg_text),E_USER_WARNING);
				else
					trigger_error(sprintf(__('Result of table optimize for %1$s is: %2$s','backwpup'), $table, $optimize->Msg_text),E_USER_NOTICE);

				$wpdb->get_row("ALTER TABLE `".$table."` ENGINE='InnoDB'");
				if (mysql_error())
					trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), $wpdb->last_query),E_USER_ERROR);
				else
					trigger_error(sprintf(__('InnoDB Table %1$s optimize done','backwpup'), $table),E_USER_NOTICE);

				$this->jobdata['WORKING']['DB_OPTIMIZE']['DONETABLE'][]=$table;
				$this->jobdata['WORKING']['STEPDONE']=count($this->jobdata['WORKING']['DB_OPTIMIZE']['DONETABLE']);
			}
			trigger_error(__('Database optimize done!','backwpup'),E_USER_NOTICE);
			$this->_maintenance_mode(false);
		} else {
			trigger_error(__('No tables to optimize','backwpup'),E_USER_WARNING);
		}
		$this->jobdata['WORKING']['STEPSDONE'][]='DB_OPTIMIZE'; //set done
	}

	private function wp_export() {
		$this->jobdata['WORKING']['STEPTODO']=1;
		trigger_error(sprintf(__('%d. Trying for WordPress Export to XML file...','backwpup'),$this->jobdata['WORKING']['WP_EXPORT']['STEP_TRY']),E_USER_NOTICE);
		$this->_need_free_memory('5M'); //5MB free memory
		//build filename
		$datevars=array('%d','%D','%l','%N','%S','%w','%z','%W','%F','%m','%M','%n','%t','%L','%o','%Y','%a','%A','%B','%g','%G','%h','%H','%i','%s','%u','%e','%I','%O','%P','%T','%Z','%c','%U');
		$datevalues=array(date_i18n('d'),date_i18n('D'),date_i18n('l'),date_i18n('N'),date_i18n('S'),date_i18n('w'),date_i18n('z'),date_i18n('W'),date_i18n('F'),date_i18n('m'),date_i18n('M'),date_i18n('n'),date_i18n('t'),date_i18n('L'),date_i18n('o'),date_i18n('Y'),date_i18n('a'),date_i18n('A'),date_i18n('B'),date_i18n('g'),date_i18n('G'),date_i18n('h'),date_i18n('H'),date_i18n('i'),date_i18n('s'),date_i18n('u'),date_i18n('e'),date_i18n('I'),date_i18n('O'),date_i18n('P'),date_i18n('T'),date_i18n('Z'),date_i18n('c'),date_i18n('U'));
		$this->jobdata['STATIC']['JOB']['wpexportfile']=str_replace($datevars,$datevalues,$this->jobdata['STATIC']['JOB']['wpexportfile']);

		//check compression
		if ($this->jobdata['STATIC']['JOB']['wpexportfilecompression']=='gz' and !function_exists('gzopen'))
			$this->jobdata['STATIC']['JOB']['wpexportfilecompression']='';
		if ($this->jobdata['STATIC']['JOB']['wpexportfilecompression']=='bz2' and !function_exists('bzopen'))
			$this->jobdata['STATIC']['JOB']['wpexportfilecompression']='';
		//add file ending
		$this->jobdata['STATIC']['JOB']['wpexportfile'].='.xml';
		if ($this->jobdata['STATIC']['JOB']['wpexportfilecompression']=='gz' or $this->jobdata['STATIC']['JOB']['wpexportfilecompression']=='bz2')
			$this->jobdata['STATIC']['JOB']['wpexportfile'].='.'.$this->jobdata['STATIC']['JOB']['wpexportfilecompression'];

		if ($this->jobdata['STATIC']['JOB']['wpexportfilecompression']=='gz')
			$this->jobdata['WORKING']['filehandel']= gzopen($this->jobdata['STATIC']['CFG']['tempfolder'].$this->jobdata['STATIC']['JOB']['wpexportfile'], 'wb9');
		elseif ($this->jobdata['STATIC']['JOB']['wpexportfilecompression']=='bz2')
			$this->jobdata['WORKING']['filehandel'] = bzopen($this->jobdata['STATIC']['CFG']['tempfolder'].$this->jobdata['STATIC']['JOB']['wpexportfile'], 'w');
		else
			$this->jobdata['WORKING']['filehandel'] = fopen($this->jobdata['STATIC']['CFG']['tempfolder'].$this->jobdata['STATIC']['JOB']['wpexportfile'], 'wb');

		//include WP export function
		require_once(ABSPATH.'wp-admin/includes/export.php');
		error_reporting(0); //disable error reporting
		ob_start(array($this,'_wp_export_ob_bufferwrite'),1024);//start output buffering
		export_wp();		//WP export
		ob_end_clean(); 	//End output buffering
		error_reporting(E_ALL | E_STRICT); //enable error reporting

		if ($this->jobdata['STATIC']['JOB']['wpexportfilecompression']=='gz') {
			gzclose($this->jobdata['WORKING']['filehandel']);
		} elseif ($this->jobdata['STATIC']['JOB']['wpexportfilecompression']=='bz2') {
			bzclose($this->jobdata['WORKING']['filehandel']);
		} else {
			fclose($this->jobdata['WORKING']['filehandel']);
		}

		//add XML file to backup files
		if (is_readable($this->jobdata['STATIC']['CFG']['tempfolder'].$this->jobdata['STATIC']['JOB']['wpexportfile'])) {
			$this->jobdata['WORKING']['EXTRAFILESTOBACKUP'][]=$this->jobdata['STATIC']['CFG']['tempfolder'].$this->jobdata['STATIC']['JOB']['wpexportfile'];
			trigger_error(sprintf(__('Added XML export "%1$s" with %2$s to backup file list','backwpup'),$this->jobdata['STATIC']['JOB']['wpexportfile'],backwpup_formatBytes(filesize($this->jobdata['STATIC']['CFG']['tempfolder'].$this->jobdata['STATIC']['JOB']['wpexportfile']))),E_USER_NOTICE);
		}
		$this->jobdata['WORKING']['STEPDONE']=1;
		$this->jobdata['WORKING']['STEPSDONE'][]='WP_EXPORT'; //set done
	}

	public function _wp_export_ob_bufferwrite($output) {
		if ($this->jobdata['STATIC']['JOB']['wpexportfilecompression']=='gz') {
			gzwrite($this->jobdata['WORKING']['filehandel'], $output);
		} elseif ($this->jobdata['STATIC']['JOB']['wpexportfilecompression']=='bz2') {
			bzwrite($this->jobdata['WORKING']['filehandel'], $output);
		} else {
			fwrite($this->jobdata['WORKING']['filehandel'], $output);
		}
		$this->_update_working_data();
	}

}

function backwpup_job_curl_progressfunction($handle) {
	if (defined('CURLOPT_PROGRESSFUNCTION')) {
		curl_setopt($handle, CURLOPT_NOPROGRESS, false);
		curl_setopt($handle, CURLOPT_PROGRESSFUNCTION, 'backwpup_job_curl_progresscallback');
		curl_setopt($handle, CURLOPT_BUFFERSIZE, 512);
	}
}

function backwpup_job_curl_progresscallback($download_size, $downloaded, $upload_size, $uploaded) {
	global $backwpup_job_object;
	$backwpup_job_object->update_stepdone($uploaded);
}

//start class
$backwpup_job_object=new BackWPup_job();
?>