<?PHP 
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');
?>
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
 
<table class="widefat" cellspacing="0">
	<thead>
	<tr>
<?php print_column_headers('backwpup_options_logs'); ?>
	</tr>
	</thead>
	
	<tfoot>
	<tr>
<?php print_column_headers('backwpup_options_logs', false); ?>
	</tr>
	</tfoot>	
 
	<tbody id="the-list" class="list:post"> 
	
<?php
	$item_columns = get_column_headers('backwpup_options_logs');
	$hidden = get_hidden_columns('backwpup_options_logs');
	
	//get log files
	$logfiles=array();
	if ( $dir = opendir( $cfg['dirlogs'] ) ) {
		while (($file = readdir( $dir ) ) !== false ) {
			if (is_file($cfg['dirlogs'].'/'.$file) and 'backwpup_log_' == substr($file,0,strlen('backwpup_log_')) and  '.html' == substr($file,-5))
				$logfiles[]=$file;
		}
		closedir( $dir );
		rsort($logfiles);
	}
	

	foreach ($logfiles as $logfile) {
		$logdata=backwpup_read_logheader($cfg['dirlogs'].'/'.$logfile);
		?><tr id="<?PHP echo $logfile?>" valign="top"><?PHP
		foreach($item_columns as $column_name=>$column_display_name) {
			$class = "class=\"column-$column_name\"";

			$style = '';
			if ( in_array($column_name, $hidden) )
				$style = ' style="display:none;"';

			$attributes = "$class$style";
			
			switch($column_name) {
				case 'cb':
					echo '<th scope="row" class="check-column"><input type="checkbox" name="logfiles[]" value="'. esc_attr($logfile) .'" /></th>';
					break;
				case 'id':
					echo '<td $attributes>'.$logdata['jobid'].'</td>'; 
					break;
				case 'type':
					echo '<td $attributes>';
					echo $logdata['type'];
					echo '</td>'; 
					break;
				case 'log':
					$name='';
					if (is_file($logvalue['backupfile']))
						$name=basename($logvalue['backupfile']);
				
					echo '<td $attributes><strong><a href="'.wp_nonce_url('admin.php?page=BackWPup&action=view_log&logfile='.$cfg['dirlogs'].'/'.$logfile, 'view-log').'" title="'.__('View log','backwpup').'">'.date_i18n(get_option('date_format'),$logdata['logtime']).' '.date_i18n(get_option('time_format'),$logdata['logtime']).': <i>'.$logdata['name'].'</i></a></strong>';
					$actions = array();
					$actions['view'] = "<a href=\"" . wp_nonce_url('admin.php?page=BackWPup&action=view_log&logfile='.$cfg['dirlogs'].'/'.$logfile, 'view-log') . "\">" . __('View','backwpup') . "</a>";
					$actions['delete'] = "<a class=\"submitdelete\" href=\"" . wp_nonce_url('admin.php?page=BackWPup&action=delete-logs&logfile='.$logfile, 'delete-log_'.$logfile) . "\" onclick=\"if ( confirm('" . esc_js(__("You are about to delete this Job. \n  'Cancel' to stop, 'OK' to delete.","backwpup")) . "') ) { return true;}return false;\">" . __('Delete') . "</a>";
					if (!empty($logdata['backupfile']) and is_file($logdata['backupfile']))
						$actions['download'] = "<a class=\"submitdelete\" href=\"" . wp_nonce_url('admin.php?page=BackWPup&action=download&file='.$logdata['backupfile'], 'download-backup') . "\">" . __('Download','backwpup') . "</a>";
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
				case 'status':
					echo '<td $attributes>';
					if($logdata['errors']>0 or $logdata['warnings']>0) { 
						if ($logdata['errors']>0)
							echo '<span style="color:red;">'.$logdata['errors'].' '.__('ERROR(S)','backwpup').'</span><br />'; 
						if ($logdata['warnings']>0)
							echo '<span style="color:yellow;">'.$logdata['warnings'].' '.__('WARNING(S)','backwpup').'</span>'; 
					} else { 
						echo '<span style="color:green;">'.__('OK','backwpup').'</span>';
					} 
					echo '</td>'; 
					break;
				case 'size':
					echo '<td $attributes>';
					if (!empty($logdata['backupfile']) and is_file($logdata['backupfile'])) {
						echo backwpup_formatBytes(filesize($logdata['backupfile']));
					} elseif (!empty($logdata['backupfile'])) {
						_e('File not exists','backwpup');
					} else {
						_e('only Log','backwpup');
					}
					echo '</td>'; 
					break;
				case 'runtime':
					echo '<td $attributes>';
					echo $logdata['runtime'].' '.__('sec.','backwpup');
					echo '</td>'; 
					break;					
			}
		}
		echo "\n    </tr>\n";
	}
	?></tbody> 
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
 
</div>