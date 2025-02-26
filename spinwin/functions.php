<?php
require 'config.php';

// generate uuid
function generateUuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    // example output: 1b9d6bcd-bbfd-4b2d-9b5d-ab8dfbbd4c1a
}

function getUserId($telegramUserId) {
    try {
        $conn = getDbConnection();

        // Check if user already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE telegram_user_id = ?");
        $stmt->bind_param('s', $telegramUserId);
        $stmt->execute();
        $stmt->bind_result($id);
        $stmt->fetch();
        $stmt->close();

        if (!$id) {
            return 0;
        } else {
            return $id;
        }
    } catch (Exception $e) {
        return 0;
    }
}

function referralPercentage($uniqueName) {
    // TODO: Implement referral percentage logic
    return 10;
}

function saveReferrals($referredId, $referrerUniqueName) {
    try {
        $conn = getDbConnection();

        $reward = getSettings('referral_bonus');

        $percentage = referralPercentage($referrerUniqueName);

        // Retrieve the internal user_id using the Telegram user ID
        $stmt = $conn->prepare("SELECT id FROM users WHERE unique_name = ?");
        $stmt->bind_param('s', $referrerUniqueName);
        $stmt->execute();
        $stmt->bind_result($referrerId);
        $stmt->fetch();
        $stmt->close();

        if (!$referrerId) {
            error_log("Referrer does not exist.");
            $conn->close();
            return ['success' => false, 'message' => 'Referrer does not exist.'];
        }

        // Check if the referral already exists
        $stmt = $conn->prepare("SELECT id FROM referrals WHERE referred_user_id = ? AND referrer_user_id = ?");
        $stmt->bind_param('ii', $referredId, $referrerId);
        $stmt->execute();
        $stmt->bind_result($referralId);
        $stmt->fetch();
        $stmt->close();

        if ($referralId) {
            error_log("Referred exists.");
            $conn->close();
            return ['success' => false, 'message' => 'Referral already exists.'];
        }

        // Save the referral
        $stmt = $conn->prepare("INSERT INTO referrals (referred_user_id, referrer_user_id, referral_bonus) VALUES (?, ?, ?)");
        $stmt->bind_param('iid', $referredId, $referrerId, $percentage);
        $stmt->execute();
        $stmt->close();

        // Reward the referred user (insert to user wallet if not have or update)
        $stmt = $conn->prepare("SELECT id FROM user_wallets WHERE user_id = ?");
        $stmt->bind_param('i', $referredId);
        $stmt->execute();
        $stmt->bind_result($walletId);
        $stmt->fetch();
        $stmt->close();
        
        if (!$walletId) {
            $stmt = $conn->prepare("INSERT INTO user_wallets (user_id, coin_balance) VALUES (?, ?)");
            $stmt->bind_param('ii', $referredId, $reward);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("UPDATE user_wallets SET coin_balance = coin_balance + ? WHERE user_id = ?");
            $stmt->bind_param('ii', $reward, $referredId);
            $stmt->execute();
            $stmt->close();
        }

        $conn->close();

        error_log("Referral saved successfully.");

        return ['success' => true, 'message' => 'Referral saved successfully.'];
    } catch (Exception $e) {
        error_log($e->getMessage());
        return ['success' => false, 'message' => 'An error occurred.'];
    }
}

function registerOrAuthenticate($username, $telegramUserId, $referrerUniqueName) {
    try {
        $conn = getDbConnection();

        // Check if user already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE telegram_user_id = ?");
        $stmt->bind_param('s', $telegramUserId);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 0) {
            // Create new user
            $generator = new \Nubs\RandomNameGenerator\Alliteration();
            $uniqueName = str_replace(' ', '', $generator->getName());

            $stmt = $conn->prepare("INSERT INTO users (telegram_user_id, unique_name, chat_id, username) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $telegramUserId, $uniqueName, $telegramUserId, $username);
            $stmt->execute();

            $referral = saveReferrals($stmt->insert_id, $referrerUniqueName);

            $response = ['success' => true, 'message' => "Welcome to SpinCoin Mini App, $username! Enjoy the game!"];
        } else {
            $response = ['success' => true, 'message' => "Welcome back, $username!"];
        }

        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
        error_log($e->getMessage());
    }
    
    return json_encode($response);
}

function checkBalance($userId) {
    // check from the database
    try {
        $telegramUserId = $userId;
        
        $conn = getDbConnection();

        // Retrieve the internal user_id using the Telegram user ID
        $stmt = $conn->prepare("SELECT id FROM users WHERE telegram_user_id = ?");
        $stmt->bind_param('s', $telegramUserId);
        $stmt->execute();
        $stmt->bind_result($id);
        $stmt->fetch();
        $stmt->close();

        if (!$id) {
            $conn->close();
            return 0;
        }

        $stmt = $conn->prepare("SELECT coin_balance FROM user_wallets WHERE user_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($balance);
        $stmt->fetch();

        $response = $balance;

        $stmt->close();
        $conn->close();

        // turn it into intiger 
        $response = intval($response);

        return $response;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return 0;
    }
}

function getLotteryTickets($userId, $limit, $page) {
    // get all lottery tickets
    try {
        $conn = getDbConnection();

        // Retrieve the internal user_id using the Telegram user ID
        $stmt = $conn->prepare("SELECT id FROM users WHERE telegram_user_id = ?");
        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $stmt->bind_result($id);
        $stmt->fetch();
        $stmt->close();

        $offset = ($page - 1) * $limit;

        if (!$id) {
            $conn->close();
            return ['success' => false, 'message' => 'User does not exist.'];
        }

        $stmt = $conn->prepare("SELECT * FROM user_tickets WHERE user_id = ? LIMIT ? OFFSET ?");
        $stmt->bind_param('iii', $id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $tickets = $result->fetch_all(MYSQLI_ASSOC);

        $stmt->close();

        foreach ($tickets as &$ticket) {
            $lottoData = getLotteryData($ticket['lotto_id']);
            $ticket['status'] = $lottoData['status'];
            $ticket['price'] = $lottoData['price'];
            $ticket['currency'] = $lottoData['currency'];
        }

        $conn->close();

        return ['success' => true, 'data' => $tickets];
    } catch (Exception $e) {
        error_log($e->getMessage());
        return ['success' => false, 'message' => 'An error occurred.'];
    }
}

function checkWinning($userId) {
    // check from the database
    try {
        $telegramUserId = $userId;
        
        $conn = getDbConnection();

        // Retrieve the internal user_id using the Telegram user ID
        $stmt = $conn->prepare("SELECT id FROM users WHERE telegram_user_id = ?");
        $stmt->bind_param('s', $telegramUserId);
        $stmt->execute();
        $stmt->bind_result($id);
        $stmt->fetch();
        $stmt->close();

        if (!$id) {
            $conn->close();
            return 0;
        }

        $stmt = $conn->prepare("SELECT coin_balance FROM winnings WHERE user_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($balance);
        $stmt->fetch();

        $response = $balance;

        $stmt->close();
        $conn->close();

        // error_log("User ID {$id} has a winning balance: {$balance} SpinCoins");

        // turn it into intiger 
        $response = intval($response);

        return $response;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return 0;
    }
}



function withdraw($userId, $amount, $wallet, $currency, $currencyAmount) {
    try {
        $minimumWithdrawalAmount = minimumWithdrawalAmount($userId);

        if ($amount < $minimumWithdrawalAmount) {
            return ['success' => false, 'message' => 'The minimum withdrawal amount is ' . number_format($minimumWithdrawalAmount) . ' SpinCoins.'];
        }

        $conn = getDbConnection();

        // use transaction and check if the user has enough winning balance, and if true, deduct the amount from winning and insert into withdrawal table along with transaction table and all that
        $conn->begin_transaction();

        // Retrieve the internal user_id using the Telegram user ID
        $stmt = $conn->prepare("SELECT id FROM users WHERE telegram_user_id = ?");
        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $stmt->bind_result($id);
        $stmt->fetch();
        $stmt->close();
        
        if (!$id) {
            $conn->close();
            return ['success' => false, 'message' => 'User does not exist.', 'balance' => 0];
        }

        // Check if the user has a winnings wallet
        $stmt = $conn->prepare("SELECT id, coin_balance FROM winnings WHERE user_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($walletId, $balance);
        $stmt->fetch();
        $stmt->close();

        if (!$walletId) {
            $conn->close();
            return ['success' => false, 'message' => 'You do not have any winnings to withdraw.', 'balance' => 0];
        }

        // Check if the user has enough balance to withdraw
        if ($balance < $amount) {
            $conn->close();
            return ['success' => false, 'message' => 'You do not have enough winnings to withdraw.', 'balance' => $balance];
        }

        // Generate a unique transaction number
        $trxNumber = generateUuid();
        $trxType = 'withdraw';

        // Record the transaction
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, coin_amount, trx_number, trx_type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iiss', $id, $amount, $trxNumber, $trxType);
        $stmt->execute();
        $trxId = $stmt->insert_id; // Get the new transaction ID
        $stmt->close();

        // Deduct the amount from the user's winnings balance
        $stmt = $conn->prepare("UPDATE winnings SET coin_balance = coin_balance - ? WHERE user_id = ?");
        $stmt->bind_param('ii', $amount, $id);
        $stmt->execute();
        $stmt->close();

        // Insert the withdrawal record
        $stmt = $conn->prepare("INSERT INTO withdrawals (user_id, wallet, coin_amount, currency, currency_amount, trx_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isdsdi', $id, $wallet, $amount, $currency, $currencyAmount, $trxId);
        $stmt->execute();
        $stmt->close();

        // Commit the transaction
        $conn->commit();
        $conn->close();

        return ['success' => true, 'message' => 'We have received your withdrawal request and will process it soon!', 'balance' => $balance - $amount];
    } catch (Exception $e) {
        // Rollback the transaction if something failed
        $conn->rollback();
        error_log($e->getMessage());
        $conn->close();

        return ['success' => false, 'message' => 'An error occurred.'];
    }
}

function getUserDataFromId($id) {
    // get all user data is that user exists
    try {
        $conn = getDbConnection();

        // Retrieve the internal user_id using the Telegram user ID
        $stmt = $conn->prepare("SELECT id, telegram_user_id, unique_name, chat_id, username FROM users WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($id, $telegramUserId, $uniqueName, $chatId, $username);
        $stmt->fetch();
        $stmt->close();

        if (!$id) {
            $conn->close();
            return ['success' => false, 'message' => 'User does not exist.'];
        }

        $conn->close();

        return ['success' => true, 'data' => ['id' => $id, 'telegram_user_id' => $telegramUserId, 'unique_name' => $uniqueName, 'chat_id' => $chatId, 'username' => $username]];
    } catch (Exception $e) {
        error_log($e->getMessage());
        return ['success' => false, 'message' => 'An error occurred.'];
    }
}

function getUserData($userId) {
    // get all user data is that user exists
    try {
        $conn = getDbConnection();

        // Retrieve the internal user_id using the Telegram user ID
        $stmt = $conn->prepare("SELECT id, telegram_user_id, unique_name, chat_id, username FROM users WHERE telegram_user_id = ?");
        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $stmt->bind_result($id, $telegramUserId, $uniqueName, $chatId, $username);
        $stmt->fetch();
        $stmt->close();

        if (!$id) {
            $conn->close();
            return ['success' => false, 'message' => 'User does not exist.'];
        }

        $conn->close();

        return ['success' => true, 'data' => ['id' => $id, 'telegram_user_id' => $telegramUserId, 'unique_name' => $uniqueName, 'chat_id' => $chatId, 'username' => $username]];
    } catch (Exception $e) {
        error_log($e->getMessage());
        return ['success' => false, 'message' => 'An error occurred.'];
    }
}

function getUserDataFromUniqueName($uniqueName) {
    // get all user data is that user exists
    try {
        $conn = getDbConnection();

        // Retrieve the internal user_id using the Telegram user ID
        $stmt = $conn->prepare("SELECT id, telegram_user_id, unique_name, chat_id, username FROM users WHERE unique_name = ?");
        $stmt->bind_param('s', $uniqueName);
        $stmt->execute();
        $stmt->bind_result($id, $telegramUserId, $uniqueName, $chatId, $username);
        $stmt->fetch();
        $stmt->close();

        if (!$id) {
            $conn->close();
            return ['success' => false, 'message' => 'User does not exist.'];
        }

        $conn->close();

        return ['success' => true, 'data' => ['id' => $id, 'telegram_user_id' => $telegramUserId, 'unique_name' => $uniqueName, 'chat_id' => $chatId, 'username' => $username]];
    } catch (Exception $e) {
        error_log($e->getMessage());
        return ['success' => false, 'message' => 'An error occurred.'];
    }
}

function addOrUpdateBalance($userId, $amount, $type, $currency, $currencyAmount) {
    $amount = intval($amount);

    // amount cannot be negative
    if ($amount < 0) {
        return json_encode(['success' => false, 'message' => 'Invalid amount. Amount cannot be negative.']);
    }

    if ($type == 'bet' || $type == 'withdraw') {
        // turn the amount into negative
        $amount = -$amount;
    }

    try {
        $telegramUserId = $userId;
        
        $conn = getDbConnection();

        // Retrieve the internal id using the Telegram user ID
        $stmt = $conn->prepare("SELECT id FROM users WHERE telegram_user_id = ?");
        $stmt->bind_param('s', $telegramUserId);
        $stmt->execute();
        $stmt->bind_result($id);
        $stmt->fetch();
        $stmt->close();

        if (!$id) {
            $conn->close();
            $response = ['success' => false, 'message' => 'User does not exist.', 'balance' => 0];
            return json_encode($response);
        }

        // Check if the user has a wallet
        if ($type == 'withdraw') {
            $stmt = $conn->prepare("SELECT id FROM winnings WHERE user_id = ?");
        } else {
            $stmt = $conn->prepare("SELECT id FROM user_wallets WHERE user_id = ?");
        }
        
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($walletId);
        $stmt->fetch();
        $stmt->close();

        // Start a transaction
        $conn->begin_transaction();

        try {
            // Generate a unique transaction number
            $trxNumber = generateUuid();

            // Record the transaction
            $stmt = $conn->prepare("INSERT INTO transactions (user_id, coin_amount, currency_amount, currency, trx_number, trx_type) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iiisss', $id, $amount, $currencyAmount, $currency, $trxNumber, $type);
            $stmt->execute();
            $stmt->close();

            if (!$walletId) {
                // Create a new wallet for the user
                if ($type == 'withdraw') {
                    $stmt = $conn->prepare("INSERT IGNORE INTO winnings (user_id, coin_balance) VALUES (?, ?)");
                } else {
                    $stmt = $conn->prepare("INSERT IGNORE INTO user_wallets (user_id, coin_balance) VALUES (?, ?)");
                }
                
                $stmt->bind_param('ii', $id, $amount);
                $stmt->execute();
                $stmt->close();
            } else {
                // Update the user's balance
                if ($type == 'withdraw') {
                    $stmt = $conn->prepare("UPDATE winnings SET coin_balance = coin_balance + ? WHERE user_id = ?");
                } else {
                    $stmt = $conn->prepare("UPDATE user_wallets SET coin_balance = coin_balance + ? WHERE user_id = ?");
                }
                
                $stmt->bind_param('ii', $amount, $userId);
                $stmt->execute();
                $stmt->close();
            }

            // Commit the transaction
            $conn->commit();
            $conn->close();
    
            $newBalance = checkBalance($userId);
            $newWinning = checkWinning($userId);
    
            $response = ['success' => true, 'message' => 'Balance updated successfully.', 'balance' => $newBalance, 'winning' => $newWinning, 'trxNumber' => $trxNumber, 'type' => $type];
        } catch (Exception $e) {
            // Rollback the transaction if something failed
            $conn->rollback();
            error_log($e->getMessage());
            $conn->close();

            $response = ['success' => false, 'message' => 'An error occurred.'];
        }

        return json_encode($response);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return json_encode(['success' => false, 'message' => 'An error occurred.']);
    }
}

function minimumBetAmount($userId) {
    // Minimum bet amount in SpinCoins
    $minBetAmount = 2500;

    return $minBetAmount;
}

function minimumWithdrawalAmount($userId) {
    $convertTonToSpin = currencyConverter(1, 'TON', 'SPIN');
    // Minimum withdrawal amount in SpinCoins
    $minWithdrawalAmount = $convertTonToSpin;

    return $minWithdrawalAmount;
}

function minimumTransferAmount($userId) {
    $convertTonToSpin = currencyConverter(1, 'TON', 'SPIN');
    // Minimum trasnfer amount in SpinCoins
    $minTransferAmount = $convertTonToSpin;

    return $minTransferAmount;
}

function minimumBuyAmount($userId) {
    $convertTonToSpin = currencyConverter(0.5, 'TON', 'SPIN');
    // Minimum buy amount in SpinCoins
    $minBetAmount = $convertTonToSpin;

    return $minBetAmount;
}

function getSettings($name) {
    $settings = [
        'referral_bonus' => 5000,
        'transfer_fee' => 0,
        'transfer_fee_type' => 'percentage', // plain
        'withdrawalFee' => 0,
        'withdrawalFeeType' => 'percentage', // plain
    ];

    return $settings[$name];
}

function transfer($userId, $to, $amount) {
    try {
        $conn = getDbConnection();

        $transferFee = getSettings('transfer_fee');
        $transferFeeType = getSettings('transfer_fee_type');

        if ($transferFeeType == 'percentage') {
            $totalTransferFee = $amount * $transferFee / 100;
        } else {
            $totalTransferFee = $transferFee;
        }

        // Start a transaction
        $conn->begin_transaction();

        // Retrieve the internal user_id using the Telegram user ID
        $stmt = $conn->prepare("SELECT id FROM users WHERE telegram_user_id = ?");
        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $stmt->bind_result($id);
        $stmt->fetch();
        $stmt->close();

        if (!$id) {
            $conn->close();
            return ['success' => false, 'message' => 'Sender does not exist.'];
        }

        // Retrieve the internal user_id of the recipient
        $stmt = $conn->prepare("SELECT id FROM users WHERE telegram_user_id = ?");
        $stmt->bind_param('s', $to);
        $stmt->execute();
        $stmt->bind_result($recipientId);
        $stmt->fetch();
        $stmt->close();

        if (!$recipientId) {
            $conn->close();
            return ['success' => false, 'message' => 'Recipient does not exist.'];
        }

        // Check if the sender has a winnings wallet
        $stmt = $conn->prepare("SELECT id, coin_balance FROM winnings WHERE user_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($walletId, $balance);
        $stmt->fetch();
        $stmt->close();

        if (!$walletId || $balance < $amount) {
            error_log("Sender has no winning wallet or {$balance} is lessa than {$amount}. Checking user wallet.");
            // Check if the sender has a user wallet
            $stmt = $conn->prepare("SELECT id, coin_balance FROM user_wallets WHERE user_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->bind_result($userWalletId, $userBalance);
            $stmt->fetch();
            $stmt->close();

            if (!$userWalletId || $userBalance < $amount) {
                $conn->close();
                return ['success' => false, 'message' => 'Insufficient balance.'];
            }

            error_log("Sender has user wallet and {$userBalance} is greater than {$amount}. Deducting from user wallet.");

            // Deduct the amount from the sender's user wallet
            $stmt = $conn->prepare("UPDATE user_wallets SET coin_balance = coin_balance - ? WHERE user_id = ?");
            $stmt->bind_param('ii', $amount, $id);
            $stmt->execute();
            $stmt->close();
        } else {
            error_log("Sender has a winning wallet {$balance} greater than {$amount}. Deducting from winnings wallet.");
            // Deduct the amount from the sender's winnings wallet
            $stmt = $conn->prepare("UPDATE winnings SET coin_balance = coin_balance - ? WHERE user_id = ?");
            $stmt->bind_param('ii', $amount, $id);
            $stmt->execute();
            $stmt->close();
        }

        // Check if the recipient has a user wallet
        $stmt = $conn->prepare("SELECT id FROM user_wallets WHERE user_id = ?");
        $stmt->bind_param('i', $recipientId);
        $stmt->execute();
        $stmt->bind_result($receiverWalletId);
        $stmt->fetch();
        $stmt->close();

        $totalReceivedAmount = $amount - $totalTransferFee;
        error_log("Transfer fee: {$totalTransferFee}");
        error_log("Total received amount: {$totalReceivedAmount}");

        if (!$receiverWalletId) {
            error_log("Recipient {$recipientId} does not have a user wallet. Creating one...");
            // Create a new user wallet for the recipient
            $stmt = $conn->prepare("INSERT INTO user_wallets (user_id, coin_balance) VALUES (?, ?)");
            $stmt->bind_param('id', $recipientId, $totalReceivedAmount);
            $stmt->execute();
            $stmt->close();
        } else {
            error_log("Recipient {$recipientId} has a user wallet. Updating balance...");
            // Add the amount to the recipient's winnings balance
            $stmt = $conn->prepare("UPDATE user_wallets SET coin_balance = coin_balance + ? WHERE user_id = ?");
            $stmt->bind_param('di', $totalReceivedAmount, $recipientId);
            $stmt->execute();
            $stmt->close();
        }

        // Log the transfer for sender
        $referenceNumber = generateUuid();
        $transactionType = 'sent';
        $stmt = $conn->prepare("INSERT INTO transfers (user_id, assoc_user_id, amount, transfer_fee, reference_number, transaction_type) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iiddss', $id, $recipientId, $totalReceivedAmount, $totalTransferFee, $referenceNumber, $transactionType);
        $stmt->execute();
        $stmt->close();

        // Log the transfer for recipient
        $transactionType = 'received';
        $stmt = $conn->prepare("INSERT INTO transfers (user_id, assoc_user_id, amount, transfer_fee, reference_number, transaction_type) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iiddss', $recipientId, $id, $totalReceivedAmount, $totalTransferFee, $referenceNumber, $transactionType);
        $stmt->execute();
        $stmt->close();

        // Log the transaction for the sender
        $trxNumber = generateUuid();
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, coin_amount, trx_number, trx_type) VALUES (?, ?, ?, ?)");
        $trxType = 'outgoing_transfer';
        $stmt->bind_param('iiss', $id, $amount, $trxNumber, $trxType);
        $stmt->execute();
        $stmt->close();

        // Log the transaction for the recipient
        $trxNumber = generateUuid();
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, coin_amount, trx_number, trx_type) VALUES (?, ?, ?, ?)");
        $trxType = 'incoming_transfer';
        $stmt->bind_param('iiss', $recipientId, $amount, $trxNumber, $trxType);
        $stmt->execute();
        $stmt->close();

        // Commit the transaction
        $conn->commit();
        $conn->close();

        return ['success' => true, 'message' => 'Transfer successful.'];
    } catch (Exception $e) {
        // Rollback the transaction if something failed
        $conn->rollback();
        error_log($e->getMessage());
        $conn->close();

        return ['success' => false, 'message' => 'An error occurred during the transfer.'];
    }
}

function checkBalanceAgainstBet($userId, $betAmount) {
    $betAmount = intval($betAmount);
    if (minimumBetAmount($userId) > $betAmount) {
        $hasEnoughBalance = false;
        $newBalance = 0;
        $outcome = [
            'label' => '',
            'sound' => '',
            'message' => 'The minimum bet amount is ' . number_format(minimumBetAmount($userId)) . ' SpinCoins. Please enter a valid amount.',
            'newBalance' => '',
            'newWinningBalance' => ''
        ];
        $newWinningBalance = 0;
    } else {
        // Retrieve user's balance from the database
        // For demonstration, we'll assume a balance of 1000 SpinCoins
        $userBalance = checkBalance($userId); // Replace with actual retrieval logic
        $newWinningBalance = checkWinning($userId);
        
        $hasEnoughBalance = ($userBalance + $newWinningBalance) >= $betAmount;
        $outcome = json_decode(getOutcome($userId, $betAmount));

        if ($hasEnoughBalance) {
            $newBalance = $userBalance - $betAmount;
            if ($newBalance <= 0) {
                $newWinningBalance = ($newWinningBalance - $betAmount) + $userBalance;
            }
        } else {
            $outcome = [
                'label' => '',
                'sound' => '',
                'message' => 'You do not have enough balance to place this bet. Buy more SpinCoins and try again.',
                'newBalance' => '',
                'newWinningBalance' => ''
            ];
            $newBalance = $userBalance;
        }
    }    

    // return json_encode(['hasEnoughBalance' => $hasEnoughBalance]);
    return json_encode([
        'hasEnoughBalance' => $hasEnoughBalance, 
        'outcome' => $outcome, 
        'newBalance' => $newBalance,
        'newWinningBalance' => $newWinningBalance
    ]);
}

function getLeaderBoard($userId, $limit) {

    $leaderboard = [
        ['name' => 'Alice', 'score' => 5000],
        ['name' => 'Bob', 'score' => 4500],
        ['name' => 'Charlie', 'score' => 4000],
        ['name' => 'David', 'score' => 3500],
        ['name' => 'Eve', 'score' => 3000],
        // ... more players
    ];

    return $leaderboard;
}


function currencyConverter($amount, $from, $to) {
    $exchangeRate = getExchangeRate($from, $to);

    return $amount * $exchangeRate;
}

function getExchangeRate($from, $to) {
    // Exchange rates based on the given data
    $exchangeRates = [
        'ETB' => 1,          // Base currency
        'TON' => 740,        // 1 TON = 740 ETB
        'USD' => 135,        // 1 USD = 135 ETB
        'USDC' => 135,
        'USDT' => 135,
        'SPIN' => 0.01,      // 1 SPIN = 0.01 ETB
    ];

    // Check if currencies exist in the exchange rates array
    if (!isset($exchangeRates[$from]) || !isset($exchangeRates[$to])) {
        throw new Exception("Unsupported currency: $from or $to");
    }

    // Handle direct conversions
    if ($from == 'ETB') {
        $rate = 1 / $exchangeRates[$to]; // ETB to target currency
    } elseif ($to == 'ETB') {
        $rate = $exchangeRates[$from];   // Currency to ETB
    } else {
        // Convert from $from to ETB, then ETB to $to
        $rate = $exchangeRates[$from] / $exchangeRates[$to];
    }

    return $rate;
}

function getPlans($userId) {
    // Base exchange rate: 1 ETB = 100 SpinCoins
    $etbToSpinCoinRate = 100; // 1 ETB = 100 SpinCoins

    // Base plans with ETB prices and plan-specific bonuses
    $basePlans = [
        [
            'id' => 1,
            'label' => 'Basic',
            'price_etb' => 100,
            'plan_bonus_percent' => 0,   // No plan bonus for Basic
        ],
        [
            'id' => 2,
            'label' => 'Standard',
            'price_etb' => 300,
            'plan_bonus_percent' => 30,  // 30% more SpinCoins
        ],
        [
            'id' => 3,
            'label' => 'Premium',
            'price_etb' => 500,
            'plan_bonus_percent' => 50,  // 50% more SpinCoins
        ],
    ];

    // Currency-specific bonuses
    $currencies = [
        'ETB' => [
            'currency_bonus_percent' => 0,
            'info' => 'Get normal SpinCoins',
        ],
        'TON' => [
            'currency_bonus_percent' => 20,
            'info' => 'Get 20% more SpinCoins',
        ],
        'USD' => [
            'currency_bonus_percent' => 25,
            'info' => 'Get 25% more SpinCoins',
        ],
        'USDC' => [
            'currency_bonus_percent' => 25,
            'info' => 'Get 25% more SpinCoins',
        ],
        'USDT' => [
            'currency_bonus_percent' => 25,
            'info' => 'Get 25% more SpinCoins',
        ],
    ];

    $plans = [];

    foreach ($basePlans as $plan) {
        $price_etb = $plan['price_etb'];
        $base_spincoins = $price_etb * $etbToSpinCoinRate;
        $plan_bonus_percent = $plan['plan_bonus_percent'];

        // Apply plan bonus
        $spincoins_after_plan_bonus = $base_spincoins * (1 + $plan_bonus_percent / 100);

        $options = [];

        foreach ($currencies as $currency => $currencyData) {
            // Price in target currency
            if ($currency == 'ETB') {
                $price_in_currency = $price_etb;
            } else {
                $exchangeRate = getExchangeRate('ETB', $currency);
                $price_in_currency = $price_etb * $exchangeRate;
                $price_in_currency = round($price_in_currency, 2);
            }

            // Apply currency bonus
            $currency_bonus_percent = $currencyData['currency_bonus_percent'];
            if ($currency == 'ETB' && ($plan['label'] == 'Standard' || $plan['label'] == 'Premium')) {
                $plan_bonus_percent = $plan['plan_bonus_percent'];
                $currencyData['info'] = 'Get ' . $plan['plan_bonus_percent'] . '% more SpinCoins';
            } else {
                if ($currency_bonus_percent == 0) {
                    $currencyData['info'] = 'Get normal SpinCoins';
                } else {
                    $currencyData['info'] = 'Get ' . $currency_bonus_percent + $plan_bonus_percent . '% more SpinCoins';
                }
            }
            $total_spincoins = $base_spincoins * (1 + ($currency_bonus_percent + $plan_bonus_percent) / 100);
            $total_spincoins = round($total_spincoins);
            $total_additional_spincoins = $total_spincoins - $base_spincoins;

            // Build the options array
            $options[$currency] = [
                'price' => $price_in_currency,
                'base_spincoin' => $base_spincoins,
                'spincoin_after_plan_bonus' => $spincoins_after_plan_bonus,
                'currency_bonus_percent' => $currency_bonus_percent,
                'plan_bonus_percent' => $plan_bonus_percent,
                'total_bonus_percent' => $currency_bonus_percent + $plan_bonus_percent,
                'bonus_spincoin' => $total_additional_spincoins,
                'spincoin' => $total_spincoins,
                'info' => $currencyData['info'],
            ];
        }

        $plans[] = [
            'id' => $plan['id'],
            'label' => $plan['label'],
            'options' => $options,
        ];
    }

    return $plans;
}

function getWithdrawalAmount($spincoinAmount, $toCurrency) {
    // Base SpinCoin to ETB exchange rate
    $spinToETBRate = 0.01; // 1 SpinCoin = 0.01 ETB

    // Currency-specific withdrawal adjustments
    $withdrawalAdjustments = [
        'ETB' => 0,
        'TON' => 20,
        'USD' => 25,
        'USDC' => 25,
        'USDT' => 25,
    ];

    // Calculate the base ETB amount
    $etbAmount = $spincoinAmount * $spinToETBRate;

    // Adjust the ETB amount based on the withdrawal adjustment percentage
    $adjustmentPercent = $withdrawalAdjustments[$toCurrency];
    $adjustedETBAmount = $etbAmount * (1 - $adjustmentPercent / 100);

    // Convert the adjusted ETB amount to the target currency
    $exchangeRate = getExchangeRate('ETB', $toCurrency);
    $withdrawalAmount = $adjustedETBAmount * $exchangeRate;

    // Round the withdrawal amount to 2 decimal places
    $withdrawalAmount = round($withdrawalAmount, 2);

    return $withdrawalAmount;
}


function getSectors($userId) {
    $sectors = [
        ['label' => 'Thank you'],
        ['label' => '20X'],
        // ['label' => 'Thank you'],
        ['label' => '2X'],
        ['label' => '10X'],
        // ['label' => 'Thank you'],
        ['label' => '1X'],
        ['label' => '5X'],
        ['label' => '0X'],
        ['label' => '15X'],
        // ['label' => '0X'],
        ['label' => '3X']
    ];

    return $sectors;
}

function getSectorsValues($userId) {
    // return an array of values only from the sectors
    $sectors = getSectors($userId);
    $values = array_map(function($sector) {
        return $sector['label'];
    }, $sectors);

    return $values;
}

function getSectorsA($userId) {
    $sectors = [
        ['label' => '1'],
        ['label' => '2'],
        ['label' => '3'],
        ['label' => '4'],
        ['label' => '5'],
        ['label' => '6'],
        ['label' => '7'],
        ['label' => '8'],
        ['label' => '9'],
        ['label' => '10'],
        ['label' => '11'],
        ['label' => '12'],
    ];

    return $sectors;
}

function getBiasedOutcome($outcomes, $betAmount, $userId) {
    // get an outcome based on the betAmount
    $newWinningBalance = checkWinning($userId);

    $converted1 = currencyConverter(0.4, 'TON', 'SPIN');
    $converted2 = currencyConverter(0.8, 'TON', 'SPIN');

    if ($betAmount > $converted1 || ($newWinningBalance + $betAmount * 3) > $converted2) {
        $weights = [
            'Thank you' => 0.4, // 40%
            '0X' => 0.3,       // 30%
            '1X' => 0.24,       // 24%
            '2X' => 0.049,        // 4.9%
            '3X' => 0.01,        // 1%
            '5X' => 0.001,       // 0.1%
        ];

        // error_log("Rigged bad faith");
    } else {
        $weights = [
            'Thank you' => 0.1, // 10%
            '0X' => 0.1,       // 10%
            '1X' => 0.25,       // 25%
            '2X' => 0.44,        // 44%
            '3X' => 0.105,        // 10.5%
            '5X' => 0.005,       // 0.5%
        ];

        // error_log("Rigged in good faith");
    }


    // $weights = [
    //     'Thank you' => 0.1, // 10%
    //     '0X' => 0.1,       // 10%
    //     '1X' => 0.1,       // 10%
    //     '2X' => 0.4,        // 40%
    //     '3X' => 0.25,        // 25%
    //     '5X' => 0.5,       // 5%
    // ];


    // Calculate cumulative probabilities
    $cumulative = [];
    $sum = 0;
    
    foreach ($outcomes as $outcome) {
        $label = $outcome['label']; // Ensure $outcome is treated as an array
        if (isset($weights[$label])) {
            $sum += $weights[$label];
            $cumulative[] = ['label' => $label, 'cumulative' => $sum];
        }
    }

    // Generate a random float between 0 and the sum of all weights
    $rand = mt_rand() / mt_getrandmax() * $sum; // Adjusted for non-unity weights

    // Find the outcome corresponding to the random value
    foreach ($cumulative as $entry) {
        if ($rand <= $entry['cumulative']) {
            return $entry['label'];
        }
    }

    // Fallback in case something goes wrong
    return $outcomes[array_rand($outcomes)]['label'];
}

function liveDraw() {
    // Simulate a draw time (e.g., the draw happens at a specific time)
    $drawTime = strtotime('2025-01-15 03:55:00'); // Replace with your actual draw time
    $currentTime = time();

    if ($currentTime < $drawTime) {
        // If the draw has not happened yet, return the countdown
        $timeLeft = $drawTime - $currentTime;
        return json_encode([
            'status' => 'countdown',
            'timeLeft' => $timeLeft
        ]);
    } else {
        // If the draw has happened, return the winners and the draw timestamp
        $winners = [
            ['ticket' => '326945875', 'amount' => '100 TON'],
            ['ticket' => '987654321', 'amount' => '50 TON'],
            ['ticket' => '456789123', 'amount' => '25 TON']
        ];

        return json_encode([
            'status' => 'draw',
            'winners' => $winners,
            'drawnOn' => date('Y-m-d H:i:s', $drawTime), // Format the draw time
            'currentTime' => date('Y-m-d H:i:s', $currentTime) // Format the current time
        ]);
    }
}

function getOutcome($userId, $betAmount) {
     
    $betAmount = intval($betAmount);

    // Deduct the bet amount from user's balance
    // Retrieve user's current balance from the database
    $userBalance = checkBalance($userId); // Replace with actual retrieval logic
    $newWinningBalance = checkWinning($userId);

    error_log("User {$userId} betted: {$betAmount} SpinCoins");
    error_log("User {$userId} balance: {$userBalance} SpinCoins");

    
    $newBalance = $userBalance - $betAmount;

    // if ($newBalance < 0) {
    //     $allBalance = $userBalance + $newWinningBalance;
    //     if ($allBalance < 0) {
    //         error_log("Completely less than zero");
    //     } else {
    //         $decuctible = $betAmount - $userBalance;
    //         error_log("Convert the remaining amount from winning to user balance {$decuctible}");
    //     }

    //     // error_log("Less than 0 after bet");
    //     return false;
    // }
    
    // Update the user's balance in the database
    // Replace with actual update logic

    // Determine the outcome
    // $outcomes = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
    // $outcomes = getSectorsValues($userId);
    $outcomes = getSectors($userId);
    // $selectedOutcome = $outcomes[array_rand($outcomes)];
    // $selectedOutcome = 'Thank you';
    $selectedOutcome = getBiasedOutcome($outcomes, $betAmount, $userId);

    // Map the selected outcome to the appropriate sound file
    $soundFile = 'won.wav';

    error_log("User {$userId} outcome: $selectedOutcome");
    error_log("User {$userId} new balance: $newBalance");

    switch ($selectedOutcome) {
        case '1X':
            $betBalance = $betAmount;
            $winOrLose = 'win';
            $message = "You won " . number_format($betBalance) . " SpinCoins.";
            break;
        case '2X':
            $betBalance = $betAmount * 2;
            $winOrLose = 'win';
            $message = "You won " . number_format($betBalance) . " SpinCoins.";
            break;
        case '3X':
            $betBalance = $betAmount * 3;
            $winOrLose = 'win';
            $message = "You won " . number_format($betBalance) . " SpinCoins.";
            break;
        case '5X':
            // jackpot
            $betBalance = $betAmount * 5;
            $winOrLose = 'win';
            $message = "You won " . number_format($betBalance) . " SpinCoins.";
            break;
        case 'Thank you':
            $betBalance = $betAmount * 10/100;
            $winOrLose = 'win';
            $message = "You won " . number_format($betBalance) . " SpinCoins.";
            break;
        default:
            // No win
            $betBalance = 0;
            $winOrLose = 'lose';
            $message = "Oops! Better luck next time!";
            $soundFile = 'lose.wav';
            break;
    }

    $afterBetBalance = $newBalance + $betBalance;
    
    error_log("User {$userId} bet won amount: $betBalance");
    error_log("User {$userId} new balance after bet: {$afterBetBalance}");
    
    $processBet = processWinnings($userId, $betBalance, $betAmount, $winOrLose);
    error_log($processBet);

    if (json_decode($processBet, true)['success']) {
        
        logOutcomes($selectedOutcome, $betAmount, $betBalance);
        return json_encode([
            'label' => $selectedOutcome,
            'sound' => $soundFile,
            'message' => $message,
            'confetti' => $winOrLose,
            'newBalance' => $newBalance,
            'newWinningBalance' => $newWinningBalance
        ]);
    } else {
        return false;
    }
}

function rewardReferrer($userId, $spinCoin) {
    // spincoin cannot be negative
    if ($spinCoin < 0) {
        error_log("RRRRRRRRR Invalid amount. Amount cannot be negative.");
        return json_encode(['success' => false, 'message' => 'Invalid amount. Amount cannot be negative.']);
    }

    $referralData = getReferrer($userId);

    if (!$referralData) {
        error_log("RRRRRRRRR No referrer found for user {$userId}.");
        return json_encode(['success' => false, 'message' => 'No referrer found.']);
    }

    $referrerId = $referralData['referrer_user_id'];
    $referrerBonus = $referralData['referral_bonus'];

    $rewardAmount = $spinCoin * $referrerBonus / 100;

    error_log("RRRRRRRRR Referrer {$referrerId} will be rewarded with {$rewardAmount} SpinCoins.");
    $conn = getDbConnection();

    // begin a transaction
    $conn->begin_transaction();

    try {
        // Generate a unique transaction number
        $trxNumber = generateUuid();
        $trxType = 'referral';

        // Record the transaction
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, coin_amount, trx_number, trx_type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iiss', $referrerId, $rewardAmount, $trxNumber, $trxType);
        $stmt->execute();
        $stmt->close();

        // check if the user has a winnings wallet
        $stmt = $conn->prepare("SELECT id FROM winnings WHERE user_id = ?");
        $stmt->bind_param('i', $referrerId);
        $stmt->execute();
        $stmt->bind_result($walletId);
        $stmt->fetch();
        $stmt->close();

        if (!$walletId) {
            // Create a new winnings wallet for the user
            $stmt = $conn->prepare("INSERT INTO winnings (user_id, coin_balance) VALUES (?, ?)");
            $stmt->bind_param('ii', $referrerId, $rewardAmount);
            $stmt->execute();
            $stmt->close();
        } else {
            // Update the user's winnings balance
            $stmt = $conn->prepare("UPDATE winnings SET coin_balance = coin_balance + ? WHERE user_id = ?");
            $stmt->bind_param('ii', $rewardAmount, $referrerId);
            $stmt->execute();
            $stmt->close();
        }

        // Commit the transaction
        $conn->commit();
        $conn->close();

        error_log("RRRRRRRRR Referrer {$referrerId} rewarded with {$rewardAmount} SpinCoins.");

        return json_encode(['success' => true, 'message' => 'Referrer rewarded successfully.']);
    } catch (Exception $e) {
        // Rollback the transaction if something failed
        $conn->rollback();
        error_log($e->getMessage());
        $conn->close();

        error_log("RRRRRRRRR An error occurred while rewarding the referrer {$referrerId} with {$rewardAmount} SpinCoins.");

        return json_encode(['success' => false, 'message' => 'An error occurred while rewarding the referrer.']);
    }
}

function getReferrer($userId) {
    // Retrieve the referrer from the database
    $conn = getDbConnection();

    $stmt = $conn->prepare("SELECT * FROM referrals WHERE referred_user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $referralData = $result->fetch_assoc();
    $stmt->close();

    if (!$referralData) {
        return null;
    }

    // return all referral data
    return $referralData;
}

function processWinnings($userId, $amount, $betAmount, $winOrLose) {
    $amount = intval($amount);

    // check if the user has a balance
    $userBalance = checkBalance($userId); // Replace with actual retrieval logic
    $winningBalance = checkWinning($userId);

    $newBalance = $userBalance - $betAmount;

    $trxAmount = $amount;

    // amount cannot be negative unless it's a loss
    if ($amount < 0 && $winOrLose !== 'lose') {
        return json_encode(['success' => false, 'message' => 'Invalid amount. Amount cannot be negative.']);
    }

    if ($winOrLose == 'lose') {
        // turn the amount into negative
        $amount = -$amount;
        $trxAmount = $betAmount;
    }

    try {
        $telegramUserId = $userId;
        
        $conn = getDbConnection();

        // Retrieve the internal id using the Telegram user ID
        $stmt = $conn->prepare("SELECT id FROM users WHERE telegram_user_id = ?");
        $stmt->bind_param('s', $telegramUserId);
        $stmt->execute();
        $stmt->bind_result($id);
        $stmt->fetch();
        $stmt->close();

        if (!$id) {
            $conn->close();
            $response = ['success' => false, 'message' => 'User does not exist.', 'balance' => 0];
            return json_encode($response);
        }

        // Check if the user has a winnings wallet
        $stmt = $conn->prepare("SELECT id FROM winnings WHERE user_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($walletId);
        $stmt->fetch();
        $stmt->close();

        // Start a transaction
        $conn->begin_transaction();

        try {
            // Generate a unique transaction number
            $trxNumber = generateUuid();

            // Record the transaction
            $stmt = $conn->prepare("INSERT INTO transactions (user_id, coin_amount, trx_number, trx_type) VALUES (?, ?, ?, ?)");
            $trxType = $winOrLose == 'lose' ? 'loss' : 'win';
            $stmt->bind_param('iiss', $id, $trxAmount, $trxNumber, $trxType);
            $stmt->execute();
            $stmt->close();

            if (!$walletId) {
                // Create a new winnings wallet for the user
                $stmt = $conn->prepare("INSERT INTO winnings (user_id, coin_balance) VALUES (?, ?)");
                $stmt->bind_param('ii', $id, $amount);
                $stmt->execute();
                $stmt->close();
            } else {
                // Update the user's winnings balance
                $stmt = $conn->prepare("UPDATE winnings SET coin_balance = coin_balance + ? WHERE user_id = ?");
                $stmt->bind_param('ii', $amount, $id);
                $stmt->execute();
                $stmt->close();
            }

            if ($newBalance < 0) {
                $allBalance = ($userBalance + $winningBalance) - $betAmount;
                error_log("User balance: {$userBalance}");
                error_log("Winning balance: {$winningBalance}");
                error_log("Bet amount: {$betAmount}");
                error_log("All balance: {$allBalance}");
                if ($allBalance < 0) {
                    error_log("Completely less than zero");
                    return json_encode(['success' => false, 'message' => 'Completely less than zero.']);
                } else {
                    $decuctible = $betAmount - $userBalance;
                    error_log("Deductible: {$decuctible}");
                    error_log("Convert the remaining amount from winning to user balance {$decuctible}");
                    // return json_encode(['success' => false, 'message' => "Convert the remaining amount from winning to user balance {$decuctible}"]);

                    $stmt = $conn->prepare("UPDATE winnings SET coin_balance = coin_balance - ? WHERE user_id = ?");
                    $stmt->bind_param('ii', $decuctible, $id);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $conn->prepare("UPDATE user_wallets SET coin_balance = coin_balance + ? WHERE user_id = ?");
                    $stmt->bind_param('ii', $decuctible, $id);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $stmt = $conn->prepare("UPDATE user_wallets SET coin_balance = coin_balance - ? WHERE user_id = ?");
            $stmt->bind_param('ii', $betAmount, $id);
            $stmt->execute();
            $stmt->close();

            // Commit the transaction
            $conn->commit();
            $conn->close();

            $response = [
                'success' => true,
                'message' => 'Winnings updated successfully.',
                'amount' => $amount,
                'trxNumber' => $trxNumber,
                'type' => $trxType,
            ];
        } catch (Exception $e) {
            // Rollback the transaction if something failed
            $conn->rollback();
            error_log($e->getMessage());
            $conn->close();

            $response = ['success' => false, 'message' => 'An error occurred while processing winnings.'];
        }

        return json_encode($response);
    } catch (Exception $e) {
        error_log($e->getMessage());
        return json_encode(['success' => false, 'message' => 'An error occurred.']);
    }
}

function logOutcomes($label, $bet, $won) {
    $conn = getDbConnection();
    
    // Start a transaction
    $conn->begin_transaction();

    try {
        // Generate a unique transaction number
        $trxNumber = generateUuid();

        // Record the transaction
        $stmt = $conn->prepare("INSERT INTO test (label, bet, won) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $label, $bet, $won);
        $stmt->execute();
        $stmt->close();

        // Commit the transaction
        $conn->commit();
        $conn->close();        
    } catch (Exception $e) {
        // Rollback the transaction if something failed
        $conn->rollback();
        error_log($e->getMessage());
        $conn->close();
    }
}

function dbOrderVerifyAll($host, $apiKey) {
    $conn = getDbConnection();

    $stmt = $conn->prepare("SELECT * FROM orders WHERE status = 0 ORDER BY id ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$orders) {
        return json_encode(['success' => false, 'message' => 'No pending orders found.']);
    }

    foreach ($orders as $order) {
        $userId = $order['user_id'];
        $fromWallet = $order['from_wallet'];
        $toWallet = $order['to_wallet'];
        $amount = $order['amount'];
        $uniqueIdentifier = $order['unique_identifier'];

        // turn the amount to nanoTON
        $amount = bcmul($amount, '1000000000');

        echo "555555555555555555555555Verifying order: {$order['id']}\n";

        $verify = verifyTransaction2($toWallet, $amount, $uniqueIdentifier, $apiKey);

        // if true, set the order status to 1
        if ($verify) {
            $stmt = $conn->prepare("UPDATE orders SET status = 1, verified_at = CURRENT_TIMESTAMP, attempt = attempt + 1 WHERE id = ?");
            $stmt->bind_param('i', $order['id']);
            $stmt->execute();
            $stmt->close();
        } else {
            // increment the attempt
            $stmt = $conn->prepare("UPDATE orders SET attempt = attempt + 1 WHERE id = ?");
            $stmt->bind_param('i', $order['id']);
            $stmt->execute();
            $stmt->close();
        }
    }

    $conn->close();

    return json_encode(['success' => true, 'message' => 'All orders verified.']);
}

function verifyOrder($host, $apiKey) {
    $conn = getDbConnection();

    $stmt = $conn->prepare("SELECT * FROM orders WHERE status = 0 ORDER BY id ASC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();

    if (!$order) {
        return json_encode(['success' => false, 'message' => 'No pending orders found.']);
    }

    $userId = $order['user_id'];
    $fromWallet = $order['from_wallet'];
    $toWallet = $order['to_wallet'];
    $amount = $order['amount'];
    $uniqueIdentifier = $order['unique_identifier'];

    $verify = verifyTransaction2($toWallet, $amount, $uniqueIdentifier, $apiKey);

    // if true, set the order status to 1
    if ($verify) {
        $stmt = $conn->prepare("UPDATE orders SET status = 1 WHERE id = ?");
        $stmt->bind_param('i', $order['id']);
        $stmt->execute();
        $stmt->close();

        $response = ['success' => true, 'message' => 'Order verified.'];
    } else {
        $response = ['success' => false, 'message' => 'Order not verified.'];
    }
}

function verifyTransaction2($toWallet, $expectedAmount, $expectedPayload, $apiKey, $attempts = 3) {
    GLOBAL $tonCenterHost;
    $apiUrl = $tonCenterHost;
    // $apiUrl = "https://testnet.toncenter.com/api/v2/getTransactions";
    
    // Construct the URL with the recipient wallet and API key
    $url = "$apiUrl?address=$toWallet&limit=100&api_key=$apiKey";
    // error_log($url);
    // echo "\n";

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        echo "Error: Failed to fetch transactions.\n";
        return false;
    }

    // $expectedAmountNanoTon = bcmul($expectedAmount, '1000000000');
    $expectedAmountNanoTon = $expectedAmount;

    // Decode the JSON response
    $data = json_decode($response, true);
    // print_r($data['result']);

    if (isset($data['result']) && count($data['result']) > 0) {
        $transactions = $data['result'];

        foreach ($transactions as $tx) {
            $actualAmount = $tx['in_msg']['value'] ?? null; // Amount in nanoTONs
            $actualPayloadBase64 = $tx['in_msg']['message'] ?? null; // Payload in Base64
            error_log("Actual Payload: $actualPayloadBase64");
            error_log("Expected Payload: $expectedPayload");
            error_log("Actual Amount: $actualAmount");
            error_log("Expected Amount: $expectedAmountNanoTon");

            if ($actualAmount && $actualPayloadBase64 && $actualAmount === $expectedAmountNanoTon) {
                // Compare the payload
                if ($actualPayloadBase64 === $expectedPayload) {
                    error_log("Verified transaction:");
                    // print_r($tx); // Optional: Log the transaction details
                    return true; // Transaction verified
                } else {
                    error_log("Passed");
                }
            } else {
                error_log("Something");
            }
        }
    } else {
        error_log("Nothing to see here");
    }

    if ($attempts > 1) {
        sleep(3);
        error_log("0000000000 Retrying... Attempts left: " . ($attempts - 1));
        return verifyTransaction2($toWallet, $expectedAmount, $expectedPayload, $apiKey, $attempts - 1);
    }

    error_log("No matching transaction found for {$expectedPayload}");
    return false; // No matching transaction found
}

function verifyTransactionLotto($userId, $walletAddress, $toWallet, $expectedAmount, $tonAmount, $uniqueIdentifier, $apiKey, $lottoId) {
    $processOrder = processLottoOrder($userId, $walletAddress, $toWallet, $tonAmount, $uniqueIdentifier, 'TON', 1, $lottoId);
    
    if ($processOrder) {
        return ['success' => true, 'message' => "Transaction verified! Your balance has been updated."];
    } else {
        return ['success' => true, 'message' => 'Received payment, but your order is not processed. Please give us a moment.'];
    }
    
    // $verify = verifyTransaction2($toWallet, $expectedAmount, $uniqueIdentifier, $apiKey);

    // if ($verify) {
    //     $processOrder = processOrder($userId, $walletAddress, $toWallet, $tonAmount, $uniqueIdentifier, 'TON', 1);
    //     if ($processOrder) {
    //         return ['success' => true, 'message' => "Transaction verified! Your balance has been updated."];
    //     } else {
    //         return ['success' => true, 'message' => 'Received payment, but your order is not processed. Please give us a moment.'];
    //     }
    // } else {
    //     $processOrder = processOrder($userId, $walletAddress, $toWallet, $tonAmount, $uniqueIdentifier, 'TON', 0);
    //     if ($processOrder) {
    //         return ['success' => true, 'message' => "Your transaction is being verified in the background! Your balance has been updated."];
    //     } else {
    //         return ['success' => true, 'message' => 'Received payment, but your order is not processed. Please give us a moment.'];
    //     }
    // }
}


function verifyTransaction($userId, $walletAddress, $toWallet, $expectedAmount, $tonAmount, $uniqueIdentifier, $apiKey) {
    $processOrder = processOrder($userId, $walletAddress, $toWallet, $tonAmount, $uniqueIdentifier, 'TON', 1);
    
    if ($processOrder) {
        return ['success' => true, 'message' => "Transaction verified! Your balance has been updated."];
    } else {
        return ['success' => true, 'message' => 'Received payment, but your order is not processed. Please give us a moment.'];
    }
    
    // $verify = verifyTransaction2($toWallet, $expectedAmount, $uniqueIdentifier, $apiKey);

    // if ($verify) {
    //     $processOrder = processOrder($userId, $walletAddress, $toWallet, $tonAmount, $uniqueIdentifier, 'TON', 1);
    //     if ($processOrder) {
    //         return ['success' => true, 'message' => "Transaction verified! Your balance has been updated."];
    //     } else {
    //         return ['success' => true, 'message' => 'Received payment, but your order is not processed. Please give us a moment.'];
    //     }
    // } else {
    //     $processOrder = processOrder($userId, $walletAddress, $toWallet, $tonAmount, $uniqueIdentifier, 'TON', 0);
    //     if ($processOrder) {
    //         return ['success' => true, 'message' => "Your transaction is being verified in the background! Your balance has been updated."];
    //     } else {
    //         return ['success' => true, 'message' => 'Received payment, but your order is not processed. Please give us a moment.'];
    //     }
    // }
}

function verifyTransactionOld($userId, $walletAddress, $toWallet, $expectedAmount, $uniqueIdentifier, $apiKey) {
    GLOBAL $tonCenterHost;
    $apiUrl = $tonCenterHost;
    
    // $processOrder = processOrder($userId, $toWallet, $expectedAmount, $uniqueIdentifier, 'TON');
    // if ($processOrder) {
    //     return ['success' => true, 'transaction' => 'Something goes here'];
    // } else {
    //     return ['success' => false, 'message' => 'Received payment, but your order is not processed. Please give us a moment.'];
    // }

    $ch = curl_init();

    $queryParams = [
        'address' => $toWallet,
        'api_key' => $apiKey
    ];
    
    $uri = $apiUrl . http_build_query($queryParams, '', '&');
    
    error_log("URI: $uri");

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $uri);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute cURL request
    $response = curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'message' => 'cURL error: ' . $error_msg];
    }

    error_log("Executed: {$response}");

    // Close cURL session
    curl_close($ch);

    // Decode the response
    $data = json_decode($response, true);

    if ($data['ok']) {
        foreach ($data['result'] as $transaction) {
            $amount = $transaction['in_msg']['value'];
            $payload = $transaction['in_msg']['message'] ?? ''; // Retrieve transaction payload

            error_log("Amount: $amount");
            error_log("Payload: $payload");
            // Check for matching amount and unique identifier
            if ($amount == $expectedAmount * 1e9 && $payload === $payload) {
                $processOrder = processOrder($userId, $walletAddress, $toWallet, $expectedAmount, $uniqueIdentifier, 'TON', 1);
                if ($processOrder) {
                    return ['success' => true, 'transaction' => $transaction];
                } else {
                    return ['success' => false, 'message' => 'Received payment, but your order is not processed. Please give us a moment.'];
                }
            }
        }
    }

    return ['success' => false, 'message' => 'Transaction not found or does not match.'];
}

function generateSecureLotteryNumber($length = 9) {
    // Ensure the length is valid
    if ($length <= 0) {
        throw new InvalidArgumentException("Length must be a positive integer.");
    }

    $lotteryNumber = '';
    $allowedCharacters = '0123456789'; // Allowed characters (digits only)
    $maxIndex = strlen($allowedCharacters) - 1;

    // Use a cryptographically secure random function to generate each character
    for ($i = 0; $i < $length; $i++) {
        $randomIndex = random_int(0, $maxIndex); // Secure random integer
        $lotteryNumber .= $allowedCharacters[$randomIndex];
    }

    return $lotteryNumber;
}

function processLottoOrder($userId, $fromWallet, $toWallet, $tonAmount, $uniqueIdentifier, $currency, $status, $lottoId) {
    $lottoData = getLotteryData($lottoId);
    $draw_date = $lottoData['draw_date'];
    $price = $lottoData['price'];
    $lottoNumber = generateSecureLotteryNumber(9);
    
    error_log("TON Amount: $tonAmount");
    error_log("Lotto number: $lottoNumber");

    if ($price <= $tonAmount) {
        $conn = getDbConnection();
        // Generate a unique transaction number
        $trxNumber = generateUuid();

        // Record the transaction
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, coin_amount, currency_amount, currency, trx_number, trx_type) VALUES (?, ?, ?, ?, ?, ?)");
        $trxType = 'order';
        $stmt->bind_param('iddsss', $userId, 0, $tonAmount, $currency, $trxNumber, $trxType);
        $stmt->execute();
        $transactionId = $stmt->insert_id; // Get the new transaction ID
        $stmt->close();

        $orderNumber = generateUuid();

        // Add to orders table
        $stmt = $conn->prepare("INSERT INTO orders (user_id, currency, amount, coin_amount, from_wallet, to_wallet, unique_identifier, trx_id, order_number, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isddsssisi', $userId, $currency, $tonAmount, 0, $fromWallet, $toWallet, $uniqueIdentifier, $transactionId, $orderNumber, $status);
        $stmt->execute();
        $stmt->close();

        // insert to tickets
        $stmt = $conn->prepare("INSERT INTO user_tickets (user_id, ticket_number, purchased_at) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $userId, $lottoNumber, date('Y-m-d H:i:s'));
        $stmt->execute();
        $ticket_id = $stmt->insert_id;
        $stmt->close();

        return $ticket_id;
    } else {
        error_log("Your payment of {$tonAmount} is less than a price {$price}");
        return false;
    }
}

function processOrder($userId, $fromWallet, $toWallet, $tonAmount, $uniqueIdentifier, $currency, $status) {
    
    $special = checkCoinPrice($tonAmount, $currency);
        
    if ($special && $special['percentage'] != null) {
        $spinCoin = $special['coin'];
    } else {
        $spinCoin = currencyConverter($tonAmount, $currency, 'SPIN');
    }

    error_log("TON Amount: $tonAmount");
    error_log("SpinCoin Amount: $spinCoin");

    try {
        $conn = getDbConnection();

        // Start a transaction
        $conn->begin_transaction();

        // Retrieve the internal id using the Telegram user ID
        $stmt = $conn->prepare("SELECT id FROM users WHERE telegram_user_id = ?");
        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $stmt->bind_result($id);
        $stmt->fetch();
        $stmt->close();

        if (!$id) {
            $conn->rollback();
            $conn->close();
            return json_encode(['success' => false, 'message' => 'User does not exist.']);
        }

        // Check if the user has a wallet
        $stmt = $conn->prepare("SELECT id FROM user_wallets WHERE user_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($walletId);
        $stmt->fetch();
        $stmt->close();

        // Generate a unique transaction number
        $trxNumber = generateUuid();

        // Record the transaction
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, coin_amount, currency_amount, currency, trx_number, trx_type) VALUES (?, ?, ?, ?, ?, ?)");
        $trxType = 'order';
        $stmt->bind_param('iddsss', $id, $spinCoin, $tonAmount, $currency, $trxNumber, $trxType);
        $stmt->execute();
        $transactionId = $stmt->insert_id; // Get the new transaction ID
        $stmt->close();

        $orderNumber = generateUuid();

        // Add to orders table
        $stmt = $conn->prepare("INSERT INTO orders (user_id, currency, amount, coin_amount, from_wallet, to_wallet, unique_identifier, trx_id, order_number, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isddsssisi', $id, $currency, $tonAmount, $spinCoin, $fromWallet, $toWallet, $uniqueIdentifier, $transactionId, $orderNumber, $status);
        $stmt->execute();
        $stmt->close();

        if (!$walletId) {
            // Create a new wallet for the user
            $stmt = $conn->prepare("INSERT INTO user_wallets (user_id, coin_balance) VALUES (?, ?)");
            $stmt->bind_param('ii', $id, $spinCoin);
            $stmt->execute();
            $stmt->close();
        } else {
            // Update the user's balance
            $stmt = $conn->prepare("UPDATE user_wallets SET coin_balance = coin_balance + ? WHERE user_id = ?");
            $stmt->bind_param('ii', $spinCoin, $id);
            $stmt->execute();
            $stmt->close();
        }

        // Commit the transaction
        $conn->commit();
        $conn->close();

        rewardReferrer($id, $spinCoin);

        // return json_encode(['success' => true, 'message' => 'Order processed successfully.', 'spinCoin' => $spinCoin]);
        return true;
    } catch (Exception $e) {
        // Rollback the transaction if something failed
        $conn->rollback();
        error_log($e->getMessage());
        $conn->close();

        // return json_encode(['success' => false, 'message' => 'An error occurred while processing the order.']);
        return false;
    }
}

function decodeBase64Payload($payloadBase64) {
    return base64_decode($payloadBase64);
}

// get user transactions with pagination and search and limit
function getTransactions($userId, $limit = 10, $search = null, $page = 1) {
    $conn = getDbConnection();

    $offset = ($page - 1) * $limit;

    if ($search) {
        $stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? AND trx_number LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $search = "%$search%";
        $stmt->bind_param('issi', $userId, $search, $limit, $offset);
    } else {
        $stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->bind_param('iii', $userId, $limit, $offset);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $transactions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $conn->close();

    return $transactions;
}

function getWithdrawals($userId, $limit = 10, $search = null, $page = 1) {
    $conn = getDbConnection();

    $offset = ($page - 1) * $limit;

    if ($search) {
        $stmt = $conn->prepare("SELECT * FROM withdrawals WHERE user_id = ? AND wallet LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $search = "%$search%";
        $stmt->bind_param('issi', $userId, $search, $limit, $offset);
    } else {
        $stmt = $conn->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->bind_param('iii', $userId, $limit, $offset);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $withdrawals = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $conn->close();

    return $withdrawals;
}

function getOrders($userId, $limit = 10, $search = null, $page = 1) {
    $conn = getDbConnection();

    $offset = ($page - 1) * $limit;

    if ($search) {
        $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? AND unique_identifier LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $search = "%$search%";
        $stmt->bind_param('issi', $userId, $search, $limit, $offset);
    } else {
        $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->bind_param('iii', $userId, $limit, $offset);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $conn->close();

    return $orders;
}

function getLotteries($limit = 10, $page = 1) {
    $conn = getDbConnection();

    $offset = ($page - 1) * $limit;

    $stmt = $conn->prepare("SELECT * FROM lotteries ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('ii', $limit, $offset);

    $stmt->execute();
    $result = $stmt->get_result();
    $lotteries = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $conn->close();

    return $lotteries;
}

function getLotteryLevelData($id, $level) {
    $conn = getDbConnection();

    $stmt = $conn->prepare("SELECT * FROM lotto_levels WHERE lotto_id = ? AND lotto_level = ?");
    $stmt->bind_param('ii', $id, $level);
    $stmt->execute();
    $result = $stmt->get_result();
    $lotteryLevelData = $result->fetch_assoc();
    $stmt->close();

    $conn->close();

    return $lotteryLevelData;
}

function getCoinPrices($id, $limit = 10, $page = 1) {
    GLOBAL $adminWallet;
    // TODO: User based pricing

    $conn = getDbConnection();

    $offset = ($page - 1) * $limit;

    $stmt = $conn->prepare("SELECT * FROM coin_prices ORDER BY coin ASC LIMIT ? OFFSET ?");
    $stmt->bind_param('ii', $limit, $offset);

    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $conn->close();

    foreach ($orders as &$order) {
        $order['wallet'] = $adminWallet;
    }

    return $orders;
}

function getReferrals($userId, $limit = 10, $search = null, $page = 1) {
    $conn = getDbConnection();

    $offset = ($page - 1) * $limit;

    if ($search) {
        $stmt = $conn->prepare("SELECT r.*, u.unique_name FROM referrals r JOIN users u ON r.referred_user_id = u.id WHERE r.referrer_user_id = ? AND u.unique_name LIKE ? ORDER BY r.created_at DESC LIMIT ? OFFSET ?");
        $search = "%$search%";
        $stmt->bind_param('issi', $userId, $search, $limit, $offset);
    } else {
        $stmt = $conn->prepare("SELECT r.*, u.unique_name FROM referrals r JOIN users u ON r.referred_user_id = u.id WHERE r.referrer_user_id = ? ORDER BY r.created_at DESC LIMIT ? OFFSET ?");
        $stmt->bind_param('iii', $userId, $limit, $offset);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $referrals = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $conn->close();

    // Hijack: Replace created_at with relative time
    foreach ($referrals as &$referral) {
        if (isset($referral['created_at'])) {
            $referral['created_at_relative'] = relativeTime($referral['created_at']);
        }
    }

    return $referrals;
}

function getWinningData($id) {
    $conn = getDbConnection();

    $stmt = $conn->prepare("SELECT * FROM draws WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $winnerData = $result->fetch_assoc();
    $stmt->close();

    $conn->close();

    $winnerData['lotto_name'] = getLotteryData($winnerData['lotto_id'])['name'];
    $winnerData['user'] = getUserDataFromId($winnerData['user_id'])['data']['unique_name'];
    $winnerData['date'] = relativeTime($winnerData['draw_date']);
    
    return $winnerData;
}

function getWinners($limit = 10, $page = 1) {
    $conn = getDbConnection();

    $offset = ($page - 1) * $limit;

    $stmt = $conn->prepare("SELECT * FROM draws ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('ii', $limit, $offset);

    $stmt->execute();
    $result = $stmt->get_result();
    $draws = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // attach username to user_id
    foreach ($draws as &$draw) {
        $userData = getUserDataFromId($draw['user_id']);
        $draw['user'] = $userData['data']['unique_name'];
        $draw['date'] = relativeTime($draw['draw_date']);
    }

    $conn->close();

    return $draws;
}

function getTransfers($userId, $limit = 10, $search = null, $page = 1) {
    $conn = getDbConnection();

    $offset = ($page - 1) * $limit;

    // base query
    if ($search) {
        $stmt = $conn->prepare("SELECT * FROM transfers WHERE user_id = ? AND reference_number LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $search = "%$search%";
        $stmt->bind_param('issi', $userId, $search, $limit, $offset);
    } else {
        $stmt = $conn->prepare("SELECT * FROM transfers WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->bind_param('iii', $userId, $limit, $offset);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $transfers = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();    
    $conn->close();

    foreach ($transfers as &$transfer) {
        $userData = getUserDataFromId($transfer['assoc_user_id']);
        $transfer['user'] = $userData['data']['unique_name'];
    }

    return $transfers;
}

function relativeTime($dateTime) {
    $timeNow = new DateTime(); // Current time
    $timeGiven = new DateTime($dateTime); // Given date/time
    $interval = $timeNow->diff($timeGiven); // Calculate the difference

    // Check whether the given time is in the past or future
    $isFuture = $timeGiven > $timeNow;

    // Human-readable output
    $output = '';

    if ($interval->y > 0) {
        $output = $interval->y . ' year' . ($interval->y > 1 ? 's' : '');
    } elseif ($interval->m > 0) {
        $output = $interval->m . ' month' . ($interval->m > 1 ? 's' : '');
    } elseif ($interval->d > 0) {
        $output = $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
    } elseif ($interval->h > 0) {
        $output = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '');
    } elseif ($interval->i > 0) {
        $output = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
    } elseif ($interval->s > 0) {
        $output = $interval->s . ' second' . ($interval->s > 1 ? 's' : '');
    } else {
        $output = 'just now';
    }

    // Add "ago" or "from now" based on whether it's past or future
    if ($output !== 'just now') {
        $output .= $isFuture ? ' from now' : ' ago';
    }

    return $output;
}

function getUserReferralLink($userId) {
    GLOBAL $botUrl;
    GLOBAL $botName;
    $uniqueName = getUserData($userId)['data']['unique_name'];
    $link = $botUrl . "/{$botName}?startapp={$uniqueName}";

    return $link;
}

function getLotteryData($id) {
    $conn = getDbConnection();

    $stmt = $conn->prepare("SELECT * FROM lotteries WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lotteryData = $result->fetch_assoc();
    $stmt->close();

    $conn->close();

    $lotteryData['first_place_reward'] = number_format(getLotteryLevelData($id, 1)['reward_amount'], 2) . ' ' . $lotteryData['currency'];
    $lotteryData['second_place_reward'] = number_format(getLotteryLevelData($id, 2)['reward_amount'], 2) . ' ' . $lotteryData['currency'];
    $lotteryData['third_place_reward'] = number_format(getLotteryLevelData($id, 3)['reward_amount'], 2) . ' ' . $lotteryData['currency'];

    return $lotteryData;
}

function checkCoinPrice($price, $currency) {
    $conn = getDbConnection();

    $stmt = $conn->prepare("SELECT * FROM coin_prices WHERE currency = ? AND price = ? LIMIT 1");
    $stmt->bind_param('sd', $currency, $price);
    $stmt->execute();
    $result = $stmt->get_result();
    $priceData = $result->fetch_assoc();
    $stmt->close();

    $conn->close();

    if (!$priceData) {
        return false;
    }

    return $priceData;
}