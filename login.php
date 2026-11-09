<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/customer_auth.php';
if (current_customer()) redirect('account.php');
$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) $error = 'Your session expired. Refresh the page and try again.';
    else {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $lockedUntil = (int)($_SESSION['customer_login_locked_until'] ?? 0);
        if ($lockedUntil > time()) {
            $error = 'Too many sign-in attempts. Please try again in a few minutes.';
        } else {
            try {
                $stmt = db()->prepare('SELECT id, full_name, email, phone, password_hash FROM customers WHERE email = :email LIMIT 1');
                $stmt->execute(['email' => $email]);
                $customer = $stmt->fetch();
                if ($customer && password_verify($password, $customer['password_hash'])) {
                    customer_login($customer);
                    db()->prepare('UPDATE customers SET last_login_at = NOW() WHERE id = :id')->execute(['id' => (int)$customer['id']]);
                    redirect('account.php');
                }
                $failures = (int)($_SESSION['customer_login_failures'] ?? 0) + 1;
                $_SESSION['customer_login_failures'] = $failures;
                if ($failures >= 6) { $_SESSION['customer_login_locked_until'] = time() + 300; $_SESSION['customer_login_failures'] = 0; }
                $error = 'The email or password is not correct.';
            } catch (Throwable $exception) {
                error_log('Customer login failed: ' . $exception->getMessage());
                $error = 'Sign-in is temporarily unavailable. Check your database migration and try again.';
            }
        }
    }
}
$pageTitle = 'Sign in — K.O.D Café';
$pageDescription = 'Sign in to your K.O.D customer account and view your rewards.';
require __DIR__ . '/includes/header.php';
?>
<section class="order-page section-pad customer-auth-page">
  <div class="section-kicker"><span>09</span><span class="kicker-line"></span><span>WELCOME BACK</span></div>
  <div class="customer-auth-layout customer-login-layout">
    <div class="customer-auth-story"><span class="eyebrow">YOUR K.O.D MOMENTS, ALL TOGETHER</span><h1 class="section-title">Your next<br><em>good thing.</em></h1><p>Sign in to check your rewards balance, view your order history and pick up where you left off.</p><div class="customer-perk-list"><span>01 / Collect points on completed orders</span><span>02 / Redeem points for café rewards</span><span>03 / Keep your orders in one place</span></div></div>
    <form method="post" class="customer-form" novalidate>
      <span class="eyebrow">CUSTOMER SIGN IN</span><h2>Good to see you.</h2>
      <?php if ($error): ?><div class="customer-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label>Email address<input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" maxlength="190" required></label>
      <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
      <button class="button button-dark customer-submit" type="submit">Sign in <span>↗</span></button>
      <p class="customer-form-foot">New to K.O.D? <a href="<?= e(base_url('register.php')) ?>">Create an account</a></p>
      <p class="customer-form-note">Forgot your password? Contact K.O.D support for assistance. Email-based password reset is not enabled in this local version.</p>
    </form>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
