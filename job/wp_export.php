<?PHP
function wp_export() {
	global $WORKING,$STATIC;
	$WORKING['STEPTODO']=1;
	trigger_error(sprintf(__('%d. try for wordpress export to XML file...','backwpup'),$WORKING['WP_EXPORT']['STEP_TRY']),E_USER_NOTICE);
	need_free_memory(10485760); //10MB free memory
	if (function_exists('curl_exec')) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, substr($STATIC['JOBRUNURL'],0,-11).'wp_export_generate.php');
		curl_setopt($ch, CURLOPT_COOKIESESSION, true);
		curl_setopt($ch, CURLOPT_COOKIE,'BackWPupJobTemp='.$STATIC['TEMPDIR'].'; path=/');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		if (defined('CURLOPT_PROGRESSFUNCTION')) {
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
			file_put_contents($STATIC['TEMPDIR'].preg_replace( '/[^a-z0-9_\-]/', '', strtolower($STATIC['WP']['BLOGNAME'])).'.wordpress.'.date( 'Y-m-d' ).'.xml', $return);
		}
		curl_close($ch);
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