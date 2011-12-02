<?PHP
if (!defined('ABSPATH'))
	die();

nocache_headers(); //no chache
if (!empty($_GET['logfile'])) {
	check_admin_referer('view-log_'.basename(trim($_GET['logfile'])));
}

//add Help
get_current_screen()->add_help_tab( array(
	'id'      => 'overview',
	'title'   => __('Overview'),
	'content'	=>
	'<p>' .__('Here you see a working jobs or a logfile','backwpup') . '</p>'
) );
?>