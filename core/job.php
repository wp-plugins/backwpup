<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

class BackWPup_job {

	private $jobdata = false;
	private $jobstarttype = '';

	public function __construct($starttype,$jobid=0) {
		$this->jobstarttype=$starttype;
		//get job data
		if ( in_array($this->jobstarttype, array( 'runnow', 'cronrun', 'runext', 'runcmd','apirun' )) )
			$this->start((int)$jobid);
		else
			$this->jobdata = backwpup_get_option('working', 'data');
		add_filter('query',array($this,'_count_querys'));
		//set function for PHP user defined error handling
		$this->jobdata['PHP']['INI']['ERROR_LOG'] = ini_get('error_log');
		$this->jobdata['PHP']['INI']['LOG_ERRORS'] = ini_get('log_errors');
		$this->jobdata['PHP']['INI']['DISPLAY_ERRORS'] = ini_get('display_errors');
		@ini_set('error_log', $this->jobdata['LOGFILE']);
		@ini_set('display_errors', 'Off');
		@ini_set('log_errors', 'On');
		set_error_handler(array( $this, '_error_handler' ), E_ALL | E_STRICT);
		//Check Folder
		if (!empty($this->jobdata['BACKUPDIR']) and $this->jobdata['BACKUPDIR']!=backwpup_get_option('cfg','tempfolder') )
			$this->_check_folder($this->jobdata['BACKUPDIR']);
		if (backwpup_get_option('cfg','tempfolder'))
			$this->_check_folder(backwpup_get_option('cfg','tempfolder'));
		if (backwpup_get_option('cfg','logfolder'))
			$this->_check_folder(backwpup_get_option('cfg','logfolder'));
		//Check double running and inactivity
		if ( $this->jobdata['PID'] != getmypid() and $this->jobdata['TIMESTAMP'] > (current_time('timestamp') - 500) and $this->jobstarttype == 'restarttime' ) {
			trigger_error(__('Job restart terminated, because other job runs!', 'backwpup'), E_USER_ERROR);
			die();
		} elseif ( $this->jobstarttype == 'restarttime' ) {
			trigger_error(__('Job restarted, because of inactivity!', 'backwpup'), E_USER_ERROR);
		} elseif ( $this->jobdata['PID'] != getmypid() and $this->jobdata['PID'] != 0 and $this->jobdata['timestamp'] > (time() - 500) ) {
			trigger_error(sprintf(__('Second prozess is running, but old job runs! Start type is %s', 'backwpup'), $this->jobstarttype), E_USER_ERROR);
			die();
		}
		//set Pid
		$this->jobdata['PID'] = getmypid();
		// execute function on job shutdown
		register_shutdown_function(array( $this, '__destruct' ));
		if ( function_exists('pcntl_signal') ) {
			declare(ticks = 1); //set ticks
			pcntl_signal(15, array( $this, '__destruct' )); //SIGTERM
			//pcntl_signal(9, array($this,'__destruct')); //SIGKILL
			pcntl_signal(2, array( $this, '__destruct' )); //SIGINT
		}
		$this->_update_working_data(true);
		// Working step by step
		foreach ( $this->jobdata['STEPS'] as $step ) {
			//Set next step
			if ( !isset($this->jobdata[$step]['STEP_TRY']) ) {
				$this->jobdata[$step]['STEP_TRY'] = 0;
				$this->jobdata['STEPDONE'] = 0;
				$this->jobdata['STEPTODO'] = 0;
			}
			//update running file
			$this->_update_working_data(true);
			//Run next step
			if ( !in_array($step, $this->jobdata['STEPSDONE']) ) {
				if ( method_exists($this, strtolower($step)) ) {
					while ( $this->jobdata[$step]['STEP_TRY'] < backwpup_get_option('cfg','jobstepretry') ) {
						if ( in_array($step, $this->jobdata['STEPSDONE']) )
							break;
						$this->jobdata[$step]['STEP_TRY']++;
						$this->_update_working_data(true);
						call_user_func(array( $this, strtolower($step) ));
					}
					if ( $this->jobdata[$step]['STEP_TRY'] >= backwpup_get_option('cfg','jobstepretry') )
						trigger_error(__('Step aborted has too many tries!', 'backwpup'), E_USER_ERROR);
				} else {
					trigger_error(sprintf(__('Can not find job step method %s!', 'backwpup'), strtolower($step)), E_USER_ERROR);
					$this->jobdata['STEPSDONE'][] = $step;
				}
			}
		}
	}

	public function __destruct() {
		$args = func_get_args();
		//nothing on empty
		if ( empty($this->jobdata['LOGFILE']) )
			return;
		//Put last error to log if one
		$lasterror = error_get_last();
		if ( $lasterror['type'] == E_ERROR or $lasterror['type'] == E_PARSE or $lasterror['type'] == E_CORE_ERROR or $lasterror['type'] == E_CORE_WARNING or $lasterror['type'] == E_COMPILE_ERROR or $lasterror['type'] == E_COMPILE_WARNING )
			$this->_error_handler($lasterror['type'], $lasterror['message'], $lasterror['file'], $lasterror['line'],false);
		//Put sigterm to log
		if ( !empty($args[0]) )
			$this->_error_handler(E_USER_ERROR, sprintf(__('Signal %d send to script!', 'backwpup')), __FILE__, __LINE__,false);
		//no more restarts
		$this->jobdata['RESTART']++;
		if ( (defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON) or $this->jobdata['RESTART'] >= backwpup_get_option('cfg','jobscriptretry') ) { //only x restarts allowed
			if ( defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON )
				$this->_error_handler(E_USER_ERROR, __('Can not restart on alternate cron....', 'backwpup'), __FILE__, __LINE__,false);
			else
				$this->_error_handler(E_USER_ERROR, __('To many restarts....', 'backwpup'), __FILE__, __LINE__,false);
			$this->end();
			exit;
		}
		if ( !backwpup_get_option('working', 'data',false) )
			exit;
		//set PID to 0
		$this->jobdata['PID'] = 0;
		//Restart job
		$this->_update_working_data(true);
		$this->_error_handler(E_USER_NOTICE, sprintf(__('%d. Script stop! Will started again now!', 'backwpup'), $this->jobdata['RESTART']), __FILE__, __LINE__,false);
		$raw_response=backwpup_jobrun_url('restart','',true);
		if (300<= wp_remote_retrieve_response_code($raw_response) or is_wp_error($raw_response))
			$this->_error_handler(E_USER_ERROR, strip_tags(json_encode($raw_response)), __FILE__, __LINE__,false);
		exit;
	}

	private function start($jobid) {
		global $wp_version;
		if (empty($jobid))
			return;
		//make start on cli mode
		if ( defined('STDIN') )
			_e('Run!', 'backwpup');
		//clean var
		$this->jobdata = array();
		//check exists gzip functions
		if ( !function_exists('gzopen') )
			backwpup_update_option('cfg','gzlogs',false);
		//set Logfile
		$this->jobdata['LOGFILE'] = backwpup_get_option('cfg','logfolder') . 'backwpup_log_' . date_i18n('Y-m-d_H-i-s') . '.html';
		//Set job data
		$this->jobdata['JOBID'] = $jobid;
		$this->jobdata['JOBMAIN'] = 'job_'.$jobid;
		//Set job start settings
		backwpup_update_option($this->jobdata['JOBMAIN'], 'starttime', current_time('timestamp')); //set start time for job
		backwpup_update_option($this->jobdata['JOBMAIN'], 'logfile', $this->jobdata['LOGFILE']); //Set current logfile
		backwpup_update_option($this->jobdata['JOBMAIN'], 'lastbackupdownloadurl', '');
		//only for jobs that makes backups
		if ( in_array('FILE', backwpup_get_option($this->jobdata['JOBMAIN'],'type')) or in_array('DB', backwpup_get_option($this->jobdata['JOBMAIN'],'type')) or in_array('WPEXP', backwpup_get_option($this->jobdata['JOBMAIN'],'type')) ) {
			//make empty file list
			if ( backwpup_get_option($this->jobdata['JOBMAIN'],'backuptype') == 'archive' ) {
				//set Backup folder to temp folder if not set
				$this->jobdata['BACKUPDIR']=backwpup_get_option($this->jobdata['JOBMAIN'],'BACKUPDIR');
				if ( !$this->jobdata['BACKUPDIR'] or $this->jobdata['BACKUPDIR'] == '/' )
					$this->jobdata['BACKUPDIR'] = backwpup_get_option('cfg','tempfolder');
				//Create backup archive full file name
				$this->jobdata['BACKUPFILE'] = backwpup_get_option($this->jobdata['JOBMAIN'],'fileprefix') . date_i18n('Y-m-d_H-i-s') . backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart');
			}
		}
		$this->jobdata['BACKUPFILESIZE'] = 0;
		$this->jobdata['PID'] = 0;
		$this->jobdata['WARNING'] = 0;
		$this->jobdata['ERROR'] = 0;
		$this->jobdata['RESTART'] = 0;
		$this->jobdata['STEPSDONE'] = array();
		$this->jobdata['STEPTODO'] = 0;
		$this->jobdata['STEPDONE'] = 0;
		$this->jobdata['STEPSPERSENT'] = 0;
		$this->jobdata['STEPPERSENT'] = 0;
		$this->jobdata['TIMESTAMP'] = current_time('timestamp');
		$this->jobdata['ENDINPROGRESS'] = false;
		$this->jobdata['EXTRAFILESTOBACKUP'] = array();
		$this->jobdata['FOLDERLIST'] = array();
		$this->jobdata['FILEEXCLUDES']=explode(',',trim(backwpup_get_option($this->jobdata['JOBMAIN'],'fileexclude')));
		$this->jobdata['FILEEXCLUDES']=array_unique($this->jobdata['FILEEXCLUDES']);
		$this->jobdata['DBDUMPFILE'] = false;
		$this->jobdata['WPEXPORTFILE'] = false;
		$this->jobdata['COUNT']['SQLQUERRYS']=0;
		$this->jobdata['COUNT']['FILES']=0;
		$this->jobdata['COUNT']['FILESIZE']=0;
		$this->jobdata['COUNT']['FOLDER']=0;
		$this->jobdata['COUNT']['FILESINFOLDER']=0;
		$this->jobdata['COUNT']['FILESIZEINFOLDER']=0;
		//create path to remove
		if ( trailingslashit(str_replace('\\', '/', ABSPATH)) == '/' or trailingslashit(str_replace('\\', '/', ABSPATH)) == '' )
			$this->jobdata['REMOVEPATH'] = '';
		else
			$this->jobdata['REMOVEPATH'] = trailingslashit(str_replace('\\', '/', ABSPATH));
		//build working steps
		$this->jobdata['STEPS'] = array();
		//setup job steps
		if ( in_array('DB', backwpup_get_option($this->jobdata['JOBMAIN'],'type')) )
			$this->jobdata['STEPS'][] = 'DB_DUMP';
		if ( in_array('WPEXP', backwpup_get_option($this->jobdata['JOBMAIN'],'type')) )
			$this->jobdata['STEPS'][] = 'WP_EXPORT';
		if ( in_array('FILE', backwpup_get_option($this->jobdata['JOBMAIN'],'type')) )
			$this->jobdata['STEPS'][] = 'FOLDER_LIST';
		if ( in_array('DB', backwpup_get_option($this->jobdata['JOBMAIN'],'type')) or in_array('WPEXP', backwpup_get_option($this->jobdata['JOBMAIN'],'type')) or in_array('FILE', backwpup_get_option($this->jobdata['JOBMAIN'],'type')) ) {
			if ( backwpup_get_option($this->jobdata['JOBMAIN'],'backuptype') == 'archive' ) {
				$this->jobdata['STEPS'][] = 'CREATE_ARCHIVE';
				$backuptypeextension = '';
			} elseif ( backwpup_get_option($this->jobdata['JOBMAIN'],'backuptype') == 'sync' ) {
				$backuptypeextension = '_SYNC';
			}
			//ADD Destinations
			if ( backwpup_get_option($this->jobdata['JOBMAIN'],'BACKUPDIR') and backwpup_get_option($this->jobdata['JOBMAIN'],'BACKUPDIR') != '/')
				$this->jobdata['STEPS'][] = 'DEST_FOLDER' . $backuptypeextension;
			if ( backwpup_get_option($this->jobdata['JOBMAIN'],'mailaddress') and backwpup_get_option($this->jobdata['JOBMAIN'],'backuptype') == 'archive' )
				$this->jobdata['STEPS'][] = 'DEST_MAIL';
			if ( backwpup_get_option($this->jobdata['JOBMAIN'],'ftphost') and backwpup_get_option($this->jobdata['JOBMAIN'],'ftpuser') and backwpup_get_option($this->jobdata['JOBMAIN'],'ftppass') and in_array('FTP', explode(',', strtoupper(BACKWPUP_DESTS))) )
				$this->jobdata['STEPS'][] = 'DEST_FTP' . $backuptypeextension;
			if ( backwpup_get_option($this->jobdata['JOBMAIN'],'dropetoken') and backwpup_get_option($this->jobdata['JOBMAIN'],'dropesecret') and in_array('DROPBOX', explode(',', strtoupper(BACKWPUP_DESTS))) )
				$this->jobdata['STEPS'][] = 'DEST_DROPBOX' . $backuptypeextension;
			if ( backwpup_get_option($this->jobdata['JOBMAIN'],'sugaruser') and backwpup_get_option($this->jobdata['JOBMAIN'],'sugarpass') and backwpup_get_option($this->jobdata['JOBMAIN'],'sugarroot') and in_array('SUGARSYNC', explode(',', strtoupper(BACKWPUP_DESTS))) )
				$this->jobdata['STEPS'][] = 'DEST_SUGARSYNC' . $backuptypeextension;
			if ( backwpup_get_option($this->jobdata['JOBMAIN'],'awsAccessKey') and backwpup_get_option($this->jobdata['JOBMAIN'],'awsSecretKey') and backwpup_get_option($this->jobdata['JOBMAIN'],'awsBucket') and in_array('S3', explode(',', strtoupper(BACKWPUP_DESTS))) )
				$this->jobdata['STEPS'][] = 'DEST_S3' . $backuptypeextension;
			if ( backwpup_get_option($this->jobdata['JOBMAIN'],'GStorageAccessKey') and backwpup_get_option($this->jobdata['JOBMAIN'],'GStorageSecret') and backwpup_get_option($this->jobdata['JOBMAIN'],'GStorageBucket') and in_array('GSTORAGE', explode(',', strtoupper(BACKWPUP_DESTS))) )
				$this->jobdata['STEPS'][] = 'DEST_GSTORAGE' . $backuptypeextension;
			if ( backwpup_get_option($this->jobdata['JOBMAIN'],'rscUsername') and backwpup_get_option($this->jobdata['JOBMAIN'],'rscAPIKey') and backwpup_get_option($this->jobdata['JOBMAIN'],'rscContainer') and in_array('RSC', explode(',', strtoupper(BACKWPUP_DESTS))) )
				$this->jobdata['STEPS'][] = 'DEST_RSC' . $backuptypeextension;
			if ( backwpup_get_option($this->jobdata['JOBMAIN'],'msazureHost') and backwpup_get_option($this->jobdata['JOBMAIN'],'msazureAccName') and backwpup_get_option($this->jobdata['JOBMAIN'],'msazureKey') and backwpup_get_option($this->jobdata['JOBMAIN'],'msazureContainer') and in_array('MSAZURE', explode(',', strtoupper(BACKWPUP_DESTS))) )
				$this->jobdata['STEPS'][] = 'DEST_MSAZURE' . $backuptypeextension;
		}
		if ( in_array('CHECK', backwpup_get_option($this->jobdata['JOBMAIN'],'type')) )
			$this->jobdata['STEPS'][] = 'DB_CHECK';
		if ( in_array('OPTIMIZE', backwpup_get_option($this->jobdata['JOBMAIN'],'type')) )
			$this->jobdata['STEPS'][] = 'DB_OPTIMIZE';
		$this->jobdata['STEPS'][] = 'END';
		//mark all as not done
		foreach ( $this->jobdata['STEPS'] as $step )
			$this->jobdata[$step]['DONE'] = false;
		//must write working data
		backwpup_update_option('working','data',$this->jobdata);
		//create log file
		$fd = fopen($this->jobdata['LOGFILE'], 'w');
		fwrite($fd, "<html>" . BACKWPUP_LINE_SEPARATOR . "<head>" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, "<meta name=\"backwpup_version\" content=\"" . BACKWPUP_VERSION . "\" />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, "<meta name=\"backwpup_logtime\" content=\"" . current_time('timestamp') . "\" />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, str_pad("<meta name=\"backwpup_errors\" content=\"0\" />", 100) . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, str_pad("<meta name=\"backwpup_warnings\" content=\"0\" />", 100) . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, "<meta name=\"backwpup_jobid\" content=\"" . backwpup_get_option($this->jobdata['JOBMAIN'],'jobid') . "\" />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, "<meta name=\"backwpup_jobname\" content=\"" . backwpup_get_option($this->jobdata['JOBMAIN'],'name') . "\" />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, "<meta name=\"backwpup_jobtype\" content=\"" . implode('+', backwpup_get_option($this->jobdata['JOBMAIN'],'type')) . "\" />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, str_pad("<meta name=\"backwpup_backupfilesize\" content=\"0\" />", 100) . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, str_pad("<meta name=\"backwpup_jobruntime\" content=\"0\" />", 100) . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, "<style type=\"text/css\">" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, ".warning {background-color:yellow;}" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, ".error {background-color:red;}" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, "#body {font-family:monospace;font-size:12px;white-space:nowrap;}" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, "</style>" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, "<title>" . sprintf(__('BackWPup log for %1$s from %2$s at %3$s', 'backwpup'), backwpup_get_option($this->jobdata['JOBMAIN'],'name'), date_i18n(get_option('date_format')), date_i18n(get_option('time_format'))) . "</title>" . BACKWPUP_LINE_SEPARATOR . "</head>" . BACKWPUP_LINE_SEPARATOR . "<body id=\"body\">" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, sprintf(__('[INFO]: BackWPup version %1$s, WordPress version %4$s Copyright %2$s %3$s'), BACKWPUP_VERSION, '&copy; 2009-'.date_i18n('Y'), '<a href="http://danielhuesken.de" target="_blank">Daniel H&uuml;sken</a>', $wp_version) . "<br />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, __('[INFO]: BackWPup comes with ABSOLUTELY NO WARRANTY. This is free software, and you are welcome to redistribute it under certain conditions.', 'backwpup') . "<br />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, __('[INFO]: BackWPup job:', 'backwpup') . ' ' . backwpup_get_option($this->jobdata['JOBMAIN'],'jobid') . '. ' . backwpup_get_option($this->jobdata['JOBMAIN'],'name') . '; ' . implode('+', backwpup_get_option($this->jobdata['JOBMAIN'],'type')) . "<br />" . BACKWPUP_LINE_SEPARATOR);
		if ( backwpup_get_option($this->jobdata['JOBMAIN'],'activetype')!='' )
			fwrite($fd, __('[INFO]: BackWPup cron:', 'backwpup') . ' ' . backwpup_get_option($this->jobdata['JOBMAIN'],'cron') . '; ' . date_i18n('D, j M Y @ H:i', backwpup_get_option($this->jobdata['JOBMAIN'],'cronnextrun')) . "<br />" . BACKWPUP_LINE_SEPARATOR);
		if ( $this->jobstarttype == 'cronrun' )
			fwrite($fd, __('[INFO]: BackWPup job started from wp-cron', 'backwpup') . "<br />" . BACKWPUP_LINE_SEPARATOR);
		elseif ( $this->jobstarttype == 'runnow' )
			fwrite($fd, __('[INFO]: BackWPup job started manually', 'backwpup') . "<br />" . BACKWPUP_LINE_SEPARATOR);
		elseif ( $this->jobstarttype == 'runext' )
			fwrite($fd, __('[INFO]: BackWPup job started external from url', 'backwpup') . "<br />" . BACKWPUP_LINE_SEPARATOR);
		elseif ( $this->jobstarttype == 'apirun' )
			fwrite($fd, __('[INFO]: BackWPup job started by its API', 'backwpup') . "<br />" . BACKWPUP_LINE_SEPARATOR);
		elseif ( $this->jobstarttype == 'runcmd' )
			fwrite($fd, __('[INFO]: BackWPup job started form commandline', 'backwpup') . "<br />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, __('[INFO]: PHP ver.:', 'backwpup') . ' ' . phpversion() . '; ' . php_sapi_name() . '; ' . PHP_OS . "<br />" . BACKWPUP_LINE_SEPARATOR);
		if ( (bool)ini_get('safe_mode') )
			fwrite($fd, sprintf(__('[INFO]: PHP Safe mode is ON! Maximum script execution time is %1$d sec.', 'backwpup'), ini_get('max_execution_time')) . "<br />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, sprintf(__('[INFO]: MySQL ver.: %s', 'backwpup'), mysql_result(mysql_query("SELECT VERSION() AS version"), 0)) . "<br />" . BACKWPUP_LINE_SEPARATOR);
		if ( function_exists('curl_init') ) {
			$curlversion = curl_version();
			fwrite($fd, sprintf(__('[INFO]: curl ver.: %1$s; %2$s', 'backwpup'), $curlversion['version'], $curlversion['ssl_version']) . "<br />" . BACKWPUP_LINE_SEPARATOR);
		}
		fwrite($fd, sprintf(__('[INFO]: Temp folder is: %s', 'backwpup'), backwpup_get_option('cfg','tempfolder')) . "<br />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, sprintf(__('[INFO]: Logfile folder is: %s', 'backwpup'), backwpup_get_option('cfg','logfolder')) . "<br />" . BACKWPUP_LINE_SEPARATOR);
		fwrite($fd, sprintf(__('[INFO]: Backup type is: %s', 'backwpup'), backwpup_get_option($this->jobdata['JOBMAIN'],'backuptype')) . "<br />" . BACKWPUP_LINE_SEPARATOR);
		if ( !empty($this->jobdata['BACKUPFILE']) and backwpup_get_option($this->jobdata['JOBMAIN'],'backuptype') == 'archive' )
			fwrite($fd, sprintf(__('[INFO]: Backup file is: %s', 'backwpup'), $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE']) . "<br />" . BACKWPUP_LINE_SEPARATOR);
		fclose($fd);
		//test for destinations
		if ( in_array('DB', backwpup_get_option($this->jobdata['JOBMAIN'],'type')) or in_array('WPEXP', backwpup_get_option($this->jobdata['JOBMAIN'],'type')) or in_array('FILE', backwpup_get_option($this->jobdata['JOBMAIN'],'type')) ) {
			$desttest = false;
			foreach ( $this->jobdata['STEPS'] as $deststeptest ) {
				if ( substr($deststeptest, 0, 5) == 'DEST_' ) {
					$desttest = true;
					break;
				}
			}
			if ( !$desttest )
				$this->_error_handler(E_USER_ERROR, __('No destination defined for backup!!! Please correct job settings', 'backwpup'), __FILE__, __LINE__);
		}
	}

	public function _count_querys($query) {
		$this->jobdata['COUNT']['SQLQUERRYS']++;
		$this->jobdata['LASTQUERYTIMESTAMP']=current_time('timestamp');
		return $query;
	}
	private function _check_folder($folder) {
		$folder = untrailingslashit($folder);
		//check that is not home of WP
		if ( is_file($folder . '/wp-load.php') )
			return false;
		//create backup dir if it not exists
		if ( !is_dir($folder) ) {
			if ( !mkdir($folder, FS_CHMOD_DIR, true) ) {
				trigger_error(sprintf(__('Can not create folder: %1$s', 'backwpup'), $folder), E_USER_ERROR);
				return false;
			}
			//create .htaccess for apache and index.html/php for other
			if ( strtolower(substr($_SERVER["SERVER_SOFTWARE"], 0, 6)) == "apache" ) { //check for apache webserver
				if ( !is_file($folder . '/.htaccess') )
					file_put_contents($folder . '/.htaccess', "Order allow,deny" . BACKWPUP_LINE_SEPARATOR . "deny from all");
			} else {
				if ( !is_file($folder . '/index.html') )
					file_put_contents($folder . '/index.html', BACKWPUP_LINE_SEPARATOR);
				if ( !is_file($folder . '/index.php') )
					file_put_contents($folder . '/index.php', BACKWPUP_LINE_SEPARATOR);
			}
		}
		//check backup dir
		if ( !is_writable($folder) ) {
			trigger_error(sprintf(__('Not writable folder: %1$s', 'backwpup'), $folder), E_USER_ERROR);
			return false;
		}
		return true;
	}

	public function _error_handler() {
		$args = func_get_args(); // 0:errno, 1:errstr, 2:errfile, 3:errline
		// if error has been suppressed with an @
		if ( error_reporting() == 0 )
			return;

		$adderrorwarning = false;

		switch ( $args[0] ) {
			case E_NOTICE:
			case E_USER_NOTICE:
				$messagetype = "<span>";
				break;
			case E_WARNING:
			case E_CORE_WARNING:
			case E_COMPILE_WARNING:
			case E_USER_WARNING:
				$this->jobdata['WARNING']++;
				$adderrorwarning = true;
				$messagetype = "<span class=\"warning\">" . __('WARNING:', 'backwpup');
				break;
			case E_ERROR:
			case E_PARSE:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
				$this->jobdata['ERROR']++;
				$adderrorwarning = true;
				$messagetype = "<span class=\"error\">" . __('ERROR:', 'backwpup');
				break;
			case E_DEPRECATED:
			case E_USER_DEPRECATED:
				$messagetype = "<span>" . __('DEPRECATED:', 'backwpup');
				break;
			case E_STRICT:
				$messagetype = "<span>" . __('STRICT NOTICE:', 'backwpup');
				break;
			case E_RECOVERABLE_ERROR:
				$messagetype = "<span>" . __('RECOVERABLE ERROR:', 'backwpup');
				break;
			default:
				$messagetype = "<span>" . $args[0] . ":";
				break;
		}

		//log line
		$timestamp = "<span title=\"[Type: " . $args[0] . "|Line: " . $args[3] . "|File: " . basename($args[2]) . "|Mem: " . backwpup_format_bytes(@memory_get_usage(true)) . "|Mem Max: " . backwpup_format_bytes(@memory_get_peak_usage(true)) . "|Mem Limit: " . ini_get('memory_limit') . "|PID: " . getmypid() . "|Query's: " . $this->jobdata['COUNT']['SQLQUERRYS'] . "]\">[" . date_i18n('d-M-Y H:i:s') . "]</span> ";
		//write log file
		file_put_contents($this->jobdata['LOGFILE'], $timestamp . $messagetype . " " . $args[1] . "</span><br />" . BACKWPUP_LINE_SEPARATOR, FILE_APPEND);

		//write new log header
		if ( $adderrorwarning ) {
			$found = 0;
			$fd = fopen($this->jobdata['LOGFILE'], 'r+');
			$filepos = ftell($fd);
			while ( !feof($fd) ) {
				$line = fgets($fd);
				if ( stripos($line, "<meta name=\"backwpup_errors\"") !== false ) {
					fseek($fd, $filepos);
					fwrite($fd, str_pad("<meta name=\"backwpup_errors\" content=\"" . $this->jobdata['ERROR'] . "\" />", 100) . BACKWPUP_LINE_SEPARATOR);
					$found++;
				}
				if ( stripos($line, "<meta name=\"backwpup_warnings\"") !== false ) {
					fseek($fd, $filepos);
					fwrite($fd, str_pad("<meta name=\"backwpup_warnings\" content=\"" . $this->jobdata['WARNING'] . "\" />", 100) . BACKWPUP_LINE_SEPARATOR);
					$found++;
				}
				if ( $found >= 2 )
					break;
				$filepos = ftell($fd);
			}
			fclose($fd);
		}

		//write working data
		$this->_update_working_data($adderrorwarning);

		//Die on fatal php errors.
		if ( ($args[0] == E_ERROR or $args[0] == E_CORE_ERROR or $args[0] == E_COMPILE_ERROR) and $args[4]!=false)
			die();

		//true for no more php error handling.
		return true;
	}

	private function _update_working_data($mustwrite = false) {
		global $wpdb;
		//only run every  1 sec.
		$timetoupdate = current_time('timestamp')-$this->jobdata['TIMESTAMP'];
		if ( !$mustwrite and $timetoupdate<1 )
			return true;
		//check if job already aborted
		if ( !backwpup_get_option('working', 'data',false) ) {
			$this->end();
			return false;
		}
		//check MySQL connection
		$lastquerytimestamp=current_time('timestamp')-$this->jobdata['LASTQUERYTIMESTAMP'];
		if ($lastquerytimestamp>=5) {
			if (!mysql_ping($wpdb->dbh)) {
				trigger_error(__('Database connection is gone create a new one.', 'backwpup'), E_USER_NOTICE);
				$wpdb->db_connect();
			}
			$this->jobdata['LASTQUERYTIMESTAMP']=current_time('timestamp');
		}
		//Update % data
		if ( $this->jobdata['STEPTODO'] > 0 and $this->jobdata['STEPDONE'] > 0 )
			$this->jobdata['STEPPERSENT'] = round($this->jobdata['STEPDONE'] / $this->jobdata['STEPTODO'] * 100);
		else
			$this->jobdata['STEPPERSENT'] = 1;
		if ( count($this->jobdata['STEPSDONE']) > 0 )
			$this->jobdata['STEPSPERSENT'] = round(count($this->jobdata['STEPSDONE']) / count($this->jobdata['STEPS']) * 100);
		else
			$this->jobdata['STEPSPERSENT'] = 1;
		$this->jobdata['TIMESTAMP'] = current_time('timestamp');
		backwpup_update_option('working', 'data', $this->jobdata);
		if ( defined('STDIN') ) //make dots on cli mode
			echo ".";
		return true;
	}

	private function end() {
		//check if end() in progress
		if ( !$this->jobdata['ENDINPROGRESS'] )
			$this->jobdata['ENDINPROGRESS'] = true;
		else
			return;

		$this->jobdata['STEPTODO'] = 1;
		//Back from maintenance if not
		if (is_file(ABSPATH . '.maintenance') or get_site_option( FB_WM_TEXTDOMAIN . '-msqld' )==1 or get_option( FB_WM_TEXTDOMAIN . '-msqld' )==1)
			$this->_maintenance_mode(false);
		//delete old logs
		if ( backwpup_get_option('cfg','maxlogs') ) {
			if ( $dir = opendir(backwpup_get_option('cfg','logfolder')) ) { //make file list
				while ( ($file = readdir($dir)) !== false ) {
					if ( 'backwpup_log_' == substr($file, 0, strlen('backwpup_log_')) and (".html" == substr($file, -5) or ".html.gz" == substr($file, -8)) )
						$logfilelist[] = $file;
				}
				closedir($dir);
			}
			if ( sizeof($logfilelist) > 0 ) {
				rsort($logfilelist);
				$numdeltefiles = 0;
				for ( $i = backwpup_get_option('cfg','maxlogs'); $i < sizeof($logfilelist); $i++ ) {
					unlink(backwpup_get_option('cfg','logfolder') . $logfilelist[$i]);
					$numdeltefiles++;
				}
				if ( $numdeltefiles > 0 )
					trigger_error(sprintf(_n('One old log deleted', '%d old logs deleted', $numdeltefiles, 'backwpup'), $numdeltefiles), E_USER_NOTICE);
			}
		}

		//Display job working time
		if ( backwpup_get_option($this->jobdata['JOBMAIN'],'starttime') )
			trigger_error(sprintf(__('Job done in %s sec.', 'backwpup'), current_time('timestamp') - backwpup_get_option($this->jobdata['JOBMAIN'],'starttime'), E_USER_NOTICE));


		if ( empty($this->jobdata['BACKUPFILE']) or !is_file($this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE']) or !($filesize = filesize($this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'])) ) //Set the filesize correctly
			$filesize = 0;

		//clean up temp
		if ( $this->jobdata['BACKUPFILE'] and file_exists(backwpup_get_option('cfg','tempfolder') . $this->jobdata['BACKUPFILE']) )
			unlink(backwpup_get_option('cfg','tempfolder') . $this->jobdata['BACKUPFILE']);
		if ( $this->jobdata['DBDUMPFILE'] and file_exists(backwpup_get_option('cfg','tempfolder') . $this->jobdata['DBDUMPFILE']) )
			unlink(backwpup_get_option('cfg','tempfolder') . $this->jobdata['DBDUMPFILE']);
		if ($this->jobdata['WPEXPORTFILE'] and file_exists(backwpup_get_option('cfg','tempfolder') . $this->jobdata['WPEXPORTFILE']) )
			unlink(backwpup_get_option('cfg','tempfolder') . $this->jobdata['WPEXPORTFILE']);

		//Update job options
		$starttime = backwpup_get_option($this->jobdata['JOBMAIN'],'starttime');
		backwpup_update_option($this->jobdata['JOBMAIN'], 'lastrun', $starttime);
		backwpup_update_option($this->jobdata['JOBMAIN'], 'lastruntime', current_time('timestamp') - $starttime);
		backwpup_update_option($this->jobdata['JOBMAIN'], 'starttime', '');

		//write header info
		if ( is_writable($this->jobdata['LOGFILE']) ) {
			$fd = fopen($this->jobdata['LOGFILE'], 'r+');
			$filepos = ftell($fd);
			$found = 0;
			while ( !feof($fd) ) {
				$line = fgets($fd);
				if ( stripos($line, "<meta name=\"backwpup_jobruntime\"") !== false ) {
					fseek($fd, $filepos);
					fwrite($fd, str_pad("<meta name=\"backwpup_jobruntime\" content=\"" . backwpup_get_option($this->jobdata['JOBMAIN'],'lastruntime') . "\" />", 100) . BACKWPUP_LINE_SEPARATOR);
					$found++;
				}
				if ( stripos($line, "<meta name=\"backwpup_backupfilesize\"") !== false ) {
					fseek($fd, $filepos);
					fwrite($fd, str_pad("<meta name=\"backwpup_backupfilesize\" content=\"" . $filesize . "\" />", 100) . BACKWPUP_LINE_SEPARATOR);
					$found++;
				}
				if ( $found >= 2 )
					break;
				$filepos = ftell($fd);
			}
			fclose($fd);
		}
		//Restore error handler
		restore_error_handler();
		@ini_set('log_errors', $this->jobdata['PHP']['INI']['LOG_ERRORS']);
		@ini_set('error_log', $this->jobdata['PHP']['INI']['ERROR_LOG']);
		@ini_set('display_errors', $this->jobdata['PHP']['INI']['DISPLAY_ERRORS']);
		//logfile end
		file_put_contents($this->jobdata['LOGFILE'], "</body>" . BACKWPUP_LINE_SEPARATOR . "</html>", FILE_APPEND);

		//Send mail with log
		$sendmail = false;
		if ( $this->jobdata['ERROR'] > 0 and backwpup_get_option($this->jobdata['JOBMAIN'],'mailerroronly') and backwpup_get_option($this->jobdata['JOBMAIN'],'mailaddresslog') )
			$sendmail = true;
		if ( !backwpup_get_option($this->jobdata['JOBMAIN'],'mailerroronly') and backwpup_get_option($this->jobdata['JOBMAIN'],'mailaddresslog') )
			$sendmail = true;
		if ( $sendmail ) {
			$message = file_get_contents($this->jobdata['LOGFILE']);
			if ( !backwpup_get_option('cfg','mailsndname') )
				$headers = 'From: ' . backwpup_get_option('cfg','mailsndname') . ' <' . backwpup_get_option('cfg','mailsndemail') . '>' . "\r\n";
			else
				$headers = 'From: ' . backwpup_get_option('cfg','mailsndemail') . "\r\n";
			//special subject
			$status = 'Successful';
			if ( $this->jobdata['WARNING'] > 0 )
				$status = 'Warning';
			if ( $this->jobdata['ERROR'] > 0 )
				$status = 'Error';
			add_filter('wp_mail_content_type', create_function('', 'return "text/html"; '));
			wp_mail(backwpup_get_option($this->jobdata['JOBMAIN'],'mailaddresslog'),
				sprintf(__('[%3$s] BackWPup log %1$s: %2$s', 'backwpup'), date_i18n('d-M-Y H:i', backwpup_get_option($this->jobdata['JOBMAIN'],'lastrun')), backwpup_get_option($this->jobdata['JOBMAIN'],'name'), $status),
				$message, $headers);
		}

		//gzip logfile
		if ( backwpup_get_option('cfg','gzlogs') and is_writable($this->jobdata['LOGFILE']) ) {
			$fd = fopen($this->jobdata['LOGFILE'], 'r');
			$zd = gzopen($this->jobdata['LOGFILE'] . '.gz', 'w9');
			while ( !feof($fd) )
				gzwrite($zd, fread($fd, 4096));
			gzclose($zd);
			fclose($fd);
			unlink($this->jobdata['LOGFILE']);
			$this->jobdata['LOGFILE'] = $this->jobdata['LOGFILE'] . '.gz';
			backwpup_update_option($this->jobdata['JOBMAIN'], 'logfile', $this->jobdata['LOGFILE']);
		}

		$this->jobdata['STEPDONE'] = 1;
		$this->jobdata['STEPSDONE'][] = 'END'; //set done
		backwpup_delete_option('working','data'); //delete working data
		if ( defined('STDIN') )
			_e('Done!', 'backwpup');
		exit;
	}

	private function _maintenance_mode($enable = false) {
		if ( !backwpup_get_option($this->jobdata['JOBMAIN'],'maintenance') )
			return;
		if ( $enable ) {
			trigger_error(__('Set Blog to maintenance mode', 'backwpup'), E_USER_NOTICE);
			if ( class_exists('WPMaintenanceMode') ) { //Support for WP Maintenance Mode Plugin (Frank Bueltge)
				if (is_multisite() && is_plugin_active_for_network(FB_WM_BASENAME) )
					set_site_option( FB_WM_TEXTDOMAIN . '-msqld', 1 );
				else
					update_option(FB_WM_TEXTDOMAIN . '-msqld', 1);
			} else { //WP Support
				if ( is_writable(ABSPATH . '.maintenance') )
					file_put_contents(ABSPATH . '.maintenance', '<?php $upgrading = ' .time(). '; ?>');
				else
					trigger_error(__('Cannot set Blog to maintenance mode! Root folder is not writable!', 'backwpup'), E_USER_NOTICE);
			}
		} else {
			trigger_error(__('Set Blog to normal mode', 'backwpup'), E_USER_NOTICE);
			if (  class_exists('WPMaintenanceMode') ) { //Support for WP Maintenance Mode Plugin (Frank Bueltge)
				if (is_multisite() && is_plugin_active_for_network(FB_WM_BASENAME))
					set_site_option( FB_WM_TEXTDOMAIN . '-msqld', 0 );
				else
					update_option(FB_WM_TEXTDOMAIN . '-msqld', 0);
			}  else { //WP Support
				@unlink(ABSPATH . '.maintenance');
			}
		}
	}

	private function _job_in_bytes($value) {
		$multi = strtoupper(substr(trim($value), -1));
		$bytes = abs(intval(trim($value)));
		if ( $multi == 'G' )
			$bytes = $bytes * 1024 * 1024 * 1024;
		if ( $multi == 'M' )
			$bytes = $bytes * 1024 * 1024;
		if ( $multi == 'K' )
			$bytes = $bytes * 1024;
		return $bytes;
	}

	private function _need_free_memory($memneed) {
		if ( !function_exists('memory_get_usage') )
			return;
		//need memory
		$needmemory = @memory_get_usage(true) + $this->_job_in_bytes($memneed);
		// increase Memory
		if ( $needmemory > $this->_job_in_bytes(ini_get('memory_limit')) ) {
			$newmemory = round($needmemory / 1024 / 1024) + 1 . 'M';
			if ( $needmemory >= 1073741824 )
				$newmemory = round($needmemory / 1024 / 1024 / 1024) . 'G';
			if ( $oldmem = @ini_set('memory_limit', $newmemory) )
				trigger_error(sprintf(__('Memory increased from %1$s to %2$s', 'backwpup'), $oldmem, @ini_get('memory_limit')), E_USER_NOTICE);
			else
				trigger_error(sprintf(__('Can not increase memory limit is %1$s', 'backwpup'), @ini_get('memory_limit')), E_USER_WARNING);
		}
	}

	public function _curl_progresscallback($download_size, $downloaded, $upload_size, $uploaded) {
		if ( $this->jobdata['STEPTODO'] > 10 and backwpup_get_option($this->jobdata['JOBMAIN'],'backuptype') != 'sync' )
			$this->jobdata['STEPDONE'] = $uploaded;
		$this->_update_working_data();
	}

	public function curl_progressfunction($handle) {
		if ( defined('CURLOPT_PROGRESSFUNCTION') ) {
			curl_setopt($handle, CURLOPT_NOPROGRESS, false);
			curl_setopt($handle, CURLOPT_PROGRESSFUNCTION, array($this,'_curl_progresscallback'));
			curl_setopt($handle, CURLOPT_BUFFERSIZE, 512);
		}
	}
	private function db_dump() {
		global $wpdb, $wp_version;

		trigger_error(sprintf(__('%d. Try for database dump...', 'backwpup'), $this->jobdata['DB_DUMP']['STEP_TRY']), E_USER_NOTICE);

		if ( !isset($this->jobdata['DB_DUMP']['TABLES']) or !is_array($this->jobdata['DB_DUMP']['TABLES']) )
			$this->jobdata['DB_DUMP']['TABLES'] = array();


		if ( $this->jobdata['STEPDONE']==0 ) {
			//build filename
			$datevars = array( '%d', '%D', '%l', '%N', '%S', '%w', '%z', '%W', '%F', '%m', '%M', '%n', '%t', '%L', '%o', '%Y', '%a', '%A', '%B', '%g', '%G', '%h', '%H', '%i', '%s', '%u', '%e', '%I', '%O', '%P', '%T', '%Z', '%c', '%U' );
			$datevalues = array( date_i18n('d'), date_i18n('D'), date_i18n('l'), date_i18n('N'), date_i18n('S'), date_i18n('w'), date_i18n('z'), date_i18n('W'), date_i18n('F'), date_i18n('m'), date_i18n('M'), date_i18n('n'), date_i18n('t'), date_i18n('L'), date_i18n('o'), date_i18n('Y'), date_i18n('a'), date_i18n('A'), date_i18n('B'), date_i18n('g'), date_i18n('G'), date_i18n('h'), date_i18n('H'), date_i18n('i'), date_i18n('s'), date_i18n('u'), date_i18n('e'), date_i18n('I'), date_i18n('O'), date_i18n('P'), date_i18n('T'), date_i18n('Z'), date_i18n('c'), date_i18n('U') );
			$this->jobdata['DBDUMPFILE'] = str_replace($datevars, $datevalues, backwpup_get_option($this->jobdata['JOBMAIN'],'dbdumpfile'));
			//check compression
			if ( backwpup_get_option($this->jobdata['JOBMAIN'],'dbdumpfilecompression') == 'gz' and !function_exists('gzopen') )
				backwpup_update_option($this->jobdata['JOBMAIN'],'dbdumpfilecompression','');
			if ( backwpup_get_option($this->jobdata['JOBMAIN'],'dbdumpfilecompression') == 'bz2' and !function_exists('bzopen') )
				backwpup_update_option($this->jobdata['JOBMAIN'],'dbdumpfilecompression','');
			//add file ending
			$this->jobdata['DBDUMPFILE'] .= '.sql';
			if ( backwpup_get_option($this->jobdata['JOBMAIN'],'dbdumpfilecompression') == 'gz' or backwpup_get_option($this->jobdata['JOBMAIN'],'dbdumpfilecompression') == 'bz2' )
				$this->jobdata['DBDUMPFILE'] .= '.' . backwpup_get_option($this->jobdata['JOBMAIN'],'dbdumpfilecompression');

			//get tables to backup
			$tables = $wpdb->get_col("SHOW TABLES FROM `" . DB_NAME . "`"); //get table status
			if ( mysql_error() )
				trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), $wpdb->last_query), E_USER_ERROR);
			foreach ( $tables as $table ) {
				if ( !in_array($table, backwpup_get_option($this->jobdata['JOBMAIN'],'dbexclude')) )
					$this->jobdata['DB_DUMP']['TABLES'][] = $table;
			}
			$this->jobdata['STEPTODO'] = count($this->jobdata['DB_DUMP']['TABLES']);

			//Get table status
			$tablesstatus = $wpdb->get_results("SHOW TABLE STATUS FROM `" . DB_NAME . "`", ARRAY_A); //get table status
			if ( mysql_error() )
				trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), $wpdb->last_query), E_USER_ERROR);
			foreach ( $tablesstatus as $tablestatus )
				$this->jobdata['DB_DUMP']['TABLESTATUS'][$tablestatus['Name']] = $tablestatus;



			if ( count($this->jobdata['DB_DUMP']['TABLES']) == 0 ) {
				trigger_error(__('No tables to dump', 'backwpup'), E_USER_WARNING);
				$this->jobdata['STEPSDONE'][] = 'DB_DUMP'; //set done
				return;
			}

			//Set maintenance
			$this->_maintenance_mode(true);

			if ( backwpup_get_option($this->jobdata['JOBMAIN'],'dbdumpfilecompression') == 'gz' )
				$file = gzopen(backwpup_get_option('cfg','tempfolder') . $this->jobdata['DBDUMPFILE'], 'wb9');
			elseif ( backwpup_get_option($this->jobdata['JOBMAIN'],'dbdumpfilecompression') == 'bz2' )
				$file = bzopen(backwpup_get_option('cfg','tempfolder') . $this->jobdata['DBDUMPFILE'], 'w');
			else
				$file = fopen(backwpup_get_option('cfg','tempfolder') . $this->jobdata['DBDUMPFILE'], 'wb');

			if ( !$file ) {
				trigger_error(sprintf(__('Can not create database dump file! "%s"', 'backwpup'), $this->jobdata['DBDUMPFILE']), E_USER_ERROR);
				$this->jobdata['STEPSDONE'][] = 'DB_DUMP'; //set done
				$this->_maintenance_mode(false);
				return;
			}


			$dbdumpheader = "-- ---------------------------------------------------------" . BACKWPUP_LINE_SEPARATOR;
			$dbdumpheader .= "-- Dumped with BackWPup ver.: " . BACKWPUP_VERSION . BACKWPUP_LINE_SEPARATOR;
			$dbdumpheader .= "-- Plugin for WordPress " . $wp_version . " by Daniel Huesken" . BACKWPUP_LINE_SEPARATOR;
			$dbdumpheader .= "-- http://backwpup.com" . BACKWPUP_LINE_SEPARATOR;
			$dbdumpheader .= "-- Blog Name: " . get_bloginfo('name') . BACKWPUP_LINE_SEPARATOR;
			if ( defined('WP_SITEURL') )
				$dbdumpheader .= "-- Blog URL: " . trailingslashit(WP_SITEURL) . BACKWPUP_LINE_SEPARATOR;
			else
				$dbdumpheader .= "-- Blog URL: " . trailingslashit(get_option('siteurl')) . BACKWPUP_LINE_SEPARATOR;
			$dbdumpheader .= "-- Blog ABSPATH: " . trailingslashit(str_replace('\\', '/', ABSPATH)) . BACKWPUP_LINE_SEPARATOR;
			$dbdumpheader .= "-- Blog Charset: " . get_option( 'blog_charset' ) . BACKWPUP_LINE_SEPARATOR;
			$dbdumpheader .= "-- Table Prefix: " . $wpdb->prefix . BACKWPUP_LINE_SEPARATOR;
			$dbdumpheader .= "-- Database Name: " . DB_NAME . BACKWPUP_LINE_SEPARATOR;
			$dbdumpheader .= "-- Database charset: " . DB_CHARSET . BACKWPUP_LINE_SEPARATOR;
			$dbdumpheader .= "-- Database collate: " . DB_COLLATE . BACKWPUP_LINE_SEPARATOR;
			$dbdumpheader .= "-- Dumped on: " . date_i18n('Y-m-d H:i.s') . BACKWPUP_LINE_SEPARATOR;
			$dbdumpheader .= "-- ---------------------------------------------------------" . BACKWPUP_LINE_SEPARATOR . BACKWPUP_LINE_SEPARATOR;
			//for better import with mysql client
			$dbdumpheader .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;" . BACKWPUP_LINE_SEPARATOR;
			$dbdumpheader .= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;" . BACKWPUP_LINE_SEPARATOR;
			$dbdumpheader .= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;" . BACKWPUP_LINE_SEPARATOR;
			$dbdumpheader .= "/*!40101 SET NAMES '" . mysql_client_encoding() . "' */;" . BACKWPUP_LINE_SEPARATOR;
			$dbdumpheader .= "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;" . BACKWPUP_LINE_SEPARATOR;
			$dbdumpheader .= "/*!40103 SET TIME_ZONE='" . $wpdb->get_var("SELECT @@time_zone") . "' */;" . BACKWPUP_LINE_SEPARATOR;
			$dbdumpheader .= "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;" . BACKWPUP_LINE_SEPARATOR;
			$dbdumpheader .= "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;" . BACKWPUP_LINE_SEPARATOR;
			$dbdumpheader .= "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;" . BACKWPUP_LINE_SEPARATOR;
			$dbdumpheader .= "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;" . BACKWPUP_LINE_SEPARATOR . BACKWPUP_LINE_SEPARATOR;
			if ( backwpup_get_option($this->jobdata['JOBMAIN'],'dbdumpfilecompression') == 'gz' )
				gzwrite($file, $dbdumpheader);
			elseif ( backwpup_get_option($this->jobdata['JOBMAIN'],'dbdumpfilecompression') == 'bz2' )
				bzwrite($file, $dbdumpheader);
			else
				fwrite($file, $dbdumpheader);

		}
		//make table dumps
		if ($this->jobdata['STEPTODO']!=$this->jobdata['STEPDONE']) {
			foreach ( $this->jobdata['DB_DUMP']['TABLES'] as $tablekey => $table ) {

				trigger_error(sprintf(__('Dump database table "%s"', 'backwpup'), $table), E_USER_NOTICE);
				//get more memory if needed
				$this->_need_free_memory(($this->jobdata['DB_DUMP']['TABLESTATUS'][$table]['Data_length'] + $this->jobdata['DB_DUMP']['TABLESTATUS'][$table]['Index_length']) * 2);
				$this->_update_working_data();

				$tablecreate = BACKWPUP_LINE_SEPARATOR . "--" . BACKWPUP_LINE_SEPARATOR . "-- Table structure for table $table" . BACKWPUP_LINE_SEPARATOR . "--" . BACKWPUP_LINE_SEPARATOR . BACKWPUP_LINE_SEPARATOR;
				$tablecreate .= "DROP TABLE IF EXISTS `" . $table . "`;" . BACKWPUP_LINE_SEPARATOR;
				$tablecreate .= "/*!40101 SET @saved_cs_client     = @@character_set_client */;" . BACKWPUP_LINE_SEPARATOR;
				$tablecreate .= "/*!40101 SET character_set_client = '" . mysql_client_encoding() . "' */;" . BACKWPUP_LINE_SEPARATOR;
				//Dump the table structure
				$tablestruc = $wpdb->get_row("SHOW CREATE TABLE `" . $table . "`", 'ARRAY_A');
				if ( mysql_error() ) {
					trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), "SHOW CREATE TABLE `" . $table . "`"), E_USER_ERROR);
					return false;
				}
				$tablecreate .= $tablestruc['Create Table'] . ";" . BACKWPUP_LINE_SEPARATOR;
				$tablecreate .= "/*!40101 SET character_set_client = @saved_cs_client */;" . BACKWPUP_LINE_SEPARATOR;

				if ( backwpup_get_option($this->jobdata['JOBMAIN'],'dbdumpfilecompression') == 'gz' )
					gzwrite($file, $tablecreate);
				elseif ( backwpup_get_option($this->jobdata['JOBMAIN'],'dbdumpfilecompression') == 'bz2' )
					bzwrite($file, $tablecreate);
				else
					fwrite($file, $tablecreate);

				//get data from table
				$datas = $wpdb->get_results("SELECT * FROM `" . $table . "`", 'ARRAY_N');
				if ( mysql_error() ) {
					trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), $wpdb->last_query), E_USER_ERROR);
					return false;
				}
				//get key information
				$keys = $wpdb->get_col_info('name', -1);

				//build key string
				$keystring = " (`" . implode("`, `", $keys) . "`)";
				//colem infos
				for ( $i = 0; $i < count($keys); $i++ ) {
					$colinfo[$i]['numeric'] = $wpdb->get_col_info('numeric', $i);
					$colinfo[$i]['type'] = $wpdb->get_col_info('type', $i);
					$colinfo[$i]['blob'] = $wpdb->get_col_info('blob', $i);
				}

				$tabledata = BACKWPUP_LINE_SEPARATOR . "--" . BACKWPUP_LINE_SEPARATOR . "-- Dumping data for table $table" . BACKWPUP_LINE_SEPARATOR . "--" . BACKWPUP_LINE_SEPARATOR . BACKWPUP_LINE_SEPARATOR;

				if ( $this->jobdata['DB_DUMP']['TABLESTATUS'][$table]['Engine'] == 'MyISAM' )
					$tabledata .= "/*!40000 ALTER TABLE `" . $table . "` DISABLE KEYS */;" . BACKWPUP_LINE_SEPARATOR;

				if ( backwpup_get_option($this->jobdata['JOBMAIN'],'dbdumpfilecompression') == 'gz' )
					gzwrite($file, $tabledata);
				elseif ( backwpup_get_option($this->jobdata['JOBMAIN'],'dbdumpfilecompression') == 'bz2' )
					bzwrite($file, $tabledata);
				else
					fwrite($file, $tabledata);
				$tabledata = '';

				$querystring = '';
				foreach ( $datas as $data ) {
					$values = array();
					foreach ( $data as $key => $value ) {
						if ( is_null($value) or !isset($value) ) // Make Value NULL to string NULL
							$value = "NULL";
						elseif ( $colinfo[$key]['numeric'] == 1 and $colinfo[$key]['type'] != 'timestamp' and $colinfo[$key]['blob'] != 1 ) //is value numeric no esc
							$value = empty($value) ? 0 : $value;
						else
							$value = "'" . mysql_real_escape_string($value) . "'";
						$values[] = $value;
					}
					if ( empty($querystring) )
						$querystring = "INSERT INTO `" . $table . "`" . $keystring . " VALUES" . BACKWPUP_LINE_SEPARATOR;
					if ( strlen($querystring) <= 50000 ) { //write dump on more than 50000 chars.
						$querystring .= "(" . implode(", ", $values) . ")," . BACKWPUP_LINE_SEPARATOR;
					} else {
						$querystring .= "(" . implode(", ", $values) . ");" . BACKWPUP_LINE_SEPARATOR;
						if ( backwpup_get_option($this->jobdata['JOBMAIN'],'dbdumpfilecompression') == 'gz' )
							gzwrite($file, $querystring);
						elseif ( backwpup_get_option($this->jobdata['JOBMAIN'],'dbdumpfilecompression') == 'bz2' )
							bzwrite($file, $querystring);
						else
							fwrite($file, $querystring);
						$querystring = '';
					}
				}
				if ( !empty($querystring) ) //dump rest
					$tabledata = substr($querystring, 0, -strlen(BACKWPUP_LINE_SEPARATOR)-1) . ";" . BACKWPUP_LINE_SEPARATOR;

				if ( $this->jobdata['DB_DUMP']['TABLESTATUS'][$table]['Engine'] == 'MyISAM' )
					$tabledata .= "/*!40000 ALTER TABLE `" . $table . "` ENABLE KEYS */;" . BACKWPUP_LINE_SEPARATOR;

				if ( backwpup_get_option($this->jobdata['JOBMAIN'],'dbdumpfilecompression') == 'gz' )
					gzwrite($file, $tabledata);
				elseif ( backwpup_get_option($this->jobdata['JOBMAIN'],'dbdumpfilecompression') == 'bz2' )
					bzwrite($file, $tabledata);
				else
					fwrite($file, $tabledata);

				$wpdb->flush();

				unset($this->jobdata['DB_DUMP']['TABLES'][$tablekey]);
				$this->jobdata['STEPDONE']++;
			}
		}
		if ( $this->jobdata['STEPTODO']==$this->jobdata['STEPDONE'] ) {
			//for better import with mysql client
			$dbdumpfooter = BACKWPUP_LINE_SEPARATOR . "--" . BACKWPUP_LINE_SEPARATOR . "-- Delete not needed values on backwpup table" . BACKWPUP_LINE_SEPARATOR . "--" . BACKWPUP_LINE_SEPARATOR . BACKWPUP_LINE_SEPARATOR;
			$dbdumpfooter .= "DELETE FROM `" . $wpdb->prefix . "backwpup` WHERE `main`='temp';" . BACKWPUP_LINE_SEPARATOR;
			$dbdumpfooter .= "DELETE FROM `" . $wpdb->prefix . "backwpup` WHERE `main`='working';" . BACKWPUP_LINE_SEPARATOR . BACKWPUP_LINE_SEPARATOR . BACKWPUP_LINE_SEPARATOR;
			$dbdumpfooter .= "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;" . BACKWPUP_LINE_SEPARATOR;
			$dbdumpfooter .= "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;" . BACKWPUP_LINE_SEPARATOR;
			$dbdumpfooter .= "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;" . BACKWPUP_LINE_SEPARATOR;
			$dbdumpfooter .= "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;" . BACKWPUP_LINE_SEPARATOR;
			$dbdumpfooter .= "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;" . BACKWPUP_LINE_SEPARATOR;
			$dbdumpfooter .= "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;" . BACKWPUP_LINE_SEPARATOR;
			$dbdumpfooter .= "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;" . BACKWPUP_LINE_SEPARATOR;
			$dbdumpfooter .= "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;" . BACKWPUP_LINE_SEPARATOR;

			if ( backwpup_get_option($this->jobdata['JOBMAIN'],'dbdumpfilecompression') == 'gz' ) {
				gzwrite($file, $dbdumpfooter);
				gzclose($file);
			} elseif ( backwpup_get_option($this->jobdata['JOBMAIN'],'dbdumpfilecompression') == 'bz2' ) {
				bzwrite($file, $dbdumpfooter);
				bzclose($file);
			} else {
				fwrite($file, $dbdumpfooter);
				fclose($file);
			}

			trigger_error(__('Database dump done!', 'backwpup'), E_USER_NOTICE);

			//add database file to backup files
			if ( is_readable(backwpup_get_option('cfg','tempfolder') . $this->jobdata['DBDUMPFILE']) ) {
				$this->jobdata['EXTRAFILESTOBACKUP'][] = backwpup_get_option('cfg','tempfolder') . $this->jobdata['DBDUMPFILE'];
				$this->jobdata['COUNT']['FILES']++;
				$this->jobdata['COUNT']['FILESIZE']=$this->jobdata['COUNT']['FILESIZE']+@filesize(backwpup_get_option('cfg','tempfolder') . $this->jobdata['DBDUMPFILE']);
				trigger_error(sprintf(__('Added database dump "%1$s" with %2$s to backup file list', 'backwpup'), $this->jobdata['DBDUMPFILE'], backwpup_format_bytes(filesize(backwpup_get_option('cfg','tempfolder') .$this->jobdata['DBDUMPFILE']))), E_USER_NOTICE);
			}
		}
		//Back from maintenance
		$this->_maintenance_mode(false);
		$this->jobdata['STEPSDONE'][] = 'DB_DUMP'; //set done
	}

	private function db_check() {
		global $wpdb;
		trigger_error(sprintf(__('%d. Try for database check...', 'backwpup'), $this->jobdata['DB_CHECK']['STEP_TRY']), E_USER_NOTICE);
		if ( !isset($this->jobdata['DB_CHECK']['DONETABLE']) or !is_array($this->jobdata['DB_CHECK']['DONETABLE']) )
			$this->jobdata['DB_CHECK']['DONETABLE'] = array();

		//to backup
		$tablestobackup = array();
		$tables = $wpdb->get_col("SHOW TABLES FROM `" . DB_NAME . "`"); //get table status
		if ( mysql_error() )
			trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), $wpdb->last_query), E_USER_ERROR);
		foreach ( $tables as $table ) {
			if ( !in_array($table, backwpup_get_option($this->jobdata['JOBMAIN'],'dbexclude')) )
				$tablestobackup[] = $table;
		}
		//Set num of todos
		$this->jobdata['STEPTODO'] = sizeof($tablestobackup);

		//check tables
		if ( $this->jobdata['STEPTODO'] > 0 ) {
			$this->_maintenance_mode(true);
			foreach ( $tablestobackup as $table ) {
				if ( in_array($table, $this->jobdata['DB_CHECK']['DONETABLE']) )
					continue;
				$check = $wpdb->get_row("CHECK TABLE `" . $table . "` MEDIUM");
				if ( mysql_error() ) {
					trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), $wpdb->last_query), E_USER_ERROR);
					continue;
				}
				if ( $check->Msg_text == 'OK' )
					trigger_error(sprintf(__('Result of table check for %1$s is: %2$s', 'backwpup'), $table, $check->Msg_text), E_USER_NOTICE);
				elseif ( strtolower($check->Msg_type) == 'warning' )
					trigger_error(sprintf(__('Result of table check for %1$s is: %2$s', 'backwpup'), $table, $check->Msg_text), E_USER_WARNING);
				else
					trigger_error(sprintf(__('Result of table check for %1$s is: %2$s', 'backwpup'), $table, $check->Msg_text), E_USER_ERROR);

				//Try to Repair tabele
				if ( $check->Msg_text != 'OK' ) {
					$repair = $wpdb->get_row('REPAIR TABLE `' . $table . '`');
					if ( mysql_error() ) {
						trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), $wpdb->last_query), E_USER_ERROR);
						continue;
					}
					if ( $repair->Msg_type == 'OK' )
						trigger_error(sprintf(__('Result of table repair for %1$s is: %2$s', 'backwpup'), $table, $repair->Msg_text), E_USER_NOTICE);
					elseif ( strtolower($repair->Msg_type) == 'warning' )
						trigger_error(sprintf(__('Result of table repair for %1$s is: %2$s', 'backwpup'), $table, $repair->Msg_text), E_USER_WARNING);
					else
						trigger_error(sprintf(__('Result of table repair for %1$s is: %2$s', 'backwpup'), $table, $repair->Msg_text), E_USER_ERROR);
				}
				$this->jobdata['DB_CHECK']['DONETABLE'][] = $table;
				$this->jobdata['STEPDONE']++;
			}
			$this->_maintenance_mode(false);
			trigger_error(__('Database check done!', 'backwpup'), E_USER_NOTICE);
		} else {
			trigger_error(__('No tables to check', 'backwpup'), E_USER_WARNING);
		}
		$this->jobdata['STEPSDONE'][] = 'DB_CHECK'; //set done
	}

	private function db_optimize() {
		global $wpdb;
		trigger_error(sprintf(__('%d. Try for database optimize...', 'backwpup'), $this->jobdata['DB_OPTIMIZE']['STEP_TRY']), E_USER_NOTICE);
		if ( !isset($this->jobdata['DB_OPTIMIZE']['DONETABLE']) or !is_array($this->jobdata['DB_OPTIMIZE']['DONETABLE']) )
			$this->jobdata['DB_OPTIMIZE']['DONETABLE'] = array();

		//to backup
		$tablestobackup = array();
		$tables = $wpdb->get_col("SHOW TABLES FROM `" . DB_NAME . "`"); //get table status
		if ( mysql_error() )
			trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), $wpdb->last_query), E_USER_ERROR);
		foreach ( $tables as $table ) {
			if ( !in_array($table, backwpup_get_option($this->jobdata['JOBMAIN'],'dbexclude')) )
				$tablestobackup[] = $table;
		}
		//Set num of todos
		$this->jobdata['STEPTODO'] = count($tablestobackup);

		//get table status
		$tablesstatus = $wpdb->get_results("SHOW TABLE STATUS FROM `" . DB_NAME . "`");
		if ( mysql_error() )
			trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), $wpdb->last_query), E_USER_ERROR);
		foreach ( $tablesstatus as $tablestatus )
			$status[$tablestatus->Name] = $tablestatus;

		if ( $this->jobdata['STEPTODO'] > 0 ) {
			$this->_maintenance_mode(true);
			foreach ( $tablestobackup as $table ) {
				if ( in_array($table, $this->jobdata['DB_OPTIMIZE']['DONETABLE']) )
					continue;
				if ( $status[$table]->Engine != 'InnoDB' ) {
					$optimize = $wpdb->get_row("OPTIMIZE TABLE `" . $table . "`");
					if ( mysql_error() )
						trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), $wpdb->last_query), E_USER_ERROR);
					elseif ( strtolower($optimize->Msg_type) == 'error' )
						trigger_error(sprintf(__('Result of table optimize for %1$s is: %2$s', 'backwpup'), $table, $optimize->Msg_text), E_USER_ERROR);
					elseif ( strtolower($optimize->Msg_type) == 'warning' )
						trigger_error(sprintf(__('Result of table optimize for %1$s is: %2$s', 'backwpup'), $table, $optimize->Msg_text), E_USER_WARNING);
					else
						trigger_error(sprintf(__('Result of table optimize for %1$s is: %2$s', 'backwpup'), $table, $optimize->Msg_text), E_USER_NOTICE);
				} else {
					$wpdb->get_row("ALTER TABLE `" . $table . "` ENGINE='InnoDB'");
					if ( mysql_error() )
						trigger_error(sprintf(__('Database error %1$s for query %2$s', 'backwpup'), mysql_error(), $wpdb->last_query), E_USER_ERROR);
					else
						trigger_error(sprintf(__('InnoDB Table %1$s optimize done', 'backwpup'), $table), E_USER_NOTICE);
				}
				$this->jobdata['DB_OPTIMIZE']['DONETABLE'][] = $table;
				$this->jobdata['STEPDONE']++;
			}
			trigger_error(__('Database optimize done!', 'backwpup'), E_USER_NOTICE);
			$this->_maintenance_mode(false);
		} else {
			trigger_error(__('No tables to optimize', 'backwpup'), E_USER_WARNING);
		}
		$this->jobdata['STEPSDONE'][] = 'DB_OPTIMIZE'; //set done
	}

	private function wp_export() {
		$this->jobdata['STEPTODO'] = 1;
		trigger_error(sprintf(__('%d. Try to make a WordPress Export to XML file...', 'backwpup'), $this->jobdata['WP_EXPORT']['STEP_TRY']), E_USER_NOTICE);
		$this->_need_free_memory('5M'); //5MB free memory
		//build filename
		$datevars = array( '%d', '%D', '%l', '%N', '%S', '%w', '%z', '%W', '%F', '%m', '%M', '%n', '%t', '%L', '%o', '%Y', '%a', '%A', '%B', '%g', '%G', '%h', '%H', '%i', '%s', '%u', '%e', '%I', '%O', '%P', '%T', '%Z', '%c', '%U' );
		$datevalues = array( date_i18n('d'), date_i18n('D'), date_i18n('l'), date_i18n('N'), date_i18n('S'), date_i18n('w'), date_i18n('z'), date_i18n('W'), date_i18n('F'), date_i18n('m'), date_i18n('M'), date_i18n('n'), date_i18n('t'), date_i18n('L'), date_i18n('o'), date_i18n('Y'), date_i18n('a'), date_i18n('A'), date_i18n('B'), date_i18n('g'), date_i18n('G'), date_i18n('h'), date_i18n('H'), date_i18n('i'), date_i18n('s'), date_i18n('u'), date_i18n('e'), date_i18n('I'), date_i18n('O'), date_i18n('P'), date_i18n('T'), date_i18n('Z'), date_i18n('c'), date_i18n('U') );
		$this->jobdata['WPEXPORTFILE'] = str_replace($datevars, $datevalues, backwpup_get_option($this->jobdata['JOBMAIN'],'wpexportfile'));

		//check compression
		if ( backwpup_get_option($this->jobdata['JOBMAIN'],'wpexportfilecompression') == 'gz' and !function_exists('gzopen') )
			backwpup_update_option($this->jobdata['JOBMAIN'],'wpexportfilecompression', '');
		if ( backwpup_get_option($this->jobdata['JOBMAIN'],'wpexportfilecompression') == 'bz2' and !function_exists('bzopen') )
			backwpup_update_option($this->jobdata['JOBMAIN'],'wpexportfilecompression', '');
		//add file ending
		$this->jobdata['WPEXPORTFILE'] .= '.xml';
		if ( backwpup_get_option($this->jobdata['JOBMAIN'],'wpexportfilecompression') == 'gz' or backwpup_get_option($this->jobdata['JOBMAIN'],'wpexportfilecompression') == 'bz2' )
			$this->jobdata['WPEXPORTFILE'] .= '.' . backwpup_get_option($this->jobdata['JOBMAIN'],'wpexportfilecompression');

		if ( backwpup_get_option($this->jobdata['JOBMAIN'],'wpexportfilecompression') == 'gz' )
			$this->jobdata['filehandel'] = gzopen(backwpup_get_option('cfg','tempfolder') . $this->jobdata['WPEXPORTFILE'], 'wb9');
		elseif ( backwpup_get_option($this->jobdata['JOBMAIN'],'wpexportfilecompression') == 'bz2' )
			$this->jobdata['filehandel'] = bzopen(backwpup_get_option('cfg','tempfolder') .$this->jobdata['WPEXPORTFILE'], 'w');
		else
			$this->jobdata['filehandel'] = fopen(backwpup_get_option('cfg','tempfolder') . $this->jobdata['WPEXPORTFILE'], 'wb');

		//include WP export function
		require_once(ABSPATH . 'wp-admin/includes/export.php');
		error_reporting(0); //disable error reporting
		ob_start(array( $this, '_wp_export_ob_bufferwrite' ), 512); //start output buffering
		export_wp(); //WP export
		ob_end_clean(); //End output buffering
		error_reporting(E_ALL | E_STRICT); //enable error reporting

		if ( backwpup_get_option($this->jobdata['JOBMAIN'],'wpexportfilecompression') == 'gz' ) {
			gzclose($this->jobdata['filehandel']);
		} elseif ( backwpup_get_option($this->jobdata['JOBMAIN'],'wpexportfilecompression') == 'bz2' ) {
			bzclose($this->jobdata['filehandel']);
		} else {
			fclose($this->jobdata['filehandel']);
		}

		//add XML file to backup files
		if ( is_readable(backwpup_get_option('cfg','tempfolder') .$this->jobdata['WPEXPORTFILE']) ) {
			$this->jobdata['EXTRAFILESTOBACKUP'][] = backwpup_get_option('cfg','tempfolder') . $this->jobdata['WPEXPORTFILE'];
			$this->jobdata['COUNT']['FILES']++;
			$this->jobdata['COUNT']['FILESIZE']=$this->jobdata['COUNT']['FILESIZE']+@filesize(backwpup_get_option('cfg','tempfolder') . $this->jobdata['WPEXPORTFILE']);
			trigger_error(sprintf(__('Added XML export "%1$s" with %2$s to backup file list', 'backwpup'), $this->jobdata['WPEXPORTFILE'], backwpup_format_bytes(filesize(backwpup_get_option('cfg','tempfolder') . $this->jobdata['WPEXPORTFILE']))), E_USER_NOTICE);
		}
		$this->jobdata['STEPDONE'] = 1;
		$this->jobdata['STEPSDONE'][] = 'WP_EXPORT'; //set done
	}

	public function _wp_export_ob_bufferwrite($output) {
		if ( backwpup_get_option($this->jobdata['JOBMAIN'],'wpexportfilecompression') == 'gz' ) {
			gzwrite($this->jobdata['filehandel'], $output);
		} elseif ( backwpup_get_option($this->jobdata['JOBMAIN'],'wpexportfilecompression') == 'bz2' ) {
			bzwrite($this->jobdata['filehandel'], $output);
		} else {
			fwrite($this->jobdata['filehandel'], $output);
		}
		$this->_update_working_data();
	}

	private function folder_list() {
		trigger_error(sprintf(__('%d. Try to make list of folder to backup....', 'backwpup'), $this->jobdata['FOLDER_LIST']['STEP_TRY']), E_USER_NOTICE);
		$this->jobdata['STEPTODO'] = 7;

		//Check free memory for file list
		$this->_need_free_memory('2M'); //2MB free memory

		//Folder list for blog folders
		if ( backwpup_get_option($this->jobdata['JOBMAIN'],'backuproot') and $this->jobdata['STEPDONE'] == 0 )
			$this->_folder_list(trailingslashit(str_replace('\\', '/', ABSPATH)), 100,
				array_merge(backwpup_get_option($this->jobdata['JOBMAIN'],'backuprootexcludedirs'), backwpup_get_exclude_wp_dirs(ABSPATH)));
		if ( $this->jobdata['STEPDONE'] == 0 )
			$this->jobdata['STEPDONE'] = 1;
		$this->_update_working_data();
		if ( backwpup_get_option($this->jobdata['JOBMAIN'],'backupcontent') and $this->jobdata['STEPDONE'] == 1 )
			$this->_folder_list(trailingslashit(str_replace('\\', '/', WP_CONTENT_DIR)), 100,
				array_merge(backwpup_get_option($this->jobdata['JOBMAIN'],'backupcontentexcludedirs'), backwpup_get_exclude_wp_dirs(WP_CONTENT_DIR)));
		if ( $this->jobdata['STEPDONE'] == 1 )
			$this->jobdata['STEPDONE'] = 2;
		$this->_update_working_data();
		if ( backwpup_get_option($this->jobdata['JOBMAIN'],'backupplugins') and $this->jobdata['STEPDONE'] == 2 )
			$this->_folder_list(trailingslashit(str_replace('\\', '/', WP_PLUGIN_DIR)), 100,
				array_merge(backwpup_get_option($this->jobdata['JOBMAIN'],'backuppluginsexcludedirs'), backwpup_get_exclude_wp_dirs(WP_PLUGIN_DIR)));
		if ( $this->jobdata['STEPDONE'] == 2 )
			$this->jobdata['STEPDONE'] = 3;
		$this->_update_working_data();
		if ( backwpup_get_option($this->jobdata['JOBMAIN'],'backupthemes') and $this->jobdata['STEPDONE'] == 3 )
			$this->_folder_list(trailingslashit(str_replace('\\', '/', trailingslashit(WP_CONTENT_DIR) . 'themes/')), 100,
				array_merge(backwpup_get_option($this->jobdata['JOBMAIN'],'backupthemesexcludedirs'), backwpup_get_exclude_wp_dirs(trailingslashit(WP_CONTENT_DIR) . 'themes/')));
		if ( $this->jobdata['STEPDONE'] == 3 )
			$this->jobdata['STEPDONE'] = 4;
		$this->_update_working_data();
		if ( backwpup_get_option($this->jobdata['JOBMAIN'],'backupuploads') and $this->jobdata['STEPDONE'] == 4 )
			$this->_folder_list(backwpup_get_upload_dir(), 100,
				array_merge(backwpup_get_option($this->jobdata['JOBMAIN'],'backupuploadsexcludedirs'), backwpup_get_exclude_wp_dirs(backwpup_get_upload_dir())));
		if ( $this->jobdata['STEPDONE'] == 4 )
			$this->jobdata['STEPDONE'] = 5;
		$this->_update_working_data();

		//include dirs
		if ( backwpup_get_option($this->jobdata['JOBMAIN'],'dirinclude') and $this->jobdata['STEPDONE'] == 5 ) {
			$dirinclude = explode(',', backwpup_get_option($this->jobdata['JOBMAIN'],'dirinclude'));
			$dirinclude = array_unique($dirinclude);
			//Crate file list for includes
			foreach ( $dirinclude as $dirincludevalue ) {
				if ( is_dir($dirincludevalue) )
					$this->_folder_list($dirincludevalue);
			}
		}
		if ( $this->jobdata['STEPDONE'] == 5 )
			$this->jobdata['STEPDONE'] = 6;
		$this->_update_working_data();

		$this->jobdata['FOLDERLIST'] = array_unique($this->jobdata['FOLDERLIST']); //all files only one time in list
		sort($this->jobdata['FOLDERLIST']);

		//add extra files if selected
		if (backwpup_get_option($this->jobdata['JOBMAIN'],'backupspecialfiles')) {
			if ( file_exists( ABSPATH . 'wp-config.php') and !backwpup_get_option($this->jobdata['JOBMAIN'],'backuproot')) {
				$this->jobdata['EXTRAFILESTOBACKUP'][]=str_replace('\\','/',ABSPATH . 'wp-config.php');
				$this->jobdata['COUNT']['FILES']++;
				$this->jobdata['COUNT']['FILESIZE']=$this->jobdata['COUNT']['FILESIZE']+@filesize(ABSPATH . 'wp-config.php');
				trigger_error(sprintf(__('Added "%s" to backup file list', 'backwpup'),'wp-config.php'), E_USER_NOTICE);
			} elseif ( file_exists( dirname(ABSPATH) . '/wp-config.php' ) && ! file_exists( dirname(ABSPATH) . '/wp-settings.php' ) ) {
				$this->jobdata['EXTRAFILESTOBACKUP'][]=str_replace('\\','/',dirname(ABSPATH) . '/wp-config.php');
				$this->jobdata['COUNT']['FILES']++;
				$this->jobdata['COUNT']['FILESIZE']=$this->jobdata['COUNT']['FILESIZE']+@filesize(dirname(ABSPATH) . '/wp-config.php');
				trigger_error(sprintf(__('Added "%s" to backup file list', 'backwpup'),'wp-config.php'), E_USER_NOTICE);
			}
			if ( file_exists( ABSPATH . '.htaccess') and !backwpup_get_option($this->jobdata['JOBMAIN'],'backuproot')) {
				$this->jobdata['EXTRAFILESTOBACKUP'][]=str_replace('\\','/',ABSPATH . '.htaccess');
				$this->jobdata['COUNT']['FILES']++;
				$this->jobdata['COUNT']['FILESIZE']=$this->jobdata['COUNT']['FILESIZE']+@filesize(ABSPATH . '.htaccess');
				trigger_error(sprintf(__('Added "%s" to backup file list', 'backwpup'),'.htaccess'), E_USER_NOTICE);
			}
			if ( file_exists( ABSPATH . '.htpasswd') and !backwpup_get_option($this->jobdata['JOBMAIN'],'backuproot')) {
				$this->jobdata['EXTRAFILESTOBACKUP'][]=str_replace('\\','/',ABSPATH . '.htpasswd');
				$this->jobdata['COUNT']['FILES']++;
				$this->jobdata['COUNT']['FILESIZE']=$this->jobdata['COUNT']['FILESIZE']+@filesize(ABSPATH . '.htpasswd');
				trigger_error(sprintf(__('Added "%s" to backup file list', 'backwpup'),'.htpasswd'), E_USER_NOTICE);
			}
			if ( file_exists( ABSPATH . 'robots.txt') and !backwpup_get_option($this->jobdata['JOBMAIN'],'backuproot')) {
				$this->jobdata['EXTRAFILESTOBACKUP'][]=str_replace('\\','/',ABSPATH . 'robots.txt');
				$this->jobdata['COUNT']['FILES']++;
				$this->jobdata['COUNT']['FILESIZE']=$this->jobdata['COUNT']['FILESIZE']+@filesize(ABSPATH . 'robots.txt');
				trigger_error(sprintf(__('Added "%s" to backup file list', 'backwpup'),'robots.txt'), E_USER_NOTICE);
			}
			if ( file_exists( ABSPATH . 'favicon.ico') and !backwpup_get_option($this->jobdata['JOBMAIN'],'backuproot')) {
				$this->jobdata['EXTRAFILESTOBACKUP'][]=str_replace('\\','/',ABSPATH . 'favicon.ico');
				$this->jobdata['COUNT']['FILES']++;
				$this->jobdata['COUNT']['FILESIZE']=$this->jobdata['COUNT']['FILESIZE']+@filesize(ABSPATH . 'favicon.ico');
				trigger_error(sprintf(__('Added "%s" to backup file list', 'backwpup'),'favicon.ico'), E_USER_NOTICE);
			}
		}

		if ( empty($this->jobdata['FOLDERLIST']) )
			trigger_error(__('No Folder to backup', 'backwpup'), E_USER_ERROR);
		else {
			//$this->jobdata['COUNT']['FOLDER']=count($this->jobdata['FOLDERLIST']);
			trigger_error(sprintf(__('%1$d Folders to backup', 'backwpup'), $this->jobdata['COUNT']['FOLDER']), E_USER_NOTICE);
		}

		$this->jobdata['STEPDONE'] = 7;
		$this->jobdata['STEPSDONE'][] = 'FOLDER_LIST'; //set done
		$this->_update_working_data();
	}

	private function _folder_list($folder = '', $levels = 100, $excludedirs = array()) {
		if ( empty($folder) )
			return false;
		if ( !$levels )
			return false;
		$this->jobdata['COUNT']['FOLDER']++;
		$folder = trailingslashit($folder);
		if ( $dir = @opendir($folder) ) {
			$this->jobdata['FOLDERLIST'][] = str_replace('\\', '/', $folder);
			while ( ($file = readdir($dir)) !== false ) {
				if ( in_array($file, array( '.', '..' )) )
					continue;
				foreach ($this->jobdata['FILEEXCLUDES'] as $exclusion) { //exclude files
					$exclusion=trim($exclusion);
					if (false !== stripos($folder.$file,trim($exclusion)) and !empty($exclusion))
						continue 2;
				}
				if ( is_dir($folder . $file) and !is_readable($folder . $file) ) {
					trigger_error(sprintf(__('Folder "%s" is not readable!', 'backwpup'), $folder . $file), E_USER_WARNING);
				} elseif ( is_dir($folder . $file) ) {
					if ( in_array(trailingslashit($folder . $file), $excludedirs) or in_array(trailingslashit($folder . $file), $this->jobdata['FOLDERLIST']) )
						continue;
					$this->_folder_list(trailingslashit($folder . $file), $levels - 1, $excludedirs);
				}
			}
			@closedir($dir);
		}
	}

	private function _get_files_in_folder($folder) {
		$files=array();
		if ( $dir = @opendir($folder) ) {
			while ( ($file = readdir($dir)) !== false ) {
				if ( in_array($file, array( '.', '..' )) )
					continue;
				foreach ($this->jobdata['FILEEXCLUDES'] as $exclusion) { //exclude files
					$exclusion=trim($exclusion);
					if (false !== stripos($folder.$file,trim($exclusion)) and !empty($exclusion))
						continue 2;
				}
				if (backwpup_get_option($this->jobdata['JOBMAIN'],'backupexcludethumbs') and strpos($folder,backwpup_get_upload_dir()) !== false and  preg_match("/\-[0-9]{2,4}x[0-9]{2,4}\.(jpg|png|gif)$/i",$file))
					continue;
				if ( !is_readable($folder . $file) )
					trigger_error(sprintf(__('File "%s" is not readable!', 'backwpup'), $folder . $file), E_USER_WARNING);
				elseif ( is_link($folder . $file) )
					trigger_error(sprintf(__('Link "%s" not followed', 'backwpup'),$folder . $file), E_USER_WARNING);
				elseif ( is_file($folder . $file) ) {
					$files[]=$folder . $file;
					$this->jobdata['COUNT']['FILESINFOLDER']++;
					$this->jobdata['COUNT']['FILESIZEINFOLDER']=$this->jobdata['COUNT']['FILESIZEINFOLDER']+@filesize($folder . $file);
				}
			}
			@closedir($dir);
		}
		return $files;
	}

	private function create_archive() {
		$this->jobdata['STEPTODO'] = count($this->jobdata['FOLDERLIST']) + 1;

		if ( strtolower(backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart')) == ".zip" and class_exists('ZipArchive') ) { //use php zip lib
			trigger_error(sprintf(__('%d. Trying to create backup zip archive...', 'backwpup'), $this->jobdata['CREATE_ARCHIVE']['STEP_TRY']), E_USER_NOTICE);
			$numopenfiles=0;
			$zip = new ZipArchive();
			$res = $zip->open($this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'], ZipArchive::CREATE);
			if ( $res !== true ) {
				trigger_error(sprintf(__('Can not create backup zip archive: %d!', 'backwpup'), $res), E_USER_ERROR);
				$this->jobdata['STEPSDONE'][] = 'CREATE_ARCHIVE'; //set done
				return;
			}
			//add extra files
			if ($this->jobdata['STEPDONE']==0) {
				if ( !empty($this->jobdata['EXTRAFILESTOBACKUP']) and $this->jobdata['STEPDONE'] == 0 ) {
					foreach ( $this->jobdata['EXTRAFILESTOBACKUP'] as $file ) {
						if ( !$zip->addFile($file, basename($file)) )
							trigger_error(sprintf(__('Can not add "%s" to zip archive!', 'backwpup'), basename($file)), E_USER_ERROR);
						$this->_update_working_data();
						$numopenfiles++;
					}
				}
				$this->jobdata['STEPDONE']++;
			}
			//add normal files
			for ( $i = $this->jobdata['STEPDONE'] - 1; $i < $this->jobdata['STEPTODO']-1; $i++ ) {
				$foldername=trim(str_replace($this->jobdata['REMOVEPATH'], '', $this->jobdata['FOLDERLIST'][$i]));
				if (!empty($foldername)) {
					if ( !$zip->addEmptyDir($foldername) )
						trigger_error(sprintf(__('Can not add dir "%s" to zip archive!', 'backwpup'), $foldername), E_USER_ERROR);
				}
				$files=$this->_get_files_in_folder($this->jobdata['FOLDERLIST'][$i]);
				if (count($files)>0) {
					foreach($files as $file) {
						$zipfilename=str_replace($this->jobdata['REMOVEPATH'], '', $file);
						if ( !$zip->addFile( $file,$zipfilename ) )
							trigger_error(sprintf(__('Can not add "%s" to zip archive!', 'backwpup'), $zipfilename), E_USER_ERROR);
						$this->_update_working_data();
					}
				}
				//colse and reopen, all added files are open on fs
				if ($numopenfiles>=30) { //35 works with PHP 5.2.4 on win
					if ( $zip->status > 0 ) {
						$ziperror = $zip->status;
						if ( $zip->status == 4 )
							$ziperror = __('(4) ER_SEEK', 'backwpup');
						if ( $zip->status == 5 )
							$ziperror = __('(5) ER_READ', 'backwpup');
						if ( $zip->status == 9 )
							$ziperror = __('(9) ER_NOENT', 'backwpup');
						if ( $zip->status == 10 )
							$ziperror = __('(10) ER_EXISTS', 'backwpup');
						if ( $zip->status == 11 )
							$ziperror = __('(11) ER_OPEN', 'backwpup');
						if ( $zip->status == 14 )
							$ziperror = __('(14) ER_MEMORY', 'backwpup');
						if ( $zip->status == 18 )
							$ziperror = __('(18) ER_INVAL', 'backwpup');
						if ( $zip->status == 19 )
							$ziperror = __('(19) ER_NOZIP', 'backwpup');
						if ( $zip->status == 21 )
							$ziperror = __('(21) ER_INCONS', 'backwpup');
						trigger_error(sprintf(__('Zip returns status: %s', 'backwpup'), $zip->status), E_USER_ERROR);
					}
					$zip->close();
					if ( $this->jobdata['STEPDONE'] == 0 )
						$this->jobdata['STEPDONE'] = 1;
					$zip->open($this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'], ZipArchive::CREATE );
					$numopenfiles=0;
				}
				$numopenfiles++;
				$this->jobdata['STEPDONE']++;
			}
			//clese Zip
			if ( $zip->status > 0 ) {
				$ziperror = $zip->status;
				if ( $zip->status == 4 )
					$ziperror = __('(4) ER_SEEK', 'backwpup');
				if ( $zip->status == 5 )
					$ziperror = __('(5) ER_READ', 'backwpup');
				if ( $zip->status == 9 )
					$ziperror = __('(9) ER_NOENT', 'backwpup');
				if ( $zip->status == 10 )
					$ziperror = __('(10) ER_EXISTS', 'backwpup');
				if ( $zip->status == 11 )
					$ziperror = __('(11) ER_OPEN', 'backwpup');
				if ( $zip->status == 14 )
					$ziperror = __('(14) ER_MEMORY', 'backwpup');
				if ( $zip->status == 18 )
					$ziperror = __('(18) ER_INVAL', 'backwpup');
				if ( $zip->status == 19 )
					$ziperror = __('(19) ER_NOZIP', 'backwpup');
				if ( $zip->status == 21 )
					$ziperror = __('(21) ER_INCONS', 'backwpup');
				trigger_error(sprintf(__('Zip returns status: %s', 'backwpup'), $zip->status), E_USER_ERROR);
			}
			$zip->close();
			trigger_error(__('Backup zip archive created', 'backwpup'), E_USER_NOTICE);
			$this->jobdata['STEPSDONE'][] = 'CREATE_ARCHIVE'; //set done
		}
		elseif ( strtolower(backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart')) == ".zip" ) { //use PclZip
			define('PCLZIP_TEMPORARY_DIR', backwpup_get_option('cfg','tempfolder'));
			if ( ini_get('mbstring.func_overload') && function_exists('mb_internal_encoding') ) {
				$previous_encoding = mb_internal_encoding();
				mb_internal_encoding('ISO-8859-1');
			}
			require_once(ABSPATH . 'wp-admin/includes/class-pclzip.php');
			//Create Zip File
			trigger_error(sprintf(__('%d. Trying to create backup zip (PclZip) archive...', 'backwpup'), $this->jobdata['CREATE_ARCHIVE']['STEP_TRY']), E_USER_NOTICE);
			$this->_need_free_memory('10M'); //10MB free memory for zip
			$zipbackupfile = new PclZip($this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE']);
			//add extra files
			if ( !empty($this->jobdata['EXTRAFILESTOBACKUP']) and $this->jobdata['STEPDONE'] == 0 ) {
				foreach ( $this->jobdata['EXTRAFILESTOBACKUP'] as $file ) {
					if ( 0 == $zipbackupfile->add(array( array( PCLZIP_ATT_FILE_NAME => $file, PCLZIP_ATT_FILE_NEW_FULL_NAME => basename($file) ) )) )
						trigger_error(sprintf(__('Zip archive add error: %s', 'backwpup'), $zipbackupfile->errorInfo(true)), E_USER_ERROR);
					$this->_update_working_data();
				}
			}
			if ( $this->jobdata['STEPDONE'] == 0 )
				$this->jobdata['STEPDONE'] = 1;
			//add normal files
			for ( $i = $this->jobdata['STEPDONE'] - 1; $i < $this->jobdata['STEPTODO']-1; $i++ ) {
				$files=$this->_get_files_in_folder($this->jobdata['FOLDERLIST'][$i]);
				if ( 0 == $zipbackupfile->add($files, PCLZIP_OPT_REMOVE_PATH, $this->jobdata['REMOVEPATH']) )
					trigger_error(sprintf(__('Zip archive add error: %s', 'backwpup'), $zipbackupfile->errorInfo(true)), E_USER_ERROR);
				$this->_update_working_data();
				$this->jobdata['STEPDONE']++;
			}
			if ( isset($previous_encoding) )
				mb_internal_encoding($previous_encoding);
			trigger_error(__('Backup zip archive created', 'backwpup'), E_USER_NOTICE);
			$this->jobdata['STEPSDONE'][] = 'CREATE_ARCHIVE'; //set done

		} elseif ( strtolower(backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart')) == ".tar.gz" or strtolower(backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart')) == ".tar.bz2" or strtolower(backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart')) == ".tar" ) { //tar files
			if ( strtolower(backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart')) == '.tar.gz' )
				$tarbackup = gzopen($this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'], 'ab9');
			elseif ( strtolower(backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart')) == '.tar.bz2' )
				$tarbackup = bzopen($this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'], 'w');
			else
				$tarbackup = fopen($this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'], 'ab');
			if ( !$tarbackup ) {
				trigger_error(__('Can not create tar arcive file!', 'backwpup'), E_USER_ERROR);
				$this->jobdata['STEPSDONE'][] = 'CREATE_ARCHIVE'; //set done
				return;
			} else {
				trigger_error(sprintf(__('%1$d. Trying to create %2$s archive file...', 'backwpup'), $this->jobdata['CREATE_ARCHIVE']['STEP_TRY'], substr(backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart'), 1)), E_USER_NOTICE);
			}
			//add extra files
			if ( !empty($this->jobdata['EXTRAFILESTOBACKUP']) and $this->jobdata['STEPDONE'] == 0 ) {
				foreach ( $this->jobdata['EXTRAFILESTOBACKUP'] as $file )
					$this->_tar_file($file, basename($file), $tarbackup);
			}
			if ( $this->jobdata['STEPDONE'] == 0 )
				$this->jobdata['STEPDONE'] = 1;
			//add normal files
			for ( $i = $this->jobdata['STEPDONE'] - 1; $i < $this->jobdata['STEPTODO']-1; $i++ ) {
				$foldername=trim(str_replace($this->jobdata['REMOVEPATH'], '', $this->jobdata['FOLDERLIST'][$i]));
				if (!empty($foldername))
					$this->_tar_foldername($this->jobdata['FOLDERLIST'][$i],$foldername, $tarbackup);
				$files=$this->_get_files_in_folder($this->jobdata['FOLDERLIST'][$i]);
				if (count($files)>0) {
					foreach($files as $file)
						$this->_tar_file($file, str_replace($this->jobdata['REMOVEPATH'], '', $file), $tarbackup);
				}
				$this->jobdata['STEPDONE']++;
				$this->_update_working_data();
			}
			// Add 1024 bytes of NULLs to designate EOF
			if ( strtolower(backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart')) == '.tar.gz' ) {
				gzwrite($tarbackup, pack("a1024", ""));
				gzclose($tarbackup);
			} elseif ( strtolower(backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart')) == '.tar.bz2' ) {
				bzwrite($tarbackup, pack("a1024", ""));
				bzclose($tarbackup);
			} else {
				fwrite($tarbackup, pack("a1024", ""));
				fclose($tarbackup);
			}
			trigger_error(sprintf(__('%s archive created', 'backwpup'), substr(backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart'), 1)), E_USER_NOTICE);
		}
		$this->jobdata['STEPSDONE'][] = 'CREATE_ARCHIVE'; //set done
		$this->jobdata['BACKUPFILESIZE'] = filesize($this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE']);
		if ( $this->jobdata['BACKUPFILESIZE'] )
			trigger_error(sprintf(__('Archive size is %s', 'backwpup'), backwpup_format_bytes($this->jobdata['BACKUPFILESIZE'])), E_USER_NOTICE);
		trigger_error(sprintf(__(' %1$d Files with %2$s in Archive', 'backwpup'),$this->jobdata['COUNT']['FILES']+$this->jobdata['COUNT']['FILESINFOLDER'] ,backwpup_format_bytes($this->jobdata['COUNT']['FILESIZE']+$this->jobdata['COUNT']['FILESIZEINFOLDER'])), E_USER_NOTICE);
	}

	private function _tar_file($file, $outfile, $handle) {
		$this->_need_free_memory('2M'); //2MB free memory
		//split filename larger than 100 chars
		if ( strlen($outfile) <= 100 ) {
			$filename = $outfile;
			$filenameprefix = "";
		} else {
			$filenameofset = strlen($outfile) - 100;
			$dividor = strpos($outfile, '/', $filenameofset);
			$filename = substr($outfile, $dividor + 1);
			$filenameprefix = substr($outfile, 0, $dividor);
			if ( strlen($filename) > 100 )
				trigger_error(sprintf(__('File name "%1$s" to long to save correctly in %2$s archive!', 'backwpup'), $outfile, substr(backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart'), 1)), E_USER_WARNING);
			if ( strlen($filenameprefix) > 155 )
				trigger_error(sprintf(__('File path "%1$s" to long to save correctly in %2$s archive!', 'backwpup'), $outfile, substr(backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart'), 1)), E_USER_WARNING);
		}
		//get file stat
		$filestat = stat($file);
		//Set file user/group name if linux
		$fileowner = __("Unknown","backwpup");
		$filegroup = __("Unknown","backwpup");
		if ( function_exists('posix_getpwuid') ) {
			$info = posix_getpwuid($filestat['uid']);
			$fileowner = $info['name'];
			$info = posix_getgrgid($filestat['gid']);
			$filegroup = $info['name'];
		}
		// Generate the TAR header for this file
		$header = pack("a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12",
			$filename, //name of file  100
			sprintf("%07o", $filestat['mode']), //file mode  8
			sprintf("%07o", $filestat['uid']), //owner user ID  8
			sprintf("%07o", $filestat['gid']), //owner group ID  8
			sprintf("%011o", $filestat['size']), //length of file in bytes  12
			sprintf("%011o", $filestat['mtime']), //modify time of file  12
			"        ", //checksum for header  8
			0, //type of file  0 or null = File, 5=Dir
			"", //name of linked file  100
			"ustar ", //USTAR indicator  6
			"00", //USTAR version  2
			$fileowner, //owner user name 32
			$filegroup, //owner group name 32
			"", //device major number 8
			"", //device minor number 8
			$filenameprefix, //prefix for file name 155
			""); //fill block 512K

		// Computes the unsigned Checksum of a file's header
		$checksum = 0;
		for ( $i = 0; $i < 512; $i++ )
			$checksum += ord(substr($header, $i, 1));
		$checksum = pack("a8", sprintf("%07o", $checksum));
		$header = substr_replace($header, $checksum, 148, 8);
		if ( strtolower(backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart')) == '.tar.gz' )
			gzwrite($handle, $header);
		elseif ( strtolower(backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart')) == '.tar.bz2' )
			bzwrite($handle, $header);
		else
			fwrite($handle, $header);
		// read/write files in 512K Blocks
		$fd = fopen($file, 'rb');
		while ( !feof($fd) ) {
			$filedata = fread($fd, 512);
			if ( strlen($filedata) > 0 ) {
				if ( strtolower(backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart')) == '.tar.gz' )
					gzwrite($handle, pack("a512", $filedata));
				elseif ( strtolower(backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart')) == '.tar.bz2' )
					bzwrite($handle, pack("a512", $filedata));
				else
					fwrite($handle, pack("a512", $filedata));
			}
		}
		fclose($fd);
	}

	private function _tar_foldername($folder, $foldername, $handle) {
		//split filename larger than 100 chars
		if ( strlen($foldername) <= 100 ) {
			$foldernameprefix = "";
		} else {
			$foldernameofset = strlen($foldername) - 100;
			$dividor = strpos($foldername, '/', $foldernameofset);
			$foldername = substr($foldername, $dividor + 1);
			$foldernameprefix = substr($foldername, 0, $dividor);
			if ( strlen($foldername) > 100 )
				trigger_error(sprintf(__('Folder name "%1$s" to long to save correctly in %2$s archive!', 'backwpup'), $foldername, substr(backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart'), 1)), E_USER_WARNING);
			if ( strlen($foldernameprefix) > 155 )
				trigger_error(sprintf(__('Folder path "%1$s" to long to save correctly in %2$s archive!', 'backwpup'), $foldername, substr(backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart'), 1)), E_USER_WARNING);
		}
		//get file stat
		$folderstat = stat($folder);
		//Set file user/group name if linux
		$folderowner = __("Unknown","backwpup");
		$foldergroup = __("Unknown","backwpup");
		if ( function_exists('posix_getpwuid') ) {
			$info = posix_getpwuid($folderstat['uid']);
			$folderowner = $info['name'];
			$info = posix_getgrgid($folderstat['gid']);
			$foldergroup = $info['name'];
		}
		// Generate the TAR header for this file
		$header = pack("a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12",
			$foldername, //name of file  100
			sprintf("%07o", $folderstat['mode']), //file mode  8
			sprintf("%07o", $folderstat['uid']), //owner user ID  8
			sprintf("%07o", $folderstat['gid']), //owner group ID  8
			sprintf("%011o", 0), //length of file in bytes  12
			sprintf("%011o", $folderstat['mtime']), //modify time of file  12
			"        ", //checksum for header  8
			5, //type of file  0 or null = File, 5=Dir
			"", //name of linked file  100
			"ustar ", //USTAR indicator  6
			"00", //USTAR version  2
			$folderowner, //owner user name 32
			$foldergroup, //owner group name 32
			"", //device major number 8
			"", //device minor number 8
			$foldernameprefix, //prefix for file name 155
			""); //fill block 512K

		// Computes the unsigned Checksum of a folder's header
		$checksum = 0;
		for ( $i = 0; $i < 512; $i++ )
			$checksum += ord(substr($header, $i, 1));
		$checksum = pack("a8", sprintf("%07o", $checksum));
		$header = substr_replace($header, $checksum, 148, 8);
		if ( strtolower(backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart')) == '.tar.gz' )
			gzwrite($handle, $header);
		elseif ( strtolower(backwpup_get_option($this->jobdata['JOBMAIN'],'fileformart')) == '.tar.bz2' )
			bzwrite($handle, $header);
		else
			fwrite($handle, $header);
	}

	private function dest_folder() {
		$this->jobdata['STEPTODO'] = 1;
		backwpup_update_option($this->jobdata['JOBMAIN'],'lastbackupdownloadurl', backwpup_admin_url('admin.php') . '?page=backwpupbackups&action=download&file=' . $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE']);
		//Delete old Backupfiles
		$backupfilelist = array();
		if ( backwpup_get_option($this->jobdata['JOBMAIN'],'maxbackups') > 0 ) {
			if ( $dir = @opendir($this->jobdata['BACKUPDIR']) ) { //make file list
				while ( ($file = readdir($dir)) !== false ) {
					if ( backwpup_get_option($this->jobdata['JOBMAIN'],'fileprefix') == substr($file, 0, strlen(backwpup_get_option($this->jobdata['JOBMAIN'],'fileprefix'))) )
						$backupfilelist[filemtime($this->jobdata['BACKUPDIR'] . $file)] = $file;
				}
				@closedir($dir);
			}
			if ( count($backupfilelist) > backwpup_get_option($this->jobdata['JOBMAIN'],'maxbackups') ) {
				$numdeltefiles = 0;
				while ( $file = array_shift($backupfilelist) ) {
					if ( count($backupfilelist) < backwpup_get_option($this->jobdata['JOBMAIN'],'maxbackups') )
						break;
					unlink($this->jobdata['BACKUPDIR'] . $file);
					$numdeltefiles++;
				}
				if ( $numdeltefiles > 0 )
					trigger_error(sprintf(_n('One backup file deleted', '%d backup files deleted', $numdeltefiles, 'backwpup'), $numdeltefiles), E_USER_NOTICE);
			}
		}
		$this->jobdata['STEPDONE']++;
		$this->jobdata['STEPSDONE'][] = 'DEST_FOLDER'; //set done
	}

	private function dest_folder_sync() {
		$this->jobdata['STEPTODO']=count($this->jobdata['FOLDERLIST']);
		trigger_error(sprintf(__('%d. Try to sync files with folder...','backwpup'),$this->jobdata['DEST_FOLDER_SYNC']['STEP_TRY']),E_USER_NOTICE);

		//create not existing folders
		foreach($this->jobdata['FOLDERLIST'] as $folder) {
			$testfolder=str_replace($this->jobdata['REMOVEPATH'], '', $folder);
			if (empty($testfolder))
				continue;
			if (!is_dir($this->jobdata['BACKUPDIR'].$testfolder))
				mkdir($this->jobdata['BACKUPDIR'].$testfolder,FS_CHMOD_DIR, true);
		}
		//sync folder by folder
		$this->_dest_folder_sync_files($this->jobdata['BACKUPDIR']);
		$this->jobdata['STEPDONE']++;
		$this->jobdata['STEPSDONE'][] = 'DEST_FOLDER_SYNC'; //set done
	}

	private function _dest_folder_sync_files($folder = '', $levels = 100) {
		if ( empty($folder) )
			return false;
		if ( !$levels )
			return false;
		$this->_update_working_data();
		$folder = trailingslashit($folder);
		//get files to sync
		$filestosync=$this->_get_files_in_folder($this->jobdata['REMOVEPATH'].trim(str_replace($this->jobdata['BACKUPDIR'], '', $folder)));
		if ($folder==$this->jobdata['BACKUPDIR']) //add extra files to sync
			$filestosync=array_merge($filestosync,$this->jobdata['EXTRAFILESTOBACKUP']);

		if ( $dir = @opendir($folder) ) {
			while ( ($file = readdir($dir)) !== false ) {
				if ( in_array($file, array( '.', '..' )) )
					continue;
				if ( !is_readable($folder . $file) ) {
					trigger_error(sprintf(__('File or folder "%s" is not readable!', 'backwpup'), $folder . $file), E_USER_WARNING);
				}  elseif ( is_dir($folder . $file) ) {
					$this->_dest_folder_sync_files(trailingslashit($folder . $file), $levels - 1);
					$testfolder=str_replace($this->jobdata['BACKUPDIR'], '', $folder . $file);
					if (!in_array($this->jobdata['REMOVEPATH'].$testfolder,$this->jobdata['FOLDERLIST'])) {
						rmdir($folder . $file);
						trigger_error(sprintf(__('Folder deleted %s','backwpup'),$folder . $file));
					}
				} elseif ( is_file($folder . $file) ) {
					$testfile=str_replace($this->jobdata['BACKUPDIR'], '', $folder . $file);
					if (in_array($this->jobdata['REMOVEPATH'].$testfile,$filestosync)) {
						if (filesize($this->jobdata['REMOVEPATH'].$testfile)!=filesize($folder . $file))
							copy($this->jobdata['REMOVEPATH'].$testfile,$folder . $file);
						foreach($filestosync as $key => $keyfile) {
							if ($keyfile==$this->jobdata['REMOVEPATH'].$testfile)
								unset($filestosync[$key]);
						}
					} else {
						unlink($folder . $file);
						trigger_error(sprintf(__('File deleted %s','backwpup'),$folder . $file));
					}
				}
			}
			@closedir($dir);
		}
		//sync new files
		foreach($filestosync as $keyfile) {
			copy($keyfile,$folder . basename($keyfile));
		}
	}

	private function dest_dropbox() {
		$this->jobdata['STEPTODO']=2+$this->jobdata['BACKUPFILESIZE'];
		trigger_error(sprintf(__('%d. Try to sending backup file to DropBox...','backwpup'),$this->jobdata['DEST_DROPBOX']['STEP_TRY']),E_USER_NOTICE);
		require_once(realpath(dirname(__FILE__).'/../libs/dropbox.php'));
		try {
			$dropbox = new backwpup_Dropbox(backwpup_get_option($this->jobdata['JOBMAIN'],'droperoot'));
			// set the tokens
			$dropbox->setOAuthTokens(backwpup_get_option($this->jobdata['JOBMAIN'],'dropetoken'),backwpup_get_option($this->jobdata['JOBMAIN'],'dropesecret'));
			//get account info
			$info=$dropbox->accountInfo();
			if (!empty($info['uid'])) {
				trigger_error(sprintf(__('Authed with DropBox from %s','backwpup'),$info['display_name'].' ('.$info['email'].')'),E_USER_NOTICE);
			}
			//Check Quota
			$dropboxfreespase=$info['quota_info']['quota']-$info['quota_info']['shared']-$info['quota_info']['normal'];
			if ($this->jobdata['BACKUPFILESIZE']>$dropboxfreespase) {
				trigger_error(__('No free space left on DropBox!!!','backwpup'),E_USER_ERROR);
				$this->jobdata['STEPSDONE'][]='DEST_DROPBOX'; //set done
				return;
			} else {
				trigger_error(sprintf(__('%s free on DropBox','backwpup'),backwpup_format_bytes($dropboxfreespase)),E_USER_NOTICE);
			}
			//set callback function
			$dropbox->setProgressFunction(array($this,'_curl_progresscallback'));
			// put the file
			trigger_error(__('Upload to DropBox now started... ','backwpup'),E_USER_NOTICE);
			$response = $dropbox->upload($this->jobdata['BACKUPDIR'].$this->jobdata['BACKUPFILE'],backwpup_get_option($this->jobdata['JOBMAIN'],'dropedir').$this->jobdata['BACKUPFILE']);
			if ($response['bytes']==filesize($this->jobdata['BACKUPDIR'].$this->jobdata['BACKUPFILE'])) {
				backwpup_update_option($this->jobdata['JOBMAIN'],'lastbackupdownloadurl',backwpup_admin_url('admin.php').'?page=backwpupbackups&action=downloaddropbox&file='.backwpup_get_option($this->jobdata['JOBMAIN'],'dropedir').$this->jobdata['BACKUPFILE'].'&jobid='.$this->jobdata['JOBID']);
				$this->jobdata['STEPDONE']++;
				$this->jobdata['STEPSDONE'][]='DEST_DROPBOX'; //set done
				trigger_error(sprintf(__('Backup transferred to %s','backwpup'),'https://api-content.dropbox.com/1/files/'.backwpup_get_option($this->jobdata['JOBMAIN'],'droperoot').'/'.backwpup_get_option($this->jobdata['JOBMAIN'],'dropedir').$this->jobdata['BACKUPFILE']),E_USER_NOTICE);
			} else {
				if ($response['bytes']!=filesize($this->jobdata['BACKUPDIR'].$this->jobdata['BACKUPFILE']))
					trigger_error(__('Uploaded file size and local file size not the same!!!','backwpup'),E_USER_ERROR);
				else
					trigger_error(sprintf(__('Error on transfer backup to DropBox: %s','backwpup'),$response['error']),E_USER_ERROR);
				return;
			}
		} catch (Exception $e) {
			trigger_error(sprintf(__('DropBox API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
		}
		try {
			if (backwpup_get_option($this->jobdata['JOBMAIN'],'dropemaxbackups')>0 and is_object($dropbox)) { //Delete old backups
				$backupfilelist=array();
				$metadata = $dropbox->metadata(backwpup_get_option($this->jobdata['JOBMAIN'],'dropedir'));
				if (is_array($metadata)) {
					foreach ($metadata['contents'] as $data) {
						$file=basename($data['path']);
						if ($data['is_dir']!=true and backwpup_get_option($this->jobdata['JOBMAIN'],'fileprefix') == substr($file,0,strlen(backwpup_get_option($this->jobdata['JOBMAIN'],'fileprefix'))))
							$backupfilelist[strtotime($data['modified'])]=$file;
					}
				}
				if (count($backupfilelist)>backwpup_get_option($this->jobdata['JOBMAIN'],'dropemaxbackups')) {
					$numdeltefiles=0;
					while ($file=array_shift($backupfilelist)) {
						if (count($backupfilelist)<backwpup_get_option($this->jobdata['JOBMAIN'],'dropemaxbackups'))
							break;
						$response=$dropbox->fileopsDelete(backwpup_get_option($this->jobdata['JOBMAIN'],'dropedir').$file); //delete files on Cloud
						if ($response['is_deleted']=='true')
							$numdeltefiles++;
						else
							trigger_error(sprintf(__('Error on delete file on DropBox: %s','backwpup'),$file),E_USER_ERROR);
					}
					if ($numdeltefiles>0)
						trigger_error(sprintf(_n('One file deleted on DropBox','%d files deleted on DropBox',$numdeltefiles,'backwpup'),$numdeltefiles),E_USER_NOTICE);
				}
			}
		} catch (Exception $e) {
			trigger_error(sprintf(__('DropBox API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
		}
		$this->jobdata['STEPDONE']++;
	}

	private function dest_ftp() {
		$this->jobdata['STEPTODO']=2;
		trigger_error(sprintf(__('%d. Try to sending backup file to a FTP Server...','backwpup'),$this->jobdata['DEST_FTP']['STEP_TRY']),E_USER_NOTICE);

		$this->_need_free_memory($this->jobdata['BACKUPFILESIZE']*1.5);

		if (backwpup_get_option($this->jobdata['JOBMAIN'],'ftpssl')) { //make SSL FTP connection
			if (function_exists('ftp_ssl_connect')) {
				$ftp_conn_id = ftp_ssl_connect(backwpup_get_option($this->jobdata['JOBMAIN'],'ftphost'),backwpup_get_option($this->jobdata['JOBMAIN'],'ftphostport'),backwpup_get_option($this->jobdata['JOBMAIN'],'ftptimeout'));
				if ($ftp_conn_id)
					trigger_error(sprintf(__('Connected by SSL-FTP to Server: %s','backwpup'),backwpup_get_option($this->jobdata['JOBMAIN'],'ftphost').':'.backwpup_get_option($this->jobdata['JOBMAIN'],'ftphostport')),E_USER_NOTICE);
				else {
					trigger_error(sprintf(__('Can not connect by SSL-FTP to Server: %s','backwpup'),backwpup_get_option($this->jobdata['JOBMAIN'],'ftphost').':'.backwpup_get_option($this->jobdata['JOBMAIN'],'ftphostport')),E_USER_ERROR);
					return false;
				}
			} else {
				trigger_error(__('PHP function to connect with SSL-FTP to server not exists!','backwpup'),E_USER_ERROR);
				$this->jobdata['STEPSDONE'][]='DEST_FTP'; //set done
				return;
			}
		} else { //make normal FTP connection if SSL not work
			$ftp_conn_id = ftp_connect(backwpup_get_option($this->jobdata['JOBMAIN'],'ftphost'),backwpup_get_option($this->jobdata['JOBMAIN'],'ftphostport'),backwpup_get_option($this->jobdata['JOBMAIN'],'ftptimeout'));
			if ($ftp_conn_id)
				trigger_error(sprintf(__('Connected to FTP server: %s','backwpup'),backwpup_get_option($this->jobdata['JOBMAIN'],'ftphost').':'.backwpup_get_option($this->jobdata['JOBMAIN'],'ftphostport')),E_USER_NOTICE);
			else {
				trigger_error(sprintf(__('Can not connect to FTP server: %s','backwpup'),backwpup_get_option($this->jobdata['JOBMAIN'],'ftphost').':'.backwpup_get_option($this->jobdata['JOBMAIN'],'ftphostport')),E_USER_ERROR);
				return false;
			}
		}

		//FTP Login
		trigger_error(sprintf(__('FTP Client command: %s','backwpup'),' USER '.backwpup_get_option($this->jobdata['JOBMAIN'],'ftpuser')),E_USER_NOTICE);
		if ($loginok=ftp_login($ftp_conn_id, backwpup_get_option($this->jobdata['JOBMAIN'],'ftpuser'), backwpup_decrypt(backwpup_get_option($this->jobdata['JOBMAIN'],'ftppass')))) {
			trigger_error(sprintf(__('FTP Server reply: %s','backwpup'),' User '.backwpup_get_option($this->jobdata['JOBMAIN'],'ftpuser').' logged in.'),E_USER_NOTICE);
		} else { //if PHP ftp login don't work use raw login
			$return=ftp_raw($ftp_conn_id,'USER '.backwpup_get_option($this->jobdata['JOBMAIN'],'ftpuser'));
			trigger_error(sprintf(__('FTP Server reply: %s','backwpup'),$return[0]),E_USER_NOTICE);
			if (substr(trim($return[0]),0,3)<=400) {
				trigger_error(sprintf(__('FTP Client command: %s','backwpup'),' PASS *******'),E_USER_NOTICE);
				$return=ftp_raw($ftp_conn_id,'PASS '.backwpup_decrypt(backwpup_get_option($this->jobdata['JOBMAIN'],'ftppass')));
				trigger_error(sprintf(__('FTP Server reply: %s','backwpup'),$return[0]),E_USER_NOTICE);
				if (substr(trim($return[0]),0,3)<=400)
					$loginok=true;
			}
		}
		if (!$loginok)
			return false;

		//PASV
		trigger_error(sprintf(__('FTP Client command: %s','backwpup'),' PASV'),E_USER_NOTICE);
		if (backwpup_get_option($this->jobdata['JOBMAIN'],'ftppasv')) {
			if (ftp_pasv($ftp_conn_id, true))
				trigger_error(sprintf(__('FTP Server reply: %s','backwpup'),__('Entering Passive Mode','backwpup')),E_USER_NOTICE);
			else
				trigger_error(sprintf(__('FTP Server reply: %s','backwpup'),__('Can not Entering Passive Mode','backwpup')),E_USER_WARNING);
		} else {
			if (ftp_pasv($ftp_conn_id, false))
				trigger_error(sprintf(__('FTP Server reply: %s','backwpup'),__('Entering Normal Mode','backwpup')),E_USER_NOTICE);
			else
				trigger_error(sprintf(__('FTP Server reply: %s','backwpup'),__('Can not Entering Normal Mode','backwpup')),E_USER_WARNING);
		}
		//SYSTYPE
		trigger_error(sprintf(__('FTP Client command: %s','backwpup'),' SYST'),E_USER_NOTICE);
		$systype=ftp_systype($ftp_conn_id);
		if ($systype)
			trigger_error(sprintf(__('FTP Server reply: %s','backwpup'),$systype),E_USER_NOTICE);
		else
			trigger_error(sprintf(__('FTP Server reply: %s','backwpup'),__('Error getting SYSTYPE','backwpup')),E_USER_ERROR);

		if ($this->jobdata['STEPDONE']==0) {
			//test ftp dir and create it if not exists
			$ftpdirs=explode("/", rtrim(backwpup_get_option($this->jobdata['JOBMAIN'],'ftpdir'),'/'));
			foreach ($ftpdirs as $ftpdir) {
				if (empty($ftpdir))
					continue;
				if (!@ftp_chdir($ftp_conn_id, $ftpdir)) {
					if (@ftp_mkdir($ftp_conn_id, $ftpdir)) {
						trigger_error(sprintf(__('FTP Folder "%s" created!','backwpup'),$ftpdir),E_USER_NOTICE);
						ftp_chdir($ftp_conn_id, $ftpdir);
					} else {
						trigger_error(sprintf(__('FTP Folder "%s" can not created!','backwpup'),$ftpdir),E_USER_ERROR);
						return false;
					}
				}
			}
			trigger_error(__('Upload to FTP now started ... ','backwpup'),E_USER_NOTICE);
			if (ftp_put($ftp_conn_id, backwpup_get_option($this->jobdata['JOBMAIN'],'ftpdir').$this->jobdata['BACKUPFILE'], $this->jobdata['BACKUPDIR'].$this->jobdata['BACKUPFILE'], FTP_BINARY)) { //transfer file
				$this->jobdata['STEPTODO']=1+$this->jobdata['BACKUPFILESIZE'];
				trigger_error(sprintf(__('Backup transferred to FTP server: %s','backwpup'),backwpup_get_option($this->jobdata['JOBMAIN'],'ftpdir').$this->jobdata['BACKUPFILE']),E_USER_NOTICE);
				backwpup_update_option($this->jobdata['JOBMAIN'],'lastbackupdownloadurl',"ftp://".backwpup_get_option($this->jobdata['JOBMAIN'],'ftpuser').":".backwpup_decrypt(backwpup_get_option($this->jobdata['JOBMAIN'],'ftppass'))."@".backwpup_get_option($this->jobdata['JOBMAIN'],'ftphost').backwpup_get_option($this->jobdata['JOBMAIN'],'ftpdir').$this->jobdata['BACKUPFILE']);
				$this->jobdata['STEPSDONE'][]='DEST_FTP'; //set done
			} else
				trigger_error(__('Can not transfer backup to FTP server!','backwpup'),E_USER_ERROR);
		}

		if (backwpup_get_option($this->jobdata['JOBMAIN'],'ftpmaxbackups')>0) { //Delete old backups
			$backupfilelist=array();
			if ($filelist=ftp_nlist($ftp_conn_id, backwpup_get_option($this->jobdata['JOBMAIN'],'ftpdir'))) {
				foreach($filelist as $file) {
					if ( backwpup_get_option($this->jobdata['JOBMAIN'],'fileprefix') == substr(basename($file),0,strlen(backwpup_get_option($this->jobdata['JOBMAIN'],'fileprefix')))  ) {
						$time=ftp_mdtm($ftp_conn_id,'"'.basename($file).'"');
						if (!isset($time) or $time==-1) {
							$timestring=str_replace(array(backwpup_get_option($this->jobdata['JOBMAIN'],'fileprefix'),'.tar.gz','.tar.bz2','.tar','.zip'),'',basename($file));
							list($dateex,$timeex)=explode('_',$timestring);
							$time=strtotime($dateex.' '.str_replace('-',':',$timeex));
						}
						$backupfilelist[$time]=basename($file);
					}
				}
				if (count($backupfilelist)>backwpup_get_option($this->jobdata['JOBMAIN'],'ftpmaxbackups')) {
					$numdeltefiles=0;
					while ($file=array_shift($backupfilelist)) {
						if (count($backupfilelist)<backwpup_get_option($this->jobdata['JOBMAIN'],'ftpmaxbackups'))
							break;
						if (ftp_delete($ftp_conn_id, backwpup_get_option($this->jobdata['JOBMAIN'],'ftpdir').$file)) //delete files on ftp
							$numdeltefiles++;
						else
							trigger_error(sprintf(__('Can not delete "%s" on FTP server!','backwpup'),backwpup_get_option($this->jobdata['JOBMAIN'],'ftpdir').$file),E_USER_ERROR);

					}
					if ($numdeltefiles>0)
						trigger_error(sprintf(_n('One file deleted on FTP Server','%d files deleted on FTP Server',$numdeltefiles,'backwpup'),$numdeltefiles),E_USER_NOTICE);
				}
			}
		}

		ftp_close($ftp_conn_id);
		$this->jobdata['STEPDONE']++;
	}


	private function dest_s3() {
		$this->jobdata['STEPTODO']=2+$this->jobdata['BACKUPFILESIZE'];
		trigger_error(sprintf(__('%d. Try to sending backup file to Amazon S3...','backwpup'),$this->jobdata['DEST_S3']['STEP_TRY']),E_USER_NOTICE);

		if (!class_exists('AmazonS3'))
			require_once(dirname(__FILE__).'/../libs/aws/sdk.class.php');

		try {
			CFCredentials::set(array('backwpup' => array('key'=>backwpup_get_option($this->jobdata['JOBMAIN'],'awsAccessKey'),'secret'=>backwpup_get_option($this->jobdata['JOBMAIN'],'awsSecretKey'),'default_cache_config'=>'','certificate_authority'=>true),'@default' => 'backwpup'));
			$s3 = new AmazonS3();
			if ($s3->if_bucket_exists(backwpup_get_option($this->jobdata['JOBMAIN'],'awsBucket'))) {
				$bucketregion=$s3->get_bucket_region(backwpup_get_option($this->jobdata['JOBMAIN'],'awsBucket'));
				trigger_error(sprintf(__('Connected to S3 Bucket "%1$s" in %2$s','backwpup'),backwpup_get_option($this->jobdata['JOBMAIN'],'awsBucket'),$bucketregion->body),E_USER_NOTICE);
			} else {
				trigger_error(sprintf(__('S3 Bucket "%s" not exists!','backwpup'),backwpup_get_option($this->jobdata['JOBMAIN'],'awsBucket')),E_USER_ERROR);
				$this->jobdata['STEPSDONE'][]='DEST_S3'; //set done
				return;
			}
			//create Parameter
			$params=array();
			$params['fileUpload']=$this->jobdata['BACKUPDIR'].$this->jobdata['BACKUPFILE'];
			$params['acl']=AmazonS3::ACL_PRIVATE;
			if (backwpup_get_option($this->jobdata['JOBMAIN'],'awsssencrypt'))
				$params['encryption']=backwpup_get_option($this->jobdata['JOBMAIN'],'awsssencrypt');
			if (backwpup_get_option($this->jobdata['JOBMAIN'],'awsrrs')) //set reduced redundancy or not
				$params['storage']=AmazonS3::STORAGE_REDUCED;
			else
				$params['storage']=AmazonS3::STORAGE_STANDARD;
			if (defined('CURLOPT_PROGRESSFUNCTION'))
				$params['curlopts']=array(CURLOPT_NOPROGRESS=>false,CURLOPT_PROGRESSFUNCTION=>array($this,'_curl_progresscallback'),CURLOPT_BUFFERSIZE=>512);
			//transfer file to S3
			trigger_error(__('Upload to Amazon S3 now started... ','backwpup'),E_USER_NOTICE);
			$result=$s3->create_object(backwpup_get_option($this->jobdata['JOBMAIN'],'awsBucket'), backwpup_get_option($this->jobdata['JOBMAIN'],'awsdir').$this->jobdata['BACKUPFILE'],$params);
			$result=(array)$result;
			if ($result["status"]=200 and $result["status"]<300)  {
				$this->jobdata['STEPTODO']=1+$this->jobdata['BACKUPFILESIZE'];
				trigger_error(sprintf(__('Backup transferred to %s','backwpup'),$result["header"]["_info"]["url"]),E_USER_NOTICE);
				backwpup_update_option($this->jobdata['JOBMAIN'],'lastbackupdownloadurl',backwpup_admin_url('admin.php').'?page=backwpupbackups&action=downloads3&file='.backwpup_get_option($this->jobdata['JOBMAIN'],'awsdir').$this->jobdata['BACKUPFILE'].'&jobid='.$this->jobdata['JOBID']);
				$this->jobdata['STEPSDONE'][]='DEST_S3'; //set done
			} else {
				trigger_error(sprintf(__('Can not transfer backup to S3! (%1$d) %2$s','backwpup'),$result["status"],$result["Message"]),E_USER_ERROR);
			}
		} catch (Exception $e) {
			trigger_error(sprintf(__('Amazon API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
			return;
		}
		try {
			if ($s3->if_bucket_exists(backwpup_get_option($this->jobdata['JOBMAIN'],'awsBucket'))) {
				if (backwpup_get_option($this->jobdata['JOBMAIN'],'awsmaxbackups')>0) { //Delete old backups
					$backupfilelist=array();
					if (($contents = $s3->list_objects(backwpup_get_option($this->jobdata['JOBMAIN'],'awsBucket'),array('prefix'=>backwpup_get_option($this->jobdata['JOBMAIN'],'awsdir')))) !== false) {
						foreach ($contents->body->Contents as $object) {
							$file=basename($object->Key);
							$changetime=strtotime($object->LastModified);
							if (backwpup_get_option($this->jobdata['JOBMAIN'],'fileprefix') == substr($file,0,strlen(backwpup_get_option($this->jobdata['JOBMAIN'],'fileprefix'))))
								$backupfilelist[$changetime]=$file;
						}
					}
					if (count($backupfilelist)>backwpup_get_option($this->jobdata['JOBMAIN'],'awsmaxbackups')) {
						$numdeltefiles=0;
						while ($file=array_shift($backupfilelist)) {
							if (count($backupfilelist)<backwpup_get_option($this->jobdata['JOBMAIN'],'awsmaxbackups'))
								break;
							if ($s3->delete_object(backwpup_get_option($this->jobdata['JOBMAIN'],'awsBucket'), backwpup_get_option($this->jobdata['JOBMAIN'],'awsdir').$file)) //delte files on S3
								$numdeltefiles++;
							else
								trigger_error(sprintf(__('Can not delete backup on S3://%s','backwpup'),backwpup_get_option($this->jobdata['JOBMAIN'],'awsBucket').'/'.backwpup_get_option($this->jobdata['JOBMAIN'],'awsdir').$file),E_USER_ERROR);
						}
						if ($numdeltefiles>0)
							trigger_error(sprintf(_n('One file deleted on S3 Bucket','%d files deleted on S3 Bucket',$numdeltefiles,'backwpup'),$numdeltefiles),E_USER_NOTICE);
					}
				}
			}
		} catch (Exception $e) {
			trigger_error(sprintf(__('Amazon API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
			return;
		}
		$this->jobdata['STEPDONE']++;
	}

	private function dest_mail() {
		$this->jobdata['STEPTODO']=1;
		trigger_error(sprintf(__('%d. Try to sending backup with mail...','backwpup'),$this->jobdata['DEST_MAIL']['STEP_TRY']),E_USER_NOTICE);

		//check file Size
		if (backwpup_get_option($this->jobdata['JOBMAIN'],'mailefilesize')) {
			if ($this->jobdata['BACKUPFILESIZE']>backwpup_get_option($this->jobdata['JOBMAIN'],'mailefilesize')*1024*1024) {
				trigger_error(__('Backup archive too big for sending by mail!','backwpup'),E_USER_ERROR);
				$this->jobdata['STEPDONE']=1;
				$this->jobdata['STEPSDONE'][]='DEST_MAIL'; //set done
				return;
			}
		}

		trigger_error(__('Sending mail...','backwpup'),E_USER_NOTICE);
		if (backwpup_get_option('cfg','mailsndname'))
			$headers = 'From: '.backwpup_get_option('cfg','mailsndname').' <'.backwpup_get_option('cfg','mailsndemail').'>' . "\r\n";
		else
			$headers = 'From: '.backwpup_get_option('cfg','mailsndemail') . "\r\n";

		$this->_need_free_memory($this->jobdata['BACKUPFILESIZE']*5);
		$mail=wp_mail(backwpup_get_option($this->jobdata['JOBMAIN'],'mailaddress'),
			sprintf(__('BackWPup archive from %1$s: %2$s','backwpup'),date_i18n('d-M-Y H:i',backwpup_get_option($this->jobdata['JOBMAIN'],'starttime')),backwpup_get_option($this->jobdata['JOBMAIN'],'name')),
			sprintf(__('Backup archive: %s','backwpup'),$this->jobdata['BACKUPFILE']),
			$headers,array($this->jobdata['BACKUPDIR'].$this->jobdata['BACKUPFILE']));

		if (!$mail) {
			trigger_error(__('Error on sending mail!','backwpup'),E_USER_ERROR);
		} else {
			$this->jobdata['STEPTODO']=$this->jobdata['BACKUPFILESIZE'];
			trigger_error(__('Mail sent.','backwpup'),E_USER_NOTICE);
		}
		$this->jobdata['STEPSDONE'][]='DEST_MAIL'; //set done
	}
}
?>