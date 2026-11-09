<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/functions.php';
if (empty($_SESSION['admin_id'])) {
    redirect('admin/login.php');
}
try {
    $stmt = db()->prepare('SELECT id, name, email FROM admins WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int)$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    if (!$admin) {
        unset($_SESSION['admin_id']);
        redirect('admin/login.php');
    }
} catch (Throwable $exception) {
    http_response_code(500);
    exit('Database unavailable. Check your configuration.');
}
