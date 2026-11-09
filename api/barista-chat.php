<?php
declare(strict_types=1);

// API endpoints must never leak PHP warning/notice HTML into a JSON response.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ob_start();

register_shutdown_function(static function (): void {
    $lastError = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!$lastError || !in_array($lastError['type'], $fatalTypes, true)) {
        return;
    }

    error_log('KOD Barista fatal error: ' . ($lastError['message'] ?? 'Unknown error') . ' in ' . ($lastError['file'] ?? 'unknown file') . ':' . ($lastError['line'] ?? 0));
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode([
        'success' => false,
        'message' => 'The K.O.D server encountered an internal error. Check the XAMPP Apache error log for details.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
});

try {
    // Convert PHP warnings/notices into catchable exceptions. Details are logged,
    // while the customer receives a safe JSON error instead of an HTML traceback.
    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    require_once dirname(__DIR__) . '/includes/functions.php';
    require_once dirname(__DIR__) . '/includes/customer_auth.php';
    require_once dirname(__DIR__) . '/includes/personalization.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}
$data = request_json();
if (!csrf_check($data['csrf_token'] ?? null)) {
    json_response(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 419);
}

$now = time();
$rate = $_SESSION['barista_rate'] ?? ['started' => $now, 'count' => 0];
if (!is_array($rate) || $now - (int)($rate['started'] ?? 0) >= 60) {
    $rate = ['started' => $now, 'count' => 0];
}
$rate['count'] = (int)($rate['count'] ?? 0) + 1;
$_SESSION['barista_rate'] = $rate;
if ($rate['count'] > 12) {
    json_response(['success' => false, 'message' => 'Our barista needs a tiny breather. Please try again in a minute.'], 429);
}

function kod_barista_substr(string $value, int $length): string { return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length); }
function kod_barista_lower(string $value): string { return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value); }
function kod_barista_strlen(string $value): int { return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value); }

$messages = $data['messages'] ?? [];
if (!is_array($messages) || count($messages) < 1 || count($messages) > 10) {
    json_response(['success' => false, 'message' => 'Please send a message to get started.'], 422);
}
$cleanMessages = [];
foreach ($messages as $message) {
    if (!is_array($message)) continue;
    $role = ($message['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
    $content = trim((string)($message['content'] ?? ''));
    if ($content === '') continue;
    $cleanMessages[] = ['role' => $role, 'content' => kod_barista_substr($content, 700)];
}
if (!$cleanMessages || end($cleanMessages)['role'] !== 'user') {
    json_response(['success' => false, 'message' => 'Please enter a message before sending.'], 422);
}

try {
    $items = db()->query("SELECT id, name, category, description, price, image_url FROM menu_items WHERE is_available = 1 ORDER BY is_featured DESC, sort_order ASC, name ASC LIMIT 50")->fetchAll();
} catch (Throwable $exception) {
    error_log('KOD Barista menu lookup failed: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'The menu is taking a moment to load. Please try again shortly.'], 503);
}
if (!$items) {
    json_response(['success' => true, 'mode' => 'fallback', 'message' => 'Our menu is being refreshed right now. Please check back in a little while, or ask our café team for today’s recommendations.', 'products' => []]);
}

$preferences = $data['preferences'] ?? [];
if (!is_array($preferences)) $preferences = [];
$preferences = [
    'taste' => kod_barista_substr(trim((string)($preferences['taste'] ?? 'surprise me')), 80),
    'strength' => kod_barista_substr(trim((string)($preferences['strength'] ?? 'any')), 30),
    'budget' => max(0, min(1000000, (float)($preferences['budget'] ?? 0))),
    'shade' => kod_barista_substr(trim((string)($preferences['shade'] ?? 'caramel')), 30),
    'dessert_pairing' => !empty($preferences['dessert_pairing']),
    'milk_preference' => kod_barista_substr(trim((string)($preferences['milk_preference'] ?? 'any')), 30),
    'sweetness' => kod_barista_substr(trim((string)($preferences['sweetness'] ?? 'any')), 20),
];

// Signed-in customers' saved taste profile fills any preference the current chat did not specify.
try {
    $signedCustomer = current_customer();
    if ($signedCustomer) {
        $savedProfile = kod_get_customer_preferences((int)$signedCustomer['id']);
        if ($preferences['strength'] === 'any') $preferences['strength'] = $savedProfile['strength'];
        if ($preferences['taste'] === 'surprise me' && $savedProfile['preferred_flavour'] !== 'any') $preferences['taste'] = $savedProfile['preferred_flavour'];
        if ($preferences['budget'] <= 0 && $savedProfile['preferred_budget'] !== '') $preferences['budget'] = (float)$savedProfile['preferred_budget'];
        if ($preferences['milk_preference'] === 'any') $preferences['milk_preference'] = $savedProfile['milk_preference'];
        if ($preferences['sweetness'] === 'any') $preferences['sweetness'] = $savedProfile['sweetness'];
    }
} catch (Throwable $profileException) {
    error_log('KOD Barista customer preference merge failed: ' . $profileException->getMessage());
}

$catalogue = array_map(static fn(array $item): array => [
    'id' => (int)$item['id'],
    'name' => (string)$item['name'],
    'category' => (string)$item['category'],
    'description' => (string)$item['description'],
    'price' => (float)$item['price'],
], $items);

function kod_barista_fallback(array $messages, array $preferences, array $catalogue): array
{
    $last = (string)end($messages)['content'];
    $query = kod_barista_lower($last . ' ' . json_encode($preferences, JSON_UNESCAPED_UNICODE));
    $scored = [];
    foreach ($catalogue as $item) {
        $haystack = kod_barista_lower($item['name'] . ' ' . $item['category'] . ' ' . $item['description']);
        $score = 0;
        if (str_contains($query, 'dessert') || str_contains($query, 'cake') || !empty($preferences['dessert_pairing'])) {
            if ($item['category'] === 'dessert' || $item['category'] === 'pastry') $score += 5;
        }
        if (preg_match('/coffee|espresso|latte|cappuccino|strong|bold|creamy|mocha|iced/', $query) && $item['category'] === 'coffee') $score += 3;
        if (preg_match('/tea|matcha|earthy|gentle/', $query) && $item['category'] === 'tea') $score += 4;
        foreach (preg_split('/[^\pL\pN]+/u', $query) ?: [] as $word) {
            if (kod_barista_strlen($word) > 3 && str_contains($haystack, $word)) $score += 1;
        }
        if ($preferences['budget'] > 0 && $item['price'] <= $preferences['budget']) $score += 2;
        if ($preferences['budget'] > 0 && $item['price'] > $preferences['budget']) $score -= 5;
        $scored[] = ['score' => $score, 'item' => $item];
    }
    usort($scored, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    $chosen = array_slice(array_column($scored, 'item'), 0, min(3, count($scored)));
    $names = array_map(static fn(array $p): string => $p['name'], $chosen);
    $message = 'I can help you find a lovely K.O.D pick. Based on what you shared, I would start with ' . implode(', ', $names) . '. These are suggestions from our current menu; ask our team about ingredients or allergens before ordering.';
    return ['message' => $message, 'product_ids' => array_map(static fn(array $p): int => (int)$p['id'], $chosen), 'quick_replies' => ['Something less sweet, please', 'Pair it with a dessert', 'Show me a budget-friendly option']];
}

$apiKey = trim((string)env_value('OPENAI_API_KEY', ''));
$model = trim((string)env_value('OPENAI_MODEL', 'gpt-6-astra'));
$answer = null;
$mode = 'fallback';
if ($apiKey !== '' && function_exists('curl_init')) {
    $apiMessages = [];
    foreach (array_slice($cleanMessages, -8) as $message) {
        $apiMessages[] = ['role' => $message['role'], 'content' => $message['content']];
    }
    $last = end($apiMessages);
    $last['content'] .= "\n\nCUSTOMER PREFERENCES (may be incomplete): " . json_encode($preferences, JSON_UNESCAPED_UNICODE);
    $apiMessages[count($apiMessages) - 1] = $last;
    $payload = [
        'model' => $model,
        'store' => false,
        'instructions' => 'You are the K.O.D Café AI Barista: warm, concise, tasteful and genuinely helpful. Recommend ONLY products whose IDs appear in the supplied live menu catalogue. Never invent a menu item, price, ingredient, allergen, dietary certification, opening hour, stock level or delivery promise. If asked about allergens or ingredients not shown in the catalogue, tell the customer to confirm with K.O.D staff before ordering. Keep answers concise (usually 2–5 sentences). Ask one short follow-up question if preferences are unclear. Always explain why a recommended item fits the customer. If the user asks for something unrelated to K.O.D, politely bring the conversation back to coffee, tea, desserts, the K.O.D menu or ordering. You do not place orders or take payments. Return JSON only with: message (string), product_ids (array of integer menu IDs, max 3), quick_replies (array of up to 3 short strings).',
        'input' => array_merge([
            ['role' => 'developer', 'content' => 'LIVE K.O.D MENU CATALOGUE (source of truth): ' . json_encode($catalogue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ], $apiMessages),
        'text' => ['format' => [
            'type' => 'json_schema',
            'name' => 'kod_barista_reply',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'message' => ['type' => 'string'],
                    'product_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => 3],
                    'quick_replies' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 3],
                ],
                'required' => ['message', 'product_ids', 'quick_replies'],
            ],
        ]],
        'max_output_tokens' => 500,
    ];
    $curl = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $raw = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    if (is_string($raw) && $status >= 200 && $status < 300) {
        $response = json_decode($raw, true);
        $outputText = '';
        foreach (($response['output'] ?? []) as $output) {
            if (($output['type'] ?? '') !== 'message') continue;
            foreach (($output['content'] ?? []) as $part) {
                if (($part['type'] ?? '') === 'output_text') $outputText .= (string)($part['text'] ?? '');
            }
        }
        $decoded = json_decode($outputText, true);
        if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
            $validIds = array_fill_keys(array_map(static fn(array $p): int => (int)$p['id'], $catalogue), true);
            $ids = array_values(array_unique(array_filter(array_map('intval', is_array($decoded['product_ids'] ?? null) ? $decoded['product_ids'] : []), static fn(int $id): bool => isset($validIds[$id]))));
            $answer = [
                'message' => kod_barista_substr(trim($decoded['message']), 1800),
                'product_ids' => array_slice($ids, 0, 3),
                'quick_replies' => array_slice(array_map(static fn($v): string => kod_barista_substr(trim((string)$v), 70), is_array($decoded['quick_replies'] ?? null) ? $decoded['quick_replies'] : []), 0, 3),
            ];
            $mode = 'ai';
        } else {
            error_log('KOD Barista: OpenAI response was not valid structured output.');
        }
    } else {
        error_log('KOD Barista API request failed. HTTP ' . $status . '; ' . $curlError . '; response: ' . (is_string($raw) ? kod_barista_substr($raw, 700) : 'no response'));
    }
}
if ($answer === null) $answer = kod_barista_fallback($cleanMessages, $preferences, $catalogue);

$byId = [];
foreach ($items as $item) $byId[(int)$item['id']] = $item;
$products = [];
foreach ($answer['product_ids'] as $id) {
    if (!isset($byId[$id])) continue;
    $item = $byId[$id];
    $imageUrl = trim((string)($item['image_url'] ?? ''));
    $products[] = [
        'id' => (int)$item['id'],
        'name' => (string)$item['name'],
        'category' => (string)$item['category'],
        'description' => (string)$item['description'],
        'price' => (float)$item['price'],
        'image_url' => $imageUrl !== '' ? $imageUrl : placeholder_image((string)$item['category']),
    ];
}
json_response(['success' => true, 'mode' => $mode, 'message' => $answer['message'], 'quick_replies' => $answer['quick_replies'], 'products' => $products]);

} catch (Throwable $exception) {
    error_log('KOD Barista endpoint exception: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode([
        'success' => false,
        'message' => 'The K.O.D server could not complete that request. Check the XAMPP Apache error log, then try again.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
