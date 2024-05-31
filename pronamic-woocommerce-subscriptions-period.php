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
		\add_filter( 'wcs_new_order_created', [ $this, 'wcs_new_order_created' ], 10, 2 );

		\add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'woocommerce_checkout_update_order_meta'] );

		\add_filter( 'woocommerce_add_cart_item_data', [ $this, 'woocommerce_add_cart_item_data' ], 30, 3 );

		\add_filter( 'woocommerce_get_item_data', [ $this, 'woocommerce_get_item_data' ], 10, 2 );

		\add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'woocommerce_checkout_create_order_line_item' ], 20, 3 );

		\add_filter( 'woocommerce_order_item_display_meta_key', [ $this, 'woocommerce_order_item_display_meta_key' ], 10, 2 );

		\add_filter( 'woocommerce_order_again_cart_item_data', [ $this, 'woocommerce_order_again_cart_item_data' ], 10, 2 );
	}

	/**
	 * WooCommerce add cart item data.
	 *  
	 * This filter function is called as soon as a WooCommerce product is added to the cart.
	 * 
	 * @link https://github.com/woocommerce/woocommerce/blob/7.7.0/plugins/woocommerce/includes/class-wc-cart.php#L1137-L1138
	 * @param array $cart_item_data WooCommerce cart item data.
	 * @param int   $product_id     Product ID.
	 * @param int   $variation_id   Variation ID.
	 * @return array
	 */
	public function woocommerce_add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		if ( ! \array_key_exists( 'subscription_renewal', $cart_item_data ) ) {
			return $cart_item_data;
		}

		$subscription_renewal_data = $cart_item_data['subscription_renewal'];

		if ( ! \is_array( $subscription_renewal_data ) ) {
			return $cart_item_data;
		}

		if ( ! \array_key_exists( 'subscription_id', $subscription_renewal_data ) ) {
			return $cart_item_data;
		}

		$subscription_id = $subscription_renewal_data['subscription_id'];

		$subscription = \wcs_get_subscription( $subscription_id );

		if ( false === $subscription ) {
			return $cart_item_data;
		}

		$period = $this->get_next_period( $subscription );

		if ( null === $period ) {
			return $cart_item_data;
		}

		$cart_item_data['_pronamic_start_date'] = $period->start_date->format( 'Y-m-d H:i:s' );
		$cart_item_data['_pronamic_end_date']   = $period->end_date->format( 'Y-m-d H:i:s' );

		return $cart_item_data;
	}

	/**
	 * WooCommerce get item data.
	 * 
	 * This filter function is called to get a list of cart item data + variations for display on the frontend.
	 *
	 * @link https://github.com/woocommerce/woocommerce/blob/7.7.0/plugins/woocommerce/includes/wc-template-functions.php#L3774-L3775
	 * @param array $item_data Item data to display.
	 * @param array $cart_item Cart item object.
	 */
	public function woocommerce_get_item_data( $item_data, $cart_item ) {
		if ( \array_key_exists( '_pronamic_start_date', $cart_item ) ) {
			$start_date_string = $cart_item['_pronamic_start_date'];

			$item_data[] = [
				'key'   => \__( 'Start date', 'pronamic-woocommerce-subscriptions-period' ),
				'value' => $start_date_string,
			];
		}

		if ( \array_key_exists( '_pronamic_end_date', $cart_item ) ) {
			$end_date_string = $cart_item['_pronamic_end_date'];

			$item_data[] = [
				'key'   => \__( 'End date', 'pronamic-woocommerce-subscriptions-period' ),
				'value' => $end_date_string,
			];
		}

		return $item_data;
	}

	/**
	 * WooCommerce checkout create order line item.
	 * 
	 * This action function is called when order items are created from the cart.
	 * 
	 * @link https://github.com/woocommerce/woocommerce/blob/7.7.0/plugins/woocommerce/includes/class-wc-checkout.php#L544-L549
	 * @param WC_Order_Item $item          WooCommerce order item.
	 * @param string        $cart_item_key Cart item key.
	 * @param array         $values        Cart item values.
	 * @return void
	 */
	public function woocommerce_checkout_create_order_line_item( $item, $cart_item_key, $values ) {
		if ( \array_key_exists( '_pronamic_start_date', $values ) ) {
			$item->update_meta_data( '_pronamic_start_date', $values['_pronamic_start_date'] );
		}

		if ( \array_key_exists( '_pronamic_end_date', $values ) ) {
			$item->update_meta_data( '_pronamic_end_date', $values['_pronamic_end_date'] );
		}
	}

	/**
	 * WooCommerce order item display meta key.
	 * 
	 * This filter function is called when order item meta is displayed.
	 * 
	 * @link https://github.com/woocommerce/woocommerce/blob/7.7.0/plugins/woocommerce/includes/class-wc-order-item.php#L301
	 * @param string $display_key Display key.
	 * @param object $meta        Meta object.
	 * @return string
	 */
	public function woocommerce_order_item_display_meta_key( $display_key, $meta ) {
		switch ( $meta->key ) {
			case '_pronamic_start_date':
				return \__( 'Start date', 'pronamic-woocommerce-subscriptions-period' );
			case '_pronamic_end_date':
				return \__( 'End date', 'pronamic-woocommerce-subscriptions-period' );
			default:
				return $display_key;
		}
	}

	/**
	 * WooCommerce order again cart item data.
	 * 
	 * @link https://github.com/Automattic/woocommerce-subscriptions-core/blob/5.9.0/includes/class-wcs-cart-renewal.php#L345
	 * @param array         $item_data Cart item data.
	 * @param WC_Order_Item $line_item Order line item.
	 * @return array
	 */
	public function woocommerce_order_again_cart_item_data( $item_data, $line_item ) {
		$start_date = (string) $line_item->get_meta( '_pronamic_start_date' );

		if ( '' !== $start_date ) {
			$item_data['_pronamic_start_date'] = $start_date;
		}

		$end_date = (string) $line_item->get_meta( '_pronamic_end_date' );

		if ( '' !== $end_date ) {
			$item_data['_pronamic_end_date'] = $end_date;
		}

		return $item_data;
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
		var_dump( $new_order );
		exit;
		$this->update_period_meta( $new_order, $subscription );

		return $new_order;
	}

	private function get_next_period( $subscription ) {
		$next_payment_date_timestamp_1 = $subscription->get_time( 'next_payment' );

		if ( 0 === $next_payment_date_timestamp_1 ) {
			return null;
		} 

		$next_payment_date_timestamp_2 = \wcs_add_time(
			$subscription->get_billing_interval(),
			$subscription->get_billing_period(),
			$next_payment_date_timestamp_1
		);

		$start_date = new DateTimeImmutable( '@' . $next_payment_date_timestamp_1, new DateTimeZone( 'UTC' ) );
		$end_date   = new DateTimeImmutable( '@' . $next_payment_date_timestamp_2, new DateTimeZone( 'UTC' ) );

		return (object) [
			'start_date' => $start_date,
			'end_date'   => $end_date,
		];
	}

	private function update_period_meta( $order, $subscription ) {
		$period = $this->get_next_period( $subscription );

		if ( null === $period ) {
			return;
		}

		$order->update_meta_data( '_pronamic_period_start_date', $period->start_date->format( 'Y-m-d H:i:s' ) );
		$order->update_meta_data( '_pronamic_period_end_date', $period->end_date->format( 'Y-m-d H:i:s' ) );

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
