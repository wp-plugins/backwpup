<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function dest_msazure() {
	if (empty($_SESSION['JOB']['msazureHost']) or empty($_SESSION['JOB']['msazureAccName']) or empty($_SESSION['JOB']['msazureKey']) or empty($_SESSION['JOB']['msazureContainer'])) {
		$_SESSION['WORKING']['STEPSDONE'][]='DEST_MSAZURE'; //set done	
		return;
	}
	$_SESSION['WORKING']['STEPTODO']=2+filesize($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile']);
	trigger_error($_SESSION['WORKING']['DEST_MSAZURE']['STEP_TRY'].'. '.__('Try to sending backup file to a Microsoft Azure (Blob)...','backwpup'),E_USER_NOTICE);

	require_once(dirname(__FILE__).'/../libs/Microsoft/WindowsAzure/Storage/Blob.php');
	need_free_memory(4194304*1.5); 
	
	try {
		$storageClient = new Microsoft_WindowsAzure_Storage_Blob($_SESSION['JOB']['msazureHost'],$_SESSION['JOB']['msazureAccName'],$_SESSION['JOB']['msazureKey']);

		if(!$storageClient->containerExists($_SESSION['JOB']['msazureContainer'])) {
			trigger_error(__('Microsoft Azure Container not exists:','backwpup').' '.$_SESSION['JOB']['msazureContainer'],E_USER_ERROR);
			return;
		} else {
			trigger_error(__('Connected to Microsoft Azure Container:','backwpup').' '.$_SESSION['JOB']['msazureContainer'],E_USER_NOTICE);
		}
		
		trigger_error(__('Upload to MS Azure now started ... ','backwpup'),E_USER_NOTICE);
		@set_time_limit(300);
		$result = $storageClient->putBlob($_SESSION['JOB']['msazureContainer'], $_SESSION['JOB']['msazuredir'].$_SESSION['STATIC']['backupfile'], $_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile']);
		
		if ($result->Name==$_SESSION['JOB']['msazuredir'].$_SESSION['STATIC']['backupfile']) {
			$_SESSION['WORKING']['STEPTODO']=1+filesize($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile']);
			trigger_error(__('Backup File transferred to azure://','backwpup').$_SESSION['JOB']['msazuredir'].$_SESSION['STATIC']['backupfile'],E_USER_NOTICE);
			$_SESSION['JOB']['lastbackupdownloadurl']=$_SESSION['WP']['ADMINURL'].'?page=backwpupbackups&action=downloadmsazure&file='.$_SESSION['JOB']['msazuredir'].$_SESSION['STATIC']['backupfile'].'&jobid='.$_SESSION['JOB']['jobid'];
		} else {
			trigger_error(__('Can not transfer backup to Microsoft Azure.','backwpup'),E_USER_ERROR);
		}

		if ($_SESSION['JOB']['msazuremaxbackups']>0) { //Delete old backups
			$backupfilelist=array();
			$blobs = $storageClient->listBlobs($_SESSION['JOB']['msazureContainer'],$_SESSION['JOB']['msazuredir']);
			if (is_array($blobs)) {
				foreach ($blobs as $blob) {
					$file=basename($blob->Name);
					if ($_SESSION['JOB']['fileprefix'] == substr($file,0,strlen($_SESSION['JOB']['fileprefix'])) and $_SESSION['JOB']['fileformart'] == substr($file,-strlen($_SESSION['JOB']['fileformart'])))
						$backupfilelist[]=$file;
				}
			}
			if (sizeof($backupfilelist)>0) {
				rsort($backupfilelist);
				$numdeltefiles=0;
				for ($i=$_SESSION['JOB']['msazuremaxbackups'];$i<sizeof($backupfilelist);$i++) {
					$storageClient->deleteBlob($_SESSION['JOB']['msazureContainer'],$_SESSION['JOB']['msazuredir'].$backupfilelist[$i]); //delte files on Cloud
					$numdeltefiles++;
				}
				if ($numdeltefiles>0)
					trigger_error($numdeltefiles.' '.__('files deleted on Microsoft Azure Container!','backwpup'),E_USER_NOTICE);
			}
		}
		
	} catch (Exception $e) {
		trigger_error(__('Microsoft Azure API:','backwpup').' '.$e->getMessage(),E_USER_ERROR);
	} 
		
	$_SESSION['WORKING']['STEPDONE']++;
	$_SESSION['WORKING']['STEPSDONE'][]='DEST_MSAZURE'; //set done
}
?>