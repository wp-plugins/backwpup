<?PHP
function backwpup_job_dest_boxnet_sync() {
	global $backwpupjobrun,$backwpup_cfg;
	//get files
	$filelist=backwpup_get_option('WORKING','FILELIST'); //get file list
	$folderlist=backwpup_get_option('WORKING','FOLDERLIST'); //get folder list
	$backwpupjobrun['STEPTODO']=count($filelist);
	$backwpupjobrun['STEPDONE']=0;
	trigger_error(sprintf(__('%d. Try to sync files with Box.net...','backwpup'),$backwpupjobrun['DEST_BOXNET_SYNC']['STEP_TRY']),E_USER_NOTICE);

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
	trigger_error(sprintf(__('%s free on Box.net.','backwpup'),backwpup_format_bytes($boxfreespase)),E_USER_NOTICE);

	
	
	//check filesize
	if ($backwpupjobrun['backupfilesize']>(float)$info->user->max_upload_size) {
		trigger_error(sprintf(__('Filesize to big max. %s allowed with your Box.net account!!!','backwpup'),backwpup_format_bytes((float)$info->user->max_upload_size)),E_USER_ERROR);
		$backwpupjobrun['STEPSDONE'][]='DEST_BOXNET_SYNC'; //set done
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
	
	
	trigger_error(__('Get remote file and folder list...','backwpup'),E_USER_NOTICE);
	$raw_response=@wp_remote_get('http://www.box.net/api/1.0/rest?action=get_account_tree&folder_id='.$boxnetfolderid.'&api_key='.$backwpup_cfg['BOXNET'].'&auth_token='.$backwpupjobrun['STATIC']['JOB']['boxnetauth'].'&params[]=nozip&params[]=simple');
	if (!is_wp_error($raw_response) && 200 == wp_remote_retrieve_response_code($raw_response)) {
		$metadata = simplexml_load_string(wp_remote_retrieve_body($raw_response)); 
	} elseif(is_wp_error($raw_response)) {
		trigger_error(sprintf(__('Box.net API: %s','backwpup'),$raw_response->get_error_message()),E_USER_ERROR);
	}
	if ($metadata->status=='listing_ok') {
		trigger_error(json_encode($metadata),E_USER_WARNING);
		foreach ($metadata->tree->folder->files->file as $data) {
			$file=(string)$data->attributes()->file_name;
			$fileid=(float)$data->attributes()->id;
			$modified=(float)$data->attributes()->updated;
			if ($backwpupjobrun['STATIC']['JOB']['fileprefix'] == substr($file,0,strlen($backwpupjobrun['STATIC']['JOB']['fileprefix'])))
				$backupfilelist[$modified]=$fileid;
		}
	}
	
	
	
	//add prozess callback
	add_action('http_api_curl','backwpup_job_curl_progressfunction');


	

	
	if (count($filelist)==0) {
		$backwpupjobrun['STEPSDONE'][]='DEST_BOXNET_SYNC'; //set done
		remove_action('http_api_curl','backwpup_job_curl_progressfunction');
	}

}
?>