<?php
require 'functions.php';

$host = 'http://localhost:8000';

$dbVerify = dbOrderVerifyAll($host, $tonCenterApiKey);

// $verify = verifyOrder($host, $apiKey);

// if ($verify['status'] == 'success') {
//     $message = "Order {$verify['status']} verification successful";
//     error_log($message);
//     echo $message;
// } else {
//     $message = "Order {$verify['status']} verification failed";
//     error_log($message);
//     echo $message;
// }
echo "\n";