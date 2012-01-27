<?php
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}
/**
 * Class for BackWPup logs display page
 */
class BackWpup_Page_Logs {
	public static function load() {
		global $backwpup_listtable;
		//Create Table
		$backwpup_listtable = new BackWPup_Table_Logs;

		switch($backwpup_listtable->current_action()) {
			case 'delete':
				if (is_array($_GET['logfiles'])) {
					check_admin_referer('bulk-logs');
					$num=0;
					foreach ($_GET['logfiles'] as $logfile) {
						if (is_file(backwpup_get_option('cfg','logfolder').$logfile))
							unlink(backwpup_get_option('cfg','logfolder').$logfile);
						$num++;
					}
				}
				break;
			case 'download': //Download Backup
				check_admin_referer('download-backup_'.basename(trim($_GET['file'])));
				if (is_file(trim($_GET['file']))) {
					header("Pragma: public");
					header("Expires: 0");
					header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
					header("Content-Type: application/force-download");
					header("Content-Type: application/octet-stream");
					header("Content-Type: application/download");
					header("Content-Disposition: attachment; filename=".basename(trim($_GET['file'])).";");
					header("Content-Transfer-Encoding: binary");
					header("Content-Length: ".filesize(trim($_GET['file'])));
					@readfile(trim($_GET['file']));
					die();
				} else {
					header('HTTP/1.0 404 Not Found');
					die();
				}
				break;
		}


		//Save per page
		if (isset($_POST['screen-options-apply']) && isset($_POST['wp_screen_options']['option']) && isset($_POST['wp_screen_options']['value']) && $_POST['wp_screen_options']['option']=='backwpuplogs_per_page') {
			check_admin_referer( 'screen-options-nonce', 'screenoptionnonce' );
			global $current_user;
			if ($_POST['wp_screen_options']['value']>0 && $_POST['wp_screen_options']['value']<1000) {
				update_user_option($current_user->ID,'backwpuplogs_per_page',(int) $_POST['wp_screen_options']['value']);
				wp_redirect( remove_query_arg( array('pagenum', 'apage', 'paged'), wp_get_referer() ) );
				exit;
			}
		}

		//add Help
		BackWPup_Help::help();
		BackWPup_Help::add_tab(array(
				'id'      => 'overview',
				'title'   => __('Overview'),
				'content'	=> '<p>' .__('Here you can manage the log files of the jobs. You can download, view, or delete them.','backwpup') . '</p>'
			) );

		add_screen_option( 'per_page', array('label' => __('Logs','backwpup'), 'default' => 20, 'option' =>'backwpuplogs_per_page') );

		//add css for Admin Section
		wp_enqueue_style('backwpup_logs',plugins_url('',dirname(__FILE__)).'/css/logs.css','',((defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? time() : backwpup_get_version()),'screen');
		//add java for Admin Section
		//wp_enqueue_script('backwpup_logs',plugins_url('',dirname(__FILE__)).'/js/logs.js','',((defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? time() : backwpup_get_version()),true);

		$backwpup_listtable->prepare_items();
	}

	public static function page() {
		global $backwpup_listtable;
		echo "<div class=\"wrap\">";
		screen_icon();
		echo "<h2>".esc_html( __('BackWPup Logs', 'backwpup'))."</h2>";
		echo "<form id=\"posts-filter\" action=\"\" method=\"get\">";
		echo "<input type=\"hidden\" name=\"page\" value=\"backwpuplogs\" />";
		$backwpup_listtable->display();
		echo "<div id=\"ajax-response\"></div>";
		echo "</form>";
		echo "</div>";
	}
}