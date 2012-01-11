<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function backwpup_plugin_init() {
	//start upgrade if needed
	if (backwpup_get_option('cfg','dbversion')!=BACKWPUP_VERSION) {
		require_once(dirname(__FILE__).'/upgrade.php');
		backwpup_upgrade();
	}
	//add admin bar. Works only in init
	if (!defined('DOING_CRON') and backwpup_get_option('cfg','showadminbar') and current_user_can(BACKWPUP_USER_CAPABILITY) and is_admin_bar_showing())
		include_once(dirname(__FILE__).'/adminbar.php');
	//load Api for update checks and so on
	include_once(dirname(__FILE__).'/api.php');
}
add_action('init','backwpup_plugin_init');

function backwpup_default_option_settings($main,$name) {
	$main=sanitize_key(trim($main));
	$name=sanitize_key(trim($name));
	//set defaults
	if ($main=='cfg') { //for settings
		$default['cfg']['dbversion']='0.0';
		$default['cfg']['mailsndemail']=sanitize_email(get_bloginfo( 'admin_email' ));
		$default['cfg']['mailsndname']='BackWPup '.get_bloginfo('name');
		$default['cfg']['showadminbar']=true;
		$default['cfg']['jobstepretry']=3;
		$default['cfg']['jobscriptretry']=5;
		$default['cfg']['maxlogs']=50;
		$default['cfg']['gzlogs']=false;
		$default['cfg']['logfolder']=str_replace('\\','/',trailingslashit(WP_CONTENT_DIR)).'backwpup-'.substr(md5(md5(SECURE_AUTH_KEY)), -5).'-logs/';
		$default['cfg']['httpauthuser']='';
		$default['cfg']['httpauthpassword']='';
		$default['cfg']['jobrunauthkey']='';
		$default['cfg']['apicronservicekey']='';
		$default['cfg']['jobrunmaxexectime']=0;
		if ($name=='tempfolder') {
			if (defined('WP_TEMP_DIR')) //get temp folder
				$default['cfg']['tempfolder']=trim(WP_TEMP_DIR);
			if (empty($default['cfg']['tempfolder']) or !backwpup_check_open_basedir($default['cfg']['tempfolder']) or !@is_writable($default['cfg']['tempfolder']) or !@is_dir($default['cfg']['tempfolder']))
				$default['cfg']['tempfolder']=sys_get_temp_dir();									//normal temp dir
			if (empty($default['cfg']['tempfolder']) or !backwpup_check_open_basedir($default['cfg']['tempfolder']) or !@is_writable($default['cfg']['tempfolder']) or !@is_dir($default['cfg']['tempfolder']))
				$default['cfg']['tempfolder']=ini_get('upload_tmp_dir');							//if sys_get_temp_dir not work
			if (empty($default['cfg']['tempfolder']) or !backwpup_check_open_basedir($default['cfg']['tempfolder']) or !@is_writable($default['cfg']['tempfolder']) or !@is_dir($default['cfg']['tempfolder']))
				$default['cfg']['tempfolder']=WP_CONTENT_DIR.'/';
			if (empty($default['cfg']['tempfolder']) or !backwpup_check_open_basedir($default['cfg']['tempfolder']) or !@is_writable($default['cfg']['tempfolder']) or !@is_dir($default['cfg']['tempfolder']))
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
		$default[$main]['cronnextrun']=backwpup_cron_next('0 3 * * *');
		$default[$main]['mailaddresslog']=get_option('admin_email');
		$default[$main]['mailerroronly']=true;
		if ($name=='dbexclude') {
			$default[$main]['dbexclude']=array();
			global $wpdb;
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
		$default[$main]['wpexportfilecompression']='';
		$default[$main]['fileexclude']='';
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
		$default[$main]['ftptimeout']=10;
		$default[$main]['ftpuser']='';
		$default[$main]['ftpdir']='';
		$default[$main]['ftpmaxbackups']=0;
		$default[$main]['ftppasv']=true;
		$default[$main]['ftpssl']=false;
		$default[$main]['ftpsyncnodelete']=true;
		$default[$main]['awsAccessKey']='';
		$default[$main]['awsSecretKey']='';
		$default[$main]['awsssencrypt']='';
		$default[$main]['awsrrs']=false;
		$default[$main]['awsBucket']='';
		$default[$main]['awsdir']='';
		$default[$main]['awsmaxbackups']=0;
		$default[$main]['awssyncnodelete']=true;
		$default[$main]['GStorageAccessKey']='';
		$default[$main]['GStorageSecret']='';
		$default[$main]['GStorageBucket']='';
		$default[$main]['GStoragedir']='';
		$default[$main]['GStoragemaxbackups']=0;
		$default[$main]['GStoragesyncnodelete']=true;
		$default[$main]['msazureHost']='blob.core.windows.net';
		$default[$main]['msazureAccName']='';
		$default[$main]['msazureKey']='';
		$default[$main]['msazureContainer']='';
		$default[$main]['msazuredir']='';
		$default[$main]['msazuremaxbackups']=0;
		$default[$main]['msazuresyncnodelete']=true;
		$default[$main]['rscUsername']='';
		$default[$main]['rscAPIKey']='';
		$default[$main]['rscContainer']='';
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
	if ($main=='temp') {
		$default['temp']['apiapp']=false;
	}
	//return defaults
	if(isset($default[$main][$name]))
		return maybe_serialize($default[$main][$name]);
	else
		return false;
}

function backwpup_update_option($main,$name,$value) {
	global $wpdb;
	$main=sanitize_key(trim($main));
	$name=sanitize_key(trim($name));
	$oldvalue='';
	$alloptions=wp_cache_get( 'options', 'backwpup' );
	if (empty($main) or empty($name))
		return false;
	if (is_object($value))
		$value = clone $value;
	$value=maybe_serialize($value);
	//unset if it a default option value
	if (isset($alloptions[$main][$name]) and $alloptions[$main][$name]==backwpup_default_option_settings($main,$name))
		unset($alloptions[$main][$name]);
	//is value same as old do nothing
	if (isset($alloptions[$main][$name]) and $alloptions[$main][$name]==$value)
		return false;
	if (!isset($alloptions[$main][$name])) { //sql if not in cache
		$oldvalue=$wpdb->get_row($wpdb->prepare("SELECT value FROM ".$wpdb->prefix."backwpup WHERE main=%s AND name=%s LIMIT 1",$main,$name));
		if (is_object($oldvalue) and $oldvalue->value==$value)
			return false;
	}
	//Update or insert
	if (isset($alloptions[$main][$name]) or is_object($oldvalue))
		$result=$wpdb->update( $wpdb->prefix.'backwpup', array( 'value' => $value ), array( 'main' => $main, 'name' => $name ),array('%s'),array('%s','%s') );
	 else
		$result=$wpdb->insert( $wpdb->prefix.'backwpup', array( 'main' => $main, 'name' => $name, 'value' => $value ), '%s' );
	if ($result) {
		$alloptions[$main][$name]=$value;
		wp_cache_set( 'options', $alloptions, 'backwpup' );
		return true;
	} else
		return false;
}

function backwpup_get_option($main,$name,$default=false) {
	global $wpdb;
	$main=sanitize_key(trim($main));
	$name=sanitize_key(trim($name));
	if (empty($main) or empty($name))
		return false;
	$alloptions=wp_cache_get( 'options', 'backwpup' );
	//load options to cache if empty
	if ($alloptions==false) {
		$option_cache_req = $wpdb->get_results( "SELECT main,name,value FROM ".$wpdb->prefix."backwpup ORDER BY main,name" );
		if (is_array($option_cache_req)) {
			foreach ($option_cache_req as $option)
				$alloptions[$option->main][$option->name]=$option->value;
		}
		wp_cache_set( 'options', $alloptions, 'backwpup' );
	}
	// output from cache or db
	if (isset($alloptions[$main][$name])) {
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
		$alloptions[$main][$name]=$otionvalue;
		wp_cache_set( 'options', $alloptions, 'backwpup' );
		return $otionvalue;
	}
}

function backwpup_delete_option($main,$name) {
	global $wpdb;
	$main=sanitize_key(trim($main));
	$name=sanitize_key(trim($name));
	$alloptions=wp_cache_get( 'options', 'backwpup' );
	if (empty($main) or empty($name))
		return false;
	$result=$wpdb->query($wpdb->prepare("DELETE FROM ".$wpdb->prefix."backwpup WHERE main=%s AND name=%s LIMIT 1",$main,$name));
	if ($result) {
		if (isset($alloptions[$main][$name])) {
			unset($alloptions[$main][$name]);
			wp_cache_set( 'options', $alloptions, 'backwpup' );
		}
		return true;
	} else
		return false;
}


function backwpup_jobrun_url($starttype,$jobid='',$run=false) {
	$url=BACKWPUP_PLUGIN_BASEURL.'/job.php';
	$query_args['jobid']=$jobid;
	$header='';
	$authurl='';

	if (in_array($starttype, array('restarttime', 'restart', 'runnow', 'cronrun', 'runext','apirun' )))
		$query_args['starttype']=$starttype;

	if (backwpup_get_option('cfg','httpauthuser') and backwpup_get_option('cfg','httpauthpassword')) {
		$header=array( 'Authorization' => 'Basic '.base64_encode(backwpup_get_option('cfg','httpauthuser').':'.backwpup_decrypt(backwpup_get_option('cfg','httpauthpassword'))));
		$authurl=backwpup_get_option('cfg','httpauthuser').':'.backwpup_decrypt(backwpup_get_option('cfg','httpauthpassword')).'@';
	}

	if (WP_PLUGIN_DIR==ABSPATH.'/wp-content/plugins')
		$query_args['ABSPATH']=urlencode(str_replace('\\','/',ABSPATH));

	if ($starttype=='apirun')
		$query_args['_nonce']=backwpup_get_option('cfg','apicronservicekey');
	elseif ($starttype=='runext') {
		$query_args['_nonce']=backwpup_get_option('cfg','jobrunauthkey');
		if (!empty($authurl)) {
			$url=str_replace('https://','https://'.$authurl,$url);
			$url=str_replace('http://','http://'.$authurl,$url);
		}
	} else
		$query_args['_nonce']=wp_create_nonce('BackWPupJobRun'.$jobid.$starttype);

	if ($run)
		return @wp_remote_get(add_query_arg($query_args, $url), array('timeout' => 5, 'blocking' => false, 'sslverify' => false, 'headers'=>$header, 'user-agent'=>'BackWPup'));
	else
		return array('url'=>add_query_arg($query_args, $url),'header'=>$header);
}

function backwpup_check_open_basedir($dir) {
	if (!ini_get('open_basedir'))
		return true;
	$openbasedirarray=explode(PATH_SEPARATOR,ini_get('open_basedir'));
	$dir=rtrim(str_replace('\\','/',$dir),'/').'/';
	if (!empty($openbasedirarray)) {
		foreach ($openbasedirarray as $basedir) {
			if (stripos($dir,rtrim(str_replace('\\','/',$basedir),'/').'/')==0)
				return true;
		}
	} 
	return false;
}

function backwpup_format_bytes($bytes, $precision = 2) {
	$units = array('B', 'KB', 'MB', 'GB', 'TB');
	$bytes = max($bytes, 0);
	$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
	$pow = min($pow, count($units) - 1);
	$bytes /= pow(1024, $pow);
	return round($bytes, $precision) . ' ' . $units[$pow];
}

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
	else
		return $typename;
}

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

function backwpup_get_upload_dir() {
	$upload_path = get_option('upload_path');
	$upload_path = trim($upload_path);
	if ( empty($upload_path) ) {
		$dir = WP_CONTENT_DIR . '/uploads';
	} else {
		$dir = $upload_path;
		if ( 'wp-content/uploads' == $upload_path )
			$dir = WP_CONTENT_DIR . '/uploads';
		elseif ( 0 !== strpos($dir, ABSPATH) )
			$dir = path_join( ABSPATH, $dir );
	}
	if (defined('UPLOADS') && !is_multisite())
		$dir = ABSPATH . UPLOADS;
	if (is_multisite())
			$dir = untrailingslashit(WP_CONTENT_DIR).'/blogs.dir';
	return str_replace('\\','/',trailingslashit($dir));
}

function backwpup_get_exclude_wp_dirs($folder) {
	global $wpdb;
	$folder=trailingslashit(str_replace('\\','/',$folder));
	$excludedir=array();
	$excludedir[]=backwpup_get_option('cfg','tempfolder'); //exclude temp
	$excludedir[]=backwpup_get_option('cfg','logfolder'); //exclude logfiles
	if (false !== strpos(trailingslashit(str_replace('\\','/',ABSPATH)),$folder) and trailingslashit(str_replace('\\','/',ABSPATH))!=$folder)
		$excludedir[]=trailingslashit(str_replace('\\','/',ABSPATH));
	if (false !== strpos(trailingslashit(str_replace('\\','/',WP_CONTENT_DIR)),$folder) and trailingslashit(str_replace('\\','/',WP_CONTENT_DIR))!=$folder)
		$excludedir[]=trailingslashit(str_replace('\\','/',WP_CONTENT_DIR));
	if (false !== strpos(trailingslashit(str_replace('\\','/',WP_PLUGIN_DIR)),$folder) and trailingslashit(str_replace('\\','/',WP_PLUGIN_DIR))!=$folder)
		$excludedir[]=trailingslashit(str_replace('\\','/',WP_PLUGIN_DIR));
	if (false !== strpos(str_replace('\\','/',trailingslashit(WP_CONTENT_DIR).'themes/'),$folder) and str_replace('\\','/',trailingslashit(WP_CONTENT_DIR).'themes/')!=$folder)
		$excludedir[]=str_replace('\\','/',trailingslashit(WP_CONTENT_DIR).'themes/');
	if (false !== strpos(backwpup_get_upload_dir(),$folder) and backwpup_get_upload_dir()!=$folder)
		$excludedir[]=backwpup_get_upload_dir();
	//Exclude Backup dirs
	$value=wp_cache_get('exclude','backwpup');
	if (false==$value) {
		$value=$wpdb->get_col("SELECT value FROM `".$wpdb->prefix."backwpup` WHERE main LIKE 'job_%' AND name='backupdir' and value<>'' and value<>'/' ");
		wp_cache_set('exclude',$value,'backwpup');
	}
	if (!empty($value)) {
		foreach($value as $backupdir)
				$excludedir[]=$backupdir;
	}
	return $excludedir;
}

function backwpup_cron_next($cronstring) {
	//Cronstring
	list($cronstr['minutes'],$cronstr['hours'],$cronstr['mday'],$cronstr['mon'],$cronstr['wday'])=explode(' ',$cronstring,5);

	//make arrys form string
	foreach ($cronstr as $key => $value) {
		if (strstr($value,','))
			$cronarray[$key]=explode(',',$value);
		else
			$cronarray[$key]=array(0=>$value);
	}
	//make arryas complete with ranges and steps
	foreach ($cronarray as $cronarraykey => $cronarrayvalue) {
		$cron[$cronarraykey]=array();
		foreach ($cronarrayvalue as $key => $value) {
			//steps
			$step=1;
			if (strstr($value,'/'))
				list($value,$step)=explode('/',$value,2);
			//replase weekday 7 with 0 for sundays
			if ($cronarraykey=='wday')
				$value=str_replace('7','0',$value);
			//ranges
			if (strstr($value,'-')) {
				list($first,$last)=explode('-',$value,2);
				if (!is_numeric($first) or !is_numeric($last) or $last>60 or $first>60) //check
					return 2147483647;
				if ($cronarraykey=='minutes' and $step<5)  //set step ninimum to 5 min.
					$step=5;
				$range=array();
				for ($i=$first;$i<=$last;$i=$i+$step)
					$range[]=$i;
				$cron[$cronarraykey]=array_merge($cron[$cronarraykey],$range);
			} elseif ($value=='*') {
				$range=array();
				if ($cronarraykey=='minutes') {
					if ($step<5) //set step ninimum to 5 min.
						$step=5;
					for ($i=0;$i<=59;$i=$i+$step)
						$range[]=$i;
				}
				if ($cronarraykey=='hours') {
					for ($i=0;$i<=23;$i=$i+$step)
						$range[]=$i;
				}
				if ($cronarraykey=='mday') {
					for ($i=$step;$i<=31;$i=$i+$step)
						$range[]=$i;
				}
				if ($cronarraykey=='mon') {
					for ($i=$step;$i<=12;$i=$i+$step)
						$range[]=$i;
				}
				if ($cronarraykey=='wday') {
					for ($i=0;$i<=6;$i=$i+$step)
						$range[]=$i;
				}
				$cron[$cronarraykey]=array_merge($cron[$cronarraykey],$range);
			} else {
				//Month names
				if (strtolower($value)=='jan')
					$value=1;
				if (strtolower($value)=='feb')
					$value=2;
				if (strtolower($value)=='mar')
					$value=3;
				if (strtolower($value)=='apr')
					$value=4;
				if (strtolower($value)=='may')
					$value=5;
				if (strtolower($value)=='jun')
					$value=6;
				if (strtolower($value)=='jul')
					$value=7;
				if (strtolower($value)=='aug')
					$value=8;
				if (strtolower($value)=='sep')
					$value=9;
				if (strtolower($value)=='oct')
					$value=10;
				if (strtolower($value)=='nov')
					$value=11;
				if (strtolower($value)=='dec')
					$value=12;
				//Week Day names
				if (strtolower($value)=='sun')
					$value=0;
				if (strtolower($value)=='sat')
					$value=6;
				if (strtolower($value)=='mon')
					$value=1;
				if (strtolower($value)=='tue')
					$value=2;
				if (strtolower($value)=='wed')
					$value=3;
				if (strtolower($value)=='thu')
					$value=4;
				if (strtolower($value)=='fri')
					$value=5;
				if (!is_numeric($value) or $value>60) //check
					return 2147483647;
				$cron[$cronarraykey]=array_merge($cron[$cronarraykey],array(0=>$value));
			}
		}
	}
	//generate next 10 years
	for ($i=date('Y');$i<2038;$i++)
		$cron['year'][]=$i;

	//calc next timestamp
	$currenttime=current_time('timestamp');
	foreach ($cron['year'] as $year) {
		foreach ($cron['mon'] as $mon) {
			foreach ($cron['mday'] as $mday) {
				foreach ($cron['hours'] as $hours) {
					foreach ($cron['minutes'] as $minutes) {
						$timestamp=mktime($hours,$minutes,0,$mon,$mday,$year);
						if ($timestamp and in_array(date('j',$timestamp),$cron['mday']) and in_array(date('w',$timestamp),$cron['wday']) and $timestamp>$currenttime) {
							return $timestamp;
						}
					}
				}
			}
		}
	}
	return 2147483647;
}

function backwpup_admin_url($url) {
	if (is_multisite()) {
		if  (is_super_admin())
			return network_admin_url($url);
	} else {
		return admin_url($url);
	}
}

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
?>