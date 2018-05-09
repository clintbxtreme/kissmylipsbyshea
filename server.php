<?php
if (isset($argv)) {
	parse_str(implode('&', array_slice($argv, 1)), $_REQUEST);
}

if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json') {
    $HTTP_RAW_POST_DATA = file_get_contents("php://input");
    if ($HTTP_RAW_POST_DATA) {
	    $_REQUEST = array_merge($_REQUEST, json_decode($HTTP_RAW_POST_DATA, true));
    }
}

if (!isset($_REQUEST['action'])) {
	http_response_code(404);
	exit(1);
}

if ($_REQUEST['action'] != 'get_products') {
	require_once 'paypal_api_context.php';
}

$secret = tools::$options['secret'];

switch ($_REQUEST['action']) {
	case 'update_paid_orders':
		$unpaid_orders = tools::query($con, "SELECT * FROM orders WHERE paid IS NULL AND invoice_id IS NOT NULL");
		foreach($unpaid_orders as $order) {
			extract($order);
			$invoice = \PayPal\Api\Invoice::get($invoice_id, $apiContext);
			if (in_array($invoice->getStatus(), array('PAID','MARKED_AS_PAID'))) {
				tools::query($con, "UPDATE orders SET paid=NOW() WHERE invoice_id=:invoice_id",array(':invoice_id'=>$invoice_id));
				tools::postToSlack("Invoice: {$invoice_id} paid");
			}
		}
		break;

	case 'insert_invoice':
		if (!isset($_REQUEST['invoice_id']) || !$_REQUEST['invoice_id']) exit('need invoice_id');
		$invoice_id = $_REQUEST['invoice_id'];
		include 'webhook_callback.php';
		break;

	case 'get_products':
		if (isset($_SERVER['HTTP_HOST'])) exit("Permissions Denied");

		$seconds = rand(1,3600);
		date_default_timezone_set("America/Phoenix");
		// echo "Going to run at ".date("m/d/y h:i:s a", time()+$seconds);
		// sleep($seconds);

		require_once 'plugins/simpletest/browser.php';
		require_once 'plugins/simple_html_dom.php';
		$browser = new SimpleBrowser();
		$browser->get('https://www.senegence.com/senegenceweb/DistBackOffice/Login.aspx');
		$browser->setFieldById('distributorID', tools::$options['distributor_id']);
		$browser->setFieldById('password', tools::$options['distributor_password']);
		$browser->click('Login Now');
		$browser->get('https://www.senegence.com/SeneGenceWeb/WebOrdering/default.aspx?d=201660&e=0');
		$page_html = $browser->getContent();
		$html = new simple_html_dom();
		$html->load($page_html);
		$trs = $html->find('table[cellspacing=1] tr');
		foreach ($trs as $tr) {
			$tds = $tr->find('td');
			if (count($tds)!=5) continue;

			$id = trim($tds[1]->plaintext);
			$name = strtolower(trim($tds[2]->plaintext));
			$price = trim($tds[3]->plaintext);
			$status = trim($tds[4]->plaintext);

			if (strpos($name, "lipsense") || strpos($name, " gloss") || strpos($name, "lip balm") || strpos($name, "color remover") || strpos($name, "lear lipvolumizer") || strpos($name, "shadowsense")) {
				if (strpos($name, "applicator") || strpos($name, "samples")) continue;
				$name = str_replace("lipsense collection", "starter kit", $name);
				$name = str_replace("lipsense moisturizing", "moisturizing", $name);
				$name = str_replace("lipsense", "lipstick", $name);
				$name = str_replace("sensecosmetics", "cosmetics", $name);
				$name = str_replace("creme to powder shadowsense", "shadow creme", $name);
				$name = str_replace("crème to powder shadowsense", "shadow creme", $name);
				$first_time = 1;
				if (strpos($name, " gloss") && strpos($name, "glossy")===false) $first_time = 0;
				$in_stock = 1;
				if (strpos(strtolower($status), "out")) $in_stock = 0;
				$price = str_replace("$", '', $price);
				$name = ucwords($name);
				$products[] = array(
					'id' => $id,
					'name' => $name,
					'price' => $price,
					'status' => $status,
					'in_stock' => $in_stock,
					'first_time' => $first_time,
				);
			}
		}
		$params = array(
			'products' 	=> json_encode($products),
			'action'	=> 'update_products',
			'scrt'		=> $secret,
		);
		$url = "https://127.0.0.1/server.php";
		$ch = curl_init();
		curl_setopt ($ch, CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_POST, count($params));
		curl_setopt($ch,CURLOPT_POSTFIELDS, $params);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$result = curl_exec($ch);

		$curl_error = (curl_errno($ch)==0) ? false : true;
		$curl_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if($curl_error) {
			tools::postToSlack('update_products curl error: ' . curl_error($ch) . '; Status code: ' . $curl_http_code);
		}
		curl_close($ch);

		print $result;
		break;

	case 'update_products':
		if ($_REQUEST['scrt'] != $secret) exit("Permissions Denied");
		$products_json = $_REQUEST['products'];
		$products = json_decode($products_json, true);

		if (!$products_json || !$products || !count($products)) {
			tools::postToSlack("Unable to get Products");
			exit;
		}

		foreach ($products as $p) {
			$query_values = array(
				':product'=>$p['name'],
				':price'=>$p['price'],
				':in_stock'=>$p['in_stock'],
				':first_time'=>$p['first_time'],
				':id'=>$p['id'],
			);
			$echo_details = ": {$p['id']} {$p['name']} {$p['price']} {$p['status']}\n";
			$slack_details = array(
				"name" => $p['name'],
				"price" => $p['price'],
				"in_stock" => $p['in_stock'],
				"first_time" => $p['first_time'],
			);

			$exists = tools::query($con, "SELECT * FROM items WHERE id=:id", array(':id'=>$p['id']));
			if (count($exists)) {
				$product = $exists[0];
				if ($p['name'] != $product['product'] || $p['price'] != $product['price'] || $p['in_stock'] != $product['in_stock'] || $p['first_time'] != $product['first_time']) {
					$query = "UPDATE items SET product=:product,price=:price,in_stock=:in_stock,first_time=:first_time,update_dt=CURRENT_TIMESTAMP WHERE id=':id'";
					tools::query($con, $query, $query_values);
					echo "UPDATE".$echo_details;
					tools::postToSlack("Product Change: ".print_r($slack_details, true));
				}
			} else {
				$query = "INSERT INTO items (id,product,price,in_stock,first_time) VALUES (:id,:product,:price,:in_stock,:first_time)";
				tools::query($con, $query, $query_values);
				echo "INSERT".$echo_details;
				tools::postToSlack("Product New: ".print_r($slack_details, true));
			}
		}
		break;

	default:
		http_response_code(404);
		exit();
		break;
}