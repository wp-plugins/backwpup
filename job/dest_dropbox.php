<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function dest_dropbox() {
	if (empty($_SESSION['JOB']['dropetoken']) or empty($_SESSION['JOB']['dropesecret'])) {
		$_SESSION['WORKING']['STEPSDONE'][]='DEST_DROPBOX'; //set done	
		return;
	}
	$_SESSION['WORKING']['STEPTODO']=2+filesize($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile']);
	$_SESSION['WORKING']['STEPDONE']=0;
	trigger_error($_SESSION['WORKING']['DEST_DROPBOX']['STEP_TRY'].'. '.__('Try to sending backup file to DropBox...','backwpup'),E_USER_NOTICE);
	require_once(realpath(dirname(__FILE__).'/../libs/dropbox/dropbox.php'));
	try {
		$dropbox = new Dropbox($_SESSION['BACKWPUP']['DROPBOX_APP_KEY'], $_SESSION['BACKWPUP']['DROPBOX_APP_SECRET']);
		// set the tokens 
		$dropbox->setOAuthTokens($_SESSION['JOB']['dropetoken'],$_SESSION['JOB']['dropesecret']);
		//set boxtype
		if ($_SESSION['JOB']['droperoot']=='sandbox')
			$dropbox->setSandbox();
		else
			$dropbox->setDropbox();
		$info=$dropbox->accountInfo();
		if (!empty($info['uid'])) {
			trigger_error(__('Authed to DropBox from ','backwpup').$info['display_name'],E_USER_NOTICE);
		}
		//Check Quota
		$dropboxfreespase=$info['quota_info']['quota']-$info['quota_info']['shared']-$info['quota_info']['normal'];
		if (filesize($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile'])>$dropboxfreespase) {
			trigger_error(__('No free space left on DropBox!!!','backwpup'),E_USER_ERROR);
			$_SESSION['WORKING']['STEPSDONE'][]='DEST_DROPBOX'; //set done
			return;
		} else {
			trigger_error(__('Free Space on DropBox: ','backwpup').formatBytes($dropboxfreespase),E_USER_NOTICE);
		}
		//set calback function
		$dropbox->setProgressFunction('curl_progresscallback');
		// put the file 
		trigger_error(__('Upload to DropBox now started ... ','backwpup'),E_USER_NOTICE);
		$response = $dropbox->upload($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile'],$_SESSION['JOB']['dropedir']); 
		if ($response['result']=="winner!") {
			$_SESSION['JOB']['lastbackupdownloadurl']='admin.php?page=backwpupbackups&action=downloaddropbox&file='.$_SESSION['JOB']['dropedir'].$_SESSION['STATIC']['backupfile'].'&jobid='.$_SESSION['JOB']['jobid'];
			$_SESSION['WORKING']['STEPDONE']++;
			trigger_error(__('Backup File transferred to DropBox.','backwpup'),E_USER_NOTICE);
		} else {
			trigger_error(__('Can not transfere Backup file to DropBox:','backwpup').' '.$response['error'],E_USER_ERROR);
			return;
		}
		//unset calback function
		$dropbox->setProgressFunction('');
		
		if ($_SESSION['JOB']['dropemaxbackups']>0) { //Delete old backups
			$backupfilelist=array();
			$metadata = $dropbox->metadata($_SESSION['JOB']['dropedir']);
			if (is_array($metadata)) {
				foreach ($metadata['contents'] as $data) {
					$file=basename($data['path']);
					if ($data['is_dir']!=true and $_SESSION['JOB']['fileprefix'] == substr($file,0,strlen($_SESSION['JOB']['fileprefix'])) and $_SESSION['JOB']['fileformart'] == substr($file,-strlen($_SESSION['JOB']['fileformart'])))
						$backupfilelist[]=$file;
				}
			}
			if (sizeof($backupfilelist)>0) {
				rsort($backupfilelist);
				$numdeltefiles=0;
				for ($i=$_SESSION['JOB']['dropemaxbackups'];$i<count($backupfilelist);$i++) {
					$dropbox->fileopsDelete($_SESSION['JOB']['dropedir'].$backupfilelist[$i]); //delete files on Cloud
					$numdeltefiles++;
				}
				if ($numdeltefiles>0)
					trigger_error($numdeltefiles.' '.__('files deleted on DropBox Folder!','backwpup'),E_USER_NOTICE);
			}
		}	
	} catch (Exception $e) {
		trigger_error(__('DropBox API:','backwpup').' '.$e->getMessage(),E_USER_ERROR);
	} 

	$_SESSION['WORKING']['STEPDONE']++;
	$_SESSION['WORKING']['STEPSDONE'][]='DEST_DROPBOX'; //set done
}
?>