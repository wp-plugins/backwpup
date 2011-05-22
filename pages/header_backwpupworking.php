<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

if (isset($_GET['action']) and $_GET['action']=='runnow' and !empty($_GET['jobid'])) {
	$jobid = (int) $_GET['jobid'];
	check_admin_referer('runnow-job_'.$jobid);
	if (is_file(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running')) {
		$backwpup_message=__('A job alredy running!!! Pleace try again if its done.','backwpup');
		$runfile=file_get_contents(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running');
		$runningfile=unserialize(trim($runfile));
		$_GET['logfile']=$runningfile['LOGFILE'];
	} else {
		$_GET['logfile']=backwpup_jobstart($jobid);
	}
}
elseif (!empty($_GET['logfile'])) {
	check_admin_referer('view-log_'.basename($_GET['logfile']));
}
elseif (is_file(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running')) {
	$backwpup_message=__('A job is running!!!','backwpup');
	$runfile=file_get_contents(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running');
	$runningfile=unserialize(trim($runfile));
	$_GET['logfile']=$runningfile['LOGFILE'];
}
else {
	$backwpup_message=__('Nothing...','backwpup');
}

//add Help
backwpup_contextual_help(
	'<div class="metabox-prefs">'.
	''.
	'</div>'
);
?>