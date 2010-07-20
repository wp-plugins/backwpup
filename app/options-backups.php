<?PHP
// don't load directly
if ( !defined('ABSPATH') )
	die('-1');
	
//get GET data
if (isset($_GET['paged'])) $paged=$_GET['paged'];

//Get Backup files
$backups=backwpup_get_backup_files();

//Page links
$per_page = 20;

$pagenum = isset( $paged ) ? absint( $paged ) : 0;
if ( empty($pagenum) )
	$pagenum = 1;

$num_backups = count($backups);
$num_pages = ceil($num_backups / $per_page);

$page_links = paginate_links( array(
	'base' => add_query_arg('paged', '%#%'),
	'format' => '',
	'prev_text' => __('&laquo;'),
	'next_text' => __('&raquo;'),
	'total' => $num_pages,
	'current' => $pagenum
));
?>
<div class="wrap">
	<div id="icon-tools" class="icon32"><br /></div>
<h2><?php _e("BackWPup Manage Backups", "backwpup"); ?></h2>
<ul class="subsubsub">
<li><a href="admin.php?page=BackWPup"><?PHP _e('Jobs','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=logs"><?PHP _e('Logs','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=backups" class="current"><?PHP _e('Backups','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=tools"><?PHP _e('Tools','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=settings"><?PHP _e('Settings','backwpup'); ?></a></li>
</ul>

<form id="logs-filter" action="" method="post">
<?php wp_nonce_field('actions-backups'); ?>
<input type="hidden" name="page" value="BackWPup" />
<div class="tablenav">

<div class="alignleft actions">
<select name="action" class="select-action">
<option value="backups" selected="selected"><?PHP _e('Bulk Actions','backwpup'); ?></option>
<option value="delete-backup"><?PHP _e('Delete','backwpup'); ?></option>
</select>
<input type="submit" value="<?PHP _e('Apply','backwpup'); ?>" name="doaction" id="doaction" class="button-secondary action" />
</div>

<?php if ( $page_links ) { ?>
<div class="tablenav-pages"><?php $page_links_text = sprintf( '<span class="displaying-num">' . __( 'Displaying %s&#8211;%s of %s' ) . '</span>%s',
	number_format_i18n( ( $pagenum - 1 ) * $per_page + 1 ),
	number_format_i18n( min( $pagenum * $per_page, $num_backups ) ),
	number_format_i18n( $num_backups ),
	$page_links
); echo $page_links_text; ?></div>
<?php } ?>
<div class="clear"></div>
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
	$jobs=get_option('backwpup_jobs'); //Load jobs

	foreach ($backups as $backupnum => $backup) {
		if (!($backupnum>=($pagenum*$per_page-$per_page) and $backupnum<($pagenum*$per_page)))
			continue;
		$jobvalue=backwpup_check_job_vars($jobs[$backup['jobid']]); //Check job values
		?><tr id="<?PHP echo $logfile?>" valign="top"><?PHP
		foreach($item_columns as $column_name=>$column_display_name) {
			$class = "class=\"column-$column_name\"";

			$style = '';
			if ( in_array($column_name, $hidden) )
				$style = ' style="display:none;"';

			$attributes = "$class$style";

			switch($column_name) {
				case 'cb':
					echo '<th scope="row" class="check-column"><input type="checkbox" name="backupfiles[]" value="'. esc_attr($backup['file'].':'.$backup['jobid'].':'.$backup['type']) .'" /></th>';
					break;
				case 'id':
					echo "<td $attributes>".$backup['jobid']."</td>";
					break;
				case 'backup':
					if ($backup['type']=='FOLDER') {
						echo "<td $attributes><strong>".basename($backup['file'])."</strong><br />".dirname($backup['file'])."/";
					} elseif ($backup['type']=='S3') {
						echo "<td $attributes><strong>".basename($backup['file'])."</strong><br />S3://".$jobvalue['awsBucket']."/".dirname($backup['file'])."/";
					} elseif ($backup['type']=='FTP') {
						echo "<td $attributes><strong>".basename($backup['file'])."</strong><br />ftp://".$jobvalue['ftphost'].dirname($backup['file'])."/";
					} 
					$actions = array();
					$actions['delete'] = "<a class=\"submitdelete\" href=\"" . wp_nonce_url('admin.php?page=BackWPup&action=delete-backup&paged='.$paged.'&backupfile='.$backup['file'].'&jobid='.$backup['jobid'].'&type='.$backup['type'], 'delete-backup_'.basename($backup['file'])) . "\" onclick=\"if ( confirm('" . esc_js(__("You are about to delete this Backup Archive. \n  'Cancel' to stop, 'OK' to delete.","backwpup")) . "') ) { return true;}return false;\">" . __('Delete') . "</a>";
					$actions['download'] = "<a class=\"submitdelete\" href=\"" . $backup['downloadurl'] . "\">" . __('Download','backwpup') . "</a>";
					$action_count = count($actions);
					$i = 0;
					echo '<br /><div class="row-actions">';
					foreach ( $actions as $action => $linkaction ) {
						++$i;
						( $i == $action_count ) ? $sep = '' : $sep = ' | ';
						echo "<span class='$action'>$linkaction$sep</span>";
					}
					echo '</div>';
					echo "</td>";
					break;
				case 'size':
					echo "<td $attributes>";
					if (!empty($backup['filesize']) and $backup['filesize']!=-1) {
						echo backwpup_formatBytes($backup['filesize']);
					} else {
						_e('?','backwpup');
					}
					echo "</td>";
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
<option value="backups" selected="selected"><?PHP _e('Bulk Actions','backwpup'); ?></option>
<option value="delete-backup"><?PHP _e('Delete','backwpup'); ?></option>
</select>
<input type="submit" value="<?PHP _e('Apply','backwpup'); ?>" name="doaction2" id="doaction2" class="button-secondary action" />
</div>

<br class="clear" />
<?php
if ( $page_links )
	echo "<div class='tablenav-pages'>$page_links_text</div>";
?>
</div>
</form>
<br class="clear" />

</div>