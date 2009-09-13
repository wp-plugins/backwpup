<?PHP 
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');
?>
<div class="wrap">
	<div id="icon-tools" class="icon32"><br /></div>
<h2><?php _e("BackWPup Restore Database", "backwpup"); ?></h2>
<ul class="subsubsub"> 
<li><a href="admin.php?page=BackWPup"><?PHP _e('Jobs','backwpup'); ?></a> |</li> 
<li><a href="admin.php?page=BackWPup&amp;action=logs"><?PHP _e('Logs','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=tools" class="current"><?PHP _e('Tools','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=settings"><?PHP _e('Settings','backwpup'); ?></a></li>
</ul>

<form method="post" action="">
<input type="hidden" name="action" value="tools" />
<input type="hidden" name="page" value="BackWPup" />
<?php  wp_nonce_field('backwpup-tools'); ?>
<table class="form-table">
<tr valign="top"> 
<th scope="row"><label for="sqlfile"><?PHP _e('Database restore','backwpup'); ?></label></th> 
<td>

<?PHP
if ($_POST['dbrestore']==__('Restore', 'backwpup') and is_file($_POST['sqlfile'])) {
	$sqlfile=$_POST['sqlfile'];
	require('tools/db_restore.php');
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
		<input type="hidden" name="sqlfile" id="sqlfile" value="<?PHP echo trailingslashit(ABSPATH).$sqlfile;?>" class="regular-text" />
		<input type="submit" name="dbrestore" class="button" value="<?php _e('Restore', 'backwpup'); ?>" />
		<?PHP
	} else {
		echo __('Copy SQL file to restore in the Blog root dir to use restore.', 'backwpup')."<br />";
	}
}
?>
</td> 
</tr> 
</table>
</form>
</div>
