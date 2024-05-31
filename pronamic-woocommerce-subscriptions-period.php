<?php
/**
 * Plugin Name: Pronamic period information for Woo Subscriptions
 * Plugin URI: https://www.pronamic.eu/plugins/pronamic-woocommerce-subscriptions-period/
 * Description: This “Woo Subscriptions” add-on ensures that a period is saved with each subscription order.
 *
 * Version: 1.0.0
 * Requires at least: 5.9
 * Requires PHP: 8.1
 *
 * Author: Pronamic
 * Author URI: https://www.pronamic.eu/
 *
 * Text Domain: pronamic-woocommerce-subscriptions-period
 * Domain Path: /languages/
 *
 * License: GPL-2.0-or-later
 *
 * GitHub URI: https://github.com/pronamic/pronamic-woocommerce-subscriptions-period
 *
 * WC requires at least: 8.0
 * WC tested up to: 8.0
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2023 Pronamic
 * @license   GPL-3.0-or-later
 * @package   Pronamic\WooSubscriptionsPeriod
 */

namespace Pronamic\WooSubscriptionsPeriod;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Autoload.
 */
require_once __DIR__ . '/vendor/autoload_packages.php';

/**
 * Bootstrap.
 */
add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain( 'pronamic-woocommerce-subscriptions-period', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
	}
);

$pronamic_wcsp = new Plugin();

$pronamic_wcsp->setup();

/**
 * High Performance Order Storage.
 * 
 * @link https://github.com/pronamic/pronamic-payment-gateways-fees-for-woocommerce/issues/4
 * @link https://github.com/woocommerce/woocommerce/wiki/High-Performance-Order-Storage-Upgrade-Recipe-Book#declaring-extension-incompatibility
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);
