<?php
include_once 'paypal_api_context.php';

$request_body = file_get_contents("php://input");

//calling from server.php
if (isset($invoice_id) && $invoice_id) {
	$request = array(
		'event_type'=>'INVOICING.INVOICE.PAID',
		'resource'=>array(
			'invoice'=>array(
				'id'=>$invoice_id,
			),
		),
	);
	$request_body = json_encode($request);
}

if (!$request_body) {
	http_response_code(404);
	exit(1);
}

//hacky
$rb_dec = json_decode($request_body, true);
if (isset($rb_dec['resource']['invoice']) && $rb_dec['resource']['invoice'] == null) {
	tools::postToSlack("Invoice is null again for webhook: ```{$request_body}```");
	return true;
}

$webhookEvent = new \PayPal\Api\WebhookEvent();
$webhookEvent->fromJson($request_body);
$event_type = $webhookEvent->getEventType();
$resource = $webhookEvent->getResource()->toArray();

switch ($event_type) {
	case 'INVOICING.INVOICE.PAID':
		$invoice_id = $resource['invoice']['id'];
		try {
			$invoice = \PayPal\Api\Invoice::get($invoice_id, $apiContext);
		} catch (Exception $ex) {
		    tools::postToSlack("Unable to get invoice! ```{$ex->getData()}```");
		    exit(1);
		}
		$status = $invoice->getStatus();
		$paid = (in_array($status, array('PAID','MARKED_AS_PAID'))) ? true : false;
		$existing_invoice = tools::query($con, "SELECT * FROM orders o LEFT JOIN customers c ON c.id=o.customer_id WHERE o.invoice_id=:invoice_id", array(':invoice_id'=>$invoice_id));
		if (count($existing_invoice)) {
			if ($paid && !$existing_invoice[0]['paid']) {
				tools::query($con, "UPDATE orders SET paid=NOW() WHERE invoice_id=:invoice_id", array(':invoice_id'=>$invoice_id));
				$name = $existing_invoice[0]['first_name'].' '.$existing_invoice[0]['last_name'];
				tools::postToSlack("Existing Invoice: {$invoice_id} paid ({$name})");
			}
		} else {
			$billing_info = $invoice->getBillingInfo()[0];
			$first_name = $billing_info->getFirstName();
			$last_name = $billing_info->getLastName();
			$email = $billing_info->getEmail();
			$shipping = $invoice->getShippingCost()->getAmount()->getValue();
			$items = $invoice->getItems();
			$tax = $items[0]->getTax()->getPercent()/100;
			foreach($items as $item) {
				$product = array();
				$product = tools::query($con, "SELECT * FROM items WHERE product=:product_name", array(':product_name'=>$item->getName()));
				if (count($product)) {
					$p = array(
						'id'=>$product[0]['id'],
						'quantity'=>$item->getQuantity(),
					);
					$discount = $item->getDiscount();
					if ($discount) {
						if ($discount->getPercent()) {
							$p['percent_off'] = $discount->getPercent();
						} else {
							$p['amount_off'] = $discount->getAmount()->getValue();
						}
					}
					$products[] = $p;
				} else {
					tools::postToSlack("Can't find product: {$item->getName()} for invoice: {$invoice_id}");
				}
			}
			if (!count($products)) {
				tools::postToSlack("No products for invoice: {$invoice_id}");
				exit;
			}

			$percent_off = $amount_off = 0;
			$discount = $invoice->getDiscount();
			if ($discount) {
				if ($discount->getPercent()) {
					$percent_off = $discount->getPercent();
				} else {
					$amount_off = $discount->getAmount()->getValue();
				}
			}

			$customer_id = tools::createOrUpdateCustomer($con, $first_name, $last_name, $email);
			$order_id = tools::insertOrder($con, $customer_id, $shipping, $tax, 0, $invoice_id, 'invoice', $products, $percent_off, $amount_off, $paid);
			$details = tools::getOrderDetails($con, 'invoice', $invoice_id);
		    if ($details) {
		        tools::postToSlack("Order Created from Invoice! ".print_r($details, true));
		    } else {
		        $err_details = array(
		        	'first name'=>$first_name,
		        	'last name'=>$last_name,
		        	'email'=>$email,
		        	'paid'=>$paid,
		        	'order id'=>$order_id,
		        	'invoice id'=>$invoice_id,
		        	'shipping'=>$shipping,
		        	'tax'=>$tax,
		        	'customer id'=>$customer_id,
		        	'products'=>$products,
	        	);
		        tools::postToSlack("No Invoice Order Details Found!".print_r($err_details, true));
		    }
		}
		break;

	case 'PAYMENT.SALE.COMPLETED':
		$payment_id = $resource['parent_payment'];
		try {
			$payment = \PayPal\Api\Payment::get($payment_id, $apiContext);
		} catch (Exception $ex) {
		    tools::postToSlack("Unable to get payment! ```{$ex->getData()}```");
		    exit(1);
		}
		$paid = ($payment->getState() == 'approved') ? true : false;
		$return = tools::query($con, "SELECT * FROM orders WHERE payment_id=:payment_id", array(':payment_id'=>$payment_id));
		if (count($return)) {
			if ($paid && !$return[0]['paid']) {
				$order_id = $return[0]['id'];
				tools::query($con, "UPDATE orders SET paid=NOW() WHERE payment_id=:payment_id", array(':payment_id'=>$payment_id));
				tools::postToSlack("Payment: {$payment_id} paid");
			}
		} else {
			$payer_info = $payment->getPayer()->getPayerInfo();
			$first_name = $payer_info->getFirstName();
			$last_name = $payer_info->getLastName();
			$email = $payer_info->getEmail();
			$transactions = $payment->getTransactions();
			if (count($transactions) > 1) {
				tools::postToSlack("More than one transaction for payment: {$payment_id}");
			}
			$transaction = $transactions[0];
			$payment_details = $transaction->getAmount()->getDetails();
			$shipping = $payment_details->getShipping();
			$tax = $payment_details->getTax()/$payment_details->getSubtotal();
			$products = array();
			foreach($transaction->getItemList()->getItems() as $item) {
				$product = array();
				if ($item->getSku()) {
					$product = tools::query($con, "SELECT * FROM items WHERE id=:item_id", array(':item_id'=>$item->getSku()));
				}
				if (empty($product)) {
					$product = tools::query($con, "SELECT * FROM items WHERE product=:product_name", array(':product_name'=>$item->getName()));
				}
				if (count($product)) {
					$products[] = array(
						'id'=>$product[0]['id'],
						'quantity'=>$item->getQuantity(),
						//TODO handle discounts??
					);
				} else {
					tools::postToSlack("Can't find product: {$item->getName()} - {$item->getSku()} for payment: {$payment_id}");
				}
			}
			if (!count($products)) {
				tools::postToSlack("No products for payment: {$payment_id}");
				exit;
			}
			$customer_id = tools::createOrUpdateCustomer($con, $first_name, $last_name, $email);
			$order_id = tools::insertOrder($con, $customer_id, $shipping, $tax, 0, $payment_id, 'payment', $products, 0, 0, $paid);
			$details = tools::getOrderDetails($con, 'payment', $payment_id);
		    if ($details) {
		        tools::postToSlack("Order Created from Payment! ".print_r($details, true));
		    } else {
		        $err_details = array(
		        	'first name'=>$first_name,
		        	'last name'=>$last_name,
		        	'email'=>$email,
		        	'paid'=>$paid,
		        	'order id'=>$order_id,
		        	'payment id'=>$payment_id,
		        	'shipping'=>$shipping,
		        	'tax'=>$tax,
		        	'customer id'=>$customer_id,
		        	'products'=>$products,
	        	);
		        tools::postToSlack("No Payment Order Details Found!".print_r($err_details, true));
		    }
		}
		break;

	default:
		tools::postToSlack(print_r($request_body, true));
		break;
}