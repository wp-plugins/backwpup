<div class="wrap">
	<div id="icon-tools" class="icon32"><br /></div>
<h2><?php _e("BackWPup Settings", "backwpup"); ?></h2>
<ul class="subsubsub"> 

<li><a href="admin.php?page=BackWPup">Jobs</a> |</li> 
<li><a href="admin.php?page=BackWPup&amp;action=logs">Logs</a> |</li> 
<li><a href="admin.php?page=BackWPup&amp;action=settings" class="current">Settings</a></li>
</ul>

<form method="post" action="">
<input type="hidden" name="action" value="savecfg" />
<?php  wp_nonce_field('backwpup-cfg'); ?>

<table class="form-table">
<tr valign="top">
<th scope="row"><label for="tempdir"><?PHP _e('Temp Directory:','backwpup'); ?></label></th> 
<td><input name="tempdir" type="text" value="<?PHP echo $cfg['tempdir'];?>" class="regular-text" /><span class="description"><?PHP _e('Full Phath of Temp Directory','backwpup'); ?></span></td> 
</tr> 

</table>
 
<p class="submit"> 
<input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Changes', 'backwpup'); ?>" /> 
</p> 
</form>
