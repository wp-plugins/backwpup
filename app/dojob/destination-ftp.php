<?PHP 
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');

if (!empty($jobs[$jobid]['ftphost']) and !empty($jobs[$jobid]['ftpuser']) and !empty($jobs[$jobid]['ftppass'])) {
	
	function ftp_raw_helper($ftp_conn_id,$command) { //FTP Comands helper function
		$return=ftp_raw($ftp_conn_id,$command);
		if (strtoupper(substr(trim($command),0,4))=="PASS") {
			backwpup_joblog($logtime,__('FTP Client command:','backwpup').' PASS *******');
		} else {
			backwpup_joblog($logtime,__('FTP Client command:','backwpup').' '.$command);
		}
		foreach ($return as $returnline) {
			$code=substr(trim($returnline),0,3);
			if ($code>=100 and $code<200) {
				backwpup_joblog($logtime,__('FTP Server Preliminary reply:','backwpup').' '.$returnline);
				return true;
			} elseif ($code>=200 and $code<300) {
				backwpup_joblog($logtime,__('FTP Server Completion reply:','backwpup').' '.$returnline);
				return true;
			} elseif ($code>=300 and $code<400) {
				backwpup_joblog($logtime,__('FTP Server Intermediate reply:','backwpup').' '.$returnline);
				return true;
			} elseif ($code>=400)  {
				backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('FTP Server reply:','backwpup').' '.$returnline);
				return false;
			} else {
				backwpup_joblog($logtime,__('FTP Server answer:','backwpup').' '.$returnline);
				return $return;
			}
		}
	}
	

	$ftpport=21;
	$ftphost=$jobs[$jobid]['ftphost'];
	if (false !== strpos($jobs[$jobid]['ftphost'],':')) //look for port
		list($ftphost,$ftpport)=split(':',$jobs[$jobid]['ftphost'],2);

	if (function_exists('ftp_ssl_connect')) { //make SSL FTP connection
		$ftp_conn_id = ftp_ssl_connect($ftphost,$ftpport,10);
		if ($ftp_conn_id) {
			backwpup_joblog($logtime,__('Connected by SSL to FTP server:','backwpup').' '.$jobs[$jobid]['ftphost']);
		}
	}
	if (!$ftp_conn_id) { //make normal FTP conection if SSL not work
		$ftp_conn_id = ftp_connect($ftphost,$ftpport,10);
		if ($ftp_conn_id) {
			backwpup_joblog($logtime,__('Connected insecure to FTP server:','backwpup').' '.$jobs[$jobid]['ftphost']);
		}
	}
	
	if ($ftp_conn_id) {
		backwpup_joblog($logtime,__('FTP server System is:','backwpup').' '.ftp_systype($ftp_conn_id));
		
		//FTP Login
		$loginok=false;
		if (ftp_raw_helper($ftp_conn_id,'USER '.$jobs[$jobid]['ftpuser'])) {
			if (ftp_raw_helper($ftp_conn_id,'PASS '.base64_decode($jobs[$jobid]['ftppass']))) {
				$loginok=true;
			}
		}

		//if (ftp_login($ftp_conn_id, $jobs[$jobid]['ftpuser'], $jobs[$jobid]['ftppass'])) {
		if ($loginok) {
			//PASV
			ftp_raw_helper($ftp_conn_id,'PASV');
			//ALLO
			ftp_raw_helper($ftp_conn_id,'ALLO '.filesize($backupfile));
				
			if (ftp_put($ftp_conn_id, trailingslashit($jobs[$jobid]['ftpdir']).basename($backupfile), $backupfile, FTP_BINARY))  //transvere file
				backwpup_joblog($logtime,__('Backup File transferred to FTP Server:','backwpup').' '.trailingslashit($jobs[$jobid]['ftpdir']).basename($backupfile));
			else
				backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('Can not transfer backup to FTP server.','backwpup'));
			
			unset($backupfilelist);			
			if ($jobs[$jobid]['ftpmaxbackups']>0) { //Delete old backups
				if ($filelist=ftp_nlist($ftp_conn_id, trailingslashit($jobs[$jobid]['ftpdir']))) {
					foreach($filelist as $files) {
						if ('backwpup_'.$jobid.'_' == substr(basename($files),0,strlen('backwpup_'.$jobid.'_')) and ".zip" == substr(basename($files),-4))
							$backupfilelist[]=basename($files);
					}
					if (sizeof($backupfilelist)>0) {
						rsort($backupfilelist);
						$numdeltefiles=0;
						for ($i=$jobs[$jobid]['ftpmaxbackups'];$i<sizeof($backupfilelist);$i++) {
							if (ftp_delete($ftp_conn_id, trailingslashit($jobs[$jobid]['ftpdir']).$backupfilelist[$i])) //delte files on ftp
							$numdeltefiles++;
							else 
								backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('Can not delete file on FTP Server:','backwpup').' '.trailingslashit($jobs[$jobid]['ftpdir']).$backupfilelist[$i]);
						}
						if ($numdeltefiles>0)
							backwpup_joblog($logtime,$numdeltefiles.' '.__('files deleted on FTP Server:','backwpup'));
					}
				}
			}
		} 
		ftp_close($ftp_conn_id); 
	} else {
		backwpup_joblog($logtime,__('ERROR:','backwpup').' '.__('Can not connect to FTP server:','backwpup').' '.$jobs[$jobid]['ftphost']);
	}
}
?>
