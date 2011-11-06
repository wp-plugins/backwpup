<?PHP
if (!defined('ABSPATH')) 
	die();


if (isset($_POST['submit']) and isset($_POST['action']) and $_POST['action']=='update') {
	global $wpdb,$backwpup_cfg;
	check_admin_referer('backwpup-cfg');

	backwpup_update_option('CFG','mailsndemail',sanitize_email($_POST['mailsndemail']));
	backwpup_update_option('CFG','mailsndname',$_POST['mailsndname']);
	backwpup_update_option('CFG','showadminbar',isset($_POST['showadminbar']) ? true : false);
    if (100>$_POST['jobstepretry'] and 0<$_POST['jobstepretry']) 
		$_POST['jobstepretry']=(int)$_POST['jobstepretry'];
	if (empty($_POST['jobstepretry']) or !is_int($_POST['jobstepretry']))
		$_POST['jobstepretry']=3;
	backwpup_update_option('CFG','jobstepretry',$_POST['jobstepretry']);	
	if (100>$_POST['jobscriptretry'] and 0<$_POST['jobscriptretry']) 
		$_POST['jobscriptretry']=(int)$_POST['jobscriptretry'];
	if (empty($_POST['jobscriptretry']) or !is_int($_POST['jobscriptretry']))
		$_POST['jobscriptretry']=5;
	backwpup_update_option('CFG','jobscriptretry',$_POST['jobscriptretry']);
	backwpup_update_option('CFG','maxlogs',abs((int)$_POST['maxlogs']));
	backwpup_update_option('CFG','gzlogs',isset($_POST['gzlogs']) ? true : false);
	backwpup_update_option('CFG','phpzip',isset($_POST['phpzip']) ? true : false);
	backwpup_update_option('CFG','unloadtranslations',isset($_POST['unloadtranslations']) ? true : false);
	backwpup_update_option('CFG','apicronservice',isset($_POST['apicronservice']) ? true : false);
	backwpup_update_option('CFG','httpauthuser',$_POST['httpauthuser']);
	backwpup_update_option('CFG','httpauthpassword',base64_encode($_POST['httpauthpassword']));
	$_POST['dirlogs']=rtrim(str_replace('\\','/',$_POST['dirlogs']),'/').'/';
	//set def. folders
	if (!isset($_POST['dirlogs']) or $_POST['dirlogs']=='/' or empty($_POST['dirlogs'])) {
		$rand = substr( md5( md5( SECURE_AUTH_KEY ) ), -5 );
		$_POST['dirlogs']=str_replace('\\','/',trailingslashit(WP_CONTENT_DIR)).'backwpup-'.$rand.'-logs/';
	}
	if (substr($_POST['dirlogs'],0,1)!='/' and substr($_POST['dirlogs'],1,1)!=':') //add abspath if not absolute
		$_POST['dirlogs']=rtrim(str_replace('\\','/',ABSPATH),'/').'/'.$_POST['dirlogs'];
	backwpup_update_option('CFG','dirlogs',$_POST['dirlogs']);

	//load cfg
	$cfgs=$wpdb->get_results("SELECT name,value FROM `".$wpdb->prefix."backwpup` WHERE main_name='CFG'");
	foreach ($cfgs as $cfg) {
		$backwpup_cfg[$cfg->name]=maybe_unserialize($cfg->value);
	}
	$backwpupapi=new backwpup_api();
	$backwpupapi->cronupdate();
	
	$backwpup_message=__('Settings saved', 'backwpup');
}

//add Help
backwpup_contextual_help();
?>