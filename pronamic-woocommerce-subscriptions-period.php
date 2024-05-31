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
use WC_Subscription;

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
		\add_filter( 'woocommerce_add_cart_item_data', [ $this, 'woocommerce_add_cart_item_data' ], 30, 3 );

		\add_filter( 'woocommerce_get_item_data', [ $this, 'woocommerce_get_item_data' ], 10, 2 );

		\add_action( 'woocommerce_checkout_create_subscription', [ $this, 'woocommerce_checkout_create_subscription' ], 10, 4 );

		\add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'woocommerce_checkout_create_order_line_item' ], 20, 3 );

		\add_filter( 'woocommerce_order_item_display_meta_key', [ $this, 'woocommerce_order_item_display_meta_key' ], 10, 2 );
		\add_filter( 'woocommerce_order_item_display_meta_value', [ $this, 'woocommerce_order_item_display_meta_value' ], 10, 2 );

		\add_filter( 'wcs_renewal_order_created', [ $this, 'wcs_renewal_order_created' ], 10, 2 );
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
		/**
		 * In the case of a subscription renewal we will try to add the period information to the cart items.
		 */
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

		$period = $this->get_period( $subscription );

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

			$start_date = new DateTimeImmutable( $start_date_string, new DateTimeZone( 'UTC' ) );

			$item_data[] = [
				'key'   => \__( 'Start date', 'pronamic-woocommerce-subscriptions-period' ),
				'value' => \wp_date( \get_option( 'date_format' ), $start_date->getTimestamp() ),
			];
		}

		if ( \array_key_exists( '_pronamic_end_date', $cart_item ) ) {
			$end_date_string = $cart_item['_pronamic_end_date'];

			$end_date = new DateTimeImmutable( $end_date_string, new DateTimeZone( 'UTC' ) );

			$item_data[] = [
				'key'   => \__( 'End date', 'pronamic-woocommerce-subscriptions-period' ),
				'value' => \wp_date( \get_option( 'date_format' ), $end_date->getTimestamp() ),
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
		/**
		 * If the shopping cart item contains period information, we will transfer this to the order item.
		 */
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
		/**
		 * User-friendly display of the period meta keys.
		 */
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
	 * WooCommerce order item display meta value.
	 * 
	 * This filter function is called when order item meta is displayed.
	 * 
	 * @link https://github.com/woocommerce/woocommerce/blob/7.7.0/plugins/woocommerce/includes/class-wc-order-item.php#L302
	 * @param string $display_value Display value.
	 * @param object $meta          Meta object.
	 * @return string
	 */
	public function woocommerce_order_item_display_meta_value( $display_value, $meta ) {
		/**
		 * User-friendly display of the period meta values.
		 */
		switch ( $meta->key ) {
			case '_pronamic_start_date':
				$start_date = new DateTimeImmutable( $meta->value, new DateTimeZone( 'UTC' ) );

				return \wp_date( \get_option( 'date_format' ), $start_date->getTimestamp() );
			case '_pronamic_end_date':
				$end_date = new DateTimeImmutable( $meta->value, new DateTimeZone( 'UTC' ) );

				return \wp_date( \get_option( 'date_format' ), $end_date->getTimestamp() );
			default:
				return $display_value;
		}
	}

	/**
	 * WooCommerce checkout create subscription.
	 * 
	 * This action is initiated when a subscription is created for an order in
	 * the WooCommerce checkout process. This action is called in the
	 * `WC_Subscriptions_Checkout::process_checkout( $order_id, $posted_data )`
	 * function, which is hooked into the WooCommerce action
	 * `woocommerce_checkout_order_processed`.
	 * 
	 * We use this action to update the order items with the period information 
	 * from the WooCommerce subscription object.
	 * 
	 * @link https://github.com/Automattic/woocommerce-subscriptions-core/blob/7.1.1/includes/class-wc-subscriptions-checkout.php#L246-L247
	 * @param WC_Subscription $subscription Subscription.
	 * @param array           $posted_data  Posted data.
	 * @param WC_Order        $order        Order.
	 * @return void
	 */
	public function woocommerce_checkout_create_subscription( $subscription, $posted_data, $order, $cart ) {
		$this->update_order_items_meta( $subscription, $order, 'date_created' );
	}

	/**
	 * WooCommerce Subscriptions renewal order created.
	 * 
	 * This filter is called when a subscription renewal order is created.
	 * This happens, for example, when a renewal order is created via the
	 * Action Scheduler library on the next payment date of a subscription.
	 * 
	 * We use this filter to update the order items with the period information 
	 * from the WooCommerce subscription object.
	 * 
	 * @link https://github.com/Automattic/woocommerce-subscriptions-core/blob/7.1.1/includes/wcs-renewal-functions.php#L38
	 * @param WC_Order        $renewal_order Renewal order.
	 * @param WC_Subscription $subscription Subscription.
	 * @return WC_Order
	 */ 
	public function wcs_renewal_order_created( $renewal_order, $subscription ) {
		$this->update_order_items_meta( $subscription, $renewal_order );

		return $renewal_order;
	}

	/**
	 * Update order items meta.
	 * 
	 * This function checks the subscription items and looks for a match in the
	 * order items. If a match is found, the order item will be provided with
	 * period information based on the time information from the subscription.
	 * For a new subscription 'date_created' can be used, for a renewal order
	 * 'next_payment' can be used.
	 * 
	 * @param WC_Subscription $subscription Subscription.
	 * @param WC_Order        $order        Order.
	 * @return void
	 */
	private function update_order_items_meta( $subscription, $order, $date_type = 'next_payment' ) {
		foreach ( $subscription->get_items() as $subscription_item ) {
			$subscription_item_product_id = \wcs_get_canonical_product_id( $subscription_item );

			foreach ( $order->get_items() as $order_item ) {
				$order_item_product_id = \wcs_get_canonical_product_id( $order_item );

				if ( $subscription_item_product_id === $order_item_product_id ) {
					$period = $this->get_period( $subscription, $date_type );

					if ( null !== $period ) {
						$order_item->update_meta_data( '_pronamic_start_date', $period->start_date->format( 'Y-m-d H:i:s' ) );
						$order_item->update_meta_data( '_pronamic_end_date', $period->end_date->format( 'Y-m-d H:i:s' ) );
					}
				}
			}
		}

		$order->save();
	}

	/**
	 * Get period.
	 * 
	 * This function makes it possible to request a period for a subscription.
	 * The period can be based on, for example, 'date_created' or the
	 * 'next_payment' time.
	 * 
	 * @param WC_Subscription $subscription Subscription.
	 * @param string          $date_type    Date type.
	 * @return object|null
	 */
	private function get_period( $subscription, $date_type = 'next_payment' ) {
		$timestamp_1 = $subscription->get_time( $date_type );

		if ( 0 === $timestamp_1 ) {
			return null;
		} 

		$timestamp_2 = \wcs_add_time(
			$subscription->get_billing_interval(),
			$subscription->get_billing_period(),
			$timestamp_1
		);

		$start_date = new DateTimeImmutable( '@' . $timestamp_1, new DateTimeZone( 'UTC' ) );
		$end_date   = new DateTimeImmutable( '@' . $timestamp_2, new DateTimeZone( 'UTC' ) );

		return (object) [
			'start_date' => $start_date,
			'end_date'   => $end_date,
		];
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
