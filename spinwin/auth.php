<?php
use TgWebValid\TgWebValid;
use Firebase\JWT\JWT;;

include './vendor/autoload.php';
include 'functions.php';

header('Content-Type: application/json');

if (!isset($_POST['initData'])) {
    $message = "Go back to school and try again";
    $status = 400;

    $response = json_encode(['message' => $message, 'status' => $status]);
    error_log($response);
    echo $response;
    exit;
} else {
    $initData = $_POST['initData'];
    error_log('INIT_DATA: ' . $initData);
    
    try {
        $tgWebValid = new TgWebValid($botToken, true);
        $validate = $tgWebValid->bot()->validateInitData($initData);

        $userId = $validate->user->id;
        $username = $validate->user->username;
        $firstName = $validate->user->firstName;
        $lastName = $validate->user->lastName;
        $isBot = $validate->user->isBot;
        $startParam = $validate->startParam;

        if ($isBot) {
            $message = "Bots are not allowed";
            $status = 403;

            $response = json_encode(['message' => $message, 'status' => $status]);
            error_log($response);
            echo $response;
        } else {
            $issuedAt = time();
            $expirationTime = $issuedAt + (2 * 24 * 60 * 60); // 2 days
            $payload = [
                'iat' => $issuedAt,
                'exp' => $expirationTime,
                'userId' => $userId,
                'username' => $username
            ];

            $auth = registerOrAuthenticate($username, $userId, $startParam);
            
            if ($auth) {
                // $auth is {"success":true,"message":"Welcome back, falcontech!"}
                // parse the JSON string to an object
                $authObj = json_decode($auth);
                $success = $authObj->success;
                $message = $authObj->message;
    
                if (!$success) {
                    $status = 500;
    
                    $response = json_encode(['message' => $message, 'status' => $status]);
                    error_log($response);
                    echo $response;
                    exit;
                } else {
                    $token = JWT::encode($payload, $secretKey, 'HS256');
    
                    error_log("Login token: $token");

                    $status = 200;

                    $uniqueName = getUserData($userId)['data']['unique_name'];
                    $referralLink = getUserReferralLink($userId);
    
                    $response = json_encode(['message' => $message, 'status' => $status, 'token' => $token, 'username' => $username, 'unique_name' => $uniqueName, 'first_name' => $firstName, 'last_name' => $lastName, 'referral_link' => $referralLink]);
                    error_log($response);
                    echo $response;
                }
            } else {
                $message = "An error occurred";
                $status = 500;
    
                $response = json_encode(['message' => $message, 'status' => $status]);
                error_log($response);
                echo $response;
                exit;
            }
            

            // $token = JWT::encode($payload, $secretKey, 'HS256');

            // error_log("Login token: $token");

            // $message = "Welcome $firstName $lastName";
            // $status = 200;

            // $response = json_encode(['message' => $message, 'status' => $status, 'token' => $token, 'username' => $username]);
            // error_log($response);
            // echo $response;
        }
    } catch (Exception $e) {
        $message = "An error occurred";
        $message2 = $e->getMessage();
        $status = 500;

        $response = json_encode(['message' => $message, 'status' => $status]);
        error_log($message2);
        echo $response;
        exit;
    }
}