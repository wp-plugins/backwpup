<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function dest_sugarsync() {
	if (empty($_SESSION['JOB']['sugaruser']) or empty($_SESSION['JOB']['sugarpass']) or empty($_SESSION['JOB']['sugarroot'])) {
		$_SESSION['WORKING']['STEPSDONE'][]='DEST_SUGARSYNC'; //set done	
		return;
	}
	trigger_error($_SESSION['WORKING']['DEST_SUGARSYNC']['STEP_TRY'].'. '.__('Try to sending backup file to sugarsync...','backwpup'),E_USER_NOTICE);
	$_SESSION['WORKING']['STEPTODO']=1;
	$_SESSION['WORKING']['STEPDONE']=0;


	$_SESSION['WORKING']['STEPDONE']=1;
	$_SESSION['WORKING']['STEPSDONE'][]='DEST_SUGARSYNC'; //set done
}
?>