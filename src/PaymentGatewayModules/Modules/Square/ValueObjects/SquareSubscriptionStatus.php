<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\Square\ValueObjects;

use Give\Framework\Support\ValueObjects\Enum;

/**
 * The overall status of the subscriptions.
 *
 * @see https://developer.squareup.com/reference/square/objects/Subscription#definition__property-status
 *
 * @since 2.3.0
 *
 * @method static SquareInvoiceStatus PENDING()
 * @method static SquareInvoiceStatus ACTIVE()
 * @method static SquareInvoiceStatus CANCELED()
 * @method static SquareInvoiceStatus DEACTIVATED()
 * @method static SquareInvoiceStatus PAUSED()
 * @method bool isPending()
 * @method bool isActive()
 * @method bool isCanceled()
 * @method bool isDeactivated()
 * @method bool isPaused()
 *
 */
class SquareSubscriptionStatus extends Enum
{
    const PENDING = "PENDING";
    const ACTIVE = "ACTIVE";
    const CANCELED = "CANCELED";
    const DEACTIVATED = "DEACTIVATED";
    const PAUSED = "PAUSED";
}
