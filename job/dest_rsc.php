<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function dest_rsc() {
	if (empty($_SESSION['JOB']['rscUsername']) or empty($_SESSION['JOB']['rscAPIKey']) or empty($_SESSION['JOB']['rscContainer'])) {
		$_SESSION['WORKING']['STEPSDONE'][]='DEST_RSC'; //set done	
		return;
	}
	trigger_error($_SESSION['WORKING']['DEST_RSC']['STEP_TRY'].'. '.__('Try to sending backup file to Rackspace Cloud...','backwpup'),E_USER_NOTICE);
	$_SESSION['WORKING']['STEPTODO']=2+filesize($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile']);
	$_SESSION['WORKING']['STEPDONE']=0;
	require_once(dirname(__FILE__).'/../libs/rackspace/cloudfiles.php');
	
	$auth = new CF_Authentication($_SESSION['JOB']['rscUsername'], $_SESSION['JOB']['rscAPIKey']);
	$auth->ssl_use_cabundle();
	try {
		if ($auth->authenticate())
			trigger_error(__('Connected to Rackspase ...','backwpup'),E_USER_NOTICE);			
		$conn = new CF_Connection($auth);
		$conn->ssl_use_cabundle();
		$is_container=false;
		$containers=$conn->get_containers();
		foreach ($containers as $container) {
			if ($container->name == $_SESSION['JOB']['rscContainer'] )
				$is_container=true;
		}
		if (!$is_container) {
			$public_container = $conn->create_container($_SESSION['JOB']['rscContainer']);
			$public_container->make_private();
			if (empty($public_container))
				$is_container=false;
		}	
	} catch (Exception $e) {
		trigger_error(__('Rackspase Cloud API:','backwpup').' '.$e->getMessage(),E_USER_ERROR);
		return;
	}
	
	if (!$is_container) {
		trigger_error(__('Rackspase Cloud Container not exists:','backwpup').' '.$_SESSION['JOB']['rscContainer'],E_USER_ERROR);
		return;
	}
	
	try {
		//Transfer Backup to Rackspace Cloud
		$backwpupcontainer = $conn->get_container($_SESSION['JOB']['rscContainer']);
		//if (!empty($_SESSION['JOB']['rscdir'])) //make the foldder
		//	$backwpupcontainer->create_paths($_SESSION['JOB']['rscdir']); 
		$backwpupbackup = $backwpupcontainer->create_object($_SESSION['JOB']['rscdir'].$_SESSION['STATIC']['backupfile']);
		//set content Type
		if ($_SESSION['JOB']['fileformart']=='.zip')
			$backwpupbackup->content_type='application/zip';
		if ($_SESSION['JOB']['fileformart']=='.tar')
			$backwpupbackup->content_type='application/x-ustar';
		if ($_SESSION['JOB']['fileformart']=='.tar.gz')
			$backwpupbackup->content_type='application/x-compressed';
		if ($_SESSION['JOB']['fileformart']=='.tar.bz2')
			$backwpupbackup->content_type='application/x-compressed';			
		trigger_error(__('Upload to RSC now started ... ','backwpup'),E_USER_NOTICE);
		if ($backwpupbackup->load_from_filename($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile'])) {
			$_SESSION['WORKING']['STEPTODO']=1+filesize($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile']);
			trigger_error(__('Backup File transferred to RSC://','backwpup').$_SESSION['JOB']['rscContainer'].'/'.$_SESSION['JOB']['rscdir'].$_SESSION['STATIC']['backupfile'],E_USER_NOTICE);
			$_SESSION['JOB']['lastbackupdownloadurl']=$_SESSION['WP']['ADMINURL'].'?page=backwpupbackups&action=downloadrsc&file='.$_SESSION['JOB']['rscdir'].$_SESSION['STATIC']['backupfile'].'&jobid='.$_SESSION['JOB']['jobid'];
		} else {
			trigger_error(__('Can not transfer backup to RSC.','backwpup'),E_USER_ERROR);
		}
		
		if ($_SESSION['JOB']['rscmaxbackups']>0) { //Delete old backups
			$backupfilelist=array();
			$contents = $backwpupcontainer->list_objects(0,NULL,NULL,$_SESSION['JOB']['rscdir']);
			if (is_array($contents)) {
				foreach ($contents as $object) {
					$file=basename($object);
					if ($_SESSION['JOB']['rscdir'].$file == $object) {//only in the folder and not in complete bucket
						if ($_SESSION['JOB']['fileprefix'] == substr($file,0,strlen($_SESSION['JOB']['fileprefix'])) and $_SESSION['JOB']['fileformart'] == substr($file,-strlen($_SESSION['JOB']['fileformart'])))
							$backupfilelist[]=$file;
					}
				}
			}
			if (sizeof($backupfilelist)>0) {
				rsort($backupfilelist);
				$numdeltefiles=0;
				for ($i=$_SESSION['JOB']['rscmaxbackups'];$i<sizeof($backupfilelist);$i++) {
					if ($backwpupcontainer->delete_object($_SESSION['JOB']['rscdir'].$backupfilelist[$i])) //delte files on Cloud
						$numdeltefiles++;
					else
						trigger_error(__('Can not delete file on RSC://','backwpup').$_SESSION['JOB']['rscContainer'].$_SESSION['JOB']['rscdir'].$backupfilelist[$i],E_USER_ERROR);
				}
				if ($numdeltefiles>0)
					trigger_error($numdeltefiles.' '.__('files deleted on Racspase Cloud Container!','backwpup'),E_USER_NOTICE);
			}
		}	
	} catch (Exception $e) {
		trigger_error(__('Rackspase Cloud API:','backwpup').' '.$e->getMessage(),E_USER_ERROR);
	} 

	$_SESSION['WORKING']['STEPDONE']++;
	$_SESSION['WORKING']['STEPSDONE'][]='DEST_RSC'; //set done
}
?>