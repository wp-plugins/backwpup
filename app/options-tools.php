<?PHP 
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');
?>
<div class="wrap">
	<div id="icon-tools" class="icon32"><br /></div>
<h2><?php _e("BackWPup Tools", "backwpup"); ?></h2>

<ul class="subsubsub"> 
<li><a href="admin.php?page=BackWPup"><?PHP _e('Jobs','backwpup'); ?></a> |</li> 
<li><a href="admin.php?page=BackWPup&amp;action=logs"><?PHP _e('Logs','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=backups"><?PHP _e('Backups','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=tools" class="current"><?PHP _e('Tools','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=settings"><?PHP _e('Settings','backwpup'); ?></a></li>
</ul>

<div class="clear"></div> 

<form method="post" action="">
<input type="hidden" name="action" value="tools" />
<input type="hidden" name="page" value="BackWPup" />
<?php  wp_nonce_field('backwpup-tools'); ?>

<div id="poststuff" class="metabox-holder has-right-sidebar"> 
	<div class="inner-sidebar">
		<div id="side-sortables" class="meta-box-sortables">
		
		</div>
	</div>
	<div class="has-sidebar" >
		<div id="post-body-content" class="has-sidebar-content">
				
			<div id="dbrestore" class="postbox">
				<h3 class="hndle"><span><?PHP _e('Database restore','backwpup'); ?></span></h3>
				<div class="inside">
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
							<input type="hidden" name="sqlfile" id="sqlfile" value="<?PHP echo trailingslashit(ABSPATH).$sqlfile;?>" />
							<input type="submit" name="dbrestore" class="button-primary" value="<?php _e('Restore', 'backwpup'); ?>" />
							<?PHP
						} else {
							echo __('Copy SQL file to Blog root folder to use restore.', 'backwpup')."<br />";
						}
					}
					?>
				</div>
			</div>
			
		</div>
	</div>
</div>
</form>
</div>
