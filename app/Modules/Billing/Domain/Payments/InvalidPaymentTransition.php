<?php

namespace App\Modules\Billing\Domain\Payments;

use DomainException;

final class InvalidPaymentTransition extends DomainException {}
