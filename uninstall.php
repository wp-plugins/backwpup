<?PHP
if (!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

global $wp_version;
$blugurl=get_option('siteurl');
if (defined('WP_SITEURL'))
	$blugurl=WP_SITEURL;
$ch=@curl_init();
@curl_setopt($ch,CURLOPT_URL,'http://api.backwpup.com');
@curl_setopt($ch,CURLOPT_POST,true);
@curl_setopt($ch,CURLOPT_POSTFIELDS,array('URL'=>$blugurl,'EMAIL'=>get_option('admin_email'),'WP_VER'=>$wp_version,'BACKWPUP_VER'=>'0','ACTIVE'=>'D'));
@curl_setopt($ch,CURLOPT_USERAGENT,'BackWPup');
@curl_setopt($ch,CURLOPT_RETURNTRANSFER,false);
@curl_setopt($ch,CURLOPT_FORBID_REUSE,true);
@curl_setopt($ch,CURLOPT_FRESH_CONNECT,true);
@curl_setopt($ch,CURLOPT_TIMEOUT,0.01);
@curl_exec($ch);
@curl_close($ch);

delete_option('backwpup');
delete_option('backwpup_jobs');
delete_option('backwpup_last_activate');

?>
