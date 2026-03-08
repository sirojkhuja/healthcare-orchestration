<?php

namespace App\Shared\Infrastructure\Persistence\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasUuidPrimaryKey
{
    #[\Override]
    public function getIncrementing(): bool
    {
        return false;
    }

    #[\Override]
    public function getKeyType(): string
    {
        return 'string';
    }

    protected static function bootHasUuidPrimaryKey(): void
    {
        static::creating(function (Model $model): void {
            $keyName = $model->getKeyName();
            /** @var mixed $currentKey */
            $currentKey = $model->getAttribute($keyName);

            if (is_string($currentKey) && $currentKey !== '') {
                return;
            }

            $model->setAttribute($keyName, Str::uuid()->toString());
        });
    }
}
