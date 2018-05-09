<?php
$page_title="Order Management";
include_once 'paypal_api_context.php';
include 'header.php';

tools::login();

if (isset($_POST['action'])) {
	if (!isset($_POST['ids']) || !count($_POST['ids'])) {
		exit;
	}
	$results = array();

	switch ($_POST['action']) {
		case 'mark_complete':
			foreach ($_POST['ids'] as $id) {
				$id=intval($id);
				tools::query($con, "UPDATE orders SET completed=NOW() WHERE id={$id} AND completed IS NULL");
				$results[] = array('success'=>"Order: {$id} marked as completed");
			}
			break;

		case 'paid_cash':
			foreach ($_POST['ids'] as $id) {
				$id=intval($id);
				$orders = tools::query($con, "SELECT invoice_id, payment_id, paid FROM orders WHERE id=:id", array(':id'=>$id));
				if (!count($orders)) {
					$results[] = array('error'=>"Faliled getting invoice for order: {$id}");
					tools::postToSlack("Failed getting invoice id for order: {$id}");
					continue;
				}
				extract($orders[0]);
				if (!$invoice_id) {
					if ($payment_id) {
						$results[] = array('error'=>"Order: {$id} is a Payment not an Invoice");
					} else {
						$results[] = array('error'=>"No Invoice for order: {$id}");
						tools::postToSlack("No invoice id for order: {$id}");
					}
					continue;
				}
				if ($paid) {
					$results[] = array('success'=>"Order: {$id} already paid");
					continue;
				}
				try {
					$invoice = \PayPal\Api\Invoice::get($invoice_id, $apiContext);

				    $record = new \PayPal\Api\PaymentDetail();
					$record->setMethod('CASH')
						->setDate(date('Y-m-d H:i:s T'));

				    $recordStatus = $invoice->recordPayment($record, $apiContext);
					$results[] = array('success'=>"Invoice for order: {$id} marked as paid (with cash)");
				    tools::postToSlack("Invoice: {$invoice_id} paid with cash");
				} catch (Exception $ex) {
					$messageDecoded = json_decode($ex->getData(), true);
					if ($messageDecoded['message'] == 'Already paid.') {
						$results[] = array('success'=>"Invoice for order: {$id} already paid");
					} else {
						$results[] = array('error'=>"Unknown error with order: {$id}");
						tools::postToSlack("Failed recording cash payment! ```{$ex->getData()}```");
					}
				}
			}
			break;
	}
	if (count($results)) {
		foreach ($results as $result) {
			foreach ($result as $type=>$text) {
				$type = ($type == 'success') ? 'info' : 'danger';
				echo "<div class='alert alert-{$type} alert-dismissible fade in' role='alert'>
						<button type='button' class='close' data-dismiss='alert' aria-label='Close'>
							<span aria-hidden='true'>&times;</span>
						</button>
						<strong>{$text}</strong>
					</div>";
			}
		}
	}
}

$query = "SELECT c.first_name, c.last_name, c.email, o.id, o.ordered_dt, o.paid,
				o.completed, o.shipping, o.tax, p.name as party_name, o.notes, o.payment_id, o.invoice_id, o.customer_id,
				o.percent_off, o.amount_off, io.amount_off as item_amount_off,
				io.percent_off as item_percent_off, i.product, i.price, io.item_id
			FROM orders o
        	INNER JOIN customers c ON c.id=o.customer_id
        	INNER JOIN items_ordered io on o.id=io.order_id
        	INNER JOIN items i on io.item_id=i.id
        	LEFT JOIN parties p on o.party=1 AND o.ordered_dt BETWEEN p.start_dt AND DATE_ADD(p.end_dt,INTERVAL 1 DAY)
        	";

$results = tools::query($con, $query);
$orders = $orders_per_id = $orders_by_id = array();
foreach ($results as $result) {
	$orders_by_id[$result['id']][] = $result;
}
foreach ($orders_by_id as $order_id => $items) {
	$order = array();
	foreach ($items as $item) {
		if (!count($order)) {
			$order = array(
				'ID' => $item['id'],
				'First Name' => $item['first_name'],
				'Last Name' => $item['last_name'],
				'Email' => $item['email'],
				'Product' => $item['product'],
				'Ordered Date' => ($item['ordered_dt']) ? date('m/d/y', strtotime($item['ordered_dt'])) : '',
				'Shipping' => $item['shipping'],
				'Paid' => ($item['paid']) ? date('m/d/y', strtotime($item['paid'])) : '',
				'Completed' => ($item['completed']) ? date('m/d/y', strtotime($item['completed'])) : '',
				'Payment ID' => $item['payment_id'],
				'Invoice ID' => $item['invoice_id'],
				'Tax' => $item['tax'],
				'Party' => $item['party_name'],
				'Amount Off' => $item['amount_off'],
				'Percent Off' => $item['percent_off'],
				'Customer ID' => $item['customer_id'],
			);
		} else {
			$order['Product'] .= ", {$item['product']}";
		}
	}
	$orders[] = $order;
}
$default_hidden = array(
	'Ordered Date',
	'Payment ID',
	'Invoice ID',
	'Tax',
	'Party',
	'Amount Off',
	'Percent Off',
	'Customer ID',
);
$orders_html = $orders_html_header = "";
foreach (array_keys($orders[0]) as $header) {
	$data_html = '';
	if (in_array($header, $default_hidden)) {
		$data_html .= ' data-visible="false"';
	}
	$orders_html_header .= "<th data-sortable='true' data-order='desc'{$data_html}>{$header}</th>";
}
foreach ($orders as $order) {
	$column_html = '';
	foreach ($order as $column => $value) {
		if ($column == 'ID') {
			$value = "<input type='checkbox' class='ids' name='ids[]' value='{$value}'> {$value}";
		}
		$column_html .= "<td>{$value}</td>";
	}
	$orders_html .= "<tr>{$column_html}</tr>";
}
echo "
	<form class='form-validate' method='POST' action='order_management.php'>
		<div id='toolbar' class='btn-group'>
		    <button type='submit' name='action' value='mark_complete' class='btn btn-default' title='mark complete'>
		        <span class='glyphicon glyphicon-ok'></span>
		    </button>
		    <button type='submit' name='action' value='paid_cash' class='btn btn-default' title='paid cash'>
		        <span class='glyphicon glyphicon-usd'></span>
		    </button>
		</div>

	    <table data-toggle='table'
	    	data-search='true'
	    	data-toolbar='#toolbar'
	    	data-show-columns='true'
	    	data-pagination='true'
			data-show-pagination-switch='true'
	    >
	        <thead><tr>{$orders_html_header}</tr></thead>
	        <tbody>{$orders_html}</tbody>
	    </table>
    </form>";

include 'footer.php';