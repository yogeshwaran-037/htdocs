<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/chat.php';
header('Cache-Control: no-store, private');
$userId = (int) $_SESSION['user_id'];
$statement = $conn->prepare("SELECT r.id, r.owner_id, r.requester_id, b.title, b.book_image,
    owner.name AS owner_name, requester.name AS requester_name,
    m.message AS last_message, m.created_at AS last_message_time
    FROM exchange_requests r
    JOIN books b ON b.id = r.book_id
    JOIN users owner ON owner.id = r.owner_id
    JOIN users requester ON requester.id = r.requester_id
    LEFT JOIN exchange_messages m ON m.id = (SELECT MAX(latest.id) FROM exchange_messages latest WHERE latest.request_id = r.id)
    WHERE r.status = 'Accepted' AND (r.owner_id = ? OR r.requester_id = ?)
    ORDER BY COALESCE(m.created_at, r.created_at) DESC, r.id DESC");
$statement->bind_param('ii', $userId, $userId);
$statement->execute();
$chats = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
$statement->close();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Exchange Chats | Book Exchange Easy</title>
<?php require __DIR__ . '/includes/layout.php'; ?><link rel="stylesheet" href="assets/chat.css"></head>
<body><?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="container app-main chat-main"><h1>Exchange Chats</h1><p class="intro">Coordinate your accepted book exchanges in private.</p>
<?php if (!$chats): ?><div class="empty"><h2>Your next conversation starts with a book</h2><p>Chats appear here after an exchange request is accepted.</p><a class="button" href="my_requests.php">View My Requests</a></div>
<?php else: ?><section class="chat-list">
<?php foreach ($chats as $chat): ?>
<article class="chat-list-card"><img class="chat-cover" src="<?php echo book_image($chat); ?>" alt="Book cover"><div><h2><?php echo chat_escape($chat['title']); ?></h2><p>With: <?php echo chat_escape((int) $chat['owner_id'] === $userId ? $chat['requester_name'] : $chat['owner_name']); ?></p><p class="chat-preview"><?php echo $chat['last_message'] !== null ? chat_escape(mb_substr($chat['last_message'], 0, 140, 'UTF-8')) : 'Start your exchange conversation'; ?></p><?php if ($chat['last_message_time']): ?><time><?php echo chat_escape($chat['last_message_time']); ?></time><?php endif; ?></div><a class="button" href="exchange_chat.php?request_id=<?php echo (int) $chat['id']; ?>">Open Exchange Chat</a></article>
<?php endforeach; ?></section><?php endif; ?>
</main></body></html>
