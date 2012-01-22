<?php
if (!defined('ABSPATH')) {
	header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
	header("Status: 404 Not Found");
	die();
}

function backwpup_plugin_deactivate() {
	global $wpdb,$wp_roles;
	wp_clear_scheduled_hook('backwpup_cron');
	$wpdb->query("UPDATE ".$wpdb->prefix."backwpup SET value='' WHERE main='backwpup' and name='md5' LIMIT 1");
	$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main='temp'");
	$wpdb->query("DELETE FROM ".$wpdb->prefix."backwpup WHERE main='working'");
	if (file_exists(backwpup_get_option('cfg','tempfolder').'.backwpup_working_'.substr(md5(ABSPATH),16)))
		unlink(backwpup_get_option('cfg','tempfolder').'.backwpup_working_'.substr(md5(ABSPATH),16));
	do_action('backwpup_api_delete');
	//remove user rights
	$wp_roles -> remove_cap( 'administrator', 'backwpup' );
}