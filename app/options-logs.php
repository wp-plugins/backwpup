<div class="wrap">
	<div id="icon-tools" class="icon32"><br /></div>
<h2><?php _e('BackWPup Logs', 'backwpup'); ?></h2>
<ul class="subsubsub"> 
<li><a href="admin.php?page=BackWPup"><?PHP _e('Jobs','backwpup'); ?></a> |</li> 
<li><a href="admin.php?page=BackWPup&amp;action=logs" class="current"><?PHP _e('Logs','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=tools"><?PHP _e('Tools','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=settings"><?PHP _e('Settings','backwpup'); ?></a></li>
</ul>

<form id="logs-filter" action="" method="post">
<?php wp_nonce_field('actions-logs'); ?>
<input type="hidden" name="page" value="BackWPup" />
<div class="tablenav"> 
 
<div class="alignleft actions"> 
<select name="action" class="select-action"> 
<option value="-1" selected="selected"><?PHP _e('Bulk Actions','backwpup'); ?></option> 
<option value="delete-logs"><?PHP _e('Delete','backwpup'); ?></option> 
</select> 
<input type="submit" value="<?PHP _e('Apply','backwpup'); ?>" name="doaction" id="doaction" class="button-secondary action" /> 
</div> 
 
<br class="clear" /> 
</div> 
 
<div class="clear"></div> 
 
<table class="widefat fixed" cellspacing="0"> 
	<thead> 
	<tr> 
	<th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox" /></th> 
	<th scope="col" id="id" class="manage-column column-id" style=""><?PHP _e('Job','backwpup'); ?></th> 
	<th scope="col" id="type" class="manage-column column-type" style=""><?PHP _e('Type','backwpup'); ?></th> 
	<th scope="col" id="log" class="manage-column column-log" style=""><?PHP _e('Backup/Log Date/Time','backwpup'); ?></th> 
	<th scope="col" id="size" class="manage-column column-status" style=""><?PHP _e('Status','backwpup'); ?></th> 
	<th scope="col" id="size" class="manage-column column-size" style=""><?PHP _e('Size','backwpup'); ?></th> 
	<th scope="col" id="logdate" class="manage-column column-runtime" style=""><?PHP _e('Runtime','backwpup'); ?></th> 
	</tr> 
	</thead> 
 
	<tfoot> 
	<tr> 
	<th scope="col" class="manage-column column-cb check-column" style=""><input type="checkbox" /></th> 
	<th scope="col" class="manage-column column-id" style=""><?PHP _e('Job','backwpup'); ?></th> 
	<th scope="col" class="manage-column column-type" style=""><?PHP _e('Type','backwpup'); ?></th> 
	<th scope="col" class="manage-column column-log" style=""><?PHP _e('Backup/Log Date/Time','backwpup'); ?></th>
	<th scope="col" class="manage-column column-status" style=""><?PHP _e('Status','backwpup'); ?></th> 
	<th scope="col" class="manage-column column-size" style=""><?PHP _e('Size','backwpup'); ?></th> 
	<th scope="col" class="manage-column column-runtime" style=""><?PHP _e('Runtime','backwpup'); ?></th> 
	</tr> 
	</tfoot> 
 
	<tbody id="the-list" class="list:post"> 
	
	<?PHP 
		$logs=$wpdb->get_results("SELECT * FROM ".$wpdb->backwpup_logs." ORDER BY logtime DESC", ARRAY_A);
		if (is_array($logs)) { 
		foreach ($logs as $logvalue) {?>
	<tr id="post-16" class="alternate author-self status-inherit" valign="top"> 
		<th scope="row" class="check-column">
			<input type="checkbox" name="logs[]" value="<?PHP echo $logvalue['logtime']?>" />
		</th> 
		<td class="column-id"><?PHP echo $logvalue['jobid'];?></td> 
		<td class="column-type">
		<?PHP
			BackWPupFunctions::backup_types($logvalue['type'],true);
		?>
		</td> 
		<td class="name column-log">
					<?php
					$name='';
					if (is_file($logvalue['backupfile']))
						$name=basename($logvalue['backupfile']);
					?>
					<strong><a href="<?PHP echo wp_nonce_url('admin.php?page=BackWPup&action=view_log&logtime='.$logvalue['logtime'], 'view-log'); ?>" title="<?PHP _e('View log','backwpup'); ?>"><?PHP echo date(get_option('date_format'),$logvalue['logtime']); ?> <?PHP echo date(get_option('time_format'),$logvalue['logtime']); ?><?php if (!empty($logvalue['jobname'])) echo ': <i>'.$logvalue['jobname'].'</i>';?></a></strong>
					<p><div class="row-actions">
						<span class="view"><a href="<?PHP echo wp_nonce_url('admin.php?page=BackWPup&action=view_log&logtime='.$logvalue['logtime'], 'view-log'); ?>"><?PHP _e('View','backwpup'); ?></a></span>
						<span class="delete"> | <a class="submitdelete" href="<?PHP echo wp_nonce_url('admin.php?page=BackWPup&action=delete-logs&log='.$logvalue['logtime'], 'delete-log_'.$logvalue['logtime']); ?>" onclick="if ( confirm('<?PHP echo esc_js(__("You are about to delete this Log and Backupfile. \n  'Cancel' to stop, 'OK' to delete.","backwpup")) ?>') ){return true;}return false;"><?PHP _e('Delete','backwpup'); ?></a></span>
						<?PHP if (!empty($logvalue['backupfile']) and is_file($logvalue['backupfile'])) { ?>
							<span class="download"> | <a href="<?PHP echo wp_nonce_url('admin.php?page=BackWPup&action=download&log='.$logvalue['logtime'], 'download-backup_'.$logvalue['logtime']); ?>"><?PHP _e('Download','backwpup'); ?></a></span>
						<?PHP } ?>
					</div></p>
		</td> 
		<td class="column-status">
		<strong>
		<?PHP
		if($logvalue['error']>0 or $logvalue['warning']>0) { 
			if ($logvalue['error']>0)
				echo '<span style="color:red;">'.$logvalue['error'].' '.__('ERROR(S)','backwpup').'</span><br />'; 
			if ($logvalue['warning']>0)
				echo '<span style="color:yellow;">'.$logvalue['warning'].' '.__('WARNING(S)','backwpup').'</span>'; 
		} else { 
			echo '<span style="color:green;">'.__('OK','backwpup').'</span>';
		} 
		?>
		</strong>
		</td> 
		<td class="column-size">
		<?PHP
			if (!empty($logvalue['backupfile']) and is_file($logvalue['backupfile'])) {
				echo BackWPupFunctions::formatBytes(filesize($logvalue['backupfile']));
			} else {
				_e('only Log','backwpup');
			}
		?>
		</td> 
		<td class="column-runtime">
		<?PHP
			echo $logvalue['worktime'].' '.__('sec.','backwpup');
		?>
		</td> 
	</tr>
	<?PHP }}?>
	</tbody> 
</table> 
  
<div class="tablenav"> 
<div class="alignleft actions"> 
<select name="action2" class="select-action"> 
<option value="-1" selected="selected"><?PHP _e('Bulk Actions','backwpup'); ?></option> 
<option value="delete-logs"><?PHP _e('Delete','backwpup'); ?></option> 
</select> 
<input type="submit" value="<?PHP _e('Apply','backwpup'); ?>" name="doaction2" id="doaction2" class="button-secondary action" /> 
</div> 
 
<br class="clear" /> 
</div> 
</form> 
<br class="clear" /> 
 
<div>