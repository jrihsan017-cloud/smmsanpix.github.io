<?php
// ============ KONFIGURASI (edit bagian ini) ============
const SITE = 'FB Pro Booster';
const ADMIN_USER = 'admin';
const ADMIN_PASS = 'GANTI_PASSWORD_INI';      // wajib diganti sebelum upload
const PROVIDER_URL = '';                       // contoh: https://provider.com/api/v2 (kosong = proses manual)
const PROVIDER_KEY = '';                       // API key dari provider
const BANK_INFO = 'BCA 1234567890 a/n Nama Anda  |  DANA/OVO 08xxxxxxxxxx';
const MIN_TOPUP = 10000;
const FREE_MODE = true;       // true = semua pesanan GRATIS, tanpa saldo. false = berbayar
const FREE_MAX_QTY = 500;      // batas jumlah per pesanan saat mode gratis
const FREE_PER_DAY = 3;        // batas pesanan per pengguna per 24 jam saat mode gratis
const FINAL_ST = "'Completed','Canceled','Partial','Gagal','Batal'";
// =======================================================

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();
if (empty($_SESSION['t'])) $_SESSION['t'] = bin2hex(random_bytes(16));

$dir = __DIR__ . '/data';
if (!is_dir($dir)) mkdir($dir, 0750, true);
if (!file_exists("$dir/.htaccess")) file_put_contents("$dir/.htaccess", "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
$db = new PDO('sqlite:' . $dir . '/panel.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec("CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY,name TEXT UNIQUE,pass TEXT,bal INTEGER DEFAULT 0,role TEXT DEFAULT 'user');
CREATE TABLE IF NOT EXISTS services(id INTEGER PRIMARY KEY,name TEXT,price INTEGER,min INTEGER,max INTEGER,pid INTEGER DEFAULT 0,info TEXT,active INTEGER DEFAULT 1);
CREATE TABLE IF NOT EXISTS orders(id INTEGER PRIMARY KEY,uid INTEGER,sid INTEGER,sname TEXT,link TEXT,qty INTEGER,total INTEGER,pord TEXT,status TEXT,created INTEGER);
CREATE TABLE IF NOT EXISTS topups(id INTEGER PRIMARY KEY,uid INTEGER,amt INTEGER,note TEXT,status TEXT DEFAULT 'pending',created INTEGER);");
if (!one("SELECT id FROM users WHERE role='admin'")) {
    q("INSERT INTO users(name,pass,role) VALUES(?,?,'admin')", [ADMIN_USER, password_hash(ADMIN_PASS, PASSWORD_DEFAULT)]);
}
if (!one('SELECT id FROM services')) {
    foreach ([['FB Pro Followers - Hemat', 18000, 100, 50000, 'Estimasi 1-3 hari'], ['FB Pro Followers - Standar', 28000, 100, 100000, 'Garansi 30 hari'], ['FB Pro Followers - Premium', 45000, 50, 30000, 'Garansi refill 90 hari']] as $s)
        q('INSERT INTO services(name,price,min,max,info) VALUES(?,?,?,?,?)', $s);
}

function q($sql, $a = []) { global $db; $s = $db->prepare($sql); $s->execute($a); return $s; }
function one($sql, $a = []) { return q($sql, $a)->fetch(); }
function all($sql, $a = []) { return q($sql, $a)->fetchAll(); }
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rp($n) { return 'Rp ' . number_format((int)$n, 0, ',', '.'); }
function go($p, $m = '') { if ($m) $_SESSION['f'] = $m; header('Location: ?p=' . $p); exit; }
function me() { return isset($_SESSION['u']) ? one('SELECT * FROM users WHERE id=?', [$_SESSION['u']]) : null; }
function tok() { return '<input type="hidden" name="t" value="' . e($_SESSION['t']) . '">'; }
function api($a) {
    if (!PROVIDER_URL) return null;
    $c = curl_init(PROVIDER_URL);
    curl_setopt_array($c, [CURLOPT_POST => 1, CURLOPT_POSTFIELDS => http_build_query($a + ['key' => PROVIDER_KEY]), CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 20]);
    $r = curl_exec($c);
    return $r ? (json_decode($r, true) ?: ['error' => 'bad response']) : ['error' => 'timeout'];
}
function refund($id, $st, $amt = null) {
    $o = one('SELECT * FROM orders WHERE id=?', [$id]);
    if (!$o) return;
    $n = q('UPDATE orders SET status=? WHERE id=? AND status NOT IN (' . FINAL_ST . ')', [$st, $id])->rowCount();
    if ($n) q('UPDATE users SET bal=bal+? WHERE id=?', [$amt ?? $o['total'], $o['uid']]);
}
function sync($uid) {
    if (!PROVIDER_URL) return;
    foreach (all('SELECT * FROM orders WHERE uid=? AND pord IS NOT NULL AND status NOT IN (' . FINAL_ST . ') LIMIT 15', [$uid]) as $o) {
        $r = api(['action' => 'status', 'order' => $o['pord']]);
        if (empty($r['status'])) continue;
        if ($r['status'] == 'Canceled') refund($o['id'], 'Canceled');
        elseif ($r['status'] == 'Partial') refund($o['id'], 'Partial', (int)floor(($r['remains'] ?? 0) * $o['total'] / max(1, $o['qty'])));
        else q('UPDATE orders SET status=? WHERE id=?', [$r['status'], $o['id']]);
    }
}

$u = me();
$adm = $u && $u['role'] === 'admin';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['t'], $_POST['t'] ?? '')) exit('Sesi tidak valid, muat ulang halaman.');
    $a = $_POST['a'] ?? '';
    if ($a == 'reg') {
        $n = trim($_POST['name'] ?? ''); $pw = $_POST['pass'] ?? '';
        if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $n) || strlen($pw) < 6) go('login', 'Username 3-20 huruf/angka, password min 6 karakter.');
        if (one('SELECT id FROM users WHERE name=?', [$n])) go('login', 'Username sudah dipakai.');
        q('INSERT INTO users(name,pass) VALUES(?,?)', [$n, password_hash($pw, PASSWORD_DEFAULT)]);
        $_SESSION['u'] = $db->lastInsertId(); session_regenerate_id(true); go('home', 'Selamat datang!');
    }
    if ($a == 'login') {
        $r = one('SELECT * FROM users WHERE name=?', [trim($_POST['name'] ?? '')]);
        if (!$r || !password_verify($_POST['pass'] ?? '', $r['pass'])) { sleep(1); go('login', 'Username atau password salah.'); }
        $_SESSION['u'] = $r['id']; session_regenerate_id(true); go('home');
    }
    if ($a == 'logout') { session_destroy(); header('Location: ?p=login'); exit; }
    if (!$u) go('login');
    if ($a == 'order') {
        $s = one('SELECT * FROM services WHERE id=? AND active=1', [(int)$_POST['sid']]);
        $qty = (int)$_POST['qty']; $link = trim($_POST['link'] ?? '');
        if (!$s || $qty < $s['min'] || $qty > $s['max'] || !preg_match('~^https?://(www\.|m\.|web\.)?(facebook|fb)\.com/\S+$~i', $link)) go('home', 'Data pesanan tidak valid.');
        $t = (int)ceil($qty * $s['price'] / 1000);
        if (FREE_MODE) {
            $t = 0;
            if ($qty > FREE_MAX_QTY || one('SELECT COUNT(*) c FROM orders WHERE uid=? AND created>?', [$u['id'], time() - 86400])['c'] >= FREE_PER_DAY)
                go('home', 'Batas gratis: maks ' . FREE_MAX_QTY . ' per pesanan dan ' . FREE_PER_DAY . ' pesanan per hari.');
        }
        $db->exec('BEGIN IMMEDIATE');
        if (one('SELECT bal FROM users WHERE id=?', [$u['id']])['bal'] < $t) { $db->exec('ROLLBACK'); go('home', 'Saldo tidak cukup, silakan isi saldo.'); }
        q('UPDATE users SET bal=bal-? WHERE id=?', [$t, $u['id']]);
        q('INSERT INTO orders(uid,sid,sname,link,qty,total,status,created) VALUES(?,?,?,?,?,?,?,?)', [$u['id'], $s['id'], $s['name'], $link, $qty, $t, 'Pending', time()]);
        $oid = $db->lastInsertId();
        $db->exec('COMMIT');
        if (PROVIDER_URL && $s['pid']) {
            $r = api(['action' => 'add', 'service' => $s['pid'], 'link' => $link, 'quantity' => $qty]);
            if (!empty($r['order'])) q('UPDATE orders SET pord=? WHERE id=?', [$r['order'], $oid]);
            else refund($oid, 'Gagal');
        }
        go('hist', 'Pesanan dibuat.');
    }
    if ($a == 'topup') {
        $amt = (int)$_POST['amt'];
        if ($amt < MIN_TOPUP) go('topup', 'Minimal isi saldo ' . rp(MIN_TOPUP));
        q('INSERT INTO topups(uid,amt,note,created) VALUES(?,?,?,?)', [$u['id'], $amt, substr(trim($_POST['note'] ?? ''), 0, 100), time()]);
        go('topup', 'Permintaan terkirim. Saldo masuk setelah transfer dikonfirmasi admin.');
    }
    if (!$adm) exit('Forbidden');
    if ($a == 'tp') {
        $r = one('SELECT * FROM topups WHERE id=?', [(int)$_POST['id']]);
        if ($r && q("UPDATE topups SET status=? WHERE id=? AND status='pending'", [$_POST['ok'] ? 'ok' : 'no', $r['id']])->rowCount() && $_POST['ok'])
            q('UPDATE users SET bal=bal+? WHERE id=?', [$r['amt'], $r['uid']]);
        go('admin', 'Top-up diproses.');
    }
    if ($a == 'sv') {
        $v = [trim($_POST['name']), (int)$_POST['price'], (int)$_POST['min'], (int)$_POST['max'], (int)$_POST['pid'], trim($_POST['info']), isset($_POST['active']) ? 1 : 0];
        if (!empty($_POST['id'])) q('UPDATE services SET name=?,price=?,min=?,max=?,pid=?,info=?,active=? WHERE id=?', array_merge($v, [(int)$_POST['id']]));
        else q('INSERT INTO services(name,price,min,max,pid,info,active) VALUES(?,?,?,?,?,?,?)', $v);
        go('admin', 'Layanan disimpan.');
    }
    if ($a == 'bal') { q('UPDATE users SET bal=bal+? WHERE id=?', [(int)$_POST['amt'], (int)$_POST['uid']]); go('admin', 'Saldo diubah.'); }
    if ($a == 'ord') {
        if ($_POST['st'] == 'Batal') refund((int)$_POST['id'], 'Batal');
        else q('UPDATE orders SET status=? WHERE id=?', ['Completed', (int)$_POST['id']]);
        go('admin', 'Pesanan diperbarui.');
    }
}

$p = $_GET['p'] ?? 'home';
if (!$u && !in_array($p, ['login'])) go('login');
if ($p == 'admin' && !$adm) go('home');
$flash = $_SESSION['f'] ?? ''; unset($_SESSION['f']);

function top($u, $adm) {
    global $flash;
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e(SITE) . '</title><style>
:root{--bg:#f4f6fb;--cd:#fff;--tx:#18202f;--mu:#667085;--bd:#e3e7ef;--pr:#1877f2}
@media(prefers-color-scheme:dark){:root{--bg:#10141c;--cd:#1a2030;--tx:#e8ecf5;--mu:#98a2b8;--bd:#2a3247;--pr:#4a95ff}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--tx);font:15px/1.5 system-ui,sans-serif}
.w{max-width:860px;margin:0 auto;padding:14px}.c{background:var(--cd);border:1px solid var(--bd);border-radius:14px;padding:16px;margin-bottom:14px}
nav{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px}nav a,button{font:inherit;color:var(--tx);background:var(--cd);border:1px solid var(--bd);border-radius:10px;padding:7px 12px;text-decoration:none;cursor:pointer}
button.p{background:var(--pr);border-color:var(--pr);color:#fff}input,select{width:100%;font:inherit;padding:9px 11px;border-radius:10px;border:1px solid var(--bd);background:var(--bg);color:var(--tx);margin:3px 0 10px}
label{font-size:13px;color:var(--mu)}.s{overflow-x:auto}table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;padding:7px 5px;border-bottom:1px solid var(--bd);white-space:nowrap}
.f{background:var(--pr);color:#fff;padding:9px 12px;border-radius:10px;margin-bottom:12px}h2{margin:0 0 10px;font-size:17px}.m{color:var(--mu);font-size:13px}td input{margin:0;padding:5px}form.i{display:inline}</style></head><body><div class="w">';
    if ($u) {
        echo '<nav><b>' . e(SITE) . '</b><a href="?p=home">Pesan</a><a href="?p=hist">Riwayat</a>' . (FREE_MODE ? '' : '<a href="?p=topup">Isi saldo</a>') . ($adm ? '<a href="?p=admin">Admin</a>' : '') . '<span style="margin-left:auto">' . e($u['name']) . (FREE_MODE ? ' · <b>GRATIS</b>' : ' · <b>' . rp($u['bal']) . '</b>') . '</span><form class="i" method="post">' . tok() . '<input type="hidden" name="a" value="logout"><button>Keluar</button></form></nav>';
    }
    if ($flash) echo '<div class="f">' . e($flash) . '</div>';
}

top($u, $adm);
if ($p == 'login') {
    echo '<div class="c" style="max-width:380px;margin:40px auto"><h2>' . e(SITE) . '</h2><form method="post">' . tok() . '<label>Username</label><input name="name" required><label>Password</label><input type="password" name="pass" required>
<button class="p" name="a" value="login">Masuk</button> <button name="a" value="reg">Daftar akun baru</button></form></div>';
} elseif ($p == 'hist') {
    sync($u['id']);
    echo '<div class="c"><h2>Riwayat pesanan</h2><div class="s"><table><tr><th>ID</th><th>Waktu</th><th>Layanan</th><th>Link</th><th>Jumlah</th><th>Total</th><th>Status</th></tr>';
    foreach (all('SELECT * FROM orders WHERE uid=? ORDER BY id DESC LIMIT 100', [$u['id']]) as $o)
        echo '<tr><td>#' . $o['id'] . '</td><td>' . date('d/m H:i', $o['created']) . '</td><td>' . e($o['sname']) . '</td><td>' . e(substr($o['link'], 0, 30)) . '</td><td>' . number_format($o['qty'], 0, ',', '.') . '</td><td>' . rp($o['total']) . '</td><td>' . e($o['status']) . '</td></tr>';
    echo '</table></div></div>';
} elseif ($p == 'topup') {
    echo '<div class="c"><h2>Isi saldo</h2><p>Transfer ke: <b>' . e(BANK_INFO) . '</b></p><form method="post">' . tok() . '<input type="hidden" name="a" value="topup"><label>Nominal (Rp)</label><input type="number" name="amt" min="' . MIN_TOPUP . '" required><label>Catatan (nama pengirim / no. referensi)</label><input name="note"><button class="p">Saya sudah transfer</button></form></div>
<div class="c"><h2>Riwayat isi saldo</h2><div class="s"><table><tr><th>Waktu</th><th>Nominal</th><th>Status</th></tr>';
    foreach (all('SELECT * FROM topups WHERE uid=? ORDER BY id DESC LIMIT 20', [$u['id']]) as $t)
        echo '<tr><td>' . date('d/m H:i', $t['created']) . '</td><td>' . rp($t['amt']) . '</td><td>' . e($t['status']) . '</td></tr>';
    echo '</table></div></div>';
} elseif ($p == 'admin') {
    echo '<div class="c"><h2>Top-up menunggu</h2><div class="s"><table>';
    foreach (all("SELECT t.*,u.name FROM topups t JOIN users u ON u.id=t.uid WHERE t.status='pending' ORDER BY t.id") as $t)
        echo '<tr><td>' . e($t['name']) . '</td><td>' . rp($t['amt']) . '</td><td>' . e($t['note']) . '</td><td><form class="i" method="post">' . tok() . '<input type="hidden" name="a" value="tp"><input type="hidden" name="id" value="' . $t['id'] . '"><button class="p" name="ok" value="1">Terima</button> <button name="ok" value="">Tolak</button></form></td></tr>';
    echo '</table></div></div><div class="c"><h2>Layanan</h2><p class="m">Harga per 1000 (Rp). ID provider = nomor layanan di panel provider Anda.</p><div class="s"><table><tr><th>Nama</th><th>Harga</th><th>Min</th><th>Max</th><th>ID prov</th><th>Info</th><th>Aktif</th><th></th></tr>';
    $rows = all('SELECT * FROM services ORDER BY id'); $rows[] = ['id' => '', 'name' => '', 'price' => '', 'min' => 100, 'max' => 10000, 'pid' => 0, 'info' => '', 'active' => 1];
    foreach ($rows as $s)
        echo '<tr><form method="post">' . tok() . '<input type="hidden" name="a" value="sv"><input type="hidden" name="id" value="' . $s['id'] . '"><td><input name="name" value="' . e($s['name']) . '" required></td><td><input name="price" type="number" value="' . e($s['price']) . '" required></td><td><input name="min" type="number" value="' . $s['min'] . '"></td><td><input name="max" type="number" value="' . $s['max'] . '"></td><td><input name="pid" type="number" value="' . $s['pid'] . '"></td><td><input name="info" value="' . e($s['info']) . '"></td><td><input type="checkbox" name="active" ' . ($s['active'] ? 'checked' : '') . ' style="width:auto"></td><td><button class="p">' . ($s['id'] ? 'Simpan' : 'Tambah') . '</button></td></form></tr>';
    echo '</table></div></div><div class="c"><h2>Pengguna</h2><div class="s"><table>';
    foreach (all('SELECT * FROM users ORDER BY id DESC LIMIT 50') as $r)
        echo '<tr><td>' . e($r['name']) . '</td><td>' . rp($r['bal']) . '</td><td><form class="i" method="post">' . tok() . '<input type="hidden" name="a" value="bal"><input type="hidden" name="uid" value="' . $r['id'] . '"><input name="amt" type="number" placeholder="+/- Rp" style="width:120px;margin:0"> <button>Ubah saldo</button></form></td></tr>';
    echo '</table></div></div><div class="c"><h2>Pesanan terbaru</h2><div class="s"><table>';
    foreach (all('SELECT o.*,u.name FROM orders o JOIN users u ON u.id=o.uid ORDER BY o.id DESC LIMIT 50') as $o)
        echo '<tr><td>#' . $o['id'] . '</td><td>' . e($o['name']) . '</td><td>' . e($o['sname']) . '</td><td>' . e($o['link']) . '</td><td>' . $o['qty'] . '</td><td>' . e($o['status']) . '</td><td><form class="i" method="post">' . tok() . '<input type="hidden" name="a" value="ord"><input type="hidden" name="id" value="' . $o['id'] . '"><button name="st" value="Completed">Selesai</button> <button name="st" value="Batal">Batal+refund</button></form></td></tr>';
    echo '</table></div></div>';
} else {
    $sv = all('SELECT * FROM services WHERE active=1 ORDER BY price');
    echo '<div class="c"><h2>Pesan followers FB Pro</h2><form method="post">' . tok() . '<input type="hidden" name="a" value="order"><label>Layanan</label><select name="sid" id="sid">';
    foreach ($sv as $s) echo '<option value="' . $s['id'] . '" data-p="' . $s['price'] . '" data-min="' . $s['min'] . '" data-max="' . $s['max'] . '" data-i="' . e($s['info']) . '">' . e($s['name']) . '</option>';
    echo '</select><div class="m" id="inf"></div><br><label>Link profil Facebook</label><input name="link" placeholder="https://facebook.com/username" required><label>Jumlah</label><input type="number" name="qty" id="qty" value="1000" required><div class="m" id="rng"></div><p><b>Total: <span id="tot">Rp 0</span></b></p><button class="p">Pesan sekarang</button></form></div>
<script>var S=document.getElementById("sid"),Q=document.getElementById("qty");function f(){var o=S.selectedOptions[0];if(!o)return;document.getElementById("inf").textContent=o.dataset.i+" · Rp "+(+o.dataset.p).toLocaleString("id-ID")+" / 1000";document.getElementById("rng").textContent="Min "+o.dataset.min+" · Max "+o.dataset.max;document.getElementById("tot").textContent="Rp "+Math.ceil(Q.value*o.dataset.p/1000).toLocaleString("id-ID")}S.onchange=Q.oninput=f;f();' . (FREE_MODE ? 'function g(){document.getElementById("tot").textContent="GRATIS"}S.onchange=Q.oninput=function(){f();g()};g();' : '') . '</script>';
}
echo '</div></body></html>';
