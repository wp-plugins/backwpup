<?PHP 
BackWPupFunctions::joblog($logtime,__('Run Database Backup...','backwpup'));

//Tables to backup		
$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');	
$jobs[$jobid]['dbexclude'][]=$wpdb->backwpup_logs; //Exclude log table
if (is_array($jobs[$jobid]['dbexclude'])) {
	foreach($tables as $tablekey => $tablevalue) {
		if (in_array($tablevalue,$jobs[$jobid]['dbexclude']))
			unset($tables[$tablekey]);
	}
}

if (sizeof($tables)>0) {
	foreach($tables as $table) {
		BackWPupFunctions::joblog($logtime,__('Database table to Backup: ','backwpup').' '.$table);
	}
	
	require_once('MySQLDBExport.class.php');
	$export = new MySQLDBExport(DB_HOST, DB_USER, DB_PASSWORD);
	$export->set_db(DB_NAME);  

	$file = @fopen(BackWPupFunctions::get_temp_dir().'backwpup/'.DB_NAME.'.sql', 'w');
	fwrite($file, "-- ------------------------------------------\n");
	fwrite($file, "-- Export with BackWPup ver.: ".BACKWPUP_VERSION."\n");
	fwrite($file, "-- Plugin by Daniel Huesken for WordPress\n");
	fwrite($file, "-- http://danielhuesken.de/portfolio/backwpup/\n");
	fwrite($file, "-- Blog Name: ".get_option('blogname')."\n");
	if (defined('WP_SITEURL')) 
		fwrite($file, "-- Blog URL: ".trailingslashit(WP_SITEURL)."\n");
	else 
		fwrite($file, "-- Blog URL: ".trailingslashit(get_option('siteurl'))."\n");
	fwrite($file, "-- Blog ABSPATH: ".trailingslashit(ABSPATH)."\n");
	fwrite($file, $export->make_dump($tables));
	fclose($file);


	if ($error=$export->get_error()) {
		BackWPupFunctions::joblog($logtime,__('ERROR:','backwpup').' '.$error);
	}
} else {
	BackWPupFunctions::joblog($logtime,__('ERROR: No Tables to Backup','backwpup'));
}


BackWPupFunctions::joblog($logtime,__('Database backup done!','backwpup'));

if ($jobs[$jobid]['type']=='DB' and is_file(BackWPupFunctions::get_temp_dir().'backwpup/'.DB_NAME.'.sql')) {
	BackWPupFunctions::joblog($logtime,__('Database file size:','backwpup').' '.BackWPupFunctions::formatBytes(filesize(BackWPupFunctions::get_temp_dir().'backwpup/'.DB_NAME.'.sql')));
	BackWPupFunctions::joblog($logtime,__('Create Zip file from dump...','backwpup'));
	$zipbackupfile = new PclZip($backupfile);
	if (0==$zipbackupfile -> create(BackWPupFunctions::get_temp_dir().'backwpup/'.DB_NAME.'.sql',PCLZIP_OPT_REMOVE_ALL_PATH,PCLZIP_OPT_ADD_TEMP_FILE_ON)) {
		BackWPupFunctions::joblog($logtime,__('ERROR: Database Zip file create:','backwpup').' '.$zipbackupfile->errorInfo(true));
		$joberror=true;
	} 
	BackWPupFunctions::joblog($logtime,__('Zip file created...','backwpup'));
}
//clean vars
unset($tables);
unset($zipbackupfile);
?>