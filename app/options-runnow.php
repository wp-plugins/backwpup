<?PHP 
// don't load directly 
if ( !defined('ABSPATH') ) 
	die('-1');
?>
<div class="wrap">
	<div id="icon-tools" class="icon32"><br /></div>
<h2><?php _e("BackWPup Job Running", "backwpup"); ?></h2>
<ul class="subsubsub"> 
<li><a href="admin.php?page=BackWPup"><?PHP _e('Jobs','backwpup'); ?></a> |</li> 
<li><a href="admin.php?page=BackWPup&amp;action=logs"><?PHP _e('Logs','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=tools"><?PHP _e('Tools','backwpup'); ?></a> |</li>
<li><a href="admin.php?page=BackWPup&amp;action=settings"><?PHP _e('Settings','backwpup'); ?></a></li>
</ul>
<br class="clear" />
<big><?php
_e('Running Job','backwpup');
echo ' <strong>'.$jobs[$jobid]['name'].'</strong>';
?></big>
<iframe src="<?PHP  echo wp_nonce_url(plugins_url('options-runnow-iframe.php',__FILE__).'?wpabs='.trailingslashit(ABSPATH).'&jobid=' . $jobid, 'dojob-now_' . $jobid); ?>" name="Logframe" id="Logframe" width="100%" height="450" align="left" scrolling="auto" style="border: 1px solid gray" frameborder="0"></iframe>
</div>
