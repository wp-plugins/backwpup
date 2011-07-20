<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function db_optimize() {
	global $WORKING,$STATIC;
	trigger_error(sprintf(__('%d. try for database optimize...','backwpup'),$WORKING['DB_OPTIMIZE']['STEP_TRY']),E_USER_NOTICE);
	if (!isset($WORKING['DB_OPTIMIZE']['DONETABLE']) or !is_array($WORKING['DB_OPTIMIZE']['DONETABLE']))
		$WORKING['DB_OPTIMIZE']['DONETABLE']=array();
	$WORKING['STEPTODO']=sizeof($STATIC['JOB']['dbtables']);
	if (sizeof($STATIC['JOB']['dbtables'])>0) {
		maintenance_mode(true);
		foreach ($STATIC['JOB']['dbtables'] as $table) {
			if (in_array($table, $WORKING['DB_OPTIMIZE']['DONETABLE']))
				continue;
			$result=mysql_query('OPTIMIZE TABLE `'.$table.'`');
			if (!$result) {
				trigger_error(sprintf(__('Database error %1$s for query %2$s','backwpup'), mysql_error(), "OPTIMIZE TABLE `".$table."`"),E_USER_ERROR);
				continue;
			}
			$optimize=mysql_fetch_assoc($result);
			$WORKING['DB_OPTIMIZE']['DONETABLE'][]=$table;
			$WORKING['STEPDONE']=sizeof($WORKING['DB_OPTIMIZE']['DONETABLE']);
			if ($optimize['Msg_type']=='error')
				trigger_error(sprintf(__('Result of table optimize for %1$s is: %2$s','backwpup'), $table, $optimize['Msg_text']),E_USER_ERROR);
			elseif ($optimize['Msg_type']=='warning')
				trigger_error(sprintf(__('Result of table optimize for %1$s is: %2$s','backwpup'), $table, $optimize['Msg_text']),E_USER_WARNING);
			else
				trigger_error(sprintf(__('Result of table optimize for %1$s is: %2$s','backwpup'), $table, $optimize['Msg_text']),E_USER_NOTICE);
		}
		trigger_error(__('Database optimize done!','backwpup'),E_USER_NOTICE);
		maintenance_mode(false);
	} else {
		trigger_error(__('No tables to optimize','backwpup'),E_USER_WARNING);
	}
	$WORKING['STEPSDONE'][]='DB_OPTIMIZE'; //set done
}

?>