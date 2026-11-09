<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/customer_auth.php';
require_once __DIR__ . '/includes/personalization.php';
$customer = require_customer();
if (kod_award_birthday_points_if_due((int)$customer['id'])) {
    set_flash('success', 'Happy birthday month! 100 bonus K.O.D points have been added to your rewards balance.');
    redirect('account.php#rewards');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) $error = 'Your session expired. Refresh and try again.';
    else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'profile') {
                $name = trim((string)($_POST['full_name'] ?? ''));
                $email = strtolower(trim((string)($_POST['email'] ?? '')));
                $phone = trim((string)($_POST['phone'] ?? ''));
                if ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 || $phone === '' || strlen($phone) > 40) {
                    $error = 'Check your name, email and phone number.';
                } else {
                    $stmt = db()->prepare('UPDATE customers SET full_name = :name, email = :email, phone = :phone WHERE id = :id');
                    $stmt->execute(['name' => $name, 'email' => $email, 'phone' => $phone, 'id' => (int)$customer['id']]);
                    set_flash('success', 'Your profile has been updated.');
                    redirect('account.php');
                }
            } elseif ($action === 'password') {
                $currentPassword = (string)($_POST['current_password'] ?? '');
                $newPassword = (string)($_POST['new_password'] ?? '');
                $confirmPassword = (string)($_POST['confirm_password'] ?? '');
                $stmt = db()->prepare('SELECT password_hash FROM customers WHERE id = :id');
                $stmt->execute(['id' => (int)$customer['id']]);
                $passwordHash = (string)$stmt->fetchColumn();
                if (!password_verify($currentPassword, $passwordHash)) $error = 'Your current password is not correct.';
                elseif (strlen($newPassword) < 12) $error = 'Your new password must contain at least 12 characters.';
                elseif ($newPassword !== $confirmPassword) $error = 'Your new passwords do not match.';
                else {
                    db()->prepare('UPDATE customers SET password_hash = :hash WHERE id = :id')->execute(['hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => (int)$customer['id']]);
                    session_regenerate_id(true);
                    set_flash('success', 'Your password has been changed.');
                    redirect('account.php');
                }
            } elseif ($action === 'preferences') {
                $birthDate = trim((string)($_POST['birth_date'] ?? ''));
                $sweetness = (string)($_POST['sweetness'] ?? 'medium');
                $strength = (string)($_POST['strength'] ?? 'balanced');
                $milk = (string)($_POST['milk_preference'] ?? 'any');
                $flavour = (string)($_POST['preferred_flavour'] ?? 'any');
                $budgetInput = trim((string)($_POST['preferred_budget'] ?? ''));
                $validSweetness = ['low', 'medium', 'high'];
                $validStrength = ['mild', 'balanced', 'strong'];
                $validMilk = ['dairy', 'oat', 'almond', 'soy', 'no_milk', 'any'];
                $validFlavour = ['caramel', 'chocolate', 'vanilla', 'nutty', 'fruity', 'floral', 'earthy', 'any'];
                $birthValue = null;
                if ($birthDate !== '') {
                    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
                    if (!$parsed || $parsed->format('Y-m-d') !== $birthDate || $parsed > new DateTimeImmutable('today')) {
                        $error = 'Enter a valid birthday that is not in the future.';
                    } else { $birthValue = $birthDate; }
                }
                $budget = null;
                if ($budgetInput !== '') {
                    if (!is_numeric($budgetInput) || (float)$budgetInput < 0 || (float)$budgetInput > 1000000) $error = 'Your usual budget must be between ₦0 and ₦1,000,000.';
                    else $budget = number_format((float)$budgetInput, 2, '.', '');
                }
                if (!$error && (!in_array($sweetness, $validSweetness, true) || !in_array($strength, $validStrength, true) || !in_array($milk, $validMilk, true) || !in_array($flavour, $validFlavour, true))) {
                    $error = 'Choose from the available taste profile options.';
                }
                if (!$error) {
                    $save = db()->prepare('INSERT INTO customer_preferences (customer_id, birth_date, sweetness, strength, milk_preference, preferred_flavour, preferred_budget) VALUES (:customer_id, :birth_date, :sweetness, :strength, :milk, :flavour, :budget) ON DUPLICATE KEY UPDATE birth_date = VALUES(birth_date), sweetness = VALUES(sweetness), strength = VALUES(strength), milk_preference = VALUES(milk_preference), preferred_flavour = VALUES(preferred_flavour), preferred_budget = VALUES(preferred_budget)');
                    $save->execute(['customer_id' => (int)$customer['id'], 'birth_date' => $birthValue, 'sweetness' => $sweetness, 'strength' => $strength, 'milk' => $milk, 'flavour' => $flavour, 'budget' => $budget]);
                    set_flash('success', 'Your taste profile has been saved. K.O.D will use it to personalize your recommendations.');
                    redirect('account.php#taste-profile');
                }
            }
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') $error = 'That email address is already used by another K.O.D account.';
            else { error_log('Customer account update failed: ' . $exception->getMessage()); $error = 'We could not update your account. Please try again.'; }
        } catch (Throwable $exception) {
            error_log('Customer account update failed: ' . $exception->getMessage());
            $error = 'We could not update your account. Please try again.';
        }
    }
}
$customer = current_customer() ?? $customer;
$preferences = kod_get_customer_preferences((int)$customer['id']);
$favoriteIds = kod_get_customer_favorite_ids((int)$customer['id']);
$favoriteItems = $personalizedItems = [];
try {
    if ($favoriteIds) {
        $favoritePlaceholders = implode(',', array_fill(0, count($favoriteIds), '?'));
        $favoriteStmt = db()->prepare("SELECT id, name, slug, category, description, price, image_url FROM menu_items WHERE is_available = 1 AND id IN ($favoritePlaceholders) ORDER BY name ASC LIMIT 4");
        $favoriteStmt->execute($favoriteIds); $favoriteItems = $favoriteStmt->fetchAll();
    }
    $personalizedItems = kod_personalized_recommendations((int)$customer['id'], 4);
} catch (Throwable $exception) { error_log('Customer personalization panel failed: ' . $exception->getMessage()); }
$transactions = $orders = [];
try {
    $tx = db()->prepare('SELECT transaction_type, points_delta, naira_value, note, created_at FROM loyalty_transactions WHERE customer_id = :id ORDER BY created_at DESC, id DESC LIMIT 12');
    $tx->execute(['id' => (int)$customer['id']]); $transactions = $tx->fetchAll();
    $os = db()->prepare('SELECT order_number, public_token, total, discount_amount, status, fulfillment, created_at FROM orders WHERE customer_id = :id ORDER BY created_at DESC LIMIT 8');
    $os->execute(['id' => (int)$customer['id']]); $orders = $os->fetchAll();
} catch (Throwable $exception) { error_log('Customer account data lookup failed: ' . $exception->getMessage()); }
$points = (int)$customer['loyalty_points'];
$progress = $points % 100;
$pageTitle = 'My K.O.D account';
$pageDescription = 'Manage your customer account, rewards points and café orders.';
require __DIR__ . '/includes/header.php';
?>
<section class="order-page section-pad account-page">
  <div class="section-kicker"><span>10</span><span class="kicker-line"></span><span>YOUR K.O.D CLUB</span></div>
  <div class="account-heading"><div><span class="eyebrow">WELCOME BACK, <?= e(strtoupper($customer['full_name'])) ?></span><h1 class="section-title">Your little<br><em>corner of K.O.D.</em></h1><p>Your orders, your details and every point you've collected.</p></div><form method="post" action="<?= e(base_url('logout.php')) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="text-link account-logout" type="submit">Sign out ↗</button></form></div>
  <?php if ($error): ?><div class="customer-alert account-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
  <div class="loyalty-overview">
    <div class="loyalty-balance"><span class="eyebrow">YOUR REWARDS BALANCE</span><strong><?= number_format($points) ?></strong><span>POINTS AVAILABLE</span><p>Every completed order earns 1 point for each ₦500 spent.</p></div>
    <div class="loyalty-progress"><div class="loyalty-progress-top"><span>YOUR NEXT REWARD</span><strong><?= number_format($progress) ?> / 100 pts</strong></div><div class="loyalty-progress-track"><span style="width:<?= (int)$progress ?>%"></span></div><p>100 points = ₦500 off a future order. You can redeem in blocks of 100 points at checkout. Add your birthday below to receive 100 bonus points during your birthday month.</p><div class="loyalty-stat-row"><span>Reward value</span><strong>₦<?= number_format(intdiv($points, 100) * 500) ?></strong></div><div class="loyalty-stat-row"><span>Member since</span><strong><?= e(date('M Y', strtotime($customer['created_at']))) ?></strong></div></div>
  </div>
  <div class="account-content-grid">
    <section class="account-panel"><div class="account-panel-heading"><div><span class="eyebrow">YOUR DETAILS</span><h2>Profile settings</h2></div></div><form method="post" class="account-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="profile"><label>Full name<input name="full_name" value="<?= e($customer['full_name']) ?>" maxlength="120" required></label><label>Email address<input type="email" name="email" value="<?= e($customer['email']) ?>" maxlength="190" required></label><label>Phone number<input type="tel" name="phone" value="<?= e($customer['phone']) ?>" maxlength="40" required></label><button class="button button-dark" type="submit">Save profile <span>↗</span></button></form></section>
    <section class="account-panel"><div class="account-panel-heading"><div><span class="eyebrow">ACCOUNT SECURITY</span><h2>Change password</h2></div></div><form method="post" class="account-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="password"><label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label><label>New password<input type="password" name="new_password" minlength="12" autocomplete="new-password" required></label><label>Confirm new password<input type="password" name="confirm_password" minlength="12" autocomplete="new-password" required></label><button class="button button-outline" type="submit">Update password <span>↗</span></button></form></section>
  </div>
  <section class="account-panel account-personalization" id="taste-profile">
    <div class="account-panel-heading"><div><span class="eyebrow">YOUR TASTE, REMEMBERED</span><h2>Build your taste profile.</h2><p class="personalization-intro">Tell us what you enjoy. Your account will use these choices to shape recommendations from the live K.O.D menu.</p></div></div>
    <form method="post" class="taste-profile-form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="preferences">
      <label>Birthday <small>Receive 100 bonus points once during your birthday month each year.</small><input type="date" name="birth_date" value="<?= e($preferences['birth_date']) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
      <label>How sweet do you like it?<select name="sweetness"><option value="low" <?= $preferences['sweetness'] === 'low' ? 'selected' : '' ?>>A little or no sweetness</option><option value="medium" <?= $preferences['sweetness'] === 'medium' ? 'selected' : '' ?>>Balanced</option><option value="high" <?= $preferences['sweetness'] === 'high' ? 'selected' : '' ?>>Sweet and indulgent</option></select></label>
      <label>Your preferred strength<select name="strength"><option value="mild" <?= $preferences['strength'] === 'mild' ? 'selected' : '' ?>>Mild and gentle</option><option value="balanced" <?= $preferences['strength'] === 'balanced' ? 'selected' : '' ?>>Balanced</option><option value="strong" <?= $preferences['strength'] === 'strong' ? 'selected' : '' ?>>Strong and bold</option></select></label>
      <label>Milk preference<select name="milk_preference"><option value="any" <?= $preferences['milk_preference'] === 'any' ? 'selected' : '' ?>>No preference</option><option value="dairy" <?= $preferences['milk_preference'] === 'dairy' ? 'selected' : '' ?>>Dairy milk</option><option value="oat" <?= $preferences['milk_preference'] === 'oat' ? 'selected' : '' ?>>Oat milk</option><option value="almond" <?= $preferences['milk_preference'] === 'almond' ? 'selected' : '' ?>>Almond milk</option><option value="soy" <?= $preferences['milk_preference'] === 'soy' ? 'selected' : '' ?>>Soy milk</option><option value="no_milk" <?= $preferences['milk_preference'] === 'no_milk' ? 'selected' : '' ?>>No milk</option></select></label>
      <label>Flavour you reach for<select name="preferred_flavour"><option value="any" <?= $preferences['preferred_flavour'] === 'any' ? 'selected' : '' ?>>Surprise me</option><option value="caramel" <?= $preferences['preferred_flavour'] === 'caramel' ? 'selected' : '' ?>>Caramel and toffee</option><option value="chocolate" <?= $preferences['preferred_flavour'] === 'chocolate' ? 'selected' : '' ?>>Chocolate and cocoa</option><option value="vanilla" <?= $preferences['preferred_flavour'] === 'vanilla' ? 'selected' : '' ?>>Vanilla and cream</option><option value="nutty" <?= $preferences['preferred_flavour'] === 'nutty' ? 'selected' : '' ?>>Nutty</option><option value="fruity" <?= $preferences['preferred_flavour'] === 'fruity' ? 'selected' : '' ?>>Fruity and citrus</option><option value="floral" <?= $preferences['preferred_flavour'] === 'floral' ? 'selected' : '' ?>>Floral and botanical</option><option value="earthy" <?= $preferences['preferred_flavour'] === 'earthy' ? 'selected' : '' ?>>Earthy</option></select></label>
      <label>Usual spend per item (₦)<input type="number" name="preferred_budget" min="0" max="1000000" step="100" value="<?= e($preferences['preferred_budget']) ?>" placeholder="e.g. 5000"></label>
      <button class="button button-dark" type="submit">Save my taste profile <span>↗</span></button>
    </form>
    <p class="personalization-privacy">Your preferences stay in your K.O.D account and are used to personalize menu suggestions. Birthday points are issued once per year when you visit your account during your birthday month.</p>
  </section>
  <section class="account-panel account-personalized-picks">
    <div class="account-panel-heading"><div><span class="eyebrow">A MENU THAT GETS YOU</span><h2>Picked for your taste.</h2><p class="personalization-intro">Suggestions are matched to your saved preferences, previous completed orders and favourites.</p></div><a class="text-link" href="<?= e(base_url('ai-barista.php')) ?>">Ask the AI barista ↗</a></div>
    <?php if (!$personalizedItems): ?><div class="account-empty">Your personalized menu will appear here when K.O.D products are available.</div><?php else: ?><div class="personalized-product-grid"><?php foreach ($personalizedItems as $item): ?><article class="personalized-product"><img src="<?= e($item['image_url'] ?: placeholder_image($item['category'])) ?>" alt="<?= e($item['name']) ?>" loading="lazy"><div class="personalized-product-body"><span class="eyebrow"><?= e(strtoupper($item['category'])) ?></span><h3><?= e($item['name']) ?></h3><p><?= e($item['recommendation_reason']) ?></p><div><strong><?= e(money($item['price'])) ?></strong><a href="<?= e(base_url('showroom.php?item=' . rawurlencode((string)$item['slug']))) ?>">Explore ↗</a></div><button class="add-to-bag" type="button" data-product-id="<?= (int)$item['id'] ?>" data-product-name="<?= e($item['name']) ?>" data-product-price="<?= e((string)$item['price']) ?>" data-product-image="<?= e($item['image_url'] ?: placeholder_image($item['category'])) ?>">Add to bag <span>+</span></button></div></article><?php endforeach; ?></div><?php endif; ?>
  </section>
  <section class="account-panel account-favorites-preview">
    <div class="account-panel-heading"><div><span class="eyebrow">YOUR PERSONAL SHORTLIST</span><h2>Saved favourites.</h2></div><a class="text-link" href="<?= e(base_url('favorites.php')) ?>">View all favourites (<?= count($favoriteIds) ?>) ↗</a></div>
    <?php if (!$favoriteItems): ?><div class="account-empty">Save a drink or dessert with the heart button on the menu and it will live here. <a href="<?= e(base_url('#menu')) ?>">Browse the menu.</a></div><?php else: ?><div class="favorite-chip-list"><?php foreach ($favoriteItems as $item): ?><a href="<?= e(base_url('showroom.php?item=' . rawurlencode((string)$item['slug']))) ?>"><span>♥</span><?= e($item['name']) ?><small><?= e(money($item['price'])) ?></small></a><?php endforeach; ?></div><?php endif; ?>
  </section>
  <section class="account-panel account-orders"><div class="account-panel-heading"><div><span class="eyebrow">FROM YOUR BAG TO OUR COUNTER</span><h2>Your orders</h2></div><a class="text-link" href="<?= e(base_url('order-history.php')) ?>">All recent orders ↗</a></div>
    <?php if (!$orders): ?><div class="account-empty">Your first order is waiting to happen. <a href="<?= e(base_url('#menu')) ?>">Explore the menu.</a></div><?php else: ?><div class="account-order-list"><?php foreach ($orders as $order): ?><article class="account-order-row"><div><strong><?= e($order['order_number']) ?></strong><small><?= e(date('d M Y · H:i', strtotime($order['created_at']))) ?> · <?= e(ucfirst($order['fulfillment'])) ?></small></div><span class="status-pill <?= e($order['status']) ?>"><?= e(str_replace('_', ' ', ucfirst($order['status']))) ?></span><strong><?= e(money($order['total'])) ?></strong><a class="text-link" href="<?= e(base_url('order-tracking.php?ref=' . $order['public_token'])) ?>">Track ↗</a></article><?php endforeach; ?></div><?php endif; ?>
  </section>
  <section class="account-panel account-rewards-history"><div class="account-panel-heading"><div><span class="eyebrow">EVERY POINT HAS A STORY</span><h2>Rewards activity</h2></div></div>
    <?php if (!$transactions): ?><div class="account-empty">Your rewards activity will appear here.</div><?php else: ?><div class="rewards-transaction-list"><?php foreach ($transactions as $transaction): ?><article><div><strong><?= e(ucwords(str_replace('_', ' ', $transaction['transaction_type']))) ?></strong><small><?= e($transaction['note']) ?> · <?= e(date('d M Y', strtotime($transaction['created_at']))) ?></small></div><strong class="points-delta <?= (int)$transaction['points_delta'] >= 0 ? 'positive' : 'negative' ?>"><?= (int)$transaction['points_delta'] > 0 ? '+' : '' ?><?= number_format((int)$transaction['points_delta']) ?> pts</strong></article><?php endforeach; ?></div><?php endif; ?>
  </section>
  <p class="account-footnote">Rewards are earned on completed orders only. Cancelled orders do not earn points. Points redeemed on a cancelled order are returned to your balance.</p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
