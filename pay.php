<?php
require_once 'paypal_api_context.php';

if (isset($_SESSION['paymentId']) && isset($_SESSION['PayerID'])) {
    $fname = tools::setValidName('first_name');
    $lname = tools::setValidName('last_name');
    $email = tools::setValidEmail('email');
    if (!$fname || !$lname || !$email) {
        header('Location: complete.php?err=ip');
        exit(1);
    }
    $paymentId = $_SESSION['paymentId'];
    $payment = \PayPal\Api\Payment::get($paymentId, $apiContext);
    $execution = new \PayPal\Api\PaymentExecution();
    $execution->setPayerId($_SESSION['PayerID']);

    try {
        $result = $payment->execute($execution, $apiContext);
    } catch (Exception $ex) {
        tools::postToSlack("Payment Execution Failure! {$paymentId} ```{$ex->getData()}```");
        header('Location: complete.php?err=pef');
        exit(1);
    }
    try {
        $payment = \PayPal\Api\Payment::get($paymentId, $apiContext);
        foreach ($payment->getTransactions() as $transaction) {
            $amount_details = $transaction->getAmount()->getDetails();
            $shipping = $amount_details->getShipping();
            $subtotal = $amount_details->getSubtotal();
            $taxTotal = $amount_details->getTax();
            $tax = $taxTotal/$subtotal;
            $items_ordered = array();
            foreach ($transaction->getItemList()->getItems() as $item) {
                $id = $item->getSku();
                $quantity = $item->getQuantity();
                $items_ordered[] = array('id'=>$id,'quantity'=>$quantity);
            }
        }
    } catch (Exception $ex) {
        tools::postToSlack("Payment Retrieval Failure! {$paymentId} ```{$ex->getData()}```");
        header('Location: error.php?err=prf');
        exit(1);
    }
    try {
        $party = (isset($_POST['party']));
        $customer_id = tools::createOrUpdateCustomer($con, $fname, $lname, $email);
        $order_id = tools::insertOrder($con, $customer_id, $shipping, $tax, $party, $paymentId, 'payment', $items_ordered, 0, 0, true);
        $details = tools::getOrderDetails($con, 'payment', $paymentId);
        if ($details) {
            tools::postToSlack("Payment Successful! ".print_r($details, true));
        } else {
            $session = array_diff_key($_SESSION,array('products'=>1));
            $err_details = array_merge($session,$_POST,array('items'=>$items_ordered));
            tools::postToSlack("No Order Details Found!".print_r($err_details, true));
        }
        tools::clearCart();
        tools::clearSession();
        header('Location: success.php');

    } catch(Exception $ex) {
        tools::postToSlack("Order Creation Failure! {$paymentId} ```{$ex}```");
        header('Location: error.php?err=ocf');
        exit(1);
    }
} else {
    header('Location: error.php');
    exit(1);
}