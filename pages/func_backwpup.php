<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

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
		global $mode;
		$this->items=get_option('backwpup_jobs');
		$mode = empty( $_REQUEST['mode'] ) ? 'list' : $_REQUEST['mode'];
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
		$runningfile['JOBID']='';
		if (is_file(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running')) {
			$runfile=file_get_contents(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running');
			$runningfile=unserialize(trim($runfile));
		}
		$style = '';
		foreach ( $this->items as $jobid => $jobvalue ) {
			$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';
			echo "\n\t", $this->single_row( $jobid, $jobvalue, $runningfile, $style );
		}
	}
	
	function single_row( $jobid, $jobvalue, $runningfile, $style = '' ) {
		global $mode;
				
		list( $columns, $hidden, $sortable ) = $this->get_column_info();
		$r = "<tr id='jodid-$jobid'$style>";
		foreach ( $columns as $column_name => $column_display_name ) {
			$class = "class=\"$column_name column-$column_name\"";

			$style = '';
			if ( in_array( $column_name, $hidden ) )
				$style = ' style="display:none;"';

			$attributes = "$class$style";
			
			switch( $column_name ) {
				case 'cb':
					$r .=  '<th scope="row" class="check-column"><input type="checkbox" name="jobs[]" value="'. esc_attr($jobid) .'" /></th>';
					break;
				case 'id':
					$r .=  "<td $attributes>".$jobid."</td>"; 
					break;
				case 'jobname':
					$r .=  "<td $attributes><strong><a href=\"".wp_nonce_url('admin.php?page=backwpup&subpage=edit&jobid='.$jobid, 'edit-job')."\" title=\"".__('Edit:','backwpup').esc_html($jobvalue['name'])."\">".esc_html($jobvalue['name'])."</a></strong>";
					$actions = array();
					if (!is_file(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running')) {
						$actions['edit'] = "<a href=\"" . wp_nonce_url('admin.php?page=backwpupeditjob&jobid='.$jobid, 'edit-job') . "\">" . __('Edit') . "</a>";
						$actions['copy'] = "<a href=\"" . wp_nonce_url('admin.php?page=backwpup&action=copy&jobid='.$jobid, 'copy-job_'.$jobid) . "\">" . __('Copy','backwpup') . "</a>";
						$actions['export'] = "<a href=\"" . wp_nonce_url('admin.php?page=backwpup&action=export&jobs[]='.$jobid, 'bulk-jobs') . "\">" . __('Export','backwpup') . "</a>";
						$actions['delete'] = "<a class=\"submitdelete\" href=\"" . wp_nonce_url('admin.php?page=backwpup&action=delete&jobs[]='.$jobid, 'bulk-jobs') . "\" onclick=\"return showNotice.warn();\">" . __('Delete') . "</a>";
						$actions['runnow'] = "<a href=\"" . wp_nonce_url('admin.php?page=backwpupworking&action=runnow&jobid='.$jobid, 'runnow-job_'.$jobid) . "\">" . __('Run Now','backwpup') . "</a>";
					} else {
						if ($runningfile['JOBID']==$jobid)
							$actions['abort'] = "<a href=\"" . wp_nonce_url('admin.php?page=backwpup&action=abort', 'abort-job') . "\">" . __('Abort!!!','backwpup') . "</a>";
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
					if (in_array('FILE',explode('+',$jobvalue['type']))) {
						$files=backwpup_calc_file_size($jobvalue);
						$r .=  __("Files Size:","backwpup")." ".backwpup_formatBytes($files['size'])."<br />";
						if ( 'excerpt' == $mode ) {
							$r .=  __("Files count:","backwpup")." ".$files['num']."<br />";
						}
					}
					if (in_array('DB',explode('+',$jobvalue['type'])) or in_array('OPTIMIZE',explode('+',$jobvalue['type'])) or in_array('CHECK',explode('+',$jobvalue['type']))) {
						$dbsize=backwpup_calc_db_size($jobvalue);
						$r .=  "DB Size: ".backwpup_formatBytes($dbsize['size'])."<br />";
						if ( 'excerpt' == $mode ) {
							$r .=  __("DB Tables:","backwpup")." ".$dbsize['num']."<br />";
							$r .=  __("DB Rows:","backwpup")." ".$dbsize['rows']."<br />";
						}
					}
					$r .=  "</td>";
					break;
				case 'next':
					$r .= "<td $attributes>";
					if ($runningfile['JOBID']==$jobid and is_file(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running')) {
						$runtime=current_time('timestamp')-$jobvalue['starttime'];
						$r .=  __('Running since:','backwpup').' '.$runtime.' '.__('sec.','backwpup');
					} elseif ($jobvalue['activated']) {
						$r .=  date(get_option('date_format'),$jobvalue['cronnextrun']).'<br />'. date(get_option('time_format'),$jobvalue['cronnextrun']);
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
					if ($jobvalue['lastrun']) {
						$r .=  date_i18n(get_option('date_format'),$jobvalue['lastrun']).'<br />'. date_i18n(get_option('time_format'),$jobvalue['lastrun']); 
						if (isset($jobvalue['lastruntime']))
							$r .=  '<br />'.__('Runtime:','backwpup').' '.$jobvalue['lastruntime'].' '.__('sec.','backwpup').'<br />';
					} else {
						$r .= __('None','backwpup');
					}
					if (!empty($jobvalue['lastbackupdownloadurl']))
						$r .="<a href=\"" . wp_nonce_url($jobvalue['lastbackupdownloadurl'], 'download-backup') . "\" title=\"".__('Download last Backup','backwpup')."\">" . __('Download','backwpup') . "</a> | ";
					if (!empty($jobvalue['logfile']))
						$r .="<a href=\"" . wp_nonce_url('admin.php?page=backwpupworking&logfile='.$jobvalue['logfile'], 'view-log_'.basename($jobvalue['logfile'])) . "\" title=\"".__('View last Log','backwpup')."\">" . __('Log','backwpup') . "</a><br />";

					$r .=  "</td>";
					break;
			}
		}
		$r .= '</tr>';
		return $r;
	}
}	
?>