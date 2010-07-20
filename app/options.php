<?PHP 
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');
?>
<div class="wrap">
	<div id="icon-tools" class="icon32"><br /></div>
<h2><?php _e("BackWPup", "backwpup"); ?>&nbsp;<a href="<?PHP echo wp_nonce_url('admin.php?page=BackWPup&action=edit&jobid=0', 'edit-job'); ?>" class="button add-new-h2"><?php esc_html_e('Add New'); ?></a></h2>
<ul class="subsubsub"> 
<li><a href="admin.php?page=BackWPup" class="current"><?PHP _e('Jobs','backwpup'); ?></a> |</li> 
<li><a href="admin.php?page=BackWPup&amp;action=logs"><?PHP _e('Logs','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=backups"><?PHP _e('Backups','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=tools"><?PHP _e('Tools','backwpup'); ?></a> |</li>
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

<table class="widefat" cellspacing="0">
	<thead>
	<tr>
<?php print_column_headers($page_hook); ?>
	</tr>
	</thead>
	
	<tfoot>
	<tr>
<?php print_column_headers($page_hook, false); ?>
	</tr>
	</tfoot>	
 
	<tbody id="the-list" class="list:post"> 
<?php
	$item_columns = get_column_headers($page_hook);
	$hidden = get_hidden_columns($page_hook);
	
	if (is_array($jobs)) { 
		foreach ($jobs as $jobid => $jobvalue) {
		?><tr id="job-<?PHP echo $jobid;?>" valign="top"><?PHP 
		foreach($item_columns as $column_name=>$column_display_name) {
			$class = "class=\"column-$column_name\"";

			$style = '';
			if ( in_array($column_name, $hidden) )
				$style = ' style="display:none;"';

			$attributes = "$class$style";
			
			switch($column_name) {
				case 'cb':
					echo '<th scope="row" class="check-column"><input type="checkbox" name="jobs[]" value="'. esc_attr($jobid) .'" /></th>';
					break;
				case 'id':
					echo "<td $attributes>".$jobid."</td>"; 
					break;
				case 'jobname':
					echo "<td $attributes><strong><a href=\"".wp_nonce_url('admin.php?page=BackWPup&action=edit&jobid='.$jobid, 'edit-job')."\" title=\"".__('Edit:','backwpup').$jobvalue['name']."\">".esc_html($jobvalue['name'])."</a></strong>";
					$actions = array();
					$actions['edit'] = "<a href=\"" . wp_nonce_url('admin.php?page=BackWPup&action=edit&jobid='.$jobid, 'edit-job') . "\">" . __('Edit') . "</a>";
					$actions['copy'] = "<a href=\"" . wp_nonce_url('admin.php?page=BackWPup&action=copy&jobid='.$jobid, 'copy-job_'.$jobid) . "\">" . __('Copy','backwpup') . "</a>";
					$actions['delete'] = "<a class=\"submitdelete\" href=\"" . wp_nonce_url('admin.php?page=BackWPup&action=delete&jobid='.$jobid, 'delete-job_'.$jobid) . "\" onclick=\"if ( confirm('" . esc_js(__("You are about to delete this Job. \n  'Cancel' to stop, 'OK' to delete.","backwpup")) . "') ) { return true;}return false;\">" . __('Delete') . "</a>";
					$actions['runnow'] = "<a class=\"submitdelete\" href=\"" . wp_nonce_url('admin.php?page=BackWPup&action=runnow&jobid='.$jobid, 'runnow-job_'.$jobid) . "\">" . __('Run Now','backwpup') . "</a>";
					$action_count = count($actions);
					$i = 0;
					echo '<br /><div class="row-actions">';
					foreach ( $actions as $action => $linkaction ) {
						++$i;
						( $i == $action_count ) ? $sep = '' : $sep = ' | ';
						echo "<span class='$action'>$linkaction$sep</span>";
					}
					echo '</div>';
					echo '</td>';
					break;
				case 'type':
					echo "<td $attributes>";
					backwpup_backup_types($jobvalue['type'],true);
					echo "</td>";
					break;	
				case 'next':
					echo "<td $attributes>";
					if ($jobvalue['starttime']>0 and empty($jobvalue['stoptime'])) {
						$runtime=current_time('timestamp')-$jobvalue['starttime'];
						echo __('Running since:','backwpup').' '.$runtime.' '.__('sec.','backwpup');
					} elseif ($time=wp_next_scheduled('backwpup_cron',array('jobid'=>$jobid))) {
						echo date_i18n(get_option('date_format'),$time); ?><br /><?PHP echo date_i18n(get_option('time_format'),$time);
					} else {
						_e('Inactive','backwpup');
					}
					echo "</td>";
					break;
				case 'last':
					echo "<td $attributes>";
					if ($jobvalue['lastrun']) {
						echo date_i18n(get_option('date_format'),$jobvalue['lastrun']); ?><br /><?PHP echo date_i18n(get_option('time_format'),$jobvalue['lastrun']); 
						if (isset($jobvalue['lastruntime']))
							echo '<br />'.__('Runtime:','backwpup').' '.$jobvalue['lastruntime'].' '.__('sec.','backwpup');
					} else {
						_e('None','backwpup');
					}
					echo "</td>";
					break;
			}
		}
		echo "\n    </tr>\n";
		}
	}
	?></tbody> 
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
 
</div>