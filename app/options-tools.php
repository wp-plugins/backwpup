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

<tr valign="top"> 
<th scope="row"><label for="sqlfile"><?PHP _e('Create Amazon S3 Bucket','backwpup'); ?></label></th> 
<td>
<?PHP
if ($_POST['awsbucketcreate']==__('Create', 'backwpup') and !empty($_POST['awsAccessKey']) and !empty($_POST['awsSecretKey']) and !empty($_POST['awsBucket'])) {
	if (!class_exists('S3')) require_once 'libs/S3.php';
	$s3 = new S3($_POST['awsAccessKey'], $_POST['awsSecretKey'], false);
	if ($s3->putBucket($_POST['awsBucket'], S3::ACL_PRIVATE, $_POST['awsRegion']))
		echo __('Amazone S3 Bucket created.', 'backwpup')."<br />";
	else
		echo __('Can not create Amazon S3 Bucket.', 'backwpup')."<br />";
	
}
?>
<?php _e('Access Key ID:', 'backwpup'); ?><input type="text" name="awsAccessKey" id="awsAccessKey" value="<?PHP echo $_POST['awsAccessKey'];?>" class="regular-text" /><br />
<?php _e('Secret Access Key:', 'backwpup'); ?><input type="text" name="awsSecretKey" id="awsSecretKey" value="<?PHP echo $_POST['awsSecretKey'];?>" class="regular-text" /><br />
<?php _e('Bucket Name:', 'backwpup'); ?><input type="text" name="awsBucket" id="awsBucket" value="<?PHP echo $_POST['awsBucket'];?>" class="regular-text" /><br />
<?php _e('Bucket Region:', 'backwpup'); ?><select name="awsRegion"><option value=""><?php _e('US', 'backwpup'); ?></option><option value="EU"><?php _e('Europe', 'backwpup'); ?></option></select>
<input type="submit" name="awsbucketcreate" class="button" value="<?php _e('Create', 'backwpup'); ?>" />
</td> 
</tr> 


</table>
</form>
</div>
