<?PHP
function wp_export() {
	global $WORKING,$STATIC;
	$WORKING['STEPTODO']=1;
	trigger_error(sprintf(__('%d. try for wordpress export to XML file...','backwpup'),$WORKING['WP_EXPORT']['STEP_TRY']),E_USER_NOTICE);
	need_free_memory(10485760); //10MB free memory
	
	require_once(dirname(__FILE__).'/../libs/class.http.php');
	$http = new Http();
	$http->setMethod('POST');
	$http->setCookiepath($STATIC['TEMPDIR']);
	$http->followRedirects(false);
	if (!empty($STATIC['CFG']['httpauthuser']) and !empty($STATIC['CFG']['httpauthpassword']))
		$http->setAuth($STATIC['CFG']['httpauthuser'], base64_decode($STATIC['CFG']['httpauthpassword']));
	$http->addParam('BackWPupJobTemp', $STATIC['TEMPDIR']);
	$http->addParam('nonce',$WORKING['NONCE']);
	$http->addParam('type', 'getxmlexport');
	$http->setUseragent('BackWPup');
	$http->setTimeout(300);
	$http->setProgressFunction('curl_progresscallback');
	@set_time_limit($STATIC['CFG']['jobscriptruntimelong']);
	$return=$http->execute(substr($STATIC['JOBRUNURL'],0,-11).'wp_export_generate.php');
	$status=$http->getStatus();
	$error=$http->getError();
	
	if ($status>=300 or $status<200 or !empty($error)) {
		trigger_error(sprintf(__('XML Export (%1$d) %2$s','backwpup'),$status,$error),E_USER_ERROR);	
	} else {
		file_put_contents($STATIC['TEMPDIR'].preg_replace( '/[^a-z0-9_\-]/', '', strtolower($STATIC['WP']['BLOGNAME'])).'.wordpress.'.date( 'Y-m-d' ).'.xml', $return);
	}
	
	//add XML file to backupfiles
	if (is_readable($STATIC['TEMPDIR'].preg_replace( '/[^a-z0-9_\-]/', '', strtolower($STATIC['WP']['BLOGNAME'])).'.wordpress.'.date( 'Y-m-d' ).'.xml')) {
		$filestat=stat($STATIC['TEMPDIR'].preg_replace( '/[^a-z0-9_\-]/', '', strtolower($STATIC['WP']['BLOGNAME'])).'.wordpress.'.date( 'Y-m-d' ).'.xml');
		trigger_error(sprintf(__('Add XML export "%1$s" to backup list with %2$s','backwpup'),preg_replace( '/[^a-z0-9_\-]/', '', strtolower($STATIC['WP']['BLOGNAME'])).'.wordpress.'.date( 'Y-m-d' ).'.xml',formatbytes($filestat['size'])),E_USER_NOTICE);
		$WORKING['ALLFILESIZE']+=$filestat['size'];
		add_file(array(array('FILE'=>$STATIC['TEMPDIR'].preg_replace( '/[^a-z0-9_\-]/', '', strtolower($STATIC['WP']['BLOGNAME'])).'.wordpress.'.date( 'Y-m-d' ).'.xml','OUTFILE'=>preg_replace( '/[^a-z0-9_\-]/', '', strtolower($STATIC['WP']['BLOGNAME'])).'.wordpress.'.date( 'Y-m-d' ).'.xml','SIZE'=>$filestat['size'],'ATIME'=>$filestat['atime'],'MTIME'=>$filestat['mtime'],'CTIME'=>$filestat['ctime'],'UID'=>$filestat['uid'],'GID'=>$filestat['gid'],'MODE'=>$filestat['mode'])));
	}
	$WORKING['STEPDONE']=1;
	$WORKING['STEPSDONE'][]='WP_EXPORT'; //set done
}
?>