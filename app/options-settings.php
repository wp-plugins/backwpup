<div class="wrap">
	<div id="icon-tools" class="icon32"><br /></div>
<h2><?php _e("BackWPup Settings", "backwpup"); ?></h2>
<ul class="subsubsub"> 
<li><a href="admin.php?page=BackWPup"><?PHP _e('Jobs','backwpup'); ?></a> |</li> 
<li><a href="admin.php?page=BackWPup&amp;action=logs"><?PHP _e('Logs','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=db_restore"><?PHP _e('DB Restore','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=settings" class="current"><?PHP _e('Settings','backwpup'); ?></a></li>
</ul>

<form method="post" action="">
<input type="hidden" name="action" value="savecfg" />
<?php  wp_nonce_field('backwpup-cfg'); ?>

<table class="form-table">
<tr valign="top"> 
<tr valign="top"> 
<th scope="row"><label for="jobname"><?PHP _e('Script Runime','backwpup'); ?></label></th> 
<td>
<? 
	echo __('PHP.ini execution time:','backwpup').' '.ini_get('max_execution_time').' '.__('sec.','backwpup').'<br />'; 
?>
</td> 
</tr> 

<tr valign="top">
<th scope="row"><label for="mailaddress"><?PHP _e('Mail Send:','backwpup'); ?></label></th> 
<td>
<span class="description"><?PHP _e('Send mail method:','backwpup'); ?></span>
<?PHP 
echo '<select name="mailmethod">';
echo '<option value="mail"'.selected('mail',$cfg['mailmethod'],false).'>'.__('PHP: mail()','backwpup').'</option>';
echo '<option value="Sendmail"'.selected('Sendmail',$cfg['mailmethod'],false).'>'.__('Sendmail','backwpup').'</option>';
echo '<option value="SMTP"'.selected('SMTP',$cfg['mailmethod'],false).'>'.__('SMTP','backwpup').'</option>';
echo '</select>';
if (empty($cfg['mailsendmail'])) {
	$cfg['mailsendmail']=substr(ini_get('sendmail_path'),0,strpos(ini_get('sendmail_path'),' -'));
}
?><br />
<span class="description"><?PHP _e('Sendmail Path:','backwpup'); ?></span><input name="mailhost" type="text" value="<?PHP echo $cfg['mailsendmail'];?>" class="regular-text" /><br />
<span class="description"><?PHP _e('SMTP Hostname:','backwpup'); ?></span><input name="mailhost" type="text" value="<?PHP echo $cfg['mailhost'];?>" class="regular-text" /><br />
<span class="description"><?PHP _e('SMTP Secure Connection:','backwpup'); ?></span><?PHP 
echo '<select name="mailsecure">';
echo '<option value=""'.selected('',$cfg['mailsecure'],false).'>'.__('none','backwpup').'</option>';
echo '<option value="ssl"'.selected('ssl',$cfg['mailsecure'],false).'>SSL</option>';
echo '<option value="tls"'.selected('tls',$cfg['mailsecure'],false).'>TLS</option>';
echo '</select>';
if (!empty($cfg['mailsendmail']))
	$cfg['mailsendmail']='/usr/sbin/sendmail';
?><br />
<span class="description"><?PHP _e('SMTP Username:','backwpup'); ?></span><input name="mailuser" type="text" value="<?PHP echo $cfg['mailuser'];?>" class="user" /><br />
<span class="description"><?PHP _e('SMTP Password:','backwpup'); ?></span><input name="mailpass" type="password" value="<?PHP echo $cfg['mailpass'];?>" class="password" /><br />
</td> 
</tr>


</table>
 
<p class="submit"> 
<input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Changes', 'backwpup'); ?>" /> 
</p> 
</form>
