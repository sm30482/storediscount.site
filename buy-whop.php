<?php
/**
 * Unified Whop checkout launcher for both single and bundle purchases.
 *
 * Query params:
 * - userID (required)
 * - postID (optional)
 * - mode = single|bundle (optional, defaults to bundle)
 * - postPrice (optional, used by single mode prefill)
 */

const WHOP_API_BASE = 'https://api.whop.com/api/v2';
const WHOP_BUY_LOG_FILE = 'whop_buy_debug.txt';
if (file_exists(__DIR__ . '/whop-config.php')) {
    require_once __DIR__ . '/whop-config.php';
}

function json_response(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function buy_log(string $message, array $context = []): void {
    $safeContext = [];
    foreach ($context as $key => $value) {
        if ($key === 'apiKey' || stripos((string)$key, 'secret') !== false) {
            continue;
        }
        $safeContext[$key] = $value;
    }

    file_put_contents(
        WHOP_BUY_LOG_FILE,
        '[' . date('Y-m-d H:i:s') . '] ' . $message . ' ' . json_encode($safeContext) . "\n",
        FILE_APPEND
    );
}

function create_whop_checkout_session(array $params): array {
    $apiKey = defined('WHOP_API_KEY') ? WHOP_API_KEY : '';
    $companyId = defined('WHOP_COMPANY_ID') ? WHOP_COMPANY_ID : '';

    if ($apiKey === '') {
        return [
            'ok' => false,
            'error' => 'Missing Whop API key. Set WHOP_API_KEY or WHOP_API_KEY in whop-config.php.',
        ];
    }

    $amount = (float)$params['amount'];
    $currency = $params['currency'];

    $utm = [
        'source' => 'storediscounts',
        'medium' => 'iframe',
        'campaign' => $params['mode'],
        'content' => $params['offerCode'],
        'term' => $params['paymentRef'],
    ];

    $metadata = [
        'userID' => (string)$params['userID'],
        'postID' => (string)$params['postID'],
        'offerCode' => (string)$params['offerCode'],
        'paymentRef' => (string)$params['paymentRef'],
        'amount' => (string)$amount,
        'mode' => (string)$params['mode'],
    ];

    $payload = [
        'mode' => 'payment',
        'plan' => [
            'plan_type' => 'one_time',
            'initial_price' => $amount,
            'currency' => $currency,
            'name' => 'Storediscount ' . $params['offerCode'],
        ],
        'metadata' => $metadata,
        // Kept for compatibility with support guidance about SDK dynamic UTMs.
        'utm' => $utm,
    ];
    if ($companyId !== '') {
        $payload['company_id'] = $companyId;
    }

    // Whop v5 endpoint uses underscore naming.
    $ch = curl_init(WHOP_API_BASE . '/checkout_configurations');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_USERAGENT, 'storediscount-whop-checkout/1.0');

    $raw = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $curlError !== '') {
        buy_log('Whop API cURL failure', [
            'mode' => $params['mode'],
            'offerCode' => $params['offerCode'],
            'httpCode' => $httpCode,
            'curlError' => $curlError,
        ]);
        return [
            'ok' => false,
            'error' => 'Whop API request failed: ' . $curlError,
        ];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        buy_log('Whop API invalid JSON response', [
            'mode' => $params['mode'],
            'offerCode' => $params['offerCode'],
            'httpCode' => $httpCode,
            'raw' => substr((string)$raw, 0, 1000),
        ]);
        $debugResponse = trim((string)$raw) === '' ? '<empty body>' : (string)$raw;
        return [
            'ok' => false,
            'error' => 'Invalid response from Whop API.',
            'raw' => $raw,
            'debug_whop_response' => $debugResponse,
            'status' => $httpCode,
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        buy_log('Whop API non-2xx response', [
            'mode' => $params['mode'],
            'offerCode' => $params['offerCode'],
            'httpCode' => $httpCode,
            'response' => $decoded,
        ]);
        return [
            'ok' => false,
            'error' => 'Whop API error.',
            'status' => $httpCode,
            'response' => $decoded,
        ];
    }

    $checkoutUrl = $decoded['purchase_url'] ?? ($decoded['data']['purchase_url'] ?? null);
    if (!$checkoutUrl) {
        buy_log('Whop API missing purchase_url', [
            'mode' => $params['mode'],
            'offerCode' => $params['offerCode'],
            'httpCode' => $httpCode,
            'response' => $decoded,
        ]);
        return [
            'ok' => false,
            'error' => 'Whop response did not include purchase_url.',
            'response' => $decoded,
        ];
    }

    return [
        'ok' => true,
        'checkoutUrl' => $checkoutUrl,
        'sessionId' => $decoded['id'] ?? null,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create-session') {
    $userID = trim((string)($_POST['userID'] ?? ''));
    $postID = trim((string)($_POST['postID'] ?? ''));
    $offerCode = trim((string)($_POST['offerCode'] ?? ''));
    $mode = trim((string)($_POST['mode'] ?? 'bundle'));
    $currency = strtolower(trim((string)($_POST['currency'] ?? 'eur')));
    $amountRaw = $_POST['amount'] ?? null;

    if ($userID === '') {
        json_response(['ok' => false, 'error' => 'userID is required.'], 400);
    }

    if ($offerCode === '') {
        json_response(['ok' => false, 'error' => 'offerCode is required.'], 400);
    }

    if (!in_array($mode, ['single', 'bundle'], true)) {
        json_response(['ok' => false, 'error' => 'Invalid mode.'], 400);
    }

    if (!is_numeric($amountRaw)) {
        json_response(['ok' => false, 'error' => 'amount must be numeric.'], 400);
    }

    $amount = (float)$amountRaw;
    if ($amount <= 0) {
        json_response(['ok' => false, 'error' => 'amount must be greater than 0.'], 400);
    }

    $paymentRef = 'sd_' . bin2hex(random_bytes(12));

    $result = create_whop_checkout_session([
        'userID' => $userID,
        'postID' => $postID,
        'offerCode' => $offerCode,
        'mode' => $mode,
        'currency' => $currency,
        'amount' => $amount,
        'paymentRef' => $paymentRef,
    ]);

    json_response($result, $result['ok'] ? 200 : 500);
}

$userID = htmlspecialchars($_GET['userID'] ?? '');
if ($userID === '') {
    die('Error: userID is required.');
}

$postID = htmlspecialchars($_GET['postID'] ?? '');
$mode = $_GET['mode'] ?? '';
if ($mode === '') {
    // Backward-compatible behavior with buy.php:
    // if individual context is present, render single purchase button.
    if (isset($_GET['postPrice'])) {
        $mode = 'single';
    } else {
        $mode = 'bundle';
    }
}
if (!in_array($mode, ['single', 'bundle'], true)) {
    $mode = 'bundle';
}
$defaultPostPrice = htmlspecialchars($_GET['postPrice'] ?? '14');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Assistant:wght@200..800&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: Assistant, sans-serif; margin: 0 !important; }
        .status { text-align: center; font-size: 14px; color: #555; margin-top: 10px; min-height: 18px; }
        .user-submit {
            font-family: Assistant, sans-serif;
            font-weight: 400;
            display: inline-block;
            padding: 0 20px;
            cursor: pointer;
            line-height: 45px;
            background-color: #12729e;
            height: 45px;
            border-radius: 10px;
            text-align: center;
            font-size: x-large;
            color: #e4f6fa;
            margin: 0;
            border: 1px solid #0269aa;
        }
        .user-submit:hover { background-color: #63b9e9; border: 1px solid #c7d8ec; color: white; }
        .ticket-option { margin: 15px 0; cursor: pointer; }
        .option-box {
            border: 2px solid #ddd;
            padding: 10px;
            border-radius: 8px;
            background-color: #fffcf5;
            width: 315px;
            margin: 0 auto;
            text-align: center;
            transition: border-color .3s ease, background-color .3s ease;
        }
        .ticket-option.selected .option-box { border-color: #ffc570; background-color: #f8ffb9; }
        .main-option-side {
            text-align: left; font-size: x-large; width: 92px; display: inline-block;
            border-right: 2px dotted #ddd; color: #0771a5; line-height: 36px; height: 68px; padding-bottom: 4px;
        }
        .main-option-side>span>strong { font-size: 30px; }
        .medium-option-side {
            text-align: left; display: inline-block; border-right: 2px dotted #ddd; height: 72px; width: 123px;
        }
        .medium-option-side>ul>li>span>strong { font-size: 19px; }
        .price-option-side {
            text-align: right; display: inline-block; width: 82px; font-size: 47px;
            height: 68px; vertical-align: top; line-height: 62px; color: #0771a5;
        }
        ul { margin: 0; padding-inline-start: 20px; color: #0771a5; }
        .strikethrough { text-decoration: line-through; color: #ff3285; font-style: italic; }
        .container { text-align: center; }
        .spacer { height: 8px; }
    </style>
</head>
<body>
<div class="container" id="checkout-container" data-mode="<?php echo htmlspecialchars($mode); ?>">
    <?php if ($mode === 'single'): ?>
        <div class="spacer"></div>
        <div class="user-submit"
             id="single-buy-btn"
             data-amount="<?php echo $defaultPostPrice; ?>"
             data-offer-code="single_<?php echo $defaultPostPrice; ?>"
             style="display:inline-block">
            Unlock · <?php echo $defaultPostPrice; ?> Tkts
        </div>
    <?php else: ?>
        <div class="ticket-option" data-offer-code="bundle_42" data-amount="42">
            <div class="option-box">
                <div class="main-option-side">
                    <span>Buy<strong> 42</strong></span>
                    <span>Tickets<strong></strong></span>
                </div>
                <div class="medium-option-side">
                    <ul>
                        <li><span class="strikethrough"><strong>€42</strong></span></li>
                        <li><span><strong>15%</strong> discount</span></li>
                        <li><span>Save <strong>€6</strong></span></li>
                    </ul>
                </div>
                <div class="price-option-side"><span><strong>€36</strong></span></div>
            </div>
        </div>

        <div class="ticket-option" data-offer-code="bundle_84" data-amount="84">
            <div class="option-box">
                <div class="main-option-side">
                    <span>Get<strong> 84</strong></span>
                    <span>Tickets<strong></strong></span>
                </div>
                <div class="medium-option-side">
                    <ul>
                        <li><span class="strikethrough"><strong>€84</strong></span></li>
                        <li><span><strong>22%</strong> discount</span></li>
                        <li><span>Save <strong>€19</strong></span></li>
                    </ul>
                </div>
                <div class="price-option-side"><span><strong>€65</strong></span></div>
            </div>
        </div>

        <div class="ticket-option" data-offer-code="bundle_140" data-amount="140">
            <div class="option-box">
                <div class="main-option-side">
                    <span>Get<strong> 140</strong></span>
                    <span>Tickets<strong></strong></span>
                </div>
                <div class="medium-option-side">
                    <ul>
                        <li><span class="strikethrough"><strong>€140</strong></span></li>
                        <li><span><strong>25%</strong> discount</span></li>
                        <li><span>Save <strong>€35</strong></span></li>
                    </ul>
                </div>
                <div class="price-option-side"><span><strong>€105</strong></span></div>
            </div>
        </div>
    <?php endif; ?>
    <div class="status" id="status"></div>
</div>

<script>
(function () {
    const userID = <?php echo json_encode($userID); ?>;
    const postID = <?php echo json_encode($postID); ?>;
    const mode = <?php echo json_encode($mode); ?>;

    function setStatus(text, isError) {
        const el = $('#status');
        el.text(text || '');
        el.css('color', isError ? '#b00020' : '#555');
    }

    function startCheckout(offerCode, amount) {
        setStatus('Preparing secure checkout…', false);

        const payload = {
            action: 'create-session',
            userID: userID,
            postID: postID,
            offerCode: offerCode,
            amount: amount,
            mode: mode,
            currency: 'eur'
        };

        $.post(window.location.href, payload)
            .done(function (res) {
                if (!res || !res.ok || !res.checkoutUrl) {
                    let message = (res && res.error) ? res.error : 'Unable to create checkout session.';
                    if (res && res.debug_whop_response) {
                        message += ' Debug: ' + String(res.debug_whop_response).substring(0, 400);
                    } else if (res && res.response) {
                        message += ' Debug: ' + JSON.stringify(res.response).substring(0, 400);
                    }
                    setStatus(message, true);
                    return;
                }

                const checkoutWindow = window.open(res.checkoutUrl, 'WhopCheckout', 'width=800,height=800');
                if (!checkoutWindow) {
                    setStatus('Popup blocked. Please allow popups and retry.', true);
                    return;
                }

                window.parent.postMessage('purchaseStarted', '*');
                setStatus('Checkout opened. Complete payment in the popup.', false);

                const checkWindowClosed = setInterval(function () {
                    if (checkoutWindow.closed) {
                        clearInterval(checkWindowClosed);
                        window.parent.postMessage('purchaseComplete', '*');
                        setStatus('Checkout closed. Processing payment…', false);
                    }
                }, 1000);
            })
            .fail(function (xhr) {
                let err = 'Failed to create checkout session.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.error) {
                    err = xhr.responseJSON.error;
                    if (xhr.responseJSON.debug_whop_response) {
                        err += ' Debug: ' + String(xhr.responseJSON.debug_whop_response).substring(0, 400);
                    } else if (xhr.responseJSON.response) {
                        err += ' Debug: ' + JSON.stringify(xhr.responseJSON.response).substring(0, 400);
                    }
                }
                setStatus(err, true);
            });
    }

    if (mode === 'single') {
        $('#single-buy-btn').on('click', function () {
            startCheckout($(this).data('offer-code'), $(this).data('amount'));
        });
    } else {
        $('.ticket-option').on('click', function () {
            $('.ticket-option').removeClass('selected');
            $(this).addClass('selected');
            startCheckout($(this).data('offer-code'), $(this).data('amount'));
        });
    }
})();
</script>
</body>
</html>
