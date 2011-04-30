<?php
// don't load directly
if ( !defined('ABSPATH') )
	die('-1');

/**
* BackWPup PHP class for WordPress
*
*/
class backwpup_dojob {

	private $jobid=0;
	public $filelist=array();
	private $tempfilelist=array();
	public 	$todo=array();
	private $allfilesize=0;
	public $backupfile='';
	public $backupfileformat='.zip';
	public $backupdir='';
	private $lastbackupdownloadurl='';
	public  $logdir='';
	public  $logfile='';
	private $tempdir='';
	public $cfg=array();
	public $job=array();

	public function __construct($jobid) {
		global $wpdb;
		@ini_get('safe_mode','Off'); //disable safe mode
		@ini_set('ignore_user_abort','Off'); //Set PHP ini setting
		ignore_user_abort(true);			//user can't abort script (close windows or so.)
		@set_time_limit(0);					//set script run time limit to wen its done
		$this->jobid=$jobid;			   //set job id
		$this->cfg=get_option('backwpup'); //load config
		$jobs=get_option('backwpup_jobs'); //load jobdata
		//set Logs Dir
		$this->logdir=trailingslashit($this->cfg['dirlogs']);
		if (empty($this->logdir) or $this->logdir=='/') {
			$rand = substr( md5( md5( SECURE_AUTH_KEY ) ), -5 );
			$this->logdir=str_replace('\\','/',trailingslashit(WP_CONTENT_DIR)).'backwpup-'.$rand.'-logs/';
		}
		//Check log file dir
		if (!$this->_check_folders($this->logdir))
			return false;		
		//check exists gzip functions
		if(!function_exists('gzopen'))
			$this->cfg['gzlogs']=false;
		//set Log file name
		$this->logfile='backwpup_log_'.date_i18n('Y-m-d_H-i-s').'.html';
		//create log file
		$fd=fopen($this->logdir.$this->logfile,'w');
		//Create log file header
		fwrite($fd,"<html>\n<head>\n");
		fwrite($fd,"<meta name=\"backwpup_version\" content=\"".BACKWPUP_VERSION."\" />\n");
		fwrite($fd,"<meta name=\"php_version\" content=\"".phpversion()."\" />\n");
		fwrite($fd,"<meta name=\"mysql_version\" content=\"".$wpdb->get_var("SELECT VERSION() AS version")."\" />\n");
		fwrite($fd,"<meta name=\"backwpup_logtime\" content=\"".current_time('timestamp')."\" />\n");
		fwrite($fd,str_pad("<meta name=\"backwpup_errors\" content=\"0\" />",100)."\n");
		fwrite($fd,str_pad("<meta name=\"backwpup_warnings\" content=\"0\" />",100)."\n");
		fwrite($fd,"<meta name=\"backwpup_jobid\" content=\"".$this->jobid."\" />\n");
		fwrite($fd,"<meta name=\"backwpup_jobname\" content=\"".$jobs[$this->jobid]['name']."\" />\n");
		fwrite($fd,"<meta name=\"backwpup_jobtype\" content=\"".$jobs[$this->jobid]['type']."\" />\n");
		fwrite($fd,str_pad("<meta name=\"backwpup_backupfilesize\" content=\"0\" />",100)."\n");
		fwrite($fd,str_pad("<meta name=\"backwpup_jobruntime\" content=\"0\" />",100)."\n");
		fwrite($fd,"<title>".sprintf(__('BackWPup Log for %1$s from %2$s at %3$s','backwpup'),$jobs[$this->jobid]['name'],date_i18n(get_option('date_format')),date_i18n(get_option('time_format')))."</title>\n</head>\n<body style=\"font-family:monospace;font-size:12px;white-space:nowrap;\">\n");
		fclose($fd);
		//set function for PHP user defineid error handling
		if (defined(WP_DEBUG) and WP_DEBUG)
			set_error_handler(array($this,'joberrorhandler'),E_ALL | E_STRICT);
		else
			set_error_handler(array($this,'joberrorhandler'),E_ALL & ~E_NOTICE);
		//find out if job already running and abort if
		if ($jobs[$this->jobid]['starttime']>0 and !empty($jobs[$this->jobid]['logfile'])) {
			if ($jobs[$this->jobid]['starttime']+600<current_time('timestamp')) { //Abort old jo if work longer as 10 min. because websever has 300 sec timeout
				trigger_error(__('Working Job will closed!!! And a new started!!!','backwpup'),E_USER_WARNING);
				//old logfile end
				$fd=fopen($jobs[$this->jobid]['logfile'],'a');
				fwrite($fd,"<span style=\"background-color:c3c3c3;\" title=\"[Line: ".__LINE__."|File: ".basename(__FILE__)."\">".date_i18n('Y-m-d H:i.s').":</span> <span style=\"background-color:red;\">".__('[ERROR]','backwpup')." ".__('Backup Aborted working to long!!!','backwpup')."</span><br />\n");
				fclose($fd);
				$logheader=backwpup_read_logheader($jobs[$this->jobid]['logfile']); //read waring count from log header
				$logheader['errors']++;
				//write new log header
				$fd=fopen($jobs[$this->jobid]['logfile'],'r+');
				while (!feof($fd)) {
					if (stripos(fgets($fd),"<meta name=\"backwpup_errors\"") !== false) {
						fseek($fd,$filepos);
						fwrite($fd,str_pad("<meta name=\"backwpup_errors\" content=\"".$logheader['errors']."\" />",100)."\n");
						break;
					}
					$filepos=ftell($fd);
				}
				fclose($fd);
				$this->job_end($jobs[$this->jobid]['logfile']);
			} else {
				trigger_error(sprintf(__('Job %1$s already running!!!','backwpup'),$jobs[$this->jobid]['name']),E_USER_ERROR);
			}
		}
		//Set job start settings
		$jobs[$this->jobid]['starttime']=current_time('timestamp'); //set start time for job
		$jobs[$this->jobid]['logfile']=$this->logdir.$this->logfile;	   //Set current logfile
		$jobs[$this->jobid]['cronnextrun']=backwpup_cron_next($jobs[$this->jobid]['cron']);  //set next run
		$jobs[$this->jobid]['lastbackupdownloadurl']='';
		$jobs[$this->jobid]['lastlogfile']=$this->logdir.$this->logfile;
		$jobs[$this->jobid]['cronnextrun']=backwpup_cron_next($jobs[$this->jobid]['cron']);  //set next run
		update_option('backwpup_jobs',$jobs); //Save job Settings
		$this->job=backwpup_check_job_vars($jobs[$this->jobid],$this->jobid);//Set and check job settings
		//set waht to do
		$this->todo=explode('+',$this->job['type']);
		//set Temp Dir
		$this->tempdir=trailingslashit($this->cfg['dirtemp']);
		if (empty($this->tempdir) or $this->tempdir=='/')
			$this->tempdir=backwpup_get_upload_dir();
		//only for jos that makes backups
		if (in_array('FILE',$this->todo) or in_array('DB',$this->todo) or in_array('WPEXP',$this->todo)) {
			//set Backup File format
			$this->backupfileformat=$this->job['fileformart'];
			//set Backup Dir
			$this->backupdir=$this->job['backupdir'];
			if (empty($this->backupdir))
				$this->backupdir=$this->tempdir;
			//check backup dir				
			if ($this->backupdir!=backwpup_get_upload_dir()) {
				if (!$this->_check_folders($this->backupdir))
					return false;
			}
			//set Backup file Name
			$this->backupfile=$this->job['fileprefix'].date_i18n('Y-m-d_H-i-s').$this->backupfileformat;
		}
		//check max script execution tme
		if (ini_get('safe_mode') or strtolower(ini_get('safe_mode'))=='on' or ini_get('safe_mode')=='1')
			trigger_error(sprintf(__('PHP Safe Mode is on!!! Max exec time is %1$d sec.','backwpup'),ini_get('max_execution_time')),E_USER_WARNING);
		// check function for memorylimit
		if (!function_exists('memory_get_usage')) {
			ini_set('memory_limit', apply_filters( 'admin_memory_limit', '256M' )); //Wordpress default
			trigger_error(sprintf(__('Memory limit set to %1$s ,because can not use PHP: memory_get_usage() function to dynamically increase the Memory!','backwpup'),ini_get('memory_limit')),E_USER_WARNING);
		}
	}

	//function for PHP error handling
	public function joberrorhandler() {
		$args = func_get_args(); // 0:errno, 1:errstr, 2:errfile, 3:errline
		
		//genrate timestamp
		$timestamp="<span style=\"background-color:c3c3c3;\" title=\"[Line: ".$args[3]."|File: ".basename($args[2])."|Mem: ".backwpup_formatBytes(@memory_get_usage(true))."|Mem Max: ".backwpup_formatBytes(@memory_get_peak_usage(true))."|Mem Limit: ".ini_get('memory_limit')."]\">".date_i18n('Y-m-d H:i.s').":</span> ";

		switch ($args[0]) {
		case E_NOTICE:
		case E_USER_NOTICE:
			$massage=$timestamp."<span>".$args[1]."</span>";
			break;
		case E_WARNING:
		case E_USER_WARNING:
			$logheader=backwpup_read_logheader($this->logdir.$this->logfile); //read waring count from log header
			$warnings=$logheader['warnings']+1;
			$massage=$timestamp."<span style=\"background-color:yellow;\">".__('[WARNING]','backwpup')." ".$args[1]."</span>";
			break;
		case E_ERROR: 
		case E_USER_ERROR:
			$logheader=backwpup_read_logheader($this->logdir.$this->logfile); //read error count from log header
			$errors=$logheader['errors']+1;
			$massage=$timestamp."<span style=\"background-color:red;\">".__('[ERROR]','backwpup')." ".$args[1]."</span>";
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

		if (!empty($massage)) {
			//wirte log file
			$fd=fopen($this->logdir.$this->logfile,'a');
			fwrite($fd,$massage."<br />\n");
			fclose($fd);

			//output on run now
			if (!defined('DOING_CRON')) {
				echo $massage."<script type=\"text/javascript\">window.scrollBy(0, 15);</script><br />\n";
				@flush();
				@ob_flush();
			}

			//write new log header
			if (isset($errors) or isset($warnings)) {
				$fd=fopen($this->logdir.$this->logfile,'r+');
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

			if ($args[0]==E_ERROR or $args[0]==E_CORE_ERROR or $args[0]==E_COMPILE_ERROR) {//Die on fatal php errors.
				$this->send_log_mail();
				die();
			} 
			//true for no more php error hadling.
			return true;
		} else {
			return false;
		}
	}	
	
	private function _check_folders($folder) {
		if (!is_dir($folder)) { //create dir if not exists
			if (!mkdir($folder,0755,true)) {
				trigger_error(sprintf(__('Can not create Folder: %1$s','backwpup'),$folder),E_USER_ERROR);
				return false;
			}
		}
		if (!is_writeable($folder)) { //test if folder wirteable
			trigger_error(sprintf(__('Can not write to Folder: %1$s','backwpup'),$folder),E_USER_ERROR);
			return false;
		}
		//create .htaccess for apache and index.html for other
		if (strtolower(substr($_SERVER["SERVER_SOFTWARE"],0,6))=="apache") {  //check if it a apache webserver
			if (!is_file($folder.'.htaccess')) {
				if($file = fopen($folder.'.htaccess', 'w')) {
					fwrite($file, "Order allow,deny\ndeny from all");
					fclose($file);
				}
			}
		} else {
			if (!is_file($folder.'index.html')) {
				if($file = fopen($folder.'index.html', 'w')) {
					fwrite($file,"\n");
					fclose($file);
				}
			}
			if (!is_file($folder.'index.php')) {
				if($file = fopen($folder.'index.php', 'w')) {
					fwrite($file,"\n");
					fclose($file);
				}
			}
		}
		return true;
	}

	private function need_free_memory($memneed) {
		//fail back if fuction not exist
		if (!function_exists('memory_get_usage'))
			return true;

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

	private function maintenance_mode($enable = false) {
		if (!$this->job['maintenance'])
			return;

		if ( $enable ) {
			trigger_error(__('Set Blog to Maintenance Mode','backwpup'),E_USER_NOTICE);
			if ( class_exists('WPMaintenanceMode') ) { //Support for WP Maintenance Mode Plugin
				update_option('wp-maintenance-mode-msqld','1');
			} elseif ( class_exists('MaintenanceMode') ) { //Support for Maintenance Mode Plugin
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
			if ( class_exists('WPMaintenanceMode') ) { //Support for WP Maintenance Mode Plugin
				update_option('wp-maintenance-mode-msqld','0');
			} elseif ( class_exists('MaintenanceMode') ) { //Support for Maintenance Mode Plugin
				$mamo=get_option('plugin_maintenance-mode');
				$mamo['mamo_activate']='off';
				update_option('plugin_maintenance-mode',$mamo);
			} else { //WP Support
				@unlink(trailingslashit(ABSPATH).'.maintenance');
			}
		}
	}

	public function check_db() {
		global $wpdb;

		trigger_error(__('Run Database check...','backwpup'),E_USER_NOTICE);

		$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');
		//exclude tables from check
		foreach($tables as $tablekey => $tablevalue) {
			if (in_array($tablevalue,$this->job['dbexclude']))
				unset($tables[$tablekey]);
		}

		//check tables
		if (sizeof($tables)>0) {
			$this->maintenance_mode(true);
			foreach ($tables as $table) {
				$check=$wpdb->get_row('CHECK TABLE `'.$table.'` MEDIUM', ARRAY_A);
				if ($check['Msg_type']=='error')
					trigger_error(sprintf(__('Result of table check for %1$s is: %2$s','backwpup'), $table, $check['Msg_text']),E_USER_ERROR);
				elseif ($check['Msg_type']=='warning')
					trigger_error(sprintf(__('Result of table check for %1$s is: %2$s','backwpup'), $table, $check['Msg_text']),E_USER_WARNING);
				else
					trigger_error(sprintf(__('Result of table check for %1$s is: %2$s','backwpup'), $table, $check['Msg_text']),E_USER_NOTICE);

				if ($sqlerr=mysql_error($wpdb->dbh)) //aditional SQL error
					trigger_error(sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), $sqlerr, $sqlerr->last_query),E_USER_ERROR);
				//Try to Repair tabele
				if ($check['Msg_type']=='error' or $check['Msg_type']=='warning') {
					$repair=$wpdb->get_row('REPAIR TABLE `'.$table.'`', ARRAY_A);
					if ($repair['Msg_type']=='error')
						trigger_error(sprintf(__('Result of table repair for %1$s is: %2$s','backwpup'), $table, $repair['Msg_text']),E_USER_ERROR);
					elseif ($repair['Msg_type']=='warning')
						trigger_error(sprintf(__('Result of table repair for %1$s is: %2$s','backwpup'), $table, $repair['Msg_text']),E_USER_WARNING);
					else
						trigger_error(sprintf(__('Result of table repair for %1$s is: %2$s','backwpup'), $table, $repair['Msg_text']),E_USER_NOTICE);

					if ($sqlerr=mysql_error($wpdb->dbh)) //aditional SQL error
						trigger_error(sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), $sqlerr, $sqlerr->last_query),E_USER_ERROR);
				}
			}
			$wpdb->flush();
			$this->maintenance_mode(false);
			trigger_error(__('Database check done!','backwpup'),E_USER_NOTICE);
		} else {
			trigger_error(__('No Tables to check','backwpup'),E_USER_WARNING);
		}
	}

	private function dump_db_table($table,$status,$file) {
		$this->need_free_memory(1048576); //1MB free memory for dump
		// create dump
		fwrite($file, "\n");
		fwrite($file, "--\n");
		fwrite($file, "-- Table structure for table $table\n");
		fwrite($file, "--\n\n");
		fwrite($file, "DROP TABLE IF EXISTS `" . $table .  "`;\n");
		fwrite($file, "/*!40101 SET @saved_cs_client     = @@character_set_client */;\n");
		fwrite($file, "/*!40101 SET character_set_client = '".mysql_client_encoding()."' */;\n");
		//Dump the table structure
		$result=mysql_query("SHOW CREATE TABLE `".$table."`");
		if (!$result) {
			trigger_error(sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), mysql_error(), "SHOW CREATE TABLE `".$table."`"),E_USER_ERROR);
			return false;
		}
		$tablestruc=mysql_fetch_assoc($result);
		fwrite($file, $tablestruc['Create Table'].";\n");
		fwrite($file, "/*!40101 SET character_set_client = @saved_cs_client */;\n");

		//take data of table
		$result=mysql_query("SELECT * FROM `".$table."`");
		if (!$result) {
			trigger_error(sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), mysql_error(), "SELECT * FROM `".$table."`"),E_USER_ERROR);
			return false;
		}

		fwrite($file, "--\n");
		fwrite($file, "-- Dumping data for table $table\n");
		fwrite($file, "--\n\n");
		if ($status['Engine']=='MyISAM')
			fwrite($file, "/*!40000 ALTER TABLE `".$table."` DISABLE KEYS */;\n");


		while ($data = mysql_fetch_assoc($result)) {
			$keys = array();
			$values = array();
			foreach($data as $key => $value) {
				if (!$this->job['dbshortinsert'])
					$keys[] = "`".str_replace("´", "´´", $key)."`"; // Add key to key list
				if($value === NULL) // Make Value NULL to string NULL
					$value = "NULL";
				elseif($value === "" or $value === false) // if empty or false Value make  "" as Value
					$value = "''";
				elseif(!is_numeric($value)) //is value not numeric esc
					$value = "'".mysql_real_escape_string($value)."'";
				$values[] = $value;
			}
			// make data dump
			if ($this->job['dbshortinsert'])
				fwrite($file, "INSERT INTO `".$table."` VALUES ( ".implode(", ",$values)." );\n");
			else
				fwrite($file, "INSERT INTO `".$table."` ( ".implode(", ",$keys)." )\n\tVALUES ( ".implode(", ",$values)." );\n");

		}
		if ($status['Engine']=='MyISAM')
			fwrite($file, "/*!40000 ALTER TABLE ".$table." ENABLE KEYS */;\n");
	}

	public function dump_db() {
		global $wpdb;
		trigger_error(__('Run Database Dump to file...','backwpup'),E_USER_NOTICE);
		//Set maintenance
		$this->maintenance_mode(true);

		//Tables to backup
		$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');
		if ($sqlerr=mysql_error($wpdb->dbh))
			trigger_error(sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), $sqlerr, "SHOW TABLES FROM `".DB_NAME."`"),E_USER_ERROR);

		foreach($tables as $tablekey => $tablevalue) {
			if (in_array($tablevalue,$this->job['dbexclude']))
				unset($tables[$tablekey]);
		}
		sort($tables);

		if (sizeof($tables)>0) {
			$result=$wpdb->get_results("SHOW TABLE STATUS FROM `".DB_NAME."`;", ARRAY_A); //get table status
			if ($sqlerr=mysql_error($wpdb->dbh))
				trigger_error(sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), $sqlerr, "SHOW TABLE STATUS FROM `".DB_NAME."`;"),E_USER_ERROR);
			foreach($result as $statusdata) {
				$status[$statusdata['Name']]=$statusdata;
			}

			if ($file = fopen($this->tempdir.DB_NAME.'.sql', 'wb')) {
				fwrite($file, "-- ---------------------------------------------------------\n");
				fwrite($file, "-- Dump with BackWPup ver.: ".BACKWPUP_VERSION."\n");
				fwrite($file, "-- Plugin for WordPress by Daniel Huesken\n");
				fwrite($file, "-- http://danielhuesken.de/portfolio/backwpup/\n");
				fwrite($file, "-- Blog Name: ".get_option('blogname')."\n");
				if (defined('WP_SITEURL'))
					fwrite($file, "-- Blog URL: ".trailingslashit(WP_SITEURL)."\n");
				else
					fwrite($file, "-- Blog URL: ".trailingslashit(get_option('siteurl'))."\n");
				fwrite($file, "-- Blog ABSPATH: ".trailingslashit(ABSPATH)."\n");
				fwrite($file, "-- Table Prefix: ".$wpdb->prefix."\n");
				fwrite($file, "-- Database Name: ".DB_NAME."\n");
				fwrite($file, "-- Dump on: ".date_i18n('Y-m-d H:i.s')."\n");
				fwrite($file, "-- ---------------------------------------------------------\n\n");
				//for better import with mysql client
				fwrite($file, "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n");
				fwrite($file, "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n");
				fwrite($file, "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n");
				fwrite($file, "/*!40101 SET NAMES '".mysql_client_encoding()."' */;\n");
				fwrite($file, "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;\n");
				fwrite($file, "/*!40103 SET TIME_ZONE='".mysql_result(mysql_query("SELECT @@time_zone"),0)."' */;\n");
				fwrite($file, "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n");
				fwrite($file, "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n");
				fwrite($file, "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n");
				fwrite($file, "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n");
				//make table dumps
				foreach($tables as $table) {
					trigger_error(__('Dump Database table: ','backwpup').' '.$table,E_USER_NOTICE);
					$this->need_free_memory(($status[$table]['Data_length']+$status[$table]['Index_length'])*1.3); //get more memory if needed
					fwrite($file, $this->dump_db_table($table,$status[$table],$file));
				}
				//for better import with mysql client
				fwrite($file, "\n");
				fwrite($file, "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;\n");
				fwrite($file, "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n");
				fwrite($file, "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n");
				fwrite($file, "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n");
				fwrite($file, "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n");
				fwrite($file, "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n");
				fwrite($file, "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n");
				fwrite($file, "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;\n");
				fclose($file);
				trigger_error(__('Database Dump done!','backwpup'),E_USER_NOTICE);
			} else {
				trigger_error(__('Can not create Database Dump file','backwpup'),E_USER_ERROR);
			}
		} else {
			trigger_error(__('No Tables to Dump','backwpup'),E_USER_WARNING);
		}

		//add database file to backupfiles
		if (is_readable($this->tempdir.DB_NAME.'.sql')) {
			trigger_error(__('Add Database Dump to Backup:','backwpup').' '.DB_NAME.'.sql '.backwpup_formatBytes(filesize($this->tempdir.DB_NAME.'.sql')),E_USER_NOTICE);
			$this->allfilesize+=filesize($this->tempdir.DB_NAME.'.sql');
			$this->filelist[]=array(79001=>$this->tempdir.DB_NAME.'.sql',79003=>DB_NAME.'.sql');
		}
		//Back from maintenance
		$this->maintenance_mode(false);
	}

	public function export_wp() {
		$this->need_free_memory(1048576); //1MB free memory
		$nonce=wp_create_nonce('backwpup-xmlexport');
		update_option('backwpup_nonce',array('nonce'=>$nonce,'timestamp'=>time()));
		if (function_exists('curl_exec')) {
			trigger_error(__('Run Wordpress Export to XML file...','backwpup'),E_USER_NOTICE);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, plugins_url('wp_xml_export.php',__FILE__).'?wpabs='.trailingslashit(ABSPATH).'&_nonce='.$nonce);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
			$return=curl_exec($ch);
			if (!$return) {
				trigger_error(__('cURL:','backwpup').' '.curl_error($ch),E_USER_ERROR);
			} else {
				$fd=fopen($this->tempdir.preg_replace( '/[^a-z0-9_\-]/', '', strtolower(get_bloginfo('name')) ).'.wordpress.' . date( 'Y-m-d' ) . '.xml',"w+");
				fwrite($fd,$return);
				fclose($fd);
			}
			curl_close($ch);
		} elseif (ini_get('allow_url_fopen')==true or ini_get('allow_url_fopen')==1 or strtolower(ini_get('allow_url_fopen'))=="on") {
			trigger_error(__('Run Wordpress Export to XML file...','backwpup'),E_USER_NOTICE);
			if (copy(plugins_url('wp_xml_export.php',__FILE__).'?wpabs='.trailingslashit(ABSPATH).'&_nonce='.$nonce,$this->tempdir.preg_replace( '/[^a-z0-9_\-]/', '', strtolower(get_bloginfo('name')) ).'.wordpress.' . date( 'Y-m-d' ) . '.xml')) {
				trigger_error(__('Export to XML done!','backwpup'),E_USER_NOTICE);
			} else {
				trigger_error(__('Can not Export to XML!','backwpup'),E_USER_ERROR);
			}		
		} else {
			trigger_error(__('Can not Export to XML! no cURL or allow_url_fopen Support!','backwpup'),E_USER_WARNING);
		}
		if (is_readable($this->tempdir.preg_replace( '/[^a-z0-9_\-]/', '', strtolower(get_bloginfo('name')) ).'.wordpress.' . date( 'Y-m-d' ) . '.xml')) {
			//add database file to backupfiles
			trigger_error(__('Add XML Export to Backup:','backwpup').' '.preg_replace( '/[^a-z0-9_\-]/', '', strtolower(get_bloginfo('name')) ).'.wordpress.' . date( 'Y-m-d' ) . '.xml '.backwpup_formatBytes(filesize($this->tempdir.preg_replace( '/[^a-z0-9_\-]/', '', strtolower(get_bloginfo('name')) ).'.wordpress.' . date( 'Y-m-d' ) . '.xml')),E_USER_NOTICE);
			$this->allfilesize+=filesize($this->tempdir.preg_replace( '/[^a-z0-9_\-]/', '', strtolower(get_bloginfo('name')) ).'.wordpress.' . date( 'Y-m-d' ) . '.xml');
			$this->filelist[]=array(79001=>$this->tempdir.preg_replace( '/[^a-z0-9_\-]/', '', strtolower(get_bloginfo('name')) ).'.wordpress.' . date( 'Y-m-d' ) . '.xml',79003=>preg_replace( '/[^a-z0-9_\-]/', '', strtolower(get_bloginfo('name')) ).'.wordpress.' . date( 'Y-m-d' ) . '.xml');
		}
	}
	
	public function optimize_db() {
		global $wpdb;

		trigger_error(__('Run Database optimize...','backwpup'),E_USER_NOTICE);

		$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');
		//exclude tables from optimize
		foreach($tables as $tablekey => $tablevalue) {
			if (in_array($tablevalue,$this->job['dbexclude']))
				unset($tables[$tablekey]);
		}

		if (sizeof($tables)>0) {
			$this->maintenance_mode(true);
			foreach ($tables as $table) {
				$optimize=$wpdb->get_row('OPTIMIZE TABLE `'.$table.'`', ARRAY_A);
				if ($optimize['Msg_type']=='error')
					trigger_error(sprintf(__('Result of table optimize for %1$s is: %2$s','backwpup'), $table, $optimize['Msg_text']),E_USER_ERROR);
				elseif ($optimize['Msg_type']=='warning')
					trigger_error(sprintf(__('Result of table optimize for %1$s is: %2$s','backwpup'), $table, $optimize['Msg_text']),E_USER_WARNING);
				else
					trigger_error(sprintf(__('Result of table optimize for %1$s is: %2$s','backwpup'), $table, $optimize['Msg_text']),E_USER_NOTICE);

				if ($sqlerr=mysql_error($wpdb->dbh))
					trigger_error(sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), $sqlerr, $sqlerr->last_query),E_USER_ERROR);
			}
			$wpdb->flush();
			trigger_error(__('Database optimize done!','backwpup'),E_USER_NOTICE);
			$this->maintenance_mode(false);
		} else {
			trigger_error(__('No Tables to optimize','backwpup'),E_USER_WARNING);
		}
	}

	private function _file_list_folder( $folder = '', $levels = 100, $excludes=array(),$excludedirs=array()) {
		if( empty($folder) )
			return false;
		if( ! $levels )
			return false;
		if ( $dir = @opendir( $folder ) ) {
			while (($file = readdir( $dir ) ) !== false ) {
				if ( in_array($file, array('.', '..','.svn') ) )
					continue;
				foreach ($excludes as $exclusion) { //exclude dirs and files
					if (false !== stripos($folder.$file,$exclusion) and !empty($exclusion) and $exclusion!='/')
						continue 2;
				}
				if ( !is_readable($folder.$file) ) {
					trigger_error(__('File or Folder is not readable:','backwpup').' '.$folder.$file,E_USER_WARNING);
				} elseif ( is_link($folder.$file) ) {
					trigger_error(__('Link not followed:','backwpup').' '.$folder.$file,E_USER_WARNING);
				} elseif ( is_dir( $folder.$file )) {
					if (!in_array(trailingslashit($folder.$file),$excludedirs))
						$this->_file_list_folder( trailingslashit($folder.$file), $levels - 1, $excludes);
				} elseif ( is_file( $folder.$file ) or is_executable($folder.$file) ) { //add file to filelist
					$this->tempfilelist[]=$folder.$file;
					$this->allfilesize=$this->allfilesize+filesize($folder.$file);
				} else {
					trigger_error(__('Is not a file or directory:','backwpup').' '.$folder.$file,E_USER_WARNING);
				}
			}
			@closedir( $dir );
		}
	}

	public function file_list() {

		//Make filelist
		trigger_error(__('Make a list of files to Backup ....','backwpup'),E_USER_NOTICE);
		
		//Check free memory for file list
		$this->need_free_memory(2097152); //2MB free memory for filelist
		//empty filelist
		$this->tempfilelist=array();

		$backwpup_exclude=explode(',',trim($this->job['fileexclude']));
		//Exclude Temp Files
		$backwpup_exclude[]=$this->tempdir.DB_NAME.'.sql';
		$backwpup_exclude[]=$this->tempdir.'wordpress.' . date( 'Y-m-d' ) . '.xml';
		$backwpup_exclude=array_unique($backwpup_exclude);

		//File list for blog folders
		if ($this->job['backuproot'])
			$this->_file_list_folder(trailingslashit(str_replace('\\','/',ABSPATH)),100,$backwpup_exclude,array_merge($this->job['backuprootexcludedirs'],backwpup_get_exclude_wp_dirs(ABSPATH)));
		if ($this->job['backupcontent'])
			$this->_file_list_folder(trailingslashit(str_replace('\\','/',WP_CONTENT_DIR)),100,$backwpup_exclude,array_merge($this->job['backupcontentexcludedirs'],backwpup_get_exclude_wp_dirs(WP_CONTENT_DIR)));
		if ($this->job['backupplugins'])
			$this->_file_list_folder(trailingslashit(str_replace('\\','/',WP_PLUGIN_DIR)),100,$backwpup_exclude,array_merge($this->job['backuppluginsexcludedirs'],backwpup_get_exclude_wp_dirs(WP_PLUGIN_DIR)));
		if ($this->job['backupthemes'])
			$this->_file_list_folder(trailingslashit(str_replace('\\','/',trailingslashit(WP_CONTENT_DIR).'themes')),100,$backwpup_exclude,array_merge($this->job['backupthemesexcludedirs'],backwpup_get_exclude_wp_dirs(trailingslashit(WP_CONTENT_DIR).'themes')));
		if ($this->job['backupuploads'])
			$this->_file_list_folder(trailingslashit(backwpup_get_upload_dir()),100,$backwpup_exclude,array_merge($this->job['backupuploadsexcludedirs'],backwpup_get_exclude_wp_dirs(backwpup_get_upload_dir())));

	    //include dirs
		if (!empty($this->job['dirinclude'])) {
			$dirinclude=explode(',',$this->job['dirinclude']);
			$dirinclude=array_unique($dirinclude);
			//Crate file list for includes
			foreach($dirinclude as $dirincludevalue) {
				if (is_dir($dirincludevalue))
					$this->_file_list_folder(trailingslashit($dirincludevalue),100,$backwpup_exclude);
			}
		}
		
		$this->tempfilelist=array_unique($this->tempfilelist); //all files only one time in list
		sort($this->tempfilelist);
		//Check abs path
		if (ABSPATH=='/' or ABSPATH=='')
			$removepath='';
		else
			$removepath=trailingslashit(ABSPATH);
		//make file list
		foreach ($this->tempfilelist as $files) 
			$this->filelist[]=array(79001=>$files,79003=>str_replace($removepath,'',$files));
		$this->tempfilelist=array();

		if (!is_array($this->filelist[0])) {
			trigger_error(__('No files to Backup','backwpup'),E_USER_ERROR);
		} else {
			trigger_error(__('Files to Backup:','backwpup').' '.count($this->filelist),E_USER_NOTICE);
			trigger_error(__('Size of all Files:','backwpup').' '.backwpup_formatBytes($this->allfilesize),E_USER_NOTICE);
		}

	}

	public function zip_files() {
		if (class_exists('ZipArchive')) {  //use php zip lib
			trigger_error(__('Create Backup Zip file...','backwpup'),E_USER_NOTICE);
			$zip = new ZipArchive;
			if ($res=$zip->open($this->backupdir.$this->backupfile,ZIPARCHIVE::CREATE) === TRUE) {
				foreach($this->filelist as $key => $files) {
					if (!is_file($files[79001])) //check file exists
						continue;
					if ($zip->addFile($files[79001], $files[79003])) {
						if ($this->cfg['logfilelist'])
							trigger_error(__('Add File to ZIP file:','backwpup').' '.$files[79001].' '.backwpup_formatBytes(filesize($files[79001])),E_USER_NOTICE);
					} else {
						trigger_error(__('Can not add File to ZIP file:','backwpup').' '.$files[79001],E_USER_ERROR);
					}
				}
				$zip->close();
				trigger_error(__('Backup Zip file create done!','backwpup'),E_USER_NOTICE);
			} else {
				trigger_error(__('Can not create Backup ZIP file:','backwpup').' '.$res,E_USER_ERROR);
			}
		} else { //use PclZip
			define( 'PCLZIP_TEMPORARY_DIR', $this->tempdir );
			if (!class_exists('PclZip'))
				require_once(dirname(__FILE__).'/libs/pclzip.lib.php');

			//Create Zip File
			if (is_array($this->filelist[0])) {
				$this->need_free_memory(10485760); //10MB free memory for zip
				trigger_error(__('Create Backup Zip (PclZip) file...','backwpup'),E_USER_NOTICE);
				if ($this->cfg['logfilelist']) {
					foreach($this->filelist as $key => $files) {
						trigger_error(__('Add File to ZIP file:','backwpup').' '.$files[79001].' '.backwpup_formatBytes(filesize($files[79001])),E_USER_NOTICE);
					}
				}
				$zipbackupfile = new PclZip($this->backupdir.$this->backupfile);
				if (0==$zipbackupfile -> create($this->filelist,PCLZIP_OPT_ADD_TEMP_FILE_ON)) {
					trigger_error(__('Zip file create:','backwpup').' '.$zipbackupfile->errorInfo(true),E_USER_ERROR);
				} else {
					trigger_error(__('Backup Zip file create done!','backwpup'),E_USER_NOTICE);
				}
			}
		}
		if ($filesize=filesize($this->backupdir.$this->backupfile))
			trigger_error(sprintf(__('Backup Archive File size is %1s','backwpup'),backwpup_formatBytes($filesize)),E_USER_NOTICE);
	}

	public function tar_pack_files() {

		if ($this->backupfileformat=='.tar.gz') {
			$tarbackup=gzopen($this->backupdir.$this->backupfile,'w9');
		} elseif ($this->backupfileformat=='.tar.bz2') {
			$tarbackup=bzopen($this->backupdir.$this->backupfile,'w');
		} else {
			$tarbackup=fopen($this->backupdir.$this->backupfile,'w');
		}

		if (!$tarbackup) {
			trigger_error(__('Can not create TAR Backup file','backwpup'),E_USER_ERROR);
			return;
		} else {
			trigger_error(__('Create Backup Archive file...','backwpup'),E_USER_NOTICE);
		}

		$this->need_free_memory(1048576); //1MB free memory for tar

		foreach($this->filelist as $key => $files) {
				if ($this->cfg['logfilelist'])
					trigger_error(__('Add File to Backup Archive:','backwpup').' '.$files[79001].' '.backwpup_formatBytes(filesize($files[79001])),E_USER_NOTICE);
					
				//check file exists
				if (!is_readable($files[79001]))
					continue;

				// Get file information
				$file_information = stat($files[79001]);
				//split filename larger than 100 chars
				if (strlen($files[79003])<=100) {
					$filename=$files[79003];
					$filenameprefix="";
				} else {
					$filenameofset=strlen($files[79003])-100;
					$dividor=strpos($files[79003],'/',$filenameofset);
					$filename=substr($files[79003],$dividor+1);
					$filenameprefix=substr($files[79003],0,$dividor);
					if (strlen($filename)>100)
						trigger_error(__('File Name to Long to save corectly in TAR Backup Archive:','backwpup').' '.$files[79003],E_USER_WARNING);
					if (strlen($filenameprefix)>155)
						trigger_error(__('File Path to Long to save corectly in TAR Backup Archive:','backwpup').' '.$files[79003],E_USER_WARNING);
				}
				//Set file user/group name if linux
				$fileowner="Unknown";
				$filegroup="Unknown";
				if (function_exists('posix_getpwuid')) {
					$info=posix_getpwuid($file_information['uid']);
					$fileowner=$info['name'];
					$info=posix_getgrgid($file_information['gid']);
					$filegroup=$info['name'];
				}
				
				// Generate the TAR header for this file
				$header = pack("a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12",
						  $filename,  									//name of file  100
						  sprintf("%07o", $file_information['mode']), 	//file mode  8
						  sprintf("%07o", $file_information['uid']),	//owner user ID  8
						  sprintf("%07o", $file_information['gid']),	//owner group ID  8
						  sprintf("%011o", $file_information['size']),	//length of file in bytes  12
						  sprintf("%011o", $file_information['mtime']),	//modify time of file  12
						  "        ",									//checksum for header  8
						  0,											//type of file  0 or null = File, 5=Dir
						  "",											//name of linked file  100
						  "ustar",										//USTAR indicator  6
						  "00",											//USTAR version  2
						  $fileowner,									//owner user name 32
						  $filegroup,									//owner group name 32
						  "",											//device major number 8
						  "",											//device minor number 8
						  $filenameprefix,								//prefix for file name 155
						  "");											//fill block 512K

				// Computes the unsigned Checksum of a file's header
				$checksum = 0;
				for ($i = 0; $i < 512; $i++)
					$checksum += ord(substr($header, $i, 1));
				$checksum = pack("a8", sprintf("%07o", $checksum));

				$header = substr_replace($header, $checksum, 148, 8);

				if ($this->backupfileformat=='.tar.gz') {
					gzwrite($tarbackup, $header);
				} elseif ($this->backupfileformat=='.tar.bz2') {
					bzwrite($tarbackup, $header);
				} else {
					fwrite($tarbackup, $header);
				}

				// read/write files in 512K Blocks
				$fd=fopen($files[79001],'rb');
				while(!feof($fd)) {
					$filedata=fread($fd,512);
					if (strlen($filedata)>0) {
						if ($this->backupfileformat=='.tar.gz') {
							gzwrite($tarbackup,pack("a512", $filedata));
						} elseif ($this->backupfileformat=='.tar.bz2') {
							bzwrite($tarbackup,pack("a512", $filedata));
						} else {
							fwrite($tarbackup,pack("a512", $filedata));
						}
					}
				}
				fclose($fd);
			}


		if ($this->backupfileformat=='.tar.gz') {
			gzwrite($tarbackup, pack("a1024", "")); // Add 1024 bytes of NULLs to designate EOF
			gzclose($tarbackup);
		} elseif ($this->backupfileformat=='.tar.bz2') {
			bzwrite($tarbackup, pack("a1024", "")); // Add 1024 bytes of NULLs to designate EOF
			bzclose($tarbackup);
		} else {
			fwrite($tarbackup, pack("a1024", "")); // Add 1024 bytes of NULLs to designate EOF
			fclose($tarbackup);
		}

		trigger_error(__('Backup Archive file create done!','backwpup'),E_USER_NOTICE);
		if ($filesize=filesize($this->backupdir.$this->backupfile))
			trigger_error(sprintf(__('Backup Archive File size is %1s','backwpup'),backwpup_formatBytes($filesize)),E_USER_NOTICE);
	}


	public function destination_ftp() {
		
		$this->need_free_memory(filesize($this->backupdir.$this->backupfile)*1.5);
		
		$ftpport=21;
		$ftphost=$this->job['ftphost'];
		if (false !== strpos($this->job['ftphost'],':')) //look for port
			list($ftphost,$ftpport)=explode(':',$this->job['ftphost'],2);

		if ($this->job['ftpssl']) { //make SSL FTP connection
			if (function_exists('ftp_ssl_connect')) {
				$ftp_conn_id = ftp_ssl_connect($ftphost,$ftpport,10);
				if ($ftp_conn_id) 
					trigger_error(__('Connected by SSL-FTP to Server:','backwpup').' '.$this->job['ftphost'],E_USER_NOTICE);
				else {
					trigger_error(__('Can not connect by SSL-FTP to Server:','backwpup').' '.$this->job['ftphost'],E_USER_ERROR);
					return false;
				}
			} else {
				trigger_error(__('PHP Function to connect with SSL-FTP to Server not exists!','backwpup'),E_USER_ERROR);
				return false;			
			}
		} else { //make normal FTP conection if SSL not work
			$ftp_conn_id = ftp_connect($ftphost,$ftpport,10);
			if ($ftp_conn_id) 
				trigger_error(__('Connected to FTP Server:','backwpup').' '.$this->job['ftphost'],E_USER_NOTICE);
			else {
				trigger_error(__('Can not connect to FTP Server:','backwpup').' '.$this->job['ftphost'],E_USER_ERROR);
				return false;
			}
		}

		//FTP Login
		$loginok=false;
		trigger_error(__('FTP Client command:','backwpup').' USER '.$this->job['ftpuser'],E_USER_NOTICE);
		if ($loginok=ftp_login($ftp_conn_id, $this->job['ftpuser'], base64_decode($this->job['ftppass']))) {
			trigger_error(__('FTP Server reply:','backwpup').' User '.$this->job['ftpuser'].' logged in.',E_USER_NOTICE);
		} else { //if PHP ftp login don't work use raw login
			$return=ftp_raw($ftp_conn_id,'USER '.$this->job['ftpuser']); 
			trigger_error(__('FTP Server reply:','backwpup').' '.$return[0],E_USER_NOTICE);
			if (substr(trim($return[0]),0,3)<=400) {
				trigger_error(__('FTP Client command:','backwpup').' PASS *******',E_USER_NOTICE);
				$return=ftp_raw($ftp_conn_id,'PASS '.base64_decode($this->job['ftppass']));
				trigger_error(__('FTP Server reply:','backwpup').' '.$return[0],E_USER_NOTICE);
				if (substr(trim($return[0]),0,3)<=400) 
					$loginok=true;
			}
		}

		if (!$loginok)
			return false;

		//PASV
		trigger_error(__('FTP Client command:','backwpup').' PASV',E_USER_NOTICE);
		if ($this->job['ftppasv']) {
			if (ftp_pasv($ftp_conn_id, true))
				trigger_error(__('FTP Server reply:','backwpup').' '.__('Entering Passive Mode','backwpup'),E_USER_NOTICE);
			else
				trigger_error(__('FTP Server reply:','backwpup').' '.__('Can not Entering Passive Mode','backwpup'),E_USER_WARNING);
		} else {
			if (ftp_pasv($ftp_conn_id, false))
				trigger_error(__('FTP Server reply:','backwpup').' '.__('Entering Normal Mode','backwpup'),E_USER_NOTICE);
			else
				trigger_error(__('FTP Server reply:','backwpup').' '.__('Can not Entering Normal Mode','backwpup'),E_USER_WARNING);		
		}
		//SYSTYPE
		trigger_error(__('FTP Client command:','backwpup').' SYST',E_USER_NOTICE);
		$systype=ftp_systype($ftp_conn_id);
		if ($systype) 
			trigger_error(__('FTP Server reply:','backwpup').' '.$systype,E_USER_NOTICE);
		else
			trigger_error(__('FTP Server reply:','backwpup').' '.__('Error getting SYSTYPE','backwpup'),E_USER_ERROR);

		//test ftp dir and create it f not exists
		$ftpdirs=explode("/", untrailingslashit($this->job['ftpdir']));
		foreach ($ftpdirs as $ftpdir) {
			if (empty($ftpdir))
				continue;
			if (!@ftp_chdir($ftp_conn_id, $ftpdir)) {
				trigger_error('"'.$ftpdir.'" '.__('FTP Folder on Server not exists!','backwpup'),E_USER_WARNING);
				if (@ftp_mkdir($ftp_conn_id, $ftpdir)) {
					trigger_error('"'.$ftpdir.'" '.__('FTP Folder created!','backwpup'),E_USER_NOTICE);
					ftp_chdir($ftp_conn_id, $ftpdir);
				} else {
					trigger_error('"'.$ftpdir.'" '.__('FTP Folder on Server can not created!','backwpup'),E_USER_ERROR);
				}
			}
		}

		if (ftp_put($ftp_conn_id, $this->job['ftpdir'].$this->backupfile, $this->backupdir.$this->backupfile, FTP_BINARY)) { //transfere file
			trigger_error(__('Backup File transferred to FTP Server:','backwpup').' '.$this->job['ftpdir'].$this->backupfile,E_USER_NOTICE);
			$this->lastbackupdownloadurl="ftp://".$this->job['ftpuser'].":".base64_decode($this->job['ftppass'])."@".$this->job['ftphost'].$this->job['ftpdir'].$this->backupfile;
		} else
			trigger_error(__('Can not transfer backup to FTP server.','backwpup'),E_USER_ERROR);

		if ($this->job['ftpmaxbackups']>0) { //Delete old backups
			$backupfilelist=array();
			if ($filelist=ftp_nlist($ftp_conn_id, $this->job['ftpdir'])) {
				foreach($filelist as $files) {
					if ($this->job['fileprefix'] == substr(basename($files),0,strlen($this->job['fileprefix'])) and $this->backupfileformat == substr(basename($files),-strlen($this->backupfileformat)))
						$backupfilelist[]=basename($files);
				}
				if (sizeof($backupfilelist)>0) {
					rsort($backupfilelist);
					$numdeltefiles=0;
					for ($i=$this->job['ftpmaxbackups'];$i<sizeof($backupfilelist);$i++) {
						if (ftp_delete($ftp_conn_id, $this->job['ftpdir'].$backupfilelist[$i])) //delte files on ftp
						$numdeltefiles++;
						else
							trigger_error(__('Can not delete file on FTP Server:','backwpup').' '.$this->job['ftpdir'].$backupfilelist[$i],E_USER_ERROR);
					}
					if ($numdeltefiles>0)
						trigger_error($numdeltefiles.' '.__('files deleted on FTP Server:','backwpup'),E_USER_NOTICE);
				}
			}
		}
		ftp_close($ftp_conn_id);

	}

	public function destination_mail() {
		trigger_error(__('Prepare Sending backup file with mail...','backwpup'),E_USER_NOTICE);
		//Create PHP Mailer
		require_once(ABSPATH.WPINC.'/class-phpmailer.php');
		$phpmailer = new PHPMailer();
		//Setting den methode
		if ($this->cfg['mailmethod']=="SMTP") {
			require_once(ABSPATH.WPINC.'/class-smtp.php');
			$smtpport=25;
			$smtphost=$this->cfg['mailhost'];
			if (false !== strpos($this->cfg['mailhost'],':')) //look for port
				list($smtphost,$smtpport)=explode(':',$this->cfg['mailhost'],2);
			$phpmailer->Host=$smtphost;
			$phpmailer->Port=$smtpport;
			$phpmailer->SMTPSecure=$this->cfg['mailsecure'];
			$phpmailer->Username=$this->cfg['mailuser'];
			$phpmailer->Password=base64_decode($this->cfg['mailpass']);
			if (!empty($this->cfg['mailuser']) and !empty($this->cfg['mailpass']))
				$phpmailer->SMTPAuth=true;
			$phpmailer->IsSMTP();
			trigger_error(__('Send mail with SMTP','backwpup'),E_USER_NOTICE);
		} elseif ($this->cfg['mailmethod']=="Sendmail") {
			$phpmailer->Sendmail=$this->cfg['mailsendmail'];
			$phpmailer->IsSendmail();
			trigger_error(__('Send mail with Sendmail','backwpup'),E_USER_NOTICE);
		} else {
			$phpmailer->IsMail();
			trigger_error(__('Send mail with PHP mail','backwpup'),E_USER_NOTICE);
		}


		trigger_error(__('Creating mail','backwpup'),E_USER_NOTICE);
		$phpmailer->From     = $this->cfg['mailsndemail'];
		$phpmailer->FromName = $this->cfg['mailsndname'];
		$phpmailer->AddAddress($this->job['mailaddress']);
		$phpmailer->Subject  =  __('BackWPup File from','backwpup').' '.date_i18n('Y-m-d H:i',$this->job['starttime']).': '.$this->job['name'];
		$phpmailer->IsHTML(false);
		$phpmailer->Body  =  'Backup File';

		//check file Size
		if (!empty($this->job['mailefilesize'])) {
			$maxfilezise=abs($this->job['mailefilesize']*1024*1024);
			if (filesize($this->backupdir.$this->backupfile)>$maxfilezise) {
				trigger_error(__('Backup Archive too big for sending by mail','backwpup'),E_USER_ERROR);
				return false;
			}
		}

		trigger_error(__('Adding Attachment to mail','backwpup'),E_USER_NOTICE);
		$this->need_free_memory(filesize($this->backupdir.$this->backupfile)*5);
		$phpmailer->AddAttachment($this->backupdir.$this->backupfile);

		trigger_error(__('Send mail....','backwpup'),E_USER_NOTICE);
		if (false == $phpmailer->Send()) {
			trigger_error(__('Can not send mail:','backwpup').' '.$phpmailer->ErrorInfo,E_USER_ERROR);
		} else {
			trigger_error(__('Mail send!!!','backwpup'),E_USER_NOTICE);
		}

	}

	public function destination_s3() {

		if (!class_exists('CFRuntime'))
			require_once(dirname(__FILE__).'/libs/aws/sdk.class.php');
		
		$this->need_free_memory(26214400*1.1); 
		
		try {
			$s3 = new AmazonS3($this->job['awsAccessKey'], $this->job['awsSecretKey']);

			if ($s3->if_bucket_exists($this->job['awsBucket'])) {
				trigger_error(__('Connected to S3 Bucket:','backwpup').' '.$this->job['awsBucket'],E_USER_NOTICE);

				//Transfer Backup to S3
				if ($this->job['awsrrs']) //set reduced redundancy or not
					$storage=AmazonS3::STORAGE_REDUCED;
				else 
					$storage=AmazonS3::STORAGE_STANDARD;
					
				if ($s3->create_mpu_object($this->job['awsBucket'], $this->job['awsdir'].$this->backupfile, array('fileUpload' => $this->backupdir.$this->backupfile,'acl' => AmazonS3::ACL_PRIVATE,'storage' => $storage,'partSize'=>26214400)))  {//transfere file to S3
					trigger_error(__('Backup File transferred to S3://','backwpup').$this->job['awsBucket'].'/'.$this->job['awsdir'].$this->backupfile,E_USER_NOTICE);
					$this->lastbackupdownloadurl='admin.php?page=BackWPup&subpage=backups&action=downloads3&file='.$this->job['awsdir'].$this->backupfile.'&jobid='.$this->jobid;
				} else {
					trigger_error(__('Can not transfer backup to S3.','backwpup'),E_USER_ERROR);
				}
				
				if ($this->job['awsmaxbackups']>0) { //Delete old backups
					$backupfilelist=array();
					if (($contents = $s3->list_objects($this->job['awsBucket'],array('prefix'=>$this->job['awsdir']))) !== false) {
						foreach ($contents->body->Contents as $object) {
							$file=basename($object->Key);
							if ($this->job['fileprefix'] == substr($file,0,strlen($this->job['fileprefix'])) and $this->backupfileformat == substr($file,-strlen($this->backupfileformat)))
								$backupfilelist[]=$file;
						}
					}
					if (sizeof($backupfilelist)>0) {
						rsort($backupfilelist);
						$numdeltefiles=0;
						for ($i=$this->job['awsmaxbackups'];$i<sizeof($backupfilelist);$i++) {
							if ($s3->delete_object($this->job['awsBucket'], $this->job['awsdir'].$backupfilelist[$i])) //delte files on S3
								$numdeltefiles++;
							else
								trigger_error(__('Can not delete file on S3://','backwpup').$this->job['awsBucket'].'/'.$this->job['awsdir'].$backupfilelist[$i],E_USER_ERROR);
						}
						if ($numdeltefiles>0)
							trigger_error($numdeltefiles.' '.__('files deleted on S3 Bucket!','backwpup'),E_USER_NOTICE);
					}
				}					
					
			

			} else {
				trigger_error(__('S3 Bucket not exists:','backwpup').' '.$this->job['awsBucket'],E_USER_ERROR);
			}
		} catch (Exception $e) {
			trigger_error(__('Amazon S3 API:','backwpup').' '.$e->getMessage(),E_USER_ERROR);
			return;
		}
	}

	public function destination_rsc() {

		if (!class_exists('CF_Authentication')) 
			require_once(dirname(__FILE__).'/libs/rackspace/cloudfiles.php');
		
		$auth = new CF_Authentication($this->job['rscUsername'], $this->job['rscAPIKey']);
		$auth->ssl_use_cabundle();
		try {
			if ($auth->authenticate())
				trigger_error(__('Connected to Rackspase ...','backwpup'),E_USER_NOTICE);			
			$conn = new CF_Connection($auth);
			$conn->ssl_use_cabundle();
			$is_container=false;
			$containers=$conn->get_containers();
			foreach ($containers as $container) {
				if ($container->name == $this->job['rscContainer'] )
					$is_container=true;
			}
			if (!$is_container) {
				$public_container = $conn->create_container($this->job['rscContainer']);
				$public_container->make_private();
				if (empty($public_container))
					$is_container=false;
			}	
		} catch (Exception $e) {
			trigger_error(__('Rackspase Cloud API:','backwpup').' '.$e->getMessage(),E_USER_ERROR);
			return;
		}
		
		if (!$is_container) {
			trigger_error(__('Rackspase Cloud Container not exists:','backwpup').' '.$this->job['rscContainer'],E_USER_ERROR);
			return;
		}
		
		try {
			//Transfer Backup to Rackspace Cloud
			$backwpupcontainer = $conn->get_container($this->job['rscContainer']);
			//if (!empty($this->job['rscdir'])) //make the foldder
			//	$backwpupcontainer->create_paths($this->job['rscdir']); 
			$backwpupbackup = $backwpupcontainer->create_object($this->job['rscdir'].$this->backupfile);

			if ($backwpupbackup->load_from_filename($this->backupdir.$this->backupfile)) {
				trigger_error(__('Backup File transferred to RSC://','backwpup').$this->job['rscContainer'].'/'.$this->job['rscdir'].$this->backupfile,E_USER_NOTICE);
				$this->lastbackupdownloadurl='admin.php?page=BackWPup&subpage=backups&action=downloadrsc&file='.$this->job['rscdir'].$this->backupfile.'&jobid='.$this->jobid;
			} else {
				trigger_error(__('Can not transfer backup to RSC.','backwpup'),E_USER_ERROR);
			}
			
			if ($this->job['rscmaxbackups']>0) { //Delete old backups
				$backupfilelist=array();
				$contents = $backwpupcontainer->list_objects(0,NULL,NULL,$this->job['rscdir']);
				if (is_array($contents)) {
					foreach ($contents as $object) {
						$file=basename($object);
						if ($this->job['rscdir'].$file == $object) {//only in the folder and not in complete bucket
							if ($this->job['fileprefix'] == substr($file,0,strlen($this->job['fileprefix'])) and $this->backupfileformat == substr($file,-strlen($this->backupfileformat)))
								$backupfilelist[]=$file;
						}
					}
				}
				if (sizeof($backupfilelist)>0) {
					rsort($backupfilelist);
					$numdeltefiles=0;
					for ($i=$this->job['rscmaxbackups'];$i<sizeof($backupfilelist);$i++) {
						if ($backwpupcontainer->delete_object($this->job['rscdir'].$backupfilelist[$i])) //delte files on Cloud
							$numdeltefiles++;
						else
							trigger_error(__('Can not delete file on RSC://','backwpup').$this->job['rscContainer'].$this->job['rscdir'].$backupfilelist[$i],E_USER_ERROR);
					}
					if ($numdeltefiles>0)
						trigger_error($numdeltefiles.' '.__('files deleted on Racspase Cloud Container!','backwpup'),E_USER_NOTICE);
				}
			}	
		} catch (Exception $e) {
			trigger_error(__('Rackspase Cloud API:','backwpup').' '.$e->getMessage(),E_USER_ERROR);
		} 
	}
	
	public function destination_msazure() {
		
		if (!class_exists('Microsoft_WindowsAzure_Storage_Blob')) {
			set_include_path(get_include_path().PATH_SEPARATOR.dirname(__FILE__).'/libs');
			require_once 'Microsoft/WindowsAzure/Storage/Blob.php';
		}
		
		$this->need_free_memory(4194304*1.5); 
		
		try {
			$storageClient = new Microsoft_WindowsAzure_Storage_Blob($this->job['msazureHost'],$this->job['msazureAccName'],$this->job['msazureKey']);

			if(!$storageClient->containerExists($this->job['msazureContainer'])) {
				trigger_error(__('Microsoft Azure Container not exists:','backwpup').' '.$this->job['msazureContainer'],E_USER_ERROR);
				return;
			} else {
				trigger_error(__('Connected to Microsoft Azure Container:','backwpup').' '.$this->job['msazureContainer'],E_USER_NOTICE);
			}
				
			$result = $storageClient->putBlob($this->job['msazureContainer'], $this->job['msazuredir'].$this->backupfile, $this->backupdir.$this->backupfile);

			if ($result->Name==$this->job['msazuredir'].$this->backupfile) {
				trigger_error(__('Backup File transferred to azure://','backwpup').$this->job['msazuredir'].$this->backupfile,E_USER_NOTICE);
				$this->lastbackupdownloadurl='admin.php?page=BackWPup&subpage=backups&action=downloadmsazure&file='.$this->job['msazuredir'].$this->backupfile.'&jobid='.$this->jobid;
			} else {
				trigger_error(__('Can not transfer backup to Microsoft Azure.','backwpup'),E_USER_ERROR);
			}

			if ($this->job['msazuremaxbackups']>0) { //Delete old backups
				$backupfilelist=array();
				$blobs = $storageClient->listBlobs($this->job['msazureContainer'],$this->job['msazuredir']);
				if (is_array($blobs)) {
					foreach ($blobs as $blob) {
						$file=basename($blob->Name);
						if ($this->job['fileprefix'] == substr($file,0,strlen($this->job['fileprefix'])) and $this->backupfileformat == substr($file,-strlen($this->backupfileformat)))
							$backupfilelist[]=$file;
					}
				}
				if (sizeof($backupfilelist)>0) {
					rsort($backupfilelist);
					$numdeltefiles=0;
					for ($i=$this->job['msazuremaxbackups'];$i<sizeof($backupfilelist);$i++) {
						$storageClient->deleteBlob($this->job['msazureContainer'],$this->job['msazuredir'].$backupfilelist[$i]); //delte files on Cloud
						$numdeltefiles++;
					}
					if ($numdeltefiles>0)
						trigger_error($numdeltefiles.' '.__('files deleted on Microsoft Azure Container!','backwpup'),E_USER_NOTICE);
				}
			}
			
		} catch (Exception $e) {
			trigger_error(__('Microsoft Azure API:','backwpup').' '.$e->getMessage(),E_USER_ERROR);
		} 
	}
	
	public function destination_dir() {
		$this->lastbackupdownloadurl='admin.php?page=BackWPup&subpage=backups&action=download&file='.$this->backupdir.$this->backupfile;
		//Delete old Backupfiles
		$backupfilelist=array();
		if (!empty($this->job['maxbackups'])) {
			if ( $dir = @opendir($this->job['backupdir']) ) { //make file list
				while (($file = readdir($dir)) !== false ) {
					if ($this->job['fileprefix'] == substr($file,0,strlen($this->job['fileprefix'])) and $this->backupfileformat == substr($file,-strlen($this->backupfileformat)))
						$backupfilelist[]=$file;
				}
				@closedir( $dir );
			}
			if (sizeof($backupfilelist)>0) {
				rsort($backupfilelist);
				$numdeltefiles=0;
				for ($i=$this->job['maxbackups'];$i<sizeof($backupfilelist);$i++) {
					unlink(trailingslashit($this->job['backupdir']).$backupfilelist[$i]);
					$numdeltefiles++;
				}
				if ($numdeltefiles>0)
					trigger_error($numdeltefiles.' '.__('old backup files deleted!!!','backwpup'),E_USER_NOTICE);
			}
		}
	}
	
	public function destination_dropbox(){
		
		if (!class_exists('Dropbox'))
			require_once (dirname(__FILE__).'/libs/dropbox/dropbox.php');
		
		try {
			$dropbox = new Dropbox(BACKWPUP_DROPBOX_APP_KEY, BACKWPUP_DROPBOX_APP_SECRET);
			// set the tokens 
			$dropbox->setOAuthTokens($this->job['dropetoken'],$this->job['dropesecret']);
			$info=$dropbox->accountInfo();
			if (!empty($info['uid'])) {
				trigger_error(__('Authed to DropBox from ','backwpup').$info['display_name'],E_USER_NOTICE);
			}
			//Check Quota
			$dropboxfreespase=$info['quota_info']['quota']-$info['quota_info']['shared']-$info['quota_info']['normal'];
			if (filesize($this->backupdir.$this->backupfile)>$dropboxfreespase) {
				trigger_error(__('No free space left on DropBox!!!','backwpup'),E_USER_ERROR);
				return;
			} else {
				trigger_error(__('Free Space on DropBox: ','backwpup').backwpup_formatBytes($dropboxfreespase),E_USER_NOTICE);
			}
			// put the file 
			$response = $dropbox->upload($this->backupdir.$this->backupfile,$this->job['dropedir']); 
			if ($response['result']=="winner!") {
				$this->lastbackupdownloadurl='admin.php?page=BackWPup&subpage=backups&action=downloaddropbox&file='.$this->job['dropedir'].$this->backupfile.'&jobid='.$this->jobid;
				trigger_error(__('Backup File transferred to DropBox.','backwpup'),E_USER_NOTICE);
			} else {
				trigger_error(__('Can not transfere Backup file to DropBox:','backwpup').' '.$response['error'],E_USER_ERROR);
			}	

			if ($this->job['dropemaxbackups']>0) { //Delete old backups
				$backupfilelist=array();
				$metadata = $dropbox->metadata($this->job['dropedir']);
				if (is_array($metadata)) {
					foreach ($metadata['contents'] as $data) {
						$file=basename($data['path']);
						if ($data['is_dir']!=true and $this->job['fileprefix'] == substr($file,0,strlen($this->job['fileprefix'])) and $this->backupfileformat == substr($file,-strlen($this->backupfileformat)))
							$backupfilelist[]=$file;
					}
				}
				if (sizeof($backupfilelist)>0) {
					rsort($backupfilelist);
					$numdeltefiles=0;
					for ($i=$this->job['dropemaxbackups'];$i<sizeof($backupfilelist);$i++) {
						$dropbox->fileopsDelete($this->job['dropedir'].$backupfilelist[$i]); //delete files on Cloud
						$numdeltefiles++;
					}
					if ($numdeltefiles>0)
						trigger_error($numdeltefiles.' '.__('files deleted on DropBox Folder!','backwpup'),E_USER_NOTICE);
				}
			}	
		} catch (Exception $e) {
			trigger_error(__('DropBox API:','backwpup').' '.$e->getMessage(),E_USER_ERROR);
		} 
	}

	public function destination_sugarsync(){
		
		if (!class_exists('SugarSync'))
			require_once (dirname(__FILE__).'/libs/sugarsync.php');
		
		$this->need_free_memory(filesize($this->backupdir.$this->backupfile)*1.5); 
		
		try {
			$sugarsync = new SugarSync($this->job['sugaruser'],base64_decode($this->job['sugarpass']),BACKWPUP_SUGARSYNC_ACCESSKEY, BACKWPUP_SUGARSYNC_PRIVATEACCESSKEY);
			//Check Quota
			$user=$sugarsync->user();
			if (!empty($user->nickname)) {
				trigger_error(__('Authed to SugarSync with Nick ','backwpup').$user->nickname,E_USER_NOTICE);
			}
			$sugarsyncfreespase=$user->quota->limit-$user->quota->usage;
			if (filesize($this->backupdir.$this->backupfile)>$sugarsyncfreespase) {
				trigger_error(__('No free space left on SugarSync!!!','backwpup'),E_USER_ERROR);
				return;
			} else {
				trigger_error(__('Free Space on SugarSync: ','backwpup').backwpup_formatBytes($sugarsyncfreespase),E_USER_NOTICE);
			}
			//Create and change folder
			$sugarsync->mkdir($this->job['sugardir'],$this->job['sugarroot']);
			$sugarsync->chdir($this->job['sugardir'],$this->job['sugarroot']);
			//Upload to Sugarsync
			$reponse=$sugarsync->upload($this->backupdir.$this->backupfile);
			if (is_object($reponse)) {
				$this->lastbackupdownloadurl='admin.php?page=BackWPup&subpage=backups&action=downloadsugarsync&file='.(string)$reponse.'&jobid='.$this->jobid;
				trigger_error(__('Backup File transferred to SugarSync.','backwpup'),E_USER_NOTICE);
			} else {
				trigger_error(__('Can not transfere Backup file to SugarSync:','backwpup'),E_USER_ERROR);
			}	

			if ($this->job['sugarmaxbackups']>0) { //Delete old backups
				$backupfilelist=array();
				$getfiles=$sugarsync->getcontents('file');
				if (is_object($getfiles)) {
					foreach ($getfiles->file as $getfile) {
						if ($this->job['fileprefix'] == substr($getfile->displayName,0,strlen($this->job['fileprefix'])) and $this->backupfileformat == substr($getfile->displayName,-strlen($this->backupfileformat)))
							$backupfilelist[]=$getfile->displayName;
							$backupfileref[utf8_encode($getfile->displayName)]=$getfile->ref;
					}
				}
				if (sizeof($backupfilelist)>0) {
					rsort($backupfilelist);
					$numdeltefiles=0;
					for ($i=$this->job['sugarmaxbackups'];$i<sizeof($backupfilelist);$i++) {
						$sugarsync->delete($backupfileref[utf8_encode($backupfilelist[$i])]); //delete files on Cloud
						$numdeltefiles++;
					}
					if ($numdeltefiles>0)
						trigger_error($numdeltefiles.' '.__('files deleted on Sugarsync Folder!','backwpup'),E_USER_NOTICE);
				}
			}	
		} catch (Exception $e) {
			trigger_error(__('SugarSync API:','backwpup').' '.$e->getMessage(),E_USER_ERROR);
		} 
	}

	
	public function job_end($logfile ='') {
		if (empty($logfile)) 
			$logfile=$this->logdir.$this->logfile;
		
		if ($logfile==$this->logdir.$this->logfile) {
			//delete old logs
			if (!empty($this->cfg['maxlogs'])) {
				if ( $dir = opendir($this->logdir) ) { //make file list
					while (($file = readdir($dir)) !== false ) {
						if ('backwpup_log_' == substr($file,0,strlen('backwpup_log_')) and (".html" == substr($file,-5) or ".html.gz" == substr($file,-8)))
							$logfilelist[]=$file;
					}
					closedir( $dir );
				}
				if (sizeof($logfilelist)>0) {
					rsort($logfilelist);
					$numdeltefiles=0;
					for ($i=$this->cfg['maxlogs'];$i<sizeof($logfilelist);$i++) {
						unlink($this->logdir.$logfilelist[$i]);
						$numdeltefiles++;
					}
					if ($numdeltefiles>0)
						trigger_error($numdeltefiles.' '.__('old Log files deleted!!!','backwpup'),E_USER_NOTICE);
				}
			}
			trigger_error(sprintf(__('Job done in %1s sec.','backwpup'),current_time('timestamp')-$this->job['starttime']),E_USER_NOTICE);	
		}
		restore_error_handler();
		
		if (!($filesize=@filesize($this->backupdir.$this->backupfile))) //Set the filezie corectly
			$filesize=0;

		//clean up
		@unlink($this->tempdir.DB_NAME.'.sql');
		@unlink($this->tempdir.preg_replace( '/[^a-z0-9_\-]/', '', strtolower(get_bloginfo('name')) ).'.wordpress.' . date( 'Y-m-d' ) . '.xml');

		if (empty($this->job['backupdir']) and is_file($this->backupdir.$this->backupfile))  //delete backup file in temp dir
			unlink($this->backupdir.$this->backupfile);
		
		$jobs=get_option('backwpup_jobs');
		$jobs[$this->jobid]['lastrun']=$jobs[$this->jobid]['starttime'];
		$jobs[$this->jobid]['lastruntime']=current_time('timestamp')-$jobs[$this->jobid]['starttime'];
		$jobs[$this->jobid]['logfile']='';
		$jobs[$this->jobid]['lastlogfile']=$logfile;
		$jobs[$this->jobid]['starttime']='';
		if (!empty($this->lastbackupdownloadurl))
			$jobs[$this->jobid]['lastbackupdownloadurl']=$this->lastbackupdownloadurl;
		else
			$jobs[$this->jobid]['lastbackupdownloadurl']='';
		update_option('backwpup_jobs',$jobs); //Save Settings
		
		$this->job['lastrun']=$jobs[$this->jobid]['lastrun'];
		$this->job['lastruntime']=$jobs[$this->jobid]['lastruntime'];
		
		//write heder info
		$fd=fopen($logfile,'r+');
		$found=0;
		while (!feof($fd)) {
			$line=fgets($fd);
			if (stripos($line,"<meta name=\"backwpup_jobruntime\"") !== false) {
				fseek($fd,$filepos);
				fwrite($fd,str_pad("<meta name=\"backwpup_jobruntime\" content=\"".$this->job['lastruntime']."\" />",100)."\n");
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
		
		//logfile end
		$fd=fopen($logfile,'a');
		fwrite($fd,"</body>\n</html>\n");
		fclose($fd);
		
		//gzip logfile
		if ($this->cfg['gzlogs']) {
			$fd=fopen($logfile,'r');
			$zd=gzopen($logfile.'.gz','w9');
			while (!feof($fd)) {
				gzwrite($zd,fread($fd,4096));
			}
			gzclose($zd);
			fclose($fd);
			unlink($logfile);
			$logfile=$logfile.'.gz';
			$jobs=get_option('backwpup_jobs');
			$jobs[$this->jobid]['lastlogfile']=$logfile;
			update_option('backwpup_jobs',$jobs); //Save Settings
		}
		
		$logdata=backwpup_read_logheader($logfile);
		
		//Send mail with log
		$sendmail=false;
		if ($logdata['errors']>0 and $this->job['mailerroronly'] and !empty($this->job['mailaddresslog']))
			$sendmail=true;
		if (!$this->job['mailerroronly'] and !empty($this->job['mailaddresslog']))
			$sendmail=true;
		if ($sendmail) {
			//Create PHP Mailer
			require_once(ABSPATH.WPINC.'/class-phpmailer.php');
			$phpmailer = new PHPMailer();
			//Setting den methode
			if ($this->cfg['mailmethod']=="SMTP") {
				require_once(ABSPATH.WPINC.'/class-smtp.php');
				$smtpport=25;
				$smtphost=$this->cfg['mailhost'];
				if (false !== strpos($this->cfg['mailhost'],':')) //look for port
					list($smtphost,$smtpport)=explode(':',$this->cfg['mailhost'],2);
				$phpmailer->Host=$smtphost;
				$phpmailer->Port=$smtpport;
				$phpmailer->SMTPSecure=$this->cfg['mailsecure'];
				$phpmailer->Username=$this->cfg['mailuser'];
				$phpmailer->Password=base64_decode($this->cfg['mailpass']);
				if (!empty($this->cfg['mailuser']) and !empty($this->cfg['mailpass']))
					$phpmailer->SMTPAuth=true;
				$phpmailer->IsSMTP();
			} elseif ($this->cfg['mailmethod']=="Sendmail") {
				$phpmailer->Sendmail=$this->cfg['mailsendmail'];
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
			
			$phpmailer->From     = $this->cfg['mailsndemail'];
			$phpmailer->FromName = $this->cfg['mailsndname'];
			$phpmailer->AddAddress($this->job['mailaddresslog']);
			$phpmailer->Subject  =  __('BackWPup Log from','backwpup').' '.date_i18n('Y-m-d H:i',$this->job['lastrun']).': '.$this->job['name'];
			$phpmailer->IsHTML(false);
			$phpmailer->Body  =  $mailbody;
			$phpmailer->AddAttachment($logfile);
			$phpmailer->Send();
		}
	}
}
?>