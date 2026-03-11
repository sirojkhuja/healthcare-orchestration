<?php

namespace App\Modules\Scheduling\Application\Services;

use App\Modules\Patient\Application\Data\PatientContactData;
use App\Modules\Patient\Application\Data\PatientData;

final class AppointmentNotificationRecipientResolver
{
    /**
     * @param  list<PatientContactData>  $contacts
     * @return array<string, mixed>|null
     */
    public function resolve(string $channel, PatientData $patient, array $contacts): ?array
    {
        return match ($channel) {
            'sms' => $this->smsRecipient($patient, $contacts),
            'email' => $this->emailRecipient($patient, $contacts),
            default => null,
        };
    }

    /**
     * @param  list<PatientContactData>  $contacts
     * @return array<string, mixed>|null
     */
    private function emailRecipient(PatientData $patient, array $contacts): ?array
    {
        $email = $patient->email ?? $this->firstContactValue($contacts, 'email');

        if ($email === null) {
            return null;
        }

        return [
            'email' => $email,
            'name' => $this->patientDisplayName($patient),
        ];
    }

    /**
     * @param  list<PatientContactData>  $contacts
     * @return array<string, mixed>|null
     */
    private function smsRecipient(PatientData $patient, array $contacts): ?array
    {
        $phone = $patient->phone ?? $this->firstContactValue($contacts, 'phone');

        return $phone === null ? null : [
            'phone_number' => $phone,
        ];
    }

    /**
     * @param  list<PatientContactData>  $contacts
     */
    private function firstContactValue(array $contacts, string $field): ?string
    {
        foreach ($contacts as $contact) {
            $value = $field === 'email' ? $contact->email : $contact->phone;

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    private function patientDisplayName(PatientData $patient): string
    {
        $preferred = $patient->preferredName !== null && trim($patient->preferredName) !== ''
            ? trim($patient->preferredName)
            : trim($patient->firstName.' '.$patient->lastName);

        return $preferred !== '' ? $preferred : $patient->patientId;
    }
}
