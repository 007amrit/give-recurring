<?php
namespace GiveRecurring\Donation\Migrations;

use Give\Framework\Database\DB;
use Give_Donor;
use Give_Payment;
use Give_Updates;
use GiveRecurring\Infrastructure\Migration;

/**
 * @since 1.12.7
 */
class RecoverDonorFirstAndLastNameAffectByRenewal extends Migration {
	/**
	 * @since 1.12.7
	 *
	 * @param Give_Updates $giveUpdates
	 */
	public function register( $giveUpdates ) {
		$giveUpdates->register(
			[
				'id'       => $this->id(),
				'version'  => '1.13.0',
				'callback' => [ $this, 'run' ]
			]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function run() {
		global $wpdb;

		$giveUpdates = Give_Updates::get_instance();
		$perPage     = 20;

		$donors = DB::get_results(
			DB::prepare(
				"
				SELECT donor_meta.donor_id as id, donor.payment_ids as donations
				FROM $wpdb->donormeta as donor_meta
				INNER JOIN $wpdb->donors  as donor ON donor_meta.donor_id=donor.id
				WHERE donor_meta.meta_key = '_give_donor_first_name'
				AND donor_meta.meta_value=''
				AND donor.payment_ids!=''
				ORDER BY donor.id DESC
				LIMIT %d
				OFFSET %d",
				$perPage,
				$giveUpdates->get_offset( $perPage )
			)
		);

		$totalDonors = ( new \Give_Donors_Query( [ 'number' => 1, 'count' => true ] ) )->get_donors();
		$giveUpdates->set_percentage( $totalDonors, ( $giveUpdates->step * $perPage ) );

		if ( $donors ) {
			foreach ( $donors as $donor ) {
				$donationIds = array_filter( explode( ',', $donor->donations ) );

				foreach ( $donationIds as $donationId ) {
					$donationId = (int) trim( $donationId );
					$donation   = new Give_Payment( $donationId );

					if ( ! $donation->ID ) {
						continue;
					}

					$donor = new Give_Donor( $donation->donor_id );
					$donor->update_meta( '_give_donor_first_name', $donation->first_name );
					$donor->update_meta( '_give_donor_last_name', $donation->last_name );
				}
			}

			return;
		}

		give_set_upgrade_complete( $this->id() );
	}

	/**
	 * @since 1.12.7
	 * @return string
	 */
	public static function id() {
		return 'recurring-donations-recover-donor-first-and-last-name-affect-by-renewal';
	}

	/**
	 * @since 1.12.7
	 * @return string
	 */
	public static function title() {
		return esc_html__( 'Recover Donor First And Last Name Affect By Renewal', 'give-recurring' );
	}

	/**
	 * @since 1.12.7
	 * @return false|int
	 */
	public static function timestamp() {
		return strtotime( '2021-09-23' );
	}
}
