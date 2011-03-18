<?PHP
if (file_exists(trim($_GET['wpabs']).'wp-load.php')) {
	require_once(trim($_GET['wpabs']).'wp-load.php'); /** Setup WordPress environment */
} else {
	header("HTTP/1.0 404 Not Found");
}
require_once (dirname(__FILE__).'/libs/dropbox.php');
$dropbox = new Dropbox(BACKWPUP_DROPBOX_APP_KEY, BACKWPUP_DROPBOX_APP_SECRET);
//for Dropbox oAuth backlink
if ($_GET['uid']>1 and !empty($_GET['oauth_token'])) {
	$reqtoken=get_option('backwpup_dropboxrequest');
	if ($reqtoken['oAuthRequestToken']==$_GET['oauth_token']) {
		//Get Access Tokens
		$oAuthStuff = $dropbox->oAuthAccessToken($_GET['oauth_token']);
		//Save Tokens
		$jobs=get_option('backwpup_jobs');
		$jobs[$reqtoken['jobid']]['dropetoken']=$oAuthStuff['oauth_token'];
		$jobs[$reqtoken['jobid']]['dropesecret']=$oAuthStuff['oauth_token_secret'];
		update_option('backwpup_jobs',$jobs);
		delete_option('backwpup_dropboxrequest');
		//Go back to jobs page
		header("Location: ".$reqtoken['referer']."#dropbox");
	}
} 
?>