<?PHP 
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');

if (!empty($jobs[$jobid]['awsAccessKey']) and !empty($jobs[$jobid]['awsSecretKey']) and !empty($jobs[$jobid]['awsBucket'])) {
	if (!class_exists('S3')) require_once 'libs/S3.php';
	
	$s3 = new S3($jobs[$jobid]['awsAccessKey'], $jobs[$jobid]['awsSecretKey'], $jobs[$jobid]['awsSSL']);
	
	if (in_array($jobs[$jobid]['awsBucket'],$s3->listBuckets())) {
		backwpup_joblog($logtime,__('Connected to S3 Bucket:','backwpup').' '.$jobs[$jobid]['awsBucket']);
		//Transfer Backup to S3
		if ($s3->putObjectFile($backupfile, $jobs[$jobid]['awsBucket'], str_replace('//','/',trailingslashit($jobs[$jobid]['awsdir']).basename($backupfile)), S3::ACL_PRIVATE))  //transfere file to S3
			backwpup_joblog($logtime,__('Backup File transferred to S3://','backwpup').$jobs[$jobid]['awsBucket'].'/'.str_replace('//','/',trailingslashit($jobs[$jobid]['awsdir']).basename($backupfile)));
		else
			backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('Can not transfer backup to S3.','backwpup'));
		
		unset($backupfilelist);			
			if ($jobs[$jobid]['awsmaxbackups']>0) { //Delete old backups
				if (($contents = $s3->getBucket($jobs[$jobid]['awsBucket'])) !== false) {
					foreach ($contents as $object) {
						if (trailingslashit($jobs[$jobid]['awsdir'])==substr($object['name'],0,strlen(trailingslashit($jobs[$jobid]['awsdir'])))) {
							$files=basename($object['name']);
							if ('backwpup_'.$jobid.'_' == substr(basename($files),0,strlen('backwpup_'.$jobid.'_')) and ".zip" == substr(basename($files),-4))
								$backupfilelist[]=basename($object['name']);
						}
					}
				}
				if (sizeof($backupfilelist)>0) {
					rsort($backupfilelist);
					$numdeltefiles=0;
					for ($i=$jobs[$jobid]['awsmaxbackups'];$i<sizeof($backupfilelist);$i++) {
						if ($s3->deleteObject($jobs[$jobid]['awsBucket'], str_replace('//','/',trailingslashit($jobs[$jobid]['awsdir']).$backupfilelist[$i]))) //delte files on S3
						$numdeltefiles++;
						else 
							backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('Can not delete file on S3//:','backwpup').$jobs[$jobid]['awsBucket'].'/'.str_replace('//','/',trailingslashit($jobs[$jobid]['awsdir']).$backupfilelist[$i]));
					}
					if ($numdeltefiles>0)
						backwpup_joblog($logtime,$numdeltefiles.' '.__('files deleted on S3 Bucket!','backwpup'));
				}
			}
		
	} else {
		backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('S3 Bucket not exists:','backwpup').' '.$jobs[$jobid]['awsBucket']);
	}
}
?>
