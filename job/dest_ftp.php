<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function dest_ftp() {
	if (empty($_SESSION['JOB']['ftphost']) or empty($_SESSION['JOB']['ftpuser']) or empty($_SESSION['JOB']['ftppass'])) {
		$_SESSION['WORKING']['STEPSDONE'][]='DEST_FTP'; //set done	
		return;
	}
	trigger_error($_SESSION['WORKING']['DEST_FTP']['STEP_TRY'].'. '.__('Try to sending backup file to a FTP Server...','backwpup'),E_USER_NOTICE);
	$_SESSION['WORKING']['STEPTODO']=2;
	$_SESSION['WORKING']['STEPDONE']=0;
	need_free_memory(filesize($this->backupdir.$this->backupfile)*1.5);
	
	$ftpport=21;
	$ftphost=$_SESSION['JOB']['ftphost'];
	if (false !== strpos($_SESSION['JOB']['ftphost'],':')) //look for port
		list($ftphost,$ftpport)=explode(':',$_SESSION['JOB']['ftphost'],2);

	if ($_SESSION['JOB']['ftpssl']) { //make SSL FTP connection
		if (function_exists('ftp_ssl_connect')) {
			$ftp_conn_id = ftp_ssl_connect($ftphost,$ftpport,10);
			if ($ftp_conn_id) 
				trigger_error(__('Connected by SSL-FTP to Server:','backwpup').' '.$_SESSION['JOB']['ftphost'],E_USER_NOTICE);
			else {
				trigger_error(__('Can not connect by SSL-FTP to Server:','backwpup').' '.$_SESSION['JOB']['ftphost'],E_USER_ERROR);
				return false;
			}
		} else {
			trigger_error(__('PHP Function to connect with SSL-FTP to Server not exists!','backwpup'),E_USER_ERROR);
			return false;			
		}
	} else { //make normal FTP conection if SSL not work
		$ftp_conn_id = ftp_connect($ftphost,$ftpport,10);
		if ($ftp_conn_id) 
			trigger_error(__('Connected to FTP Server:','backwpup').' '.$_SESSION['JOB']['ftphost'],E_USER_NOTICE);
		else {
			trigger_error(__('Can not connect to FTP Server:','backwpup').' '.$_SESSION['JOB']['ftphost'],E_USER_ERROR);
			return false;
		}
	}

	//FTP Login
	$loginok=false;
	trigger_error(__('FTP Client command:','backwpup').' USER '.$_SESSION['JOB']['ftpuser'],E_USER_NOTICE);
	if ($loginok=ftp_login($ftp_conn_id, $_SESSION['JOB']['ftpuser'], base64_decode($_SESSION['JOB']['ftppass']))) {
		trigger_error(__('FTP Server reply:','backwpup').' User '.$_SESSION['JOB']['ftpuser'].' logged in.',E_USER_NOTICE);
	} else { //if PHP ftp login don't work use raw login
		$return=ftp_raw($ftp_conn_id,'USER '.$_SESSION['JOB']['ftpuser']); 
		trigger_error(__('FTP Server reply:','backwpup').' '.$return[0],E_USER_NOTICE);
		if (substr(trim($return[0]),0,3)<=400) {
			trigger_error(__('FTP Client command:','backwpup').' PASS *******',E_USER_NOTICE);
			$return=ftp_raw($ftp_conn_id,'PASS '.base64_decode($_SESSION['JOB']['ftppass']));
			trigger_error(__('FTP Server reply:','backwpup').' '.$return[0],E_USER_NOTICE);
			if (substr(trim($return[0]),0,3)<=400) 
				$loginok=true;
		}
	}

	if (!$loginok)
		return false;

	//PASV
	trigger_error(__('FTP Client command:','backwpup').' PASV',E_USER_NOTICE);
	if ($_SESSION['JOB']['ftppasv']) {
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

	//test ftp dir and create it f not exists
	$ftpdirs=explode("/", untrailingslashit($_SESSION['JOB']['ftpdir']));
	foreach ($ftpdirs as $ftpdir) {
		if (empty($ftpdir))
			continue;
		if (!@ftp_chdir($ftp_conn_id, $ftpdir)) {
			trigger_error('"'.$ftpdir.'" '.__('FTP Folder on Server not exists!','backwpup'),E_USER_WARNING);
			if (@ftp_mkdir($ftp_conn_id, $ftpdir)) {
				trigger_error('"'.$ftpdir.'" '.__('FTP Folder created!','backwpup'),E_USER_NOTICE);
				ftp_chdir($ftp_conn_id, $ftpdir);
			} else {
				trigger_error('"'.$ftpdir.'" '.__('FTP Folder on Server can not created!','backwpup'),E_USER_ERROR);
			}
		}
	}

	if (ftp_put($ftp_conn_id, $_SESSION['JOB']['ftpdir'].$this->backupfile, $this->backupdir.$this->backupfile, FTP_BINARY)) { //transfere file
		trigger_error(__('Backup File transferred to FTP Server:','backwpup').' '.$_SESSION['JOB']['ftpdir'].$this->backupfile,E_USER_NOTICE);
		$this->lastbackupdownloadurl="ftp://".$_SESSION['JOB']['ftpuser'].":".base64_decode($_SESSION['JOB']['ftppass'])."@".$_SESSION['JOB']['ftphost'].$_SESSION['JOB']['ftpdir'].$this->backupfile;
	} else
		trigger_error(__('Can not transfer backup to FTP server.','backwpup'),E_USER_ERROR);

	if ($_SESSION['JOB']['ftpmaxbackups']>0) { //Delete old backups
		$backupfilelist=array();
		if ($filelist=ftp_nlist($ftp_conn_id, $_SESSION['JOB']['ftpdir'])) {
			foreach($filelist as $files) {
				if ($_SESSION['JOB']['fileprefix'] == substr(basename($files),0,strlen($_SESSION['JOB']['fileprefix'])) and $this->backupfileformat == substr(basename($files),-strlen($this->backupfileformat)))
					$backupfilelist[]=basename($files);
			}
			if (sizeof($backupfilelist)>0) {
				rsort($backupfilelist);
				$numdeltefiles=0;
				for ($i=$_SESSION['JOB']['ftpmaxbackups'];$i<sizeof($backupfilelist);$i++) {
					if (ftp_delete($ftp_conn_id, $_SESSION['JOB']['ftpdir'].$backupfilelist[$i])) //delte files on ftp
					$numdeltefiles++;
					else
						trigger_error(__('Can not delete file on FTP Server:','backwpup').' '.$_SESSION['JOB']['ftpdir'].$backupfilelist[$i],E_USER_ERROR);
				}
				if ($numdeltefiles>0)
					trigger_error($numdeltefiles.' '.__('files deleted on FTP Server:','backwpup'),E_USER_NOTICE);
			}
		}
	}
	ftp_close($ftp_conn_id);


	$_SESSION['WORKING']['STEPDONE']=2;
	$_SESSION['WORKING']['STEPSDONE'][]='DEST_FTP'; //set done
}
?>