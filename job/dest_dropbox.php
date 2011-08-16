<?PHP
function dest_dropbox() {
	global $WORKING,$STATIC;
	$WORKING['STEPTODO']=2+filesize($STATIC['JOB']['backupdir'].$STATIC['backupfile']);
	$WORKING['STEPDONE']=0;
	trigger_error(sprintf(__('%d. Try to sending backup file to DropBox...','backwpup'),$WORKING['DEST_DROPBOX']['STEP_TRY']),E_USER_NOTICE);
	
	require_once(realpath(dirname(__FILE__).'/../libs/Dropbox/autoload.php'));
	try {
		if (class_exists('OAuth')) 
			$oauth = new Dropbox_OAuth_PHP($STATIC['BACKWPUP']['DROPBOX_APP_KEY'], $STATIC['BACKWPUP']['DROPBOX_APP_SECRET']);
		elseif (class_exists('HTTP_OAuth_Consumer'))
			$oauth = new Dropbox_OAuth_PEAR($STATIC['BACKWPUP']['DROPBOX_APP_KEY'], $STATIC['BACKWPUP']['DROPBOX_APP_SECRET']);
		elseif (class_exists('Zend_Oauth_Consumer'))
			$oauth = new Dropbox_OAuth_Zend($STATIC['BACKWPUP']['DROPBOX_APP_KEY'], $STATIC['BACKWPUP']['DROPBOX_APP_SECRET']);
		elseif (function_exists('curl_exec'))
			$oauth = new Dropbox_OAuth_Curl($STATIC['BACKWPUP']['DROPBOX_APP_KEY'], $STATIC['BACKWPUP']['DROPBOX_APP_SECRET']);
		else {
			trigger_error(sprintf(__('No supported DropDox oauth class found!','backwpup'),$info['display_name']),E_USER_ERROR);
			return;
		}
		
		$dropbox = new Dropbox_API($oauth,$STATIC['JOB']['droperoot']);
		// set the tokens 
		$oauth->setToken($STATIC['JOB']['dropetoken'],$STATIC['JOB']['dropesecret']);
		$info=$dropbox->getAccountInfo();
		if (!empty($info['uid'])) {
			trigger_error(sprintf(__('Authed with DropBox from %s','backwpup'),$info['display_name']),E_USER_NOTICE);
		}
		//Check Quota
		$dropboxfreespase=$info['quota_info']['quota']-$info['quota_info']['shared']-$info['quota_info']['normal'];
		if (filesize($STATIC['JOB']['backupdir'].$STATIC['backupfile'])>$dropboxfreespase) {
			trigger_error(__('No free space left on DropBox!!!','backwpup'),E_USER_ERROR);
			$WORKING['STEPSDONE'][]='DEST_DROPBOX'; //set done
			return;
		} else {
			trigger_error(sprintf(__('%s free on DropBox','backwpup'),formatBytes($dropboxfreespase)),E_USER_NOTICE);
		}
		//set calback function
		$oauth->ProgressFunction='curl_progresscallback';
		// put the file 
		trigger_error(__('Upload to DropBox now started... ','backwpup'),E_USER_NOTICE);
		need_free_memory(filesize($STATIC['JOB']['backupdir'].$STATIC['backupfile'])*3); //free memory to transfer to dropbox
		$response = $dropbox->putFile($STATIC['JOB']['dropedir'].$STATIC['backupfile'],$STATIC['JOB']['backupdir'].$STATIC['backupfile']); 
		if ($response) {
			$STATIC['JOB']['lastbackupdownloadurl']=$STATIC['WP']['ADMINURL'].'?page=backwpupbackups&action=downloaddropbox&file='.$STATIC['JOB']['dropedir'].$STATIC['backupfile'].'&jobid='.$STATIC['JOB']['jobid'];
			$WORKING['STEPDONE']++;
			$WORKING['STEPSDONE'][]='DEST_DROPBOX'; //set done
			trigger_error(sprintf(__('Backup transferred to %s','backwpup'),'https://api-content.dropbox.com/0/files/'.$STATIC['JOB']['droperoot'].'/'.$STATIC['JOB']['dropedir'].$STATIC['backupfile']),E_USER_NOTICE);
		}
		//unset calback function
		$oauth->ProgressFunction=false;
	} catch (Exception $e) {
		trigger_error(sprintf(__('DropBox API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
	}
	try {	
		if ($STATIC['JOB']['dropemaxbackups']>0 and is_object($dropbox)) { //Delete old backups
			$backupfilelist=array();
			$metadata = $dropbox->getMetaData($STATIC['JOB']['dropedir']);
			if (is_array($metadata)) {
				foreach ($metadata['contents'] as $data) {
					$file=basename($data['path']);
					if ($data['is_dir']!=true and $STATIC['JOB']['fileprefix'] == substr($file,0,strlen($STATIC['JOB']['fileprefix'])))
						$backupfilelist[strtotime($data['modified'])]=$file;
				}
			}
			if (count($backupfilelist)>$STATIC['JOB']['dropemaxbackups']) {
				$numdeltefiles=0;
				while ($file=array_shift($backupfilelist)) {
					if (count($backupfilelist)<$STATIC['JOB']['dropemaxbackups'])
						break;
					$dropbox->delete($STATIC['JOB']['dropedir'].$file); //delete files on Cloud
					$numdeltefiles++;
				}
				if ($numdeltefiles>0)
					trigger_error(sprintf(_n('One file deleted on DropBox','%d files deleted on DropBox',$numdeltefiles,'backwpup'),$numdeltefiles),E_USER_NOTICE);
			}
		}	
	} catch (Exception $e) {
		trigger_error(sprintf(__('DropBox API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
	} 

	$WORKING['STEPDONE']++;
}
?>