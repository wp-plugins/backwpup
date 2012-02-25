<?php
if ( ! defined( 'ABSPATH' ) ) {
	header( $_SERVER["SERVER_PROTOCOL"] . " 404 Not Found" );
	header( "Status: 404 Not Found" );
	die();
}

/**
 * Class in that the BackWPup job runs
 */
class BackWPup_Job {
	protected $jobdata = false;
	protected $line_separator = "\n";
	private $jobstarttype = '';
	private $scriptstarttime = 0;

	/**
	 *
	 * This starts or restarts the job working
	 *
	 * @param string $starttype Start types are 'runnow', 'runnowalt', 'cronrun', 'runext', 'runcmd', 'apirun', 'restart'
	 * @param int   $jobid	 The id of job to start
	 */
	public function __construct( $starttype, $jobid = 0 ) {
		$this->scriptstarttime = microtime(true);
		$this->jobstarttype = $starttype;
		if ( false !== strpos( PHP_OS, "WIN" ) or false !== strpos( PHP_OS, "OS/2" ) )
			$this->line_separator = "\r\n";
		//get job data
		if ( in_array( $this->jobstarttype, array( 'runnow', 'runnowalt', 'cronrun', 'runext', 'runcmd', 'apirun' ) ) )
			$this->start( (int) $jobid );
		else
			$this->jobdata = backwpup_get_workingdata();
		//set function for PHP user defined error handling
		$this->jobdata['PHP']['INI']['ERROR_LOG']      = ini_get( 'error_log' );
		$this->jobdata['PHP']['INI']['LOG_ERRORS']     = ini_get( 'log_errors' );
		$this->jobdata['PHP']['INI']['DISPLAY_ERRORS'] = ini_get( 'display_errors' );
		@ini_set( 'error_log', $this->jobdata['LOGFILE'] );
		@ini_set( 'display_errors', 'Off' );
		@ini_set( 'log_errors', 'On' );
		@ini_set( 'mysql.connect_timeout', '60' );
		set_error_handler( array( $this, 'error_handler' ), E_ALL | E_STRICT );
		set_exception_handler( array( $this, 'exception_handler' ) );
		//Check Folder
		if ( ! empty($this->jobdata['BACKUPDIR']) && $this->jobdata['BACKUPDIR'] != backwpup_get_option( 'cfg', 'tempfolder' ) )
			$this->check_folder( $this->jobdata['BACKUPDIR'] );
		if ( backwpup_get_option( 'cfg', 'tempfolder' ) )
			$this->check_folder( backwpup_get_option( 'cfg', 'tempfolder' ) );
		if ( backwpup_get_option( 'cfg', 'logfolder' ) )
			$this->check_folder( backwpup_get_option( 'cfg', 'logfolder' ) );
		//Check double running and inactivity
		if ( $this->jobdata['PID'] != 0 && $this->jobdata['PID'] != getmypid() && (microtime( true ) - $this->jobdata['TIMESTAMP'] < 300) && $this->jobstarttype == 'restart' ) {
			trigger_error( __( 'Job restart terminated, because job runs!', 'backwpup' ), E_USER_ERROR );
			die();
		} elseif ( $this->jobdata['PID'] != 0 && $this->jobstarttype == 'restart' ) {
			trigger_error( __( 'Job restart due to inactivity for more than 5 min.!', 'backwpup' ), E_USER_ERROR );
		} elseif ( $this->jobdata['PID'] != getmypid() && $this->jobdata['PID'] != 0 && (microtime( true ) - $this->jobdata['TIMESTAMP'] >= 480) ) {
			trigger_error( sprintf( __( 'Second process is running, but old job runs! Start type is %s', 'backwpup' ), $this->jobstarttype ), E_USER_ERROR );
			die();
		}
		//set Pid
		$this->jobdata['PID'] = getmypid();
		// execute function on job shutdown
		register_shutdown_function( array( $this, 'shutdown' ) );
		if ( function_exists( 'pcntl_signal' ) ) {
			declare(ticks = 1) ; //set ticks
			pcntl_signal( 15, array( $this, 'shutdown' ) ); //SIGTERM
			//pcntl_signal(9, array($this,'shutdown')); //SIGKILL
			pcntl_signal( 2, array( $this, 'shutdown' ) ); //SIGINT
		}
		$this->update_working_data( true );
		// Working step by step
		foreach ( $this->jobdata['STEPS'] as $step ) {
			//Set next step
			if ( ! isset($this->jobdata[$step]['STEP_TRY']) ) {
				$this->jobdata[$step]['STEP_TRY'] = 0;
				$this->jobdata['STEPDONE']        = 0;
				$this->jobdata['STEPTODO']        = 0;
			}
			//update running file
			$this->update_working_data( true );
			//Run next step
			if ( ! in_array( $step, $this->jobdata['STEPSDONE'] ) ) {
				if ( method_exists( $this, strtolower( $step ) ) ) {
					while ( $this->jobdata[$step]['STEP_TRY'] < backwpup_get_option( 'cfg', 'jobstepretry' ) ) {
						if ( in_array( $step, $this->jobdata['STEPSDONE'] ) )
							break;
						$this->jobdata[$step]['STEP_TRY'] ++;
						$this->update_working_data( true );
						call_user_func( array( $this, strtolower( $step ) ) );
					}
					if ( $this->jobdata[$step]['STEP_TRY'] >= backwpup_get_option( 'cfg', 'jobstepretry' ) )
						trigger_error( __( 'Step aborted has too many tries!', 'backwpup' ), E_USER_ERROR );
				} else {
					trigger_error( sprintf( __( 'Can not find job step method %s!', 'backwpup' ), strtolower( $step ) ), E_USER_ERROR );
					$this->jobdata['STEPSDONE'][] = $step;
				}
			}
		}
	}

	/**
	 *
	 * Shutdown function	is call if script terminates trys to make a restart if needed
	 *
	 * Prepare the job for start
	 *
	 * @param int the signal that terminates the job
	 */
	public function shutdown() { //can not in __destruct()
		$args = func_get_args();
		//nothing on empty
		if ( empty($this->jobdata['LOGFILE']) )
			return;
		//Put last error to log if one
		$lasterror = error_get_last();
		if ( $lasterror['type'] == E_ERROR or $lasterror['type'] == E_PARSE or $lasterror['type'] == E_CORE_ERROR or $lasterror['type'] == E_CORE_WARNING or $lasterror['type'] == E_COMPILE_ERROR or $lasterror['type'] == E_COMPILE_WARNING )
			$this->error_handler( $lasterror['type'], $lasterror['message'], $lasterror['file'], $lasterror['line'], false );
		//Put sigterm to log
		if ( ! empty($args[0]))
			$this->error_handler( E_USER_ERROR, sprintf( __( 'Signal %d send to script!', 'backwpup' ), $args[0] ), __FILE__, __LINE__, false );
		//no more restarts
		$this->jobdata['RESTART'] ++;
		if ( (defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON) or $this->jobdata['RESTART'] >= backwpup_get_option( 'cfg', 'jobscriptretry' ) ) { //only x restarts allowed
			if ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON )
				$this->error_handler( E_USER_ERROR, __( 'Can not restart on alternate cron....', 'backwpup' ), __FILE__, __LINE__, false );
			else
				$this->error_handler( E_USER_ERROR, __( 'To many restarts....', 'backwpup' ), __FILE__, __LINE__, false );
			$this->end();
			exit;
		}
		if ( ! backwpup_get_workingdata( false ) )
			exit;
		//Back from maintenance if not
		if ( is_file( ABSPATH . '.maintenance' ) or (defined( 'FB_WM_TEXTDOMAIN' ) && (get_site_option( FB_WM_TEXTDOMAIN . '-msqld' ) == 1 or get_option( FB_WM_TEXTDOMAIN . '-msqld' ) == 1)) )
			$this->update_working_data( false );
		//set PID to 0
		$this->jobdata['PID'] = 0;
		//Restart job
		$this->update_working_data( true );
		$this->error_handler( E_USER_NOTICE, sprintf( __( '%d. Script stop! Will started again now!', 'backwpup' ), $this->jobdata['RESTART'] ), __FILE__, __LINE__, false );
		backwpup_jobrun_url( 'restart');
		exit;
	}

	/**
	 *
	 * Prepare the job for start
	 *
	 * @param $jobid int the job id to start
	 */
	private function start( $jobid ) {
		global $wpdb, $wp_version;
		if ( empty($jobid) )
			return;
		//make start on cli mode
		if ( defined( 'STDIN' ) )
			_e( 'Run!', 'backwpup' );
		//clean var
		$this->jobdata = array();
		//check exists gzip functions
		if ( ! function_exists( 'gzopen' ) )
			backwpup_update_option( 'cfg', 'gzlogs', false );
		//set Logfile
		$this->jobdata['LOGFILE'] = backwpup_get_option( 'cfg', 'logfolder' ) . 'backwpup_log_' . date_i18n( 'Y-m-d_H-i-s' ) . '.html';
		//Set job data
		$this->jobdata['JOBID']   = $jobid;
		$this->jobdata['JOBMAIN'] = 'job_' . $jobid;
		//Set job start settings
		backwpup_update_option( $this->jobdata['JOBMAIN'], 'starttime', current_time( 'timestamp' ) ); //set start time for job
		backwpup_update_option( $this->jobdata['JOBMAIN'], 'logfile', $this->jobdata['LOGFILE'] ); //Set current logfile
		backwpup_update_option( $this->jobdata['JOBMAIN'], 'lastbackupdownloadurl', '' );
		backwpup_update_option( $this->jobdata['JOBMAIN'], 'cronnextrun', BackWPup_Cron::cron_next( backwpup_get_option( $this->jobdata['JOBMAIN'], 'cron' ) ) );
		//only for jobs that makes backups
		if ( in_array( 'FILE', backwpup_get_option( $this->jobdata['JOBMAIN'], 'type' ) ) or in_array( 'DB', backwpup_get_option( $this->jobdata['JOBMAIN'], 'type' ) ) or in_array( 'WPEXP', backwpup_get_option( $this->jobdata['JOBMAIN'], 'type' ) ) ) {
			//make empty file list
			if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backuptype' ) == 'archive' ) {
				//set Backup folder to temp folder if not set
				$this->jobdata['BACKUPDIR'] = backwpup_get_option( $this->jobdata['JOBMAIN'], 'backupdir' );
				if ( ! $this->jobdata['BACKUPDIR'] or $this->jobdata['BACKUPDIR'] == '/' )
					$this->jobdata['BACKUPDIR'] = backwpup_get_option( 'cfg', 'tempfolder' );
				//Create backup archive full file name
				$this->jobdata['BACKUPFILE'] = backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileprefix' ) . date_i18n( 'Y-m-d_H-i-s' ) . backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileformart' );
			}
		}
		$this->jobdata['BACKUPFILESIZE']            = 0;
		$this->jobdata['PID']                       = 0;
		$this->jobdata['WARNING']                   = 0;
		$this->jobdata['ERROR']                     = 0;
		$this->jobdata['RESTART']                   = 0;
		$this->jobdata['STEPSDONE']                 = array();
		$this->jobdata['STEPTODO']                  = 0;
		$this->jobdata['STEPDONE']                  = 0;
		$this->jobdata['STEPSPERSENT']              = 0;
		$this->jobdata['STEPPERSENT']               = 0;
		$this->jobdata['TIMESTAMP']                 = microtime( true );
		$this->jobdata['ENDINPROGRESS']             = false;
		$this->jobdata['EXTRAFILESTOBACKUP']        = array();
		$this->jobdata['FOLDERLIST']                = array();
		$this->jobdata['FILEEXCLUDES']              = explode( ',', trim( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileexclude' ) ) );
		$this->jobdata['FILEEXCLUDES']              = array_unique( $this->jobdata['FILEEXCLUDES'] );
		$this->jobdata['DBDUMPFILE']                = false;
		$this->jobdata['WPEXPORTFILE']              = false;
		$this->jobdata['PLUGINLISTFILE']            = false;
		$this->jobdata['COUNT']['FILES']            = 0;
		$this->jobdata['COUNT']['FILESIZE']         = 0;
		$this->jobdata['COUNT']['FOLDER']           = 0;
		$this->jobdata['COUNT']['FILESINFOLDER']    = 0;
		$this->jobdata['COUNT']['FILESIZEINFOLDER'] = 0;
		//create path to remove
		if ( trailingslashit( str_replace( '\\', '/', ABSPATH ) ) == '/' or trailingslashit( str_replace( '\\', '/', ABSPATH ) ) == '' )
			$this->jobdata['REMOVEPATH'] = '';
		else
			$this->jobdata['REMOVEPATH'] = trailingslashit( str_replace( '\\', '/', ABSPATH ) );
		//build working steps
		$this->jobdata['STEPS'] = array();
		//setup job steps
		if ( in_array( 'DB', backwpup_get_option( $this->jobdata['JOBMAIN'], 'type' ) ) )
			$this->jobdata['STEPS'][] = 'DB_DUMP';
		if ( in_array( 'WPEXP', backwpup_get_option( $this->jobdata['JOBMAIN'], 'type' ) ) ) {
			if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'wpexportfile' ) )
				$this->jobdata['STEPS'][] = 'WP_EXPORT';
			if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'pluginlistfile' ) )
				$this->jobdata['STEPS'][] = 'WP_PLUGIN_LIST';
		}
		if ( in_array( 'FILE', backwpup_get_option( $this->jobdata['JOBMAIN'], 'type' ) ) )
			$this->jobdata['STEPS'][] = 'FOLDER_LIST';
		if ( in_array( 'DB', backwpup_get_option( $this->jobdata['JOBMAIN'], 'type' ) ) or in_array( 'WPEXP', backwpup_get_option( $this->jobdata['JOBMAIN'], 'type' ) ) or in_array( 'FILE', backwpup_get_option( $this->jobdata['JOBMAIN'], 'type' ) ) ) {
			//Add archive creation on backup type archive
			if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backuptype' ) == 'archive' )
				$this->jobdata['STEPS'][] = 'CREATE_ARCHIVE';
			$appendsync = '';
			if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backuptype' ) == 'sync' )
				$appendsync = '_SYNC';
			//ADD Destinations
			if ( in_array( 'FOLDER', explode( ',', strtoupper( BACKWPUP_DESTS ) ) ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'BACKUPDIR' ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'BACKUPDIR' ) != '/' )
				$this->jobdata['STEPS'][] = 'DEST_FOLDER' . $appendsync;
			if ( in_array( 'MAIL', explode( ',', strtoupper( BACKWPUP_DESTS ) ) ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'mailaddress' ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'backuptype' ) == 'archive' )
				$this->jobdata['STEPS'][] = 'DEST_MAIL';
			if ( in_array( 'FTP', explode( ',', strtoupper( BACKWPUP_DESTS ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftphost' ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftpuser' ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftppass' ) ) ) )
				$this->jobdata['STEPS'][] = 'DEST_FTP' . $appendsync;
			if ( in_array( 'DROPBOX', explode( ',', strtoupper( BACKWPUP_DESTS ) ) ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'dropetoken' ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'dropesecret' ) )
				$this->jobdata['STEPS'][] = 'DEST_DROPBOX' . $appendsync;
			if ( in_array( 'SUGARSYNC', explode( ',', strtoupper( BACKWPUP_DESTS ) ) ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'sugaruser' ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'sugarpass' ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'sugarroot' ) )
				$this->jobdata['STEPS'][] = 'DEST_SUGARSYNC' . $appendsync;
			if ( in_array( 'S3', explode( ',', strtoupper( BACKWPUP_DESTS ) ) ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsAccessKey' ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsSecretKey' ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsBucket' ) )
				$this->jobdata['STEPS'][] = 'DEST_S3' . $appendsync;
			if ( in_array( 'GSTORAGE', explode( ',', strtoupper( BACKWPUP_DESTS ) ) ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStorageAccessKey' ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStorageSecret' ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStorageBucket' ) )
				$this->jobdata['STEPS'][] = 'DEST_GSTORAGE' . $appendsync;
			if ( in_array( 'RSC', explode( ',', strtoupper( BACKWPUP_DESTS ) ) ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscUsername' ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscAPIKey' ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscContainer' ) )
				$this->jobdata['STEPS'][] = 'DEST_RSC' . $appendsync;
			if ( in_array( 'MSAZURE', explode( ',', strtoupper( BACKWPUP_DESTS ) ) ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazureHost' ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazureAccName' ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazureKey' ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazureContainer' ) )
				$this->jobdata['STEPS'][] = 'DEST_MSAZURE' . $appendsync;
		}
		if ( in_array( 'CHECK', backwpup_get_option( $this->jobdata['JOBMAIN'], 'type' ) ) )
			$this->jobdata['STEPS'][] = 'DB_CHECK';
		if ( in_array( 'OPTIMIZE', backwpup_get_option( $this->jobdata['JOBMAIN'], 'type' ) ) )
			$this->jobdata['STEPS'][] = 'DB_OPTIMIZE';
		$this->jobdata['STEPS'][] = 'END';
		//mark all as not done
		foreach ( $this->jobdata['STEPS'] as $step )
		{
			$this->jobdata[$step]['DONE'] = false;
		}
		//must write working data
		if ( backwpup_get_option( 'cfg', 'storeworkingdatain' ) == 'db' )
			backwpup_update_option( 'working', 'data', $this->jobdata );
		if ( backwpup_get_option( 'cfg', 'storeworkingdatain' ) == 'file' )
			file_put_contents( backwpup_get_option( 'cfg', 'tempfolder' ) . '.backwpup_working_' . substr( md5( ABSPATH ), 16 ), maybe_serialize( $this->jobdata ) );
		//add cron for restart in 5 min if needed
		wp_clear_scheduled_hook( 'backwpup_cron', array( 'main'=> 'restart' ) );
		wp_schedule_single_event( time() + 300, 'backwpup_cron', array( 'main'=> 'restart' ) );
		//create log file
		$charset= get_option('blog_charset');
		if (empty($charset))
			$charset = 'UTF-8';
		$lang = str_replace('_', '-', get_locale());
		$fd = fopen( $this->jobdata['LOGFILE'], 'w' );
		fwrite( $fd, "<!DOCTYPE html>". $this->line_separator ."<html lang=\"".$lang."\">" . $this->line_separator . "<head>" . $this->line_separator );
		fwrite( $fd, "<meta charset=\"".$charset."\" />". $this->line_separator );
		fwrite( $fd, "<title>" . sprintf( __( 'BackWPup log for %1$s from %2$s at %3$s', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'name' ), date_i18n( get_option( 'date_format' ) ), date_i18n( get_option( 'time_format' ) ) ) . "</title>". $this->line_separator);
		fwrite( $fd, "<meta name=\"robots\" content=\"noindex, nofollow\" />" . $this->line_separator );
		fwrite( $fd, "<meta name=\"backwpup_version\" content=\"" . BackWPup::get_plugin_data('Version') . "\" />" . $this->line_separator );
		fwrite( $fd, "<meta name=\"backwpup_logtime\" content=\"" . current_time( 'timestamp' ) . "\" />" . $this->line_separator );
		fwrite( $fd, str_pad( "<meta name=\"backwpup_errors\" content=\"0\" />", 100 ) . $this->line_separator );
		fwrite( $fd, str_pad( "<meta name=\"backwpup_warnings\" content=\"0\" />", 100 ) . $this->line_separator );
		fwrite( $fd, "<meta name=\"backwpup_jobid\" content=\"" . backwpup_get_option( $this->jobdata['JOBMAIN'], 'jobid' ) . "\" />" . $this->line_separator );
		fwrite( $fd, "<meta name=\"backwpup_jobname\" content=\"" . backwpup_get_option( $this->jobdata['JOBMAIN'], 'name' ) . "\" />" . $this->line_separator );
		fwrite( $fd, "<meta name=\"backwpup_jobtype\" content=\"" . implode( '+', backwpup_get_option( $this->jobdata['JOBMAIN'], 'type' ) ) . "\" />" . $this->line_separator );
		fwrite( $fd, str_pad( "<meta name=\"backwpup_backupfilesize\" content=\"0\" />", 100 ) . $this->line_separator );
		fwrite( $fd, str_pad( "<meta name=\"backwpup_jobruntime\" content=\"0\" />", 100 ) . $this->line_separator );
		fwrite( $fd, "<style type=\"text/css\">" . $this->line_separator );
		fwrite( $fd, ".warning {background-color:yellow;}" . $this->line_separator );
		fwrite( $fd, ".error {background-color:red;}" . $this->line_separator );
		fwrite( $fd, "body {font-family:Fixedsys,Courier,monospace;font-size:12px;line-height:15px;background-color:black;color:white;white-space:pre;display:block;}" . $this->line_separator );
		fwrite( $fd, "</style>" . $this->line_separator );
		fwrite( $fd, "</head>" . $this->line_separator . "<body>" );
		fwrite( $fd, sprintf( __( '[INFO] BackWPup version %1$s, WordPress version %4$s Copyright %2$s %3$s' ), BackWPup::get_plugin_data('Version'), '&copy; 2009-' . date_i18n( 'Y' ), '<a href="http://danielhuesken.de" target="_blank">Daniel H&uuml;sken</a>', $wp_version )  . $this->line_separator );
		fwrite( $fd, __( '[INFO] This program comes with ABSOLUTELY NO WARRANTY. This is free software, and you are welcome to redistribute it under certain conditions.', 'backwpup' )  . $this->line_separator );
		fwrite( $fd, __( '[INFO] BackWPup job:', 'backwpup' ) . ' ' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'jobid' ) . '. ' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'name' ) . '; ' . implode( '+', backwpup_get_option( $this->jobdata['JOBMAIN'], 'type' ) )  . $this->line_separator );
		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'activetype' ) != '' )
			fwrite( $fd, __( '[INFO] BackWPup cron:', 'backwpup' ) . ' ' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'cron' ) . '; ' . date_i18n( 'D, j M Y @ H:i', backwpup_get_option( $this->jobdata['JOBMAIN'], 'cronnextrun' ) )  . $this->line_separator );
		if ( $this->jobstarttype == 'cronrun' )
			fwrite( $fd, __( '[INFO] BackWPup job started from wp-cron', 'backwpup' )  . $this->line_separator );
		elseif ( $this->jobstarttype == 'runnow' or $this->jobstarttype == 'runnowalt' )
			fwrite( $fd, __( '[INFO] BackWPup job started manually', 'backwpup' )  . $this->line_separator );
		elseif ( $this->jobstarttype == 'runext' )
			fwrite( $fd, __( '[INFO] BackWPup job started from external url', 'backwpup' )  . $this->line_separator );
		elseif ( $this->jobstarttype == 'apirun' )
			fwrite( $fd, __( '[INFO] BackWPup job started by its API', 'backwpup' )  . $this->line_separator );
		elseif ( $this->jobstarttype == 'runcmd' )
			fwrite( $fd, __( '[INFO] BackWPup job started form commandline', 'backwpup' )  . $this->line_separator );
		fwrite( $fd, __( '[INFO] PHP ver.:', 'backwpup' ) . ' ' . phpversion() . '; ' . php_sapi_name() . '; ' . PHP_OS  . $this->line_separator );
		if ( (bool) ini_get( 'safe_mode' ) )
			fwrite( $fd, sprintf( __( '[INFO] PHP Safe mode is ON! Maximum script execution time is %1$d sec.', 'backwpup' ), ini_get( 'max_execution_time' ) )  . $this->line_separator );
		fwrite( $fd, sprintf( __( '[INFO] MySQL ver.: %s', 'backwpup' ), $wpdb->get_var( "SELECT VERSION() AS version" ) )  . $this->line_separator );
		if ( function_exists( 'curl_init' ) ) {
			$curlversion = curl_version();
			fwrite( $fd, sprintf( __( '[INFO] curl ver.: %1$s; %2$s', 'backwpup' ), $curlversion['version'], $curlversion['ssl_version'] )  . $this->line_separator );
		}
		fwrite( $fd, sprintf( __( '[INFO] Temp folder is: %s', 'backwpup' ), backwpup_get_option( 'cfg', 'tempfolder' ) )  . $this->line_separator );
		fwrite( $fd, sprintf( __( '[INFO] Logfile folder is: %s', 'backwpup' ), backwpup_get_option( 'cfg', 'logfolder' ) ) . $this->line_separator );
		fwrite( $fd, sprintf( __( '[INFO] Backup type is: %s', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'backuptype' ) ) . $this->line_separator );
		if ( ! empty($this->jobdata['BACKUPFILE']) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'backuptype' ) == 'archive' )
			fwrite( $fd, sprintf( __( '[INFO] Backup file is: %s', 'backwpup' ), $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'] )  . $this->line_separator );
		fclose( $fd );
		//test for destinations
		if ( in_array( 'DB', backwpup_get_option( $this->jobdata['JOBMAIN'], 'type' ) ) or in_array( 'WPEXP', backwpup_get_option( $this->jobdata['JOBMAIN'], 'type' ) ) or in_array( 'FILE', backwpup_get_option( $this->jobdata['JOBMAIN'], 'type' ) ) ) {
			$desttest = false;
			foreach ( $this->jobdata['STEPS'] as $deststeptest ) {
				if ( substr( $deststeptest, 0, 5 ) == 'DEST_' ) {
					$desttest = true;
					break;
				}
			}
			if ( ! $desttest )
				$this->error_handler( E_USER_ERROR, __( 'No destination defined for backup!!! Please correct job settings', 'backwpup' ), __FILE__, __LINE__ );
		}
	}

	/**
	 *
	 * Check is folder readable and exists create it if not
	 * add .htaccess or index.html file in folder to prevent directory listing
	 *
	 * @param string $folder the folder to check
	 *
	 * @return bool ok or not
	 */
	protected function check_folder( $folder ) {
		$folder = untrailingslashit( $folder );
		//check that is not home of WP
		if ( is_file( $folder . '/wp-load.php' ) )
			return false;
		//create backup dir if it not exists
		if ( ! is_dir( $folder ) ) {
			if ( ! wp_mkdir_p( $folder ) ) {
				trigger_error( sprintf( __( 'Can not create folder: %1$s', 'backwpup' ), $folder ), E_USER_ERROR );
				return false;
			}
			//create .htaccess for apache and index.html/php for other
			if ( strtolower( substr( $_SERVER["SERVER_SOFTWARE"], 0, 6 ) ) == "apache" ) { //check for apache webserver
				if ( ! is_file( $folder . '/.htaccess' ) )
					file_put_contents( $folder . '/.htaccess', "Order allow,deny" . $this->line_separator . "deny from all" );
			} else {
				if ( ! is_file( $folder . '/index.html' ) )
					file_put_contents( $folder . '/index.html', $this->line_separator );
				if ( ! is_file( $folder . '/index.php' ) )
					file_put_contents( $folder . '/index.php', $this->line_separator );
			}
		}
		//check backup dir
		if ( ! is_writable( $folder ) ) {
			trigger_error( sprintf( __( 'Not writable folder: %1$s', 'backwpup' ), $folder ), E_USER_ERROR );
			return false;
		}
		return true;
	}

	/**
	 *
	 * The uncouth exception handler
	 *
	 * @param object $exception
	 */
	public function exception_handler( $exception ) {
		$this->error_handler( E_USER_ERROR, sprintf( __( 'Exception caught in %1$s: %2$s', 'backwpup' ), get_class( $exception ), htmlentities( $exception->getMessage() ) ), $exception->getFile(), $exception->getLine() );
	}

	/**
	 *
	 * The error handler to write massegase to log
	 *
	 * @param int	the error number (E_USER_ERROR,E_USER_WARNING,E_USER_NOTICE, ...)
	 * @param string the error massege
	 * @param string the full path of file with error (__FILE__)
	 * @param int	the line in that is the error (__LINE__)
	 *
	 * @return bool true
	 */
	public function error_handler() {
		$args = func_get_args();
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
				$this->jobdata['WARNING'] ++;
				$adderrorwarning = true;
				$messagetype     = "<span class=\"warning\">" . __( 'WARNING:', 'backwpup' ). " ";
				break;
			case E_ERROR:
			case E_PARSE:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
				$this->jobdata['ERROR'] ++;
				$adderrorwarning = true;
				$messagetype     = "<span class=\"error\">" . __( 'ERROR:', 'backwpup' ). " ";
				break;
			case E_DEPRECATED:
			case E_USER_DEPRECATED:
				$messagetype = "<span>" . __( 'DEPRECATED:', 'backwpup' ). " ";
				break;
			case E_STRICT:
				$messagetype = "<span>" . __( 'STRICT NOTICE:', 'backwpup' ). " ";
				break;
			case E_RECOVERABLE_ERROR:
				$messagetype = "<span>" . __( 'RECOVERABLE ERROR:', 'backwpup' ). " ";
				break;
			default:
				$messagetype = "<span>" . $args[0] . ": ";
				break;
		}

		//log line
		$timestamp = "<span title=\"[Type: " . $args[0] . "|Line: " . $args[3] . "|File: " . basename( $args[2] ) . "|Mem: " . size_format( @memory_get_usage( true ), 2 ) . "|Mem Max: " . size_format( @memory_get_peak_usage( true ), 2 ) . "|Mem Limit: " . ini_get( 'memory_limit' ) . "|PID: " . getmypid() . "|Query's: " . get_num_queries() . "]\">[" . date_i18n( 'd-M-Y H:i:s' ) . "]</span> ";
		//write log file
		file_put_contents( $this->jobdata['LOGFILE'], $timestamp . $messagetype  . $args[1] . "</span>" . $this->line_separator, FILE_APPEND );

		//write new log header
		if ( $adderrorwarning ) {
			$found   = 0;
			$fd      = fopen( $this->jobdata['LOGFILE'], 'r+' );
			$filepos = ftell( $fd );
			while ( ! feof( $fd ) ) {
				$line = fgets( $fd );
				if ( stripos( $line, "<meta name=\"backwpup_errors\" content=\"" ) !== false ) {
					fseek( $fd, $filepos );
					fwrite( $fd, str_pad( "<meta name=\"backwpup_errors\" content=\"" . $this->jobdata['ERROR'] . "\" />", 100 ) . $this->line_separator );
					$found ++;
				}
				if ( stripos( $line, "<meta name=\"backwpup_warnings\" content=\"" ) !== false ) {
					fseek( $fd, $filepos );
					fwrite( $fd, str_pad( "<meta name=\"backwpup_warnings\" content=\"" . $this->jobdata['WARNING'] . "\" />", 100 ) . $this->line_separator );
					$found ++;
				}
				if ( $found >= 2 )
					break;
				$filepos = ftell( $fd );
			}
			fclose( $fd );
		}

		//write working data
		$this->update_working_data( $adderrorwarning );

		//Die on fatal php errors.
		if ( ($args[0] == E_ERROR || $args[0] == E_CORE_ERROR || $args[0] == E_COMPILE_ERROR) && $args[4] != false )
			die();

		//true for no more php error handling.
		return true;
	}

	/**
	 *
	 * Write the Working data to display the process or that i can executes again
	 *
	 * @param bool $mustwrite overwrite the only ever 1 sec writing
	 *
	 * @return bool true if working date written
	 */
	protected function update_working_data( $mustwrite = false ) {
		global $wpdb;
		//only run every 1 sec.
		$timetoupdate = microtime( true ) - $this->jobdata['TIMESTAMP'];
		if ( ! $mustwrite && $timetoupdate < 1 )
			return true;
		//check MySQL connection
		if ( ! mysql_ping( $wpdb->dbh ) ) {
			trigger_error( __( 'Database connection is gone create a new one.', 'backwpup' ), E_USER_NOTICE );
			$wpdb->db_connect();
		}
		//check if job already aborted
		if ( ! backwpup_get_workingdata( false ) ) {
			$this->end();
			return false;
		}
		//Update % data
		if ( $this->jobdata['STEPTODO'] > 0 && $this->jobdata['STEPDONE'] > 0 )
			$this->jobdata['STEPPERSENT'] = round( $this->jobdata['STEPDONE'] / $this->jobdata['STEPTODO'] * 100 );
		else
			$this->jobdata['STEPPERSENT'] = 1;
		if ( count( $this->jobdata['STEPSDONE'] ) > 0 )
			$this->jobdata['STEPSPERSENT'] = round( count( $this->jobdata['STEPSDONE'] ) / count( $this->jobdata['STEPS'] ) * 100 );
		else
			$this->jobdata['STEPSPERSENT'] = 1;
		$this->jobdata['TIMESTAMP'] = microtime( true );
		if ( backwpup_get_option( 'cfg', 'storeworkingdatain' ) == 'db' )
			backwpup_update_option( 'working', 'data', $this->jobdata );
		if ( backwpup_get_option( 'cfg', 'storeworkingdatain' ) == 'file' )
			file_put_contents( backwpup_get_option( 'cfg', 'tempfolder' ) . '.backwpup_working_' . substr( md5( ABSPATH ), 16 ), maybe_serialize( $this->jobdata ) );
		if ( defined( 'STDIN' ) ) {//make dots on cli mode
			echo ".";
		}
		return true;
	}

	/**
	 *
	 * Called on job stop makes cleanup and terminates the script
	 *
	 */
	private function end() {
		//check if end() in progress
		if ( ! $this->jobdata['ENDINPROGRESS'] )
			$this->jobdata['ENDINPROGRESS'] = true;
		else
			return;

		$this->jobdata['STEPTODO'] = 1;
		//Back from maintenance if not
		if ( is_file( ABSPATH . '.maintenance' ) || (defined( 'FB_WM_TEXTDOMAIN' ) && (get_site_option( FB_WM_TEXTDOMAIN . '-msqld' ) == 1 || get_option( FB_WM_TEXTDOMAIN . '-msqld' ) == 1)) )
			$this->update_working_data( false );
		//delete old logs
		if ( backwpup_get_option( 'cfg', 'maxlogs' ) ) {
			if ( $dir = opendir( backwpup_get_option( 'cfg', 'logfolder' ) ) ) { //make file list
				while ( ($file = readdir( $dir )) !== false ) {
					if ( 'backwpup_log_' == substr( $file, 0, strlen( 'backwpup_log_' ) ) && (".html" == substr( $file, - 5 ) || ".html.gz" == substr( $file, - 8 )) )
						$logfilelist[] = $file;
				}
				closedir( $dir );
			}
			if ( sizeof( $logfilelist ) > 0 ) {
				rsort( $logfilelist );
				$numdeltefiles = 0;
				for ( $i = backwpup_get_option( 'cfg', 'maxlogs' ); $i < sizeof( $logfilelist ); $i ++ ) {
					unlink( backwpup_get_option( 'cfg', 'logfolder' ) . $logfilelist[$i] );
					$numdeltefiles ++;
				}
				if ( $numdeltefiles > 0 )
					trigger_error( sprintf( _n( 'One old log deleted', '%d old logs deleted', $numdeltefiles, 'backwpup' ), $numdeltefiles ), E_USER_NOTICE );
			}
		}

		//Display job working time
		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'starttime' ) )
			trigger_error( sprintf( __( 'Job done in %s sec.', 'backwpup' ), current_time( 'timestamp' ) - backwpup_get_option( $this->jobdata['JOBMAIN'], 'starttime' ), E_USER_NOTICE ) );


		if ( empty($this->jobdata['BACKUPFILE']) || ! is_file( $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'] ) || ! ($filesize = filesize( $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'] )) ) //Set the filesize correctly
			$filesize = 0;

		//clean up temp
		if ( ! empty($this->jobdata['BACKUPFILE']) && is_file( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['BACKUPFILE'] ) )
			unlink( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['BACKUPFILE'] );
		if ( ! empty($this->jobdata['DBDUMPFILE']) && is_file( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['DBDUMPFILE'] ) )
			unlink( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['DBDUMPFILE'] );
		if ( ! empty($this->jobdata['WPEXPORTFILE']) && is_file( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['WPEXPORTFILE'] ) )
			unlink( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['WPEXPORTFILE'] );
		if ( ! empty($this->jobdata['PLUGINLISTFILE']) && is_file( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['PLUGINLISTFILE'] ) )
			unlink( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['PLUGINLISTFILE'] );

		//Update job options
		$starttime = backwpup_get_option( $this->jobdata['JOBMAIN'], 'starttime' );
		backwpup_update_option( $this->jobdata['JOBMAIN'], 'lastrun', $starttime );
		backwpup_update_option( $this->jobdata['JOBMAIN'], 'lastruntime', current_time( 'timestamp' ) - $starttime );
		backwpup_update_option( $this->jobdata['JOBMAIN'], 'starttime', '' );

		//write header info
		if ( is_writable( $this->jobdata['LOGFILE'] ) ) {
			$fd      = fopen( $this->jobdata['LOGFILE'], 'r+' );
			$filepos = ftell( $fd );
			$found   = 0;
			while ( ! feof( $fd ) ) {
				$line = fgets( $fd );
				if ( stripos( $line, "<meta name=\"backwpup_jobruntime\"" ) !== false ) {
					fseek( $fd, $filepos );
					fwrite( $fd, str_pad( "<meta name=\"backwpup_jobruntime\" content=\"" . backwpup_get_option( $this->jobdata['JOBMAIN'], 'lastruntime' ) . "\" />", 100 ) . $this->line_separator );
					$found ++;
				}
				if ( stripos( $line, "<meta name=\"backwpup_backupfilesize\"" ) !== false ) {
					fseek( $fd, $filepos );
					fwrite( $fd, str_pad( "<meta name=\"backwpup_backupfilesize\" content=\"" . $filesize . "\" />", 100 ) . $this->line_separator );
					$found ++;
				}
				if ( $found >= 2 )
					break;
				$filepos = ftell( $fd );
			}
			fclose( $fd );
		}
		//Restore error handler
		restore_exception_handler();
		restore_error_handler();
		@ini_set( 'log_errors', $this->jobdata['PHP']['INI']['LOG_ERRORS'] );
		@ini_set( 'error_log', $this->jobdata['PHP']['INI']['ERROR_LOG'] );
		@ini_set( 'display_errors', $this->jobdata['PHP']['INI']['DISPLAY_ERRORS'] );
		//logfile end
		file_put_contents( $this->jobdata['LOGFILE'], "</body>" . $this->line_separator . "</html>", FILE_APPEND );

		//Send mail with log
		$sendmail = false;
		if ( $this->jobdata['ERROR'] > 0 && backwpup_get_option( $this->jobdata['JOBMAIN'], 'mailerroronly' ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'mailaddresslog' ) )
			$sendmail = true;
		if ( ! backwpup_get_option( $this->jobdata['JOBMAIN'], 'mailerroronly' ) && backwpup_get_option( $this->jobdata['JOBMAIN'], 'mailaddresslog' ) )
			$sendmail = true;
		if ( $sendmail ) {
			$message = file_get_contents( $this->jobdata['LOGFILE'] );
			if ( ! backwpup_get_option( 'cfg', 'mailsndname' ) )
				$headers = 'From: ' . backwpup_get_option( 'cfg', 'mailsndname' ) . ' <' . backwpup_get_option( 'cfg', 'mailsndemail' ) . '>' . "\r\n";
			else
				$headers = 'From: ' . backwpup_get_option( 'cfg', 'mailsndemail' ) . "\r\n";
			//special subject
			$status = 'Successful';
			if ( $this->jobdata['WARNING'] > 0 )
				$status = 'Warning';
			if ( $this->jobdata['ERROR'] > 0 )
				$status = 'Error';
			add_filter( 'wp_mail_content_type', create_function( '', 'return "text/html"; ' ) );
			wp_mail( backwpup_get_option( $this->jobdata['JOBMAIN'], 'mailaddresslog' ),
				sprintf( __( '[%3$s] BackWPup log %1$s: %2$s', 'backwpup' ), date_i18n( 'd-M-Y H:i', backwpup_get_option( $this->jobdata['JOBMAIN'], 'lastrun' ) ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'name' ), $status ),
				$message, $headers );
		}

		//gzip logfile
		if ( backwpup_get_option( 'cfg', 'gzlogs' ) && is_writable( $this->jobdata['LOGFILE'] ) ) {
			$fd = fopen( $this->jobdata['LOGFILE'], 'r' );
			$zd = gzopen( $this->jobdata['LOGFILE'] . '.gz', 'w9' );
			while ( ! feof( $fd ) )
			{
				gzwrite( $zd, fread( $fd, 4096 ) );
			}
			gzclose( $zd );
			fclose( $fd );
			unlink( $this->jobdata['LOGFILE'] );
			$this->jobdata['LOGFILE'] = $this->jobdata['LOGFILE'] . '.gz';
			backwpup_update_option( $this->jobdata['JOBMAIN'], 'logfile', $this->jobdata['LOGFILE'] );
		}
		//remove restart cron
		wp_clear_scheduled_hook( 'backwpup_cron', array( 'main'=> 'restart' ) );

		$this->jobdata['STEPDONE']    = 1;
		$this->jobdata['STEPSDONE'][] = 'END'; //set done
		backwpup_delete_option( 'working', 'data' ); //delete working data
		if ( is_file( backwpup_get_option( 'cfg', 'tempfolder' ) . '.backwpup_working_' . substr( md5( ABSPATH ), 16 ) ) )
			unlink( backwpup_get_option( 'cfg', 'tempfolder' ) . '.backwpup_working_' . substr( md5( ABSPATH ), 16 ) );
		if ( defined( 'STDIN' ) )
			_e( 'Done!', 'backwpup' );
		exit;
	}

	/**
	 *
	 * Ste blog to maintenance mode
	 *
	 * @param bool $enable set to true to enable maintenance
	 */
	protected function maintenance_mode( $enable = false ) {
		if ( ! backwpup_get_option( $this->jobdata['JOBMAIN'], 'maintenance' ) )
			return;
		if ( $enable ) {
			trigger_error( __( 'Set Blog to maintenance mode', 'backwpup' ), E_USER_NOTICE );
			if ( class_exists( 'WPMaintenanceMode', false ) ) { //Support for WP Maintenance Mode Plugin (Frank Bueltge)
				if ( is_multisite() && is_plugin_active_for_network( FB_WM_BASENAME ) )
					set_site_option( FB_WM_TEXTDOMAIN . '-msqld', 1 );
				else
					update_option( FB_WM_TEXTDOMAIN . '-msqld', 1 );
			} else { //WP Support
				if ( is_writable( ABSPATH . '.maintenance' ) )
					file_put_contents( ABSPATH . '.maintenance', '<?php $upgrading = ' . time() . '; ?>' );
				else
					trigger_error( __( 'Cannot set Blog to maintenance mode! Root folder is not writable!', 'backwpup' ), E_USER_NOTICE );
			}
		} else {
			trigger_error( __( 'Set Blog to normal mode', 'backwpup' ), E_USER_NOTICE );
			if ( class_exists( 'WPMaintenanceMode', false ) ) { //Support for WP Maintenance Mode Plugin (Frank Bueltge)
				if ( is_multisite() && is_plugin_active_for_network( FB_WM_BASENAME ) )
					set_site_option( FB_WM_TEXTDOMAIN . '-msqld', 0 );
				else
					update_option( FB_WM_TEXTDOMAIN . '-msqld', 0 );
			} else { //WP Support
				@unlink( ABSPATH . '.maintenance' );
			}
		}
	}

	/**
	 *
	 * convertes a string withe M,G,K in bytes
	 *
	 * @param string $value string of size
	 *
	 * @return int bytes
	 */
	protected function job_in_bytes( $value ) {
		$multi = strtoupper( substr( trim( $value ), - 1 ) );
		$bytes = abs( intval( trim( $value ) ) );
		if ( $multi == 'G' )
			$bytes = $bytes * 1024 * 1024 * 1024;
		if ( $multi == 'M' )
			$bytes = $bytes * 1024 * 1024;
		if ( $multi == 'K' )
			$bytes = $bytes * 1024;
		return $bytes;
	}

	/**
	 *
	 * Increase automatically the memory that is needed
	 *
	 * @param int|string $memneed of the needed memory
	 */
	protected function need_free_memory( $memneed ) {
		if ( ! function_exists( 'memory_get_usage' ) )
			return;
		//need memory
		$needmemory = @memory_get_usage( true ) + $this->job_in_bytes( $memneed );
		// increase Memory
		if ( $needmemory > $this->job_in_bytes( ini_get( 'memory_limit' ) ) ) {
			$newmemory = round( $needmemory / 1024 / 1024 ) + 1 . 'M';
			if ( $needmemory >= 1073741824 )
				$newmemory = round( $needmemory / 1024 / 1024 / 1024 ) . 'G';
			if ( $oldmem = @ini_set( 'memory_limit', $newmemory ) )
				trigger_error( sprintf( __( 'Memory increased from %1$s to %2$s', 'backwpup' ), $oldmem, @ini_get( 'memory_limit' ) ), E_USER_NOTICE );
			else
				trigger_error( sprintf( __( 'Can not increase memory limit is %1$s', 'backwpup' ), @ini_get( 'memory_limit' ) ), E_USER_WARNING );
		}
	}

	/**
	 *
	 * Callback for the CURLOPT_READFUNCTION that submit the transferred bytes
	 * to build the process bar
	 *
	 * @param int $bytes_transferred
	 */
	public function curl_read_callback( $bytes_transferred ) {
		if ( $this->jobdata['STEPTODO'] > 10 && backwpup_get_option( $this->jobdata['JOBMAIN'], 'backuptype' ) != 'sync' )
			$this->jobdata['STEPDONE'] = $this->jobdata['STEPDONE'] + $bytes_transferred;
		$this->update_working_data();
		return;
	}

	/**
	 *
	 * Curl callback for AWS SDK to build the process bar
	 *
	 * @param $curl_handle
	 * @param $file_handle
	 * @param $out
	 */
	public function curl_aws_read_callback( $curl_handle, $file_handle, $out ) {
		$this->curl_read_callback( strlen( $out ) );
		return;
	}

	/**
	 *
	 * Get the mime type of a file
	 *
	 * @param string $file The full file name
	 *
	 * @return bool|string the mime type or false
	 */
	protected function get_mime_type( $file ) {
		if ( ! is_file( $file ) )
			return false;

		if ( function_exists( 'fileinfo' ) ) {
			$finfo = finfo_open( FILEINFO_MIME );
			return finfo_file( $finfo, $file );
		}

		if ( function_exists( 'mime_content_type' ) ) {
			return mime_content_type( $file );
		}

		$mime_types = array(
			'3gp'	 => 'video/3gpp',
			'ai'	  => 'application/postscript',
			'aif'	 => 'audio/x-aiff',
			'aifc'	=> 'audio/x-aiff',
			'aiff'	=> 'audio/x-aiff',
			'asc'	 => 'text/plain',
			'atom'	=> 'application/atom+xml',
			'au'	  => 'audio/basic',
			'avi'	 => 'video/x-msvideo',
			'bcpio'   => 'application/x-bcpio',
			'bin'	 => 'application/octet-stream',
			'bmp'	 => 'image/bmp',
			'cdf'	 => 'application/x-netcdf',
			'cgm'	 => 'image/cgm',
			'class'   => 'application/octet-stream',
			'cpio'	=> 'application/x-cpio',
			'cpt'	 => 'application/mac-compactpro',
			'csh'	 => 'application/x-csh',
			'css'	 => 'text/css',
			'dcr'	 => 'application/x-director',
			'dif'	 => 'video/x-dv',
			'dir'	 => 'application/x-director',
			'djv'	 => 'image/vnd.djvu',
			'djvu'	=> 'image/vnd.djvu',
			'dll'	 => 'application/octet-stream',
			'dmg'	 => 'application/octet-stream',
			'dms'	 => 'application/octet-stream',
			'doc'	 => 'application/msword',
			'dtd'	 => 'application/xml-dtd',
			'dv'	  => 'video/x-dv',
			'dvi'	 => 'application/x-dvi',
			'dxr'	 => 'application/x-director',
			'eps'	 => 'application/postscript',
			'etx'	 => 'text/x-setext',
			'exe'	 => 'application/octet-stream',
			'ez'	  => 'application/andrew-inset',
			'flv'	 => 'video/x-flv',
			'gif'	 => 'image/gif',
			'gram'	=> 'application/srgs',
			'grxml'   => 'application/srgs+xml',
			'gtar'	=> 'application/x-gtar',
			'gz'	  => 'application/x-gzip',
			'hdf'	 => 'application/x-hdf',
			'hqx'	 => 'application/mac-binhex40',
			'htm'	 => 'text/html',
			'html'	=> 'text/html',
			'ice'	 => 'x-conference/x-cooltalk',
			'ico'	 => 'image/x-icon',
			'ics'	 => 'text/calendar',
			'ief'	 => 'image/ief',
			'ifb'	 => 'text/calendar',
			'iges'	=> 'model/iges',
			'igs'	 => 'model/iges',
			'jnlp'	=> 'application/x-java-jnlp-file',
			'jp2'	 => 'image/jp2',
			'jpe'	 => 'image/jpeg',
			'jpeg'	=> 'image/jpeg',
			'jpg'	 => 'image/jpeg',
			'js'	  => 'application/x-javascript',
			'kar'	 => 'audio/midi',
			'latex'   => 'application/x-latex',
			'lha'	 => 'application/octet-stream',
			'lzh'	 => 'application/octet-stream',
			'm3u'	 => 'audio/x-mpegurl',
			'm4a'	 => 'audio/mp4a-latm',
			'm4p'	 => 'audio/mp4a-latm',
			'm4u'	 => 'video/vnd.mpegurl',
			'm4v'	 => 'video/x-m4v',
			'mac'	 => 'image/x-macpaint',
			'man'	 => 'application/x-troff-man',
			'mathml'  => 'application/mathml+xml',
			'me'	  => 'application/x-troff-me',
			'mesh'	=> 'model/mesh',
			'mid'	 => 'audio/midi',
			'midi'	=> 'audio/midi',
			'mif'	 => 'application/vnd.mif',
			'mov'	 => 'video/quicktime',
			'movie'   => 'video/x-sgi-movie',
			'mp2'	 => 'audio/mpeg',
			'mp3'	 => 'audio/mpeg',
			'mp4'	 => 'video/mp4',
			'mpe'	 => 'video/mpeg',
			'mpeg'	=> 'video/mpeg',
			'mpg'	 => 'video/mpeg',
			'mpga'	=> 'audio/mpeg',
			'ms'	  => 'application/x-troff-ms',
			'msh'	 => 'model/mesh',
			'mxu'	 => 'video/vnd.mpegurl',
			'nc'	  => 'application/x-netcdf',
			'oda'	 => 'application/oda',
			'ogg'	 => 'application/ogg',
			'ogv'	 => 'video/ogv',
			'pbm'	 => 'image/x-portable-bitmap',
			'pct'	 => 'image/pict',
			'pdb'	 => 'chemical/x-pdb',
			'pdf'	 => 'application/pdf',
			'pgm'	 => 'image/x-portable-graymap',
			'pgn'	 => 'application/x-chess-pgn',
			'pic'	 => 'image/pict',
			'pict'	=> 'image/pict',
			'png'	 => 'image/png',
			'pnm'	 => 'image/x-portable-anymap',
			'pnt'	 => 'image/x-macpaint',
			'pntg'	=> 'image/x-macpaint',
			'ppm'	 => 'image/x-portable-pixmap',
			'ppt'	 => 'application/vnd.ms-powerpoint',
			'ps'	  => 'application/postscript',
			'qt'	  => 'video/quicktime',
			'qti'	 => 'image/x-quicktime',
			'qtif'	=> 'image/x-quicktime',
			'ra'	  => 'audio/x-pn-realaudio',
			'ram'	 => 'audio/x-pn-realaudio',
			'ras'	 => 'image/x-cmu-raster',
			'rdf'	 => 'application/rdf+xml',
			'rgb'	 => 'image/x-rgb',
			'rm'	  => 'application/vnd.rn-realmedia',
			'roff'	=> 'application/x-troff',
			'rtf'	 => 'text/rtf',
			'rtx'	 => 'text/richtext',
			'sgm'	 => 'text/sgml',
			'sgml'	=> 'text/sgml',
			'sh'	  => 'application/x-sh',
			'shar'	=> 'application/x-shar',
			'silo'	=> 'model/mesh',
			'sit'	 => 'application/x-stuffit',
			'skd'	 => 'application/x-koan',
			'skm'	 => 'application/x-koan',
			'skp'	 => 'application/x-koan',
			'skt'	 => 'application/x-koan',
			'smi'	 => 'application/smil',
			'smil'	=> 'application/smil',
			'snd'	 => 'audio/basic',
			'so'	  => 'application/octet-stream',
			'spl'	 => 'application/x-futuresplash',
			'src'	 => 'application/x-wais-source',
			'sv4cpio' => 'application/x-sv4cpio',
			'sv4crc'  => 'application/x-sv4crc',
			'svg'	 => 'image/svg+xml',
			'swf'	 => 'application/x-shockwave-flash',
			't'	   => 'application/x-troff',
			'tar'	 => 'application/x-tar',
			'tcl'	 => 'application/x-tcl',
			'tex'	 => 'application/x-tex',
			'texi'	=> 'application/x-texinfo',
			'texinfo' => 'application/x-texinfo',
			'tif'	 => 'image/tiff',
			'tiff'	=> 'image/tiff',
			'tr'	  => 'application/x-troff',
			'tsv'	 => 'text/tab-separated-values',
			'txt'	 => 'text/plain',
			'ustar'   => 'application/x-ustar',
			'vcd'	 => 'application/x-cdlink',
			'vrml'	=> 'model/vrml',
			'vxml'	=> 'application/voicexml+xml',
			'wav'	 => 'audio/x-wav',
			'wbmp'	=> 'image/vnd.wap.wbmp',
			'wbxml'   => 'application/vnd.wap.wbxml',
			'webm'	=> 'video/webm',
			'wml'	 => 'text/vnd.wap.wml',
			'wmlc'	=> 'application/vnd.wap.wmlc',
			'wmls'	=> 'text/vnd.wap.wmlscript',
			'wmlsc'   => 'application/vnd.wap.wmlscriptc',
			'wmv'	 => 'video/x-ms-wmv',
			'wrl'	 => 'model/vrml',
			'xbm'	 => 'image/x-xbitmap',
			'xht'	 => 'application/xhtml+xml',
			'xhtml'   => 'application/xhtml+xml',
			'xls'	 => 'application/vnd.ms-excel',
			'xml'	 => 'application/xml',
			'xpm'	 => 'image/x-xpixmap',
			'xsl'	 => 'application/xml',
			'xslt'	=> 'application/xslt+xml',
			'xul'	 => 'application/vnd.mozilla.xul+xml',
			'xwd'	 => 'image/x-xwindowdump',
			'xyz'	 => 'chemical/x-xyz',
			'zip'	 => 'application/zip'
		);
		preg_match( "|\.([a-z0-9]{2,4})$|i", $file, $filesuffix );
		if ( ! empty($filesuffix[1]) ) {
			$suffix = strtolower( $filesuffix[1] );
			if ( isset($mime_types[$suffix]) )
				return $mime_types[$suffix];
		}
		return 'application/octet-stream';
	}

	/**
	 * Dumps the Database
	 * @return nothing
	 */
	private function db_dump() {
		global $wpdb,$wp_version;

		trigger_error( sprintf( __( '%d. Try for database dump...', 'backwpup' ), $this->jobdata['DB_DUMP']['STEP_TRY'] ), E_USER_NOTICE );

		if ( ! isset($this->jobdata['DB_DUMP']['TABLES']) || ! is_array( $this->jobdata['DB_DUMP']['TABLES'] ) )
			$this->jobdata['DB_DUMP']['TABLES'] = array();

		//build filename
		if ( empty($this->jobdata['DBDUMPFILE']) ) {
			$datevars                    = array( '%d', '%D', '%l', '%N', '%S', '%w', '%z', '%W', '%F', '%m', '%M', '%n', '%t', '%L', '%o', '%Y', '%a', '%A', '%B', '%g', '%G', '%h', '%H', '%i', '%s', '%u', '%e', '%I', '%O', '%P', '%T', '%Z', '%c', '%U' );
			$datevalues                  = array( date_i18n( 'd' ), date_i18n( 'D' ), date_i18n( 'l' ), date_i18n( 'N' ), date_i18n( 'S' ), date_i18n( 'w' ), date_i18n( 'z' ), date_i18n( 'W' ), date_i18n( 'F' ), date_i18n( 'm' ), date_i18n( 'M' ), date_i18n( 'n' ), date_i18n( 't' ), date_i18n( 'L' ), date_i18n( 'o' ), date_i18n( 'Y' ), date_i18n( 'a' ), date_i18n( 'A' ), date_i18n( 'B' ), date_i18n( 'g' ), date_i18n( 'G' ), date_i18n( 'h' ), date_i18n( 'H' ), date_i18n( 'i' ), date_i18n( 's' ), date_i18n( 'u' ), date_i18n( 'e' ), date_i18n( 'I' ), date_i18n( 'O' ), date_i18n( 'P' ), date_i18n( 'T' ), date_i18n( 'Z' ), date_i18n( 'c' ), date_i18n( 'U' ) );
			$this->jobdata['DBDUMPFILE'] = str_replace( $datevars, $datevalues, backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbdumpfile' ) );
			//check compression
			if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbdumpfilecompression' ) == 'gz' && ! function_exists( 'gzopen' ) )
				backwpup_update_option( $this->jobdata['JOBMAIN'], 'dbdumpfilecompression', '' );
			if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbdumpfilecompression' ) == 'bz2' && ! function_exists( 'bzopen' ) )
				backwpup_update_option( $this->jobdata['JOBMAIN'], 'dbdumpfilecompression', '' );
			//add file ending
			$this->jobdata['DBDUMPFILE'] .= '.sql';
			if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbdumpfilecompression' ) == 'gz' || backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbdumpfilecompression' ) == 'bz2' )
				$this->jobdata['DBDUMPFILE'] .= '.' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbdumpfilecompression' );
		}

		//Set maintenance
		$this->maintenance_mode( true );
		//make a new DB connection
		$backwpupsql=new wpdb(backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbuser' ),backwpup_decrypt(backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbpassword' )),backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbname' ),backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbhost' ));
		if (!$backwpupsql->dbh)  {
			trigger_error( sprintf( __( 'Can not connect to database %1$s', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbname' ) ), E_USER_ERROR );
		}
		$backwpupsql->set_charset($backwpupsql->dbh,backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbcharset' ),backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbcollation' ));
		trigger_error( sprintf( __( 'Connected to database %1$s', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbname' ) ), E_USER_NOTICE );

		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbdumpfilecompression' ) == 'gz' )
			$file = gzopen( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['DBDUMPFILE'], 'wb9' );
		elseif ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbdumpfilecompression' ) == 'bz2' )
			$file = bzopen( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['DBDUMPFILE'], 'w' );
		else
			$file = fopen( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['DBDUMPFILE'], 'wb' );

		if ( ! $file ) {
			trigger_error( sprintf( __( 'Can not create database dump file! "%s"', 'backwpup' ), $this->jobdata['DBDUMPFILE'] ), E_USER_ERROR );
			$this->jobdata['STEPSDONE'][] = 'DB_DUMP'; //set done
			$this->maintenance_mode( false );
			return false;
		}


		if ( $this->jobdata['STEPDONE'] == 0 ) {
			//get tables to backup
			$tables = $backwpupsql->get_col( "SHOW TABLES FROM `" . backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbname' ) . "`" ); //get table status
			if ( mysql_error($backwpupsql->dbh) )
				trigger_error( sprintf( __( 'Database error %1$s for query %2$s', 'backwpup' ), mysql_error($backwpupsql->dbh), $backwpupsql->last_query ), E_USER_ERROR );
			foreach ( $tables as $table ) {
				if ( ! in_array( $table, backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbexclude' ) ) )
					$this->jobdata['DB_DUMP']['TABLES'][] = $table;
			}
			$this->jobdata['STEPTODO'] = count( $this->jobdata['DB_DUMP']['TABLES'] );

			//Get table status
			$tablesstatus = $backwpupsql->get_results( "SHOW TABLE STATUS FROM `" . backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbname' ) . "`", ARRAY_A ); //get table status
			if ( mysql_error($backwpupsql->dbh) )
				trigger_error( sprintf( __( 'Database error %1$s for query %2$s', 'backwpup' ), mysql_error($backwpupsql->dbh), $backwpupsql->last_query ), E_USER_ERROR );
			foreach ( $tablesstatus as $tablestatus )
			{
				$this->jobdata['DB_DUMP']['TABLESTATUS'][$tablestatus['Name']] = $tablestatus;
			}

			if ( count( $this->jobdata['DB_DUMP']['TABLES'] ) == 0 ) {
				trigger_error( __( 'No tables to dump', 'backwpup' ), E_USER_WARNING );
				$this->jobdata['STEPSDONE'][] = 'DB_DUMP'; //set done
				return;
			}

			$dbdumpheader = "-- ---------------------------------------------------------" . $this->line_separator;
			$dbdumpheader .= "-- Dumped with BackWPup ver.: " . BackWPup::get_plugin_data('Version') . $this->line_separator;
			$dbdumpheader .= "-- Plugin for WordPress " . $wp_version . " by Daniel Huesken" . $this->line_separator;
			$dbdumpheader .= "-- http://backwpup.com" . $this->line_separator;
			$dbdumpheader .= "-- Blog Name: " . get_bloginfo( 'name' ) . $this->line_separator;
			if ( defined( 'WP_SITEURL' ) )
				$dbdumpheader .= "-- Blog URL: " . trailingslashit( WP_SITEURL ) . $this->line_separator;
			else
				$dbdumpheader .= "-- Blog URL: " . trailingslashit( get_option( 'siteurl' ) ) . $this->line_separator;
			$dbdumpheader .= "-- Blog ABSPATH: " . trailingslashit( str_replace( '\\', '/', ABSPATH ) ) . $this->line_separator;
			$dbdumpheader .= "-- Blog Charset: " . get_option( 'blog_charset' ) . $this->line_separator;
			$dbdumpheader .= "-- Table Prefix: " . $wpdb->prefix . $this->line_separator;
			$dbdumpheader .= "-- Database Name: " . backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbname' ) . $this->line_separator;
			$dbdumpheader .= "-- Dumped on: " . date_i18n( 'Y-m-d H:i.s' ) . $this->line_separator;
			$dbdumpheader .= "-- ---------------------------------------------------------" . $this->line_separator . $this->line_separator;
			//for better import with mysql client
			$dbdumpheader .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;" . $this->line_separator;
			$dbdumpheader .= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;" . $this->line_separator;
			$dbdumpheader .= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;" . $this->line_separator;
			$dbdumpheader .= "/*!40101 SET NAMES '" . mysql_client_encoding($backwpupsql->dbh)."'";
				if (backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbcollation' ))
					$dbdumpheader .=" COLLATE '".backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbcollation' )."'";
				$dbdumpheader .=" */;" . $this->line_separator;
			$dbdumpheader .= "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;" . $this->line_separator;
			$dbdumpheader .= "/*!40103 SET TIME_ZONE='" . $backwpupsql->get_var( "SELECT @@time_zone" ) . "' */;" . $this->line_separator;
			$dbdumpheader .= "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;" . $this->line_separator;
			$dbdumpheader .= "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;" . $this->line_separator;
			$dbdumpheader .= "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;" . $this->line_separator;
			$dbdumpheader .= "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;" . $this->line_separator . $this->line_separator;
			if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbdumpfilecompression' ) == 'gz' )
				gzwrite( $file, $dbdumpheader );
			elseif ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbdumpfilecompression' ) == 'bz2' )
				bzwrite( $file, $dbdumpheader );
			else
				fwrite( $file, $dbdumpheader );
		}

		//make table dumps with native sql commands for reduce memory usage
		if ( $this->jobdata['STEPTODO'] != $this->jobdata['STEPDONE'] ) {
			foreach ( $this->jobdata['DB_DUMP']['TABLES'] as $tablekey => $table ) {

				trigger_error( sprintf( __( 'Dump database table "%s"', 'backwpup' ), $table ), E_USER_NOTICE );
				$this->update_working_data();
				if ( ! isset($this->jobdata['DB_DUMP']['ROWDONE']) )
					$this->jobdata['DB_DUMP']['ROWDONE'] = 0;

				$tablecreate = $this->line_separator . "--" . $this->line_separator . "-- Table structure for table $table" . $this->line_separator . "--" . $this->line_separator . $this->line_separator;
				$tablecreate .= "DROP TABLE IF EXISTS `" . $table . "`;" . $this->line_separator;
				$tablecreate .= "/*!40101 SET @saved_cs_client     = @@character_set_client */;" . $this->line_separator;
				$tablecreate .= "/*!40101 SET character_set_client = '" . mysql_client_encoding($backwpupsql->dbh) . "' */;" . $this->line_separator;
				//Dump the table structure
				$res = mysql_query( "SHOW CREATE TABLE `" . $table . "`", $backwpupsql->dbh);
				if ( mysql_error($backwpupsql->dbh) ) {
					trigger_error( sprintf( __( 'Database error %1$s for query %2$s', 'backwpup' ), mysql_error($backwpupsql->dbh), "SHOW CREATE TABLE `" . $table . "`" ), E_USER_ERROR );
					return false;
				}
				$tablecreate .= mysql_result( $res, 0, 'Create Table' ) . ";" . $this->line_separator . $this->line_separator;
				$tablecreate .= "/*!40101 SET character_set_client = @saved_cs_client */;" . $this->line_separator;

				if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbdumpfilecompression' ) == 'gz' )
					gzwrite( $file, $tablecreate );
				elseif ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbdumpfilecompression' ) == 'bz2' )
					bzwrite( $file, $tablecreate );
				else
					fwrite( $file, $tablecreate );

				$tabledata = $this->line_separator . "--" . $this->line_separator . "-- Dumping data for table $table" . $this->line_separator . "--" . $this->line_separator . $this->line_separator;

				if ( $this->jobdata['DB_DUMP']['TABLESTATUS'][$table]['Engine'] == 'MyISAM' )
					$tabledata .= "/*!40000 ALTER TABLE `" . $table . "` DISABLE KEYS */;" . $this->line_separator;

				if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbdumpfilecompression' ) == 'gz' )
					gzwrite( $file, $tabledata );
				elseif ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbdumpfilecompression' ) == 'bz2' )
					bzwrite( $file, $tabledata );
				else
					fwrite( $file, $tabledata );
				$tabledata = '';

				//get data from table
				$result  = mysql_query( "SELECT * FROM `" . $table . "`", $backwpupsql->dbh );
				$numrows = mysql_num_rows( $result );
				if ( mysql_error($backwpupsql->dbh) ) {
					trigger_error( sprintf( __( 'Database error %1$s for query %2$s', 'backwpup' ), mysql_error($backwpupsql->dbh), "SELECT * FROM `" . $table . "`" ), E_USER_ERROR );
					return false;
				}
				//get field information
				$fieldsarray = array();
				$fieldinfo   = array();
				$fields      = mysql_num_fields( $result );
				for ( $i = 0; $i < $fields; $i ++ ) {
					$fieldsarray[$i]             = mysql_field_name( $result, $i );
					$fieldinfo[$fieldsarray[$i]] = mysql_fetch_field( $result, $i );
				}

				if ( $this->jobdata['DB_DUMP']['ROWDONE'] == 0 )
					$this->jobdata['DB_DUMP']['QUERYLEN'] = 0;
				$count = 0;
				while ( $data = mysql_fetch_assoc( $result ) ) {
					$values = array();
					$dump   = '';
					if ( $this->jobdata['DB_DUMP']['ROWDONE'] > $count )
						continue;
					foreach ( $data as $key => $value ) {
						if ( is_null( $value ) || ! isset($value) ) // Make Value NULL to string NULL
							$value = "NULL";
						elseif ( $fieldinfo[$key]->numeric == 1 && $fieldinfo[$key]->type != 'timestamp' && $fieldinfo[$key]->blob != 1 ) //is value numeric no esc
							$value = empty($value) ? 0 : $value;
						else
							$value = "'" . mysql_real_escape_string( $value ) . "'";
						$values[] = $value;
					}
					if ( $this->jobdata['DB_DUMP']['QUERYLEN'] == 0 )
						$dump = "INSERT INTO `" . $table . "` (`" . implode( "`, `", $fieldsarray ) . "`) VALUES " . $this->line_separator;
					if ( ($this->jobdata['DB_DUMP']['QUERYLEN'] + strlen( $dump )) <= 50000 && $this->jobdata['DB_DUMP']['ROWDONE'] != ($numrows - 1) ) { //new query in dump on more than 50000 chars.
						$dump .= "(" . implode( ", ", $values ) . ")," . $this->line_separator;
						$this->jobdata['DB_DUMP']['QUERYLEN'] = $this->jobdata['DB_DUMP']['QUERYLEN'] + strlen( $dump );
					} else {
						$dump .= "(" . implode( ", ", $values ) . ");" . $this->line_separator;
						$this->jobdata['DB_DUMP']['QUERYLEN'] = 0;
					}
					if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbdumpfilecompression' ) == 'gz' )
						gzwrite( $file, $dump );
					elseif ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbdumpfilecompression' ) == 'bz2' )
						bzwrite( $file, $dump );
					else
						fwrite( $file, $dump );
					$this->jobdata['DB_DUMP']['ROWDONE'] ++;
					$count ++;
				}

				if ( $this->jobdata['DB_DUMP']['TABLESTATUS'][$table]['Engine'] == 'MyISAM' )
					$tabledata = "/*!40000 ALTER TABLE `" . $table . "` ENABLE KEYS */;" . $this->line_separator;

				if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbdumpfilecompression' ) == 'gz' )
					gzwrite( $file, $tabledata );
				elseif ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbdumpfilecompression' ) == 'bz2' )
					bzwrite( $file, $tabledata );
				else
					fwrite( $file, $tabledata );

				mysql_free_result( $result );

				unset($this->jobdata['DB_DUMP']['TABLES'][$tablekey]);
				$this->jobdata['STEPDONE'] ++;
				$this->jobdata['DB_DUMP']['ROWDONE'] = 0;
			}
		}

		if ( $this->jobdata['STEPTODO'] == $this->jobdata['STEPDONE'] ) {
			//for better import with mysql client
			$dbdumpfooter = $this->line_separator . "--" . $this->line_separator . "-- Delete not needed values on backwpup table" . $this->line_separator . "--" . $this->line_separator . $this->line_separator;
			$dbdumpfooter .= "DELETE FROM `" . $backwpupsql->prefix . "backwpup` WHERE `main`='temp';" . $this->line_separator;
			$dbdumpfooter .= "DELETE FROM `" . $backwpupsql->prefix . "backwpup` WHERE `main`='api';" . $this->line_separator;
			$dbdumpfooter .= "DELETE FROM `" . $backwpupsql->prefix . "backwpup` WHERE `main`='working';" . $this->line_separator . $this->line_separator . $this->line_separator;
			$dbdumpfooter .= "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;" . $this->line_separator;
			$dbdumpfooter .= "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;" . $this->line_separator;
			$dbdumpfooter .= "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;" . $this->line_separator;
			$dbdumpfooter .= "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;" . $this->line_separator;
			$dbdumpfooter .= "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;" . $this->line_separator;
			$dbdumpfooter .= "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;" . $this->line_separator;
			$dbdumpfooter .= "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;" . $this->line_separator;
			$dbdumpfooter .= "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;" . $this->line_separator;

			if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbdumpfilecompression' ) == 'gz' ) {
				gzwrite( $file, $dbdumpfooter );
				gzclose( $file );
			} elseif ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbdumpfilecompression' ) == 'bz2' ) {
				bzwrite( $file, $dbdumpfooter );
				bzclose( $file );
			} else {
				fwrite( $file, $dbdumpfooter );
				fclose( $file );
			}

			trigger_error( __( 'Database dump done!', 'backwpup' ), E_USER_NOTICE );

			//add database file to backup files
			if ( is_readable( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['DBDUMPFILE'] ) ) {
				$this->jobdata['EXTRAFILESTOBACKUP'][] = backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['DBDUMPFILE'];
				$this->jobdata['COUNT']['FILES'] ++;
				$this->jobdata['COUNT']['FILESIZE'] = $this->jobdata['COUNT']['FILESIZE'] + @filesize( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['DBDUMPFILE'] );
				trigger_error( sprintf( __( 'Added database dump "%1$s" with %2$s to backup file list', 'backwpup' ), $this->jobdata['DBDUMPFILE'], size_format( filesize( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['DBDUMPFILE'] ), 2 ) ), E_USER_NOTICE );
			}
		}
		//close db connection
		unset($backwpupsqlh);
		//Back from maintenance
		$this->maintenance_mode( false );
		$this->jobdata['STEPSDONE'][] = 'DB_DUMP'; //set done
		return true;
	}

	/**
	 * Checks the Database
	 * @return nothing
	 */
	private function db_check() {
		trigger_error( sprintf( __( '%d. Try for database check...', 'backwpup' ), $this->jobdata['DB_CHECK']['STEP_TRY'] ), E_USER_NOTICE );
		if ( ! isset($this->jobdata['DB_CHECK']['DONETABLE']) || ! is_array( $this->jobdata['DB_CHECK']['DONETABLE'] ) )
			$this->jobdata['DB_CHECK']['DONETABLE'] = array();

		//make a new DB connection
		$backwpupsql=new wpdb(backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbuser' ),backwpup_decrypt(backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbpassword' )),backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbname' ),backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbhost' ));
		if (!$backwpupsql->dbh)  {
			trigger_error( sprintf( __( 'Can not connect to database %1$s', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbname' ) ), E_USER_ERROR );
		}
		$backwpupsql->set_charset($backwpupsql->dbh,backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbcharset' ),backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbcollation' ));
		trigger_error( sprintf( __( 'Connected to database %1$s', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbname' ) ), E_USER_NOTICE );

		//to backup
		$tablestobackup = array();
		$tables         = $backwpupsql->get_col( "SHOW TABLES FROM `" . backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbname' ) . "`" ); //get table status
		if ( mysql_error($backwpupsql->dbh) )
			trigger_error( sprintf( __( 'Database error %1$s for query %2$s', 'backwpup' ), mysql_error($backwpupsql->dbh), $backwpupsql->last_query ), E_USER_ERROR );
		foreach ( $tables as $table ) {
			if ( ! in_array( $table, backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbexclude' ) ) )
				$tablestobackup[] = $table;
		}
		//Set num
		$this->jobdata['STEPTODO'] = sizeof( $tablestobackup );

		//check tables
		if ( $this->jobdata['STEPTODO'] > 0 ) {
			$this->maintenance_mode( true );
			foreach ( $tablestobackup as $table ) {
				if ( in_array( $table, $this->jobdata['DB_CHECK']['DONETABLE'] ) )
					continue;
				$check = $backwpupsql->get_row( "CHECK TABLE `" . $table . "` MEDIUM" );
				if ( mysql_error($backwpupsql->dbh) ) {
					trigger_error( sprintf( __( 'Database error %1$s for query %2$s', 'backwpup' ), mysql_error($backwpupsql->dbh), $backwpupsql->last_query ), E_USER_ERROR );
					continue;
				}
				if ( $check->Msg_text == 'OK' )
					trigger_error( sprintf( __( 'Result of table check for %1$s is: %2$s', 'backwpup' ), $table, $check->Msg_text ), E_USER_NOTICE );
				elseif ( strtolower( $check->Msg_type ) == 'warning' )
					trigger_error( sprintf( __( 'Result of table check for %1$s is: %2$s', 'backwpup' ), $table, $check->Msg_text ), E_USER_WARNING );
				else
					trigger_error( sprintf( __( 'Result of table check for %1$s is: %2$s', 'backwpup' ), $table, $check->Msg_text ), E_USER_ERROR );

				//Try to Repair table
				if ( $check->Msg_text != 'OK' ) {
					$repair = $backwpupsql->get_row( 'REPAIR TABLE `' . $table . '`' );
					if ( mysql_error($backwpupsql->dbh) ) {
						trigger_error( sprintf( __( 'Database error %1$s for query %2$s', 'backwpup' ), mysql_error($backwpupsql->dbh), $backwpupsql->last_query ), E_USER_ERROR );
						continue;
					}
					if ( $repair->Msg_type == 'OK' )
						trigger_error( sprintf( __( 'Result of table repair for %1$s is: %2$s', 'backwpup' ), $table, $repair->Msg_text ), E_USER_NOTICE );
					elseif ( strtolower( $repair->Msg_type ) == 'warning' )
						trigger_error( sprintf( __( 'Result of table repair for %1$s is: %2$s', 'backwpup' ), $table, $repair->Msg_text ), E_USER_WARNING );
					else
						trigger_error( sprintf( __( 'Result of table repair for %1$s is: %2$s', 'backwpup' ), $table, $repair->Msg_text ), E_USER_ERROR );
				}
				$this->jobdata['DB_CHECK']['DONETABLE'][] = $table;
				$this->jobdata['STEPDONE'] ++;
			}
			$this->maintenance_mode( false );
			trigger_error( __( 'Database check done!', 'backwpup' ), E_USER_NOTICE );
		} else {
			trigger_error( __( 'No tables to check', 'backwpup' ), E_USER_WARNING );
		}
		unset($backwpupsql);
		$this->jobdata['STEPSDONE'][] = 'DB_CHECK'; //set done
	}

	/**
	 * Optimize the Database
	 * @return nothing
	 */
	private function db_optimize() {
		trigger_error( sprintf( __( '%d. Try for database optimize...', 'backwpup' ), $this->jobdata['DB_OPTIMIZE']['STEP_TRY'] ), E_USER_NOTICE );
		if ( ! isset($this->jobdata['DB_OPTIMIZE']['DONETABLE']) || ! is_array( $this->jobdata['DB_OPTIMIZE']['DONETABLE'] ) )
			$this->jobdata['DB_OPTIMIZE']['DONETABLE'] = array();

		//make a new DB connection
		$backwpupsql=new wpdb(backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbuser' ),backwpup_decrypt(backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbpassword' )),backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbname' ),backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbhost' ));
		if (!$backwpupsql->dbh)  {
			trigger_error( sprintf( __( 'Can not connect to database %1$s', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbname' ) ), E_USER_ERROR );
		}
		$backwpupsql->set_charset($backwpupsql->dbh,backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbcharset' ),backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbcollation' ));
		trigger_error( sprintf( __( 'Connected to database %1$s', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbname' ) ), E_USER_NOTICE );


		//to backup
		$tablestobackup = array();
		$tables         = $backwpupsql->get_col( "SHOW TABLES FROM `" . backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbname' ) . "`" ); //get table status
		if ( mysql_error($backwpupsql->dbh) )
			trigger_error( sprintf( __( 'Database error %1$s for query %2$s', 'backwpup' ), mysql_error($backwpupsql->dbh), $backwpupsql->last_query ), E_USER_ERROR );
		foreach ( $tables as $table ) {
			if ( ! in_array( $table, backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbexclude' ) ) )
				$tablestobackup[] = $table;
		}
		//Set num of todos
		$this->jobdata['STEPTODO'] = count( $tablestobackup );

		//get table status
		$tablesstatus = $backwpupsql->get_results( "SHOW TABLE STATUS FROM `" . backwpup_get_option( $this->jobdata['JOBMAIN'], 'dbname' ) . "`" );
		if ( mysql_error($backwpupsql->dbh) )
			trigger_error( sprintf( __( 'Database error %1$s for query %2$s', 'backwpup' ), mysql_error($backwpupsql->dbh), $backwpupsql->last_query ), E_USER_ERROR );
		foreach ( $tablesstatus as $tablestatus )
		{
			$status[$tablestatus->Name] = $tablestatus;
		}

		if ( $this->jobdata['STEPTODO'] > 0 ) {
			$this->maintenance_mode( true );
			foreach ( $tablestobackup as $table ) {
				if ( in_array( $table, $this->jobdata['DB_OPTIMIZE']['DONETABLE'] ) )
					continue;
				if ( $status[$table]->Engine != 'InnoDB' ) {
					$optimize = $backwpupsql->get_row( "OPTIMIZE TABLE `" . $table . "`" );
					if ( mysql_error($backwpupsql->dbh) )
						trigger_error( sprintf( __( 'Database error %1$s for query %2$s', 'backwpup' ), mysql_error($backwpupsql->dbh), $backwpupsql->last_query ), E_USER_ERROR );
					elseif ( strtolower( $optimize->Msg_type ) == 'error' )
						trigger_error( sprintf( __( 'Result of table optimize for %1$s is: %2$s', 'backwpup' ), $table, $optimize->Msg_text ), E_USER_ERROR );
					elseif ( strtolower( $optimize->Msg_type ) == 'warning' )
						trigger_error( sprintf( __( 'Result of table optimize for %1$s is: %2$s', 'backwpup' ), $table, $optimize->Msg_text ), E_USER_WARNING );
					else
						trigger_error( sprintf( __( 'Result of table optimize for %1$s is: %2$s', 'backwpup' ), $table, $optimize->Msg_text ), E_USER_NOTICE );
				} else {
					$backwpupsql->get_row( "ALTER TABLE `" . $table . "` ENGINE='InnoDB'" );
					if ( mysql_error($backwpupsql->dbh) )
						trigger_error( sprintf( __( 'Database error %1$s for query %2$s', 'backwpup' ), mysql_error($backwpupsql->dbh), $backwpupsql->last_query ), E_USER_ERROR );
					else
						trigger_error( sprintf( __( 'InnoDB Table %1$s optimize done', 'backwpup' ), $table ), E_USER_NOTICE );
				}
				$this->jobdata['DB_OPTIMIZE']['DONETABLE'][] = $table;
				$this->jobdata['STEPDONE'] ++;
			}
			trigger_error( __( 'Database optimize done!', 'backwpup' ), E_USER_NOTICE );
			$this->maintenance_mode( false );
		} else {
			trigger_error( __( 'No tables to optimize', 'backwpup' ), E_USER_WARNING );
		}
		unset($backwpupsql);
		$this->jobdata['STEPSDONE'][] = 'DB_OPTIMIZE'; //set done
	}

	/**
	 * Makes a WordPress export
	 *
	 * @return nothing
	 */
	private function wp_export() {
		$this->jobdata['STEPTODO'] = 1;
		trigger_error( sprintf( __( '%d. Try to make a WordPress Export to XML file...', 'backwpup' ), $this->jobdata['WP_EXPORT']['STEP_TRY'] ), E_USER_NOTICE );
		$this->need_free_memory( '5M' ); //5MB free memory
		//build filename
		$datevars                      = array( '%d', '%D', '%l', '%N', '%S', '%w', '%z', '%W', '%F', '%m', '%M', '%n', '%t', '%L', '%o', '%Y', '%a', '%A', '%B', '%g', '%G', '%h', '%H', '%i', '%s', '%u', '%e', '%I', '%O', '%P', '%T', '%Z', '%c', '%U' );
		$datevalues                    = array( date_i18n( 'd' ), date_i18n( 'D' ), date_i18n( 'l' ), date_i18n( 'N' ), date_i18n( 'S' ), date_i18n( 'w' ), date_i18n( 'z' ), date_i18n( 'W' ), date_i18n( 'F' ), date_i18n( 'm' ), date_i18n( 'M' ), date_i18n( 'n' ), date_i18n( 't' ), date_i18n( 'L' ), date_i18n( 'o' ), date_i18n( 'Y' ), date_i18n( 'a' ), date_i18n( 'A' ), date_i18n( 'B' ), date_i18n( 'g' ), date_i18n( 'G' ), date_i18n( 'h' ), date_i18n( 'H' ), date_i18n( 'i' ), date_i18n( 's' ), date_i18n( 'u' ), date_i18n( 'e' ), date_i18n( 'I' ), date_i18n( 'O' ), date_i18n( 'P' ), date_i18n( 'T' ), date_i18n( 'Z' ), date_i18n( 'c' ), date_i18n( 'U' ) );
		$this->jobdata['WPEXPORTFILE'] = str_replace( $datevars, $datevalues, backwpup_get_option( $this->jobdata['JOBMAIN'], 'wpexportfile' ) );

		//check compression
		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'wpexportfilecompression' ) == 'gz' && ! function_exists( 'gzopen' ) )
			backwpup_update_option( $this->jobdata['JOBMAIN'], 'wpexportfilecompression', '' );
		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'wpexportfilecompression' ) == 'bz2' && ! function_exists( 'bzopen' ) )
			backwpup_update_option( $this->jobdata['JOBMAIN'], 'wpexportfilecompression', '' );
		//add file ending
		$this->jobdata['WPEXPORTFILE'] .= '.xml';
		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'wpexportfilecompression' ) == 'gz' || backwpup_get_option( $this->jobdata['JOBMAIN'], 'wpexportfilecompression' ) == 'bz2' )
			$this->jobdata['WPEXPORTFILE'] .= '.' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'wpexportfilecompression' );

		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'wpexportfilecompression' ) == 'gz' )
			$this->jobdata['filehandel'] = gzopen( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['WPEXPORTFILE'], 'wb9' );
		elseif ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'wpexportfilecompression' ) == 'bz2' )
			$this->jobdata['filehandel'] = bzopen( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['WPEXPORTFILE'], 'w' );
		else
			$this->jobdata['filehandel'] = fopen( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['WPEXPORTFILE'], 'wb' );

		//include WP export function
		require_once(ABSPATH . 'wp-admin/includes/export.php');
		error_reporting( 0 ); //disable error reporting
		ob_start( array( $this, '_wp_export_ob_bufferwrite' ), 512 ); //start output buffering
		export_wp(); //WP export
		ob_end_clean(); //End output buffering
		error_reporting( E_ALL | E_STRICT ); //enable error reporting

		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'wpexportfilecompression' ) == 'gz' ) {
			gzclose( $this->jobdata['filehandel'] );
		} elseif ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'wpexportfilecompression' ) == 'bz2' ) {
			bzclose( $this->jobdata['filehandel'] );
		} else {
			fclose( $this->jobdata['filehandel'] );
		}

		//add XML file to backup files
		if ( is_readable( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['WPEXPORTFILE'] ) ) {
			$this->jobdata['EXTRAFILESTOBACKUP'][] = backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['WPEXPORTFILE'];
			$this->jobdata['COUNT']['FILES'] ++;
			$this->jobdata['COUNT']['FILESIZE'] = $this->jobdata['COUNT']['FILESIZE'] + @filesize( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['WPEXPORTFILE'] );
			trigger_error( sprintf( __( 'Added XML export "%1$s" with %2$s to backup file list', 'backwpup' ), $this->jobdata['WPEXPORTFILE'], size_format( filesize( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['WPEXPORTFILE'] ), 2 ) ), E_USER_NOTICE );
		}
		$this->jobdata['STEPDONE']    = 1;
		$this->jobdata['STEPSDONE'][] = 'WP_EXPORT'; //set done
	}

	/**
	 *
	 * Helper for wp-export()
	 *
	 * @param $output
	 */
	public function _wp_export_ob_bufferwrite( $output ) {
		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'wpexportfilecompression' ) == 'gz' ) {
			gzwrite( $this->jobdata['filehandel'], $output );
		} elseif ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'wpexportfilecompression' ) == 'bz2' ) {
			bzwrite( $this->jobdata['filehandel'], $output );
		} else {
			fwrite( $this->jobdata['filehandel'], $output );
		}
		$this->update_working_data();
	}

	/**
	 * Makes a file with installed Plugins
	 *
	 * @return nothing
	 */
	private function wp_plugin_list() {
		global $wp_version;
		$this->jobdata['STEPTODO'] = 1;
		trigger_error( sprintf( __( '%d. Try to generate a file with installed Plugin names...', 'backwpup' ), $this->jobdata['WP_PLUGIN_LIST']['STEP_TRY'] ), E_USER_NOTICE );
		//build filename
		$datevars                        = array( '%d', '%D', '%l', '%N', '%S', '%w', '%z', '%W', '%F', '%m', '%M', '%n', '%t', '%L', '%o', '%Y', '%a', '%A', '%B', '%g', '%G', '%h', '%H', '%i', '%s', '%u', '%e', '%I', '%O', '%P', '%T', '%Z', '%c', '%U' );
		$datevalues                      = array( date_i18n( 'd' ), date_i18n( 'D' ), date_i18n( 'l' ), date_i18n( 'N' ), date_i18n( 'S' ), date_i18n( 'w' ), date_i18n( 'z' ), date_i18n( 'W' ), date_i18n( 'F' ), date_i18n( 'm' ), date_i18n( 'M' ), date_i18n( 'n' ), date_i18n( 't' ), date_i18n( 'L' ), date_i18n( 'o' ), date_i18n( 'Y' ), date_i18n( 'a' ), date_i18n( 'A' ), date_i18n( 'B' ), date_i18n( 'g' ), date_i18n( 'G' ), date_i18n( 'h' ), date_i18n( 'H' ), date_i18n( 'i' ), date_i18n( 's' ), date_i18n( 'u' ), date_i18n( 'e' ), date_i18n( 'I' ), date_i18n( 'O' ), date_i18n( 'P' ), date_i18n( 'T' ), date_i18n( 'Z' ), date_i18n( 'c' ), date_i18n( 'U' ) );
		$this->jobdata['PLUGINLISTFILE'] = str_replace( $datevars, $datevalues, backwpup_get_option( $this->jobdata['JOBMAIN'], 'pluginlistfile' ) ) . '.txt';
		//open file
		$fd     = fopen( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['PLUGINLISTFILE'], 'wb' );
		$header = "------------------------------------------------------------" . $this->line_separator;
		$header .= "  Plugin list generated with BackWPup ver.: " . BackWPup::get_plugin_data('Version') . $this->line_separator;
		$header .= "  Plugin for WordPress " . $wp_version . " by Daniel Huesken" . $this->line_separator;
		$header .= "  http://backwpup.com" . $this->line_separator;
		$header .= "  Blog Name: " . get_bloginfo( 'name' ) . $this->line_separator;
		if ( defined( 'WP_SITEURL' ) )
			$header .= "  Blog URL: " . trailingslashit( WP_SITEURL ) . $this->line_separator;
		else
			$header .= "  Blog URL: " . trailingslashit( get_option( 'siteurl' ) ) . $this->line_separator;
		$header .= "  Generated on: " . date_i18n( 'Y-m-d H:i.s' ) . $this->line_separator;
		$header .= "------------------------------------------------------------" . $this->line_separator . $this->line_separator;
		fwrite( $fd, $header );
		//get Plugins
		if ( ! function_exists( 'get_plugins' ) )
			require_once(ABSPATH . 'wp-admin/includes/plugin.php');
		$plugins        = get_plugins();
		$plugins_active = get_option( 'active_plugins' );
		//write it to file
		fwrite( $fd, $this->line_separator . __( 'All plugins information:', 'backwpup' ) . $this->line_separator . '------------------------------' . $this->line_separator );
		foreach ( $plugins as $key => $plugin ) {
			fwrite( $fd, $plugin['Name'] . ' (v.' . $plugin['Version'] . ') ' . html_entity_decode( sprintf( __( 'from %s', 'backwpup' ), $plugin['Author'] ), ENT_QUOTES ) . $this->line_separator . "\t" . $plugin['PluginURI'] . $this->line_separator );
		}
		fwrite( $fd, $this->line_separator . __( 'Active plugins:', 'backwpup' ) . $this->line_separator . '------------------------------' . $this->line_separator );
		foreach ( $plugins as $key => $plugin ) {
			if ( in_array( $key, $plugins_active ) )
				fwrite( $fd, $plugin['Name'] . $this->line_separator );
		}
		fwrite( $fd, $this->line_separator . __( 'Inactive plugins:', 'backwpup' ) . $this->line_separator . '------------------------------' . $this->line_separator );
		foreach ( $plugins as $key => $plugin ) {
			if ( ! in_array( $key, $plugins_active ) )
				fwrite( $fd, $plugin['Name'] . $this->line_separator );
		}
		fclose( $fd );
		//add file to backup files
		if ( is_readable( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['PLUGINLISTFILE'] ) ) {
			$this->jobdata['EXTRAFILESTOBACKUP'][] = backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['PLUGINLISTFILE'];
			$this->jobdata['COUNT']['FILES'] ++;
			$this->jobdata['COUNT']['FILESIZE'] = $this->jobdata['COUNT']['FILESIZE'] + @filesize( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['PLUGINLISTFILE'] );
			trigger_error( sprintf( __( 'Added plugin list file "%1$s" with %2$s to backup file list', 'backwpup' ), $this->jobdata['PLUGINLISTFILE'], size_format( filesize( backwpup_get_option( 'cfg', 'tempfolder' ) . $this->jobdata['PLUGINLISTFILE'] ), 2 ) ), E_USER_NOTICE );
		}
		$this->jobdata['STEPDONE']    = 1;
		$this->jobdata['STEPSDONE'][] = 'WP_PLUGIN_LIST'; //set done
	}


	/**
	 * Generates a list of folder to backup
	 * @return nothing
	 */
	private function folder_list() {
		trigger_error( sprintf( __( '%d. Try to make list of folder to backup....', 'backwpup' ), $this->jobdata['FOLDER_LIST']['STEP_TRY'] ), E_USER_NOTICE );
		$this->jobdata['STEPTODO'] = 7;

		//Check free memory for file list
		$this->need_free_memory( '2M' ); //2MB free memory

		//Folder list for blog folders
		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backuproot' ) && $this->jobdata['STEPDONE'] == 0 )
			$this->_folder_list( trailingslashit( str_replace( '\\', '/', ABSPATH ) ), 100,
				array_merge( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backuprootexcludedirs' ), BackWPup_File::get_exclude_wp_dirs( ABSPATH ) ) );
		if ( $this->jobdata['STEPDONE'] == 0 )
			$this->jobdata['STEPDONE'] = 1;
		$this->update_working_data();
		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backupcontent' ) && $this->jobdata['STEPDONE'] == 1 )
			$this->_folder_list( trailingslashit( str_replace( '\\', '/', WP_CONTENT_DIR ) ), 100,
				array_merge( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backupcontentexcludedirs' ), BackWPup_File::get_exclude_wp_dirs( WP_CONTENT_DIR ) ) );
		if ( $this->jobdata['STEPDONE'] == 1 )
			$this->jobdata['STEPDONE'] = 2;
		$this->update_working_data();
		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backupplugins' ) && $this->jobdata['STEPDONE'] == 2 )
			$this->_folder_list( trailingslashit( str_replace( '\\', '/', WP_PLUGIN_DIR ) ), 100,
				array_merge( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backuppluginsexcludedirs' ), BackWPup_File::get_exclude_wp_dirs( WP_PLUGIN_DIR ) ) );
		if ( $this->jobdata['STEPDONE'] == 2 )
			$this->jobdata['STEPDONE'] = 3;
		$this->update_working_data();
		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backupthemes' ) && $this->jobdata['STEPDONE'] == 3 )
			$this->_folder_list( trailingslashit( str_replace( '\\', '/', trailingslashit( WP_CONTENT_DIR ) . 'themes/' ) ), 100,
				array_merge( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backupthemesexcludedirs' ), BackWPup_File::get_exclude_wp_dirs( trailingslashit( WP_CONTENT_DIR ) . 'themes/' ) ) );
		if ( $this->jobdata['STEPDONE'] == 3 )
			$this->jobdata['STEPDONE'] = 4;
		$this->update_working_data();
		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backupuploads' ) && $this->jobdata['STEPDONE'] == 4 )
			$this->_folder_list( BackWPup_File::get_upload_dir(), 100,
				array_merge( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backupuploadsexcludedirs' ), BackWPup_File::get_exclude_wp_dirs( BackWPup_File::get_upload_dir() ) ) );
		if ( $this->jobdata['STEPDONE'] == 4 )
			$this->jobdata['STEPDONE'] = 5;
		$this->update_working_data();

		//include dirs
		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dirinclude' ) && $this->jobdata['STEPDONE'] == 5 ) {
			$dirinclude = explode( ',', backwpup_get_option( $this->jobdata['JOBMAIN'], 'dirinclude' ) );
			$dirinclude = array_unique( $dirinclude );
			//Crate file list for includes
			foreach ( $dirinclude as $dirincludevalue ) {
				if ( is_dir( $dirincludevalue ) )
					$this->_folder_list( $dirincludevalue );
			}
		}
		if ( $this->jobdata['STEPDONE'] == 5 )
			$this->jobdata['STEPDONE'] = 6;
		$this->update_working_data();

		$this->jobdata['FOLDERLIST'] = array_unique( $this->jobdata['FOLDERLIST'] ); //all files only one time in list
		sort( $this->jobdata['FOLDERLIST'] );

		//add extra files if selected
		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backupspecialfiles' ) ) {
			if ( is_file( ABSPATH . 'wp-config.php' ) && ! backwpup_get_option( $this->jobdata['JOBMAIN'], 'backuproot' ) ) {
				$this->jobdata['EXTRAFILESTOBACKUP'][] = str_replace( '\\', '/', ABSPATH . 'wp-config.php' );
				$this->jobdata['COUNT']['FILES'] ++;
				$this->jobdata['COUNT']['FILESIZE'] = $this->jobdata['COUNT']['FILESIZE'] + @filesize( ABSPATH . 'wp-config.php' );
				trigger_error( sprintf( __( 'Added "%s" to backup file list', 'backwpup' ), 'wp-config.php' ), E_USER_NOTICE );
			} elseif ( is_file( dirname( ABSPATH ) . '/wp-config.php' ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
				$this->jobdata['EXTRAFILESTOBACKUP'][] = str_replace( '\\', '/', dirname( ABSPATH ) . '/wp-config.php' );
				$this->jobdata['COUNT']['FILES'] ++;
				$this->jobdata['COUNT']['FILESIZE'] = $this->jobdata['COUNT']['FILESIZE'] + @filesize( dirname( ABSPATH ) . '/wp-config.php' );
				trigger_error( sprintf( __( 'Added "%s" to backup file list', 'backwpup' ), 'wp-config.php' ), E_USER_NOTICE );
			}
			if ( is_file( ABSPATH . '.htaccess' ) && ! backwpup_get_option( $this->jobdata['JOBMAIN'], 'backuproot' ) ) {
				$this->jobdata['EXTRAFILESTOBACKUP'][] = str_replace( '\\', '/', ABSPATH . '.htaccess' );
				$this->jobdata['COUNT']['FILES'] ++;
				$this->jobdata['COUNT']['FILESIZE'] = $this->jobdata['COUNT']['FILESIZE'] + @filesize( ABSPATH . '.htaccess' );
				trigger_error( sprintf( __( 'Added "%s" to backup file list', 'backwpup' ), '.htaccess' ), E_USER_NOTICE );
			}
			if ( is_file( ABSPATH . '.htpasswd' ) && ! backwpup_get_option( $this->jobdata['JOBMAIN'], 'backuproot' ) ) {
				$this->jobdata['EXTRAFILESTOBACKUP'][] = str_replace( '\\', '/', ABSPATH . '.htpasswd' );
				$this->jobdata['COUNT']['FILES'] ++;
				$this->jobdata['COUNT']['FILESIZE'] = $this->jobdata['COUNT']['FILESIZE'] + @filesize( ABSPATH . '.htpasswd' );
				trigger_error( sprintf( __( 'Added "%s" to backup file list', 'backwpup' ), '.htpasswd' ), E_USER_NOTICE );
			}
			if ( is_file( ABSPATH . 'robots.txt' ) && ! backwpup_get_option( $this->jobdata['JOBMAIN'], 'backuproot' ) ) {
				$this->jobdata['EXTRAFILESTOBACKUP'][] = str_replace( '\\', '/', ABSPATH . 'robots.txt' );
				$this->jobdata['COUNT']['FILES'] ++;
				$this->jobdata['COUNT']['FILESIZE'] = $this->jobdata['COUNT']['FILESIZE'] + @filesize( ABSPATH . 'robots.txt' );
				trigger_error( sprintf( __( 'Added "%s" to backup file list', 'backwpup' ), 'robots.txt' ), E_USER_NOTICE );
			}
			if ( is_file( ABSPATH . 'favicon.ico' ) && ! backwpup_get_option( $this->jobdata['JOBMAIN'], 'backuproot' ) ) {
				$this->jobdata['EXTRAFILESTOBACKUP'][] = str_replace( '\\', '/', ABSPATH . 'favicon.ico' );
				$this->jobdata['COUNT']['FILES'] ++;
				$this->jobdata['COUNT']['FILESIZE'] = $this->jobdata['COUNT']['FILESIZE'] + @filesize( ABSPATH . 'favicon.ico' );
				trigger_error( sprintf( __( 'Added "%s" to backup file list', 'backwpup' ), 'favicon.ico' ), E_USER_NOTICE );
			}
		}

		if ( empty($this->jobdata['FOLDERLIST']) )
			trigger_error( __( 'No Folder to backup', 'backwpup' ), E_USER_ERROR );
		else
			trigger_error( sprintf( __( '%1$d Folders to backup', 'backwpup' ), $this->jobdata['COUNT']['FOLDER'] ), E_USER_NOTICE );

		$this->jobdata['STEPDONE']    = 7;
		$this->jobdata['STEPSDONE'][] = 'FOLDER_LIST'; //set done
		$this->update_working_data();
	}

	/**
	 *
	 * Helper function for folder_list()
	 *
	 * @param string $folder
	 * @param int	$levels
	 * @param array  $excludedirs
	 *
	 * @return bool
	 */
	private function _folder_list( $folder = '', $levels = 100, $excludedirs = array() ) {
		if ( empty($folder) )
			return false;
		if ( ! $levels )
			return false;
		$this->jobdata['COUNT']['FOLDER'] ++;
		$folder = trailingslashit( $folder );
		if ( $dir = @opendir( $folder ) ) {
			$this->jobdata['FOLDERLIST'][] = str_replace( '\\', '/', $folder );
			while ( ($file = readdir( $dir )) !== false ) {
				if ( in_array( $file, array( '.', '..' ) ) )
					continue;
				foreach ( $this->jobdata['FILEEXCLUDES'] as $exclusion ) { //exclude files
					$exclusion = trim( $exclusion );
					if ( false !== stripos( $folder . $file, trim( $exclusion ) ) && ! empty($exclusion) )
						continue 2;
				}
				if ( is_dir( $folder . $file ) && ! is_readable( $folder . $file ) ) {
					trigger_error( sprintf( __( 'Folder "%s" is not readable!', 'backwpup' ), $folder . $file ), E_USER_WARNING );
				} elseif ( is_dir( $folder . $file ) ) {
					if ( in_array( trailingslashit( $folder . $file ), $excludedirs ) || in_array( trailingslashit( $folder . $file ), $this->jobdata['FOLDERLIST'] ) )
						continue;
					$this->_folder_list( trailingslashit( $folder . $file ), $levels - 1, $excludedirs );
				}
			}
			@closedir( $dir );
		}
		return true;
	}

	/**
	 *
	 *	Gif back a array of files to backup in the selected folder
	 *
	 * @param string $folder thefolder to get the files from
	 *
	 * @return array files to backup
	 */
	protected function get_files_in_folder( $folder ) {
		$files = array();
		if ( $dir = @opendir( $folder ) ) {
			while ( ($file = readdir( $dir )) !== false ) {
				if ( in_array( $file, array( '.', '..' ) ) )
					continue;
				foreach ( $this->jobdata['FILEEXCLUDES'] as $exclusion ) { //exclude files
					$exclusion = trim( $exclusion );
					if ( false !== stripos( $folder . $file, trim( $exclusion ) ) && ! empty($exclusion) )
						continue 2;
				}
				if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backupexcludethumbs' ) && strpos( $folder, BackWPup_File::get_upload_dir() ) !== false && preg_match( "/\-[0-9]{2,4}x[0-9]{2,4}\.(jpg|png|gif)$/i", $file ) )
					continue;
				if ( ! is_readable( $folder . $file ) )
					trigger_error( sprintf( __( 'File "%s" is not readable!', 'backwpup' ), $folder . $file ), E_USER_WARNING );
				elseif ( is_link( $folder . $file ) )
					trigger_error( sprintf( __( 'Link "%s" not followed', 'backwpup' ), $folder . $file ), E_USER_WARNING );
				elseif ( is_file( $folder . $file ) ) {
					$files[] = $folder . $file;
					$this->jobdata['COUNT']['FILESINFOLDER'] ++;
					$this->jobdata['COUNT']['FILESIZEINFOLDER'] = $this->jobdata['COUNT']['FILESIZEINFOLDER'] + @filesize( $folder . $file );
				}
			}
			@closedir( $dir );
		}
		return $files;
	}

	/**
	 * Creates the backup archive
	 * @return nothing
	 */
	private function create_archive() {
		$this->jobdata['STEPTODO'] = count( $this->jobdata['FOLDERLIST'] ) + 1;

		if ( strtolower( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileformart' ) ) == ".zip" && class_exists( 'ZipArchive', true ) ) { //use php zip lib
			trigger_error( sprintf( __( '%d. Trying to create backup zip archive...', 'backwpup' ), $this->jobdata['CREATE_ARCHIVE']['STEP_TRY'] ), E_USER_NOTICE );
			$numopenfiles = 0;
			$zip          = new ZipArchive();
			$res          = $zip->open( $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'], ZipArchive::CREATE );
			if ( $res !== true ) {
				trigger_error( sprintf( __( 'Can not create backup zip archive: %d!', 'backwpup' ), $res ), E_USER_ERROR );
				$this->jobdata['STEPSDONE'][] = 'CREATE_ARCHIVE'; //set done
				return;
			}
			//add extra files
			if ( $this->jobdata['STEPDONE'] == 0 ) {
				if ( ! empty($this->jobdata['EXTRAFILESTOBACKUP']) && $this->jobdata['STEPDONE'] == 0 ) {
					foreach ( $this->jobdata['EXTRAFILESTOBACKUP'] as $file ) {
						if ( ! $zip->addFile( $file, basename( $file ) ) )
							trigger_error( sprintf( __( 'Can not add "%s" to zip archive!', 'backwpup' ), basename( $file ) ), E_USER_ERROR );
						$this->update_working_data();
						$numopenfiles ++;
					}
				}
				$this->jobdata['STEPDONE'] ++;
			}
			//add normal files
			for ( $i = $this->jobdata['STEPDONE'] - 1; $i < $this->jobdata['STEPTODO'] - 1; $i ++ ) {
				$foldername = ltrim( str_replace( $this->jobdata['REMOVEPATH'], '', $this->jobdata['FOLDERLIST'][$i] ),'/' );
				if ( ! empty($foldername) ) {
					if ( ! $zip->addEmptyDir( $foldername ) )
						trigger_error( sprintf( __( 'Can not add dir "%s" to zip archive!', 'backwpup' ), $foldername ), E_USER_ERROR );
				}
				$files = $this->get_files_in_folder( $this->jobdata['FOLDERLIST'][$i] );
				if ( count( $files ) > 0 ) {
					foreach ( $files as $file ) {
						$zipfilename = ltrim(str_replace( $this->jobdata['REMOVEPATH'], '', $file ),'/');
						if ( ! $zip->addFile( $file, $zipfilename ) )
							trigger_error( sprintf( __( 'Can not add "%s" to zip archive!', 'backwpup' ), $zipfilename ), E_USER_ERROR );
						$this->update_working_data();
					}
				}
				//colse and reopen, all added files are open on fs
				if ( $numopenfiles >= 30 ) { //35 works with PHP 5.2.4 on win
					if ( $zip->status > 0 ) {
						$ziperror = $zip->status;
						if ( $zip->status == 4 )
							$ziperror = __( '(4) ER_SEEK', 'backwpup' );
						if ( $zip->status == 5 )
							$ziperror = __( '(5) ER_READ', 'backwpup' );
						if ( $zip->status == 9 )
							$ziperror = __( '(9) ER_NOENT', 'backwpup' );
						if ( $zip->status == 10 )
							$ziperror = __( '(10) ER_EXISTS', 'backwpup' );
						if ( $zip->status == 11 )
							$ziperror = __( '(11) ER_OPEN', 'backwpup' );
						if ( $zip->status == 14 )
							$ziperror = __( '(14) ER_MEMORY', 'backwpup' );
						if ( $zip->status == 18 )
							$ziperror = __( '(18) ER_INVAL', 'backwpup' );
						if ( $zip->status == 19 )
							$ziperror = __( '(19) ER_NOZIP', 'backwpup' );
						if ( $zip->status == 21 )
							$ziperror = __( '(21) ER_INCONS', 'backwpup' );
						trigger_error( sprintf( __( 'Zip returns status: %s', 'backwpup' ), $zip->status ), E_USER_ERROR );
					}
					$zip->close();
					if ( $this->jobdata['STEPDONE'] == 0 )
						$this->jobdata['STEPDONE'] = 1;
					$zip->open( $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'], ZipArchive::CREATE );
					$numopenfiles = 0;
				}
				$numopenfiles ++;
				$this->jobdata['STEPDONE'] ++;
			}
			//clese Zip
			if ( $zip->status > 0 ) {
				$ziperror = $zip->status;
				if ( $zip->status == 4 )
					$ziperror = __( '(4) ER_SEEK', 'backwpup' );
				if ( $zip->status == 5 )
					$ziperror = __( '(5) ER_READ', 'backwpup' );
				if ( $zip->status == 9 )
					$ziperror = __( '(9) ER_NOENT', 'backwpup' );
				if ( $zip->status == 10 )
					$ziperror = __( '(10) ER_EXISTS', 'backwpup' );
				if ( $zip->status == 11 )
					$ziperror = __( '(11) ER_OPEN', 'backwpup' );
				if ( $zip->status == 14 )
					$ziperror = __( '(14) ER_MEMORY', 'backwpup' );
				if ( $zip->status == 18 )
					$ziperror = __( '(18) ER_INVAL', 'backwpup' );
				if ( $zip->status == 19 )
					$ziperror = __( '(19) ER_NOZIP', 'backwpup' );
				if ( $zip->status == 21 )
					$ziperror = __( '(21) ER_INCONS', 'backwpup' );
				trigger_error( sprintf( __( 'Zip returns status: %s', 'backwpup' ), $zip->status ), E_USER_ERROR );
			}
			$zip->close();
			trigger_error( __( 'Backup zip archive created', 'backwpup' ), E_USER_NOTICE );
			$this->jobdata['STEPSDONE'][] = 'CREATE_ARCHIVE'; //set done
		}
		elseif ( strtolower( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileformart' ) ) == ".zip" ) { //use PclZip
			define('PCLZIP_TEMPORARY_DIR', backwpup_get_option( 'cfg', 'tempfolder' ));
			if ( ini_get( 'mbstring.func_overload' ) && function_exists( 'mb_internal_encoding' ) ) {
				$previous_encoding = mb_internal_encoding();
				mb_internal_encoding( 'ISO-8859-1' );
			}
			//Create Zip File
			trigger_error( sprintf( __( '%d. Trying to create backup zip (PclZip) archive...', 'backwpup' ), $this->jobdata['CREATE_ARCHIVE']['STEP_TRY'] ), E_USER_NOTICE );
			$this->need_free_memory( '20M' ); //20MB free memory for zip
			$zipbackupfile = new PclZip($this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE']);
			//add extra files
			if ( ! empty($this->jobdata['EXTRAFILESTOBACKUP']) && $this->jobdata['STEPDONE'] == 0 ) {
				foreach ( $this->jobdata['EXTRAFILESTOBACKUP'] as $file ) {
					if ( 0 == $zipbackupfile->add( array( array( PCLZIP_ATT_FILE_NAME		  => $file,
																 PCLZIP_ATT_FILE_NEW_FULL_NAME => basename( $file ) ) ),PCLZIP_OPT_TEMP_FILE_THRESHOLD, 5 )
					)
						trigger_error( sprintf( __( 'Zip archive add error: %s', 'backwpup' ), $zipbackupfile->errorInfo( true ) ), E_USER_ERROR );
					$this->update_working_data();
				}
			}
			if ( $this->jobdata['STEPDONE'] == 0 )
				$this->jobdata['STEPDONE'] = 1;
			//add normal files
			for ( $i = $this->jobdata['STEPDONE'] - 1; $i < $this->jobdata['STEPTODO'] - 1; $i ++ ) {
				$files = $this->get_files_in_folder( $this->jobdata['FOLDERLIST'][$i] );
				$removepath='/';
				if (is_array($files) && strstr($files[0],$this->jobdata['REMOVEPATH']))
					$removepath=$this->jobdata['REMOVEPATH'];
				if ( 0 == $zipbackupfile->add( $files, PCLZIP_OPT_REMOVE_PATH, $removepath ) )
					trigger_error( sprintf( __( 'Zip archive add error: %s', 'backwpup' ), $zipbackupfile->errorInfo( true ) ), E_USER_ERROR );
				$this->update_working_data();
				$this->jobdata['STEPDONE'] ++;
			}
			if ( isset($previous_encoding) )
				mb_internal_encoding( $previous_encoding );
			trigger_error( __( 'Backup zip archive created', 'backwpup' ), E_USER_NOTICE );
			$this->jobdata['STEPSDONE'][] = 'CREATE_ARCHIVE'; //set done

		} elseif ( strtolower( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileformart' ) ) == ".tar.gz" || strtolower( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileformart' ) ) == ".tar.bz2" || strtolower( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileformart' ) ) == ".tar" ) { //tar files
			if ( strtolower( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileformart' ) ) == '.tar.gz' )
				$tarbackup = gzopen( $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'], 'ab9' );
			elseif ( strtolower( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileformart' ) ) == '.tar.bz2' )
				$tarbackup = bzopen( $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'], 'w' );
			else
				$tarbackup = fopen( $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'], 'ab' );
			if ( ! $tarbackup ) {
				trigger_error( __( 'Can not create tar arcive file!', 'backwpup' ), E_USER_ERROR );
				$this->jobdata['STEPSDONE'][] = 'CREATE_ARCHIVE'; //set done
				return;
			} else {
				trigger_error( sprintf( __( '%1$d. Trying to create %2$s archive file...', 'backwpup' ), $this->jobdata['CREATE_ARCHIVE']['STEP_TRY'], substr( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileformart' ), 1 ) ), E_USER_NOTICE );
			}
			//add extra files
			if ( ! empty($this->jobdata['EXTRAFILESTOBACKUP']) && $this->jobdata['STEPDONE'] == 0 ) {
				foreach ( $this->jobdata['EXTRAFILESTOBACKUP'] as $file )
				{
					$this->_tar_file( $file, basename( $file ), $tarbackup );
				}
			}
			if ( $this->jobdata['STEPDONE'] == 0 )
				$this->jobdata['STEPDONE'] = 1;
			//add normal files
			for ( $i = $this->jobdata['STEPDONE'] - 1; $i < $this->jobdata['STEPTODO'] - 1; $i ++ ) {
				$files = $this->get_files_in_folder( $this->jobdata['FOLDERLIST'][$i] );
				if ( count( $files ) > 0 ) {
					foreach ( $files as $file )
					{
						$this->_tar_file( $file, trim(str_replace( $this->jobdata['REMOVEPATH'], '', $file ),'/'), $tarbackup );
					}
				}
				$this->jobdata['STEPDONE'] ++;
				$this->update_working_data();
			}
			// Add 1024 bytes of NULLs to designate EOF
			if ( strtolower( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileformart' ) ) == '.tar.gz' ) {
				gzwrite( $tarbackup, pack( "a1024", "" ) );
				gzclose( $tarbackup );
			} elseif ( strtolower( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileformart' ) ) == '.tar.bz2' ) {
				bzwrite( $tarbackup, pack( "a1024", "" ) );
				bzclose( $tarbackup );
			} else {
				fwrite( $tarbackup, pack( "a1024", "" ) );
				fclose( $tarbackup );
			}
			trigger_error( sprintf( __( '%s archive created', 'backwpup' ), substr( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileformart' ), 1 ) ), E_USER_NOTICE );
		}
		$this->jobdata['STEPSDONE'][]    = 'CREATE_ARCHIVE'; //set done
		$this->jobdata['BACKUPFILESIZE'] = filesize( $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'] );
		if ( $this->jobdata['BACKUPFILESIZE'] )
			trigger_error( sprintf( __( 'Archive size is %s', 'backwpup' ), size_format( $this->jobdata['BACKUPFILESIZE'], 2 ) ), E_USER_NOTICE );
		trigger_error( sprintf( __( '%1$d Files with %2$s in Archive', 'backwpup' ), $this->jobdata['COUNT']['FILES'] + $this->jobdata['COUNT']['FILESINFOLDER'], size_format( $this->jobdata['COUNT']['FILESIZE'] + $this->jobdata['COUNT']['FILESIZEINFOLDER'], 2 ) ), E_USER_NOTICE );
	}

	/**
	 *
	 * Helper for create_archive() to tar files
	 *
	 * @param string   $file	full file path
	 * @param string   $outfile filename in archive
	 * @param resource $handle  of archive
	 */
	private function _tar_file( $file, $outfile, $handle ) {
		$this->need_free_memory( '2M' ); //2MB free memory
		//split filename larger than 100 chars
		if ( strlen( $outfile ) <= 100 ) {
			$filename       = $outfile;
			$filenameprefix = "";
		} else {
			$filenameofset  = strlen( $outfile ) - 100;
			$dividor        = strpos( $outfile, '/', $filenameofset );
			$filename       = substr( $outfile, $dividor + 1 );
			$filenameprefix = substr( $outfile, 0, $dividor );
			if ( strlen( $filename ) > 100 )
				trigger_error( sprintf( __( 'File name "%1$s" to long to save correctly in %2$s archive!', 'backwpup' ), $outfile, substr( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileformart' ), 1 ) ), E_USER_WARNING );
			if ( strlen( $filenameprefix ) > 155 )
				trigger_error( sprintf( __( 'File path "%1$s" to long to save correctly in %2$s archive!', 'backwpup' ), $outfile, substr( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileformart' ), 1 ) ), E_USER_WARNING );
		}
		//get file stat
		$filestat = stat( $file );
		//Set file user/group name if linux
		$fileowner = __( "Unknown", "backwpup" );
		$filegroup = __( "Unknown", "backwpup" );
		if ( function_exists( 'posix_getpwuid' ) ) {
			$info      = posix_getpwuid( $filestat['uid'] );
			$fileowner = $info['name'];
			$info      = posix_getgrgid( $filestat['gid'] );
			$filegroup = $info['name'];
		}
		// Generate the TAR header for this file
		$header = pack( "a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12",
			$filename, //name of file  100
			sprintf( "%07o", $filestat['mode'] ), //file mode  8
			sprintf( "%07o", $filestat['uid'] ), //owner user ID  8
			sprintf( "%07o", $filestat['gid'] ), //owner group ID  8
			sprintf( "%011o", $filestat['size'] ), //length of file in bytes  12
			sprintf( "%011o", $filestat['mtime'] ), //modify time of file  12
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
			"" ); //fill block 512K

		// Computes the unsigned Checksum of a file's header
		$checksum = 0;
		for ( $i = 0; $i < 512; $i ++ )
		{
			$checksum += ord( substr( $header, $i, 1 ) );
		}
		$checksum = pack( "a8", sprintf( "%07o", $checksum ) );
		$header   = substr_replace( $header, $checksum, 148, 8 );
		if ( strtolower( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileformart' ) ) == '.tar.gz' )
			gzwrite( $handle, $header );
		elseif ( strtolower( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileformart' ) ) == '.tar.bz2' )
			bzwrite( $handle, $header );
		else
			fwrite( $handle, $header );
		// read/write files in 512K Blocks
		$fd = fopen( $file, 'rb' );
		while ( ! feof( $fd ) ) {
			$filedata = fread( $fd, 512 );
			if ( strlen( $filedata ) > 0 ) {
				if ( strtolower( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileformart' ) ) == '.tar.gz' )
					gzwrite( $handle, pack( "a512", $filedata ) );
				elseif ( strtolower( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileformart' ) ) == '.tar.bz2' )
					bzwrite( $handle, pack( "a512", $filedata ) );
				else
					fwrite( $handle, pack( "a512", $filedata ) );
			}
		}
		fclose( $fd );
	}

	/**
	 * Backup destination Folder for archives
	 * @return nothing
	 */
	private function dest_folder() {
		$this->jobdata['STEPTODO'] = 1;
		backwpup_update_option( $this->jobdata['JOBMAIN'], 'lastbackupdownloadurl', add_query_arg( array( 'page'  => 'backwpupbackups',
																										  'action'=> 'download',
																										  'file'  => $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'] ), backwpup_admin_url( 'admin.php' ) ) );
		//Delete old Backupfiles
		$backupfilelist = array();
		$filecounter    = 0;
		$files          = array();
		if ( $dir = @opendir( $this->jobdata['BACKUPDIR'] ) ) { //make file list
			while ( ($file = readdir( $dir )) !== false ) {
				if ( is_file( $this->jobdata['BACKUPDIR'] . $file ) ) {
					//list for deletion
					if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileprefix' ) == substr( $file, 0, strlen( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileprefix' ) ) ) )
						$backupfilelist[filemtime( $this->jobdata['BACKUPDIR'] . $file )] = $file;
					//file list for backups
					$files[$filecounter]['folder']      = $this->jobdata['BACKUPDIR'];
					$files[$filecounter]['file']        = $this->jobdata['BACKUPDIR'] . $file;
					$files[$filecounter]['filename']    = $file;
					$files[$filecounter]['downloadurl'] = add_query_arg( array( 'page'  => 'backwpupbackups',
																				'action'=> 'download',
																				'file'  => $this->jobdata['BACKUPDIR'] . $file ), backwpup_admin_url( 'admin.php' ) );
					$files[$filecounter]['filesize']    = filesize( $this->jobdata['BACKUPDIR'] . $file );
					$files[$filecounter]['time']        = filemtime( $this->jobdata['BACKUPDIR'] . $file );
					$filecounter ++;
				}
			}
			@closedir( $dir );

		}
		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'maxbackups' ) > 0 ) {
			if ( count( $backupfilelist ) > backwpup_get_option( $this->jobdata['JOBMAIN'], 'maxbackups' ) ) {
				$numdeltefiles = 0;
				while ( $file = array_shift( $backupfilelist ) ) {
					if ( count( $backupfilelist ) < backwpup_get_option( $this->jobdata['JOBMAIN'], 'maxbackups' ) )
						break;
					unlink( $this->jobdata['BACKUPDIR'] . $file );
					for ( $i = 0; $i < count( $files ); $i ++ ) {
						if ( $files[$i]['file'] == $this->jobdata['BACKUPDIR'] . $file )
							unset($files[$i]);
					}
					$numdeltefiles ++;
				}
				if ( $numdeltefiles > 0 )
					trigger_error( sprintf( _n( 'One backup file deleted', '%d backup files deleted', $numdeltefiles, 'backwpup' ), $numdeltefiles ), E_USER_NOTICE );
			}
		}
		backwpup_update_option( 'temp', $this->jobdata['JOBID'] . '_FOLDER', $files );

		$this->jobdata['STEPDONE'] ++;
		$this->jobdata['STEPSDONE'][] = 'DEST_FOLDER'; //set done
	}

	private function dest_folder_sync() {
		$this->jobdata['STEPTODO'] = count( $this->jobdata['FOLDERLIST'] );
		trigger_error( sprintf( __( '%d. Try to sync files with folder...', 'backwpup' ), $this->jobdata['DEST_FOLDER_SYNC']['STEP_TRY'] ), E_USER_NOTICE );
		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backupsyncnodelete' ) )
			trigger_error( __( 'No files/folder will deleted on destination!', 'backwpup' ) );
		//create not existing folders
		foreach ( $this->jobdata['FOLDERLIST'] as $folder ) {
			$testfolder = str_replace( $this->jobdata['REMOVEPATH'], '', $folder );
			if ( empty($testfolder) )
				continue;
			if ( ! is_dir( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backupdir' ) . $testfolder ) )
				wp_mkdir_p( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backupdir' ) . $testfolder );
		}
		//sync folder by folder
		$this->_dest_folder_sync_files( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backupdir' ) );
		$this->jobdata['STEPSDONE'][] = 'DEST_FOLDER_SYNC'; //set done
	}

	/**
	 * @param string $folder
	 * @param int	$levels
	 *
	 * @return bool
	 */
	private function _dest_folder_sync_files( $folder = '', $levels = 100 ) {
		if ( empty($folder) )
			return false;
		if ( ! $levels )
			return false;
		$this->jobdata['STEPDONE'] ++;
		$this->update_working_data();
		$folder = trailingslashit( $folder );
		//get files to sync
		$filestosync = $this->get_files_in_folder( $this->jobdata['REMOVEPATH'] . trim( str_replace( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backupdir' ), '', $folder ) ) );
		if ( $folder == backwpup_get_option( $this->jobdata['JOBMAIN'], 'backupdir' ) ) //add extra files to sync
			$filestosync = array_merge( $filestosync, $this->jobdata['EXTRAFILESTOBACKUP'] );

		if ( $dir = @opendir( $folder ) ) {
			while ( ($file = readdir( $dir )) !== false ) {
				if ( in_array( $file, array( '.', '..' ) ) )
					continue;
				if ( ! is_readable( $folder . $file ) ) {
					trigger_error( sprintf( __( 'File or folder "%s" is not readable!', 'backwpup' ), $folder . $file ), E_USER_WARNING );
				} elseif ( is_dir( $folder . $file ) ) {
					$this->_dest_folder_sync_files( trailingslashit( $folder . $file ), $levels - 1 );
					if ( ! backwpup_get_option( $this->jobdata['JOBMAIN'], 'backupsyncnodelete' ) ) {
						$testfolder = str_replace( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backupdir' ), '', $folder . $file );
						if ( ! in_array( $this->jobdata['REMOVEPATH'] . $testfolder, $this->jobdata['FOLDERLIST'] ) ) {
							if ( rmdir( $folder . $file ) )
								trigger_error( sprintf( __( 'Folder deleted %s', 'backwpup' ), $folder . $file ) );
						}
					}
				} elseif ( is_file( $folder . $file ) ) {
					$testfile = str_replace( backwpup_get_option( $this->jobdata['JOBMAIN'], 'backupdir' ), '', $folder . $file );
					if ( in_array( $this->jobdata['REMOVEPATH'] . $testfile, $filestosync ) ) {
						if ( filesize( $this->jobdata['REMOVEPATH'] . $testfile ) != filesize( $folder . $file ) )
							copy( $this->jobdata['REMOVEPATH'] . $testfile, $folder . $file );
						foreach ( $filestosync as $key => $keyfile ) {
							if ( $keyfile == $this->jobdata['REMOVEPATH'] . $testfile )
								unset($filestosync[$key]);
						}
					} elseif ( ! backwpup_get_option( $this->jobdata['JOBMAIN'], 'backupsyncnodelete' ) ) {
						if ( unlink( $folder . $file ) )
							trigger_error( sprintf( __( 'File deleted %s', 'backwpup' ), $folder . $file ) );
					}
				}
			}
			@closedir( $dir );
		}
		//sync new files
		foreach ( $filestosync as $keyfile )
		{
			copy( $keyfile, $folder . basename( $keyfile ) );
		}
		return true;
	}

	/**
	 * Backup destination DropBox for archives
	 * @return nothing
	 */
	private function dest_dropbox() {
		$this->jobdata['STEPTODO'] = 2 + $this->jobdata['BACKUPFILESIZE'];
		trigger_error( sprintf( __( '%d. Try to sending backup file to DropBox...', 'backwpup' ), $this->jobdata['DEST_DROPBOX']['STEP_TRY'] ), E_USER_NOTICE );
		try {
			$dropbox = new BackWPup_Dest_Dropbox(backwpup_get_option( $this->jobdata['JOBMAIN'], 'droperoot' ));
			// set the tokens
			$dropbox->setOAuthTokens( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dropetoken' ), backwpup_decrypt(backwpup_get_option( $this->jobdata['JOBMAIN'], 'dropesecret' )) );
			//get account info
			$info = $dropbox->accountInfo();
			if ( ! empty($info['uid']) ) {
				trigger_error( sprintf( __( 'Authed with DropBox from %s', 'backwpup' ), $info['display_name'] . ' (' . $info['email'] . ')' ), E_USER_NOTICE );
			}
			//Check Quota
			$dropboxfreespase = $info['quota_info']['quota'] - $info['quota_info']['shared'] - $info['quota_info']['normal'];
			if ( $this->jobdata['BACKUPFILESIZE'] > $dropboxfreespase ) {
				trigger_error( __( 'No free space left on DropBox!!!', 'backwpup' ), E_USER_ERROR );
				$this->jobdata['STEPSDONE'][] = 'DEST_DROPBOX'; //set done
				return;
			} else {
				trigger_error( sprintf( __( '%s free on DropBox', 'backwpup' ), size_format( $dropboxfreespase, 2 ) ), E_USER_NOTICE );
			}
			//set callback function
			$dropbox->setProgressFunction( array( $this, 'curl_read_callback' ) );
			$this->jobdata['STEPDONE'] = 0;
			// put the file
			trigger_error( __( 'Upload to DropBox now started... ', 'backwpup' ), E_USER_NOTICE );
			$response = $dropbox->upload( $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'], backwpup_get_option( $this->jobdata['JOBMAIN'], 'dropedir' ) . $this->jobdata['BACKUPFILE'] );
			if ( $response['bytes'] == filesize( $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'] ) ) {
				backwpup_update_option( $this->jobdata['JOBMAIN'], 'lastbackupdownloadurl', backwpup_admin_url( 'admin.php' ) . '?page=backwpupbackups&action=downloaddropbox&file=' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'dropedir' ) . $this->jobdata['BACKUPFILE'] . '&jobid=' . $this->jobdata['JOBID'] );
				$this->jobdata['STEPDONE'] ++;
				$this->jobdata['STEPSDONE'][] = 'DEST_DROPBOX'; //set done
				trigger_error( sprintf( __( 'Backup transferred to %s', 'backwpup' ), 'https://api-content.dropbox.com/1/files/' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'droperoot' ) . '/' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'dropedir' ) . $this->jobdata['BACKUPFILE'] ), E_USER_NOTICE );
			} else {
				if ( $response['bytes'] != filesize( $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'] ) )
					trigger_error( __( 'Uploaded file size and local file size not the same!!!', 'backwpup' ), E_USER_ERROR );
				else
					trigger_error( sprintf( __( 'Error on transfer backup to DropBox: %s', 'backwpup' ), $response['error'] ), E_USER_ERROR );
				return;
			}
		} catch ( Exception $e ) {
			$this->error_handler( E_USER_ERROR, sprintf( __( 'DropBox API: %s', 'backwpup' ), htmlentities( $e->getMessage() ) ), $e->getFile(), $e->getLine() );
			return;
		}
		try {
			$backupfilelist = array();
			$filecounter    = 0;
			$files          = array();
			$metadata       = $dropbox->metadata( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dropedir' ) );
			if ( is_array( $metadata ) ) {
				foreach ( $metadata['contents'] as $data ) {
					if ( $data['is_dir'] != true ) {
						$file = basename( $data['path'] );
						if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileprefix' ) == substr( $file, 0, strlen( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileprefix' ) ) ) )
							$backupfilelist[strtotime( $data['modified'] )] = $file;
						$files[$filecounter]['folder']      = "https://api-content.dropbox.com/1/files/" . backwpup_get_option( $this->jobdata['JOBMAIN'], 'droperoot' ) . "/" . dirname( $data['path'] ) . "/";
						$files[$filecounter]['file']        = $data['path'];
						$files[$filecounter]['filename']    = basename( $data['path'] );
						$files[$filecounter]['downloadurl'] = backwpup_admin_url( 'admin.php' ) . '?page=backwpupbackups&action=downloaddropbox&file=' . $data['path'] . '&jobid=' . $this->jobdata['JOBID'];
						$files[$filecounter]['filesize']    = $data['bytes'];
						$files[$filecounter]['time']        = strtotime( $data['modified'] );
						$filecounter ++;
					}
				}
			}
			if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dropemaxbackups' ) > 0 && is_object( $dropbox ) ) { //Delete old backups
				if ( count( $backupfilelist ) > backwpup_get_option( $this->jobdata['JOBMAIN'], 'dropemaxbackups' ) ) {
					$numdeltefiles = 0;
					while ( $file = array_shift( $backupfilelist ) ) {
						if ( count( $backupfilelist ) < backwpup_get_option( $this->jobdata['JOBMAIN'], 'dropemaxbackups' ) )
							break;
						$response = $dropbox->fileopsDelete( backwpup_get_option( $this->jobdata['JOBMAIN'], 'dropedir' ) . $file ); //delete files on Cloud
						if ( $response['is_deleted'] == 'true' ) {
							for ( $i = 0; $i < count( $files ); $i ++ ) {
								if ( $files[$i]['file'] == '/' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'dropedir' ) . $file )
									unset($files[$i]);
							}
							$numdeltefiles ++;
						} else
							trigger_error( sprintf( __( 'Error on delete file on DropBox: %s', 'backwpup' ), $file ), E_USER_ERROR );
					}
					if ( $numdeltefiles > 0 )
						trigger_error( sprintf( _n( 'One file deleted on DropBox', '%d files deleted on DropBox', $numdeltefiles, 'backwpup' ), $numdeltefiles ), E_USER_NOTICE );
				}
			}
			backwpup_update_option( 'temp', $this->jobdata['JOBID'] . '_DROPBOX', $files );
		} catch ( Exception $e ) {
			$this->error_handler( E_USER_ERROR, sprintf( __( 'DropBox API: %s', 'backwpup' ), htmlentities( $e->getMessage() ) ), $e->getFile(), $e->getLine() );
			return;
		}
		$this->jobdata['STEPDONE'] ++;
	}

	/**
	 * Backup destination FTP for archives
	 * @return nothing
	 */
	private function dest_ftp() {
		$this->jobdata['STEPTODO'] = 2;
		trigger_error( sprintf( __( '%d. Try to sending backup file to a FTP Server...', 'backwpup' ), $this->jobdata['DEST_FTP']['STEP_TRY'] ), E_USER_NOTICE );

		$this->need_free_memory( $this->jobdata['BACKUPFILESIZE'] * 1.5 );

		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftpssl' ) ) { //make SSL FTP connection
			if ( function_exists( 'ftp_ssl_connect' ) ) {
				$ftp_conn_id = ftp_ssl_connect( backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftphost' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftphostport' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftptimeout' ) );
				if ( $ftp_conn_id )
					trigger_error( sprintf( __( 'Connected by SSL-FTP to Server: %s', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftphost' ) . ':' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftphostport' ) ), E_USER_NOTICE );
				else {
					trigger_error( sprintf( __( 'Can not connect by SSL-FTP to Server: %s', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftphost' ) . ':' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftphostport' ) ), E_USER_ERROR );
					return false;
				}
			} else {
				trigger_error( __( 'PHP function to connect with SSL-FTP to server not exists!', 'backwpup' ), E_USER_ERROR );
				$this->jobdata['STEPSDONE'][] = 'DEST_FTP'; //set done
				return false;
			}
		} else { //make normal FTP connection if SSL not work
			$ftp_conn_id = ftp_connect( backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftphost' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftphostport' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftptimeout' ) );
			if ( $ftp_conn_id )
				trigger_error( sprintf( __( 'Connected to FTP server: %s', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftphost' ) . ':' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftphostport' ) ), E_USER_NOTICE );
			else {
				trigger_error( sprintf( __( 'Can not connect to FTP server: %s', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftphost' ) . ':' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftphostport' ) ), E_USER_ERROR );
				return false;
			}
		}

		//FTP Login
		trigger_error( sprintf( __( 'FTP Client command: %s', 'backwpup' ), ' USER ' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftpuser' ) ), E_USER_NOTICE );
		if ( $loginok = ftp_login( $ftp_conn_id, backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftpuser' ), backwpup_decrypt( backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftppass' ) ) ) ) {
			trigger_error( sprintf( __( 'FTP Server reply: %s', 'backwpup' ), ' User ' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftpuser' ) . ' logged in.' ), E_USER_NOTICE );
		} else { //if PHP ftp login don't work use raw login
			$return = ftp_raw( $ftp_conn_id, 'USER ' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftpuser' ) );
			trigger_error( sprintf( __( 'FTP Server reply: %s', 'backwpup' ), $return[0] ), E_USER_NOTICE );
			if ( substr( trim( $return[0] ), 0, 3 ) <= 400 ) {
				trigger_error( sprintf( __( 'FTP Client command: %s', 'backwpup' ), ' PASS *******' ), E_USER_NOTICE );
				$return = ftp_raw( $ftp_conn_id, 'PASS ' . backwpup_decrypt( backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftppass' ) ) );
				trigger_error( sprintf( __( 'FTP Server reply: %s', 'backwpup' ), $return[0] ), E_USER_NOTICE );
				if ( substr( trim( $return[0] ), 0, 3 ) <= 400 )
					$loginok = true;
			}
		}
		if ( ! $loginok )
			return false;

		//PASV
		trigger_error( sprintf( __( 'FTP Client command: %s', 'backwpup' ), ' PASV' ), E_USER_NOTICE );
		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftppasv' ) ) {
			if ( ftp_pasv( $ftp_conn_id, true ) )
				trigger_error( sprintf( __( 'FTP Server reply: %s', 'backwpup' ), __( 'Entering Passive Mode', 'backwpup' ) ), E_USER_NOTICE );
			else
				trigger_error( sprintf( __( 'FTP Server reply: %s', 'backwpup' ), __( 'Can not Entering Passive Mode', 'backwpup' ) ), E_USER_WARNING );
		} else {
			if ( ftp_pasv( $ftp_conn_id, false ) )
				trigger_error( sprintf( __( 'FTP Server reply: %s', 'backwpup' ), __( 'Entering Normal Mode', 'backwpup' ) ), E_USER_NOTICE );
			else
				trigger_error( sprintf( __( 'FTP Server reply: %s', 'backwpup' ), __( 'Can not Entering Normal Mode', 'backwpup' ) ), E_USER_WARNING );
		}
		//SYSTYPE
		trigger_error( sprintf( __( 'FTP Client command: %s', 'backwpup' ), ' SYST' ), E_USER_NOTICE );
		$systype = ftp_systype( $ftp_conn_id );
		if ( $systype )
			trigger_error( sprintf( __( 'FTP Server reply: %s', 'backwpup' ), $systype ), E_USER_NOTICE );
		else
			trigger_error( sprintf( __( 'FTP Server reply: %s', 'backwpup' ), __( 'Error getting SYSTYPE', 'backwpup' ) ), E_USER_ERROR );

		if ( $this->jobdata['STEPDONE'] == 0 ) {
			//test ftp dir and create it if not exists
			$ftpdirs = explode( "/", rtrim( backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftpdir' ), '/' ) );
			foreach ( $ftpdirs as $ftpdir ) {
				if ( empty($ftpdir) )
					continue;
				if ( ! @ftp_chdir( $ftp_conn_id, $ftpdir ) ) {
					if ( @ftp_mkdir( $ftp_conn_id, $ftpdir ) ) {
						trigger_error( sprintf( __( 'FTP Folder "%s" created!', 'backwpup' ), $ftpdir ), E_USER_NOTICE );
						ftp_chdir( $ftp_conn_id, $ftpdir );
					} else {
						trigger_error( sprintf( __( 'FTP Folder "%s" can not created!', 'backwpup' ), $ftpdir ), E_USER_ERROR );
						return false;
					}
				}
			}
			trigger_error( __( 'Upload to FTP now started ... ', 'backwpup' ), E_USER_NOTICE );
			if ( ftp_put( $ftp_conn_id, backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftpdir' ) . $this->jobdata['BACKUPFILE'], $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'], FTP_BINARY ) ) { //transfer file
				$this->jobdata['STEPTODO'] = 1 + $this->jobdata['BACKUPFILESIZE'];
				trigger_error( sprintf( __( 'Backup transferred to FTP server: %s', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftpdir' ) . $this->jobdata['BACKUPFILE'] ), E_USER_NOTICE );
				backwpup_update_option( $this->jobdata['JOBMAIN'], 'lastbackupdownloadurl', "ftp://" . backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftpuser' ) . ":" . backwpup_decrypt( backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftppass' ) ) . "@" . backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftphost' ) . backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftpdir' ) . $this->jobdata['BACKUPFILE'] );
				$this->jobdata['STEPSDONE'][] = 'DEST_FTP'; //set done
			} else
				trigger_error( __( 'Can not transfer backup to FTP server!', 'backwpup' ), E_USER_ERROR );
		}


		$backupfilelist = array();
		$filecounter    = 0;
		$files          = array();
		if ( $filelist = ftp_nlist( $ftp_conn_id, backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftpdir' ) ) ) {
			foreach ( $filelist as $file ) {
				if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileprefix' ) == substr( basename( $file ), 0, strlen( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileprefix' ) ) ) ) {
					$time = ftp_mdtm( $ftp_conn_id, $file );
					if ( ! isset($time) || $time == - 1 ) {
						$timestring = str_replace( array( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileprefix' ), '.tar.gz', '.tar.bz2', '.tar', '.zip' ), '', basename( $file ) );
						list($dateex, $timeex) = explode( '_', $timestring );
						$time = strtotime( $dateex . ' ' . str_replace( '-', ':', $timeex ) );
					}
					$backupfilelist[$time] = basename( $file );
				}
				if ( basename( $file ) != '.' && basename( $file ) != '..' ) {
					$files[$filecounter]['folder']      = "ftp://" . backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftphost' ) . ':' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftphostport' ) . dirname( $file ) . "/";
					$files[$filecounter]['file']        = $file;
					$files[$filecounter]['filename']    = basename( $file );
					$files[$filecounter]['downloadurl'] = "ftp://" . rawurlencode( backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftpuser' ) ) . ":" . rawurlencode( backwpup_decrypt( backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftppass' ) ) ) . "@" . backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftphost' ) . ':' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftphostport' ) . rawurlencode( $file );
					$files[$filecounter]['filesize']    = ftp_size( $ftp_conn_id, $file );
					$files[$filecounter]['time']        = ftp_mdtm( $ftp_conn_id, $file );
					$filecounter ++;
				}
			}
		}
		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftpmaxbackups' ) > 0 ) { //Delete old backups
			if ( count( $backupfilelist ) > backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftpmaxbackups' ) ) {
				$numdeltefiles = 0;
				while ( $file = array_shift( $backupfilelist ) ) {
					if ( count( $backupfilelist ) < backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftpmaxbackups' ) )
						break;
					if ( ftp_delete( $ftp_conn_id, backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftpdir' ) . $file ) ) { //delete files on ftp
						for ( $i = 0; $i < count( $files ); $i ++ ) {
							if ( $files[$i]['file'] == backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftpdir' ) . $file )
								unset($files[$i]);
						}
						$numdeltefiles ++;
					}
					else
						trigger_error( sprintf( __( 'Can not delete "%s" on FTP server!', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'ftpdir' ) . $file ), E_USER_ERROR );

				}
				if ( $numdeltefiles > 0 )
					trigger_error( sprintf( _n( 'One file deleted on FTP Server', '%d files deleted on FTP Server', $numdeltefiles, 'backwpup' ), $numdeltefiles ), E_USER_NOTICE );
			}
		}
		backwpup_update_option( 'temp', $this->jobdata['JOBID'] . '_FTP', $files );

		ftp_close( $ftp_conn_id );
		$this->jobdata['STEPDONE'] ++;
		return true;
	}

	/**
	 * Backup destination Amazon S3 for archives
	 * @return nothing
	 */
	private function dest_s3() {
		$this->jobdata['STEPTODO'] = 2 + $this->jobdata['BACKUPFILESIZE'];
		trigger_error( sprintf( __( '%d. Try to sending backup file to Amazon S3...', 'backwpup' ), $this->jobdata['DEST_S3']['STEP_TRY'] ), E_USER_NOTICE );
		try {
			$s3 = new AmazonS3(array( 'key'				  => backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsAccessKey' ),
									  'secret'			   => backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsSecretKey' ),
									  'certificate_authority'=> true ));
			if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsdisablessl' ) )
				$s3->disable_ssl( true );
			if ( $s3->if_bucket_exists( backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsBucket' ) ) ) {
				$bucketregion = $s3->get_bucket_region( backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsBucket' ) );
				trigger_error( sprintf( __( 'Connected to S3 Bucket "%1$s" in %2$s', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsBucket' ), $bucketregion->body ), E_USER_NOTICE );
			} else {
				trigger_error( sprintf( __( 'S3 Bucket "%s" not exists!', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsBucket' ) ), E_USER_ERROR );
				$this->jobdata['STEPSDONE'][] = 'DEST_S3'; //set done
				return;
			}
			//create Parameter
			$params               = array();
			$params['fileUpload'] = $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'];
			$params['acl']        = AmazonS3::ACL_PRIVATE;
			if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsssencrypt' ) )
				$params['encryption'] = backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsssencrypt' );
			if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsrrs' ) ) //set reduced redundancy or not
				$params['storage'] = AmazonS3::STORAGE_REDUCED;
			else
				$params['storage'] = AmazonS3::STORAGE_STANDARD;
			$s3->register_streaming_read_callback( array( $this, 'curl_aws_read_callback' ) );
			$this->jobdata['STEPDONE'] = 0;
			//transfer file to S3
			trigger_error( __( 'Upload to Amazon S3 now started... ', 'backwpup' ), E_USER_NOTICE );
			$result = $s3->create_object( backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsBucket' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsdir' ) . $this->jobdata['BACKUPFILE'], $params );
			$result = (array) $result;
			if ( $result["status"] = 200 && $result["status"] < 300 ) {
				$this->jobdata['STEPTODO'] = 1 + $this->jobdata['BACKUPFILESIZE'];
				trigger_error( sprintf( __( 'Backup transferred to %s', 'backwpup' ), $result["header"]["_info"]["url"] ), E_USER_NOTICE );
				backwpup_update_option( $this->jobdata['JOBMAIN'], 'lastbackupdownloadurl', backwpup_admin_url( 'admin.php' ) . '?page=backwpupbackups&action=downloads3&file=' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsdir' ) . $this->jobdata['BACKUPFILE'] . '&jobid=' . $this->jobdata['JOBID'] );
				$this->jobdata['STEPSDONE'][] = 'DEST_S3'; //set done
			} else {
				trigger_error( sprintf( __( 'Can not transfer backup to S3! (%1$d) %2$s', 'backwpup' ), $result["status"], $result["Message"] ), E_USER_ERROR );
			}
			$s3->register_streaming_read_callback( NULL );
		} catch ( Exception $e ) {
			$this->error_handler( E_USER_ERROR, sprintf( __( 'Amazon API: %s', 'backwpup' ), htmlentities( $e->getMessage() ) ), $e->getFile(), $e->getLine() );
			return;
		}
		try {
			if ( $s3->if_bucket_exists( backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsBucket' ) ) ) {
				$backupfilelist = array();
				$filecounter    = 0;
				$files          = array();
				if ( ($contents = $s3->list_objects( backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsBucket' ), array( 'prefix'=> backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsdir' ) ) )) !== false ) {
					foreach ( $contents->body->Contents as $object ) {
						$file       = basename( $object->Key );
						$changetime = strtotime( $object->LastModified );
						if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileprefix' ) == substr( $file, 0, strlen( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileprefix' ) ) ) )
							$backupfilelist[$changetime] = $file;
						$files[$filecounter]['folder']      = "https://" . backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsBucket' ) . ".s3.amazonaws.com/" . dirname( (string) $object->Key ) . '/';
						$files[$filecounter]['file']        = (string) $object->Key;
						$files[$filecounter]['filename']    = basename( $object->Key );
						$files[$filecounter]['downloadurl'] = backwpup_admin_url( 'admin.php' ) . '?page=backwpupbackups&action=downloads3&file=' . $object->Key . '&jobid=' . $this->jobdata['JOBID'];
						$files[$filecounter]['filesize']    = (string) $object->Size;
						$files[$filecounter]['time']        = $changetime;
						$filecounter ++;
					}
				}
				if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsmaxbackups' ) > 0 ) { //Delete old backups
					if ( count( $backupfilelist ) > backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsmaxbackups' ) ) {
						$numdeltefiles = 0;
						while ( $file = array_shift( $backupfilelist ) ) {
							if ( count( $backupfilelist ) < backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsmaxbackups' ) )
								break;
							if ( $s3->delete_object( backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsBucket' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsdir' ) . $file ) ) { //delete files on S3
								for ( $i = 0; $i < count( $files ); $i ++ ) {
									if ( $files[$i]['file'] == backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsdir' ) . $file )
										unset($files[$i]);
								}
								$numdeltefiles ++;
							}
							else
								trigger_error( sprintf( __( 'Can not delete backup on S3://%s', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsBucket' ) . '/' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'awsdir' ) . $file ), E_USER_ERROR );
						}
						if ( $numdeltefiles > 0 )
							trigger_error( sprintf( _n( 'One file deleted on S3 Bucket', '%d files deleted on S3 Bucket', $numdeltefiles, 'backwpup' ), $numdeltefiles ), E_USER_NOTICE );
					}
				}
				backwpup_update_option( 'temp', $this->jobdata['JOBID'] . '_S3', $files );
			}
		} catch ( Exception $e ) {
			$this->error_handler( E_USER_ERROR, sprintf( __( 'Amazon API: %s', 'backwpup' ), htmlentities( $e->getMessage() ) ), $e->getFile(), $e->getLine() );
			return;
		}
		$this->jobdata['STEPDONE'] ++;
	}

	/**
	 * Backup destination Google storage for archives
	 * @return nothing
	 */
	private function dest_gstorage() {
		$this->jobdata['STEPTODO'] = 2 + $this->jobdata['BACKUPFILESIZE'];
		trigger_error( sprintf( __( '%d. Try to sending backup file to Google Storage...', 'backwpup' ), $this->jobdata['DEST_GSTORAGE']['STEP_TRY'] ), E_USER_NOTICE );
		try {
			$gstorage = new AmazonS3(array( 'key'				  => backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStorageAccessKey' ),
											'secret'			   => backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStorageSecret' ),
											'certificate_authority'=> true ));
			$gstorage->set_hostname( 'commondatastorage.googleapis.com' );
			$gstorage->allow_hostname_override( false );
			if ( $gstorage->if_bucket_exists( backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStorageBucket' ) ) ) {
				trigger_error( sprintf( __( 'Connected to Google Storage Bucket "%1$s"', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStorageBucket' ) ), E_USER_NOTICE );
			} else {
				trigger_error( sprintf( __( 'Google Storage Bucket "%s" not exists!', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStorageBucket' ) ), E_USER_ERROR );
				$this->jobdata['STEPSDONE'][] = 'DEST_GSTORAGE'; //set done
				return;
			}
			//create Parameter
			$params               = array();
			$params['fileUpload'] = $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'];
			$params['acl']        = AmazonS3::ACL_PRIVATE;
			$gstorage->register_streaming_read_callback( array( $this, 'curl_aws_read_callback' ) );
			$this->jobdata['STEPDONE'] = 0;
			//transfer file to Google Storage
			trigger_error( __( 'Upload to Google Storage now started... ', 'backwpup' ), E_USER_NOTICE );
			$result = $gstorage->create_object( backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStorageBucket' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStoragedir' ) . $this->jobdata['BACKUPFILE'], $params );
			$result = (array) $result;
			if ( $result["status"] = 200 && $result["status"] < 300 ) {
				$this->jobdata['STEPTODO'] = 1 + $this->jobdata['BACKUPFILESIZE'];
				trigger_error( sprintf( __( 'Backup transferred to %s', 'backwpup' ), $result["header"]["_info"]["url"] ), E_USER_NOTICE );
				backwpup_update_option( $this->jobdata['JOBMAIN'], 'lastbackupdownloadurl', "https://sandbox.google.com/storage/" . backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStorageBucket' ) . "/" . backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStoragedir' ) . $this->jobdata['BACKUPFILE'] );
				$this->jobdata['STEPSDONE'][] = 'DEST_GSTORAGE'; //set done
			} else {
				trigger_error( sprintf( __( 'Can not transfer backup to Google Storage! (%1$d) %2$s', 'backwpup' ), $result["status"], $result["Message"] ), E_USER_ERROR );
			}
			$gstorage->register_streaming_read_callback( NULL );
		} catch ( Exception $e ) {
			$this->error_handler( E_USER_ERROR, sprintf( __( 'Google Storage API: %s', 'backwpup' ), htmlentities( $e->getMessage() ) ), $e->getFile(), $e->getLine() );
			return;
		}
		try {
			if ( $gstorage->if_bucket_exists( backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStorageBucket' ) ) ) {
				$backupfilelist = array();
				$filecounter    = 0;
				$files          = array();
				if ( ($contents = $gstorage->list_objects( backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStorageBucket' ), array( 'prefix'=> backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStoragedir' ) ) )) !== false ) {
					foreach ( $contents->body->Contents as $object ) {
						$file       = basename( $object->Key );
						$changetime = strtotime( $object->LastModified );
						if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileprefix' ) == substr( $file, 0, strlen( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileprefix' ) ) ) )
							$backupfilelist[$changetime] = $file;
						$files[$filecounter]['folder']      = "https://sandbox.google.com/storage/" . backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStorageBucket' ) . "/" . dirname( (string) $object->Key ) . '/';
						$files[$filecounter]['file']        = (string) $object->Key;
						$files[$filecounter]['filename']    = basename( $object->Key );
						$files[$filecounter]['downloadurl'] = "https://sandbox.google.com/storage/" . backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStorageBucket' ) . "/" . (string) $object->Key;
						$files[$filecounter]['filesize']    = (string) $object->Size;
						$files[$filecounter]['time']        = $changetime;
						$filecounter ++;
					}
				}
				if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStoragemaxbackups' ) > 0 ) { //Delete old backups
					if ( count( $backupfilelist ) > backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStoragemaxbackups' ) ) {
						$numdeltefiles = 0;
						while ( $file = array_shift( $backupfilelist ) ) {
							if ( count( $backupfilelist ) < backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStoragemaxbackups' ) )
								break;
							if ( $gstorage->delete_object( backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStorageBucket' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStoragedir' ) . $file ) ) { //delete files on Google Storage
								for ( $i = 0; $i < count( $files ); $i ++ ) {
									if ( $files[$i]['file'] == backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStoragedir' ) . $file )
										unset($files[$i]);
								}
								$numdeltefiles ++;
							}
							else
								trigger_error( sprintf( __( 'Can not delete backup on Google Storage://%s', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStorageBucket' ) . '/' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'GStoragedir' ) . $file ), E_USER_ERROR );
						}
						if ( $numdeltefiles > 0 )
							trigger_error( sprintf( _n( 'One file deleted on Google Storage Bucket', '%d files deleted on Google Storage Bucket', $numdeltefiles, 'backwpup' ), $numdeltefiles ), E_USER_NOTICE );
					}
				}
				backwpup_update_option( 'temp', $this->jobdata['JOBID'] . '_GSTORAGE', $files );
			}
		} catch ( Exception $e ) {
			$this->error_handler( E_USER_ERROR, sprintf( __( 'Google Storage API: %s', 'backwpup' ), htmlentities( $e->getMessage() ) ), $e->getFile(), $e->getLine() );
			return;
		}
		$this->jobdata['STEPDONE'] ++;
	}

	/**
	 * Backup destination Mail for archives
	 * @return nothing
	 */
	private function dest_mail() {
		$this->jobdata['STEPTODO'] = 1;
		trigger_error( sprintf( __( '%d. Try to sending backup with mail...', 'backwpup' ), $this->jobdata['DEST_MAIL']['STEP_TRY'] ), E_USER_NOTICE );

		//check file Size
		if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'mailefilesize' ) ) {
			if ( $this->jobdata['BACKUPFILESIZE'] > backwpup_get_option( $this->jobdata['JOBMAIN'], 'mailefilesize' ) * 1024 * 1024 ) {
				trigger_error( __( 'Backup archive too big for sending by mail!', 'backwpup' ), E_USER_ERROR );
				$this->jobdata['STEPDONE']    = 1;
				$this->jobdata['STEPSDONE'][] = 'DEST_MAIL'; //set done
				return;
			}
		}

		trigger_error( __( 'Sending mail...', 'backwpup' ), E_USER_NOTICE );
		if ( backwpup_get_option( 'cfg', 'mailsndname' ) )
			$headers = 'From: ' . backwpup_get_option( 'cfg', 'mailsndname' ) . ' <' . backwpup_get_option( 'cfg', 'mailsndemail' ) . '>' . "\r\n";
		else
			$headers = 'From: ' . backwpup_get_option( 'cfg', 'mailsndemail' ) . "\r\n";

		$this->need_free_memory( $this->jobdata['BACKUPFILESIZE'] * 5 );
		$mail = wp_mail( backwpup_get_option( $this->jobdata['JOBMAIN'], 'mailaddress' ),
			sprintf( __( 'BackWPup archive from %1$s: %2$s', 'backwpup' ), date_i18n( 'd-M-Y H:i', backwpup_get_option( $this->jobdata['JOBMAIN'], 'starttime' ) ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'name' ) ),
			sprintf( __( 'Backup archive: %s', 'backwpup' ), $this->jobdata['BACKUPFILE'] ),
			$headers, array( $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'] ) );

		if ( ! $mail ) {
			trigger_error( __( 'Error on sending mail!', 'backwpup' ), E_USER_ERROR );
		} else {
			$this->jobdata['STEPTODO'] = $this->jobdata['BACKUPFILESIZE'];
			trigger_error( __( 'Mail sent.', 'backwpup' ), E_USER_NOTICE );
		}
		$this->jobdata['STEPSDONE'][] = 'DEST_MAIL'; //set done
	}

	/**
	 * Backup destination Microsoft Azure for archives
	 * @return nothing
	 */
	private function dest_msazure() {
		$this->jobdata['STEPTODO'] = 2;
		trigger_error( sprintf( __( '%d. Try sending backup to a Microsoft Azure (Blob)...', 'backwpup' ), $this->jobdata['DEST_MSAZURE']['STEP_TRY'] ), E_USER_NOTICE );
		try {
			$storageClient = new Microsoft_WindowsAzure_Storage_Blob(backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazureHost' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazureAccName' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazureKey' ));

			if ( ! $storageClient->containerExists( backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazureContainer' ) ) ) {
				trigger_error( sprintf( __( 'Microsoft Azure container "%s" not exists!', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazureContainer' ) ), E_USER_ERROR );
				return;
			} else {
				trigger_error( sprintf( __( 'Connected to Microsoft Azure container "%s"', 'backwpup' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazureContainer' ) ), E_USER_NOTICE );
			}

			trigger_error( __( 'Upload to MS Azure now started... ', 'backwpup' ), E_USER_NOTICE );
			$this->need_free_memory( $this->jobdata['BACKUPFILESIZE'] * 2.5 );
			$result = $storageClient->putBlob( backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazureContainer' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazuredir' ) . $this->jobdata['BACKUPFILE'], $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'] );

			if ( $result->Name == backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazuredir' ) . $this->jobdata['BACKUPFILE'] ) {
				$this->jobdata['STEPTODO'] ++;
				trigger_error( sprintf( __( 'Backup transferred to %s', 'backwpup' ), 'https://' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazureAccName' ) . '.' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazureHost' ) . '/' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazuredir' ) . $this->jobdata['BACKUPFILE'] ), E_USER_NOTICE );
				backwpup_update_option( $this->jobdata['JOBMAIN'], 'lastbackupdownloadurl', backwpup_admin_url( 'admin.php' ) . '?page=backwpupbackups&action=downloadmsazure&file=' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazuredir' ) . $this->jobdata['BACKUPFILE'] . '&jobid=' . $this->jobdata['JOBID'] );
				$this->jobdata['STEPSDONE'][] = 'DEST_MSAZURE'; //set done
			} else {
				trigger_error( __( 'Can not transfer backup to Microsoft Azure!', 'backwpup' ), E_USER_ERROR );
			}

			$backupfilelist = array();
			$filecounter    = 0;
			$files          = array();
			$blobs          = $storageClient->listBlobs( backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazureContainer' ), untrailingslashit( backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazuredir' ) ) );
			if ( is_array( $blobs ) ) {
				foreach ( $blobs as $blob ) {
					$file = basename( $blob->Name );
					if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileprefix' ) == substr( $file, 0, strlen( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileprefix' ) ) ) )
						$backupfilelist[strtotime( $blob->lastmodified )] = $file;
					$files[$filecounter]['folder']      = "https://" . backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazureAccName' ) . '.' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazureHost' ) . "/" . backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazureContainer' ) . "/" . dirname( $blob->Name ) . "/";
					$files[$filecounter]['file']        = $blob->Name;
					$files[$filecounter]['filename']    = basename( $blob->Name );
					$files[$filecounter]['downloadurl'] = backwpup_admin_url( 'admin.php' ) . '?page=backwpupbackups&action=downloadmsazure&file=' . $blob->Name . '&jobid=' . $this->jobdata['JOBID'];
					$files[$filecounter]['filesize']    = $blob->size;
					$files[$filecounter]['time']        = strtotime( $blob->lastmodified );
					$filecounter ++;
				}
			}
			if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazuremaxbackups' ) > 0 ) { //Delete old backups
				if ( count( $backupfilelist ) > backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazuremaxbackups' ) ) {
					$numdeltefiles = 0;
					while ( $file = array_shift( $backupfilelist ) ) {
						if ( count( $backupfilelist ) < backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazuremaxbackups' ) )
							break;
						$storageClient->deleteBlob( backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazureContainer' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazuredir' ) . $file ); //delete files on Google Storage
						for ( $i = 0; $i < count( $files ); $i ++ ) {
							if ( $files[$i]['file'] == backwpup_get_option( $this->jobdata['JOBMAIN'], 'msazuredir' ) . $file )
								unset($files[$i]);
						}
						$numdeltefiles ++;
					}
					if ( $numdeltefiles > 0 )
						trigger_error( sprintf( _n( 'One file deleted on Microsoft Azure container', '%d files deleted on Microsoft Azure container', $numdeltefiles, 'backwpup' ), $numdeltefiles ), E_USER_NOTICE );
				}
			}
			backwpup_update_option( 'temp', $this->jobdata['JOBID'] . '_MSAZURE', $files );
		} catch ( Exception $e ) {
			$this->error_handler( E_USER_ERROR, sprintf( __( 'Microsoft Azure API: %s', 'backwpup' ), htmlentities( $e->getMessage() ) ), $e->getFile(), $e->getLine() );
			return;
		}
		$this->jobdata['STEPDONE'] ++;
	}

	/**
	 * Backup destination rackspace cloud for archives
	 * @return nothing
	 */
	private function dest_rsc() {
		$this->jobdata['STEPTODO'] = 2 + $this->jobdata['BACKUPFILESIZE'];
		$this->jobdata['STEPDONE'] = 0;
		trigger_error( sprintf( __( '%d. Try to sending backup file to Rackspace cloud...', 'backwpup' ), $this->jobdata['DEST_RSC']['STEP_TRY'] ), E_USER_NOTICE );

		$auth = new CF_Authentication(backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscUsername' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscAPIKey' ));
		try {
			if ( $auth->authenticate() )
				trigger_error( __( 'Connected to Rackspase cloud ...', 'backwpup' ), E_USER_NOTICE );
			$conn = new CF_Connection($auth);
			$conn->set_write_progress_function( array( $this, 'curl_read_callback' ) );
			$is_container = false;
			$containers   = $conn->get_containers();
			foreach ( $containers as $container ) {
				if ( $container->name == backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscContainer' ) )
					$is_container = true;
			}
			if ( ! $is_container ) {
				$public_container = $conn->create_container( backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscContainer' ) );
				$public_container->make_private();
				if ( empty($public_container) )
					$is_container = false;
			}
		} catch ( Exception $e ) {
			$this->error_handler( E_USER_ERROR, sprintf( __( 'Rackspase Cloud API: %s', 'backwpup' ), htmlentities( $e->getMessage() ) ), $e->getFile(), $e->getLine() );
			return;
		}

		if ( ! $is_container ) {
			trigger_error( __( 'Rackspase cloud Container not exists:', 'backwpup' ) . ' ' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscContainer' ), E_USER_ERROR );
			$this->jobdata['STEPSDONE'][] = 'DEST_RSC'; //set done
			return;
		}

		try {
			//Transfer Backup to Rackspace Cloud
			$backwpupcontainer            = $conn->get_container( backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscContainer' ) );
			$backwpupbackup               = $backwpupcontainer->create_object( backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscdir' ) . $this->jobdata['BACKUPFILE'] );
			$backwpupbackup->content_type = $this->get_mime_type( $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'] );
			$this->jobdata['STEPDONE']    = 0;
			trigger_error( __( 'Upload to Rackspase cloud now started ... ', 'backwpup' ), E_USER_NOTICE );
			if ( $backwpupbackup->load_from_filename( $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'] ) ) {
				$this->jobdata['STEPTODO'] = 1 + $this->jobdata['BACKUPFILESIZE'];
				trigger_error( __( 'Backup File transferred to RSC://', 'backwpup' ) . backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscContainer' ) . '/' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscdir' ) . $this->jobdata['BACKUPFILE'], E_USER_NOTICE );
				backwpup_update_option( $this->jobdata['JOBMAIN'], 'lastbackupdownloadurl', backwpup_admin_url( 'admin.php' ) . '?page=backwpupbackups&action=downloadrsc&file=' . backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscdir' ) . $this->jobdata['BACKUPFILE'] . '&jobid=' . $this->jobdata['JOBID'] );
				$this->jobdata['STEPSDONE'][] = 'DEST_RSC'; //set done
			} else {
				trigger_error( __( 'Can not transfer backup to Rackspase cloud.', 'backwpup' ), E_USER_ERROR );
			}
		} catch ( Exception $e ) {
			$this->error_handler( E_USER_ERROR, sprintf( __( 'Rackspase Cloud API: %s', 'backwpup' ), htmlentities( $e->getMessage() ) ), $e->getFile(), $e->getLine() );
			return;
		}
		try {
			$backupfilelist = array();
			$filecounter    = 0;
			$files          = array();
			$contents       = $backwpupcontainer->get_objects( 0, NULL, NULL, backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscdir' ) );
			if ( is_array( $contents ) ) {
				foreach ( $contents as $object ) {
					$file = basename( $object->name );
					if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscdir' ) . $file == $object->name ) { //only in the folder and not in complete bucket
						if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileprefix' ) == substr( $file, 0, strlen( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileprefix' ) ) ) )
							$backupfilelist[strtotime( $object->last_modified )] = $file;
					}
					$files[$filecounter]['folder']      = "RSC://" . backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscContainer' ) . "/" . dirname( $object->name ) . "/";
					$files[$filecounter]['file']        = $object->name;
					$files[$filecounter]['filename']    = basename( $object->name );
					$files[$filecounter]['downloadurl'] = backwpup_admin_url( 'admin.php' ) . '?page=backwpupbackups&action=downloadrsc&file=' . $object->name . '&jobid=' . $this->jobdata['JOBID'];
					$files[$filecounter]['filesize']    = $object->content_length;
					$files[$filecounter]['time']        = strtotime( $object->last_modified );
					$filecounter ++;
				}
			}
			if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscmaxbackups' ) > 0 ) { //Delete old backups
				if ( count( $backupfilelist ) > backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscmaxbackups' ) ) {
					$numdeltefiles = 0;
					while ( $file = array_shift( $backupfilelist ) ) {
						if ( count( $backupfilelist ) < backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscmaxbackups' ) )
							break;
						if ( $backwpupcontainer->delete_object( backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscdir' ) . $file ) ) { //delete files on Cloud
							for ( $i = 0; $i < count( $files ); $i ++ ) {
								if ( $files[$i]['file'] == backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscdir' ) . $file )
									unset($files[$i]);
							}
							$numdeltefiles ++;
						} else
							trigger_error( __( 'Can not delete file on RSC://', 'backwpup' ) . backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscContainer' ) . backwpup_get_option( $this->jobdata['JOBMAIN'], 'rscdir' ) . $file, E_USER_ERROR );
					}
					if ( $numdeltefiles > 0 )
						trigger_error( sprintf( _n( 'One file deleted on Rackspase cloud container', '%d files deleted on Rackspase cloud container', $numdeltefiles, 'backwpup' ), $numdeltefiles ), E_USER_NOTICE );
				}
			}
			backwpup_update_option( 'temp', $this->jobdata['JOBID'] . '_RSC', $files );
		} catch ( Exception $e ) {
			$this->error_handler( E_USER_ERROR, sprintf( __( 'Rackspase Cloud API: %s', 'backwpup' ), htmlentities( $e->getMessage() ) ), $e->getFile(), $e->getLine() );
			return;
		}
		$this->jobdata['STEPDONE'] ++;
	}

	/**
	 * Backup destination SugarSync for archives
	 * @return nothing
	 */
	private function dest_sugarsync() {
		$this->jobdata['STEPTODO'] = 2 + $this->jobdata['BACKUPFILESIZE'];
		trigger_error( sprintf( __( '%d. Try to sending backup to SugarSync...', 'backwpup' ), $this->jobdata['DEST_SUGARSYNC']['STEP_TRY'] ), E_USER_NOTICE );
		try {
			$sugarsync = new BackWPup_Dest_SugarSync(backwpup_get_option( $this->jobdata['JOBMAIN'], 'sugaruser' ), backwpup_decrypt( backwpup_get_option( $this->jobdata['JOBMAIN'], 'sugarpass' ) ));
			//Check Quota
			$user = $sugarsync->user();
			if ( ! empty($user->nickname) )
				trigger_error( sprintf( __( 'Authed to SugarSync with Nick %s', 'backwpup' ), $user->nickname ), E_USER_NOTICE );
			$sugarsyncfreespase = (float) $user->quota->limit - (float) $user->quota->usage; //float fixes bug for display of no free space
			if ( $this->jobdata['BACKUPFILESIZE'] > $sugarsyncfreespase ) {
				trigger_error( __( 'No free space left on SugarSync!!!', 'backwpup' ), E_USER_ERROR );
				$this->jobdata['STEPTODO']    = 1 + $this->jobdata['BACKUPFILESIZE'];
				$this->jobdata['STEPSDONE'][] = 'DEST_SUGARSYNC'; //set done
				return;
			} else {
				trigger_error( sprintf( __( '%s free on SugarSync', 'backwpup' ), size_format( $sugarsyncfreespase, 2 ) ), E_USER_NOTICE );
			}
			//Create and change folder
			$sugarsync->mkdir( backwpup_get_option( $this->jobdata['JOBMAIN'], 'sugardir' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'sugarroot' ) );
			$dirid = $sugarsync->chdir( backwpup_get_option( $this->jobdata['JOBMAIN'], 'sugardir' ), backwpup_get_option( $this->jobdata['JOBMAIN'], 'sugarroot' ) );
			//Upload to SugarSync
			$sugarsync->setProgressFunction( array( $this, 'curl_read_callback' ) );
			$this->jobdata['STEPDONE'] = 0;
			trigger_error( __( 'Upload to SugarSync now started... ', 'backwpup' ), E_USER_NOTICE );
			$reponse = $sugarsync->upload( $this->jobdata['BACKUPDIR'] . $this->jobdata['BACKUPFILE'] );
			if ( is_object( $reponse ) ) {
				backwpup_update_option( $this->jobdata['JOBMAIN'], 'lastbackupdownloadurl', backwpup_admin_url( 'admin.php' ) . '?page=backwpupbackups&action=downloadsugarsync&file=' . (string) $reponse . '&jobid=' . $this->jobdata['JOBID'] );
				$this->jobdata['STEPDONE'] ++;
				$this->jobdata['STEPSDONE'][] = 'DEST_SUGARSYNC'; //set done
				trigger_error( sprintf( __( 'Backup transferred to %s', 'backwpup' ), 'https://' . $user->nickname . '.sugarsync.com/' . $sugarsync->showdir( $dirid ) . $this->jobdata['BACKUPFILE'] ), E_USER_NOTICE );
			} else {
				trigger_error( __( 'Can not transfer backup to SugarSync!', 'backwpup' ), E_USER_ERROR );
				return;
			}

			$backupfilelist = array();
			$files          = array();
			$filecounter    = 0;
			$dir            = $sugarsync->showdir( $dirid );
			$getfiles       = $sugarsync->getcontents( 'file' );
			if ( is_object( $getfiles ) ) {
				foreach ( $getfiles->file as $getfile ) {
					$getfile->displayName = utf8_decode( (string) $getfile->displayName );
					if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileprefix' ) == substr( $getfile->displayName, 0, strlen( backwpup_get_option( $this->jobdata['JOBMAIN'], 'fileprefix' ) ) ) )
						$backupfilelist[strtotime( (string) $getfile->lastModified )] = (string) $getfile->ref;
					$files[$filecounter]['folder']      = 'https://' . (string) $user->nickname . '.sugarsync.com/' . $dir;
					$files[$filecounter]['file']        = (string) $getfile->ref;
					$files[$filecounter]['filename']    = (string) $getfile->displayName;
					$files[$filecounter]['downloadurl'] = backwpup_admin_url( 'admin.php' ) . '?page=backwpupbackups&action=downloadsugarsync&file=' . (string) $getfile->ref . '&jobid=' . $this->jobdata['JOBID'];
					$files[$filecounter]['filesize']    = (int) $getfile->size;
					$files[$filecounter]['time']        = strtotime( (string) $getfile->lastModified );
					$filecounter ++;
				}
			}
			if ( backwpup_get_option( $this->jobdata['JOBMAIN'], 'sugarmaxbackups' ) > 0 ) { //Delete old backups
				if ( count( $backupfilelist ) > backwpup_get_option( $this->jobdata['JOBMAIN'], 'sugarmaxbackups' ) ) {
					$numdeltefiles = 0;
					while ( $file = array_shift( $backupfilelist ) ) {
						if ( count( $backupfilelist ) < backwpup_get_option( $this->jobdata['JOBMAIN'], 'sugarmaxbackups' ) )
							break;
						$sugarsync->delete( $file ); //delete files on Cloud
						for ( $i = 0; $i < count( $files ); $i ++ ) {
							if ( $files[$i]['file'] == $file )
								unset($files[$i]);
						}
						$numdeltefiles ++;
					}
					if ( $numdeltefiles > 0 )
						trigger_error( sprintf( _n( 'One file deleted on SugarSync folder', '%d files deleted on SugarSync folder', $numdeltefiles, 'backwpup' ), $numdeltefiles ), E_USER_NOTICE );
				}
			}
			backwpup_update_option( 'temp', $this->jobdata['JOBID'] . '_SUGARSYNC', $files );
		} catch ( Exception $e ) {
			$this->error_handler( E_USER_ERROR, sprintf( __( 'SugarSync API: %s', 'backwpup' ), htmlentities( $e->getMessage() ) ), $e->getFile(), $e->getLine() );
			return;
		}
		$this->jobdata['STEPDONE'] ++;
	}
}