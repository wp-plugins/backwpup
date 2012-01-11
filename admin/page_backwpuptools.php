<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

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
<h3><?PHP _e('Database restore','backwpup'); ?></h3> 
<table class="form-table"> 
<tr valign="top">
<th scope="row"><?PHP _e('DB Restore','backwpup'); ?></th>
<td>
<?PHP
if (!file_exists(ABSPATH.'backwpup_db_restore.php') and is_writeable(ABSPATH)) {
	_e('Download manually DB restore tool: <a href="http://api.backwpup.com/download/backwpup_db_restore.zip">http://api.backwpup.com/download/backwpup_db_restore.zip</a>','backwpup');
	echo '<br />';
	echo '<input type="submit" name="dbrestoretool" class="button-primary" value="'.__('Put DB restore tool to blog root...', 'backwpup').'" /><br />';
}
elseif(is_writeable(ABSPATH)) {
	echo '<input type="submit" name="dbrestoretooldel" class="button-primary" value="'.__('Delete restore tool from blog root...', 'backwpup').'" /><br />';
	echo sprintf(__('Make a DB restore:  <a href="%1$s/backwpup_db_restore.php">%1$s/backwpup_db_restore.php</a>', 'backwpup'),get_bloginfo('url')).' <br />';
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
	$jobids=$wpdb->get_col("SELECT value FROM `".$wpdb->prefix."backwpup` WHERE main LIKE 'job_%' AND name='jobid' ORDER BY value ASC");
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
	$import=maybe_unserialize(trim(urldecode($_POST['importfile'])));
	foreach ( $_POST['importtype'] as $id => $type ) {
		if ($type=='over')
			$import[$id]['jobid']=$id;
		if ($type=='append') {
			$import[$id]['jobid']=$wpdb->get_var("SELECT value FROM `".$wpdb->prefix."backwpup` WHERE main LIKE 'job_%' AND name='jobid' ORDER BY value DESC LIMIT 1",0,0);
			$import[$id]['jobid']++;
		}
		$import[$id]['activetype']='';
		unset($import[$id]['cronnextrun']);
		unset($import[$id]['starttime']);
		unset($import[$id]['logfile']);
		unset($import[$id]['lastlogfile']);
		unset($import[$id]['lastrun']);
		unset($import[$id]['lastruntime']);
		unset($import[$id]['lastbackupdownloadurl']);
		foreach ($import[$id] as $jobname => $jobvalue)
			backwpup_update_option('job_'.$import[$id]['jobid'],$jobname,$jobvalue);
	}
	_e('Jobs imported!', 'backwpup');
}
echo '</td>';
?>
</tr>
</table>

	<h3><?PHP _e('Test max. script execution time','backwpup'); ?></h3>
	<table class="form-table">
		<tr valign="top">
			<th scope="row"><?PHP _e('Test result:','backwpup'); ?></th>
			<td>
				<?PHP
				$times=backwpup_get_option('temp','exectime');
				if (empty($times['starttime']) or empty($times['lasttime'])) {
					_e('No result');
					echo "<br /><input type=\"submit\" name=\"executiontime\" class=\"button-primary\" value=\"".__('Start time test...', 'backwpup')."\" />";
				}
				elseif ($times['lasttime']<=current_time('timestamp')-5) {
					$exectime=$times['lasttime']-$times['starttime'];
					echo '<span>'.sprintf(__('%d sec.','backwpup'),$exectime).' </span><br />';
					echo "<input type=\"submit\" name=\"executiontime\" class=\"button\" value=\"".__('Start time test...', 'backwpup')."\" />";
					echo "<input type=\"submit\" name=\"executionsave\" class=\"button-primary\" value=\"".__('Save to config!', 'backwpup')."\" />";
				}
				else {
					$exectime=$times['lasttime']-$times['starttime'];
					echo '<span>'.sprintf(__('%d sec.','backwpup'),$exectime).' </span> <blink><strong>'.__('In progress').'</strong></blink><br />';
					echo "<input type=\"submit\" name=\"executionstop\" class=\"button\" value=\"".__('Terminate time test!', 'backwpup')."\" />";
				}
				?>
			</td>
		</tr>
	</table>


</form>
</div>