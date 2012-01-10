<?PHP
function backwpup_job_dest_boxnet() {
	global $backwpupjobrun,$backwpup_cfg;
	$backwpupjobrun['STEPTODO']=2+$backwpupjobrun['backupfilesize'];
	$backwpupjobrun['STEPDONE']=0;
	trigger_error(sprintf(__('%d. Try to sending backup file to Box.net...','backwpup'),$backwpupjobrun['DEST_BOXNET']['STEP_TRY']),E_USER_NOTICE);
	//get account info
	$raw_response=@wp_remote_get('http://www.box.net/api/1.0/rest?action=get_account_info&api_key='.$backwpup_cfg['BOXNET'].'&auth_token='.$backwpupjobrun['STATIC']['JOB']['boxnetauth']);
	if (!is_wp_error($raw_response) && 200 == wp_remote_retrieve_response_code($raw_response)) {
		$info = simplexml_load_string(wp_remote_retrieve_body($raw_response)); 
	} elseif(is_wp_error($raw_response)) {
		trigger_error(sprintf(__('Box.net API: %s','backwpup'),$raw_response->get_error_message()),E_USER_ERROR);
	}
	if ($info->status=='get_account_info_ok') {
		trigger_error(sprintf(__('Authed with Box.net from %s','backwpup'),$info->user->email),E_USER_NOTICE);
	} else {
		trigger_error(sprintf(__('Box.net API: %s !!!','backwpup'),$info->status),E_USER_ERROR);
	}
	//Check Quota
	$boxfreespase=(float)$info->user->space_amount-(float)$info->user->space_used;
	if ($backwpupjobrun['backupfilesize']>$boxfreespase) {
		trigger_error(__('No free space left on Box.nat Account!!!','backwpup'),E_USER_ERROR);
		$backwpupjobrun['STEPSDONE'][]='DEST_BOXNET'; //set done
		return;
	} else {
		trigger_error(sprintf(__('%s free on Box.net.','backwpup'),backwpup_format_bytes($boxfreespase)),E_USER_NOTICE);
	}
	//check filesize
	if ($backwpupjobrun['backupfilesize']>(float)$info->user->max_upload_size) {
		trigger_error(sprintf(__('Filesize to big max. %s allowed with your Box.net account!!!','backwpup'),backwpup_format_bytes((float)$info->user->max_upload_size)),E_USER_ERROR);
		$backwpupjobrun['STEPSDONE'][]='DEST_BOXNET'; //set done
		return;
	}
	
	backwpup_job_need_free_memory($backwpupjobrun['backupfilesize']*1.5);
	
	//create folder if needed
	$boxnetfolderid=0;
	if ($backwpupjobrun['STATIC']['JOB']['boxnetdir']!='/' and !empty($backwpupjobrun['STATIC']['JOB']['boxnetdir'])) {
		$folders=explode('/',trim($backwpupjobrun['STATIC']['JOB']['boxnetdir'],'/'));
		foreach ($folders as $folder) {
			$raw_response=@wp_remote_get('http://www.box.net/api/1.0/rest?action=create_folder&share=0&name='.urlencode($folder).'&parent_id='.$boxnetfolderid.'&api_key='.$backwpup_cfg['BOXNET'].'&auth_token='.$backwpupjobrun['STATIC']['JOB']['boxnetauth']);
			if (!is_wp_error($raw_response) && 200 == wp_remote_retrieve_response_code($raw_response)) {
				$folder = simplexml_load_string(wp_remote_retrieve_body($raw_response)); 
			} elseif(is_wp_error($raw_response)) {
				trigger_error(sprintf(__('Box.net API: %s','backwpup'),$raw_response->get_error_message()),E_USER_ERROR);
			}
			if ($folder->status!='create_ok' and $folder->status!='s_folder_exists') {
				trigger_error(sprintf(__('Box.net API on folder create: %s !!!','backwpup'),$folder->status),E_USER_ERROR);
				return;
			} else {
				$boxnetfolderid=(float)$folder->folder->folder_id;
			}
		}
	}
	//add prozess callback
	add_action('http_api_curl','backwpup_job_curl_progressfunction');
	//post the file
	trigger_error(__('Upload to Box.net now started... ','backwpup'),E_USER_NOTICE);
	$boundary=md5(time());
	$post="--".$boundary."\r\n";
	$post.="content-disposition: form-data; name=\"new_file1\"; filename=\"".rawurlencode($backwpupjobrun['STATIC']['backupfile'])."\"\r\n";
	//$post.="Content-type: application/octet-stream\r\n";
	$post.="\r\n";
	$post.=file_get_contents($backwpupjobrun['STATIC']['JOB']['backupdir'].$backwpupjobrun['STATIC']['backupfile']);
	$post.="\r\n--".$boundary."--\r\n";
	
	$raw_response=@wp_remote_post('https://upload.box.net/api/1.0/upload/'.$backwpupjobrun['STATIC']['JOB']['boxnetauth'].'/'.$boxnetfolderid,array('sslverify' => false, 'body'=>$post,'timeout' => 300,'headers'=>array('Content-Type'=>'multipart/form-data, boundary='.$boundary))); 
	if (!is_wp_error($raw_response) && 200 == wp_remote_retrieve_response_code($raw_response)) {
		$response = simplexml_load_string(wp_remote_retrieve_body($raw_response)); 
	} elseif(is_wp_error($raw_response)) {
		trigger_error(sprintf(__('Box.net API: %s','backwpup'),$raw_response->get_error_message()),E_USER_ERROR);
	}
	
	if ($response->status=='upload_ok') {
		backwpup_update_option('job_'.$backwpupjobrun['STATIC']['JOB']['jobid'],'lastbackupdownloadurl','https://www.box.net/api/1.0/download/'.$backwpupjobrun['STATIC']['JOB']['boxnetauth'].'/'.$response->files->file->attributes()->id);
		$backwpupjobrun['STEPDONE']++;
		$backwpupjobrun['STEPSDONE'][]='DEST_BOXNET'; //set done
		trigger_error(sprintf(__('Backup transferred to %s','backwpup'),'https://www.box.net/'.$backwpupjobrun['STATIC']['JOB']['boxnetdir'].$backwpupjobrun['STATIC']['backupfile']),E_USER_NOTICE);
	} else {
		trigger_error(sprintf(__('Error on transfere backup to box.net: %s','backwpup'),$response->status),E_USER_ERROR);
		return;
	}
	//remove prozess callback
	remove_action('http_api_curl','backwpup_job_curl_progressfunction');
	
	if ($backwpupjobrun['STATIC']['JOB']['boxnetbackups']>0) { //Delete old backups
		$backupfilelist=array();
		$raw_response=@wp_remote_get('http://www.box.net/api/1.0/rest?action=get_account_tree&folder_id='.$boxnetfolderid.'&api_key='.$backwpup_cfg['BOXNET'].'&auth_token='.$backwpupjobrun['STATIC']['JOB']['boxnetauth'].'&params[]=nozip&params[]=simple&params[]=onelevel');
		if (!is_wp_error($raw_response) && 200 == wp_remote_retrieve_response_code($raw_response)) {
			$metadata = simplexml_load_string(wp_remote_retrieve_body($raw_response)); 
		} elseif(is_wp_error($raw_response)) {
			trigger_error(sprintf(__('Box.net API: %s','backwpup'),$raw_response->get_error_message()),E_USER_ERROR);
		}
		if ($metadata->status=='listing_ok') {
			foreach ($metadata->tree->folder->files->file as $data) {
				$file=(string)$data->attributes()->file_name;
				$fileid=(float)$data->attributes()->id;
				$modified=(float)$data->attributes()->updated;
				if ($backwpupjobrun['STATIC']['JOB']['fileprefix'] == substr($file,0,strlen($backwpupjobrun['STATIC']['JOB']['fileprefix'])))
					$backupfilelist[$modified]=$fileid;
			}
		}
		if (count($backupfilelist)>$backwpupjobrun['STATIC']['JOB']['boxnetbackups']) {
			$numdeltefiles=0;
			while ($file=array_shift($backupfilelist)) {
				if (count($backupfilelist)<$backwpupjobrun['STATIC']['JOB']['boxnetbackups'])
					break;
				$raw_response=@wp_remote_get('http://www.box.net/api/1.0/rest?action=delete&target=file&target_id='.$file.'&api_key='.$backwpup_cfg['BOXNET'].'&auth_token='.$backwpupjobrun['STATIC']['JOB']['boxnetauth']);
				if (!is_wp_error($raw_response) && 200 == wp_remote_retrieve_response_code($raw_response)) {
					$response = simplexml_load_string(wp_remote_retrieve_body($raw_response)); 
				}				
				if ($response->status=='s_delete_node')
					$numdeltefiles++;
				else
					trigger_error(sprintf(__('Error on delete file with id %s on Box.net','backwpup'),$file),E_USER_ERROR);
			}
			if ($numdeltefiles>0)
				trigger_error(sprintf(_n('One file deleted on Box.net','%d files deleted on Box.net',$numdeltefiles,'backwpup'),$numdeltefiles),E_USER_NOTICE);
		}
	}
	$backwpupjobrun['STEPDONE']++;
}
?>