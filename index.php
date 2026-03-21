<?php

declare(strict_types=1);

const SUPPORTED_PRICES = [14, 17, 36, 65, 105];
const MAX_WEBHOOK_AGE_SECONDS = 300;

$action = $_GET['action'] ?? 'checkout';

try {
    switch ($action) {
        case 'webhook':
            handleWebhook();
            break;
        case 'return':
            handleReturnPage();
            break;
        case 'checkout':
        default:
            handleCheckout();
            break;
    }
} catch (Throwable $exception) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Error: ' . $exception->getMessage();
}

function handleCheckout(): void
{
    $userId = trim((string) ($_GET['userID'] ?? ''));
    $postId = trim((string) ($_GET['postID'] ?? ''));
    $price = parseSupportedPrice($_GET['price'] ?? null);

    if ($userId === '' || $price === null) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Missing or invalid parameters. Required: userID and price. Supported prices: " . implode(', ', SUPPORTED_PRICES);
        return;
    }

    $checkout = createWhopCheckoutConfiguration($userId, $postId !== '' ? $postId : null, $price);

    if (empty($checkout['purchase_url'])) {
        throw new RuntimeException('Whop response did not include purchase_url.');
    }

    header('Location: ' . $checkout['purchase_url'], true, 302);
    exit;
}

function handleReturnPage(): void
{
    $status = (string) ($_GET['status'] ?? 'unknown');
    $userId = trim((string) ($_GET['userID'] ?? ''));
    $postId = trim((string) ($_GET['postID'] ?? ''));
    $price = parseSupportedPrice($_GET['price'] ?? null);
    $paymentId = trim((string) ($_GET['paymentId'] ?? ''));
    $sessionId = trim((string) ($_GET['sessionId'] ?? ''));

    $message = 'We are waiting for the Whop webhook to confirm the payment.';

    if ($status === 'success' && $userId !== '' && $price !== null) {
        try {
            notifyExternalWebsite([
                'event' => 'payment.succeeded',
                'source' => 'checkout_return',
                'paymentId' => $paymentId !== '' ? $paymentId : null,
                'checkoutSessionId' => $sessionId !== '' ? $sessionId : null,
                'userId' => $userId,
                'postId' => $postId !== '' ? $postId : null,
                'price' => $price,
            ]);

            $message = 'The external website was notified from the return page.';
        } catch (Throwable $exception) {
            $message = 'Return-page notification failed: ' . $exception->getMessage();
        }
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Checkout result</title></head><body>';
    echo '<h1>' . htmlspecialchars($status === 'success' ? 'Payment received' : 'Checkout not completed', ENT_QUOTES, 'UTF-8') . '</h1>';
    echo '<p>Status: ' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p>The server-to-server webhook is still the main confirmation path for delivery.</p>';
    echo '</body></html>';
}

function handleWebhook(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo 'Method not allowed';
        return;
    }

    $rawBody = file_get_contents('php://input');
    if ($rawBody === false) {
        throw new RuntimeException('Unable to read webhook body.');
    }

    if (!verifyWhopWebhookSignature($rawBody)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Invalid webhook signature.'], JSON_THROW_ON_ERROR);
        return;
    }

    $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);

    if (($payload['type'] ?? '') === 'payment.succeeded') {
        $data = $payload['data'] ?? [];
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];

        notifyExternalWebsite([
            'event' => 'payment.succeeded',
            'source' => 'whop_webhook',
            'paymentId' => $data['id'] ?? null,
            'checkoutSessionId' => $data['checkout_session_id'] ?? null,
            'userId' => isset($metadata['userId']) ? (string) $metadata['userId'] : '',
            'postId' => isset($metadata['postId']) && $metadata['postId'] !== '' ? (string) $metadata['postId'] : null,
            'price' => isset($metadata['price']) ? (int) $metadata['price'] : 0,
            'rawWhopPayload' => $data,
        ]);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
}

function createWhopCheckoutConfiguration(string $userId, ?string $postId, int $price): array
{
    $planId = getPlanIdForPrice($price);
    $baseUrl = rtrim(getEnvOrDefault('APP_BASE_URL', getCurrentBaseUrl()), '/');
    $returnUrl = $baseUrl . '/index.php?action=return&status=success&userID=' . rawurlencode($userId) . '&price=' . $price;

    if ($postId !== null) {
        $returnUrl .= '&postID=' . rawurlencode($postId);
    }

    $response = sendJsonRequest(
        'POST',
        rtrim(getEnvOrDefault('WHOP_API_BASE_URL', 'https://api.whop.com/api/v5'), '/') . '/checkout_configurations',
        [
            'Authorization: Bearer ' . getRequiredEnv('WHOP_API_KEY'),
            'Content-Type: application/json',
        ],
        [
            'company_id' => getRequiredEnv('WHOP_COMPANY_ID'),
            'plan' => ['id' => $planId],
            'metadata' => [
                'userId' => $userId,
                'postId' => $postId ?? '',
                'price' => (string) $price,
            ],
            'redirect_url' => $returnUrl,
            'source_url' => $baseUrl . '/index.php',
        ]
    );

    return $response;
}

function notifyExternalWebsite(array $payload): void
{
    $headers = ['Content-Type: application/json'];
    $token = getenv('EXTERNAL_DELIVERY_WEBHOOK_TOKEN');

    if ($token !== false && $token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    sendJsonRequest(
        'POST',
        getRequiredEnv('EXTERNAL_DELIVERY_WEBHOOK_URL'),
        $headers,
        $payload
    );
}

function sendJsonRequest(string $method, string $url, array $headers, array $payload): array
{
    $ch = curl_init($url);

    if ($ch === false) {
        throw new RuntimeException('Unable to initialize curl.');
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HEADER => true,
    ]);

    $rawResponse = curl_exec($ch);
    if ($rawResponse === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('HTTP request failed: ' . $error);
    }

    $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $body = substr($rawResponse, $headerSize);
    $decoded = $body !== '' ? json_decode($body, true) : [];

    if ($statusCode < 200 || $statusCode >= 300) {
        throw new RuntimeException('HTTP ' . $statusCode . ' response from ' . $url . ': ' . $body);
    }

    return is_array($decoded) ? $decoded : [];
}

function verifyWhopWebhookSignature(string $rawBody): bool
{
    $secret = getRequiredEnv('WHOP_WEBHOOK_SECRET');
    $webhookId = getHeaderValue('webhook-id');
    $timestamp = getHeaderValue('webhook-timestamp');
    $signatureHeader = getHeaderValue('webhook-signature');

    if ($webhookId === null || $timestamp === null || $signatureHeader === null) {
        return false;
    }

    if (!ctype_digit($timestamp)) {
        return false;
    }

    if (abs(time() - (int) $timestamp) > MAX_WEBHOOK_AGE_SECONDS) {
        return false;
    }

    $expected = base64_encode(hash_hmac('sha256', $webhookId . '.' . $timestamp . '.' . $rawBody, $secret, true));
    $signatures = parseSignatureHeader($signatureHeader);

    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }

    return false;
}

function parseSignatureHeader(string $signatureHeader): array
{
    $entries = preg_split('/[\s,]+/', trim($signatureHeader)) ?: [];
    $signatures = [];

    foreach ($entries as $entry) {
        if ($entry === '') {
            continue;
        }

        if (str_starts_with($entry, 'v1=')) {
            $entry = substr($entry, 3);
        } elseif (str_starts_with($entry, 'v1,')) {
            $entry = substr($entry, 3);
        }

        $signatures[] = $entry;
    }

    return $signatures;
}

function parseSupportedPrice(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    $price = filter_var($value, FILTER_VALIDATE_INT);
    if ($price === false) {
        return null;
    }

    return in_array($price, SUPPORTED_PRICES, true) ? $price : null;
}

function getPlanIdForPrice(int $price): string
{
    $map = [
        14 => getRequiredEnv('WHOP_PLAN_14'),
        17 => getRequiredEnv('WHOP_PLAN_17'),
        36 => getRequiredEnv('WHOP_PLAN_36'),
        65 => getRequiredEnv('WHOP_PLAN_65'),
        105 => getRequiredEnv('WHOP_PLAN_105'),
    ];

    if (!isset($map[$price]) || $map[$price] === '') {
        throw new RuntimeException('Missing plan mapping for price ' . $price);
    }

    return $map[$price];
}

function getRequiredEnv(string $name): string
{
    $value = getenv($name);

    if ($value === false || $value === '') {
        throw new RuntimeException('Missing required environment variable: ' . $name);
    }

    return $value;
}

function getEnvOrDefault(string $name, string $default): string
{
    $value = getenv($name);
    return ($value === false || $value === '') ? $default : $value;
}

function getCurrentBaseUrl(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function getHeaderValue(string $name): ?string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $value = $_SERVER[$serverKey] ?? null;
    return is_string($value) && $value !== '' ? $value : null;
}
