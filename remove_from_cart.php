<?php
require_once 'tools.php';
// get the product id
$id = isset($_POST['id']) ? $_POST['id'] : "";

if (!$id) {
    header('Location: error.php');
    exit(1);
}

$saved_cart_items = tools::getCart();

unset($saved_cart_items[$id]);
tools::clearCart();
tools::setCart($saved_cart_items);
?>
