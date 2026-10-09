<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/chat.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); echo json_encode(['error' => 'Use GET to refresh messages.']); exit; }
$userId = (int) $_SESSION['user_id'];
$requestId = filter_input(INPUT_GET, 'request_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$afterId = isset($_GET['after_id']) ? filter_input(INPUT_GET, 'after_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) : 0;
if ($afterId === false || $afterId === null) { http_response_code(400); echo json_encode(['error' => 'Invalid message cursor.']); exit; }
try {
    if (!$requestId || !accepted_chat($conn, $requestId, $userId)) { http_response_code(403); echo json_encode(['error' => CHAT_DENIED]); exit; }
    $messages = chat_messages($conn, $requestId, $userId, $afterId);
    echo json_encode(['messages' => $messages], JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(503); echo json_encode(['error' => 'Unable to refresh messages. Please try again.']);
}
