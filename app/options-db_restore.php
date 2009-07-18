<div class="wrap">
	<div id="icon-tools" class="icon32"><br /></div>
<h2><?php _e("BackWPup Restore Database", "backwpup"); ?></h2>
<ul class="subsubsub"> 
<li><a href="admin.php?page=BackWPup"><?PHP _e('Jobs','backwpup'); ?></a> |</li> 
<li><a href="admin.php?page=BackWPup&amp;action=logs"><?PHP _e('Logs','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=db_restore" class="current"><?PHP _e('DB Restore','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=settings"><?PHP _e('Settings','backwpup'); ?></a></li>
</ul>

<form method="post" action="">
<input type="hidden" name="action" value="db_restore" />
<input type="hidden" name="page" value="BackWPup" />
<?php  wp_nonce_field('backwpup-db_restore'); ?>

<table class="form-table">


</table>
 
<p class="submit"> 
<input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Changes', 'backwpup'); ?>" /> 
</p> 
</form>
