<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
$error = '';
$allowed = ['pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery', 'completed', 'cancelled'];
$paymentAllowed = ['unpaid', 'paid', 'refunded'];
$labels = ['pending'=>'Pending','confirmed'=>'Confirmed','preparing'=>'Preparing','ready'=>'Ready for pickup','out_for_delivery'=>'Out for delivery','completed'=>'Completed','cancelled'=>'Cancelled'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) $error = 'Your session expired. Refresh and try again.';
    else {
        $id = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
        $status = (string)($_POST['status'] ?? '');
        $etaInput = trim((string)($_POST['estimated_ready_at'] ?? ''));
        $paymentStatus = (string)($_POST['payment_status'] ?? 'unpaid');
        $etaValue = null;
        if ($id === false || $id < 1 || !in_array($status, $allowed, true) || !in_array($paymentStatus, $paymentAllowed, true)) $error = 'Choose a valid order, order status and payment status.';
        elseif ($etaInput !== '') {
            $etaDate = DateTime::createFromFormat('Y-m-d\\TH:i', $etaInput);
            $dateErrors = DateTime::getLastErrors();
            if (!$etaDate || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) $error = 'Enter a valid estimated ready or delivery time.';
            else $etaValue = $etaDate->format('Y-m-d H:i:00');
        }
        if ($error === '') {
            try {
                $pdo = db(); $pdo->beginTransaction();
                $currentStmt = $pdo->prepare('SELECT id, customer_id, status, payment_status, fulfillment, total, estimated_ready_at, loyalty_points_redeemed FROM orders WHERE id = :id FOR UPDATE');
                $currentStmt->execute(['id' => $id]); $current = $currentStmt->fetch();
                if (!$current) { $pdo->rollBack(); $error = 'That order could not be found.'; }
                elseif ($status === 'out_for_delivery' && $current['fulfillment'] !== 'delivery') { $pdo->rollBack(); $error = 'Only delivery orders can be marked out for delivery.'; }
                elseif ($status === 'ready' && $current['fulfillment'] === 'delivery') { $pdo->rollBack(); $error = 'Delivery orders should be marked out for delivery instead of ready for pickup.'; }
                elseif ($current['status'] === 'completed' && $status === 'cancelled') { $pdo->rollBack(); $error = 'A completed order cannot be cancelled. Contact the customer and record any refund separately.'; }
                elseif ($current['status'] === 'cancelled' && $status !== 'cancelled') { $pdo->rollBack(); $error = 'A cancelled order cannot be reopened. Create a new order if the customer still wants it.'; }
                elseif ($current['payment_status'] === 'refunded' && $paymentStatus !== 'refunded') { $pdo->rollBack(); $error = 'A refunded payment is final. Create a new order if another payment is due.'; }
                elseif ($current['payment_status'] === 'paid' && $paymentStatus === 'unpaid') { $pdo->rollBack(); $error = 'A recorded payment cannot be changed back to unpaid. Record a refund if money was returned.'; }
                elseif ($paymentStatus === 'refunded' && $current['payment_status'] !== 'paid' && $current['payment_status'] !== 'refunded') { $pdo->rollBack(); $error = 'Only a previously paid order can be marked refunded.'; }
                else {
                    $update = $pdo->prepare('UPDATE orders SET status = :status, estimated_ready_at = :eta, payment_status = :payment_status WHERE id = :id');
                    $update->execute(['status' => $status, 'eta' => $etaValue, 'payment_status' => $paymentStatus, 'id' => $id]);
                    if ($current['status'] !== $status) {
                        $history = $pdo->prepare('INSERT INTO order_status_history (order_id, from_status, to_status, note, changed_by_admin_id) VALUES (:order_id, :from_status, :to_status, :note, :admin_id)');
                        $history->execute(['order_id'=>$id,'from_status'=>$current['status'],'to_status'=>$status,'note'=>'Your order is now ' . strtolower($labels[$status]) . '.','admin_id'=>(int)$admin['id']]);
                    }
                    $customerId = $current['customer_id'] !== null ? (int)$current['customer_id'] : 0;
                    if ($customerId > 0 && $status === 'completed' && $current['status'] !== 'completed') {
                        $check = $pdo->prepare("SELECT id FROM loyalty_transactions WHERE order_id = :order_id AND transaction_type = 'earned' LIMIT 1");
                        $check->execute(['order_id' => $id]);
                        if (!$check->fetchColumn()) {
                            $earned = (int)floor((float)$current['total'] / 500);
                            if ($earned > 0) {
                                $pdo->prepare('UPDATE customers SET loyalty_points = loyalty_points + :points WHERE id = :id')->execute(['points'=>$earned,'id'=>$customerId]);
                                $reward = $pdo->prepare("INSERT INTO loyalty_transactions (customer_id, order_id, transaction_type, points_delta, naira_value, note) VALUES (:customer_id, :order_id, 'earned', :delta, 0, 'Points earned on completed order')");
                                $reward->execute(['customer_id'=>$customerId,'order_id'=>$id,'delta'=>$earned]);
                            }
                        }
                    }
                    if ($customerId > 0 && $status === 'cancelled' && $current['status'] !== 'cancelled' && (int)$current['loyalty_points_redeemed'] > 0) {
                        $check = $pdo->prepare("SELECT id FROM loyalty_transactions WHERE order_id = :order_id AND transaction_type = 'refund' LIMIT 1");
                        $check->execute(['order_id' => $id]);
                        if (!$check->fetchColumn()) {
                            $returned = (int)$current['loyalty_points_redeemed'];
                            $pdo->prepare('UPDATE customers SET loyalty_points = loyalty_points + :points WHERE id = :id')->execute(['points'=>$returned,'id'=>$customerId]);
                            $refund = $pdo->prepare("INSERT INTO loyalty_transactions (customer_id, order_id, transaction_type, points_delta, naira_value, note) VALUES (:customer_id, :order_id, 'refund', :delta, 0, 'Points returned because the order was cancelled')");
                            $refund->execute(['customer_id'=>$customerId,'order_id'=>$id,'delta'=>$returned]);
                        }
                    }
                    $pdo->commit();
                    set_flash('success', $status === 'completed' && $customerId > 0 ? 'Order updated. Eligible loyalty points are credited only once when completed.' : 'Order status, ETA and payment record updated.');
                    redirect('admin/orders.php');
                }
            } catch (Throwable $exception) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
                $error = 'Could not update this order. Check that the order tracking and loyalty migrations have been imported.';
                error_log('Order status update failed: ' . $exception->getMessage());
            }
        }
    }
}
try {
    $orders = db()->query('SELECT o.*, c.full_name AS account_name, c.email AS account_email, (SELECT GROUP_CONCAT(CONCAT(oi.quantity, " × ", oi.item_name) ORDER BY oi.id SEPARATOR ", ") FROM order_items oi WHERE oi.order_id = o.id) AS item_summary FROM orders o LEFT JOIN customers c ON c.id = o.customer_id ORDER BY o.created_at DESC LIMIT 100')->fetchAll();
} catch (Throwable $exception) {
    $orders = []; $error = 'Order desk unavailable. Import the online-order, tracking and loyalty database migrations in order.';
    error_log('Admin orders load failed: ' . $exception->getMessage());
}
$adminPageTitle = 'Customer orders';
require __DIR__ . '/includes/header.php';
?>
<section class="admin-panel">
  <div class="panel-heading"><div><span class="admin-overline">FROM THEIR BAG TO YOUR COUNTER</span><h2>Order desk <small><?= count($orders) ?> recent orders</small></h2></div></div><p class="admin-help-note">Record a payment as <strong>Paid</strong> only after cash or transfer has been received. Mark <strong>Refunded</strong> only after returning the customer's money. Completed orders are not automatically counted as paid.</p>
  <?php if ($error): ?><div class="admin-alert error"><?= e($error) ?></div><?php endif; ?>
  <?php if (!$orders): ?><div class="admin-empty">No orders yet. Customer orders from your website will appear here.</div><?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>ORDER</th><th>CUSTOMER</th><th>ITEMS</th><th>TOTAL</th><th>FULFILMENT</th><th>STATUS</th><th>UPDATE STATUS, ETA & PAYMENT</th></tr></thead><tbody>
  <?php foreach ($orders as $order): ?>
    <tr>
      <td><strong><?= e($order['order_number']) ?></strong><small><?= e(date('d M Y, H:i', strtotime($order['created_at']))) ?></small><a class="table-action" href="<?= e(base_url('order-tracking.php?ref=' . $order['public_token'])) ?>" target="_blank" rel="noopener">Open tracking ↗</a></td>
      <td><strong><?= e($order['customer_name']) ?></strong><?php if (!empty($order['account_name'])): ?><small class="account-order-badge">K.O.D Club member</small><?php endif; ?><small><?= e($order['customer_phone']) ?></small><small><?= e($order['customer_email']) ?></small></td>
      <td><?= e($order['item_summary'] ?? 'Order items') ?></td>
      <td><strong><?= e(money($order['total'])) ?></strong><?php if ((float)$order['discount_amount'] > 0): ?><small>Saved <?= e(money($order['discount_amount'])) ?></small><?php endif; ?><small>Payment: <?= e(ucfirst($order['payment_status'])) ?></small></td>
      <td><?= e(ucfirst($order['fulfillment'])) ?><?php if ($order['fulfillment'] === 'delivery' && $order['delivery_address']): ?><small><?= e($order['delivery_address']) ?></small><?php endif; ?></td>
      <td><span class="status-pill <?= e($order['status']) ?>"><?= e($labels[$order['status']] ?? $order['status']) ?></span><?php if (!empty($order['estimated_ready_at'])): ?><small>ETA: <?= e(date('d M, H:i', strtotime($order['estimated_ready_at']))) ?></small><?php endif; ?><?php if ($order['status'] === 'completed' && !empty($order['customer_id'])): ?><small>Loyalty earned on completion</small><?php endif; ?></td>
      <td><form method="post" class="order-status-form order-tracking-admin-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>"><select name="status" aria-label="Order status"><?php foreach ($allowed as $status): if (($status === 'out_for_delivery' && $order['fulfillment'] !== 'delivery') || ($status === 'ready' && $order['fulfillment'] === 'delivery')) continue; ?><option value="<?= e($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= e($labels[$status]) ?></option><?php endforeach; ?></select><label class="admin-eta-label">Estimated time<input type="datetime-local" name="estimated_ready_at" value="<?= !empty($order['estimated_ready_at']) ? e(date('Y-m-d\\TH:i', strtotime($order['estimated_ready_at']))) : '' ?>"></label><label class="admin-eta-label">Payment status<select name="payment_status" aria-label="Payment status"><?php foreach ($paymentAllowed as $paymentOption): ?><option value="<?= e($paymentOption) ?>" <?= $order['payment_status'] === $paymentOption ? 'selected' : '' ?>><?= e(ucfirst($paymentOption)) ?></option><?php endforeach; ?></select></label><button class="table-action" type="submit">Save update</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
