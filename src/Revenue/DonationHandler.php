<?php

namespace GiveRecurring\Revenue;

use Give\Revenue\DonationHandler as GiveDonationHandler;
use Give\Revenue\Repositories\Revenue;
use Give_Payment;


/**
 * Class DonationHandler
 * @package GiveRecurring\Revenue
 *
 * @since 1.11.0
 */
class DonationHandler {
	/**
	 * @var Revenue
	 */
	private $revenueRepository;

	/**
	 * @var GiveDonationHandler
	 */
	private $donationHandler;

	public function __construct(
		Revenue $revenue,
		GiveDonationHandler $handler
	) {
		$this->revenueRepository = $revenue;
		$this->donationHandler   = $handler;
	}

	/**
	 * Handle new renewal
	 *
	 * @param  Give_Payment  $payment
	 */
	public function handle( $payment ) {
		$this->revenueRepository->insert(
			$this->donationHandler->getData( $payment->ID )
		);
	}
}
