<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

//Save Dropbox  settings
if (isset($_GET['dropboxauth']) and $_GET['dropboxauth']=='AccessToken')  { 
	$jobid = (int) $_GET['jobid'];
	check_admin_referer('edit-job');
	$backwpup_message='';
	if ((int)$_GET['uid']>0 and !empty($_GET['oauth_token'])) {
		$reqtoken=get_transient('backwpup_dropboxrequest');
		if ($reqtoken['oAuthRequestToken']==$_GET['oauth_token']) {
			//Get Access Tokens
			if (!class_exists('Dropbox'))
				require_once (dirname(__FILE__).'/libs/dropbox/dropbox.php');
			$dropbox = new Dropbox(BACKWPUP_DROPBOX_APP_KEY, BACKWPUP_DROPBOX_APP_SECRET);
			$oAuthStuff = $dropbox->oAuthAccessToken($reqtoken['oAuthRequestToken'],$reqtoken['oAuthRequestTokenSecret']);
			var_dump($oAuthStuff);
			//Save Tokens
			$jobs=get_option('backwpup_jobs');
			$jobs[$jobid]['dropetoken']=$oAuthStuff['oauth_token'];
			$jobs[$jobid]['dropesecret']=$oAuthStuff['oauth_token_secret'];
			update_option('backwpup_jobs',$jobs);
			$backwpup_message.=__('Dropbox authentication complete!','backwpup').'<br />';
		} else {
			$backwpup_message.=__('Wrong Token for Dropbox authentication reseved!','backwpup').'<br />';
		}
	} else {
		$backwpup_message.=__('No Dropbox authentication reseved!','backwpup').'<br />';	
	}
	delete_transient('backwpup_dropboxrequest');
	$_POST['jobid']=$jobid;
}

//Save Job settings
if (isset($_POST['submit']) and  !empty($_POST['jobid'])) {
	$jobid = (int) $_POST['jobid'];
	check_admin_referer('edit-job');
	$backwpup_message='';
	$jobs=get_option('backwpup_jobs'); //Load Settings
	
	$jobs[$jobid]['type']= implode('+',(array)$_POST['type']);
	$jobs[$jobid]['name']= esc_html($_POST['name']);
	$jobs[$jobid]['activated']= $_POST['activated']==1 ? true : false;
	if ($_POST['cronminutes'][0]=='*' or empty($_POST['cronminutes'])) {
		if (!empty($_POST['cronminutes'][1]))
			$_POST['cronminutes']=array('*/'.$_POST['cronminutes'][1]);
		else
			$_POST['cronminutes']=array('*');
	}
	if ($_POST['cronhours'][0]=='*' or empty($_POST['cronhours'])) {
		if (!empty($_POST['cronhours'][1]))
			$_POST['cronhours']=array('*/'.$_POST['cronhours'][1]);
		else
			$_POST['cronhours']=array('*');
	}
	if ($_POST['cronmday'][0]=='*' or empty($_POST['cronmday'])) {
		if (!empty($_POST['cronmday'][1]))
			$_POST['cronmday']=array('*/'.$_POST['cronmday'][1]);
		else
			$_POST['cronmday']=array('*');
	}
	if ($_POST['cronmon'][0]=='*' or empty($_POST['cronmon'])) {
		if (!empty($_POST['cronmon'][1]))
			$_POST['cronmon']=array('*/'.$_POST['cronmon'][1]);
		else
			$_POST['cronmon']=array('*');
	}
	if ($_POST['cronwday'][0]=='*' or empty($_POST['cronwday'])) {
		if (!empty($_POST['cronwday'][1]))
			$_POST['cronwday']=array('*/'.$_POST['cronwday'][1]);
		else
			$_POST['cronwday']=array('*');
	}
	$jobs[$jobid]['cron']=implode(",",$_POST['cronminutes']).' '.implode(",",$_POST['cronhours']).' '.implode(",",$_POST['cronmday']).' '.implode(",",$_POST['cronmon']).' '.implode(",",$_POST['cronwday']);
	$jobs[$jobid]['cronnextrun']=backwpup_cron_next($jobs[$jobid]['cron']);
	$jobs[$jobid]['mailaddresslog']=sanitize_email($_POST['mailaddresslog']);
	$jobs[$jobid]['mailerroronly']= $_POST['mailerroronly']==1 ? true : false;
	$jobs[$jobid]['dbtables']=(array)$_POST['dbtables'];
	$jobs[$jobid]['dbshortinsert']=$_POST['dbshortinsert']==1 ? true : false;
	$jobs[$jobid]['maintenance']= $_POST['maintenance']==1 ? true : false;
	$jobs[$jobid]['fileexclude']=stripslashes($_POST['fileexclude']);
	$jobs[$jobid]['dirinclude']=stripslashes($_POST['dirinclude']);
	$jobs[$jobid]['backuproot']= $_POST['backuproot']==1 ? true : false;
	$jobs[$jobid]['backuprootexcludedirs']=(array)$_POST['backuprootexcludedirs'];
	$jobs[$jobid]['backupcontent']= $_POST['backupcontent']==1 ? true : false;
	$jobs[$jobid]['backupcontentexcludedirs']=(array)$_POST['backupcontentexcludedirs'];
	$jobs[$jobid]['backupplugins']= $_POST['backupplugins']==1 ? true : false;
	$jobs[$jobid]['backuppluginsexcludedirs']=(array)$_POST['backuppluginsexcludedirs'];
	$jobs[$jobid]['backupthemes']= $_POST['backupthemes']==1 ? true : false;
	$jobs[$jobid]['backupthemesexcludedirs']=(array)$_POST['backupthemesexcludedirs'];
	$jobs[$jobid]['backupuploads']= $_POST['backupuploads']==1 ? true : false;
	$jobs[$jobid]['backupuploadsexcludedirs']=(array)$_POST['backupuploadsexcludedirs'];
	$jobs[$jobid]['fileprefix']=$_POST['fileprefix'];
	$jobs[$jobid]['fileformart']=$_POST['fileformart'];
	$jobs[$jobid]['mailefilesize']=(float)$_POST['mailefilesize'];
	$jobs[$jobid]['backupdir']=stripslashes($_POST['backupdir']);
	$jobs[$jobid]['maxbackups']=(int)$_POST['maxbackups'];
	$jobs[$jobid]['ftphost']=$_POST['ftphost'];
	$jobs[$jobid]['ftpuser']=$_POST['ftpuser'];
	$jobs[$jobid]['ftppass']=base64_encode($_POST['ftppass']);
	$jobs[$jobid]['ftpdir']=stripslashes($_POST['ftpdir']);
	$jobs[$jobid]['ftpmaxbackups']=(int)$_POST['ftpmaxbackups'];
	$jobs[$jobid]['ftpssl']= $_POST['ftpssl']==1 ? true : false;
	$jobs[$jobid]['ftppasv']= $_POST['ftppasv']==1 ? true : false;
	$jobs[$jobid]['dropemaxbackups']=(int)$_POST['dropemaxbackups'];
	$jobs[$jobid]['dropedir']=$_POST['dropedir'];
	$jobs[$jobid]['awsAccessKey']=$_POST['awsAccessKey'];
	$jobs[$jobid]['awsSecretKey']=$_POST['awsSecretKey'];
	$jobs[$jobid]['awsrrs']= $_POST['awsrrs']==1 ? true : false;
	$jobs[$jobid]['awsBucket']=$_POST['awsBucket'];
	$jobs[$jobid]['awsdir']=stripslashes($_POST['awsdir']);
	$jobs[$jobid]['awsmaxbackups']=(int)$_POST['awsmaxbackups'];
	$jobs[$jobid]['msazureHost']=$_POST['msazureHost'];
	$jobs[$jobid]['msazureAccName']=$_POST['msazureAccName'];
	$jobs[$jobid]['msazureKey']=$_POST['msazureKey'];
	$jobs[$jobid]['msazureContainer']=$_POST['msazureContainer'];
	$jobs[$jobid]['msazuredir']=stripslashes($_POST['msazuredir']);
	$jobs[$jobid]['msazuremaxbackups']=(int)$_POST['msazuremaxbackups'];
	$jobs[$jobid]['sugaruser']=$_POST['sugaruser'];
	$jobs[$jobid]['sugarpass']=base64_encode($_POST['sugarpass']);
	$jobs[$jobid]['sugardir']=stripslashes($_POST['sugardir']);
	$jobs[$jobid]['sugarroot']=$_POST['sugarroot'];
	$jobs[$jobid]['sugarmaxbackups']=(int)$_POST['sugarmaxbackups'];
	$jobs[$jobid]['rscUsername']=$_POST['rscUsername'];
	$jobs[$jobid]['rscAPIKey']=$_POST['rscAPIKey'];
	$jobs[$jobid]['rscContainer']=$_POST['rscContainer'];
	$jobs[$jobid]['rscdir']=stripslashes($_POST['rscdir']);
	$jobs[$jobid]['rscmaxbackups']=(int)$_POST['rscmaxbackups'];
	$jobs[$jobid]['mailaddress']=sanitize_email($_POST['mailaddress']);
	//unset old vars
	unset($jobs[$jobid]['scheduletime']);
	unset($jobs[$jobid]['scheduleintervaltype']);
	unset($jobs[$jobid]['scheduleintervalteimes']);
	unset($jobs[$jobid]['scheduleinterval']);

	$jobs[$jobid]=backwpup_check_job_vars($jobs[$jobid],$jobid); //check vars and set def.

	if (!empty($_POST['newawsBucket']) and !empty($_POST['awsAccessKey']) and !empty($_POST['awsSecretKey'])) { //create new s3 bucket if needed
		if (!class_exists('CFRuntime'))
			require_once(dirname(__FILE__).'/libs/aws/sdk.class.php');
		try {
			$s3 = new AmazonS3($_POST['awsAccessKey'], $_POST['awsSecretKey']);
			$s3->create_bucket($_POST['newawsBucket'], $_POST['awsRegion']);
			$jobs[$jobid]['awsBucket']=$_POST['newawsBucket'];
		} catch (Exception $e) {
			$backwpup_message.=__($e->getMessage(),'backwpup').'<br />';
		}
	}

	if (!empty($_POST['newmsazureContainer'])  and !empty($_POST['msazureHost']) and !empty($_POST['msazureAccName']) and !empty($_POST['msazureKey'])) { //create new s3 bucket if needed
		if (!class_exists('Microsoft_WindowsAzure_Storage_Blob')) {
			set_include_path(get_include_path().PATH_SEPARATOR.dirname(__FILE__).'/libs');
			require_once 'Microsoft/WindowsAzure/Storage/Blob.php';
		}
		try {
			$storageClient = new Microsoft_WindowsAzure_Storage_Blob($_POST['msazureHost'],$_POST['msazureAccName'],$_POST['msazureKey']);
			$result = $storageClient->createContainer($_POST['newmsazureContainer']);
			$jobs[$jobid]['msazureContainer']=$result->Name;
		} catch (Exception $e) {
			$backwpup_message.=__($e->getMessage(),'backwpup').'<br />';
		}
	}	
	
	if (!empty($_POST['rscUsername']) and !empty($_POST['rscAPIKey']) and !empty($_POST['newrscContainer'])) { //create new Rackspase Container if needed
		if (!class_exists('CF_Authentication'))
			require_once(plugin_dir_path(__FILE__).'libs/rackspace/cloudfiles.php');
		try {
			$auth = new CF_Authentication($_POST['rscUsername'], $_POST['rscAPIKey']);
			if ($auth->authenticate()) {
				$conn = new CF_Connection($auth);
				$public_container = $conn->create_container($_POST['newrscContainer']);
				$public_container->make_private();
			}
		} catch (Exception $e) {
			$backwpup_message.=__($e->getMessage(),'backwpup').'<br />';
		}
	}
	
	if ($_POST['dropboxauth']==__('Authenticate!', 'backwpup')) {
		if (!class_exists('Dropbox'))
			require_once (dirname(__FILE__).'/libs/dropbox/dropbox.php');
		$dropbox = new Dropbox(BACKWPUP_DROPBOX_APP_KEY, BACKWPUP_DROPBOX_APP_SECRET);
		// request request tokens
		$response = $dropbox->oAuthRequestToken();
		// save job id and referer
		set_transient('backwpup_dropboxrequest',array('oAuthRequestToken' => $response['oauth_token'],'oAuthRequestTokenSecret' => $response['oauth_token_secret']),3600);
		// let the user authorize (user will be redirected)
		$response = $dropbox->oAuthAuthorize($response['oauth_token'], get_admin_url().'admin.php?page=backwpupeditjob&jobid='.$jobid.'&dropboxauth=AccessToken&_wpnonce='.wp_create_nonce('edit-job'));
	}
	
	if ($_POST['dropboxauth']==__('Delete!', 'backwpup')) {
		$jobs[$jobid]['dropetoken']='';
		$jobs[$jobid]['dropesecret']='';
		$backwpup_message.=__('Dropbox authentication deleted!','backwpup').'<br />';
	}

	//save chages
	update_option('backwpup_jobs',$jobs);
	$_POST['jobid']=$jobid;
	$backwpup_message.=str_replace('%1',$jobs[$jobid]['name'],__('Job \'%1\' changes saved.', 'backwpup')).' <a href="admin.php?page=backwpup">'.__('Jobs overview.', 'backwpup').'</a>';
}


$dests=explode(',',strtoupper(BACKWPUP_DESTS));

//load java
wp_enqueue_script('common');
wp_enqueue_script('wp-lists');
wp_enqueue_script('postbox');

//add several metaboxes now, all metaboxes registered during load page can be switched off/on at "Screen Options" automatically, nothing special to do therefore
add_meta_box('backwpup_jobedit_backupfile', __('Backup File','backwpup'), 'backwpup_jobedit_metabox_backupfile', $current_screen->id, 'side', 'default');
add_meta_box('backwpup_jobedit_sendlog', __('Send log','backwpup'), 'backwpup_jobedit_metabox_sendlog', $current_screen->id, 'side', 'default');
if (in_array('FTP',$dests))
	add_meta_box('backwpup_jobedit_destftp', __('Backup to FTP Server','backwpup'), 'backwpup_jobedit_metabox_destftp', $current_screen->id, 'advanced', 'default');
if (in_array('S3',$dests))
	add_meta_box('backwpup_jobedit_dests3', __('Backup to Amazon S3','backwpup'), 'backwpup_jobedit_metabox_dests3', $current_screen->id, 'advanced', 'default');
if (in_array('MSAZURE',$dests))
	add_meta_box('backwpup_jobedit_destazure', __('Backup to Micosoft Azure (Blob)','backwpup'), 'backwpup_jobedit_metabox_destazure', $current_screen->id, 'advanced', 'default');
if (in_array('RSC',$dests))
	add_meta_box('backwpup_jobedit_destrsc', __('Backup to Rackspace Cloud','backwpup'), 'backwpup_jobedit_metabox_destrsc', $current_screen->id, 'advanced', 'default');
if (in_array('DROPBOX',$dests))
	add_meta_box('backwpup_jobedit_destdropbox', __('Backup to Dropbox','backwpup'), 'backwpup_jobedit_metabox_destdropbox', $current_screen->id, 'advanced', 'default');
if (in_array('SUGARSYNC',$dests))
	add_meta_box('backwpup_jobedit_destsugarsync', __('Backup to SugarSync','backwpup'), 'backwpup_jobedit_metabox_destsugarsync', $current_screen->id, 'advanced', 'default');
//add clumns
add_screen_option('layout_columns', array('max' => 2));

//add Help
backwpup_contextual_help(
	'<div class="metabox-prefs">'.
	''.
	'</div>'
);


?>