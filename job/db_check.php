<?PHP
function backwpup_job_db_check() {
	global $backwpupjobrun,$wpdb;
	trigger_error(sprintf(__('%d. try for database check...','backwpup'),$backwpupjobrun['WORKING']['DB_CHECK']['STEP_TRY']),E_USER_NOTICE);
	if (!isset($backwpupjobrun['WORKING']['DB_CHECK']['DONETABLE']) or !is_array($backwpupjobrun['WORKING']['DB_CHECK']['DONETABLE']))
		$backwpupjobrun['WORKING']['DB_CHECK']['DONETABLE']=array();
	
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
	$backwpupjobrun['WORKING']['STEPTODO']=sizeof($tablestobackup);
	
	//check tables
	if (count($tablestobackup)>0) {
		backwpup_job_maintenance_mode(true);
		foreach ($tablestobackup as $table) {
			if (in_array($table, $backwpupjobrun['WORKING']['DB_CHECK']['DONETABLE']))
				continue;
			$check = $wpdb->get_row("CHECK TABLE `".$table."` MEDIUM");
			if (mysql_error()) {
				trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), "CHECK TABLE `".$table."` MEDIUM"),E_USER_ERROR);
				continue;
			}
			if ($check->Msg_text=='OK')
				trigger_error(sprintf(__('Result of table check for %1$s is: %2$s','backwpup'), $table, $check->Msg_text),E_USER_NOTICE);
			elseif (strtolower($check->Msg_type)=='warning')
				trigger_error(sprintf(__('Result of table check for %1$s is: %2$s','backwpup'), $table, $check->Msg_text),E_USER_WARNING);
			else
				trigger_error(sprintf(__('Result of table check for %1$s is: %2$s','backwpup'), $table, $check->Msg_text),E_USER_ERROR);

			//Try to Repair tabele
			if ($check->Msg_text!='OK') {
				$repair = $wpdb->get_row('REPAIR TABLE `'.$table.'`');
				if (mysql_error()) {
					trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), "REPAIR TABLE `'.$table.'`"),E_USER_ERROR);
					continue;
				}
				if ($repair->Msg_type=='OK')
					trigger_error(sprintf(__('Result of table repair for %1$s is: %2$s','backwpup'), $table, $repair->Msg_text),E_USER_NOTICE);
				elseif (strtolower($repair->Msg_type)=='warning')
					trigger_error(sprintf(__('Result of table repair for %1$s is: %2$s','backwpup'), $table, $repair->Msg_text),E_USER_WARNING);
				else
					trigger_error(sprintf(__('Result of table repair for %1$s is: %2$s','backwpup'), $table, $repair->Msg_text),E_USER_ERROR);
			}
			$backwpupjobrun['WORKING']['DB_CHECK']['DONETABLE'][]=$table;
			$backwpupjobrun['WORKING']['STEPDONE']=sizeof($backwpupjobrun['WORKING']['DB_CHECK']['DONETABLE']);
		}
		backwpup_job_maintenance_mode(false);
		trigger_error(__('Database check done!','backwpup'),E_USER_NOTICE);
	} else {
		trigger_error(__('No tables to check','backwpup'),E_USER_WARNING);
	}
	$backwpupjobrun['WORKING']['STEPSDONE'][]='DB_CHECK'; //set done
}
?>