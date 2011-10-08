<?PHP
function backwpup_job_file_list() {
	global $backwpupjobrun,$tempfilelist;
	//Make filelist
	trigger_error(sprintf(__('%d. try for make list of files to backup....','backwpup'),$backwpupjobrun['WORKING']['FILE_LIST']['STEP_TRY']),E_USER_NOTICE);
	$backwpupjobrun['WORKING']['STEPTODO']=2;

	//Check free memory for file list
	backwpup_job_need_free_memory(2097152); //2MB free memory for filelist
	//empty filelist
	$tempfilelist=array();
	//exlude of job
	$backwpupjobrun['WORKING']['FILEEXCLUDES']=explode(',',trim($backwpupjobrun['STATIC']['JOB']['fileexclude']));
	$backwpupjobrun['WORKING']['FILEEXCLUDES'][]='.tmp';  //do not backup .tmp files
	$backwpupjobrun['WORKING']['FILEEXCLUDES']=array_unique($backwpupjobrun['WORKING']['FILEEXCLUDES']);

	//File list for blog folders
	if ($backwpupjobrun['STATIC']['JOB']['backuproot'])
		_backwpup_job_file_list(trailingslashit(str_replace('\\','/',ABSPATH)),100,array_merge($backwpupjobrun['STATIC']['JOB']['backuprootexcludedirs'],backwpup_get_exclude_wp_dirs(trailingslashit(str_replace('\\','/',ABSPATH)))));
	if ($backwpupjobrun['STATIC']['JOB']['backupcontent'])
		_backwpup_job_file_list(trailingslashit(str_replace('\\','/',WP_CONTENT_DIR)),100,array_merge($backwpupjobrun['STATIC']['JOB']['backupcontentexcludedirs'],backwpup_get_exclude_wp_dirs(trailingslashit(str_replace('\\','/',WP_CONTENT_DIR)))));
	if ($backwpupjobrun['STATIC']['JOB']['backupplugins'])
		_backwpup_job_file_list(trailingslashit(str_replace('\\','/',WP_PLUGIN_DIR)),100,array_merge($backwpupjobrun['STATIC']['JOB']['backuppluginsexcludedirs'],backwpup_get_exclude_wp_dirs(trailingslashit(str_replace('\\','/',WP_PLUGIN_DIR)))));
	if ($backwpupjobrun['STATIC']['JOB']['backupthemes'])
		_backwpup_job_file_list(trailingslashit(str_replace('\\','/',trailingslashit(WP_CONTENT_DIR).'themes/')),100,array_merge($backwpupjobrun['STATIC']['JOB']['backupthemesexcludedirs'],backwpup_get_exclude_wp_dirs(trailingslashit(str_replace('\\','/',trailingslashit(WP_CONTENT_DIR).'themes/')))));
	if ($backwpupjobrun['STATIC']['JOB']['backupuploads'])
		_backwpup_job_file_list(backwpup_get_upload_dir(),100,array_merge($backwpupjobrun['STATIC']['JOB']['backupuploadsexcludedirs'],backwpup_get_exclude_wp_dirs(backwpup_get_upload_dir())));

	//include dirs
	if (!empty($backwpupjobrun['STATIC']['JOB']['dirinclude'])) {
		$dirinclude=explode(',',$backwpupjobrun['STATIC']['JOB']['dirinclude']);
		$dirinclude=array_unique($dirinclude);
		//Crate file list for includes
		foreach($dirinclude as $dirincludevalue) {
			if (is_dir($dirincludevalue))
				_backwpup_job_file_list($dirincludevalue,100);
		}
	}
	$tempfilelist=array_unique($tempfilelist); //all files only one time in list
	sort($tempfilelist);
	$backwpupjobrun['WORKING']['STEPDONE']=1; //Step done
	backwpup_job_update_working_data();

	//Check abs path
	if (trailingslashit(str_replace('\\','/',ABSPATH))=='/' or trailingslashit(str_replace('\\','/',ABSPATH))=='')
		$removepath='';
	else
		$removepath=trailingslashit(str_replace('\\','/',ABSPATH));
	//make file list
	$filelist=array();
	for ($i=0; $i<count($tempfilelist); $i++) {
		$filestat=stat($tempfilelist[$i]);
		$backwpupjobrun['WORKING']['ALLFILESIZE']+=$filestat['size'];
		$outfile=str_replace($removepath,'',$tempfilelist[$i]);
		if (substr($outfile,0,1)=='/') //remove first /
			$outfile=substr($outfile,1);
		$filelist[]=array('FILE'=>$tempfilelist[$i],'OUTFILE'=>$outfile,'SIZE'=>$filestat['size'],'ATIME'=>$filestat['atime'],'MTIME'=>$filestat['mtime'],'CTIME'=>$filestat['ctime'],'UID'=>$filestat['uid'],'GID'=>$filestat['gid'],'MODE'=>$filestat['mode']);
	}
	backwpup_job_add_file($filelist); //add files to list
	$backwpupjobrun['WORKING']['STEPDONE']=2;
	$backwpupjobrun['WORKING']['STEPSDONE'][]='FILE_LIST'; //set done
	unset($tempfilelist);

	$filelist=get_transient('backwpup_job_filelist'); //get files
	if (empty($filelist)) {
		trigger_error(__('No files to backup','backwpup'),E_USER_ERROR);
	} else {
		trigger_error(sprintf(__('%1$d files with %2$s to backup','backwpup'),count($filelist),backwpup_formatBytes($backwpupjobrun['WORKING']['ALLFILESIZE'])),E_USER_NOTICE);
	}
}

function _backwpup_job_file_list( $folder = '', $levels = 100, $excludedirs=array()) {
	global $backwpupjobrun,$tempfilelist;
	if( empty($folder) )
		return false;
	if( ! $levels )
		return false;
	if ($levels == 100 or $levels == 95)
		backwpup_job_update_working_data();
	$folder=rtrim($folder,'/').'/';
	if ( $dir = @opendir( $folder ) ) {
		while (($file = readdir( $dir ) ) !== false ) {
			if ( in_array($file, array('.', '..','.svn') ) )
				continue;
			foreach ($backwpupjobrun['WORKING']['FILEEXCLUDES'] as $exclusion) { //exclude dirs and files
				$exclusion=trim($exclusion);
				if (false !== stripos($folder.$file,$exclusion) and !empty($exclusion) and $exclusion!='/')
					continue 2;
			}
			if (in_array(rtrim($folder.$file,'/').'/',$excludedirs) and is_dir( $folder.$file ))
				continue;
			if ( !is_readable($folder.$file)) {
				trigger_error(sprintf(__('File or folder "%s" is not readable!','backwpup'),$folder.$file),E_USER_WARNING);
			} elseif ( is_link($folder.$file) ) {
				trigger_error(sprintf(__('Link "%s" not followed','backwpup'),$folder.$file),E_USER_WARNING);
			} elseif ( is_dir( $folder.$file )) {
				_backwpup_job_file_list( rtrim($folder.$file,'/'), $levels - 1,$excludedirs);
			} elseif ( is_file( $folder.$file ) or is_executable($folder.$file)) { //add file to filelist
				$tempfilelist[]=$folder.$file;
			} else {
				trigger_error(sprintf(__('"%s" is not a file or directory','backwpup'),$folder.$file),E_USER_WARNING);
			}

		}
		@closedir( $dir );
	}
}

?>