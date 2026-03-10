<?php

namespace App\Modules\Billing\Application\Commands;

final readonly class DeleteBillableServiceCommand
{
    public function __construct(public string $serviceId) {}
}
