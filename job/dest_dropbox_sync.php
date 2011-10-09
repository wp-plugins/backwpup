<?PHP
function backwpup_job_dest_dropbox_sync() {
	global $backwpupjobrun;
	$backwpupjobrun['WORKING']['STEPTODO']=2;
	$backwpupjobrun['WORKING']['STEPDONE']=0;
	trigger_error(sprintf(__('%d. Try to sync files with DropBox...','backwpup'),$backwpupjobrun['WORKING']['DEST_DROPBOX_SYNC']['STEP_TRY']),E_USER_NOTICE);
	require_once(realpath(dirname(__FILE__).'/../libs/dropbox.php'));
	try {
		//set boxtype and authkeys
		if ($backwpupjobrun['STATIC']['JOB']['droperoot']=='sandbox')
			$dropbox = new backwpup_Dropbox(BACKWPUP_DROPBOX_SANDBOX_APP_KEY, BACKWPUP_DROPBOX_SANDBOX_APP_SECRET,'sandbox');
		else 
			$dropbox = new backwpup_Dropbox(BACKWPUP_DROPBOX_APP_KEY, BACKWPUP_DROPBOX_APP_SECRET);
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
		//get files
		$filelist=get_transient('backwpup_job_filelist'); //get file list
		$backwpupjobrun['WORKING']['STEPTODO']=count($filelist);
		//get folder
		$folderlist=get_transient('backwpup_job_folderlist'); //get folder list
		$folderlist=array_unique($folderlist,SORT_STRING);
		sort($folderlist,SORT_STRING);
		//check
		//foreach ($folderlist as $folder) {
			$dropboxfiles = $dropbox->metadata($backwpupjobrun['STATIC']['JOB']['dropedir'].'/'.$folder,true);
			foreach($dropboxfiles['contents'] as $contents) {
				$path=str_replace('/'.$backwpupjobrun['STATIC']['JOB']['dropedir'],'',$contents['path']);
				if ($contents['is_dir']) {
					if (!in_array($path,$folderlist)) {
						trigger_error(sprintf(__('Delete folder from DropBox: %s','backwpup'),$files['OUTFILE']),E_USER_NOTICE);
						$dropbox->fileopsDelete($contents['path']);
					}
					continue;
				}
				$found=false;
				foreach($filelist as $filekey => $files) {
					if ($files['OUTFILE']==$path and !$contents['is_dir']) {
						$found=true;
						if ($contents['bytes']!=$files['SIZE']) {
							$uploaddir=trim(dirname($files['OUTFILE']),'/');
							if ($uploaddir=='.')
								$uploaddir='';
							trigger_error(sprintf(__('Upload updated file to DropBox: %s','backwpup'),$files['OUTFILE']),E_USER_NOTICE);
							$dropbox->upload($files['FILE'],$backwpupjobrun['STATIC']['JOB']['dropedir'].$uploaddir,true);
							$backwpupjobrun['WORKING']['STEPDONE']++;
							unset($filelist[$filekey]);
						}
						break;
					} 
				}
				//delete files not in filelist
				if (!$found) {
					trigger_error(sprintf(__('Delete file from DropBox: %s','backwpup'),$files['OUTFILE']),E_USER_NOTICE);
					$dropbox->fileopsDelete($contents['path']);
				}
			}
		//}
		//upload files not on dest
		foreach($filelist as $filekey => $files) {
			$uploaddir=trim(dirname($files['OUTFILE']),'/');
			if ($uploaddir=='.')
				$uploaddir='';
			trigger_error(sprintf(__('Upload new file to DropBox: %s','backwpup'),$files['OUTFILE']),E_USER_NOTICE);
			$dropbox->upload($files['FILE'],$backwpupjobrun['STATIC']['JOB']['dropedir'].$uploaddir,true);
			$backwpupjobrun['WORKING']['STEPDONE']++;
			unset($filelist[$filekey]);
		}
	} catch (Exception $e) {
		trigger_error(sprintf(__('DropBox API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
	}
	if (count($filelist)==0)
		$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_DROPBOX_SYNC'; //set done
}
?>