<?php
set_time_limit(300);

$cfg=get_option('backwpup');
$jobs=get_option('backwpup_jobs');
$logfilename='/backwpup_'.$jobid.'_'.date('Y-m-d_H-i-s').'.log';
$logfile=$cfg['tempdir'].$logfilename;
$backupfilename='/backwpup_'.$jobid.'_'.date('Y-m-d_H-i-s').'.zip';
$backupfile=$cfg['tempdir'].$backupfilename;
$joberror=false;

//Look for and Crate Temp dir
if (!is_dir($cfg['tempdir'].'/')) {
	if (!mkdir($cfg['tempdir'].'/')) {
		return false;
	}	 
}
if (!is_file($cfg['tempdir'].'/.htaccess')) {
	if($file = @fopen($cfg['tempdir'].'/.htaccess', 'w')) {
		fwrite($file, "Order allow,deny\ndeny from all");
		fclose($file);
	}
}
if (!is_file($cfg['tempdir'].'/index.html')) {
	if($file = @fopen($cfg['tempdir'].'/index.html', 'w')) {
		fwrite($file,"\n");
		fclose($file);
	} 
}

//Set start vars	
$jobs[$jobid]['starttime']=time();
$jobs[$jobid]['stoptime']='';
$jobs[$jobid]['scheduletime']=wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid));
update_option('backwpup_jobs',$jobs); //Save Settings
?>