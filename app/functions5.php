<?PHP
// don't load directly
if ( !defined('ABSPATH') )
	die('-1');

	//Checking,upgrade and default job setting
	function backwpup_check_job_vars($jobsettings,$jobid='') {
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
			$jobsettings['cronnextrun']=backwpup_cron_next($jobs[$jobid]['cron']);;
			
		if (!is_string($jobsettings['mailaddresslog']) or false === $pos=strpos($jobsettings['mailaddresslog'],'@') or false === strpos($jobsettings['mailaddresslog'],'.',$pos))
			$jobsettings['mailaddresslog']=get_option('admin_email');

		if (!isset($jobsettings['mailerroronly']) or !is_bool($jobsettings['mailerroronly']))
			$jobsettings['mailerroronly']=true;

		if (!isset($jobsettings['dbexclude']) or !is_array($jobsettings['dbexclude'])) {
			$jobsettings['dbexclude']=array();
			$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');
			foreach ($tables as $table) {
				if (substr($table,0,strlen($wpdb->prefix))!=$wpdb->prefix)
					$jobsettings['dbexclude'][]=$table;
			}
		}
		$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');
		foreach($jobsettings['dbexclude'] as $key => $value) {
			if (empty($jobsettings['dbexclude'][$key]) or !in_array($value,$tables))
				unset($jobsettings['dbexclude'][$key]);
		}
		sort($jobsettings['dbexclude']);

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

		if (!isset($jobsettings['backupdir']) or (!is_dir($jobsettings['backupdir']) and !empty($jobsettings['backupdir']))) {
			$rand = substr( md5( md5( SECURE_AUTH_KEY ) ), -5 );
			$jobsettings['backupdir']=str_replace('\\','/',trailingslashit(WP_CONTENT_DIR)).'backwpup-'.$rand.'/';
		}
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

		if (!isset($jobsettings['awsAccessKey']) or !is_string($jobsettings['awsAccessKey']))
			$jobsettings['awsAccessKey']='';

		if (!isset($jobsettings['awsSecretKey']) or !is_string($jobsettings['awsSecretKey']))
			$jobsettings['awsSecretKey']='';

		if (!isset($jobsettings['awsSSL']) or !is_bool($jobsettings['awsSSL']))
			$jobsettings['awsSSL']=true;

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

		if (!is_string($jobsettings['mailaddress']) or false === $pos=strpos($jobsettings['mailaddress'],'@') or false === strpos($jobsettings['mailaddress'],'.',$pos))
			$jobsettings['mailaddress']='';

		return $jobsettings;
	}	
	
	
	//ajax/normal get backup files and infos
	function backwpup_get_backup_files() {
		$jobs=(array)get_option('backwpup_jobs'); //Load jobs
		$filecounter=0;
		$files=array();
		$donefolders=array();
		if (extension_loaded('curl') or @dl(PHP_SHLIB_SUFFIX == 'so' ? 'curl.so' : 'php_curl.dll')) {
			if (!class_exists('S3'))
				require_once(dirname(__FILE__).'/libs/S3.php');
			if (!class_exists('CF_Authentication'))
				require_once(dirname(__FILE__).'/libs/rackspace/cloudfiles.php');
		}

		foreach ($jobs as $jobid => $jobvalue) { //go job by job
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
							$files[$filecounter]['downloadurl']=wp_nonce_url('admin.php?page=BackWPup&subpage=backups&action=download&file='.$jobvalue['backupdir'].$file, 'download-backup_'.$file);
							$files[$filecounter]['filesize']=filesize($jobvalue['backupdir'].$file);
							$files[$filecounter]['time']=filemtime($jobvalue['backupdir'].$file);
							$filecounter++;
						}
					}
					closedir( $dir );
					$donefolders[]=$jobvalue['backupdir'];
				}
			}
			//Get files/filinfo from S3
			if (class_exists('S3') and !in_array($jobvalue['awsAccessKey'].'|'.$jobvalue['awsBucket'].'|'.$jobvalue['awsdir'],$donefolders)) {
				if (!empty($jobvalue['awsAccessKey']) and !empty($jobvalue['awsSecretKey']) and !empty($jobvalue['awsBucket'])) {
					$s3 = new S3($jobvalue['awsAccessKey'], $jobvalue['awsSecretKey'], $jobvalue['awsSSL']);
					if (($contents = $s3->getBucket($jobvalue['awsBucket'],$jobvalue['awsdir'])) !== false) {
						foreach ($contents as $object) {
							if (strtolower(substr($object['name'],-4))=='.zip' or strtolower(substr($object['name'],-4))=='.tar'  or strtolower(substr($object['name'],-7))=='.tar.gz'  or strtolower(substr($object['name'],-8))=='.tar.bz2') {
								$files[$filecounter]['type']='S3';
								$files[$filecounter]['jobid']=$jobid;
								$files[$filecounter]['file']=$object['name'];
								$files[$filecounter]['filename']=basename($object['name']);
								$files[$filecounter]['downloadurl']=wp_nonce_url('admin.php?page=BackWPup&subpage=backups&action=downloads3&file='.$object['name'].'&jobid='.$jobid, 'downloads3-backup_'.$object['name']);
								$files[$filecounter]['filesize']=$object['size'];
								$files[$filecounter]['time']=$object['time'];
								$filecounter++;
							}
						}
					}
					$donefolders[]=$jobvalue['awsAccessKey'].'|'.$jobvalue['awsBucket'].'|'.$jobvalue['awsdir'];
				}
			}
			//Get files/filinfo from RSC
			if (class_exists('CF_Authentication') and !in_array($jobvalue['rscUsername'].'|'.$jobvalue['rscContainer'].'|'.$jobvalue['rscdir'],$donefolders)) {
				if (!empty($jobvalue['rscUsername']) and !empty($jobvalue['rscAPIKey']) and !empty($jobvalue['rscContainer'])) {
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
								$files[$filecounter]['downloadurl']=wp_nonce_url('admin.php?page=BackWPup&subpage=backups&action=downloadrsc&file='.$object->name.'&jobid='.$jobid, 'downloadrsc-backup_'.$object->name);
								$files[$filecounter]['filesize']=$object->content_length;
								$files[$filecounter]['time']=$object->last_modified;
								$filecounter++;
							}
						}
						$donefolders[]=$jobvalue['rscUsername'].'|'.$jobvalue['rscContainer'].'|'.$jobvalue['rscdir'];
					}
				}
			}
			//Get files/filinfo from FTP
			if (!empty($jobvalue['ftphost']) and !empty($jobvalue['ftpuser']) and !empty($jobvalue['ftppass']) and !in_array($jobvalue['ftphost'].'|'.$jobvalue['ftpuser'].'|'.$jobvalue['ftpdir'],$donefolders)) {
				$ftpport=21;
				$ftphost=$jobvalue['ftphost'];
				if (false !== strpos($jobvalue['ftphost'],':')) //look for port
					list($ftphost,$ftpport)=explode(':',$jobvalue,2);

				$SSL=false;
				if (function_exists('ftp_ssl_connect')) { //make SSL FTP connection
					$ftp_conn_id = ftp_ssl_connect($ftphost,$ftpport,10);
					if ($ftp_conn_id)
						$SSL=true;
				}
				if (!$ftp_conn_id) { //make normal FTP conection if SSL not work
					$ftp_conn_id = ftp_connect($ftphost,$ftpport,10);
				}
				if ($ftp_conn_id) {
					//FTP Login
					$loginok=false;
					if (@ftp_login($ftp_conn_id, $jobvalue['ftpuser'], base64_decode($jobvalue['ftppass']))) {
						$loginok=true;
					} else { //if PHP ftp login don't work use raw login
						ftp_raw($ftp_conn_id,'USER '.$jobvalue['ftpuser']);
						ftp_raw($ftp_conn_id,'PASS '.base64_decode($jobvalue['ftppass']));
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
							$files[$filecounter]['downloadurl']="ftp://".$jobvalue['ftpuser'].":".base64_decode($jobvalue['ftppass'])."@".$jobvalue['ftphost'].$ftpfiles;
							$files[$filecounter]['filesize']=ftp_size($ftp_conn_id,$ftpfiles);
							if ('backwpup_log_' == substr(basename($ftpfiles),0,strlen('backwpup_log_'))) {
								$filnameparts=explode('_',substr(basename($ftpfiles),0,strpos(basename($ftpfiles),'.')));
								$files[$filecounter]['time']=strtotime($filnameparts[2].' '.str_replace('-',':',$filnameparts[3]));
							}
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
		if (!class_exists('S3'))
			require_once(dirname(__FILE__).'/libs/S3.php');
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
		$s3 = new S3($awsAccessKey, $awsSecretKey, false);
		$buckets=@$s3->listBuckets();
		if (!is_array($buckets)) {
			echo '<span id="awsBucket" style="color:red;">'.__('No Buckets found! Or wrong Keys!','backwpup').'</span>';
			if ($ajax)
				die();
			else
				return;
		}
		echo '<select name="awsBucket" id="awsBucket">';
		foreach ($buckets as $bucket) {
			echo "<option ".selected(strtolower($awsselected),strtolower($bucket),false).">".$bucket."</option>";
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
		$auth = new CF_Authentication($rscUsername, $rscAPIKey);

		try {
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
?>