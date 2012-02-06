<?php
if ( empty($_GET['starttype']) && ! defined( 'STDIN' ) ) {
	header( $_SERVER["SERVER_PROTOCOL"] . " 404 Not Found" );
	header( "Status: 404 Not Found" );
	die();
}
ignore_user_abort( true );
define('DONOTCACHEPAGE', true);
define('DONOTCACHEDB', true);
define('DONOTMINIFY', true);
define('DONOTCDN', true);
define('DONOTCACHCEOBJECT', true);
define('W3TC_IN_MINIFY', false); //W3TC will not loaded
define('DOING_CRON', true);
//define E_DEPRECATED if PHP lower than 5.3
if ( ! defined( 'E_DEPRECATED' ) )
	define('E_DEPRECATED', 8192);
if ( ! defined( 'E_USER_DEPRECATED' ) )
	define('E_USER_DEPRECATED', 16384);
//phrase commandline args
if ( defined( 'STDIN' ) ) {
	$starttype = 'runcmd';
	$abspath   = '';
	$jobid     = 0;
	foreach ( $_SERVER['argv'] as $arg ) {
		if ( strtolower( substr( $arg, 0, 7 ) ) == '-jobid=' )
			$jobid = (int) substr( $arg, 7 );
		if ( strtolower( substr( $arg, 0, 9 ) ) == '-abspath=' )
			$abspath = substr( $arg, 9 );
	}
	@chdir( dirname( __FILE__ ) );
	if ( is_file( '../../../wp-load.php' ) ) {
		require_once('../../../wp-load.php');
	} else {
		$abspath = rtrim( $abspath, '/' );
		if ( is_dir( $abspath ) && file_exists( $abspath . '/wp-load.php' ) )
			require_once($abspath . '/wp-load.php');
		else
			die('ABSPATH check');
	}
	if ( (empty($jobid) || ! is_numeric( $jobid )) )
		die(__( 'JOBID check', 'backwpup' ));
	@set_time_limit( 0 );
} else { //normal start from webservice
	//check get vars
	$jobid     = filter_input( INPUT_GET, 'jobid', FILTER_SANITIZE_NUMBER_INT );
	$starttype = filter_input( INPUT_GET, 'starttype', FILTER_SANITIZE_STRING );
	$nonce     = filter_input( INPUT_GET, '_nonce', FILTER_SANITIZE_STRING );
	@chdir( dirname( __FILE__ ) );
	if ( is_file( '../../../wp-load.php' ) ) {
		require_once('../../../wp-load.php');
	} else {
		$abspath = filter_input( INPUT_GET, 'ABSPATH', FILTER_SANITIZE_URL );
		$abspath = rtrim( preg_replace( '/[^a-zA-Z0-9. :_\/-]/', '', trim( urldecode( $abspath ) ) ), '/' );
		if ( substr( $abspath, 1, 1 ) == ':' )
			$abspath = realpath( str_replace( array( '..' ), '', $abspath ) );
		else
			$abspath = realpath( '/' . ltrim( str_replace( array( ':', '..' ), '', $abspath ), '/' ) );
		if ( ! empty($abspath) && is_dir( $abspath . '/' ) && file_exists( realpath( $abspath . '/wp-load.php' ) ) ) {
			require_once(realpath( $abspath . '/wp-load.php' ));
		} else {
			header( $_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request", 400 );
			die('ABSPATH check');
		}
	}
	if ( isset($nonce) )
		$nonce = preg_replace( '/[^a-zA-Z0-9]/', '', trim( $nonce ) );
	if ( empty($nonce) || ! is_string( $nonce ) )
		wp_die( __( 'Nonce pre check', 'backwpup' ), __( 'Nonce pre check', 'backwpup' ), array( 'response' => 403 ) );
	if ( empty($starttype) || ! in_array( $starttype, array( 'restart', 'runnow', 'runnowalt', 'runext', 'apirun' ) ) )
		wp_die( __( 'Starttype check', 'backwpup' ), __( 'Starttype check', 'backwpup' ), array( 'response' => 400 ) );
	if ( (empty($jobid) || ! is_numeric( $jobid )) && in_array( $starttype, array( 'runnow', 'runnowalt', 'runext', 'apirun' ) ) )
		wp_die( __( 'JOBID check', 'backwpup' ), __( 'JOBID check', 'backwpup' ), array( 'response' => 400 ) );

	if ( in_array( $starttype, array( 'runnow', 'runnowalt' ) ) && (! backwpup_get_option( 'temp', $starttype . '_nonce_' . $jobid ) || $nonce != backwpup_get_option( 'temp', $starttype . '_nonce_' . $jobid )) )
		wp_die( __( 'Nonce check', 'backwpup' ), __( 'Nonce check', 'backwpup' ), array( 'response' => 403 ) );
	elseif ( $starttype == 'restart' && (! backwpup_get_option( 'temp', $starttype . '_nonce' ) || $nonce != backwpup_get_option( 'temp', $starttype . '_nonce' )) )
		wp_die( __( 'Nonce check', 'backwpup' ), __( 'Nonce check', 'backwpup' ), array( 'response' => 403 ) );
	elseif ( $starttype == 'apirun' && (! backwpup_get_option( 'cfg', 'apicronservicekey' ) || $nonce != backwpup_get_option( 'cfg', 'apicronservicekey' )) )
		wp_die( __( 'Nonce check', 'backwpup' ), __( 'Nonce check', 'backwpup' ), array( 'response' => 403 ) );
	elseif ( $starttype == 'runext' && (! backwpup_get_option( 'cfg', 'jobrunauthkey' ) || $nonce != backwpup_get_option( 'cfg', 'jobrunauthkey' )) )
		wp_die( __( 'Nonce check', 'backwpup' ), __( 'Nonce check', 'backwpup' ), array( 'response' => 403 ) );
	//delete nonce
	backwpup_delete_option( 'temp', $starttype . '_nonce_' . $jobid );
	backwpup_delete_option( 'temp', $starttype . '_nonce' );
	//set max execution time
	@set_time_limit( backwpup_get_option( 'cfg', 'jobrunmaxexectime' ) );
}
//check job id exists
if ( in_array( $starttype, array( 'runnow', 'runnowalt', 'runext', 'apirun', 'runcmd' ) ) ) {
	if ( $jobid != backwpup_get_option( 'job_' . $jobid, 'jobid' ) )
		wp_die( __( 'Wrong JOBID check', 'backwpup' ), __( 'Wrong JOBID check', 'backwpup' ), array( 'response' => 400 ) );
}
//check api run is in time windows
if ( $starttype == 'apirun' ) {
	$nextruntime = backwpup_get_option( 'job_' . $jobid, 'cronnextrun' );
	$timenow     = current_time( 'timestamp' );
	if ( ($nextruntime + 1800) < $timenow || ($nextruntime - 1800) > $timenow )
		wp_die( __( 'API run on false time', 'backwpup' ), __( 'API run on false time', 'backwpup' ), array( 'response' => 400 ) );
}
//check folders
if ( ! backwpup_get_option( 'cfg', 'logfolder' ) || ! is_dir( backwpup_get_option( 'cfg', 'logfolder' ) ) || ! is_writable( backwpup_get_option( 'cfg', 'logfolder' ) ) )
	wp_die( __( 'Log folder not exists or is not writable', 'backwpup' ), __( 'Log folder not exists or is not writable', 'backwpup' ), array( 'response' => 500 ) );
if ( ! backwpup_get_option( 'cfg', 'tempfolder' ) || ! is_dir( backwpup_get_option( 'cfg', 'tempfolder' ) ) || ! is_writable( backwpup_get_option( 'cfg', 'tempfolder' ) ) )
	wp_die( __( 'Temp folder not exists or is not writable', 'backwpup' ), __( 'Temp folder not exists or is not writable', 'backwpup' ), array( 'response' => 500 ) );
//check running job
if ( in_array( $starttype, array( 'runnow', 'runnowalt', 'runext', 'runcmd', 'apirun' ) ) && backwpup_get_workingdata( false ) )
	wp_die( __( 'A job already running', 'backwpup' ), __( 'A job already running', 'backwpup' ), array( 'response' => 503 ) );
if ( in_array( $starttype, array( 'restart' ) ) && ! backwpup_get_workingdata( false ) )
	wp_die( __( 'No job running', 'backwpup' ), __( 'No job running', 'backwpup' ), array( 'response' => 400 ) );
//disconnect or redirect
if ( in_array( $starttype, array( 'restart', 'runnowalt', 'runext', 'apirun' ) ) ) {
	nocache_headers();
	@ob_end_clean();
	header( "Connection: close" );
	@ob_start();
	header( "Content-Length: 0" );
	@ob_end_flush();
	@flush();
}
elseif ( $starttype == 'runnow' ) {
	nocache_headers();
	@ob_start();
	wp_redirect( add_query_arg( array( 'page' => 'backwpupworking',
									   'jobid'=> $jobid ), backwpup_admin_url( 'admin.php' ) ) );
	echo ' ';
	while ( @ob_end_flush() ) {
		;
	}
	@flush();
}
//start class
new BackWPup_Job($starttype, $jobid);