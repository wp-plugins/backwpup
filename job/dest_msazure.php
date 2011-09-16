<?PHP
function backwpup_job_dest_msazure() {
	global $backwpupjobrun;
	$backwpupjobrun['WORKING']['STEPTODO']=2+$backwpupjobrun['WORKING']['backupfilesize'];
	trigger_error(sprintf(__('%d. try sending backup to a Microsoft Azure (Blob)...','backwpup'),$backwpupjobrun['WORKING']['DEST_MSAZURE']['STEP_TRY']),E_USER_NOTICE);

	require_once(dirname(__FILE__).'/../libs/Microsoft/WindowsAzure/Storage/Blob.php');
	need_free_memory(4194304*1.5);

	try {
		$storageClient = new Microsoft_WindowsAzure_Storage_Blob($backwpupjobrun['STATIC']['JOB']['msazureHost'],$backwpupjobrun['STATIC']['JOB']['msazureAccName'],$backwpupjobrun['STATIC']['JOB']['msazureKey']);

		if(!$storageClient->containerExists($backwpupjobrun['STATIC']['JOB']['msazureContainer'])) {
			trigger_error(sprintf(__('Microsoft Azure container "%s" not exists!','backwpup'),$backwpupjobrun['STATIC']['JOB']['msazureContainer']),E_USER_ERROR);
			return;
		} else {
			trigger_error(sprintf(__('Connected to Microsoft Azure container "%s"','backwpup'),$backwpupjobrun['STATIC']['JOB']['msazureContainer']),E_USER_NOTICE);
		}

		trigger_error(__('Upload to MS Azure now started... ','backwpup'),E_USER_NOTICE);
		$result = $storageClient->putBlob($backwpupjobrun['STATIC']['JOB']['msazureContainer'], $backwpupjobrun['STATIC']['JOB']['msazuredir'].$backwpupjobrun['STATIC']['backupfile'], $backwpupjobrun['STATIC']['JOB']['backupdir'].$backwpupjobrun['STATIC']['backupfile']);

		if ($result->Name==$backwpupjobrun['STATIC']['JOB']['msazuredir'].$backwpupjobrun['STATIC']['backupfile']) {
			$backwpupjobrun['WORKING']['STEPTODO']=1+$backwpupjobrun['WORKING']['backupfilesize'];
			trigger_error(sprintf(__('Backup transferred to %s','backwpup'),'https://'.$backwpupjobrun['STATIC']['JOB']['msazureAccName'].'.'.$backwpupjobrun['STATIC']['JOB']['msazureHost'].'/'.$backwpupjobrun['STATIC']['JOB']['msazuredir'].$backwpupjobrun['STATIC']['backupfile']),E_USER_NOTICE);
			$backwpupjobrun['STATIC']['JOB']['lastbackupdownloadurl']=backwpup_admin_url('admin.php').'?page=backwpupbackups&action=downloadmsazure&file='.$backwpupjobrun['STATIC']['JOB']['msazuredir'].$backwpupjobrun['STATIC']['backupfile'].'&jobid='.$backwpupjobrun['STATIC']['JOB']['jobid'];
			$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_MSAZURE'; //set done
		} else {
			trigger_error(__('Can not transfer backup to Microsoft Azure!','backwpup'),E_USER_ERROR);
		}

		if ($backwpupjobrun['STATIC']['JOB']['msazuremaxbackups']>0) { //Delete old backups
			$backupfilelist=array();
			$blobs = $storageClient->listBlobs($backwpupjobrun['STATIC']['JOB']['msazureContainer'],$backwpupjobrun['STATIC']['JOB']['msazuredir']);
			if (is_array($blobs)) {
				foreach ($blobs as $blob) {
					$file=basename($blob->Name);
					if ($backwpupjobrun['STATIC']['JOB']['fileprefix'] == substr($file,0,strlen($backwpupjobrun['STATIC']['JOB']['fileprefix'])) and $backwpupjobrun['STATIC']['JOB']['fileformart'] == substr($file,-strlen($backwpupjobrun['STATIC']['JOB']['fileformart'])))
						$backupfilelist[]=$file;
				}
			}
			if (sizeof($backupfilelist)>0) {
				rsort($backupfilelist);
				$numdeltefiles=0;
				for ($i=$backwpupjobrun['STATIC']['JOB']['msazuremaxbackups'];$i<sizeof($backupfilelist);$i++) {
					$storageClient->deleteBlob($backwpupjobrun['STATIC']['JOB']['msazureContainer'],$backwpupjobrun['STATIC']['JOB']['msazuredir'].$backupfilelist[$i]); //delte files on Cloud
					$numdeltefiles++;
				}
				if ($numdeltefiles>0)
					trigger_error(sprintf(_n('One file deleted on Microsoft Azure container','%d files deleted on Microsoft Azure container',$numdeltefiles,'backwpup'),$numdeltefiles),E_USER_NOTICE);
			}
		}

	} catch (Exception $e) {
		trigger_error(sprintf(__('Microsoft Azure API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
	}

	$backwpupjobrun['WORKING']['STEPDONE']++;
}
?>