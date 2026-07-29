<?php

namespace App\Http\Controllers;

use App\Data\Routing\ChatRequestData;
use App\Exceptions\AllProvidersFailedException;
use App\Http\Requests\ChatCompletionRequest;
use App\Services\Routing\Router;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ChatCompletionController extends Controller
{
    public function __invoke(ChatCompletionRequest $request, Router $router): JsonResponse|StreamedResponse
    {
        $data = $request->payload();

        if ($data->stream) {
            return $this->streamResponse($router, $data);
        }

        return $this->jsonResponse($router, $data);
    }

    private function jsonResponse(Router $router, ChatRequestData $data): JsonResponse
    {
        $result = $router->chat($data);

        return response()->json(
            json_decode((string) $result->body, true, flags: JSON_THROW_ON_ERROR),
        );
    }

    private function streamResponse(Router $router, ChatRequestData $data): StreamedResponse
    {
        return response()->stream(function () use ($router, $data): void {
            try {
                $router->stream($data, function (string $chunk): void {
                    echo $chunk."\n";
                    ob_flush();
                    flush();
                });
            } catch (AllProvidersFailedException $e) {
                echo 'data: '.json_encode([
                    'error' => [
                        'message' => $e->getMessage(),
                        'type' => 'provider_error',
                    ],
                ], JSON_THROW_ON_ERROR)."\n\n";
                ob_flush();
                flush();
            }

            echo 'data: [DONE]'."\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
