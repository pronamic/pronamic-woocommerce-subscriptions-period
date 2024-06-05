<?php
/**
 * Plugin
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2024 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\WooSubscriptionsPeriod
 */

namespace Pronamic\WooSubscriptionsPeriod;

use DateTimeImmutable;
use DateTimeZone;
use WC_Order_Item;

/**
 * Plugin class
 */
final class Period {
	/**
	 * Start date.
	 * 
	 * @var DateTimeImmutable
	 */
	public $start_date;

	/**
	 * End date.
	 * 
	 * @var DateTimeImmutable
	 */
	public $end_date;

	/**
	 * Construct period.
	 * 
	 * @param DateTimeImmutable $start_date Start date.
	 * @param DateTimeImmutable $end_date   End date.
	 */
	public function __construct( DateTimeImmutable $start_date, DateTimeImmutable $end_date ) {
		$this->start_date = $start_date;
		$this->end_date   = $end_date;
	}

	/**
	 * Retrieve period from WooCommerce order item.
	 * 
	 * @param WC_Order_Item $item WooCommerce order item.
	 * @return Period|null
	 */
	public static function from_woocommerce_order_item( WC_Order_Item $item ) {
		$start_date_string = $item->get_meta( '_pronamic_start_date' );

		if ( ! \is_string( $start_date_string ) ) {
			return null;
		}

		if ( '' === $start_date_string ) {
			return null;
		}

		$end_date_string = $item->get_meta( '_pronamic_end_date' );

		if ( ! \is_string( $end_date_string ) ) {
			return null;
		}

		if ( '' === $end_date_string ) {
			return null;
		}

		try {
			$start_date = new DateTimeImmutable( $start_date_string, new DateTimeZone( 'UTC' ) );
			$end_date   = new DateTimeImmutable( $end_date_string, new DateTimeZone( 'UTC' ) );

			return new self( $start_date, $end_date );
		} catch ( \Exception $e ) {
			return null;
		}
	}
}
