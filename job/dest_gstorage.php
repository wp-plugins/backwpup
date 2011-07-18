<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function dest_gstorage() {
	global $WORKING,$STATIC;
	$WORKING['STEPTODO']=2+filesize($STATIC['JOB']['backupdir'].$STATIC['backupfile']);
	$WORKING['STEPDONE']=0;
	trigger_error($WORKING['DEST_GSTORAGE']['STEP_TRY'].'. '.__('Try to sending backup file to Google Storage...','backwpup'),E_USER_NOTICE);

	require_once(dirname(__FILE__).'/../libs/googlestorage.php');
	try {
		$googlestorage = new GoogleStorage($STATIC['JOB']['GStorageAccessKey'], $STATIC['JOB']['GStorageSecret']);
		$googlestorage->setProgressFunction('curl_progresscallback');
		$bucket=$googlestorage->getBucketAcl($STATIC['JOB']['GStorageBucket']);
		if (is_object($bucket)) {
			trigger_error(__('Connected to Google storage bucket:','backwpup').' '.$STATIC['JOB']['GStorageBucket'],E_USER_NOTICE);
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
			trigger_error(__('Upload to Google storage now started ... ','backwpup'),E_USER_NOTICE);
			@set_time_limit($STATIC['CFG']['jobscriptruntimelong']);
			$upload=$googlestorage->putObject($STATIC['JOB']['GStorageBucket'],$STATIC['JOB']['GStoragedir'].$STATIC['backupfile'],$STATIC['JOB']['backupdir'].$STATIC['backupfile'],'private',$content_type);
			if (empty($upload))  {
				$WORKING['STEPTODO']=1+filesize($STATIC['JOB']['backupdir'].$STATIC['backupfile']);
				trigger_error(__('Backup File transferred to GSTORAGE://','backwpup').$STATIC['JOB']['GStorageBucket'].'/'.$STATIC['JOB']['GStoragedir'].$STATIC['backupfile'],E_USER_NOTICE);
				$STATIC['JOB']['lastbackupdownloadurl']=$STATIC['WP']['ADMINURL'].'?page=backwpupbackups&action=downloadgstorage&file='.$STATIC['JOB']['GStoragedir'].$STATIC['backupfile'].'&jobid='.$STATIC['JOB']['jobid'];
			} else {
				trigger_error(__('Can not transfer backup to Google storage!','backwpup').' '.$upload,E_USER_ERROR);
			}
			
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
						trigger_error($numdeltefiles.' '.__('files deleted on Google Storage Bucket!','backwpup'),E_USER_NOTICE);
				}
			}					
		} else {
			trigger_error(__('Bucket error:','backwpup').' '.$bucket,E_USER_ERROR);
		}
	} catch (Exception $e) {
		trigger_error(__('Amazon Google Storage API:','backwpup').' '.$e->getMessage(),E_USER_ERROR);
		return;
	}
	
	$WORKING['STEPDONE']++;
	$WORKING['STEPSDONE'][]='DEST_GSTORAGE'; //set done
}
?>