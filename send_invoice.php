<?php
require_once 'paypal_api_context.php';

$invoice = new \PayPal\Api\Invoice();

$saved_cart = tools::getCart();
$products = tools::getProducts($con, array_keys($saved_cart));
$customer_id = tools::setValidId('customer');
$first_name = tools::setValidName('first_name');
$last_name = tools::setValidName('last_name');
$email = tools::setValidEmail('email');
if ((!$customer_id && (!$first_name || !$last_name || !$email)) || !count($products)) {
    header('Location: cart.php?err=ip');
    exit(1);
}
if ($customer_id && (!$first_name || !$last_name || !$email)) {
    $customer = tools::getCustomer($con, $customer_id);
    extract($customer[0]);
}
$tax = (isset($_POST['tax'])) ? number_format($_POST['tax'], 2) : tools::$tax*100;
$shipping = isset($_POST['shipping']) ? tools::$shipping : 0;
$party = intval(isset($_POST['party']));
$percent_off = $amount_off = 0;

$merchantInfo = new \PayPal\Api\MerchantInfo();
use PayPal\Api\Templates;
$templates = Templates::getAll(array(), $apiContext);
foreach ($templates->getTemplates() as $template) {
    if ($template->getDefault()) {
        $merchantInfo = $template->getTemplateData()->getMerchantInfo();
        if ($merchantInfo->getPhone() && $merchantInfo->getPhone()->getNationalNumber()) {
            $merchantInfo->getPhone()->setCountryCode('1');
        }
    }
}

$invoice
    ->setMerchantInfo($merchantInfo)
    ->setShippingCost(new \PayPal\Api\ShippingCost())
    ->setBillingInfo(array(new \PayPal\Api\BillingInfo()))
    ->setPaymentTerm(new \PayPal\Api\PaymentTerm())
    ->setLogoUrl(tools::$options['invoice_logo']);

$currency = new \PayPal\Api\Currency();
$currency->setCurrency("USD")
    ->setValue($shipping);
$invoice->getShippingCost()
    ->setAmount($currency);

$billing = $invoice->getBillingInfo();
$billing[0]
    ->setFirstName($first_name)
    ->setLastName($last_name)
    ->setEmail($email);

if ($tax > 0) {
    $taxes = new \PayPal\Api\Tax();
    $taxes->setPercent($tax)
        ->setName("Taxes & Fees");
}

$items_ordered = $items = array();
$subtotal = 0;
foreach ($products as $p) {
    extract($p);
    $item_ordered = array('id'=>$id,'quantity'=>$saved_cart[$id]);
    $item = new \PayPal\Api\InvoiceItem();
    $item->setName($product)
        ->setQuantity($saved_cart[$id])
        ->setUnitOfMeasure('QUANTITY')
        ->setUnitPrice(new \PayPal\Api\Currency());
    if ($tax > 0) {
        $item->setTax($taxes);
    }
    if ($_POST['item_discount'][$id]['value']) {
        $item_discount = new \PayPal\Api\Cost();
        $discount = number_format($_POST['item_discount'][$id]['value'], 2);
        if (isset($_POST['item_discount'][$id]['type'])) {
            $item_discount->setPercent($discount);
            $item_ordered['percent_off'] = $discount;
        } else {
            $currency = new \PayPal\Api\Currency();
            $currency->setCurrency("USD")
                ->setValue($discount);
            $item_discount->setAmount($currency);
            $item_ordered['amount_off'] = $discount;
        }
        $item->setDiscount($item_discount);
    }

    $item->getUnitPrice()
        ->setCurrency('USD')
        ->setValue($price);

    $items[] = $item;
    $items_ordered[] = $item_ordered;
    $subtotal += ($saved_cart[$id] * $price);
}

$invoice->setItems($items);

if ($_POST['total_discount']) {
    $total_discount = new \PayPal\Api\Cost();
    $discount = number_format($_POST['total_discount'], 2);
    if (isset($_POST['total_discount_type'])) {
        $total_discount->setPercent($discount);
        $percent_off = $discount;
    } else {
        $currency = new \PayPal\Api\Currency();
        $currency->setCurrency("USD")
            ->setValue($discount);
        $total_discount->setAmount($currency);
        $amount_off = $discount;
    }
    $invoice->setDiscount($total_discount);
}

$invoice->getPaymentTerm()
    ->setTermType("DUE_ON_RECEIPT");

try {
    $invoice->create($apiContext);
    $invoice_id = $invoice->getId();
} catch (Exception $ex) {
    header("Content-type: text/plain");
    print_r($ex);
    exit(1);
    tools::postToSlack("Invoice Creation Failure! ```{$ex->getData()}```");
    header('Location: cart.php?err=icf');
    exit(1);
}

try {
    $sendStatus = $invoice->send($apiContext);
} catch (Exception $ex) {
    tools::postToSlack("Invoice Send Failure! ```{$ex->getData()}```");
    header('Location: cart.php?err=isf');
    exit(1);
}
try {
    if (!$customer_id) {
        $customer_id = tools::createOrUpdateCustomer($con, $first_name, $last_name, $email);
    }
    $order_id = tools::insertOrder($con, $customer_id, $shipping, $tax/100, $party, $invoice_id, 'invoice', $items_ordered, $percent_off, $amount_off);
    $details = tools::getOrderDetails($con, 'invoice', $invoice_id);
    if ($details) {
        tools::postToSlack("Invoice Sent Successful! ".print_r($details, true));
    } else {
        $err_details = array_merge($_POST,array('items'=>$items_ordered,'invoice_id'=>$invoice_id));
        tools::postToSlack("No Order Details Found!".print_r($err_details, true));
    }
    tools::clearCart();
    tools::clearSession();
    header('Location: success.php');

} catch(PDOException $ex) {
    tools::postToSlack("Order Creation Failure! {$invoice_id} ```{$ex->getMessage()}\n{$ex->getTraceAsString()}```");
    exit;
    header('Location: error.php?err=ocf');
    exit(1);
} catch(Exception $ex) {
    tools::postToSlack("Order Creation Failure! {$invoice_id} ```{$ex->getMessage()}```");
    header('Location: error.php?err=ocf');
    exit(1);
}
