<?php

class BackWPupOptions {

	function delete_job($jobid) {
		$jobs=get_option('backwpup_jobs'); //Load Settings
		unset($jobs[$jobid]);
		update_option('backwpup_jobs',$jobs); //Save Settings
		if ($time=wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid))) {
			wp_unschedule_event($time,'backwpup_cron',array('jobid'=>$jobid));
		}
	}
	
	function delete_log($logtime) {
		global $wpdb;
		$backupfile=$wpdb->get_var("SELECT backupfile FROM ".$wpdb->backwpup_logs." WHERE logtime=".$logtime);
		if (is_file($backupfile)) 
			unlink($backupfile);
		$wpdb->query("DELETE FROM ".$wpdb->backwpup_logs." WHERE logtime=".$logtime);
	}

	function copy_job($jobid) {
		$jobs=get_option('backwpup_jobs'); //Load Settings
		
		//generate new ID
		foreach ($jobs as $jobkey => $jobvalue) {
			if ($jobkey>$heighestid) $heighestid=$jobkey;
		}
		$newjobid=$heighestid+1;

		$jobs[$newjobid]=$jobs[$jobid];
		$jobs[$newjobid]['name']=__('Copy of','backwpup').' '.$jobs[$newjobid]['name'];
		$jobs[$newjobid]['activated']=false;
		update_option('backwpup_jobs',$jobs); //Save Settings
	}

	function config() {
		$cfg=get_option('backwpup'); //Load Settings

		update_option('backwpup',$cfg); //Save Settings
	}
	
	function download_backup($logtime) {
		global $wpdb;
		$backupfile=$wpdb->get_var("SELECT backupfile FROM ".$wpdb->backwpup_logs." WHERE logtime=".$logtime);
		if (is_file($backupfile)) {
			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0"); 
			header("Content-Type: application/force-download");
			header("Content-Type: application/octet-stream");
			header("Content-Type: application/download");
			header("Content-Disposition: attachment; filename=".basename($backupfile).";");
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: ".filesize($backupfile));
			@readfile($backupfile);
		} else {
			header('HTTP/1.0 404 Not Found');
			die(__('File does not exist.', 'backwpup'));
		}			
	}
	
	function edit_job($jobid) {
		$jobs=get_option('backwpup_jobs'); //Load Settings
		
		if (empty($jobid)) { //generate a new id for new job
			if (is_array($jobs)) { 
				foreach ($jobs as $jobkey => $jobvalue) {
					if ($jobkey>$heighestid) $heighestid=$jobkey;
				}
				$jobid=$heighestid+1;
			} else {
				$jobid=1;
			}
		}
		
		
		$jobs[$jobid]['type']= $_POST['type'];
		$jobs[$jobid]['name']= esc_html($_POST['name']);
		$jobs[$jobid]['activated']= $_POST['activated']==1 ? true : false;
		$jobs[$jobid]['scheduletime']=mktime($_POST['schedulehour'],$_POST['scheduleminute'],0,$_POST['schedulemonth'],$_POST['scheduleday'],$_POST['scheduleyear']);
		$jobs[$jobid]['scheduleintervaltype']=$_POST['scheduleintervaltype'];
		$jobs[$jobid]['scheduleintervalteimes']=$_POST['scheduleintervalteimes'];
		$jobs[$jobid]['scheduleinterval']=$_POST['scheduleintervaltype']*$_POST['scheduleintervalteimes'];
		$jobs[$jobid]['backupdir']= untrailingslashit(str_replace('\\','/',stripslashes($_POST['backupdir'])));
		$jobs[$jobid]['maxbackups']=abs((int)$_POST['maxbackups']);
		$jobs[$jobid]['mailaddress']=sanitize_email($_POST['mailaddress']);
		$jobs[$jobid]['dbexclude']=array_unique((array)$_POST['dbexclude']);
		$jobs[$jobid]['fileexclude']=str_replace('\\','/',stripslashes($_POST['fileexclude']));
		$jobs[$jobid]['dirinclude']=str_replace('\\','/',stripslashes($_POST['dirinclude']));
		$jobs[$jobid]['backuproot']= $_POST['backuproot']==1 ? true : false;
		$jobs[$jobid]['backupcontent']= $_POST['backupcontent']==1 ? true : false;
		$jobs[$jobid]['backupplugins']= $_POST['backupplugins']==1 ? true : false;
		$jobs[$jobid]['ftphost']=$_POST['ftphost'];
		$jobs[$jobid]['ftpuser']=$_POST['ftpuser'];
		$jobs[$jobid]['ftppass']=$_POST['ftppass'];
		$jobs[$jobid]['ftpdir']=str_replace('\\','/',stripslashes($_POST['ftpdir']));
		$jobs[$jobid]['ftpmaxbackups']=abs((int)$_POST['ftpmaxbackups']);
		
		update_option('backwpup_jobs',$jobs); //Save Settings
		if ($time=wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid))) {
			wp_unschedule_event($time,'backwpup_cron',array('jobid'=>$jobid));
		}
		if ($jobs[$jobid]['activated']) {
			wp_schedule_event($jobs[$jobid]['scheduletime'], 'backwpup_int_'.$jobid, 'backwpup_cron',array('jobid'=>$jobid));
		}
		if (!empty($_POST['change'])) {
			$_REQUEST['action']='edit';
			$_REQUEST['jobid']=$jobid;
		} else {
			$_REQUEST['action']='';
		}
	}
}
?>