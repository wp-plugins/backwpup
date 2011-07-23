<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function db_check() {
	global $WORKING,$STATIC;
	trigger_error(sprintf(__('%d. try for database check...','backwpup'),$WORKING['DB_CHECK']['STEP_TRY']),E_USER_NOTICE);
	if (!isset($WORKING['DB_CHECK']['DONETABLE']) or !is_array($WORKING['DB_CHECK']['DONETABLE']))
		$WORKING['DB_CHECK']['DONETABLE']=array();
	//Set num of todos
	$WORKING['STEPTODO']=sizeof($STATIC['JOB']['dbtables']);
	//check tables
	if (sizeof($STATIC['JOB']['dbtables'])>0) {
		maintenance_mode(true);
		foreach ($STATIC['JOB']['dbtables'] as $table) {
			if (in_array($table, $WORKING['DB_CHECK']['DONETABLE']))
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
			$WORKING['DB_CHECK']['DONETABLE'][]=$table;
			$WORKING['STEPDONE']=sizeof($WORKING['DB_CHECK']['DONETABLE']);
		}
		maintenance_mode(false);
		trigger_error(__('Database check done!','backwpup'),E_USER_NOTICE);
	} else {
		trigger_error(__('No tables to check','backwpup'),E_USER_WARNING);
	}
	$WORKING['STEPSDONE'][]='DB_CHECK'; //set done
}
?>