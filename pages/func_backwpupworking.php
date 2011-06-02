<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

//ajax show info div for jobs
function backwpup_working_update() {
	check_ajax_referer('backwpupworking_ajax_nonce');
	if (!current_user_can(BACKWPUP_USER_CAPABILITY))
		die('-1');
	if (is_file(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running')) {
		$runfile=trim(file_get_contents(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running'));
		if (!empty($runfile)) {
			$infile=unserialize($runfile);
			$infile['LOG']=@file_get_contents(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_massage');
			@unlink(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_massage');
			echo json_encode($infile);
		}
	} else {
		$log=@file_get_contents(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_massage');
		@unlink(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_massage');
		echo json_encode(array('LOG'=>$log.'<span id="stopworking"></span>','WARNING'=>'','ERROR'=>'','STEPSPERSENT'=>100,'STEPPERSENT'=>100));
	}
	die();
}
//add ajax function
add_action('wp_ajax_backwpup_working_update', 'backwpup_working_update');	
?>