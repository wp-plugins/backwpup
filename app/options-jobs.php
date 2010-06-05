<?PHP 
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');
?>
<div class="wrap">
	<div id="icon-tools" class="icon32"><br /></div>
<h2><?php _e('Edit BackWPup Job', 'backwpup'); ?></h2>

<form method="post" action="">
<input type="hidden" name="action" value="saveeditjob" />
<input type="hidden" name="jobid" value="<?PHP echo $jobid;?>" />
<?php 
wp_nonce_field('edit-job'); 
if (empty($jobs[$jobid]['type'])) 
	$jobs[$jobid]['type']='DB+FILE';

$todo=explode('+',$jobs[$jobid]['type']);
?>

<table class="form-table">

<tr valign="top"> 
<th scope="row"><label for="job_type"><?PHP _e('Job Type','backwpup'); ?></label></th> 
<td> 
<?php
foreach (backwpup_backup_types() as $type) {
	echo "<input class=\"checkbox\" type=\"checkbox\"".checked(true,in_array($type,$todo),false)." name=\"type[]\" value=\"".$type."\"/> ".backwpup_backup_types($type);
}
?>
<input type="submit" name="change" class="button" value="<?php _e('Change', 'backwpup'); ?>" /> 
</td> 
</tr> 

<tr valign="top"> 
<th scope="row"><label for="jobname"><?PHP _e('Job Name','backwpup'); ?></label></th> 
<td><input name="name" type="text" id="jobname" value="<?PHP echo $jobs[$jobid]['name'];?>" class="regular-text" /></td> 
</tr> 

<?PHP if (in_array('DB',$todo) or in_array('OPTIMIZE',$todo) or in_array('CHECK',$todo)) {?>
<tr valign="top"> 
<th scope="row"><label for="dbexclude"><?PHP _e('Exclude Database Tables:','backwpup'); ?></label></th><td> 
<?php
$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');
if (!isset($jobs[$jobid]['dbexclude'])) { //def.
	foreach ($tables as $table) {
		if (substr($table,0,strlen($wpdb->prefix))!=$wpdb->prefix)
			$jobs[$jobid]['dbexclude'][]=$table;
	}
}
foreach ($tables as $table) {
	if ($wpdb->backwpup_logs<>$table)
		echo ' <input class="checkbox" type="checkbox"'.checked(in_array($table,(array)$jobs[$jobid]['dbexclude']),true,false).' name="dbexclude[]" value="'.$table.'"/> '.$table.'<br />';
}

?>
</td></tr>
<?PHP } ?>
<?PHP if (in_array('FILE',$todo)) {?>
<?PHP if (!isset($jobs[$jobid]['backuproot'])) $jobs[$jobid]['backuproot']=true; if (!isset($jobs[$jobid]['backupcontent'])) $jobs[$jobid]['backupcontent']=true; if (!isset($jobs[$jobid]['backupplugins'])) $jobs[$jobid]['backupplugins']=true;?>
<tr valign="top"> 
<th scope="row"><label for="fileinclude"><?PHP _e('Backup Blog Folders','backwpup'); ?></label></th><td id="fileinclude"> 
<input class="checkbox" type="checkbox"<?php checked($jobs[$jobid]['backuproot'],true,true);?> name="backuproot" value="1"/> <?php _e('Blog root and WP Files','backwpup');?><br />
<input class="checkbox" type="checkbox"<?php checked($jobs[$jobid]['backupcontent'],true,true);?> name="backupcontent" value="1"/> <?php _e('Blog Content','backwpup');?><br />
<input class="checkbox" type="checkbox"<?php checked($jobs[$jobid]['backupplugins'],true,true);?> name="backupplugins" value="1"/> <?php _e('Blog Plugins','backwpup');?>
</td></tr> 

<tr valign="top"> 
<th scope="row"><label for="dirinclude"><?PHP _e('Include Folders','backwpup'); ?></label></th><td> 
<input name="dirinclude" id="dirinclude" type="text" value="<?PHP echo $jobs[$jobid]['dirinclude'];?>" class="regular-text" /><span class="description"><?PHP echo __('Separate with ,. Full Path like:','backwpup').' '.str_replace('\\','/',ABSPATH); ?></span>
</td></tr> 

<tr valign="top"> 
<th scope="row"><label for="fileexclude"><?PHP _e('Exclude Files/Folders','backwpup'); ?></label></th><td> 
<input name="fileexclude" id="fileexclude" type="text" value="<?PHP echo $jobs[$jobid]['fileexclude'];?>" class="regular-text" /><span class="description"><?PHP _e('Separate with ,','backwpup') ?></span>
</td></tr> 
<?PHP } ?>

<tr valign="top"> 
<th scope="row"><label for="jobschedule"><?PHP _e('Schedule','backwpup'); ?></label></th> 
<td id="jobschedule">
<?php 
_e('Run Every:', 'backwpup');
echo '<select name="scheduleintervalteimes">';
for ($i=1;$i<=60;$i++) {
	echo '<option value="'.$i.'"'.selected($i,$jobs[$jobid]['scheduleintervalteimes'],false).'>'.$i.'</option>';
}
echo '</select>';
if (empty($jobs[$jobid]['scheduleintervaltype']))
	$jobs[$jobid]['scheduleintervaltype']=3600;
echo '<select name="scheduleintervaltype">';
echo '<option value="60"'.selected('3600',$jobs[$jobid]['scheduleintervaltype'],false).'>'.__('Min(s)','backwpup').'</option>';
echo '<option value="3600"'.selected('3600',$jobs[$jobid]['scheduleintervaltype'],false).'>'.__('Hour(s)','backwpup').'</option>';
echo '<option value="86400"'.selected('86400',$jobs[$jobid]['scheduleintervaltype'],false).'>'.__('Day(s)','backwpup').'</option>';
echo '</select><br />';

_e('Start Time:', 'backwpup');
if (empty($jobs[$jobid]['scheduletime']))
	$jobs[$jobid]['scheduletime']=current_time('timestamp');

echo '<select name="schedulehour">';
for ($i=0;$i<=23;$i++) {
	echo '<option value="'.$i.'"'.selected($i,date_i18n('G',$jobs[$jobid]['scheduletime']),false).'>'.$i.'</option>';
}
echo '</select>:';
echo '<select name="scheduleminute">';
for ($i=0;$i<=59;$i++) {
	$minute=$i;
	if (strlen($minute)<2)
		$minute='0'.$minute;
	echo '<option value="'.$i.'"'.selected($minute,date_i18n('i',$jobs[$jobid]['scheduletime']),false).'>'.$i.'</option>';
}
echo '</select><br />';
_e('Start Date:', 'backwpup'); 
echo '<select name="scheduleday">';
for ($i=1;$i<=31;$i++) {
	echo '<option value="'.$i.'"'.selected($i,date_i18n('j',$jobs[$jobid]['scheduletime']),false).'>'.$i.'</option>';
}
echo '</select>.';
$month=array('1'=>__('January'),'2'=>__('February'),'3'=>__('March'),'4'=>__('April'),'5'=>__('May'),'6'=>__('June'),'7'=>__('July'),'8'=>__('August'),'9'=>__('September'),'10'=>__('October'),'11'=>__('November'),'12'=>__('December'));
echo '<select name="schedulemonth">';
for ($i=1;$i<=12;$i++) {
	echo '<option value="'.$i.'"'.selected($i,date_i18n('n',$jobs[$jobid]['scheduletime']),false).'>'.$month[$i].'</option>';
}
echo '</select>.';
echo '<select name="scheduleyear">';
for ($i=date_i18n('Y')-1;$i<=date_i18n('Y')+3;$i++) {
	echo '<option value="'.$i.'"'.selected($i,date_i18n('Y',$jobs[$jobid]['scheduletime']),false).'>'.$i.'</option>';
}
echo '</select><br />';
?>
<input class="checkbox" value="1" type="checkbox" <?php checked($jobs[$jobid]['activated'],true); ?> name="activated" /> <?PHP _e('Activate scheduling', 'backwpup'); ?>
</td> 
</tr> 

<?PHP if (in_array('DB',$todo) or in_array('FILE',$todo) or in_array('WPEXP',$todo)) {?>

<tr valign="top">
<th scope="row"><label for="fileformart"><?PHP _e('Backup File Format','backwpup'); ?></label></th> 
<td>
<?PHP 
echo '<select name="fileformart">';
if (function_exists('gzopen') or class_exists('ZipArchive'))
	echo '<option value=".zip"'.selected('.zip',$jobs[$jobid]['fileformart'],false).'>'.__('ZIP (.zip)','backwpup').'</option>';
echo '<option value=".tar"'.selected('.tar',$jobs[$jobid]['fileformart'],false).'>'.__('TAR (.tar)','backwpup').'</option>';
if (function_exists('gzopen'))
	echo '<option value=".tar.gz"'.selected('.tar.gz',$jobs[$jobid]['fileformart'],false).'>'.__('TAR GZIP (.tar.gz)','backwpup').'</option>';
if (function_exists('bzopen'))
	echo '<option value=".tar.bz2"'.selected('.tar.bz2',$jobs[$jobid]['fileformart'],false).'>'.__('TAR BZIP2 (.tar.bz2)','backwpup').'</option>';
echo '</select><br />';
?>
</td> 
</tr>

<tr valign="top">
<?PHP if (empty($jobs[$jobid]['backupdir'])) $jobs[$jobid]['backupdir']=str_replace('\\','/',WP_CONTENT_DIR).'/backwpup/';?>
<th scope="row"><label for="backupdir"><?PHP _e('Save Backups to directory','backwpup'); ?></label></th> 
<td><input name="backupdir" id="backupdir" type="text" value="<?PHP echo $jobs[$jobid]['backupdir'];?>" class="regular-text" /><span class="description"><?PHP _e('Full Path of Folder for Backup Files','backwpup'); ?></span></td> 
</tr>

<tr valign="top">
<th scope="row"><label for="maxbackups"><?PHP _e('Max. Number of Backup Files','backwpup'); ?></label></th> 
<td>
<input name="maxbackups" id="maxbackups" type="text" value="<?PHP echo $jobs[$jobid]['maxbackups'];?>" class="small-text" /><span class="description"><?PHP _e('0=off','backwpup');?> <?PHP _e('Oldest files will deleted first.','backwpup');?></span>
</td> 
</tr>

<tr valign="top">
<th scope="row"><label for="ftptransfer"><?PHP _e('Copy Backup to FTP Server','backwpup'); ?></label></th> 
<td id="ftptransfer">
<?PHP _e('Ftp Hostname:','backwpup'); ?><input name="ftphost" type="text" value="<?PHP echo $jobs[$jobid]['ftphost'];?>" class="regular-text" /><br />
<?PHP _e('Ftp Username:','backwpup'); ?><input name="ftpuser" type="text" value="<?PHP echo $jobs[$jobid]['ftpuser'];?>" class="user" /><br />
<?PHP _e('Ftp Password:','backwpup'); ?><input name="ftppass" type="password" value="<?PHP echo base64_decode($jobs[$jobid]['ftppass']);?>" class="password" /><br />
<?PHP _e('Ftp directory:','backwpup'); ?><input name="ftpdir" type="text" value="<?PHP echo $jobs[$jobid]['ftpdir'];?>" class="regular-text" /><br />
<?PHP _e('Max Backup files on ftp:','backwpup'); ?><input name="ftpmaxbackups" type="text" value="<?PHP echo $jobs[$jobid]['ftpmaxbackups'];?>" class="small-text" /><span class="description"><?PHP _e('0=off','backwpup');?></span><br />
</td> 
</tr>

<?PHP if (extension_loaded('curl') or @dl(PHP_SHLIB_SUFFIX == 'so' ? 'curl.so' : 'php_curl.dll')) {?>
<tr valign="top">
<th scope="row"><label for="ftptransfer"><?PHP _e('Backup to Amazon S3','backwpup'); ?></label></th> 
<td id="ftptransfer">
<?PHP _e('Access Key ID:','backwpup'); ?><input name="awsAccessKey" type="text" value="<?PHP echo $jobs[$jobid]['awsAccessKey'];?>" class="regular-text" /><br />
<?PHP _e('Secret Access Key:','backwpup'); ?><input name="awsSecretKey" type="text" value="<?PHP echo $jobs[$jobid]['awsSecretKey'];?>" class="regular-text" /><br />
<input class="checkbox" value="1" type="checkbox" <?php checked($jobs[$jobid]['awsSSL'],true); ?> name="awsSSL" /> <?PHP _e('Use SSL connection.','backwpup'); ?><br />
<?PHP _e('Bucket:','backwpup'); ?><input name="awsBucket" type="text" value="<?PHP echo $jobs[$jobid]['awsBucket'];?>" class="regular-text" /><br />
<?PHP _e('Directory in Bucket:','backwpup'); ?><input name="awsdir" type="text" value="<?PHP echo $jobs[$jobid]['awsdir'];?>" class="regular-text" /><br />
<?PHP _e('Max Backup files on Bucket:','backwpup'); ?><input name="awsmaxbackups" type="text" value="<?PHP echo $jobs[$jobid]['awsmaxbackups'];?>" class="small-text" /><span class="description"><?PHP _e('0=off','backwpup');?></span><br />
</td> 
</tr>
<?PHP } ?>

<tr valign="top">
<th scope="row"><label for="mailaddress"><?PHP _e('Send Backup with Mail to','backwpup'); ?></label></th> 
<td><input name="mailaddress" id="mailaddress" type="text" value="<?PHP echo $jobs[$jobid]['mailaddress'];?>" class="regular-text" /><br />
<?PHP 
echo __('Max File Size for sending Backups with mail:','backwpup').'<input name="mailefilesize" type="text" value="'.$jobs[$jobid]['mailefilesize'].'" class="small-text" />MB <span class="description">'.__('0=send log only.','backwpup').'</span><br />';
?>
</td> 
</tr>
<?PHP } ?>

<tr valign="top">
<th scope="row"><label for="mailaddresslog"><?PHP _e('Send Log Mail to','backwpup'); ?></label></th> 
<td><input name="mailaddresslog" id="mailaddresslog" type="text" value="<?PHP echo $jobs[$jobid]['mailaddresslog'];?>" class="regular-text" /><br />
<input class="checkbox" value="1" type="checkbox" <?php checked($jobs[$jobid]['mailerroronly'],true); ?> name="mailerroronly" /> <?PHP _e('Send only mail on errors.','backwpup'); ?>
</td> 
</tr>


</table>
<p class="submit"> 
<input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Changes', 'backwpup'); ?>" /> 
</p> 
</form>
</div>