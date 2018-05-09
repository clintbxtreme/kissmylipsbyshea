<?php
$host = "localhost";
$db_name = tools::$options['db_name'];
$username = tools::$options['db_username'];
$password = tools::$options['db_password'];

try {
	$con_str = "mysql:host={$host};dbname={$db_name}";
    $con = new PDO($con_str, $username, $password,array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
}

//to handle connection error
catch(PDOException $exception){
    echo "Connection error: " . $exception->getMessage();
    header('Location: error.php?err=ce');
}