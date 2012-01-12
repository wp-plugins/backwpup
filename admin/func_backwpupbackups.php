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
				$jobdests=array(',');
			$jobdest=$jobdests[0];
			$_GET['jobdest']=$jobdests[0];
		}
		
		list($this->jobid,$this->dest)=explode(',',$jobdest);
		
		$backups=backwpup_get_option('temp','backups');
		if (!empty($backups) or $this->dest!=$backups[0]['DEST'] or $this->jobid!=$backups[0]['JOBID']) {
			$backups=$this->get_backup_files($this->jobid,$this->dest);
			backwpup_update_option('temp','backups',$backups);
		}
		
		//if no itmes brake
		if (empty($backups)) {
			$this->items='';
			return;
		}
		
		//Sorting
		$order=isset($_GET['order']) ? $_GET['order'] : 'desc';
		$orderby=isset($_GET['orderby']) ? $_GET['orderby'] : 'time';
		$tmp = Array();
		if ($orderby=='time') {
			if ($order=='asc') {
				foreach($backups as &$ma)
					$tmp[] = &$ma["time"];
				array_multisort($tmp, SORT_ASC, $backups);			
			} else {
				foreach($backups as &$ma)
					$tmp[] = &$ma["time"];
				array_multisort($tmp, SORT_DESC, $backups);	
			}
		}		
		elseif ($orderby=='file') {
			if ($order=='asc') {
				foreach($backups as &$ma)
					$tmp[] = &$ma["filename"];
				array_multisort($tmp, SORT_ASC, $backups);			
			} else {
				foreach($backups as &$ma)
					$tmp[] = &$ma["filename"];
				array_multisort($tmp, SORT_DESC, $backups);	
			}
		}
		elseif ($orderby=='folder') {
			if ($order=='asc') {
				foreach($backups as &$ma)
					$tmp[] = &$ma["folder"];
				array_multisort($tmp, SORT_ASC, $backups);			
			} else {
				foreach($backups as &$ma)
					$tmp[] = &$ma["folder"];
				array_multisort($tmp, SORT_DESC, $backups);	
			}
		}
		elseif ($orderby=='size') {
			if ($order=='asc') {
				foreach($backups as &$ma)
					$tmp[] = &$ma["filesize"];
				array_multisort($tmp, SORT_ASC, $backups);			
			} else {
				foreach($backups as &$ma)
					$tmp[] = &$ma["filesize"];
				array_multisort($tmp, SORT_DESC, $backups);	
			}
		}	

		//by page
		$start=intval( ( $this->get_pagenum() - 1 ) * $per_page );
		$end=$start+$per_page;
		if ($end>count($backups))
			$end=count($backups);

		$this->items=array();
		for ($i=$start;$i<$end;$i++)
			$this->items[]=$backups[$i];
	
		$this->set_pagination_args( array(
			'total_items' => count($backups),
			'per_page' => $per_page,
			'jobdest' => $jobdest,
			'orderby' => $orderby,
			'order' => $order
		) );

	}
	
	function no_items() {
		_e( 'No Files found.','backwpup');
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
			list($jobid,$dest)=explode(',',$jobdest);
			$jobname=backwpup_get_option('job_'.$this->jobid,'name');
			echo "\t<option value=\"".$jobdest."\" ".selected($this->jobid.','.$this->dest,$jobdest).">".$dest.": ".esc_html($jobname)."</option>\n";
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
					$jobdest[]=$jobid.',FOLDER';
				foreach (explode(',',strtoupper(BACKWPUP_DESTS)) as $dest) {
					if ($dest=='S3' and backwpup_get_option($main,'awsAccessKey') and backwpup_get_option($main,'awsSecretKey') and backwpup_get_option($main,'awsBucket'))
						$jobdest[]=$jobid.','.$dest;
					if ($dest=='GSTORAGE' and backwpup_get_option($main,'GStorageAccessKey') and backwpup_get_option($main,'GStorageSecret') and backwpup_get_option($main,'GStorageBucket'))
						$jobdest[]=$jobid.','.$dest;
					if ($dest=='DROPBOX' and backwpup_get_option($main,'dropetoken') and backwpup_get_option($main,'dropesecret'))
						$jobdest[]=$jobid.','.$dest;
					if ($dest=='RSC' and backwpup_get_option($main,'rscUsername') and backwpup_get_option($main,'rscAPIKey') and backwpup_get_option($main,'rscContainer'))
						$jobdest[]=$jobid.','.$dest;
					if ($dest=='FTP' and backwpup_get_option($main,'ftphost') and function_exists('ftp_connect') and backwpup_get_option($main,'ftpuser') and backwpup_get_option($main,'ftppass'))
						$jobdest[]=$jobid.','.$dest;
					if ($dest=='MSAZURE' and backwpup_get_option($main,'msazureHost') and backwpup_get_option($main,'msazureAccName') and backwpup_get_option($main,'msazureKey') and backwpup_get_option($main,'msazureContainer'))
						$jobdest[]=$jobid.','.$dest;
					if ($dest=='SUGARSYNC' and backwpup_get_option($main,'sugarpass') and backwpup_get_option($main,'sugarpass'))
						$jobdest[]=$jobid.','.$dest;					
				}

			}
		}
		return $jobdest;
	}

	//get backup files and infos
	function get_backup_files($jobid,$dest) {
		global $backwpup_message;
		if (empty($jobid) or (!in_array(strtoupper($dest),explode(',',strtoupper(BACKWPUP_DESTS))) and $dest!='FOLDER'))
			return false;
		$main='job_'.$jobid;
		$filecounter=0;
		$files=array();
		//Get files/fileinfo in backup folder
		if ($dest=='FOLDER' and backwpup_get_option($main,'backupdir') and is_dir(backwpup_get_option($main,'backupdir'))) {
			if ( $dir = opendir( backwpup_get_option($main,'backupdir') ) ) {
				while (($file = readdir( $dir ) ) !== false ) {
					if (substr($file,0,1)=='.')
						continue;
					if (is_file(backwpup_get_option($main,'backupdir').$file)) {
						$files[$filecounter]['JOBID']=$jobid;
						$files[$filecounter]['DEST']=$dest;
						$files[$filecounter]['folder']=backwpup_get_option($main,'backupdir');
						$files[$filecounter]['file']=backwpup_get_option($main,'backupdir').$file;
						$files[$filecounter]['filename']=$file;
						$files[$filecounter]['downloadurl']=backwpup_admin_url('admin.php').'?page=backwpupbackups&action=download&file='.backwpup_get_option($main,'backupdir').$file;
						$files[$filecounter]['filesize']=filesize(backwpup_get_option($main,'backupdir').$file);
						$files[$filecounter]['time']=filemtime(backwpup_get_option($main,'backupdir').$file);
						$filecounter++;
					}
				}
				closedir( $dir );
			}
		}
		//Get files/fileinfo from Dropbox
		if ($dest=='DROPBOX' and backwpup_get_option($main,'dropetoken') and backwpup_get_option($main,'dropesecret')) {
			require_once(realpath(dirname(__FILE__).'/../libs/dropbox.php'));
			try {
				$dropbox = new backwpup_Dropbox(backwpup_get_option($main,'droperoot'));
				$dropbox->setOAuthTokens(backwpup_get_option($main,'dropetoken'),backwpup_get_option($main,'dropesecret'));
				$contents = $dropbox->metadatabackwpup_get_option($main,'dropedir');
				if (is_array($contents)) {
					foreach ($contents['contents'] as $object) {
						if ($object['is_dir']!=true) {
							$files[$filecounter]['JOBID']=$jobid;
							$files[$filecounter]['DEST']=$dest;
							$files[$filecounter]['folder']="https://api-content.dropbox.com/1/files/".backwpup_get_option($main,'droperoot')."/".dirname($object['path'])."/";
							$files[$filecounter]['file']=$object['path'];
							$files[$filecounter]['filename']=basename($object['path']);
							$files[$filecounter]['downloadurl']=backwpup_admin_url('admin.php').'?page=backwpupbackups&action=downloaddropbox&file='.$object['path'].'&jobid='.$jobid;
							$files[$filecounter]['filesize']=$object['bytes'];
							$files[$filecounter]['time']=strtotime($object['modified']);
							$filecounter++;
						}
					}
				}
			} catch (Exception $e) {
				$backwpup_message.='DROPBOX: '.$e->getMessage().'<br />';
			}
		}
		//Get files/fileinfo from Sugarsync
		if ($dest=='SUGARSYNC' and backwpup_get_option($main,'sugarpass') and backwpup_get_option($main,'sugarpass')) {
			if (!class_exists('SugarSync'))
				require_once (dirname(__FILE__).'/../libs/sugarsync.php');
			if (class_exists('SugarSync')) {
				try {
					$sugarsync = new SugarSync(backwpup_get_option($main,'sugarpass'),backwpup_decrypt(backwpup_get_option($main,'sugarpass')));
					$dirid=$sugarsync->chdir(backwpup_get_option($main,'sugardir'),backwpup_get_option($main,'sugarroot'));
					$user=$sugarsync->user();
					$dir=$sugarsync->showdir($dirid);
					$getfiles=$sugarsync->getcontents('file');
					if (is_object($getfiles)) {
						foreach ($getfiles->file as $getfile) {
							$files[$filecounter]['JOBID']=$jobid;
							$files[$filecounter]['DEST']=$dest;
							$files[$filecounter]['folder']='https://'.$user->nickname.'.sugarsync.com/'.$dir;
							$files[$filecounter]['file']=(string)$getfile->ref;
							$files[$filecounter]['filename']=utf8_decode((string) $getfile->displayName);
							$files[$filecounter]['downloadurl']=backwpup_admin_url('admin.php').'?page=backwpupbackups&action=downloadsugarsync&file='.(string) $getfile->ref.'&jobid='.$jobid;
							$files[$filecounter]['filesize']=(int) $getfile->size;
							$files[$filecounter]['time']=strtotime((string) $getfile->lastModified);
							$filecounter++;
						}
					}
				} catch (Exception $e) {
					$backwpup_message.='SUGARSYNC: '.$e->getMessage().'<br />';
				}
			}
		}
		//Get files/fileinfo from S3
		if ($dest=='S3' and backwpup_get_option($main,'awsAccessKey') and backwpup_get_option($main,'awsSecretKey') and backwpup_get_option($main,'awsBucket'))	{
			if (!class_exists('AmazonS3'))
				require_once(dirname(__FILE__).'/../libs/aws/sdk.class.php');
			if (class_exists('AmazonS3')) {
				try {
					CFCredentials::set(array('backwpup' => array('key'=>backwpup_get_option($main,'awsAccessKey'),'secret'=>backwpup_get_option($main,'awsSecretKey'),'default_cache_config'=>'','certificate_authority'=>true),'@default' => 'backwpup'));
					$s3 = new AmazonS3();
					if (($contents = $s3->list_objects(backwpup_get_option($main,'awsBucket'),array('prefix'=>backwpup_get_option($main,'awsdir')))) !== false) {
						foreach ($contents->body->Contents as $object) {
							$files[$filecounter]['JOBID']=$jobid;
							$files[$filecounter]['DEST']=$dest;
							$files[$filecounter]['folder']="https://".backwpup_get_option($main,'awsBucket').".s3.amazonaws.com/".dirname((string)$object->Key).'/';
							$files[$filecounter]['file']=(string)$object->Key;
							$files[$filecounter]['filename']=basename($object->Key);
							$files[$filecounter]['downloadurl']=backwpup_admin_url('admin.php').'?page=backwpupbackups&action=downloads3&file='.$object->Key.'&jobid='.$jobid;
							$files[$filecounter]['filesize']=(string)$object->Size;
							$files[$filecounter]['time']=strtotime($object->LastModified);
							$filecounter++;
						}
					}
				} catch (Exception $e) {
					$backwpup_message.='Amazon S3: '.$e->getMessage().'<br />';
				}
			}
		}
		//Get files/fileinfo from Google Storage
		if ($dest=='GSTORAGE' and backwpup_get_option($main,'GStorageAccessKey') and backwpup_get_option($main,'GStorageSecret') and backwpup_get_option($main,'GStorageBucket'))	{
			if (!class_exists('AmazonS3'))
				require_once(dirname(__FILE__).'/../libs/aws/sdk.class.php');
			if (class_exists('AmazonS3')) {
				try {
					CFCredentials::set(array('backwpup' => array('key'=>backwpup_get_option($main,'GStorageAccessKey'),'secret'=>backwpup_get_option($main,'GStorageSecret'),'default_cache_config'=>'','certificate_authority'=>true),'@default' => 'backwpup'));
					$gstorage = new AmazonS3();
					$gstorage->set_hostname('commondatastorage.googleapis.com');
					$gstorage->allow_hostname_override(false);
					if (($contents = $gstorage->list_objects(backwpup_get_option($main,'GStorageBucket'),array('prefix'=>backwpup_get_option($main,'GStoragedir')))) !== false) {
						foreach ($contents->body->Contents as $object) {
							$files[$filecounter]['JOBID']=$jobid;
							$files[$filecounter]['DEST']=$dest;
							$files[$filecounter]['folder']="https://sandbox.google.com/storage/".backwpup_get_option($main,'GStorageBucket')."/".dirname((string)$object->Key).'/';
							$files[$filecounter]['file']=(string)$object->Key;
							$files[$filecounter]['filename']=basename($object->Key);
							$files[$filecounter]['downloadurl']="https://sandbox.google.com/storage/".backwpup_get_option($main,'GStorageBucket')."/".(string)$object->Key;
							$files[$filecounter]['filesize']=(string)$object->Size;
							$files[$filecounter]['time']=strtotime($object->LastModified);
							$filecounter++;
						}
					}
				} catch (Exception $e) {
					$backwpup_message.=sprintf(__('GStorage API: %s','backwpup'),$e->getMessage()).'<br />';
				}
			}
		}
		//Get files/fileinfo from Microsoft Azure
		if ($dest=='MSAZURE' and backwpup_get_option($main,'msazureHost') and backwpup_get_option($main,'msazureAccName') and backwpup_get_option($main,'msazureKey') and backwpup_get_option($main,'msazureContainer')) {
			if (!class_exists('Microsoft_WindowsAzure_Storage_Blob'))
				require_once(dirname(__FILE__).'/../libs/Microsoft/WindowsAzure/Storage/Blob.php');
			if (class_exists('Microsoft_WindowsAzure_Storage_Blob')) {
				try {
					$storageClient = new Microsoft_WindowsAzure_Storage_Blob(backwpup_get_option($main,'msazureHost'),backwpup_get_option($main,'msazureAccName'),backwpup_get_option($main,'msazureKey'));
					$blobs = $storageClient->listBlobs(backwpup_get_option($main,'msazureContainer'),backwpup_get_option($main,'msazuredir'));
					if (is_array($blobs)) {
						foreach ($blobs as $blob) {
							$files[$filecounter]['JOBID']=$jobid;
							$files[$filecounter]['DEST']=$dest;
							$files[$filecounter]['folder']="https://".backwpup_get_option($main,'msazureAccName').'.'.backwpup_get_option($main,'msazureHost')."/".backwpup_get_option($main,'msazureContainer')."/".dirname($blob->Name)."/";
							$files[$filecounter]['file']=$blob->Name;
							$files[$filecounter]['filename']=basename($blob->Name);
							$files[$filecounter]['downloadurl']=backwpup_admin_url('admin.php').'?page=backwpupbackups&action=downloadmsazure&file='.$blob->Name.'&jobid='.$jobid;
							$files[$filecounter]['filesize']=$blob->size;
							$files[$filecounter]['time']=strtotime($blob->lastmodified);
							$filecounter++;
						}
					}
				} catch (Exception $e) {
					$backwpup_message.='MSAZURE: '.$e->getMessage().'<br />';
				}
			}
		}
		//Get files/fileinfo from RSC
		if ($dest=='RSC' and backwpup_get_option($main,'rscUsername') and backwpup_get_option($main,'rscAPIKey') and backwpup_get_option($main,'rscContainer'))	{
			if (!class_exists('CF_Authentication'))
				require_once(dirname(__FILE__).'/../libs/rackspace/cloudfiles.php');
			if (class_exists('CF_Authentication') ) {
				try {
					$auth = new CF_Authentication(backwpup_get_option($main,'rscUsername'), backwpup_get_option($main,'rscAPIKey'));
					$auth->ssl_use_cabundle();
					if ($auth->authenticate()) {
						$conn = new CF_Connection($auth);
						$conn->ssl_use_cabundle();
						$backwpupcontainer = $conn->get_container(backwpup_get_option($main,'rscContainer'));
						$contents = $backwpupcontainer->get_objects(0,NULL,NULL,backwpup_get_option($main,'rscdir'));
						foreach ($contents as $object) {
							$files[$filecounter]['JOBID']=$jobid;
							$files[$filecounter]['DEST']=$dest;
							$files[$filecounter]['folder']="RSC://".backwpup_get_option($main,'rscContainer')."/".dirname($object->name)."/";
							$files[$filecounter]['file']=$object->name;
							$files[$filecounter]['filename']=basename($object->name);
							$files[$filecounter]['downloadurl']=backwpup_admin_url('admin.php').'?page=backwpupbackups&action=downloadrsc&file='.$object->name.'&jobid='.$jobid;
							$files[$filecounter]['filesize']=$object->content_length;
							$files[$filecounter]['time']=strtotime($object->last_modified);
							$filecounter++;
						}
					}
				} catch (Exception $e) {
					$backwpup_message.='RSC: '.$e->getMessage().'<br />';
				}
			}
		}
		//Get files/fileinfo from FTP
		if ($dest=='FTP' and backwpup_get_option($main,'ftphost') and function_exists('ftp_connect') and !backwpup_get_option($main,'ftpuser') and backwpup_get_option($main,'ftppass')) {
			if (function_exists('ftp_ssl_connect') and backwpup_get_option($main,'ftpssl')) { //make SSL FTP connection
				$ftp_conn_id = ftp_ssl_connect(backwpup_get_option($main,'ftphost'),backwpup_get_option($main,'ftphostport'),backwpup_get_option($main,'ftptimeout'));
			} elseif (!backwpup_get_option($main,'ftpssl')) { //make normal FTP conection if SSL not work
				$ftp_conn_id = ftp_connect(backwpup_get_option($main,'ftphost'),backwpup_get_option($main,'ftphostport'),backwpup_get_option($main,'ftptimeout'));
			}
			$loginok=false;
			if ($ftp_conn_id) {
				//FTP Login
				if (@ftp_login($ftp_conn_id,backwpup_get_option($main,'ftpuser'), backwpup_decrypt(backwpup_get_option($main,'ftppass')))) {
					$loginok=true;
				} else { //if PHP ftp login don't work use raw login
					ftp_raw($ftp_conn_id,'USER '.backwpup_get_option($main,'ftpuser'));
					$return=ftp_raw($ftp_conn_id,'PASS '.backwpup_decrypt(backwpup_get_option($main,'ftppass')));
					if (substr(trim($return[0]),0,3)<=400)
						$loginok=true;
				}
			}
			if ($loginok) {
				ftp_pasv($ftp_conn_id,backwpup_get_option($main,'ftppasv'));
				if ($ftpfilelist=ftp_nlist($ftp_conn_id,backwpup_get_option($main,'ftpdir'))) {
					foreach($ftpfilelist as $ftpfiles) {
						if (substr(basename($ftpfiles),0,1)=='.')
							continue;
						$files[$filecounter]['JOBID']=$jobid;
						$files[$filecounter]['DEST']=$dest;
						$files[$filecounter]['folder']="ftp://".backwpup_get_option($main,'ftphost').':'.backwpup_get_option($main,'ftphostport').dirname($ftpfiles)."/";
						$files[$filecounter]['file']=$ftpfiles;
						$files[$filecounter]['filename']=basename($ftpfiles);
						$files[$filecounter]['downloadurl']="ftp://".rawurlencode(backwpup_get_option($main,'ftpuser')).":".rawurlencode(backwpup_decrypt(backwpup_get_option($main,'ftppass')))."@".backwpup_get_option($main,'ftphost').':'.backwpup_get_option($main,'ftphostport').rawurlencode($ftpfiles);
						$files[$filecounter]['filesize']=ftp_size($ftp_conn_id,$ftpfiles);
						$files[$filecounter]['time']=ftp_mdtm($ftp_conn_id,$ftpfiles);
						$filecounter++;
					}
				}
			} else {
				$backwpup_message.='FTP: '.__('Login failure!','backwpup').'<br />';
			}
		}
		return $files;
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