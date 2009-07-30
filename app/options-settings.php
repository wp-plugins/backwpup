<div class="wrap">
	<div id="icon-tools" class="icon32"><br /></div>
<h2><?php _e("BackWPup Settings", "backwpup"); ?></h2>
<ul class="subsubsub"> 
<li><a href="admin.php?page=BackWPup"><?PHP _e('Jobs','backwpup'); ?></a> |</li> 
<li><a href="admin.php?page=BackWPup&amp;action=logs"><?PHP _e('Logs','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=tools"><?PHP _e('Tools','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=settings" class="current"><?PHP _e('Settings','backwpup'); ?></a></li>
</ul>

<form method="post" action="">
<input type="hidden" name="action" value="savecfg" />
<?php  wp_nonce_field('backwpup-cfg'); ?>

<table class="form-table">
<tr valign="top"> 
<th scope="row"><label for="jobname"><?PHP _e('Script Runime','backwpup'); ?></label></th> 
<td>
<? 
echo __('PHP.ini execution time:','backwpup').' '.ini_get('max_execution_time').' '.__('sec.','backwpup').'<br />'; 
	
if (empty($cfg['maxexecutiontime']));
	$cfg['maxexecutiontime']=300;
	
if (!ini_get('safe_mode') or strtolower(ini_get('safe_mode'))=='off' or ini_get('safe_mode')=='0') 
	echo __('Set max ececution Time for Scripts:','backwpup').'<input name="maxexecutiontime" type="text" value="'.$cfg['maxexecutiontime'].'" class="small-text" />'.__('sec.','backwpup');
 else 
	echo '<input name="maxexecutiontime" type="hidden" value="'.$cfg['maxexecutiontime'].'"  />';

?>
</td> 
</tr> 

<tr valign="top"> 
<th scope="row"><label for="jobname"><?PHP _e('Max Memory Usage','backwpup'); ?></label></th> 
<td>
<? 
echo __('PHP.ini Memory Limit:','backwpup').' '.ini_get('memory_limit').'<br />'; 
	
if (empty($cfg['memorylimit']));
	$cfg['memorylimit']='128M';
	
if (!function_exists('memory_get_usage')) 
	echo __('Set Memory limit:','backwpup').'<input name="memorylimit" type="text" value="'.$cfg['memorylimit'].'" class="small-text" />';
 else 
	echo '<span class="description">'.__('Memory will be automaticly increased!!!','backwpup').'</span><input name="memorylimit" type="hidden" value="'.$cfg['memorylimit'].'"  />';

?>
</td> 
</tr> 



<tr valign="top">
<th scope="row"><label for="mailaddress"><?PHP _e('Mail Send:','backwpup'); ?></label></th> 
<td>
<?PHP _e('Send mail method:','backwpup'); 
echo '<select name="mailmethod">';
echo '<option value="mail"'.selected('mail',$cfg['mailmethod'],false).'>'.__('PHP: mail()','backwpup').'</option>';
echo '<option value="Sendmail"'.selected('Sendmail',$cfg['mailmethod'],false).'>'.__('Sendmail','backwpup').'</option>';
echo '<option value="SMTP"'.selected('SMTP',$cfg['mailmethod'],false).'>'.__('SMTP','backwpup').'</option>';
echo '</select>';
if (empty($cfg['mailsendmail'])) {
	$cfg['mailsendmail']=substr(ini_get('sendmail_path'),0,strpos(ini_get('sendmail_path'),' -'));
}
?><br />
<?PHP _e('Sendmail Path:','backwpup'); ?><input name="mailhost" type="text" value="<?PHP echo $cfg['mailsendmail'];?>" class="regular-text" /><br />
<?PHP _e('SMTP Hostname:','backwpup'); ?><input name="mailhost" type="text" value="<?PHP echo $cfg['mailhost'];?>" class="regular-text" /><br />
<?PHP _e('SMTP Secure Connection:','backwpup');
echo '<select name="mailsecure">';
echo '<option value=""'.selected('',$cfg['mailsecure'],false).'>'.__('none','backwpup').'</option>';
echo '<option value="ssl"'.selected('ssl',$cfg['mailsecure'],false).'>SSL</option>';
echo '<option value="tls"'.selected('tls',$cfg['mailsecure'],false).'>TLS</option>';
echo '</select>';
if (!empty($cfg['mailsendmail']))
	$cfg['mailsendmail']='/usr/sbin/sendmail';
?><br />
<?PHP _e('SMTP Username:','backwpup'); ?><input name="mailuser" type="text" value="<?PHP echo $cfg['mailuser'];?>" class="user" /><br />
<?PHP _e('SMTP Password:','backwpup'); ?><input name="mailpass" type="password" value="<?PHP echo base64_decode($cfg['mailpass']);?>" class="password" /><br />
</td> 
</tr>

<tr valign="top">
<th scope="row"><label for="maxlogs"><?PHP _e('Max. number of Logs','backwpup'); ?></label></th> 
<td>
<input name="maxlogs" type="text" value="<?PHP echo $cfg['maxlogs'];?>" class="small-text" /><span class="description"><?PHP _e('0=off','backwpup');?> <?PHP _e('Oldest log will deletet first.','backwpup');?></span>
</td> 
</tr>

<tr valign="top"> 
<th scope="row"><label for="jobname"><?PHP _e('Disable WP-Cron:','backwpup'); ?></label></th> 
<td>
<input class="checkbox" type="checkbox"<?php checked($cfg['disablewpcron'],true,true);?> name="disablewpcron" value="1"/>
 <?PHP _e('Use Cron job of Hoster and disable WP_Cron','backwpup'); ?><br />
<?PHP _e('You must set up a cron job that calls:','backwpup'); ?><br />
<i> php -q <?PHP echo ABSPATH.'wp-cron.php'; ?></i><br /> 
<?PHP _e('or URL:','backwpup'); ?> <i><?PHP echo trailingslashit(get_option('siteurl')).'wp-cron.php'; ?></i><br /> 
</td> 
</tr> 

</table>
 
<p class="submit"> 
<input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Changes', 'backwpup'); ?>" /> 
</p> 
</form>
