<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function dest_dropbox() {
	global $WORKING,$STATIC;
	if (empty($STATIC['JOB']['dropetoken']) or empty($STATIC['JOB']['dropesecret'])) {
		$WORKING['STEPSDONE'][]='DEST_DROPBOX'; //set done	
		return;
	}
	$WORKING['STEPTODO']=2+filesize($STATIC['JOB']['backupdir'].$STATIC['backupfile']);
	$WORKING['STEPDONE']=0;
	trigger_error($WORKING['DEST_DROPBOX']['STEP_TRY'].'. '.__('Try to sending backup file to DropBox...','backwpup'),E_USER_NOTICE);
	require_once(realpath(dirname(__FILE__).'/../libs/dropbox/dropbox.php'));
	try {
		$dropbox = new Dropbox($STATIC['BACKWPUP']['DROPBOX_APP_KEY'], $STATIC['BACKWPUP']['DROPBOX_APP_SECRET']);
		// set the tokens 
		$dropbox->setOAuthTokens($STATIC['JOB']['dropetoken'],$STATIC['JOB']['dropesecret']);
		//set oAuth Sign method
		if ($STATIC['JOB']['dropesignmethod']=='PLAIN') {
			$dropbox->setoAuthSignMethodPlain();
			trigger_error(__('oAuth sign method for DropBox set to:','backwpup').' '.__('PLAINTEXT', 'backwpup'),E_USER_NOTICE);
		} else {
			$dropbox->setoAuthSignMethodSHA1();
			trigger_error(__('oAuth sign method for DropBox set to:','backwpup').' '.__('HMAC-SHA1', 'backwpup'),E_USER_NOTICE);
		}
		//set boxtype
		if ($STATIC['JOB']['droperoot']=='sandbox')
			$dropbox->setSandbox();
		else
			$dropbox->setDropbox();
		$info=$dropbox->accountInfo();
		if (!empty($info['uid'])) {
			trigger_error(__('Authed to DropBox from ','backwpup').$info['display_name'],E_USER_NOTICE);
		}
		//Check Quota
		$dropboxfreespase=$info['quota_info']['quota']-$info['quota_info']['shared']-$info['quota_info']['normal'];
		if (filesize($STATIC['JOB']['backupdir'].$STATIC['backupfile'])>$dropboxfreespase) {
			trigger_error(__('No free space left on DropBox!!!','backwpup'),E_USER_ERROR);
			$WORKING['STEPSDONE'][]='DEST_DROPBOX'; //set done
			return;
		} else {
			trigger_error(__('Free Space on DropBox: ','backwpup').formatBytes($dropboxfreespase),E_USER_NOTICE);
		}
		//set calback function
		$dropbox->setProgressFunction('curl_progresscallback');
		// put the file 
		trigger_error(__('Upload to DropBox now started ... ','backwpup'),E_USER_NOTICE);
		need_free_memory(filesize($STATIC['JOB']['backupdir'].$STATIC['backupfile'])*2); //free memory to transfer to dropbox
		@set_time_limit($STATIC['CFG']['jobscriptruntimelong']);
		$response = $dropbox->upload($STATIC['JOB']['backupdir'].$STATIC['backupfile'],$STATIC['JOB']['dropedir']); 
		if ($response['result']=="winner!") {
			$STATIC['JOB']['lastbackupdownloadurl']=$STATIC['WP']['ADMINURL'].'?page=backwpupbackups&action=downloaddropbox&file='.$STATIC['JOB']['dropedir'].$STATIC['backupfile'].'&jobid='.$STATIC['JOB']['jobid'];
			$WORKING['STEPDONE']++;
			trigger_error(__('Backup File transferred to DropBox://','backwpup').$STATIC['JOB']['droperoot'].'/'.$STATIC['JOB']['dropedir'].$STATIC['backupfile'],E_USER_NOTICE);
		} else {
			trigger_error(__('Can not transfere Backup file to DropBox:','backwpup').' '.$response['error'],E_USER_ERROR);
			return;
		}
		//unset calback function
		$dropbox->setProgressFunction('');
		
		if ($STATIC['JOB']['dropemaxbackups']>0) { //Delete old backups
			$backupfilelist=array();
			$metadata = $dropbox->metadata($STATIC['JOB']['dropedir']);
			if (is_array($metadata)) {
				foreach ($metadata['contents'] as $data) {
					$file=basename($data['path']);
					if ($data['is_dir']!=true and $STATIC['JOB']['fileprefix'] == substr($file,0,strlen($STATIC['JOB']['fileprefix'])) and $STATIC['JOB']['fileformart'] == substr($file,-strlen($STATIC['JOB']['fileformart'])))
						$backupfilelist[]=$file;
				}
			}
			if (sizeof($backupfilelist)>0) {
				rsort($backupfilelist);
				$numdeltefiles=0;
				for ($i=$STATIC['JOB']['dropemaxbackups'];$i<count($backupfilelist);$i++) {
					$dropbox->fileopsDelete($STATIC['JOB']['dropedir'].$backupfilelist[$i]); //delete files on Cloud
					$numdeltefiles++;
				}
				if ($numdeltefiles>0)
					trigger_error($numdeltefiles.' '.__('files deleted on DropBox Folder!','backwpup'),E_USER_NOTICE);
			}
		}	
	} catch (Exception $e) {
		trigger_error(__('DropBox API:','backwpup').' '.$e->getMessage(),E_USER_ERROR);
	} 

	$WORKING['STEPDONE']++;
	$WORKING['STEPSDONE'][]='DEST_DROPBOX'; //set done
}
?>