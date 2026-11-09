<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

function kod_get_customer_preferences(int $customerId): array
{
    $defaults = [
        'birth_date' => '',
        'sweetness' => 'medium',
        'strength' => 'balanced',
        'milk_preference' => 'any',
        'preferred_flavour' => 'any',
        'preferred_budget' => '',
    ];
    if ($customerId < 1) return $defaults;
    try {
        $stmt = db()->prepare('SELECT birth_date, sweetness, strength, milk_preference, preferred_flavour, preferred_budget FROM customer_preferences WHERE customer_id = :id LIMIT 1');
        $stmt->execute(['id' => $customerId]);
        $row = $stmt->fetch();
        if (!$row) return $defaults;
        return array_merge($defaults, [
            'birth_date' => (string)($row['birth_date'] ?? ''),
            'sweetness' => (string)($row['sweetness'] ?? 'medium'),
            'strength' => (string)($row['strength'] ?? 'balanced'),
            'milk_preference' => (string)($row['milk_preference'] ?? 'any'),
            'preferred_flavour' => (string)($row['preferred_flavour'] ?? 'any'),
            'preferred_budget' => $row['preferred_budget'] === null ? '' : (string)$row['preferred_budget'],
        ]);
    } catch (Throwable $exception) {
        error_log('KOD customer preferences lookup failed: ' . $exception->getMessage());
        return $defaults;
    }
}

function kod_get_customer_favorite_ids(int $customerId): array
{
    if ($customerId < 1) return [];
    try {
        $stmt = db()->prepare('SELECT menu_item_id FROM customer_favorites WHERE customer_id = :id');
        $stmt->execute(['id' => $customerId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $exception) {
        error_log('KOD customer favourites lookup failed: ' . $exception->getMessage());
        return [];
    }
}

/** Grant 100 points once during the customer's birthday month. */
function kod_award_birthday_points_if_due(int $customerId): bool
{
    if ($customerId < 1) return false;
    $pdo = null;
    try {
        $pdo = db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT birth_date FROM customer_preferences WHERE customer_id = :id FOR UPDATE');
        $stmt->execute(['id' => $customerId]);
        $birthDate = $stmt->fetchColumn();
        if (!$birthDate || !preg_match('/^\d{4}-(\d{2})-(\d{2})$/', (string)$birthDate, $parts) || $parts[1] !== date('m')) {
            $pdo->commit();
            return false;
        }
        $year = (int)date('Y');
        $insert = $pdo->prepare('INSERT IGNORE INTO birthday_rewards (customer_id, reward_year, points_awarded) VALUES (:customer_id, :reward_year, 100)');
        $insert->execute(['customer_id' => $customerId, 'reward_year' => $year]);
        if ($insert->rowCount() !== 1) {
            $pdo->commit();
            return false;
        }
        $pdo->prepare('UPDATE customers SET loyalty_points = loyalty_points + 100 WHERE id = :id')->execute(['id' => $customerId]);
        $history = $pdo->prepare("INSERT INTO loyalty_transactions (customer_id, order_id, transaction_type, points_delta, naira_value, note) VALUES (:customer_id, NULL, 'adjustment', 100, 500.00, :note)");
        $history->execute(['customer_id' => $customerId, 'note' => 'K.O.D birthday-month gift ' . $year]);
        $pdo->commit();
        return true;
    } catch (Throwable $exception) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        error_log('KOD birthday reward check failed: ' . $exception->getMessage());
        return false;
    }
}

function kod_personalized_recommendations(int $customerId, int $limit = 4): array
{
    $limit = max(1, min(8, $limit));
    $preferences = kod_get_customer_preferences($customerId);
    $favoriteIds = kod_get_customer_favorite_ids($customerId);
    try {
        $items = db()->query('SELECT id, name, slug, category, description, price, image_url, is_featured FROM menu_items WHERE is_available = 1 ORDER BY is_featured DESC, sort_order ASC, name ASC LIMIT 50')->fetchAll();
        $historyStmt = db()->prepare("SELECT oi.menu_item_id, SUM(oi.quantity) AS bought FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id WHERE o.customer_id = :customer_id AND o.status = 'completed' AND oi.menu_item_id IS NOT NULL GROUP BY oi.menu_item_id");
        $historyStmt->execute(['customer_id' => $customerId]);
        $history = [];
        foreach ($historyStmt->fetchAll() as $row) $history[(int)$row['menu_item_id']] = (int)$row['bought'];
    } catch (Throwable $exception) {
        error_log('KOD personalized recommendation lookup failed: ' . $exception->getMessage());
        return [];
    }

    $flavourTerms = [
        'caramel' => ['caramel', 'brown sugar', 'toffee'],
        'chocolate' => ['chocolate', 'cocoa', 'mocha'],
        'vanilla' => ['vanilla', 'cream', 'silk'],
        'nutty' => ['nut', 'almond', 'hazelnut'],
        'fruity' => ['berry', 'fruit', 'citrus', 'orange'],
        'floral' => ['floral', 'rose', 'botanical'],
        'earthy' => ['earthy', 'matcha', 'tea'],
    ];
    $scored = [];
    foreach ($items as $item) {
        $haystack = strtolower($item['name'] . ' ' . $item['category'] . ' ' . $item['description']);
        $score = 0;
        $reasons = [];
        $flavour = $preferences['preferred_flavour'];
        if ($flavour !== 'any' && isset($flavourTerms[$flavour])) {
            foreach ($flavourTerms[$flavour] as $term) {
                if (str_contains($haystack, $term)) { $score += 5; $reasons[] = 'matches your ' . $flavour . ' flavour preference'; break; }
            }
        }
        $strengthTerms = ['strong' => ['espresso', 'double', 'bold', 'dark', 'cold brew'], 'mild' => ['soft', 'gentle', 'silk', 'vanilla', 'botanical', 'tea']];
        $strength = $preferences['strength'];
        if ($strength !== 'balanced' && isset($strengthTerms[$strength])) {
            foreach ($strengthTerms[$strength] as $term) {
                if (str_contains($haystack, $term)) { $score += 3; $reasons[] = 'fits your ' . $strength . ' strength preference'; break; }
            }
        }
        $sweetTerms = ['low' => ['espresso', 'dark', 'bold', 'tea', 'unsweet'], 'high' => ['caramel', 'sugar', 'sweet', 'vanilla', 'cream', 'chocolate']];
        $sweetness = $preferences['sweetness'];
        if ($sweetness !== 'medium' && isset($sweetTerms[$sweetness])) {
            foreach ($sweetTerms[$sweetness] as $term) {
                if (str_contains($haystack, $term)) { $score += 2; $reasons[] = 'suits your ' . $sweetness . '-sweetness preference'; break; }
            }
        }
        $milk = $preferences['milk_preference'];
        if ($milk !== 'any') {
            $milkTerm = $milk === 'no_milk' ? ['espresso', 'cold brew', 'tea'] : [$milk];
            foreach ($milkTerm as $term) {
                if (str_contains($haystack, str_replace('_', ' ', $term))) { $score += 2; $reasons[] = 'aligns with your milk preference'; break; }
            }
        }
        $budget = (float)($preferences['preferred_budget'] ?: 0);
        if ($budget > 0) {
            if ((float)$item['price'] <= $budget) { $score += 3; $reasons[] = 'fits your usual budget'; }
            else $score -= 4;
        }
        if (isset($history[(int)$item['id']])) { $score += min(8, $history[(int)$item['id']] * 2); $reasons[] = 'you have enjoyed it before'; }
        if (in_array((int)$item['id'], $favoriteIds, true)) { $score += 4; $reasons[] = 'one of your saved favourites'; }
        if ((int)($item['is_featured'] ?? 0) === 1) $score += 1;
        $scored[] = ['score' => $score, 'item' => $item, 'reason' => $reasons ? ucfirst($reasons[0]) . '.' : 'A K.O.D pick worth discovering.'];
    }
    usort($scored, static function (array $a, array $b): int {
        if ($a['score'] === $b['score']) return strcmp((string)$a['item']['name'], (string)$b['item']['name']);
        return $b['score'] <=> $a['score'];
    });
    return array_map(static function (array $entry): array {
        $entry['item']['recommendation_reason'] = $entry['reason'];
        return $entry['item'];
    }, array_slice($scored, 0, $limit));
}
