<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function dest_sugarsync() {
	global $WORKING,$STATIC;
	if (empty($STATIC['JOB']['sugaruser']) or empty($STATIC['JOB']['sugarpass']) or empty($STATIC['JOB']['sugarroot'])) {
		$WORKING['STEPSDONE'][]='DEST_SUGARSYNC'; //set done	
		return;
	}
	$WORKING['STEPTODO']=2+filesize($STATIC['JOB']['backupdir'].$STATIC['backupfile']);
	$WORKING['STEPDONE']=0;
	trigger_error($WORKING['DEST_SUGARSYNC']['STEP_TRY'].'. '.__('Try to sending backup file to sugarsync...','backwpup'),E_USER_NOTICE);

	require_once(realpath(dirname(__FILE__).'/../libs/sugarsync.php'));
	
	try {
		$sugarsync = new SugarSync($STATIC['JOB']['sugaruser'],base64_decode($STATIC['JOB']['sugarpass']),$STATIC['BACKWPUP']['SUGARSYNC_ACCESSKEY'], $STATIC['BACKWPUP']['SUGARSYNC_PRIVATEACCESSKEY']);
		//Check Quota
		$user=$sugarsync->user();
		if (!empty($user->nickname)) {
			trigger_error(__('Authed to SugarSync with Nick ','backwpup').$user->nickname,E_USER_NOTICE);
		}
		$sugarsyncfreespase=(float)$user->quota->limit-(float)$user->quota->usage; //float fixes bug for display of no free space
		if (filesize($STATIC['JOB']['backupdir'].$STATIC['backupfile'])>$sugarsyncfreespase) {
			trigger_error(__('No free space left on SugarSync!!!','backwpup'),E_USER_ERROR);
			$WORKING['STEPTODO']=1+filesize($STATIC['JOB']['backupdir'].$STATIC['backupfile']);
			$WORKING['STEPSDONE'][]='DEST_SUGARSYNC'; //set done
			return;
		} else {
			trigger_error(__('Free Space on SugarSync: ','backwpup').formatBytes($sugarsyncfreespase),E_USER_NOTICE);
		}
		//Create and change folder
		$sugarsync->mkdir($STATIC['JOB']['sugardir'],$STATIC['JOB']['sugarroot']);
		$dirid=$sugarsync->chdir($STATIC['JOB']['sugardir'],$STATIC['JOB']['sugarroot']);
		//Upload to Sugarsync
		$sugarsync->setProgressFunction('curl_progresscallback');
		trigger_error(__('Upload to SugarSync now started ... ','backwpup'),E_USER_NOTICE);
		@set_time_limit($STATIC['CFG']['jobscriptruntimelong']);
		$reponse=$sugarsync->upload($STATIC['JOB']['backupdir'].$STATIC['backupfile']);
		if (is_object($reponse)) {
			$STATIC['JOB']['lastbackupdownloadurl']=$STATIC['WP']['ADMINURL'].'?page=backwpupbackups&action=downloadsugarsync&file='.(string)$reponse.'&jobid='.$STATIC['JOB']['jobid'];
			$WORKING['STEPDONE']++;
			trigger_error(__('Backup File transferred to SugarSync://','backwpup').$sugarsync->showdir($dirid).$STATIC['backupfile'],E_USER_NOTICE);
		} else {
			trigger_error(__('Can not transfere Backup file to SugarSync:','backwpup'),E_USER_ERROR);
			return;
		}	
		$sugarsync->setProgressFunction('');
		
		if ($STATIC['JOB']['sugarmaxbackups']>0) { //Delete old backups
			$backupfilelist=array();
			$getfiles=$sugarsync->getcontents('file');
			if (is_object($getfiles)) {
				foreach ($getfiles->file as $getfile) {
					if ($STATIC['JOB']['fileprefix'] == substr($getfile->displayName,0,strlen($STATIC['JOB']['fileprefix'])) and $STATIC['JOB']['fileformart'] == substr($getfile->displayName,-strlen($STATIC['JOB']['fileformart'])))
						$backupfilelist[]=$getfile->displayName;
						$backupfileref[utf8_encode($getfile->displayName)]=$getfile->ref;
				}
			}
			if (sizeof($backupfilelist)>0) {
				rsort($backupfilelist);
				$numdeltefiles=0;
				for ($i=$STATIC['JOB']['sugarmaxbackups'];$i<count($backupfilelist);$i++) {
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

	$WORKING['STEPDONE']++;
	$WORKING['STEPSDONE'][]='DEST_SUGARSYNC'; //set done
}
?>