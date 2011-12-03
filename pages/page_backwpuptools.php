<?PHP
if (!defined('ABSPATH')) 
	die();
global $wpdb;
?>
<div class="wrap">
<?PHP
screen_icon();
echo "<h2>".esc_html( __('BackWPup Tools', 'backwpup'))."</h2>";
if (isset($backwpup_message) and !empty($backwpup_message)) 
	echo "<div id=\"message\" class=\"updated\"><p>".$backwpup_message."</p></div>";
?>
<form id="posts-filter" enctype="multipart/form-data" action="<?PHP echo backwpup_admin_url('admin.php').'?page=backwpuptools'; ?>" method="post">
<?PHP wp_nonce_field('backwpup-tools'); ?>
<input type="hidden" name="action" value="update" />
<h3><?PHP _e('Database restore','backwpup'); ?></h3> 
<table class="form-table"> 
<tr valign="top">
<th scope="row"><label for="mailsndemail"><?PHP _e('DB Restore','backwpup'); ?></label></th>
<td>
<?PHP
if (isset($_POST['dbrestore']) and $_POST['dbrestore']==__('Restore', 'backwpup') and is_file(trim($_POST['sqlfile']))) {
	check_admin_referer('backwpup-tools');
	$sqlfile=trim($_POST['sqlfile']);
	require(dirname(__FILE__).'/tools/db_restore.php');
} else {
	if ( $dir = @opendir(ABSPATH)) {
		$sqlfile="";
		while (($file = readdir( $dir ) ) !== false ) {
			if (strtolower(substr($file,-4))==".sql") {
				$sqlfile=$file;
				break;
			}	
		}
		@closedir( $dir );
	}
	if (!empty($sqlfile)) {
		echo __('SQL File to restore:','backwpup').' '.trailingslashit(ABSPATH).$sqlfile."<br />";
		?>
		<input type="hidden" class="regular-text" name="sqlfile" id="sqlfile" value="<?PHP echo trailingslashit(ABSPATH).$sqlfile;?>" />
		<input type="submit" name="dbrestore" class="button-primary" value="<?php _e('Restore', 'backwpup'); ?>" />
		<?PHP
	} else {
		echo __('Copy SQL file to blog root folder to use for a restoring.', 'backwpup')."<br />";
	}
}
?>
</td>
</tr>
</table>

<h3><?PHP _e('Import Jobs settings','backwpup'); ?></h3>
<table class="form-table"> 
<tr valign="top"> 
<th scope="row"><label for="importfile"><?php _e('Select file to import:', 'backwpup'); ?></label></th> 
<td><input name="importfile" type="file" id="importfile" class="regular-text code" /> 
<input type="submit" name="upload" class="button-primary" value="<?php _e('Upload', 'backwpup'); ?>" />
</td> 
</tr>
<tr valign="top"> 
<?PHP
if (isset($_POST['upload']) and is_uploaded_file($_FILES['importfile']['tmp_name']) and $_POST['upload']==__('Upload', 'backwpup')) {
	echo "<th scope=\"row\"><label for=\"maxlogs\">".__('Select jobs to import','backwpup')."</label></th><td>";
	$import=file_get_contents($_FILES['importfile']['tmp_name']);
	$jobids=$wpdb->get_col("SELECT value FROM `".$wpdb->prefix."backwpup` WHERE main_name LIKE 'job_%' AND name='jobid' ORDER BY value DESC");
	foreach ( unserialize($import) as $jobid => $jobvalue ) {
		echo "<select name=\"importtype[".$jobid."]\" title=\"".__('Import Type', 'backwpup')."\"><option value=\"not\">".__('No Import', 'backwpup')."</option>";
		if (in_array($jobid,$jobids))
			echo "<option value=\"over\">".__('Overwrite', 'backwpup')."</option><option value=\"append\">".__('Append', 'backwpup')."</option>"; 
		else
			echo "<option value=\"over\">".__('Import', 'backwpup')."</option>";
		echo "</select>";
		echo '&nbsp;<span class="description">'.$jobid.". ".$jobvalue['name'].'</span><br />';
	}
	echo "<input type=\"hidden\" name=\"importfile\" value=\"".urlencode($import)."\" />";
	echo "<input type=\"submit\" name=\"import\" class=\"button-primary\" value=\"".__('Import', 'backwpup')."\" />";
}
if (isset($_POST['import']) and $_POST['import']==__('Import', 'backwpup') and !empty($_POST['importfile'])) {
	echo "<th scope=\"row\"><label for=\"maxlogs\">".__('Import','backwpup')."</label></th><td>";
	$import=unserialize(trim(urldecode($_POST['importfile'])));
	foreach ( $_POST['importtype'] as $id => $type ) {
		if ($type=='over') {
			$import[$id]['jobid']=$id;
			$import[$id]['activated']=false;
			$import[$id]['cronnextrun']='';
			$import[$id]['starttime']='';
			$import[$id]['logfile']='';
			$import[$id]['lastlogfile']='';
			$import[$id]['lastrun']='';
			$import[$id]['lastruntime']='';
			$import[$id]['lastbackupdownloadurl']='';
			//delte old
			$wpdb->query($wpdb->prepare("DELETE FROM ".$wpdb->prefix."backwpup WHERE main_name=%s",'job_'.$id));
			//save
			foreach ($import[$id] as $jobvaluename => $jobvaluevalue) {
				backwpup_update_option('job_'.$import[$id]['jobid'],$jobvaluename,$jobvaluevalue);
			}					
		} elseif ($type=='append') {
			unset($import[$id]['jobid']);
			$import[$id]['activated']=false;
			$import[$id]['cronnextrun']='';
			$import[$id]['starttime']='';
			$import[$id]['logfile']='';
			$import[$id]['lastlogfile']='';
			$import[$id]['lastrun']='';
			$import[$id]['lastruntime']='';
			$import[$id]['lastbackupdownloadurl']='';
			//save
			$jobvalues=backwpup_get_job_vars(0,$import[$id]);
			foreach ($jobvalues as $jobvaluename => $jobvaluevalue) {
				backwpup_update_option('job_'.$jobvalues['jobid'],$jobvaluename,$jobvaluevalue);
			}	
		} 
	}
	_e('Jobs imported!', 'backwpup');
}
echo '</td>';
?>
</tr>
</table>

</form>
</div>