<?php
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function backwpup_upgrade() {
	global $wpdb;
	//Set table collate
	if ( ! empty($wpdb->charset) )
		$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
	if ( ! empty($wpdb->collate) )
		$charset_collate .= " COLLATE $wpdb->collate";
	//Create DB table if not exists
	$query ="CREATE TABLE ".$wpdb->prefix."backwpup (
			id bigint(20) unsigned NOT NULL auto_increment,
			main varchar(64) NOT NULL default '',
			name varchar(64) NOT NULL default '',
			value longtext NOT NULL,
			PRIMARY KEY (id),
			KEY main (main),
			KEY name (name)
			) $charset_collate;";
	//Update table
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($query);

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
			$wpdb->query("UPDATE ".$wpdb->prefix."backwpup SET value='backwpupapi' WHERE name='activetype' AND main LIKE 'job_%' AND value='wpcron'");
			backwpup_update_option('cfg','apicronservicekey', wp_create_nonce('BackWPupJobRunAPI'));
		}
		// delete old not needed vars
		unset($cfg['mailmethod'],$cfg['mailsendmail'],$cfg['mailhost'],$cfg['mailhostport'],$cfg['mailsecure'],$cfg['mailuser'],$cfg['mailpass'],$cfg['dirtemp'],$cfg['dirlogs'],$cfg['logfilelist'],$cfg['jobscriptruntime'],$cfg['jobscriptruntimelong'],$cfg['last_activate'],$cfg['disablewpcron'],$cfg['phpzip'],$cfg['apicronservice']);
		//save in options
		foreach ($cfg as $cfgname => $cfgvalue)
			backwpup_update_option('cfg',$cfgname,$cfgvalue);
		delete_option('backwpup');
	}

	//Put old jobs to DB if exists
	$jobs=get_option('backwpup_jobs');
	if (!empty($jobs) and is_array($jobs)) {
		foreach ($jobs as $jobid => $jobvalue) {
			//convert old data
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
			if (isset($jobvalue['dbtables']) and is_array($jobvalue['dbtables'])) {
				$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');
				foreach ($tables as $table) {
					if (!in_array($table,$jobvalue['dbtables']))
						$jobvalue['dbexclude'][]=$table;
				}
			}
			if (!isset($jobvalue['cronselect']) and !isset($jobvalue['cron']))
				$jobvalue['cronselect']='basic';
			elseif (!isset($jobvalue['cronselect']) and isset($jobvalue['cron']))
				$jobvalue['cronselect']='advanced';
			if (!empty($jobvalue['ftphost']) and false !== strpos($jobvalue['ftphost'],':'))
				list($jobvalue['ftphost'],$jobvalue['ftphostport'])=explode(':',$jobvalue['ftphost'],2);
			$jobvalue['backuptype']='archive';
			$jobvalue['type']=explode('+',$jobvalue['type']); //save as array
			//delete not loger needed
			unset($jobvalue['dbtables'],$jobvalue['scheduleintervaltype'],$jobvalue['scheduleintervalteimes'],$jobvalue['scheduleinterval'],$jobvalue['dropemail'],$jobvalue['dropepass'],$jobvalue['dropesignmethod'],$jobvalue['dbtables']);
			//save in options
			foreach ($jobvalue as $jobvaluename => $jobvaluevalue)
				backwpup_update_option('job_'.$jobvalue['jobid'],$jobvaluename,$jobvaluevalue);
		}
		delete_option('backwpup_jobs');
	}

	//cleanup database
	$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main='job_'");
	$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main='temp'");
	$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main='working'");
	//remove old schedule
	wp_clear_scheduled_hook('backwpup_cron');
	//make new schedule
	wp_schedule_event(time(), 'backwpup', 'backwpup_cron');
	//update version
	backwpup_update_option('cfg','dbversion',BACKWPUP_VERSION);
}