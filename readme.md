# ACJ MANGODB SYNC

* Contributors:      Arun Chaitanya Jami
* Tags:              plugin
* Tested up to:      6.1
* Stable tag:        1.0.0


## Description

This is the long description. No limit, and you can use Markdown (as well as in the following sections).

For backwards compatibility, if this section is missing, the full length of the short description will be used, and
Markdown parsed.

## Pre-requisites

This plugin required MANGODB package an extension enabled. Please follow the below process.

##### 1. Install MongoDB PHP Extension
You can install the MongoDB PHP extension using PECL (PHP Extension Community Library). Make sure you have pecl installed on your system. Then, run the following command:
```shell
pecl install mongodb
```
Follow the prompts to complete the installation process.

##### 2. Install Required Dependencies

Make sure you have the necessary SSL libraries installed on your system. On Debian/Ubuntu-based systems, you can install them using:
```shell
sudo apt-get install openssl libssl-dev
```
On CentOS/RHEL-based systems, you might need to install `openssl-devel`:
```shell
sudo yum install openssl openssl-devel
```

#### 3. Enable the MongoDB Extension
Once the extension is installed, you need to enable it in your PHP configuration. Find your php.ini file (you can locate it by running php --ini in the command line), and add the following line:
```shell
extension=mongodb.so
```

Make sure to restart your web server (e.g., Apache or Nginx) after making this change for the configuration to take effect.

#### 4. Verify Installation
You can verify that the MongoDB PHP extension is installed and enabled by running the following command in your terminal:
```shell
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

1. Upload the plugin files to the `/wp-content/plugins/acj-mangodb-clone` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
