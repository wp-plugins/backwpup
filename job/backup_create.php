<?PHP
// don't load directly
if ( !defined('BACKWPUP_JOBRUN_FILE') )
	die('-1');
	
if (isset($backwpup_dojob->filelist[0][79001])) { // Make backup file
	if ($backwpup_dojob->backupfileformat==".zip")
		$backwpup_dojob->zip_files();
	elseif ($backwpup_dojob->backupfileformat==".tar.gz" or $backwpup_dojob->backupfileformat==".tar.bz2" or $backwpup_dojob->backupfileformat==".tar")
		$backwpup_dojob->tar_pack_files();
}
?>