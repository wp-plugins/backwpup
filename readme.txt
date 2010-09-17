=== BackWPup ===
Contributors: danielhuesken
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=daniel%40huesken-net%2ede&item_name=Daniel%20Huesken%20Plugin%20Donation&item_number=BackWPup&no_shipping=0&no_note=1&tax=0&currency_code=EUR&lc=DE&bn=PP%2dDonationsBF&charset=UTF%2d8
Tags: backup, admin, file, Database, mysql, cron, ftp, S3, export, xml, Rackspase, cloud, webdav
Requires at least: 2.8
Tested up to: 3.1.0
Stable tag: 1.3.6

Backup and more of your WordPress Blog Database and Files

== Description ==

Backup and more your Blog.

* Database Backup
* WordPress XML Export
* Optimize Database
* Check\Repair Database
* File Backup
* Backups in zip,tar,tar.gz,tar.bz2 format
* Store backup to Folder
* Store backup to FTP Server
* Store backup to Amazon S3
* Store backup to RackSpaceCloud
* Send Log/Backup by eMail


I can give no WARRANTY to any backups...


== Installation ==

1. Download BackWPup Plugin.
1. Decompress and upload the contents of the archive into /wp-content/plugins/.
1. Activate the Plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==
= Requires =
* PHP 5.2.0
* WordPress 2.8
* curl (for Amazon S3 Support)
* gzip (for PCLZIP and gzip archives)
* bzip2 (for bzip2 archives)

= Where is the Database dump File =
in the root folder of the Archive. <i>DBName</i>.sql

= Where is the WordPress Export File =
in the root folder of the Archive. wordpress.<i>jjjj-mm-dd</i>.xml

= Zip File Support =
Plugin use PCLZIP lib if not PHP uses zip extension

= Maintenance Mode =
Supported Plugins
* maintenance-mode
* wp-maintenance-mode
* WordPress .maintenance file

if your Blog do not come back from Maintenance Mode switch back from Maintenance Mode by changing the Plugin options or delete the <i>.maintenance</i> file in Blog root folder.

= Restore a Blog Database =
Copy the <i>DBName</i>.sql in the root folder of the Blog and go to the tools tab in the Plugin.
You can use PHPMyAdmin also.

= Abnormal Script aborts =
Webserver normally abort Scrips that works longer then 300s.
PHP normally abort Script that works longer then 30s but the Plugin try too switch off the abortion.

= Memory usage =
* The Plugin is coded to use low memory
* The Plugin will try to increase Memory automatically if needed
* PCLZIP lib need 8MB free Memory for zipping
* Mail a archive need many Memory

= Mail archives =
I have build in many options to Optimize the Mailing but the mailing lib uses high Memory.
Place mail only little archives

= FTP Warnings =
Please deactivate Pasive Mode and try it again.

== Screenshots ==

1. Job Page

== Changelog ==
= 1.3.6 =
* long file list not longer displayed in logs.
* Added option to see detailed file list
* removed FTP Alloc command
* set FTP normal mode if pasive mode disabled
* remove FTP heler function and use FTP PHP functions
* spend file list 2MB free memory

= 1.3.5 =
* fixed problem with folder include
* added option to deactivate FTP passive mode
* fixed bug for prasing errors because PHP 5 move PHP 5 functions in a seperate file

= 1.3.4 =
* fixed warning in send mail
* bug fixes

= 1.3.3 =
* fixed bug with clear only displayed
* fiex bug with Parse Error for some php versions

= 1.3.2 =
* added changeble backup file prefix
* bug fixes

= 1.3.1 =
* added File and DB size information
* removed "LOCK TABLE" in sql dumps
* fixed bug in automatic job abortion
* fixed bug in ABSPATH if it '/'
* fiexd bug in save settings
* fiexd bugs if no jobs exists
* added link to clear running jobs

= 1.3.0 =
* added S3 new region codes for bucket creation
* added S3 REDUCED REDUNDANCY support on put Backups
* jobs will aborted after 10 min. and can't run twice
* use curl for xml dump and copy if curl not works
* increasd min. PHP version to 5.2.0, because than all works
* use linux cron based scheduing times
* added rackspacecloud.com support
* use WP 3.1 table creation
* added plugin checks for folder and new scheduling

= 1.2.1 =
* fixed "Wrong parameter count for array_unique()" for old php version
* added php version to log header
* added mysql version to log header

= 1.2.0 = 
* Backup file size now in log file
* Paged Logs Table
* added Backup Archives Page
* Grammar fixes
* Bug fixes

= 1.1.1 =
* fixed S3 lib not found bug again.
* improved reschedule on activation problem.

= 1.1.0 =
* added function to check/update job settings
* added no Ajax bucket list to job page
* changed error handling a bit and remove PHP errors that can't handled
* fixed problem with not compiled --enable-memory-limit in PHP
* removed setting for memory limit use WP filter and default now (256M)
* now a time limit of 5 mins. is set again for job execution but it will be reseted on every message. (prevent never ending jobs.)
* added a shutdown function if __destruct not called for job
* added more flexible Backup file selection

= 1.0.10 =
* fix  "Undefined index: dbshortinsert"

= 1.0.9 =
* change s3 class to hide warnings
* add option to make MySQL INSERTs shorter (smaller dump file size.)
* add requirements checks
* Ajaxed S3 bucket selection in job settings
* add S3 Bucket can made in job settings

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
* use WP functions to get Plugin dirs

= 1.0.3 =
* hopefully fixed a cache problem on run now

= 1.0.2 =
* fixed bug for file excludes

= 1.0.1 =
* fixed bug for https

= 1.0.0 =
* now WordPress Exports to XML can made
* new backup files formats tar, tar.gz, tar.bz2
* all job types can made in one job
* added PHP zip extension support (use pclzip only if not supported)
* removed PclZip trace code
* fixed time display and schedule bugs
* added some security
* Maintenance Mode on MySQL Operations
* new Design on some Pages

= 0.8.1 =
* use global var instead of constant for log file
* PCLZip Trace included with setting for log Level

= 0.8.0 =
* Fixed not working default settings on settings page
* crate .htaccsses on Apache and index.html on other Webserver
* fixed global for $wp_version
* set max execution time to 0 for unlimited
* use WP function to generate options tables
* Backup file list and zip creation changes
* Added support for Amazon S3
* Only works with PHP 5 now
* Complete rewrite of job doing as PHP5 class
* PHP errors now in Backup log
* Log now in files

= 0.7.2 =
* make FTP any more robust
* increased memory for Zip Files
* make date with date_i18n

= 0.7.1 =
* FTP Connection test changes
* no Errors in Log for FTP ALLO command.

= 0.7.0 =
* set ftp Connection timeout to 10 sec
* fix bug for DB tables exclude
* DB Backup in MySQL Client encoding now
* Fixed missing ; in DB Backup
* Added tool DB Restore with automatic Blog URL/Path change

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
* Add option to disable WP-Cron and use Hosters cron
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