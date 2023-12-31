<?php
/**
 * Give Authorize.net Recurring Gateway Integration
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $give_recurring_authorize;

/**
 * Class Give_Recurring_Authorize
 */
class Give_Recurring_Authorize extends Give_Recurring_Gateway {

	/**
	 * API Login ID.
	 *
	 * @var string
	 */
	private $api_login_id;

	/**
	 * Transaction Key.
	 *
	 * @var string
	 */
	private $transaction_key;

	/**
	 * Sandbox mode.
	 *
	 * @var bool
	 */
	private $is_sandbox_mode;

	/**
	 * Whether the live webhooks have been setup.
	 *
	 * @var bool
	 */
	var $live_webhooks_setup;

	/**
	 * Whether the sandbox webhooks have been setup.
	 *
	 * @var bool
	 */
	var $sandbox_webhooks_setup;

	/**
	 * @var bool
	 */
	public $offsite = true;

	/**
	 * Get Authorize started
	 */
	public function init() {

		$this->id                     = 'authorize';
		$this->live_webhooks_setup    = give_get_option( 'give_authorize_live_webhooks_setup' );
		$this->sandbox_webhooks_setup = give_get_option( 'give_authorize_sandbox_webhooks_setup' );

		// Load Authorize SDK and define its constants.
		$this->load_authnetxml_library();
		$this->define_authorize_values();

		// Cancellation support.
		add_action( 'give_recurring_cancel_authorize_subscription', array( $this, 'cancel' ), 10, 2 );

		// Require last name.
		add_filter( 'give_donation_form_before_personal_info', array( $this, 'maybe_require_last_name' ) );

	}


	/**
	 * Loads AuthorizeNet PHP SDK.
	 *
	 * @return void
	 */
	public function load_authnetxml_library() {
		if ( file_exists( GIVE_RECURRING_PLUGIN_DIR . 'includes/gateways/authorize/AuthnetXML/AuthnetXML.class.php' ) ) {
			require_once GIVE_RECURRING_PLUGIN_DIR . 'includes/gateways/authorize/AuthnetXML/AuthnetXML.class.php';
		}
	}

	/**
	 * Set API Login ID, Transaction Key and Mode.
	 *
	 * @return void
	 */
	public function define_authorize_values() {

		// Live keys
		if ( ! give_is_test_mode() ) {
			$this->api_login_id    = give_get_option( 'give_api_login' );
			$this->transaction_key = give_get_option( 'give_transaction_key' );
			$this->is_sandbox_mode = false;
		} else {
			// Sandbox keys
			$this->api_login_id    = give_get_option( 'give_authorize_sandbox_api_login' );
			$this->transaction_key = give_get_option( 'give_authorize_sandbox_transaction_key' );
			$this->is_sandbox_mode = true;
		}

	}

	/**
	 * Check that the necessary credentials are set.
	 *
	 * @since 1.3
	 * @return bool
	 */
	private function check_credentials() {
		// Check credentials
		if (
			empty( $this->api_login_id )
			|| empty( $this->transaction_key )
		) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Validates the form data.
	 *
	 * @param $data
	 * @param $posted
	 */
	public function validate_fields( $data, $posted ) {

		if ( ! class_exists( 'AuthnetXML' ) && ! class_exists( 'Give_Authorize' ) ) {
			give_set_error( 'give_recurring_authorize_missing', __( 'The Authorize.net gateway is not activated.', 'give-recurring' ) );
		}

		if ( empty( $this->api_login_id ) || empty( $this->transaction_key ) ) {
			give_set_error( 'give_recurring_authorize_settings_missing', __( 'The API Login ID or Transaction key is missing.', 'give-recurring' ) );
		}
	}

	/**
	 * Creates subscription payment profiles and sets the IDs so they can be stored.
	 *
     * @since 2.0.0 Set offsite property value to "false".
	 * @return bool true on success and false on failure.
	 */
	public function create_payment_profiles() {

		$subscription = $this->subscriptions;
		$card_info    = $this->purchase_data['card_info'];
		$user_info    = $this->purchase_data['user_info'];

		$response = $this->create_authorize_net_subscription( $subscription, $card_info, $user_info );

		if ( $response->isSuccessful() ) {
            // New subscription status in subscription creation argument will be change to "active" only if one-time transaction was successful.
            // So we do not need to wait for webhook to confirm the subscription and initial donation transaction.
            // Recurring add-on core change donation status to "publish" if $offsite set to "false". Check record_signup().
            // Edited in @since 2.0.0
            if( $this->subscriptions['status'] === 'active' ) {
                $this->offsite = false;
            }

			$sub_id_to_json  = json_encode( $response->subscriptionId );
			$sub_id_to_array = json_decode( $sub_id_to_json, true );

			// Set sub profile ID from Authorize.net response.
			$this->subscriptions['profile_id'] = isset( $sub_id_to_array[0] ) ? $sub_id_to_array[0] : '';

			// If no sub ID mention it in logs.
			if ( empty( $this->subscriptions['profile_id'] ) ) {
				give_record_gateway_error( 'Authorize.net Error', __( 'Could not generate a subscription ID from the API response. Please contact GiveWP support.', 'give-recurring' ) );
			}

			$is_success = true;

		} else {

			give_set_error( 'give_recurring_authorize_error', $response->messages->message->code . ' - ' . $response->messages->message->text );
			give_record_gateway_error( 'Authorize.net Error', sprintf( __( 'Gateway Error %1$s: %2$s', 'give-recurring' ), $response->messages->message->code, $response->messages->message->text ) );

			$is_success = false;

		}

		return $is_success;
	}

	/**
	 * Creates a new Automated Recurring Billing (ARB) subscription.
	 *
	 * @param  array $subscription
	 * @param  array $card_info
	 * @param  array $user_info
	 *
	 * @return AuthnetXML
	 */
	public function create_authorize_net_subscription( $subscription, $card_info, $user_info ) {

		$args = $this->generate_create_subscription_request_args( $subscription, $card_info, $user_info );

		// Use AuthnetXML library to create a new subscription request.
		$authnet_xml = new AuthnetXML( $this->api_login_id, $this->transaction_key, $this->is_sandbox_mode );
		$authnet_xml->ARBCreateSubscriptionRequest( $args );

		return $authnet_xml;
	}

	/**
	 * Generates args for making a ARB create subscription request.
	 *
	 * @param  array $subscription
	 * @param  array $card_info
	 * @param  array $user_info
	 *
	 * @return array
	 */
	public function generate_create_subscription_request_args( $subscription, $card_info, $user_info ) {

		// Set date to same timezone as Authorize's servers (Mountain Time) to prevent conflicts.
		date_default_timezone_set( 'America/Denver' );
		$today = date( 'Y-m-d' );

		// Calculate totalOccurrences.
		$total_occurrences = ( $subscription['bill_times'] == 0 ) ? 9999 : $subscription['bill_times'];

		$address = isset( $user_info['address']['line1'] ) ? $user_info['address']['line1'] : '';
		$address .= isset( $user_info['address']['line2'] ) ? ' ' . $user_info['address']['line2'] : '';

		$name = mb_substr( give_recurring_generate_subscription_name( $subscription['form_id'], $subscription['price_id'] ), 0, 49 );

		$args = array(
			'subscription' => array(
				'name'            => $name,
				'paymentSchedule' => array(
					'interval'         => array(
						'length' => $this->get_interval_count( $subscription['period'], $subscription['frequency'] ),
						'unit'   => $this->get_interval( $subscription['period'], $subscription['frequency'] ),
					),
					'startDate'        => $today,
					'totalOccurrences' => $total_occurrences,
				),
				'amount'          => $subscription['recurring_amount'],
				'payment'         => array(
					'creditCard' => array(
						'cardNumber'     => $card_info['card_number'],
						'expirationDate' => $card_info['card_exp_year'] . '-' . $card_info['card_exp_month'],
						'cardCode'       => $card_info['card_cvc'],
					),
				),
				'order'           => array(
					'invoiceNumber' => substr( $this->purchase_data['purchase_key'], 0, 19 ),
					'description'   => apply_filters( 'give_authorize_recurring_payment_description', give_payment_gateway_donation_summary( $this->purchase_data, false ), $this->purchase_data, $subscription ),
				),
				'customer'        => array(
					'email' => $user_info['email'],
				),
				'billTo'          => array(
					'firstName' => $user_info['first_name'],
					'lastName'  => $user_info['last_name'],
					'address'   => mb_substr( $address, 0, 59 ),
					'city'      => isset( $user_info['address']['city'] ) ? mb_substr( $user_info['address']['city'], 0, 39 ) : '',
					'state'     => isset( $user_info['address']['state'] ) ? $user_info['address']['state'] : '',
					'zip'       => isset( $user_info['address']['zip'] ) ? mb_substr( $user_info['address']['zip'], 0, 19 ) : '',
					'country'   => isset( $user_info['address']['country'] ) ? $user_info['address']['country'] : '',
				),
			),
		);

		return apply_filters( 'give_recurring_authorize_create_subscription_request_args', $args, $this, $subscription, $card_info );

	}

	/**
	 * Gets interval length for Authorize.net based on Give subscription period.
	 *
	 * @param  string $subscription_period
	 * @param  int    $subscription_interval
	 *
	 * @return array
	 */
	public static function get_interval( $subscription_period, $subscription_interval ) {

		$unit = $subscription_period;

		switch ( $subscription_period ) {

			case 'day':
			case 'week':
				$unit   = 'days';
				break;
			case 'quarter':
			case 'month':
			case 'year':
				$unit   = 'months';
				break;
		}

		return $unit;
	}

	/**
	 * Gets interval unit for Authorize.net based on Give subscription period.
	 *
	 * @param  string $subscription_period
	 * @param  int    $subscription_interval
	 *
	 * @since 1.9.0
	 *
	 * @return array
	 */
	public static function get_interval_count( $subscription_period, $subscription_interval ) {

		$length = $subscription_interval;

		switch ( $subscription_period ) {

			case 'week':
				$length = 7 * $subscription_interval;
				break;
			case 'quarter':
				$length = 3 * $subscription_interval;
				break;
			case 'month':
				$length = 1 * $subscription_interval;
				break;
			case 'year':
				$length = 12 * $subscription_interval;
				break;
		}

		return $length;
	}



	/**
	 * Determines if the subscription can be cancelled.
	 *
	 * @param  bool              $ret
	 * @param  Give_Subscription $subscription
	 *
	 * @return bool
	 */
	public function can_cancel( $ret, $subscription ) {

		if (
			$subscription->gateway === $this->id
			&& ! empty( $subscription->profile_id )
			&& 'active' === $subscription->status
			&& $this->check_credentials()
		) {
			$ret = true;
		}

		return $ret;

	}

	/**
	 * Can update subscription CC details.
	 *
	 * @since 1.7
	 *
	 * @param bool   $ret
	 * @param object $subscription
	 *
	 * @return bool
	 */
	public function can_update( $ret, $subscription ) {

		if (
			'authorize' === $subscription->gateway
			&& ! empty( $subscription->profile_id )
			&& in_array( $subscription->status, array(
				'active',
				'failing',
			), true )
		) {
			return true;
		}

		return $ret;
	}

	/**
	 * Can update subscription details.
	 *
	 * @since 1.8
	 *
	 * @param bool   $ret
	 * @param object $subscription
	 *
	 * @return bool
	 */
	public function can_update_subscription( $ret, $subscription ) {

		if (
			'authorize' === $subscription->gateway
			&& ! empty( $subscription->profile_id )
			&& in_array( $subscription->status, array(
				'active',
			), true )
		) {
			return true;
		}

		return $ret;
	}

	/**
	 * @inheritdoc
	 */
	public function update_payment_method( $subscriber, $subscription, $data = null ) {

		if ( $data === null ) {
			$card_info = give_get_donation_cc_info();
		} else {
			$card_info = $data;
		}

		$card_details = $this->generate_card_info( $card_info );
		$values       = array_search( '', $card_details );

		$card_zip = $card_info['card_zip'];

		if ( ! empty( $values ) ) {
			give_set_error( 'give_recurring_authnet', __( 'Please enter all required fields.', 'give-recurring' ) );
		}

		$errors = give_get_errors();

		if ( ! $errors ) {
			// No errors in Authorize.net, continue on through processing
			try {

				$authnet_xml = new AuthnetXML( $this->api_login_id, $this->transaction_key, $this->is_sandbox_mode );

				$args = array(
					'subscriptionId' => $subscription->profile_id,
					'subscription'   => array(
						'payment' => array(
							'creditCard' => $card_details,
						),
						'billTo'  => array(
							'zip' => $card_zip,
						),
					),
				);

				$authnet_xml->ARBUpdateSubscriptionRequest( $args );

				if ( ! $authnet_xml->isSuccessful() ) {

					if ( isset( $authnet_xml->messages->message ) ) {

						give_set_error( 'give_recurring_authorize_error', $authnet_xml->messages->message->code . ': ' . $authnet_xml->messages->message->text, 'give-recurring' );

					} else {

						give_set_error( 'give_recurring_authorize_error', __( 'There was an error updating your payment method.', 'give-recurring' ) );

					}

				}

			} catch ( Exception $e ) {

				give_set_error( 'give_recurring_authnet', $e );

			}
		}

	}

	/**
	 * Given the $card_info array, generate the card info array for use with the API
	 *
	 * @since   1.7
	 *
	 * @param   array $card_info The Card Info from the checkout form
	 *
	 * @return  array            Formatted card info for the Authorize.net API
	 */
	private function generate_card_info( $card_info = array() ) {

		$card_details = array(
			'cardNumber'     => give_clean( str_replace( ' ', '', trim( $card_info['card_number'] ) ) ),
			'expirationDate' => give_clean( $card_info['card_exp_year'] ) . '-' . give_clean( $card_info['card_exp_month'] ),
			'cardCode'       => give_clean( $card_info['card_cvc'] ),
		);

		return $card_details;

	}

	/**
	 * Determines if the subscription can be cancelled.
	 *
	 * @param  bool              $ret
	 * @param  Give_Subscription $subscription
	 *
	 * @return bool
	 */
	public function can_sync( $ret, $subscription ) {

		if (
			$subscription->gateway === $this->id
			&& $this->check_credentials()
			&& ! empty( $subscription->profile_id )
		) {
			$ret = true;
		}

		return $ret;

	}

	/**
	 * Cancels a subscription.
	 *
	 * @param  Give_Subscription $subscription
	 * @param  bool              $valid
	 *
	 * @return bool
	 * @since 1.12.6 rename function from cancel_subscription to cancel
	 */
	public function cancel( $subscription, $valid ) {

		if ( empty( $valid ) ) {
			return false;
		}

		return $this->cancel_authorize_net_subscription( $subscription->profile_id );
	}

	/**
	 * Cancel a ARB subscription based for a given subscription id.
	 *
	 * @param  string $anet_subscription_id
	 *
	 * @return bool
	 */
	public function cancel_authorize_net_subscription( $anet_subscription_id ) {

		// Use AuthnetXML library to create a new subscription request,
		$authnet_xml = new AuthnetXML( $this->api_login_id, $this->transaction_key, $this->is_sandbox_mode );
		$authnet_xml->ARBCancelSubscriptionRequest( array(
			'subscriptionId' => $anet_subscription_id,
		) );

		return $authnet_xml->isSuccessful();
	}

	/**
	 * Require last name if authorize recurring donation.
	 *
	 * @param $form_id
	 *
	 * @return bool
	 */
	public function maybe_require_last_name( $form_id ) {

		$gateway   = isset( $_POST['give_payment_mode'] ) ? $_POST['give_payment_mode'] : '';
		$recurring = give_is_form_recurring( $form_id );

		// On gateway change:
		// If authorize gateway require last name.
		if ( $this->id === $gateway && $recurring ) {
			add_filter( 'give_donation_form_required_fields', array( $this, 'require_last_name' ), 10, 2 );
		}

		// On page load:
		$default_gateway = give_get_default_gateway( $form_id );
		if (
			empty( $gateway ) && $this->id === $default_gateway
			&& give_is_gateway_active( $this->id ) && $recurring
		) {
			add_filter( 'give_donation_form_required_fields', array( $this, 'require_last_name' ), 10, 2 );
		}

	}

	/**
	 * Require Last Name.
	 *
	 * Authorize requires the last name field be completed and passed when creating subscriptions.
	 *
	 * @since 1.2
	 *
	 * @param $required_fields
	 * @param $form_id
	 *
	 * @return mixed
	 */
	function require_last_name( $required_fields, $form_id ) {

		$required_fields['give_last'] = array(
			'error_id'      => 'invalid_last_name',
			'error_message' => __( 'Please enter your last name.', 'give-recurring' ),
		);

		return $required_fields;

	}

	/**
	 * Get gateway subscription.
	 *
	 * @since 1.3
	 *
	 * @see   https://github.com/DevinWalker/Authorize.Net-XML/blob/master/examples/arb/ARBGetSubscriptionStatusRequest.php
	 *
	 * @param $subscription Give_Subscription
	 *
	 * @return bool|mixed
	 */
	public function get_subscription_details( $subscription ) {

		$authnet_xml = new AuthnetXML( $this->api_login_id, $this->transaction_key, $this->is_sandbox_mode );

		$authnet_xml->ARBGetSubscriptionRequest( array(
			'subscriptionId' => $subscription->profile_id,
		) );

		// Check for error.
		if ( 'error' == strtolower( $authnet_xml->messages->resultCode ) ) {
			return false;
		}

		$billing_period = $this->sync_format_billing_period( $authnet_xml );
		$frequency      = $authnet_xml->subscription->paymentSchedule->interval->length->__toString();

		// Get Frequency for the Week.
		if ( 'week' === $billing_period ) {
			$frequency = $frequency / 7;
		}

		$subscription_details = array(
			'status'         => $authnet_xml->subscription->status->__toString(),
			'created'        => strtotime( $authnet_xml->subscription->paymentSchedule->startDate->__toString() ),
			'billing_period' => $billing_period,
			'frequency'      => $frequency,
		);

		return $subscription_details;

	}

	/**
	 * Format the billing period for sync.
	 *
	 * @param $authnet_xml
	 *
	 * @return string
	 */
	public function sync_format_billing_period( $authnet_xml ) {

		$length         = $authnet_xml->subscription->paymentSchedule->interval->length->__toString();
		$unit           = $authnet_xml->subscription->paymentSchedule->interval->unit->__toString();
		$billing_period = '';

		switch ( true ) {
			case ( $length >= 7 && 'days' === $unit ) :
				$billing_period = 'week';
				break;
			case ( $length < 12 && 'months' === $unit ) :
				$billing_period = 'month';
				break;
			case ( $length >= 12 && 'months' === $unit ) :
				$billing_period = 'year';
				break;
		}

		return $billing_period;

	}

	/**
	 * Format the billing period for sync.
	 *
	 * @param $authnet_xml
	 *
	 * @return string
	 */
	public function sync_format_expiration( $authnet_xml ) {

		$expiration = $authnet_xml->subscription->paymentSchedule->startDate->__toString();
		$length     = $authnet_xml->subscription->paymentSchedule->interval->length->__toString();
		$unit       = $authnet_xml->subscription->paymentSchedule->interval->unit->__toString();

		$string = strtotime( $expiration . ' +' . $length . ' ' . $unit );

		return $string;

	}


	/**
	 * Get transactions.
	 *
	 * @see https://community.developer.authorize.net/t5/Integration-and-Testing/Getting-transaction-details/td-p/14198
	 *
	 * @param        $subscription
	 * @param string $date
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_gateway_transactions( $subscription, $date = '' ) {

		$start                 = new DateTime( apply_filters( 'give_authorize_sync_timespan', '12 months ago' ) );
		$end                   = new DateTime();
		$interval              = new DateInterval( 'P1M' );
		$date_range            = new DatePeriod( $start, $interval, $end );
		$subscription_invoices = array();
		$transactions          = array();

		// Loop through the last 12-months.
		foreach ( $date_range as $date ) {

			$period_start     = $date->format( 'Y-m-d\TH:i:s' );
			$period_end       = $date->modify( '+1 month' )->format( 'Y-m-d\TH:i:s' );
			$authnet_invoices = $this->get_invoices_for_give_subscription( $subscription, $period_start, $period_end );

			// Authorize.net reporting Transactions API isn't enabled. Show error.
			if ( isset( $authnet_invoices[0] ) && 'E00011' === $authnet_invoices[0] ) {
				$error_message = sprintf( __( 'Access denied. You do not have permissions to call the Authorize.net Transaction Details API. Please <a href="%s" target="_blank">enable the Transactions Detail API</a>.', 'give-recurring' ), 'https://community.developer.authorize.net/t5/Integration-and-Testing/E00011-Access-denied-You-do-not-have-permission-to-call-the/m-p/28676#M15095' );
				Give_Recurring()->synchronizer->print_notice( $error_message, 'error' );
				break;
			}

			// Grab invoices for each month.
			if ( ! empty( $authnet_invoices ) ) {
				array_push( $subscription_invoices, $authnet_invoices );
			}
		}

		// Bundle all invoices into one array formatted for synchronizer.
		foreach ( $subscription_invoices as $invoice_set ) {

			foreach ( $invoice_set as $invoice ) {

				$transactions[ $invoice['transId'] ] = array(
					'amount'         => give_sanitize_amount( $invoice['settleAmount'] ),
					'date'           => strtotime( $invoice['submitTimeLocal'] ),
					'transaction_id' => $invoice['transId'],
				);

			}
		}

		// Return transactions in reverse order in order because they are by default returning newest first.
		// When multiple are synced the sequential IDs need to properly be ordered. This fixes that.
		return array_reverse( $transactions );

	}

	/**
	 * Get invoices for Authorize.net subscription.
	 *
	 * @param $subscription
	 * @param $start_date
	 * @param $end_date
	 *
	 * @return array|bool
	 */
	public function get_invoices_for_give_subscription( $subscription, $start_date, $end_date ) {

		$auth = new AuthnetXML( $this->api_login_id, $this->transaction_key, $this->is_sandbox_mode );

		// Get batches from Authorize.net.
		$args = array(
			'includeStatistics'   => true,
			'firstSettlementDate' => $start_date,
			'lastSettlementDate'  => $end_date,
		);
		$auth->getSettledBatchListRequest( $args );

		// Check for error.
		if ( 'error' == strtolower( $auth->messages->resultCode ) ) {
			return json_decode( json_encode( (array) $auth->messages->message->code ), true );
		}

		// Create PHP array out of SimpleXML.
		$batches = json_decode( json_encode( (array) $auth->batchList ), true );

		// Need batch to continue.
		if (
			! isset( $batches['batch'] )
			|| ! is_array( $batches['batch'] )
		) {
			return false;
		}

		// Some batches come in in single array without iterator.
		// Here we prepare those batches for our loop below.
		if ( ! isset( $batches['batch'][0] ) ) {
			$batches['batch'] = array( $batches['batch'] );
		}

		$transactions = array();

		// Loop through this batch and pick out subscription's transactions.
		foreach ( $batches['batch'] as $batch ) {

			// Need to get transactions for this specific batch.
			$auth2 = new AuthnetXML( $this->api_login_id, $this->transaction_key, $this->is_sandbox_mode );
			$auth2->getTransactionListRequest( array(
				'batchId' => $batch['batchId'],
			) );
			$batch_transactions = json_decode( json_encode( (array) $auth2->transactions ), true );

			// Is this a multi-dimensional array of transactions?
			if (
				isset( $batch_transactions['transaction'][0] )
				&& is_array( $batch_transactions['transaction'][0] )
			) {
				// Loop through transactions in batch and check if any are for this subscription.
				foreach ( $batch_transactions['transaction'] as $transaction ) {

					$transactions = $this->setup_batch_transaction_array( $transaction, $transactions, $subscription );

				}
			} else {

				$transactions = $this->setup_batch_transaction_array( $batch_transactions['transaction'], $transactions, $subscription );

			}
		} // End foreach().

		return $transactions;
	}

	/**
	 * Setup batch transactions.
	 *
	 * This function checks conditionally if a transaction is part of the subscription.
	 * If it is then it is added to the subscription transactions' array.
	 *
	 * @param $transaction
	 * @param $transactions
	 * @param $subscription
	 *
	 * @return array
	 */
	function setup_batch_transaction_array( $transaction, $transactions, $subscription ) {

		/**
		 * Add transaction to array if:
		 *
		 * a: There is a subscription ID.
		 * b: If subscription IDs match.
		 * c: This isn't the first payment.
		 * d: This is a successful payment aka "settledSuccessfully".
		 */
		if (
			isset( $transaction['subscription']['id'] )
			&& $transaction['subscription']['id'] == $subscription->profile_id
			&& $transaction['subscription']['payNum'] !== '1'
			&& $transaction['transactionStatus'] === 'settledSuccessfully'
		) {
			$transactions[ $transaction['transId'] ] = $transaction;
		}

		return $transactions;

	}

	/**
	 * Get gateway subscription CC details.
	 *
	 * @since 1.7
	 *
	 * @param object $subscription Give_Subscription
	 *
	 * @return bool|mixed
	 */
	public function get_subscription_cc_details( $subscription ) {

		$authnet_xml = new AuthnetXML( $this->api_login_id, $this->transaction_key, $this->is_sandbox_mode );
		$authnet_xml->ARBGetSubscriptionRequest( array(
			'subscriptionId' => $subscription->profile_id,
		) );

		// Check for error.
		if ( 'error' === strtolower( $authnet_xml->messages->resultCode ) ) {
			return false;
		}

		$auth = new JohnConde\Authnet\AuthnetApiFactory;

		$request = $auth->getJsonApiHandler( $this->api_login_id, $this->transaction_key, (int) $this->is_sandbox_mode );

		$response = $request->getCustomerPaymentProfileRequest( array(
			'customerProfileId'        => $authnet_xml->subscription->profile->customerProfileId->__toString(),
			'customerPaymentProfileId' => $authnet_xml->subscription->profile->paymentProfile->customerPaymentProfileId->__toString(),
			'unmaskExpirationDate'     => true,
		) );

		$subscription_details = array(
			'cardNumber'     => $response->paymentProfile->payment->creditCard->cardNumber,
			'expirationDate' => $response->paymentProfile->payment->creditCard->expirationDate,
			'cardType'       => $response->paymentProfile->payment->creditCard->cardType,
		);

		return $subscription_details;

	}

	/**
	 * @inheritdoc
	 */
	public function update_subscription( $subscriber, $subscription, $data = null ) {

		if ( $data === null ) {
			// Sanitize the values submitted with donation form.
			$post_data = give_clean( $_POST ); // WPCS: input var ok, sanitization ok, CSRF ok.
		} else {
			$post_data = $data;
		}

		// Get update renewal amount.
		$renewal_amount           = isset( $post_data['give-amount'] ) ? give_maybe_sanitize_amount( $post_data['give-amount'] ) : 0;
		$current_recurring_amount = give_maybe_sanitize_amount( $subscription->recurring_amount );
		$check_amount             = number_format( $renewal_amount, 0 );

		// Set error if renewal amount not valid.
		if (
			empty( $check_amount )
			|| $renewal_amount === $current_recurring_amount
		) {
			give_set_error( 'give_recurring_invalid_subscription_amount', __( 'Please enter the valid subscription amount.', 'give-recurring' ) );
		}

		// Is errors?
		$errors = give_get_errors();

		if ( ! $errors ) {
			// No errors in Authorize.net, continue on through processing.
			try {

				$authnet_xml = new AuthnetXML( $this->api_login_id, $this->transaction_key, $this->is_sandbox_mode );

				$args = array(
					'subscriptionId' => $subscription->profile_id,
					'subscription'   => array(
						'amount' => $renewal_amount,
					),
				);

				$authnet_xml->ARBUpdateSubscriptionRequest( $args );

				if ( ! $authnet_xml->isSuccessful() ) {

					if ( isset( $authnet_xml->messages->message ) ) {

						give_set_error( 'give_recurring_authorize_error', $authnet_xml->messages->message->code . ': ' . $authnet_xml->messages->message->text, 'give-recurring' );

					} else {

						give_set_error( 'give_recurring_authorize_error', __( 'There was an error updating your subscription.', 'give-recurring' ) );

					}

				}

			} catch ( Exception $e ) {

				give_set_error( 'give_recurring_authnet', $e );

			}
		}

	}

}


$give_recurring_authorize = new Give_Recurring_Authorize();
