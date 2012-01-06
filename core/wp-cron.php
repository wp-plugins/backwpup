<?PHP
function backwpup_cron_run() {
	global $wpdb;
	$backupdata=backwpup_get_option('working','data');
	if (!empty($backupdata)) {
		$revtime=current_time('timestamp')-600; //10 min no progress.
		if (!empty($backupdata['working']['TIMESTAMP']) and $backupdata['working']['TIMESTAMP']<$revtime)
			backwpup_jobrun_url('restarttime','',true);
	} else {
		$main_names=$wpdb->get_col("SELECT main_name FROM `".$wpdb->prefix."backwpup` WHERE main_name LIKE 'job_%' AND name='activetype' AND vlaue='wpcron'");
		if (!empty($main_names)) {
			foreach ($main_names as $main_name) {
				$cronnextrun=backwpup_get_option($main_name,'cronnextrun');
				if ($cronnextrun<=current_time('timestamp')) {
					$jobstartid=backwpup_get_option($main_name,'jobid');
					backwpup_update_option('job_' . $jobstartid, 'cronnextrun', backwpup_cron_next(backwpup_get_option('job_' . $jobstartid, 'cron'))); //update next run time
					backwpup_jobrun_url('cronrun',$jobstartid,true);
					exit;
				}
			}
		}
	}
}
add_action('backwpup_cron',  'backwpup_cron_run');
?>