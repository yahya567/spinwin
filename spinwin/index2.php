<!doctype html>
<html>
  <head>
    <title>SpinWin</title>
    <meta charset="UTF-8" />
    <!-- Meta viewport tag for mobile devices -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <!-- Include Bootstrap CSS -->
    <link
      rel="stylesheet"
      href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"
    />
    <!-- Custom Styles -->
    <link rel="stylesheet" href="src/styles.css" />
    <!-- Google Fonts -->
    <link
      href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap"
      rel="stylesheet"
    >
    <!-- Font Awesome -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
    />

    <!-- Include TON Connect SDK -->
    <!-- <script src="https://cdn.jsdelivr.net/npm/@tonconnect/sdk@latest/dist/tonconnect-sdk.min.js"></script> -->
    <!-- Include TON Connect UI SDK -->
    <script src="https://unpkg.com/@tonconnect/ui@latest/dist/tonconnect-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tonweb@latest/dist/tonweb.js"></script>
    <!-- favicon -->
    <link rel="icon" href="src/icon.png" type="image/x-icon" />
  </head>

  <body>
    <!-- Header -->
    <!-- Navigation Tabs -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
      <a class="navbar-brand" href="#">SpinWin</a>
      <!-- Hamburger Menu Button -->
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarTabs" aria-controls="navbarTabs" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <!-- Collapsible Menu -->
      <div class="collapse navbar-collapse" id="navbarTabs">
        <ul class="navbar-nav mr-auto">
          <li class="nav-item">
            <a class="nav-link active" href="#" onclick="showTab('#nav-home', this)">Play Spin</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" onclick="showTab('#nav-lotto', this)">Lucky Lotto</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" onclick="showTab('#nav-my-lotto', this)">My Lottos</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" onclick="showTab('#nav-lotto-winners', this)">Lotto Winners</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" onclick="showTab('#nav-buy', this)">Purchase Coin</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" onclick="showTab('#nav-withdraw', this)">Withdraw Winnings</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" onclick="showTab('#nav-transfer', this)">Transfer Coin</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" onclick="showTab('#nav-orders', this)">Your Orders</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" onclick="showTab('#nav-trxs', this)">Your Transactions</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" onclick="showTab('#nav-referrals', this)">Your Referrals</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" onclick="showTab('#nav-live-draw', this)">LIVE LOTTO DRAW</a>
          </li>
        </ul>
      </div>
    </nav>
    <br>
    <br>
    <br>

    <script>
    // capture a click event on anywhere on the page
    document.addEventListener('click', function(event) {
      // if a content with id of navbarTabs has a class of show, remove the 'show' and leave other classes as it is
      if (document.getElementById('navbarTabs').classList.contains('show')) {
        // document.getElementById('navbarTabs').classList.remove('show');
        // initate a click on a button with class 'navbar-toggler'
        document.querySelector('.navbar-toggler').click();
      }
    });
    </script>

    <script>
      function showTab(tabId, element) {
        $('.nav-link').removeClass('active');
        $(element).addClass('active');
        $('.tab-pane').removeClass('show active');
        $(tabId).addClass('show active');
      }
    </script>

    <!-- Main Content -->
    <div class="container mt-4">

      <div class="" id="connect_button">
        <div id="ton-connect"></div>
      </div>
      <br>

      <!-- Welcome Message -->
      <div class="alert alert-warning mt-2 d-flex justify-content-between align-items-center" role="alert">
        <div>
          Welcome, <strong><span id="userName"></span></strong><br>
          Balance: <strong><span id="userBalance">0</span></strong> SpinCoins<br>
          Winnings: <strong><span id="userWinning">0</span></strong> SpinCoins
        </div>
      </div>

      <!-- Alert Messages -->
      <div id="alertContainer"></div>
      <!-- a card with three tabs -->
      <!-- <nav>
        <div class="nav nav-tabs text-white flex-nowrap overflow-auto" id="nav-tab" role="tablist">
          <a class="nav-link active" id="nav-home-tab" data-toggle="tab" href="#nav-home" role="tab" aria-controls="nav-home" aria-selected="true">Play</a>
          <a class="nav-link" id="nav-lotto-tab" data-toggle="tab" href="#nav-lotto" role="tab" aria-controls="nav-lotto" aria-selected="true">Lucky Lotto</a>
          <a class="nav-link" id="nav-my-lotto-tab" data-toggle="tab" href="#nav-my-lotto" role="tab" aria-controls="nav-my-lotto" aria-selected="true">My Lottos</a>
          <a class="nav-link" id="nav-buy-tab" data-toggle="tab" href="#nav-buy" role="tab" aria-controls="nav-buy" aria-selected="false">Purchase</a>
          <a class="nav-link" id="nav-withdraw-tab" data-toggle="tab" href="#nav-withdraw" role="tab" aria-controls="nav-withdraw" aria-selected="false">Withdraw</a>
          <a class="nav-link" id="nav-transfer-tab" data-toggle="tab" href="#nav-transfer" role="tab" aria-controls="nav-transfer" aria-selected="false">Transfer</a>
          <a class="nav-link" id="nav-orders-tab" data-toggle="tab" href="#nav-orders" role="tab" aria-controls="nav-orders" aria-selected="false">Orders</a>
          <a class="nav-link" id="nav-trxs-tab" data-toggle="tab" href="#nav-trxs" role="tab" aria-controls="nav-trxs" aria-selected="false">Trxs</a>
          <a class="nav-link" id="nav-referrals-tab" data-toggle="tab" href="#nav-referrals" role="tab" aria-controls="nav-referrals" aria-selected="false">Referrals</a>
          <a class="nav-link" id="nav-top-performers-tab" data-toggle="tab" href="#nav-top-performers" role="tab" aria-controls="nav-top-performers" aria-selected="false">Toppers</a>
        </div>
      </nav> -->
      <div class="tab-content" id="nav-tabContent">
        <div class="tab-pane fade show active" id="nav-home" role="tabpanel" aria-labelledby="nav-home-tab">
          
          <!-- Betting Input and Wheel -->
          <div class="card withdraw-card mt-3">
            <div class="card-body text-center">
              <h5 class="card-title">Spin and win big!</h5>
              <p class="card-text">2500 minimum bet!</p>
              <input
                type="number"
                id="betAmount"
                class="form-control"
                placeholder="Enter SpinCoins to bet"
                min="2500"
              />

              <!-- Spin the Wheel Section -->
              <div id="spin_the_wheel" class="text-center mt-3">
                <canvas id="wheel" width="300" height="300"></canvas>
                <button id="spin">SPIN</button>
              </div>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="nav-lotto" role="tabpanel" aria-labelledby="nav-lotto-tab">
          
          <!-- Withdraw Section -->
          <div class="card withdraw-card mt-3">
            <div class="card-body text-center">
              <h5 class="card-title">Lucky Lotto</h5>
              <div id="lotto-details"></div>
              <p class="card-text">Buy lucky lotto</p>
                <ul class="list-group" id="lotteriesList">
                  <!-- Lottories will be loaded here -->
                </ul>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="nav-my-lotto" role="tabpanel" aria-labelledby="nav-my-lotto-tab">
          
          <!-- Withdraw Section -->
          <div class="card withdraw-card mt-3">
            <div class="card-body text-center">
              <h5 class="card-title">Your Lotto Numbers</h5>
              <div id="your-lotto-details"></div>
                <p class="card-text">Your tickets</p>
                <ul class="list-group" id="userTicketsList">
                  <!-- User tickets will be loaded here -->
                </ul>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="nav-buy" role="tabpanel" aria-labelledby="nav-buy-tab">
          <!-- Buy Section -->
          <div id="plansList">
          </div>
          <div class="card buy-card mt-3">
            <div class="card-body text-center">
              <h5 class="card-title">Buy SpinCoins</h5>
              <p class="card-text">Top up your chips</p>
                <ul class="list-group" id="coinPricesList">
                  <!-- Coin prices will be loaded here -->
                </ul>
            </div>
          </div>

          <!-- Buy custom -->
          <div class="card buy-card mt-3">
            <div class="card-body text-center">
              <h5 class="card-title">Buy SpinCoins</h5>
              <p class="card-text">Buy SpinCoins with TON (0.5 TON minimum order)</p>
              <p class="card-text"><span id="custom_coin"></span></p>
              <input
                type="number"
                id="buyAmount"
                name="buyAmount"
                class="form-control"
                placeholder="Enter TON amount to buy"
                min="0.5"
              />
              <button id="buyBtn" class="btn btn-dark btn-lg mt-3">Buy</button>
            </div>
          </div>
        </div>
        <div class="tab-pane fade" id="nav-withdraw" role="tabpanel" aria-labelledby="nav-withdraw-tab">
          <!-- Withdraw Section -->
          <div class="card withdraw-card mt-3">
            <div class="card-body text-center">
              <h5 class="card-title">Withdraw SpinCoins</h5>
              <p class="card-text"><span id="transfer_nb">Only <strong>Winnings</strong> SpinCoins can be withdrawn!</span></p>
              <p class="card-text"><span id="spin_to_ton">Withdraw your SpinCoins to your TON wallet.</span></p>
              <input
                type="number"
                id="withdrawAmount"
                class="form-control"
                placeholder="Enter amount to withdraw"
                min="74000"
              />
              <button id="withdrawBtn" class="btn btn-dark btn-lg mt-3">Withdraw</button>
              <p class="card-text">Check your withdrawals</p>
                <ul class="list-group" id="withdrawalsList">
                  <!-- Withdrawals will be loaded here -->
                </ul>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="nav-transfer" role="tabpanel" aria-labelledby="nav-transfer-tab">
          <!-- Transfer Section -->
          <div class="card withdraw-card mt-3">
            <div class="card-body text-center">
              <h5 class="card-title">Trasfer SpinCoins</h5>
              <p class="card-text"><span id="account_name">Transfer SpinCoins to your friends or families.</span></p>
              <input
                type="text"
                id="uniqueName"
                class="form-control"
                placeholder="Enter a unique name of the receiver"
              />
              <br>
              <input
                type="number"
                id="transferAmount"
                class="form-control"
                placeholder="Enter amount to transfer"
                min="74000"
              />
              <button id="transferBtn" class="btn btn-dark btn-lg mt-3">Authorize Transfer</button>
              <p class="card-text">Check your transfers</p>
                <ul class="list-group" id="transfersList">
                  <!-- Transfers will be loaded here -->
                   <p>No transfers history yet</p>
                </ul>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="nav-orders" role="tabpanel" aria-labelledby="nav-orders-tab">
          <div class="card buy-card mt-3">
            <div class="card-body text-center">
              <h5 class="card-title">Orders</h5>
              <p class="card-text">Check your oders</p>
                <ul class="list-group" id="ordersList">
                  <!-- Orders will be loaded here -->
                  <p>No orders history yet</p>
                </ul>
            </div>
          </div>
        </div>
        <div class="tab-pane fade" id="nav-trxs" role="tabpanel" aria-labelledby="nav-trxs-tab">
          <div class="card buy-card mt-3">
            <div class="card-body text-center">
              <h5 class="card-title">Transactions</h5>
              <p class="card-text">Check your transactions</p>
                <ul class="list-group" id="transactionsList">
                  <!-- Transactions will be loaded here -->
                  <p>No transactions history yet</p>
                </ul>
            </div>
          </div>
        </div>
        <div class="tab-pane fade" id="nav-referrals" role="tabpanel" aria-labelledby="nav-referrals-tab">
          <div class="card buy-card mt-3">
            <div class="card-body text-center">
              <h5 class="card-title">Referrals</h5>              
              <p class="card-text">Invite your friends and earn 10% of their purchases!</p>
              <p class="card-text">5000 free SpinCoins for anyone using your referral link!</p>
              <div class="input-group-append">
                <input type="text" class="form-control" id="referralLink" value="Your referral link" disabled>
                <button class="btn btn-outline-secondary text-white" type="button" onclick="copyReferralLink()">
                  <i class="fas fa-clipboard"></i>
                </button>
              </div>
                
              <script>
                function copyReferralLink() {
                  var copyText = document.getElementById("referralLink");
                  copyText.disabled = false;
                  copyText.select();
                  copyText.setSelectionRange(0, 99999); // For mobile devices
                  document.execCommand("copy");
                  copyText.disabled = true;
                  alert("Copied the text: " + copyText.value);
                }

                function copyInvitationCode() {
                  var copyText = document.getElementById("invitation-code");
                  copyText.disabled = false;
                  copyText.select();
                  copyText.setSelectionRange(0, 99999); // For mobile devices
                  document.execCommand("copy");
                  copyText.disabled = true;
                  alert("Copied the text: " + copyText.value);
                }
              </script>
              <p class="card-text">Your recent referrals</p>
                <ul class="list-group" id="referralsList">
                  <!-- Referrals will be loaded here -->
                  <p>No referrals yet</p>
                </ul>
            </div>
          </div>
        </div>
        <div class="tab-pane fade" id="nav-lotto-winners" role="tabpanel" aria-labelledby="nav-lotto-winners-tab">
          <div class="card buy-card mt-3">
            <div class="card-body text-center">
              <h5 class="card-title">Recent Lotto Winners</h5>   
                <div id="winner-details"></div>
                <ul class="list-group" id="winnersList">
                  <!-- Winners will be loaded here -->
                  <p>No winners yet</p>
                </ul>
            </div>
          </div>
        </div>
        <div class="tab-pane fade" id="nav-live-draw" role="tabpanel" aria-labelledby="nav-live-draw-tab">
          <div class="card buy-card mt-3">
            <div class="card-body text-center">
              <h5 class="card-title">Live Lotto Draw</h5>

              <div id="lottery-result"></div>
              <div id="countdown"></div>
              <div class="alert alert-success alert-dismissible fade show mt-2" role="alert" id="winners-list"></div>
              <canvas id="confetti-canvas"></canvas>
              <script src="https://cdnjs.cloudflare.com/ajax/libs/howler/2.2.3/howler.min.js"></script>
              <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>


                <ul class="list-group" id="liveDraw">
                  <!-- Live draw will be loaded here -->
                  <!-- <p>No live draw today</p> -->
                </ul>
            </div>
          </div>
        </div>
        <div class="tab-pane fade" id="nav-top-performers" role="tabpanel" aria-labelledby="nav-top-performers-tab">
          <div class="card buy-card mt-3">
            <div class="card-body text-center">
              <h5 class="card-title">Top Performers</h5>
              <p class="card-text">Check top performers</p>
                <ul class="list-group" id="leaderboardList">
                  <!-- Top performers will be loaded here -->
                </ul>
            </div>
          </div>
        </div>
      </div>
      <!-- End of card with three tabs -->      
    </div>

    <!-- give me a modal here with dynamic content body to be populated by javascript -->
    <div class="modal fade text-dark" id="modal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalLabel">Alert</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body" id="modalBody">
            <!-- Modal body will be loaded here -->
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          </div>
        </div>
      </div>

    <!-- Include Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script
      src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"
    ></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- Telegram Web Apps Script -->
    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    <!-- Your Custom Script -->
    <script defer src="src/index.js?v=1.0.5"></script>
  </body>
</html>
