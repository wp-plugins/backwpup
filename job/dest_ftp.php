<?PHP
function backwpup_job_dest_ftp() {
	global $backwpupjobrun;
	if (empty($backwpupjobrun['STATIC']['JOB']['ftphost']) or empty($backwpupjobrun['STATIC']['JOB']['ftpuser']) or empty($backwpupjobrun['STATIC']['JOB']['ftppass'])) {
		$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_FTP'; //set done
		return;
	}
	$backwpupjobrun['WORKING']['STEPTODO']=2;
	trigger_error(sprintf(__('%d. Trying to sending backup file to a FTP Server...','backwpup'),$backwpupjobrun['WORKING']['DEST_FTP']['STEP_TRY']),E_USER_NOTICE);

	backwpup_job_need_free_memory($backwpupjobrun['WORKING']['backupfilesize']*1.5);

	if ($backwpupjobrun['STATIC']['JOB']['ftpssl']) { //make SSL FTP connection
		if (function_exists('ftp_ssl_connect')) {
			$ftp_conn_id = ftp_ssl_connect($backwpupjobrun['STATIC']['JOB']['ftphost'],$backwpupjobrun['STATIC']['JOB']['ftphostport'],10);
			if ($ftp_conn_id)
				trigger_error(sprintf(__('Connected by SSL-FTP to Server: %s','backwpup'),$backwpupjobrun['STATIC']['JOB']['ftphost'].':'.$backwpupjobrun['STATIC']['JOB']['ftphostport']),E_USER_NOTICE);
			else {
				trigger_error(sprintf(__('Can not connect by SSL-FTP to Server: %s','backwpup'),$backwpupjobrun['STATIC']['JOB']['ftphost'].':'.$backwpupjobrun['STATIC']['JOB']['ftphostport']),E_USER_ERROR);
				return false;
			}
		} else {
			trigger_error(__('PHP function to connect with SSL-FTP to server not exists!','backwpup'),E_USER_ERROR);
			$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_FTP'; //set done
			return;
		}
	} else { //make normal FTP conection if SSL not work
		$ftp_conn_id = ftp_connect($backwpupjobrun['STATIC']['JOB']['ftphost'],$backwpupjobrun['STATIC']['JOB']['ftphostport'],10);
		if ($ftp_conn_id)
			trigger_error(sprintf(__('Connected to FTP server: %s','backwpup'),$backwpupjobrun['STATIC']['JOB']['ftphost'].':'.$backwpupjobrun['STATIC']['JOB']['ftphostport']),E_USER_NOTICE);
		else {
			trigger_error(sprintf(__('Can not connect to FTP server: %s','backwpup'),$backwpupjobrun['STATIC']['JOB']['ftphost'].':'.$backwpupjobrun['STATIC']['JOB']['ftphostport']),E_USER_ERROR);
			return false;
		}
	}

	//FTP Login
	$loginok=false;
	trigger_error(sprintf(__('FTP Client command: %s','backwpup'),' USER '.$backwpupjobrun['STATIC']['JOB']['ftpuser']),E_USER_NOTICE);
	if ($loginok=ftp_login($ftp_conn_id, $backwpupjobrun['STATIC']['JOB']['ftpuser'], base64_decode($backwpupjobrun['STATIC']['JOB']['ftppass']))) {
		trigger_error(sprintf(__('FTP Server reply: %s','backwpup'),' User '.$backwpupjobrun['STATIC']['JOB']['ftpuser'].' logged in.'),E_USER_NOTICE);
	} else { //if PHP ftp login don't work use raw login
		$return=ftp_raw($ftp_conn_id,'USER '.$backwpupjobrun['STATIC']['JOB']['ftpuser']);
		trigger_error(sprintf(__('FTP Server reply: %s','backwpup'),$return[0]),E_USER_NOTICE);
		if (substr(trim($return[0]),0,3)<=400) {
			trigger_error(sprintf(__('FTP Client command: %s','backwpup'),' PASS *******'),E_USER_NOTICE);
			$return=ftp_raw($ftp_conn_id,'PASS '.base64_decode($backwpupjobrun['STATIC']['JOB']['ftppass']));
			trigger_error(sprintf(__('FTP Server reply: %s','backwpup'),$return[0]),E_USER_NOTICE);
			if (substr(trim($return[0]),0,3)<=400)
				$loginok=true;
		}
	}

	if (!$loginok)
		return false;

	//PASV
	trigger_error(sprintf(__('FTP Client command: %s','backwpup'),' PASV'),E_USER_NOTICE);
	if ($backwpupjobrun['STATIC']['JOB']['ftppasv']) {
		if (ftp_pasv($ftp_conn_id, true))
			trigger_error(sprintf(__('FTP Server reply: %s','backwpup'),__('Entering Passive Mode','backwpup')),E_USER_NOTICE);
		else
			trigger_error(sprintf(__('FTP Server reply: %s','backwpup'),__('Can not Entering Passive Mode','backwpup')),E_USER_WARNING);
	} else {
		if (ftp_pasv($ftp_conn_id, false))
			trigger_error(sprintf(__('FTP Server reply: %s','backwpup'),__('Entering Normal Mode','backwpup')),E_USER_NOTICE);
		else
			trigger_error(sprintf(__('FTP Server reply: %s','backwpup'),__('Can not Entering Normal Mode','backwpup')),E_USER_WARNING);
	}
	//SYSTYPE
	trigger_error(sprintf(__('FTP Client command: %s','backwpup'),' SYST'),E_USER_NOTICE);
	$systype=ftp_systype($ftp_conn_id);
	if ($systype)
		trigger_error(sprintf(__('FTP Server reply: %s','backwpup'),$systype),E_USER_NOTICE);
	else
		trigger_error(sprintf(__('FTP Server reply: %s','backwpup'),__('Error getting SYSTYPE','backwpup')),E_USER_ERROR);

	if ($backwpupjobrun['WORKING']['STEPDONE']==0) {
		//test ftp dir and create it f not exists
		$ftpdirs=explode("/", rtrim($backwpupjobrun['STATIC']['JOB']['ftpdir'],'/'));
		foreach ($ftpdirs as $ftpdir) {
			if (empty($ftpdir))
				continue;
			if (!@ftp_chdir($ftp_conn_id, $ftpdir)) {
				if (@ftp_mkdir($ftp_conn_id, $ftpdir)) {
					trigger_error(sprintf(__('FTP Folder "%s" created!','backwpup'),$ftpdir),E_USER_NOTICE);
					ftp_chdir($ftp_conn_id, $ftpdir);
				} else {
					trigger_error(sprintf(__('FTP Folder "%s" can not created!','backwpup'),$ftpdir),E_USER_ERROR);
					return false;
				}
			}
		}
		trigger_error(__('Upload to FTP now started ... ','backwpup'),E_USER_NOTICE);
		if (ftp_put($ftp_conn_id, $backwpupjobrun['STATIC']['JOB']['ftpdir'].$backwpupjobrun['STATIC']['backupfile'], $backwpupjobrun['STATIC']['JOB']['backupdir'].$backwpupjobrun['STATIC']['backupfile'], FTP_BINARY)) { //transfere file
			$backwpupjobrun['WORKING']['STEPTODO']=1+$backwpupjobrun['WORKING']['backupfilesize'];
			trigger_error(sprintf(__('Backup transferred to FTP server: %s','backwpup'),$backwpupjobrun['STATIC']['JOB']['ftpdir'].$backwpupjobrun['STATIC']['backupfile']),E_USER_NOTICE);
			$backwpupjobrun['STATIC']['JOB']['lastbackupdownloadurl']="ftp://".$backwpupjobrun['STATIC']['JOB']['ftpuser'].":".base64_decode($backwpupjobrun['STATIC']['JOB']['ftppass'])."@".$backwpupjobrun['STATIC']['JOB']['ftphost'].$backwpupjobrun['STATIC']['JOB']['ftpdir'].$backwpupjobrun['STATIC']['backupfile'];
			$backwpupjobrun['WORKING']['STEPSDONE'][]='DEST_FTP'; //set done
		} else
			trigger_error(__('Can not transfer backup to FTP server!','backwpup'),E_USER_ERROR);
	}

	if ($backwpupjobrun['STATIC']['JOB']['ftpmaxbackups']>0) { //Delete old backups
		$backupfilelist=array();
		if ($filelist=ftp_nlist($ftp_conn_id, $backwpupjobrun['STATIC']['JOB']['ftpdir'])) {
			foreach($filelist as $files) {
				if ($backwpupjobrun['STATIC']['JOB']['fileprefix'] == substr(basename($files),0,strlen($backwpupjobrun['STATIC']['JOB']['fileprefix'])) and $backwpupjobrun['STATIC']['JOB']['fileformart'] == substr(basename($files),-strlen($backwpupjobrun['STATIC']['JOB']['fileformart'])))
					$backupfilelist[ftp_mdtm($ftp_conn_id,$ftpfiles)]=basename($files);
			}
			if (count($backupfilelist)>$backwpupjobrun['STATIC']['JOB']['ftpmaxbackups']) {
				$numdeltefiles=0;
				while ($file=array_shift($backupfilelist)) {
					if (count($backupfilelist)<$backwpupjobrun['STATIC']['JOB']['ftpmaxbackups'])
						break;
					if (ftp_delete($ftp_conn_id, $backwpupjobrun['STATIC']['JOB']['ftpdir'].$file)) //delte files on ftp
						$numdeltefiles++;
					else
						trigger_error(sprintf(__('Can not delete "%s" on FTP server!','backwpup'),$backwpupjobrun['STATIC']['JOB']['ftpdir'].$file),E_USER_ERROR);

				}				
				if ($numdeltefiles>0)
					trigger_error(sprintf(_n('One file deleted on FTP Server','%d files deleted on FTP Server',$numdeltefiles,'backwpup'),$numdeltefiles),E_USER_NOTICE);
			}
		}
	}

	ftp_close($ftp_conn_id);
	$backwpupjobrun['WORKING']['STEPDONE']++;

}
?>