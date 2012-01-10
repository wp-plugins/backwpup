<?PHP
function backwpup_job_dest_folder() {
	global $backwpupjobrun;
	$backwpupjobrun['STEPTODO']=1;
	$backwpupjobrun['STEPDONE']=0;
	backwpup_update_option('job_'.$backwpupjobrun['STATIC']['JOB']['jobid'],'lastbackupdownloadurl',backwpup_admin_url('admin.php').'?page=backwpupbackups&action=download&file='.$backwpupjobrun['STATIC']['JOB']['backupdir'].$backwpupjobrun['STATIC']['backupfile']);
	//Delete old Backupfiles
	$backupfilelist=array();
	if ($backwpupjobrun['STATIC']['JOB']['maxbackups']>0) {
		if ( $dir = @opendir($backwpupjobrun['STATIC']['JOB']['backupdir']) ) { //make file list
			while (($file = readdir($dir)) !== false ) {
				if ($backwpupjobrun['STATIC']['JOB']['fileprefix'] == substr($file,0,strlen($backwpupjobrun['STATIC']['JOB']['fileprefix'])))
					$backupfilelist[filemtime($backwpupjobrun['STATIC']['JOB']['backupdir'].$file)]=$file;
			}
			@closedir($dir);
		}
		if (count($backupfilelist)>$backwpupjobrun['STATIC']['JOB']['maxbackups']) {
			$numdeltefiles=0;
			while ($file=array_shift($backupfilelist)) {
				if (count($backupfilelist)<$backwpupjobrun['STATIC']['JOB']['maxbackups'])
					break;
				unlink($backwpupjobrun['STATIC']['JOB']['backupdir'].$file);
				$numdeltefiles++;
			}
			if ($numdeltefiles>0)
				trigger_error(sprintf(_n('One backup file deleted','%d backup files deleted',$numdeltefiles,'backwpup'),$numdeltefiles),E_USER_NOTICE);
		}
	}
	$backwpupjobrun['STEPDONE']++;
	$backwpupjobrun['STEPSDONE'][]='DEST_FOLDER'; //set done
}

?>