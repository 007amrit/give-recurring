<?php

namespace GiveRecurring\PaymentGatewayModules\Modules\Square\ValueObjects;

use Give\Framework\Support\ValueObjects\Enum;

/**
 * The overall status of the invoices.
 *
 * @see https://developer.squareup.com/reference/square/objects/Invoice#definition__property-status
 *
 * @since 2.3.0
 *
 * @method static SquareInvoiceStatus DRAFT()
 * @method static SquareInvoiceStatus UNPAID()
 * @method static SquareInvoiceStatus SCHEDULED()
 * @method static SquareInvoiceStatus PARTIALLY_PAID()
 * @method static SquareInvoiceStatus PAID()
 * @method static SquareInvoiceStatus PARTIALLY_REFUNDED()
 * @method static SquareInvoiceStatus REFUNDED()
 * @method static SquareInvoiceStatus CANCELED()
 * @method static SquareInvoiceStatus FAILED()
 * @method static SquareInvoiceStatus PAYMENT_PENDING()
 * @method bool isDraft()
 * @method bool isUnpaid()
 * @method bool isScheduled()
 * @method bool isPartiallyPaid()
 * @method bool isPaid()
 * @method bool isPartiallyRefunded()
 * @method bool isRefunded()
 * @method bool isCanceled()
 * @method bool isFailed()
 * @method bool isPaymentPending()
 */
class SquareInvoiceStatus extends Enum
{
    const DRAFT = "DRAFT";
    const UNPAID = "UNPAID";
    const SCHEDULED = "SCHEDULED";
    const PARTIALLY_PAID = "PARTIALLY_PAID";
    const PAID = "PAID";
    const PARTIALLY_REFUNDED = "PARTIALLY_REFUNDED";
    const REFUNDED = "REFUNDED";
    const CANCELED = "CANCELED";
    const FAILED = "FAILED";
    const PAYMENT_PENDING = "PAYMENT_PENDING";
}
