<?PHP 
BackWPupFunctions::joblog($logtime,__('Run Database Backup...','backwpup'));

//Tables to backup		
$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');	

if (is_array($jobs[$jobid]['dbexclude'])) {
	foreach($tables as $tablekey => $tablevalue) {
		if (in_array($tablevalue,$jobs[$jobid]['dbexclude']))
			unset($tables[$tablekey]);
	}
}

if (sizeof($tables)>0) {
	BackWPupFunctions::joblog($logtime,__('Tables to Backup: ','backwpup').print_r($tables,true));
	
	require_once('MySQLDBExport.class.php');
	$export = new MySQLDBExport(DB_HOST, DB_USER, DB_PASSWORD);
	$export->set_db(DB_NAME);  

	$file = @fopen(BackWPupFunctions::get_temp_dir().'backwpup/'.DB_NAME.'.sql', 'w');
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
	BackWPupFunctions::joblog($logtime,__('Create Zip file from dump...','backwpup'));
	require_once(ABSPATH . 'wp-admin/includes/class-pclzip.php');
	$zipbackupfile = new PclZip($backupfile);
	if (0==$zipbackupfile -> create(BackWPupFunctions::get_temp_dir().'backwpup/'.DB_NAME.'.sql',PCLZIP_OPT_REMOVE_PATH,BackWPupFunctions::get_temp_dir().'backwpup')) {
		BackWPupFunctions::joblog($logtime,__('ERROR: Database Zip file create:','backwpup').' '.$zipbackupfile->errorInfo(true));
		$joberror=true;
	}
	BackWPupFunctions::joblog($logtime,__('Zip file created...','backwpup'));
}
//clean vars
unset($tables);
unset($zipbackupfile);
?>