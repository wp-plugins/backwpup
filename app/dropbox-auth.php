<?PHP
if (file_exists(trim($_GET['wpabs']).'wp-load.php')) {
	require_once(trim($_GET['wpabs']).'wp-load.php'); /** Setup WordPress environment */
} else {
	header("HTTP/1.0 404 Not Found");
	die();
}
$reqtoken=get_option('backwpup_dropboxrequest');
if (!is_array($reqtoken)) {
	header("HTTP/1.0 404 Not Found");
	die();
}
require_once (dirname(__FILE__).'/libs/dropbox/dropbox.php');
$dropbox = new Dropbox(BACKWPUP_DROPBOX_APP_KEY, BACKWPUP_DROPBOX_APP_SECRET);
//for Dropbox oAuth backlink
if ($_GET['uid']>1 and !empty($_GET['oauth_token'])) {
	if ($reqtoken['oAuthRequestToken']==$_GET['oauth_token']) {
		//Get Access Tokens
		$oAuthStuff = $dropbox->oAuthAccessToken($reqtoken['oAuthRequestToken'],$reqtoken['oAuthRequestTokenSecret']);
		//Save Tokens
		$jobs=get_option('backwpup_jobs');
		$jobs[$reqtoken['jobid']]['dropetoken']=$oAuthStuff['oauth_token'];
		$jobs[$reqtoken['jobid']]['dropesecret']=$oAuthStuff['oauth_token_secret'];
		update_option('backwpup_jobs',$jobs);
		delete_option('backwpup_dropboxrequest');
		//Go back to jobs page
		header("Location: ".get_admin_url().'admin.php?page=BackWPup&subpage=edit&jobid='.$reqtoken['jobid'].'&_wpnonce='.wp_create_nonce('edit-job').'#dropbox');
	}
} 
?>