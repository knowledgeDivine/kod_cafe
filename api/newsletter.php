<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/database.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
$data = request_json();
if (!csrf_check($data['csrf_token'] ?? null)) json_response(['success' => false, 'message' => 'Refresh the page and try again.'], 419);
$email = trim((string)($data['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) json_response(['success' => false, 'message' => 'Enter a valid email address.'], 422);
try {
    $stmt = db()->prepare('INSERT INTO newsletter_subscribers (email, created_at) VALUES (:email, NOW()) ON DUPLICATE KEY UPDATE email = VALUES(email)');
    $stmt->execute(['email' => $email]);
    json_response(['success' => true, 'message' => 'You’re on the list. Watch this space.']);
} catch (Throwable $exception) {
    error_log('Newsletter signup failed: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'Subscription is unavailable at the moment. Please try again.'], 500);
}
