<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) $error = 'Your session expired. Refresh and try again.';
    else {
        $customerId = filter_var($_POST['customer_id'] ?? null, FILTER_VALIDATE_INT);
        $delta = filter_var($_POST['points_delta'] ?? null, FILTER_VALIDATE_INT);
        $note = trim((string)($_POST['note'] ?? ''));
        if ($customerId === false || $customerId < 1 || $delta === false || $delta === 0 || abs($delta) > 5000 || $note === '' || strlen($note) > 255) {
            $error = 'Enter a valid customer, a points change between -5,000 and 5,000 (not zero), and a short reason.';
        } else {
            try {
                $pdo = db(); $pdo->beginTransaction();
                $stmt = $pdo->prepare('SELECT id, loyalty_points FROM customers WHERE id = :id FOR UPDATE');
                $stmt->execute(['id' => $customerId]);
                $customer = $stmt->fetch();
                if (!$customer) { $pdo->rollBack(); $error = 'Customer account not found.'; }
                elseif ((int)$customer['loyalty_points'] + $delta < 0) { $pdo->rollBack(); $error = 'This adjustment would make the customer balance negative.'; }
                else {
                    $pdo->prepare('UPDATE customers SET loyalty_points = loyalty_points + :delta WHERE id = :id')->execute(['delta' => $delta, 'id' => $customerId]);
                    $tx = $pdo->prepare("INSERT INTO loyalty_transactions (customer_id, order_id, transaction_type, points_delta, naira_value, note) VALUES (:customer_id, NULL, 'adjustment', :delta, 0, :note)");
                    $tx->execute(['customer_id' => $customerId, 'delta' => $delta, 'note' => $note]);
                    $pdo->commit();
                    set_flash('success', 'Customer loyalty balance updated and recorded in the rewards ledger.');
                    redirect('admin/customers.php');
                }
            } catch (Throwable $exception) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
                error_log('Admin customer rewards adjustment failed: ' . $exception->getMessage());
                $error = 'Could not update that customer. Confirm the loyalty migration has been imported.';
            }
        }
    }
}
$q = trim((string)($_GET['q'] ?? ''));
$customers = [];
try {
    if ($q !== '') {
        $stmt = db()->prepare('SELECT id, full_name, email, phone, loyalty_points, created_at FROM customers WHERE full_name LIKE :q1 OR email LIKE :q2 OR phone LIKE :q3 ORDER BY created_at DESC LIMIT 100');
        $needle = '%' . $q . '%';
        $stmt->execute(['q1' => $needle, 'q2' => $needle, 'q3' => $needle]); $customers = $stmt->fetchAll();
    } else {
        $customers = db()->query('SELECT id, full_name, email, phone, loyalty_points, created_at FROM customers ORDER BY created_at DESC LIMIT 100')->fetchAll();
    }
} catch (Throwable $exception) {
    $error = 'Customer accounts are not available. Import database/add_customer_loyalty.sql into kod_cafe.';
    error_log('Admin customers query failed: ' . $exception->getMessage());
}
$adminPageTitle = 'K.O.D Club members';
require __DIR__ . '/includes/header.php';
?>
<section class="admin-panel">
  <div class="panel-heading"><div><span class="admin-overline">THE PEOPLE BEHIND EVERY POUR</span><h2>K.O.D Club <small><?= count($customers) ?> members shown</small></h2></div></div>
  <?php if ($error): ?><div class="admin-alert error"><?= e($error) ?></div><?php endif; ?>
  <form method="get" class="customer-search-form"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email or phone"><button class="admin-button" type="submit">Search members</button><?php if ($q !== ''): ?><a class="table-action" href="<?= e(base_url('admin/customers.php')) ?>">Clear</a><?php endif; ?></form>
  <?php if (!$customers): ?><div class="admin-empty">No K.O.D Club members found.</div><?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>MEMBER</th><th>CONTACT</th><th>POINTS BALANCE</th><th>MEMBER SINCE</th><th>GOODWILL ADJUSTMENT</th></tr></thead><tbody>
  <?php foreach ($customers as $member): ?><tr>
    <td><strong><?= e($member['full_name']) ?></strong><small>Member #<?= (int)$member['id'] ?></small></td>
    <td><?= e($member['email']) ?><small><?= e($member['phone']) ?></small></td>
    <td><strong><?= number_format((int)$member['loyalty_points']) ?> pts</strong><small>₦<?= number_format(intdiv((int)$member['loyalty_points'], 100) * 500) ?> potential reward value</small></td>
    <td><?= e(date('d M Y', strtotime($member['created_at']))) ?></td>
    <td><form method="post" class="member-adjust-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="customer_id" value="<?= (int)$member['id'] ?>"><input type="number" name="points_delta" min="-5000" max="5000" step="1" placeholder="+/- points" required><input type="text" name="note" maxlength="255" placeholder="Reason for adjustment" required><button class="table-action" type="submit">Adjust</button></form></td>
  </tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
  <p class="admin-help-note">Goodwill changes are recorded in the customer's rewards activity. Negative adjustments cannot reduce a balance below zero.</p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
