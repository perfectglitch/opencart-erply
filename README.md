# OpenCart-Erply synchronization

## About
An experimental unidirectional synchronization module for OpenCart to fetch categories, products and images from Erply.

Initially creates:
 * Categories
 * Products
 * Images

For existing products updates:
 * Product price
 * Product stock
 * Product images
 
Erply inventory API(system/library/EAPI.php) downloaded from Erply homepage: https://learn-api.erply.com/getting-started/php.

OC versions tested: 3.0.2.0

## Usage

Example of starting synchronization using CLI(for Cron job):
`php-cli erply-cli/cli.php erply-sync`
