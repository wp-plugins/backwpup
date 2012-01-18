<?php
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

//helper functions for detecting file size
function _backwpup_calc_file_size_file_list_folder( $folder = '', $levels = 100, $excludes=array(),$excludedirs=array(),$nothumbs=false) {
	global $backwpup_temp_files;
	$backwpup_temp_files['folder']++;
	if ( !empty($folder) and $levels and $dir = @opendir( $folder )) {
		while (($file = readdir( $dir ) ) !== false ) {
			if ( in_array($file, array('.', '..') ) )
				continue;
			foreach ($excludes as $exclusion) { //exclude dirs and files
				if (false !== stripos($folder.$file,$exclusion) and !empty($exclusion) and $exclusion!='/')
					continue 2;
			}
			if ($nothumbs and strpos($folder,backwpup_get_upload_dir()) !== false and  preg_match("/\-[0-9]{2,4}x[0-9]{2,4}\.(jpg|png|gif)$/i",$file))
				continue;
			if ( @is_dir( $folder.$file )) {
				if (!in_array(trailingslashit($folder.$file),$excludedirs))
					_backwpup_calc_file_size_file_list_folder( trailingslashit($folder.$file), $levels - 1, $excludes,$excludedirs,$nothumbs);
			} elseif ((@is_file( $folder.$file ) or @is_executable($folder.$file)) and @is_readable($folder.$file)) {
				$backwpup_temp_files['num']++;
				$backwpup_temp_files['size']=$backwpup_temp_files['size']+filesize($folder.$file);
			}
		}
		@closedir( $dir );
	}
}

//helper functions for detecting file size
function backwpup_calc_file_size($main) {
	global $backwpup_temp_files;
	$backwpup_temp_files=array('size'=>0,'num'=>0,'folder'=>0);
	//Exclude Files
	$backwpup_exclude=explode(',',trim(backwpup_get_option($main,'fileexclude')));
	$backwpup_exclude=array_unique($backwpup_exclude);

	//File list for blog folders
	if (backwpup_get_option($main,'backuproot'))
		_backwpup_calc_file_size_file_list_folder(trailingslashit(str_replace('\\','/',ABSPATH)),100,$backwpup_exclude,array_merge(backwpup_get_option($main,'backuprootexcludedirs'),backwpup_get_exclude_wp_dirs(ABSPATH)));
	if (backwpup_get_option($main,'backupcontent'))
		_backwpup_calc_file_size_file_list_folder(trailingslashit(str_replace('\\','/',WP_CONTENT_DIR)),100,$backwpup_exclude,array_merge(backwpup_get_option($main,'backupcontentexcludedirs'),backwpup_get_exclude_wp_dirs(WP_CONTENT_DIR)));
	if (backwpup_get_option($main,'backupplugins'))
		_backwpup_calc_file_size_file_list_folder(trailingslashit(str_replace('\\','/',WP_PLUGIN_DIR)),100,$backwpup_exclude,array_merge(backwpup_get_option($main,'backuppluginsexcludedirs'),backwpup_get_exclude_wp_dirs(WP_PLUGIN_DIR)));
	if (backwpup_get_option($main,'backupthemes'))
		_backwpup_calc_file_size_file_list_folder(trailingslashit(trailingslashit(str_replace('\\','/',WP_CONTENT_DIR)).'themes'),100,$backwpup_exclude,array_merge(backwpup_get_option($main,'backupthemesexcludedirs',backwpup_get_exclude_wp_dirs(trailingslashit(WP_CONTENT_DIR).'themes'))));
	if (backwpup_get_option($main,'backupuploads'))
		_backwpup_calc_file_size_file_list_folder(trailingslashit(str_replace('\\','/',backwpup_get_upload_dir())),100,$backwpup_exclude,array_merge(backwpup_get_option($main,'backupuploadsexcludedirs'),backwpup_get_exclude_wp_dirs(backwpup_get_upload_dir())),backwpup_get_option($main,'backupexcludethumbs'));

	//include dirs
	if (backwpup_get_option($main,'dirinclude')) {
		$dirinclude=explode(',',backwpup_get_option($main,'dirinclude'));
		$dirinclude=array_unique($dirinclude);
		//Crate file list for includes
		foreach($dirinclude as $dirincludevalue) {
			if (is_dir($dirincludevalue))
				_backwpup_calc_file_size_file_list_folder(trailingslashit($dirincludevalue),100,$backwpup_exclude);
		}
	}

	//add extra files if selected
	if (backwpup_get_option($main,'backupspecialfiles')) {
		if ( file_exists( ABSPATH . 'wp-config.php') and !backwpup_get_option($main,'backuproot')) {
			$backwpup_temp_files['size']=$backwpup_temp_files['size']+filesize(ABSPATH . 'wp-config.php');
			$backwpup_temp_files['num']++;
		} elseif ( file_exists( dirname(ABSPATH) . '/wp-config.php' ) && ! file_exists( dirname(ABSPATH) . '/wp-settings.php' ) ) {
			$backwpup_temp_files['size']=$backwpup_temp_files['size']+filesize(dirname(ABSPATH) . '/wp-config.php');
			$backwpup_temp_files['num']++;
		}
		if ( file_exists( ABSPATH . '.htaccess') and !backwpup_get_option($main,'backuproot')) {
			$backwpup_temp_files['size']=$backwpup_temp_files['size']+filesize(ABSPATH . '.htaccess');
			$backwpup_temp_files['num']++;
		}
		if ( file_exists( ABSPATH . '.htpasswd') and !backwpup_get_option($main,'backuproot')) {
			$backwpup_temp_files['size']=$backwpup_temp_files['size']+filesize(ABSPATH . '.htpasswd');
			$backwpup_temp_files['num']++;
		}
		if ( file_exists( ABSPATH . 'robots.txt') and !backwpup_get_option($main,'backuproot')) {
			$backwpup_temp_files['size']=$backwpup_temp_files['size']+filesize(ABSPATH . 'robots.txt');
			$backwpup_temp_files['num']++;
		}
		if ( file_exists( ABSPATH . 'favicon.ico') and !backwpup_get_option($main,'backuproot')) {
			$backwpup_temp_files['size']=$backwpup_temp_files['size']+filesize(ABSPATH . 'favicon.ico');
			$backwpup_temp_files['num']++;
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
	$main='job_'.(int)$_POST['jobid'];
	if (in_array('DB',backwpup_get_option($main,'type')) or in_array('OPTIMIZE',backwpup_get_option($main,'type')) or in_array('CHECK',backwpup_get_option($main,'type'))) {
		$dbsize=array('size'=>0,'num'=>0,'rows'=>0);
		$status=$wpdb->get_results("SHOW TABLE STATUS FROM `".DB_NAME."`;", ARRAY_A);
		foreach($status as $tablekey => $tablevalue) {
			if (!in_array($tablevalue['Name'],backwpup_get_option($main,'dbexclude'))) {
				$dbsize['size']=$dbsize['size']+$tablevalue["Data_length"]+$tablevalue["Index_length"];
				$dbsize['num']++;
				$dbsize['rows']=$dbsize['rows']+$tablevalue["Rows"];
			}
		}
		echo __("DB Size:","backwpup")." ".backwpup_format_bytes($dbsize['size'])."<br />";
		if ( 'excerpt' == $mode ) {
			echo  __("DB Tables:","backwpup")." ".$dbsize['num']."<br />";
			echo  __("DB Rows:","backwpup")." ".$dbsize['rows']."<br />";
		}
	}
	if (in_array('FILE',backwpup_get_option($main,'type'))) {
		$files=backwpup_calc_file_size($main);
		echo __("Files Size:","backwpup")." ".backwpup_format_bytes($files['size'])."<br />";
		if ( 'excerpt' == $mode ) {
			echo __("Folder count:","backwpup")." ".$files['folder']."<br />";
			echo __("Files count:","backwpup")." ".$files['num']."<br />";
		}
	}
	die();
}
//add ajax function
add_action('wp_ajax_backwpup_show_info_td', 'backwpup_show_info_td');