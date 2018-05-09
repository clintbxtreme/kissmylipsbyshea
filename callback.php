<?php
include_once 'paypal_api_context.php';

if (isset($_GET['success'])) {
    if ($_GET['success'] == 'true' && isset($_GET['paymentId']) && isset($_GET['PayerID'])) {
        $_SESSION['paymentId'] = $_GET['paymentId'];
        $_SESSION['PayerID'] = $_GET['PayerID'];
        header('Location: complete.php');
    } elseif (isset($_GET['token'])) {
        tools::postToSlack("User Canceled the Approval! Token: {$_GET['token']}\n");
        header('Location: cart.php');
        //todo cancel payement(if needed)
    }
} else {
    header('Location: error.php');
    exit(1);
}