<?php
/**
 * Period
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2026 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\WooSubscriptionsPeriod
 */

declare(strict_types=1);

namespace Pronamic\WooSubscriptionsPeriod;

use DateTimeImmutable;
use DateTimeZone;
use WC_Order_Item;

/**
 * Period class
 */
final readonly class Period {
	/**
	 * Construct period.
	 *
	 * @param DateTimeImmutable $start_date Start date.
	 * @param DateTimeImmutable $end_date   End date.
	 */
	public function __construct(
		public DateTimeImmutable $start_date,
		public DateTimeImmutable $end_date
	) {
	}

	/**
	 * Retrieve period from WooCommerce order item.
	 *
	 * @param WC_Order_Item $item WooCommerce order item.
	 * @return self|null
	 */
	public static function from_woocommerce_order_item( WC_Order_Item $item ): ?self {
		$start_date_string = $item->get_meta( '_pronamic_start_date' );
		$end_date_string   = $item->get_meta( '_pronamic_end_date' );

		if ( ! \is_string( $start_date_string ) || '' === $start_date_string ) {
			return null;
		}

		if ( ! \is_string( $end_date_string ) || '' === $end_date_string ) {
			return null;
		}

		try {
			return new self(
				new DateTimeImmutable( $start_date_string, new DateTimeZone( 'UTC' ) ),
				new DateTimeImmutable( $end_date_string, new DateTimeZone( 'UTC' ) )
			);
		} catch ( \Exception ) {
			return null;
		}
	}
}
