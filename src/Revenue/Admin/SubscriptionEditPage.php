<?php
namespace GiveRecurring\Revenue\Admin;

use Give_Subscription;
use GiveRecurring\Infrastructure\View;
use GiveFunds\Repositories\Funds;
use GiveRecurring\Revenue\Repositories\Subscription;

/**
 * Class SubscriptionEditPage
 * @package GiveRecurring\Reveneue\Admin
 *
 * @since 1.11.0
 */
class SubscriptionEditPage {
	/**
	 * @var Funds
	 */
	private $fundsRepository;

	/**
	 * @var Subscription
	 */
	private $subscriptionRepository;

	/**
	 * @param  Funds  $fundsRepository
	 * @param  Subscription  $subscriptionRepository
	 */
	public function __construct( Funds $fundsRepository, Subscription $subscriptionRepository ) {
		$this->fundsRepository        = $fundsRepository;
		$this->subscriptionRepository = $subscriptionRepository;
	}

	/**
	 * Render fund list select field on subscription edit page.
	 *
	 * @since 1.11.0
	 * @param int $subscriptionId
	 */
	public function handle( $subscriptionId ) {
		$subscription = new Give_Subscription( $subscriptionId );
		$funds        = $this->fundsRepository->getFunds();
		$selectedFund = $this->subscriptionRepository->getAssociatedFundId( $subscription->id );

		if ( ! $selectedFund ) {
			$selectedFund = $this->fundsRepository->getDefaultFundId();
		}

		View::render(
			'admin/subscription-select-fund',
			[
				'funds'        => $funds,
				'selectedFund' => $selectedFund
			]
		);
	}

	/**
	 * Update fund id.
	 *
	 * @param int $subscriptionId
	 *
	 * @since 1.11.0
	 */
	public function updateFundId( $subscriptionId ) {
		// Exit if admin is not update subscription details from subscription detail page.
		if ( ! isset( $_POST['give-selected-fund'], $_POST['give_update_subscription'] ) ) {
			return;
		}

		$fundId = (int) $_POST['give-selected-fund'];

		give_recurring()->subscription_meta->update_meta( $subscriptionId, 'fund_id', $fundId );
	}
}
