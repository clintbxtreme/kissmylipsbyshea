<?php
$page_title="Complete Order";
require_once 'paypal_api_context.php';

if (isset($_SESSION['paymentId']) && isset($_SESSION['PayerID'])) {
    $payment = \PayPal\Api\Payment::get($_SESSION['paymentId'], $apiContext);
    $payerInfo = $payment->getPayer()->getPayerInfo();
    $email = $payerInfo->getEmail();
    $fname = $payerInfo->getFirstName();
    $lname = $payerInfo->getLastName();

    include 'header.php';
    echo <<<EOC
        <form class='form-validate' method="POST" action='pay.php'>
        <table class='table table-hover table-responsive table-bordered'>
            <tr>
                <td class='textAlignLeft'>First Name:</td>
                <td class='textAlignLeft'><input class='form-control' type='text' id='first_name' name='first_name' value='{$fname}'></td>
            </tr>
            <tr>
                <td class='textAlignLeft'>Last Name:</td>
                <td class='textAlignLeft'><input class='form-control' type='text' id='last_name' name='last_name' value='{$lname}'></td>
            </tr>
            <tr>
                <td class='textAlignLeft'>Email:</td>
                <td class='textAlignLeft'><input class='form-control' type='text' id='email' name='email' value='{$email}'></td>
            </tr>
            <tr>
                <td class='textAlignLeft'>Party Order:</td>
                <td class='textAlignLeft'>
                    <label class='switch'>
                        <input type='checkbox' name='party' id='party' value='1'>
                        <div class='slider round'></div>
                    </label>
                </td>
            </tr>
            <tr>
                <td>
                    <button type='submit' class='btn btn-pink disable-on-submit'>
                        <span class='glyphicon glyphicon-usd'></span> Pay
                    </button>
                </td>
                <td></td>
            </tr>
        </table>
        </form>
EOC;

    include 'footer.php';
} else {
    header('Location: error.php');
    exit(1);
}