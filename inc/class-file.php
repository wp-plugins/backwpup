<?php
if ( ! defined( 'ABSPATH' ) ) {
	header( $_SERVER["SERVER_PROTOCOL"] . " 404 Not Found" );
	header( "Status: 404 Not Found" );
	die();
}

/**
 * Class for methods for file/folder related things
 */
class BackWPup_File {

	/**
	 *
	 * Get folder to exclude from a given folder for file backups
	 *
	 * @param $folder to check for excludes
	 *
	 * @return array of folder to exclude
	 */
	public static function get_exclude_wp_dirs( $folder ) {
		global $wpdb;
		$folder       = trailingslashit( str_replace( '\\', '/', $folder ) );
		$excludedir   = array();
		$excludedir[] = backwpup_get_option( 'cfg', 'tempfolder' ); //exclude temp
		$excludedir[] = backwpup_get_option( 'cfg', 'logfolder' ); //exclude log folder
		if ( false !== strpos( trailingslashit( str_replace( '\\', '/', ABSPATH ) ), $folder ) && trailingslashit( str_replace( '\\', '/', ABSPATH ) ) != $folder )
			$excludedir[] = trailingslashit( str_replace( '\\', '/', ABSPATH ) );
		if ( false !== strpos( trailingslashit( str_replace( '\\', '/', WP_CONTENT_DIR ) ), $folder ) && trailingslashit( str_replace( '\\', '/', WP_CONTENT_DIR ) ) != $folder )
			$excludedir[] = trailingslashit( str_replace( '\\', '/', WP_CONTENT_DIR ) );
		if ( false !== strpos( trailingslashit( str_replace( '\\', '/', WP_PLUGIN_DIR ) ), $folder ) && trailingslashit( str_replace( '\\', '/', WP_PLUGIN_DIR ) ) != $folder )
			$excludedir[] = trailingslashit( str_replace( '\\', '/', WP_PLUGIN_DIR ) );
		if ( false !== strpos( str_replace( '\\', '/', trailingslashit( WP_CONTENT_DIR ) . 'themes/' ), $folder ) && str_replace( '\\', '/', trailingslashit( WP_CONTENT_DIR ) . 'themes/' ) != $folder )
			$excludedir[] = str_replace( '\\', '/', trailingslashit( WP_CONTENT_DIR ) . 'themes/' );
		if ( false !== strpos( BackWPup_File::get_upload_dir(), $folder ) && BackWPup_File::get_upload_dir() != $folder )
			$excludedir[] = BackWPup_File::get_upload_dir();
		//Exclude Backup dirs
		$value = wp_cache_get( 'exclude', 'backwpup' );
		if ( false == $value ) {
			$value = $wpdb->get_col( "SELECT value FROM `" . $wpdb->prefix . "backwpup` WHERE main LIKE 'job_%' AND name='backupdir' and value<>'' and value<>'/' " );
			wp_cache_set( 'exclude', $value, 'backwpup' );
		}
		if ( ! empty($value) ) {
			foreach ( $value as $backupdir )
			{
				$excludedir[] = $backupdir;
			}
		}
		return $excludedir;
	}

	/**
	 *
	 * Get the folder for blog uploads
	 *
	 * @return sting
	 */
	public static function get_upload_dir() {
		$upload_path = get_option( 'upload_path' );
		$upload_path = trim( $upload_path );
		if ( empty($upload_path) ) {
			$dir = WP_CONTENT_DIR . '/uploads';
		} else {
			$dir = $upload_path;
			if ( 'wp-content/uploads' == $upload_path )
				$dir = WP_CONTENT_DIR . '/uploads';
			elseif ( 0 !== strpos( $dir, ABSPATH ) )
				$dir = path_join( ABSPATH, $dir );
		}
		if ( defined( 'UPLOADS' ) && ! is_multisite() )
			$dir = ABSPATH . UPLOADS;
		if ( is_multisite() )
			$dir = untrailingslashit( WP_CONTENT_DIR ) . '/blogs.dir';
		return str_replace( '\\', '/', trailingslashit( $dir ) );
	}

	/**
	 *
	 * check if path in open basedir
	 *
	 * @param string $dir the folder to check
	 *
	 * @return bool is it in open basedir
	 */
	public static function check_open_basedir( $dir ) {
		if ( ! ini_get( 'open_basedir' ) )
			return true;
		$openbasedirarray = explode( PATH_SEPARATOR, ini_get( 'open_basedir' ) );
		$dir              = rtrim( str_replace( '\\', '/', $dir ), '/' ) . '/';
		if ( ! empty($openbasedirarray) ) {
			foreach ( $openbasedirarray as $basedir ) {
				if ( stripos( $dir, rtrim( str_replace( '\\', '/', $basedir ), '/' ) . '/' ) == 0 )
					return true;
			}
		}
		return false;
	}
}