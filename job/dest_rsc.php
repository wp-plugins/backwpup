<?PHP
function backwpup_job_dest_rsc() {
	global $backwpupjobrun;
	$backwpupjobrun['WORKING']['STEPTODO']=2+$backwpupjobrun['WORKING']['backupfilesize'];
	$backwpupjobrun['WORKING']['STEPDONE']=0;
	trigger_error(sprintf(__('%d. Try to sending backup file to Rackspace Cloud...','backwpup'),$backwpupjobrun['WORKING']['DEST_RSC']['STEP_TRY']),E_USER_NOTICE);
	require_once(dirname(__FILE__).'/../libs/rackspace/cloudfiles.php');

	$auth = new CF_Authentication($backwpupjobrun['STATIC']['JOB']['rscUsername'], $backwpupjobrun['STATIC']['JOB']['rscAPIKey']);
	$auth->ssl_use_cabundle();
	try {
		if ($auth->authenticate())
			trigger_error(__('Connected to Rackspase ...','backwpup'),E_USER_NOTICE);
		$conn = new CF_Connection($auth);
		$conn->ssl_use_cabundle(dirname(__FILE__).'/../libs/cacert.pem');
		$is_container=false;
		$containers=$conn->get_containers();
		foreach ($containers as $container) {
			if ($container->name == $backwpupjobrun['STATIC']['JOB']['rscContainer'] )
				$is_container=true;
		}
		if (!$is_container) {
			$public_container = $conn->create_container($backwpupjobrun['STATIC']['JOB']['rscContainer']);
			$public_container->make_private();
			if (empty($public_container))
				$is_container=false;
		}
	} catch (Exception $e) {
		trigger_error(__('Rackspase Cloud API:','backwpup').' '.$e->getMessage(),E_USER_ERROR);
		return;
	}

	if (!$is_container) {
		trigger_error(__('Rackspase Cloud Container not exists:','backwpup').' '.$backwpupjobrun['STATIC']['JOB']['rscContainer'],E_USER_ERROR);
		return;
	}

	try {
		//Transfer Backup to Rackspace Cloud
		$backwpupcontainer = $conn->get_container($backwpupjobrun['STATIC']['JOB']['rscContainer']);
		//if (!empty($backwpupjobrun['STATIC']['JOB']['rscdir'])) //make the foldder
		//	$backwpupcontainer->create_paths($backwpupjobrun['STATIC']['JOB']['rscdir']);
		$backwpupbackup = $backwpupcontainer->create_object($backwpupjobrun['STATIC']['JOB']['rscdir'].$backwpupjobrun['STATIC']['backupfile']);
		trigger_error(__('Upload to RSC now started ... ','backwpup'),E_USER_NOTICE);
		if ($backwpupbackup->load_from_filename($backwpupjobrun['STATIC']['JOB']['backupdir'].$backwpupjobrun['STATIC']['backupfile'])) {
			$backwpupjobrun['WORKING']['STEPTODO']=1+$backwpupjobrun['WORKING']['backupfilesize'];
			trigger_error(__('Backup File transferred to RSC://','backwpup').$backwpupjobrun['STATIC']['JOB']['rscContainer'].'/'.$backwpupjobrun['STATIC']['JOB']['rscdir'].$backwpupjobrun['STATIC']['backupfile'],E_USER_NOTICE);
			$backwpupjobrun['STATIC']['JOB']['lastbackupdownloadurl']=backwpup_admin_url('admin.php').'?page=backwpupbackups&action=downloadrsc&file='.$backwpupjobrun['STATIC']['JOB']['rscdir'].$backwpupjobrun['STATIC']['backupfile'].'&jobid='.$backwpupjobrun['STATIC']['JOB']['jobid'];
			$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_RSC'; //set done
		} else {
			trigger_error(__('Can not transfer backup to RSC.','backwpup'),E_USER_ERROR);
		}
	} catch (Exception $e) {
		trigger_error(__('Rackspase Cloud API:','backwpup').' '.$e->getMessage(),E_USER_ERROR);
	}
	try {
		if ($backwpupjobrun['STATIC']['JOB']['rscmaxbackups']>0) { //Delete old backups
			$backupfilelist=array();
			$contents = $backwpupcontainer->list_objects(0,NULL,NULL,$backwpupjobrun['STATIC']['JOB']['rscdir']);
			if (is_array($contents)) {
				foreach ($contents as $object) {
					$file=basename($object);
					if ($backwpupjobrun['STATIC']['JOB']['rscdir'].$file == $object) {//only in the folder and not in complete bucket
						if ($backwpupjobrun['STATIC']['JOB']['fileprefix'] == substr($file,0,strlen($backwpupjobrun['STATIC']['JOB']['fileprefix'])) and $backwpupjobrun['STATIC']['JOB']['fileformart'] == substr($file,-strlen($backwpupjobrun['STATIC']['JOB']['fileformart'])))
							$backupfilelist[]=$file;
					}
				}
			}
			if (sizeof($backupfilelist)>0) {
				rsort($backupfilelist);
				$numdeltefiles=0;
				for ($i=$backwpupjobrun['STATIC']['JOB']['rscmaxbackups'];$i<sizeof($backupfilelist);$i++) {
					if ($backwpupcontainer->delete_object($backwpupjobrun['STATIC']['JOB']['rscdir'].$backupfilelist[$i])) //delte files on Cloud
						$numdeltefiles++;
					else
						trigger_error(__('Can not delete file on RSC://','backwpup').$backwpupjobrun['STATIC']['JOB']['rscContainer'].$backwpupjobrun['STATIC']['JOB']['rscdir'].$backupfilelist[$i],E_USER_ERROR);
				}
				if ($numdeltefiles>0)
					trigger_error(sprintf(_n('One file deleted on RSC container','%d files deleted on RSC container',$numdeltefiles,'backwpup'),$numdeltefiles),E_USER_NOTICE);
			}
		}
	} catch (Exception $e) {
		trigger_error(__('Rackspase Cloud API:','backwpup').' '.$e->getMessage(),E_USER_ERROR);
	}

	$backwpupjobrun['WORKING']['STEPDONE']++;
}
?>