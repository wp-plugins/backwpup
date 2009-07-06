<?php
set_time_limit(300);

$cfg=get_option('backwpup');
$jobs=get_option('backwpup_jobs');
$logfilename='/backwpup_'.$jobid.'_'.date('Y-m-d_H-i-s').'.log';
$logfile=BackWPupFunctions::get_temp_dir().'backwpup'.$logfilename;
$backupfilename='/backwpup_'.$jobid.'_'.date('Y-m-d_H-i-s').'.zip';
$backupfile=BackWPupFunctions::get_temp_dir().'backwpup'.$backupfilename;
$joberror=false;

//Look for and Crate Temp dir
if (!is_dir(BackWPupFunctions::get_temp_dir().'backwpup')) {
	if (!mkdir(BackWPupFunctions::get_temp_dir().'backwpup')) {
		return false;
	}	 
}
if (!is_file(BackWPupFunctions::get_temp_dir().'backwpup/.htaccess')) {
	if($file = @fopen(BackWPupFunctions::get_temp_dir().'backwpup/.htaccess', 'w')) {
		fwrite($file, "Order allow,deny\ndeny from all");
		fclose($file);
	}
}
if (!is_file(BackWPupFunctions::get_temp_dir().'backwpup/index.html')) {
	if($file = @fopen(BackWPupFunctions::get_temp_dir().'backwpup/index.html', 'w')) {
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