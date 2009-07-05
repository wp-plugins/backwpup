<?php
BackWPupFunctions::joblog($logfile,__('Move Backup Zip file to Backup dir...','backwpup'));

if (!is_file($jobs[$jobid]['backupdir'].'/.htaccess')) {
	if($file = fopen($jobs[$jobid]['backupdir'].'/.htaccess', 'w')) {
		fwrite($file, "Order allow,deny\ndeny from all");
		fclose($file);
	}
}
if (!is_file($jobs[$jobid]['backupdir'].'/index.html')) {
	if($file = fopen($jobs[$jobid]['backupdir'].'/index.html', 'w')) {
		fwrite($file,"\n");
		fclose($file);
	} 
}

if ($jobs[$jobid]['backupdir']!=$cfg['tempdir']) {
	if (!rename($backupfile,$jobs[$jobid]['backupdir'].$backupfilename)) {
		BackWPupFunctions::joblog($logfile,__('ERROR: Backup Zip file can not moved to Backup dir!!!','backwpup'));
		$joberror=true;
	} else {
		$backupfile=$jobs[$jobid]['backupdir'].$backupfilename;
	}
	if (!rename($logfile,$jobs[$jobid]['backupdir'].$logfilename)) {
		BackWPupFunctions::joblog($logfile,__('ERROR: Log file file can not moved to Backup dir!!!','backwpup'));
		$joberror=true;
	} else {
		$logfile=$jobs[$jobid]['backupdir'].$logfilename;
	}
}

if (is_file($backupfile)) {
	BackWPupFunctions::joblog($logfile,__('Backup zip file saved to:','backwpup').' '.$backupfile);
	BackWPupFunctions::joblog($logfile,__('Backup zip filesize is','backwpup').' '.BackWPupFunctions::formatBytes(filesize($backupfile)));
}
BackWPupFunctions::joblog($logfile,__('Log file saved to:','backwpup').' '.$logfile);

if (!empty($jobs[$jobid]['maxbackups'])) {
	BackWPupFunctions::joblog($logfile,__('Delete old backup files...','backwpup'));
	$logs=get_option('backwpup_log');
	if (is_array($logs)) {
		unset($logkeys);
		foreach ($logs as $timestamp => $logdata) {
			if ($logdata['jobid']==$jobid)
				$logkeys[]=$timestamp;
		}
		if (is_array($logkeys)) {
			rsort($logkeys,SORT_NUMERIC);
			$counter=0;$countdelbackups=0;$countdellogs=0;
			for ($i=0;$i<sizeof($logkeys);$i++) {
				if (!empty($logs[$logkeys[$i]]['backupfile']))
					$counter++;
				if ($counter>=$jobs[$jobid]['maxbackups']) {
					if (is_file($logs[$logkeys[$i]]['backupfile'])) {
						unlink($logs[$logkeys[$i]]['backupfile']);
						$countdelbackups++;
					}
					if (is_file($logs[$logkeys[$i]]['logfile'])) {
						unlink($logs[$logkeys[$i]]['logfile']);
						$countdellogs++;
					}
					unset($logs[$logkeys[$i]]);
				}
			}
		}
	}
	update_option('backwpup_log',$logs);
	BackWPupFunctions::joblog($logfile,$countdelbackups.' '.__('Old backup files deleted!!!','backwpup'));
	BackWPupFunctions::joblog($logfile,$countdellogs.' '.__('Old Log files deleted!!!','backwpup'));
	//clean vars
	unset($logkeys);
	unset($logs);
}

?>