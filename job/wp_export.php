<?PHP
function backwpup_job_wp_export() {
	global $backwpupjobrun;
	$backwpupjobrun['WORKING']['STEPTODO']=1;
	trigger_error(sprintf(__('%d. try for wordpress export to XML file...','backwpup'),$backwpupjobrun['WORKING']['WP_EXPORT']['STEP_TRY']),E_USER_NOTICE);
	backwpup_job_need_free_memory('5M'); //5MB free memory
	//build filename
	if (empty($backwpupjobrun['STATIC']['JOB']['wpexportfile']))
		$backwpupjobrun['STATIC']['JOB']['wpexportfile']=sanitize_key(get_bloginfo('name')).'.wordpress.%Y-%m-%d.xml';
	$datevars=array('%d','%D','%l','%N','%S','%w','%z','%W','%F','%m','%M','%n','%t','%L','%o','%Y','%a','%A','%B','%g','%G','%h','%H','%i','%s','%u','%e','%I','%O','%P','%T','%Z','%c','%U');
	$datevalues=array(date_i18n('d'),date_i18n('D'),date_i18n('l'),date_i18n('N'),date_i18n('S'),date_i18n('w'),date_i18n('z'),date_i18n('W'),date_i18n('F'),date_i18n('m'),date_i18n('M'),date_i18n('n'),date_i18n('t'),date_i18n('L'),date_i18n('o'),date_i18n('Y'),date_i18n('a'),date_i18n('A'),date_i18n('B'),date_i18n('g'),date_i18n('G'),date_i18n('h'),date_i18n('H'),date_i18n('i'),date_i18n('s'),date_i18n('u'),date_i18n('e'),date_i18n('I'),date_i18n('O'),date_i18n('P'),date_i18n('T'),date_i18n('Z'),date_i18n('c'),date_i18n('U'));
	$backwpupjobrun['STATIC']['JOB']['wpexportfile']=str_replace($datevars,$datevalues,$backwpupjobrun['STATIC']['JOB']['wpexportfile']);
	//include WP export function
	require_once(ABSPATH.'wp-admin/includes/export.php');
	error_reporting(0); //disable error reporteing
	ob_start('_backwpup_job_wp_export_ob_bufferwrite',1024);//start output buffering
	export_wp();		//WP export
	ob_end_clean(); 	//End output bufferung
	error_reporting(E_ALL | E_STRICT); //enable error reporting
	
	//add XML file to backupfiles
	if (is_readable($backwpupjobrun['STATIC']['TEMPDIR'].$backwpupjobrun['STATIC']['JOB']['wpexportfile'])) {
		$filestat=stat($backwpupjobrun['STATIC']['TEMPDIR'].$backwpupjobrun['STATIC']['JOB']['wpexportfile']);
		trigger_error(sprintf(__('Add XML export "%1$s" to backup list with %2$s','backwpup'),$backwpupjobrun['STATIC']['JOB']['wpexportfile'],backwpup_formatBytes($filestat['size'])),E_USER_NOTICE);
		$backwpupjobrun['WORKING']['ALLFILESIZE']+=$filestat['size'];
		backwpup_job_add_file(array(array('FILE'=>$backwpupjobrun['STATIC']['TEMPDIR'].$backwpupjobrun['STATIC']['JOB']['wpexportfile'],'OUTFILE'=>$backwpupjobrun['STATIC']['JOB']['wpexportfile'],'SIZE'=>$filestat['size'],'ATIME'=>$filestat['atime'],'MTIME'=>$filestat['mtime'],'CTIME'=>$filestat['ctime'],'UID'=>$filestat['uid'],'GID'=>$filestat['gid'],'MODE'=>$filestat['mode'])));
	}
	$backwpupjobrun['WORKING']['STEPDONE']=1;
	$backwpupjobrun['WORKING']['STEPSDONE'][]='WP_EXPORT'; //set done
}

function _backwpup_job_wp_export_ob_bufferwrite($output) {
	global $backwpupjobrun;
	backwpup_job_update_working_data();
	file_put_contents($backwpupjobrun['STATIC']['TEMPDIR'].$backwpupjobrun['STATIC']['JOB']['wpexportfile'],$output, FILE_APPEND);
}
?>