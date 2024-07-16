# ACJ MONGODB SYNC

* Contributors:      iamarunchaitanyajami
* Tags:              mongodb, wp mongodb, MongoDb Sync, MongoDb Clone, WP MongoDb Sync, mongodb
* Stable tag:        1.1.0
* Requires at least: 4.3
* Tested up to:      6.5.2
* Requires PHP:      8.0 & greater
* License: GPLv2 or later 
* License URI: http://www.gnu.org/licenses/gpl-2.0.html

MONGODB SYNC is a plugin that help you sync data from WordPress to Mongo Db

## Requirements
- Requires PHP: 8.0 & greater

## Contributors

- Contributors: iamarunchaitanyajami

## Description

ACJ MONGODB SYNC is a plugin that help you sync data from WordPress to Mongo Db. It completely works in backend and will not break any part of the site while
users having the smooth WordPress Experience.

### Currently Supports
- All Options Sync
- All Terms Sync
- Any Post Type Sync.
- All Users.

## Pre-requisites

This plugin required MONGODB package an extension enabled. Please follow the below process.

#### 1. Install Required Dependencies

Make sure you have the necessary SSL libraries installed on your system. On Debian/Ubuntu-based systems, you can install them using:
```
sudo apt-get install -y openssl libssl-dev libcurl4-openssl-dev pkg-config libssl-dev
```
On CentOS/RHEL-based systems, you might need to install `openssl-devel`:
```
sudo yum install openssl openssl-devel
```

#### 2. Install MongoDB PHP Extension
You can install the MongoDB PHP extension using PECL (PHP Extension Community Library). Make sure you have pecl installed on your system. Then, run the following command:

```
apt-get update

apt-get install libmongoc-1.0-0

pecl install mongodb
```

Follow the prompts to complete the installation process.

#### 3. Enable the MongoDB Extension
Once the extension is installed, you need to enable it in your PHP configuration. Find your php.ini file (you can locate it by running php --ini in the command line), and add the following line:

To find where php.ini file, use below command.

```
php -i | grep 'php.ini'
```

Then add below code

```
extension=mongodb.so
mongodb.ssl = true
```

Make sure to restart your web server (e.g., Apache or Nginx) after making this change for the configuration to take effect.

#### 4. Verify Installation
You can verify that the MongoDB PHP extension is installed and enabled by running the following command in your terminal:

```
php -m | grep mongodb
```

If the MongoDB extension is properly installed and enabled, you should see mongodb in the list of enabled modules.

#### 5. Handling Installation Issues
If you encounter any issues during the installation process, make sure you have the necessary build tools and development headers installed on your system. You may need packages like `php-dev`, `gcc`, `make`, and others depending on your operating system.

Additionally, refer to the official MongoDB PHP extension documentation for troubleshooting tips and platform-specific installation instructions.

Once you've installed and enabled the MongoDB PHP extension, the error you encountered should be resolved, and your PHP script should be able to interact with MongoDB successfully.

## Plugin Installation

This section describes how to install the plugin and get it working.

e.g.

1. Upload the plugin files to the `/wp-content/plugins/acj-mongodb-sync` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress

## == Changelog ==

### 1.2.0
* Add multisite data sync for blog, site meta.
* Fixed Attachments not sync issue.
* Move settings to network admin when used on multisite.
* Fixed read.md file UI changes.

### 1.1.0
* Added No GPL-compatible license declared.
* Fixed/removed Unneeded Folders.
* More Generic function/class/define/namespace/option names.

### 1.0.6
* Sync Users to Mongodb.
* Fix: Post meta sync in cron.

### 1.0.5
* Cli increase per page limit.

### 1.0.4
* Cli commands to sync the untracked posts and terms from the site.

### 1.0.3
* Bug fix ACTION HOOKS.

### 1.0.2
* Update Read.md file.

### 1.0.1
* Allows users to select custom post types to sync.
* Allows users to select custom post status to sync. 
* Allows users to select custom Terms to sync.
* Flexibility for developers to push data for custom tables via allowed functions.

### 1.0.0
* Initial plugin.

## == Upgrade Notice ==

### 1.2.0
* Add multisite data sync for blog, site meta. 
* Fixed Attachments not sync issue.
* Move settings to network admin when used on multisite.
* Fixed read.md file UI changes and added more question.

## == Frequently Asked Questions ==

* How can we sync custom data and store in MangoDb?
  * We have custom functions defined to create tables and send data to the tables created
  * Example : 
    * ``acjpd_mongodb_push_data`` will help data to push to custom tables.
    * ``acjpd_mongodb_delete_data`` will help data to delete from custom tables.
* Will this plugin works for WordPress Multisite?
  * Yes
* Will this plugin works for WordPress Single Site?
  * Yes
* Will this plugin works for WordPress WooCommerce?
  * No for now, but we will be extending it in further versions.

## == Screenshots ==

1. Go to Mangodb Settings Screen.
2. Add MangoDb URI
3. Select the post type, taxonomy data and Post Status data to sync.