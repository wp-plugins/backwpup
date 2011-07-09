<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function wp_export() {
	$_SESSION['WORKING']['STEPTODO']=1;
	trigger_error($_SESSION['WORKING']['WP_EXPORT']['STEP_TRY'].'. '.__('Try for wordpress export to XML file...','backwpup'),E_USER_NOTICE);
	need_free_memory(10485760); //10MB free memory
	if (function_exists('curl_exec')) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, substr($_SESSION['STATIC']['JOBRUNURL'],0,-11).'wp_export_generate.php');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		if (is_numeric(CURLOPT_PROGRESSFUNCTION)) {
			curl_setopt($ch, CURLOPT_NOPROGRESS, false);
			curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, 'curl_progresscallback');
			curl_setopt($ch, CURLOPT_BUFFERSIZE, 512);
		}
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		$return=curl_exec($ch);
		$status=curl_getinfo($ch);
		if ($status['http_code']>=300 or $status['http_code']<200 or curl_errno($ch)>0) {
			if (0!=curl_errno($ch)) 
				trigger_error(__('cURL:','backwpup').' ('.curl_errno($ch).') '.curl_error($ch),E_USER_ERROR);
			else 
				trigger_error(__('cURL:','backwpup').' ('.$status['http_code'].')  Invalid response.',E_USER_ERROR);	
		} else {
			file_put_contents($_SESSION['STATIC']['TEMPDIR'].preg_replace( '/[^a-z0-9_\-]/', '', strtolower($_SESSION['WP']['BLOGNAME'])).'.wordpress.'.date( 'Y-m-d' ).'.xml', $return);
		}
		curl_close($ch);
	} 
	//add XML file to backupfiles
	if (is_readable($_SESSION['STATIC']['TEMPDIR'].preg_replace( '/[^a-z0-9_\-]/', '', strtolower($_SESSION['WP']['BLOGNAME'])).'.wordpress.'.date( 'Y-m-d' ).'.xml')) {
		$filestat=stat($_SESSION['STATIC']['TEMPDIR'].preg_replace( '/[^a-z0-9_\-]/', '', strtolower($_SESSION['WP']['BLOGNAME'])).'.wordpress.'.date( 'Y-m-d' ).'.xml');
		trigger_error(__('Add XML export to backup list:','backwpup').' '.preg_replace( '/[^a-z0-9_\-]/', '', strtolower($_SESSION['WP']['BLOGNAME'])).'.wordpress.'.date( 'Y-m-d' ).'.xml '.formatbytes($filestat['size']),E_USER_NOTICE);
		$_SESSION['WORKING']['ALLFILESIZE']+=$filestat['size'];
		$_SESSION['WORKING']['FILELIST'][]=array('FILE'=>$_SESSION['STATIC']['TEMPDIR'].preg_replace( '/[^a-z0-9_\-]/', '', strtolower($_SESSION['WP']['BLOGNAME'])).'.wordpress.'.date( 'Y-m-d' ).'.xml','OUTFILE'=>preg_replace( '/[^a-z0-9_\-]/', '', strtolower($_SESSION['WP']['BLOGNAME'])).'.wordpress.'.date( 'Y-m-d' ).'.xml','SIZE'=>$filestat['size'],'ATIME'=>$filestat['atime'],'MTIME'=>$filestat['mtime'],'CTIME'=>$filestat['ctime'],'UID'=>$filestat['uid'],'GID'=>$filestat['gid'],'MODE'=>$filestat['mode']);
	}
	$_SESSION['WORKING']['STEPDONE']=1;
	$_SESSION['WORKING']['STEPSDONE'][]='WP_EXPORT'; //set done
}
?>