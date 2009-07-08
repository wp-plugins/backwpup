<div class="wrap">
	<div id="icon-tools" class="icon32"><br /></div>
<h2><?php _e("BackWPup View Log", "backwpup"); ?></h2>
<ul class="subsubsub"> 
<li><a href="admin.php?page=BackWPup">Jobs</a> |</li> 
<li><a href="admin.php?page=BackWPup&amp;action=logs" >Logs</a> |</li> 
<li><a href="admin.php?page=BackWPup&amp;action=settings">Settings</a></li>
</ul>
<br class="clear" /> 

<pre>
<?PHP
echo $logs[$logtime]['log'];
?>
</pre>