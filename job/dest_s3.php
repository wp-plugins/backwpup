<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function dest_s3() {
	if (empty($_SESSION['JOB']['awsAccessKey']) or empty($_SESSION['JOB']['awsSecretKey']) or empty($_SESSION['JOB']['awsBucket'])) {
		$_SESSION['WORKING']['STEPSDONE'][]='DEST_S3'; //set done	
		return;
	}
	trigger_error($_SESSION['WORKING']['DEST_S3']['STEP_TRY'].'. '.__('Try to sending backup file to Amazon S3...','backwpup'),E_USER_NOTICE);
	$_SESSION['WORKING']['STEPTODO']=2+filesize($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile']);
	$_SESSION['WORKING']['STEPDONE']=0;

	require_once(dirname(__FILE__).'/../libs/aws/sdk.class.php');
	need_free_memory(26214400*1.1); 
	
	try {
		$s3 = new AmazonS3($_SESSION['JOB']['awsAccessKey'], $_SESSION['JOB']['awsSecretKey']);
		if ($s3->if_bucket_exists($_SESSION['JOB']['awsBucket'])) {
			trigger_error(__('Connected to S3 Bucket:','backwpup').' '.$_SESSION['JOB']['awsBucket'],E_USER_NOTICE);
			//Transfer Backup to S3
			if ($_SESSION['JOB']['awsrrs']) //set reduced redundancy or not
				$storage=AmazonS3::STORAGE_REDUCED;
			else 
				$storage=AmazonS3::STORAGE_STANDARD;
			//set surl Prozess bar
			$curlops=array();
			if (function_exists('curl_progresscallback') and is_numeric(CURLOPT_PROGRESSFUNCTION))
				$curlops=array(CURLOPT_NOPROGRESS=>false,CURLOPT_PROGRESSFUNCTION=>'curl_progresscallback',CURLOPT_BUFFERSIZE=>256);
			else 
				@set_time_limit(300);
			trigger_error(__('Upload to Amazon S3 now started ... ','backwpup'),E_USER_NOTICE);	
			if ($s3->create_mpu_object($_SESSION['JOB']['awsBucket'], $_SESSION['JOB']['awsdir'].$_SESSION['STATIC']['backupfile'], array('fileUpload' => $_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile'],'acl' => AmazonS3::ACL_PRIVATE,'storage' => $storage,'partSize'=>26214400,'curlopts'=>$curlops)))  {//transfere file to S3
				$_SESSION['WORKING']['STEPTODO']=1+filesize($_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile']);
				trigger_error(__('Backup File transferred to S3://','backwpup').$_SESSION['JOB']['awsBucket'].'/'.$_SESSION['JOB']['awsdir'].$_SESSION['STATIC']['backupfile'],E_USER_NOTICE);
				$_SESSION['JOB']['lastbackupdownloadurl']='admin.php?page=backwpupbackups&action=downloads3&file='.$_SESSION['JOB']['awsdir'].$_SESSION['STATIC']['backupfile'].'&jobid='.$_SESSION['JOB']['jobid'];
			} else {
				trigger_error(__('Can not transfer backup to S3.','backwpup'),E_USER_ERROR);
			}
			
			if ($_SESSION['JOB']['awsmaxbackups']>0) { //Delete old backups
				$backupfilelist=array();
				if (($contents = $s3->list_objects($_SESSION['JOB']['awsBucket'],array('prefix'=>$_SESSION['JOB']['awsdir']))) !== false) {
					foreach ($contents->body->Contents as $object) {
						$file=basename($object->Key);
						if ($_SESSION['JOB']['fileprefix'] == substr($file,0,strlen($_SESSION['JOB']['fileprefix'])) and $_SESSION['JOB']['fileformart'] == substr($file,-strlen($_SESSION['JOB']['fileformart'])))
							$backupfilelist[]=$file;
					}
				}
				if (sizeof($backupfilelist)>0) {
					rsort($backupfilelist);
					$numdeltefiles=0;
					for ($i=$_SESSION['JOB']['awsmaxbackups'];$i<sizeof($backupfilelist);$i++) {
						if ($s3->delete_object($_SESSION['JOB']['awsBucket'], $_SESSION['JOB']['awsdir'].$backupfilelist[$i])) //delte files on S3
							$numdeltefiles++;
						else
							trigger_error(__('Can not delete file on S3://','backwpup').$_SESSION['JOB']['awsBucket'].'/'.$_SESSION['JOB']['awsdir'].$backupfilelist[$i],E_USER_ERROR);
					}
					if ($numdeltefiles>0)
						trigger_error($numdeltefiles.' '.__('files deleted on S3 Bucket!','backwpup'),E_USER_NOTICE);
				}
			}					
		} else {
			trigger_error(__('S3 Bucket not exists:','backwpup').' '.$_SESSION['JOB']['awsBucket'],E_USER_ERROR);
		}
	} catch (Exception $e) {
		trigger_error(__('Amazon S3 API:','backwpup').' '.$e->getMessage(),E_USER_ERROR);
		return;
	}
	
	$_SESSION['WORKING']['STEPDONE']++;
	$_SESSION['WORKING']['STEPSDONE'][]='DEST_S3'; //set done
}
?>