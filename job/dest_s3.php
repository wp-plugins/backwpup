<?PHP
function backwpup_job_dest_s3() {
	global $backwpupjobrun;
	trigger_error(sprintf(__('%d. Try to sending backup file to Amazon S3...','backwpup'),$backwpupjobrun['WORKING']['DEST_S3']['STEP_TRY']),E_USER_NOTICE);
	$backwpupjobrun['WORKING']['STEPTODO']=2+$backwpupjobrun['WORKING']['backupfilesize'];
	$backwpupjobrun['WORKING']['STEPDONE']=0;

	require_once(dirname(__FILE__).'/../libs/aws/sdk.class.php');
	need_free_memory(26214400*1.1);

	try {
		$s3 = new AmazonS3($backwpupjobrun['STATIC']['JOB']['awsAccessKey'], $backwpupjobrun['STATIC']['JOB']['awsSecretKey']);
		if ($s3->if_bucket_exists($backwpupjobrun['STATIC']['JOB']['awsBucket'])) {
			trigger_error(sprintf(__('Connected to S3 Bucket: %s','backwpup'),$backwpupjobrun['STATIC']['JOB']['awsBucket']),E_USER_NOTICE);
			//Transfer Backup to S3
			if ($backwpupjobrun['STATIC']['JOB']['awsrrs']) //set reduced redundancy or not
				$storage=AmazonS3::STORAGE_REDUCED;
			else
				$storage=AmazonS3::STORAGE_STANDARD;
			//set surl Prozess bar
			$curlops=array();
			if (defined('CURLOPT_PROGRESSFUNCTION'))
				$curlops=array(CURLOPT_NOPROGRESS=>false,CURLOPT_PROGRESSFUNCTION=>'curl_progresscallback',CURLOPT_BUFFERSIZE=>256);
			trigger_error(__('Upload to Amazon S3 now started... ','backwpup'),E_USER_NOTICE);
			//transfere file to S3
			$result=$s3->create_mpu_object($backwpupjobrun['STATIC']['JOB']['awsBucket'], $backwpupjobrun['STATIC']['JOB']['awsdir'].$backwpupjobrun['STATIC']['backupfile'], array('fileUpload' => $backwpupjobrun['STATIC']['JOB']['backupdir'].$backwpupjobrun['STATIC']['backupfile'],'acl' => AmazonS3::ACL_PRIVATE,'storage' => $storage,'partSize'=>26214400,'curlopts'=>$curlops));
			$result=(array)$result;
			if ($result["status"]=200 and $result["status"]<300)  {
				$backwpupjobrun['WORKING']['STEPTODO']=1+$backwpupjobrun['WORKING']['backupfilesize'];
				trigger_error(sprintf(__('Backup transferred to %s','backwpup'),$result["header"]["_info"]["url"]),E_USER_NOTICE);
				$backwpupjobrun['STATIC']['JOB']['lastbackupdownloadurl']=backwpup_admin_url('admin.php').'?page=backwpupbackups&action=downloads3&file='.$backwpupjobrun['STATIC']['JOB']['awsdir'].$backwpupjobrun['STATIC']['backupfile'].'&jobid='.$backwpupjobrun['STATIC']['JOB']['jobid'];
				$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_S3'; //set done
			} else {
				trigger_error(sprintf(__('Can not transfer backup to S3! (%1$d) %2$s','backwpup'),$result["status"],$result["Message"]),E_USER_ERROR);
			}
		} else {
			trigger_error(sprintf(__('S3 Bucket "%s" not exists!','backwpup'),$backwpupjobrun['STATIC']['JOB']['awsBucket']),E_USER_ERROR);
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
						if ($backwpupjobrun['STATIC']['JOB']['fileprefix'] == substr($file,0,strlen($backwpupjobrun['STATIC']['JOB']['fileprefix'])) and $backwpupjobrun['STATIC']['JOB']['fileformart'] == substr($file,-strlen($backwpupjobrun['STATIC']['JOB']['fileformart'])))
							$backupfilelist[]=$file;
					}
				}
				if (sizeof($backupfilelist)>0) {
					rsort($backupfilelist);
					$numdeltefiles=0;
					for ($i=$backwpupjobrun['STATIC']['JOB']['awsmaxbackups'];$i<sizeof($backupfilelist);$i++) {
						if ($s3->delete_object($backwpupjobrun['STATIC']['JOB']['awsBucket'], $backwpupjobrun['STATIC']['JOB']['awsdir'].$backupfilelist[$i])) //delte files on S3
							$numdeltefiles++;
						else
							trigger_error(sprintf(__('Can not delete backup on S3://%s','backwpup'),$backwpupjobrun['STATIC']['JOB']['awsBucket'].'/'.$backwpupjobrun['STATIC']['JOB']['awsdir'].$backupfilelist[$i]),E_USER_ERROR);
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