<?php
/**
 * Whop webhook endpoint.
 *
 * - Verifies webhook signature (Standard Webhooks style headers).
 * - Extracts metadata/UTM from event payload.
 * - Calls handlebuy.php using a signed payload (no ticket-code dependency).
 *
 * NOTE:
 * Keep legacy Shopify files untouched for fallback during rollout.
 */

const HANDLEBUY_URL = 'https://giorgiobts.com/php/handlebuy.php';
const HANDLEBUY_TOKEN = '79a0f3827ef97ebdef591918448dc7d3471b49235664e26235f184b413bc885c';
const INTERNAL_SIGNING_SECRET = 'change_me_whop_to_handlebuy_signing_secret';
const WEBHOOK_LOG_FILE = 'whop_webhook_debug.txt';

function env_or_default(string $key, string $default = ''): string {
    $value = getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return $value;
}

function log_line(string $line): void {
    file_put_contents(WEBHOOK_LOG_FILE, '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND);
}

function send_json(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function parse_whop_signatures(string $header): array {
    $parts = array_map('trim', explode(' ', $header));
    $signatures = [];

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        if (strpos($part, 'v1,') === 0) {
            $signatures[] = substr($part, 3);
            continue;
        }

        if (strpos($part, 'v1=') === 0) {
            $signatures[] = substr($part, 3);
            continue;
        }
    }

    return $signatures;
}

function is_valid_whop_signature(string $payload, string $secret): bool {
    $headerId = $_SERVER['HTTP_WEBHOOK_ID'] ?? '';
    $headerTimestamp = $_SERVER['HTTP_WEBHOOK_TIMESTAMP'] ?? '';
    $headerSignature = $_SERVER['HTTP_WEBHOOK_SIGNATURE'] ?? '';

    if ($headerId === '' || $headerTimestamp === '' || $headerSignature === '' || $secret === '') {
        return false;
    }

    if (!ctype_digit((string)$headerTimestamp)) {
        return false;
    }

    $timestamp = (int)$headerTimestamp;
    if (abs(time() - $timestamp) > 300) {
        return false;
    }

    $toSign = $headerId . '.' . $headerTimestamp . '.' . $payload;
    $expected = base64_encode(hash_hmac('sha256', $toSign, $secret, true));

    foreach (parse_whop_signatures($headerSignature) as $candidate) {
        if (hash_equals($expected, $candidate)) {
            return true;
        }
    }

    return false;
}

function value_from_paths(array $data, array $paths, $default = null) {
    foreach ($paths as $path) {
        $cursor = $data;
        $ok = true;

        foreach ($path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                $ok = false;
                break;
            }
            $cursor = $cursor[$segment];
        }

        if ($ok && $cursor !== null && $cursor !== '') {
            return $cursor;
        }
    }

    return $default;
}

function compute_balance_delta(string $offerCode, $amountField): float {
    // Dynamic price on the fly: trust server-side amount when present.
    $amount = is_numeric($amountField) ? (float)$amountField : 0.0;
    if ($amount > 0) {
        return $amount;
    }

    if (preg_match('/_(\d+(?:\.\d+)?)$/', $offerCode, $m)) {
        return (float)$m[1];
    }

    return 0.0;
}

$rawBody = file_get_contents('php://input');
$whopSecret = env_or_default('WHOP_WEBHOOK_SECRET', '');

if (!is_valid_whop_signature($rawBody, $whopSecret)) {
    log_line('Invalid webhook signature.');
    send_json(['ok' => false, 'error' => 'Invalid signature'], 403);
}

$event = json_decode($rawBody, true);
if (!is_array($event)) {
    log_line('Invalid JSON payload.');
    send_json(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

$eventType = (string)value_from_paths($event, [
    ['type'],
    ['event'],
], '');

if ($eventType !== 'payment.succeeded') {
    log_line('Ignoring event type: ' . $eventType);
    send_json(['ok' => true, 'ignored' => true, 'event' => $eventType], 200);
}

$metadata = value_from_paths($event, [
    ['data', 'metadata'],
    ['data', 'payment', 'metadata'],
    ['data', 'checkout_session', 'metadata'],
], []);
if (!is_array($metadata)) {
    $metadata = [];
}

$utm = value_from_paths($event, [
    ['data', 'utm'],
    ['data', 'payment', 'utm'],
    ['data', 'checkout_session', 'utm'],
], []);
if (!is_array($utm)) {
    $utm = [];
}

$userID = (string)($metadata['userID'] ?? $utm['source'] ?? '');
$postID = (string)($metadata['postID'] ?? '');
$offerCode = (string)($metadata['offerCode'] ?? $utm['content'] ?? '');
$paymentRef = (string)($metadata['paymentRef'] ?? $utm['term'] ?? value_from_paths($event, [
    ['data', 'id'],
    ['data', 'payment', 'id'],
], ''));
$amountField = $metadata['amount'] ?? value_from_paths($event, [
    ['data', 'amount'],
    ['data', 'payment', 'amount'],
], 0);

if ($userID === '' || $offerCode === '' || $paymentRef === '') {
    log_line('Missing required metadata. userID=' . $userID . ', offerCode=' . $offerCode . ', paymentRef=' . $paymentRef);
    send_json(['ok' => false, 'error' => 'Missing required metadata'], 400);
}

$balanceDelta = compute_balance_delta($offerCode, $amountField);
if ($balanceDelta <= 0) {
    log_line('Unable to compute balance delta for offerCode=' . $offerCode);
    send_json(['ok' => false, 'error' => 'Invalid amount/offer code'], 400);
}

$timestamp = (string)time();
$signaturePayload = implode('|', [
    $userID,
    $postID,
    $offerCode,
    number_format($balanceDelta, 2, '.', ''),
    $paymentRef,
    $timestamp,
]);
$signature = hash_hmac('sha256', $signaturePayload, INTERNAL_SIGNING_SECRET);

$postData = [
    'userID' => $userID,
    'postID' => $postID,
    'offerCode' => $offerCode,
    'balance_delta' => number_format($balanceDelta, 2, '.', ''),
    'payment_ref' => $paymentRef,
    'event_provider' => 'whop',
    'event_timestamp' => $timestamp,
    'event_signature' => $signature,
    // Legacy compatibility token (existing handlebuy.php path).
    'token' => HANDLEBUY_TOKEN,
];

$ch = curl_init(HANDLEBUY_URL);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$handlebuyResponse = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError !== '') {
    log_line('handlebuy curl error: ' . $curlError);
    send_json(['ok' => false, 'error' => 'handlebuy request failed'], 500);
}

log_line('Fulfilled paymentRef=' . $paymentRef . '; handlebuyStatus=' . $httpCode . '; response=' . (string)$handlebuyResponse);

send_json([
    'ok' => true,
    'payment_ref' => $paymentRef,
    'handlebuy_status' => $httpCode,
]);
