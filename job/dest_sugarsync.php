<?PHP
function backwpup_job_dest_sugarsync() {
	global $backwpupjobrun;
	$backwpupjobrun['WORKING']['STEPTODO']=2+$backwpupjobrun['WORKING']['backupfilesize'];
	$backwpupjobrun['WORKING']['STEPDONE']=0;
	trigger_error(sprintf(__('%d. Try to sending backup to SugarSync...','backwpup'),$backwpupjobrun['WORKING']['DEST_SUGARSYNC']['STEP_TRY']),E_USER_NOTICE);

	require_once(realpath(dirname(__FILE__).'/../libs/sugarsync.php'));

	try {
		$sugarsync = new SugarSync($backwpupjobrun['STATIC']['JOB']['sugaruser'],base64_decode($backwpupjobrun['STATIC']['JOB']['sugarpass']),BACKWPUP_SUGARSYNC_ACCESSKEY, BACKWPUP_SUGARSYNC_PRIVATEACCESSKEY);
		//Check Quota
		$user=$sugarsync->user();
		if (!empty($user->nickname)) {
			trigger_error(sprintf(__('Authed to SugarSync with Nick %s','backwpup'),$user->nickname),E_USER_NOTICE);
		}
		$sugarsyncfreespase=(float)$user->quota->limit-(float)$user->quota->usage; //float fixes bug for display of no free space
		if ($backwpupjobrun['WORKING']['backupfilesize']>$sugarsyncfreespase) {
			trigger_error(__('No free space left on SugarSync!!!','backwpup'),E_USER_ERROR);
			$backwpupjobrun['WORKING']['STEPTODO']=1+$backwpupjobrun['WORKING']['backupfilesize'];
			$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_SUGARSYNC'; //set done
			return;
		} else {
			trigger_error(sprintf(__('%s free on SugarSync','backwpup'),backwpup_formatBytes($sugarsyncfreespase)),E_USER_NOTICE);
		}
		//Create and change folder
		$sugarsync->mkdir($backwpupjobrun['STATIC']['JOB']['sugardir'],$backwpupjobrun['STATIC']['JOB']['sugarroot']);
		$dirid=$sugarsync->chdir($backwpupjobrun['STATIC']['JOB']['sugardir'],$backwpupjobrun['STATIC']['JOB']['sugarroot']);
		//Upload to Sugarsync
		$sugarsync->setProgressFunction('curl_progresscallback');
		trigger_error(__('Upload to SugarSync now started... ','backwpup'),E_USER_NOTICE);
		$reponse=$sugarsync->upload($backwpupjobrun['STATIC']['JOB']['backupdir'].$backwpupjobrun['STATIC']['backupfile']);
		if (is_object($reponse)) {
			$backwpupjobrun['STATIC']['JOB']['lastbackupdownloadurl']=backwpup_admin_url('admin.php').'?page=backwpupbackups&action=downloadsugarsync&file='.(string)$reponse.'&jobid='.$backwpupjobrun['STATIC']['JOB']['jobid'];
			$backwpupjobrun['WORKING']['STEPDONE']++;
			$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_SUGARSYNC'; //set done
			trigger_error(sprintf(__('Backup transferred to %s','backwpup'),'https://'.$user->nickname.'.sugarsync.com/'.$sugarsync->showdir($dirid).$backwpupjobrun['STATIC']['backupfile']),E_USER_NOTICE);
		} else {
			trigger_error(__('Can not transfer backup to SugarSync!','backwpup'),E_USER_ERROR);
			return;
		}
		$sugarsync->setProgressFunction('');

		if ($backwpupjobrun['STATIC']['JOB']['sugarmaxbackups']>0) { //Delete old backups
			$backupfilelist=array();
			$getfiles=$sugarsync->getcontents('file');
			if (is_object($getfiles)) {
				foreach ($getfiles->file as $getfile) {
					if ($backwpupjobrun['STATIC']['JOB']['fileprefix'] == substr($getfile->displayName,0,strlen($backwpupjobrun['STATIC']['JOB']['fileprefix'])) and $backwpupjobrun['STATIC']['JOB']['fileformart'] == substr($getfile->displayName,-strlen($backwpupjobrun['STATIC']['JOB']['fileformart'])))
						$backupfilelist[]=$getfile->displayName;
						$backupfileref[utf8_encode($getfile->displayName)]=$getfile->ref;
				}
			}
			if (sizeof($backupfilelist)>0) {
				rsort($backupfilelist);
				$numdeltefiles=0;
				for ($i=$backwpupjobrun['STATIC']['JOB']['sugarmaxbackups'];$i<count($backupfilelist);$i++) {
					$sugarsync->delete($backupfileref[utf8_encode($backupfilelist[$i])]); //delete files on Cloud
					$numdeltefiles++;
				}
				if ($numdeltefiles>0)
					trigger_error(sprintf(_n('One file deleted on SugarSync folder','%d files deleted on SugarSync folder',$numdeltefiles,'backwpup'),$numdeltefiles),E_USER_NOTICE);
			}
		}
	} catch (Exception $e) {
		trigger_error(sprintf(__('SugarSync API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
	}

	$backwpupjobrun['WORKING']['STEPDONE']++;
}
?>