<?PHP
if (!defined('ABSPATH')) 
	die();

if (isset($_POST['dbrestoretool']) and $_POST['dbrestoretool']==__('Put DB restore tool to blog root...', 'backwpup')) {
	check_admin_referer('backwpup-tools');
	if(copy('http://api.backwpup.com/download/backwpup_db_restore.zip',ABSPATH.'backwpup_db_restore.zip')) {
		//unzip
		if (class_exists('ZipArchive')) {
			$zip = new ZipArchive;
			if ($zip->open(ABSPATH.'backwpup_db_restore.zip') === TRUE) {
				$zip->extractTo(ABSPATH);
				$zip->close();
				unlink(ABSPATH.'backwpup_db_restore.zip');
			}
		} else { //PCL zip
			require_once(ABSPATH . 'wp-admin/includes/class-pclzip.php');
			$zip = new PclZip(ABSPATH.'backwpup_db_restore.zip');
			$zip->extract(PCLZIP_OPT_PATH,ABSPATH);
			unset($zip);
			unlink(ABSPATH.'backwpup_db_restore.zip');
		}
	}
}

if (isset($_POST['dbrestoretooldel']) and $_POST['dbrestoretooldel']==__('Delete restore tool from blog root...', 'backwpup')) {
	check_admin_referer('backwpup-tools');
	if (file_exists(ABSPATH.'backwpup_db_restore.zip'))
		unlink(ABSPATH.'backwpup_db_restore.zip');
	if (file_exists(ABSPATH.'backwpup_db_restore.php'))
		unlink(ABSPATH.'backwpup_db_restore.php');
	if (file_exists(ABSPATH.'.backwpup_restore'))
		unlink(ABSPATH.'.backwpup_restore');
}

if (isset($_POST['executiontime']) and $_POST['executiontime']==__('Start time test...', 'backwpup')) {
	check_admin_referer('backwpup-tools');
	//try to disable safe mode
	@ini_set('safe_mode', '0');
	// Now user abort
	@ini_set('ignore_user_abort', '0');
	ignore_user_abort(true);
	@set_time_limit(1800);
	ob_start();
	wp_redirect(backwpup_admin_url('admin.php') . '?page=backwpuptools');
	echo ' ';
	while ( @ob_end_flush() );
	flush();
	$times['starttime']=current_time('timestamp');
	$times['lasttime']=current_time('timestamp');
	backwpup_update_option('temp','exectime',$times);
	$count=0;
	while ($count<1800) {
		sleep(1);
		$stop=backwpup_get_option('temp','exectimestop');
		if (!empty($stop)) {
			backwpup_delete_option('temp','exectimestop');
			backwpup_delete_option('temp','exectime');
			die();
		}
		$times['lasttime']=current_time('timestamp');
		backwpup_update_option('temp','exectime',$times);
		$count++;
	}
}
if (isset($_POST['executionstop']) and $_POST['executionstop']==__('Terminate time test!', 'backwpup')) {
	check_admin_referer('backwpup-tools');
	backwpup_update_option('temp','exectimestop',true);
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