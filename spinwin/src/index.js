// Initialize TON Connect UI SDK
const tonConnectUI = new TON_CONNECT_UI.TonConnectUI({
      manifestUrl: 'https://spin.lomiads.online/tonconnect-manifest.json',
      buttonRootId: 'ton-connect'
});

const TonWeb = window.TonWeb;
const { Cell } = TonWeb.boc;

let winningMessage = 'Welcome!';
let walletAddress = null;
let walletConnected = false;
let userBalance = 0;
let userWinning = 0;
let triggerConfetti = false;

tonConnectUI.onStatusChange((walletInfo) => {
  if (walletInfo) {
    // get all wallet info
    // console.log(walletInfo);

    walletAddress = walletInfo.account.address;
    publicKey = walletInfo.account.publicKey;
    walletConnected = true;
    // fetchUserBalance(); // Fetch the balance once connected
  } else {
    walletAddress = null;
    walletConnected = false;
    // updateBalance(0);
  }
});


document.addEventListener('DOMContentLoaded', () => {
    /**
     * Function to determine if the web app is accessed via Telegram.
     * @returns {boolean} True if accessed via Telegram, false otherwise.
     */
    function isAccessedViaTelegram() {
      const telegramWebApp = window.Telegram && window.Telegram.WebApp;

      if (telegramWebApp && telegramWebApp.initData) {
          try {
              // Decode the initData to verify its integrity
              const decodedInitData = decodeURIComponent(telegramWebApp.initData);

              // Check if decodedInitData has expected structure or length
              // Here, we assume that valid initData has a minimum length
              // console.log("Decoded initData:", decodedInitData);

              return decodedInitData.length > 0;
          } catch (error) {
              console.error("Error decoding initData:", error);
              return false;
          }
      }

      return false;
  }

  if (isAccessedViaTelegram()) {
      // Accessed via Telegram
      // console.log("Accessed via Telegram");

      // Initialize the Telegram Web App
      const telegramWebApp = window.Telegram.WebApp;
      telegramWebApp.ready(); // Notify Telegram that your app is ready

      // Example: Access user data
      const user = telegramWebApp.initDataUnsafe.user;
      // console.log("User Data:", user);

      // Customize UI based on Telegram settings
      if (telegramWebApp.colorScheme === "dark") {
          document.body.style.backgroundColor = "#1e1e1e";
          document.body.style.color = "#ffffff";
      } else {
          document.body.style.backgroundColor = "#ffffff";
          document.body.style.color = "#000000";
      }

      // Additional Telegram-specific functionalities can be initialized here
  } else {
      // Accessed outside Telegram
      // console.log("Accessed outside Telegram");

      // Optionally, notify the user or adjust the UI accordingly
      // alert("This web app is best accessed within Telegram.");

      // Example: Redirect user to Telegram or display alternative content
      // window.location.href = "https://t.me/wheelyspinwinbot";
  }

  // check if the token is valid and reload the page if not
  const token = getJWT();
  // console.log(`Token: ${token}`);

  const response = fetch('ajax.php', {
          method: 'POST',
          headers: {
              'Authorization': `Bearer ${token}`,
              'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: 'check-token',
      })
      .then(async (res) => {
          // Check if response is not OK (status code outside the 2xx range)
          if (!res.ok) {
            // console.error(`HTTP Error: ${res.status}`);
            // const errorText = await res.json();
            // console.error(`Error details: ${errorText.command}`);
            if (res.status == 401) {
              // console.log('Token expired. Reloading...');
              await initTelegram();
              // console.log(`New token: ${getJWT()}`);
              // alert("Reload");
              location.reload();
            }
          } else {
              // console.log(`Fetched ${token}`);
              await init();
              // const data = await res.json();
              // console.log('Success:', data);
          }
      })
      .catch((err) => {
          alert("Unexpected error. Please reload the page!");
          // Handle network errors or other unexpected issues
          console.error('Fetch failed:', err.message);
          console.error('Error stack:', err.stack);
      });
});

document.getElementById('uniqueName').addEventListener('keyup', async function() {
  var uniqueName = this.value;
  const token = getJWT();
  
  const response = await fetch('ajax.php', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `check-unque-name&value=${uniqueName}`,
  });

  const data = await response.json();
  // if not successful disable the transferBtn button
  if (!data.success) {
    transferBtn.disabled = true;
  } else {
    transferBtn.disabled = false;
  }

  document.getElementById('account_name').innerText = data.message;
});

document.getElementById('buyAmount').addEventListener('keyup', async function() {
  var buyAmount = this.value;
  const token = getJWT();
  if (buyAmount > 0) {
    const response = await fetch('ajax.php', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `buy&amount=${buyAmount}&currency=TON`,
    });
    const data = await response.json();
    let bonus = '';
    if (data.percentage == null) {
      bonus = '';
    } else {
      bonus = data.percentage;
      bonus = `<span class="badge badge-success badge-pill">+${bonus}%</span>`;
    }

    document.getElementById('custom_coin').innerHTML = data.amount + ' ' + bonus;
  } else {
    document.getElementById('custom_coin').innerHTML = 'Withdraw your SpinCoins to your TON wallet.';
  }
});


document.getElementById('withdrawAmount').addEventListener('keyup', async function() {
  var withdrawAmount = this.value;
  const token = getJWT();
  if (withdrawAmount > 0) {
    const response = await fetch('ajax.php', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `convert&amount=${withdrawAmount}`,
    });
    const data = await response.json();
    document.getElementById('spin_to_ton').innerText = data.amount;
  } else {
    document.getElementById('spin_to_ton').innerText = 'Withdraw your SpinCoins to your TON wallet.';
  }
});

function getAdminWallet() {
  const token = getJWT();
  if (!token) {
    showAlert("You need to login first! Refresh the page to login again!", 'danger');
    return;
  } else {
    const headers = {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/x-www-form-urlencoded',
    };

    return fetch('ajax.php', {
      method: 'POST',
      headers: headers,
      body: `getAdminWallet`,
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.json();
      })
      .then((data) => {
        if (data.success) {
          // console.log(data.wallet);
          return data.wallet;
        } else {
          console.log('Failed to fetch wallet');
          showAlert('Unable to fetch admin wallet. Please try again later.', 'danger');
          return false;
        }
      })
      .catch((error) => {
        if (error.name === 'AbortError') {
          console.error('Fetch request timed out.');
        } else {
          console.error('Fetch request failed:', error);
        }
        console.log('Error fetching admin wallet.');
        showAlert('Unable to purchase. Please try again later.', 'danger');
        return false;
      });
  }
}          

function getJWT() {
  const token = localStorage.getItem('jwtToken');
  if (!token) {
    return null;
  } else {
    return token;
  }
}

function setWinningMessage(message) {
  winningMessage = message;
}

async function init() {
  await initTelegram();
  adjustCanvasSize();

  // Prepare live lotto draw
  prepareLiveLotto();

  // Fetch user balance
  displayUserBalance();

  // Fetch user winning
  fetchUserWinning();

  // Fetch coin prices
  fetchCoinPrices();

  // Fetch leaderboard data
  // fetchLeaderboard();

  // Fetch withdrawals data
  fetchWithdrawals();

  // Fetch transactions data
  fetchTransactions();

  // Fetch transfers data
  fetchTransfers();

  // Fetch orders data
  fetchOrders();

  // Fetch referrals data
  fetchReferrals();

  // Fetch lotteries data
  fetchLotteries();

  // Fetch user tickets
  fetchUserTikcets();

  // Fetch lotto winners
  fetchWinners();

  // Fetch plans data
  // fetchPlans();

  // Initialize event listeners
  spinEl.addEventListener('click', spinWheel);
  events.addListener('spinEnd', (sector) => {
    const newBalance = displayUserBalance(); // TODO: maybe fix this
    showAlert(winningMessage);
    // showAlert(`Congratulations! You won ${sector.label}`, 'success', 'modalBody');
    spinEl.disabled = false; // Re-enable the spin button
  });

  withdrawBtn.addEventListener('click', withdrawSpinCoins);
  buyBtn.addEventListener('click', buyCustomSpinCoins);
  transferBtn.addEventListener('click', transferSpinCoins);

  // Fetch sectors and initialize the wheel
  fetchSectors().then(() => {
    assignColorsToSectors();

    // Recalculate constants based on the number of sectors
    tot = sectors.length;
    arc = TAU / tot;

    sectors.forEach(drawSector);
    rotate(); // Initial rotation
    requestAnimationFrame(frame); // Start the animation loop
  });

  // Adjust canvas size on window resize
  window.addEventListener('resize', adjustCanvasSize);
}

// Adjust canvas size based on screen width
function adjustCanvasSize() {
  // Your adjustCanvasSize function is empty as per your code
}

function testPopup(message) {
  window.Telegram.WebApp.showAlert(message);
}

// Initialize Telegram Web Apps and fetch user info
async function initTelegram() {
  const tg = window.Telegram.WebApp;
  tg.expand(); // Expand the web app to full height

  const initData = tg.initData;
  const initDataURLEncoded = encodeURIComponent(initData);
  

  const response = await fetch('auth.php', {
      method: 'POST',
      headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `initData=${initDataURLEncoded}`,
  });

  const result = await response.json();
  // console.log(result);
  if (result.token) {
      localStorage.setItem('jwtToken', result.token);
      document.getElementById('referralLink').value = result.referral_link;
      document.getElementById('userName').textContent = result.first_name + ' ' + result.last_name + ' (' + result.unique_name + ')';
      showAlert(result.message, 'success', 'alertContainer');
  } else {
      // console.log('Login failed: ' + result.message);
      // alert('Login failed: ' + result.message);
      showAlert('Login failed: ' + result.message, 'danger');
  }
}

function removeShowAlert() {
  const alertContainer = document.getElementById('modalBody');
  alertContainer.innerHTML = '';
}

// Show Bootstrap alerts
function showAlert(message, type = 'warning', containerId = 'modalBody') {
  // show a modal with an id 'modal'
  
  // show modal
  if (containerId == 'modalBody') {
    $('#modal').modal('show');
    document.getElementById('modalLabel').innerText = 'Attention!';
  }

  const alertContainer = document.getElementById(containerId);
  alertContainer.innerHTML = `
    <div class="alert alert-${type} alert-dismissible fade show mt-2" role="alert">
      ${message}
      <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
      </button>
    </div>
  `;
}

// Update user balance display
function updateBalance(newBalance) {
  userBalance = newBalance;
  document.getElementById('userBalance').textContent = userBalance.toLocaleString();
}
// Update user balance display
function updateWinning(newWinning) {
  userWinning = newWinning;
  document.getElementById('userWinning').textContent = userWinning.toLocaleString();
}

async function purchaseCoins(tonAmount, toWallet) {
  const token = getJWT();

  const adminWallet = await getAdminWallet();

  if (!walletConnected || !walletAddress) {
    showAlert('Please connect your wallet first.', 'warning');
    return;
  }

  if (tonAmount && !isNaN(tonAmount) && tonAmount > 0) {
    try {
      // generate UUID
      const orderId = crypto.randomUUID();

      // Format the wallet for human readability
      const publicKeyHex = TonWeb.utils.bytesToHex(TonWeb.utils.base64ToBytes(publicKey));

      // console.log('Public Key (Hex):', publicKeyHex);

      const humanReadableWallet = new TonWeb.utils.Address(walletAddress).toString(true, true, true);;
      // console.log('Normal wallet:', walletAddress);
      // console.log('Human-readable wallet:', humanReadableWallet);

      // Generate a unique identifier for this transaction
      const uniqueIdentifier = `order-${walletAddress}-${orderId}-${Date.now()}`; // User's wallet + timestamp

      let a = new TonWeb.boc.Cell();
      a.bits.writeUint(0, 32);
      a.bits.writeString(uniqueIdentifier);
      let payload = TonWeb.utils.bytesToBase64(await a.toBoc());
      
      const tx = {
        validUntil: Date.now() + 5 * 60 * 1000, // Transaction valid for 5 minutes
        messages: [
          {
            // address: '0QDXOIzTLFDSVl2eQJoTPH_jJLuJMGJ94L9DlEaDQ0JpJ8Y0', // Your wallet address
            address: adminWallet,
            amount: (tonAmount * 1e9).toString(), // Convert TON to nanotons
            payload: payload, // Add the unique identifier
          },
        ],
      };

      // console.log("Transaction object:", tx);

      // Send the transaction request to the wallet
      const result = await tonConnectUI.sendTransaction(tx);
      // console.log('Transaction result:', result);
      // console.log('Payload:', uniqueIdentifier);

      showAlert('Purchase transaction sent. Waiting for confirmation...', 'info');
      
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 seconds timeout
            
      try {
        const response = await fetch('ajax.php', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `verify-transaction&tonAmount=${encodeURIComponent(tonAmount)}&walletAddress=${encodeURIComponent(walletAddress)}&purchaseAmount=${encodeURIComponent((tonAmount * 1e9).toString())}&uniqueIdentifier=${encodeURIComponent(uniqueIdentifier)}&toWallet=${encodeURIComponent(adminWallet)}`,
            signal: controller.signal,
        });

        // Clear the timeout if the request succeeds
        clearTimeout(timeoutId);
    
        if (!response.ok) {
            // console.log("Error occured: " + response.status);
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        
        const result = await response.json(); 
        // console.log(result);

        if (result.success) {
          await fetchUserBalance();

          showAlert(result.message, 'success');
        } else {
          showAlert('Transaction verification failed. Please try again later.', 'danger');
        }
      } catch (error) {
        if (error.name === 'AbortError') {
            console.error('Fetch request timed out.');
        } else {
            console.error('Fetch request failed:', error);
        }
        showAlert('Error verifying the trx. Please try again later.', 'danger');
      }
    } catch (error) {
      console.error('Error:', error);
      showAlert('Error processing transaction. Please try again.', 'danger');
    }
  } else {
    showAlert('Invalid amount entered.', 'danger');
  }
}

function displayLottotPrices(tonAmount, lottoId) {
  // create a radio button for 0.5, 0.4, 0.3, 0.2 and 0.1 TON. Up on click, run a buyLotto(tonAMount, lottoId)
  const lottoPrices = `
    <div class="form-check">
      <input class="form-check-input" type="radio" name="lottoPrice" id="lottoPrice1" value="0.5" onclick="buyLotto(0.5, ${lottoId})">
      <label class="form-check-label form-control" for="lottoPrice1">0.5 TON (Full)</label>
      <br>
      <input class="form-check-input" type="radio" name="lottoPrice" id="lottoPrice2" value="0.4" onclick="buyLotto(0.4, ${lottoId})">
      <label class="form-check-label form-control" for="lottoPrice2">0.4 TON</label>
      <br>
      <input class="form-check-input" type="radio" name="lottoPrice" id="lottoPrice3" value="0.3" onclick="buyLotto(0.3, ${lottoId})">
      <label class="form-check-label form-control" for="lottoPrice3">0.3 TON</label>
      <br>
      <input class="form-check-input" type="radio" name="lottoPrice" id="lottoPrice4" value="0.2" onclick="buyLotto(0.2, ${lottoId})">
      <label class="form-check-label form-control" for="lottoPrice4">0.2 TON</label>
      <br>
      <input class="form-check-input" type="radio" name="lottoPrice" id="lottoPrice5" value="0.1" onclick="buyLotto(0.1, ${lottoId})">
      <label class="form-check-label form-control" for="lottoPrice5">0.1 TON</label>
    </div>
    `;

    $('#modal').modal('show');
    const alertContainer = document.getElementById('modalBody');
    alertContainer.innerHTML = lottoPrices;
    document.getElementById('modalLabel').innerText = 'Select Lotto Price';
}

async function buyLotto(tonAmount, lottoId) {
  // console.log(tonAmount, lottoId);
  const token = getJWT();

  const adminWallet = await getAdminWallet();

  if (!walletConnected || !walletAddress) {
    showAlert('Please connect your wallet first.', 'warning');
    return;
  }

  if (tonAmount && !isNaN(tonAmount) && tonAmount > 0) {
    try {
      // generate UUID
      const orderId = crypto.randomUUID();

      // Format the wallet for human readability
      const publicKeyHex = TonWeb.utils.bytesToHex(TonWeb.utils.base64ToBytes(publicKey));

      // console.log('Public Key (Hex):', publicKeyHex);

      const humanReadableWallet = new TonWeb.utils.Address(walletAddress).toString(true, true, true);;
      // console.log('Normal wallet:', walletAddress);
      // console.log('Human-readable wallet:', humanReadableWallet);

      // Generate a unique identifier for this transaction
      const uniqueIdentifier = `lotto-${walletAddress}-${orderId}-${Date.now()}`; // User's wallet + timestamp

      let a = new TonWeb.boc.Cell();
      a.bits.writeUint(0, 32);
      a.bits.writeString(uniqueIdentifier);
      let payload = TonWeb.utils.bytesToBase64(await a.toBoc());
      
      const tx = {
        validUntil: Date.now() + 5 * 60 * 1000, // Transaction valid for 5 minutes
        messages: [
          {
            // address: '0QDXOIzTLFDSVl2eQJoTPH_jJLuJMGJ94L9DlEaDQ0JpJ8Y0', // Your wallet address
            address: adminWallet,
            amount: (tonAmount * 1e9).toString(), // Convert TON to nanotons
            payload: payload, // Add the unique identifier
          },
        ],
      };

      // console.log("Transaction object:", tx);

      // Send the transaction request to the wallet
      const result = await tonConnectUI.sendTransaction(tx);
      // console.log('Transaction result:', result);
      // console.log('Payload:', uniqueIdentifier);

      showAlert('Purchase transaction sent. Waiting for confirmation...', 'info');
      
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 seconds timeout
            
      try {
        const response = await fetch('ajax.php', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `verify-lotto-transaction&lottoId=${lottoId}&tonAmount=${encodeURIComponent(tonAmount)}&walletAddress=${encodeURIComponent(walletAddress)}&purchaseAmount=${encodeURIComponent((tonAmount * 1e9).toString())}&uniqueIdentifier=${encodeURIComponent(uniqueIdentifier)}&toWallet=${encodeURIComponent(adminWallet)}`,
            signal: controller.signal,
        });

        // Clear the timeout if the request succeeds
        clearTimeout(timeoutId);
    
        if (!response.ok) {
            // console.log("Error occured: " + response.status);
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        
        const result = await response.json(); 
        // console.log(result);

        if (result.success) {
          await fetchUserBalance();

          showAlert(result.message, 'success');
        } else {
          showAlert('Transaction verification failed. Please try again later.', 'danger');
        }
      } catch (error) {
        if (error.name === 'AbortError') {
            console.error('Fetch request timed out.');
        } else {
            console.error('Fetch request failed:', error);
        }
        showAlert('Error verifying the trx. Please try again later.', 'danger');
      }
    } catch (error) {
      console.error('Error:', error);
      showAlert('Error processing transaction. Please try again.', 'danger');
    }
  } else {
    showAlert('Invalid amount entered.', 'danger');
  }
}

async function transferSpinCoins() {
  const token = getJWT();

  const transferAmount = parseInt(transferAmountInput.value);
  const uniqueName = uniqueNameInput.value;
  if (!uniqueName || uniqueName.length < 3) {
    showAlert('Please enter a valid unique name.', 'warning', 'modalBody');
    return;
  }
  
  if (!transferAmount || transferAmount <= 0) {
    showAlert('Please enter a valid amount to transfer.', 'warning', 'modalBody');
    return;
  }

  try {
    // Call backend to process withdrawal and get the transaction parameters
    const response = await fetch('ajax.php', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `transfer&amount=${transferAmount}&to=${uniqueName}`,
    });
    
    const data = await response.json();
    // console.log(data);
    if (data.success) {
      transferAmountInput.value = '';
      uniqueNameInput.value = '';
      fetchUserBalance();
      fetchTransfers();
      showAlert(data.message, 'success', 'modalBody');
    } else {
      showAlert(data.message, 'danger', 'modalBody');
    }
  } catch (error) {
    // console.log("Error: " + error);
    showAlert('Error processing your transfer. Please try again later.', 'danger', 'modalBody');
  }
}

async function buyCustomSpinCoins() {
  const token = getJWT();
  if (!walletConnected || !walletAddress) {
    showAlert('Please connect your wallet first.', 'warning', 'modalBody');
    return;
  }

  const buyAmount = parseInt(buyAmountInput.value);
  if (!buyAmount || buyAmount <= 0) {
    showAlert('Please enter a valid amount to buy.', 'warning', 'modalBody');
    return;
  }

  try {
    // Call backend to process withdrawal and get the transaction parameters
    const response = await fetch('ajax.php', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `getAdminWallet`,
    });
    const data = await response.json();
    // console.log(data);
    if (data.success) {
      purchaseCoins(buyAmount, data.wallet);
    } else {
      // document.getElementById('spin_to_ton').innerText = 'Withdraw your SpinCoins to your TON wallet.';
      showAlert(data.message, 'danger', 'modalBody');
      // showAlert('Withdrawal failed. Please try again.', 'danger', 'modalBody');
    }
  } catch (error) {
    showAlert('Error processing your purchase. Please try again later.', 'danger', 'modalBody');
  }
}

async function withdrawSpinCoins() {
  const token = getJWT();
  if (!walletConnected || !walletAddress) {
    showAlert('Please connect your wallet first.', 'warning', 'modalBody');
    return;
  }

  const withdrawAmount = parseInt(withdrawAmountInput.value);
  if (!withdrawAmount || withdrawAmount <= 0) {
    showAlert('Please enter a valid amount to withdraw.', 'warning', 'modalBody');
    return;
  }

  try {
    // Call backend to process withdrawal and get the transaction parameters
    const response = await fetch('ajax.php', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `withdraw&amount=${withdrawAmount}&walletAddress=${walletAddress}`,
    });
    const data = await response.json();
    // console.log(data);
    if (data.success) {
      document.getElementById('spin_to_ton').innerText = 'Withdraw your SpinCoins to your TON wallet.';
      fetchWithdrawals();
      updateWinning(data.balance);
      showAlert(data.message, 'success', 'modalBody');
      // showAlert('We have received your withdrawal request and will process it soon!', 'success', 'modalBody');
      withdrawAmountInput.value = '';
    } else {
      // document.getElementById('spin_to_ton').innerText = 'Withdraw your SpinCoins to your TON wallet.';
      showAlert(data.message, 'danger', 'modalBody');
      // showAlert('Withdrawal failed. Please try again.', 'danger', 'modalBody');
    }
  } catch (error) {
    console.error('Error processing withdrawal:', error);
    showAlert('Error processing withdrawal. Please try again.', 'danger', 'modalBody');
  }
}


// Handle withdrawing SpinCoins
async function withdrawSpinCoins2() {
  if (!walletConnected || !walletAddress) {
    showAlert('Please connect your wallet first.', 'warning', 'modalBody');
    return;
  }

  const withdrawAmount = parseInt(withdrawAmountInput.value);
  if (!withdrawAmount || withdrawAmount <= 0) {
    showAlert('Please enter a valid amount to withdraw.', 'warning', 'modalBody');
    return;
  }

  if (withdrawAmount > userBalance) {
    showAlert('Insufficient balance to withdraw.', 'danger', 'modalBody');
    return;
  }

  try {
    // Call backend to process withdrawal and get the transaction parameters
    const response = await fetch('withdraw_spincoin.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ walletAddress, amount: withdrawAmount }),
    });
    const data = await response.json();

    if (data.success && data.transaction) {
      // Send the withdrawal transaction to the user's wallet
      await tonConnectUI.sendTransaction(data.transaction);

      showAlert('Withdrawal transaction sent. Waiting for confirmation...', 'info', 'modalBody');

      // Update the user's balance after confirmation
      updateBalance(data.newBalance);
      showAlert('Withdrawal successful!', 'success', 'modalBody');
      withdrawAmountInput.value = '';
    } else {
      showAlert('Withdrawal failed. Please try again.', 'danger', 'modalBody');
    }
  } catch (error) {
    console.error('Error processing withdrawal:', error);
    showAlert('Error processing withdrawal. Please try again.', 'danger', 'modalBody');
  }
}

async function displayUserBalance() {
  const balance = fetchUserBalance();
  
  updateBalance('Loading...');
  updateWinning('Loading...');
}

// Fetch user's balance from the backend
async function fetchUserBalance() {
  const token = getJWT();
  if (!token) {
      showAlert("You need to login first! Refresh the page to login again!", 'danger');
      return;
  }

  try {
    const response = await fetch('ajax.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'check-balance'
    });
    
    const data = await response.json();
    
    userBalance = data.balance;
    userWinning = data.winning;
    updateBalance(userBalance);
    updateWinning(userWinning);
    return data;
  } catch (error) {
    console.error('Error fetching user balance:', error);
    userBalance = 0;
    userWinning = 0;
    updateBalance(userBalance);
    updateWinning(userWinning);
  }
}

async function fetchUserWinning() {
  const token = getJWT();
  if (!token) {
      showAlert("You need to login first! Refresh the page to login again!", 'danger');
      return;
  }

  try {
    const response = await fetch('ajax.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'check-winning'
    });

    const data = await response.json();
    userWinning = data.winning;
    updateWinning(userWinning);
    return data;
  } catch (error) {
    console.error('Error fetching user winning:', error);
    return null;
  }
}

async function winningDetails(id) {
  const token = getJWT();
  if (!token) {
      showAlert("You need to login first! Refresh the page to login again!", 'danger');
      return;
  }

  try {
    const response = await fetch('ajax.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `winningDetail&id=${id}`
    });

    const data = await response.json();

    // display lotto detail in lotto-detail id dom
    const winningDetail = document.getElementById('winner-details');


    winningDetail.innerHTML = `<div id="winner-details">
                                <div class="alert alert-success alert-dismissible fade show mt-2" role="alert">
                                  <div class="d-flex justify-content-between">
                                    <div class="text-left">
                                      Winner: <strong>${data.user}</strong> <br>
                                      Lotto: <strong>${data.lotto_name}</strong> <br>
                                      Price: <strong>${data.ticket_price.toLocaleString()} ${data.currency}</strong> <br>
                                      Ticket: <strong>${data.winning_ticket}</strong> <br>
                                      Winning Spot: <strong>${data.lotto_level}</strong> <br>
                                      Date: <strong>${data.date}</strong> <br>                                      
                                      On: <strong>${data.draw_date}</strong> <br>                                      
                                    </div>
                                    <div class="text-right">
                                      <!-- Add any additional content here if needed -->
                                    </div>
                                  </div>
                                  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">×</span>
                                  </button>
                                </div>
                              </div>`;
  } catch (error) {
    console.error('Error fetching lottery details:', error);
  }
}

async function lottoDetail(id, placement = null) {
  const token = getJWT();
  if (!token) {
      showAlert("You need to login first! Refresh the page to login again!", 'danger');
      return;
  }

  try {
    const response = await fetch('ajax.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `lottoDetail&id=${id}`
    });

    const data = await response.json();

    // display lotto detail in lotto-detail id dom
    let lottoDetail;
    if (placement == 'user') {
      lottoDetail = document.getElementById('your-lotto-details');
    } else {
      lottoDetail = document.getElementById('lotto-details');
    }

    let buyButton = '<button class="btn btn-sm btn-secondary buy-btn disabled">Not Available</button>';
    if (data.status == 1) {
      buyButton = `<button class="btn btn-sm btn-success buy-btn" onclick="displayLottotPrices(${data.price}, ${data.id})">Buy A Ticket</button>`;
    }

    lottoDetail.innerHTML = `<div class="alert alert-success alert-dismissible fade show mt-2" role="alert">
                              ${data.name} <br><br>
                              Price: ${data.price.toLocaleString()} ${data.currency} <br>
                              1 st place: ${data.first_place_reward.toLocaleString()} <br>
                              2 nd place: ${data.second_place_reward.toLocaleString()} <br>
                              3 rd place: ${data.third_place_reward.toLocaleString()} <br><br>
                              Draw Date: ${data.draw_date} <br><br>
                              ${buyButton}
                              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">×</span>
                              </button>
                            </div>`;
  } catch (error) {
    console.error('Error fetching lottery details:', error);
  }
}

async function fetchWinners(limit = 10, search = '', page = 1) {
  const token = getJWT();
  if (!token) {
    showAlert("You need to login first! Refresh the page to login again!", 'danger');
    return;
  }

  try {
    const response = await fetch('ajax.php', {
      method: 'POST',
      headers: {
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `winners&limit=${limit}&search=${encodeURIComponent(search)}&page=${page}`,
    });

    const data = await response.json();

    console.log(data);

    const winnersList = document.getElementById('winnersList');
    if (page === 1) {
      winnersList.innerHTML = ''; // Clear list if it's the first page

      // Add table header
      const headerItem = document.createElement('li');
      headerItem.className = 'text-dark list-group-item d-flex justify-content-between align-items-center font-weight-bold';
      headerItem.innerHTML = `
        <span>Winner</span>
        <span>Price</span>
        <span>Amount</span>
      `;
      winnersList.appendChild(headerItem);
    }

    data.forEach((winner) => {
      const listItem = document.createElement('li');
      listItem.className = 'text-dark list-group-item d-flex justify-content-between align-items-center';
      listItem.innerHTML = `
        <span>${winner.user}</span>
        <span>${winner.ticket_price}</span>
        <span>${winner.reward_amount} ${winner.currency}</span>
      `;
      listItem.onclick = () => winningDetails(winner.id);
      winnersList.appendChild(listItem);
    });

    // Check if more winners are available and append a "Load More" button if necessary
    if (data.length === limit) {
      const loadMoreButton = document.createElement('button');
      loadMoreButton.className = 'btn btn-primary mt-3';
      loadMoreButton.textContent = 'Load More';
      loadMoreButton.addEventListener('click', () => {
        fetchWinners(limit, search, page + 1);
        loadMoreButton.remove(); // Remove the button after clicking
      });
      winnersList.appendChild(loadMoreButton);
    }
  } catch (error) {
    console.error('Error fetching winners:', error);
  }
}

async function fetchUserTikcets(limit = 10, search = '', page = 1) {
  const token = getJWT();
  if (!token) {
    showAlert("You need to login first! Refresh the page to login again!", 'danger');
    return;
  }

  try {
    const response = await fetch('ajax.php', {
      method: 'POST',
      headers: {
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `userTickets&limit=${limit}&search=${encodeURIComponent(search)}&page=${page}`,
    });

    const data = await response.json();

    // console.log(data);

    const userTicketsList = document.getElementById('userTicketsList');
    if (page === 1) {
      userTicketsList.innerHTML = ''; // Clear list if it's the first page

      // Add table header
      const headerItem = document.createElement('li');
      headerItem.className = 'text-dark list-group-item d-flex justify-content-between align-items-center font-weight-bold';
      headerItem.innerHTML = `
        <span>Ticket</span>
        <span>Price</span>
        <span>Status</span>
      `;
      userTicketsList.appendChild(headerItem);
    }

    data.forEach((lottery) => {
      let status = '';
      if (lottery.status == null) {
        status = '';
      } else {
        status = lottery.status;
        if (status == 0) {
          status = `<span class="badge badge-warning badge-pill">Pending</span>`;
        } else if (status == 1) {
          status = `<span class="badge badge-success badge-pill">Active</span>`;
        } else if (status == 2) {
          status = `<span class="badge badge-danger badge-pill">Drawn</span>`;
        } else {
          status = `<span class="badge badge-danger badge-pill">Unknown</span>`;
        }
      }

      const listItem = document.createElement('li');
      listItem.className = 'text-dark list-group-item d-flex justify-content-between align-items-center';
      listItem.innerHTML = `
        <span>${lottery.ticket_number}</span>
        <span>${lottery.price} ${lottery.currency}</span>
        <span>${status}</span>
      `;

      // Row click handler
      listItem.addEventListener('click', () => {
        lottoDetail(lottery.lotto_id, 'user');
      });

      userTicketsList.appendChild(listItem);
    });

    // Check if more lottery prices are available and append a "Load More" button if necessary
    if (data.length === limit) {
      const loadMoreButton = document.createElement('button');
      loadMoreButton.className = 'btn btn-primary mt-3';
      loadMoreButton.textContent = 'Load More';
      loadMoreButton.addEventListener('click', () => {
        fetchUserTikcets(limit, search, page + 1);
        loadMoreButton.remove(); // Remove the button after clicking
      });
      userTicketsList.appendChild(loadMoreButton);
    }
  } catch (error) {
    console.error('Error fetching lottery prices:', error);
  }
}

async function fetchLotteries(limit = 10, search = '', page = 1) {
  const token = getJWT();
  if (!token) {
    showAlert("You need to login first! Refresh the page to login again!", 'danger');
    return;
  }

  try {
    const response = await fetch('ajax.php', {
      method: 'POST',
      headers: {
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `lotteries&limit=${limit}&search=${encodeURIComponent(search)}&page=${page}`,
    });

    const data = await response.json();

    console.log(data);

    const lotteriesList = document.getElementById('lotteriesList');
    if (page === 1) {
      lotteriesList.innerHTML = ''; // Clear list if it's the first page

      // Add table header
      const headerItem = document.createElement('li');
      headerItem.className = 'text-dark list-group-item d-flex justify-content-between align-items-center font-weight-bold';
      headerItem.innerHTML = `
        <span>Lotto</span>
        <span>Price</span>
        <span>Purchase</span>
      `;
      lotteriesList.appendChild(headerItem);
    }

    data.forEach((lottery, index) => {
      let status = '';
      let purchase = '';
      if (lottery.status == null) {
        status = '';
      } else {
        status = lottery.status;
        if (status == 0) {
          status = `<span class="badge badge-warning badge-pill">Pending</span>`;
          purchase = `<button class="btn btn-sm btn-secondary disabled">Buy</button>`;
        } else if (status == 1) {
          status = `<span class="badge badge-success badge-pill">Active</span>`;
          purchase = `<button class="btn btn-sm btn-success buy-btn" data-price="${lottery.price}" data-id="${lottery.id}">Buy</button>`;
        } else if (status == 2) {
          status = `<span class="badge badge-danger badge-pill">Drawn</span>`;
          purchase = `<button class="btn btn-sm btn-danger buy-btn disabled">Buy</button>`;
        } else {
          status = `<span class="badge badge-scondary badge-pill">Unknown</span>`;
          purchase = `<button class="btn btn-sm btn-secondary disabled">Buy</button>`;
        }
      }

      const listItem = document.createElement('li');
      listItem.className = 'text-dark list-group-item d-flex justify-content-between align-items-center';
      listItem.innerHTML = `
        <span>${lottery.name}</span>
        <span>${lottery.price} ${lottery.currency}</span>
        <span>${purchase}</span>
      `;

      // Row click handler
      listItem.addEventListener('click', () => {
        lottoDetail(lottery.id);
      });

      // Prevent row click when "Buy" button is clicked
      const buyButton = listItem.querySelector('.buy-btn');
      if (buyButton) {
        buyButton.addEventListener('click', (event) => {
          event.stopPropagation(); // Prevent the click from bubbling to the row
          const price = buyButton.getAttribute('data-price');
          const id = buyButton.getAttribute('data-id');
          displayLottotPrices(price, id);
        });
      }

      lotteriesList.appendChild(listItem);
    });

    // Check if more lottery prices are available and append a "Load More" button if necessary
    if (data.length === limit) {
      const loadMoreButton = document.createElement('button');
      loadMoreButton.className = 'btn btn-primary mt-3';
      loadMoreButton.textContent = 'Load More';
      loadMoreButton.addEventListener('click', () => {
        fetchLotteries(limit, search, page + 1);
        loadMoreButton.remove(); // Remove the button after clicking
      });
      lotteriesList.appendChild(loadMoreButton);
    }
  } catch (error) {
    console.error('Error fetching lottery prices:', error);
  }
}

async function fetchCoinPrices(limit = 10, search = '', page = 1) {
  const token = getJWT();

  if (!token) {
      showAlert("You need to login first! Refresh the page to login again!", 'danger');
      return;
  }

  try {
    const response = await fetch('ajax.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `coinPrices&limit=${limit}&search=${encodeURIComponent(search)}&page=${page}`
    });

    const data = await response.json();

    const coinPricesList = document.getElementById('coinPricesList');
    if (page === 1) {
      coinPricesList.innerHTML = ''; // Clear list if it's the first page

      // Add table header
      const headerItem = document.createElement('li');
      headerItem.className = 'text-dark list-group-item d-flex justify-content-between align-items-center font-weight-bold';
      headerItem.innerHTML = `
        <span>SpinCoin</span>
        <span>Price</span>
        <span>Bonus</span>
      `;
      coinPricesList.appendChild(headerItem);
    }
    data.forEach((coin, index) => {
      let bonus = '';
      if (coin.percentage == null) {
        bonus = '';
      } else {
        bonus = coin.percentage;
        bonus = `<span class="badge badge-success badge-pill">+${bonus}%</span>`;
      }

      const listItem = document.createElement('li');
      listItem.className = 'text-dark list-group-item d-flex justify-content-between align-items-center';
      listItem.innerHTML = `
        <span>${coin.coin}</span>
        <span>${coin.price} ${coin.currency}</span>
        <span>${bonus}</span>
      `;
      listItem.onclick = () => purchaseCoins(coin.price, '');
      coinPricesList.appendChild(listItem);
    });

    // Check if more coin prices are available and append a "Load More" button if necessary
    if (data.length === limit) {
      const loadMoreButton = document.createElement('button');
      loadMoreButton.className = 'btn btn-primary mt-3';
      loadMoreButton.textContent = 'Load More';
      loadMoreButton.addEventListener('click', () => {
        fetchCoinPrices(limit, search, page + 1);
        loadMoreButton.remove(); // Remove the button after clicking
      });
      coinPricesList.appendChild(loadMoreButton);
    }
  } catch (error) {
    console.error('Error fetching coin prices:', error);
  }
}

async function fetchWithdrawals(limit = 10, search = '', page = 1) {
  const token = getJWT();
  if (!token) {
      showAlert("You need to login first! Refresh the page to login again!", 'danger');
      return;
  }

  try {
    const response = await fetch('ajax.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `withdrawals&limit=${limit}&search=${encodeURIComponent(search)}&page=${page}`
    });    
    
    const data = await response.json();

    const withdrawalsList = document.getElementById('withdrawalsList');
    if (page === 1) {
      withdrawalsList.innerHTML = ''; // Clear list if it's the first page

      // Add table header
      const headerItem = document.createElement('li');
      headerItem.className = 'text-dark list-group-item d-flex justify-content-between align-items-center font-weight-bold';
      headerItem.innerHTML = `
        <span>SpinCoin</span>
        <span>Amount</span>
        <span>Status</span>
      `;
      withdrawalsList.appendChild(headerItem);
    }
    data.forEach((withdrawal, index) => {
      var badgeColor = 'primary';
      var statusDisplay = 'Pending';
      if (withdrawal.status == 1) {
          badgeColor = 'success';
          statusDisplay = 'Paid';
      } else if (withdrawal.status == 2) {
          badgeColor = 'warning';
          statusDisplay = 'Auditing';
      } else if (withdrawal.status == 3) {
          badgeColor = 'danger';
          statusDisplay = 'Rejected';
      }

      const listItem = document.createElement('li');
      listItem.className = 'text-dark list-group-item d-flex justify-content-between align-items-center';
      listItem.innerHTML = `
        <span>${withdrawal.coin_amount}</span>
        <span>${withdrawal.currency_amount} ${withdrawal.currency}</span>
        <span class="badge badge-${badgeColor} badge-pill">${statusDisplay}</span>
      `;
      listItem.onclick = () => alert(`Withdrawal (Account): ${withdrawal.wallet}`);
      withdrawalsList.appendChild(listItem);
    });

    // Check if more withdrawals are available and append a "Load More" button if necessary
    if (data.length === limit) {
      const loadMoreButton = document.createElement('button');
      loadMoreButton.className = 'btn btn-secondary mt-3';
      loadMoreButton.textContent = 'Load More';
      loadMoreButton.addEventListener('click', () => {
        fetchWithdrawals(limit, search, page + 1);
        loadMoreButton.remove(); // Remove the button after clicking
      }
      );
      withdrawalsList.appendChild(loadMoreButton);
    }
  } catch (error) {
    console.error('Error fetching withdrawals:', error);
  }
}

async function fetchReferrals(limit = 10, search = '', page = 1) {
  const token = getJWT();
  if (!token) {
      showAlert("You need to login first! Refresh the page to login again!", 'danger');
      return;
  }

  try {
    const response = await fetch('ajax.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `referrals&limit=${limit}&search=${encodeURIComponent(search)}&page=${page}`
    });    
    
    const data = await response.json();

    const referralsList = document.getElementById('referralsList');
    if (page === 1) {
      referralsList.innerHTML = ''; // Clear list if it's the first page

      // Add table header
      const headerItem = document.createElement('li');
      headerItem.className = 'text-dark list-group-item d-flex justify-content-between align-items-center font-weight-bold';
      headerItem.innerHTML = `
        <span>User</span>
        <span>Registered On</span>
        
      `;
      referralsList.appendChild(headerItem);
    }
    data.forEach((referral, index) => {
      const listItem = document.createElement('li');
      listItem.className = 'text-dark list-group-item d-flex justify-content-between align-items-center';
      listItem.innerHTML = `
        <span>${referral.unique_name}</span>
        <span>${referral.created_at_relative}</span>
        
      `;
      listItem.onclick = () => alert(`Referral: ${referral.unique_name}`);
      referralsList.appendChild(listItem);
    });

    // Check if more referrals are available and append a "Load More" button if necessary
    if (data.length === limit) {
      const loadMoreButton = document.createElement('button');
      loadMoreButton.className = 'btn btn-primary mt-3';
      loadMoreButton.textContent = 'Load More';
      loadMoreButton.addEventListener('click', () => {
        fetchReferrals(limit, search, page + 1);
        loadMoreButton.remove(); // Remove the button after clicking
      });
      referralsList.appendChild(loadMoreButton);
    }
  } catch (error) {
    console.error('Error fetching referrals:', error);
  }
}

async function fetchOrders(limit = 10, search = '', page = 1) {
  const token = getJWT();
  if (!token) {
      showAlert("You need to login first! Refresh the page to login again!", 'danger');
      return;
  }

  try {
    const response = await fetch('ajax.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `orders&limit=${limit}&search=${encodeURIComponent(search)}&page=${page}`
    });    
    
    const data = await response.json();

    const ordersList = document.getElementById('ordersList');
    if (page === 1) {
      ordersList.innerHTML = ''; // Clear list if it's the first page

      // Add table header
      const headerItem = document.createElement('li');
      headerItem.className = 'text-dark list-group-item d-flex justify-content-between align-items-center font-weight-bold';
      headerItem.innerHTML = `
        <span>SpinCoin</span>
        <span>Currency Amount</span>
        <span>Status</span>
      `;
      ordersList.appendChild(headerItem);
    }
    data.forEach((transaction, index) => {
      var badgeColor = 'primary';
      var statusDisplay = 'Pending';
      if (transaction.status == 1) {
          badgeColor = 'success';
          statusDisplay = 'Completed';
      } else if (transaction.status == 2) {
          badgeColor = 'warning';
          statusDisplay = 'Failed';
      } else if (transaction.status == 3) {
          badgeColor = 'danger';
          statusDisplay = 'Rejected';
      }

      const listItem = document.createElement('li');
      listItem.className = 'text-dark list-group-item d-flex justify-content-between align-items-center';
      listItem.innerHTML = `
        <span>${transaction.coin_amount}</span>
        <span>${transaction.amount} ${transaction.currency}</span>
        <span class="badge badge-${badgeColor} badge-pill">${statusDisplay}</span>
      `;
      listItem.onclick = () => alert(`Order Number: ${transaction.unique_identifier}`);
      ordersList.appendChild(listItem);
    });

    // Check if more transactions are available and append a "Load More" button if necessary
    if (data.length === limit) {
      const loadMoreButton = document.createElement('button');
      loadMoreButton.className = 'btn btn-primary mt-3';
      loadMoreButton.textContent = 'Load More';
      loadMoreButton.addEventListener('click', () => {
        fetchOrders(limit, search, page + 1);
        loadMoreButton.remove(); // Remove the button after clicking
      });
      ordersList.appendChild(loadMoreButton);
    }
  } catch (error) {
    console.error('Error fetching transactions:', error);
  }
}

async function fetchTransfers(limit = 10, search = '', page = 1) {
  const token = getJWT();
  if (!token) {
      showAlert("You need to login first! Refresh the page to login again!", 'danger');
      return;
  }

  try {
    const response = await fetch('ajax.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `transfers&limit=${limit}&search=${encodeURIComponent(search)}&page=${page}`
    });    
    
    const data = await response.json();

    const transfersList = document.getElementById('transfersList');
    if (page === 1) {
      transfersList.innerHTML = ''; // Clear list if it's the first page

      // Add table header
      const headerItem = document.createElement('li');
      headerItem.className = 'text-dark list-group-item d-flex justify-content-between align-items-center font-weight-bold';
      headerItem.innerHTML = `
        <span>SpinCoin</span>
        <span>User</span>
        <span>Direction</span>
      `;
      transfersList.appendChild(headerItem);
    }
    data.forEach((transfer) => {
      var badgeColor = 'danger';
      var directionDisplay = 'Sent';
      if (transfer.transaction_type == 'received') {
          badgeColor = 'success';
          directionDisplay = 'Received';
      }

      const listItem = document.createElement('li');
      listItem.className = 'text-dark list-group-item d-flex justify-content-between align-items-center';
      listItem.innerHTML = `
        <span>${transfer.amount}</span>
        <span>${transfer.user}</span>
        <span class="badge badge-${badgeColor} badge-pill">${directionDisplay}</span>
      `;
      listItem.onclick = () => alert(`Reference Number: ${transfer.reference_number}`);
      transfersList.appendChild(listItem);
    });

    // Check if more transfers are available and append a "Load More" button if necessary
    if (data.length === limit) {
      const loadMoreButton = document.createElement('button');
      loadMoreButton.className = 'btn btn-secondary mt-3';
      loadMoreButton.textContent = 'Load More';
      loadMoreButton.addEventListener('click', () => {
        fetchTransfers(limit, search, page + 1);
        loadMoreButton.remove(); // Remove the button after clicking
      });
      transfersList.appendChild(loadMoreButton);
    }
  } catch (error) {
    console.error('Error fetching transfers:', error);
  }
}

async function fetchTransactions(limit = 10, search = '', page = 1) {
  const token = getJWT();
  if (!token) {
      showAlert("You need to login first! Refresh the page to login again!", 'danger');
      return;
  }

  try {
    const response = await fetch('ajax.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `transactions&limit=${limit}&search=${encodeURIComponent(search)}&page=${page}`
    });    
    
    const data = await response.json();

    const transactionsList = document.getElementById('transactionsList');
    if (page === 1) {
      transactionsList.innerHTML = ''; // Clear list if it's the first page

      // Add table header
      const headerItem = document.createElement('li');
      headerItem.className = 'text-dark list-group-item d-flex justify-content-between align-items-center font-weight-bold';
      headerItem.innerHTML = `
        <span>SpinCoin</span>
        <span>Type</span>
      `;
      transactionsList.appendChild(headerItem);
    }
    data.forEach((transaction, index) => {
      var badgeColor = 'info';
      if (transaction.trx_type == 'referral') {
          badgeColor = 'success';
      } else if (transaction.trx_type == 'order') {
          badgeColor = 'info';
      } else if (transaction.trx_type == 'win') {
          badgeColor = 'success';
      } else if (transaction.trx_type == 'loss') {
          badgeColor = 'danger';
      }

      const listItem = document.createElement('li');
      listItem.className = 'text-dark list-group-item d-flex justify-content-between align-items-center';
      listItem.innerHTML = `
        <span>${transaction.coin_amount}</span>
        <span class="badge badge-${badgeColor} badge-pill">${transaction.trx_type}</span>
      `;
      listItem.onclick = () => alert(`Transaction Number: ${transaction.trx_number}`);
      transactionsList.appendChild(listItem);
    });

    // Check if more transactions are available and append a "Load More" button if necessary
    if (data.length === limit) {
      const loadMoreButton = document.createElement('button');
      loadMoreButton.className = 'btn btn-primary mt-3';
      loadMoreButton.textContent = 'Load More';
      loadMoreButton.addEventListener('click', () => {
        fetchTransactions(limit, search, page + 1);
        loadMoreButton.remove(); // Remove the button after clicking
      });
      transactionsList.appendChild(loadMoreButton);
    }
  } catch (error) {
    console.error('Error fetching transactions:', error);
  }
}

// Fetch leaderboard data from the backend
async function fetchLeaderboard() {
  const token = getJWT();
  if (!token) {
      showAlert("You need to login first! Refresh the page to login again!", 'danger');
      return;
  }

  try {
    const response = await fetch('ajax.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'leaderboard'
    });    
    
    const data = await response.json();

    const leaderboardList = document.getElementById('leaderboardList');
    leaderboardList.innerHTML = '';
    data.forEach((player, index) => {
      const listItem = document.createElement('li');
      listItem.className = 'list-group-item d-flex justify-content-between align-items-center';
      listItem.innerHTML = `
        <span>${index + 1}. ${player.name}</span>
        <span class="badge badge-primary badge-pill">${player.score}</span>
      `;
      leaderboardList.appendChild(listItem);
    });
  } catch (error) {
    console.error('Error fetching leaderboard:', error);
  }
}

// fetch plans data from the backend
async function fetchPlans() {
  const token = getJWT();
  if (!token) {
      showAlert("You need to login first! Refresh the page to login again!", 'danger');
      return;
  }

  try {
    const response = await fetch('ajax.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'plans'
    });

    const data = await response.json();

    const plansList = document.getElementById('plansList');
    plansList.innerHTML = '';
    data.forEach((plan, index) => {
      // {"id":1,"label":"Basic","options":{"ETB":{"price":100,"spincoin":10000,"info":"Get normal SpinCoins"},"TON":{"price":0.14,"spincoin":12000,"info":"Get 20% more SpinCoins"},"USD":{"price":0.74,"spincoin":12500,"info":"Get 25% more SpinCoins"},"USDC":{"price":0.74,"spincoin":12500,"info":"Get 25% more SpinCoins"},"USDT":{"price":0.74,"spincoin":12500,"info":"Get 25% more SpinCoins"}}},{"id":2,"label":"Standard","options":{"ETB":{"price":300,"spincoin":39000,"info":"Get normal SpinCoins"},"TON":{"price":0.41,"spincoin":46800,"info":"Get 20% more SpinCoins"},"USD":{"price":2.22,"spincoin":48750,"info":"Get 25% more SpinCoins"},"USDC":{"price":2.22,"spincoin":48750,"info":"Get 25% more SpinCoins"},"USDT":{"price":2.22,"spincoin":48750,"info":"Get 25% more SpinCoins"}}},{"id":3,"label":"Premium","options":{"ETB":{"price":500,"spincoin":75000,"info":"Get normal SpinCoins"},"TON":{"price":0.68,"spincoin":90000,"info":"Get 20% more SpinCoins"},"USD":{"price":3.7,"spincoin":93750,"info":"Get 25% more SpinCoins"},"USDC":{"price":3.7,"spincoin":93750,"info":"Get 25% more SpinCoins"},"USDT":{"price":3.7,"spincoin":93750,"info":"Get 25% more SpinCoins"}}}
      const listItem = document.createElement('div');
      listItem.className = '';
      listItem.innerHTML = `


      <div class="card buy-card mt-3">
        <div class="card-header" id="headingOne${plan.id}">
          <h5 class="card-title">
            <div class="collapsed" data-toggle="collapse" data-target="#collapseOne${plan.id}" aria-expanded="false" aria-controls="collapseOne${plan.id}">
            ${plan.label}
            </div>
          </h5>
        </div>
    
        <div id="collapseOne${plan.id}" class="collapse" aria-labelledby="headingOne${plan.id}" data-parent="#plansList">
          <div class="card-body">
            <ul class="list-group text-dark">
              <li class="list-group list-group-item d-flex justify-content-between align-items-center">
                <span><strong>ETB</strong></span>
                <span>PRICE ${plan.options.ETB.price} ETB</span><br>
                <span>${plan.options.ETB.base_spincoin.toLocaleString()} Base SpinCoins</span>
                <span>${plan.options.ETB.currency_bonus_percent.toLocaleString()}% Currency Bonus</span>
                <span>${plan.options.ETB.plan_bonus_percent.toLocaleString()}% Plan Bonus</span>
                <span>${plan.options.ETB.total_bonus_percent.toLocaleString()}% Total Bonus</span>
                <span>${plan.options.ETB.bonus_spincoin.toLocaleString()} Bonus SpinCoins</span><br>
                <span><strong>${plan.options.ETB.spincoin.toLocaleString()}</strong> New SpinCoins</span>
                <span>${plan.options.ETB.info}</span>
              </li><br>
              <li class="list-group list-group-item d-flex justify-content-between align-items-center">
                <span><strong>TON</strong></span>
                <span>PRICE ${plan.options.TON.price} TON</span><br>
                <span>${plan.options.TON.base_spincoin.toLocaleString()} Base SpinCoins</span>
                <span>${plan.options.TON.currency_bonus_percent.toLocaleString()}% Currency Bonus</span>
                <span>${plan.options.TON.plan_bonus_percent.toLocaleString()}% Plan Bonus</span>
                <span>${plan.options.TON.total_bonus_percent.toLocaleString()}% Total Bonus</span>
                <span>${plan.options.TON.bonus_spincoin.toLocaleString()} Bonus SpinCoins</span><br>
                <span><strong>${plan.options.TON.spincoin.toLocaleString()}</strong> New SpinCoins</span>
                <span>${plan.options.TON.info}</span>
              </li><br>
              <li class="list-group list-group-item d-flex justify-content-between align-items-center">
                <span><strong>USDC</strong></span>
                <span>PRICE ${plan.options.USDC.price} USDC</span><br>
                <span>${plan.options.USDC.base_spincoin.toLocaleString()} Base SpinCoins</span>
                <span>${plan.options.USDC.currency_bonus_percent.toLocaleString()}% Currency Bonus</span>
                <span>${plan.options.USDC.plan_bonus_percent.toLocaleString()}% Plan Bonus</span>
                <span>${plan.options.USDC.total_bonus_percent.toLocaleString()}% Total Bonus</span>
                <span>${plan.options.USDC.bonus_spincoin.toLocaleString()} Bonus SpinCoins</span><br>
                <span><strong>${plan.options.USDC.spincoin.toLocaleString()}</strong> New SpinCoins</span>
                <span>${plan.options.USDC.info}</span>
              </li><br>
              <li class="list-group list-group-item d-flex justify-content-between align-items-center">
                <span><strong>USDT</strong></span>
                <span>PRICE ${plan.options.USDT.price} USDT</span><br>
                <span>${plan.options.USDT.base_spincoin.toLocaleString()} Base SpinCoins</span>
                <span>${plan.options.USDT.currency_bonus_percent.toLocaleString()}% Currency Bonus</span>
                <span>${plan.options.USDT.plan_bonus_percent.toLocaleString()}% Plan Bonus</span>
                <span>${plan.options.USDT.total_bonus_percent.toLocaleString()}% Total Bonus</span>
                <span>${plan.options.USDT.bonus_spincoin.toLocaleString()} Bonus SpinCoins</span><br>
                <span><strong>${plan.options.USDT.spincoin.toLocaleString()}</strong> New SpinCoins</span>
                <span>${plan.options.USDT.info}</span>

                <button id="rechargeBtn" class="btn btn-dark btn-lg mt-3">Recharge</button>
              </li>
            </ul>
          </div>
        </div>
      </div>


      `;
      plansList.appendChild(listItem);
    });
  } catch (error) {
    console.error('Error fetching plans:', error);
  }
}


    
// Fetch sectors from the backend
async function fetchSectors() {
  const token = getJWT();
  if (!token) {
      showAlert("You need to login first! Refresh the page to login again!", 'danger');
      return;
  }

  try {
    const response = await fetch('ajax.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'sectors'
    });    
    
    const data = await response.json();
    
    sectors = data;
  } catch (error) {
    console.error('Error fetching sectors:', error);
  }
}

// Assign colors to sectors dynamically, ensuring adjacent sectors have different colors
function assignColorsToSectors() {
  for (let i = 0; i < sectors.length; i++) {
    sectors[i].color = colorPalette[i % colorPalette.length];
    sectors[i].text = '#000000'; // Black text for readability
  }

  // Ensure adjacent sectors don't have the same color
  for (let i = 1; i < sectors.length; i++) {
    if (sectors[i].color === sectors[i - 1].color) {
      const nextColorIndex = (colorPalette.indexOf(sectors[i].color) + 1) % colorPalette.length;
      sectors[i].color = colorPalette[nextColorIndex];
    }
  }
}

// Draw each sector on the canvas
function drawSector(sector, i) {
  const ang = arc * i;
  ctx.save();

  // Draw sector
  ctx.beginPath();
  ctx.fillStyle = sector.color;
  ctx.moveTo(rad, rad);
  ctx.arc(rad, rad, rad, ang, ang + arc);
  ctx.lineTo(rad, rad);
  ctx.fill();

  // Draw sector label
  ctx.translate(rad, rad);
  ctx.rotate(ang + arc / 2);
  ctx.textAlign = 'right';
  ctx.fillStyle = sector.text;
  ctx.font = 'bold 16px Roboto, sans-serif';
  ctx.fillText(sector.label, rad - 10, 10);

  ctx.restore();
}

// Rotate the wheel and update the display
function rotate() {
  const sector = sectors[getIndex()];
  ctx.canvas.style.transform = `rotate(${ang - PI / 2}rad)`;

  spinEl.textContent = angVel ? sector.label : 'SPIN';
  spinEl.style.background = sector.color;
  spinEl.style.color = sector.text;

  // Play tick sound when entering a new sector
  if (angVel > 0 && sector !== prevSector) {
    tickSound.currentTime = 0;
    tickSound.play().catch((error) => {
      console.error('Error playing tick sound:', error);
    });
    prevSector = sector;
  }
}

// Animation frame function
function frame() {
  if (!angVel && spinButtonClicked) {
    const finalSector = sectors[getIndex()];

    events.fire('spinEnd', finalSector);
    spinButtonClicked = false;
    spinEl.disabled = false; // Re-enable the spin button

    // Play the outcome sound
    if (outcomeSound) {
      outcomeSound.play().catch((error) => {
        console.error('Error playing outcome sound:', error);
      });
    }
    outcomeSound = null; // Reset for next spin

    if (triggerConfetti == true) {
      triggerFireworksTwo();
      triggerConfetti = false;
    }
  }

  ang += angVel; // Update angle
  ang %= TAU; // Normalize angle between 0 and TAU

  angVel *= friction; // Apply friction
  if (angVel < 0.002) angVel = 0; // Stop the wheel when angular velocity is low enough

  rotate();
  requestAnimationFrame(frame); // Continue the animation loop
}

// Fetch the predetermined outcome from the backend
async function fetchOutcome(betAmount) {
  const token = getJWT();
  if (!token) {
      showAlert("You need to login first! Refresh the page to login again!", 'danger');
      return;
  }

  try {
    const response = await fetch('ajax.php', {
      method: 'POST',
      headers: {
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: `outcome&betAmount=${betAmount}`
    });    
    
    const data = await response.json();
    
    updateBalance(data.newBalance);
    return data; // Data contains 'label', 'sound', and 'newBalance'
  } catch (error) {
    console.error('Error fetching outcome:', error);
    return null;
  }
}

// Handle the spin action
async function spinWheel() {
  if (angVel) return; // Prevent multiple spins

  const betAmount = parseInt(betAmountInput.value);
  if (!betAmount || betAmount <= 0) {
    showAlert('Please enter a valid bet amount.', 'warning', 'modalBody');
    return;
  }

  // Check if the user has sufficient balanceconst token = getJWT();
  const token = getJWT();
  if (!token) {
    showAlert("You need to login first! Refresh the page to login again!", 'danger');
    return;
  }

  const response = await fetch('ajax.php', {
    method: 'POST',
    headers: {
      'Authorization': 'Bearer ' + token,
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `check-against&betAmount=${betAmount}`
  });

  
  const balanceData = await response.json();
  setWinningMessage(balanceData.outcome.message);
  console.log(balanceData);
  if (!balanceData.hasEnoughBalance) {
    setWinningMessage(balanceData.outcome.message);
    showAlert(balanceData.outcome.message, 'danger', 'modalBody');
    // showAlert('Insufficient balance. Please buy more SpinCoins.', 'danger', 'modalBody');
    return;
  }

  spinEl.disabled = true; // Disable the spin button

  const outcomeData = balanceData.outcome;
  // console.log(balanceData);
  // console.log(outcomeData);
  
  if (!outcomeData) {
    showAlert('Error determining outcome. Try again!', 'danger', 'modalBody');
    spinEl.disabled = false;
    return;
  }

  updateBalance(balanceData.newBalance);
  updateWinning(balanceData.newWinningBalance);

  const desiredOutcome = outcomeData.label;
  const outcomeSoundFile = outcomeData.sound;

  const desiredIndex = sectors.findIndex((sector) => sector.label === desiredOutcome);
  if (desiredIndex === -1) {
    // Outcome not found on the wheel
    showAlert('Something went wrong. Try again!', 'danger', 'modalBody');
    spinEl.disabled = false;
    return;
  }

  // Randomize extra rotations for longer spin duration
  const extraRotations = Math.floor(rand(6, 10)); // Between 6 and 10 rotations

  // Calculate the angle to stop at the desired sector
  const stopAngle = (TAU / sectors.length) * (sectors.length - desiredIndex) - arc / 2;
  const currentRotation = ang % TAU;
  let totalRotation = extraRotations * TAU + stopAngle - currentRotation;

  // Ensure totalRotation is positive
  if (totalRotation < 0) {
    totalRotation += extraRotations * TAU;
  }

  // Calculate initial angular velocity
  angVel = totalRotation * (1 - friction);

  // Reset previous sector for sound effect
  prevSector = null;

  // Load the outcome sound
  outcomeSound = new Audio(outcomeSoundFile);
  if (balanceData.outcome.confetti == 'win') {
    triggerConfetti = true;
  }

  spinButtonClicked = true;

  // Clear the bet input
  // betAmountInput.value = '';
  removeShowAlert();
}

async function prepareLiveLotto() {
  const token = getJWT();
  // Sound effects (local files in the same directory)
  const tickSound = new Howl({
      src: ['tick.mp3'], // Local tick sound file
      volume: 0.2
  });

  const winSound = new Howl({
      src: ['winning.wav'], // Local win sound file
      volume: 0.7
  });

  const countdownSound = new Howl({
      src: ['countdown.wav'], // Local countdown sound file
      volume: 0.5
  });

  let countdownInterval;
  let winnersDisplayed = false; // Flag to track if winners have been displayed

  function startPolling() {
      // Poll the backend every second
      setInterval(async () => {
          try {
            const response = await fetch('ajax.php', {
              method: 'POST',
              headers: {
                  'Authorization': `Bearer ${token}`,
                  'Content-Type': 'application/x-www-form-urlencoded',
              },
              body: 'liveDraw'
            });
            const data = await response.json();
            // console.log(data);
            if (data.status === 'countdown') {
              handleCountdown(data.timeLeft);
            } else if (data.status === 'draw' && !winnersDisplayed) {
              clearInterval(countdownInterval);
              handleDraw(data.winners, data.drawnOn, data.currentTime);
            }
          } catch (error) {
            console.error('Error fetching data:', error);
          }
      }, 1000);
  }

  function handleCountdown(timeLeft) {
      const countdownElement = document.getElementById('countdown');
      const { days, hours, minutes, seconds } = formatTimeLeft(timeLeft);

      countdownElement.textContent = `${days}d ${hours}h ${minutes}m ${seconds}s left`;

      // Play countdown sound when seconds are below 10
      if (timeLeft <= 10) {
          countdownSound.play();
          triggerFireworks();
      }
  }

  function formatTimeLeft(timeLeft) {
      const days = Math.floor(timeLeft / (24 * 60 * 60));
      const hours = Math.floor((timeLeft % (24 * 60 * 60)) / (60 * 60));
      const minutes = Math.floor((timeLeft % (60 * 60)) / 60);
      const seconds = Math.floor(timeLeft % 60);

      return { days, hours, minutes, seconds };
  }

  function handleDraw(winners, drawnOn, currentTime) {
      const countdownElement = document.getElementById('countdown');
      const drawTime = new Date(drawnOn).getTime(); // Convert drawnOn to timestamp
      const serverTime = new Date(currentTime).getTime(); // Convert currentTime to timestamp
      const timeSinceDraw = Math.floor((serverTime - drawTime) / 1000); // Time since draw in seconds


      if (timeSinceDraw <= 60) {
          // Animate the winners if the draw was less than a minute ago
          animateWinners(winners);
      } else {
          countdownElement.textContent = `Draw completed on ${drawnOn}`;
          // Skip animation and display winners directly
          displayWinners(winners);
      }

      // Mark winners as displayed
      winnersDisplayed = true;
  }

  function animateWinners(winners) {
      let currentWinnerIndex = 0;

      function animateNextWinner() {
          if (currentWinnerIndex < winners.length) {
              const winningNumber = winners[currentWinnerIndex].ticket;
              animateLotteryDraw(winningNumber, () => {
                  updateWinnersList(winners[currentWinnerIndex]);
                  currentWinnerIndex++;
                  animateNextWinner();
              });
          } else {
              // All winners have been animated
              removeAnimationAndButton();
          }
      }

      animateNextWinner();
  }

  function animateLotteryDraw(winningNumber, callback) {
      const lotteryResult = document.getElementById('lottery-result');
      lotteryResult.innerHTML = ''; // Clear previous content

      const digits = winningNumber.split('');
      const digitElements = [];

      digits.forEach((digit, index) => {
          const digitElement = document.createElement('span');
          digitElement.classList.add('digit');
          digitElement.textContent = Math.floor(Math.random() * 10); // Start with a random digit
          lotteryResult.appendChild(digitElement);
          digitElements.push(digitElement);

          // Animate each digit
          animateDigit(digitElement, digit, index, callback);
      });
  }

  function animateDigit(digitElement, targetDigit, index, callback) {
      const duration = 1000; // Duration of the animation in milliseconds
      const steps = 50; // Number of steps in the animation
      const delay = index * 500; // Delay each digit's animation for a cascading effect

      setTimeout(() => {
          let currentStep = 0;
          const interval = setInterval(() => {
              if (currentStep >= steps) {
                  digitElement.textContent = targetDigit; // Set the final digit
                  clearInterval(interval);

                  // Play win sound and trigger fireworks on the last digit
                  if (index === 8) { // Assuming 9 digits, adjust as needed
                      winSound.play();
                      triggerFireworks();

                      // Call the callback to proceed to the next winner
                      if (callback) callback();
                  }
              } else {
                  digitElement.textContent = Math.floor(Math.random() * 10); // Show a random digit
                  tickSound.play(); // Play tick sound for each step
                  currentStep++;
              }
          }, duration / steps);
      }, delay);
  }

  function updateWinnersList(winner) {
      const winnersList = document.getElementById('winners-list');
      const winnerDiv = document.createElement('div');
      winnerDiv.textContent = `Ticket: ${winner.ticket} => ${winner.amount}`;
      winnersList.appendChild(winnerDiv);
  }

  function displayWinners(winners) {
      const winnersList = document.getElementById('winners-list');
      winnersList.innerHTML = ''; // Clear previous winners
      winners.forEach(winner => {
          const winnerDiv = document.createElement('div');
          winnerDiv.textContent = `Ticket: ${winner.ticket} => ${winner.amount}`;
          winnersList.appendChild(winnerDiv);
      });
  }

  function removeAnimationAndButton() {
      // Remove the animation div and the button
      const lotteryResult = document.getElementById('lottery-result');
      const countdownElement = document.getElementById('countdown');

      if (lotteryResult) {
          lotteryResult.remove();
      }
      if (countdownElement) {
          countdownElement.remove();
      }
  }

  function triggerFireworks() {
      // Use the global confetti function from the canvas-confetti library
      confetti({
          particleCount: 150,
          spread: 180,
          origin: { y: 0.6 },
          colors: ['#ff0000', '#00ff00', '#0000ff'],
          shapes: ['circle', 'square'],
      });
  }

  // Start polling the backend
  startPolling();
}

function triggerFireworksTwo() {
  // Use the global confetti function from the canvas-confetti library
  confetti({
      particleCount: 150,
      spread: 180,
      origin: { y: 0.6 },
      colors: ['#ff0000', '#00ff00', '#FBFF00FF'],
      shapes: ['circle', 'square'],
  });
}

// Variables and constants
let user = null;
let tgWebApp = null;

let sectors = [];
const colorPalette = [
  '#FFBC03',
  '#FF5A10',
  '#FFC107',
  '#03A9F4',
  '#8BC34A',
  '#E91E63',
  '#9C27B0',
  '#00BCD4',
  '#FF5722',
  '#CDDC39',
  '#009688',
  '#795548',
];

const events = {
  listeners: {},
  addListener(eventName, fn) {
    this.listeners[eventName] = this.listeners[eventName] || [];
    this.listeners[eventName].push(fn);
  },
  fire(eventName, ...args) {
    if (this.listeners[eventName]) {
      for (let fn of this.listeners[eventName]) {
        fn(...args);
      }
    }
  },
};

const rand = (m, M) => Math.random() * (M - m) + m;
let tot;
const spinEl = document.getElementById('spin');
const ctx = document.getElementById('wheel').getContext('2d');
let dia = ctx.canvas.width;
let rad = dia / 2;
const PI = Math.PI;
const TAU = 2 * PI;
let arc;

const friction = 0.98; // Friction coefficient
let angVel = 0; // Angular velocity
let ang = 0; // Current angle in radians

let spinButtonClicked = false;
let outcomeSound = null; // Holds the outcome sound
let prevSector = null; // For playing the tick sound when entering a new sector

const getIndex = () => Math.floor(tot - (ang / TAU) * tot) % tot;

// Load the spinning tick sound via JavaScript
const tickSound = new Audio('tick.mp3'); // Ensure the file path is correct

// DOM elements
const betAmountInput = document.getElementById('betAmount');
const withdrawBtn = document.getElementById('withdrawBtn');
const withdrawAmountInput = document.getElementById('withdrawAmount');
const buyAmountInput = document.getElementById('buyAmount');
const transferAmountInput = document.getElementById('transferAmount');
const uniqueNameInput = document.getElementById('uniqueName');
