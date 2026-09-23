<?php
// ============================================
// SK Travel Planner — Admin Password Update
// Secure password change with verification
// ============================================
ini_set('session.cookie_httponly', 1);
session_start();

function e($s) { return htmlspecialchars($s, ENT_QUOTES|ENT_HTML5, 'UTF-8'); }

// DB connection
 $dbHost = 'localhost'; $dbName = 'sk_travel_planner'; $dbUser = 'root'; $dbPass = '';
 $cp = __DIR__ . '/config.php';
if (file_exists($cp)) {
    $c = file_get_contents($cp);
    if (preg_match("/define\('DB_HOST',\s*'([^']*)'\)/", $c, $m)) $dbHost = $m[1];
    if (preg_match("/define\('DB_NAME',\s*'([^']*)'\)/", $c, $m)) $dbName = $m[1];
    if (preg_match("/define\('DB_USER',\s*'([^']*)'\)/", $c, $m)) $dbUser = $m[1];
    if (preg_match("/define\('DB_PASS',\s*'([^']*)'\)/", $c, $m)) $dbPass = $m[1];
}

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
} catch (PDOException $ex) { die('DB error: '.$ex->getMessage()); }

// CSRF
 $_SESSION['pw_csrf'] = $_SESSION['pw_csrf'] ?? bin2hex(random_bytes(32));
 $csrf = $_SESSION['pw_csrf'];

// Admin list
 $admins = $pdo->query("SELECT id, username, created_at FROM admin_users ORDER BY id")->fetchAll();

// Process
 $errors = []; $success = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['act'])) {
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['pw_csrf'], $_POST['csrf'])) {
        $errors[] = 'Security token mismatch. Refresh and retry.';
    } else {
        $act = $_POST['act'];

        // ---- CHANGE PASSWORD ----
        if ($act === 'chpass') {
            $uid = (int)($_POST['uid'] ?? 0);
            $cur = $_POST['cur'] ?? '';
            $new = $_POST['new'] ?? '';
            $cnf = $_POST['cnf'] ?? '';

            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id=?");
            $stmt->execute([$uid]);
            $adm = $stmt->fetch();

            if (!$adm) { $errors[] = 'Account not found.'; }
            elseif (!password_verify($cur, $adm['password'])) { $errors[] = 'Current password is incorrect.'; }
            elseif (strlen($new) < 8) { $errors[] = 'New password must be at least 8 characters.'; }
            elseif (strlen($new) > 128) { $errors[] = 'Password too long (max 128).'; }
            elseif ($new !== $cnf) { $errors[] = 'New passwords do not match.'; }
            elseif (password_verify($new, $adm['password'])) { $errors[] = 'New password must differ from current.'; }

            if (empty($errors)) {
                $hash = password_hash($new, PASSWORD_DEFAULT, ['cost'=>12]);
                $stmt = $pdo->prepare("UPDATE admin_users SET password=? WHERE id=?");
                $stmt->execute([$hash, $uid]);
                $success = 'Password updated for "' . $adm['username'] . '". Old password no longer works.';
            }
        }

        // ---- CHANGE USERNAME ----
        if ($act === 'chuser') {
            $uid = (int)($_POST['uid'] ?? 0);
            $cur = $_POST['cur'] ?? '';
            $nusr = trim($_POST['nusr'] ?? '');

            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id=?");
            $stmt->execute([$uid]);
            $adm = $stmt->fetch();

            if (!$adm) { $errors[] = 'Account not found.'; }
            elseif (!password_verify($cur, $adm['password'])) { $errors[] = 'Current password is incorrect.'; }
            elseif (strlen($nusr) < 3) { $errors[] = 'Username must be at least 3 characters.'; }
            elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $nusr)) { $errors[] = 'Username: only letters, numbers, underscores.'; }
            elseif ($nusr === $adm['username']) { $errors[] = 'New username is same as current.'; }
            else {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE username=? AND id!=?");
                $chk->execute([$nusr, $uid]);
                if ($chk->fetchColumn() > 0) { $errors[] = 'Username already taken.'; }
            }

            if (empty($errors)) {
                $stmt = $pdo->prepare("UPDATE admin_users SET username=? WHERE id=?");
                $stmt->execute([$nusr, $uid]);
                $success = 'Username changed to "' . $nusr . '". Use new username for next login.';
                $admins = $pdo->query("SELECT id, username, created_at FROM admin_users ORDER BY id")->fetchAll();
            }
        }

        // ---- CREATE ADMIN ----
        if ($act === 'create') {
            $nusr = trim($_POST['nusr'] ?? '');
            $npass = $_POST['npass'] ?? '';
            $ncnf = $_POST['ncnf'] ?? '';

            if (strlen($nusr) < 3) { $errors[] = 'Username must be at least 3 characters.'; }
            elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $nusr)) { $errors[] = 'Username: only letters, numbers, underscores.'; }
            else {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE username=?");
                $chk->execute([$nusr]);
                if ($chk->fetchColumn() > 0) { $errors[] = 'Username already exists.'; }
            }
            if (strlen($npass) < 8) { $errors[] = 'Password must be at least 8 characters.'; }
            if ($npass !== $ncnf) { $errors[] = 'Passwords do not match.'; }

            if (empty($errors)) {
                $hash = password_hash($npass, PASSWORD_DEFAULT, ['cost'=>12]);
                $stmt = $pdo->prepare("INSERT INTO admin_users (username, password) VALUES (?, ?)");
                $stmt->execute([$nusr, $hash]);
                $success = 'Admin "' . $nusr . '" created successfully.';
                $admins = $pdo->query("SELECT id, username, created_at FROM admin_users ORDER BY id")->fetchAll();
            }
        }

        // ---- DELETE ADMIN ----
        if ($act === 'deladm') {
            $uid = (int)($_POST['uid'] ?? 0);
            $cur = $_POST['cur'] ?? '';

            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id=?");
            $stmt->execute([$uid]);
            $adm = $stmt->fetch();

            if (!$adm) { $errors[] = 'Account not found.'; }
            elseif (!password_verify($cur, $adm['password'])) { $errors[] = 'Password incorrect. Deletion denied.'; }
            else {
                $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id=?");
                $stmt->execute([$uid]);
                $success = 'Admin "' . $adm['username'] . '" deleted permanently.';
                $admins = $pdo->query("SELECT id, username, created_at FROM admin_users ORDER BY id")->fetchAll();
            }
        }
    }
    $_SESSION['pw_csrf'] = bin2hex(random_bytes(32));
    $csrf = $_SESSION['pw_csrf'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin Credential Manager — SK Travel Planner</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--primary:#0D7377;--primary-dark:#095558;--accent:#E8912D;--accent-dark:#C75B2A;--dark:#1B2838;--text:#2D2D2D;--text-muted:#6B7280;--light:#F7F3ED;--light-darker:#EDE7DB;--white:#FFF;--success:#2E8B57;--danger:#DC3545;--warning:#D4A017;--border:#D1CBC0;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Source Sans 3',sans-serif;background:linear-gradient(135deg,#1B2838,#095558,#0D7377);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:30px 16px;color:var(--text);}
.card{background:var(--white);border-radius:16px;width:100%;max-width:680px;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;}
.card-hdr{background:linear-gradient(135deg,var(--dark),var(--primary-dark));padding:24px 32px;display:flex;align-items:center;gap:14px;}
.card-hdr .ico{width:48px;height:48px;background:linear-gradient(135deg,var(--accent),var(--accent-dark));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;color:white;flex-shrink:0;}
.card-hdr div h1{font-family:'Playfair Display',serif;color:var(--white);font-size:20px;margin-bottom:2px;}
.card-hdr div p{color:rgba(255,255,255,.55);font-size:13px;}
.card-body{padding:28px 32px;}

.admin-chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;}
.chip{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:var(--light);border-radius:20px;font-size:13px;font-weight:600;color:var(--dark);}
.chip i{color:var(--primary);}
.chip .del-link{margin-left:4px;color:var(--danger);cursor:pointer;font-size:12px;}
.chip .del-link:hover{color:#b02a37;}

.sec{margin-bottom:24px;padding-bottom:24px;border-bottom:1px solid var(--light-darker);}
.sec:last-child{border-bottom:none;margin-bottom:0;padding-bottom:0;}
.sec-title{font-family:'Playfair Display',serif;font-size:17px;color:var(--dark);margin-bottom:14px;display:flex;align-items:center;gap:8px;}
.sec-title i{color:var(--primary);}

.form-g{margin-bottom:16px;}
.form-g label{display:block;margin-bottom:5px;font-weight:600;font-size:13px;color:var(--dark);}
.form-g label .req{color:var(--danger);margin-left:2px;}
.form-g .hint{font-size:11px;color:var(--text-muted);margin-top:3px;}
.fc{width:100%;padding:10px 14px;border:2px solid var(--border);border-radius:8px;font-family:'Source Sans 3',sans-serif;font-size:14px;transition:border-color .3s;background:var(--white);}
.fc:focus{outline:none;border-color:var(--primary);}
select.fc{cursor:pointer;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}

.pw-meter{display:flex;gap:3px;align-items:center;margin-top:6px;}
.pw-bar{flex:1;height:4px;border-radius:2px;background:var(--light-darker);transition:background .3s;}
.pw-lbl{font-size:11px;font-weight:600;min-width:50px;text-align:right;}
.match-hint{font-size:11px;font-weight:600;margin-top:3px;display:none;}

.btn{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;border:none;border-radius:8px;font-family:'Source Sans 3',sans-serif;font-size:14px;font-weight:600;cursor:pointer;transition:all .2s;text-decoration:none;}
.btn-p{background:var(--primary);color:var(--white);}
.btn-p:hover{background:var(--primary-dark);color:var(--white);}
.btn-a{background:var(--accent);color:var(--white);}
.btn-a:hover{background:var(--accent-dark);color:var(--white);}
.btn-d{background:var(--danger);color:var(--white);}
.btn-d:hover{background:#b02a37;color:var(--white);}
.btn-o{background:transparent;color:var(--primary);border:2px solid var(--primary);}
.btn-o:hover{background:var(--primary);color:var(--white);}
.btn-g{background:transparent;color:var(--text-muted);border:2px solid var(--border);}
.btn-g:hover{background:var(--light);color:var(--dark);border-color:var(--text-muted);}
.btn-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;}

.alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-weight:500;font-size:13px;}
.alert-s{background:#d4edda;color:#155724;border-left:4px solid var(--success);}
.alert-d{background:#f8d7da;color:#721c24;border-left:4px solid var(--danger);}

.warn{background:rgba(220,53,69,.05);border:1.5px solid rgba(220,53,69,.15);border-radius:8px;padding:14px 18px;margin-top:18px;display:flex;align-items:flex-start;gap:10px;font-size:13px;color:#721c24;}
.warn i{color:var(--danger);font-size:18px;margin-top:1px;flex-shrink:0;}

.foot{text-align:center;padding:16px 32px 24px;border-top:1px solid var(--light-darker);font-size:13px;}
.foot a{color:var(--primary);font-weight:600;text-decoration:none;}
.foot a:hover{color:var(--accent);}

&.gen-btn{width:100%;justify-content:center;margin-top:4px;}

@media(max-width:500px){.card-body{padding:20px 16px;} .card-hdr{padding:20px 16px;} .form-row{grid-template-columns:1fr;}}
</style>
</head>
<body>
<div class="card">

<div class="card-hdr">
<span class="ico"><i class="fas fa-user-shield"></i></span>
<div>
<h1>Admin Credential Manager</h1>
<p>SK Travel Planner</p>
</div>
</div>

<div class="card-body">

<?php if(!empty($errors)): foreach($errors as $er): ?>
<div class="alert alert-d"><i class="fas fa-times-circle"></i> <?= e($er) ?></div>
<?php endforeach; endif; ?>

<?php if($success): ?>
<div class="alert alert-s"><i class="fas fa-check-circle"></i> <?= e($success) ?></div>
<?php endif; ?>

<div class="admin-chips">
<?php foreach($admins as $a): ?>
<span class="chip">
<i class="fas fa-user"></i> <?= e($a['username']) ?>
<span style="color:var(--text-muted);font-size:11px;margin-left:2px;"><?= date('M j, Y', strtotime($a['created_at'])) ?></span>
</span>
<?php endforeach; ?>
</div>

<!-- ====== CHANGE PASSWORD ====== -->
<div class="sec">
<div class="sec-title"><i class="fas fa-key"></i> Change Password</div>
<form method="POST">
<input type="hidden" name="csrf" value="<?= e($csrf) ?>">
<input type="hidden" name="act" value="chpass">
<div class="form-g">
<label>Select Account <span class="req">*</span></label>
<select name="uid" class="fc" required>
<option value="">— Choose admin —</option>
<?php foreach($admins as $a): ?>
<option value="<?= (int)$a['id'] ?>"><?= e($a['username']) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-g">
<label>Current Password <span class="req">*</span></label>
<input type="password" name="cur" class="fc" placeholder="Verify current password" required>
</div>
<div class="form-g">
<label>New Password <span class="req">*</span></label>
<div style="display:flex;gap:6px;">
<input type="password" name="new" id="pw_new" class="fc" style="flex:1;" placeholder="Min 8 characters" required>
<button type="button" class="btn btn-a" onclick="window.open('password-generator.php','pwgen','width=640,height=800')" style="white-space:nowrap;padding:10px 14px;font-size:13px;"><i class="fas fa-key"></i> Gen</button>
</div>
<div class="pw-meter" id="pw-meter" style="display:none;">
<div class="pw-bar" id="pb1"></div><div class="pw-bar" id="pb2"></div><div class="pw-bar" id="pb3"></div><div class="pw-bar" id="pb4"></div>
<span class="pw-lbl" id="plbl"></span>
</div>
</div>
<div class="form-g">
<label>Confirm New Password <span class="req">*</span></label>
<input type="password" name="cnf" id="pw_cnf" class="fc" placeholder="Re-enter new password" required>
<div class="match-hint" id="mh"></div>
</div>
<div class="btn-row">
<button type="submit" class="btn btn-p"><i class="fas fa-save"></i> Update Password</button>
</div>
</form>
</div>

<!-- ====== CHANGE USERNAME ====== -->
<div class="sec">
<div class="sec-title"><i class="fas fa-user-edit"></i> Change Username</div>
<form method="POST">
<input type="hidden" name="csrf" value="<?= e($csrf) ?>">
<input type="hidden" name="act" value="chuser">
<div class="form-row">
<div class="form-g">
<label>Select Account <span class="req">*</span></label>
<select name="uid" class="fc" required>
<option value="">— Choose admin —</option>
<?php foreach($admins as $a): ?>
<option value="<?= (int)$a['id'] ?>"><?= e($a['username']) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-g">
<label>New Username <span class="req">*</span></label>
<input type="text" name="nusr" class="fc" placeholder="Letters, numbers, underscores" minlength="3" maxlength="50" pattern="[a-zA-Z0-9_]+" required>
</div>
</div>
<div class="form-g">
<label>Current Password (to verify) <span class="req">*</span></label>
<input type="password" name="cur" class="fc" placeholder="Enter current password" required>
</div>
<div class="btn-row">
<button type="submit" class="btn btn-p"><i class="fas fa-save"></i> Update Username</button>
</div>
</form>
</div>

<!-- ====== CREATE NEW ADMIN ====== -->
<div class="sec">
<div class="sec-title"><i class="fas fa-user-plus"></i> Create New Admin</div>
<form method="POST">
<input type="hidden" name="csrf" value="<?= e($csrf) ?>">
<input type="hidden" name="act" value="create">
<div class="form-g">
<label>Username <span class="req">*</span></label>
<input type="text" name="nusr" class="fc" placeholder="e.g. superadmin" minlength="3" maxlength="50" pattern="[a-zA-Z0-9_]+" required>
</div>
<div class="form-g">
<label>Password <span class="req">*</span></label>
<div style="display:flex;gap:6px;">
<input type="password" name="npass" id="cr_pw" class="fc" style="flex:1;" placeholder="Min 8 characters" required>
<button type="button" class="btn btn-a" onclick="window.open('password-generator.php','pwgen','width=640,height=800')" style="white-space:nowrap;padding:10px 14px;font-size:13px;"><i class="fas fa-key"></i> Gen</button>
</div>
</div>
<div class="form-g">
<label>Confirm Password <span class="req">*</span></label>
<input type="password" name="ncnf" id="cr_cnf" class="fc" placeholder="Re-enter password" required>
</div>
<div class="btn-row">
<button type="submit" class="btn btn-a"><i class="fas fa-user-plus"></i> Create Admin</button>
</div>
</form>
</div>

<!-- ====== DANGER ZONE ====== -->
<div class="sec">
<div class="sec-title" style="color:var(--danger);"><i class="fas fa-trash-alt"></i> Delete Admin Account</div>
<p style="font-size:13px;color:var(--text-muted);margin-bottom:14px;">This permanently removes the account. Cannot be undone.</p>
<form method="POST" onsubmit="return confirm('DELETE this admin account permanently?');">
<input type="hidden" name="csrf" value="<?= e($csrf) ?>">
<input type="hidden" name="act" value="deladm">
<div class="form-row">
<div class="form-g">
<select name="uid" class="fc" required>
<option value="">— Select —</option>
<?php foreach($admins as $a): ?>
<option value="<?= (int)$a['id'] ?>"><?= e($a['username']) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-g">
<input type="password" name="cur" class="fc" placeholder="Current password to confirm" required>
</div>
</div>
<div class="btn-row">
<button type="submit" class="btn btn-d"><i class="fas fa-trash-alt"></i> Delete</button>
</div>
</form>
</div>

<div class="warn">
<i class="fas fa-exclamation-triangle"></i>
<span><strong>Delete this file</strong> after setup. Leaving credential management pages accessible is a security risk.</span>
</div>

</div>

<div class="foot">
<a href="index.php"><i class="fas fa-home"></i> Website</a> &bull;
<a href="admin/index.php"><i class="fas fa-lock"></i> Admin Panel</a> &bull;
<a href="password-generator.php"><i class="fas fa-key"></i> Password Generator</a>
</div>

</div>

<script>
// Password strength
var pwf=document.getElementById('pw_new');
if(pwf) pwf.addEventListener('input',function(){
    var v=this.value, m=document.getElementById('pw-meter');
    if(!v){m.style.display='none';return;}
    m.style.display='flex';
    var s=0;
    if(v.length>=8)s++;if(v.length>=12)s++;
    if(/[A-Z]/.test(v)&&/[a-z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^a-zA-Z0-9]/.test(v))s++;
    s=Math.min(s,4);
    var c=['#DC3545','#E8912D','#D4A017','#2E8B57'],l=['Weak','Fair','Good','Strong'];
    for(var i=1;i<=4;i++){var b=document.getElementById('pb'+i);b.style.background=(i<=s)?c[s-1]:'#EDE7DB';}
    var lb=document.getElementById('plbl');lb.textContent=l[s-1]||'Weak';lb.style.color=c[s-1]||'#DC3545';
});

// Confirm match
var cnf=document.getElementById('pw_cnf');
if(cnf) cnf.addEventListener('input',function(){
    var pw=document.getElementById('pw_new').value,h=document.getElementById('mh');
    if(!this.value){h.style.display='none';return;}
    h.style.display='block';
    h.textContent=pw===this.value?'Passwords match':'Passwords do not match';
    h.style.color=pw===this.value?'#2E8B57':'#DC3545';
});

// Listen for password from generator
window.addEventListener('message',function(ev){
    if(ev.data&&ev.data.type==='password'){
        var pw=ev.data.password;
        var f1=document.getElementById('pw_new'),f2=document.getElementById('pw_cnf');
        if(!f1) {f1=document.getElementById('cr_pw');f2=document.getElementById('cr_cnf');}
        if(f1){f1.value=pw;f1.dispatchEvent(new Event('input'));}
        if(f2){f2.value=pw;f2.dispatchEvent(new Event('input'));}
    }
});
</script>
</body>
</html>