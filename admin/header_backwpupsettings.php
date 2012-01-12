<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}


if (isset($_POST['submit']) and isset($_POST['action']) and $_POST['action']=='update') {
	global $wpdb;
	check_admin_referer('backwpup-cfg');

	backwpup_update_option('cfg','mailsndemail',sanitize_email($_POST['mailsndemail']));
	backwpup_update_option('cfg','mailsndname',$_POST['mailsndname']);
	backwpup_update_option('cfg','showadminbar',isset($_POST['showadminbar']) ? true : false);
    if (100>$_POST['jobstepretry'] and 0<$_POST['jobstepretry']) 
		$_POST['jobstepretry']=(int)$_POST['jobstepretry'];
	if (empty($_POST['jobstepretry']) or !is_int($_POST['jobstepretry']))
		$_POST['jobstepretry']=3;
	backwpup_update_option('cfg','jobstepretry',$_POST['jobstepretry']);
	if (100>$_POST['jobscriptretry'] and 0<$_POST['jobscriptretry']) 
		$_POST['jobscriptretry']=(int)$_POST['jobscriptretry'];
	if (empty($_POST['jobscriptretry']) or !is_int($_POST['jobscriptretry']))
		$_POST['jobscriptretry']=5;
	backwpup_update_option('cfg','jobscriptretry',$_POST['jobscriptretry']);
	backwpup_update_option('cfg','maxlogs',abs((int)$_POST['maxlogs']));
	backwpup_update_option('cfg','gzlogs',isset($_POST['gzlogs']) ? true : false);
	backwpup_update_option('cfg','apicronservice',isset($_POST['apicronservice']) ? true : false);
	backwpup_update_option('cfg','httpauthuser',$_POST['httpauthuser']);
	backwpup_update_option('cfg','httpauthpassword',backwpup_encrypt($_POST['httpauthpassword']));
	$_POST['jobrunauthkey']=preg_replace( '/[^a-zA-Z0-9_\-]/', '',trim($_POST['jobrunauthkey']));
	backwpup_update_option('cfg','jobrunauthkey',$_POST['jobrunauthkey']);
	$_POST['apicronservicekey']=preg_replace( '/[^a-zA-Z0-9_\-]/', '',trim($_POST['apicronservicekey']));
	backwpup_update_option('cfg','apicronservicekey',$_POST['apicronservicekey']);
	if (7200>$_POST['jobrunmaxexectime'] and 0<$_POST['jobrunmaxexectime'])
		$_POST['jobrunmaxexectime']=(int)$_POST['jobrunmaxexectime'];
	if (empty($_POST['jobrunmaxexectime']) or !is_int($_POST['jobrunmaxexectime']))
		$_POST['jobrunmaxexectime']=0;
	backwpup_update_option('cfg','jobrunmaxexectime',$_POST['jobrunmaxexectime']);
	$_POST['logfolder']=trailingslashit(str_replace('\\','/',$_POST['logfolder']));
	//set def. folders
	if (!isset($_POST['logfolder']) or $_POST['logfolder']=='/' or empty($_POST['logfolder'])) {
		$rand = substr( md5( md5( SECURE_AUTH_KEY ) ), -5 );
		$_POST['logfolder']=str_replace('\\','/',trailingslashit(WP_CONTENT_DIR)).'backwpup-'.$rand.'-logs/';
	}
	if (substr($_POST['logfolder'],0,1)!='/' and substr($_POST['logfolder'],1,1)!=':') //add abspath if not absolute
		$_POST['logfolder']=rtrim(str_replace('\\','/',ABSPATH),'/').'/'.$_POST['logfolder'];
	backwpup_update_option('cfg','logfolder',$_POST['logfolder']);

	$_POST['tempfolder']=trailingslashit(str_replace('\\','/',$_POST['tempfolder']));
	if (substr($_POST['tempfolder'],0,1)!='/' and substr($_POST['tempfolder'],1,1)!=':') //add abspath if not absolute
		$_POST['tempfolder']=rtrim(str_replace('\\','/',ABSPATH),'/').'/'.$_POST['tempfolder'];
	if (empty($_POST['tempfolder']) or backwpup_check_open_basedir($_POST['tempfolder'])) {
		if (defined('WP_TEMP_DIR'))
			$tempfolder=trim(WP_TEMP_DIR);
		if (empty($tempfolder) or !backwpup_check_open_basedir($tempfolder) or !@is_writable($tempfolder) or !@is_dir($tempfolder))
			$tempfolder=sys_get_temp_dir();									//normal temp dir
		if (empty($tempfolder) or !backwpup_check_open_basedir($tempfolder) or !@is_writable($tempfolder) or !@is_dir($tempfolder))
			$tempfolder=ini_get('upload_tmp_dir');							//if sys_get_temp_dir not work
		if (empty($tempfolder) or !backwpup_check_open_basedir($tempfolder) or !@is_writable($tempfolder) or !@is_dir($tempfolder))
			$tempfolder=WP_CONTENT_DIR.'/';
		if (empty($tempfolder) or !backwpup_check_open_basedir($tempfolder) or !@is_writable($tempfolder) or !@is_dir($tempfolder))
			$tempfolder=get_temp_dir();
		$_POST['tempfolder']=trailingslashit(str_replace('\\','/',realpath($tempfolder)));
	}
	backwpup_update_option('cfg','tempfolder',$_POST['tempfolder']);

	do_action('backwpup_api_cron_update');
	
	$backwpup_message=__('Settings saved', 'backwpup');
}

//add Help
if (method_exists(get_current_screen(),'add_help_tab')) {
	get_current_screen()->add_help_tab( array(
		'id'      => 'overview',
		'title'   => __('Overview'),
		'content'	=>
		'<p>' . '</p>'
	) );
}
?>