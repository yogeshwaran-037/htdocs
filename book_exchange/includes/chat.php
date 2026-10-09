<?php
const CHAT_DENIED = 'This exchange chat is available only after the request is accepted.';
function accepted_chat(mysqli $conn, int $requestId, int $userId, bool $lock = false): ?array {
    $sql = "SELECT r.id, r.book_id, r.owner_id, r.requester_id, r.status,
                   b.title, b.book_image, owner.name AS owner_name, requester.name AS requester_name
            FROM exchange_requests r
            JOIN books b ON b.id = r.book_id
            JOIN users owner ON owner.id = r.owner_id
            JOIN users requester ON requester.id = r.requester_id
            WHERE r.id = ? AND r.status = 'Accepted' AND (r.owner_id = ? OR r.requester_id = ?)";
    if ($lock) { $sql .= ' FOR UPDATE'; }
    $statement = $conn->prepare($sql);
    $statement->bind_param('iii', $requestId, $userId, $userId);
    $statement->execute();
    $chat = $statement->get_result()->fetch_assoc();
    $statement->close();
    return $chat ?: null;
}
function chat_messages(mysqli $conn, int $requestId, int $userId, int $afterId = 0): array {
    // Authorization is included in the message SELECT itself, including polling.
    $statement = $conn->prepare("SELECT m.id, m.sender_id, m.message, m.created_at
        FROM exchange_messages m JOIN exchange_requests r ON r.id = m.request_id
        WHERE r.id = ? AND r.status = 'Accepted' AND (r.owner_id = ? OR r.requester_id = ?)
          AND m.id > ? ORDER BY m.id ASC LIMIT 100");
    $statement->bind_param('iiii', $requestId, $userId, $userId, $afterId);
    $statement->execute();
    $messages = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();
    foreach ($messages as &$message) {
        $message['id'] = (int) $message['id'];
        $message['mine'] = (int) $message['sender_id'] === $userId;
        unset($message['sender_id']);
    }
    unset($message);
    return $messages;
}
function chat_escape(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
