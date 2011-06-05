<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function dest_msazure() {
	if (empty($_SESSION['JOB']['msazureHost']) or empty($_SESSION['JOB']['msazureAccName']) or empty($_SESSION['JOB']['msazureKey']) or empty($_SESSION['JOB']['msazureContainer'])) {
		$_SESSION['WORKING']['STEPSDONE'][]='DEST_MSAZURE'; //set done	
		return;
	}
	trigger_error($_SESSION['WORKING']['DEST_MSAZURE']['STEP_TRY'].'. '.__('Try to sending backup file to a Microsoft Azure (Blob)...','backwpup'),E_USER_NOTICE);
	$_SESSION['WORKING']['STEPTODO']=1;
	$_SESSION['WORKING']['STEPDONE']=0;


	$_SESSION['WORKING']['STEPDONE']=1;
	$_SESSION['WORKING']['STEPSDONE'][]='DEST_MSAZURE'; //set done
}
?>