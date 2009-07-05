<?PHP
if (is_file($cfg['tempdir'].'/'.DB_NAME.'.sql') ) {
	unlink($cfg['tempdir'].'/'.DB_NAME.'.sql');
}

$jobs[$jobid]['lastrun']=$jobs[$jobid]['starttime'];
$jobs[$jobid]['stoptime']=time();
$jobs[$jobid]['scheduletime']=wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid));
update_option('backwpup_jobs',$jobs); //Save Settings

$logs=get_option('backwpup_log');
$time=time();
$logs[$time]['jobid']=$jobid;
$logs[$time]['error']=$joberror;
$logs[$time]['logfile']=$logfile;
$logs[$time]['type']=$jobs[$jobid]['type'];
$logs[$time]['worktime']=$jobs[$jobid]['stoptime']-$jobs[$jobid]['starttime'];
if (is_file($backupfile))
	$logs[$time]['backupfile']=$backupfile;	
update_option('backwpup_log',$logs);
?>