<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

//ajax show info div for jobs
function backwpup_working_update() {
	if (is_file(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running')) {
		$runfile=file_get_contents(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running');
		$runningfile=unserialize(trim($runfile));
		backwpup_read_logfile(trim($runningfile['LOGFILE']));
	} else {
		echo '<div id="stopworking"></div>';
		backwpup_read_logfile($_POST['logfile']);
	}
	die();
}
//add ajax function
add_action('wp_ajax_backwpup_working_update', 'backwpup_working_update');	
?>