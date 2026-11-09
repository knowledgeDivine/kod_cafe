<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/customer_auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
$data = request_json();
if (!csrf_check($data['csrf_token'] ?? null)) json_response(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 419);
$name = trim((string)($data['customer_name'] ?? ''));
$email = strtolower(trim((string)($data['customer_email'] ?? '')));
$phone = trim((string)($data['customer_phone'] ?? ''));
$fulfillment = (string)($data['fulfillment'] ?? 'pickup');
$address = trim((string)($data['delivery_address'] ?? ''));
$note = trim((string)($data['customer_note'] ?? ''));
$rawItems = $data['items'] ?? [];
$length = static fn(string $value): int => function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
if ($name === '' || $length($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $length($email) > 190 || $phone === '' || $length($phone) > 40) json_response(['success' => false, 'message' => 'Enter your name, a valid email address and phone number.'], 422);
if (!in_array($fulfillment, ['pickup', 'delivery'], true)) json_response(['success' => false, 'message' => 'Choose pickup or delivery.'], 422);
if ($fulfillment === 'delivery' && ($address === '' || $length($address) > 500)) json_response(['success' => false, 'message' => 'Enter a delivery address of up to 500 characters.'], 422);
if ($length($note) > 1000 || !is_array($rawItems) || count($rawItems) < 1 || count($rawItems) > 30) json_response(['success' => false, 'message' => 'Your bag is empty or contains too many items.'], 422);
$quantities = [];
foreach ($rawItems as $row) {
    if (!is_array($row)) continue;
    $id = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT);
    $qty = filter_var($row['quantity'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || !$qty || $id < 1 || $qty < 1 || $qty > 20) json_response(['success' => false, 'message' => 'One of the quantities in your bag is invalid.'], 422);
    $quantities[$id] = min(20, ($quantities[$id] ?? 0) + $qty);
}
if (!$quantities) json_response(['success' => false, 'message' => 'Your bag is empty.'], 422);
try {
    $ids = array_keys($quantities);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT id, name, price FROM menu_items WHERE is_available = 1 AND id IN ($placeholders)");
    $stmt->execute($ids);
    $products = $stmt->fetchAll();
    if (count($products) !== count($quantities)) json_response(['success' => false, 'message' => 'A product in your bag is no longer available. Refresh your bag and try again.'], 409);
    $subtotal = 0.0; $orderRows = [];
    foreach ($products as $product) {
        $qty = $quantities[(int)$product['id']];
        $unit = (float)$product['price']; $line = $unit * $qty; $subtotal += $line;
        $orderRows[] = ['id' => (int)$product['id'], 'name' => $product['name'], 'unit' => $unit, 'quantity' => $qty, 'line' => $line];
    }
    $pdo = db(); $pdo->beginTransaction();
    $customerId = null; $discount = 0.0; $redeemedPoints = 0;
    $customer = current_customer();
    if ($customer) {
        $customerStmt = $pdo->prepare('SELECT id, loyalty_points FROM customers WHERE id = :id FOR UPDATE');
        $customerStmt->execute(['id' => (int)$customer['id']]);
        $lockedCustomer = $customerStmt->fetch();
        if ($lockedCustomer) {
            $customerId = (int)$lockedCustomer['id'];
            if ((string)($data['redeem_points'] ?? '') === '1') {
                // Reward rule: 100 points = ₦500, applied in 100-point blocks.
                $blocksFromBalance = intdiv((int)$lockedCustomer['loyalty_points'], 100);
                $blocksFromSubtotal = (int)floor($subtotal / 500);
                $blocks = min($blocksFromBalance, $blocksFromSubtotal);
                $redeemedPoints = $blocks * 100;
                $discount = $blocks * 500.0;
            }
        }
    }
    $total = max(0.0, $subtotal - $discount);
    $orderNumber = 'KOD-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $publicToken = bin2hex(random_bytes(32));
    $paymentMethod = $fulfillment === 'delivery' ? 'pay_on_delivery' : 'pay_on_pickup';
    $insert = $pdo->prepare('INSERT INTO orders (customer_id, order_number, public_token, customer_name, customer_email, customer_phone, fulfillment, delivery_address, customer_note, subtotal, discount_amount, loyalty_points_redeemed, total, payment_method) VALUES (:customer_id, :number, :token, :name, :email, :phone, :fulfillment, :address, :note, :subtotal, :discount, :points_redeemed, :total, :payment)');
    $insert->execute(['customer_id'=>$customerId,'number'=>$orderNumber,'token'=>$publicToken,'name'=>$name,'email'=>$email,'phone'=>$phone,'fulfillment'=>$fulfillment,'address'=>$fulfillment === 'delivery' ? $address : null,'note'=>$note ?: null,'subtotal'=>$subtotal,'discount'=>$discount,'points_redeemed'=>$redeemedPoints,'total'=>$total,'payment'=>$paymentMethod]);
    $orderId = (int)$pdo->lastInsertId();
    $insertItem = $pdo->prepare('INSERT INTO order_items (order_id, menu_item_id, item_name, unit_price, quantity, line_total) VALUES (:order_id, :menu_item_id, :item_name, :unit_price, :quantity, :line_total)');
    foreach ($orderRows as $row) $insertItem->execute(['order_id'=>$orderId,'menu_item_id'=>$row['id'],'item_name'=>$row['name'],'unit_price'=>$row['unit'],'quantity'=>$row['quantity'],'line_total'=>$row['line']]);
    $history = $pdo->prepare('INSERT INTO order_status_history (order_id, from_status, to_status, note) VALUES (:order_id, NULL, :status, :note)');
    $history->execute(['order_id' => $orderId, 'status' => 'pending', 'note' => 'Your order has been received by K.O.D.']);
    if ($redeemedPoints > 0 && $customerId !== null) {
        $pdo->prepare('UPDATE customers SET loyalty_points = loyalty_points - :points WHERE id = :id')->execute(['points' => $redeemedPoints, 'id' => $customerId]);
        $reward = $pdo->prepare("INSERT INTO loyalty_transactions (customer_id, order_id, transaction_type, points_delta, naira_value, note) VALUES (:customer_id, :order_id, 'redeemed', :delta, :value, 'Points redeemed at checkout')");
        $reward->execute(['customer_id' => $customerId, 'order_id' => $orderId, 'delta' => -$redeemedPoints, 'value' => $discount]);
    }
    $pdo->commit();

    $sessionTokens = $_SESSION['kod_order_tokens'] ?? [];
    if (!is_array($sessionTokens)) $sessionTokens = [];
    array_unshift($sessionTokens, $publicToken);
    $_SESSION['kod_order_tokens'] = array_slice(array_values(array_unique($sessionTokens)), 0, 20);
    json_response(['success'=>true,'message'=>'Your K.O.D order has been received.','order_number'=>$orderNumber,'discount_amount'=>$discount,'loyalty_points_redeemed'=>$redeemedPoints,'redirect'=>base_url('order-confirmation.php?ref='.$publicToken)]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Order creation failed: '.$exception->getMessage());
    json_response(['success'=>false,'message'=>'We could not place your order right now. Please check your connection and try again.'], 500);
}
