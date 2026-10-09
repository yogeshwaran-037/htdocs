<?php
require_once __DIR__ . '/includes/security.php';

// Only signed-in users may send exchange requests.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

$requesterId = (int) $_SESSION['user_id'];
$bookId = filter_input(INPUT_GET, 'book_id', FILTER_VALIDATE_INT);
$book = null;
$error = '';
$successMessage = '';
$message = '';

if ($bookId) {
    // Get the available book and the name of its owner.
    $bookStatement = mysqli_prepare(
        $conn,
        "SELECT books.id, books.user_id, books.title, books.author, books.exchange_status, users.name AS owner_name
         FROM books
         INNER JOIN users ON books.user_id = users.id
         WHERE books.id = ? AND books.exchange_status = 'Available'
         LIMIT 1"
    );

    if ($bookStatement) {
        mysqli_stmt_bind_param($bookStatement, 'i', $bookId);
        mysqli_stmt_execute($bookStatement);
        $bookResult = mysqli_stmt_get_result($bookStatement);
        $book = mysqli_fetch_assoc($bookResult);
        mysqli_stmt_close($bookStatement);
    } else {
        $error = 'Unable to load this book right now.';
    }

    if (!$book && $error === '') {
        $error = 'This book is not available for exchange.';
    }
} else {
    $error = 'Invalid book selected.';
}

if ($book && (int) $book['user_id'] === $requesterId) {
    $error = 'You cannot send an exchange request for your own book.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $book && $error === '') {
    mysqli_begin_transaction($conn);
    $lock = $conn->prepare("SELECT id FROM books WHERE id = ? AND user_id <> ? AND exchange_status = 'Available' FOR UPDATE");
    $lock->bind_param('ii', $bookId, $requesterId); $lock->execute();
    if (!$lock->get_result()->fetch_assoc()) { mysqli_rollback($conn); http_response_code(409); exit('This book is no longer available.'); }
    $message = trim($_POST['message'] ?? '');
    $ownerId = (int) $book['user_id'];
    $pendingStatus = 'Pending';

    // Do not allow the same requester to make another pending request for this book.
    $duplicateStatement = mysqli_prepare(
        $conn,
        'SELECT id FROM exchange_requests WHERE book_id = ? AND requester_id = ? AND status = ? LIMIT 1'
    );

    if ($duplicateStatement) {
        mysqli_stmt_bind_param($duplicateStatement, 'iis', $bookId, $requesterId, $pendingStatus);
        mysqli_stmt_execute($duplicateStatement);
        $duplicateResult = mysqli_stmt_get_result($duplicateStatement);
        $existingRequest = mysqli_fetch_assoc($duplicateResult);
        mysqli_stmt_close($duplicateStatement);

        if ($existingRequest) {
            $error = 'You already have a pending exchange request for this book.';
        } else {
            $insertStatement = mysqli_prepare(
                $conn,
                'INSERT INTO exchange_requests (book_id, requester_id, owner_id, message, status) VALUES (?, ?, ?, ?, ?)'
            );

            if ($insertStatement) {
                mysqli_stmt_bind_param($insertStatement, 'iiiss', $bookId, $requesterId, $ownerId, $message, $pendingStatus);

                if (mysqli_stmt_execute($insertStatement)) {
                    $successMessage = 'Exchange request sent successfully.';
                    $message = '';
                } else {
                    $error = 'Unable to send the exchange request. Please try again.';
                }

                mysqli_stmt_close($insertStatement);
            } else {
                $error = 'Unable to send the exchange request. Please try again.';
            }
        }
    } else {
        $error = 'Unable to check existing exchange requests right now.';
    }
    if ($error === '') { mysqli_commit($conn); } else { mysqli_rollback($conn); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Exchange Request | Book Exchange Easy</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f5f7fb; color: #1e293b; }
        .navbar { padding: 16px 5%; background: #312e81; }
        .navbar a { color: white; text-decoration: none; font-weight: bold; }
        .container { width: min(700px, 90%); margin: 40px auto; }
        .card { padding: 32px; background: white; border-radius: 16px; box-shadow: 0 5px 18px rgba(15, 23, 42, .08); }
        h1 { margin: 0 0 8px; color: #312e81; }
        .intro { margin: 0 0 24px; color: #64748b; }
        .book-summary { margin-bottom: 22px; padding: 18px; border-radius: 10px; background: #eef2ff; }
        .book-summary p { margin: 8px 0; }
        label { display: block; margin: 18px 0 7px; font-weight: bold; }
        textarea { width: 100%; min-height: 120px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; resize: vertical; }
        textarea:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px #e0e7ff; }
        .message { margin: 18px 0; padding: 12px; border-radius: 8px; }
        .error { background: #fef2f2; color: #b91c1c; }
        .success { background: #ecfdf5; color: #047857; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 24px; }
        button, .button { display: inline-block; padding: 11px 16px; border: 0; border-radius: 8px; background: #4f46e5; color: white; font: inherit; font-weight: bold; text-decoration: none; cursor: pointer; }
        button:hover, .button:hover { background: #3730a3; }
        .back-button { background: #e2e8f0; color: #334155; }
        .back-button:hover { background: #cbd5e1; }
    </style>
<?php require __DIR__ . "/includes/layout.php"; ?>
</head>
<body><?php require __DIR__ . "/includes/sidebar.php"; ?>

    <main class="container app-main">
        <section class="card">
            <h1>Send Exchange Request</h1>

            <?php if ($book): ?>
                <p class="intro">Send a message to the owner to request this book.</p>
                <div class="book-summary">
                    <p><strong>Book Title:</strong> <?php echo htmlspecialchars($book['title']); ?></p>
                    <p><strong>Author:</strong> <?php echo htmlspecialchars($book['author']); ?></p>
                    <p><strong>Owner Name:</strong> <?php echo htmlspecialchars($book['owner_name']); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($successMessage !== ''): ?>
                <div class="message success"><?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>

            <?php if ($book && $error === '' && $successMessage === ''): ?>
                <form method="POST" action="exchange_request.php?book_id=<?php echo (int) $bookId; ?>"><?php echo csrf_field(); ?>
                    <label for="message">Message to Book Owner</label>
                    <textarea id="message" name="message" placeholder="Write a friendly message about the exchange."><?php echo htmlspecialchars($message); ?></textarea>
                    <div class="actions">
                        <button type="submit">Send Exchange Request</button>
                        <a class="button back-button" href="browse_books.php">Back to Browse Books</a>
                    </div>
                </form>
            <?php else: ?>
                <div class="actions"><a class="button back-button" href="browse_books.php">Back to Browse Books</a></div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
