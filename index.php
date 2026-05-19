<?php
session_start();
$db = new SQLite3('venomx.db');

$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE,
    name TEXT,
    password TEXT,
    wallet REAL DEFAULT 0,
    banned INTEGER DEFAULT 0,
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
$db->exec("CREATE TABLE IF NOT EXISTS fund_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    amount REAL,
    utr TEXT,
    status TEXT DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    message TEXT,
    seen INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

define('BOT_TOKEN', '8603431466:AAGfzdIoWE7jEmziVEMhA5oNs2dEuhD1Az0');
define('OWNER_ID', '8586849798');

function botRequest($method, $data) {
    @file_get_contents("https://api.telegram.org/bot".BOT_TOKEN."/".$method."?".http_build_query($data));
}

// Bot Commands via URL
if(isset($_GET['bot_cmd'])) {
    $cmd = $_GET['bot_cmd'];
    $parts = explode(' ', $cmd);
    $action = $parts[0];
    if($action == 'addfund' && isset($parts[1], $parts[2])) {
        $user_id = intval($parts[1]);
        $amount = floatval($parts[2]);
        $db->exec("UPDATE users SET wallet = wallet + $amount WHERE id = $user_id");
        botRequest("sendMessage", ['chat_id' => OWNER_ID, 'text' => "✅ Added ₹$amount to User ID $user_id"]);
        echo "Done";
    }
    elseif($action == 'ban' && isset($parts[1])) {
        $db->exec("UPDATE users SET banned = 1 WHERE id = " . intval($parts[1]));
        botRequest("sendMessage", ['chat_id' => OWNER_ID, 'text' => "🚫 User ID {$parts[1]} banned"]);
        echo "Banned";
    }
    elseif($action == 'unban' && isset($parts[1])) {
        $db->exec("UPDATE users SET banned = 0 WHERE id = " . intval($parts[1]));
        botRequest("sendMessage", ['chat_id' => OWNER_ID, 'text' => "✅ User ID {$parts[1]} unbanned"]);
        echo "Unbanned";
    }
    elseif($action == 'broadcast') {
        $msg = implode(' ', array_slice($parts, 1));
        $users = $db->query("SELECT id FROM users");
        while($u = $users->fetchArray()) {
            $db->exec("INSERT INTO notifications (user_id, message) VALUES ({$u['id']}, '$msg')");
        }
        botRequest("sendMessage", ['chat_id' => OWNER_ID, 'text' => "📢 Broadcast sent: $msg"]);
        echo "Broadcasted";
    }
    exit;
}

// Signup
if(isset($_POST['signup'])) {
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
            'text' => "🆕 NEW SIGNUP!\n🆔 ID: $user_id\n📧 $email\n👤 $name"
        ]);
    }
    header("Location: index.php");
    exit;
}

// Login
if(isset($_POST['login'])) {
    $email = $_POST['email'];
    $pass = $_POST['password'];
    $user = $db->querySingle("SELECT * FROM users WHERE email='$email'", true);
    if($user && password_verify($pass, $user['password'])) {
        if($user['banned'] == 1) {
            $_SESSION['banned'] = true;
        } else {
            $_SESSION['user_id'] = $user['id'];
        }
    }
    header("Location: index.php");
    exit;
}

// Add Funds Request
if(isset($_POST['add_funds_req'])) {
    $amount = floatval($_POST['amount']);
    $utr = $_POST['utr'];
    $uid = $_SESSION['user_id'];
    if($amount >= 100) {
        $db->exec("INSERT INTO fund_requests (user_id, amount, utr) VALUES ($uid, $amount, '$utr')");
        $user = $db->querySingle("SELECT * FROM users WHERE id=$uid", true);
        botRequest("sendMessage", [
            'chat_id' => OWNER_ID,
            'text' => "💰 PAYMENT REQUEST\n👤 User: {$user['name']}\n🆔 ID: $uid\n📧 {$user['email']}\n₹$amount\n🔑 UTR: $utr"
        ]);
        $msg = "✅ Request sent. Wait for approval.";
    } else {
        $msg = "Minimum ₹100";
    }
    header("Location: index.php?msg=".urlencode($msg));
    exit;
}

// Logout
if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

$user = null;
$banned = false;
if(isset($_SESSION['user_id'])) {
    $user = $db->querySingle("SELECT * FROM users WHERE id=".$_SESSION['user_id'], true);
    if($user && $user['banned'] == 1) $banned = true;
}

$msg = isset($_GET['msg']) ? $_GET['msg'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VENOM X | Free Fire Top Up</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0a0a0a; font-family: 'Segoe UI', sans-serif; color: #fff; }
        .container { min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; background: linear-gradient(135deg, #0a0a0a, #1a0033); }
        .card { background: #111; border-radius: 20px; padding: 40px; width: 100%; max-width: 450px; text-align: center; border: 1px solid #9b59b6; box-shadow: 0 0 20px rgba(155,89,182,0.3); }
        .logo { font-size: 2.5rem; font-weight: bold; background: linear-gradient(90deg, #9b59b6, #ff00cc); -webkit-background-clip: text; background-clip: text; color: transparent; margin-bottom: 20px; }
        input { width: 100%; padding: 12px; margin: 10px 0; background: #1a1a1a; border: 1px solid #333; border-radius: 8px; color: #fff; }
        button { width: 100%; padding: 12px; background: #9b59b6; color: #fff; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .switch { margin-top: 15px; color: #aaa; cursor: pointer; }
        .switch span { color: #9b59b6; }
        hr { margin: 20px 0; border-color: #333; }
        .dashboard { min-height: 100vh; background: linear-gradient(135deg, #0a0a0a, #1a0033); padding: 20px; }
        .navbar { display: flex; justify-content: space-between; align-items: center; background: #111; padding: 15px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #9b59b6; }
        .wallet-card { background: #1a0033; padding: 20px; border-radius: 12px; margin-bottom: 20px; text-align: center; border: 1px solid #9b59b6; }
        .wallet-amount { font-size: 2rem; color: #9b59b6; }
        .menu-icon { font-size: 24px; cursor: pointer; }
        .sidebar { position: fixed; top: 0; left: -280px; width: 280px; height: 100%; background: #111; border-right: 1px solid #9b59b6; transition: 0.3s; padding: 20px; z-index: 1000; }
        .sidebar.open { left: 0; }
        .close-sidebar { text-align: right; font-size: 24px; cursor: pointer; color: #9b59b6; }
        .menu-item { padding: 12px; margin: 8px 0; cursor: pointer; border-radius: 8px; text-align: center; background: #1a0033; }
        .menu-item:hover { background: #9b59b6; }
        .popup { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); display: none; justify-content: center; align-items: center; z-index: 2000; }
        .popup-card { background: #111; border-radius: 20px; padding: 30px; width: 90%; max-width: 400px; border: 1px solid #9b59b6; text-align: center; }
        .bonus-btns { display: flex; flex-wrap: wrap; gap: 10px; margin: 15px 0; }
        .bonus-btn { flex: 1; background: #1a0033; border: 1px solid #9b59b6; padding: 10px; }
        .whatsapp-link { display: inline-block; background: #25D366; color: #fff; padding: 8px 15px; border-radius: 8px; text-decoration: none; margin-top: 10px; }
        .order-item, .notif-item { background: #1a1a1a; padding: 10px; margin: 8px 0; border-radius: 8px; border-left: 3px solid #9b59b6; text-align: left; }
    </style>
</head>
<body>

<?php if($banned): ?>
<div class="container">
    <div class="card">
        <div class="logo">VENOM X</div>
        <h2 style="color:#ff4444">🚫 You Are Banned</h2>
        <p>Contact support:</p>
        <a href="https://wa.me/9485813638" class="whatsapp-link">📞 WhatsApp Support</a>
        <a href="https://t.me/venomx_here" class="whatsapp-link" style="background:#0088cc; margin-left:10px;">✈️ Telegram</a>
    </div>
</div>
<?php elseif(!isset($_SESSION['user_id'])): ?>
<div class="container">
    <div class="card">
        <div class="logo">VENOM X</div>
        <p style="margin-bottom:20px">India's #1 Free Fire Top Up</p>
        
        <form method="post">
            <input type="email" name="email" placeholder="Email Address" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">Login</button>
        </form>
        
        <div class="switch" onclick="showSignup()">New here? <span>Create account</span></div>
        
        <div id="signupForm" style="display:none; margin-top:20px">
            <form method="post">
                <input type="text" name="name" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Email Address" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="signup">Create Account</button>
            </form>
        </div>
    </div>
</div>
<script> function showSignup() { document.getElementById('signupForm').style.display = 'block'; } </script>

<?php else: ?>
<!-- DASHBOARD -->
<div class="dashboard">
    <div class="navbar">
        <div class="menu-icon" onclick="toggleSidebar()">☰</div>
        <div class="logo" style="font-size:1.5rem">VENOM X</div>
        <div>ID: <?php echo $user['id']; ?></div>
    </div>

    <div class="sidebar" id="sidebar">
        <div class="close-sidebar" onclick="toggleSidebar()">✕</div>
        <div style="text-align:center;margin:20px 0">
            <div style="background:#9b59b6; width:80px; height:80px; border-radius:50%; margin:0 auto; display:flex; align-items:center; justify-content:center; font-size:40px;">👤</div>
            <p><strong><?php echo htmlspecialchars($user['name']); ?></strong></p>
            <p style="font-size:12px"><?php echo $user['email']; ?></p>
        </div>
        <div class="menu-item" onclick="showSection('orders')">📜 Order History</div>
        <div class="menu-item" onclick="showSection('notifications')">🔔 Notifications</div>
        <div class="menu-item" onclick="showAddFunds()">💰 Add Funds</div>
        <div class="menu-item" onclick="showSection('funds_history')">📋 Fund Requests</div>
        <a href="https://wa.me/9485813638" class="menu-item" style="display:block; text-decoration:none;">📞 WhatsApp Support</a>
        <div class="menu-item" onclick="window.location.href='?logout=1'">🚪 Logout</div>
    </div>

    <div class="wallet-card">
        <p>💰 Wallet Balance</p>
        <div class="wallet-amount">₹<?php echo number_format($user['wallet'], 2); ?></div>
        <button onclick="showAddFunds()" style="margin-top:15px; width:auto; padding:8px 25px;">+ Add Funds</button>
    </div>

    <?php if($msg): ?>
        <div style="background:#1a0033; padding:10px; border-radius:8px; margin-bottom:20px; text-align:center; border:1px solid #9b59b6;"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <!-- Orders Section -->
    <div id="ordersSection" style="display:none;">
        <div class="wallet-card">
            <h3>📜 Order History</h3>
            <?php
            $orders = $db->query("SELECT * FROM orders WHERE user_id=".$user['id']." ORDER BY id DESC");
            $has_orders = false;
            while($o = $orders->fetchArray()) {
                $has_orders = true;
                echo "<div class='order-item'>🎮 {$o['plan']}<br>💰 ₹{$o['amount']}<br>⏱ {$o['created_at']}<br>📌 Status: {$o['status']}</div>";
            }
            if(!$has_orders) echo "<p style='text-align:center'>No orders yet</p>";
            ?>
        </div>
    </div>

    <!-- Notifications Section -->
    <div id="notificationsSection" style="display:none;">
        <div class="wallet-card">
            <h3>🔔 Notifications</h3>
            <?php
            $notif = $db->query("SELECT * FROM notifications WHERE user_id=".$user['id']." ORDER BY id DESC");
            $has_notif = false;
            while($n = $notif->fetchArray()) {
                $has_notif = true;
                echo "<div class='notif-item'>📢 {$n['message']}<br><small>{$n['created_at']}</small></div>";
            }
            if(!$has_notif) echo "<p style='text-align:center'>No notifications</p>";
            ?>
        </div>
    </div>

    <!-- Fund Requests History -->
    <div id="fundsHistorySection" style="display:none;">
        <div class="wallet-card">
            <h3>📋 Your Fund Requests</h3>
            <?php
            $funds = $db->query("SELECT * FROM fund_requests WHERE user_id=".$user['id']." ORDER BY id DESC");
            $has_funds = false;
            while($f = $funds->fetchArray()) {
                $has_funds = true;
                $status_style = $f['status'] == 'approved' ? 'color:#00ff00' : ($f['status'] == 'pending' ? 'color:#ffaa00' : 'color:#ff4444');
                echo "<div class='order-item'>💰 ₹{$f['amount']}<br>🔑 UTR: {$f['utr']}<br>📌 Status: <span style='$status_style'>{$f['status']}</span><br>⏱ {$f['created_at']}</div>";
            }
            if(!$has_funds) echo "<p style='text-align:center'>No fund requests</p>";
            ?>
        </div>
    </div>

    <!-- Default Dashboard -->
    <div id="dashboardDefault">
        <div class="wallet-card">
            <h3>💎 Membership Plans (Coming Soon)</h3>
            <p style="color:#aaa">Use Add Funds to recharge your wallet. After approval, you can buy memberships.</p>
        </div>
    </div>
</div>

<!-- Add Funds Popup -->
<div id="fundsPopup" class="popup">
    <div class="popup-card">
        <h3 style="color:#9b59b6">💰 Add Funds</h3>
        <p style="font-size:12px; color:#aaa;">Minimum ₹100</p>
        <div class="bonus-btns">
            <button class="bonus-btn" onclick="setAmount(100)">₹100</button>
            <button class="bonus-btn" onclick="setAmount(200)">₹200</button>
            <button class="bonus-btn" onclick="setAmount(500)">₹500</button>
            <button class="bonus-btn" onclick="setAmount(1000)">₹1000</button>
        </div>
        <input type="number" id="customAmount" placeholder="Custom amount (Min ₹100)">
        <button onclick="generateQR()">Proceed to Pay</button>
        <button onclick="closePopup('fundsPopup')">Cancel</button>
    </div>
</div>

<!-- QR Popup -->
<div id="qrPopup" class="popup">
    <div class="popup-card">
        <h3 style="color:#9b59b6">Scan & Pay</h3>
        <img id="qrImage" src="" width="180" style="margin:15px auto; background:#fff; padding:10px; border-radius:12px;">
        <p id="qrAmount" style="font-size:18px; font-weight:bold;"></p>
        <p style="font-size:11px; color:#aaa;">UPI: vanshx44@airtel</p>
        <input type="text" id="utrInput" placeholder="Enter UTR / Transaction ID">
        <button onclick="submitFundRequest()">Submit</button>
        <button onclick="closePopup('qrPopup')">Cancel</button>
    </div>
</div>

<script>
let selectedAmount = 0;

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}
function showSection(section) {
    document.getElementById('dashboardDefault').style.display = 'none';
    document.getElementById('ordersSection').style.display = 'none';
    document.getElementById('notificationsSection').style.display = 'none';
    document.getElementById('fundsHistorySection').style.display = 'none';
    if(section === 'orders') document.getElementById('ordersSection').style.display = 'block';
    else if(section === 'notifications') document.getElementById('notificationsSection').style.display = 'block';
    else if(section === 'funds_history') document.getElementById('fundsHistorySection').style.display = 'block';
    else document.getElementById('dashboardDefault').style.display = 'block';
    if(window.innerWidth < 768) toggleSidebar();
}
function showAddFunds() {
    document.getElementById('fundsPopup').style.display = 'flex';
}
function setAmount(amt) {
    document.getElementById('customAmount').value = amt;
}
function generateQR() {
    let amt = parseFloat(document.getElementById('customAmount').value);
    if(!amt || amt < 100) { alert('Minimum ₹100'); return; }
    selectedAmount = amt;
    document.getElementById('fundsPopup').style.display = 'none';
    let qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=upi://pay?pa=vanshx44@airtel&pn=VENOMX&am=${selectedAmount}&cu=INR`;
    document.getElementById('qrImage').src = qrUrl;
    document.getElementById('qrAmount').innerHTML = `₹${selectedAmount}`;
    document.getElementById('qrPopup').style.display = 'flex';
}
function submitFundRequest() {
    let utr = document.getElementById('utrInput').value;
    if(!utr) { alert('Enter UTR Number'); return; }
    let form = document.createElement('form');
    form.method = 'POST';
    let amtInput = document.createElement('input'); amtInput.type = 'hidden'; amtInput.name = 'amount'; amtInput.value = selectedAmount;
    let utrInput = document.createElement('input'); utrInput.type = 'hidden'; utrInput.name = 'utr'; utrInput.value = utr;
    let btn = document.createElement('input'); btn.type = 'hidden'; btn.name = 'add_funds_req'; btn.value = '1';
    form.appendChild(amtInput); form.appendChild(utrInput); form.appendChild(btn);
    document.body.appendChild(form); form.submit();
}
function closePopup(id) {
    document.getElementById(id).style.display = 'none';
}
// Show dashboard default
document.getElementById('dashboardDefault').style.display = 'block';
</script>
<?php endif; ?>
</body>
</html>
