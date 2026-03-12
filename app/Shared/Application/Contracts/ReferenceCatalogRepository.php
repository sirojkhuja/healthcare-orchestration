<?php

namespace App\Shared\Application\Contracts;

use App\Shared\Application\Data\ReferenceEntryData;

interface ReferenceCatalogRepository
{
    /**
     * @return list<ReferenceEntryData>
     */
    public function list(string $catalog): array;
}
