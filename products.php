<?php
$page_title="Products";
include 'header.php';

if (!isset($_SESSION['products'])) {
    $query = "SELECT id, product, price FROM items ORDER BY product";
    $_SESSION['products'] = tools::query($con, $query);
}

//start table
// echo "<table class='table table-hover table-responsive table-bordered'>";

//     // our table heading
//     echo "<tr>";
//         echo "<th class='textAlignLeft'>Product Name</th>";
//         echo "<th>Price (USD)</th>";
//         echo "<th>Action</th>";
//     echo "</tr>";

    $options = "";
    foreach($_SESSION['products'] as $products) {
        extract($products);
        $options .= "<option value='{$id}'>{$product} - \${$price}</option>";

        //creating new table row per record
        // echo "<tr>";
        //     echo "<td>{$product}</td>";
        //     echo "<td>&#36;{$price}</td>";
        //     echo "<td>";
        //         echo "<a href='add_to_cart.php?id={$id}&product={$product}' class='btn btn-primary'>";
        //             echo "<span class='glyphicon glyphicon-shopping-cart'></span> Add to cart";
        //         echo "</a>";
        //     echo "</td>";
        // echo "</tr>";
    }
    $quantities = "";
    for ($i=1; $i <= 5; $i++) {
        $quantities .= "<option value='{$i}'>{$i}</option>";
    }
    echo <<<EOC
        <form action='#' onsubmit='addToCart();return false;'>
            <div class="input-group select2-bootstrap-append select2-bootstrap-prepend">
                <span class='rounded-left'>
                    <select class='select-2' id='product' data-placeholder='Select Product' data-allow-clear='true' data-width='null'>
                        <option></option>{$options}
                    </select>
                </span>
                <div class="input-group-btn">
                    <select class='select-2' id='quantity' data-width='47px' data-minimum-results-for-search='Infinity'>
                        {$quantities}
                    </select>
                </div>
                <div class="input-group-btn">
                    <button type='submit' class='btn btn-pink disable-on-submit' id='add-to-cart'>
                        <span class='glyphicon glyphicon-plus'></span>
                    </button>
                </div>
            </div>
        </form>
EOC;
// echo "</table>";

include 'footer.php';
?>
