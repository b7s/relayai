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
                'provider' => 'nvidia',
                'model' => 'z-ai/glm-5.2',
                'api_key' => env('NVIDIA_KEY_1'),
            ],
            [
                'provider' => 'nvidia',
                'model' => 'z-ai/glm-5.2',
                'api_key' => env('NVIDIA_KEY_2'),
            ],
            [
                'provider' => 'nvidia',
                'model' => 'minimaxai/minimax-m3',
                'api_key' => env('NVIDIA_KEY_1'),
            ],
            [
                'provider' => 'nvidia',
                'model' => 'minimaxai/minimax-m3',
                'api_key' => env('NVIDIA_KEY_2'),
            ],
            [
                'provider' => 'nvidia',
                'model' => 'deepseek-ai/deepseek-v4-flash',
                'api_key' => env('NVIDIA_KEY_1'),
            ],
            [
                'provider' => 'nvidia',
                'model' => 'deepseek-ai/deepseek-v4-flash',
                'api_key' => env('NVIDIA_KEY_2'),
            ],
            [
                'provider' => 'nvidia',
                'model' => 'deepseek-ai/deepseek-v4-flash',
                'api_key' => env('NVIDIA_KEY_1'),
            ],
            [
                'provider' => 'nvidia',
                'model' => 'deepseek-ai/deepseek-v4-flash',
                'api_key' => env('NVIDIA_KEY_2'),
            ],
            [
                'provider' => 'openrouter',
                'model' => 'tencent/hy3',
                'api_key' => env('OPENROUTER_KEY_1'),
            ],
            [
                'provider' => 'openrouter',
                'model' => 'deepseek/deepseek-v4-flash',
                'api_key' => env('OPENROUTER_KEY_1'),
            ],
        ],
];
