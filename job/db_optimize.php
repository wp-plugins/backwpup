<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function db_optimize() {
	trigger_error($_SESSION['WORKING']['DB_OPTIMIZE']['STEP_TRY'].'. '.__('Try to run database optimize...','backwpup'),E_USER_NOTICE);
	if (!isset($_SESSION['WORKING']['DB_OPTIMIZE']['DONETABLE']) or !is_array($_SESSION['WORKING']['DB_OPTIMIZE']['DONETABLE']))
		$_SESSION['WORKING']['DB_OPTIMIZE']['DONETABLE']=array();
	$_SESSION['WORKING']['STEPTODO']=sizeof($_SESSION['JOB']['dbtables']);
	if (sizeof($_SESSION['JOB']['dbtables'])>0) {
		maintenance_mode(true);
		foreach ($_SESSION['JOB']['dbtables'] as $table) {
			if (in_array($table, $_SESSION['WORKING']['DB_OPTIMIZE']['DONETABLE']))
				continue;
			$result=mysql_query('OPTIMIZE TABLE `'.$table.'`');
			if (!$result) {
				trigger_error(sprintf(__('BackWPup database error %1$s for query %2$s','backwpup'), mysql_error(), "OPTIMIZE TABLE `".$table."`"),E_USER_ERROR);
				continue;
			}
			$optimize=mysql_fetch_assoc($result);
			if ($optimize['Msg_type']=='error')
				trigger_error(sprintf(__('Result of table optimize for %1$s is: %2$s','backwpup'), $table, $optimize['Msg_text']),E_USER_ERROR);
			elseif ($optimize['Msg_type']=='warning')
				trigger_error(sprintf(__('Result of table optimize for %1$s is: %2$s','backwpup'), $table, $optimize['Msg_text']),E_USER_WARNING);
			else
				trigger_error(sprintf(__('Result of table optimize for %1$s is: %2$s','backwpup'), $table, $optimize['Msg_text']),E_USER_NOTICE);
			$_SESSION['WORKING']['DB_OPTIMIZE']['DONETABLE'][]=$table;
			$_SESSION['WORKING']['STEPDONE']=sizeof($_SESSION['WORKING']['DB_OPTIMIZE']['DONETABLE']);
		}
		trigger_error(__('Database optimize done!','backwpup'),E_USER_NOTICE);
		maintenance_mode(false);
	} else {
		trigger_error(__('No Tables to optimize','backwpup'),E_USER_WARNING);
	}
	$_SESSION['WORKING']['STEPSDONE'][]='DB_OPTIMIZE'; //set done
}

?>