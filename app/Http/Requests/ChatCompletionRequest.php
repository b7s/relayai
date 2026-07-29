<?php

namespace App\Http\Requests;

use App\Data\Routing\ChatRequestData;
use Illuminate\Foundation\Http\FormRequest;

final class ChatCompletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'model' => ['required', 'string'],
            'messages' => ['required', 'array', 'min:1'],
            'messages.*.role' => ['required', 'string', 'in:system,user,assistant,tool'],
            'messages.*.content' => ['required_without:messages.*.tool_calls'],
            'stream' => ['sometimes', 'boolean'],
            'temperature' => ['sometimes', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function payload(): ChatRequestData
    {
        return ChatRequestData::fromRequest($this->validated());
    }
}
