<?php
require_once __DIR__ . '/includes/security.php';
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once __DIR__ . '/config/db.php';
$userId = (int) $_SESSION['user_id'];
$bookId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$bookId) { http_response_code(405); exit('Use the Delete button in My Books.'); }
mysqli_begin_transaction($conn);
try {
    $statement = $conn->prepare('SELECT id FROM books WHERE id = ? AND user_id = ? FOR UPDATE');
    $statement->bind_param('ii', $bookId, $userId); $statement->execute();
    if (!$statement->get_result()->fetch_assoc()) { mysqli_rollback($conn); http_response_code(403); exit('You do not own this book.'); }
    $statement = $conn->prepare('SELECT id FROM exchange_requests WHERE book_id = ? LIMIT 1');
    $statement->bind_param('i', $bookId); $statement->execute();
    if ($statement->get_result()->fetch_assoc()) {
        mysqli_rollback($conn);
        $_SESSION['success_message'] = 'This book has exchange history and must be kept. Its details can still be edited.';
    } else {
        $statement = $conn->prepare('DELETE FROM books WHERE id = ? AND user_id = ?');
        $statement->bind_param('ii', $bookId, $userId); $statement->execute();
        mysqli_commit($conn); $_SESSION['success_message'] = 'Your book was deleted successfully.';
    }
} catch (Throwable $exception) { mysqli_rollback($conn); $_SESSION['success_message'] = 'Unable to delete the book right now.'; }
header('Location: my_books.php'); exit;
