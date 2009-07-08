<?PHP
	BackWPupFunctions::joblog($logtime,__('Run File Backup...','backwpup'));
	BackWPupFunctions::joblog($logtime,__('Make File List...','backwpup'));

	//Make filelist
	if ($jobs[$jobid]['backuproot']) {
		$filelist=BackWPupFunctions::list_files(str_replace('\\','/',untrailingslashit(ABSPATH)));
	} else {
		$filelist=(array)$filelist;
	}
	if ($jobs[$jobid]['backupcontent']) {
		$filelist=array_merge(BackWPupFunctions::list_files(str_replace('\\','/',untrailingslashit(WP_CONTENT_DIR))),$filelist);
	} else {
		if (is_array($filelist)) {
			unset($excludefilelist); //clean vars
			$excludefilelist=BackWPupFunctions::list_files(WP_CONTENT_DIR);
			foreach($excludefilelist as $fileexcludevalue) {
				foreach($filelist as $filelistkey =>$filelistvalue) {
					if ($filelistvalue==$fileexcludevalue)
						unset($filelist[$filelistkey]);
				}
			}	
		}
	}
	if ($jobs[$jobid]['backupplugins']) {
		$filelist=array_merge(BackWPupFunctions::list_files(str_replace('\\','/',untrailingslashit(WP_PLUGIN_DIR))),$filelist);
	} else {
		if (is_array($filelist)) {
			unset($excludefilelist); //clean vars
			$excludefilelist=BackWPupFunctions::list_files(WP_PLUGIN_DIR);
			foreach($excludefilelist as $fileexcludevalue) {
				foreach($filelist as $filelistkey =>$filelistvalue) {
					if ($filelistvalue==$fileexcludevalue)
						unset($filelist[$filelistkey]);
				}
			}
		}			
	}
	if (!empty($jobs[$jobid]['dirinclude'])) {// Add extra include dirs
		$dirinclude=split(',',$jobs[$jobid]['dirinclude']); 
		if (is_array($dirinclude)) {
			foreach($dirinclude as $dirincludevalue) {
				if (is_dir($dirincludevalue)) {
					$filelist=array_merge(BackWPupFunctions::list_files(str_replace('\\','/',untrailingslashit($dirincludevalue))),$filelist);
				}
			}
		}
	}
	
	if (sizeof($filelist)>0) {
		$filelist=array_unique($filelist);
		BackWPupFunctions::joblog($logtime,__('Remove Excludes from file list...','backwpup'));	
		//Remove Temp dir
		foreach($filelist as $filelistkey =>$filelistvalue) {
			if (stristr($filelistvalue,BackWPupFunctions::get_temp_dir().'backwpup/'))
				unset($filelist[$filelistkey]);
		}
		//Remove Backup dirs
		foreach($jobs as $jobsvale) {
			foreach($filelist as $filelistkey =>$filelistvalue) {
				if (stristr($filelistvalue,$jobsvale['backupdir'].'/') and !empty($filelistvalue))
					unset($filelist[$filelistkey]);
			}
		}
		//Exclute files and dirs
		if (!empty($jobs[$jobid]['fileexclude'])) {
			$fileexclude=split(',',$jobs[$jobid]['fileexclude']);
			if (is_array($fileexclude)) {
				foreach($fileexclude as $fileexcludevalue) {
					foreach($filelist as $filelistkey =>$filelistvalue) {
						if (stristr($filelistvalue,$fileexcludevalue))
							unset($filelist[$filelistkey]);
					}
				}
			}
			unset($fileexclude); //clean vars
		}
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
		BackWPupFunctions::joblog($logtime,__('ERROR:','backwpup').' '.__('No files to Backup','backwpup'));
		unset($filelist); //clean vars
	}
	
	//Create Zip File
	if (is_array($filelist) or $jobs[$jobid]['type']=='DB+FILE') {
		BackWPupFunctions::joblog($logtime,__('Files to Backup: ','backwpup').print_r($filelist,true));
		BackWPupFunctions::joblog($logtime,__('Create Backup Zip file...','backwpup'));
		require_once(ABSPATH . 'wp-admin/includes/class-pclzip.php');
		$zipbackupfile = new PclZip($backupfile);
		if (0==$zipbackupfile -> create($filelist,PCLZIP_OPT_REMOVE_PATH,str_replace('\\','/',ABSPATH))) {
			BackWPupFunctions::joblog($logtime,__('ERROR:','backwpup').' '.__('Zip file create:','backwpup').' '.$zipbackupfile->errorInfo(true));
		}
		if ($jobs[$jobid]['type']=='DB+FILE') {
			BackWPupFunctions::joblog($logtime,__('Add Database dump to Backup Zip file...','backwpup'));
			if (0==$zipbackupfile -> add(BackWPupFunctions::get_temp_dir().'backwpup/'.DB_NAME.'.sql',PCLZIP_OPT_REMOVE_PATH,BackWPupFunctions::get_temp_dir().'backwpup/')) {
				BackWPupFunctions::joblog($logtime,__('ERROR:','backwpup').' '.__('Zip file create Add Database dump:','backwpup').' '.$zipbackupfile->errorInfo(true));
			}
		}
		//clean vars
		unset($zipbackupfile);
		unset($filelist);
		BackWPupFunctions::joblog($logtime,__('Backup Zip file create done!','backwpup'));
	}

?>