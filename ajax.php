<?php
define('SHORTINIT',true) ;   //load minimal WordPress
define('DOING_AJAX', true);
@chdir( dirname( __FILE__ ) );
if ( is_file( '../../../wp-load.php' ) ) {
	require_once('../../../wp-load.php');
} else {
	$abspath = filter_input( INPUT_POST, 'ABSPATH', FILTER_SANITIZE_URL );
	if ( ! empty($abspath) && is_dir( $abspath . '/' ) && file_exists( realpath( $abspath . '/wp-load.php' ) ) ) {
		require_once(realpath( $abspath . '/wp-load.php' ));
	} else {
		die('ABSPATH Check');
	}
}

//load only needed things for wp-settings.php

// Load the L10n library.
require_once( ABSPATH . WPINC . '/l10n.php' );
require( ABSPATH . WPINC . '/formatting.php' );
require( ABSPATH . WPINC . '/capabilities.php' );
require( ABSPATH . WPINC . '/user.php' );
require( ABSPATH . WPINC . '/meta.php' );
require( ABSPATH . WPINC . '/general-template.php' );
require( ABSPATH . WPINC . '/link-template.php' );
//require( ABSPATH . WPINC . '/post.php' );  //must if current_user_can()
// Define constants that rely on the API to obtain the default value.
// Define must-use plugin directory constants, which may be overridden in the sunrise.php drop-in.
wp_plugin_directory_constants( );
if ( is_multisite() )
	ms_cookie_constants(  );
// Define constants after multisite is loaded. Cookie-related constants may be overridden in ms_network_cookies().
wp_cookie_constants( );
// Define and enforce our SSL constants
wp_ssl_constants( );
// Create common globals.
require( ABSPATH . WPINC . '/vars.php' );
// Load pluggable functions.
require( ABSPATH . WPINC . '/pluggable.php' );
require( ABSPATH . WPINC . '/pluggable-deprecated.php' );

// Set internal encoding.
wp_set_internal_encoding();
wp_magic_quotes();
$wp = new WP();
// Set up current user.
$wp->init();
// Pull in locale data after loading text domain.
require_once( ABSPATH . WPINC . '/locale.php' );
$GLOBALS['wp_locale'] = new WP_Locale();


//end load only needed things for wp-settings.php

//load Plugin
include_once(dirname(__FILE__).'/backwpup.php');
//Load text domain
load_plugin_textdomain( 'backwpup', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
//register auto load
spl_autoload_register( array( 'BackWPup', 'autoloader' ) );
//load Plugin api
BackWPup_Api::get_object();

@header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
send_nosniff_header();
//echo size_format( @memory_get_peak_usage( true ), 2 );
//ajax actions
switch ( $_POST['action'] ) {
	case 'backwpup_show_info':
		BackWPup_Ajax_Fileinfo::get_object();
		break;
	case 'backwpup_working':
		BackWPup_Ajax_Working::working();
		break;
	case 'backwpup_cron_text':
		BackWPup_Ajax_Editjob::cron_text();
		break;
	case 'backwpup_aws_buckets':
		BackWPup_Ajax_Editjob::aws_buckets();
		break;
	case 'backwpup_gstorage_buckets':
		BackWPup_Ajax_Editjob::gstorage_buckets();
		break;
	case 'backwpup_rsc_container':
		BackWPup_Ajax_Editjob::rsc_container();
		break;
	case 'backwpup_msazure_container':
		BackWPup_Ajax_Editjob::msazure_container();
		break;
	case 'backwpup_sugarsync_root':
		BackWPup_Ajax_Editjob::sugarsync_root();
		break;
	case 'backwpup_db_tables':
		BackWPup_Ajax_Editjob::db_tables();
		break;
	case 'backwpup_db_databases':
		BackWPup_Ajax_Editjob::db_databases();
		break;
}
// Default status
die( '0' );