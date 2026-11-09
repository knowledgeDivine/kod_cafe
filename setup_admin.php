<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
$error = ''; $success = false;
try { $adminCount = (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn(); }
catch (Throwable $exception) { http_response_code(500); exit('Database unavailable. Import database/database.sql and check config/.env first.'); }
$setupToken = env_value('ADMIN_SETUP_TOKEN', '');
if ($adminCount > 0) { http_response_code(403); exit('An administrator already exists. Setup is permanently closed.'); }
if ($setupToken === '' || $setupToken === 'replace-this-with-a-long-random-secret') { http_response_code(403); exit('Set a unique ADMIN_SETUP_TOKEN in your .env file before using this page.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) $error = 'Session expired. Refresh and try again.';
    elseif (!hash_equals($setupToken, (string)($_POST['setup_token'] ?? ''))) $error = 'Setup token does not match your .env configuration.';
    else {
        $name = trim((string)($_POST['name'] ?? '')); $email = trim((string)($_POST['email'] ?? '')); $password = (string)($_POST['password'] ?? '');
        if ($name === '' || mb_strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) $error = 'Enter a name, valid email and a password of at least 12 characters.';
        else {
            $stmt = db()->prepare('INSERT INTO admins (name,email,password_hash) VALUES (:name,:email,:hash)');
            $stmt->execute(['name'=>$name,'email'=>$email,'hash'=>password_hash($password, PASSWORD_DEFAULT)]);
            $success = true;
        }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Initial admin setup — K.O.D</title><link rel="stylesheet" href="<?= e(base_url('assets/css/admin.css')) ?>"></head><body class="login-body"><div class="login-card"><a class="admin-logo login-logo" href="<?= e(base_url()) ?>">K<span>.</span>O<span>.</span>D <small>ADMIN STUDIO</small></a><?php if ($success): ?><span class="admin-overline">ALL SET</span><h1>Your workspace<br>is ready.</h1><p>Your administrator account has been created. Delete <code>setup_admin.php</code> from your project now.</p><a class="admin-button button-link" href="<?= e(base_url('admin/login.php')) ?>">Go to admin login ↗</a><?php else: ?><span class="admin-overline">ONE-TIME SETUP</span><h1>Create your<br>admin account.</h1><p>This page closes automatically after the first administrator is created.</p><?php if ($error): ?><div class="admin-alert error"><?= e($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Setup token<input type="password" name="setup_token" required autocomplete="off"></label><label>Your name<input type="text" name="name" required maxlength="100"></label><label>Admin email<input type="email" name="email" required></label><label>Password (minimum 12 characters)<input type="password" name="password" minlength="12" required autocomplete="new-password"></label><button class="admin-button" type="submit">Create administrator ↗</button></form><?php endif; ?><a class="back-link" href="<?= e(base_url()) ?>">← Back to website</a></div></body></html>
