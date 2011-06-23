<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

$cfg=get_option('backwpup');	
	
echo "<div class=\"wrap\">";
screen_icon();
echo "<h2>".esc_html( __('BackWPup Settings', 'backwpup'))."</h2>";
if (isset($backwpup_message) and !empty($backwpup_message)) 
	echo "<div id=\"message\" class=\"updated\"><p>".$backwpup_message."</p></div>";
echo "<form id=\"posts-filter\" action=\"".admin_url('admin.php')."?page=backwpupsettings\" method=\"post\">";
wp_nonce_field('backwpup-cfg');
?>
<input type="hidden" name="action" value="update" />
<h3><?PHP _e('Send Mail','backwpup'); ?></h3> 
<p><?PHP _e('Here you can set special things for Mail sending. The settings will used in jobs for sending backups with mail or sending log files.','backwpup'); ?></p>
<table class="form-table"> 
<tr valign="top"> 
<th scope="row"><label for="mailsndemail"><?PHP _e('Sender email','backwpup'); ?></label></th> 
<td><input name="mailsndemail" type="text" id="mailsndemail" value="<?PHP echo $cfg['mailsndemail'];?>" class="regular-text" />
</td> 
</tr> 
<tr valign="top"> 
<th scope="row"><label for="mailsndname"><?PHP _e('Sender name','backwpup'); ?></label></th> 
<td><input name="mailsndname" type="text" id="mailsndname" value="<?PHP echo $cfg['mailsndname'];?>" class="regular-text" /></td> 
</tr> 
<tr valign="top"> 
<th scope="row"><label for="mailmethod"><?PHP _e('Send mail method','backwpup'); ?></label></th> 
<td> 
<?PHP 
echo '<select id="mailmethod" name="mailmethod">';
echo '<option class="level-0" value="mail"'.selected('mail',$cfg['mailmethod'],false).'>'.__('PHP: mail()','backwpup').'</option>';
echo '<option class="level-0" value="Sendmail"'.selected('Sendmail',$cfg['mailmethod'],false).'>'.__('Sendmail','backwpup').'</option>';
echo '<option class="level-0" value="SMTP"'.selected('SMTP',$cfg['mailmethod'],false).'>'.__('SMTP','backwpup').'</option>';
echo '</select>';
?>
</td> 
</tr> 
<tr valign="top" id="mailsendmail" <?PHP if ($cfg['mailmethod']!='Sendmail') echo 'style="display:none;"';?>> 
<th scope="row"><label for="mailsendmail"><?PHP _e('Sendmail path','backwpup'); ?></label></th> 
<td> 
<input name="mailsendmail" id="sendmail" type="text" value="<?PHP echo $cfg['mailsendmail'];?>" class="regular-text code" />
</select> 
</td> 
</tr> 
<tr valign="top" class="mailsmtp" <?PHP if ($cfg['mailmethod']!='SMTP') echo 'style="display:none;"';?>> 
<th scope="row"><label for="mailhost"><?PHP _e('SMTP hostname','backwpup'); ?></label></th> 
<td> 
<input name="mailhost" id="mailhost" type="text" value="<?PHP echo $cfg['mailhost'];?>" class="regular-text code" />&nbsp;
<?PHP _e('Port:','backwpup'); ?><input name="mailhostport" id="mailhostport" type="text" value="<?PHP echo $cfg['mailhostport'];?>" class="small-text code" />
</td> 
</tr>
<tr valign="top" class="mailsmtp" <?PHP if ($cfg['mailmethod']!='SMTP') echo 'style="display:none;"';?>> 
<th scope="row"><label for="mailsecure"><?PHP _e('SMTP secure connection','backwpup'); ?></label></th> 
<td> 
<select name="mailsecure">
<option class="level-0" value=""<?PHP selected('',$cfg['mailsecure'],true); ?>><?PHP _e('none','backwpup'); ?></option>
<option class="level-0" value="ssl"<?PHP selected('ssl',$cfg['mailsecure'],true); ?>>SSL</option>
<option class="level-0" value="tls"<?PHP selected('tls',$cfg['mailsecure'],true); ?>>TLS</option>
</select>
</td> 
</tr>
<tr valign="top" class="mailsmtp" <?PHP if ($cfg['mailmethod']!='SMTP') echo 'style="display:none;"';?>> 
<th scope="row"><label for="mailuser"><?PHP _e('SMTP username','backwpup'); ?></label></th> 
<td> 
<input name="mailuser" id="mailuser" type="text" value="<?PHP echo $cfg['mailuser'];?>" class="regular-text" />
</td> 
</tr>
<tr valign="top" class="mailsmtp" <?PHP if ($cfg['mailmethod']!='SMTP') echo 'style="display:none;"';?>> 
<th scope="row"><label for="mailpass"><?PHP _e('SMTP password','backwpup'); ?></label></th> 
<td> 
<input name="mailpass" id="mailpass" type="password" value="<?PHP echo base64_decode($cfg['mailpass']);?>" class="regular-text" />
</td> 
</tr>
</table> 

<h3><?PHP _e('Logs','backwpup'); ?></h3> 
<p><?PHP _e('Here you can set Logfile related things.','backwpup'); ?></p>
<table class="form-table"> 
<tr valign="top"> 
<th scope="row"><label for="dirlogs"><?PHP _e('Log file Folder','backwpup'); ?></label></th> 
<td><input name="dirlogs" type="text" id="dirlogs" value="<?PHP echo $cfg['dirlogs'];?>" class="regular-text code" />
</td> 
</tr>
<tr valign="top"> 
<th scope="row"><label for="maxlogs"><?PHP _e('Max. Log Files in Folder','backwpup'); ?></label></th> 
<td><input name="maxlogs" type="text" id="maxlogs" value="<?PHP echo $cfg['maxlogs'];?>" class="small-text code" />
<span class="description"><?PHP _e('(Oldest files will deleted first.)','backwpup');?></span>
</td> 
</tr>
<tr valign="top"> 
<th scope="row"><?PHP _e('Compression','backwpup'); ?></th> 
<td><fieldset><legend class="screen-reader-text"><span><?PHP _e('Compression','backwpup'); ?></span></legend><label for="gzlogs"> 
<input name="gzlogs" type="checkbox" id="gzlogs" value="1" <?php checked($cfg['gzlogs'],true); ?><?php if (!function_exists('gzopen')) echo " disabled=\"disabled\""; ?> /> 
<?PHP _e('Gzip Log files!','backwpup'); ?></label> 
</fieldset></td>
</tr>
</table>
<h3><?PHP _e('Jobs','backwpup'); ?></h3> 
<p><?PHP _e('Here you can set Job related things.','backwpup'); ?></p>
<table class="form-table"> 
<tr valign="top"> 
<th scope="row"><label for="jobstepretry"><?PHP _e('Max. retrys for job steps','backwpup'); ?></label></th> 
<td><input name="jobstepretry" type="text" id="jobstepretry" value="<?PHP echo $cfg['jobstepretry'];?>" class="small-text code" />
</td> 
</tr>
<tr valign="top"> 
<th scope="row"><label for="jobscriptretry"><?PHP _e('Max. retrys for job script restarts','backwpup'); ?></label></th> 
<td><input name="jobscriptretry" type="text" id="jobscriptretry" value="<?PHP echo $cfg['jobscriptretry'];?>" class="small-text code" />
</td> 
</tr>
<tr valign="top"> 
<?PHP
@ini_set('safe_mode','0');
$disabled='';
if (ini_get('safe_mode')) {
	$cfg['jobscriptruntime']=ini_get('max_execution_time');
	$cfg['jobscriptruntimelong']=ini_get('max_execution_time');
	$disabled=' disabled="disabled"';
} 
?>
<th scope="row"><label for="jobscriptruntime"><?PHP _e('Max. normal script runtime:','backwpup'); ?></label></th> 
<td><input name="jobscriptruntime" type="text" id="jobscriptruntime" value="<?PHP echo $cfg['jobscriptruntime'];?>" class="small-text code" <?PHP echo $disabled;?>/> <?PHP _e('sec.','backwpup');?>&nbsp;
<span class="description"><?PHP _e('Script runtime will reset on many job functions. You can only set it if safemode off. Default runtime is 30 sec. Your ini setting is in sec.:','backwpup');echo ini_get('max_execution_time');?></span>
</td> 
</tr>
<tr valign="top"> 
<th scope="row"><label for="jobscriptruntimelong"><?PHP _e('Max. long script runtime:','backwpup'); ?></label></th>
<td><input name="jobscriptruntimelong" type="text" id="jobscriptruntimelong" value="<?PHP echo $cfg['jobscriptruntimelong'];?>" class="small-text code" <?PHP echo $disabled;?>/> <?PHP _e('sec.','backwpup');?>&nbsp;
<span class="description"><?PHP _e('Script runtime for loong operations withaut responce to script. You can only set it if safemode off. Default runtime is 300 sec.(Max. on most webservers.)','backwpup');?></span></td> 
</tr>
</table>

<h3><?PHP _e('WP Admin Bar','backwpup'); ?></h3>
<p><?PHP _e('Will you see BackWPup in the WordPress Admin Bar?','backwpup'); ?></p>
<table class="form-table"> 
<tr valign="top"> 
<th scope="row"><?PHP _e('Admin Bar','backwpup'); ?></th> 
<td><fieldset><legend class="screen-reader-text"><span><?PHP _e('Admin Bar','backwpup'); ?></span></legend><label for="showadminbar"> 
<input name="showadminbar" type="checkbox" id="showadminbar" value="1" <?php checked($cfg['showadminbar'],true); ?> /> 
<?PHP _e('Show BackWPup Links in Admin Bar.','backwpup'); ?></label> 
</fieldset></td>
</tr>
</table>

<h3><?PHP _e('WP-Cron','backwpup'); ?></h3>
<p><?PHP _e('If you would use the cron job of your hoster you must point it to the url:','backwpup'); echo ' <i>'.get_option('siteurl').'/wp-cron.php</i>'; ?></p>
<table class="form-table"> 
<tr valign="top"> 
<th scope="row"><?PHP _e('Disable WP-Cron','backwpup'); ?></th> 
<td><fieldset><legend class="screen-reader-text"><span><?PHP _e('Disable WP-Cron','backwpup'); ?></span></legend><label for="disablewpcron"> 
<input name="disablewpcron" type="checkbox" id="disablewpcron" value="1" <?php checked($cfg['disablewpcron'],true); ?> /> 
<?PHP _e('Use your host\'s Cron Job and disable WP-Cron','backwpup'); ?></label> 
</fieldset></td>
</tr>
</table>
<p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="Save Changes"  /></p>
</form>
</div>