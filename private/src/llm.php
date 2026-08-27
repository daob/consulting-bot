<?php
/**
 * A small client for any OpenAI-compatible /chat/completions endpoint.
 *
 * Everything the app knows about the model provider is in this file. Moving to
 * SURF AI-hub, Scaleway or Mistral means changing api_base and api_key in
 * config.php; no other file needs to know.
 */
declare(strict_types=1);

class CcLlmError extends RuntimeException {}

/**
 * Replace the HTTP layer, for tests. Pass null to restore the real one.
 * The callable receives (string $url, array $payload, array $headers) and
 * returns [int $status, string $body].
 */
function cc_llm_transport(?callable $fn = null, bool $set = false): ?callable
{
    static $transport = null;
    if ($set) {
        $transport = $fn;
    }
    return $transport;
}

/**
 * One chat completion.
 *
 * @param array $messages [['role'=>..., 'content'=>...], ...]
 * @param array $opts     model, max_tokens, temperature, response_format
 * @return array{content:string, usage:array, finish:string}
 */
function cc_llm_chat(array $messages, array $opts = []): array
{
    $cfg = cc_config();

    $payload = [
        'model'       => $opts['model']       ?? $cfg['model'],
        'messages'    => $messages,
        'max_tokens'  => $opts['max_tokens']  ?? $cfg['max_tokens'],
        'temperature' => $opts['temperature'] ?? $cfg['temperature'],
    ];
    if (!empty($opts['response_format'])) {
        $payload['response_format'] = $opts['response_format'];
    }

    // Passed through verbatim when configured. Several models reason on every
    // turn and bill the thinking as output; where the provider lets you switch
    // that off it is the single biggest lever on both cost and latency.
    // OpenRouter spells it ['enabled' => false]; others differ, which is why
    // this is a passthrough rather than a boolean.
    // array_key_exists, not ??: a caller passing null means "send nothing",
    // which is different from "not specified, fall back to config".
    $reasoning = array_key_exists('reasoning', $opts)
        ? $opts['reasoning']
        : cc_config('reasoning');
    if ($reasoning !== null) {
        $payload['reasoning'] = $reasoning;
    }

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $cfg['api_key'],
        // OpenRouter attribution headers; ignored by other providers.
        'HTTP-Referer: ' . ($cfg['site_url'] ?? ''),
        'X-Title: '      . ($cfg['site_title'] ?? ''),
    ];

    $url = rtrim($cfg['api_base'], '/') . '/chat/completions';

    // Retry only on the failures that are worth retrying.
    $attempts = 0;
    $lastError = 'unknown';
    while ($attempts < 3) {
        $attempts++;
        [$status, $body] = cc_llm_send($url, $payload, $headers);

        if ($status === 200) {
            $json = json_decode($body, true);
            if (!is_array($json)) {
                $lastError = 'response was not JSON';
            } elseif (isset($json['error'])) {
                // Some gateways return errors with a 200.
                throw new CcLlmError('provider error: ' . ($json['error']['message'] ?? 'unspecified'));
            } else {
                $choice  = $json['choices'][0] ?? [];
                $content = (string)($choice['message']['content'] ?? '');
                $finish  = (string)($choice['finish_reason'] ?? '');

                // Reasoning models spend max_tokens on thinking first. If the
                // budget ran out there is no content at all, and retrying with
                // the same budget would fail identically.
                if (trim($content) === '') {
                    if ($finish === 'length') {
                        $payload['max_tokens'] = (int)($payload['max_tokens'] * 2);
                        $lastError = 'empty content: token budget consumed by reasoning';
                        continue;
                    }
                    $lastError = 'model returned empty content (finish_reason: ' . $finish . ')';
                    continue;
                }

                return [
                    'content' => $content,
                    'usage'   => $json['usage'] ?? [],
                    'finish'  => $finish,
                ];
            }
        } elseif ($status === 429 || $status >= 500) {
            $lastError = 'HTTP ' . $status;
        } else {
            $detail = json_decode($body, true)['error']['message'] ?? substr($body, 0, 300);

            // Some models cannot have reasoning switched off and reject the
            // request outright. That is a configuration mismatch, not a reason
            // to lose the turn: drop the field and try once more.
            if (isset($payload['reasoning']) && stripos($detail, 'reasoning') !== false) {
                unset($payload['reasoning']);
                $lastError = 'model refuses to disable reasoning; retried without it';
                continue;
            }

            // Everything else here - 400, 401, 403 - will not fix itself.
            throw new CcLlmError('HTTP ' . $status . ': ' . $detail);
        }

        if ($attempts < 3) {
            usleep(400000 * $attempts);   // 0.4s, 0.8s
        }
    }

    throw new CcLlmError('model call failed after ' . $attempts . ' attempts: ' . $lastError);
}

/** The HTTP call itself, or the test double if one is installed. */
function cc_llm_send(string $url, array $payload, array $headers): array
{
    $fake = cc_llm_transport();
    if ($fake !== null) {
        return $fake($url, $payload, $headers);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 180,
    ]);
    $body   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new CcLlmError('network error: ' . $err);
    }
    return [$status, (string)$body];
}
