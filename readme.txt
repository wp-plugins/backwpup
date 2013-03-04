=== BackWPup ===
Contributors: inpsyde, danielhuesken, Bueltge, nullbyte
Tags: backup, dump, database, file, ftp, xml, time, upload, multisite, cloud, dropbox, storage, S3
Requires at least: 3.2
Tested up to: 3.5.1
Stable tag: 3.0.4
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Flexible, scheduled WordPress backups to any location

== Description ==

The backup files can be used to save your whole installation including `/wp-content/`
and push them to an external Backup Service, if you don’t want to save the backups on
the same server. With the single backup .zip file you are able to restore an installation.

* Database Backup  *(needs mysqli)*
* WordPress XML Export
* Generate a file with installed plugins
* Optimize Database
* Check and repair Database
* File backup
* Backups in zip, tar, tar.gz, tar.bz2 format *(needs gz, bz2, ZipArchive)*
* Store backup to directory
* Store backup to FTP server *(needs ftp)*
* Store backup to S3 services *(needs curl)*
* Store backup to Microsoft Azure (Blob) *(needs PHP 5.3.2, curl)*
* Store backup to RackSpaceCloud *(needs PHP 5.3.2, curl)*
* Store backup to Dropbox *(needs curl)*
* Store backup to SugarSync *(needs curl)*
* Send logs and backups by email
* Multi-site support only as network admin

Get the Pro Version: http://marketpress.com/product/backwpup-pro/

**WordPress 3.2 and PHP 5.2.6 required!**

**To use the Plugin with full functionality PHP 5.3.3 with mysqli, FTP,gz, bz2,  ZipArchive and curl is needed.**

**Plugin functions that don't work will be not displayed.**

**Test your Backups!**

**Made by [Inpsyde](http://inpsyde.com) &middot; We love WordPress**

Have a look at the premium plugins in our [market](http://marketpress.com).

== Frequently Asked Questions ==

= EN =
* Manual: http://marketpress.com/documentation/backwpup-pro/

= DE =
* Dokumentation: http://marketpress.de/dokumentation/backwpup-pro/

== Screenshots ==

1. Working job and jobs overview
2. Job creation/edit
3. Displaying logs
4. Manage backup archives
5. Dashboard

== Upgrade Notice ==
= After an upgrade from version 2 =

Please check all settings after the update:

* Dropbox authentication must be done again
* SugarSync authentication must be done again
* S3 Settings
* Google Storage is now in S3
* Check all your passwords

== Installation ==

1. Download the BackWPup plugin.
2. Decompress the ZIP file and upload the contents of the archive into `/wp-content/plugins/`.
3. Activate the plugin through the 'Plugins' menu in WordPress


== Changelog ==
= Version 3.0.4 =
* Changed: default settings for 'Restart on every main step' and 'Reduce server load' to disabled
* Fixed: Settings not correctly set to default
* Fixed: mysqli::get_charset() undefined method
* Fixed: Settings not saved correctly
* Fixed: Abort on MySQL Functions Backup
* Improved: MySQLi connection
* Added: Server connection test on run now.
* Added: S3 AWS SDK 1.6.0 for PHP lower than 5.3.3

= Version 3.0.3 =
* Improved: Archive creation performance
* Fixed: Problem with S3 Prefix
* Fixed: warnings on excluded folders
* Fixed: message from putenv
* Fixed: not working downloads
* Changed: removed fancybox and uses thickbox because plugin compatibility
* Added: folder checking on run now

= Version 3.0.2 =
* Fixed: Warnings on job edit tab files
* Fixed: folder name on temp cleanup in cron
* Fixed: Setting charset on sql backup
* Fixed: DB Connection on database backup if hostname has a port
* Fixed: Call undefined function apc_clear_cache()
* Fixed: wp-content selected folders not excluded
* Added: Deactivation off multi part upload for S3 Services
* Added: fallback for mysql_ping()
* Added: Options for email senders name
* Changed: 5 minutes cron steps back
* Removed: Flashing admin bar icon
* Updated: OpenCloud API to Version 1.4.1

= Version 3.0 =
* Added: Jobs can now be started with an external link or per command line
* Added: Backups can now be compressed with gz or bzip2
* Added: All file names can now be adjusted
* Added: MySQL dump supports now views
* Added: Settings for access control per capability and role
* Added: Save a list of installed Plugins
* Added: Support for WP-CLI
* Improved: Job edit page with tabs
* Improved: Settings page with tabs
* Improved: Database dump now uses mysqli PHP extension for better performance
* Improved: ZIP archives are now created with PHP Zip if available
* Improved: All passwords are now stored encrypted in database
* Improved: wp-cron job start mechanism
* Improved: Job start mechanism not longer uses URL in plugin directory
* Improved: Use `temp` directory in uploads or set it with `WP_TEMP_DIR`
* Changed: Mailing backup archives now with SwiftMailer
* Changed: Job process now back in the WordPress environment
* Changed: License changed to GPLv3
* Changed: Rewrote almost the complete code base to use classes with auto-loading
* Changed: Logs are now displayed with fancybox
* Updated: AWS SDK v2.1.2 (PHP 5.3.3)
* Updated: OpenCloud SDK to v1.3 (PHP 5.3)
* Updated: Windows Azure SDK v0.3.1_2011-08 (PHP 5.3.2)
* Removed: serialized job export
* Removed: tools section - not needed anymore
* Removed: Dashboard widgets are now on the BackWPup plugin dashboard
* Fixed: many, many minor bugs

= Version 3.0 Pro =

* Wizards
* Export jobs and settings as XML
* Synchronization of files to backup with destination (filename and size checked)
* Wizard to import jobs and settings from XML
* Database dump can backup other MySQL databases
* Database dump can use `mysqldump` command on commend line
* Database dump can create XML files (phpMyAdmin schema)
* Use your own API keys for Dropbox and SugarSync
* Premium Support
* Automatic updates
