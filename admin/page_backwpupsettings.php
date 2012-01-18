<?php
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

?>
<div class="wrap">
<?php
screen_icon();
echo "<h2>".esc_html( __('BackWPup Settings', 'backwpup'))."</h2>";
if (isset($backwpup_message) and !empty($backwpup_message)) 
	echo "<div id=\"message\" class=\"updated\"><p>".$backwpup_message."</p></div>";
?>
<form id="posts-filter" action="<?php echo backwpup_admin_url('admin.php')."?page=backwpupsettings";?>" method="post">
<?php wp_nonce_field('backwpup-cfg'); ?>
<input type="hidden" name="action" value="update" />
<h3><?php _e('Send Mail','backwpup'); ?></h3>
<p><?php _e('Here you can set the options for email sending. The settings will be used in jobs for sending backups via email or for sending log files.','backwpup'); ?></p>
<table class="form-table"> 
<tr valign="top"> 
<th scope="row"><label for="mailsndemail"><?php _e('Sender email','backwpup'); ?></label></th>
<td><input name="mailsndemail" type="text" id="mailsndemail" value="<?php echo backwpup_get_option('cfg','mailsndemail');?>" class="regular-text" />
</td> 
</tr> 
<tr valign="top"> 
<th scope="row"><label for="mailsndname"><?php _e('Sender name','backwpup'); ?></label></th>
<td><input name="mailsndname" type="text" id="mailsndname" value="<?php echo backwpup_get_option('cfg','mailsndname');?>" class="regular-text" /></td>
</tr>
</table> 

<h3><?php _e('Logs','backwpup'); ?></h3>
<p><?php _e('Here you can set Logfile related options.','backwpup'); ?></p>
<table class="form-table"> 
<tr valign="top"> 
<th scope="row"><label for="logfolder"><?php _e('Log file Folder','backwpup'); ?></label></th>
<td><input name="logfolder" type="text" id="logfolder" value="<?php echo backwpup_get_option('cfg','logfolder');?>" class="regular-text code" />
</td> 
</tr>
<tr valign="top"> 
<th scope="row"><label for="maxlogs"><?php _e('Max. Log Files in Folder','backwpup'); ?></label></th>
<td><input name="maxlogs" type="text" id="maxlogs" value="<?php echo backwpup_get_option('cfg','maxlogs');?>" class="small-text code" />
<span class="description"><?php _e('(Oldest files will deleted first.)','backwpup');?></span>
</td> 
</tr>
<tr valign="top"> 
<th scope="row"><?php _e('Compression','backwpup'); ?></th>
<td><fieldset><legend class="screen-reader-text"><span><?php _e('Compression','backwpup'); ?></span></legend><label for="gzlogs">
<input name="gzlogs" type="checkbox" id="gzlogs" value="1" <?php checked(backwpup_get_option('cfg','gzlogs'),true); ?><?php if (!function_exists('gzopen')) echo " disabled=\"disabled\""; ?> />
<?php _e('Gzip Log files!','backwpup'); ?></label>
</fieldset></td>
</tr>
</table>
<h3><?php _e('Jobs','backwpup'); ?></h3>
<p><?php _e('Here you can set Job related options.','backwpup'); ?></p>
<table class="form-table">
	<tr valign="top">
		<th scope="row"><?php _e('Alternate run noe job start','backwpup'); ?></th>
		<td><fieldset><legend class="screen-reader-text"><span><?php _e('Alternate run noe job start','backwpup'); ?></span></legend><label for="runnowalt">
			<input name="runnowalt" type="checkbox" id="runnowalt" value="1" <?php checked(backwpup_get_option('cfg','runnowalt'),true); ?> />
			<?php _e('If problems with redirect on run now job start you can try this','backwpup'); ?></label>
		</fieldset></td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="storeworkingdatain"><?php _e('Where to store working data','backwpup'); ?></label></th>
		<td>
			<select name="storeworkingdatain" id="storeworkingdatain">
				<option <?php selected(strtolower(backwpup_get_option('cfg','storeworkingdatain')),'db',true);?> value="db"><?php _e('Database','backwpup'); ?></option>
				<option <?php selected(strtolower(backwpup_get_option('cfg','storeworkingdatain')),'file',true);?> value="file"><?php _e('File','backwpup'); ?></option>
			</select>
			<span class="description"><?php _e('(The data will stored every second if a job run!)','backwpup');?></span>
		</td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="jobrunmaxexectime"><?php _e('Max. Script Execution time','backwpup'); ?></label></th>
		<td><input name="jobrunmaxexectime" type="text" id="jobrunmaxexectime" value="<?php echo backwpup_get_option('cfg','jobrunmaxexectime');?>" class="small-text code" />
			<span class="description"><?php _e('(0 = endless; Default. You can test the time under Tools. The job will be automatic restarted after this time.)','backwpup');?></span>
		</td>
	<tr valign="top">
<th scope="row"><label for="jobstepretry"><?php _e('Max. retrys for job steps','backwpup'); ?></label></th>
<td><input name="jobstepretry" type="text" id="jobstepretry" value="<?php echo backwpup_get_option('cfg','jobstepretry');?>" class="small-text code" />
</td> 
</tr>
<tr valign="top"> 
<th scope="row"><label for="jobscriptretry"><?php _e('Max. retrys for job script retries','backwpup'); ?></label></th>
<td><input name="jobscriptretry" type="text" id="jobscriptretry" value="<?php echo backwpup_get_option('cfg','jobscriptretry');?>" class="small-text code" <?php if (defined('ALTERNATE_WP_CRON') and ALTERNATE_WP_CRON) echo " disabled=\"disabled\""; ?> />
</td> 
</tr>
	<tr valign="top">
		<th scope="row"><label for="tempfolder"><?php _e('Temp file Folder','backwpup'); ?></label></th>
		<td><input name="tempfolder" type="text" id="tempfolder" value="<?php echo backwpup_get_option('cfg','tempfolder');?>" class="regular-text code" />
		</td>
	</tr>
	<tr valign="top">
		<th scope="row"><label for="jobrunauthkey"><?php _e('Key for start jobs external with a URL','backwpup'); ?></label></th>
		<td><input name="jobrunauthkey" type="text" id="jobrunauthkey" value="<?php echo backwpup_get_option('cfg','jobrunauthkey');?>" class="text code" />
			<span><?php echo sprintf(__('A unique key is: %s','backwpup'),wp_create_nonce('BackWPupJobRun')); ?></span>
			<span class="description"><?php _e('(empty = deactivated. Will be used for, that nobody else can use the job start URLs.)','backwpup');?></span>
		</td>
	</tr>
</table>

<h3><?php _e('WP Admin Bar','backwpup'); ?></h3>
<p><?php _e('Will you see BackWPup in the WordPress Admin Bar?','backwpup'); ?></p>
<table class="form-table"> 
<tr valign="top"> 
<th scope="row"><?php _e('Admin Bar','backwpup'); ?></th>
<td><fieldset><legend class="screen-reader-text"><span><?php _e('Admin Bar','backwpup'); ?></span></legend><label for="showadminbar">
<input name="showadminbar" type="checkbox" id="showadminbar" value="1" <?php checked(backwpup_get_option('cfg','showadminbar'),true); ?> />
<?php _e('Show BackWPup Links in Admin Bar.','backwpup'); ?></label>
</fieldset></td>
</tr>
</table>

<h3><?php _e('Http basic authentication','backwpup'); ?></h3>
<p><?php _e('Is your blog behind a http basic authentication (.htaccess)? Only then you must set the username and password for authentication to get jobs working.','backwpup'); ?></p>
<table class="form-table"> 
<tr valign="top">
<th scope="row"><label for="httpauthuser"><?php _e('Username:','backwpup'); ?></label></th>
<td><input name="httpauthuser" type="text" id="httpauthuser" value="<?php echo backwpup_get_option('cfg','httpauthuser');?>" class="regular-text" />
</td> 
</tr>
<tr valign="top"> 
<th scope="row"><label for="httpauthpassword"><?php _e('Password:','backwpup'); ?></label></th>
<td><input name="httpauthpassword" type="password" id="httpauthpassword" value="<?php echo backwpup_get_option('cfg','httpauthpassword');?>" class="regular-text" />
</tr>
</table>
<h3><?php _e('Cron service of BackWPup.com','backwpup'); ?></h3>
<p><?php _e('Use cron service of backwpup.com','backwpup'); ?></p>
<table class="form-table"> 
<tr valign="top"> 
<th scope="row"><?php _e('Key for cron service','backwpup'); ?></th>
<td><fieldset><legend class="screen-reader-text"><span><?php _e('Key for cron service','backwpup'); ?></span></legend>
	<label for="apicronservicekey">
		<input name="apicronservicekey" type="text" id="apicronservicekey" value="<?php echo backwpup_get_option('cfg','apicronservicekey');?>" class="text code" />
	</label>
	<span><?php echo sprintf(__('A unique key is: %s','backwpup'),wp_create_nonce('BackWPupJobRunAPI')); ?></span>
	<span class="description"><?php _e('(empty = deactivated. Will be used for, that nobody else can use the job start URLs.)','backwpup');?></span>
</label>
</fieldset>
</td>
</tr>
	<tr valign="top">
	<th scope="row"><?php _e('Terms of service','backwpup'); ?></th>
	<td>
		<?php _e('If you use this service in jobs, the schedule will submitted to api.backwpup.com. The api.backwpup.com will call the script to start the job directly. <em>Use this service only if you have not a cron service of your hoster, or a blog that has a few visitors.</em> The cron service can start a job behind a basic authentication (.htaccess), on that the http authentication data will transferred too! Please make a little donation for the plugin if you use this service. The service can be removed by me without a massage.','backwpup'); ?><br />
		<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=Q3QSVRSFXBLSE" target="_new"><img src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" title="PayPal - The safer, easier way to pay online!"></a>
	</td>
	</tr>
</table>
<p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="Save Changes"  /></p>
</form>
</div>