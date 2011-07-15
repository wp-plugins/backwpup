<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function dest_msazure() {
	global $WORKING,$STATIC;
	if (empty($STATIC['JOB']['msazureHost']) or empty($STATIC['JOB']['msazureAccName']) or empty($STATIC['JOB']['msazureKey']) or empty($STATIC['JOB']['msazureContainer'])) {
		$WORKING['STEPSDONE'][]='DEST_MSAZURE'; //set done	
		return;
	}
	$WORKING['STEPTODO']=2+filesize($STATIC['JOB']['backupdir'].$STATIC['backupfile']);
	trigger_error($WORKING['DEST_MSAZURE']['STEP_TRY'].'. '.__('Try to sending backup file to a Microsoft Azure (Blob)...','backwpup'),E_USER_NOTICE);

	require_once(dirname(__FILE__).'/../libs/Microsoft/WindowsAzure/Storage/Blob.php');
	need_free_memory(4194304*1.5); 
	
	try {
		$storageClient = new Microsoft_WindowsAzure_Storage_Blob($STATIC['JOB']['msazureHost'],$STATIC['JOB']['msazureAccName'],$STATIC['JOB']['msazureKey']);

		if(!$storageClient->containerExists($STATIC['JOB']['msazureContainer'])) {
			trigger_error(__('Microsoft Azure Container not exists:','backwpup').' '.$STATIC['JOB']['msazureContainer'],E_USER_ERROR);
			return;
		} else {
			trigger_error(__('Connected to Microsoft Azure Container:','backwpup').' '.$STATIC['JOB']['msazureContainer'],E_USER_NOTICE);
		}
		
		trigger_error(__('Upload to MS Azure now started ... ','backwpup'),E_USER_NOTICE);
		@set_time_limit($STATIC['CFG']['jobscriptruntimelong']);
		$result = $storageClient->putBlob($STATIC['JOB']['msazureContainer'], $STATIC['JOB']['msazuredir'].$STATIC['backupfile'], $STATIC['JOB']['backupdir'].$STATIC['backupfile']);
		
		if ($result->Name==$STATIC['JOB']['msazuredir'].$STATIC['backupfile']) {
			$WORKING['STEPTODO']=1+filesize($STATIC['JOB']['backupdir'].$STATIC['backupfile']);
			trigger_error(__('Backup File transferred to azure://','backwpup').$STATIC['JOB']['msazuredir'].$STATIC['backupfile'],E_USER_NOTICE);
			$STATIC['JOB']['lastbackupdownloadurl']=$STATIC['WP']['ADMINURL'].'?page=backwpupbackups&action=downloadmsazure&file='.$STATIC['JOB']['msazuredir'].$STATIC['backupfile'].'&jobid='.$STATIC['JOB']['jobid'];
		} else {
			trigger_error(__('Can not transfer backup to Microsoft Azure.','backwpup'),E_USER_ERROR);
		}

		if ($STATIC['JOB']['msazuremaxbackups']>0) { //Delete old backups
			$backupfilelist=array();
			$blobs = $storageClient->listBlobs($STATIC['JOB']['msazureContainer'],$STATIC['JOB']['msazuredir']);
			if (is_array($blobs)) {
				foreach ($blobs as $blob) {
					$file=basename($blob->Name);
					if ($STATIC['JOB']['fileprefix'] == substr($file,0,strlen($STATIC['JOB']['fileprefix'])) and $STATIC['JOB']['fileformart'] == substr($file,-strlen($STATIC['JOB']['fileformart'])))
						$backupfilelist[]=$file;
				}
			}
			if (sizeof($backupfilelist)>0) {
				rsort($backupfilelist);
				$numdeltefiles=0;
				for ($i=$STATIC['JOB']['msazuremaxbackups'];$i<sizeof($backupfilelist);$i++) {
					$storageClient->deleteBlob($STATIC['JOB']['msazureContainer'],$STATIC['JOB']['msazuredir'].$backupfilelist[$i]); //delte files on Cloud
					$numdeltefiles++;
				}
				if ($numdeltefiles>0)
					trigger_error($numdeltefiles.' '.__('files deleted on Microsoft Azure Container!','backwpup'),E_USER_NOTICE);
			}
		}
		
	} catch (Exception $e) {
		trigger_error(__('Microsoft Azure API:','backwpup').' '.$e->getMessage(),E_USER_ERROR);
	} 
		
	$WORKING['STEPDONE']++;
	$WORKING['STEPSDONE'][]='DEST_MSAZURE'; //set done
}
?>