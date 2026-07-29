<p align="center"><img src="https://img.shields.io/badge/RelayAI-AI%20Router-blue" alt="RelayAI"></p>

# RelayAI

A Laravel 13 API-only AI provider router. RelayAI sits between your AI client (opencode, Cursor, Claude Code, or any OpenAI-compatible SDK) and upstream LLM providers (NVIDIA, OpenRouter, and any OpenAI-compatible API). When a provider errors — rate limits, 5xx, timeouts, auth failures — RelayAI automatically fails over to the next configured provider/model/key, with per-entry cooldown and streaming reconnect-replay.

## Features

- **OpenAI-compatible API** — point your client's custom provider URL at `http://localhost:8000/v1/chat/completions`.
- **Automatic failover** — on retryable errors, the router walks the configured chain of providers/models/keys until one succeeds.
- **Per-entry cooldown** — each `(provider, model, key)` tuple is tracked independently in SQLite. After `RELAYAI_MAX_FAILURES` failures within `RELAYAI_WINDOW_MINUTES`, the entry is skipped for `RELAYAI_COOLDOWN_MINUTES`.
- **Streaming with reconnect-replay** — Server-Sent Events are proxied to the client; if a stream breaks mid-way, the partial content is injected as context and the next provider continues seamlessly.
- **Wraparound fallback** — if every entry is on cooldown, the router forces one more attempt from the top of the chain rather than returning 503.
- **Optional gateway auth** — set `RELAYAI_GATEWAY_KEY` and clients must send `Authorization: Bearer <key>`.
- **Request tracing** — every response carries an `X-Request-ID` header.
- **RFC 9457 errors** — all failures return Problem+JSON.
- **No frontend, no database required for operation** — only SQLite for provider-health tracking.

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## Configuration

All settings live in `.env` and are read by `config/relayai.php`.

| Variable | Default | Description |
|---|---|---|
| `RELAYAI_GATEWAY_KEY` | *(empty)* | If set, clients must present this bearer token. |
| `RELAYAI_RETRIES` | `3` | Attempts per entry before advancing to the next. |
| `RELAYAI_TIMEOUT_SECONDS` | `60` | Upstream request timeout. |
| `RELAYAI_MAX_FAILURES` | `3` | Failures within the window that trip cooldown. |
| `RELAYAI_WINDOW_MINUTES` | `1` | Sliding window to count failures in. |
| `RELAYAI_COOLDOWN_MINUTES` | `15` | How long a tripped entry stays skipped. |
| `RELAYAI_ENTRIES` | `[]` | JSON array of `{provider, model, api_key}` objects, in priority order. |

Example `RELAYAI_ENTRIES`:

```json
[
  {"provider":"nvidia","model":"nvidia/llama-3.1-nemotron-ultra-253b-v1","api_key":"nvapi-xxxx"},
  {"provider":"openrouter","model":"deepseek/deepseek-chat","api_key":"sk-or-v1-xxxx"},
  {"provider":"nvidia","model":"nvidia/llama-3.1-nemotron-ultra-253b-v1","api_key":"nvapi-yyyy"}
]
```

You may repeat the same provider/model with a different key to rotate quotas.

## Endpoints

| Method | Path | Description |
|---|---|---|
| `POST` | `/v1/chat/completions` | OpenAI-compatible chat completion (streaming & non-streaming). |
| `GET` | `/v1/models` | Lists configured logical models. |
| `GET` | `/v1/health` | Per-provider reachability. |
| `GET` | `/up` | Readiness probe. |

## Using with opencode

In your opencode config, add a custom provider:

```json
{
  "providers": {
    "relayai": {
      "type": "openai",
      "baseUrl": "http://localhost:8000/v1",
      "apiKey": "any-value-or-gateway-key"
    }
  }
}
```

The model name you select in opencode is passed through to RelayAI, which then routes to the first healthy entry (you can mirror the upstream model names in `RELAYAI_ENTRIES`).

## Architecture

Built following the Laravel Statecraft patterns (bounded context, explicit transitions, action/service layers, audit trail):

- `app/Enums/Provider.php` — provider registry with base URLs.
- `app/Data/Routing/` — typed DTOs (`ConfigEntry`, `ChatRequestData`, `AttemptResult`).
- `app/Actions/Routing/AttemptChat.php` — performs the upstream call and classifies errors.
- `app/Actions/Routing/RecordFailure.php` — append-only failure log.
- `app/Models/ProviderFailure.php` — SQLite-backed cooldown tracking.
- `app/Services/Routing/Router.php` — orchestrates the chain walk, cooldown skip, and wraparound.
- `app/Http/Controllers/`, `app/Http/Middleware/`, `app/Http/Requests/` — API layer.

## Quality Gates

```bash
./vendor/bin/pest --parallel
./vendor/bin/phpstan analyse --level 6
./vendor/bin/pint
./vendor/bin/catraca
```

## License

MIT.
