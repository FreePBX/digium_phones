# Digium Phones Module

    Copyright (c) 2011, Digium, Inc.
    GNU General Public License Version 2

    Originally Written by Jason Parker
    Currently maintained by Scott Griepentrog <sgriepentrog@digium.com>

## Purpose

This module provides a convenient way to configure Digium IP Phones with FreePBX.

Supported features:

  * Easy mode: phone configuration built automatically from FreePBX extensions
  * Contact lists (manually edited and automatically built from extensions)
  * Alerts and Ringtones
  * Queue and Agent monitoring on phone
  * Custom Status Messsages
  * Custom Phone Applications
  * Uploadable Phone Logo
  * Supports multiple network configurations
  * External Lines
  * Firmware updates

## Module components

  * PHP files
    * `functions.inc.php` - definition of classes and utility functions for managing configuration data and objects, interfacing with Mysql, and building configuration
	* classes/digium_phones*.php - support classes moved out of functions.inc
	* conf/*.php - configuration file generators moved out of functions.inc
    * `page.digium_phones.php` - top level page generation code (calls appropriate views/*.php for subpages)
    * `install.php` - steps to run on module first installation to insure database and filesystem is configured with default settings
    * `uninstall.php` - run at removal (not upgrade) of module to erase database tables
    * `views/digium_phones_*.php` - html generation for each data table

  * CSS files
    * `assets/css/digium_phones.css` - custom styles used within digium_phones module

  * Javascript files
    * `assets/js/phones.js` - drop box and submit handling
    * `assets/js/digium_phone_queues.js` - handles queue sorting

  * Configuration files
    * `etc/res_digium_phone.conf` - symlinked into /etc/asterisk, this includes the configuration files generated to configure DPMA

## Storage locations

The module itself when installed is located at `/var/www/html/admin/modules/digium_phones`.

  * On a FreePBX distro, the /admin URL (anything in /var/www/html/admin) and all subdirs are restricted by Apache htpasswd configuration and is accessible only by the admin user(s).
  * When the module is upgraded this directory is completely deleted prior to extracting the new tarball (nothing stored here will survive module udpate).

Other locations that are used to store different types of files and information:

  * `/var/www/html/digium_phones/firmware_package` \- files accessible to phones via HTTP since module version 2.11.1.0 (firmware_package_directory setting in res_digium_phone_general.conf)
  * `/etc/asterisk/res_digium_phone*.conf` \- configuration for DPMA (res_digium_phone) itself.
  * `/etc/asterisk/digium_phones` \- stores files that DPMA (res_digium_phone) transmits to the phone via SIP (file_directory setting in res_digium_phone_general.conf)
  * Mysql database `asterisk` tables `digium_phones_*`

## Phone configuration files

#### Configuration files generated on FreePBX rebuild (pressing APPLY CONFIG button)

  * In /etc/asterisk
    * res_digium_phone.conf (symlink to /var/www/html/admin/modules/digium_phones/etc/res_digium_phone.conf)
    * res_digium_phone_general.conf - general/global configuration settings
    * res_digium_phone_devices.conf - list of phones and their individual configuration
    * res_digium_phone_applications.conf - application, queue, status settings for each phone
    * res_digium_phone_firmware.conf - list of firmware packages available

  * In /etc/asterisk/digium_phones (delivered to phone by DPMA):
    * contacts-{phonebookid}.xml - user configured contact lists
    * contacts-internal-{deviceid}.xml - automatically generated contacts from list of phones (built unique for each phone)

#### Configuration files uploaded by user

  * In /etc/asterisk/digium_phones (delivered to phone by DPMA)
    * `user_image_{id}.png` - user uploaded

  * In /var/www/html/digium_phones/firmware_package (downloaded by phone via HTTP)
    * `user_ringtone_{id}.raw` - ring tone in mono, 16000 samples/sec, 16 bit signed linear, raw (no header)
    * `application_{id}.zip` - json application to run on phone
    * `firmware_{version}_package` - directory containing firmware files for various models

## Changes

  * Cleaned up functions.inc, moved extra classes to classes subdir, config generators to conf subdir
  * Added pin=voicemail support for DPMA 2.1
  * Logo improvements: upload most any image format, automatically resized, D45 now supported
  * Status (presence) will now use FreePBX PreseneceState module settings if it is enabled
  * Can now download older firmware versions, not just the current one
  * Ringtones can now be configured individually to the phone without having to be set as default
  * General status page now shows version of the DPMA module installed in Asterisk

## Support files

  * build_test_module - bash script that creates uploadable tarball for testing
  * Doxyfile - configuration for doxygen (run 'doxygen' to generate html documentation)

## Notes:

  1. DPMA 1.7 and later: contacts and logos **can** be downloaded from file_url_prefix, but we don't want to expose contact lists via a publicly accessible http url.
  2. Firmware files can't be uploaded directly due to often exceeding the PHP max upload file size

