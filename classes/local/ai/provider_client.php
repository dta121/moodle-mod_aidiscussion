<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_aidiscussion\local\ai;

use core_ai\aiactions\base;
use core_ai\aiactions\generate_text;
use core_ai\aiactions\responses\response_base;
use core_ai\manager;
use core_ai\provider;

/**
 * Runs Moodle AI requests against a specific provider plugin.
 *
 * @package   mod_aidiscussion
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_client {
    /**
     * Generate text with the named provider.
     *
     * @param string $providercomponent
     * @param int $contextid
     * @param int $userid
     * @param string $prompttext
     * @return \stdClass
     */
    public static function generate_text(
        string $providercomponent,
        int $contextid,
        int $userid,
        string $prompttext,
    ): \stdClass {
        if (provider_registry::is_plugin_provider($providercomponent)) {
            return self::generate_text_with_plugin_provider($providercomponent, $prompttext);
        }

        return self::generate_text_with_core_provider($providercomponent, $contextid, $userid, $prompttext);
    }

    /**
     * Generate text with a Moodle core AI provider.
     *
     * @param string $providercomponent
     * @param int $contextid
     * @param int $userid
     * @param string $prompttext
     * @return \stdClass
     */
    private static function generate_text_with_core_provider(
        string $providercomponent,
        int $contextid,
        int $userid,
        string $prompttext,
    ): \stdClass {
        $provider = self::get_provider_instance($providercomponent);

        if (!in_array(generate_text::class, $provider->get_action_list())) {
            throw new \coding_exception('Provider does not support text generation: ' . $providercomponent);
        }

        if (!manager::is_action_enabled($providercomponent, generate_text::class)) {
            throw new \coding_exception('Text generation is disabled for provider: ' . $providercomponent);
        }

        if (!$provider->is_provider_configured()) {
            throw new \coding_exception('Provider is not configured: ' . $providercomponent);
        }

        $processorclass = $providercomponent . '\\process_generate_text';
        if (!class_exists($processorclass)) {
            throw new \coding_exception('Missing processor class: ' . $processorclass);
        }

        $action = new generate_text($contextid, $userid, $prompttext);
        $processor = new $processorclass($provider, $action);
        $response = $processor->process();
        self::store_action_result($provider, $action, $response);

        $responsedata = $response->get_response_data();

        return (object) [
            'success' => $response->get_success(),
            'providercomponent' => $providercomponent,
            'modelname' => (string)get_config($providercomponent, 'action_generate_text_model'),
            'generatedcontent' => trim((string)($responsedata['generatedcontent'] ?? '')),
            'errorcode' => $response->get_errorcode(),
            'errormessage' => $response->get_errormessage(),
            'responsedata' => $responsedata,
        ];
    }

    /**
     * Generate text with a plugin-managed provider.
     *
     * @param string $providerid
     * @param string $prompttext
     * @return \stdClass
     */
    private static function generate_text_with_plugin_provider(string $providerid, string $prompttext): \stdClass {
        $config = provider_registry::get_plugin_provider_config($providerid);

        if (empty($config['enabled'])) {
            throw new \coding_exception('Plugin-managed provider is disabled: ' . $providerid);
        }

        if (!provider_registry::is_plugin_provider_configured($providerid)) {
            throw new \coding_exception('Plugin-managed provider is not fully configured: ' . $providerid);
        }

        $response = match ($config['transport']) {
            'anthropic' => self::generate_text_anthropic($config, $prompttext),
            default => self::generate_text_openai_compatible($config, $prompttext),
        };

        return (object) [
            'success' => trim((string)$response['generatedcontent']) !== '',
            'providercomponent' => $providerid,
            'modelname' => $response['modelname'],
            'generatedcontent' => trim((string)$response['generatedcontent']),
            'errorcode' => '',
            'errormessage' => '',
            'responsedata' => $response['responsedata'],
        ];
    }

    /**
     * Generate text using an OpenAI-compatible chat completions endpoint.
     *
     * @param array $config
     * @param string $prompttext
     * @return array
     */
    private static function generate_text_openai_compatible(array $config, string $prompttext): array {
        global $CFG;

        $headers = [
            'Content-Type: application/json',
        ];

        $apikey = trim((string)$config['apikey']);
        if ($apikey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apikey;
        } else if ($config['id'] === 'aidiscussion_ollama') {
            $headers[] = 'Authorization: Bearer ollama';
        }

        if ($config['id'] === 'aidiscussion_openrouter') {
            $headers[] = 'HTTP-Referer: ' . $CFG->wwwroot;
            $headers[] = 'X-OpenRouter-Title: Moodle AI discussion';
        }

        $payload = [
            'model' => $config['model'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompttext,
                ],
            ],
            'temperature' => (float)$config['temperature'],
            'max_tokens' => (int)$config['maxtokens'],
            'stream' => false,
        ];

        $url = self::build_url($config['baseurl'], '/chat/completions');
        $responsedata = self::post_json($url, $payload, $headers);
        $generatedcontent = self::extract_openai_content($responsedata);

        return [
            'generatedcontent' => $generatedcontent,
            'modelname' => trim((string)($responsedata['model'] ?? $config['model'])),
            'responsedata' => $responsedata,
        ];
    }

    /**
     * Generate text using the native Anthropic messages API.
     *
     * @param array $config
     * @param string $prompttext
     * @return array
     */
    private static function generate_text_anthropic(array $config, string $prompttext): array {
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . trim((string)$config['apikey']),
            'anthropic-version: 2023-06-01',
        ];

        $payload = [
            'model' => $config['model'],
            'max_tokens' => (int)$config['maxtokens'],
            'temperature' => max(0.0, min(1.0, (float)$config['temperature'])),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompttext,
                ],
            ],
        ];

        $url = self::build_url($config['baseurl'], '/v1/messages');
        $responsedata = self::post_json($url, $payload, $headers);
        $generatedcontent = self::extract_anthropic_content($responsedata);

        return [
            'generatedcontent' => $generatedcontent,
            'modelname' => trim((string)($responsedata['model'] ?? $config['model'])),
            'responsedata' => $responsedata,
        ];
    }

    /**
     * Execute a JSON POST request.
     *
     * @param string $url
     * @param array $payload
     * @param array $headers
     * @return array
     */
    private static function post_json(string $url, array $payload, array $headers): array {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $responsebody = $curl->post($url, json_encode($payload), [
            'CURLOPT_HTTPHEADER' => $headers,
            'CURLOPT_TIMEOUT' => 120,
            'CURLOPT_CONNECTTIMEOUT' => 20,
        ]);
        $info = $curl->get_info();
        $status = (int)($info['http_code'] ?? 0);

        if ($responsebody === false) {
            $message = trim((string)($curl->error ?? ''));
            if ($message === '') {
                $message = 'The provider request failed before any response was returned.';
            }
            throw new \coding_exception($message);
        }

        $responsedata = json_decode($responsebody, true);
        if (!is_array($responsedata)) {
            $responsedata = [];
        }

        if ($status < 200 || $status >= 300) {
            throw new \coding_exception(self::extract_error_message($responsedata, $responsebody, $status));
        }

        return $responsedata;
    }

    /**
     * Extract text from an OpenAI-compatible response payload.
     *
     * @param array $responsedata
     * @return string
     */
    private static function extract_openai_content(array $responsedata): string {
        $message = $responsedata['choices'][0]['message']['content'] ?? '';
        if (is_string($message)) {
            return trim($message);
        }

        if (!is_array($message)) {
            return '';
        }

        $parts = [];
        foreach ($message as $part) {
            if (is_string($part)) {
                $parts[] = $part;
                continue;
            }

            if (!is_array($part)) {
                continue;
            }

            if (isset($part['text']) && is_string($part['text'])) {
                $parts[] = $part['text'];
                continue;
            }

            if (($part['type'] ?? '') === 'text' && isset($part['text']) && is_string($part['text'])) {
                $parts[] = $part['text'];
            }
        }

        return trim(implode("\n", array_filter($parts)));
    }

    /**
     * Extract text from an Anthropic response payload.
     *
     * @param array $responsedata
     * @return string
     */
    private static function extract_anthropic_content(array $responsedata): string {
        $parts = [];

        foreach (($responsedata['content'] ?? []) as $part) {
            if (!is_array($part)) {
                continue;
            }

            if (($part['type'] ?? '') === 'text' && isset($part['text']) && is_string($part['text'])) {
                $parts[] = $part['text'];
            }
        }

        return trim(implode("\n", array_filter($parts)));
    }

    /**
     * Extract a useful provider error message.
     *
     * @param array $responsedata
     * @param string $rawbody
     * @param int $status
     * @return string
     */
    private static function extract_error_message(array $responsedata, string $rawbody, int $status): string {
        $message = trim((string)($responsedata['error']['message'] ?? $responsedata['message'] ?? ''));
        if ($message === '' && isset($responsedata['error']) && is_string($responsedata['error'])) {
            $message = trim($responsedata['error']);
        }

        if ($message === '') {
            $message = trim(strip_tags($rawbody));
        }

        if ($message === '') {
            $message = 'The provider returned HTTP ' . $status . '.';
        }

        if (\core_text::strlen($message) > 500) {
            $message = \core_text::substr($message, 0, 497) . '...';
        }

        return $message;
    }

    /**
     * Join a base URL with a path.
     *
     * @param string $baseurl
     * @param string $path
     * @return string
     */
    private static function build_url(string $baseurl, string $path): string {
        return rtrim($baseurl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Instantiate a configured provider plugin.
     *
     * @param string $providercomponent
     * @return provider
     */
    private static function get_provider_instance(string $providercomponent): provider {
        if (!preg_match('/^aiprovider_[a-z0-9_]+$/', $providercomponent)) {
            throw new \coding_exception('Invalid provider component: ' . $providercomponent);
        }

        $providerclass = $providercomponent . '\\provider';
        if (!class_exists($providerclass)) {
            throw new \coding_exception('Unknown provider class: ' . $providerclass);
        }

        return new $providerclass();
    }

    /**
     * Mirror core_ai\manager result logging for explicit provider dispatch.
     *
     * @param provider $provider
     * @param base $action
     * @param response_base $response
     * @return int
     */
    private static function store_action_result(
        provider $provider,
        base $action,
        response_base $response,
    ): int {
        global $DB;

        $record = (object) [
            'actionname' => $action->get_basename(),
            'success' => $response->get_success(),
            'userid' => $action->get_configuration('userid'),
            'contextid' => $action->get_configuration('contextid'),
            'provider' => $provider->get_name(),
            'errorcode' => $response->get_errorcode(),
            'errormessage' => $response->get_errormessage(),
            'timecreated' => $action->get_configuration('timecreated'),
            'timecompleted' => $response->get_timecreated(),
        ];

        $transaction = $DB->start_delegated_transaction();

        try {
            $record->actionid = $action->store($response);
            $recordid = $DB->insert_record('ai_action_register', $record);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        return $recordid;
    }
}
