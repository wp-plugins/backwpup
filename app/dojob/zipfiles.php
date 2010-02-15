<?PHP
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');
	
if (!is_array($backwpup_fielstobackup[0])) {
	backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('No files to Backup','backwpup'));
	unset($backwpup_fielstobackup); //clean vars
} else {
	backwpup_joblog($logtime,__('Size off all files:','backwpup').' '.backwpup_formatBytes($backwpup_allfilezise));
}

//Create Zip File
if (is_array($backwpup_fielstobackup[0])) {
	backwpup_needfreememory(10485760); //10MB free memory for zip
	backwpup_joblog($logtime,__('Create Backup Zip file...','backwpup'));
	$zipbackupfile = new PclZip($backupfile);
	if (0==$zipbackupfile -> create($backwpup_fielstobackup,PCLZIP_OPT_ADD_TEMP_FILE_ON)) {
		backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('Zip file create:','backwpup').' '.$zipbackupfile->errorInfo(true));
	}
	unset($backwpup_fielstobackup);
	unset($zipbackupfile);
	backwpup_joblog($logtime,__('Backup Zip file create done!','backwpup'));
}
	
?>