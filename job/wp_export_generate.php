<?PHP
if (is_writable(trim($_COOKIE['BackWPupJobTemp']).'.running')) 
	$runningfile=file_get_contents(trim($_COOKIE['BackWPupJobTemp']).'.running');
$infile=array();
if (!empty($runningfile)) 
	$infile=unserialize(trim($runningfile));
if (file_exists($infile['ABSPATH'].'wp-load.php')) {
	require_once($infile['ABSPATH'].'wp-load.php'); /** Setup WordPress environment */
	require_once($infile['ABSPATH'].'wp-admin/includes/export.php');
	export_wp();
} 
?>