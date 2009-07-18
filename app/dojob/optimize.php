<?PHP
//Optimize SQL Table
BackWPupFunctions::joblog($logtime,__('Run Database optimize...','backwpup'));
$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');

if (is_array($jobs[$jobid]['dbexclude'])) {
	foreach($tables as $tablekey => $tablevalue) {
		if (in_array($tablevalue,$jobs[$jobid]['dbexclude']))
			unset($tables[$tablekey]);
	}
}

if (sizeof($tables)>0) {
	foreach ($tables as $table) {
		if (!in_array($table,(array)$jobs[$jobid]['dbexclude'])) {
			$optimize=$wpdb->get_row('OPTIMIZE TABLE `'.$table.'`', ARRAY_A);
			BackWPupFunctions::joblog($logtime,__(strtoupper($optimize['Msg_type']).':','backwpup').' '.sprintf(__('Result of table optimize for %1$s is: %2$s','backwpup'), $table, $optimize['Msg_text']));
			if ($sqlerr=mysql_error($wpdb->dbh)) 
				BackWPupFunctions::joblog($logtime,__('ERROR:','backwpup').' '.sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), $sqlerr, $sqlerr->last_query));
		}
	}
	$wpdb->flush();
	BackWPupFunctions::joblog($logtime,__('Database optimize done!','backwpup'));
} else {
	BackWPupFunctions::joblog($logtime,__('ERROR:','backwpup').' '.__('No Tables to optimize','backwpup'));
}
?>