<?php
require 'functions.php';

// grab a POST body and pass it to error_log as a string for debugging
$post = file_get_contents('php://input');
error_log($post);

// Get the content of the incoming message
$content = file_get_contents("php://input");
$update = json_decode($content, true);

// Check if the update contains a message
if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];

    // Create an inline button to open the web app
    $replyMarkup = [
        'inline_keyboard' => [
            [
                [
                    'text' => 'Open SpinWin Mini App',
                    'web_app' => ['url' => $webAppUrl]
                ]
            ]
        ]
    ];

    // Send a response to the user
    $data = [
        'chat_id' => $chatId,
        'text' => "Click the button below to start the SpinWin Mini App:",
        'reply_markup' => json_encode($replyMarkup)
    ];

    $uri = "https://api.telegram.org/bot$botToken/sendMessage?" . http_build_query($data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $uri);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    // log request and response
    error_log("Request: " . json_encode($data));
    error_log("Response: " . $response);
}