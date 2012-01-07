<?PHP
if (!defined('ABSPATH'))
	die();

include_once( trailingslashit(ABSPATH).'wp-admin/includes/class-wp-list-table.php');

class BackWPup_Jobs_Table extends WP_List_Table {

	function __construct() {
		parent::__construct( array(
			'plural' => 'jobs',
			'singular' => 'job',
			'ajax' => true
		) );
	}

	function ajax_user_can() {
		return current_user_can(BACKWPUP_USER_CAPABILITY);
	}

	function prepare_items() {
		global $mode,$wpdb;
		$jobsids=$wpdb->get_col("SELECT value FROM `".$wpdb->prefix."backwpup` WHERE main_name LIKE 'job_%' AND name='jobid' ORDER BY value ASC");
		if (!empty($jobsids)) {
			foreach ($jobsids as $jobid) {
				$this->items[$jobid]=backwpup_get_job_vars($jobid);
			}
		} else {
			$this->items='';
		}
		$mode = empty( $_GET['mode'] ) ? 'list' : $_GET['mode'];
	}

	function pagination( $which ) {
		global $mode;

		parent::pagination( $which );

		if ( 'top' == $which )
			$this->view_switcher( $mode );
	}

	function no_items() {
		_e( 'No Jobs.','backwpup');
	}

	function get_bulk_actions() {
		$actions = array();
		$actions['export'] = __( 'Export' );
		$actions['delete'] = __( 'Delete' );

		return $actions;
	}

	function get_columns() {
		$jobs_columns = array();
		$jobs_columns['cb'] = '<input type="checkbox" />';
		$jobs_columns['id'] = __('ID','backwpup');
		$jobs_columns['jobname'] = __('Job Name','backwpup');
		$jobs_columns['type'] = __('Type','backwpup');
		$jobs_columns['info'] = __('Information','backwpup');
		$jobs_columns['next'] = __('Next Run','backwpup');
		$jobs_columns['last'] = __('Last Run','backwpup');
		return $jobs_columns;
	}

	function display_rows() {
		//check for running job
		$backupdata=backwpup_get_option('working','data');
		$style = '';
		foreach ( $this->items as $jobvalue ) {
			$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';
			echo "\n\t", $this->single_row( $jobvalue, $backupdata, $style );
		}
	}

	function single_row($jobvalue, $backupdata, $style = '' ) {
		global $mode,$backwpup_cfg;

		list( $columns, $hidden, $sortable ) = $this->get_column_info();
		$r = "<tr id=\"jodid-".$jobvalue["jobid"]."\"".$style.">";
		foreach ( $columns as $column_name => $column_display_name ) {
			$class = "class=\"$column_name column-$column_name\"";

			$style = '';
			if ( in_array( $column_name, $hidden ) )
				$style = ' style="display:none;"';

			$attributes = "$class$style";

			switch( $column_name ) {
				case 'cb':
					$r .=  '<th scope="row" class="check-column"><input type="checkbox" name="jobs[]" value="'. esc_attr($jobvalue["jobid"]) .'" /></th>';
					break;
				case 'id':
					$r .=  "<td $attributes>".$jobvalue["jobid"]."</td>";
					break;
				case 'jobname':
					$r .=  "<td $attributes><strong><a href=\"".wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpupeditjob&jobid='.$jobvalue["jobid"], 'edit-job')."\" title=\"".__('Edit:','backwpup').esc_html($jobvalue['name'])."\">".esc_html($jobvalue['name'])."</a></strong>";
					$actions = array();
					if (empty($backupdata)) {
						$actions['edit'] = "<a href=\"" . wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpupeditjob&jobid='.$jobvalue["jobid"], 'edit-job') . "\">" . __('Edit') . "</a>";
						$actions['copy'] = "<a href=\"" . wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpup&action=copy&jobid='.$jobvalue["jobid"], 'copy-job_'.$jobvalue["jobid"]) . "\">" . __('Copy','backwpup') . "</a>";
						$actions['export'] = "<a href=\"" . wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpup&action=export&jobs[]='.$jobvalue["jobid"], 'bulk-jobs') . "\">" . __('Export','backwpup') . "</a>";
						$actions['delete'] = "<a class=\"submitdelete\" href=\"" . wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpup&action=delete&jobs[]='.$jobvalue["jobid"], 'bulk-jobs') . "\" onclick=\"return showNotice.warn();\">" . __('Delete') . "</a>";
						if (BACKWPUP_ENV_CHECK_OK) {
							$url=backwpup_jobrun_url('runnow',$jobvalue["jobid"]);
							$actions['runnow'] = "<a href=\"" .$url['url'] . "\">" . __('Run Now','backwpup') . "</a>";
						}
					} else {
						if (!empty($backupdata) and $backupdata['STATIC']['JOB']['jobid']==$jobvalue["jobid"]) {
							$actions['working'] = "<a href=\"" . wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpupworking', '') . "\">" . __('View!','backwpup') . "</a>";
							$actions['abort'] = "<a class=\"submitdelete\" href=\"" . wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpup&action=abort', 'abort-job') . "\">" . __('Abort!','backwpup') . "</a>";
						}
					}
					$r .= $this->row_actions($actions);
					$r .=  '</td>';
					break;
				case 'type':
					$r .=  "<td $attributes>";
					$r .=  backwpup_backup_types($jobvalue['type'],false);
					$r .=  "</td>";
					break;
				case 'info':
					$r .=  "<td $attributes>";
					$r .=  "<img class=\"waiting\" src=\"".esc_url( backwpup_admin_url( 'images/wpspin_light.gif' ) )."\" id=\"image-wait-".$jobvalue["jobid"]."\" />";
					$r .=  "</td>";
					break;
				case 'next':
					$r .= "<td $attributes>";
					if (!empty($backupdata) and $backupdata['STATIC']['JOB']['jobid']==$jobvalue["jobid"]) {
						$runtime=current_time('timestamp')-$jobvalue['starttime'];
						$r .=  __('Running since:','backwpup').' '.$runtime.' '.__('sec.','backwpup');
					} elseif ($jobvalue['activetype']=='wpcron') {
						$r .=  date_i18n(get_option('date_format').' @ '.get_option('time_format'),$jobvalue['cronnextrun']).' by WP-Cron';
					} elseif ($jobvalue['activetype']=='backwpupapi') {
						$r .=  date_i18n(get_option('date_format').' @ '.get_option('time_format'),$jobvalue['cronnextrun']).' by BackWPup Cron Service';
					} else {
						$r .= __('Inactive','backwpup');
					}
					if ( 'excerpt' == $mode ) {
						$r .= '<br />'.__('<a href="http://wikipedia.org/wiki/Cron" target="_blank">Cron</a>:','backwpup').' '.$jobvalue['cron'];
					}
					$r .=  "</td>";
					break;
				case 'last':
					$r .=  "<td $attributes>";
					if (!empty($jobvalue['lastrun'])) {
						$r .=  date_i18n(get_option('date_format').' @ '.get_option('time_format'),$jobvalue['lastrun']);
						if (!empty($jobvalue['lastruntime']))
							$r .=  '<br />'.__('Runtime:','backwpup').' '.$jobvalue['lastruntime'].' '.__('sec.','backwpup');
					} else {
						$r .= __('None','backwpup');
					}
					$r .=  "<br />";
					if (!empty($jobvalue['lastbackupdownloadurl']))
						$r .="<a href=\"" . wp_nonce_url($jobvalue['lastbackupdownloadurl'], 'download-backup') . "\" title=\"".__('Download last Backup','backwpup')."\">" . __('Download','backwpup') . "</a> | ";
					if (!empty($jobvalue['logfile']))
						$r .="<a href=\"" . wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpupworking&logfile='.$jobvalue['logfile'], 'view-log_'.basename($jobvalue['logfile'])) . "\" title=\"".__('View last Log','backwpup')."\">" . __('Log','backwpup') . "</a>";
					$r .=  "</td>";
					break;
			}
		}
		$r .= '</tr>';
		return $r;
	}
}
?>