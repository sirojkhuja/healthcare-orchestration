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
    'telegram' => [
        'provider_key' => 'telegram',
        'api_base_url' => env('TELEGRAM_API_BASE_URL', 'https://api.telegram.org'),
        'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', ''),
        'default_parse_mode' => env('TELEGRAM_PARSE_MODE', 'HTML'),
    ],
];
