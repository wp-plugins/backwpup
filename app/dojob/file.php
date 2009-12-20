<?PHP 
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');

	global $backwupu_exclude ,$backwpup_allfilezise, $backwpup_jobs;
	$backwpup_jobs=$jobs[$jobid];
	backwpup_joblog($logtime,__('Run file backup...','backwpup'));
	backwpup_joblog($logtime,__('Get files to backup...','backwpup'));
	
	// helper function to scan dirs recursive
	function backwpup_list_files( $folder = '', $levels = 100 ) {  
		global $backwupu_exclude ,$backwpup_allfilezise, $backwpup_jobs, $logtime;
		if( empty($folder) )
			return false;
		if( ! $levels )
			return false;
		if ( $dir = @opendir( $folder ) ) {
			while (($file = readdir( $dir ) ) !== false ) {
				if ( in_array($file, array('.', '..','.svn') ) )
					continue;
				foreach ($backwupu_exclude as $exclusion) { //exclude dirs and files
					if (false !== stripos($folder.'/'.$file,str_replace('\\','/',$exclusion)))
						continue 2;
				}
				if (!$backwpup_jobs['backuproot'] and false !== stripos($folder.'/'.$file,str_replace('\\','/',ABSPATH)) and false === stripos($folder.'/'.$file,str_replace('\\','/',WP_CONTENT_DIR)) and !is_dir($folder.'/'.$file))
					continue;
				if (!$backwpup_jobs['backupcontent'] and false !== stripos($folder.'/'.$file,str_replace('\\','/',WP_CONTENT_DIR)) and false === stripos($folder.'/'.$file,str_replace('\\','/',WP_PLUGIN_DIR)) and !is_dir($folder.'/'.$file))
					continue;
				if (!$backwpup_jobs['backupplugins'] and false !== stripos($folder.'/'.$file,str_replace('\\','/',WP_PLUGIN_DIR)))
					continue;
				if ( is_dir( $folder . '/' . $file ) ) {
					$files .= ",".backwpup_list_files( $folder . '/' . $file, $levels - 1);
				} elseif (is_file( $folder . '/' . $file )) {
					if (is_readable($folder . '/' . $file)) {
						$files.=",". $folder . '/' . $file;
						$filezise=filesize($folder . '/' . $file);
						$backwpup_allfilezise=$backwpup_allfilezise+$filezise;
						backwpup_joblog($logtime,__('File to Backup:','backwpup').' '.$folder . '/' . $file.' '.backwpup_formatBytes($filezise));
					} else {
						backwpup_joblog($logtime,__('WARNING:','backwpup').' '.__('Can not read file:','backwpup').' '.$folder . '/' . $file);
					}
				} else {
					backwpup_joblog($logtime,__('WARNING:','backwpup').' '.__('Is not a file or directory:','backwpup').' '.$folder . '/' . $file);
				}
			}
		}
		@closedir( $dir );
		return str_replace(',,',',',$files);;
	}
	
	
	//Make filelist
	$backwupu_exclude=array(); $dirinclude=array(); $allfilezise=''; $filelist='';
	
	if (!empty($jobs[$jobid]['fileexclude'])) 
		$backwupu_exclude=split(',',$jobs[$jobid]['fileexclude']);
	//Exclude Temp dir
	$backwupu_exclude[]=get_temp_dir().'backwpup';
	//Exclude Backup dirs
	foreach($jobs as $jobsvale) {
		$backwupu_exclude[]=$jobsvale['backupdir'];
	}
	$backwupu_exclude=array_unique($backwupu_exclude);
	
	//include dirs
	if (!empty($jobs[$jobid]['dirinclude'])) 
		$dirinclude=split(',',str_replace('\\','/',$jobs[$jobid]['dirinclude']));
		
	if ($jobs[$jobid]['backuproot']) //Include extra path
		$dirinclude[]=ABSPATH;
	if ($jobs[$jobid]['backupcontent'] and ((strtolower(str_replace('\\','/',substr(WP_CONTENT_DIR,0,strlen(ABSPATH))))!=strtolower(str_replace('\\','/',ABSPATH)) and $jobs[$jobid]['backuproot']) or !$jobs[$jobid]['backuproot']))
		$dirinclude[]=WP_CONTENT_DIR;
	if ($jobs[$jobid]['backupplugins'] and ((strtolower(str_replace('\\','/',substr(WP_PLUGIN_DIR,0,strlen(ABSPATH))))!=strtolower(str_replace('\\','/',ABSPATH)) and $jobs[$jobid]['backuproot']) or !$jobs[$jobid]['backuproot']) and  ((strtolower(str_replace('\\','/',substr(WP_PLUGIN_DIR,0,strlen(WP_CONTENT_DIR))))!=strtolower(str_replace('\\','/',WP_CONTENT_DIR)) and $jobs[$jobid]['backupcontent']) or !$jobs[$jobid]['backupcontent']))
		$dirinclude[]=WP_PLUGIN_DIR;	
	$dirinclude=array_unique($dirinclude);
	//Crate file list
	if (is_array($dirinclude)) {
		foreach($dirinclude as $dirincludevalue) {
			if (is_dir($dirincludevalue)) 
				$filelist .=",".backwpup_list_files(untrailingslashit(str_replace('\\','/',$dirincludevalue)));
		}
	}	

	if (empty($filelist)) {
		backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('No files to Backup','backwpup'));
		unset($filelist); //clean vars
	} else {
		backwpup_joblog($logtime,__('Size off all files:','backwpup').' '.backwpup_formatBytes($backwpup_allfilezise));
	}
	
	//Create Zip File
	if (!empty($filelist)) {
		backwpup_needfreememory(10485760); //10MB free memory for zip
		backwpup_joblog($logtime,__('Create Backup Zip file...','backwpup'));
		$zipbackupfile = new PclZip($backupfile);
		if (0==$zipbackupfile -> create($filelist,PCLZIP_OPT_REMOVE_PATH,str_replace('\\','/',ABSPATH),PCLZIP_OPT_ADD_TEMP_FILE_ON)) {
			backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('Zip file create:','backwpup').' '.$zipbackupfile->errorInfo(true));
		}
		unset($filelist);
		if ($jobs[$jobid]['type']=='DB+FILE') {
			backwpup_joblog($logtime,__('Database file size:','backwpup').' '.backwpup_formatBytes(filesize(get_temp_dir().'backwpup/'.DB_NAME.'.sql')));
			backwpup_joblog($logtime,__('Add Database dump to Backup Zip file...','backwpup'));
			if (0==$zipbackupfile -> add(get_temp_dir().'backwpup/'.DB_NAME.'.sql',PCLZIP_OPT_REMOVE_ALL_PATH,PCLZIP_OPT_ADD_TEMP_FILE_ON)) {
				backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('Zip file create Add Database dump:','backwpup').' '.$zipbackupfile->errorInfo(true));
			} 
		}
		unset($zipbackupfile);
		backwpup_joblog($logtime,__('Backup Zip file create done!','backwpup'));
	}

?>