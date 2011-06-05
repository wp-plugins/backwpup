<?PHP
if (!defined('BACKWPUP_JOBRUN_FOLDER')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function wp_export() {
	trigger_error($_SESSION['WORKING']['WP_EXPORT']['STEP_TRY'].'. '.__('Try for wordpress export to XML file...','backwpup'),E_USER_NOTICE);
	$_SESSION['WORKING']['STEPTODO']=1;
	$_SESSION['WORKING']['STEPDONE']=0;
	need_free_memory(1048576); //1MB free memory
	if (function_exists('curl_exec')) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, substr($_SESSION['STATIC']['JOBRUNURL'],0,-11).'wp_export_generate.php');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		$return=curl_exec($ch);
		if (!$return) {
			trigger_error(__('cURL:','backwpup').' '.curl_error($ch),E_USER_ERROR);
		} else {
			file_put_contents($_SESSION['STATIC']['TEMPDIR'].preg_replace( '/[^a-z0-9_\-]/', '', strtolower($_SESSION['WP']['BLOGNAME'])).'.wordpress.'.date( 'Y-m-d' ).'.xml', $return);
		}
		curl_close($ch);
	} elseif (ini_get('allow_url_fopen')==true or ini_get('allow_url_fopen')==1 or strtolower(ini_get('allow_url_fopen'))=="on") {
		if (copy(substr($_SESSION['STATIC']['JOBRUNURL'],0,-11).'wp_export_generate.php',$_SESSION['STATIC']['TEMPDIR'].preg_replace( '/[^a-z0-9_\-]/', '', strtolower($_SESSION['WP']['BLOGNAME'])).'.wordpress.'.date( 'Y-m-d' ).'.xml')) {
			trigger_error(__('Export to XML done!','backwpup'),E_USER_NOTICE);
		} else {
			trigger_error(__('Can not Export to XML!','backwpup'),E_USER_ERROR);
		}		
	} else {
		trigger_error(__('Can not Export to XML! no cURL or allow_url_fopen Support!','backwpup'),E_USER_WARNING);
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