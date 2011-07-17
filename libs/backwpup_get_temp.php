<?PHP
function backwpup_get_temp() {
	$tmpbackwpup='.backwpup_'.crc32(__FILE__).'/';
	$tempdir=getenv('TMP');												//temp dirs form env
	if (empty($tempdir) or !backwpup_get_temp_check_open_basedir($tempdir) or !is_writable($tempdir) or !is_dir($tempdir))
		$tempdir=getenv('TEMP');
	if (empty($tempdir) or !backwpup_get_temp_check_open_basedir($tempdir) or !is_writable($tempdir) or !is_dir($tempdir))
		$tempdir=getenv('TMPDIR');
	if (empty($tempdir) or !backwpup_get_temp_check_open_basedir($tempdir) or !is_writable($tempdir) or !is_dir($tempdir)) 
		$tempdir=sys_get_temp_dir();									//normal temp dir
	if (empty($tempdir) or !backwpup_get_temp_check_open_basedir($tempdir) or !is_writable($tempdir) or !is_dir($tempdir)) 
		$tempdir=ini_get('upload_tmp_dir');								//if sys_get_temp_dir not work
	if (empty($tempdir) or !backwpup_get_temp_check_open_basedir($tempdir) or !is_writable($tempdir) or !is_dir($tempdir)) 
		$tempdir=ini_get('session.save_path');							//if sys_get_temp_dir not work
	if (empty($tempdir) or !backwpup_get_temp_check_open_basedir($tempdir) or !is_writable($tempdir) or !is_dir($tempdir)) {
		$openbasedir=ini_get('open_basedir');
		if (!empty($openbasedir)) {
			$openbasedir=explode(PATH_SEPARATOR,$openbasedir);
			$tempdir=$openbasedir[0];
		}
	}
	if (is_readable(dirname(__FILE__).'/../../.backwpuptempfolder'))    // user def. temp dir
		$tempdir=trim(file_get_contents(dirname(__FILE__).'/../../.backwpuptempfolder',false,NULL,0,255));
	$tempdir=rtrim(str_replace('\\','/',realpath($tempdir)),'/').'/';
	return $tempdir.$tmpbackwpup;
}
//checks the tempdir is in openbasedir
function backwpup_get_temp_check_open_basedir($tempdir) {
	$openbasedir=ini_get('open_basedir');
	$tempdir=rtrim(str_replace('\\','/',$tempdir),'/').'/';
	if (!empty($openbasedir)) {
		$openbasedirarray=explode(PATH_SEPARATOR,$openbasedir);
		foreach ($openbasedirarray as $basedir) {
			if (stripos($tempdir,rtrim(str_replace('\\','/',$basedir),'/').'/')==0)
				return true;
		}
	} else {
		return true;
	}
	return false;
}
?>