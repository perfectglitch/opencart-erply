# OpenCart-Erply integration

## About

Integration module for OpenCart to fetch categories, products and images from Erply.  
Allows you to sync products with images and categories from Erply to OpenCart.  
Supports both manual and periodic sync (using cron jobs with PHP CLI).  

Initially creates:
 * categories
 * products
 * product images

For tracked products updates:
 * price, stock, name
 * images
 * category
 
 For tracked categories updates:
 * description, name
 * parent category
 
Additional features:
* Disables removed or hidden products and categories.

Erply inventory API(system/library/EAPI.php) downloaded from Erply homepage: https://learn-api.erply.com/getting-started/php.

OC versions tested: 3.0.2.0

## Installation

1. Upload files to your OpenCart installation via FTP.  
2. Enable Erply module through administration, i.e www.your-shop.com/admin.  
3. Navigate to Extensions -> Modules.  
4. Enable Erply module.  
5. Edit module settings and enter your Erply Client Code, username and password.  
6. Run sync from module settings page or use cron jobs to do that for you automatically.

![Erply module settings page](https://github.com/perfectglitch/opencart-erply-sync/blob/develop/configure_module.jpg)

## Usage

Example of starting synchronization using CLI(for Cron job):
`php-cli erply-cli/cli.php erply-sync`
