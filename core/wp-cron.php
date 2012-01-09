<?PHP
function backwpup_cron_run() {
	global $wpdb;
	$backupdata=backwpup_get_option('working','data');
	if (!empty($backupdata)) {
		$revtime=current_time('timestamp')-600; //10 min no progress.
		if (!empty($backupdata['working']['TIMESTAMP']) and $backupdata['working']['TIMESTAMP']<$revtime)
			backwpup_jobrun_url('restarttime','',true);
	} else {
		$mains=$wpdb->get_col("SELECT main FROM `".$wpdb->prefix."backwpup` WHERE main LIKE 'job_%' AND name='activetype' AND vlaue='wpcron'");
		if (!empty($mains)) {
			foreach ($mains as $main) {
				$cronnextrun=backwpup_get_option($main,'cronnextrun');
				if ($cronnextrun<=current_time('timestamp')) {
					$jobstartid=backwpup_get_option($main,'jobid');
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