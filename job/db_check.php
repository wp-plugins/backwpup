<?PHP
function backwpup_job_db_check() {
	global $backwpupjobrun;
	trigger_error(sprintf(__('%d. try for database check...','backwpup'),$backwpupjobrun['WORKING']['DB_CHECK']['STEP_TRY']),E_USER_NOTICE);
	if (!isset($backwpupjobrun['WORKING']['DB_CHECK']['DONETABLE']) or !is_array($backwpupjobrun['WORKING']['DB_CHECK']['DONETABLE']))
		$backwpupjobrun['WORKING']['DB_CHECK']['DONETABLE']=array();
	
	//to backup
	$tabelstobackup=array();
	$result=mysql_query("SHOW TABLES FROM `".DB_NAME."`"); //get table status
	if (!$result)
		trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), "SHOW TABLE STATUS FROM `".DB_NAME."`;"),E_USER_ERROR);
	while ($data = mysql_fetch_row($result)) {
		if (!in_array($data[0],$backwpupjobrun['STATIC']['JOB']['dbexclude']))
			$tabelstobackup[]=$data[0];
	}	
	//Set num of todos
	$backwpupjobrun['WORKING']['STEPTODO']=sizeof($tabelstobackup);
	
	//check tables
	if (count($tabelstobackup)>0) {
		backwpup_job_maintenance_mode(true);
		foreach ($tabelstobackup as $table) {
			if (in_array($table, $backwpupjobrun['WORKING']['DB_CHECK']['DONETABLE']))
				continue;
			$result=mysql_query('CHECK TABLE `'.$table.'` MEDIUM');
			if (!$result) {
				trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), "CHECK TABLE `".$table."` MEDIUM"),E_USER_ERROR);
				continue;
			}
			$check=mysql_fetch_assoc($result);
			if ($check['Msg_type']=='error')
				trigger_error(sprintf(__('Result of table check for %1$s is: %2$s','backwpup'), $table, $check['Msg_text']),E_USER_ERROR);
			elseif ($check['Msg_type']=='warning')
				trigger_error(sprintf(__('Result of table check for %1$s is: %2$s','backwpup'), $table, $check['Msg_text']),E_USER_WARNING);
			else
				trigger_error(sprintf(__('Result of table check for %1$s is: %2$s','backwpup'), $table, $check['Msg_text']),E_USER_NOTICE);

			//Try to Repair tabele
			if ($check['Msg_type']=='error' or $check['Msg_type']=='warning') {
				$result=mysql_query('REPAIR TABLE `'.$table.'`');
				if (!$result) {
					trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), "REPAIR TABLE `'.$table.'`"),E_USER_ERROR);
					continue;
				}
				$repair=mysql_fetch_assoc($result);
				if ($repair['Msg_type']=='error')
					trigger_error(sprintf(__('Result of table repair for %1$s is: %2$s','backwpup'), $table, $repair['Msg_text']),E_USER_ERROR);
				elseif ($repair['Msg_type']=='warning')
					trigger_error(sprintf(__('Result of table repair for %1$s is: %2$s','backwpup'), $table, $repair['Msg_text']),E_USER_WARNING);
				else
					trigger_error(sprintf(__('Result of table repair for %1$s is: %2$s','backwpup'), $table, $repair['Msg_text']),E_USER_NOTICE);
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