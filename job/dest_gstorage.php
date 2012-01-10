<?PHP
function backwpup_job_dest_gstorage() {
	global $backwpupjobrun;
	$backwpupjobrun['STEPTODO']=2+$backwpupjobrun['backupfilesize'];
	$backwpupjobrun['STEPDONE']=0;
	trigger_error(sprintf(__('%d. Try sending backup to Google Storage...','backwpup'),$backwpupjobrun['DEST_GSTORAGE']['STEP_TRY']),E_USER_NOTICE);

	if (!class_exists('AmazonS3'))
		require_once(dirname(__FILE__).'/../libs/aws/sdk.class.php');
	
	try {
		CFCredentials::set(array('backwpup' => array('key'=>$backwpupjobrun['STATIC']['JOB']['GStorageAccessKey'],'secret'=>$backwpupjobrun['STATIC']['JOB']['GStorageSecret'],'default_cache_config'=>'','certificate_authority'=>true),'@default' => 'backwpup'));
		$gstorage = new AmazonS3();
		//set up s3 for google
		$gstorage->set_hostname('commondatastorage.googleapis.com');
		$gstorage->allow_hostname_override(false);
		if ($gstorage->if_bucket_exists($backwpupjobrun['STATIC']['JOB']['GStorageBucket'])) {
			trigger_error(sprintf(__('Connected to GStorage Bucket: %s','backwpup'),$backwpupjobrun['STATIC']['JOB']['GStorageBucket']),E_USER_NOTICE);
		} else {
			trigger_error(sprintf(__('GStorage Bucket "%s" not exists!','backwpup'),$backwpupjobrun['STATIC']['JOB']['GStorageBucket']),E_USER_ERROR);
			$backwpupjobrun['STEPSDONE'][]='DEST_GSTORAGE'; //set done
			return;
		}
			
		//set surl Prozess bar
		$param=array();
		$param['fileUpload']=$backwpupjobrun['STATIC']['JOB']['backupdir'].$backwpupjobrun['STATIC']['backupfile'];
		$param['acl']='private';
		if (defined('CURLOPT_PROGRESSFUNCTION'))
			$params['curlopts']=array(CURLOPT_NOPROGRESS=>false,CURLOPT_PROGRESSFUNCTION=>'backwpup_job_curl_progresscallback',CURLOPT_BUFFERSIZE=>256);
		trigger_error(__('Upload to GStorage now started... ','backwpup'),E_USER_NOTICE);
		//transfere file to GStorage
		$result=$gstorage->create_object($backwpupjobrun['STATIC']['JOB']['GStorageBucket'], $backwpupjobrun['STATIC']['JOB']['GStoragedir'].$backwpupjobrun['STATIC']['backupfile'],$param);
		$result=(array)$result;
		if ($result["status"]=200 and $result["status"]<300)  {
			$backwpupjobrun['STEPTODO']=1+$backwpupjobrun['backupfilesize'];
			trigger_error(sprintf(__('Backup transferred to %s','backwpup'),"https://sandbox.google.com/storage/".$backwpupjobrun['STATIC']['JOB']['GStorageBucket']."/".$backwpupjobrun['STATIC']['JOB']['GStoragedir'].$backwpupjobrun['STATIC']['backupfile']),E_USER_NOTICE);
			$backwpupjobrun['STATIC']['JOB']['lastbackupdownloadurl']=backwpup_admin_url('admin.php').'?page=backwpupbackups&action=downloads3&file='.$backwpupjobrun['STATIC']['JOB']['GStoragedir'].$backwpupjobrun['STATIC']['backupfile'].'&jobid='.$backwpupjobrun['STATIC']['JOB']['jobid'];
			$backwpupjobrun['STEPSDONE'][]='DEST_GSTORAGE'; //set done
		} else {
			trigger_error(sprintf(__('Can not transfer backup to GStorage! (%1$d) %2$s','backwpup'),$result["status"],$result["Message"]),E_USER_ERROR);
		}

	} catch (Exception $e) {
		trigger_error(sprintf(__('GStorage API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
		return;
	}
	try {
		if ($gstorage->if_bucket_exists($backwpupjobrun['STATIC']['JOB']['GStorageBucket'])) {
			if ($backwpupjobrun['STATIC']['JOB']['GStoragemaxbackups']>0) { //Delete old backups
				$backupfilelist=array();
				if (($contents = $gstorage->list_objects($backwpupjobrun['STATIC']['JOB']['GStorageBucket'],array('prefix'=>$backwpupjobrun['STATIC']['JOB']['GStoragedir']))) !== false) {
					foreach ($contents->body->Contents as $object) {
						$file=basename($object->Key);
						$changetime=strtotime($object->LastModified);
						if ($backwpupjobrun['STATIC']['JOB']['fileprefix'] == substr($file,0,strlen($backwpupjobrun['STATIC']['JOB']['fileprefix'])) and $backwpupjobrun['STATIC']['JOB']['fileformart'] == substr($file,-strlen($backwpupjobrun['STATIC']['JOB']['fileformart'])))
							$backupfilelist[$changetime]=$file;
					}
				}
				if (count($backupfilelist)>$backwpupjobrun['STATIC']['JOB']['GStoragemaxbackups']) {
					$numdeltefiles=0;
					while ($file=array_shift($backupfilelist)) {
						if (count($backupfilelist)<$backwpupjobrun['STATIC']['JOB']['GStoragemaxbackups'])
							break;			
						if ($gstorage->delete_object($backwpupjobrun['STATIC']['JOB']['GStorageBucket'], $backwpupjobrun['STATIC']['JOB']['GStoragedir'].$file)) //delte files on S3
							$numdeltefiles++;
						else
							trigger_error(sprintf(__('Can not delete backup on GStorage://%s','backwpup'),$backwpupjobrun['STATIC']['JOB']['GStorageBucket'].'/'.$backwpupjobrun['STATIC']['JOB']['GStoragedir'].$file),E_USER_ERROR);
					}
					if ($numdeltefiles>0)
						trigger_error(sprintf(_n('One file deleted on GStorage Bucket','%d files deleted on GStorage Bucket',$numdeltefiles,'backwpup'),$numdeltefiles),E_USER_NOTICE);
				}
			}
		}
	} catch (Exception $e) {
		trigger_error(sprintf(__('GStorage API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
		return;
	}

	$backwpupjobrun['STEPDONE']++;
}
?>