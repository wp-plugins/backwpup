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

		if ( is_file( $logfile . '.gz' ) )
			$logfile .= '.gz';

		// check given file is a backwpup logfile
		if ( (substr( basename( $logfile ), - 5) != '.html' || substr( basename( $logfile ), - 8 ) != '.html.gz') && substr( basename( $logfile ), 0, 13 ) != 'backwpup_log_' )
			die();

		if ( is_file( $logfile ) ) {
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
			}

			if ( strtolower( substr( $logfile, - 3 ) ) == '.gz' ) {
				$gzlogfiledata = gzfile( $logfile );
				$logfiledata = substr(implode('',$gzlogfiledata),$logpos);
			} else {
				$logfiledata = file_get_contents( $logfile,false,NULL,$logpos );
			}
			preg_match('/<body[^>]*>/si',$logfiledata,$match);
			if (!empty($match[0]))
				$startpos=strpos($logfiledata,$match[0])+strlen($match[0]);
			else
				$startpos=0;

			$endpos=stripos($logfiledata,'</body>');
			$stop= '';
			if ($endpos !== false) {
				$stop  = '<span id="stopworking"></span>';
				$stepspersent = 100;
				$steppersent  = 100;
			}
			if (false === $endpos)
				$endpos=strlen( $logfiledata );
			$length=strlen( $logfiledata )-(strlen( $logfiledata )-$endpos)-$startpos;

			echo json_encode( array( 'logpos'	  => strlen( $logfiledata )+$logpos,
									 'LOG'		 => substr($logfiledata,$startpos,$length).$stop,
									 'WARNING'	 => $warnings,
									 'ERROR'	   => $errors,
									 'STEPSPERSENT'=> $stepspersent,
									 'STEPPERSENT' => $steppersent ) );
		}
		die();
	}
}