<?php
require 'vendor/autoload.php';
$secretKey = 'superSecret';
$botToken = '7633606859:AAH3W-YT51vAD34YHPECTGb74acW6QwkDe4';
$webAppUrl = "https://spin.lomiadvert.online";  // Replace with your hosted web app URL
$botUrl = 'https://t.me/wheelyspinbot';
$botName = 'SpinWin';
$environment = 'production'; // production
$tonCenterApiKey = '2a0db6608b7ce5ef93c59388caa38b222768b31c8d83c97aeee274a5706dc4a6';

if ($environment == 'local') {
    $adminWallet = '0QDXOIzTLFDSVl2eQJoTPH_jJLuJMGJ94L9DlEaDQ0JpJ8Y0';

    // $adminWallet = 'UQAX4OL_xU4RLD9J-QguKbYV--CRyHLYgb2zA2XBIRtRqWXv';
    $tonCenterHost = 'https://testnet.toncenter.com/api/v2/getTransactions';
} else {
    $adminWallet = 'UQAX4OL_xU4RLD9J-QguKbYV--CRyHLYgb2zA2XBIRtRqWXv';
    $tonCenterHost = 'https://toncenter.com/api/v2/getTransactions';
}

function getDbConnection() {
    $servername = "localhost";
    $username = "root";
    $password = "falcon2020";
    $dbname = "spin";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}
