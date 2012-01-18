<?php
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

nocache_headers(); //no cache

if (isset($_GET['starttype']) and $_GET['starttype']=='runnow' and backwpup_get_option('cfg','runnowalt') and !empty($_GET['jobid'])) {
	check_admin_referer('job-runnow');
	backwpup_jobrun_url('runnowalt',$_GET['jobid'],true);
}

if (!empty($_GET['logfile']))
	check_admin_referer('view-log_'.basename(trim($_GET['logfile'])));

if (!empty($_GET['jobid']))
	$_GET['logfile']=backwpup_get_option('job_' . $_GET['jobid'], 'logfile');

//add Help
if (method_exists(get_current_screen(),'add_help_tab')) {
	get_current_screen()->add_help_tab( array(
		'id'      => 'overview',
		'title'   => __('Overview'),
		'content'	=>
		'<p>' .__('Here you see a working jobs or a logfile','backwpup') . '</p>'
	) );
}