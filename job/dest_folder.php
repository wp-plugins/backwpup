<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function dest_folder() {
	global $WORKING,$STATIC;
	if (empty($STATIC['JOB']['backupdir']) or $STATIC['JOB']['backupdir']=='/' or $STATIC['JOB']['backupdir']==$STATIC['TEMPDIR']) {
		$WORKING['STEPSDONE'][]='DEST_FOLDER'; //set done	
		return;
	}
	$WORKING['STEPTODO']=1;
	$WORKING['STEPDONE']=0;
	$STATIC['JOB']['lastbackupdownloadurl']=$STATIC['WP']['ADMINURL'].'?page=backwpupbackups&action=download&file='.$STATIC['JOB']['backupdir'].$STATIC['backupfile'];
	//Delete old Backupfiles
	$backupfilelist=array();
	if ($STATIC['JOB']['maxbackups']>0) {
		if ( $dir = @opendir($STATIC['JOB']['backupdir']) ) { //make file list
			while (($file = readdir($dir)) !== false ) {
				if ($STATIC['JOB']['fileprefix'] == substr($file,0,strlen($STATIC['JOB']['fileprefix'])) and $STATIC['JOB']['fileformart'] == substr($file,-strlen($STATIC['JOB']['fileformart'])))
					$backupfilelist[]=$file;
			}
			@closedir( $dir );
		}
		if (sizeof($backupfilelist)>0) {
			rsort($backupfilelist);
			$numdeltefiles=0;
			for ($i=$STATIC['JOB']['maxbackups'];$i<sizeof($backupfilelist);$i++) {
				unlink($STATIC['JOB']['backupdir'].$backupfilelist[$i]);
				$numdeltefiles++;
			}
			if ($numdeltefiles>0)
				trigger_error($numdeltefiles.' '.__('old backup files deleted!','backwpup'),E_USER_NOTICE);
		}
	}
	$WORKING['STEPDONE']++;
	$WORKING['STEPSDONE'][]='DEST_FOLDER'; //set done
}

?>