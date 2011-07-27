<?PHP
function dest_gstorage() {
	global $WORKING,$STATIC;
	$WORKING['STEPTODO']=2+filesize($STATIC['JOB']['backupdir'].$STATIC['backupfile']);
	$WORKING['STEPDONE']=0;
	trigger_error(sprintf(__('%d. try sending backup to Google Storage...','backwpup'),$WORKING['DEST_GSTORAGE']['STEP_TRY']),E_USER_NOTICE);

	require_once(dirname(__FILE__).'/../libs/googlestorage.php');
	try {
		$googlestorage = new GoogleStorage($STATIC['JOB']['GStorageAccessKey'], $STATIC['JOB']['GStorageSecret']);
		$googlestorage->setProgressFunction('curl_progresscallback');
		$bucket=$googlestorage->getBucketAcl($STATIC['JOB']['GStorageBucket']);
		if (is_object($bucket)) {
			trigger_error(sprintf(__('Connected to Google storage bucket: %s','backwpup'),$STATIC['JOB']['GStorageBucket']),E_USER_NOTICE);
			//set content Type
			if ($STATIC['JOB']['fileformart']=='.zip')
				$content_type='application/zip';
			if ($STATIC['JOB']['fileformart']=='.tar')
				$content_type='application/x-ustar';
			if ($STATIC['JOB']['fileformart']=='.tar.gz')
				$content_type='application/x-compressed';
			if ($STATIC['JOB']['fileformart']=='.tar.bz2')
				$content_type='application/x-compressed';		
			//Transfer Backup to Google Storrage
			trigger_error(__('Upload to Google storage now started...','backwpup'),E_USER_NOTICE);
			@set_time_limit($STATIC['CFG']['jobscriptruntimelong']);
			$upload=$googlestorage->putObject($STATIC['JOB']['GStorageBucket'],$STATIC['JOB']['GStoragedir'].$STATIC['backupfile'],$STATIC['JOB']['backupdir'].$STATIC['backupfile'],'private',$content_type);
			if (empty($upload))  {
				$WORKING['STEPTODO']=1+filesize($STATIC['JOB']['backupdir'].$STATIC['backupfile']);
				trigger_error(sprintf(__('Backup transferred to GSTORAGE://%s','backwpup'),$STATIC['JOB']['GStorageBucket'].'/'.$STATIC['JOB']['GStoragedir'].$STATIC['backupfile']),E_USER_NOTICE);
				$STATIC['JOB']['lastbackupdownloadurl']=$STATIC['WP']['ADMINURL'].'?page=backwpupbackups&action=downloadgstorage&file='.$STATIC['JOB']['GStoragedir'].$STATIC['backupfile'].'&jobid='.$STATIC['JOB']['jobid'];
			} else {
				trigger_error(sprintf(__('Error "%s" on transfer backup to Google storage!','backwpup'),$upload),E_USER_ERROR);
			}
		} else {
			trigger_error(sprintf(__('Error "%s" on connect to Google Storage bucket','backwpup'),$bucket),E_USER_ERROR);
		}
	} catch (Exception $e) {
		trigger_error(sprintf(__('Google Storage API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
		return;
	}
	try {	
		if (is_object($bucket)) {
			if ($STATIC['JOB']['GStoragemaxbackups']>0) { //Delete old backups
				$backupfilelist=array();
				$contents = $googlestorage->getBucket($STATIC['JOB']['GStorageBucket'],$STATIC['JOB']['GStoragedir']);
				if (is_object($contents)) {
					foreach ($contents as $object) {
						$file=basename($object->Key);
						if ($STATIC['JOB']['fileprefix'] == substr($file,0,strlen($STATIC['JOB']['fileprefix'])) and $STATIC['JOB']['fileformart'] == substr($file,-strlen($STATIC['JOB']['fileformart'])))
							$backupfilelist[]=$file;
					}
				}
				if (sizeof($backupfilelist)>0) {
					rsort($backupfilelist);
					$numdeltefiles=0;
					for ($i=$STATIC['JOB']['GStoragemaxbackups'];$i<sizeof($backupfilelist);$i++) {
						$googlestorage->deleteObject($STATIC['JOB']['GStorageBucket'],$STATIC['JOB']['GStoragedir'].$backupfilelist[$i]); //delte files on Google Storage
						$numdeltefiles++;
					}
					if ($numdeltefiles>0)
						trigger_error(sprintf(_n('One file deleted on Google Storage bucket','%d files deleted on Google Storage bucket',$numdeltefiles,'backwpup'),$numdeltefiles),E_USER_NOTICE);
				}
			}					
		}
	} catch (Exception $e) {
		trigger_error(sprintf(__('Google Storage API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
		return;
	}
	
	$WORKING['STEPDONE']++;
	$WORKING['STEPSDONE'][]='DEST_GSTORAGE'; //set done
}
?>