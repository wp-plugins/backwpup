<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

//ajax/normal get cron text
function backwpup_get_cron_text($args='') {
	if (is_array($args)) {
		extract($args);
		$ajax=false;
	} else {
		check_ajax_referer('backwpupeditjob_ajax_nonce');
		if (!current_user_can(BACKWPUP_USER_CAPABILITY))
			die('-1');
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
		$cronstamp=implode(",",$_POST['cronminutes']).' '.implode(",",$_POST['cronhours']).' '.implode(",",$_POST['cronmday']).' '.implode(",",$_POST['cronmon']).' '.implode(",",$_POST['cronwday']);
		$ajax=true;
	}	
	echo '<div id="cron-text">';
	_e('Working as <a href="http://wikipedia.org/wiki/Cron" target="_blank">Cron</a> job schedule:','backwpup'); echo ' <i><b><nobr>'.$cronstamp.'</nobr></b></i><br />'; 
	list($cronstr['minutes'],$cronstr['hours'],$cronstr['mday'],$cronstr['mon'],$cronstr['wday'])=explode(' ',$cronstamp,5);
	if (false !== strpos($cronstr['minutes'],'*/') or ($cronstr['minutes']=='*')) {
		$repeatmins=str_replace('*/','',$cronstr['minutes']);
		if ($repeatmins=='*' or empty($repeatmins))
			$repeatmins=5;
		echo '<span style="color:red;">'.str_replace('%d',$repeatmins,__('ATTENTION: Job runs every %d mins.!!!','backwpup')).'</span><br />';
	}
	if (false !== strpos($cronstr['hours'],'*/') or ($cronstr['hours']=='*')) {
		$repeathouer=str_replace('*/','',$cronstr['hours']);
		if ($repeathouer=='*' or empty($repeathouer))
			$repeathouer=1;
		echo '<span style="color:red;">'.str_replace('%d',$repeathouer,__('ATTENTION: Job runs every %d houers.!!!','backwpup')).'</span><br />';
	}
	$nextrun=backwpup_cron_next($cronstamp);
	if (2147483647==$nextrun) {
		echo '<span style="color:red;">'.__('ATTENTION: Can\'t calculate cron!!!','backwpup').'</span><br />';
	} else {
		_e('Next runtime:','backwpup'); echo ' <b>'.date_i18n('D, j M Y, H:i',backwpup_cron_next($cronstamp)).'</b>';
	}
	echo "</div>";	
	if ($ajax)
		die();
	else
		return;
}

//ajax/normal get buckests select box
function backwpup_get_aws_buckets($args='') {
	if (is_array($args)) {
		extract($args);
		$ajax=false;
	} else {
		check_ajax_referer('backwpupeditjob_ajax_nonce');
		if (!current_user_can(BACKWPUP_USER_CAPABILITY))
			die('-1');
		$awsAccessKey=$_POST['awsAccessKey'];
		$awsSecretKey=$_POST['awsSecretKey'];
		$awsselected=$_POST['awsselected'];
		$ajax=true;
	}
	if (!class_exists('CFRuntime'))
		require_once(dirname(__FILE__).'/../libs/aws/sdk.class.php');
	if (empty($awsAccessKey)) {
		echo '<span id="awsBucket" style="color:red;">'.__('Missing access key!','backwpup').'</span>';
		if ($ajax)
			die();
		else
			return;
	}
	if (empty($awsSecretKey)) {
		echo '<span id="awsBucket" style="color:red;">'.__('Missing secret access key!','backwpup').'</span>';
		if ($ajax)
			die();
		else
			return;
	}
	try {
		CFCredentials::set(array('backwpup' => array('key'=>$awsAccessKey,'secret'=>$awsSecretKey,'default_cache_config'=>'','certificate_authority'=>true),'@default' => 'backwpup'));
		$s3 = new AmazonS3();
		$buckets=$s3->list_buckets();
	} catch (Exception $e) {
		echo '<span id="awsBucket" style="color:red;">'.$e->getMessage().'</span>';
		if ($ajax)
			die();
		else
			return;
	}
	if ($buckets->status<200 or $buckets->status>=300) {
		echo '<span id="awsBucket" style="color:red;">'.$buckets->status.': '.$buckets->body->Message.'</span>';
		if ($ajax)
			die();
		else
			return;
	}
	if (count($buckets->body->Buckets->Bucket)<1) {
		echo '<span id="awsBucket" style="color:red;">'.__('No bucket fount!','backwpup').'</span>';
		if ($ajax)
			die();
		else
			return;
	} 	
	echo '<select name="awsBucket" id="awsBucket">';
	foreach ($buckets->body->Buckets->Bucket as $bucket) {
		echo "<option ".selected(strtolower($awsselected),strtolower($bucket->Name),false).">".$bucket->Name."</option>";
	}
	echo '</select>';
	if ($ajax)
		die();
	else
		return;
}

//ajax/normal get buckests select box
function backwpup_get_gstorage_buckets($args='') {
	if (is_array($args)) {
		extract($args);
		$ajax=false;
	} else {
		check_ajax_referer('backwpupeditjob_ajax_nonce');
		if (!current_user_can(BACKWPUP_USER_CAPABILITY))
			die('-1');
		$GStorageAccessKey=$_POST['GStorageAccessKey'];
		$GStorageSecret=$_POST['GStorageSecret'];
		$GStorageselected=$_POST['GStorageselected'];
		$ajax=true;
	}
	if (!class_exists('CFRuntime'))
		require_once(dirname(__FILE__).'/../libs/aws/sdk.class.php');
	if (empty($GStorageAccessKey)) {
		echo '<span id="GStorageBucket" style="color:red;">'.__('Missing access key!','backwpup').'</span>';
		if ($ajax)
			die();
		else
			return;
	}
	if (empty($GStorageSecret)) {
		echo '<span id="GStorageBucket" style="color:red;">'.__('Missing secret access key!','backwpup').'</span>';
		if ($ajax)
			die();
		else
			return;
	}
	try {
		CFCredentials::set(array('backwpup' => array('key'=>$GStorageAccessKey,'secret'=>$GStorageSecret,'default_cache_config'=>'','certificate_authority'=>true),'@default' => 'backwpup'));
		$gstorage = new AmazonS3();
		$gstorage->set_hostname('commondatastorage.googleapis.com');
		$gstorage->allow_hostname_override(false);
		$buckets=$gstorage->list_buckets();
	} catch (Exception $e) {
		echo '<span id="GStorageBucket" style="color:red;">'.$e->getMessage().'</span>';
		if ($ajax)
			die();
		else
			return;
	}
	if ($buckets->status<200 or $buckets->status>=300) {
		echo '<span id="GStorageBucket" style="color:red;">'.$buckets->status.': '.$buckets->body->Message.'</span>';
		if ($ajax)
			die();
		else
			return;
	} 
	if (count($buckets->body->Buckets->Bucket)<1) {
		echo '<span id="GStorageBucket" style="color:red;">'.__('No bucket fount!','backwpup').'</span>';
		if ($ajax)
			die();
		else
			return;
	} 	
	echo '<select name="GStorageBucket" id="GStorageBucket">';
	foreach ($buckets->body->Buckets->Bucket as $bucket) {
		echo "<option ".selected(strtolower($GStorageselected),strtolower($bucket->Name),false).">".$bucket->Name."</option>";
	}
	echo '</select>';
	if ($ajax)
		die();
	else
		return;
}

//ajax/normal get Container for RSC select box
function backwpup_get_rsc_container($args='') {
	if (is_array($args)) {
		extract($args);
		$ajax=false;
	} else {
		check_ajax_referer('backwpupeditjob_ajax_nonce');
		if (!current_user_can(BACKWPUP_USER_CAPABILITY))
			die('-1');
		$rscUsername=$_POST['rscUsername'];
		$rscAPIKey=$_POST['rscAPIKey'];
		$rscselected=$_POST['rscselected'];
		$ajax=true;
	}
	if (!class_exists('CF_Authentication'))
		require_once(dirname(__FILE__).'/../libs/rackspace/cloudfiles.php');

	if (empty($rscUsername)) {
		echo '<span id="rscContainer" style="color:red;">'.__('Missing Username!','backwpup').'</span>';
		if ($ajax)
			die();
		else
			return;
	}
	if (empty($rscAPIKey)) {
		echo '<span id="rscContainer" style="color:red;">'.__('Missing API Key!','backwpup').'</span>';
		if ($ajax)
			die();
		else
			return;
	}

	try {
		$auth = new CF_Authentication($rscUsername, $rscAPIKey);
		$auth->authenticate();
		$conn = new CF_Connection($auth);
		$containers=$conn->get_containers();
	} catch (Exception $e) {
		echo '<span id="rscContainer" style="color:red;">'.$e->getMessage().'</span>';
		if ($ajax)
			die();
		else
			return;
	}

	if (!is_array($containers)) {
		echo '<span id="rscContainer" style="color:red;">'.__('No Containerss found!','backwpup').'</span>';
		if ($ajax)
			die();
		else
			return;
	}
	echo '<select name="rscContainer" id="rscContainer">';
	foreach ($containers as $container) {
		echo "<option ".selected(strtolower($rscselected),strtolower($container->name),false).">".$container->name."</option>";
	}
	echo '</select>';
		if ($ajax)
			die();
		else
			return;
}

//ajax/normal get buckests select box
function backwpup_get_msazure_container($args='') {
	if (is_array($args)) {
		extract($args);
		$ajax=false;
	} else {
		check_ajax_referer('backwpupeditjob_ajax_nonce');
		if (!current_user_can(BACKWPUP_USER_CAPABILITY))
			die('-1');
		$msazureHost=$_POST['msazureHost'];
		$msazureAccName=$_POST['msazureAccName'];
		$msazureKey=$_POST['msazureKey'];
		$msazureselected=$_POST['msazureselected'];
		$ajax=true;
	}
	if (!class_exists('Microsoft_WindowsAzure_Storage_Blob')) 
		require_once(dirname(__FILE__).'/../libs/Microsoft/WindowsAzure/Storage/Blob.php');
	if (empty($msazureHost)) {
		echo '<span id="msazureContainer" style="color:red;">'.__('Missing Hostname!','backwpup').'</span>';
		if ($ajax)
			die();
		else
			return;
	}
	if (empty($msazureAccName)) {
		echo '<span id="msazureContainer" style="color:red;">'.__('Missing Account Name!','backwpup').'</span>';
		if ($ajax)
			die();
		else
			return;
	}
	if (empty($msazureKey)) {
		echo '<span id="msazureContainer" style="color:red;">'.__('Missing Access Key!','backwpup').'</span>';
		if ($ajax)
			die();
		else
			return;
	}
	try {
		$storageClient = new Microsoft_WindowsAzure_Storage_Blob($msazureHost,$msazureAccName,$msazureKey);
		$Containers=$storageClient->listContainers();
	} catch (Exception $e) {
		echo '<span id="msazureContainer" style="color:red;">'.$e->getMessage().'</span>';
		if ($ajax)
			die();
		else
			return;
	}
	if (empty($Containers)) {
		echo '<span id="msazureContainer" style="color:red;">'.__('No Container found!','backwpup').'</span>';
		if ($ajax)
			die();
		else
			return;
	}
	echo '<select name="msazureContainer" id="msazureContainer">';
	foreach ($Containers as $Container) {
		echo "<option ".selected(strtolower($msazureselected),strtolower($Container->Name),false).">".$Container->Name."</option>";
	}
	echo '</select>';
	if ($ajax)
		die();
	else
		return;
}

//ajax/normal get SugarSync roots select box
function backwpup_get_sugarsync_root($args='') {
	if (is_array($args)) {
		extract($args);
		$ajax=false;
	} else {
		check_ajax_referer('backwpupeditjob_ajax_nonce');
		if (!current_user_can(BACKWPUP_USER_CAPABILITY))
			die('-1');
		$sugaruser=$_POST['sugaruser'];
		$sugarpass=$_POST['sugarpass'];
		$sugarrootselected=$_POST['sugarrootselected'];
		$ajax=true;
	}
	if (!class_exists('SugarSync'))
		require_once(dirname(__FILE__).'/../libs/sugarsync.php');

	if (empty($sugaruser)) {
		echo '<span id="sugarroot" style="color:red;">'.__('Missing Username!','backwpup').'</span>';
		if ($ajax)
			die();
		else
			return;
	}
	if (empty($sugarpass)) {
		echo '<span id="sugarroot" style="color:red;">'.__('Missing Password!','backwpup').'</span>';
		if ($ajax)
			die();
		else
			return;
	}

	try {
		$sugarsync = new SugarSync($sugaruser,$sugarpass,backwpup_get_option('cfg','SUGARSYNC_ACCESSKEY'),backwpup_get_option('cfg','SUGARSYNC_PRIVATEACCESSKEY'));
		$user=$sugarsync->user();
		$syncfolders=$sugarsync->get($user->syncfolders);
	} catch (Exception $e) {
		echo '<span id="sugarroot" style="color:red;">'.$e->getMessage().'</span>';
		if ($ajax)
			die();
		else
			return;
	}

	if (!is_object($syncfolders)) {
		echo '<span id="sugarroot" style="color:red;">'.__('No Syncfolders found!','backwpup').'</span>';
		if ($ajax)
			die();
		else
			return;
	}
	echo '<select name="sugarroot" id="sugarroot">';
	foreach ($syncfolders->collection as $roots) {
		echo "<option ".selected(strtolower($sugarrootselected),strtolower($roots->ref),false)." value=\"".$roots->ref."\">".$roots->displayName."</option>";
	}
	echo '</select>';
		if ($ajax)
			die();
		else
			return;
}
//add ajax function
add_action('wp_ajax_backwpup_get_cron_text', 'backwpup_get_cron_text');
add_action('wp_ajax_backwpup_get_aws_buckets', 'backwpup_get_aws_buckets');
add_action('wp_ajax_backwpup_get_gstorage_buckets', 'backwpup_get_gstorage_buckets');
add_action('wp_ajax_backwpup_get_rsc_container', 'backwpup_get_rsc_container');
add_action('wp_ajax_backwpup_get_msazure_container', 'backwpup_get_msazure_container');
add_action('wp_ajax_backwpup_get_sugarsync_root', 'backwpup_get_sugarsync_root');
?>