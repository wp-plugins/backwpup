<?PHP
if (!defined('ABSPATH'))
	die();

nocache_headers(); //no chache
if (!empty($_GET['logfile'])) {
	check_admin_referer('view-log_'.basename(trim($_GET['logfile'])));
}

//add Help
backwpup_contextual_help(__('Here you see working jobs or logfiles','backwpup'));
?>