<?PHP
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

include_once( trailingslashit(ABSPATH).'wp-admin/includes/class-wp-list-table.php');

class BackWPup_Backups_Table extends WP_List_Table {
	
	private $jobid=1;
	private $dest='FOLDER';
	
	function __construct() {
		parent::__construct( array(
			'plural' => 'backups',
			'singular' => 'backup',
			'ajax' => true
		) );
	}
	
	function ajax_user_can() {
		return current_user_can(BACKWPUP_USER_CAPABILITY);
	}
	
	function prepare_items() {	
		
		$per_page = $this->get_items_per_page('backwpupbackups_per_page');
		if ( empty( $per_page ) || $per_page < 1 )
			$per_page = 20;
		
		if (isset($_GET['jobdest'])) {
			$jobdest=$_GET['jobdest'];
		} else {
			$jobdests=$this->get_dest_list();
			if (empty($jobdests))
				$jobdests=array('_');
			$jobdest=$jobdests[0];
			$_GET['jobdest']=$jobdests[0];
		}
		
		list($this->jobid,$this->dest)=explode('_',$jobdest);

		$this->items=backwpup_get_option('temp',$jobdest,false);
		//if no itmes brake
		if (!$this->items) {
			$this->items='';
			return;
		}
		
		//Sorting
		$order=isset($_GET['order']) ? $_GET['order'] : 'desc';
		$orderby=isset($_GET['orderby']) ? $_GET['orderby'] : 'time';
		$tmp = Array();
		if ($orderby=='time') {
			if ($order=='asc') {
				foreach($this->items as &$ma)
					$tmp[] = &$ma["time"];
				array_multisort($tmp, SORT_ASC, $this->items);
			} else {
				foreach($this->items as &$ma)
					$tmp[] = &$ma["time"];
				array_multisort($tmp, SORT_DESC, $this->items);
			}
		}		
		elseif ($orderby=='file') {
			if ($order=='asc') {
				foreach($this->items as &$ma)
					$tmp[] = &$ma["filename"];
				array_multisort($tmp, SORT_ASC, $this->items);
			} else {
				foreach($this->items as &$ma)
					$tmp[] = &$ma["filename"];
				array_multisort($tmp, SORT_DESC, $this->items);
			}
		}
		elseif ($orderby=='folder') {
			if ($order=='asc') {
				foreach($this->items as &$ma)
					$tmp[] = &$ma["folder"];
				array_multisort($tmp, SORT_ASC, $this->items);
			} else {
				foreach($this->items as &$ma)
					$tmp[] = &$ma["folder"];
				array_multisort($tmp, SORT_DESC, $this->items);
			}
		}
		elseif ($orderby=='size') {
			if ($order=='asc') {
				foreach($this->items as &$ma)
					$tmp[] = &$ma["filesize"];
				array_multisort($tmp, SORT_ASC, $this->items);
			} else {
				foreach($this->items as &$ma)
					$tmp[] = &$ma["filesize"];
				array_multisort($tmp, SORT_DESC, $this->items);
			}
		}	

		//by page
		$start=intval( ( $this->get_pagenum() - 1 ) * $per_page );
		$end=$start+$per_page;
		if ($end>count($this->items))
			$end=count($this->items);
	
		$this->set_pagination_args( array(
			'total_items' => count($this->items),
			'per_page' => $per_page,
			'jobdest' => $jobdest,
			'orderby' => $orderby,
			'order' => $order
		) );

	}
	
	function no_items() {
		_e( 'No Files found. (List will be generated on next backup)','backwpup');
	}
	
	function get_bulk_actions() {
		$actions = array();
		$actions['delete'] = __( 'Delete' );
		return $actions;
	}

	function extra_tablenav( $which ) {
		if ( 'top' != $which )
			return;
		echo '<div class="alignleft actions">';
		echo "<select name=\"jobdest\" id=\"jobdest\" class=\"postform\">\n";
		foreach ($this->get_dest_list() as $jobdest) {
			list($jobid,$dest)=explode('_',$jobdest);
			echo "\t<option value=\"".$jobdest."\" ".selected($this->jobid.'_'.$this->dest,$jobdest).">".$dest.": ".esc_html(backwpup_get_option('job_'.$jobid,'name'))."</option>\n";
		}
		echo "</select>\n";
		submit_button( __('Change Destination','backwpup'), 'secondary', '', false, array( 'id' => 'post-query-submit' ) );
		echo '</div>';
	}
	
	function get_dest_list() {
		global $wpdb;
		$jobdest=array();
		$jobids=$wpdb->get_col("SELECT value FROM `".$wpdb->prefix."backwpup` WHERE main LIKE 'job_%' AND name='jobid' ORDER BY value ASC");
		if (!empty($jobids)) {
			foreach ($jobids as $jobid) {
				$main='job_'.$jobid;
				if (backwpup_get_option($main,'backupdir') and is_dir(backwpup_get_option($main,'backupdir')))
					$jobdest[]=$jobid.'_FOLDER';
				foreach (explode(',',strtoupper(BACKWPUP_DESTS)) as $dest) {
					if ($dest=='S3' and backwpup_get_option($main,'awsAccessKey') and backwpup_get_option($main,'awsSecretKey') and backwpup_get_option($main,'awsBucket'))
						$jobdest[]=$jobid.'_'.$dest;
					if ($dest=='GSTORAGE' and backwpup_get_option($main,'GStorageAccessKey') and backwpup_get_option($main,'GStorageSecret') and backwpup_get_option($main,'GStorageBucket'))
						$jobdest[]=$jobid.'_'.$dest;
					if ($dest=='DROPBOX' and backwpup_get_option($main,'dropetoken') and backwpup_get_option($main,'dropesecret'))
						$jobdest[]=$jobid.'_'.$dest;
					if ($dest=='RSC' and backwpup_get_option($main,'rscUsername') and backwpup_get_option($main,'rscAPIKey') and backwpup_get_option($main,'rscContainer'))
						$jobdest[]=$jobid.'_'.$dest;
					if ($dest=='FTP' and backwpup_get_option($main,'ftphost') and function_exists('ftp_connect') and backwpup_get_option($main,'ftpuser') and backwpup_get_option($main,'ftppass'))
						$jobdest[]=$jobid.'_'.$dest;
					if ($dest=='MSAZURE' and backwpup_get_option($main,'msazureHost') and backwpup_get_option($main,'msazureAccName') and backwpup_get_option($main,'msazureKey') and backwpup_get_option($main,'msazureContainer'))
						$jobdest[]=$jobid.'_'.$dest;
					if ($dest=='SUGARSYNC' and backwpup_get_option($main,'sugarpass') and backwpup_get_option($main,'sugarpass'))
						$jobdest[]=$jobid.'_'.$dest;
				}

			}
		}
		return $jobdest;
	}

	function get_columns() {
		$posts_columns = array();
		$posts_columns['cb'] = '<input type="checkbox" />';
		$posts_columns['file'] = __('File','backwpup');
		$posts_columns['folder'] = __('Folder','backwpup');
		$posts_columns['size'] = __('Size','backwpup');
		$posts_columns['folder'] = __('Folder','backwpup');
		$posts_columns['time'] = __('Time','backwpup');
		return $posts_columns;
	}

	function get_sortable_columns() {
		return array(
			'file'    => array('file',false),
			'folder'    => 'folder',
			'size'    => 'size',
			'time'    => array('time',false)
		);
	}	
	
	function display_rows() {
		$style = '';
		foreach ( $this->items as $backup ) {
			$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';
			echo "\n\t", $this->single_row( $backup, $style );
		}
	}
	
	function single_row( $backup, $style = '' ) {
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
					$r .= '<th scope="row" class="check-column"><input type="checkbox" name="backupfiles[]" value="'. esc_attr($backup['file']) .'" /></th>';
					break;
				case 'file':
					$r .= "<td $attributes><strong>".$backup['filename']."</strong>";
					$actions = array();
					$actions['delete'] = "<a class=\"submitdelete\" href=\"" . wp_nonce_url(backwpup_admin_url('admin.php').'?page=backwpupbackups&action=delete&jobdest='.$this->jobid.','.$this->dest.'&paged='.$this->get_pagenum().'&backupfiles[]='.esc_attr($backup['file']), 'bulk-backups') . "\" onclick=\"if ( confirm('" . esc_js(__("You are about to delete this Backup Archive. \n  'Cancel' to stop, 'OK' to delete.","backwpup")) . "') ) { return true;}return false;\">" . __('Delete') . "</a>";
					$actions['download'] = "<a href=\"" . wp_nonce_url($backup['downloadurl'], 'download-backup') . "\">" . __('Download','backwpup') . "</a>";
					$r .= $this->row_actions($actions);
					$r .= "</td>";
					break;
				case 'folder':
					$r .= "<td $attributes>";
					$r .= $backup['folder'];
					$r .= "</td>";
					break;
				case 'size':
					$r .= "<td $attributes>";
					if (!empty($backup['filesize']) and $backup['filesize']!=-1) {
						$r .= backwpup_format_bytes($backup['filesize']);
					} else {
						$r .= __('?','backwpup');
					}
					$r .= "</td>";
					break;
				case 'time':
					$r .= "<td $attributes>";
					$r .= date_i18n(get_option('date_format'),$backup['time']).' @ '. date_i18n(get_option('time_format'),$backup['time']); 
					$r .= "</td>";
					break;
			}
		}
		$r .= '</tr>';
		return $r;
	}
}





?>