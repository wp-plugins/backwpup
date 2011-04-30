<?PHP
include_once( trailingslashit(ABSPATH).'wp-admin/includes/class-wp-list-table.php' );


class BackWPup_Jobs_Table extends WP_List_Table {
	function BackWPup_Jobs_Table() {
		global $current_screen;
		parent::WP_List_Table( array(
			'screen' => $current_screen,
			'plural' => 'jobs',
			'singular' => 'job'
		) );
	}
	
	function check_permissions() {
		if ( !current_user_can( BACKWPUP_USER_CAPABILITY ) )
			wp_die( __( 'No rights' ) );
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
		$posts_columns = array();
		$posts_columns['cb'] = '<input type="checkbox" />';
		$posts_columns['id'] = __('ID','backwpup');
		$posts_columns['jobname'] = __('Job Name','backwpup');
		$posts_columns['type'] = __('Type','backwpup');
		$posts_columns['info'] = __('Information','backwpup');
		$posts_columns['next'] = __('Next Run','backwpup');
		$posts_columns['last'] = __('Last Run','backwpup');
		return $posts_columns;
	}

	function get_sortable_columns() {
		return array();
	}	

	function get_hidden_columns() {
		return (array) get_user_option( 'backwpup_jobs_columnshidden' );
	}
	
	function display_rows() {
		$style = '';
		foreach ( $this->items as $jobid => $jobvalue ) {
			$jobvalue=backwpup_check_job_vars($jobvalue,$jobid);//Set and check job settings
			$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';
			echo "\n\t", $this->single_row( $jobid, $jobvalue, $style );
		}
	}
	
	function single_row( $jobid, $jobvalue, $style = '' ) {
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
					$r .=  "<td $attributes><strong><a href=\"".wp_nonce_url('admin.php?page=BackWPup&subpage=edit&jobid='.$jobid, 'edit-job')."\" title=\"".__('Edit:','backwpup').$jobvalue['name']."\">".esc_html($jobvalue['name'])."</a></strong>";
					$actions = array();
					if (!is_file(rtrim(str_replace('\\','/',sys_get_temp_dir()),'/').'/.backwpup_running')) {
						$actions['edit'] = "<a href=\"" . wp_nonce_url('admin.php?page=BackWPup&subpage=edit&jobid='.$jobid, 'edit-job') . "\">" . __('Edit') . "</a>";
						$actions['copy'] = "<a href=\"" . wp_nonce_url('admin.php?page=BackWPup&action=copy&jobid='.$jobid, 'copy-job_'.$jobid) . "\">" . __('Copy','backwpup') . "</a>";
						$actions['export'] = "<a href=\"" . wp_nonce_url('admin.php?page=BackWPup&action=export&jobs[]='.$jobid, 'bulk-jobs') . "\">" . __('Export','backwpup') . "</a>";
						$actions['delete'] = "<a class=\"submitdelete\" href=\"" . wp_nonce_url('admin.php?page=BackWPup&action=delete&jobs[]='.$jobid, 'bulk-jobs') . "\" onclick=\"if ( confirm('" . esc_js(__("You are about to delete this Job. \n  'Cancel' to stop, 'OK' to delete.","backwpup")) . "') ) { return true;}return false;\">" . __('Delete') . "</a>";
						$actions['runnow'] = "<a href=\"" . wp_nonce_url('admin.php?page=BackWPup&subpage=runnow&jobid='.$jobid, 'runnow-job_'.$jobid) . "\">" . __('Run Now','backwpup') . "</a>";
					} else {
						$actions['abort'] = "<a href=\"" . wp_nonce_url('admin.php?page=BackWPup&action=abort', 'abort-job') . "\">" . __('Abort!!!','backwpup') . "</a>";
					}
					$action_count = count($actions);
					$i = 0;
					$r .=  '<br /><div class="row-actions">';
					foreach ( $actions as $action => $linkaction ) {
						++$i;
						( $i == $action_count ) ? $sep = '' : $sep = ' | ';
						$r .=  "<span class='$action'>$linkaction$sep</span>";
					}
					$r .=  '</div>';
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
					if ($jobvalue['starttime']>0 and !empty($jobvalue['logfile'])) {
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
						$r .="<a href=\"" . wp_nonce_url('admin.php?page=BackWPup&subpage=view_log&logfile='.$jobvalue['logfile'], 'view-log_'.basename($jobvalue['logfile'])) . "\" title=\"".__('View last Log','backwpup')."\">" . __('Log','backwpup') . "</a><br />";

					$r .=  "</td>";
					break;
			}
		}
		$r .= '</tr>';
		return $r;
	}
}

class BackWPup_Logs_Table extends WP_List_Table {
	function BackWPup_Logs_Table() {
		global $current_screen;
		parent::WP_List_Table( array(
			'screen' => $current_screen,
			'plural' => 'logs',
			'singular' => 'log'
		) );
	}
	
	function check_permissions() {
		if ( !current_user_can( BACKWPUP_USER_CAPABILITY ) )
			wp_die( __( 'No rights' ) );
	}	
	
	function prepare_items() {	
		
		$per_page = (int) get_user_option( 'backwpup_logs_per_page' );
		if ( empty( $per_page ) || $per_page < 1 )
			$per_page = 20;	
		
		//load logs
		$cfg=get_option('backwpup');
		$logfiles=array();
		if ( $dir = @opendir( $cfg['dirlogs'] ) ) {
			while (($file = readdir( $dir ) ) !== false ) {
				if (is_file($cfg['dirlogs'].'/'.$file) and 'backwpup_log_' == substr($file,0,strlen('backwpup_log_')) and  ('.html' == substr($file,-5) or '.html.gz' == substr($file,-8))) 
					$logfiles[]=$file;
			}
			closedir( $dir );
			if ( !isset( $_REQUEST['orderby'] ) or $_REQUEST['orderby']=='log') {
				if (isset($_REQUEST['order']) and $_REQUEST['order']=='asc')
					sort($logfiles);
				else
					rsort($logfiles);
			}
			if (!isset( $_REQUEST['orderby'] ) and !isset( $_REQUEST['order'] ))
				rsort($logfiles);
		}
		//by page
		$start=intval( ( $this->get_pagenum() - 1 ) * $per_page );
		$end=$start+$per_page;
		if ($end>count($logfiles))
			$end=count($logfiles);
		
		for ($i=$start;$i<$end;$i++) {
			$this->items[] = $logfiles[$i];
		}
		
		$this->set_pagination_args( array(
			'total_items' => count($logfiles),
			'per_page' => $per_page
		) );

	}

	function get_sortable_columns() {
		return array(
			'log'    => 'log',
		);
	}
	
	function no_items() {
		_e( 'No Logs.','backwpup');
	}
	
	function get_bulk_actions() {
		$actions = array();
		$actions['delete'] = __( 'Delete' );

		return $actions;
	}
	
	function get_columns() {
		$posts_columns = array();
		$posts_columns['cb'] = '<input type="checkbox" />';
		$posts_columns['id'] = __('Job','backwpup');
		$posts_columns['type'] = __('Type','backwpup');
		$posts_columns['log'] = __('Backup/Log Date/Time','backwpup');
		$posts_columns['status'] = __('Status','backwpup');
		$posts_columns['size'] = __('Size','backwpup');
		$posts_columns['runtime'] = __('Runtime','backwpup');
		return $posts_columns;
	}

	function get_hidden_columns() {
		return (array) get_user_option( 'backwpup_logs_columnshidden' );
	}
	
	function display_rows() {
		$style = '';
		$cfg=get_option('backwpup');
		foreach ( $this->items as $logfile ) {
			$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';
			$logdata=backwpup_read_logheader($cfg['dirlogs'].$logfile);
			echo "\n\t", $this->single_row( $cfg['dirlogs'].$logfile, $logdata, $style );
		}
	}
	
	function single_row( $logfile, $logdata, $style = '' ) {
		list( $columns, $hidden, $sortable ) = $this->get_column_info();
		$r = "<tr id='".basename($logfile)."'$style>";
		foreach ( $columns as $column_name => $column_display_name ) {
			$class = "class=\"$column_name column-$column_name\"";

			$style = '';
			if ( in_array( $column_name, $hidden ) )
				$style = ' style="display:none;"';

			$attributes = "$class$style";
			
			switch($column_name) {
				case 'cb':
					$r .= '<th scope="row" class="check-column"><input type="checkbox" name="logfiles[]" value="'. esc_attr(basename($logfile)) .'" /></th>';
					break;
				case 'id':
					$r .= "<td $attributes>".$logdata['jobid']."</td>"; 
					break;
				case 'type':
					$r .= "<td $attributes>";
					$r .= backwpup_backup_types($logdata['type'],false);
					$r .= "</td>"; 
					break;
				case 'log':				
					$r .= "<td $attributes><strong><a href=\"".wp_nonce_url('admin.php?page=BackWPup&subpage=view_log&logfile='.$logfile, 'view-log_'.basename($logfile))."\" title=\"".__('View log','backwpup')."\">".date_i18n(get_option('date_format'),$logdata['logtime'])." ".date_i18n(get_option('time_format'),$logdata['logtime']).": <i>".$logdata['name']."</i></a></strong>";
					$actions = array();
					$actions['view'] = "<a href=\"" . wp_nonce_url('admin.php?page=BackWPup&subpage=view_log&logfile='.$logfile, 'view-log_'.basename($logfile)) . "\">" . __('View','backwpup') . "</a>";
					$actions['delete'] = "<a class=\"submitdelete\" href=\"" . wp_nonce_url('admin.php?page=BackWPup&subpage=logs&action=delete&paged='.$this->get_pagenum().'&logfiles[]='.basename($logfile), 'bulk-logs') . "\" onclick=\"return showNotice.warn();\">" . __('Delete') . "</a>";
					$actions['download'] = "<a href=\"" . wp_nonce_url('admin.php?page=BackWPup&subpage=logs&action=download&file='.$logfile, 'download-backup_'.basename($logfile)) . "\">" . __('Download','backwpup') . "</a>";
					$action_count = count($actions);
					$i = 0;
					$r .= '<br /><div class="row-actions">';
					foreach ( $actions as $action => $linkaction ) {
						++$i;
						( $i == $action_count ) ? $sep = '' : $sep = ' | ';
						$r .= "<span class='$action'>$linkaction$sep</span>";
					}
					$r .= '</div>';
					$r .= "</td>";
					break;
				case 'status':
					$r .= "<td $attributes>";
					if($logdata['errors']>0 or $logdata['warnings']>0) { 
						if ($logdata['errors']>0)
							$r .= '<span style="color:red;">'.$logdata['errors'].' '.__('ERROR(S)','backwpup').'</span><br />'; 
						if ($logdata['warnings']>0)
							$r .= '<span style="color:yellow;">'.$logdata['warnings'].' '.__('WARNING(S)','backwpup').'</span>'; 
					} else { 
						$r .= '<span style="color:green;">'.__('OK','backwpup').'</span>';
					} 
					$r .= "</td>"; 
					break;
				case 'size':
					$r .= "<td $attributes>";
					if (!empty($logdata['backupfilesize'])) {
						$r .= backwpup_formatBytes($logdata['backupfilesize']);
					} else {
						$r .= __('only Log','backwpup');
					}
					$r .= "</td>"; 
					break;
				case 'runtime':
					$r .= "<td $attributes>";
					$r .= $logdata['runtime'].' '.__('sec.','backwpup');
					$r .= "</td>"; 
					break;					
			}
		}
		$r .= '</tr>';
		return $r;
	}
}

class BackWPup_Backups_Table extends WP_List_Table {
	function BackWPup_Backups_Table() {
		global $current_screen;
		parent::WP_List_Table( array(
			'screen' => $current_screen,
			'plural' => 'backups',
			'singular' => 'backup'
		) );
	}
	
	function check_permissions() {
		if ( !current_user_can( BACKWPUP_USER_CAPABILITY ) )
			wp_die( __( 'No rights' ) );
	}	
	
	function prepare_items() {	
		
		$per_page = (int) get_user_option( 'backwpup_backups_per_page' );
		if ( empty( $per_page ) || $per_page < 1 )
			$per_page = 20;	
		
		$backups=get_option('backwpup_backups_chache');		
		
		//by page
		$start=intval( ( $this->get_pagenum() - 1 ) * $per_page );
		$end=$start+$per_page;
		if ($end>count($backups))
			$end=count($backups);
		
		for ($i=$start;$i<$end;$i++) {
			$this->items[] = $backups[$i];
		}
		
		$this->set_pagination_args( array(
			'total_items' => count($backups),
			'per_page' => $per_page	
		) );

	}
	
	function no_items() {
		_e( 'No Backups.','backwpup');
	}
	
	
	function get_bulk_actions() {
		$actions = array();
		$actions['delete'] = __( 'Delete' );

		return $actions;
	}
	
	function get_columns() {
		$posts_columns = array();
		$posts_columns['cb'] = '<input type="checkbox" />';
		$posts_columns['backup'] = __('Backupfile','backwpup');
		$posts_columns['size'] = __('Size','backwpup');
		return $posts_columns;
	}

	function get_sortable_columns() {
		return array();
	}	

	function get_hidden_columns() {
		return (array) get_user_option( 'backwpup_backups_columnshidden' );
	}
	
	function display_rows() {
		$style = '';
		$jobs=get_option('backwpup_jobs'); //Load jobs
		foreach ( $this->items as $backup ) {
			$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';
			echo "\n\t", $this->single_row( $backup, backwpup_check_job_vars($jobs[$backup['jobid']],$backup['jobid']), $style );
		}
	}
	
	function single_row( $backup, $jobvalue, $style = '' ) {
		list( $columns, $hidden, $sortable ) = $this->get_column_info();
		$r = "<tr $style>";
		foreach ( $columns as $column_name => $column_display_name ) {
			$class = "class=\"$column_name column-$column_name\"";

			$style = '';
			if ( in_array( $column_name, $hidden ) )
				$style = ' style="display:none;"';

			$attributes = "$class$style";
			
			switch($column_name) {
				case 'cb':
					$r .= '<th scope="row" class="check-column"><input type="checkbox" name="backupfiles[]" value="'. esc_attr($backup['file'].'|'.$backup['jobid'].'|'.$backup['type']) .'" /></th>';
					break;
				case 'backup':
					$dir=dirname($backup['file']);
					if ($dir=='.')
						$dir='';
					else
						$dir.='/';
					if ($backup['type']=='FOLDER') {
						$r .= "<td $attributes><strong>".basename($backup['file'])."</strong><br />".$dir;
					} elseif ($backup['type']=='S3') {
						$r .= "<td $attributes><strong>".basename($backup['file'])."</strong><br />S3://".$jobvalue['awsBucket']."/".$dir;
					} elseif ($backup['type']=='FTP') {
						$r .= "<td $attributes><strong>".basename($backup['file'])."</strong><br />ftp://".$jobvalue['ftphost'].$dir;
					} elseif ($backup['type']=='RSC') {
						$r .= "<td $attributes><strong>".basename($backup['file'])."</strong><br />RSC://".$jobvalue['rscContainer']."/".$dir;
					} elseif ($backup['type']=='MSAZURE') {
						$r .= "<td $attributes><strong>".basename($backup['file'])."</strong><br />azure://".$jobvalue['msazureContainer']."/".$dir;
					} elseif ($backup['type']=='DROPBOX') {
						$r .= "<td $attributes><strong>".basename($backup['file'])."</strong><br />dropbox:/".$dir;
					} elseif ($backup['type']=='SUGARSYNC') {
						$r .= "<td $attributes><strong>".$backup['filename']."</strong><br />sugarsync://magicBriefcase/".$jobvalue['sugardir'];
					} 
					$actions = array();
					$actions['delete'] = "<a class=\"submitdelete\" href=\"" . wp_nonce_url('admin.php?page=BackWPup&subpage=backups&action=delete&paged='.$this->get_pagenum().'&backupfiles[]='.esc_attr($backup['file'].'|'.$backup['jobid'].'|'.$backup['type']), 'bulk-backups') . "\" onclick=\"if ( confirm('" . esc_js(__("You are about to delete this Backup Archive. \n  'Cancel' to stop, 'OK' to delete.","backwpup")) . "') ) { return true;}return false;\">" . __('Delete') . "</a>";
					$actions['download'] = "<a href=\"" . wp_nonce_url($backup['downloadurl'], 'download-backup') . "\">" . __('Download','backwpup') . "</a>";
					$action_count = count($actions);
					$i = 0;
					$r .= '<br /><div class="row-actions">';
					foreach ( $actions as $action => $linkaction ) {
						++$i;
						( $i == $action_count ) ? $sep = '' : $sep = ' | ';
						$r .= "<span class='$action'>$linkaction$sep</span>";
					}
					$r .= '</div>';
					$r .= "</td>";
					break;
				case 'size':
					$r .= "<td $attributes>";
					if (!empty($backup['filesize']) and $backup['filesize']!=-1) {
						$r .= backwpup_formatBytes($backup['filesize']);
					} else {
						$r .= __('?','backwpup');
					}
					$r .= "</td>";
					break;
			}
		}
		$r .= '</tr>';
		return $r;
	}
}
?>