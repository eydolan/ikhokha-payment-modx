<?php

/*used in formit as a hoo  e.g. 


[[!FormIt?
    &hooks=`ikPayFormHandler,FormItSaveForm`
    &submitVar=`paySubmit`
    &validate=`phone:required`
]]

<form method="post" action="[[~38]]">
    <label>Name (optional): <input type="text" name="name" value="[[!+fi.name]]"></label>
    <span class="error">[[!+fi.error.name]]</span><br>

    <label>Email (optional): <input type="email" name="email" value="[[!+fi.email]]"></label>
    <span class="error">[[!+fi.error.email]]</span><br>

    <label>Phone (required): <input type="text" name="phone" value="[[!+fi.phone]]" required></label>
    <span class="error">[[!+fi.error.phone]]</span><br>

    <input type="hidden" name="amount" value="10.00">
    <input type="hidden" name="option" value="option1">
    <span class="error">[[!+fi.error.amount]]</span><br>

    <input type="submit" name="paySubmit" value="Pay Now">
</form>
[[!+fi.error.payment]]
*/

function ikPayFormHandler($hook) {
    $modx = $hook->modx;

    //$modx->log(modX::LOG_LEVEL_ERROR, "Starting ikPayFormHandler execution");

    $name = $hook->getValue('name') ?: 'anonymous';
    $email = $hook->getValue('email');
    $phone = $hook->getValue('phone');
    $option = $hook->getValue('option');
    $amount = floatval($hook->getValue('amount'));

    if (empty($email)) {
        $email = $phone . '@somemeiaaddy.co.za';
    }

  //  $modx->log(modX::LOG_LEVEL_ERROR, "Form values - Name: $name, Email: $email, Phone: $phone, Amount: $amount");
 //   $modx->log(modX::LOG_LEVEL_ERROR, "Calling ikPayInitiate with parameters: amount=$amount, email=$email, phone=$phone, name=$name");

    $params = [
        'amount' => $amount,
        'email' => $email,
        'phone' => $phone,
        'name' => $name,
        'option' => $option
    ];
    $result = $modx->runSnippet('ikPayInitiate', $params);

 //   $modx->log(modX::LOG_LEVEL_ERROR, "ikPayInitiate returned: " . ($result ?: 'no result'));

    if (strpos($result, 'cURL error') === false && strpos($result, 'Payment initiation failed') === false) {
        return true;
    } else {
   //     $modx->log(modX::LOG_LEVEL_ERROR, "ikPayInitiate failed: $result");
        $hook->addError('payment', $result);
        return false;
    }
}

return ikPayFormHandler($hook);