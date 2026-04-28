=== AI Provider for vLLM ===
Contributors:      georgestephanis
Tags:              ai, vllm, llm, openai-compatible, connector
Requires at least: 7.0
Tested up to:      7.0
Stable tag:        1.1.0
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

vLLM provider for the WordPress AI Client, based on original work by Fueled.

== Description ==

This plugin provides [vLLM](https://docs.vllm.ai/) integration for the WordPress AI Client.

It is a fork based on original work by Fueled, updated to use vLLM's OpenAI-compatible endpoints.

Current functionality:

* Model discovery from `GET /v1/models`
* Text generation via OpenAI-compatible chat completions
* Host configuration in **Settings > vLLM**
* API key management in **Settings > Connectors** under provider **vLLM**

Current limitations:

* Text-generation focused (no image-generation support in this fork)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/ai-provider-for-vllm/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Set your vLLM host in **Settings > vLLM**.
4. Set your API key in **Settings > Connectors** for provider **vLLM**.

== Frequently Asked Questions ==

= What host URL should I enter? =

Enter the base URL of your vLLM server **without** `/v1`.

Examples:

* `http://localhost:8000`
* `http://192.168.0.233:8000`

= Can I configure host via environment variable? =

Yes. Set `VLLM_HOST`. It takes precedence over the admin setting.

= Where do I enter the API key? =

Use **Settings > Connectors** and set the key for provider **vLLM**.

= Does this plugin support image generation? =

No. This fork currently supports text generation capabilities.

== Screenshots ==

1. Settings > vLLM screen showing host URL configuration and model listing.

== Changelog ==

= 1.1.0 - 2026-04-23 =

* Fork adapted for vLLM OpenAI-compatible endpoints.
* Provider renamed to vLLM.
* Model discovery now uses `/v1/models`.
* Text generation uses OpenAI-compatible chat completion requests.

== Upgrade Notice ==

= 1.1.0 =

Updates this fork to vLLM-focused behavior and endpoint compatibility.
