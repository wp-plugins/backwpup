<?PHP
BackWPupFunctions::joblog($logfile,__('Run File Backup...','backwpup'));

BackWPupFunctions::joblog($logfile,__('Make File List...','backwpup'));

	function allfiles($path,$filelist='') { //helper function
		if ($pathhandle = @opendir($path)) {
			$path=str_replace('\\','/',$path);
			$path=str_replace('//','/',$path);
			$path=untrailingslashit($path);
			while (false !== ($file = readdir($pathhandle))) {
				if ($file != "." && $file != ".." && $file != ".svn") {
					if (is_dir($path.'/'.$file)) {
						$filelist=allfiles($path.'/'.$file,$filelist);
					} else {
						$filelist[]=$path.'/'.$file;
					}
				}
			}
			closedir($pathhandle);
		}
		return $filelist;
	}
	
	//Make filelist
	if ($jobs[$jobid]['backuproot']) {
		$filelist=allfiles(ABSPATH);
	}
	if ($jobs[$jobid]['backupcontent']) {
		$filelist=allfiles(WP_CONTENT_DIR,$filelist);
	} else {
		if (is_array($filelist)) {
			unset($excludefilelist); //clean vars
			$excludefilelist=allfiles(WP_CONTENT_DIR);
			foreach($excludefilelist as $fileexcludevalue) {
				foreach($filelist as $filelistkey =>$filelistvalue) {
					if ($filelistvalue==$fileexcludevalue)
						unset($filelist[$filelistkey]);
				}
			}	
		}
	}
	if ($jobs[$jobid]['backupplugins']) {
		$filelist=allfiles(WP_PLUGIN_DIR,$filelist);
	} else {
		if (is_array($filelist)) {
			unset($excludefilelist); //clean vars
			$excludefilelist=allfiles(WP_PLUGIN_DIR);
			foreach($excludefilelist as $fileexcludevalue) {
				foreach($filelist as $filelistkey =>$filelistvalue) {
					if ($filelistvalue==$fileexcludevalue)
						unset($filelist[$filelistkey]);
				}
			}
		}			
	}
	$dirinclude=split(',',$jobs[$jobid]['dirinclude']); // Add extra include dirs
	if (is_array($dirinclude)) {
		foreach($dirinclude as $dirincludevalue) {
			if (is_dir($dirincludevalue)) {
				$filelist=allfiles($dirincludevalue,$filelist);
			}
		}
	}
	
	if (sizeof($filelist)>0) {
		$filelist=array_unique($filelist);
		BackWPupFunctions::joblog($logfile,__('Remove Excludes from file list...','backwpup'));	
		//Remove Backup dirs
		foreach($jobs as $jobsvale) {
			foreach($filelist as $filelistkey =>$filelistvalue) {
				if (stristr($filelistvalue,$jobsvale['backupdir'].'/'))
					unset($filelist[$filelistkey]);
			}
		}
		//Exclute files and dirs
		$fileexclude=split(',',$jobs[$jobid]['fileexclude']);
		if (is_array($fileexclude) and !empty($fileexclude[0]) and sizeof($fileexclude)>1) {
			foreach($fileexclude as $fileexcludevalue) {
				foreach($filelist as $filelistkey =>$filelistvalue) {
					if (stristr($filelistvalue,$fileexcludevalue))
						unset($filelist[$filelistkey]);
				}
			}
		}
		unset($fileexclude); //clean vars
	} else {
		BackWPupFunctions::joblog($logfile,__('ERROR: No files to Backup','backwpup'));
		$joberror=true;
		unset($filelist); //clean vars
	}
	
	if (sizeof($filelist)>0) {
		//Make array index mor readable
		$filestobackup=0;
		foreach($filelist as $filelistvalue) {
			$cleanfilelist[$filestobackup++]=$filelistvalue;
		}
		unset($filelist);
		$filelist=$cleanfilelist;
		unset($cleanfilelist);
	} else {
		BackWPupFunctions::joblog($logfile,__('ERROR: No files to Backup','backwpup'));
		$joberror=true;
		unset($filelist); //clean vars
	}
	
	//Create Zip File
	BackWPupFunctions::joblog($logfile,__('Files to Backup: ','backwpup').print_r($filelist,true));
	BackWPupFunctions::joblog($logfile,__('Create Backup Zip file...','backwpup'));
	
	if (is_array($filelist) or $jobs[$jobid]['type']=='DB+FILE') {
		require_once(ABSPATH . 'wp-admin/includes/class-pclzip.php');
		$zipbackupfile = new PclZip($backupfile);
		if (0==$zipbackupfile -> create($filelist,PCLZIP_OPT_REMOVE_PATH,str_replace('\\','/',ABSPATH))) {
			BackWPupFunctions::joblog($logfile,__('ERROR: Zip file create:','backwpup').' '.$zipbackupfile->errorInfo(true));
			$joberror=true;
		}
		if ($jobs[$jobid]['type']=='DB+FILE') {
			BackWPupFunctions::joblog($logfile,__('Add Database dump to Backup Zip file...','backwpup'));
			if (0==$zipbackupfile -> add($cfg['tempdir'].'/'.DB_NAME.'.sql',PCLZIP_OPT_REMOVE_PATH,$cfg['tempdir'].'/')) {
				BackWPupFunctions::joblog($logfile,__('ERROR: Zip file create Add Database dump:','backwpup').' '.$zipbackupfile->errorInfo(true));
				$joberror=true;
			}
		}
		//clean vars
		unset($zipbackupfile);
		unset($filelist);
		BackWPupFunctions::joblog($logfile,__('Backup Zip file create done!','backwpup'));
	}

?>