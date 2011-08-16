<?PHP
function dest_folder() {
	global $WORKING,$STATIC;
	$WORKING['STEPTODO']=1;
	$WORKING['STEPDONE']=0;
	$STATIC['JOB']['lastbackupdownloadurl']=$STATIC['WP']['ADMINURL'].'?page=backwpupbackups&action=download&file='.$STATIC['JOB']['backupdir'].$STATIC['backupfile'];
	//Delete old Backupfiles
	$backupfilelist=array();
	if ($STATIC['JOB']['maxbackups']>0) {
		if ( $dir = @opendir($STATIC['JOB']['backupdir']) ) { //make file list
			while (($file = readdir($dir)) !== false ) {
				if ($STATIC['JOB']['fileprefix'] == substr($file,0,strlen($STATIC['JOB']['fileprefix'])))
					$backupfilelist[filemtime($STATIC['JOB']['backupdir'].$file)]=$file;
			}
			@closedir($dir);
		}
		if (count($backupfilelist)>$STATIC['JOB']['maxbackups']) {
			$numdeltefiles=0;
			while ($file=array_shift($backupfilelist)) {
				if (count($backupfilelist)<$STATIC['JOB']['maxbackups'])
					break;
				unlink($STATIC['JOB']['backupdir'].$file);
				$numdeltefiles++;
			}
			if ($numdeltefiles>0)
				trigger_error(sprintf(_n('One backup file deleted','%d backup files deleted',$numdeltefiles,'backwpup'),$numdeltefiles),E_USER_NOTICE);
		}
	}
	$WORKING['STEPDONE']++;
	$WORKING['STEPSDONE'][]='DEST_FOLDER'; //set done
}

?>