<?php
require_once __DIR__ . '/includes/security.php';
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once __DIR__ . '/config/db.php';
$userId = (int) $_SESSION['user_id'];
$requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$requestId || !in_array($action, ['accept', 'reject'], true)) { http_response_code(400); exit('Invalid request action.'); }
mysqli_begin_transaction($conn);
try {
    $statement = $conn->prepare('SELECT books.id, books.exchange_status FROM books JOIN exchange_requests r ON r.book_id = books.id WHERE r.id = ? AND r.owner_id = ? AND books.user_id = ? FOR UPDATE');
    $statement->bind_param('iii', $requestId, $userId, $userId); $statement->execute();
    $book = $statement->get_result()->fetch_assoc();
    if (!$book) { mysqli_rollback($conn); http_response_code(403); exit('This request does not belong to you.'); }
    if ($action === 'accept' && $book['exchange_status'] !== 'Available') { throw new RuntimeException('Book unavailable.'); }
    if ($action === 'accept') {
        $check = $conn->prepare("SELECT id FROM exchange_requests WHERE book_id = ? AND status = 'Accepted' LIMIT 1");
        $check->bind_param('i', $book['id']); $check->execute();
        if ($check->get_result()->fetch_assoc()) { throw new RuntimeException('An exchange is already accepted for this book.'); }
    }
    $status = $action === 'accept' ? 'Accepted' : 'Rejected';
    $statement = $conn->prepare("UPDATE exchange_requests SET status = ? WHERE id = ? AND owner_id = ? AND status = 'Pending'");
    $statement->bind_param('sii', $status, $requestId, $userId); $statement->execute();
    if ($statement->affected_rows !== 1) { throw new RuntimeException('Request no longer pending.'); }
    if ($action === 'accept') {
        // Acceptance enables communication; it does not complete the handover or change book status.
        $statement = $conn->prepare("UPDATE exchange_requests SET status = 'Rejected' WHERE book_id = ? AND id <> ? AND status = 'Pending'");
        $statement->bind_param('ii', $book['id'], $requestId); $statement->execute();
    }
    mysqli_commit($conn); $_SESSION['success_message'] = $action === 'accept' ? 'Exchange request accepted. Chat is now available.' : 'Exchange request rejected.';
} catch (Throwable $exception) { mysqli_rollback($conn); $_SESSION['success_message'] = 'The request may no longer be pending or available.'; }
header('Location: received_requests.php'); exit;
