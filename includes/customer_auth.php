<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

function current_customer(): ?array
{
    $id = filter_var($_SESSION['customer_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) return null;
    try {
        $stmt = db()->prepare('SELECT id, full_name, email, phone, loyalty_points, created_at FROM customers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $customer = $stmt->fetch();
        if (!$customer) unset($_SESSION['customer_id']);
        return $customer ?: null;
    } catch (Throwable $exception) {
        error_log('Customer session lookup failed: ' . $exception->getMessage());
        return null;
    }
}

function require_customer(): array
{
    $customer = current_customer();
    if (!$customer) {
        set_flash('error', 'Sign in to view your K.O.D account.');
        redirect('login.php');
    }
    return $customer;
}

function customer_login(array $customer): void
{
    session_regenerate_id(true);
    $_SESSION['customer_id'] = (int)$customer['id'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    unset($_SESSION['customer_login_failures'], $_SESSION['customer_login_locked_until']);
}
