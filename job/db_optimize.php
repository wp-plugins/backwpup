<?PHP
function backwpup_job_db_optimize() {
	global $backwpupjobrun,$wpdb;
	trigger_error(sprintf(__('%d. Try for database optimize...','backwpup'),$backwpupjobrun['WORKING']['DB_OPTIMIZE']['STEP_TRY']),E_USER_NOTICE);
	if (!isset($backwpupjobrun['WORKING']['DB_OPTIMIZE']['DONETABLE']) or !is_array($backwpupjobrun['WORKING']['DB_OPTIMIZE']['DONETABLE']))
		$backwpupjobrun['WORKING']['DB_OPTIMIZE']['DONETABLE']=array();
	
	//to backup
	$tablestobackup=array();
	$tables = $wpdb->get_col("SHOW TABLES FROM `".DB_NAME."`"); //get table status
	if (mysql_error())
		trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), "SHOW TABLE STATUS FROM `".DB_NAME."`;"),E_USER_ERROR);
	foreach ($tables as $table) {
		if (!in_array($table,$backwpupjobrun['STATIC']['JOB']['dbexclude']))
			$tablestobackup[]=$table;
	}	
	//Set num of todos
	$backwpupjobrun['WORKING']['STEPTODO']=count($tablestobackup);
	
	if (count($tablestobackup)>0) {
		backwpup_job_maintenance_mode(true);
		foreach ($tablestobackup as $table) {
			if (in_array($table, $backwpupjobrun['WORKING']['DB_OPTIMIZE']['DONETABLE']))
				continue;
			$optimize = $wpdb->get_row("OPTIMIZE TABLE `".$table."`");
			if (mysql_error()) {
				trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), "OPTIMIZE TABLE `".$table."`"),E_USER_ERROR);
				continue;
			}
			$backwpupjobrun['WORKING']['DB_OPTIMIZE']['DONETABLE'][]=$table;
			$backwpupjobrun['WORKING']['STEPDONE']=count($backwpupjobrun['WORKING']['DB_OPTIMIZE']['DONETABLE']);
			if (strtolower($optimize->Msg_type)=='error')
				trigger_error(sprintf(__('Result of table optimize for %1$s is: %2$s','backwpup'), $table, $optimize->Msg_text),E_USER_ERROR);
			elseif (strtolower($optimize->Msg_type)=='warning')
				trigger_error(sprintf(__('Result of table optimize for %1$s is: %2$s','backwpup'), $table, $optimize->Msg_text),E_USER_WARNING);
			else
				trigger_error(sprintf(__('Result of table optimize for %1$s is: %2$s','backwpup'), $table, $optimize->Msg_text),E_USER_NOTICE);
		}
		trigger_error(__('Database optimize done!','backwpup'),E_USER_NOTICE);
		backwpup_job_maintenance_mode(false);
	} else {
		trigger_error(__('No tables to optimize','backwpup'),E_USER_WARNING);
	}
	$backwpupjobrun['WORKING']['STEPSDONE'][]='DB_OPTIMIZE'; //set done
}

?>