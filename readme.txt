=== BackWPup ===
Contributors: danielhuesken
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=daniel%40huesken-net%2ede&item_name=Daniel%20Huesken%20Plugin%20Donation&item_number=BackWPup&no_shipping=0&no_note=1&tax=0&currency_code=EUR&lc=DE&bn=PP%2dDonationsBF&charset=UTF%2d8
Tags: backup, admin, file, Database, mysql, cron, ftp, S3, export
Requires at least: 2.8
Tested up to: 3.0.0
Stable tag: 1.0.10

Backup and more of your WordPress Blog Database and Files

== Description ==

Backup and more your Blog.

* Database Backup
* Wordpress XML Export
* Optimize Database
* Check\Repair Database
* File Backup
* Backups in zip,tar,tar.gz,tar.bz2 formart
* Store backup to Folder
* Store backup to FTP Server
* Store backup to Amazon S3
* Send Log/Backup by eMail


I can give no WARRANTY to any backups...


== Installation ==

1. Download BackWPup Plugin.
1. Decompress and upload the contents of the archive into /wp-content/plugins/.
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==
= Requires =
* PHP 5.0.0
* Wordpress 2.8
* curl (for Amazon S3 Support)
* gzip (for PCLZIP and gzip archives)
* bzip2 (for bzip2 archives)

= Where is the Database dump File =
in the root folder of the Archive. <i>DBName</i>.sql

= Where is the Wordpress Export File =
in the root folder of the Archive. wordpress.<i>jjjj-mm-dd</i>.xml

= Zip File Support =
Plugin use PCLZIP lib if not php uses zip extension

= Mantinance Mode =
Supported Plugins
* maintenance-mode
* wp-maintenance-mode
* Wordpress .mantinance file

if your blog do not come back from Mantinace Mode switsh back from Mantinace Mode by changing the Plugin options or delete the <i>.mantinance</i> file in blog root folder.

= Retore a Blog DataBase =
Copy the <i>DBName</i>.sql in the root folder of the blog and go to the tools tab in the plugin.
You kann use PHPMyAdmin also.

= Abnormal Script aborts =
Webserver normaly abort Scrips that works longer then 300s.
PHP normaly abort Script that works langen then 30s but the plugin try too switch off the arbotion.

= Memory usage =
* The Plugin is coded to use low memory
* The Plugin will try to increase Memory automaticly if needed
* PCLZIP lib need 8MB free Memeory for ziping
* Mail a archive need many Memory

= Mail achives =
I have build in many options to Optimize the Mailing but the mailing lib uses high Memory.
Pleace mail only littele archives

== Screenshots ==

1. Job Page

== Changelog ==
= 1.1.0 =
* added fuction to check/update job settings
* added no ajax bucket list to job page
* changed error handling a bit and remove PHP errors that can't handeld.
* fixed problem with not compiled --enable-memory-limit in php
* removed setting for memory limit use WP filter and default now (256M)

= 1.0.10 =
* fix  "Undefined index: dbshortinsert"

= 1.0.9 =
* change s3 class to hide warnigs
* add option to make MySQL INSERTs shorter (smaler dump file size.)
* add requerments checks
* ajaxed S3 bucket selection in job settings
* add S3 Buckt can made in job settings

= 1.0.8 =
* fix temp backup file not deleted if no destination folder
* some folder fixes
* removed some not used code

= 1.0.7 =
* added flattr button in Help
* Fixed bug on S3 File deletion
* get files form S3 now faster for file deletion

= 1.0.6 =
* fixed false massage an send mail with backup
* removed test code for blank screen and fixed it!

= 1.0.5 =
* some ABSPATH changes

= 1.0.4 =
* fixed bugs in DB restore
* use WP functions to get plugin dirs

= 1.0.3 =
* hopfuly fixed a chche problem on runnow

= 1.0.2 =
* fiexd bug for file excludes

= 1.0.1 =
* fiexd bug for https

= 1.0.0 =
* now Worpress Exports to XML can made
* new backup files formats tar, tar.gz, tar.bz2
* all job types can made in one job
* added php zip extension support (use pclzip only if not supported)
* removed PclZip trace code
* fixed time display and schedule bugs
* added some security
* Mantinance Mode on MySQL Operations
* new Design on some Pages

= 0.8.1 =
* use global var instat of constant for log file
* PCL Zip Trace included with setting for log Level

= 0.8.0 =
* Fiexed not working default setttings on settingspage
* crate .htaccsses on Apache and index.html on other webserver
* fixed global for $wp_version
* set max execution time to 0 for unlimeted
* use WP function to generate options tables
* Backup file list and zip creation changes
* Added support for Amazon S3
* Only works with PHP 5 now
* Cmplete rewrite of job doing as PHP5 class
* PHP errors now in Backup log
* Log now in files

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