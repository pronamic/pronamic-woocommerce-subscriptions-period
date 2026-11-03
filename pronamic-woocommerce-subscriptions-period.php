<?php
/**
 * Pronamic period information for WooCommerce Subscriptions
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\WooCommerceSubscriptionsPeriod
 *
 * @wordpress-plugin
 * Plugin Name:          Pronamic period information for WooCommerce Subscriptions
 * Plugin URI:           https://www.pronamic.eu/plugins/pronamic-woocommerce-subscriptions-period/
 * Description:          This “WooCommerce Subscriptions” add-on ensures that a period is saved with each subscription order.
 * Version:              1.0.0
 * Requires at least:    6.7
 * Requires PHP:         8.3
 * Author:               Pronamic
 * Author URI:           https://www.pronamic.eu/
 * Text Domain:          pronamic-woocommerce-subscriptions-period
 * Domain Path:          /languages/
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins:     woocommerce
 * GitHub URI:           https://github.com/pronamic/pronamic-woocommerce-subscriptions-period
 * WC requires at least: 8.0
 * WC tested up to:      11.2
 */

declare(strict_types=1);

namespace Pronamic\WooCommerceSubscriptionsPeriod;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

( static function (): void {
	$autoload_path = __DIR__ . '/vendor/autoload_packages.php';

	if ( \file_exists( $autoload_path ) ) {
		require_once $autoload_path;
	}

	Plugin::instance( __FILE__ );
} )();
