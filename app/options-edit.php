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


if ($jobs[$jobid]['type']=='OPTIMIZE') {
	echo '<input type="hidden" name="backupdir" value="'.$jobs[$jobid]['backupdir'].'" />';
	echo '<input type="hidden" name="maxbackups" value="'.$jobs[$jobid]['maxbackups'].'" />';
}
if ($jobs[$jobid]['type']=='DB' or $jobs[$jobid]['type']=='OPTIMIZE') {
	echo '<input type="hidden" name="fileexclude" value="'.$jobs[$jobid]['fileexclude'].'" />';
}
if ($jobs[$jobid]['type']=='FILE') {
	if (is_array($jobs[$jobid]['dbexclude'])) {
		foreach ($jobs[$jobid]['dbexclude'] as $table) {
			echo '<input type="hidden" name="dbexclude[]" value="'.$table.'" />';
		}
	}
}
	
	
?>
<table class="form-table">

<tr valign="top"> 
<th scope="row"><label for="job_type"><?PHP _e('Job Type','backwpup'); ?></label></th> 
<td> 
<select name="type" id="job_type">
	<?php
	foreach (BackWPupFunctions::backup_types() as $type) {
		echo '<option value="'.$type.'"'.selected($type,$jobs[$jobid]['type'],false).'>'.BackWPupFunctions::backup_types($type).'</option>';
	}
	?>
</select>
<input type="submit" name="change" class="button" value="<?php _e('Change', 'backwpup'); ?>" /> 
</td> 
</tr> 

<tr valign="top"> 
<th scope="row"><label for="jobname"><?PHP _e('Job Name','backwpup'); ?></label></th> 
<td><input name="name" type="text" id="jobname" value="<?PHP echo $jobs[$jobid]['name'];?>" class="regular-text" /></td> 
</tr> 

<?PHP if ($jobs[$jobid]['type']=='DB' or $jobs[$jobid]['type']=='DB+FILE' or $jobs[$jobid]['type']=='OPTIMIZE') {?>
<tr valign="top"> 
<th scope="row"><label for="dbexclude"><?PHP _e('Exclude Databas Tabels:','backwpup'); ?></label></th><td> 
<?php
$tables=$wpdb->get_col('SHOW TABLES FROM `'.DB_NAME.'`');
if (!isset($jobs[$jobid]['dbexclude'])) { //def.
	foreach ($tables as $table) {
		if (substr($table,0,strlen($wpdb->prefix))!=$wpdb->prefix)
			$jobs[$jobid]['dbexclude'][]=$table;
	}
}
foreach ($tables as $table) {
	echo ' <input class="checkbox" type="checkbox"'.checked(in_array($table,(array)$jobs[$jobid]['dbexclude']),true,false).' name="dbexclude[]" value="'.$table.'"/>'.$table;
}

?>
</td></tr>
<?PHP } ?>
<?PHP if ($jobs[$jobid]['type']=='FILE' or $jobs[$jobid]['type']=='DB+FILE') {?>
<?PHP if (!isset($jobs[$jobid]['backuproot'])) $jobs[$jobid]['backuproot']=true; if (!isset($jobs[$jobid]['backupcontent'])) $jobs[$jobid]['backupcontent']=true; if (!isset($jobs[$jobid]['backupplugins'])) $jobs[$jobid]['backupplugins']=true;?>
<tr valign="top"> 
<th scope="row"><label for="fileinclude"><?PHP _e('Backup Blog dirs','backwpup'); ?></label></th><td> 
<input class="checkbox" type="checkbox"<?php checked($jobs[$jobid]['backuproot'],true,true);?> name="backuproot" value="1"/> <?php _e('Blog root and WP Files','backwpup');?><br />
<input class="checkbox" type="checkbox"<?php checked($jobs[$jobid]['backupcontent'],true,true);?> name="backupcontent" value="1"/> <?php _e('Blog Content','backwpup');?><br />
<input class="checkbox" type="checkbox"<?php checked($jobs[$jobid]['backupplugins'],true,true);?> name="backupplugins" value="1"/> <?php _e('Blog Plugins','backwpup');?>
</td></tr> 

<tr valign="top"> 
<th scope="row"><label for="dirinclude"><?PHP _e('Include extra dirs','backwpup'); ?></label></th><td> 
<input name="dirinclude" type="text" value="<?PHP echo $jobs[$jobid]['dirinclude'];?>" class="regular-text" /><span class="description"><?PHP echo __('Separate with ,. Full Path like:','backwpup').' '.str_replace('\\','/',ABSPATH); ?></span>
</td></tr> 

<tr valign="top"> 
<th scope="row"><label for="fileexclude"><?PHP _e('Exclude files/dirs','backwpup'); ?></label></th><td> 
<input name="fileexclude" type="text" value="<?PHP echo $jobs[$jobid]['fileexclude'];?>" class="regular-text" /><span class="description"><?PHP _e('Separate with ,','backwpup') ?></span>
</td></tr> 
<?PHP } ?>

<tr valign="top"> 
<th scope="row"><label for="jobname"><?PHP _e('Schedule','backwpup'); ?></label></th> 
<td>
<span class="description"><?php _e('Run Every:', 'backwpup'); ?></span>
<?PHP
echo '<select name="scheduleintervalteimes">';
for ($i=1;$i<=60;$i++) {
	echo '<option value="'.$i.'"'.selected($i,$jobs[$jobid]['scheduleintervalteimes'],false).'>'.$i.'</option>';
}
echo '</select>';
if (empty($jobs[$jobid]['scheduleintervaltype']))
	$jobs[$jobid]['scheduleintervaltype']=3600;
echo '<select name="scheduleintervaltype">';
echo '<option value="60"'.selected('3600',$jobs[$jobid]['scheduleintervaltype'],false).'>'.__('Min(s)','backwpup').'</option>';
echo '<option value="3600"'.selected('3600',$jobs[$jobid]['scheduleintervaltype'],false).'>'.__('Houer(s)','backwpup').'</option>';
echo '<option value="86400"'.selected('86400',$jobs[$jobid]['scheduleintervaltype'],false).'>'.__('Day(s)','backwpup').'</option>';
echo '</select>';
?>
<br />
<span class="description"><?php _e('Start Time:', 'backwpup'); ?></span>
<?PHP
if (empty($jobs[$jobid]['scheduletime']))
	$jobs[$jobid]['scheduletime']=time();

echo '<select name="schedulehour">';
for ($i=0;$i<=23;$i++) {
	echo '<option value="'.$i.'"'.selected($i,date('G',$jobs[$jobid]['scheduletime']),false).'>'.$i.'</option>';
}
echo '</select>:';
echo '<select name="scheduleminute">';
for ($i=0;$i<=59;$i++) {
	echo '<option value="'.$i.'"'.selected($i,date('i',$jobs[$jobid]['scheduletime']),false).'>'.$i.'</option>';
}
echo '</select>&nbsp;';
?><span class="description"><?php _e('Start Date:', 'backwpup'); ?></span><?PHP
echo '<select name="scheduleday">';
for ($i=1;$i<=31;$i++) {
	echo '<option value="'.$i.'"'.selected($i,date('j',$jobs[$jobid]['scheduletime']),false).'>'.$i.'</option>';
}
echo '</select>.';
$month=array('1'=>__('January'),'2'=>__('February'),'3'=>__('March'),'4'=>__('April'),'5'=>__('May'),'6'=>__('June'),'7'=>__('July'),'8'=>__('August'),'9'=>__('September'),'10'=>__('October'),'11'=>__('November'),'12'=>__('December'));
echo '<select name="schedulemonth">';
for ($i=1;$i<=12;$i++) {
	echo '<option value="'.$i.'"'.selected($i,date('n',$jobs[$jobid]['scheduletime']),false).'>'.$month[$i].'</option>';
}
echo '</select>.';
echo '<select name="scheduleyear">';
for ($i=date('Y')-1;$i<=date('Y')+3;$i++) {
	echo '<option value="'.$i.'"'.selected($i,date('Y',$jobs[$jobid]['scheduletime']),false).'>'.$i.'</option>';
}
echo '</select>';
?>
<br />
<span class="description"><?php _e('Activate:', 'backwpup'); ?></span>
<input class="checkbox" value="1" type="checkbox" <?php checked($jobs[$jobid]['activated'],true); ?> name="activated" />
</td> 
</tr> 

<?PHP if ($jobs[$jobid]['type']=='FILE' or $jobs[$jobid]['type']=='DB' or $jobs[$jobid]['type']=='DB+FILE') {?>
<tr valign="top">
<?PHP if (empty($jobs[$jobid]['backupdir'])) $jobs[$jobid]['backupdir']=str_replace('\\','/',WP_CONTENT_DIR).'/backwpup/';?>
<th scope="row"><label for="backupdir"><?PHP _e('Backup to Directory','backwpup'); ?></label></th> 
<td><input name="backupdir" type="text" value="<?PHP echo $jobs[$jobid]['backupdir'];?>" class="regular-text" /><span class="description"><?PHP _e('Full Phath of Directory for Backup fiels','backwpup'); ?></span></td> 
</tr>
<tr valign="top">
<th scope="row"><label for="maxbackups"><?PHP _e('Max number of Backup Files','backwpup'); ?></label></th> 
<td>
<?PHP 
echo '<select name="maxbackups">';
echo '<option value="0"'.selected(0,$jobs[$jobid]['maxbackups'],false).'>'.__('Off','backwpup').'</option>';
for ($i=1;$i<=50;$i++) {
	echo '<option value="'.$i.'"'.selected($i,$jobs[$jobid]['maxbackups'],false).'>'.$i.'</option>';
}
echo '</select> <span class="description">';
_e('Oldest files will deletet first.','backwpup');
?></span>
</td> 
</tr>
<?PHP } ?>

 
<tr valign="top">
<th scope="row"><label for="mailaddress"><?PHP _e('Send Mail to','backwpup'); ?></label></th> 
<td><input name="mailaddress" type="text" value="<?PHP echo $jobs[$jobid]['mailaddress'];?>" class="regular-text" /></td> 
</tr>

</table>
<p class="submit"> 
<input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Changes', 'backwpup'); ?>" /> 
</p> 
</form>