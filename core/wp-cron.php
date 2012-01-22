<?php
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}
function backwpup_cron_run() {
	$backupdata=backwpup_get_workingdata();
	if ($backupdata and current_time('timestamp')-$backupdata['TIMESTAMP']>=480) { //8 min no progress.
		define('DONOTCACHEPAGE', true);
		define('DONOTCACHEDB', true);
		define('DONOTMINIFY', true);
		define('DONOTCDN', true);
		define('DONOTCACHCEOBJECT', true);
		//define E_DEPRECATED if PHP lower than 5.3
		if ( !defined('E_DEPRECATED') )
			define('E_DEPRECATED', 8192);
		if ( !defined('E_USER_DEPRECATED') )
			define('E_USER_DEPRECATED', 16384);
		//try to disable safe mode
		@ini_set('safe_mode', '0');
		// Now user abort
		@ini_set('ignore_user_abort', '0');
		ignore_user_abort(true);
		@set_time_limit(backwpup_get_option('cfg','jobrunmaxexectime'));
		include_once(dirname(__FILE__).'/job.php');
		new BackWPup_job('restarttime');
	} else {
		global $wpdb;
		$mains=$wpdb->get_col("SELECT main FROM `".$wpdb->prefix."backwpup` WHERE main LIKE 'job_%' AND name='activetype' AND value='wpcron'");
		if (!empty($mains)) {
			foreach ($mains as $main) {
				$cronnextrun=backwpup_get_option($main,'cronnextrun');
				if ($cronnextrun<=current_time('timestamp')) {
					define('DONOTCACHEPAGE', true);
					define('DONOTCACHEDB', true);
					define('DONOTMINIFY', true);
					define('DONOTCDN', true);
					define('DONOTCACHCEOBJECT', true);
					//define E_DEPRECATED if PHP lower than 5.3
					if ( !defined('E_DEPRECATED') )
						define('E_DEPRECATED', 8192);
					if ( !defined('E_USER_DEPRECATED') )
						define('E_USER_DEPRECATED', 16384);
					//try to disable safe mode
					@ini_set('safe_mode', '0');
					// Now user abort
					@ini_set('ignore_user_abort', '0');
					ignore_user_abort(true);
					@set_time_limit(backwpup_get_option('cfg','jobrunmaxexectime'));
					include_once(dirname(__FILE__).'/job.php');
					new BackWPup_job('cronrun',backwpup_get_option($main,'jobid'));
				}
			}
		}
	}
}
add_action('backwpup_cron',  'backwpup_cron_run');