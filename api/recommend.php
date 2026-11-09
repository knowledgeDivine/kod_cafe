<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/database.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
$data = request_json();
if (!csrf_check($data['csrf_token'] ?? null)) json_response(['success' => false, 'message' => 'Your session expired. Refresh and try again.'], 419);
$shade = strtolower(trim((string)($data['shade'] ?? '')));
$matches = [
    'caramel' => ['name' => 'Caramel Cloud Latte', 'description' => 'A smooth espresso with silky milk, warm caramel and a soft, sweet finish.', 'strength' => 'MEDIUM / SMOOTH', 'price' => 3800],
    'mocha' => ['name' => 'Dark Velvet Mocha', 'description' => 'Bold espresso wrapped in deep chocolate and steamed milk. Rich, balanced and comforting.', 'strength' => 'MEDIUM / RICH', 'price' => 4200],
    'latte' => ['name' => 'Oat Silk Latte', 'description' => 'A gentle espresso softened with creamy oat milk. Light sweetness, beautifully mellow.', 'strength' => 'LIGHT / CREAMY', 'price' => 4000],
    'espresso' => ['name' => 'Midnight Double Espresso', 'description' => 'A focused double shot with a deep roast profile and a clean, lingering finish.', 'strength' => 'STRONG / BOLD', 'price' => 2800],
    'rose' => ['name' => 'Rose Cloud Latte', 'description' => 'A soft latte with a delicate floral note and a silky finish. Gentle, fragrant and playful.', 'strength' => 'LIGHT / FLORAL', 'price' => 4300],
    'matcha' => ['name' => 'Matcha Milk Cloud', 'description' => 'Earthy ceremonial-style matcha blended with smooth milk for a calm, creamy pick-me-up.', 'strength' => 'GENTLE / EARTHY', 'price' => 4000],
];
if (!isset($matches[$shade])) json_response(['success' => false, 'message' => 'Choose one of the available coffee shades.'], 422);
$match = $matches[$shade];
try {
    $stmt = db()->prepare('INSERT INTO coffee_recommendations (shade, recommended_drink, created_at) VALUES (:shade, :drink, NOW())');
    $stmt->execute(['shade' => $shade, 'drink' => $match['name']]);
} catch (Throwable $exception) {
    error_log('Recommendation save failed: ' . $exception->getMessage());
    // The recommendation remains available even if analytics storage is unavailable.
}
json_response(['success' => true, 'recommendation' => $match]);
