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
}
