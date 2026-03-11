<?php

use App\Modules\Notifications\Infrastructure\Integrations\EskizSmsProvider;
use App\Modules\Notifications\Infrastructure\Integrations\PlayMobileSmsProvider;
use App\Modules\Notifications\Infrastructure\Integrations\TextUpSmsProvider;

return [
    'sms' => [
        'message_types' => [
            'otp',
            'reminder',
            'transactional',
            'bulk',
        ],
        'default_routing' => [
            'otp' => ['eskiz', 'playmobile', 'textup'],
            'reminder' => ['playmobile', 'eskiz', 'textup'],
            'transactional' => ['eskiz', 'playmobile', 'textup'],
            'bulk' => ['textup', 'playmobile', 'eskiz'],
        ],
        'providers' => [
            'eskiz' => [
                'driver' => EskizSmsProvider::class,
                'name' => 'Eskiz',
                'sender' => env('SMS_ESKIZ_SENDER', 'MedFlow'),
                'message_id_prefix' => env('SMS_ESKIZ_MESSAGE_ID_PREFIX', 'eskiz'),
            ],
            'playmobile' => [
                'driver' => PlayMobileSmsProvider::class,
                'name' => 'Play Mobile',
                'sender' => env('SMS_PLAYMOBILE_SENDER', 'MedFlow'),
                'message_id_prefix' => env('SMS_PLAYMOBILE_MESSAGE_ID_PREFIX', 'playmobile'),
            ],
            'textup' => [
                'driver' => TextUpSmsProvider::class,
                'name' => 'TextUp',
                'sender' => env('SMS_TEXTUP_SENDER', 'MedFlow'),
                'message_id_prefix' => env('SMS_TEXTUP_MESSAGE_ID_PREFIX', 'textup'),
            ],
        ],
    ],
];
