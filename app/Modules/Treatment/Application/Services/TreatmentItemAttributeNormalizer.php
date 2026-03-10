<?php

namespace App\Modules\Treatment\Application\Services;

use App\Modules\Treatment\Application\Data\TreatmentItemData;
use App\Modules\Treatment\Domain\TreatmentPlans\TreatmentItemType;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class TreatmentItemAttributeNormalizer
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     item_type: string,
     *     title: string,
     *     description: ?string,
     *     instructions: ?string,
     *     sort_order: ?int
     * }
     */
    public function normalizeCreate(array $attributes): array
    {
        return [
            'item_type' => $this->requiredType($attributes['item_type'] ?? null),
            'title' => $this->requiredTrimmedString($attributes['title'] ?? null, 'The title field is required.'),
            'description' => $this->nullableTrimmedString($attributes['description'] ?? null),
            'instructions' => $this->nullableTrimmedString($attributes['instructions'] ?? null),
            'sort_order' => array_key_exists('sort_order', $attributes)
                ? $this->nullablePositiveInteger($attributes['sort_order'], 'The sort_order field must be a positive integer.')
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     item_type?: string,
     *     title?: string,
     *     description?: ?string,
     *     instructions?: ?string,
     *     sort_order?: int
     * }
     */
    public function normalizePatch(TreatmentItemData $item, array $attributes): array
    {
        $updates = [];

        if (array_key_exists('item_type', $attributes)) {
            $itemType = $this->requiredType($attributes['item_type']);

            if ($itemType !== $item->itemType) {
                $updates['item_type'] = $itemType;
            }
        }

        if (array_key_exists('title', $attributes)) {
            $title = $this->requiredTrimmedString($attributes['title'], 'The title field is required.');

            if ($title !== $item->title) {
                $updates['title'] = $title;
            }
        }

        if (array_key_exists('description', $attributes)) {
            $description = $this->nullableTrimmedString($attributes['description']);

            if ($description !== $item->description) {
                $updates['description'] = $description;
            }
        }

        if (array_key_exists('instructions', $attributes)) {
            $instructions = $this->nullableTrimmedString($attributes['instructions']);

            if ($instructions !== $item->instructions) {
                $updates['instructions'] = $instructions;
            }
        }

        if (array_key_exists('sort_order', $attributes)) {
            $sortOrder = $this->nullablePositiveInteger($attributes['sort_order'], 'The sort_order field must be a positive integer.');

            if ($sortOrder === null) {
                throw new UnprocessableEntityHttpException('The sort_order field must be a positive integer.');
            }

            if ($sortOrder !== $item->sortOrder) {
                $updates['sort_order'] = $sortOrder;
            }
        }

        return $updates;
    }

    private function nullablePositiveInteger(mixed $value, string $message): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw new UnprocessableEntityHttpException($message);
        }

        $normalized = (int) $value;

        if ($normalized < 1 || (string) $normalized !== trim((string) $value)) {
            throw new UnprocessableEntityHttpException($message);
        }

        return $normalized;
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function requiredTrimmedString(mixed $value, string $message): string
    {
        return $this->nullableTrimmedString($value) ?? throw new UnprocessableEntityHttpException($message);
    }

    private function requiredType(mixed $value): string
    {
        $normalized = $this->nullableTrimmedString($value);

        if ($normalized === null || ! in_array($normalized, TreatmentItemType::all(), true)) {
            throw new UnprocessableEntityHttpException('The item_type field must be a supported treatment item type.');
        }

        return $normalized;
    }
}
