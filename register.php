<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/customer_auth.php';
if (current_customer()) redirect('account.php');
$error = '';
$name = $email = $phone = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        $name = trim((string)($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        if ($name === '' || strlen($name) > 120) $error = 'Enter your name (up to 120 characters).';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) $error = 'Enter a valid email address.';
        elseif ($phone === '' || strlen($phone) > 40) $error = 'Enter a phone number (up to 40 characters).';
        elseif (strlen($password) < 12) $error = 'Your password must contain at least 12 characters.';
        elseif ($password !== $confirm) $error = 'Your passwords do not match.';
        else {
            try {
                $pdo = db();
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('INSERT INTO customers (full_name, email, phone, password_hash, loyalty_points) VALUES (:name, :email, :phone, :password_hash, 25)');
                $stmt->execute(['name' => $name, 'email' => $email, 'phone' => $phone, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
                $customerId = (int)$pdo->lastInsertId();
                $welcome = $pdo->prepare("INSERT INTO loyalty_transactions (customer_id, order_id, transaction_type, points_delta, naira_value, note) VALUES (:customer_id, NULL, 'welcome', 25, 0, 'K.O.D welcome bonus')");
                $welcome->execute(['customer_id' => $customerId]);
                $pdo->commit();
                customer_login(['id' => $customerId]);
                set_flash('success', 'Welcome to K.O.D. Your 25 welcome points are ready.');
                redirect('account.php');
            } catch (PDOException $exception) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
                if ($exception->getCode() === '23000') $error = 'An account with that email already exists. Sign in instead.';
                else { error_log('Customer registration failed: ' . $exception->getMessage()); $error = 'We could not create your account right now. Check the database migration and try again.'; }
            } catch (Throwable $exception) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
                error_log('Customer registration failed: ' . $exception->getMessage());
                $error = 'Registration is temporarily unavailable. Please try again.';
            }
        }
    }
}
$pageTitle = 'Join K.O.D — Customer account';
$pageDescription = 'Create a K.O.D customer account and start earning café rewards.';
require __DIR__ . '/includes/header.php';
?>
<section class="order-page section-pad customer-auth-page">
  <div class="section-kicker"><span>09</span><span class="kicker-line"></span><span>THE K.O.D CLUB</span></div>
  <div class="customer-auth-layout">
    <div class="customer-auth-story"><span class="eyebrow">A LITTLE EXTRA IN EVERY VISIT</span><h1 class="section-title">Good coffee.<br><em>Good rewards.</em></h1><p>Create an account to keep your orders together, collect loyalty points and enjoy rewards as you return.</p><div class="welcome-reward"><strong>+25</strong><span>WELCOME POINTS<br><small>Added when you join.</small></span></div></div>
    <form method="post" class="customer-form" novalidate>
      <span class="eyebrow">CREATE YOUR ACCOUNT</span><h2>Join the K.O.D Club.</h2>
      <?php if ($error): ?><div class="customer-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label>Full name<input type="text" name="full_name" value="<?= e($name) ?>" autocomplete="name" maxlength="120" required></label>
      <label>Email address<input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" maxlength="190" required></label>
      <label>Phone number<input type="tel" name="phone" value="<?= e($phone) ?>" autocomplete="tel" maxlength="40" required></label>
      <label>Password <small>At least 12 characters</small><input type="password" name="password" autocomplete="new-password" minlength="12" required></label>
      <label>Confirm password<input type="password" name="confirm_password" autocomplete="new-password" minlength="12" required></label>
      <button class="button button-dark customer-submit" type="submit">Create my account <span>↗</span></button>
      <p class="customer-form-foot">Already a member? <a href="<?= e(base_url('login.php')) ?>">Sign in</a></p>
    </form>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
