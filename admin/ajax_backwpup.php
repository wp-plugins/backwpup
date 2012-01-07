<?PHP
if (!defined('ABSPATH'))
	die();

//helper functions for detecting file size
function _backwpup_calc_file_size_file_list_folder( $folder = '', $levels = 100, $excludes=array(),$excludedirs=array()) {
	global $backwpup_temp_files;
	if ( !empty($folder) and $levels and $dir = @opendir( $folder )) {
		while (($file = readdir( $dir ) ) !== false ) {
			if ( in_array($file, array('.', '..','.svn') ) )
				continue;
			foreach ($excludes as $exclusion) { //exclude dirs and files
				if (false !== stripos($folder.$file,$exclusion) and !empty($exclusion) and $exclusion!='/')
					continue 2;
			}
			if ( @is_dir( $folder.$file )) {
				if (!in_array(trailingslashit($folder.$file),$excludedirs))
					_backwpup_calc_file_size_file_list_folder( trailingslashit($folder.$file), $levels - 1, $excludes);
			} elseif ((@is_file( $folder.$file ) or @is_executable($folder.$file)) and @is_readable($folder.$file)) {
				$backwpup_temp_files['num']++;
				$backwpup_temp_files['size']=$backwpup_temp_files['size']+filesize($folder.$file);
			}
		}
		@closedir( $dir );
	}
}

//helper functions for detecting file size
function backwpup_calc_file_size($jobvalues) {
	global $backwpup_temp_files;
	$backwpup_temp_files=array('size'=>0,'num'=>0);
	//Exclude Files
	$backwpup_exclude=explode(',',trim($jobvalues['fileexclude']));
	$backwpup_exclude[]='.tmp';  //do not backup .tmp files
	$backwpup_exclude=array_unique($backwpup_exclude);

	//File list for blog folders
	if ($jobvalues['backuproot'])
		_backwpup_calc_file_size_file_list_folder(trailingslashit(str_replace('\\','/',ABSPATH)),100,$backwpup_exclude,array_merge($jobvalues['backuprootexcludedirs'],backwpup_get_exclude_wp_dirs(ABSPATH)));
	if ($jobvalues['backupcontent'])
		_backwpup_calc_file_size_file_list_folder(trailingslashit(str_replace('\\','/',WP_CONTENT_DIR)),100,$backwpup_exclude,array_merge($jobvalues['backupcontentexcludedirs'],backwpup_get_exclude_wp_dirs(WP_CONTENT_DIR)));
	if ($jobvalues['backupplugins'])
		_backwpup_calc_file_size_file_list_folder(trailingslashit(str_replace('\\','/',WP_PLUGIN_DIR)),100,$backwpup_exclude,array_merge($jobvalues['backuppluginsexcludedirs'],backwpup_get_exclude_wp_dirs(WP_PLUGIN_DIR)));
	if ($jobvalues['backupthemes'])
		_backwpup_calc_file_size_file_list_folder(trailingslashit(trailingslashit(str_replace('\\','/',WP_CONTENT_DIR)).'themes'),100,$backwpup_exclude,array_merge($jobvalues['backupthemesexcludedirs'],backwpup_get_exclude_wp_dirs(trailingslashit(WP_CONTENT_DIR).'themes')));
	if ($jobvalues['backupuploads'])
		_backwpup_calc_file_size_file_list_folder(trailingslashit(str_replace('\\','/',backwpup_get_upload_dir())),100,$backwpup_exclude,array_merge($jobvalues['backupuploadsexcludedirs'],backwpup_get_exclude_wp_dirs(backwpup_get_upload_dir())));

	//include dirs
	if (!empty($jobvalues['dirinclude'])) {
		$dirinclude=explode(',',$jobvalues['dirinclude']);
		$dirinclude=array_unique($dirinclude);
		//Crate file list for includes
		foreach($dirinclude as $dirincludevalue) {
			if (is_dir($dirincludevalue))
				_backwpup_calc_file_size_file_list_folder(trailingslashit($dirincludevalue),100,$backwpup_exclude);
		}
	}

	return $backwpup_temp_files;

}

//ajax show info div for jobs
function backwpup_show_info_td() {
	check_ajax_referer('backwpup_ajax_nonce');
	if (!current_user_can(BACKWPUP_USER_CAPABILITY))
		die('-1');
	global $wpdb;
	$mode=$_POST['mode'];
	$jobvalue=backwpup_get_job_vars($_POST['jobid']);
	if (in_array('DB',$jobvalue['type']) or in_array('OPTIMIZE',$jobvalue['type']) or in_array('CHECK',$jobvalue['type'])) {
		$dbsize=array('size'=>0,'num'=>0,'rows'=>0);
		$status=$wpdb->get_results("SHOW TABLE STATUS FROM `".DB_NAME."`;", ARRAY_A);
		foreach($status as $tablekey => $tablevalue) {
			if (!in_array($tablevalue['Name'],$jobvalue['dbexclude'])) {
				$dbsize['size']=$dbsize['size']+$tablevalue["Data_length"]+$tablevalue["Index_length"];
				$dbsize['num']++;
				$dbsize['rows']=$dbsize['rows']+$tablevalue["Rows"];
			}
		}
		echo __("DB Size:","backwpup")." ".backwpup_formatBytes($dbsize['size'])."<br />";
		if ( 'excerpt' == $mode ) {
			echo  __("DB Tables:","backwpup")." ".$dbsize['num']."<br />";
			echo  __("DB Rows:","backwpup")." ".$dbsize['rows']."<br />";
		}
	}
	if (in_array('FILE',$jobvalue['type'])) {
		$files=backwpup_calc_file_size($jobvalue);
		echo __("Files Size:","backwpup")." ".backwpup_formatBytes($files['size'])."<br />";
		if ( 'excerpt' == $mode ) {
			echo __("Files count:","backwpup")." ".$files['num']."<br />";
		}
	}
	die();
}
//add ajax function
add_action('wp_ajax_backwpup_show_info_td', 'backwpup_show_info_td');
?>