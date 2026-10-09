<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/chat.php';
header('Cache-Control: no-store, private');
$userId = (int) $_SESSION['user_id'];
$requestId = filter_input(INPUT_GET, 'request_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$chat = $requestId ? accepted_chat($conn, $requestId, $userId) : null;
$error = '';
$draft = '';
if (!$chat) {
    http_response_code(403);
    $error = CHAT_DENIED;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $draft = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';
    $submissionToken = $_POST['submission_token'] ?? '';
    if ($draft === '' || !mb_check_encoding($draft, 'UTF-8') || mb_strlen($draft, 'UTF-8') > 2000) {
        http_response_code(422);
        $error = 'Enter a message between 1 and 2,000 characters.';
    } elseif (!is_string($submissionToken) || !preg_match('/^[a-f0-9]{32}$/D', $submissionToken)) {
        http_response_code(422);
        $error = 'Reload the chat and try sending again.';
    } else {
        $conn->begin_transaction();
        try {
            if (!accepted_chat($conn, $requestId, $userId, true)) {
                $conn->rollback();
                http_response_code(403);
                $chat = null;
                $error = CHAT_DENIED;
            } else {
                // The request was locked and authorized; sender always comes from the session.
                $statement = $conn->prepare('INSERT INTO exchange_messages (request_id, sender_id, message, submission_token) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE id = id');
                $statement->bind_param('iiss', $requestId, $userId, $draft, $submissionToken);
                $statement->execute();
                $statement->close();
                $conn->commit();
                header('Location: exchange_chat.php?request_id=' . $requestId, true, 303);
                exit;
            }
        } catch (Throwable $exception) {
            $conn->rollback();
            http_response_code(503);
            $error = 'Unable to send your message right now. Please try again.';
        }
    }
}
$messages = $chat ? chat_messages($conn, $requestId, $userId) : [];
$otherId = $chat ? ((int) $chat['owner_id'] === $userId ? (int) $chat['requester_id'] : (int) $chat['owner_id']) : 0;
$otherName = $chat ? ((int) $chat['owner_id'] === $userId ? $chat['requester_name'] : $chat['owner_name']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exchange Chat | Book Exchange Easy</title>
    <?php require __DIR__ . '/includes/layout.php'; ?>
    <link rel="stylesheet" href="assets/chat.css">
</head>
<body>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="container app-main chat-main">
    <a href="exchange_chats.php">&larr; Exchange Chats</a>
    <h1>Exchange Chat</h1>
    <?php if ($error !== ''): ?><p class="chat-error" role="alert"><?php echo chat_escape($error); ?></p><?php endif; ?>
    <?php if ($chat): ?>
    <div class="chat-layout">
        <section class="chat-window" aria-label="Private exchange conversation">
            <header class="chat-header">
                <img class="chat-cover" src="<?php echo book_image($chat); ?>" alt="Book cover">
                <div><h2><?php echo chat_escape($chat['title']); ?></h2><p>With: <strong><?php echo chat_escape($otherName); ?></strong></p><a href="profile.php?id=<?php echo $otherId; ?>">View Profile</a></div>
                <span class="status accepted">Accepted</span>
            </header>
            <div class="chat-messages" id="chat-messages" role="log" aria-label="Messages" aria-live="polite" tabindex="0" data-request-id="<?php echo (int) $requestId; ?>">
                <div class="chat-empty" id="chat-empty" <?php if ($messages): ?>hidden<?php endif; ?>>
                    <span aria-hidden="true">&#128172;</span><h2>Start your exchange conversation</h2>
                    <p>Your exchange request has been accepted. You can now discuss delivery, meeting location, or book handover details here.</p>
                </div>
                <?php foreach ($messages as $message): ?>
                <article class="chat-bubble <?php echo $message['mine'] ? 'mine' : 'theirs'; ?>" data-message-id="<?php echo $message['id']; ?>">
                    <p><?php echo chat_escape($message['message']); ?></p><time><?php echo chat_escape($message['created_at']); ?></time>
                </article>
                <?php endforeach; ?>
            </div>
            <p id="chat-refresh-status" class="chat-refresh-status" role="status">New messages refresh every 4 seconds.</p>
            <form method="POST" action="exchange_chat.php?request_id=<?php echo (int) $requestId; ?>" class="chat-composer" id="chat-composer">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="submission_token" value="<?php echo bin2hex(random_bytes(16)); ?>">
                <label class="visually-hidden" for="message">Your message</label>
                <textarea name="message" id="message" maxlength="2000" rows="2" required placeholder="Type your message..."><?php echo chat_escape($draft); ?></textarea>
                <button type="submit">Send</button>
            </form>
        </section>
        <aside class="chat-info">
            <p class="chat-eyebrow">YOUR BOOK HANDOVER</p><h2>Exchange Information</h2>
            <dl><dt>Book</dt><dd><?php echo chat_escape($chat['title']); ?></dd><dt>Owner</dt><dd><a href="profile.php?id=<?php echo (int) $chat['owner_id']; ?>"><?php echo chat_escape($chat['owner_name']); ?></a></dd><dt>Requester</dt><dd><a href="profile.php?id=<?php echo (int) $chat['requester_id']; ?>"><?php echo chat_escape($chat['requester_name']); ?></a></dd><dt>Status</dt><dd>Accepted</dd></dl>
            <a class="button" href="book_details.php?id=<?php echo (int) $chat['book_id']; ?>">View Book</a>
            <p class="chat-note">Share a meeting location or contact details here when you choose. Profile addresses are never shared automatically.</p>
        </aside>
    </div>
    <script src="assets/chat.js" defer></script>
    <?php endif; ?>
</main>
</body>
</html>
