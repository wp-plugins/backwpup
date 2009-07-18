<div class="wrap">
	<div id="icon-tools" class="icon32"><br /></div>
<h2><?php _e("BackWPup Settings", "backwpup"); ?></h2>
<ul class="subsubsub"> 
<li><a href="admin.php?page=BackWPup"><?PHP _e('Jobs','backwpup'); ?></a> |</li> 
<li><a href="admin.php?page=BackWPup&amp;action=logs"><?PHP _e('Logs','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=db_restore"><?PHP _e('DB Restore','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=settings" class="current"><?PHP _e('Settings','backwpup'); ?></a></li>
</ul>

<form method="post" action="">
<input type="hidden" name="action" value="savecfg" />
<?php  wp_nonce_field('backwpup-cfg'); ?>

<table class="form-table">
<tr valign="top"> 
<tr valign="top"> 
<th scope="row"><label for="jobname"><?PHP _e('Script Runime','backwpup'); ?></label></th> 
<td>
<? 
	echo __('PHP.ini execution time:','backwpup').' '.ini_get('max_execution_time').' '.__('sec.','backwpup').'<br />'; 
?>
</td> 
</tr> 

</table>
 
<p class="submit"> 
<input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Changes', 'backwpup'); ?>" /> 
</p> 
</form>
