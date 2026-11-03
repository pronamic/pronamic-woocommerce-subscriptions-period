<?php
/**
 * Plugin
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\WooCommerceSubscriptionsPeriod
 */

declare(strict_types=1);

namespace Pronamic\WooCommerceSubscriptionsPeriod;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use DateTimeImmutable;
use DateTimeZone;
use WC_Order;
use WC_Order_Item;
use WC_Subscription;
use WC_Subscriptions;

/**
 * Plugin class
 */
final class Plugin {
	/**
	 * Instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Return instance of this class.
	 *
	 * @param string $plugin_file The plugin file.
	 * @return self A single instance of this class.
	 */
	public static function instance( string $plugin_file ): self {
		self::$instance ??= new self( $plugin_file );

		return self::$instance;
	}

	/**
	 * Construct.
	 *
	 * @param string $plugin_file The plugin file.
	 */
	private function __construct(
		private readonly string $plugin_file
	) {
		\add_action( 'plugins_loaded', $this->plugins_loaded( ... ) );
		\add_action( 'before_woocommerce_init', $this->before_woocommerce_init( ... ) );
	}

	/**
	 * Plugins loaded.
	 *
	 * @return void
	 */
	private function plugins_loaded(): void {
		if ( ! \class_exists( WC_Subscriptions::class ) ) {
			return;
		}

		\add_action( 'init', $this->init( ... ) );
	}

	/**
	 * Declare compatibility with High-Performance Order Storage (HPOS).
	 *
	 * @link https://github.com/woocommerce/woocommerce/wiki/High-Performance-Order-Storage-Upgrade-Recipe-Book#declaring-extension-incompatibility
	 * @return void
	 */
	private function before_woocommerce_init(): void {
		if ( \class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', $this->plugin_file, true );
		}
	}

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	private function init(): void {
		\add_filter( 'woocommerce_add_cart_item_data', $this->woocommerce_add_cart_item_data( ... ), 30, 1 );
		\add_filter( 'woocommerce_get_item_data', $this->woocommerce_get_item_data( ... ), 10, 2 );
		\add_action( 'woocommerce_checkout_create_subscription', $this->woocommerce_checkout_create_subscription( ... ), 10, 3 );
		\add_action( 'woocommerce_checkout_create_order_line_item', $this->woocommerce_checkout_create_order_line_item( ... ), 20, 3 );
		\add_filter( 'woocommerce_order_item_display_meta_key', $this->woocommerce_order_item_display_meta_key( ... ), 10, 2 );
		\add_filter( 'woocommerce_order_item_display_meta_value', $this->woocommerce_order_item_display_meta_value( ... ), 10, 2 );
		\add_filter( 'wcs_renewal_order_created', $this->wcs_renewal_order_created( ... ), 10, 2 );
	}

	/**
	 * WooCommerce add cart item data.
	 *
	 * Called as soon as a WooCommerce product is added to the cart. In the
	 * case of a subscription renewal we add the period information to the
	 * cart item.
	 *
	 * @link https://github.com/woocommerce/woocommerce/blob/7.7.0/plugins/woocommerce/includes/class-wc-cart.php#L1137-L1138
	 * @param array<string, mixed> $cart_item_data WooCommerce cart item data.
	 * @return array<string, mixed>
	 */
	private function woocommerce_add_cart_item_data( $cart_item_data ) {
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

		$subscription = \wcs_get_subscription( $subscription_renewal_data['subscription_id'] );

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
	 * Called to get a list of cart item data + variations for display on the frontend.
	 *
	 * @link https://github.com/woocommerce/woocommerce/blob/7.7.0/plugins/woocommerce/includes/wc-template-functions.php#L3774-L3775
	 * @param array<int, array{key: string, value: string}> $item_data Item data to display.
	 * @param array<string, mixed>                          $cart_item Cart item.
	 * @return array<int, array{key: string, value: string}>
	 */
	private function woocommerce_get_item_data( $item_data, $cart_item ) {
		if ( \array_key_exists( '_pronamic_start_date', $cart_item ) && \is_string( $cart_item['_pronamic_start_date'] ) ) {
			$item_data[] = [
				'key'   => \__( 'Start date', 'pronamic-woocommerce-subscriptions-period' ),
				'value' => $this->format_date( $cart_item['_pronamic_start_date'] ),
			];
		}

		if ( \array_key_exists( '_pronamic_end_date', $cart_item ) && \is_string( $cart_item['_pronamic_end_date'] ) ) {
			$item_data[] = [
				'key'   => \__( 'End date', 'pronamic-woocommerce-subscriptions-period' ),
				'value' => $this->format_date( $cart_item['_pronamic_end_date'] ),
			];
		}

		return $item_data;
	}

	/**
	 * WooCommerce checkout create order line item.
	 *
	 * Called when order items are created from the cart. Period information
	 * from the cart item is transferred to the order item.
	 *
	 * @link https://github.com/woocommerce/woocommerce/blob/7.7.0/plugins/woocommerce/includes/class-wc-checkout.php#L544-L549
	 * @param WC_Order_Item        $item          WooCommerce order item.
	 * @param string               $cart_item_key Cart item key.
	 * @param array<string, mixed> $values        Cart item values.
	 * @return void
	 */
	private function woocommerce_checkout_create_order_line_item( $item, $cart_item_key, $values ): void {
		if ( \array_key_exists( '_pronamic_start_date', $values ) && \is_string( $values['_pronamic_start_date'] ) ) {
			$item->update_meta_data( '_pronamic_start_date', $values['_pronamic_start_date'] );
		}

		if ( \array_key_exists( '_pronamic_end_date', $values ) && \is_string( $values['_pronamic_end_date'] ) ) {
			$item->update_meta_data( '_pronamic_end_date', $values['_pronamic_end_date'] );
		}
	}

	/**
	 * WooCommerce order item display meta key.
	 *
	 * @link https://github.com/woocommerce/woocommerce/blob/7.7.0/plugins/woocommerce/includes/class-wc-order-item.php#L301
	 * @param string              $display_key Display key.
	 * @param object{key: string} $meta        Meta object.
	 * @return string
	 */
	private function woocommerce_order_item_display_meta_key( $display_key, $meta ) {
		return match ( $meta->key ) {
			'_pronamic_start_date' => \__( 'Start date', 'pronamic-woocommerce-subscriptions-period' ),
			'_pronamic_end_date'   => \__( 'End date', 'pronamic-woocommerce-subscriptions-period' ),
			default                => $display_key,
		};
	}

	/**
	 * WooCommerce order item display meta value.
	 *
	 * @link https://github.com/woocommerce/woocommerce/blob/7.7.0/plugins/woocommerce/includes/class-wc-order-item.php#L302
	 * @param string                            $display_value Display value.
	 * @param object{key: string, value: mixed} $meta          Meta object.
	 * @return string
	 */
	private function woocommerce_order_item_display_meta_value( $display_value, $meta ) {
		if ( ! \in_array( $meta->key, [ '_pronamic_start_date', '_pronamic_end_date' ], true ) ) {
			return $display_value;
		}

		if ( ! \is_string( $meta->value ) ) {
			return $display_value;
		}

		return $this->format_date( $meta->value );
	}

	/**
	 * WooCommerce checkout create subscription.
	 *
	 * Triggered when a subscription is created for an order in the checkout
	 * process, from `WC_Subscriptions_Checkout::process_checkout()`. We use it
	 * to add period information to the order items.
	 *
	 * @link https://github.com/Automattic/woocommerce-subscriptions-core/blob/7.1.1/includes/class-wc-subscriptions-checkout.php#L246-L247
	 * @param WC_Subscription      $subscription Subscription.
	 * @param array<string, mixed> $posted_data  Posted data.
	 * @param WC_Order             $order        Order.
	 * @return void
	 */
	private function woocommerce_checkout_create_subscription( $subscription, $posted_data, $order ): void {
		$this->update_order_items_meta( $subscription, $order, 'date_created' );
	}

	/**
	 * WooCommerce Subscriptions renewal order created.
	 *
	 * Called when a renewal order is created, for example by the Action
	 * Scheduler on the next payment date. We use it to add period
	 * information to the order items.
	 *
	 * @link https://github.com/Automattic/woocommerce-subscriptions-core/blob/7.1.1/includes/wcs-renewal-functions.php#L38
	 * @param WC_Order        $renewal_order Renewal order.
	 * @param WC_Subscription $subscription  Subscription.
	 * @return WC_Order
	 */
	private function wcs_renewal_order_created( $renewal_order, $subscription ) {
		$this->update_order_items_meta( $subscription, $renewal_order );

		return $renewal_order;
	}

	/**
	 * Update order items meta.
	 *
	 * Matches subscription items with order items and adds period
	 * information to the matching order items. For a new subscription
	 * 'date_created' can be used, for a renewal order 'next_payment'.
	 *
	 * @param WC_Subscription $subscription Subscription.
	 * @param WC_Order        $order        Order.
	 * @param string          $date_type    Date type.
	 * @return void
	 */
	private function update_order_items_meta( WC_Subscription $subscription, WC_Order $order, string $date_type = 'next_payment' ): void {
		foreach ( $subscription->get_items() as $subscription_item ) {
			$subscription_item_product_id = \wcs_get_canonical_product_id( $subscription_item );

			foreach ( $order->get_items() as $order_item ) {
				if ( \wcs_get_canonical_product_id( $order_item ) !== $subscription_item_product_id ) {
					continue;
				}

				$period = $this->get_period( $subscription, $date_type );

				if ( null === $period ) {
					continue;
				}

				$order_item->update_meta_data( '_pronamic_start_date', $period->start_date->format( 'Y-m-d H:i:s' ) );
				$order_item->update_meta_data( '_pronamic_end_date', $period->end_date->format( 'Y-m-d H:i:s' ) );
			}
		}

		$order->save();
	}

	/**
	 * Get period.
	 *
	 * @param WC_Subscription $subscription Subscription.
	 * @param string          $date_type    Date type, for example 'date_created' or 'next_payment'.
	 * @return Period|null
	 */
	private function get_period( WC_Subscription $subscription, string $date_type = 'next_payment' ): ?Period {
		$start_timestamp = $subscription->get_time( $date_type );

		if ( ! \is_int( $start_timestamp ) || 0 === $start_timestamp ) {
			return null;
		}

		$end_timestamp = \wcs_add_time(
			(int) $subscription->get_billing_interval(),
			$subscription->get_billing_period(),
			$start_timestamp
		);

		if ( ! \is_int( $end_timestamp ) ) {
			return null;
		}

		return new Period(
			new DateTimeImmutable( '@' . $start_timestamp, new DateTimeZone( 'UTC' ) ),
			new DateTimeImmutable( '@' . $end_timestamp, new DateTimeZone( 'UTC' ) )
		);
	}

	/**
	 * Format a stored UTC date string with the site date format.
	 *
	 * @param string $date_string Date string in UTC.
	 * @return string
	 */
	private function format_date( string $date_string ): string {
		$date = new DateTimeImmutable( $date_string, new DateTimeZone( 'UTC' ) );

		$format = \get_option( 'date_format' );

		if ( ! \is_string( $format ) || '' === $format ) {
			$format = 'Y-m-d';
		}

		return (string) \wp_date( $format, $date->getTimestamp() );
	}
}
