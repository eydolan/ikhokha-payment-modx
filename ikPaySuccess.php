<?php

/*once payment comes back successfully from gateway 
do additional stuff in modx so ie delivering messages to the person who bought stuff 
and modfing modx content

*/

$cacheKey = 'temp_paylinkID';
$cacheOptions = [xPDO::OPT_CACHE_KEY => 'custom_cache'];

$modx->getService('mail', 'mail.modPHPMailer');
$paylinkID = $modx->cacheManager->get($cacheKey, $cacheOptions);
//$modx->log(modX::LOG_LEVEL_ERROR, "Paylink ID: '$paylinkID'");
//$paylinkID = "xx";

if ($paylinkID) {
    // Get GET parameters and fix 'amp;' prefixes
    $params = $modx->request->parameters['GET'];
    $fixedParams = [];
    foreach ($params as $key => $value) {
        $fixedKey = (substr($key, 0, 4) === 'amp;') ? substr($key, 4) : $key;
        $fixedParams[$fixedKey] = $value;
    }

    // Assign values with fallback
    $transactionId = $fixedParams['transactionId'] ?? '';
    $email = $fixedParams['email'] ?? '';
    $phone = $fixedParams['phone'] ?? '';
    $name = $fixedParams['name'] ?? 'Anonymous';
    $option = $fixedParams['option'] ?? '';
    $amount = $fixedParams['amount'] ?? '';

    $email = $email ?: "$phone@inkosiconnect.co.za";
/*
    $modx->log(modX::LOG_LEVEL_ERROR, "Fixed Params: " . print_r($fixedParams, true));
    $modx->log(modX::LOG_LEVEL_ERROR, "Email: '$email'");
    $modx->log(modX::LOG_LEVEL_ERROR, "Name: '$name'");
    $modx->log(modX::LOG_LEVEL_ERROR, "Phone: '$phone'");
    $modx->log(modX::LOG_LEVEL_ERROR, "Option: '$option'");
    $modx->log(modX::LOG_LEVEL_ERROR, "Amount: '$amount'");
*/
    // Default access code
    $accessCode = ' Your WIFI voucher could not be retrieved please contact support. ';
    $resourceId = 40;

    $resource = $modx->getObject('modResource', $resourceId);
    if (!$resource) {
        $modx->log(modX::LOG_LEVEL_ERROR, "Failed to load resource with ID: $resourceId");
    } else {
        for ($i = 1; $i <= 10; $i++) {
            if ($option === "option$i") {
                $tvName = "option$i";
                $optionTV = $resource->getTVValue($tvName);
                
                if ($optionTV !== null && $optionTV !== '') {
                    $lines = explode("\n", $optionTV);
                    $availableCode = false;
                    $codeIndex = -1;

                    foreach ($lines as $index => $line) {
                        $trimmedLine = trim($line);
                        if ($trimmedLine && strpos($trimmedLine, 'USED') !== 0) {
                            $accessCode = $trimmedLine;
                            $codeIndex = $index;
                            $availableCode = true;
                            break;
                        }
                    }

                    if ($availableCode) {
                        unset($lines[$codeIndex]);
                        $remainingContent = implode("\n", array_filter($lines, 'trim'));
                        $datetime = date('Y-m-d H:i:s');
                        $newEntry = "USED - $accessCode - $transactionId - $name - $phone - $amount - $datetime";
                        $updatedContent = $remainingContent . ($remainingContent ? "\n" : "") . $newEntry;

                        $resource->setTVValue($tvName, $updatedContent);
                        $resource->save();
                       // $modx->log(modX::LOG_LEVEL_INFO, "Access code '$accessCode' assigned and logged to $tvName");
                    } else {
                        $modx->log(modX::LOG_LEVEL_ERROR, "No unused codes found in $tvName TV");
                        sendAdminEmail($modx, $tvName, $option, $transactionId, $name, $email, $phone, $amount);
                    }
                } else {
                    $modx->log(modX::LOG_LEVEL_ERROR, "$tvName TV is empty or not set");
                    sendAdminEmail($modx, $tvName, $option, $transactionId, $name, $email, $phone, $amount);
                }
                break;
            }
        }
    }

    $subject = 'Your WiFi Access Code';
    $message = $modx->getChunk('ikEmailCodeTpl', [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'amount' => $amount,
        'transactionId' => $transactionId,
        'accessCode' => $accessCode
    ]);

    if (!$message) {
        $modx->log(modX::LOG_LEVEL_ERROR, "Failed to load chunk 'ikEmailCodeTpl'");
        return "$email$phone$name Error: Email template not found.";
    }

    $modx->mail->set(modMail::MAIL_BODY, nl2br($message));
    $modx->mail->set(modMail::MAIL_FROM, 'no-reply@xxxxxxxxx.co.za');
    $modx->mail->set(modMail::MAIL_FROM_NAME, 'somehname');
    $modx->mail->set(modMail::MAIL_SUBJECT, $subject);
    $modx->mail->address('to', $email);
    $modx->mail->setHTML(true);

    if (!$modx->mail->send()) {
        $errorInfo = $modx->mail->mailer->ErrorInfo;
        $modx->log(modX::LOG_LEVEL_ERROR, "Error sending email to user: $errorInfo");
        return "$email - $phone - $name Error sending email: $errorInfo";
    }

    $modx->mail->reset();

    $smsEmail = "$phone@winsms.net"; 
    $smsMessage = "Your WiFi access code is $accessCode. Ref#: $transactionId";

    $modx->mail->set(modMail::MAIL_BODY, $smsMessage);
    $modx->mail->set(modMail::MAIL_FROM, 'testestsset');
    $modx->mail->set(modMail::MAIL_SUBJECT, ''); 
    $modx->mail->address('to', $smsEmail);
    $modx->mail->setHTML(false); 

    if (!$modx->mail->send()) {
        $errorInfo = $modx->mail->mailer->ErrorInfo;
        $modx->log(modX::LOG_LEVEL_ERROR, "Error sending SMS via email to $smsEmail: $errorInfo");
    } else {
        $modx->log(modX::LOG_LEVEL_INFO, "SMS sent successfully to $phone via email-to-SMS gateway");
    }

    $modx->cacheManager->delete($cacheKey, $cacheOptions);
  //  $modx->log(modX::LOG_LEVEL_ERROR, "Cache cleared for key: '$cacheKey'");
    $modx->mail->reset();
    return "<p>Thanks for using [[++site_name]] </p><p>Your WIFI access code is: <strong>". $accessCode ."</strong> it also has been sent to your phone via SMS and to your email. </p><p>Note: you access code only works on one device.</p>";
} else {
    return "No payment ID found. Please check your sms or email for the WIFI code. If you still have issue please ensure that the payment went through or contact support.";
}

// Function to send admin email
function sendAdminEmail($modx, $tvName, $option, $transactionId, $name, $email, $phone, $amount) {
    $adminEmail = 'email here';
    $adminSubject = 'Voucher Codes Exhausted';
    $adminMessage = "The system has run out of voucher codes for $tvName.\n\nDetails:\n- Option: $option\n- Transaction ID: $transactionId\n- User: $name ($email, $phone)\n- Amount: $amount\n- Date: " . date('Y-m-d H:i:s');
    
    $modx->mail->set(modMail::MAIL_BODY, nl2br($adminMessage));
    $modx->mail->set(modMail::MAIL_FROM, 'sms@inkosiconnect.co.za');
    $modx->mail->set(modMail::MAIL_FROM_NAME, 'inkosiconnect');
    $modx->mail->set(modMail::MAIL_SUBJECT, $adminSubject);
    $modx->mail->address('to', $adminEmail);
    $modx->mail->setHTML(true);
    
    if (!$modx->mail->send()) {
        $modx->log(modX::LOG_LEVEL_ERROR, "Failed to send admin notification: " . $modx->mail->mailer->ErrorInfo);
    }
    $modx->mail->reset();
}
