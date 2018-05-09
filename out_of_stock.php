<?php
if (php_sapi_name() == "cli") {
	$seconds = rand(1,1800);
	// date_default_timezone_set("America/Phoenix");
	// echo "Going to run at ".date("m/d/y h:i:s a", time()+$seconds);
	sleep($seconds);
}

require_once 'plugins/simple_html_dom.php';
$page_title="Out of Stock";
include 'header.php';
$url = 'https://www.senegence.com/SeneGenceWeb/WebOrdering/ProductListOutOfStock.aspx?d=201660&c=1';

$file_contents = @file_get_contents($url);
if (!$file_contents) exit('Unable to get URL');

$html = new simple_html_dom();
$html->load($file_contents);

$trs = $html->find('table[cellspacing=1] tr');
$ids = array();
foreach ($trs as $tr) {
	$tds = $tr->find('td');
	$id = trim($tds[0]->plaintext);
	if($id) $ids[] = $id;
}
$products = tools::getProducts($con, $ids);

$ids_data = $products_out = $products_in = array();
if(count($products)>0){
	$products_html = '';
	foreach ($products as $i => $row) {
		extract($row);
		if ($in_stock) $products_out[] = $row;
		$ids_data[":{$i}"] = $id;
		$ids_data[":set_{$i}"] = 'SET'.$id;
        $products_html .= "<tr id='row_{$id}'>
        	<td>{$id}</td>
        	<td>{$product}</td>
    	</tr>";
	}

    echo "<table class='table table-hover table-responsive table-bordered'>
    		<tr>
    			<th>ID</th>
    			<th>Name</th>
			</tr>
			{$products_html}
		</table>";
} else{
    echo "<div class='alert alert-info'>No products out of stock!</div>";
}

include 'footer.php';

//TODO: return to browser and continue processing

$update_in_query = "UPDATE items SET in_stock=1 WHERE in_stock=0";
if (count($ids_data)) {
	$ids_sql = implode(",", array_keys($ids_data));
	$query_in = "SELECT * FROM items WHERE in_stock=0 AND id NOT IN ({$ids_sql})";
	$products_in = tools::query($con, $query_in, $ids_data);
	$update_out_query = "UPDATE items SET in_stock=0 WHERE in_stock=1 AND id IN ({$ids_sql})";
	tools::query($con, $update_out_query, $ids_data);
	$update_in_query .= " AND id NOT IN ({$ids_sql})";
}
tools::query($con, $update_in_query, $ids_data);

$products_out_html = "*Out of Stock:*\n";
foreach ($products_out as $p) {
	extract($p);
	$products_out_html .= "{$id} - {$product}\n";
}
$products_in_html = "*In Stock:*\n";
foreach ($products_in as $p) {
	extract($p);
	$products_in_html .= "{$id} - {$product}\n";
}

$message = '';
if(count($products_in)) $message .= $products_in_html;
if(count($products_out)) $message .= $products_out_html;
if($message) tools::postToSlack($message);
