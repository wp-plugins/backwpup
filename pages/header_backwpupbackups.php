<?PHP
if (!defined('ABSPATH')) 
	die();

//Create Table
$backwpup_listtable = new BackWPup_Backups_Table;

//get cuurent action
$doaction = $backwpup_listtable->current_action();
	
if (!empty($doaction)) {
	switch($doaction) {
	case 'delete': //Delete Backup archives
		check_admin_referer('bulk-backups');
		list($jobid,$dest)=explode(',',$_GET['jobdest']);
		$jobvalue=$jobvalue=backwpup_get_job_vars($jobid);
		foreach ($_GET['backupfiles'] as $backupfile) {
			if ($dest=='FOLDER') {
				if (is_file($backupfile))
					unlink($backupfile);
			} elseif ($dest=='S3') {
				if (!class_exists('AmazonS3'))
					require_once(realpath(dirname(__FILE__).'/../libs/aws/sdk.class.php'));
				if (class_exists('AmazonS3')) {
					if (!empty($jobvalue['awsAccessKey']) and !empty($jobvalue['awsSecretKey']) and !empty($jobvalue['awsBucket'])) {
						try {
							CFCredentials::set(array('backwpup' => array('key'=>$jobvalue['awsAccessKey'],'secret'=>$jobvalue['awsSecretKey'],'default_cache_config'=>'','certificate_authority'=>true),'@default' => 'backwpup'));
							$s3 = new AmazonS3();
							$s3->ssl_verification=false;
							$s3->delete_object($jobvalue['awsBucket'],$backupfile);
							unset($s3);
						} catch (Exception $e) {
							$backwpup_message.='Amazon S3: '.$e->getMessage().'<br />';
						}
					}
				}
			}  elseif ($dest=='GSTORAGE') {
				if (!class_exists('AmazonS3'))
					require_once(realpath(dirname(__FILE__).'/../libs/aws/sdk.class.php'));
				if (class_exists('AmazonS3')) {
					if (!empty($jobvalue['GStorageAccessKey']) and !empty($jobvalue['GStorageSecret']) and !empty($jobvalue['GStorageBucket'])) {
						try {
							CFCredentials::set(array('backwpup' => array('key'=>$jobvalue['GStorageAccessKey'],'secret'=>$jobvalue['GStorageSecret'],'default_cache_config'=>'','certificate_authority'=>true),'@default' => 'backwpup'));
							$gstorage = new AmazonS3();
							$gstorage->ssl_verification=false;
							$gstorage->set_hostname('commondatastorage.googleapis.com');
							$gstorage->allow_hostname_override(false);
							$gstorage->delete_object($jobvalue['GStorageBucket'],$backupfile);
							unset($gstorage);
						} catch (Exception $e) {
							$backwpup_message.=sprintf(__('GStorage API: %s','backwpup'),$e->getMessage()).'<br />';
						}
					}
				}
			}elseif ($dest=='MSAZURE') {
				if (!class_exists('Microsoft_WindowsAzure_Storage_Blob'))
					require_once(dirname(__FILE__).'/../libs/Microsoft/WindowsAzure/Storage/Blob.php');
				if (class_exists('Microsoft_WindowsAzure_Storage_Blob')) {
					if (!empty($jobvalue['msazureHost']) and !empty($jobvalue['msazureAccName']) and !empty($jobvalue['msazureKey']) and !empty($jobvalue['msazureContainer'])) {
						try {
							$storageClient = new Microsoft_WindowsAzure_Storage_Blob($jobvalue['msazureHost'],$jobvalue['msazureAccName'],$jobvalue['msazureKey']);
							$storageClient->deleteBlob($jobvalue['msazureContainer'],$backupfile);
							unset($storageClient);
						} catch (Exception $e) {
							$backwpup_message.='MS AZURE: '.$e->getMessage().'<br />';
						}
					}
				}
			} elseif ($dest=='DROPBOX') {
				require_once(realpath(dirname(__FILE__).'/../libs/dropbox.php'));
				if (!empty($jobvalue['dropetoken']) and !empty($jobvalue['dropesecret'])) {
					try {
						if ($jobvalue['droperoot']=='sandbox')
							$dropbox = new backwpup_Dropbox($backwpup_cfg['DROPBOX_SANDBOX_APP_KEY'], $backwpup_cfg['DROPBOX_SANDBOX_APP_SECRET'],false);
						else
							$dropbox = new backwpup_Dropbox($backwpup_cfg['DROPBOX_APP_KEY'], $backwpup_cfg['DROPBOX_APP_SECRET'],true);
						$dropbox->setOAuthTokens($jobvalue['dropetoken'],$jobvalue['dropesecret']);
						$dropbox->fileopsDelete($backupfile);
						unset($dropbox);
					} catch (Exception $e) {
						$backwpup_message.='DROPBOX: '.$e->getMessage().'<br />';
					}
				}	
			} elseif ($dest=='BOXNET') {
				if (!empty($jobvalue['boxnetauth'])) 
					wp_remote_get('http://www.box.net/api/1.0/rest?action=delete&target=file&target_id='.$backupfile.'&api_key='.$backwpup_cfg['BOXNET'].'&auth_token='.$jobvalue['boxnetauth']);
			} elseif ($dest=='SUGARSYNC') {
				if (!class_exists('SugarSync'))
					require_once (realpath(dirname(__FILE__).'/../libs/sugarsync.php'));
				if (class_exists('SugarSync')) {
					if (!empty($jobvalue['sugaruser']) and !empty($jobvalue['sugarpass'])) {
						try {
							$sugarsync = new SugarSync($jobvalue['sugaruser'],base64_decode($jobvalue['sugarpass']),BACKWPUP_SUGARSYNC_ACCESSKEY, BACKWPUP_SUGARSYNC_PRIVATEACCESSKEY);
							$sugarsync->delete(urldecode($backupfile));
							unset($sugarsync);
						} catch (Exception $e) {
							$backwpup_message.='SUGARSYNC: '.$e->getMessage().'<br />';
						}
					}
				}
			} elseif ($dest=='RSC') {
				if (!class_exists('CF_Authentication'))
					require_once(realpath(dirname(__FILE__).'/../libs/rackspace/cloudfiles.php'));
				if (class_exists('CF_Authentication')) {
					if (!empty($jobvalue['rscUsername']) and !empty($jobvalue['rscAPIKey']) and !empty($jobvalue['rscContainer'])) {
						try {
							$auth = new CF_Authentication($jobvalue['rscUsername'], $jobvalue['rscAPIKey']);
							$auth->ssl_use_cabundle();
							if ($auth->authenticate()) {
								$conn = new CF_Connection($auth);
								$conn->ssl_use_cabundle();
								$backwpupcontainer = $conn->get_container($jobvalue['rscContainer']);
								$backwpupcontainer->delete_object($backupfile);
							}
							unset($auth);
							unset($conn);
							unset($backwpupcontainer);
						} catch (Exception $e) {
							$backwpup_message.='RSC: '.$e->getMessage().'<br />';
						}
					}
				}
			} elseif ($dest=='FTP') {
				if (!empty($jobvalue['ftphost']) and !empty($jobvalue['ftpuser']) and !empty($jobvalue['ftppass']) and function_exists('ftp_connect')) {
					if (function_exists('ftp_ssl_connect') and $jobvalue['ftpssl']) { //make SSL FTP connection
						$ftp_conn_id = ftp_ssl_connect($jobvalue['ftphost'],$jobvalue['ftphostport'],10);
					} elseif (!$jobvalue['ftpssl']) { //make normal FTP conection if SSL not work
						$ftp_conn_id = ftp_connect($jobvalue['ftphost'],$jobvalue['ftphostport'],10);
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
						ftp_pasv($ftp_conn_id, $jobvalue['ftppasv']);
						ftp_delete($ftp_conn_id, $backupfile);
					} else {
						$backwpup_message.='FTP: '.__('Login failure!','backwpup').'<br />';
					}
				}
			}
		}
		delete_transient('backwpup_backups_chache');
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
			header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
			header("Status: 404 Not Found");
			die();
		}
		break;
	case 'downloads3': //Download S3 Backup
		check_admin_referer('download-backup');
		if (!class_exists('AmazonS3'))
			require_once(realpath(dirname(__FILE__).'/../libs/aws/sdk.class.php'));
		$jobid=$_GET['jobid'];
		$jobvalue=backwpup_get_job_vars($jobid);
		try {
			CFCredentials::set(array('backwpup' => array('key'=>$jobvalue['awsAccessKey'],'secret'=>$jobvalue['awsSecretKey'],'default_cache_config'=>'','certificate_authority'=>true),'@default' => 'backwpup'));
			$s3 = new AmazonS3();
			$s3file=$s3->get_object($jobvalue['awsBucket'], $_GET['file']);
		} catch (Exception $e) {
			die($e->getMessage());
		} 
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
	case 'downloaddropbox': //Download Dropbox Backup
		check_admin_referer('download-backup');
		require_once(realpath(dirname(__FILE__).'/../libs/dropbox.php'));
		$jobid=$_GET['jobid'];
		$jobvalue=backwpup_get_job_vars($jobid);
		try {
			if ($jobvalue['droperoot']=='sandbox')
				$dropbox = new backwpup_Dropbox($backwpup_cfg['DROPBOX_SANDBOX_APP_KEY'], $backwpup_cfg['DROPBOX_SANDBOX_APP_SECRET'],false);
			else
				$dropbox = new backwpup_Dropbox($backwpup_cfg['DROPBOX_APP_KEY'], $backwpup_cfg['DROPBOX_APP_SECRET'],true);
			$dropbox->setOAuthTokens($jobvalue['dropetoken'],$jobvalue['dropesecret']);
			$media=$dropbox->media($_GET['file']);
			if (!empty($media['url']))
				header("Location: ".$media['url']);
			die();
		} catch (Exception $e) {
			die($e->getMessage());
		} 
		break;
	case 'downloadsugarsync': //Download Dropbox Backup
		check_admin_referer('download-backup');
		if (!class_exists('SugarSync'))
			require_once(realpath(dirname(__FILE__).'/../libs/sugarsync.php'));
		$jobid=$_GET['jobid'];
		$jobvalue=backwpup_get_job_vars($jobid);
		try {
			$sugarsync = new SugarSync($jobvalue['sugaruser'],base64_decode($jobvalue['sugarpass']),$backwpup_cfg['SUGARSYNC_ACCESSKEY'], $backwpup_cfg['SUGARSYNC_PRIVATEACCESSKEY']);
			$response=$sugarsync->get(urldecode($_GET['file']));
			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Content-Type: ".(string)$response->mediaType);
			header("Content-Type: application/force-download");
			header("Content-Type: application/octet-stream");
			header("Content-Type: application/download");
			header("Content-Disposition: attachment; filename=".(string)$response->displayName.";");
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: ".(int)$response->size);
			echo $sugarsync->download(urldecode($_GET['file']));
			die();
		} catch (Exception $e) {
			die($e->getMessage());
		} 
		break;
	case 'downloadmsazure': //Download Microsoft Azure Backup
		check_admin_referer('download-backup');
		if (!class_exists('Microsoft_WindowsAzure_Storage_Blob'))
			require_once(dirname(__FILE__).'/../libs/Microsoft/WindowsAzure/Storage/Blob.php');
		$jobid=$_GET['jobid'];
		$jobvalue=backwpup_get_job_vars($jobid);
		try {
			$storageClient = new Microsoft_WindowsAzure_Storage_Blob($jobvalue['msazureHost'],$jobvalue['msazureAccName'],$jobvalue['msazureKey']);
			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			//header("Content-Type: ".$s3file->header->_info->content_type);
			header("Content-Type: application/force-download");
			header("Content-Type: application/octet-stream");
			header("Content-Type: application/download");
			header("Content-Disposition: attachment; filename=".basename($_GET['file']).";");
			header("Content-Transfer-Encoding: binary");
			//header("Content-Length: ".$s3file->header->_info->size_download);
			echo $storageClient->getBlobData($jobvalue['msazureContainer'], $_GET['file']);
			die();
		} catch (Exception $e) {
			die($e->getMessage());
		} 
		break;
	case 'downloadrsc': //Download RSC Backup
		check_admin_referer('download-backup');
		if (!class_exists('CF_Authentication'))
			require_once(realpath(plugin_dir_path(__FILE__).'/../libs/rackspace/cloudfiles.php'));
		$jobid=$_GET['jobid'];
		$jobvalue=backwpup_get_job_vars($jobid);
		try {
			$auth = new CF_Authentication($jobvalue['rscUsername'], $jobvalue['rscAPIKey']);
			$auth->ssl_use_cabundle();
			if ($auth->authenticate()) {
				$conn = new CF_Connection($auth);
				$conn->ssl_use_cabundle();
				$backwpupcontainer = $conn->get_container($jobvalue['rscContainer']);
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
				header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
				header("Status: 404 Not Found");
				die();
			}
		} catch (Exception $e) {
			die($e->getMessage());
		} 
		break;
	}
}
//Save per page
if (isset($_POST['screen-options-apply']) and isset($_POST['wp_screen_options']['option']) and isset($_POST['wp_screen_options']['value']) and $_POST['wp_screen_options']['option']=='backwpupbackups_per_page') {
	check_admin_referer( 'screen-options-nonce', 'screenoptionnonce' );
	global $current_user;
	if ($_POST['wp_screen_options']['value']>0 and $_POST['wp_screen_options']['value']<1000) {
		update_user_option($current_user->ID,'backwpupbackups_per_page',(int) $_POST['wp_screen_options']['value']);
		wp_redirect( remove_query_arg( array('pagenum', 'apage', 'paged'), wp_get_referer() ) );
		exit;
	}
}



//add Help
if (method_exists(get_current_screen(),'add_help_tab')) {
	get_current_screen()->add_help_tab( array(
		'id'      => 'overview',
		'title'   => __('Overview'),
		'content'	=>
		'<p>' .__('Here you see a list of backup files. Change the destination to jobname:destination to become a list of backups from other destinations and jobs. Then you can delete or download backup files.','backwpup') . '</p>'
	) );
}

add_screen_option( 'per_page', array('label' => __('Logs','backwpup'), 'default' => 20, 'option' =>'backwpupbackups_per_page') );

$backwpup_listtable->prepare_items();
?>