<?PHP

function backwpup_update_option($mainname,$name,$value) {
	global $wpdb;
	$mainname=sanitize_key(trim($mainname));
	$name=sanitize_key(trim($name));
	if (empty($mainname) or empty($name))
		return false;
	if (is_object($value))
		$value = clone $value;
	$oldvalue=backwpup_get_option($mainname,$name);
	if ($value===$oldvalue)
		return false;
	if (false===$oldvalue)
		return backwpup_add_option($mainname,$name,$value);
	$value=maybe_serialize($value);
	$result=$wpdb->update($wpdb->prefix.'backwpup', array('value'=>$value), array('main_name'=>$mainname,'name'=>$name),array('%s'));
	if ($result)
		return true;
	else
		return false;
}

function backwpup_get_option($mainname,$name,$default=false) {
	global $wpdb;
	$mainname=sanitize_key(trim($mainname));
	$name=sanitize_key(trim($name));
	if (empty($mainname) or empty($name))
		return false;
	$value=$wpdb->get_row($wpdb->prepare("SELECT value FROM `".$wpdb->prefix."backwpup` WHERE `main_name`=%s AND `name`=%s LIMIT 1",$mainname,$name));
	if (is_object($value)) 
		return maybe_unserialize($value->value);
	else
		return $default;
}

function backwpup_add_option($mainname,$name,$value='') {
	global $wpdb;
	$mainname=sanitize_key(trim($mainname));
	$name=sanitize_key(trim($name));
	if (empty($mainname) or empty($name))
		return false;
	if (is_object($value))
		$value = clone $value;
	$value=maybe_serialize($value);
	$result=$wpdb->query( $wpdb->prepare( "INSERT INTO `".$wpdb->prefix."backwpup` (`main_name`, `name`, `value`) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE `main_name` = VALUES(`name`), `main_name` = VALUES(`name`), `value` = VALUES(`value`)", $mainname, $name, $value ) );
	if ($result)
		return true;
	else
		return false;
}

function backwpup_delete_option($mainname,$name) {	
	global $wpdb;
	$mainname=sanitize_key(trim($mainname));
	$name=sanitize_key(trim($name));
	if (empty($mainname) or empty($name))
		return false;
	$result=$wpdb->query($wpdb->prepare("DELETE FROM ".$wpdb->prefix."backwpup WHERE main_name=%s AND name=%s LIMIT 1",$mainname,$name));
	if ($result)
		return true;
	else
		return false;
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


function backwpup_formatBytes($bytes, $precision = 2) {
	$units = array('B', 'KB', 'MB', 'GB', 'TB');
	$bytes = max($bytes, 0);
	$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
	$pow = min($pow, count($units) - 1);
	$bytes /= pow(1024, $pow);
	return round($bytes, $precision) . ' ' . $units[$pow];
}

function backwpup_backup_types($type=array(),$echo=false) {
	$typename='';
	$type=(array)$type;
	if (!empty($type)) {
		foreach($type as $key => $value) {
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
		if ( 'wp-content/uploads' == $upload_path ) {
			$dir = WP_CONTENT_DIR . '/uploads';
		} elseif ( 0 !== strpos($dir, ABSPATH) ) {
			// $dir is absolute, $upload_path is (maybe) relative to ABSPATH
			$dir = path_join( ABSPATH, $dir );
		}
	}
	if (defined('UPLOADS') && !is_multisite()) {
		$dir = ABSPATH . UPLOADS;
	}
	if (is_multisite()) {
			$dir = untrailingslashit(WP_CONTENT_DIR).'/blogs.dir';
	}
	return str_replace('\\','/',trailingslashit($dir));
}

function backwpup_get_exclude_wp_dirs($folder) {
	global $backwpup_cfg;
	$folder=trailingslashit(str_replace('\\','/',$folder));
	$excludedir=array();
	$excludedir[]=$backwpup_cfg['tempfolder']; //exclude temp
	$excludedir[]=trailingslashit(str_replace('\\','/',$backwpup_cfg['logfolder'])); //exclude logfiles
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
	$jobs=get_option('backwpup_jobs');
	if (!empty($jobs)) {
		foreach($jobs as $jobsvale) {
			if (!empty($jobsvale['backupdir']) and $jobsvale['backupdir']!='/')
				$excludedir[]=$jobsvale['backupdir'];
		}
	}
	return $excludedir;
}

function backwpup_cron_next($cronstring) {
	//Cronstring zerlegen
	list($cronstr['minutes'],$cronstr['hours'],$cronstr['mday'],$cronstr['mon'],$cronstr['wday'])=explode(' ',$cronstring,5);

	//make arrys form string
	foreach ($cronstr as $key => $value) {
		if (strstr($value,','))
			$cronarray[$key]=explode(',',$value);
		else
			$cronarray[$key]=array(0=>$value);
	}
	//make arrys complete with ranges and steps
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

function backwpup_get_job_vars($jobid=0,$jobnewsettings='') {
	global $wpdb;
	//get job data
	if (!empty($jobid) and is_numeric($jobid)) {
		//load jobvalues
		$jobvars=$wpdb->get_results("SELECT name,value FROM `".$wpdb->prefix."backwpup` WHERE main_name='job_".$jobid."'");
		foreach ($jobvars as $vars) {
			$jobsettings[$vars->name]=maybe_unserialize($vars->value);
		}
	}
	if (empty($jobsettings['jobid'])) {  //generate jobid if not exists
		$jobsettings['jobid']=$wpdb->get_var("SELECT value FROM `".$wpdb->prefix."backwpup` WHERE main_name LIKE 'job_%' AND name='jobid' ORDER BY value ASC LIMIT 1",0,0);
		$jobsettings['jobid']++;
	}
	unset($jobid);
	
	//overwrite with new settings
	if (!empty($jobnewsettings) && is_array($jobnewsettings)) {
		$jobsettings=array_merge($jobsettings,$jobnewsettings);
	}

	//check job type
	if (!isset($jobsettings['type']) or !is_array($jobsettings['type']))
		$jobsettings['type']=array('DB','FILE');
	//Check existing types
	foreach($jobsettings['type'] as $key => $value) {
		$jobsettings['type'][$key]=strtoupper($jobsettings['type'][$key]);
		$value=strtoupper($value);
		if (!in_array($value,backwpup_backup_types()))
			unset($jobsettings['type'][$key]);
	}
	sort($jobsettings['type']);

	if (empty($jobsettings['name']) or !is_string($jobsettings['name']))
		$jobsettings['name']= __('New', 'backwpup');
	
	if (!isset($jobsettings['activated']))
		$jobsettings['activated']=false;
	else
		$jobsettings['activated']=(bool)$jobsettings['activated'];
		
	if (!isset($jobsettings['cronselect']) and !isset($jobsettings['cron']))
		$jobsettings['cronselect']='basic';
	elseif (!isset($jobsettings['cronselect']) and isset($jobsettings['cron']))
		$jobsettings['cronselect']='advanced';

	if ($jobsettings['cronselect']!='advanced' and $jobsettings['cronselect']!='basic')
		$jobsettings['cronselect']='advanced';

	if (!isset($jobsettings['cron']) or !is_string($jobsettings['cron']))
		$jobsettings['cron']='0 3 * * *';

	if (!isset($jobsettings['cronnextrun']) or !is_numeric($jobsettings['cronnextrun']))
		$jobsettings['cronnextrun']=backwpup_cron_next($jobsettings['cron']);

	if (!isset($jobsettings['mailaddresslog']) or !is_string($jobsettings['mailaddresslog']))
		$jobsettings['mailaddresslog']=get_option('admin_email');

	if (!isset($jobsettings['mailerroronly']))
		$jobsettings['mailerroronly']=true;
	else
		$jobsettings['mailerroronly']=(bool)$jobsettings['mailerroronly'];

	//Tables to backup (old)
	if (isset($jobsettings['dbtables']) and is_array($jobsettings['dbtables'])) {
		$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');
		foreach ($tables as $table) {
			if (!in_array($table,$jobsettings['dbtables']))
				$jobsettings['dbexclude'][]=$table;
		}
	}

	//don not backup tables
	if (!isset($jobsettings['dbexclude']) or !is_array($jobsettings['dbexclude'])) {
		$jobsettings['dbexclude']=array();
		$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');
		foreach ($tables as $table) {
			if (substr($table,0,strlen($wpdb->prefix))!=$wpdb->prefix)
				$jobsettings['dbexclude'][]=$table;
		}
	}

	if (!isset($jobsettings['dbshortinsert']))
		$jobsettings['dbshortinsert']=false;
	else
		$jobsettings['dbshortinsert']=(bool)$jobsettings['dbshortinsert'];

	if (!isset($jobsettings['dbdumpfile']) or empty($jobsettings['dbdumpfile']) or !is_string($jobsettings['dbdumpfile']))
		$jobsettings['dbdumpfile']=DB_NAME;

	if (!isset($jobsettings['dbdumpfilecompression']) or ($jobsettings['dbdumpfilecompression']!='gz' and $jobsettings['dbdumpfilecompression']!='bz2' and $jobsettings['dbdumpfilecompression']!=''))
		$jobsettings['dbdumpfilecompression']='';

	if (!isset($jobsettings['maintenance']))
		$jobsettings['maintenance']=false;
	else
		$jobsettings['maintenance']=(bool)$jobsettings['maintenance'];

	if (!isset($jobsettings['wpexportfile']) or empty($jobsettings['wpexportfile']) or !is_string($jobsettings['wpexportfile']))
		$jobsettings['wpexportfile']=sanitize_key(get_bloginfo('name')).'.wordpress.%Y-%m-%d';

	if (!isset($jobsettings['wpexportfilecompression']) or ($jobsettings['wpexportfilecompression']!='gz' and $jobsettings['wpexportfilecompression']!='bz2' and $jobsettings['dbdumpfilecompression']!=''))
		$jobsettings['wpexportfilecompression']='';		
		
	if (!isset($jobsettings['fileexclude']) or !is_string($jobsettings['fileexclude']))
		$jobsettings['fileexclude']='';
	$fileexclude=explode(',',$jobsettings['fileexclude']);
	foreach($fileexclude as $key => $value) {
		$fileexclude[$key]=str_replace('//','/',str_replace('\\','/',trim($value)));
		if (empty($fileexclude[$key]))
			unset($fileexclude[$key]);
	}
	sort($fileexclude);
	$jobsettings['fileexclude']=implode(',',$fileexclude);

	if (!isset($jobsettings['dirinclude']) or !is_string($jobsettings['dirinclude']))
		$jobsettings['dirinclude']='';
	$dirinclude=explode(',',$jobsettings['dirinclude']);
	foreach($dirinclude as $key => $value) {
		$dirinclude[$key]=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($value))));
		if ($dirinclude[$key]=='/' or empty($dirinclude[$key]) or !is_dir($dirinclude[$key]))
			unset($dirinclude[$key]);
	}
	sort($dirinclude);
	$jobsettings['dirinclude']=implode(',',$dirinclude);

	if (!isset($jobsettings['backuproot']))
		$jobsettings['backuproot']=true;
	else
		$jobsettings['backuproot']=(bool)$jobsettings['backuproot'];

	if (!isset($jobsettings['backupcontent']))
		$jobsettings['backupcontent']=true;
	else
		$jobsettings['backupcontent']=(bool)$jobsettings['backupcontent'];

	if (!isset($jobsettings['backupplugins']))
		$jobsettings['backupplugins']=true;
	else
		$jobsettings['backupplugins']=(bool)$jobsettings['backupplugins'];
		
	if (!isset($jobsettings['backupthemes']))
		$jobsettings['backupthemes']=true;
	else
		$jobsettings['backupthemes']=(bool)$jobsettings['backupthemes'];

	if (!isset($jobsettings['backupuploads']))
		$jobsettings['backupuploads']=true;
	else
		$jobsettings['backupuploads']=(bool)$jobsettings['backupuploads'];

	if (!isset($jobsettings['backuprootexcludedirs']) or !is_array($jobsettings['backuprootexcludedirs']))
		$jobsettings['backuprootexcludedirs']=array();
	foreach($jobsettings['backuprootexcludedirs'] as $key => $value) {
		$jobsettings['backuprootexcludedirs'][$key]=str_replace('//','/',str_replace('\\','/',trim($value)));
		if (empty($jobsettings['backuprootexcludedirs'][$key]) or $jobsettings['backuprootexcludedirs'][$key]=='/' or !is_dir($jobsettings['backuprootexcludedirs'][$key]))
			unset($jobsettings['backuprootexcludedirs'][$key]);
	}
	sort($jobsettings['backuprootexcludedirs']);

	if (!isset($jobsettings['backupcontentexcludedirs']) or !is_array($jobsettings['backupcontentexcludedirs']))
		$jobsettings['backupcontentexcludedirs']=array();
	foreach($jobsettings['backupcontentexcludedirs'] as $key => $value) {
		$jobsettings['backupcontentexcludedirs'][$key]=str_replace('//','/',str_replace('\\','/',trim($value)));
		if (empty($jobsettings['backupcontentexcludedirs'][$key]) or $jobsettings['backupcontentexcludedirs'][$key]=='/' or !is_dir($jobsettings['backupcontentexcludedirs'][$key]))
			unset($jobsettings['backupcontentexcludedirs'][$key]);
	}
	sort($jobsettings['backupcontentexcludedirs']);

	if (!isset($jobsettings['backuppluginsexcludedirs']) or !is_array($jobsettings['backuppluginsexcludedirs']))
		$jobsettings['backuppluginsexcludedirs']=array();
	foreach($jobsettings['backuppluginsexcludedirs'] as $key => $value) {
		$jobsettings['backuppluginsexcludedirs'][$key]=str_replace('//','/',str_replace('\\','/',trim($value)));
		if (empty($jobsettings['backuppluginsexcludedirs'][$key]) or $jobsettings['backuppluginsexcludedirs'][$key]=='/' or !is_dir($jobsettings['backuppluginsexcludedirs'][$key]))
			unset($jobsettings['backuppluginsexcludedirs'][$key]);
	}
	sort($jobsettings['backuppluginsexcludedirs']);

	if (!isset($jobsettings['backupthemesexcludedirs']) or !is_array($jobsettings['backupthemesexcludedirs']))
		$jobsettings['backupthemesexcludedirs']=array();
	foreach($jobsettings['backupthemesexcludedirs'] as $key => $value) {
		$jobsettings['backupthemesexcludedirs'][$key]=str_replace('//','/',str_replace('\\','/',trim($value)));
		if (empty($jobsettings['backupthemesexcludedirs'][$key]) or $jobsettings['backupthemesexcludedirs'][$key]=='/' or !is_dir($jobsettings['backupthemesexcludedirs'][$key]))
			unset($jobsettings['backupthemesexcludedirs'][$key]);
	}
	sort($jobsettings['backupthemesexcludedirs']);

	if (!isset($jobsettings['backupuploadsexcludedirs']) or !is_array($jobsettings['backupuploadsexcludedirs']))
		$jobsettings['backupuploadsexcludedirs']=array();
	foreach($jobsettings['backupuploadsexcludedirs'] as $key => $value) {
		$jobsettings['backupuploadsexcludedirs'][$key]=str_replace('//','/',str_replace('\\','/',trim($value)));
		if (empty($jobsettings['backupuploadsexcludedirs'][$key]) or $jobsettings['backupuploadsexcludedirs'][$key]=='/' or !is_dir($jobsettings['backupuploadsexcludedirs'][$key]))
			unset($jobsettings['backupuploadsexcludedirs'][$key]);
	}
	sort($jobsettings['backupuploadsexcludedirs']);

	if (!isset($jobsettings['backuptype']) or ($jobsettings['backuptype']!='archive' and $jobsettings['backuptype']!='sync'))
		$jobsettings['backuptype']='archive';
	
	$fileformarts=array('.zip','.tar.gz','.tar.bz2','.tar');
	if (!isset($jobsettings['fileformart']) or !in_array($jobsettings['fileformart'],$fileformarts))
		$jobsettings['fileformart']='.zip';

	if (!isset($jobsettings['fileprefix']) or !is_string($jobsettings['fileprefix']))
		$jobsettings['fileprefix']='backwpup_'.$jobsettings['jobid'].'_';

	if (!isset($jobsettings['mailefilesize']) or !is_float($jobsettings['mailefilesize']))
		$jobsettings['mailefilesize']=0;

	if (!isset($jobsettings['backupdir']))
		$jobsettings['backupdir']='';
	if (substr($jobsettings['backupdir'],0,1)!='/' and substr($jobsettings['backupdir'],1,1)!=':' and !empty($jobsettings['backupdir'])) //add abspath if not absolute
		$jobsettings['backupdir']=rtrim(str_replace('\\','/',ABSPATH),'/').'/'.$jobsettings['backupdir'];
	$jobsettings['backupdir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($jobsettings['backupdir']))));
	if ($jobsettings['backupdir']=='/')
		$jobsettings['backupdir']='';

	if (!isset($jobsettings['maxbackups']) or !is_numeric($jobsettings['maxbackups']))
		$jobsettings['maxbackups']=0;

	if (!empty($jobsettings['ftphost']) and false !== strpos($jobsettings['ftphost'],':'))
		list($jobsettings['ftphost'],$jobsettings['ftphostport'])=explode(':',$jobsettings['ftphost'],2);

	if (!isset($jobsettings['ftphost']) or !is_string($jobsettings['ftphost']))
		$jobsettings['ftphost']='';

	if (!isset($jobsettings['ftphostport']) or !is_numeric($jobsettings['ftphostport']))
		$jobsettings['ftphostport']=21;

	if (!isset($jobsettings['ftpuser']) or !is_string($jobsettings['ftpuser']))
		$jobsettings['ftpuser']='';

	if (!isset($jobsettings['ftppass']) or !is_string($jobsettings['ftppass']))
		$jobsettings['ftppass']='';

	if (!isset($jobsettings['ftpdir']) or !is_string($jobsettings['ftpdir']) or $jobsettings['ftpdir']=='/')
		$jobsettings['ftpdir']='';
	$jobsettings['ftpdir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($jobsettings['ftpdir']))));
	if (substr($jobsettings['ftpdir'],0,1)!='/')
		$jobsettings['ftpdir']='/'.$jobsettings['ftpdir'];

	if (!isset($jobsettings['ftpmaxbackups']) or !is_numeric($jobsettings['ftpmaxbackups']))
		$jobsettings['ftpmaxbackups']=0;

	if (!isset($jobsettings['ftppasv']))
		$jobsettings['ftppasv']=true;
	else
		$jobsettings['ftppasv']=(bool)$jobsettings['ftppasv'];

	if (!isset($jobsettings['ftpssl']) or !function_exists('ftp_ssl_connect'))
		$jobsettings['ftpssl']=false;
	else 
		$jobsettings['ftpssl']=(bool)$jobsettings['ftpssl'];

	if (!isset($jobsettings['awsAccessKey']) or !is_string($jobsettings['awsAccessKey']))
		$jobsettings['awsAccessKey']='';

	if (!isset($jobsettings['awsSecretKey']) or !is_string($jobsettings['awsSecretKey']))
		$jobsettings['awsSecretKey']='';

	if (!isset($jobsettings['awsssencrypt']) or ($jobsettings['awsssencrypt']!='' and $jobsettings['awsssencrypt']!='AES256'))
		$jobsettings['awsssencrypt']='';

	if (!isset($jobsettings['awsrrs']))
		$jobsettings['awsrrs']=false;
	else
		$jobsettings['awsrrs']=(bool)$jobsettings['awsrrs'];

	if (!isset($jobsettings['awsBucket']) or !is_string($jobsettings['awsBucket']))
		$jobsettings['awsBucket']='';

	if (!isset($jobsettings['awsdir']) or !is_string($jobsettings['awsdir']) or $jobsettings['awsdir']=='/')
		$jobsettings['awsdir']='';
	$jobsettings['awsdir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($jobsettings['awsdir']))));
	if (substr($jobsettings['awsdir'],0,1)=='/')
		$jobsettings['awsdir']=substr($jobsettings['awsdir'],1);

	if (!isset($jobsettings['awsmaxbackups']) or !is_numeric($jobsettings['awsmaxbackups']))
		$jobsettings['awsmaxbackups']=0;

	if (!isset($jobsettings['GStorageAccessKey']) or !is_string($jobsettings['GStorageAccessKey']))
		$jobsettings['GStorageAccessKey']='';

	if (!isset($jobsettings['GStorageSecret']) or !is_string($jobsettings['GStorageSecret']))
		$jobsettings['GStorageSecret']='';

	if (!isset($jobsettings['GStorageBucket']) or !is_string($jobsettings['GStorageBucket']))
		$jobsettings['GStorageBucket']='';

	if (!isset($jobsettings['GStoragedir']) or !is_string($jobsettings['GStoragedir']) or $jobsettings['GStoragedir']=='/')
		$jobsettings['GStoragedir']='';
	$jobsettings['GStoragedir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($jobsettings['GStoragedir']))));
	if (substr($jobsettings['GStoragedir'],0,1)=='/')
		$jobsettings['GStoragedir']=substr($jobsettings['GStoragedir'],1);

	if (!isset($jobsettings['GStoragemaxbackups']) or !is_numeric($jobsettings['GStoragemaxbackups']))
		$jobsettings['GStoragemaxbackups']=0;

	if (!isset($jobsettings['msazureHost']) or !is_string($jobsettings['msazureHost']))
		$jobsettings['msazureHost']='blob.core.windows.net';

	if (!isset($jobsettings['msazureAccName']) or !is_string($jobsettings['msazureAccName']))
		$jobsettings['msazureAccName']='';

	if (!isset($jobsettings['msazureKey']) or !is_string($jobsettings['msazureKey']))
		$jobsettings['msazureKey']='';

	if (!isset($jobsettings['msazureContainer']) or !is_string($jobsettings['msazureContainer']))
		$jobsettings['msazureContainer']='';

	if (!isset($jobsettings['msazuredir']) or !is_string($jobsettings['msazuredir']) or $jobsettings['msazuredir']=='/')
		$jobsettings['msazuredir']='';
	$jobsettings['msazuredir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($jobsettings['msazuredir']))));
	if (substr($jobsettings['msazuredir'],0,1)=='/')
		$jobsettings['msazuredir']=substr($jobsettings['msazuredir'],1);

	if (!isset($jobsettings['msazuremaxbackups']) or !is_numeric($jobsettings['msazuremaxbackups']))
		$jobsettings['msazuremaxbackups']=0;

	if (!isset($jobsettings['rscUsername']) or !is_string($jobsettings['rscUsername']))
		$jobsettings['rscUsername']='';

	if (!isset($jobsettings['rscAPIKey']) or !is_string($jobsettings['rscAPIKey']))
		$jobsettings['rscAPIKey']='';

	if (!isset($jobsettings['rscContainer']) or !is_string($jobsettings['rscContainer']))
		$jobsettings['rscContainer']='';

	if (!isset($jobsettings['rscdir']) or !is_string($jobsettings['rscdir']) or $jobsettings['rscdir']=='/')
		$jobsettings['rscdir']='';
	$jobsettings['rscdir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($jobsettings['rscdir']))));
	if (substr($jobsettings['rscdir'],0,1)=='/')
		$jobsettings['rscdir']=substr($jobsettings['rscdir'],1);
		
	if (!isset($jobsettings['rscmaxbackups']) or !is_numeric($jobsettings['rscmaxbackups']))
		$jobsettings['rscmaxbackups']=0;

	if (!isset($jobsettings['dropetoken']) or !is_string($jobsettings['dropetoken']))
		$jobsettings['dropetoken']='';

	if (!isset($jobsettings['dropesecret']) or !is_string($jobsettings['dropesecret']))
		$jobsettings['dropesecret']='';

	if (!isset($jobsettings['dropedir']) or !is_string($jobsettings['dropedir']) or $jobsettings['dropedir']=='/')
		$jobsettings['dropedir']='';
	$jobsettings['dropedir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($jobsettings['dropedir']))));
	if (substr($jobsettings['dropedir'],0,1)=='/')
		$jobsettings['dropedir']=substr($jobsettings['dropedir'],1);

	if (!isset($jobsettings['droperoot']) or ($jobsettings['droperoot']!='dropbox' and $jobsettings['droperoot']!='sandbox'))
		$jobsettings['droperoot']='sandbox';

	if (!isset($jobsettings['dropemaxbackups']) or !is_numeric($jobsettings['dropemaxbackups']))
		$jobsettings['dropemaxbackups']=0;

	if (!isset($jobsettings['boxnetauth']) or !is_string($jobsettings['boxnetauth']))
		$jobsettings['boxnetauth']='';	

	if (!isset($jobsettings['boxnetdir']) or !is_string($jobsettings['boxnetdir']) or $jobsettings['boxnetdir']=='/')
		$jobsettings['boxnetdir']='';
	$jobsettings['boxnetdir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($jobsettings['boxnetdir']))));
	if (substr($jobsettings['boxnetdir'],0,1)=='/')
		$jobsettings['boxnetdir']=substr($jobsettings['boxnetdir'],1);

	if (!isset($jobsettings['boxnetbackups']) or !is_numeric($jobsettings['boxnetbackups']))
		$jobsettings['boxnetbackups']=0;		
		
	if (!isset($jobsettings['sugaruser']) or !is_string($jobsettings['sugaruser']))
		$jobsettings['sugaruser']='';

	if (!isset($jobsettings['sugarpass']) or !is_string($jobsettings['sugarpass']))
		$jobsettings['sugarpass']='';

	if (!isset($jobsettings['sugarroot']) or !is_string($jobsettings['sugarroot']))
		$jobsettings['sugarroot']='';

	if (!isset($jobsettings['sugardir']) or !is_string($jobsettings['sugardir']) or $jobsettings['sugardir']=='/')
		$jobsettings['sugardir']='';
	$jobsettings['sugardir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($jobsettings['sugardir']))));
	if (substr($jobsettings['sugardir'],0,1)=='/')
		$jobsettings['sugardir']=substr($jobsettings['sugardir'],1);

	if (!isset($jobsettings['sugarmaxbackups']) or !is_numeric($jobsettings['sugarmaxbackups']))
		$jobsettings['sugarmaxbackups']=0;

	if (!isset($jobsettings['mailaddress']) or !is_string($jobsettings['mailaddress']))
		$jobsettings['mailaddress']='';

	return $jobsettings;
}
?>