<?php
$page_title="Cart";
include 'header.php';

$saved_cart_items = tools::getCart();

$products = tools::getProducts($con, array_keys($saved_cart_items));
if(count($products)>0){
    $action = 'checkout.php';
    $discount_html = $admin_html = $party_html = '';
    if ($isAdmin) {
        $action = 'send_invoice.php';
        $customer_options = '';
        $discount_options = '';
        for ($i=0; $i <= 20; $i+=5) {
            $discount_options .= "<option value='{$i}'>{$i}</option>";
        }
        $customers = tools::query($con, "SELECT * FROM customers ORDER BY first_name");
        foreach ($customers as $customer) {
            extract($customer);
            $customer_options .=
                "<option value='{$id}'>{$first_name} {$last_name} - {$email}</option>";
        }
        $discount_html = "
            <td class='form-inline'>
                <label class='toggle-label' for='total_discount_type'>$/% </label>
                <label class='switch'>
                    <input type='checkbox' name='total_discount_type' value='1'>
                    <div class='slider round multi'></div>
                </label>
                <select name='total_discount' class='discount select-2' data-tags='true' data-width='55px'>{$discount_options}</select>
            </td>";
            $tax = tools::$tax*100;
        $admin_html = "
            <tr>
                <td>Customer</td>
                <td colspan='100%'>
                    <select class='form-control select-2' id='customer' name='customer' data-placeholder='New' data-allow-clear='true' data-width='null'>
                        <option></option>{$customer_options}
                    </select>

                </td>
            </tr>
            <tr class='new-customer'>
                <td>First Name</td>
                <td colspan='100%'><input class='form-control type='text' name='first_name'></td>
            </tr>
            <tr class='new-customer'>
                <td>Last Name</td>
                <td colspan='100%'><input class='form-control type='text' name='last_name'></td>
            </tr>
            <tr class='new-customer'>
                <td>Email</td>
                <td colspan='100%'><input class='form-control type='email' name='email'></td>
            </tr>
            <tr>
                <td>Tax</td>
                <td colspan='100%'>
                    <select name='tax' class='discount select-2' data-tags='true' data-width='55px'><option value='{$tax}'>{$tax}</option></select>
                </td>
            </tr>";
        $party_html = "
            <tr>
                <td>Party</td>
                <td colspan='100%'>
                    <label class='switch'>
                        <input type='checkbox' name='party' id='party' value='1'>
                        <div class='slider round'></div>
                    </label>
                </td>
            </tr>";
    }

    $total_price=$total_quantity=0;
    $products_html = '';
    foreach($products as $row){
        extract($row);

        $quantity = intval($saved_cart_items[$id]);
        $products_admin_html = '';
        if ($isAdmin) {
            $products_admin_html = "
            <td class='form-inline'>
                <label class='toggle-label' for='item_discount[{$id}][type]'>$/% </label>
                <label class='switch'>
                    <input type='checkbox' name='item_discount[{$id}][type]' value='1'>
                    <div class='slider round multi'></div>
                </label>
                <select name='item_discount[{$id}][value]' class='discount select-2' data-tags='true' data-width='55px'>{$discount_options}</select>
            </td>";
        }

        $products_html .= "
            <tr id='row_{$id}'>
                <td>{$product}</td>
                <td>&#36;{$price}</td>
                <td>{$quantity}</td>
                {$products_admin_html}
                <td>
                    <a onClick='removeFromCart(\"$id\");' href='#' class='text-pink h4'>
                        <span class='glyphicon glyphicon-remove'></span></a>
                </td>
            </tr>";

        $total_price+=$price*$quantity;
        $total_quantity+=$quantity;
    }

    echo "
        <form class='form-validate' method='POST' action='{$action}'>
            <table class='table table-responsive'>
                {$admin_html}
                {$products_html}
                {$party_html}
                <tr>
                    <td>Shipping</td>
                    <td colspan='100%'>
                        <label class='switch'>
                            <input type='checkbox' name='shipping' id='shipping' value='1' checked>
                            <div class='slider round'></div>
                        </label>
                    </td>
                </tr>
                <tr>
                    <td><b>Subtotal</b> <small>(tax applied at checkout)</small></td>
                    <td>&#36;{$total_price}</td>
                    <td>{$total_quantity}</td>
                    {$discount_html}
                    <td>
                        <button type='submit' class='btn btn-pink disable-on-submit'>
                            <span class='glyphicon glyphicon-shopping-cart'></span> Checkout
                        </button>
                    </td>
                </tr>
            </table>
        </form>";
} else{
    echo "<div class='alert alert-danger'><strong>No products found</strong> in your cart!</div>";
}

include 'footer.php';
?>
