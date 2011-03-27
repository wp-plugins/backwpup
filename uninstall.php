<?PHP
if( !defined( 'ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') )
    exit();

delete_option('backwpup');
delete_option('backwpup_jobs');
delete_option('backwpup_backups_chache');
delete_option('backwpup_dropboxrequest');
?>
