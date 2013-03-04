<?php
//if uninstall not called from WordPress exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) )
	die();

global $wpdb;

//only uninstall if no BackWPup Version active
if ( ! class_exists( 'BackWPup' ) ) {
	//delete log folder and logs
	$log_folder = get_site_option( 'backwpup_cfg_logfolder' );
	$log_flies = scandir( $log_folder );
	foreach ( $log_flies as $log_flie ) {
		if ( is_file( $log_folder . $log_flie ) && ( substr( $log_flie, -8 ) == '.html.gz' || substr( $log_flie, -5 ) == '.html' ) )
			unlink( $log_folder . $log_flie );
	}
	rmdir( $log_folder );
	//delete plugin options
	if ( is_multisite() )
		$wpdb->query( "DELETE FROM " . $wpdb->sitemeta . " WHERE meta_key LIKE '%backwpup_%' " );
	else
		$wpdb->query( "DELETE FROM " . $wpdb->options . " WHERE option_name LIKE '%backwpup_%' " );
}