<?php

return [
    'gateway_key' => env('RELAYAI_GATEWAY_KEY'),

    'retries' => (int) env('RELAYAI_RETRIES', 3),

    'timeout_seconds' => (float) env('RELAYAI_TIMEOUT_SECONDS', 60),

    'max_failures' => (int) env('RELAYAI_MAX_FAILURES', 3),

    'window_minutes' => (int) env('RELAYAI_WINDOW_MINUTES', 1),

    'cooldown_minutes' => (int) env('RELAYAI_COOLDOWN_MINUTES', 15),

    'entries' => env('RELAYAI_ENTRIES')
        ? json_decode((string) env('RELAYAI_ENTRIES'), true, flags: JSON_THROW_ON_ERROR)
        : [
            [
                'provider' => 'openai',
                'model' => 'gpt-5',
                'api_key' => env('RELAYAI_API_KEY_1'),
            ],
            [
                'provider' => 'openrouter',
                'model' => 'deepseek/deepseek-chat',
                'api_key' => env('RELAYAI_API_KEY_2'),
            ],
        ],
];
