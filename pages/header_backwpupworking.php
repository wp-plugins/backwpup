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
		$runfile=trim(file_get_contents(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running'));
		$infile=unserialize($runfile);
		$_GET['logfile']=$infile['LOGFILE'];
	} else {
		$_GET['logfile']=backwpup_jobstart($jobid);
	}
}
elseif (!empty($_GET['logfile'])) {
	check_admin_referer('view-log_'.basename(trim($_GET['logfile'])));
}
elseif (is_file(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running')) {
	$backwpup_message=__('A job is running!!!','backwpup');
	$runfile=trim(file_get_contents(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running'));
	$infile=unserialize($runfile);
	$_GET['logfile']=$infile['LOGFILE'];
}
elseif (!isset($_GET['action']) and !isset($_GET['logfile']) and empty($backwpup_message)) {
	$backwpup_message=__('Nothing...','backwpup');
	$_GET['logfile']='';
}

//add Help
backwpup_contextual_help(
	'<div class="metabox-prefs">'.
	''.
	'</div>'
);
?>