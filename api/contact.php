<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/database.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
if (!csrf_check($_POST['csrf_token'] ?? null)) json_response(['success' => false, 'message' => 'Your session expired. Refresh and try again.'], 419);
$name = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$subject = trim((string)($_POST['subject'] ?? 'General enquiry'));
$message = trim((string)($_POST['message'] ?? ''));
if ($name === '' || mb_strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190 || $message === '' || mb_strlen($message) > 3000) {
    json_response(['success' => false, 'message' => 'Please check your name, email and message, then try again.'], 422);
}
if (mb_strlen($phone) > 30 || mb_strlen($subject) > 100) json_response(['success' => false, 'message' => 'One of the fields is too long.'], 422);
try {
    $stmt = db()->prepare('INSERT INTO contact_messages (name, email, phone, subject, message, status, created_at) VALUES (:name, :email, :phone, :subject, :message, "new", NOW())');
    $stmt->execute(['name' => $name, 'email' => $email, 'phone' => $phone, 'subject' => $subject, 'message' => $message]);
    json_response(['success' => true, 'message' => 'Your note is with us. We’ll get back to you soon.']);
} catch (Throwable $exception) {
    error_log('Contact form failed: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'We could not send your note right now. Please try again later.'], 500);
}
