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
		if (is_array($_GET['jobs'])) {
			check_admin_referer('bulk-jobs');
			foreach ($_GET['jobs'] as $jobid) {
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
		if (is_array($_GET['jobs'])) {
			check_admin_referer('bulk-jobs');
			foreach ($_GET['jobs'] as $jobid) {
				$jobsexport[$jobid]=backwpup_get_job_vars($jobid);
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
		$runningfile=backwpup_get_working_file();
		unlink(backwpup_get_working_dir().'/.running'); //delete runnig file
		file_put_contents($runningfile['LOGFILE'], "<span class=\"timestamp\">".date_i18n('Y-m-d H:i.s').":</span> <span class=\"error\">[ERROR]".__('Aborted by user!!!','backwpup')."</span><br />\n", FILE_APPEND);
		//write new log header
		$runningfile['ERROR']++;
		$fd=fopen($runningfile['LOGFILE'],'r+');
		while (!feof($fd)) {
			$line=fgets($fd);
			if (stripos($line,"<meta name=\"backwpup_errors\"") !== false) {
				fseek($fd,$filepos);
				fwrite($fd,str_pad("<meta name=\"backwpup_errors\" content=\"".$runningfile['ERROR']."\" />",100)."\n");
				break;
			}
			$filepos=ftell($fd);
		}
		fclose($fd);
		$backwpup_message=__('Job will be terminated.','backwpup').'<br />';
		if (!empty($runningfile['PID']) and function_exists('posix_kill')) {
			if (posix_kill($runningfile['PID'],9)) {
				$backwpup_message.=__('Process killed with PID:','backwpup').' '.$runningfile['PID'];
				//gzip logfile
				file_put_contents($runningfile['LOGFILE'], "</body>\n</html>\n", FILE_APPEND);
				$cfg=get_option('backwpup');
				if ($cfg['gzlogs']) {
					$fd=fopen($runningfile['LOGFILE'],'r');
					$zd=gzopen($runningfile['LOGFILE'].'.gz','w9');
					while (!feof($fd)) 
						gzwrite($zd,fread($fd,4096));
					gzclose($zd);
					fclose($fd);
					unlink($runningfile['LOGFILE']);
					$newlogfile=$runningfile['LOGFILE'].'.gz';
				}
			} else {
				$backwpup_message.=__('Can\'t kill process with PID:','backwpup').' '.$runningfile['PID'];
			}
		}
		//update job settings
		$jobs=get_option('backwpup_jobs');
		if (isset($newlogfile) and !empty($newlogfile))
			$jobs[$runningfile['JOBID']]['logfile']=$newlogfile;
		$jobs[$runningfile['JOBID']]['lastrun']=$jobs[$runningfile['JOBID']]['starttime'];
		$jobs[$runningfile['JOBID']]['lastruntime']=$runningfile['timestamp']-$jobs[$runningfile['JOBID']]['starttime'];
		update_option('backwpup_jobs',$jobs); //Save Settings
		break;
	}
}

//add Help
backwpup_contextual_help(__('Here is the job overview with some information. You can see some further information of the jobs, how many can be switched with the view button. Also you can manage the jobs or abbort working jobs. Some links are added to have direct access to the last log or download.','backwpup'));

$backwpup_listtable->prepare_items();
?>