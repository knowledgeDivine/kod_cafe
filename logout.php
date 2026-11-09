<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/customer_auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
    http_response_code(405);
    exit('Invalid logout request. Return to your account and try again.');
}
unset($_SESSION['customer_id']);
session_regenerate_id(true);
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
set_flash('success', 'You have signed out of your K.O.D account.');
redirect('login.php');
