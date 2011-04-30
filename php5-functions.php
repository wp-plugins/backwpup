<?PHP
// don't load directly
if ( !defined('ABSPATH') )
	die('-1');

//Checking,upgrade and default job setting
function backwpup_check_job_vars($jobsettings,$jobid) {
	global $wpdb;
	//check job type
	if (!isset($jobsettings['type']) or !is_string($jobsettings['type']))
		$jobsettings['type']='DB+FILE';
	$todo=explode('+',strtoupper($jobsettings['type']));
	foreach($todo as $key => $value) {
		if (!in_array($value,backwpup_backup_types()))
			unset($todo[$key]);
	}
	$jobsettings['type']=implode('+',$todo);
	if (empty($jobsettings['type']))
		$jobsettings['type']='DB+FILE';

	if (empty($jobsettings['name']) or !is_string($jobsettings['name']))
		$jobsettings['name']= __('New');

	if (!isset($jobsettings['activated']) or !is_bool($jobsettings['activated']))
		$jobsettings['activated']=false;

	//upgrade old schedule
	if (!isset($jobsettings['cron']) and isset($jobsettings['scheduletime']) and isset($jobsettings['scheduleintervaltype']) and isset($jobsettings['scheduleintervalteimes'])) {  //Upgrade to cron string
		if ($jobsettings['scheduleintervaltype']==60) { //Min
			$jobsettings['cron']='*/'.$jobsettings['scheduleintervalteimes'].' * * * *';
		}
		if ($jobsettings['scheduleintervaltype']==3600) { //Houer
			$jobsettings['cron']=(date('i',$jobsettings['scheduletime'])*1).' */'.$jobsettings['scheduleintervalteimes'].' * * *';
		}
		if ($jobsettings['scheduleintervaltype']==86400) {  //Days
			$jobsettings['cron']=(date('i',$jobsettings['scheduletime'])*1).' '.date('G',$jobsettings['scheduletime']).' */'.$jobsettings['scheduleintervalteimes'].' * *';
		}
	}

	if (!isset($jobsettings['cron']) or !is_string($jobsettings['cron']))
		$jobsettings['cron']='0 3 * * *';
		
	if (!isset($jobsettings['cronnextrun']) or !is_numeric($jobsettings['cronnextrun']))
		$jobsettings['cronnextrun']=backwpup_cron_next($jobsettings['cron']);;
		
	if (!isset($jobsettings['mailaddresslog']) or!is_string($jobsettings['mailaddresslog']) or false === $pos=strpos($jobsettings['mailaddresslog'],'@') or false === strpos($jobsettings['mailaddresslog'],'.',$pos))
		$jobsettings['mailaddresslog']=get_option('admin_email');

	if (!isset($jobsettings['mailerroronly']) or !is_bool($jobsettings['mailerroronly']))
		$jobsettings['mailerroronly']=true;
	
	//old tables for backup (exclude)
	if (isset($jobsettings['dbexclude'])) {
		if (is_array($jobsettings['dbexclude'])) {
			$jobsettings['dbtables']=array();
			$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');
			foreach ($tables as $table) {
				if (!in_array($table,$jobsettings['dbexclude']))
					$jobsettings['dbtables'][]=$table;
			}
		}
		unset($jobsettings['dbexclude']);
	}
	
	//Tables to backup
	if (!isset($jobsettings['dbtables']) or !is_array($jobsettings['dbtables'])) {
		$jobsettings['dbtables']=array();
		$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');
		foreach ($tables as $table) {
			if (substr($table,0,strlen($wpdb->prefix))==$wpdb->prefix)
				$jobsettings['dbtables'][]=$table;
		}
	}
	sort($jobsettings['dbtables']);
	
	if (!isset($jobsettings['dbshortinsert']) or !is_bool($jobsettings['dbshortinsert']))
		$jobsettings['dbshortinsert']=false;

	if (!isset($jobsettings['maintenance']) or !is_bool($jobsettings['maintenance']))
		$jobsettings['maintenance']=false;

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

	if (!isset($jobsettings['backuproot']) or !is_bool($jobsettings['backuproot']))
		$jobsettings['backuproot']=true;

	if (!isset($jobsettings['backupcontent']) or !is_bool($jobsettings['backupcontent']))
		$jobsettings['backupcontent']=true;

	if (!isset($jobsettings['backupplugins']) or !is_bool($jobsettings['backupplugins']))
		$jobsettings['backupplugins']=true;

	if (!isset($jobsettings['backupthemes']) or !is_bool($jobsettings['backupthemes']))
		$jobsettings['backupthemes']=true;

	if (!isset($jobsettings['backupuploads']) or !is_bool($jobsettings['backupuploads']))
		$jobsettings['backupuploads']=true;

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

	$fileformarts=array('.zip','.tar.gz','.tar.bz2','.tar');
	if (!isset($jobsettings['fileformart']) or !in_array($jobsettings['fileformart'],$fileformarts))
		$jobsettings['fileformart']='.zip';
	
	if (!isset($jobsettings['fileprefix']) or !is_string($jobsettings['fileprefix']))
		$jobsettings['fileprefix']='backwpup_'.$jobid.'_';
	
	if (!isset($jobsettings['mailefilesize']) or !is_float($jobsettings['mailefilesize']))
		$jobsettings['mailefilesize']=0;

	if (!isset($jobsettings['backupdir']) or !is_dir($jobsettings['backupdir']))
		$jobsettings['backupdir']='';
	$jobsettings['backupdir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($jobsettings['backupdir']))));
	if ($jobsettings['backupdir']=='/')
		$jobsettings['backupdir']='';

	if (!isset($jobsettings['maxbackups']) or !is_int($jobsettings['maxbackups']))
		$jobsettings['maxbackups']=0;

	if (!isset($jobsettings['ftphost']) or !is_string($jobsettings['ftphost']))
		$jobsettings['ftphost']='';

	if (!isset($jobsettings['ftpuser']) or !is_string($jobsettings['ftpuser']))
		$jobsettings['ftpuser']='';

	if (!isset($jobsettings['ftppass']) or !is_string($jobsettings['ftppass']))
		$jobsettings['ftppass']='';

	if (!isset($jobsettings['ftpdir']) or !is_string($jobsettings['ftpdir']) or $jobsettings['ftpdir']=='/')
		$jobsettings['ftpdir']='';
	$jobsettings['ftpdir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($jobsettings['ftpdir']))));
	if (substr($jobsettings['ftpdir'],0,1)!='/')
		$jobsettings['ftpdir']='/'.$jobsettings['ftpdir'];

	if (!isset($jobsettings['ftpmaxbackups']) or !is_int($jobsettings['ftpmaxbackups']))
		$jobsettings['ftpmaxbackups']=0;
	
	if (!isset($jobsettings['ftppasv']) or !is_bool($jobsettings['ftppasv']))
		$jobsettings['ftppasv']=true;
	
	if (!isset($jobsettings['ftpssl']) or !is_bool($jobsettings['ftpssl']))
		$jobsettings['ftpssl']=false;

	if (!isset($jobsettings['awsAccessKey']) or !is_string($jobsettings['awsAccessKey']))
		$jobsettings['awsAccessKey']='';

	if (!isset($jobsettings['awsSecretKey']) or !is_string($jobsettings['awsSecretKey']))
		$jobsettings['awsSecretKey']='';

	if (!isset($jobsettings['awsrrs']) or !is_bool($jobsettings['awsrrs']))
		$jobsettings['awsrrs']=false;

	if (!isset($jobsettings['awsBucket']) or !is_string($jobsettings['awsBucket']))
		$jobsettings['awsBucket']='';

	if (!isset($jobsettings['awsdir']) or !is_string($jobsettings['awsdir']) or $jobsettings['awsdir']=='/')
		$jobsettings['awsdir']='';
	$jobsettings['awsdir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($jobsettings['awsdir']))));
	if (substr($jobsettings['awsdir'],0,1)=='/')
		$jobsettings['awsdir']=substr($jobsettings['awsdir'],1);

	if (!isset($jobsettings['awsmaxbackups']) or !is_int($jobsettings['awsmaxbackups']))
		$jobsettings['awsmaxbackups']=0;
		
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

	if (!isset($jobsettings['msazuremaxbackups']) or !is_int($jobsettings['msazuremaxbackups']))
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

	if (!isset($jobsettings['rscmaxbackups']) or !is_int($jobsettings['rscmaxbackups']))
		$jobsettings['rscmaxbackups']=0;
		
	if (isset($jobsettings['dropemail']))
		unset($jobsettings['dropemail']);
		
	if (isset($jobsettings['dropepass']))
		unset($jobsettings['dropepass']);
		
	if (!isset($jobsettings['dropetoken']) or !is_string($jobsettings['dropetoken']))
		$jobsettings['dropetoken']='';
	
	if (!isset($jobsettings['dropesecret']) or !is_string($jobsettings['dropesecret']))
		$jobsettings['dropesecret']='';

	if (!isset($jobsettings['dropedir']) or !is_string($jobsettings['dropedir']) or $jobsettings['dropedir']=='/')
		$jobsettings['dropedir']='';
	$jobsettings['dropedir']=trailingslashit(str_replace('//','/',str_replace('\\','/',trim($jobsettings['dropedir']))));
	if (substr($jobsettings['dropedir'],0,1)=='/')
		$jobsettings['dropedir']=substr($jobsettings['dropedir'],1);
	
	if (!isset($jobsettings['dropemaxbackups']) or !is_int($jobsettings['dropemaxbackups']))
		$jobsettings['dropemaxbackups']=0;	
		
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
	
	if (!isset($jobsettings['sugarmaxbackups']) or !is_int($jobsettings['sugarmaxbackups']))
		$jobsettings['sugarmaxbackups']=0;			
			
	if (!isset($jobsettings['mailaddress']) or !is_string($jobsettings['mailaddress']) or false === $pos=strpos($jobsettings['mailaddress'],'@') or false === strpos($jobsettings['mailaddress'],'.',$pos))
		$jobsettings['mailaddress']='';

	return $jobsettings;
}	


//ajax/normal get backup files and infos
function backwpup_get_backup_files($onlyjobid='') {
	$jobs=(array)get_option('backwpup_jobs'); //Load jobs
	$dests=explode(',',strtoupper(BACKWPUP_DESTS));
	$filecounter=0;
	$files=array();
	$donefolders=array();
	if (function_exists('curl_exec')) {
		set_include_path(get_include_path().PATH_SEPARATOR.dirname(__FILE__).'/libs');
		if (!class_exists('Microsoft_WindowsAzure_Storage_Blob'))
			require_once 'Microsoft/WindowsAzure/Storage/Blob.php';
		if (!class_exists('CFRuntime'))
			require_once(dirname(__FILE__).'/libs/aws/sdk.class.php');
		if (!class_exists('CF_Authentication'))
			require_once(dirname(__FILE__).'/libs/rackspace/cloudfiles.php');
		if (!class_exists('Dropbox') and function_exists('json_decode'))
			require_once(dirname(__FILE__).'/libs/dropbox/dropbox.php');
		if (!class_exists('SugarSync'))
			require_once (dirname(__FILE__).'/libs/sugarsync.php');
	}

	foreach ($jobs as $jobid => $jobvalue) { //go job by job
		if (!empty($onlyjobid) and $jobid!=$onlyjobid)
			continue;
		$jobvalue=backwpup_check_job_vars($jobvalue,$jobid); //Check job values
		$todo=explode('+',$jobvalue['type']); //only for backup jobs
		if (!in_array('FILE',$todo) and !in_array('DB',$todo) and !in_array('WPEXP',$todo))
			continue;

		//Get files/filinfo in backup folder
		if (!empty($jobvalue['backupdir']) and !in_array($jobvalue['backupdir'],$donefolders)) {
			if ( $dir = @opendir( $jobvalue['backupdir'] ) ) {
				while (($file = readdir( $dir ) ) !== false ) {
					if (substr($file,0,1)=='.' or !(strtolower(substr($file,-4))=='.zip' or strtolower(substr($file,-4))=='.tar'  or strtolower(substr($file,-7))=='.tar.gz'  or strtolower(substr($file,-8))=='.tar.bz2'))
						continue;
					if (is_file($jobvalue['backupdir'].$file)) {
						$files[$filecounter]['type']='FOLDER';
						$files[$filecounter]['jobid']=$jobid;
						$files[$filecounter]['file']=$jobvalue['backupdir'].$file;
						$files[$filecounter]['filename']=$file;
						$files[$filecounter]['downloadurl']='admin.php?page=BackWPup&subpage=backups&action=download&file='.$jobvalue['backupdir'].$file;
						$files[$filecounter]['filesize']=filesize($jobvalue['backupdir'].$file);
						$files[$filecounter]['time']=filemtime($jobvalue['backupdir'].$file);
						$filecounter++;
					}
				}
				closedir( $dir );
				$donefolders[]=$jobvalue['backupdir'];
			}
		}
		//Get files/filinfo from Dropbox
		if (class_exists('Dropbox') and in_array('DROPBOX',$dests) and !in_array($jobvalue['dropetoken'].'|'.$jobvalue['dropesecret'].'|'.$jobvalue['dropedir'],$donefolders)) {
			if (!empty($jobvalue['dropetoken']) and !empty($jobvalue['dropesecret'])) {
				try {
					$dropbox = new Dropbox(BACKWPUP_DROPBOX_APP_KEY, BACKWPUP_DROPBOX_APP_SECRET);
					$dropbox->setOAuthTokens($jobvalue['dropetoken'],$jobvalue['dropesecret']);
					$contents = $dropbox->metadata($jobvalue['dropedir']);
					if (is_array($contents)) {
						foreach ($contents['contents'] as $object) {
							if ($object['is_dir']!=true and (strtolower(substr($object['path'],-4))=='.zip' or strtolower(substr($object['path'],-4))=='.tar'  or strtolower(substr($object['path'],-7))=='.tar.gz'  or strtolower(substr($object['path'],-8))=='.tar.bz2')) {
								$files[$filecounter]['type']='DROPBOX';
								$files[$filecounter]['jobid']=$jobid;
								$files[$filecounter]['file']=$object['path'];
								$files[$filecounter]['filename']=basename($object['path']);
								$files[$filecounter]['downloadurl']='admin.php?page=BackWPup&subpage=backups&action=downloaddropbox&file='.$object['path'].'&jobid='.$jobid;
								$files[$filecounter]['filesize']=$object['bytes'];
								$files[$filecounter]['time']=strtotime($object['modified']);
								$filecounter++;
							}
						}
					}
					$donefolders[]=$jobvalue['dropetoken'].'|'.$jobvalue['dropesecret'].'|'.$jobvalue['dropedir'];
				} catch (Exception $e) {
				}
			}
		}
		//Get files/filinfo from Sugarsync
		if (class_exists('SugarSync') and in_array('SUGARSYNC',$dests) and !in_array($jobvalue['sugaruser'].'|'.base64_decode($jobvalue['sugarpass']).'|'.$jobvalue['sugardir'],$donefolders)) {
			if (!empty($jobvalue['sugarpass']) and !empty($jobvalue['sugarpass'])) {
				try {
					$sugarsync = new SugarSync($jobvalue['sugaruser'],base64_decode($jobvalue['sugarpass']),BACKWPUP_SUGARSYNC_ACCESSKEY, BACKWPUP_SUGARSYNC_PRIVATEACCESSKEY);
					$sugarsync->chdir($jobvalue['sugardir'],$jobvalue['sugarroot']);
					$getfiles=$sugarsync->getcontents('file');
					if (is_object($getfiles)) {
						foreach ($getfiles->file as $getfile) {
							if (strtolower(substr($getfile->displayName,-4))=='.zip' or strtolower(substr($getfile->displayName,-4))=='.tar'  or strtolower(substr($getfile->displayName,-7))=='.tar.gz'  or strtolower(substr($getfile->displayName,-8))=='.tar.bz2') {
								$files[$filecounter]['type']='SUGARSYNC';
								$files[$filecounter]['jobid']=$jobid;
								$files[$filecounter]['file']= (string) $getfile->ref;
								$files[$filecounter]['filename']=utf8_decode((string) $getfile->displayName);
								$files[$filecounter]['downloadurl']='admin.php?page=BackWPup&subpage=backups&action=downloadsugarsync&file='.(string) $getfile->ref.'&jobid='.$jobid;
								$files[$filecounter]['filesize']=(int) $getfile->size;
								$files[$filecounter]['time']=strtotime((string) $getfile->lastModified);
								$filecounter++;
							}
						}
					}
					$donefolders[]=$jobvalue['sugaruser'].'|'.base64_decode($jobvalue['sugarpass']).'|'.$jobvalue['sugardir'];
				} catch (Exception $e) {
				}
			}
		}
		//Get files/filinfo from S3
		if (class_exists('AmazonS3') and in_array('S3',$dests) and !in_array($jobvalue['awsAccessKey'].'|'.$jobvalue['awsBucket'].'|'.$jobvalue['awsdir'],$donefolders)) {
			if (!empty($jobvalue['awsAccessKey']) and !empty($jobvalue['awsSecretKey']) and !empty($jobvalue['awsBucket'])) {
				try {
					$s3 = new AmazonS3($jobvalue['awsAccessKey'], $jobvalue['awsSecretKey']);
					if (($contents = $s3->list_objects($jobvalue['awsBucket'],array('prefix'=>$jobvalue['awsdir']))) !== false) {
						foreach ($contents->body->Contents as $object) {
							if (strtolower(substr($object->Key,-4))=='.zip' or strtolower(substr($object->Key,-4))=='.tar'  or strtolower(substr($object->Key,-7))=='.tar.gz'  or strtolower(substr($object->Key,-8))=='.tar.bz2') {
								$files[$filecounter]['type']='S3';
								$files[$filecounter]['jobid']=$jobid;
								$files[$filecounter]['file']=(string)$object->Key;
								$files[$filecounter]['filename']=basename($object->Key);
								$files[$filecounter]['downloadurl']='admin.php?page=BackWPup&subpage=backups&action=downloads3&file='.$object->Key.'&jobid='.$jobid;
								$files[$filecounter]['filesize']=(string)$object->Size;
								$files[$filecounter]['time']=strtotime($object->LastModified);
								$filecounter++;
							}
						}
					}
					$donefolders[]=$jobvalue['awsAccessKey'].'|'.$jobvalue['awsBucket'].'|'.$jobvalue['awsdir'];
				} catch (Exception $e) {
				}
			}
		}
		//Get files/filinfo from Microsoft Azure
		if (class_exists('Microsoft_WindowsAzure_Storage_Blob') and in_array('MSAZURE',$dests) and !in_array($jobvalue['msazureAccName'].'|'.$jobvalue['msazureKey'].'|'.$jobvalue['msazureContainer'].'|'.$jobvalue['msazuredir'],$donefolders)) {
			if (!empty($jobvalue['msazureHost']) and !empty($jobvalue['msazureAccName']) and !empty($jobvalue['msazureKey']) and !empty($jobvalue['msazureContainer'])) {
				try {
					$storageClient = new Microsoft_WindowsAzure_Storage_Blob($jobvalue['msazureHost'],$jobvalue['msazureAccName'],$jobvalue['msazureKey']);
					$blobs = $storageClient->listBlobs($jobvalue['msazureContainer'],$jobvalue['msazuredir']);
					if (is_array($blobs)) {
						foreach ($blobs as $blob) {
							if (strtolower(substr($blob->Name,-4))=='.zip' or strtolower(substr($blob->Name,-4))=='.tar'  or strtolower(substr($blob->Name,-7))=='.tar.gz'  or strtolower(substr($blob->Name,-8))=='.tar.bz2') {
								$files[$filecounter]['type']='MSAZURE';
								$files[$filecounter]['jobid']=$jobid;
								$files[$filecounter]['file']=$blob->Name;
								$files[$filecounter]['filename']=basename($blob->Name);
								$files[$filecounter]['downloadurl']='admin.php?page=BackWPup&subpage=backups&action=downloadmsazure&file='.$blob->Name.'&jobid='.$jobid;
								$files[$filecounter]['filesize']=$blob->size;
								$files[$filecounter]['time']=strtotime($blob->lastmodified);
								$filecounter++;
							}
						}
					}
					$donefolders[]=$jobvalue['msazureAccName'].'|'.$jobvalue['msazureKey'].'|'.$jobvalue['msazureContainer'].'|'.$jobvalue['msazuredir'];		
				} catch (Exception $e) {
				}
			}
		}		
		//Get files/filinfo from RSC
		if (class_exists('CF_Authentication') and in_array('RSC',$dests) and !in_array($jobvalue['rscUsername'].'|'.$jobvalue['rscContainer'].'|'.$jobvalue['rscdir'],$donefolders)) {
			if (!empty($jobvalue['rscUsername']) and !empty($jobvalue['rscAPIKey']) and !empty($jobvalue['rscContainer'])) {
				try {
					$auth = new CF_Authentication($jobvalue['rscUsername'], $jobvalue['rscAPIKey']);
					$auth->ssl_use_cabundle();
					if ($auth->authenticate()) {
						$conn = new CF_Connection($auth);
						$conn->ssl_use_cabundle();
						$backwpupcontainer = $conn->get_container($jobvalue['rscContainer']);
						$contents = $backwpupcontainer->get_objects(0,NULL,NULL,$jobvalue['rscdir']);
						foreach ($contents as $object) {
							if (strtolower(substr($object->name,-4))=='.zip' or strtolower(substr($object->name,-4))=='.tar'  or strtolower(substr($object->name,-7))=='.tar.gz'  or strtolower(substr($object->name,-8))=='.tar.bz2') {
								$files[$filecounter]['type']='RSC';
								$files[$filecounter]['jobid']=$jobid;
								$files[$filecounter]['file']=$object->name;
								$files[$filecounter]['filename']=basename($object->name);
								$files[$filecounter]['downloadurl']='admin.php?page=BackWPup&subpage=backups&action=downloadrsc&file='.$object->name.'&jobid='.$jobid;
								$files[$filecounter]['filesize']=$object->content_length;
								$files[$filecounter]['time']=$object->last_modified;
								$filecounter++;
							}
						}
						$donefolders[]=$jobvalue['rscUsername'].'|'.$jobvalue['rscContainer'].'|'.$jobvalue['rscdir'];
					}
				} catch (Exception $e) {
				}
			}
		}
		//Get files/filinfo from FTP
		if (!empty($jobvalue['ftphost']) and in_array('FTP',$dests) and function_exists('ftp_connect') and !empty($jobvalue['ftpuser']) and !empty($jobvalue['ftppass']) and !in_array($jobvalue['ftphost'].'|'.$jobvalue['ftpuser'].'|'.$jobvalue['ftpdir'],$donefolders)) {
			$ftpport=21;
			$ftphost=$jobvalue['ftphost'];
			if (false !== strpos($jobvalue['ftphost'],':')) //look for port
				list($ftphost,$ftpport)=explode(':',$jobvalue,2);

			if (function_exists('ftp_ssl_connect') and $jobvalue['ftpssl']) { //make SSL FTP connection
				$ftp_conn_id = ftp_ssl_connect($ftphost,$ftpport,10);
			} elseif (!$jobvalue['ftpssl']) { //make normal FTP conection if SSL not work
				$ftp_conn_id = ftp_connect($ftphost,$ftpport,10);
			}
			$loginok=false;
			if ($ftp_conn_id) {
				//FTP Login
				if (@ftp_login($ftp_conn_id, $jobvalue['ftpuser'], base64_decode($jobvalue['ftppass']))) {
					$loginok=true;
				} else { //if PHP ftp login don't work use raw login
					ftp_raw($ftp_conn_id,'USER '.$jobvalue['ftpuser']);
					$return=ftp_raw($ftp_conn_id,'PASS '.base64_decode($jobvalue['ftppass']));
					if (substr(trim($return[0]),0,3)<=400)
						$loginok=true;
				}
			}
			if ($loginok) {
				ftp_pasv($ftp_conn_id, $jobvalue['ftppasv']);
				if ($ftpfilelist=ftp_nlist($ftp_conn_id, $jobvalue['ftpdir'])) {
					foreach($ftpfilelist as $ftpfiles) {
						if (substr(basename($ftpfiles),0,1)=='.' or !(strtolower(substr($ftpfiles,-4))=='.zip' or strtolower(substr($ftpfiles,-4))=='.tar'  or strtolower(substr($ftpfiles,-7))=='.tar.gz'  or strtolower(substr($ftpfiles,-8))=='.tar.bz2'))
							continue;
						$files[$filecounter]['type']='FTP';
						$files[$filecounter]['jobid']=$jobid;
						$files[$filecounter]['file']=$ftpfiles;
						$files[$filecounter]['filename']=basename($ftpfiles);
						$files[$filecounter]['downloadurl']="ftp://".rawurlencode($jobvalue['ftpuser']).":".rawurlencode(base64_decode($jobvalue['ftppass']))."@".$jobvalue['ftphost'].rawurlencode($ftpfiles);
						$files[$filecounter]['filesize']=ftp_size($ftp_conn_id,$ftpfiles);
						$files[$filecounter]['time']=ftp_mdtm($ftp_conn_id,$ftpfiles);
						$filecounter++;
					}
				}
			}
			$donefolders[]=$jobvalue['ftphost'].'|'.$jobvalue['ftpuser'].'|'.$jobvalue['ftpdir'];
		}
	}
	//Sort list
	$tmp = Array();
	foreach($files as &$ma)
		$tmp[] = &$ma["time"];
	array_multisort($tmp, SORT_DESC, $files);
	return $files;
}

//ajax/normal get buckests select box
function backwpup_get_aws_buckets($args='') {
	if (is_array($args)) {
		extract($args);
		$ajax=false;
	} else {
		$awsAccessKey=$_POST['awsAccessKey'];
		$awsSecretKey=$_POST['awsSecretKey'];
		$awsselected=$_POST['awsselected'];
		$ajax=true;
	}
	if (!class_exists('CFRuntime'))
		require_once(dirname(__FILE__).'/libs/aws/sdk.class.php');
	if (empty($awsAccessKey)) {
		echo '<span id="awsBucket" style="color:red;">'.__('Missing Access Key ID!','backwpup').'</span>';
		if ($ajax)
			die();
		else
			return;
	}
	if (empty($awsSecretKey)) {
		echo '<span id="awsBucket" style="color:red;">'.__('Missing Secret Access Key!','backwpup').'</span>';
		if ($ajax)
			die();
		else
			return;
	}
	try {
		$s3 = new AmazonS3($awsAccessKey, $awsSecretKey);
		$buckets=$s3->list_buckets();
	} catch (Exception $e) {
		echo '<span id="awsBucket" style="color:red;">'.__($e->getMessage(),'backwpup').'</span>';
		if ($ajax)
			die();
		else
			return;
	}
	if ($buckets->status<200 and $buckets->status>=300) {
		echo '<span id="awsBucket" style="color:red;">'.__('S3 Message:','backwpup').' '.$buckets->status.': '.$buckets->body->Message.'</span>';
		if ($ajax)
			die();
		else
			return;
	}
	if (empty($buckets->body->Buckets->Bucket)) {
		echo '<span id="awsBucket" style="color:red;">'.__('No Buckets found!','backwpup').'</span>';
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

//ajax/normal get Container for RSC select box
function backwpup_get_rsc_container($args='') {
	if (is_array($args)) {
		extract($args);
		$ajax=false;
	} else {
		$rscUsername=$_POST['rscUsername'];
		$rscAPIKey=$_POST['rscAPIKey'];
		$rscselected=$_POST['rscselected'];
		$ajax=true;
	}
	if (!class_exists('CF_Authentication'))
		require_once(dirname(__FILE__).'/libs/rackspace/cloudfiles.php');

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
		echo '<span id="rscContainer" style="color:red;">'.__($e->getMessage(),'backwpup').'</span>';
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
		$msazureHost=$_POST['msazureHost'];
		$msazureAccName=$_POST['msazureAccName'];
		$msazureKey=$_POST['msazureKey'];
		$msazureselected=$_POST['msazureselected'];
		$ajax=true;
	}
	if (!class_exists('Microsoft_WindowsAzure_Storage_Blob')) {
		set_include_path(get_include_path().PATH_SEPARATOR.dirname(__FILE__).'/libs');
		require_once 'Microsoft/WindowsAzure/Storage/Blob.php';
	}
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
		$sugaruser=$_POST['sugaruser'];
		$sugarpass=$_POST['sugarpass'];
		$sugarrootselected=$_POST['sugarrootselected'];
		$ajax=true;
	}
	
	if (!class_exists('SugarSync'))
		require_once(dirname(__FILE__).'/libs/sugarsync.php');

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
		$sugarsync = new SugarSync($sugaruser,$sugarpass,BACKWPUP_SUGARSYNC_ACCESSKEY, BACKWPUP_SUGARSYNC_PRIVATEACCESSKEY);
		$user=$sugarsync->user();
		$syncfolders=$sugarsync->get($user->syncfolders);
	} catch (Exception $e) {
		echo '<span id="sugarroot" style="color:red;">'.__($e->getMessage(),'backwpup').'</span>';
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
?>