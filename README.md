# AI Provider for vLLM

![AI Provider for vLLM](./.wordpress-org/banner-1544x500.png)

> vLLM provider for the PHP and WordPress AI Client packages, based on original work by Fueled.

## Overview

AI Provider for vLLM integrates [vLLM](https://docs.vllm.ai/) with the WordPress AI Client (`wordpress/php-ai-client`).

This fork has been updated for vLLM's OpenAI-compatible API:

- Model discovery via `GET /v1/models`
- Text generation via OpenAI-compatible chat completions
- API key authentication through WordPress Connectors

Current implementation scope:

- Text generation support
- Chat history capability
- Structured output and function declarations through supported model options

Not currently implemented in this fork:

- Image generation

## Requirements

- PHP 7.4+
- WordPress 7.0+ or `wordpress/php-ai-client` `^1.3`
- A reachable vLLM server exposing OpenAI-compatible endpoints

## Installation

### As a WordPress Plugin

1. Upload the plugin files to `/wp-content/plugins/ai-provider-for-vllm/`.
2. Activate the plugin in WordPress admin.
3. Configure host URL in **Settings > vLLM**.
4. Configure API key in **Settings > Connectors** for provider **vLLM**.

### As a Composer Package

```bash
composer require georgestephanis/ai-provider-for-vllm
```

## Configuration

### vLLM Host URL

Default host:

- `http://localhost:8000`

Override options:

1. Environment variable `VLLM_HOST` (takes precedence)
2. WordPress admin setting in **Settings > vLLM**

Use the base URL **without** `/v1`.

Examples:

- `http://localhost:8000`
- `http://192.168.0.233:8000`

### API Key

Set your API key in **Settings > Connectors** under the **vLLM** provider.

Internally this maps to the option key:

- `connectors_ai_vllm_api_key`

## Usage

### With WordPress AI Client

```php
$result = wp_ai_client_prompt( 'Hello, how are you?' )
    ->using_provider( 'vllm' )
    ->using_system_instruction( 'You are a helpful assistant.' )
    ->generate_text();
```

### Standalone PHP (`php-ai-client`)

```php
use GeorgeStephanis\AiProviderForVllm\Provider\VllmProvider;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

require_once 'vendor/autoload.php';

$registry = AiClient::defaultRegistry();
$registry->registerProvider( VllmProvider::class );
$registry->setProviderRequestAuthentication( 'vllm', new ApiKeyRequestAuthentication( 'YOUR_API_KEY' ) );

$result = AiClient::prompt( 'Hello!' )
    ->usingProvider( 'vllm' )
    ->generateText();
```

## Attribution

This project is based on original work by Fueled.

## Changelog

See [CHANGELOG.md](./CHANGELOG.md).
