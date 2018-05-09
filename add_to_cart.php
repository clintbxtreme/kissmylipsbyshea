<?php
require_once 'tools.php';
// initialize empty cart items array
$cart_items=array();

// get the product id and name
$id = isset($_POST['id']) ? $_POST['id'] : "";
$quantity = isset($_POST['quantity']) ? $_POST['quantity'] : "";

if (!$id || !$quantity) {
    header('Location: error.php');
    exit(1);
}

// add new item on array
$cart_items[$id]=$quantity;

$saved_cart_items=tools::getCart();

// check if the item is in the array, if it is, do not add
// if(array_key_exists($id, $saved_cart_items)){
//     // redirect to product list and tell the user it was already added to the cart
//     header('Location: products.php?action=exists&id' . $id . '&product=' . $product);
// }

// else{
    // if cart has contents
    if(count($saved_cart_items)>0){
        foreach($saved_cart_items as $key=>$value){
            if (isset($cart_items[$key])) {
                $cart_items[$key] += $value;
            } else {
                $cart_items[$key] = $value;
            }
        }
    }

    tools::setCart($cart_items);

    // redirect
    // header('Location: products.php?action=added&id=' . $id . '&product=' . $product);
// }

?>
