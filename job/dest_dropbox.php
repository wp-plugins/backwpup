<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function dest_dropbox() {
	if (empty($_SESSION['JOB']['dropetoken']) or empty($_SESSION['JOB']['dropesecret'])) {
		$_SESSION['WORKING']['STEPSDONE'][]='DEST_DROPBOX'; //set done	
		return;
	}
	trigger_error($_SESSION['WORKING']['DEST_DROPBOX']['STEP_TRY'].'. '.__('Try to sending backup file to DropBox...','backwpup'),E_USER_NOTICE);
	$_SESSION['WORKING']['STEPTODO']=1;
	$_SESSION['WORKING']['STEPDONE']=0;


	$_SESSION['WORKING']['STEPDONE']=1;
	$_SESSION['WORKING']['STEPSDONE'][]='DEST_DROPBOX'; //set done
}
?>