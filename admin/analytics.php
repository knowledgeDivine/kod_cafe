<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

function kod_valid_date(string $value, string $fallback): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : $fallback;
}

function kod_money(float|int|string|null $value): string
{
    return '₦' . number_format((float)$value, 2);
}

function kod_csv_cell(mixed $value): string
{
    $value = (string)$value;
    // Prevent spreadsheet formula execution when an exported CSV is opened.
    if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) $value = "'" . $value;
    return $value;
}

$today = new DateTimeImmutable('today');
$period = (string)($_GET['period'] ?? '30d');
$validPeriods = ['7d', '30d', '90d', 'ytd', 'custom'];
if (!in_array($period, $validPeriods, true)) $period = '30d';
$defaultStart = $today->modify('-29 days')->format('Y-m-d');
$defaultEnd = $today->format('Y-m-d');

if ($period === '7d') {
    $startDate = $today->modify('-6 days')->format('Y-m-d');
    $endDate = $defaultEnd;
} elseif ($period === '90d') {
    $startDate = $today->modify('-89 days')->format('Y-m-d');
    $endDate = $defaultEnd;
} elseif ($period === 'ytd') {
    $startDate = $today->setDate((int)$today->format('Y'), 1, 1)->format('Y-m-d');
    $endDate = $defaultEnd;
} elseif ($period === 'custom') {
    $startDate = kod_valid_date((string)($_GET['start'] ?? ''), $defaultStart);
    $endDate = kod_valid_date((string)($_GET['end'] ?? ''), $defaultEnd);
    if ($startDate > $endDate) [$startDate, $endDate] = [$endDate, $startDate];
    // Keep reports bounded to one year for performance and readable charts.
    $candidateStart = (new DateTimeImmutable($endDate))->modify('-364 days')->format('Y-m-d');
    if ($startDate < $candidateStart) $startDate = $candidateStart;
} else {
    $startDate = $defaultStart;
    $endDate = $defaultEnd;
}

$startAt = $startDate . ' 00:00:00';
$endExclusive = (new DateTimeImmutable($endDate))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
$rangeDays = max(1, (int)(new DateTimeImmutable($startDate))->diff(new DateTimeImmutable($endDate))->days + 1);
$rangeLabel = (new DateTimeImmutable($startDate))->format('M j, Y') . ' – ' . (new DateTimeImmutable($endDate))->format('M j, Y');
$queryParams = ['start' => $startAt, 'end' => $endExclusive];

$summary = [
    'all_orders' => 0, 'valid_orders' => 0, 'cancelled_orders' => 0,
    'gross_value' => 0, 'paid_revenue' => 0, 'fulfilled_value' => 0,
    'average_order' => 0, 'unique_buyers' => 0,
];
$trendRows = $statusRows = $categoryRows = $productRows = $signupRows = $customerRows = $paymentRows = [];
$repeatBuyers = 0;
$loyaltySummary = ['earned' => 0, 'redeemed' => 0];
$reportError = null;

try {
    $pdo = db();
    $summaryStmt = $pdo->prepare("SELECT
        COUNT(*) AS all_orders,
        SUM(CASE WHEN status <> 'cancelled' THEN 1 ELSE 0 END) AS valid_orders,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
        COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN total ELSE 0 END), 0) AS gross_value,
        COALESCE(SUM(CASE WHEN status <> 'cancelled' AND payment_status = 'paid' THEN total ELSE 0 END), 0) AS paid_revenue,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN total ELSE 0 END), 0) AS fulfilled_value,
        COALESCE(AVG(CASE WHEN status <> 'cancelled' THEN total END), 0) AS average_order,
        COUNT(DISTINCT CASE WHEN status <> 'cancelled' THEN LOWER(customer_email) END) AS unique_buyers
        FROM orders WHERE created_at >= :start AND created_at < :end");
    $summaryStmt->execute($queryParams);
    $summary = array_merge($summary, $summaryStmt->fetch() ?: []);

    $trendStmt = $pdo->prepare("SELECT DATE(created_at) AS sales_day,
        COUNT(*) AS order_count,
        COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN total ELSE 0 END), 0) AS gross_value,
        COALESCE(SUM(CASE WHEN status <> 'cancelled' AND payment_status = 'paid' THEN total ELSE 0 END), 0) AS paid_value
        FROM orders WHERE created_at >= :start AND created_at < :end
        GROUP BY DATE(created_at) ORDER BY sales_day");
    $trendStmt->execute($queryParams);
    $trendRows = $trendStmt->fetchAll();

    $statusStmt = $pdo->prepare("SELECT status, COUNT(*) AS total FROM orders
        WHERE created_at >= :start AND created_at < :end GROUP BY status ORDER BY total DESC");
    $statusStmt->execute($queryParams);
    $statusRows = $statusStmt->fetchAll();

    $categoryStmt = $pdo->prepare("SELECT COALESCE(mi.category, 'other') AS category,
        SUM(oi.quantity) AS units,
        COALESCE(SUM(oi.line_total), 0) AS item_value
        FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id
        LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
        WHERE o.created_at >= :start AND o.created_at < :end AND o.status <> 'cancelled'
        GROUP BY COALESCE(mi.category, 'other') ORDER BY units DESC");
    $categoryStmt->execute($queryParams);
    $categoryRows = $categoryStmt->fetchAll();

    $productStmt = $pdo->prepare("SELECT oi.item_name,
        SUM(oi.quantity) AS units,
        COALESCE(SUM(oi.line_total), 0) AS item_value
        FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id
        WHERE o.created_at >= :start AND o.created_at < :end AND o.status <> 'cancelled'
        GROUP BY oi.item_name ORDER BY units DESC, item_value DESC LIMIT 8");
    $productStmt->execute($queryParams);
    $productRows = $productStmt->fetchAll();

    $signupStmt = $pdo->prepare("SELECT DATE(created_at) AS signup_day, COUNT(*) AS total
        FROM customers WHERE created_at >= :start AND created_at < :end
        GROUP BY DATE(created_at) ORDER BY signup_day");
    $signupStmt->execute($queryParams);
    $signupRows = $signupStmt->fetchAll();

    $customerStmt = $pdo->prepare("SELECT MAX(customer_name) AS customer_name,
        customer_email, COUNT(*) AS order_count,
        COALESCE(SUM(total), 0) AS spend,
        MAX(created_at) AS last_order
        FROM orders WHERE created_at >= :start AND created_at < :end AND status <> 'cancelled'
        GROUP BY LOWER(customer_email), customer_email
        ORDER BY spend DESC, order_count DESC LIMIT 8");
    $customerStmt->execute($queryParams);
    $customerRows = $customerStmt->fetchAll();

    $repeatStmt = $pdo->prepare("SELECT COUNT(*) FROM (
        SELECT LOWER(customer_email) AS email_key FROM orders
        WHERE created_at >= :start AND created_at < :end AND status <> 'cancelled'
        GROUP BY LOWER(customer_email) HAVING COUNT(*) >= 2
    ) AS repeat_list");
    $repeatStmt->execute($queryParams);
    $repeatBuyers = (int)$repeatStmt->fetchColumn();

    $loyaltyStmt = $pdo->prepare("SELECT
        COALESCE(SUM(CASE WHEN transaction_type = 'earned' AND points_delta > 0 THEN points_delta ELSE 0 END), 0) AS earned,
        COALESCE(SUM(CASE WHEN transaction_type = 'redeemed' THEN ABS(points_delta) ELSE 0 END), 0) AS redeemed
        FROM loyalty_transactions WHERE created_at >= :start AND created_at < :end");
    $loyaltyStmt->execute($queryParams);
    $loyaltySummary = array_merge($loyaltySummary, $loyaltyStmt->fetch() ?: []);

    $paymentStmt = $pdo->prepare("SELECT payment_status, COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN total ELSE 0 END), 0) AS order_value
        FROM orders WHERE created_at >= :start AND created_at < :end
        GROUP BY payment_status ORDER BY total DESC");
    $paymentStmt->execute($queryParams);
    $paymentRows = $paymentStmt->fetchAll();

    if (($_GET['export'] ?? '') === 'orders') {
        $exportStmt = $pdo->prepare("SELECT order_number, created_at, customer_name, customer_email,
            customer_phone, fulfillment, status, payment_status, subtotal, discount_amount, total
            FROM orders WHERE created_at >= :start AND created_at < :end
            ORDER BY created_at DESC");
        $exportStmt->execute($queryParams);
        $filename = 'kod-orders-' . $startDate . '-to-' . $endDate . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Order number', 'Created at', 'Customer', 'Email', 'Phone', 'Fulfilment', 'Order status', 'Payment status', 'Subtotal', 'Discount', 'Total']);
        while ($row = $exportStmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, array_map('kod_csv_cell', array_values($row)));
        }
        fclose($out);
        exit;
    }
} catch (Throwable $exception) {
    error_log('K.O.D analytics query failed: ' . $exception->getMessage());
    $reportError = 'Analytics could not be loaded. Confirm that the online ordering and customer loyalty database upgrades are installed.';
}

$trendMap = [];
foreach ($trendRows as $row) $trendMap[(string)$row['sales_day']] = $row;
$trend = [];
$cursor = new DateTimeImmutable($startDate);
$last = new DateTimeImmutable($endDate);
while ($cursor <= $last) {
    $key = $cursor->format('Y-m-d');
    $trend[] = [
        'date' => $key,
        'orders' => (int)($trendMap[$key]['order_count'] ?? 0),
        'gross' => (float)($trendMap[$key]['gross_value'] ?? 0),
        'paid' => (float)($trendMap[$key]['paid_value'] ?? 0),
    ];
    $cursor = $cursor->modify('+1 day');
}

$chartWidth = 820;
$chartHeight = 250;
$chartLeft = 54;
$chartRight = 18;
$chartTop = 18;
$chartBottom = 38;
$plotWidth = $chartWidth - $chartLeft - $chartRight;
$plotHeight = $chartHeight - $chartTop - $chartBottom;
$maxTrend = 0.0;
foreach ($trend as $point) $maxTrend = max($maxTrend, $point['gross'], $point['paid']);
$maxTrend = $maxTrend > 0 ? $maxTrend * 1.12 : 1;
$grossPoints = $paidPoints = [];
foreach ($trend as $index => $point) {
    $x = $chartLeft + (count($trend) > 1 ? ($index / (count($trend) - 1)) * $plotWidth : $plotWidth / 2);
    $grossY = $chartTop + $plotHeight - ($point['gross'] / $maxTrend) * $plotHeight;
    $paidY = $chartTop + $plotHeight - ($point['paid'] / $maxTrend) * $plotHeight;
    $grossPoints[] = number_format($x, 2, '.', '') . ',' . number_format($grossY, 2, '.', '');
    $paidPoints[] = number_format($x, 2, '.', '') . ',' . number_format($paidY, 2, '.', '');
}
$maxUnits = 1;
foreach ($categoryRows as $row) $maxUnits = max($maxUnits, (int)$row['units']);
$maxStatus = 1;
foreach ($statusRows as $row) $maxStatus = max($maxStatus, (int)$row['total']);
$maxSignups = 1;
foreach ($signupRows as $row) $maxSignups = max($maxSignups, (int)$row['total']);
$repeatRate = (int)$summary['unique_buyers'] > 0 ? ($repeatBuyers / (int)$summary['unique_buyers']) * 100 : 0;
$cancelRate = (int)$summary['all_orders'] > 0 ? ((int)$summary['cancelled_orders'] / (int)$summary['all_orders']) * 100 : 0;
$adminPageTitle = 'Business analytics';
$adminExtraCss = 'assets/css/analytics.css';
require __DIR__ . '/includes/header.php';
?>
<section class="analytics-intro">
    <div><span class="admin-overline">THE BUSINESS AT A GLANCE</span><h2>Numbers behind every pour.</h2><p>Sales performance, customer behaviour and product demand for <?= e($rangeLabel) ?>.</p></div>
    <a class="admin-button" href="<?= e(base_url('admin/analytics.php?' . http_build_query(['period' => $period, 'start' => $startDate, 'end' => $endDate, 'export' => 'orders']))) ?>">↓ Export orders CSV</a>
</section>
<?php if ($reportError): ?><div class="admin-alert error"><?= e($reportError) ?></div><?php endif; ?>
<section class="analytics-filter admin-panel">
    <form method="get" class="analytics-filter-form">
        <label>REPORT RANGE
            <select name="period" id="analytics-period">
                <option value="7d" <?= $period === '7d' ? 'selected' : '' ?>>Last 7 days</option>
                <option value="30d" <?= $period === '30d' ? 'selected' : '' ?>>Last 30 days</option>
                <option value="90d" <?= $period === '90d' ? 'selected' : '' ?>>Last 90 days</option>
                <option value="ytd" <?= $period === 'ytd' ? 'selected' : '' ?>>Year to date</option>
                <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Custom dates</option>
            </select>
        </label>
        <label>FROM <input type="date" name="start" value="<?= e($startDate) ?>" max="<?= e($endDate) ?>"></label>
        <label>TO <input type="date" name="end" value="<?= e($endDate) ?>" min="<?= e($startDate) ?>"></label>
        <button class="admin-button" type="submit">Apply filters</button>
    </form>
    <span class="analytics-range-note">Up to 365 days per report</span>
</section>

<section class="analytics-kpis">
    <article class="analytics-kpi featured"><span>PAID REVENUE</span><strong><?= e(kod_money($summary['paid_revenue'])) ?></strong><small>Orders marked as paid only</small></article>
    <article class="analytics-kpi"><span>FULFILLED ORDER VALUE</span><strong><?= e(kod_money($summary['fulfilled_value'])) ?></strong><small>Orders marked completed</small></article>
    <article class="analytics-kpi"><span>VALID ORDERS</span><strong><?= number_format((int)$summary['valid_orders']) ?></strong><small><?= number_format((int)$summary['cancelled_orders']) ?> cancelled in this period</small></article>
    <article class="analytics-kpi"><span>AVERAGE ORDER VALUE</span><strong><?= e(kod_money($summary['average_order'])) ?></strong><small>Excludes cancelled orders</small></article>
    <article class="analytics-kpi"><span>UNIQUE BUYERS</span><strong><?= number_format((int)$summary['unique_buyers']) ?></strong><small>Identified by order email</small></article>
    <article class="analytics-kpi"><span>CANCELLATION RATE</span><strong><?= number_format($cancelRate, 1) ?>%</strong><small><?= number_format((int)$summary['cancelled_orders']) ?> of <?= number_format((int)$summary['all_orders']) ?> orders</small></article>
    <article class="analytics-kpi"><span>LOYALTY POINTS EARNED</span><strong><?= number_format((int)$loyaltySummary['earned']) ?></strong><small>Credited on completed orders</small></article>
    <article class="analytics-kpi"><span>LOYALTY POINTS REDEEMED</span><strong><?= number_format((int)$loyaltySummary['redeemed']) ?></strong><small>Used for customer discounts</small></article>
</section>

<section class="analytics-grid analytics-grid-main">
    <article class="admin-panel analytics-panel analytics-trend-panel">
        <div class="panel-heading"><div><span class="admin-overline">ORDER VALUE OVER TIME</span><h2>Daily performance</h2></div><span class="analytics-period-tag"><?= e($rangeLabel) ?></span></div>
        <div class="chart-legend"><span><i class="legend-dot gross"></i> Order value (not cancelled)</span><span><i class="legend-dot paid"></i> Paid revenue</span></div>
        <?php if (array_sum(array_column($trend, 'orders')) === 0): ?><div class="analytics-empty">No orders in this period yet. New order activity will appear here.</div><?php else: ?>
        <div class="svg-chart-wrap"><svg class="analytics-line-chart" viewBox="0 0 <?= $chartWidth ?> <?= $chartHeight ?>" role="img" aria-label="Daily order value and paid revenue line chart">
            <?php for ($i = 0; $i <= 4; $i++): $y = $chartTop + ($plotHeight / 4) * $i; $value = $maxTrend * (1 - $i / 4); ?>
                <line x1="<?= $chartLeft ?>" y1="<?= number_format($y, 2, '.', '') ?>" x2="<?= $chartWidth - $chartRight ?>" y2="<?= number_format($y, 2, '.', '') ?>" class="chart-gridline" />
                <text x="<?= $chartLeft - 9 ?>" y="<?= number_format($y + 4, 2, '.', '') ?>" text-anchor="end" class="chart-axis-label">₦<?= e(number_format($value / 1000, 0)) ?>k</text>
            <?php endfor; ?>
            <polyline points="<?= e(implode(' ', $grossPoints)) ?>" class="chart-line chart-line-gross" />
            <polyline points="<?= e(implode(' ', $paidPoints)) ?>" class="chart-line chart-line-paid" />
            <?php foreach ($trend as $index => $point): $x = $chartLeft + (count($trend) > 1 ? ($index / (count($trend) - 1)) * $plotWidth : $plotWidth / 2); $gy = $chartTop + $plotHeight - ($point['gross'] / $maxTrend) * $plotHeight; $py = $chartTop + $plotHeight - ($point['paid'] / $maxTrend) * $plotHeight; ?>
                <circle cx="<?= number_format($x, 2, '.', '') ?>" cy="<?= number_format($gy, 2, '.', '') ?>" r="3.2" class="chart-point-gross"><title><?= e($point['date'] . ' · Order value ' . kod_money($point['gross'])) ?></title></circle>
                <circle cx="<?= number_format($x, 2, '.', '') ?>" cy="<?= number_format($py, 2, '.', '') ?>" r="2.8" class="chart-point-paid"><title><?= e($point['date'] . ' · Paid ' . kod_money($point['paid'])) ?></title></circle>
            <?php endforeach; ?>
            <?php
            $labelIndexes = array_unique([0, (int)floor((count($trend) - 1) / 2), count($trend) - 1]);
            foreach ($labelIndexes as $labelIndex): $lx = $chartLeft + (count($trend) > 1 ? ($labelIndex / (count($trend) - 1)) * $plotWidth : $plotWidth / 2); ?>
                <text x="<?= number_format($lx, 2, '.', '') ?>" y="<?= $chartHeight - 9 ?>" text-anchor="middle" class="chart-axis-label"><?= e((new DateTimeImmutable($trend[$labelIndex]['date']))->format('M j')) ?></text>
            <?php endforeach; ?>
        </svg></div><?php endif; ?>
        <p class="analytics-footnote">Order value is not the same as cash collected. Only orders marked <strong>Paid</strong> count toward paid revenue. Product category and item values use the original order-item totals before order-level loyalty discounts.</p>
    </article>

    <article class="admin-panel analytics-panel">
        <div class="panel-heading"><div><span class="admin-overline">CUSTOMER LOYALTY</span><h2>Repeat buyers</h2></div></div>
        <div class="repeat-metric"><strong><?= number_format($repeatBuyers) ?></strong><span>buyers with 2+ orders in this date range</span></div>
        <div class="repeat-track"><span style="width:<?= number_format(min(100, $repeatRate), 1, '.', '') ?>%"></span></div>
        <div class="repeat-bottom"><span>Repeat buyer share</span><strong><?= number_format($repeatRate, 1) ?>%</strong></div>
        <div class="analytics-mini-divider"></div>
        <div class="mini-stat"><span>New K.O.D Club members</span><strong><?= number_format(array_sum(array_map(static fn($r) => (int)$r['total'], $signupRows))) ?></strong></div>
        <div class="mini-stat"><span>Average order value</span><strong><?= e(kod_money($summary['average_order'])) ?></strong></div>
    </article>
</section>

<section class="analytics-grid">
    <article class="admin-panel analytics-panel">
        <div class="panel-heading"><div><span class="admin-overline">WHAT SELLS</span><h2>Category performance</h2></div></div>
        <?php if (!$categoryRows): ?><div class="analytics-empty">Product sales by category will appear here after orders are placed.</div><?php else: ?><div class="horizontal-bars">
            <?php foreach ($categoryRows as $row): $width = ((int)$row['units'] / $maxUnits) * 100; ?>
                <div class="hbar-row"><div class="hbar-meta"><span><?= e(ucfirst((string)$row['category'])) ?></span><strong><?= number_format((int)$row['units']) ?> units <small>· <?= e(kod_money($row['item_value'])) ?></small></strong></div><div class="hbar-track"><span style="width:<?= number_format($width, 1, '.', '') ?>%"></span></div></div>
            <?php endforeach; ?>
        </div><?php endif; ?>
    </article>
    <article class="admin-panel analytics-panel">
        <div class="panel-heading"><div><span class="admin-overline">ORDER WORKFLOW</span><h2>Orders by status</h2></div></div>
        <?php if (!$statusRows): ?><div class="analytics-empty">No order statuses to report yet.</div><?php else: ?><div class="horizontal-bars status-bars">
            <?php foreach ($statusRows as $row): $width = ((int)$row['total'] / $maxStatus) * 100; ?>
                <div class="hbar-row"><div class="hbar-meta"><span><?= e(ucwords(str_replace('_', ' ', (string)$row['status']))) ?></span><strong><?= number_format((int)$row['total']) ?></strong></div><div class="hbar-track"><span style="width:<?= number_format($width, 1, '.', '') ?>%"></span></div></div>
            <?php endforeach; ?>
        </div><?php endif; ?>
    </article>
</section>

<section class="analytics-grid">
    <article class="admin-panel analytics-panel">
        <div class="panel-heading"><div><span class="admin-overline">CUSTOMER ACQUISITION</span><h2>New member sign-ups</h2></div></div>
        <?php if (!$signupRows): ?><div class="analytics-empty">New customer accounts will appear here.</div><?php else: ?><div class="signup-bars">
            <?php foreach ($signupRows as $row): $height = max(5, ((int)$row['total'] / $maxSignups) * 100); ?>
                <div class="signup-bar-item"><strong><?= (int)$row['total'] ?></strong><span class="signup-bar"><i style="height:<?= number_format($height, 1, '.', '') ?>%"></i></span><small><?= e((new DateTimeImmutable((string)$row['signup_day']))->format($rangeDays > 45 ? 'M j' : 'j M')) ?></small></div>
            <?php endforeach; ?>
        </div><?php endif; ?>
    </article>
    <article class="admin-panel analytics-panel">
        <div class="panel-heading"><div><span class="admin-overline">PAYMENT COLLECTION</span><h2>Payment status</h2></div></div>
        <?php if (!$paymentRows): ?><div class="analytics-empty">Payment records will appear here.</div><?php else: ?><div class="payment-status-list">
            <?php foreach ($paymentRows as $row): ?>
                <div class="payment-status-row"><span class="status-pill <?= e((string)$row['payment_status']) ?>"><?= e(ucfirst((string)$row['payment_status'])) ?></span><strong><?= number_format((int)$row['total']) ?> orders</strong><small><?= e(kod_money($row['order_value'])) ?> order value</small></div>
            <?php endforeach; ?>
        </div><?php endif; ?>
        <p class="analytics-footnote">Record payments in the order desk when customers pay at pickup or delivery. This dashboard does not infer payment from order completion.</p>
    </article>
</section>

<section class="admin-panel analytics-panel">
    <div class="panel-heading"><div><span class="admin-overline">MENU INTELLIGENCE</span><h2>Best-selling products</h2></div><a href="<?= e(base_url('admin/menu.php')) ?>">Manage menu ↗</a></div>
    <?php if (!$productRows): ?><div class="analytics-empty">No product sales in this period yet.</div><?php else: ?><div class="table-wrap"><table class="analytics-table"><thead><tr><th>PRODUCT</th><th>UNITS SOLD</th><th>ITEM VALUE</th></tr></thead><tbody>
        <?php foreach ($productRows as $row): ?><tr><td><strong><?= e((string)$row['item_name']) ?></strong></td><td><?= number_format((int)$row['units']) ?></td><td><strong><?= e(kod_money($row['item_value'])) ?></strong></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>

<section class="admin-panel analytics-panel">
    <div class="panel-heading"><div><span class="admin-overline">CUSTOMER VALUE</span><h2>Top customers</h2></div><a href="<?= e(base_url('admin/customers.php')) ?>">K.O.D Club ↗</a></div>
    <?php if (!$customerRows): ?><div class="analytics-empty">Customer order activity will appear here.</div><?php else: ?><div class="table-wrap"><table class="analytics-table"><thead><tr><th>CUSTOMER</th><th>ORDERS</th><th>ORDER VALUE</th><th>LAST ORDER</th></tr></thead><tbody>
        <?php foreach ($customerRows as $row): ?><tr><td><strong><?= e((string)$row['customer_name']) ?></strong><small><?= e((string)$row['customer_email']) ?></small></td><td><?= number_format((int)$row['order_count']) ?></td><td><strong><?= e(kod_money($row['spend'])) ?></strong></td><td><?= e(date('M j, Y', strtotime((string)$row['last_order']))) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<p class="analytics-disclaimer">Business insight only: this report shows order value and recorded payments. It does not calculate profit, cost of goods, tax or delivery costs because those figures are not currently stored in K.O.D.</p>
<script>
(() => {
    const period = document.getElementById('analytics-period');
    if (!period) return;
    period.addEventListener('change', () => {
        const custom = period.value === 'custom';
        document.querySelectorAll('.analytics-filter-form input[type="date"]').forEach((input) => {
            input.required = custom;
        });
    });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
