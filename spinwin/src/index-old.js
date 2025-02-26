// index.js

// Initialize TON Connect UI SDK
const tonConnectUI = new TON_CONNECT_UI.TonConnectUI({
  manifestUrl: 'https://footage-offering-cant-fellowship.trycloudflare.com/sites/spin2/tonconnect-manifest.json',
  buttonRootId: 'ton-connect'
});

let walletAddress = null;
let walletConnected = false;
let userBalance = 0;

tonConnectUI.onStatusChange((walletInfo) => {
if (walletInfo) {
// get all wallet info
console.log(walletInfo);

walletAddress = walletInfo.account.address;
walletConnected = true;
// fetchUserBalance(); // Fetch the balance once connected
} else {
walletAddress = null;
walletConnected = false;
// updateBalance(0);
}
});


document.addEventListener('DOMContentLoaded', () => {
// Check if the app is accessed from Telegram
if (
typeof window.Telegram === 'undefined' ||
typeof window.Telegram.WebApp === 'undefined'
) {
// Not in Telegram WebApp
document.body.innerHTML =
  '<div class="container"><div class="alert alert-danger mt-5" role="alert">This application can only be accessed from Telegram.</div></div>';
} else {
// Proceed with initialization
init();
}
});

function init() {
initTelegram();
adjustCanvasSize();

fetchUserBalance();

// Fetch leaderboard data
fetchLeaderboard();

// Initialize event listeners
spinEl.addEventListener('click', spinWheel);
events.addListener('spinEnd', (sector) => {
showAlert(`Congratulations! You won ${sector.label}`, 'success', 'alertContainer');
spinEl.disabled = false; // Re-enable the spin button
});

withdrawBtn.addEventListener('click', withdrawSpinCoins);

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

// Initialize Telegram Web Apps and fetch user info
function initTelegram() {
const tg = window.Telegram.WebApp;
tg.expand(); // Expand the web app to full height

const telegramUserId = tg.initDataUnsafe?.user?.id || null;
const username = tg.initDataUnsafe?.user?.username || 'unknown';
const firstName = tg.initDataUnsafe?.user?.first_name || ''; // give it random verb if empty
const lastName = tg.initDataUnsafe?.user?.last_name || ''; // give it random noun if empty

user = tg.initDataUnsafe.user;
if (user) {
document.getElementById('userName').textContent = user.first_name;
} else {
document.getElementById('userName').textContent = 'Guest';
}
}

// Show Bootstrap alerts
function showAlert(message, type = 'warning', containerId = 'alertContainer') {
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

// Handle buying SpinCoins
async function buySpinCoins() {
if (!walletConnected || !walletAddress) {
showAlert('Please connect your wallet first.', 'warning');
return;
}

const purchaseAmountInput = document.getElementById('purchaseAmount');
const purchaseAmount = parseFloat(purchaseAmountInput.value);

if (purchaseAmount && !isNaN(purchaseAmount) && purchaseAmount > 0) {
try {
  // Prepare the transaction parameters
  const tx = {
    validUntil: Date.now() + 5 * 60 * 1000, // Transaction valid for 5 minutes
    messages: [
      {
        address: 'YOUR_CONTRACT_ADDRESS', // Replace with your smart contract address
        amount: (purchaseAmount * 1e9).toString(), // Convert TON to nanotons
        payload: '', // Optional: Add payload if needed
      },
    ],
  };

  // Send the transaction request to the wallet
  await tonConnectUI.sendTransaction(tx);

  // Handle the transaction result
  showAlert('Purchase transaction sent. Waiting for confirmation...', 'info');

  // TODO: Verify the transaction on the backend and update the user's balance

  // For now, simulate balance update
  // You should replace this with actual backend verification
  updateBalance(userBalance + purchaseAmount * 100); // Example conversion rate

  showAlert(`Successfully purchased SpinCoins!`, 'success');

  // Hide the input field after purchase
  const buyCoinsContainer = document.getElementById('buyCoinsContainer');
  buyCoinsContainer.innerHTML = '';
} catch (error) {
  console.error('Error sending transaction:', error);
  showAlert('Error processing transaction. Please try again.', 'danger');
}
} else {
showAlert('Invalid amount entered.', 'danger');
}
}

// Handle withdrawing SpinCoins
async function withdrawSpinCoins() {
if (!walletConnected || !walletAddress) {
showAlert('Please connect your wallet first.', 'warning', 'alertContainer');
return;
}

const withdrawAmount = parseInt(withdrawAmountInput.value);
if (!withdrawAmount || withdrawAmount <= 0) {
showAlert('Please enter a valid amount to withdraw.', 'warning', 'alertContainer');
return;
}

if (withdrawAmount > userBalance) {
showAlert('Insufficient balance to withdraw.', 'danger', 'alertContainer');
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

  showAlert('Withdrawal transaction sent. Waiting for confirmation...', 'info', 'alertContainer');

  // Update the user's balance after confirmation
  updateBalance(data.newBalance);
  showAlert('Withdrawal successful!', 'success', 'alertContainer');
  withdrawAmountInput.value = '';
} else {
  showAlert('Withdrawal failed. Please try again.', 'danger', 'alertContainer');
}
} catch (error) {
console.error('Error processing withdrawal:', error);
showAlert('Error processing withdrawal. Please try again.', 'danger', 'alertContainer');
}
}

// Fetch user's balance from the backend
async function fetchUserBalance() {
// const user_id get from telegram
const user_id = user.id;

if (!user_id) return; // Ensure user is available
try {
const response = await fetch('get_balance.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ user_id }),
});
const data = await response.json();
userBalance = data.balance;
updateBalance(userBalance);
} catch (error) {
console.error('Error fetching user balance:', error);
userBalance = 0;
updateBalance(userBalance);
}
}

// Fetch leaderboard data from the backend
async function fetchLeaderboard() {
try {
const response = await fetch('get_leaderboard.php');
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

// Fetch sectors from the backend
async function fetchSectors() {
try {
const response = await fetch('get_sectors.php');
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
try {
const response = await fetch('get_outcome.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ walletAddress, betAmount }),
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
showAlert('Please enter a valid bet amount.', 'warning', 'alertContainer');
return;
}

// Check if the user has sufficient balance
const response = await fetch('check_balance.php', {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ walletAddress, betAmount }),
});
const balanceData = await response.json();
if (!balanceData.hasEnoughBalance) {
showAlert('Insufficient balance. Please buy more SpinCoins.', 'danger', 'alertContainer');
return;
}

spinEl.disabled = true; // Disable the spin button

const outcomeData = await fetchOutcome(betAmount);
if (!outcomeData) {
showAlert('Error determining outcome.', 'danger', 'alertContainer');
spinEl.disabled = false;
return;
}

const desiredOutcome = outcomeData.label;
const outcomeSoundFile = outcomeData.sound;

const desiredIndex = sectors.findIndex((sector) => sector.label === desiredOutcome);
if (desiredIndex === -1) {
showAlert('Outcome not found on the wheel.', 'danger', 'alertContainer');
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

spinButtonClicked = true;

// Clear the bet input
betAmountInput.value = '';
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
