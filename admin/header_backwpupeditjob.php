<?PHP
if (!defined('ABSPATH')) 
	die();

//Save Dropbox auth
if (isset($_GET['auth']) and $_GET['auth']=='Dropbox')  { 
	$jobid = (int) $_GET['jobid'];
	check_admin_referer('edit-job');
	$backwpup_message='';
	if ((int)$_GET['uid']>0 and !empty($_GET['oauth_token'])) {
		$reqtoken=backwpup_get_option('TEMP','DROPBOXAUTH');
		if ($reqtoken['oAuthRequestToken']==$_GET['oauth_token']) {
			//Get Access Tokens
			require_once (dirname(__FILE__).'/../libs/dropbox.php');
			//set boxtype and authkeys
			$root=backwpup_get_option('job_'.$jobid,'droperoot');
			if ($root=='sandbox')
				$dropbox = new backwpup_Dropbox($backwpup_cfg['DROPBOX_SANDBOX_APP_KEY'], $backwpup_cfg['DROPBOX_SANDBOX_APP_SECRET'],false);
			else
				$dropbox = new backwpup_Dropbox($backwpup_cfg['DROPBOX_APP_KEY'], $backwpup_cfg['DROPBOX_APP_SECRET'],true);

			$oAuthStuff = $dropbox->oAuthAccessToken($reqtoken['oAuthRequestToken'],$reqtoken['oAuthRequestTokenSecret']);
			//Save Tokens
			backwpup_update_option('job_'.$jobid,'dropetoken',$oAuthStuff['oauth_token']);
			backwpup_update_option('job_'.$jobid,'dropesecret',$oAuthStuff['oauth_token_secret']);
			$backwpup_message.=__('Dropbox authentication complete!','backwpup').'<br />';
		} else {
			$backwpup_message.=__('Wrong Token for Dropbox authentication received!','backwpup').'<br />';
		}
	} else {
		$backwpup_message.=__('No Dropbox authentication received!','backwpup').'<br />';	
	}
	backwpup_delete_option('TEMP','DROPBOXAUTH');
	$_POST['jobid']=$jobid;
}

//Save Boxnet auth
if (isset($_GET['auth']) and $_GET['auth']=='Boxnet')  { 
	$jobid = (int) $_GET['jobid'];
	check_admin_referer('edit-job');
	$backwpup_message='';
	if (!empty($_REQUEST['auth_token'])) {
		$reqtoken=backwpup_get_option('TEMP','BOXNETTICKET');
		if ($reqtoken==$_REQUEST['ticket'] and !empty($_REQUEST['ticket'])) {
			//Save Auth
			backwpup_update_option('job_'.$jobid,'boxnetauth',$_REQUEST['auth_token']);
			$backwpup_message.=__('Box.net authentication complete!','backwpup').'<br />';
		} else {
			$backwpup_message.=__('Wrong ticket for Box.net authentication received!','backwpup').'<br />';
		}
	} else {
		$backwpup_message.=__('No Box.net authentication received!','backwpup').'<br />';	
	}
	backwpup_delete_option('TEMP','BOXNETTICKET');
	$_POST['jobid']=$jobid;
}


//Save Job settings
if ((isset($_POST['save']) or isset($_POST['authbutton'])) and !empty($_POST['jobid'])) {
	check_admin_referer('edit-job');
	$jobvalues['jobid']=(int) $_POST['jobid'];
	$jobvalues['type']=(array)$_POST['type'];
	$jobvalues['name']= esc_html($_POST['name']);
	$jobvalues['activetype']=$_POST['activetype'];
	$jobvalues['cronselect']= $_POST['cronselect']=='basic' ? 'basic':'advanced';
	if ($jobvalues['cronselect']=='advanced') {
		if (empty($_POST['cronminutes']) or $_POST['cronminutes'][0]=='*') {
			if (!empty($_POST['cronminutes'][1]))
				$_POST['cronminutes']=array('*/'.$_POST['cronminutes'][1]);
			else
				$_POST['cronminutes']=array('*');
		}
		if (empty($_POST['cronhours']) or $_POST['cronhours'][0]=='*') {
			if (!empty($_POST['cronhours'][1]))
				$_POST['cronhours']=array('*/'.$_POST['cronhours'][1]);
			else
				$_POST['cronhours']=array('*');
		}
		if (empty($_POST['cronmday']) or $_POST['cronmday'][0]=='*') {
			if (!empty($_POST['cronmday'][1]))
				$_POST['cronmday']=array('*/'.$_POST['cronmday'][1]);
			else
				$_POST['cronmday']=array('*');
		}
		if (empty($_POST['cronmon']) or $_POST['cronmon'][0]=='*') {
			if (!empty($_POST['cronmon'][1]))
				$_POST['cronmon']=array('*/'.$_POST['cronmon'][1]);
			else
				$_POST['cronmon']=array('*');
		}
		if (empty($_POST['cronwday']) or $_POST['cronwday'][0]=='*') {
			if (!empty($_POST['cronwday'][1]))
				$_POST['cronwday']=array('*/'.$_POST['cronwday'][1]);
			else
				$_POST['cronwday']=array('*');
		}
		$jobvalues['cron']=implode(",",$_POST['cronminutes']).' '.implode(",",$_POST['cronhours']).' '.implode(",",$_POST['cronmday']).' '.implode(",",$_POST['cronmon']).' '.implode(",",$_POST['cronwday']);
	} else {
		if ($_POST['cronbtype']=='mon') {
			$jobvalues['cron']=$_POST['moncronminutes'].' '.$_POST['moncronhours'].' '.$_POST['moncronmday'].' * *';
		}
		if ($_POST['cronbtype']=='week') {
			$jobvalues['cron']=$_POST['weekcronminutes'].' '.$_POST['weekcronhours'].' * * '.$_POST['weekcronwday'];
		}
		if ($_POST['cronbtype']=='day') {
			$jobvalues['cron']=$_POST['daycronminutes'].' '.$_POST['daycronhours'].' * * *';
		}
		if ($_POST['cronbtype']=='hour') {
			$jobvalues['cron']=$_POST['hourcronminutes'].' * * * *';
		}
	}
	$jobvalues['cronnextrun']=backwpup_cron_next($jobvalues['cron']);
	$jobvalues['mailaddresslog']= isset($_POST['mailaddresslog']) ? sanitize_email($_POST['mailaddresslog']) : '';
	$jobvalues['mailerroronly']= (isset($_POST['mailerroronly']) && $_POST['mailerroronly']==1) ? true : false;
	$checedtables=array();
	if (isset($_POST['jobtabs'])) {
		foreach ($_POST['jobtabs'] as $dbtable) {
			$checedtables[]=rawurldecode($dbtable);
		}
	}
	global $wpdb;
	$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');
	$jobvalues['dbexclude']=array();
	foreach ($tables as $dbtable) {
		if (!in_array($dbtable,$checedtables))
			$jobvalues['dbexclude'][]=$dbtable;
	}	
	$jobvalues['dbdumpfile']=$_POST['dbdumpfile'];
	$jobvalues['dbdumpfilecompression']=$_POST['dbdumpfilecompression'];
	$jobvalues['maintenance']= (isset($_POST['maintenance']) && $_POST['maintenance']==1) ? true : false;
	$jobvalues['wpexportfile']=$_POST['wpexportfile'];
	$jobvalues['wpexportfilecompression']=$_POST['wpexportfilecompression'];
	$jobvalues['fileexclude']=isset($_POST['fileexclude']) ? stripslashes($_POST['fileexclude']) : '';
	$jobvalues['dirinclude']=isset($_POST['dirinclude']) ? stripslashes($_POST['dirinclude']) : '';
	$jobvalues['backuproot']= (isset($_POST['backuproot']) && $_POST['backuproot']==1) ? true : false;
	$jobvalues['backuprootexcludedirs']=!empty($_POST['backuprootexcludedirs']) ? (array)$_POST['backuprootexcludedirs'] : array();
	$jobvalues['backupcontent']= (isset($_POST['backupcontent']) && $_POST['backupcontent']==1) ? true : false;
	$jobvalues['backupcontentexcludedirs']=!empty($_POST['backupcontentexcludedirs']) ? (array)$_POST['backupcontentexcludedirs'] : array();
	$jobvalues['backupplugins']= (isset($_POST['backupplugins']) && $_POST['backupplugins']==1) ? true : false;
	$jobvalues['backuppluginsexcludedirs']=!empty($_POST['backuppluginsexcludedirs']) ? (array)$_POST['backuppluginsexcludedirs'] : array();
	$jobvalues['backupthemes']= (isset($_POST['backupthemes']) && $_POST['backupthemes']==1) ? true : false;
	$jobvalues['backupthemesexcludedirs']=!empty($_POST['backupthemesexcludedirs']) ? (array)$_POST['backupthemesexcludedirs'] : array();
	$jobvalues['backupuploads']= (isset($_POST['backupuploads']) && $_POST['backupuploads']==1) ? true : false;
	$jobvalues['backupuploadsexcludedirs']=!empty($_POST['backupuploadsexcludedirs']) ? (array)$_POST['backupuploadsexcludedirs'] : array();
	$jobvalues['backuptype']=$_POST['backuptype'];
	$jobvalues['fileprefix']= isset($_POST['fileprefix']) ? $_POST['fileprefix'] : '';
	$jobvalues['fileformart']=$_POST['fileformart'];
	$jobvalues['mailefilesize']=isset($_POST['mailefilesize']) ? (float)$_POST['mailefilesize'] : 0;
	$jobvalues['backupdir']=isset($_POST['backupdir']) ? stripslashes($_POST['backupdir']) : '';
	$jobvalues['maxbackups']=isset($_POST['maxbackups']) ? (int)$_POST['maxbackups'] : 0;
	$jobvalues['backupsyncnodelete']= (isset($_POST['backupsyncnodelete']) && $_POST['backupsyncnodelete']==1) ? true : false;
	$jobvalues['ftpsyncnodelete']= (isset($_POST['ftpsyncnodelete']) && $_POST['ftpsyncnodelete']==1) ? true : false;
	$jobvalues['awssyncnodelete']= (isset($_POST['awssyncnodelete']) && $_POST['awssyncnodelete']==1) ? true : false;
	$jobvalues['GStoragesyncnodelete']= (isset($_POST['GStoragesyncnodelete']) && $_POST['backupsyncnodelete']==1) ? true : false;
	$jobvalues['msazuresyncnodelete']= (isset($_POST['msazuresyncnodelete']) && $_POST['msazuresyncnodelete']==1) ? true : false;
	$jobvalues['rscsyncnodelete']= (isset($_POST['rscsyncnodelete']) && $_POST['rscsyncnodelete']==1) ? true : false;
	$jobvalues['dropesyncnodelete']= (isset($_POST['dropesyncnodelete']) && $_POST['dropesyncnodelete']==1) ? true : false;
	$jobvalues['boxnetsyncnodelete']= (isset($_POST['boxnetsyncnodelete']) && $_POST['boxnetsyncnodelete']==1) ? true : false;
	$jobvalues['sugarsyncnodelete']= (isset($_POST['sugarsyncnodelete']) && $_POST['sugarsyncnodelete']==1) ? true : false;
	$jobvalues['ftphost']=isset($_POST['ftphost']) ? $_POST['ftphost'] : '';
	$jobvalues['ftphostport']=!empty($_POST['ftphostport']) ? (int)$_POST['ftphostport'] : 21;
	$jobvalues['ftpuser']=isset($_POST['ftpuser']) ? $_POST['ftpuser'] : '';
	$jobvalues['ftppass']=isset($_POST['ftppass']) ? backwpup_encrypt($_POST['ftppass']) : '';
	$jobvalues['ftpdir']=isset($_POST['ftpdir']) ? stripslashes($_POST['ftpdir']) : '';
	$jobvalues['ftpmaxbackups']=isset($_POST['ftpmaxbackups']) ? (int)$_POST['ftpmaxbackups'] : 0;
	$jobvalues['ftpssl']= (isset($_POST['ftpssl']) && $_POST['ftpssl']==1) ? true : false;
	$jobvalues['ftppasv']= (isset($_POST['ftppasv']) && $_POST['ftppasv']==1) ? true : false;
	$jobvalues['dropemaxbackups']=isset($_POST['dropemaxbackups']) ? (int)$_POST['dropemaxbackups'] : 0;
	$jobvalues['droperoot']=$_POST['droperoot'];
	$jobvalues['dropedir']=isset($_POST['dropedir']) ? $_POST['dropedir'] : '';
	$jobvalues['boxnetbackups']=isset($_POST['boxnetbackups']) ? (int)$_POST['boxnetbackups'] : 0;	
	$jobvalues['boxnetdir']=isset($_POST['boxnetdir']) ? $_POST['boxnetdir'] : '';
	$jobvalues['awsAccessKey']=isset($_POST['awsAccessKey']) ? $_POST['awsAccessKey'] : '';
	$jobvalues['awsSecretKey']=isset($_POST['awsSecretKey']) ? $_POST['awsSecretKey'] : '';
	$jobvalues['awsrrs']= (isset($_POST['awsrrs']) && $_POST['awsrrs']==1) ? true : false;
	$jobvalues['awsssencrypt']= (isset($_POST['awsssencrypt']) && $_POST['awsssencrypt']=='AES256') ? 'AES256' : '';
	$jobvalues['awsBucket']=isset($_POST['awsBucket']) ? $_POST['awsBucket'] : '';
	$jobvalues['awsdir']=isset($_POST['awsdir']) ? stripslashes($_POST['awsdir']) : '';
	$jobvalues['awsmaxbackups']=isset($_POST['awsmaxbackups']) ? (int)$_POST['awsmaxbackups'] : 0;
	$jobvalues['GStorageAccessKey']=isset($_POST['GStorageAccessKey']) ? $_POST['GStorageAccessKey'] : '';
	$jobvalues['GStorageSecret']=isset($_POST['GStorageSecret']) ? $_POST['GStorageSecret'] : '';
	$jobvalues['GStorageBucket']=isset($_POST['GStorageBucket']) ? $_POST['GStorageBucket'] : '';
	$jobvalues['GStoragedir']=isset($_POST['GStoragedir']) ? stripslashes($_POST['GStoragedir']) : '';
	$jobvalues['GStoragemaxbackups']=isset($_POST['GStoragemaxbackups']) ? (int)$_POST['GStoragemaxbackups'] : 0;
	$jobvalues['msazureHost']=isset($_POST['msazureHost']) ? $_POST['msazureHost'] : 'blob.core.windows.net';
	$jobvalues['msazureAccName']=isset($_POST['msazureAccName']) ? $_POST['msazureAccName'] : '';
	$jobvalues['msazureKey']=isset($_POST['msazureKey']) ? $_POST['msazureKey'] : '';
	$jobvalues['msazureContainer']=isset($_POST['msazureContainer']) ? $_POST['msazureContainer'] : '';
	$jobvalues['msazuredir']=isset($_POST['msazuredir']) ? stripslashes($_POST['msazuredir']) : '';
	$jobvalues['msazuremaxbackups']=isset($_POST['msazuremaxbackups']) ? (int)$_POST['msazuremaxbackups'] : 0;
	$jobvalues['sugaruser']=isset($_POST['sugaruser']) ? $_POST['sugaruser'] : '';
	$jobvalues['sugarpass']=isset($_POST['sugarpass']) ? backwpup_encrypt($_POST['sugarpass']) : '';
	$jobvalues['sugardir']=isset($_POST['sugardir']) ? stripslashes($_POST['sugardir']) : '';
	$jobvalues['sugarroot']=isset($_POST['sugarroot']) ? $_POST['sugarroot'] : '';
	$jobvalues['sugarmaxbackups']=isset($_POST['sugarmaxbackups']) ? (int)$_POST['sugarmaxbackups'] : 0;
	$jobvalues['rscUsername']=isset($_POST['rscUsername']) ? $_POST['rscUsername'] : '';
	$jobvalues['rscAPIKey']=isset($_POST['rscAPIKey']) ? $_POST['rscAPIKey'] : '';
	$jobvalues['rscContainer']=isset($_POST['rscContainer']) ? $_POST['rscContainer'] : '';
	$jobvalues['rscdir']=isset($_POST['rscdir']) ? stripslashes($_POST['rscdir']) : '';
	$jobvalues['rscmaxbackups']=isset($_POST['rscmaxbackups']) ? (int)$_POST['rscmaxbackups'] : 0;
	$jobvalues['mailaddress']=isset($_POST['mailaddress']) ? sanitize_email($_POST['mailaddress']) : '';


	if (!empty($_POST['newawsBucket']) and !empty($_POST['awsAccessKey']) and !empty($_POST['awsSecretKey'])) { //create new s3 bucket if needed
		if (!class_exists('CFRuntime'))
			require_once(dirname(__FILE__).'/../libs/aws/sdk.class.php');
		try {
			$s3 = new AmazonS3($_POST['awsAccessKey'], $_POST['awsSecretKey']);
			$s3->create_bucket($_POST['newawsBucket'], $_POST['awsRegion']);
			$jobvalues['awsBucket']=$_POST['newawsBucket'];
		} catch (Exception $e) {
			$backwpup_message.=__($e->getMessage(),'backwpup').'<br />';
		}
	}
	
	if (!empty($_POST['GStorageAccessKey']) and !empty($_POST['GStorageSecret']) and !empty($_POST['newGStorageBucket'])) { //create new google storage bucket if needed
		if (!class_exists('CFRuntime'))
			require_once(dirname(__FILE__).'/../libs/aws/sdk.class.php');
		try {
			$gstorage = new AmazonS3($_POST['GStorageAccessKey'], $_POST['GStorageSecret']);
			$gstorage->set_hostname('commondatastorage.googleapis.com');
			$gstorage->allow_hostname_override(false);
			$gstorage->create_bucket($_POST['newGStorageBucket'],'');
			$jobvalues['GStorageBucket']=$_POST['newGStorageBucket'];
			sleep(1); //creation take a moment
		} catch (Exception $e) {
			$backwpup_message.=__($e->getMessage(),'backwpup').'<br />';
		}
	}
	
	if (!empty($_POST['newmsazureContainer'])  and !empty($_POST['msazureHost']) and !empty($_POST['msazureAccName']) and !empty($_POST['msazureKey'])) { //create new s3 bucket if needed
		if (!class_exists('Microsoft_WindowsAzure_Storage_Blob')) {
			require_once(dirname(__FILE__).'/../libs/Microsoft/WindowsAzure/Storage/Blob.php');
		}
		try {
			$storageClient = new Microsoft_WindowsAzure_Storage_Blob($_POST['msazureHost'],$_POST['msazureAccName'],$_POST['msazureKey']);
			$result = $storageClient->createContainer($_POST['newmsazureContainer']);
			$jobvalues['msazureContainer']=$result->Name;
		} catch (Exception $e) {
			$backwpup_message.=__($e->getMessage(),'backwpup').'<br />';
		}
	}	
	
	if (!empty($_POST['rscUsername']) and !empty($_POST['rscAPIKey']) and !empty($_POST['newrscContainer'])) { //create new Rackspase Container if needed
		if (!class_exists('CF_Authentication'))
			require_once(dirname(__FILE__).'/../libs/rackspace/cloudfiles.php');
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
	
	
	if (isset($_POST['authbutton']) and $_POST['authbutton']==__('Delete Dropbox authentication!', 'backwpup')) {
		$jobvalues['dropetoken']='';
		$jobvalues['dropesecret']='';
		$backwpup_message.=__('Dropbox authentication deleted!','backwpup').'<br />';
	}

	if (isset($_POST['authbutton']) and $_POST['authbutton']==__('Delete Box.net authentication!', 'backwpup')) {
		$jobvalues['boxnetauth']='';
		$backwpup_message.=__('Box.net authentication deleted!','backwpup').'<br />';
	}
	
	//save chages
	$jobvalues=backwpup_get_job_vars($jobvalues['jobid'],$jobvalues);
	foreach ($jobvalues as $jobvaluename => $jobvaluevalue) {
		backwpup_update_option('job_'.$jobvalues['jobid'],$jobvaluename,$jobvaluevalue);
	}

	//get dropbox auth	
	if (isset($_POST['authbutton']) and $_POST['authbutton']==__('Dropbox authenticate!', 'backwpup')) {
		require_once (dirname(__FILE__).'/../libs/dropbox.php');
		//set boxtype and authkeys
		if ($jobvalues['droperoot']=='sandbox')
			$dropbox = new backwpup_Dropbox($backwpup_cfg['DROPBOX_SANDBOX_APP_KEY'], $backwpup_cfg['DROPBOX_SANDBOX_APP_SECRET'],false);
		else
			$dropbox = new backwpup_Dropbox($backwpup_cfg['DROPBOX_APP_KEY'], $backwpup_cfg['DROPBOX_APP_SECRET'],true);

		// let the user authorize (user will be redirected)
		$response = $dropbox->oAuthAuthorize(backwpup_admin_url('admin.php').'?page=backwpupeditjob&jobid='.$jobvalues['jobid'].'&auth=Dropbox&_wpnonce='.wp_create_nonce('edit-job'));
		// save oauth_token_secret 
		backwpup_update_option('TEMP','DROPBOXAUTH',array('oAuthRequestToken'=>$response['oauth_token'],'oAuthRequestTokenSecret' => $response['oauth_token_secret']));
		//forward to auth page
		wp_redirect($response['authurl']);
	}
	
	//get box.net auth	
	if (isset($_POST['authbutton']) and $_POST['authbutton']==__('Box.net authenticate!', 'backwpup')) {
		//set boxtype and authkeys
		$raw_response=wp_remote_get('http://www.box.net/api/1.0/rest?action=get_ticket&api_key='.$backwpup_cfg['BOXNET']);
		if (!is_wp_error($raw_response) && 200 == wp_remote_retrieve_response_code($raw_response)) {
			$response = simplexml_load_string(wp_remote_retrieve_body($raw_response)); 
		}
		if ($response->status=='get_ticket_ok') {
			$callback=backwpup_admin_url('admin.php').'?page=backwpupeditjob&jobid='.$jobvalues['jobid'].'&auth=Boxnet&_wpnonce='.wp_create_nonce('edit-job');
			$authurl=$backwpupapi->boxnetauthproxy((string)$response->ticket,$callback);
			// save oauth_token_secret 
			backwpup_update_option('TEMP','BOXNETTICKET',(string)$response->ticket);
			//forward to auth page
			wp_redirect($authurl);
		} else {
			$backwpup_message.=__('Box.net get authentication faild!','backwpup').'<br />';
		}
	}
	
	//make api call to backwpup.com
	global $backwpupapi;
	$backwpupapi->cronupdate();
	
	$_POST['jobid']=$jobvalues['jobid'];
	$url=backwpup_jobrun_url('runnow',$jobvalues['jobid'],false);
	$backwpup_message.=str_replace('%1',$jobvalues['name'],__('Job \'%1\' changes saved.', 'backwpup')).' <a href="'.backwpup_admin_url('admin.php').'?page=backwpup">'.__('Jobs overview', 'backwpup').'</a> | <a href="'.$url['url'].'">'.__('Run now', 'backwpup').'</a>';
}

$dests=explode(',',strtoupper(BACKWPUP_DESTS));

//load java
wp_enqueue_script('common');
wp_enqueue_script('wp-lists');
wp_enqueue_script('postbox');

//add several metaboxes now, all metaboxes registered during load page can be switched off/on at "Screen Options" automatically, nothing special to do therefore
add_meta_box('backwpup_jobedit_backupfile', __('Backup File','backwpup'), array('BackWPup_editjob_metaboxes','backupfile'), get_current_screen()->id, 'side', 'default');
add_meta_box('backwpup_jobedit_sendlog', __('Send log','backwpup'), array('BackWPup_editjob_metaboxes','sendlog'), get_current_screen()->id, 'side', 'default');
add_meta_box('backwpup_jobedit_destfolder', __('Backup to Folder','backwpup'), array('BackWPup_editjob_metaboxes','destfolder'), get_current_screen()->id, 'advanced', 'core');
add_meta_box('backwpup_jobedit_destmail', __('Backup to E-Mail','backwpup'), array('BackWPup_editjob_metaboxes','destmail'), get_current_screen()->id, 'advanced', 'core');
if (in_array('FTP',$dests))
	add_meta_box('backwpup_jobedit_destftp', __('Backup to FTP Server','backwpup'), array('BackWPup_editjob_metaboxes','destftp'), get_current_screen()->id, 'advanced', 'default');
if (in_array('DROPBOX',$dests))
	add_meta_box('backwpup_jobedit_destdropbox', __('Backup to Dropbox','backwpup'), array('BackWPup_editjob_metaboxes','destdropbox'), get_current_screen()->id, 'advanced', 'default');
if (in_array('BOXNET',$dests))
	add_meta_box('backwpup_jobedit_destboxnet', __('Backup to Box.net','backwpup'), array('BackWPup_editjob_metaboxes','destboxnet'), get_current_screen()->id, 'advanced', 'default');
if (in_array('SUGARSYNC',$dests))
	add_meta_box('backwpup_jobedit_destsugarsync', __('Backup to SugarSync','backwpup'), array('BackWPup_editjob_metaboxes','destsugarsync'), get_current_screen()->id, 'advanced', 'default');
if (in_array('S3',$dests))
	add_meta_box('backwpup_jobedit_dests3', __('Backup to Amazon S3','backwpup'), array('BackWPup_editjob_metaboxes','dests3'), get_current_screen()->id, 'advanced', 'default');
if (in_array('GSTORAGE',$dests))
	add_meta_box('backwpup_jobedit_destgstorage', __('Backup to Google storage','backwpup'), array('BackWPup_editjob_metaboxes','destgstorage'), get_current_screen()->id, 'advanced', 'default');
if (in_array('MSAZURE',$dests))
	add_meta_box('backwpup_jobedit_destazure', __('Backup to Micosoft Azure (Blob)','backwpup'), array('BackWPup_editjob_metaboxes','destazure'), get_current_screen()->id, 'advanced', 'default');
if (in_array('RSC',$dests))
	add_meta_box('backwpup_jobedit_destrsc', __('Backup to Rackspace Cloud','backwpup'), array('BackWPup_editjob_metaboxes','destrsc'), get_current_screen()->id, 'advanced', 'default');

//add clumns
add_screen_option('layout_columns', array('max' => 2, 'default' => 2));

//add Help
if (method_exists(get_current_screen(),'add_help_tab')) {
	get_current_screen()->add_help_tab( array(
		'id'      => 'overview',
		'title'   => __('Overview'),
		'content'	=>
		'<p>' . '</p>'
	) );
}
?>