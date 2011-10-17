<?PHP
function backwpup_job_start($jobid,$jobstarttype) {
	global $wpdb,$wp_version,$backwpupjobrun;
	//clean var
	$backwpupjobrun = array();
	//get temp dir
	$backwpupjobrun['STATIC']['TEMPDIR']=backwpup_get_temp();
	//Set config data
	$backwpupjobrun['STATIC']['CFG']=get_option('backwpup');
	//check exists gzip functions
	if(!function_exists('gzopen'))
		$backwpupjobrun['STATIC']['CFG']['gzlogs']=false;
	if(!class_exists('ZipArchive'))
		$backwpupjobrun['STATIC']['CFG']['phpzip']=false;
	//Set job data
	$backwpupjobrun['STATIC']['JOB']=backwpup_get_job_vars($jobid);
	//Setup Logs dir
	$backwpupjobrun['STATIC']['CFG']['dirlogs']=rtrim(str_replace('\\','/',$backwpupjobrun['STATIC']['CFG']['dirlogs']),'/').'/';
	//set Logfile
	$backwpupjobrun['LOGFILE']=$backwpupjobrun['STATIC']['CFG']['dirlogs'].'backwpup_log_'.date_i18n('Y-m-d_H-i-s').'.html';
	//Set job start settings
	$jobs=get_option('backwpup_jobs');
	$jobs[$backwpupjobrun['STATIC']['JOB']['jobid']]['starttime']=current_time('timestamp'); //set start time for job
	$backwpupjobrun['STATIC']['JOB']['starttime']=$jobs[$backwpupjobrun['STATIC']['JOB']['jobid']]['starttime'];
	$jobs[$backwpupjobrun['STATIC']['JOB']['jobid']]['logfile']=$backwpupjobrun['LOGFILE'];	   //Set current logfile
	$jobs[$backwpupjobrun['STATIC']['JOB']['jobid']]['cronnextrun']=backwpup_cron_next($jobs[$backwpupjobrun['STATIC']['JOB']['jobid']]['cron']);  //set next run
	$backwpupjobrun['STATIC']['JOB']['cronnextrun']=$jobs[$backwpupjobrun['STATIC']['JOB']['jobid']]['cronnextrun'];
	$jobs[$backwpupjobrun['STATIC']['JOB']['jobid']]['lastbackupdownloadurl']='';
	$backwpupjobrun['STATIC']['JOB']['lastbackupdownloadurl']='';
	update_option('backwpup_jobs',$jobs); //Save job Settings
	//Set todo
	$backwpupjobrun['STATIC']['TODO']=explode('+',$backwpupjobrun['STATIC']['JOB']['type']);
	//only for jobs that makes backups
	if (in_array('FILE',$backwpupjobrun['STATIC']['TODO']) or in_array('DB',$backwpupjobrun['STATIC']['TODO']) or in_array('WPEXP',$backwpupjobrun['STATIC']['TODO'])) {
		//make emty file list
		$backwpupjobrun['WORKING']['ALLFILESIZE']=0;
        $backwpupjobrun['WORKING']['backupfilesize']=0;
		if ($backwpupjobrun['STATIC']['JOB']['backuptype']=='archive') {
			//set Backup Dir if not set
			if (empty($backwpupjobrun['STATIC']['JOB']['backupdir']) or $backwpupjobrun['STATIC']['JOB']['backupdir']=='/') {
				$backwpupjobrun['STATIC']['JOB']['backupdir']=$backwpupjobrun['STATIC']['TEMPDIR'];
				$backwpupjobrun['WORKING']['TEMPFILES'][]=$backwpupjobrun['STATIC']['TEMPDIR'].$backwpupjobrun['STATIC']['backupfile'];
			} else {
				//clear path
				$backwpupjobrun['STATIC']['JOB']['backupdir']=rtrim(str_replace('\\','/',$backwpupjobrun['STATIC']['JOB']['backupdir']),'/').'/';
				//create backup dir if it not exists
				if (!is_dir($backwpupjobrun['STATIC']['JOB']['backupdir'])) {
					if (!mkdir(rtrim($backwpupjobrun['STATIC']['JOB']['backupdir'],'/'),0777,true)) {
						sprintf(__('Can not create folder for backups: %1$s','backwpup'),$backwpupjobrun['STATIC']['JOB']['backupdir']);
						return false;
					}
					//create .htaccess for apache and index.html for other
					if (strtolower(substr($_SERVER["SERVER_SOFTWARE"],0,6))=="apache") {  //check if it a apache webserver
						if (!is_file($backwpupjobrun['STATIC']['JOB']['backupdir'].'.htaccess'))
							file_put_contents($backwpupjobrun['STATIC']['JOB']['backupdir'].'.htaccess',"Order allow,deny\ndeny from all");
					} else {
						if (!is_file($backwpupjobrun['STATIC']['JOB']['backupdir'].'index.html'))
							file_put_contents($backwpupjobrun['STATIC']['JOB']['backupdir'].'index.html',"\n");
						if (!is_file($backwpupjobrun['STATIC']['JOB']['backupdir'].'index.php'))
							file_put_contents($backwpupjobrun['STATIC']['JOB']['backupdir'].'index.php',"\n");
					}
				}
			}
			//check backup dir
			if (!is_writable($backwpupjobrun['STATIC']['JOB']['backupdir'])) {
				_e("Backup folder not writeable!","backwpup");
				return false;
			}
			$backwpupjobrun['STATIC']['backupfile']=$backwpupjobrun['STATIC']['JOB']['fileprefix'].date_i18n('Y-m-d_H-i-s').$backwpupjobrun['STATIC']['JOB']['fileformart'];
		}
	}
	$backwpupjobrun['WORKING']['PID']=0;
	$backwpupjobrun['WORKING']['WARNING']=0;
	$backwpupjobrun['WORKING']['ERROR']=0;
	$backwpupjobrun['WORKING']['RESTART']=0;
	$backwpupjobrun['WORKING']['STEPSDONE']=array();
	$backwpupjobrun['WORKING']['STEPTODO']=0;
	$backwpupjobrun['WORKING']['STEPDONE']=0;
	$backwpupjobrun['WORKING']['STEPSPERSENT']=0;
	$backwpupjobrun['WORKING']['STEPPERSENT']=0;
	$backwpupjobrun['WORKING']['TIMESTAMP']=current_time('timestamp');
	$backwpupjobrun['WORKING']['JOBENDINPROGRESS']=false;
	//build working steps
	$backwpupjobrun['WORKING']['STEPS']=array();
	//setup job steps
	if (in_array('DB',$backwpupjobrun['STATIC']['TODO']))
		$backwpupjobrun['WORKING']['STEPS'][]='DB_DUMP';
	if (in_array('WPEXP',$backwpupjobrun['STATIC']['TODO']))
		$backwpupjobrun['WORKING']['STEPS'][]='WP_EXPORT';
	if (in_array('FILE',$backwpupjobrun['STATIC']['TODO']))
		$backwpupjobrun['WORKING']['STEPS'][]='FILE_LIST';
	if (in_array('DB',$backwpupjobrun['STATIC']['TODO']) or in_array('WPEXP',$backwpupjobrun['STATIC']['TODO']) or in_array('FILE',$backwpupjobrun['STATIC']['TODO'])) {
		if ($backwpupjobrun['STATIC']['JOB']['backuptype']=='archive') {
			$backwpupjobrun['WORKING']['STEPS'][]='BACKUP_CREATE';
			$backuptypeextension='';
		} elseif ($backwpupjobrun['STATIC']['JOB']['backuptype']=='sync') {
			$backuptypeextension='_SYNC';
		}
		//ADD Destinations
		if (!empty($backwpupjobrun['STATIC']['JOB']['backupdir']) and $backwpupjobrun['STATIC']['JOB']['backupdir']!='/' and $backwpupjobrun['STATIC']['JOB']['backupdir']!=$backwpupjobrun['STATIC']['TEMPDIR'])
			$backwpupjobrun['WORKING']['STEPS'][]='DEST_FOLDER'.$backuptypeextension;
		if (!empty($backwpupjobrun['STATIC']['JOB']['mailaddress']) and $backwpupjobrun['STATIC']['JOB']['backuptype']=='archive')
			$backwpupjobrun['WORKING']['STEPS'][]='DEST_MAIL';
		if (!empty($backwpupjobrun['STATIC']['JOB']['ftphost']) and !empty($backwpupjobrun['STATIC']['JOB']['ftpuser']) and !empty($backwpupjobrun['STATIC']['JOB']['ftppass']) and in_array('FTP',explode(',',strtoupper(BACKWPUP_DESTS))))
			$backwpupjobrun['WORKING']['STEPS'][]='DEST_FTP'.$backuptypeextension;
		if (!empty($backwpupjobrun['STATIC']['JOB']['dropetoken']) and !empty($backwpupjobrun['STATIC']['JOB']['dropesecret']) and in_array('DROPBOX',explode(',',strtoupper(BACKWPUP_DESTS))))
			$backwpupjobrun['WORKING']['STEPS'][]='DEST_DROPBOX'.$backuptypeextension;
		if (!empty($backwpupjobrun['STATIC']['JOB']['sugaruser']) and !empty($backwpupjobrun['STATIC']['JOB']['sugarpass']) and !empty($backwpupjobrun['STATIC']['JOB']['sugarroot']) and in_array('SUGARSYNC',explode(',',strtoupper(BACKWPUP_DESTS))))
			$backwpupjobrun['WORKING']['STEPS'][]='DEST_SUGARSYNC'.$backuptypeextension;
		if (!empty($backwpupjobrun['STATIC']['JOB']['awsAccessKey']) and !empty($backwpupjobrun['STATIC']['JOB']['awsSecretKey']) and !empty($backwpupjobrun['STATIC']['JOB']['awsBucket']) and in_array('S3',explode(',',strtoupper(BACKWPUP_DESTS))))
			$backwpupjobrun['WORKING']['STEPS'][]='DEST_S3'.$backuptypeextension;
		if (!empty($backwpupjobrun['STATIC']['JOB']['GStorageAccessKey']) and !empty($backwpupjobrun['STATIC']['JOB']['GStorageSecret']) and !empty($backwpupjobrun['STATIC']['JOB']['GStorageBucket']) and in_array('GSTORAGE',explode(',',strtoupper(BACKWPUP_DESTS))))
			$backwpupjobrun['WORKING']['STEPS'][]='DEST_GSTORAGE'.$backuptypeextension;
		if (!empty($backwpupjobrun['STATIC']['JOB']['rscUsername']) and !empty($backwpupjobrun['STATIC']['JOB']['rscAPIKey']) and !empty($backwpupjobrun['STATIC']['JOB']['rscContainer']) and in_array('RSC',explode(',',strtoupper(BACKWPUP_DESTS))))
			$backwpupjobrun['WORKING']['STEPS'][]='DEST_RSC'.$backuptypeextension;
		if (!empty($backwpupjobrun['STATIC']['JOB']['msazureHost']) and !empty($backwpupjobrun['STATIC']['JOB']['msazureAccName']) and !empty($backwpupjobrun['STATIC']['JOB']['msazureKey']) and !empty($backwpupjobrun['STATIC']['JOB']['msazureContainer']) and in_array('MSAZURE',explode(',',strtoupper(BACKWPUP_DESTS))))
			$backwpupjobrun['WORKING']['STEPS'][]='DEST_MSAZURE'.$backuptypeextension;
	}
	if (in_array('CHECK',$backwpupjobrun['STATIC']['TODO']))
		$backwpupjobrun['WORKING']['STEPS'][]='DB_CHECK';
	if (in_array('OPTIMIZE',$backwpupjobrun['STATIC']['TODO']))
		$backwpupjobrun['WORKING']['STEPS'][]='DB_OPTIMIZE';
	$backwpupjobrun['WORKING']['STEPS'][]='JOB_END';
	//mark all as not done
	foreach($backwpupjobrun['WORKING']['STEPS'] as $step)
		$backwpupjobrun['WORKING'][$step]['DONE']=false;
	//write working date
	set_transient( 'backwpup_job_working', $backwpupjobrun, BACKWPUP_JOB_TRANSIENT_LIVETIME );
	//create log file
	$fd=fopen($backwpupjobrun['LOGFILE'],'w');
	fwrite($fd,"<html>\n<head>\n");
	fwrite($fd,"<meta name=\"backwpup_version\" content=\"".BACKWPUP_VERSION."\" />\n");
	fwrite($fd,"<meta name=\"backwpup_logtime\" content=\"".current_time('timestamp')."\" />\n");
	fwrite($fd,str_pad("<meta name=\"backwpup_errors\" content=\"0\" />",100)."\n");
	fwrite($fd,str_pad("<meta name=\"backwpup_warnings\" content=\"0\" />",100)."\n");
	fwrite($fd,"<meta name=\"backwpup_jobid\" content=\"".$backwpupjobrun['STATIC']['JOB']['jobid']."\" />\n");
	fwrite($fd,"<meta name=\"backwpup_jobname\" content=\"".$backwpupjobrun['STATIC']['JOB']['name']."\" />\n");
	fwrite($fd,"<meta name=\"backwpup_jobtype\" content=\"".$backwpupjobrun['STATIC']['JOB']['type']."\" />\n");
	fwrite($fd,str_pad("<meta name=\"backwpup_backupfilesize\" content=\"0\" />",100)."\n");
	fwrite($fd,str_pad("<meta name=\"backwpup_jobruntime\" content=\"0\" />",100)."\n");
	fwrite($fd,"<style type=\"text/css\">\n");
	fwrite($fd,".timestamp {background-color:grey;}\n");
	fwrite($fd,".warning {background-color:yellow;}\n");
	fwrite($fd,".error {background-color:red;}\n");
	fwrite($fd,"#body {font-family:monospace;font-size:12px;white-space:nowrap;}\n");
	fwrite($fd,"</style>\n");
	fwrite($fd,"<title>".sprintf(__('BackWPup log for %1$s from %2$s at %3$s','backwpup'),$backwpupjobrun['STATIC']['JOB']['name'],date_i18n(get_option('date_format')),date_i18n(get_option('time_format')))."</title>\n</head>\n<body id=\"body\">\n");
	fwrite($fd,sprintf(__('[INFO]: BackWPup version %1$s, WordPress version %4$s Copyright &copy; %2$s %3$s'),BACKWPUP_VERSION,date_i18n('Y'),'<a href="http://danielhuesken.de" target="_blank">Daniel H&uuml;sken</a>',$wp_version)."<br />\n");
	fwrite($fd,__('[INFO]: BackWPup comes with ABSOLUTELY NO WARRANTY. This is free software, and you are welcome to redistribute it under certain conditions.','backwpup')."<br />\n");
	fwrite($fd,__('[INFO]: BackWPup job:','backwpup').' '.$backwpupjobrun['STATIC']['JOB']['jobid'].'. '.$backwpupjobrun['STATIC']['JOB']['name'].'; '.$backwpupjobrun['STATIC']['JOB']['type']."<br />\n");
	if ($backwpupjobrun['STATIC']['JOB']['activated'])
		fwrite($fd,__('[INFO]: BackWPup cron:','backwpup').' '.$backwpupjobrun['STATIC']['JOB']['cron'].'; '.date_i18n('D, j M Y @ H:i',$backwpupjobrun['STATIC']['JOB']['cronnextrun'])."<br />\n");
	if ($jobstarttype=='cronrun')
		fwrite($fd,__('[INFO]: BackWPup job strated by cron','backwpup')."<br />\n");
	elseif ($jobstarttype=='runnow')
		fwrite($fd,__('[INFO]: BackWPup job strated manualy','backwpup')."<br />\n");
	fwrite($fd,__('[INFO]: PHP ver.:','backwpup').' '.phpversion().'; '.php_sapi_name().'; '.PHP_OS."<br />\n");
	if ((bool)ini_get('safe_mode'))
		fwrite($fd,sprintf(__('[INFO]: PHP Safe mode is ON! Maximum script execution time is %1$d sec.','backwpup'),ini_get('max_execution_time'))."<br />\n");
	fwrite($fd,sprintf(__('[INFO]: MySQL ver.: %s','backwpup'),mysql_result(mysql_query("SELECT VERSION() AS version"),0))."<br />\n");
	if (function_exists('curl_init')) {
		$curlversion=curl_version();
		fwrite($fd,sprintf(__('[INFO]: curl ver.: %1$s; %2$s','backwpup'),$curlversion['version'],$curlversion['ssl_version'])."<br />\n");
	}
	fwrite($fd,sprintf(__('[INFO]: Temp folder is: %s','backwpup'),$backwpupjobrun['STATIC']['TEMPDIR'])."<br />\n");
	fwrite($fd,sprintf(__('[INFO]: Logfile folder is: %s','backwpup'),$backwpupjobrun['STATIC']['CFG']['dirlogs'])."<br />\n");
	fwrite($fd,sprintf(__('[INFO]: Backup type is: %s','backwpup'),$backwpupjobrun['STATIC']['JOB']['backuptype'])."<br />\n");
	if(!empty($backwpupjobrun['STATIC']['backupfile']) and $backwpupjobrun['STATIC']['JOB']['backuptype']=='archive')
		fwrite($fd,sprintf(__('[INFO]: Backup file is: %s','backwpup'),$backwpupjobrun['STATIC']['JOB']['backupdir'].$backwpupjobrun['STATIC']['backupfile'])."<br />\n");
	//test for destinations
	if (in_array('DB',$backwpupjobrun['STATIC']['TODO']) or in_array('WPEXP',$backwpupjobrun['STATIC']['TODO']) or in_array('FILE',$backwpupjobrun['STATIC']['TODO'])) {
		$desttest=false;
		foreach($backwpupjobrun['WORKING']['STEPS'] as $deststeptest) {
			if (substr($deststeptest,0,5)=='DEST_') {
				$desttest=true;
				break;
			}
		}
		if (!$desttest) {
			fwrite($fd,"<span class=\"timestamp\" title=\"[Line: ".__LINE__."|File: ".basename(__FILE__)."|Mem: ".backwpup_formatBytes(@memory_get_usage(true))."|Mem Max: ".backwpup_formatBytes(@memory_get_peak_usage(true))."|Mem Limit: ".ini_get('memory_limit')."|PID: ".getmypid()."]\">".date_i18n('Y/m/d H:i.s').":</span> <span class=\"error\">".__('[ERROR]','backwpup').__('No destination defineid for backup!!! Please correct job settings','backwpup')."</span><br />\n");
		    $backwpupjobrun['WORKING']['ERROR']=1;
		}
	}
	fclose($fd);
}
?>