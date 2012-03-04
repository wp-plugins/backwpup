<?php
if ( ! defined( 'ABSPATH' ) ) {
	header( $_SERVER["SERVER_PROTOCOL"] . " 404 Not Found" );
	header( "Status: 404 Not Found" );
	die();
}

/**
 *
 */
class BackWPup_Page_Backwpup_Table extends WP_List_Table {

	/**
	 *
	 */
	function __construct() {
		parent::__construct( array(
			'plural'   => 'jobs',
			'singular' => 'job',
			'ajax'	 => true
		) );
	}

	/**
	 * @return bool
	 */
	function ajax_user_can() {
		return current_user_can( 'backwpup' );
	}

	function prepare_items() {
		global $mode, $wpdb;
		$this->items = array();
		$jobsids     = $wpdb->get_col( "SELECT value FROM `" . $wpdb->prefix . "backwpup` WHERE main LIKE 'job_%' AND name='jobid' ORDER BY value ASC" );
		if ( ! empty($jobsids) ) {
			foreach ( $jobsids as $jobid )
			{
				$this->items[] = $jobid;
			}
		}
		$mode = empty($_GET['mode']) ? 'list' : $_GET['mode'];
	}

	/**
	 * @param $which
	 */
	function pagination( $which ) {
		global $mode;

		parent::pagination( $which );

		if ( 'top' == $which )
			$this->view_switcher( $mode );
	}

	function no_items() {
		_e( 'No Jobs.', 'backwpup' );
	}

	/**
	 * @return array
	 */
	function get_bulk_actions() {
		$actions           = array();
		$actions['export'] = __( 'Export' );
		$actions['delete'] = __( 'Delete' );

		return $actions;
	}

	/**
	 * @return array
	 */
	function get_columns() {
		$jobs_columns            = array();
		$jobs_columns['cb']      = '<input type="checkbox" />';
		$jobs_columns['id']      = __( 'ID', 'backwpup' );
		$jobs_columns['jobname'] = __( 'Job Name', 'backwpup' );
		$jobs_columns['type']    = __( 'Type', 'backwpup' );
		$jobs_columns['info']    = __( 'Information', 'backwpup' );
		$jobs_columns['next']    = __( 'Next Run', 'backwpup' );
		$jobs_columns['last']    = __( 'Last Run', 'backwpup' );
		return $jobs_columns;
	}

	function display_rows() {
		//check for running job
		$backupdata = backwpup_get_workingdata();
		$style      = '';
		foreach ( $this->items as $jobid ) {
			$style = (' class="alternate"' == $style) ? '' : ' class="alternate"';
			echo "\n\t", $this->single_row( $jobid, $backupdata, $style );
		}
	}

	/**
	 * @param		$jobid
	 * @param		$backupdata
	 * @param string $style
	 *
	 * @return string
	 */
	function single_row( $jobid, $backupdata, $style = '' ) {
		global $mode,$wpdb;

		list($columns, $hidden, $sortable) = $this->get_column_info();
		$r = "<tr id=\"jodid-" . $jobid . "\"" . $style . ">";
		foreach ( $columns as $column_name => $column_display_name ) {
			$class = "class=\"$column_name column-$column_name\"";

			$style = '';
			if ( in_array( $column_name, $hidden ) )
				$style = ' style="display:none;"';

			$attributes = "$class$style";

			switch ( $column_name ) {
				case 'cb':
					$r .= '<th scope="row" class="check-column"><input type="checkbox" name="jobs[]" value="' . esc_attr( $jobid ) . '" /></th>';
					break;
				case 'id':
					$r .= "<td $attributes>" . $jobid . "</td>";
					break;
				case 'jobname':
					$r .= "<td $attributes><strong><a href=\"" . wp_nonce_url( backwpup_admin_url( 'admin.php' ) . '?page=backwpupeditjob&jobid=' . $jobid, 'edit-job' ) . "\" title=\"" . __( 'Edit:', 'backwpup' ) . esc_html( backwpup_get_option( 'job_' . $jobid, 'name' ) ) . "\">" . esc_html( backwpup_get_option( 'job_' . $jobid, 'name' ) ) . "</a></strong>";
					$actions = array();
					if ( empty($backupdata) ) {
						$actions['edit']   = "<a href=\"" . wp_nonce_url( backwpup_admin_url( 'admin.php' ) . '?page=backwpupeditjob&jobid=' . $jobid, 'edit-job' ) . "\">" . __( 'Edit' ) . "</a>";
						$actions['copy']   = "<a href=\"" . wp_nonce_url( backwpup_admin_url( 'admin.php' ) . '?page=backwpup&action=copy&jobid=' . $jobid, 'copy-job_' . $jobid ) . "\">" . __( 'Copy', 'backwpup' ) . "</a>";
						$actions['export'] = "<a href=\"" . wp_nonce_url( backwpup_admin_url( 'admin.php' ) . '?page=backwpup&action=export&jobs[]=' . $jobid, 'bulk-jobs' ) . "\">" . __( 'Export', 'backwpup' ) . "</a>";
						$actions['delete'] = "<a class=\"submitdelete\" href=\"" . wp_nonce_url( backwpup_admin_url( 'admin.php' ) . '?page=backwpup&action=delete&jobs[]=' . $jobid, 'bulk-jobs' ) . "\" onclick=\"return showNotice.warn();\">" . __( 'Delete' ) . "</a>";
						if ( backwpup_get_option( 'backwpup', 'check' ) ) {
							$url               = backwpup_jobrun_url( 'runnowlink', $jobid );
							$actions['runnow'] = "<a href=\"" . $url['url'] . "\">" . __( 'Run now', 'backwpup' ) . "</a>";
						}
					} else {
						if ( ! empty($backupdata) && $backupdata['JOBID'] == $jobid ) {
							$actions['working'] = "<a href=\"" . wp_nonce_url( backwpup_admin_url( 'admin.php' ) . '?page=backwpupworking', '' ) . "\">" . __( 'View!', 'backwpup' ) . "</a>";
							$actions['abort']   = "<a class=\"submitdelete\" href=\"" . wp_nonce_url( backwpup_admin_url( 'admin.php' ) . '?page=backwpup&action=abort', 'abort-job' ) . "\">" . __( 'Abort!', 'backwpup' ) . "</a>";
						}
					}
					$r .= $this->row_actions( $actions );
					$r .= '</td>';
					break;
				case 'type':
					$r .= "<td $attributes>";
					$r .= backwpup_job_types( backwpup_get_option( 'job_' . $jobid, 'type' ), false );
					$r .= "</td>";
					break;
				case 'info':
					$r .= "<td $attributes>";
					if ( in_array( 'DB', backwpup_get_option( 'job_' . $jobid, 'type' ) ) || in_array( 'OPTIMIZE', backwpup_get_option( 'job_' . $jobid, 'type' ) ) || in_array( 'CHECK', backwpup_get_option( 'job_' . $jobid, 'type' ) ) ) {
						if (!backwpup_get_option( 'job_' . $jobid, 'wpdbsettings')) {
							$backwpupsql=@mysql_connect(backwpup_get_option( 'job_' . $jobid, 'dbhost' ),backwpup_get_option( 'job_' . $jobid, 'dbuser' ),backwpup_decrypt(backwpup_get_option( 'job_' . $jobid, 'dbpassword' )),true);
							@mysql_set_charset( backwpup_get_option('job_' . $jobid, 'dbcharset' ), $backwpupsql );
							$dbname= ackwpup_get_option( 'job_' . $jobid, 'dbname' );
						} else {
							$backwpupsql=$wpdb->dbh;
							$dbname= DB_NAME;
						}
						$dbsize = array( 'size'=> 0,
										 'num' => 0,
										 'rows'=> 0 );
						$res = mysql_query( "SHOW TABLE STATUS FROM `" . $dbname . "`" );
						while ( $tablevalue=mysql_fetch_assoc($res) ) {
							if ( ! in_array( $tablevalue['Name'], backwpup_get_option( 'job_' . $jobid, 'dbexclude' ) ) ) {
								$dbsize['size'] = $dbsize['size'] + $tablevalue["Data_length"] + $tablevalue["Index_length"];
								$dbsize['num'] ++;
								$dbsize['rows'] = $dbsize['rows'] + $tablevalue["Rows"];
							}
						}
						$r .= sprintf( __( "DB Size: %s", "backwpup" ), size_format( $dbsize['size'], 2 ) ) . "<br />";
						if ( 'excerpt' == $mode ) {
							$r .= sprintf( __( "DB Tables: %d", "backwpup" ), $dbsize['num'] ) . "<br />";
							$r .= sprintf( __( "DB Rows: %d", "backwpup" ), $dbsize['rows'] ) . "<br />";
						}
						if (!backwpup_get_option( 'job_' . $jobid, 'wpdbsettings'))
							mysql_close($backwpupsql);
					}
					if ( in_array( 'FILE', backwpup_get_option( 'job_' . $jobid, 'type' ) ) ) {
						if ( false === ($files = get_transient( 'backwpup_file_info_' . $jobid )) )
							$r .= "<img class=\"waiting\" src=\"" . esc_url( backwpup_admin_url( 'images/wpspin_light.gif' ) ) . "\" id=\"image-wait-" . $jobid . "\" />";
						else {
							$r .= sprintf( __( "Files Size: %s", "backwpup" ), size_format( $files['size'], 2 ) ) . "<br />";
							if ( 'excerpt' == $mode ) {
								$r .= sprintf( __( "Folder count: %d", "backwpup" ), $files['folder'] ) . "<br />";
								$r .= sprintf( __( "Files count: %d", "backwpup" ), $files['files'] ) . "<br />";
							}
						}
					}
					$r .= "</td>";
					break;
				case 'next':
					$r .= "<td $attributes>";
					if ( $backupdata && $backupdata['JOBID'] == $jobid ) {
						$runtime = current_time( 'timestamp' ) - backwpup_get_option( 'job_' . $jobid, 'starttime' );
						$r .= __( 'Running since:', 'backwpup' ) . ' ' . $runtime . ' ' . __( 'sec.', 'backwpup' );
					} elseif ( backwpup_get_option( 'job_' . $jobid, 'activetype' ) == 'wpcron' ) {
						if ( $nextrun = wp_next_scheduled( 'backwpup_cron', array( 'main'=> 'job_' . $jobid ) ) ) {
							$offset = get_option( 'gmt_offset' ) * 3600;
							$r .= date_i18n( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), $nextrun + $offset ) . ' by WP-Cron';
						} else {
							$r .= __( 'Not scheduled!', 'backwpup' );
						}
					} elseif ( backwpup_get_option( 'job_' . $jobid, 'activetype' ) == 'backwpupapi' ) {
						$r .= date_i18n( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), backwpup_get_option( 'job_' . $jobid, 'cronnextrun' ) ) . ' by BackWPup Cron Service';
					} else {
						$r .= __( 'Inactive', 'backwpup' );
					}
					if ( 'excerpt' == $mode ) {
						$r .= '<br />' . __( '<a href="http://wikipedia.org/wiki/Cron" target="_blank">Cron</a>:', 'backwpup' ) . ' ' . backwpup_get_option( 'job_' . $jobid, 'cron' );
					}
					$r .= "</td>";
					break;
				case 'last':
					$r .= "<td $attributes>";
					if ( backwpup_get_option( 'job_' . $jobid, 'lastrun' ) ) {
						$r .= date_i18n( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), backwpup_get_option( 'job_' . $jobid, 'lastrun' ) );
						if ( backwpup_get_option( 'job_' . $jobid, 'lastruntime' ) )
							$r .= '<br />' . __( 'Runtime:', 'backwpup' ) . ' ' . backwpup_get_option( 'job_' . $jobid, 'lastruntime' ) . ' ' . __( 'sec.', 'backwpup' );
					} else {
						$r .= __( 'None', 'backwpup' );
					}
					$r .= "<br />";
					if ( backwpup_get_option( 'job_' . $jobid, 'lastbackupdownloadurl' ) )
						$r .= "<a href=\"" . wp_nonce_url( backwpup_get_option( 'job_' . $jobid, 'lastbackupdownloadurl' ), 'download-backup' ) . "\" title=\"" . __( 'Download last Backup', 'backwpup' ) . "\">" . __( 'Download', 'backwpup' ) . "</a> | ";
					if ( backwpup_get_option( 'job_' . $jobid, 'logfile' ) )
						$r .= "<a href=\"" . wp_nonce_url( backwpup_admin_url( 'admin.php' ) . '?page=backwpupworking&logfile=' . backwpup_get_option( 'job_' . $jobid, 'logfile' ), 'view-log_' . basename( backwpup_get_option( 'job_' . $jobid, 'logfile' ) ) ) . "\" title=\"" . __( 'View last Log', 'backwpup' ) . "\">" . __( 'Log', 'backwpup' ) . "</a>";
					$r .= "</td>";
					break;
			}
		}
		$r .= '</tr>';
		return $r;
	}
}