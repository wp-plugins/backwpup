<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

//Save Dropbox auth
if (isset($_GET['auth']) and $_GET['auth']=='DropBox')  {
	$jobid = (int) $_GET['jobid'];
	if (!wp_verify_nonce($_GET['_wpnonce'],'edit-job')) {
		wp_nonce_ays('edit-job');
		die();
	}
	$backwpup_message='';
	if ((int)$_GET['uid']>0 and !empty($_GET['oauth_token_backwpup'])) {
		$reqtoken=backwpup_get_option('temp','dropboxauth');
		if ($reqtoken['oAuthRequestToken']==$_GET['oauth_token_backwpup']) {
			//Get Access Tokens
			require_once (dirname(__FILE__).'/../libs/dropbox.php');
			$dropbox = new backwpup_Dropbox(backwpup_get_option('job_'.$jobid,'droperoot'));
			$oAuthStuff = $dropbox->oAuthAccessToken($reqtoken['oAuthRequestToken'],$reqtoken['oAuthRequestTokenSecret']);
			//Save Tokens
			backwpup_update_option('job_'.$jobid,'dropetoken',$oAuthStuff['oauth_token']);
			backwpup_update_option('job_'.$jobid,'dropesecret',$oAuthStuff['oauth_token_secret']);
			$backwpup_message.=__('Dropbox authentication complete!','backwpup').'<br />';
		} else {
			$backwpup_message.=__('Wrong Token for DropBox authentication received!','backwpup').'<br />';
		}
	} else {
		$backwpup_message.=__('No DropBox authentication received!','backwpup').'<br />';
	}
	backwpup_delete_option('temp','dropboxauth');
	$_POST['jobid']=$jobid;
}

//Save and check Job settings
if ((isset($_POST['save']) or isset($_POST['authbutton'])) and !empty($_POST['jobid'])) {
	global $wpdb;
	check_admin_referer('edit-job');
	$main='job_'.(int)$_POST['jobid'];
	backwpup_update_option($main,'jobid',(int) $_POST['jobid']);
	foreach((array)$_POST['type'] as $key => $value) {
		$value=strtoupper($value);
		if (!in_array($value,backwpup_job_types()))
			unset($_POST['type'][$key]);
	}
	sort($_POST['type']);
	backwpup_update_option($main,'type',$_POST['type']);
	backwpup_update_option($main,'name',esc_html($_POST['name']));
	if ($_POST['activetype']=='' or $_POST['activetype']=='wpcron' or $_POST['activetype']=='backwpupapi')
		backwpup_update_option($main,'activetype',$_POST['activetype']);
	backwpup_update_option($main,'cronselect',$_POST['cronselect']=='advanced' ? 'advanced':'basic');
	if ($_POST['cronselect']=='advanced') {
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
		$cron=implode(",",$_POST['cronminutes']).' '.implode(",",$_POST['cronhours']).' '.implode(",",$_POST['cronmday']).' '.implode(",",$_POST['cronmon']).' '.implode(",",$_POST['cronwday']);
		backwpup_update_option($main,'cron',$cron);
	} else {
		if ($_POST['cronbtype']=='mon')
			backwpup_update_option($main,'cron',$_POST['moncronminutes'].' '.$_POST['moncronhours'].' '.$_POST['moncronmday'].' * *');
		if ($_POST['cronbtype']=='week')
			backwpup_update_option($main,'cron',$_POST['weekcronminutes'].' '.$_POST['weekcronhours'].' * * '.$_POST['weekcronwday']);
		if ($_POST['cronbtype']=='day')
			backwpup_update_option($main,'cron',$_POST['daycronminutes'].' '.$_POST['daycronhours'].' * * *');
		if ($_POST['cronbtype']=='hour')
			backwpup_update_option($main,'cron',$_POST['hourcronminutes'].' * * * *');
	}
	$cronnextrun=backwpup_cron_next(backwpup_get_option($main,'cron'));
	backwpup_update_option($main,'cronnextrun',$cronnextrun);
	backwpup_update_option($main,'mailaddresslog',sanitize_email($_POST['mailaddresslog']));
	backwpup_update_option($main,'mailerroronly',(isset($_POST['mailerroronly']) && $_POST['mailerroronly']==1) ? true : false);
	$check_db_tables=array();
	if (isset($_POST['jobtabs'])) {
		foreach ($_POST['jobtabs'] as $dbtable) {
			$check_db_tables[]=rawurldecode($dbtable);
		}
	}
	$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');
	$dbexclude=array();
	foreach ($tables as $dbtable) {
		if (!in_array($dbtable,$check_db_tables))
			$dbexclude[]=$dbtable;
	}
	backwpup_update_option($main,'dbexclude',$dbexclude);
	backwpup_update_option($main,'dbdumpfile',$_POST['dbdumpfile']);
	if ($_POST['dbdumpfilecompression']=='' or $_POST['dbdumpfilecompression']=='gz' or $_POST['dbdumpfilecompression']=='bz2')
		backwpup_update_option($main,'dbdumpfilecompression',$_POST['dbdumpfilecompression']);
	backwpup_update_option($main,'maintenance',(isset($_POST['maintenance']) && $_POST['maintenance']==1) ? true : false);
	backwpup_update_option($main,'wpexportfile',$_POST['wpexportfile']);
	if ($_POST['wpexportfilecompression']=='' or $_POST['wpexportfilecompression']=='gz' or $_POST['wpexportfilecompression']=='bz2')
		backwpup_update_option($main,'wpexportfilecompression',$_POST['wpexportfilecompression']);
	$fileexclude=explode(',',stripslashes($_POST['fileexclude']));
	foreach($fileexclude as $key => $value) {
		$fileexclude[$key]=str_replace('//','/',str_replace('\\','/',trim($value)));
		if (empty($fileexclude[$key]))
			unset($fileexclude[$key]);
	}
	sort($fileexclude);
	backwpup_update_option($main,'fileexclude',implode(',',$fileexclude));
	$dirinclude=explode(',',stripslashes($_POST['dirinclude']));
	foreach($dirinclude as $key => $value) {
		$dirinclude[$key]=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($value))));
		if ($dirinclude[$key]=='/' or empty($dirinclude[$key]) or !is_dir($dirinclude[$key]))
			unset($dirinclude[$key]);
	}
	sort($dirinclude);
	backwpup_update_option($main,'dirinclude',implode(',',$dirinclude));
	backwpup_update_option($main,'backupexcludethumbs',(isset($_POST['backupexcludethumbs']) && $_POST['backupexcludethumbs']==1) ? true : false);
	backwpup_update_option($main,'backupspecialfiles',(isset($_POST['backupspecialfiles']) && $_POST['backupspecialfiles']==1) ? true : false);
	backwpup_update_option($main,'backuproot',(isset($_POST['backuproot']) && $_POST['backuproot']==1) ? true : false);
	if (!isset($_POST['backuprootexcludedirs']) or !is_array($_POST['backuprootexcludedirs']))
		$_POST['backuprootexcludedirs']=array();
	foreach($_POST['backuprootexcludedirs'] as $key => $value) {
		$_POST['backuprootexcludedirs'][$key]=str_replace('//','/',str_replace('\\','/',trim($value)));
		if (empty($_POST['backuprootexcludedirs'][$key]) or $_POST['backuprootexcludedirs'][$key]=='/' or !is_dir($_POST['backuprootexcludedirs'][$key]))
			unset($_POST['backuprootexcludedirs'][$key]);
	}
	sort($_POST['backuprootexcludedirs']);
	backwpup_update_option($main,'backuprootexcludedirs',$_POST['backuprootexcludedirs']);
	backwpup_update_option($main,'backupcontent',(isset($_POST['backupcontent']) && $_POST['backupcontent']==1) ? true : false);
	if (!isset($_POST['backupcontentexcludedirs']) or !is_array($_POST['backupcontentexcludedirs']))
		$_POST['backupcontentexcludedirs']=array();
	foreach($_POST['backupcontentexcludedirs'] as $key => $value) {
		$_POST['backupcontentexcludedirs'][$key]=str_replace('//','/',str_replace('\\','/',trim($value)));
		if (empty($_POST['backupcontentexcludedirs'][$key]) or $_POST['backupcontentexcludedirs'][$key]=='/' or !is_dir($_POST['backupcontentexcludedirs'][$key]))
			unset($_POST['backupcontentexcludedirs'][$key]);
	}
	sort($_POST['backupcontentexcludedirs']);
	backwpup_update_option($main,'backupcontentexcludedirs',$_POST['backupcontentexcludedirs']);
	backwpup_update_option($main,'backupplugins', (isset($_POST['backupplugins']) && $_POST['backupplugins']==1) ? true : false);
	if (!isset($_POST['backuppluginsexcludedirs']) or !is_array($_POST['backuppluginsexcludedirs']))
		$_POST['backuppluginsexcludedirs']=array();
	foreach($_POST['backuppluginsexcludedirs'] as $key => $value) {
		$_POST['backuppluginsexcludedirs'][$key]=str_replace('//','/',str_replace('\\','/',trim($value)));
		if (empty($_POST['backuppluginsexcludedirs'][$key]) or $_POST['backuppluginsexcludedirs'][$key]=='/' or !is_dir($_POST['backuppluginsexcludedirs'][$key]))
			unset($_POST['backuppluginsexcludedirs'][$key]);
	}
	sort($_POST['backuppluginsexcludedirs']);
	backwpup_update_option($main,'backuppluginsexcludedirs',$_POST['backuppluginsexcludedirs']);
	backwpup_update_option($main,'backupthemes',(isset($_POST['backupthemes']) && $_POST['backupthemes']==1) ? true : false);
	if (!isset($_POST['backupthemesexcludedirs']) or !is_array($_POST['backupthemesexcludedirs']))
		$_POST['backupthemesexcludedirs']=array();
	foreach($_POST['backupthemesexcludedirs'] as $key => $value) {
		$_POST['backupthemesexcludedirs'][$key]=str_replace('//','/',str_replace('\\','/',trim($value)));
		if (empty($_POST['backupthemesexcludedirs'][$key]) or $_POST['backupthemesexcludedirs'][$key]=='/' or !is_dir($_POST['backupthemesexcludedirs'][$key]))
			unset($_POST['backupthemesexcludedirs'][$key]);
	}
	sort($_POST['backupthemesexcludedirs']);
	backwpup_update_option($main,'backupthemesexcludedirs',$_POST['backupthemesexcludedirs']);
	backwpup_update_option($main,'backupuploads', (isset($_POST['backupuploads']) && $_POST['backupuploads']==1) ? true : false);
	if (!isset($_POST['backupuploadsexcludedirs']) or !is_array($_POST['backupuploadsexcludedirs']))
		$_POST['backupuploadsexcludedirs']=array();
	foreach($_POST['backupuploadsexcludedirs'] as $key => $value) {
		$_POST['backupuploadsexcludedirs'][$key]=str_replace('//','/',str_replace('\\','/',trim($value)));
		if (empty($_POST['backupuploadsexcludedirs'][$key]) or $_POST['backupuploadsexcludedirs'][$key]=='/' or !is_dir($_POST['backupuploadsexcludedirs'][$key]))
			unset($_POST['backupuploadsexcludedirs'][$key]);
	}
	sort($_POST['backupuploadsexcludedirs']);
	backwpup_update_option($main,'backupuploadsexcludedirs',$_POST['backupuploadsexcludedirs']);
	backwpup_update_option($main,'backuptype',$_POST['backuptype']);
	backwpup_update_option($main,'fileformart',$_POST['fileformart']);
	backwpup_update_option($main,'mailefilesize',isset($_POST['mailefilesize']) ? (float)$_POST['mailefilesize'] : 0);
	$_POST['backupdir']=stripslashes($_POST['backupdir']);
	if (substr($_POST['backupdir'],0,1)!='/' and substr($_POST['backupdir'],1,1)!=':' and !empty($_POST['backupdir'])) //add abspath if not absolute
		$_POST['backupdir']=rtrim(str_replace('\\','/',ABSPATH),'/').'/'.$_POST['backupdir'];
	$_POST['backupdir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($_POST['backupdir']))));
	if ($_POST['backupdir']=='/')
		$_POST['backupdir']='';
	backwpup_update_option($main,'backupdir',$_POST['backupdir']);
	backwpup_update_option($main,'maxbackups',isset($_POST['maxbackups']) ? (int)$_POST['maxbackups'] : 0);
	backwpup_update_option($main,'backupsyncnodelete', (isset($_POST['backupsyncnodelete']) && $_POST['backupsyncnodelete']==1) ? true : false);
	backwpup_update_option($main,'ftpsyncnodelete', (isset($_POST['ftpsyncnodelete']) && $_POST['ftpsyncnodelete']==1) ? true : false);
	backwpup_update_option($main,'awssyncnodelete', (isset($_POST['awssyncnodelete']) && $_POST['awssyncnodelete']==1) ? true : false);
	backwpup_update_option($main,'GStoragesyncnodelete', (isset($_POST['GStoragesyncnodelete']) && $_POST['backupsyncnodelete']==1) ? true : false);
	backwpup_update_option($main,'msazuresyncnodelete', (isset($_POST['msazuresyncnodelete']) && $_POST['msazuresyncnodelete']==1) ? true : false);
	backwpup_update_option($main,'rscsyncnodelete', (isset($_POST['rscsyncnodelete']) && $_POST['rscsyncnodelete']==1) ? true : false);
	backwpup_update_option($main,'dropesyncnodelete', (isset($_POST['dropesyncnodelete']) && $_POST['dropesyncnodelete']==1) ? true : false);
	backwpup_update_option($main,'sugarsyncnodelete', (isset($_POST['sugarsyncnodelete']) && $_POST['sugarsyncnodelete']==1) ? true : false);
	backwpup_update_option($main,'ftphost',isset($_POST['ftphost']) ? $_POST['ftphost'] : '');
	backwpup_update_option($main,'ftphostport',!empty($_POST['ftphostport']) ? (int)$_POST['ftphostport'] : 21);
	backwpup_update_option($main,'ftptimeout',!empty($_POST['ftptimeout']) ? (int)$_POST['ftptimeout'] : 10);
	backwpup_update_option($main,'ftpuser',isset($_POST['ftpuser']) ? $_POST['ftpuser'] : '');
	backwpup_update_option($main,'ftppass',isset($_POST['ftppass']) ? backwpup_encrypt($_POST['ftppass']) : '');
	$_POST['ftpdir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim(stripslashes($_POST['ftpdir'])))));
	if (substr($_POST['ftpdir'],0,1)!='/')
		$_POST['ftpdir']='/'.$_POST['ftpdir'];
	if ($_POST['ftpdir']=='/')
		$_POST['ftpdir']='';
	backwpup_update_option($main,'ftpdir',$_POST['ftpdir']);
	backwpup_update_option($main,'ftpmaxbackups',isset($_POST['ftpmaxbackups']) ? (int)$_POST['ftpmaxbackups'] : 0);
	backwpup_update_option($main,'ftpssl', (isset($_POST['ftpssl']) && $_POST['ftpssl']==1) ? true : false);
	backwpup_update_option($main,'ftppasv', (isset($_POST['ftppasv']) && $_POST['ftppasv']==1) ? true : false);
	backwpup_update_option($main,'dropemaxbackups',isset($_POST['dropemaxbackups']) ? (int)$_POST['dropemaxbackups'] : 0);
	backwpup_update_option($main,'droperoot', (isset($_POST['droperoot']) and $_POST['droperoot']=='dropbox') ? 'dropbox' : 'sandbox');
	$_POST['dropedir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim(stripslashes($_POST['dropedir'])))));
	if (substr($_POST['dropedir'],0,1)=='/')
		$_POST['dropedir']=substr($_POST['dropedir'],1);
	if ($_POST['dropedir']=='/')
		$_POST['dropedir']='';
	backwpup_update_option($main,'dropedir',$_POST['dropedir']);
	backwpup_update_option($main,'awsAccessKey',isset($_POST['awsAccessKey']) ? $_POST['awsAccessKey'] : '');
	backwpup_update_option($main,'awsSecretKey',isset($_POST['awsSecretKey']) ? $_POST['awsSecretKey'] : '');
	backwpup_update_option($main,'awsrrs', (isset($_POST['awsrrs']) && $_POST['awsrrs']==1) ? true : false);
	backwpup_update_option($main,'awsdisablessl', (isset($_POST['awsdisablessl']) && $_POST['awsdisablessl']==1) ? true : false);
	backwpup_update_option($main,'awsssencrypt', (isset($_POST['awsssencrypt']) && $_POST['awsssencrypt']=='AES256') ? 'AES256' : '');
	backwpup_update_option($main,'awsBucket',isset($_POST['awsBucket']) ? $_POST['awsBucket'] : '');
	$_POST['awsdir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim(stripslashes($_POST['awsdir'])))));
	if (substr($_POST['awsdir'],0,1)=='/')
		$_POST['awsdir']=substr($_POST['awsdir'],1);
	if ($_POST['awsdir']=='/')
		$_POST['awsdir']='';
	backwpup_update_option($main,'awsdir',$_POST['awsdir']);
	backwpup_update_option($main,'awsmaxbackups',isset($_POST['awsmaxbackups']) ? (int)$_POST['awsmaxbackups'] : 0);
	backwpup_update_option($main,'GStorageAccessKey',isset($_POST['GStorageAccessKey']) ? $_POST['GStorageAccessKey'] : '');
	backwpup_update_option($main,'GStorageSecret',isset($_POST['GStorageSecret']) ? $_POST['GStorageSecret'] : '');
	backwpup_update_option($main,'GStorageBucket',isset($_POST['GStorageBucket']) ? $_POST['GStorageBucket'] : '');
	$_POST['GStoragedir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim(stripslashes($_POST['GStoragedir'])))));
	if (substr($_POST['GStoragedir'],0,1)=='/')
		$_POST['GStoragedir']=substr($_POST['GStoragedir'],1);
	if ($_POST['GStoragedir']=='/')
		$_POST['GStoragedir']='';
	backwpup_update_option($main,'GStoragedir',$_POST['GStoragedir']);
	backwpup_update_option($main,'GStoragemaxbackups',isset($_POST['GStoragemaxbackups']) ? (int)$_POST['GStoragemaxbackups'] : 0);
	backwpup_update_option($main,'msazureHost',isset($_POST['msazureHost']) ? $_POST['msazureHost'] : 'blob.core.windows.net');
	backwpup_update_option($main,'msazureAccName',isset($_POST['msazureAccName']) ? $_POST['msazureAccName'] : '');
	backwpup_update_option($main,'msazureKey',isset($_POST['msazureKey']) ? $_POST['msazureKey'] : '');
	backwpup_update_option($main,'msazureContainer',isset($_POST['msazureContainer']) ? $_POST['msazureContainer'] : '');
	$_POST['msazuredir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim(stripslashes($_POST['msazuredir'])))));
	if (substr($_POST['msazuredir'],0,1)=='/')
		$_POST['msazuredir']=substr($_POST['msazuredir'],1);
	if ($_POST['msazuredir']=='/')
		$_POST['msazuredir']='';
	backwpup_update_option($main,'msazuredir',$_POST['msazuredir']);
	backwpup_update_option($main,'msazuremaxbackups',isset($_POST['msazuremaxbackups']) ? (int)$_POST['msazuremaxbackups'] : 0);
	backwpup_update_option($main,'sugaruser',isset($_POST['sugaruser']) ? $_POST['sugaruser'] : '');
	backwpup_update_option($main,'sugarpass',isset($_POST['sugarpass']) ? backwpup_encrypt($_POST['sugarpass']) : '');
	$_POST['sugardir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim(stripslashes($_POST['sugardir'])))));
	if (substr($_POST['sugardir'],0,1)=='/')
		$_POST['sugardir']=substr($_POST['sugardir'],1);
	if ($_POST['sugardir']=='/')
		$_POST['sugardir']='';
	backwpup_update_option($main,'sugardir',$_POST['sugardir']);
	backwpup_update_option($main,'sugarroot',isset($_POST['sugarroot']) ? $_POST['sugarroot'] : '');
	backwpup_update_option($main,'sugarmaxbackups',isset($_POST['sugarmaxbackups']) ? (int)$_POST['sugarmaxbackups'] : 0);
	backwpup_update_option($main,'rscUsername',isset($_POST['rscUsername']) ? $_POST['rscUsername'] : '');
	backwpup_update_option($main,'rscAPIKey',isset($_POST['rscAPIKey']) ? $_POST['rscAPIKey'] : '');
	backwpup_update_option($main,'rscContainer',isset($_POST['rscContainer']) ? $_POST['rscContainer'] : '');
	$_POST['rscdir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim(stripslashes($_POST['rscdir'])))));
	if (substr($_POST['rscdir'],0,1)=='/')
		$_POST['rscdir']=substr($_POST['rscdir'],1);
	if ($_POST['rscdir']=='/')
		$_POST['rscdir']='';
	backwpup_update_option($main,'rscdir',$_POST['rscdir']);
	backwpup_update_option($main,'rscmaxbackups',isset($_POST['rscmaxbackups']) ? (int)$_POST['rscmaxbackups'] : 0);
	backwpup_update_option($main,'mailaddress',isset($_POST['mailaddress']) ? sanitize_email($_POST['mailaddress']) : '');


	if (!empty($_POST['newawsBucket']) and !empty($_POST['awsAccessKey']) and !empty($_POST['awsSecretKey'])) { //create new s3 bucket if needed
		if (!class_exists('CFRuntime'))
			require_once(dirname(__FILE__).'/../libs/aws/sdk.class.php');
		try {
			CFCredentials::set(array('backwpup' => array('key'=>$_POST['awsAccessKey'],'secret'=>$_POST['awsSecretKey'],'default_cache_config'=>'','certificate_authority'=>true),'@default' => 'backwpup'));
			$s3 = new AmazonS3();
			$s3->disable_ssl(backwpup_get_option($this->jobdata['JOBMAIN'],'awsdisablessl'));
			$req=$s3->create_bucket($_POST['newawsBucket'], $_POST['awsRegion']);
			if (empty($req->body->Message)) {
				$backwpup_message.=sprintf(__('S3 bucket "%s" created.','backwpup'),$_POST['newawsBucket']).'<br />';
				backwpup_update_option($main,'awsBucket',$_POST['newawsBucket']);
			} else {
				$backwpup_message.=sprintf(__('S3 bucket create: %s','backwpup'),$req->body->Message).'<br />';
			}
		} catch (Exception $e) {
			$backwpup_message.=sprintf(__('S3 bucket create: %s','backwpup'),$e->getMessage()).'<br />';
		}
	}
	
	if (!empty($_POST['newmsazureContainer'])  and !empty($_POST['msazureHost']) and !empty($_POST['msazureAccName']) and !empty($_POST['msazureKey'])) { //create new s3 bucket if needed
		if (!class_exists('Microsoft_WindowsAzure_Storage_Blob')) {
			require_once(dirname(__FILE__).'/../libs/Microsoft/WindowsAzure/Storage/Blob.php');
		}
		try {
			$storageClient = new Microsoft_WindowsAzure_Storage_Blob($_POST['msazureHost'],$_POST['msazureAccName'],$_POST['msazureKey']);
			$result = $storageClient->createContainer($_POST['newmsazureContainer']);
			if (!empty($result->Name)) {
				backwpup_update_option($main,'msazureContainer',$result->Name);
				$backwpup_message.=sprintf(__('MS azure container "%s" created.','backwpup'),$result->Name).'<br />';
			}
		} catch (Exception $e) {
			$backwpup_message.=sprintf(__('MS azure container create: %s','backwpup'),$e->getMessage()).'<br />';
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
				backwpup_update_option($main,'rscContainer',$_POST['newrscContainer']);
				$backwpup_message.=sprintf(__('Rackspase Cloud container "%s" created.','backwpup'),$_POST['newrscContainer']).'<br />';
			}
		} catch (Exception $e) {
			$backwpup_message.=sprintf(__('Rackspase Cloud container create: %s','backwpup'),$e->getMessage()).'<br />';
		}
	}
	
	
	if (isset($_POST['authbutton']) and $_POST['authbutton']==__('Delete DropBox authentication!', 'backwpup')) {
		backwpup_update_option($main,'dropetoken','');
		backwpup_update_option($main,'dropesecret','');
		$backwpup_message.=__('Dropbox authentication deleted!','backwpup').'<br />';
	}

	//get DropBox auth
	if (isset($_POST['authbutton']) and $_POST['authbutton']==__('DropBox authenticate!', 'backwpup')) {
		require_once (dirname(__FILE__).'/../libs/dropbox.php');
		$dropbox = new backwpup_Dropbox(backwpup_get_option($main,'droperoot'));
		// let the user authorize (user will be redirected)
		$response = $dropbox->oAuthAuthorize(backwpup_admin_url('admin.php').'?page=backwpupeditjob&jobid='.backwpup_get_option($main,'jobid').'&auth=DropBox&_wpnonce='.wp_create_nonce('edit-job'));
		// save oauth_token_secret 
		backwpup_update_option('temp','dropboxauth',array('oAuthRequestToken'=>$response['oauth_token'],'oAuthRequestTokenSecret' => $response['oauth_token_secret']));
		//forward to auth page
		wp_redirect($response['authurl']);
	}
	
	//make api call to backwpup.com
	do_action('backwpup_api_cron_update');
	
	$_POST['jobid']=backwpup_get_option($main,'jobid');
	$url=backwpup_jobrun_url('runnow',backwpup_get_option($main,'jobid'),false);
	$backwpup_message.=str_replace('%1',backwpup_get_option($main,'name'),__('Job \'%1\' changes saved.', 'backwpup')).' <a href="'.backwpup_admin_url('admin.php').'?page=backwpup">'.__('Jobs overview', 'backwpup').'</a> | <a href="'.$url['url'].'">'.__('Run now', 'backwpup').'</a>';
}


$dests=explode(',',strtoupper(BACKWPUP_DESTS));
//load java
wp_enqueue_script('common');
wp_enqueue_script('wp-lists');
wp_enqueue_script('postbox');

//add several metaboxes now, all metaboxes registered during load page can be switched off/on at "Screen Options" automatically, nothing special to do therefore
add_meta_box('backwpup_jobedit_backupfile', __('Backup File','backwpup'), array('BackWPup_editjob_metaboxes','backupfile'), get_current_screen()->id, 'side', 'default');
add_meta_box('backwpup_jobedit_sendlog', __('Send log','backwpup'), array('BackWPup_editjob_metaboxes','sendlog'), get_current_screen()->id, 'side', 'default');
if (in_array('FOLDER',$dests))
	add_meta_box('backwpup_jobedit_destfolder', __('Backup to Folder','backwpup'), array('BackWPup_editjob_metaboxes','destfolder'), get_current_screen()->id, 'normal', 'default');
if (in_array('MAIL',$dests))
	add_meta_box('nosync_backwpup_jobedit_destmail', __('Backup to E-Mail','backwpup'), array('BackWPup_editjob_metaboxes','destmail'), get_current_screen()->id, 'normal', 'default');
if (in_array('FTP',$dests))
	add_meta_box('nosync_backwpup_jobedit_destftp', __('Backup to FTP Server','backwpup'), array('BackWPup_editjob_metaboxes','destftp'), get_current_screen()->id, 'normal', 'default');
if (in_array('DROPBOX',$dests))
	add_meta_box('nosync_backwpup_jobedit_destdropbox', __('Backup to Dropbox','backwpup'), array('BackWPup_editjob_metaboxes','destdropbox'), get_current_screen()->id, 'normal', 'default');
if (in_array('SUGARSYNC',$dests))
	add_meta_box('nosync_backwpup_jobedit_destsugarsync', __('Backup to SugarSync','backwpup'), array('BackWPup_editjob_metaboxes','destsugarsync'), get_current_screen()->id, 'normal', 'default');
if (in_array('S3',$dests))
	add_meta_box('nosync_backwpup_jobedit_dests3', __('Backup to Amazon S3','backwpup'), array('BackWPup_editjob_metaboxes','dests3'), get_current_screen()->id, 'normal', 'default');
if (in_array('GSTORAGE',$dests))
	add_meta_box('nosync_backwpup_jobedit_destgstorage', __('Backup to Google storage','backwpup'), array('BackWPup_editjob_metaboxes','destgstorage'), get_current_screen()->id, 'normal', 'default');
if (in_array('MSAZURE',$dests))
	add_meta_box('nosync_backwpup_jobedit_destazure', __('Backup to Micosoft Azure (Blob)','backwpup'), array('BackWPup_editjob_metaboxes','destazure'), get_current_screen()->id, 'normal', 'default');
if (in_array('RSC',$dests))
	add_meta_box('nosync_backwpup_jobedit_destrsc', __('Backup to Rackspace Cloud','backwpup'), array('BackWPup_editjob_metaboxes','destrsc'), get_current_screen()->id, 'normal', 'default');


//add columns
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