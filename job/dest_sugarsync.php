<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function dest_sugarsync() {
	if (empty($_SESSION['JOB']['sugaruser']) or empty($_SESSION['JOB']['sugarpass']) or empty($_SESSION['JOB']['sugarroot'])) {
		$_SESSION['WORKING']['STEPSDONE'][]='DEST_SUGARSYNC'; //set done	
		return;
	}
	$_SESSION['WORKING']['STEPTODO']=2+filesize($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile']);
	$_SESSION['WORKING']['STEPDONE']=0;
	trigger_error($_SESSION['WORKING']['DEST_SUGARSYNC']['STEP_TRY'].'. '.__('Try to sending backup file to sugarsync...','backwpup'),E_USER_NOTICE);

	require_once(realpath(dirname(__FILE__).'/../libs/sugarsync.php'));
	
	need_free_memory(filesize($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile'])*1.5); 
	
	try {
		$sugarsync = new SugarSync($_SESSION['JOB']['sugaruser'],base64_decode($_SESSION['JOB']['sugarpass']),$_SESSION['BACKWPUP']['SUGARSYNC_ACCESSKEY'], $_SESSION['BACKWPUP']['SUGARSYNC_PRIVATEACCESSKEY']);
		//Check Quota
		$user=$sugarsync->user();
		if (!empty($user->nickname)) {
			trigger_error(__('Authed to SugarSync with Nick ','backwpup').$user->nickname,E_USER_NOTICE);
		}
		$sugarsyncfreespase=(float)$user->quota->limit-(float)$user->quota->usage; //float fixes bug for display of no free space
		if (filesize($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile'])>$sugarsyncfreespase) {
			trigger_error(__('No free space left on SugarSync!!!','backwpup'),E_USER_ERROR);
			$_SESSION['WORKING']['STEPTODO']=1+filesize($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile']);
			$_SESSION['WORKING']['STEPSDONE'][]='DEST_SUGARSYNC'; //set done
			return;
		} else {
			trigger_error(__('Free Space on SugarSync: ','backwpup').formatBytes($sugarsyncfreespase),E_USER_NOTICE);
		}
		//Create and change folder
		$sugarsync->mkdir($_SESSION['JOB']['sugardir'],$_SESSION['JOB']['sugarroot']);
		$dirid=$sugarsync->chdir($_SESSION['JOB']['sugardir'],$_SESSION['JOB']['sugarroot']);
		//Upload to Sugarsync
		$sugarsync->setProgressFunction('curl_progresscallback');
		trigger_error(__('Upload to SugarSync now started ... ','backwpup'),E_USER_NOTICE);
		$reponse=$sugarsync->upload($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile']);
		if (is_object($reponse)) {
			$_SESSION['JOB']['lastbackupdownloadurl']=$_SESSION['WP']['ADMINURL'].'?page=backwpupbackups&action=downloadsugarsync&file='.(string)$reponse.'&jobid='.$_SESSION['JOB']['jobid'];
			$_SESSION['WORKING']['STEPDONE']++;
			trigger_error(__('Backup File transferred to SugarSync://','backwpup').$sugarsync->showdir($dirid).$_SESSION['STATIC']['backupfile'],E_USER_NOTICE);
		} else {
			trigger_error(__('Can not transfere Backup file to SugarSync:','backwpup'),E_USER_ERROR);
			return;
		}	
		$sugarsync->setProgressFunction('');
		
		if ($_SESSION['JOB']['sugarmaxbackups']>0) { //Delete old backups
			$backupfilelist=array();
			$getfiles=$sugarsync->getcontents('file');
			if (is_object($getfiles)) {
				foreach ($getfiles->file as $getfile) {
					if ($_SESSION['JOB']['fileprefix'] == substr($getfile->displayName,0,strlen($_SESSION['JOB']['fileprefix'])) and $_SESSION['JOB']['fileformart'] == substr($getfile->displayName,-strlen($_SESSION['JOB']['fileformart'])))
						$backupfilelist[]=$getfile->displayName;
						$backupfileref[utf8_encode($getfile->displayName)]=$getfile->ref;
				}
			}
			if (sizeof($backupfilelist)>0) {
				rsort($backupfilelist);
				$numdeltefiles=0;
				for ($i=$_SESSION['JOB']['sugarmaxbackups'];$i<count($backupfilelist);$i++) {
					$sugarsync->delete($backupfileref[utf8_encode($backupfilelist[$i])]); //delete files on Cloud
					$numdeltefiles++;
				}
				if ($numdeltefiles>0)
					trigger_error($numdeltefiles.' '.__('files deleted on Sugarsync folder!','backwpup'),E_USER_NOTICE);
			}
		}	
	} catch (Exception $e) {
		trigger_error(__('SugarSync API:','backwpup').' '.$e->getMessage(),E_USER_ERROR);
	} 

	$_SESSION['WORKING']['STEPDONE']++;
	$_SESSION['WORKING']['STEPSDONE'][]='DEST_SUGARSYNC'; //set done
}
?>