<?PHP
// don't load directly
if ( !defined('ABSPATH') )
	die('-1');

function backwpup_job_operations($action) {
	switch($action) {
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
				$jobsexport[$jobid]=$jobs[$jobid];
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
	case 'clear': //Abort Job
		$jobid = (int) $_GET['jobid'];
		check_admin_referer('clear-job_'.$jobid);
		$jobs=get_option('backwpup_jobs');
		$cfg=get_option('backwpup'); //Load Settings

		if (is_file($jobs[$jobid]['logfile'])) {
			$logheader=backwpup_read_logheader($jobs[$jobid]['logfile']); //read waring count from log header
			$fd=fopen($jobs[$jobid]['logfile'],"a+");
			fwrite($fd,"<span style=\"background-color:c3c3c3;\" title=\"[Line: ".__LINE__."|File: ".basename(__FILE__)."\">".date_i18n('Y-m-d H:i.s').":</span> <span style=\"background-color:red;\">".__('[ERROR]','backwpup')." ".__('Backup Cleand by User!!!','backwpup')."</span><br />\n");
			fwrite($fd,"</body>\n</html>\n");
			fclose($fd);
			$logheader['errors']=$logheader['errors']+1;
			//write new log header
			$fd=fopen($jobs[$jobid]['logfile'],"r+");
			while (!feof($fd)) {
				$line=fgets($fd);
				if (stripos($line,"<meta name=\"backwpup_errors\"") !== false) {
					fseek($fd,$filepos);
					fwrite($fd,str_pad("<meta name=\"backwpup_errors\" content=\"".$logheader['errors']."\" />",100)."\n");
					break;
				}
				$filepos=ftell($fd);
			}
			fclose($fd);
		}
		if ($cfg['gzlogs'] and function_exists('gzopen') and file_exists($jobs[$jobid]['logfile'])) {
			$fd=fopen($jobs[$jobid]['logfile'],'r');
			$zd=gzopen($jobs[$jobid]['logfile'].'.gz','w9');
			while (!feof($fd)) {
				gzwrite($zd,fread($fd,4096));
			}
			gzclose($zd);
			fclose($fd);
			unlink($jobs[$jobid]['logfile']);
			$jobs[$jobid]['logfile']=$jobs[$jobid]['logfile'].'.gz';
		}	
		$jobs[$jobid]['cronnextrun']=backwpup_cron_next($jobs[$jobid]['cron']);
		$jobs[$jobid]['stoptime']=current_time('timestamp');
		$jobs[$jobid]['lastrun']=$jobs[$jobid]['starttime'];
		$jobs[$jobid]['lastruntime']=$jobs[$jobid]['stoptime']-$jobs[$jobid]['starttime'];
		$jobs[$jobid]['starttime']='';
		$jobs[$jobid]['logfile']='';
		$jobs[$jobid]['lastlogfile']=$jobs[$jobid]['logfile'];
		update_option('backwpup_jobs',$jobs);
		break;
	}
}

function backwpup_log_operations($action) {
	switch($action) {
	case 'delete': //Delete Log
		$cfg=get_option('backwpup'); //Load Settings
		if (is_array($_REQUEST['logfiles'])) {
			check_admin_referer('bulk-logs');
			$num=0;
			foreach ($_REQUEST['logfiles'] as $logfile) {
				if (is_file($cfg['dirlogs'].'/'.$logfile))
					unlink($cfg['dirlogs'].'/'.$logfile);
				$num++;
			}
		}
		break;
	case 'download': //Download Backup
		check_admin_referer('download-backup_'.basename($_GET['file']));
		if (is_file($_GET['file'])) {
			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Content-Type: application/force-download");
			header("Content-Type: application/octet-stream");
			header("Content-Type: application/download");
			header("Content-Disposition: attachment; filename=".basename($_GET['file']).";");
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: ".filesize($_GET['file']));
			@readfile($_GET['file']);
			die();
		} else {
			header('HTTP/1.0 404 Not Found');
			die(__('File does not exist.', 'backwpup'));
		}
		break;
	}
}

function backwpup_backups_operations($action) {
	switch($action) {
	case 'delete': //Delete Backup archives
		$deletebackups=array();
		if (is_array($_REQUEST['backupfiles'])) {
			check_admin_referer('bulk-backups');
			$i=0;
			foreach ($_REQUEST['backupfiles'] as $backupfile) {
				list($deletebackups[$i]['file'],$deletebackups[$i]['jobid'],$deletebackups[$i]['type'])=explode(':',$backupfile,3);
				$i++;
			}
		}

		if(empty($deletebackups)) {
			$_REQUEST['action']='backups';
			break;
		}

		$jobs=get_option('backwpup_jobs'); //Load jobs
		if (extension_loaded('curl') or @dl(PHP_SHLIB_SUFFIX == 'so' ? 'curl.so' : 'php_curl.dll')) {
				if (!class_exists('CFRuntime'))
					require_once(dirname(__FILE__).'/libs/aws/sdk.class.php');
				if (!class_exists('CF_Authentication'))
					require_once(plugin_dir_path(__FILE__).'libs/rackspace/cloudfiles.php');
		}

		$num=0;
		foreach ($deletebackups as $backups) {
			$jobvalue=backwpup_check_job_vars($jobs[$backups['jobid']],$backups['jobid']); //Check job values
			if ($backups['type']=='FOLDER') {
				if (is_file($backups['file']))
					unlink($backups['file']);
			} elseif ($backups['type']=='S3') {
				if (class_exists('AmazonS3')) {
					if (!empty($jobvalue['awsAccessKey']) and !empty($jobvalue['awsSecretKey']) and !empty($jobvalue['awsBucket'])) {
						$s3 = new AmazonS3($jobvalue['awsAccessKey'], $jobvalue['awsSecretKey']);
						$s3->delete_object($jobvalue['awsBucket'],$backups['file']);
					}
				}
			} elseif ($backups['type']=='RSC') {
				if (class_exists('CF_Authentication')) {
					if (!empty($jobvalue['rscUsername']) and !empty($jobvalue['rscAPIKey']) and !empty($jobvalue['rscContainer'])) {
						$auth = new CF_Authentication($jobvalue['rscUsername'], $jobvalue['rscAPIKey']);
						$auth->ssl_use_cabundle();
						if ($auth->authenticate()) {
							$conn = new CF_Connection($auth);
							$conn->ssl_use_cabundle();
							$backwpupcontainer = $conn->get_container($jobvalue['rscContainer']);
							$backwpupcontainer->delete_object($backups['file']);
						}
					}
				}
			} elseif ($backups['type']=='FTP') {
				if (!empty($jobvalue['ftphost']) and !empty($jobvalue['ftpuser']) and !empty($jobvalue['ftppass'])) {
					$ftpport=21;
					$ftphost=$jobvalue['ftphost'];
					if (false !== strpos($jobvalue['ftphost'],':')) //look for port
						list($ftphost,$ftpport)=explode(':',$jobvalue,2);

					if (function_exists('ftp_ssl_connect') and $jobvalue['ftpssl']) { //make SSL FTP connection
						$ftp_conn_id = ftp_ssl_connect($ftphost,$ftpport,10);
					} elseif (!$jobvalue['ftpssl']) { //make normal FTP conection if SSL not work
						$ftp_conn_id = ftp_connect($ftphost,$ftpport,10);
					}
					$loginok=false;
					if ($ftp_conn_id) {
						//FTP Login
						if (@ftp_login($ftp_conn_id, $jobvalue['ftpuser'], base64_decode($jobvalue['ftppass']))) {
							$loginok=true;
						} else { //if PHP ftp login don't work use raw login
							ftp_raw($ftp_conn_id,'USER '.$jobvalue['ftpuser']);
							$return=ftp_raw($ftp_conn_id,'PASS '.base64_decode($jobvalue['ftppass']));
							if (substr(trim($return[0]),0,3)<=400)
								$loginok=true;
						}
					}
					if ($loginok) {
						ftp_pasv($ftp_conn_id, true);
						ftp_delete($ftp_conn_id, $backups['file']);
					}
				}
			}
			$num++;
		}
		update_option('backwpup_backups_chache',backwpup_get_backup_files());
		break;
	case 'download': //Download Backup
		check_admin_referer('download-backup');
		if (is_file($_GET['file'])) {
			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Content-Type: application/force-download");
			header("Content-Type: application/octet-stream");
			header("Content-Type: application/download");
			header("Content-Disposition: attachment; filename=".basename($_GET['file']).";");
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: ".filesize($_GET['file']));
			@readfile($_GET['file']);
			die();
		} else {
			header('HTTP/1.0 404 Not Found');
			die(__('File does not exist.', 'backwpup'));
		}
		break;
	case 'downloads3': //Download S3 Backup
		check_admin_referer('download-backup');
		require_once(dirname(__FILE__).'/libs/aws/sdk.class.php');
		$jobs=get_option('backwpup_jobs');
		$jobid=$_GET['jobid'];
		$s3 = new AmazonS3($jobs[$jobid]['awsAccessKey'], $jobs[$jobid]['awsSecretKey']);
		$s3file=$s3->get_object($jobs[$jobid]['awsBucket'], $_GET['file']);
		if ($s3file->status==200) {
			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Content-Type: ".$s3file->header->_info->content_type);
			header("Content-Type: application/force-download");
			header("Content-Type: application/octet-stream");
			header("Content-Type: application/download");
			header("Content-Disposition: attachment; filename=".basename($_GET['file']).";");
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: ".$s3file->header->_info->size_download);
			echo $s3file->body;
			die();
		} else {
			header('HTTP/1.0 '.$s3file->status.' Not Found');
			die();
		}
		break;
	case 'downloadrsc': //Download RSC Backup
		check_admin_referer('download-backup');
		require_once(plugin_dir_path(__FILE__).'libs/rackspace/cloudfiles.php');
		$jobs=get_option('backwpup_jobs');
		$jobid=$_GET['jobid'];
		$auth = new CF_Authentication($jobs[$jobid]['rscUsername'], $jobs[$jobid]['rscAPIKey']);
		$auth->ssl_use_cabundle();
		if ($auth->authenticate()) {
			$conn = new CF_Connection($auth);
			$conn->ssl_use_cabundle();
			$backwpupcontainer = $conn->get_container($jobs[$jobid]['rscContainer']);
			$backupfile=$backwpupcontainer->get_object($_GET['file']);
			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Content-Type: ".$backupfile->content_type);
			header("Content-Type: application/force-download");
			header("Content-Type: application/octet-stream");
			header("Content-Type: application/download");
			header("Content-Disposition: attachment; filename=".basename($_GET['file']).";");
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: ".$backupfile->content_length);
			$output = fopen("php://output", "w");
			$backupfile->stream($output);
			fclose($output);
			die();
		} else {
			header('HTTP/1.0 404 Not Found');
			die(__('File does not exist.', 'backwpup'));
		}
		break;
	}
}

function backwpup_save_settings() {
	check_admin_referer('backwpup-cfg');
	$cfg=get_option('backwpup'); //Load Settings
	$cfg['mailsndemail']=sanitize_email($_POST['mailsndemail']);
	$cfg['mailsndname']=$_POST['mailsndname'];
	$cfg['mailmethod']=$_POST['mailmethod'];
	$cfg['mailsendmail']=untrailingslashit(str_replace('//','/',str_replace('\\','/',stripslashes($_POST['mailsendmail']))));
	$cfg['mailsecure']=$_POST['mailsecure'];
	$cfg['mailhost']=$_POST['mailhost'];
	$cfg['mailuser']=$_POST['mailuser'];
	$cfg['mailpass']=base64_encode($_POST['mailpass']);
	$cfg['memorylimit']=$_POST['memorylimit'];
	$cfg['disablewpcron']=$_POST['disablewpcron']==1 ? true : false;
	$cfg['logfilelist']=$_POST['logfilelist']==1 ? true : false;
	$cfg['maxlogs']=abs((int)$_POST['maxlogs']);
	$cfg['gzlogs']=$_POST['gzlogs']==1 ? true : false;
	$cfg['dirlogs']=trailingslashit(str_replace('//','/',str_replace('\\','/',stripslashes(trim($_POST['dirlogs'])))));
	$cfg['dirtemp']=trailingslashit(str_replace('//','/',str_replace('\\','/',stripslashes(trim($_POST['dirtemp'])))));
	//set def. folders
	if (empty($cfg['dirtemp']) or $cfg['dirtemp']=='/')
		$cfg['dirtemp']=str_replace('\\','/',trailingslashit(backwpup_get_upload_dir()));
	if (empty($cfg['dirlogs']) or $cfg['dirlogs']=='/') {
			$rand = substr( md5( md5( SECURE_AUTH_KEY ) ), -5 );
			$cfg['dirlogs']=str_replace('\\','/',trailingslashit(WP_CONTENT_DIR)).'backwpup-'.$rand.'-logs/';
	}

	if (update_option('backwpup',$cfg))
		$backwpup_message=__('Settings saved', 'backwpup');
	return $backwpup_message;
}

function backwpup_save_job() { //Save Job settings
	$jobid = (int) $_POST['jobid'];
	check_admin_referer('edit-job');
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

	if ($jobs[$jobid]['type']!=$_POST['type']) // set type to save
		$savetype=explode('+',$jobs[$jobid]['type']);
	else
		$savetype=$_POST['type'];

	$jobs[$jobid]['type']= implode('+',(array)$_POST['type']);
	$jobs[$jobid]['name']= esc_html($_POST['name']);
	$jobs[$jobid]['activated']= $_POST['activated']==1 ? true : false;
		if ($_POST['cronminutes'][0]=='*' or empty($_POST['cronminutes'])) {
			if (!empty($_POST['cronminutes'][1]))
				$_POST['cronminutes']=array('*/'.$_POST['cronminutes'][1]);
			else
				$_POST['cronminutes']=array('*');
		}
		if ($_POST['cronhours'][0]=='*' or empty($_POST['cronhours'])) {
			if (!empty($_POST['cronhours'][1]))
				$_POST['cronhours']=array('*/'.$_POST['cronhours'][1]);
			else
				$_POST['cronhours']=array('*');
		}
		if ($_POST['cronmday'][0]=='*' or empty($_POST['cronmday'])) {
			if (!empty($_POST['cronmday'][1]))
				$_POST['cronmday']=array('*/'.$_POST['cronmday'][1]);
			else
				$_POST['cronmday']=array('*');
		}
		if ($_POST['cronmon'][0]=='*' or empty($_POST['cronmon'])) {
			if (!empty($_POST['cronmon'][1]))
				$_POST['cronmon']=array('*/'.$_POST['cronmon'][1]);
			else
				$_POST['cronmon']=array('*');
		}
		if ($_POST['cronwday'][0]=='*' or empty($_POST['cronwday'])) {
			if (!empty($_POST['cronwday'][1]))
				$_POST['cronwday']=array('*/'.$_POST['cronwday'][1]);
			else
				$_POST['cronwday']=array('*');
		}
	$jobs[$jobid]['cron']=implode(",",$_POST['cronminutes']).' '.implode(",",$_POST['cronhours']).' '.implode(",",$_POST['cronmday']).' '.implode(",",$_POST['cronmon']).' '.implode(",",$_POST['cronwday']);
	$jobs[$jobid]['cronnextrun']=backwpup_cron_next($jobs[$jobid]['cron']);
	$jobs[$jobid]['mailaddresslog']=sanitize_email($_POST['mailaddresslog']);
	$jobs[$jobid]['mailerroronly']= $_POST['mailerroronly']==1 ? true : false;
	$jobs[$jobid]['dbexclude']=(array)$_POST['dbexclude'];
	$jobs[$jobid]['dbshortinsert']=$_POST['dbshortinsert']==1 ? true : false;
	$jobs[$jobid]['maintenance']= $_POST['maintenance']==1 ? true : false;
	$jobs[$jobid]['fileexclude']=stripslashes($_POST['fileexclude']);
	$jobs[$jobid]['dirinclude']=stripslashes($_POST['dirinclude']);
	$jobs[$jobid]['backuproot']= $_POST['backuproot']==1 ? true : false;
	$jobs[$jobid]['backuprootexcludedirs']=(array)$_POST['backuprootexcludedirs'];
	$jobs[$jobid]['backupcontent']= $_POST['backupcontent']==1 ? true : false;
	$jobs[$jobid]['backupcontentexcludedirs']=(array)$_POST['backupcontentexcludedirs'];
	$jobs[$jobid]['backupplugins']= $_POST['backupplugins']==1 ? true : false;
	$jobs[$jobid]['backuppluginsexcludedirs']=(array)$_POST['backuppluginsexcludedirs'];
	$jobs[$jobid]['backupthemes']= $_POST['backupthemes']==1 ? true : false;
	$jobs[$jobid]['backupthemesexcludedirs']=(array)$_POST['backupthemesexcludedirs'];
	$jobs[$jobid]['backupuploads']= $_POST['backupuploads']==1 ? true : false;
	$jobs[$jobid]['backupuploadsexcludedirs']=(array)$_POST['backupuploadsexcludedirs'];
	$jobs[$jobid]['fileprefix']=$_POST['fileprefix'];
	$jobs[$jobid]['fileformart']=$_POST['fileformart'];
	$jobs[$jobid]['mailefilesize']=(float)$_POST['mailefilesize'];
	$jobs[$jobid]['backupdir']=stripslashes($_POST['backupdir']);
	$jobs[$jobid]['maxbackups']=(int)$_POST['maxbackups'];
	$jobs[$jobid]['ftphost']=$_POST['ftphost'];
	$jobs[$jobid]['ftpuser']=$_POST['ftpuser'];
	$jobs[$jobid]['ftppass']=base64_encode($_POST['ftppass']);
	$jobs[$jobid]['ftpdir']=stripslashes($_POST['ftpdir']);
	$jobs[$jobid]['ftpmaxbackups']=(int)$_POST['ftpmaxbackups'];
	$jobs[$jobid]['ftpssl']= $_POST['ftpssl']==1 ? true : false;
	$jobs[$jobid]['ftppasv']= $_POST['ftppasv']==1 ? true : false;
	$jobs[$jobid]['dropemail']=$_POST['dropemail'];
	$jobs[$jobid]['dropepass']=base64_encode($_POST['dropepass']);
	$jobs[$jobid]['dropedir']=$_POST['dropedir'];
	$jobs[$jobid]['awsAccessKey']=$_POST['awsAccessKey'];
	$jobs[$jobid]['awsSecretKey']=$_POST['awsSecretKey'];
	$jobs[$jobid]['awsrrs']= $_POST['awsrrs']==1 ? true : false;
	$jobs[$jobid]['awsBucket']=$_POST['awsBucket'];
	$jobs[$jobid]['awsdir']=stripslashes($_POST['awsdir']);
	$jobs[$jobid]['awsmaxbackups']=(int)$_POST['awsmaxbackups'];
	$jobs[$jobid]['rscUsername']=$_POST['rscUsername'];
	$jobs[$jobid]['rscAPIKey']=$_POST['rscAPIKey'];
	$jobs[$jobid]['rscContainer']=$_POST['rscContainer'];
	$jobs[$jobid]['rscdir']=stripslashes($_POST['rscdir']);
	$jobs[$jobid]['rscmaxbackups']=(int)$_POST['rscmaxbackups'];
	$jobs[$jobid]['mailaddress']=sanitize_email($_POST['mailaddress']);
	//unset old vars
	unset($jobs[$jobid]['scheduletime']);
	unset($jobs[$jobid]['scheduleintervaltype']);
	unset($jobs[$jobid]['scheduleintervalteimes']);
	unset($jobs[$jobid]['scheduleinterval']);

	$jobs[$jobid]=backwpup_check_job_vars($jobs[$jobid],$jobid); //check vars and set def.

	if (!empty($_POST['newawsBucket']) and !empty($_POST['awsAccessKey']) and !empty($_POST['awsSecretKey'])) { //create new s3 bucket if needed
		if (!class_exists('CFRuntime'))
			require_once(dirname(__FILE__).'/libs/aws/sdk.class.php');
		$s3 = new AmazonS3($_POST['awsAccessKey'], $_POST['awsSecretKey']);
		$s3->create_bucket($_POST['newawsBucket'], $_POST['awsRegion']);
		$jobs[$jobid]['awsBucket']=$_POST['newawsBucket'];
	}

	if (!empty($_POST['rscUsername']) and !empty($_POST['rscAPIKey']) and !empty($_POST['newrscContainer'])) { //create new Rackspase Container if needed
		if (!class_exists('CF_Authentication'))
			require_once(plugin_dir_path(__FILE__).'libs/rackspace/cloudfiles.php');
		try {
			$auth = new CF_Authentication($_POST['rscUsername'], $_POST['rscAPIKey']);
			if ($auth->authenticate()) {
				$conn = new CF_Connection($auth);
				$public_container = $conn->create_container($_POST['newrscContainer']);
				$public_container->make_private();
			}
		} catch (Exception $e) {
			$backwpup_message=__($e->getMessage(),'backwpup');
		}
	}

	//save chages
	update_option('backwpup_jobs',$jobs);
	$_POST['jobid']=$jobid;
	$backwpup_message.=str_replace('%1',$jobs[$jobid]['name'],__('Job \'%1\' changes saved.', 'backwpup')).' <a href="admin.php?page=BackWPup">'.__('Jobs overview.', 'backwpup').'</a>';
	return $backwpup_message;
}
?>