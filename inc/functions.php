<?php
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

/**
 *
 * Get (and set) Version number of BackWPup
 *
 * @return string Version
 */
function backwpup_get_version() {
	$version=backwpup_get_option('backwpup','version');
	if ($version==false || $version=='0.0') {
		$plugin_data = get_file_data( realpath(dirname(__FILE__).'/../backwpup.php'),  array('Version' => 'Version'), 'plugin' );
		$version=$plugin_data['Version'];
		backwpup_update_option('backwpup','version',$version);
	}
	if (empty($version))
		return '0.0';
	return $version;
}

/**
 *
 * Get default option for BackWPup option
 *
 * @param string $main Main option name lowercase max 64 chars
 * @param string $name Option name lowercase max 64 chars
 * @return bool|mixed
 */
function backwpup_default_option_settings($main,$name) {
	global $wpdb;
	$main=sanitize_key(trim($main));
	$name=sanitize_key(trim($name));
	//set defaults
	if ($main=='backwpup') { //for settings
		$default['backwpup']['version']='0.0';
		$default['backwpup']['check']=false;
	} elseif ($main=='cfg') { //for settings
		$default['cfg']['mailsndemail']=sanitize_email(get_bloginfo( 'admin_email' ));
		$default['cfg']['mailsndname']='BackWPup '.get_bloginfo('name');
		$default['cfg']['showadminbar']=true;
		$default['cfg']['jobstepretry']=3;
		$default['cfg']['jobscriptretry']=5;
		$default['cfg']['maxlogs']=50;
		$default['cfg']['gzlogs']=false;
		$default['cfg']['runnowalt']=false;
		$default['cfg']['logfolder']=str_replace('\\','/',trailingslashit(WP_CONTENT_DIR)).'backwpup-'.substr(md5(md5(SECURE_AUTH_KEY)), -5).'-logs/';
		$default['cfg']['httpauthuser']='';
		$default['cfg']['httpauthpassword']='';
		$default['cfg']['jobrunauthkey']='';
		$default['cfg']['apicronservicekey']='';
		$default['cfg']['jobrunmaxexectime']=0;
		$default['cfg']['storeworkingdatain']='db';
		if ($name=='tempfolder') {
			if (defined('WP_TEMP_DIR')) //get temp folder
				$default['cfg']['tempfolder']=trim(WP_TEMP_DIR);
			if (empty($default['cfg']['tempfolder']) || !BackWPup_File::check_open_basedir($default['cfg']['tempfolder']) || !@is_writable($default['cfg']['tempfolder']) || !@is_dir($default['cfg']['tempfolder']))
				$default['cfg']['tempfolder']=sys_get_temp_dir();									//normal temp dir
			if (empty($default['cfg']['tempfolder']) || !BackWPup_File::check_open_basedir($default['cfg']['tempfolder']) || !@is_writable($default['cfg']['tempfolder']) || !@is_dir($default['cfg']['tempfolder']))
				$default['cfg']['tempfolder']=ini_get('upload_tmp_dir');							//if sys_get_temp_dir not work
			if (empty($default['cfg']['tempfolder']) || !BackWPup_File::check_open_basedir($default['cfg']['tempfolder']) || !@is_writable($default['cfg']['tempfolder']) || !@is_dir($default['cfg']['tempfolder']))
				$default['cfg']['tempfolder']=WP_CONTENT_DIR.'/';
			if (empty($default['cfg']['tempfolder']) || !BackWPup_File::check_open_basedir($default['cfg']['tempfolder']) || !@is_writable($default['cfg']['tempfolder']) || !@is_dir($default['cfg']['tempfolder']))
				$default['cfg']['tempfolder']=get_temp_dir();
			$default['cfg']['tempfolder']=trailingslashit(str_replace('\\','/',realpath($default['cfg']['tempfolder'])));
		}
	}
	if (substr($main,0,4)=='job_') { //for job settings
		$default[$main]['type']=array('DB','FILE');
		$default[$main]['name']= __('New', 'backwpup');
		$default[$main]['activetype']='';
		$default[$main]['cronselect']='basic';
		$default[$main]['cron']='0 3 * * *';
		$default[$main]['mailaddresslog']=get_option('admin_email');
		$default[$main]['mailerroronly']=true;
		if ($name=='dbexclude') {
			$default[$main]['dbexclude']=array();
			$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');
			foreach ($tables as $table) {
				if (strpos($table,$wpdb->prefix) === false)
					$default[$main]['dbexclude'][]=$table;
			}
		}
		$default[$main]['dbdumpfile']=DB_NAME;
		$default[$main]['dbdumpfilecompression']='';
		$default[$main]['maintenance']=false;
		$default[$main]['wpexportfile']=sanitize_key(get_bloginfo('name')).'.wordpress.%Y-%m-%d';
		$default[$main]['pluginlistfile']=sanitize_key(get_bloginfo('name')).'.pluginlist.%Y-%m-%d';
		$default[$main]['wpexportfilecompression']='';
		$default[$main]['fileexclude']='.tmp,.svn,.git';
		$default[$main]['dirinclude']='';
		$default[$main]['backupexcludethumbs']=false;
		$default[$main]['backupspecialfiles']=false;
		$default[$main]['backuproot']=true;
		$default[$main]['backupcontent']=true;
		$default[$main]['backupplugins']=true;
		$default[$main]['backupthemes']=true;
		$default[$main]['backupuploads']=true;
		$default[$main]['backuprootexcludedirs']=array();
		$default[$main]['backupcontentexcludedirs']=array();
		$default[$main]['backuppluginsexcludedirs']=array();
		$default[$main]['backupthemesexcludedirs']=array();
		$default[$main]['backupuploadsexcludedirs']=array();
		$default[$main]['backuptype']='archive';
		$default[$main]['fileformart']='.zip';
		$default[$main]['fileprefix']='backwpup_'.substr($main,4).'_';
		$default[$main]['mailefilesize']=0;
		$default[$main]['backupdir']='';
		$default[$main]['maxbackups']=0;
		$default[$main]['backupsyncnodelete']=true;
		$default[$main]['ftphost']='';
		$default[$main]['ftphostport']=21;
		$default[$main]['ftptimeout']=90;
		$default[$main]['ftpuser']='';
		$default[$main]['ftpdir']='';
		$default[$main]['ftpmaxbackups']=0;
		$default[$main]['ftppasv']=true;
		$default[$main]['ftpssl']=false;
		$default[$main]['ftpsyncnodelete']=true;
		$default[$main]['awsaccesskey']='';
		$default[$main]['awssecretkey']='';
		$default[$main]['awsssencrypt']='';
		$default[$main]['awsrrs']=false;
		$default[$main]['awsdisablessl']=false;
		$default[$main]['awsbucket']='';
		$default[$main]['awsdir']='';
		$default[$main]['awsmaxbackups']=0;
		$default[$main]['awssyncnodelete']=true;
		$default[$main]['gstorageaccesskey']='';
		$default[$main]['gstoragesecret']='';
		$default[$main]['gstoragebucket']='';
		$default[$main]['gstoragedir']='';
		$default[$main]['gstoragemaxbackups']=0;
		$default[$main]['gstoragesyncnodelete']=true;
		$default[$main]['msazurehost']='blob.core.windows.net';
		$default[$main]['msazureaccname']='';
		$default[$main]['msazurekey']='';
		$default[$main]['msazurecontainer']='';
		$default[$main]['msazuredir']='';
		$default[$main]['msazuremaxbackups']=0;
		$default[$main]['msazuresyncnodelete']=true;
		$default[$main]['rscusername']='';
		$default[$main]['rscapikey']='';
		$default[$main]['rsccontainer']='';
		$default[$main]['rscdir']='';
		$default[$main]['rscmaxbackups']=0;
		$default[$main]['rscsyncnodelete']=true;
		$default[$main]['droperoot']='sandbox';
		$default[$main]['dropetoken']='';
		$default[$main]['dropesecret']='';
		$default[$main]['dropedir']='';
		$default[$main]['dropemaxbackups']=0;
		$default[$main]['dropesyncnodelete']=true;
		$default[$main]['sugaruser']='';
		$default[$main]['sugarpass']='';
		$default[$main]['sugarroot']='';
		$default[$main]['sugardir']='';
		$default[$main]['sugarmaxbackups']=0;
		$default[$main]['sugarsyncnodelete']=true;
		$default[$main]['mailaddress']='';
	}
	if ($main=='working') {
		$default['working']['data']=false;
	}
	if ($main=='api') {
		$default['api']['apiapp']=false;
	}
	//return defaults
	if(isset($default[$main][$name]))
		return maybe_serialize($default[$main][$name]);
	else
		return false;
}

/**
 *
 * Update a BackWPup option
 *
 * @param string $main Main option name lowercase max 64 chars
 * @param string $name Option name lowercase max 64 chars
 * @param mixed $value the value to store
 * @return bool if option save or not
 */
function backwpup_update_option($main,$name,$value) {
	global $wpdb;
	$main=sanitize_key(trim($main));
	$name=sanitize_key(trim($name));
	$oldvalue='';
	if ($main!='working')
		$alloptions=wp_cache_get( 'options', 'backwpup' );
	if (empty($main) || empty($name))
		return false;
	if (is_object($value))
		$value = clone $value;
	$value=maybe_serialize($value);
	//unset if it a default option value
	if (isset($alloptions[$main][$name]) && $alloptions[$main][$name]==backwpup_default_option_settings($main,$name) && $main!='working')
		unset($alloptions[$main][$name]);
	//is value same as old do nothing
	if (isset($alloptions[$main][$name]) && $alloptions[$main][$name]==$value)
		return false;
	if (!isset($alloptions[$main][$name])) { //sql if not in cache
		$oldvalue=$wpdb->get_row($wpdb->prepare("SELECT value FROM ".$wpdb->prefix."backwpup WHERE main=%s AND name=%s LIMIT 1",$main,$name));
		if (is_object($oldvalue) && $oldvalue->value==$value)
			return false;
	}
	//Update or insert
	if (isset($alloptions[$main][$name]) || is_object($oldvalue))
		$result=$wpdb->update( $wpdb->prefix.'backwpup', array( 'value' => $value ), array( 'main' => $main, 'name' => $name ),array('%s'),array('%s','%s') );
	 else
		$result=$wpdb->insert( $wpdb->prefix.'backwpup', array( 'main' => $main, 'name' => $name, 'value' => $value ), '%s' );
	if ($result) {
		if ($main!='working' && $main!='temp') {
			$alloptions[$main][$name]=$value;
			wp_cache_set( 'options', $alloptions, 'backwpup' );
		}
		return true;
	} else
		return false;
}

/**
 *
 * Get a BackWPup Option
 *
 * @param string $main Main option name lowercase max 64 chars
 * @param string $name Option name lowercase max 64 chars
 * @param mixed $default if not the the default BackWPup option will get
 * @return bool|mixed false if nothing can get else the option value
 */
function backwpup_get_option($main,$name,$default=false) {
	global $wpdb,$wp_object_cache;
	$main=sanitize_key(trim($main));
	$name=sanitize_key(trim($name));
	if (empty($main) || empty($name))
		return false;
	$alloptions=wp_cache_get( 'options', 'backwpup' );
	//load options to cache if empty
	if ($alloptions==false) {
		$option_cache_req = $wpdb->get_results( "SELECT main,name,value FROM ".$wpdb->prefix."backwpup WHERE main<>'working' AND main<>'temp' ORDER BY main,name" );
		if (is_array($option_cache_req)) {
			foreach ($option_cache_req as $option)
				$alloptions[$option->main][$option->name]=$option->value;
		}
		wp_cache_set( 'options', $alloptions, 'backwpup' );
	}
	// output from cache or db
	if (isset($alloptions[$main][$name]) && $main!='working') {
		return maybe_unserialize($alloptions[$main][$name]);
	} else {
		$value=$wpdb->get_row($wpdb->prepare("SELECT value FROM ".$wpdb->prefix."backwpup WHERE main=%s AND name=%s LIMIT 1",$main,$name));
		if (is_object($value)) {
			$otionvalue=maybe_unserialize($value->value);
		} else {
			if ($default)
				$otionvalue=$default;
			else
				$otionvalue=maybe_unserialize(backwpup_default_option_settings($main,$name));
		}
		if ($main!='working') {
			$alloptions[$main][$name]=$otionvalue;
			wp_cache_set( 'options', $alloptions, 'backwpup' );
		}
		return $otionvalue;
	}
}

/**
 *
 * Deltests a BackWPup Option
 *
 * @param string $main Main option name lowercase max 64 chars
 * @param string $name Option name lowercase max 64 chars
 * @return bool deletet or not
 */
function backwpup_delete_option($main,$name) {
	global $wpdb;
	$main=sanitize_key(trim($main));
	$name=sanitize_key(trim($name));
	$alloptions=wp_cache_get( 'options', 'backwpup' );
	if (empty($main) || empty($name))
		return false;
	unset($alloptions[$main][$name]);
	wp_cache_set( 'options', $alloptions, 'backwpup' );
	$result=$wpdb->query($wpdb->prepare("DELETE FROM ".$wpdb->prefix."backwpup WHERE main=%s AND name=%s LIMIT 1",$main,$name));
	if ($result)
		return true;
	else
		return false;
}

/**
 *
 * Get a url to run a job of BackWPup
 *
 * @param string $starttype Start types are 'runnow', 'runnowalt', 'cronrun', 'runext', 'runcmd', 'apirun', 'restart', 'restarttime'
 * @param int $jobid The id of job to start else 0
 * @param bool $run call the url no give back
 * @return array|object [url] is the job url [header] for auth header or object form wp_remote_get()
 */
function backwpup_jobrun_url($starttype,$jobid=0,$run=false) {
	$url=plugins_url('',dirname(__FILE__)).'/job.php';
	$header='';
	$authurl='';
	$query_args=array();

	if (in_array($starttype, array( 'restart', 'runnow', 'runnowalt', 'cronrun', 'runext','apirun' )))
		$query_args['starttype']=$starttype;

	if (in_array($starttype, array( 'runnow', 'runnowalt', 'cronrun', 'runext','apirun' )) && !empty($jobid))
		$query_args['jobid']=$jobid;

	if (backwpup_get_option('cfg','httpauthuser') && backwpup_get_option('cfg','httpauthpassword')) {
		$header=array( 'Authorization' => 'Basic '.base64_encode(backwpup_get_option('cfg','httpauthuser').':'.backwpup_decrypt(backwpup_get_option('cfg','httpauthpassword'))));
		$authurl=backwpup_get_option('cfg','httpauthuser').':'.backwpup_decrypt(backwpup_get_option('cfg','httpauthpassword')).'@';
	}

	if (WP_PLUGIN_DIR!=ABSPATH.'wp-content/plugins')
		$query_args['ABSPATH']=urlencode(str_replace('\\','/',ABSPATH));

	if ($starttype=='apirun')
		$query_args['_nonce']=backwpup_get_option('cfg','apicronservicekey');
	elseif ($starttype=='runext') {
		$query_args['_nonce']=backwpup_get_option('cfg','jobrunauthkey');
		if (!empty($authurl)) {
			$url=str_replace('https://','https://'.$authurl,$url);
			$url=str_replace('http://','http://'.$authurl,$url);
		}
	} elseif ($starttype=='cronrun' || $starttype=='restart') {
		$oldnonce=backwpup_get_option('temp', $starttype.'_nonce');
		if (!empty($oldnonce))
			$query_args['_nonce']=$oldnonce;
		else {
			$query_args['_nonce']=wp_generate_password( 12, false, false );
			backwpup_update_option('temp', $starttype.'_nonce', $query_args['_nonce']);
		}
	} elseif (backwpup_get_option('cfg','runnowalt') && $starttype=='runnow') {
		$url=wp_nonce_url(backwpup_admin_url('admin.php'), 'job-runnow');
		$query_args['page']='backwpupworking';
	} elseif ($starttype=='runnow' || $starttype=='runnowalt'){
		$oldnonce=backwpup_get_option('temp', $starttype.'_nonce_'.$jobid);
		if (!empty($oldnonce))
			$query_args['_nonce']=$oldnonce;
		else {
			$query_args['_nonce']=wp_generate_password( 12, false, false );
			backwpup_update_option('temp', $starttype.'_nonce_'.$jobid, $query_args['_nonce']);
		}
	}

	$url=array('url'=>add_query_arg($query_args, $url),'header'=>$header);
	if ($run) {
		return @wp_remote_get($url['url'], array('timeout' => 5, 'blocking' => true, 'sslverify' => false, 'headers'=>$url['header'], 'user-agent'=>'BackWPup'));
	} else
		return $url;
}

/**
 *
 * Returns the job types as Text or array
 *
 * @param array $type
 * @param bool $echo
 * @return array|string
 */
function backwpup_job_types($type=array(),$echo=false) {
	$typename='';
	$type=(array)$type;
	if (!empty($type)) {
		foreach($type as $value) {
			switch($value) {
			case 'WPEXP':
				$typename.=__('WP XML Export','backwpup')."<br />";
				break;
			case 'FILE':
				$typename.=__('File Backup','backwpup')."<br />";
				break;
			case 'DB':
				$typename.=__('Database Backup','backwpup')."<br />";
				break;
			case 'OPTIMIZE':
				$typename.=__('Optimize Database Tables','backwpup')."<br />";
				break;
			case 'CHECK':
				$typename.=__('Check Database Tables','backwpup')."<br />";
				break;
			}
		}
	} else {
		$typename=array('DB','WPEXP','FILE','OPTIMIZE','CHECK');
	}

	if ($echo)
		echo $typename;

	return $typename;
}

/**
 *
 * Reads a BackWPup logfile header and gives back a array of information
 *
 * @param string $logfile full logfile path
 * @return array|bool
 */
function backwpup_read_logheader($logfile) {
	$headers=array("backwpup_version" => "version","backwpup_logtime" => "logtime","backwpup_errors" => "errors","backwpup_warnings" => "warnings","backwpup_jobid" => "jobid","backwpup_jobname" => "name","backwpup_jobtype" => "type","backwpup_jobruntime" => "runtime","backwpup_backupfilesize" => "backupfilesize");
	if (!is_readable($logfile))
		return false;
	//Read file
	if (strtolower(substr($logfile,-3))==".gz") {
		$fp = gzopen( $logfile, 'r' );
		$file_data = gzread( $fp, 1536 ); // Pull only the first 1,5kiB of the file in.
		gzclose( $fp );
	} else {
		$fp = fopen( $logfile, 'r' );
		$file_data = fread( $fp, 1536 ); // Pull only the first 1,5kiB of the file in.
		fclose( $fp );
	}
	//get data form file
	foreach ($headers as $keyword => $field) {
		preg_match('/(<meta name="'.$keyword.'" content="(.*)" \/>)/i',$file_data,$content);
		if (!empty($content))
			$joddata[$field]=$content[2];
		else
			$joddata[$field]='';
	}
	if (empty($joddata['logtime']))
		$joddata['logtime']=filectime($logfile);
	return $joddata;
}

/**
 *
 * Warpper for Admin urls on multisite or normal blog
 *
 * @param $url 'admin.php'
 * @return string url
 */
function backwpup_admin_url($url) {
	if (is_multisite() && is_super_admin())
		return network_admin_url($url);
	return admin_url($url);
}

/**
 *
 * Encrypt a string (Passwords)
 *
 * @param string $string value to encrypt
 * @param string $key if empty default will used
 * @return string encrypted string
 */
function backwpup_encrypt($string, $key='') {
	if (empty($key))
		$key=md5(ABSPATH);
	if (empty($string))
		return $string;
	//only encrypt if needed
	if (strpos($string,'$BackWPup$ENC1$')!==false)
		return $string;
	$result = '';
	for($i=0; $i<strlen ($string); $i++) {
		$char = substr($string, $i, 1);
		$keychar = substr($key, ($i % strlen($key))-1, 1);
		$char = chr(ord($char)+ord($keychar));
		$result.=$char;
	}
	return '$BackWPup$ENC1$'.base64_encode($result);
}

/**
 *
 * Decrypt a string (Passwords)
 *
 * @param string $string value to decrypt
 * @param string $key if empty default will used
 * @return string decrypted string
 */
function backwpup_decrypt($string, $key='') {
	if (empty($key))
		$key=md5(ABSPATH);
	if (empty($string))
		return $string;
	//only decrypt if encrypted
	if (strpos($string,'$BackWPup$ENC1$')!==false)
		$string=str_replace('$BackWPup$ENC1$','',$string);
	else
		return $string;
	$result = '';
	$string = base64_decode($string);
	for($i=0; $i<strlen($string); $i++) {
		$char = substr($string, $i, 1);
		$keychar = substr($key, ($i % strlen($key))-1, 1);
		$char = chr(ord($char)-ord($keychar));
		$result.=$char;
	}
	return $result;
}

/**
 *
 * Get data off a working job
 *
 * @param bool $fulldata is full data needed or only that it working
 * @return bool|array false if not working, true or array with data if working
 */
function backwpup_get_workingdata($fulldata=true) {
	global $wpdb;
	if ($fulldata) {
		$workingdata=false;
		if (backwpup_get_option('cfg','storeworkingdatain')=='db')
			$workingdata=backwpup_get_option('working', 'data', false);
		if (backwpup_get_option('cfg','storeworkingdatain')=='file') {
			if (file_exists(backwpup_get_option('cfg','tempfolder').'.backwpup_working_'.substr(md5(ABSPATH),16)))
				$workingdata=maybe_unserialize(file_get_contents(backwpup_get_option('cfg','tempfolder').'.backwpup_working_'.substr(md5(ABSPATH),16)));
		}
		return $workingdata;
	} else {
		if (backwpup_get_option('cfg','storeworkingdatain')=='db') {
			$results=$wpdb->query("SELECT value FROM ".$wpdb->prefix."backwpup WHERE main='working' AND name='data' LIMIT 1");
			if ($results==1)
				return true;
		}
		if (backwpup_get_option('cfg','storeworkingdatain')=='file' && file_exists(backwpup_get_option('cfg','tempfolder').'.backwpup_working_'.substr(md5(ABSPATH),16)))
			return true;
		return false;
	}
}