# OpenCart-Erply synchronization

## About
An experimental unidirectional synchronization module for OpenCart to fetch categories, products and images from Erply.

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

## Usage

Example of starting synchronization using CLI(for Cron job):
`php-cli erply-cli/cli.php erply-sync`
