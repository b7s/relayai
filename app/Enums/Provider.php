<?php

namespace App\Enums;

enum Provider: string
{
    case Nvidia = 'nvidia';
    case OpenRouter = 'openrouter';
    case OpenAI = 'openai';
    case Anthropic = 'anthropic';
    case ZAI = 'zai';
    case OpenCodeGo = 'opencode-go';

    public function baseUrl(): string
    {
        return match ($this) {
            self::Nvidia => 'https://integrate.api.nvidia.com',
            self::OpenRouter => 'https://openrouter.ai/api',
            self::OpenAI => 'https://api.openai.com',
            self::Anthropic => 'https://api.anthropic.com',
            self::ZAI => 'https://api.z.ai',
            self::OpenCodeGo => 'https://opencode.ai',
        };
    }

    /**
     * Upstream OpenAI-compatible chat completions path for this provider.
     */
    public function chatCompletionsPath(): string
    {
        return match ($this) {
            self::Nvidia => $this->baseUrl().'/v1/chat/completions',
            self::OpenRouter => $this->baseUrl().'/v1/chat/completions',
            self::OpenAI => $this->baseUrl().'/v1/chat/completions',
            self::Anthropic => $this->baseUrl().'/v1/chat/completions',
            self::ZAI => $this->baseUrl().'/api/paas/v4/chat/completions',
            self::OpenCodeGo => $this->baseUrl().'/zen/go/v1/chat/completions',
        };
    }

    public static function fromName(string $name): self
    {
        return match (strtolower($name)) {
            'nvidia' => self::Nvidia,
            'openrouter' => self::OpenRouter,
            'openai' => self::OpenAI,
            'anthropic' => self::Anthropic,
            'zai' => self::ZAI,
            'opencode-go', 'opencode_go', 'opencodego' => self::OpenCodeGo,
            default => throw new \InvalidArgumentException("Unknown provider: {$name}"),
        };
    }
}
