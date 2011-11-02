<?PHP
function backwpup_job_dest_gstorage_sync() {
	global $backwpupjobrun;
	//get files
	$filelist=backwpup_get_option('WORKING','FILELIST'); //get file list
	$backwpupjobrun['WORKING']['STEPTODO']=count($filelist);
	$backwpupjobrun['WORKING']['STEPDONE']=0;
	trigger_error(sprintf(__('%d. Try to sync files with Google Storage...','backwpup'),$backwpupjobrun['WORKING']['DEST_GSTORAGE_SYNC']['STEP_TRY']),E_USER_NOTICE);
	
	if (!class_exists('AmazonS3'))
		require_once(dirname(__FILE__).'/../libs/aws/sdk.class.php');

	try {
		$gstorage = new AmazonS3($backwpupjobrun['STATIC']['JOB']['GStorageAccessKey'], $backwpupjobrun['STATIC']['JOB']['GStorageSecret']);
		//set up s3 for google
		$gstorage->set_hostname('commondatastorage.googleapis.com');
		$gstorage->allow_hostname_override(false);
		if ($gstorage->if_bucket_exists($backwpupjobrun['STATIC']['JOB']['GStorageBucket'])) {
			trigger_error(sprintf(__('Connected to GStorage Bucket: %s','backwpup'),$backwpupjobrun['STATIC']['JOB']['GStorageBucket']),E_USER_NOTICE);
		} else {
			trigger_error(sprintf(__('GStorage Bucket "%s" not exists!','backwpup'),$backwpupjobrun['STATIC']['JOB']['GStorageBucket']),E_USER_ERROR);
			$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_GSTORAGE_SYNC'; //set done
			return;
		}
		//create general Upload Parameters
		$params=array();
		$params['acl']='private';
		if (defined('CURLOPT_PROGRESSFUNCTION'))
			$params['curlopts']=array(CURLOPT_NOPROGRESS=>false,CURLOPT_PROGRESSFUNCTION=>'backwpup_job_curl_progresscallback',CURLOPT_BUFFERSIZE=>256);
		//get file list
		trigger_error(__('Get remote file list...','backwpup'),E_USER_NOTICE);
		$remotefilelist=array();
		if (($contents = $gstorage->list_objects($backwpupjobrun['STATIC']['JOB']['GStorageBucket'],array('prefix'=>$backwpupjobrun['STATIC']['JOB']['GStoragedir']))) === false) {
			trigger_error(__('Can not get filelist from GStorage Bucket!','backwpup'),E_USER_ERROR);
			$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_GSTORAGE_SYNC'; //set done
			return;		
		}
		foreach ($contents->body->Contents as $object) {
			$remotefilelist[]=array('FILE'=>(string)$object->Key,'BYTES'=>(float)$object->Size);
		}
	} catch (Exception $e) {
		trigger_error(sprintf(__('GStorage API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
		return;
	}			

	trigger_error(__('Sync files...','backwpup'),E_USER_NOTICE);
	foreach($remotefilelist as $remotefile) {
		$found=false;
		foreach($filelist as $filekey => $files) {
			if (ltrim($backwpupjobrun['STATIC']['JOB']['GStoragedir'],'/').$files['OUTFILE']==$remotefile['FILE']) {
				$found=true;
				if ($remotefile['BYTES']!=$files['SIZE']) {
					trigger_error(sprintf(__('Upload updated file to GStorage: %s','backwpup'),$files['OUTFILE']),E_USER_NOTICE);
					try {
						$params['fileUpload']=$files['FILE'];
						$gstorage->create_object($backwpupjobrun['STATIC']['JOB']['GStorageBucket'], $backwpupjobrun['STATIC']['JOB']['GStoragedir'].$files['OUTFILE'],$params);
					} catch (Exception $e) {
						trigger_error(sprintf(__('GStorage API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
					}
				}
				$backwpupjobrun['WORKING']['STEPDONE']++;
				unset($filelist[$filekey]);
				break;
			} 
		}
		//delete files not in filelist
		if (!$found) {
			trigger_error(sprintf(__('Delete file from GStorage: %s','backwpup'),$remotefile['FILE']),E_USER_NOTICE);
			try {
				$gstorage->delete_object($backwpupjobrun['STATIC']['JOB']['GStorageBucket'], $remotefile['FILE']);
			} catch (Exception $e) {
				trigger_error(sprintf(__('GStorage API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
			}	
		}
	}
	
	//upload files not on dest
	foreach($filelist as $filekey => $files) {
		trigger_error(sprintf(__('Upload new file to GStorage: %s','backwpup'),$files['OUTFILE']),E_USER_NOTICE);
		try {
			$params['fileUpload']=$files['FILE'];
			$gstorage->create_object($backwpupjobrun['STATIC']['JOB']['GStorageBucket'], $backwpupjobrun['STATIC']['JOB']['GStoragedir'].$files['OUTFILE'],$params);
		} catch (Exception $e) {
			trigger_error(sprintf(__('GStorage API: %s','backwpup'),$e->getMessage()),E_USER_ERROR);
		}			
		$backwpupjobrun['WORKING']['STEPDONE']++;
		unset($filelist[$filekey]);
	}
	if (count($filelist)==0)
		$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_GSTORAGE_SYNC'; //set done
}
?>