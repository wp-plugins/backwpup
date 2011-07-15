<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function dest_ftp() {
	global $WORKING,$STATIC;
	if (empty($STATIC['JOB']['ftphost']) or empty($STATIC['JOB']['ftpuser']) or empty($STATIC['JOB']['ftppass'])) {
		$WORKING['STEPSDONE'][]='DEST_FTP'; //set done	
		return;
	}
	$WORKING['STEPTODO']=2;
	trigger_error($WORKING['DEST_FTP']['STEP_TRY'].'. '.__('Try to sending backup file to a FTP Server...','backwpup'),E_USER_NOTICE);

	need_free_memory(filesize($STATIC['JOB']['backupdir'].$STATIC['backupfile'])*1.5);

	if ($STATIC['JOB']['ftpssl']) { //make SSL FTP connection
		if (function_exists('ftp_ssl_connect')) {
			$ftp_conn_id = ftp_ssl_connect($STATIC['JOB']['ftphost'],$STATIC['JOB']['ftphostport'],10);
			if ($ftp_conn_id) 
				trigger_error(__('Connected by SSL-FTP to Server:','backwpup').' '.$STATIC['JOB']['ftphost'].':'.$STATIC['JOB']['ftphostport'],E_USER_NOTICE);
			else {
				trigger_error(__('Can not connect by SSL-FTP to Server:','backwpup').' '.$STATIC['JOB']['ftphost'].':'.$STATIC['JOB']['ftphostport'],E_USER_ERROR);
				return false;
			}
		} else {
			trigger_error(__('PHP Function to connect with SSL-FTP to Server not exists!','backwpup'),E_USER_ERROR);
			return false;			
		}
	} else { //make normal FTP conection if SSL not work
		$ftp_conn_id = ftp_connect($STATIC['JOB']['ftphost'],$STATIC['JOB']['ftphostport'],10);
		if ($ftp_conn_id) 
			trigger_error(__('Connected to FTP Server:','backwpup').' '.$STATIC['JOB']['ftphost'].':'.$STATIC['JOB']['ftphostport'],E_USER_NOTICE);
		else {
			trigger_error(__('Can not connect to FTP Server:','backwpup').' '.$STATIC['JOB']['ftphost'].':'.$STATIC['JOB']['ftphostport'],E_USER_ERROR);
			return false;
		}
	}

	//FTP Login
	$loginok=false;
	trigger_error(__('FTP Client command:','backwpup').' USER '.$STATIC['JOB']['ftpuser'],E_USER_NOTICE);
	if ($loginok=ftp_login($ftp_conn_id, $STATIC['JOB']['ftpuser'], base64_decode($STATIC['JOB']['ftppass']))) {
		trigger_error(__('FTP Server reply:','backwpup').' User '.$STATIC['JOB']['ftpuser'].' logged in.',E_USER_NOTICE);
	} else { //if PHP ftp login don't work use raw login
		$return=ftp_raw($ftp_conn_id,'USER '.$STATIC['JOB']['ftpuser']); 
		trigger_error(__('FTP Server reply:','backwpup').' '.$return[0],E_USER_NOTICE);
		if (substr(trim($return[0]),0,3)<=400) {
			trigger_error(__('FTP Client command:','backwpup').' PASS *******',E_USER_NOTICE);
			$return=ftp_raw($ftp_conn_id,'PASS '.base64_decode($STATIC['JOB']['ftppass']));
			trigger_error(__('FTP Server reply:','backwpup').' '.$return[0],E_USER_NOTICE);
			if (substr(trim($return[0]),0,3)<=400) 
				$loginok=true;
		}
	}

	if (!$loginok)
		return false;

	//PASV
	trigger_error(__('FTP Client command:','backwpup').' PASV',E_USER_NOTICE);
	if ($STATIC['JOB']['ftppasv']) {
		if (ftp_pasv($ftp_conn_id, true))
			trigger_error(__('FTP Server reply:','backwpup').' '.__('Entering Passive Mode','backwpup'),E_USER_NOTICE);
		else
			trigger_error(__('FTP Server reply:','backwpup').' '.__('Can not Entering Passive Mode','backwpup'),E_USER_WARNING);
	} else {
		if (ftp_pasv($ftp_conn_id, false))
			trigger_error(__('FTP Server reply:','backwpup').' '.__('Entering Normal Mode','backwpup'),E_USER_NOTICE);
		else
			trigger_error(__('FTP Server reply:','backwpup').' '.__('Can not Entering Normal Mode','backwpup'),E_USER_WARNING);		
	}
	//SYSTYPE
	trigger_error(__('FTP Client command:','backwpup').' SYST',E_USER_NOTICE);
	$systype=ftp_systype($ftp_conn_id);
	if ($systype) 
		trigger_error(__('FTP Server reply:','backwpup').' '.$systype,E_USER_NOTICE);
	else
		trigger_error(__('FTP Server reply:','backwpup').' '.__('Error getting SYSTYPE','backwpup'),E_USER_ERROR);
	
	if ($WORKING['STEPDONE']==0) {
		//test ftp dir and create it f not exists
		$ftpdirs=explode("/", rtrim($STATIC['JOB']['ftpdir'],'/'));
		foreach ($ftpdirs as $ftpdir) {
			if (empty($ftpdir))
				continue;
			if (!@ftp_chdir($ftp_conn_id, $ftpdir)) {
				if (@ftp_mkdir($ftp_conn_id, $ftpdir)) {
					trigger_error('"'.$ftpdir.'" '.__('FTP Folder created!','backwpup'),E_USER_NOTICE);
					ftp_chdir($ftp_conn_id, $ftpdir);
				} else {
					trigger_error('"'.$ftpdir.'" '.__('FTP Folder on Server can not created!','backwpup'),E_USER_ERROR);
					return false;
				}
			}
		}
		trigger_error(__('Upload to FTP now started ... ','backwpup'),E_USER_NOTICE);
		@set_time_limit($STATIC['CFG']['jobscriptruntimelong']);
		if (ftp_put($ftp_conn_id, $STATIC['JOB']['ftpdir'].$STATIC['backupfile'], $STATIC['JOB']['backupdir'].$STATIC['backupfile'], FTP_BINARY)) { //transfere file
			$WORKING['STEPTODO']=1+filesize($STATIC['JOB']['backupdir'].$STATIC['backupfile']);
			trigger_error(__('Backup File transferred to FTP Server:','backwpup').' '.$STATIC['JOB']['ftpdir'].$STATIC['backupfile'],E_USER_NOTICE);
			$STATIC['JOB']['lastbackupdownloadurl']="ftp://".$STATIC['JOB']['ftpuser'].":".base64_decode($STATIC['JOB']['ftppass'])."@".$STATIC['JOB']['ftphost'].$STATIC['JOB']['ftpdir'].$STATIC['backupfile'];
		} else
			trigger_error(__('Can not transfer backup to FTP server.','backwpup'),E_USER_ERROR);
	}
	
	if ($STATIC['JOB']['ftpmaxbackups']>0) { //Delete old backups
		$backupfilelist=array();
		if ($filelist=ftp_nlist($ftp_conn_id, $STATIC['JOB']['ftpdir'])) {
			foreach($filelist as $files) {
				if ($STATIC['JOB']['fileprefix'] == substr(basename($files),0,strlen($STATIC['JOB']['fileprefix'])) and $STATIC['JOB']['fileformart'] == substr(basename($files),-strlen($STATIC['JOB']['fileformart'])))
					$backupfilelist[]=basename($files);
			}
			if (sizeof($backupfilelist)>0) {
				rsort($backupfilelist);
				$numdeltefiles=0;
				for ($i=$STATIC['JOB']['ftpmaxbackups'];$i<sizeof($backupfilelist);$i++) {
					if (ftp_delete($ftp_conn_id, $STATIC['JOB']['ftpdir'].$backupfilelist[$i])) //delte files on ftp
					$numdeltefiles++;
					else
						trigger_error(__('Can not delete file on FTP Server:','backwpup').' '.$STATIC['JOB']['ftpdir'].$backupfilelist[$i],E_USER_ERROR);
				}
				if ($numdeltefiles>0)
					trigger_error($numdeltefiles.' '.__('files deleted on FTP Server:','backwpup'),E_USER_NOTICE);
			}
		}
	}

	ftp_close($ftp_conn_id);
	$WORKING['STEPDONE']++;
	$WORKING['STEPSDONE'][]='DEST_FTP'; //set done
}
?>