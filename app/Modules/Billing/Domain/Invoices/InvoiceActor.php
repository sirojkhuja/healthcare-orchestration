<?php

namespace App\Modules\Billing\Domain\Invoices;

final readonly class InvoiceActor
{
    public function __construct(
        public string $type,
        public ?string $id = null,
        public ?string $name = null,
    ) {}

    /**
     * @return array{type: string, id: string|null, name: string|null}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
