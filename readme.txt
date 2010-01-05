=== BackWPup ===
Contributors: danielhuesken
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=daniel%40huesken-net%2ede&item_name=Daniel%20Huesken%20Plugin%20Donation&item_number=BackWPup&no_shipping=0&no_note=1&tax=0&currency_code=EUR&lc=DE&bn=PP%2dDonationsBF&charset=UTF%2d8
Tags: backup, admin, file, Database, mysql, cron
Requires at least: 2.8
Tested up to: 2.9.0
Stable tag: 0.7.2

Backup and more of your WordPress Blog Database and Files

== Description ==

This Plugin is under heavy Development. Please test it and give feedback!!!.

Backup and more your Blog.

* Database Backup
* Optimize Database
* Check\Repair Database
* File Backup
* Uses PCLZIP class of WordPress
* Store backup to Folder
* Store backup to FTP Server
* Send Log/Backup by eMail


I can give no WARRANTY to any backups...

== Installation ==

1. Download BackWPup Plugin.
1. Decompress and upload the contents of the archive into /wp-content/plugins/.
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Where is the Database dump on DB+File backup =

in the root folder of the zip Archive. <i>DBName</i>.sql


== Screenshots ==

1. Job Page

== Changelog ==
= 0.7.3 =
* Litele changes on DB Table creation for logs

= 0.7.2 =
* make FTP any more robust
* increased memory for Zip Files
* make date with date_i18n

= 0.7.1 =
* FTP Conection test changes
* no Errors in Log for FTP ALLO command.

= 0.7.0 =
* set ftp Connection timeout to 10 sec
* fix bug for DB tables exclude
* DB Backup in mySQL Client encoding now
* Fixed missing ; in DB Backup
* Added tool DB Restore with automatic Blog Url/Path change

= 0.6.5 =
* Prevent direct file loading
* job working in iframe
* colored logs
* HTML fixes
* spell check

= 0.6.4 =
* New option to delete old logs
* Backup file deletion separated form logs deletion
* make dashboard widget smaller
* added massages
* bug fixes

= 0.6.3 =
* use ftp_row for login and other commands
* Add option to send only email on errors
* Internal structure changes
* Add option to disable WP-Cron and use Hoster cron
* bug fixes

= 0.6.2 =
* Added setting for memory_limit if needed
* Added setting for Max. Script execution time
* Added job option to make Max file size for sending via mail
* bug fixes and little improvements

= 0.6.1 =
* Added setting for Send Mail type.
* Optimize Memory usage again
* Fixed Bug that cron not work

= 0.6.0 =
* Add Dashboard Widget
* Add Database Check
* Add Backup file transfer to FTP Server
* Save log files in own database table
* Optimize Memory usage
* Optimize File system access
* DB dump with own function
* fixed some Bugs

= 0.5.5 =
* removed log files. Log now stored in Database

= 0.5.0 =
* Initial release