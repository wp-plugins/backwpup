<?php
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

/**
 * Class for BackWPup Dashbord Widgets
 */
class BackWPup_Dashboard {
	public static function dashboard_logs() {
		$widgets = get_option('dashboard_widget_options');
		if (!isset($widgets['backwpup_dashboard_logs']) || $widgets['backwpup_dashboard_logs']<1 || $widgets['backwpup_dashboard_logs']>20)
			$widgets['backwpup_dashboard_logs'] =5;
		//get log files
		$logfiles=array();
		if (is_readable(backwpup_get_option('cfg','logfolder')) &&  $dir = @opendir( backwpup_get_option('cfg','logfolder') ) ) {
			while (($file = readdir( $dir ) ) !== false ) {
				if (is_file(backwpup_get_option('cfg','logfolder').$file) && 'backwpup_log_' == substr($file,0,strlen('backwpup_log_')) &&  ('.html' == substr($file,-5) || '.html.gz' == substr($file,-8)))
					$logfiles[]=$file;
			}
			closedir( $dir );
			rsort($logfiles);
		}
		echo '<ul>';
		if (count($logfiles)>0) {
			$count=0;
			foreach ($logfiles as $logfile) {
				$logdata=backwpup_read_logheader(backwpup_get_option('cfg','logfolder').$logfile);
				echo '<li>';
				echo '<span>'.date_i18n(get_option('date_format').' @ '.get_option('time_format'),$logdata['logtime']).'</span> ';
				echo '<a href="'.wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpupworking&logfile='.backwpup_get_option('cfg','logfolder').$logfile, 'view-log_'.$logfile).'" title="'.__('View Log:','backwpup').' '.basename($logfile).'">'.$logdata['name'].'</i></a>';
				if ($logdata['errors']>0)
					printf(' <span style="color:red;font-weight:bold;">'._n("%d ERROR", "%d ERRORS", $logdata['errors'],'backwpup').'</span>', $logdata['errors']);
				if ($logdata['warnings']>0)
					printf(' <span style="color:#e66f00;font-weight:bold;">'._n("%d WARNING", "%d WARNINGS", $logdata['warnings'],'backwpup').'</span>', $logdata['warnings']);
				if($logdata['errors']==0 && $logdata['warnings']==0)
					echo ' <span style="color:green;font-weight:bold;">'.__('O.K.','backwpup').'</span>';
				echo '</li>';
				$count++;
				if ($count>=$widgets['backwpup_dashboard_logs'])
					break;
			}
			echo '</ul>';
		} else {
			echo '<i>'.__('none','backwpup').'</i>';
		}
	}

	public static function dashboard_logs_config() {
		if ( !$widget_options = get_option( 'dashboard_widget_options' ) )
			$widget_options = array();

		if ( !isset($widget_options['backwpup_dashboard_logs']) )
			$widget_options['backwpup_dashboard_logs'] = 5;

		if ( 'POST' == $_SERVER['REQUEST_METHOD'] && isset($_POST['backwpup_dashboard_logs']) ) {
			$number = absint( $_POST['backwpup_dashboard_logs'] );
			$widget_options['backwpup_dashboard_logs'] = $number;
			update_option( 'dashboard_widget_options', $widget_options );
		}

		echo '<p><label for="backwpup-logs">'.__('How many of the lastes logs would you like to display?','backwpup').'</label>';
		echo '<select id="backwpup-logs" name="backwpup_dashboard_logs">';
		for ($i=0;$i<=20;$i++)
			echo '<option value="'.$i.'" '.selected($i,$widget_options['backwpup_dashboard_logs']).'>'.$i.'</option>';
		echo '</select>';

	}

	public static function dashboard_activejobs() {
		global $wpdb;
		$backupdata=backwpup_get_workingdata();
		$mainsactive=$wpdb->get_col("SELECT main FROM `".$wpdb->prefix."backwpup` WHERE main LIKE 'job_%' AND name='activetype' AND value!=''");
		if (empty($mainsactive) and !$backupdata) {
			echo '<ul><li><i>'.__('none','backwpup').'</i></li></ul>';
			return;
		}
		//get ordering
		$mainscronnextrun=$wpdb->get_col("SELECT main FROM `".$wpdb->prefix."backwpup` WHERE main LIKE 'job_%' AND name='cronnextrun' ORDER BY value ASC");
		// ad woking jo if it not in active jobs
		if ($backupdata and !in_array('job_'.$backupdata['JOBID'],$mainsactive))
			$mainsactive[]='job_'.$backupdata['JOBID'];
		echo '<ul>';
		foreach ($mainscronnextrun as $main) {
			if (!in_array($main,$mainsactive))
				continue;
			$name=backwpup_get_option($main,'name');
			$jobid=backwpup_get_option($main,'jobid');
			if (!empty($backupdata) && $backupdata['JOBID']==$jobid) {
				$startime=backwpup_get_option($main,'starttime');
				$runtime=current_time('timestamp')-$startime;
				echo '<li><span style="font-weight:bold;">'.$jobid.'. '.$name.': </span>';
				printf('<span style="color:#e66f00;">'.__('working since %d sec.','backwpup').'</span>',$runtime);
				echo " <a style=\"color:green;\" href=\"" . backwpup_admin_url('admin.php').'?page=backwpupworking' . "\">" . __('View!','backwpup') . "</a>";
				echo " <a style=\"color:red;\" href=\"" . wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpup&action=abort', 'abort-job') . "\">" . __('Abort!','backwpup') . "</a>";
				echo "</li>";
			} else {
				$cronnextrun=backwpup_get_option($main,'cronnextrun');
				echo '<li><span>'.date_i18n(get_option('date_format'),$cronnextrun).' @ '.date_i18n(get_option('time_format'),$cronnextrun).'</span>';
				echo ' <a href="'.wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpupeditjob&jobid='.$jobid, 'edit-job').'" title="'.__('Edit Job','backwpup').'">'.$name.'</a><br />';
				echo "</li>";
			}
		}
		echo '</ul>';
	}
}