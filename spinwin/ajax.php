<?php
header('Content-Type: application/json');

require 'functions.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// $generator = new \Nubs\RandomNameGenerator\Alliteration();
// echo $generator->getName();
// exit;

$headers = apache_request_headers();
$token = null;
if (isset($headers['Authorization'])) {
    list(, $token) = explode(' ', $headers['Authorization']);
}

if ($token) {
    logger("Used token 1: $token");

    try {
        $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
        // Assuming the user is valid, process the request
        // check request type
        // $request = file_get_contents('php://input');
        // $data = json_decode($request, true);
        
        // get user id from the token
        $userId = $decoded->userId;
        $id = getUserId($userId);
        
        if (isset($_POST['check-balance'])) {

            $balance = checkBalance($userId);
            $winning = checkWinning($userId);

            echo json_encode(['balance' => $balance, 'winning' => $winning]);
        } elseif (isset($_POST['leaderboard'])) {

            $leaderboard = getLeaderBoard($userId, 10); 
            
            // Output leaderboard as JSON
            echo json_encode($leaderboard);
        } elseif (isset($_POST['sectors'])) {

            $sectors = getSectors($userId);

            // Output sectors as JSON
            echo json_encode($sectors);
        } elseif (isset($_POST['outcome'])) {
            // TODO: Remove, as it will not be used
            $betAmount = $_POST['betAmount'];
            $outcome = getOutcome($userId, $betAmount);

            echo $outcome;
        } elseif (isset($_POST['check-against'])) {
            $betAmount = $_POST['betAmount'];
            $check = checkBalanceAgainstBet($userId, $betAmount);

            echo $check;
        } elseif (isset($_POST['verify-transaction'])) {
            $walletAddress = $_POST['walletAddress'];
            $purchaseAmount = $_POST['purchaseAmount'];
            $uniqueIdentifier = $_POST['uniqueIdentifier'];
            $toWallet = $_POST['toWallet'];
            $tonAmount = $_POST['tonAmount'];
            
            $verify = verifyTransaction($userId, $walletAddress, $toWallet, $purchaseAmount, $tonAmount, $uniqueIdentifier, $tonCenterApiKey);

            echo json_encode($verify);
        } elseif (isset($_POST['verify-lotto-transaction'])) {
            $walletAddress = $_POST['walletAddress'];
            $purchaseAmount = $_POST['purchaseAmount'];
            $uniqueIdentifier = $_POST['uniqueIdentifier'];
            $toWallet = $_POST['toWallet'];
            $tonAmount = $_POST['tonAmount'];
            $lottoId = $_POST['lottoId'];
            
            $verify = verifyTransactionLotto($userId, $walletAddress, $toWallet, $purchaseAmount, $tonAmount, $uniqueIdentifier, $tonCenterApiKey, $lottoId);

            echo json_encode($verify);
        } elseif (isset($_POST['plans'])) {

            $plans = getPlans($userId); 
            
            // Output plans as JSON
            echo json_encode($plans);

        } elseif (isset($_POST['check-winning'])) {

            $winning = checkWinning($userId);

            echo json_encode(['winning' => $winning]);

        } elseif (isset($_POST['transactions'])) {
            $limit = $_POST['limit'];
            $search = $_POST['search'];
            $page = $_POST['page'];

            $transactions = getTransactions($id, $limit, $search, $page);

            echo json_encode($transactions);

        } elseif (isset($_POST['orders'])) {
            $limit = $_POST['limit'];
            $search = $_POST['search'];
            $page = $_POST['page'];

            $orders = getOrders($id, $limit, $search, $page);

            echo json_encode($orders);

        } elseif (isset($_POST['withdrawals'])) {
            $limit = $_POST['limit'];
            $search = $_POST['search'];
            $page = $_POST['page'];

            $withdrawals = getWithdrawals($id, $limit, $search, $page);

            echo json_encode($withdrawals);

        } elseif (isset($_POST['referrals'])) {
            $limit = $_POST['limit'];
            $search = $_POST['search'];
            $page = $_POST['page'];

            $referrals = getReferrals($id, $limit, $search, $page);

            echo json_encode($referrals);
        } elseif (isset($_POST['coinPrices'])) {
            $limit = $_POST['limit'];
            $search = $_POST['search'];
            $page = $_POST['page'];

            $prices = getCoinPrices($id, $limit, $page);

            echo json_encode($prices);

        } elseif (isset($_POST['lotteries'])) {
            $limit = $_POST['limit'];
            $search = $_POST['search'];
            $page = $_POST['page'];

            $prices = getLotteries($limit, $page);

            logger(json_encode($prices));

            echo json_encode($prices);

        } elseif (isset($_POST['userTickets'])) {
            $limit = $_POST['limit'];
            $search = $_POST['search'];
            $page = $_POST['page'];

            logger("User ID: $userId");

            $tickets = getLotteryTickets($userId, $limit, $page);

            logger(json_encode($tickets));

            if ($tickets && $tickets['success'] == true) {
                echo json_encode($tickets['data']);
            } else {
                echo '';
            }

        } elseif (isset($_POST['lottoDetail'])) {
            $id = $_POST['id'];

            $lotto_details = getLotteryData($id);

            echo json_encode($lotto_details);

        } elseif (isset($_POST['winningDetail'])) {
            $id = $_POST['id'];

            $winning_details = getWinningData($id);

            logger(json_encode($winning_details));

            echo json_encode($winning_details);

        } elseif (isset($_POST['transfers'])) {
            $limit = $_POST['limit'];
            $search = $_POST['search'];
            $page = $_POST['page'];

            $transfers = getTransfers($id, $limit, $search, $page);

            echo json_encode($transfers);
        } elseif (isset($_POST['winners'])) {
            $limit = $_POST['limit'];
            $search = $_POST['search'];
            $page = $_POST['page'];

            $winners = getWinners($limit, $page);
            // logger(json_encode($winners));
            echo json_encode($winners);
        } elseif (isset($_POST['convert'])) {
            $from = 'SPIN';
            $to = 'TON';
            $amount = $_POST['amount'];

            $converted = currencyConverter($amount, $from, $to);
            
            $amount = number_format($converted, 2) . ' TON';
            echo json_encode(['amount' => $amount]);
        }  elseif (isset($_POST['buy'])) {
            $to = 'SPIN';
            $amount = $_POST['amount'];
            // $from = $_POST['currency'];
            $from = 'TON';

            $special = checkCoinPrice($amount, $from);
            
            if ($special && $special['percentage'] != null) {
                $amount = number_format($special['coin'], 2) . ' SPIN';
                $percentage = $special['percentage'];
            } else {
                $converted = currencyConverter($amount, $from, $to);
                $amount = number_format($converted, 2) . ' SPIN';
                $percentage = null;
            }
            
            echo json_encode(['amount' => $amount, 'percentage' => $percentage]);
        }  elseif (isset($_POST['getAdminWallet'])) {
            logger(json_encode(['success' => true, 'wallet' => $adminWallet]));
            echo json_encode(['success' => true, 'wallet' => $adminWallet]);
        } elseif (isset($_POST['withdraw'])) {
            $amount = $_POST['amount'];
            $wallet = $_POST['walletAddress'];
            $humanWallet = $_POST['humanWallet'];

            $converted = currencyConverter($amount, 'SPIN', 'TON');

            $withdraw = withdraw($userId, $amount, $wallet, 'TON', $converted, $humanWallet);

            echo json_encode($withdraw);

        } elseif (isset($_POST['transfer'])) {
            $to = $_POST['to'];
            $amount = $_POST['amount'];

            // the amount should be integer
            if (!is_numeric($amount)) {
                echo json_encode(['success' => false, 'message' => 'Invalid amount']);
                exit;
            }

            $myData = getUserData($userId);

            // you can't transfer to yourself
            if ($to == $myData['data']['unique_name']) {
                echo json_encode(['success' => false, 'message' => 'You can\'t transfer to yourself']);
                exit;
            }

            $minimumTransferAmount = minimumTransferAmount($id);
            
            if ($amount < $minimumTransferAmount) {
                echo json_encode(['success' => false, 'message' => 'Minimum transfer amount is ' . number_format($minimumTransferAmount, 2) . ' SpinCoins']);
                exit;
            }

            $receipientId = getUserDataFromUniqueName($to);

            // check if the success is false
            if (!$receipientId['success']) {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit;
            }

            $transfer = transfer($userId, $receipientId['data']['telegram_user_id'], $amount);

            echo json_encode($transfer);
        } elseif (isset($_POST['check-unque-name'])) {
            $value = $_POST['value'];

            $receipientData = getUserDataFromUniqueName(trim($value));

            // check if the success is false
            if (!$receipientData['success']) {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit;
            } else {
                echo json_encode(['success' => true, 'message' => "Sending to {$receipientData['data']['unique_name']}"]);
            }
        } elseif (isset($_POST['check-token'])) {
            logger(json_encode(['success' => true, 'message' => 'Token is valid']));
            echo json_encode(['success' => true, 'message' => 'Token is valid']);
        } elseif (isset($_POST['liveDraw'])) {
            echo liveDraw();
        } elseif (isset($_GET['liveDraw'])) {

        } else {
            http_response_code(400);
            logger('Invalid request');
            echo json_encode(['message' => 'Invalid request']);
        }
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['message' => 'Unauthorized: ' . $e->getMessage(), 'command' => 'reload']);
    }
} else {
    http_response_code(400);
    echo json_encode(['message' => 'Authorization token not provided']);
}