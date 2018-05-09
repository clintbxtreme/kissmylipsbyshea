<?php

if(preg_match('(spider|bot)', strtolower($_SERVER['HTTP_USER_AGENT'])) === 1) {
    echo "Nice to meet you Bot!";
    exit;
}

header('Location: products.php');