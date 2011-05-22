<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

echo "<div class=\"wrap\">";
screen_icon();
echo "<h2>".esc_html( __('BackWPup Manage Backups', 'backwpup'))."</h2>";
if (isset($backwpup_message) and !empty($backwpup_message)) 
	echo "<div id=\"message\" class=\"updated\"><p>".$backwpup_message."</p></div>";
echo "<form id=\"posts-filter\" action=\"".get_admin_url()."admin.php?page=backwpupbackups\" method=\"post\">";
$backwpup_listtable->display();
echo "<div id=\"ajax-response\"></div>";
echo "</form>"; 
echo "</div>";	
?>