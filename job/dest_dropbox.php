<?PHP
function backwpup_job_dest_dropbox() {
	global $backwpupjobrun,$backwpup_cfg;
	$backwpupjobrun['STEPTODO']=2+$backwpupjobrun['backupfilesize'];
	$backwpupjobrun['STEPDONE']=0;
	trigger_error(sprintf(__('%d. Try to sending backup file to DropBox...','backwpup'),$backwpupjobrun['DEST_DROPBOX']['STEP_TRY']),E_USER_NOTICE);
	require_once(realpath(dirname(__FILE__).'/../libs/dropbox.php'));
	try {
		//set boxtype and authkeys
		if ($backwpupjobrun['STATIC']['JOB']['droperoot']=='sandbox')
			$dropbox = new backwpup_Dropbox($backwpup_cfg['DROPBOX_SANDBOX_APP_KEY'], $backwpup_cfg['DROPBOX_SANDBOX_APP_SECRET'],false);
		else 
			$dropbox = new backwpup_Dropbox($backwpup_cfg['DROPBOX_APP_KEY'], $backwpup_cfg['DROPBOX_APP_SECRET'],true);
		
		// set the tokens 
		$dropbox->setOAuthTokens($backwpupjobrun['STATIC']['JOB']['dropetoken'],$backwpupjobrun['STATIC']['JOB']['dropesecret']);
		//get account info
		$info=$dropbox->accountInfo();
		if (!empty($info['uid'])) {
			trigger_error(sprintf(__('Authed with DropBox from %s','backwpup'),$info['display_name'].' ('.$info['email'].')'),E_USER_NOTICE);
		}
		//Check Quota
		$dropboxfreespase=$info['quota_info']['quota']-$info['quota_info']['shared']-$info['quota_info']['normal'];
		if ($backwpupjobrun['backupfilesize']>$dropboxfreespase) {
			trigger_error(__('No free space left on DropBox!!!','backwpup'),E_USER_ERROR);
			$backwpupjobrun['STEPSDONE'][]='DEST_DROPBOX'; //set done
			return;
		} else {
			trigger_error(sprintf(__('%s free on DropBox','backwpup'),backwpup_format_bytes($dropboxfreespase)),E_USER_NOTICE);
		}
		//set calback function
		$dropbox->setProgressFunction('backwpup_job_curl_progresscallback');
		// put the file
		trigger_error(__('Upload to DropBox now started... ','backwpup'),E_USER_NOTICE);
		$response = $dropbox->upload($backwpupjobrun['STATIC']['JOB']['backupdir'].$backwpupjobrun['STATIC']['backupfile'],$backwpupjobrun['STATIC']['JOB']['dropedir'].$backwpupjobrun['STATIC']['backupfile']); 
		if ($response['bytes']==filesize($backwpupjobrun['STATIC']['JOB']['backupdir'].$backwpupjobrun['STATIC']['backupfile'])) {
			backwpup_update_option('job_'.$backwpupjobrun['STATIC']['JOB']['jobid'],'lastbackupdownloadurl',backwpup_admin_url('admin.php').'?page=backwpupbackups&action=downloaddropbox&file='.$backwpupjobrun['STATIC']['JOB']['dropedir'].$backwpupjobrun['STATIC']['backupfile'].'&jobid='.$backwpupjobrun['STATIC']['JOB']['jobid']);
			$backwpupjobrun['STEPDONE']++;
			$backwpupjobrun['STEPSDONE'][]='DEST_DROPBOX'; //set done
			trigger_error(sprintf(__('Backup transferred to %s','backwpup'),'https://api-content.dropbox.com/1/files/'.$backwpupjobrun['STATIC']['JOB']['droperoot'].'/'.$backwpupjobrun['STATIC']['JOB']['dropedir'].$backwpupjobrun['STATIC']['backupfile']),E_USER_NOTICE);
		} else {
			if ($response['bytes']!=filesize($backwpupjobrun['STATIC']['JOB']['backupdir'].$backwpupjobrun['STATIC']['backupfile']))
				trigger_error(__('Uploadet filesize and filesize not the same!!!','backwpup'),E_USER_ERROR);
			else
				trigger_error(sprintf(__('Error on transfere backup to DropBox: %s','backwpup'),$response['error']),E_USER_ERROR);
			return;
		}
		//unset calback function
		$dropbox->setProgressFunction('');
	} catch (Exception $e) {
		trigger_error(sprintf(__('DropBox API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
	}
	try {
		if ($backwpupjobrun['STATIC']['JOB']['dropemaxbackups']>0 and is_object($dropbox)) { //Delete old backups
			$backupfilelist=array();
			$metadata = $dropbox->metadata($backwpupjobrun['STATIC']['JOB']['dropedir']);
			if (is_array($metadata)) {
				foreach ($metadata['contents'] as $data) {
					$file=basename($data['path']);
					if ($data['is_dir']!=true and $backwpupjobrun['STATIC']['JOB']['fileprefix'] == substr($file,0,strlen($backwpupjobrun['STATIC']['JOB']['fileprefix'])))
						$backupfilelist[strtotime($data['modified'])]=$file;
				}
			}
			if (count($backupfilelist)>$backwpupjobrun['STATIC']['JOB']['dropemaxbackups']) {
				$numdeltefiles=0;
				while ($file=array_shift($backupfilelist)) {
					if (count($backupfilelist)<$backwpupjobrun['STATIC']['JOB']['dropemaxbackups'])
						break;
					$response=$dropbox->fileopsDelete($backwpupjobrun['STATIC']['JOB']['dropedir'].$file); //delete files on Cloud
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
	$backwpupjobrun['STEPDONE']++;
}
?>