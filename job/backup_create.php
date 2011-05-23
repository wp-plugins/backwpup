<?PHP
if (!defined('BACKWPUP_JOBRUN_FILE')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}
	
if (isset($backwpup_dojob->filelist[0][79001])) { // Make backup file
	if ($backwpup_dojob->backupfileformat==".zip")
		$backwpup_dojob->zip_files();
	elseif ($backwpup_dojob->backupfileformat==".tar.gz" or $backwpup_dojob->backupfileformat==".tar.bz2" or $backwpup_dojob->backupfileformat==".tar")
		$backwpup_dojob->tar_pack_files();
}
?>