<?php
session_start();
require_once 'tools.php';
tools::isTesting();
require_once 'config/db_connect.php';
require_once __DIR__  . '/PayPal-PHP-SDK/autoload.php';
$apiContext = new \PayPal\Rest\ApiContext(
new \PayPal\Auth\OAuthTokenCredential(
        tools::$options['api_client_id'],
        tools::$options['api_secret']
    )
);
$apiContext->setConfig(
	array(
		'mode' => tools::$options['api_mode'],
		'log.LogEnabled' => true,
		'log.FileName' => "/var/log/custom/PayPal.log",
		'log.LogLevel' => 'DEBUG'
	)
);
