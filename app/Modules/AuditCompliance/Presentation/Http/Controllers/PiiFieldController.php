<?php

namespace App\Modules\AuditCompliance\Presentation\Http\Controllers;

use App\Modules\AuditCompliance\Application\Commands\ReEncryptPiiCommand;
use App\Modules\AuditCompliance\Application\Commands\RotatePiiKeysCommand;
use App\Modules\AuditCompliance\Application\Commands\SetPiiFieldsCommand;
use App\Modules\AuditCompliance\Application\Data\PiiFieldData;
use App\Modules\AuditCompliance\Application\Data\PiiFieldMutationData;
use App\Modules\AuditCompliance\Application\Handlers\ListPiiFieldsQueryHandler;
use App\Modules\AuditCompliance\Application\Handlers\ReEncryptPiiCommandHandler;
use App\Modules\AuditCompliance\Application\Handlers\RotatePiiKeysCommandHandler;
use App\Modules\AuditCompliance\Application\Handlers\SetPiiFieldsCommandHandler;
use App\Modules\AuditCompliance\Application\Queries\ListPiiFieldsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PiiFieldController
{
    public function list(ListPiiFieldsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn (PiiFieldData $field): array => $field->toArray(),
                $handler->handle(new ListPiiFieldsQuery),
            ),
        ]);
    }

    public function reEncrypt(Request $request, ReEncryptPiiCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'field_ids' => ['nullable', 'array'],
            'field_ids.*' => ['uuid', 'distinct'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'status' => 'pii_reencryption_completed',
            'data' => $handler->handle(new ReEncryptPiiCommand($this->fieldIds($validated)))->toArray(),
        ]);
    }

    public function rotateKeys(Request $request, RotatePiiKeysCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'field_ids' => ['nullable', 'array'],
            'field_ids.*' => ['uuid', 'distinct'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'status' => 'pii_key_rotation_completed',
            'data' => $handler->handle(new RotatePiiKeysCommand($this->fieldIds($validated)))->toArray(),
        ], 201);
    }

    public function update(Request $request, SetPiiFieldsCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'fields' => ['required', 'array'],
            'fields.*.object_type' => ['required', 'string', 'max:64'],
            'fields.*.field_path' => ['required', 'string', 'max:191'],
            'fields.*.classification' => ['required', 'string', 'in:identity,contact,government_id,clinical,financial,biometric,other'],
            'fields.*.encryption_profile' => ['required', 'string', 'in:encrypted_string,encrypted_json'],
            'fields.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);
        /** @var array<string, mixed> $validated */
        $fieldRows = is_array($validated['fields']) ? array_values($validated['fields']) : [];
        $fields = $this->mutationFields($fieldRows);

        return response()->json([
            'status' => 'pii_fields_updated',
            'data' => array_map(
                static fn (PiiFieldData $field): array => $field->toArray(),
                $handler->handle(new SetPiiFieldsCommand($fields)),
            ),
        ]);
    }

    /**
     * @param  array<int, mixed>  $fieldRows
     * @return list<PiiFieldMutationData>
     */
    private function mutationFields(array $fieldRows): array
    {
        $fields = [];

        foreach ($fieldRows as $fieldRow) {
            if (! is_array($fieldRow)) {
                continue;
            }

            $fields[] = new PiiFieldMutationData(
                objectType: $this->requiredString($fieldRow, 'object_type'),
                fieldPath: $this->requiredString($fieldRow, 'field_path'),
                classification: $this->requiredString($fieldRow, 'classification'),
                encryptionProfile: $this->requiredString($fieldRow, 'encryption_profile'),
                notes: array_key_exists('notes', $fieldRow) && is_string($fieldRow['notes']) ? $fieldRow['notes'] : null,
            );
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<string>
     */
    private function fieldIds(array $validated): array
    {
        if (! array_key_exists('field_ids', $validated) || ! is_array($validated['field_ids'])) {
            return [];
        }

        /** @var array<int, mixed> $rawFieldIds */
        $rawFieldIds = array_values($validated['field_ids']);
        $fieldIds = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($rawFieldIds as $fieldId) {
            if (is_string($fieldId) && $fieldId !== '') {
                $fieldIds[] = $fieldId;
            }
        }

        return $fieldIds;
    }

    /**
     * @param  array<array-key, mixed>  $fieldRow
     */
    private function requiredString(array $fieldRow, string $key): string
    {
        return array_key_exists($key, $fieldRow) && is_string($fieldRow[$key])
            ? $fieldRow[$key]
            : '';
    }
}
