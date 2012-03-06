<?php
if ( ! defined( 'ABSPATH' ) ) {
	header( $_SERVER["SERVER_PROTOCOL"] . " 404 Not Found" );
	header( "Status: 404 Not Found" );
	die();
}
class BackWPup_Page_Backups {
	public static function load() {
		global $backwpup_listtable, $backwpup_message;
		//Create Table
		$backwpup_listtable = new BackWPup_Page_Backups_Table;

		switch ( $backwpup_listtable->current_action() ) {
			case 'delete': //Delete Backup archives
				check_admin_referer( 'bulk-backups' );
				list($jobid, $dest) = explode( '_', $_GET['jobdest'] );
				$main  = 'job_' . $jobid;
				$files = backwpup_get_option( 'temp', $_GET['jobdest'], false );
				foreach ( $_GET['backupfiles'] as $backupfile ) {
					if ( $dest == 'FOLDER' ) {
						if ( is_file( $backupfile ) ) {
							if ( unlink( $backupfile ) ) {
								//update file list
								foreach ( $files as $key => $file ) {
									if ( is_array( $file ) && $file['file'] == $backupfile )
										unset($files[$key]);
								}
							}
						}
					}
					elseif ( $dest == 'S3' ) {
						if ( backwpup_get_option( $main, 'awsAccessKey' ) && backwpup_get_option( $main, 'awsSecretKey' ) && backwpup_get_option( $main, 'awsBucket' ) ) {
							try {
								$s3 = new AmazonS3(array( 'key'				  => backwpup_get_option( $main, 'awsAccessKey' ),
														  'secret'			   => backwpup_get_option( $main, 'awsSecretKey' ),
														  'certificate_authority'=> true ));
								if ( backwpup_get_option( $main, 'awsdisablessl' ) )
									$s3->disable_ssl( true );
								$s3->delete_object( backwpup_get_option( $main, 'awsBucket' ), $backupfile );
								//update file list
								foreach ( $files as $key => $file ) {
									if ( is_array( $file ) && $file['file'] == $backupfile )
										unset($files[$key]);
								}
								unset($s3);
							} catch ( Exception $e ) {
								$backwpup_message .= 'Amazon S3: ' . $e->getMessage() . '<br />';
							}
						}
					}
					elseif ( $dest == 'GSTORAGE' ) {
						if ( backwpup_get_option( $main, 'GStorageAccessKey' ) && backwpup_get_option( $main, 'GStorageSecret' ) && backwpup_get_option( $main, 'GStorageBucket' ) ) {
							try {
								$gstorage = new AmazonS3(array( 'key'				  => backwpup_get_option( $main, 'GStorageAccessKey' ),
																'secret'			   => backwpup_get_option( $main, 'GStorageSecret' ),
																'certificate_authority'=> true ));
								$gstorage->set_hostname( 'commondatastorage.googleapis.com' );
								$gstorage->allow_hostname_override( false );
								$gstorage->delete_object( backwpup_get_option( $main, 'GStorageBucket' ), $backupfile );
								//update file list
								foreach ( $files as $key => $file ) {
									if ( is_array( $file ) && $file['file'] == $backupfile )
										unset($files[$key]);
								}
								unset($gstorage);
							} catch ( Exception $e ) {
								$backwpup_message .= sprintf( __( 'GStorage API: %s', 'backwpup' ), $e->getMessage() ) . '<br />';
							}
						}
					}
					elseif ( $dest == 'MSAZURE' ) {
						if ( backwpup_get_option( $main, 'msazureHost' ) && backwpup_get_option( $main, 'msazureAccName' ) && backwpup_get_option( $main, 'msazureKey' ) && backwpup_get_option( $main, 'msazureContainer' ) ) {
							try {
								$storageClient = new Microsoft_WindowsAzure_Storage_Blob(backwpup_get_option( $main, 'msazureHost' ), backwpup_get_option( $main, 'msazureAccName' ), backwpup_get_option( $main, 'msazureKey' ));
								$storageClient->deleteBlob( backwpup_get_option( $main, 'msazureContainer' ), $backupfile );
								//update file list
								foreach ( $files as $key => $file ) {
									if ( is_array( $file ) && $file['file'] == $backupfile )
										unset($files[$key]);
								}
								unset($storageClient);
							} catch ( Exception $e ) {
								$backwpup_message .= 'MS AZURE: ' . $e->getMessage() . '<br />';
							}
						}
					}
					elseif ( $dest == 'DROPBOX' ) {
						if ( backwpup_get_option( $main, 'dropetoken' ) && backwpup_get_option( $main, 'dropesecret' ) ) {
							try {
								$dropbox = new BackWPup_Dest_Dropbox(backwpup_get_option( $main, 'droperoot' ));
								$dropbox->setOAuthTokens( backwpup_get_option( $main, 'dropetoken' ), backwpup_decrypt(backwpup_get_option( $main, 'dropesecret' )) );
								$dropbox->fileopsDelete( $backupfile );
								//update file list
								foreach ( $files as $key => $file ) {
									if ( is_array( $file ) && $file['file'] == $backupfile )
										unset($files[$key]);
								}
								unset($dropbox);
							} catch ( Exception $e ) {
								$backwpup_message .= 'DROPBOX: ' . $e->getMessage() . '<br />';
							}
						}
					}
					elseif ( $dest == 'SUGARSYNC' ) {
						if ( backwpup_get_option( $main, 'sugaruser' ) && backwpup_get_option( $main, 'sugarpass' ) ) {
							try {
								$sugarsync = new BackWPup_Dest_SugarSync(backwpup_get_option( $main, 'sugaruser' ), backwpup_decrypt( backwpup_get_option( $main, 'sugarpass' ) ));
								$sugarsync->delete( urldecode( $backupfile ) );
								//update file list
								foreach ( $files as $key => $file ) {
									if ( is_array( $file ) && $file['file'] == $backupfile )
										unset($files[$key]);
								}
								unset($sugarsync);
							} catch ( Exception $e ) {
								$backwpup_message .= 'SUGARSYNC: ' . $e->getMessage() . '<br />';
							}
						}
					}
					elseif ( $dest == 'RSC' ) {
						if ( backwpup_get_option( $main, 'rscUsername' ) && backwpup_get_option( $main, 'rscAPIKey' ) && backwpup_get_option( $main, 'rscContainer' ) ) {
							try {
								$auth = new CF_Authentication(backwpup_get_option( $main, 'rscUsername' ), backwpup_get_option( $main, 'rscAPIKey' ));
								if ( $auth->authenticate() ) {
									$conn              = new CF_Connection($auth);
									$backwpupcontainer = $conn->get_container( backwpup_get_option( $main, 'rscContainer' ) );
									$backwpupcontainer->delete_object( $backupfile );
									//update file list
									foreach ( $files as $key => $file ) {
										if ( is_array( $file ) && $file['file'] == $backupfile )
											unset($files[$key]);
									}
								}
							} catch ( Exception $e ) {
								$backwpup_message .= 'RSC: ' . $e->getMessage() . '<br />';
							}
						}
					}
					elseif ( $dest == 'FTP' ) {
						if ( backwpup_get_option( $main, 'ftphost' ) && backwpup_get_option( $main, 'ftpuser' ) && backwpup_get_option( $main, 'ftppass' ) && function_exists( 'ftp_connect' ) ) {
							$ftp_conn_id = false;
							if ( function_exists( 'ftp_ssl_connect' ) && backwpup_get_option( $main, 'ftpssl' ) ) { //make SSL FTP connection
								$ftp_conn_id = ftp_ssl_connect( backwpup_get_option( $main, 'ftphost' ), backwpup_get_option( $main, 'ftphostport' ), backwpup_get_option( $main, 'ftptimeout' ) );
							} elseif ( ! backwpup_get_option( $main, 'ftpssl' ) ) { //make normal FTP conection if SSL not work
								$ftp_conn_id = ftp_connect( backwpup_get_option( $main, 'ftphost' ), backwpup_get_option( $main, 'ftphostport' ), backwpup_get_option( $main, 'ftptimeout' ) );
							}
							$loginok = false;
							if ( $ftp_conn_id ) {
								//FTP Login
								if ( @ftp_login( $ftp_conn_id, backwpup_get_option( $main, 'ftpuser' ), backwpup_decrypt( backwpup_get_option( $main, 'ftppass' ) ) ) ) {
									$loginok = true;
								} else { //if PHP ftp login don't work use raw login
									ftp_raw( $ftp_conn_id, 'USER ' . backwpup_get_option( $main, 'ftpuser' ) );
									$return = ftp_raw( $ftp_conn_id, 'PASS ' . backwpup_decrypt( backwpup_get_option( $main, 'ftppass' ) ) );
									if ( substr( trim( $return[0] ), 0, 3 ) <= 400 )
										$loginok = true;
								}
							}
							if ( $loginok ) {
								ftp_pasv( $ftp_conn_id, backwpup_get_option( $main, 'ftppasv' ) );
								ftp_delete( $ftp_conn_id, $backupfile );
								//update file list
								foreach ( $files as $key => $file ) {
									if ( is_array( $file ) && $file['file'] == $backupfile )
										unset($files[$key]);
								}
							} else {
								$backwpup_message .= 'FTP: ' . __( 'Login failure!', 'backwpup' ) . '<br />';
							}
						}
					}
				}
				backwpup_update_option( 'temp', $_GET['jobdest'], $files );
				break;
			case 'download': //Download Backup
				check_admin_referer( 'download-backup' );
				if ( is_file( $_GET['file'] ) ) {
					header( "Pragma: public" );
					header( "Expires: 0" );
					header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
					header( "Content-Type: application/force-download" );
					header( "Content-Type: application/octet-stream" );
					header( "Content-Type: application/download" );
					header( "Content-Disposition: attachment; filename=" . basename( $_GET['file'] ) . ";" );
					header( "Content-Transfer-Encoding: binary" );
					header( "Content-Length: " . filesize( $_GET['file'] ) );
					@readfile( $_GET['file'] );
					die();
				} else {
					header( $_SERVER["SERVER_PROTOCOL"] . " 404 Not Found" );
					header( "Status: 404 Not Found" );
					die();
				}
				break;
			case 'downloads3': //Download S3 Backup
				check_admin_referer( 'download-backup' );
				$main = 'job_' . (int) $_GET['jobid'];
				try {
					$s3 = new AmazonS3(array( 'key'				  => backwpup_get_option( $main, 'awsAccessKey' ),
											  'secret'			   => backwpup_get_option( $main, 'awsSecretKey' ),
											  'certificate_authority'=> true ));
					if ( backwpup_get_option( $main, 'awsdisablessl' ) )
						$s3->disable_ssl( true );
					$s3file = $s3->get_object( backwpup_get_option( $main, 'awsBucket' ), $_GET['file'] );
				} catch ( Exception $e ) {
					die($e->getMessage());
				}
				if ( $s3file->status == 200 ) {
					header( "Pragma: public" );
					header( "Expires: 0" );
					header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
					header( "Content-Type: " . $s3file->header->_info->content_type );
					header( "Content-Type: application/force-download" );
					header( "Content-Type: application/octet-stream" );
					header( "Content-Type: application/download" );
					header( "Content-Disposition: attachment; filename=" . basename( $_GET['file'] ) . ";" );
					header( "Content-Transfer-Encoding: binary" );
					header( "Content-Length: " . $s3file->header->_info->size_download );
					echo $s3file->body;
					die();
				} else {
					header( 'HTTP/1.0 ' . $s3file->status . ' Not Found' );
					die();
				}
				break;
			case 'downloaddropbox': //Download Dropbox Backup
				check_admin_referer( 'download-backup' );
				$main = 'job_' . (int) $_GET['jobid'];
				try {
					$dropbox = new BackWPup_Dest_Dropbox(backwpup_get_option( $main, 'droperoot' ));
					$dropbox->setOAuthTokens( backwpup_get_option( $main, 'dropetoken' ), backwpup_decrypt(backwpup_get_option( $main, 'dropesecret' )) );
					$media = $dropbox->media( $_GET['file'] );
					if ( ! empty($media['url']) )
						header( "Location: " . $media['url'] );
					die();
				} catch ( Exception $e ) {
					die($e->getMessage());
				}
				break;
			case 'downloadsugarsync': //Download SugarSync Backup
				check_admin_referer( 'download-backup' );
				$main = 'job_' . (int) $_GET['jobid'];
				try {
					$sugarsync = new BackWPup_Dest_SugarSync(backwpup_get_option( $main, 'sugaruser' ), backwpup_decrypt( backwpup_get_option( $main, 'sugarpass' ) ));
					$response  = $sugarsync->get( urldecode( $_GET['file'] ) );
					header( "Pragma: public" );
					header( "Expires: 0" );
					header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
					header( "Content-Type: " . (string) $response->mediaType );
					header( "Content-Type: application/force-download" );
					header( "Content-Type: application/octet-stream" );
					header( "Content-Type: application/download" );
					header( "Content-Disposition: attachment; filename=" . (string) $response->displayName . ";" );
					header( "Content-Transfer-Encoding: binary" );
					header( "Content-Length: " . (int) $response->size );
					echo $sugarsync->download( urldecode( $_GET['file'] ) );
					die();
				} catch ( Exception $e ) {
					die($e->getMessage());
				}
				break;
			case 'downloadmsazure': //Download Microsoft Azure Backup
				check_admin_referer( 'download-backup' );
				$main = 'job_' . (int) $_GET['jobid'];
				try {
					$storageClient = new Microsoft_WindowsAzure_Storage_Blob(backwpup_get_option( $main, 'msazureHost' ), backwpup_get_option( $main, 'msazureAccName' ), backwpup_get_option( $main, 'msazureKey' ));
					header( "Pragma: public" );
					header( "Expires: 0" );
					header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
					//header("Content-Type: ".$s3file->header->_info->content_type);
					header( "Content-Type: application/force-download" );
					header( "Content-Type: application/octet-stream" );
					header( "Content-Type: application/download" );
					header( "Content-Disposition: attachment; filename=" . basename( $_GET['file'] ) . ";" );
					header( "Content-Transfer-Encoding: binary" );
					//header("Content-Length: ".$s3file->header->_info->size_download);
					echo $storageClient->getBlobData( backwpup_get_option( $main, 'msazureContainer' ), $_GET['file'] );
					die();
				} catch ( Exception $e ) {
					die($e->getMessage());
				}
				break;
			case 'downloadrsc': //Download RSC Backup
				check_admin_referer( 'download-backup' );
				$main = 'job_' . (int) $_GET['jobid'];
				try {
					$auth = new CF_Authentication(backwpup_get_option( $main, 'rscUsername' ), backwpup_get_option( $main, 'rscAPIKey' ));
					$auth->ssl_use_cabundle( realpath( dirname( __FILE__ ) . '/../cert/cacert.pem' ) );
					if ( $auth->authenticate() ) {
						$conn = new CF_Connection($auth);
						$conn->ssl_use_cabundle( realpath( dirname( __FILE__ ) . '/../cert/cacert.pem' ) );
						$backwpupcontainer = $conn->get_container( backwpup_get_option( $main, 'rscContainer' ) );
						$backupfile        = $backwpupcontainer->get_object( $_GET['file'] );
						header( "Pragma: public" );
						header( "Expires: 0" );
						header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
						header( "Content-Type: " . $backupfile->content_type );
						header( "Content-Type: application/force-download" );
						header( "Content-Type: application/octet-stream" );
						header( "Content-Type: application/download" );
						header( "Content-Disposition: attachment; filename=" . basename( $_GET['file'] ) . ";" );
						header( "Content-Transfer-Encoding: binary" );
						header( "Content-Length: " . $backupfile->content_length );
						$output = fopen( "php://output", "w" );
						$backupfile->stream( $output );
						fclose( $output );
						die();
					} else {
						header( $_SERVER["SERVER_PROTOCOL"] . " 404 Not Found" );
						header( "Status: 404 Not Found" );
						die();
					}
				} catch ( Exception $e ) {
					die($e->getMessage());
				}
				break;
		}

		//Save per page
		if ( isset($_POST['screen-options-apply']) && isset($_POST['wp_screen_options']['option']) && isset($_POST['wp_screen_options']['value']) && $_POST['wp_screen_options']['option'] == 'backwpupbackups_per_page' ) {
			check_admin_referer( 'screen-options-nonce', 'screenoptionnonce' );
			global $current_user;
			if ( $_POST['wp_screen_options']['value'] > 0 && $_POST['wp_screen_options']['value'] < 1000 ) {
				update_user_option( $current_user->ID, 'backwpupbackups_per_page', (int) $_POST['wp_screen_options']['value'] );
				wp_redirect( remove_query_arg( array( 'pagenum', 'apage', 'paged' ), wp_get_referer() ) );
				exit;
			}
		}

		//add Help
		BackWPup_Help::help();
		BackWPup_Help::add_tab( array(
			'id'		 => 'overview',
			'title'	  => __( 'Overview' ),
			'content'	=>
			'<p>' . __( 'Here you see a list of backup files. Change the destination to jobname:destination to become a list of backups from other destinations and jobs. Then you can delete or download backup files. <br />NOTE: The lists will be only generated on backup jobs to reduce traffic.', 'backwpup' ) . '</p>'
		) );

		add_screen_option( 'per_page', array( 'label'   => __( 'Logs', 'backwpup' ),
											  'default' => 20,
											  'option'  => 'backwpupbackups_per_page' ) );

		$backwpup_listtable->prepare_items();
	}

	/**
	 *
	 * Output javascript
	 *
	 * @return nothing
	 */
	public static function javascript() {
		return;
	}

	/**
	 *
	 * Output css
	 *
	 * @return nothing
	 */
	public static function css() {
		wp_enqueue_style( 'backwpup_backups', plugins_url( '', dirname( __FILE__ ) ) . '/css/backups.css', '', ((defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG) ? time() : BackWPup::get_plugin_data('Version')), 'screen' );
	}

	public static function page() {
		global $backwpup_message, $backwpup_listtable;
		echo "<div class=\"wrap\">";
		screen_icon();
		echo "<h2>" . esc_html( __( 'BackWPup Manage Backups', 'backwpup' ) ) . "</h2>";
		if ( isset($backwpup_message) && ! empty($backwpup_message) )
			echo "<div id=\"message\" class=\"updated\"><p>" . $backwpup_message . "</p></div>";
		echo "<form id=\"posts-filter\" action=\"\" method=\"get\">";
		echo "<input type=\"hidden\" name=\"page\" value=\"backwpupbackups\" />";
		$backwpup_listtable->display();
		echo "<div id=\"ajax-response\"></div>";
		echo "</form>";
		echo "</div>";
	}
}
