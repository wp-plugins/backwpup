<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function dest_gstorage() {
	if (empty($_SESSION['JOB']['GStorageAccessKey']) or empty($_SESSION['JOB']['GStorageSecret']) or empty($_SESSION['JOB']['GStorageBucket'])) {
		$_SESSION['WORKING']['STEPSDONE'][]='DEST_GSTORAGE'; //set done	
		return;
	}
	$_SESSION['WORKING']['STEPTODO']=2+filesize($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile']);
	$_SESSION['WORKING']['STEPDONE']=0;
	trigger_error($_SESSION['WORKING']['DEST_GSTORAGE']['STEP_TRY'].'. '.__('Try to sending backup file to Google Storage...','backwpup'),E_USER_NOTICE);

	require_once(dirname(__FILE__).'/../libs/googlestorage.php');
	try {
		$googlestorage = new GoogleStorage($_SESSION['JOB']['GStorageAccessKey'], $_SESSION['JOB']['GStorageSecret']);
		$googlestorage->setProgressFunction('curl_progresscallback');
		$bucket=$googlestorage->getBucketAcl($_SESSION['JOB']['GStorageBucket']);
		if (is_object($bucket)) {
			trigger_error(__('Connected to Google storage bucket:','backwpup').' '.$_SESSION['JOB']['GStorageBucket'],E_USER_NOTICE);
			//set content Type
			if ($_SESSION['JOB']['fileformart']=='.zip')
				$content_type='application/zip';
			if ($_SESSION['JOB']['fileformart']=='.tar')
				$content_type='application/x-ustar';
			if ($_SESSION['JOB']['fileformart']=='.tar.gz')
				$content_type='application/x-compressed';
			if ($_SESSION['JOB']['fileformart']=='.tar.bz2')
				$content_type='application/x-compressed';		
			//Transfer Backup to Google Storrage
			trigger_error(__('Upload to Google storage now started ... ','backwpup'),E_USER_NOTICE);
			$upload=$googlestorage->putObject($_SESSION['JOB']['GStorageBucket'],$_SESSION['JOB']['GStoragedir'].$_SESSION['STATIC']['backupfile'],$_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile'],'private',$content_type);
			if (empty($upload))  {
				$_SESSION['WORKING']['STEPTODO']=1+filesize($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile']);
				trigger_error(__('Backup File transferred to GSTORAGE://','backwpup').$_SESSION['JOB']['GStorageBucket'].'/'.$_SESSION['JOB']['GStoragedir'].$_SESSION['STATIC']['backupfile'],E_USER_NOTICE);
				$_SESSION['JOB']['lastbackupdownloadurl']=$_SESSION['WP']['ADMINURL'].'?page=backwpupbackups&action=downloadgstorage&file='.$_SESSION['JOB']['GStoragedir'].$_SESSION['STATIC']['backupfile'].'&jobid='.$_SESSION['JOB']['jobid'];
			} else {
				trigger_error(__('Can not transfer backup to Google storage!','backwpup').' '.$upload,E_USER_ERROR);
			}
			
			if ($_SESSION['JOB']['GStoragemaxbackups']>0) { //Delete old backups
				$backupfilelist=array();
				$contents = $googlestorage->getBucket($_SESSION['JOB']['GStorageBucket'],$_SESSION['JOB']['GStoragedir']);
				if (is_object($contents)) {
					foreach ($contents as $object) {
						$file=basename($object->Key);
						if ($_SESSION['JOB']['fileprefix'] == substr($file,0,strlen($_SESSION['JOB']['fileprefix'])) and $_SESSION['JOB']['fileformart'] == substr($file,-strlen($_SESSION['JOB']['fileformart'])))
							$backupfilelist[]=$file;
					}
				}
				if (sizeof($backupfilelist)>0) {
					rsort($backupfilelist);
					$numdeltefiles=0;
					for ($i=$_SESSION['JOB']['GStoragemaxbackups'];$i<sizeof($backupfilelist);$i++) {
						$googlestorage->deleteObject($_SESSION['JOB']['GStorageBucket'],$_SESSION['JOB']['GStoragedir'].$backupfilelist[$i]); //delte files on Google Storage
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
	
	$_SESSION['WORKING']['STEPDONE']++;
	$_SESSION['WORKING']['STEPSDONE'][]='DEST_GSTORAGE'; //set done
}
?>