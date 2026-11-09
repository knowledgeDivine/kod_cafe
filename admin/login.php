<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/functions.php';
if (!empty($_SESSION['admin_id'])) redirect('admin/dashboard.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) $error = 'Session expired. Refresh the page and try again.';
    else {
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        try {
            $stmt = db()->prepare('SELECT id, name, email, password_hash FROM admins WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            $admin = $stmt->fetch();
            if ($admin && password_verify($password, $admin['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$admin['id'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                db()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $admin['id']]);
                redirect('admin/dashboard.php');
            }
            $error = 'The email or password is not correct.';
        } catch (Throwable $exception) { error_log('Admin login error: ' . $exception->getMessage()); $error = 'Login is temporarily unavailable. Check the database connection.'; }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin login — K.O.D</title><link rel="stylesheet" href="<?= e(base_url('assets/css/admin.css')) ?>"></head><body class="login-body"><div class="login-card"><a class="admin-logo login-logo" href="<?= e(base_url()) ?>">K<span>.</span>O<span>.</span>D <small>ADMIN STUDIO</small></a><span class="admin-overline">GOOD TO SEE YOU</span><h1>Sign in to<br>your workspace.</h1><p>Manage the K.O.D menu and customer enquiries.</p><?php if ($error): ?><div class="admin-alert error"><?= e($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Email address<input type="email" name="email" required autocomplete="username"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button class="admin-button" type="submit">Enter the studio ↗</button></form><a class="back-link" href="<?= e(base_url()) ?>">← Back to website</a></div></body></html>
