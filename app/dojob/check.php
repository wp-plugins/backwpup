<?PHP
//Optimize SQL Table
backwpup_joblog($logtime,__('Run Database check...','backwpup'));
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
			$check=$wpdb->get_row('CHECK TABLE `'.$table.'` MEDIUM', ARRAY_A);
			backwpup_joblog($logtime,__(strtoupper($check['Msg_type']).':','backwpup').' '.sprintf(__('Result of table check for %1$s is: %2$s','backwpup'), $table, $check['Msg_text']));
			if ($sqlerr=mysql_error($wpdb->dbh)) 
				backwpup_joblog($logtime,__('ERROR:','backwpup').' '.sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), $sqlerr, $sqlerr->last_query));
			if ($check['Msg_type']=='error') {
				$repair=$wpdb->get_row('REPAIR TABLE `'.$table.'`', ARRAY_A);
				backwpup_joblog($logtime,__(strtoupper($repair['Msg_type']).':','backwpup').' '.sprintf(__('Result of table repair for %1$s is: %2$s ','backwpup'), $table, $repair['Msg_text']));
				if ($sqlerr=mysql_error($wpdb->dbh)) 
					backwpup_joblog($logtime,__('ERROR:','backwpup').' '.sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), $sqlerr, $sqlerr->last_query));
			}
		}
	}
	$wpdb->flush();
	backwpup_joblog($logtime,__('Database check done!','backwpup'));
} else {
	backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('No Tables to check','backwpup'));
}
?>