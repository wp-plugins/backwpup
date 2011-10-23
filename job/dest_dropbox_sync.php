<?PHP
function backwpup_job_dest_dropbox_sync() {
	global $backwpupjobrun;
	$backwpupjobrun['WORKING']['STEPTODO']=2;
	$backwpupjobrun['WORKING']['STEPDONE']=0;
	trigger_error(sprintf(__('%d. Try to sync files with DropBox...','backwpup'),$backwpupjobrun['WORKING']['DEST_DROPBOX_SYNC']['STEP_TRY']),E_USER_NOTICE);
	require_once(realpath(dirname(__FILE__).'/../libs/dropbox.php'));
	try {
		//set boxtype and authkeys
		$backwpupapi=new backwpup_api();
		$keys=$backwpupapi->get_keys();
		if ($backwpupjobrun['STATIC']['JOB']['droperoot']=='sandbox')
			$dropbox = new backwpup_Dropbox($keys['DROPBOX_SANDBOX_APP_KEY'], $keys['DROPBOX_SANDBOX_APP_SECRET'],'sandbox');
		else 
			$dropbox = new backwpup_Dropbox($keys['DROPBOX_APP_KEY'], $keys['DROPBOX_APP_SECRET']);
		// set the tokens 
		$dropbox->setOAuthTokens($backwpupjobrun['STATIC']['JOB']['dropetoken'],$backwpupjobrun['STATIC']['JOB']['dropesecret']);
		//get account info
		$info=$dropbox->accountInfo();
		if (!empty($info['uid'])) {
			trigger_error(sprintf(__('Authed with DropBox from %s','backwpup'),$info['display_name']),E_USER_NOTICE);
		}
		//Quota
		$dropboxfreespase=$info['quota_info']['quota']-$info['quota_info']['shared']-$info['quota_info']['normal'];
		trigger_error(sprintf(__('%s free on DropBox','backwpup'),backwpup_formatBytes($dropboxfreespase)),E_USER_NOTICE);
	} catch (Exception $e) {
		trigger_error(sprintf(__('DropBox API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
		return false;
	}
	//get files
	$filelist=backwpup_get_option('WORKING','FILELIST'); //get file list
	$folderlist=backwpup_get_option('WORKING','FOLDERLIST'); //get folder list
	$backwpupjobrun['WORKING']['STEPTODO']=count($filelist);
	//check
	trigger_error(__('Get remote file and folder list...','backwpup'),E_USER_NOTICE);
	$remotefilelist=array();
	$remotefolderlist=array();
	backwpup_job_dest_dropbox_sync_get_remote_files($backwpupjobrun['STATIC']['JOB']['dropedir'],$dropbox,$remotefilelist,$remotefolderlist);
	rsort($remotefolderlist);
	//Start sync
	trigger_error(__('Sync folder...','backwpup'),E_USER_NOTICE);
	foreach($remotefolderlist as $remotefolder) {
		backwpup_job_update_working_data();
		$found=false;
		foreach($folderlist as $folderkey => $folder) {
			if('/'.trim(trim($backwpupjobrun['STATIC']['JOB']['dropedir'],'/').'/'.$folder,'/')==$remotefolder) {
				$found=true;
				unset($folderlist[$folderkey]);
				break;				
			}
		}
		//delete folder not in folderlist
		if (!$found) {
			trigger_error(sprintf(__('Delete folder from DropBox: %s','backwpup'),$remotefolder),E_USER_NOTICE);
			try {
				$dropbox->fileopsDelete($remotefolder);
			} catch (Exception $e) {
				trigger_error(sprintf(__('DropBox API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
			}
			//delte files form remotefolder list if folder deletet
			foreach($remotefilelist as $remotekey => $remotefile) {
				if (strpos($remotefolder,$remotefile['FILE']) == 0) 
					unset($remotefilelist[$remotekey]);
			}
		}
	}
	unset($remotefolderlist);
	
	trigger_error(__('Sync files...','backwpup'),E_USER_NOTICE);
	foreach($remotefilelist as $remotefile) {
		$found=false;
		foreach($filelist as $filekey => $files) {
			if ('/'.ltrim($backwpupjobrun['STATIC']['JOB']['dropedir'],'/').$files['OUTFILE']==$remotefile['FILE']) {
				$found=true;
				if ($remotefile['BYTES']!=$files['SIZE']) {
					trigger_error(sprintf(__('Upload updated file to DropBox: %s','backwpup'),$files['OUTFILE']),E_USER_NOTICE);
					try {
						$dropbox->upload($files['FILE'],$backwpupjobrun['STATIC']['JOB']['dropedir'].$files['OUTFILE'],true);
					} catch (Exception $e) {
						trigger_error(sprintf(__('DropBox API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
					}
				}
				$backwpupjobrun['WORKING']['STEPDONE']++;
				unset($filelist[$filekey]);
				break;
			} 
		}
		//delete files not in filelist
		if (!$found) {
			trigger_error(sprintf(__('Delete file from DropBox: %s','backwpup'),$remotefile['FILE']),E_USER_NOTICE);
			try {
				$dropbox->fileopsDelete($remotefile['FILE']);
			} catch (Exception $e) {
				trigger_error(sprintf(__('DropBox API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
			}	
		}
	}
	unset($remotefilelist);
	//upload files not on dest
	foreach($filelist as $filekey => $files) {
		trigger_error(sprintf(__('Upload new file to DropBox: %s','backwpup'),$files['OUTFILE']),E_USER_NOTICE);
		try {
			$dropbox->upload($files['FILE'],$backwpupjobrun['STATIC']['JOB']['dropedir'].$files['OUTFILE'],true);
		} catch (Exception $e) {
			trigger_error(sprintf(__('DropBox API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
		}			
		$backwpupjobrun['WORKING']['STEPDONE']++;
		unset($filelist[$filekey]);
	}
	if (count($filelist)==0)
		$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_DROPBOX_SYNC'; //set done
}

function backwpup_job_dest_dropbox_sync_get_remote_files($folder,&$dropbox,&$remotefilelist,&$remotefolderlist) {
	backwpup_job_update_working_data();
	try {
		$remote = $dropbox->metadata($folder,true);
	} catch (Exception $e) {
		trigger_error(sprintf(__('DropBox API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
	}
	foreach($remote['contents'] as $entries) {
		if ($entries['is_dir']) {
			$remotefolderlist[]=$entries['path'];
			backwpup_job_dest_dropbox_sync_get_remote_files($entries['path'],$dropbox,$remotefilelist,$remotefolderlist);
		} else {
			$remotefilelist[]=array('FILE'=>$entries['path'],'BYTES'=>$entries['bytes']);
		}
	}
}
?>