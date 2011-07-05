<?PHP
$folder='backwpup_'.substr(md5(str_replace('\\','/',realpath(rtrim(basename(__FILE__),'/\\').'/'))),8,16).'/';
$tempdir=getenv('TMP');
if (!$tempdir or !is_writable($tempdir) or !is_dir($tempdir))
	$tempdir=getenv('TEMP');
if (!$tempdir or !is_writable($tempdir) or !is_dir($tempdir))
	$tempdir=getenv('TMPDIR');
if (!$tempdir or !is_writable($tempdir) or !is_dir($tempdir))
	$tempdir=ini_get('upload_tmp_dir');
if (!$tempdir or empty($tempdir) or !is_writable($tempdir) or !is_dir($tempdir))
	$tempdir=sys_get_temp_dir();
$tempdir=str_replace('\\','/',realpath(rtrim($tempdir,'/'))).'/';
if (is_dir($tempdir.$folder) and is_writable($tempdir.$folder)) {
	$runningfile=file_get_contents($tempdir.$folder.'.running');
}
$infile=array();
if (!empty($runningfile)) 
	$infile=unserialize(trim($runningfile));
if (file_exists($infile['ABSPATH'].'wp-load.php')) {
	require_once($infile['ABSPATH'].'wp-load.php'); /** Setup WordPress environment */
	require_once($infile['ABSPATH'].'wp-admin/includes/export.php');
	export_wp();
} else {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}
?>