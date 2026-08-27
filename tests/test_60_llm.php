<?php
group('model client');

// Retries a 429, then succeeds.
$calls = 0;
cc_llm_transport(function ($url, $payload, $headers) use (&$calls) {
    $calls++;
    if ($calls < 3) {
        return [429, '{"error":{"message":"slow down"}}'];
    }
    return [200, json_encode(['choices' => [['message' => ['content' => 'fine'], 'finish_reason' => 'stop']], 'usage' => []])];
}, true);
$r = cc_llm_chat([['role' => 'user', 'content' => 'x']]);
is_same($r['content'], 'fine', 'retries a rate limit and recovers');
is_same($calls, 3, 'took three attempts');

// A 401 is not worth retrying.
$calls = 0;
cc_llm_transport(function ($url, $payload, $headers) use (&$calls) {
    $calls++;
    return [401, '{"error":{"message":"bad key"}}'];
}, true);
throws(fn() => cc_llm_chat([['role' => 'user', 'content' => 'x']]), 'bad key', 'gives up immediately on a bad key');
is_same($calls, 1, 'did not retry a 401');

// The reasoning-model trap: the whole budget goes on thinking and content is
// empty with finish_reason "length". Retrying at the same size would fail the
// same way, so the budget has to grow.
$seen = [];
cc_llm_transport(function ($url, $payload, $headers) use (&$seen) {
    $seen[] = $payload['max_tokens'];
    if (count($seen) < 2) {
        return [200, json_encode(['choices' => [['message' => ['content' => ''], 'finish_reason' => 'length']], 'usage' => []])];
    }
    return [200, json_encode(['choices' => [['message' => ['content' => 'got there'], 'finish_reason' => 'stop']], 'usage' => []])];
}, true);
$r = cc_llm_chat([['role' => 'user', 'content' => 'x']], ['max_tokens' => 100]);
is_same($r['content'], 'got there', 'recovers from a budget eaten by reasoning');
is_same($seen, [100, 200], 'doubled the budget instead of repeating it');

// An error delivered with a 200, as some gateways do.
cc_llm_transport(fn() => [200, '{"error":{"message":"upstream is down"}}'], true);
throws(fn() => cc_llm_chat([['role' => 'user', 'content' => 'x']]), 'upstream is down', 'catches an error hidden in a 200');

cc_llm_transport(null, true);

group('reasoning passthrough');
$sent = null;
cc_llm_transport(function ($url, $payload, $headers) use (&$sent) {
    $sent = $payload;
    return [200, json_encode(['choices' => [['message' => ['content' => 'x'], 'finish_reason' => 'stop']], 'usage' => []])];
}, true);

cc_llm_chat([['role' => 'user', 'content' => 'x']]);
ok(!array_key_exists('reasoning', $sent), 'nothing is sent when unconfigured');

cc_llm_chat([['role' => 'user', 'content' => 'x']], ['reasoning' => ['enabled' => false]]);
is_same($sent['reasoning'], ['enabled' => false], 'passed through verbatim when given');
cc_llm_transport(null, true);

group('reasoning is scoped, and a refusal does not lose the turn');
$sent = null;
cc_llm_transport(function ($url, $payload, $headers) use (&$sent) {
    $sent = $payload;
    return [200, json_encode(['choices' => [['message' => ['content' => 'x'], 'finish_reason' => 'stop']], 'usage' => []])];
}, true);
cc_llm_chat([['role' => 'user', 'content' => 'x']], ['reasoning' => null]);
ok(!array_key_exists('reasoning', $sent), 'an explicit null means send nothing, not fall back to config');

// A provider that refuses to disable reasoning: drop the field and retry.
$seen = [];
cc_llm_transport(function ($url, $payload, $headers) use (&$seen) {
    $seen[] = array_key_exists('reasoning', $payload);
    if (count($seen) === 1) {
        return [400, '{"error":{"message":"Reasoning is mandatory for this endpoint and cannot be disabled."}}'];
    }
    return [200, json_encode(['choices' => [['message' => ['content' => 'recovered'], 'finish_reason' => 'stop']], 'usage' => []])];
}, true);
$r = cc_llm_chat([['role' => 'user', 'content' => 'x']], ['reasoning' => ['enabled' => false]]);
is_same($r['content'], 'recovered', 'a mandatory-reasoning refusal is recovered from');
is_same($seen, [true, false], 'the second attempt drops the field');
cc_llm_transport(null, true);
