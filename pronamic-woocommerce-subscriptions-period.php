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

use DateTimeImmutable;
use DateTimeZone;
use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstrap.
 */
final class Plugin {
	/**
	 * Setup.
	 *
	 * @return void
	 */
	public function setup() {
		\add_action( 'init', [ $this, 'init' ] );
	}

	/**
	 * Initialize.
	 * 
	 * @return void
	 */
	public function init() {
		if ( \is_admin() ) {
			\add_action( 'add_meta_boxes', [ $this, 'maybe_add_pronamic_meta_box_to_wc_order' ], 10, 2 );
		}

		\add_filter( 'wcs_new_order_created', [ $this, 'wcs_new_order_created' ], 10, 2 );

		\add_filter( 'wcs_renewal_order_created2', function( $id, $test ) {
			echo 'hoi';exit;
		}, 10, 2 );

		\add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'woocommerce_checkout_update_order_meta'] );
	}


	/**
	 * Maybe add a Pronamic meta box the WooCommerce order.
	 * 
	 * @link https://github.com/pronamic/wp-pronamic-pay-woocommerce/issues/41
	 * @link https://developer.wordpress.org/reference/hooks/add_meta_boxes/
	 * @param string           $post_type_or_screen_id Post type or screen ID.
	 * @param WC_Order|WP_Post $post_or_order_object   Post or order object.
	 * @return void
	 */
	public function maybe_add_pronamic_meta_box_to_wc_order( $post_type_or_screen_id, $post_or_order_object ) {
		if ( ! \in_array( $post_type_or_screen_id, [ 'shop_order', 'woocommerce_page_wc-orders' ], true ) ) {
			return;
		}

		$order = $post_or_order_object instanceof WC_Order ? $post_or_order_object : \wc_get_order( $post_or_order_object->ID );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		\add_meta_box(
			'woocommerce-order-pronamic-period',
			\__( 'Period', 'pronamic-woocommerce-subscriptions-period' ),
			function () use ( $order ) {
				$start_date = $order->get_meta( '_pronamic_period_start_date' );
				$end_date   = $order->get_meta( '_pronamic_period_end_date' );

				var_dump( $start_date );
				var_dump( $end_date );
			},
			$post_type_or_screen_id,
			'side',
			'default'
		);
	}

	/**
	 * Woo Subscriptions renewal order created.
	 * 
	 * @link https://github.com/Automattic/woocommerce-subscriptions-core/blob/c2bf2c565a3b77b8cf1c0cb254a56f9039fa0ab6/includes/wcs-order-functions.php#L232-L239
	 * @link https://github.com/Automattic/woocommerce-subscriptions-core/blob/c2bf2c565a3b77b8cf1c0cb254a56f9039fa0ab6/includes/wcs-renewal-functions.php#L38C25-L38C50
	 * @param WC_Order        $new_order    The new order created from the subscription.
	 * @param WC_Subscription $subscription The subscription the order was created from.
	 * @return WC_Order
	 */
	public function wcs_new_order_created( $new_order, $subscription ) {
		$this->update_period_meta( $new_order, $subscription );

		return $new_order;
	}

	private function update_period_meta( $order, $subscription ) {
		$next_payment_date_timestamp_1 = $subscription->get_time( 'next_payment' );

		if ( 0 === $next_payment_date_timestamp_1 ) {
			return;
		} 

		$next_payment_date_timestamp_2 = \wcs_add_time(
			$subscription->get_billing_interval(),
			$subscription->get_billing_period(),
			$next_payment_date_timestamp_1
		);

		$start_date = new DateTimeImmutable( '@' . $next_payment_date_timestamp_1, new DateTimeZone( 'UTC' ) );
		$end_date   = new DateTimeImmutable( '@' . $next_payment_date_timestamp_2, new DateTimeZone( 'UTC' ) );

		$order->update_meta_data( '_pronamic_period_start_date', $start_date->format( 'Y-m-d H:i:s' ) );
		$order->update_meta_data( '_pronamic_period_end_date', $end_date->format( 'Y-m-d H:i:s' ) );

		$order->save();

	}

	/**
	 * Order.
	 *
	 * @link https://github.com/woocommerce/woocommerce/blob/1d593e3d8933ba3834be06d2371ae34d14a08e0c/plugins/woocommerce/includes/class-wc-checkout.php#L461-L466
	 */
	public function woocommerce_checkout_update_order_meta( $order_id ) {
		$order = \wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$subscriptions = \wcs_get_subscriptions_for_order( $order );

		foreach ( $subscriptions as $subscription ) {
			$this->update_period_meta( $order, $subscription );
		}
	}
}

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
