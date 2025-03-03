<?php

/*
this is a modx snippet that initialises the payment gateways see https://dev.ikhokha.com/overview

it takes the entries from a formit hook called ikPayFormHandler

*/

//$modx->log(modX::LOG_LEVEL_ERROR, "Parameters - Amount: [[+amount]], Email: [[+email]], Phone: [[+phone]], Name: [[+name]] [[+opotion]]");

$appKey = $modx->getOption('ikpay_app_key', null, 'APP_KEY');
$appSecret = $modx->getOption('ikpay_app_secret', null, 'APP_SECRET');
$endpoint = 'https://api.ikhokha.com/public-api/v1/api/payment';

$amount = floatval($scriptProperties['amount']);

$phone = $scriptProperties['phone'];
$option = isset($scriptProperties['option']) ? $scriptProperties['option'] : 'default';
$sanitizedPhone = preg_replace('/[^0-9]/', '', $phone);
$externalTransactionID = "P_" . $sanitizedPhone . "_T_" . time();
$name = $scriptProperties['name'] ?: 'anonymous';
$email = $scriptProperties['email']?: $sanitizedPhone.'@emaildomainxxx.co.za';

$successUrl = $modx->makeUrl(11, '', [
    'transactionId' => $externalTransactionID,
    'email' => $email,
    'amount' => $amount,
    'phone' => $phone,
    'name' => $name,
    'option' => $option
], 'full');
$callbackUrl = $modx->makeUrl(11, '', '', 'full');
$failureUrl = $modx->makeUrl(39, '', '', 'full');
$cancelUrl = $modx->makeUrl(9, '', '', 'full');
$requesterUrl = $modx->makeUrl(38, '', '', 'full');
/*
$modx->log(modX::LOG_LEVEL_ERROR, "Sanitized Phone: $sanitizedPhone");
$modx->log(modX::LOG_LEVEL_ERROR, "App Key: $appKey");
$modx->log(modX::LOG_LEVEL_ERROR, "App Secret (partial): " . substr($appSecret, 0, 4) . "...");
$modx->log(modX::LOG_LEVEL_ERROR, "Endpoint: $endpoint");
$modx->log(modX::LOG_LEVEL_ERROR, "Success URL: $successUrl");
$modx->log(modX::LOG_LEVEL_ERROR, "Failure URL: $failureUrl");
$modx->log(modX::LOG_LEVEL_ERROR, "Requester URL: $requesterUrl");
$modx->log(modX::LOG_LEVEL_ERROR, "Cancel URL: $cancelUrl");
*/
if ($amount <= 0) {
    $errorMsg = 'Invalid amount provided';
    return $errorMsg;

   // $modx->log(modX::LOG_LEVEL_ERROR, "Validation failed: $errorMsg");

}

$requestBody = [
    "entityID" => "inkosiconnectWebsite",
    "amount" => (int)($amount * 100),
    "currency" => "ZAR",
    "requesterUrl" => $requesterUrl,
    "description" => "Payment from " . $name,
    "paymentReference" => "ORDER_" . $externalTransactionID,
    "mode" => "test",
    "externalTransactionID" => $externalTransactionID,
    "urls" => [
        "callbackUrl" => $callbackUrl,
        "successPageUrl" => $successUrl,
        "failurePageUrl" => $failureUrl,
        "cancelUrl" => $cancelUrl
    ]
];

$stringifiedBody = json_encode($requestBody);

//$modx->log(modX::LOG_LEVEL_ERROR, "Request Body prepared: $stringifiedBody");

function escapeString($str) {
    $escaped = preg_replace(['/[\\"\'\"]/u', '/\x00/'], ['\\\\$0', '\\0'], (string)$str);
    $cleaned = str_replace('\/', '/', $escaped);
    return $cleaned;
}

function createPayloadToSign($urlPath, $body) {
    $parsedUrl = parse_url($urlPath);
    $basePath = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
    if (!$basePath) {
        return false;

    //    $modx->log(modX::LOG_LEVEL_ERROR, "No path present in URL: $urlPath");

    }
    $payload = $basePath . $body;
    $escapedPayloadString = escapeString($payload);
    return $escapedPayloadString;
}
$payloadToSign = createPayloadToSign($endpoint, $stringifiedBody);

if ($payloadToSign === false) {
    $errorMsg = "Error: Unable to create payload due to missing URL path";
    return $errorMsg;

  //  $modx->log(modX::LOG_LEVEL_ERROR, $errorMsg);

}

$ikSign = hash_hmac('sha256', $payloadToSign, $appSecret);
//$modx->log(modX::LOG_LEVEL_ERROR, "Payload to Sign: $payloadToSign");
//$modx->log(modX::LOG_LEVEL_ERROR, "Signature generated: $ikSign");

$ch = curl_init($endpoint);
//$modx->log(modX::LOG_LEVEL_ERROR, "cURL initialized with endpoint: $endpoint");

curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
//$modx->log(modX::LOG_LEVEL_ERROR, "cURL option set: CUSTOMREQUEST = POST");

curl_setopt($ch, CURLOPT_POSTFIELDS, $stringifiedBody);
//$modx->log(modX::LOG_LEVEL_ERROR, "cURL option set: POSTFIELDS = $stringifiedBody");

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//$modx->log(modX::LOG_LEVEL_ERROR, "cURL option set: RETURNTRANSFER = true");

$headers = [
    'Content-Type: application/json',
    'IK-APPID: ' . $appKey, 
    'IK-SIGN: ' . $ikSign,
    'Accept: application/json'
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

//$modx->log(modX::LOG_LEVEL_ERROR, "cURL option set: HTTPHEADER = " . implode(', ', $headers));
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

//$modx->log(modX::LOG_LEVEL_ERROR, "cURL executed - Response: " . ($response !== false ? $response : 'false'));
//$modx->log(modX::LOG_LEVEL_ERROR, "cURL HTTP Code: $httpCode");
//$modx->log(modX::LOG_LEVEL_ERROR, "cURL Error: " . ($error ?: 'none'));
//$modx->log(modX::LOG_LEVEL_ERROR, "cURL closed");

curl_close($ch);

if ($response === false || !empty($error)) {
    $errorMsg = 'cURL error: ' . $error;
    return $errorMsg;

    $modx->log(modX::LOG_LEVEL_ERROR, $errorMsg);
}

$responseData = json_decode($response, true);

//$modx->log(modX::LOG_LEVEL_ERROR, "Response decoded: " . (is_array($responseData) ? print_r($responseData, true) : 'Invalid JSON'));

if ($httpCode === 200 && isset($responseData['paylinkUrl'])) {
    $cacheKey = 'temp_paylinkID';
    $cacheOptions = [xPDO::OPT_CACHE_KEY => 'custom_cache']; 
    $paylinkID = $modx->cacheManager->get($cacheKey, $cacheOptions);

    $modx->cacheManager->set($cacheKey, $responseData['paylinkID'], 600, $cacheOptions);

    if ($paylinkID) {

  //      $modx->log(modX::LOG_LEVEL_INFO, "Retrieved PaylinkID: " . $paylinkID);

    }

  //  $modx->log(modX::LOG_LEVEL_ERROR, "Preparing redirect to: " . $responseData['paylinkUrl']);

    $modx->sendRedirect($responseData['paylinkUrl']);

  //  $modx->log(modX::LOG_LEVEL_ERROR, "Redirect executed (should not reach here)");

    return "Redirected to: " . $responseData['paylinkUrl'];
} 
else {
    $errorMsg = 'Payment initiation failed. HTTP Code: ' . $httpCode . ' Response: ' . (is_array($responseData) ? print_r($responseData, true) : $response);

    $modx->log(modX::LOG_LEVEL_ERROR, $errorMsg);

    return $errorMsg;
}
