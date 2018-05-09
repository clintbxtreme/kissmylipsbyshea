<?php
require_once 'paypal_api_context.php';

$payer = new \PayPal\Api\Payer();
$payer->setPaymentMethod("paypal");

$saved_cart = tools::getCart();
$products = tools::getProducts($con, array_keys($saved_cart));
$shipping = isset($_POST['shipping']) ? tools::$shipping : 0;

if (!count($products)) {
    header('Location: cart.php?err=ip');
    exit(1);
}

$items = array();
$subtotal = 0;
foreach ($products as $p) {
    extract($p);
    $item = new \PayPal\Api\Item();
    $item->setName($product)
        ->setCurrency('USD')
        ->setQuantity($saved_cart[$id])
        ->setSku($id)
        ->setPrice($price);
    $items[] = $item;
    $subtotal += ($saved_cart[$id] * $price);
}

$itemList = new \PayPal\Api\ItemList();
$itemList->setItems($items);

$details = new \PayPal\Api\Details();
$details->setShipping($shipping)
    ->setTax($subtotal*tools::$tax)
    ->setSubtotal($subtotal);

$amount = new \PayPal\Api\Amount();
$amount->setCurrency("USD")
    ->setTotal($subtotal+($subtotal*tools::$tax)+$shipping)
    ->setDetails($details);

$transaction = new \PayPal\Api\Transaction();
$transaction->setAmount($amount)
    ->setItemList($itemList);

$baseUrl = tools::getBaseUrl();
$redirectUrls = new \PayPal\Api\RedirectUrls();
$redirectUrls->setReturnUrl("{$baseUrl}/callback.php?success=true")
    ->setCancelUrl("{$baseUrl}/callback.php?success=false");

$payment = new \PayPal\Api\Payment();
$payment->setIntent("sale")
    ->setPayer($payer)
    ->setRedirectUrls($redirectUrls)
    ->setTransactions(array($transaction));

try {
    $payment->create($apiContext);
} catch (Exception $ex) {
    tools::postToSlack("Payment Creation Failure! ```{$ex->getData()}```");
    header('Location: cart.php?err=pcf');
    exit(1);
}

$approvalUrl = $payment->getApprovalLink();
header("Location: {$approvalUrl}");