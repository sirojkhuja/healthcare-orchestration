<?php

use App\Modules\Notifications\Application\Data\NotificationTemplateData;
use App\Modules\Notifications\Infrastructure\Rendering\CurlyBraceNotificationTemplateRenderer;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

it('extracts sorted unique placeholders and renders nested variables', function (): void {
    $renderer = new CurlyBraceNotificationTemplateRenderer;
    $placeholders = $renderer->placeholders(
        'Reminder for {{patient.first_name}}',
        'Visit at {{clinic.name}} starts at {{appointment.start_at}} for {{patient.first_name}}.',
    );

    expect($placeholders)->toBe([
        'appointment.start_at',
        'clinic.name',
        'patient.first_name',
    ]);

    $rendered = $renderer->render(
        notificationTemplateFixture($placeholders),
        [
            'patient' => ['first_name' => 'Amina'],
            'clinic' => ['name' => 'Downtown Clinic'],
            'appointment' => ['start_at' => '2026-03-12 09:00'],
        ],
    );

    expect($rendered->renderedSubject)->toBe('Reminder for Amina');
    expect($rendered->renderedBody)->toBe('Visit at Downtown Clinic starts at 2026-03-12 09:00 for Amina.');
});

it('rejects missing placeholders and non scalar render values', function (): void {
    $renderer = new CurlyBraceNotificationTemplateRenderer;
    $template = notificationTemplateFixture(['invoice.number', 'patient.first_name']);

    expect(fn () => $renderer->render($template, [
        'patient' => ['first_name' => 'Amina'],
    ]))->toThrow(UnprocessableEntityHttpException::class, 'clinic.name');

    expect(fn () => $renderer->render($template, [
        'patient' => ['first_name' => ['not', 'scalar']],
        'invoice' => ['number' => 'INV-000001'],
    ]))->toThrow(UnprocessableEntityHttpException::class, 'patient.first_name');
});

/**
 * @param  list<string>  $placeholders
 */
function notificationTemplateFixture(array $placeholders): NotificationTemplateData
{
    return new NotificationTemplateData(
        templateId: 'template-1',
        tenantId: 'tenant-1',
        code: 'APPOINTMENT-REMINDER',
        name: 'Appointment reminder',
        channel: 'email',
        description: null,
        isActive: true,
        currentVersion: 1,
        subjectTemplate: 'Reminder for {{patient.first_name}}',
        bodyTemplate: 'Visit at {{clinic.name}} starts at {{appointment.start_at}} for {{patient.first_name}}.',
        placeholders: $placeholders,
        createdAt: CarbonImmutable::parse('2026-03-12T08:00:00+05:00'),
        updatedAt: CarbonImmutable::parse('2026-03-12T08:00:00+05:00'),
    );
}
