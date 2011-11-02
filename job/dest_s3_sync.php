<?PHP
function backwpup_job_dest_s3_sync() {
	global $backwpupjobrun;
	//get files
	$filelist=backwpup_get_option('WORKING','FILELIST'); //get file list
	$backwpupjobrun['WORKING']['STEPTODO']=count($filelist);
	$backwpupjobrun['WORKING']['STEPDONE']=0;
	trigger_error(sprintf(__('%d. Try to sync files with Amazon S3...','backwpup'),$backwpupjobrun['WORKING']['DEST_S3_SYNC']['STEP_TRY']),E_USER_NOTICE);
	
	if (!class_exists('AmazonS3'))
		require_once(dirname(__FILE__).'/../libs/aws/sdk.class.php');

	try {
		$s3 = new AmazonS3($backwpupjobrun['STATIC']['JOB']['awsAccessKey'], $backwpupjobrun['STATIC']['JOB']['awsSecretKey']);
		if ($s3->if_bucket_exists($backwpupjobrun['STATIC']['JOB']['awsBucket'])) {
			trigger_error(sprintf(__('Connected to S3 Bucket: %s','backwpup'),$backwpupjobrun['STATIC']['JOB']['awsBucket']),E_USER_NOTICE);
		} else {
			trigger_error(sprintf(__('S3 Bucket "%s" not exists!','backwpup'),$backwpupjobrun['STATIC']['JOB']['awsBucket']),E_USER_ERROR);
			$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_S3_SYNC'; //set done
			return;
		}
		//create general Upload Parameters
		$params=array();
		$params['acl']=AmazonS3::ACL_PRIVATE;
		if (!empty($backwpupjobrun['STATIC']['JOB']['awsssencrypt']))
			$params['encryption']=$backwpupjobrun['STATIC']['JOB']['awsssencrypt'];
		if ($backwpupjobrun['STATIC']['JOB']['awsrrs']) //set reduced redundancy or not
			$params['storage']=AmazonS3::STORAGE_REDUCED;
		else
			$params['storage']=AmazonS3::STORAGE_STANDARD;
		if (defined('CURLOPT_PROGRESSFUNCTION'))
			$params['curlopts']=array(CURLOPT_NOPROGRESS=>false,CURLOPT_PROGRESSFUNCTION=>'backwpup_job_curl_progresscallback',CURLOPT_BUFFERSIZE=>256);
		//get file list
		trigger_error(__('Get remote file list...','backwpup'),E_USER_NOTICE);
		$remotefilelist=array();
		if (($contents = $s3->list_objects($backwpupjobrun['STATIC']['JOB']['awsBucket'],array('prefix'=>$backwpupjobrun['STATIC']['JOB']['awsdir']))) === false) {
			trigger_error(__('Can not get filelist from S3 Bucket!','backwpup'),E_USER_ERROR);
			$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_S3_SYNC'; //set done
			return;		
		}
		foreach ($contents->body->Contents as $object) {
			$remotefilelist[]=array('FILE'=>(string)$object->Key,'BYTES'=>(float)$object->Size);
		}
	} catch (Exception $e) {
		trigger_error(sprintf(__('Amazon API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
		return;
	}			

	trigger_error(__('Sync files...','backwpup'),E_USER_NOTICE);
	foreach($remotefilelist as $remotefile) {
		$found=false;
		foreach($filelist as $filekey => $files) {
			if (ltrim($backwpupjobrun['STATIC']['JOB']['awsdir'],'/').$files['OUTFILE']==$remotefile['FILE']) {
				$found=true;
				if ($remotefile['BYTES']!=$files['SIZE']) {
					trigger_error(sprintf(__('Upload updated file to S3: %s','backwpup'),$files['OUTFILE']),E_USER_NOTICE);
					try {
						$params['fileUpload']=$files['FILE'];
						$s3->create_object($backwpupjobrun['STATIC']['JOB']['awsBucket'], $backwpupjobrun['STATIC']['JOB']['awsdir'].$files['OUTFILE'],$params);
					} catch (Exception $e) {
						trigger_error(sprintf(__('Amazon API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
					}
				}
				$backwpupjobrun['WORKING']['STEPDONE']++;
				unset($filelist[$filekey]);
				break;
			} 
		}
		//delete files not in filelist
		if (!$found) {
			trigger_error(sprintf(__('Delete file from S3: %s','backwpup'),$remotefile['FILE']),E_USER_NOTICE);
			try {
				$s3->delete_object($backwpupjobrun['STATIC']['JOB']['awsBucket'], $remotefile['FILE']);
			} catch (Exception $e) {
				trigger_error(sprintf(__('Amazon API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
			}	
		}
	}
	
	//upload files not on dest
	foreach($filelist as $filekey => $files) {
		trigger_error(sprintf(__('Upload new file to S3: %s','backwpup'),$files['OUTFILE']),E_USER_NOTICE);
		try {
			$params['fileUpload']=$files['FILE'];
			$s3->create_object($backwpupjobrun['STATIC']['JOB']['awsBucket'], $backwpupjobrun['STATIC']['JOB']['awsdir'].$files['OUTFILE'],$params);
		} catch (Exception $e) {
			trigger_error(sprintf(__('Amazon API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
		}			
		$backwpupjobrun['WORKING']['STEPDONE']++;
		unset($filelist[$filekey]);
	}
	if (count($filelist)==0)
		$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_S3_SYNC'; //set done
}
?>