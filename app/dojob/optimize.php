<?PHP
//Optimize SQL Table
BackWPupFunctions::joblog($logfile,__('Run Database optimize...','backwpup'));
$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');

if (is_array($jobs[$jobid]['dbexclude'])) {
	foreach($tables as $tablekey => $tablevalue) {
		if (in_array($tablevalue,$jobs[$jobid]['dbexclude']))
			unset($tables[$tablekey]);
	}
}

if (sizeof($tables)>0) {
	BackWPupFunctions::joblog($logfile,__('Tables to optimize: ','backwpup').print_r($tables,true));

	foreach ($tables as $table) {
		if (!in_array($table,(array)$jobs[$jobid]['dbexclude'])) {
			$wpdb->query('OPTIMIZE TABLE `'.$table.'`');
			if ($sqlerr=mysql_error($wpdb->dbh)) {
				BackWPupFunctions::joblog($logfile,sprintf(__('ERROR: BackWPup database error %1$s for query %2$s','backwpup'), $sqlerr, $sqlerr->last_query));
				$joberror=true;
			}
		}
	}
	$wpdb->flush();
	BackWPupFunctions::joblog($logfile,__('Database optimize done!','backwpup'));
} else {
	BackWPupFunctions::joblog($logfile,__('ERROR: No Tables to optimize','backwpup'));
	$joberror=true;
}


	BackWPupFunctions::joblog($logfile,__('Delete old Log files...','backwpup'));
	$logs=get_option('backwpup_log');
	if (is_array($logs)) {
		unset($logkeys);
		foreach ($logs as $timestamp => $logdata) {
			if ($logdata['jobid']==$jobid)
				$logkeys[]=$timestamp;
		}
		if (is_array($logkeys)) {
			rsort($logkeys,SORT_NUMERIC);
			$counter=0;$countdelbackups=0;$countdellogs=0;
			for ($i=0;$i<sizeof($logkeys);$i++) {
				if (!empty($logs[$logkeys[$i]]['backupfile']))
					$counter++;
				if ($counter>=15) {
					if (is_file($logs[$logkeys[$i]]['backupfile'])) {
						unlink($logs[$logkeys[$i]]['backupfile']);
						$countdelbackups++;
					}
					if (is_file($logs[$logkeys[$i]]['logfile'])) {
						unlink($logs[$logkeys[$i]]['logfile']);
						$countdellogs++;
					}
					unset($logs[$logkeys[$i]]);
				}
			}
		}
	}
	update_option('backwpup_log',$logs);
	BackWPupFunctions::joblog($logfile,$countdellogs.' '.__('Old Log files deleted!!!','backwpup'));
	//clean vars
	unset($logkeys);
	unset($logs);



?>