<?PHP
function backwpup_job_start($jobid='') {
	global $wpdb,$wp_version,$backwpupjobrun;

	//clean var
	$backwpupjobrun = array();
	//get and create temp dir
	$backwpupjobrun['STATIC']['TEMPDIR']=backwpup_get_temp();
	if (!is_dir($backwpupjobrun['STATIC']['TEMPDIR'])) {
		if (!mkdir(rtrim($backwpupjobrun['STATIC']['TEMPDIR'],'/'),0777,true)) {
			printf(__('Can not create temp folder: %s','backwpup'),$backwpupjobrun['STATIC']['TEMPDIR']);
			return false;
		}
	}
	if (!is_writable($backwpupjobrun['STATIC']['TEMPDIR'])) {
		_e("Temp dir not writeable","backwpup");
		return false;
	} else {  //clean up old temp files
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
		//create .htaccess for apache and index.html for other
		if (strtolower(substr($_SERVER["SERVER_SOFTWARE"],0,6))=="apache") {  //check if it a apache webserver
			if (!is_file($backwpupjobrun['STATIC']['TEMPDIR'].'.htaccess'))
				file_put_contents($backwpupjobrun['STATIC']['TEMPDIR'].'.htaccess',"Order allow,deny\ndeny from all");
		} else {
			if (!is_file($backwpupjobrun['STATIC']['TEMPDIR'].'index.html'))
				file_put_contents($backwpupjobrun['STATIC']['TEMPDIR'].'index.html',"\n");
			if (!is_file($backwpupjobrun['STATIC']['TEMPDIR'].'index.php'))
				file_put_contents($backwpupjobrun['STATIC']['TEMPDIR'].'index.php',"\n");
		}
	}

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
	if (!is_dir($backwpupjobrun['STATIC']['CFG']['dirlogs'])) {
		if (!mkdir(rtrim($backwpupjobrun['STATIC']['CFG']['dirlogs'],'/'),0777,true)) {
			printf(__('Can not create folder for log files: %s','backwpup'),$backwpupjobrun['STATIC']['CFG']['dirlogs']);
			return false;
		}
		//create .htaccess for apache and index.html for other
		if (strtolower(substr($_SERVER["SERVER_SOFTWARE"],0,6))=="apache") {  //check if it a apache webserver
			if (!is_file($backwpupjobrun['STATIC']['CFG']['dirlogs'].'.htaccess'))
				file_put_contents($backwpupjobrun['STATIC']['CFG']['dirlogs'].'.htaccess',"Order allow,deny\ndeny from all");
		} else {
			if (!is_file($backwpupjobrun['STATIC']['CFG']['dirlogs'].'index.html'))
				file_put_contents($backwpupjobrun['STATIC']['CFG']['dirlogs'].'index.html',"\n");
			if (!is_file($backwpupjobrun['STATIC']['CFG']['dirlogs'].'index.php'))
				file_put_contents($backwpupjobrun['STATIC']['CFG']['dirlogs'].'index.php',"\n");
		}
	}
	if (!is_writable($backwpupjobrun['STATIC']['CFG']['dirlogs'])) {
		_e("Log folder not writeable!","backwpup");
		return false;
	}
	//set Logfile
	$backwpupjobrun['LOGFILE']=$backwpupjobrun['STATIC']['CFG']['dirlogs'].'backwpup_log_'.date_i18n('Y-m-d_H-i-s').'.html';
	//create log file
	$fd=fopen($backwpupjobrun['LOGFILE'],'w');
	//Create log file header
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
	fclose($fd);
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
	//only for jos that makes backups
	if (in_array('FILE',$backwpupjobrun['STATIC']['TODO']) or in_array('DB',$backwpupjobrun['STATIC']['TODO']) or in_array('WPEXP',$backwpupjobrun['STATIC']['TODO'])) {
		//make emty file list
		$backwpupjobrun['WORKING']['ALLFILESIZE']=0;
        $backwpupjobrun['WORKING']['backupfilesize']=0;
		//set Backup Dir if not set
		if (empty($backwpupjobrun['STATIC']['JOB']['backupdir']) or $backwpupjobrun['STATIC']['JOB']['backupdir']=='/') {
			$backwpupjobrun['STATIC']['JOB']['backupdir']=$backwpupjobrun['STATIC']['TEMPDIR'];
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
		$backwpupjobrun['WORKING']['STEPS'][]='BACKUP_CREATE';
		//ADD Destinations
		if (!empty($backwpupjobrun['STATIC']['JOB']['backupdir']) and $backwpupjobrun['STATIC']['JOB']['backupdir']!='/' and $backwpupjobrun['STATIC']['JOB']['backupdir']!=$backwpupjobrun['STATIC']['TEMPDIR'])
			$backwpupjobrun['WORKING']['STEPS'][]='DEST_FOLDER';
		if (!empty($backwpupjobrun['STATIC']['JOB']['mailaddress']))
			$backwpupjobrun['WORKING']['STEPS'][]='DEST_MAIL';
		if (!empty($backwpupjobrun['STATIC']['JOB']['ftphost']) and !empty($backwpupjobrun['STATIC']['JOB']['ftpuser']) and !empty($backwpupjobrun['STATIC']['JOB']['ftppass']) and in_array('FTP',explode(',',strtoupper(BACKWPUP_DESTS))))
			$backwpupjobrun['WORKING']['STEPS'][]='DEST_FTP';
		if (!empty($backwpupjobrun['STATIC']['JOB']['dropetoken']) and !empty($backwpupjobrun['STATIC']['JOB']['dropesecret']) and in_array('DROPBOX',explode(',',strtoupper(BACKWPUP_DESTS))))
			$backwpupjobrun['WORKING']['STEPS'][]='DEST_DROPBOX';
		if (!empty($backwpupjobrun['STATIC']['JOB']['sugaruser']) and !empty($backwpupjobrun['STATIC']['JOB']['sugarpass']) and !empty($backwpupjobrun['STATIC']['JOB']['sugarroot']) and in_array('SUGARSYNC',explode(',',strtoupper(BACKWPUP_DESTS))))
			$backwpupjobrun['WORKING']['STEPS'][]='DEST_SUGARSYNC';
		if (!empty($backwpupjobrun['STATIC']['JOB']['awsAccessKey']) and !empty($backwpupjobrun['STATIC']['JOB']['awsSecretKey']) and !empty($backwpupjobrun['STATIC']['JOB']['awsBucket']) and in_array('S3',explode(',',strtoupper(BACKWPUP_DESTS))))
			$backwpupjobrun['WORKING']['STEPS'][]='DEST_S3';
		if (!empty($backwpupjobrun['STATIC']['JOB']['GStorageAccessKey']) and !empty($backwpupjobrun['STATIC']['JOB']['GStorageSecret']) and !empty($backwpupjobrun['STATIC']['JOB']['GStorageBucket']) and in_array('GSTORAGE',explode(',',strtoupper(BACKWPUP_DESTS))))
			$backwpupjobrun['WORKING']['STEPS'][]='DEST_GSTORAGE';
		if (!empty($backwpupjobrun['STATIC']['JOB']['rscUsername']) and !empty($backwpupjobrun['STATIC']['JOB']['rscAPIKey']) and !empty($backwpupjobrun['STATIC']['JOB']['rscContainer']) and in_array('RSC',explode(',',strtoupper(BACKWPUP_DESTS))))
			$backwpupjobrun['WORKING']['STEPS'][]='DEST_RSC';
		if (!empty($backwpupjobrun['STATIC']['JOB']['msazureHost']) and !empty($backwpupjobrun['STATIC']['JOB']['msazureAccName']) and !empty($backwpupjobrun['STATIC']['JOB']['msazureKey']) and !empty($backwpupjobrun['STATIC']['JOB']['msazureContainer']) and in_array('MSAZURE',explode(',',strtoupper(BACKWPUP_DESTS))))
			$backwpupjobrun['WORKING']['STEPS'][]='DEST_MSAZURE';
	}
	if (in_array('CHECK',$backwpupjobrun['STATIC']['TODO']))
		$backwpupjobrun['WORKING']['STEPS'][]='DB_CHECK';
	if (in_array('OPTIMIZE',$backwpupjobrun['STATIC']['TODO']))
		$backwpupjobrun['WORKING']['STEPS'][]='DB_OPTIMIZE';
	$backwpupjobrun['WORKING']['STEPS'][]='JOB_END';
	//mark all as not done
	foreach($backwpupjobrun['WORKING']['STEPS'] as $step)
		$backwpupjobrun['WORKING'][$step]['DONE']=false;
	//write working file
	update_option('backwpup_job_working',$backwpupjobrun);

}
?>