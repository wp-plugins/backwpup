<div class="wrap">
	<div id="icon-tools" class="icon32"><br /></div>
<h2><?php _e("BackWPup", "backwpup"); ?><a href="<?PHP echo wp_nonce_url('admin.php?page=BackWPup&action=edit&jobid=0', 'edit-job'); ?>" class="button add-new-h2"><?php esc_html_e('Add New'); ?></a></h2>
<ul class="subsubsub"> 

<li><a href="admin.php?page=BackWPup" class="current"><?PHP _e('Jobs','backwpup'); ?></a> |</li> 
<li><a href="admin.php?page=BackWPup&amp;action=logs"><?PHP _e('Logs','backwpup'); ?></a> |</li> 
<li><a href="admin.php?page=BackWPup&amp;action=settings"><?PHP _e('Settings','backwpup'); ?></a></li>
</ul>

<form id="jobs-filter" action="" method="post">
<?php wp_nonce_field('actions-jobs'); ?>
<input type="hidden" name="page" value="BackWPup" />
<div class="tablenav"> 
 
<div class="alignleft actions"> 
<select name="action" class="select-action"> 
<option value="-1" selected="selected"><?PHP _e('Bulk Actions','backwpup'); ?></option> 
<option value="delete"><?PHP _e('Delete','backwpup'); ?></option> 
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
	<th scope="col" id="id" class="manage-column column-id" style=""><?PHP _e('ID','backwpup'); ?></th> 
	<th scope="col" id="name" class="manage-column column-name" style=""><?PHP _e('Name','backwpup'); ?></th> 
	<th scope="col" id="type" class="manage-column column-type" style=""><?PHP _e('Type','backwpup'); ?></th> 
	<th scope="col" id="next" class="manage-column column-next" style=""><?PHP _e('Next Run','backwpup'); ?></th> 
	<th scope="col" id="last" class="manage-column column-last" style=""><?PHP _e('Last Run','backwpup'); ?></th> 
	</tr> 
	</thead> 
 
	<tfoot> 
	<tr> 
	<th scope="col" class="manage-column column-cb check-column" style=""><input type="checkbox" /></th> 
	<th scope="col" class="manage-column column-id" style=""><?PHP _e('ID','backwpup'); ?></th> 
	<th scope="col" class="manage-column column-name" style=""><?PHP _e('Name','backwpup'); ?></th> 
	<th scope="col" class="manage-column column-type" style=""><?PHP _e('Type','backwpup'); ?></th> 
	<th scope="col" class="manage-column column-next" style=""><?PHP _e('Next Run','backwpup'); ?></th> 
	<th scope="col" class="manage-column column-last" style=""><?PHP _e('Last Run','backwpup'); ?></th> 
	</tr> 
	</tfoot> 
 
	<tbody id="the-list" class="list:post"> 
	
	<?PHP if (is_array($jobs)) { foreach ($jobs as $jobid => $jobvalue) {?>
	<tr id="post-16" class="alternate author-self status-inherit" valign="top"> 
		<th scope="row" class="check-column">
			<input type="checkbox" name="jobs[]" value="<?PHP echo $jobid;?>" />
		</th> 
		<td class="column-id"><?PHP echo $jobid;?></td> 
		<td class="name column-name">
					<strong><a href="<?PHP echo wp_nonce_url('admin.php?page=BackWPup&action=edit&jobid='.$jobid, 'edit-job'); ?>" title="<?PHP _e('Edit:','backwpup'); ?> <?PHP echo $jobvalue['name'];?>"><?PHP echo $jobvalue['name'];?></a></strong>
					<p><div class="row-actions">
						<span class="edit"><a href="<?PHP echo wp_nonce_url('admin.php?page=BackWPup&action=edit&jobid='.$jobid, 'edit-job'); ?>"><?PHP _e('Edit','backwpup'); ?></a> | </span>
						<span class="delete"><a class="submitdelete" href="<?PHP echo wp_nonce_url('admin.php?page=BackWPup&action=delete&jobid='.$jobid, 'delete-job_'.$jobid); ?>" onclick="if ( confirm('<?PHP echo esc_js(__("You are about to delete this Job. \n  'Cancel' to stop, 'OK' to delete.","backwpup")) ?>') ){return true;}return false;"><?PHP _e('Delete','backwpup'); ?></a> | </span>
						<span class="copy"><a href="<?PHP echo wp_nonce_url('admin.php?page=BackWPup&action=copy&jobid='.$jobid, 'copy-job_'.$jobid); ?>"><?PHP _e('Copy','backwpup'); ?></a> | </span>
						<span class="runnow"><a href="<?PHP echo wp_nonce_url('admin.php?page=BackWPup&action=runnow&jobid='.$jobid, 'runnow-job_'.$jobid); ?>" title="Run Now: <?PHP echo $jobvalue['name'];?>"><?PHP _e('Run Now','backwpup'); ?></a></span>
					</div></p>
		</td> 
		<td class="column-type">
		<?PHP
			switch($jobvalue['type']) {
			case 'DB+FILE':
				_e('Database &amp; File Backup','backwpup');
				break;
			case 'DB':
				_e('Database Backup','backwpup');
				break;			
			case 'FILE':
				_e('File Backup','backwpup');
				break;
			case 'OPTIMIZE':
				_e('Optimize Database Tabels','backwpup');
				break;				
			}
		?>
		</td> 
		<td class="column-next">
		<?PHP
			if ($jobvalue['starttime']>0 and empty($jobvalue['stoptime'])) {
				$runtime=time()-$jobvalue['starttime'];
				echo __('Running since:','backwpup').' '.$runtime.' '.__('sec.','backwpup');
			} elseif ($time=wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid))) {
				echo date(get_option('date_format'),$time); ?><br /><?PHP echo date(get_option('time_format'),$time);
			} else {
				_e('Inactive','backwpup');
			}		
		?>
		</td> 
		<td class="column-last">
		<?PHP
			if ($jobvalue['lastrun']) {
				echo date(get_option('date_format'),$jobvalue['lastrun']); ?><br /><?PHP echo date(get_option('time_format'),$jobvalue['lastrun']); 
				$runtime=$jobvalue['stoptime']-$jobvalue['starttime'];
				echo '<br />'.__('Runtime:','backwpup').' '.$runtime.' '.__('sec.','backwpup');
			} else {
				_e('None','backwpup');
			}
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
<option value="delete"><?PHP _e('Delete','backwpup'); ?></option> 
</select> 
<input type="submit" value="<?PHP _e('Apply','backwpup'); ?>" name="doaction2" id="doaction2" class="button-secondary action" /> 
</div> 
 
<br class="clear" /> 
</div> 
</form> 
<br class="clear" /> 
 
<div>