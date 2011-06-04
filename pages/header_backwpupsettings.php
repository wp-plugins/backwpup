<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

if (isset($_POST['submit']) and isset($_POST['action']) and $_POST['action']=='update') {
	check_admin_referer('backwpup-cfg');
	$cfg=get_option('backwpup'); //Load Settings
	$cfg['mailsndemail']=sanitize_email($_POST['mailsndemail']);
	$cfg['mailsndname']=$_POST['mailsndname'];
	$cfg['mailmethod']=$_POST['mailmethod'];
	$cfg['mailsendmail']=untrailingslashit(str_replace('//','/',str_replace('\\','/',stripslashes($_POST['mailsendmail']))));
	$cfg['mailsecure']=$_POST['mailsecure'];
	$cfg['mailhost']=$_POST['mailhost'];
	$cfg['mailuser']=$_POST['mailuser'];
	$cfg['mailpass']=base64_encode($_POST['mailpass']);
	$cfg['disablewpcron']=isset($_POST['disablewpcron']) ? true : false;
    if (100>$_POST['jobstepretry'] and 0<$_POST['jobstepretry']) 
		$cfg['jobstepretry']=(int)$_POST['jobstepretry'];
	if (100>$_POST['jobscriptretry'] and 0<$_POST['jobscriptretry']) 
		$cfg['jobscriptretry']=(int)$_POST['jobscriptretry'];
	if (empty($cfg['jobstepretry']) or !is_int($cfg['jobstepretry']))
		$cfg['jobstepretry']=3;
	if (empty($cfg['jobscriptretry']) or !is_int($cfg['jobscriptretry']))
		$cfg['jobscriptretry']=5;
	$cfg['maxlogs']=abs((int)$_POST['maxlogs']);
	$cfg['gzlogs']=isset($_POST['gzlogs']) ? true : false;
	$cfg['dirlogs']=trailingslashit(str_replace('//','/',str_replace('\\','/',stripslashes(trim($_POST['dirlogs'])))));
	//set def. folders
	if (!isset($cfg['dirlogs']) or !is_dir($cfg['dirlogs']) or $cfg['dirlogs']=='/' or empty($cfg['dirlogs'])) {
		$rand = substr( md5( md5( SECURE_AUTH_KEY ) ), -5 );
		$cfg['dirlogs']=str_replace('\\','/',trailingslashit(WP_CONTENT_DIR)).'backwpup-'.$rand.'-logs/';
	}
	if (!isset($cfg['mailsendmail']) or empty($cfg['mailsendmail'])) {
		$cfg['mailsendmail']=substr(ini_get('sendmail_path'),0,strpos(ini_get('sendmail_path'),' -'));
	}
	if (update_option('backwpup',$cfg))
		$backwpup_message=__('Settings saved', 'backwpup');
}

//add Help
backwpup_contextual_help(
	'<div class="metabox-prefs">'.
	''.
	'</div>'
);
?>