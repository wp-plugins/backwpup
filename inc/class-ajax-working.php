<?php
if ( ! defined( 'ABSPATH' ) ) {
	header( $_SERVER["SERVER_PROTOCOL"] . " 404 Not Found" );
	header( "Status: 404 Not Found" );
	die();
}

/**
 * Ajax generate job with job working data for show process bars and log
 */
class BackWPup_Ajax_Working {

	/**
	 *
	 * Function to generate json data
	 *
	 */
	public static function working() {
		check_ajax_referer( 'backwpupworking_ajax_nonce' );

		$logfile = filter_input( INPUT_POST, 'logfile', FILTER_SANITIZE_URL );
		$logpos  = filter_input( INPUT_POST, 'logpos', FILTER_SANITIZE_NUMBER_INT );


		if ( file_exists( $logfile . '.gz' ) )
			$logfile .= '.gz';

		// check given file is a backwpup logfile
		if ( substr( basename( $logfile ), - 3 ) != '.gz' && substr( basename( $logfile ), - 8 ) != '.html.gz' && substr( basename( $logfile ), 0, 13 ) != 'backwpup_log_' )
			die();

		$log = '';
		if ( file_exists( $logfile ) ) {
			$backupdata = backwpup_get_workingdata();
			if ( ! empty($backupdata) ) {
				$warnings     = $backupdata['WARNING'];
				$errors       = $backupdata['ERROR'];
				$stepspersent = $backupdata['STEPSPERSENT'];
				$steppersent  = $backupdata['STEPPERSENT'];
			} else {
				$logheader    = backwpup_read_logheader( $logfile );
				$warnings     = $logheader['warnings'];
				$errors       = $logheader['errors'];
				$stepspersent = 100;
				$steppersent  = 100;
				$log .= '<span id="stopworking"></span>';
			}

			if ( strtolower( substr( $logfile, - 3 ) ) == '.gz' )
				$logfiledata = gzfile( $logfile );
			else
				$logfiledata = file( $logfile );

			for ( $i = $logpos; $i < count( $logfiledata ); $i ++ ) {
				if ( trim( $logfiledata[$i] ) != '</body>' && trim( $logfiledata[$i] ) != '</html>' )
					$log .= $logfiledata[$i];
			}
			echo json_encode( array( 'logpos'	  => count( $logfiledata ),
									 'LOG'		 => $log,
									 'WARNING'	 => $warnings,
									 'ERROR'	   => $errors,
									 'STEPSPERSENT'=> $stepspersent,
									 'STEPPERSENT' => $steppersent ) );
		}
		die();
	}
}