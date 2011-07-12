<?PHP
function backwpup_get_temp() {
	$openbasedir=explode(PATH_SEPARATOR,ini_get('open_basedir'));
	$tmpbackwpup='.backwpup_'.substr(md5(__FILE__),8,16).'/';
	$tempdir=getenv('TMP');
	if (empty($tempdir) or !is_writable($tempdir) or !is_dir($tempdir))
		$tempdir=getenv('TEMP');
	if (empty($tempdir) or !is_writable($tempdir) or !is_dir($tempdir))
		$tempdir=getenv('TMPDIR');
	if (empty($tempdir) or !is_writable($tempdir) or !is_dir($tempdir))
		$tempdir=ini_get('upload_tmp_dir');
	if (empty($tempdir) or !is_writable($tempdir) or !is_dir($tempdir))
		$tempdir=ini_get('session.save_path');
	if (empty($tempdir) or !is_writable($tempdir) or !is_dir($tempdir))
		$tempdir=sys_get_temp_dir();
	if (empty($tempdir) or !is_writable($tempdir) or !is_dir($tempdir))
		$tempdir=$openbasedir[0];
	if (is_readable(dirname(__FILE__).'/../.backwpuptempfolder'))
		$tempdir=trim(file_get_contents(dirname(__FILE__).'/../../.backwpuptempfolder',false,NULL,0,255));
	$tempdir=str_replace('\\','/',realpath(rtrim($tempdir,'/'))).'/';
	return $tempdir.$tmpbackwpup;
}
?>