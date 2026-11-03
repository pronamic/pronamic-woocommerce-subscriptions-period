=== Pronamic period information for WooCommerce Subscriptions ===
Contributors: pronamic, remcotolsma
Tags: woocommerce, subscriptions, period
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This “WooCommerce Subscriptions” add-on ensures that a period is saved with each subscription order.

== Description ==

The “WooCommerce Subscriptions” plugin itself does not store the subscription period in an order. Without this add-on, it is not clear to webshop owners and customers for what period the subscription order and associated payment was.

The period is stored in the `_pronamic_start_date` and `_pronamic_end_date` order item meta.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the "Plugins" menu in WordPress

== Changelog ==

= 2.0.0 =
* Requires PHP 8.3 and WordPress 6.8 or higher.
* Requires the WooCommerce and WooCommerce Subscriptions plugins (`Requires Plugins` header).
* Translations are now loaded just-in-time by WordPress.
* Modernized the plugin code (strict types, readonly `Period` class, `Plugin` singleton).
* Updated Jetpack Autoloader to 6.0.3 and Pronamic WordPress Updater to 1.0.4.

= 1.0.0 =
* Initial release.
