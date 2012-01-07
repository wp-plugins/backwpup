<?PHP
if (!defined('ABSPATH'))
	die();

function backwpup_upgrade() {
	global $wpdb,$backwpup_cfg;

	//Create DB table if not exists
	$query ='CREATE TABLE IF NOT EXISTS `'.$wpdb->prefix.'backwpup` (
			`main_name` varchar(64) NOT NULL,
			`name` varchar(64) NOT NULL,
			`value` longtext NOT NULL,
			KEY `main_name` (`main_name`),
			KEY `name` (`name`)
			) ';
	if(!empty($wpdb->charset))
		$query .= 'DEFAULT CHARACTER SET '.$wpdb->charset;
	if(!empty($wpdb->collate))
		$query .= ' COLLATE '.$wpdb->collate;
	$wpdb->query($query);

	//Put old cfg to DB if exists
	$cfg=get_option('backwpup');
	if (!empty($cfg)) {
		//if old value switch it to new
		if (!empty($cfg['dirtemp']))
			$cfg['tempfolder']=$cfg['dirtemp'];
		if (!empty($cfg['dirlogs']))
			$cfg['logfolder']=$cfg['dirlogs'];
		if (!empty($cfg['sugarpass']))
			$cfg['httpauthpassword']=backwpup_encrypt(base64_decode($cfg['httpauthpassword']));
		if (!empty($cfg['apicronservice'])) {
			$wpdb->query("UPDATE ".$wpdb->prefix."backwpup SET value='backwpupapi' WHERE name='activetype' AND main_name LIKE 'job_%' AND value='wpcron'");
			$backwpup_cfg['apicronservicekey']=wp_create_nonce('BackWPupJobRunAPI');
			backwpup_update_option('cfg','apicronservicekey', $backwpup_cfg['apicronservicekey']);
		}
		// delete old not needed vars
		unset($cfg['mailmethod'],$cfg['mailsendmail'],$cfg['mailhost'],$cfg['mailhostport'],$cfg['mailsecure'],$cfg['mailuser'],$cfg['mailpass'],$cfg['dirtemp'],$cfg['dirlogs'],$cfg['logfilelist'],$cfg['jobscriptruntime'],$cfg['jobscriptruntimelong'],$cfg['last_activate'],$cfg['disablewpcron'],$cfg['phpzip'],$cfg['apicronservice']);
		if (is_array($cfg)) {
			foreach ($cfg as $cfgname => $cfgvalue)
				backwpup_update_option('cfg',$cfgname,$cfgvalue);
		}
		delete_option('backwpup');
	}

	//Put old jobs to DB if exists
	$jobs=get_option('backwpup_jobs');
	if (!empty($jobs) and is_array($jobs)) {
		foreach ($jobs as $jobid => $jobvalue) {
			if (empty($jobvalue['jobid']))
				$jobvalue['jobid']=$jobid;
			if (!empty($jobvalue['ftppass']))
				$jobvalue['ftppass']=backwpup_encrypt(base64_decode($jobvalue['ftppass']));
			if (!empty($jobvalue['sugarpass']))
				$jobvalue['sugarpass']=backwpup_encrypt(base64_decode($jobvalue['sugarpass']));
			if (empty($jobvalue['activated']))
				$jobvalue['activetype']='';
			else
				$jobvalue['activetype']='wpcron';
			$jobvalue['type']=explode('+',$jobvalue['type']); //save as array
			unset($jobvalue['scheduleintervaltype'],$jobvalue['scheduleintervalteimes'],$jobvalue['scheduleinterval'],$jobvalue['dropemail'],$jobvalue['dropepass'],$jobvalue['dropesignmethod'],$jobvalue['dbtables']);
			foreach ($jobvalue as $jobvaluename => $jobvaluevalue) {
				backwpup_update_option('job_'.$jobvalue['jobid'],$jobvaluename,$jobvaluevalue);
			}
		}
		delete_option('backwpup_jobs');
	}

	//cleanup database
	$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main_name='job_'");
	$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main_name='temp'");
	$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main_name='api'");
	$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main_name='working'");

	//remove old schedule
	wp_clear_scheduled_hook('backwpup_cron');

	//make new schedule
	wp_schedule_event(time(), 'backwpup', 'backwpup_cron');

	//check cfg
	if (empty($backwpup_cfg['mailsndemail'])) backwpup_update_option('cfg','mailsndemail',sanitize_email(get_bloginfo( 'admin_email' )));
	if (empty($backwpup_cfg['mailsndname'])) backwpup_update_option('cfg','mailsndname','BackWPup '.get_bloginfo('name'));
	if (!isset($backwpup_cfg['showadminbar'])) backwpup_update_option('cfg','showadminbar',true);
	if (!isset($backwpup_cfg['jobstepretry']) or !is_numeric($backwpup_cfg['jobstepretry']) or 100<$backwpup_cfg['jobstepretry'] or empty($backwpup_cfg['jobstepretry']))  backwpup_update_option('cfg','jobstepretry',3);
	if (!isset($backwpup_cfg['jobscriptretry']) or!is_numeric($backwpup_cfg['jobscriptretry']) or 100<$backwpup_cfg['jobscriptretry'] or empty($backwpup_cfg['jobscriptretry'])) backwpup_update_option('cfg','jobscriptretry',5);
	if (empty($backwpup_cfg['maxlogs']) or !is_numeric($backwpup_cfg['maxlogs'])) backwpup_update_option('cfg','maxlogs',50);
	if (!function_exists('gzopen') or !isset($backwpup_cfg['gzlogs'])) backwpup_update_option('cfg','gzlogs',false);
	if (!isset($backwpup_cfg['logfolder']) or empty($backwpup_cfg['logfolder']) or !is_dir($backwpup_cfg['logfolder'])) {
		$rand = substr( md5( md5( SECURE_AUTH_KEY ) ), -5 );
		backwpup_update_option('cfg','logfolder',str_replace('\\','/',trailingslashit(WP_CONTENT_DIR)).'backwpup-'.$rand.'-logs/');
	}
	if (!isset($backwpup_cfg['httpauthuser'])) backwpup_update_option('cfg','httpauthuser','');
	if (!isset($backwpup_cfg['httpauthpassword'])) backwpup_update_option('cfg','httpauthpassword','');
	if (!isset($backwpup_cfg['jobrunauthkey'])) backwpup_update_option('cfg','jobrunauthkey', '');
	if (!isset($backwpup_cfg['apicronservicekey'])) backwpup_update_option('cfg','apicronservicekey','');
	if (!isset($backwpup_cfg['jobrunmaxexectime']) or !is_numeric($backwpup_cfg['jobrunmaxexectime'])) backwpup_update_option('cfg','jobrunmaxexectime',0);
	if (empty($backwpup_cfg['tempfolder'])) {
		if (defined('WP_TEMP_DIR'))
			$tempfolder=trim(WP_TEMP_DIR);
		if (empty($tempfolder) or !backwpup_check_open_basedir($tempfolder) or !@is_writable($tempfolder) or !@is_dir($tempfolder))
			$tempfolder=sys_get_temp_dir();									//normal temp dir
		if (empty($tempfolder) or !backwpup_check_open_basedir($tempfolder) or !@is_writable($tempfolder) or !@is_dir($tempfolder))
			$tempfolder=ini_get('upload_tmp_dir');							//if sys_get_temp_dir not work
		if (empty($tempfolder) or !backwpup_check_open_basedir($tempfolder) or !@is_writable($tempfolder) or !@is_dir($tempfolder))
			$tempfolder=WP_CONTENT_DIR.'/';
		if (empty($tempfolder) or !backwpup_check_open_basedir($tempfolder) or !@is_writable($tempfolder) or !@is_dir($tempfolder))
			$tempfolder=get_temp_dir();
		backwpup_update_option('cfg','tempfolder',trailingslashit(str_replace('\\','/',realpath($tempfolder))));
	}

	//update version
	backwpup_update_option('cfg','dbversion',BACKWPUP_VERSION);

	//load cfg again.
	$cfgs=$wpdb->get_results("SELECT name,value FROM `".$wpdb->prefix."backwpup` WHERE `main_name`='cfg'");
	foreach ($cfgs as $cfg)
		$backwpup_cfg[$cfg->name]=maybe_unserialize($cfg->value);
}
?>