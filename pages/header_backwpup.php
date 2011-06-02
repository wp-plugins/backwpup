<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

//Create Table
$backwpup_listtable = new BackWPup_Jobs_Table;

//get cuurent action
$doaction = $backwpup_listtable->current_action();
	
if (!empty($doaction)) {
	switch($doaction) {
	case 'delete': //Delete Job
		$jobs=get_option('backwpup_jobs');
		if (is_array($_REQUEST['jobs'])) {
			check_admin_referer('bulk-jobs');
			foreach ($_REQUEST['jobs'] as $jobid) {
				unset($jobs[$jobid]);
			}
		}
		update_option('backwpup_jobs',$jobs);
		break;
	case 'copy': //Copy Job
		$jobid = (int) $_GET['jobid'];
		check_admin_referer('copy-job_'.$jobid);
		$jobs=get_option('backwpup_jobs');
		//generate new ID
		$heighestid=0;
		foreach ($jobs as $jobkey => $jobvalue) {
			if ($jobkey>$heighestid) $heighestid=$jobkey;
		}
		$newjobid=$heighestid+1;
		$jobs[$newjobid]=$jobs[$jobid];
		$jobs[$newjobid]['name']=__('Copy of','backwpup').' '.$jobs[$newjobid]['name'];
		$jobs[$newjobid]['activated']=false;
		update_option('backwpup_jobs',$jobs);
		break;
	case 'export': //Copy Job
		$jobs=get_option('backwpup_jobs');
		if (is_array($_REQUEST['jobs'])) {
			check_admin_referer('bulk-jobs');
			foreach ($_REQUEST['jobs'] as $jobid) {
				$jobsexport[$jobid]=backwpup_check_job_vars($jobs[$jobid],$jobid);
			}
		}
		$export=serialize($jobsexport);
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Content-Type: text/plain");
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");
		header("Content-Disposition: attachment; filename=".sanitize_key(get_bloginfo('name'))."_BackWPupExport.txt;");
		header("Content-Transfer-Encoding: 8bit");
		header("Content-Length: ".strlen($export));
		echo $export;
		die();		
		break;
	case 'abort': //Abort Job
		check_admin_referer('abort-job');
		$runfile=file_get_contents(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running');
		$runningfile=unserialize(trim($runfile));
		unlink(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running');
		$backwpup_message=__('Job will be terminated.','backwpup').'<br />';
		if (!empty($runningfile['PID']) and function_exists('posix_kill')) {
			if (posix_kill($runningfile['PID'],SIGKILL))
				$backwpup_message.=__('Process killed with PID:','backwpup').' '.$runningfile['PID'];
			else
				$backwpup_message.=__('Can\'t kill process with PID:','backwpup').' '.$runningfile['PID'];
		}	
		break;
	}
}

//add Help
backwpup_contextual_help(
	'<div class="metabox-prefs">'.
	''.
	'</div>'
);

$backwpup_listtable->prepare_items();
?>