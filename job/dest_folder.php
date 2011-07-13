<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function dest_folder() {
	if (empty($_SESSION['JOB']['backupdir']) or $_SESSION['JOB']['backupdir']=='/' or $_SESSION['JOB']['backupdir']==$_SESSION['STATIC']['TEMPDIR']) {
		$_SESSION['WORKING']['STEPSDONE'][]='DEST_FOLDER'; //set done	
		return;
	}
	$_SESSION['WORKING']['STEPTODO']=1;
	$_SESSION['WORKING']['STEPDONE']=0;
	$_SESSION['JOB']['lastbackupdownloadurl']=$_SESSION['WP']['ADMINURL'].'?page=backwpupbackups&action=download&file='.$_SESSION['JOB']['backupdir'].$_SESSION['STATIC']['backupfile'];
	//Delete old Backupfiles
	$backupfilelist=array();
	if ($_SESSION['JOB']['maxbackups']>0) {
		if ( $dir = @opendir($_SESSION['JOB']['backupdir']) ) { //make file list
			while (($file = readdir($dir)) !== false ) {
				if ($_SESSION['JOB']['fileprefix'] == substr($file,0,strlen($_SESSION['JOB']['fileprefix'])) and $_SESSION['JOB']['fileformart'] == substr($file,-strlen($_SESSION['JOB']['fileformart'])))
					$backupfilelist[]=$file;
			}
			@closedir( $dir );
		}
		if (sizeof($backupfilelist)>0) {
			rsort($backupfilelist);
			$numdeltefiles=0;
			for ($i=$_SESSION['JOB']['maxbackups'];$i<sizeof($backupfilelist);$i++) {
				unlink($_SESSION['JOB']['backupdir'].$backupfilelist[$i]);
				$numdeltefiles++;
			}
			if ($numdeltefiles>0)
				trigger_error($numdeltefiles.' '.__('old backup files deleted!','backwpup'),E_USER_NOTICE);
		}
	}
	$_SESSION['WORKING']['STEPDONE']++;
	$_SESSION['WORKING']['STEPSDONE'][]='DEST_FOLDER'; //set done
}

?>