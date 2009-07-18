<?php
if (!empty($jobs[$jobid]['ftphost']) and !empty($jobs[$jobid]['ftpuser']) and !empty($jobs[$jobid]['ftppass'])) {
	$ftpport=21;
	$ftphost=$jobs[$jobid]['ftphost'];
	if (false !== strpos($jobs[$jobid]['ftphost'],':')) //look for port
		list($ftphost,$ftpport)=split(':',$jobs[$jobid]['ftphost'],2);

	if (function_exists('ftp_ssl_connect')) { //make SSL FTP connection
		$ftp_conn_id = ftp_ssl_connect($ftphost,$ftpport);
		if ($ftp_conn_id)
			BackWPupFunctions::joblog($logtime,__('Connected by SSL to FTP server:','backwpup').' '.$jobs[$jobid]['ftphost']);
	}
	if (!$ftp_conn_id) { //make normal FTP conection if SSL not work
		$ftp_conn_id = ftp_connect($ftphost,$ftpport);
		if ($ftp_conn_id)
			BackWPupFunctions::joblog($logtime,__('Connected insecure to FTP server:','backwpup').' '.$jobs[$jobid]['ftphost']);		
	}
	
	if ($ftp_conn_id) {
		if ($login_result = ftp_login($ftp_conn_id, $jobs[$jobid]['ftpuser'], $jobs[$jobid]['ftppass'])) {
			BackWPupFunctions::joblog($logtime,__('Logt on to FTP server with user:','backwpup').' '.$jobs[$jobid]['ftpuser']);
			
			if (ftp_pasv($ftp_conn_id, true)) //set passive mode
				BackWPupFunctions::joblog($logtime,__('FTP set to passiv.','backwpup'));
			else 
				BackWPupFunctions::joblog($logtime,__('WARNING:','backwpup').' '.__('Can not set FTP Server to passiv!','backwpup'));
			
			if (ftp_alloc($ftp_conn_id, filesize($backupfile), $result)) //allocate file spase on ftp server
				BackWPupFunctions::joblog($logtime,__('Space successfully allocated on FTP server. Sending backup file.','backwpup'));
			else 
				BackWPupFunctions::joblog($logtime,__('WARNING:','backwpup').' '.__('Unable to allocate space on server. FTP Server said:','backwpup').' '.$result);
				
			if (ftp_put($ftp_conn_id, trailingslashit($jobs[$jobid]['ftpdir']).basename($backupfile), $backupfile, FTP_BINARY)) { //transvere file
				BackWPupFunctions::joblog($logtime,__('Backup File transfered to FTP Server:','backwpup').' '.trailingslashit($jobs[$jobid]['ftpdir']).basename($backupfile));
			} else {
				BackWPupFunctions::joblog($logtime,__('ERROR:','backwpup').' '.__('Can not tranfer backup to FTP server.','backwpup'));
			}
			if ($jobs[$jobid]['ftpmaxbackups']>0) { //Delete old backups
				$filelist=ftp_nlist($ftp_conn_id, trailingslashit($jobs[$jobid]['ftpdir']));
				foreach($filelist as $files) {
					if (!in_array(basename($files),array('.','..')) and false !== strpos(basename($files),'backwpup_'.$jobid.'_'))
						$backupfilelist[]=basename($files);
				}
				rsort($backupfilelist);
				$numdeltefiles=0;
				for ($i=$jobs[$jobid]['ftpmaxbackups'];$i<sizeof($backupfilelist);$i++) {
					if (ftp_delete($ftp_conn_id, trailingslashit($jobs[$jobid]['ftpdir']).$backupfilelist[$i])) //delte files on ftp
						$numdeltefiles++;
					else 
						BackWPupFunctions::joblog($logtime,__('ERROR:','backwpup').' '.__('Can not delete file on FTP Server:','backwpup').' '.trailingslashit($jobs[$jobid]['ftpdir']).$backupfilelist[$i]);
				}
				if ($numdeltefiles>0)
					BackWPupFunctions::joblog($logtime,$numdeltefiles.' '.__('files deleted on FTP Server:','backwpup'));
			}
		} else {
			BackWPupFunctions::joblog($logtime,__('ERROR:','backwpup').' '.__('Can not login to FTP server with user:','backwpup').' '.$jobs[$jobid]['ftpuser']);
		}
		ftp_close($ftp_conn_id); 
	} else {
		BackWPupFunctions::joblog($logtime,__('ERROR:','backwpup').' '.__('Can not connect to FTP server:','backwpup').' '.$jobs[$jobid]['ftphost']);
	}
}
?>
