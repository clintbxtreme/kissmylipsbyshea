<?php

class tools {
	protected static $cart_cookie = 'cart_items_cookie';
	public static $shipping = 3.5;
	public static $tax = 0.13;
	public static $merchant_info = array();
	public static $template_id = '';
	public static $options = array();
	public static $regs = array(
		'name' => "/^(\s)*[A-Za-z]+((\s)?((\'|\-|\.)?([A-Za-z])+))*(\s)*$/",
		'email' => "/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/",
	);

	static function isTesting() {
		$ini = parse_ini_file('./config/config.ini');
		$ini_test = parse_ini_file('./config/config_dev.ini');
		self::$options = $ini;
		if (isset($_SERVER['HTTP_X_SCRIPT']) && strpos($_SERVER['HTTP_X_SCRIPT'], "_test/")) {
			self::$options = $ini_test;
			return true;
		} elseif (strpos($_SERVER['PHP_SELF'], "_test/")) {
			self::$options = $ini_test;
			return true;
		}
		return false;
	}
	static function isAdmin() {
	    return (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']) && $_SERVER['PHP_AUTH_USER'] == self::$options['admin_username'] && $_SERVER['PHP_AUTH_PW'] == self::$options['admin_password']);
	}
	static function login($refresh=false) {
		if (!self::isAdmin()) {
			$domain = self::$options['domain'];
		    header("WWW-Authenticate: Basic realm='{$domain}'");
		    header('HTTP/1.1 401 Unauthorized');
		    echo"<script>window.location='error.php?err=il'</script>";
		    exit(1);
		}
		if ($refresh) {
		    header("Location: {$_SERVER['PHP_SELF']}");
		    exit;
		}
	}
	static function logout() {
	    header('HTTP/1.1 401 Unauthorized');
	    echo"<script>window.location='{$_SERVER['PHP_SELF']}'</script>";
	}
	static function getCart() {
		$saved_cart_items = array();
		if (isset($_COOKIE[self::$cart_cookie])) {
			$cookie = $_COOKIE[self::$cart_cookie];
			$cookie = stripslashes($cookie);
			$saved_cart_items = json_decode($cookie, true);
		}
		return $saved_cart_items;
	}
	static function setCart($data) {
		$json = json_encode($data, true);
		setcookie(self::$cart_cookie, $json);
	}
	static function clearCart() {
		setcookie(self::$cart_cookie, '', time()-3600);
	}
	static function clearSession() {
		if (session_status() === PHP_SESSION_NONE) session_start();
		session_unset();
	    session_destroy();
	    session_write_close();
	    setcookie(session_name(),'',0,'/');
	    session_regenerate_id(true);
	}
	static function getProducts($con, $ids) {
		if (!count($ids)) return array();
		$ids_data = array();
		foreach ($ids as $i => $id) {
			$ids_data[":{$i}"] = $id;
		}
		$ids_sql = implode(",", array_keys($ids_data));
        $query = "SELECT * FROM items WHERE id IN ({$ids_sql}) ORDER BY product";
        $products = self::query($con, $query, $ids_data);
        return $products;
	}
    static function nonNullString($con, $string) {
        $string = trim($string);
        $string = stripslashes($string);
        return ($string == 'NULL') ? 'NULL' : $con->quote($string);
    }
    static function getCustomer($con, $customer_id) {
		$customer_id = intval($customer_id);
		$query = "SELECT * FROM customers WHERE id=:id";
		return self::query($con, $query, array(':id'=>$customer_id));
    }
    static function createOrUpdateCustomer($con, $fname, $lname, $email) {
		$fname = trim($fname);
		$lname = trim($lname);
		$email = strtolower(trim($email));

        if (!$email || $email == 'noreply@here.paypal.com') $email = $fname.$lname.'@email';

        // $fname_sql = self::nonNullString($con, $fname);
        // $lname_sql = self::nonNullString($con, $lname);
        // $email_sql = self::nonNullString($con, $email);
		$existing_customer = self::query($con, "SELECT * FROM customers WHERE email=:email ORDER BY updated_dt DESC", array(':email'=>$email));
        if (!count($existing_customer)) {
	        $existing_customer = self::query($con, "SELECT * FROM customers WHERE first_name=:fname AND last_name=:lname ORDER BY updated_dt DESC", array(':fname'=>$fname,':lname'=>$lname));
        }
        if (count($existing_customer)) {
            $cust_update_sql = $cust_update_data = array();
            $customer = $existing_customer[0];
            $customer_id = $customer['id'];
            if (!trim($customer['first_name']) && $fname) {
            	$cust_update_sql[] = "first_name=:fname";
            	$cust_update_data[':fname'] = $fname;
            }
            if (!trim($customer['last_name']) && $lname) {
            	$cust_update_sql[] = "last_name=:lname";
            	$cust_update_data[':lname'] = $lname;
            }
            if ((!trim($customer['email']) || strpos($customer['email'], '@email')) && $email) {
            	$cust_update_sql[] = "email=:email";
            	$cust_update_data[':email'] = $email;
            }
            if (count($cust_update_sql)) {
                $cust_update_sql = implode(",", $cust_update_sql);
                $cust_update_data[":id"] = $customer_id;
                self::query($con, "UPDATE customers SET {$cust_update_sql} WHERE id=:id", $cust_update_data);
            }
            $customer_status = 'existing';
        } else {
            self::query($con, "INSERT INTO customers (first_name, last_name, email) VALUES (:fname,:lname,:email)", array(':fname'=>$fname,':lname'=>$lname,':email'=>$email));
            $customer_id = $con->lastInsertId();
            $customer_status = 'new';
        }
        if (!isset($_SESSION['customer_status'])) $_SESSION['customer_status'] = $customer_status;
        return $customer_id;
    }
    static function insertOrder($con, $customer_id, $shipping=0, $tax=0, $party=0, $payment_id, $payment_type, $products, $percent_off=0, $amount_off=0, $paid=false) {
 		$percent_off = number_format($percent_off);
 		$amount_off = number_format($amount_off, 2);
 		$shipping = number_format($shipping, 2);
 		$tax = number_format($tax, 2);
 		$now = date('Y-m-d H:i:s');
 		$paid = $paid ? $now : null;
 		$party = intval($party);
        self::query($con, "INSERT INTO orders (customer_id, ordered_dt, shipping, tax, party, {$payment_type}_id, percent_off, amount_off, paid)
            VALUES (:customer_id,:ordered_dt,:shipping,:tax,:party,:payment_id,:percent_off,:amount_off,:paid)", array(':customer_id'=>$customer_id,':ordered_dt'=>$now,':shipping'=>$shipping,':tax'=>$tax,':party'=>$party,':payment_id'=>$payment_id,':percent_off'=>$percent_off,':amount_off'=>$amount_off,':paid'=>$paid));
        $order_id = $con->lastInsertId();
        foreach($products as $product) {
    		$amount_off = (isset($product['amount_off'])) ? number_format($product['amount_off'], 2) : 0;
    		$percent_off = (isset($product['percent_off'])) ? number_format($product['percent_off'], 2) : 0;
            // $product_id_sql = self::nonNullString($con, $product['id']);
        	for ($q=1; $q <= $product['quantity']; $q++) {
	            self::query($con, "INSERT INTO items_ordered (item_id, order_id, amount_off, percent_off) VALUES (:item_id,:order_id,:amount_off,:percent_off)", array(':item_id'=>$product['id'],':order_id'=>$order_id,':amount_off'=>$amount_off,':percent_off'=>$percent_off));
        	}
        }
        return $order_id;
    }
    static function getOrderDetails ($con, $payment_type, $paymentId) {
    	// $paymentIdSql = self::nonNullString($con, $paymentId);
        $query = "SELECT c.first_name, c.last_name, c.email, io.order_id, i.product, i.price, o.shipping, o.party, o.paid, o.{$payment_type}_id
            FROM orders o
            INNER JOIN customers c ON o.customer_id = c.id
            INNER JOIN items_ordered io ON o.id = io.order_id
            INNER JOIN items i ON io.item_id = i.id
            WHERE o.{$payment_type}_id=:payment_id";
        $orders = tools::query($con, $query, array(':payment_id'=>$paymentId));
        $details = array();
        foreach($orders as $order) {
            $details['name'] = $order['first_name']." ".$order['last_name'];
            $details['email'] = $order['email'];
            $details['order id'] = $order['order_id'];
            $details['shipping'] = intval($order['shipping']) ? 'yes' : 'no';
            $details['party'] = $order['party'] ? 'yes' : 'no';
            $details['products'][] = "{$order['product']} - \${$order['price']}";
            if (strtotime($order['paid'])>946710000) {
            	$details['paid'] = $order['paid'];
            }
            $details[$payment_type.' id'] = $order[$payment_type.'_id'];
            if ($payment_type == 'invoice') {
				$inv_id = $order['invoice_id'];
				$details['invoice id'] = "<https://www.paypal.com?cmd=_pay-inv&id={$inv_id}|{$inv_id}>";
            }
        }
        return $details;
    }
    static function query($con, $query, $data=array()) {
    	$result = array();
		$stmt = $con->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmt->execute($data);
		if ($stmt->columnCount()) {
			$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
		}
	    // $num = $stmt->rowCount();
	    // if ($num>0) {
	    //     while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){
	    //         $result[] = $row;
	    //     }
	    // }
	    return $result;
    }
	static function getBaseUrl()
	{
	    if (PHP_SAPI == 'cli') {
	        $trace=debug_backtrace();
	        $relativePath = substr(dirname($trace[0]['file']), strlen(dirname(dirname(__FILE__))));
	        echo "Warning: This sample may require a server to handle return URL. Cannot execute in command line. Defaulting URL to http://localhost$relativePath \n";
	        return "http://localhost" . $relativePath;
	    }
	    $protocol = 'http';
	    if ($_SERVER['SERVER_PORT'] == 8081 || (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on')) {
	        $protocol .= 's';
	    }
	    $host = $_SERVER['HTTP_HOST'];
	    $request = $_SERVER['PHP_SELF'];
	    return dirname($protocol . '://' . $host . $request);
	}
	static function postToSlack($message) {
	    $url = self::$options['slack_url'];
	    $fields = json_encode(array("text" => $message));
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_HEADER, 0);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($ch, CURLOPT_TIMEOUT, 0);
	    curl_setopt($ch, CURLOPT_POST, true);
	    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
	    $result = curl_exec($ch);
	    curl_close($ch);
	}
	static function setValid($field, $type) {
		if (isset($_POST[$field]) && preg_match(self::$regs[$type], $_POST[$field])) {
			return trim($_POST[$field]);
		}
		return false;
	}
	static function setValidName($field) {
		return self::setValid($field, 'name');
	}
	static function setValidEmail($field) {
		return strtolower(self::setValid($field, 'email'));
	}
	static function setValidId($field) {
		if (isset($_POST[$field])) {
			return intval($_POST[$field]);
		}
		return 0;
	}
}