<?php

namespace App\Modules\Notifications\Presentation\Http\Controllers;

use App\Modules\Notifications\Application\Commands\CreateTemplateCommand;
use App\Modules\Notifications\Application\Commands\DeleteTemplateCommand;
use App\Modules\Notifications\Application\Commands\TestRenderTemplateCommand;
use App\Modules\Notifications\Application\Commands\UpdateTemplateCommand;
use App\Modules\Notifications\Application\Data\NotificationTemplateListCriteria;
use App\Modules\Notifications\Application\Handlers\CreateTemplateCommandHandler;
use App\Modules\Notifications\Application\Handlers\DeleteTemplateCommandHandler;
use App\Modules\Notifications\Application\Handlers\GetTemplateQueryHandler;
use App\Modules\Notifications\Application\Handlers\ListTemplatesQueryHandler;
use App\Modules\Notifications\Application\Handlers\TestRenderTemplateCommandHandler;
use App\Modules\Notifications\Application\Handlers\UpdateTemplateCommandHandler;
use App\Modules\Notifications\Application\Queries\GetTemplateQuery;
use App\Modules\Notifications\Application\Queries\ListTemplatesQuery;
use App\Modules\Notifications\Domain\NotificationTemplateChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class NotificationTemplateController
{
    public function create(Request $request, CreateTemplateCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $template = $handler->handle(new CreateTemplateCommand($validated));

        return response()->json([
            'status' => 'notification_template_created',
            'data' => $template->toArray(),
        ], 201);
    }

    public function delete(string $templateId, DeleteTemplateCommandHandler $handler): JsonResponse
    {
        $template = $handler->handle(new DeleteTemplateCommand($templateId));

        return response()->json([
            'status' => 'notification_template_deleted',
            'data' => $template->toArray(),
        ]);
    }

    public function list(Request $request, ListTemplatesQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request);

        return response()->json([
            'data' => array_map(
                static fn ($template): array => $template->toArray(),
                $handler->handle(new ListTemplatesQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function show(string $templateId, GetTemplateQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetTemplateQuery($templateId))->toArray(),
        ]);
    }

    public function testRender(
        string $templateId,
        Request $request,
        TestRenderTemplateCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'variables' => ['required', 'array'],
        ]);
        /** @var mixed $variablesInput */
        $variablesInput = $validated['variables'] ?? [];

        return response()->json([
            'status' => 'notification_template_rendered',
            'data' => $handler->handle(new TestRenderTemplateCommand(
                $templateId,
                $this->normalizeVariables($variablesInput),
            ))->toArray(),
        ]);
    }

    public function update(string $templateId, Request $request, UpdateTemplateCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $template = $handler->handle(new UpdateTemplateCommand($templateId, $validated));

        return response()->json([
            'status' => 'notification_template_updated',
            'data' => $template->toArray(),
        ]);
    }

    private function criteria(Request $request): NotificationTemplateListCriteria
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'channel' => ['nullable', 'string', Rule::in(NotificationTemplateChannel::all())],
            'is_active' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        /** @var array<string, mixed> $validated */

        return new NotificationTemplateListCriteria(
            query: $this->stringValue($validated, 'q'),
            channel: $this->stringValue($validated, 'channel'),
            isActive: array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : null,
            limit: array_key_exists('limit', $validated) && is_numeric($validated['limit'])
                ? (int) $validated['limit']
                : 25,
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function createRules(): array
    {
        return [
            'code' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'string', Rule::in(NotificationTemplateChannel::all())],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'subject_template' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'body_template' => ['required', 'string', 'max:50000'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function updateRules(): array
    {
        return [
            'code' => ['sometimes', 'filled', 'string', 'max:120'],
            'name' => ['sometimes', 'filled', 'string', 'max:255'],
            'channel' => ['sometimes', 'filled', 'string', Rule::in(NotificationTemplateChannel::all())],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'subject_template' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'body_template' => ['sometimes', 'filled', 'string', 'max:50000'],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function assertNonEmptyPatch(array $validated): void
    {
        if ($validated === []) {
            throw ValidationException::withMessages([
                'payload' => ['At least one updatable field is required.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function stringValue(array $validated, string $key): ?string
    {
        return array_key_exists($key, $validated) && is_string($validated[$key]) && $validated[$key] !== ''
            ? $validated[$key]
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeVariables(mixed $variables): array
    {
        if (! is_array($variables)) {
            return [];
        }

        /** @var array<string, mixed> $normalized */
        $normalized = $variables;

        return $normalized;
    }
}
