<?PHP
function backwpup_job_dest_s3() {
	global $backwpupjobrun;
	$backwpupjobrun['WORKING']['STEPTODO']=2+$backwpupjobrun['WORKING']['backupfilesize'];
	$backwpupjobrun['WORKING']['STEPDONE']=0;
	trigger_error(sprintf(__('%d. Try to sending backup file to Amazon S3...','backwpup'),$backwpupjobrun['WORKING']['DEST_S3']['STEP_TRY']),E_USER_NOTICE);
	
	if (!class_exists('AmazonS3'))
		require_once(dirname(__FILE__).'/../libs/aws/sdk.class.php');

	try {
		CFCredentials::set(array('backwpup' => array('key'=>$backwpupjobrun['STATIC']['JOB']['awsAccessKey'],'secret'=>$backwpupjobrun['STATIC']['JOB']['awsSecretKey'],'default_cache_config'=>'','certificate_authority'=>true),'@default' => 'backwpup'));
		$s3 = new AmazonS3();
		if ($s3->if_bucket_exists($backwpupjobrun['STATIC']['JOB']['awsBucket'])) {
			trigger_error(sprintf(__('Connected to S3 Bucket: %s','backwpup'),$backwpupjobrun['STATIC']['JOB']['awsBucket']),E_USER_NOTICE);
		} else {
			trigger_error(sprintf(__('S3 Bucket "%s" not exists!','backwpup'),$backwpupjobrun['STATIC']['JOB']['awsBucket']),E_USER_ERROR);
			$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_S3'; //set done
			return;
		}		
		//create Parameter
		$params=array();
		$params['fileUpload']=$backwpupjobrun['STATIC']['JOB']['backupdir'].$backwpupjobrun['STATIC']['backupfile'];
		$params['acl']=AmazonS3::ACL_PRIVATE;
		if (!empty($backwpupjobrun['STATIC']['JOB']['awsssencrypt']))
			$params['encryption']=$backwpupjobrun['STATIC']['JOB']['awsssencrypt'];
		if ($backwpupjobrun['STATIC']['JOB']['awsrrs']) //set reduced redundancy or not
			$params['storage']=AmazonS3::STORAGE_REDUCED;
		else
			$params['storage']=AmazonS3::STORAGE_STANDARD;
		if (defined('CURLOPT_PROGRESSFUNCTION'))
			$params['curlopts']=array(CURLOPT_NOPROGRESS=>false,CURLOPT_PROGRESSFUNCTION=>'backwpup_job_curl_progresscallback',CURLOPT_BUFFERSIZE=>256);
		//transfere file to S3
		trigger_error(__('Upload to Amazon S3 now started... ','backwpup'),E_USER_NOTICE);
		$result=$s3->create_object($backwpupjobrun['STATIC']['JOB']['awsBucket'], $backwpupjobrun['STATIC']['JOB']['awsdir'].$backwpupjobrun['STATIC']['backupfile'],$params);
		$result=(array)$result;
		if ($result["status"]=200 and $result["status"]<300)  {
			$backwpupjobrun['WORKING']['STEPTODO']=1+$backwpupjobrun['WORKING']['backupfilesize'];
			trigger_error(sprintf(__('Backup transferred to %s','backwpup'),$result["header"]["_info"]["url"]),E_USER_NOTICE);
			backwpup_update_option('job_'.$backwpupjobrun['STATIC']['JOB']['jobid'],'lastbackupdownloadurl',backwpup_admin_url('admin.php').'?page=backwpupbackups&action=downloads3&file='.$backwpupjobrun['STATIC']['JOB']['awsdir'].$backwpupjobrun['STATIC']['backupfile'].'&jobid='.$backwpupjobrun['STATIC']['JOB']['jobid']);
			$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_S3'; //set done
		} else {
			trigger_error(sprintf(__('Can not transfer backup to S3! (%1$d) %2$s','backwpup'),$result["status"],$result["Message"]),E_USER_ERROR);
		}
	} catch (Exception $e) {
		trigger_error(sprintf(__('Amazon API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
		return;
	}
	try {
		if ($s3->if_bucket_exists($backwpupjobrun['STATIC']['JOB']['awsBucket'])) {
			if ($backwpupjobrun['STATIC']['JOB']['awsmaxbackups']>0) { //Delete old backups
				$backupfilelist=array();
				if (($contents = $s3->list_objects($backwpupjobrun['STATIC']['JOB']['awsBucket'],array('prefix'=>$backwpupjobrun['STATIC']['JOB']['awsdir']))) !== false) {
					foreach ($contents->body->Contents as $object) {
						$file=basename($object->Key);
						$changetime=strtotime($object->LastModified);
						if ($backwpupjobrun['STATIC']['JOB']['fileprefix'] == substr($file,0,strlen($backwpupjobrun['STATIC']['JOB']['fileprefix'])))
							$backupfilelist[$changetime]=$file;
					}
				}
				if (count($backupfilelist)>$backwpupjobrun['STATIC']['JOB']['awsmaxbackups']) {
					$numdeltefiles=0;
					while ($file=array_shift($backupfilelist)) {
						if (count($backupfilelist)<$backwpupjobrun['STATIC']['JOB']['awsmaxbackups'])
							break;			
						if ($s3->delete_object($backwpupjobrun['STATIC']['JOB']['awsBucket'], $backwpupjobrun['STATIC']['JOB']['awsdir'].$file)) //delte files on S3
							$numdeltefiles++;
						else
							trigger_error(sprintf(__('Can not delete backup on S3://%s','backwpup'),$backwpupjobrun['STATIC']['JOB']['awsBucket'].'/'.$backwpupjobrun['STATIC']['JOB']['awsdir'].$file),E_USER_ERROR);
					}
					if ($numdeltefiles>0)
						trigger_error(sprintf(_n('One file deleted on S3 Bucket','%d files deleted on S3 Bucket',$numdeltefiles,'backwpup'),$numdeltefiles),E_USER_NOTICE);
				}
			}
		}
	} catch (Exception $e) {
		trigger_error(sprintf(__('Amazon API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
		return;
	}

	$backwpupjobrun['WORKING']['STEPDONE']++;
}
?>