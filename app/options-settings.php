<?PHP 
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');

$cfg=get_option('backwpup');
?>
<div class="wrap">
	<div id="icon-tools" class="icon32"><br /></div>
<h2><?php _e("BackWPup Settings", "backwpup"); ?></h2>
<?php backwpup_option_submenues(); ?>

<div class="clear"></div>

<form method="post" action="">
<input type="hidden" name="subpage" value="settings" />
<?php  wp_nonce_field('backwpup-cfg'); ?>

<div id="poststuff" class="metabox-holder has-right-sidebar"> 
	<div class="inner-sidebar">
		<div id="side-sortables" class="meta-box-sortables">
			<input type="submit" name="submit" class="button-primary" value="<?php _e('Save Changes', 'backwpup'); ?>" /> 
		</div>
	</div>
	<div class="has-sidebar" >
		<div id="post-body-content" class="has-sidebar-content">
						
			<div id="mailtype" class="postbox">
				<h3 class="hndle"><span><?PHP _e('Send Mail','backwpup'); ?></span></h3>
				<div class="inside">
					<b><?PHP _e('Sender Email:','backwpup'); ?></b><br /><input name="mailsndemail" type="text" value="<?PHP echo $cfg['mailsndemail'];?>" class="large-text" /><br />
					<b><?PHP _e('Sender Name:','backwpup'); ?></b><br /><input name="mailsndname" type="text" value="<?PHP echo $cfg['mailsndname'];?>" class="large-text" /><br />
					<b><?PHP _e('Send mail method:','backwpup'); ?></b><br />
					<?PHP 
					echo '<select id="mailmethod" name="mailmethod">';
					echo '<option value="mail"'.selected('mail',$cfg['mailmethod'],false).'>'.__('PHP: mail()','backwpup').'</option>';
					echo '<option value="Sendmail"'.selected('Sendmail',$cfg['mailmethod'],false).'>'.__('Sendmail','backwpup').'</option>';
					echo '<option value="SMTP"'.selected('SMTP',$cfg['mailmethod'],false).'>'.__('SMTP','backwpup').'</option>';
					echo '</select>';
					?><br />
					<label id="mailsendmail" <?PHP if ($cfg['mailmethod']!='Sendmail') echo 'style="display:none;"';?>><b><?PHP _e('Sendmail Path:','backwpup'); ?></b><br /><input name="mailsendmail" type="text" value="<?PHP echo $cfg['mailsendmail'];?>" class="large-text" /><br /></label>
					<label id="mailsmtp" <?PHP if ($cfg['mailmethod']!='SMTP') echo 'style="display:none;"';?>>
					<b><?PHP _e('SMTP Hostname:','backwpup'); ?></b><br /><input name="mailhost" type="text" value="<?PHP echo $cfg['mailhost'];?>" class="large-text" /><br />
					<b><?PHP _e('SMTP Secure Connection:','backwpup'); ?></b><br />
					<select name="mailsecure">
					<option value=""<?PHP selected('',$cfg['mailsecure'],true); ?>><?PHP _e('none','backwpup'); ?></option>
					<option value="ssl"<?PHP selected('ssl',$cfg['mailsecure'],true); ?>>SSL</option>
					<option value="tls"<?PHP selected('tls',$cfg['mailsecure'],true); ?>>TLS</option>
					</select><br />
					<b><?PHP _e('SMTP Username:','backwpup'); ?></b><br /><input name="mailuser" type="text" value="<?PHP echo $cfg['mailuser'];?>" class="user large-text" /><br />
					<b><?PHP _e('SMTP Password:','backwpup'); ?></b><br /><input name="mailpass" type="password" value="<?PHP echo base64_decode($cfg['mailpass']);?>" class="password large-text" /><br />
					</label>
				</div>
			</div>
		
			<div id="logs" class="postbox">
				<h3 class="hndle"><span><?PHP _e('Logs','backwpup'); ?></span></h3>
				<div class="inside">
					<b><?PHP _e('Log file Folder:','backwpup'); ?></b><br />
					<input name="dirlogs" type="text" value="<?PHP echo $cfg['dirlogs'];?>" class="large-text" /><br />
					<b><?PHP _e('Max. Log Files in Folder:','backwpup'); ?></b><br />
					<input name="maxlogs" id="maxlogs" size="3" type="text" value="<?PHP echo $cfg['maxlogs'];?>" class="small-text" /><span class="description"><?PHP _e('(Oldest files will deleted first.)','backwpup');?></span><br />
					<input class="checkbox" value="1" type="checkbox" <?php checked($cfg['gzlogs'],true); ?> name="gzlogs" <?php if (!function_exists('gzopen')) echo "disabled=\"disabled\""; ?> /><b>&nbsp;<?PHP _e('Gzip Log files!','backwpup'); ?></b><br />
					<input class="checkbox" value="1" type="checkbox" <?php checked($cfg['logfilelist'],true); ?> name="logfilelist" /><b>&nbsp;<?PHP _e('Log a detailed file list.','backwpup'); ?></b><br />
				</div>
			</div>
		
			<div id="disablewpcron" class="postbox">
				<h3 class="hndle"><span><?PHP _e('Disable WP-Cron','backwpup'); ?></span></h3>
				<div class="inside">
					<input class="checkbox" id="disablewpcron" type="checkbox"<?php checked($cfg['disablewpcron'],true,true);?> name="disablewpcron" value="1"/> <?PHP _e('Use your host\'s Cron Job and disable WP-Cron','backwpup'); ?><br />
					<?PHP _e('You must set up a cron job that calls:','backwpup'); ?><br />
					<i> php -q <?PHP echo ABSPATH.'wp-cron.php'; ?></i><br /> 
					<?PHP _e('or URL:','backwpup'); ?> <i><?PHP echo trailingslashit(get_option('siteurl')).'wp-cron.php'; ?></i><br /> 
				</div>
			</div>
		
			<div id="dirtemp" class="postbox">
				<h3 class="hndle"><span><?PHP _e('Temp Folder','backwpup'); ?></span></h3>
				<div class="inside">
					<input name="dirtemp" type="text" value="<?PHP echo $cfg['dirtemp'];?>" class="large-text" /><br />
				</div>
			</div>
			<input type="submit" name="submit" class="button-primary" value="<?php _e('Save Changes', 'backwpup'); ?>" /> 
		</div>
	</div>
</div>
<p class="submit"> 

</p> 
</form>
</div>
