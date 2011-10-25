<?PHP
define('DONOTCACHEPAGE', true);
define('DONOTCACHEDB', true);
define('DONOTMINIFY', true);
define('DONOTCDN', true);
define('DONOTCACHCEOBJECT', true);
define('W3TC_IN_MINIFY',false); //W3TC will not loaded

$_POST['BackWPupJobTemp']=str_replace(array(':','@','../','//','\\'),'',$_POST['BackWPupJobTemp']);
if (is_file(trim($_POST['BackWPupJobTemp']).'.running')) 
	$runningfile=file_get_contents(trim($_POST['BackWPupJobTemp']).'.running');
$infile=array();
if (!empty($runningfile)) 
	$infile=unserialize(trim($runningfile));
if (is_file(trim($infile['ABSPATH']).'wp-load.php') and $_POST['nonce']==$infile['WORKING']['NONCE'] and $_POST['type']=='getxmlexport') {
	require_once(trim($infile['ABSPATH']).'wp-load.php'); /** Setup WordPress environment */
	require_once(trim($infile['ABSPATH']).'wp-admin/includes/export.php');
	export_wp();
} 
?>