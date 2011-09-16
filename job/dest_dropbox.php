<?PHP
function backwpup_job_dest_dropbox() {
	global $backwpupjobrun;
	$backwpupjobrun['WORKING']['STEPTODO']=2+$backwpupjobrun['WORKING']['backupfilesize'];
	$backwpupjobrun['WORKING']['STEPDONE']=0;
	trigger_error(sprintf(__('%d. Try to sending backup file to DropBox...','backwpup'),$backwpupjobrun['WORKING']['DEST_DROPBOX']['STEP_TRY']),E_USER_NOTICE);

	require_once(realpath(dirname(__FILE__).'/../libs/Dropbox/autoload.php'));
	try {
        if (function_exists('curl_exec')) {
            $oauth = new Dropbox_OAuth_Curl(BACKWPUP_DROPBOX_APP_KEY, BACKWPUP_DROPBOX_APP_SECRET);
        } elseif (function_exists('OAuth')) {
            $oauth = new Dropbox_OAuth_PHP(BACKWPUP_DROPBOX_APP_KEY, BACKWPUP_DROPBOX_APP_SECRET);
        } else {
            $oauth = new Dropbox_OAuth_Wordpress(BACKWPUP_DROPBOX_APP_KEY, BACKWPUP_DROPBOX_APP_SECRET);
        }
		$dropbox = new Dropbox_API($oauth,$backwpupjobrun['STATIC']['JOB']['droperoot']);
		// set the tokens
		$oauth->setToken($backwpupjobrun['STATIC']['JOB']['dropetoken'],$backwpupjobrun['STATIC']['JOB']['dropesecret']);
		$info=$dropbox->getAccountInfo();
		if (!empty($info['uid'])) {
			trigger_error(sprintf(__('Authed with DropBox from %s','backwpup'),$info['display_name']),E_USER_NOTICE);
		}
		//Check Quota
		$dropboxfreespase=$info['quota_info']['quota']-$info['quota_info']['shared']-$info['quota_info']['normal'];
		if ($backwpupjobrun['WORKING']['backupfilesize']>$dropboxfreespase) {
			trigger_error(__('No free space left on DropBox!!!','backwpup'),E_USER_ERROR);
			$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_DROPBOX'; //set done
			return;
		} else {
			trigger_error(sprintf(__('%s free on DropBox','backwpup'),backwpup_formatBytes($dropboxfreespase)),E_USER_NOTICE);
		}
		// put the file
		trigger_error(__('Upload to DropBox now started... ','backwpup'),E_USER_NOTICE);
		backwpup_job_need_free_memory($backwpupjobrun['WORKING']['backupfilesize']*3); //free memory to transfer to dropbox
		$response = $dropbox->putFile($backwpupjobrun['STATIC']['JOB']['dropedir'].$backwpupjobrun['STATIC']['backupfile'],$backwpupjobrun['STATIC']['JOB']['backupdir'].$backwpupjobrun['STATIC']['backupfile']);
		if ($response) {
			$backwpupjobrun['STATIC']['JOB']['lastbackupdownloadurl']=backwpup_admin_url('admin.php').'?page=backwpupbackups&action=downloaddropbox&file='.$backwpupjobrun['STATIC']['JOB']['dropedir'].$backwpupjobrun['STATIC']['backupfile'].'&jobid='.$backwpupjobrun['STATIC']['JOB']['jobid'];
			$backwpupjobrun['WORKING']['STEPDONE']++;
			$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_DROPBOX'; //set done
			trigger_error(sprintf(__('Backup transferred to %s','backwpup'),'https://api-content.dropbox.com/0/files/'.$backwpupjobrun['STATIC']['JOB']['droperoot'].'/'.$backwpupjobrun['STATIC']['JOB']['dropedir'].$backwpupjobrun['STATIC']['backupfile']),E_USER_NOTICE);
		}
	} catch (Exception $e) {
		trigger_error(sprintf(__('DropBox API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
	}
	try {
		if ($backwpupjobrun['STATIC']['JOB']['dropemaxbackups']>0 and is_object($dropbox)) { //Delete old backups
			$backupfilelist=array();
			$metadata = $dropbox->getMetaData($backwpupjobrun['STATIC']['JOB']['dropedir']);
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
					$dropbox->delete($backwpupjobrun['STATIC']['JOB']['dropedir'].$file); //delete files on Cloud
					$numdeltefiles++;
				}
				if ($numdeltefiles>0)
					trigger_error(sprintf(_n('One file deleted on DropBox','%d files deleted on DropBox',$numdeltefiles,'backwpup'),$numdeltefiles),E_USER_NOTICE);
			}
		}
	} catch (Exception $e) {
		trigger_error(sprintf(__('DropBox API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
	}
	$backwpupjobrun['WORKING']['STEPDONE']++;
}
?>