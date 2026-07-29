<?php

namespace App\Enums;

enum Provider: string
{
    case Nvidia = 'nvidia';
    case OpenRouter = 'openrouter';

    public function baseUrl(): string
    {
        return match ($this) {
            self::Nvidia => 'https://integrate.api.nvidia.com',
            self::OpenRouter => 'https://openrouter.ai/api',
        };
    }

    public static function fromName(string $name): self
    {
        return match (strtolower($name)) {
            'nvidia' => self::Nvidia,
            'openrouter' => self::OpenRouter,
            default => throw new \InvalidArgumentException("Unknown provider: {$name}"),
        };
    }
}
