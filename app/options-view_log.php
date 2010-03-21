<?PHP 
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');
?>
<div class="wrap">
	<div id="icon-tools" class="icon32"><br /></div>
<h2><?php _e("BackWPup View Log", "backwpup"); ?></h2>
<ul class="subsubsub"> 
<li><a href="admin.php?page=BackWPup"><?PHP _e('Jobs','backwpup'); ?></a> |</li> 
<li><a href="admin.php?page=BackWPup&amp;action=logs"><?PHP _e('Logs','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=tools"><?PHP _e('Tools','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=settings"><?PHP _e('Settings','backwpup'); ?></a></li>
</ul>
<br class="clear" /> 
<big><?php
_e('View Log','backwpup');
echo ' <strong>'.basename($_GET['logfile']).'</strong>';
?></big>
<iframe src="<?PHP  echo wp_nonce_url(WP_PLUGIN_URL.'/'.BACKWPUP_PLUGIN_DIR.'/app/options-view_log-iframe.php?ABSPATH='.ABSPATH.'&amp;logfile=' . $_GET['logfile'], 'viewlognow_'.basename($_GET['logfile'])); ?>" name="Logframe" id="Logframe" width="100%" height="450" align="left" scrolling="auto" style="border: 1px solid gray" frameborder="0"></iframe>
</div>