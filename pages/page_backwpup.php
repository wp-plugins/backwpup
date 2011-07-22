<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}
	
echo "<div class=\"wrap\">";
screen_icon();
echo "<h2>".esc_html( __('BackWPup Jobs', 'backwpup'))."&nbsp;<a href=\"".wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpupeditjob', 'edit-job')."\" class=\"add-new-h2\">".esc_html__('Add New','backwpup')."</a></h2>";
if (isset($backwpup_message) and !empty($backwpup_message)) 
	echo "<div id=\"message\" class=\"updated\"><p>".$backwpup_message."</p></div>";
echo "<form id=\"posts-filter\" action=\"\" method=\"get\">";
echo "<input type=\"hidden\" name=\"page\" value=\"backwpup\" />";
wp_nonce_field('backwpup_ajax_nonce', 'backwpupajaxnonce', false ); 
$backwpup_listtable->display();
echo "<div id=\"ajax-response\"></div>";
echo "</form>"; 
echo "</div>";	
?>
<div style="text-align:center;"><br />
<script type="text/javascript"><!--
google_ad_client = "ca-pub-0128840172400820";
/* BackWPup Plugin horizontal */
google_ad_slot = "5785357827";
google_ad_width = 728;
google_ad_height = 90;
//-->
</script>
<script type="text/javascript"
src="http://pagead2.googlesyndication.com/pagead/show_ads.js">
</script>
</div>